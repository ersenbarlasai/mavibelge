<?php
/**
 * Faz 5 Düzeltme ve Kabul §2.6 — renders ONE yeterlilik result card
 * straight from the catalog service's own DTO
 * (MaviBelge_Core_Catalog_Service::get_qualification_results()'s
 * 'items' shape: id/title/permalink/myk_code/level/excerpt/sectors[]).
 *
 * This does NOT call get_post()/get_post_meta()/get_the_terms() itself —
 * archive-mb_yeterlilik.php and taxonomy-mb_sektor.php used to hand
 * only $item['id'] to template-parts/content/content-card.php, which
 * then re-read the SAME title/MYK-code/level/sector data the service
 * had already resolved a moment earlier. content-card.php itself is
 * untouched (still used, unchanged, by archive-mb_haber.php,
 * archive-mb_dokuman.php, taxonomy.php and front-page.php's news
 * section) — this is a separate, catalog-only part.
 *
 * $args:
 * - item (array, required) — one element of get_qualification_results()['items']
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item = isset( $args['item'] ) && is_array( $args['item'] ) ? $args['item'] : null;
if ( null === $item ) {
	return;
}

$badges = array();
if ( ! empty( $item['myk_code'] ) ) {
	$badges[] = '<span class="badge badge-neutral">' . esc_html( $item['myk_code'] ) . '</span>';
}
if ( ! empty( $item['level'] ) ) {
	$badges[] = '<span class="badge">' . esc_html(
		sprintf(
			/* translators: %s: MYK qualification level, "1"-"8" */
			__( 'Seviye %s', 'mavibelge' ),
			$item['level']
		)
	) . '</span>';
}
if ( ! empty( $item['sectors'] ) && is_array( $item['sectors'] ) ) {
	foreach ( $item['sectors'] as $sector ) {
		$badges[] = '<span class="badge badge-neutral">' . esc_html( $sector['name'] ) . '</span>';
	}
}

$meta_html = $badges ? '<div class="card-badges">' . implode( ' ', $badges ) . '</div>' : '';

get_template_part(
	'template-parts/components/card',
	null,
	array(
		'title'        => $item['title'],
		'url'          => $item['permalink'],
		'content'      => isset( $item['excerpt'] ) ? $item['excerpt'] : '',
		'content_html' => $meta_html,
	)
);
