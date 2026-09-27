<?php
/**
 * Faz 6B4 — admin üzerinden güvenli, kesintiye dayanıklı apply/rollback ve sektör görsel eşleme.
 *
 * Saf PHP + bellek içi sahte WordPress dünyası (tests/support/import-apply-fakes.php). Beklenen değerler
 * LITERAL fixture'dır; üretim kodunun aynı yardımcısıyla hesaplanmaz. Gerçek WordPress davranışı
 * tools/runtime-test/scripts/admin-import-runtime.php ile ayrıca sınanır.
 *
 * Bölümler: A) sektör görsel eşleme sözleşmesi (Task 1).
 */

$B4_SI = 'MaviBelge_Core_Import_Sector_Image_Map';

/* ================================================================
 * A) Sektör görsel eşleme: kapalı zarf, manifest kapsamı, attachment kuralları
 * ================================================================ */
$b4_digest  = str_repeat( 'a', 64 );
$b4_records = array(
	array( 'source_key' => 'sector:alfa', 'slug' => 'alfa', 'image' => 'assets/images/content/alfa.png' ),
	array( 'source_key' => 'sector:beta', 'slug' => 'beta', 'image' => 'assets/images/content/beta.png' ),
	array( 'source_key' => 'sector:gama', 'slug' => 'gama', 'image' => 'assets/images/content/beta.png' ), // beta ile AYNI kaynak yolu.
	array( 'source_key' => 'sector:delta', 'slug' => 'delta', 'image' => '' ), // görselsiz.
);
$b4_good = array( 'schema_version' => '1.0.0', 'manifest_digest' => $b4_digest, 'mappings' => array( 'alfa' => 11, 'beta' => 12, 'gama' => 12 ) );
$b4_ok_attachment = function ( $id ) {
	return array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'mime' => 'image/png', 'readable' => true );
};
$b4_validate = function ( $raw, $inspect = null, $digest = null ) use ( $B4_SI, $b4_records, $b4_ok_attachment, $b4_digest ) {
	return $B4_SI::validate_envelope( $raw, $b4_records, null === $inspect ? $b4_ok_attachment : $inspect, null === $digest ? $b4_digest : $digest );
};
$b4_with = function ( array $patch, array $unset = array() ) use ( $b4_good ) {
	$r = array_merge( $b4_good, $patch );
	foreach ( $unset as $k ) {
		unset( $r[ $k ] );
	}
	return $r;
};
$b4_has_error = function ( array $result, $prefix ) {
	foreach ( $result['errors'] as $error ) {
		if ( 0 === strpos( $error, $prefix ) ) {
			return true;
		}
	}
	return false;
};

mb_test( '6B4 görsel-map: gerekli görsel kümesi manifest kayıtlarından türetilir (slug => kaynak yolu; görselsiz sektör yok)',
	array( 'alfa' => 'assets/images/content/alfa.png', 'beta' => 'assets/images/content/beta.png', 'gama' => 'assets/images/content/beta.png' ) === $B4_SI::required_image_sources( $b4_records ) );
$b4_ok = $b4_validate( $b4_good );
mb_test( '6B4 görsel-map: geçerli tam map kabul edilir (mappings int, digest 64-hex, hata yok)',
	true === $b4_ok['valid'] && array() === $b4_ok['errors'] && array( 'alfa' => 11, 'beta' => 12, 'gama' => 12 ) === $b4_ok['mappings'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $b4_ok['digest'] ) );
mb_test( '6B4 görsel-map: map digest deterministik ve içeriğe/manifest digest\'ine bağlı',
	$b4_ok['digest'] === $b4_validate( $b4_good )['digest']
	&& $b4_ok['digest'] !== $b4_validate( $b4_with( array( 'mappings' => array( 'alfa' => 13, 'beta' => 12, 'gama' => 12 ) ) ) )['digest'] );
$b4_missing = $b4_validate( $b4_with( array( 'mappings' => array( 'alfa' => 11, 'beta' => 12 ) ) ) );
mb_test( '6B4 görsel-map: eksik slug reddedilir (gama), map kabul edilmez',
	false === $b4_missing['valid'] && $b4_has_error( $b4_missing, 'missing_slug:gama' ) && array() === $b4_missing['mappings'] && null === $b4_missing['digest'] );
$b4_extra = $b4_validate( $b4_with( array( 'mappings' => array( 'alfa' => 11, 'beta' => 12, 'gama' => 12, 'zeta' => 14 ) ) ) );
mb_test( '6B4 görsel-map: fazla slug reddedilir (zeta manifestte yok)', false === $b4_extra['valid'] && $b4_has_error( $b4_extra, 'unexpected_slug:zeta' ) );
$b4_noimg = $b4_validate( $b4_with( array( 'mappings' => array( 'alfa' => 11, 'beta' => 12, 'gama' => 12, 'delta' => 15 ) ) ) );
mb_test( '6B4 görsel-map: manifestte görseli olmayan slug (delta) fazla sayılır ve reddedilir', false === $b4_noimg['valid'] && $b4_has_error( $b4_noimg, 'unexpected_slug:delta' ) );
$b4_bad_ids = array(
	'string "11"' => '11',
	'sıfır'       => 0,
	'negatif'     => -4,
	'float'       => 11.0,
	'bool'        => true,
	'null'        => null,
	'dizi'        => array( 11 ),
);
foreach ( $b4_bad_ids as $label => $badId ) {
	$r = $b4_validate( $b4_with( array( 'mappings' => array( 'alfa' => $badId, 'beta' => 12, 'gama' => 12 ) ) ) );
	mb_test( '6B4 görsel-map: geçersiz ID (' . $label . ') reddedilir — cast/düzeltme yok', false === $r['valid'] && $b4_has_error( $r, 'invalid_id:alfa' ) );
}
$b4_ver = $b4_validate( $b4_with( array( 'schema_version' => '2.0.0' ) ) );
mb_test( '6B4 görsel-map: yanlış schema_version reddedilir', false === $b4_ver['valid'] && $b4_has_error( $b4_ver, 'schema_version_mismatch' ) );
$b4_old = $b4_validate( $b4_with( array( 'manifest_digest' => str_repeat( 'b', 64 ) ) ) );
mb_test( '6B4 görsel-map: eski manifest digest\'i taşıyan map fail-closed reddedilir (manifest değişti)', false === $b4_old['valid'] && $b4_has_error( $b4_old, 'manifest_digest_mismatch' ) && array() === $b4_old['mappings'] );
$b4_baddig = $b4_validate( $b4_with( array( 'manifest_digest' => 'kısa' ) ) );
mb_test( '6B4 görsel-map: bozuk (64-hex olmayan) manifest digest reddedilir', false === $b4_baddig['valid'] && $b4_has_error( $b4_baddig, 'manifest_digest_mismatch' ) );
foreach ( array( 'dizi olmayan' => 'x', 'null' => null, 'int' => 5, 'boş dizi' => array() ) as $label => $raw ) {
	$r = $b4_validate( $raw );
	mb_test( '6B4 görsel-map: zarf ' . $label . ' -> reddedilir, fatal yok', false === $r['valid'] && array() === $r['mappings'] && null === $r['digest'] && array() !== $r['errors'] );
}
$b4_top = $b4_validate( $b4_with( array( 'notlar' => 'fazla anahtar' ) ) );
mb_test( '6B4 görsel-map: bilinmeyen üst anahtar reddedilir (kapalı zarf)', false === $b4_top['valid'] && $b4_has_error( $b4_top, 'unknown_top_keys' ) );
$b4_lack = $b4_validate( $b4_with( array(), array( 'manifest_digest' ) ) );
mb_test( '6B4 görsel-map: eksik üst anahtar reddedilir', false === $b4_lack['valid'] && $b4_has_error( $b4_lack, 'unknown_top_keys' ) );
$b4_mapsnot = $b4_validate( $b4_with( array( 'mappings' => 'x' ) ) );
mb_test( '6B4 görsel-map: mappings dizi değilse reddedilir', false === $b4_mapsnot['valid'] && $b4_has_error( $b4_mapsnot, 'mappings_not_array' ) );
$b4_list = $b4_validate( $b4_with( array( 'mappings' => array( 11, 12, 12 ) ) ) );
mb_test( '6B4 görsel-map: slug anahtarsız liste reddedilir (eksik+fazla slug)', false === $b4_list['valid'] );

/* --- attachment kuralları (enjekte edilen inceleyici; literal sonuçlar) --- */
$b4_inspect = function ( array $table ) {
	return function ( $id ) use ( $table ) {
		return array_key_exists( $id, $table ) ? $table[ $id ] : null;
	};
};
$b4_att = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'mime' => 'image/png', 'readable' => true );
$b4_table_ok = array( 11 => $b4_att, 12 => $b4_att );
mb_test( '6B4 görsel-map: gerçek image attachment kabul edilir', true === $b4_validate( $b4_good, $b4_inspect( $b4_table_ok ) )['valid'] );
$r = $b4_validate( $b4_good, $b4_inspect( array( 11 => array_merge( $b4_att, array( 'post_type' => 'post' ) ), 12 => $b4_att ) ) );
mb_test( '6B4 görsel-map: attachment olmayan post reddedilir', false === $r['valid'] && $b4_has_error( $r, 'attachment_wrong_type:alfa' ) );
$r = $b4_validate( $b4_good, $b4_inspect( array( 12 => $b4_att ) ) );
mb_test( '6B4 görsel-map: var olmayan/silinmiş kayıt reddedilir', false === $r['valid'] && $b4_has_error( $r, 'attachment_missing:alfa' ) );
$r = $b4_validate( $b4_good, $b4_inspect( array( 11 => array_merge( $b4_att, array( 'post_status' => 'trash' ) ), 12 => $b4_att ) ) );
mb_test( '6B4 görsel-map: çöpteki attachment reddedilir', false === $r['valid'] && $b4_has_error( $r, 'attachment_trashed:alfa' ) );
$r = $b4_validate( $b4_good, $b4_inspect( array( 11 => array_merge( $b4_att, array( 'mime' => 'text/plain' ) ), 12 => $b4_att ) ) );
mb_test( '6B4 görsel-map: image olmayan MIME (text/plain) reddedilir', false === $r['valid'] && $b4_has_error( $r, 'attachment_not_image:alfa' ) );
$r = $b4_validate( $b4_good, $b4_inspect( array( 11 => array_merge( $b4_att, array( 'mime' => 'application/pdf' ) ), 12 => $b4_att ) ) );
mb_test( '6B4 görsel-map: PDF attachment reddedilir', false === $r['valid'] && $b4_has_error( $r, 'attachment_not_image:alfa' ) );
$r = $b4_validate( $b4_good, $b4_inspect( array( 11 => array_merge( $b4_att, array( 'readable' => false ) ), 12 => $b4_att ) ) );
mb_test( '6B4 görsel-map: okunamayan dosya reddedilir', false === $r['valid'] && $b4_has_error( $r, 'attachment_unreadable:alfa' ) );
$r = $b4_validate( $b4_good, $b4_inspect( array( 11 => 'bozuk', 12 => $b4_att ) ) );
mb_test( '6B4 görsel-map: inceleyici bozuk şekil dönerse fail-closed reddedilir', false === $r['valid'] && $b4_has_error( $r, 'attachment_missing:alfa' ) );
$r = $b4_validate( $b4_good, function ( $id ) {
	throw new RuntimeException( 'sızıntı /mutlak/yol' );
} );
mb_test( '6B4 görsel-map: inceleyici exception atarsa reddedilir ve hata metni sızmaz',
	false === $r['valid'] && $b4_has_error( $r, 'attachment_missing:alfa' ) && false === strpos( implode( ' ', $r['errors'] ), 'mutlak' ) );

/* --- paylaşım kuralı --- */
$b4_share_ok = $b4_validate( $b4_with( array( 'mappings' => array( 'alfa' => 11, 'beta' => 12, 'gama' => 12 ) ) ) );
mb_test( '6B4 görsel-map: manifestte AYNI kaynak yolunu kullanan sektörler (beta,gama) aynı attachment\'ı paylaşabilir', true === $b4_share_ok['valid'] );
$b4_share_bad = $b4_validate( $b4_with( array( 'mappings' => array( 'alfa' => 12, 'beta' => 12, 'gama' => 12 ) ) ) );
mb_test( '6B4 görsel-map: FARKLI kaynak yollu sektörler (alfa,beta) aynı attachment\'a bağlanamaz', false === $b4_share_bad['valid'] && $b4_has_error( $b4_share_bad, 'shared_id_different_source' ) );
$b4_split = $b4_validate( $b4_with( array( 'mappings' => array( 'alfa' => 11, 'beta' => 12, 'gama' => 13 ) ) ), $b4_inspect( array( 11 => $b4_att, 12 => $b4_att, 13 => $b4_att ) ) );
mb_test( '6B4 görsel-map: aynı kaynağı paylaşan sektörlere FARKLI attachment atamak da kabul edilir (paylaşım zorunlu değil, yalnız serbest)', true === $b4_split['valid'] );

/* --- güncel manifestin görselli sektör kümesi (gerçek manifest) --- */
$b4_real = json_decode( (string) file_get_contents( dirname( __DIR__, 5 ) . '/data/content/sectors.manifest.json' ), true );
$b4_real_sources = is_array( $b4_real ) ? $B4_SI::required_image_sources( $b4_real['records'] ) : array();
$b4_real_slugs   = array_keys( $b4_real_sources );
sort( $b4_real_slugs );
mb_test( '6B4 görsel-map: GERÇEK sectors.manifest.json\'dan tam olarak 10 görselli sektör türetilir (makine, metalurji, metal, lojistik, enerji, cam, tekstil, insaat, maden, mermer)',
	array( 'cam', 'enerji', 'insaat', 'lojistik', 'maden', 'makine', 'mermer', 'metal', 'metalurji', 'tekstil' ) === $b4_real_slugs );
mb_test( '6B4 görsel-map: gerçek manifestte 10 sektör 9 benzersiz kaynak görsele bağlı; maden ve mermer AYNI kaynağı paylaşır',
	9 === count( array_unique( array_values( $b4_real_sources ) ) ) && $b4_real_sources['maden'] === $b4_real_sources['mermer'] );

/* ================================================================
 * B) Repository çözümleme önceliği + ortak runtime factory
 * ================================================================ */
$B4_RP = 'MaviBelge_Core_Import_WordPress_Target_Repository';
mb_test( '6B4 çözümleme: yalnız açık map ID\'si -> o ID; conflict yok',
	array( 'id' => 11, 'conflict' => false ) === $B4_RP::merge_sector_image_sources( 11, null ) && array( 'id' => 11, 'conflict' => false ) === $B4_RP::merge_sector_image_sources( 11, 0 ) );
mb_test( '6B4 çözümleme: yalnız term-meta ID\'si -> o ID (eski davranış korunur)', array( 'id' => 12, 'conflict' => false ) === $B4_RP::merge_sector_image_sources( null, 12 ) );
mb_test( '6B4 çözümleme: ikisi AYNI ID -> o ID, conflict yok', array( 'id' => 13, 'conflict' => false ) === $B4_RP::merge_sector_image_sources( 13, 13 ) );
mb_test( '6B4 çözümleme: ikisi FARKLI ID -> sessiz seçim YOK: id null + conflict', array( 'id' => null, 'conflict' => true ) === $B4_RP::merge_sector_image_sources( 11, 12 ) );
mb_test( '6B4 çözümleme: hiçbiri yok -> id null, conflict yok (blocked_dependency yolu)', array( 'id' => null, 'conflict' => false ) === $B4_RP::merge_sector_image_sources( null, null ) && array( 'id' => null, 'conflict' => false ) === $B4_RP::merge_sector_image_sources( 0, 0 ) );
mb_test( '6B4 çözümleme: string/float/negatif ID hiçbir kaynaktan kabul edilmez',
	array( 'id' => null, 'conflict' => false ) === $B4_RP::merge_sector_image_sources( '11', 1.5 ) && array( 'id' => null, 'conflict' => false ) === $B4_RP::merge_sector_image_sources( -3, true ) );

$b4_real_dir            = dirname( __DIR__, 5 ) . '/data/content';
$b4_real_sector_records = json_decode( (string) file_get_contents( $b4_real_dir . '/sectors.manifest.json' ), true )['records'];
$b4_real_full_map       = array( 'makine' => 1001, 'metalurji' => 1002, 'metal' => 1003, 'lojistik' => 1004, 'enerji' => 1005, 'cam' => 1006, 'tekstil' => 1007, 'insaat' => 1008, 'maden' => 1009, 'mermer' => 1009 );
$b4_real_env = function ( array $map ) use ( $b4_real_dir ) {
	$env                     = mb_fake_apply_env( $b4_real_dir );
	$env->world->attachments = array( 1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009 );
	$repo                    = new MB_Fake_World_Repository( $env->world, $map );
	$env->dryRun             = new MaviBelge_Core_Import_Dry_Run_Service( $repo, $b4_real_dir );
	$env->repo               = $repo;
	return $env;
};
$b4_none = $b4_real_env( array() )->dryRun->run_stage( 'all' )['plan']['summary'];
mb_test( '6B4 dry-run (GERÇEK manifest, map YOK): total 200, create 23, blocked 177, invalid 0, conflict 0, structurally_valid true, applicable false',
	200 === $b4_none['total'] && 23 === $b4_none['operations']['create'] && 177 === $b4_none['operations']['blocked'] && 0 === $b4_none['operations']['invalid'] && 0 === $b4_none['operations']['conflict']
	&& true === $b4_none['structurally_valid'] && false === $b4_none['applicable'] );
$b4_sec_none = $b4_real_env( array() )->dryRun->run_stage( 'sectors' )['plan']['summary'];
mb_test( '6B4 dry-run (GERÇEK manifest, map YOK): sectors aşaması 14 kayıt, create 4, blocked 10, applicable false',
	14 === $b4_sec_none['total'] && 4 === $b4_sec_none['operations']['create'] && 10 === $b4_sec_none['operations']['blocked'] && false === $b4_sec_none['applicable'] );
$b4_sec_full = $b4_real_env( $b4_real_full_map )->dryRun->run_stage( 'sectors' );
$b4_ops      = $b4_sec_full['plan']['summary']['operations'];
mb_test( '6B4 dry-run (GERÇEK manifest, TAM map): sectors aşaması total 14, create 14, blocked 0, invalid 0, conflict 0, applicable true',
	14 === $b4_sec_full['plan']['summary']['total'] && 14 === $b4_ops['create'] && 0 === $b4_ops['blocked'] && 0 === $b4_ops['invalid'] && 0 === $b4_ops['conflict'] && true === $b4_sec_full['plan']['summary']['applicable'] );
$b4_all_full = $b4_real_env( $b4_real_full_map )->dryRun->run_stage( 'all' )['plan']['summary'];
mb_test( '6B4 dry-run (GERÇEK manifest, TAM map, boş hedef): all aşaması 200 kayıt, create 33 (14 sektör + 19 kodsuz ücret), blocked 167 (83 yeterlilik + 84 kodlu ücret), applicable false',
	200 === $b4_all_full['total'] && 33 === $b4_all_full['operations']['create'] && 167 === $b4_all_full['operations']['blocked'] && false === $b4_all_full['applicable'] );
$b4_partial = $b4_real_env( array_diff_key( $b4_real_full_map, array( 'mermer' => 1 ) ) )->dryRun->run_stage( 'sectors' )['plan']['summary'];
mb_test( '6B4 dry-run: eksik tek eşleme (mermer) tek kaydı bloklar (9 görselli+4 görselsiz = 13 create, 1 blocked), sectors uygulanabilir olmaz',
	13 === $b4_partial['operations']['create'] && 1 === $b4_partial['operations']['blocked'] && false === $b4_partial['applicable'] );

/* açık map ile term-meta çelişkisi -> diagnostic + blocked */
$b4_conf                    = $b4_real_env( $b4_real_full_map );
$b4_conf->world->terms[900] = array( 'taxonomy' => 'mb_sektor', 'slug' => 'makine', 'name' => 'Makine', 'description' => '', 'parent' => 0, 'meta' => array( '_mb_image_attachment_id' => '1002' ) );
$b4_conf_run                = $b4_conf->dryRun->run_stage( 'sectors' );
$b4_conf_codes              = array_map(
	function ( $d ) {
		return $d['code'] . '|' . $d['source_key'];
	},
	$b4_conf->repo->get_diagnostics()
);
mb_test( '6B4 çözümleme: açık map (1001) ile mevcut term-meta (1002) çelişirse repository diagnostic üretir ve sektör bloklanır',
	in_array( 'sector_image_map_conflict|sector:makine', $b4_conf_codes, true ) && false === $b4_conf_run['plan']['summary']['applicable'] );

/* uçtan uca (sahte dünya, GERÇEK manifest, tam map): sectors apply -> terim meta -> tekrar önizleme unchanged */
$b4_apply        = $b4_real_env( $b4_real_full_map );
$b4_apply->apply = new MaviBelge_Core_Import_Apply_Service( $b4_apply->dryRun, $b4_apply->writer, $b4_apply->tx, $b4_apply->store, $b4_apply->audit );
$b4_pv           = $b4_apply->apply->preview( 'sectors' );
$b4_res          = $b4_apply->apply->apply( 'sectors', $b4_pv['plan_digest'], null, 1 );
$b4_meta_ids     = array();
foreach ( $b4_apply->world->terms as $t ) {
	if ( isset( $t['meta']['_mb_image_attachment_id'] ) && '' !== $t['meta']['_mb_image_attachment_id'] && '0' !== $t['meta']['_mb_image_attachment_id'] ) {
		$b4_meta_ids[ $t['slug'] ] = $t['meta']['_mb_image_attachment_id'];
	}
}
$b4_after = $b4_apply->apply->preview( 'sectors' );
mb_test( '6B4 apply (GERÇEK manifest, TAM map): 14 sektör yazıldı (10 görselli meta = map ID\'leri), sonraki önizleme 14 unchanged, applicable true, diagnostic yok',
	true === $b4_res['ok'] && 'completed' === $b4_res['status'] && 14 === count( $b4_apply->world->terms ) && '1001' === $b4_meta_ids['makine'] && '1009' === $b4_meta_ids['maden'] && '1009' === $b4_meta_ids['mermer'] && 10 === count( $b4_meta_ids )
	&& 14 === $b4_after['summary']['operations']['unchanged'] && array() === $b4_apply->repo->get_diagnostics() );

/* --- factory --- */
$b4_fx_records = $b4_real_sector_records;
$b4_fx_dig     = MaviBelge_Core_Import_Sector_Image_Map::manifest_digest_for( $b4_fx_records );
mb_test( '6B4 factory: sektör manifest digest\'i deterministik 64-hex; kayıt değişince değişir',
	1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $b4_fx_dig ) && $b4_fx_dig === MaviBelge_Core_Import_Sector_Image_Map::manifest_digest_for( $b4_fx_records )
	&& $b4_fx_dig !== MaviBelge_Core_Import_Sector_Image_Map::manifest_digest_for( array_slice( $b4_fx_records, 1 ) ) );
$b4_attach_ok = function ( $id ) {
	return $id >= 1001 && $id <= 1009 ? array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'mime' => 'image/png', 'readable' => true ) : null;
};
$b4_raw_good = MaviBelge_Core_Import_Sector_Image_Map::build_envelope( $b4_real_full_map, $b4_fx_dig );
$b4_factory  = function ( $raw, $inspector = null ) use ( $b4_real_dir, $b4_attach_ok ) {
	$world              = new MB_Fake_World();
	$world->attachments = array( 1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009 );
	return new MaviBelge_Core_Import_Runtime_Factory(
		array(
			'manifest_dir'         => $b4_real_dir,
			'image_map_raw'        => $raw,
			'attachment_inspector' => null === $inspector ? $b4_attach_ok : $inspector,
			'repository_factory'   => function ( array $map ) use ( $world ) {
				return new MB_Fake_World_Repository( $world, $map );
			},
			'writer'               => new MB_Fake_Writer( $world ),
			'tx'                   => new MB_Fake_Transaction( $world ),
			'store'                => new MB_Fake_Run_Store( $world ),
			'audit'                => new MB_Fake_Audit_Sink( $world ),
		)
	);
};
$b4_fa = $b4_factory( $b4_raw_good );
$b4_fb = $b4_factory( $b4_raw_good );
$b4_ra = $b4_fa->dry_run_service()->run_stage( 'sectors' );
$b4_rb = $b4_fb->dry_run_service()->run_stage( 'sectors' );
mb_test( '6B4 factory: iki bağımsız kurulum (CLI/admin bağlamı) aynı fixture için AYNI summary, entries, manifest digest ve plan digest\'i üretir',
	$b4_ra['plan']['summary'] === $b4_rb['plan']['summary'] && $b4_ra['plan']['entries'] === $b4_rb['plan']['entries'] && $b4_ra['manifest_digest'] === $b4_rb['manifest_digest']
	&& MaviBelge_Core_Import_Apply_Plan::plan_digest( 'sectors', $b4_ra['manifest_digest'], $b4_ra['plan'] ) === MaviBelge_Core_Import_Apply_Plan::plan_digest( 'sectors', $b4_rb['manifest_digest'], $b4_rb['plan'] )
	&& 14 === $b4_ra['plan']['summary']['operations']['create'] && true === $b4_ra['plan']['summary']['applicable'] );
mb_test( '6B4 factory: tek bağımlılık grafiği — her çağrı AYNI run store/writer/tx/audit/repository/servis örneklerini döndürür',
	$b4_fa->run_store() === $b4_fa->run_store() && $b4_fa->writer() === $b4_fa->writer() && $b4_fa->transaction() === $b4_fa->transaction() && $b4_fa->audit_sink() === $b4_fa->audit_sink()
	&& $b4_fa->repository() === $b4_fa->repository() && $b4_fa->dry_run_service() === $b4_fa->dry_run_service() && $b4_fa->apply_service() === $b4_fa->apply_service() && $b4_fa->rollback_service() === $b4_fa->rollback_service()
	&& $b4_fa->apply_service() instanceof MaviBelge_Core_Import_Apply_Service && $b4_fa->rollback_service() instanceof MaviBelge_Core_Import_Rollback_Service );
$b4_state = $b4_fa->image_map_state();
mb_test( '6B4 factory: geçerli map durumu present+valid, digest\'li, 10 slug', true === $b4_state['present'] && true === $b4_state['valid'] && 10 === count( $b4_state['mappings'] ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $b4_state['digest'] ) );
$b4_fs = $b4_factory( MaviBelge_Core_Import_Sector_Image_Map::build_envelope( $b4_real_full_map, str_repeat( '0', 64 ) ) );
$b4_ss = $b4_fs->image_map_state();
$b4_sr = $b4_fs->dry_run_service()->run_stage( 'sectors' )['plan']['summary'];
mb_test( '6B4 factory: eski manifest digest\'li map DOĞRULANMAZ ve repository\'ye HİÇ verilmez (görselli 10 sektör blocked, applicable false)',
	true === $b4_ss['present'] && false === $b4_ss['valid'] && array() === $b4_ss['mappings'] && 10 === $b4_sr['operations']['blocked'] && false === $b4_sr['applicable'] );
$b4_fn = $b4_factory( null );
mb_test( '6B4 factory: option yoksa present=false; dry-run map-yok davranışıyla aynı (4 create + 10 blocked)',
	false === $b4_fn->image_map_state()['present'] && 4 === $b4_fn->dry_run_service()->run_stage( 'sectors' )['plan']['summary']['operations']['create'] );
$b4_bad_attach = function ( $id ) {
	return array( 'post_type' => 'post', 'post_status' => 'publish', 'mime' => '', 'readable' => false );
};
$b4_fbad = $b4_factory( $b4_raw_good, $b4_bad_attach );
mb_test( '6B4 factory: attachment olmayan hedefli map doğrulanmaz, repository\'ye verilmez (10 blocked)',
	false === $b4_fbad->image_map_state()['valid'] && array() === $b4_fbad->image_map_state()['mappings'] && 10 === $b4_fbad->dry_run_service()->run_stage( 'sectors' )['plan']['summary']['operations']['blocked'] );

/* ================================================================
 * C) Run durum makinesi (ready/paused/rollback_ready/rollback_paused), plan snapshot, checkpoint CAS
 * ================================================================ */
$B4_S = 'MaviBelge_Core_Import_Run_State';
mb_test( '6B4 durum: on iki durum tam olarak tanımlı (eski sekiz + ready, paused, rollback_ready, rollback_paused)',
	array( 'planned', 'ready', 'running', 'paused', 'failed', 'completed', 'rollback_required', 'rollback_ready', 'rolling_back', 'rollback_paused', 'rolled_back', 'rollback_failed' ) === $B4_S::ALL );
$b4_allowed = array(
	array( 'ready', 'running' ), array( 'ready', 'failed' ), array( 'running', 'paused' ), array( 'paused', 'running' ), array( 'running', 'completed' ),
	array( 'running', 'failed' ), array( 'running', 'rollback_required' ), array( 'paused', 'rollback_required' ), array( 'paused', 'failed' ),
	array( 'completed', 'rollback_ready' ), array( 'rollback_required', 'rollback_ready' ), array( 'rollback_failed', 'rollback_ready' ),
	array( 'rollback_ready', 'rolling_back' ), array( 'rollback_ready', 'rollback_failed' ), array( 'rolling_back', 'rollback_paused' ), array( 'rollback_paused', 'rolling_back' ),
	array( 'rolling_back', 'rolled_back' ), array( 'rolling_back', 'rollback_failed' ), array( 'rollback_paused', 'rollback_failed' ),
	// eski (CLI) geçişler korunur
	array( 'planned', 'running' ), array( 'planned', 'failed' ), array( 'completed', 'rolling_back' ), array( 'rollback_required', 'rolling_back' ), array( 'rollback_failed', 'rolling_back' ),
);
$b4_all_ok = true;
foreach ( $b4_allowed as $pair ) {
	$b4_all_ok = $b4_all_ok && $B4_S::can_transition( $pair[0], $pair[1] );
}
mb_test( '6B4 durum: bağlayıcı akış geçişleri (ready→running→paused→running→completed; rollback_ready→rolling_back→rollback_paused→rolling_back→rolled_back) ve eski CLI geçişleri izinli', $b4_all_ok );
$b4_denied = array(
	array( 'ready', 'paused' ), array( 'ready', 'completed' ), array( 'paused', 'completed' ), array( 'paused', 'paused' ), array( 'running', 'ready' ), array( 'completed', 'running' ), array( 'completed', 'paused' ),
	array( 'failed', 'ready' ), array( 'failed', 'running' ), array( 'rolled_back', 'rollback_ready' ), array( 'rolled_back', 'rolling_back' ), array( 'rollback_ready', 'rolled_back' ),
	array( 'rollback_ready', 'rollback_paused' ), array( 'rollback_paused', 'rolled_back' ), array( 'rolling_back', 'rollback_ready' ), array( 'running', 'rollback_paused' ), array( 'paused', 'rolling_back' ),
	array( 'rollback_paused', 'running' ), array( 'ready', 'rollback_ready' ), array( 'READY', 'running' ), array( 'ready', 'RUNNING' ), array( null, 'ready' ), array( 'ready', array( 'running' ) ),
);
$b4_none_ok = true;
foreach ( $b4_denied as $pair ) {
	$b4_none_ok = $b4_none_ok && ! $B4_S::can_transition( $pair[0], $pair[1] );
}
mb_test( '6B4 durum: atlama, ters yön, terminal durumdan çıkış, apply/rollback karışımı ve tip/harf hataları reddedilir', $b4_none_ok );
mb_test( '6B4 durum: yeni apply\'ı engelleyen durumlar ready/running/paused/rollback_required/rollback_ready/rolling_back/rollback_paused (failed, completed, rolled_back, rollback_failed engellemez)',
	array( 'ready', 'running', 'paused', 'rollback_required', 'rollback_ready', 'rolling_back', 'rollback_paused' ) === $B4_S::BLOCKS_NEW_APPLY
	&& ! in_array( 'failed', $B4_S::BLOCKS_NEW_APPLY, true ) && ! in_array( 'completed', $B4_S::BLOCKS_NEW_APPLY, true ) && ! in_array( 'rolled_back', $B4_S::BLOCKS_NEW_APPLY, true ) && ! in_array( 'rollback_failed', $B4_S::BLOCKS_NEW_APPLY, true ) );
mb_test( '6B4 durum: yalnız running/rolling_back "etkin" (bayat kilit adayı); bekleme durumları (ready/paused/rollback_ready/rollback_paused) değil', array( 'running', 'rolling_back' ) === $B4_S::ACTIVE );
mb_test( '6B4 durum: rollback başlatılabilen durumlar completed/rollback_required/rollback_failed (yeni başlatma) — devam ettirilen rollback_ready/rollback_paused ayrı',
	array( 'completed', 'rollback_required', 'rollback_failed' ) === $B4_S::ROLLBACKABLE && array( 'rollback_ready', 'rollback_paused' ) === $B4_S::ROLLBACK_RESUMABLE && array( 'ready', 'paused' ) === $B4_S::APPLY_RESUMABLE );

/* --- plan snapshot şekli (SAF doğrulayıcı) --- */
$B4_PS = 'MaviBelge_Core_Import_Plan_Snapshot';
$b4_h  = function ( $c ) {
	return str_repeat( $c, 64 );
};
$b4_write_create = array( 'source_key' => 'sector:zz-test-a', 'type' => 'sector', 'decision' => 'create', 'target_id' => null, 'expected_incoming_hash' => $b4_h( 'a' ), 'expected_current_hash' => null, 'expected_last_applied_hash' => null );
$b4_write_update = array( 'source_key' => 'sector:zz-test-b', 'type' => 'sector', 'decision' => 'update', 'target_id' => 7, 'expected_incoming_hash' => $b4_h( 'b' ), 'expected_current_hash' => $b4_h( 'c' ), 'expected_last_applied_hash' => $b4_h( 'c' ) );
$b4_items = $B4_PS::from_writes( array( $b4_write_create, $b4_write_update ) );
mb_test( '6B4 snapshot: yazma listesinden sıralı (seq 1..n) ve kapalı şekilli item\'lar üretilir; create hedefsiz/natural_key none, update hedefli',
	is_array( $b4_items ) && 2 === count( $b4_items ) && 1 === $b4_items[0]['seq'] && 2 === $b4_items[1]['seq'] && 0 === $b4_items[0]['target_id'] && 'none' === $b4_items[0]['natural_key_check'] && 7 === $b4_items[1]['target_id'] && null === $b4_items[1]['natural_key_check']
	&& $b4_h( 'a' ) === $b4_items[0]['expected_incoming_hash'] && array() === $B4_PS::validate_items( $b4_items ) );
mb_test( '6B4 snapshot: item anahtar kümesi KAPALI ve içerik değeri taşımaz (yalnız source_key, tip, karar, hedef, sıra, beklenen hash\'ler, natural-key sonucu)',
	array( 'expected_current_hash', 'expected_incoming_hash', 'expected_last_applied_hash', 'natural_key_check', 'seq', 'source_key', 'target_id', 'type', 'decision' ) === array_values( array_intersect( array( 'expected_current_hash', 'expected_incoming_hash', 'expected_last_applied_hash', 'natural_key_check', 'seq', 'source_key', 'target_id', 'type', 'decision' ), array_keys( $b4_items[0] ) ) )
	&& 9 === count( $b4_items[0] ) );
$b4_mut = function ( array $patch, $index = 0, array $unset = array() ) use ( $b4_items, $B4_PS ) {
	$items = $b4_items;
	$items[ $index ] = array_merge( $items[ $index ], $patch );
	foreach ( $unset as $k ) {
		unset( $items[ $index ][ $k ] );
	}
	return $B4_PS::validate_items( $items );
};
mb_test( '6B4 snapshot: fazladan anahtar (ör. içerik alanı "fields"/"name") reddedilir', array() !== $b4_mut( array( 'fields' => array( 'name' => 'x' ) ) ) && array() !== $b4_mut( array( 'name' => 'TEST' ) ) );
mb_test( '6B4 snapshot: eksik anahtar reddedilir', array() !== $b4_mut( array(), 0, array( 'expected_incoming_hash' ) ) && array() !== $b4_mut( array(), 1, array( 'natural_key_check' ) ) );
mb_test( '6B4 snapshot: bozuk hash (kısa, büyük harf, dizi) reddedilir', array() !== $b4_mut( array( 'expected_incoming_hash' => 'abc' ) ) && array() !== $b4_mut( array( 'expected_incoming_hash' => str_repeat( 'A', 64 ) ) ) && array() !== $b4_mut( array( 'expected_current_hash' => array( 'x' ) ), 1 ) );
mb_test( '6B4 snapshot: update hedefsiz veya hash\'siz reddedilir; create hedefli/hash\'li reddedilir',
	array() !== $b4_mut( array( 'target_id' => 0 ), 1 ) && array() !== $b4_mut( array( 'expected_current_hash' => null ), 1 ) && array() !== $b4_mut( array( 'target_id' => 5 ), 0 ) && array() !== $b4_mut( array( 'expected_last_applied_hash' => $b4_h( 'd' ) ), 0 ) && array() !== $b4_mut( array( 'natural_key_check' => 'duplicate' ), 0 ) );
mb_test( '6B4 snapshot: yanlış sıra/tekrar eden seq ve tekrar eden source_key reddedilir',
	array() !== $b4_mut( array( 'seq' => 2 ), 0 ) && array() !== $b4_mut( array( 'seq' => 1 ), 1 ) && array() !== $b4_mut( array( 'source_key' => 'sector:zz-test-a' ), 1 ) );
mb_test( '6B4 snapshot: bilinmeyen tür/karar, yanlış aileli source_key reddedilir',
	array() !== $b4_mut( array( 'type' => 'bogus' ) ) && array() !== $b4_mut( array( 'decision' => 'delete' ) ) && array() !== $b4_mut( array( 'source_key' => 'fee:zz-test-a:1:x' ) ) );
mb_test( '6B4 snapshot: boş liste ve liste olmayan girdi reddedilir; from_writes bozuk yazmada null döner',
	array() !== $B4_PS::validate_items( array() ) && array() !== $B4_PS::validate_items( 'x' ) && null === $B4_PS::from_writes( array( array( 'source_key' => 'sector:zz-test-a' ) ) ) && null === $B4_PS::from_writes( array() ) );
mb_test( '6B4 snapshot: to_write() snapshot satırını apply yazma tanımına (Apply_Eligibility::evaluate_plan biçimi) BİREBİR çevirir',
	$b4_write_create === $B4_PS::to_write( $b4_items[0] ) && $b4_write_update === $B4_PS::to_write( $b4_items[1] ) );

/* --- fake store: create_run_with_plan, get_plan_items, checkpoint CAS --- */
$b4_world = new MB_Fake_World();
$b4_store = new MB_Fake_Run_Store( $b4_world );
$b4_run_data = array( 'stage' => 'sectors', 'plan_digest' => $b4_h( '1' ), 'manifest_digest' => $b4_h( '2' ), 'map_digest' => $b4_h( '3' ), 'batch_size' => 10, 'total_writes' => 2, 'created_by' => 5 );
$b4_run = $b4_store->create_run_with_plan( $b4_run_data, $b4_items );
mb_test( '6B4 store: create_run_with_plan run\'ı `ready` durumunda, sayaçlar 0, map digest\'li ve item\'larla birlikte oluşturur',
	is_array( $b4_run ) && 'ready' === $b4_run['status'] && 0 === $b4_run['committed_batches'] && 0 === $b4_run['committed_items'] && 0 === $b4_run['rollback_batches'] && 0 === $b4_run['rollback_items'] && $b4_h( '3' ) === $b4_run['map_digest'] && 2 === $b4_run['total_writes'] );
mb_test( '6B4 store: get_plan_items sıralı ve sayfalı (afterSeq/limit)',
	2 === count( $b4_store->get_plan_items( $b4_run['id'], 0, 10 ) ) && array( 1 ) === array_map( function ( $r ) {
		return $r['seq'];
	}, $b4_store->get_plan_items( $b4_run['id'], 0, 1 ) ) && array( 2 ) === array_map( function ( $r ) {
		return $r['seq'];
	}, $b4_store->get_plan_items( $b4_run['id'], 1, 5 ) ) && array() === $b4_store->get_plan_items( $b4_run['id'], 2, 5 ) && array() === $b4_store->get_plan_items( 999, 0, 5 ) );
mb_test( '6B4 store: bozuk plan (fazladan anahtar) run OLUŞTURMAZ', null === $b4_store->create_run_with_plan( $b4_run_data, array( array_merge( $b4_items[0], array( 'fields' => array() ) ) ) ) && 1 === count( $b4_world->runs ) );
mb_test( '6B4 store: `ready` durumunda checkpoint yazılamaz (yalnız running); beklenen checkpoint uyuşmazsa CAS reddeder',
	false === $b4_store->record_checkpoint( $b4_run['id'], 1, 1, 0 ) && true === $b4_store->transition( $b4_run['id'], 'ready', 'running' )
	&& false === $b4_store->record_checkpoint( $b4_run['id'], 2, 2, 1 ) && true === $b4_store->record_checkpoint( $b4_run['id'], 1, 1, 0 ) && false === $b4_store->record_checkpoint( $b4_run['id'], 1, 1, 0 ) );
mb_test( '6B4 store: eski (4. parametresiz) record_checkpoint çağrısı geriye dönük uyumlu (CAS yok)', true === $b4_store->record_checkpoint( $b4_run['id'], 2, 2 ) && 2 === $b4_store->get_run( $b4_run['uid'] )['committed_batches'] );
mb_test( '6B4 store: aynı durumdan iki eşzamanlı geçiş talebinden yalnız biri başarılı (CAS)',
	true === $b4_store->transition( $b4_run['id'], 'running', 'paused' ) && false === $b4_store->transition( $b4_run['id'], 'running', 'paused' ) && true === $b4_store->transition( $b4_run['id'], 'paused', 'running' ) && false === $b4_store->transition( $b4_run['id'], 'paused', 'running' ) );
$b4_rb_run = $b4_store->create_run_with_plan( $b4_run_data, $b4_items );
$b4_store->transition( $b4_rb_run['id'], 'ready', 'running' );
$b4_store->record_checkpoint( $b4_rb_run['id'], 1, 2 );
$b4_store->transition( $b4_rb_run['id'], 'running', 'completed' );
$b4_store->transition( $b4_rb_run['id'], 'completed', 'rollback_ready' );
mb_test( '6B4 store: rollback checkpoint yalnız rolling_back durumunda ve beklenen sayaçla yazılır; geriye gidemez',
	false === $b4_store->record_rollback_checkpoint( $b4_rb_run['id'], 0, 1, 2 ) && true === $b4_store->transition( $b4_rb_run['id'], 'rollback_ready', 'rolling_back' )
	&& false === $b4_store->record_rollback_checkpoint( $b4_rb_run['id'], 1, 2, 2 ) && true === $b4_store->record_rollback_checkpoint( $b4_rb_run['id'], 0, 1, 1 )
	&& false === $b4_store->record_rollback_checkpoint( $b4_rb_run['id'], 0, 1, 1 ) && false === $b4_store->record_rollback_checkpoint( $b4_rb_run['id'], 1, 0, 0 ) && true === $b4_store->record_rollback_checkpoint( $b4_rb_run['id'], 1, 2, 2 )
	&& 2 === $b4_store->get_run( $b4_rb_run['uid'] )['rollback_batches'] && 2 === $b4_store->get_run( $b4_rb_run['uid'] )['rollback_items'] );
/* transaction: run + snapshot birlikte geri alınır */
$b4_tx = new MB_Fake_Transaction( $b4_world );
$b4_before = count( $b4_world->runs );
$b4_tx->begin();
$b4_store->create_run_with_plan( $b4_run_data, $b4_items );
$b4_tx->rollback();
mb_test( '6B4 store: create_run_with_plan transaction geri alınırsa run VE snapshot birlikte kaybolur (yarım run kalmaz)', $b4_before === count( $b4_world->runs ) );

/* ================================================================
 * D) Resumable apply: start (ready + snapshot), advance (bir istek = bir batch), checkpoint, idempotency
 * ================================================================ */
$b4_env25 = mb_apply_fixture_envelopes_with_sectors( 25 );
$b4_dirs = 0;
$b4_fresh_dir = function () use ( $b4_env25, &$b4_dirs ) {
	$dir = mb6b2_temp_dir( 'b4res' . ( $b4_dirs++ ) );
	mb_apply_fixture_write_dir( $dir, $b4_env25 );
	return $dir;
};
$b4_env = function ( $dir = null ) use ( $b4_fresh_dir ) {
	$dir      = null === $dir ? $b4_fresh_dir() : $dir;
	$env      = mb_fake_apply_env( $dir );
	$env->dir = $dir;
	return $env;
};
/** Aynı sahte dünyayı paylaşan YENİ servis grafiği (yeni HTTP isteği / yeni PHP süreci benzetimi). */
$b4_new_process = function ( $env ) {
	$n         = new stdClass();
	$n->world  = $env->world;
	$n->dryRun = new MaviBelge_Core_Import_Dry_Run_Service( new MB_Fake_World_Repository( $env->world ), $env->dir );
	$n->tx     = new MB_Fake_Transaction( $env->world );
	$n->store  = new MB_Fake_Run_Store( $env->world );
	$n->audit  = new MB_Fake_Audit_Sink( $env->world );
	$n->store->installed = true;
	$n->apply  = new MaviBelge_Core_Import_Apply_Service( $n->dryRun, new MB_Fake_Writer( $env->world ), $n->tx, $n->store, $n->audit );
	return $n;
};
$b4_events = function ( $env ) {
	return array_map(
		function ( $a ) {
			return $a['event'];
		},
		$env->world->audit
	);
};
$b4_start = function ( $env, $stage = 'sectors', $size = 10, $mapDigest = null ) {
	$p = $env->apply->preview( $stage );
	return $env->apply->start_resumable( $stage, $p['plan_digest'], $size, 1, $mapDigest );
};
$b4_no_side_effects = function ( $env ) {
	return array() === $env->world->runs && array() === $env->world->planItems && array() === $env->world->items && array() === $env->world->writeLog && array() === $env->world->audit && array() === $env->world->terms;
};

/* --- start kapıları: hiçbir run/snapshot/yazma/audit oluşmaz --- */
$b4_e = $b4_env();
$b4_p = $b4_e->apply->preview( 'sectors' );
mb_test( '6B4 start: 25 sektörlük fixture önizlemesi uygulanabilir (25 create, digest 64-hex)', true === $b4_p['eligible'] && 25 === $b4_p['writes'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $b4_p['plan_digest'] ) );
$b4_gates = array(
	'invalid_stage'        => array( 'bogus', $b4_p['plan_digest'], 10 ),
	'invalid_batch_size'   => array( 'sectors', $b4_p['plan_digest'], 0 ),
	'invalid_confirmation' => array( 'sectors', 'kısa', 10 ),
	'confirmation_mismatch' => array( 'sectors', str_repeat( 'f', 64 ), 10 ),
);
foreach ( $b4_gates as $code => $args ) {
	$r = $b4_e->apply->start_resumable( $args[0], $args[1], $args[2], 1, null );
	mb_test( '6B4 start kapısı: ' . $code . ' -> reddedilir; run, snapshot, yazma, audit YOK', false === $r['ok'] && $code === $r['error_code'] && null === $r['run_uid'] && $b4_no_side_effects( $b4_e ) );
}
$r = $b4_e->apply->start_resumable( 'sectors', $b4_p['plan_digest'], 21, 1, null );
mb_test( '6B4 start kapısı: batch boyutu 21 (>20) reddedilir', 'invalid_batch_size' === $r['error_code'] && $b4_no_side_effects( $b4_e ) );
$b4_ce = $b4_env();
$b4_ce->world->terms[700] = array( 'taxonomy' => 'mb_sektor', 'slug' => 'zz-test-a', 'name' => 'Kullanıcı terimi', 'description' => '', 'parent' => 0, 'meta' => array() );
$b4_cp = $b4_ce->apply->preview( 'sectors' );
$r = $b4_ce->apply->start_resumable( 'sectors', (string) $b4_cp['plan_digest'], 10, 1, null );
mb_test( '6B4 start kapısı: conflict içeren plan (kullanıcının aynı slug\'lı terimi) uygulanamaz -> plan_not_applicable; run/yazma YOK', 'plan_not_applicable' === $r['error_code'] && array() === $b4_ce->world->runs && array() === $b4_ce->world->writeLog );
$b4_ue = $b4_env();
$b4_ue->world->runs[50] = array( 'id' => 50, 'uid' => sprintf( '%032x', 50 ), 'stage' => 'sectors', 'status' => 'paused', 'plan_digest' => str_repeat( '1', 64 ), 'manifest_digest' => str_repeat( '2', 64 ), 'batch_size' => 10, 'total_writes' => 3, 'committed_batches' => 1, 'committed_items' => 1, 'error_code' => null, 'created_by' => 1, 'created_at' => 'x', 'updated_at' => 'x', 'map_digest' => null, 'rollback_batches' => 0, 'rollback_items' => 0 );
$b4_up = $b4_ue->apply->preview( 'sectors' );
$b4_ur = $b4_ue->apply->start_resumable( 'sectors', $b4_up['plan_digest'], 10, 1, null );
mb_test( '6B4 start kapısı: çözülmemiş (paused) run varken yeni run başlatılamaz -> unresolved_run_exists; ikinci run ve snapshot YOK', 'unresolved_run_exists' === $b4_ur['error_code'] && 1 === count( $b4_ue->world->runs ) && array() === $b4_ue->world->planItems && false === $b4_ue->store->locked );
$b4_le = $b4_env();
$b4_le->store->locked = true;
$r = $b4_start( $b4_le );
mb_test( '6B4 start kapısı: kilit başkasında -> locked; hiçbir şey yazılmaz', 'locked' === $r['error_code'] && $b4_no_side_effects( $b4_le ) );
$b4_ie = $b4_env();
$b4_ie->tx->preflightOk = false;
$r = $b4_start( $b4_ie );
mb_test( '6B4 start kapısı: transaction ön kontrolü başarısız (MyISAM vb.) -> infrastructure_unavailable; hiçbir şey yazılmaz', 'infrastructure_unavailable' === $r['error_code'] && $b4_no_side_effects( $b4_ie ) );
$b4_ae = $b4_env();
$b4_ae->audit->readyFlag = false;
$r = $b4_start( $b4_ae );
mb_test( '6B4 start kapısı: audit hedefi hazır değil -> infrastructure_unavailable; hiçbir şey yazılmaz', 'infrastructure_unavailable' === $r['error_code'] && $b4_no_side_effects( $b4_ae ) );

/* --- start başarısı --- */
$b4_e1 = $b4_env();
$b4_s  = $b4_start( $b4_e1, 'sectors', 10, str_repeat( '9', 64 ) );
$b4_run1 = $b4_e1->store->get_run( (string) $b4_s['run_uid'] );
mb_test( '6B4 start: run `ready`, sayaç 0, total 25, remaining 25; HİÇBİR içerik yazılmadı ve run_started audit\'i henüz YOK (ilk advance\'te)',
	true === $b4_s['ok'] && 'ready' === $b4_s['status'] && 0 === $b4_s['checkpoint'] && 25 === $b4_s['total'] && 0 === $b4_s['committed_items'] && 25 === $b4_s['remaining'] && array() === $b4_e1->world->writeLog && array() === $b4_e1->world->terms && array() === $b4_e1->world->audit
	&& 'ready' === $b4_run1['status'] && str_repeat( '9', 64 ) === $b4_run1['map_digest'] && 10 === $b4_run1['batch_size'] );
$b4_rows = $b4_e1->store->get_plan_items( $b4_run1['id'], 0, 100 );
mb_test( '6B4 start: 25 snapshot item\'ı sıralı, kapalı şekilli, içerik değeri taşımıyor (create/hedefsiz/natural_key none)',
	25 === count( $b4_rows ) && array() === MaviBelge_Core_Import_Plan_Snapshot::validate_items( $b4_rows ) && 'sector:zz-test-a' === $b4_rows[0]['source_key'] && 'sector:zz-test-s25' === $b4_rows[24]['source_key'] && 'create' === $b4_rows[3]['decision'] && 'none' === $b4_rows[3]['natural_key_check']
	&& false === strpos( json_encode( $b4_rows ), 'Sahte test' ) && false === strpos( json_encode( $b4_rows ), 'TEST Sektör' ) );
mb_test( '6B4 start: kilit start sonunda bırakılır', false === $b4_e1->store->locked );

/* --- advance: bir istek = bir batch --- */
$uid1 = $b4_s['run_uid'];
$a1   = $b4_e1->apply->advance_resumable( $uid1, 0, 1, str_repeat( '9', 64 ) );
mb_test( '6B4 advance #1: yalnız 10 kayıt yazılır, run `paused`, checkpoint 1, committed 10, remaining 15; audit run_started + batch_committed',
	true === $a1['ok'] && 'paused' === $a1['status'] && 1 === $a1['checkpoint'] && 10 === $a1['committed_items'] && 15 === $a1['remaining'] && 25 === $a1['total'] && 10 === count( $b4_e1->world->terms )
	&& array( 'import_run_started', 'import_batch_committed' ) === $b4_events( $b4_e1 ) && 10 === count( $b4_e1->world->items ) && false === $b4_e1->store->locked );
$a1dup = $b4_e1->apply->advance_resumable( $uid1, 0, 1, str_repeat( '9', 64 ) );
mb_test( '6B4 advance: AYNI checkpoint\'in tekrarı (çift tıklama/eski istek) stale_request; ikinci yazma YOK', false === $a1dup['ok'] && 'stale_request' === $a1dup['error_code'] && 10 === count( $b4_e1->world->terms ) && 10 === count( $b4_e1->world->items ) && 'paused' === $b4_e1->store->get_run( $uid1 )['status'] && 1 === $b4_e1->store->get_run( $uid1 )['committed_batches'] );
$a1ahead = $b4_e1->apply->advance_resumable( $uid1, 3, 1, str_repeat( '9', 64 ) );
mb_test( '6B4 advance: ileri (uydurma) checkpoint de stale_request; yazma YOK', 'stale_request' === $a1ahead['error_code'] && 10 === count( $b4_e1->world->terms ) );
$bad_calls = array( array( 'x', 0 ), array( $uid1, '1' ), array( $uid1, -1 ), array( $uid1, 1.0 ), array( $uid1, null ), array( $uid1, true ), array( substr( $uid1, 1 ), 1 ), array( str_repeat( 'g', 32 ), 1 ) );
$bad_ok    = true;
foreach ( $bad_calls as $call ) {
	$r      = $b4_e1->apply->advance_resumable( $call[0], $call[1], 1, str_repeat( '9', 64 ) );
	$bad_ok = $bad_ok && false === $r['ok'] && in_array( $r['error_code'], array( 'invalid_request', 'run_not_found' ), true );
}
mb_test( '6B4 advance: geçersiz run_uid (kısa, hex olmayan)/checkpoint tipi (string, negatif, float, null, bool) reddedilir; yazma YOK', $bad_ok && 10 === count( $b4_e1->world->terms ) );
$b4_e1->store->locked = true;
$alock = $b4_e1->apply->advance_resumable( $uid1, 1, 1, str_repeat( '9', 64 ) );
$b4_e1->store->locked = false;
mb_test( '6B4 advance: paralel istek (kilit başkasında) -> locked; yazma YOK', 'locked' === $alock['error_code'] && 10 === count( $b4_e1->world->terms ) && 'paused' === $b4_e1->store->get_run( $uid1 )['status'] );
mb_test( '6B4 advance: aynı `paused` durumundan iki eşzamanlı claim\'den yalnız biri running\'e geçer (CAS)',
	true === $b4_e1->store->transition( $b4_e1->store->get_run( $uid1 )['id'], 'paused', 'running' ) && false === $b4_e1->store->transition( $b4_e1->store->get_run( $uid1 )['id'], 'paused', 'running' ) && true === $b4_e1->store->transition( $b4_e1->store->get_run( $uid1 )['id'], 'running', 'paused' ) );


/* sayfa kapandı: YENİ süreç (yeni servis/tx/store örnekleri) aynı run'ı sunucu durumundan sürdürür */
$b4_p2 = $b4_new_process( $b4_e1 );
$a2    = $b4_p2->apply->advance_resumable( $uid1, 1, 1, str_repeat( '9', 64 ) );
mb_test( '6B4 advance #2 (yeni süreç): sayfa kapanıp açıldıktan sonra kaldığı yerden 10 kayıt daha; checkpoint 2, committed 20, remaining 5, paused',
	true === $a2['ok'] && 'paused' === $a2['status'] && 2 === $a2['checkpoint'] && 20 === $a2['committed_items'] && 5 === $a2['remaining'] && 20 === count( $b4_e1->world->terms ) && 20 === count( $b4_e1->world->items ) );
$a3 = $b4_p2->apply->advance_resumable( $uid1, 2, 1, str_repeat( '9', 64 ) );
mb_test( '6B4 advance #3: son 5 kayıt yazılır, run `completed`, checkpoint 3, committed 25, remaining 0; run_completed audit\'i',
	true === $a3['ok'] && 'completed' === $a3['status'] && 3 === $a3['checkpoint'] && 25 === $a3['committed_items'] && 0 === $a3['remaining'] && 25 === count( $b4_e1->world->terms ) && 'completed' === $b4_e1->store->get_run( $uid1 )['status']
	&& array( 'import_run_started', 'import_batch_committed', 'import_batch_committed', 'import_batch_committed', 'import_run_completed' ) === $b4_events( $b4_e1 ) );
$a4 = $b4_p2->apply->advance_resumable( $uid1, 3, 1, str_repeat( '9', 64 ) );
mb_test( '6B4 advance: tamamlanmış run\'a advance run_not_resumable; yazma YOK', 'run_not_resumable' === $a4['error_code'] && 'completed' === $a4['status'] && 25 === count( $b4_e1->world->items ) );
$b4_after = $b4_p2->apply->preview( 'sectors' );
mb_test( '6B4 advance: tamamlanınca aşama önizlemesi 25 unchanged (readback)', 25 === $b4_after['summary']['operations']['unchanged'] && 0 === $b4_after['writes'] );

/* çok istekli sonuç == tek süreçli apply() sonucu (aynı DB/rollback-kaydı durumu) */
$b4_legacy = $b4_env( $b4_e1->dir );
$b4_lp     = $b4_legacy->apply->preview( 'sectors' );
$b4_lr     = $b4_legacy->apply->apply( 'sectors', $b4_lp['plan_digest'], 10, 1 );
$b4_strip  = function ( $env ) {
	$rows = array();
	foreach ( $env->world->items as $item ) {
		$rows[] = array( $item['seq'], $item['batch_no'], $item['source_key'], $item['type'], $item['decision'], $item['target_id'], $item['rollback_record'] );
	}
	return $rows;
};
mb_test( '6B4 eşdeğerlik: çok istekli advance zinciri ile tek süreçli apply() AYNI terimleri, item\'ları, batch numaralarını ve rollback kayıtlarını üretir; aynı audit olay sırası',
	true === $b4_lr['ok'] && $b4_legacy->world->terms === $b4_e1->world->terms && $b4_strip( $b4_legacy ) === $b4_strip( $b4_e1 ) && $b4_events( $b4_legacy ) === $b4_events( $b4_e1 ) );

/* --- manifest / map değişimi, TOCTOU --- */
$b4_m = $b4_env();
$b4_ms = $b4_start( $b4_m, 'sectors', 10, str_repeat( '9', 64 ) );
$b4_m->apply->advance_resumable( $b4_ms['run_uid'], 0, 1, str_repeat( '9', 64 ) );
$b4_mf  = json_decode( (string) file_get_contents( $b4_m->dir . '/sectors.manifest.json' ), true );
$b4_mf['records'][24]['description'] = 'Manifest başlangıçtan sonra değişti.';
file_put_contents( $b4_m->dir . '/sectors.manifest.json', json_encode( $b4_mf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
$b4_mr = $b4_m->apply->advance_resumable( $b4_ms['run_uid'], 1, 1, str_repeat( '9', 64 ) );
mb_test( '6B4 advance: manifest başlangıçtan sonra değişti -> manifest_changed; batch yazılmaz, run rollback_required (10 commit edilmiş kayıt korunur, geri alınabilir)',
	false === $b4_mr['ok'] && 'manifest_changed' === $b4_mr['error_code'] && 'rollback_required' === $b4_mr['status'] && 10 === count( $b4_m->world->terms ) && 1 === $b4_m->store->get_run( $b4_ms['run_uid'] )['committed_batches'] );
$b4_g = $b4_env();
$b4_gs = $b4_start( $b4_g, 'sectors', 10, str_repeat( '9', 64 ) );
$b4_gr = $b4_g->apply->advance_resumable( $b4_gs['run_uid'], 0, 1, str_repeat( '8', 64 ) );
mb_test( '6B4 advance: görsel map digest\'i başlangıçtan sonra değişti -> map_changed; hiçbir kayıt yazılmaz, run failed',
	false === $b4_gr['ok'] && 'map_changed' === $b4_gr['error_code'] && 'failed' === $b4_gr['status'] && array() === $b4_g->world->terms && array() === $b4_g->world->items );
$b4_gn = $b4_env();
$b4_gns = $b4_start( $b4_gn, 'sectors', 10, null );
$b4_gnr = $b4_gn->apply->advance_resumable( $b4_gns['run_uid'], 0, 1, str_repeat( '8', 64 ) );
mb_test( '6B4 advance: başlangıçta map YOKKEN (null) sonradan map belirdiyse de map_changed (null !== digest)', 'map_changed' === $b4_gnr['error_code'] && array() === $b4_gn->world->terms );
$b4_t = $b4_env();
$b4_ts = $b4_start( $b4_t );
$b4_t->apply->advance_resumable( $b4_ts['run_uid'], 0, 1, null );
$b4_t->world->terms[800] = array( 'taxonomy' => 'mb_sektor', 'slug' => 'zz-test-s15', 'name' => 'Kullanıcının terimi', 'description' => '', 'parent' => 0, 'meta' => array(), 'external' => true );
$b4_tr = $b4_t->apply->advance_resumable( $b4_ts['run_uid'], 1, 1, null );
mb_test( '6B4 advance (TOCTOU): planlamadan sonra doğal anahtarı dolan kayıt -> toctou_drift; o batch\'in HİÇBİR kaydı yazılmaz (10 eski kayıt + kullanıcının terimi), run rollback_required',
	false === $b4_tr['ok'] && 'toctou_drift' === $b4_tr['error_code'] && 'rollback_required' === $b4_tr['status'] && 11 === count( $b4_t->world->terms ) && 10 === count( $b4_t->world->items ) && 1 === $b4_t->store->get_run( $b4_ts['run_uid'] )['committed_batches'] );

/* --- hata enjeksiyonları: durum ve sayaçlar DOĞRU, kısmi batch yok --- */
$b4_f1 = $b4_env();
$b4_f1s = $b4_start( $b4_f1 );
$b4_f1->audit->failEvents = array( 'import_run_started' );
$b4_f1r = $b4_f1->apply->advance_resumable( $b4_f1s['run_uid'], 0, 1, null );
mb_test( '6B4 hata: run_started audit\'i yazılamazsa run_start_failed, run failed; hiçbir içerik yazılmaz',
	false === $b4_f1r['ok'] && 'run_start_failed' === $b4_f1r['error_code'] && 'failed' === $b4_f1r['status'] && array() === $b4_f1->world->terms && array() === $b4_f1->world->writeLog );
$b4_f2 = $b4_env();
$b4_f2s = $b4_start( $b4_f2 );
$b4_f2->audit->failEvents = array( 'import_batch_committed' );
$b4_f2r = $b4_f2->apply->advance_resumable( $b4_f2s['run_uid'], 0, 1, null );
mb_test( '6B4 hata: batch audit\'i yazılamazsa audit_failed; batch TAMAMEN geri alınır (0 terim, 0 item), run failed (commit edilmiş batch yok)',
	false === $b4_f2r['ok'] && 'audit_failed' === $b4_f2r['error_code'] && 'failed' === $b4_f2r['status'] && array() === $b4_f2->world->terms && array() === $b4_f2->world->items && 0 === $b4_f2->store->get_run( $b4_f2s['run_uid'] )['committed_batches'] );
$b4_f3 = $b4_env();
$b4_f3s = $b4_start( $b4_f3 );
$b4_f3->store->failWrites = array( 'checkpoint' );
$b4_f3r = $b4_f3->apply->advance_resumable( $b4_f3s['run_uid'], 0, 1, null );
mb_test( '6B4 hata: checkpoint yazılamazsa checkpoint_failed; batch geri alınır, run failed; sayaç 0',
	false === $b4_f3r['ok'] && 'checkpoint_failed' === $b4_f3r['error_code'] && 'failed' === $b4_f3r['status'] && array() === $b4_f3->world->terms && 0 === $b4_f3->store->get_run( $b4_f3s['run_uid'] )['committed_items'] );
$b4_f4 = $b4_env();
$b4_f4s = $b4_start( $b4_f4 );
$b4_f4->tx->fail_commit_in( 1 ); // run_started geçişinin commit'i geçer, batch commit'i başarısız olur
$b4_f4r = $b4_f4->apply->advance_resumable( $b4_f4s['run_uid'], 0, 1, null );
mb_test( '6B4 hata: COMMIT başarısızsa commit_failed; batch geri alınır, run failed',
	false === $b4_f4r['ok'] && 'commit_failed' === $b4_f4r['error_code'] && 'failed' === $b4_f4r['status'] && array() === $b4_f4->world->terms );
$b4_f5 = $b4_env();
$b4_f5s = $b4_start( $b4_f5 );
$b4_f5->apply->advance_resumable( $b4_f5s['run_uid'], 0, 1, null );
$b4_f5->tx->failBegin = true;
$b4_f5r = $b4_f5->apply->advance_resumable( $b4_f5s['run_uid'], 1, 1, null );
mb_test( '6B4 hata: ikinci batch BEGIN başarısız -> transaction_begin_failed; ilk batch korunur (10 kayıt), run rollback_required, sayaç 1',
	false === $b4_f5r['ok'] && 'transaction_begin_failed' === $b4_f5r['error_code'] && 'rollback_required' === $b4_f5r['status'] && 10 === count( $b4_f5->world->terms ) && 1 === $b4_f5->store->get_run( $b4_f5s['run_uid'] )['committed_batches'] );
$b4_f6 = $b4_env();
$b4_f6s = $b4_start( $b4_f6 );
$b4_f6->apply->advance_resumable( $b4_f6s['run_uid'], 0, 1, null );
$b4_f6->store->failTransitionTo = array( 'paused' );
$b4_f6r = $b4_f6->apply->advance_resumable( $b4_f6s['run_uid'], 1, 1, null );
$b4_f6->store->failTransitionTo = array();
mb_test( '6B4 hata: batch commit edildi ama `paused` geçişi uygulanamadı -> finalization_failed, GERÇEK durum running raporlanır (hedef durum uydurulmaz); 20 kayıt commit\'li',
	false === $b4_f6r['ok'] && 'finalization_failed' === $b4_f6r['error_code'] && 'running' === $b4_f6r['status'] && 20 === count( $b4_f6->world->terms ) && 2 === $b4_f6->store->get_run( $b4_f6s['run_uid'] )['committed_batches'] );
$b4_f6n = $b4_f6->apply->advance_resumable( $b4_f6s['run_uid'], 2, 1, null );
mb_test( '6B4 hata: takılı kalan `running` run bir sonraki istekte bayat sayılır (rollback_required, stale_run); advance run_not_resumable — sessiz devam YOK, yazma YOK',
	false === $b4_f6n['ok'] && 'run_not_resumable' === $b4_f6n['error_code'] && 'rollback_required' === $b4_f6n['status'] && 20 === count( $b4_f6->world->terms ) && 'stale_run' === $b4_f6->store->get_run( $b4_f6s['run_uid'] )['error_code'] );
$b4_f7 = $b4_env();
$b4_f7s = $b4_start( $b4_f7 );
$b4_f7->apply->advance_resumable( $b4_f7s['run_uid'], 0, 1, null );
$b4_f7->apply->advance_resumable( $b4_f7s['run_uid'], 1, 1, null );
$b4_f7->audit->failEvents = array( 'import_run_completed' );
$b4_f7r = $b4_f7->apply->advance_resumable( $b4_f7s['run_uid'], 2, 1, null );
mb_test( '6B4 hata: run_completed audit\'i yazılamazsa (son batch commit edilmiş) finalization_failed; run rollback_required ve sonuç GERÇEK durumu söyler; 25 kayıt commit\'li',
	false === $b4_f7r['ok'] && 'finalization_failed' === $b4_f7r['error_code'] && 'rollback_required' === $b4_f7r['status'] && 25 === count( $b4_f7->world->terms ) );
$b4_f8 = $b4_env();
$b4_f8s = $b4_start( $b4_f8 );
$b4_f8->world->faults = array( array( 'op' => 'create_sector', 'source_key' => 'sector:zz-test-s05' ) );
$b4_f8r = $b4_f8->apply->advance_resumable( $b4_f8s['run_uid'], 0, 1, null );
mb_test( '6B4 hata: batch ortasında yazma hatası (write_failed) -> batch atomik geri alınır (0 terim/0 item), run failed',
	false === $b4_f8r['ok'] && 'write_failed' === $b4_f8r['error_code'] && 'failed' === $b4_f8r['status'] && array() === $b4_f8->world->terms && array() === $b4_f8->world->items );

/* --- yazılacak kayıt yoksa run oluşmaz --- */
$b4_noop = $b4_legacy->apply->start_resumable( 'sectors', $b4_legacy->apply->preview( 'sectors' )['plan_digest'], 10, 1, null );
mb_test( '6B4 start: yazılacak kayıt yoksa (hepsi unchanged) ok=true status noop; yeni run/snapshot/audit oluşmaz', true === $b4_noop['ok'] && 'noop' === $b4_noop['status'] && null === $b4_noop['run_uid'] && 1 === count( $b4_legacy->world->runs ) && array() === $b4_legacy->world->planItems );

/* ================================================================
 * E) Resumable rollback: start (rollback_ready), advance (ters sırada bir batch), checkpoint, drift, reapply
 * ================================================================ */
$b4_applied = function () use ( $b4_env, $b4_start ) {
	$env = $b4_env();
	$s   = $b4_start( $env );
	$env->apply->advance_resumable( $s['run_uid'], 0, 1, null );
	$env->apply->advance_resumable( $s['run_uid'], 1, 1, null );
	$env->apply->advance_resumable( $s['run_uid'], 2, 1, null );
	$env->uid = $s['run_uid'];
	return $env;
};
$b4_rb_start = function ( $env, $digest = null ) {
	$pv = $env->rollback->preview( $env->uid );
	return $env->rollback->start_resumable( $env->uid, null === $digest ? $pv['rollback_digest'] : $digest );
};
$b4_terms_by_slug = function ( $env ) {
	$slugs = array();
	foreach ( $env->world->terms as $t ) {
		$slugs[] = $t['slug'];
	}
	sort( $slugs );
	return $slugs;
};

$b4_r0 = $b4_applied();
mb_test( '6B4 rollback hazırlık: 25 sektörlük run completed; önizleme 25 bekleyen item, engel yok', 'completed' === $b4_r0->store->get_run( $b4_r0->uid )['status'] && 25 === $b4_r0->rollback->preview( $b4_r0->uid )['items_pending'] && array() === $b4_r0->rollback->preview( $b4_r0->uid )['blockers'] );
$b4_pv0 = $b4_r0->rollback->preview( $b4_r0->uid );
$b4_before0 = count( $b4_r0->world->audit );
$b4_starts = array(
	'invalid_confirmation'  => array( $b4_r0->uid, 'kısa' ),
	'confirmation_mismatch' => array( $b4_r0->uid, str_repeat( 'f', 64 ) ),
	'run_not_found'         => array( str_repeat( 'a', 32 ), $b4_pv0['rollback_digest'] ),
);
foreach ( $b4_starts as $code => $args ) {
	$r = $b4_r0->rollback->start_resumable( $args[0], $args[1] );
	mb_test( '6B4 rollback start kapısı: ' . $code . ' -> reddedilir; durum completed kalır, audit/yazma YOK', false === $r['ok'] && $code === $r['error_code'] && 'completed' === $b4_r0->store->get_run( $b4_r0->uid )['status'] && $b4_before0 === count( $b4_r0->world->audit ) && 25 === count( $b4_r0->world->terms ) );
}
$b4_ne = $b4_env();
$b4_nes = $b4_start( $b4_ne );
$b4_ne->uid = $b4_nes['run_uid'];
$r = $b4_ne->rollback->start_resumable( $b4_ne->uid, str_repeat( 'a', 64 ) );
mb_test( '6B4 rollback start kapısı: henüz `ready` (yazma yapılmamış) apply run\'ı geri alınamaz -> run_not_rollbackable', false === $r['ok'] && 'run_not_rollbackable' === $r['error_code'] && 'ready' === $b4_ne->store->get_run( $b4_ne->uid )['status'] );

$b4_rs = $b4_rb_start( $b4_r0 );
mb_test( '6B4 rollback start: run `rollback_ready`, checkpoint 0, total 25, remaining 25; rollback_started audit\'i; HİÇBİR kayıt henüz geri alınmadı',
	true === $b4_rs['ok'] && 'rollback_ready' === $b4_rs['status'] && 0 === $b4_rs['checkpoint'] && 25 === $b4_rs['total'] && 25 === $b4_rs['remaining'] && 25 === count( $b4_r0->world->terms )
	&& 'import_rollback_started' === end( $b4_r0->world->audit )['event'] && false === $b4_r0->store->locked );
$b4_uid0 = $b4_r0->uid;
$b4_ra1  = $b4_r0->rollback->advance_resumable( $b4_uid0, 0, 10 );
$b4_left1 = $b4_terms_by_slug( $b4_r0 );
mb_test( '6B4 rollback advance #1: TERS sırada yalnız 10 kayıt (s25..s16) geri alınır; run `rollback_paused`, checkpoint 1, geri alınan 10, kalan 15',
	true === $b4_ra1['ok'] && 'rollback_paused' === $b4_ra1['status'] && 1 === $b4_ra1['checkpoint'] && 10 === $b4_ra1['committed'] && 15 === $b4_ra1['remaining'] && 15 === count( $b4_left1 )
	&& ! in_array( 'zz-test-s25', $b4_left1, true ) && ! in_array( 'zz-test-s16', $b4_left1, true ) && in_array( 'zz-test-s15', $b4_left1, true ) && in_array( 'zz-test-a', $b4_left1, true ) && false === $b4_r0->store->locked );
$b4_dupr = $b4_r0->rollback->advance_resumable( $b4_uid0, 0, 10 );
mb_test( '6B4 rollback advance: AYNI checkpoint tekrarı stale_request; ikinci geri alma YOK; checkpoint geriye gitmez', false === $b4_dupr['ok'] && 'stale_request' === $b4_dupr['error_code'] && 15 === count( $b4_r0->world->terms ) && 1 === $b4_r0->store->get_run( $b4_uid0 )['rollback_batches'] );
$b4_bad_rb = array( array( 'x', 1, 10 ), array( $b4_uid0, '1', 10 ), array( $b4_uid0, -1, 10 ), array( $b4_uid0, 1, 0 ), array( $b4_uid0, 1, 21 ), array( $b4_uid0, 1.0, 10 ), array( $b4_uid0, null, 10 ) );
$b4_bad_ok = true;
foreach ( $b4_bad_rb as $call ) {
	$r         = $b4_r0->rollback->advance_resumable( $call[0], $call[1], $call[2] );
	$b4_bad_ok = $b4_bad_ok && false === $r['ok'] && in_array( $r['error_code'], array( 'invalid_request', 'invalid_batch_size', 'run_not_found' ), true );
}
mb_test( '6B4 rollback advance: geçersiz uid/checkpoint/batch boyutu reddedilir; geri alma YOK', $b4_bad_ok && 15 === count( $b4_r0->world->terms ) );
$b4_r0->store->locked = true;
$b4_lockr = $b4_r0->rollback->advance_resumable( $b4_uid0, 1, 10 );
$b4_r0->store->locked = false;
mb_test( '6B4 rollback advance: paralel istek (kilit başkasında) -> locked; geri alma YOK', 'locked' === $b4_lockr['error_code'] && 15 === count( $b4_r0->world->terms ) );
$b4_rp2 = $b4_new_process( $b4_r0 );
$b4_rp2->rollback = new MaviBelge_Core_Import_Rollback_Service( new MB_Fake_World_Repository( $b4_r0->world ), new MB_Fake_Writer( $b4_r0->world ), $b4_rp2->tx, $b4_rp2->store, $b4_rp2->audit );
$b4_ra2 = $b4_rp2->rollback->advance_resumable( $b4_uid0, 1, 10 );
mb_test( '6B4 rollback advance #2 (YENİ süreç): sayfa kapanıp açıldıktan sonra kaldığı yerden 10 kayıt daha; checkpoint 2, geri alınan 20, kalan 5',
	true === $b4_ra2['ok'] && 'rollback_paused' === $b4_ra2['status'] && 2 === $b4_ra2['checkpoint'] && 20 === $b4_ra2['committed'] && 5 === $b4_ra2['remaining'] && 5 === count( $b4_r0->world->terms ) );
$b4_ra3 = $b4_rp2->rollback->advance_resumable( $b4_uid0, 2, 10 );
$b4_all_rb = true;
foreach ( $b4_r0->world->items as $item ) {
	$b4_all_rb = $b4_all_rb && 'rolled_back' === $item['rollback_status'];
}
mb_test( '6B4 rollback advance #3: kalan 5 kayıt geri alınır; run `rolled_back`, checkpoint 3, geri alınan 25, kalan 0; 0 terim; her item rolled_back; rollback_completed audit\'i',
	true === $b4_ra3['ok'] && 'rolled_back' === $b4_ra3['status'] && 3 === $b4_ra3['checkpoint'] && 25 === $b4_ra3['committed'] && 0 === $b4_ra3['remaining'] && array() === $b4_r0->world->terms && $b4_all_rb
	&& 'import_rollback_completed' === end( $b4_r0->world->audit )['event'] && 'rolled_back' === $b4_r0->store->get_run( $b4_uid0 )['status'] );
$b4_ra4 = $b4_rp2->rollback->advance_resumable( $b4_uid0, 3, 10 );
mb_test( '6B4 rollback advance: rolled_back run\'a advance run_not_resumable; geri alma YOK', 'run_not_resumable' === $b4_ra4['error_code'] && 'rolled_back' === $b4_ra4['status'] );

/* rollback -> yeniden apply: aynı manifest tekrar uygulanabilir, kalıcı silme yok */
$b4_rpv = $b4_r0->apply->preview( 'sectors' );
mb_test( '6B4 rollback->reapply: temiz rollback sonrası aynı aşama önizlemesi 25 create, conflict 0, applicable true', 25 === $b4_rpv['summary']['operations']['create'] && 0 === $b4_rpv['summary']['operations']['conflict'] && true === $b4_rpv['summary']['applicable'] );
$b4_rre = $b4_start( $b4_r0 );
for ( $b4_k = 0; $b4_k < 3; $b4_k++ ) {
	$b4_rre_last = $b4_r0->apply->advance_resumable( $b4_rre['run_uid'], $b4_k, 1, null );
}
mb_test( '6B4 rollback->reapply: yeniden apply çok istekle `completed` olur; 25 terim geri gelir', true === $b4_rre['ok'] && true === $b4_rre_last['ok'] && 'completed' === $b4_rre_last['status'] && 25 === count( $b4_r0->world->terms ) );

/* tek süreçli rollback() ile aynı sonuç */
$b4_leg   = $b4_applied();
$b4_multi = $b4_applied();
$b4_leg->rollback->rollback( $b4_leg->uid, $b4_leg->rollback->preview( $b4_leg->uid )['rollback_digest'], 10 );
$b4_rb_start( $b4_multi );
for ( $b4_k = 0; $b4_k < 3; $b4_k++ ) {
	$b4_multi->rollback->advance_resumable( $b4_multi->uid, $b4_k, 10 );
}
$b4_item_states = function ( $env ) {
	$rows = array();
	foreach ( $env->world->items as $item ) {
		$rows[] = array( $item['seq'], $item['source_key'], $item['rollback_status'] );
	}
	return $rows;
};
mb_test( '6B4 rollback eşdeğerlik: çok istekli rollback ile tek süreçli rollback() AYNI son durumu bırakır (terimler, item durumları, run durumu, rollback audit olayları)',
	$b4_leg->world->terms === $b4_multi->world->terms && array() === $b4_multi->world->terms && $b4_item_states( $b4_leg ) === $b4_item_states( $b4_multi ) && 'rolled_back' === $b4_multi->store->get_run( $b4_multi->uid )['status']
	&& 'rolled_back' === $b4_leg->store->get_run( $b4_leg->uid )['status'] && 'import_rollback_completed' === end( $b4_multi->world->audit )['event'] && 'import_rollback_completed' === end( $b4_leg->world->audit )['event'] );

/* --- drift: rollback ortasında kullanıcı değişikliği -> kalan hiçbir item yazılmaz, kullanıcı değişikliği korunur --- */
$b4_d = $b4_applied();
$b4_rb_start( $b4_d );
$b4_d->rollback->advance_resumable( $b4_d->uid, 0, 10 );
$b4_sid = null;
foreach ( $b4_d->world->terms as $id => $t ) {
	if ( 'zz-test-s03' === $t['slug'] || ( null === $b4_sid && 'zz-test-b' === $t['slug'] ) ) {
		$b4_sid = $id;
	}
}
$b4_d->world->terms[ $b4_sid ]['meta']['kullanici_notu'] = 'Rollback ortasında kullanıcı değişikliği';
$b4_dr = $b4_d->rollback->advance_resumable( $b4_d->uid, 1, 10 );
mb_test( '6B4 rollback drift: ilk batch\'ten sonra yönetilmeyen alan değişirse sonraki advance rollback_failed/drift_detected; 15 kayıt YAZILMADAN kalır, kullanıcı değişikliği korunur',
	false === $b4_dr['ok'] && 'drift_detected' === $b4_dr['error_code'] && 'rollback_failed' === $b4_dr['status'] && 15 === count( $b4_d->world->terms ) && 'Rollback ortasında kullanıcı değişikliği' === $b4_d->world->terms[ $b4_sid ]['meta']['kullanici_notu']
	&& 1 === $b4_d->store->get_run( $b4_d->uid )['rollback_batches'] && 10 === $b4_d->store->get_run( $b4_d->uid )['rollback_items'] );
$b4_dpv = $b4_d->rollback->preview( $b4_d->uid );
$b4_dst = $b4_d->rollback->start_resumable( $b4_d->uid, $b4_dpv['rollback_digest'] );
mb_test( '6B4 rollback drift: drift sürerken yeniden başlatma reddedilir (drift_detected); durum rollback_failed kalır', false === $b4_dst['ok'] && 'drift_detected' === $b4_dst['error_code'] && 'rollback_failed' === $b4_d->store->get_run( $b4_d->uid )['status'] );
unset( $b4_d->world->terms[ $b4_sid ]['meta']['kullanici_notu'] );
$b4_dpv2 = $b4_d->rollback->preview( $b4_d->uid );
$b4_dst2 = $b4_d->rollback->start_resumable( $b4_d->uid, $b4_dpv2['rollback_digest'] );
$b4_dsteps = array();
$b4_d_cp   = 1;
while ( 'rollback_ready' === $b4_d->store->get_run( $b4_d->uid )['status'] || 'rollback_paused' === $b4_d->store->get_run( $b4_d->uid )['status'] ) {
	$b4_dsteps[] = $b4_d->rollback->advance_resumable( $b4_d->uid, $b4_d->store->get_run( $b4_d->uid )['rollback_batches'], 10 );
	if ( count( $b4_dsteps ) > 5 ) {
		break;
	}
}
mb_test( '6B4 rollback drift: kullanıcı değişikliği giderilince rollback_failed run yeniden başlatılabilir ve kalan 15 kayıtla tamamlanır (rolled_back)',
	true === $b4_dst2['ok'] && 'rollback_ready' === $b4_dst2['status'] && 15 === $b4_dst2['remaining'] && 'rolled_back' === $b4_d->store->get_run( $b4_d->uid )['status'] && array() === $b4_d->world->terms );
$b4_d2 = $b4_applied();
$b4_rb_start( $b4_d2 );
$b4_d2->rollback->advance_resumable( $b4_d2->uid, 0, 10 );
foreach ( $b4_d2->world->terms as $id => $t ) {
	if ( 'zz-test-a' === $t['slug'] ) {
		$b4_d2->world->terms[ $id ]['meta']['kullanici_notu'] = 'Son batch\'teki kayıt kirlendi';
	}
}
$b4_d2r = $b4_d2->rollback->advance_resumable( $b4_d2->uid, 1, 10 );
mb_test( '6B4 rollback drift: kirlenen kayıt SONRAKİ batch\'te değil, en sonda olsa bile o advance\'te HİÇBİR kayıt geri alınmaz (tüm kalan item\'lar önceden doğrulanır)', 'drift_detected' === $b4_d2r['error_code'] && 15 === count( $b4_d2->world->terms ) );

/* --- hata enjeksiyonları --- */
$b4_h1 = $b4_applied();
$b4_h1->audit->failEvents = array( 'import_rollback_started' );
$b4_h1r = $b4_rb_start( $b4_h1 );
mb_test( '6B4 rollback hata: rollback_started audit\'i yazılamazsa run completed kalır (state_transition_failed); hiçbir şey geri alınmaz', false === $b4_h1r['ok'] && 'state_transition_failed' === $b4_h1r['error_code'] && 'completed' === $b4_h1->store->get_run( $b4_h1->uid )['status'] && 25 === count( $b4_h1->world->terms ) );
$b4_h2 = $b4_applied();
$b4_rb_start( $b4_h2 );
$b4_h2->store->failWrites = array( 'rollback_checkpoint' );
$b4_h2r = $b4_h2->rollback->advance_resumable( $b4_h2->uid, 0, 10 );
mb_test( '6B4 rollback hata: rollback checkpoint yazılamazsa checkpoint_failed; batch atomik geri alınır (25 terim yerinde), run rollback_failed', false === $b4_h2r['ok'] && 'checkpoint_failed' === $b4_h2r['error_code'] && 'rollback_failed' === $b4_h2r['status'] && 25 === count( $b4_h2->world->terms ) && 0 === $b4_h2->store->get_run( $b4_h2->uid )['rollback_items'] );
$b4_h3 = $b4_applied();
$b4_rb_start( $b4_h3 );
$b4_h3->tx->failBegin = true;
$b4_h3r = $b4_h3->rollback->advance_resumable( $b4_h3->uid, 0, 10 );
mb_test( '6B4 rollback hata: BEGIN başarısız -> transaction_begin_failed; rollback_failed; hiçbir kayıt geri alınmaz', false === $b4_h3r['ok'] && 'transaction_begin_failed' === $b4_h3r['error_code'] && 'rollback_failed' === $b4_h3r['status'] && 25 === count( $b4_h3->world->terms ) );
$b4_h4 = $b4_applied();
$b4_rb_start( $b4_h4 );
$b4_h4->tx->fail_commit_in( 0 );
$b4_h4r = $b4_h4->rollback->advance_resumable( $b4_h4->uid, 0, 10 );
mb_test( '6B4 rollback hata: batch COMMIT başarısız -> commit_failed; batch geri alınır (25 terim yerinde), rollback_failed', false === $b4_h4r['ok'] && 'commit_failed' === $b4_h4r['error_code'] && 'rollback_failed' === $b4_h4r['status'] && 25 === count( $b4_h4->world->terms ) );
$b4_h5 = $b4_applied();
$b4_rb_start( $b4_h5 );
$b4_h5->store->failTransitionTo = array( 'rollback_paused' );
$b4_h5r = $b4_h5->rollback->advance_resumable( $b4_h5->uid, 0, 10 );
$b4_h5->store->failTransitionTo = array();
$b4_h5n = $b4_h5->rollback->advance_resumable( $b4_h5->uid, 1, 10 );
mb_test( '6B4 rollback hata: batch commit edildi ama rollback_paused geçişi uygulanamadı -> finalization_failed, GERÇEK durum rolling_back; sonraki istek bayat sayar (rollback_failed/stale_run), sessiz devam YOK',
	false === $b4_h5r['ok'] && 'finalization_failed' === $b4_h5r['error_code'] && 'rolling_back' === $b4_h5r['status'] && 15 === count( $b4_h5->world->terms ) && 'run_not_resumable' === $b4_h5n['error_code'] && 'rollback_failed' === $b4_h5n['status'] && 'stale_run' === $b4_h5->store->get_run( $b4_h5->uid )['error_code'] );
$b4_h6 = $b4_applied();
$b4_rb_start( $b4_h6 );
$b4_h6->rollback->advance_resumable( $b4_h6->uid, 0, 10 );
$b4_h6->rollback->advance_resumable( $b4_h6->uid, 1, 10 );
$b4_h6->audit->failEvents = array( 'import_rollback_completed' );
$b4_h6r = $b4_h6->rollback->advance_resumable( $b4_h6->uid, 2, 10 );
mb_test( '6B4 rollback hata: rollback_completed audit\'i yazılamazsa (son batch commit\'li) finalization_failed; run rollback_failed; sonuç GERÇEK durumu söyler; kayıtlar geri alınmış',
	false === $b4_h6r['ok'] && 'finalization_failed' === $b4_h6r['error_code'] && 'rollback_failed' === $b4_h6r['status'] && array() === $b4_h6->world->terms );

/* legacy CLI rollback bir rollback_paused run'ı tamamlayabilir */
$b4_c = $b4_applied();
$b4_rb_start( $b4_c );
$b4_c->rollback->advance_resumable( $b4_c->uid, 0, 10 );
$b4_cr = $b4_c->rollback->rollback( $b4_c->uid, $b4_c->rollback->preview( $b4_c->uid )['rollback_digest'], 10 );
mb_test( '6B4 CLI uyumu: yarım kalmış (rollback_paused) UI rollback\'ı CLI rollback() ile tamamlanabilir; run rolled_back, 0 terim', true === $b4_cr['ok'] && 'rolled_back' === $b4_cr['status'] && array() === $b4_c->world->terms );

/* ================================================================
 * F) Admin güvenlik kapıları (SAF), kapalı request şekli, onay ifadeleri
 * ================================================================ */
$B4_G = 'MaviBelge_Core_Import_Admin_Gates';
$b4_snap = array(
	'method'           => 'POST',
	'is_ssl'           => true,
	'user_id'          => 7,
	'can_manage_options' => true,
	'can_tariff'       => true,
	'constants'        => array( 'MAVIBELGE_IMPORT_APPLY_ENABLED' => true, 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED' => true ),
	'environment_type' => 'staging',
	'host'             => 'cms-yeni.example.test',
);
$b4_gate = function ( $level, array $patch = array(), array $constPatch = null ) use ( $B4_G, $b4_snap ) {
	$snap = array_merge( $b4_snap, $patch );
	if ( null !== $constPatch ) {
		$snap['constants'] = $constPatch;
	}
	return $B4_G::evaluate( $level, $snap );
};
mb_test( '6B4 kapı (run): staging + iki sabit true + POST + HTTPS + iki yetki -> izinli', true === $b4_gate( 'run' )['ok'] && array() === $b4_gate( 'run' )['codes'] );
$b4_run_denials = array(
	'method_not_post'         => array( array( 'method' => 'GET' ), null ),
	'not_https'               => array( array( 'is_ssl' => false ), null ),
	'not_logged_in'           => array( array( 'user_id' => 0 ), null ),
	'missing_capability (yalnız tariff)' => array( array( 'can_manage_options' => false ), null ),
	'missing_capability (yalnız manage_options)' => array( array( 'can_tariff' => false ), null ),
	'apply_disabled (yok)'     => array( array(), array( 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED' => true ) ),
	'apply_disabled (false)'   => array( array(), array( 'MAVIBELGE_IMPORT_APPLY_ENABLED' => false, 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED' => true ) ),
	'apply_disabled (string "1")' => array( array(), array( 'MAVIBELGE_IMPORT_APPLY_ENABLED' => '1', 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED' => true ) ),
	'apply_disabled (int 1)'   => array( array(), array( 'MAVIBELGE_IMPORT_APPLY_ENABLED' => 1, 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED' => true ) ),
	'admin_apply_disabled (yok)' => array( array(), array( 'MAVIBELGE_IMPORT_APPLY_ENABLED' => true ) ),
	'admin_apply_disabled (false)' => array( array(), array( 'MAVIBELGE_IMPORT_APPLY_ENABLED' => true, 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED' => false ) ),
	'environment_not_allowed (local)' => array( array( 'environment_type' => 'local' ), null ),
	'environment_not_allowed (development)' => array( array( 'environment_type' => 'development' ), null ),
	'environment_not_allowed (STAGING büyük harf)' => array( array( 'environment_type' => 'STAGING' ), null ),
	'environment_not_allowed (boş)' => array( array( 'environment_type' => '' ), null ),
);
foreach ( $b4_run_denials as $label => $case ) {
	$code = explode( ' ', $label )[0];
	$r    = $b4_gate( 'run', $case[0], $case[1] );
	mb_test( '6B4 kapı (run): ' . $label . ' -> reddedilir (' . $code . ')', false === $r['ok'] && in_array( $code, $r['codes'], true ) );
}
$b4_prod_consts = array( 'MAVIBELGE_IMPORT_APPLY_ENABLED' => true, 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED' => true, 'MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED' => true, 'MAVIBELGE_IMPORT_PRODUCTION_HOST' => 'www.example.test' );
mb_test( '6B4 kapı (run): üretimde ek sabit + host eşleşmesi olmadan reddedilir (production_disabled)',
	in_array( 'production_disabled', $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'www.example.test' ) )['codes'], true ) );
mb_test( '6B4 kapı (run): üretimde PRODUCTION_APPLY_ENABLED false -> reddedilir', false === $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'www.example.test' ), array_merge( $b4_prod_consts, array( 'MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED' => false ) ) )['ok'] );
mb_test( '6B4 kapı (run): üretimde host sabiti yok/boş/eşleşmiyor (alt alan, port, büyük harf, sona nokta) -> production_host_mismatch',
	in_array( 'production_host_mismatch', $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'www.example.test' ), array_diff_key( $b4_prod_consts, array( 'MAVIBELGE_IMPORT_PRODUCTION_HOST' => 1 ) ) )['codes'], true )
	&& in_array( 'production_host_mismatch', $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'www.example.test' ), array_merge( $b4_prod_consts, array( 'MAVIBELGE_IMPORT_PRODUCTION_HOST' => '' ) ) )['codes'], true )
	&& in_array( 'production_host_mismatch', $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'cms.www.example.test' ), $b4_prod_consts )['codes'], true )
	&& in_array( 'production_host_mismatch', $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'www.example.test:8080' ), $b4_prod_consts )['codes'], true )
	&& in_array( 'production_host_mismatch', $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'WWW.EXAMPLE.TEST' ), $b4_prod_consts )['codes'], true )
	&& in_array( 'production_host_mismatch', $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'www.example.test.' ), $b4_prod_consts )['codes'], true )
	&& in_array( 'production_host_mismatch', $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'www.example.test' ), array_merge( $b4_prod_consts, array( 'MAVIBELGE_IMPORT_PRODUCTION_HOST' => array( 'www.example.test' ) ) ) )['codes'], true ) );
mb_test( '6B4 kapı (run): üretimde HER ŞEY doğru (iki ek sabit + birebir host) -> izinli', true === $b4_gate( 'run', array( 'environment_type' => 'production', 'host' => 'www.example.test' ), $b4_prod_consts )['ok'] );
mb_test( '6B4 kapı (run): staging\'de üretim sabitleri tek başına yetmez; staging kuralı sabit false olsa da geçerlidir (üretim sabitleri staging\'de aranmaz)',
	true === $b4_gate( 'run', array(), array_merge( $b4_prod_consts, array( 'MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED' => false ) ) )['ok'] );
mb_test( '6B4 kapı (read/map): apply sabitleri OLMADAN önizleme ve eşleme kaydı izinli (runbook: eşleme sabit açılmadan önce yapılır); ama POST, HTTPS, giriş ve iki yetki hâlâ zorunlu',
	true === $b4_gate( 'read', array(), array() )['ok'] && true === $b4_gate( 'map', array( 'environment_type' => 'local' ), array() )['ok']
	&& false === $b4_gate( 'read', array( 'method' => 'GET' ) )['ok'] && false === $b4_gate( 'read', array( 'is_ssl' => false ) )['ok'] && false === $b4_gate( 'map', array( 'user_id' => 0 ) )['ok']
	&& false === $b4_gate( 'map', array( 'can_tariff' => false ) )['ok'] && false === $b4_gate( 'read', array( 'can_manage_options' => false ) )['ok'] );
mb_test( '6B4 kapı: bilinmeyen seviye ve bozuk snapshot fail-closed', false === $B4_G::evaluate( 'bogus', $b4_snap )['ok'] && false === $B4_G::evaluate( 'run', array() )['ok'] && false === $B4_G::evaluate( 'run', array( 'method' => 'POST' ) )['ok'] );
mb_test( '6B4 kapı: sabitler yalnız snapshot\'tan okunur — istek verisi (form/URL/option) kapıları AÇAMAZ; evaluate() istek dizisi almaz', 2 === ( new ReflectionMethod( $B4_G, 'evaluate' ) )->getNumberOfRequiredParameters() );

/* --- onay ifadeleri: birebir --- */
$b4_pd  = 'abcdef0123456789' . str_repeat( '0', 48 );
$b4_uid = '0123456789abcdef0123456789abcdef';
mb_test( '6B4 onay: apply ifadesi tam biçim "UYGULA <stage> <digest ilk 12>"', 'UYGULA sectors abcdef012345' === $B4_G::apply_phrase( 'sectors', $b4_pd ) && 'UYGULA content abcdef012345' === $B4_G::apply_phrase( 'content', $b4_pd ) );
mb_test( '6B4 onay: rollback ifadesi tam biçim "GERI AL <run kısa 8> <digest ilk 12>"', 'GERI AL 01234567 abcdef012345' === $B4_G::rollback_phrase( $b4_uid, $b4_pd ) );
mb_test( '6B4 onay: yanlış girdiler için ifade üretilmez (null)', null === $B4_G::apply_phrase( 'bogus', $b4_pd ) && null === $B4_G::apply_phrase( 'sectors', 'kısa' ) && null === $B4_G::rollback_phrase( 'x', $b4_pd ) && null === $B4_G::rollback_phrase( $b4_uid, 'x' ) );
$b4_phrase = 'UYGULA sectors abcdef012345';
$b4_bad_phrases = array( 'uygula sectors abcdef012345', 'UYGULA sectors ABCDEF012345', ' UYGULA sectors abcdef012345', 'UYGULA sectors abcdef012345 ', 'UYGULA  sectors abcdef012345', "UYGULA sectors abcdef012345\n", 'UYGULA sectors abcdef01234', 'UYGULA all abcdef012345', 'GERI AL 01234567 abcdef012345', '', null, array( $b4_phrase ), 5, "UYGULA\tsectors abcdef012345" );
$b4_phr_ok = true === $B4_G::phrase_matches( $b4_phrase, $b4_phrase );
foreach ( $b4_bad_phrases as $bad ) {
	$b4_phr_ok = $b4_phr_ok && false === $B4_G::phrase_matches( $bad, $b4_phrase );
}
mb_test( '6B4 onay: küçük/büyük harf, baş/son boşluk, çift boşluk, satır sonu, sekme, kısa digest, yanlış stage, başka işlemin ifadesi, boş/null/dizi/int sessizce DÜZELTİLMEZ, reddedilir', $b4_phr_ok );
mb_test( '6B4 onay: apply ifadesi rollback için, rollback ifadesi apply için kullanılamaz', false === $B4_G::phrase_matches( $B4_G::apply_phrase( 'sectors', $b4_pd ), $B4_G::rollback_phrase( $b4_uid, $b4_pd ) ) );

/* --- kapalı request şekli --- */
$b4_nonce_field = 'mb_import_nonce';
$b4_req = function ( $action, array $extra, array $patch = array() ) use ( $B4_G, $b4_nonce_field ) {
	$post = array_merge( array( 'action' => 'mavibelge_import_' . $action, $b4_nonce_field => 'abc1234567' ), $extra );
	$post = array_merge( $post, $patch );
	return $B4_G::normalize_request( $action, $post, array() );
};
$b4_valid = array(
	'preview_stage'    => array( 'stage' => 'sectors' ),
	'start_apply'      => array( 'stage' => 'qualifications', 'plan_digest' => $b4_pd, 'confirm_phrase' => $b4_phrase ),
	'advance_apply'    => array( 'run_uid' => $b4_uid, 'expected_checkpoint' => '2' ),
	'preview_rollback' => array( 'run_uid' => $b4_uid ),
	'start_rollback'   => array( 'run_uid' => $b4_uid, 'rollback_digest' => $b4_pd, 'confirm_phrase' => 'GERI AL 01234567 abcdef012345' ),
	'advance_rollback' => array( 'run_uid' => $b4_uid, 'expected_checkpoint' => '0' ),
	'save_sector_image_map' => array( 'mappings' => array( 'makine' => '11', 'maden' => '12' ) ),
);
$b4_all_valid = true;
foreach ( $b4_valid as $action => $extra ) {
	$n = $b4_req( $action, $extra );
	$b4_all_valid = $b4_all_valid && true === $n['ok'] && null === $n['error_code'];
}
mb_test( '6B4 request: yedi action için geçerli kapalı istekler kabul edilir', $b4_all_valid );
$n = $b4_req( 'advance_apply', $b4_valid['advance_apply'] );
mb_test( '6B4 request: veri tiplendirilir (expected_checkpoint gerçek int, stage/digest/run_uid string)', 2 === $n['data']['expected_checkpoint'] && $b4_uid === $n['data']['run_uid'] );
$n = $b4_req( 'save_sector_image_map', $b4_valid['save_sector_image_map'] );
mb_test( '6B4 request: mappings slug => gerçek int', array( 'makine' => 11, 'maden' => 12 ) === $n['data']['mappings'] );
$b4_bad_reqs = array(
	'fazladan anahtar (mb_force)'          => array( 'preview_stage', $b4_valid['preview_stage'], array( 'mb_force' => '1' ) ),
	'fazladan anahtar (_wp_http_referer)'  => array( 'preview_stage', $b4_valid['preview_stage'], array( '_wp_http_referer' => '/x' ) ),
	'eksik anahtar (stage)'                => array( 'preview_stage', array(), array() ),
	'bilinmeyen stage'                     => array( 'preview_stage', array( 'stage' => 'fees' ), array() ),
	'stage dizi'                           => array( 'preview_stage', array( 'stage' => array( 'sectors' ) ), array() ),
	'stage büyük harf'                     => array( 'preview_stage', array( 'stage' => 'SECTORS' ), array() ),
	'plan_digest kısa'                     => array( 'start_apply', $b4_valid['start_apply'], array( 'plan_digest' => 'abc' ) ),
	'plan_digest büyük harf'               => array( 'start_apply', $b4_valid['start_apply'], array( 'plan_digest' => strtoupper( $b4_pd ) ) ),
	'run_uid kısa'                         => array( 'advance_apply', $b4_valid['advance_apply'], array( 'run_uid' => 'abc' ) ),
	'run_uid büyük harf'                   => array( 'advance_apply', $b4_valid['advance_apply'], array( 'run_uid' => strtoupper( $b4_uid ) ) ),
	'checkpoint negatif'                   => array( 'advance_apply', $b4_valid['advance_apply'], array( 'expected_checkpoint' => '-1' ) ),
	'checkpoint ondalık'                   => array( 'advance_apply', $b4_valid['advance_apply'], array( 'expected_checkpoint' => '1.0' ) ),
	'checkpoint baştaki sıfır'             => array( 'advance_apply', $b4_valid['advance_apply'], array( 'expected_checkpoint' => '01' ) ),
	'checkpoint boşluklu'                  => array( 'advance_apply', $b4_valid['advance_apply'], array( 'expected_checkpoint' => ' 1' ) ),
	'checkpoint bilimsel'                  => array( 'advance_apply', $b4_valid['advance_apply'], array( 'expected_checkpoint' => '1e2' ) ),
	'checkpoint dizi'                      => array( 'advance_apply', $b4_valid['advance_apply'], array( 'expected_checkpoint' => array( '1' ) ) ),
	'checkpoint taşma'                     => array( 'advance_apply', $b4_valid['advance_apply'], array( 'expected_checkpoint' => '99999999999999999999' ) ),
	'checkpoint boş'                       => array( 'advance_apply', $b4_valid['advance_apply'], array( 'expected_checkpoint' => '' ) ),
	'confirm_phrase dizi'                  => array( 'start_apply', $b4_valid['start_apply'], array( 'confirm_phrase' => array( 'x' ) ) ),
	'confirm_phrase çok uzun'              => array( 'start_apply', $b4_valid['start_apply'], array( 'confirm_phrase' => str_repeat( 'a', 201 ) ) ),
	'nonce alanı yok'                      => array( 'preview_stage', $b4_valid['preview_stage'], array( 'mb_import_nonce' => null ) ),
	'mappings dizi değil'                  => array( 'save_sector_image_map', array( 'mappings' => '11' ), array() ),
	'mappings değeri string olmayan'       => array( 'save_sector_image_map', array( 'mappings' => array( 'makine' => 11 ) ), array() ),
	'mappings değeri 0'                    => array( 'save_sector_image_map', array( 'mappings' => array( 'makine' => '0' ) ), array() ),
	'mappings değeri negatif'              => array( 'save_sector_image_map', array( 'mappings' => array( 'makine' => '-3' ) ), array() ),
	'mappings değeri boş'                  => array( 'save_sector_image_map', array( 'mappings' => array( 'makine' => '' ) ), array() ),
	'mappings değeri ondalık'              => array( 'save_sector_image_map', array( 'mappings' => array( 'makine' => '11.5' ) ), array() ),
	'mappings değeri iç dizi'              => array( 'save_sector_image_map', array( 'mappings' => array( 'makine' => array( '1' ) ) ), array() ),
	'mappings slug büyük harf'             => array( 'save_sector_image_map', array( 'mappings' => array( 'Makine' => '11' ) ), array() ),
	'mappings slug yol geçişi'             => array( 'save_sector_image_map', array( 'mappings' => array( '../x' => '11' ) ), array() ),
	'mappings sayısal slug (PHP int anahtar)' => array( 'save_sector_image_map', array( 'mappings' => array( '12' => '11' ) ), array() ),
	'mappings boş'                         => array( 'save_sector_image_map', array( 'mappings' => array() ), array() ),
	'mappings 100+ slug'                   => array( 'save_sector_image_map', array( 'mappings' => array_fill_keys( array_map( function ( $i ) {
		return 's' . $i . 'x';
	}, range( 1, 101 ) ), '1' ) ), array() ),
);
foreach ( $b4_bad_reqs as $label => $case ) {
	$n = $b4_req( $case[0], $case[1], $case[2] );
	if ( 'nonce alanı yok' === $label ) {
		$post = array( 'action' => 'mavibelge_import_preview_stage', 'stage' => 'sectors' );
		$n    = $B4_G::normalize_request( 'preview_stage', $post, array() );
	}
	mb_test( '6B4 request: ' . $label . ' -> reddedilir, veri döndürülmez', false === $n['ok'] && is_string( $n['error_code'] ) && array() === $n['data'] );
}
mb_test( '6B4 request: URL (GET) parametresi taşıyan istek reddedilir (kapalı anahtar kümesi GET\'i de kapsar)', false === $B4_G::normalize_request( 'preview_stage', array_merge( array( 'action' => 'mavibelge_import_preview_stage', $b4_nonce_field => 'abc1234567' ), $b4_valid['preview_stage'] ), array( 'stage' => 'sectors' ) )['ok'] );
mb_test( '6B4 request: action adı eşleşmeyen veya bilinmeyen istek reddedilir',
	false === $B4_G::normalize_request( 'preview_stage', array( 'action' => 'mavibelge_import_start_apply', $b4_nonce_field => 'abc1234567', 'stage' => 'sectors' ), array() )['ok'] && false === $B4_G::normalize_request( 'silent_admin', array( 'action' => 'mavibelge_import_silent_admin', $b4_nonce_field => 'x' ), array() )['ok'] );
mb_test( '6B4 request: her action seviyesi bağlayıcı — kayıt/apply/rollback yazma action\'ları map/run, önizlemeler read; nonce action adı action\'a özel',
	'read' === $B4_G::ACTIONS['preview_stage']['level'] && 'read' === $B4_G::ACTIONS['preview_rollback']['level'] && 'map' === $B4_G::ACTIONS['save_sector_image_map']['level'] && 'run' === $B4_G::ACTIONS['start_apply']['level'] && 'run' === $B4_G::ACTIONS['advance_apply']['level']
	&& 'run' === $B4_G::ACTIONS['start_rollback']['level'] && 'run' === $B4_G::ACTIONS['advance_rollback']['level'] && 9 === count( $B4_G::ACTIONS ) && 'mavibelge_import_start_apply' === $B4_G::nonce_action( 'start_apply' ) && $B4_G::nonce_action( 'start_apply' ) !== $B4_G::nonce_action( 'start_rollback' ) );

/* ================================================================
 * G) Admin çalıştırma servisi: aşama sırası (sunucu tarafı), onay ifadesi, güvenli DTO, uçtan uca dört aşama
 * ================================================================ */
$B4_AS = 'MaviBelge_Core_Import_Admin_Run_Service';
$b4_sector_terms = function ( $world ) {
	$n = 0;
	foreach ( $world->terms as $t ) {
		$n += 'mb_sektor' === $t['taxonomy'] ? 1 : 0;
	}
	return $n;
};
$b4_admin = function ( $imageMapRaw = null ) use ( $b4_fresh_dir ) {
	$dir = $b4_fresh_dir();
	mb_content_fixture_write_dir( $dir, mb_content_fixture_envelopes() );
	copy( dirname( __DIR__, 5 ) . '/data/content/pages.manifest.json', $dir . '/pages.manifest.json' ); // Faz 12: zincirin ilk aşaması (32 sayfa)
	$world              = new MB_Fake_World();
	$world->terms[501]  = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'haber', 'name' => 'Haber', 'description' => '', 'parent' => 0, 'meta' => array() );
	$world->terms[502]  = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'duyuru', 'name' => 'Duyuru', 'description' => '', 'parent' => 0, 'meta' => array() );
	$o                  = new stdClass();
	$o->dir             = $dir;
	$o->world           = $world;
	$o->build           = function ( $raw ) use ( $dir, $world ) {
		$store            = new MB_Fake_Run_Store( $world );
		$store->installed = true;
		return new MaviBelge_Core_Import_Runtime_Factory(
			array(
				'manifest_dir'         => $dir,
				'image_map_raw'        => $raw,
				'attachment_inspector' => function ( $id ) {
					return array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'mime' => 'image/png', 'readable' => true );
				},
				'repository_factory'   => function ( array $map ) use ( $world ) {
					return new MB_Fake_World_Repository( $world, $map );
				},
				'writer'               => new MB_Fake_Writer( $world ),
				'tx'                   => new MB_Fake_Transaction( $world ),
				'store'                => $store,
				'audit'                => new MB_Fake_Audit_Sink( $world ),
			)
		);
	};
	$o->factory = call_user_func( $o->build, $imageMapRaw );
	$o->svc     = new MaviBelge_Core_Import_Admin_Run_Service( $o->factory );
	return $o;
};
$b4_G = 'MaviBelge_Core_Import_Admin_Gates';
$b4_pv_and_start = function ( $o, $stage, $phrase = null ) use ( $b4_G ) {
	$p = $o->svc->preview_stage( $stage );
	return array( $p, $o->svc->start_apply( $stage, (string) $p['plan_digest'], null === $phrase ? (string) $b4_G::apply_phrase( $stage, (string) $p['plan_digest'] ) : $phrase, 7 ) );
};
$b4_drive = function ( $o, $uid, $expected = 0 ) {
	$last = null;
	for ( $i = 0; $i < 20; $i++ ) {
		$last = $o->svc->advance_apply( $uid, $expected + $i, 7 );
		if ( ! $last['ok'] || 'paused' !== $last['status'] ) {
			break;
		}
	}
	return $last;
};

$b4_a = $b4_admin();
mb_test( '6B4 admin servisi: BATCH_SIZE sabit 10', 10 === $B4_AS::BATCH_SIZE );
$b4_pq = $b4_a->svc->preview_stage( 'qualifications' );
mb_test( '6B4 admin aşama sırası: sektörler tamamlanmadan `qualifications` önizlemesi önkoşulu karşılanmıyor olarak işaretler (sectors gerekli)',
	true === $b4_pq['ok'] && false === $b4_pq['prerequisites']['met'] && 'sectors' === $b4_pq['prerequisites']['requires'] );
$b4_sq = $b4_a->svc->start_apply( 'qualifications', (string) $b4_pq['plan_digest'], (string) $b4_G::apply_phrase( 'qualifications', (string) $b4_pq['plan_digest'] ), 7 );
mb_test( '6B4 admin aşama sırası: UI\'ya güvenilmez — sunucu `qualifications` başlatmayı önkoşul yokken reddeder (prerequisite_not_met); run/yazma YOK', false === $b4_sq['ok'] && 'prerequisite_not_met' === $b4_sq['error_code'] && array() === $b4_a->world->runs && array() === $b4_a->world->writeLog );
$b4_ss = $b4_a->svc->start_apply( 'all', str_repeat( 'a', 64 ), 'UYGULA all aaaaaaaaaaaa', 7 );
$b4_sc = $b4_a->svc->start_apply( 'content', str_repeat( 'a', 64 ), 'UYGULA content aaaaaaaaaaaa', 7 );
mb_test( '6B4 admin aşama sırası: `all` ve `content` de önceki aşamalar olmadan reddedilir', 'prerequisite_not_met' === $b4_ss['error_code'] && 'prerequisite_not_met' === $b4_sc['error_code'] && array() === $b4_a->world->runs );
$b4_ps0 = $b4_a->svc->preview_stage( 'sectors' );
$b4_sr0 = $b4_a->svc->start_apply( 'sectors', (string) $b4_ps0['plan_digest'], (string) $b4_G::apply_phrase( 'sectors', (string) $b4_ps0['plan_digest'] ), 7 );
mb_test( 'Faz 12 admin aşama sırası: `pages` tamamlanmadan `sectors` önizlemesi önkoşulu karşılanmıyor olarak işaretler (pages gerekli); sunucu başlatmayı reddeder (prerequisite_not_met); run/yazma YOK',
	true === $b4_ps0['ok'] && false === $b4_ps0['prerequisites']['met'] && 'pages' === $b4_ps0['prerequisites']['requires'] && false === $b4_sr0['ok'] && 'prerequisite_not_met' === $b4_sr0['error_code'] && array() === $b4_a->world->runs && array() === $b4_a->world->writeLog );
$b4_pgp = $b4_a->svc->preview_stage( 'pages' );
mb_test( 'Faz 12 admin: `pages` önkoşulsuz ilk aşama; 32 create, uygulanabilir, güvenli DTO (alan/içerik/yol YOK)',
	true === $b4_pgp['ok'] && true === $b4_pgp['prerequisites']['met'] && null === $b4_pgp['prerequisites']['requires'] && true === $b4_pgp['eligible'] && 32 === $b4_pgp['writes'] && 32 === count( $b4_pgp['entries'] ) && 'page' === $b4_pgp['entries'][0]['type'] && false === strpos( json_encode( $b4_pgp ), 'İskenderun' ) && false === strpos( json_encode( $b4_pgp ), $b4_a->dir ) );
$b4_pg = $b4_pv_and_start( $b4_a, 'pages' );
$b4_pgl = $b4_drive( $b4_a, $b4_pg[1]['run_uid'] );
mb_test( 'Faz 12 admin: `pages` aşaması istek başına en çok 10 kayıtla (4 istek) completed; 32 taslak sayfa; readback 32 unchanged',
	true === $b4_pg[1]['ok'] && true === $b4_pgl['ok'] && 'completed' === $b4_pgl['status'] && 32 === $b4_pgl['committed'] && 32 === count( $b4_a->world->posts ) && 32 === $b4_a->svc->preview_stage( 'pages' )['summary']['operations']['unchanged'] );
$b4_base_runs = count( $b4_a->world->runs );
$b4_base_wl   = count( $b4_a->world->writeLog );
$b4_ps = $b4_a->svc->preview_stage( 'sectors' );
mb_test( '6B4 admin önizleme: sectors önkoşulu (pages) karşılandıktan sonra uygulanabilir (25 create), güvenli DTO — yalnız sayaç, source_key/tür/karar/reason; alan değeri, dosya yolu, SQL YOK',
	true === $b4_ps['ok'] && true === $b4_ps['prerequisites']['met'] && true === $b4_ps['eligible'] && 25 === $b4_ps['writes'] && 25 === $b4_ps['summary']['operations']['create'] && 25 === count( $b4_ps['entries'] ) && array( 'decision', 'reason', 'source_key', 'type' ) === ( function ( $keys ) {
		sort( $keys );
		return $keys;
	} )( array_keys( $b4_ps['entries'][0] ) )
	&& false === strpos( json_encode( $b4_ps ), 'TEST Sektör' ) && false === strpos( json_encode( $b4_ps ), 'Sahte test' ) && false === strpos( json_encode( $b4_ps ), '/tmp' ) && false === strpos( json_encode( $b4_ps ), $b4_a->dir ) );
$b4_bad_phr = array( 'uygula sectors ' . substr( $b4_ps['plan_digest'], 0, 12 ), 'UYGULA sectors ' . substr( $b4_ps['plan_digest'], 0, 11 ), 'UYGULA sectors ' . substr( $b4_ps['plan_digest'], 0, 12 ) . ' ', 'UYGULA all ' . substr( $b4_ps['plan_digest'], 0, 12 ), '' );
$b4_phr_rej = true;
foreach ( $b4_bad_phr as $bad ) {
	$r          = $b4_a->svc->start_apply( 'sectors', $b4_ps['plan_digest'], $bad, 7 );
	$b4_phr_rej = $b4_phr_rej && false === $r['ok'] && 'confirmation_phrase_mismatch' === $r['error_code'];
}
mb_test( '6B4 admin onay: yanlış/eksik/harf-boşluk hatalı ifade -> confirmation_phrase_mismatch; run/yazma YOK', $b4_phr_rej && $b4_base_runs === count( $b4_a->world->runs ) && $b4_base_wl === count( $b4_a->world->writeLog ) );
$b4_stale = $b4_a->svc->start_apply( 'sectors', str_repeat( 'e', 64 ), (string) $b4_G::apply_phrase( 'sectors', str_repeat( 'e', 64 ) ), 7 );
mb_test( '6B4 admin onay: kullanıcının gördüğü digest sunucunun yeniden ürettiğiyle eşit değilse (ifade kendi digest\'ine uysa da) confirmation_mismatch; run YOK', 'confirmation_mismatch' === $b4_stale['error_code'] && $b4_base_runs === count( $b4_a->world->runs ) );
$b4_started = $b4_a->svc->start_apply( 'sectors', $b4_ps['plan_digest'], $b4_G::apply_phrase( 'sectors', $b4_ps['plan_digest'] ), 7 );
mb_test( '6B4 admin start: doğru ifade + digest -> run `ready`, safe DTO (yalnız ok/status/error_code/run_uid/checkpoint/total/committed/remaining/plan_digest/stage)',
	true === $b4_started['ok'] && 'ready' === $b4_started['status'] && 25 === $b4_started['total'] && 0 === $b4_started['checkpoint'] && 25 === $b4_started['remaining'] && array( 'checkpoint', 'committed', 'error_code', 'ok', 'plan_digest', 'remaining', 'run_uid', 'stage', 'status', 'total' ) === ( function ( $keys ) {
		sort( $keys );
		return $keys;
	} )( array_keys( $b4_started ) ) );
$b4_uid_a = $b4_started['run_uid'];
$b4_adv1  = $b4_a->svc->advance_apply( $b4_uid_a, 0, 7 );
mb_test( '6B4 admin advance: bir istek bir batch (10), paused', true === $b4_adv1['ok'] && 'paused' === $b4_adv1['status'] && 10 === $b4_adv1['committed'] && 15 === $b4_adv1['remaining'] && 1 === $b4_adv1['checkpoint'] );
$b4_adup = $b4_a->svc->advance_apply( $b4_uid_a, 0, 7 );
mb_test( '6B4 admin advance: çift tıklama (aynı checkpoint) stale_request; ikinci yazma YOK', 'stale_request' === $b4_adup['error_code'] && 10 === $b4_sector_terms( $b4_a->world ) );
$b4_ps_stale = $b4_a->svc->start_apply( 'sectors', $b4_ps['plan_digest'], $b4_G::apply_phrase( 'sectors', $b4_ps['plan_digest'] ), 7 );
$b4_ps_new   = $b4_a->svc->preview_stage( 'sectors' );
$b4_second   = $b4_a->svc->start_apply( 'sectors', $b4_ps_new['plan_digest'], $b4_G::apply_phrase( 'sectors', $b4_ps_new['plan_digest'] ), 7 );
mb_test( '6B4 admin start: yarım (paused) run varken eski önizleme digest\'i confirmation_mismatch, güncel önizleme digest\'i de unresolved_run_exists ile reddedilir; ikinci run YOK',
	'confirmation_mismatch' === $b4_ps_stale['error_code'] && 'unresolved_run_exists' === $b4_second['error_code'] && $b4_base_runs + 1 === count( $b4_a->world->runs ) );
$b4_last = $b4_drive( $b4_a, $b4_uid_a, 1 );
mb_test( '6B4 admin: sectors aşaması iki istek daha sonra completed (25/25), readback unchanged', true === $b4_last['ok'] && 'completed' === $b4_last['status'] && 25 === $b4_last['committed'] && 25 === $b4_sector_terms( $b4_a->world ) && 25 === $b4_a->svc->preview_stage( 'sectors' )['summary']['operations']['unchanged'] );

/* aşama 2: qualifications */
$b4_pq2 = $b4_a->svc->preview_stage( 'qualifications' );
mb_test( '6B4 admin aşama sırası: sektörler gerçek readback sonucunda unchanged olunca `qualifications` açılır (önkoşul karşılandı, 3 create)', true === $b4_pq2['prerequisites']['met'] && true === $b4_pq2['eligible'] && 3 === $b4_pq2['writes'] );
$b4_q = $b4_pv_and_start( $b4_a, 'qualifications' );
$b4_ql = $b4_drive( $b4_a, $b4_q[1]['run_uid'] );
mb_test( '6B4 admin: qualifications çok istekle (tek batch) completed', true === $b4_q[1]['ok'] && true === $b4_ql['ok'] && 'completed' === $b4_ql['status'] && 3 === $b4_ql['committed'] );
$b4_pa = $b4_a->svc->preview_stage( 'all' );
mb_test( '6B4 admin aşama sırası: `content` hâlâ kapalı (yalnız `all` unchanged olunca açılır); `all` açık', false === $b4_a->svc->preview_stage( 'content' )['prerequisites']['met'] && true === $b4_pa['prerequisites']['met'] && 5 === $b4_pa['writes'] );
$b4_all = $b4_pv_and_start( $b4_a, 'all' );
$b4_alll = $b4_drive( $b4_a, $b4_all[1]['run_uid'] );
mb_test( '6B4 admin: all aşaması (5 ücret) completed', true === $b4_alll['ok'] && 'completed' === $b4_alll['status'] && 5 === $b4_alll['committed'] );
$b4_pc = $b4_a->svc->preview_stage( 'content' );
mb_test( '6B4 admin aşama sırası: katalog aşamaları tamamlanınca `content` açılır (haber+referans, 6 create)', true === $b4_pc['prerequisites']['met'] && true === $b4_pc['eligible'] && 6 === $b4_pc['writes'] );
$b4_con = $b4_pv_and_start( $b4_a, 'content' );
$b4_conl = $b4_drive( $b4_a, $b4_con[1]['run_uid'] );
mb_test( '6B4 admin: content aşaması completed; dört aşamanın hepsi sırayla tamamlandı', true === $b4_conl['ok'] && 'completed' === $b4_conl['status'] && 6 === $b4_conl['committed'] && 5 === count( $b4_a->svc->list_runs() ) );
$b4_runs = $b4_a->svc->list_runs();
mb_test( '6B4 admin run listesi: en yeni önce, yalnız güvenli sütunlar (uid, stage, status, error_code, sayaçlar, tarih, eylem); digest/manifest/yol YOK',
	'content' === $b4_runs[0]['stage'] && 'sectors' === $b4_runs[3]['stage'] && 'pages' === $b4_runs[4]['stage'] && 'completed' === $b4_runs[0]['status'] && 'preview_rollback' === $b4_runs[0]['next_action'] && false === strpos( json_encode( $b4_runs ), 'digest' ) && false === strpos( json_encode( $b4_runs ), $b4_a->dir ) );

/* rollback: önizleme -> ifade -> çok istek -> reapply */
$b4_rp = $b4_a->svc->preview_rollback( $b4_conl['run_uid'] );
mb_test( '6B4 admin rollback önizleme: 6 bekleyen item, engel yok, digest 64-hex; DTO güvenli', true === $b4_rp['ok'] && 6 === $b4_rp['items_pending'] && array() === $b4_rp['blockers'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $b4_rp['rollback_digest'] ) );
$b4_rphr = $b4_G::rollback_phrase( $b4_conl['run_uid'], $b4_rp['rollback_digest'] );
$b4_rbad = $b4_a->svc->start_rollback( $b4_conl['run_uid'], $b4_rp['rollback_digest'], (string) $b4_G::apply_phrase( 'content', $b4_rp['rollback_digest'] ) );
mb_test( '6B4 admin rollback onay: apply ifadesi rollback için kullanılamaz (confirmation_phrase_mismatch); durum completed kalır', 'confirmation_phrase_mismatch' === $b4_rbad['error_code'] && 'completed' === $b4_a->factory->run_store()->get_run( $b4_conl['run_uid'] )['status'] );
$b4_rst = $b4_a->svc->start_rollback( $b4_conl['run_uid'], $b4_rp['rollback_digest'], $b4_rphr );
$b4_rad = $b4_a->svc->advance_rollback( $b4_conl['run_uid'], 0 );
mb_test( '6B4 admin rollback: doğru ifade -> rollback_ready; advance -> 6 kayıt geri alınır, rolled_back; içerik kayıtları çöpe gitti (kalıcı silme yok)',
	true === $b4_rst['ok'] && 'rollback_ready' === $b4_rst['status'] && true === $b4_rad['ok'] && 'rolled_back' === $b4_rad['status'] && 6 === $b4_rad['committed'] );
$b4_rpv2 = $b4_a->svc->preview_stage( 'content' );
mb_test( '6B4 admin rollback->reapply: içerik aşaması yeniden 6 create olarak uygulanabilir', 6 === $b4_rpv2['summary']['operations']['create'] && true === $b4_rpv2['eligible'] );

/* map değişimi: run başladıktan sonra görsel map digest'i değişirse advance reddedilir */
$b4_m = $b4_admin();
$b4_msec = json_decode( (string) file_get_contents( $b4_m->dir . '/sectors.manifest.json' ), true )['records'];
$b4_mpg  = $b4_pv_and_start( $b4_m, 'pages' );
$b4_drive( $b4_m, $b4_mpg[1]['run_uid'] );
$b4_mp   = $b4_m->svc->preview_stage( 'sectors' );
$b4_ms   = $b4_m->svc->start_apply( 'sectors', $b4_mp['plan_digest'], $b4_G::apply_phrase( 'sectors', $b4_mp['plan_digest'] ), 7 );
$b4_m2   = call_user_func( $b4_m->build, MaviBelge_Core_Import_Sector_Image_Map::build_envelope( array(), MaviBelge_Core_Import_Sector_Image_Map::manifest_digest_for( $b4_msec ) ) );
$b4_m2s  = new MaviBelge_Core_Import_Admin_Run_Service( $b4_m2 );
$b4_mr   = $b4_m2s->advance_apply( $b4_ms['run_uid'], 0, 7 );
mb_test( '6B4 admin: run başladıktan sonra görsel map durumu (digest) değişti -> map_changed; hiçbir kayıt yazılmaz', false === $b4_mr['ok'] && 'map_changed' === $b4_mr['error_code'] && 0 === $b4_sector_terms( $b4_m->world ) );

/* görsel map kaydı: servis üzerinden, yalnız doğrulanmış map */
$b4_imgview = $b4_a->svc->image_map_view();
mb_test( '6B4 admin görsel map görünümü: gerekli slug listesi manifestten (fixture görselsiz -> 0 zorunlu), durum present=false', array() === $b4_imgview['required'] && false === $b4_imgview['present'] );

/* ================================================================
 * H) Her aşamada çok istekli apply ve rollback (batch=2): sectors 13, qualifications 2, all 3, content 3 istek
 * ================================================================ */
$b4_sb = $b4_admin();
$b4_sb_runs = array();
foreach ( array( 'sectors' => 13, 'qualifications' => 2, 'all' => 3, 'content' => 3 ) as $b4_stage => $b4_requests ) {
	$b4_pv   = $b4_sb->svc->preview_stage( $b4_stage );
	$b4_as   = $b4_sb->factory->apply_service();
	$b4_st   = $b4_as->start_resumable( $b4_stage, (string) $b4_pv['plan_digest'], 2, 7, null );
	$b4_n    = 0;
	$b4_last = null;
	$b4_cp   = 0;
	for ( $b4_i = 0; $b4_i < 30 && $b4_st['ok']; $b4_i++ ) {
		$b4_last = $b4_as->advance_resumable( $b4_st['run_uid'], $b4_cp, 7, null );
		$b4_n++;
		if ( ! $b4_last['ok'] || 'paused' !== $b4_last['status'] ) {
			break;
		}
		$b4_cp = $b4_last['checkpoint'];
	}
	$b4_after = $b4_sb->svc->preview_stage( $b4_stage );
	mb_test( '6B4 çok istekli (batch=2) ' . $b4_stage . ': ' . $b4_requests . ' istekte completed; readback tümü unchanged',
		true === $b4_st['ok'] && $b4_requests === $b4_n && true === $b4_last['ok'] && 'completed' === $b4_last['status'] && $b4_after['summary']['operations']['unchanged'] === $b4_after['summary']['total'] && 0 === $b4_after['writes'] );
	$b4_sb_runs[ $b4_stage ] = $b4_st['run_uid'];
}
$b4_rbs  = $b4_sb->factory->rollback_service();
$b4_rpv  = $b4_rbs->preview( $b4_sb_runs['content'] );
$b4_rst  = $b4_rbs->start_resumable( $b4_sb_runs['content'], $b4_rpv['rollback_digest'] );
$b4_rn   = 0;
$b4_rcp  = 0;
$b4_rlast = null;
for ( $b4_i = 0; $b4_i < 10 && $b4_rst['ok']; $b4_i++ ) {
	$b4_rlast = $b4_rbs->advance_resumable( $b4_sb_runs['content'], $b4_rcp, 2 );
	$b4_rn++;
	if ( ! $b4_rlast['ok'] || 'rollback_paused' !== $b4_rlast['status'] ) {
		break;
	}
	$b4_rcp = $b4_rlast['checkpoint'];
}
mb_test( '6B4 çok istekli (batch=2) content rollback: 3 istekte rolled_back; içerik çöpte', true === $b4_rst['ok'] && 3 === $b4_rn && 'rolled_back' === $b4_rlast['status'] && 6 === $b4_rlast['committed'] && 0 === count( array_filter( $b4_sb->world->posts, function ( $p ) {
	return in_array( $p['post_type'], array( 'mb_haber', 'mb_referans' ), true ) && 'trash' !== $p['status'];
} ) ) );

/* ================================================================
 * I) Görsel eşleme kaydı: option yazımı + audit + commit ATOMİK (Faz 6B4 son kabul düzeltmesi)
 * ================================================================ */
$b4_am_recs  = $b4_real_sector_records;
$b4_am_dig   = MaviBelge_Core_Import_Sector_Image_Map::manifest_digest_for( $b4_am_recs );
$b4_am_full  = $b4_real_full_map;
$b4_am_store = function ( $existing = null ) use ( $b4_am_dig ) {
	$s              = new MB_Fake_Image_Map_Store();
	$s->attachments = array( 1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010 );
	if ( null !== $existing ) {
		$s->db = MaviBelge_Core_Import_Sector_Image_Map::build_envelope( $existing, $b4_am_dig );
	}
	return $s;
};
$b4_am_save = function ( $store, array $map = null ) use ( $b4_am_recs, $b4_am_dig, $b4_am_full ) {
	return MaviBelge_Core_Import_Sector_Image_Map::save( null === $map ? $b4_am_full : $map, $b4_am_recs, $b4_am_dig, $store );
};
$b4_am_env = function ( array $map ) use ( $b4_am_dig ) {
	return MaviBelge_Core_Import_Sector_Image_Map::build_envelope( $map, $b4_am_dig );
};

$s1 = $b4_am_store();
$r1 = $b4_am_save( $s1 );
mb_test( '6B4 map atomik: yeni option + başarılı audit + başarılı commit -> ok; option yazıldı (add), 1 audit, 10 değişen slug; sıra lock -> read -> ready -> begin -> write -> audit -> commit -> unlock',
	true === $r1['ok'] && $b4_am_env( $b4_am_full ) === $s1->db && 1 === count( $s1->audit ) && 10 === count( $r1['changed_slugs'] ) && false === $s1->open()
	&& array( 'lock', 'read', 'ready', 'begin', 'write:add', 'audit', 'commit', 'unlock' ) === array_values( array_filter( $s1->log, function ( $e ) {
		return 'flush' !== $e;
	} ) ) );
$s2   = $b4_am_store( $b4_am_full );
$new2 = array_merge( $b4_am_full, array( 'cam' => 1010 ) );
$r2   = $b4_am_save( $s2, $new2 );
mb_test( '6B4 map atomik: mevcut option güncellemesi + başarılı audit -> ok; write:update; changed_slugs yalnız [cam]; audit old_digest eski map digest\'i',
	true === $r2['ok'] && array( 'cam' ) === $r2['changed_slugs'] && in_array( 'write:update', $s2->log, true ) && $b4_am_env( $new2 ) === $s2->db && 1 === count( $s2->audit ) && array( 'cam' ) === $s2->audit[0]['changed_slugs']
	&& MaviBelge_Core_Import_Sector_Image_Map::digest( $b4_am_dig, $b4_am_full ) === $s2->audit[0]['old_digest'] && $r2['digest'] === $s2->audit[0]['new_digest'] );
$s3 = $b4_am_store( $b4_am_full );
$r3 = $b4_am_save( $s3 );
mb_test( '6B4 map atomik: gerçek değişiklik yoksa no-op başarı; option, audit, transaction ve ready YOK (sahte audit olayı yok)',
	true === $r3['ok'] && array() === $r3['changed_slugs'] && array() === $s3->audit && array() === array_intersect( $s3->log, array( 'ready', 'begin', 'write:add', 'write:update', 'audit', 'commit' ) ) );
$s4            = $b4_am_store();
$s4->readyFlag = false;
$r4            = $b4_am_save( $s4 );
mb_test( '6B4 map atomik: audit/altyapı hazır değil -> infrastructure_unavailable; transaction açılmaz, option DEĞİŞMEZ', false === $r4['ok'] && array( 'infrastructure_unavailable' ) === $r4['errors'] && null === $s4->db && ! in_array( 'begin', $s4->log, true ) && ! in_array( 'write:add', $s4->log, true ) );
$s5            = $b4_am_store();
$s5->failAudit = true;
$r5            = $b4_am_save( $s5 );
mb_test( '6B4 map atomik: audit yazımı false -> audit_failed, ok=false; option ESKİ durumda (yok); rollback ve flush çağrıldı; commit yok; açık transaction yok',
	false === $r5['ok'] && array( 'audit_failed' ) === $r5['errors'] && null === $s5->db && array() === $s5->audit && in_array( 'rollback', $s5->log, true ) && in_array( 'flush', $s5->log, true ) && ! in_array( 'commit', $s5->log, true ) && false === $s5->open() );
$s6            = $b4_am_store();
$s6->failWrite = true;
$r6            = $b4_am_save( $s6 );
mb_test( '6B4 map atomik: option yazımı başarısız -> option_write_failed; audit YAZILMAZ; rollback; option değişmez', false === $r6['ok'] && array( 'option_write_failed' ) === $r6['errors'] && ! in_array( 'audit', $s6->log, true ) && null === $s6->db && in_array( 'rollback', $s6->log, true ) );
$s7            = $b4_am_store();
$s7->failBegin = true;
$r7            = $b4_am_save( $s7 );
mb_test( '6B4 map atomik: transaction BEGIN başarısız -> transaction_begin_failed; hiçbir yazma, audit veya rollback YOK', false === $r7['ok'] && array( 'transaction_begin_failed' ) === $r7['errors'] && null === $s7->db && ! in_array( 'write:add', $s7->log, true ) && ! in_array( 'audit', $s7->log, true ) && ! in_array( 'rollback', $s7->log, true ) );
$s8             = $b4_am_store( $b4_am_full );
$s8->failCommit = true;
$r8             = $b4_am_save( $s8, array_merge( $b4_am_full, array( 'cam' => 1010 ) ) );
mb_test( '6B4 map atomik: COMMIT başarısız -> commit_failed, başarı YOK; güvenli rollback denendi; option eski map', false === $r8['ok'] && array( 'commit_failed' ) === $r8['errors'] && $b4_am_env( $b4_am_full ) === $s8->db && array() === $s8->audit && in_array( 'rollback', $s8->log, true ) );
$s9               = $b4_am_store();
$s9->failAudit    = true;
$s9->failRollback = true;
$r9               = $b4_am_save( $s9 );
mb_test( '6B4 map atomik: rollback da başarısız -> sabit transaction_rollback_failed; ASLA sahte başarı', false === $r9['ok'] && array( 'transaction_rollback_failed' ) === $r9['errors'] && null === $r9['digest'] && array() === $r9['changed_slugs'] );
$s10            = $b4_am_store();
$s10->failAudit = true;
$b4_am_save( $s10 );
mb_test( '6B4 map atomik: eski option HİÇ yokken audit hatası -> option oluşturulmuş KALMAZ (db ve önbellek null; read() null)', null === $s10->db && null === $s10->cache && null === $s10->read() );
$s11            = $b4_am_store( $b4_am_full );
$before11       = $s11->db;
$s11->failAudit = true;
$b4_am_save( $s11, array_merge( $b4_am_full, array( 'cam' => 1010 ) ) );
mb_test( '6B4 map atomik: eski option VARKEN audit hatası -> eski zarf birebir (===) korunur', $before11 === $s11->db && $before11 === $s11->read() );
$s12            = $b4_am_store();
$s12->failAudit = true;
$b4_am_save( $s12 );
mb_test( '6B4 map atomik: hata sonrası option önbelleği temizlenir; aynı süreçte read() gerçek (eski) durumu görür — geri alınmış yeni değer önbellekte KALMAZ', null === $s12->cache && null === $s12->read() && in_array( 'flush', $s12->log, true ) );
$b4_ctx = $s1->audit[0];
mb_test( '6B4 map atomik: audit context YALNIZ old_digest, new_digest, changed_slugs; yol, dosya adı, ID veya alan içeriği YOK',
	array( 'changed_slugs', 'new_digest', 'old_digest' ) === ( function ( $k ) {
		sort( $k );
		return $k;
	} )( array_keys( $b4_ctx ) ) && null === $b4_ctx['old_digest'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $b4_ctx['new_digest'] ) && false === strpos( json_encode( $b4_ctx ), '/' ) && false === strpos( json_encode( $b4_ctx ), '.png' ) && false === strpos( json_encode( $b4_ctx ), '1001' ) );
$b4_h = str_repeat( 'a', 64 );
mb_test( '6B4 map atomik: audit_context() kapalı — geçersiz digest, boş/liste olmayan/geçersiz slug null döndürür',
	null === MaviBelge_Core_Import_Sector_Image_Map::audit_context( 'kısa', $b4_h, array( 'cam' ) ) && null === MaviBelge_Core_Import_Sector_Image_Map::audit_context( null, 'kısa', array( 'cam' ) ) && null === MaviBelge_Core_Import_Sector_Image_Map::audit_context( null, $b4_h, array() )
	&& null === MaviBelge_Core_Import_Sector_Image_Map::audit_context( null, $b4_h, array( '../x' ) ) && null === MaviBelge_Core_Import_Sector_Image_Map::audit_context( null, $b4_h, array( 'cam' => 'cam' ) ) && is_array( MaviBelge_Core_Import_Sector_Image_Map::audit_context( str_repeat( 'b', 64 ), $b4_h, array( 'cam' ) ) ) );
$s13              = $b4_am_store();
$s13->writeThrows = true;
$r13              = $b4_am_save( $s13 );
mb_test( '6B4 map atomik: yazım sırasında exception -> unexpected_exception (sabit kod); rollback yapıldı, açık transaction yok, exception metni/yol/SQL SIZMAZ',
	false === $r13['ok'] && array( 'unexpected_exception' ) === $r13['errors'] && null === $s13->db && false === $s13->open() && false === strpos( json_encode( $r13 ), 'mutlak' ) && false === strpos( json_encode( $r13 ), 'SELECT' ) );
$s14 = $b4_am_store();
$r14 = $b4_am_save( $s14, array_diff_key( $b4_am_full, array( 'mermer' => 1 ) ) );
mb_test( '6B4 map atomik: doğrulama hatası (eksik slug) transaction AÇMADAN reddedilir; altyapı bile denenmez', false === $r14['ok'] && in_array( 'missing_slug:mermer', $r14['errors'], true ) && ! in_array( 'begin', $s14->log, true ) && ! in_array( 'ready', $s14->log, true ) );
$s15     = $b4_am_store();
$s15->db = MaviBelge_Core_Import_Sector_Image_Map::build_envelope( $b4_am_full, str_repeat( '0', 64 ) );
$r15     = $b4_am_save( $s15 );
mb_test( '6B4 map atomik: önceki option geçersizdi (eski manifest digest\'i) -> yeni geçerli map 10 slug değişti sayılır, old_digest null; update yolu (option vardı)', true === $r15['ok'] && 10 === count( $r15['changed_slugs'] ) && null === $s15->audit[0]['old_digest'] && in_array( 'write:update', $s15->log, true ) );
$b4_am_over = function ( $store ) use ( $b4_real_dir ) {
	return new MaviBelge_Core_Import_Runtime_Factory(
		array(
			'manifest_dir'       => $b4_real_dir,
			'image_map_raw'      => null,
			'image_map_store'    => $store,
			'repository_factory' => function ( array $map ) {
				return new MB_Fake_World_Repository( new MB_Fake_World(), $map );
			},
		)
	);
};
$s16            = $b4_am_store();
$s16->failAudit = true;
$svc16          = new MaviBelge_Core_Import_Admin_Run_Service( $b4_am_over( $s16 ) );
$r16            = $svc16->save_image_map( $b4_am_full );
mb_test( '6B4 map atomik (admin servisi/JSON): audit hatasında ok=false ve SABİT kod (audit_failed); digest null, changed_slugs boş; option oluşmadı; yol/SQL sızmaz',
	false === $r16['ok'] && array( 'audit_failed' ) === $r16['error_codes'] && null === $r16['digest'] && array() === $r16['changed_slugs'] && null === $s16->db && false === strpos( json_encode( $r16 ), '/' ) );
$s17 = $b4_am_store();
$r17 = ( new MaviBelge_Core_Import_Admin_Run_Service( $b4_am_over( $s17 ) ) )->save_image_map( $b4_am_full );
mb_test( '6B4 map atomik (admin servisi/JSON): başarılı kayıt ok=true, 10 değişen slug, digest 64-hex, 1 audit', true === $r17['ok'] && 10 === count( $r17['changed_slugs'] ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $r17['digest'] ) && 1 === count( $s17->audit ) );

/* ---- Eşzamanlı iki yönetici: eski/yeni digest audit doğruluğu (kilit + kilit içinde yeniden okuma) ---- */
$b4_race_digest_of = function ( $envelope ) use ( $b4_am_dig ) {
	return MaviBelge_Core_Import_Sector_Image_Map::digest( $b4_am_dig, $envelope['mappings'] );
};
$b4_race_m1  = array_merge( $b4_am_full, array( 'cam' => 1010 ) );
$b4_race_m2  = array_merge( $b4_am_full, array( 'tekstil' => 1010 ) );
$sr1         = $b4_am_store( $b4_am_full );
$sr1->rival  = function ( $st ) use ( $b4_am_env, $b4_race_m1 ) {
	$st->db    = $b4_am_env( $b4_race_m1 );
	$st->cache = $st->db;
};
$rr1 = $b4_am_save( $sr1, $b4_race_m2 );
mb_test( '6B4 map yarış: rakip yönetici okumadan SONRA yazamaz (kilit) VEYA audit old_digest gerçekten değiştirilen duruma eşit; audit eski/yeni digest\'i asla yalan söylemez',
	true === $rr1['ok'] && 1 === count( $sr1->audit ) && 1 === count( $sr1->replaced ) && $b4_race_digest_of( $sr1->replaced[0] ) === $sr1->audit[0]['old_digest'] && $b4_am_env( $b4_race_m2 ) === $sr1->db );
$sr2           = $b4_am_store( $b4_am_full );
$sr2->lockFail = true;
$rr2           = $b4_am_save( $sr2, $b4_race_m2 );
mb_test( '6B4 map yarış: kilit alınamazsa sabit `locked`; okuma/ready/begin/yazma/audit YOK; option değişmez',
	false === $rr2['ok'] && array( 'locked' ) === $rr2['errors'] && $b4_am_env( $b4_am_full ) === $sr2->db && array() === $sr2->audit && array() === array_intersect( $sr2->log, array( 'read', 'ready', 'begin', 'write:add', 'write:update', 'audit', 'commit' ) ) && in_array( 'lock', $sr2->log, true ) );
$b4_race_ord = function ( $store ) {
	return array_values( array_filter( $store->log, function ( $e ) {
		return 'flush' !== $e;
	} ) );
};
$sr3 = $b4_am_store( $b4_am_full );
$b4_am_save( $sr3, $b4_race_m2 );
$ord3 = $b4_race_ord( $sr3 );
mb_test( '6B4 map yarış: sıra lock -> read -> ready -> begin -> write -> audit -> commit -> unlock; kilit her zaman en son bırakılır',
	array( 'lock', 'read', 'ready', 'begin', 'write:update', 'audit', 'commit', 'unlock' ) === $ord3 && false === $sr3->lockHeld );
$b4_race_fail = array( 'failAudit', 'failCommit', 'failBegin', 'failWrite', 'writeThrows' );
$b4_race_ok   = true;
foreach ( $b4_race_fail as $flag ) {
	$sx        = $b4_am_store( $b4_am_full );
	$sx->$flag = true;
	$b4_am_save( $sx, $b4_race_m2 );
	$ordx       = $b4_race_ord( $sx );
	$b4_race_ok = $b4_race_ok && false === $sx->lockHeld && 'unlock' === end( $ordx );
}
$sx            = $b4_am_store( $b4_am_full );
$sx->readyFlag = false;
$b4_am_save( $sx, $b4_race_m2 );
$b4_race_ok = $b4_race_ok && false === $sx->lockHeld;
$sx         = $b4_am_store( $b4_am_full );
$b4_am_save( $sx );
$b4_race_ok = $b4_race_ok && false === $sx->lockHeld && in_array( 'unlock', $sx->log, true );
mb_test( '6B4 map yarış: kilit başarı, no-op, altyapı hatası, begin/write/audit/commit hatası ve exception yollarının HEPSİNDE bırakılır (kilit sızıntısı yok)', true === $b4_race_ok );
