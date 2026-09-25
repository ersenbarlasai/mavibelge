<?php
/**
 * Uninstall handler.
 *
 * VARSAYILAN: hiçbir veri silinmez (yazılar, terimler, medya, roller/yetkiler, seçenekler, tablolar). Yalnız
 * `mavibelge_core_delete_data_on_uninstall` seçeneği AÇIKÇA boolean `true` ise ve yalnız o durumda, eklentinin KENDİ
 * kapalı allowlist'i (bkz. includes/class-uninstall-scope.php) silinir: audit/import tabloları, eklenti seçenekleri, cron
 * kancası. İçerik ASLA silinmez. Çok siteli kurulumda yalnız geçerli site işlenir.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-uninstall-scope.php';

if ( MaviBelge_Core_Uninstall_Scope::should_delete( get_option( MaviBelge_Core_Uninstall_Scope::OPT_IN_OPTION, false ) ) ) {
	global $wpdb;
	foreach ( MaviBelge_Core_Uninstall_Scope::CRON_HOOKS as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	foreach ( MaviBelge_Core_Uninstall_Scope::tables( $wpdb->prefix ) as $table ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tablo adı sabit allowlist + önek.
	}
	foreach ( MaviBelge_Core_Uninstall_Scope::OPTIONS as $option ) {
		delete_option( $option );
	}
}
