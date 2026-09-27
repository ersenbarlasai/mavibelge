<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı (qualification-render.sh ortamı). Faz 12g İletişim sayfası ve
 * beş formun güvenlik matrisi için. Gerçek kişi verisi, gerçek alıcı adresi ve kurum kararı YOKTUR: form ayarları yalnız bu
 * klonda, AÇIKÇA SENTETİK değerlerle (.example, "TEST") kurulur; `config` boş dizi ile kapatılır.
 *
 *   wp eval-file contact-fixtures.php locations [clear]  TEST mb_lokasyon kayıtları (aktif/taslak/pasif/güvensiz harita); clear: yalnız kaldır
 *   wp eval-file contact-fixtures.php config <base64>    MaviBelge_Core_Forms_Service::save_config( json )
 *   wp eval-file contact-fixtures.php describe           beş formun kapı sonucu (open + nedenler)
 *   wp eval-file contact-fixtures.php audit-scan         form audit satırlarında sentetik kişisel veri izi sayısı
 */
global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	fwrite( STDERR, "HATA: yalnız mbfx_ fixture önekinde çalışır.\n" );
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : '';
$out  = array( 'mode' => $mode );

if ( 'locations' === $mode ) {
	// Tekrar çalıştırılabilir: aynı klonda önceki TEST lokasyonları önce kaldırılır (yalnız mbfx_ klonu).
	foreach ( get_posts( array( 'post_type' => 'mb_lokasyon', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $old ) {
		if ( 0 === strpos( get_the_title( $old ), 'TEST Lokasyon' ) ) {
			wp_delete_post( $old, true );
		}
	}
	if ( isset( $args[1] ) && 'clear' === $args[1] ) {
		echo wp_json_encode( array( 'mode' => 'locations-clear' ) ), "\n";
		return;
	}
	$mk = function ( $title, $status, array $meta ) use ( $wpdb ) {
		$id = wp_insert_post( wp_slash( array( 'post_type' => 'mb_lokasyon', 'post_title' => $title, 'post_status' => 'draft' ) ), true );
		if ( is_wp_error( $id ) ) {
			fwrite( STDERR, "lokasyon oluşturulamadı\n" );
			exit( 1 );
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		if ( 'publish' === $status ) {
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish', 'post_name' => sanitize_title( $title ) ), array( 'ID' => $id ) );
			clean_post_cache( $id );
		}
		return (int) $id;
	};
	$out['loc1'] = $mk( 'TEST Lokasyon Bir', 'publish', array( '_mb_address' => 'TEST Adres Bir, Uzun Bir Sokak Adı Numara 12 Kat 3 Daire 45 TEST / İL', '_mb_phone_numbers' => array( '0000 000 00 01', '0000 000 00 02' ), '_mb_map_url' => 'https://harita.example/bir', '_mb_working_hours' => 'TEST 09:00-18:00', '_mb_sort_order' => 1, '_mb_record_status' => 'active' ) );
	$out['loc2'] = $mk( 'TEST Lokasyon İki', 'publish', array( '_mb_address' => 'TEST Adres İki', '_mb_phone_numbers' => array( '0000 000 00 03' ), '_mb_map_url' => 'javascript:alert(2)', '_mb_sort_order' => 2, '_mb_record_status' => 'active' ) );
	$out['loc3'] = $mk( 'TEST Lokasyon Üç', 'publish', array( '_mb_address' => 'TEST Adres Üç', '_mb_map_url' => 'http://harita.example/guvensiz', '_mb_sort_order' => 3, '_mb_record_status' => 'active' ) );
	$out['loc4'] = $mk( 'TEST Lokasyon Dört', 'publish', array( '_mb_address' => 'TEST Adres Dört', '_mb_sort_order' => 4, '_mb_record_status' => 'active' ) );
	$out['draft'] = $mk( 'TEST Lokasyon Taslak', 'draft', array( '_mb_address' => 'TEST Taslak Adres', '_mb_sort_order' => 0, '_mb_record_status' => 'active' ) );
	$out['passive'] = $mk( 'TEST Lokasyon Pasif', 'publish', array( '_mb_address' => 'TEST Gizli Adres', '_mb_sort_order' => 0, '_mb_record_status' => 'passive' ) );
} elseif ( 'config' === $mode ) {
	$json = isset( $args[1] ) ? base64_decode( $args[1], true ) : false;
	$cfg  = false === $json ? null : json_decode( $json, true );
	if ( ! is_array( $cfg ) ) {
		fwrite( STDERR, "config: geçersiz json\n" );
		exit( 1 );
	}
	MaviBelge_Core_Forms_Service::save_config( $cfg );
	$out['saved'] = true;
} elseif ( 'describe' === $mode ) {
	foreach ( MaviBelge_Core_Forms_Schema::FORM_IDS as $id ) {
		$d           = MaviBelge_Core_Forms_Service::describe( $id );
		$out[ $id ] = array( 'open' => $d['open'], 'reasons' => $d['reasons'], 'page' => MaviBelge_Core_Forms_Schema::get( $id )['page_slug'] );
	}
} elseif ( 'audit-scan' === $mode ) {
	$rows = $wpdb->get_col( "SELECT context FROM {$wpdb->prefix}mb_audit_log WHERE event_type IN ('form_submitted','form_rejected')" );
	$hits = 0;
	foreach ( $rows as $ctx ) {
		foreach ( array( 'Sentetik', 'ornek.example', '10000000146', '0000 000 00', 'TEST mesaj', 'sentetik-cv' ) as $marker ) {
			if ( false !== strpos( (string) $ctx, $marker ) ) {
				$hits++;
			}
		}
	}
	$out['rows'] = count( $rows );
	$out['pii_hits'] = $hits;
} else {
	fwrite( STDERR, "kip: locations|config|describe|audit-scan\n" );
	exit( 1 );
}
echo wp_json_encode( $out ), "\n";
