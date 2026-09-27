<?php
/**
 * Faz 6B4 — admin apply/rollback yüzeyinin güvenlik kapıları, kapalı request şekli ve onay ifadeleri.
 *
 * SAF çekirdek (WordPress fonksiyonu çağırmaz): `evaluate()`, `normalize_request()`, `apply_phrase()`,
 * `rollback_phrase()`, `phrase_matches()`. WordPress'ten okuma YALNIZ `snapshot()`'tadır ve yalnız şunları okur:
 * istek metodu, HTTPS, oturum/yetki, `wp-config.php` sabitleri, ortam türü ve host. Sabitler formdan, URL'den,
 * option'dan veya JavaScript'ten AÇILAMAZ: `evaluate()` istek verisi almaz, yalnız bu snapshot'ı alır.
 *
 * Kapı seviyeleri:
 *  - read : önizleme (salt okunur) — POST + HTTPS + giriş + `manage_options` + `mb_manage_tariff_period`.
 *  - map  : sektör görsel eşleme kaydı — read ile aynı (apply sabitleri GEREKMEZ: runbook'ta eşleme, sabitler açılmadan
 *           ÖNCE yapılır; eşleme apply değildir ve hiçbir içerik yazmaz).
 *  - publish : sayfa yayınlama (Faz 12) — run ile aynı kapılar + YALNIZ staging (üretimde yayınlama bu sürümde yoktur: `publish_staging_only`).
 *  - run  : apply/rollback başlat-ilerlet — read + `MAVIBELGE_IMPORT_APPLY_ENABLED === true` +
 *           `MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED === true` + ortam: `staging`; `production` ise ayrıca
 *           `MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED === true` ve `MAVIBELGE_IMPORT_PRODUCTION_HOST`'un mevcut host ile
 *           BİREBİR eşleşmesi. Başka her ortam türü (local/development/boş/bilinmeyen) reddedilir.
 * Sabitler yalnız gerçek `true` (bool) kabul edilir; '1', 1, 'true' KABUL EDİLMEZ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Admin_Gates {

	const ACTION_PREFIX = 'mavibelge_import_';
	const NONCE_FIELD   = 'mb_import_nonce';

	const CONST_APPLY           = 'MAVIBELGE_IMPORT_APPLY_ENABLED';
	const CONST_ADMIN_APPLY     = 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED';
	const CONST_PRODUCTION      = 'MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED';
	const CONST_PRODUCTION_HOST = 'MAVIBELGE_IMPORT_PRODUCTION_HOST';

	const LEVELS = array( 'read', 'map', 'run', 'publish' );

	/** action => seviye + kapalı (nonce/action dışı) POST anahtarları. */
	const ACTIONS = array(
		'save_sector_image_map' => array( 'level' => 'map', 'keys' => array( 'mappings' ) ),
		'preview_stage'         => array( 'level' => 'read', 'keys' => array( 'stage' ) ),
		'start_apply'           => array( 'level' => 'run', 'keys' => array( 'stage', 'plan_digest', 'confirm_phrase' ) ),
		'advance_apply'         => array( 'level' => 'run', 'keys' => array( 'run_uid', 'expected_checkpoint' ) ),
		'preview_rollback'      => array( 'level' => 'read', 'keys' => array( 'run_uid' ) ),
		'start_rollback'        => array( 'level' => 'run', 'keys' => array( 'run_uid', 'rollback_digest', 'confirm_phrase' ) ),
		'advance_rollback'      => array( 'level' => 'run', 'keys' => array( 'run_uid', 'expected_checkpoint' ) ),
		// Faz 12: taslak sayfaların AYRI, açık onaylı yayınlanması (staging'e özel; sayfa oluşturma ile birleştirilmez).
		'preview_publish'       => array( 'level' => 'read', 'keys' => array() ),
		'publish_pages'         => array( 'level' => 'publish', 'keys' => array( 'plan_digest', 'confirm_phrase', 'expected_remaining' ) ),
	);

	const MAX_PHRASE_LENGTH = 200;
	const MAX_MAPPINGS      = 50;

	public static function nonce_action( $action ) {
		return self::ACTION_PREFIX . $action;
	}

	/**
	 * @param string $level read|map|run
	 * @param array  $snapshot method, is_ssl, user_id, can_manage_options, can_tariff, constants, environment_type, host
	 * @return array{ok: bool, codes: string[]} Reddedilen kapıların SABİT kodları.
	 */
	public static function evaluate( $level, array $snapshot ) {
		if ( ! is_string( $level ) || ! in_array( $level, self::LEVELS, true ) ) {
			return array( 'ok' => false, 'codes' => array( 'unknown_level' ) );
		}
		foreach ( array( 'method', 'is_ssl', 'user_id', 'can_manage_options', 'can_tariff', 'constants', 'environment_type', 'host' ) as $key ) {
			if ( ! array_key_exists( $key, $snapshot ) ) {
				return array( 'ok' => false, 'codes' => array( 'invalid_snapshot' ) );
			}
		}
		$codes = array();
		if ( 'POST' !== $snapshot['method'] ) {
			$codes[] = 'method_not_post';
		}
		if ( true !== $snapshot['is_ssl'] ) {
			$codes[] = 'not_https';
		}
		if ( ! is_int( $snapshot['user_id'] ) || $snapshot['user_id'] <= 0 ) {
			$codes[] = 'not_logged_in';
		}
		if ( true !== $snapshot['can_manage_options'] || true !== $snapshot['can_tariff'] ) {
			$codes[] = 'missing_capability';
		}
		if ( 'run' === $level || 'publish' === $level ) {
			$constants = is_array( $snapshot['constants'] ) ? $snapshot['constants'] : array();
			if ( ! array_key_exists( self::CONST_APPLY, $constants ) || true !== $constants[ self::CONST_APPLY ] ) {
				$codes[] = 'apply_disabled';
			}
			if ( ! array_key_exists( self::CONST_ADMIN_APPLY, $constants ) || true !== $constants[ self::CONST_ADMIN_APPLY ] ) {
				$codes[] = 'admin_apply_disabled';
			}
			if ( 'staging' === $snapshot['environment_type'] ) {
				// staging: ek kapı yok.
			} elseif ( 'production' === $snapshot['environment_type'] ) {
				if ( ! array_key_exists( self::CONST_PRODUCTION, $constants ) || true !== $constants[ self::CONST_PRODUCTION ] ) {
					$codes[] = 'production_disabled';
				}
				$expectedHost = array_key_exists( self::CONST_PRODUCTION_HOST, $constants ) ? $constants[ self::CONST_PRODUCTION_HOST ] : null;
				if ( ! is_string( $expectedHost ) || '' === $expectedHost || ! is_string( $snapshot['host'] ) || $snapshot['host'] !== $expectedHost ) {
					$codes[] = 'production_host_mismatch';
				}
			} else {
				$codes[] = 'environment_not_allowed';
			}
			if ( 'publish' === $level && 'staging' !== $snapshot['environment_type'] ) {
				$codes[] = 'publish_staging_only';
			}
		}
		return array( 'ok' => array() === $codes, 'codes' => $codes );
	}

	/** "UYGULA <stage> <plan digest ilk 12>" — kullanıcının BİREBİR yazması gereken ifade. */
	public static function apply_phrase( $stage, $planDigest ) {
		if ( ! is_string( $stage ) || ! in_array( $stage, MaviBelge_Core_Import_Apply_Plan::ALL_STAGES, true ) || ! MaviBelge_Core_Import_Apply_Plan::is_digest( $planDigest ) ) {
			return null;
		}
		return 'UYGULA ' . $stage . ' ' . substr( $planDigest, 0, 12 );
	}

	/** "YAYINLA <plan digest ilk 12>" — sayfa yayınlama onayı (apply/rollback ifadelerinden AYRI). */
	public static function publish_phrase( $planDigest ) {
		return MaviBelge_Core_Import_Page_Publisher::confirm_phrase( $planDigest );
	}

	/** "GERI AL <run uid ilk 8> <rollback digest ilk 12>". */
	public static function rollback_phrase( $runUid, $rollbackDigest ) {
		if ( ! is_string( $runUid ) || 1 !== preg_match( '/^[0-9a-f]{32}\z/', $runUid ) || ! MaviBelge_Core_Import_Apply_Plan::is_digest( $rollbackDigest ) ) {
			return null;
		}
		return 'GERI AL ' . substr( $runUid, 0, 8 ) . ' ' . substr( $rollbackDigest, 0, 12 );
	}

	/** Sessiz düzeltme YOK: yalnız birebir eşit gerçek string kabul edilir. */
	public static function phrase_matches( $submitted, $expected ) {
		return is_string( $submitted ) && is_string( $expected ) && '' !== $expected && hash_equals( $expected, $submitted );
	}

	/**
	 * Kapalı request şekli: POST anahtarları TAM olarak {action, nonce, action anahtarları}; GET boş; değerler tipli ve
	 * kanonik. Nonce'ın DOĞRULUĞU çağıran katmanda (wp_verify_nonce) denetlenir; burada yalnız biçimi (string) ve varlığı.
	 * Değerler `wp_unslash` edilmiş olmalıdır.
	 *
	 * @param mixed $post
	 * @param mixed $get
	 * @return array{ok: bool, error_code: string|null, data: array, nonce: string|null}
	 */
	public static function normalize_request( $action, $post, $get ) {
		if ( ! is_string( $action ) || ! array_key_exists( $action, self::ACTIONS ) || ! is_array( $post ) || ! is_array( $get ) ) {
			return self::bad( 'invalid_request' );
		}
		if ( array() !== $get ) {
			return self::bad( 'unexpected_request_key' );
		}
		$expected = array_merge( array( 'action', self::NONCE_FIELD ), self::ACTIONS[ $action ]['keys'] );
		$given    = array_keys( $post );
		sort( $expected, SORT_STRING );
		$givenSorted = array_map( 'strval', $given );
		sort( $givenSorted, SORT_STRING );
		if ( $givenSorted !== $expected ) {
			return self::bad( 'unexpected_request_key' );
		}
		if ( ! isset( $post['action'] ) || self::ACTION_PREFIX . $action !== $post['action'] ) {
			return self::bad( 'invalid_request' );
		}
		if ( ! isset( $post[ self::NONCE_FIELD ] ) || ! is_string( $post[ self::NONCE_FIELD ] ) || 1 !== preg_match( '/^[A-Za-z0-9]{6,64}\z/', $post[ self::NONCE_FIELD ] ) ) {
			return self::bad( 'invalid_nonce' );
		}
		$data = array();
		foreach ( self::ACTIONS[ $action ]['keys'] as $key ) {
			$value = $post[ $key ];
			switch ( $key ) {
				case 'stage':
					if ( ! is_string( $value ) || ! in_array( $value, MaviBelge_Core_Import_Apply_Plan::ALL_STAGES, true ) ) {
						return self::bad( 'invalid_request' );
					}
					break;
				case 'plan_digest':
				case 'rollback_digest':
					if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $value ) ) {
						return self::bad( 'invalid_request' );
					}
					break;
				case 'run_uid':
					if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9a-f]{32}\z/', $value ) ) {
						return self::bad( 'invalid_request' );
					}
					break;
				case 'expected_checkpoint':
				case 'expected_remaining':
					$parsed = is_string( $value ) ? MaviBelge_Core_Validator::parse_canonical_decimal_int( $value ) : array( 'ok' => false );
					if ( ! $parsed['ok'] || $parsed['value'] < 0 ) {
						return self::bad( 'invalid_request' );
					}
					$value = $parsed['value'];
					break;
				case 'confirm_phrase':
					if ( ! is_string( $value ) || strlen( $value ) > self::MAX_PHRASE_LENGTH ) {
						return self::bad( 'invalid_request' );
					}
					break;
				case 'mappings':
					$mappings = self::normalize_mappings( $value );
					if ( null === $mappings ) {
						return self::bad( 'invalid_request' );
					}
					$value = $mappings;
					break;
				default:
					return self::bad( 'invalid_request' );
			}
			$data[ $key ] = $value;
		}
		return array( 'ok' => true, 'error_code' => null, 'data' => $data, 'nonce' => $post[ self::NONCE_FIELD ] );
	}

	/** @return array<string,int>|null */
	private static function normalize_mappings( $value ) {
		if ( ! is_array( $value ) || array() === $value || count( $value ) > self::MAX_MAPPINGS ) {
			return null;
		}
		$out = array();
		foreach ( $value as $slug => $id ) {
			if ( ! is_string( $slug ) || 1 !== preg_match( '/^[a-z][a-z0-9]*(-[a-z0-9]+)*\z/', $slug ) || ! is_string( $id ) ) {
				return null;
			}
			$parsed = MaviBelge_Core_Validator::parse_canonical_decimal_int( $id );
			if ( ! $parsed['ok'] || $parsed['value'] <= 0 ) {
				return null;
			}
			$out[ $slug ] = $parsed['value'];
		}
		return $out;
	}

	private static function bad( $code ) {
		return array( 'ok' => false, 'error_code' => $code, 'data' => array(), 'nonce' => null );
	}

	/**
	 * GERÇEK WordPress durumundan snapshot (yalnız okur). Sabitler `defined()`/`constant()` ile okunur; istek verisi
	 * (`$_POST`/`$_GET`/`$_COOKIE`) buraya GİRMEZ.
	 *
	 * @return array
	 */
	public static function snapshot() {
		$constants = array();
		foreach ( array( self::CONST_APPLY, self::CONST_ADMIN_APPLY, self::CONST_PRODUCTION, self::CONST_PRODUCTION_HOST ) as $name ) {
			if ( defined( $name ) ) {
				$constants[ $name ] = constant( $name );
			}
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) && is_string( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return array(
			'method'             => isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'is_ssl'             => is_ssl(),
			'user_id'            => (int) get_current_user_id(),
			'can_manage_options' => current_user_can( 'manage_options' ),
			'can_tariff'         => current_user_can( 'mb_manage_tariff_period' ),
			'constants'          => $constants,
			'environment_type'   => wp_get_environment_type(),
			'host'               => $host,
		);
	}
}
