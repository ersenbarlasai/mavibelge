/**
 * Static SOURCE-TEXT contract (Faz 13 — referans sayfa ailesi uyumu): 14 hedef rota tek merkezi sunum kaydına
 * (inc/page-layouts.php) bağlıdır; gövdeyi TEK ortak parça (template-parts/page/content-presentation.php) çizer; arşiv ve
 * taksonomi ekranları ortak page-hero kullanır; banka/haber/doküman verisi temaya gömülmez; /index.php/ koda gömülmez;
 * form kapısı ve alan adları korunur. Davranış gerçek WordPress + Chrome'da tools/runtime-test/page-parity-test.js ile
 * ayrıca sınanır. PHP/tarayıcı çalıştırmaz.
 *
 * Run: node tests/static/page-presentation-contract.test.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );
var cp = require( 'child_process' );

var root = path.join( __dirname, '..', '..' );
var repo = path.join( root, '..', '..', '..', '..' );
var failures = [];
var checks = 0;
function check( label, ok ) {
	checks++;
	if ( ! ok ) {
		failures.push( label );
	}
}
function exists( rel ) {
	return fs.existsSync( path.join( root, rel ) );
}
function read( rel ) {
	return exists( rel ) ? fs.readFileSync( path.join( root, rel ), 'utf8' ) : '';
}
function code( s ) {
	return s.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /^\s*\/\/.*$/gm, '' ).replace( /<!--[\s\S]*?-->/g, '' );
}
function walk( dir, out ) {
	fs.readdirSync( dir, { withFileTypes: true } ).forEach( function ( e ) {
		var p = path.join( dir, e.name );
		if ( e.isDirectory() ) {
			if ( 'tests' !== e.name && 'node_modules' !== e.name ) {
				walk( p, out );
			}
		} else {
			out.push( p );
		}
	} );
	return out;
}

var page = code( read( 'page.php' ) );
var layouts = code( read( 'inc/page-layouts.php' ) );
var helpers = code( read( 'inc/presentation-helpers.php' ) );
var bootstrap = code( read( 'inc/bootstrap.php' ) );
var body = code( read( 'template-parts/page/content-presentation.php' ) );
var formOff = code( read( 'template-parts/page/content-form-disabled.php' ) );
var formLive = code( read( 'template-parts/page/content-form-live.php' ) );
var news = code( read( 'archive-mb_haber.php' ) );
var docs = code( read( 'archive-mb_dokuman.php' ) );
var tax = code( read( 'taxonomy.php' ) );
var css = read( 'assets/src/css/pages.css' );
var resp = read( 'assets/src/css/responsive.css' );
var dist = read( 'assets/dist/style.css' );

// 1. Rota -> aile kaydı (tek merkez)
var FAMILY = {
	'sinav-surecleri': 'list',
	'banka-hesap-bilgileri': 'card',
	'yetki-akreditasyon': 'cards',
	'mevzuat': 'list',
	'myk': 'prose',
	'turkak': 'prose',
	'nasil-basvururum': 'steps',
	'sinav-takvimi': 'card',
	'sonuc-belge-sorgulama': 'card',
	'itiraz-sikayet': 'split',
	'belge-yenileme': 'cards'
};
check( 'page-layouts: mavibelge_page_presentation() tanımlı', /function mavibelge_page_presentation\(\s*\$slug\s*\)/.test( layouts ) );
var reg = ( layouts.match( /function mavibelge_page_presentation[\s\S]*?\n}\n/ ) || [ '' ] )[ 0 ];
Object.keys( FAMILY ).forEach( function ( slug ) {
	var row = ( reg.match( new RegExp( "'" + slug + "'\\s*=>\\s*array\\(([\\s\\S]*?)\\n\\t\\t\\)," ) ) || [ '', '' ] )[ 1 ];
	check( 'kayıt: ' + slug + " -> family '" + FAMILY[ slug ] + "'", new RegExp( "'family'\\s*=>\\s*'" + FAMILY[ slug ] + "'" ).test( row ) );
	check( 'kayıt: ' + slug + ' eyebrow + parents taşır', /'eyebrow'\s*=>/.test( row ) && /'parents'\s*=>/.test( row ) );
} );
check( 'kayıt: iletisim + online-basvuru kahramanı aynı merkezde (tek harita)', /'iletisim'\s*=>/.test( reg ) && /'online-basvuru'\s*=>/.test( reg ) );
check( 'mavibelge_page_hero_for_slug() merkezi kayda delege eder (ikinci harita yok)', /function mavibelge_page_hero_for_slug[\s\S]*?mavibelge_page_presentation\(\s*\$slug\s*\)/.test( layouts ) );
check( 'arşiv kaydı: mavibelge_archive_presentation() haberler/dokumanlar/haber-turu', /function mavibelge_archive_presentation\(/.test( layouts ) && /'haberler'\s*=>/.test( layouts ) && /'dokumanlar'\s*=>/.test( layouts ) && /'haber-turu'\s*=>/.test( layouts ) );

// 2. 14 kopya şablon YOK; tek ortak gövde
var copies = Object.keys( FAMILY ).concat( [ 'haberler', 'duyurular', 'dokumanlar' ] ).filter( function ( s ) {
	return exists( 'page-' + s + '.php' );
} );
check( 'sayfa başına kopya page-<slug>.php yok', 0 === copies.length );
check( 'bootstrap presentation-helpers.php yükler', /require_once __DIR__ \. '\/presentation-helpers\.php';/.test( bootstrap ) );
check( 'page.php: sunum kaydı varsa ortak gövde content-presentation', /mavibelge_page_presentation\(\s*\$slug\s*\)/.test( page ) && /template-parts\/page\/content-presentation/.test( page ) );
check( 'page.php: kahraman yardımcıdan (kırıntı + lead merkezi)', /mavibelge_page_hero_args\(/.test( page ) );
check( 'content-presentation: aileler list/card/cards/steps/prose', [ 'list', 'card', 'cards', 'steps', 'prose' ].every( function ( f ) {
	return new RegExp( "'" + f + "'\\s*===\\s*\\$family" ).test( body );
} ) );
check( 'content-presentation: editör içeriği the_content filtresinden gelir (mavibelge_rendered_content); bölümleme yardımcıdan', /mavibelge_rendered_content\(\)/.test( body ) && /apply_filters\(\s*'the_content'/.test( helpers ) && /mavibelge_split_content_sections\(/.test( body ) );
check( 'iç bağlantılar kalıcı bağlantı yapısına çevrilir (yardımcı + sayfa gövdelerinin tamamı)', /mavibelge_localize_content_links\(/.test( helpers ) && [ 'template-parts/page/content-default.php', 'template-parts/page/content-form-disabled.php', 'template-parts/page/content-form-live.php', 'template-parts/page/content-hub.php', 'template-parts/page/content-contact.php', 'template-parts/page/content-application.php' ].every( function ( f ) {
	var c = code( read( f ) );
	return /mavibelge_rendered_content\(\)/.test( c ) && ! /(^|[^_])the_content\(\)/.test( c );
} ) );
check( 'bölümleme: metinsiz intro/rest (görsel/iframe) atılmaz — ham HTML ile kontrol', /trim\(\s*\$parts\['intro'\]\s*\)/.test( body ) && /trim\(\s*\$parts\['rest'\]\s*\)/.test( body ) && ! /wp_strip_all_tags\(\s*\$parts/.test( body ) );
check( 'bölümleme: sarmalayıcı içindeki başlıkta güvenli yol (bölümleme yok); yalnız üst seviye başlık rest başlatır', /return \$whole;/.test( helpers ) && /\$heading_level < \$level/.test( helpers ) );
check( 'content-presentation: CTA yolu mavibelge_url() ile', /mavibelge_url\(\s*\$cta\['path'\]\s*\)/.test( body ) );
check( 'yardımcılar: split + localize + hero args tanımlı', /function mavibelge_split_content_sections\(/.test( helpers ) && /function mavibelge_localize_content_links\(/.test( helpers ) && /function mavibelge_page_hero_args\(/.test( helpers ) );
check( 'yardımcılar: lead önce WordPress özeti (has_excerpt), sonra kayıt', /has_excerpt\(/.test( helpers ) && /get_the_excerpt\(/.test( helpers ) );
check( 'yardımcılar: bölüm başlığı atlamasız seviyede yeniden yazılır (metin korunur)', /'<h'\s*\.\s*\$level/.test( helpers ) );

// 3. Arşiv + taksonomi ortak page-hero
[ [ 'archive-mb_haber.php', news, 'haberler' ], [ 'archive-mb_dokuman.php', docs, 'dokumanlar' ], [ 'taxonomy.php', tax, 'haber-turu' ] ].forEach( function ( t ) {
	check( t[ 0 ] + ': ortak page-hero + arşiv kaydı', /template-parts\/page\/page-hero/.test( t[ 1 ] ) && new RegExp( "mavibelge_archive_hero_args\\(\\s*'" + t[ 2 ] + "'" ).test( t[ 1 ] ) );
	check( t[ 0 ] + ': başlık artık section-heading H1 değil (tek H1 kahramanda)', ! /section-heading[\s\S]{0,200}'level'\s*=>\s*1/.test( t[ 1 ] ) );
} );
check( 'haber arşivi: servis sorgusu + tür süzgeci + sayfalama korunur', /mavibelge_get_news\(\s*mavibelge_content_request_args\(\)\s*\)/.test( news ) && /'mb_type'/.test( news ) && /components\/pagination/.test( news ) );
check( 'doküman arşivi: servis sorgusu + kategori süzgeci + sayfalama korunur; satır içi grid stili yok', /mavibelge_get_documents\(\s*mavibelge_content_request_args\(\)\s*\)/.test( docs ) && /'mb_cat'/.test( docs ) && /components\/pagination/.test( docs ) && ! /style="display:grid/.test( docs ) && /class="doc-list"/.test( docs ) );
check( 'haber kartı: öne çıkan görsel yalnız DTO thumbnail_id / has_post_thumbnail varsa; ortak kart bileşeni media_html (wp_kses_post)', /'media_html'\s*=>\s*! empty\( \$item\['thumbnail_id'\] \)/.test( code( read( 'template-parts/content/news-card.php' ) ) ) && /has_post_thumbnail\(/.test( code( read( 'template-parts/content/content-card.php' ) ) ) && /wp_kses_post\(\s*\$media_html\s*\)/.test( code( read( 'template-parts/components/card.php' ) ) ) );
check( 'taksonomi: WordPress döngüsü + sayfalama korunur', /have_posts\(\)/.test( tax ) && /the_posts_pagination\(\)/.test( tax ) );

// 4. Form sayfası (itiraz-sikayet) kapı ve alanlar korunur
check( 'form kapalı gövde: <form>/input yok, split düzen', ! /<form\b|<input\b/.test( formOff ) && /mb-split/.test( formOff ) );
check( 'form açık gövde: merkezi forms/form + form-status, split düzen', /template-parts\/forms\/form'/.test( formLive ) && /forms\/form-status/.test( formLive ) && /mb-split/.test( formLive ) );
check( 'page.php: form kapısı DTO open alanı (gevşek ikinci koşul yok)', /\$form\['open'\]\s*\|\|\s*'success'\s*===\s*\$form\['status'\]/.test( page ) );

// 5. Veri gömme yok (banka/haber/doküman)
var themeFiles = walk( root, [] ).filter( function ( f ) {
	return /\.(php|js|css|html)$/.test( f ) && ! /[\\\/]assets[\\\/]dist[\\\/]/.test( f );
} );
var bankHits = themeFiles.filter( function ( f ) {
	var s = fs.readFileSync( f, 'utf8' );
	return /\bTR\d{2}\s?\d{4}/.test( s ) || /Halk Bankas/i.test( s ) || /Şube Kodu/.test( s ) || /IBAN:/.test( s );
} );
check( 'tema: banka/IBAN verisi gömülü değil', 0 === bankHits.length );
var newsHits = themeFiles.filter( function ( f ) {
	return /TEST QA Haber|window\.MB_NEWS|news\.js/.test( fs.readFileSync( f, 'utf8' ) );
} );
check( 'tema: haber/doküman kaydı gömülü değil', 0 === newsHits.length );
var idx = themeFiles.filter( function ( f ) {
	return /\.php$/.test( f ) && /['"][^'"]*\/index\.php\/[^'"]*['"]/.test( code( fs.readFileSync( f, 'utf8' ) ) );
} );
check( 'tema PHP: /index.php/ koda gömülü değil', 0 === idx.length );
check( 'tema: statik .html bağlantısı üretilmez', 0 === themeFiles.filter( function ( f ) {
	return /\.php$/.test( f ) && /href="[a-z0-9-]+\.html/.test( fs.readFileSync( f, 'utf8' ) );
} ).length );

// 6. CSS aileleri + kırılımlar + dist eşliği
[ '.mb-body', '.mb-body--narrow', '.mb-body--prose', '.mb-list', '.mb-cards', '.mb-steps', '.mb-card', '.mb-cta', '.mb-split', '.doc-list' ].forEach( function ( sel ) {
	var re = new RegExp( sel.replace( /\./g, '\\.' ) + '[\\s,{:.]' );
	check( 'pages.css: ' + sel, re.test( css ) );
	check( 'dist: ' + sel, re.test( dist ) );
} );
check( 'pages.css: dar gövde 760px, düz metin 820px', /\.mb-body--narrow\s*\{[^}]*max-width:\s*760px/.test( css ) && /\.mb-body--prose\s*\{[^}]*max-width:\s*820px/.test( css ) );
check( 'responsive: ≤1024 split tek sütun; ≤768 kart ızgarası tek sütun', /@media \(max-width: 1024px\)[\s\S]*?\.mb-split\s*\{[^}]*grid-template-columns:\s*1fr/.test( resp ) && /@media \(max-width: 768px\)[\s\S]*?\.mb-cards[^{]*\{[^}]*grid-template-columns:\s*1fr/.test( resp ) );
check( 'pages.css: prefers-reduced-motion altında geçiş yok', /@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.mb-/.test( css ) );

// 7. Statik referans değişmedi
var st = '';
try {
	st = cp.execSync( 'git status --porcelain -- tanitim-site', { cwd: repo, encoding: 'utf8' } );
} catch ( e ) {
	st = 'git-yok';
}
check( 'tanitim-site/** değişmedi (yalnız izlenmeyen kullanıcı zip)', st.split( '\n' ).filter( function ( l ) {
	return l.trim() && ! /yeni-mavibelge-v1\.zip/.test( l );
} ).length === 0 );

if ( failures.length ) {
	failures.forEach( function ( f ) {
		console.log( 'FAIL  ' + f );
	} );
	console.log( '\n' + ( checks - failures.length ) + '/' + checks + ' sayfa sunum sözleşme testi geçti.' );
	process.exit( 1 );
}
console.log( 'Tüm ' + checks + ' sayfa sunum sözleşme testi geçti (statik kaynak taraması; davranış gerçek WordPress/Chrome\'da ayrıca).' );
