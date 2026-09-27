<?php
/**
 * Homepage trust/accreditation band (.bg-navy). Accreditation dates and
 * standard numbers are verbatim from the approved tanitim-site/index.html
 * — not invented here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="bg-navy">
	<div class="container">
		<div class="section-title">
			<span class="eyebrow"><?php esc_html_e( 'Kurumsal Güven', 'mavibelge' ); ?></span>
			<h2><?php esc_html_e( 'Yetkilendirilmiş Belgelendirme Kuruluşu', 'mavibelge' ); ?></h2>
			<p><?php esc_html_e( 'Mavi Belge, 02.05.2016 tarihinde Türk Akreditasyon Kurumu (TÜRKAK) tarafından TS EN ISO / IEC 17024:2012 standardında akredite edilmiş, 16.08.2016 tarihinde Mesleki Yeterlilik Kurumu (MYK) tarafından sınav ve belgelendirme faaliyetleri alanında yetkilendirilmiş belgelendirme kuruluşudur.', 'mavibelge' ); ?></p>
			<div class="hero-actions">
				<?php
				get_template_part(
					'template-parts/components/button',
					null,
					array(
						'label'   => __( 'Mavi Belge Hakkında', 'mavibelge' ),
						'url'     => mavibelge_url( 'hakkimizda' ),
						'variant' => 'ghost',
					)
				);
				get_template_part(
					'template-parts/components/button',
					null,
					array(
						'label' => __( 'Yetki Belgelerimizi İnceleyin', 'mavibelge' ),
						'url'   => mavibelge_url( 'yetki-akreditasyon' ),
					)
				);
				?>
			</div>
		</div>
	</div>
</section>
