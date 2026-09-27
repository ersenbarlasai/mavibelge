<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı. Faz 11 render QA için: statik referanstaki sayfa slug'larının
 * WordPress `page` karşılıklarını (başlık: statik başlangıç değeri) ve TEK onaylı haberi yazar. Diğer içerik türleri BİLEREK
 * boş bırakılır (boş durum davranışı sınanır). Sahte içerik yoktur; sayfa gövdeleri boştur.
 */
global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	exit( 1 );
}
$defaults = include MAVIBELGE_CORE_PATH . 'includes/seo/data/page-defaults.php';
$non_page = array( 'front-page', '404', 'meslekler', 'haberler', 'dokumanlar', 'sektor', 'duyurular', 'yeterlilik', 'haber-detay' );
$out      = array( 'pages' => array() );
// Faz 12: MB_QA_SKIP_PAGES=1 iken sayfalar bu betikte oluşturulmaz (pages-render.sh gerçek pages aşamasıyla oluşturur).
$skip_pages = '1' === getenv( 'MB_QA_SKIP_PAGES' );
foreach ( $skip_pages ? array() : $defaults as $slug => $row ) {
	if ( in_array( (string) $slug, $non_page, true ) ) {
		continue;
	}
	$title = preg_replace( '/ — Mavi Belge$/u', '', $row[0] );
	$id    = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => '' ) ), true );
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, 'sayfa oluşturulamadı: ' . $slug . "\n" );
		exit( 1 );
	}
	$out['pages'][] = $slug;
}
foreach ( array( 'haber' => 'Haber', 'duyuru' => 'Duyuru' ) as $slug => $name ) {
	if ( ! get_term_by( 'slug', $slug, 'mb_haber_turu' ) ) {
		wp_insert_term( $name, 'mb_haber_turu', array( 'slug' => $slug ) );
	}
}
$news = wp_insert_post( wp_slash( array( 'post_type' => 'mb_haber', 'post_title' => 'TEST QA Haber', 'post_name' => 'test-qa-haber', 'post_content' => 'TEST içerik', 'post_status' => 'draft' ) ), true );
update_post_meta( $news, '_mb_approval_status', 'approved' );
$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $news ) );
clean_post_cache( $news );
wp_set_object_terms( $news, 'duyuru', 'mb_haber_turu' );
$out['news_id'] = (int) $news;
$qual = get_posts( array( 'post_type' => 'mb_yeterlilik', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_mb_record_status', 'meta_value' => 'active' ) );
$out['qualification_id'] = empty( $qual ) ? 0 : (int) $qual[0];
$term = get_term_by( 'slug', 'plastik', 'mb_sektor' );
$out['sector_slug'] = $term ? 'plastik' : '';
echo wp_json_encode( $out ), "\n";
