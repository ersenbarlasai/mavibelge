<?php
/**
 * Faz 6B4 — GERÇEK WordPress 6.9.9 + PHP 7.3.33 + MariaDB runtime kabul kapıları: admin görsel eşleme, aşama önizleme,
 * kesintiye dayanıklı (resumable) apply/rollback, hata enjeksiyonu.
 *
 *   wp --require=fixture-env.php --user=mbadmin eval-file admin-import-runtime.php <faz> [argümanlar]
 *
 * Fazlar (her biri TAZE fixture DB'de, ayrı PHP sürecinde çalışır):
 *   map              : GERÇEK manifest + 9 GERÇEK image attachment: 23/177 -> map -> 14/0, negatif eşleme, audit, autoload
 *   image-map-atomicity : görsel-map option + audit + commit ATOMİK (audit/begin/write/commit/rollback hata enjeksiyonu, gerçek DB)
 *   stages           : sunucu tarafı aşama sırası + dört aşama çok istekli apply + rollback/reapply (AÇIKÇA SAHTE /tmp/mbfx-b4-manifest)
 *   sector-rollback  : 25 sektör çok istekli apply -> çok istekli rollback -> reapply -> rollback ortasında drift -> kurtarma
 *   small-batches    : dört aşamanın HER BİRİ batch=2 ile çok istekli apply + content rollback (gerçek WordPress)
 *   migration        : run-store sürüm 1 -> 2 idempotent yükseltme (eski satırlar korunur)
 *   faults           : audit/checkpoint/durum/COMMIT hata enjeksiyonu, TOCTOU, manifest/map değişimi, gerçek DB kilidi (paralel istek)
 *   resume-start / resume-advance <N> / resume-final : AYRI PHP süreçleri arasında devam (sayfa kapanması benzetimi)
 *
 * YALNIZ klonlanmış, silinebilir `mbfx_` fixture tablolarında çalışır. Gerçek katalog manifesti YALNIZ salt okunur dry-run
 * ve görsel-map doğrulaması için okunur (asla apply edilmez); bütün yazmalar AÇIKÇA SAHTE fixture manifestiyle yapılır.
 * Test sarmalayıcıları (MBFX4_*) yalnız bu betikte tanımlıdır; üretim koduna test kancası eklenmez. Gerçek attachment
 * dosyaları /tmp altındadır (uploads dizinine YAZILMAZ). Parola/anahtar okunmaz veya yazdırılmaz.
 */

global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	return;
}
$phase = isset( $args[0] ) ? $args[0] : '';
$B4    = '/tmp/mbfx-b4-manifest';
$REAL  = ABSPATH . 'data/content';
$G     = 'MaviBelge_Core_Import_Admin_Gates';
// Faz 12b: referans logo attachment dosyaları GERÇEK uploads dizinine değil geçici bir dizine yazılır (ana uploads farkı 0).
add_filter(
	'upload_dir',
	function ( $d ) {
		$base = '/tmp/mbfx-uploads';
		$sub  = '/mbfx';
		wp_mkdir_p( $base . $sub );
		@chmod( $base, 0777 );
		@chmod( $base . $sub, 0777 );
		return array_merge( $d, array( 'path' => $base . $sub, 'url' => 'http://mbfx.invalid/uploads' . $sub, 'subdir' => $sub, 'basedir' => $base, 'baseurl' => 'http://mbfx.invalid/uploads', 'error' => false ) );
	}
);

/** Gerçek audit sink'i sarar: seçilen olaylar GERÇEKTEN yazılır, sonra false döner (transaction geri almazsa yetim audit kalır). */
class MBFX4_Sink implements MaviBelge_Core_Import_Audit_Sink {
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
/** Gerçek run deposu; seçilen HEDEF durumlara geçiş ve checkpoint yazımı başarısız olabilir. */
class MBFX4_Store extends MaviBelge_Core_Import_Wpdb_Run_Store {
	public $failTo         = array();
	public $failCheckpoint = false;
	public function transition( $runId, $from, $to, array $fields = array() ) {
		if ( in_array( $to, $this->failTo, true ) ) {
			return false;
		}
		return parent::transition( $runId, $from, $to, $fields );
	}
	public function record_checkpoint( $runId, $committedBatches, $committedItems, $expectedBatches = null ) {
		return $this->failCheckpoint ? false : parent::record_checkpoint( $runId, $committedBatches, $committedItems, $expectedBatches );
	}
}
/** Gerçek transaction; N başarılı COMMIT'ten sonra COMMIT GERÇEKTEN geri alınır ve false döner. */
class MBFX4_Tx extends MaviBelge_Core_Import_Wpdb_Transaction {
	public $failCommitAfter = null;
	private $commits        = 0;
	public function commit() {
		if ( null !== $this->failCommitAfter && $this->commits >= $this->failCommitAfter ) {
			parent::rollback();
			return false;
		}
		$this->commits++;
		return parent::commit();
	}
}

$results = array();
/** Sonuç hemen yazdırılır (betik ortada ölse bile kanıt kaybolmaz). */
$t = function ( $label, $ok, $detail = '' ) use ( &$results ) {
	$results[] = (bool) $ok;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== (string) $detail && ! $ok ? '  [' . $detail . ']' : '' ) . "
";
};
$finish = function ( $title ) use ( &$results ) {
	echo "
" . count( array_filter( $results ) ) . '/' . count( $results ) . " {$title} geçti.
";
};
$factory = function ( $dir, array $over = array() ) {
	return new MaviBelge_Core_Import_Runtime_Factory( array_merge( array( 'manifest_dir' => $dir ), $over ) );
};
$svcDirs = array();
$svcOf = function ( $dir, array $over = array() ) use ( $factory, &$svcDirs ) {
	$svc                              = new MaviBelge_Core_Import_Admin_Run_Service( $factory( $dir, $over ) );
	$svcDirs[ spl_object_hash( $svc ) ] = $dir;
	return $svc;
};
$sectors = function () use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'mb_sektor' AND t.slug LIKE %s", $wpdb->esc_like( 'zz-test-' ) . '%' ) );
};
$posts = function ( $type, $status = null ) use ( $wpdb ) {
	// Sahte fixture başlıkları: katalog/haber "TEST ...", SSS "ZZ Test ...", referans "Referans 0N".
	$sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND ( post_title LIKE %s OR post_title LIKE %s OR post_title LIKE %s )";
	$arg = array( $type, $wpdb->esc_like( 'TEST ' ) . '%', $wpdb->esc_like( 'ZZ Test ' ) . '%', $wpdb->esc_like( 'Referans 0' ) . '%' );
	if ( null !== $status ) {
		$sql  .= ' AND post_status = %s';
		$arg[] = $status;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $arg ) );
};
$runRow = function ( $uid ) use ( $wpdb ) {
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mb_import_runs WHERE uid = %s", $uid ), ARRAY_A );
};
$count = function ( $table, $where = '1=1' ) use ( $wpdb ) {
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE {$where}" );
};
$events = function ( $runId ) use ( $wpdb ) {
	return $wpdb->get_col( $wpdb->prepare( "SELECT event_type FROM {$wpdb->prefix}mb_audit_log WHERE object_type = 'mb_import_run' AND object_id = %d ORDER BY id ASC", $runId ) );
};
$phrase = function ( $stage, $digest ) use ( $G ) {
	return (string) $G::apply_phrase( $stage, (string) $digest );
};
/**
 * Faz 12: aşama zinciri pages -> sectors -> ... SUNUCUDA zorunludur. Sektör aşaması başlatılmadan önce (önkoşul karşılanmamışsa)
 * pages aşaması TEMİZ bir servisle (hata enjeksiyonu YOK) tamamlanır; testlerin sektör/hata davranışı değişmez.
 */
$ensurePages = function ( $svc ) use ( &$svcDirs, $svcOf, $phrase ) {
	$key = spl_object_hash( $svc );
	if ( ! isset( $svcDirs[ $key ] ) ) {
		return;
	}
	$clean = $svcOf( $svcDirs[ $key ] );
	if ( true === $clean->prerequisites( 'sectors' )['met'] ) {
		return;
	}
	$p = $clean->preview_stage( 'pages' );
	if ( true !== $p['eligible'] ) {
		return;
	}
	$st = $clean->start_apply( 'pages', (string) $p['plan_digest'], $phrase( 'pages', $p['plan_digest'] ), get_current_user_id() );
	for ( $i = 0; $i < 10 && ! empty( $st['ok'] ) && in_array( $st['status'], array( 'ready', 'paused' ), true ); $i++ ) {
		$st = $clean->advance_apply( $st['run_uid'], $st['checkpoint'], get_current_user_id() );
	}
};
/** Preview + start (doğru onay ifadesiyle). */
$startStage = function ( $svc, $stage ) use ( $phrase, $ensurePages ) {
	if ( 'sectors' === $stage ) {
		$ensurePages( $svc );
	}
	$p = $svc->preview_stage( $stage );
	return array( $p, $svc->start_apply( $stage, (string) $p['plan_digest'], $phrase( $stage, $p['plan_digest'] ), get_current_user_id() ) );
};
/** Her istek YENİ servis grafiğiyle (yeni HTTP isteği benzetimi): paused olduğu sürece ilerletir. */
$driveApply = function ( $dir, $uid, $checkpoint = 0, array $over = array() ) use ( $svcOf ) {
	$log = array();
	for ( $i = 0; $i < 30; $i++ ) {
		$r     = $svcOf( $dir, $over )->advance_apply( $uid, $checkpoint, get_current_user_id() );
		$log[] = $r;
		if ( ! $r['ok'] || 'paused' !== $r['status'] ) {
			break;
		}
		$checkpoint = $r['checkpoint'];
	}
	return $log;
};
$lastOf = function ( array $list ) {
	return end( $list );
};
$seedNewsTerms = function () {
	foreach ( array( 'haber' => 'Haber', 'duyuru' => 'Duyuru' ) as $slug => $name ) {
		if ( ! get_term_by( 'slug', $slug, 'mb_haber_turu' ) ) {
			wp_insert_term( $name, 'mb_haber_turu', array( 'slug' => $slug ) );
		}
	}
};
$pngBytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' );

/* ============================================================================================ */
if ( 'map' === $phase ) {
	// Klon ana test veritabanındaki eski elle-tohumlanmış sektör/içerik kayıtlarını taşır; GERÇEK manifest sayıları BOŞ hedef
	// içindir (23/177): bu fixture klonunda (yalnız mbfx_) import hedefleri temizlenir.
	foreach ( array( 'mb_sektor' ) as $tax ) {
		foreach ( (array) get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) ) as $termId ) {
			wp_delete_term( (int) $termId, $tax );
		}
	}
	foreach ( array( 'mb_yeterlilik', 'mb_ucret', 'mb_haber', 'mb_referans' ) as $type ) {
		foreach ( get_posts( array( 'post_type' => $type, 'post_status' => array_merge( array( 'any' ), array_keys( get_post_stati() ) ), 'numberposts' => -1, 'fields' => 'ids' ) ) as $postId ) {
			wp_delete_post( (int) $postId, true );
		}
	}
	$t( 'hazırlık: fixture klonunda import hedefleri (sektör terimi, yeterlilik/ücret/haber/referans) temizlendi', 0 === count( (array) get_terms( array( 'taxonomy' => 'mb_sektor', 'hide_empty' => false, 'fields' => 'ids' ) ) ) );
	$imgDir = '/tmp/mbfx-b4-images';
	if ( ! is_dir( $imgDir ) ) {
		mkdir( $imgDir, 0777, true );
	}
	$att = array();
	for ( $i = 1; $i <= 10; $i++ ) {
		$file = "{$imgDir}/img-{$i}.png";
		file_put_contents( $file, $pngBytes );
		$att[ $i ] = wp_insert_attachment( array( 'post_title' => "TEST Sektör Görseli {$i}", 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $file );
	}
	$txtFile = "{$imgDir}/not-image.txt";
	file_put_contents( $txtFile, 'düz metin' );
	$txtId  = wp_insert_attachment( array( 'post_title' => 'TEST Metin', 'post_mime_type' => 'text/plain', 'post_status' => 'inherit' ), $txtFile );
	$postId = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'TEST Sayfa', 'post_status' => 'draft' ) );
	$ghost  = wp_insert_attachment( array( 'post_title' => 'TEST Dosyasız', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), '/tmp/mbfx-b4-images/yok.png' );
	$t( 'hazırlık: 10 gerçek image attachment (dosyası okunabilir), 1 text/plain attachment, 1 sayfa, 1 dosyasız attachment', 10 === count( array_filter( $att ) ) && $txtId > 0 && $postId > 0 && $ghost > 0 && 'attachment' === get_post_type( $att[1] ) && is_readable( get_attached_file( $att[1] ) ) && false === is_readable( get_attached_file( $ghost ) ) );
	$fullMap = array( 'makine' => $att[1], 'metalurji' => $att[2], 'metal' => $att[3], 'lojistik' => $att[4], 'enerji' => $att[5], 'cam' => $att[6], 'tekstil' => $att[7], 'insaat' => $att[8], 'maden' => $att[9], 'mermer' => $att[9] );
	$opt     = MaviBelge_Core_Import_Sector_Image_Map::OPTION;
	$mapEvents = function () use ( $wpdb ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mb_audit_log WHERE event_type = 'import_image_map_changed'" );
	};

	$svc0 = $svcOf( $REAL );
	$all0 = $factory( $REAL )->dry_run_service()->run_stage( 'all' )['plan']['summary'];
	$t( 'map YOK, GERÇEK manifest, tam dry-run: total 200, create 23, blocked 177, invalid 0, conflict 0, structurally_valid true, applicable false',
		200 === $all0['total'] && 23 === $all0['operations']['create'] && 177 === $all0['operations']['blocked'] && 0 === $all0['operations']['invalid'] && 0 === $all0['operations']['conflict'] && true === $all0['structurally_valid'] && false === $all0['applicable'], wp_json_encode( $all0 ) );
	$view0 = $svc0->image_map_view();
	$t( 'görünüm (map yok): tam 10 gerekli slug (manifestten), 9 benzersiz kaynak etiketi, etiketlerde dizin YOK, present=false',
		10 === count( $view0['required'] ) && 9 === count( array_unique( array_values( $view0['required'] ) ) ) && false === $view0['present'] && 0 === count( array_filter( $view0['required'], function ( $l ) {
			return false !== strpos( $l, '/' );
		} ) ) && 'real-makine.png' === $view0['required']['makine'] );
	$t( 'görünüm: maden ve mermer aynı kaynak görsel etiketini paylaşır', $view0['required']['maden'] === $view0['required']['mermer'] );

	$bad = array(
		'eksik slug (mermer yok)'            => array_diff_key( $fullMap, array( 'mermer' => 1 ) ),
		'fazla slug (zz-fazla)'              => array_merge( $fullMap, array( 'zz-fazla' => $att[10] ) ),
		'sayfa (attachment değil)'           => array_merge( $fullMap, array( 'makine' => $postId ) ),
		'text/plain attachment'              => array_merge( $fullMap, array( 'makine' => $txtId ) ),
		'var olmayan ID'                     => array_merge( $fullMap, array( 'makine' => 987654 ) ),
		'dosyası okunamayan attachment'      => array_merge( $fullMap, array( 'makine' => $ghost ) ),
		'farklı kaynaklı sektörler aynı ID'  => array_merge( $fullMap, array( 'metal' => $att[1] ) ),
	);
	foreach ( $bad as $label => $map ) {
		$r = $svc0->save_image_map( $map );
		$t( 'map kaydı reddedilir: ' . $label . ' (option ve audit YAZILMAZ)', false === $r['ok'] && array() !== $r['error_codes'] && null === get_option( $opt, null ) && 0 === $mapEvents(), wp_json_encode( $r ) );
	}
	wp_update_post( array( 'ID' => $att[1], 'post_status' => 'trash' ) );
	$r = $svc0->save_image_map( $fullMap );
	$t( 'map kaydı reddedilir: çöpteki attachment', false === $r['ok'] && null === get_option( $opt, null ), wp_json_encode( $r ) );
	wp_update_post( array( 'ID' => $att[1], 'post_status' => 'inherit' ) );

	$ok1 = $svc0->save_image_map( $fullMap );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT autoload, option_value FROM {$wpdb->options} WHERE option_name = %s", $opt ), ARRAY_A );
	$t( 'geçerli map (maden+mermer aynı attachment) kaydedilir: ok, 10 değişen slug, 64-hex digest', true === $ok1['ok'] && 10 === count( $ok1['changed_slugs'] ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $ok1['digest'] ), wp_json_encode( $ok1 ) );
	$t( 'map option autoload=no (veritabanında off/no) ve kapalı zarf (yalnız üç anahtar)', is_array( $row ) && in_array( $row['autoload'], array( 'no', 'off' ), true ) && array( 'manifest_digest', 'mappings', 'schema_version' ) === ( function ( $k ) {
		sort( $k );
		return $k;
	} )( array_keys( maybe_unserialize( $row['option_value'] ) ) ) );
	$auditRow = $wpdb->get_var( "SELECT context FROM {$wpdb->prefix}mb_audit_log WHERE event_type = 'import_image_map_changed' ORDER BY id DESC LIMIT 1" );
	$auditCtx = json_decode( (string) $auditRow, true );
	$t( 'map değişikliği audit\'i: eski/yeni digest + 10 değişen slug adı; mutlak yol, dosya adı veya alan içeriği YOK',
		1 === $mapEvents() && is_array( $auditCtx ) && null === $auditCtx['old_digest'] && $auditCtx['new_digest'] === $ok1['digest'] && 10 === count( $auditCtx['changed_slugs'] ) && false === strpos( (string) $auditRow, '/tmp' ) && false === strpos( (string) $auditRow, 'img-' ) && false === strpos( (string) $auditRow, '.png' ) );
	$ok2 = $svc0->save_image_map( $fullMap );
	$t( 'aynı map yeniden kaydedilirse ok, değişen slug YOK ve yeni audit satırı YAZILMAZ', true === $ok2['ok'] && array() === $ok2['changed_slugs'] && 1 === $mapEvents() );

	$svc1 = $svcOf( $REAL );
	$st   = $factory( $REAL )->image_map_state();
	$sec  = $svc1->preview_stage( 'sectors' );
	$t( 'map TAM: sectors önizlemesi total 14, create 14, blocked 0, invalid 0, conflict 0, structurally_valid, applicable, eligible',
		true === $st['valid'] && 14 === $sec['summary']['total'] && 14 === $sec['summary']['operations']['create'] && 0 === $sec['summary']['operations']['blocked'] && 0 === $sec['summary']['operations']['invalid'] && 0 === $sec['summary']['operations']['conflict']
		&& true === $sec['summary']['structurally_valid'] && true === $sec['summary']['applicable'] && true === $sec['eligible'] && 14 === $sec['writes'], wp_json_encode( $sec['summary'] ) );
	$all1 = $factory( $REAL )->dry_run_service()->run_stage( 'all' )['plan']['summary'];
	$t( 'map TAM, boş hedef: all aşaması hâlâ applicable=false (yeterlilik/ücret önkoşullu): create 33, blocked 167', 33 === $all1['operations']['create'] && 167 === $all1['operations']['blocked'] && false === $all1['applicable'] );

	// map digest'i manifest değişince fail-closed
	$mod = '/tmp/mbfx-b4-manifest-mod';
	if ( ! is_dir( $mod ) ) {
		mkdir( $mod, 0777, true );
	}
	foreach ( glob( $REAL . '/*.manifest.json' ) as $f ) {
		copy( $f, $mod . '/' . basename( $f ) );
	}
	$mf = json_decode( (string) file_get_contents( $mod . '/sectors.manifest.json' ), true );
	foreach ( $mf['records'] as $i => $rec ) {
		if ( 'makine' === $rec['slug'] ) {
			$mf['records'][ $i ]['image'] = 'assets/images/content/degisti.png';
		}
	}
	file_put_contents( $mod . '/sectors.manifest.json', wp_json_encode( $mf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	$stMod = $factory( $mod )->image_map_state();
	$secMod = $factory( $mod )->dry_run_service()->run_stage( 'sectors' )['plan']['summary'];
	$t( 'sektör manifesti değişince eski map fail-closed reddedilir (manifest_digest_mismatch); repository\'ye verilmez: 10 blocked, applicable=false',
		true === $stMod['present'] && false === $stMod['valid'] && array() === $stMod['mappings'] && in_array( 'manifest_digest_mismatch', $stMod['errors'], true ) && 10 === $secMod['operations']['blocked'] && false === $secMod['applicable'], wp_json_encode( array( $stMod['errors'], $secMod['operations'] ) ) );

	// attachment silinince map geçersiz olur
	$keep3 = $att[3];
	wp_delete_attachment( $keep3, true );
	$stDel = $factory( $REAL )->image_map_state();
	$t( 'kayıtlı map\'teki attachment silinirse map geçersiz (attachment_missing:metal) ve dry-run blocked',
		false === $stDel['valid'] && in_array( 'attachment_missing:metal', $stDel['errors'], true ) && false === $factory( $REAL )->dry_run_service()->run_stage( 'sectors' )['plan']['summary']['applicable'], wp_json_encode( $stDel['errors'] ) );
	$newMetal = wp_insert_attachment( array( 'post_title' => 'TEST Yeni Metal', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), "{$imgDir}/img-3.png" );
	$okFix    = $svc0->save_image_map( array_merge( $fullMap, array( 'metal' => $newMetal ) ) );
	$auditRow2 = $wpdb->get_var( "SELECT context FROM {$wpdb->prefix}mb_audit_log WHERE event_type = 'import_image_map_changed' ORDER BY id DESC LIMIT 1" );
	$ctx2      = json_decode( (string) $auditRow2, true );
	$t( 'önceki map geçersizdi (attachment silindi): düzeltilmiş kayıt geçerli SON map yokmuş gibi ele alınır — 10 slug değişti sayılır, old_digest null; ikinci audit satırı yazıldı',
		true === $okFix['ok'] && 10 === count( $okFix['changed_slugs'] ) && 2 === $mapEvents() && null === $ctx2['old_digest'] && $ctx2['new_digest'] === $okFix['digest'], wp_json_encode( $okFix ) );
	$validMap2 = array_merge( $fullMap, array( 'metal' => $newMetal, 'cam' => $att[10] ) );
	$okOne     = $svc0->save_image_map( $validMap2 );
	$ctx3      = json_decode( (string) $wpdb->get_var( "SELECT context FROM {$wpdb->prefix}mb_audit_log WHERE event_type = 'import_image_map_changed' ORDER BY id DESC LIMIT 1" ), true );
	$t( 'geçerli map üzerinde TEK slug (cam) değişirse audit yalnız o slug\'ı ve ESKİ geçerli digest\'i taşır', true === $okOne['ok'] && array( 'cam' ) === $okOne['changed_slugs'] && 3 === $mapEvents() && array( 'cam' ) === $ctx3['changed_slugs'] && $ctx3['old_digest'] === $okFix['digest'] && $ctx3['new_digest'] === $okOne['digest'], wp_json_encode( $okOne ) );

	// açık map ile term-meta çelişkisi
	$termR = wp_insert_term( 'Makine', 'mb_sektor', array( 'slug' => 'makine' ) );
	update_term_meta( $termR['term_id'], '_mb_image_attachment_id', $att[2] );
	$repoF = $factory( $REAL );
	$secC  = $repoF->dry_run_service()->run_stage( 'sectors' );
	$diagCodes = array_map( function ( $d ) {
		return $d['code'] . '|' . $d['source_key'];
	}, $secC['diagnostics'] );
	$t( 'açık map (makine=A) ile mevcut term-meta (B) çelişirse diagnostic sector_image_map_conflict üretilir ve sektör bloklanır (applicable=false)', in_array( 'sector_image_map_conflict|sector:makine', $diagCodes, true ) && false === $secC['plan']['summary']['applicable'], wp_json_encode( $diagCodes ) );
	wp_delete_term( $termR['term_id'], 'mb_sektor' );

	$t( 'bu faz hiçbir katalog/haber/referans kaydı yazmadı: import run tabloları oluşturulmadı veya boş; içerik yok', 0 === $sectors() && 0 === $posts( 'mb_yeterlilik' ) && 0 === $posts( 'mb_ucret' ) );
	foreach ( $att as $id ) {
		wp_delete_attachment( $id, true );
	}
	wp_delete_attachment( $newMetal, true );
	wp_delete_attachment( $txtId, true );
	wp_delete_attachment( $ghost, true );
	wp_delete_post( $postId, true );
	delete_option( $opt );
	foreach ( glob( "{$imgDir}/*" ) as $f ) {
		unlink( $f );
	}
	rmdir( $imgDir );
	foreach ( glob( "{$mod}/*" ) as $f ) {
		unlink( $f );
	}
	rmdir( $mod );
	$t( 'temizlik: geçici görsel dosyaları ve map option\'ı kaldırıldı', ! is_dir( $imgDir ) && ! is_dir( $mod ) && null === get_option( $opt, null ) );
	$finish( 'Faz 6B4 görsel-map WordPress runtime testi' );
	return;
}

/* ============================================================================================ */
if ( 'image-map-atomicity' === $phase ) {
	// Faz 6B4 son kabul düzeltmesi — görsel eşleme option'ı + audit kaydı ATOMİK olmalı. GERÇEK WordPress, GERÇEK audit tablosu.
	foreach ( (array) get_terms( array( 'taxonomy' => 'mb_sektor', 'hide_empty' => false, 'fields' => 'ids' ) ) as $termId ) {
		wp_delete_term( (int) $termId, 'mb_sektor' );
	}
	$imgDir = '/tmp/mbfx-b4-images';
	if ( ! is_dir( $imgDir ) ) {
		mkdir( $imgDir, 0777, true );
	}
	$att = array();
	for ( $i = 1; $i <= 10; $i++ ) {
		$file = "{$imgDir}/img-{$i}.png";
		file_put_contents( $file, $pngBytes );
		$att[ $i ] = wp_insert_attachment( array( 'post_title' => "TEST Sektör Görseli {$i}", 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $file );
	}
	$fullMap = array( 'makine' => $att[1], 'metalurji' => $att[2], 'metal' => $att[3], 'lojistik' => $att[4], 'enerji' => $att[5], 'cam' => $att[6], 'tekstil' => $att[7], 'insaat' => $att[8], 'maden' => $att[9], 'mermer' => $att[9] );
	$opt     = MaviBelge_Core_Import_Sector_Image_Map::OPTION;
	$auditT  = MaviBelge_Core_Audit_Log::table_name();
	$optRow = function () use ( $wpdb, $opt ) {
		return $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $opt ), ARRAY_A );
	};
	$auditRows = function () use ( $wpdb, $auditT ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$auditT}` WHERE event_type = 'import_image_map_changed'" );
	};
	$runTables = function () use ( $wpdb ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('{$wpdb->prefix}mb_import_runs','{$wpdb->prefix}mb_import_run_items','{$wpdb->prefix}mb_import_run_plan_items')" );
	};
	$inTx = function () use ( $wpdb ) {
		return (int) $wpdb->get_var( 'SELECT @@in_transaction' );
	};
	/** Gerçek store; seçilen adım GERÇEKTEN çalıştırılır sonra başarısızlık bildirilir (yetim yazım kalırsa test yakalar). */
	$mkStore = function ( $mode ) {
		return new class( $mode ) extends MaviBelge_Core_Import_Wp_Image_Map_Store {
			private $mode;
			public function __construct( $mode ) {
				parent::__construct();
				$this->mode = $mode;
			}
			public function begin() {
				return 'begin' === $this->mode ? false : parent::begin();
			}
			public function write( array $stored, $exists ) {
				return 'write' === $this->mode ? false : parent::write( $stored, $exists );
			}
			public function audit( array $context ) {
				$ok = parent::audit( $context ); // audit satırı GERÇEKTEN yazılır
				return in_array( $this->mode, array( 'audit', 'audit+rollback' ), true ) ? false : $ok;
			}
			public function commit() {
				if ( 'commit' === $this->mode ) {
					parent::rollback(); // COMMIT gerçekten başarısız oldu (bağlantı geri alındı)
					return false;
				}
				return parent::commit();
			}
			public function rollback() {
				if ( 'audit+rollback' === $this->mode ) {
					parent::rollback();
					return false;
				}
				return parent::rollback();
			}
		};
	};

	// (1) Audit altyapısı hazır değil (tablo düşürüldü): kayıt reddedilir, option oluşmaz, run tablosu kurulmaz.
	$wpdb->query( "DROP TABLE IF EXISTS `{$auditT}`" );
	$r = $svcOf( $REAL )->save_image_map( $fullMap );
	$t( 'audit altyapısı hazır değil: ok=false, sabit kod infrastructure_unavailable; option OLUŞMAZ; audit satırı yok; run tabloları KURULMADI', false === $r['ok'] && array( 'infrastructure_unavailable' ) === $r['error_codes'] && null === $optRow() && 0 === $runTables() && 0 === $inTx(), wp_json_encode( $r ) );
	delete_option( 'mavibelge_core_audit_table_version' );
	MaviBelge_Core_Audit_Log::install();
	$t( 'hazırlık: audit tablosu yeniden kuruldu (install idempotent)', true === MaviBelge_Core_Audit_Log::table_exists() && 0 === $auditRows() );

	// (2) Run tabloları OLMADAN başarılı kayıt: option + 1 audit, autoload no, run tabloları oluşmaz.
	$ok = $svcOf( $REAL )->save_image_map( $fullMap );
	$row = $optRow();
	$t( 'run tabloları YOKKEN geçerli map kaydedilir (option + audit); run/plan_items tabloları KURULMAZ; açık transaction yok', true === $ok['ok'] && 10 === count( $ok['changed_slugs'] ) && null !== $row && in_array( $row['autoload'], array( 'no', 'off' ), true ) && 1 === $auditRows() && 0 === $runTables() && 0 === $inTx(), wp_json_encode( $ok ) );
	$audCtx = json_decode( (string) $wpdb->get_var( "SELECT context FROM `{$auditT}` WHERE event_type = 'import_image_map_changed' ORDER BY id DESC LIMIT 1" ), true );
	$t( 'audit context yalnız old_digest, new_digest, changed_slugs (yol/dosya adı/ID yok)', is_array( $audCtx ) && array( 'changed_slugs', 'new_digest', 'old_digest' ) === ( function ( $k ) {
		sort( $k );
		return $k;
	} )( array_keys( $audCtx ) ) && null === $audCtx['old_digest'] && $audCtx['new_digest'] === $ok['digest'] );
	$noop = $svcOf( $REAL )->save_image_map( $fullMap );
	$t( 'aynı map yeniden: no-op başarı; yeni audit satırı ve DB değişikliği YOK', true === $noop['ok'] && array() === $noop['changed_slugs'] && 1 === $auditRows() && $row === $optRow() );

	// (3) Mevcut option VARKEN audit hatası: audit satırı yazıldı ama false döndü -> option ve audit BİREBİR eski durum.
	$beforeRow = $optRow();
	$changed   = array_merge( $fullMap, array( 'cam' => $att[10] ) );
	$r         = $svcOf( $REAL, array( 'image_map_store' => $mkStore( 'audit' ) ) )->save_image_map( $changed );
	$t( 'mevcut option + audit yazımı false: ok=false audit_failed; option ham değer VE autoload birebir korundu; yetim audit satırı YOK; açık transaction yok',
		false === $r['ok'] && array( 'audit_failed' ) === $r['error_codes'] && $beforeRow === $optRow() && 1 === $auditRows() && 0 === $inTx(), wp_json_encode( array( $r, $auditRows() ) ) );
	$t( 'hata sonrası aynı süreçte get_option() ESKİ gerçek durumu görür (önbellekte geri alınmış yeni değer YOK)', $svcOf( $REAL )->image_map_view()['mappings']['cam'] === $att[6] && get_option( $opt )['mappings']['cam'] === $att[6] );
	$r = $svcOf( $REAL, array( 'image_map_store' => $mkStore( 'commit' ) ) )->save_image_map( $changed );
	$t( 'mevcut option + COMMIT başarısız: ok=false commit_failed; option eski; audit satırı yok; get_option eski', false === $r['ok'] && array( 'commit_failed' ) === $r['error_codes'] && $beforeRow === $optRow() && 1 === $auditRows() && get_option( $opt )['mappings']['cam'] === $att[6] && 0 === $inTx(), wp_json_encode( $r ) );
	$r = $svcOf( $REAL, array( 'image_map_store' => $mkStore( 'write' ) ) )->save_image_map( $changed );
	$t( 'mevcut option + option yazımı başarısız: ok=false option_write_failed; audit YAZILMADI; option eski', false === $r['ok'] && array( 'option_write_failed' ) === $r['error_codes'] && $beforeRow === $optRow() && 1 === $auditRows(), wp_json_encode( $r ) );
	$r = $svcOf( $REAL, array( 'image_map_store' => $mkStore( 'begin' ) ) )->save_image_map( $changed );
	$t( 'mevcut option + BEGIN başarısız: ok=false transaction_begin_failed; hiçbir yazma yok', false === $r['ok'] && array( 'transaction_begin_failed' ) === $r['error_codes'] && $beforeRow === $optRow() && 1 === $auditRows() );
	$r = $svcOf( $REAL, array( 'image_map_store' => $mkStore( 'audit+rollback' ) ) )->save_image_map( $changed );
	$t( 'audit hatası + rollback başarısız bildirimi: sabit transaction_rollback_failed, ASLA sahte başarı; veritabanı gerçekten geri alınmış (option eski)', false === $r['ok'] && array( 'transaction_rollback_failed' ) === $r['error_codes'] && $beforeRow === $optRow() && 1 === $auditRows(), wp_json_encode( $r ) );

	// (4) Eski option HİÇ yokken audit hatası: option oluşturulmuş kalmaz (DB, get_option ve önbellek).
	delete_option( $opt );
	wp_cache_flush();
	$r = $svcOf( $REAL, array( 'image_map_store' => $mkStore( 'audit' ) ) )->save_image_map( $fullMap );
	$t( 'option HİÇ yokken audit hatası: option oluşturulmuş KALMAZ (DB satırı yok; get_option false; alloptions/notoptions önbelleğinde yok); yetim audit satırı yok',
		false === $r['ok'] && array( 'audit_failed' ) === $r['error_codes'] && null === $optRow() && false === get_option( $opt, false ) && 1 === $auditRows() && 0 === $inTx() && ! isset( wp_load_alloptions()[ $opt ] ), wp_json_encode( $r ) );
	$r = $svcOf( $REAL, array( 'image_map_store' => $mkStore( 'commit' ) ) )->save_image_map( $fullMap );
	$t( 'option HİÇ yokken COMMIT başarısız: option yok, audit satırı yok, başarı yok', false === $r['ok'] && array( 'commit_failed' ) === $r['error_codes'] && null === $optRow() && 1 === $auditRows() && false === get_option( $opt, false ) );
	$again = $svcOf( $REAL )->save_image_map( $fullMap );
	$t( 'hatalardan sonra normal kayıt yeniden başarılı (kilit/transaction kalıntısı yok): option + yalnız 1 yeni audit satırı', true === $again['ok'] && 2 === $auditRows() && null !== $optRow() && 0 === $inTx() && 0 === $runTables() );
	$chain = MaviBelge_Core_Audit_Log::verify_chain();
	$t( 'audit zinciri hata enjeksiyonlarından sonra sağlam', is_array( $chain ) && ! empty( $chain['ok'] ), wp_json_encode( $chain ) );
	foreach ( $att as $id ) {
		wp_delete_attachment( $id, true );
	}
	delete_option( $opt );
	foreach ( (array) glob( "{$imgDir}/*" ) as $f ) {
		unlink( $f );
	}
	rmdir( $imgDir );
	$finish( 'Faz 6B4 görsel-map atomiklik WordPress runtime testi' );
	return;
}

/* ============================================================================================ */
if ( 'stages' === $phase ) {
	$seedNewsTerms();
	$svc = $svcOf( $B4 );
	$t( 'başlangıç: run tabloları henüz yok (dry-run/önizleme tablo OLUŞTURMAZ)', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$wpdb->prefix}mb_import_run_plan_items'" ) );
	$pq = $svc->preview_stage( 'qualifications' );
	$t( 'aşama sırası: sektörler yokken qualifications önkoşulu KARŞILANMADI (sectors gerekli)', true === $pq['ok'] && false === $pq['prerequisites']['met'] && 'sectors' === $pq['prerequisites']['requires'] );
	$rej = $svc->start_apply( 'qualifications', (string) $pq['plan_digest'], $phrase( 'qualifications', $pq['plan_digest'] ), get_current_user_id() );
	$t( 'aşama sırası (sunucu): qualifications başlatma reddedilir (prerequisite_not_met); run/plan_items/yazma YOK', false === $rej['ok'] && 'prerequisite_not_met' === $rej['error_code'] && 0 === $sectors() && 0 === $posts( 'mb_yeterlilik' ) );
	$t( 'aşama sırası: all ve content de reddedilir', 'prerequisite_not_met' === $svc->start_apply( 'all', str_repeat( 'a', 64 ), 'UYGULA all aaaaaaaaaaaa', 1 )['error_code'] && 'prerequisite_not_met' === $svc->start_apply( 'content', str_repeat( 'a', 64 ), 'UYGULA content aaaaaaaaaaaa', 1 )['error_code'] );

	// Faz 12: aşama zinciri pages ile başlar. pages tamamlanmadan sectors kapalıdır (sunucu zorlar).
	$pg0 = $svc->preview_stage( 'pages' );
	$t( 'Faz 12 aşama sırası: pages önkoşulsuz (ilk aşama); sectors pages tamamlanana kadar KAPALI', true === $pg0['prerequisites']['met'] && null === $pg0['prerequisites']['requires'] && false === $svc->preview_stage( 'sectors' )['prerequisites']['met'] && 'pages' === $svc->preview_stage( 'sectors' )['prerequisites']['requires'] );
	$rejS = $svc->start_apply( 'sectors', (string) $svc->preview_stage( 'sectors' )['plan_digest'], $phrase( 'sectors', $svc->preview_stage( 'sectors' )['plan_digest'] ), get_current_user_id() );
	$t( 'Faz 12 aşama sırası (sunucu): pages atlanıp sectors başlatma reddedilir (prerequisite_not_met); yazma YOK', false === $rejS['ok'] && 'prerequisite_not_met' === $rejS['error_code'] && 0 === $sectors() );
	$t( 'Faz 12 pages önizleme: 32 create (draft), eligible; kurum kararı bekleyen (bloklayıcı) sayfa YOK (yedi karar çözüldü); bilgilendirme uyarıları görünür', true === $pg0['eligible'] && 32 === $pg0['writes'] && 32 === $pg0['summary']['operations']['create'] && 0 === count( array_filter( $pg0['notices'], function ( $n ) {
		return true === $n['blocking'];
	} ) ) && array() !== $pg0['notices'], wp_json_encode( $pg0['summary'] ) );
	list( , $pgs ) = $startStage( $svc, 'pages' );
	$pgr = $lastOf( array_merge( array( $pgs ), $driveApply( $B4, (string) $pgs['run_uid'] ) ) );
	$pgPosts = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_mb_import_source_key', 'fields' => 'ids' ) );
	$pgDraft = array_filter( $pgPosts, function ( $id ) {
		return 'draft' === get_post_status( $id );
	} );
	$t( 'Faz 12 pages apply: 32 sayfa (4 batch ≤10), hepsi TASLAK; yayında sayfa yok; run completed', true === $pgr['ok'] && 'completed' === $pgr['status'] && 32 === count( $pgPosts ) && 32 === count( $pgDraft ) && 4 === (int) $runRow( (string) $pgs['run_uid'] )['committed_batches'], wp_json_encode( $pgr ) );
	$t( 'Faz 12 pages readback: önizleme 32 unchanged; tekrar start noop', 32 === $svc->preview_stage( 'pages' )['summary']['operations']['unchanged'] && 'noop' === $svc->start_apply( 'pages', (string) $svc->preview_stage( 'pages' )['plan_digest'], $phrase( 'pages', $svc->preview_stage( 'pages' )['plan_digest'] ), 1 )['status'] );
	$ps = $svc->preview_stage( 'sectors' );
	$badPhrase = $svc->start_apply( 'sectors', (string) $ps['plan_digest'], 'uygula sectors ' . substr( $ps['plan_digest'], 0, 12 ), get_current_user_id() );
	$t( 'sectors önizleme: 25 create, eligible; yanlış (küçük harfli) onay ifadesi reddedilir; run YOK', true === $ps['eligible'] && 25 === $ps['writes'] && 'confirmation_phrase_mismatch' === $badPhrase['error_code'] && 0 === $count( 'mb_import_runs', "stage = 'sectors'" ) );
	list( , $st ) = $startStage( $svc, 'sectors' );
	$uid = $st['run_uid'];
	$row = $runRow( $uid );
	$t( 'sectors start: run `ready` (DB), 25 plan item, 0 run item, 0 terim, run_started audit\'i henüz YOK; plan_items içerik değeri taşımıyor', true === $st['ok'] && 'ready' === $row['status'] && 25 === $count( 'mb_import_run_plan_items', 'run_id = ' . (int) $row['id'] ) && 0 === $count( 'mb_import_run_items', 'run_id = ' . (int) $row['id'] ) && 0 === $sectors() && array() === $events( (int) $row['id'] )
		&& 0 === $count( 'mb_import_run_plan_items', 'run_id = ' . (int) $row['id'] . " AND (CONCAT_WS('|', source_key, type, decision, expected_incoming_hash, IFNULL(expected_current_hash,''), IFNULL(natural_key_check,'')) LIKE '%TEST %' OR source_key NOT LIKE 'sector:zz-test-%')" ), wp_json_encode( $st ) );
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}mb_import_run_plan_items", 0 );
	$t( 'plan_items şeması: yalnız kimlik/sıra/tür/karar/hedef/hash/doğal-anahtar sütunları (içerik sütunu YOK)', array( 'created_at', 'decision', 'expected_current_hash', 'expected_incoming_hash', 'expected_last_applied_hash', 'id', 'natural_key_check', 'run_id', 'seq', 'source_key', 'target_id', 'type' ) === ( function ( $c ) {
		sort( $c );
		return $c;
	} )( $cols ) && '2' === get_option( 'mavibelge_core_import_tables_version' ) );
	$a1 = $svcOf( $B4 )->advance_apply( $uid, 0, get_current_user_id() );
	$t( 'advance #1 (yeni servis grafiği): 10 terim, run paused, checkpoint 1, kalan 15; audit run_started + batch_committed', true === $a1['ok'] && 'paused' === $a1['status'] && 1 === $a1['checkpoint'] && 15 === $a1['remaining'] && 10 === $sectors() && 'paused' === $runRow( $uid )['status'] && array( 'import_run_started', 'import_batch_committed' ) === $events( (int) $row['id'] ), wp_json_encode( $a1 ) );
	$dup = $svcOf( $B4 )->advance_apply( $uid, 0, get_current_user_id() );
	$t( 'çift tıklama / eski checkpoint: stale_request; DB değişmez (10 terim, 10 run item)', 'stale_request' === $dup['error_code'] && 10 === $sectors() && 10 === $count( 'mb_import_run_items', 'run_id = ' . (int) $row['id'] ) );
	$rest = $driveApply( $B4, $uid, 1 );
	$last = end( $rest );
	$t( 'advance #2 ve #3: 20 sonra 25 terim; run completed, checkpoint 3; audit sırası started, batch×3, completed',
		2 === count( $rest ) && true === $last['ok'] && 'completed' === $last['status'] && 3 === $last['checkpoint'] && 25 === $sectors() && 'completed' === $runRow( $uid )['status'] && array( 'import_run_started', 'import_batch_committed', 'import_batch_committed', 'import_batch_committed', 'import_run_completed' ) === $events( (int) $row['id'] ), wp_json_encode( $rest ) );
	$t( 'readback: sectors önizlemesi 25 unchanged; tekrar start için yazılacak kayıt yok (noop)', 25 === $svc->preview_stage( 'sectors' )['summary']['operations']['unchanged'] && 'noop' === ( function ( $s, $p, $ph ) {
		return $s->start_apply( 'sectors', $p, $ph, 1 )['status'];
	} )( $svc, $svc->preview_stage( 'sectors' )['plan_digest'], $phrase( 'sectors', $svc->preview_stage( 'sectors' )['plan_digest'] ) ) );

	list( $pq2, $sq ) = $startStage( $svcOf( $B4 ), 'qualifications' );
	$lq = $lastOf( $driveApply( $B4, (string) $sq['run_uid'] ) );
	$t( 'qualifications: önkoşul karşılandı, 3 create, completed; 3 yeterlilik', true === $pq2['prerequisites']['met'] && true === $lq['ok'] && 'completed' === $lq['status'] && 3 === $posts( 'mb_yeterlilik', 'draft' ) + $posts( 'mb_yeterlilik', 'publish' ), wp_json_encode( $lq ) );
	$t( 'aşama sırası: content hâlâ kapalı (yalnız all unchanged olunca açılır)', false === $svcOf( $B4 )->preview_stage( 'content' )['prerequisites']['met'] );
	list( $pa, $sa ) = $startStage( $svcOf( $B4 ), 'all' );
	$la = $lastOf( $driveApply( $B4, (string) $sa['run_uid'] ) );
	$t( 'all: 5 ücret (3 kodlu + 2 kodsuz), completed', true === $pa['prerequisites']['met'] && true === $la['ok'] && 'completed' === $la['status'] && 5 === $posts( 'mb_ucret' ), wp_json_encode( $la ) );
	list( $pc, $sc ) = $startStage( $svcOf( $B4 ), 'content' );
	$lc = $lastOf( $driveApply( $B4, (string) $sc['run_uid'] ) );
	$t( 'content: önkoşul (all unchanged) karşılandı, 6 kayıt (3 haber + 3 referans) completed; dört aşamanın hepsi sırayla tamamlandı', true === $pc['prerequisites']['met'] && true === $lc['ok'] && 'completed' === $lc['status'] && 3 === $posts( 'mb_haber' ) && 3 === $posts( 'mb_referans' ), wp_json_encode( $lc ) );
	$runs = $svcOf( $B4 )->list_runs();
	$t( 'run listesi: 5 completed run (pages, sectors, qualifications, all, content), en yeni önce (content), güvenli sütunlar; digest/yol/manifest sızmaz',
		5 === count( $runs ) && 'content' === $runs[0]['stage'] && 'sectors' === $runs[3]['stage'] && 'pages' === $runs[4]['stage'] && 5 === count( array_filter( $runs, function ( $r ) {
			return 'completed' === $r['status'];
		} ) ) && false === strpos( wp_json_encode( $runs ), '/tmp' ) && false === strpos( wp_json_encode( $runs ), 'digest' ) );
	$dump = wp_json_encode( $svcOf( $B4 )->preview_stage( 'all' ) );
	$t( 'önizleme DTO\'su güvenli: sektör adı/açıklaması, mutlak yol, SQL yok', false === strpos( $dump, 'TEST Sektör' ) && false === strpos( $dump, 'Sahte test' ) && false === strpos( $dump, '/tmp' ) && false === strpos( $dump, 'SELECT' ) );

	// content rollback -> reapply (çok istekli)
	$rp  = $svcOf( $B4 )->preview_rollback( (string) $sc['run_uid'] );
	$bad = $svcOf( $B4 )->start_rollback( (string) $sc['run_uid'], $rp['rollback_digest'], $phrase( 'content', $rp['rollback_digest'] ) );
	$t( 'rollback önizleme: 6 bekleyen item, engel yok; apply ifadesi rollback için REDDEDİLİR', true === $rp['ok'] && 6 === $rp['items_pending'] && array() === $rp['blockers'] && 'confirmation_phrase_mismatch' === $bad['error_code'] && 'completed' === $runRow( (string) $sc['run_uid'] )['status'] );
	$rs = $svcOf( $B4 )->start_rollback( (string) $sc['run_uid'], $rp['rollback_digest'], (string) $G::rollback_phrase( (string) $sc['run_uid'], $rp['rollback_digest'] ) );
	$ra = $svcOf( $B4 )->advance_rollback( (string) $sc['run_uid'], 0 );
	$t( 'content rollback: rollback_ready -> advance -> rolled_back; haber/referans çöpte (kalıcı silme yok)', true === $rs['ok'] && 'rollback_ready' === $rs['status'] && true === $ra['ok'] && 'rolled_back' === $ra['status'] && 0 === $posts( 'mb_haber', 'draft' ) && 3 === $posts( 'mb_haber', 'trash' ) && 3 === $posts( 'mb_referans', 'trash' ), wp_json_encode( array( $rs, $ra ) ) );
	list( $pr, $sr ) = $startStage( $svcOf( $B4 ), 'content' );
	$lr = $lastOf( $driveApply( $B4, (string) $sr['run_uid'] ) );
	$t( 'rollback -> reapply: content aşaması yeniden 6 create olarak uygulanır ve completed', 6 === $pr['summary']['operations']['create'] && true === $lr['ok'] && 'completed' === $lr['status'] && 3 === $posts( 'mb_haber', 'draft' ), wp_json_encode( $pr['summary'] ) );
	$chain = MaviBelge_Core_Audit_Log::verify_chain();
	$t( 'audit zinciri bütün aşamalardan sonra doğrulanır', is_array( $chain ) && ! empty( $chain['ok'] ), wp_json_encode( $chain ) );
	$finish( 'Faz 6B4 dört aşama WordPress runtime testi' );
	return;
}

/* ============================================================================================ */
if ( 'sector-rollback' === $phase ) {
	list( , $st ) = $startStage( $svcOf( $B4 ), 'sectors' );
	$uid = $st['run_uid'];
	$driveApply( $B4, $uid );
	$t( 'hazırlık: 25 sektör çok istekle uygulandı (completed)', 25 === $sectors() && 'completed' === $runRow( $uid )['status'] );
	$svc = $svcOf( $B4 );
	$rp  = $svc->preview_rollback( $uid );
	$rs  = $svc->start_rollback( $uid, $rp['rollback_digest'], (string) $G::rollback_phrase( $uid, $rp['rollback_digest'] ) );
	$t( 'rollback start: rollback_ready, 25 kalan; hiçbir terim silinmedi', true === $rs['ok'] && 'rollback_ready' === $rs['status'] && 25 === $rs['remaining'] && 25 === $sectors() );
	$r1 = $svcOf( $B4 )->advance_rollback( $uid, 0 );
	$leftSlugs = $wpdb->get_col( $wpdb->prepare( "SELECT t.slug FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'mb_sektor' AND t.slug LIKE %s", $wpdb->esc_like( 'zz-test-' ) . '%' ) );
	$t( 'rollback #1: TERS sırada 10 kayıt (s25..s16) geri alındı; rollback_paused, checkpoint 1, kalan 15', true === $r1['ok'] && 'rollback_paused' === $r1['status'] && 1 === $r1['checkpoint'] && 15 === $r1['remaining'] && ! in_array( 'zz-test-s25', $leftSlugs, true ) && ! in_array( 'zz-test-s16', $leftSlugs, true ) && in_array( 'zz-test-s15', $leftSlugs, true ) && 15 === count( $leftSlugs ), wp_json_encode( $r1 ) );
	$dupr = $svcOf( $B4 )->advance_rollback( $uid, 0 );
	$t( 'rollback çift tıklama: stale_request; geri alma tekrarlanmaz', 'stale_request' === $dupr['error_code'] && 15 === $sectors() && 1 === (int) $runRow( $uid )['rollback_batches'] );
	// drift: yönetilmeyen alanı kullanıcı değiştirdi (hâlâ bekleyen bir sektör)
	$tid = get_term_by( 'slug', 'zz-test-b', 'mb_sektor' )->term_id;
	update_term_meta( $tid, 'kullanici_notu', 'rollback ortasında kullanıcı değişikliği' );
	$rd = $svcOf( $B4 )->advance_rollback( $uid, 1 );
	$t( 'rollback drift: kullanıcı değişikliği -> rollback_failed/drift_detected; kalan 15 kayıt YAZILMADAN kalır; kullanıcı değişikliği korunur',
		false === $rd['ok'] && 'drift_detected' === $rd['error_code'] && 'rollback_failed' === $rd['status'] && 15 === $sectors() && 'rollback ortasında kullanıcı değişikliği' === get_term_meta( $tid, 'kullanici_notu', true ) && 10 === (int) $runRow( $uid )['rollback_items'], wp_json_encode( $rd ) );
	$rp2 = $svcOf( $B4 )->preview_rollback( $uid );
	$rsx = $svcOf( $B4 )->start_rollback( $uid, $rp2['rollback_digest'], (string) $G::rollback_phrase( $uid, $rp2['rollback_digest'] ) );
	$t( 'drift sürerken rollback yeniden başlatılamaz (drift_detected); durum rollback_failed', false === $rsx['ok'] && 'drift_detected' === $rsx['error_code'] && 'rollback_failed' === $runRow( $uid )['status'] );
	delete_term_meta( $tid, 'kullanici_notu' );
	$rp3 = $svcOf( $B4 )->preview_rollback( $uid );
	$rs3 = $svcOf( $B4 )->start_rollback( $uid, $rp3['rollback_digest'], (string) $G::rollback_phrase( $uid, $rp3['rollback_digest'] ) );
	$cp  = (int) $runRow( $uid )['rollback_batches'];
	$steps = array();
	for ( $i = 0; $i < 6; $i++ ) {
		$s = $svcOf( $B4 )->advance_rollback( $uid, $cp );
		$steps[] = $s;
		if ( ! $s['ok'] || 'rollback_paused' !== $s['status'] ) {
			break;
		}
		$cp = $s['checkpoint'];
	}
	$t( 'drift giderilince rollback_failed run yeniden başlatılır ve kalan 15 kayıtla rolled_back olur; 0 terim; her item rolled_back', true === $rs3['ok'] && 'rollback_ready' === $rs3['status'] && 'rolled_back' === $runRow( $uid )['status'] && 0 === $sectors() && 0 === $count( 'mb_import_run_items', "run_id = " . (int) $runRow( $uid )['id'] . " AND rollback_status <> 'rolled_back'" ), wp_json_encode( $steps ) );
	list( $pre, $sre ) = $startStage( $svcOf( $B4 ), 'sectors' );
	$lre = $lastOf( $driveApply( $B4, (string) $sre['run_uid'] ) );
	$t( 'rollback -> reapply: aynı manifest yeniden 25 create olarak önerilir (conflict 0) ve çok istekle completed; 25 terim', 25 === $pre['summary']['operations']['create'] && 0 === $pre['summary']['operations']['conflict'] && true === $lre['ok'] && 'completed' === $lre['status'] && 25 === $sectors(), wp_json_encode( $pre['summary'] ) );
	$chain = MaviBelge_Core_Audit_Log::verify_chain();
	$t( 'audit zinciri sağlam', is_array( $chain ) && ! empty( $chain['ok'] ), wp_json_encode( $chain ) );
	$finish( 'Faz 6B4 sektör rollback/reapply/drift WordPress runtime testi' );
	return;
}

/* ============================================================================================ */
if ( 'small-batches' === $phase ) {
	$seedNewsTerms();
	$runs = array();
	foreach ( array( 'sectors' => 13, 'qualifications' => 2, 'all' => 3, 'content' => 3 ) as $stage => $requests ) {
		$pv = $svcOf( $B4 )->preview_stage( $stage );
		$st = $factory( $B4 )->apply_service()->start_resumable( $stage, (string) $pv['plan_digest'], 2, get_current_user_id(), null );
		$n  = 0;
		$cp = 0;
		$last = null;
		for ( $i = 0; $i < 30 && $st['ok']; $i++ ) {
			$last = $factory( $B4 )->apply_service()->advance_resumable( $st['run_uid'], $cp, get_current_user_id(), null );
			$n++;
			if ( ! $last['ok'] || 'paused' !== $last['status'] ) {
				break;
			}
			$cp = $last['checkpoint'];
		}
		$after = $svcOf( $B4 )->preview_stage( $stage );
		$t( "çok istekli (batch=2) {$stage}: {$requests} ayrı istekte completed (her istek YENİ servis grafiği); readback tümü unchanged", true === $st['ok'] && $requests === $n && true === $last['ok'] && 'completed' === $last['status'] && $after['summary']['operations']['unchanged'] === $after['summary']['total'] && 0 === $after['writes'], wp_json_encode( array( $st['ok'], $n, $last ) ) );
		$runs[ $stage ] = $st['run_uid'];
	}
	$rp  = $factory( $B4 )->rollback_service()->preview( $runs['content'] );
	$rst = $factory( $B4 )->rollback_service()->start_resumable( $runs['content'], $rp['rollback_digest'] );
	$n   = 0;
	$cp  = 0;
	$last = null;
	for ( $i = 0; $i < 10 && $rst['ok']; $i++ ) {
		$last = $factory( $B4 )->rollback_service()->advance_resumable( $runs['content'], $cp, 2 );
		$n++;
		if ( ! $last['ok'] || 'rollback_paused' !== $last['status'] ) {
			break;
		}
		$cp = $last['checkpoint'];
	}
	$t( 'çok istekli (batch=2) content rollback: 3 ayrı istekte rolled_back; haber/referans çöpte, kalıcı silme yok', true === $rst['ok'] && 3 === $n && 'rolled_back' === $last['status'] && 6 === $last['committed'] && 0 === $posts( 'mb_haber', 'draft' ) && 3 === $posts( 'mb_haber', 'trash' ) && 3 === $posts( 'mb_referans', 'trash' ), wp_json_encode( array( $n, $last ) ) );
	$chain = MaviBelge_Core_Audit_Log::verify_chain();
	$t( 'audit zinciri sağlam', is_array( $chain ) && ! empty( $chain['ok'] ), wp_json_encode( $chain ) );
	$finish( 'Faz 6B4 her-aşamada çok istekli (batch=2) WordPress runtime testi' );
	return;
}

/* ============================================================================================ */
if ( 'migration' === $phase ) {
	// Faz 6B3 (sürüm 1) run tabloları: ESKİ DDL'nin birebir kopyası + bir eski run/item satırı; sürüm seçeneği '1'.
	$runsT  = $wpdb->prefix . 'mb_import_runs';
	$itemsT = $wpdb->prefix . 'mb_import_run_items';
	$planT  = $wpdb->prefix . 'mb_import_run_plan_items';
	foreach ( array( $planT, $itemsT, $runsT ) as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}
	delete_option( 'mavibelge_core_import_tables_version' );
	$collate = $wpdb->get_charset_collate();
	$wpdb->query( "CREATE TABLE {$runsT} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, uid CHAR(32) NOT NULL, stage VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL,
		plan_digest CHAR(64) NOT NULL, manifest_digest CHAR(64) NOT NULL, batch_size SMALLINT UNSIGNED NOT NULL, total_writes INT UNSIGNED NOT NULL,
		committed_batches INT UNSIGNED NOT NULL DEFAULT 0, committed_items INT UNSIGNED NOT NULL DEFAULT 0, error_code VARCHAR(64) NULL,
		created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id), UNIQUE KEY uid (uid), KEY status (status)
	) {$collate}" );
	$wpdb->query( "CREATE TABLE {$itemsT} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, run_id BIGINT UNSIGNED NOT NULL, seq INT UNSIGNED NOT NULL, batch_no INT UNSIGNED NOT NULL,
		source_key VARCHAR(191) NOT NULL, type VARCHAR(20) NOT NULL, decision VARCHAR(10) NOT NULL, target_id BIGINT UNSIGNED NOT NULL,
		rollback_record LONGTEXT NOT NULL, rollback_status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL,
		PRIMARY KEY  (id), UNIQUE KEY run_seq (run_id, seq), KEY type_decision (type, decision, rollback_status)
	) {$collate}" );
	update_option( 'mavibelge_core_import_tables_version', '1', false );
	$legacyUid = str_repeat( 'ab', 16 );
	$wpdb->insert( $runsT, array( 'uid' => $legacyUid, 'stage' => 'sectors', 'status' => 'completed', 'plan_digest' => str_repeat( '1', 64 ), 'manifest_digest' => str_repeat( '2', 64 ), 'batch_size' => 20, 'total_writes' => 3, 'committed_batches' => 1, 'committed_items' => 3, 'created_by' => 1, 'created_at' => '2026-09-01 00:00:00', 'updated_at' => '2026-09-01 00:00:00' ) );
	$wpdb->insert( $itemsT, array( 'run_id' => (int) $wpdb->insert_id, 'seq' => 1, 'batch_no' => 1, 'source_key' => 'sector:zz-eski', 'type' => 'sector', 'decision' => 'create', 'target_id' => 5, 'rollback_record' => '{}', 'rollback_status' => 'pending', 'created_at' => '2026-09-01 00:00:00' ) );
	$store = new MaviBelge_Core_Import_Wpdb_Run_Store();
	$t( 'hazırlık: eski (sürüm 1) run tabloları + 1 run + 1 item; sürüm seçeneği 1; plan_items tablosu YOK', '1' === get_option( 'mavibelge_core_import_tables_version' ) && 1 === $count( 'mb_import_runs' ) && 1 === $count( 'mb_import_run_items' ) && null === $wpdb->get_var( "SHOW TABLES LIKE '{$planT}'" ) );
	$t( 'is_installed() sürüm 1 kurulumda FALSE (salt okunur; tablo/sütun yükseltmesi YAPMAZ)', false === $store->is_installed() && null === $wpdb->get_var( "SHOW TABLES LIKE '{$planT}'" ) && 0 === count( $wpdb->get_col( "SHOW COLUMNS FROM {$runsT} LIKE 'map_digest'" ) ) );
	$ok1 = $store->ensure_installed();
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$runsT}", 0 );
	$t( 'ensure_installed(): idempotent yükseltme — runs\'a map_digest/rollback_batches/rollback_items eklendi, plan_items tablosu oluştu, sürüm 2', true === $ok1 && in_array( 'map_digest', $cols, true ) && in_array( 'rollback_batches', $cols, true ) && in_array( 'rollback_items', $cols, true ) && $planT === $wpdb->get_var( "SHOW TABLES LIKE '{$planT}'" ) && '2' === get_option( 'mavibelge_core_import_tables_version' ) && true === $store->is_installed() );
	$legacy = $store->get_run( $legacyUid );
	$t( 'eski run satırı KORUNDU (veri kaybı yok): completed, 3 item; yeni sütunlar varsayılan (map_digest null, rollback sayaçları 0); eski item satırı yerinde', is_array( $legacy ) && 'completed' === $legacy['status'] && 3 === $legacy['committed_items'] && null === $legacy['map_digest'] && 0 === $legacy['rollback_batches'] && 0 === $legacy['rollback_items'] && 1 === $count( 'mb_import_run_items' ) && 1 === $count( 'mb_import_runs' ) );
	$before = array( $count( 'mb_import_runs' ), $count( 'mb_import_run_items' ), $count( 'mb_import_run_plan_items' ), $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$runsT}'" ) );
	$ok2 = $store->ensure_installed();
	$after = array( $count( 'mb_import_runs' ), $count( 'mb_import_run_items' ), $count( 'mb_import_run_plan_items' ), $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$runsT}'" ) );
	$t( 'ikinci ensure_installed(): değişiklik YOK (satır sayıları ve sütun sayısı aynı; hata yok)', true === $ok2 && $before === $after );
	// yükseltilmiş depoda yeni yollar çalışır
	$snap  = MaviBelge_Core_Import_Plan_Snapshot::from_writes( array( array( 'source_key' => 'sector:zz-yeni', 'type' => 'sector', 'decision' => 'create', 'target_id' => null, 'expected_incoming_hash' => str_repeat( 'a', 64 ), 'expected_current_hash' => null, 'expected_last_applied_hash' => null ) ) );
	$newRun = $store->create_run_with_plan( array( 'stage' => 'sectors', 'plan_digest' => str_repeat( '3', 64 ), 'manifest_digest' => str_repeat( '4', 64 ), 'map_digest' => null, 'batch_size' => 10, 'total_writes' => 1, 'created_by' => 1 ), $snap );
	$t( 'yükseltilmiş depoda create_run_with_plan çalışır: run ready + 1 plan item; get_plan_items ve CAS checkpoint sürüm 2 sütunlarıyla çalışır', is_array( $newRun ) && 'ready' === $newRun['status'] && 1 === count( $store->get_plan_items( $newRun['id'], 0, 10 ) ) && true === $store->transition( $newRun['id'], 'ready', 'running' ) && true === $store->record_checkpoint( $newRun['id'], 1, 1, 0 ) && false === $store->record_checkpoint( $newRun['id'], 2, 2, 0 ) );
	$finish( 'Faz 6B4 run-store sürüm 1 → 2 yükseltme WordPress runtime testi' );
	return;
}

/* ============================================================================================ */
if ( 'faults' === $phase ) {
	$rollbackAll = function ( $uid ) use ( $svcOf, $B4, $G ) {
		$rp = $svcOf( $B4 )->preview_rollback( $uid );
		$rs = $svcOf( $B4 )->start_rollback( $uid, (string) $rp['rollback_digest'], (string) $G::rollback_phrase( $uid, (string) $rp['rollback_digest'] ) );
		$cp = 0;
		$last = $rs;
		for ( $i = 0; $i < 6 && $rs['ok']; $i++ ) {
			$last = $svcOf( $B4 )->advance_rollback( $uid, $cp );
			if ( ! $last['ok'] || 'rollback_paused' !== $last['status'] ) {
				break;
			}
			$cp = $last['checkpoint'];
		}
		return $last;
	};
	// F1: batch audit yazıldı ama false döndü -> transaction geri alır (yetim audit YOK)
	$sink = new MBFX4_Sink();
	$sink->failAfterRecord = array( 'import_batch_committed' );
	list( , $s1 ) = $startStage( $svcOf( $B4, array( 'audit' => $sink ) ), 'sectors' );
	$a = $svcOf( $B4, array( 'audit' => $sink ) )->advance_apply( $s1['run_uid'], 0, get_current_user_id() );
	$r1 = $runRow( $s1['run_uid'] );
	$t( 'hata (gerçek DB): batch audit yazıldıktan sonra false -> audit_failed; batch TAMAMEN geri alındı (0 terim, 0 item, yetim batch audit satırı YOK); run failed',
		false === $a['ok'] && 'audit_failed' === $a['error_code'] && 'failed' === $a['status'] && 0 === $sectors() && 0 === $count( 'mb_import_run_items', 'run_id = ' . (int) $r1['id'] ) && ! in_array( 'import_batch_committed', $events( (int) $r1['id'] ), true ) && 'failed' === $r1['status'], wp_json_encode( array( $a, $events( (int) $r1['id'] ) ) ) );
	// F2: checkpoint yazımı başarısız
	$store2 = new MBFX4_Store();
	list( , $s2 ) = $startStage( $svcOf( $B4, array( 'store' => $store2 ) ), 'sectors' );
	$store2->failCheckpoint = true;
	$a = $svcOf( $B4, array( 'store' => $store2 ) )->advance_apply( $s2['run_uid'], 0, get_current_user_id() );
	$t( 'hata (gerçek DB): checkpoint yazılamazsa checkpoint_failed; batch geri alındı (0 terim), run failed, sayaçlar 0', false === $a['ok'] && 'checkpoint_failed' === $a['error_code'] && 'failed' === $a['status'] && 0 === $sectors() && 0 === (int) $runRow( $s2['run_uid'] )['committed_items'], wp_json_encode( $a ) );
	// F3: COMMIT başarısız (gerçek)
	$tx3 = new MBFX4_Tx();
	$tx3->failCommitAfter = 1; // run_started geçişinin commit'i geçer; batch commit'i başarısız
	list( , $s3 ) = $startStage( $svcOf( $B4 ), 'sectors' );
	$a = $svcOf( $B4, array( 'tx' => $tx3 ) )->advance_apply( $s3['run_uid'], 0, get_current_user_id() );
	$t( 'hata (gerçek DB): batch COMMIT başarısız -> commit_failed; 0 terim (gerçek ROLLBACK), run failed', false === $a['ok'] && 'commit_failed' === $a['error_code'] && 'failed' === $a['status'] && 0 === $sectors(), wp_json_encode( $a ) );
	// F4: paused geçişi başarısız (commit edilmiş batch, durum running kalır)
	$store4 = new MBFX4_Store();
	list( , $s4 ) = $startStage( $svcOf( $B4 ), 'sectors' );
	$store4->failTo = array( 'paused' );
	$a = $svcOf( $B4, array( 'store' => $store4 ) )->advance_apply( $s4['run_uid'], 0, get_current_user_id() );
	$rowAfter = $runRow( $s4['run_uid'] );
	$t( 'hata (gerçek DB): batch commit edildi ama paused geçişi uygulanamadı -> finalization_failed, GERÇEK durum running (DB), 10 terim commit\'li', false === $a['ok'] && 'finalization_failed' === $a['error_code'] && 'running' === $a['status'] && 'running' === $rowAfter['status'] && 10 === $sectors() && 1 === (int) $rowAfter['committed_batches'], wp_json_encode( $a ) );
	$next = $svcOf( $B4 )->advance_apply( $s4['run_uid'], 1, get_current_user_id() );
	$t( 'takılı running run sonraki istekte bayat sayılır: rollback_required/stale_run; advance run_not_resumable; sessiz devam YOK', false === $next['ok'] && 'run_not_resumable' === $next['error_code'] && 'rollback_required' === $next['status'] && 'stale_run' === $runRow( $s4['run_uid'] )['error_code'] && 10 === $sectors() );
	$rb = $rollbackAll( $s4['run_uid'] );
	$t( 'rollback_required run çok istekli geri alınabilir (yarım apply kurtarma): rolled_back, 0 terim', true === $rb['ok'] && 'rolled_back' === $rb['status'] && 0 === $sectors(), wp_json_encode( $rb ) );
	// F5: TOCTOU — planlamadan sonra doğal anahtar doldu
	list( , $s5 ) = $startStage( $svcOf( $B4 ), 'sectors' );
	$svcOf( $B4 )->advance_apply( $s5['run_uid'], 0, get_current_user_id() );
	$ext = wp_insert_term( 'Kullanıcının Terimi', 'mb_sektor', array( 'slug' => 'zz-test-s15' ) );
	$a   = $svcOf( $B4 )->advance_apply( $s5['run_uid'], 1, get_current_user_id() );
	$t( 'TOCTOU (gerçek DB): planlamadan sonra doğal anahtar dolarsa toctou_drift; o batch\'in HİÇBİR kaydı yazılmaz (10 eski + kullanıcının 1 terimi), run rollback_required', false === $a['ok'] && 'toctou_drift' === $a['error_code'] && 'rollback_required' === $a['status'] && 11 === $sectors() && 10 === $count( 'mb_import_run_items', 'run_id = ' . (int) $runRow( $s5['run_uid'] )['id'] ), wp_json_encode( $a ) );
	wp_delete_term( $ext['term_id'], 'mb_sektor' );
	$rb = $rollbackAll( $s5['run_uid'] );
	$t( 'TOCTOU sonrası kalan 10 kayıt geri alınır; kullanıcı terimi silindikten sonra 0 terim', true === $rb['ok'] && 0 === $sectors(), wp_json_encode( $rb ) );
	// F6: manifest değişimi
	$mod = '/tmp/mbfx-b4-manifest-mod';
	if ( ! is_dir( $mod ) ) {
		mkdir( $mod, 0777, true );
	}
	foreach ( glob( $B4 . '/*.manifest.json' ) as $f ) {
		copy( $f, $mod . '/' . basename( $f ) );
	}
	list( , $s6 ) = $startStage( $svcOf( $mod ), 'sectors' );
	$svcOf( $mod )->advance_apply( $s6['run_uid'], 0, get_current_user_id() );
	$mf = json_decode( (string) file_get_contents( $mod . '/sectors.manifest.json' ), true );
	$mf['records'][24]['description'] = 'Manifest çalışma sırasında değişti.';
	file_put_contents( $mod . '/sectors.manifest.json', wp_json_encode( $mf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	$a = $svcOf( $mod )->advance_apply( $s6['run_uid'], 1, get_current_user_id() );
	$t( 'manifest çalışma sırasında değişti -> manifest_changed; sonraki batch yazılmaz (10 terim), run rollback_required', false === $a['ok'] && 'manifest_changed' === $a['error_code'] && 'rollback_required' === $a['status'] && 10 === $sectors(), wp_json_encode( $a ) );
	$rollbackAll( $s6['run_uid'] );
	// F7: görsel map digest'i değişti
	list( , $s7 ) = $startStage( $svcOf( $B4 ), 'sectors' );
	$saved = $svcOf( $B4 )->save_image_map( array( 'zz-yok' => 1 ) );
	$t( 'map: manifestte görselli sektör yokken fazla slug\'lı kayıt reddedilir', false === $saved['ok'] );
	$secRecords = json_decode( (string) file_get_contents( $B4 . '/sectors.manifest.json' ), true )['records'];
	update_option( MaviBelge_Core_Import_Sector_Image_Map::OPTION, MaviBelge_Core_Import_Sector_Image_Map::build_envelope( array(), MaviBelge_Core_Import_Sector_Image_Map::manifest_digest_for( $secRecords ) ), false );
	$a = $svcOf( $B4 )->advance_apply( $s7['run_uid'], 0, get_current_user_id() );
	delete_option( MaviBelge_Core_Import_Sector_Image_Map::OPTION );
	$t( 'görsel map durumu run başladıktan sonra değişti -> map_changed; hiçbir kayıt yazılmadı, run failed', false === $a['ok'] && 'map_changed' === $a['error_code'] && 'failed' === $a['status'] && 0 === $sectors(), wp_json_encode( $a ) );
	// F8: gerçek DB kilidi (paralel istek)
	list( , $s8 ) = $startStage( $svcOf( $B4 ), 'sectors' );
	$other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$got   = $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $wpdb->prefix . 'mavibelge_import' ) );
	$a     = $svcOf( $B4 )->advance_apply( $s8['run_uid'], 0, get_current_user_id() );
	$pv    = $svcOf( $B4 )->preview_stage( 'sectors' );
	$st2   = $svcOf( $B4 )->start_apply( 'sectors', (string) $pv['plan_digest'], $phrase( 'sectors', $pv['plan_digest'] ), 1 );
	$t( 'paralel istek (GERÇEK GET_LOCK başka bağlantıda): advance -> locked; start -> locked; hiçbir şey yazılmaz', '1' === (string) $got && false === $a['ok'] && 'locked' === $a['error_code'] && 'locked' === $st2['error_code'] && 0 === $sectors() && 'ready' === $runRow( $s8['run_uid'] )['status'], wp_json_encode( array( $a['error_code'], $st2['error_code'] ) ) );
	$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $wpdb->prefix . 'mavibelge_import' ) );
	$after = $svcOf( $B4 )->advance_apply( $s8['run_uid'], 0, get_current_user_id() );
	$t( 'kilit bırakılınca aynı checkpoint ile ilerler (10 terim) — kilit yüzünden durum bozulmadı', true === $after['ok'] && 'paused' === $after['status'] && 10 === $sectors(), wp_json_encode( $after ) );
	$last = $lastOf( $driveApply( $B4, $s8['run_uid'], 1 ) );
	$t( 'kalan batch\'ler tamamlanır (completed, 25 terim)', true === $last['ok'] && 'completed' === $last['status'] && 25 === $sectors() );
	$chain = MaviBelge_Core_Audit_Log::verify_chain();
	$t( 'audit zinciri hata enjeksiyonlarından sonra da doğrulanır', is_array( $chain ) && ! empty( $chain['ok'] ), wp_json_encode( $chain ) );
	foreach ( (array) glob( $mod . '/*' ) as $f ) {
		unlink( $f );
	}
	rmdir( $mod );
	$finish( 'Faz 6B4 hata enjeksiyonu / kilit / TOCTOU WordPress runtime testi' );
	return;
}

/* ============================================================================================ */
// Eşzamanlı İKİ yönetici (İKİ AYRI PHP süreci, GERÇEK GET_LOCK): audit eski/yeni digest zinciri doğru kalmalı.
//   map-race-setup  : 10 attachment + başlangıç map'i (M0)
//   map-race-a      : yönetici A, audit adımında (kilit ELİNDEYKEN) 4 sn bekler; cam -> att10
//   map-race-b      : yönetici B, A'dan ~1,5 sn sonra başlar; tekstil -> att10; kilit yüzünden A bitene kadar BEKLEMELİ
//   map-race-verify : audit zinciri M0 -> A -> B; B'nin old_digest'i A'nın new_digest'i; son option B'nin map'i
if ( 0 === strpos( $phase, 'map-race-' ) ) {
	$raceFile = '/tmp/mbfx-race.json';
	$opt      = MaviBelge_Core_Import_Sector_Image_Map::OPTION;
	$auditT   = MaviBelge_Core_Audit_Log::table_name();
	$mkMap    = function ( array $att, array $over = array() ) {
		return array_merge( array( 'makine' => $att[1], 'metalurji' => $att[2], 'metal' => $att[3], 'lojistik' => $att[4], 'enerji' => $att[5], 'cam' => $att[6], 'tekstil' => $att[7], 'insaat' => $att[8], 'maden' => $att[9], 'mermer' => $att[9] ), $over );
	};
	if ( 'map-race-setup' === $phase ) {
		foreach ( (array) get_terms( array( 'taxonomy' => 'mb_sektor', 'hide_empty' => false, 'fields' => 'ids' ) ) as $termId ) {
			wp_delete_term( (int) $termId, 'mb_sektor' );
		}
		$imgDir = '/tmp/mbfx-race-images';
		if ( ! is_dir( $imgDir ) ) {
			mkdir( $imgDir, 0777, true );
		}
		$att = array();
		for ( $i = 1; $i <= 10; $i++ ) {
			$file = "{$imgDir}/img-{$i}.png";
			file_put_contents( $file, $pngBytes );
			$att[ $i ] = wp_insert_attachment( array( 'post_title' => "TEST Yarış Görseli {$i}", 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $file );
		}
		MaviBelge_Core_Audit_Log::install();
		$r0 = $svcOf( $REAL )->save_image_map( $mkMap( $att ) );
		file_put_contents( $raceFile, wp_json_encode( array( 'att' => $att ) ) );
		echo 'RESULT ' . wp_json_encode( array( 'ok' => $r0['ok'], 'digest' => $r0['digest'] ) ) . "\n";
		return;
	}
	$att = json_decode( (string) file_get_contents( $raceFile ), true )['att'];
	$att = array_map( 'intval', $att );
	if ( 'map-race-a' === $phase || 'map-race-b' === $phase ) {
		$isA   = 'map-race-a' === $phase;
		// `nolock` = KONTROL koşusu: kilit no-op (düzeltmeden ÖNCEKİ davranışı benzetir) — yarışın gerçek DB'de yaşandığını kanıtlar.
		$store = new class( $isA, isset( $args[1] ) && 'nolock' === $args[1] ) extends MaviBelge_Core_Import_Wp_Image_Map_Store {
			private $slow;
			private $noLock;
			public function __construct( $slow, $noLock ) {
				parent::__construct();
				$this->slow   = $slow;
				$this->noLock = $noLock;
			}
			public function lock() {
				return $this->noLock ? true : parent::lock();
			}
			public function unlock() {
				return $this->noLock ? true : parent::unlock();
			}
			public function audit( array $context ) {
				if ( $this->slow ) {
					sleep( 4 ); // kilit ELİNDEYKEN: rakip yönetici bu sürede başlamış olmalı
				}
				return parent::audit( $context );
			}
		};
		$over  = $isA ? array( 'cam' => $att[10] ) : array( 'tekstil' => $att[10] );
		$t0    = microtime( true );
		$r     = $svcOf( $REAL, array( 'image_map_store' => $store ) )->save_image_map( $mkMap( $att, $over ) );
		$t1    = microtime( true );
		echo 'RESULT ' . wp_json_encode( array( 'ok' => $r['ok'], 'error_codes' => $r['error_codes'], 'digest' => $r['digest'], 'changed' => $r['changed_slugs'], 'elapsed' => round( $t1 - $t0, 2 ), 'pid' => getmypid() ) ) . "\n";
		return;
	}
	if ( 'map-race-verify' === $phase ) {
		$rows = $wpdb->get_col( "SELECT context FROM `{$auditT}` WHERE event_type = 'import_image_map_changed' ORDER BY id ASC" );
		$ctx  = array_map( function ( $c ) {
			return json_decode( (string) $c, true );
		}, $rows );
		$chainOk = 3 === count( $ctx ) && null === $ctx[0]['old_digest'] && $ctx[1]['old_digest'] === $ctx[0]['new_digest'] && $ctx[2]['old_digest'] === $ctx[1]['new_digest'];
		$stored  = get_option( $opt, array() );
		$final   = isset( $stored['mappings'] ) ? $stored['mappings'] : array();
		$chain   = MaviBelge_Core_Audit_Log::verify_chain();
		echo 'RESULT ' . wp_json_encode( array( 'audit_rows' => count( $ctx ), 'chain_ok' => $chainOk, 'final_has_b' => isset( $final['tekstil'] ) && $final['tekstil'] === $att[10], 'final_cam_is_b_value' => isset( $final['cam'] ) && $final['cam'] === $att[6], 'audit_hash_chain' => is_array( $chain ) && ! empty( $chain['ok'] ), 'changed_a' => $ctx[1]['changed_slugs'], 'changed_b' => $ctx[2]['changed_slugs'], 'in_tx' => (int) $wpdb->get_var( 'SELECT @@in_transaction' ) ) ) . "\n";
		foreach ( $att as $id ) {
			wp_delete_attachment( $id, true );
		}
		delete_option( $opt );
		foreach ( (array) glob( '/tmp/mbfx-race-images/*' ) as $f ) {
			unlink( $f );
		}
		@rmdir( '/tmp/mbfx-race-images' );
		@unlink( $raceFile );
		return;
	}
}

/* ============================================================================================ */
/* ============================================================================================ */
/* Faz 12: pages aşaması (32 gerçek `page` kaydı) + AYRI, açık onaylı staging yayınlama — gerçek WordPress 6.9.9 / PHP 7.3.   */
if ( 'pages' === $phase ) {
	$svc    = $svcOf( $B4 );
	$pageIds = function ( $status = 'any' ) {
		return get_posts( array( 'post_type' => 'page', 'post_status' => $status, 'numberposts' => -1, 'meta_key' => '_mb_import_source_key', 'fields' => 'ids', 'orderby' => 'ID' ) );
	};
	$trashed = function () {
		return count( get_posts( array( 'post_type' => 'page', 'post_status' => 'trash', 'numberposts' => -1, 'fields' => 'ids' ) ) );
	};
	$applyPages = function () use ( $svcOf, $B4, $startStage, $driveApply, $lastOf ) {
		list( $p, $st ) = $startStage( $svcOf( $B4 ), 'pages' );
		$rest = 'ready' === $st['status'] || 'paused' === $st['status'] ? $driveApply( $B4, (string) $st['run_uid'] ) : array();
		return array( $p, $st, array() === $rest ? $st : $lastOf( $rest ) );
	};
	$rollbackAll = function ( $uid ) use ( $svcOf, $B4, $G ) {
		$rp = $svcOf( $B4 )->preview_rollback( $uid );
		$rs = $svcOf( $B4 )->start_rollback( $uid, (string) $rp['rollback_digest'], (string) $G::rollback_phrase( $uid, (string) $rp['rollback_digest'] ) );
		$cp   = 0;
		$last = $rs;
		for ( $i = 0; $i < 6 && $rs['ok']; $i++ ) {
			$last = $svcOf( $B4 )->advance_rollback( $uid, $cp );
			if ( ! $last['ok'] || 'rollback_paused' !== $last['status'] ) {
				break;
			}
			$cp = $last['checkpoint'];
		}
		return array( $rp, $last );
	};

	// 1) admin önizleme digest'i == fabrika/CLI yolunun digest'i (tek karar motoru)
	$pv = $svc->preview_stage( 'pages' );
	$cliPlan = $factory( $B4 )->apply_service()->preview( 'pages' );
	$t( 'pages önizleme: admin plan_digest == CLI/fabrika plan_digest (tek karar motoru); 32 create, eligible; önkoşulsuz', true === $pv['eligible'] && 32 === $pv['summary']['operations']['create'] && $pv['plan_digest'] === $cliPlan['plan_digest'] && true === $pv['prerequisites']['met'] );
	$t( 'pages önizleme: yedi sayfanın kurum kararı çözüldü (bloklayıcı uyarı YOK); bloklayıcı olmayan bilgilendirme uyarıları (form kapısı/lokasyon/belge taramaları) görünür', 0 === count( array_filter( $pv['notices'], function ( $n ) {
		return true === $n['blocking'];
	} ) ) && count( $pv['notices'] ) > 0 );
	// 2) yayınlama, sayfalar oluşturulmadan: hiçbir sayfa yayınlanamaz
	$pp0 = $svc->preview_publish();
	$t( 'yayın önizleme (sayfalar YOKKEN): 32 satır, hazır 0, oluşturulmamış 32, bekletilen 0', true === $pp0['ok'] && 32 === $pp0['summary']['total'] && 0 === $pp0['summary']['ready'] && 32 === $pp0['summary']['not_created'] && 0 === $pp0['summary']['held_pending_decision'], wp_json_encode( $pp0['summary'] ) );
	// 3) apply -> 32 taslak
	list( , $st1, $r1 ) = $applyPages();
	$ids1   = $pageIds();
	$drafts = $pageIds( 'draft' );
	$t( 'pages apply: 32 sayfa, 4 batch, hepsi TASLAK, run completed', true === $r1['ok'] && 'completed' === $r1['status'] && 32 === count( $ids1 ) && 32 === count( $drafts ) && 4 === (int) $runRow( (string) $st1['run_uid'] )['committed_batches'], wp_json_encode( $r1 ) );
	// içerik: kapalı HTML, script/olay işleyici yok; KVKK/gizlilik/banka/takvim/MYK sorgu sayfaları onaylı kaynaktan DOLU
	$bad = 0;
	foreach ( $ids1 as $id ) {
		$c = (string) get_post_field( 'post_content', $id );
		if ( preg_match( '/<\s*(script|iframe|style|form|input|button|img|svg|object|embed)\b|\son[a-z]+\s*=|javascript:|data:text/i', $c ) ) {
			$bad++;
		}
	}
	$approvedCta = array( 'sinav-takvimi' => 'https://mavibelge.pratikteorik.com/home/examcalendar', 'sonuc-belge-sorgulama' => 'https://portal.myk.gov.tr/index.php?option=com_belgelendirme&view=belgelendirme_islemleri&layout=aday_bilgi_sorgu' );
	$ctaOk = true;
	foreach ( $approvedCta as $slugC => $urlC ) {
		$pc = get_page_by_path( $slugC, OBJECT, 'page' );
		$ctaOk = $ctaOk && $pc && false !== strpos( html_entity_decode( (string) $pc->post_content ), '<a href="' . $urlC . '">' ) && false === stripos( (string) $pc->post_content, '<iframe' );
	}
	$legalOk = true;
	foreach ( array( 'kvkk' => 'KİŞİSEL VERİLERİN KORUNMASI', 'gizlilik-politikasi' => 'Kişisel verileriniz', 'banka-hesap-bilgileri' => 'Şube Kodu' ) as $slugL => $needle ) {
		$pl = get_page_by_path( $slugL, OBJECT, 'page' );
		$legalOk = $legalOk && $pl && false !== strpos( (string) $pl->post_content, $needle ) && 'draft' === $pl->post_status;
	}
	$t( 'pages içerik: 32 sayfada tehlikeli etiket/olay işleyici/javascript: YOK; KVKK/gizlilik/banka sayfaları onaylı kaynaktan DOLU (taslak); sınav takvimi ve MYK sorgu CTA adresleri onaylı adresle BİREBİR (query string bozulmadan), iframe yok', 0 === $bad && $legalOk && $ctaOk );
	$t( 'pages readback: önizleme 32 unchanged (gerçek okuma == manifest; kses/slash gidiş-dönüş kaybı YOK); tekrar start noop', 32 === $svc->preview_stage( 'pages' )['summary']['operations']['unchanged'] && 'noop' === $svc->start_apply( 'pages', (string) $svc->preview_stage( 'pages' )['plan_digest'], $phrase( 'pages', $svc->preview_stage( 'pages' )['plan_digest'] ), 1 )['status'] );
	// düz yapı: manifestte üst sayfa yok (tema haritası düz); hiçbir sayfa üst sayfaya bağlanmaz
	$nonFlat = 0;
	foreach ( $ids1 as $id ) {
		if ( 0 !== (int) get_post_field( 'post_parent', $id ) ) {
			$nonFlat++;
		}
	}
	$t( 'pages yapı: manifest düz (parent_source_key yok) -> 32 sayfanın hepsi üst sayfasız; menü sırası manifestten', 0 === $nonFlat );

	// 4) rollback -> 0 sayfa (çöp), -> reapply
	list( $rbp, $rb ) = $rollbackAll( (string) $st1['run_uid'] );
	$t( 'pages rollback: önizleme 32 bekleyen, engel yok; geri alma rolled_back; oluşturulan 32 sayfa ÇÖPTE (kalıcı silme yok)', 32 === $rbp['items_pending'] && array() === $rbp['blockers'] && true === $rb['ok'] && 'rolled_back' === $rb['status'] && 0 === count( $pageIds( 'draft' ) ) && 32 === $trashed(), wp_json_encode( $rb ) );
	list( $pre, , $r2 ) = $applyPages();
	$t( 'pages rollback -> reapply: 32 create (conflict 0), completed; 32 taslak', 32 === $pre['summary']['operations']['create'] && 0 === $pre['summary']['operations']['conflict'] && true === $r2['ok'] && 'completed' === $r2['status'] && 32 === count( $pageIds( 'draft' ) ), wp_json_encode( $pre['summary'] ) );

	// 5) yayın önizleme + onay kapıları
	$pp = $svc->preview_publish();
	$t( 'yayın önizleme: 32 satır; hazır 30, içerik bekleyen 2 (referanslar, sss: content aşaması yok), bekletilen (kurum kararı) 0, yayında 0; her satırda başlık/slug/kaynak/durum/neden', true === $pp['ok'] && 30 === $pp['summary']['ready'] && 2 === $pp['summary']['content_not_ready'] && 0 === $pp['summary']['held_pending_decision'] && 0 === $pp['summary']['published'] && 32 === count( $pp['rows'] ) && array( 'source_key', 'slug', 'title', 'source_file', 'status', 'reason', 'publish_hold', 'publish_requires', 'pending' ) === array_keys( $pp['rows'][0] ), wp_json_encode( $pp['summary'] ) );
	$badPub = $svc->publish_pages( $pp['plan_digest'], 'yayinla ' . substr( $pp['plan_digest'], 0, 12 ), 30, 1 );
	$t( 'yayın: yanlış (küçük harfli) onay ifadesi reddedilir; hiçbir sayfa yayınlanmaz', false === $badPub['ok'] && 'confirmation_phrase_mismatch' === $badPub['error_code'] && 0 === count( $pageIds( 'publish' ) ) );
	$stale0 = $svc->publish_pages( $pp['plan_digest'], (string) $pp['phrase'], 29, 1 );
	$t( 'yayın: bayat expected_remaining (29 != 30) reddedilir (stale_request); yayın YOK', false === $stale0['ok'] && 'stale_request' === $stale0['error_code'] && 0 === count( $pageIds( 'publish' ) ) );
	$wrongDigest = $svc->publish_pages( str_repeat( 'a', 64 ), (string) MaviBelge_Core_Import_Admin_Gates::publish_phrase( str_repeat( 'a', 64 ) ), 30, 1 );
	$t( 'yayın: önizlemedeki özetle eşleşmeyen digest reddedilir (confirmation_mismatch)', false === $wrongDigest['ok'] && 'confirmation_mismatch' === $wrongDigest['error_code'] && 0 === count( $pageIds( 'publish' ) ) );

	// 6) çözülmemiş run varken yayın reddedilir
	$pvs = $svc->preview_stage( 'sectors' );
	$stS = $svc->start_apply( 'sectors', (string) $pvs['plan_digest'], $phrase( 'sectors', $pvs['plan_digest'] ), get_current_user_id() );
	$blk = $svc->publish_pages( $pp['plan_digest'], (string) $pp['phrase'], 30, 1 );
	$t( 'yayın: çözülmemiş (ready) bir run varken reddedilir (unresolved_run_exists); yayın YOK', true === $stS['ok'] && false === $blk['ok'] && 'unresolved_run_exists' === $blk['error_code'] && 0 === count( $pageIds( 'publish' ) ) );
	$rSec = $driveApply( $B4, (string) $stS['run_uid'] );
	$t( 'hazırlık: sektör run tamamlandı (çözülmüş)', true === $lastOf( $rSec )['ok'] && 'completed' === $lastOf( $rSec )['status'] );

	// 7) GERÇEK yayın: 10 + 10 + 10; her istek yeni servis grafiği
	$digest = (string) $pp['plan_digest'];
	$ph     = (string) $pp['phrase'];
	$b1 = $svcOf( $B4 )->publish_pages( $digest, $ph, 30, 1 );
	$t( 'yayın #1: 10 sayfa yayınlandı, kalan 20, paused; TAM 10 yayında', true === $b1['ok'] && 10 === $b1['published'] && 20 === $b1['remaining'] && 'paused' === $b1['status'] && 10 === count( $pageIds( 'publish' ) ), wp_json_encode( $b1 ) );
	$dbl = $svcOf( $B4 )->publish_pages( $digest, $ph, 30, 1 );
	$t( 'yayın çift tıklama: aynı expected_remaining (30) tekrar gönderilirse stale_request; yayın sayısı DEĞİŞMEZ (10)', false === $dbl['ok'] && 'stale_request' === $dbl['error_code'] && 10 === count( $pageIds( 'publish' ) ) );
	$b2 = $svcOf( $B4 )->publish_pages( $digest, $ph, 20, 1 );
	$b3 = $svcOf( $B4 )->publish_pages( $digest, $ph, 10, 1 );
	$t( 'yayın #2 #3: 10 sonra 10; completed; TOPLAM 30 yayında, içerik bağımlılığı olan 2 sayfa (referanslar, sss) TASLAKTA kaldı', true === $b2['ok'] && 10 === $b2['published'] && true === $b3['ok'] && 10 === $b3['published'] && 'completed' === $b3['status'] && 0 === $b3['remaining'] && 30 === count( $pageIds( 'publish' ) ) && 2 === count( $pageIds( 'draft' ) ), wp_json_encode( array( $b2, $b3 ) ) );
	$held = array_map( function ( $r ) {
		return $r['slug'];
	}, array_filter( $svc->preview_publish()['rows'], function ( $r ) {
		return 'content_not_ready' === $r['reason'];
	} ) );
	$heldPublished = 0;
	foreach ( $held as $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $p && 'publish' === $p->post_status ) {
			$heldPublished++;
		}
	}
	$t( 'yayın: içerik bağımlılığı tamamlanmayan sayfaların HİÇBİRİ yayınlanmadı (yalnız referanslar ve sss; sunucu tarafı kapı)', 2 === count( $held ) && 0 === $heldPublished && 'referanslar,sss' === implode( ',', array_values( $held ) ) );
	$again = $svcOf( $B4 )->publish_pages( $digest, $ph, 0, 1 );
	$t( 'yayın idempotent: yeniden onay -> completed, 0 sayfa yayınlanır (hazır kalmadı)', true === $again['ok'] && 0 === $again['published'] && 'completed' === $again['status'] );
	// yayın sonrası dry-run: yayındaki sayfa unchanged (post_status yönetilmez)
	$t( 'yayın sonrası pages önizleme: 32 unchanged (yayın durumu yönetilen alan değil)', 32 === $svcOf( $B4 )->preview_stage( 'pages' )['summary']['operations']['unchanged'] );
	// yayın sonrası import rollback: yayındaki sayfalar kullanıcı/yayın değişikliği (drift) sayılır -> rollback engellenir
	$prbList = $svcOf( $B4 )->list_runs();
	$pagesRun = '';
	foreach ( $prbList as $r ) {
		if ( 'pages' === $r['stage'] && 'completed' === $r['status'] ) {
			$pagesRun = $r['run_uid'];
			break;
		}
	}
	$rbp2 = $svcOf( $B4 )->preview_rollback( $pagesRun );
	$t( 'yayın sonrası import rollback: 30 yayınlı sayfa drift engeli (drift_detected); hiçbir sayfa silinmez', 30 === count( $rbp2['blockers'] ) && 'drift_detected' === $rbp2['blockers'][0]['code'] && 32 === $trashed(), wp_json_encode( $rbp2['blockers'][0] ) );
	// audit: yayın olayı (3 istek) + zincir
	$pubEvents = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `" . MaviBelge_Core_Audit_Log::table_name() . "` WHERE event_type = %s", 'import_pages_published' ) );
	$chain     = MaviBelge_Core_Audit_Log::verify_chain();
	$t( 'audit: import_pages_published 3 olay (10+10+10); zincir sağlam', 3 === $pubEvents && is_array( $chain ) && ! empty( $chain['ok'] ), wp_json_encode( array( $pubEvents, $chain ) ) );

	// 8) Faz 12b — GERÇEK WordPress'te içerik bağımlılığı: SSS + referans kayıtları (gerçek attachment) oluşup YAYINLANANA kadar referanslar/sss yayınlanamaz.
	$GATE = '/tmp/mbfx-pagegate';
	$gsvc = $svcOf( $GATE );
	$gapply = function ( $stage ) use ( $gsvc, $GATE, $phrase ) {
		$p  = $gsvc->preview_stage( $stage );
		$s  = $gsvc->start_apply( $stage, (string) $p['plan_digest'], $phrase( $stage, $p['plan_digest'] ), get_current_user_id() );
		$l  = $s;
		for ( $i = 0; $i < 12 && $s['ok']; $i++ ) {
			$l = $gsvc->advance_apply( (string) $s['run_uid'], $i, get_current_user_id() );
			if ( ! $l['ok'] || 'paused' !== $l['status'] ) {
				break;
			}
		}
		return $l;
	};
	$gpages = $gapply( 'pages' );
	$gpv0   = $gsvc->preview_publish();
	$t( 'yayın kapısı (gerçek WP): pages zaten uygulanmış/yayınlı (30); içerik aşaması YOKKEN referanslar ve sss content_not_ready (hazır 0)', is_array( $gpages ) && 30 === $gpv0['summary']['published'] && 0 === $gpv0['summary']['ready'] && 2 === $gpv0['summary']['content_not_ready'], wp_json_encode( $gpv0['summary'] ) );
	foreach ( array( 'haber' => 'Haber', 'duyuru' => 'Duyuru' ) as $gs => $gn ) {
		if ( ! get_term_by( 'slug', $gs, 'mb_haber_turu' ) ) {
			wp_insert_term( $gn, 'mb_haber_turu', array( 'slug' => $gs ) );
		}
	}
	$gfactory = $factory( $GATE );
	$gcp      = $gfactory->apply_service()->preview( 'content' );
	$gca      = $gfactory->apply_service()->apply( 'content', (string) $gcp['plan_digest'], null, get_current_user_id() );
	$attCount = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mb_import_logo_sha256' )" );
	$t( 'content (gerçek WP): 9 kayıt (3 haber + 3 referans + 3 SSS) completed; 3 logo attachment oluştu; referans _mb_logo_attachment_id gerçek, dosya özeti geçerli', true === $gca['ok'] && 'completed' === $gca['status'] && 3 === $posts( 'mb_referans', 'draft' ) && 3 === $attCount && 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'mb_sss' AND post_status = 'draft'" ), wp_json_encode( $gca ) );
	$gpv1 = $gsvc->preview_publish();
	$t( 'yayın kapısı (gerçek WP): içerik kayıtları oluştu ama TASLAK -> referanslar/sss hâlâ content_not_ready (boş liste yayınlanmaz)', 2 === $gpv1['summary']['content_not_ready'], wp_json_encode( $gpv1['summary'] ) );
	// Faz 12b: referans yayın hazırlığı (gerçek WP) — logo dosyası okunamıyorsa kayıt yayınlanamaz; dosya geri konunca yayınlanır.
	$refDrafts = get_posts( array( 'post_type' => 'mb_referans', 'post_status' => 'draft', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
	$probe     = (int) $refDrafts[0];
	$probeFile = get_attached_file( (int) get_post_meta( $probe, '_mb_logo_attachment_id', true ) );
	$moved     = is_string( $probeFile ) && is_file( $probeFile ) && rename( $probeFile, $probeFile . '.bak' );
	wp_update_post( array( 'ID' => $probe, 'post_status' => 'publish' ) );
	$blockedNoFile = 'publish' !== get_post_status( $probe );
	if ( $moved ) {
		rename( $probeFile . '.bak', $probeFile );
	}
	$t( 'referans yayın hazırlığı (gerçek WP): logo dosyası okunamıyorsa kayıt YAYINLANAMAZ (taslak kalır); dosya geri konunca hazır', true === $moved && true === $blockedNoFile );
	foreach ( get_posts( array( 'post_type' => array( 'mb_sss', 'mb_referans' ), 'post_status' => 'draft', 'numberposts' => -1, 'fields' => 'ids' ) ) as $cid ) {
		wp_update_post( array( 'ID' => $cid, 'post_status' => 'publish' ) );
	}
	$gpv2 = $gsvc->preview_publish();
	$t( 'yayın kapısı (gerçek WP): SSS + referans kayıtları yayında (yayın hazırlığı: logo attachment geçerli) -> referanslar ve sss hazır (2); içerik bekleyen 0', 2 === $gpv2['summary']['ready'] && 0 === $gpv2['summary']['content_not_ready'] && 30 === $gpv2['summary']['published'], wp_json_encode( $gpv2['summary'] ) );
	$gpub = $gsvc->publish_pages( (string) $gpv2['plan_digest'], (string) $gpv2['phrase'], 2, get_current_user_id() );
	$t( 'yayın kapısı (gerçek WP): kalan 2 sayfa (referanslar, sss) içerik hazırken yayınlandı', true === $gpub['ok'] && 2 === $gpub['published'], wp_json_encode( $gpub ) );
	for ( $gi = 0; $gi < 5 && isset( $gpub['status'] ) && 'paused' === $gpub['status']; $gi++ ) {
		$gpub = $gsvc->publish_pages( (string) $gpv2['plan_digest'], (string) $gpv2['phrase'], (int) $gpub['remaining'], get_current_user_id() );
	}
	$gpages_pub = count( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_mb_import_source_key', 'fields' => 'ids' ) ) );
	$t( 'yayın kapısı (gerçek WP): sonuç 32 yayında; referanslar ve sss dahil', 32 === $gpages_pub && 'completed' === $gpub['status'], wp_json_encode( array( $gpages_pub, $gpub ) ) );
	// Logo attachment'ı bozulursa (dosya silinir) referanslar sayfası yayın kapısında geri düşer: unchanged olmayan referans -> not ready.
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_mb_import_logo_sha256', 'fields' => 'ids', 'numberposts' => -1 ) ) as $aid ) {
		wp_delete_attachment( (int) $aid, true );
	}
	$gcp2 = $gfactory->apply_service()->preview( 'content' );
	$t( 'logo doğrulaması (gerçek WP): logo attachment dosyaları silinirse referanslar unchanged SAYILMAZ (3 conflict; sahte hazır YOK)', 3 === $gcp2['summary']['operations']['conflict'] && false === $gcp2['summary']['applicable'], wp_json_encode( $gcp2['summary']['operations'] ) );
	$finish( 'Faz 12 pages aşaması + yayınlama WordPress runtime testi' );
	return;
}

if ( 'resume-start' === $phase ) {
	$svc = $svcOf( $B4 );
	list( , $st ) = $startStage( $svc, 'sectors' );
	file_put_contents( '/tmp/mbfx-b4-run.txt', (string) $st['run_uid'] );
	echo 'RESULT ' . wp_json_encode( array( 'ok' => $st['ok'], 'status' => $st['status'], 'checkpoint' => $st['checkpoint'], 'total' => $st['total'], 'sectors' => $sectors(), 'pid' => getmypid() ) ) . "\n";
	return;
}
if ( 'resume-advance' === $phase ) {
	$uid = trim( (string) file_get_contents( '/tmp/mbfx-b4-run.txt' ) );
	$cp  = isset( $args[1] ) ? (int) $args[1] : 0;
	$r   = $svcOf( $B4 )->advance_apply( $uid, $cp, get_current_user_id() );
	echo 'RESULT ' . wp_json_encode( array( 'ok' => $r['ok'], 'status' => $r['status'], 'error_code' => $r['error_code'], 'checkpoint' => $r['checkpoint'], 'committed' => $r['committed'], 'remaining' => $r['remaining'], 'sectors' => $sectors(), 'pid' => getmypid() ) ) . "\n";
	return;
}
if ( 'resume-final' === $phase ) {
	$uid = trim( (string) file_get_contents( '/tmp/mbfx-b4-run.txt' ) );
	$row = $runRow( $uid );
	echo 'RESULT ' . wp_json_encode( array( 'status' => $row['status'], 'batches' => (int) $row['committed_batches'], 'items' => (int) $row['committed_items'], 'run_items' => $count( 'mb_import_run_items', 'run_id = ' . (int) $row['id'] ), 'sectors' => $sectors(), 'unchanged' => $svcOf( $B4 )->preview_stage( 'sectors' )['summary']['operations']['unchanged'] ) ) . "\n";
	@unlink( '/tmp/mbfx-b4-run.txt' );
	return;
}

echo "HATA: bilinmeyen faz (map|map-race-*|stages|sector-rollback|faults|resume-start|resume-advance|resume-final)\n";
