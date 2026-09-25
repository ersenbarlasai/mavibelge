<?php
/**
 * Generic archive fallback — used only when no more specific
 * archive-{post_type}.php exists (WordPress template hierarchy). Each
 * mavibelge-core public CPT that needs richer presentation has its own
 * archive-mb_*.php; this file is a safe, content-agnostic catch-all so
 * an unexpected archive context never falls through to a broken or
 * empty page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container section-tight">
	<?php
	get_template_part(
		'template-parts/components/section-heading',
		null,
		array(
			'title' => get_the_archive_title(),
			'level' => 1,
		)
	);

	if ( have_posts() ) :
		?>
		<div class="card-grid-3">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part(
					'template-parts/components/card',
					null,
					array(
						'title'   => get_the_title(),
						'content' => wp_strip_all_tags( get_the_excerpt() ),
						'url'     => get_permalink(),
					)
				);
			endwhile;
			?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<?php
		get_template_part(
			'template-parts/components/alert',
			null,
			array(
				'type'    => 'empty-state',
				'title'   => __( 'İçerik bulunamadı', 'mavibelge' ),
				'message' => __( 'Bu bölümde henüz yayınlanmış bir kayıt yok.', 'mavibelge' ),
			)
		);
	endif;
	?>
</div>

<?php
get_footer();
