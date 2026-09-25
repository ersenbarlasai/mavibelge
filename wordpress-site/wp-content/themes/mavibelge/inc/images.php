<?php
/**
 * Faz 10 — yerel (tema içi) görseller için güvenli öznitelikler. Boyutlar GERÇEK dosyadan (getimagesize) okunur; dosya
 * yoksa/okunamıyorsa boyut özniteliği YAZILMAZ (uydurma boyut yok). Yükleme kipi çağıranın belirttiği değerle sınırlıdır:
 * ekran üstü (header) görseller `eager` + `fetchpriority=high`, fold altı görseller `lazy`; hepsi `decoding=async`.
 * WordPress ek görselleri (wp_get_attachment_image) width/height/lazy'yi zaten kendisi üretir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $relative Tema köküne göre yol (ör. 'assets/images/logos/header-logo.png')
 * @return array{width:int,height:int}|null
 */
function mavibelge_local_image_size( $relative ) {
	static $memo = array();
	if ( array_key_exists( $relative, $memo ) ) {
		return $memo[ $relative ];
	}
	$memo[ $relative ] = null;
	$path              = get_theme_file_path( $relative );
	if ( '' !== $relative && is_string( $path ) && is_file( $path ) && is_readable( $path ) ) {
		$info = getimagesize( $path );
		if ( is_array( $info ) && $info[0] > 0 && $info[1] > 0 ) {
			$memo[ $relative ] = array( 'width' => (int) $info[0], 'height' => (int) $info[1] );
		}
	}
	return $memo[ $relative ];
}

/**
 * Yerel görsel için öznitelik dizgesi (width/height/decoding/loading[/fetchpriority]); kaçışlıdır.
 *
 * @param string $relative Tema köküne göre yol
 * @param string $mode     'eager' (ekran üstü, yüksek öncelik) | 'above' (ekran üstü, öncelik yok) | 'lazy' (fold altı)
 * @param int    $displayHeight >0 ise CSS'in sabit yüksekliği: width/height bu yüksekliğe ORANTILI ölçeklenir (en-boy oranı gerçek dosyadan)
 */
function mavibelge_local_image_attrs( $relative, $mode = 'lazy', $displayHeight = 0 ) {
	$attrs = array();
	$size  = mavibelge_local_image_size( $relative );
	if ( null !== $size ) {
		$w = (int) $size['width'];
		$h = (int) $size['height'];
		if ( $displayHeight > 0 ) {
			$w = max( 1, (int) round( $w * $displayHeight / $h ) );
			$h = (int) $displayHeight;
		}
		$attrs[] = 'width="' . $w . '"';
		$attrs[] = 'height="' . $h . '"';
	}
	$attrs[] = 'decoding="async"';
	if ( 'eager' === $mode ) {
		$attrs[] = 'fetchpriority="high"';
	} elseif ( 'above' === $mode ) {
		// ekran üstü ama öncelik vermeyen görsel: lazy YOK, fetchpriority YOK (varsayılan yükleme).
	} else {
		$attrs[] = 'loading="lazy"';
	}
	return implode( ' ', $attrs );
}
