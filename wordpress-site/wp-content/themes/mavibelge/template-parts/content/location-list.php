<?php
/**
 * Lokasyon kartları bölümü (İletişim sayfası). Veri MaviBelge_Core_Content_Service::get_locations()
 * servisinden gelir (mb_lokasyon = tek merkezi yönetim). Kayıt yoksa HİÇBİR şey çizilmez (sayfa doğrulanmış
 * statik iletişim yedeğini kullanmaya devam eder; uydurma lokasyon yok).
 *
 * $args:
 * - heading (string) bölüm başlığı (H2)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$locations = mavibelge_get_locations();
if ( empty( $locations ) ) {
	return;
}
$heading = isset( $args['heading'] ) ? $args['heading'] : __( 'Ofislerimiz ve Sınav Alanlarımız', 'mavibelge' );
?>
<section class="container section-tight" aria-labelledby="locations-heading">
	<h2 id="locations-heading"><?php echo esc_html( $heading ); ?></h2>
	<div class="card-grid-2">
		<?php
		foreach ( $locations as $location ) {
			get_template_part( 'template-parts/content/location-card', null, array( 'item' => $location, 'heading_level' => 3 ) );
		}
		?>
	</div>
</section>
