<?php
/**
 * tanitim-site'in referanslar.html sayfasının WordPress karşılığı. mb_referans `has_archive => false`
 * olduğundan gerçek bir `page` şablonudur; liste MaviBelge_Core_Content_Service::get_references()
 * servisinden gelir (tema doğrudan sorgu yapmaz). Slider/otomatik oynatma yoktur. "Temsili görsel"
 * notu `real` olmayan her referansta gösterilir, asla gizlenmez.
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
	$references = mavibelge_get_references();
	?>

	<div class="container section-tight">
		<?php if ( trim( (string) get_the_content() ) ) : ?>
			<div class="entry-content content-narrow">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $references ) ) : ?>
			<?php get_template_part( 'template-parts/content/reference-grid', null, array( 'items' => $references ) ); ?>
		<?php else : ?>
			<?php
			get_template_part(
				'template-parts/content/content-none',
				null,
				array(
					'title'   => __( 'Referans listesi henüz yayınlanmadı', 'mavibelge' ),
					'message' => __( 'Yayınlanmış referans bulunmuyor.', 'mavibelge' ),
				)
			);
			?>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
