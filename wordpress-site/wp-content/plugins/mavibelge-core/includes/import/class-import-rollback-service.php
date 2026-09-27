<?php
/**
 * Faz 6B3 — import run ROLLBACK servisi.
 *
 * WordPress fonksiyonu ÇAĞIRMAZ; okuma/yazma enjekte edilen arayüzlerin
 * arkasındadır. Tek rollback doğrulayıcısı
 * `MaviBelge_Core_Import_Apply_Eligibility::validate_rollback_record()`'tur
 * (kodek üzerinden); doğrulanmayan bir kayıtla HİÇBİR işlem yapılmaz.
 *
 * Kurallar:
 * - Önce bütün bekleyen item'lar SALT OKUNUR ön kontrolden geçer; tek bir
 *   engel (kullanıcı değişikliği/drift, tür/hedef uyuşmazlığı, sektör
 *   teriminin run dışı bağımlısı) varsa hiçbir şey yazılmaz, run
 *   `rollback_failed` olur ve kullanıcı değişikliği korunur.
 * - Sıra ücret -> yeterlilik -> sektör (bağımlılığın tersi).
 * - update: yalnız `old_fields` (yönetilen alanlar) ve eski marker/hash geri
 *   yazılır; katı readback + yönetilmeyen alan parmak izi karşılaştırması.
 * - create: hem yönetilen alan hash'i hem create'ten HEMEN SONRA kaydedilen
 *   `unmanaged_fingerprint` (post_content/excerpt/SEO metası/yönetilmeyen
 *   taksonomi; sektörde parent/term_group/yönetilmeyen term meta) hâlâ
 *   eşleşmelidir; uyuşmazlık `drift_detected`, hiçbir post çöpe gitmez ve
 *   hiçbir terim silinmez (kullanıcı değişikliği korunur).
 * - create post: dar `rollback_created_post()` operasyonu — yalnız import
 *   tarafından yönetilen meta/sektör ilişkisi temizlenir (marker + doğal
 *   anahtar kalmaz, aynı manifest yeniden uygulanabilir), sonra çöp kutusuna
 *   alınır (kalıcı silinmez; EMPTY_TRASH_DAYS=0 fail-closed).
 * - create sektör terimi: WordPress'te terimlerin çöp kutusu YOKTUR; terim
 *   yalnız bu run oluşturduysa, hash hâlâ eşleşiyorsa, alt terimi yoksa ve
 *   ona bağlı/işaret eden her post çöp kutusundaysa VE ya bu run'ın ya da
 *   rollback'i tamamlanmış başka bir import create'inin postuysa silinir;
 *   aksi hâlde silinmez, rollback `rollback_failed` (manuel inceleme) olur.
 * - Her batch kendi transaction'ında; kısmi rollback AÇIKÇA hatadır
 *   (`rollback_failed`), geri alınmış item'lar işaretlidir ve yeniden deneme
 *   yalnız kalan item'ları işler.
 * - Audit olayı + durum geçişi AYNI transaction'da (Run_Finalizer): "rollback
 *   completed audit'i var ama run rolling_back" oluşamaz; sonuç nesnesi
 *   deponun GERÇEK durumunu raporlar.
 * Rollback veritabanı yedeğinin yerine GEÇMEZ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Rollback_Service {

	/** @var MaviBelge_Core_Import_Target_Repository */
	private $repository;
	/** @var MaviBelge_Core_Import_Target_Writer */
	private $writer;
	/** @var MaviBelge_Core_Import_Transaction */
	private $tx;
	/** @var MaviBelge_Core_Import_Run_Store */
	private $store;
	/** @var MaviBelge_Core_Import_Audit_Sink */
	private $audit;
	/** @var MaviBelge_Core_Import_Run_Finalizer */
	private $finalizer;

	public function __construct(
		MaviBelge_Core_Import_Target_Repository $repository,
		MaviBelge_Core_Import_Target_Writer $writer,
		MaviBelge_Core_Import_Transaction $tx,
		MaviBelge_Core_Import_Run_Store $store,
		MaviBelge_Core_Import_Audit_Sink $audit
	) {
		$this->repository = $repository;
		$this->writer     = $writer;
		$this->tx         = $tx;
		$this->store      = $store;
		$this->audit      = $audit;
		$this->finalizer  = new MaviBelge_Core_Import_Run_Finalizer( $store, $audit, $tx );
	}

	/**
	 * SALT OKUNUR önizleme: bekleyen item'lar, onaylanacak rollback_digest ve engeller.
	 *
	 * @param mixed $uid
	 * @return array
	 */
	public function preview( $uid ) {
		$load = $this->load( $uid );
		if ( null !== $load['error'] ) {
			return array( 'ok' => false, 'error_code' => $load['error'], 'run_uid' => null, 'status' => null, 'items_pending' => 0, 'rollback_digest' => null, 'blockers' => array() );
		}
		$blockers = self::startable( $load['run']['status'] )
			? $this->preflight( $load['run'], $load['items'] )
			: array( array( 'source_key' => null, 'code' => 'run_not_rollbackable' ) );
		return array(
			'ok'              => true,
			'error_code'      => null,
			'run_uid'         => $load['run']['uid'],
			'status'          => $load['run']['status'],
			'items_pending'   => count( $load['items'] ),
			'rollback_digest' => $load['digest'],
			'blockers'        => $blockers,
		);
	}

	/**
	 * @param mixed $uid
	 * @param mixed $confirmDigest preview()'ın rollback_digest'i.
	 * @param mixed $batchSize null => varsayılan.
	 * @return array{ok: bool, status: string|null, error_code: string|null, errors: string[], run_uid: string|null, rolled_back_items: int}
	 */
	public function rollback( $uid, $confirmDigest, $batchSize = null ) {
		$size = MaviBelge_Core_Import_Apply_Plan::normalize_batch_size( $batchSize );
		if ( null === $size ) {
			return self::result( false, null, 'invalid_batch_size' );
		}
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $confirmDigest ) ) {
			return self::result( false, null, 'invalid_confirmation' );
		}
		$load = $this->load( $uid );
		if ( null !== $load['error'] ) {
			return self::result( false, null, $load['error'] );
		}
		if ( ! self::startable( $load['run']['status'] ) ) {
			return self::result( false, $load['run']['status'], 'run_not_rollbackable', array(), $load['run']['uid'] );
		}
		if ( $load['digest'] !== $confirmDigest ) {
			return self::result( false, $load['run']['status'], 'confirmation_mismatch', array(), $load['run']['uid'] );
		}
		$preflight = $this->tx->preflight();
		if ( ! is_array( $preflight ) || true !== $preflight['ok'] || ! $this->audit->ready() ) {
			return self::result( false, $load['run']['status'], 'infrastructure_unavailable', array(), $load['run']['uid'] );
		}
		if ( ! $this->store->acquire_lock() ) {
			return self::result( false, $load['run']['status'], 'locked', array(), $load['run']['uid'] );
		}
		try {
			MaviBelge_Core_Import_Apply_Service::expire_stale_runs( $this->store, $this->audit, $this->tx );
			$load = $this->load( $uid );
			if ( null !== $load['error'] || $load['digest'] !== $confirmDigest ) {
				return self::result( false, null, null !== $load['error'] ? $load['error'] : 'confirmation_mismatch' );
			}
			return $this->execute( $load['run'], $load['items'], $size );
		} finally {
			$this->store->release_lock();
		}
	}

	/**
	 * Faz 6B4 — RESUMABLE rollback, adım 1: önizlemede gösterilen digest'i onaylanmış bir rollback'i BAŞLATIR. Bütün
	 * kalan item'lar salt okunur doğrulanır (tek engel bile varsa run durumu DEĞİŞMEZ ve hiçbir şey geri alınmaz);
	 * geçerse run `rollback_ready` olur (rollback_started audit'i ile aynı transaction'da). Henüz hiçbir kayıt geri alınmaz.
	 *
	 * @param mixed $uid
	 * @param mixed $confirmDigest preview()'ın rollback_digest'i.
	 * @return array Bkz. resumable_result().
	 */
	public function start_resumable( $uid, $confirmDigest ) {
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $confirmDigest ) ) {
			return self::resumable_result( false, null, 'invalid_confirmation', null, 0, 0, 0 );
		}
		$load = $this->load( $uid );
		if ( null !== $load['error'] ) {
			return self::resumable_result( false, null, $load['error'], null, 0, 0, 0 );
		}
		if ( ! in_array( $load['run']['status'], MaviBelge_Core_Import_Run_State::ROLLBACKABLE, true ) ) {
			return self::resumable_from_run( $load['run'], count( $load['items'] ), 'run_not_rollbackable' );
		}
		if ( $load['digest'] !== $confirmDigest ) {
			return self::resumable_from_run( $load['run'], count( $load['items'] ), 'confirmation_mismatch' );
		}
		$preflight = $this->tx->preflight();
		if ( ! is_array( $preflight ) || true !== $preflight['ok'] || ! $this->audit->ready() ) {
			return self::resumable_from_run( $load['run'], count( $load['items'] ), 'infrastructure_unavailable' );
		}
		if ( ! $this->store->acquire_lock() ) {
			return self::resumable_from_run( $load['run'], count( $load['items'] ), 'locked' );
		}
		try {
			MaviBelge_Core_Import_Apply_Service::expire_stale_runs( $this->store, $this->audit, $this->tx );
			$load = $this->load( $uid );
			if ( null !== $load['error'] ) {
				return self::resumable_result( false, null, $load['error'], null, 0, 0, 0 );
			}
			if ( ! in_array( $load['run']['status'], MaviBelge_Core_Import_Run_State::ROLLBACKABLE, true ) ) {
				return self::resumable_from_run( $load['run'], count( $load['items'] ), 'run_not_rollbackable' );
			}
			if ( $load['digest'] !== $confirmDigest ) {
				return self::resumable_from_run( $load['run'], count( $load['items'] ), 'confirmation_mismatch' );
			}
			$blockers = $this->preflight( $load['run'], $load['items'] );
			if ( ! empty( $blockers ) ) {
				$result             = self::resumable_from_run( $load['run'], count( $load['items'] ), $blockers[0]['code'] );
				$result['blockers'] = $blockers;
				return $result;
			}
			$run = $load['run'];
			if ( ! $this->finalizer->transition_atomic( $run, $run['status'], MaviBelge_Core_Import_Run_State::ROLLBACK_READY, array(), MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_STARTED, array( 'run_id' => $run['uid'] ) ) ) {
				return self::resumable_result( false, $this->finalizer->actual_status( $run['uid'] ), 'state_transition_failed', $run['uid'], count( $load['items'] ), $run['rollback_items'], $run['rollback_batches'] );
			}
			return self::resumable_result( true, $this->finalizer->actual_status( $run['uid'] ), null, $run['uid'], count( $load['items'] ) + $run['rollback_items'], $run['rollback_items'], $run['rollback_batches'] );
		} finally {
			$this->store->release_lock();
		}
	}

	/**
	 * Faz 6B4 — RESUMABLE rollback, adım 2: SIRADAKİ tek rollback batch'ini (ters sırada, en çok `$batchSize` item)
	 * yürütür. `rollback_ready|rollback_paused` durumu ve `$expectedCheckpoint === run.rollback_batches` zorunludur.
	 * Her istekte KALAN bütün item'lar salt okunur yeniden doğrulanır: tek drift/engel bile varsa o istekte HİÇBİR
	 * kayıt geri alınmaz, run `rollback_failed` olur ve kullanıcı değişikliği korunur. Batch commit sonrası run
	 * `rollback_paused` veya `rolled_back` olur; sonuç deponun GERÇEK durumunu raporlar.
	 *
	 * @return array Bkz. resumable_result().
	 */
	public function advance_resumable( $uid, $expectedCheckpoint, $batchSize ) {
		if ( ! is_string( $uid ) || 1 !== preg_match( '/^[0-9a-f]{32}\z/', $uid ) || ! is_int( $expectedCheckpoint ) || $expectedCheckpoint < 0 ) {
			return self::resumable_result( false, null, 'invalid_request', null, 0, 0, 0 );
		}
		$size = MaviBelge_Core_Import_Apply_Plan::normalize_batch_size( $batchSize );
		if ( null === $size ) {
			return self::resumable_result( false, null, 'invalid_batch_size', null, 0, 0, 0 );
		}
		if ( ! $this->store->is_installed() ) {
			return self::resumable_result( false, null, 'run_not_found', null, 0, 0, 0 );
		}
		$preflight = $this->tx->preflight();
		if ( ! is_array( $preflight ) || true !== $preflight['ok'] || ! $this->audit->ready() ) {
			return self::resumable_result( false, null, 'infrastructure_unavailable', null, 0, 0, 0 );
		}
		if ( ! $this->store->acquire_lock() ) {
			return self::resumable_result( false, null, 'locked', null, 0, 0, 0 );
		}
		try {
			MaviBelge_Core_Import_Apply_Service::expire_stale_runs( $this->store, $this->audit, $this->tx );
			$load = $this->load( $uid );
			if ( null !== $load['error'] ) {
				return self::resumable_result( false, null, $load['error'], null, 0, 0, 0 );
			}
			$run     = $load['run'];
			$items   = $load['items'];
			$pending = count( $items );
			$total   = $pending + $run['rollback_items'];
			if ( ! in_array( $run['status'], MaviBelge_Core_Import_Run_State::ROLLBACK_RESUMABLE, true ) ) {
				return self::resumable_result( false, $run['status'], 'run_not_resumable', $run['uid'], $total, $run['rollback_items'], $run['rollback_batches'] );
			}
			if ( $run['rollback_batches'] !== $expectedCheckpoint ) {
				return self::resumable_result( false, $run['status'], 'stale_request', $run['uid'], $total, $run['rollback_items'], $run['rollback_batches'] );
			}
			// Bekleme durumundan çalışmaya geçiş compare-and-set'tir: eşzamanlı ikinci istek burada elenir.
			if ( ! $this->finalizer->transition_atomic( $run, $run['status'], MaviBelge_Core_Import_Run_State::ROLLING_BACK, array(), null, array() ) ) {
				return self::resumable_result( false, $this->finalizer->actual_status( $run['uid'] ), 'stale_request', $run['uid'], $total, $run['rollback_items'], $run['rollback_batches'] );
			}
			$batchNo  = $run['rollback_batches'] + 1;
			$blockers = $this->preflight( $run, $items );
			if ( ! empty( $blockers ) ) {
				return self::legacy_to_resumable( $this->fail( $run, $blockers[0]['code'], $batchNo, $run['rollback_items'], $blockers[0]['source_key'], $run['rollback_batches'] ), $run, $total );
			}
			if ( 0 === $pending ) {
				return self::legacy_to_resumable( $this->fail( $run, 'plan_items_invalid', $batchNo, $run['rollback_items'], null, $run['rollback_batches'] ), $run, $total );
			}
			$batch = array_slice( MaviBelge_Core_Import_Apply_Plan::rollback_order( $items ), 0, $size );
			if ( true !== $this->tx->begin() ) {
				return self::legacy_to_resumable( $this->fail( $run, 'transaction_begin_failed', $batchNo, $run['rollback_items'], null, $run['rollback_batches'] ), $run, $total );
			}
			$error    = null;
			$errorKey = null;
			try {
				if ( true !== $this->writer->begin_side_effect_scope() ) {
					$error = 'side_effect_scope_failed';
				}
				foreach ( null === $error ? $batch : array() as $item ) {
					$error = $this->rollback_item( $item, $items );
					if ( null === $error && true !== $this->store->mark_item_rolled_back( $item['id'] ) ) {
						$error = 'item_store_failed';
					}
					if ( null !== $error ) {
						$errorKey = $item['source_key'];
						break;
					}
				}
				if ( null === $error && true !== $this->store->record_rollback_checkpoint( $run['id'], $run['rollback_batches'], $run['rollback_batches'] + 1, $run['rollback_items'] + count( $batch ) ) ) {
					$error = 'checkpoint_failed';
				}
				if ( null === $error && true !== $this->tx->commit() ) {
					$error = 'commit_failed';
				}
			} catch ( Throwable $e ) {
				$error = 'unexpected_exception';
			}
			if ( null === $error ) {
				$this->writer->commit_side_effect_scope();
			}
			if ( null !== $error ) {
				$txRolledBack = true === $this->tx->rollback();
				$comp       = $this->writer->compensate_side_effect_scope( $txRolledBack );
				if ( ! $txRolledBack ) {
					$error = 'transaction_rollback_failed';
				} elseif ( true !== $comp['ok'] ) {
					$error = is_string( $comp['error'] ) ? $comp['error'] : 'side_effect_cleanup_failed';
				}
				return self::legacy_to_resumable( $this->fail( $run, $error, $batchNo, $run['rollback_items'], $errorKey, $run['rollback_batches'] ), $run, $total );
			}
			$batches   = $run['rollback_batches'] + 1;
			$rolledNow = $run['rollback_items'] + count( $batch );

			if ( $pending - count( $batch ) <= 0 ) {
				$settled = $this->finalizer->settle(
					$run,
					MaviBelge_Core_Import_Run_State::ROLLING_BACK,
					array(
						array( 'to' => MaviBelge_Core_Import_Run_State::ROLLED_BACK, 'event' => MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_COMPLETED, 'context' => array( 'run_id' => $run['uid'], 'checkpoint' => $batches ) ),
						array(
							'to'      => MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED,
							'fields'  => array( 'error_code' => 'finalization_failed' ),
							'event'   => MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_FAILED,
							'context' => array( 'run_id' => $run['uid'], 'error_code' => 'finalization_failed', 'batch_no' => 0, 'checkpoint' => $batches ),
						),
						array( 'to' => MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED, 'fields' => array( 'error_code' => 'finalization_failed' ) ),
					)
				);
				$ok = true === $settled['ok'];
				return self::resumable_result( $ok, $settled['status'], $ok ? null : 'finalization_failed', $run['uid'], $total, $rolledNow, $batches, $ok ? array() : self::settle_errors( $settled, MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED, 1 ) );
			}
			$paused = $this->finalizer->transition_atomic( $run, MaviBelge_Core_Import_Run_State::ROLLING_BACK, MaviBelge_Core_Import_Run_State::ROLLBACK_PAUSED, array(), null, array() );
			return self::resumable_result( $paused, $this->finalizer->actual_status( $run['uid'] ), $paused ? null : 'finalization_failed', $run['uid'], $total, $rolledNow, $batches );
		} finally {
			$this->store->release_lock();
		}
	}

	private static function legacy_to_resumable( array $legacy, array $run, $total ) {
		return self::resumable_result( $legacy['ok'], $legacy['status'], $legacy['error_code'], $run['uid'], $total, $legacy['rolled_back_items'], $run['rollback_batches'], $legacy['errors'] );
	}

	private static function resumable_from_run( array $run, $pending, $code ) {
		return self::resumable_result( false, $run['status'], $code, $run['uid'], $pending + $run['rollback_items'], $run['rollback_items'], $run['rollback_batches'] );
	}

	/**
	 * Güvenli sonuç (yalnız sayaç/durum/kod): checkpoint = commit edilmiş rollback batch sayısı, total = run'ın yazdığı
	 * item sayısı, committed = geri alınan item sayısı, remaining = total - committed.
	 */
	private static function resumable_result( $ok, $status, $code, $uid, $total, $rolledBack, $batches, array $errors = array() ) {
		$base                = self::result( $ok, $status, $code, $errors, $uid, $rolledBack );
		$base['checkpoint']  = $batches;
		$base['total']       = $total;
		$base['committed']   = $rolledBack;
		$base['remaining']   = max( 0, $total - $rolledBack );
		return $base;
	}

	/**
	 * Run'ı ve bekleyen item'larını yükler; HER rollback kaydı tek
	 * doğrulayıcıdan geçer ve item sütunlarıyla (source_key/tür/karar/hedef)
	 * birebir eşleşmelidir.
	 *
	 * @return array{error: string|null, run?: array, items?: array, digest?: string|null}
	 */
	private function load( $uid ) {
		if ( ! is_string( $uid ) || 1 !== preg_match( '/^[0-9a-f]{32}\z/', $uid ) || ! $this->store->is_installed() ) {
			return array( 'error' => 'run_not_found' );
		}
		$run = $this->store->get_run( $uid );
		if ( ! is_array( $run ) ) {
			return array( 'error' => 'run_not_found' );
		}
		$items = array();
		foreach ( $this->store->get_items( $run['id'] ) as $row ) {
			if ( 'rolled_back' === $row['rollback_status'] ) {
				continue;
			}
			$record = 'pending' === $row['rollback_status'] ? MaviBelge_Core_Import_Rollback_Codec::decode( $row['rollback_record'] ) : null;
			if ( null === $record || $record['source_key'] !== $row['source_key'] || $record['type'] !== $row['type']
				|| $record['decision'] !== $row['decision'] || $record['target_id'] !== $row['target_id'] ) {
				return array( 'error' => 'rollback_record_invalid' );
			}
			$row['record']   = $record;
			$row['new_hash'] = $record['new_hash'];
			$items[]         = $row;
		}
		return array(
			'error'  => null,
			'run'    => $run,
			'items'  => $items,
			'digest' => MaviBelge_Core_Import_Apply_Plan::rollback_digest( $run['uid'], $items ),
		);
	}

	/** @return array<int, array{source_key: string|null, code: string}> SALT OKUNUR engeller. */
	private function preflight( array $run, array $items ) {
		$blockers = array();
		foreach ( $items as $item ) {
			$code = $this->item_blocker( $item, $items, false );
			if ( null !== $code ) {
				$blockers[] = array( 'source_key' => $item['source_key'], 'code' => $code );
			}
		}
		return $blockers;
	}

	/**
	 * @param bool $atWrite true iken sektör silmeden hemen önceki katı kontrol
	 *   (bağlı her postun ÇÖPTE olması şartı) uygulanır.
	 * @return string|null Engel kodu.
	 */
	private function item_blocker( array $item, array $runItems, $atWrite ) {
		$record = $item['record'];
		$lookup = MaviBelge_Core_Import_Record_Validator::normalize_target_lookup( $this->repository->find_target_by_source_key( $record['type'], $record['source_key'] ), $record['type'] );
		if ( ! $lookup['target_state_valid'] || true !== $lookup['target_found'] || true !== $lookup['target_type_matches'] || $lookup['target_id'] !== $record['target_id'] ) {
			return 'drift_detected';
		}
		$current = self::safe_hash( $lookup['current_managed_fields'] );
		if ( $lookup['last_applied_hash'] !== $record['new_hash'] || ! MaviBelge_Core_Import_Apply_Eligibility::rollback_allowed( $record, $current ) ) {
			return 'drift_detected';
		}
		if ( 'create' === $record['decision'] ) {
			// Kullanıcı değişikliğini koru: create sonrası kaydedilen yönetilmeyen-durum parmak izi.
			$fingerprint = $this->writer->unmanaged_fingerprint( $record['type'], $record['target_id'] );
			if ( ! is_string( $fingerprint ) || $fingerprint !== $record['unmanaged_fingerprint'] ) {
				return 'drift_detected';
			}
			if ( 'sector' === $record['type'] ) {
				return $this->term_blocker( $record['target_id'], $runItems, $atWrite );
			}
		}
		return null;
	}

	private function term_blocker( $termId, array $runItems, $atWrite ) {
		$refs = $this->writer->sector_term_references( $termId );
		if ( ! is_array( $refs ) || true !== $refs['ok'] ) {
			return 'term_reference_check_failed';
		}
		if ( 0 !== $refs['child_count'] ) {
			return 'term_has_external_dependents';
		}
		$allowed = array();
		foreach ( $runItems as $item ) {
			if ( 'create' === $item['decision'] && 'sector' !== $item['type'] ) {
				$allowed[] = $item['target_id'];
			}
		}
		$allowed = array_merge( $allowed, $this->store->rolled_back_create_target_ids( 'qualification' ), $this->store->rolled_back_create_target_ids( 'fee' ) );
		foreach ( array_merge( $refs['object_ids'], $refs['fee_ids'] ) as $postId ) {
			if ( ! in_array( $postId, $allowed, true ) ) {
				return 'term_has_external_dependents';
			}
			if ( $atWrite && ! in_array( $postId, $refs['trashed_ids'], true ) ) {
				return 'term_has_external_dependents';
			}
		}
		return null;
	}

	private function execute( array $run, array $items, $size ) {
		$uid = $run['uid'];
		// rollback_started + durum geçişi AYNI transaction'da: ya ikisi de var ya da hiçbiri.
		$started = $this->finalizer->transition_atomic(
			$run,
			$run['status'],
			MaviBelge_Core_Import_Run_State::ROLLING_BACK,
			array(),
			MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_STARTED,
			array( 'run_id' => $uid )
		);
		if ( ! $started ) {
			// Hedef durum UYDURULMAZ: deponun gerçek durumu raporlanır (geçiş uygulanmadıysa run olduğu gibi kalır).
			return self::result( false, $this->finalizer->actual_status( $uid ), 'state_transition_failed', array(), $uid );
		}

		$blockers = $this->preflight( $run, $items );
		if ( ! empty( $blockers ) ) {
			$first = $blockers[0];
			return $this->fail( $run, $first['code'], 0, 0, $first['source_key'] );
		}

		$rolledBack = 0;
		$batches    = 0;
		foreach ( MaviBelge_Core_Import_Apply_Plan::batches( MaviBelge_Core_Import_Apply_Plan::rollback_order( $items ), $size ) as $index => $batch ) {
			if ( true !== $this->tx->begin() ) {
				return $this->fail( $run, 'transaction_begin_failed', $index + 1, $rolledBack );
			}
			$error = null;
			$errorKey = null;
			try {
				if ( true !== $this->writer->begin_side_effect_scope() ) {
					$error = 'side_effect_scope_failed';
				}
				foreach ( null === $error ? $batch : array() as $item ) {
					$error = $this->rollback_item( $item, $items );
					if ( null === $error && true !== $this->store->mark_item_rolled_back( $item['id'] ) ) {
						$error = 'item_store_failed';
					}
					if ( null !== $error ) {
						$errorKey = $item['source_key'];
						break;
					}
				}
				if ( null === $error && true !== $this->tx->commit() ) {
					$error = 'commit_failed';
				}
			} catch ( Throwable $e ) {
				$error = 'unexpected_exception';
			}
			if ( null === $error ) {
				$this->writer->commit_side_effect_scope();
			}
			if ( null !== $error ) {
				$txRolledBack = true === $this->tx->rollback();
				$comp       = $this->writer->compensate_side_effect_scope( $txRolledBack );
				if ( ! $txRolledBack ) {
					$error = 'transaction_rollback_failed';
				} elseif ( true !== $comp['ok'] ) {
					$error = is_string( $comp['error'] ) ? $comp['error'] : 'side_effect_cleanup_failed';
				}
				return $this->fail( $run, $error, $index + 1, $rolledBack, $errorKey, $batches );
			}
			$batches++;
			$rolledBack += count( $batch );
		}

		// rollback_completed + rolling_back -> rolled_back AYNI transaction'da. Başarısızsa güvenli
		// geri çekilme: rollback_failed (+ rollback_failed audit, o da olmazsa yalnız durum).
		$settled = $this->finalizer->settle(
			$run,
			MaviBelge_Core_Import_Run_State::ROLLING_BACK,
			array(
				array(
					'to'      => MaviBelge_Core_Import_Run_State::ROLLED_BACK,
					'event'   => MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_COMPLETED,
					'context' => array( 'run_id' => $uid, 'checkpoint' => $batches ),
				),
				array(
					'to'      => MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED,
					'fields'  => array( 'error_code' => 'finalization_failed' ),
					'event'   => MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_FAILED,
					'context' => array( 'run_id' => $uid, 'error_code' => 'finalization_failed', 'batch_no' => 0, 'checkpoint' => $batches ),
				),
				array(
					'to'     => MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED,
					'fields' => array( 'error_code' => 'finalization_failed' ),
				),
			)
		);
		if ( true === $settled['ok'] ) {
			return self::result( true, MaviBelge_Core_Import_Run_State::ROLLED_BACK, null, array(), $uid, $rolledBack );
		}
		$errors = self::settle_errors( $settled, MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED, 1 );
		return self::result( false, $settled['status'], 'finalization_failed', $errors, $uid, $rolledBack );
	}

	/** @return string|null Hata kodu. */
	private function rollback_item( array $item, array $runItems ) {
		$blocker = $this->item_blocker( $item, $runItems, true );
		if ( null !== $blocker ) {
			return $blocker;
		}
		$record = $item['record'];
		$type   = $record['type'];
		$id     = $record['target_id'];

		if ( 'update' === $record['decision'] ) {
			$fingerprint = $this->writer->unmanaged_fingerprint( $type, $id );
			if ( ! is_string( $fingerprint ) ) {
				return 'unmanaged_fingerprint_unavailable';
			}
			$payload = MaviBelge_Core_Import_Write_Payload::prepare( $type, $record['old_fields'], $record['source_key'], $record['old_hash'] );
			if ( ! $payload['ok'] ) {
				return 'payload_invalid';
			}
			$res = 'sector' === $type ? $this->writer->update_sector( $id, $payload['payload'] ) : $this->writer->update_post( $id, $payload['payload'] );
			if ( ! is_array( $res ) || true !== $res['ok'] || $res['id'] !== $id ) {
				return 'write_failed';
			}
			$lookup = MaviBelge_Core_Import_Record_Validator::normalize_target_lookup( $this->repository->find_target_by_source_key( $type, $record['source_key'] ), $type );
			if ( true !== $lookup['target_found'] || $lookup['target_id'] !== $id || $lookup['last_applied_hash'] !== $record['old_hash'] || $lookup['current_managed_fields'] !== $record['old_fields'] ) {
				return 'readback_mismatch';
			}
			if ( $this->writer->unmanaged_fingerprint( $type, $id ) !== $fingerprint ) {
				return 'unmanaged_field_changed';
			}
			return null;
		}

		if ( 'sector' === $type ) {
			$res = $this->writer->delete_sector_term( $id, $record['unmanaged_fingerprint'] );
		} else {
			$res = $this->writer->rollback_created_post( $id, MaviBelge_Core_Import_Apply_Service::POST_TYPES[ $type ], $record['unmanaged_fingerprint'] );
		}
		if ( ! is_array( $res ) || true !== $res['ok'] ) {
			return is_array( $res ) && 'drift_detected' === $res['error'] ? 'drift_detected' : 'write_failed';
		}
		// Readback: hedef bulunamaz VE doğal anahtar/marker artık yeniden apply'ı engellemez.
		$lookup = MaviBelge_Core_Import_Record_Validator::normalize_target_lookup( $this->repository->find_target_by_source_key( $type, $record['source_key'] ), $type );
		if ( false !== $lookup['target_found'] || ! $lookup['target_state_valid'] || ( null !== $lookup['natural_key'] && 'none' !== $lookup['natural_key'] ) ) {
			return 'readback_mismatch';
		}
		return null;
	}

	private function fail( array $run, $error, $batchNo, $rolledBack, $sourceKey = null, $batches = 0 ) {
		$uid     = $run['uid'];
		$context = array( 'run_id' => $uid, 'error_code' => $error, 'batch_no' => $batchNo, 'checkpoint' => $batches );
		if ( is_string( $sourceKey ) ) {
			$context['source_key'] = $sourceKey;
		}
		$fields  = array( 'error_code' => $error );
		$settled = $this->finalizer->settle(
			$run,
			MaviBelge_Core_Import_Run_State::ROLLING_BACK,
			array(
				array( 'to' => MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED, 'fields' => $fields, 'event' => MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_FAILED, 'context' => $context ),
				array( 'to' => MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED, 'fields' => $fields ),
			)
		);
		return self::result( false, $settled['status'], $error, self::settle_errors( $settled, MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED, 0 ), $uid, $rolledBack );
	}

	/**
	 * Finalizasyon sonucundan kullanıcıya dönük hata satırları. `$auditedAttempt`: audit'li denemenin indeksi.
	 * Hiçbir deneme uygulanamadıysa (null) açık fail-closed hata + GERÇEK kalıcı durum bildirilir.
	 */
	private static function settle_errors( array $settled, $target, $auditedAttempt ) {
		$errors = array();
		if ( null === $settled['attempt'] ) {
			$errors[] = 'Run ' . $target . ' durumuna alınamadı; kalıcı durum: ' . ( null === $settled['status'] ? 'okunamadı' : $settled['status'] ) . '.';
		} elseif ( $auditedAttempt !== $settled['attempt'] && 0 !== $settled['attempt'] ) {
			$errors[] = 'Başarısızlık audit kaydı yazılamadı; durum güvenle kapatıldı.';
		}
		return $errors;
	}

	/** Tek süreçli rollback()/preview() için geçerli durumlar: yeni başlatılabilenler + yarım kalmış (resumable) rollback. */
	private static function startable( $status ) {
		return in_array( $status, MaviBelge_Core_Import_Run_State::ROLLBACKABLE, true ) || in_array( $status, MaviBelge_Core_Import_Run_State::ROLLBACK_RESUMABLE, true );
	}

	private static function safe_hash( $fields ) {
		if ( ! is_array( $fields ) ) {
			return null;
		}
		try {
			return MaviBelge_Core_Import_Hash::hash( $fields );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	private static function result( $ok, $status, $code, array $errors = array(), $uid = null, $rolledBack = 0 ) {
		return array(
			'ok'                => $ok,
			'status'            => $status,
			'error_code'        => $code,
			'errors'            => array_values( $errors ),
			'run_uid'           => $uid,
			'rolled_back_items' => $rolledBack,
		);
	}
}
