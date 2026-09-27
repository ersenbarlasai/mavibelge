<?php
/**
 * SSS akordeonu — build_faq_dto() DTO listesinden. Yerel <details>/<summary>: JavaScript gerektirmez,
 * klavyeyle çalışır. Cevap HTML'i servis tarafında wp_kses_post'tan geçmiştir.
 *
 * $args:
 * - items (array, zorunlu) SSS DTO listesi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
if ( empty( $items ) ) {
	return;
}
?>
<div class="accordion">
	<?php foreach ( $items as $item ) : ?>
		<details class="accordion-item">
			<summary>
				<?php echo esc_html( $item['question'] ); ?>
				<svg class="icon icon-24" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>
			</summary>
			<div class="accordion-panel entry-content">
				<?php echo wp_kses_post( $item['answer_html'] ); ?>
			</div>
		</details>
	<?php endforeach; ?>
</div>
