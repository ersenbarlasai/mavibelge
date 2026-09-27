/**
 * Static SOURCE-TEXT contract: ana sayfa görev kartı ve sektör kartı ikonları yer-tutucu daire OLMAMALI;
 * kayıttaki (inc/icons.php) her ikon, dondurulmuş statik referanstaki (tanitim-site/index.html) SVG içeriğiyle
 * birebir aynı olmalı. PHP çalıştırmaz; gerçek kaynak dosyaları metin olarak okur.
 *
 * Run: node tests/static/icons-contract.test.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );

var themeRoot = path.join( __dirname, '..', '..' );
var repoRoot = path.join( themeRoot, '..', '..', '..', '..' );
var failures = [];
var checks = 0;

function check( label, condition ) {
	checks++;
	if ( ! condition ) {
		failures.push( label );
	}
}
function read( p ) {
	return fs.readFileSync( p, 'utf8' );
}

var iconsPath = path.join( themeRoot, 'inc', 'icons.php' );
check( 'inc/icons.php mevcut', fs.existsSync( iconsPath ) );
var icons = fs.existsSync( iconsPath ) ? read( iconsPath ) : '';
check( 'inc/icons.php bootstrap tarafından yükleniyor', /require_once __DIR__ \. '\/icons\.php';/.test( read( path.join( themeRoot, 'inc', 'bootstrap.php' ) ) ) );
check( 'mavibelge_icon_svg() tanımlı', /function mavibelge_icon_svg\(/.test( icons ) );

// Statik referans: index.html
var html = read( path.join( repoRoot, 'tanitim-site', 'index.html' ) );
var w = {};
new Function( 'window', read( path.join( repoRoot, 'tanitim-site', 'assets', 'data', 'sectors.js' ) ) )( w );
var sectors = w.MB_SECTORS;
var cards = [];
html.replace( /<div class="sector-icon"><svg[^>]*>([\s\S]*?)<\/svg><\/div>\s*<div>\s*<strong>([^<]*)<\/strong>/g, function ( m, inner, name ) {
	cards.push( { inner: inner.trim(), name: name.trim() } );
	return m;
} );
check( 'referansta 14 sektör kartı', cards.length === 14 && sectors.length === 14 );
cards.forEach( function ( c ) {
	var s = sectors.filter( function ( x ) { return x.name === c.name; } )[ 0 ];
	check( 'sektör ' + c.name + ' referansta bulundu', !! s );
	if ( s ) {
		check( 'ikon kaydında sektör ikonu ' + s.icon + ' referansla birebir', icons.indexOf( c.inner ) !== -1 && icons.indexOf( "'" + s.icon + "'" ) !== -1 );
	}
} );
var tasks = [];
html.replace( /<div class="task-icon"><svg[^>]*>([\s\S]*?)<\/svg>/g, function ( m, inner ) {
	tasks.push( inner.trim() );
	return m;
} );
check( 'referansta 5 görev ikonu', tasks.length === 5 );
tasks.forEach( function ( inner, i ) {
	check( 'görev ikonu #' + i + ' referansla birebir', icons.indexOf( inner ) !== -1 );
} );

var tasksTpl = read( path.join( themeRoot, 'template-parts', 'home', 'tasks.php' ) );
check( 'tasks.php yer-tutucu daire SVG içermiyor', ! /<circle cx="12" cy="12" r="9"\/><\/svg>/.test( tasksTpl ) );
check( 'tasks.php mavibelge_icon_svg() çağırıyor', /mavibelge_icon_svg\(/.test( tasksTpl ) );
var sectorTpl = read( path.join( themeRoot, 'template-parts', 'home', 'sector-grid.php' ) );
check( 'sector-grid.php yer-tutucu daire SVG içermiyor', ! /<circle cx="12" cy="12" r="8"\/><\/svg>/.test( sectorTpl ) );
check( 'sector-grid.php _mb_icon_key okuyor ve mavibelge_icon_svg() çağırıyor', /_mb_icon_key/.test( sectorTpl ) && /mavibelge_icon_svg\(/.test( sectorTpl ) );

check( 'sector-grid.php term meta değerini (string) ile cast ETMİYOR (bozuk dizi meta Notice üretmemeli)', ! /\(string\)\s*get_term_meta/.test( sectorTpl ) && /is_string\(\s*\$icon_key\s*\)/.test( sectorTpl ) );

if ( failures.length ) {
	failures.forEach( function ( f ) { console.error( 'FAIL: ' + f ); } );
	console.error( failures.length + '/' + checks + ' başarısız' );
	process.exit( 1 );
}
console.log( 'Tüm ' + checks + ' ikon sözleşme testi geçti (statik kaynak taraması; tarayıcı render değil).' );
