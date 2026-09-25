<?php
/**
 * Faz 9 — robots.txt ve sitemap SÖZLEŞMESİ (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Crawler politikası (SEO/AIO görev kartı §12): Googlebot, Bingbot, OAI-SearchBot, PerplexityBot arama/görünürlük
 * botlarıdır — SERBEST; GPTBot eğitim amaçlıdır ve OAI-SearchBot'tan BAĞIMSIZ bir KURUM KARARIDIR (varsayılan
 * `undecided`: ayrı kural üretilmez, yalnız açıklama satırı). Meşru botların gerçek doğrulaması (WAF/CDN, resmî IP
 * aralıkları, ters DNS, log) bu dosyanın dışında, DevOps/güvenlik doğrulama zincirindedir (kart §12.1) — bu dosya
 * tek başına "bot doğrulandı" iddiası taşımaz.
 *
 * Üretim dışı ortamda (staging/yerel) HER ZAMAN `Disallow: /`. Gerçek robots.txt dosyasına/sunucuya dokunulmaz;
 * WordPress `robots_txt` filtresiyle sanal çıktıdır (fiziksel bir robots.txt varsa o kazanır ve deploy kılavuzu uyarır).
 * Süzgeçli/sayfalı URL'ler robots ile ENGELLENMEZ (noindex meta'sının görülebilmesi için); yalnız site içi arama ve yönetim.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Seo_Robots {

	const SEARCH_BOTS   = array( 'Googlebot', 'Bingbot', 'OAI-SearchBot', 'PerplexityBot' );
	const TRAINING_BOTS = array( 'GPTBot' );
	const POLICIES      = array( 'allow', 'disallow', 'undecided' );

	/** Ortak Disallow/Allow kuralları (tüm gruplarda aynı). */
	const COMMON_RULES = array(
		'Disallow: /wp-admin/',
		'Allow: /wp-admin/admin-ajax.php',
		'Disallow: /?s=',
		'Disallow: /*?s=',
		'Disallow: /search/',
	);

	/** Sitemap'e giren içerik türleri (kalın içerik sayfaları); ince tekil sayfalar (SSS, referans) ve herkese açık olmayan türler girmez. */
	const SITEMAP_POST_TYPES  = array( 'page', 'mb_yeterlilik', 'mb_haber', 'mb_dokuman', 'mb_lokasyon' );
	const SITEMAP_TAXONOMIES  = array( 'mb_sektor' );

	/**
	 * @param array $opts production (bool), sitemap_url (string), crawler_policy (array bot => allow|disallow|undecided)
	 * @return string
	 */
	public static function generate( array $opts ) {
		$sitemap = isset( $opts['sitemap_url'] ) && is_string( $opts['sitemap_url'] ) && 1 === preg_match( '#^https?://[^\s]+$#', $opts['sitemap_url'] ) ? $opts['sitemap_url'] : '';
		if ( empty( $opts['production'] ) ) {
			return "# Mavi Belge — üretim DIŞI ortam: hiçbir şey taranmamalı.\nUser-agent: *\nDisallow: /\n";
		}
		$policy = self::normalize_policy( isset( $opts['crawler_policy'] ) ? $opts['crawler_policy'] : array() );
		$lines  = array( '# Mavi Belge robots.txt — üretim politikası (SEO/AIO görev kartı §12).' );
		$lines[] = 'User-agent: *';
		$lines   = array_merge( $lines, self::COMMON_RULES, array( '' ) );
		foreach ( self::SEARCH_BOTS as $bot ) {
			$lines = array_merge( $lines, self::group( $bot, $policy[ $bot ] ) );
		}
		foreach ( self::TRAINING_BOTS as $bot ) {
			$lines = array_merge( $lines, self::group( $bot, $policy[ $bot ] ) );
		}
		if ( '' !== $sitemap ) {
			$lines[] = 'Sitemap: ' . $sitemap;
		}
		return implode( "\n", $lines ) . "\n";
	}

	/** Bilinmeyen bot/değer yok sayılır; eksik bot varsayılanı: arama botları allow, GPTBot undecided. */
	public static function normalize_policy( $raw ) {
		$out = array();
		foreach ( self::SEARCH_BOTS as $bot ) {
			$out[ $bot ] = 'allow';
		}
		foreach ( self::TRAINING_BOTS as $bot ) {
			$out[ $bot ] = 'undecided';
		}
		if ( is_array( $raw ) ) {
			foreach ( $out as $bot => $default ) {
				if ( isset( $raw[ $bot ] ) && is_string( $raw[ $bot ] ) && in_array( $raw[ $bot ], self::POLICIES, true ) ) {
					$out[ $bot ] = $raw[ $bot ];
				}
			}
		}
		return $out;
	}

	private static function group( $bot, $policy ) {
		if ( 'disallow' === $policy ) {
			return array( 'User-agent: ' . $bot, 'Disallow: /', '' );
		}
		if ( 'allow' === $policy ) {
			return array_merge( array( 'User-agent: ' . $bot ), self::COMMON_RULES, array( '' ) );
		}
		return array( '# ' . $bot . ': kurum kararı bekleniyor — ayrı kural yok (genel kurallar geçerli).', '' );
	}

	/** Bir içerik türü sitemap'e girebilir mi. */
	public static function sitemap_includes_post_type( $postType ) {
		return in_array( $postType, self::SITEMAP_POST_TYPES, true );
	}

	public static function sitemap_includes_taxonomy( $taxonomy ) {
		return in_array( $taxonomy, self::SITEMAP_TAXONOMIES, true );
	}
}
