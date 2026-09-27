/**
 * Static SOURCE-TEXT contract test for the "tek anlamlı H1 per sayfa"
 * rule fixed in Faz 4 Nihai Kabul Düzeltmesi.
 *
 * This does NOT execute PHP or WordPress (no `php` binary in this
 * environment — see tests/README.md) and does NOT prove a real render
 * produces exactly one <h1> in the DOM. It reads the real, shipped PHP
 * source files as text and asserts structural facts with regular
 * expressions. A pass here means "the source still reads the way the
 * single-H1 contract intends", not "WordPress rendered this page".
 *
 * Run: node tests/static/heading-contract.test.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );

var themeRoot = path.join( __dirname, '..', '..' );

function read( relPath ) {
	return fs.readFileSync( path.join( themeRoot, relPath ), 'utf8' );
}

var failures = [];

function check( label, condition ) {
	if ( ! condition ) {
		failures.push( label );
	}
}

// 1. section-heading.php accepts level 1, and still defaults to 2.
var sectionHeadingSrc = read( 'template-parts/components/section-heading.php' );
check(
	'section-heading.php default level is 2',
	/\$level\s*=\s*isset\(\s*\$args\[\s*'level'\s*\]\s*\)\s*\?\s*\(int\)\s*\$args\[\s*'level'\s*\]\s*:\s*2\s*;/.test( sectionHeadingSrc )
);
check(
	'section-heading.php clamps the lower bound to 1 (not 2 — level 1 must be allowed)',
	/if\s*\(\s*\$level\s*<\s*1\s*\)\s*\{\s*\$level\s*=\s*1\s*;\s*\}/.test( sectionHeadingSrc )
);
check(
	'section-heading.php does not clamp level 1 back up to 2',
	! /if\s*\(\s*\$level\s*<\s*2\s*\)/.test( sectionHeadingSrc )
);
check(
	'section-heading.php still clamps the upper bound to 4',
	/if\s*\(\s*\$level\s*>\s*4\s*\)\s*\{\s*\$level\s*=\s*4\s*;\s*\}/.test( sectionHeadingSrc )
);

// 2. The seven archive/taxonomy/search templates must call the page-level
//    section-heading with 'level' => 1 (previously they used the
//    component's default of 2, producing zero <h1> on every render path).
var pageHeadingTemplates = [
	'archive.php',
	'archive-mb_haber.php',
	'archive-mb_dokuman.php',
	'taxonomy.php',
	'taxonomy-mb_sektor.php',
	'search.php',
];

pageHeadingTemplates.forEach( function ( file ) {
	var src = read( file );
	check(
		file + " calls section-heading with 'level' => 1 for its page title",
		/'level'\s*=>\s*1\s*,?/.test( src )
	);
});

// 2b. Faz 12e: archive-mb_yeterlilik.php sayfa başlığını template-parts/page/page-hero ile verir (tek <h1>, page-hero.php içinde);
//     kendisi <h1> veya section-heading level 1 ÇİZMEZ (çift H1 olmasın).
var qualArchiveSrc = read( 'archive-mb_yeterlilik.php' );
check( 'archive-mb_yeterlilik.php renders its single H1 via template-parts/page/page-hero', /template-parts\/page\/page-hero/.test( qualArchiveSrc ) && ! /<h1/.test( qualArchiveSrc ) && ! /'level'\s*=>\s*1/.test( qualArchiveSrc ) );
check( 'page-hero.php renders exactly one <h1>', ( read( 'template-parts/page/page-hero.php' ).replace( /\/\*[\s\S]*?\*\//g, '' ).match( /<h1>/g ) || [] ).length === 1 );

// 3. index.php: exactly one page-level H1 (via section-heading level=>1),
//    and the in-loop post titles must be H2, never H1 — this must hold
//    whether the loop yields zero, one, or many posts.
var indexSrc = read( 'index.php' );
check(
	"index.php calls section-heading with 'level' => 1 for the page title",
	/'level'\s*=>\s*1\s*,?/.test( indexSrc )
);
check(
	'index.php loop uses <h2> for individual post titles (not <h1>)',
	/<h2>\s*<\?php\s+the_title\(\);\s*\?>\s*<\/h2>/.test( indexSrc )
);
check(
	'index.php loop does not also emit an <h1> around the_title() (would duplicate the page H1)',
	! /<h1>\s*<\?php\s+the_title\(\);\s*\?>\s*<\/h1>/.test( indexSrc )
);

// 4. Homepage section headings (template-parts/home/**) must NOT pass
//    level => 1 — front-page.php's own page-level H1 comes from hero.php,
//    and every other home section heading must stay H2 (the component
//    default) so the homepage never ends up with more than one H1.
var homeDir = path.join( themeRoot, 'template-parts', 'home' );
var homeFiles = fs.readdirSync( homeDir ).filter( function ( f ) {
	return f.indexOf( '.php' ) !== -1;
});

homeFiles.forEach( function ( file ) {
	var src = fs.readFileSync( path.join( homeDir, file ), 'utf8' );
	if ( src.indexOf( 'section-heading' ) === -1 ) {
		return;
	}
	check(
		'template-parts/home/' + file + " does not pass 'level' => 1 to section-heading (must stay H2 default)",
		! /'level'\s*=>\s*1/.test( src )
	);
});

if ( failures.length ) {
	console.error( failures.length + ' check(s) FAILED:' );
	failures.forEach( function ( f ) { console.error( '  - ' + f ); } );
	process.exit( 1 );
}

console.log(
	'All heading contract checks passed (static source-text scan of the real PHP templates; not a PHP/WordPress execution).'
);
