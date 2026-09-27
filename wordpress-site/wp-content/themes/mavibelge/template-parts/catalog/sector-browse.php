<?php
/**
 * Faz 12e — "Sektöre Göre Gözat" bölümü (tanitim-site/meslekler.html #sektorler). Gerçek mb_sektor terimleri çağırandan
 * gelir (mavibelge_get_sector_terms(): eklentinin sıralama sözleşmesi korunur); sabit sektör listesi YOKTUR. İkon, terimin
 * `_mb_icon_key` meta'sı ile merkezi ikon sisteminden (mavibelge_icon_svg) gelir; geçersiz anahtar güvenli yedek ikona düşer.
 * Bağlantı yalnız get_term_link() — hata varsa kart atlanır.
 *
 * $args:
 * - sectors (array<WP_Term>) boşsa bölüm hiç çizilmez
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sectors = isset( $args['sectors'] ) && is_array( $args['sectors'] ) ? $args['sectors'] : array();
if ( empty( $sectors ) ) {
	return;
}
?>
<section class="bg-light sector-browse" id="sektorler" aria-labelledby="sektorler-baslik">
	<div class="container">
		<div class="section-title"><h2 id="sektorler-baslik"><?php esc_html_e( 'Sektöre Göre Gözat', 'mavibelge' ); ?></h2></div>
		<div class="sector-grid">
			<?php
			foreach ( $sectors as $sector ) :
				if ( ! ( $sector instanceof WP_Term ) ) {
					continue;
				}
				$sector_link = get_term_link( $sector );
				if ( is_wp_error( $sector_link ) ) {
					continue;
				}
				$icon_key = get_term_meta( $sector->term_id, '_mb_icon_key', true );
				?>
				<a class="sector-card" href="<?php echo esc_url( $sector_link ); ?>">
					<div class="sector-icon" aria-hidden="true">
						<?php echo mavibelge_icon_svg( 'sector', is_string( $icon_key ) ? $icon_key : '', 'icon-22' ); // phpcs:ignore WordPress.Security.EscapeOutput -- sabit, kayıtlı SVG ?>
					</div>
					<div>
						<strong><?php echo esc_html( $sector->name ); ?></strong>
						<span><?php esc_html_e( 'Meslekleri İncele →', 'mavibelge' ); ?></span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
