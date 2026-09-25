<?php
/**
 * Homepage impact-stats band. Shows the 4 values the Faz 4 brief §5
 * names: 11 / 100.000+ / 14 / 81. The first 3 also appear in the frozen
 * tanitim-site/index.html (only 3 .impact-stat blocks exist there); "81
 * — İlde Hizmet" does not appear in that static reference, but it is
 * not a guessed/fabricated number — it is content the user explicitly
 * approved in the Faz 4 task brief itself, which is a later and more
 * specific instruction than the frozen static snapshot (see
 * docs/integration-notes/faz4-homepage-content.md).
 *
 * These 4 numbers are themselves a hardcoded presentational fallback,
 * same as the rest of front-page.php's marketing copy — not a
 * yeterlilik/ücret/haber/referans data record. A future managed-setting
 * home for them is the open integration note above.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="impact-stats" aria-label="<?php esc_attr_e( 'Mavi Belge istatistikleri', 'mavibelge' ); ?>">
	<div class="container">
		<div class="impact-stats-grid">
			<div class="impact-stat">
				<strong class="impact-stat-number">11</strong>
				<span><?php esc_html_e( 'Yıllık Tecrübe', 'mavibelge' ); ?></span>
			</div>
			<div class="impact-stat">
				<strong class="impact-stat-number">100.000+</strong>
				<span><?php esc_html_e( 'Belge Teslimi', 'mavibelge' ); ?></span>
			</div>
			<div class="impact-stat">
				<strong class="impact-stat-number">14</strong>
				<span><?php esc_html_e( 'Sektör', 'mavibelge' ); ?></span>
			</div>
			<div class="impact-stat">
				<strong class="impact-stat-number">81</strong>
				<span><?php esc_html_e( 'İlde Hizmet', 'mavibelge' ); ?></span>
			</div>
		</div>
	</div>
</section>
