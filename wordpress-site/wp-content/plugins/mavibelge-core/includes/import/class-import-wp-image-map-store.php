<?php
/**
 * Faz 6B4 son kabul düzeltmesi — `MaviBelge_Core_Import_Image_Map_Store`'un WordPress uygulaması.
 *
 * Transaction: mevcut `MaviBelge_Core_Import_Wpdb_Transaction` (sonuçları kontrol edilen START TRANSACTION/COMMIT/ROLLBACK).
 * Altyapı doğrulaması yalnız option + audit tabloları içindir (`preflight_tables()`); run tabloları gerekmez ve KURULMAZ.
 * Yazma: `add_option`/`update_option` (autoload `no`), transaction içinde. Audit: `MaviBelge_Core_Audit_Log::record()`
 * dönüş değeri kontrol edilir. Hata metni/SQL dışarı verilmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Wp_Image_Map_Store implements MaviBelge_Core_Import_Image_Map_Store {

	/** @var MaviBelge_Core_Import_Wpdb_Transaction */
	private $tx;

	public function __construct( MaviBelge_Core_Import_Wpdb_Transaction $tx = null ) {
		$this->tx = null === $tx ? new MaviBelge_Core_Import_Wpdb_Transaction() : $tx;
	}

	/** Kilit bekleme süresi (sn): kısa ve sınırlı; aşılırsa kayıt `locked` ile reddedilir. */
	const LOCK_TIMEOUT = 10;

	private static function lock_name() {
		global $wpdb;
		return 'mavibelge_image_map_' . substr( md5( $wpdb->prefix ), 0, 12 );
	}

	public function lock() {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::lock_name(), self::LOCK_TIMEOUT ) );
	}

	public function unlock() {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name() ) );
	}

	public function ready() {
		global $wpdb;
		if ( ! MaviBelge_Core_Audit_Log::table_exists() ) {
			return false;
		}
		$preflight = $this->tx->preflight_tables( array( $wpdb->options, MaviBelge_Core_Audit_Log::table_name() ) );
		return is_array( $preflight ) && true === $preflight['ok'];
	}

	public function read() {
		$this->flush(); // kilit altında başka süreçten yazılmış son değer okunur (kalıcı nesne önbelleği bayat olamaz)
		$raw = get_option( MaviBelge_Core_Import_Sector_Image_Map::OPTION, null );
		return false === $raw ? null : $raw;
	}

	public function inspect( $id ) {
		return MaviBelge_Core_Import_Sector_Image_Map::inspect_attachment( $id );
	}

	public function begin() {
		return true === $this->tx->begin();
	}

	public function write( array $stored, $exists ) {
		$option = MaviBelge_Core_Import_Sector_Image_Map::OPTION;
		if ( ! $exists ) {
			return true === add_option( $option, $stored, '', 'no' );
		}
		return true === update_option( $option, $stored, 'no' );
	}

	public function audit( array $context ) {
		return true === MaviBelge_Core_Audit_Log::record( MaviBelge_Core_Audit_Log::EVENT_IMPORT_IMAGE_MAP_CHANGED, 'mb_import_image_map', 0, $context );
	}

	public function commit() {
		return true === $this->tx->commit();
	}

	public function rollback() {
		return true === $this->tx->rollback();
	}

	public function flush() {
		$option = MaviBelge_Core_Import_Sector_Image_Map::OPTION;
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
