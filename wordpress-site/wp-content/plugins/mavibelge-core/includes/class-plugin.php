<?php
/**
 * Single bootstrap class for the plugin's runtime.
 *
 * Faz 2 update: wires up content types, taxonomies, the meta field
 * schema, roles, the audit log, and the admin UI. No REST endpoints,
 * front-end filters/search, CSV import, or SEO output are added in
 * this phase (see raporlar/karar-kaydi-wordpress-php73.md and görev
 * kartı 02 §3 scope boundaries).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Plugin {

	public function run() {
		load_plugin_textdomain( 'mavibelge-core', false, dirname( plugin_basename( MAVIBELGE_CORE_PATH . 'mavibelge-core.php' ) ) . '/languages' );

		$this->load_files();

		add_action( 'init', array( 'MaviBelge_Core_Content_Types', 'register_all' ) );
		add_action( 'init', array( 'MaviBelge_Core_Taxonomies', 'register_all' ) );
		add_action( 'init', array( 'MaviBelge_Core_Meta_Schema', 'register_all' ), 20 );

		// pre_insert_term: apply_filters( $term, $taxonomy, $args ) — 3 args.
		add_filter( 'pre_insert_term', array( 'MaviBelge_Core_Taxonomies', 'restrict_haber_turu_terms' ), 10, 3 );
		// wp_update_term_data: apply_filters( $data, $term_id, $taxonomy, $args ) — 4 args.
		add_filter( 'wp_update_term_data', array( 'MaviBelge_Core_Taxonomies', 'lock_haber_turu_term_data' ), 10, 4 );

		MaviBelge_Core_Publish_Readiness::init();
		MaviBelge_Core_Visibility_Guard::init();
		MaviBelge_Core_Forms_Service::init();
		MaviBelge_Core_Seo_Service::init();
		MaviBelge_Core_Redirects_Service::init();
		// Faz 10: önbellek geçersiz kılma, sertleştirme, günlük bakım.
		MaviBelge_Core_Cache::init();
		MaviBelge_Core_Hardening::init();
		MaviBelge_Core_Maintenance::init();

		// Idempotent, version-guarded — cheap to check on every admin
		// request, but does not repeat the actual install work (see
		// class-roles.php / class-audit-log.php). Covers upgrades that
		// happen without a fresh activation hook firing.
		add_action( 'admin_init', array( 'MaviBelge_Core_Roles', 'install' ) );
		add_action( 'admin_init', array( 'MaviBelge_Core_Taxonomies', 'ensure_haber_turu_terms' ) );
		add_action( 'admin_init', array( 'MaviBelge_Core_Audit_Log', 'install' ) );

		if ( is_admin() ) {
			MaviBelge_Core_Admin_Notices::init();
			MaviBelge_Core_Meta_Boxes::init();
			MaviBelge_Core_List_Columns::init();
			MaviBelge_Core_List_Filters::init();
			MaviBelge_Core_Content_Admin::init();
			MaviBelge_Core_Forms_Admin::init();
			MaviBelge_Core_Settings::init();
			MaviBelge_Core_Import_Dry_Run_Page::init();
			MaviBelge_Core_Health_Page::init();
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Only admin-only files are loaded here; the always-needed classes
	 * (content types, taxonomies, meta schema, validator, installer,
	 * roles, audit log) are required unconditionally from
	 * mavibelge-core.php itself — see the comment there.
	 */
	private function load_files() {
		if ( ! is_admin() ) {
			return;
		}
		$path = MAVIBELGE_CORE_PATH;
		require_once $path . 'admin/class-admin-notices.php';
		require_once $path . 'admin/class-meta-boxes.php';
		require_once $path . 'admin/class-list-columns.php';
		require_once $path . 'admin/class-list-filters.php';
		require_once $path . 'admin/class-content-admin.php';
		require_once $path . 'admin/class-forms-admin.php';
		require_once $path . 'admin/class-settings.php';
		require_once $path . 'admin/class-import-dry-run-page.php';
		require_once $path . 'admin/class-health-page.php';
	}

	public function enqueue_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'mb_ucret' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script(
			'mavibelge-core-meta-boxes',
			MAVIBELGE_CORE_URL . 'admin/assets/meta-boxes.js',
			array(),
			MAVIBELGE_CORE_VERSION,
			true
		);
	}
}
