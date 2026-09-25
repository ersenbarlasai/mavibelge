<?php
/**
 * Generic single fallback — used only when no more specific
 * single-{post_type}.php exists. Each mavibelge-core public CPT that
 * needs richer presentation has its own single-mb_*.php; this file is a
 * safe, content-agnostic catch-all (e.g. for WordPress's own built-in
 * `post` type, which this project does not otherwise use).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container section-tight">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'large' ); ?>
			<?php endif; ?>
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</div>

<?php
get_footer();
