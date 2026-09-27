<?php
/**
 * Faz 12b — SSS (mb_sss) içe aktarımı ve gerçek logolu referanslar (mb_referans + attachment).
 *
 * Yalnız AÇIKÇA SAHTE veri (`zz-test-soru-*`, `referans-01..03`, sahte PNG baytları; tests/fixtures/apply-fixture.php).
 * Saf PHP + bellek içi sahte WordPress dünyası (tests/support/import-apply-fakes.php); gerçek WordPress davranışı
 * (attachment oluşturma, dosya özeti geri okuma, uploads) tools/runtime-test/scripts/apply-cycle-test-3.php ile ayrıca sınanır.
 */

$F12B_RV = 'MaviBelge_Core_Import_Record_Validator';
$F12B_MF = 'MaviBelge_Core_Import_Managed_Fields';
$F12B_PL = 'MaviBelge_Core_Import_Dry_Run_Planner';
$F12B_AP = 'MaviBelge_Core_Import_Apply_Plan';
$F12B_WP = 'MaviBelge_Core_Import_Write_Payload';
$F12B_ML = 'MaviBelge_Core_Import_Manifest_Loader';
$F12B_RP = 'MaviBelge_Core_Import_WordPress_Target_Repository';
$F12B_V  = 'MaviBelge_Core_Validator';

$f12b_env   = mb_content_fixture_envelopes( array(), 3 );
$f12b_faqs  = $f12b_env['faq']['records'];
$f12b_refs  = $f12b_env['reference']['records'];
$f12b_news  = $f12b_env['news']['records'];
$f12b_fx    = $F12B_MF::project_faq( $f12b_faqs[0], array() )['fields'];
$f12b_h     = MaviBelge_Core_Import_Hash::hash( $f12b_fx );

/* --- A) source_key ailesi, doğal anahtar, alan listesi --- */
mb_test( 'Faz 12b source_key: faq:<slug> yalnız kendi ailesinde geçerli; başka aileler/biçim hataları reddedilir',
	$F12B_V::IMPORT_KEY_VALID === $F12B_V::classify_import_source_key( 'faq:zz-test-soru-a', 'faq' ) && $F12B_V::IMPORT_KEY_WRONG_PREFIX === $F12B_V::classify_import_source_key( 'news:zz-test-soru-a', 'faq' ) && $F12B_V::IMPORT_KEY_WRONG_PREFIX === $F12B_V::classify_import_source_key( 'faq:zz-test-soru-a', 'news' )
	&& $F12B_V::IMPORT_KEY_MALFORMED === $F12B_V::classify_import_source_key( 'faq:A', 'faq' ) && $F12B_V::IMPORT_KEY_MALFORMED === $F12B_V::classify_import_source_key( 'faq:', 'faq' ) && $F12B_V::IMPORT_KEY_MALFORMED === $F12B_V::classify_import_source_key( "faq:a\n", 'faq' ) && $F12B_V::IMPORT_KEY_EMPTY === $F12B_V::classify_import_source_key( '', 'faq' ) );
mb_test( 'Faz 12b doğal anahtar: faq source_key -> {slug}; yanlış aile null; marker durumları tek sınıflandırıcıdan',
	array( 'slug' => 'zz-test-soru-a' ) === $F12B_RP::natural_key_from_source_key( 'faq', 'faq:zz-test-soru-a' ) && null === $F12B_RP::natural_key_from_source_key( 'faq', 'news:zz-test-soru-a' ) && 'undiscovered_marker' === $F12B_RP::natural_key_state_from_marker( 'faq:a', 'faq', 'faq:a' ) && 'foreign_marker' === $F12B_RP::natural_key_state_from_marker( 'faq:b', 'faq', 'faq:a' ) && 'unmanaged' === $F12B_RP::natural_key_state_from_marker( '', 'faq', 'faq:a' ) );
mb_test( 'Faz 12b meta şeması: mb_sss marker + hash sistem yönetimli ve salt-okunur; alan doğrulaması faq ailesi',
	(function () {
		$f = MaviBelge_Core_Meta_Schema::get_fields_for( 'mb_sss' );
		foreach ( array( '_mb_import_source_key', '_mb_last_applied_hash' ) as $k ) {
			if ( ! isset( $f[ $k ] ) || empty( $f[ $k ]['readonly'] ) || empty( $f[ $k ]['system_managed'] ) ) {
				return false;
			}
		}
		return 'import_source_key_faq' === $f['_mb_import_source_key']['format'];
	})() );
mb_test( 'Faz 12b yönetilen alanlar: FAQ_FIELDS tam ve sıralı; TYPES faq içerir; REFERENCE_FIELDS logo_sha256 taşır (attachment kimliği YOK)',
	array( 'slug', 'title', 'content', 'sort_order', 'record_status' ) === $F12B_MF::FAQ_FIELDS && in_array( 'faq', $F12B_MF::TYPES, true ) && $F12B_MF::FAQ_FIELDS === $F12B_MF::fields_for( 'faq' ) && in_array( 'logo_sha256', $F12B_MF::REFERENCE_FIELDS, true ) && ! in_array( 'logo_attachment_id', $F12B_MF::REFERENCE_FIELDS, true ) );

/* --- B) SSS kayıt doğrulayıcı --- */
$f12b_vq = function ( array $patch, $drop = array() ) use ( $F12B_RV, $f12b_faqs ) {
	$r = array_merge( $f12b_faqs[0], $patch );
	foreach ( $drop as $k ) {
		unset( $r[ $k ] );
	}
	return $F12B_RV::validate_faq( $r )['valid'];
};
mb_test( 'Faz 12b doğrulayıcı (SSS): geçerli sahte kayıtlar kabul; dizi olmayan, fazla/eksik anahtar, yanlış tip -> ret',
	true === $F12B_RV::validate_faq( $f12b_faqs[0] )['valid'] && true === $F12B_RV::validate_faq( $f12b_faqs[2] )['valid'] && false === $F12B_RV::validate_faq( null )['valid'] && false === $f12b_vq( array( 'ek' => 1 ) ) && false === $f12b_vq( array(), array( 'question' ) ) && false === $f12b_vq( array(), array( 'answer' ) ) && false === $f12b_vq( array(), array( 'source' ) )
	&& false === $f12b_vq( array( 'question' => 5 ) ) && false === $f12b_vq( array( 'answer' => null ) ) && false === $f12b_vq( array( 'source_index' => '0' ) ) );
mb_test( 'Faz 12b doğrulayıcı (SSS): slug = slugify(question); source_key = "faq:"+slug; işaretleme/kontrol karakteri/baş-son boşluk/boş metin reddedilir',
	false === $f12b_vq( array( 'slug' => 'baska', 'source_key' => 'faq:baska' ) ) && false === $f12b_vq( array( 'source_key' => 'faq:baska' ) ) && false === $f12b_vq( array( 'source_key' => 'news:zz-test-soru-a' ) ) && false === $f12b_vq( array( 'question' => 'ZZ <b>Soru</b>?' ) ) && false === $f12b_vq( array( 'answer' => 'Cevap <script>x</script>' ) )
	&& false === $f12b_vq( array( 'answer' => " Cevap" ) ) && false === $f12b_vq( array( 'answer' => "Cevap\x07" ) ) && false === $f12b_vq( array( 'answer' => '' ) ) && false === $f12b_vq( array( 'source_index' => -1 ) ) );
mb_test( 'Faz 12b doğrulayıcı (SSS): Türkçe sorunun sluğu Faz 6A slugify ile birebir (yanlış slug reddedilir)',
	true === $F12B_RV::validate_faq( array_merge( $f12b_faqs[0], array( 'question' => 'İtiraz veya şikayetimi nasıl iletirim?', 'slug' => 'itiraz-veya-sikayetimi-nasil-iletirim', 'source_key' => 'faq:itiraz-veya-sikayetimi-nasil-iletirim' ) ) )['valid']
	&& false === $F12B_RV::validate_faq( array_merge( $f12b_faqs[0], array( 'question' => 'İtiraz veya şikayetimi nasıl iletirim?', 'slug' => 'itiraz-veya-ikayetimi-nasil-iletirim', 'source_key' => 'faq:itiraz-veya-ikayetimi-nasil-iletirim' ) ) )['valid'] );
mb_test( 'Faz 12b doğrulayıcı (referans): logo alanları kapalı şekil; alt metin anlamlı; firma adı tahmin edilmez',
	true === $F12B_RV::validate_reference( $f12b_refs[0] )['valid'] && false === $F12B_RV::validate_reference( array_merge( $f12b_refs[0], array( 'name_status' => 'verified' ) ) )['valid'] && false === $F12B_RV::validate_reference( array_merge( $f12b_refs[0], array( 'logo_file' => 'tanitim-site/assets/images/references/atlas-endustri.svg' ) ) )['valid']
	&& false === $F12B_RV::validate_reference( array_merge( $f12b_refs[0], array( 'logo_sha256' => 'abc' ) ) )['valid'] && false === $F12B_RV::validate_reference( array_merge( $f12b_refs[0], array( 'alt' => '' ) ) )['valid'] );

/* --- C) yazma yükü --- */
$f12b_pq = $F12B_WP::prepare( 'faq', $f12b_fx, 'faq:zz-test-soru-a', $f12b_h );
mb_test( 'Faz 12b yük: faq payload (post_name/başlık=soru/gövde=cevap; sıra + aktif; terim YOK); marker + hash; yalnız MANAGED_POST_META anahtarları',
	true === $f12b_pq['ok'] && array( 'post_type' => 'mb_sss', 'post_title' => 'ZZ Test Soru A?', 'post_name' => 'zz-test-soru-a', 'post_content' => 'Sahte cevap A (yalnız yerel fixture).' ) === $f12b_pq['payload']['post'] && ! isset( $f12b_pq['payload']['terms'] ) && ! isset( $f12b_pq['payload']['logo'] )
	&& array( '_mb_sort_order' => 1, '_mb_record_status' => 'active', '_mb_import_source_key' => 'faq:zz-test-soru-a', '_mb_last_applied_hash' => $f12b_h ) === $f12b_pq['payload']['post_meta']
	&& array( '_mb_sort_order', '_mb_record_status', '_mb_import_source_key', '_mb_last_applied_hash' ) === $F12B_WP::MANAGED_POST_META['faq'] && 'mb_sss' === $F12B_WP::POST_TYPES['faq'] );
$f12b_forced = function ( array $fields, $sk = 'faq:zz-test-soru-a' ) use ( $F12B_WP ) {
	return $F12B_WP::prepare( 'faq', $fields, $sk, MaviBelge_Core_Import_Hash::hash( $fields ) );
};
mb_test( 'Faz 12b yük: pasif kayıt, işaretlemeli soru/cevap, yanlış marker ailesi, yanlış hash, fazla alan -> ret (kısmi yük YOK)',
	false === $f12b_forced( array_merge( $f12b_fx, array( 'record_status' => 'passive' ) ) )['ok'] && false === $f12b_forced( array_merge( $f12b_fx, array( 'content' => 'a <b>b</b>' ) ) )['ok'] && false === $f12b_forced( array_merge( $f12b_fx, array( 'title' => 'a > b' ) ) )['ok'] && false === $f12b_forced( $f12b_fx, 'news:zz-test-soru-a' )['ok']
	&& false === $F12B_WP::prepare( 'faq', $f12b_fx, 'faq:zz-test-soru-a', str_repeat( 'a', 64 ) )['ok'] && false === $f12b_forced( array_merge( $f12b_fx, array( 'x' => 1 ) ) )['ok'] && false === $f12b_forced( array_diff_key( $f12b_fx, array( 'content' => 1 ) ) )['ok'] );
$f12b_ref_fx = $F12B_MF::project_reference( $f12b_refs[0], array() )['fields'];
$f12b_pr     = $F12B_WP::prepare( 'reference', $f12b_ref_fx, 'reference:referans-01', MaviBelge_Core_Import_Hash::hash( $f12b_ref_fx ) );
mb_test( 'Faz 12b yük: referans payload attachment KİMLİĞİ taşımaz (yükte yalnız logo {sha256}); logo özeti geçersizse yük reddedilir',
	true === $f12b_pr['ok'] && ! array_key_exists( '_mb_logo_attachment_id', $f12b_pr['payload']['post_meta'] ) && array( 'sha256' => $f12b_refs[0]['logo_sha256'] ) === $f12b_pr['payload']['logo']
	&& false === $F12B_WP::prepare( 'reference', array_merge( $f12b_ref_fx, array( 'logo_sha256' => '' ) ), 'reference:referans-01', MaviBelge_Core_Import_Hash::hash( array_merge( $f12b_ref_fx, array( 'logo_sha256' => '' ) ) ) )['ok'] );

/* --- D) planlayıcı --- */
$f12b_lk = function ( $sourceKey, array $fields, $lastHash = null, $targetId = 41 ) {
	return array(
		$sourceKey => array(
			'target_found' => true, 'target_id' => $targetId, 'target_type_matches' => true, 'has_source_key_marker' => true,
			'last_applied_hash' => null === $lastHash ? MaviBelge_Core_Import_Hash::hash( $fields ) : $lastHash, 'current_managed_fields' => $fields,
		),
	);
};
$f12b_none = array( 'faq:zz-test-soru-a' => array( 'target_found' => false, 'natural_key' => 'none' ) );
$f12b_c    = $F12B_PL::plan_faq( $f12b_faqs[0], $f12b_none, mb_empty_dependencies() );
mb_test( 'Faz 12b plan (SSS): hedef yok + doğal anahtar none -> create (bağımlılık gerekmez); hash projeksiyon hash\'i; unchanged / sıra değişimi update / editör pasife aldı conflict / geçersiz kayıt invalid',
	'create' === $f12b_c['decision'] && 'faq' === $f12b_c['type'] && $f12b_h === $f12b_c['incoming_hash'] && 'unchanged' === $F12B_PL::plan_faq( $f12b_faqs[0], $f12b_lk( 'faq:zz-test-soru-a', $f12b_fx ), mb_empty_dependencies() )['decision']
	&& 'update' === $F12B_PL::plan_faq( $f12b_faqs[0], $f12b_lk( 'faq:zz-test-soru-a', array_merge( $f12b_fx, array( 'sort_order' => 9 ) ) ), mb_empty_dependencies() )['decision']
	&& 'conflict' === $F12B_PL::plan_faq( $f12b_faqs[0], $f12b_lk( 'faq:zz-test-soru-a', array_merge( $f12b_fx, array( 'content' => 'Editör değiştirdi' ) ), $f12b_h ), mb_empty_dependencies() )['decision']
	&& 'invalid' === $F12B_PL::plan_faq( array_merge( $f12b_faqs[0], array( 'question' => 'x' ) ), $f12b_none, mb_empty_dependencies() )['decision'] );
$f12b_full = $F12B_PL::plan( array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array(), 'news' => array(), 'references' => array(), 'faqs' => $f12b_faqs ), array(), array_merge( mb_empty_dependencies(), array( 'news_type_term_ids' => array() ) ) );
mb_test( 'Faz 12b plan: faqs listesi özetlenir; by_type faq sayılır (news/reference de anahtar); sum(by_type)=total',
	3 === $f12b_full['summary']['total'] && 3 === $f12b_full['summary']['by_type']['faq'] && array( 'sector', 'qualification', 'fee', 'news', 'reference', 'faq' ) === array_keys( $f12b_full['summary']['by_type'] ) && array_sum( $f12b_full['summary']['by_type'] ) === $f12b_full['summary']['total'] );
mb_test( 'Faz 12b plan: faqs ile diğer listeler ARASINDA tekrar eden source_key ve bozuk source_index -> plan hatası, entry yok',
	(function () use ( $F12B_PL, $f12b_faqs, $f12b_news ) {
		$dup    = $f12b_faqs;
		$dup[1] = array_merge( $f12b_faqs[0], array( 'source_index' => 1 ) );
		$bad    = $f12b_faqs;
		$bad[1]['source_index'] = 7;
		$cat    = array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array() );
		$a      = $F12B_PL::plan( array_merge( $cat, array( 'faqs' => $dup ) ), array(), mb_empty_dependencies() );
		$b      = $F12B_PL::plan( array_merge( $cat, array( 'faqs' => $bad ) ), array(), mb_empty_dependencies() );
		return array() === $a['entries'] && ! empty( $a['errors'] ) && array() === $b['entries'] && ! empty( $b['errors'] );
	})() );

/* --- E) aşama modeli --- */
mb_test( 'Faz 12b aşama: content aşaması news + reference + faq; faqs isteğe bağlı liste (yalnız content aşamasında); yazma sırası news < reference < faq < page; rollback tersi',
	array( 'news', 'reference', 'faq' ) === $F12B_AP::STAGE_TYPES['content'] && 'faqs' === $F12B_AP::TYPE_LISTS['faq'] && in_array( 'faqs', $F12B_AP::OPTIONAL_LISTS, true ) && 'content' === $F12B_AP::OPTIONAL_LIST_STAGE['faqs']
	&& (function () use ( $F12B_AP ) {
		$w  = array( array( 'type' => 'faq', 'source_key' => 'f' ), array( 'type' => 'reference', 'source_key' => 'r' ), array( 'type' => 'news', 'source_key' => 'n' ), array( 'type' => 'page', 'source_key' => 'p' ) );
		$o  = array_map( function ( $x ) {
			return $x['source_key'];
		}, $F12B_AP::order_writes( $w ) );
		$rb = array_map( function ( $x ) {
			return $x['type'];
		}, $F12B_AP::rollback_order( array( array( 'type' => 'news', 'seq' => 1 ), array( 'type' => 'faq', 'seq' => 2 ), array( 'type' => 'reference', 'seq' => 3 ) ) ) );
		return array( 'n', 'r', 'f', 'p' ) === $o && array( 'faq', 'reference', 'news' ) === $rb;
	})() );

/* --- F) manifest yükleyici: faqs + logo doğrulaması (fail-closed) --- */
$f12b_dir = mb6b2_temp_dir( 'f12bload' );
mb_content_fixture_write_dir( $f12b_dir, mb_content_fixture_envelopes( array(), 3 ) );
$f12b_lc = $F12B_ML::load_content( $f12b_dir );
mb_test( 'Faz 12b yükleyici: load_content news + references + faqs (sırasıyla, olduğu gibi); faqs.manifest.json sabit dosya adı; logo dizini çözülür',
	true === $f12b_lc['ok'] && array( 'news', 'references', 'faqs' ) === array_keys( $f12b_lc['manifest'] ) && $f12b_faqs === $f12b_lc['manifest']['faqs'] && $f12b_refs === $f12b_lc['manifest']['references'] && 'faqs.manifest.json' === $F12B_ML::CONTENT_FILES['faq'] && null !== $F12B_ML::logo_dir( $f12b_dir ) );
$f12b_logo_case = function ( callable $mutateFiles, callable $mutateEnv = null ) use ( $F12B_ML ) {
	$dir = mb6b2_temp_dir( 'f12blogo' );
	$env = mb_content_fixture_envelopes( array(), 3 );
	if ( null !== $mutateEnv ) {
		$env = $mutateEnv( $env );
	}
	mb_content_fixture_write_dir( $dir, $env );
	$mutateFiles( dirname( $dir ) . '/sources/reference-logos' );
	$r = $F12B_ML::load_content( $dir );
	mb_fixture_write_logos( $dir ); // sonraki senaryolar için sağlam kopyayı geri yaz
	return $r;
};
mb_test( 'Faz 12b yükleyici (fail-closed): logo dosyası YOKSA tüm içerik manifesti reddedilir; hata metni mutlak yol içermez',
	(function () use ( $f12b_logo_case ) {
		$r = $f12b_logo_case( function ( $d ) {
			unlink( $d . '/ref-02.png' );
		} );
		return false === $r['ok'] && null === $r['manifest'] && false === strpos( json_encode( $r['errors'] ), sys_get_temp_dir() ) && false !== strpos( json_encode( $r['errors'] ), 'records[1]' );
	})() );
mb_test( 'Faz 12b yükleyici (fail-closed): logo baytı değiştirilmiş (SHA-256/bayt uyuşmuyor) -> ret',
	false === $f12b_logo_case( function ( $d ) {
		file_put_contents( $d . '/ref-01.png', mb_fixture_logo_bytes( 1 ) . 'x' );
	} )['ok'] );
mb_test( 'Faz 12b yükleyici (fail-closed): PNG olmayan içerik (SHA-256 kayıtla eşit olsa bile) -> ret',
	false === $f12b_logo_case(
		function ( $d ) {
			file_put_contents( $d . '/ref-01.png', 'PNG değil, düz metin' );
		},
		function ( $env ) {
			$env['reference']['records'][0]['logo_sha256'] = hash( 'sha256', 'PNG değil, düz metin' );
			$env['reference']['records'][0]['logo_bytes']  = strlen( 'PNG değil, düz metin' );
			return $env;
		}
	)['ok'] );
mb_test( 'Faz 12b yükleyici (fail-closed): kayıttaki logo boyutu PNG başlığıyla uyuşmuyor; aynı logo iki kayıtta; logo_file kalıp dışı -> ret',
	false === $f12b_logo_case( function ( $d ) {
	}, function ( $env ) {
		$env['reference']['records'][0]['logo_width'] = 99;
		return $env;
	} )['ok'] && false === $f12b_logo_case( function ( $d ) {
		copy( $d . '/ref-01.png', $d . '/ref-02.png' );
	}, function ( $env ) {
		$env['reference']['records'][1]['logo_sha256'] = $env['reference']['records'][0]['logo_sha256'];
		$env['reference']['records'][1]['logo_bytes']  = $env['reference']['records'][0]['logo_bytes'];
		return $env;
	} )['ok'] && false === $f12b_logo_case( function ( $d ) {
	}, function ( $env ) {
		$env['reference']['records'][0]['logo_file'] = '../ref-01.png';
		return $env;
	} )['ok'] );
mb_test( 'Faz 12b yükleyici: faqs dosyası yoksa load_content TÜMÜNÜ reddeder (kısmi içerik manifesti yok); katalog etkilenmez',
	(function () use ( $F12B_ML ) {
		$dir = mb6b2_temp_dir( 'f12bnofaq' );
		$env = mb_content_fixture_envelopes( array(), 3 );
		unset( $env['faq'] );
		mb_content_fixture_write_dir( $dir, $env );
		return false === $F12B_ML::load_content( $dir )['ok'];
	})() );
mb_test( 'Faz 12b yükleyici: zarf source.file kalıpları — referans envanter dosyası, sss.html; haber zarfı SSS dosyasını gösteremez',
	$F12B_ML::EXPECTED_SOURCE_FILE['reference'] === 'wordpress-site/data/sources/reference-logos/reference-logos.manifest.json' && $F12B_ML::EXPECTED_SOURCE_FILE['faq'] === 'tanitim-site/sss.html'
	&& (function () use ( $F12B_ML ) {
		$dir = mb6b2_temp_dir( 'f12bsrc' );
		$env = mb_content_fixture_envelopes( array(), 3 );
		$env['faq']['source']['file'] = 'tanitim-site/assets/data/news.js';
		mb_content_fixture_write_dir( $dir, $env );
		return false === $F12B_ML::load_content( $dir )['ok'];
	})() );

/* --- G) sahte dünyada uçtan uca: dry-run, apply, readback, idempotency, rollback, reapply --- */
function f12b_env( $dir ) {
	$env = mb_fake_apply_env( $dir );
	$env->world->terms[501] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'haber', 'name' => 'Haber', 'description' => '', 'parent' => 0, 'meta' => array() );
	$env->world->terms[502] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'duyuru', 'name' => 'Duyuru', 'description' => '', 'parent' => 0, 'meta' => array() );
	return $env;
}
function f12b_apply( $env, $batch = null ) {
	$p = $env->apply->preview( 'content' );
	return $env->apply->apply( 'content', (string) $p['plan_digest'], $batch, 1 );
}
function f12b_of( $env, $postType ) {
	$out = array();
	foreach ( $env->world->posts as $id => $p ) {
		if ( $postType === $p['post_type'] ) {
			$out[ $id ] = $p;
		}
	}
	return $out;
}
$f12b_cdir = mb6b2_temp_dir( 'f12bcontent' );
mb_content_fixture_write_dir( $f12b_cdir, mb_content_fixture_envelopes( array(), 3 ) );
$E12a = f12b_env( $f12b_cdir );
$f12b_pv = $E12a->apply->preview( 'content' );
mb_test( 'Faz 12b dry-run: temiz dünyada content aşaması 9 create (3 haber + 3 referans + 3 SSS), applicable, eligible; by_type news/reference/faq; salt okunur (dünya dokunulmadı)',
	true === $f12b_pv['ok'] && 9 === $f12b_pv['summary']['operations']['create'] && 3 === $f12b_pv['summary']['by_type']['faq'] && 3 === $f12b_pv['summary']['by_type']['reference'] && true === $f12b_pv['summary']['applicable'] && true === $f12b_pv['eligible'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $f12b_pv['plan_digest'] ) && 0 === count( $E12a->world->posts ) && 0 === count( $E12a->world->logoAttachments ) && array() === $E12a->world->writeLog );
$f12b_run = f12b_apply( $E12a );
mb_test( 'Faz 12b apply: content aşaması completed; 9 item; tek batch; run stage=content',
	true === $f12b_run['ok'] && 'completed' === $f12b_run['status'] && 9 === count( $E12a->world->items ) && 1 === count( $E12a->world->runs ) && 'content' === $E12a->world->runs[1]['stage'] );
$f12b_sss = f12b_of( $E12a, 'mb_sss' );
$f12b_p0  = null;
foreach ( $f12b_sss as $p ) {
	if ( 'zz-test-soru-a' === $p['name'] ) {
		$f12b_p0 = $p;
	}
}
mb_test( 'Faz 12b apply: 3 SSS kaydı DRAFT olarak oluştu (asla publish); başlık=soru, gövde=cevap, post_name=slug, sıra 1/2/3, aktif; marker + hash plan hash\'ine eşit; terim/başka meta YOK',
	3 === count( $f12b_sss ) && null !== $f12b_p0 && 'draft' === $f12b_p0['status'] && 'ZZ Test Soru A?' === $f12b_p0['title'] && 'Sahte cevap A (yalnız yerel fixture).' === $f12b_p0['content'] && '1' === $f12b_p0['meta']['_mb_sort_order'] && 'active' === $f12b_p0['meta']['_mb_record_status']
	&& 'faq:zz-test-soru-a' === $f12b_p0['meta']['_mb_import_source_key'] && $f12b_h === $f12b_p0['meta']['_mb_last_applied_hash'] && array() === $f12b_p0['terms'] && 4 === count( $f12b_p0['meta'] ) && 0 === count( array_filter( $f12b_sss, function ( $p ) {
		return 'publish' === $p['status'];
	} ) ) );
$f12b_refs_w = f12b_of( $E12a, 'mb_referans' );
$f12b_ok_logo = true;
foreach ( $f12b_refs_w as $id => $p ) {
	$att = (int) $p['meta']['_mb_logo_attachment_id'];
	if ( $att <= 0 || ! isset( $E12a->world->logoAttachments[ $att ] ) || 'draft' !== $p['status'] || 'real' !== $p['meta']['_mb_reference_status'] ) {
		$f12b_ok_logo = false;
	}
}
mb_test( 'Faz 12b apply: 3 referans DRAFT, real/aktif; her birinin _mb_logo_attachment_id GERÇEK (pozitif) attachment kimliği ve içerik özeti manifestteki logo özetiyle eşit; 3 benzersiz attachment',
	3 === count( $f12b_refs_w ) && true === $f12b_ok_logo && 3 === count( $E12a->world->logoAttachments ) && 3 === count( array_unique( $E12a->world->logoAttachments ) ) && array_values( $E12a->world->logoAttachments ) === array( $f12b_refs[0]['logo_sha256'], $f12b_refs[1]['logo_sha256'], $f12b_refs[2]['logo_sha256'] ) );
mb_test( 'Faz 12b apply: yazma sırası news < reference (attachment referansla birlikte) < faq; audit started -> batch_committed -> completed; audit içerik/cevap taşımaz',
	(function () use ( $E12a ) {
		$posts = array_values( array_filter( $E12a->world->writeLog, function ( $l ) {
			return 0 === strpos( $l, 'create_post:' );
		} ) );
		return array( 'create_post:news:zz-test-haber-a', 'create_post:news:zz-test-haber-b', 'create_post:news:zz-test-haber-c', 'create_post:reference:referans-01', 'create_post:reference:referans-02', 'create_post:reference:referans-03', 'create_post:faq:zz-test-soru-a', 'create_post:faq:zz-test-soru-b', 'create_post:faq:zz-test-soru-c' ) === $posts
			&& array( 'import_run_started', 'import_batch_committed', 'import_run_completed' ) === array_map( function ( $a ) {
				return $a['event'];
			}, $E12a->world->audit ) && false === strpos( json_encode( $E12a->world->audit ), 'Sahte cevap' );
	})() );
$f12b_items = $E12a->store->get_items( $E12a->world->runs[1]['id'] );
mb_test( 'Faz 12b apply: her item için doğrulanmış rollback kaydı (create + 64-hex unmanaged_fingerprint); faq türü kayıtta',
	9 === count( $f12b_items ) && (function () use ( $f12b_items ) {
		$faq = 0;
		foreach ( $f12b_items as $row ) {
			$rec = MaviBelge_Core_Import_Rollback_Codec::decode( $row['rollback_record'] );
			if ( null === $rec || 'create' !== $rec['decision'] || 1 !== preg_match( '/^[0-9a-f]{64}\z/', (string) $rec['unmanaged_fingerprint'] ) ) {
				return false;
			}
			$faq += 'faq' === $rec['type'] ? 1 : 0;
		}
		return 3 === $faq;
	})() );
$f12b_writes_before = count( $E12a->world->writeLog );
$f12b_p2 = $E12a->apply->preview( 'content' );
$f12b_n2 = f12b_apply( $E12a );
mb_test( 'Faz 12b readback + idempotency: ikinci plan 9 unchanged (logo özeti gerçek attachment\'tan okunarak eşleşir), 0 yazma; ikinci apply noop (yeni run/item/audit/yazma/attachment YOK)',
	9 === $f12b_p2['summary']['operations']['unchanged'] && true === $f12b_p2['summary']['applicable'] && 0 === $f12b_p2['writes'] && 'noop' === $f12b_n2['status'] && 1 === count( $E12a->world->runs ) && 9 === count( $E12a->world->items ) && 3 === count( $E12a->world->audit ) && $f12b_writes_before === count( $E12a->world->writeLog ) && 3 === count( $E12a->world->logoAttachments ) );
// Editör bir SSS cevabını değiştirirse: conflict (üzerine yazılmaz); logo attachment'ı silinirse: conflict (sahte unchanged YOK).
foreach ( $E12a->world->posts as $id => $p ) {
	if ( 'zz-test-soru-b' === $p['name'] ) {
		$E12a->world->posts[ $id ]['content'] = 'Editör yeniden yazdı.';
	}
}
$f12b_conf = $E12a->apply->preview( 'content' );
mb_test( 'Faz 12b kullanıcı değişikliği: editör SSS cevabını değiştirdi -> conflict manual_edit_detected; aşama uygulanamaz; editör içeriği ezilmez',
	1 === $f12b_conf['summary']['operations']['conflict'] && false === $f12b_conf['summary']['applicable'] && 'plan_not_applicable' === f12b_apply( $E12a )['error_code'] );
foreach ( $E12a->world->posts as $id => $p ) {
	if ( 'zz-test-soru-b' === $p['name'] ) {
		$E12a->world->posts[ $id ]['content'] = 'Sahte cevap B (yalnız yerel fixture).';
	}
}
$f12b_saved_logos = $E12a->world->logoAttachments;
$E12a->world->logoAttachments = array();
$f12b_nolo = $E12a->apply->preview( 'content' );
mb_test( 'Faz 12b logo doğrulaması: bağlı logo attachment\'ı geçersizleşirse (silindi/dosya yok) referans unchanged SAYILMAZ (conflict; uygulanamaz) — geçerli ve erişilebilir logo olmadan içerik hazır değildir',
	3 === $f12b_nolo['summary']['operations']['conflict'] && 6 === $f12b_nolo['summary']['operations']['unchanged'] && false === $f12b_nolo['summary']['applicable'] );
$E12a->world->logoAttachments = $f12b_saved_logos;

// Rollback -> yeniden apply: kayıtlar çöpe gider, logo attachment'ları YENİDEN KULLANILIR (aynı logo tekrar eklenmez).
$f12b_uid = $E12a->world->runs[1]['uid'];
$f12b_rbp = $E12a->rollback->preview( $f12b_uid );
mb_test( 'Faz 12b rollback önizleme: 9 bekleyen item, engel yok, 64-hex digest', true === $f12b_rbp['ok'] && 9 === $f12b_rbp['items_pending'] && array() === $f12b_rbp['blockers'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $f12b_rbp['rollback_digest'] ) );
$f12b_rb = $E12a->rollback->rollback( $f12b_uid, (string) $f12b_rbp['rollback_digest'], null );
mb_test( 'Faz 12b rollback: rolled_back, 9 item; SSS/referans/haber ÇÖPE gider (kalıcı silme yok); marker/hash/yönetilen meta temizlenir; ters sıra faq -> reference -> news',
	true === $f12b_rb['ok'] && 'rolled_back' === $f12b_rb['status'] && 9 === count( $E12a->world->posts ) && 0 === count( array_filter( $E12a->world->posts, function ( $p ) {
		return 'trash' !== $p['status'];
	} ) ) && (function () use ( $E12a ) {
		foreach ( $E12a->world->posts as $p ) {
			if ( isset( $p['meta']['_mb_import_source_key'] ) || isset( $p['meta']['_mb_last_applied_hash'] ) || '' !== $p['name'] ) {
				return false;
			}
		}
		return true;
	})() );
$f12b_p3 = $E12a->apply->preview( 'content' );
mb_test( 'Faz 12b rollback sonrası: aynı manifest yeniden 9 create (doğal anahtar none, conflict=0), applicable',
	9 === $f12b_p3['summary']['operations']['create'] && 0 === $f12b_p3['summary']['operations']['conflict'] && true === $f12b_p3['summary']['applicable'] );
$f12b_run2 = f12b_apply( $E12a );
mb_test( 'Faz 12b reapply: yeniden completed; 9 YENİ post (draft); logo attachment sayısı DEĞİŞMEDİ (3; yeniden kullanıldı); plan yeniden 9 unchanged',
	true === $f12b_run2['ok'] && 'completed' === $f12b_run2['status'] && 18 === count( $E12a->world->posts ) && 3 === count( $E12a->world->logoAttachments ) && 9 === $E12a->apply->preview( 'content' )['summary']['operations']['unchanged'] );

// Editör SSS'i YAYINLARSA: plan hâlâ unchanged (durum yönetilmez) ama rollback drift nedeniyle ENGELLENİR (yayındaki içerik korunur).
foreach ( $E12a->world->posts as $id => $p ) {
	if ( 'mb_sss' === $p['post_type'] && 'trash' !== $p['status'] ) {
		$E12a->world->posts[ $id ]['status'] = 'publish';
		break;
	}
}
$f12b_uid2 = $E12a->world->runs[2]['uid'];
$f12b_rbp2 = $E12a->rollback->preview( $f12b_uid2 );
mb_test( 'Faz 12b drift: editör bir SSS kaydını yayınladı -> plan unchanged (sahte conflict yok) AMA rollback engellenir (drift_detected); yayındaki kayıt çöpe gitmez',
	9 === $E12a->apply->preview( 'content' )['summary']['operations']['unchanged'] && 1 === count( $f12b_rbp2['blockers'] ) && 'drift_detected' === $f12b_rbp2['blockers'][0]['code'] );

/* --- H) atomiklik: audit / yazma / logo hatası --- */
$E12b = f12b_env( $f12b_cdir );
$E12b->audit->failEvents = array( 'import_batch_committed' );
$f12b_fa = f12b_apply( $E12b );
mb_test( 'Faz 12b audit atomikliği: batch audit\'i yazılamazsa batch GERİ ALINIR; hiçbir SSS/referans/attachment kalmaz; run failed',
	false === $f12b_fa['ok'] && 0 === count( f12b_of( $E12b, 'mb_sss' ) ) && 0 === count( f12b_of( $E12b, 'mb_referans' ) ) && 0 === count( $E12b->world->logoAttachments ) );
$E12c = f12b_env( $f12b_cdir );
$E12c->world->faults = array( array( 'op' => 'logo_fail', 'source_key' => 'reference:referans-02' ) );
$f12b_fl = f12b_apply( $E12c );
mb_test( 'Faz 12b logo hatası: logo kaynağı bulunamazsa/oluşturulamazsa batch TAMAMEN geri alınır (yarım referans/attachment YOK), run rollback_required/failed',
	false === $f12b_fl['ok'] && 0 === count( f12b_of( $E12c, 'mb_referans' ) ) && 0 === count( $E12c->world->logoAttachments ) && 0 === count( f12b_of( $E12c, 'mb_sss' ) ) );
$E12d = f12b_env( $f12b_cdir );
$E12d->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'faq:zz-test-soru-b' ) );
$f12b_fw = f12b_apply( $E12d );
mb_test( 'Faz 12b yazma atomikliği: SSS yazımı hata verirse aynı batch geri alınır; yarım SSS/logo yok',
	false === $f12b_fw['ok'] && 0 === count( f12b_of( $E12d, 'mb_sss' ) ) && 0 === count( $E12d->world->logoAttachments ) );
// Batch=2: 9 kayıt 5 batch'te tamamlanır; SSS hatası son batch'i geri alır, önceki batchler korunur, yarım run yalnız commit edilenleri geri alır.
$E12e = f12b_env( $f12b_cdir );
$f12b_b1 = f12b_apply( $E12e, 2 );
$f12b_r1 = $E12e->store->get_run( $f12b_b1['run_uid'] );
mb_test( 'Faz 12b batch: batch=2 ile 9 kayıt 5 batch içinde completed; 3 logo attachment; readback 9 unchanged',
	true === $f12b_b1['ok'] && 'completed' === $f12b_b1['status'] && 5 === $f12b_r1['committed_batches'] && 9 === $f12b_r1['committed_items'] && 3 === count( $E12e->world->logoAttachments ) && 9 === $E12e->apply->preview( 'content' )['summary']['operations']['unchanged'] );
$E12g = f12b_env( $f12b_cdir );
$E12g->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'faq:zz-test-soru-c' ) );
$f12b_g = f12b_apply( $E12g, 2 );
$f12b_gr = $E12g->store->get_run( $f12b_g['run_uid'] );
mb_test( 'Faz 12b batch atomikliği: son SSS yazımı hata verirse yalnız o batch geri alınır (yarım SSS yok), önceki batchler korunur, run rollback_required; yarım run yalnız commit edilen itemlar için geri alınır',
	false === $f12b_g['ok'] && 'rollback_required' === $f12b_gr['status'] && 4 === $f12b_gr['committed_batches'] && 8 === $f12b_gr['committed_items'] && 2 === count( f12b_of( $E12g, 'mb_sss' ) ) && (function () use ( $E12g, $f12b_g ) {
		$pv = $E12g->rollback->preview( $f12b_g['run_uid'] );
		$rb = $E12g->rollback->rollback( $f12b_g['run_uid'], (string) $pv['rollback_digest'], null );
		return true === $rb['ok'] && 8 === $rb['rolled_back_items'] && 0 === count( array_filter( $E12g->world->posts, function ( $p ) {
			return 'trash' !== $p['status'];
		} ) );
	})() );

/* --- I) SSS yoksa (boş liste) geriye dönük davranış: 6 kayıt; faqs anahtarı loader'da boş liste --- */
$f12b_dir0 = mb6b2_temp_dir( 'f12b0' );
mb_content_fixture_write_dir( $f12b_dir0, mb_content_fixture_envelopes( array(), 0 ) );
$E12f = f12b_env( $f12b_dir0 );
mb_test( 'Faz 12b: SSS listesi boşsa content aşaması yalnız haber + referans (6 create); by_type faq 0',
	6 === $E12f->apply->preview( 'content' )['summary']['operations']['create'] && 0 === $E12f->apply->preview( 'content' )['summary']['by_type']['faq'] );
