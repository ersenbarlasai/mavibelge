<?php
/**
 * YALNIZ yerel, izole test ortamı — `wp --require=/opt/mb-runtime/fixture-env.php ...`
 *
 * WP-CLI sürecini klonlanmış, silinebilir fixture tablolarına (`mbfx_`
 * öneki) yönlendirir; gerçek `wp_` tablolarına bu süreçte hiç dokunulmaz.
 * Dosya/eklenti kurmaz; WordPress yüklenmeden önce çalışır:
 *   - after_wp_config_load: $table_prefix = 'mbfx_' (wp-settings $wpdb'yi bununla kurar)
 *   - MB_FX_MANIFEST ortam değişkeni: sahte fixture manifest dizini
 *     (varsayılan /tmp/mbfx-manifest; 'none' => gerçek manifest dizini)
 *   - MB_FX_APPLY=1: MAVIBELGE_IMPORT_APPLY_ENABLED === true (yalnız bu süreç)
 * Üretime kopyalanmaz.
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

WP_CLI::add_hook(
	'after_wp_config_load',
	function () {
		$GLOBALS['table_prefix'] = 'mbfx_';
	}
);

$mbFxManifest = getenv( 'MB_FX_MANIFEST' );
if ( 'none' !== $mbFxManifest ) {
	define( 'MAVIBELGE_IMPORT_MANIFEST_DIR', is_string( $mbFxManifest ) && '' !== $mbFxManifest ? $mbFxManifest : '/tmp/mbfx-manifest' );
}
if ( '1' === getenv( 'MB_FX_APPLY' ) ) {
	define( 'MAVIBELGE_IMPORT_APPLY_ENABLED', true );
}
