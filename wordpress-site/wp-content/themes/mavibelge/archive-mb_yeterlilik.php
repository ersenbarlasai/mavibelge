<?php
/**
 * mb_yeterlilik archive — WordPress equivalent of tanitim-site's
 * meslekler.html (Faz 12e: koyu page-hero, kompakt filtre, 3 sütunlu
 * iki aksiyonlu kartlar, Önceki/Sonraki sayfalama, sonuçlardan SONRA
 * "Sektöre Göre Gözat"). Faz 5: real meslek arama/filtre deneyimi,
 * entirely driven by MaviBelge_Core_Catalog_Service via the GET contract
 * (mb_q/mb_sector/mb_level/mb_priced/mb_page — see
 * docs/catalog-service-contract.md). This template does NOT run its
 * own WP_Query/meta_query against mb_yeterlilik or mb_ucret — every
 * visibility/matching rule lives in the plugin service (brief §7).
 *
 * WordPress's own main query for this archive is intentionally NOT
 * used for the result list (its default pagination/ordering does not
 * know about the free-text/sector/level/priced filters).
 *
 * No fee/price data is shown here — "yalnız güncel fiyatı bulunanlar"
 * only checks whether an active fee EXISTS for each result, never
 * displays the amount (that belongs to single-mb_yeterlilik.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$archive_url = (string) get_post_type_archive_link( 'mb_yeterlilik' );
$results     = mavibelge_get_qualification_results( $_GET );
$sectors     = mavibelge_get_sector_terms();

get_template_part(
	'template-parts/page/page-hero',
	null,
	array(
		'eyebrow'          => __( 'Meslekler ve Belgeler', 'mavibelge' ),
		'title'            => __( 'Tüm Meslekler ve Yeterlilikler', 'mavibelge' ),
		'description'      => __( 'MYK ulusal yeterlilik sistemine göre belgelendirdiğimiz tüm meslekleri arayın ve filtreleyin.', 'mavibelge' ),
		'breadcrumb_items' => array(
			array(
				'label' => __( 'Anasayfa', 'mavibelge' ),
				'url'   => home_url( '/' ),
			),
			array( 'label' => __( 'Meslekler ve Belgeler', 'mavibelge' ) ),
		),
	)
);
?>

<section class="section-tight">
	<div class="container">
		<?php
		get_template_part(
			'template-parts/catalog/filter-form',
			null,
			array(
				'action_url' => $archive_url,
				'filters'    => $results['filters'],
				'sectors'    => $sectors,
			)
		);

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
			<div class="qual-list">
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
			get_template_part(
				'template-parts/components/pagination',
				null,
				array(
					'current' => $results['page'],
					'total'   => $results['total_pages'],
					'url_for' => function ( $page ) use ( $archive_url, $results ) {
						return mavibelge_catalog_page_url( $archive_url, $results['filters'], $page );
					},
				)
			);
		else :
			get_template_part(
				'template-parts/content/content-none',
				null,
				array(
					'title'   => __( 'Aramanızla eşleşen bir kayıt bulunamadı', 'mavibelge' ),
					'message' => __( 'Farklı bir anahtar kelime, sektör veya seviye ile tekrar deneyin ya da filtreleri temizleyin.', 'mavibelge' ),
				)
			);
		endif;
		?>
	</div>
</section>

<?php
get_template_part( 'template-parts/catalog/sector-browse', null, array( 'sectors' => $sectors ) );

get_footer();
