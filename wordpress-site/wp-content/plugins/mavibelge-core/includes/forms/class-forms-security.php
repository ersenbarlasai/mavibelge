<?php
/**
 * Faz 8 — form güvenliği için SAF karar mantığı (WordPress fonksiyonu
 * çağırmaz): imzalı zaman damgalı tek-kullanımlık jeton, bal küpü (honeypot),
 * FAIL-CLOSED oran sınırı kararı ve dosya yükleme doğrulaması.
 *
 * WordPress nonce'u ("_mb_nonce") ayrıca hizmet katmanında doğrulanır; bu
 * sınıftaki jeton onu TAMAMLAR: anonim ziyaretçiler için nonce herkes için aynı
 * olduğundan tekrar gönderimi (replay) tek başına engelleyemez. Jeton, form
 * gösterildiği anı imzalar (min/maks yaş kontrolü — botların anında gönderimi)
 * ve başarılı gönderimden sonra bir kez tüketilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Security {

	const TOKEN_MIN_AGE = 3;      // saniye: form gösterildikten en az bu kadar sonra gönderilebilir
	const TOKEN_MAX_AGE = 7200;   // saniye: 2 saatten eski jeton geçersiz

	/** Yürütülebilir/betik uzantıları: dosya adında HERHANGİ bir yerde geçerse reddedilir (çift uzantı savunması). */
	const DANGEROUS_EXTENSIONS = array( 'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'pht', 'exe', 'dll', 'bat', 'cmd', 'com', 'sh', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'js', 'html', 'htm', 'svg', 'htaccess', 'ini' );

	/* ------------------------------------------------------------- jeton */

	/** @return string `<zaman>.<rastgele>.<imza>`; imza HMAC-SHA256(form|zaman|rastgele). */
	public static function issue_token( $formId, $secret, $now, $random ) {
		$payload = (int) $now . '.' . $random;
		return $payload . '.' . self::sign( $formId, $payload, $secret );
	}

	/**
	 * @return array{ok: bool, reason: string|null, id: string|null} id: tüketim kaydı için kararlı kimlik.
	 */
	public static function verify_token( $formId, $token, $secret, $now ) {
		if ( ! is_string( $token ) || 1 !== preg_match( '/^([0-9]{1,12})\.([0-9a-f]{16,64})\.([0-9a-f]{64})$/', $token, $m ) ) {
			return array( 'ok' => false, 'reason' => 'token_malformed', 'id' => null );
		}
		$payload = $m[1] . '.' . $m[2];
		if ( ! hash_equals( self::sign( $formId, $payload, $secret ), $m[3] ) ) {
			return array( 'ok' => false, 'reason' => 'token_bad_signature', 'id' => null );
		}
		$age = (int) $now - (int) $m[1];
		if ( $age < self::TOKEN_MIN_AGE ) {
			return array( 'ok' => false, 'reason' => 'token_too_fast', 'id' => null );
		}
		if ( $age > self::TOKEN_MAX_AGE ) {
			return array( 'ok' => false, 'reason' => 'token_expired', 'id' => null );
		}
		return array( 'ok' => true, 'reason' => null, 'id' => hash( 'sha256', $formId . '|' . $payload ) );
	}

	private static function sign( $formId, $payload, $secret ) {
		return hash_hmac( 'sha256', $formId . '|' . $payload, (string) $secret );
	}

	/* ----------------------------------------------------------- bal küpü */

	/** Bal küpü alanı DOLUYSA (boş olmayan herhangi bir değer/dizi) bot sayılır. */
	public static function honeypot_triggered( array $post ) {
		if ( ! array_key_exists( MaviBelge_Core_Forms_Schema::HONEYPOT_FIELD, $post ) ) {
			return false;
		}
		$value = $post[ MaviBelge_Core_Forms_Schema::HONEYPOT_FIELD ];
		return is_array( $value ) || is_object( $value ) || '' !== trim( (string) $value );
	}

	/* --------------------------------------------------------- oran sınırı */

	/**
	 * FAIL-CLOSED karar: sayaçlardan biri okunamazsa (null) izin VERİLMEZ.
	 *
	 * @param int|null $clientCount Bu istemcinin penceredeki sayısı (bu gönderim HARİÇ).
	 * @param int|null $globalCount Formun genel penceredeki sayısı.
	 * @param array    $limits      MaviBelge_Core_Forms_Config::DEFAULT_RATE_LIMIT biçimi.
	 * @return string 'ok' | 'client_limited' | 'global_limited' | 'unavailable'
	 */
	public static function evaluate_rate( $clientCount, $globalCount, array $limits ) {
		// Faz 10: karar mantığı TEK yerde (MaviBelge_Core_Rate_Limit); davranış birebir aynıdır.
		return MaviBelge_Core_Rate_Limit::evaluate( $clientCount, $globalCount, $limits );
	}

	/* ------------------------------------------------------------- dosya */

	/**
	 * @param string $name Kullanıcının gönderdiği ad (yalnız uzantı çıkarımı için; ASLA saklanan ad olmaz).
	 * @return string|null Küçük harf, son uzantı; tehlikeli veya geçersiz ad -> null.
	 */
	public static function safe_extension( $name ) {
		if ( ! is_string( $name ) || '' === $name || false !== strpos( $name, "\0" ) || strlen( $name ) > 255 ) {
			return null;
		}
		$base  = str_replace( '\\', '/', $name );
		$base  = substr( $base, (int) strrpos( $base, '/' ) ); // yol bileşenlerini at
		$base  = ltrim( $base, '/' );
		$parts = explode( '.', strtolower( $base ) );
		if ( count( $parts ) < 2 ) {
			return null;
		}
		$ext = end( $parts );
		foreach ( array_slice( $parts, 1 ) as $segment ) {
			if ( in_array( $segment, self::DANGEROUS_EXTENSIONS, true ) ) {
				return null; // "a.php.pdf" gibi çift uzantılar
			}
		}
		return 1 === preg_match( '/^[a-z0-9]{1,5}$/', $ext ) ? $ext : null;
	}

	/**
	 * Tek yüklenen dosya betimleyicisini doğrular. `$detectedMime` sunucuda finfo ile tespit edilir (istemci `type` alanına
	 * GÜVENİLMEZ); `$isUploaded` is_uploaded_file() sonucudur.
	 *
	 * @param array       $file        array( name, type, tmp_name, error, size )
	 * @param string[]    $allowedExt  izinli uzantılar (küçük harf)
	 * @param string|null $detectedMime
	 * @param bool        $isUploaded
	 * @return array{ok: bool, error: string|null, ext: string|null}
	 */
	public static function validate_upload( array $file, array $allowedExt, $detectedMime, $isUploaded ) {
		$fail = function ( $code ) {
			return array( 'ok' => false, 'error' => $code, 'ext' => null );
		};
		if ( ! isset( $file['error'], $file['size'], $file['name'], $file['tmp_name'] ) || ! is_int( $file['error'] ) || ! is_int( $file['size'] ) ) {
			return $fail( 'upload_malformed' );
		}
		if ( UPLOAD_ERR_NO_FILE === $file['error'] ) {
			return $fail( 'upload_missing' );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return $fail( 'upload_error' );
		}
		if ( true !== $isUploaded ) {
			return $fail( 'upload_not_uploaded_file' );
		}
		if ( $file['size'] <= 0 || $file['size'] > MaviBelge_Core_Forms_Schema::UPLOAD_MAX_BYTES ) {
			return $fail( 'upload_size' );
		}
		$ext = self::safe_extension( $file['name'] );
		if ( null === $ext || ! in_array( $ext, $allowedExt, true ) || ! isset( MaviBelge_Core_Forms_Schema::UPLOAD_TYPES[ $ext ] ) ) {
			return $fail( 'upload_extension' );
		}
		if ( ! is_string( $detectedMime ) || ! in_array( $detectedMime, MaviBelge_Core_Forms_Schema::UPLOAD_TYPES[ $ext ], true ) ) {
			return $fail( 'upload_mime' );
		}
		return array( 'ok' => true, 'error' => null, 'ext' => $ext );
	}

	/** Rastgele, tahmin edilemez dosya adı (kullanıcı adı ASLA kullanılmaz). */
	public static function random_filename( $ext, $randomHex ) {
		return 1 === preg_match( '/^[0-9a-f]{32}$/', $randomHex ) && 1 === preg_match( '/^[a-z0-9]{1,5}$/', $ext ) ? $randomHex . '.' . $ext : null;
	}
}
