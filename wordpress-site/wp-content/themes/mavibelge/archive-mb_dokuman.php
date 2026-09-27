<?php
/**
 * mb_dokuman archive — tanitim-site'in dokumanlar.html sayfasının WordPress karşılığı.
 * Liste MaviBelge_Core_Content_Service::get_documents() servisinden gelir: yalnız yayınlanmış ve
 * pasif olmayan dokümanlar; indirme bağlantısı yalnız doğrulanmış gerçek dosya için üretilir.
 * Kategori süzgeci `?mb_cat=slug`, sayfalama `?mb_page=N`.
 * Faz 13: ortak page-hero (kayıt: mavibelge_archive_presentation('dokumanlar')) + .doc-list (satır içi stil yok).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$docs     = mavibelge_get_documents( mavibelge_content_request_args() );
$base_url = post_type_exists( 'mb_dokuman' ) ? (string) get_post_type_archive_link( 'mb_dokuman' ) : mavibelge_url( 'dokumanlar' );
$category = isset( $docs['args']['category'] ) ? $docs['args']['category'] : '';
$options  = array();
foreach ( $docs['categories'] as $term ) {
	$options[] = array( 'value' => $term['slug'], 'label' => $term['name'] );
}
$links = mavibelge_content_filter_links( $base_url, 'mb_cat', $category, $options );
?>

<?php get_template_part( 'template-parts/page/page-hero', null, mavibelge_archive_hero_args( 'dokumanlar', __( 'Dokümanlar', 'mavibelge' ) ) ); ?>

<section class="section-tight mb-archive">
<div class="container">
	<h2 class="screen-reader-text"><?php esc_html_e( 'Doküman listesi', 'mavibelge' ); ?></h2>
	<?php
	get_template_part( 'template-parts/content/filter-links', null, array( 'links' => $links, 'label' => __( 'Doküman kategorisine göre süz', 'mavibelge' ) ) );

	if ( ! empty( $docs['items'] ) ) :
		?>
		<div class="doc-list">
			<?php
			foreach ( $docs['items'] as $item ) {
				get_template_part( 'template-parts/content/document-card', null, array( 'item' => $item ) );
			}
			?>
		</div>
		<?php
		get_template_part(
			'template-parts/components/pagination',
			null,
			array(
				'current' => $docs['page'],
				'total'   => $docs['total_pages'],
				'url_for' => function ( $page ) use ( $base_url, $category ) {
					return mavibelge_content_page_url( $base_url, array( 'mb_cat' => $category ), $page );
				},
			)
		);
	else :
		get_template_part(
			'template-parts/content/content-none',
			null,
			array(
				'title'   => __( 'Yayınlanmış doküman bulunamadı', 'mavibelge' ),
				'message' => '' === $category
					? __( 'Bu bölümde şu anda yayınlanmış doküman yok.', 'mavibelge' )
					: __( 'Seçili kategoride yayınlanmış doküman yok. Tüm dokümanları görmek için "Tümü"nü seçin.', 'mavibelge' ),
			)
		);
	endif;
	?>
</div>
</section>

<?php
get_footer();
