<?php
/**
 * Renders the primary menu in the markup the approved static design and
 * header.css expect: a top-level item WITH children renders BOTH a real
 * <a class="nav-parent-link"> (its own destination page — never lost)
 * AND a <button class="nav-toggle" aria-controls="submenu-ID"> that only
 * opens/closes the submenu. A top-level item WITHOUT children stays a
 * plain <a>. One level of children is wrapped in <ul id="submenu-ID"
 * class="submenu">.
 *
 * Depth contract: this walker only supports 2 levels (top-level + one
 * level of children), matching the approved static design and
 * header.css/.submenu (no nested .submenu-in-.submenu rules exist).
 * wp_nav_menu() is called with 'depth' => 2 in header.php so WordPress
 * itself never asks this walker to render a 3rd level; if a menu editor
 * nests a grandchild anyway, WordPress silently omits it at depth 2 —
 * this is a documented limitation (see docs/design-system.md), not a
 * silent HTML break, since there is no markup produced for it either way.
 *
 * WordPress adds the 'menu-item-has-children' class to a parent item
 * automatically before walking (wp_nav_menu() -> _wp_menu_item_classes_by_context()),
 * so that is used here rather than re-deriving the tree.
 *
 * Link attribute contract (mirrors core Walker_Nav_Menu::start_el()):
 * title/target/rel/href/aria-current are read from the real $item fields
 * ONCE into a single $atts array, that array is passed through
 * 'nav_menu_link_attributes' exactly once, and the final HTML is built
 * ONLY from what that filter returns — so no attribute (aria-current
 * included) can ever be emitted twice.
 *
 * 'walker_nav_menu_start_el' contract (verified against WordPress core
 * wp-includes/class-walker-nav-menu.php, Walker_Nav_Menu::start_el()):
 * the opening `<li id="..." class="...">` is written straight into the
 * shared $output BEFORE the filter runs — it is never part of the value
 * given to the filter. Only the per-item inner content (the <a> link,
 * and in this theme's parent-with-children case the extra <button
 * class="nav-toggle">) is built into $item_output and passed through
 * the filter; core's own filter call is
 * apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args )
 * — 4 arguments, no $id/$current_object_id — matched exactly here so a
 * plugin/eklenti hooking this filter (menu icon injectors, SEO/AIO
 * breadcrumb markers, accessibility add-ons) receives and can safely
 * wrap/prefix/suffix only the link content, never the <li> boundary,
 * exactly as it would against core.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * Submenu id computed in start_el() for the parent that was just
	 * output, consumed by the immediately-following start_lvl() call —
	 * Walker_Nav_Menu::start_lvl() is not given the parent $item, so this
	 * is the only way to keep the <button aria-controls> and <ul id> in
	 * sync per WordPress's own walk() call order (start_el then start_lvl).
	 *
	 * @var string
	 */
	protected $current_submenu_id = '';

	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$id      = $this->current_submenu_id ? ' id="' . esc_attr( $this->current_submenu_id ) . '"' : '';
		$output .= '<ul' . $id . ' class="submenu">';
	}

	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$has_children = in_array( 'menu-item-has-children', (array) $item->classes, true );

		// nav_menu_item_args runs first in core and every later filter in
		// this method (nav_menu_css_class, nav_menu_item_id,
		// nav_menu_link_attributes, nav_menu_item_title,
		// walker_nav_menu_start_el) receives whatever it returns — a
		// plugin/eklenti replacing $args (e.g. to change link_before/
		// link_after) must see that replacement everywhere, not just here.
		$args = apply_filters( 'nav_menu_item_args', $args, $item, $depth );

		$classes = (array) $item->classes;
		$classes = apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth );
		$classes = implode( ' ', array_filter( array_map( 'sanitize_html_class', $classes ) ) );

		$li_id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );

		// Core does not esc_html() $title here — it trusts saved menu-item
		// titles and whatever 'nav_menu_item_title' filters return as
		// already-safe (admin-authored) content. This theme deliberately
		// keeps esc_html() as an extra hardening layer: it costs
		// compatibility with a plugin that intentionally injects inline
		// markup (e.g. a badge/icon) via that filter — such markup would
		// be escaped to literal text here — but a menu title filter
		// returning attacker-influenced text (translation string mixups,
		// a misconfigured integration, etc.) can never become live HTML.
		// This is a conscious safety-over-100%-filter-compat trade-off,
		// not an oversight.
		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

		// The <li> boundary is written straight into the shared $output,
		// BEFORE 'walker_nav_menu_start_el' runs — see the class docblock.
		// $item_output below holds ONLY the inner content that filter is
		// allowed to see/wrap.
		$output .= '<li' . ( $li_id ? ' id="' . esc_attr( $li_id ) . '"' : '' ) . ( $classes ? ' class="' . esc_attr( $classes ) . '"' : '' ) . '>';

		if ( 0 === $depth && $has_children ) {
			$submenu_id                = 'submenu-' . (int) $item->ID;
			$this->current_submenu_id  = $submenu_id;

			$link_atts           = $this->base_link_atts( $item );
			$link_atts['class']  = 'nav-parent-link';
			$link_atts           = apply_filters( 'nav_menu_link_attributes', $link_atts, $item, $args, $depth );

			$item_output = '<a' . $this->build_attrs( $link_atts ) . '>' . esc_html( $title ) . '</a>';

			$toggle_label = sprintf(
				/* translators: %s: parent menu item title, e.g. "Kurumsal" */
				__( '%s alt menüsünü aç/kapat', 'mavibelge' ),
				$title
			);

			$item_output .= '<button type="button" class="nav-toggle" aria-expanded="false" aria-controls="' . esc_attr( $submenu_id ) . '">'
				. '<span class="screen-reader-text">' . esc_html( $toggle_label ) . '</span>'
				. ' <svg width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>'
				. '</button>';
		} else {
			$link_atts = $this->base_link_atts( $item );
			$link_atts = apply_filters( 'nav_menu_link_attributes', $link_atts, $item, $args, $depth );

			$item_output = '<a' . $this->build_attrs( $link_atts ) . '>' . esc_html( $title ) . '</a>';
		}

		// Matches core's exact 4-argument call — no $id/$current_object_id.
		$item_output = apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );

		$output .= $item_output;
	}

	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		// Runs for every item at every supported depth (0 and 1) — the
		// framework's own Walker::display_element() always calls end_el()
		// once per start_el(), so </li> belongs here uniformly rather than
		// being closed early inside start_el() for child items.
		$output .= '</li>';
	}

	/**
	 * Builds the single source-of-truth link attribute array from the
	 * real $item fields, mirroring core Walker_Nav_Menu::start_el():
	 * title <- $item->attr_title, target <- $item->target,
	 * rel <- $item->xfn, href <- $item->url, aria-current <- $item->current
	 * (WordPress core itself has set 'page' | 'true' | '' | false there
	 * since the 'current' menu-item logic runs before the walker). This
	 * is called exactly once per link and its result goes straight into
	 * the 'nav_menu_link_attributes' filter — nothing is added after.
	 *
	 * @param WP_Post $item
	 * @return array
	 */
	protected function base_link_atts( $item ) {
		$atts = array();

		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] = ! empty( $item->target ) ? $item->target : '';
		$atts['rel']    = ! empty( $item->xfn ) ? $item->xfn : '';
		$atts['href']   = ! empty( $item->url ) ? $item->url : '';

		if ( isset( $item->current ) && $item->current ) {
			$atts['aria-current'] = 'page';
		}

		return $atts;
	}

	/**
	 * Renders an attribute array into an escaped HTML attribute string.
	 * Empty/null values are skipped (so 'nav_menu_link_attributes' filters
	 * that leave e.g. target/rel/title blank do not emit empty attrs,
	 * matching core behavior). href uses esc_url(), everything else
	 * esc_attr(). Array/object values are dropped rather than stringified
	 * ("Array") or echoed raw.
	 *
	 * @param array $attrs
	 * @return string
	 */
	protected function build_attrs( array $attrs ) {
		$out = '';
		foreach ( $attrs as $name => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}
			if ( '' === $value || null === $value || false === $value ) {
				continue;
			}
			$name = preg_replace( '/[^a-zA-Z0-9-]/', '', (string) $name );
			if ( '' === $name ) {
				continue;
			}
			if ( 'href' === $name ) {
				$out .= ' href="' . esc_url( $value ) . '"';
			} else {
				$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}
		}
		return $out;
	}
}
