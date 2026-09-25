<?php
/**
 * mb_yeterlilik archive — WordPress equivalent of tanitim-site's
 * meslekler.html. Faz 5: real meslek arama/filtre deneyimi, entirely
 * driven by MaviBelge_Core_Catalog_Service via the GET contract
 * (mb_q/mb_sector/mb_level/mb_priced/mb_page — see
 * docs/catalog-service-contract.md). This template does NOT run its
 * own WP_Query/meta_query against mb_yeterlilik or mb_ucret — every
 * visibility/matching rule lives in the plugin service (brief §7).
 *
 * WordPress's own main query for this archive is intentionally NOT
 * used for the result list (its default pagination/ordering does not
 * know about the free-text/sector/level/priced filters) — only the
 * page-level heading uses get_the_archive_title().
 *
 * No fee/price data is shown here — mb_ucret is deliberately non-public
 * (see docs/content-model.md); "yalnız güncel fiyatı bulunanlar" only
 * checks whether an active fee EXISTS for each result, never displays
 * the amount (that belongs to single-mb_yeterlilik.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$archive_url = (string) get_post_type_archive_link( 'mb_yeterlilik' );
$results     = mavibelge_get_qualification_results( $_GET );
$sectors     = mavibelge_get_sector_terms();
?>

<div class="container section-tight">
	<?php
	get_template_part(
		'template-parts/components/section-heading',
		null,
		array(
			'eyebrow'     => __( 'Meslekler ve Belgeler', 'mavibelge' ),
			'title'       => __( 'Tüm Meslekler', 'mavibelge' ),
			'description' => __( 'MYK yetkilendirmesi kapsamında sınav ve belgelendirme hizmeti verilen mesleki yeterlilikler.', 'mavibelge' ),
			'level'       => 1,
		)
	);

	get_template_part(
		'template-parts/catalog/filter-form',
		null,
		array(
			'action_url' => $archive_url,
			'filters'    => $results['filters'],
			'sectors'    => $sectors,
		)
	);

	if ( ! empty( $sectors ) ) {
		?>
		<nav class="sector-grid" id="sektorler" aria-label="<?php esc_attr_e( 'Sektöre göre gözat', 'mavibelge' ); ?>">
			<?php
			foreach ( $sectors as $sector ) :
				$sector_link = get_term_link( $sector );
				if ( is_wp_error( $sector_link ) ) {
					continue;
				}
				?>
				<a class="sector-card" href="<?php echo esc_url( $sector_link ); ?>">
					<?php echo esc_html( $sector->name ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
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

<?php
get_footer();
