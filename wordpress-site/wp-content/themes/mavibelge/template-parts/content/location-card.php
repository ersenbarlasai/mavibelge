<?php
/**
 * Lokasyon kartı — build_location_dto() DTO'su VEYA doğrulanmış yedek (inc/contact-helpers.php; `id` 0, bağlantısız başlık).
 * Harita bağlantısı yalnız https (servis/yedek doğrular; yeni harita URL'si ÜRETİLMEZ); yoksa erişilebilir yer tutucu.
 * Telefonlar `tel:`, e-posta (yalnız veride varsa) `mailto:` bağlantısıdır. iframe/izleme betiği YOKTUR.
 *
 * $args:
 * - item (array, zorunlu) lokasyon DTO'su
 * - heading_level (int) 2..4, varsayılan 3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item = isset( $args['item'] ) && is_array( $args['item'] ) ? $args['item'] : array();
if ( empty( $item['name'] ) ) {
	return;
}
$level = isset( $args['heading_level'] ) ? max( 2, min( 4, (int) $args['heading_level'] ) ) : 3;
$tag   = 'h' . $level;
$email = isset( $item['email'] ) && is_email( $item['email'] ) ? $item['email'] : '';
$map   = isset( $item['map_url'] ) && is_string( $item['map_url'] ) && 0 === strpos( $item['map_url'], 'https://' ) ? $item['map_url'] : '';
?>
<div class="location-card">
	<<?php echo esc_html( $tag ); ?>>
		<?php if ( ! empty( $item['id'] ) ) : ?>
			<a href="<?php echo esc_url( get_permalink( $item['id'] ) ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $item['name'] ); ?>
		<?php endif; ?>
	</<?php echo esc_html( $tag ); ?>>
	<?php if ( '' !== $item['address'] ) : ?>
		<p><?php echo esc_html( $item['address'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $item['phones'] ) ) : ?>
		<p class="location-phones">
			<?php foreach ( $item['phones'] as $index => $phone ) : ?>
				<?php echo $index > 0 ? '<br>' : ''; ?><a href="<?php echo esc_url( $phone['tel'], array( 'tel' ) ); ?>"><?php echo esc_html( $phone['display'] ); ?></a>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>
	<?php if ( '' !== $email ) : ?>
		<p><a href="<?php echo esc_url( 'mailto:' . $email, array( 'mailto' ) ); ?>"><?php echo esc_html( $email ); ?></a></p>
	<?php endif; ?>
	<?php if ( ! empty( $item['hours'] ) ) : ?>
		<p><?php echo esc_html( $item['hours'] ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $map ) : ?>
		<a class="directions-link" href="<?php echo esc_url( $map ); ?>" target="_blank" rel="noopener noreferrer">
			<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.4 7-11.5A7 7 0 005 9.5C5 14.6 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.3"/></svg>
			<?php esc_html_e( 'Yol Tarifi Al', 'mavibelge' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(yeni sekmede açılır)', 'mavibelge' ); ?></span>
		</a>
	<?php else : ?>
		<p class="map-placeholder"><?php esc_html_e( 'Harita bilgisi henüz eklenmedi', 'mavibelge' ); ?></p>
	<?php endif; ?>
</div>
