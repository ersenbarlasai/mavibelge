<?php
/**
 * Ana sayfa "Haberler ve Duyurular" — MaviBelge_Core_Content_Service::get_latest_news() ile en yeni 3
 * onaylı haber/duyuru (tema doğrudan sorgu yapmaz). Kayıt yoksa dürüst boş durum; uydurma haber yok.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$news = mavibelge_get_latest_news( 3 );
?>
<section>
	<div class="container">
		<?php
		get_template_part(
			'template-parts/components/section-heading',
			null,
			array(
				'title'       => __( 'Haberler ve Duyurular', 'mavibelge' ),
				'description' => __( 'Kurumumuza ait güncel gelişmeler ve mevzuat duyuruları.', 'mavibelge' ),
			)
		);
		?>

		<?php if ( ! empty( $news ) ) : ?>
			<div class="news-grid">
				<?php
				foreach ( $news as $item ) {
					get_template_part( 'template-parts/content/news-card', null, array( 'item' => $item ) );
				}
				?>
			</div>
			<p style="margin-top:24px">
				<?php
				get_template_part(
					'template-parts/components/button',
					null,
					array(
						'label'   => __( 'Tüm Haberler →', 'mavibelge' ),
						'url'     => post_type_exists( 'mb_haber' ) ? (string) get_post_type_archive_link( 'mb_haber' ) : home_url( '/' ),
						'variant' => 'secondary',
					)
				);
				?>
			</p>
		<?php else : ?>
			<?php
			get_template_part(
				'template-parts/content/content-none',
				null,
				array(
					'title'   => __( 'Henüz haber yayınlanmadı', 'mavibelge' ),
					'message' => __( 'Yayınlanmış haber veya duyuru bulunmuyor.', 'mavibelge' ),
				)
			);
			?>
		<?php endif; ?>
	</div>
</section>
