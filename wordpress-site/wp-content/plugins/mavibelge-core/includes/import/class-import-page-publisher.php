<?php
/**
 * Faz 12 — sayfa YAYINLAMA: `pages` aşamasının oluşturduğu TASLAK sayfaları, ayrı ve açık onaylı bir işlemle yayınlar.
 *
 * Neden ayrı: sayfa oluşturma (apply) HİÇBİR ZAMAN yayınlamaz; yayınlama kurum kararlarına bağlıdır. Bu sınıf yeni bir karar motoru
 * DEĞİLDİR: satır durumları mevcut tek karar motorundan (`Dry_Run_Service::observe_record()`) gelir.
 *
 * Bir sayfa YALNIZ şu koşulların TÜMÜ sağlanırsa yayınlanabilir ("ready"):
 *  - hedef sayfa var ve import tarafından oluşturulmuş (marker + hash geçerli),
 *  - yönetilen alanlar son uygulanan haliyle BİREBİR (karar `unchanged`; elle değiştirilmiş/çakışmalı sayfa yayınlanmaz),
 *  - `publish_hold` false (bekleyen BLOKLAYICI kurum kararı yok: KVKK/gizlilik metni, banka bilgileri, sınav takvimi/MYK sorgu
 *    bağlantısı, gerçek referanslar, onaylı SSS),
 *  - şu anki durum `draft`.
 * Bekleyen bloklayıcı kararı olan sayfalar `held_pending_decision` olarak görünür ve ATLANIR (kullanıcı kararı gelene kadar taslak).
 *
 * Güvenlik: onay özeti (`plan_digest`) her satırın kimliğini/hash'ini ve yayınlanabilirlik durumunu kapsar (yayın durumunu KAPSAMAZ:
 * sayfalar yayınlandıkça özet değişmez); onay ifadesi `YAYINLA <özet ilk 12>`; istek başına en çok 10 sayfa; her batch tek transaction
 * (TOCTOU yeniden kontrol -> yayın -> katı readback -> audit -> commit); `expected_remaining` çift tıklama/bayat isteği reddeder;
 * çözülmemiş bir import run'ı varken yayınlanmaz. Yalnız `draft -> publish` geçişi yapılır; başka durum/alan değişmez.
 * Import rollback'i yayınlanmış sayfalar için drift nedeniyle reddedilir (kullanıcıya görünen yayın korunur).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Page_Publisher {

	/** İstek başına en çok yayınlanan sayfa. */
	const BATCH_LIMIT = 10;

	const PLAN_SCHEMA = 'mavibelge-page-publish-plan/1';

	/** Satır nedenleri (kapalı küme). */
	const REASONS = array( 'ready', 'published', 'held_pending_decision', 'content_not_ready', 'not_created', 'not_unchanged', 'unexpected_status' );

	/** Bekleyen karar kodu => Türkçe etiket (tools/import/lib/page-inventory.js PENDING_CODES ile AYNI; statik test eşitliği doğrular). */
	const PENDING_LABELS = array(
		'form_gate_institution_decisions' => 'Form kapısı: KVKK metni, alıcı e-posta, hassas alan/dosya yükleme onayı',
		'location_data_pending'           => 'Güncel lokasyon/iletişim bilgilerinin kurum onayı',
		'fee_tariff_documents_pending'    => 'Kaynak tarife PDF belgeleri (medya kütüphanesi eşlemesi)',
		'accreditation_documents_pending' => 'MYK/TÜRKAK yetki belgesi taramaları ve logo görselleri',
		'legislation_links_pending'       => 'Mevzuat metinlerinin doğrulanmış bağlantıları',
		'static_counter_block_omitted'    => 'Statik sayaç bloğu (12 sektör) çelişkili olduğu için aktarılmadı',
	);

	/** @var MaviBelge_Core_Import_Dry_Run_Service */
	private $dryRun;
	/** @var MaviBelge_Core_Import_Target_Writer */
	private $writer;
	/** @var MaviBelge_Core_Import_Transaction */
	private $tx;
	/** @var MaviBelge_Core_Import_Run_Store */
	private $store;
	/** Bekleyen karar kodu için görünür özet (etiket + bloklayıcı mı); bloklayıcılık kaynağı Record_Validator sözlüğüdür. */
	public static function pending_view( $code ) {
		return array( 'code' => $code, 'label' => self::PENDING_LABELS[ $code ], 'blocking' => MaviBelge_Core_Import_Record_Validator::PAGE_PENDING_DECISIONS[ $code ] );
	}

	/** @var MaviBelge_Core_Import_Audit_Sink */
	private $audit;

	/** @var array<string,bool> Bir önizleme/yayın turu içinde içerik türü hazır mı (memo; her turda sıfırlanır). */
	private $contentReadyMemo = array();

	public function __construct(
		MaviBelge_Core_Import_Dry_Run_Service $dryRun,
		MaviBelge_Core_Import_Target_Writer $writer,
		MaviBelge_Core_Import_Transaction $tx,
		MaviBelge_Core_Import_Run_Store $store,
		MaviBelge_Core_Import_Audit_Sink $audit
	) {
		$this->dryRun = $dryRun;
		$this->writer = $writer;
		$this->tx     = $tx;
		$this->store  = $store;
		$this->audit  = $audit;
	}

	/** "YAYINLA <özet ilk 12>" — kullanıcının BİREBİR yazması gereken ifade. */
	public static function confirm_phrase( $digest ) {
		return MaviBelge_Core_Import_Apply_Plan::is_digest( $digest ) ? 'YAYINLA ' . substr( $digest, 0, 12 ) : null;
	}

	/**
	 * SALT OKUNUR yayın önizlemesi: her sayfa için başlık, slug, kaynak, bekleyen kararlar ve yayınlanabilirlik.
	 *
	 * @return array{ok: bool, error_code: string|null, plan_digest: string|null, rows: array[], summary: array}
	 */
	public function preview() {
		$this->contentReadyMemo = array();
		$ctx = $this->dryRun->run_stage( MaviBelge_Core_Import_Apply_Plan::STAGE_PAGES );
		if ( ! $ctx['ok'] || ! is_array( $ctx['manifest'] ) || ! isset( $ctx['manifest']['pages'] ) ) {
			return self::preview_failed( 'plan_not_available' );
		}
		$rows      = array();
		$digestRow = array();
		$records   = array();
		foreach ( $ctx['manifest']['pages'] as $record ) {
			$records[ $record['source_key'] ] = $record;
			$obs   = $this->dryRun->observe_record( 'page', $record );
			$entry = $obs['entry'];
			$id    = is_int( $entry['target_id'] ) ? $entry['target_id'] : null;
			$status = null === $id ? null : $this->writer->page_status( $id );
			if ( true === $record['publish_hold'] ) {
				$reason = 'held_pending_decision';
			} elseif ( null === $id || 'create' === $entry['decision'] ) {
				$reason = 'not_created';
			} elseif ( 'unchanged' !== $entry['decision'] ) {
				$reason = 'not_unchanged';
			} elseif ( 'draft' === $status ) {
				// İçerik bağımlılığı (SSS/referans kayıtları) tamam değilse sayfa YAYINLANAMAZ (sunucu tarafı kapı).
				$reason = ( array() === $record['publish_requires'] || $this->requirements_met( $record['publish_requires'] ) ) ? 'ready' : 'content_not_ready';
			} elseif ( 'publish' === $status ) {
				$reason = 'published';
			} else {
				$reason = 'unexpected_status';
			}
			$rows[] = array(
				'source_key'        => $record['source_key'],
				'slug'              => $record['slug'],
				'title'             => $record['title'],
				'source_file'       => $record['source']['file'],
				'pending_decisions' => $record['pending_decisions'],
				'publish_hold'      => $record['publish_hold'],
				'publish_requires'  => $record['publish_requires'],
				'status'            => $status,
				'reason'            => $reason,
				'target_id'         => $id,
			);
			// Özet, "yayınlanabilir mi" bilgisini taşır ama ANLIK yayın durumunu taşımaz (ready == published): ilerledikçe değişmez.
			$digestRow[] = array( $record['source_key'], $id, $entry['incoming_hash'], in_array( $reason, array( 'ready', 'published' ), true ) ? 'publishable' : $reason );
		}
		$digest = null;
		try {
			$digest = MaviBelge_Core_Import_Hash::hash( array( 'schema' => self::PLAN_SCHEMA, 'manifest' => $ctx['manifest_digest'], 'rows' => $digestRow ) );
		} catch ( InvalidArgumentException $e ) {
			return self::preview_failed( 'plan_not_available' );
		}
		$summary = array( 'total' => count( $rows ), 'ready' => 0, 'published' => 0, 'held_pending_decision' => 0, 'content_not_ready' => 0, 'not_created' => 0, 'not_unchanged' => 0, 'unexpected_status' => 0 );
		foreach ( $rows as $row ) {
			++$summary[ $row['reason'] ];
		}
		return array( 'ok' => true, 'error_code' => null, 'plan_digest' => $digest, 'rows' => $rows, 'summary' => $summary, 'records' => $records );
	}

	/**
	 * En çok BATCH_LIMIT hazır sayfayı yayınlar (tek transaction). Çağıran katman (admin servisi) onay ifadesini doğrular.
	 *
	 * @param mixed $confirmDigest     Kullanıcının önizlemede gördüğü plan_digest.
	 * @param mixed $expectedRemaining Kullanıcının gördüğü "yayınlanmayı bekleyen hazır sayfa" sayısı (bayat/çift istek koruması).
	 * @return array{ok: bool, status: string, error_code: string|null, published: int, remaining: int, plan_digest: string|null}
	 */
	public function publish( $confirmDigest, $expectedRemaining, $userId ) {
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $confirmDigest ) || ! is_int( $expectedRemaining ) || $expectedRemaining < 0 ) {
			return self::result( false, 'rejected', 'invalid_confirmation', 0, 0, null );
		}
		$preflight = $this->tx->preflight();
		if ( ! is_array( $preflight ) || true !== $preflight['ok'] || ! $this->audit->ready() ) {
			return self::result( false, 'rejected', 'infrastructure_unavailable', 0, 0, $confirmDigest );
		}
		if ( ! $this->store->acquire_lock() ) {
			return self::result( false, 'rejected', 'locked', 0, 0, $confirmDigest );
		}
		try {
			if ( ! empty( $this->store->find_runs_in_status( MaviBelge_Core_Import_Run_State::BLOCKS_NEW_APPLY ) ) ) {
				return self::result( false, 'rejected', 'unresolved_run_exists', 0, 0, $confirmDigest );
			}
			$pv = $this->preview();
			if ( ! $pv['ok'] ) {
				return self::result( false, 'rejected', 'plan_not_available', 0, 0, $confirmDigest );
			}
			if ( $pv['plan_digest'] !== $confirmDigest ) {
				return self::result( false, 'rejected', 'confirmation_mismatch', 0, $pv['summary']['ready'], $confirmDigest );
			}
			if ( $pv['summary']['ready'] !== $expectedRemaining ) {
				return self::result( false, 'rejected', 'stale_request', 0, $pv['summary']['ready'], $confirmDigest );
			}
			$ready = array();
			foreach ( $pv['rows'] as $row ) {
				if ( 'ready' === $row['reason'] ) {
					$ready[] = $row;
				}
			}
			if ( array() === $ready ) {
				return self::result( true, 'completed', null, 0, 0, $confirmDigest );
			}
			$batch = array_slice( $ready, 0, self::BATCH_LIMIT );
			return $this->publish_batch( $batch, count( $ready ), $confirmDigest, $pv['records'] );
		} finally {
			$this->store->release_lock();
		}
	}

	private function publish_batch( array $batch, $readyBefore, $digest, array $records ) {
		if ( true !== $this->tx->begin() ) {
			return self::result( false, 'failed', 'transaction_begin_failed', 0, $readyBefore, $digest );
		}
		$auditItems = array();
		$error      = null;
		foreach ( $batch as $row ) {
			$record = isset( $records[ $row['source_key'] ] ) ? $records[ $row['source_key'] ] : null;
			if ( null === $record ) {
				$error = 'toctou_drift';
				break;
			}
			// TOCTOU: yazmadan HEMEN önce aynı karar yoluyla yeniden gözle.
			$before = $this->dryRun->observe_record( 'page', $record );
			$bid    = $before['entry']['target_id'];
			if ( 'unchanged' !== $before['entry']['decision'] || $bid !== $row['target_id'] || 'draft' !== $this->writer->page_status( $bid ) ) {
				$error = 'toctou_drift';
				break;
			}
			// İçerik bağımlılığı yayın anında YENİDEN doğrulanır (memo sıfırlanır): önizlemeden sonra SSS/referans kayıtları bozulduysa/geri alındıysa yayın YAPILMAZ.
			$this->contentReadyMemo = array();
			if ( array() !== $record['publish_requires'] && ! $this->requirements_met( $record['publish_requires'] ) ) {
				$error = 'content_not_ready';
				break;
			}
			$res = $this->writer->publish_page( $bid );
			if ( ! is_array( $res ) || true !== $res['ok'] ) {
				$error = 'write_failed';
				break;
			}
			// Katı readback: durum publish, yönetilen alanlar hâlâ birebir (karar unchanged).
			$after = $this->dryRun->observe_record( 'page', $record );
			if ( 'unchanged' !== $after['entry']['decision'] || $after['entry']['target_id'] !== $bid || 'publish' !== $this->writer->page_status( $bid ) ) {
				$error = 'readback_mismatch';
				break;
			}
			$auditItems[] = array( 'source_key' => $row['source_key'], 'type' => 'page', 'target_id' => $bid );
		}
		if ( null === $error && true !== $this->audit->record( MaviBelge_Core_Audit_Log::EVENT_IMPORT_PAGES_PUBLISHED, 0, array( 'items' => $auditItems ) ) ) {
			$error = 'audit_failed';
		}
		if ( null === $error && true !== $this->tx->commit() ) {
			$error = 'commit_failed';
		}
		if ( null !== $error ) {
			$rolledBack = true === $this->tx->rollback();
			return self::result( false, 'failed', $rolledBack ? $error : 'transaction_rollback_failed', 0, $readyBefore, $digest );
		}
		$remaining = $readyBefore - count( $batch );
		return self::result( true, $remaining > 0 ? 'paused' : 'completed', null, count( $batch ), $remaining, $digest );
	}

	/** İçerik bağımlılıklarının (faq / reference) TAMAMI hazır mı. */
	private function requirements_met( array $types ) {
		foreach ( $types as $type ) {
			if ( ! $this->content_type_ready( $type ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Bir içerik türünün TÜM manifest kayıtları gerçek WordPress'te (mevcut karar motoruyla) `unchanged` ve YAYINDA mı.
	 * unchanged: kayıt var, marker/hash geçerli, alanlar manifestle birebir (referans için bağlı attachment'ın gerçek dosya özeti logo
	 * özetine eşit = geçerli ve erişilebilir logo). Manifest yüklenemiyorsa veya liste boşsa false (fail-closed).
	 */
	private function content_type_ready( $type ) {
		if ( isset( $this->contentReadyMemo[ $type ] ) ) {
			return $this->contentReadyMemo[ $type ];
		}
		$lists = array( 'faq' => 'faqs', 'reference' => 'references' );
		$ready = false;
		if ( isset( $lists[ $type ] ) ) {
			$ctx = $this->dryRun->run_stage( MaviBelge_Core_Import_Apply_Plan::STAGE_CONTENT );
			if ( $ctx['ok'] && is_array( $ctx['manifest'] ) && isset( $ctx['manifest'][ $lists[ $type ] ] ) && is_array( $ctx['manifest'][ $lists[ $type ] ] ) && array() !== $ctx['manifest'][ $lists[ $type ] ] ) {
				$ready = true;
				foreach ( $ctx['manifest'][ $lists[ $type ] ] as $record ) {
					$obs = $this->dryRun->observe_record( $type, $record );
					$id  = $obs['entry']['target_id'];
					if ( 'unchanged' !== $obs['entry']['decision'] || ! is_int( $id ) || 'publish' !== $this->writer->content_post_status( $type, $id ) ) {
						$ready = false;
						break;
					}
				}
			}
		}
		$this->contentReadyMemo[ $type ] = $ready;
		return $ready;
	}

	private static function preview_failed( $code ) {
		return array( 'ok' => false, 'error_code' => $code, 'plan_digest' => null, 'rows' => array(), 'records' => array(), 'summary' => array( 'total' => 0, 'ready' => 0, 'published' => 0, 'held_pending_decision' => 0, 'content_not_ready' => 0, 'not_created' => 0, 'not_unchanged' => 0, 'unexpected_status' => 0 ) );
	}

	private static function result( $ok, $status, $errorCode, $published, $remaining, $digest ) {
		return array( 'ok' => $ok, 'status' => $status, 'error_code' => $errorCode, 'published' => $published, 'remaining' => $remaining, 'plan_digest' => $digest );
	}
}
