<?php
/**
 * Faz 6B2/6B3 — `wp mavibelge import ...` WP-CLI komutları.
 *
 * Bu SINIF KENDİ KARAR MANTIĞINI YAZMAZ. Dry-run yalnız
 * `MaviBelge_Core_Import_Dry_Run_Service`'i (admin ekranıyla PAYLAŞILAN AYNI
 * servis), apply/rollback yalnız `MaviBelge_Core_Import_Apply_Service` /
 * `MaviBelge_Core_Import_Rollback_Service`'i çağırır.
 *
 * Varsayılan davranış hâlâ SALT OKUNUR dry-run'dır. Gerçek yazma yalnız:
 *  - `--apply` açıkça verilirse,
 *  - wp-config.php'de `MAVIBELGE_IMPORT_APPLY_ENABLED` sabiti `true` ise
 *    (varsayılan KAPALI; tanımlı değilse apply hiç çalışmaz),
 *  - çağıran kullanıcı (`--user=`) `manage_options` VE
 *    `mb_manage_tariff_period` yetkisine sahipse,
 *  - `--confirm=<plan_digest>` önizlemede gösterilen plan özetine BİREBİR
 *    eşitse
 * mümkündür. `--force`, conflict atlama, kısmi kayıt yazma veya benzeri bir
 * kaçış yolu YOKTUR; bilinmeyen parametreleri WP-CLI reddeder.
 *
 * Bu dosya yalnız WP-CLI ortamında (`defined('WP_CLI') && WP_CLI`) require
 * edilir (bkz. mavibelge-core.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_CLI_Command {

	/**
	 * Faz 6A manifestlerini WordPress kayıtlarına karşı planlar (varsayılan:
	 * SALT OKUNUR dry-run). `--apply` ile, yalnız aşağıdaki kapılar geçerse,
	 * onaylanan planı yazar.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Salt okunur plan (varsayılan davranış; verilmese de aynıdır). --apply ile birlikte verilemez.
	 *
	 * [--apply]
	 * : Onaylanan planı GERÇEKTEN yazar. Varsayılan KAPALI: MAVIBELGE_IMPORT_APPLY_ENABLED === true, manage_options + mb_manage_tariff_period yetkisi (--user) ve --confirm gerekir.
	 *
	 * [--confirm=<plan_digest>]
	 * : Dry-run çıktısındaki 64 karakterlik plan_digest. Plan veya manifest değiştiyse apply reddedilir.
	 *
	 * [--stage=<stage>]
	 * : Bağımlılık aşaması. sectors = yalnız sektörler; qualifications = sektörler + yeterlilikler; all = tam katalog; content = haberler sonra referanslar (news.manifest.json + references.manifest.json; katalogdan bağımsız; iki kontrollü mb_haber_turu terimi `haber`/`duyuru` ÖNCEDEN var olmalı, import bunları oluşturmaz).
	 * ---
	 * default: all
	 * options:
	 *   - sectors
	 *   - qualifications
	 *   - all
	 *   - content
	 * ---
	 *
	 * [--batch-size=<n>]
	 * : Apply batch boyutu (1-20, varsayılan 20). Her batch kendi transaction'ındadır.
	 *
	 * [--format=<format>]
	 * : Çıktı biçimi.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mavibelge import catalog --dry-run
	 *     wp mavibelge import catalog --dry-run --stage=sectors --format=json
	 *     wp mavibelge import catalog --dry-run --stage=content --format=json
	 *     wp mavibelge import catalog --apply --stage=sectors --confirm=<plan_digest> --user=<yönetici>
	 *
	 * @when after_wp_load
	 */
	public function catalog( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		// Düzeltme ve Kabul §2.4 — bilinmeyen format fail-closed reddedilir;
		// keyfî olarak table'a düşürülmez.
		if ( ! in_array( $format, array( 'table', 'json' ), true ) ) {
			WP_CLI::error( sprintf( 'Bilinmeyen --format değeri: "%s" (yalnız table/json kabul edilir).', is_string( $format ) ? $format : gettype( $format ) ) );
			return;
		}
		$stage = isset( $assoc_args['stage'] ) ? $assoc_args['stage'] : MaviBelge_Core_Import_Apply_Plan::STAGE_ALL;
		if ( ! in_array( $stage, MaviBelge_Core_Import_Apply_Plan::ALL_STAGES, true ) ) {
			WP_CLI::error( 'Bilinmeyen --stage değeri.' );
			return;
		}
		$apply = ! empty( $assoc_args['apply'] );
		if ( $apply && ! empty( $assoc_args['dry-run'] ) ) {
			WP_CLI::error( '--dry-run ve --apply birlikte verilemez.' );
			return;
		}
		if ( ! $apply && ( isset( $assoc_args['confirm'] ) || isset( $assoc_args['batch-size'] ) ) ) {
			WP_CLI::error( '--confirm ve --batch-size yalnız --apply ile kullanılır.' );
			return;
		}
		if ( $apply ) {
			$this->apply_catalog( $stage, $assoc_args, $format );
			return;
		}

		// SALT OKUNUR dry-run (Faz 6B2 davranışı; yazma sınıfı hiç kullanılmaz).
		$repository = new MaviBelge_Core_Import_WordPress_Target_Repository();
		$service    = new MaviBelge_Core_Import_Dry_Run_Service( $repository );
		$stageRun   = $service->run_stage( $stage );
		$result     = array(
			'plan'             => $stageRun['plan'],
			'diagnostics'      => $stageRun['diagnostics'],
			'generated_at_utc' => $stageRun['generated_at_utc'],
			'read_only'        => true,
			'load_errors'      => $stageRun['load_errors'],
			'stage'            => $stage,
			'plan_digest'      => $stageRun['ok'] ? MaviBelge_Core_Import_Apply_Plan::plan_digest( $stage, $stageRun['manifest_digest'], $stageRun['plan'] ) : null,
		);

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( self::to_safe_output( $result ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
		} else {
			self::print_table( $result );
		}

		if ( self::has_errors( $result ) ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Bir import run'ını doğrulanmış rollback kayıtlarıyla geri alır.
	 * `--confirm` verilmezse SALT OKUNUR önizleme yapar (bekleyen item'lar,
	 * engeller ve onaylanacak rollback_digest). Rollback veritabanı
	 * yedeğinin yerine GEÇMEZ.
	 *
	 * ## OPTIONS
	 *
	 * --run-id=<uid>
	 * : Geri alınacak run'ın 32 karakterlik kimliği.
	 *
	 * [--confirm=<rollback_digest>]
	 * : Önizlemedeki rollback_digest. Verilmezse yalnız önizleme yapılır.
	 *
	 * [--batch-size=<n>]
	 * : Rollback batch boyutu (1-20, varsayılan 20).
	 *
	 * [--format=<format>]
	 * : Çıktı biçimi.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mavibelge import rollback --run-id=<uid> --user=<yönetici>
	 *     wp mavibelge import rollback --run-id=<uid> --confirm=<rollback_digest> --user=<yönetici>
	 *
	 * @when after_wp_load
	 */
	public function rollback( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		if ( ! in_array( $format, array( 'table', 'json' ), true ) ) {
			WP_CLI::error( 'Bilinmeyen --format değeri.' );
			return;
		}
		if ( ! self::user_can_import() ) {
			WP_CLI::error( 'Yetkisiz: rollback için manage_options ve mb_manage_tariff_period yetkisi olan bir kullanıcı (--user) gerekir.' );
			return;
		}
		$uid     = isset( $assoc_args['run-id'] ) ? $assoc_args['run-id'] : null;
		$service = self::rollback_service();
		if ( ! isset( $assoc_args['confirm'] ) ) {
			if ( isset( $assoc_args['batch-size'] ) ) {
				WP_CLI::error( '--batch-size yalnız --confirm ile kullanılır.' );
				return;
			}
			$preview = $service->preview( $uid );
			self::emit( $format, $preview, 'Rollback önizlemesi (SALT OKUNUR)' );
			if ( ! $preview['ok'] || ! empty( $preview['blockers'] ) ) {
				WP_CLI::halt( 1 );
			}
			return;
		}
		if ( ! self::apply_enabled() ) {
			WP_CLI::error( 'Rollback kapalı: MAVIBELGE_IMPORT_APPLY_ENABLED wp-config.php içinde true olarak tanımlı değil.' );
			return;
		}
		$result = $service->rollback( $uid, $assoc_args['confirm'], isset( $assoc_args['batch-size'] ) ? $assoc_args['batch-size'] : null );
		self::emit( $format, $result, 'Rollback sonucu' );
		if ( ! $result['ok'] ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Import run'larını SALT OKUNUR listeler (tablo kurulu değilse hiçbir şey
	 * oluşturmaz).
	 *
	 * ## OPTIONS
	 *
	 * [--run-id=<uid>]
	 * : Tek bir run.
	 *
	 * [--format=<format>]
	 * : Çıktı biçimi.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @when after_wp_load
	 */
	public function status( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		if ( ! in_array( $format, array( 'table', 'json' ), true ) ) {
			WP_CLI::error( 'Bilinmeyen --format değeri.' );
			return;
		}
		if ( ! self::user_can_import() ) {
			WP_CLI::error( 'Yetkisiz: manage_options ve mb_manage_tariff_period yetkisi olan bir kullanıcı (--user) gerekir.' );
			return;
		}
		$store = new MaviBelge_Core_Import_Wpdb_Run_Store();
		$runs  = array();
		if ( $store->is_installed() ) {
			if ( isset( $assoc_args['run-id'] ) ) {
				$run  = $store->get_run( $assoc_args['run-id'] );
				$runs = null === $run ? array() : array( $run );
			} else {
				$runs = $store->list_runs( 20 );
			}
		}
		$rows = array();
		foreach ( $runs as $run ) {
			$rows[] = array(
				'run_uid'           => $run['uid'],
				'stage'             => $run['stage'],
				'status'            => $run['status'],
				'error_code'        => (string) $run['error_code'],
				'total_writes'      => $run['total_writes'],
				'committed_batches' => $run['committed_batches'],
				'committed_items'   => $run['committed_items'],
				'created_at_utc'    => $run['created_at'],
			);
		}
		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
			return;
		}
		if ( empty( $rows ) ) {
			WP_CLI::log( 'Import run kaydı yok.' );
			return;
		}
		self::print_rows( $rows, array_keys( $rows[0] ) );
	}

	private function apply_catalog( $stage, array $assoc_args, $format ) {
		if ( ! self::apply_enabled() ) {
			WP_CLI::error( 'Apply kapalı: MAVIBELGE_IMPORT_APPLY_ENABLED wp-config.php içinde true olarak tanımlı değil. Hiçbir şey yazılmadı.' );
			return;
		}
		if ( ! self::user_can_import() ) {
			WP_CLI::error( 'Yetkisiz: apply için manage_options ve mb_manage_tariff_period yetkisi olan bir kullanıcı (--user) gerekir. Hiçbir şey yazılmadı.' );
			return;
		}
		$service = self::apply_service();
		$preview = $service->preview( $stage );
		WP_CLI::log( sprintf( 'Aşama: %s | plan_digest: %s | uygun: %s | yazılacak: %d | değişmeyen: %d', $stage, (string) $preview['plan_digest'], $preview['eligible'] ? 'evet' : 'hayır', $preview['writes'], $preview['noops'] ) );
		if ( ! isset( $assoc_args['confirm'] ) ) {
			WP_CLI::error( 'Onay gerekli: planı inceledikten sonra --confirm=<plan_digest> ile yeniden çalıştırın. Hiçbir şey yazılmadı.' );
			return;
		}
		$result = $service->apply( $stage, $assoc_args['confirm'], isset( $assoc_args['batch-size'] ) ? $assoc_args['batch-size'] : null, get_current_user_id() );
		self::emit( $format, $result, 'Apply sonucu' );
		if ( ! $result['ok'] ) {
			WP_CLI::halt( 1 );
		}
	}

	private static function apply_enabled() {
		return defined( 'MAVIBELGE_IMPORT_APPLY_ENABLED' ) && true === MAVIBELGE_IMPORT_APPLY_ENABLED;
	}

	private static function user_can_import() {
		return get_current_user_id() > 0 && current_user_can( 'manage_options' ) && current_user_can( 'mb_manage_tariff_period' );
	}

	private static function apply_service() {
		$repository = new MaviBelge_Core_Import_WordPress_Target_Repository();
		return new MaviBelge_Core_Import_Apply_Service(
			new MaviBelge_Core_Import_Dry_Run_Service( $repository ),
			new MaviBelge_Core_Import_WordPress_Target_Writer(),
			new MaviBelge_Core_Import_Wpdb_Transaction(),
			new MaviBelge_Core_Import_Wpdb_Run_Store(),
			new MaviBelge_Core_Import_WP_Audit_Sink()
		);
	}

	private static function rollback_service() {
		return new MaviBelge_Core_Import_Rollback_Service(
			new MaviBelge_Core_Import_WordPress_Target_Repository(),
			new MaviBelge_Core_Import_WordPress_Target_Writer(),
			new MaviBelge_Core_Import_Wpdb_Transaction(),
			new MaviBelge_Core_Import_Wpdb_Run_Store(),
			new MaviBelge_Core_Import_WP_Audit_Sink()
		);
	}

	/** Apply/rollback sonucu — yalnız kimlik, durum, sayaç ve sabit hata kodları (içerik/gizli bilgi yok). */
	private static function emit( $format, array $result, $title ) {
		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
			return;
		}
		WP_CLI::log( $title . ':' );
		foreach ( $result as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( null === $value ) {
				$value = '-';
			}
			WP_CLI::log( sprintf( '  %s: %s', $key, $value ) );
		}
	}

	private static function has_errors( array $result ) {
		return ! empty( $result['load_errors'] ) || ! empty( $result['plan']['errors'] );
	}

	/**
	 * Yalnız güvenli, özet alanlar taşır — tam alan DEĞERLERİ, kişisel
	 * veri, sunucu yolu veya kimlik bilgisi ASLA yazdırılmaz. Yalnız
	 * source_key/type/decision/reason/target_id, `changed_fields`'in ALAN
	 * ADLARI (değerleri değil), unresolved_dependencies ve hash'lerin (zaten
	 * içerik taşımayan) tam SHA-256 kısa formları.
	 */
	private static function to_safe_output( array $result ) {
		return array(
			'read_only'        => true,
			'generated_at_utc' => $result['generated_at_utc'],
			'stage'            => $result['stage'],
			'plan_digest'      => $result['plan_digest'],
			'load_errors'      => array_values( $result['load_errors'] ),
			'plan_errors'      => array_values( $result['plan']['errors'] ),
			'summary'          => $result['plan']['summary'],
			'diagnostics'      => $result['diagnostics'],
			'entries'          => array_map( array( __CLASS__, 'to_safe_entry' ), $result['plan']['entries'] ),
		);
	}

	private static function to_safe_entry( array $entry ) {
		return array(
			'source_key'              => $entry['source_key'],
			'type'                    => $entry['type'],
			'decision'                => $entry['decision'],
			'reason'                  => $entry['reason'],
			'target_id'               => $entry['target_id'],
			'changed_fields'          => $entry['changed_fields'],
			'unresolved_dependencies' => $entry['unresolved_dependencies'],
			'incoming_hash'           => $entry['incoming_hash'],
			'current_hash'            => $entry['current_hash'],
			'last_applied_hash'       => $entry['last_applied_hash'],
			// Faz 6B3 Önkoşul — doğal anahtar preflight durum kodu (içerik değil).
			'natural_key_check'       => array_key_exists( 'natural_key_check', $entry ) ? $entry['natural_key_check'] : null,
		);
	}

	private static function print_table( array $result ) {
		$summary = $result['plan']['summary'];

		WP_CLI::log( sprintf( 'Salt okunur dry-run — %s (UTC)', $result['generated_at_utc'] ) );

		foreach ( $result['load_errors'] as $error ) {
			WP_CLI::warning( $error );
		}
		foreach ( $result['plan']['errors'] as $error ) {
			WP_CLI::warning( $error );
		}

		WP_CLI::log(
			sprintf(
				'Toplam: %d | create=%d update=%d unchanged=%d conflict=%d blocked=%d invalid=%d',
				$summary['total'],
				$summary['operations']['create'],
				$summary['operations']['update'],
				$summary['operations']['unchanged'],
				$summary['operations']['conflict'],
				$summary['operations']['blocked'],
				$summary['operations']['invalid']
			)
		);
		WP_CLI::log(
			sprintf(
				'structurally_valid=%s applicable=%s (applicable=true YALNIZ Faz 6B3 için adaylık işaretidir — otomatik uygulama YETKİSİ DEĞİLDİR.)',
				$summary['structurally_valid'] ? 'true' : 'false',
				$summary['applicable'] ? 'true' : 'false'
			)
		);
		WP_CLI::log( sprintf( 'Aşama: %s | plan_digest: %s', $result['stage'], null === $result['plan_digest'] ? '-' : $result['plan_digest'] ) );

		if ( ! empty( $result['plan']['entries'] ) ) {
			$rows = array_map( array( __CLASS__, 'to_safe_entry' ), $result['plan']['entries'] );
			// Düzeltme ve Kabul §2.4 kök neden — `WP_CLI\Utils` bir SINIF
			// değil, fonksiyon içeren bir NAMESPACE'tir; `class_exists()`
			// bu yüzden HER ZAMAN false döner ve `format_items()` hiç
			// çağrılmazdı (satırlar sessizce hiç gösterilmiyordu). Doğru
			// kontrol `function_exists()`'tir; fonksiyon yoksa (WP-CLI'nin
			// çok eski/özel bir sürümü) satırları SESSİZCE ATLAMAK yerine
			// okunabilir bir fallback tablo yazdırılır.
			// §2.4 — tablo biçiminde en az source_key/type/decision/reason/
			// target_id, DEĞİŞEN alan adları ve çözülemeyen bağımlılık
			// adları görünür olmalı (değerler değil, yalnız adlar).
			$displayRows = array_map( array( __CLASS__, 'to_display_row' ), $rows );
			$columns     = array( 'source_key', 'type', 'decision', 'reason', 'target_id', 'changed_fields', 'unresolved_dependencies' );
			self::print_rows( $displayRows, $columns );
		}

		if ( ! empty( $result['diagnostics'] ) ) {
			WP_CLI::log( 'Tanılar:' );
			foreach ( $result['diagnostics'] as $diagnostic ) {
				WP_CLI::log( sprintf( '  [%s] %s (%s)', $diagnostic['type'], $diagnostic['code'], $diagnostic['source_key'] ) );
			}
		}
	}

	/** @param array $row to_safe_entry() çıktısı. */
	private static function to_display_row( array $row ) {
		return array(
			'source_key'              => $row['source_key'],
			'type'                    => $row['type'],
			'decision'                => $row['decision'],
			'reason'                  => $row['reason'],
			'target_id'               => null === $row['target_id'] ? '' : (string) $row['target_id'],
			'changed_fields'          => implode( ',', $row['changed_fields'] ),
			'unresolved_dependencies' => implode( ',', $row['unresolved_dependencies'] ),
		);
	}

	private static function print_rows( array $rows, array $columns ) {
		if ( function_exists( 'WP_CLI\\Utils\\format_items' ) ) {
			call_user_func( 'WP_CLI\\Utils\\format_items', 'table', $rows, $columns );
		} else {
			self::print_fallback_rows( $rows, $columns );
		}
	}

	/**
	 * `WP_CLI\Utils\format_items()` bulunamadığında kullanılan, WP-CLI
	 * bağımlılığı olmayan sade metin tablosu — satırları SESSİZCE ATLAMAZ,
	 * yalnız hizalama daha basittir.
	 *
	 * @param array<int, array<string,string>> $rows
	 * @param string[]                          $columns
	 */
	private static function print_fallback_rows( array $rows, array $columns ) {
		WP_CLI::log( implode( ' | ', $columns ) );
		foreach ( $rows as $row ) {
			$cells = array();
			foreach ( $columns as $column ) {
				$cells[] = isset( $row[ $column ] ) ? (string) $row[ $column ] : '';
			}
			WP_CLI::log( implode( ' | ', $cells ) );
		}
	}
}
