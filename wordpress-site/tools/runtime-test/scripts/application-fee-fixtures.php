<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı (application-fees.sh; önce qualification-render-fixtures.php
 * gerçek 14/83/103 kataloğu aktarmış olmalı). Faz 16 online başvuru meslek seçenekleri (ücret kayıtlarından) için.
 * Gerçek kişi verisi YOKTUR; form kapısı yalnız bu klonda AÇIKÇA SENTETİK ayarlarla açılır ve `close` ile kapatılır.
 *
 *   setup                         103 ücreti yayında + aktif yapar (canlıdaki kullanıcı yayını gibi), application formunu sentetik açar
 *   set <id> <alan> <değer>       post_status | _mb_record_status | _mb_valid_from | _mb_valid_until | _mb_tariff_period
 *                                 (WordPress API'leriyle: önbellek nesli GERÇEK kancalarla artar)
 *   trash <id> | untrash <id>
 *   period <değer> | period-reset aktif tarife dönemi seçeneği
 *   dates <saat-dilimi>          o dilimde bugün/dün/yarın (Y-m-d)
 *   bulk-add <n> | bulk-clean     <n> sentetik, ziyaretçiye açık ücret (TEST Toplu Meslek ...) ekler / siler
 *   gen                           önbellek nesli
 *   close                         form ayarını kapalıya döndür
 */
global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	fwrite( STDERR, "HATA: yalnız mbfx_ fixture önekinde çalışır.\n" );
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : '';
$out  = array( 'mode' => $mode );
$fail = function ( $m ) {
	fwrite( STDERR, $m . "\n" );
	exit( 1 );
};
$fee_ids = function () {
	return array_map( 'intval', get_posts( array( 'post_type' => 'mb_ucret', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ) );
};
$publish = function ( $id ) {
	$r = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $r ) || 'publish' !== get_post_status( $id ) ) {
		// Yayın hazırlık kapısı WP-CLI bağlamında farklı davranırsa: doğrudan durum + gerçek önbellek kancası.
		$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		do_action( 'save_post', $id, get_post( $id ), true );
	}
};

if ( 'setup' === $mode ) {
	$ids = $fee_ids();
	foreach ( $ids as $id ) {
		update_post_meta( $id, '_mb_record_status', 'active' );
		$publish( $id );
	}
	MaviBelge_Core_Forms_Service::save_config(
		array(
			'forms'      => array(
				'application' => array(
					'enabled'                   => '1',
					'recipient_email'           => 'kurum@ornek.example',
					'consent_approved'          => '1',
					'consent_text'              => 'TEST: sentetik onay metni (kurum kararı DEĞİL).',
					'consent_version'           => 'test-16',
					'sensitive_fields_approved' => '1',
					'uploads_approved'          => '1',
				),
			),
			'rate_limit' => array( 'per_client' => 200, 'window' => 600, 'global' => 2000, 'global_window' => 3600 ),
		)
	);
	MaviBelge_Core_Forms_Service::reset();
	$out['fees']    = array();
	foreach ( $ids as $id ) {
		$out['fees'][ (string) get_post_meta( $id, '_mb_import_source_key', true ) ] = $id;
	}
	$out['visible'] = MaviBelge_Core_Catalog_Service::get_active_fee_count();
	$out['open']    = MaviBelge_Core_Forms_Service::describe( 'application' )['open'];
	$out['period']  = MaviBelge_Core_Catalog_Service::get_active_tariff_period();
	$out['today']   = current_time( 'Y-m-d' );
	$mv             = $out['fees']['fee:is-makineleri:3:mobil-vinc-operatoru-mhc-sahil-ve-gemi-vinci'];
	$qid            = (int) get_post_meta( $mv, '_mb_qualification_id', true );
	$out['mobil_qual']     = array( 'id' => $qid, 'url' => get_permalink( $qid ), 'title' => get_the_title( $qid ), 'code' => (string) get_post_meta( $qid, '_mb_myk_code', true ) );
	$page                  = get_page_by_path( 'online-basvuru', OBJECT, 'page' );
	$out['page_id']        = $page instanceof WP_Post ? (int) $page->ID : 0;
	$fee_page              = get_page_by_path( 'sinav-ucretleri', OBJECT, 'page' );
	$out['fee_page']       = $fee_page instanceof WP_Post ? get_permalink( $fee_page ) : '';
	$out['application']    = $page instanceof WP_Post ? get_permalink( $page ) : '';
	$out['qual_any']       = (int) get_posts( array( 'post_type' => 'mb_yeterlilik', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) )[0];
	$out['gen']            = MaviBelge_Core_Cache::generation();
} elseif ( 'set' === $mode ) {
	$id    = isset( $args[1] ) ? (int) $args[1] : 0;
	$field = isset( $args[2] ) ? $args[2] : '';
	$value = isset( $args[3] ) ? $args[3] : '';
	if ( 'mb_ucret' !== get_post_type( $id ) ) {
		$fail( 'ücret değil: ' . $id );
	}
	if ( 'post_status' === $field ) {
		if ( 'publish' === $value ) {
			$publish( $id );
		} else {
			wp_update_post( array( 'ID' => $id, 'post_status' => $value ) );
		}
	} elseif ( in_array( $field, array( '_mb_record_status', '_mb_valid_from', '_mb_valid_until', '_mb_tariff_period' ), true ) ) {
		update_post_meta( $id, $field, $value );
	} else {
		$fail( 'alan yok: ' . $field );
	}
	$out['state'] = array( 'post_status' => get_post_status( $id ), 'record_status' => get_post_meta( $id, '_mb_record_status', true ), 'from' => get_post_meta( $id, '_mb_valid_from', true ), 'until' => get_post_meta( $id, '_mb_valid_until', true ), 'period' => get_post_meta( $id, '_mb_tariff_period', true ) );
	$out['gen']   = MaviBelge_Core_Cache::generation();
} elseif ( 'trash' === $mode || 'untrash' === $mode ) {
	$id = isset( $args[1] ) ? (int) $args[1] : 0;
	if ( 'mb_ucret' !== get_post_type( $id ) ) {
		$fail( 'ücret değil: ' . $id );
	}
	if ( 'trash' === $mode ) {
		wp_trash_post( $id );
	} else {
		wp_untrash_post( $id );
		$publish( $id ); // WordPress 5.6+: çöpten dönen yazı taslak olur
	}
	$out['state'] = get_post_status( $id );
	$out['gen']   = MaviBelge_Core_Cache::generation();
} elseif ( 'period' === $mode ) {
	update_option( 'mb_active_tariff_period', isset( $args[1] ) ? $args[1] : '' );
	$out['period'] = get_option( 'mb_active_tariff_period' );
	$out['gen']    = MaviBelge_Core_Cache::generation();
} elseif ( 'dates' === $mode ) {
	$tz  = new DateTimeZone( isset( $args[1] ) ? $args[1] : 'UTC' );
	$now = new DateTime( 'now', $tz );
	$out['today']     = $now->format( 'Y-m-d' );
	$out['yesterday'] = ( clone $now )->modify( '-1 day' )->format( 'Y-m-d' );
	$out['tomorrow']  = ( clone $now )->modify( '+1 day' )->format( 'Y-m-d' );
} elseif ( 'bulk-add' === $mode ) {
	$n    = isset( $args[1] ) ? (int) $args[1] : 0;
	$tpl  = (int) get_posts( array( 'post_type' => 'mb_ucret', 'post_status' => 'publish', 'meta_key' => '_mb_import_source_key', 'meta_value' => 'fee:makine:3:makine-bakimci', 'numberposts' => 1, 'fields' => 'ids' ) )[0];
	$meta = array();
	foreach ( get_post_meta( $tpl ) as $key => $values ) {
		if ( 0 === strpos( $key, '_mb_' ) && ! in_array( $key, array( '_mb_import_source_key', '_mb_last_applied_hash', '_mb_import_run_id', '_mb_fee_publication_marker' ), true ) ) {
			$meta[ $key ] = maybe_unserialize( $values[0] );
		}
	}
	$made = array();
	// Bellek: uzun WP-CLI sürecinde nesne önbelleği büyümesin; kayıt başına önbellek nesli artışı yerine sonda tek artış.
	wp_suspend_cache_addition( true );
	remove_action( 'added_post_meta', array( 'MaviBelge_Core_Cache', 'on_meta_change' ), 10 );
	remove_action( 'updated_post_meta', array( 'MaviBelge_Core_Cache', 'on_meta_change' ), 10 );
	remove_action( 'save_post', array( 'MaviBelge_Core_Cache', 'on_post_change' ), 10 );
	for ( $i = 1; $i <= $n; $i++ ) {
		if ( 0 === $i % 50 ) {
			wp_cache_flush();
		}
		$name = sprintf( 'TEST Toplu Meslek %03d', $i );
		$id   = wp_insert_post( wp_slash( array( 'post_type' => 'mb_ucret', 'post_title' => $name, 'post_status' => 'draft' ) ), true );
		if ( is_wp_error( $id ) ) {
			$fail( 'toplu ücret oluşturulamadı' );
		}
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		update_post_meta( $id, '_mb_profession_name', $name );
		update_post_meta( $id, '_mb_qualification_id', 0 );
		update_post_meta( $id, '_mb_qualification_code', '' );
		update_post_meta( $id, 'mbfx_bulk_test', 'faz16' );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$made[] = (int) $id;
	}
	MaviBelge_Core_Cache::bump();
	$out['made']    = count( $made );
	$out['visible'] = MaviBelge_Core_Catalog_Service::get_active_fee_count();
} elseif ( 'bulk-clean' === $mode ) {
	$ids = get_posts( array( 'post_type' => 'mb_ucret', 'post_status' => 'any', 'meta_key' => 'mbfx_bulk_test', 'meta_value' => 'faz16', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	$out['deleted'] = count( $ids );
	$out['visible'] = MaviBelge_Core_Catalog_Service::get_active_fee_count();
} elseif ( 'get' === $mode ) {
	$id           = isset( $args[1] ) ? (int) $args[1] : 0;
	$out['state'] = array( 'post_status' => get_post_status( $id ), 'record_status' => get_post_meta( $id, '_mb_record_status', true ), 'from' => get_post_meta( $id, '_mb_valid_from', true ), 'until' => get_post_meta( $id, '_mb_valid_until', true ), 'period' => get_post_meta( $id, '_mb_tariff_period', true ) );
	$out['gen']   = MaviBelge_Core_Cache::generation();
} elseif ( 'raw-status' === $mode ) {
	// Önbelleği BİLEREK bayat bırakır: kanca çalıştırmadan durum değişir (nesil artmaz). Gönderimdeki önbelleksiz yeniden
	// doğrulamanın, bayat listeye rağmen kaydı reddettiğini sınamak içindir. Sonra `bump` ile önbellek yenilenir.
	$id = isset( $args[1] ) ? (int) $args[1] : 0;
	if ( 'mb_ucret' !== get_post_type( $id ) ) {
		$fail( 'ücret değil: ' . $id );
	}
	$wpdb->update( $wpdb->posts, array( 'post_status' => isset( $args[2] ) ? $args[2] : 'draft' ), array( 'ID' => $id ) );
	clean_post_cache( $id );
	$out['state'] = get_post_status( $id );
	$out['gen']   = MaviBelge_Core_Cache::generation();
} elseif ( 'bump' === $mode ) {
	MaviBelge_Core_Cache::bump();
	$out['gen'] = MaviBelge_Core_Cache::generation();
} elseif ( 'gen' === $mode ) {
	$out['gen'] = MaviBelge_Core_Cache::generation();
} elseif ( 'close' === $mode ) {
	MaviBelge_Core_Forms_Service::save_config( array( 'forms' => array() ) );
	$out['open'] = MaviBelge_Core_Forms_Service::describe( 'application' )['open'];
} else {
	$fail( 'kip: setup|set|trash|untrash|period|dates|bulk-add|bulk-clean|gen|close' );
}
echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
