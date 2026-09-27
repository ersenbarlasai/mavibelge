/**
 * Static SOURCE-TEXT contract: tema dahili bağlantıları merkezi çözümleyiciden (inc/urls.php) geçer; `home_url( '/slug/' )` ile
 * dahili sayfa bağlantısı KURULMAZ ve `/index.php/` koda GÖMÜLMEZ. Davranış (iki permalink yapısı) gerçek WordPress'te
 * tools/runtime-test/scripts/theme-urls-test.php ile sınanır. PHP çalıştırmaz.
 *
 * Run: node tests/static/internal-urls-contract.test.js
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
function walk( dir, out ) {
	fs.readdirSync( dir, { withFileTypes: true } ).forEach( function ( e ) {
		var p = path.join( dir, e.name );
		if ( e.isDirectory() ) {
			if ( [ 'tests', 'assets', 'languages', 'node_modules' ].indexOf( e.name ) === -1 ) {
				walk( p, out );
			}
		} else if ( /\.php$/.test( e.name ) ) {
			out.push( p );
		}
	} );
	return out;
}
function stripComments( s ) {
	return s.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /^\s*\/\/.*$/gm, '' );
}

var files = walk( root, [] );
var offenders = [];
var indexPhp = [];
files.forEach( function ( f ) {
	var src = stripComments( fs.readFileSync( f, 'utf8' ) );
	if ( /home_url\(\s*['"]\/[^'"\s][^'"]*['"]\s*\)/.test( src ) || /home_url\(\s*['"]\/['"]\s*\.\s*[^)]/.test( src ) ) {
		offenders.push( path.relative( root, f ) );
	}
	if ( /\/index\.php/.test( src ) ) {
		indexPhp.push( path.relative( root, f ) );
	}
} );
check( 'tema PHP kodunda home_url( \'/slug/\' ) ile dahili bağlantı kurulmuyor: ' + offenders.join( ',' ), offenders.length === 0 );
check( '/index.php/ tema koduna gömülü değil: ' + indexPhp.join( ',' ), indexPhp.length === 0 );

var urls = fs.readFileSync( path.join( root, 'inc', 'urls.php' ), 'utf8' );
check( 'inc/urls.php: mavibelge_url() + get_page_by_path + get_permalink + get_post_type_archive_link + get_page_permastruct', /function mavibelge_url\(/.test( urls ) && /get_page_by_path\(/.test( urls ) && /get_permalink\(/.test( urls ) && /get_post_type_archive_link\(/.test( urls ) && /get_page_permastruct\(/.test( urls ) );
check( 'inc/urls.php: üç CPT arşiv eşlemesi', /'meslekler'\s*=>\s*'mb_yeterlilik'/.test( urls ) && /'haberler'\s*=>\s*'mb_haber'/.test( urls ) && /'dokumanlar'\s*=>\s*'mb_dokuman'/.test( urls ) );
check( 'inc/urls.php: duyurular mb_haber_turu/duyuru term arşivi çözümü korunur', /get_term_by\( 'slug', 'duyuru', 'mb_haber_turu' \)/.test( urls ) && /get_term_link\(/.test( urls ) );
check( 'inc/urls.php: PHP 7.4+ sözdizimi yok (?->, ??=, fn(), str_contains)', ! /\?->|\?\?=|\bfn\s*\(|str_contains|str_starts_with/.test( urls ) );
check( 'inc/bootstrap.php urls.php\'yi menu-fallback.php\'den ÖNCE yükler', /urls\.php[\s\S]*menu-fallback\.php/.test( fs.readFileSync( path.join( root, 'inc', 'bootstrap.php' ), 'utf8' ) ) );

var fb = fs.readFileSync( path.join( root, 'inc', 'menu-fallback.php' ), 'utf8' );
check( 'menu-fallback: mavibelge_fallback_menu_url() mavibelge_url() kullanır', /function mavibelge_fallback_menu_url[\s\S]*mavibelge_url\(/.test( fb ) );
var pl = fs.readFileSync( path.join( root, 'inc', 'page-layouts.php' ), 'utf8' );
check( 'page-layouts: hub bağlantısı mavibelge_url() kullanır', /function mavibelge_resolve_hub_link_url[\s\S]*mavibelge_url\(/.test( pl ) );
var hdr = fs.readFileSync( path.join( root, 'header.php' ), 'utf8' );
check( 'header: marka bağlantısı home_url( \'/\' ) (gerçek ana sayfa) kalır; CTA mavibelge_url( \'online-basvuru\' )', /class="brand-logo" href="<\?php echo esc_url\( home_url\( '\/' \) \)/.test( hdr ) && ( hdr.match( /mavibelge_url\( 'online-basvuru' \)/g ) || [] ).length === 2 );
var ftr = fs.readFileSync( path.join( root, 'footer.php' ), 'utf8' );
check( 'footer: KVKK/gizlilik + #cerezler fragment merkezi çözümleyiciden', /mavibelge_url\( 'kvkk' \)/.test( ftr ) && /mavibelge_url\( 'gizlilik-politikasi', 'cerezler' \)/.test( ftr ) );

if ( failures.length ) {
	failures.forEach( function ( f ) {
		console.log( 'FAIL  ' + f );
	} );
	process.exit( 1 );
}
console.log( 'Tüm ' + checks + ' dahili bağlantı sözleşme testi geçti (statik kaynak taraması; tarayıcı/staging değil).' );
