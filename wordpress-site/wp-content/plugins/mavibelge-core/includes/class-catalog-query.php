<?php
/**
 * Faz 5 — pure, WordPress-independent helpers for the public catalog
 * (meslek/sektör/ücret) query contract. No method here ever calls a
 * WordPress database function; this class is exercised directly by
 * tests/run.php's standalone bootstrap (no `php` binary was available
 * in the authoring environment either — see tests/README.md for what
 * was and was not actually run).
 *
 * The five public GET parameter names this schema fixes (see
 * docs/catalog-service-contract.md §GET parametreleri):
 *
 *   mb_q       free-text search term (meslek adı veya MYK kodu)
 *   mb_sector  mb_sektor term slug
 *   mb_level   MYK seviyesi, "1"-"8"
 *   mb_priced  "1" = yalnız geçerli aktif ücreti bulunanlar
 *   mb_page    1 tabanlı sayfa numarası
 *
 * MaviBelge_Core_Catalog_Service (public/class-catalog-service.php) is
 * the only caller of these normalizers — a theme template must never
 * reimplement this sanitization itself (see AGENTS.md / brief §7).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Catalog_Query {

	const ALLOWED_LEVELS   = array( '1', '2', '3', '4', '5', '6', '7', '8' );
	const MAX_QUERY_LENGTH = 100;
	const DEFAULT_PAGE_SIZE = 12;

	/**
	 * Normalizes a raw $_GET-shaped array (or any array using the same
	 * five keys) into a clean filters array. Never trusts array/object
	 * values where a scalar is expected — such a shape is treated as
	 * "not provided" rather than being cast/stringified.
	 *
	 * @return array{q: string, sector: string, level: string, priced: bool, page: int}
	 */
	public static function normalize_filters( array $raw ) {
		return array(
			'q'      => self::normalize_query_term( self::unslash_scalar( self::scalar_or_empty( isset( $raw['mb_q'] ) ? $raw['mb_q'] : '' ) ) ),
			'sector' => self::normalize_sector_slug( self::unslash_scalar( self::scalar_or_empty( isset( $raw['mb_sector'] ) ? $raw['mb_sector'] : '' ) ) ),
			'level'  => self::normalize_level( self::unslash_scalar( self::scalar_or_empty( isset( $raw['mb_level'] ) ? $raw['mb_level'] : '' ) ) ),
			'priced' => self::normalize_priced_flag( self::unslash_scalar( self::scalar_or_empty( isset( $raw['mb_priced'] ) ? $raw['mb_priced'] : '' ) ) ),
			'page'   => self::normalize_page( self::unslash_scalar( self::scalar_or_empty( isset( $raw['mb_page'] ) ? $raw['mb_page'] : '' ) ) ),
		);
	}

	/**
	 * Faz 5 Düzeltme ve Kabul §2.2: a real $_GET value coming through
	 * WordPress may still carry the magic-quotes-style backslash escaping
	 * WordPress itself adds to superglobals (wp_unslash() undoes this —
	 * it is NOT the same as generic "sanitization"; skipping it means a
	 * search term containing an apostrophe reaches sanitize_text_field()
	 * still backslash-escaped). Applied AFTER the array/object shape
	 * check (scalar_or_empty()) and BEFORE any of the normalize_*()
	 * functions below, for all five GET keys alike. Falls back to
	 * stripslashes() so this stays exercisable from the standalone
	 * tests/run.php bootstrap, which has no WordPress functions loaded.
	 */
	private static function unslash_scalar( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		return function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : stripslashes( $value );
	}

	private static function scalar_or_empty( $value ) {
		return ( is_array( $value ) || is_object( $value ) ) ? '' : $value;
	}

	public static function normalize_query_term( $raw ) {
		$value = trim( (string) $raw );
		if ( '' === $value ) {
			return '';
		}
		$value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : strip_tags( $value );
		$value = trim( preg_replace( '/\s+/', ' ', $value ) );
		if ( self::str_length( $value ) > self::MAX_QUERY_LENGTH ) {
			$value = self::str_truncate( $value, self::MAX_QUERY_LENGTH );
		}
		return $value;
	}

	/**
	 * Format-only check (a-z0-9, tire ile ayrılmış) — a real mb_sektor
	 * term with this slug may or may not exist; that lookup needs the
	 * database and is the service's job (get_term_by()), never this
	 * pure class's.
	 */
	public static function normalize_sector_slug( $raw ) {
		$value = strtolower( trim( (string) $raw ) );
		if ( '' === $value ) {
			return '';
		}
		return (bool) preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $value ) ? $value : '';
	}

	public static function normalize_level( $raw ) {
		$value = trim( (string) $raw );
		return in_array( $value, self::ALLOWED_LEVELS, true ) ? $value : '';
	}

	/** Only the literal "1" means true — anything else (including "0", "true", "yes") is false. */
	public static function normalize_priced_flag( $raw ) {
		return '1' === trim( (string) $raw );
	}

	public static function normalize_page( $raw ) {
		$value = trim( (string) $raw );
		if ( '' === $value || ! preg_match( '/^[0-9]+$/', $value ) ) {
			return 1;
		}
		$int = (int) $value;
		return $int > 0 ? $int : 1;
	}

	/** Clamps a requested page into [1, max(total_pages,1)] — never 0, never negative, never past the last real page. */
	public static function clamp_page( $page, $total_pages ) {
		$total_pages = max( 1, (int) $total_pages );
		$page        = max( 1, (int) $page );
		return min( $page, $total_pages );
	}

	/**
	 * True only when both sides are non-empty and match exactly (trimmed,
	 * case-sensitive string compare — tarife dönemi bir serbest metin
	 * etiketidir, örn. "2026"). An empty active period means "no active
	 * period is set" and therefore no record can ever match it, by
	 * design — never falls back to "show everything".
	 */
	public static function is_period_match( $record_period, $active_period ) {
		$active_period = trim( (string) $active_period );
		if ( '' === $active_period ) {
			return false;
		}
		return trim( (string) $record_period ) === $active_period;
	}

	/**
	 * Inclusive validity-window check against a Y-m-d "today" string.
	 * Empty valid_from/valid_until is unbounded on that side. Y-m-d
	 * strings compare correctly with plain string comparison operators
	 * (zero-padded, fixed-width, big-endian) ONLY once each non-empty
	 * value is confirmed to actually BE a real, valid calendar date —
	 * Faz 5 Düzeltme ve Kabul §2.8: this used to compare raw strings
	 * with no format/calendar check at all, so a malformed value
	 * ("2026-13-40"), a value that merely LOOKS Y-m-d-shaped but isn't a
	 * real date ("2026-02-30"), or a reversed range (valid_from AFTER
	 * valid_until) could silently pass or silently fail in an
	 * unpredictable way instead of failing closed.
	 *
	 * Fails CLOSED (returns false, hides the fee) on: missing/unavailable
	 * canonical date validator, invalid/empty $today_ymd, an invalid
	 * non-empty $valid_from or $valid_until, or valid_from > valid_until
	 * when both are set.
	 */
	public static function is_within_validity_window( $valid_from, $valid_until, $today_ymd ) {
		$valid_from  = trim( (string) $valid_from );
		$valid_until = trim( (string) $valid_until );
		$today_ymd   = trim( (string) $today_ymd );

		if ( ! class_exists( 'MaviBelge_Core_Validator' ) ) {
			return false;
		}
		if ( '' === $today_ymd || ! MaviBelge_Core_Validator::is_valid_ymd_date( $today_ymd ) ) {
			return false;
		}
		if ( '' !== $valid_from && ! MaviBelge_Core_Validator::is_valid_ymd_date( $valid_from ) ) {
			return false;
		}
		if ( '' !== $valid_until && ! MaviBelge_Core_Validator::is_valid_ymd_date( $valid_until ) ) {
			return false;
		}
		if ( '' !== $valid_from && '' !== $valid_until && $valid_from > $valid_until ) {
			return false;
		}
		if ( '' !== $valid_from && $today_ymd < $valid_from ) {
			return false;
		}
		if ( '' !== $valid_until && $today_ymd > $valid_until ) {
			return false;
		}
		return true;
	}

	/**
	 * Canonical, single price-options validity+ordering decision — Faz 5
	 * Düzeltme ve Kabul §2.1. This used to check ONLY that every row
	 * carried a positive integer amount_kurus, so a stored row missing
	 * its label, carrying an oversized label, an out-of-range unit list,
	 * or exceeding MAX_PRICE_OPTIONS could still pass and be shown to
	 * the public. There is now exactly ONE validator for this shape,
	 * shared with the admin save path and the publish-readiness gate:
	 * MaviBelge_Core_Validator::normalize_price_options() (see
	 * docs/content-model.md "Tek ayrıştırma noktası"). If it reports ANY
	 * error, the ENTIRE list is treated as invalid — never a partially
	 * "fixed"/trimmed subset. Delegates to canonicalize_price_options()
	 * so the same call also fixes the display ordering (brief: "geçerli
	 * seçenekler kanonik ve kararlı sırada sunulmalı").
	 */
	public static function has_priced_options( $price_options ) {
		return ! empty( self::canonicalize_price_options( $price_options ) );
	}

	/**
	 * Re-validates a stored _mb_price_options array through the ONE
	 * canonical validator and returns the resulting canonically-sorted
	 * option list, or an empty array if ANY row is invalid (atomic
	 * reject — see has_priced_options() docblock). Callers that need to
	 * both check validity AND render the options (present_fee()) should
	 * call this once and reuse the result, rather than validating and
	 * re-deriving the display list separately.
	 *
	 * @return array canonical option rows, or empty array if invalid/empty/unavailable.
	 */
	public static function canonicalize_price_options( $price_options ) {
		if ( ! is_array( $price_options ) || empty( $price_options ) ) {
			return array();
		}
		if ( ! class_exists( 'MaviBelge_Core_Validator' ) ) {
			return array();
		}
		$result = MaviBelge_Core_Validator::normalize_price_options( $price_options );
		if ( ! empty( $result['errors'] ) ) {
			return array();
		}
		return $result['options'];
	}

	/**
	 * Case-insensitive, Turkish-aware-only-via-mb_strtolower "contains"
	 * check. Documented limitation (see docs/catalog-service-contract.md):
	 * mb_strtolower with the 'UTF-8' encoding folds the ASCII/Turkish
	 * common cases (ör. "İ"/"i" doğru eşleşmeyebilir dotless/dotted-I
	 * edge durumunda) — this is a plain substring match, not a fuzzy or
	 * locale-aware search; no external ICU/Intl dependency is assumed.
	 */
	public static function text_contains_ci( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}
		$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $haystack, 'UTF-8' ) : strtolower( (string) $haystack );
		$needle   = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $needle, 'UTF-8' ) : strtolower( (string) $needle );
		if ( function_exists( 'mb_strpos' ) ) {
			return false !== mb_strpos( $haystack, $needle, 0, 'UTF-8' );
		}
		return false !== strpos( $haystack, $needle );
	}

	/**
	 * Builds the subset of normalized filters that should survive into a
	 * pagination/"temizle" link as real query args — empty/default
	 * values are omitted entirely rather than emitted as "mb_q=". The
	 * 'page' key is deliberately never included here; callers add
	 * mb_page themselves per target page (see template-parts/catalog/*).
	 *
	 * @return array<string, string>
	 */
	public static function filters_to_query_args( array $filters ) {
		$args = array();
		if ( ! empty( $filters['q'] ) ) {
			$args['mb_q'] = $filters['q'];
		}
		if ( ! empty( $filters['sector'] ) ) {
			$args['mb_sector'] = $filters['sector'];
		}
		if ( ! empty( $filters['level'] ) ) {
			$args['mb_level'] = $filters['level'];
		}
		if ( ! empty( $filters['priced'] ) ) {
			$args['mb_priced'] = '1';
		}
		return $args;
	}

	private static function str_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	private static function str_truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
