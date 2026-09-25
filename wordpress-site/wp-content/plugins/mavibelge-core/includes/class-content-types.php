<?php
/**
 * Registers the seven mavibelge-core custom post types.
 *
 * Each type gets its own capability_type (see roles/class-roles.php for
 * the matrix that actually grants those capabilities) so that, for
 * example, granting "publish_mb_ucret" never implies "publish_pages".
 *
 * show_in_rest is false for every type in this phase: no Gutenberg
 * block editor is required, and görev kartı 02 §2.8 / §3 asks for the
 * REST write surface to stay closed until a real need is defined.
 * WordPress falls back to the classic edit screen automatically when
 * show_in_rest is false, so this does not block content editing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Content_Types {

	public static function register_all() {
		foreach ( self::get_definitions() as $post_type => $args ) {
			register_post_type( $post_type, $args );
		}
	}

	/**
	 * @return array<string, array> post_type => register_post_type() args
	 */
	public static function get_definitions() {
		return array(
			'mb_yeterlilik' => array(
				'labels'             => self::labels( 'Yeterlilik', 'Yeterlilikler' ),
				'public'              => true,
				'has_archive'         => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-awards',
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
				'rewrite'             => array( 'slug' => 'yeterlilikler', 'with_front' => false ),
				'query_var'           => true,
				'capability_type'     => array( 'mb_yeterlilik', 'mb_yeterlilikler' ),
				'map_meta_cap'        => true,
			),
			'mb_ucret'      => array(
				'labels'              => self::labels( 'Ücret Kaydı', 'Ücret Kayıtları' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-money-alt',
				'supports'            => array( 'title', 'custom-fields' ),
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => array( 'mb_ucret', 'mb_ucretler' ),
				'map_meta_cap'        => true,
			),
			'mb_haber'      => array(
				'labels'              => self::labels( 'Haber', 'Haberler' ),
				'public'              => true,
				'has_archive'         => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-megaphone',
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
				'rewrite'             => array( 'slug' => 'haberler', 'with_front' => false ),
				'query_var'           => true,
				'capability_type'     => array( 'mb_haber', 'mb_haberler' ),
				'map_meta_cap'        => true,
			),
			'mb_dokuman'    => array(
				'labels'              => self::labels( 'Doküman', 'Dokümanlar' ),
				'public'              => true,
				'has_archive'         => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-media-document',
				'supports'            => array( 'title', 'custom-fields' ),
				'rewrite'             => array( 'slug' => 'dokumanlar', 'with_front' => false ),
				'query_var'           => true,
				'capability_type'     => array( 'mb_dokuman', 'mb_dokumanlar' ),
				'map_meta_cap'        => true,
			),
			'mb_referans'   => array(
				'labels'              => self::labels( 'Referans', 'Referanslar' ),
				'public'              => true,
				'has_archive'         => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-building',
				'supports'            => array( 'title', 'custom-fields' ),
				'rewrite'             => array( 'slug' => 'referanslar', 'with_front' => false ),
				'query_var'           => true,
				'capability_type'     => array( 'mb_referans', 'mb_referanslar' ),
				'map_meta_cap'        => true,
			),
			'mb_lokasyon'   => array(
				'labels'              => self::labels( 'Lokasyon', 'Lokasyonlar' ),
				'public'              => true,
				'has_archive'         => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-location',
				'supports'            => array( 'title', 'custom-fields' ),
				'rewrite'             => array( 'slug' => 'lokasyonlar', 'with_front' => false ),
				'query_var'           => true,
				'capability_type'     => array( 'mb_lokasyon', 'mb_lokasyonlar' ),
				'map_meta_cap'        => true,
			),
			'mb_sss'        => array(
				'labels'              => self::labels( 'SSS', 'SSS Kayıtları', true ),
				'public'              => true,
				'has_archive'         => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-editor-help',
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
				'rewrite'             => array( 'slug' => 'sss', 'with_front' => false ),
				'query_var'           => true,
				'capability_type'     => array( 'mb_sss', 'mb_sss_kayitlari' ),
				'map_meta_cap'        => true,
			),
		);
	}

	private static function labels( $singular, $plural, $is_acronym = false ) {
		$new_item = $is_acronym ? 'Yeni ' . $singular : 'Yeni ' . $singular . ' Ekle';
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			'add_new'            => 'Yeni Ekle',
			'add_new_item'       => $new_item,
			'edit_item'          => $singular . ' Düzenle',
			'new_item'           => 'Yeni ' . $singular,
			'view_item'          => $singular . ' Görüntüle',
			'view_items'         => $plural . ' Görüntüle',
			'search_items'       => $singular . ' Ara',
			'not_found'          => $singular . ' bulunamadı.',
			'not_found_in_trash' => 'Çöp kutusunda ' . $singular . ' bulunamadı.',
			'all_items'          => 'Tüm ' . $plural,
			'archives'           => $plural . ' Arşivi',
		);
	}
}
