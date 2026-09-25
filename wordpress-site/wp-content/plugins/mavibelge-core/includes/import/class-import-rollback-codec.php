<?php
/**
 * Faz 6B3 — rollback kaydının kalıcı JSON temsili (SAF).
 *
 * Tek doğrulayıcı `MaviBelge_Core_Import_Apply_Eligibility::validate_rollback_record()`
 * hem yazmadan ÖNCE (encode) hem okumadan SONRA (decode) çalışır:
 * bozuk/kesilmiş JSON, eksik/fazla anahtar, alanlarla uyuşmayan hash veya
 * yanlış `changed_fields` taşıyan bir checkpoint kaydı hiçbir işlemde
 * kullanılmaz (fail-closed). JSON sütun tipi kullanılmaz; LONGTEXT +
 * uygulama düzeyi JSON.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Rollback_Codec {

	/**
	 * @param mixed $record
	 * @return string|null
	 */
	public static function encode( $record ) {
		if ( ! MaviBelge_Core_Import_Apply_Eligibility::validate_rollback_record( $record ) ) {
			return null;
		}
		$json = json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || JSON_ERROR_NONE !== json_last_error() ) {
			return null;
		}
		// Kodlanan kaydın AYNEN geri çözüldüğü kanıtlanmadan saklanmaz.
		return self::decode( $json ) === $record ? $json : null;
	}

	/**
	 * @param mixed $json
	 * @return array|null
	 */
	public static function decode( $json ) {
		if ( ! is_string( $json ) || '' === $json ) {
			return null;
		}
		$record = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $record ) ) {
			return null;
		}
		return MaviBelge_Core_Import_Apply_Eligibility::validate_rollback_record( $record ) ? $record : null;
	}
}
