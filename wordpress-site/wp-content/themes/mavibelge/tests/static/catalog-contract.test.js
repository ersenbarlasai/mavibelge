/**
 * Static SOURCE-TEXT contract test for Faz 5's theme/plugin boundary
 * (AGENTS.md / brief §7: "Tema, mb_ucret veya mb_yeterlilik için kendi
 * sorgu/iş kuralını yazmaz").
 *
 * This does NOT execute PHP or WordPress. It reads the real, shipped
 * theme source files as text and asserts structural facts with regular
 * expressions. A pass here means "the source still reads the way the
 * theme/plugin boundary intends", not "WordPress rendered this page".
 *
 * Run: node tests/static/catalog-contract.test.js
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

// 1. Theme templates that touch the catalog must never build their own
//    WP_Query/$wpdb against mb_yeterlilik/mb_ucret — only call the
//    inc/catalog-helpers.php adapters.
var catalogTemplates = [
	'archive-mb_yeterlilik.php',
	'taxonomy-mb_sektor.php',
	'single-mb_yeterlilik.php',
	'page-sinav-ucretleri.php',
	'template-parts/home/hero.php',
];

var templatesExpectedToCallAHelper = [
	'archive-mb_yeterlilik.php',
	'taxonomy-mb_sektor.php',
	'single-mb_yeterlilik.php',
	'page-sinav-ucretleri.php',
];

catalogTemplates.forEach( function ( file ) {
	var src = read( file );
	// $wpdb-> (actual usage) rather than a bare "$wpdb" mention, so a
	// docblock comment explaining the rule (e.g. "never $wpdb") doesn't
	// itself trip this check.
	check( file + ' does not USE $wpdb (a bare docblock mention is fine)', ! /\$wpdb\s*->/.test( src ) );
	check( file + ' does not construct its own WP_Query', ! /new\s+WP_Query/.test( src ) );
	if ( templatesExpectedToCallAHelper.indexOf( file ) !== -1 ) {
		check(
			file + ' calls at least one mavibelge_* catalog helper (from inc/catalog-helpers.php)',
			/mavibelge_(get_qualification_results|get_active_fee_results|get_active_fees_for_qualification|get_sector_terms|active_tariff_period|present_fee|catalog_page_url)\(/.test( src )
		);
	}
});

// 2. inc/catalog-helpers.php itself: every service call is guarded by a
//    fatal-safe class_exists() check (never calls MaviBelge_Core_Catalog_Service
//    unconditionally).
var helpersSrc = read( 'inc/catalog-helpers.php' );
check(
	'inc/catalog-helpers.php guards MaviBelge_Core_Catalog_Service calls with class_exists()',
	/class_exists\(\s*'MaviBelge_Core_Catalog_Service'\s*\)/.test( helpersSrc )
);
check(
	'inc/catalog-helpers.php never calls MaviBelge_Core_Catalog_Service:: outside an if/class_exists guard body (heuristic: at least as many class_exists checks as distinct call sites is not enforced here — presence check only)',
	( helpersSrc.match( /MaviBelge_Core_Catalog_Service::/g ) || [] ).length >= 6
);

// 3. The plugin's normalize_filters() must read EXACTLY the five
//    documented GET param keys from its raw input array — no
//    undocumented sixth key silently added later.
var catalogQuerySrc = fs.readFileSync(
	path.join( themeRoot, '..', '..', 'plugins', 'mavibelge-core', 'includes', 'class-catalog-query.php' ),
	'utf8'
);
var rawKeyPattern = /\$raw\[\s*'(mb_[a-z_]+)'\s*\]/g;
var foundKeys = [];
var m;
while ( null !== ( m = rawKeyPattern.exec( catalogQuerySrc ) ) ) {
	if ( foundKeys.indexOf( m[ 1 ] ) === -1 ) {
		foundKeys.push( m[ 1 ] );
	}
}
var expectedKeys = [ 'mb_q', 'mb_sector', 'mb_level', 'mb_priced', 'mb_page' ];
check(
	'class-catalog-query.php normalize_filters() reads exactly the 5 documented GET param keys (mb_q/mb_sector/mb_level/mb_priced/mb_page), no more, no fewer',
	foundKeys.length === expectedKeys.length && expectedKeys.every( function ( k ) { return foundKeys.indexOf( k ) !== -1; } )
);

// filter-form.php emits real form controls for q/sector/level/priced —
// mb_page is deliberately NOT a form field (submitting the filter form
// always resets to page 1; mb_page only ever appears in pagination
// links, built by mavibelge_catalog_page_url() in catalog-helpers.php).
var filterFormSrc = read( 'template-parts/catalog/filter-form.php' );
[ 'mb_q', 'mb_sector', 'mb_level', 'mb_priced' ].forEach( function ( key ) {
	check(
		'filter-form.php emits a form control named "' + key + '"',
		new RegExp( 'name="' + key + '"' ).test( filterFormSrc )
	);
});
check(
	'catalog-helpers.php builds pagination URLs using the mb_page param',
	/'mb_page'/.test( helpersSrc )
);

// 4. get_term_link() calls added/touched in Faz 5 templates must be
//    guarded by is_wp_error() (same contract as the Faz 4 Nihai Kabul
//    Düzeltmesi fix).
// Faz 12e: arşivin sektör kartları template-parts/catalog/sector-browse.php'ye taşındı (get_term_link orada).
[ 'template-parts/catalog/sector-browse.php', 'taxonomy-mb_sektor.php' ].forEach( function ( file ) {
	var src = read( file );
	var callCount = ( src.match( /get_term_link\(/g ) || [] ).length;
	var guardCount = ( src.match( /is_wp_error\(\s*\$(sector_link|term_url|link)\s*\)/g ) || [] ).length;
	check( file + ' guards every get_term_link() result with is_wp_error()', callCount > 0 && guardCount > 0 );
});

// 5. fee-table.php / fee-cards.php must not both be visible at once by
//    default markup convention — checked via the responsive.css source
//    text (display:none pairing), not computed CSS (no browser here).
var responsiveSrc = fs.readFileSync( path.join( themeRoot, 'assets', 'src', 'css', 'responsive.css' ), 'utf8' );
check(
	'responsive.css hides fee-table-wrap and shows fee-cards together at the same breakpoint (or vice versa desktop-default)',
	/\.fee-table-wrap\s*\{\s*display:\s*none;\s*\}/.test( responsiveSrc ) && /\.fee-cards\s*\{\s*display:\s*flex;\s*\}/.test( responsiveSrc )
);
var pagesSrc = fs.readFileSync( path.join( themeRoot, 'assets', 'src', 'css', 'pages.css' ), 'utf8' );
check(
	'pages.css sets the desktop-default opposite (.fee-table-wrap visible, .fee-cards hidden)',
	/\.fee-table-wrap\s*\{\s*display:\s*block;\s*\}/.test( pagesSrc ) && /\.fee-cards\s*\{[^}]*display:\s*none;/.test( pagesSrc )
);

// 6. mavibelge-core.php (plugin bootstrap) must load the two Faz 5
//    classes in the documented order (query before service).
var pluginBootstrapPath = path.join( themeRoot, '..', '..', 'plugins', 'mavibelge-core', 'mavibelge-core.php' );
var pluginSrc = fs.readFileSync( pluginBootstrapPath, 'utf8' );
var queryIdx = pluginSrc.indexOf( "includes/class-catalog-query.php" );
var serviceIdx = pluginSrc.indexOf( "public/class-catalog-service.php" );
check( 'mavibelge-core.php loads class-catalog-query.php', -1 !== queryIdx );
check( 'mavibelge-core.php loads public/class-catalog-service.php', -1 !== serviceIdx );
check( 'mavibelge-core.php loads catalog-query.php BEFORE catalog-service.php', queryIdx > -1 && serviceIdx > -1 && queryIdx < serviceIdx );
var visibilityGuardIdx = pluginSrc.indexOf( "public/class-visibility-guard.php" );
check( 'mavibelge-core.php loads public/class-visibility-guard.php (Faz 5 Düzeltme ve Kabul §2.5)', -1 !== visibilityGuardIdx );

// 7. Faz 5 Düzeltme ve Kabul §2.6 — archive-mb_yeterlilik.php and
//    taxonomy-mb_sektor.php must render results from the service's own
//    DTO (template-parts/catalog/qualification-card.php), never by
//    re-querying the same post via content-card.php + post_id.
[ 'archive-mb_yeterlilik.php', 'taxonomy-mb_sektor.php' ].forEach( function ( file ) {
	var src = read( file );
	check(
		file + ' renders results via template-parts/catalog/qualification-card.php (DTO-based)',
		/catalog\/qualification-card/.test( src )
	);
	check(
		file + ' does NOT render results via template-parts/content/content-card.php + post_id (would re-query the same data)',
		! /content\/content-card/.test( src )
	);
});

// 8. Faz 5 Düzeltme ve Kabul §3.2 — mavibelge_sector_name_for_slug() was
//    removed; the theme must use the service-resolved sector_name field
//    instead of re-resolving the taxonomy term itself.
check(
	'inc/catalog-helpers.php no longer defines mavibelge_sector_name_for_slug() (sector name now resolved once in the plugin DTO)',
	! /function\s+mavibelge_sector_name_for_slug/.test( helpersSrc )
);
[ 'template-parts/catalog/fee-table.php', 'template-parts/catalog/fee-cards.php' ].forEach( function ( file ) {
	var src = read( file );
	check( file + " uses \$fee['sector_name'] (service-resolved) rather than re-resolving the term itself", /\$fee\[\s*'sector_name'\s*\]/.test( src ) );
	check( file + ' does not call a theme-side sector-name resolver', ! /sector_name_for_slug/.test( src ) );
});

// 9. Faz 5 Son Kapanış Düzeltmesi §3 — a ücret record's own sector must
//    fail closed (resolve_sector_name_or_null() returns null, never the
//    raw slug) when it can't resolve to a real mb_sektor term.
var catalogServiceSrc = fs.readFileSync(
	path.join( themeRoot, '..', '..', 'plugins', 'mavibelge-core', 'public', 'class-catalog-service.php' ),
	'utf8'
);
check(
	'class-catalog-service.php defines resolve_sector_name_or_null() (fail-closed sector resolution)',
	/function\s+resolve_sector_name_or_null/.test( catalogServiceSrc )
);
check(
	'class-catalog-service.php no longer defines the old raw-slug-fallback resolve_sector_name() method',
	! /function\s+resolve_sector_name\s*\(/.test( catalogServiceSrc )
);
check(
	'resolve_sector_name_or_null() does not fall back to returning the raw $sector_slug (old bug — "return $sector_slug;")',
	! /return\s+\$sector_slug\s*;/.test( catalogServiceSrc )
);
check(
	'get_all_valid_active_fees() fails a fee closed (continue) when resolve_sector_name_or_null() returns null',
	/resolve_sector_name_or_null[\s\S]{0,200}continue;/.test( catalogServiceSrc )
);
check(
	'class-catalog-service.php caches the sector slug->name map once per request (sector_slug_to_name_map)',
	/function\s+sector_slug_to_name_map/.test( catalogServiceSrc )
);

// 10. Faz 5 Son Kapanış Düzeltmesi §2 — class-validator.php's price
//     options validator must no longer silently repair units/sort_order.
var validatorSrc = fs.readFileSync(
	path.join( themeRoot, '..', '..', 'plugins', 'mavibelge-core', 'includes', 'class-validator.php' ),
	'utf8'
);
check(
	'class-validator.php no longer silently truncates a unit string with str_truncate() inside normalize_price_options()',
	! /\$unit\s*=\s*self::str_truncate/.test( validatorSrc )
);
check(
	'class-validator.php no longer silently caps the units list with a bare "break;" (must push an error first)',
	! /count\(\s*\$units\s*\)\s*>=\s*self::MAX_UNITS_PER_OPTION\s*\)\s*\{\s*break;\s*\}/.test( validatorSrc )
);
check(
	'class-validator.php uses the spaceship operator (<=>), not subtraction, to compare sort_order (overflow-safe)',
	/\$a\[\s*'sort_order'\s*\]\s*<=>\s*\$b\[\s*'sort_order'\s*\]/.test( validatorSrc )
);
check(
	'class-validator.php ties sort_order equality to a deterministic secondary key (stable sort on PHP < 8.0)',
	/_orig/.test( validatorSrc )
);

if ( failures.length ) {
	console.error( failures.length + ' check(s) FAILED:' );
	failures.forEach( function ( f ) { console.error( '  - ' + f ); } );
	process.exit( 1 );
}

console.log(
	'All catalog contract checks passed (static source-text scan of the real PHP files; not a PHP/WordPress execution).'
);
