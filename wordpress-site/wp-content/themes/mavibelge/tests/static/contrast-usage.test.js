/**
 * Static SOURCE-TEXT contract: gerçek tarayıcıda (aXe) staging ana sayfasında yakalanan üç kontrast ihlali için
 * CSS kuralının GERÇEK ön plan rengi, bilinen zemin token'ına karşı >= 4.5:1 olmalı.
 * Token çifti hesabı (tools/qa/contrast-check.js) bu kullanım hatalarını yakalamamıştı.
 *
 * Run: node tests/static/contrast-usage.test.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );
var css = path.join( __dirname, '..', '..', 'assets', 'src', 'css' );
var tokens = {};
fs.readFileSync( path.join( css, 'tokens.css' ), 'utf8' ).replace( /--color-([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})/g, function ( m, n, v ) {
	tokens[ n ] = v;
	return m;
} );
function lum( hex ) {
	var c = [ 1, 3, 5 ].map( function ( i ) { return parseInt( hex.slice( i, i + 2 ), 16 ) / 255; } ).map( function ( v ) { return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 ); } );
	return 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ];
}
function ratio( a, b ) {
	var l = [ lum( a ), lum( b ) ].sort( function ( x, y ) { return y - x; } );
	return ( l[ 0 ] + 0.05 ) / ( l[ 1 ] + 0.05 );
}
function ruleColor( file, selector ) {
	var src = fs.readFileSync( path.join( css, file ), 'utf8' );
	var esc = selector.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	var m = new RegExp( '(?:^|\\n|\\})\\s*' + esc + '\\s*\\{([^}]*)\\}' ).exec( src );
	if ( ! m ) { return null; }
	var c = /(?:^|;|\s)color:\s*var\(--color-([a-z0-9-]+)\)/.exec( m[ 1 ] );
	return c ? tokens[ c[ 1 ] ] : null;
}
var failures = [];
var n = 0;
[
	[ 'components.css', '.empty-state p', 'gray-50', 'boş durum metni / açık zemin' ],
	[ 'base.css', '.bg-navy .section-title p', 'navy-900', 'koyu bölüm açıklaması' ],
	[ 'footer.css', '.footer-bottom', 'navy-950', 'altbilgi telif satırı' ]
].forEach( function ( row ) {
	n++;
	var fg = ruleColor( row[ 0 ], row[ 2 ] === undefined ? row[ 1 ] : row[ 1 ] );
	var r = fg ? ratio( fg, tokens[ row[ 2 ] ] ) : 0;
	if ( ! fg || r < 4.5 ) {
		failures.push( row[ 3 ] + ' (' + row[ 1 ] + '): renk ' + ( fg || 'tanımsız' ) + ' / ' + row[ 2 ] + ' = ' + r.toFixed( 2 ) + ':1 (< 4.5)' );
	}
} );
if ( failures.length ) {
	failures.forEach( function ( f ) { console.error( 'FAIL: ' + f ); } );
	process.exit( 1 );
}
console.log( 'Tüm ' + n + ' kontrast kullanım testi geçti (statik kaynak taraması; tarayıcı render değil).' );
