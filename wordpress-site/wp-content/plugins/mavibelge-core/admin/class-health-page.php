<?php
/**
 * Faz 10 — Araçlar → Mavi Belge Sağlık (SALT OKUNUR). `manage_options` gerekir. Hiçbir ayarı değiştirmez, form/POST yoktur;
 * parola, anahtar, sunucu yolu göstermez; phpinfo yoktur. Karar mantığı MaviBelge_Core_Health_Checks (saf) içindedir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Health_Page {

	const CAPABILITY = 'manage_options';
	const PAGE_SLUG  = 'mavibelge-core-health';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_submenu_page( 'tools.php', 'Mavi Belge Sağlık', 'Mavi Belge Sağlık', self::CAPABILITY, self::PAGE_SLUG, array( __CLASS__, 'render' ) );
	}

	/** Ortam dizisini salt okunur toplar. */
	public static function collect_env() {
		global $wpdb;
		$exts = array();
		foreach ( MaviBelge_Core_Health_Checks::REQUIRED_EXTENSIONS as $ext ) {
			$exts[ $ext ] = extension_loaded( $ext );
		}
		$engines = array();
		foreach ( MaviBelge_Core_Uninstall_Scope::tables( $wpdb->prefix ) as $table ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
			if ( is_array( $row ) && isset( $row['Engine'] ) ) {
				$engines[ $table ] = (string) $row['Engine'];
			}
		}
		$uploads = wp_get_upload_dir();
		$free    = function_exists( 'disk_free_space' ) ? @disk_free_space( WP_CONTENT_DIR ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- kısıtlı hosting'te devre dışı olabilir.

		$forms   = array( 'total' => 0, 'closed' => 0, 'reasons' => array() );
		foreach ( MaviBelge_Core_Forms_Service::admin_overview() as $row ) {
			$forms['total']++;
			if ( empty( $row['open'] ) ) {
				$forms['closed']++;
				foreach ( (array) $row['reasons'] as $code ) {
					$forms['reasons'][ $code ] = ( isset( $forms['reasons'][ $code ] ) ? $forms['reasons'][ $code ] : 0 ) + 1;
				}
			}
		}
		$rules    = MaviBelge_Core_Redirects_Service::rules();
		$active   = 0;
		foreach ( is_array( $rules ) ? $rules : array() as $rule ) {
			if ( is_array( $rule ) && ! empty( $rule['active'] ) ) {
				$active++;
			}
		}
		$audit = null;
		$stat  = MaviBelge_Core_Audit_Log::table_status();
		if ( null !== $stat ) {
			$audit = array( 'rows' => $stat['rows'], 'bytes' => $stat['bytes'], 'chain' => MaviBelge_Core_Audit_Log::verify_chain( 2000 ) );
		}
		$reason = defined( 'WP_DEBUG' ) && WP_DEBUG ? 'WP_DEBUG açık' : ( defined( 'MAVIBELGE_CACHE_DISABLED' ) && MAVIBELGE_CACHE_DISABLED ? 'MAVIBELGE_CACHE_DISABLED' : 'bu oturum düzenleme yetkili' );

		return array(
			'php_version'            => PHP_VERSION,
			'extensions'             => $exts,
			'db_engines'             => $engines,
			'db_charset'             => (string) $wpdb->charset,
			'db_collate'             => (string) $wpdb->collate,
			'permalink_structure'    => (string) get_option( 'permalink_structure', '' ),
			'wp_cron_disabled'       => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'maintenance_scheduled'  => false !== wp_next_scheduled( MaviBelge_Core_Maintenance::HOOK ),
			'disk_free_bytes'        => is_float( $free ) || is_int( $free ) ? (int) $free : null,
			'uploads_writable'       => empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) && wp_is_writable( $uploads['basedir'] ),
			'content_writable'       => wp_is_writable( WP_CONTENT_DIR ),
			'wp_version'             => (string) get_bloginfo( 'version' ),
			'wp_debug'               => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_display'       => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'file_edit_disallowed'   => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'is_https'               => 0 === strpos( (string) home_url(), 'https://' ),
			'environment_type'       => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
			'blog_public'            => '0' !== (string) get_option( 'blog_public', '1' ),
			'forms'                  => $forms,
			'redirects'              => array( 'total' => is_array( $rules ) ? count( $rules ) : 0, 'active' => $active ),
			'audit'                  => $audit,
			'cache'                  => array( 'enabled' => MaviBelge_Core_Cache::enabled(), 'reason' => $reason, 'generation' => MaviBelge_Core_Cache::generation() ),
		);
	}

	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}
		$checks  = MaviBelge_Core_Health_Checks::evaluate( self::collect_env() );
		$summary = MaviBelge_Core_Health_Checks::summary( $checks );
		$labels  = array( 'ok' => 'Tamam', 'warn' => 'Uyarı', 'fail' => 'Hata', 'info' => 'Bilgi' );
		echo '<div class="wrap"><h1>Mavi Belge Sağlık</h1>';
		echo '<p>Salt okunur sistem özeti. Bu ekran hiçbir ayarı değiştirmez ve gizli bilgi göstermez.</p>';
		echo '<p><strong>Özet:</strong> ' . (int) $summary['ok'] . ' tamam, ' . (int) $summary['warn'] . ' uyarı, ' . (int) $summary['fail'] . ' hata, ' . (int) $summary['info'] . ' bilgi.</p>';
		echo '<table class="widefat striped" id="mb-health-table"><thead><tr><th>Kontrol</th><th>Durum</th><th>Ayrıntı</th></tr></thead><tbody>';
		foreach ( $checks as $check ) {
			echo '<tr data-check="' . esc_attr( $check['id'] ) . '" data-status="' . esc_attr( $check['status'] ) . '"><td>' . esc_html( $check['label'] ) . '</td><td>' . esc_html( $labels[ $check['status'] ] ) . '</td><td>' . esc_html( $check['detail'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
