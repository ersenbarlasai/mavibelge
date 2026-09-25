<?php
/**
 * Core theme supports and menu locations.
 *
 * Faz 3: menu location labels Türkçeleştirildi (Faz 3 brief §6.2);
 * footer'ın iki ayrı sütunu (Hızlı Bağlantılar / Kurumsal) için iki
 * ayrı konum eklendi — statik referansın iki farklı başlıklı listesini
 * tek bir düz menüye sıkıştırmak yerine. Minimal editör stili eklendi
 * (add_editor_style()) — özel blok yok, yalnız temel tipografi eşleşmesi.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mavibelge_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	register_nav_menus(
		array(
			'primary'          => __( 'Ana Menü', 'mavibelge' ),
			'footer_quick'     => __( 'Footer — Hızlı Bağlantılar', 'mavibelge' ),
			'footer_kurumsal'  => __( 'Footer — Kurumsal', 'mavibelge' ),
		)
	);

	if ( file_exists( get_stylesheet_directory() . '/assets/src/css/editor.css' ) ) {
		add_editor_style( 'assets/src/css/editor.css' );
	}
}
add_action( 'after_setup_theme', 'mavibelge_setup' );

/**
 * Site yalnız Türkçedir: kurulum dili sonradan değişse/yanlış olsa da <html lang> DAİMA tr-TR olur
 * (ekran okuyucu telaffuzu ve arama motoru dil sinyali). Yönetim ekranını etkilemez.
 */
function mavibelge_force_turkish_lang_attribute( $output ) {
	if ( is_admin() ) {
		return $output;
	}
	return preg_replace( '/lang="[^"]*"/', 'lang="tr-TR"', (string) $output, 1 );
}
add_filter( 'language_attributes', 'mavibelge_force_turkish_lang_attribute' );
