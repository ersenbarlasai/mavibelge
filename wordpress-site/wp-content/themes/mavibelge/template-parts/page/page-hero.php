<?php
/**
 * Shared page-hero band: breadcrumb + eyebrow + H1 + optional lead
 * paragraph, on the .page-hero background used by every non-front-page
 * template in Faz 4 (page.php, archive/single/taxonomy CPT shells,
 * 404.php). Presentation only — no query, no DB access beyond what the
 * caller already resolved.
 *
 * $args:
 * - eyebrow (string) optional small label above the title
 * - title (string, required) — becomes the page's <h1>; caller is
 *   responsible for there being exactly one H1 per render (this part
 *   never renders more than one)
 * - description (string) optional lead paragraph
 * - breadcrumb_items (array) optional, same shape as
 *   template-parts/components/breadcrumb.php's $args['items']
 *   (array of array('label'=>string,'url'=>string|'')); last item is
 *   always the current page. Omit to render no breadcrumb.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eyebrow          = isset( $args['eyebrow'] ) ? $args['eyebrow'] : '';
$title            = isset( $args['title'] ) ? $args['title'] : '';
$description      = isset( $args['description'] ) ? $args['description'] : '';
$breadcrumb_items = isset( $args['breadcrumb_items'] ) && is_array( $args['breadcrumb_items'] ) ? $args['breadcrumb_items'] : array();
?>
<section class="page-hero" style="background-image:url('<?php echo esc_url( get_theme_file_uri( 'assets/images/hero/hero-generic.svg' ) ); ?>')">
	<div class="container">
		<?php if ( ! empty( $breadcrumb_items ) ) : ?>
			<?php get_template_part( 'template-parts/components/breadcrumb', null, array( 'items' => $breadcrumb_items ) ); ?>
		<?php endif; ?>
		<?php if ( '' !== $eyebrow ) : ?>
			<span class="eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
		<?php endif; ?>
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php if ( '' !== $description ) : ?>
			<p><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
	</div>
</section>
