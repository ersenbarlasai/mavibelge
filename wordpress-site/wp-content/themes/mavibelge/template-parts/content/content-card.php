<?php
/**
 * One archive/loop card. Post-type-aware (branches internally rather
 * than shipping one near-identical file per CPT — see
 * docs/template-architecture.md). Built on top of the existing
 * template-parts/components/card.php shell so all archive cards share
 * one visually-consistent presentation instead of introducing a new,
 * unreviewed card style per content type in this phase.
 *
 * $args:
 * - post_id (int, required) — reads the post via get_post(); this part
 *   does not run its own WP_Query, only reads what the caller's loop
 *   already resolved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
$post    = $post_id ? get_post( $post_id ) : null;

if ( ! $post ) {
	return;
}

$post_type = get_post_type( $post );
$meta_html = '';

if ( 'mb_yeterlilik' === $post_type ) {
	$myk_code = get_post_meta( $post_id, '_mb_myk_code', true );
	$level    = get_post_meta( $post_id, '_mb_level', true );

	$badges = array();
	if ( $myk_code ) {
		$badges[] = '<span class="badge badge-neutral">' . esc_html( $myk_code ) . '</span>';
	}
	if ( $level ) {
		$badges[] = '<span class="badge">' . esc_html(
			sprintf(
				/* translators: %s: MYK qualification level, "1"-"8" */
				__( 'Seviye %s', 'mavibelge' ),
				$level
			)
		) . '</span>';
	}
	if ( taxonomy_exists( 'mb_sektor' ) ) {
		$terms = get_the_terms( $post_id, 'mb_sektor' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$badges[] = '<span class="badge badge-neutral">' . esc_html( $term->name ) . '</span>';
			}
		}
	}
	if ( $badges ) {
		$meta_html = '<div class="card-badges">' . implode( ' ', $badges ) . '</div>';
	}
} elseif ( 'mb_haber' === $post_type ) {
	$badges = array();
	if ( taxonomy_exists( 'mb_haber_turu' ) ) {
		$terms = get_the_terms( $post_id, 'mb_haber_turu' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$variant  = 'duyuru' === $term->slug ? 'duyuru' : '';
				$badges[] = '<span class="badge' . ( $variant ? ' badge-' . esc_attr( $variant ) : '' ) . '">' . esc_html( $term->name ) . '</span>';
			}
		}
	}
	$date = get_the_date( '', $post );
	if ( $date ) {
		$badges[] = '<span class="badge badge-neutral">' . esc_html( $date ) . '</span>';
	}
	if ( $badges ) {
		$meta_html = '<div class="card-badges">' . implode( ' ', $badges ) . '</div>';
	}
} elseif ( 'mb_dokuman' === $post_type ) {
	$version = get_post_meta( $post_id, '_mb_document_version', true );
	if ( $version ) {
		$meta_html = '<div class="card-badges"><span class="badge badge-neutral">'
			. esc_html(
				sprintf(
					/* translators: %s: document version string */
					__( 'Sürüm %s', 'mavibelge' ),
					$version
				)
			)
			. '</span></div>';
	}
}

$content = 'mb_dokuman' === $post_type ? '' : wp_strip_all_tags( get_the_excerpt( $post ) );

get_template_part(
	'template-parts/components/card',
	null,
	array(
		'title'        => get_the_title( $post ),
		'url'          => get_permalink( $post ),
		'content'      => $content,
		'content_html' => $meta_html,
	)
);
