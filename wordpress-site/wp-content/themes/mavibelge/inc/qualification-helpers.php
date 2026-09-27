<?php
/**
 * Yeterlilik liste/detay ekranları için tema yardımcıları (Faz 12e). PHP 7.3 uyumlu.
 *
 * Sorgu/iş kuralı YOKTUR: yeterlilik ve ücret verisi eklentinin katalog servisinden (inc/catalog-helpers.php) gelir.
 * Burada yalnız sunum için güvenli URL çözümü yapılır:
 *  - Sektör kahraman görseli: `_mb_image_attachment_id` term meta'sı GEÇERLİ, okunabilir bir görsel attachment'a işaret ediyorsa
 *    onun URL'si; aksi hâlde temadaki jenerik kahraman görseli. Sabit dosya yolu/tahmini görsel adı kullanılmaz.
 *  - Başvuru bağlantısı: `mavibelge_url( 'online-basvuru' )` (etkin permalink yapısı) + MYK kodu `meslek` sorgu parametresi.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param WP_Term|null $term Sektör terimi (mb_sektor).
 * @return string Kahraman arka plan görselinin URL'si (her zaman geçerli bir URL).
 */
function mavibelge_term_hero_image_url( $term ) {
	$fallback = get_theme_file_uri( 'assets/images/hero/hero-generic.svg' );
	if ( ! ( $term instanceof WP_Term ) ) {
		return $fallback;
	}
	$raw = get_term_meta( $term->term_id, '_mb_image_attachment_id', true );
	// Bozuk (dizi/metin) meta sayı sayılmaz; yalnız pozitif tam sayı kabul edilir.
	$id = ( is_int( $raw ) || ( is_string( $raw ) && '' !== $raw && ctype_digit( $raw ) ) ) ? (int) $raw : 0;
	if ( $id <= 0 || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
		return $fallback;
	}
	$file = get_attached_file( $id );
	if ( ! is_string( $file ) || '' === $file || ! is_readable( $file ) ) {
		return $fallback;
	}
	$url = wp_get_attachment_image_url( $id, 'full' );
	return is_string( $url ) && '' !== $url ? $url : $fallback;
}

/**
 * Başvuru sayfası bağlantısı; MYK kodu varsa `meslek` parametresiyle taşınır (URL-kodlanmış).
 *
 * @param string $myk_code
 * @return string
 */
function mavibelge_application_url( $myk_code = '' ) {
	$url  = mavibelge_url( 'online-basvuru' );
	$code = is_string( $myk_code ) ? trim( $myk_code ) : '';
	return '' !== $code ? add_query_arg( 'meslek', rawurlencode( $code ), $url ) : $url;
}
