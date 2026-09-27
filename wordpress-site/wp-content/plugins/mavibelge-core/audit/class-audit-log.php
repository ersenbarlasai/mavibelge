<?php
/**
 * Minimal audit log for critical content/price events.
 *
 * Table: {$wpdb->prefix}mb_audit_log. Context is stored as LONGTEXT
 * holding a JSON-encoded array (application-level JSON string, not a
 * MySQL JSON column type — see docs/content-model.md §Denetim günlüğü
 * for why: MySQL/MariaDB version and JSON column support have not been
 * verified in production, matris §7).
 *
 * Never write passwords, nonces, full personal data, secret keys, or
 * uploaded file contents into the context field (AGENTS.md §4 /
 * görev kartı 02 §9).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Audit_Log {

	/** Sürüm 2 (Faz 10): prev_hash/row_hash bütünlük zinciri. Eski (v1) satırlar row_hash='' ile korunur. */
	const TABLE_VERSION = '2';

	/** Zincir yazımı için kilit bekleme süresi (sn). */
	const LOCK_TIMEOUT = 3;

	const EVENT_CONTENT_META_CHANGED   = 'content_meta_changed';
	const EVENT_PRICE_OPTION_CHANGED   = 'price_option_changed';
	const EVENT_ACTIVE_PERIOD_CHANGED  = 'active_tariff_period_changed';
	const EVENT_APPROVAL_STATUS_CHANGED = 'approval_status_changed';

	/* Faz 8 — form olayları. object_type 'mb_form', object_id 0; context YALNIZ form kimliği ve sabit sonuç kodu (kişisel veri YOK). */
	const EVENT_FORM_SUBMITTED = 'form_submitted';
	const EVENT_FORM_REJECTED  = 'form_rejected';

	/*
	 * Faz 6B3 — katalog içe aktarım olayları. object_type 'mb_import_run',
	 * object_id run'ın veritabanı ID'sidir. Context yalnız
	 * MaviBelge_Core_Import_Audit_Context::build()'in kapalı izin listesinden
	 * geçer (run ID, source key, tür, karar, hedef ID, hash, değişen alan
	 * adları, hata kodu, batch/checkpoint numarası) — alan içeriği yazılmaz.
	 */
	const EVENT_IMPORT_RUN_STARTED        = 'import_run_started';
	const EVENT_IMPORT_BATCH_COMMITTED    = 'import_batch_committed';
	const EVENT_IMPORT_RUN_COMPLETED      = 'import_run_completed';
	const EVENT_IMPORT_RUN_FAILED         = 'import_run_failed';
	const EVENT_IMPORT_ROLLBACK_STARTED   = 'import_rollback_started';
	const EVENT_IMPORT_ROLLBACK_COMPLETED = 'import_rollback_completed';
	const EVENT_IMPORT_ROLLBACK_FAILED    = 'import_rollback_failed';
	// Faz 12: taslak sayfaların ayrı, onaylı yayınlanması (run'a bağlı değildir).
	const EVENT_IMPORT_PAGES_PUBLISHED    = 'import_pages_published';

	/** Faz 6B4 — sektör görsel eşlemesi değişti (context: eski/yeni map digest'i + değişen slug adları; dosya yolu YOK). */
	const EVENT_IMPORT_IMAGE_MAP_CHANGED  = 'import_image_map_changed';

	/** Per-request cache so table_exists() issues at most one query. */
	private static $table_confirmed_exists = null;

	/** İstek içi önbellek: v2 zincir sütunları var mı. */
	private static $chain_columns_present = null;

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'mb_audit_log';
	}

	/**
	 * Idempotent, dbDelta-based table creation. Safe to call on every
	 * admin_init; only re-runs the actual DDL when the schema version
	 * option is behind. Crucially, the version option is only written
	 * AFTER confirming the table actually exists — if dbDelta() fails
	 * silently (e.g. insufficient DB privileges), the version stays
	 * unset and the next admin_init retries instead of permanently
	 * believing a table exists that never got created.
	 */
	public static function install() {
		if ( get_option( 'mavibelge_core_audit_table_version' ) === self::TABLE_VERSION ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(64) NOT NULL,
			object_type VARCHAR(64) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			context LONGTEXT NULL,
			created_at_gmt DATETIME NOT NULL,
			prev_hash CHAR(64) NOT NULL DEFAULT '',
			row_hash CHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY object_type_id (object_type, object_id),
			KEY created_at_gmt (created_at_gmt)
		) {$charset_collate};";

		dbDelta( $sql );

		self::$table_confirmed_exists = null; // Force a fresh check after dbDelta().

		self::$chain_columns_present = null;

		// Sürüm seçeneği YALNIZ tablo VE v2 sütunları gerçekten varsa yazılır (dbDelta sessizce başarısız olabilir).
		if ( self::table_exists() && self::chain_columns_present() ) {
			update_option( 'mavibelge_core_audit_table_version', self::TABLE_VERSION );
		} elseif ( function_exists( 'error_log' ) ) {
			// Server-side diagnosis only — never surfaced to visitors,
			// never contains query text or credentials.
			error_log( 'mavibelge-core: mb_audit_log table creation did not succeed; will retry on next admin_init.' );
		}
	}

	/**
	 * @return bool Whether the audit table currently exists in the DB.
	 */
	public static function table_exists() {
		if ( null !== self::$table_confirmed_exists ) {
			return self::$table_confirmed_exists;
		}
		global $wpdb;
		$table_name = self::table_name();
		// esc_like() escapes "%" and "_" (LIKE wildcards) in the table
		// name before it goes into the LIKE pattern — without it, a
		// literal "_" in the table name (wpdb prefixes routinely
		// contain one, e.g. "wp_") matches ANY character, so a
		// similarly-named table could produce a false positive "yes,
		// it exists". prepare() still supplies proper quoting on top.
		$like                          = $wpdb->esc_like( $table_name );
		$found                        = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		self::$table_confirmed_exists = ( $found === $table_name );
		return self::$table_confirmed_exists;
	}

	/**
	 * @param string $event_type  One of the EVENT_* constants (or a future one).
	 * @param string $object_type Post type or other object type key.
	 * @param int    $object_id
	 * @param array  $context     Safe, non-sensitive context data.
	 * @return bool True if the row was written.
	 */
	public static function record( $event_type, $object_type, $object_id, array $context = array() ) {
		if ( ! self::table_exists() ) {
			// Table not ready (e.g. install() hasn't succeeded yet).
			// Silently skip — never fatal, never visitor-facing output.
			return false;
		}

		global $wpdb;

		$json = wp_json_encode( $context );
		$row  = array(
			'event_type'     => sanitize_key( $event_type ),
			'object_type'    => sanitize_key( $object_type ),
			'object_id'      => (int) $object_id,
			'user_id'        => (int) get_current_user_id(),
			'context'        => is_string( $json ) ? $json : '[]',
			'created_at_gmt' => current_time( 'mysql', true ),
		);
		$formats = array( '%s', '%s', '%d', '%d', '%s', '%s' );

		if ( ! self::chain_columns_present() ) {
			// Tablo henüz v2'ye yükseltilmedi (ör. admin_init çalışmadan CLI): eski biçimde yaz; zincir dışı v1 satırı olur.
			return false !== $wpdb->insert( self::table_name(), $row, $formats );
		}

		// Zincirli yazım GET_LOCK ile SERİLEŞTİRİLİR. Kilit alınamazsa satır YAZILMAZ (kilitsiz yazım yoktur; çağıranlar
		// audit başarısızlığına zaten toleranslıdır).
		if ( ! self::acquire_lock() ) {
			return false;
		}
		try {
			$group = MaviBelge_Core_Audit_Chain::group( $row['event_type'] );
			$prev  = self::last_row_hash( $group );
			if ( null === $prev ) {
				return false; // önceki hash okunamadı: zinciri yanlış başlatmak yerine yazma
			}
			$row['prev_hash'] = $prev;
			$row['row_hash']  = MaviBelge_Core_Audit_Chain::compute( $prev, $row );
			return false !== $wpdb->insert( self::table_name(), $row, array_merge( $formats, array( '%s', '%s' ) ) );
		} finally {
			self::release_lock();
		}
	}

	/* ------------------------------------------------------- Faz 10: zincir */

	/** @return bool v2 zincir sütunları (prev_hash/row_hash) mevcut mu? */
	public static function chain_columns_present() {
		if ( null !== self::$chain_columns_present ) {
			return self::$chain_columns_present;
		}
		global $wpdb;
		$found                        = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `' . self::table_name() . '` LIKE %s', $wpdb->esc_like( 'row_hash' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tablo adı $wpdb->prefix'ten.
		self::$chain_columns_present = ( 'row_hash' === $found );
		return self::$chain_columns_present;
	}

	private static function lock_name() {
		global $wpdb;
		return 'mbc_audit_' . substr( md5( $wpdb->prefix ), 0, 16 );
	}

	private static function acquire_lock() {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::lock_name(), self::LOCK_TIMEOUT ) );
	}

	private static function release_lock() {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name() ) );
	}

	/** SQL koşulu: grubun olay türleri. */
	private static function group_condition( $group ) {
		global $wpdb;
		$op = MaviBelge_Core_Audit_Chain::GROUP_FORM === $group ? 'LIKE' : 'NOT LIKE';
		return $wpdb->prepare( "event_type {$op} %s", $wpdb->esc_like( 'form_' ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $op iki sabitten biri.
	}

	/**
	 * Grubun zincirindeki son row_hash. Zincirde satır yoksa '' (zincir başı); sorgu hatasında null.
	 *
	 * @return string|null
	 */
	private static function last_row_hash( $group ) {
		global $wpdb;
		$wpdb->last_error = '';
		$hash             = $wpdb->get_var( "SELECT row_hash FROM `" . self::table_name() . "` WHERE row_hash <> '' AND " . self::group_condition( $group ) . ' ORDER BY id DESC LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( '' !== (string) $wpdb->last_error ) {
			return null;
		}
		return null === $hash ? '' : (string) $hash;
	}

	/**
	 * Zinciri doğrular: en yeni `$limit` v2 satırını (varsayılan 5000) eskiden yeniye kontrol eder.
	 *
	 * @return array{ok: bool, checked: int, legacy: int, first_bad_id: int|null, reason: string|null}
	 */
	public static function verify_chain( $limit = 5000 ) {
		$none = array( 'ok' => true, 'checked' => 0, 'legacy' => 0, 'first_bad_id' => null, 'reason' => 'no_chain' );
		if ( ! self::table_exists() || ! self::chain_columns_present() ) {
			return $none;
		}
		global $wpdb;
		$limit  = max( 1, min( 100000, (int) $limit ) );
		$table  = self::table_name();
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT id, event_type, object_type, object_id, user_id, context, created_at_gmt, prev_hash, row_hash FROM `{$table}` WHERE row_hash <> '' ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $rows ) ) {
			return array( 'ok' => false, 'checked' => 0, 'legacy' => 0, 'first_bad_id' => null, 'reason' => 'read_failed' );
		}
		$result           = MaviBelge_Core_Audit_Chain::verify( array_reverse( $rows ) );
		$result['legacy'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE row_hash = ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $result;
	}

	/**
	 * Saklama: her zincirin yalnız EN ESKİ ön ekini siler (form 90 gün, diğer 365 gün; süzgeçle değiştirilebilir, en az 30).
	 * Zincir ön eki silindiği için ilk kalan satırın prev_hash'i çapa olur (bkz. Audit_Chain).
	 *
	 * @param int|null $nowTs Test için "şimdi" (UNIX zamanı).
	 * @return array{form: int, core: int}|null Silinen satır sayıları; tablo yoksa/kilit alınamazsa null.
	 */
	public static function prune( $nowTs = null ) {
		if ( ! self::table_exists() ) {
			return null;
		}
		global $wpdb;
		$now   = null === $nowTs ? time() : (int) $nowTs;
		$table = self::table_name();
		if ( ! self::acquire_lock() ) {
			return null;
		}
		$deleted = array( 'form' => 0, 'core' => 0 );
		try {
			foreach ( array( MaviBelge_Core_Audit_Chain::GROUP_FORM, MaviBelge_Core_Audit_Chain::GROUP_CORE ) as $group ) {
				$days = (int) apply_filters( 'mavibelge_core_audit_retention_days', MaviBelge_Core_Audit_Chain::retention_days( $group ), $group );
				$days = max( 30, $days );
				$cut  = gmdate( 'Y-m-d H:i:s', $now - $days * 86400 );
				$cond = self::group_condition( $group );
				// Önek garantisi: eşik altındaki EN BÜYÜK id'ye kadar sil (saat kayması aradan satır bırakmaz).
				$max = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM `{$table}` WHERE created_at_gmt < %s AND {$cond}", $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( null !== $max && (int) $max > 0 ) {
					$count = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id <= %d AND {$cond}", (int) $max ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$deleted[ $group ] = is_int( $count ) ? $count : 0;
				}
			}
		} finally {
			self::release_lock();
		}
		return $deleted;
	}

	/**
	 * Salt okunur tablo durumu (sağlık ekranı): satır sayısı, boyut, motor.
	 *
	 * @return array{rows: int, bytes: int, engine: string}|null
	 */
	public static function table_status() {
		if ( ! self::table_exists() ) {
			return null;
		}
		global $wpdb;
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( self::table_name() ) ), ARRAY_A );
		if ( ! is_array( $status ) ) {
			return null;
		}
		return array(
			'rows'   => (int) ( isset( $status['Rows'] ) ? $status['Rows'] : 0 ),
			'bytes'  => (int) ( isset( $status['Data_length'] ) ? $status['Data_length'] : 0 ) + (int) ( isset( $status['Index_length'] ) ? $status['Index_length'] : 0 ),
			'engine' => (string) ( isset( $status['Engine'] ) ? $status['Engine'] : '' ),
		);
	}
}
