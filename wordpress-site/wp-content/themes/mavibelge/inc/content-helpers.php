<?php
/**
 * Faz 7 — haber/doküman/referans/lokasyon/SSS için ince, fatal-güvenli
 * adaptörler (mavibelge-core'un MaviBelge_Core_Content_Service'i üzerinde).
 * İş kuralı, veri doğrulama ve doğrudan WP_Query/$wpdb burada YOKTUR.
 * Eklenti pasifken her fonksiyon servisin döneceği belgelenmiş boş şekli
 * döndürür (tema fatal vermez, şablonlar dürüst boş durum gösterir).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mavibelge_content_available() {
	return class_exists( 'MaviBelge_Core_Content_Service' );
}

/** @return array{items: array, total: int, total_pages: int, page: int, page_size: int, args: array} */
function mavibelge_empty_content_page() {
	return array( 'items' => array(), 'total' => 0, 'total_pages' => 1, 'page' => 1, 'page_size' => 12, 'args' => array() );
}

/** @return array{items: array, total: int, total_pages: int, page: int, page_size: int, args: array} */
function mavibelge_get_news( array $raw_args ) {
	return mavibelge_content_available() ? MaviBelge_Core_Content_Service::get_news( $raw_args ) : mavibelge_empty_content_page();
}

/** @return array[] */
function mavibelge_get_latest_news( $limit = 3 ) {
	return mavibelge_content_available() ? MaviBelge_Core_Content_Service::get_latest_news( $limit ) : array();
}

/** @return array{items: array, total: int, total_pages: int, page: int, page_size: int, args: array, categories: array} */
function mavibelge_get_documents( array $raw_args ) {
	if ( ! mavibelge_content_available() ) {
		$empty               = mavibelge_empty_content_page();
		$empty['categories'] = array();
		return $empty;
	}
	return MaviBelge_Core_Content_Service::get_documents( $raw_args );
}

/** @return array[] */
function mavibelge_get_references() {
	return mavibelge_content_available() ? MaviBelge_Core_Content_Service::get_references() : array();
}

/** @return array[] */
function mavibelge_get_locations() {
	return mavibelge_content_available() ? MaviBelge_Core_Content_Service::get_locations() : array();
}

/** @return array{items: array, categories: array, args: array} */
function mavibelge_get_faqs( array $raw_args ) {
	return mavibelge_content_available() ? MaviBelge_Core_Content_Service::get_faqs( $raw_args ) : array( 'items' => array(), 'categories' => array(), 'args' => array() );
}

/**
 * Tekil şablonlar için: geçerli WP_Post'un DTO'su (güvenli dosya/logo/bağlantı doğrulaması servisin içindedir).
 * $kind: 'news' | 'document' | 'reference' | 'location' | 'faq'. Eklenti pasifse / bilinmeyen tür -> boş dizi.
 *
 * @return array
 */
function mavibelge_content_dto( $kind, $post ) {
	if ( ! mavibelge_content_available() || ! ( $post instanceof WP_Post ) ) {
		return array();
	}
	$builders = array(
		'news'      => 'build_news_dto',
		'document'  => 'build_document_dto',
		'reference' => 'build_reference_dto',
		'location'  => 'build_location_dto',
		'faq'       => 'build_faq_dto',
	);
	return isset( $builders[ $kind ] ) ? call_user_func( array( 'MaviBelge_Core_Content_Service', $builders[ $kind ] ), $post ) : array();
}

/** İstekten yalnız içerik sorgusunun tanıdığı üç GET anahtarı (servis normalize eder; burada ham aktarılır). */
function mavibelge_content_request_args() {
	$args = array();
	foreach ( array( 'mb_type', 'mb_cat', 'mb_page' ) as $key ) {
		if ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız okuma/filtre, durum değiştirmez.
			$args[ $key ] = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
	return $args;
}

/**
 * Gerçek sayfa URL'si: yalnız dolu filtreler + mb_page (asla "#").
 *
 * @param string $base_url
 * @param array  $query_args ör. array('mb_type' => 'duyuru')
 * @param int    $page
 */
function mavibelge_content_page_url( $base_url, array $query_args, $page ) {
	$clean = array();
	foreach ( $query_args as $key => $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			$clean[ $key ] = $value;
		}
	}
	if ( (int) $page > 1 ) {
		$clean['mb_page'] = (string) (int) $page;
	}
	return empty( $clean ) ? $base_url : add_query_arg( $clean, $base_url );
}

/**
 * Bilgi Merkezi filtre şeridi (Tümü + seçenekler). Her öğe gerçek bir URL'dir; geçerli olan aria-current taşır.
 *
 * @param string $base_url
 * @param string $param     ör. 'mb_type' | 'mb_cat'
 * @param string $current   geçerli değer ('' = Tümü)
 * @param array  $options   array( array('value' => string, 'label' => string), ... )
 * @return array[] array( array('label','url','current'), ... )
 */
function mavibelge_content_filter_links( $base_url, $param, $current, array $options ) {
	$links   = array();
	$links[] = array( 'label' => __( 'Tümü', 'mavibelge' ), 'url' => $base_url, 'current' => '' === $current );
	foreach ( $options as $option ) {
		$links[] = array(
			'label'   => $option['label'],
			'url'     => add_query_arg( $param, $option['value'], $base_url ),
			'current' => $option['value'] === $current,
		);
	}
	return $links;
}

/**
 * Faz 8 — form DTO'su (sayfa slug'ına göre). Eklenti pasifse veya sayfa bir form sayfası değilse null.
 * Form kapıya takılı (kapalı) ise `open` false döner; kapalı nedenleri yalnız `manage_options` yetkisi olan
 * kullanıcıya gösterilmelidir (bkz. content-form-disabled.php).
 *
 * @return array|null
 */
function mavibelge_form_dto( $page_slug ) {
	if ( ! class_exists( 'MaviBelge_Core_Forms_Service' ) ) {
		return null;
	}
	return MaviBelge_Core_Forms_Service::describe_for_page( $page_slug );
}
