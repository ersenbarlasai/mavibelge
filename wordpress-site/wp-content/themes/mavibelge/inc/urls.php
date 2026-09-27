<?php
/**
 * Merkezi dahili bağlantı çözümleyici. PHP 7.3 uyumlu.
 *
 * Tema içindeki dahili sayfa/arşiv bağlantıları `home_url( '/slug/' )` ile KURULMAZ: home_url() etkin permalink yapısının
 * önekini (ör. staging'deki `/index.php/%postname%/`) eklemez. Bu dosya bağlantıyı WordPress'in etkin yapısına göre üretir;
 * hiçbir önek koda gömülmez.
 *
 * Çözüm sırası:
 *  1. CPT arşivi (meslekler → mb_yeterlilik, haberler → mb_haber, dokumanlar → mb_dokuman): get_post_type_archive_link().
 *  2. `duyurular`: mb_haber_turu/duyuru term arşivi (varsa); yoksa haberler arşivi.
 *  3. Yayınlanmış normal sayfa: get_page_by_path() + get_permalink().
 *  4. Sayfa yok/yayında değil: `$wp_rewrite->get_page_permastruct()` ile aynı yapıda önceden planlanmış yol
 *     (yayınlandığında adres değişmez; düz bağlantılarda ?pagename=).
 *
 * Harici, tel: ve mailto: bağlantılar ve gerçek ana sayfa (`home_url( '/' )`) bu çözümleyiciden GEÇMEZ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Yol -> yazı türü arşivi eşlemesi.
 *
 * @return array<string,string>
 */
function mavibelge_url_archive_map() {
	return array(
		'meslekler'  => 'mb_yeterlilik',
		'haberler'   => 'mb_haber',
		'dokumanlar' => 'mb_dokuman',
	);
}

/**
 * Bir dahili sayfa/arşiv yolunun (ör. 'sss', 'meslekler') etkin permalink yapısına uygun tam URL'si.
 *
 * @param string $path   Slug ('sss') veya iç içe yol ('a/b'); başta/sonda '/' kırpılır.
 * @param string $anchor Opsiyonel fragment ('sektorler'); '#' olmadan.
 * @return string Ham URL (çıktıda esc_url() ile kaçışlanır).
 */
function mavibelge_url( $path, $anchor = '' ) {
	$path = trim( (string) $path, '/' );
	$url  = mavibelge_url_resolve_base( $path );
	$frag = ltrim( (string) $anchor, '#' );
	return '' !== $frag ? $url . '#' . $frag : $url;
}

/**
 * @param string $path
 * @return string
 */
function mavibelge_url_resolve_base( $path ) {
	if ( '' === $path ) {
		return home_url( '/' );
	}
	$archives = mavibelge_url_archive_map();
	if ( isset( $archives[ $path ] ) && post_type_exists( $archives[ $path ] ) ) {
		$link = get_post_type_archive_link( $archives[ $path ] );
		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}
	}
	if ( 'duyurular' === $path ) {
		return mavibelge_url_announcements();
	}
	$page = get_page_by_path( $path, OBJECT, 'page' );
	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		$link = get_permalink( $page );
		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}
	}
	return mavibelge_url_planned_page( $path );
}

/**
 * Duyurular: mb_haber_turu/duyuru term arşivi; term yoksa haberler arşivi (bağlantı asla ölü kalmaz).
 *
 * @return string
 */
function mavibelge_url_announcements() {
	if ( taxonomy_exists( 'mb_haber_turu' ) ) {
		$term = get_term_by( 'slug', 'duyuru', 'mb_haber_turu' );
		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}
	}
	return post_type_exists( 'mb_haber' ) ? (string) get_post_type_archive_link( 'mb_haber' ) : home_url( '/' );
}

/**
 * Henüz yayınlanmamış/oluşturulmamış sayfa için, etkin yapıdaki planlanmış URL.
 *
 * @param string $path
 * @return string
 */
function mavibelge_url_planned_page( $path ) {
	global $wp_rewrite;
	$struct = is_object( $wp_rewrite ) ? (string) $wp_rewrite->get_page_permastruct() : '';
	if ( '' === $struct ) {
		return add_query_arg( 'pagename', $path, home_url( '/' ) );
	}
	return home_url( user_trailingslashit( str_replace( '%pagename%', $path, $struct ) ) );
}
