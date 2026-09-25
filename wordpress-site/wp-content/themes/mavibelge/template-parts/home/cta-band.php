<?php
/**
 * Homepage closing CTA band. Copy verbatim from tanitim-site/index.html.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="cta-band">
	<div class="container">
		<h2><?php esc_html_e( 'Mesleki yeterlilik belgeniz için ilk adımı atın.', 'mavibelge' ); ?></h2>
		<div class="cta-actions">
			<?php
			get_template_part(
				'template-parts/components/button',
				null,
				array(
					'label' => __( 'Online Başvuru', 'mavibelge' ),
					'url'   => home_url( '/online-basvuru/' ),
				)
			);
			get_template_part(
				'template-parts/components/button',
				null,
				array(
					'label'   => __( 'Sınav Talebi Oluştur', 'mavibelge' ),
					'url'     => home_url( '/sinav-talepleri/' ),
					'variant' => 'ghost',
				)
			);
			?>
		</div>
	</div>
</section>
