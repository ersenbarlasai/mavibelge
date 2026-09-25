<?php
/**
 * Badge/tag component. Presentation only.
 *
 * $args:
 * - label (string, required)
 * - variant (string) '' | 'duyuru' | 'neutral' — default '' (blue)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$label   = isset( $args['label'] ) ? $args['label'] : '';
$variant = isset( $args['variant'] ) ? $args['variant'] : '';

$classes = array( 'badge' );
if ( '' !== $variant ) {
	$classes[] = 'badge-' . sanitize_html_class( $variant );
}
?>
<span class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"><?php echo esc_html( $label ); ?></span>
