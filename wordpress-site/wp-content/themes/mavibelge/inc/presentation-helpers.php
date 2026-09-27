<?php
/**
 * Faz 13 — referans sayfa aileleri için SUNUM yardımcıları (veri yok, sorgu yok).
 *
 * - Kahraman argümanları: kırıntı Anasayfa'dan + kayıttaki üst sayfalar; lead önce WordPress sayfa özeti
 *   (yönetilebilir), yoksa kayıttaki statik referans gezinme metni.
 * - Editör içeriğini başlıklardan bölümlere ayırma: METİN DEĞİŞMEZ; yalnız sarmalayıcı eklenir ve bölüm başlığı sayfanın
 *   başlık sırasını atlamayacak seviyede yeniden yazılır.
 * - Kök-göreli iç bağlantıların (/slug/) etkin kalıcı bağlantı yapısına çevrilmesi: saklanan içerik değişmez, yalnız
 *   çıktı; çözüm `mavibelge_url()` (inc/urls.php) ile.
 *
 * Kayıtlar: inc/page-layouts.php (`mavibelge_page_presentation()`, `mavibelge_archive_presentation()`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kayıttaki üst sayfalardan kırıntı dizisi (Anasayfa ilk, geçerli sayfa son; son öğe bağlantısız).
 *
 * @param array  $presentation kayıt satırı (parents: array<int, array{label: string, path: string}>)
 * @param string $current      geçerli sayfa etiketi
 * @return array<int, array{label: string, url?: string}>
 */
function mavibelge_presentation_crumbs( array $presentation, $current ) {
	$crumbs = array( array( 'label' => __( 'Anasayfa', 'mavibelge' ), 'url' => home_url( '/' ) ) );
	foreach ( isset( $presentation['parents'] ) && is_array( $presentation['parents'] ) ? $presentation['parents'] : array() as $parent ) {
		$crumbs[] = array( 'label' => $parent['label'], 'url' => mavibelge_url( $parent['path'] ) );
	}
	$crumbs[] = array( 'label' => (string) $current );
	return $crumbs;
}

/**
 * page.php için page-hero argümanları. Kayıt yoksa önceki davranış: yalnız başlık kırıntısı, eyebrow/lead yok.
 *
 * @param int    $post_id
 * @param string $slug
 * @return array
 */
function mavibelge_page_hero_args( $post_id, $slug ) {
	$presentation = function_exists( 'mavibelge_page_presentation' ) ? mavibelge_page_presentation( $slug ) : array();
	$title        = get_the_title( $post_id );
	if ( empty( $presentation ) ) {
		return array(
			'eyebrow'          => '',
			'title'            => $title,
			'description'      => '',
			'breadcrumb_items' => array( array( 'label' => $title ) ),
		);
	}
	$lead = '';
	if ( has_excerpt( $post_id ) ) {
		$lead = trim( wp_strip_all_tags( get_the_excerpt( $post_id ) ) );
	}
	if ( '' === $lead && isset( $presentation['description'] ) ) {
		$lead = (string) $presentation['description'];
	}
	return array(
		'eyebrow'          => isset( $presentation['eyebrow'] ) ? (string) $presentation['eyebrow'] : '',
		'title'            => $title,
		'description'      => $lead,
		'breadcrumb_items' => mavibelge_presentation_crumbs( $presentation, $title ),
	);
}

/**
 * Arşiv/taksonomi ekranları için page-hero argümanları.
 *
 * @param string $key   'haberler' | 'dokumanlar' | 'haber-turu'
 * @param string $title WordPress'in verdiği başlık (arşiv etiketi / terim adı)
 * @param string $lead  WordPress'in verdiği açıklama (ör. terim açıklaması); boşsa kayıt metni
 * @return array
 */
function mavibelge_archive_hero_args( $key, $title, $lead = '' ) {
	$presentation = function_exists( 'mavibelge_archive_presentation' ) ? mavibelge_archive_presentation( $key ) : array();
	$lead         = trim( wp_strip_all_tags( (string) $lead ) );
	if ( '' === $lead && isset( $presentation['description'] ) ) {
		$lead = (string) $presentation['description'];
	}
	return array(
		'eyebrow'          => isset( $presentation['eyebrow'] ) ? (string) $presentation['eyebrow'] : '',
		'title'            => (string) $title,
		'description'      => $lead,
		'breadcrumb_items' => mavibelge_presentation_crumbs( $presentation, $title ),
	);
}

/**
 * Editör HTML'ini bölümlere ayırır. Bölüm başlığı $tag (h2|h3|h4) olan ÜST DÜZEY başlıklardır; ilk bölüm başlığından
 * önceki kısım `intro`; $tag'den DAHA ÜST seviyede (ör. h3 bölümlerde h2) bir başlık görülünce oradan sonrası `rest`
 * olur (belge-yenileme: h3 kartlar, ardından h2 "Süreç"). Daha DERİN alt başlıklar bölüm gövdesinde kalır.
 * Herhangi bir başlık bir sarmalayıcının İÇİNDEYSE (div/ul/li/table/details… — ör. Gutenberg grup bloğu) bölümleme
 * YAPILMAZ ve içerik tek parça döner: etiket dengesi hiçbir koşulda bozulmaz, içerik kaybolmaz/çoğalmaz.
 * Başlık metni korunur; `heading_html` iç HTML, `heading_attrs` yalnız güvenli `id` özniteliği (sayfa içi çapa).
 *
 * @param string $html
 * @param string $tag
 * @return array{intro: string, sections: array<int, array{heading_html: string, heading_attrs: array, body: string}>, rest: string}
 */
function mavibelge_split_content_sections( $html, $tag ) {
	$html  = (string) $html;
	$whole = array( 'intro' => $html, 'sections' => array(), 'rest' => '' );
	if ( ! in_array( $tag, array( 'h2', 'h3', 'h4' ), true ) ) {
		return $whole;
	}
	$parts = preg_split( '#(<h[2-4]\b[^>]*>.*?</h[2-4]>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $parts ) ) {
		return $whole;
	}
	$level     = (int) substr( $tag, 1 );
	$container = 'div|section|article|aside|nav|header|footer|main|form|ul|ol|li|dl|dd|dt|table|thead|tbody|tfoot|tr|td|th|details|summary|blockquote|figure|figcaption';
	$depth     = 0;
	$out       = array( 'intro' => '', 'sections' => array(), 'rest' => '' );
	$current   = -1;
	$in_rest   = false;
	foreach ( $parts as $part ) {
		$is_heading = (bool) preg_match( '#^<(h([2-4]))\b([^>]*)>(.*)</h[2-4]>$#is', $part, $m );
		if ( $is_heading && 0 !== $depth ) {
			return $whole; // Sarmalayıcı içinde başlık: güvenli yol, bölümleme yok.
		}
		if ( ! $is_heading ) {
			$depth += preg_match_all( '#<(?:' . $container . ')\b[^>]*(?<!/)>#i', $part ) - preg_match_all( '#</(?:' . $container . ')\s*>#i', $part );
		}
		if ( $in_rest ) {
			$out['rest'] .= $part;
			continue;
		}
		if ( $is_heading ) {
			$heading_level = (int) $m[2];
			if ( $heading_level === $level ) {
				$attrs = array();
				if ( preg_match( '#\bid\s*=\s*(["\'])([A-Za-z][A-Za-z0-9_-]*)\1#', $m[3], $id ) ) {
					$attrs['id'] = $id[2];
				}
				$out['sections'][] = array( 'heading_html' => trim( $m[4] ), 'heading_attrs' => $attrs, 'body' => '' );
				$current           = count( $out['sections'] ) - 1;
				continue;
			}
			if ( $current >= 0 && $heading_level < $level ) {
				$in_rest      = true;
				$out['rest'] .= $part;
				continue;
			}
		}
		if ( $current >= 0 ) {
			$out['sections'][ $current ]['body'] .= $part;
		} else {
			$out['intro'] .= $part;
		}
	}
	if ( 0 !== $depth ) {
		return $whole;
	}
	return $out;
}

/**
 * Bölüm başlığını verilen seviyede yeniden yazar (metin/iç HTML aynen, yalnız etiket seviyesi; `strong` sarmalı korunur;
 * yalnız doğrulanmış `id` özniteliği taşınır).
 *
 * @param string $inner_html
 * @param int    $level 2..4
 * @param string $class
 * @param array  $attrs array( 'id' => string ) isteğe bağlı
 * @return string
 */
function mavibelge_section_heading( $inner_html, $level, $class = '', $attrs = array() ) {
	$level = max( 2, min( 4, (int) $level ) );
	$id    = isset( $attrs['id'] ) && preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', (string) $attrs['id'] ) ? ' id="' . esc_attr( $attrs['id'] ) . '"' : '';
	return '<h' . $level . $id . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>' . wp_kses_post( $inner_html ) . '</h' . $level . '>';
}

/**
 * Editör içeriğindeki kök-göreli, tek bölümlü iç bağlantıları (href="/slug/" veya "/slug/#parça") etkin kalıcı bağlantı
 * yapısına çevirir. Yalnız gerçek `href` özniteliği (ör. `data-href` değil); harici, sorgulu, çok bölümlü, zaten mutlak
 * ve WordPress'e ayrılmış yollara (wp-admin, feed, wp-json…) dokunmaz. Saklanan içerik değişmez; yalnız çıktı.
 *
 * @param string $html
 * @return string
 */
function mavibelge_localize_content_links( $html ) {
	return (string) preg_replace_callback(
		'#(?<![\w-])href=(["\'])/([a-z0-9-]+)/?(\#[A-Za-z0-9_-]+)?\1#',
		function ( $m ) {
			$reserved = array( 'wp-admin', 'wp-json', 'wp-content', 'wp-includes', 'feed', 'comments', 'xmlrpc', 'wp-login' );
			if ( in_array( $m[2], $reserved, true ) ) {
				return $m[0];
			}
			$anchor = isset( $m[3] ) ? ltrim( $m[3], '#' ) : '';
			return 'href=' . $m[1] . esc_url( mavibelge_url( $m[2], $anchor ) ) . $m[1];
		},
		(string) $html
	);
}

/**
 * `the_content` çıktısı (the_content() ile aynı: filtre + `]]>` kaçışı) + kök-göreli iç bağlantıların etkin yapıya
 * çevrilmesi. Döngü içinde çağrılır.
 *
 * @return string
 */
function mavibelge_rendered_content() {
	$html = (string) apply_filters( 'the_content', get_the_content() );
	return mavibelge_localize_content_links( str_replace( ']]>', ']]&gt;', $html ) );
}
