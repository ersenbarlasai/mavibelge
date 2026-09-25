<?php
/**
 * WordPress equivalent of tanitim-site's sinav-ucretleri.html. Faz 5:
 * a real, slug-specific page template (like page-referanslar.php /
 * page-sss.php in Faz 4) rather than the generic page.php + layout map,
 * because this page runs its own bounded, service-driven ücret query —
 * something page.php's router never does (see
 * docs/template-architecture.md).
 *
 * mb_ucret stays non-public: this template calls
 * mavibelge_get_active_fee_results() (MaviBelge_Core_Catalog_Service),
 * never a direct WP_Query/$wpdb against mb_ucret.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$page_url      = get_permalink();
	$active_period = mavibelge_active_tariff_period();
	$sectors       = mavibelge_get_sector_terms();
	$results       = null;
	if ( '' !== $active_period ) {
		$results = mavibelge_get_active_fee_results( $_GET );
	}
	?>
	<?php
	get_template_part(
		'template-parts/page/page-hero',
		null,
		array(
			'title'            => get_the_title(),
			'breadcrumb_items' => array( array( 'label' => get_the_title() ) ),
		)
	);
	?>

	<div class="container section-tight">
		<?php if ( trim( (string) get_the_content() ) ) : ?>
			<div class="entry-content content-narrow">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $active_period ) : ?>
			<p class="fee-active-period">
				<?php
				printf(
					/* translators: %s: active tariff period label, e.g. "2026" */
					esc_html__( 'Gösterilen ücretler %s dönemine aittir.', 'mavibelge' ),
					esc_html( $active_period )
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( null === $results ) : ?>
			<?php
			get_template_part(
				'template-parts/content/content-none',
				null,
				array(
					'title'   => __( 'Aktif tarife dönemi henüz tanımlı değil', 'mavibelge' ),
					'message' => __( 'Ücret bilgisi, kurum aktif tarife dönemini belirledikten sonra burada gösterilecektir.', 'mavibelge' ),
				)
			);
			?>
		<?php else : ?>
			<?php
			get_template_part(
				'template-parts/catalog/filter-form',
				null,
				array(
					'action_url'  => $page_url,
					'filters'     => $results['filters'],
					'sectors'     => $sectors,
					'show_priced' => false,
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
					'noun'        => __( 'ücret kaydından', 'mavibelge' ),
				)
			);
			?>

			<?php if ( ! empty( $results['items'] ) ) : ?>
				<?php get_template_part( 'template-parts/catalog/fee-table', null, array( 'items' => $results['items'] ) ); ?>
				<?php get_template_part( 'template-parts/catalog/fee-cards', null, array( 'items' => $results['items'] ) ); ?>
				<?php
				get_template_part(
					'template-parts/components/pagination',
					null,
					array(
						'current' => $results['page'],
						'total'   => $results['total_pages'],
						'url_for' => function ( $page ) use ( $page_url, $results ) {
							return mavibelge_catalog_page_url( $page_url, $results['filters'], $page );
						},
					)
				);
				?>
			<?php else : ?>
				<?php
				get_template_part(
					'template-parts/content/content-none',
					null,
					array(
						'title'   => __( 'Aramanızla eşleşen bir ücret kaydı bulunamadı', 'mavibelge' ),
						'message' => __( 'Farklı bir anahtar kelime, sektör veya seviye ile tekrar deneyin ya da filtreleri temizleyin.', 'mavibelge' ),
					)
				);
				?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
