<?php
/**
 * Faz 6B1 — deterministic managed-field canonicalization + SHA-256 hash.
 *
 * This is the WordPress/PHP-side counterpart of
 * wordpress-site/tools/import/lib/hash.js's toDeterministicJson()/
 * sha256Hex() — the two are implemented independently (Node vs. PHP,
 * different languages/runtimes) but MUST follow the same documented
 * canonicalization rule so a Faz 6B2 comparison between a Node-computed
 * manifest hash and a PHP-computed "current managed state" hash is
 * meaningful. No WordPress function is used here — this class is pure
 * PHP 7.3, safe to unit test with `php tests/run.php` alone.
 *
 * BAĞLAYICI KANONİKLEŞTİRME KURALI (Faz 6A Son Kabul Düzeltmesi §6'daki
 * gibi burada da tek belgeli kural, iki farklı yerde iki kopya değil):
 *
 * 1. Anahtarlar (associative array'lerde) özyinelemeli olarak `SORT_STRING`
 *    (byte sırası) ile sıralanır. Bu proje alan adları yalnız ASCII
 *    küçük-harf/alt-çizgi olduğundan bu, Node'un varsayılan string
 *    karşılaştırmasıyla (UTF-16 code unit sırası) ASCII aralığında
 *    birebir aynı sonucu verir.
 * 2. Liste (sequential 0..n-1 integer key) dizilerinin SIRASI KORUNUR —
 *    yeniden sıralanmaz.
 * 3. Skaler tipler AYNEN korunur: `null`, `bool`, `int`, `string`.
 *    `float` KABUL EDİLMEZ (bu projede tüm parasal/sayısal alanlar zaten
 *    tam sayı — kuruş, seviye, sayfa no — bir float'ın hash girdisine
 *    sızması, yuvarlama/temsil farkına bağlı sessiz hash kaymasını
 *    engellemek için sert biçimde reddedilir).
 * 4. `array`/`object`/`resource` (kanonikleştirilebilir dizi dışında) ve
 *    her PHP tipi (`is_scalar()`+`null`+dizi dışında her şey) reddedilir.
 * 5. JSON kodlaması `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`
 *    bayraklarıyla yapılır — Node'un `JSON.stringify()`'ı da ne `/`
 *    karakterini ne Unicode karakterleri kaçış (`\uXXXX`) ile yazar; bu
 *    iki bayrak PHP'nin varsayılanını Node'un davranışıyla eşitler.
 * 6. `json_encode()` başarısız olursa (ör. geçersiz UTF-8 string) SESSİZCE
 *    `false` hashlenmez — bir istisna fırlatılır.
 * 7. Hash `hash('sha256', $json)` — tam 64 küçük-harf hex karakter
 *    (PHP'nin `hash()` fonksiyonu zaten küçük harf üretir).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Hash {

	/**
	 * @param mixed $value null/bool/int/string veya (özyinelemeli) yalnız
	 *   bunları içeren bir dizi.
	 * @return string tam 64 küçük-harf hex karakter.
	 * @throws InvalidArgumentException hashlenemeyen bir tip/değer geçilirse.
	 */
	public static function hash( $value ) {
		$canonical = self::canonicalize( $value );
		$json      = self::encode_json( $canonical );
		return hash( 'sha256', $json );
	}

	/**
	 * Deliberately does NOT call WordPress's own `wp_json_encode()` — this
	 * whole class must stay callable from `tests/run.php` without a
	 * WordPress bootstrap (see class docblock). Same flags `wp_json_encode()`
	 * would use for a plain array (`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`),
	 * applied directly to PHP's own `json_encode()`.
	 *
	 * @throws InvalidArgumentException on any json_encode() failure (never hashes `false`).
	 */
	private static function encode_json( $canonical ) {
		$json = json_encode( $canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			throw new InvalidArgumentException(
				'Faz 6B1 hash girdisi: json_encode() başarısız oldu (' . json_last_error_msg() . ') — geçersiz UTF-8 veya desteklenmeyen bir değer olabilir.'
			);
		}
		return $json;
	}

	/**
	 * @param mixed $value
	 * @return mixed kanonikleştirilmiş (anahtar sıralı, tip korunmuş) değer.
	 * @throws InvalidArgumentException
	 */
	public static function canonicalize( $value ) {
		if ( null === $value || is_bool( $value ) || is_int( $value ) || is_string( $value ) ) {
			return $value;
		}
		if ( is_float( $value ) ) {
			throw new InvalidArgumentException(
				'Faz 6B1 hash girdisi: float değer kabul edilmez (tüm sayısal alanlar tam sayı olmalı, bkz. class-import-hash.php dokblok kural 3).'
			);
		}
		if ( is_array( $value ) ) {
			if ( self::is_list_array( $value ) ) {
				$out = array();
				foreach ( $value as $item ) {
					$out[] = self::canonicalize( $item );
				}
				return $out;
			}
			$keys = array_keys( $value );
			sort( $keys, SORT_STRING );
			$out = array();
			foreach ( $keys as $key ) {
				$out[ $key ] = self::canonicalize( $value[ $key ] );
			}
			return $out;
		}
		throw new InvalidArgumentException(
			'Faz 6B1 hash girdisi: hashlenemeyen tip (' . gettype( $value ) . ') — yalnız null/bool/int/string/dizi kabul edilir.'
		);
	}

	/**
	 * PHP 7.3'te `array_is_list()` yok (8.1+) — kendi güvenli tespitimiz:
	 * dizi, `0..count-1` aralığında, SIRALI, integer anahtarlar taşıyorsa
	 * "liste" sayılır (boş dizi de liste sayılır — JS'teki `[]` ile
	 * eşdeğer). Aksi halde associative (JSON object) kabul edilir.
	 */
	private static function is_list_array( array $arr ) {
		$expected = 0;
		foreach ( $arr as $key => $unused_value ) {
			if ( $key !== $expected ) {
				return false;
			}
			++$expected;
		}
		return true;
	}
}
