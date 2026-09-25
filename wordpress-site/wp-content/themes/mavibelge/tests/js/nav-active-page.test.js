/**
 * Pure-logic test for the path-normalization/comparison algorithm used
 * by markActivePage() in assets/src/js/navigation.js (trailing-slash
 * bug fix, Faz 3 Düzeltme ve Kabul §4).
 *
 * This is a STATIC CONTRACT test only: it re-implements the same
 * normalizePath() algorithm in isolation (no DOM, no browser, no
 * `location` object) and checks it against known input/output pairs.
 * It does NOT load navigation.js, does NOT run in a browser, and does
 * NOT prove the real DOM/WordPress integration works — that requires a
 * real WordPress + browser environment, which is out of scope here and
 * remains an open quality gate (see docs/design-system.md).
 *
 * Run: node tests/js/nav-active-page.test.js
 */
'use strict';

function normalizePath( pathname ) {
	if ( pathname.length > 1 && '/' === pathname.charAt( pathname.length - 1 ) ) {
		return pathname.slice( 0, -1 );
	}
	return pathname;
}

var cases = [
	// [ currentPathname, linkHref, expectedMatch ]
	[ '/meslekler/', '/meslekler/', true ],
	[ '/meslekler', '/meslekler/', true ],
	[ '/meslekler/', '/meslekler', true ],
	[ '/', '/', true ],
	[ '/meslekler/', '/meslekler/detay/', false ],
	[ '/kurumsal/', '/sinav-ve-basvuru/', false ],
];

var failures = 0;

cases.forEach( function ( testCase, index ) {
	var currentPath = normalizePath( testCase[ 0 ] );
	var linkPath = normalizePath( testCase[ 1 ] );
	var actualMatch = currentPath === linkPath;
	var expectedMatch = testCase[ 2 ];

	if ( actualMatch !== expectedMatch ) {
		failures++;
		console.error(
			'FAIL case ' + index + ': normalizePath(' + JSON.stringify( testCase[ 0 ] ) + ') vs ' +
			'normalizePath(' + JSON.stringify( testCase[ 1 ] ) + ') => match=' + actualMatch +
			', expected=' + expectedMatch
		);
	}
} );

if ( failures > 0 ) {
	console.error( failures + ' of ' + cases.length + ' cases FAILED.' );
	process.exit( 1 );
}

console.log( 'All ' + cases.length + ' normalizePath cases passed (static logic only — no DOM/WordPress).' );
