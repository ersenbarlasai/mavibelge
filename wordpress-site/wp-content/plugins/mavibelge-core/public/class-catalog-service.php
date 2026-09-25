<?php
/**
 * Faz 5 — the ONE public catalog service surface a theme (or any other
 * consumer) is allowed to call for meslek/sektör/ücret data. The theme
 * must never build its own WP_Query/meta_query against mb_yeterlilik's
 * price relationship or mb_ucret directly (see AGENTS.md / brief §7) —
 * every rule in docs/catalog-service-contract.md (visibility, active
 * tariff period, date window, price-options validity) lives here, in
 * exactly one place.
 *
 * Every public method is fatal-safe: if mavibelge-core's post
 * types/taxonomy are not registered (plugin inactive, or a future
 * schema change), each method returns its documented empty/safe shape
 * instead of calling an undefined WordPress query on a non-existent
 * post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Catalog_Service {

	/**
	 * Upper bound on how many mb_yeterlilik / mb_ucret rows a single
	 * internal candidate query ever fetches before this class applies
	 * its own PHP-side filtering (free-text search, date-window and
	 * price-options validity — none of which are safe/simple to express
	 * as a single WP_Query meta_query). This is a real, small, fixed
	 * number — never -1/unbounded — chosen against the real, documented
	 * scale of this catalog (103 ücret / 83 yeterlilik kayıt, bkz.
	 * docs/content-model.md "Yeniden hesaplanan sayılar"). If a future
	 * phase's real data ever approaches this cap, that phase must
	 * revisit this constant and the pagination strategy together.
	 */
	const CANDIDATE_CAP = 500;

	public static function get_sector_terms() {
		if ( ! taxonomy_exists( 'mb_sektor' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'mb_sektor',
				'hide_empty' => false,
				'number'     => 40,
				'orderby'    => 'name',
			)
		);
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * @param array $raw_filters $_GET-shaped array using the mb_q/mb_sector/mb_level/mb_priced/mb_page keys.
	 * @return array{items: array, total: int, total_pages: int, page: int, page_size: int, filters: array}
	 */
	public static function get_qualification_results( array $raw_filters ) {
		$filters = MaviBelge_Core_Catalog_Query::normalize_filters( $raw_filters );

		if ( ! post_type_exists( 'mb_yeterlilik' ) ) {
			return self::empty_qualification_results( $filters );
		}

		$sector_resolution = self::resolve_sector_filter( $filters['sector'] );
		if ( ! $sector_resolution['valid'] ) {
			return self::empty_qualification_results( $filters );
		}
		$sector_term = $sector_resolution['term'];

		$post__in = null;
		if ( $filters['priced'] ) {
			$post__in = self::get_active_fee_qualification_ids();
			if ( empty( $post__in ) ) {
				return self::empty_qualification_results( $filters );
			}
		}

		// Faz 10: ağır aday sorgusu kısa ömürlü önbellekte. Serbest metin (q) araması ve istenenden farklı (sıkıştırılmış) sayfa
		// ASLA saklanmaz — anahtar uzayı sınırlı kalır; sektör yukarıda var olduğu doğrulandı.
		return MaviBelge_Core_Cache::remember(
			'qualifications',
			$filters['page'],
			array(
				'q'      => $filters['q'],
				'sector' => $filters['sector'],
				'level'  => $filters['level'],
				'priced' => $filters['priced'],
				'day'    => current_time( 'Y-m-d' ),
			),
			function () use ( $filters, $sector_term, $post__in ) {
				return self::build_qualification_results( $filters, $sector_term, $post__in );
			},
			function ( $fresh ) use ( $filters ) {
				return '' === $filters['q'] && isset( $fresh['page'] ) && $fresh['page'] === $filters['page'];
			}
		);
	}

	private static function build_qualification_results( array $filters, $sector_term, $post__in ) {
		$query_args = array(
			'post_type'      => 'mb_yeterlilik',
			'post_status'    => 'publish',
			'posts_per_page' => self::CANDIDATE_CAP,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => '_mb_record_status',
					'value' => 'active',
				),
			),
		);

		if ( '' !== $filters['level'] ) {
			$query_args['meta_query'][] = array(
				'key'   => '_mb_level',
				'value' => $filters['level'],
			);
		}

		if ( $sector_term ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'mb_sektor',
					'field'    => 'term_id',
					'terms'    => array( $sector_term->term_id ),
				),
			);
		}

		if ( null !== $post__in ) {
			$query_args['post__in'] = $post__in;
		}

		$query = new WP_Query( $query_args );

		$matched = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$myk_code = (string) get_post_meta( $post->ID, '_mb_myk_code', true );
				if ( '' !== $filters['q']
					&& ! MaviBelge_Core_Catalog_Query::text_contains_ci( $post->post_title, $filters['q'] )
					&& ! MaviBelge_Core_Catalog_Query::text_contains_ci( $myk_code, $filters['q'] )
				) {
					continue;
				}
				$matched[] = self::build_qualification_dto( $post, $myk_code );
			}
		}
		wp_reset_postdata();

		$total       = count( $matched );
		$page_size   = MaviBelge_Core_Catalog_Query::DEFAULT_PAGE_SIZE;
		$total_pages = max( 1, (int) ceil( $total / $page_size ) );
		$page        = MaviBelge_Core_Catalog_Query::clamp_page( $filters['page'], $total_pages );
		$offset      = ( $page - 1 ) * $page_size;
		$items       = array_slice( $matched, $offset, $page_size );

		return array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => $total_pages,
			'page'        => $page,
			'page_size'   => $page_size,
			'filters'     => $filters,
		);
	}

	/**
	 * Faz 5 Düzeltme ve Kabul §2.3 — the ONE place that decides whether a
	 * requested sector-slug filter is usable. Both
	 * get_qualification_results() (real mb_sektor taxonomy relation) and
	 * get_active_fee_results() (mb_ucret's free-text _mb_sector_slug
	 * field) now share this same validation: a format-valid but
	 * NON-EXISTENT sector must produce zero results, never a silently
	 * ignored filter (which would show everything) and never a match
	 * against a stale/typo'd meta value that happens to equal the slug
	 * text with no real term behind it.
	 *
	 * @return array{valid: bool, term: WP_Term|null}
	 */
	private static function resolve_sector_filter( $sector_slug ) {
		if ( '' === $sector_slug ) {
			return array( 'valid' => true, 'term' => null );
		}
		if ( ! taxonomy_exists( 'mb_sektor' ) ) {
			return array( 'valid' => false, 'term' => null );
		}
		$found = get_term_by( 'slug', $sector_slug, 'mb_sektor' );
		if ( ! $found || is_wp_error( $found ) ) {
			return array( 'valid' => false, 'term' => null );
		}
		return array( 'valid' => true, 'term' => $found );
	}

	private static function empty_qualification_results( array $filters ) {
		return array(
			'items'       => array(),
			'total'       => 0,
			'total_pages' => 1,
			'page'        => 1,
			'page_size'   => MaviBelge_Core_Catalog_Query::DEFAULT_PAGE_SIZE,
			'filters'     => $filters,
		);
	}

	private static function build_qualification_dto( $post, $myk_code ) {
		$level        = (string) get_post_meta( $post->ID, '_mb_level', true );
		$sector_terms = array();
		if ( taxonomy_exists( 'mb_sektor' ) ) {
			$terms = get_the_terms( $post->ID, 'mb_sektor' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_link    = get_term_link( $term );
					$sector_terms[] = array(
						'name' => $term->name,
						'slug' => $term->slug,
						'url'  => is_wp_error( $term_link ) ? '' : $term_link,
					);
				}
			}
		}
		return array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'permalink' => get_permalink( $post ),
			'myk_code'  => $myk_code,
			'level'     => $level,
			'excerpt'   => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'sectors'   => $sector_terms,
		);
	}

	/**
	 * @param array $raw_filters Only mb_q/mb_sector/mb_level are consulted — every row this method returns is already "priced" by definition, so mb_priced is ignored here.
	 * @return array{items: array, total: int, total_pages: int, page: int, page_size: int, filters: array}
	 */
	public static function get_active_fee_results( array $raw_filters ) {
		$filters = MaviBelge_Core_Catalog_Query::normalize_filters( $raw_filters );

		// Faz 5 Düzeltme ve Kabul §2.3: a sector filter must name a REAL
		// mb_sektor term, exactly like get_qualification_results() — a
		// format-valid-but-nonexistent slug shows zero rows, never every
		// row (filter silently ignored) and never a coincidental meta
		// string match with no real term behind it.
		$sector_resolution = self::resolve_sector_filter( $filters['sector'] );
		if ( ! $sector_resolution['valid'] ) {
			return self::empty_fee_results( $filters );
		}

		$fees    = self::get_all_valid_active_fees();

		$matched = array();
		foreach ( $fees as $fee ) {
			if ( '' !== $filters['sector'] && $fee['sector_slug'] !== $filters['sector'] ) {
				continue;
			}
			if ( '' !== $filters['level'] && $fee['level'] !== $filters['level'] ) {
				continue;
			}
			if ( '' !== $filters['q']
				&& ! MaviBelge_Core_Catalog_Query::text_contains_ci( $fee['profession_name'], $filters['q'] )
				&& ! MaviBelge_Core_Catalog_Query::text_contains_ci( $fee['qualification_code'], $filters['q'] )
			) {
				continue;
			}
			$matched[] = $fee;
		}

		usort(
			$matched,
			function ( $a, $b ) {
				$cmp = strnatcasecmp( $a['profession_name'], $b['profession_name'] );
				return 0 !== $cmp ? $cmp : ( $a['id'] - $b['id'] );
			}
		);

		$total       = count( $matched );
		$page_size   = MaviBelge_Core_Catalog_Query::DEFAULT_PAGE_SIZE;
		$total_pages = max( 1, (int) ceil( $total / $page_size ) );
		$page        = MaviBelge_Core_Catalog_Query::clamp_page( $filters['page'], $total_pages );
		$offset      = ( $page - 1 ) * $page_size;

		return array(
			'items'       => array_slice( $matched, $offset, $page_size ),
			'total'       => $total,
			'total_pages' => $total_pages,
			'page'        => $page,
			'page_size'   => $page_size,
			'filters'     => $filters,
		);
	}

	private static function empty_fee_results( array $filters ) {
		return array(
			'items'       => array(),
			'total'       => 0,
			'total_pages' => 1,
			'page'        => 1,
			'page_size'   => MaviBelge_Core_Catalog_Query::DEFAULT_PAGE_SIZE,
			'filters'     => $filters,
		);
	}

	/**
	 * Only valid, currently-active fees whose real, doğrulanmış
	 * _mb_qualification_id matches $qualification_id — never a
	 * name/code-similarity fallback (see brief §4 "ad benzerliğiyle ...
	 * ilişki kurulmaz").
	 *
	 * @return array list of fee DTOs (usually 0 or 1, never fabricated).
	 */
	public static function get_active_fees_for_qualification( $qualification_id ) {
		$qualification_id = (int) $qualification_id;
		if ( $qualification_id <= 0 ) {
			return array();
		}
		$matched = array();
		foreach ( self::get_all_valid_active_fees() as $fee ) {
			if ( $fee['qualification_id'] === $qualification_id ) {
				$matched[] = $fee;
			}
		}
		return $matched;
	}

	public static function get_active_tariff_period() {
		return trim( (string) get_option( 'mb_active_tariff_period', '' ) );
	}

	/**
	 * All mb_ucret records that currently pass EVERY §5 visibility rule:
	 * publish, _mb_record_status=active, tariff period equal to the
	 * single active option, today within [valid_from, valid_until], and
	 * a non-empty, fully-valid _mb_price_options list. No caching across
	 * requests — the dataset this phase targets is small (bkz.
	 * CANDIDATE_CAP docblock) and correctness matters far more here than
	 * micro-optimizing a query that currently returns zero rows (no
	 * fiyat verisi henüz aktarılmadı, Faz 6).
	 *
	 * @return array<int, array> fee DTOs, keyed 0..n (not by post ID).
	 */
	private static function get_all_valid_active_fees() {
		if ( ! post_type_exists( 'mb_ucret' ) ) {
			return array();
		}
		$active_period = self::get_active_tariff_period();
		if ( '' === $active_period ) {
			return array();
		}
		// Faz 10: yalnız bu ağır iç sorgu (aday sorgusu + kayıt başına doğrulama) önbelleğe alınır; geçerlilik penceresi
		// tarihe bağlı olduğundan gün ve aktif dönem anahtara girer. Nesil, ücret/dönem değişince artar.
		return MaviBelge_Core_Cache::remember(
			'fees',
			1,
			array( 'period' => $active_period, 'day' => current_time( 'Y-m-d' ) ),
			function () use ( $active_period ) {
				return self::build_all_valid_active_fees( $active_period );
			}
		);
	}

	private static function build_all_valid_active_fees( $active_period ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'mb_ucret',
				'post_status'    => 'publish',
				'posts_per_page' => self::CANDIDATE_CAP,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_mb_record_status',
						'value' => 'active',
					),
					array(
						'key'   => '_mb_tariff_period',
						'value' => $active_period,
					),
				),
			)
		);

		$today = current_time( 'Y-m-d' );
		$fees  = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				// Faz 5 Düzeltme ve Kabul §3.1 (catalog-service-contract.md
				// tutarlılığı): the meta_query above already narrows to
				// _mb_tariff_period = $active_period at the DB level, but
				// that is a separate mechanism from the documented pure
				// decision function — re-confirm the SAME rule through
				// MaviBelge_Core_Catalog_Query::is_period_match() here so
				// the documented contract and the actual runtime path are
				// never two different implementations of "dönem eşleşmesi".
				$tariff_period = get_post_meta( $post->ID, '_mb_tariff_period', true );
				if ( ! MaviBelge_Core_Catalog_Query::is_period_match( $tariff_period, $active_period ) ) {
					continue;
				}

				$valid_from    = get_post_meta( $post->ID, '_mb_valid_from', true );
				$valid_until   = get_post_meta( $post->ID, '_mb_valid_until', true );
				$price_options = get_post_meta( $post->ID, '_mb_price_options', true );

				if ( ! MaviBelge_Core_Catalog_Query::is_within_validity_window( $valid_from, $valid_until, $today ) ) {
					continue;
				}

				// Faz 5 Düzeltme ve Kabul §2.1: canonicalize ONCE here —
				// has_priced_options() and canonicalize_price_options() now
				// share the exact same underlying validator, so this single
				// call both decides visibility (empty = invalid = hidden)
				// and produces the canonically-sorted list present_fee()
				// will render, with no second/weaker check anywhere else.
				$canonical_options = MaviBelge_Core_Catalog_Query::canonicalize_price_options( $price_options );
				if ( empty( $canonical_options ) ) {
					continue;
				}

				/*
				 * Faz 5 Son Kapanış Düzeltmesi §3 — RECORD sector integrity
				 * (distinct from resolve_sector_filter(), which validates a
				 * requested FILTER value, not a record's own field — see
				 * docs/catalog-service-contract.md §3.2 for that
				 * separation). A ücret record's own _mb_sector_slug must be
				 * format-valid AND resolve to a real, currently-existing
				 * mb_sektor term, or the record fails closed here — it
				 * never reaches the public with an empty/unresolvable
				 * sector, and its sector_name is never the raw meta string.
				 */
				$sector_slug = (string) get_post_meta( $post->ID, '_mb_sector_slug', true );
				$sector_name = self::resolve_sector_name_or_null( $sector_slug );
				if ( null === $sector_name ) {
					continue;
				}

				$fees[] = self::build_fee_dto( $post, $canonical_options, $sector_slug, $sector_name );
			}
		}
		wp_reset_postdata();

		return $fees;
	}

	private static function build_fee_dto( $post, array $canonical_price_options, $sector_slug, $sector_name ) {
		$qualification_id = (int) get_post_meta( $post->ID, '_mb_qualification_id', true );
		return array(
			'id'                        => $post->ID,
			'profession_name'           => (string) get_post_meta( $post->ID, '_mb_profession_name', true ),
			'qualification_code'        => (string) get_post_meta( $post->ID, '_mb_qualification_code', true ),
			'qualification_id'          => $qualification_id,
			'level'                     => (string) get_post_meta( $post->ID, '_mb_level', true ),
			'sector_slug'               => $sector_slug,
			// Faz 5 Son Kapanış Düzeltmesi §3: resolved HERE, once, by the
			// CALLER (get_all_valid_active_fees(), which already fails the
			// record closed if this can't resolve to a real term) — the
			// theme must never re-resolve a taxonomy term from this
			// free-text meta field itself. This is always a real term
			// name at this point, never the raw slug (see
			// resolve_sector_name_or_null()'s own docblock).
			'sector_name'               => $sector_name,
			'tariff_period'             => (string) get_post_meta( $post->ID, '_mb_tariff_period', true ),
			'pricing_type'              => (string) get_post_meta( $post->ID, '_mb_pricing_type', true ),
			'vat_included'              => (bool) get_post_meta( $post->ID, '_mb_vat_included', true ),
			'certificate_print_fee_kurus' => (int) get_post_meta( $post->ID, '_mb_certificate_print_fee_kurus', true ),
			'source_name'               => (string) get_post_meta( $post->ID, '_mb_source_name', true ),
			'source_page'               => (int) get_post_meta( $post->ID, '_mb_source_page', true ),
			'source_attachment_id'      => (int) get_post_meta( $post->ID, '_mb_source_attachment_id', true ),
			'price_options'             => $canonical_price_options,
			'min_amount_kurus'          => (int) get_post_meta( $post->ID, '_mb_min_amount_kurus', true ),
			'max_amount_kurus'          => (int) get_post_meta( $post->ID, '_mb_max_amount_kurus', true ),
		);
	}

	/**
	 * In-request cache (slug => name) of every real mb_sektor term,
	 * built once per request and reused for every ücret row — Faz 5 Son
	 * Kapanış Düzeltmesi §3.4: avoids one get_term_by() query per fee
	 * row (get_sector_terms() itself is already a single, bounded
	 * get_terms() call; this just avoids repeating THAT call too).
	 *
	 * @var array<string,string>|null
	 */
	private static $sector_slug_to_name = null;

	private static function sector_slug_to_name_map() {
		if ( null === self::$sector_slug_to_name ) {
			$map = array();
			foreach ( self::get_sector_terms() as $term ) {
				$map[ $term->slug ] = $term->name;
			}
			self::$sector_slug_to_name = $map;
		}
		return self::$sector_slug_to_name;
	}

	/**
	 * Faz 5 Son Kapanış Düzeltmesi §3 — the ONE place a ücret record's
	 * own _mb_sector_slug is resolved to a display name. Returns the
	 * real term's name, or `null` if the slug is empty, format-invalid,
	 * or does not resolve to any currently-existing mb_sektor term —
	 * NEVER the raw slug as a fallback. Callers (get_all_valid_active_fees())
	 * treat a `null` return as "this record fails closed", not as "show
	 * the slug text anyway".
	 *
	 * This is deliberately separate from resolve_sector_filter(), which
	 * validates a requested FILTER value (empty is a valid "no filter"
	 * state there) — this method validates a RECORD's own field, where
	 * empty is never acceptable (see docs/catalog-service-contract.md §3.2).
	 *
	 * @return string|null
	 */
	private static function resolve_sector_name_or_null( $sector_slug ) {
		$normalized = MaviBelge_Core_Catalog_Query::normalize_sector_slug( (string) $sector_slug );
		if ( '' === $normalized ) {
			return null;
		}
		$map = self::sector_slug_to_name_map();
		return isset( $map[ $normalized ] ) ? $map[ $normalized ] : null;
	}

	/** @return array<int,int> distinct, real (>0) mb_yeterlilik IDs that currently have at least one valid active fee. */
	public static function get_active_fee_qualification_ids() {
		$ids = array();
		foreach ( self::get_all_valid_active_fees() as $fee ) {
			if ( $fee['qualification_id'] > 0 ) {
				$ids[] = $fee['qualification_id'];
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Faz 5 Düzeltme ve Kabul §2.4: the TOTAL count of currently-visible
	 * active fees, regardless of whether each one is linked to a real
	 * mb_yeterlilik (_mb_qualification_id may legitimately be 0 — brief
	 * §2 "eşleşme yoksa 0 bırakılabilir"). admin/class-settings.php's
	 * "no matching active fee" warning must use THIS, not
	 * get_active_fee_qualification_ids() — that method is scoped
	 * on purpose to the mb_priced yeterlilik filter's narrower need
	 * (only qualification-linked fees) and would wrongly warn "no active
	 * fee" while unlinked-but-otherwise-fully-valid fees exist and are
	 * genuinely visible on the Sınav Ücretleri page.
	 */
	public static function get_active_fee_count() {
		return count( self::get_all_valid_active_fees() );
	}

	/**
	 * Presentation helper: turns one fee DTO into template-ready display
	 * strings. Never queries anything — pure formatting of an already
	 * validated DTO.
	 *
	 * @return array{single_amount_display: string, options: array, vat_label: string, certificate_print_fee_display: string, source_url: string, qualification_permalink: string}
	 */
	public static function present_fee( array $fee ) {
		$options = array();
		foreach ( $fee['price_options'] as $row ) {
			$options[] = array(
				'label'           => isset( $row['label'] ) ? (string) $row['label'] : '',
				'units'           => isset( $row['units'] ) && is_array( $row['units'] ) ? $row['units'] : array(),
				'amount_display'  => MaviBelge_Core_Validator::kurus_to_lira_display( isset( $row['amount_kurus'] ) ? $row['amount_kurus'] : 0 ) . ' TL',
			);
		}

		$single_amount_display = 1 === count( $options ) ? $options[0]['amount_display'] : '';

		$certificate_fee_display = '';
		if ( $fee['certificate_print_fee_kurus'] > 0 ) {
			$certificate_fee_display = MaviBelge_Core_Validator::kurus_to_lira_display( $fee['certificate_print_fee_kurus'] ) . ' TL';
		}

		$source_url = '';
		if ( $fee['source_attachment_id'] > 0 && 'attachment' === get_post_type( $fee['source_attachment_id'] ) ) {
			$attachment_url = wp_get_attachment_url( $fee['source_attachment_id'] );
			$source_url     = $attachment_url ? $attachment_url : '';
		}

		// Faz 5 Düzeltme ve Kabul §2.5: a permalink is only ever offered for
		// a qualification that is genuinely public right now (published
		// AND _mb_record_status=active) — the same decision
		// MaviBelge_Core_Visibility_Guard::maybe_block_passive_qualification()
		// enforces at the actual URL. A passive-but-published qualification
		// must never get a "working" link handed to the public from here.
		$qualification_permalink = '';
		if ( $fee['qualification_id'] > 0 && class_exists( 'MaviBelge_Core_Visibility_Guard' )
			&& MaviBelge_Core_Visibility_Guard::is_public_qualification( $fee['qualification_id'] )
		) {
			$qualification_permalink = (string) get_permalink( $fee['qualification_id'] );
		}

		return array(
			'single_amount_display'         => $single_amount_display,
			'options'                       => $options,
			'vat_label'                     => $fee['vat_included'] ? __( 'KDV Dahil', 'mavibelge-core' ) : __( 'KDV Hariç', 'mavibelge-core' ),
			'certificate_print_fee_display' => $certificate_fee_display,
			'source_url'                    => $source_url,
			'qualification_permalink'       => $qualification_permalink,
		);
	}
}
