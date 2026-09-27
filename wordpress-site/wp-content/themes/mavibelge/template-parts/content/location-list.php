<?php
/**
 * Lokasyon kartları bölümü (İletişim sayfası). Veri TEK kaynaktan: mavibelge_contact_locations() (inc/contact-helpers.php) —
 * merkezi içerik servisinin herkese açık mb_lokasyon kayıtları, yoksa footer ile paylaşılan doğrulanmış yedek. Uydurma yok.
 * Statik referansta görünür bölüm başlığı olmadığından H2 görsel olarak gizlidir (ekran okuyucuya açık).
 *
 * $args:
 * - heading (string) bölüm başlığı (H2)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$locations = function_exists( 'mavibelge_contact_locations' ) ? mavibelge_contact_locations() : array();
if ( empty( $locations ) ) {
	return;
}
$heading = isset( $args['heading'] ) ? $args['heading'] : __( 'Ofislerimiz ve Sınav Alanlarımız', 'mavibelge' );
?>
<section class="section-tight contact-locations" aria-labelledby="locations-heading">
	<div class="container">
		<h2 id="locations-heading" class="screen-reader-text"><?php echo esc_html( $heading ); ?></h2>
		<div class="location-grid">
			<?php
			foreach ( $locations as $location ) {
				get_template_part( 'template-parts/content/location-card', null, array( 'item' => $location, 'heading_level' => 3 ) );
			}
			?>
		</div>
	</div>
</section>
