<?php
/**
 * Ana sayfa "Referanslarımız" — MaviBelge_Core_Content_Service::get_references() ile sade, erişilebilir
 * ızgara (JS slider YOK; Faz 4 brief §7.3). Ana sayfada en çok 12 referans gösterilir. "Temsili görsel"
 * notu `real` olmayan her referansta gösterilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$references = array_slice( mavibelge_get_references(), 0, 12 );
?>
<section class="bg-light" aria-labelledby="ref-heading">
	<div class="container">
		<div class="section-title">
			<h2 id="ref-heading"><?php esc_html_e( 'Referanslarımız', 'mavibelge' ); ?></h2>
			<p><?php esc_html_e( 'Farklı sektörlerde faaliyet gösteren kuruluşlara sınav ve belgelendirme hizmetleri sunuyoruz.', 'mavibelge' ); ?></p>
		</div>

		<?php if ( ! empty( $references ) ) : ?>
			<?php get_template_part( 'template-parts/content/reference-grid', null, array( 'items' => $references ) ); ?>
		<?php else : ?>
			<?php
			get_template_part(
				'template-parts/content/content-none',
				null,
				array(
					'title'   => __( 'Referans listesi henüz yayınlanmadı', 'mavibelge' ),
					'message' => __( 'Yayınlanmış referans bulunmuyor.', 'mavibelge' ),
				)
			);
			?>
		<?php endif; ?>
	</div>
</section>
