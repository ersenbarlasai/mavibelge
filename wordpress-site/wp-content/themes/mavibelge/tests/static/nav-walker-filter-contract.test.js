/**
 * Static SOURCE-TEXT contract test for
 * inc/class-nav-walker.php's walker_nav_menu_start_el filter boundary
 * (Faz 3 Walker Sözleşmesi Kapanışı).
 *
 * This does NOT execute PHP (no `php` binary in this environment — see
 * tests/README.md) and does NOT prove the walker renders correctly
 * inside real WordPress. It reads the real, shipped PHP source file as
 * text and asserts structural facts about it with regular expressions —
 * e.g. "the string '<li' is only ever appended to $output, never to
 * $item_output" and "end_el() always appends </li> unconditionally".
 * This catches the exact class of regression this task fixed (the <li>
 * boundary leaking into the filtered value) without needing a WordPress
 * runtime, but it is a text-pattern check, not a parser/AST check and
 * not a real Walker_Nav_Menu execution — treat a pass here as "the
 * source still reads the way we intended", not as "WordPress ran this".
 *
 * Run: node tests/static/nav-walker-filter-contract.test.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );

var filePath = path.join( __dirname, '..', '..', 'inc', 'class-nav-walker.php' );
var src = fs.readFileSync( filePath, 'utf8' );

var failures = [];

function check( label, condition ) {
	if ( ! condition ) {
		failures.push( label );
	}
}

// 1. The <li opening tag must be built directly into $output, not into
//    $item_output (which is what gets passed to the filter).
check(
	'"<li" is concatenated into $output (not $item_output)',
	/\$output\s*\.=\s*'<li/.test( src )
);
check(
	'$item_output is never assigned/concatenated a string starting with "<li"',
	! /\$item_output\s*\.?=\s*'<li/.test( src )
);

// 2. walker_nav_menu_start_el must be called with exactly the real core
//    4-argument signature: $item_output, $item, $depth, $args — no 5th
//    argument (no $id / $current_object_id).
var filterCallMatch = src.match( /apply_filters\(\s*'walker_nav_menu_start_el'\s*,\s*([^)]*)\)/ );
check( 'walker_nav_menu_start_el filter call found', !! filterCallMatch );
if ( filterCallMatch ) {
	var args = filterCallMatch[ 1 ].split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
	check(
		'walker_nav_menu_start_el called with exactly 4 args ($item_output, $item, $depth, $args)',
		4 === args.length &&
			args[ 0 ] === '$item_output' &&
			args[ 1 ] === '$item' &&
			args[ 2 ] === '$depth' &&
			args[ 3 ] === '$args'
	);
}

// 3. end_el() must append </li> unconditionally (no depth guard around
//    the closing tag) — extract its function body and check there is no
//    "if" between "function end_el" and the "</li>" append.
var endElMatch = src.match( /function end_el\([^)]*\)\s*\{([\s\S]*?)\n\t\}/ );
check( 'end_el() function body found', !! endElMatch );
if ( endElMatch ) {
	var body = endElMatch[ 1 ];
	check( 'end_el() body appends </li>', /\$output\s*\.=\s*'<\/li>'/.test( body ) );
	check( 'end_el() body has no "if" guarding the </li> append (runs at every depth)', ! /if\s*\(/.test( body ) );
}

// 4. start_el() must not itself append "</li>" for child items (that
//    would double-close or pre-empt end_el()'s unconditional close).
var startElMatch = src.match( /function start_el\([\s\S]*?\n\t\}/ );
check( 'start_el() function body found', !! startElMatch );
if ( startElMatch ) {
	check(
		'start_el() never appends "</li>" itself (only end_el() does)',
		! /'<\/li>'/.test( startElMatch[ 0 ] )
	);
}

// 5. nav_menu_item_args must run and its result must be threaded into
//    the later filters (reassigned to $args, not a new variable).
check(
	'nav_menu_item_args filter is applied and reassigned to $args',
	/\$args\s*=\s*apply_filters\(\s*'nav_menu_item_args'/.test( src )
);

if ( failures.length ) {
	console.error( failures.length + ' check(s) FAILED:' );
	failures.forEach( function ( f ) { console.error( '  - ' + f ); } );
	process.exit( 1 );
}

console.log( 'All ' + 'walker_nav_menu_start_el boundary contract checks passed (static source-text scan of the real class-nav-walker.php; not a PHP/WordPress execution).' );
