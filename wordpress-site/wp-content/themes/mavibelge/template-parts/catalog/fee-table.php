<?php
/**
 * Faz 5 — desktop ücret table. Custom markup rather than
 * template-parts/components/table-wrap.php because that shell only
 * accepts plain-text cells (esc_html()'d); the Ücret column here needs
 * the richer fee-options.php partial (single amount OR a
 * <details><summary> list) — same justification pattern as card.php's
 * content_html distinction.
 *
 * Responsive note: hidden below 768px via CSS `display:none`
 * (assets/src/css/responsive.css) rather than any ARIA attribute —
 * `display:none` removes an element from the accessibility tree in
 * every mainstream browser/AT, so this table is never announced twice
 * alongside template-parts/catalog/fee-cards.php's mobile markup (see
 * docs/template-architecture.md for the same reasoning applied here).
 *
 * $args:
 * - items (array, required) — fee DTOs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
if ( empty( $items ) ) {
	return;
}
?>
<div class="table-wrap fee-table-wrap">
	<table class="data-table fee-table">
		<caption class="visually-hidden"><?php esc_html_e( 'Sınav ve belgelendirme ücretleri', 'mavibelge' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Meslek', 'mavibelge' ); ?></th>
				<th scope="col"><?php esc_html_e( 'MYK Kodu', 'mavibelge' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Seviye', 'mavibelge' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Sektör', 'mavibelge' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Ücret', 'mavibelge' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Detay', 'mavibelge' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $fee ) : ?>
				<?php $presented = mavibelge_present_fee( $fee ); ?>
				<tr>
					<td><?php echo esc_html( $fee['profession_name'] ); ?></td>
					<td><?php echo '' !== $fee['qualification_code'] ? esc_html( $fee['qualification_code'] ) : esc_html__( 'Belirtilmemiştir', 'mavibelge' ); ?></td>
					<td><?php echo '' !== $fee['level'] ? esc_html( $fee['level'] ) : '—'; ?></td>
					<td><?php echo esc_html( $fee['sector_name'] ); ?></td>
					<td><?php get_template_part( 'template-parts/catalog/fee-options', null, array( 'fee' => $fee ) ); ?></td>
					<td>
						<?php if ( '' !== $presented['qualification_permalink'] ) : ?>
							<a href="<?php echo esc_url( $presented['qualification_permalink'] ); ?>"><?php esc_html_e( 'Detay →', 'mavibelge' ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
