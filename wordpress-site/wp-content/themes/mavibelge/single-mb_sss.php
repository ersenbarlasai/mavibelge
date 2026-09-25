<?php
/**
 * mb_sss single. No archive (has_archive => false); the sss.html
 * listing page is a `page` template (page-sss.php) with its own
 * bounded query. Title = question, content = answer (docs/content-model.md).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="container section-tight">
		<?php
		get_template_part(
			'template-parts/components/breadcrumb',
			null,
			array(
				'items' => array(
					array(
						'label' => __( 'Sık Sorulan Sorular', 'mavibelge' ),
						'url'   => home_url( '/sss/' ),
					),
					array( 'label' => get_the_title() ),
				),
			)
		);
		?>

		<h1><?php the_title(); ?></h1>

		<div class="entry-content">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
