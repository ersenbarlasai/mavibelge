<?php
/**
 * Faz 6B3 — import audit context'i için KAPALI izin listesi (SAF).
 *
 * Audit yalnız şunları taşıyabilir: run ID, source key, tür, karar, hedef
 * ID, eski/yeni hash, değişen alan ADLARI, sabit hata kodu, batch/checkpoint
 * numarası ve bu alanlardan oluşan batch item listesi. Alan İÇERİĞİ,
 * parola, nonce, çerez, kişisel veri veya dosya içeriği yapısal olarak
 * taşınamaz: izin listesi dışındaki her anahtar veya biçimsiz her değer
 * context'in TAMAMINI reddeder (null) — kısmen temizlenmiş context yazılmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Audit_Context {

	const RUN_KEYS  = array( 'run_id', 'batch_no', 'checkpoint', 'error_code', 'source_key', 'type', 'decision', 'target_id', 'old_hash', 'new_hash', 'changed_fields', 'items' );
	const ITEM_KEYS = array( 'source_key', 'type', 'decision', 'target_id', 'old_hash', 'new_hash', 'changed_fields' );

	/**
	 * @param mixed $context
	 * @return array|null
	 */
	public static function build( $context ) {
		if ( ! is_array( $context ) ) {
			return null;
		}
		foreach ( $context as $key => $value ) {
			if ( ! in_array( $key, self::RUN_KEYS, true ) ) {
				return null;
			}
			if ( 'items' === $key ) {
				if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) > MaviBelge_Core_Import_Apply_Plan::MAX_BATCH_SIZE ) {
					return null;
				}
				foreach ( $value as $item ) {
					if ( ! is_array( $item ) ) {
						return null;
					}
					foreach ( $item as $itemKey => $itemValue ) {
						if ( ! in_array( $itemKey, self::ITEM_KEYS, true ) || ! self::valid_value( $itemKey, $itemValue ) ) {
							return null;
						}
					}
				}
				continue;
			}
			if ( ! self::valid_value( $key, $value ) ) {
				return null;
			}
		}
		return $context;
	}

	private static function valid_value( $key, $value ) {
		switch ( $key ) {
			case 'run_id':
				return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{32}\z/', $value );
			case 'batch_no':
			case 'checkpoint':
				return is_int( $value ) && $value >= 0;
			case 'target_id':
				return is_int( $value ) && $value > 0;
			case 'error_code':
				return is_string( $value ) && 1 === preg_match( '/^[a-z][a-z0-9_]{0,63}\z/', $value );
			case 'type':
				return in_array( $value, MaviBelge_Core_Import_Managed_Fields::TYPES, true );
			case 'decision':
				return in_array( $value, array( 'create', 'update' ), true );
			case 'old_hash':
			case 'new_hash':
				return null === $value || MaviBelge_Core_Import_Apply_Plan::is_digest( $value );
			case 'source_key':
				if ( ! is_string( $value ) || false === strpos( $value, ':' ) ) {
					return false;
				}
				$type = substr( $value, 0, strpos( $value, ':' ) );
				return in_array( $type, MaviBelge_Core_Import_Managed_Fields::TYPES, true )
					&& MaviBelge_Core_Validator::IMPORT_KEY_VALID === MaviBelge_Core_Validator::classify_import_source_key( $value, $type );
			case 'changed_fields':
				if ( ! is_array( $value ) || array_values( $value ) !== $value ) {
					return false;
				}
				$allowed = array_merge(
					MaviBelge_Core_Import_Managed_Fields::SECTOR_FIELDS,
					MaviBelge_Core_Import_Managed_Fields::QUALIFICATION_FIELDS,
					MaviBelge_Core_Import_Managed_Fields::FEE_FIELDS,
					MaviBelge_Core_Import_Managed_Fields::NEWS_FIELDS,
					MaviBelge_Core_Import_Managed_Fields::REFERENCE_FIELDS
				);
				foreach ( $value as $field ) {
					if ( ! is_string( $field ) || ! in_array( $field, $allowed, true ) ) {
						return false;
					}
				}
				return true;
			default:
				return false;
		}
	}
}
