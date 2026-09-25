<?php
/**
 * Generic card shell. Presentation only — the caller supplies already-
 * safe HTML for $args['content_html'] (this part does not itself
 * escape that value, since it is meant to hold markup, e.g. other
 * components) OR plain text via $args['content'] (escaped here).
 *
 * $args:
 * - title (string) optional, rendered as <h3>
 * - content (string) optional plain text, escaped
 * - content_html (string) optional pre-built markup — passed through
 *   wp_kses_post() (the same allowlist post_content itself uses), so a
 *   caller cannot inject <script>, event-handler attributes, etc. even
 *   if the value came indirectly from untrusted input
 * - url (string) optional — if set, the whole title becomes a link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title        = isset( $args['title'] ) ? $args['title'] : '';
$content      = isset( $args['content'] ) ? $args['content'] : '';
$content_html = isset( $args['content_html'] ) ? $args['content_html'] : '';
$url          = isset( $args['url'] ) ? $args['url'] : '';
?>
<div class="card-shell">
	<?php if ( '' !== $title ) : ?>
		<h3>
			<?php if ( '' !== $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $title ); ?>
			<?php endif; ?>
		</h3>
	<?php endif; ?>
	<?php if ( '' !== $content_html ) : ?>
		<?php echo wp_kses_post( $content_html ); ?>
	<?php endif; ?>
	<?php if ( '' !== $content ) : ?>
		<p><?php echo esc_html( $content ); ?></p>
	<?php endif; ?>
</div>
