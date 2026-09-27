<?php
/**
 * Faz 12g — sosyal medya bağlantıları (footer ve İletişim sayfası AYNI parçayı kullanır). Hesaplar yalnız
 * mavibelge_social_links() tek kaynağından gelir; ikonlar dekoratiftir (aria-hidden), erişilebilir ad aria-label'dadır.
 * Yeni sekmede açılır: rel="noopener noreferrer" + ekran okuyucu için "(yeni sekmede açılır)".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$links = function_exists( 'mavibelge_social_links' ) ? mavibelge_social_links() : array();
if ( empty( $links ) ) {
	return;
}
$icons = array(
	'facebook'  => '<svg class="icon-18" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.6h2.6l.4-3h-3v-1.9c0-.9.2-1.5 1.5-1.5h1.6V4.3C15.9 4.2 15 4.1 14 4.1c-2.4 0-4 1.5-4 4.1v2.3H7.4v3H10V21h3.5z"/></svg>',
	'instagram' => '<svg class="icon-18" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1"/></svg>',
	'x'         => '<svg class="icon-18" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 3H21l-6.6 7.6L22 21h-6.2l-4.9-6.4L4.9 21H2.8l7-8.1L2 3h6.3l4.4 5.9L18.9 3zm-1.1 16.2h1.2L7.3 4.7H6l11.8 14.5z"/></svg>',
);
$new_tab = __( '(yeni sekmede açılır)', 'mavibelge' );
?>
<ul class="footer-social-list">
	<?php foreach ( $links as $link ) : ?>
		<?php if ( ! isset( $icons[ $link['key'] ] ) || 0 !== strpos( $link['url'], 'https://' ) ) { continue; } ?>
		<li>
			<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $link['label'] . ' ' . $new_tab ); ?>">
				<?php echo $icons[ $link['key'] ]; // phpcs:ignore WordPress.Security.EscapeOutput -- sabit, kayıtlı SVG ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
