<?php
/**
 * Section heading shell (.section-title / .eyebrow pattern).
 *
 * $args:
 * - eyebrow (string) optional small label above the heading
 * - title (string, required)
 * - description (string) optional
 * - level (int) heading level, default 2 (renders <h1>..<h4>, clamped)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eyebrow     = isset( $args['eyebrow'] ) ? $args['eyebrow'] : '';
$title       = isset( $args['title'] ) ? $args['title'] : '';
$description = isset( $args['description'] ) ? $args['description'] : '';
$level       = isset( $args['level'] ) ? (int) $args['level'] : 2;
if ( $level < 1 ) {
	$level = 1;
}
if ( $level > 4 ) {
	$level = 4;
}
$tag = 'h' . $level;
?>
<div class="section-title">
	<?php if ( '' !== $eyebrow ) : ?>
		<span class="eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
	<?php endif; ?>
	<<?php echo esc_html( $tag ); ?>><?php echo esc_html( $title ); ?></<?php echo esc_html( $tag ); ?>>
	<?php if ( '' !== $description ) : ?>
		<p><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>
</div>
