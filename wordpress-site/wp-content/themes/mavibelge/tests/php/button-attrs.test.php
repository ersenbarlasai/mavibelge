<?php
/**
 * Static contract test for template-parts/components/button.php's
 * disabled/download/scalar/variant handling (Faz 3 İkinci Kabul
 * Düzeltmesi §5).
 *
 * NOT EXECUTED in this session — no `php` binary is available in this
 * environment (verified: `command -v php` returns nothing). This file
 * is written so it CAN be run once a PHP CLI is available:
 *
 *   php wordpress-site/wp-content/themes/mavibelge/tests/php/button-attrs.test.php
 *
 * It stubs the small set of WordPress escaping/i18n functions
 * button.php calls (esc_attr, esc_html, esc_url) with minimal
 * pass-through equivalents — real htmlspecialchars-based escaping, not
 * WordPress's full implementation — then requires the real
 * button.php file with different $args and captures its actual output
 * via output buffering. This exercises the REAL component file, not a
 * re-typed copy of its logic.
 *
 * Do not claim this passed unless it was actually run with `php` and
 * printed "ALL PASSED".
 */

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$component = __DIR__ . '/../../template-parts/components/button.php';

function render_button( $args ) {
	global $component;
	ob_start();
	( function () use ( $args, $component ) {
		include $component;
	} )();
	return ob_get_clean();
}

$failures = array();

function assert_test( $condition, $label, &$failures ) {
	if ( ! $condition ) {
		$failures[] = $label;
	}
}

// disabled => false must NOT appear in output at all (not even disabled="").
$html = render_button( array( 'label' => 'Kaydet', 'attrs' => array( 'disabled' => false ) ) );
assert_test( false === strpos( $html, 'disabled' ), 'disabled=>false emits no disabled attribute at all', $failures );

// disabled => true must emit a single bare `disabled` (no ="...").
$html = render_button( array( 'label' => 'Kaydet', 'attrs' => array( 'disabled' => true ) ) );
assert_test( 1 === preg_match( '/\sdisabled(\s|>)/', $html ), 'disabled=>true emits bare disabled attribute', $failures );
assert_test( false === strpos( $html, 'disabled=' ), 'disabled=>true never emits disabled="..."', $failures );

// array/object values must not leak into output as "Array" or serialized text.
$html = render_button( array( 'label' => 'Kaydet', 'attrs' => array( 'data-x' => array( 'a', 'b' ) ) ) );
assert_test( false === strpos( $html, 'Array' ), 'array attr value never leaks as literal "Array"', $failures );
assert_test( false === strpos( $html, 'data-x' ), 'array attr value is dropped entirely, not emitted empty', $failures );

// on*/style/formaction must be rejected regardless of value.
$html = render_button( array( 'label' => 'Kaydet', 'attrs' => array( 'onclick' => 'alert(1)', 'style' => 'color:red', 'formaction' => 'https://evil.example' ) ) );
assert_test( false === strpos( $html, 'onclick' ), 'onclick attribute rejected', $failures );
assert_test( false === strpos( $html, 'style=' ), 'style attribute rejected', $failures );
assert_test( false === strpos( $html, 'formaction' ), 'formaction attribute rejected', $failures );

// invalid variant falls back to primary.
$html = render_button( array( 'label' => 'Kaydet', 'variant' => 'not-a-real-variant' ) );
assert_test( false !== strpos( $html, 'btn-primary' ), 'invalid variant falls back to btn-primary', $failures );
assert_test( false === strpos( $html, 'btn-not-a-real-variant' ), 'invalid variant string never reaches class output', $failures );

if ( $failures ) {
	fwrite( STDERR, count( $failures ) . " check(s) FAILED:\n" );
	foreach ( $failures as $f ) {
		fwrite( STDERR, "  - $f\n" );
	}
	exit( 1 );
}

echo "ALL PASSED (" . 6 . " checks, button.php real source, stubbed WP escaping functions)\n";
