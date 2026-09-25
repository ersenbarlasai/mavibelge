<?php
/**
 * Plugin Name: Mavi Belge Core
 * Plugin URI: https://mavibelge.com.tr
 * Description: Mavi Belge kurumsal veri ve is kurallari eklentisi. Faz 2: icerik turleri, taksonomiler, alan sozlesmesi, rol/yetenek modeli ve denetim gunlugu temeli. Faz 2 duzeltme turlari: MYK/TL/yetki/yayin butunlugu, tek dogrulama servisi (Field_Repository), register_meta sanitize callback imza duzeltmesi, kismi fiyat listesi kaydi kapatildi, yetki belgeleri tutarlilastirildi. Faz 5: tema icin tek genel katalog servisi (Catalog_Service) - meslek/sektor arama, aktif tarife donemi kurallarina uyan ucret sorgulari - ve yonetim liste filtreleri. Veri importu, REST ve SEO hala kapsam disi.
 * Version: 0.4.0
 * Requires at least: 6.9
 * Requires PHP: 7.3
 * Author: Mavi Belge
 * Author URI: https://mavibelge.com.tr
 * Text Domain: mavibelge-core
 * License: Proprietary
 *
 * Target WordPress family for this release is documented as the 6.9.x
 * line; no exact patch version is locked yet (see
 * raporlar/karar-kaydi-wordpress-php73.md).
 *
 * This is a shared file (see AGENTS.md §3 / wordpress-agent-mimarisi.md §4).
 * Only the main orchestrator agent merges changes here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAVIBELGE_CORE_VERSION', '0.4.0' );
define( 'MAVIBELGE_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAVIBELGE_CORE_URL', plugin_dir_url( __FILE__ ) );

// Faz 10 — sürüm uyumluluğu: karşılanmıyorsa yönetim uyarısı verilir ve eklentinin HİÇBİR işlevi yüklenmez (fatal yok).
require_once MAVIBELGE_CORE_PATH . 'includes/class-compat.php';
$mavibelge_core_unmet = MaviBelge_Core_Compat::unmet( PHP_VERSION, isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '' );
if ( ! empty( $mavibelge_core_unmet ) ) {
	add_action(
		'admin_notices',
		function () use ( $mavibelge_core_unmet ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p><strong>Mavi Belge Core devre dışı:</strong> ' . esc_html( implode( ' ', $mavibelge_core_unmet ) ) . '</p></div>';
			}
		}
	);
	return;
}

/*
 * These are required unconditionally (not only from within
 * MaviBelge_Core_Plugin::run() on 'plugins_loaded') because plugin
 * activation runs register_activation_hook's callback in the same
 * request that includes this file, before 'plugins_loaded' fires for
 * a plugin that was not already active — so the activator needs these
 * classes available immediately, not later via the normal bootstrap.
 */
require_once MAVIBELGE_CORE_PATH . 'includes/class-content-types.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-taxonomies.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-validator.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-field-repository.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-meta-schema.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-installer.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-publish-readiness.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-catalog-query.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-content-query.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-content-admin-rules.php';
require_once MAVIBELGE_CORE_PATH . 'public/class-catalog-service.php';
require_once MAVIBELGE_CORE_PATH . 'public/class-content-service.php';
// Faz 8 — güvenli form altyapısı (şema -> doğrulayıcı -> yapılandırma/kapı -> güvenlik -> ileti -> e-posta adaptörü -> hizmet).
require_once MAVIBELGE_CORE_PATH . 'includes/forms/class-forms-schema.php';
require_once MAVIBELGE_CORE_PATH . 'includes/forms/class-forms-validator.php';
require_once MAVIBELGE_CORE_PATH . 'includes/forms/class-forms-config.php';
require_once MAVIBELGE_CORE_PATH . 'includes/forms/class-forms-security.php';
require_once MAVIBELGE_CORE_PATH . 'includes/forms/class-forms-mail-builder.php';
require_once MAVIBELGE_CORE_PATH . 'includes/forms/class-forms-mailer.php';
require_once MAVIBELGE_CORE_PATH . 'public/class-forms-service.php';
// Faz 9 — SEO/AIO (title/description/canonical/robots/OG/schema/sitemap) ve eski URL yönlendirme kayıt sistemi.
require_once MAVIBELGE_CORE_PATH . 'includes/seo/class-seo-meta.php';
require_once MAVIBELGE_CORE_PATH . 'includes/seo/class-seo-schema.php';
require_once MAVIBELGE_CORE_PATH . 'includes/seo/class-seo-robots.php';
require_once MAVIBELGE_CORE_PATH . 'public/class-seo-service.php';
require_once MAVIBELGE_CORE_PATH . 'includes/redirects/class-redirects-rules.php';
require_once MAVIBELGE_CORE_PATH . 'includes/redirects/class-redirects-service.php';
require_once MAVIBELGE_CORE_PATH . 'public/class-visibility-guard.php';
require_once MAVIBELGE_CORE_PATH . 'roles/class-roles.php';
require_once MAVIBELGE_CORE_PATH . 'audit/class-audit-chain.php';
require_once MAVIBELGE_CORE_PATH . 'audit/class-audit-log.php';
// Faz 10 — performans/güvenlik/bakım: tek oran sınırı, tek önbellek, sertleştirme, bakım cron'u.
require_once MAVIBELGE_CORE_PATH . 'includes/class-rate-limit.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-cache.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-hardening.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-maintenance.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-health-checks.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-uninstall-scope.php';

/*
 * Faz 6B1/6B2 — içe aktarım karar motoru + salt okunur WordPress
 * entegrasyonu. Yükleme sırası BAĞLAYICIDIR: arayüz -> saf Faz 6B1 sınıfları
 * (hiçbiri WordPress fonksiyonu çağırmaz) -> loader/repository/service
 * (repository ve service GERÇEK WordPress okuma fonksiyonları çağırır).
 * Bu dosyaların HİÇBİRİ (CLI/admin hariç) koşullu değildir — dry-run
 * planlayıcısı ve saf sınıflar `tests/bootstrap.php`'de de bağımsız
 * yüklenir. `class-import-cli-command.php` ve
 * `admin/class-import-dry-run-page.php` bilerek burada DEĞİL, yalnız
 * ilgili bağlamda (WP-CLI / admin) aşağıda koşullu olarak yüklenir.
 */
require_once MAVIBELGE_CORE_PATH . 'includes/import/interface-import-target-repository.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-hash.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-managed-fields.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-decision.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-record-validator.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-dry-run-planner.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-manifest-loader.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-wordpress-target-repository.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-dry-run-service.php';
// Faz 6B3 Önkoşul — SAF, YAZMAYAN yazma güvenliği sözleşmeleri (atomik yük
// doğrulama + apply uygunluk/TOCTOU/rollback şekli).
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-write-payload.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-apply-eligibility.php';
// Faz 6B3 — apply/batch/audit/rollback. Sıra BAĞLAYICIDIR: dar arayüzler ->
// saf sınıflar -> WordPress'e dokunmayan servisler -> WordPress uygulamaları
// (yazma adapterı, $wpdb transaction, run deposu, audit hedefi). Yüklemek
// hiçbir şey YAZMAZ; yazma yalnız açık `wp mavibelge import catalog --apply`
// yolunda, MAVIBELGE_IMPORT_APPLY_ENABLED === true + yetki + plan onayıyla olur.
require_once MAVIBELGE_CORE_PATH . 'includes/import/interface-import-target-writer.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/interface-import-transaction.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/interface-import-run-store.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/interface-import-audit-sink.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-run-state.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-apply-plan.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-rollback-codec.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-audit-context.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-run-finalizer.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-apply-service.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-rollback-service.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-wordpress-target-writer.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-wpdb-transaction.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-wpdb-run-store.php';
require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-wp-audit-sink.php';

require_once MAVIBELGE_CORE_PATH . 'includes/class-plugin.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-activator.php';
require_once MAVIBELGE_CORE_PATH . 'includes/class-deactivator.php';

register_activation_hook( __FILE__, array( 'MaviBelge_Core_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MaviBelge_Core_Deactivator', 'deactivate' ) );

function mavibelge_core_run() {
	$plugin = new MaviBelge_Core_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'mavibelge_core_run' );

// Faz 6B2/6B3 — yalnız GERÇEK bir WP-CLI ortamında yüklenir; WP-CLI yoksa
// bu dosya hiç require edilmez, eklenti fatal VERMEZ. Varsayılan salt okunur
// dry-run; `--apply` varsayılan KAPALI (MAVIBELGE_IMPORT_APPLY_ENABLED +
// yetki + --confirm=<plan_digest>); `--write`/`--commit`/`--force` YOKTUR
// (bkz. includes/import/class-import-cli-command.php).
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MAVIBELGE_CORE_PATH . 'includes/import/class-import-cli-command.php';
	WP_CLI::add_command( 'mavibelge import', 'MaviBelge_Core_Import_CLI_Command' );
	// Faz 9 — eski URL yönlendirme kayıt sistemi (dry-run varsayılan; apply MAVIBELGE_REDIRECTS_APPLY_ENABLED ister).
	require_once MAVIBELGE_CORE_PATH . 'includes/redirects/class-redirects-cli-command.php';
	WP_CLI::add_command( 'mavibelge redirects', 'MaviBelge_Core_Redirects_CLI_Command' );
}
