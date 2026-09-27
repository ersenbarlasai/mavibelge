/**
 * SOURCE-TEXT contract: açık mobil menü, menü ve düğmesi DIŞINA tıklanınca kapanmalı (gerçek tarayıcı gözleminde
 * eksikti: outside-click yalnız alt menüleri kapatıyordu). PHP/DOM çalıştırmaz; gerçek navigation.js metnini okur.
 *
 * Run: node tests/js/mobile-outside-click.test.js
 */
'use strict';
var fs = require( 'fs' );
var path = require( 'path' );
var src = fs.readFileSync( path.join( __dirname, '..', '..', 'assets', 'src', 'js', 'navigation.js' ), 'utf8' );
var fails = [];
// initMobileMenu içinde 'click' dinleyicisi: nav açıkken, hedef nav/toggle içinde değilse closeMobileMenu çağırır.
var m = /document\.addEventListener\(\s*'click',\s*function\s*\(\s*e\s*\)\s*\{([\s\S]*?)\n\t\t\}\s*\);/g;
var ok = false, hit;
while ( ( hit = m.exec( src ) ) ) {
	var b = hit[ 1 ];
	if ( /nav\.classList\.contains\(\s*'is-open'\s*\)/.test( b ) && /nav\.contains\(\s*e\.target\s*\)/.test( b ) && /toggle\.contains\(\s*e\.target\s*\)/.test( b ) && /closeMobileMenu\(\s*false\s*\)/.test( b ) ) {
		ok = true;
	}
}
if ( ! ok ) {
	fails.push( 'açık mobil menüyü dış tıklamada closeMobileMenu(false) ile kapatan document click dinleyicisi yok' );
}
if ( fails.length ) {
	fails.forEach( function ( f ) { console.error( 'FAIL: ' + f ); } );
	process.exit( 1 );
}
console.log( 'Mobil menü dış-tıklama sözleşmesi geçti (statik kaynak taraması).' );
