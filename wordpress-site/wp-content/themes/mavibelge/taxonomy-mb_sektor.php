<?php
/**
 * mb_sektor term archive — lists mb_yeterlilik posts in the current
 * sector. WordPress routes here automatically for any mb_sektor term
 * URL (rewrite base "sektor", see docs/content-model.md); no route is
 * invented by this template.
 *
 * Faz 5: the sector context is LOCKED to the current term — the filter
 * form never offers a sector select here (a user cannot GET-parameter
 * their way into a different sector's data; the URL path segment is
 * the only sector selector). Search/level/priced filters still apply,
 * scoped to this term via the catalog service's own tax_query.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term = get_queried_object();

$term_url = '';
if ( is_a( $term, 'WP_Term' ) ) {
	$link = get_term_link( $term );
	if ( ! is_wp_error( $link ) ) {
		$term_url = $link;
	}
}

$forced_filters          = $_GET;
$forced_filters['mb_sector'] = is_a( $term, 'WP_Term' ) ? $term->slug : '';
$results                 = mavibelge_get_qualification_results( $forced_filters );
?>

<div class="container section-tight">
	<?php
	get_template_part(
		'template-parts/components/section-heading',
		null,
		array(
			'eyebrow' => __( 'Sektör', 'mavibelge' ),
			'title'   => is_a( $term, 'WP_Term' ) ? $term->name : __( 'Sektör', 'mavibelge' ),
			'level'   => 1,
		)
	);

	if ( '' !== $term_url ) {
		get_template_part(
			'template-parts/catalog/filter-form',
			null,
			array(
				'action_url' => $term_url,
				'filters'    => $results['filters'],
				'sectors'    => array(), // Locked context — no sector select.
			)
		);
	}

	get_template_part(
		'template-parts/catalog/results-meta',
		null,
		array(
			'total'       => $results['total'],
			'page'        => $results['page'],
			'page_size'   => $results['page_size'],
			'items_count' => count( $results['items'] ),
			'noun'        => __( 'meslekten', 'mavibelge' ),
		)
	);

	if ( ! empty( $results['items'] ) ) :
		?>
		<div class="card-grid-3">
			<?php
			foreach ( $results['items'] as $item ) {
				get_template_part(
					'template-parts/catalog/qualification-card',
					null,
					array( 'item' => $item )
				);
			}
			?>
		</div>
		<?php
		// Alternatif sözdizimi (if : … endif;) — dış `if … : else : endif;`
		// bloğunun içinde süslü parantezli bir if, ardından gelen `else :`'i
		// kendisine bağlıyordu (sarkan else -> PHP parse error).
		if ( '' !== $term_url ) :
			// Faz 5 Düzeltme ve Kabul §3.3: the sector is already fixed by
			// the URL path segment itself — pagination links must not also
			// carry a redundant "?mb_sector=..." query arg.
			$pagination_filters           = $results['filters'];
			$pagination_filters['sector'] = '';
			get_template_part(
				'template-parts/components/pagination',
				null,
				array(
					'current' => $results['page'],
					'total'   => $results['total_pages'],
					'url_for' => function ( $page ) use ( $term_url, $pagination_filters ) {
						return mavibelge_catalog_page_url( $term_url, $pagination_filters, $page );
					},
				)
			);
		endif;
	else :
		get_template_part(
			'template-parts/content/content-none',
			null,
			array(
				'title'   => __( 'Bu sektörde eşleşen yeterlilik kaydı yok', 'mavibelge' ),
				'message' => __( 'Farklı bir anahtar kelime veya seviye ile tekrar deneyin ya da içerik Faz 6 aktarımıyla eklenecektir.', 'mavibelge' ),
			)
		);
	endif;
	?>
</div>

<?php
get_footer();
