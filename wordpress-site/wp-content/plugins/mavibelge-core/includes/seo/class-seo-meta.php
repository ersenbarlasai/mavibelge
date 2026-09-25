<?php
/**
 * Faz 9 — SEO meta sözleşmesi (SAF; WordPress fonksiyonu çağırmaz): title, meta description, canonical,
 * robots yönergeleri, Open Graph ve Twitter/X alanları. WordPress bağlantısı `MaviBelge_Core_Seo_Service`'tedir.
 *
 * Kurallar:
 * - title: `<baz> — Mavi Belge` (marka zaten varsa tekrar edilmez), en çok 65 karakter (kelime sınırında kısaltılır);
 * - description: düz metin (etiket/kısa kod/satır sonu temizlenir), en çok 160 karakter;
 * - canonical: sorgu dizgesiz temiz URL; sayfalı sayfa kendi sayfa numarasıyla; filtreli URL süzgeçsiz tabana; arama/404 -> yok;
 * - robots: üretim dışı ortam / blog_public=0 -> noindex,nofollow (kapı); arama, filtreli URL, 404, editör noindex -> noindex,follow;
 * - Twitter/X: yalnız `twitter:card`, başlık, açıklama, görsel — GERÇEK hesap bilinmediğinden `twitter:site`/`creator` üretilmez
 *   (yapılandırmada @kullanıcı adı verilirse eklenir; uydurma hesap YOK).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Seo_Meta {

	const BRAND         = 'Mavi Belge';
	const TITLE_MAX     = 65;
	const DESC_MAX      = 160;
	const EDITOR_TITLE_MAX = 70;
	const EDITOR_DESC_MAX  = 180;

	/** Kamuya açık süzgeç/durum parametreleri: bunlardan biri varsa URL "filtreli"dir (sayfa parametresi HARİÇ). */
	const FILTER_PARAMS = array( 'mb_q', 'mb_sector', 'mb_level', 'mb_priced', 'mb_type', 'mb_cat', 'mb_form_status' );

	/** @return string Kelime sınırında kısaltılmış düz metin (`…` ile); sınırın altındaysa aynen. */
	public static function truncate( $text, $max ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( self::length( $text ) <= $max ) {
			return $text;
		}
		$cut = self::substr( $text, 0, $max - 1 );
		$pos = strrpos( $cut, ' ' );
		if ( false !== $pos && $pos > (int) ( strlen( $cut ) * 0.6 ) ) {
			$cut = substr( $cut, 0, $pos );
		}
		return rtrim( $cut, " ,;:.-—" ) . '…';
	}

	/** Düz metne çevirir: etiket, kısa kod ve satır sonları temizlenir. */
	public static function plain( $text ) {
		$text = preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', (string) $text );
		$text = strip_tags( (string) $text );
		$text = preg_replace( '/\[[^\]]{0,60}\]/u', '', $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/** `<baz> — Mavi Belge`; baz zaten markayı taşıyorsa (statik başlıklar) tekrar eklenmez; boş baz -> yalnız marka. */
	public static function compose_title( $base ) {
		$base = self::plain( $base );
		if ( '' === $base ) {
			return self::BRAND;
		}
		$suffix = ' — ' . self::BRAND;
		$brandAlready = self::BRAND === $base || substr( $base, -strlen( $suffix ) ) === $suffix || false !== strpos( $base, self::BRAND );
		if ( $brandAlready ) {
			return self::truncate( $base, self::TITLE_MAX );
		}
		return self::truncate( $base, self::TITLE_MAX - self::length( $suffix ) ) . $suffix;
	}

	/** Açıklama: editör > yedek metin; düz metin, en çok 160 karakter; ikisi de boşsa ''. */
	public static function description( $editor, $fallback ) {
		$text = self::plain( $editor );
		if ( '' === $text ) {
			$text = self::plain( $fallback );
		}
		return self::truncate( $text, self::DESC_MAX );
	}

	/**
	 * Editör alanı doğrulaması (yönetim ekranı): uzunluk sınırı aşılırsa REDDET (sessiz kırpma yok).
	 *
	 * @return array{ok: bool, value: string, error: string|null}
	 */
	public static function validate_editor_field( $kind, $raw ) {
		$max = 'title' === $kind ? self::EDITOR_TITLE_MAX : self::EDITOR_DESC_MAX;
		if ( is_array( $raw ) || is_object( $raw ) ) {
			return array( 'ok' => false, 'value' => '', 'error' => 'Geçersiz değer.' );
		}
		$value = self::plain( (string) $raw );
		if ( self::length( $value ) > $max ) {
			return array( 'ok' => false, 'value' => '', 'error' => 'En çok ' . $max . ' karakter olabilir.' );
		}
		return array( 'ok' => true, 'value' => $value, 'error' => null );
	}

	/**
	 * Süzgeç parametresi içeriyor mu (sayfa parametresi sayılmaz)?
	 *
	 * @param array $query $_GET biçimli dizi
	 */
	public static function has_filters( array $query ) {
		foreach ( self::FILTER_PARAMS as $param ) {
			if ( isset( $query[ $param ] ) && ( ! is_string( $query[ $param ] ) || '' !== trim( $query[ $param ] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Canonical URL kararı.
	 *
	 * @param array $ctx url (temiz, sorgusuz mutlak URL), page (int), page_arg ('mb_page'|'paged'), is_search, is_404
	 * @return string|null null: canonical üretilmez (arama/404/URL yok)
	 */
	public static function canonical( array $ctx ) {
		if ( ! empty( $ctx['is_search'] ) || ! empty( $ctx['is_404'] ) || empty( $ctx['url'] ) || ! is_string( $ctx['url'] ) ) {
			return null;
		}
		$url  = preg_replace( '/[?#].*$/', '', $ctx['url'] );
		$page = isset( $ctx['page'] ) ? (int) $ctx['page'] : 1;
		if ( $page > 1 ) {
			$arg = isset( $ctx['page_arg'] ) && 'paged' === $ctx['page_arg'] ? 'paged' : 'mb_page';
			return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $arg . '=' . $page;
		}
		return $url;
	}

	/**
	 * Robots yönergeleri (belirteç listesi). Üretim dışı ortam veya "arama motorlarından gizle" ayarı HER ŞEYİ kapatır.
	 *
	 * @param array $ctx production (bool), blog_public (bool), is_search, is_404, has_filters, editor_noindex
	 * @return string[]
	 */
	public static function robots( array $ctx ) {
		if ( empty( $ctx['production'] ) || isset( $ctx['blog_public'] ) && ! $ctx['blog_public'] ) {
			return array( 'noindex', 'nofollow' );
		}
		if ( ! empty( $ctx['is_404'] ) || ! empty( $ctx['is_search'] ) || ! empty( $ctx['has_filters'] ) || ! empty( $ctx['editor_noindex'] ) ) {
			return array( 'noindex', 'follow' );
		}
		return array( 'index', 'follow', 'max-image-preview:large', 'max-snippet:-1' );
	}

	/**
	 * Open Graph + Twitter/X alanları (meta name/property => içerik). Görsel yalnız GERÇEK bir mutlak URL ise eklenir.
	 *
	 * @param array $ctx title, description, url (canonical veya null), type ('website'|'article'), image (URL|''),
	 *   published (ISO 8601|''), modified (ISO 8601|''), twitter_site ('@ad'|'')
	 * @return array{og: array<string,string>, twitter: array<string,string>}
	 */
	public static function social( array $ctx ) {
		$og = array(
			'og:locale'    => 'tr_TR',
			'og:site_name' => self::BRAND,
			'og:type'      => isset( $ctx['type'] ) && 'article' === $ctx['type'] ? 'article' : 'website',
			'og:title'     => (string) $ctx['title'],
		);
		if ( ! empty( $ctx['description'] ) ) {
			$og['og:description'] = (string) $ctx['description'];
		}
		if ( ! empty( $ctx['url'] ) ) {
			$og['og:url'] = (string) $ctx['url'];
		}
		$image = isset( $ctx['image'] ) && is_string( $ctx['image'] ) && 1 === preg_match( '#^https?://[^\s"<>]+$#', $ctx['image'] ) ? $ctx['image'] : '';
		if ( '' !== $image ) {
			$og['og:image'] = $image;
		}
		if ( 'article' === $og['og:type'] ) {
			if ( ! empty( $ctx['published'] ) ) {
				$og['article:published_time'] = (string) $ctx['published'];
			}
			if ( ! empty( $ctx['modified'] ) ) {
				$og['article:modified_time'] = (string) $ctx['modified'];
			}
		}
		$tw = array(
			'twitter:card'  => '' !== $image ? 'summary_large_image' : 'summary',
			'twitter:title' => (string) $ctx['title'],
		);
		if ( ! empty( $ctx['description'] ) ) {
			$tw['twitter:description'] = (string) $ctx['description'];
		}
		if ( '' !== $image ) {
			$tw['twitter:image'] = $image;
		}
		if ( ! empty( $ctx['twitter_site'] ) && 1 === preg_match( '/^@[A-Za-z0-9_]{1,15}$/', $ctx['twitter_site'] ) ) {
			$tw['twitter:site'] = $ctx['twitter_site'];
		}
		return array( 'og' => $og, 'twitter' => $tw );
	}

	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	private static function substr( $text, $start, $len ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, $start, $len, 'UTF-8' ) : substr( $text, $start, $len );
	}
}
