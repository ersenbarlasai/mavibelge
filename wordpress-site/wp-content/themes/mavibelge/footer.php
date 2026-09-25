<?php
/**
 * The footer for the Mavi Belge theme.
 *
 * Structural equivalent of tanitim-site/index.html's <footer
 * class="site-footer">. Contact/location content below is a
 * documented, accessible FALLBACK using the statik reference's own
 * verified text — this plugin's content model (mavibelge-core) does
 * not yet expose a location/contact settings service; wiring this to
 * real CPT/settings data is Faz 7's job (görev kartı 02/03 dependency
 * — see docs/faz3-dependencies.md). No CPT query and no direct DB
 * access happens here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>

<footer class="site-footer">
	<div class="container">
		<div class="footer-grid">
			<div class="footer-brand">
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/logos/header-logo.png' ) ); ?>" alt="Mavi Belge" <?php echo mavibelge_local_image_attrs( 'assets/images/logos/header-logo.png', 'lazy', 44 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- öznitelikler sayısal/sabit (inc/images.php). ?>>
				<p><strong>Mavi Belge Uluslararası Sert. ve Göz. Hiz. Ltd. Şti.</strong></p>
				<p><?php esc_html_e( 'TÜRKAK akreditasyonlu ve MYK yetkilendirmeli, mesleki yeterlilik sınav ve belgelendirme hizmeti sunan bağımsız bir belgelendirme kuruluşudur.', 'mavibelge' ); ?></p>
			</div>

			<div class="footer-col">
				<h3><?php esc_html_e( 'Hızlı Bağlantılar', 'mavibelge' ); ?></h3>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer_quick',
						'container'      => false,
						'items_wrap'     => '<ul>%3$s</ul>',
						'fallback_cb'    => 'mavibelge_footer_quick_nav_fallback',
						'echo'           => true,
					)
				);
				?>
			</div>

			<div class="footer-col">
				<h3><?php esc_html_e( 'Kurumsal', 'mavibelge' ); ?></h3>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer_kurumsal',
						'container'      => false,
						'items_wrap'     => '<ul>%3$s</ul>',
						'fallback_cb'    => 'mavibelge_footer_kurumsal_nav_fallback',
						'echo'           => true,
					)
				);
				?>
			</div>

			<div class="footer-col">
				<h3><?php esc_html_e( 'İletişim', 'mavibelge' ); ?></h3>
				<!-- Faz 7 fallback: statik referanstan doğrulanmış iletişim metni. -->
				<ul>
					<li><?php esc_html_e( 'Mustafa Kemal Mah. İbrahim Karaoğlanoğlu Cad. Atay İş Merkezi Kat:12 Daire:63–64 İskenderun / HATAY Pk:31200', 'mavibelge' ); ?></li>
					<li><a href="tel:08502154422">0850 215 44 22</a></li>
					<li><a href="tel:03264414422">0326 441 44 22</a></li>
					<li><a href="tel:05426186284">0542 618 62 84</a></li>
					<li><a href="mailto:info@mavibelge.com.tr">info@mavibelge.com.tr</a></li>
				</ul>
				<div class="footer-social">
					<span class="footer-social-label"><?php esc_html_e( 'Sosyal Medya', 'mavibelge' ); ?></span>
					<ul class="footer-social-list">
						<li>
							<a href="https://www.facebook.com/mavibelge31" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Mavi Belge Facebook hesabı', 'mavibelge' ); ?>">
								<svg class="icon-18" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.6h2.6l.4-3h-3v-1.9c0-.9.2-1.5 1.5-1.5h1.6V4.3C15.9 4.2 15 4.1 14 4.1c-2.4 0-4 1.5-4 4.1v2.3H7.4v3H10V21h3.5z"/></svg>
							</a>
						</li>
						<li>
							<a href="https://www.instagram.com/mavi_belge" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Mavi Belge Instagram hesabı', 'mavibelge' ); ?>">
								<svg class="icon-18" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1"/></svg>
							</a>
						</li>
						<li>
							<!-- Statik kaynakta http://twitter.com/mavibelge31 idi; yalnız şema https'e düzeltildi.
							     Marka adı/hesap kurumdan teyit beklemektedir — bkz. docs/faz3-dependencies.md. -->
							<a href="https://twitter.com/mavibelge31" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Mavi Belge X (Twitter) hesabı', 'mavibelge' ); ?>">
								<svg class="icon-18" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 3H21l-6.6 7.6L22 21h-6.2l-4.9-6.4L4.9 21H2.8l7-8.1L2 3h6.3l4.4 5.9L18.9 3zm-1.1 16.2h1.2L7.3 4.7H6l11.8 14.5z"/></svg>
							</a>
						</li>
					</ul>
				</div>
			</div>
		</div>

		<?php $mb_footer_locations = function_exists( 'mavibelge_get_locations' ) ? mavibelge_get_locations() : array(); ?>
		<div class="footer-locations">
			<?php if ( ! empty( $mb_footer_locations ) ) : ?>
				<?php foreach ( $mb_footer_locations as $mb_location ) : ?>
					<div>
						<h4><?php echo esc_html( $mb_location['name'] ); ?></h4>
						<?php if ( '' !== $mb_location['address'] ) : ?><p><?php echo esc_html( $mb_location['address'] ); ?></p><?php endif; ?>
						<?php if ( ! empty( $mb_location['phones'] ) ) : ?>
							<p><?php foreach ( $mb_location['phones'] as $mb_i => $mb_phone ) : ?><?php echo $mb_i > 0 ? '<br>' : ''; ?><a href="<?php echo esc_url( $mb_phone['tel'], array( 'tel' ) ); ?>"><?php echo esc_html( $mb_phone['display'] ); ?></a><?php endforeach; ?></p>
						<?php endif; ?>
						<?php if ( '' !== $mb_location['map_url'] ) : ?>
							<a class="directions-link" href="<?php echo esc_url( $mb_location['map_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Yol Tarifi Al', 'mavibelge' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(yeni sekmede açılır)', 'mavibelge' ); ?></span></a>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
			<!-- Yedek: mb_lokasyon kaydı yoksa statik referanstan doğrulanmış lokasyon metni. -->
			<div>
				<h4><?php esc_html_e( 'Merkez Ofis — İskenderun/Hatay', 'mavibelge' ); ?></h4>
				<p><?php esc_html_e( 'Mustafa Kemal Mah. İbrahim Karaoğlanoğlu Cad. Atay İş Merkezi Kat:12 Daire:63–64 İskenderun / HATAY Pk:31200', 'mavibelge' ); ?></p>
			</div>
			<div>
				<h4><?php esc_html_e( 'Payas Sınav Alanı', 'mavibelge' ); ?></h4>
				<p><?php esc_html_e( 'Yıldırım Beyazıt, Özkul Çolak Cd., 31900 Payas / Dörtyol / Hatay', 'mavibelge' ); ?></p>
			</div>
			<div>
				<h4><?php esc_html_e( 'İzmir Aliağa Sınav Alanı', 'mavibelge' ); ?></h4>
				<p><?php esc_html_e( 'Siteler Mahallesi, 35800 Aliağa / İzmir', 'mavibelge' ); ?></p>
				<p><a href="tel:05426196284">0542 619 62 84</a></p>
			</div>
			<div>
				<h4><?php esc_html_e( 'Ankara Ofisi', 'mavibelge' ); ?></h4>
				<p><?php esc_html_e( '1176 Sokak, No: 28, Ostim / ANKARA', 'mavibelge' ); ?></p>
				<p><a href="tel:+905426196284">0542 619 62 84</a><br><a href="tel:+905426226284">0542 622 62 84</a></p>
				<a class="directions-link" href="https://www.google.com/maps/search/?api=1&amp;query=1176%20Sokak%20No%3A28%20Ostim%20Ankara" target="_blank" rel="noopener noreferrer">
					<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.4 7-11.5A7 7 0 005 9.5C5 14.6 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.3"/></svg>
					<?php esc_html_e( 'Yol Tarifi Al', 'mavibelge' ); ?>
				</a>
			</div>
					<?php endif; ?>
		</div>

		<div class="footer-bottom">
			<span>&copy; <span data-year><?php echo esc_html( gmdate( 'Y' ) ); ?></span> Mavi Belge. <?php esc_html_e( 'Tüm hakları saklıdır.', 'mavibelge' ); ?></span>
			<ul class="footer-legal">
				<li><a href="<?php echo esc_url( home_url( '/kvkk/' ) ); ?>">KVKK</a></li>
				<li><a href="<?php echo esc_url( home_url( '/gizlilik-politikasi/' ) ); ?>"><?php esc_html_e( 'Gizlilik Politikası', 'mavibelge' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/gizlilik-politikasi/#cerezler' ) ); ?>"><?php esc_html_e( 'Çerez Tercihleri', 'mavibelge' ); ?></a></li>
			</ul>
		</div>
	</div>
</footer>

<?php get_template_part( 'template-parts/components/back-to-top' ); ?>

<?php wp_footer(); ?>
</body>
</html>
