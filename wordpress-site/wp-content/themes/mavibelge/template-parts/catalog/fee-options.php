<?php
/**
 * Faz 5 — renders ONE already-valid, already-visible fee's price
 * content: a single bold amount, or an accessible <details><summary>
 * list for multiple options (brief §5.1). Uses the browser's native
 * <details>/<summary> — same reasoning as page-sss.php (Faz 4): works
 * with JS off, free keyboard access, no custom accordion script.
 *
 * $args:
 * - fee (array, required) — a fee DTO as returned by the catalog service (already visibility-checked)
 * - expanded (bool) default false — Faz 12e: çok seçenekli listeyi açık başlatır (yeterlilik detayı CTA kartı); seçenekler aynıdır
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fee = isset( $args['fee'] ) && is_array( $args['fee'] ) ? $args['fee'] : null;
if ( null === $fee ) {
	return;
}

$expanded  = ! empty( $args['expanded'] );
$presented = mavibelge_present_fee( $fee );
$options   = $presented['options'];
?>
<div class="fee-options">
	<?php if ( 1 === count( $options ) ) : ?>
		<strong class="fee-amount"><?php echo esc_html( $options[0]['amount_display'] ); ?></strong>
		<?php if ( '' !== $options[0]['label'] ) : ?>
			<?php // Faz 15: tek seçenekte de açıklayıcı etiket ve birimler gösterilir (kayıpsız gösterim). ?>
			<span class="fee-option-label fee-single-label">
				<?php echo esc_html( $options[0]['label'] ); ?>
				<?php if ( ! empty( $options[0]['units'] ) ) : ?>
					<span class="fee-option-units">(<?php echo esc_html( implode( ', ', $options[0]['units'] ) ); ?>)</span>
				<?php endif; ?>
			</span>
		<?php endif; ?>
	<?php elseif ( count( $options ) > 1 ) : ?>
		<details class="fee-options-detail"<?php echo $expanded ? ' open' : ''; ?>>
			<summary>
				<?php
				printf(
					/* translators: %d: number of price options */
					esc_html__( 'Fiyat Seçenekleri (%d)', 'mavibelge' ),
					count( $options )
				);
				?>
			</summary>
			<ul class="fee-options-list">
				<?php foreach ( $options as $option ) : ?>
					<li>
						<span class="fee-option-label">
							<?php echo esc_html( $option['label'] ); ?>
							<?php if ( ! empty( $option['units'] ) ) : ?>
								<span class="fee-option-units">(<?php echo esc_html( implode( ', ', $option['units'] ) ); ?>)</span>
							<?php endif; ?>
						</span>
						<strong class="fee-option-amount"><?php echo esc_html( $option['amount_display'] ); ?></strong>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
	<?php else : ?>
		<span class="fee-amount-unknown">—</span>
	<?php endif; ?>

	<?php
	// Faz 15: standart KDV/belge basım açıklaması eklentide (MaviBelge_Core_Catalog_Query::fee_pricing_statements()) kaydın
	// KENDİ KDV oranı/dahil bilgisi ve belge basım tutarından üretilir; tema oran veya tutar yazmaz.
	?>
	<?php if ( isset( $presented['pricing_note'] ) && '' !== $presented['pricing_note'] ) : ?>
		<p class="fee-meta"><?php echo esc_html( $presented['pricing_note'] ); ?></p>
	<?php endif; ?>

	<?php if ( '' !== $presented['source_url'] || '' !== $fee['source_name'] ) : ?>
		<p class="fee-source">
			<?php if ( '' !== $presented['source_url'] ) : ?>
				<a href="<?php echo esc_url( $presented['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( '' !== $fee['source_name'] ? $fee['source_name'] : __( 'Kaynak Belge', 'mavibelge' ) ); ?>
				</a>
			<?php else : ?>
				<?php echo esc_html( $fee['source_name'] ); ?>
			<?php endif; ?>
			<?php if ( $fee['source_page'] > 0 ) : ?>
				· <?php printf( esc_html__( 'Sayfa %d', 'mavibelge' ), (int) $fee['source_page'] ); ?>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>
