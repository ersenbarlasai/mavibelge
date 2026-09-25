<?php
/**
 * Plugin Name: MB Runtime Test Isolation (YALNIZ yerel test ortamı)
 * Description: Faz 6B2 runtime doğrulamasında WordPress çekirdeğinin
 * wordpress.org güncelleme denetimlerini kapatır. Test ortamı dış HTTP'ye
 * kapalı (WP_HTTP_BLOCK_EXTERNAL) olduğundan bu denetimler debug log'a
 * uyarı yazar ve update_* site transient'lerini günceller; bu çekirdek
 * gürültüsü dry-run sıfır-yazma karşılaştırmasını bulandırmasın diye
 * kapatılır. mavibelge-core davranışını DEĞİŞTİRMEZ. Üretime kopyalanmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

remove_action( 'admin_init', '_maybe_update_core' );
remove_action( 'admin_init', '_maybe_update_plugins' );
remove_action( 'admin_init', '_maybe_update_themes' );
remove_action( 'wp_version_check', 'wp_version_check' );
remove_action( 'load-plugins.php', 'wp_update_plugins' );
remove_action( 'load-update.php', 'wp_update_plugins' );
remove_action( 'load-update-core.php', 'wp_update_plugins' );
remove_action( 'load-themes.php', 'wp_update_themes' );
remove_action( 'load-update.php', 'wp_update_themes' );
remove_action( 'load-update-core.php', 'wp_update_themes' );
add_filter( 'pre_site_transient_update_core', '__return_null' );
add_filter( 'pre_site_transient_update_plugins', '__return_null' );
add_filter( 'pre_site_transient_update_themes', '__return_null' );
