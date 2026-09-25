<?php
/**
 * Button component shell. Presentation only.
 *
 * $args:
 * - label (string, required)
 * - url (string) default '#' is NEVER used as a real default — if
 *   'url' is omitted this renders a <button type="button"> instead of
 *   a link, so no accidental dead "#" href is ever produced.
 * - variant (string) 'primary' | 'secondary' | 'ghost' — default 'primary'.
 *   Any other value falls back to 'primary' (allowlisted, not just
 *   passed through sanitize_html_class()).
 * - size (string) '' | 'sm'
 * - block (bool) default false
 * - target (string) optional, e.g. '_blank' (adds rel="noopener noreferrer")
 * - attrs (array) extra key => value HTML attributes. The attribute NAME
 *   must match the allowlist below (id, name, value, aria-*, data-*,
 *   disabled, download) — anything else (on*, style, formaction, ...) is
 *   silently dropped. Value handling is type-aware, not just escaped:
 *     - 'disabled' is a real HTML boolean attribute: any truthy/non-empty
 *       scalar (true, 1, '1', 'disabled') emits a bare ` disabled` with NO
 *       value; false/null/''/0/'0' emits nothing at all — WordPress'/HTML's
 *       own rule that even `disabled=""` still disables a control, so a
 *       caller passing `disabled => false` must never produce the
 *       attribute text at all.
 *     - 'download' may be boolean (bare ` download`) or a scalar filename
 *       string (` download="filename.pdf"`, escaped) per the HTML
 *       attribute's own dual contract; false/null/'' emits nothing.
 *     - id/name/value/aria-* / data-* accept scalar values only (string,
 *       int, float, bool-as-'1'/''); an array or object value is silently
 *       dropped rather than being coerced into "Array" text or echoed raw.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed_variants = array( 'primary', 'secondary', 'ghost' );

$label   = isset( $args['label'] ) ? $args['label'] : '';
$url     = isset( $args['url'] ) ? $args['url'] : '';
$variant = isset( $args['variant'] ) && in_array( $args['variant'], $allowed_variants, true ) ? $args['variant'] : 'primary';
$size    = isset( $args['size'] ) ? $args['size'] : '';
$block   = ! empty( $args['block'] );
$target  = isset( $args['target'] ) ? $args['target'] : '';
$attrs   = isset( $args['attrs'] ) && is_array( $args['attrs'] ) ? $args['attrs'] : array();

$classes = array( 'btn', 'btn-' . $variant );
if ( 'sm' === $size ) {
	$classes[] = 'btn-sm';
}
if ( $block ) {
	$classes[] = 'btn-block';
}

$boolean_attrs = array( 'disabled' );
$named_attrs   = array( 'id', 'name', 'value', 'download' );

$extra_attrs = '';
foreach ( $attrs as $attr_name => $attr_value ) {
	$attr_name = (string) $attr_name;
	$is_boolean = in_array( $attr_name, $boolean_attrs, true );
	$is_named   = in_array( $attr_name, $named_attrs, true )
		|| 0 === strpos( $attr_name, 'aria-' )
		|| 0 === strpos( $attr_name, 'data-' );

	if ( ! $is_boolean && ! $is_named ) {
		continue;
	}
	if ( is_array( $attr_value ) || is_object( $attr_value ) ) {
		continue;
	}

	if ( $is_boolean ) {
		// Real HTML boolean attribute: presence alone disables the
		// control, so a falsy value must produce NO attribute text at
		// all (not even `disabled=""`).
		if ( $attr_value ) {
			$extra_attrs .= ' ' . esc_attr( $attr_name );
		}
		continue;
	}

	if ( 'download' === $attr_name ) {
		if ( '' === $attr_value || null === $attr_value || false === $attr_value ) {
			continue;
		}
		if ( true === $attr_value ) {
			$extra_attrs .= ' download';
			continue;
		}
		$extra_attrs .= ' download="' . esc_attr( $attr_value ) . '"';
		continue;
	}

	if ( '' === $attr_value || null === $attr_value ) {
		continue;
	}
	$extra_attrs .= ' ' . esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
}

if ( '' !== $url ) {
	$rel = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
	printf(
		'<a class="%1$s" href="%2$s"%3$s%4$s%5$s>%6$s</a>',
		esc_attr( implode( ' ', $classes ) ),
		esc_url( $url ),
		$target ? ' target="' . esc_attr( $target ) . '"' : '',
		$rel,
		$extra_attrs,
		esc_html( $label )
	);
} else {
	printf(
		'<button type="button" class="%1$s"%2$s>%3$s</button>',
		esc_attr( implode( ' ', $classes ) ),
		$extra_attrs,
		esc_html( $label )
	);
}
