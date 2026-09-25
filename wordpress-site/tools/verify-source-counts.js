/**
 * Faz 5 §12.5 — read-only verification script. Recomputes the source
 * data counts the Faz 5 brief names (14/83/103/145/87/16/58/84/19)
 * directly from tanitim-site/assets/data/*.js. NEVER writes anything —
 * no WordPress connection, no database, no import. If a count doesn't
 * match, this script exits non-zero and prints the mismatch instead of
 * proceeding — Faz 6's real import must not run on unverified counts.
 *
 * Run: node tools/verify-source-counts.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );

var dataDir = path.join( __dirname, '..', '..', 'tanitim-site', 'assets', 'data' );

function read( file ) {
	return fs.readFileSync( path.join( dataDir, file ), 'utf8' );
}

function countMatches( src, pattern ) {
	var matches = src.match( pattern );
	return matches ? matches.length : 0;
}

var failures = [];
function check( label, actual, expected ) {
	if ( actual !== expected ) {
		failures.push( label + ': expected ' + expected + ', got ' + actual );
	} else {
		console.log( 'OK    ' + label + ' = ' + actual );
	}
}

var sectors = read( 'sectors.js' );
check( 'sectors.js slug count', countMatches( sectors, /slug:/g ), 14 );

var qualifications = read( 'qualifications.js' );
check( 'qualifications.js code count', countMatches( qualifications, /code:/g ), 83 );

var fees = read( 'fees.js' );
check( 'fees.js pricingType (ana kayıt) count', countMatches( fees, /pricingType:/g ), 103 );
check( 'fees.js label (fiyat seçeneği) count', countMatches( fees, /label:/g ), 145 );
check( 'fees.js empty qualificationCode count', countMatches( fees, /qualificationCode: ""/g ), 19 );
check( 'fees.js non-empty qualificationCode count', countMatches( fees, /qualificationCode: "[^"]+"/g ), 84 );

var single = countMatches( fees, /pricingType: "single"/g );
var unit = countMatches( fees, /pricingType: "unit"/g );
var pkg = countMatches( fees, /pricingType: "package"/g );
var multiple = countMatches( fees, /pricingType: "multiple"/g );
check( 'fees.js pricingType=single count (Faz 5 brief: "87 tek fiyatlı")', single, 87 );
check( 'fees.js non-single (unit+package+multiple) count (Faz 5 brief: "16 çok fiyatlı")', unit + pkg + multiple, 16 );
check( 'fees.js option count belonging to non-single records (Faz 5 brief: "58 seçenek")', countMatches( fees, /label:/g ) - single, 58 );

if ( failures.length ) {
	console.error( '\n' + failures.length + ' mismatch(es) — DO NOT proceed with any import:' );
	failures.forEach( function ( f ) { console.error( '  - ' + f ); } );
	process.exit( 1 );
}

console.log( '\nAll 9 source counts verified directly from tanitim-site/assets/data/*.js.' );
