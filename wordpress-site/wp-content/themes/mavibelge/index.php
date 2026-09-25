<?php
/**
 * Safe, minimal fallback template. Uses the shared header.php/footer.php
 * (Faz 3) instead of Faz 1's self-contained full-document fallback.
 * header.php opens <main id="main" class="site-main" tabindex="-1">
 * and footer.php closes it — this file renders only what goes inside,
 * satisfying the "tek #main" contract.
 *
 * Single-H1 contract: this template renders exactly one page-level H1
 * (the blog/archive title), regardless of how many posts the loop
 * yields — zero, one, or many. Individual post titles inside the loop
 * are H2s, never H1s, so multiple posts never produce multiple H1s.
 *
 * No page-type-specific design here on purpose — front-page.php and
 * the 41-page templates are Faz 4.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="container section-tight">
	<?php
	$mavibelge_index_title = get_the_archive_title();
	if ( '' === trim( wp_strip_all_tags( (string) $mavibelge_index_title ) ) ) {
		$mavibelge_index_title = __( 'İçerikler', 'mavibelge' );
	}
	get_template_part(
		'template-parts/components/section-heading',
		null,
		array(
			'title' => $mavibelge_index_title,
			'level' => 1,
		)
	);
	?>
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h2><?php the_title(); ?></h2>
				<div><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'İçerik bulunamadı.', 'mavibelge' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
