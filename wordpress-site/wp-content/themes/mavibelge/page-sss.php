<?php
/**
 * tanitim-site'in sss.html sayfasının WordPress karşılığı. mb_sss `has_archive => false` olduğundan gerçek
 * bir `page` şablonudur; liste MaviBelge_Core_Content_Service::get_faqs() servisinden gelir. Kategori
 * süzgeci (`?mb_cat=slug`) yalnız kategori terimi varsa gösterilir. Yerel <details>/<summary> akordeonu:
 * JavaScript gerektirmez, klavyeyle çalışır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page/page-hero',
		null,
		array(
			'title'            => get_the_title(),
			'breadcrumb_items' => array( array( 'label' => get_the_title() ) ),
		)
	);
	$faqs     = mavibelge_get_faqs( mavibelge_content_request_args() );
	$category = isset( $faqs['args']['category'] ) ? $faqs['args']['category'] : '';
	$options  = array();
	foreach ( $faqs['categories'] as $term ) {
		$options[] = array( 'value' => $term['slug'], 'label' => $term['name'] );
	}
	$links = mavibelge_content_filter_links( get_permalink(), 'mb_cat', $category, $options );
	?>

	<div class="container section-tight">
		<?php if ( trim( (string) get_the_content() ) ) : ?>
			<div class="entry-content content-narrow">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/content/filter-links', null, array( 'links' => $links, 'label' => __( 'Soru kategorisine göre süz', 'mavibelge' ) ) ); ?>

		<?php if ( ! empty( $faqs['items'] ) ) : ?>
			<?php get_template_part( 'template-parts/content/faq-list', null, array( 'items' => $faqs['items'] ) ); ?>
		<?php else : ?>
			<?php
			get_template_part(
				'template-parts/content/content-none',
				null,
				array(
					'title'   => __( 'Henüz soru eklenmedi', 'mavibelge' ),
					'message' => __( 'Yayınlanmış sık sorulan soru bulunmuyor.', 'mavibelge' ),
				)
			);
			?>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
