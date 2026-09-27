<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı (qualification-render.sh sonrası). Faz 12f online başvuru
 * `meslek` ön seçimi ve form kapısı testleri için. Gerçek kişi verisi YOKTUR; kurum kararı/alıcı adresi UYDURULMAZ — açık
 * kapı yalnız bu klonda, AÇIKÇA SENTETİK değerlerle (.example alan adı, "TEST" metni) kurulur ve `close` ile geri alınır.
 *
 *   wp eval-file application-fixtures.php prepare   test yeterlilikleri (taslak, pasif, yinelenen kod) + URL üretimi kanıtı
 *   wp eval-file application-fixtures.php open      application formunu SENTETİK ayarlarla aç (yalnız mbfx_)
 *   wp eval-file application-fixtures.php close     form ayarını kapalıya döndür
 */
global $wpdb, $wp_rewrite;
if ( 'mbfx_' !== $wpdb->prefix ) {
	fwrite( STDERR, "HATA: yalnız mbfx_ fixture önekinde çalışır.\n" );
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : '';
$out  = array( 'mode' => $mode );

if ( 'prepare' === $mode ) {
	$mk = function ( $title, $status, $code, $recordStatus ) {
		$id = wp_insert_post( wp_slash( array( 'post_type' => 'mb_yeterlilik', 'post_title' => $title, 'post_status' => 'draft', 'post_content' => '' ) ), true );
		if ( is_wp_error( $id ) ) {
			fwrite( STDERR, "yeterlilik oluşturulamadı\n" );
			exit( 1 );
		}
		update_post_meta( $id, '_mb_myk_code', $code );
		update_post_meta( $id, '_mb_level', '3' );
		update_post_meta( $id, '_mb_record_status', $recordStatus );
		if ( 'publish' === $status ) {
			$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_status' => 'publish', 'post_name' => sanitize_title( $title ) ), array( 'ID' => $id ) );
			clean_post_cache( $id );
		}
		return (int) $id;
	};
	$out['draft']     = $mk( 'TEST Taslak Yeterlilik', 'draft', '99UY9999-3/00', 'active' );
	$out['passive']   = $mk( 'TEST Pasif Yeterlilik', 'publish', '98UY9998-3/00', 'passive' );
	$out['duplicate'] = $mk( 'TEST Kopya Alüminyum Kaynakçısı', 'publish', '11UY0014-3/02', 'active' );
	// URL üretimi iki kalıcı bağlantı yapısında (DB'ye yazılmaz: pre_option filtresi + rewrite yeniden kurulumu).
	$structure = null;
	add_filter(
		'pre_option_permalink_structure',
		function ( $pre ) use ( &$structure ) {
			return null === $structure ? $pre : $structure;
		}
	);
	foreach ( array( 'pathinfo' => '/index.php/%postname%/', 'clean' => '/%postname%/' ) as $k => $s ) {
		$structure = $s;
		$wp_rewrite->init();
		$out[ 'url_' . $k ]        = mavibelge_application_url( '11UY0011-3/03' );
		$out[ 'url_plain_' . $k ]  = mavibelge_application_url( '' );
	}
	$structure = null;
	$wp_rewrite->init();
	$out['home'] = untrailingslashit( home_url() );
} elseif ( 'open' === $mode ) {
	$synthetic = array(
		'enabled'                   => '1',
		'recipient_email'           => 'kurum@ornek.example',
		'consent_approved'          => '1',
		'consent_text'              => 'TEST: sentetik onay metni (kurum kararı DEĞİL).',
		'consent_version'           => 'test-1',
		'sensitive_fields_approved' => '1',
		'uploads_approved'          => '1',
	);
	MaviBelge_Core_Forms_Service::save_config(
		array(
			'forms'      => array( 'application' => $synthetic ),
			'rate_limit' => array( 'per_client' => 50, 'window' => 600, 'global' => 500, 'global_window' => 3600 ),
		)
	);
	$out['open'] = MaviBelge_Core_Forms_Service::describe( 'application' )['open'];
} elseif ( 'close' === $mode ) {
	MaviBelge_Core_Forms_Service::save_config( array( 'forms' => array() ) );
	$out['open'] = MaviBelge_Core_Forms_Service::describe( 'application' )['open'];
} else {
	fwrite( STDERR, "kip: prepare|open|close\n" );
	exit( 1 );
}
echo wp_json_encode( $out ), "\n";
