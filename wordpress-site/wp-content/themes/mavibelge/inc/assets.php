<?php
/**
 * Enqueues theme CSS/JS. Faz 3: real dist/style.css and dist/main.js
 * now exist, so this actually loads them (still checks file_exists()
 * first — safe if a future phase temporarily empties dist/ again).
 * Cache-busting uses filemtime() of the dist file when readable,
 * falling back to the theme version string — no build tool, no Node,
 * required at request time.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mavibelge_asset_version( $relative_path ) {
	$full_path = get_stylesheet_directory() . $relative_path;
	if ( file_exists( $full_path ) ) {
		$mtime = filemtime( $full_path );
		if ( false !== $mtime ) {
			return (string) $mtime;
		}
	}
	return wp_get_theme()->get( 'Version' );
}

function mavibelge_enqueue_assets() {
	$style_path = '/assets/dist/style.css';
	if ( file_exists( get_stylesheet_directory() . $style_path ) ) {
		wp_enqueue_style(
			'mavibelge-style',
			get_stylesheet_directory_uri() . $style_path,
			array(),
			mavibelge_asset_version( $style_path )
		);
	}

	$script_path = '/assets/dist/main.js';
	if ( file_exists( get_stylesheet_directory() . $script_path ) ) {
		wp_enqueue_script(
			'mavibelge-main',
			get_stylesheet_directory_uri() . $script_path,
			array(),
			mavibelge_asset_version( $script_path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'mavibelge_enqueue_assets' );

/**
 * Favicon fallback: if the site owner has set a WordPress Site Icon
 * (Settings → General), WordPress already outputs its own favicon
 * <link> tags via wp_site_icon() (hooked to wp_head automatically) —
 * this function does nothing in that case, to avoid a duplicate
 * favicon tag. Only when no Site Icon is set does this output the
 * theme's own original favicon.png as a safe fallback.
 */
function mavibelge_favicon_fallback() {
	if ( has_site_icon() ) {
		return;
	}
	$favicon_path = '/assets/images/logos/favicon.png';
	if ( ! file_exists( get_stylesheet_directory() . $favicon_path ) ) {
		return;
	}
	printf(
		'<link rel="icon" type="image/png" href="%1$s">' . "\n",
		esc_url( get_stylesheet_directory_uri() . $favicon_path )
	);
}
add_action( 'wp_head', 'mavibelge_favicon_fallback', 1 );

/**
 * Faz 10 — ön yüzde KULLANILMAYAN WordPress varsayılanlarını kaldırır (yönetim/editör etkilenmez).
 * Tema blok, emoji, oEmbed, jQuery kullanmaz. Tek süzgeç: `mavibelge_strip_wp_defaults` (false döndürülürse hiçbiri
 * kaldırılmaz). Sürümleme filemtime ile (mavibelge_asset_version) korunur.
 */
function mavibelge_strip_wp_defaults() {
	if ( is_admin() || ! apply_filters( 'mavibelge_strip_wp_defaults', true ) ) {
		return;
	}
	// Emoji.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	// oEmbed / wp-embed.
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	// Üst bilgi bağlantıları.
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	// Genel (global) stiller ve blok kitaplığı.
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
}
add_action( 'init', 'mavibelge_strip_wp_defaults', 20 );
add_filter( 'the_generator', '__return_empty_string' );

function mavibelge_dequeue_frontend_defaults() {
	if ( is_admin() || is_customize_preview() || ! apply_filters( 'mavibelge_strip_wp_defaults', true ) ) {
		return;
	}
	foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'global-styles-css-custom-properties', 'wp-embed' ) as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
	wp_dequeue_script( 'wp-embed' );
	wp_deregister_script( 'wp-embed' );
	// Tema jQuery kullanmaz: ön yüzden jQuery ve jquery-migrate çıkarılır (eklenti eklenirse süzgeçle geri açılır).
	if ( apply_filters( 'mavibelge_dequeue_jquery', true ) ) {
		foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate' ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'mavibelge_dequeue_frontend_defaults', 100 );
