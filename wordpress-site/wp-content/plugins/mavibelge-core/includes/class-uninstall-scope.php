<?php
/**
 * Faz 10 — güvenli uninstall kapsamı (SAF; WordPress fonksiyonu çağırmaz). KAPALI allowlist: yalnız burada adı geçen
 * seçenekler/tablolar/cron kancaları, yalnız `mavibelge_core_delete_data_on_uninstall` seçeneği AÇIKÇA `true` ise silinir.
 * İçerik (yazılar, terimler, medya), roller/yetkiler ve `mb_active_tariff_period` (kurum tarife verisi) ASLA silinmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Uninstall_Scope {

	const OPT_IN_OPTION = 'mavibelge_core_delete_data_on_uninstall';

	const OPTIONS = array(
		'mavibelge_core_delete_data_on_uninstall',
		'mavibelge_core_audit_table_version',
		'mavibelge_core_import_tables_version',
		'mavibelge_core_sector_image_map',
		'mavibelge_core_redirects',
		'mavibelge_core_forms_config',
		'mavibelge_core_seo',
		'mavibelge_core_cache_gen',
		'mavibelge_core_haber_turu_seeded',
	);

	/** Tablo adları ($wpdb->prefix ile birleştirilir). */
	const TABLES = array( 'mb_audit_log', 'mb_import_runs', 'mb_import_run_items', 'mb_import_run_plan_items' );

	const CRON_HOOKS = array( 'mavibelge_core_daily_maintenance' );

	/**
	 * Silme yalnız açık onayla: boolean `true` veya `update_option( ..., true )`'nun veritabanında bıraktığı TAM '1' dizgesi.
	 * 'true', 'yes', 'on', 1 (int), boş, false, null ve diğer her değer KABUL EDİLMEZ.
	 */
	public static function should_delete( $optInValue ) {
		return true === $optInValue || '1' === $optInValue;
	}

	/** @return string[] tam tablo adları */
	public static function tables( $prefix ) {
		$out = array();
		foreach ( self::TABLES as $table ) {
			$out[] = $prefix . $table;
		}
		return $out;
	}
}
