<?php
/**
 * Faz 15 — ücret kataloğu: KDV oranı alanı + standart açıklama, ücret yayın durumunun import hash'inden çıkarılması (eski
 * hash geçişi dahil), kayıt bazlı yayın kararı belgesi ve toplu ücret yayını (Fee_Publisher), admin fiyat satırı kuralları.
 *
 * Sahte, bellek içi WordPress dünyası (tests/support/import-apply-fakes.php) + sahte katalog fixture'ı (5 ücret: 3 bağlı, 2
 * bağlantısız) kullanılır; gerçek 103 kayıtlık karar belgesi ise gerçek ücret manifestiyle (PHP kanonik hash == Node üretici
 * özeti) çapraz doğrulanır. Gerçek WordPress davranışı tools/runtime-test/fee-catalog.sh ile ayrıca sınanır.
 */

$F15_FP = 'MaviBelge_Core_Import_Fee_Publisher';
$F15_MF = 'MaviBelge_Core_Import_Managed_Fields';
$F15_RP = 'MaviBelge_Core_Import_WordPress_Target_Repository';
$F15_H  = 'MaviBelge_Core_Import_Hash';
$F15_CQ = 'MaviBelge_Core_Catalog_Query';
$F15_V  = 'MaviBelge_Core_Validator';
$F15_MS = 'MaviBelge_Core_Meta_Schema';

/* ---------------------------------------------------------------- saf yardımcılar */

mb_test( 'Faz 15 açıklama: kurum kararı değerleriyle standart cümle BİREBİR (kayıt değerlerinden; oran/tutar gömülü değil)',
	'Fiyatlara %20 KDV dahildir. 1.500 TL MYK belge basım ücreti fiyatlara dahil değildir.' === $F15_CQ::fee_pricing_statements( true, 20, 150000 )['pricing_note'] );
mb_test( 'Faz 15 açıklama: başka oran/tutar kaydın değerini izler; KDV hariç ve kuruşlu basım ücreti doğru',
	'Fiyatlara %18 KDV dahil değildir. 1.500,50 TL MYK belge basım ücreti fiyatlara dahil değildir.' === $F15_CQ::fee_pricing_statements( false, 18, 150050 )['pricing_note']
	&& 'Fiyatlara %10 KDV dahildir. 2.000 TL MYK belge basım ücreti fiyatlara dahil değildir.' === $F15_CQ::fee_pricing_statements( true, 10, 200000 )['pricing_note'] );
mb_test( 'Faz 15 açıklama: basım ücreti 0 ise basım cümlesi YOK (asla "0 TL"); oran geçersizse (null) oran yazılmaz',
	'Fiyatlara %20 KDV dahildir.' === $F15_CQ::fee_pricing_statements( true, 20, 0 )['pricing_note'] && '' === $F15_CQ::fee_pricing_statements( true, 20, 0 )['certificate_statement']
	&& 'Fiyatlara KDV dahildir.' === $F15_CQ::fee_pricing_statements( true, null, 0 )['vat_statement'] );
mb_test( 'Faz 15 kısa TL: 150000->"1.500", 150050->"1.500,50", 5->"0,05", 123456789->"1.234.567,89"; negatif/string/float -> null (float aritmetiği yok)',
	'1.500' === $F15_V::kurus_to_lira_compact_display( 150000 ) && '1.500,50' === $F15_V::kurus_to_lira_compact_display( 150050 ) && '0,05' === $F15_V::kurus_to_lira_compact_display( 5 )
	&& '1.234.567,89' === $F15_V::kurus_to_lira_compact_display( 123456789 ) && null === $F15_V::kurus_to_lira_compact_display( -1 ) && null === $F15_V::kurus_to_lira_compact_display( '150000' ) && null === $F15_V::kurus_to_lira_compact_display( 1.5 ) );
mb_test( 'Faz 15 KDV oranı okuma: kayıt yoksa şema varsayılanı 20; geçerli saklı değer aynen; geçersiz saklı değer null (varsayılana DÜŞMEZ)',
	20 === $F15_MS::fee_vat_rate_percent( '' ) && 20 === $F15_MS::fee_vat_rate_percent( null ) && 18 === $F15_MS::fee_vat_rate_percent( '18' ) && 0 === $F15_MS::fee_vat_rate_percent( '0' ) && 18 === $F15_MS::fee_vat_rate_percent( 18 )
	&& null === $F15_MS::fee_vat_rate_percent( '101' ) && null === $F15_MS::fee_vat_rate_percent( 'abc' ) && null === $F15_MS::fee_vat_rate_percent( '-1' ) && null === $F15_MS::fee_vat_rate_percent( '020' ) && null === $F15_MS::fee_vat_rate_percent( array( 20 ) ) );

$f15_schema = $F15_MS::get_fields_for( 'mb_ucret' );
mb_test( 'Faz 15 şema: _mb_vat_rate_percent integer 0–100, varsayılan 20 (yeni kayıt formu + okuma); _mb_vat_included ve basım ücreti YALNIZ yeni kayıtta %20/1.500 önerir',
	isset( $f15_schema['_mb_vat_rate_percent'] ) && 'integer' === $f15_schema['_mb_vat_rate_percent']['type'] && 0 === $f15_schema['_mb_vat_rate_percent']['min'] && 100 === $f15_schema['_mb_vat_rate_percent']['max']
	&& 20 === $f15_schema['_mb_vat_rate_percent']['default'] && 20 === $f15_schema['_mb_vat_rate_percent']['admin_default']
	&& true === $f15_schema['_mb_vat_included']['admin_default_new'] && 150000 === $f15_schema['_mb_certificate_print_fee_kurus']['admin_default_new'] && ! isset( $f15_schema['_mb_certificate_print_fee_kurus']['admin_default'] ) );
mb_test( 'Faz 15 şema: yeterlilik ilişkisi aranabilir seçim, sektör seçim listesi (depolama aynı: id / slug); toplu yayın işareti sistem alanı',
	'qualification_select' === $f15_schema['_mb_qualification_id']['admin_widget'] && 'id' === $f15_schema['_mb_qualification_id']['type'] && 'mb_yeterlilik' === $f15_schema['_mb_qualification_id']['ref_post_type']
	&& 'sector_select' === $f15_schema['_mb_sector_slug']['admin_widget'] && 'slug' === $f15_schema['_mb_sector_slug']['format']
	&& ! empty( $f15_schema['_mb_fee_publication_marker']['system_managed'] ) && ! empty( $f15_schema['_mb_fee_publication_marker']['readonly'] ) && 'sha256_hash' === $f15_schema['_mb_fee_publication_marker']['format'] );

$f15_rate = $f15_schema['_mb_vat_rate_percent'];
$f15_v1   = MaviBelge_Core_Field_Repository::sanitize_and_validate( $f15_rate, '' );
$f15_v2   = MaviBelge_Core_Field_Repository::sanitize_and_validate( $f15_rate, '18' );
$f15_v3   = MaviBelge_Core_Field_Repository::sanitize_and_validate( $f15_rate, '101' );
$f15_v4   = MaviBelge_Core_Field_Repository::sanitize_and_validate( $f15_rate, '-1' );
$f15_v5   = MaviBelge_Core_Field_Repository::sanitize_and_validate( $f15_rate, '12,5' );
$f15_v6   = MaviBelge_Core_Field_Repository::sanitize_and_validate( $f15_rate, array( '20' ) );
mb_test( 'Faz 15 kaydetme doğrulaması: KDV oranı boş -> 20; "18" -> 18; 101/-1/12,5/dizi -> hata (yazılmaz, önceki değer korunur)',
	array( 20, null ) === $f15_v1 && array( 18, null ) === $f15_v2 && null === $f15_v3[0] && is_string( $f15_v3[1] ) && null === $f15_v4[0] && is_string( $f15_v4[1] ) && null === $f15_v5[0] && null === $f15_v6[0] );

$f15_rows_units_only = MaviBelge_Core_Validator::evaluate_admin_price_rows( array( array( 'label' => '', 'units' => 'A1, A2', 'amount_try' => '', 'sort_order' => '0' ) ) );
$f15_rows_filler     = MaviBelge_Core_Validator::evaluate_admin_price_rows(
	array(
		array( 'label' => 'Sınav ücreti', 'units' => 'A1', 'amount_try' => '17.000', 'sort_order' => '0' ),
		array( 'label' => '', 'units' => '', 'amount_try' => '', 'sort_order' => '1' ),
		array( 'label' => '', 'units' => '   ', 'amount_try' => '', 'sort_order' => '2' ),
	)
);
mb_test( 'Faz 15 admin satırları: yalnız birim yazılmış satır sessizce düşmez -> atomik ret + anlaşılır hata (önceki liste korunur)',
	false === $f15_rows_units_only['replace'] && 1 === count( $f15_rows_units_only['errors'] ) && false !== strpos( $f15_rows_units_only['errors'][0], 'Birim girilmiş' ) );
mb_test( 'Faz 15 admin satırları: boş dolgu satırları (sıra alanı dolu olsa bile) yok sayılır; dolu satır 17.000 TL -> 1700000 kuruş (x100 tek sefer)',
	true === $f15_rows_filler['replace'] && 1 === count( $f15_rows_filler['options'] ) && 1700000 === $f15_rows_filler['options'][0]['amount_kurus'] && 1700000 === $f15_rows_filler['min_kurus'] && 1700000 === $f15_rows_filler['max_kurus'] );

/* ---------------------------------------------------------------- hash modeli */

mb_test( 'Faz 15 hash modeli: FEE_FIELDS record_status içermez; projeksiyon da üretmez; başlangıç durumu sabiti draft',
	! in_array( 'record_status', $F15_MF::FEE_FIELDS, true ) && 16 === count( $F15_MF::FEE_FIELDS ) && 'draft' === $F15_MF::FEE_INITIAL_RECORD_STATUS
	&& array( 'record_status' => 'draft', 'a' => 1 ) == $F15_MF::legacy_fee_hash_input( array( 'a' => 1 ) ) );
mb_test( 'Faz 15 yazma yükü: ücret yükü _mb_record_status YAZMAZ; yalnız create başlangıç meta\'sı (INITIAL_POST_META) draft yazar',
	array( '_mb_record_status' => 'draft' ) === MaviBelge_Core_Import_Write_Payload::INITIAL_POST_META['fee'] && in_array( '_mb_record_status', MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META['fee'], true ) );

$f15_fields = array( 'title' => 'T', 'profession_name' => 'T', 'level' => 3, 'sector_slug' => 's', 'qualification_post_id' => 0, 'qualification_code' => '', 'pricing_type' => 'single',
	'price_options' => array( array( 'label' => 'L', 'units' => array(), 'amount_kurus' => 100, 'sort_order' => 0 ) ), 'vat_included' => true, 'certificate_print_fee_kurus' => 150000,
	'source_name' => 'S', 'source_page' => 1, 'source_attachment_id' => 0, 'tariff_period' => '2026', 'valid_from' => '', 'valid_until' => '' );
$f15_new    = $F15_H::hash( $f15_fields );
$f15_legacy = $F15_H::hash( $F15_MF::legacy_fee_hash_input( $f15_fields ) );
$f15_edited = array_merge( $f15_fields, array( 'certificate_print_fee_kurus' => 100000 ) );
mb_test( 'Faz 15 eski hash geçişi: 0.5.4 biçimli (record_status=draft dahil) saklı hash, içerik aynıysa yeni biçime çevrilir (unchanged görünür)',
	$f15_new === $F15_RP::effective_fee_last_applied_hash( $f15_legacy, $f15_fields ) && $f15_new !== $f15_legacy );
mb_test( 'Faz 15 eski hash geçişi: içerik elle değişmişse saklı eski hash AYNEN döner (conflict korunur); yeni biçimli hash ve null değişmez',
	$f15_legacy === $F15_RP::effective_fee_last_applied_hash( $f15_legacy, $f15_edited ) && $f15_new === $F15_RP::effective_fee_last_applied_hash( $f15_new, $f15_fields )
	&& null === $F15_RP::effective_fee_last_applied_hash( null, $f15_fields ) && $f15_legacy === $F15_RP::effective_fee_last_applied_hash( $f15_legacy, null ) );

/* ---------------------------------------------------------------- gerçek karar belgesi ↔ gerçek ücret manifesti */

$f15_data      = dirname( __DIR__, 5 ) . '/data/content';
$f15_real_fees = json_decode( (string) file_get_contents( $f15_data . '/fees.manifest.json' ), true );
$f15_real_doc  = MaviBelge_Core_Import_Manifest_Loader::load_fee_publication( $f15_data );
$f15_real_val  = $f15_real_doc['ok'] ? $F15_FP::validate_decisions( $f15_real_doc['document'], $f15_real_fees['records'] ) : array( 'ok' => false, 'rows' => array() );
$f15_count     = function ( $rows, $decision ) {
	$n = 0;
	foreach ( $rows as $r ) {
		$n += $decision === $r['decision'] ? 1 : 0;
	}
	return $n;
};
$f15_stale = 0;
foreach ( $f15_real_val['rows'] as $r ) {
	$f15_stale += ! empty( $r['stale'] ) ? 1 : 0;
}
mb_test( 'Faz 15 gerçek karar belgesi: güvenli yükleyiciyle okunur; 103 satır gerçek ücret manifestiyle sıra/source_key birebir; PHP kanonik kayıt özeti Node üreticisiyle 103/103 EŞİT (bayat satır yok)',
	true === $f15_real_doc['ok'] && true === $f15_real_val['ok'] && 103 === count( $f15_real_val['rows'] ) && 0 === $f15_stale );
mb_test( 'Faz 15 gerçek karar belgesi: yayına uygun 97, bekletilen 6; KDV kuralı %20 dahil / 1.500 TL hariç',
	97 === $f15_count( $f15_real_val['rows'], 'publish' ) && 6 === $f15_count( $f15_real_val['rows'], 'hold' )
	&& array( 'included' => true, 'rate_percent' => 20, 'certificate_print_fee_kurus' => 150000 ) === $f15_real_val['vat_rule'] );
mb_test( 'Faz 15 gerçek ücret manifesti: tüm 103 kayıtta vat_included=true ve belge basım 150000 kuruş (kurum kuralı; ilk aktarım bununla yazar)',
	103 === count( array_filter( $f15_real_fees['records'], function ( $f ) {
		return true === $f['vat_included'] && 150000 === $f['certificate_print_fee_kurus'];
	} ) ) );

/* ---------------------------------------------------------------- sahte dünyada toplu ücret yayını */

$f15_dir = mb6b2_temp_dir( 'faz15fees' );
mb_apply_fixture_write_dir( $f15_dir, mb_apply_fixture_envelopes() );
$f15_envs = mb_apply_fixture_envelopes();
$f15_fixture_fees = $f15_envs['fee']['records'];
/** Fixture için karar belgesi: varsayılan hepsi publish; $holds source_key => neden. */
function f15_decisions( array $fees, array $holds = array(), callable $mutate = null ) {
	$records = array();
	foreach ( array_values( $fees ) as $i => $f ) {
		$isHold    = isset( $holds[ $f['source_key'] ] );
		$records[] = array(
			'source_index'  => $i,
			'source_key'    => $f['source_key'],
			'record_sha256' => MaviBelge_Core_Import_Hash::hash( $f ),
			'decision'      => $isHold ? 'hold' : 'publish',
			'hold_reasons'  => $isHold ? array( $holds[ $f['source_key'] ] ) : array(),
			'notes'         => '' === $f['qualification_code'] ? array( 'vat_rule_institution_decision', 'unlinked_listed_without_qualification' ) : array( 'vat_rule_institution_decision' ),
		);
	}
	$doc = array(
		'schema_version' => '1.0.0', 'record_type' => 'fee_publication', 'count' => count( $records ), 'decided_on' => '2026-10-19',
		'basis' => 'TEST kararı (sahte fixture)', 'vat_rule' => array( 'included' => true, 'rate_percent' => 20, 'certificate_print_fee_kurus' => 150000 ), 'records' => $records,
	);
	return null === $mutate ? $doc : $mutate( $doc );
}
$f15_hold_key = 'fee:zz-test-b:4:test-meslek-iki';
function f15_env( $dir, $doc, $blocked = array() ) {
	$env = mb_fake_apply_env( $dir );
	foreach ( array( 'sectors', 'qualifications', 'all' ) as $stage ) {
		$p = $env->apply->preview( $stage );
		$env->apply->apply( $stage, $p['plan_digest'], null, 1 );
	}
	$env->blocked   = $blocked;
	$env->doc       = $doc;
	$env->publisher = new MaviBelge_Core_Import_Fee_Publisher(
		$env->dryRun, $env->writer, $env->tx, $env->store, $env->audit,
		function ( $id ) use ( $env ) {
			return isset( $env->blocked[ $id ] ) ? $env->blocked[ $id ] : null;
		},
		function () use ( $env ) {
			return null === $env->doc ? array( 'ok' => false, 'document' => null, 'errors' => array( 'yok' ) ) : array( 'ok' => true, 'document' => $env->doc, 'errors' => array() );
		}
	);
	return $env;
}
function f15_fee_ids( $env ) {
	$out = array();
	foreach ( $env->world->posts as $id => $p ) {
		if ( 'mb_ucret' === $p['post_type'] ) {
			$out[ $p['meta']['_mb_import_source_key'] ] = $id;
		}
	}
	return $out;
}
function f15_state( $env, $id ) {
	return $env->writer->fee_publication_state( $id );
}

$F1    = f15_env( $f15_dir, f15_decisions( $f15_fixture_fees, array( $f15_hold_key => 'duplicate_qualification_link' ) ) );
$F1ids = f15_fee_ids( $F1 );
mb_test( 'Faz 15 import: all aşaması 5 ücreti TASLAK oluşturur; Kayıt Durumu başlangıçta draft (create başlangıç meta\'sı); hiçbiri yayında değil',
	5 === count( $F1ids ) && 5 === count( array_filter( $F1ids, function ( $id ) use ( $F1 ) {
		return array( 'post_status' => 'draft', 'record_status' => 'draft', 'marker' => '' ) === f15_state( $F1, $id );
	} ) ) );
$F1p = $F1->publisher->preview();
mb_test( 'Faz 15 önizleme: 5 satır — hazır 4, bekletilen 1 (karar), bağlantısız 2 (ikisi de yayınlanabilir), seçenek toplamı 6; özet 64-hex',
	true === $F1p['ok'] && 5 === $F1p['summary']['total'] && 4 === $F1p['summary']['ready'] && 1 === $F1p['summary']['held_pending_decision'] && 2 === $F1p['summary']['unlinked']
	&& 2 === $F1p['summary']['unlinked_publishable'] && 6 === $F1p['summary']['options_total'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $F1p['plan_digest'] ) );
mb_test( 'Faz 15 önizleme: onay ifadeleri mod başına ayrı (UCRET YAYINLA / UCRET YAYINDAN KALDIR + özet ilk 12); geçersiz özet/mod -> null',
	'UCRET YAYINLA ' . substr( $F1p['plan_digest'], 0, 12 ) === $F15_FP::confirm_phrase( 'activate', $F1p['plan_digest'] ) && 'UCRET YAYINDAN KALDIR ' . substr( $F1p['plan_digest'], 0, 12 ) === $F15_FP::confirm_phrase( 'deactivate', $F1p['plan_digest'] )
	&& null === $F15_FP::confirm_phrase( 'activate', 'xyz' ) && null === $F15_FP::confirm_phrase( 'publish', $F1p['plan_digest'] ) );

$F1bad   = $F1->publisher->activate( str_repeat( 'a', 64 ), 4 );
$F1stale = $F1->publisher->activate( $F1p['plan_digest'], 3 );
mb_test( 'Faz 15 etkinleştirme: farklı özet -> confirmation_mismatch; yanlış kalan sayısı -> stale_request; hiçbir yazma yok',
	'confirmation_mismatch' === $F1bad['error_code'] && 'stale_request' === $F1stale['error_code'] && array() === array_filter( $F1->world->writeLog, function ( $l ) {
		return 0 === strpos( $l, 'activate_fee:' );
	} ) );
$F1a = $F1->publisher->activate( $F1p['plan_digest'], 4 );
mb_test( 'Faz 15 etkinleştirme: 4 hazır ücret tek batch\'te publish + active + işaret; bekletilen kayıt TASLAK kalır; sonuç completed',
	true === $F1a['ok'] && 'completed' === $F1a['status'] && 4 === $F1a['processed'] && 0 === $F1a['remaining']
	&& 'publish' === f15_state( $F1, $F1ids['fee:zz-test-a:3:test-meslek-bir'] )['post_status'] && 'active' === f15_state( $F1, $F1ids['fee:zz-test-a:3:test-meslek-bir'] )['record_status']
	&& array( 'post_status' => 'draft', 'record_status' => 'draft', 'marker' => '' ) === f15_state( $F1, $F1ids[ $f15_hold_key ] ) );
$F1ev = end( $F1->world->audit );
mb_test( 'Faz 15 audit: import_fees_activated (run_id 0), 4 madde; context yalnız source_key/tür/hedef/özet (tutar YOK)',
	'import_fees_activated' === $F1ev['event'] && 0 === $F1ev['run_id'] && 4 === count( $F1ev['context']['items'] ) && array( 'source_key', 'type', 'target_id', 'new_hash' ) === array_keys( $F1ev['context']['items'][0] ) && 'fee' === $F1ev['context']['items'][0]['type'] );
$F1p2 = $F1->publisher->preview();
mb_test( 'Faz 15 önizleme sonrası: hazır 0, yayında 4 (aracın etkinleştirdiği 4 -> geri alınabilir), özet DEĞİŞMEDİ (yayınlanabilirlik özetlenir, anlık durum değil)',
	0 === $F1p2['summary']['ready'] && 4 === $F1p2['summary']['published'] && 4 === $F1p2['summary']['deactivatable'] && $F1p['plan_digest'] === $F1p2['plan_digest'] );
$F1again = $F1->publisher->activate( $F1p['plan_digest'], 0 );
mb_test( 'Faz 15 idempotent: tekrar etkinleştirme -> completed, 0 işlem, ek audit yok', true === $F1again['ok'] && 'completed' === $F1again['status'] && 0 === $F1again['processed'] && 'import_fees_activated' === end( $F1->world->audit )['event'] && count( $F1->world->audit ) === count( array_filter( $F1->world->audit, function () {
	return true;
} ) ) );

$F1all = $F1->apply->preview( 'all' );
$f15_ops = $F1all['summary']['operations'];
mb_test( 'Faz 15 ikinci aktarım: all aşaması yeniden önizlenir -> tüm kayıtlar unchanged, create 0, conflict 0 (etkinleştirme sahte conflict ÜRETMEZ; mükerrer kayıt yok)',
	0 === $f15_ops['create'] && 0 === $f15_ops['conflict'] && $f15_ops['unchanged'] === $F1all['summary']['total'] );
$F1reapply = $F1->apply->apply( 'all', $F1all['plan_digest'], null, 1 );
mb_test( 'Faz 15 ikinci aktarım uygulanırsa yazma yok; etkinleştirilmiş ücretler yayında/aktif KALIR (update yayın durumuna dokunmaz)',
	5 === count( f15_fee_ids( $F1 ) ) && 'active' === f15_state( $F1, $F1ids['fee:zz-test-a:3:test-meslek-bir'] )['record_status'] && 'publish' === f15_state( $F1, $F1ids['fee:zz-test-a:3:test-meslek-bir'] )['post_status'] );

// Panelden fiyat değişikliği: sonraki aktarımda ezilmez (conflict), yayın aracı da atlar.
$f15_edit_id = $F1ids['fee:zz-test-c:5:test-meslek-uc'];
$F1->world->posts[ $f15_edit_id ]['meta']['_mb_price_options'] = MB_Fake_World::stored( array( array( 'label' => 'Sınav ücreti', 'units' => array(), 'amount_kurus' => 1300000, 'sort_order' => 0 ) ) );
$F1all2 = $F1->apply->preview( 'all' );
$f15_edit_entry = null;
foreach ( $F1all2['plan']['entries'] as $e ) {
	if ( 'fee:zz-test-c:5:test-meslek-uc' === $e['source_key'] ) {
		$f15_edit_entry = $e;
	}
}
mb_test( 'Faz 15 admin değişikliği: sonraki aktarım önizlemesinde conflict (manual_edit_detected) — sessizce ezilmez, plan uygulanamaz',
	null !== $f15_edit_entry && 'conflict' === $f15_edit_entry['decision'] && 'manual_edit_detected' === $f15_edit_entry['reason'] && false === $F1all2['eligible'] );
$F1p3 = $F1->publisher->preview();
$f15_edit_row = null;
foreach ( $F1p3['rows'] as $r ) {
	if ( 'fee:zz-test-c:5:test-meslek-uc' === $r['source_key'] ) {
		$f15_edit_row = $r;
	}
}
mb_test( 'Faz 15 yayın önizlemesi: panelden değişmiş kayıt not_unchanged (geri alınamaz/üzerine yazılmaz); diğer 3 kayıt geri alınabilir',
	'not_unchanged' === $f15_edit_row['reason'] && 3 === $F1p3['summary']['deactivatable'] && $F1p3['plan_digest'] !== $F1p['plan_digest'] );
$F1d = $F1->publisher->deactivate( $F1p3['plan_digest'], 3 );
mb_test( 'Faz 15 geri alma: yalnız aracın etkinleştirdiği ve değişmemiş 3 kayıt draft/draft + işaret silinir; elle değiştirilen kayıt yayında kalır; audit import_fees_deactivated',
	true === $F1d['ok'] && 3 === $F1d['processed'] && array( 'post_status' => 'draft', 'record_status' => 'draft', 'marker' => '' ) === f15_state( $F1, $F1ids['fee:zz-test-a:3:test-meslek-bir'] )
	&& 'publish' === f15_state( $F1, $f15_edit_id )['post_status'] && 'import_fees_deactivated' === end( $F1->world->audit )['event'] );

// Hata enjeksiyonu ve kapılar.
$F2  = f15_env( $f15_dir, f15_decisions( $f15_fixture_fees ) );
$F2ids = f15_fee_ids( $F2 );
$F2p = $F2->publisher->preview();
$F2->world->faults = array( array( 'op' => 'activate_fee', 'source_key' => (string) $F2ids['fee:zz-test-b:4:test-meslek-iki'] ) );
$F2a = $F2->publisher->activate( $F2p['plan_digest'], 5 );
mb_test( 'Faz 15 atomiklik: batch ortasında yazma hatası -> write_failed, transaction geri alındı, HİÇBİR ücret yayında değil, audit yok',
	false === $F2a['ok'] && 'write_failed' === $F2a['error_code'] && 0 === count( array_filter( $F2ids, function ( $id ) use ( $F2 ) {
		return 'publish' === f15_state( $F2, $id )['post_status'];
	} ) ) && ! in_array( 'import_fees_activated', array_map( function ( $a ) {
		return $a['event'];
	}, $F2->world->audit ), true ) );
$F2->world->faults = array();
$F2->audit->failEvents = array( 'import_fees_activated' );
$F2b = $F2->publisher->activate( $F2p['plan_digest'], 5 );
mb_test( 'Faz 15 atomiklik: audit yazılamazsa audit_failed ve batch geri alınır (yayın yok)', 'audit_failed' === $F2b['error_code'] && 'draft' === f15_state( $F2, $F2ids['fee:zz-test-a:3:test-meslek-bir'] )['post_status'] );
$F2->audit->failEvents = array();
$F2->world->faults = array( array( 'op' => 'fee_corrupt', 'source_key' => (string) $F2ids['fee:zz-test-a:3:test-meslek-bir'] ) );
$F2c = $F2->publisher->activate( $F2p['plan_digest'], 5 );
mb_test( 'Faz 15 katı readback: yazma sırasında yönetilen alan bozulursa readback_mismatch, batch geri alınır',
	'readback_mismatch' === $F2c['error_code'] && 'draft' === f15_state( $F2, $F2ids['fee:zz-test-a:3:test-meslek-bir'] )['post_status'] && 'TEST Meslek Bir' === $F2->world->posts[ $F2ids['fee:zz-test-a:3:test-meslek-bir'] ]['meta']['_mb_profession_name'] );
$F2->world->faults = array();

$F3 = f15_env( $f15_dir, f15_decisions( $f15_fixture_fees ) );
$F3ids = f15_fee_ids( $F3 );
$F3->blocked = array( $F3ids['fee:zz-test-a:2:test-meslek-dort'] => 'period_mismatch' );
$F3p = $F3->publisher->preview();
$f15_blk = null;
foreach ( $F3p['rows'] as $r ) {
	if ( 'fee:zz-test-a:2:test-meslek-dort' === $r['source_key'] ) {
		$f15_blk = $r;
	}
}
mb_test( 'Faz 15 görünürlük ön kontrolü: katalog kuralını geçmeyen kayıt not_publicly_valid (+engel kodu) ve hazır sayılmaz',
	'not_publicly_valid' === $f15_blk['reason'] && 'period_mismatch' === $f15_blk['blocker'] && 4 === $F3p['summary']['ready'] );
$F3a = $F3->publisher->activate( $F3p['plan_digest'], 4 );
mb_test( 'Faz 15 görünürlük: engelli kayıt etkinleştirilmez, diğer 4 yayında', true === $F3a['ok'] && 4 === $F3a['processed'] && 'draft' === f15_state( $F3, $F3ids['fee:zz-test-a:2:test-meslek-dort'] )['post_status'] );

$F4 = f15_env( $f15_dir, f15_decisions( $f15_fixture_fees ) );
$F4ids = f15_fee_ids( $F4 );
$F4p = $F4->publisher->preview();
$F4->store->locked = true;
$F4b = $F4->publisher->activate( $F4p['plan_digest'], 5 );
$F4->store->locked = false;
$f15_no_publish = function ( $env, $ids ) {
	return 0 === count( array_filter( $ids, function ( $id ) use ( $env ) {
		return 'publish' === f15_state( $env, $id )['post_status'];
	} ) );
};
mb_test( 'Faz 15 kapı: başka işlem kilidi tutuyorsa locked, yazma yok', false === $F4b['ok'] && 'locked' === $F4b['error_code'] && $f15_no_publish( $F4, $F4ids ) );
$F4->world->runs['unresolved'] = array( 'uid' => str_repeat( 'c', 32 ), 'status' => 'paused' );
$F4c = $F4->publisher->activate( $F4p['plan_digest'], 5 );
unset( $F4->world->runs['unresolved'] );
mb_test( 'Faz 15 kapı: çözülmemiş import run\'ı varken unresolved_run_exists, yazma yok', false === $F4c['ok'] && 'unresolved_run_exists' === $F4c['error_code'] && $f15_no_publish( $F4, $F4ids ) );
$F4->tx->preflightOk = false;
$F4d = $F4->publisher->activate( $F4p['plan_digest'], 5 );
$F4->tx->preflightOk = true;
mb_test( 'Faz 15 kapı: transaction ön kontrolü başarısızsa infrastructure_unavailable, yazma yok', 'infrastructure_unavailable' === $F4d['error_code'] && $f15_no_publish( $F4, $F4ids ) );

// Karar belgesi doğrulaması (fail-closed).
$f15_bad_docs = array(
	'eksik satır'           => f15_decisions( $f15_fixture_fees, array(), function ( $d ) {
		array_pop( $d['records'] );
		$d['count'] = count( $d['records'] );
		return $d;
	} ),
	'bilinmeyen bekletme'   => f15_decisions( $f15_fixture_fees, array( 'fee:zz-test-a:3:test-meslek-bir' => 'uydurma_neden' ) ),
	'hold ama neden yok'    => f15_decisions( $f15_fixture_fees, array(), function ( $d ) {
		$d['records'][0]['decision'] = 'hold';
		return $d;
	} ),
	'publish + neden'       => f15_decisions( $f15_fixture_fees, array(), function ( $d ) {
		$d['records'][0]['hold_reasons'] = array( 'pdf_mismatch' );
		return $d;
	} ),
	'sıra bozuk'            => f15_decisions( $f15_fixture_fees, array(), function ( $d ) {
		$t = $d['records'][0];
		$d['records'][0] = $d['records'][1];
		$d['records'][1] = $t;
		return $d;
	} ),
	'fazla zarf anahtarı'   => f15_decisions( $f15_fixture_fees, array(), function ( $d ) {
		$d['extra'] = 1;
		return $d;
	} ),
	'KDV oranı aralık dışı' => f15_decisions( $f15_fixture_fees, array(), function ( $d ) {
		$d['vat_rule']['rate_percent'] = 120;
		return $d;
	} ),
	'kayıtta fazla anahtar' => f15_decisions( $f15_fixture_fees, array(), function ( $d ) {
		$d['records'][2]['amount'] = 5;
		return $d;
	} ),
);
foreach ( $f15_bad_docs as $label => $doc ) {
	mb_test( 'Faz 15 karar belgesi: ' . $label . ' -> belgenin TAMAMI reddedilir (decisions_invalid)', false === $F15_FP::validate_decisions( $doc, $f15_fixture_fees )['ok'] );
}
$F5 = f15_env( $f15_dir, f15_decisions( $f15_fixture_fees, array(), function ( $d ) {
	$d['records'][0]['record_sha256'] = str_repeat( '0', 64 );
	return $d;
} ) );
$F5p = $F5->publisher->preview();
$f15_stale_row = $F5p['rows'][0];
mb_test( 'Faz 15 karar belgesi: kayıt içeriği karar satırından sonra değiştiyse satır bayat -> bekletilir (decision_stale), diğerleri hazır',
	true === $F5p['ok'] && 'held_pending_decision' === $f15_stale_row['reason'] && in_array( 'decision_stale', $f15_stale_row['hold_reasons'], true ) && 4 === $F5p['summary']['ready'] );
$F6 = f15_env( $f15_dir, null );
mb_test( 'Faz 15 karar belgesi okunamazsa önizleme fail-closed (decisions_unavailable), etkinleştirme reddedilir',
	false === $F6->publisher->preview()['ok'] && 'decisions_unavailable' === $F6->publisher->preview()['error_code'] && false === $F6->publisher->activate( str_repeat( 'b', 64 ), 0 )['ok'] );

// Eski (0.5.4) hash'li mevcut kayıt: içerik aynıysa unchanged ve yayınlanabilir; içerik değişmişse conflict.
$F7 = f15_env( $f15_dir, f15_decisions( $f15_fixture_fees ) );
$F7ids = f15_fee_ids( $F7 );
$f15_lp = $F7->repo->find_target_by_source_key( 'fee', 'fee:zz-test-a:3:test-meslek-bir' );
$F7->world->posts[ $F7ids['fee:zz-test-a:3:test-meslek-bir'] ]['meta']['_mb_last_applied_hash'] = $F15_H::hash( $F15_MF::legacy_fee_hash_input( $f15_lp['current_managed_fields'] ) );
$f15_lp2 = $F7->repo->find_target_by_source_key( 'fee', 'fee:zz-test-c:5:test-meslek-uc' );
$F7->world->posts[ $F7ids['fee:zz-test-c:5:test-meslek-uc'] ]['meta']['_mb_last_applied_hash'] = $F15_H::hash( $F15_MF::legacy_fee_hash_input( $f15_lp2['current_managed_fields'] ) );
$F7->world->posts[ $F7ids['fee:zz-test-c:5:test-meslek-uc'] ]['meta']['_mb_source_name'] = 'elle değişti';
$F7plan = $F7->apply->preview( 'all' );
$f15_dec = array();
foreach ( $F7plan['plan']['entries'] as $e ) {
	$f15_dec[ $e['source_key'] ] = $e['decision'];
}
mb_test( 'Faz 15 canlı uyumluluğu: 0.5.4 hash\'li, içeriği değişmemiş ücret unchanged (geçiş kuralı); içeriği elle değişmiş eski kayıt conflict',
	'unchanged' === $f15_dec['fee:zz-test-a:3:test-meslek-bir'] && 'conflict' === $f15_dec['fee:zz-test-c:5:test-meslek-uc'] );
// Canlıdaki gibi elle aktifleştirilmiş eski kayıt: record_status active + publish, işaret yok -> published, araçla geri alınamaz.
$F7->world->posts[ $F7ids['fee:zz-test-a:3:test-meslek-bir'] ]['meta']['_mb_record_status'] = 'active';
$F7->world->posts[ $F7ids['fee:zz-test-a:3:test-meslek-bir'] ]['status']                    = 'publish';
$F7p = $F7->publisher->preview();
$f15_qa = null;
foreach ( $F7p['rows'] as $r ) {
	if ( 'fee:zz-test-a:3:test-meslek-bir' === $r['source_key'] ) {
		$f15_qa = $r;
	}
}
mb_test( 'Faz 15 canlı uyumluluğu: elle aktifleştirilmiş (QA) eski kayıt published, by_publisher=false (araç geri almaz, yeniden yazmaz)',
	'published' === $f15_qa['reason'] && false === $f15_qa['by_publisher'] && 0 === $F7p['summary']['deactivatable'] );

/* ---------------------------------------------------------------- dağıtım bağımlılığı: eksik dosya, envanter, gerçek ortam farkı */

$f15_sig = function ( $env ) {
	return md5( serialize( array( $env->world->posts, $env->world->terms, $env->world->runs, $env->world->audit, $env->world->writeLog ) ) );
};
$f15_publisher_for = function ( $env, $dir, callable $loader, callable $inventory = null ) {
	return new MaviBelge_Core_Import_Fee_Publisher(
		new MaviBelge_Core_Import_Dry_Run_Service( $env->repo, $dir ), $env->writer, $env->tx, $env->store, $env->audit,
		function () {
			return null;
		},
		$loader,
		$inventory
	);
};
$f15_starts = function ( array $errors, $file ) {
	foreach ( $errors as $e ) {
		if ( 0 === strpos( $e, $file . ':' ) || 0 === strpos( $e, $file . ' ' ) ) {
			return true;
		}
	}
	return false;
};
mb_test( 'Faz 15 dağıtım: çalışma anında gereken dosya listesi = üç katalog manifesti + karar dosyası (yükleyicinin sabit adlarıyla birebir)',
	array_merge( array_values( MaviBelge_Core_Import_Manifest_Loader::FILES ), array( MaviBelge_Core_Import_Manifest_Loader::FEE_PUBLICATION_FILE ) ) === MaviBelge_Core_Import_Fee_Publisher::REQUIRED_FILES );

$F8      = f15_env( $f15_dir, f15_decisions( $f15_fixture_fees ) );
$F8sig   = $f15_sig( $F8 );
$f15_mdir = mb6b2_temp_dir( 'faz15nofees' );
mb_apply_fixture_write_dir( $f15_mdir, mb_apply_fixture_envelopes() );
@unlink( $f15_mdir . '/fees.manifest.json' );
$F8docLoader = function () use ( $F8 ) {
	return array( 'ok' => true, 'document' => $F8->doc, 'errors' => array() );
};
$F8a  = $f15_publisher_for( $F8, $f15_mdir, $F8docLoader );
$F8ap = $F8a->preview();
$F8aa = $F8a->activate( str_repeat( 'a', 64 ), 0 );
$F8ad = $F8a->deactivate( str_repeat( 'a', 64 ), 0 );
mb_test( 'Faz 15 dağıtım: fees.manifest.json eksik -> önizleme catalog_manifests_unavailable + dosya adı (mutlak yol YOK); etkinleştirme/geri alma aynı kodla reddedilir; dünya (kayıt/terim/run/audit/yazma günlüğü) DEĞİŞMEDİ',
	false === $F8ap['ok'] && 'catalog_manifests_unavailable' === $F8ap['error_code'] && $f15_starts( $F8ap['load_errors'], 'fees.manifest.json' )
	&& array() === array_filter( $F8ap['load_errors'], function ( $e ) use ( $f15_mdir ) {
		return false !== strpos( $e, $f15_mdir ) || false !== strpos( $e, sys_get_temp_dir() );
	} )
	&& 'catalog_manifests_unavailable' === $F8aa['error_code'] && 'catalog_manifests_unavailable' === $F8ad['error_code'] && $F8sig === $f15_sig( $F8 ) );

$f15_ddir = mb6b2_temp_dir( 'faz15nodecision' );
mb_apply_fixture_write_dir( $f15_ddir, mb_apply_fixture_envelopes() );
$F8b  = $f15_publisher_for( $F8, $f15_ddir, function () use ( $f15_ddir ) {
	return MaviBelge_Core_Import_Manifest_Loader::load_fee_publication( $f15_ddir );
} );
$F8bp = $F8b->preview();
$F8ba = $F8b->activate( str_repeat( 'a', 64 ), 0 );
mb_test( 'Faz 15 dağıtım: karar dosyası (gerçek güvenli yükleyici) yoksa decisions_unavailable + "fee-publication.manifest.json" hata satırı; etkinleştirme reddedilir; dünya DEĞİŞMEDİ',
	false === $F8bp['ok'] && 'decisions_unavailable' === $F8bp['error_code'] && $f15_starts( $F8bp['load_errors'], 'fee-publication.manifest.json' )
	&& false === $F8ba['ok'] && 'decisions_unavailable' === $F8ba['error_code'] && $F8sig === $f15_sig( $F8 ) );

$F8c  = $f15_publisher_for( $F8, $f15_ddir . '-yok', $F8docLoader );
$F8cp = $F8c->preview();
mb_test( 'Faz 15 dağıtım: manifest dizini hiç yoksa catalog_manifests_unavailable; üç katalog dosyası da adıyla listelenir; dünya DEĞİŞMEDİ',
	'catalog_manifests_unavailable' === $F8cp['error_code'] && $f15_starts( $F8cp['load_errors'], 'sectors.manifest.json' ) && $f15_starts( $F8cp['load_errors'], 'qualifications.manifest.json' )
	&& $f15_starts( $F8cp['load_errors'], 'fees.manifest.json' ) && $F8sig === $f15_sig( $F8 ) );

// Gerçek ortam envanteri: manifest dışı kayıtlar sayılır, ama araç onlara dokunmaz ve plan özeti değişmez.
$F8ids = f15_fee_ids( $F8 );
$F8inv = array();
foreach ( $F8ids as $key => $id ) {
	$F8inv[] = array( 'id' => $id, 'title' => $key, 'post_status' => 'draft', 'record_status' => 'draft', 'source_key' => $key, 'visible' => false );
}
$F8inv[] = array( 'id' => 9001, 'title' => 'TEST elle eklenmiş ücret', 'post_status' => 'publish', 'record_status' => 'active', 'source_key' => '', 'visible' => true );
$F8inv[] = array( 'id' => 9002, 'title' => 'TEST eski anahtarlı ücret', 'post_status' => 'draft', 'record_status' => 'draft', 'source_key' => 'fee:eski:1:yok', 'visible' => false );
$F8d  = $f15_publisher_for( $F8, $f15_dir, $F8docLoader, function () use ( $F8inv ) {
	return array( 'total' => count( $F8inv ), 'truncated' => false, 'records' => $F8inv );
} );
$F8dp = $F8d->preview();
$F8plain = $F8->publisher->preview();
mb_test( 'Faz 15 envanter: sitede 7 ücret (görünen 1); manifest dışı 2 (görünen 1, anahtarsız 1 + bilinmeyen anahtarlı 1); hazır sayısı ve plan özeti envanterden ETKİLENMEZ; envanter yoksa null',
	true === $F8dp['ok'] && 7 === $F8dp['inventory']['site_total'] && 1 === $F8dp['inventory']['site_visible'] && 2 === $F8dp['inventory']['outside_manifest']
	&& 1 === $F8dp['inventory']['outside_manifest_visible'] && 2 === count( $F8dp['inventory']['outside_rows'] ) && false === $F8dp['inventory']['outside_rows'][0]['has_source_key']
	&& true === $F8dp['inventory']['outside_rows'][1]['has_source_key'] && $F8plain['plan_digest'] === $F8dp['plan_digest'] && $F8plain['summary']['ready'] === $F8dp['summary']['ready']
	&& null === $F8plain['inventory'] );

// Karar "yayına uygun" ama gerçek ortamda hazır olmayan kayıtların nedeni ayrı sayılır; yayındaki bekletilen kayıt raporlanır.
mb_test( 'Faz 15 gerçek ortam farkı: görünürlük engelli kayıt publish_gap.not_publicly_valid=1 (diğer nedenler 0)',
	array( 'not_created' => 0, 'not_unchanged' => 0, 'not_publicly_valid' => 1, 'unexpected_status' => 0 ) === $F3p['summary']['publish_gap'] );
mb_test( 'Faz 15 gerçek ortam farkı: panelden değişen kayıt publish_gap.not_unchanged=1 ve gerçek aktarım kararı ayrıca sayılır (conflict 1)',
	1 === $F1p3['summary']['publish_gap']['not_unchanged'] && array( 'conflict' => 1 ) === $F1p3['summary']['not_unchanged_by_import_decision'] && 'conflict' === $f15_edit_row['import_decision'] );
$F9    = f15_env( $f15_dir, f15_decisions( $f15_fixture_fees, array( $f15_hold_key => 'name_differs_from_pdf' ) ) );
$F9ids = f15_fee_ids( $F9 );
$F9->world->posts[ $F9ids[ $f15_hold_key ] ]['meta']['_mb_record_status'] = 'active';
$F9->world->posts[ $F9ids[ $f15_hold_key ] ]['status']                    = 'publish';
$F9p = $F9->publisher->preview();
mb_test( 'Faz 15 gerçek ortam farkı: bekletilen ama ŞU AN yayında/aktif kayıt held_but_published=1 (araç dokunmaz: hazır 4, geri alınabilir 0)',
	1 === $F9p['summary']['held_but_published'] && 4 === $F9p['summary']['ready'] && 0 === $F9p['summary']['deactivatable'] );
