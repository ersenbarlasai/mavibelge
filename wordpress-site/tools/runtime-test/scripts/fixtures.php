<?php
/**
 * TEST FIXTURE YAZIMI — YALNIZ izole, silinebilir runtime test veritabanında
 * `wp eval-file` ile çalıştırılır. Üretim kodu DEĞİLDİR; mavibelge-core'a
 * eklenmez. Faz 6B2 dry-run kodunun davranışından AYRI tutulur: bu betik
 * dry-run'dan ÖNCE koşar ve dry-run öncesi veritabanı başlangıç noktası
 * bu betik bittikten SONRA alınır.
 *
 * Normal değerler WordPress API'leriyle (wp_insert_term/wp_insert_post/
 * update_*_meta) yazılır. Bozuk (dizi/nesne) meta değerleri, gerçek bir
 * veritabanı bozulmasını modellemek için $wpdb->insert ile serileştirilmiş
 * hâlde doğrudan meta tablosuna yazılır (kayıtlı sanitize callback'leri
 * atlanır). Beklenen kararlar bu betik tarafından ÖNCEDEN yazılır; gerçek
 * dry-run sonucu sonra bununla karşılaştırılır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$load = MaviBelge_Core_Import_Manifest_Loader::load_all();
if ( ! $load['ok'] ) {
	fwrite( STDERR, "manifest yüklenemedi\n" );
	exit( 1 );
}
$M = $load['manifest'];

$expect = array(); // source_key => array(decision, reason, scenario)
$log    = array();

function mbf_find( array $records, $key, $value ) {
	foreach ( $records as $r ) {
		if ( $r[ $key ] === $value ) {
			return $r;
		}
	}
	throw new RuntimeException( "kayıt yok: {$key}={$value}" );
}

function mbf_raw_meta( $kind, $objectId, $metaKey, $value ) {
	global $wpdb;
	if ( 'term' === $kind ) {
		$wpdb->insert( $wpdb->termmeta, array( 'term_id' => $objectId, 'meta_key' => $metaKey, 'meta_value' => maybe_serialize( $value ) ) );
		wp_cache_delete( $objectId, 'term_meta' );
	} else {
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $objectId, 'meta_key' => $metaKey, 'meta_value' => maybe_serialize( $value ) ) );
		wp_cache_delete( $objectId, 'post_meta' );
	}
}

function mbf_sector_projection( array $rec, $imageId ) {
	$deps = $imageId ? array( 'image_attachment_id' => $imageId ) : array();
	return MaviBelge_Core_Import_Managed_Fields::project_sector( $rec, $deps )['fields'];
}

function mbf_qual_projection( array $rec, $sectorTermId ) {
	return MaviBelge_Core_Import_Managed_Fields::project_qualification( $rec, array( 'sector_term_id' => $sectorTermId ) )['fields'];
}

function mbf_fee_projection( array $rec, $qualPostId ) {
	$v    = MaviBelge_Core_Import_Record_Validator::validate_fee( $rec );
	$deps = '' !== $rec['qualification_code'] ? array( 'qualification_post_id' => $qualPostId ) : array();
	return MaviBelge_Core_Import_Managed_Fields::project_fee( $rec, $deps, $v['normalized_price_options'] )['fields'];
}

/** Sektör terimi: alanlar + isteğe bağlı marker/hash (normal API ile). */
function mbf_make_sector_term( array $f, $marker = null, $hash = null ) {
	$r = wp_insert_term( $f['name'], 'mb_sektor', array( 'slug' => $f['slug'], 'description' => $f['description'] ) );
	if ( is_wp_error( $r ) ) {
		throw new RuntimeException( 'term: ' . $r->get_error_message() );
	}
	$tid = (int) $r['term_id'];
	update_term_meta( $tid, '_mb_icon_key', $f['icon_key'] );
	update_term_meta( $tid, '_mb_image_attachment_id', mbf_meta_value( $f['image_attachment_id'] ) );
	if ( null !== $marker ) {
		update_term_meta( $tid, '_mb_import_source_key', $marker );
	}
	if ( null !== $hash ) {
		update_term_meta( $tid, '_mb_last_applied_hash', $hash );
	}
	return $tid;
}

function mbf_make_post( $postType, $title ) {
	$pid = wp_insert_post( array( 'post_type' => $postType, 'post_status' => 'draft', 'post_title' => $title ), true );
	if ( is_wp_error( $pid ) ) {
		throw new RuntimeException( 'post: ' . $pid->get_error_message() );
	}
	return (int) $pid;
}

function mbf_make_qual_post( array $f, array $termIds, $marker = null, $hash = null ) {
	$pid = mbf_make_post( 'mb_yeterlilik', $f['title'] );
	update_post_meta( $pid, '_mb_myk_code', $f['myk_code'] );
	// _mb_level: eklentinin select sanitize'ı sayısal anahtarlı seçenekleri her zaman reddediyor
	// (runtime bulgusu) — kanonik DB temsili ("3") ham satırla yazılır.
	mbf_raw_meta( 'post', $pid, '_mb_level', mbf_meta_value( $f['level'] ) );
	update_post_meta( $pid, '_mb_revision', $f['revision'] );
	update_post_meta( $pid, '_mb_record_status', $f['record_status'] );
	wp_set_object_terms( $pid, $termIds, 'mb_sektor' );
	if ( null !== $marker ) {
		update_post_meta( $pid, '_mb_import_source_key', $marker );
	}
	if ( null !== $hash ) {
		update_post_meta( $pid, '_mb_last_applied_hash', $hash );
	}
	return $pid;
}

function mbf_fee_meta() {
	return array(
	'profession_name' => '_mb_profession_name', 'level' => '_mb_level', 'sector_slug' => '_mb_sector_slug',
	'qualification_post_id' => '_mb_qualification_id', 'qualification_code' => '_mb_qualification_code',
	'pricing_type' => '_mb_pricing_type', 'price_options' => '_mb_price_options', 'vat_included' => '_mb_vat_included',
	'certificate_print_fee_kurus' => '_mb_certificate_print_fee_kurus', 'source_name' => '_mb_source_name',
	'source_page' => '_mb_source_page', 'source_attachment_id' => '_mb_source_attachment_id',
	'tariff_period' => '_mb_tariff_period', 'record_status' => '_mb_record_status',
	'valid_from' => '_mb_valid_from', 'valid_until' => '_mb_valid_until',
	);
}

function mbf_make_fee_post( array $f, $marker = null, $hash = null, array $skip = array() ) {
	$pid = mbf_make_post( 'mb_ucret', $f['title'] );
	foreach ( mbf_fee_meta() as $field => $metaKey ) {
		if ( in_array( $field, $skip, true ) ) {
			continue;
		}
		if ( in_array( $field, array( 'level', 'certificate_print_fee_kurus' ), true ) ) {
			// level: yukarıdaki select bulgusu; kuruş alanı: kayıtlı sanitize TL girdisini kuruşa çevirir (x100).
			mbf_raw_meta( 'post', $pid, $metaKey, mbf_meta_value( $f[ $field ] ) );
			continue;
		}
		update_post_meta( $pid, $metaKey, mbf_meta_value( $f[ $field ] ) );
	}
	if ( null !== $marker ) {
		update_post_meta( $pid, '_mb_import_source_key', $marker );
	}
	if ( null !== $hash ) {
		update_post_meta( $pid, '_mb_last_applied_hash', $hash );
	}
	return $pid;
}

/**
 * WordPress formlarının yaptığı gibi skalerleri meta için string'e çevirir (int 3 -> "3", true -> "1", false -> "").
 * Eklentinin select sanitize'ı int değeri sessizce boşaltır (runtime gözlemi).
 */
function mbf_meta_value( $v ) {
	if ( is_bool( $v ) ) {
		return $v ? "1" : "";
	}
	return is_int( $v ) ? (string) $v : $v;
}

/** Aynı source_key için beklenti İKİ KEZ yazılırsa fixture hatasıdır — sessizce ezilmez. */
function mbf_expect( array &$expect, $key, array $value ) {
	if ( isset( $expect[ $key ] ) ) {
		throw new RuntimeException( "beklenti iki kez yazıldı: {$key}" );
	}
	$expect[ $key ] = $value;
}

function mbf_hash( array $fields ) {
	return MaviBelge_Core_Import_Hash::hash( $fields );
}

// --- Medya: gerçek attachment ve attachment OLMAYAN normal yazı. ---
$attachmentId = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'fixture-sektor-gorseli', 'post_status' => 'inherit' ), false );
$notAttachmentId = mbf_make_post( 'post', 'fixture-attachment-olmayan-yazi' );
$log['attachment_id'] = $attachmentId;
$log['not_attachment_id'] = $notAttachmentId;

$S = function ( $slug ) use ( $M ) { return mbf_find( $M['sectors'], 'slug', $slug ); };
$Q = function ( $code ) use ( $M ) { return mbf_find( $M['qualifications'], 'code', $code ); };

// --- Sektör senaryoları ---
// Senaryo 2: plastik (görselsiz) — alanlar = projeksiyon, marker + doğru hash -> unchanged.
$f = mbf_sector_projection( $S( 'plastik' ), 0 );
$terms['plastik'] = mbf_make_sector_term( $f, 'sector:plastik', mbf_hash( $f ) );
mbf_expect( $expect, 'sector:plastik', array( 'unchanged', 'hash_match', '2 unchanged' ) );

// Senaryo 3: mobilya — mevcut açıklama eski, hash = mevcut alanların hash'i -> update.
$f   = mbf_sector_projection( $S( 'mobilya' ), 0 );
$old = $f;
$old['description'] = 'Eski açıklama (fixture)';
$terms['mobilya'] = mbf_make_sector_term( $old, 'sector:mobilya', mbf_hash( $old ) );
mbf_expect( $expect, 'sector:mobilya', array( 'update', 'safe_update', '3 update' ) );

// Senaryo 4: is-makineleri — marker var, hash yok -> legacy conflict.
$f = mbf_sector_projection( $S( 'is-makineleri' ), 0 );
$terms['is-makineleri'] = mbf_make_sector_term( $f, 'sector:is-makineleri', null );
mbf_expect( $expect, 'sector:is-makineleri', array( 'conflict', 'legacy_missing_hash', '4 legacy hash yok' ) );

// Senaryo 7a: guzellik-sac-bakim — marker YALNIZ serileştirilmiş dizi olarak -> meta_value sorgusuyla bulunamaz.
$f = mbf_sector_projection( $S( 'guzellik-sac-bakim' ), 0 );
$terms['guzellik-sac-bakim'] = mbf_make_sector_term( $f, null, null );
mbf_raw_meta( 'term', $terms['guzellik-sac-bakim'], '_mb_import_source_key', array( 'sector:guzellik-sac-bakim' ) );
mbf_expect( $expect, 'sector:guzellik-sac-bakim', array( 'create', 'no_target', '7a marker yalnız dizi -> keşfedilemez (gözlem)' ) );

// Senaryo 8b: insaat (görselli, gerçek attachment) — marker + biçimsiz string hash -> legacy (sözleşme).
$f = mbf_sector_projection( $S( 'insaat' ), $attachmentId );
$terms['insaat'] = mbf_make_sector_term( $f, 'sector:insaat', 'bu-bir-hash-degil' );
mbf_expect( $expect, 'sector:insaat', array( 'conflict', 'legacy_missing_hash', '8b biçimsiz string hash' ) );

// Senaryo 5: makine — aynı marker iki terimde -> duplicate.
$f = mbf_sector_projection( $S( 'makine' ), $attachmentId );
$terms['makine'] = mbf_make_sector_term( $f, 'sector:makine', mbf_hash( $f ) );
$dup = $f;
$dup['slug'] = 'makine-kopya';
$dup['name'] = 'Makine (kopya fixture)';
$terms['makine-kopya'] = mbf_make_sector_term( $dup, 'sector:makine', null );
mbf_expect( $expect, 'sector:makine', array( 'conflict_duplicate_target', 'duplicate_target', '5 duplicate' ) );

// Senaryo 14: metalurji — görsel ID attachment olmayan normal yazı -> blocked.
$f = mbf_sector_projection( $S( 'metalurji' ), 0 );
$f['image_attachment_id'] = $notAttachmentId;
$terms['metalurji'] = mbf_make_sector_term( $f, null, null );
mbf_expect( $expect, 'sector:metalurji', array( 'blocked_dependency', 'dependency_unresolved', '14 görsel attachment değil' ) );

// Senaryo 15: metal — görsel gerçek attachment, marker yok -> create (bağımlılık çözüldü).
$f = mbf_sector_projection( $S( 'metal' ), $attachmentId );
$terms['metal'] = mbf_make_sector_term( $f, null, null );
mbf_expect( $expect, 'sector:metal', array( 'create', 'no_target', '15 gerçek attachment' ) );

// Senaryo 8a: lojistik — marker + hash serileştirilmiş dizi -> invalid_target_state.
$f = mbf_sector_projection( $S( 'lojistik' ), $attachmentId );
$terms['lojistik'] = mbf_make_sector_term( $f, 'sector:lojistik', null );
mbf_raw_meta( 'term', $terms['lojistik'], '_mb_last_applied_hash', array( mbf_hash( $f ) ) );
mbf_expect( $expect, 'sector:lojistik', array( 'conflict', 'invalid_target_state', '8a hash dizi' ) );

// Senaryo 9 (sektör): enerji — icon_key serileştirilmiş dizi, geçerli hash -> invalid_target_state.
$f = mbf_sector_projection( $S( 'enerji' ), $attachmentId );
$terms['enerji'] = mbf_make_sector_term( array_merge( $f, array( 'icon_key' => '' ) ), 'sector:enerji', mbf_hash( $f ) );
delete_term_meta( $terms['enerji'], '_mb_icon_key' );
mbf_raw_meta( 'term', $terms['enerji'], '_mb_icon_key', array( 'bolt' ) );
mbf_expect( $expect, 'sector:enerji', array( 'conflict', 'invalid_target_state', '9 icon_key dizi' ) );

// Senaryo 7b: cam — marker satırları [dizi, 'sector:cam'] -> bulunur ama ilk (single) değer dizi -> invalid_target_state.
$f = mbf_sector_projection( $S( 'cam' ), $attachmentId );
$terms['cam'] = mbf_make_sector_term( $f, null, mbf_hash( $f ) );
mbf_raw_meta( 'term', $terms['cam'], '_mb_import_source_key', array( 'sector:cam' ) );
mbf_raw_meta( 'term', $terms['cam'], '_mb_import_source_key', 'sector:cam' );
mbf_expect( $expect, 'sector:cam', array( 'conflict', 'invalid_target_state', '7b marker dizi (ilk satır)' ) );

// Senaryo 6 (sektör): tekstil marker'ı bir mb_ucret yazısında -> wrong type.
$wrongFee = mbf_make_post( 'mb_ucret', 'fixture yanlış tür (sektör marker)' );
// Kayıtlı sanitize mb_ucret üzerinde fee: önekli olmayan marker'ı boşaltır — çapraz-tür marker
// ancak DB düzeyinde (bozulma/elle SQL) oluşabilir; ham satırla modellenir.
mbf_raw_meta( 'post', $wrongFee, '_mb_import_source_key', 'sector:tekstil' );
mbf_expect( $expect, 'sector:tekstil', array( 'conflict_wrong_target_type', 'wrong_target_type', '6 sektör marker ücrette' ) );

// maden, mermer: dokunulmadı -> görsel çözülemez -> blocked.
mbf_expect( $expect, 'sector:maden', array( 'blocked_dependency', 'dependency_unresolved', '11 hedef yok, görsel yok' ) );
mbf_expect( $expect, 'sector:mermer', array( 'blocked_dependency', 'dependency_unresolved', '11 hedef yok, görsel yok' ) );

// --- Yeterlilik senaryoları ---
$qf = mbf_qual_projection( $Q( '12UY0069-3/02' ), $terms['plastik'] );
$quals['12UY0069-3/02'] = mbf_make_qual_post( $qf, array( $terms['plastik'] ), 'qualification:12UY0069-3/02', mbf_hash( $qf ) );
mbf_expect( $expect, 'qualification:12UY0069-3/02', array( 'unchanged', 'hash_match', '2 unchanged (post)' ) );

// Senaryo 10: level taşan string (doğrudan meta) -> invalid_target_state.
$qf = mbf_qual_projection( $Q( '13UY0143-3/01' ), $terms['plastik'] );
$pid = mbf_make_post( 'mb_yeterlilik', $qf['title'] );
foreach ( array( '_mb_myk_code' => $qf['myk_code'], '_mb_revision' => $qf['revision'], '_mb_record_status' => $qf['record_status'], '_mb_import_source_key' => 'qualification:13UY0143-3/01', '_mb_last_applied_hash' => mbf_hash( $qf ) ) as $k => $v ) {
	update_post_meta( $pid, $k, $v );
}
mbf_raw_meta( 'post', $pid, '_mb_level', '99999999999999999999' );
wp_set_object_terms( $pid, array( $terms['plastik'] ), 'mb_sektor' );
mbf_expect( $expect, 'qualification:13UY0143-3/01', array( 'conflict', 'invalid_target_state', '10 level taşma' ) );

// Senaryo 17: iki sektör terimi -> invalid_target_state; sıfır terim -> invalid_target_state.
$qf = mbf_qual_projection( $Q( '17UY0301-3/00' ), $terms['mobilya'] );
mbf_make_qual_post( $qf, array( $terms['mobilya'], $terms['plastik'] ), 'qualification:17UY0301-3/00', mbf_hash( $qf ) );
mbf_expect( $expect, 'qualification:17UY0301-3/00', array( 'conflict', 'invalid_target_state', '17 iki sektör terimi' ) );
$qf = mbf_qual_projection( $Q( '17UY0301-4/00' ), $terms['mobilya'] );
mbf_make_qual_post( $qf, array(), 'qualification:17UY0301-4/00', mbf_hash( $qf ) );
mbf_expect( $expect, 'qualification:17UY0301-4/00', array( 'conflict', 'invalid_target_state', '17 sıfır sektör terimi' ) );

// Senaryo 9 (post): record_status serileştirilmiş dizi -> invalid_target_state.
$qf = mbf_qual_projection( $Q( '16UY0245-4/02' ), $terms['guzellik-sac-bakim'] );
$pid = mbf_make_qual_post( $qf, array( $terms['guzellik-sac-bakim'] ), 'qualification:16UY0245-4/02', mbf_hash( $qf ) );
delete_post_meta( $pid, '_mb_record_status' );
mbf_raw_meta( 'post', $pid, '_mb_record_status', array( 'active' ) );
$quals['16UY0245-4/02'] = $pid;
mbf_expect( $expect, 'qualification:16UY0245-4/02', array( 'conflict', 'invalid_target_state', '9 record_status dizi' ) );

// Ücret bağımlılığı için unchanged yeterlilik.
$qf = mbf_qual_projection( $Q( '16UY0244-4/02' ), $terms['guzellik-sac-bakim'] );
$quals['16UY0244-4/02'] = mbf_make_qual_post( $qf, array( $terms['guzellik-sac-bakim'] ), 'qualification:16UY0244-4/02', mbf_hash( $qf ) );
mbf_expect( $expect, 'qualification:16UY0244-4/02', array( 'unchanged', 'hash_match', '2 unchanged (ücret bağımlılığı)' ) );

// Senaryo 13: aynı MYK koduyla iki marker'sız yazı -> resolver null + diagnostic.
$qf = mbf_qual_projection( $Q( '18UY0344-4/00' ), $terms['guzellik-sac-bakim'] );
mbf_make_qual_post( $qf, array( $terms['guzellik-sac-bakim'] ) );
mbf_make_qual_post( $qf, array( $terms['guzellik-sac-bakim'] ) );
mbf_expect( $expect, 'qualification:18UY0344-4/00', array( 'create', 'no_target', '13 marker yok (duplicate MYK yalnız bağımlılık tarafında)' ) );

// Ücret "update" senaryosunun bağımlılığı: marker'sız yeterlilik yazısı.
$qf = mbf_qual_projection( $Q( '17UY0280-3/01' ), $terms['guzellik-sac-bakim'] );
$quals['17UY0280-3/01'] = mbf_make_qual_post( $qf, array( $terms['guzellik-sac-bakim'] ) );
mbf_expect( $expect, 'qualification:17UY0280-3/01', array( 'create', 'no_target', '1 hedef yok' ) );

// Senaryo 6 (post): ücret marker'ı bir yeterlilik yazısında -> wrong type.
$feeWrong = null;
foreach ( $M['fees'] as $r ) {
	if ( 'mermer' === $r['sector_slug'] ) {
		$feeWrong = $r;
		break;
	}
}
$pid = mbf_make_post( 'mb_yeterlilik', 'fixture yanlış tür (ücret marker)' );
mbf_raw_meta( 'post', $pid, '_mb_import_source_key', $feeWrong['source_key'] );
mbf_expect( $expect, $feeWrong['source_key'], array( 'conflict_wrong_target_type', 'wrong_target_type', '6 ücret marker yeterlilikte' ) );

// --- Ücret senaryoları ---
$feesByCode = array();
foreach ( $M['fees'] as $r ) {
	$feesByCode[ $r['qualification_code'] ][] = $r;
}
// unchanged
$fr = $feesByCode['16UY0244-4/02'][0];
$ff = mbf_fee_projection( $fr, $quals['16UY0244-4/02'] );
mbf_make_fee_post( $ff, $fr['source_key'], mbf_hash( $ff ) );
mbf_expect( $expect, $fr['source_key'], array( 'unchanged', 'hash_match', '2 unchanged (ücret)' ) );

// update: belge basım ücreti eski
$fr  = $feesByCode['17UY0280-3/01'][0];
$ff  = mbf_fee_projection( $fr, $quals['17UY0280-3/01'] );
$old = $ff;
$old['certificate_print_fee_kurus'] = $ff['certificate_print_fee_kurus'] + 100;
mbf_make_fee_post( $old, $fr['source_key'], mbf_hash( $old ) );
mbf_expect( $expect, $fr['source_key'], array( 'update', 'safe_update', '3 update (ücret)' ) );

// Senaryo 9 (ücret): source_name serileştirilmiş dizi.
$fr = $feesByCode['16UY0245-4/02'][0];
$ff = mbf_fee_projection( $fr, $quals['16UY0245-4/02'] );
$pid = mbf_make_fee_post( $ff, $fr['source_key'], mbf_hash( $ff ), array( 'source_name' ) );
mbf_raw_meta( 'post', $pid, '_mb_source_name', array( 'x' ) );
mbf_expect( $expect, $fr['source_key'], array( 'conflict', 'invalid_target_state', '9 source_name dizi' ) );

// Senaryo 13: duplicate MYK koduna bağlı ücret -> blocked.
foreach ( $feesByCode['18UY0344-4/00'] as $fr ) {
	mbf_expect( $expect, $fr['source_key'], array( 'blocked_dependency', 'dependency_unresolved', '13 duplicate MYK' ) );
}
// Senaryo 12: yeterlilik yazısı olmayan kodlu ücret -> blocked (örnek).
$fr = $feesByCode['18UY0356-4'][0];
mbf_expect( $expect, $fr['source_key'], array( 'blocked_dependency', 'dependency_unresolved', '12 yeterlilik yok' ) );
// Bağımlılığı çözülen, hedefi olmayan ücret -> create.
foreach ( $feesByCode['12UY0069-3/02'] as $fr ) {
	mbf_expect( $expect, $fr['source_key'], array( 'create', 'no_target', '1 hedef yok, bağımlılık çözüldü' ) );
}
// Senaryo 16: 19 kodsuz ücret.
foreach ( $feesByCode[''] as $fr ) {
	if ( ! isset( $expect[ $fr['source_key'] ] ) ) {
		mbf_expect( $expect, $fr['source_key'], array( 'create', 'no_target', '16 kodsuz ücret' ) );
	}
}
// Senaryo 11: sektörü terimsiz yeterlilik (tekstil terimi yok) -> blocked (örnek).
mbf_expect( $expect, 'qualification:11UY0036-2/01', array( 'blocked_dependency', 'dependency_unresolved', '11 sektör terimi yok' ) );

$log['terms']   = $terms;
$log['counts']  = array( 'expect' => count( $expect ), 'codeless_fees' => count( $feesByCode[''] ) );
file_put_contents( '/tmp/mb-fixture-expect.json', wp_json_encode( array( 'expect' => $expect, 'log' => $log ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
echo wp_json_encode( $log['counts'] ), "\n";
