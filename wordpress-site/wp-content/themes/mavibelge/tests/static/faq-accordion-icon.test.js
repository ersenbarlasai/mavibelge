/**
 * Static SOURCE-TEXT contract: SSS akordeonu ikonu boyutlu olmalı (boyutsuz SVG tarayıcı varsayılanında yüzlerce piksel çizilir
 * ve soru metnini daraltır), native <details>/<summary> semantiği, 45° dönüş ve reduced-motion korunmalı.
 * Kaynak CSS ile derlenmiş dist/style.css tutarlı olmalı. PHP/tarayıcı çalıştırmaz.
 *
 * Run: node tests/static/faq-accordion-icon.test.js
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

var tpl = read( 'template-parts/content/faq-list.php' );
var pages = read( 'assets/src/css/pages.css' );
var comps = read( 'assets/src/css/components.css' );
var base = read( 'assets/src/css/base.css' );
var dist = read( 'assets/dist/style.css' );

var svg = ( tpl.match( /<svg[^>]*>/ ) || [ '' ] )[ 0 ];
check( 'faq-list.php: SVG icon-24 boyut sınıfını taşır', /class="icon icon-24"/.test( svg ) );
check( 'faq-list.php: SVG dekoratif (aria-hidden) ve viewBox 24x24', /aria-hidden="true"/.test( svg ) && /viewBox="0 0 24 24"/.test( svg ) );
check( 'components.css: .icon-24 = 24x24', /\.icon-24\s*\{\s*width:\s*24px;\s*height:\s*24px;\s*\}/.test( comps ) );

var rule = ( pages.match( /\.accordion-item > summary \.icon \{([^}]*)\}/ ) || [ '', '' ] )[ 1 ];
check( 'pages.css: accordion ikonu 24x24 sınırlı (savunma kuralı) ve flex-shrink:0', /width:\s*24px/.test( rule ) && /height:\s*24px/.test( rule ) && /flex-shrink:\s*0/.test( rule ) && /transition:\s*transform/.test( rule ) );
check( 'pages.css: open durumunda 45 derece dönüş korunur', /\.accordion-item\[open\] > summary \.icon \{\s*transform:\s*rotate\(45deg\);\s*\}/.test( pages ) );
check( 'pages.css: summary flex, ikon sağda (space-between), min-height 44px, marker gizli', /justify-content:\s*space-between/.test( pages ) && /min-height:\s*44px/.test( pages ) && /summary::-webkit-details-marker \{ display: none; \}/.test( pages ) );

check( 'faq-list.php: native <details class="accordion-item"> + <summary>; JS/tabindex/role gerektirmez', /<details class="accordion-item">/.test( tpl ) && /<summary>/.test( tpl ) && /<\/summary>/.test( tpl ) && ! /tabindex|role=|onclick|<script/i.test( tpl ) );
check( 'reduced-motion kuralı base.css\'te durur (geçişleri sıfırlar)', /prefers-reduced-motion:\s*reduce/.test( base ) && /transition-duration:\s*0\.01ms/.test( base ) );

check( 'dist/style.css: derlenmiş akordeon ikonu kuralı kaynakla aynı (24x24, flex-shrink:0)', dist.indexOf( '.accordion-item > summary .icon { width: 24px; height: 24px; transition: transform var(--transition-base); flex-shrink: 0; }' ) !== -1 );
check( 'dist/style.css: 45 derece dönüş kuralı var', dist.indexOf( '.accordion-item[open] > summary .icon { transform: rotate(45deg); }' ) !== -1 );

if ( failures.length ) {
	failures.forEach( function ( f ) {
		console.log( 'FAIL  ' + f );
	} );
	process.exit( 1 );
}
console.log( 'Tüm ' + checks + ' SSS akordeon ikon testi geçti (statik kaynak taraması; tarayıcı render değil).' );
