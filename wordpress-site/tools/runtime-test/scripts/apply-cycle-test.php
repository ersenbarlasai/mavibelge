<?php
/**
 * Faz 6B3 — GERÇEK WordPress 6.9.9 + PHP 7.3.33 + MariaDB apply/rollback
 * döngüsü (`wp --require=fixture-env.php --user=mbadmin eval-file ...`).
 *
 * YALNIZ klonlanmış, silinebilir `mbfx_` fixture tablolarında ve AÇIKÇA
 * SAHTE fixture manifestiyle (`zz-test-*`, `97UY7xxx`) çalışır; gerçek
 * katalog verisi yazılmaz. Servisler gerçek WordPress uygulamalarıyla
 * (yazma adapterı, $wpdb transaction, run deposu, audit) kurulur.
 * Hata enjeksiyonu yalnız bu süreçte eklenen WordPress filtreleriyle
 * yapılır; üretim koduna test kancası eklenmez.
 */

global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	return;
}

$results = array();
$t       = function ( $label, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array( $label, (bool) $ok, (string) $detail );
};
$v1 = '/tmp/mbfx-manifest';
$v2 = '/tmp/mbfx-manifest-v2';

$services = function ( $dir ) {
	$repo = new MaviBelge_Core_Import_WordPress_Target_Repository();
	$o    = new stdClass();
	$o->apply    = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $repo, $dir ), new MaviBelge_Core_Import_WordPress_Target_Writer(), new MaviBelge_Core_Import_Wpdb_Transaction(), new MaviBelge_Core_Import_Wpdb_Run_Store(), new MaviBelge_Core_Import_WP_Audit_Sink() );
	$o->rollback = new MaviBelge_Core_Import_Rollback_Service( $repo, new MaviBelge_Core_Import_WordPress_Target_Writer(), new MaviBelge_Core_Import_Wpdb_Transaction(), new MaviBelge_Core_Import_Wpdb_Run_Store(), new MaviBelge_Core_Import_WP_Audit_Sink() );
	return $o;
};
$apply = function ( $dir, $stage, $batch = null ) use ( $services ) {
	$s = $services( $dir );
	$p = $s->apply->preview( $stage );
	return $s->apply->apply( $stage, $p['plan_digest'], $batch, get_current_user_id() );
};
$rollback = function ( $uid, $batch = null ) use ( $services, $v1 ) {
	$s = $services( $v1 );
	$p = $s->rollback->preview( $uid );
	return $s->rollback->rollback( $uid, $p['rollback_digest'], $batch );
};
$store = new MaviBelge_Core_Import_Wpdb_Run_Store();

$zzTerms = function () use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'mb_sektor' AND t.slug LIKE %s", $wpdb->esc_like( 'zz-test-' ) . '%' ) );
};
$fxPosts = function ( $postType, $status = null ) use ( $wpdb ) {
	$sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s";
	$arg = array( $postType, $wpdb->esc_like( 'TEST ' ) . '%' );
	if ( null !== $status ) {
		$sql  .= ' AND post_status = %s';
		$arg[] = $status;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $arg ) );
};
$postBySource = function ( $sourceKey ) use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_mb_import_source_key' WHERE m.meta_value = %s AND p.post_status <> 'trash' ORDER BY p.ID DESC LIMIT 1", $sourceKey ) );
};
$termBySlug = function ( $slug ) {
	$term = get_term_by( 'slug', $slug, 'mb_sektor' );
	return $term instanceof WP_Term ? (int) $term->term_id : 0;
};
/** Canlı (çöp olmayan) içerik parmak izi: bütün mb_sektor terimleri + meta, çöp olmayan yeterlilik/ücret postları + meta + terimler. */
$liveFingerprint = function () use ( $wpdb ) {
	$terms = $wpdb->get_results( "SELECT t.term_id, t.slug, t.name, tt.description, tt.parent FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'mb_sektor' ORDER BY t.term_id", ARRAY_A );
	$tmeta = $wpdb->get_results( "SELECT m.term_id, m.meta_key, m.meta_value FROM {$wpdb->termmeta} m JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = m.term_id WHERE tt.taxonomy = 'mb_sektor' ORDER BY m.meta_id", ARRAY_A );
	$posts = $wpdb->get_results( "SELECT ID, post_type, post_title, post_status, post_content FROM {$wpdb->posts} WHERE post_type IN ('mb_yeterlilik','mb_ucret') AND post_status <> 'trash' ORDER BY ID", ARRAY_A );
	$pmeta = $wpdb->get_results( "SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type IN ('mb_yeterlilik','mb_ucret') AND p.post_status <> 'trash' ORDER BY m.meta_id", ARRAY_A );
	$rels  = $wpdb->get_results( "SELECT r.object_id, r.term_taxonomy_id FROM {$wpdb->term_relationships} r JOIN {$wpdb->posts} p ON p.ID = r.object_id WHERE p.post_type IN ('mb_yeterlilik','mb_ucret') AND p.post_status <> 'trash' ORDER BY r.object_id, r.term_taxonomy_id", ARRAY_A );
	return hash( 'sha256', serialize( array( $terms, $tmeta, $posts, $pmeta, $rels ) ) );
};
$emptyFixtureTrash = function () use ( $wpdb ) {
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_type IN ('mb_yeterlilik','mb_ucret') AND post_title LIKE %s", $wpdb->esc_like( 'TEST ' ) . '%' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	return count( $ids );
};
$auditRows = function ( $runId ) use ( $wpdb ) {
	return $wpdb->get_results( $wpdb->prepare( "SELECT event_type, context FROM {$wpdb->prefix}mb_audit_log WHERE object_type = 'mb_import_run' AND object_id = %d ORDER BY id", $runId ), ARRAY_A );
};
$sentinel = function () use ( $wpdb ) {
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE '%rejected-meta-write%'" ) + (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_value LIKE '%rejected-meta-write%'" );
};
$orphanMeta = function () use ( $wpdb ) {
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} m LEFT JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.ID IS NULL" )
		+ (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->termmeta} m LEFT JOIN {$wpdb->terms} t ON t.term_id = m.term_id WHERE t.term_id IS NULL" );
};
$content = function ( $id ) {
	$p = get_post( $id );
	return $p instanceof WP_Post ? $p->post_content : null;
};
$termDesc = function ( $id ) {
	$term = get_term( $id, 'mb_sektor' );
	return $term instanceof WP_Term ? $term->description : null;
};
$tariffBefore = get_option( 'mb_active_tariff_period', '__yok__' );

// (0) Başlangıç: CLI testlerinden sonra fixture terimi/postu yok.
$t( 'başlangıç: zz-test terimi ve TEST postu yok', 0 === $zzTerms() && 0 === $fxPosts( 'mb_yeterlilik' ) && 0 === $fxPosts( 'mb_ucret' ), $zzTerms() . '/' . $fxPosts( 'mb_ucret' ) );
$B0 = $liveFingerprint();

// (0b) Transaction ön kontrolü: yazılacak bir tablo transactional değilse (MyISAM) apply HİÇ başlamaz.
$wpdb->query( "ALTER TABLE {$wpdb->postmeta} ENGINE=MyISAM" );
$rMy = $apply( $v1, 'sectors' );
$wpdb->query( "ALTER TABLE {$wpdb->postmeta} ENGINE=InnoDB" );
$t( 'ön kontrol: transactional olmayan tablo (fixture postmeta MyISAM) -> infrastructure_unavailable; run/terim/yazma YOK', 'infrastructure_unavailable' === $rMy['error_code'] && null === $rMy['run_uid'] && 0 === $zzTerms() && $B0 === $liveFingerprint(), wp_json_encode( $rMy ) );

// (1) TOCTOU — gerçek WordPress: A oluşturulurken aynı transaction içinde C'nin doğal anahtarı yönetilmeyen bir terimle doldurulur.
$toctouHook = function ( $termId ) {
	$term = get_term( $termId, 'mb_sektor' );
	if ( $term instanceof WP_Term && 'zz-test-a' === $term->slug ) {
		wp_insert_term( 'Arada eklenen', 'mb_sektor', array( 'slug' => 'zz-test-c' ) );
	}
};
add_action( 'created_mb_sektor', $toctouHook );
$r0 = $apply( $v1, 'sectors' );
remove_action( 'created_mb_sektor', $toctouHook );
$run0 = $store->get_run( (string) $r0['run_uid'] );
$t( 'TOCTOU: yazma sırasında beliren doğal anahtar -> toctou_drift, run failed, batch TAMAMEN geri alındı (a, b ve araya eklenen c yok)', 'toctou_drift' === $r0['error_code'] && is_array( $run0 ) && 'failed' === $run0['status'] && 0 === $zzTerms() && array() === $store->get_items( $run0['id'] ), $r0['error_code'] . ' zz=' . $zzTerms() );
$t( 'TOCTOU: yarım meta/sentinel yok, canlı içerik başlangıçla aynı', 0 === $orphanMeta() && 0 === $sentinel() && $B0 === $liveFingerprint() );

// (2) Başarılı küçük fixture apply: sektör -> yeterlilik -> ücret (batch=2).
$r1 = $apply( $v1, 'sectors' );
$t( 'apply sectors: completed, 3 terim, 1 batch, 3 item', true === $r1['ok'] && 'completed' === $r1['status'] && 3 === $zzTerms() && 3 === $r1['committed_items'], wp_json_encode( $r1 ) );
$runsBefore = count( $store->list_runs( 100 ) );
$auditBefore = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mb_audit_log" );
$r1n = $apply( $v1, 'sectors' );
$t( 'idempotent: ikinci apply sectors -> noop; yeni run/audit/terim YOK', 'noop' === $r1n['status'] && 3 === $zzTerms() && count( $store->list_runs( 100 ) ) === $runsBefore && (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mb_audit_log" ) === $auditBefore );
$r2 = $apply( $v1, 'qualifications' );
$r3 = $apply( $v1, 'all', 2 );
$t( 'apply qualifications + all(batch=2): completed; 3 yeterlilik + 5 ücret draft; ücret run\'ı 3 batch', 'completed' === $r2['status'] && 'completed' === $r3['status'] && 3 === $fxPosts( 'mb_yeterlilik', 'draft' ) && 5 === $fxPosts( 'mb_ucret', 'draft' ) && 3 === $r3['committed_batches'], wp_json_encode( $r3 ) );
$fee1  = $postBySource( 'fee:zz-test-a:3:test-meslek-bir' );
$fee4  = $postBySource( 'fee:zz-test-a:2:test-meslek-dort' );
$qual1 = $postBySource( 'qualification:97UY7001-3/01' );
$t( 'kuruş x100 YOK: belge ücreti 150000, min/max 1000000 (saklanan kanonik kuruş)', '150000' === get_post_meta( $fee1, '_mb_certificate_print_fee_kurus', true ) && '1000000' === get_post_meta( $fee1, '_mb_min_amount_kurus', true ) && '1000000' === get_post_meta( $fee1, '_mb_max_amount_kurus', true ) );
$t( 'ilişki: kodlu ücret doğrulanmış yeterlilik ID\'sine, kodsuz ücret 0\'a bağlı; yeterlilik gerçek mb_sektor terimine bağlı',
	(string) $qual1 === get_post_meta( $fee1, '_mb_qualification_id', true ) && '0' === get_post_meta( $fee4, '_mb_qualification_id', true )
	&& array( $termBySlug( 'zz-test-a' ) ) === array_map( 'intval', wp_get_object_terms( $qual1, 'mb_sektor', array( 'fields' => 'ids' ) ) ) );
$t( 'aktif tarife seçeneği (mb_active_tariff_period) DEĞİŞMEDİ', get_option( 'mb_active_tariff_period', '__yok__' ) === $tariffBefore );
$fullPlan = $services( $v1 )->apply->preview( 'all' );
$t( 'readback: tam fixture planı artık 11/11 unchanged, applicable', 0 === $fullPlan['writes'] && 11 === $fullPlan['summary']['operations']['unchanged'] && true === $fullPlan['summary']['applicable'] );
$runRow1 = $store->get_run( $r1['run_uid'] );
$events1 = array_map(
	function ( $r ) {
		return $r['event_type'];
	},
	$auditRows( $runRow1['id'] )
);
$ctxOk = true;
foreach ( $auditRows( $runRow1['id'] ) as $row ) {
	$ctx = json_decode( $row['context'], true );
	if ( ! is_array( $ctx ) || null === MaviBelge_Core_Import_Audit_Context::build( $ctx ) || false !== strpos( $row['context'], 'TEST Sektör' ) || false !== strpos( $row['context'], 'Sahte test' ) ) {
		$ctxOk = false;
	}
}
$t( 'audit: run_started -> batch_committed -> run_completed; context kapalı izin listesinde, alan içeriği YOK', array( 'import_run_started', 'import_batch_committed', 'import_run_completed' ) === $events1 && $ctxOk, implode( ',', $events1 ) );
$items1 = $store->get_items( $runRow1['id'] );
$t( 'checkpoint: run satırı completed, committed_batches=1, 3 item, her rollback kaydı kodekten geçiyor', 'completed' === $runRow1['status'] && 1 === $runRow1['committed_batches'] && 3 === count( $items1 ) && 3 === count( array_filter( array_map( function ( $i ) { return MaviBelge_Core_Import_Rollback_Codec::decode( $i['rollback_record'] ); }, $items1 ) ) ) );

// (3) Apply -> rollback döngüsü: ücret -> yeterlilik -> sektör; canlı içerik başlangıç durumuna döner.
$rbSectorsEarly = $rollback( $r1['run_uid'] );
$t( 'rollback red: sektör terimine run dışı yeterlilik/ücret bağlıyken -> rollback_failed(term_has_external_dependents); terim SİLİNMEDİ', 'term_has_external_dependents' === $rbSectorsEarly['error_code'] && 3 === $zzTerms() && 'rollback_failed' === $store->get_run( $r1['run_uid'] )['status'] );
$rb3 = $rollback( $r3['run_uid'] );
$rb2 = $rollback( $r2['run_uid'] );
$rb1 = $rollback( $r1['run_uid'] );
$t( 'rollback döngüsü: ücret, yeterlilik, sektör run\'ları rolled_back; postlar ÇÖPTE (silinmedi), terimler silindi', true === $rb3['ok'] && true === $rb2['ok'] && true === $rb1['ok'] && 5 === $fxPosts( 'mb_ucret', 'trash' ) && 3 === $fxPosts( 'mb_yeterlilik', 'trash' ) && 0 === $zzTerms(), wp_json_encode( array( $rb3['error_code'], $rb2['error_code'], $rb1['error_code'] ) ) );
$t( 'rollback döngüsü: canlı içerik parmak izi başlangıçla BİREBİR aynı; yarım meta/sentinel yok', $B0 === $liveFingerprint() && 0 === $orphanMeta() && 0 === $sentinel() );
$t( 'test adımı: rollback edilen fixture postları çöpten kalıcı silindi (yönetici çöpü boşaltma simülasyonu)', 8 === $emptyFixtureTrash() );

// (4) Batch ortasında hata enjeksiyonu: 3. ücretin _mb_price_options yazımı başarısız (post + bazı meta yazılmış durumda).
$apply( $v1, 'sectors' );
$apply( $v1, 'qualifications' );
$failMeta = function ( $check, $objectId, $metaKey ) {
	if ( '_mb_price_options' === $metaKey && 'TEST Meslek Uc' === get_the_title( $objectId ) ) {
		return false;
	}
	return $check;
};
add_filter( 'update_post_metadata', $failMeta, 10, 3 );
$r4 = $apply( $v1, 'all', 2 );
remove_filter( 'update_post_metadata', $failMeta, 10 );
$run4 = $store->get_run( (string) $r4['run_uid'] );
$t( 'hata enjeksiyonu: 2. batch ortasında yazma hatası -> run rollback_required, 1 batch/2 item commit edildi', false === $r4['ok'] && 'write_failed' === $r4['error_code'] && is_array( $run4 ) && 'rollback_required' === $run4['status'] && 1 === $run4['committed_batches'] && 2 === $run4['committed_items'], wp_json_encode( $r4 ) );
$t( 'hata enjeksiyonu: başarısız batch TAMAMEN geri alındı (TEST Meslek Uc/Dort yok), yarım meta/item/sentinel yok',
	0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title IN ('TEST Meslek Uc','TEST Meslek Dort')" ) && 2 === $fxPosts( 'mb_ucret' ) && is_array( $run4 ) && 2 === count( $store->get_items( $run4['id'] ) ) && 0 === $orphanMeta() && 0 === $sentinel() );
$events4 = array_map(
	function ( $r ) {
		return $r['event_type'];
	},
	is_array( $run4 ) ? $auditRows( $run4['id'] ) : array()
);
$rows4 = is_array( $run4 ) ? $auditRows( $run4['id'] ) : array();
$last4 = empty( $rows4 ) ? array() : json_decode( $rows4[ count( $rows4 ) - 1 ]['context'], true );
$t( 'hata enjeksiyonu: audit run_started, batch_committed(1), run_failed(batch_no=2, write_failed) — başarısız batch\'in audit\'i yok', array( 'import_run_started', 'import_batch_committed', 'import_run_failed' ) === $events4 && isset( $last4['batch_no'], $last4['error_code'] ) && 2 === $last4['batch_no'] && 'write_failed' === $last4['error_code'], implode( ',', $events4 ) );
$blocked = $apply( $v1, 'all', 2 );
$t( 'çözülmemiş rollback_required run varken yeni apply reddedilir (unresolved_run_exists)', 'unresolved_run_exists' === $blocked['error_code'] && 2 === $fxPosts( 'mb_ucret' ) );
$rb4 = $rollback( $r4['run_uid'] );
$t( 'rollback_required run geri alındı -> rolled_back, 2 ücret çöpte', true === $rb4['ok'] && 2 === $fxPosts( 'mb_ucret', 'trash' ) && 0 === $fxPosts( 'mb_ucret', 'draft' ) );
$emptyFixtureTrash();
$r4d = $apply( $v1, 'all', 2 );
$t( 'yeniden apply all -> completed, 5 ücret; ardından noop', 'completed' === $r4d['status'] && 5 === $fxPosts( 'mb_ucret', 'draft' ) && 'noop' === $apply( $v1, 'all' )['status'] );

// (5) Update + yönetilmeyen alan koruması + harici sanitizer.
$fee1   = $postBySource( 'fee:zz-test-a:3:test-meslek-bir' );
$sectA  = $termBySlug( 'zz-test-a' );
$extCalls = 0;
register_post_meta(
	'mb_ucret',
	'_mb_test_external_seo',
	array(
		'type'              => 'string',
		'single'            => true,
		'sanitize_callback' => function ( $v ) use ( &$extCalls ) {
			$extCalls++;
			return $v;
		},
	)
);
add_post_meta( $fee1, '_mb_test_external_seo', 'SEO başlığı', true );
add_term_meta( $sectA, '_mb_test_external_term', 'harici', true );
wp_update_post( wp_slash( array( 'ID' => $fee1, 'post_content' => 'Editörün serbest metni' ) ) );
$extCalls = 0;
$r5 = $apply( $v2, 'all' );
$t( 'update (v2): 2 update (sektör açıklaması + ücret fiyatı); fiyat 1100000 kuruş', 'completed' === $r5['status'] && 2 === $r5['writes_total'] && '1100000' === get_post_meta( $fee1, '_mb_min_amount_kurus', true ) && 'Güncellenmiş sahte açıklama.' === $termDesc( $sectA ), wp_json_encode( $r5 ) );
$t( 'update: yönetilmeyen alanlar korundu (post_content, harici post/term meta); harici sanitizer HİÇ çalışmadı (0 çağrı)',
	'Editörün serbest metni' === $content( $fee1 ) && 'SEO başlığı' === get_post_meta( $fee1, '_mb_test_external_seo', true ) && 'harici' === get_term_meta( $sectA, '_mb_test_external_term', true ) && 0 === $extCalls, 'calls=' . $extCalls );
$rb5 = $rollback( $r5['run_uid'] );
$t( 'update rollback: yalnız yönetilen alanlar eski değere döndü (1000000, eski açıklama); yönetilmeyen alanlar korundu',
	true === $rb5['ok'] && '1000000' === get_post_meta( $fee1, '_mb_min_amount_kurus', true ) && 'Sahte test sektörü (yalnız yerel apply fixture).' === $termDesc( $sectA )
	&& 'Editörün serbest metni' === $content( $fee1 ) && 'SEO başlığı' === get_post_meta( $fee1, '_mb_test_external_seo', true ) && 'harici' === get_term_meta( $sectA, '_mb_test_external_term', true ), wp_json_encode( $rb5 ) );
$t( 'update rollback: v1 planı tamamen unchanged (marker/hash eski sözleşmeye döndü)', 0 === $services( $v1 )->apply->preview( 'all' )['writes'] );

// (6) Rollback öncesi dış değişiklik (drift): rollback reddedilir, kullanıcı değişikliği korunur.
$r6 = $apply( $v2, 'all' );
update_post_meta( $fee1, '_mb_source_name', 'Kullanıcı düzeltmesi' );
$hashBefore = get_post_meta( $fee1, '_mb_last_applied_hash', true );
$prev6 = $services( $v1 )->rollback->preview( $r6['run_uid'] );
$rb6   = $rollback( $r6['run_uid'] );
$t( 'drift: önizleme engel gösterir; rollback reddedilir (drift_detected, rollback_failed)', ! empty( $prev6['blockers'] ) && 'drift_detected' === $rb6['error_code'] && 'rollback_failed' === $store->get_run( $r6['run_uid'] )['status'], wp_json_encode( $rb6 ) );
$t( 'drift: kullanıcı değişikliği ve v2 değerleri korundu; hiçbir alan geri yazılmadı', 'Kullanıcı düzeltmesi' === get_post_meta( $fee1, '_mb_source_name', true ) && '1100000' === get_post_meta( $fee1, '_mb_min_amount_kurus', true ) && $hashBefore === get_post_meta( $fee1, '_mb_last_applied_hash', true ) );

// (7) Temizlik (fixture tabloları sürücü betik tarafından tamamen silinir).
unregister_post_meta( 'mb_ucret', '_mb_test_external_seo' );
$t( 'sentinel (REJECTED_META_WRITE) postmeta/termmeta\'da yok; yetim meta yok', 0 === $sentinel() && 0 === $orphanMeta() );

$pass = 0;
foreach ( $results as $r ) {
	echo ( $r[1] ? 'PASS  ' : 'FAIL  ' ) . $r[0] . ( '' !== $r[2] && ! $r[1] ? '  [' . $r[2] . ']' : '' ) . "\n";
	$pass += $r[1] ? 1 : 0;
}
echo "\n{$pass}/" . count( $results ) . " Faz 6B3 apply/rollback WordPress runtime testi geçti.\n";
