<?php
/**
 * Lokasyon kartı — build_location_dto() DTO'sundan. Harita bağlantısı yalnız https (servis doğrular);
 * telefonlar `tel:` bağlantısıdır.
 *
 * $args:
 * - item (array, zorunlu) lokasyon DTO'su
 * - heading_level (int) 2..4, varsayılan 3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item = isset( $args['item'] ) && is_array( $args['item'] ) ? $args['item'] : array();
if ( empty( $item['id'] ) ) {
	return;
}
$level = isset( $args['heading_level'] ) ? max( 2, min( 4, (int) $args['heading_level'] ) ) : 3;
$tag   = 'h' . $level;
?>
<div class="location-card">
	<<?php echo esc_html( $tag ); ?>><a href="<?php echo esc_url( get_permalink( $item['id'] ) ); ?>"><?php echo esc_html( $item['name'] ); ?></a></<?php echo esc_html( $tag ); ?>>
	<?php if ( '' !== $item['address'] ) : ?>
		<p><?php echo esc_html( $item['address'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $item['phones'] ) ) : ?>
		<p>
			<?php foreach ( $item['phones'] as $index => $phone ) : ?>
				<?php echo $index > 0 ? '<br>' : ''; ?><a href="<?php echo esc_url( $phone['tel'], array( 'tel' ) ); ?>"><?php echo esc_html( $phone['display'] ); ?></a>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>
	<?php if ( '' !== $item['hours'] ) : ?>
		<p><?php echo esc_html( $item['hours'] ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $item['map_url'] ) : ?>
		<a class="directions-link" href="<?php echo esc_url( $item['map_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Yol Tarifi Al', 'mavibelge' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(yeni sekmede açılır)', 'mavibelge' ); ?></span></a>
	<?php endif; ?>
</div>
