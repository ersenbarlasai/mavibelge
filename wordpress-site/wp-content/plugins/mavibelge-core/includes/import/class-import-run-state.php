<?php
/**
 * Faz 6B3 — import run durum makinesi (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Geçişler KAPALIDIR: yalnız TRANSITIONS tablosundaki geçişler izinlidir;
 * terminal durumlardan (failed, rolled_back) çıkış yoktur. Run deposu her
 * geçişi karşılaştır-ve-değiştir (compare-and-set) ile uygular.
 *
 * - planned           : run oluşturuldu, henüz hiçbir batch başlamadı.
 * - running           : batch'ler işleniyor (kilit tutuluyor).
 * - failed            : hiçbir batch commit edilmeden başarısız oldu (geri alınacak veri yok).
 * - completed         : bütün batch'ler commit edildi, audit tamam.
 * - rollback_required : en az bir batch commit edildikten sonra başarısız oldu;
 *                       önceki batch'ler yalnız doğrulanmış rollback kayıtlarıyla geri alınabilir.
 * - rolling_back      : rollback işleniyor (kilit tutuluyor).
 * - rolled_back       : bütün item'lar geri alındı.
 * - rollback_failed   : rollback reddedildi veya kısmen tamamlandı; manuel inceleme
 *                       gerekir, koşullar düzeltildikten sonra yeniden denenebilir.
 *
 * Faz 6B4 — kesintiye dayanıklı (resumable) akış için dört bekleme durumu eklendi:
 * - ready            : plan snapshot'ı kaydedildi, henüz hiçbir batch başlamadı (apply).
 * - paused           : en az bir batch commit edildi, sonraki batch bekleniyor (apply; işlem yok).
 * - rollback_ready   : rollback onaylandı ve başlatıldı, henüz hiçbir rollback batch'i çalışmadı.
 * - rollback_paused  : en az bir rollback batch'i commit edildi, sonraki bekleniyor.
 * Bağlayıcı akışlar: ready -> running -> paused -> running -> completed;
 * rollback_ready -> rolling_back -> rollback_paused -> rolling_back -> rolled_back.
 * `planned` yalnız eski (tek süreçli CLI) yolu için korunur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Run_State {

	const PLANNED           = 'planned';
	const READY             = 'ready';
	const RUNNING           = 'running';
	const PAUSED            = 'paused';
	const FAILED            = 'failed';
	const COMPLETED         = 'completed';
	const ROLLBACK_REQUIRED = 'rollback_required';
	const ROLLBACK_READY    = 'rollback_ready';
	const ROLLING_BACK      = 'rolling_back';
	const ROLLBACK_PAUSED   = 'rollback_paused';
	const ROLLED_BACK       = 'rolled_back';
	const ROLLBACK_FAILED   = 'rollback_failed';

	const ALL = array( 'planned', 'ready', 'running', 'paused', 'failed', 'completed', 'rollback_required', 'rollback_ready', 'rolling_back', 'rollback_paused', 'rolled_back', 'rollback_failed' );

	/** Kaynak durum => izinli hedef durumlar. */
	const TRANSITIONS = array(
		'planned'           => array( 'running', 'failed' ),
		'ready'             => array( 'running', 'failed' ),
		'running'           => array( 'paused', 'completed', 'failed', 'rollback_required' ),
		'paused'            => array( 'running', 'failed', 'rollback_required' ),
		'failed'            => array(),
		'completed'         => array( 'rolling_back', 'rollback_ready' ),
		'rollback_required' => array( 'rolling_back', 'rollback_ready' ),
		'rollback_ready'    => array( 'rolling_back', 'rollback_failed' ),
		'rolling_back'      => array( 'rollback_paused', 'rolled_back', 'rollback_failed' ),
		'rollback_paused'   => array( 'rolling_back', 'rollback_failed' ),
		'rolled_back'       => array(),
		'rollback_failed'   => array( 'rolling_back', 'rollback_ready' ),
	);

	/** Yeni bir rollback BAŞLATILABİLECEK durumlar. */
	const ROLLBACKABLE = array( 'completed', 'rollback_required', 'rollback_failed' );

	/** Başlatılmış rollback'in DEVAM ettirilebileceği bekleme durumları. */
	const ROLLBACK_RESUMABLE = array( 'rollback_ready', 'rollback_paused' );

	/** Apply'ın devam ettirilebileceği bekleme durumları. */
	const APPLY_RESUMABLE = array( 'ready', 'paused' );

	/** Kilit tutan (etkin) durumlar — kilit alındığında bu durumdaki run bayattır. Bekleme durumları etkin DEĞİLDİR. */
	const ACTIVE = array( 'running', 'rolling_back' );

	/** Yeni bir apply'ı engelleyen, çözülmemiş durumlar. */
	const BLOCKS_NEW_APPLY = array( 'ready', 'running', 'paused', 'rollback_required', 'rollback_ready', 'rolling_back', 'rollback_paused' );

	/**
	 * @param mixed $from
	 * @param mixed $to
	 * @return bool
	 */
	public static function can_transition( $from, $to ) {
		if ( ! is_string( $from ) || ! is_string( $to ) || ! array_key_exists( $from, self::TRANSITIONS ) ) {
			return false;
		}
		return in_array( $to, self::TRANSITIONS[ $from ], true );
	}
}
