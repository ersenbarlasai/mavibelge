<?php
/**
 * Faz 6B3 — katalog içe aktarım APPLY servisi.
 *
 * Bu sınıf WordPress fonksiyonu ÇAĞIRMAZ: bütün okuma/yazma enjekte edilen
 * arayüzlerin arkasındadır (salt okunur repository, dar yazma adapterı,
 * transaction, run/checkpoint deposu, audit hedefi). Karar ve doğrulama
 * için TEK kaynaklar kullanılır; ikinci bir karar sistemi yoktur:
 *   MaviBelge_Core_Import_Dry_Run_Service/Planner (plan, TOCTOU ve readback
 *   gözlemi), MaviBelge_Core_Import_Apply_Eligibility (plan uygunluğu,
 *   TOCTOU, rollback kaydı), MaviBelge_Core_Import_Write_Payload (atomik
 *   yük), MaviBelge_Core_Import_Hash, MaviBelge_Core_Import_Managed_Fields.
 *
 * Güvenlik kapısı (sırayla; biri bile geçmezse HİÇBİR yazma yapılmaz ve run
 * oluşturulmaz):
 *  1. aşama, batch boyutu ve 64-hex onay biçimi;
 *  2. aşama planı üretilebilmeli; plan_digest kullanıcının onayladığı
 *     digest'e BİREBİR eşit olmalı (plan/manifest onaydan beri değişmedi);
 *  3. Apply_Eligibility::evaluate_plan(): summary.applicable === true,
 *     plan hatası yok, tek bir conflict/duplicate/blocked/invalid yok,
 *     kanıtsız create yok (natural_key_check === 'none'), update'te üç-hash
 *     koşulu, source_key tekrarı yok;
 *  4. her yazma kaydı için yönetilen alanlar planlayıcıyla AYNI yoldan
 *     üretilir, hash'i plandaki incoming_hash'e eşit olmalı ve
 *     Write_Payload::prepare() atomik yükü üretmelidir;
 *  5. transaction ön kontrolü, run tabloları ve audit hedefi hazır olmalı;
 *  6. tek eşzamanlı kilit alınmalı; çözülmemiş (running/rolling_back/
 *     rollback_required) run olmamalı.
 * Kullanıcı yetkisi ve açık apply anahtarı çağıran katmanda (WP-CLI)
 * doğrulanır.
 *
 * Batch/transaction: plan düzeyinde uygunsuz tek kayıt bile varsa hiçbir
 * batch başlamaz. Her batch kendi transaction'ında atomiktir; batch
 * içindeki herhangi bir hata o batch'i TAMAMEN geri alır. Önceden commit
 * edilmiş batch'ler kaybolmuş sayılmaz: run `rollback_required` olur ve
 * yalnız doğrulanmış rollback kayıtlarıyla geri alınabilir. Süreçler/HTTP
 * istekleri arasında küresel transaction iddia edilmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Apply_Service {

	const POST_TYPES = array(
		'qualification' => 'mb_yeterlilik',
		'fee'           => 'mb_ucret',
		'news'          => 'mb_haber',
		'reference'     => 'mb_referans',
	);

	/** @var MaviBelge_Core_Import_Dry_Run_Service */
	private $dryRun;
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
		$this->finalizer = new MaviBelge_Core_Import_Run_Finalizer( $store, $audit, $tx );
	}

	/**
	 * SALT OKUNUR önizleme: aşama planı, onaylanacak plan_digest ve uygunluk.
	 *
	 * @param mixed $stage
	 * @return array
	 */
	public function preview( $stage ) {
		$ctx         = $this->dryRun->run_stage( $stage );
		$digest      = $ctx['ok'] ? MaviBelge_Core_Import_Apply_Plan::plan_digest( $stage, $ctx['manifest_digest'], $ctx['plan'] ) : null;
		$eligibility = MaviBelge_Core_Import_Apply_Eligibility::evaluate_plan( $ctx['plan'] );
		return array(
			'ok'                 => $ctx['ok'] && null !== $digest,
			'errors'             => array_values( array_merge( $ctx['load_errors'], $ctx['plan']['errors'] ) ),
			'stage'              => $stage,
			'plan_digest'        => $digest,
			'manifest_digest'    => $ctx['manifest_digest'],
			'summary'            => $ctx['plan']['summary'],
			'eligible'           => $ctx['ok'] && null !== $digest && $eligibility['eligible'],
			'eligibility_errors' => $eligibility['errors'],
			'writes'             => count( $eligibility['writes'] ),
			'noops'              => count( $eligibility['noops'] ),
			'plan'               => $ctx['plan'],
			'diagnostics'        => $ctx['diagnostics'],
		);
	}

	/**
	 * @param mixed    $stage
	 * @param mixed    $confirmDigest Kullanıcının önizlemede gördüğü plan_digest.
	 * @param mixed    $batchSize     null => varsayılan.
	 * @param int      $userId        Run'ı başlatan kullanıcı (yalnız kayıt amaçlı).
	 * @return array{ok: bool, status: string, error_code: string|null, errors: string[], run_uid: string|null,
	 *   plan_digest: string|null, writes_total: int, noops: int, committed_batches: int, committed_items: int}
	 */
	public function apply( $stage, $confirmDigest, $batchSize, $userId ) {
		if ( ! is_string( $stage ) || ! in_array( $stage, MaviBelge_Core_Import_Apply_Plan::ALL_STAGES, true ) ) {
			return self::rejected( 'invalid_stage' );
		}
		$size = MaviBelge_Core_Import_Apply_Plan::normalize_batch_size( $batchSize );
		if ( null === $size ) {
			return self::rejected( 'invalid_batch_size' );
		}
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $confirmDigest ) ) {
			return self::rejected( 'invalid_confirmation' );
		}

		$ctx = $this->dryRun->run_stage( $stage );
		if ( ! $ctx['ok'] ) {
			return self::rejected( 'plan_not_applicable', $ctx['load_errors'] );
		}
		$digest = MaviBelge_Core_Import_Apply_Plan::plan_digest( $stage, $ctx['manifest_digest'], $ctx['plan'] );
		if ( null === $digest ) {
			return self::rejected( 'plan_not_applicable' );
		}
		if ( $digest !== $confirmDigest ) {
			return self::rejected( 'confirmation_mismatch', array( 'Plan veya manifest onaylanan özetten sonra değişti; önizlemeyi yeniden çalıştırın.' ), $digest );
		}
		$eligibility = MaviBelge_Core_Import_Apply_Eligibility::evaluate_plan( $ctx['plan'] );
		if ( ! $eligibility['eligible'] ) {
			return self::rejected( 'plan_not_applicable', $eligibility['errors'], $digest );
		}
		if ( empty( $eligibility['writes'] ) ) {
			// Yazılacak kayıt yok: run, item, audit veya yazma YOK.
			$result           = self::result( true, 'noop', null, array(), null, $digest );
			$result['noops']  = count( $eligibility['noops'] );
			return $result;
		}

		$items = $this->prepare_items( $eligibility['writes'], $ctx );
		if ( null === $items ) {
			return self::rejected( 'payload_invalid', array(), $digest );
		}

		// Run tabloları (ve audit tablosu) yalnız bütün plan kapıları geçtikten
		// sonra kurulur; transaction ön kontrolü kurulu tabloları da kapsar.
		if ( ! $this->store->ensure_installed() ) {
			return self::rejected( 'infrastructure_unavailable', array(), $digest );
		}
		$preflight = $this->tx->preflight();
		if ( ! is_array( $preflight ) || true !== $preflight['ok'] || ! $this->audit->ready() ) {
			return self::rejected( 'infrastructure_unavailable', array(), $digest );
		}
		if ( ! $this->store->acquire_lock() ) {
			return self::rejected( 'locked', array(), $digest );
		}
		try {
			self::expire_stale_runs( $this->store, $this->audit, $this->tx );
			if ( ! empty( $this->store->find_runs_in_status( MaviBelge_Core_Import_Run_State::BLOCKS_NEW_APPLY ) ) ) {
				return self::rejected( 'unresolved_run_exists', array( 'Çözülmemiş bir import run\'ı var; önce geri alın veya inceleyin.' ), $digest );
			}
			$result          = $this->execute( $stage, $digest, $ctx['manifest_digest'], $size, $items, (int) $userId );
			$result['noops'] = count( $eligibility['noops'] );
			return $result;
		} finally {
			$this->store->release_lock();
		}
	}

	/**
	 * Kilit alındığında etkin (running/rolling_back) durumda kalmış run'lar
	 * bayattır (süreci ölmüş): commit edilmiş item'ı olan `running` run
	 * `rollback_required`, olmayan `failed`; `rolling_back` -> `rollback_failed`.
	 */
	public static function expire_stale_runs( MaviBelge_Core_Import_Run_Store $store, MaviBelge_Core_Import_Audit_Sink $audit, MaviBelge_Core_Import_Transaction $tx ) {
		$finalizer = new MaviBelge_Core_Import_Run_Finalizer( $store, $audit, $tx );
		foreach ( $store->find_runs_in_status( MaviBelge_Core_Import_Run_State::ACTIVE ) as $run ) {
			if ( MaviBelge_Core_Import_Run_State::RUNNING === $run['status'] ) {
				$to    = $run['committed_items'] > 0 ? MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED : MaviBelge_Core_Import_Run_State::FAILED;
				$event = MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED;
			} else {
				$to    = MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED;
				$event = MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_FAILED;
			}
			$fields = array( 'error_code' => 'stale_run' );
			$ctx    = array( 'run_id' => $run['uid'], 'error_code' => 'stale_run' );
			// Durum + audit atomik; audit yazılamazsa durum yine kapatılır (bayat run yeni apply'ı sonsuza dek engellemesin).
			$finalizer->settle(
				$run,
				$run['status'],
				array(
					array( 'to' => $to, 'fields' => $fields, 'event' => $event, 'context' => $ctx ),
					array( 'to' => $to, 'fields' => $fields ),
				)
			);
		}
	}

	/** @return array|null Yazma sırasına dizilmiş item'lar; tek bir hazırlık hatasında null. */
	private function prepare_items( array $writes, array $ctx ) {
		$records = array();
		foreach ( MaviBelge_Core_Import_Apply_Plan::TYPE_LISTS as $type => $list ) {
			foreach ( isset( $ctx['manifest'][ $list ] ) ? $ctx['manifest'][ $list ] : array() as $record ) {
				if ( is_array( $record ) && isset( $record['source_key'] ) && is_string( $record['source_key'] ) ) {
					$records[ $type . '|' . $record['source_key'] ] = $record;
				}
			}
		}
		$items = array();
		foreach ( MaviBelge_Core_Import_Apply_Plan::order_writes( $writes ) as $write ) {
			$key = $write['type'] . '|' . $write['source_key'];
			if ( ! isset( $records[ $key ] ) ) {
				return null;
			}
			$projection = MaviBelge_Core_Import_Dry_Run_Planner::project_for_apply( $write['type'], $records[ $key ], $ctx['dependencies'] );
			if ( ! $projection['ok'] || self::safe_hash( $projection['fields'] ) !== $write['expected_incoming_hash'] ) {
				return null;
			}
			$payload = MaviBelge_Core_Import_Write_Payload::prepare( $write['type'], $projection['fields'], $write['source_key'], $write['expected_incoming_hash'] );
			if ( ! $payload['ok'] ) {
				return null;
			}
			$items[] = array(
				'write'   => $write,
				'record'  => $records[ $key ],
				'fields'  => $projection['fields'],
				'payload' => $payload['payload'],
			);
		}
		return $items;
	}

	private function execute( $stage, $digest, $manifestDigest, $size, array $items, $userId ) {
		$run = $this->store->create_run(
			array(
				'stage'           => $stage,
				'plan_digest'     => $digest,
				'manifest_digest' => $manifestDigest,
				'batch_size'      => $size,
				'total_writes'    => count( $items ),
				'created_by'      => $userId,
			)
		);
		if ( ! is_array( $run ) || ! isset( $run['id'], $run['uid'] ) ) {
			return self::result( false, 'failed', 'run_create_failed', array(), null, $digest );
		}
		$runId = $run['id'];
		$uid   = $run['uid'];

		// run_started + planned -> running AYNI transaction'da: "audit started ama run planned" oluşamaz.
		if ( ! $this->finalizer->transition_atomic( $run, MaviBelge_Core_Import_Run_State::PLANNED, MaviBelge_Core_Import_Run_State::RUNNING, array(), MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_STARTED, array( 'run_id' => $uid ) ) ) {
			return $this->fail_start( $run, $digest );
		}

		$committedBatches = 0;
		$committedItems   = 0;
		$seq              = 0;
		foreach ( MaviBelge_Core_Import_Apply_Plan::batches( $items, $size ) as $index => $batch ) {
			$batchNo = $index + 1;
			if ( $this->dryRun->current_manifest_digest( $stage ) !== $manifestDigest ) {
				return $this->fail_run( $run, $digest, 'manifest_changed', $batchNo, $committedBatches, $committedItems );
			}
			if ( true !== $this->tx->begin() ) {
				return $this->fail_run( $run, $digest, 'transaction_begin_failed', $batchNo, $committedBatches, $committedItems );
			}
			$error      = null;
			$auditItems = array();
			try {
				foreach ( $batch as $item ) {
					$seq++;
					$outcome = $this->apply_item( $item, $runId, $batchNo, $seq );
					if ( null !== $outcome['error'] ) {
						$error = $outcome['error'];
						break;
					}
					$auditItems[] = $outcome['audit'];
				}
				if ( null === $error && ! $this->audit->record( MaviBelge_Core_Audit_Log::EVENT_IMPORT_BATCH_COMMITTED, $runId, array( 'run_id' => $uid, 'batch_no' => $batchNo, 'checkpoint' => $committedBatches + 1, 'items' => $auditItems ) ) ) {
					$error = 'audit_failed';
				}
				if ( null === $error && ! $this->store->record_checkpoint( $runId, $committedBatches + 1, $committedItems + count( $batch ) ) ) {
					$error = 'checkpoint_failed';
				}
				if ( null === $error && true !== $this->tx->commit() ) {
					$error = 'commit_failed';
				}
			} catch ( Throwable $e ) {
				$error = 'unexpected_exception';
			}
			if ( null !== $error ) {
				if ( true !== $this->tx->rollback() ) {
					$error = 'transaction_rollback_failed';
				}
				return $this->fail_run( $run, $digest, $error, $batchNo, $committedBatches, $committedItems );
			}
			$committedBatches++;
			$committedItems += count( $batch );
		}

		// run_completed + running -> completed AYNI transaction'da: "audit completed ama run running"
		// oluşamaz. Batch'ler commit edilmişken finalizasyon başarısızsa run güvenle rollback_required
		// olur (önce run_failed audit ile atomik, o da olmazsa yalnız durum); sonuç gerçek durumu raporlar.
		$settled = $this->finalizer->settle(
			$run,
			MaviBelge_Core_Import_Run_State::RUNNING,
			array(
				array( 'to' => MaviBelge_Core_Import_Run_State::COMPLETED, 'event' => MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_COMPLETED, 'context' => array( 'run_id' => $uid, 'checkpoint' => $committedBatches ) ),
				array(
					'to'      => MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED,
					'fields'  => array( 'error_code' => 'finalization_failed' ),
					'event'   => MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED,
					'context' => array( 'run_id' => $uid, 'error_code' => 'finalization_failed', 'batch_no' => 0, 'checkpoint' => $committedBatches ),
				),
				array( 'to' => MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED, 'fields' => array( 'error_code' => 'finalization_failed' ) ),
			)
		);
		if ( true === $settled['ok'] ) {
			return self::result( true, MaviBelge_Core_Import_Run_State::COMPLETED, null, array(), $uid, $digest, count( $items ), $committedBatches, $committedItems );
		}
		return self::result( false, $settled['status'], 'finalization_failed', self::settle_errors( $settled, MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED ), $uid, $digest, count( $items ), $committedBatches, $committedItems );
	}

	/**
	 * Tek kaydı yazar. Transaction'ı açan/kapatan çağırandır.
	 *
	 * @return array{error: string|null, audit?: array}
	 */
	private function apply_item( array $item, $runId, $batchNo, $seq ) {
		$write  = $item['write'];
		$type   = $write['type'];
		$update = 'update' === $write['decision'];

		// TOCTOU — yazmadan HEMEN önce, planlamayla AYNI yoldan yeniden gözle.
		$before = $this->dryRun->observe_record( $type, $item['record'] );
		$entry  = $before['entry'];
		if ( $entry['decision'] !== $write['decision'] ) {
			return array( 'error' => 'toctou_drift' );
		}
		$observed = array(
			'target_found'      => null !== $entry['target_id'],
			'target_id'         => $entry['target_id'],
			'current_hash'      => $entry['current_hash'],
			'last_applied_hash' => $entry['last_applied_hash'],
			'natural_key'       => array_key_exists( 'natural_key_check', $entry ) ? $entry['natural_key_check'] : null,
			'incoming_hash'     => $entry['incoming_hash'],
		);
		$recheck = MaviBelge_Core_Import_Apply_Eligibility::toctou_recheck( $write, $observed );
		if ( true !== $recheck['ok'] ) {
			return array( 'error' => 'toctou_drift' );
		}
		$oldFields   = null;
		$fingerprint = null;
		if ( $update ) {
			$oldFields = $before['lookup']['current_managed_fields'];
			if ( ! is_array( $oldFields ) || self::safe_hash( $oldFields ) !== $write['expected_current_hash'] ) {
				return array( 'error' => 'toctou_drift' );
			}
			$fingerprint = $this->writer->unmanaged_fingerprint( $type, $write['target_id'] );
			if ( ! is_string( $fingerprint ) ) {
				return array( 'error' => 'unmanaged_fingerprint_unavailable' );
			}
		}

		if ( 'sector' === $type ) {
			$res = $update ? $this->writer->update_sector( $write['target_id'], $item['payload'] ) : $this->writer->create_sector( $item['payload'] );
		} else {
			$res = $update ? $this->writer->update_post( $write['target_id'], $item['payload'] ) : $this->writer->create_post( $item['payload'] );
		}
		if ( ! is_array( $res ) || true !== $res['ok'] || ! is_int( $res['id'] ) || $res['id'] <= 0 ) {
			return array( 'error' => 'write_failed' );
		}
		$targetId = $res['id'];
		if ( $update && $targetId !== $write['target_id'] ) {
			return array( 'error' => 'write_failed' );
		}

		// Readback — saklanan kanonik durum TEK karar motoruyla yeniden
		// okunur: kayıt artık `unchanged` olmalı ve yönetilen alanlar yazılan
		// alanlarla KATI olarak eşit olmalı.
		$after = $this->dryRun->observe_record( $type, $item['record'] );
		$ae    = $after['entry'];
		$h     = $write['expected_incoming_hash'];
		if ( 'unchanged' !== $ae['decision'] || $ae['target_id'] !== $targetId || $ae['incoming_hash'] !== $h || $ae['current_hash'] !== $h || $ae['last_applied_hash'] !== $h
			|| $after['lookup']['current_managed_fields'] !== $item['fields'] ) {
			return array( 'error' => 'readback_mismatch' );
		}
		if ( $update && $this->writer->unmanaged_fingerprint( $type, $targetId ) !== $fingerprint ) {
			return array( 'error' => 'unmanaged_field_changed' );
		}

		// Create: yönetilmeyen-durum parmak izi create'ten HEMEN SONRA alınır (rollback'te kullanıcı
		// değişikliğini korumak için; yalnız hash saklanır, ham içerik değil).
		$createFingerprint = null;
		if ( ! $update ) {
			$createFingerprint = $this->writer->unmanaged_fingerprint( $type, $targetId );
			if ( ! is_string( $createFingerprint ) ) {
				return array( 'error' => 'unmanaged_fingerprint_unavailable' );
			}
		}
		$record = MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record(
			$type,
			$write['source_key'],
			$targetId,
			$write['decision'],
			$oldFields,
			$item['fields'],
			$update ? $write['expected_current_hash'] : null,
			$h,
			$createFingerprint
		);
		$json = null === $record ? null : MaviBelge_Core_Import_Rollback_Codec::encode( $record );
		if ( null === $json ) {
			return array( 'error' => 'rollback_record_invalid' );
		}
		$stored = $this->store->add_item(
			$runId,
			array(
				'seq'             => $seq,
				'batch_no'        => $batchNo,
				'source_key'      => $write['source_key'],
				'type'            => $type,
				'decision'        => $write['decision'],
				'target_id'       => $targetId,
				'rollback_record' => $json,
			)
		);
		if ( true !== $stored ) {
			return array( 'error' => 'item_store_failed' );
		}
		return array(
			'error' => null,
			'audit' => array(
				'source_key'     => $write['source_key'],
				'type'           => $type,
				'decision'       => $write['decision'],
				'target_id'      => $targetId,
				'old_hash'       => $record['old_hash'],
				'new_hash'       => $record['new_hash'],
				'changed_fields' => $record['changed_fields'],
			),
		);
	}

	/**
	 * Run başlatılamadı (run_started + planned -> running atomik geçişi uygulanamadı): run `planned`
	 * kalır; güvenli olarak failed'a alınır (run_failed audit ile atomik, o da olmazsa yalnız durum).
	 * Sonuç hedef durumu UYDURMAZ: deponun gerçek durumu raporlanır.
	 */
	private function fail_start( array $run, $digest ) {
		$uid     = $run['uid'];
		$fields  = array( 'error_code' => 'run_start_failed' );
		$settled = $this->finalizer->settle(
			$run,
			MaviBelge_Core_Import_Run_State::PLANNED,
			array(
				array( 'to' => MaviBelge_Core_Import_Run_State::FAILED, 'fields' => $fields, 'event' => MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED, 'context' => array( 'run_id' => $uid, 'error_code' => 'run_start_failed', 'batch_no' => 0, 'checkpoint' => 0 ) ),
				array( 'to' => MaviBelge_Core_Import_Run_State::FAILED, 'fields' => $fields ),
			)
		);
		return self::result( false, $settled['status'], 'run_start_failed', self::settle_errors( $settled, MaviBelge_Core_Import_Run_State::FAILED ), $uid, $digest );
	}

	/**
	 * Batch geri alındıktan SONRA (transaction dışında) run'ı işaretler: run_failed audit + durum
	 * geçişi atomiktir; audit yazılamazsa yalnız durum güvenle kapatılır. Sonuç gerçek durumu raporlar.
	 */
	private function fail_run( array $run, $digest, $error, $batchNo, $committedBatches, $committedItems ) {
		$uid     = $run['uid'];
		$to      = $committedBatches > 0 ? MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED : MaviBelge_Core_Import_Run_State::FAILED;
		$fields  = array( 'error_code' => $error );
		$settled = $this->finalizer->settle(
			$run,
			MaviBelge_Core_Import_Run_State::RUNNING,
			array(
				array( 'to' => $to, 'fields' => $fields, 'event' => MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED, 'context' => array( 'run_id' => $uid, 'error_code' => $error, 'batch_no' => $batchNo, 'checkpoint' => $committedBatches ) ),
				array( 'to' => $to, 'fields' => $fields ),
			)
		);
		return self::result( false, $settled['status'], $error, self::settle_errors( $settled, $to, 0 ), $uid, $digest, null, $committedBatches, $committedItems );
	}

	/**
	 * Finalizasyon sonucundan kullanıcıya dönük hata satırları: hiçbir deneme uygulanamadıysa açık
	 * fail-closed hata + deponun GERÇEK kalıcı durumu; audit'siz güvenli geri çekilme ayrıca not edilir.
	 */
	private static function settle_errors( array $settled, $target, $auditedAttempt = 1 ) {
		$errors = array();
		if ( null === $settled['attempt'] ) {
			$errors[] = 'Run ' . $target . ' durumuna alınamadı; kalıcı durum: ' . ( null === $settled['status'] ? 'okunamadı' : $settled['status'] ) . '.';
		} elseif ( 0 !== $settled['attempt'] && $auditedAttempt !== $settled['attempt'] ) {
			$errors[] = 'Başarısızlık audit kaydı yazılamadı; durum güvenle kapatıldı.';
		}
		return $errors;
	}

	private static function safe_hash( $fields ) {
		try {
			return MaviBelge_Core_Import_Hash::hash( $fields );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	private static function rejected( $code, array $errors = array(), $digest = null ) {
		return self::result( false, 'rejected', $code, $errors, null, $digest );
	}

	private static function result( $ok, $status, $code, array $errors, $uid, $digest, $writesTotal = 0, $batches = 0, $items = 0 ) {
		return array(
			'ok'                => $ok,
			'status'            => $status,
			'error_code'        => $code,
			'errors'            => array_values( $errors ),
			'run_uid'           => $uid,
			'plan_digest'       => $digest,
			'writes_total'      => null === $writesTotal ? 0 : $writesTotal,
			'noops'             => 0,
			'committed_batches' => $batches,
			'committed_items'   => $items,
		);
	}
}
