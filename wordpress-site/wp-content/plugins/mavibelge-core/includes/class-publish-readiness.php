<?php
/**
 * Blocks a mavibelge-core content type from actually reaching
 * post_status = 'publish' until its minimum required fields are
 * present AND valid (Faz2 düzeltme brief §4, tightened further in the
 * Faz2 ikinci düzeltme brief §4).
 *
 * Hooked on 'wp_insert_post_data' — a filter that runs INSIDE
 * wp_insert_post(), before it writes to the database, and lets us
 * rewrite $data['post_status'] before the row is saved. This is
 * deliberately NOT implemented as a 'save_post' handler that calls
 * wp_update_post() to "fix" the status after the fact: doing that
 * would re-trigger 'save_post' (and this code) again, requiring
 * re-entrancy guards to avoid an infinite loop. Filtering the data
 * before the single INSERT/UPDATE avoids that class of bug entirely —
 * there is only ever one DB write per request here.
 *
 * Every scalar meta field check below goes through the SAME
 * MaviBelge_Core_Field_Repository::resolve_effective_value() that the
 * ikinci düzeltme brief §4 asked for, in the documented priority
 * order: 1) this request's own validated admin field, 2)
 * $postarr['meta_input'], 3) the already-stored postmeta value. A
 * field is only "ready" when the resolver says both present and
 * valid=true — an admin request that fails the exact same format rule
 * the real save would apply (e.g. a malformed _mb_sector_slug) can no
 * longer sneak past readiness only to be rejected a moment later by
 * the save handler.
 *
 * No direct WordPress test environment exercises this class in this
 * phase — see the Faz2 ikinci düzeltme delivery report for what was
 * verified by static/logical review instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Publish_Readiness {

	public static function init() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_publish' ), 10, 2 );
	}

	public static function guard_publish( $data, $postarr ) {
		if ( ! isset( $data['post_status'] ) || 'publish' !== $data['post_status'] ) {
			return $data;
		}
		if ( empty( $data['post_type'] ) || ! array_key_exists( $data['post_type'], MaviBelge_Core_Meta_Schema::get_schema() ) ) {
			return $data;
		}

		$post_id  = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$problems = self::check_readiness( $data['post_type'], $post_id, $data, $postarr );

		if ( empty( $problems ) ) {
			return $data;
		}

		$fallback_status     = $post_id ? get_post_status( $post_id ) : '';
		$data['post_status'] = ( $fallback_status && ! in_array( $fallback_status, array( 'publish', 'auto-draft', 'trash' ), true ) )
			? $fallback_status
			: 'draft';

		self::queue_notices( $problems );

		return $data;
	}

	private static function queue_notices( array $problems ) {
		if ( ! class_exists( 'MaviBelge_Core_Admin_Notices' ) ) {
			return;
		}
		foreach ( $problems as $problem ) {
			MaviBelge_Core_Admin_Notices::queue( $problem, 'error' );
		}
		MaviBelge_Core_Admin_Notices::queue(
			'Zorunlu alanlar eksik veya geçersiz olduğu için kayıt yayınlanmadı; taslak/beklemede olarak tutuldu.',
			'error'
		);
	}

	/**
	 * @return string[] Turkish problem descriptions; empty array = ready to publish.
	 */
	public static function check_readiness( $post_type, $post_id, array $data = array(), array $postarr = array() ) {
		switch ( $post_type ) {
			case 'mb_yeterlilik':
				return self::check_yeterlilik( $post_id, $data, $postarr );
			case 'mb_ucret':
				return self::check_ucret( $post_id, $data, $postarr );
			case 'mb_haber':
				return self::check_haber( $post_id, $data, $postarr );
			case 'mb_dokuman':
				return self::check_dokuman( $post_id, $data, $postarr );
			case 'mb_referans':
				return self::check_referans( $post_id, $data, $postarr );
			case 'mb_lokasyon':
				return self::check_lokasyon( $post_id, $data, $postarr );
			case 'mb_sss':
				return self::check_sss( $post_id, $data, $postarr );
			default:
				return array();
		}
	}

	/* ------------------------------------------------------------------ *
	 * Shared field-resolution helpers.
	 * ------------------------------------------------------------------ */

	private static function title_from( array $data, $post_id ) {
		if ( isset( $data['post_title'] ) ) {
			return trim( wp_strip_all_tags( $data['post_title'] ) );
		}
		return $post_id ? trim( wp_strip_all_tags( get_the_title( $post_id ) ) ) : '';
	}

	private static function content_from( array $data, $post_id ) {
		if ( isset( $data['post_content'] ) ) {
			return trim( wp_strip_all_tags( $data['post_content'] ) );
		}
		return $post_id ? trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ) : '';
	}

	/**
	 * Resolves one required scalar meta field via
	 * MaviBelge_Core_Field_Repository::resolve_effective_value() and
	 * reduces it to "is this field ready" — present, valid per the same
	 * rules the real save applies (format, enum membership, referenced
	 * post existing, etc.), and non-empty.
	 */
	private static function required_field_ready( $post_type, $meta_key, $post_id, array $postarr ) {
		$fields = MaviBelge_Core_Meta_Schema::get_fields_for( $post_type );
		if ( ! isset( $fields[ $meta_key ] ) ) {
			return false;
		}
		$resolved = MaviBelge_Core_Field_Repository::resolve_effective_value( $meta_key, $fields[ $meta_key ], $post_id, $postarr );
		if ( ! $resolved['valid'] ) {
			return false;
		}
		$value = $resolved['value'];
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}
		// String comparison covers both tiers uniformly: DB-sourced
		// values always come back as strings from get_post_meta() even
		// for originally-int fields, while request/meta_input-sourced
		// values may already be real PHP ints — (string) cast makes
		// both comparable the same way. "0" (unset ID/integer default)
		// counts as not-ready, same as an empty string.
		$value_str = trim( (string) $value );
		return '' !== $value_str && '0' !== $value_str;
	}

	/**
	 * Resolves one required scalar field the same way as above but
	 * returns the raw resolved value (for checks that need the actual
	 * value, not just a ready/not-ready boolean — e.g. haber's approval
	 * status must specifically equal 'approved').
	 */
	private static function resolve_value( $post_type, $meta_key, $post_id, array $postarr ) {
		$fields = MaviBelge_Core_Meta_Schema::get_fields_for( $post_type );
		if ( ! isset( $fields[ $meta_key ] ) ) {
			return array( 'value' => null, 'valid' => false );
		}
		return MaviBelge_Core_Field_Repository::resolve_effective_value( $meta_key, $fields[ $meta_key ], $post_id, $postarr );
	}

	/**
	 * mb_sektor is hierarchical and uses WordPress's default category-
	 * style meta box (no custom meta_box_cb registered for it — see
	 * includes/class-taxonomies.php). That meta box's "+ Add New" inline
	 * term creation always happens via a SEPARATE prior AJAX request
	 * (the 'add-tag' admin-ajax action) that returns the new term_id,
	 * which is then submitted as an ordinary checked checkbox in
	 * tax_input on the actual post save. So by the time THIS filter
	 * runs (inside the post-save wp_insert_post() call), every ID in
	 * tax_input['mb_sektor'] already refers to a real, existing term —
	 * the "new term created in the very same request" race the brief
	 * flags as a possibly-unresolved flow does NOT apply here. (It
	 * would apply to a non-hierarchical, comma-text taxonomy — this
	 * plugin has one, mb_haber_turu, but no readiness check consults
	 * it, so the race is moot for every check in this file.)
	 *
	 * This is a documented assumption, not something exercised by an
	 * automated test in this phase — seeing it hold would require a
	 * real WordPress admin session driving the category meta box UI.
	 */
	private static function has_sector_term( array $postarr, $post_id ) {
		$candidate_ids = null;

		if ( isset( $postarr['tax_input']['mb_sektor'] ) ) {
			$candidate_ids = $postarr['tax_input']['mb_sektor'];
		} elseif ( isset( $_POST['tax_input']['mb_sektor'] ) ) {
			$candidate_ids = wp_unslash( $_POST['tax_input']['mb_sektor'] );
		}

		if ( null !== $candidate_ids ) {
			if ( ! is_array( $candidate_ids ) ) {
				return false; // Not the expected checkbox-array shape.
			}
			foreach ( $candidate_ids as $candidate ) {
				$candidate_str = is_int( $candidate ) ? (string) $candidate : $candidate;
				if ( ! MaviBelge_Core_Validator::is_valid_positive_integer_string( $candidate_str ) ) {
					continue;
				}
				$term_id = (int) trim( (string) $candidate_str );
				$term    = get_term( $term_id, 'mb_sektor' );
				if ( $term && ! is_wp_error( $term ) ) {
					return true; // At least one real, existing mb_sektor term.
				}
			}
			return false;
		}

		if ( $post_id ) {
			$terms = wp_get_object_terms( $post_id, 'mb_sektor', array( 'fields' => 'ids' ) );
			return ! is_wp_error( $terms ) && ! empty( $terms );
		}

		return false;
	}

	/* ------------------------------------------------------------------ *
	 * Per-content-type checks.
	 * ------------------------------------------------------------------ */

	private static function check_yeterlilik( $post_id, array $data, array $postarr ) {
		$problems = array();

		if ( '' === self::title_from( $data, $post_id ) ) {
			$problems[] = 'Yeterlilik yayınlamak için başlık gerekir.';
		}

		if ( ! self::required_field_ready( 'mb_yeterlilik', '_mb_level', $post_id, $postarr ) ) {
			$problems[] = 'Yeterlilik yayınlamak için geçerli bir seviye (1-8) gerekir.';
		}

		if ( ! self::has_sector_term( $postarr, $post_id ) ) {
			$problems[] = 'Yeterlilik yayınlamak için en az bir gerçek sektör terimi seçilmelidir.';
		}

		// MYK kodu opsiyoneldir — boşsa hiç kontrol edilmez. DOLU ise,
		// koda gömülü seviye/revizyonun _mb_level/_mb_revision ile
		// eşleştiğini AYNI paylaşılan kuralla doğrula (admin kaydının
		// kullandığı MaviBelge_Core_Validator::myk_code_matches_level_revision()
		// — ikinci bir regex kopyası yok, bkz. class-meta-boxes.php).
		$code_resolved = self::resolve_value( 'mb_yeterlilik', '_mb_myk_code', $post_id, $postarr );
		$code_value    = $code_resolved['valid'] ? trim( (string) $code_resolved['value'] ) : '';
		if ( '' !== $code_value ) {
			$level_resolved    = self::resolve_value( 'mb_yeterlilik', '_mb_level', $post_id, $postarr );
			$revision_resolved = self::resolve_value( 'mb_yeterlilik', '_mb_revision', $post_id, $postarr );
			$level_value       = $level_resolved['valid'] ? $level_resolved['value'] : null;
			$revision_value    = $revision_resolved['valid'] ? $revision_resolved['value'] : '';
			if ( ! MaviBelge_Core_Validator::myk_code_matches_level_revision( $code_value, $level_value, $revision_value ) ) {
				$problems[] = 'Yeterlilik yayınlamak için MYK koduna gömülü seviye/revizyon, Seviye/Revizyon alanlarıyla uyuşmalıdır.';
			}
		}

		return $problems;
	}

	private static function check_ucret( $post_id, array $data, array $postarr ) {
		$problems = array();

		if ( ! self::required_field_ready( 'mb_ucret', '_mb_profession_name', $post_id, $postarr ) ) {
			$problems[] = 'Ücret kaydı yayınlamak/aktif kullanım için meslek adı gerekir.';
		}

		if ( ! self::required_field_ready( 'mb_ucret', '_mb_level', $post_id, $postarr ) ) {
			$problems[] = 'Ücret kaydı yayınlamak/aktif kullanım için geçerli bir seviye (1-8) gerekir.';
		}

		// _mb_sector_slug: resolver applies the same 'slug' format rule
		// the real save does — not just "non-empty".
		if ( ! self::required_field_ready( 'mb_ucret', '_mb_sector_slug', $post_id, $postarr ) ) {
			$problems[] = 'Ücret kaydı yayınlamak/aktif kullanım için geçerli biçimde bir sektör slug gerekir.';
		}

		if ( ! self::required_field_ready( 'mb_ucret', '_mb_tariff_period', $post_id, $postarr ) ) {
			$problems[] = 'Ücret kaydı yayınlamak/aktif kullanım için tarife dönemi gerekir.';
		}

		// _mb_pricing_type: resolver enforces select-option (enum)
		// membership via the same schema options list the save handler
		// uses (MaviBelge_Core_Meta_Schema PRICING_TYPES), not just
		// "non-empty".
		if ( ! self::required_field_ready( 'mb_ucret', '_mb_pricing_type', $post_id, $postarr ) ) {
			$problems[] = 'Ücret kaydı yayınlamak/aktif kullanım için listeden geçerli bir fiyatlandırma türü gerekir.';
		}

		if ( ! self::ucret_price_options_ready( $post_id, $postarr ) ) {
			$problems[] = 'Ücret kaydı yayınlamak/aktif kullanım için gönderilen tüm fiyat seçenekleri geçerli olmalı ve en az biri bulunmalıdır.';
		}

		// _mb_qualification_id may legitimately be 0 — not checked.

		return $problems;
	}

	/**
	 * Three-tier resolution mirroring resolve_effective_value()'s
	 * priority, but for the price-options REPEATER (which isn't a
	 * simple scalar, so it gets its own tiering instead of going
	 * through resolve_effective_value()):
	 *
	 * 1. $_POST['_mb_price_options'] present (admin form submit, raw
	 *    label/amount_try/units shape) → the SAME
	 *    MaviBelge_Core_Validator::evaluate_admin_price_rows() the real
	 *    save uses. ALL submitted non-blank rows must be valid
	 *    (brief: "en az bir geçerli seçenek yetmez; ... tamamı geçerli
	 *    olmalı") and at least one option must result.
	 * 2. $postarr['meta_input']['_mb_price_options'] present — treated
	 *    as the plugin's own already-normalized DB shape (label/units/
	 *    amount_kurus/sort_order, kuruş already computed — this is the
	 *    documented contract for a programmatic writer, e.g. the Faz 6
	 *    import, which is expected to compute kuruş itself rather than
	 *    submit human TL-formatted strings). Run through
	 *    normalize_price_options() for the same deep-clean/limits pass.
	 * 3. Otherwise, the already-stored postmeta array (trusted, as it
	 *    can only have gotten there by passing one of the above).
	 */
	private static function ucret_price_options_ready( $post_id, array $postarr ) {
		if ( array_key_exists( '_mb_price_options', $_POST ) ) {
			$raw  = wp_unslash( $_POST['_mb_price_options'] );
			$eval = MaviBelge_Core_Validator::evaluate_admin_price_rows( $raw );
			return $eval['replace'] && ! empty( $eval['options'] );
		}

		if ( isset( $postarr['meta_input'] ) && is_array( $postarr['meta_input'] ) && array_key_exists( '_mb_price_options', $postarr['meta_input'] ) ) {
			$raw    = $postarr['meta_input']['_mb_price_options'];
			$result = MaviBelge_Core_Validator::normalize_price_options( is_array( $raw ) ? $raw : array() );
			return empty( $result['errors'] ) && ! empty( $result['options'] );
		}

		if ( $post_id ) {
			$stored = get_post_meta( $post_id, '_mb_price_options', true );
			return is_array( $stored ) && ! empty( $stored );
		}

		return false;
	}

	private static function check_haber( $post_id, array $data, array $postarr ) {
		$problems = array();

		if ( '' === self::title_from( $data, $post_id ) ) {
			$problems[] = 'Haber yayınlamak için başlık gerekir.';
		}
		if ( '' === self::content_from( $data, $post_id ) ) {
			$problems[] = 'Haber yayınlamak için içerik gerekir.';
		}

		$resolved = self::resolve_value( 'mb_haber', '_mb_approval_status', $post_id, $postarr );
		if ( ! $resolved['valid'] || 'approved' !== $resolved['value'] ) {
			$problems[] = 'Haber yayınlamak için onay durumu "Onaylandı" olmalıdır.';
		}

		return $problems;
	}

	private static function check_dokuman( $post_id, array $data, array $postarr ) {
		$problems = array();

		if ( '' === self::title_from( $data, $post_id ) ) {
			$problems[] = 'Doküman yayınlamak için başlık gerekir.';
		}

		if ( ! self::required_field_ready( 'mb_dokuman', '_mb_attachment_id', $post_id, $postarr ) ) {
			$problems[] = 'Doküman yayınlamak için geçerli bir dosya eki (attachment ID) gerekir.';
		}

		return $problems;
	}

	private static function check_referans( $post_id, array $data, array $postarr ) {
		$problems = array();

		if ( '' === self::title_from( $data, $post_id ) ) {
			$problems[] = 'Referans yayınlamak için başlık gerekir.';
		}

		if ( ! self::required_field_ready( 'mb_referans', '_mb_logo_attachment_id', $post_id, $postarr ) ) {
			$problems[] = 'Referans yayınlamak için logo (medya) gerekir.';
		}

		// _mb_reference_status defaults to 'representative' in the
		// schema; publishing does not require it to be explicitly 'real'.

		return $problems;
	}

	private static function check_lokasyon( $post_id, array $data, array $postarr ) {
		$problems = array();

		if ( '' === self::title_from( $data, $post_id ) ) {
			$problems[] = 'Lokasyon yayınlamak için başlık gerekir.';
		}

		if ( ! self::required_field_ready( 'mb_lokasyon', '_mb_address', $post_id, $postarr ) ) {
			$problems[] = 'Lokasyon yayınlamak için adres gerekir.';
		}

		return $problems;
	}

	private static function check_sss( $post_id, array $data, array $postarr ) {
		$problems = array();

		if ( '' === self::title_from( $data, $post_id ) ) {
			$problems[] = 'SSS yayınlamak için soru (başlık) gerekir.';
		}
		if ( '' === self::content_from( $data, $post_id ) ) {
			$problems[] = 'SSS yayınlamak için cevap (içerik) gerekir.';
		}

		return $problems;
	}
}
