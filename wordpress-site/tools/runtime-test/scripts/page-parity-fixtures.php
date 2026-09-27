<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı (pages-render.sh ortamı). Faz 13 referans sayfa ailesi testi
 * için AÇIKÇA SAHTE haber/duyuru/doküman kayıtları (başlıklar "TEST Parite ..."; kişi/kurum verisi YOK).
 *
 *   wp eval-file page-parity-fixtures.php content              13 onaylı haber + 1 ek duyuru + 2 doküman (kategori 'test-kategori')
 *   wp eval-file page-parity-fixtures.php permalink <yapı>     permalink_structure + rewrite flush (yalnız bu klonda)
 */
global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	fwrite( STDERR, "HATA: yalnız mbfx_ fixture önekinde çalışır.\n" );
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : '';
$out  = array( 'mode' => $mode );

$mk = function ( $type, $title, array $meta, $date ) use ( $wpdb ) {
	$id = wp_insert_post( wp_slash( array( 'post_type' => $type, 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_content' => 'TEST içerik: ' . $title, 'post_excerpt' => 'TEST özet: ' . $title, 'post_status' => 'draft', 'post_date' => $date ) ), true );
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, 'oluşturulamadı: ' . $title . "\n" );
		exit( 1 );
	}
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	// Yayın kapısı yönetim kaydetme yolunu korur; fixture içe aktarılmış "yayında" durumunu doğrudan kurar.
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) );
	clean_post_cache( $id );
	return (int) $id;
};

if ( 'content' === $mode ) {
	foreach ( array( 'haber' => 'Haber', 'duyuru' => 'Duyuru' ) as $slug => $name ) {
		if ( ! get_term_by( 'slug', $slug, 'mb_haber_turu' ) ) {
			wp_insert_term( $name, 'mb_haber_turu', array( 'slug' => $slug ) );
		}
	}
	$out['news'] = array();
	for ( $i = 1; $i <= 13; $i++ ) {
		$id = $mk( 'mb_haber', sprintf( 'TEST Parite Haber %02d', $i ), array( '_mb_approval_status' => 'approved' ), sprintf( '2026-08-%02d 10:00:00', $i ) );
		wp_set_object_terms( $id, 'haber', 'mb_haber_turu' );
		$out['news'][] = $id;
	}
	$out['announcement'] = $mk( 'mb_haber', 'TEST Parite Duyuru', array( '_mb_approval_status' => 'approved' ), '2026-08-20 10:00:00' );
	wp_set_object_terms( $out['announcement'], 'duyuru', 'mb_haber_turu' );
	// Öne çıkan görsel: fixture-env'in izole /tmp uploads dizinine yazılır.
	// pages-render.sh bu dizini yalnız test süresince geçici Apache Alias ile sunar ve ardından temizler.
	$theme_img   = get_theme_file_path( 'assets/images/hero/hero-home.png' );
	$uploads     = wp_get_upload_dir();
	$fixture_img = trailingslashit( $uploads['path'] ) . 'mbfx-page-parity-hero.png';
	if ( ! is_readable( $theme_img ) || ! @copy( $theme_img, $fixture_img ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- test düzeneği.
		fwrite( STDERR, "test görseli uploads'a kopyalanamadı.\n" );
		exit( 1 );
	}
	$out['image_id'] = (int) wp_insert_attachment( array( 'guid' => trailingslashit( $uploads['url'] ) . wp_basename( $fixture_img ), 'post_mime_type' => 'image/png', 'post_title' => 'TEST Parite Görsel', 'post_status' => 'inherit' ), $fixture_img );
	$size            = @getimagesize( $theme_img ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- test düzeneği.
	if ( $out['image_id'] > 0 && is_array( $size ) ) {
		wp_update_attachment_metadata( $out['image_id'], array( 'width' => (int) $size[0], 'height' => (int) $size[1], 'file' => wp_basename( $fixture_img ), 'sizes' => array() ) );
	}
	if ( $out['image_id'] > 0 ) {
		set_post_thumbnail( $out['news'][12], $out['image_id'] );
		set_post_thumbnail( $out['announcement'], $out['image_id'] );
	}
	if ( ! get_term_by( 'slug', 'test-kategori', 'mb_dokuman_kategori' ) ) {
		wp_insert_term( 'TEST Kategori', 'mb_dokuman_kategori', array( 'slug' => 'test-kategori' ) );
	}
	$out['docs'] = array();
	foreach ( array( 'TEST Parite Doküman A', 'TEST Parite Doküman B' ) as $n => $title ) {
		$id = $mk( 'mb_dokuman', $title, array( '_mb_document_version' => 'v' . ( $n + 1 ), '_mb_publish_date' => '2026-08-0' . ( $n + 1 ), '_mb_record_status' => 'active' ), '2026-08-0' . ( $n + 1 ) . ' 09:00:00' );
		wp_set_object_terms( $id, 'test-kategori', 'mb_dokuman_kategori' );
		$out['docs'][] = $id;
	}
} elseif ( 'permalink' === $mode ) {
	$struct = isset( $args[1] ) ? $args[1] : '';
	if ( ! in_array( $struct, array( '/%postname%/', '/index.php/%postname%/' ), true ) ) {
		fwrite( STDERR, "permalink: yalnız /%postname%/ veya /index.php/%postname%/\n" );
		exit( 1 );
	}
	global $wp_rewrite;
	// update_option() bellekteki $wp_rewrite'ı güncellemez; kurallar YENİ yapıyla üretilsin diye set_permalink_structure().
	$wp_rewrite->set_permalink_structure( $struct );
	flush_rewrite_rules( false );
	$out['permalink'] = get_option( 'permalink_structure' );
} elseif ( 'helper-cases' === $mode ) {
	// Gerçek tema yardımcılarının (inc/presentation-helpers.php) vaka çıktıları; test tarafında doğrulanır. Yazma YOK.
	$split = function ( $html, $tag ) {
		$p = mavibelge_split_content_sections( $html, $tag );
		$s = array();
		foreach ( $p['sections'] as $sec ) {
			$s[] = array( 'heading' => $sec['heading_html'], 'attrs' => $sec['heading_attrs'], 'body' => $sec['body'], 'emitted' => mavibelge_section_heading( $sec['heading_html'], 2, 'mb-card-title', $sec['heading_attrs'] ) );
		}
		return array( 'intro' => $p['intro'], 'sections' => $s, 'rest' => $p['rest'] );
	};
	$out['nested']   = $split( '<div class="wp-block-group"><h3>A</h3><p>x</p></div><h3>B</h3><p>y</p>', 'h3' );
	$out['deeper']   = $split( '<h3>A</h3><p>a</p><h4>A1</h4><p>a1</p><h3>B</h3><p>b</p><h2>R</h2><p>r</p>', 'h3' );
	$out['attrs']    = $split( '<h2 id="basvuru" class="x" onclick="evil()">T</h2><p>t</p>', 'h2' );
	$out['media']    = $split( '<img src="x.png" alt="a"><h2>T</h2><p>t</p>', 'h2' );
	$out['flat']     = $split( '<p>z</p>', 'h2' );
	$out['nestedli'] = $split( '<ul><li><h2>L</h2></li></ul><h2>M</h2><p>m</p>', 'h2' );
	$links           = array(
		'page'     => '<a href="/mevzuat/">m</a>',
		'anchor'   => '<a href="/mevzuat/#kanun">m</a>',
		'data'     => '<span data-href="/mevzuat/">m</span>',
		'wpadmin'  => '<a href="/wp-admin/">a</a>',
		'feed'     => '<a href="/feed/">f</a>',
		'external' => '<a href="https://ornek.example/mevzuat/">e</a>',
		'multi'    => '<a href="/a/b/">x</a>',
		'query'    => '<a href="/mevzuat/?q=1">q</a>',
		'single'   => "<a href='/mevzuat/'>s</a>",
	);
	$out['links'] = array();
	foreach ( $links as $k => $v ) {
		$out['links'][ $k ] = mavibelge_localize_content_links( $v );
	}
	$out['expected_mevzuat'] = mavibelge_url( 'mevzuat' );
} else {
	fwrite( STDERR, "kip: content|permalink <yapı>|helper-cases\n" );
	exit( 1 );
}
echo wp_json_encode( $out ), "\n";
