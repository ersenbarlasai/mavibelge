<?php
/**
 * Faz 10 — audit günlüğü bütünlük zinciri için SAF mantık (WordPress fonksiyonu çağırmaz).
 *
 * Her satırın `row_hash` değeri, ÖNCEKİ satırın hash'iyle (`prev_hash`) ve satırın kendi alanlarıyla zincirlenir:
 * sha256( uzunluk-önekli alanlar: prev | event | object_type | object_id | user_id | context | created_at ).
 * Alanlar uzunluk önekiyle birleştirilir (`<uzunluk>:<değer>`); böylece `|` içeren bağlam belirsizlik yaratamaz.
 *
 * İKİ ZİNCİR vardır: `form` (form_* olayları; 90 gün saklama) ve `core` (diğer bütün olaylar; 365 gün). Saklama silmesi
 * her zincirin yalnız EN ESKİ ön ekini siler; bu yüzden silme sonrası ilk kalan satırın `prev_hash`'i "çapa" kabul edilir.
 *
 * SINIRLAR (belgelenmiştir): zincir DEĞİŞTİRME ve ARADAN silmeyi tespit eder; zincirin SONUNDAKİ satırların silinmesi ve
 * veritabanı yöneticisinin tüm zinciri baştan hesaplaması bu mekanizmayla tespit EDİLEMEZ (dış imza/yedek gerekir).
 * Eski sürüm (v1) satırları `row_hash=''` taşır ve "eski sürüm" sayılır; yalnız v2 satırları denetlenir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Audit_Chain {

	const GROUP_FORM = 'form';
	const GROUP_CORE = 'core';

	/** Zincir grubu: `form_` ile başlayan olaylar `form`, diğerleri `core`. */
	public static function group( $eventType ) {
		return is_string( $eventType ) && 0 === strpos( $eventType, 'form_' ) ? self::GROUP_FORM : self::GROUP_CORE;
	}

	/**
	 * @param string $prev        Önceki satırın row_hash'i ('' = zincir başı)
	 * @param array  $row         event_type, object_type, object_id, user_id, context, created_at_gmt
	 * @return string 64 karakter hex
	 */
	public static function compute( $prev, array $row ) {
		$fields = array(
			(string) $prev,
			(string) ( isset( $row['event_type'] ) ? $row['event_type'] : '' ),
			(string) ( isset( $row['object_type'] ) ? $row['object_type'] : '' ),
			(string) (int) ( isset( $row['object_id'] ) ? $row['object_id'] : 0 ),
			(string) (int) ( isset( $row['user_id'] ) ? $row['user_id'] : 0 ),
			(string) ( isset( $row['context'] ) ? $row['context'] : '' ),
			(string) ( isset( $row['created_at_gmt'] ) ? $row['created_at_gmt'] : '' ),
		);
		$parts = array();
		foreach ( $fields as $field ) {
			$parts[] = strlen( $field ) . ':' . $field;
		}
		return hash( 'sha256', implode( '|', $parts ) );
	}

	/**
	 * Satır listesini (id sırasına göre, en eskiden en yeniye) doğrular.
	 *
	 * @param array[] $rows id, event_type, object_type, object_id, user_id, context, created_at_gmt, prev_hash, row_hash
	 * @return array{ok: bool, checked: int, legacy: int, first_bad_id: int|null, reason: string|null}
	 */
	public static function verify( array $rows ) {
		$last     = array(); // grup => önceki satırın row_hash'i
		$checked  = 0;
		$legacy   = 0;
		$fail     = function ( $id, $reason ) use ( &$checked, &$legacy ) {
			return array( 'ok' => false, 'checked' => $checked, 'legacy' => $legacy, 'first_bad_id' => (int) $id, 'reason' => $reason );
		};
		foreach ( $rows as $row ) {
			$hash = isset( $row['row_hash'] ) ? (string) $row['row_hash'] : '';
			if ( '' === $hash ) {
				$legacy++; // v1 satırı: zincir dışı (yükseltme öncesi)
				continue;
			}
			$group = self::group( isset( $row['event_type'] ) ? $row['event_type'] : '' );
			$prev  = isset( $row['prev_hash'] ) ? (string) $row['prev_hash'] : '';
			if ( 1 !== preg_match( '/^[0-9a-f]{64}$/', $hash ) || ( '' !== $prev && 1 !== preg_match( '/^[0-9a-f]{64}$/', $prev ) ) ) {
				return $fail( isset( $row['id'] ) ? $row['id'] : 0, 'malformed_hash' );
			}
			if ( isset( $last[ $group ] ) && $prev !== $last[ $group ] ) {
				return $fail( isset( $row['id'] ) ? $row['id'] : 0, 'chain_break' ); // aradan silinmiş/araya eklenmiş satır
			}
			// Grubun ilk satırı: prev_hash ÇAPA kabul edilir (saklama silmesi veya denetim penceresi başı).
			if ( ! hash_equals( self::compute( $prev, $row ), $hash ) ) {
				return $fail( isset( $row['id'] ) ? $row['id'] : 0, 'row_modified' );
			}
			$last[ $group ] = $hash;
			$checked++;
		}
		return array( 'ok' => true, 'checked' => $checked, 'legacy' => $legacy, 'first_bad_id' => null, 'reason' => null );
	}

	/** Saklama süreleri (gün): form olayları 90, diğerleri 365. */
	public static function retention_days( $group ) {
		return self::GROUP_FORM === $group ? 90 : 365;
	}
}
