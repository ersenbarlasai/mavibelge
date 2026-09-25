<?php
/**
 * Referans logo ızgarası — build_reference_dto() DTO listesinden. Slider/otomatik oynatma YOK.
 * Logo yalnız servisin doğruladığı gerçek görsel attachment'tır; yoksa ad yazılır. "Temsili görsel"
 * notu `real` olmayan HER referansta gösterilir (asla gizlenmez).
 *
 * $args:
 * - items (array, zorunlu) referans DTO listesi
 * - link_to_single (bool) true ise kart referans sayfasına, değilse (ana sayfa) da sayfaya bağlanır
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
if ( empty( $items ) ) {
	return;
}
?>
<ul class="ref-grid">
	<?php foreach ( $items as $item ) : ?>
		<li>
			<a class="ref-card" href="<?php echo esc_url( get_permalink( $item['id'] ) ); ?>">
				<?php if ( $item['logo_id'] > 0 ) : ?>
					<?php echo wp_get_attachment_image( $item['logo_id'], 'medium', false, array( 'alt' => $item['name'], 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
				<?php else : ?>
					<span><?php echo esc_html( $item['name'] ); ?></span>
				<?php endif; ?>
			</a>
			<?php if ( 'real' !== $item['status'] ) : ?>
				<p class="ref-demo-note"><?php esc_html_e( 'Temsili görsel', 'mavibelge' ); ?></p>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
