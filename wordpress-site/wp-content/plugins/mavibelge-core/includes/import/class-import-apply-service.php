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
		'faq'           => 'mb_sss',
		'page'          => 'page',
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
			foreach ( $batch as $offset => $unused ) {
				$batch[ $offset ]['seq'] = $seq + $offset + 1;
			}
			$error = $this->run_batch( $run, $batch, $batchNo, $committedBatches, $committedItems );
			if ( null !== $error ) {
				return $this->fail_run( $run, $digest, $error, $batchNo, $committedBatches, $committedItems );
			}
			$seq += count( $batch );
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
	 * Ortak TEK-BATCH primitive'i (tek apply yolu: CLI döngüsü ve admin advance BUNU çağırır). Bir transaction'da:
	 * her kaydın TOCTOU + yazma + readback + rollback kaydı (apply_item), batch audit'i ve compare-and-set checkpoint;
	 * herhangi bir hata batch'i TAMAMEN geri alır. Transaction dışı run durumu geçişini ÇAĞIRAN yapar.
	 *
	 * @param array $batch Her eleman: write/record/fields/payload + seq (global sıra).
	 * @return string|null Hata kodu (batch geri alınmıştır); başarıda null (batch commit edildi).
	 */
	private function run_batch( array $run, array $batch, $batchNo, $committedBatches, $committedItems ) {
		$runId = $run['id'];
		$uid   = $run['uid'];
		if ( true !== $this->tx->begin() ) {
			return 'transaction_begin_failed';
		}
		$error      = null;
		$auditItems = array();
		try {
			// Faz 12c: DB transaction dosya sistemini geri almaz; batch'in YENİ dosyaları kapsamda izlenir.
			if ( true !== $this->writer->begin_side_effect_scope() ) {
				$error = 'side_effect_scope_failed';
			}
			foreach ( null === $error ? $batch : array() as $item ) {
				$outcome = $this->apply_item( $item, $runId, $batchNo, $item['seq'] );
				if ( null !== $outcome['error'] ) {
					$error = $outcome['error'];
					break;
				}
				$auditItems[] = $outcome['audit'];
			}
			if ( null === $error && ! $this->audit->record( MaviBelge_Core_Audit_Log::EVENT_IMPORT_BATCH_COMMITTED, $runId, array( 'run_id' => $uid, 'batch_no' => $batchNo, 'checkpoint' => $committedBatches + 1, 'items' => $auditItems ) ) ) {
				$error = 'audit_failed';
			}
			if ( null === $error && ! $this->store->record_checkpoint( $runId, $committedBatches + 1, $committedItems + count( $batch ), $committedBatches ) ) {
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
			return null;
		}
		$txRolledBack = true === $this->tx->rollback();
		// DB geri alındıktan SONRA (ve yalnız o zaman) batch'in yeni dosyaları silinir; rollback başarısızsa HİÇBİR dosya silinmez.
		$comp = $this->writer->compensate_side_effect_scope( $txRolledBack );
		if ( ! $txRolledBack ) {
			return 'transaction_rollback_failed';
		}
		if ( true !== $comp['ok'] ) {
			return is_string( $comp['error'] ) ? $comp['error'] : 'side_effect_cleanup_failed';
		}
		return $error;
	}

	/**
	 * Faz 6B4 — RESUMABLE apply, adım 1: bütün plan kapıları (apply() ile AYNI) geçerse run `ready` durumunda +
	 * kapalı plan snapshot'ıyla birlikte oluşturulur. HİÇBİR içerik yazılmaz ve run_started audit'i henüz yazılmaz
	 * (ilk advance'te). Yazılacak kayıt yoksa run oluşturulmaz (status `noop`).
	 *
	 * @param mixed       $mapDigest Run başlangıcındaki sektör görsel map digest'i (64-hex|null); advance'te yeniden doğrulanır.
	 * @return array Bkz. resumable_result().
	 */
	public function start_resumable( $stage, $confirmDigest, $batchSize, $userId, $mapDigest = null ) {
		if ( ! is_string( $stage ) || ! in_array( $stage, MaviBelge_Core_Import_Apply_Plan::ALL_STAGES, true ) ) {
			return self::resumable_rejected( 'invalid_stage' );
		}
		$size = MaviBelge_Core_Import_Apply_Plan::normalize_batch_size( $batchSize );
		if ( null === $size ) {
			return self::resumable_rejected( 'invalid_batch_size' );
		}
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $confirmDigest ) ) {
			return self::resumable_rejected( 'invalid_confirmation' );
		}
		if ( null !== $mapDigest && ! MaviBelge_Core_Import_Apply_Plan::is_digest( $mapDigest ) ) {
			return self::resumable_rejected( 'invalid_request' );
		}
		$ctx = $this->dryRun->run_stage( $stage );
		if ( ! $ctx['ok'] ) {
			return self::resumable_rejected( 'plan_not_applicable' );
		}
		$digest = MaviBelge_Core_Import_Apply_Plan::plan_digest( $stage, $ctx['manifest_digest'], $ctx['plan'] );
		if ( null === $digest ) {
			return self::resumable_rejected( 'plan_not_applicable' );
		}
		if ( $digest !== $confirmDigest ) {
			return self::resumable_rejected( 'confirmation_mismatch', $digest );
		}
		$eligibility = MaviBelge_Core_Import_Apply_Eligibility::evaluate_plan( $ctx['plan'] );
		if ( ! $eligibility['eligible'] ) {
			return self::resumable_rejected( 'plan_not_applicable', $digest );
		}
		if ( empty( $eligibility['writes'] ) ) {
			$result           = self::resumable_result( true, 'noop', null, null, $digest, 0, 0, 0 );
			$result['noops']  = count( $eligibility['noops'] );
			return $result;
		}
		$items = $this->prepare_items( $eligibility['writes'], $ctx );
		if ( null === $items ) {
			return self::resumable_rejected( 'payload_invalid', $digest );
		}
		$snapshot = MaviBelge_Core_Import_Plan_Snapshot::from_writes( array_map(
			function ( $item ) {
				return $item['write'];
			},
			$items
		) );
		if ( null === $snapshot ) {
			return self::resumable_rejected( 'payload_invalid', $digest );
		}
		if ( ! $this->store->ensure_installed() ) {
			return self::resumable_rejected( 'infrastructure_unavailable', $digest );
		}
		$preflight = $this->tx->preflight();
		if ( ! is_array( $preflight ) || true !== $preflight['ok'] || ! $this->audit->ready() ) {
			return self::resumable_rejected( 'infrastructure_unavailable', $digest );
		}
		if ( ! $this->store->acquire_lock() ) {
			return self::resumable_rejected( 'locked', $digest );
		}
		try {
			self::expire_stale_runs( $this->store, $this->audit, $this->tx );
			if ( ! empty( $this->store->find_runs_in_status( MaviBelge_Core_Import_Run_State::BLOCKS_NEW_APPLY ) ) ) {
				return self::resumable_rejected( 'unresolved_run_exists', $digest );
			}
			// Run + snapshot AYNI transaction'da: yarım run/snapshot bırakılmaz.
			if ( true !== $this->tx->begin() ) {
				return self::resumable_rejected( 'run_create_failed', $digest );
			}
			$run = null;
			try {
				$run = $this->store->create_run_with_plan(
					array( 'stage' => $stage, 'plan_digest' => $digest, 'manifest_digest' => $ctx['manifest_digest'], 'map_digest' => $mapDigest, 'batch_size' => $size, 'total_writes' => count( $snapshot ), 'created_by' => (int) $userId ),
					$snapshot
				);
			} catch ( Throwable $e ) {
				$run = null;
			}
			if ( ! is_array( $run ) || ! isset( $run['id'], $run['uid'] ) || true !== $this->tx->commit() ) {
				$this->tx->rollback();
				return self::resumable_rejected( 'run_create_failed', $digest );
			}
			$result          = self::resumable_result( true, $run['status'], null, $run['uid'], $digest, count( $snapshot ), 0, 0 );
			$result['noops'] = count( $eligibility['noops'] );
			return $result;
		} finally {
			$this->store->release_lock();
		}
	}

	/**
	 * Faz 6B4 — RESUMABLE apply, adım 2: run'ın SIRADAKİ tek batch'ini (en çok run.batch_size kayıt) yürütür.
	 * `ready|paused` durumundaki run ve `$expectedCheckpoint === run.committed_batches` zorunludur (eski/çift istek
	 * `stale_request`). Manifest ve görsel map digest'i başlangıç değerleriyle karşılaştırılır. Batch commit edildikten
	 * sonra run `paused` (kalan var) veya `completed` olur; sonuç deponun GERÇEK durumunu raporlar.
	 * Yetki/nonce/ortam kapıları çağıran katmandadır.
	 *
	 * @param mixed $runUid
	 * @param mixed $expectedCheckpoint Gerçek int >= 0.
	 * @param mixed $currentMapDigest   Şu anki sektör görsel map digest'i (64-hex|null).
	 * @return array Bkz. resumable_result().
	 */
	public function advance_resumable( $runUid, $expectedCheckpoint, $userId, $currentMapDigest = null ) {
		if ( ! is_string( $runUid ) || 1 !== preg_match( '/^[0-9a-f]{32}\z/', $runUid ) || ! is_int( $expectedCheckpoint ) || $expectedCheckpoint < 0
			|| ( null !== $currentMapDigest && ! MaviBelge_Core_Import_Apply_Plan::is_digest( $currentMapDigest ) ) ) {
			return self::resumable_rejected( 'invalid_request' );
		}
		if ( ! $this->store->is_installed() ) {
			return self::resumable_rejected( 'run_not_found' );
		}
		$preflight = $this->tx->preflight();
		if ( ! is_array( $preflight ) || true !== $preflight['ok'] || ! $this->audit->ready() ) {
			return self::resumable_rejected( 'infrastructure_unavailable' );
		}
		if ( ! $this->store->acquire_lock() ) {
			return self::resumable_rejected( 'locked' );
		}
		try {
			// Kilit bizdeyken `running` görünen run'ın süreci ölmüştür (bayat): fail-closed kapatılır.
			self::expire_stale_runs( $this->store, $this->audit, $this->tx );
			$run = $this->store->get_run( $runUid );
			if ( ! is_array( $run ) ) {
				return self::resumable_rejected( 'run_not_found' );
			}
			if ( ! in_array( $run['status'], MaviBelge_Core_Import_Run_State::APPLY_RESUMABLE, true ) ) {
				return self::resumable_from_run( $run, false, 'run_not_resumable' );
			}
			if ( $run['committed_batches'] !== $expectedCheckpoint ) {
				return self::resumable_from_run( $run, false, 'stale_request' );
			}
			$digest = $run['plan_digest'];
			$ctx    = $this->dryRun->run_stage( $run['stage'] );
			if ( ! $ctx['ok'] || $ctx['manifest_digest'] !== $run['manifest_digest'] ) {
				return $this->fail_pending( $run, 'manifest_changed' );
			}
			if ( $currentMapDigest !== $run['map_digest'] ) {
				return $this->fail_pending( $run, 'map_changed' );
			}
			$batchNo = $run['committed_batches'] + 1;
			if ( MaviBelge_Core_Import_Run_State::READY === $run['status'] ) {
				// run_started + ready -> running AYNI transaction'da.
				if ( ! $this->finalizer->transition_atomic( $run, MaviBelge_Core_Import_Run_State::READY, MaviBelge_Core_Import_Run_State::RUNNING, array(), MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_STARTED, array( 'run_id' => $run['uid'] ) ) ) {
					return $this->fail_start_ready( $run );
				}
			} elseif ( ! $this->finalizer->transition_atomic( $run, MaviBelge_Core_Import_Run_State::PAUSED, MaviBelge_Core_Import_Run_State::RUNNING, array(), null, array() ) ) {
				return self::resumable_from_run( $this->store->get_run( $runUid ), false, 'stale_request' );
			}

			$rows = $this->store->get_plan_items( $run['id'], $run['committed_items'], $run['batch_size'] );
			if ( empty( $rows ) || array() !== MaviBelge_Core_Import_Plan_Snapshot::validate_items( $rows, $run['committed_items'] ) ) {
				return self::legacy_to_resumable( $this->fail_run( $run, $digest, 'plan_items_invalid', $batchNo, $run['committed_batches'], $run['committed_items'] ), $run );
			}
			$items = $this->prepare_items(
				array_map( array( 'MaviBelge_Core_Import_Plan_Snapshot', 'to_write' ), $rows ),
				$ctx
			);
			if ( null === $items || count( $items ) !== count( $rows ) ) {
				return self::legacy_to_resumable( $this->fail_run( $run, $digest, 'toctou_drift', $batchNo, $run['committed_batches'], $run['committed_items'] ), $run );
			}
			foreach ( $items as $offset => $unused ) {
				$items[ $offset ]['seq'] = $rows[ $offset ]['seq'];
			}
			$error = $this->run_batch( $run, $items, $batchNo, $run['committed_batches'], $run['committed_items'] );
			if ( null !== $error ) {
				return self::legacy_to_resumable( $this->fail_run( $run, $digest, $error, $batchNo, $run['committed_batches'], $run['committed_items'] ), $run );
			}
			$committedBatches = $run['committed_batches'] + 1;
			$committedItems   = $run['committed_items'] + count( $items );

			if ( $committedItems >= $run['total_writes'] ) {
				$settled = $this->finalizer->settle(
					$run,
					MaviBelge_Core_Import_Run_State::RUNNING,
					array(
						array( 'to' => MaviBelge_Core_Import_Run_State::COMPLETED, 'event' => MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_COMPLETED, 'context' => array( 'run_id' => $run['uid'], 'checkpoint' => $committedBatches ) ),
						array(
							'to'      => MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED,
							'fields'  => array( 'error_code' => 'finalization_failed' ),
							'event'   => MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED,
							'context' => array( 'run_id' => $run['uid'], 'error_code' => 'finalization_failed', 'batch_no' => 0, 'checkpoint' => $committedBatches ),
						),
						array( 'to' => MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED, 'fields' => array( 'error_code' => 'finalization_failed' ) ),
					)
				);
				$ok = true === $settled['ok'];
				return self::resumable_result( $ok, $settled['status'], $ok ? null : 'finalization_failed', $run['uid'], $digest, $run['total_writes'], $committedBatches, $committedItems, $ok ? array() : self::settle_errors( $settled, MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED ) );
			}
			// Batch commit edildi; kalan var: running -> paused (audit'siz, tek UPDATE). Uygulanamazsa GERÇEK durum
			// (running) raporlanır; bir sonraki istek bunu bayat sayıp fail-closed kapatır (sessiz devam yok).
			$paused = $this->finalizer->transition_atomic( $run, MaviBelge_Core_Import_Run_State::RUNNING, MaviBelge_Core_Import_Run_State::PAUSED, array(), null, array() );
			return self::resumable_result( $paused, $this->finalizer->actual_status( $run['uid'] ), $paused ? null : 'finalization_failed', $run['uid'], $digest, $run['total_writes'], $committedBatches, $committedItems );
		} finally {
			$this->store->release_lock();
		}
	}

	/** Bekleme durumundaki (ready/paused) run'ı çalıştırmadan güvenle kapatır (manifest/map değişti). */
	private function fail_pending( array $run, $error ) {
		$to      = $run['committed_items'] > 0 ? MaviBelge_Core_Import_Run_State::ROLLBACK_REQUIRED : MaviBelge_Core_Import_Run_State::FAILED;
		$fields  = array( 'error_code' => $error );
		$settled = $this->finalizer->settle(
			$run,
			$run['status'],
			array(
				array( 'to' => $to, 'fields' => $fields, 'event' => MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED, 'context' => array( 'run_id' => $run['uid'], 'error_code' => $error, 'batch_no' => $run['committed_batches'] + 1, 'checkpoint' => $run['committed_batches'] ) ),
				array( 'to' => $to, 'fields' => $fields ),
			)
		);
		return self::resumable_result( false, $settled['status'], $error, $run['uid'], $run['plan_digest'], $run['total_writes'], $run['committed_batches'], $run['committed_items'], self::settle_errors( $settled, $to, 0 ) );
	}

	/** ready -> running geçişi (run_started ile) uygulanamadı: run güvenle failed'a alınır. */
	private function fail_start_ready( array $run ) {
		$fields  = array( 'error_code' => 'run_start_failed' );
		$settled = $this->finalizer->settle(
			$run,
			MaviBelge_Core_Import_Run_State::READY,
			array(
				array( 'to' => MaviBelge_Core_Import_Run_State::FAILED, 'fields' => $fields, 'event' => MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED, 'context' => array( 'run_id' => $run['uid'], 'error_code' => 'run_start_failed', 'batch_no' => 0, 'checkpoint' => 0 ) ),
				array( 'to' => MaviBelge_Core_Import_Run_State::FAILED, 'fields' => $fields ),
			)
		);
		return self::resumable_result( false, $settled['status'], 'run_start_failed', $run['uid'], $run['plan_digest'], $run['total_writes'], 0, 0, self::settle_errors( $settled, MaviBelge_Core_Import_Run_State::FAILED ) );
	}

	/** Eski (tek süreç) hata sonucunu resumable şekline çevirir (durum gerçek depo durumudur). */
	private static function legacy_to_resumable( array $legacy, array $run ) {
		return self::resumable_result( $legacy['ok'], $legacy['status'], $legacy['error_code'], $run['uid'], $run['plan_digest'], $run['total_writes'], $legacy['committed_batches'], $legacy['committed_items'], $legacy['errors'] );
	}

	private static function resumable_rejected( $code, $digest = null ) {
		return self::resumable_result( false, 'rejected', $code, null, $digest, 0, 0, 0 );
	}

	private static function resumable_from_run( $run, $ok, $code ) {
		if ( ! is_array( $run ) ) {
			return self::resumable_rejected( $code );
		}
		return self::resumable_result( $ok, $run['status'], $code, $run['uid'], $run['plan_digest'], $run['total_writes'], $run['committed_batches'], $run['committed_items'] );
	}

	/**
	 * Güvenli, yalnız sayaç/durum/kod taşıyan sonuç (alan değeri, SQL, exception veya yol YOK).
	 *
	 * @return array{ok: bool, status: string|null, error_code: string|null, errors: string[], run_uid: string|null,
	 *   plan_digest: string|null, writes_total: int, noops: int, committed_batches: int, committed_items: int,
	 *   checkpoint: int, total: int, committed: int, remaining: int}
	 */
	private static function resumable_result( $ok, $status, $code, $uid, $digest, $total, $batches, $items, array $errors = array() ) {
		$base                = self::result( $ok, $status, $code, $errors, $uid, $digest, $total, $batches, $items );
		$base['checkpoint']  = $batches;
		$base['total']       = $total;
		$base['committed']   = $items;
		$base['remaining']   = max( 0, $total - $items );
		return $base;
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
