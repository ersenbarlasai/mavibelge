<?php
/**
 * Faz 6B4 — kalıcı plan snapshot'ının KAPALI şekli (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Admin apply çok isteklidir; run başlangıcında onaylanan planın yazma tanımları deterministik sırada
 * `{prefix}mb_import_run_plan_items` tablosuna kaydedilir ve her istek bu kayıttan devam eder. Snapshot İÇERİK
 * DEĞERİ KOPYALAMAZ: yalnız source key, tür, karar, hedef ID, sıra, beklenen hash'ler ve doğal anahtar sonucu
 * taşınır. Her batch kaydı güncel manifestten YENİDEN okur, projeksiyonu yeniden üretir ve buradaki
 * `expected_incoming_hash` ile eşleştirir (eşleşmezse yazılmaz).
 *
 * Item şekli (kapalı): seq (1..n), source_key, type, decision (create|update), target_id (create: 0; update: >0),
 * expected_incoming_hash (64-hex), expected_current_hash / expected_last_applied_hash (create: null; update: 64-hex),
 * natural_key_check (create: 'none'; update: null).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Plan_Snapshot {

	const KEYS = array( 'seq', 'source_key', 'type', 'decision', 'target_id', 'expected_incoming_hash', 'expected_current_hash', 'expected_last_applied_hash', 'natural_key_check' );

	const WRITE_KEYS = array( 'source_key', 'type', 'decision', 'target_id', 'expected_incoming_hash', 'expected_current_hash', 'expected_last_applied_hash' );

	/**
	 * `Apply_Eligibility::evaluate_plan()['writes']` (apply sırasına dizilmiş) => snapshot item'ları.
	 *
	 * @param mixed $writes
	 * @return array[]|null Geçersiz/boş girdide null.
	 */
	public static function from_writes( $writes ) {
		if ( ! is_array( $writes ) || array() === $writes || array_values( $writes ) !== $writes ) {
			return null;
		}
		$items = array();
		foreach ( $writes as $index => $write ) {
			if ( ! is_array( $write ) ) {
				return null;
			}
			foreach ( self::WRITE_KEYS as $key ) {
				if ( ! array_key_exists( $key, $write ) ) {
					return null;
				}
			}
			$create  = isset( $write['decision'] ) && 'create' === $write['decision'];
			$items[] = array(
				'seq'                        => $index + 1,
				'source_key'                 => $write['source_key'],
				'type'                       => $write['type'],
				'decision'                   => $write['decision'],
				'target_id'                  => $create ? 0 : $write['target_id'],
				'expected_incoming_hash'     => $write['expected_incoming_hash'],
				'expected_current_hash'      => $write['expected_current_hash'],
				'expected_last_applied_hash' => $write['expected_last_applied_hash'],
				'natural_key_check'          => $create ? 'none' : null,
			);
		}
		return array() === self::validate_items( $items ) ? $items : null;
	}

	/** Snapshot satırı => apply yazma tanımı (`evaluate_plan()['writes']` öğesi biçimi). */
	public static function to_write( array $item ) {
		return array(
			'source_key'                 => $item['source_key'],
			'type'                       => $item['type'],
			'decision'                   => $item['decision'],
			'target_id'                  => 'create' === $item['decision'] ? null : $item['target_id'],
			'expected_incoming_hash'     => $item['expected_incoming_hash'],
			'expected_current_hash'      => $item['expected_current_hash'],
			'expected_last_applied_hash' => $item['expected_last_applied_hash'],
		);
	}

	/**
	 * @param mixed $items
	 * @param int   $seqOffset Sayfalanmış okumada ilk item'ın beklenen seq'i offset+1'dir (tam liste için 0).
	 * @return string[] Hata listesi; boşsa geçerli.
	 */
	public static function validate_items( $items, $seqOffset = 0 ) {
		if ( ! is_array( $items ) || array() === $items || array_values( $items ) !== $items ) {
			return array( 'items_not_list' );
		}
		$errors = array();
		$seen   = array();
		$keys   = self::KEYS;
		sort( $keys, SORT_STRING );
		foreach ( $items as $index => $item ) {
			$label = '#' . ( $index + 1 );
			if ( ! is_array( $item ) ) {
				$errors[] = $label . ':not_array';
				continue;
			}
			$given = array_keys( $item );
			sort( $given, SORT_STRING );
			if ( $given !== $keys ) {
				$errors[] = $label . ':keys';
				continue;
			}
			if ( $item['seq'] !== $index + 1 + $seqOffset ) {
				$errors[] = $label . ':seq';
			}
			$type = $item['type'];
			if ( ! is_string( $type ) || ! in_array( $type, MaviBelge_Core_Import_Managed_Fields::TYPES, true ) ) {
				$errors[] = $label . ':type';
				continue;
			}
			if ( ! is_string( $item['source_key'] ) || MaviBelge_Core_Validator::IMPORT_KEY_VALID !== MaviBelge_Core_Validator::classify_import_source_key( $item['source_key'], $type ) ) {
				$errors[] = $label . ':source_key';
				continue;
			}
			if ( isset( $seen[ $item['source_key'] ] ) ) {
				$errors[] = $label . ':source_key_duplicate';
			}
			$seen[ $item['source_key'] ] = true;
			if ( ! self::is_hash( $item['expected_incoming_hash'] ) ) {
				$errors[] = $label . ':incoming_hash';
			}
			if ( 'create' === $item['decision'] ) {
				if ( 0 !== $item['target_id'] || null !== $item['expected_current_hash'] || null !== $item['expected_last_applied_hash'] || 'none' !== $item['natural_key_check'] ) {
					$errors[] = $label . ':create_shape';
				}
			} elseif ( 'update' === $item['decision'] ) {
				if ( ! is_int( $item['target_id'] ) || $item['target_id'] <= 0 || ! self::is_hash( $item['expected_current_hash'] ) || ! self::is_hash( $item['expected_last_applied_hash'] ) || null !== $item['natural_key_check'] ) {
					$errors[] = $label . ':update_shape';
				}
			} else {
				$errors[] = $label . ':decision';
			}
		}
		return $errors;
	}

	private static function is_hash( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', $value );
	}
}
