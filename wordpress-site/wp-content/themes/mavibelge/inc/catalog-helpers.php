<?php
/**
 * Faz 5 — thin, fatal-safe adapters over mavibelge-core's
 * MaviBelge_Core_Catalog_Service. No iş kuralı, no data validation, no
 * direct WP_Query/$wpdb against mb_yeterlilik/mb_ucret lives here — see
 * AGENTS.md / brief §7. Every function below is safe to call even when
 * mavibelge-core is inactive: it returns the same empty/documented
 * shape the service itself would.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mavibelge_catalog_available() {
	return class_exists( 'MaviBelge_Core_Catalog_Service' );
}

/** @return array<int, WP_Term> */
function mavibelge_get_sector_terms() {
	if ( ! mavibelge_catalog_available() ) {
		return array();
	}
	return MaviBelge_Core_Catalog_Service::get_sector_terms();
}

/** @return array{items: array, total: int, total_pages: int, page: int, page_size: int, filters: array} */
function mavibelge_get_qualification_results( array $raw_filters ) {
	if ( ! mavibelge_catalog_available() ) {
		return mavibelge_empty_catalog_results( $raw_filters );
	}
	return MaviBelge_Core_Catalog_Service::get_qualification_results( $raw_filters );
}

/** @return array{items: array, total: int, total_pages: int, page: int, page_size: int, filters: array} */
function mavibelge_get_active_fee_results( array $raw_filters ) {
	if ( ! mavibelge_catalog_available() ) {
		return mavibelge_empty_catalog_results( $raw_filters );
	}
	return MaviBelge_Core_Catalog_Service::get_active_fee_results( $raw_filters );
}

/** @return array */
function mavibelge_get_active_fees_for_qualification( $qualification_id ) {
	if ( ! mavibelge_catalog_available() ) {
		return array();
	}
	return MaviBelge_Core_Catalog_Service::get_active_fees_for_qualification( $qualification_id );
}

/** @return array */
function mavibelge_present_fee( array $fee ) {
	if ( ! mavibelge_catalog_available() ) {
		return array(
			'single_amount_display'         => '',
			'options'                       => array(),
			'vat_label'                     => '',
			'certificate_print_fee_display' => '',
			'source_url'                    => '',
			'qualification_permalink'       => '',
		);
	}
	return MaviBelge_Core_Catalog_Service::present_fee( $fee );
}

function mavibelge_active_tariff_period() {
	if ( ! mavibelge_catalog_available() ) {
		return '';
	}
	return MaviBelge_Core_Catalog_Service::get_active_tariff_period();
}

/**
 * Builds a real URL for one target page, keeping only the non-empty
 * filter query args (see MaviBelge_Core_Catalog_Query::filters_to_query_args())
 * plus mb_page — never a "#" placeholder.
 */
function mavibelge_catalog_page_url( $base_url, array $filters, $page ) {
	$args = class_exists( 'MaviBelge_Core_Catalog_Query' )
		? MaviBelge_Core_Catalog_Query::filters_to_query_args( $filters )
		: array();
	if ( $page > 1 ) {
		$args['mb_page'] = (string) $page;
	}
	if ( empty( $args ) ) {
		return $base_url;
	}
	return add_query_arg( $args, $base_url );
}

function mavibelge_empty_catalog_results( array $raw_filters ) {
	$filters = class_exists( 'MaviBelge_Core_Catalog_Query' )
		? MaviBelge_Core_Catalog_Query::normalize_filters( $raw_filters )
		: array( 'q' => '', 'sector' => '', 'level' => '', 'priced' => false, 'page' => 1 );
	return array(
		'items'       => array(),
		'total'       => 0,
		'total_pages' => 1,
		'page'        => 1,
		'page_size'   => 12,
		'filters'     => $filters,
	);
}
