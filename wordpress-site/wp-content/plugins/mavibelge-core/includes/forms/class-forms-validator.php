<?php
/**
 * Faz 8 — TEK merkezi form doğrulayıcısı (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Yalnız `MaviBelge_Core_Forms_Schema::allowed_field_names()` içindeki alanlar
 * okunur (kapalı allowlist); gönderilen başka her anahtar yok sayılır. Hata
 * mesajları GENEL Türkçe metinlerdir ve girilen değeri ASLA yansıtmaz.
 * Başlık (header) enjeksiyonuna karşı tek satırlık alanlardan CR/LF/NUL
 * kaldırılır; e-posta alanı ayrıca CR/LF içeriyorsa reddedilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Validator {

	/**
	 * @param string $formId
	 * @param array  $post    Ham (unslash edilmiş) POST dizisi.
	 * @param array  $context array( 'options' => array( 'qualification' => array( kod => etiket ) ) ) — dinamik seçenek kaynakları.
	 * @return array{ok: bool, clean: array<string, mixed>, errors: array<string, string>, echo: array<string, string>}
	 *   clean: yalnız allowlist alanları (consent -> true, dosya alanları yok);
	 *   echo: yeniden-görüntüleme için YALNIZ hassas olmayan metin alanları.
	 */
	public static function validate( $formId, array $post, array $context = array() ) {
		$def = MaviBelge_Core_Forms_Schema::get( $formId );
		if ( null === $def ) {
			return array( 'ok' => false, 'clean' => array(), 'errors' => array( '_form' => 'Form bulunamadı.' ), 'echo' => array() );
		}
		$clean  = array();
		$errors = array();
		$echo   = array();
		foreach ( $def['fields'] as $field ) {
			$name = $field['name'];
			if ( 'file' === $field['type'] ) {
				continue; // Dosyalar MaviBelge_Core_Forms_Upload ile ayrıca doğrulanır.
			}
			$raw = array_key_exists( $name, $post ) ? $post[ $name ] : null;
			if ( is_array( $raw ) || is_object( $raw ) ) {
				$errors[ $name ] = 'Geçersiz değer.';
				continue;
			}
			$required = ! empty( $field['required'] );
			if ( 'consent' === $field['type'] ) {
				if ( '1' === $raw || 1 === $raw || true === $raw || 'on' === $raw ) {
					$clean[ $name ] = true;
				} elseif ( $required ) {
					$errors[ $name ] = 'Devam etmek için onaylamanız gerekir.';
				}
				continue;
			}
			$value = self::normalize( $field, null === $raw ? '' : (string) $raw );
			if ( '' === $value ) {
				if ( $required ) {
					$errors[ $name ] = 'Bu alan zorunludur.';
				}
				continue;
			}
			$error = self::check( $field, $value, $context );
			if ( null !== $error ) {
				$errors[ $name ] = $error;
				continue;
			}
			$clean[ $name ] = $value;
			if ( empty( $field['sensitive'] ) && in_array( $field['type'], array( 'text', 'email', 'tel', 'textarea', 'select', 'radio' ), true ) ) {
				$echo[ $name ] = $value;
			}
		}
		return array( 'ok' => empty( $errors ), 'clean' => $clean, 'errors' => $errors, 'echo' => $echo );
	}

	/** Kırp, kontrol karakterlerini temizle; tek satırlık türlerde CR/LF/NUL'u tamamen kaldır. */
	public static function normalize( array $field, $value ) {
		$value = str_replace( "\0", '', $value );
		$value = strip_tags( $value );
		if ( 'textarea' === $field['type'] ) {
			$value = preg_replace( "/\r\n?/", "\n", $value );
			$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
			return trim( $value );
		}
		$value = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value );
		return trim( preg_replace( '/ {2,}/', ' ', $value ) );
	}

	/** @return string|null Hata mesajı (girilen değeri içermez) veya null. */
	private static function check( array $field, $value, array $context ) {
		if ( isset( $field['max'] ) && self::length( $value ) > (int) $field['max'] ) {
			return 'Değer çok uzun.';
		}
		switch ( $field['type'] ) {
			case 'email':
				return self::is_valid_email( $value ) ? null : 'Geçerli bir e-posta adresi girin.';
			case 'tel':
				return self::is_valid_phone( $value ) ? null : 'Geçerli bir telefon numarası girin.';
			case 'select':
			case 'radio':
				$options = isset( $field['options'] ) ? $field['options'] : ( isset( $field['options_source'], $context['options'][ $field['options_source'] ] ) ? $context['options'][ $field['options_source'] ] : array() );
				return array_key_exists( $value, $options ) ? null : 'Listeden geçerli bir seçim yapın.';
		}
		if ( 'national_id' === $field['name'] ) {
			return self::is_valid_national_id( $value ) ? null : 'Geçerli bir T.C. kimlik numarası girin.';
		}
		if ( isset( $field['pattern'] ) && 'digits' === $field['pattern'] && 1 !== preg_match( '/^[0-9]+$/', $value ) ) {
			return 'Yalnız rakam girin.';
		}
		return null;
	}

	public static function is_valid_email( $value ) {
		return is_string( $value ) && strlen( $value ) <= 254 && 1 !== preg_match( '/[\x00-\x1F\x7F\s<>,;"\']/', $value ) && false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}

	/** 7-15 rakam; yalnız rakam, boşluk, ( ) - + . ve isteğe bağlı başta +. */
	public static function is_valid_phone( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\+?[0-9 ()\-]+$/', $value ) ) {
			return false;
		}
		$digits = strlen( preg_replace( '/[^0-9]/', '', $value ) );
		return $digits >= 7 && $digits <= 15;
	}

	/**
	 * T.C. kimlik numarası: 11 hane, ilk hane 0 değil, 10. hane = ((tek hane toplamı*7) - çift hane toplamı) mod 10,
	 * 11. hane = ilk 10 hane toplamı mod 10. (Algoritma; gerçek kişi verisi içermez.)
	 */
	public static function is_valid_national_id( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]{10}$/', $value ) ) {
			return false;
		}
		$d = array_map( 'intval', str_split( $value ) );
		$odd  = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
		$even = $d[1] + $d[3] + $d[5] + $d[7];
		if ( ( ( $odd * 7 - $even ) % 10 + 10 ) % 10 !== $d[9] ) {
			return false;
		}
		return ( array_sum( array_slice( $d, 0, 10 ) ) % 10 ) === $d[10];
	}

	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}
}
