<?php
/**
 * Faz 6B3 Düzeltme — run durumu + audit olayı ATOMİK finalizasyonu.
 *
 * Birbirine ait bir audit olayı ile run durum geçişi AYNI transaction
 * içinde birlikte commit edilir ya da birlikte geri alınır; şu yarım
 * durumlar oluşamaz: "audit completed ama run running", "audit started ama
 * run planned", "rollback completed audit'i var ama run rolling_back".
 *
 * `settle()` bir DENEME ZİNCİRİ çalıştırır: ilk deneme hedef geçiştir (ör.
 * running -> completed + run_completed); başarısız olursa güvenli geçişler
 * sırayla denenir (ör. running -> rollback_required + run_failed, en son
 * yalnız durum). Sonuç nesnesi hedef durumu UYDURMAZ: her zaman deponun
 * gerçek kalıcı durumu yeniden okunup raporlanır. Hiçbir deneme
 * uygulanamazsa çağıran açık fail-closed hata ve gerçek durumu döndürür.
 *
 * WordPress fonksiyonu çağırmaz; yalnız enjekte edilen arayüzleri kullanır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Run_Finalizer {

	/** @var MaviBelge_Core_Import_Run_Store */
	private $store;
	/** @var MaviBelge_Core_Import_Audit_Sink */
	private $audit;
	/** @var MaviBelge_Core_Import_Transaction */
	private $tx;

	public function __construct( MaviBelge_Core_Import_Run_Store $store, MaviBelge_Core_Import_Audit_Sink $audit, MaviBelge_Core_Import_Transaction $tx ) {
		$this->store = $store;
		$this->audit = $audit;
		$this->tx    = $tx;
	}

	/**
	 * Tek atomik geçiş: durum geçişi + (varsa) audit olayı aynı transaction'da.
	 * `$event` null ise yalnız durum geçişi yapılır (audit'siz güvenli geri çekilme;
	 * transaction gerektirmez). Başarısızlıkta transaction geri alınır; kısmi durum bırakılmaz.
	 *
	 * @param array       $run    `id` ve `uid` taşıyan run satırı.
	 * @param string|null $event  MaviBelge_Core_Audit_Log::EVENT_IMPORT_* veya null.
	 * @param array       $fields transition() alanları (yalnız error_code).
	 * @return bool
	 */
	public function transition_atomic( array $run, $from, $to, array $fields, $event, array $context ) {
		if ( null === $event ) {
			// Audit'siz güvenli geri çekilme: tek UPDATE zaten atomiktir; transaction altyapısı (BEGIN/COMMIT)
			// bozukken bile run kalıcı olarak güvenli duruma alınabilmelidir.
			return true === $this->store->transition( $run['id'], $from, $to, $fields );
		}
		if ( true !== $this->tx->begin() ) {
			return false;
		}
		$ok = false;
		try {
			$ok = $this->store->transition( $run['id'], $from, $to, $fields )
				&& ( null === $event || true === $this->audit->record( $event, $run['id'], $context ) );
			$ok = $ok && true === $this->tx->commit();
		} catch ( Throwable $e ) {
			$ok = false;
		}
		if ( ! $ok ) {
			$this->tx->rollback();
		}
		return $ok;
	}

	/**
	 * Deneme zincirini sırayla dener; ilk başarılı denemede durur.
	 *
	 * @param array $run     `id` ve `uid` taşıyan run satırı.
	 * @param array $attempts Her eleman: array( 'to' => string, 'fields' => array, 'event' => string|null, 'context' => array )
	 * @return array{ok: bool, attempt: int|null, status: string|null}
	 *   ok: İLK (hedef) deneme uygulandı; attempt: uygulanan denemenin indeksi
	 *   (hiçbiri uygulanmadıysa null); status: deponun GERÇEK kalıcı durumu
	 *   (okunamazsa null).
	 */
	public function settle( array $run, $from, array $attempts ) {
		$applied = null;
		foreach ( $attempts as $index => $attempt ) {
			$event = array_key_exists( 'event', $attempt ) ? $attempt['event'] : null;
			$ctx   = isset( $attempt['context'] ) ? $attempt['context'] : array();
			$flds  = isset( $attempt['fields'] ) ? $attempt['fields'] : array();
			if ( $this->transition_atomic( $run, $from, $attempt['to'], $flds, $event, $ctx ) ) {
				$applied = $index;
				break;
			}
		}
		return array( 'ok' => 0 === $applied, 'attempt' => $applied, 'status' => $this->actual_status( $run['uid'] ) );
	}

	/** @return string|null Deponun gerçek kalıcı durumu. */
	public function actual_status( $uid ) {
		$row = $this->store->get_run( $uid );
		return is_array( $row ) && isset( $row['status'] ) && is_string( $row['status'] ) ? $row['status'] : null;
	}
}
