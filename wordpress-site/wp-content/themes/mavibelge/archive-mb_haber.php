<?php
/**
 * mb_haber archive — tanitim-site'in haberler.html sayfasının WordPress karşılığı.
 *
 * Liste MaviBelge_Core_Content_Service::get_news() servisinden gelir (tema doğrudan sorgu yapmaz):
 * yalnız yayınlanmış VE onaylı (`_mb_approval_status === 'approved'`) kayıtlar listelenir. Tür süzgeci
 * (`?mb_type=haber|duyuru`) ve sayfalama (`?mb_page=N`) gerçek bağlantılardır; JavaScript gerektirmez.
 * Faz 13: ortak page-hero (kayıt: inc/page-layouts.php mavibelge_archive_presentation('haberler')) + statik referans ızgarası.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$news     = mavibelge_get_news( mavibelge_content_request_args() );
$base_url = post_type_exists( 'mb_haber' ) ? (string) get_post_type_archive_link( 'mb_haber' ) : mavibelge_url( 'haberler' );
$type     = isset( $news['args']['type'] ) ? $news['args']['type'] : '';
$links    = mavibelge_content_filter_links(
	$base_url,
	'mb_type',
	$type,
	array(
		array( 'value' => 'haber', 'label' => __( 'Haberler', 'mavibelge' ) ),
		array( 'value' => 'duyuru', 'label' => __( 'Duyurular', 'mavibelge' ) ),
	)
);
?>

<?php get_template_part( 'template-parts/page/page-hero', null, mavibelge_archive_hero_args( 'haberler', __( 'Haberler', 'mavibelge' ) ) ); ?>

<section class="section-tight mb-archive">
<div class="container">
	<h2 class="screen-reader-text"><?php esc_html_e( 'Haber listesi', 'mavibelge' ); ?></h2>
	<?php
	get_template_part( 'template-parts/content/filter-links', null, array( 'links' => $links, 'label' => __( 'Haber türüne göre süz', 'mavibelge' ) ) );

	if ( ! empty( $news['items'] ) ) :
		?>
		<div class="news-grid">
			<?php
			foreach ( $news['items'] as $item ) {
				get_template_part( 'template-parts/content/news-card', null, array( 'item' => $item ) );
			}
			?>
		</div>
		<?php
		get_template_part(
			'template-parts/components/pagination',
			null,
			array(
				'current' => $news['page'],
				'total'   => $news['total_pages'],
				'url_for' => function ( $page ) use ( $base_url, $type ) {
					return mavibelge_content_page_url( $base_url, array( 'mb_type' => $type ), $page );
				},
			)
		);
	else :
		get_template_part(
			'template-parts/content/content-none',
			null,
			array(
				'title'   => __( 'Yayınlanmış haber bulunamadı', 'mavibelge' ),
				'message' => '' === $type
					? __( 'Bu bölümde şu anda yayınlanmış haber veya duyuru yok.', 'mavibelge' )
					: __( 'Seçili türde yayınlanmış içerik yok. Tüm haberleri görmek için "Tümü"nü seçin.', 'mavibelge' ),
			)
		);
	endif;
	?>
</div>
</section>

<?php
get_footer();
