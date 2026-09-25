<?php
/**
 * Breadcrumb presentation shell ONLY. No SEO/BreadcrumbList schema is
 * generated here — that is the SEO/AIO agentının işi (görev kartı 05,
 * Faz 9). This part just renders a trail the caller supplies.
 *
 * $args:
 * - items (array) list of array( 'label' => string, 'url' => string|'' ).
 *   The LAST item is treated as the current page: rendered without a
 *   link and with aria-current="page", regardless of whether 'url' was
 *   set for it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
if ( empty( $items ) ) {
	return;
}
$last_index = count( $items ) - 1;
?>
<nav class="breadcrumb" aria-label="<?php esc_attr_e( 'Sayfa konumu', 'mavibelge' ); ?>">
	<?php foreach ( $items as $index => $item ) : ?>
		<?php if ( $index === $last_index || empty( $item['url'] ) ) : ?>
			<span aria-current="page"><?php echo esc_html( $item['label'] ); ?></span>
		<?php else : ?>
			<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
			<span class="sep" aria-hidden="true">/</span>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
