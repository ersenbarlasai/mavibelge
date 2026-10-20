<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı. Faz 15 ücret kataloğu runtime testi.
 *
 * Önkoşul: qualification-render-fixtures.php bu klonda çalışmış olmalı (gerçek 14/83/103 katalog içe aktarıldı; canlı ile aynı
 * başlangıç: YALNIZ iki QA ücreti elle aktif/yayında, 101 ücret taslak; aktif dönem 2026).
 *
 * Kullanım: wp eval-file fee-catalog-fixtures.php <aşama>
 *   publish   — ücret yayın önizlemesi -> mevcut admin servisiyle (gerçek Fee_Publisher, gerçek transaction/audit) toplu
 *               etkinleştirme -> tekrar önizleme + `all` aşaması önizlemesi; test kullanıcıları ve oturum çerezleri; JSON çıktı.
 *   after     — admin düzenlemelerinden SONRA: `all` önizlemesi (elle değişen kayıtlar conflict), yayın önizlemesi, aracın
 *               etkinleştirdiklerini geri alma, eski (0.5.4) hash geçişi; JSON çıktı.
 * Ana `wp_` tablolarına dokunulmaz; parola okunmaz/yazdırılmaz (çerezler 0600 /tmp dosyalarına yazılır, stdout'a basılmaz).
 */
global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	fwrite( STDERR, "HATA: yalnız mbfx_ fixture önekinde çalışır.\n" );
	exit( 1 );
}
$phase = isset( $args[0] ) ? (string) $args[0] : '';
$dir   = ABSPATH . 'data/content';
$fail  = function ( $m ) {
	fwrite( STDERR, $m . "\n" );
	exit( 1 );
};
$svc = function () use ( $dir ) {
	return new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => $dir ) ) );
};
$feeIds = function () {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'mb_ucret', 'post_status' => array( 'draft', 'publish', 'pending', 'private' ), 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		$out[ (string) get_post_meta( $id, '_mb_import_source_key', true ) ] = (int) $id;
	}
	ksort( $out );
	return $out;
};
$stateOf = function ( $id ) {
	return array( 'post_status' => get_post_status( $id ), 'record_status' => (string) get_post_meta( $id, '_mb_record_status', true ), 'marker' => '' !== (string) get_post_meta( $id, '_mb_fee_publication_marker', true ) );
};
$allOps = function () use ( $svc ) {
	$p   = $svc()->preview_stage( 'all' );
	$fee = array( 'create' => 0, 'unchanged' => 0, 'update' => 0, 'conflict' => 0, 'other' => 0 );
	$conflicts = array();
	foreach ( $p['entries'] as $e ) {
		if ( 'fee' !== $e['type'] ) {
			continue;
		}
		$k = isset( $fee[ $e['decision'] ] ) ? $e['decision'] : 'other';
		$fee[ $k ]++;
		if ( 'conflict' === $e['decision'] ) {
			$conflicts[ $e['source_key'] ] = $e['reason'];
		}
	}
	return array( 'eligible' => $p['eligible'], 'summary_ops' => $p['summary']['operations'], 'fee' => $fee, 'fee_conflicts' => $conflicts );
};
$reasons = function ( array $pv ) {
	$out = array();
	foreach ( $pv['rows'] as $r ) {
		$out[ $r['source_key'] ] = $r['reason'];
	}
	return $out;
};
$loop = function ( $mode, array $pv ) use ( $svc ) {
	$remaining = 'activate' === $mode ? (int) $pv['summary']['ready'] : (int) $pv['summary']['deactivatable'];
	$phrase    = 'activate' === $mode ? $pv['activate_phrase'] : $pv['deactivate_phrase'];
	$requests  = array();
	for ( $i = 0; $i < 20 && $remaining > 0; $i++ ) {
		$r = 'activate' === $mode ? $svc()->activate_fees( $pv['plan_digest'], $phrase, $remaining ) : $svc()->deactivate_fees( $pv['plan_digest'], $phrase, $remaining );
		$requests[] = array( 'ok' => $r['ok'], 'status' => $r['status'], 'error' => $r['error_code'], 'processed' => $r['processed'], 'remaining' => $r['remaining'] );
		if ( empty( $r['ok'] ) ) {
			break;
		}
		$remaining = (int) $r['remaining'];
	}
	return $requests;
};
// Ücret kayıtları + meta + audit + run tabloları imzası (eksik dosya senaryosunda "hiçbir yazma yok" kanıtı).
$dbSig = function () use ( $wpdb ) {
	$tables = array( $wpdb->posts, $wpdb->postmeta, MaviBelge_Core_Audit_Log::table_name(), MaviBelge_Core_Import_Wpdb_Run_Store::runs_table(), MaviBelge_Core_Import_Wpdb_Run_Store::items_table() );
	$out    = array();
	foreach ( $tables as $t ) {
		$row   = $wpdb->get_row( 'CHECKSUM TABLE `' . $t . '`', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out[] = is_array( $row ) && isset( $row['Checksum'] ) ? (string) $row['Checksum'] : 'yok';
	}
	return implode( ',', $out );
};
// Gerçek manifest dizininin, bir dosyası eksik geçici kopyası (YALNIZ /tmp/mbfx-feemiss-*; gerçek data/ dizinine dokunulmaz).
$missingCase = function ( $label, $omit ) use ( $dir, $dbSig ) {
	$tmp   = '/tmp/mbfx-feemiss-' . $label;
	$clean = function () use ( $tmp ) {
		if ( is_dir( $tmp ) ) {
			array_map( 'unlink', glob( $tmp . '/*' ) );
			rmdir( $tmp );
		}
	};
	$clean();
	if ( null !== $omit ) {
		mkdir( $tmp, 0700 );
		foreach ( glob( $dir . '/*.json' ) as $f ) {
			if ( basename( $f ) !== $omit ) {
				copy( $f, $tmp . '/' . basename( $f ) );
			}
		}
	}
	$before  = $dbSig();
	$service = new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => $tmp ) ) );
	$pv      = $service->preview_fee_publish();
	$digest  = str_repeat( 'a', 64 );
	$act     = $service->activate_fees( $digest, MaviBelge_Core_Import_Admin_Gates::fee_publish_phrase( 'activate', $digest ), 0 );
	$deact   = $service->deactivate_fees( $digest, MaviBelge_Core_Import_Admin_Gates::fee_publish_phrase( 'deactivate', $digest ), 0 );
	$after   = $dbSig();
	$clean();
	return array(
		'preview_ok'    => $pv['ok'],
		'preview_error' => $pv['error_code'],
		'load_errors'   => $pv['load_errors'],
		'required'      => $pv['required_files'],
		'activate'      => $act['error_code'],
		'deactivate'    => $deact['error_code'],
		'path_leak'     => false !== strpos( wp_json_encode( $pv ), $tmp ),
		'db_unchanged'  => $before === $after,
	);
};
$auditCount = function ( $event ) use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . MaviBelge_Core_Audit_Log::table_name() . '` WHERE event_type = %s', $event ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
};

if ( 'publish' === $phase ) {
	$out             = array();
	$ids             = $feeIds();
	$out['fees']     = count( $ids );
	$out['before']   = array( 'active_visible' => (int) MaviBelge_Core_Catalog_Service::get_active_fee_count() );
	$out['all_pre']  = $allOps();
	// Dağıtım bağımlılığı: gerekli dosya eksikse anlaşılır hata, gerçek DB'de HİÇBİR yazma yok.
	$out['missing']  = array(
		'fees'      => $missingCase( 'fees', 'fees.manifest.json' ),
		'decisions' => $missingCase( 'decisions', 'fee-publication.manifest.json' ),
		'no_dir'    => $missingCase( 'nodir', null ),
	);
	$pv              = $svc()->preview_fee_publish();
	// Dağıtım paketi (fee-catalog.sh /tmp/mbfx-feepkg'e açar): paketin content/ dizininden alınan önizleme gerçek data/content ile AYNI olmalı.
	$pkgDir = '/tmp/mbfx-feepkg/content';
	if ( is_dir( $pkgDir ) ) {
		$pkgPv = ( new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => $pkgDir ) ) ) )->preview_fee_publish();
		$listed = scandir( '/tmp/mbfx-feepkg' );
		$out['package'] = array(
			'ok'            => $pkgPv['ok'],
			'same_digest'   => $pkgPv['plan_digest'] === $pv['plan_digest'],
			'same_summary'  => $pkgPv['summary'] === $pv['summary'],
			'content_files' => array_values( array_diff( scandir( $pkgDir ), array( '.', '..' ) ) ),
			'root_files'    => array_values( array_diff( $listed, array( '.', '..' ) ) ),
		);
	} else {
		$out['package'] = null;
	}
	if ( empty( $pv['ok'] ) ) {
		$fail( 'yayın önizlemesi başarısız: ' . (string) $pv['error_code'] );
	}
	$out['preview']  = array( 'summary' => $pv['summary'], 'reasons' => $reasons( $pv ), 'digest' => $pv['plan_digest'], 'activate_phrase' => $pv['activate_phrase'], 'inventory' => $pv['inventory'] );
	// Yanlış onay ifadesi ve bayat istek sunucuda reddedilir (yazma yok).
	$out['wrong_phrase'] = $svc()->activate_fees( $pv['plan_digest'], 'UCRET YAYINLA 000000000000', (int) $pv['summary']['ready'] );
	$out['stale']        = $svc()->activate_fees( $pv['plan_digest'], $pv['activate_phrase'], (int) $pv['summary']['ready'] + 1 );
	$out['after_rejects_visible'] = (int) MaviBelge_Core_Catalog_Service::get_active_fee_count();
	$out['requests']     = $loop( 'activate', $pv );
	$pv2                 = $svc()->preview_fee_publish();
	$out['preview_after'] = array( 'summary' => $pv2['summary'], 'reasons' => $reasons( $pv2 ), 'digest' => $pv2['plan_digest'], 'inventory' => $pv2['inventory'] );
	$out['all_post']     = $allOps();
	$out['audit_activated'] = $auditCount( 'import_fees_activated' );
	$out['active_visible']  = (int) MaviBelge_Core_Catalog_Service::get_active_fee_count();
	$states = array();
	foreach ( $ids as $key => $id ) {
		$states[ $key ] = $stateOf( $id );
	}
	$out['states']  = $states;
	$out['fee_ids'] = $ids;
	$quals = array();
	foreach ( $ids as $key => $id ) {
		$qid = (int) get_post_meta( $id, '_mb_qualification_id', true );
		if ( $qid > 0 ) {
			$quals[ $key ] = array( 'id' => $qid, 'url' => get_permalink( $qid ) );
		}
	}
	$out['qual_of_fee'] = $quals;
	$page               = get_page_by_path( 'sinav-ucretleri', OBJECT, 'page' );
	$out['fee_page']    = $page instanceof WP_Post ? get_permalink( $page ) : '';
	$out['fee_page_status'] = $page instanceof WP_Post ? $page->post_status : '';

	// Test kullanıcıları (yalnız klon): fiyat editörü; çerezler 0600 /tmp dosyalarına, değerler stdout'a BASILMAZ.
	if ( ! get_user_by( 'login', 'mbpriceed' ) ) {
		$uid = wp_insert_user( array( 'user_login' => 'mbpriceed', 'user_email' => 'priceeditor@example.invalid', 'user_pass' => wp_generate_password( 32 ), 'role' => 'mb_price_editor' ) );
		if ( is_wp_error( $uid ) ) {
			$fail( 'fiyat editörü oluşturulamadı' );
		}
	}
	foreach ( array( 'mbadmin', 'mbpriceed', 'mbsubscriber' ) as $login ) {
		$user  = get_user_by( 'login', $login );
		$exp   = time() + 4 * HOUR_IN_SECONDS;
		$token = WP_Session_Tokens::get_instance( $user->ID )->create( $exp );
		$jar   = array(
			'wordpress_test_cookie' => rawurlencode( 'WP Cookie check' ),
			AUTH_COOKIE             => rawurlencode( wp_generate_auth_cookie( $user->ID, $exp, 'auth', $token ) ),
			LOGGED_IN_COOKIE        => rawurlencode( wp_generate_auth_cookie( $user->ID, $exp, 'logged_in', $token ) ),
		);
		$file = "/tmp/mbfx-fee-cookies-{$login}.json";
		file_put_contents( $file, wp_json_encode( $jar ) );
		chmod( $file, 0600 );
		$out['users'][ $login ] = implode( ',', $user->roles );
	}
	// Fiyat editörünün KENDİ taslak ücreti (yetki testi: aktif yapamaz, aktif dönemi değiştiremez).
	$own = get_posts( array( 'post_type' => 'mb_ucret', 'post_status' => 'draft', 'author' => get_user_by( 'login', 'mbpriceed' )->ID, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( array() === $own ) {
		$own = array( wp_insert_post( array( 'post_type' => 'mb_ucret', 'post_status' => 'draft', 'post_title' => 'TEST Fiyat Editörü Taslağı', 'post_author' => get_user_by( 'login', 'mbpriceed' )->ID ) ) );
		update_post_meta( $own[0], '_mb_record_status', 'draft' );
	}
	$out['price_editor_own'] = (int) $own[0];
	echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
	exit( 0 );
}

if ( 'after' === $phase ) {
	$out           = array();
	$ids           = $feeIds();
	$out['fees']   = count( $ids );
	$out['all']    = $allOps();
	$pv            = $svc()->preview_fee_publish();
	$out['preview'] = array( 'summary' => $pv['summary'], 'reasons' => $reasons( $pv ), 'inventory' => $pv['inventory'] );
	$out['deactivate'] = $loop( 'deactivate', $pv );
	$pv2           = $svc()->preview_fee_publish();
	$out['preview_after_deactivate'] = array( 'summary' => $pv2['summary'], 'reasons' => $reasons( $pv2 ) );
	$out['audit_deactivated'] = $auditCount( 'import_fees_deactivated' );
	$out['active_visible']    = (int) MaviBelge_Core_Catalog_Service::get_active_fee_count();
	$states = array();
	foreach ( $ids as $key => $id ) {
		$states[ $key ] = $stateOf( $id );
	}
	$out['states'] = $states;

	// Eski (0.5.4) hash geçişi: taslak bir kaydın hash'i eski biçimle (record_status=draft dahil) yazılır -> unchanged kalmalı.
	$legacyKey = 'fee:tekstil:3:dokuma-operatoru';
	$lid       = $ids[ $legacyKey ];
	$repo      = new MaviBelge_Core_Import_WordPress_Target_Repository();
	$lookup    = $repo->find_target_by_source_key( 'fee', $legacyKey );
	update_post_meta( $lid, '_mb_last_applied_hash', MaviBelge_Core_Import_Hash::hash( MaviBelge_Core_Import_Managed_Fields::legacy_fee_hash_input( $lookup['current_managed_fields'] ) ) );
	$all2 = $svc()->preview_stage( 'all' );
	foreach ( $all2['entries'] as $e ) {
		if ( $legacyKey === $e['source_key'] ) {
			$out['legacy_decision'] = $e['decision'];
		}
	}
	echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
	exit( 0 );
}

if ( 'whatif' === $phase ) {
	// VARSAYIM TESTİ (yalnız taze mbfx_ klonu): bekletilen 6 kayıt için kurum "yayınla" derse ne görünür? Gerçek karar dosyası
	// DEĞİŞTİRİLMEZ; /tmp/mbfx-whatif altına kopya yazılır, yalnız `decision`/`hold_reasons` değişir (kayıt özetleri aynı kalır).
	$tmp = '/tmp/mbfx-whatif';
	if ( is_dir( $tmp ) ) {
		array_map( 'unlink', glob( $tmp . '/*' ) );
		rmdir( $tmp );
	}
	mkdir( $tmp, 0700 );
	foreach ( glob( $dir . '/*.json' ) as $f ) {
		copy( $f, $tmp . '/' . basename( $f ) );
	}
	$doc          = json_decode( (string) file_get_contents( $dir . '/fee-publication.manifest.json' ), true );
	$doc['basis'] = 'VARSAYIM TESTİ (fixture): bekletilen kayıtlar yayınlanırsa görünüm. ' . $doc['basis'];
	$flipped      = array();
	foreach ( $doc['records'] as $i => $r ) {
		if ( 'hold' === $r['decision'] ) {
			$flipped[]                            = $r['source_key'];
			$doc['records'][ $i ]['decision']     = 'publish';
			$doc['records'][ $i ]['hold_reasons'] = array();
		}
	}
	file_put_contents( $tmp . '/fee-publication.manifest.json', wp_json_encode( $doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$wsvc = function () use ( $tmp ) {
		return new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => $tmp ) ) );
	};
	$out            = array( 'flipped' => $flipped );
	$pv             = $wsvc()->preview_fee_publish();
	$out['preview'] = array( 'ok' => $pv['ok'], 'error' => $pv['error_code'], 'summary' => $pv['summary'] );
	$requests       = array();
	$remaining      = (int) $pv['summary']['ready'];
	for ( $i = 0; $i < 20 && $remaining > 0; $i++ ) {
		$r          = $wsvc()->activate_fees( $pv['plan_digest'], $pv['activate_phrase'], $remaining );
		$requests[] = array( 'ok' => $r['ok'], 'status' => $r['status'], 'error' => $r['error_code'], 'processed' => $r['processed'], 'remaining' => $r['remaining'] );
		if ( empty( $r['ok'] ) ) {
			break;
		}
		$remaining = (int) $r['remaining'];
	}
	$out['requests']       = $requests;
	$out['active_visible'] = (int) MaviBelge_Core_Catalog_Service::get_active_fee_count();
	$ids                   = $feeIds();
	$dupQ                  = (int) get_post_meta( $ids['fee:is-makineleri:3:mobil-vinc-operatoru-mhc-sahil-ve-gemi-vinci'], '_mb_qualification_id', true );
	$out['dup_qual_url']   = $dupQ > 0 ? get_permalink( $dupQ ) : '';
	$page                  = get_page_by_path( 'sinav-ucretleri', OBJECT, 'page' );
	$out['fee_page']       = $page instanceof WP_Post ? get_permalink( $page ) : '';
	array_map( 'unlink', glob( $tmp . '/*' ) );
	rmdir( $tmp );
	echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
	exit( 0 );
}

$fail( 'Kullanım: fee-catalog-fixtures.php publish|after|whatif' );
