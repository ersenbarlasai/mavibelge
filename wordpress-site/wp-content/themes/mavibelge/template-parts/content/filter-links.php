<?php
/**
 * Filtre şeridi (Haberler: Tümü / Haberler / Duyurular; Dokümanlar: kategori). JavaScript GEREKTİRMEZ:
 * her öğe gerçek bir bağlantıdır; geçerli olan `aria-current="page"` taşır.
 *
 * $args:
 * - links (array) mavibelge_content_filter_links() çıktısı
 * - label (string) nav'ın erişilebilir adı
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$links = isset( $args['links'] ) && is_array( $args['links'] ) ? $args['links'] : array();
$label = isset( $args['label'] ) ? $args['label'] : '';
if ( count( $links ) < 2 ) {
	return;
}
?>
<nav class="filter-bar" aria-label="<?php echo esc_attr( $label ); ?>">
	<ul class="filter-links">
		<?php foreach ( $links as $link ) : ?>
			<li>
				<a class="tag<?php echo $link['current'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $link['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $link['label'] ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
