<?php
/**
 * Faz 6B3 — `MaviBelge_Core_Import_Transaction`'ın $wpdb uygulaması.
 *
 * Yalnız `START TRANSACTION` / `COMMIT` / `ROLLBACK` deyimlerini çalıştırır
 * ve SONUÇLARINI kontrol eder (içerik/meta yazmaz). `preflight()` apply/
 * rollback'in yazabileceği her tablonun transactional (InnoDB) motor
 * kullandığını SALT OKUNUR doğrular; biri bile değilse batch atomikliği
 * iddia edilemez ve işlem hiç başlatılmaz. Ayrıca bağlantı karakter seti ve
 * bu tabloların collation'ı utf8mb4 değilse (Türkçe içerik bozulma riski,
 * görev kartı 04 §8) işlem başlatılmaz.
 *
 * ROLLBACK sonrası nesne önbelleği temizlenir (geri alınan satırlar önbellekte
 * kalmasın). Kalıcı bir nesne önbelleği veya veritabanı DIŞI yan etkiler
 * (dosya, e-posta, dış HTTP) transaction ile geri ALINAMAZ; bu faz bunları
 * üretmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Wpdb_Transaction implements MaviBelge_Core_Import_Transaction {

	/** @var bool */
	private $open = false;

	public function preflight() {
		global $wpdb;
		return $this->preflight_tables(
			array(
				$wpdb->posts, $wpdb->postmeta, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->termmeta, $wpdb->options,
				MaviBelge_Core_Audit_Log::table_name(),
				MaviBelge_Core_Import_Wpdb_Run_Store::runs_table(),
				MaviBelge_Core_Import_Wpdb_Run_Store::items_table(),
			)
		);
	}

	/**
	 * Faz 6B4 — yalnız VERİLEN tabloların transactional (InnoDB) ve utf8mb4 olduğunu ve bağlantının utf8mb4 olduğunu
	 * SALT OKUNUR doğrular (görsel eşleme kaydı yalnız option + audit tablolarına dokunur; run tabloları gerekmez).
	 *
	 * @param string[] $tables Tam tablo adları.
	 * @return array{ok: bool, error: string|null}
	 */
	public function preflight_tables( array $tables ) {
		global $wpdb;
		// Görev kartı 04 §8: veritabanı utf8mb4 doğrulanmadan Türkçe içerik yazılmaz.
		if ( 'utf8mb4' !== $wpdb->charset ) {
			return array( 'ok' => false, 'error' => 'non_utf8mb4_connection' );
		}
		$placeholders = implode( ', ', array_fill( 0, count( $tables ), '%s' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare( "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})", $tables ),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			return array( 'ok' => false, 'error' => 'engine_query_failed' );
		}
		$engines    = array();
		$collations = array();
		foreach ( $rows as $row ) {
			$engines[ $row['TABLE_NAME'] ]    = is_string( $row['ENGINE'] ) ? strtolower( $row['ENGINE'] ) : '';
			$collations[ $row['TABLE_NAME'] ] = is_string( $row['TABLE_COLLATION'] ) ? strtolower( $row['TABLE_COLLATION'] ) : '';
		}
		foreach ( $tables as $table ) {
			if ( ! isset( $engines[ $table ] ) ) {
				return array( 'ok' => false, 'error' => 'table_missing' );
			}
			if ( 'innodb' !== $engines[ $table ] ) {
				return array( 'ok' => false, 'error' => 'non_transactional_table' );
			}
			if ( 0 !== strpos( $collations[ $table ], 'utf8mb4' ) ) {
				return array( 'ok' => false, 'error' => 'non_utf8mb4_table' );
			}
		}
		return array( 'ok' => true, 'error' => null );
	}

	public function begin() {
		if ( $this->open ) {
			return false;
		}
		$this->open = self::run( 'START TRANSACTION' );
		return $this->open;
	}

	public function commit() {
		if ( ! $this->open ) {
			return false;
		}
		$ok         = self::run( 'COMMIT' );
		$this->open = false;
		return $ok;
	}

	public function rollback() {
		// COMMIT başarısız olduktan sonra da çağrılabilir; ROLLBACK zararsızdır.
		$ok         = self::run( 'ROLLBACK' );
		$this->open = false;
		wp_cache_flush();
		return $ok;
	}

	private static function run( $statement ) {
		global $wpdb;
		$wpdb->last_error = '';
		$result           = $wpdb->query( $statement );
		return false !== $result && '' === $wpdb->last_error;
	}
}
