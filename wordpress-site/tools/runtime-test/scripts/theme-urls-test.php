<?php
/**
 * Faz 12d — tema dahili bağlantıları etkin permalink yapısına uymalı (GERÇEK WordPress 6.9.9 + PHP 7.3.33).
 * (`wp --require=fixture-env.php --user=mbadmin eval-file theme-urls-test.php`)
 *
 * İki yapı: `/%postname%/` -> `/sss/`, `/index.php/%postname%/` -> `/index.php/sss/`. Yapı DB'ye YAZILMAZ: `pre_option_permalink_structure`
 * filtresi + `$wp_rewrite->init()`; yalnız klonlanmış `mbfx_` fixture DB'sinde sayfa/menü/terim oluşturulur ve sonunda silinir.
 * Tema kodunda `/index.php/` sabiti YOKTUR; beklenen değerler yalnız bu testte literal yazılıdır.
 */

$_SERVER['SERVER_NAME'] = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'mbfx.invalid'; // CLI: wp_head() bu anahtarı okur
global $wpdb, $wp_rewrite;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	return;
}
$results = array();
$t       = function ( $label, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array( $label, (bool) $ok, (string) $detail );
};
$structure = '';
add_filter(
	'pre_option_permalink_structure',
	function () use ( &$structure ) {
		return $structure;
	}
);
$with = function ( $s, $fn ) use ( &$structure, $wp_rewrite ) {
	$structure = $s;
	$wp_rewrite->init();
	$out = $fn();
	$structure = '';
	$wp_rewrite->init();
	return $out;
};
$home  = untrailingslashit( home_url() );
$clean = '/%postname%/';
$path  = '/index.php/%postname%/';

$pSss  = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'ZZ SSS', 'post_name' => 'sss', 'post_status' => 'publish' ) );
$pKvkk = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'ZZ Taslak', 'post_name' => 'zz-taslak-sayfa', 'post_status' => 'draft' ) );
$pHub  = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'ZZ Bilgi', 'post_name' => 'bilgi-merkezi', 'post_status' => 'publish' ) );
$t( 'fixture sayfaları oluştu (yayında sss + bilgi-merkezi, taslak zz-taslak-sayfa)', $pSss > 0 && $pKvkk > 0 && $pHub > 0 );

$fb = function ( $item ) {
	return mavibelge_fallback_menu_url( $item );
};
$t( 'fallback menü /%postname%/ -> /sss/', $home . '/sss/' === $with( $clean, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'sss' ) ); } ) );
$t( 'fallback menü /index.php/%postname%/ -> /index.php/sss/', $home . '/index.php/sss/' === $with( $path, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'sss' ) ); } ), (string) $with( $path, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'sss' ) ); } ) );

$t( 'yayında olmayan (taslak/yok) sayfa için de yapı korunur: /index.php/zz-taslak-sayfa/ ve /index.php/olmayan/', $home . '/index.php/zz-taslak-sayfa/' === $with( $path, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'zz-taslak-sayfa' ) ); } ) && $home . '/index.php/olmayan/' === $with( $path, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'olmayan' ) ); } ) && $home . '/olmayan/' === $with( $clean, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'olmayan' ) ); } ) );

$anchor = function ( $s ) use ( $with, $fb ) {
	return $with( $s, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'bilgi-merkezi', 'anchor' => 'cerezler' ) ); } );
};
$t( 'fragment doğru permalink sonuna eklenir (her iki yapı)', $home . '/bilgi-merkezi/#cerezler' === $anchor( $clean ) && $home . '/index.php/bilgi-merkezi/#cerezler' === $anchor( $path ), $anchor( $path ) );

$t( 'CPT arşivleri get_post_type_archive_link ile (meslekler/haberler/dokumanlar) — her iki yapıda', (function () use ( $with, $fb, $clean, $path ) {
	foreach ( array( 'meslekler' => 'mb_yeterlilik', 'haberler' => 'mb_haber', 'dokumanlar' => 'mb_dokuman' ) as $slug => $cpt ) {
		foreach ( array( $clean, $path ) as $s ) {
			$got = $with( $s, function () use ( $fb, $slug ) { return $fb( array( 'label' => 'x', 'path' => $slug ) ); } );
			$exp = $with( $s, function () use ( $cpt ) { return (string) get_post_type_archive_link( $cpt ); } );
			if ( '' === $exp || $got !== $exp ) {
				return false;
			}
		}
	}
	return true;
})() );
$t( 'meslekler + #sektorler: arşiv bağlantısı + fragment', (function () use ( $with, $fb, $path ) {
	$got = $with( $path, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'meslekler', 'anchor' => 'sektorler' ) ); } );
	$exp = $with( $path, function () { return (string) get_post_type_archive_link( 'mb_yeterlilik' ) . '#sektorler'; } );
	return $got === $exp && false !== strpos( $got, '/index.php/' );
})() );

// Duyurular: term yokken haberler arşivi; term varsa mb_haber_turu/duyuru term arşivi.
$dHaber = $with( $path, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'duyurular' ) ); } );
$tDuy   = wp_insert_term( 'ZZ Duyuru', 'mb_haber_turu', array( 'slug' => 'duyuru' ) );
$dTerm  = $with( $path, function () use ( $fb ) { return $fb( array( 'label' => 'x', 'path' => 'duyurular' ) ); } );
$expT   = $with( $path, function () { $tm = get_term_by( 'slug', 'duyuru', 'mb_haber_turu' ); return $tm ? (string) get_term_link( $tm ) : ''; } );
$t( 'duyurular: term yokken haberler arşivi; term varsa term arşivi (mevcut çözüm korunur)', $dHaber === $with( $path, function () { return (string) get_post_type_archive_link( 'mb_haber' ); } ) && '' !== $expT && $dTerm === $expT, $dHaber . ' | ' . $dTerm );
$hub = $with( $path, function () { return mavibelge_resolve_hub_link_url( array( 'label' => 'x', 'path' => 'duyurular' ) ); } );
$hub2 = $with( $path, function () { return mavibelge_resolve_hub_link_url( array( 'label' => 'x', 'path' => 'sss' ) ); } );
$t( 'hub bağlantısı çözümleyicisi: duyurular term arşivi; sss -> /index.php/sss/', $hub === $expT && $home . '/index.php/sss/' === $hub2, $hub2 );

// Gerçek menü: "Sayfa" öğesi WordPress'in get_permalink()'i ile; "Özel bağlantı" eski /sss/ kaydı SESSİZCE DEĞİŞTİRİLMEZ.
$menuId = wp_create_nav_menu( 'ZZ Menü' );
wp_update_nav_menu_item( $menuId, 0, array( 'menu-item-title' => 'SSS sayfa', 'menu-item-object' => 'page', 'menu-item-object-id' => $pSss, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
wp_update_nav_menu_item( $menuId, 0, array( 'menu-item-title' => 'SSS özel', 'menu-item-type' => 'custom', 'menu-item-url' => $home . '/sss/', 'menu-item-status' => 'publish' ) );
$locations = get_theme_mod( 'nav_menu_locations' );
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menuId ) );
$html = $with( $path, function () { return wp_nav_menu( array( 'theme_location' => 'primary', 'echo' => false ) ); } );
$t( 'gerçek menü: Sayfa öğesi /index.php/sss/ (get_permalink); Özel bağlantı eski /sss/ olduğu gibi kalır (yönetimsel düzeltme gerekir)', false !== strpos( (string) $html, 'href="' . $home . '/index.php/sss/"' ) && false !== strpos( (string) $html, 'href="' . $home . '/sss/"' ), substr( (string) $html, 0, 400 ) );
$t( 'gerçek menü atandığında fallback çalışmaz (çıktıda fallback alt menü kimliği yok)', false === strpos( (string) $html, 'submenu-fallback-' ) );

// Şablon çıktıları (header/footer) — yayında sss dışında sayfa yok; CTA ve footer yapıyı izler.
$hdr = $with( $path, function () { ob_start(); locate_template( 'header.php', true, false ); $o = ob_get_clean(); return $o; } );
$ftr = $with( $path, function () { ob_start(); locate_template( 'footer.php', true, false ); $o = ob_get_clean(); return $o; } );
$t( 'header CTA (Online Başvuru) /index.php/ önekli; ana sayfa (marka) bağlantısı home_url(/) olarak kalır', false !== strpos( $hdr, 'href="' . $home . '/index.php/online-basvuru/"' ) && false !== strpos( $hdr, 'href="' . $home . '/"' ) && false === strpos( $hdr, 'href="' . $home . '/online-basvuru/"' ) );
$t( 'footer KVKK + Çerez Tercihleri (fragment) /index.php/ önekli', false !== strpos( $ftr, 'href="' . $home . '/index.php/kvkk/"' ) && false !== strpos( $ftr, 'href="' . $home . '/index.php/gizlilik-politikasi/#cerezler"' ), '' );
$hdrC = $with( $clean, function () { ob_start(); locate_template( 'header.php', true, false ); $o = ob_get_clean(); return $o; } );
$t( 'temiz yapıda header CTA /online-basvuru/ (önek YOK)', false !== strpos( $hdrC, 'href="' . $home . '/online-basvuru/"' ) && false === strpos( $hdrC, '/index.php/' ), strlen( $hdrC ) . ' ' . substr( $hdrC, 0, 200 ) );

// temizlik
set_theme_mod( 'nav_menu_locations', is_array( $locations ) ? $locations : array() );
wp_delete_nav_menu( $menuId );
foreach ( array( $pSss, $pKvkk, $pHub ) as $pid ) {
	wp_delete_post( $pid, true );
}
if ( is_array( $tDuy ) ) {
	wp_delete_term( $tDuy['term_id'], 'mb_haber_turu' );
}
$t( 'temizlik: fixture sayfa/menü/terim kalmadı', null === get_page_by_path( 'sss' ) && null === term_exists( 'duyuru', 'mb_haber_turu' ), wp_json_encode( array( get_page_by_path( 'sss' ) ? 'sayfa-var' : 'yok', term_exists( 'duyuru', 'mb_haber_turu' ) ) ) );

$pass = 0;
foreach ( $results as $r ) {
	echo ( $r[1] ? 'PASS  ' : 'FAIL  ' ) . $r[0] . ( '' !== $r[2] && ! $r[1] ? '  [' . $r[2] . ']' : '' ) . "\n";
	$pass += $r[1] ? 1 : 0;
}
echo "\n{$pass}/" . count( $results ) . " Faz 12d tema URL çözümleyici WordPress runtime testi geçti.\n";
