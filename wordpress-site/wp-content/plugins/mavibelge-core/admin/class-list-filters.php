<?php
/**
 * Faz 5 — admin list filters for mb_yeterlilik and mb_ucret (brief §8.1
 * / §8.2): dönem, durum, sektör, seviye and fiyatlandırma türü dropdown
 * filters, plus a meslek-adı/MYK-kodu-aware admin search.
 *
 * Deliberately does NOT use raw SQL/$wpdb to combine "title OR meta"
 * search (brief §10 allows raw SQL only as a last resort, prepared and
 * escaped). Instead: for our two post types only, on the admin list
 * screen only, this fetches the bounded candidate ID set WordPress's
 * own query already narrows by the dropdown filters, then applies the
 * same text_contains_ci() free-text match the front-end catalog
 * service uses (title OR the relevant meta field) in PHP, and hands
 * WordPress a plain post__in — no custom SQL, no new attack surface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_List_Filters {

	const RECORD_STATUS_OPTIONS = array(
		'mb_yeterlilik' => array( 'active' => 'Aktif', 'passive' => 'Pasif' ),
		'mb_ucret'      => array( 'draft' => 'Taslak', 'active' => 'Aktif', 'archived' => 'Arşivlendi' ),
	);

	const PRICING_TYPE_OPTIONS = array(
		'single'   => 'Tek fiyat',
		'unit'     => 'Birim bazlı',
		'package'  => 'Paket',
		'multiple' => 'Çok seçenekli',
	);

	/** Search bound — same order of magnitude as MaviBelge_Core_Catalog_Service::CANDIDATE_CAP, admin side. */
	const SEARCH_CANDIDATE_CAP = 500;

	public static function init() {
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters_to_query' ) );
	}

	private static function current_screen_post_type() {
		global $pagenow, $typenow;
		if ( 'edit.php' !== $pagenow ) {
			return '';
		}
		if ( in_array( $typenow, array( 'mb_yeterlilik', 'mb_ucret' ), true ) ) {
			return $typenow;
		}
		return '';
	}

	public static function render_filters( $post_type ) {
		if ( ! in_array( $post_type, array( 'mb_yeterlilik', 'mb_ucret' ), true ) ) {
			return;
		}

		$status_options = isset( self::RECORD_STATUS_OPTIONS[ $post_type ] ) ? self::RECORD_STATUS_OPTIONS[ $post_type ] : array();
		self::render_select( 'mb_filter_status', 'Tüm Durumlar', $status_options, isset( $_GET['mb_filter_status'] ) ? wp_unslash( $_GET['mb_filter_status'] ) : '' );

		$level_options = array_combine( range( 1, 8 ), range( 1, 8 ) );
		self::render_select( 'mb_filter_level', 'Tüm Seviyeler', $level_options, isset( $_GET['mb_filter_level'] ) ? wp_unslash( $_GET['mb_filter_level'] ) : '' );

		$sector_options = array();
		if ( taxonomy_exists( 'mb_sektor' ) ) {
			$terms = get_terms( array( 'taxonomy' => 'mb_sektor', 'hide_empty' => false, 'number' => 40, 'orderby' => 'name' ) );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$sector_options[ $term->slug ] = $term->name;
				}
			}
		}
		if ( 'mb_yeterlilik' === $post_type ) {
			self::render_select( 'mb_filter_sector', 'Tüm Sektörler', $sector_options, isset( $_GET['mb_filter_sector'] ) ? wp_unslash( $_GET['mb_filter_sector'] ) : '' );
		} else {
			// mb_ucret's sector is a free-text slug field (_mb_sector_slug), not a taxonomy relationship — see docs/content-model.md.
			self::render_select( 'mb_filter_sector_slug', 'Tüm Sektörler', $sector_options, isset( $_GET['mb_filter_sector_slug'] ) ? wp_unslash( $_GET['mb_filter_sector_slug'] ) : '' );
			self::render_select( 'mb_filter_pricing_type', 'Tüm Fiyatlandırma Türleri', self::PRICING_TYPE_OPTIONS, isset( $_GET['mb_filter_pricing_type'] ) ? wp_unslash( $_GET['mb_filter_pricing_type'] ) : '' );
			self::render_period_filter();
		}
	}

	private static function render_select( $name, $all_label, array $options, $current ) {
		$current = (string) $current;
		printf( '<label class="screen-reader-text" for="%1$s">%2$s</label>', esc_attr( $name ), esc_html( $all_label ) );
		printf( '<select name="%1$s" id="%1$s">', esc_attr( $name ) );
		printf( '<option value="">%s</option>', esc_html( $all_label ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, (string) $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * A real (non-forced) dropdown built from the DISTINCT tarife dönemi
	 * values already present in mb_ucret records — "gerçek kayıtlardan
	 * türetilmiş yardımcı öneri" (brief §8.3), never a hardcoded list.
	 * When no records exist yet, this simply renders no options beyond
	 * "Tüm Dönemler" — it never blocks or forces a choice.
	 */
	private static function render_period_filter() {
		$periods = array();
		if ( post_type_exists( 'mb_ucret' ) ) {
			$results = get_posts(
				array(
					'post_type'      => 'mb_ucret',
					'post_status'    => array( 'draft', 'publish', 'pending', 'private' ),
					'posts_per_page' => self::SEARCH_CANDIDATE_CAP,
					'fields'         => 'ids',
				)
			);
			foreach ( $results as $post_id ) {
				$period = trim( (string) get_post_meta( $post_id, '_mb_tariff_period', true ) );
				if ( '' !== $period ) {
					$periods[ $period ] = $period;
				}
			}
			ksort( $periods );
		}
		self::render_select( 'mb_filter_period', 'Tüm Dönemler', $periods, isset( $_GET['mb_filter_period'] ) ? wp_unslash( $_GET['mb_filter_period'] ) : '' );
	}

	public static function apply_filters_to_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$post_type = self::current_screen_post_type();
		if ( '' === $post_type ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );

		$status = isset( $_GET['mb_filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_filter_status'] ) ) : '';
		if ( isset( self::RECORD_STATUS_OPTIONS[ $post_type ][ $status ] ) ) {
			$meta_query[] = array( 'key' => '_mb_record_status', 'value' => $status );
		}

		$level = isset( $_GET['mb_filter_level'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_filter_level'] ) ) : '';
		if ( in_array( $level, array( '1', '2', '3', '4', '5', '6', '7', '8' ), true ) ) {
			$meta_query[] = array( 'key' => '_mb_level', 'value' => $level );
		}

		if ( 'mb_yeterlilik' === $post_type ) {
			$sector_slug = isset( $_GET['mb_filter_sector'] ) ? sanitize_title( wp_unslash( $_GET['mb_filter_sector'] ) ) : '';
			if ( '' !== $sector_slug ) {
				$tax_query = (array) $query->get( 'tax_query' );
				$tax_query[] = array( 'taxonomy' => 'mb_sektor', 'field' => 'slug', 'terms' => array( $sector_slug ) );
				$query->set( 'tax_query', $tax_query );
			}
		} else {
			$sector_slug = isset( $_GET['mb_filter_sector_slug'] ) ? sanitize_title( wp_unslash( $_GET['mb_filter_sector_slug'] ) ) : '';
			if ( '' !== $sector_slug ) {
				$meta_query[] = array( 'key' => '_mb_sector_slug', 'value' => $sector_slug );
			}

			$pricing_type = isset( $_GET['mb_filter_pricing_type'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_filter_pricing_type'] ) ) : '';
			if ( isset( self::PRICING_TYPE_OPTIONS[ $pricing_type ] ) ) {
				$meta_query[] = array( 'key' => '_mb_pricing_type', 'value' => $pricing_type );
			}

			$period = isset( $_GET['mb_filter_period'] ) ? sanitize_text_field( wp_unslash( $_GET['mb_filter_period'] ) ) : '';
			if ( '' !== $period ) {
				$meta_query[] = array( 'key' => '_mb_tariff_period', 'value' => $period );
			}
		}

		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}

		self::apply_meta_aware_search( $query, $post_type );
	}

	/**
	 * See class docblock. Only runs when a search term is present and
	 * this is one of our two post types' own admin list screen.
	 */
	private static function apply_meta_aware_search( $query, $post_type ) {
		// Faz 5 Düzeltme ve Kabul §3.5: check the shape BEFORE casting to
		// string (an array/object here would otherwise (string)-cast to
		// "Array"/a PHP notice and be searched for literally) and run it
		// through the same unslash step as the front-end GET contract.
		$raw_s = $query->get( 's' );
		if ( is_array( $raw_s ) || is_object( $raw_s ) ) {
			return;
		}
		$search_term = trim( function_exists( 'wp_unslash' ) ? wp_unslash( (string) $raw_s ) : (string) $raw_s );
		if ( '' === $search_term ) {
			return;
		}

		// Fetch the candidate set WordPress's own (already-applied above)
		// meta_query/tax_query filters narrow to, WITHOUT the 's' term —
		// then apply title-or-relevant-meta contains-match in PHP.
		$candidate_args              = $query->query_vars;
		$candidate_args['s']         = '';
		$candidate_args['fields']    = '';
		$candidate_args['posts_per_page'] = self::SEARCH_CANDIDATE_CAP;
		$candidate_args['no_found_rows']  = true;
		unset( $candidate_args['paged'] );

		$candidates = get_posts( $candidate_args );

		$matched = array( 0 ); // Guarantees a valid, always-empty post__in if nothing matches (never falls back to "show all").
		foreach ( $candidates as $post ) {
			$fields_to_check = array( $post->post_title );
			if ( 'mb_yeterlilik' === $post_type ) {
				$fields_to_check[] = get_post_meta( $post->ID, '_mb_myk_code', true );
			} else {
				$fields_to_check[] = get_post_meta( $post->ID, '_mb_profession_name', true );
				$fields_to_check[] = get_post_meta( $post->ID, '_mb_qualification_code', true );
			}
			foreach ( $fields_to_check as $field_value ) {
				if ( MaviBelge_Core_Catalog_Query::text_contains_ci( (string) $field_value, $search_term ) ) {
					$matched[] = $post->ID;
					break;
				}
			}
		}

		$query->set( 's', '' );
		$query->set( 'post__in', array_values( array_unique( $matched ) ) );
	}
}
