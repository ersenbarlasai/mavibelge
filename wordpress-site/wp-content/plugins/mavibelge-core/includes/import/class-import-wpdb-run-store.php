<?php
/**
 * Faz 6B3 — `MaviBelge_Core_Import_Run_Store`'un $wpdb uygulaması.
 *
 * Üç ayrı tablo (mevcut audit tablosu büyük rollback deposu olarak
 * KULLANILMAZ):
 *   {prefix}mb_import_runs           — run başına tek satır (durum + checkpoint)
 *   {prefix}mb_import_run_items      — yazılan kayıt başına tek satır (kapalı
 *                                      rollback kaydı, LONGTEXT JSON)
 *   {prefix}mb_import_run_plan_items — Faz 6B4: run başlangıcındaki onaylı planın
 *                                      KAPALI snapshot'ı (kaynak anahtar, tür, karar,
 *                                      hedef ID, sıra, beklenen hash'ler; İÇERİK DEĞERİ YOK)
 * Tablo sürümü 2: sürüm 1 kurulumu dbDelta ile idempotent yükseltilir (runs'a map_digest,
 * rollback_batches, rollback_items; plan_items tablosu).
 * JSON sütun tipi kullanılmaz (üretim MySQL/MariaDB sürümü doğrulanmadı);
 * JSON uygulama düzeyindedir ve MaviBelge_Core_Import_Rollback_Codec ile
 * fail-closed çözülür. Gizli bilgi veya kişisel veri saklanmaz: yalnız
 * kimlikler, hash'ler, yönetilen alan değerleri ve sayaçlar.
 *
 * Kurulum idempotent ve sürümlüdür (dbDelta); sürüm seçeneği YALNIZ iki
 * tablonun gerçekten var olduğu doğrulandıktan sonra yazılır. Kurulum
 * yalnız apply yolunda (bütün plan kapıları geçtikten sonra) çağrılır;
 * dry-run ve status komutları tablo oluşturmaz.
 *
 * Yalnız bu dosya bu iki tabloya `$wpdb->insert/update` yapar; içerik/meta
 * tablolarına ham SQL yazımı YOKTUR.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Wpdb_Run_Store implements MaviBelge_Core_Import_Run_Store {

	const TABLE_VERSION  = '2';
	const VERSION_OPTION = 'mavibelge_core_import_tables_version';
	const LOCK_SUFFIX    = 'mavibelge_import';

	public static function runs_table() {
		global $wpdb;
		return $wpdb->prefix . 'mb_import_runs';
	}

	public static function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'mb_import_run_items';
	}

	public static function plan_items_table() {
		global $wpdb;
		return $wpdb->prefix . 'mb_import_run_plan_items';
	}

	public function is_installed() {
		return self::TABLE_VERSION === get_option( self::VERSION_OPTION ) && self::table_exists( self::runs_table() ) && self::table_exists( self::items_table() ) && self::table_exists( self::plan_items_table() );
	}

	public function ensure_installed() {
		if ( ! $this->is_installed() ) {
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$collate = $wpdb->get_charset_collate();
			$runs    = self::runs_table();
			$items   = self::items_table();
			$plan    = self::plan_items_table();
			dbDelta(
				"CREATE TABLE {$runs} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				uid CHAR(32) NOT NULL,
				stage VARCHAR(20) NOT NULL,
				status VARCHAR(20) NOT NULL,
				plan_digest CHAR(64) NOT NULL,
				manifest_digest CHAR(64) NOT NULL,
				batch_size SMALLINT UNSIGNED NOT NULL,
				total_writes INT UNSIGNED NOT NULL,
				committed_batches INT UNSIGNED NOT NULL DEFAULT 0,
				committed_items INT UNSIGNED NOT NULL DEFAULT 0,
				error_code VARCHAR(64) NULL,
				map_digest CHAR(64) NULL,
				rollback_batches INT UNSIGNED NOT NULL DEFAULT 0,
				rollback_items INT UNSIGNED NOT NULL DEFAULT 0,
				created_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uid (uid),
				KEY status (status)
			) {$collate};"
			);
			dbDelta(
				"CREATE TABLE {$items} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				run_id BIGINT UNSIGNED NOT NULL,
				seq INT UNSIGNED NOT NULL,
				batch_no INT UNSIGNED NOT NULL,
				source_key VARCHAR(191) NOT NULL,
				type VARCHAR(20) NOT NULL,
				decision VARCHAR(10) NOT NULL,
				target_id BIGINT UNSIGNED NOT NULL,
				rollback_record LONGTEXT NOT NULL,
				rollback_status VARCHAR(20) NOT NULL DEFAULT 'pending',
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY run_seq (run_id, seq),
				KEY type_decision (type, decision, rollback_status)
			) {$collate};"
			);
			dbDelta(
				"CREATE TABLE {$plan} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				run_id BIGINT UNSIGNED NOT NULL,
				seq INT UNSIGNED NOT NULL,
				source_key VARCHAR(191) NOT NULL,
				type VARCHAR(20) NOT NULL,
				decision VARCHAR(10) NOT NULL,
				target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				expected_incoming_hash CHAR(64) NOT NULL,
				expected_current_hash CHAR(64) NULL,
				expected_last_applied_hash CHAR(64) NULL,
				natural_key_check VARCHAR(20) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY run_seq (run_id, seq)
			) {$collate};"
			);
			if ( ! self::table_exists( $runs ) || ! self::table_exists( $items ) || ! self::table_exists( $plan ) || ! self::column_exists( $runs, 'map_digest' ) || ! self::column_exists( $runs, 'rollback_items' ) ) {
				if ( function_exists( 'error_log' ) ) {
					error_log( 'mavibelge-core: import run tabloları oluşturulamadı; apply başlatılmadı.' );
				}
				return false;
			}
			update_option( self::VERSION_OPTION, self::TABLE_VERSION, false );
		}
		// Audit hedefi de hazır olmalı (admin_init dışındaki CLI bağlamı için; idempotent).
		MaviBelge_Core_Audit_Log::install();
		return $this->is_installed();
	}

	public function acquire_lock() {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', self::lock_name() ) );
	}

	public function release_lock() {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name() ) );
	}

	public function create_run( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$uid = bin2hex( random_bytes( 16 ) );
		$ok  = $wpdb->insert(
			self::runs_table(),
			array(
				'uid'               => $uid,
				'stage'             => (string) $data['stage'],
				'status'            => MaviBelge_Core_Import_Run_State::PLANNED,
				'plan_digest'       => (string) $data['plan_digest'],
				'manifest_digest'   => (string) $data['manifest_digest'],
				'batch_size'        => (int) $data['batch_size'],
				'total_writes'      => (int) $data['total_writes'],
				'committed_batches' => 0,
				'committed_items'   => 0,
				'created_by'        => (int) $data['created_by'],
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		if ( 1 !== $ok ) {
			return null;
		}
		return $this->get_run( $uid );
	}

	public function transition( $runId, $from, $to, array $fields = array() ) {
		if ( ! MaviBelge_Core_Import_Run_State::can_transition( $from, $to ) ) {
			return false;
		}
		global $wpdb;
		$data    = array( 'status' => $to, 'updated_at' => current_time( 'mysql', true ) );
		$formats = array( '%s', '%s' );
		if ( array_key_exists( 'error_code', $fields ) ) {
			$code = $fields['error_code'];
			if ( null !== $code && ( ! is_string( $code ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}\z/', $code ) ) ) {
				return false;
			}
			$data['error_code'] = $code;
			$formats[]          = '%s';
		}
		return 1 === $wpdb->update( self::runs_table(), $data, array( 'id' => (int) $runId, 'status' => $from ), $formats, array( '%d', '%s' ) );
	}

	public function get_run( $uid ) {
		if ( ! is_string( $uid ) || 1 !== preg_match( '/^[0-9a-f]{32}\z/', $uid ) ) {
			return null;
		}
		global $wpdb;
		$table = self::runs_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uid = %s", $uid ), ARRAY_A );
		return is_array( $row ) ? self::normalize_run( $row ) : null;
	}

	public function find_runs_in_status( array $statuses ) {
		$statuses = array_values( array_intersect( $statuses, MaviBelge_Core_Import_Run_State::ALL ) );
		if ( empty( $statuses ) ) {
			return array();
		}
		global $wpdb;
		$table = self::runs_table();
		$in    = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN ({$in}) ORDER BY id ASC", $statuses ), ARRAY_A );
		return is_array( $rows ) ? array_map( array( __CLASS__, 'normalize_run' ), $rows ) : array();
	}

	public function list_runs( $limit ) {
		global $wpdb;
		$table = self::runs_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max( 1, min( 100, (int) $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? array_map( array( __CLASS__, 'normalize_run' ), $rows ) : array();
	}

	public function add_item( $runId, array $item ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::items_table(),
			array(
				'run_id'          => (int) $runId,
				'seq'             => (int) $item['seq'],
				'batch_no'        => (int) $item['batch_no'],
				'source_key'      => (string) $item['source_key'],
				'type'            => (string) $item['type'],
				'decision'        => (string) $item['decision'],
				'target_id'       => (int) $item['target_id'],
				'rollback_record' => (string) $item['rollback_record'],
				'rollback_status' => 'pending',
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return 1 === $ok;
	}

	public function record_checkpoint( $runId, $committedBatches, $committedItems, $expectedBatches = null ) {
		global $wpdb;
		$where   = array( 'id' => (int) $runId, 'status' => MaviBelge_Core_Import_Run_State::RUNNING );
		$formats = array( '%d', '%s' );
		if ( null !== $expectedBatches ) {
			$where['committed_batches'] = (int) $expectedBatches;
			$formats[]                  = '%d';
		}
		return 1 === $wpdb->update(
			self::runs_table(),
			array( 'committed_batches' => (int) $committedBatches, 'committed_items' => (int) $committedItems, 'updated_at' => current_time( 'mysql', true ) ),
			$where,
			array( '%d', '%d', '%s' ),
			$formats
		);
	}

	public function record_rollback_checkpoint( $runId, $expectedBatches, $newBatches, $newItems ) {
		if ( ! is_int( $expectedBatches ) || ! is_int( $newBatches ) || ! is_int( $newItems ) || $newBatches < $expectedBatches || $newItems < 0 ) {
			return false;
		}
		global $wpdb;
		return 1 === $wpdb->update(
			self::runs_table(),
			array( 'rollback_batches' => $newBatches, 'rollback_items' => $newItems, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $runId, 'status' => MaviBelge_Core_Import_Run_State::ROLLING_BACK, 'rollback_batches' => $expectedBatches ),
			array( '%d', '%d', '%s' ),
			array( '%d', '%s', '%d' )
		);
	}

	public function create_run_with_plan( array $data, array $planItems ) {
		if ( array() !== MaviBelge_Core_Import_Plan_Snapshot::validate_items( $planItems ) || (int) $data['total_writes'] !== count( $planItems ) ) {
			return null;
		}
		global $wpdb;
		$now       = current_time( 'mysql', true );
		$uid       = bin2hex( random_bytes( 16 ) );
		$mapDigest = isset( $data['map_digest'] ) && MaviBelge_Core_Import_Apply_Plan::is_digest( $data['map_digest'] ) ? $data['map_digest'] : null;
		$ok        = $wpdb->insert(
			self::runs_table(),
			array(
				'uid'               => $uid,
				'stage'             => (string) $data['stage'],
				'status'            => MaviBelge_Core_Import_Run_State::READY,
				'plan_digest'       => (string) $data['plan_digest'],
				'manifest_digest'   => (string) $data['manifest_digest'],
				'map_digest'        => $mapDigest,
				'batch_size'        => (int) $data['batch_size'],
				'total_writes'      => (int) $data['total_writes'],
				'committed_batches' => 0,
				'committed_items'   => 0,
				'created_by'        => (int) $data['created_by'],
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		if ( 1 !== $ok ) {
			return null;
		}
		$runId = (int) $wpdb->insert_id;
		foreach ( $planItems as $item ) {
			$inserted = $wpdb->insert(
				self::plan_items_table(),
				array(
					'run_id'                     => $runId,
					'seq'                        => $item['seq'],
					'source_key'                 => $item['source_key'],
					'type'                       => $item['type'],
					'decision'                   => $item['decision'],
					'target_id'                  => $item['target_id'],
					'expected_incoming_hash'     => $item['expected_incoming_hash'],
					'expected_current_hash'      => $item['expected_current_hash'],
					'expected_last_applied_hash' => $item['expected_last_applied_hash'],
					'natural_key_check'          => $item['natural_key_check'],
					'created_at'                 => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( 1 !== $inserted ) {
				// Transaction dışı çağrıya karşı savunma: yarım run/snapshot bırakma.
				$wpdb->delete( self::plan_items_table(), array( 'run_id' => $runId ), array( '%d' ) );
				$wpdb->delete( self::runs_table(), array( 'id' => $runId ), array( '%d' ) );
				return null;
			}
		}
		return $this->get_run( $uid );
	}

	public function get_plan_items( $runId, $afterSeq, $limit ) {
		global $wpdb;
		$table = self::plan_items_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE run_id = %d AND seq > %d ORDER BY seq ASC LIMIT %d", (int) $runId, (int) $afterSeq, max( 1, min( 500, (int) $limit ) ) ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'seq'                        => (int) $row['seq'],
				'source_key'                 => (string) $row['source_key'],
				'type'                       => (string) $row['type'],
				'decision'                   => (string) $row['decision'],
				'target_id'                  => (int) $row['target_id'],
				'expected_incoming_hash'     => (string) $row['expected_incoming_hash'],
				'expected_current_hash'      => null === $row['expected_current_hash'] ? null : (string) $row['expected_current_hash'],
				'expected_last_applied_hash' => null === $row['expected_last_applied_hash'] ? null : (string) $row['expected_last_applied_hash'],
				'natural_key_check'          => null === $row['natural_key_check'] ? null : (string) $row['natural_key_check'],
			);
		}
		return $out;
	}

	public function get_items( $runId ) {
		global $wpdb;
		$table = self::items_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE run_id = %d ORDER BY seq ASC", (int) $runId ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'              => (int) $row['id'],
				'run_id'          => (int) $row['run_id'],
				'seq'             => (int) $row['seq'],
				'batch_no'        => (int) $row['batch_no'],
				'source_key'      => (string) $row['source_key'],
				'type'            => (string) $row['type'],
				'decision'        => (string) $row['decision'],
				'target_id'       => (int) $row['target_id'],
				'rollback_record' => (string) $row['rollback_record'],
				'rollback_status' => (string) $row['rollback_status'],
			);
		}
		return $out;
	}

	public function rolled_back_create_target_ids( $type ) {
		global $wpdb;
		$table = self::items_table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT target_id FROM {$table} WHERE type = %s AND decision = 'create' AND rollback_status = 'rolled_back'", (string) $type ) );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	public function mark_item_rolled_back( $itemId ) {
		global $wpdb;
		return 1 === $wpdb->update( self::items_table(), array( 'rollback_status' => 'rolled_back' ), array( 'id' => (int) $itemId, 'rollback_status' => 'pending' ), array( '%s' ), array( '%d', '%s' ) );
	}

	private static function normalize_run( array $row ) {
		return array(
			'id'                => (int) $row['id'],
			'uid'               => (string) $row['uid'],
			'stage'             => (string) $row['stage'],
			'status'            => (string) $row['status'],
			'plan_digest'       => (string) $row['plan_digest'],
			'manifest_digest'   => (string) $row['manifest_digest'],
			'batch_size'        => (int) $row['batch_size'],
			'total_writes'      => (int) $row['total_writes'],
			'committed_batches' => (int) $row['committed_batches'],
			'committed_items'   => (int) $row['committed_items'],
			'error_code'        => null === $row['error_code'] ? null : (string) $row['error_code'],
			'map_digest'        => isset( $row['map_digest'] ) ? (string) $row['map_digest'] : null,
			'rollback_batches'  => isset( $row['rollback_batches'] ) ? (int) $row['rollback_batches'] : 0,
			'rollback_items'    => isset( $row['rollback_items'] ) ? (int) $row['rollback_items'] : 0,
			'created_by'        => (int) $row['created_by'],
			'created_at'        => (string) $row['created_at'],
			'updated_at'        => (string) $row['updated_at'],
		);
	}

	private static function lock_name() {
		global $wpdb;
		return $wpdb->prefix . self::LOCK_SUFFIX;
	}

	private static function column_exists( $table, $column ) {
		global $wpdb;
		return $column === $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ), 0 );
	}

	private static function table_exists( $table ) {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}
}
