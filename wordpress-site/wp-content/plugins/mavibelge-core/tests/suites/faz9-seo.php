<?php
/**
 * Faz 9 — SEO/AIO SAF testleri: title/description/canonical/robots/OG-Twitter (Seo_Meta), schema.org @graph
 * (Seo_Schema), robots.txt ve sitemap politikası (Seo_Robots). WordPress gerektirmez; test verisi sentetiktir.
 */

$SM = 'MaviBelge_Core_Seo_Meta';
$SS = 'MaviBelge_Core_Seo_Schema';
$SR = 'MaviBelge_Core_Seo_Robots';

/* ---------------- title / description ---------------- */
mb_test( 'Faz 9 title: marka soneki (— Mavi Belge) eklenir; marka zaten varsa (statik başlıklar) TEKRAR edilmez; boş -> yalnız marka',
	'Haberler — Mavi Belge' === $SM::compose_title( 'Haberler' ) && 'Doküman Merkezi — Mavi Belge' === $SM::compose_title( 'Doküman Merkezi — Mavi Belge' ) && 'Mavi Belge' === $SM::compose_title( '' ) && 'Mavi Belge' === $SM::compose_title( '   ' ) && 'Mavi Belge' === $SM::compose_title( '<b></b>' ) );
mb_test( 'Faz 9 title: en çok 65 karakter — markasız uzun başlık kesilip marka eklenir, markalı uzun başlık da sınırı aşmaz',
	$SM::TITLE_MAX >= mb_strlen( $SM::compose_title( str_repeat( 'Çok uzun bir başlık ', 12 ) ) ) && $SM::TITLE_MAX >= mb_strlen( $SM::compose_title( str_repeat( 'Çok uzun bir başlık ', 12 ) . ' — Mavi Belge' ) ) );
mb_test( 'Faz 9 title: kesilmiş başlık markayla biter ve toplam sınırı aşmaz',
	(function () use ( $SM ) {
		$t = $SM::compose_title( str_repeat( 'Uzun başlık kelimesi ', 10 ) );
		return mb_strlen( $t ) <= $SM::TITLE_MAX && ' — Mavi Belge' === mb_substr( $t, -13 );
	})() && '&lt;' !== $SM::compose_title( '<script>x</script>Ad' ) && false === strpos( $SM::compose_title( '<b>Ad</b>' ), '<' ) );
mb_test( 'Faz 9 description: editör > yedek; düz metin (etiket, kısa kod, satır sonu, script içeriği temizlenir); en çok 160 karakter',
	'Editör açıklaması' === $SM::description( 'Editör açıklaması', 'yedek' ) && 'yedek metin' === $SM::description( '', '<p>yedek   metin</p>' ) && '' === $SM::description( '', '' )
	&& 'kalın yazı' === $SM::description( '', "<p>kalın <strong>yazı</strong></p><script>alert(1)</script>" ) && 'a b' === $SM::description( '[gallery ids="1,2"] a' . "\n\n" . 'b', '' ) && mb_strlen( $SM::description( str_repeat( 'kelime ', 100 ), '' ) ) <= 160 );
mb_test( 'Faz 9 description: HTML varlıkları çözülür (&amp; -> &), sınır aşılırsa kelime sınırında … ile biter',
	'A & B' === $SM::description( 'A &amp; B', '' ) && '…' === mb_substr( $SM::description( str_repeat( 'kelime ', 100 ), '' ), -1 ) );
mb_test( 'Faz 9 editör alanı doğrulaması: sınır aşılırsa REDDEDİLİR (sessiz kırpma yok); dizi/nesne reddedilir; düz metne indirilir',
	true === $SM::validate_editor_field( 'title', 'Kısa' )['ok'] && false === $SM::validate_editor_field( 'title', str_repeat( 'a', 71 ) )['ok'] && true === $SM::validate_editor_field( 'title', str_repeat( 'a', 70 ) )['ok']
	&& false === $SM::validate_editor_field( 'description', str_repeat( 'a', 181 ) )['ok'] && true === $SM::validate_editor_field( 'description', str_repeat( 'a', 180 ) )['ok'] && false === $SM::validate_editor_field( 'title', array( 'x' ) )['ok'] && 'düz' === $SM::validate_editor_field( 'title', '<b>düz</b>' )['value'] );

/* ---------------- canonical ---------------- */
mb_test( 'Faz 9 canonical: sorgu dizgesi/parça atılır; sayfalı sayfa kendi sayfa numarasıyla (mb_page veya paged); ilk sayfa parametresiz',
	'https://ornek.example/haberler/' === $SM::canonical( array( 'url' => 'https://ornek.example/haberler/?mb_type=duyuru#x' ) )
	&& 'https://ornek.example/haberler/?mb_page=3' === $SM::canonical( array( 'url' => 'https://ornek.example/haberler/', 'page' => 3, 'page_arg' => 'mb_page' ) )
	&& 'https://ornek.example/haberler/?paged=2' === $SM::canonical( array( 'url' => 'https://ornek.example/haberler/', 'page' => 2, 'page_arg' => 'paged' ) )
	&& 'https://ornek.example/haberler/' === $SM::canonical( array( 'url' => 'https://ornek.example/haberler/', 'page' => 1 ) ) );
mb_test( 'Faz 9 canonical: arama, 404 veya URL yok -> canonical ÜRETİLMEZ (null); dizi/nesne url -> null',
	null === $SM::canonical( array( 'url' => 'https://ornek.example/', 'is_search' => true ) ) && null === $SM::canonical( array( 'url' => 'https://ornek.example/x/', 'is_404' => true ) ) && null === $SM::canonical( array() ) && null === $SM::canonical( array( 'url' => array( 'https://x' ) ) ) );
mb_test( 'Faz 9 filtreli URL: mb_q/mb_sector/mb_level/mb_priced/mb_type/mb_cat/mb_form_status filtre sayılır; sayfa/bilinmeyen parametre sayılmaz; boş değer sayılmaz',
	true === $SM::has_filters( array( 'mb_q' => 'kaynak' ) ) && true === $SM::has_filters( array( 'mb_sector' => 'makine', 'mb_page' => '2' ) ) && true === $SM::has_filters( array( 'mb_form_status' => 'success' ) ) && false === $SM::has_filters( array( 'mb_page' => '2' ) )
	&& false === $SM::has_filters( array( 'mb_q' => '' ) ) && false === $SM::has_filters( array( 'utm_source' => 'x' ) ) && false === $SM::has_filters( array() ) && true === $SM::has_filters( array( 'mb_q' => array( 'a' ) ) ) );

/* ---------------- robots ---------------- */
mb_test( 'Faz 9 robots: üretim dışı ortam VEYA blog_public=0 -> HER durumda noindex,nofollow (staging kapısı)',
	array( 'noindex', 'nofollow' ) === $SM::robots( array( 'production' => false ) ) && array( 'noindex', 'nofollow' ) === $SM::robots( array( 'production' => true, 'blog_public' => false ) ) && array( 'noindex', 'nofollow' ) === $SM::robots( array() )
	&& array( 'noindex', 'nofollow' ) === $SM::robots( array( 'production' => false, 'is_404' => false, 'has_filters' => false ) ) );
mb_test( 'Faz 9 robots: arama, filtreli URL, 404, editör noindex -> noindex,follow; normal sayfa -> index,follow + max-image-preview',
	array( 'noindex', 'follow' ) === $SM::robots( array( 'production' => true, 'is_search' => true ) ) && array( 'noindex', 'follow' ) === $SM::robots( array( 'production' => true, 'has_filters' => true ) ) && array( 'noindex', 'follow' ) === $SM::robots( array( 'production' => true, 'is_404' => true ) )
	&& array( 'noindex', 'follow' ) === $SM::robots( array( 'production' => true, 'editor_noindex' => true ) ) && array( 'index', 'follow', 'max-image-preview:large', 'max-snippet:-1' ) === $SM::robots( array( 'production' => true, 'blog_public' => true ) ) );

/* ---------------- Open Graph / Twitter ---------------- */
$sm_soc = $SM::social( array( 'title' => 'Başlık — Mavi Belge', 'description' => 'Açıklama', 'url' => 'https://ornek.example/x/', 'type' => 'article', 'image' => 'https://ornek.example/g.png', 'published' => '2026-01-01T10:00:00+00:00', 'modified' => '2026-02-01T10:00:00+00:00', 'twitter_site' => '' ) );
mb_test( 'Faz 9 sosyal: OG (tr_TR, site_name, type, title, description, url, image, article:*) ve Twitter (summary_large_image + görsel); twitter:site UYDURULMAZ',
	'tr_TR' === $sm_soc['og']['og:locale'] && 'article' === $sm_soc['og']['og:type'] && 'https://ornek.example/g.png' === $sm_soc['og']['og:image'] && '2026-01-01T10:00:00+00:00' === $sm_soc['og']['article:published_time'] && 'summary_large_image' === $sm_soc['twitter']['twitter:card']
	&& ! isset( $sm_soc['twitter']['twitter:site'] ) && ! isset( $sm_soc['twitter']['twitter:creator'] ) );
$sm_noimg = $SM::social( array( 'title' => 'T', 'description' => '', 'url' => null, 'type' => 'website', 'image' => 'javascript:alert(1)' ) );
mb_test( 'Faz 9 sosyal: görsel yoksa/güvensizse og:image/twitter:image yok, kart summary; boş açıklama/URL alanı yazılmaz; website türünde article alanları yok',
	! isset( $sm_noimg['og']['og:image'] ) && 'summary' === $sm_noimg['twitter']['twitter:card'] && ! isset( $sm_noimg['og']['og:description'] ) && ! isset( $sm_noimg['og']['og:url'] ) && ! isset( $sm_noimg['og']['article:published_time'] ) && 'website' === $sm_noimg['og']['og:type'] );
mb_test( 'Faz 9 sosyal: twitter:site yalnız AÇIKÇA verilmiş geçerli @kullanıcı adında; bozuk değer yok sayılır',
	'@ornekhesap' === $SM::social( array( 'title' => 'T', 'twitter_site' => '@ornekhesap' ) )['twitter']['twitter:site'] && ! isset( $SM::social( array( 'title' => 'T', 'twitter_site' => 'ornek' ) )['twitter']['twitter:site'] ) && ! isset( $SM::social( array( 'title' => 'T', 'twitter_site' => '@' . str_repeat( 'a', 30 ) ) )['twitter']['twitter:site'] ) );

/* ---------------- schema.org @graph ---------------- */
$ss_base = array(
	'home_url' => 'https://ornek.example/', 'site_name' => 'Mavi Belge', 'language' => 'tr-TR',
	'organization' => array( 'name' => 'Mavi Belge', 'logo' => 'https://ornek.example/logo.png', 'telephone' => '+90 000 000 00 00', 'email' => 'bilgi@ornek.example' ),
	'page' => array( 'url' => 'https://ornek.example/haberler/x/', 'name' => 'X Haberi — Mavi Belge', 'description' => 'Açıklama', 'published' => '2026-08-01T10:00:00+00:00' ),
	'breadcrumbs' => array( array( 'name' => 'Ana Sayfa', 'url' => 'https://ornek.example/' ), array( 'name' => 'Haberler', 'url' => 'https://ornek.example/haberler/' ), array( 'name' => 'X Haberi', 'url' => 'https://ornek.example/haberler/x/' ) ),
);
$ss_doc   = $SS::build( $ss_base );
$ss_types = array_column( $ss_doc['@graph'], '@type' );
mb_test( 'Faz 9 şema: temel @graph — Organization, WebSite, WebPage, BreadcrumbList; her biri TEK; geçerli (validate boş)',
	array( 'Organization', 'WebSite', 'WebPage', 'BreadcrumbList' ) === $ss_types && array() === $SS::validate( $ss_doc ) && 'https://schema.org' === $ss_doc['@context'] );
mb_test( 'Faz 9 şema: WebSite ve WebPage AYNI Organization\'a (@id) başvurur; WebPage breadcrumb düğümüne bağlanır; 3 öğeli breadcrumb sıralı (position 1..3)',
	'https://ornek.example/#organization' === $ss_doc['@graph'][1]['publisher']['@id'] && 'https://ornek.example/haberler/x/#breadcrumb' === $ss_doc['@graph'][2]['breadcrumb']['@id'] && array( 1, 2, 3 ) === array_column( $ss_doc['@graph'][3]['itemListElement'], 'position' ) );
mb_test( 'Faz 9 şema: Organization iletişim noktası yalnız verilen doğrulanmış veriden; logo/e-posta geçersizse yazılmaz; sameAs UYDURULMAZ',
	'customer service' === $ss_doc['@graph'][0]['contactPoint']['contactType'] && ! isset( $ss_doc['@graph'][0]['sameAs'] )
	&& ! isset( $SS::build( array_merge( $ss_base, array( 'organization' => array( 'name' => 'X', 'logo' => 'javascript:1', 'email' => 'bozuk' ) ) ) )['@graph'][0]['logo'] ) && ! isset( $SS::build( array_merge( $ss_base, array( 'organization' => array( 'name' => 'X', 'email' => 'bozuk' ) ) ) )['@graph'][0]['contactPoint'] ) );
$ss_news = $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'news', 'headline' => 'X Haberi', 'published' => '2026-08-01T10:00:00+00:00', 'modified' => '2026-08-02T10:00:00+00:00', 'description' => 'Özet' ) ) ) );
mb_test( 'Faz 9 şema: haber -> NewsArticle (başlık + yayın tarihi zorunlu), duyuru -> Article; mainEntityOfPage WebPage\'e bağlı; çelişkili tekrar YOK',
	'NewsArticle' === end( $ss_news['@graph'] )['@type'] && 'https://ornek.example/haberler/x/#webpage' === end( $ss_news['@graph'] )['mainEntityOfPage']['@id'] && array() === $SS::validate( $ss_news )
	&& 'Article' === end( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'announcement', 'headline' => 'D', 'published' => '2026-08-01T10:00:00+00:00' ) ) ) )['@graph'] )['@type']
	&& 4 === count( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'news', 'headline' => '', 'published' => '2026-08-01' ) ) ) )['@graph'] ) && 4 === count( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'news', 'headline' => 'H', 'published' => '' ) ) ) )['@graph'] ) );
$ss_faq = $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'faq', 'items' => array( array( 'q' => 'Soru?', 'a' => 'Cevap.' ), array( 'q' => '', 'a' => 'x' ), array( 'q' => 'Q', 'a' => '' ) ) ) ) ) );
mb_test( 'Faz 9 şema: FAQPage yalnız GÖRÜNÜR soru+cevap çiftlerinden (eksik soru/cevap atlanır); hiç çift yoksa düğüm YOK',
	'FAQPage' === end( $ss_faq['@graph'] )['@type'] && 1 === count( end( $ss_faq['@graph'] )['mainEntity'] ) && 'Answer' === end( $ss_faq['@graph'] )['mainEntity'][0]['acceptedAnswer']['@type'] && 4 === count( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'faq', 'items' => array() ) ) ) )['@graph'] ) );
mb_test( 'Faz 9 şema: sektör -> ItemList (en çok 50, geçersiz URL atlanır); belge -> DigitalDocument yalnız doğrulanmış dosya biçimi varsa; lokasyon -> Place+PostalAddress (harita yalnız web URL)',
	'ItemList' === end( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'sector', 'items' => array( array( 'name' => 'A', 'url' => 'https://ornek.example/a/' ), array( 'name' => 'B', 'url' => 'javascript:1' ) ) ) ) ) )['@graph'] )['@type']
	&& 1 === end( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'sector', 'items' => array( array( 'name' => 'A', 'url' => 'https://ornek.example/a/' ), array( 'name' => 'B', 'url' => 'javascript:1' ) ) ) ) ) )['@graph'] )['numberOfItems']
	&& 50 === end( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'sector', 'items' => array_map( function ( $i ) {
		return array( 'name' => 'M' . $i, 'url' => 'https://ornek.example/m' . $i . '/' );
	}, range( 1, 80 ) ) ) ) ) )['@graph'] )['numberOfItems']
	&& 'DigitalDocument' === end( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'document', 'name' => 'D', 'format' => 'application/pdf', 'version' => 'v1' ) ) ) )['@graph'] )['@type']
	&& 4 === count( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'document', 'name' => 'D', 'format' => '' ) ) ) )['@graph'] )
	&& 'Place' === end( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'location', 'name' => 'L', 'address' => 'Adres', 'map' => 'javascript:1' ) ) ) )['@graph'] )['@type']
	&& ! isset( end( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'location', 'name' => 'L', 'address' => 'Adres', 'map' => 'javascript:1' ) ) ) )['@graph'] )['hasMap'] ) );
mb_test( 'Faz 9 şema: Course, Product, Offer, Review, AggregateRating ve fiyat düğümü ASLA üretilmez (uydurma fiyat/referans iddiası yok)',
	(function () use ( $SS, $ss_base ) {
		foreach ( array( 'news', 'announcement', 'faq', 'sector', 'qualification', 'document', 'location', 'reference', 'fee' ) as $kind ) {
			foreach ( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => $kind, 'headline' => 'H', 'published' => '2026-01-01', 'name' => 'N', 'format' => 'application/pdf', 'address' => 'A', 'items' => array( array( 'q' => 'q', 'a' => 'a', 'name' => 'n', 'url' => 'https://ornek.example/' ) ) ) ) ) )['@graph'] as $node ) {
				if ( in_array( $node['@type'], array( 'Course', 'Product', 'Offer', 'Review', 'AggregateRating', 'Organization' ), true ) && 'Organization' !== $node['@type'] ) {
					return false;
				}
			}
		}
		return 4 === count( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'qualification' ) ) ) )['@graph'] ) && 4 === count( $SS::build( array_merge( $ss_base, array( 'entity' => array( 'kind' => 'reference' ) ) ) )['@graph'] );
	})() );
mb_test( 'Faz 9 şema doğrulama: yinelenen @id, çözülemeyen @id başvurusu, eksik zorunlu alan, çift Organization/WebPage reddedilir',
	(function () use ( $SS, $ss_doc ) {
		$dup = $ss_doc;
		$dup['@graph'][] = $dup['@graph'][0];
		$badRef = $ss_doc;
		$badRef['@graph'][2]['breadcrumb'] = array( '@id' => 'https://ornek.example/yok/#breadcrumb' );
		$missing = $ss_doc;
		unset( $missing['@graph'][2]['name'] );
		$twoPages = $ss_doc;
		$twoPages['@graph'][] = array_merge( $ss_doc['@graph'][2], array( '@id' => 'https://ornek.example/baska/#webpage' ) );
		return ! empty( $SS::validate( $dup ) ) && ! empty( $SS::validate( $badRef ) ) && ! empty( $SS::validate( $missing ) ) && ! empty( $SS::validate( $twoPages ) ) && ! empty( $SS::validate( array() ) ) && ! empty( $SS::validate( array( '@context' => 'https://schema.org', '@graph' => array() ) ) );
	})() );
mb_test( 'Faz 9 şema: eksik zorunlu bağlam (home_url/site_name/page url+name) -> boş dizi (fail-closed, yarım şema yok)',
	array() === $SS::build( array() ) && array() === $SS::build( array_merge( $ss_base, array( 'home_url' => '' ) ) ) && array() === $SS::build( array_merge( $ss_base, array( 'page' => array( 'url' => '', 'name' => 'x' ) ) ) ) && array() === $SS::build( array_merge( $ss_base, array( 'page' => array( 'url' => 'https://ornek.example/x/', 'name' => '' ) ) ) ) );
$ss_evil = $SS::build( array_merge( $ss_base, array( 'page' => array( 'url' => 'https://ornek.example/x/', 'name' => '</script><script>alert(1)</script>"\'&', 'description' => "satır\n<b>x</b>" ) ) ) );
$ss_json = $SS::to_json( $ss_evil );
mb_test( 'Faz 9 şema JSON güvenliği: </script>, <, >, &, \', " karakterleri \\u kaçışlıdır (script enjeksiyonu yok); JSON geri çözülür ve eşit',
	'' !== $ss_json && false === strpos( $ss_json, '</script>' ) && false === strpos( $ss_json, '<' ) && false === strpos( $ss_json, '>' ) && false !== stripos( $ss_json, chr( 92 ) . 'u003c' ) && $ss_evil === json_decode( $ss_json, true ) );
mb_test( 'Faz 9 şema JSON: Türkçe karakterler ve eğik çizgiler kaçışsız (okunabilir); kodlanamayan girdi (geçersiz UTF-8) -> boş dize',
	false !== strpos( $SS::to_json( $ss_doc ), 'https://ornek.example/haberler/x/' ) && false !== strpos( $SS::to_json( $SS::build( array_merge( $ss_base, array( 'page' => array( 'url' => 'https://ornek.example/x/', 'name' => 'Çağrı Şükrü Ğ' ) ) ) ) ), 'Çağrı Şükrü Ğ' ) && '' === $SS::to_json( array( 'x' => "\xB1\x31" ) ) );

/* ---------------- robots.txt ve sitemap ---------------- */
$sr_prod = $SR::generate( array( 'production' => true, 'sitemap_url' => 'https://ornek.example/wp-sitemap.xml' ) );
mb_test( 'Faz 9 robots.txt: üretimde * grubu (wp-admin kapalı, admin-ajax açık, site içi arama kapalı), 4 arama botu SERBEST grup, GPTBot kurum kararı (kural yok, yorum), Sitemap satırı',
	false !== strpos( $sr_prod, "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php" ) && false !== strpos( $sr_prod, 'Disallow: /?s=' ) && false !== strpos( $sr_prod, 'User-agent: Googlebot' ) && false !== strpos( $sr_prod, 'User-agent: Bingbot' ) && false !== strpos( $sr_prod, 'User-agent: OAI-SearchBot' ) && false !== strpos( $sr_prod, 'User-agent: PerplexityBot' )
	&& false === strpos( $sr_prod, 'User-agent: GPTBot' ) && false !== strpos( $sr_prod, '# GPTBot: kurum kararı bekleniyor' ) && false !== strpos( $sr_prod, 'Sitemap: https://ornek.example/wp-sitemap.xml' ) && "\n" === substr( $sr_prod, -1 ) );
mb_test( 'Faz 9 robots.txt: üretimde süzgeçli/sayfalı URL\'ler ENGELLENMEZ (noindex meta\'sı görülebilsin) ve tüm siteyi kapatan "Disallow: /" satırı yok',
	false === strpos( $sr_prod, 'mb_' ) && 0 === preg_match( '/^Disallow: \/\s*$/m', $sr_prod ) );
mb_test( 'Faz 9 robots.txt: üretim DIŞI ortamda tek grup "User-agent: * / Disallow: /" (sitemap yok, bot izni yok)',
	"# Mavi Belge — üretim DIŞI ortam: hiçbir şey taranmamalı.\nUser-agent: *\nDisallow: /\n" === $SR::generate( array( 'production' => false, 'sitemap_url' => 'https://ornek.example/wp-sitemap.xml', 'crawler_policy' => array( 'Googlebot' => 'allow' ) ) ) && "# Mavi Belge — üretim DIŞI ortam: hiçbir şey taranmamalı.\nUser-agent: *\nDisallow: /\n" === $SR::generate( array() ) );
mb_test( 'Faz 9 crawler politikası: GPTBot disallow -> ayrı Disallow: / grubu (OAI-SearchBot\'tan BAĞIMSIZ); bilinmeyen bot/değer yok sayılır; GPTBot kararı OAI-SearchBot\'u etkilemez',
	(function () use ( $SR ) {
		$t = $SR::generate( array( 'production' => true, 'crawler_policy' => array( 'GPTBot' => 'disallow', 'OAI-SearchBot' => 'allow', 'EvilBot' => 'disallow', 'PerplexityBot' => 'bogus' ) ) );
		return false !== strpos( $t, "User-agent: GPTBot\nDisallow: /\n" ) && false !== strpos( $t, "User-agent: OAI-SearchBot\nDisallow: /wp-admin/" ) && false === strpos( $t, 'EvilBot' ) && false !== strpos( $t, "User-agent: PerplexityBot\nDisallow: /wp-admin/" );
	})() );
mb_test( 'Faz 9 crawler politikası normalizasyonu: varsayılan arama botları allow, GPTBot undecided; bozuk girdi varsayılana düşer',
	array( 'Googlebot' => 'allow', 'Bingbot' => 'allow', 'OAI-SearchBot' => 'allow', 'PerplexityBot' => 'allow', 'GPTBot' => 'undecided' ) === $SR::normalize_policy( null ) && 'disallow' === $SR::normalize_policy( array( 'GPTBot' => 'disallow' ) )['GPTBot'] && 'undecided' === $SR::normalize_policy( array( 'GPTBot' => array( 'disallow' ) ) )['GPTBot'] );
mb_test( 'Faz 9 sitemap politikası: sayfa, yeterlilik, haber, doküman, lokasyon + sektör terimi dahil; ücret, SSS, referans, haber türü ve kategori taksonomileri HARİÇ',
	$SR::sitemap_includes_post_type( 'page' ) && $SR::sitemap_includes_post_type( 'mb_yeterlilik' ) && $SR::sitemap_includes_post_type( 'mb_haber' ) && $SR::sitemap_includes_post_type( 'mb_dokuman' ) && $SR::sitemap_includes_post_type( 'mb_lokasyon' )
	&& ! $SR::sitemap_includes_post_type( 'mb_ucret' ) && ! $SR::sitemap_includes_post_type( 'mb_sss' ) && ! $SR::sitemap_includes_post_type( 'mb_referans' ) && ! $SR::sitemap_includes_post_type( 'attachment' ) && $SR::sitemap_includes_taxonomy( 'mb_sektor' ) && ! $SR::sitemap_includes_taxonomy( 'mb_haber_turu' ) && ! $SR::sitemap_includes_taxonomy( 'mb_dokuman_kategori' ) && ! $SR::sitemap_includes_taxonomy( 'category' ) );

/* ---------------- statik sayfa başlangıç değerleri ---------------- */
$sp_defaults = include dirname( __DIR__, 2 ) . '/includes/seo/data/page-defaults.php';
mb_test( 'Faz 9 başlangıç değerleri: statik referanstan 41 sayfanın 41 BENZERSİZ title\'ı ve dolu description\'ı (marka soneki kaynakta var); ana sayfa anahtarı front-page',
	41 === count( $sp_defaults ) && 41 === count( array_unique( array_column( $sp_defaults, 0 ) ) ) && isset( $sp_defaults['front-page'], $sp_defaults['haberler'], $sp_defaults['dokumanlar'], $sp_defaults['404'] ) && 41 === count( array_filter( $sp_defaults, function ( $d ) {
		return '' !== trim( $d[0] ) && '' !== trim( $d[1] );
	} ) ) );
