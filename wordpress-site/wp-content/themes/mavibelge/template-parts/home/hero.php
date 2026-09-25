<?php
/**
 * Homepage hero: hero-home.png background, H1/lead/CTAs, and the
 * "Mesleğini Bul" search panel. Faz 5: the panel now submits a REAL GET
 * request straight to the mb_yeterlilik archive with the mb_q param
 * (MaviBelge_Core_Catalog_Query's own contract) rather than WordPress's
 * generic ?s= search — the archive itself runs the real, scoped meslek
 * search via the catalog service. It is still NOT the static demo's JS
 * autosuggest (assets/js/search.js), which "sahte sonuç üretme" (brief
 * §5) rules out — no client-side suggestion list is fabricated. Copy is
 * taken verbatim from the approved tanitim-site/index.html (kullanıcı
 * tarafından kabul edilmiş statik referans).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="hero" style="background-image:url('<?php echo esc_url( get_theme_file_uri( 'assets/images/hero/hero-home.png' ) ); ?>')">
	<div class="container">
		<div>
			<span class="eyebrow"><?php esc_html_e( 'MYK Yetkili · TÜRKAK Akreditasyonlu Belgelendirme Kuruluşu', 'mavibelge' ); ?></span>
			<h1><?php esc_html_e( 'Mesleki Yeterliliğinizi Belgeleyin, Geleceğinizi Güvence Altına Alın', 'mavibelge' ); ?></h1>
			<p class="lead"><?php esc_html_e( 'Mavi Belge; makineden inşaata, tekstilden güzellik hizmetlerine 14 sektörde MYK ulusal yeterlilik sınavı ve belgelendirme hizmeti sunar.', 'mavibelge' ); ?></p>
			<div class="hero-actions">
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
						'label'   => __( 'Tüm Meslekleri Gör', 'mavibelge' ),
						'url'     => post_type_exists( 'mb_yeterlilik' ) ? (string) get_post_type_archive_link( 'mb_yeterlilik' ) : home_url( '/' ),
						'variant' => 'ghost',
					)
				);
				?>
			</div>
		</div>
		<div class="hero-search-panel">
			<h2><?php esc_html_e( 'Mesleğini Bul', 'mavibelge' ); ?></h2>
			<p class="lead-sm"><?php esc_html_e( 'Meslek adını veya ulusal yeterlilik kodunu yazarak belgelendirme programını bulun.', 'mavibelge' ); ?></p>
			<form class="hero-search-form" method="get" action="<?php echo esc_url( post_type_exists( 'mb_yeterlilik' ) ? (string) get_post_type_archive_link( 'mb_yeterlilik' ) : home_url( '/' ) ); ?>">
				<label class="visually-hidden" for="hero-mb-q"><?php esc_html_e( 'Meslek adı veya MYK kodu', 'mavibelge' ); ?></label>
				<input type="search" id="hero-mb-q" name="mb_q" placeholder="<?php esc_attr_e( 'Örn. kaynakçı veya 10UY0002', 'mavibelge' ); ?>">
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Ara', 'mavibelge' ); ?></button>
			</form>
			<p style="margin-top:12px">
				<a href="<?php echo esc_url( post_type_exists( 'mb_yeterlilik' ) ? (string) get_post_type_archive_link( 'mb_yeterlilik' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Tüm Meslekleri Gör →', 'mavibelge' ); ?></a>
			</p>
		</div>
	</div>
</section>
