<?php
/**
 * Faz 6B3 Düzeltme — GERÇEK WordPress 6.9.9 + PHP 7.3.33 + MariaDB runtime kabul kapıları
 * (`wp --require=fixture-env.php --user=mbadmin eval-file ...`).
 *
 * Kapsam (Codex bulguları):
 *  1) Create rollback kullanıcı değişikliğini korur (unmanaged_fingerprint / drift_detected).
 *  2) Temiz rollback sonrası aynı aşama yeniden uygulanabilir (dry-run applicable=true, conflict=0).
 *  3) Run durumu + audit olayı atomik (hata enjeksiyonu: audit yazıldıktan sonra hata, durum geçişi hatası).
 *
 * YALNIZ klonlanmış, silinebilir `mbfx_` fixture tablolarında ve AÇIKÇA SAHTE fixture manifestiyle
 * (`zz-test-*`, `97UY7xxx`) çalışır; gerçek katalog verisi yazılmaz. Test sarmalayıcıları
 * (aşağıdaki MBFX_* sınıfları) yalnız bu betikte tanımlanır; üretim koduna test kancası eklenmez.
 */

global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	return;
}

/** Gerçek audit sink'i sarar: seçilen olaylar GERÇEKTEN yazılır, sonra false döner (transaction geri almazsa yetim audit kalır). */
class MBFX_Sink implements MaviBelge_Core_Import_Audit_Sink {
	public $inner;
	public $failAfterRecord = array();
	public function __construct() {
		$this->inner = new MaviBelge_Core_Import_WP_Audit_Sink();
	}
	public function ready() {
		return $this->inner->ready();
	}
	public function record( $event, $runId, array $context ) {
		$ok = $this->inner->record( $event, $runId, $context );
		return in_array( $event, $this->failAfterRecord, true ) ? false : $ok;
	}
}
/** Gerçek run deposu; seçilen HEDEF durumlara geçiş başarısız olur. */
class MBFX_Store extends MaviBelge_Core_Import_Wpdb_Run_Store {
	public $failTo = array();
	public function transition( $runId, $from, $to, array $fields = array() ) {
		if ( in_array( $to, $this->failTo, true ) ) {
			return false;
		}
		return parent::transition( $runId, $from, $to, $fields );
	}
}

$results = array();
$t       = function ( $label, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array( $label, (bool) $ok, (string) $detail );
};
$v1 = '/tmp/mbfx-manifest';

$mk = function ( $sink = null, $store = null ) use ( $v1 ) {
	$repo  = new MaviBelge_Core_Import_WordPress_Target_Repository();
	$sink  = null === $sink ? new MaviBelge_Core_Import_WP_Audit_Sink() : $sink;
	$store = null === $store ? new MaviBelge_Core_Import_Wpdb_Run_Store() : $store;
	$o     = new stdClass();
	$o->apply    = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $repo, $v1 ), new MaviBelge_Core_Import_WordPress_Target_Writer(), new MaviBelge_Core_Import_Wpdb_Transaction(), $store, $sink );
	$o->rollback = new MaviBelge_Core_Import_Rollback_Service( $repo, new MaviBelge_Core_Import_WordPress_Target_Writer(), new MaviBelge_Core_Import_Wpdb_Transaction(), $store, $sink );
	$o->store    = $store;
	return $o;
};
$apply = function ( $stage, $batch = null, $svc = null ) use ( $mk ) {
	$s = null === $svc ? $mk() : $svc;
	$p = $s->apply->preview( $stage );
	return $s->apply->apply( $stage, $p['plan_digest'], $batch, get_current_user_id() );
};
$rollback = function ( $uid, $batch = null, $svc = null ) use ( $mk ) {
	$s = null === $svc ? $mk() : $svc;
	$p = $s->rollback->preview( $uid );
	return $s->rollback->rollback( $uid, $p['rollback_digest'], $batch );
};
$store   = new MaviBelge_Core_Import_Wpdb_Run_Store();
$counts  = function ( $type, $status = null ) use ( $wpdb ) {
	$sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s";
	$arg = array( $type, $wpdb->esc_like( 'TEST ' ) . '%' );
	if ( null !== $status ) {
		$sql  .= ' AND post_status = %s';
		$arg[] = $status;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $arg ) );
};
$terms   = function () use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'mb_sektor' AND t.slug LIKE %s", $wpdb->esc_like( 'zz-test-' ) . '%' ) );
};
$bySource = function ( $sourceKey ) use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_mb_import_source_key' WHERE m.meta_value = %s AND p.post_status <> 'trash' ORDER BY p.ID DESC LIMIT 1", $sourceKey ) );
};
$termId  = function ( $slug ) {
	$term = get_term_by( 'slug', $slug, 'mb_sektor' );
	return $term instanceof WP_Term ? (int) $term->term_id : 0;
};
$emptyTrash = function () use ( $wpdb ) {
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_type IN ('mb_yeterlilik','mb_ucret') AND post_title LIKE %s", $wpdb->esc_like( 'TEST ' ) . '%' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	return count( $ids );
};
$auditEvents = function ( $runId ) use ( $wpdb ) {
	return $wpdb->get_col( $wpdb->prepare( "SELECT event_type FROM {$wpdb->prefix}mb_audit_log WHERE object_type = 'mb_import_run' AND object_id = %d ORDER BY id", $runId ) );
};
$runStatus = function ( $uid ) use ( $store ) {
	$r = $store->get_run( (string) $uid );
	return is_array( $r ) ? $r['status'] : null;
};
$plan = function ( $stage ) use ( $mk ) {
	$p = $mk()->apply->preview( $stage );
	return array( 'applicable' => $p['summary']['applicable'], 'conflict' => $p['summary']['operations']['conflict'], 'create' => $p['summary']['operations']['create'], 'eligible' => $p['eligible'] );
};
$blockerCodes = function ( $uid ) use ( $mk ) {
	$pv = $mk()->rollback->preview( $uid );
	return array_map(
		function ( $b ) {
			return $b['code'];
		},
		$pv['blockers']
	);
};
$feeKey = 'fee:zz-test-a:3:test-meslek-bir';

$t( 'başlangıç: temiz fixture (zz-test terimi ve TEST postu yok)', 0 === $terms() && 0 === $counts( 'mb_yeterlilik' ) && 0 === $counts( 'mb_ucret' ) );

/* ===== 1) Create rollback: kullanıcı değişikliklerini koru ===== */
$r1 = $apply( 'sectors' );
$r2 = $apply( 'qualifications' );
$r3 = $apply( 'all', 2 );
$t( 'apply zinciri sectors -> qualifications -> all completed', 'completed' === $r1['status'] && 'completed' === $r2['status'] && 'completed' === $r3['status'], wp_json_encode( array( $r1['status'], $r2['status'], $r3['status'] ) ) );
$fee  = $bySource( $feeKey );
$qual = $bySource( 'qualification:97UY7001-3/01' );
$sectA = $termId( 'zz-test-a' );
$rec  = MaviBelge_Core_Import_Rollback_Codec::decode( $store->get_items( $store->get_run( $r3['run_uid'] )['id'] )[0]['rollback_record'] );
$t( 'create rollback kaydı gerçek WordPress\'te unmanaged_fingerprint (64-hex) taşır; ham içerik saklanmaz', is_array( $rec ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', $rec['unmanaged_fingerprint'] ) );

$trashedAny = function () use ( $counts ) {
	return $counts( 'mb_ucret', 'trash' ) + $counts( 'mb_yeterlilik', 'trash' );
};
$driftCase = function ( $label, $uid, callable $mutate, callable $restore, $stillIntact ) use ( $t, $rollback, $blockerCodes, $store, $trashedAny, $terms ) {
	$mutate();
	$codes = $blockerCodes( $uid );
	$r     = $rollback( $uid );
	$t( $label, in_array( 'drift_detected', $codes, true ) && false === $r['ok'] && 'drift_detected' === $r['error_code'] && 0 === $trashedAny() && 3 === $terms() && $stillIntact(), wp_json_encode( array( $codes, $r['error_code'], $trashedAny(), $terms() ) ) );
	$restore();
};
$driftCase( 'create rollback drift: ücret post_content değişti -> drift_detected, çöpe gitmez, içerik korunur', $r3['run_uid'],
	function () use ( $fee ) {
		wp_update_post( wp_slash( array( 'ID' => $fee, 'post_content' => 'Kullanıcının içeriği' ) ) );
	},
	function () use ( $fee ) {
		wp_update_post( wp_slash( array( 'ID' => $fee, 'post_content' => '' ) ) );
	},
	function () use ( $fee ) {
		return 'Kullanıcının içeriği' === get_post( $fee )->post_content;
	}
);
$driftCase( 'create rollback drift: ücret postuna SEO meta eklendi -> drift_detected, çöpe gitmez', $r3['run_uid'],
	function () use ( $fee ) {
		add_post_meta( $fee, '_yoast_wpseo_title', 'SEO', true );
	},
	function () use ( $fee ) {
		delete_post_meta( $fee, '_yoast_wpseo_title' );
	},
	function () use ( $fee ) {
		return 'SEO' === get_post_meta( $fee, '_yoast_wpseo_title', true );
	}
);
register_taxonomy( 'mbfx_extra', 'mb_ucret', array( 'public' => false ) );
$extra = wp_insert_term( 'Ekstra', 'mbfx_extra' );
$driftCase( 'create rollback drift: ücret postuna yönetilmeyen taksonomi terimi bağlandı -> drift_detected, çöpe gitmez', $r3['run_uid'],
	function () use ( $fee, $extra ) {
		wp_set_object_terms( $fee, array( (int) $extra['term_id'] ), 'mbfx_extra' );
	},
	function () use ( $fee ) {
		wp_set_object_terms( $fee, array(), 'mbfx_extra' );
	},
	function () use ( $fee ) {
		return 1 === count( wp_get_object_terms( $fee, 'mbfx_extra', array( 'fields' => 'ids' ) ) );
	}
);
$driftCase( 'create rollback drift: yeterlilik post_content değişti -> drift_detected (yeterlilik run\'ı), çöpe gitmez', $r2['run_uid'],
	function () use ( $qual ) {
		wp_update_post( wp_slash( array( 'ID' => $qual, 'post_content' => 'Yeterlilik içeriği' ) ) );
	},
	function () use ( $qual ) {
		wp_update_post( wp_slash( array( 'ID' => $qual, 'post_content' => '' ) ) );
	},
	function () use ( $qual ) {
		return 'Yeterlilik içeriği' === get_post( $qual )->post_content;
	}
);
$driftCase( 'create rollback drift: sektör teriminin yönetilmeyen term metası eklendi -> drift_detected, terim silinmez', $r1['run_uid'],
	function () use ( $sectA ) {
		add_term_meta( $sectA, '_mbfx_harici', 'x', true );
	},
	function () use ( $sectA ) {
		delete_term_meta( $sectA, '_mbfx_harici' );
	},
	function () use ( $sectA ) {
		return 'x' === get_term_meta( $sectA, '_mbfx_harici', true );
	}
);
$driftCase( 'create rollback drift: sektör teriminin parent değeri değişti -> drift_detected, terim silinmez', $r1['run_uid'],
	function () use ( $wpdb, $sectA ) {
		$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => 999999 ), array( 'term_id' => $sectA, 'taxonomy' => 'mb_sektor' ) );
		clean_term_cache( $sectA, 'mb_sektor' );
	},
	function () use ( $wpdb, $sectA ) {
		$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => 0 ), array( 'term_id' => $sectA, 'taxonomy' => 'mb_sektor' ) );
		clean_term_cache( $sectA, 'mb_sektor' );
	},
	function () use ( $sectA ) {
		return 999999 === (int) get_term( $sectA, 'mb_sektor' )->parent;
	}
);
$driftCase( 'create rollback drift: sektör teriminin term_group değeri değişti -> drift_detected, terim silinmez', $r1['run_uid'],
	function () use ( $wpdb, $sectA ) {
		$wpdb->update( $wpdb->terms, array( 'term_group' => 3 ), array( 'term_id' => $sectA ) );
		clean_term_cache( $sectA, 'mb_sektor' );
	},
	function () use ( $wpdb, $sectA ) {
		$wpdb->update( $wpdb->terms, array( 'term_group' => 0 ), array( 'term_id' => $sectA ) );
		clean_term_cache( $sectA, 'mb_sektor' );
	},
	function () use ( $wpdb, $sectA ) {
		return 3 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_group FROM {$wpdb->terms} WHERE term_id = %d", $sectA ) );
	}
);

/* ===== 2) Temiz rollback -> yeniden apply ===== */
// all aşaması: yalnız ücretler geri alınır; aynı aşama yeniden uygulanabilir.
$rb3 = $rollback( $r3['run_uid'] );
$t( 'değişiklik yok: temiz rollback çalışır (ücret run\'ı rolled_back, 5 ücret çöpte)', true === $rb3['ok'] && 'rolled_back' === $runStatus( $r3['run_uid'] ) && 5 === $counts( 'mb_ucret', 'trash' ), wp_json_encode( $rb3 ) );
$leftMeta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = 'mb_ucret' AND p.post_status = 'trash' AND m.meta_key IN ('_mb_import_source_key','_mb_last_applied_hash','_mb_sector_slug','_mb_level','_mb_profession_name')" );
$t( 'rollback edilen ücret postlarında marker/doğal anahtar meta KALMADI (çöpteki kabuk), kalıcı silme YOK', 0 === $leftMeta && 5 === $counts( 'mb_ucret', 'trash' ), 'kalan=' . $leftMeta );
$pAll = $plan( 'all' );
$t( 'all apply -> clean rollback -> all dry-run: applicable=true, conflict=0, 5 create adayı', true === $pAll['applicable'] && true === $pAll['eligible'] && 0 === $pAll['conflict'] && 5 === $pAll['create'], wp_json_encode( $pAll ) );
$r3b = $apply( 'all', 2 );
$t( 'aynı manifest, aynı aşama yeniden apply -> completed, 5 canlı ücret', 'completed' === $r3b['status'] && 5 === $counts( 'mb_ucret', 'draft' ), wp_json_encode( $r3b ) );
// Kullanıcıya ait keyfi çöp kaydı HÂLÂ conflict üretmelidir (fuzzy/görmezden gelme yok).
$rb3b = $rollback( $r3b['run_uid'] );
$userTrash = wp_insert_post( wp_slash( array( 'post_type' => 'mb_ucret', 'post_title' => 'TEST Kullanıcı çöp', 'post_status' => 'draft' ) ) );
update_post_meta( $userTrash, '_mb_sector_slug', 'zz-test-a' );
update_post_meta( $userTrash, '_mb_level', '3' );
update_post_meta( $userTrash, '_mb_profession_name', 'Test Meslek Bir' );
wp_trash_post( $userTrash );
$pUser = $plan( 'all' );
$t( 'kullanıcıya ait aynı doğal anahtarlı çöp kaydı HÂLÂ conflict üretir (plan applicable=false)', true === $rb3b['ok'] && false === $pUser['applicable'] && $pUser['conflict'] >= 1, wp_json_encode( $pUser ) );
wp_delete_post( $userTrash, true );
$otherRun = wp_insert_post( wp_slash( array( 'post_type' => 'mb_ucret', 'post_title' => 'TEST Başka run', 'post_status' => 'draft' ) ) );
update_post_meta( $otherRun, '_mb_sector_slug', 'zz-test-a' );
update_post_meta( $otherRun, '_mb_level', '3' );
update_post_meta( $otherRun, '_mb_profession_name', 'Test Meslek Bir' );
update_post_meta( $otherRun, '_mb_import_source_key', $feeKey );
wp_trash_post( $otherRun );
$pOther = $plan( 'all' );
$t( 'başka run\'ın (rollback edilmemiş) marker\'lı çöp kaydı HÂLÂ conflict üretir', false === $pOther['applicable'] && $pOther['conflict'] >= 1, wp_json_encode( $pOther ) );
wp_delete_post( $otherRun, true );
$pClean = $plan( 'all' );
$t( 'kullanıcı/başka-run çöp kayıtları silinince plan yeniden temiz (applicable=true, conflict=0)', true === $pClean['applicable'] && 0 === $pClean['conflict'], wp_json_encode( $pClean ) );
$r3c = $apply( 'all', 2 );
$rbAll3 = $rollback( $r3c['run_uid'] );
// qualifications aşaması: rollback -> dry-run -> reapply.
$rbQ = $rollback( $r2['run_uid'] );
$pQ  = $plan( 'qualifications' );
$t( 'qualifications apply -> rollback -> dry-run: applicable=true, conflict=0, 3 create', true === $rbAll3['ok'] && true === $rbQ['ok'] && true === $pQ['applicable'] && 0 === $pQ['conflict'] && 3 === $pQ['create'], wp_json_encode( array( $rbQ['error_code'], $pQ ) ) );
$r2b = $apply( 'qualifications' );
$t( 'qualifications yeniden apply -> completed', 'completed' === $r2b['status'] && 3 === $counts( 'mb_yeterlilik', 'draft' ), wp_json_encode( $r2b ) );
// sectors aşaması: önce bağımlılar geri alınır, sonra sektör -> dry-run -> reapply.
$rbQ2 = $rollback( $r2b['run_uid'] );
$rbS  = $rollback( $r1['run_uid'] );
$pS   = $plan( 'sectors' );
$t( 'sectors apply -> rollback -> dry-run: applicable=true, conflict=0, 3 create; terimler gerçekten silindi', true === $rbQ2['ok'] && true === $rbS['ok'] && 0 === $terms() && true === $pS['applicable'] && 0 === $pS['conflict'] && 3 === $pS['create'], wp_json_encode( array( $rbS['error_code'], $pS ) ) );
$s1b = $apply( 'sectors' );
$s2b = $apply( 'qualifications' );
$s3b = $apply( 'all', 2 );
$t( 'sectors -> qualifications -> all zinciri yeniden completed (tam döngü)', 'completed' === $s1b['status'] && 'completed' === $s2b['status'] && 'completed' === $s3b['status'], wp_json_encode( array( $s1b['status'], $s2b['status'], $s3b['status'] ) ) );
$rollback( $s3b['run_uid'] );
$rollback( $s2b['run_uid'] );
$rollback( $s1b['run_uid'] );
$emptyTrash();
$t( 'döngü sonu: terim/post kalmadı (fixture temiz)', 0 === $terms() && 0 === $counts( 'mb_yeterlilik' ) && 0 === $counts( 'mb_ucret' ) );

/* ===== 3) Run durumu + audit atomikliği (gerçek transaction, gerçek audit tablosu) ===== */
$a1 = $apply( 'sectors' );
$a2 = $apply( 'qualifications' );
$run = function ( $r ) use ( $store ) {
	return $store->get_run( (string) $r['run_uid'] );
};
// (a) run_completed audit GERÇEKTEN yazıldıktan sonra false döner: transaction audit satırını geri almalı.
$sink = new MBFX_Sink();
$sink->failAfterRecord = array( 'import_run_completed' );
$svc = $mk( $sink );
$rA  = $apply( 'all', 2, $svc );
$runA = $run( $rA );
$evA = is_array( $runA ) ? $auditEvents( $runA['id'] ) : array();
$t( 'atomiklik (gerçek DB): run_completed audit yazıldıktan sonra hata -> audit satırı GERİ ALINIR; run running/completed DEĞİL rollback_required; sonuç gerçek durumu bildirir',
	false === $rA['ok'] && 'finalization_failed' === $rA['error_code'] && 'rollback_required' === $rA['status'] && is_array( $runA ) && 'rollback_required' === $runA['status'] && ! in_array( 'import_run_completed', $evA, true ) && in_array( 'import_run_failed', $evA, true ), wp_json_encode( array( $rA['status'], $evA ) ) );
$rbA = $rollback( $rA['run_uid'] );
$t( 'atomiklik: rollback_required run geri alınır (rolled_back)', true === $rbA['ok'], wp_json_encode( $rbA ) );
$emptyTrash();
// (b) completed durum geçişi başarısız: aynı transaction'daki audit satırı geri alınır.
$st = new MBFX_Store();
$st->failTo = array( 'completed' );
$svcB = $mk( null, $st );
$rB   = $apply( 'all', 2, $svcB );
$runB = $run( $rB );
$evB  = is_array( $runB ) ? $auditEvents( $runB['id'] ) : array();
$t( 'atomiklik (gerçek DB): completed durum geçişi başarısız -> run_completed audit satırı yok; sonuç completed UYDURMAZ (rollback_required)', false === $rB['ok'] && 'rollback_required' === $rB['status'] && is_array( $runB ) && 'rollback_required' === $runB['status'] && ! in_array( 'import_run_completed', $evB, true ), wp_json_encode( array( $rB['status'], $evB ) ) );
$rollback( $rB['run_uid'] );
$emptyTrash();
// (c) run_started audit yazıldıktan sonra hata: planned/running KALMAZ, audit satırı geri alınır, hiçbir içerik yazılmaz.
$sink2 = new MBFX_Sink();
$sink2->failAfterRecord = array( 'import_run_started' );
$rC   = $apply( 'all', 2, $mk( $sink2 ) );
$runC = $run( $rC );
$evC  = is_array( $runC ) ? $auditEvents( $runC['id'] ) : array();
$t( 'atomiklik (gerçek DB): run_started audit yazıldıktan sonra hata -> audit satırı geri alınır; run failed (planned/running KALMAZ); içerik yazılmadı',
	false === $rC['ok'] && 'run_start_failed' === $rC['error_code'] && 'failed' === $rC['status'] && is_array( $runC ) && 'failed' === $runC['status'] && ! in_array( 'import_run_started', $evC, true ) && 0 === $counts( 'mb_ucret' ), wp_json_encode( array( $rC['status'], $evC ) ) );
// (d) rollback_started audit yazıldıktan sonra hata: durum completed KALIR, audit satırı geri alınır, hiçbir yazma yok.
$rD0 = $apply( 'all', 2 );
$sink3 = new MBFX_Sink();
$sink3->failAfterRecord = array( 'import_rollback_started' );
$svcD = $mk( $sink3 );
$pvD  = $svcD->rollback->preview( $rD0['run_uid'] );
$rD   = $svcD->rollback->rollback( $rD0['run_uid'], $pvD['rollback_digest'], 2 );
$evD  = $auditEvents( $store->get_run( $rD0['run_uid'] )['id'] );
$t( 'atomiklik (gerçek DB): rollback_started audit hatası -> durum completed KALIR (rolling_back OLUŞMAZ), started audit satırı yok, ücretler çöpe gitmedi',
	false === $rD['ok'] && 'completed' === $rD['status'] && 'completed' === $runStatus( $rD0['run_uid'] ) && ! in_array( 'import_rollback_started', $evD, true ) && 5 === $counts( 'mb_ucret', 'draft' ) && 0 === $counts( 'mb_ucret', 'trash' ), wp_json_encode( array( $rD['status'], $evD ) ) );
// (e) rollback_completed audit yazıldıktan sonra hata: run rollback_failed, completed audit satırı geri alınır.
$sink4 = new MBFX_Sink();
$sink4->failAfterRecord = array( 'import_rollback_completed' );
$svcE = $mk( $sink4 );
$pvE  = $svcE->rollback->preview( $rD0['run_uid'] );
$rE   = $svcE->rollback->rollback( $rD0['run_uid'], $pvE['rollback_digest'], 2 );
$evE  = $auditEvents( $store->get_run( $rD0['run_uid'] )['id'] );
$t( 'atomiklik (gerçek DB): rollback_completed audit hatası -> run rollback_failed (rolling_back KALMAZ), completed audit satırı yok; sonuç gerçek durum',
	false === $rE['ok'] && 'finalization_failed' === $rE['error_code'] && 'rollback_failed' === $rE['status'] && 'rollback_failed' === $runStatus( $rD0['run_uid'] ) && ! in_array( 'import_rollback_completed', $evE, true ), wp_json_encode( array( $rE['status'], $evE ) ) );
// (f) rollback_failed run\'ı yeniden denenir: kalan item'lar yok -> rolled_back (temiz).
$rF = $rollback( $rD0['run_uid'] );
$t( 'atomiklik: rollback_failed run yeniden denenir -> rolled_back; audit rollback_completed artık var', true === $rF['ok'] && 'rolled_back' === $runStatus( $rD0['run_uid'] ) && in_array( 'import_rollback_completed', $auditEvents( $store->get_run( $rD0['run_uid'] )['id'] ), true ), wp_json_encode( $rF ) );
$rollback( $a2['run_uid'] );
$rollback( $a1['run_uid'] );
$emptyTrash();
$t( 'temizlik: fixture içerik kalmadı', 0 === $terms() && 0 === $counts( 'mb_yeterlilik' ) && 0 === $counts( 'mb_ucret' ) );

$pass = 0;
foreach ( $results as $r ) {
	echo ( $r[1] ? 'PASS  ' : 'FAIL  ' ) . $r[0] . ( '' !== $r[2] && ! $r[1] ? '  [' . $r[2] . ']' : '' ) . "\n";
	$pass += $r[1] ? 1 : 0;
}
echo "\n{$pass}/" . count( $results ) . " Faz 6B3 düzeltme WordPress runtime testi geçti.\n";
