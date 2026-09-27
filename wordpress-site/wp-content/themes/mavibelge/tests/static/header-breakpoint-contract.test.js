/**
 * Static SOURCE-TEXT contract (Faz 12e kapanış): hamburger başlık kırılımı CSS ve JS'te AYNI değerdir (1279px) ve
 * masaüstü menü etiketleri daralmaz. Değer gerçek Chrome ölçümüyle belirlendi; geometri/etkileşim davranışı
 * tools/runtime-test/header-geometry-test.js ile gerçek tarayıcıda sınanır. PHP/tarayıcı çalıştırmaz.
 *
 * Run: node tests/static/header-breakpoint-contract.test.js
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

var resp = read( 'assets/src/css/responsive.css' );
var header = read( 'assets/src/css/header.css' );
var nav = read( 'assets/src/js/navigation.js' );
var dist = read( 'assets/dist/style.css' );
var distJs = read( 'assets/dist/main.js' );

var block = ( resp.match( /@media \(max-width: 1279px\) \{([\s\S]*?)\n\}/ ) || [ '', '' ] )[ 1 ];
check( 'responsive.css: hamburger bloğu @media (max-width: 1279px)', block !== '' );
check( 'hamburger bloğu: menü düğmesi görünür, masaüstü menü kapalı, is-open ile açılır, CTA menü içinde', /\.menu-toggle \{ display: inline-flex;/.test( block ) && /\.main-nav \{[^}]*display: none;/.test( block ) && /\.main-nav\.is-open \{ display: block; \}/.test( block ) && /\.header-cta \{ display: none; \}/.test( block ) && /\.mobile-header-cta \{ display: block;/.test( block ) );
check( 'hamburger bloğu: alt menü yalnız düğmeyle (hover/focus-within kapalı, is-open açık — sıra önemli)', /\.main-nav li:hover > \.submenu,\s*\.main-nav li:focus-within > \.submenu \{ display: none; \}\s*\.main-nav li\.is-open > \.submenu \{ display: block; \}/.test( block ) );
check( 'hamburger bloğu: alt menü düğmesi 44px dokunma alanı', /button\.nav-toggle \{[^}]*width: 44px;/.test( block ) );
check( '768 bloğunda ikinci bir hamburger kuralı YOK (tek kırılım)', ! /@media \(max-width: 768px\) \{[\s\S]*?\.menu-toggle \{ display: inline-flex/.test( resp.replace( block, '' ) ) );
check( '1024 bloğunda masaüstü menü etiketi küçültme (font-size/padding sıkıştırma) YOK', ! /@media \(max-width: 1024px\) \{[^@]*\.main-nav > ul > li > a,[^@]*font-size: 0\.8125rem/.test( resp ) );
check( 'hamburger bloğu: tam satır kuralı üst bağlantıyı (nav-parent-link) KAPSAMAZ — açma düğmesi aynı satırda kalır', block.indexOf( 'li:not(.menu-item-has-children) > a:not(.nav-parent-link) { width: 100%; }' ) !== -1 && resp.indexOf( 'li:not(.menu-item-has-children) > a { width: 100%; }' ) === -1 );
check( 'navigation.js MOBILE_MQ CSS ile aynı: (max-width: 1279px)', /var MOBILE_MQ = '\(max-width: 1279px\)';/.test( nav ) );
check( 'header.css: masaüstü etiketleri daralmaz (li flex-shrink: 0) ve menü aralığı 16px', /\.main-nav > ul > li \{[^}]*flex-shrink: 0;/.test( header ) && /\.main-nav > ul \{[^}]*gap: var\(--space-4\);/.test( header ) );
check( 'dist/style.css ve dist/main.js kaynakla aynı kırılımı içerir', dist.indexOf( '@media (max-width: 1279px)' ) !== -1 && distJs.indexOf( "'(max-width: 1279px)'" ) !== -1 );

if ( failures.length ) {
	failures.forEach( function ( f ) {
		console.log( 'FAIL  ' + f );
	} );
	console.log( failures.length + '/' + checks + ' başarısız' );
	process.exit( 1 );
}
console.log( 'Tüm ' + checks + ' başlık kırılımı sözleşme testi geçti (statik kaynak taraması; geometri gerçek tarayıcıda ayrıca).' );
