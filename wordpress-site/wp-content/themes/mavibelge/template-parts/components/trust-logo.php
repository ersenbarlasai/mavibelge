<?php
/**
 * Trust/accreditation logo group (MYK/TÜRKAK). Presentation only — no
 * query, no DB access. Ported from tanitim-site/index.html's
 * .trust-group markup.
 *
 * $args:
 * - wrapper_class (string) default 'trust-group'
 * - logos (array) each: array( 'src', 'alt', 'code' ) — defaults to
 *   the two real, orijinal MYK/TÜRKAK logos with their real
 *   accreditation numbers (YB-0052 / AB-0104-P). Do not add
 *   "MYK tarafından yetkilendirilmiş" / "TÜRKAK tarafından akredite"
 *   descriptive text next to these — the brief explicitly asks that
 *   the removed captions not be re-added.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wrapper_class = isset( $args['wrapper_class'] ) ? $args['wrapper_class'] : 'trust-group';
$logos         = isset( $args['logos'] ) ? $args['logos'] : array(
	array(
		'src'  => get_theme_file_uri( 'assets/images/logos/myk_logo.png' ),
		'file' => 'assets/images/logos/myk_logo.png',
		'alt'  => 'MYK — Mesleki Yeterlilik Kurumu logosu',
		'code' => 'YB-0052',
	),
	array(
		'src'  => get_theme_file_uri( 'assets/images/logos/turkak_logo.png' ),
		'file' => 'assets/images/logos/turkak_logo.png',
		'alt'  => 'TÜRKAK — Türk Akreditasyon Kurumu logosu',
		'code' => 'AB-0104-P',
	),
);
// Faz 10: header bağlamındaki logolar ekran üstüdür (lazy YOK); diğer bağlamlar fold altı sayılır.
$image_mode = isset( $args['context'] ) && 'header' === $args['context'] ? 'above' : 'lazy';
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>" aria-label="<?php esc_attr_e( 'Yetkilendirme ve akreditasyon bilgileri', 'mavibelge' ); ?>">
	<?php foreach ( $logos as $logo ) : ?>
		<div class="trust-logo-item">
			<img src="<?php echo esc_url( $logo['src'] ); ?>" alt="<?php echo esc_attr( $logo['alt'] ); ?>" <?php echo mavibelge_local_image_attrs( isset( $logo['file'] ) ? $logo['file'] : '', $image_mode, 66 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- öznitelikler sayısal/sabit (inc/images.php). ?>>
			<span class="trust-code"><?php echo esc_html( $logo['code'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
