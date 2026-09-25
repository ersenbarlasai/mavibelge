/**
 * Loads the REAL assets/src/js/navigation.js (not a copy) under a
 * minimal Node `document`/`window` stub, then exercises the real
 * `resolveTrapFocusTarget` and `normalizePath` functions it exposes via
 * `window.__mavibelgeNavTestHooks__` (see that file's bottom, and its
 * comment on the hook).
 *
 * The stub is intentionally tiny: `document.readyState` is left as
 * 'loading' so `onReady()` only registers a no-op DOMContentLoaded
 * listener and never calls initDropdowns()/initMobileMenu() — those
 * need a real querySelectorAll-capable DOM this project has no library
 * to provide offline (no npm dependency was added per the task's
 * constraints), so their full DOM integration is NOT covered here and
 * remains a real-browser-only gate (see tests/README.md).
 *
 * What IS covered here is real: `resolveTrapFocusTarget` is the actual
 * function called from inside the real Tab-key handler, unmodified.
 *
 * Run: node tests/js/mobile-focus-trap.test.js
 */
'use strict';

var path = require( 'path' );

global.document = {
	readyState: 'loading',
	addEventListener: function () {},
};
global.window = {};

require( path.join( __dirname, '..', '..', 'assets', 'src', 'js', 'navigation.js' ) );

var hooks = global.window.__mavibelgeNavTestHooks__;

if ( ! hooks || 'function' !== typeof hooks.resolveTrapFocusTarget ) {
	console.error( 'FAIL: window.__mavibelgeNavTestHooks__.resolveTrapFocusTarget not found after requiring navigation.js — test hook missing or removed.' );
	process.exit( 1 );
}

var resolve = hooks.resolveTrapFocusTarget;

var toggle = { name: 'toggle' };
var first  = { name: 'first-nav-item' };
var last   = { name: 'last-nav-item' };
var other  = { name: 'something-else-entirely' };

var failures = 0;

function check( label, actual, expected ) {
	if ( actual !== expected ) {
		failures++;
		console.error(
			'FAIL ' + label + ': got ' + ( actual && actual.name ) + ', expected ' + ( expected && expected.name )
		);
	}
}

// The four required boundary crossings (Faz 3 İkinci Kabul Düzeltmesi §2):
check( 'Tab on toggle -> first nav item', resolve( toggle, false, toggle, first, last ), first );
check( 'Shift+Tab on first item -> toggle', resolve( first, true, toggle, first, last ), toggle );
check( 'Tab on last item -> toggle', resolve( last, false, toggle, first, last ), toggle );
check( 'Shift+Tab on toggle -> last item', resolve( toggle, true, toggle, first, last ), last );

// Non-boundary focus must not be intercepted (normal Tab flow applies).
check( 'Tab on an unrelated element -> no interception (null)', resolve( other, false, toggle, first, last ), null );
check( 'Shift+Tab on an unrelated element -> no interception (null)', resolve( other, true, toggle, first, last ), null );

// normalizePath (same function nav-active-page.test.js exercises via a
// hand-copy; here it is the real, required one).
var normalizePath = hooks.normalizePath;
if ( 'function' === typeof normalizePath ) {
	if ( normalizePath( '/meslekler/' ) !== '/meslekler' ) {
		failures++;
		console.error( 'FAIL: real normalizePath("/meslekler/") !== "/meslekler"' );
	}
	if ( normalizePath( '/' ) !== '/' ) {
		failures++;
		console.error( 'FAIL: real normalizePath("/") !== "/" (root must not be stripped to empty string)' );
	}
} else {
	failures++;
	console.error( 'FAIL: window.__mavibelgeNavTestHooks__.normalizePath not found.' );
}

if ( failures > 0 ) {
	console.error( failures + ' check(s) FAILED.' );
	process.exit( 1 );
}

console.log( 'All focus-trap boundary + normalizePath checks passed against the REAL navigation.js source (DOM-free; full browser integration still open).' );
