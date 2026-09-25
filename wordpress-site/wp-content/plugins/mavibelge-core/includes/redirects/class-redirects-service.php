<?php
/**
 * Faz 9 — eski URL yönlendirme kayıt sistemi (WordPress bağlantısı). Kurallar `MaviBelge_Core_Redirects_Rules`
 * ile doğrulanır; geçerli küme `mavibelge_core_redirects` seçeneğinde (autoload KAPALI) saklanır. Yönlendirme YALNIZ
 * istek gerçekten 404 ise uygulanır (var olan içeriği asla gölgelemez); yalnız AKTİF kurallar çalışır. Bozuk küme
 * hiçbir yönlendirme üretmez (fail-closed). Hedefler iç yoldur (home_url ile birleştirilir).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Redirects_Service {

	const OPTION = 'mavibelge_core_redirects';

	/** @var array|null İstek başına dizin önbelleği. */
	private static $index = null;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 0 );
	}

	/** @return array Kayıtlı kurallar (bozuk depo -> boş). */
	public static function rules() {
		$rules = get_option( self::OPTION, array() );
		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Kümeyi doğrular ve kaydeder. Geçersizse HİÇBİR şey yazılmaz.
	 *
	 * @return array{ok: bool, errors: array}
	 */
	public static function save_rules( array $rules ) {
		$check = MaviBelge_Core_Redirects_Rules::validate_set( $rules );
		if ( ! $check['valid'] ) {
			return array( 'ok' => false, 'errors' => $check['errors'] );
		}
		update_option( self::OPTION, array_values( $rules ), false );
		self::$index = null;
		return array( 'ok' => true, 'errors' => array() );
	}

	/** Hedef yolu WordPress'te gerçekten var mı (sayfa/yazı/arşiv) — dry-run bildirimi için. */
	public static function target_exists( $normalizedPath ) {
		if ( ! is_string( $normalizedPath ) ) {
			return false;
		}
		$path = trim( $normalizedPath, '/' );
		if ( '' === $path ) {
			return true;
		}
		if ( null !== get_page_by_path( $path, OBJECT, array( 'page', 'mb_haber', 'mb_dokuman', 'mb_yeterlilik', 'mb_lokasyon' ) ) ) {
			return true;
		}
		$parts = explode( '/', $path );
		// /haberler/ /dokumanlar/ /yeterlilikler/ arşivleri ve /sektor/<slug>/ terimleri.
		foreach ( array( 'mb_haber', 'mb_dokuman', 'mb_yeterlilik' ) as $type ) {
			$object = get_post_type_object( $type );
			if ( $object && ! empty( $object->has_archive ) && isset( $object->rewrite['slug'] ) && 1 === count( $parts ) && $object->rewrite['slug'] === $parts[0] ) {
				return true;
			}
			if ( $object && isset( $object->rewrite['slug'] ) && 2 === count( $parts ) && $object->rewrite['slug'] === $parts[0] ) {
				$found = get_posts( array( 'post_type' => $type, 'name' => $parts[1], 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true, 'suppress_filters' => true ) );
				if ( ! empty( $found ) ) {
					return true;
				}
			}
		}
		if ( 2 === count( $parts ) && 'sektor' === $parts[0] && taxonomy_exists( 'mb_sektor' ) ) {
			return false !== get_term_by( 'slug', $parts[1], 'mb_sektor' );
		}
		return false;
	}

	public static function maybe_redirect() {
		if ( ! is_404() || is_admin() ) {
			return;
		}
		if ( null === self::$index ) {
			self::$index = MaviBelge_Core_Redirects_Rules::build_index( self::rules() );
		}
		if ( empty( self::$index ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path = preg_replace( '/[?#].*$/', '', $uri );
		if ( '' !== $home && '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
			$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
		}
		$rule = MaviBelge_Core_Redirects_Rules::match( self::$index, $path );
		if ( null === $rule ) {
			return;
		}
		if ( 410 === $rule['status'] ) {
			status_header( 410 );
			nocache_headers();
			return; // 404 şablonu 410 durum koduyla gösterilir.
		}
		wp_safe_redirect( home_url( $rule['target'] ), (int) $rule['status'] );
		exit;
	}
}
