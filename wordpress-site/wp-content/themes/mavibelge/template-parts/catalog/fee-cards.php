<?php
/**
 * Faz 5 — mobile ücret card list, the ≤768px counterpart of
 * fee-table.php. Same data, different layout — hidden on desktop via
 * CSS `display:none` (see fee-table.php's docblock for why this is the
 * documented, accessibility-tree-safe way to avoid announcing the same
 * record twice).
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
<ul class="fee-cards">
	<?php foreach ( $items as $fee ) : ?>
		<?php $presented = mavibelge_present_fee( $fee ); ?>
		<li class="fee-card">
			<h3><?php echo esc_html( $fee['profession_name'] ); ?></h3>
			<p class="fee-card-meta">
				<?php if ( '' !== $fee['qualification_code'] ) : ?>
					<span class="badge badge-neutral"><?php echo esc_html( $fee['qualification_code'] ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $fee['level'] ) : ?>
					<span class="badge">
						<?php
						printf(
							/* translators: %s: MYK qualification level, "1"-"8" */
							esc_html__( 'Seviye %s', 'mavibelge' ),
							esc_html( $fee['level'] )
						);
						?>
					</span>
				<?php endif; ?>
				<span class="badge badge-neutral"><?php echo esc_html( $fee['sector_name'] ); ?></span>
			</p>
			<?php get_template_part( 'template-parts/catalog/fee-options', null, array( 'fee' => $fee ) ); ?>
			<?php if ( '' !== $presented['qualification_permalink'] ) : ?>
				<a class="fee-card-link" href="<?php echo esc_url( $presented['qualification_permalink'] ); ?>"><?php esc_html_e( 'Yeterlilik Detayı →', 'mavibelge' ); ?></a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
