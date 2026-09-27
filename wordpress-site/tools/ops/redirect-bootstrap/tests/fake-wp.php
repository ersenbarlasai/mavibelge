<?php
/**
 * Geçici yönlendirme loader'ı testleri için EN KÜÇÜK sahte WordPress yüzeyi.
 *
 * Yalnız loader'ın ve gerçek `MaviBelge_Core_Redirects_Service`/`_Rules` sınıflarının çağırdığı fonksiyonlar
 * tanımlanır. Seçenek deposu bellektedir; her yazma `$GLOBALS['mbrb_env']['writes']` listesine düşer, böylece
 * "hiçbir şey yazılmadı" iddiası doğrudan ölçülür. Test verisi sentetiktir; ağ, veritabanı veya e-posta yoktur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

function mbrb_env_reset( array $overrides = array() ) {
	$GLOBALS['mbrb_env'] = array_merge(
		array(
			'is_admin'     => true,
			'can'          => true,
			'options'      => array(),
			'autoload'     => array(),
			'writes'       => array(),
			'hooks'        => array(),
			'fail_update'  => array(),
			'pages'        => array( 'kvkk', 'gizlilik-politikasi' ),
			'haber'        => array( '6-dilde-myk-belgesi-gecerliligi', 'mobilya-sektoru-belge-zorunlulugu' ),
		),
		$overrides
	);
}
mbrb_env_reset();

function mbrb_writes() {
	return $GLOBALS['mbrb_env']['writes'];
}

/* ---- ortam ---- */
function is_admin() {
	return (bool) $GLOBALS['mbrb_env']['is_admin'];
}
function current_user_can( $cap ) {
	return 'manage_options' === $cap && (bool) $GLOBALS['mbrb_env']['can'];
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['mbrb_env']['hooks'][] = $hook;
	return true;
}
function wp_create_nonce( $action ) {
	return 'ok:' . $action;
}
function wp_verify_nonce( $nonce, $action ) {
	return ( is_string( $nonce ) && 'ok:' . $action === $nonce ) ? 1 : false;
}
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/* ---- seçenek deposu ---- */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['mbrb_env']['options'] ) ? $GLOBALS['mbrb_env']['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['mbrb_env']['writes'][] = array( 'update', $name );
	if ( in_array( $name, $GLOBALS['mbrb_env']['fail_update'], true ) ) {
		return false;
	}
	$GLOBALS['mbrb_env']['options'][ $name ]  = $value;
	$GLOBALS['mbrb_env']['autoload'][ $name ] = $autoload;
	return true;
}
function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
	$GLOBALS['mbrb_env']['writes'][] = array( 'add', $name );
	if ( array_key_exists( $name, $GLOBALS['mbrb_env']['options'] ) ) {
		return false;
	}
	$GLOBALS['mbrb_env']['options'][ $name ]  = $value;
	$GLOBALS['mbrb_env']['autoload'][ $name ] = $autoload;
	return true;
}
function delete_option( $name ) {
	$GLOBALS['mbrb_env']['writes'][] = array( 'delete', $name );
	if ( ! array_key_exists( $name, $GLOBALS['mbrb_env']['options'] ) ) {
		return false;
	}
	unset( $GLOBALS['mbrb_env']['options'][ $name ], $GLOBALS['mbrb_env']['autoload'][ $name ] );
	return true;
}

/* ---- Redirects_Service::target_exists() bağımlılıkları ---- */
function get_page_by_path( $path, $output = 'OBJECT', $types = 'page' ) {
	return in_array( $path, $GLOBALS['mbrb_env']['pages'], true ) ? (object) array( 'ID' => 1 ) : null;
}
function get_post_type_object( $type ) {
	$slugs = array( 'mb_haber' => 'haberler', 'mb_dokuman' => 'dokumanlar', 'mb_yeterlilik' => 'yeterlilikler' );
	if ( ! isset( $slugs[ $type ] ) ) {
		return null;
	}
	return (object) array( 'has_archive' => true, 'rewrite' => array( 'slug' => $slugs[ $type ] ) );
}
function get_posts( $args ) {
	if ( isset( $args['post_type'], $args['name'] ) && 'mb_haber' === $args['post_type'] && in_array( $args['name'], $GLOBALS['mbrb_env']['haber'], true ) ) {
		return array( 7 );
	}
	return array();
}
function taxonomy_exists( $taxonomy ) {
	return 'mb_sektor' === $taxonomy;
}
function get_term_by( $field, $value, $taxonomy ) {
	return false;
}

/* ---- sunum ---- */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}
function admin_url( $path = '' ) {
	return 'https://ornek.example/wp-admin/' . ltrim( $path, '/' );
}
function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function wp_nonce_field( $action, $name, $referer = true, $echo = true ) {
	$html = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';
	if ( $echo ) {
		echo $html;
	}
	return $html;
}
function add_management_page( $page_title, $menu_title, $capability, $slug, $callback ) {
	$GLOBALS['mbrb_env']['hooks'][] = 'page:' . $slug;
	return 'tools_page_' . $slug;
}
