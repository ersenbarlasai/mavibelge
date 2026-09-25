<?php
/**
 * Faz 7 içerik aktarımı — GERÇEK WordPress 6.9.9 + PHP 7.3.33 + MariaDB runtime kanıtı
 * (`wp --require=fixture-env.php --user=mbadmin eval-file apply-cycle-test-3.php`).
 *
 * `news` (mb_haber) ve `reference` (mb_referans) türlerinin `content` aşaması:
 *   dry-run -> apply -> readback -> noop -> rollback -> dry-run (applicable, conflict=0) -> yeniden apply;
 *   güncelleme (v2 manifest) + geri alma; drift koruması (post_content, SEO meta, ek taksonomi, menu_order,
 *   durum, onay, referans alanları); kullanıcının çöp kaydı HÂLÂ conflict; atomik run finalizasyonu.
 *
 * YALNIZ klonlanmış, silinebilir `mbfx_` fixture tablolarında ve AÇIKÇA SAHTE fixture manifestiyle
 * (`zz-test-haber-*`, `zz-test-ref-*`; /tmp/mbfx-content-manifest{,-v2}) çalışır. GERÇEK
 * data/content/{news,references}.manifest.json ASLA yazılmaz/okunmaz (dizin her serviste AÇIKÇA verilir).
 * İki kontrollü `mb_haber_turu` terimini (`haber`, `duyuru`) bu betik KENDİSİ wp_insert_term ile
 * fixture içinde oluşturur (üretimde import bunları oluşturmaz, yalnız çözer) ve sonunda kaldırır.
 */

global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	return;
}
$v1 = '/tmp/mbfx-content-manifest';
$v2 = '/tmp/mbfx-content-manifest-v2';
foreach ( array( $v1, $v2 ) as $dir ) {
	if ( 0 !== strpos( $dir, '/tmp/mbfx-' ) || ! is_file( $dir . '/news.manifest.json' ) || ! is_file( $dir . '/references.manifest.json' ) ) {
		echo "HATA: sahte içerik manifesti yok ({$dir}); önce fixture-db.php manifest.\n";
		return;
	}
}

/** Gerçek audit sink'i sarar: seçilen olaylar GERÇEKTEN yazılır, sonra false döner. */
class MBFX3_Sink implements MaviBelge_Core_Import_Audit_Sink {
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

$results = array();
$t       = function ( $label, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array( $label, (bool) $ok, (string) $detail );
};

$mk = function ( $dir, $sink = null ) {
	$repo  = new MaviBelge_Core_Import_WordPress_Target_Repository();
	$sink  = null === $sink ? new MaviBelge_Core_Import_WP_Audit_Sink() : $sink;
	$store = new MaviBelge_Core_Import_Wpdb_Run_Store();
	$o     = new stdClass();
	$o->repo     = $repo;
	$o->apply    = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $repo, $dir ), new MaviBelge_Core_Import_WordPress_Target_Writer(), new MaviBelge_Core_Import_Wpdb_Transaction(), $store, $sink );
	$o->rollback = new MaviBelge_Core_Import_Rollback_Service( $repo, new MaviBelge_Core_Import_WordPress_Target_Writer(), new MaviBelge_Core_Import_Wpdb_Transaction(), $store, $sink );
	$o->store    = $store;
	return $o;
};
$apply = function ( $dir, $batch = null, $svc = null ) use ( $mk ) {
	$s = null === $svc ? $mk( $dir ) : $svc;
	$p = $s->apply->preview( 'content' );
	return $s->apply->apply( 'content', (string) $p['plan_digest'], $batch, get_current_user_id() );
};
$rollback = function ( $uid, $batch = null, $svc = null ) use ( $mk, $v1 ) {
	$s = null === $svc ? $mk( $v1 ) : $svc;
	$p = $s->rollback->preview( $uid );
	return $s->rollback->rollback( $uid, (string) $p['rollback_digest'], $batch );
};
$store = new MaviBelge_Core_Import_Wpdb_Run_Store();
$plan  = function ( $dir ) use ( $mk ) {
	$p = $mk( $dir )->apply->preview( 'content' );
	$o = $p['summary']['operations'];
	return array( 'applicable' => $p['summary']['applicable'], 'eligible' => $p['eligible'], 'create' => $o['create'], 'update' => $o['update'], 'unchanged' => $o['unchanged'], 'conflict' => $o['conflict'], 'blocked' => $o['blocked'], 'invalid' => $o['invalid'], 'total' => $p['summary']['total'], 'by_type' => $p['summary']['by_type'], 'digest' => $p['plan_digest'], 'entries' => $p['plan']['entries'] );
};
$brief = function ( array $p ) {
	unset( $p['entries'] );
	return wp_json_encode( $p );
};
$blockerCodes = function ( $uid ) use ( $mk, $v1 ) {
	$pv = $mk( $v1 )->rollback->preview( $uid );
	return array_map(
		function ( $b ) {
			return $b['code'];
		},
		$pv['blockers']
	);
};
$auditEvents = function ( $runId ) use ( $wpdb ) {
	return $wpdb->get_col( $wpdb->prepare( "SELECT event_type FROM {$wpdb->prefix}mb_audit_log WHERE object_type = 'mb_import_run' AND object_id = %d ORDER BY id", $runId ) );
};
$runStatus = function ( $uid ) use ( $store ) {
	$r = $store->get_run( (string) $uid );
	return is_array( $r ) ? $r['status'] : null;
};
$zzIds = function ( $status = null ) use ( $wpdb ) {
	$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('mb_haber','mb_referans') AND ( post_title LIKE %s OR post_title LIKE %s OR post_title LIKE %s )";
	$arg = array( $wpdb->esc_like( 'TEST ' ) . '%', $wpdb->esc_like( 'ZZ Test' ) . '%', $wpdb->esc_like( 'Import ' ) . '%' );
	if ( null !== $status ) {
		$sql  .= ' AND post_status = %s';
		$arg[] = $status;
	}
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, $arg ) ) );
};
$byMarker = function ( $sourceKey ) {
	$ids = get_posts(
		array(
			'post_type'      => array( 'mb_haber', 'mb_referans' ),
			'post_status'    => 'any',
			'meta_key'       => '_mb_import_source_key',
			'meta_value'     => $sourceKey,
			'fields'         => 'ids',
			'posts_per_page' => 2,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		)
	);
	return empty( $ids ) ? null : get_post( (int) $ids[0] );
};
$termIdOf = function ( $slug ) {
	$term = get_term_by( 'slug', $slug, 'mb_haber_turu' );
	return $term instanceof WP_Term ? (int) $term->term_id : 0;
};
$termIdsOf = function ( $postId ) {
	$ids = wp_get_object_terms( $postId, 'mb_haber_turu', array( 'fields' => 'ids' ) );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
};
$fresh = function ( $id ) {
	clean_post_cache( $id );
	return get_post( $id );
};
$sqlPost = function ( $id, array $data ) use ( $wpdb ) {
	$wpdb->update( $wpdb->posts, $data, array( 'ID' => $id ) );
	clean_post_cache( $id );
};
$emptyTrash = function () use ( $zzIds ) {
	$n = 0;
	foreach ( $zzIds( 'trash' ) as $id ) {
		wp_delete_post( $id, true );
		$n++;
	}
	return $n;
};
$naturalKey = function ( $type, $sourceKey ) {
	$r = ( new MaviBelge_Core_Import_WordPress_Target_Repository() )->find_target_by_source_key( $type, $sourceKey );
	return wp_json_encode( $r );
};
$attachmentsBefore = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
register_taxonomy( 'mbfx_extra', array( 'mb_haber', 'mb_referans' ), array( 'public' => false ) );

$news = array(
	'news:zz-test-haber-a' => array( 'title' => 'TEST Haber A', 'date' => '2026-01-15 12:00:00', 'type' => 'haber' ),
	'news:zz-test-haber-b' => array( 'title' => 'TEST Duyuru B', 'date' => '2026-02-20 12:00:00', 'type' => 'duyuru' ),
	'news:zz-test-haber-c' => array( 'title' => 'TEST Haber C', 'date' => '2025-12-31 12:00:00', 'type' => 'haber' ),
);
$refs = array( 'reference:zz-test-ref-a' => 1, 'reference:zz-test-ref-b' => 2, 'reference:zz-test-ref-c' => 3 );

/* ===== 0) Başlangıç ===== */
$t( 'başlangıç: mb_haber_turu ve mb_haber/mb_referans kayıtlı; fixture temiz (zz-test içerik yok)',
	taxonomy_exists( 'mb_haber_turu' ) && post_type_exists( 'mb_haber' ) && post_type_exists( 'mb_referans' ) && array() === $zzIds() );
$removed = 0;
foreach ( array( 'haber', 'duyuru' ) as $slug ) {
	$term = get_term_by( 'slug', $slug, 'mb_haber_turu' );
	if ( $term instanceof WP_Term ) {
		wp_delete_term( $term->term_id, 'mb_haber_turu' );
		$removed++;
	}
}
$t( 'başlangıç: fixture\'da iki kontrollü tür terimi yok (varsa fixture kopyasından silindi)', 0 === $termIdOf( 'haber' ) && 0 === $termIdOf( 'duyuru' ), 'silinen=' . $removed );

/* ===== 1) Terimler yokken: blocked_dependency, apply reddedilir ===== */
$p0 = $plan( $v1 );
$r0 = $apply( $v1 );
$t( 'terimler YOKKEN: 3 haber blocked_dependency + 3 referans create; applicable=false; apply plan_not_applicable; hiçbir post yazılmadı',
	3 === $p0['blocked'] && 3 === $p0['create'] && false === $p0['applicable'] && false === $p0['eligible'] && 'plan_not_applicable' === $r0['error_code'] && array() === $zzIds(), $brief( $p0 ) );

/* ===== 2) Betik iki kontrollü terimi KENDİ oluşturur ===== */
$tHaber  = wp_insert_term( 'Haber', 'mb_haber_turu', array( 'slug' => 'haber' ) );
$tDuyuru = wp_insert_term( 'Duyuru', 'mb_haber_turu', array( 'slug' => 'duyuru' ) );
$idHaber = is_array( $tHaber ) ? (int) $tHaber['term_id'] : 0;
$idDuyuru = is_array( $tDuyuru ) ? (int) $tDuyuru['term_id'] : 0;
$t( 'iki kontrollü mb_haber_turu terimi fixture\'da wp_insert_term ile oluşturuldu (haber, duyuru)', $idHaber > 0 && $idDuyuru > 0 && $idHaber === $termIdOf( 'haber' ) && $idDuyuru === $termIdOf( 'duyuru' ), wp_json_encode( array( $tHaber, $tDuyuru ) ) );
$dry = $mk( $v1 )->repo;
$t( 'repository: resolve_news_type_term_id salt okunur çözümler (haber/duyuru -> {id,type_verified}); bilinmeyen/başka taksonomi -> null',
	array( 'id' => $idHaber, 'type_verified' => true ) === $dry->resolve_news_type_term_id( 'haber' ) && array( 'id' => $idDuyuru, 'type_verified' => true ) === $dry->resolve_news_type_term_id( 'duyuru' ) && null === $dry->resolve_news_type_term_id( 'etkinlik' ) && null === $dry->resolve_news_type_term_id( 'category' ) );

/* ===== 3) dry-run -> apply -> readback ===== */
$p1 = $plan( $v1 );
$t( 'dry-run: 6 create (3 haber + 3 referans), applicable, eligible, conflict=0, by_type news=3/reference=3, 64-hex digest',
	6 === $p1['create'] && true === $p1['applicable'] && true === $p1['eligible'] && 0 === $p1['conflict'] && 0 === $p1['blocked'] && 3 === $p1['by_type']['news'] && 3 === $p1['by_type']['reference'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $p1['digest'] ), $brief( $p1 ) );
$postCountBefore = count( $zzIds() );
$a1 = $apply( $v1 );
$t( 'apply content: completed, 6 item, tek batch', true === $a1['ok'] && 'completed' === $a1['status'] && 6 === $a1['committed_items'] && 1 === $a1['committed_batches'], wp_json_encode( $a1 ) );
$okNews = true;
$detail = '';
foreach ( $news as $sk => $exp ) {
	$p = $byMarker( $sk );
	$slug = substr( $sk, 5 );
	$src  = json_decode( file_get_contents( $v1 . '/news.manifest.json' ), true );
	$rec  = null;
	foreach ( $src['records'] as $r ) {
		if ( $r['source_key'] === $sk ) {
			$rec = $r;
		}
	}
	$row = $p instanceof WP_Post
		&& 'mb_haber' === $p->post_type && 'draft' === $p->post_status && $slug === $p->post_name && $exp['title'] === $p->post_title
		&& $rec['body'] === $p->post_content && $rec['summary'] === $p->post_excerpt
		&& $exp['date'] === $p->post_date && '0000-00-00 00:00:00' === $p->post_date_gmt
		&& 'in_review' === get_post_meta( $p->ID, '_mb_approval_status', true ) && $sk === get_post_meta( $p->ID, '_mb_import_source_key', true ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) get_post_meta( $p->ID, '_mb_last_applied_hash', true ) )
		&& array( 'haber' === $exp['type'] ? $idHaber : $idDuyuru ) === $termIdsOf( $p->ID );
	if ( ! $row ) {
		$okNews = false;
		$detail .= $sk . ':' . ( $p instanceof WP_Post ? wp_json_encode( array( $p->post_status, $p->post_name, $p->post_date, $p->post_date_gmt, $p->post_content === $rec['body'], $termIdsOf( $p->ID ) ) ) : 'yok' ) . ' ';
	}
}
$t( 'readback (gerçek WP): haber DRAFT, post_name=slug, başlık, HAM içerik=body, özet, post_date "Y-m-d 12:00:00" ve post_date_gmt SIFIR (taslak açık tarihi korudu), in_review, marker+hash, doğru tür terimi', $okNews, $detail );
$okRefs = true;
$detail = '';
foreach ( $refs as $sk => $order ) {
	$p = $byMarker( $sk );
	$row = $p instanceof WP_Post && 'mb_referans' === $p->post_type && 'draft' === $p->post_status && substr( $sk, 10 ) === $p->post_name
		&& 'representative' === get_post_meta( $p->ID, '_mb_reference_status', true ) && 'active' === get_post_meta( $p->ID, '_mb_record_status', true ) && (string) $order === (string) get_post_meta( $p->ID, '_mb_sort_order', true )
		&& '' === get_post_meta( $p->ID, '_mb_website_url', true ) && '0' === (string) get_post_meta( $p->ID, '_mb_logo_attachment_id', true ) && metadata_exists( 'post', $p->ID, '_mb_website_url' ) && metadata_exists( 'post', $p->ID, '_mb_logo_attachment_id' )
		&& $sk === get_post_meta( $p->ID, '_mb_import_source_key', true ) && 0 === (int) $p->menu_order;
	if ( ! $row ) {
		$okRefs = false;
		$detail .= $sk . ' ';
	}
}
$t( 'readback (gerçek WP): referans DRAFT, post_name=slug, temsili/aktif, sort_order 1/2/3, website_url "" ve logo 0 (meta VAR), marker', $okRefs, $detail );
$t( 'hiçbir içerik yayınlanmadı (publish=0, in_review dışı onay yok); ek/medya oluşmadı; 6 post',
	0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('mb_haber','mb_referans') AND post_status = 'publish'" ) && count( $zzIds() ) === $postCountBefore + 6 && $attachmentsBefore === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) );
$runA = $store->get_run( $a1['run_uid'] );
$auditRows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mb_audit_log WHERE object_type = 'mb_import_run' AND object_id = %d", $runA['id'] ), ARRAY_A );
$t( 'audit: started -> batch_committed -> completed; audit içeriği haber gövdesi/başlığı TAŞIMAZ (yalnız alan adları)',
	array( 'import_run_started', 'import_batch_committed', 'import_run_completed' ) === $auditEvents( $runA['id'] ) && false === strpos( wp_json_encode( $auditRows ), 'gövdesi' ) && false === strpos( wp_json_encode( $auditRows ), 'TEST Haber' ), wp_json_encode( $auditEvents( $runA['id'] ) ) );

/* ===== 4) noop / idempotent ===== */
$p2 = $plan( $v1 );
$runsBefore = count( $store->list_runs( 50 ) );
$a2 = $apply( $v1 );
$t( 'idempotent: dry-run 6 unchanged (applicable, 0 yazma); ikinci apply noop; yeni run YOK', 6 === $p2['unchanged'] && true === $p2['applicable'] && 0 === $p2['create'] + $p2['update'] && 'noop' === $a2['status'] && count( $store->list_runs( 50 ) ) === $runsBefore, $brief( $p2 ) . ' ' . wp_json_encode( $a2 ) );

/* ===== 5) rollback: çöp, marker/terim temizliği, post_name serbest ===== */
$ids1 = array();
foreach ( array_merge( array_keys( $news ), array_keys( $refs ) ) as $sk ) {
	$p = $byMarker( $sk );
	$ids1[ $sk ] = $p instanceof WP_Post ? $p->ID : 0;
}
$pvRb = $mk( $v1 )->rollback->preview( $a1['run_uid'] );
$rb1  = $rollback( $a1['run_uid'] );
$t( 'rollback önizleme: 6 bekleyen item, engel yok; rollback rolled_back (6 item)', 6 === $pvRb['items_pending'] && array() === $pvRb['blockers'] && true === $rb1['ok'] && 'rolled_back' === $rb1['status'] && 6 === $rb1['rolled_back_items'], wp_json_encode( array( $pvRb['blockers'], $rb1 ) ) );
$shellOk = true;
$detail  = '';
foreach ( $ids1 as $sk => $id ) {
	$p      = $fresh( $id );
	$slug   = 0 === strpos( $sk, 'news:' ) ? substr( $sk, 5 ) : substr( $sk, 10 );
	$keys   = 0 === strpos( $sk, 'news:' ) ? MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META['news'] : MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META['reference'];
	$left   = 0;
	foreach ( $keys as $k ) {
		$left += metadata_exists( 'post', $id, $k ) ? 1 : 0;
	}
	$desired = get_post_meta( $id, '_wp_desired_post_slug', true );
	if ( ! ( $p instanceof WP_Post ) || 'trash' !== $p->post_status || $slug === $p->post_name || $slug === $desired || 0 !== $left || array() !== $termIdsOf( $id ) ) {
		$shellOk = false;
		$detail .= $sk . ':' . ( $p instanceof WP_Post ? wp_json_encode( array( $p->post_status, $p->post_name, $desired, $left, $termIdsOf( $id ) ) ) : 'silindi' ) . ' ';
	}
}
$t( 'rollback sonrası (gerçek WP): 6 post ÇÖPTE (kalıcı silme YOK), yönetilen meta/marker ve haber türü ilişkisi temizlendi, post_name VE _wp_desired_post_slug slug\'ı TUTMUYOR', $shellOk, $detail );
$nkOk = true;
foreach ( array_merge( array_keys( $news ), array_keys( $refs ) ) as $sk ) {
	$type = 0 === strpos( $sk, 'news:' ) ? 'news' : 'reference';
	if ( '{"target_found":false,"natural_key":"none"}' !== $naturalKey( $type, $sk ) ) {
		$nkOk = false;
	}
}
$t( 'rollback sonrası doğal anahtar sorgusu (çöp dahil) 6 kaydın hepsi için "none" döner', $nkOk );
$p3 = $plan( $v1 );
$t( 'rollback -> dry-run: 6 create, applicable=true, conflict=0', 6 === $p3['create'] && true === $p3['applicable'] && 0 === $p3['conflict'], $brief( $p3 ) );
$a3 = $apply( $v1 );
$slugOk = true;
foreach ( array_merge( array_keys( $news ), array_keys( $refs ) ) as $sk ) {
	$p = $byMarker( $sk );
	$slug = 0 === strpos( $sk, 'news:' ) ? substr( $sk, 5 ) : substr( $sk, 10 );
	if ( ! ( $p instanceof WP_Post ) || $slug !== $p->post_name || 'draft' !== $p->post_status || $p->ID === $ids1[ $sk ] ) {
		$slugOk = false;
	}
}
$t( 'aynı manifest yeniden apply -> completed; YENİ 6 post, post_name TAM slug (-2 soneki YOK; slug serbest kaldığının kanıtı)', 'completed' === $a3['status'] && true === $slugOk, wp_json_encode( $a3 ) );

/* ===== 6) güncelleme (v2 manifest) + geri alma ===== */
$pU = $plan( $v2 );
$ent = array();
foreach ( $pU['entries'] as $e ) {
	$ent[ $e['source_key'] ] = $e;
}
$t( 'v2 dry-run: haber-a update(content,excerpt), haber-c update(published_on,news_type_term_id), referans a/b update(sort_order), diğerleri unchanged; eligible',
	'update' === $ent['news:zz-test-haber-a']['decision'] && array( 'content', 'excerpt' ) === $ent['news:zz-test-haber-a']['changed_fields'] && 'update' === $ent['news:zz-test-haber-c']['decision'] && array( 'published_on', 'news_type_term_id' ) === $ent['news:zz-test-haber-c']['changed_fields']
	&& 'unchanged' === $ent['news:zz-test-haber-b']['decision'] && array( 'sort_order' ) === $ent['reference:zz-test-ref-a']['changed_fields'] && 'update' === $ent['reference:zz-test-ref-b']['decision'] && 'unchanged' === $ent['reference:zz-test-ref-c']['decision'] && true === $pU['eligible'], $brief( $pU ) );
$pa = $byMarker( 'news:zz-test-haber-a' );
$pc = $byMarker( 'news:zz-test-haber-c' );
$aU = $apply( $v2 );
$paU = $fresh( $pa->ID );
$pcU = $fresh( $pc->ID );
$t( 'v2 apply (gerçek WP): aynı post ID\'leri güncellendi (yeni post yok), içerik/özet güncel, post_date KORUNDU (edit_date; yalnız içerik değişse de tarih "şimdi"ye sıfırlanmadı) ve post_date_gmt sıfır, DRAFT kaldı, tür terimi duyuru, tarih 2026-03-01 12:00:00',
	'completed' === $aU['status'] && 4 === $aU['committed_items'] && 'Güncellenmiş sahte gövde.' === $paU->post_content && 'Güncellenmiş özet.' === $paU->post_excerpt && '2026-01-15 12:00:00' === $paU->post_date && '0000-00-00 00:00:00' === $paU->post_date_gmt && 'draft' === $paU->post_status
	&& '2026-03-01 12:00:00' === $pcU->post_date && '0000-00-00 00:00:00' === $pcU->post_date_gmt && array( $idDuyuru ) === $termIdsOf( $pc->ID ) && 'draft' === $pcU->post_status && 'zz-test-haber-a' === $paU->post_name, wp_json_encode( array( $aU['status'], $paU->post_date, $paU->post_date_gmt, $pcU->post_date, $termIdsOf( $pc->ID ) ) ) );
$pRefA = $byMarker( 'reference:zz-test-ref-a' );
$pRefB = $byMarker( 'reference:zz-test-ref-b' );
$t( 'v2 apply: referans sıralaması güncellendi (ref-b=1, ref-a=2); v2 planı yeniden unchanged', '2' === (string) get_post_meta( $pRefA->ID, '_mb_sort_order', true ) && '1' === (string) get_post_meta( $pRefB->ID, '_mb_sort_order', true ) && 6 === $plan( $v2 )['unchanged'] );
$rbU = $rollback( $aU['run_uid'] );
$paR = $fresh( $pa->ID );
$pcR = $fresh( $pc->ID );
$srcNews = json_decode( file_get_contents( $v1 . '/news.manifest.json' ), true );
$t( 'v2 rollback: eski yönetilen alanlar geri yazıldı (gövde, özet, tarih, tür terimi haber, sıralama 1/2); post_date sıfırlanmadı; v1 planı yeniden 6 unchanged',
	true === $rbU['ok'] && 'rolled_back' === $rbU['status'] && $srcNews['records'][0]['body'] === $paR->post_content && '2026-01-15 12:00:00' === $paR->post_date && '2025-12-31 12:00:00' === $pcR->post_date && array( $idHaber ) === $termIdsOf( $pc->ID )
	&& '1' === (string) get_post_meta( $pRefA->ID, '_mb_sort_order', true ) && '2' === (string) get_post_meta( $pRefB->ID, '_mb_sort_order', true ) && 6 === $plan( $v1 )['unchanged'], wp_json_encode( array( $rbU['error_code'], $paR->post_date, $pcR->post_date ) ) );

/* ===== 7) drift koruması ===== */
$trashedNow = function () use ( $zzIds ) {
	return count( $zzIds( 'trash' ) );
};
$baseTrash = $trashedNow();
$driftCase = function ( $label, callable $mutate, callable $restore, callable $stillIntact ) use ( $t, $rollback, $blockerCodes, $a3, $trashedNow, $baseTrash ) {
	$mutate();
	$codes = $blockerCodes( $a3['run_uid'] );
	$r     = $rollback( $a3['run_uid'] );
	$t( $label, in_array( 'drift_detected', $codes, true ) && false === $r['ok'] && 'drift_detected' === $r['error_code'] && $trashedNow() === $baseTrash && $stillIntact(), wp_json_encode( array( $codes, $r['error_code'], $trashedNow(), $baseTrash ) ) );
	$restore();
	return $r;
};
$hid = $byMarker( 'news:zz-test-haber-a' )->ID;
$rid = $byMarker( 'reference:zz-test-ref-a' )->ID;
$driftCase( 'drift: haberdeki yönetilmeyen SEO meta -> drift_detected, hiçbir post çöpe gitmez, meta korunur',
	function () use ( $hid ) {
		add_post_meta( $hid, '_yoast_wpseo_title', 'SEO başlığı', true );
	},
	function () use ( $hid ) {
		delete_post_meta( $hid, '_yoast_wpseo_title' );
	},
	function () use ( $hid ) {
		return 'SEO başlığı' === get_post_meta( $hid, '_yoast_wpseo_title', true );
	}
);
$extra = wp_insert_term( 'Ekstra', 'mbfx_extra' );
$driftCase( 'drift: haberdeki yönetilmeyen taksonomi (mbfx_extra) ilişkisi -> drift_detected, çöpe gitmez',
	function () use ( $hid, $extra ) {
		wp_set_object_terms( $hid, array( (int) $extra['term_id'] ), 'mbfx_extra' );
	},
	function () use ( $hid ) {
		wp_set_object_terms( $hid, array(), 'mbfx_extra' );
	},
	function () use ( $hid ) {
		return 1 === count( wp_get_object_terms( $hid, 'mbfx_extra', array( 'fields' => 'ids' ) ) );
	}
);
$driftCase( 'drift: menu_order (yönetilmeyen çekirdek alan) -> drift_detected, çöpe gitmez',
	function () use ( $sqlPost, $hid ) {
		$sqlPost( $hid, array( 'menu_order' => 7 ) );
	},
	function () use ( $sqlPost, $hid ) {
		$sqlPost( $hid, array( 'menu_order' => 0 ) );
	},
	function () use ( $fresh, $hid ) {
		return 7 === (int) $fresh( $hid )->menu_order;
	}
);
$driftCase( 'drift: haber durumu draft -> publish (yönetilmeyen durum) -> drift_detected, çöpe gitmez',
	function () use ( $sqlPost, $hid ) {
		$sqlPost( $hid, array( 'post_status' => 'publish' ) );
	},
	function () use ( $sqlPost, $hid ) {
		$sqlPost( $hid, array( 'post_status' => 'draft' ) );
	},
	function () use ( $fresh, $hid ) {
		return 'publish' === $fresh( $hid )->post_status;
	}
);
$driftCase( 'drift: YÖNETİLEN alan post_content editör tarafından değiştirildi -> hash uyuşmazlığı drift_detected; editör metni korunur, çöpe gitmez',
	function () use ( $sqlPost, $hid ) {
		$sqlPost( $hid, array( 'post_content' => 'Editör bu haberi yeniden yazdı.' ) );
	},
	function () use ( $hid, $srcNews, $sqlPost ) {
		// Plan içerik düzenlemesini conflict sayar; yalnız burada, yeniden çalıştırmadan önce plan kontrolü yapılır.
		$sqlPost( $hid, array( 'post_content' => $srcNews['records'][0]['body'] ) );
	},
	function () use ( $fresh, $hid ) {
		return 'Editör bu haberi yeniden yazdı.' === $fresh( $hid )->post_content;
	}
);
$sqlPost( $hid, array( 'post_content' => 'Editör düzenlemesi 2' ) );
$pEdit = $plan( $v1 );
$t( 'drift: editör post_content düzenlemesi sonrası dry-run conflict (manual_edit_detected), applicable=false; import editör içeriğini EZMEZ', $pEdit['conflict'] >= 1 && false === $pEdit['applicable'] && 'Editör düzenlemesi 2' === $fresh( $hid )->post_content, $brief( $pEdit ) );
$sqlPost( $hid, array( 'post_content' => $srcNews['records'][0]['body'] ) );
$driftCase( 'drift: editör haberi onayladı (_mb_approval_status=approved) -> drift_detected, çöpe gitmez',
	function () use ( $wpdb, $hid ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => 'approved' ), array( 'post_id' => $hid, 'meta_key' => '_mb_approval_status' ) );
		wp_cache_delete( $hid, 'post_meta' );
	},
	function () use ( $wpdb, $hid ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => 'in_review' ), array( 'post_id' => $hid, 'meta_key' => '_mb_approval_status' ) );
		wp_cache_delete( $hid, 'post_meta' );
	},
	function () use ( $hid ) {
		return 'approved' === get_post_meta( $hid, '_mb_approval_status', true );
	}
);
$driftCase( 'drift: referansa elle post_content (referans için YÖNETİLMEYEN alan) -> drift_detected, çöpe gitmez',
	function () use ( $sqlPost, $rid ) {
		$sqlPost( $rid, array( 'post_content' => 'Referansa elle eklenen içerik' ) );
	},
	function () use ( $sqlPost, $rid ) {
		$sqlPost( $rid, array( 'post_content' => '' ) );
	},
	function () use ( $fresh, $rid ) {
		return 'Referansa elle eklenen içerik' === $fresh( $rid )->post_content;
	}
);
$driftCase( 'drift: referans pasife alındı (_mb_record_status=passive) -> drift_detected, çöpe gitmez',
	function () use ( $rid ) {
		update_post_meta( $rid, '_mb_record_status', 'passive' );
	},
	function () use ( $rid ) {
		update_post_meta( $rid, '_mb_record_status', 'active' );
	},
	function () use ( $rid ) {
		return 'passive' === get_post_meta( $rid, '_mb_record_status', true );
	}
);
$t( 'drift testleri sonrası fixture yeniden temiz: v1 planı 6 unchanged', 6 === $plan( $v1 )['unchanged'], $brief( $plan( $v1 ) ) );

/* ===== 8) kullanıcının çöp/taslak kaydı HÂLÂ conflict ===== */
$rbClean = $rollback( $a3['run_uid'] );
$t( 'değişiklik yok: temiz rollback (drift geri alındıktan sonra) çalışır', true === $rbClean['ok'] && 'rolled_back' === $runStatus( $a3['run_uid'] ), wp_json_encode( $rbClean ) );
$userTrash = wp_insert_post( wp_slash( array( 'post_type' => 'mb_haber', 'post_title' => 'TEST Kullanıcı çöp haber', 'post_name' => 'zz-test-haber-b', 'post_status' => 'draft' ) ) );
wp_trash_post( $userTrash );
$utp = $fresh( $userTrash );
$pUser = $plan( $v1 );
$r = $apply( $v1 );
$t( 'kullanıcıya ait ÇÖPTEKİ haber (post_name "<slug>__trashed", _wp_desired_post_slug=slug) HÂLÂ conflict üretir; plan applicable=false; apply plan_not_applicable',
	'zz-test-haber-b__trashed' === $utp->post_name && 'zz-test-haber-b' === get_post_meta( $userTrash, '_wp_desired_post_slug', true ) && $pUser['conflict'] >= 1 && false === $pUser['applicable'] && 'plan_not_applicable' === $r['error_code'], $brief( $pUser ) . ' ' . $utp->post_name );
wp_delete_post( $userTrash, true );
$userDraft = wp_insert_post( wp_slash( array( 'post_type' => 'mb_referans', 'post_title' => 'TEST Kullanıcı referans', 'post_name' => 'zz-test-ref-c', 'post_status' => 'draft' ) ) );
$pUser2 = $plan( $v1 );
$t( 'kullanıcıya ait TASLAK referans aynı post_name\'de -> conflict, applicable=false', $pUser2['conflict'] >= 1 && false === $pUser2['applicable'], $brief( $pUser2 ) );
wp_trash_post( $userDraft );
$pUser3 = $plan( $v1 );
$t( 'kullanıcı referansı çöpe atılsa da (slug artık _wp_desired_post_slug\'ta) HÂLÂ conflict', $pUser3['conflict'] >= 1 && false === $pUser3['applicable'], $brief( $pUser3 ) );
wp_delete_post( $userDraft, true );
$pClean = $plan( $v1 );
$t( 'kullanıcı kayıtları silinince plan yeniden temiz (6 create, applicable, conflict=0)', 6 === $pClean['create'] && true === $pClean['applicable'] && 0 === $pClean['conflict'], $brief( $pClean ) );

/* ===== 9) run durumu + audit atomikliği (content aşaması, gerçek DB) ===== */
$sink = new MBFX3_Sink();
$sink->failAfterRecord = array( 'import_run_completed' );
$rA   = $apply( $v1, null, $mk( $v1, $sink ) );
$runA2 = $store->get_run( (string) $rA['run_uid'] );
$evA  = is_array( $runA2 ) ? $auditEvents( $runA2['id'] ) : array();
$t( 'atomiklik (gerçek DB): run_completed audit hatası -> content run\'ı rollback_required (running/completed DEĞİL), completed audit satırı geri alındı, run_failed var',
	false === $rA['ok'] && 'finalization_failed' === $rA['error_code'] && 'rollback_required' === $rA['status'] && is_array( $runA2 ) && 'rollback_required' === $runA2['status'] && ! in_array( 'import_run_completed', $evA, true ) && in_array( 'import_run_failed', $evA, true ), wp_json_encode( array( $rA['status'], $evA ) ) );
$rbA = $rollback( $rA['run_uid'] );
$t( 'atomiklik: rollback_required content run\'ı doğrulanmış kayıtlarla geri alınır (rolled_back)', true === $rbA['ok'] && 'rolled_back' === $runStatus( $rA['run_uid'] ), wp_json_encode( $rbA ) );
// Batch atomikliği: batch=2, 2. batch\'te yazma hatası simüle edilemez (üretim koduna kanca yok); bunun yerine
// TOCTOU: plan sonrası beliren kullanıcı kaydı yazmayı durdurur, hiçbir yarım içerik kalmaz.
$svcT = $mk( $v1 );
$pvT  = $svcT->apply->preview( 'content' );
$intruder = wp_insert_post( wp_slash( array( 'post_type' => 'mb_haber', 'post_title' => 'TEST Arada eklenen', 'post_name' => 'zz-test-haber-c', 'post_status' => 'draft' ) ) );
$rT   = $svcT->apply->apply( 'content', (string) $pvT['plan_digest'], 2, get_current_user_id() );
$t( 'TOCTOU/onay: plan sonrası beliren aynı slug\'lı kullanıcı postu -> apply reddedilir, yeni post YAZILMAZ', false === $rT['ok'] && in_array( $rT['error_code'], array( 'confirmation_mismatch', 'plan_not_applicable', 'toctou_drift' ), true ) && null === $byMarker( 'news:zz-test-haber-a' ), wp_json_encode( array( $rT['error_code'] ) ) );
wp_delete_post( $intruder, true );

/* ===== 10) temizlik ===== */
$emptyTrash();
foreach ( array( 'haber', 'duyuru' ) as $slug ) {
	$term = get_term_by( 'slug', $slug, 'mb_haber_turu' );
	if ( $term instanceof WP_Term ) {
		wp_delete_term( $term->term_id, 'mb_haber_turu' );
	}
}
$t( 'temizlik: fixture\'da zz-test içerik ve iki tür terimi kalmadı; medya değişmedi; gerçek data/content manifestleri kullanılmadı',
	array() === $zzIds() && 0 === $termIdOf( 'haber' ) && 0 === $termIdOf( 'duyuru' ) && $attachmentsBefore === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) && 0 === strpos( $v1, '/tmp/mbfx-' ) );

$pass = 0;
foreach ( $results as $r ) {
	echo ( $r[1] ? 'PASS  ' : 'FAIL  ' ) . $r[0] . ( '' !== $r[2] && ! $r[1] ? '  [' . $r[2] . ']' : '' ) . "\n";
	$pass += $r[1] ? 1 : 0;
}
echo "\n{$pass}/" . count( $results ) . " Faz 7 içerik aktarımı WordPress runtime testi geçti.\n";
