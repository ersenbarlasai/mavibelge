/**
 * Static SOURCE-TEXT contract (Faz 12e): yeterlilik detay (single-mb_yeterlilik.php) ve arşiv (archive-mb_yeterlilik.php)
 * ekranları statik referansın (tanitim-site/yeterlilik.html, meslekler.html) kompozisyonunu izler; gerçek veri, GET filtre
 * sözleşmesi, ücret ilişkisi, permalink çözümleyicisi ve erişilebilirlik korunur. PHP/tarayıcı çalıştırmaz — davranış
 * gerçek WordPress'te tools/runtime-test/qualification-render.sh ile ayrıca sınanır.
 *
 * Run: node tests/static/qualification-screens-contract.test.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );

var root = path.join( __dirname, '..', '..' );
var failures = [];
var checks = 0;
function check( label, ok ) {
	checks++;
	if ( ! ok ) {
		failures.push( label );
	}
}
function read( rel ) {
	return fs.readFileSync( path.join( root, rel ), 'utf8' );
}
function code( s ) {
	// PHP/CSS yorumlarını çıkar (docblock'taki açıklamalar sözleşmeyi etkilemesin).
	return s.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /^\s*\/\/.*$/gm, '' );
}

var single = code( read( 'single-mb_yeterlilik.php' ) );
var archive = code( read( 'archive-mb_yeterlilik.php' ) );
var card = code( read( 'template-parts/catalog/qualification-card.php' ) );
var browse = code( read( 'template-parts/catalog/sector-browse.php' ) );
var pagination = code( read( 'template-parts/components/pagination.php' ) );
var filterForm = code( read( 'template-parts/catalog/filter-form.php' ) );
var feeOptions = code( read( 'template-parts/catalog/fee-options.php' ) );
var helpers = code( read( 'inc/qualification-helpers.php' ) );
var bootstrap = read( 'inc/bootstrap.php' );
var pagesCss = read( 'assets/src/css/pages.css' );
var layoutCss = read( 'assets/src/css/layout.css' );
var respCss = read( 'assets/src/css/responsive.css' );
var baseCss = read( 'assets/src/css/base.css' );
var dist = read( 'assets/dist/style.css' );

/* ---------- ortak ---------- */
check( 'inc/qualification-helpers.php bootstrap tarafından urls.php SONRASI yüklenir', /urls\.php[\s\S]*qualification-helpers\.php/.test( bootstrap ) );
check( 'helper: sektör kahraman görseli _mb_image_attachment_id + attachment/görsel/okunabilir dosya doğrulaması + jenerik fallback', /_mb_image_attachment_id/.test( helpers ) && /wp_attachment_is_image\(/.test( helpers ) && /is_readable\(/.test( helpers ) && /hero-generic\.svg/.test( helpers ) && /get_theme_file_uri\(/.test( helpers ) );
check( 'helper: bozuk (dizi/metin) meta sayı sayılmaz (ctype_digit/is_int), (int) cast körü körüne yapılmaz', /ctype_digit\(/.test( helpers ) && ! /\(int\)\s*get_term_meta/.test( helpers ) );
check( 'helper: başvuru URL mavibelge_url(online-basvuru) + add_query_arg(meslek, rawurlencode)', /mavibelge_url\(\s*'online-basvuru'\s*\)/.test( helpers ) && /add_query_arg\(\s*'meslek',\s*rawurlencode\(/.test( helpers ) );
[ [ 'single', single ], [ 'archive', archive ], [ 'card', card ], [ 'browse', browse ], [ 'helpers', helpers ] ].forEach( function ( p ) {
	check( p[ 0 ] + ': /index.php/ gömülü değil', ! /\/index\.php/.test( p[ 1 ] ) );
	check( p[ 0 ] + ': home_url(\'/slug/\') ile dahili bağlantı yok', ! /home_url\(\s*'\/[a-z]/.test( p[ 1 ] ) );
	check( p[ 0 ] + ': WP_Query/meta_query/$wpdb/mb_ucret sorgusu yok', ! /new\s+WP_Query|meta_query|\$wpdb\s*->|'mb_ucret'/.test( p[ 1 ] ) );
	check( p[ 0 ] + ': tanitim-site yolu veya tahmini görsel adı yok', ! /tanitim-site|real-[a-z-]+\.png/.test( p[ 1 ] ) );
} );

/* ---------- detay ---------- */
check( 'detay: page-hero bölümü (qual-hero) ve sektör görseli helper ile', /class="page-hero qual-hero"/.test( single ) && /mavibelge_term_hero_image_url\(/.test( single ) );
var heroStart = single.indexOf( 'class="page-hero qual-hero"' );
var heroEnd = single.indexOf( '</section>', heroStart );
var hero = single.slice( heroStart, heroEnd );
check( 'detay: breadcrumb kahraman alanının İÇİNDE', /components\/breadcrumb/.test( hero ) );
check( 'detay: eyebrow "MYK Kodu:" gerçek kod ile, H1 the_title, sektör gerçek term linki, seviye', /MYK Kodu: %s/.test( hero ) && /<h1>/.test( hero ) && /the_title\(\)/.test( hero ) && /get_term_link\(/.test( hero ) && /is_wp_error\(/.test( hero ) && /Seviye %s/.test( hero ) );
check( 'detay: tek H1 (şablonda tek <h1>)', ( single.match( /<h1[ >]/g ) || [] ).length === 1 );
check( 'detay: iki sütunlu qual-detail-grid + sağda aside.sticky-cta', /class="container qual-detail-grid"/.test( single ) && /<aside class="sticky-cta"/.test( single ) );
[ 'Yeterlilik Özeti', 'Yeterlilik Birimleri', 'Sınav Yapısı', 'Belge Geçerliliği', 'İlgili Dokümanlar' ].forEach( function ( h, i, arr ) {
	check( 'detay: H2 "' + h + '"', new RegExp( "<h2[^>]*>\\s*<\\?php esc_html_e\\( '" + h + "'" ).test( single ) );
	if ( i > 0 ) {
		check( 'detay: "' + arr[ i - 1 ] + '" "' + h + '"\'dan önce', single.indexOf( "'" + arr[ i - 1 ] + "'" ) < single.indexOf( "'" + h + "'" ) );
	}
} );
check( 'detay: dört bilgi kartı (MYK Kodu/Seviye/Revizyon/Sektör) qual-facts içinde', /class="qual-facts"/.test( single ) && [ 'MYK Kodu', 'Seviye', 'Revizyon', 'Sektör' ].every( function ( l ) { return single.indexOf( "esc_html_e( '" + l + "'" ) !== -1; } ) );
check( 'detay: revizyon yoksa sahte değer yerine "Kurum tarafından doğrulanacak"', /_mb_revision/.test( single ) && /Kurum tarafından doğrulanacak/.test( single ) );
check( 'detay: editör içeriği doluysa önce o (the_content), boşsa etiketli genel açıklama', /get_the_content\(\)/.test( single ) && /the_content\(\)/.test( single ) && /qual-placeholder/.test( single ) );
check( 'detay: örnek birimler "Örnek yapı" uyarısıyla ve is-sample işaretiyle (doğrulanmış gibi DEĞİL)', /Örnek yapı/.test( single ) && /unit-list is-sample/.test( single ) );
check( 'detay: sınav yapısı ve doküman "Bilgi güncellenecektir" ile etiketli; sahte doküman URL yok', ( single.match( /Bilgi güncellenecektir/g ) || [] ).length >= 2 && /mavibelge_url\(\s*'dokumanlar'\s*\)/.test( single ) && ! /\.pdf/.test( single ) );
check( 'detay: belge yenileme bağlantısı mavibelge_url', /mavibelge_url\(\s*'belge-yenileme'\s*\)/.test( single ) );
var asideStart = single.indexOf( '<aside class="sticky-cta"' );
var aside = single.slice( asideStart, single.indexOf( '</aside>', asideStart ) );
check( 'detay: ücret yalnız aside içinde (fee-options) — gövdede ikinci kez YOK', /catalog\/fee-options/.test( aside ) && ( single.match( /catalog\/fee-options/g ) || [] ).length === 1 && ! /qual-fee-section/.test( single ) );
check( 'detay: ücret gerçek ilişki (mavibelge_get_active_fees_for_qualification) üzerinden', /mavibelge_get_active_fees_for_qualification\(\s*\$post_id\s*\)/.test( aside ) );
check( 'detay: ücret yoksa bilgi mesajı + Sınav Ücretleri bağlantısı; fiyat yok', /şu an güncel bir ücret kaydı bulunmuyor/.test( aside ) && /mavibelge_url\(\s*'sinav-ucretleri'\s*\)/.test( aside ) );
check( 'detay: "Sınav Ücretlerini Gör" ve "Bu Yeterlilik İçin Başvur" düğmeleri; başvuru helper ile', /Sınav Ücretlerini Gör/.test( aside ) && /Bu Yeterlilik İçin Başvur/.test( aside ) && /mavibelge_application_url\(/.test( aside ) );
check( 'detay: aside başlıkları H2 (H1->H2 sırası; H3 atlaması yok)', /<h2 class="qual-cta-title">/.test( aside ) && ! /<h3/.test( single ) );
check( 'fee-options: çok seçenekli ücret aside\'da açık gösterilebilir (expanded), tüm seçenekler korunur', /expanded/.test( feeOptions ) && /foreach \( \$options as \$option \)/.test( feeOptions ) );
check( 'detay: doküman ikonu merkezi ikon sisteminden (mavibelge_icon_svg ui/document)', /mavibelge_icon_svg\(\s*'ui',\s*'document'/.test( single ) && /'document'\s*=>/.test( read( 'inc/icons.php' ) ) );

/* ---------- arşiv ---------- */
check( 'arşiv: page-hero (Tüm Meslekler ve Yeterlilikler + açıklama + eyebrow + breadcrumb)', /page\/page-hero/.test( archive ) && /Tüm Meslekler ve Yeterlilikler/.test( archive ) && /MYK ulusal yeterlilik sistemine göre belgelendirdiğimiz tüm meslekleri arayın ve filtreleyin\./.test( archive ) && /'eyebrow'\s*=>\s*__\( 'Meslekler ve Belgeler'/.test( archive ) && /breadcrumb_items/.test( archive ) );
check( 'arşiv: filtre formu gerçek archive action + GET', /get_post_type_archive_link\(\s*'mb_yeterlilik'\s*\)/.test( archive ) && /catalog\/filter-form/.test( archive ) && /method="get"/.test( filterForm ) );
[ 'mb_q', 'mb_sector', 'mb_level', 'mb_priced' ].forEach( function ( k ) {
	check( 'filtre formu ' + k + ' denetimini korur', new RegExp( 'name="' + k + '"' ).test( filterForm ) );
} );
check( 'filtre: "Yalnız güncel fiyatı bulunanlar" ikincil satırda korunur; Filtrele + aktifse Temizle', /filter-row-secondary/.test( filterForm ) && /Yalnız güncel fiyatı bulunanlar/.test( filterForm ) && /Filtrele/.test( filterForm ) && /\$has_active_filter/.test( filterForm ) );
check( 'arşiv: sonuçlar qual-list + qualification-card (DTO)', /class="qual-list"/.test( archive ) && /catalog\/qualification-card/.test( archive ) && /mavibelge_get_qualification_results\(/.test( archive ) );
check( 'arşiv: sonuç özeti + filtreli pagination (mavibelge_catalog_page_url)', /catalog\/results-meta/.test( archive ) && /mavibelge_catalog_page_url\(/.test( archive ) );
var iResults = archive.indexOf( 'catalog/qualification-card' );
var iPag = archive.indexOf( 'components/pagination' );
var iBrowse = archive.indexOf( 'catalog/sector-browse' );
check( 'arşiv: "Sektöre Göre Gözat" sonuçlar ve sayfalamadan SONRA; eski sonuç-öncesi sektör düğmeleri yok', iBrowse > iPag && iPag > iResults && ! /class="sector-grid"/.test( archive ) );
check( 'arşiv: tek H1 (page-hero), şablonda <h1> yok ve section-heading level 1 yok', ! /<h1/.test( archive ) && ! /'level'\s*=>\s*1/.test( archive ) );

check( 'kart: iki gerçek aksiyon (Detayları Gör -> permalink, Başvuru Yap -> helper)', /Detayları Gör/.test( card ) && /Başvuru Yap/.test( card ) && /\$item\['permalink'\]/.test( card ) && /mavibelge_application_url\(/.test( card ) );
check( 'kart: iç içe bağlantı yok (kart kabı <a> değil; tam iki <a>)', /<article class="qual-card">/.test( card ) && ( card.match( /<a\s/g ) || [] ).length === 2 && ! /components\/card/.test( card ) );
check( 'kart: başlık H2 (sayfa H1 altında), MYK kodu / seviye / sektör etiketleri DTO\'dan', /<h2 class="qual-card-title">/.test( card ) && /\$item\['myk_code'\]/.test( card ) && /\$item\['level'\]/.test( card ) && /\$item\['sectors'\]/.test( card ) );
check( 'kart: bağlantı adları görünür metni içerir (label-in-name)', /Detayları Gör: %s/.test( card ) && /Başvuru Yap: %s/.test( card ) );
check( 'kart: get_post/get_post_meta ile yeniden sorgu yok', ! /get_post_meta\(|get_post\(|get_the_terms\(/.test( card ) );

check( 'sektör bölümü: bg-light + H2 "Sektöre Göre Gözat" + sector-grid', /class="bg-light[ "]/.test( browse ) && /Sektöre Göre Gözat/.test( browse ) && /<h2[ >]/.test( browse ) && /class="sector-grid"/.test( browse ) );
check( 'sektör bölümü: ikon _mb_icon_key + mavibelge_icon_svg (is_string korumalı), link get_term_link + is_wp_error', /_mb_icon_key/.test( browse ) && /mavibelge_icon_svg\(\s*'sector'/.test( browse ) && /is_string\(\s*\$icon_key\s*\)/.test( browse ) && /get_term_link\(/.test( browse ) && /is_wp_error\(/.test( browse ) );
check( 'sektör bölümü: sabit sektör listesi yok; terimler çağırandan (mavibelge_get_sector_terms)', ! /'makine'|'tekstil'|sektor\.html/.test( browse ) && /mavibelge_get_sector_terms\(\)/.test( archive ) );
check( 'sektör kartı: "Meslekleri İncele →" ve ikon kabı aria-hidden', /Meslekleri İncele →/.test( browse ) && /class="sector-icon" aria-hidden="true"/.test( browse ) );

check( 'pagination: Önceki/Sonraki, aria-current, erişilebilir nav etiketi, tek sayfada gösterilmez', /Önceki/.test( pagination ) && /Sonraki/.test( pagination ) && /aria-current="page"/.test( pagination ) && /aria-label="<\?php esc_attr_e\( 'Sayfalama'/.test( pagination ) && /\$total\s*<\s*2/.test( pagination ) && /rel="prev"/.test( pagination ) && /rel="next"/.test( pagination ) && /aria-disabled="true"/.test( pagination ) );

/* ---------- CSS: responsive / a11y ---------- */
check( 'CSS: qual-list 3 sütun; 1024px altında 2; 768px altında 1', /\.qual-list \{ display: grid; grid-template-columns: repeat\(3, 1fr\)/.test( pagesCss ) && /@media \(max-width: 1024px\)[\s\S]*?\.qual-list \{ grid-template-columns: repeat\(2, 1fr\); \}/.test( respCss ) && /@media \(max-width: 768px\)[\s\S]*?\.qual-list \{ grid-template-columns: 1fr; \}/.test( respCss ) );
check( 'CSS: qual-detail-grid 2fr 1fr; 1024px altında tek sütun ve sticky normal akışa döner', /\.qual-detail-grid \{ display: grid; grid-template-columns: 2fr 1fr/.test( pagesCss ) && /@media \(max-width: 1024px\)[\s\S]*?\.qual-detail-grid \{ grid-template-columns: 1fr; \}[\s\S]*?\.sticky-cta \{ position: static; \}/.test( respCss ) );
check( 'CSS: sector-grid 6 sütun; 1024px altında 3, 768px altında 2', /\.sector-grid \{\s*display: grid;\s*grid-template-columns: repeat\(6, 1fr\)/.test( pagesCss ) && /\.sector-grid \{ grid-template-columns: repeat\(3, 1fr\); \}/.test( respCss ) && /\.sector-grid \{ grid-template-columns: 1fr 1fr; \}/.test( respCss ) );
check( 'CSS: kart aksiyonları alta hizalı (margin-top:auto) ve uzun başlık taşmaz (overflow-wrap)', /\.qual-list \.qual-card \.qual-actions \{ margin-top: auto; \}/.test( pagesCss ) && /\.qual-card-title \{[^}]*overflow-wrap: anywhere/.test( pagesCss ) && /\.qual-hero h1 \{[^}]*overflow-wrap: anywhere/.test( pagesCss ) );
check( 'CSS: görünür odak (qual-card/sector-card/page-btn focus-visible)', /\.qual-actions \.btn:focus-visible/.test( pagesCss ) && /\.sector-card:focus-visible/.test( pagesCss ) && /\.page-btn:focus-visible/.test( read( 'assets/src/css/components.css' ) ) );
check( 'CSS: forced-colors kart/sayfa düğmesi kenarlıkları', /@media \(forced-colors: active\)[\s\S]*?\.qual-card[\s\S]*?\.sector-card[\s\S]*?\.page-btn/.test( pagesCss ) );
check( 'CSS: prefers-reduced-motion genel kuralı (base.css)', /prefers-reduced-motion: reduce[\s\S]*transition-duration: 0\.01ms/.test( baseCss ) );
check( 'CSS: page-hero eyebrow koyu zeminde okunur renk', /\.page-hero \.eyebrow \{ color: #9dc1f7; \}/.test( layoutCss ) );
check( 'CSS: body overflow-x clip (hidden yalnız yedek) — body kaydırma kabı olmaz, sticky başlık/CTA çalışır (tarayıcıda bulunan hata)', /body \{[^}]*overflow-x: hidden;[^}]*overflow-x: clip;/.test( baseCss ) );
check( 'CSS: koyu kahramanda geçerli sayfa kırıntısı açık renkte (tarayıcıda bulunan kontrast hatası)', /\.page-hero \.breadcrumb \[aria-current="page"\] \{ color: var\(--color-gray-100\); \}/.test( layoutCss ) );
check( 'CSS: sayfa düğmeleri (a/span) dikeyde ortalı', /\.page-btn \{\s*display: inline-flex; align-items: center; justify-content: center;/.test( read( 'assets/src/css/components.css' ) ) );
check( 'CSS: ücret seçenekleri dar kartta taşmaz (fee-options-list etiket kırılır)', /\.qual-cta-card \.fee-option-label \{[^}]*overflow-wrap: anywhere/.test( pagesCss ) );
check( 'dist/style.css kaynak kuralları içerir (build ile üretilmiş)', dist.indexOf( '.qual-list .qual-card .qual-actions { margin-top: auto; }' ) !== -1 && dist.indexOf( '.page-hero .eyebrow { color: #9dc1f7; }' ) !== -1 && dist.indexOf( '.sticky-cta { position: static; }' ) !== -1 );

if ( failures.length ) {
	failures.forEach( function ( f ) {
		console.log( 'FAIL  ' + f );
	} );
	console.log( failures.length + '/' + checks + ' başarısız' );
	process.exit( 1 );
}
console.log( 'Tüm ' + checks + ' yeterlilik ekranı sözleşme testi geçti (statik kaynak taraması; tarayıcı render değil).' );
