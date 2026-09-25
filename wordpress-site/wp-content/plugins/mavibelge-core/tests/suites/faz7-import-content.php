<?php
/**
 * Faz 7 — içerik aktarımı: `news` (mb_haber) ve `reference` (mb_referans) import türleri.
 *
 * Yalnız AÇIKÇA SAHTE veri (`zz-test-haber-*`, `zz-test-ref-*`; tests/fixtures/apply-fixture.php).
 * Saf PHP + bellek içi sahte WordPress dünyası (tests/support/import-apply-fakes.php);
 * gerçek WordPress davranışı tools/runtime-test/scripts/apply-cycle-test-3.php ile ayrıca sınanır.
 */

$F7_MF = 'MaviBelge_Core_Import_Managed_Fields';
$F7_RV = 'MaviBelge_Core_Import_Record_Validator';
$F7_PL = 'MaviBelge_Core_Import_Dry_Run_Planner';
$F7_AP = 'MaviBelge_Core_Import_Apply_Plan';
$F7_WP = 'MaviBelge_Core_Import_Write_Payload';
$F7_AE = 'MaviBelge_Core_Import_Apply_Eligibility';
$F7_RP = 'MaviBelge_Core_Import_WordPress_Target_Repository';
$F7_V  = 'MaviBelge_Core_Validator';

$f7_env      = mb_content_fixture_envelopes();
$f7_news     = $f7_env['news']['records'];
$f7_refs     = $f7_env['reference']['records'];
$f7_deps     = array_merge(
	mb_empty_dependencies(),
	array( 'news_type_term_ids' => array( 'haber' => array( 'id' => 501, 'type_verified' => true ), 'duyuru' => array( 'id' => 502, 'type_verified' => true ) ) )
);
$f7_news_fx  = $F7_MF::project_news( $f7_news[0], array( 'news_type_term_id' => 501 ) )['fields'];
$f7_ref_fx   = $F7_MF::project_reference( $f7_refs[0], array() )['fields'];
$f7_news_h   = MaviBelge_Core_Import_Hash::hash( $f7_news_fx );
$f7_ref_h    = MaviBelge_Core_Import_Hash::hash( $f7_ref_fx );

/* --- A) source_key sınıflandırıcı (tek kanonik) --- */
mb_test( 'Faz 7 source_key: news:<slug> ve reference:<slug> yalnız kendi ailesinde geçerli',
	$F7_V::IMPORT_KEY_VALID === $F7_V::classify_import_source_key( 'news:zz-test-haber-a', 'news' ) && $F7_V::IMPORT_KEY_VALID === $F7_V::classify_import_source_key( 'reference:zz-test-ref-a', 'reference' )
	&& $F7_V::IMPORT_KEY_WRONG_PREFIX === $F7_V::classify_import_source_key( 'news:zz-test-haber-a', 'reference' ) && $F7_V::IMPORT_KEY_WRONG_PREFIX === $F7_V::classify_import_source_key( 'reference:zz-test-ref-a', 'news' )
	&& $F7_V::IMPORT_KEY_WRONG_PREFIX === $F7_V::classify_import_source_key( 'sector:zz-test-a', 'news' ) && $F7_V::IMPORT_KEY_WRONG_PREFIX === $F7_V::classify_import_source_key( 'news:zz-test-haber-a', 'sector' ) );
mb_test( 'Faz 7 source_key: büyük harf, çift tire, uç tire, iki nokta, boş slug, boşluk, satır sonu, 200+ karakter -> malformed',
	$F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'news:Zz', 'news' ) && $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'news:a--b', 'news' )
	&& $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'news:-a', 'news' ) && $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'news:a-', 'news' ) && $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'news:a:b', 'news' )
	&& $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'reference:', 'reference' ) && $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'reference:a b', 'reference' )
	&& $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( "news:abc\n", 'news' ) && $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'news:' . str_repeat( 'a', 200 ), 'news' ) );
mb_test( 'Faz 7 source_key: boş/null -> empty, dizi/int -> not_string; bilinmeyen tür (location/document/faq) reddedilir',
	$F7_V::IMPORT_KEY_EMPTY === $F7_V::classify_import_source_key( '', 'news' ) && $F7_V::IMPORT_KEY_EMPTY === $F7_V::classify_import_source_key( null, 'reference' ) && $F7_V::IMPORT_KEY_NOT_STRING === $F7_V::classify_import_source_key( array( 'news:a' ), 'news' )
	&& $F7_V::IMPORT_KEY_NOT_STRING === $F7_V::classify_import_source_key( 5, 'reference' ) && $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'location:a', 'location' ) && $F7_V::IMPORT_KEY_MALFORMED === $F7_V::classify_import_source_key( 'faq:a', 'faq' ) );

/* --- B) doğal anahtar ayrıştırma (SAF) --- */
mb_test( 'Faz 7 doğal anahtar: news/reference source_key -> {slug}; geçersiz/yanlış aile -> null',
	array( 'slug' => 'zz-test-haber-a' ) === $F7_RP::natural_key_from_source_key( 'news', 'news:zz-test-haber-a' ) && array( 'slug' => 'zz-test-ref-a' ) === $F7_RP::natural_key_from_source_key( 'reference', 'reference:zz-test-ref-a' )
	&& null === $F7_RP::natural_key_from_source_key( 'news', 'reference:zz-test-ref-a' ) && null === $F7_RP::natural_key_from_source_key( 'reference', 'news:x' ) && null === $F7_RP::natural_key_from_source_key( 'news', 'news:A' ) && null === $F7_RP::natural_key_from_source_key( 'location', 'location:x' ) );
mb_test( 'Faz 7 doğal anahtar durumu: marker sınıfları news/reference için de tek sınıflandırıcıdan',
	'unmanaged' === $F7_RP::natural_key_state_from_marker( '', 'news', 'news:a' ) && 'wrong_marker_prefix' === $F7_RP::natural_key_state_from_marker( 'reference:a', 'news', 'news:a' ) && 'foreign_marker' === $F7_RP::natural_key_state_from_marker( 'news:b', 'news', 'news:a' )
	&& 'undiscovered_marker' === $F7_RP::natural_key_state_from_marker( 'reference:a', 'reference', 'reference:a' ) && 'corrupt_marker' === $F7_RP::natural_key_state_from_marker( array( 'news:a' ), 'news', 'news:a' ) && 'corrupt_marker' === $F7_RP::natural_key_state_from_marker( 'news:A', 'news', 'news:a' ) );

/* --- C) kayıt doğrulayıcıları --- */
$f7_vn = function ( array $patch, $drop = array() ) use ( $F7_RV, $f7_news ) {
	$r = array_merge( $f7_news[0], $patch );
	foreach ( $drop as $k ) {
		unset( $r[ $k ] );
	}
	return $F7_RV::validate_news( $r )['valid'];
};
$f7_vr = function ( array $patch, $drop = array() ) use ( $F7_RV, $f7_refs ) {
	$r = array_merge( $f7_refs[0], $patch );
	foreach ( $drop as $k ) {
		unset( $r[ $k ] );
	}
	return $F7_RV::validate_reference( $r )['valid'];
};
mb_test( 'Faz 7 doğrulayıcı (haber): geçerli sahte kayıtların hepsi kabul', true === $F7_RV::validate_news( $f7_news[0] )['valid'] && true === $F7_RV::validate_news( $f7_news[1] )['valid'] && true === $F7_RV::validate_news( $f7_news[2] )['valid'] );
mb_test( 'Faz 7 doğrulayıcı (haber): dizi olmayan, fazla anahtar, eksik anahtar (her biri) -> ret',
	false === $F7_RV::validate_news( 'x' )['valid'] && false === $f7_vn( array( 'location' => 'x' ) ) && false === $f7_vn( array(), array( 'slug' ) ) && false === $f7_vn( array(), array( 'title' ) ) && false === $f7_vn( array(), array( 'published_on' ) )
	&& false === $f7_vn( array(), array( 'news_type' ) ) && false === $f7_vn( array(), array( 'summary' ) ) && false === $f7_vn( array(), array( 'body' ) ) && false === $f7_vn( array(), array( 'source' ) ) && false === $f7_vn( array(), array( 'source_index' ) ) && false === $f7_vn( array(), array( 'schema_version' ) ) );
mb_test( 'Faz 7 doğrulayıcı (haber): yanlış tip (int başlık, dizi gövde, string source_index, float) -> ret',
	false === $f7_vn( array( 'title' => 5 ) ) && false === $f7_vn( array( 'body' => array( 'x' ) ) ) && false === $f7_vn( array( 'source_index' => '0' ) ) && false === $f7_vn( array( 'source_index' => 0.0 ) ) && false === $f7_vn( array( 'summary' => null ) ) && false === $f7_vn( array( 'published_on' => 20260115 ) ) );
mb_test( 'Faz 7 doğrulayıcı (haber): slug/source_key biçimi ve tutarlılığı (source_key = "news:"+slug)',
	false === $f7_vn( array( 'slug' => 'Zz Test' ) ) && false === $f7_vn( array( 'slug' => '' ) ) && false === $f7_vn( array( 'slug' => 'a--b', 'source_key' => 'news:a--b' ) ) && false === $f7_vn( array( 'source_key' => 'news:baska-slug' ) )
	&& false === $f7_vn( array( 'source_key' => 'reference:zz-test-haber-a' ) ) && false === $f7_vn( array( 'source_key' => 'sector:zz-test-haber-a' ) ) && false === $f7_vn( array( 'source_index' => -1 ) ) && false === $f7_vn( array( 'schema_version' => '1.0.0' ) ) );
mb_test( 'Faz 7 doğrulayıcı (haber): tarih YYYY-MM-DD ve takvimde var olmalı; tür yalnız haber|duyuru',
	false === $f7_vn( array( 'published_on' => '' ) ) && false === $f7_vn( array( 'published_on' => '2026-02-30' ) ) && false === $f7_vn( array( 'published_on' => '26-01-01' ) ) && false === $f7_vn( array( 'published_on' => '2026-1-5' ) ) && false === $f7_vn( array( 'published_on' => '2026-01-15 10:00:00' ) )
	&& true === $f7_vn( array( 'published_on' => '2024-02-29' ) ) && false === $f7_vn( array( 'published_on' => '2025-02-29' ) )
	&& false === $f7_vn( array( 'news_type' => 'Haber' ) ) && false === $f7_vn( array( 'news_type' => 'etkinlik' ) ) && false === $f7_vn( array( 'news_type' => '' ) ) && false === $f7_vn( array( 'news_type' => array( 'haber' ) ) ) && true === $f7_vn( array( 'news_type' => 'duyuru' ) ) );
mb_test( 'Faz 7 doğrulayıcı (haber): başlık/özet/gövde boş olamaz, baş/son boşluk taşıyamaz, işaretleme karakteri (<, >) ve kontrol karakteri taşıyamaz',
	false === $f7_vn( array( 'title' => '' ) ) && false === $f7_vn( array( 'title' => '  ' ) ) && false === $f7_vn( array( 'summary' => '' ) ) && false === $f7_vn( array( 'body' => '' ) ) && false === $f7_vn( array( 'body' => ' gövde ' ) ) && false === $f7_vn( array( 'title' => ' Başlık' ) )
	&& false === $f7_vn( array( 'body' => 'Gövde <script>x</script>' ) ) && false === $f7_vn( array( 'title' => 'A > B' ) ) && false === $f7_vn( array( 'summary' => "Özet\x00" ) ) && false === $f7_vn( array( 'body' => "Gövde\x07" ) ) && true === $f7_vn( array( 'body' => "Satır 1\nSatır 2" ) ) );
mb_test( 'Faz 7 doğrulayıcı (haber): source.file/sha256 kapalı şekil, mutlak yol ve ".." reddedilir',
	false === $f7_vn( array( 'source' => array( 'file' => '/etc/x', 'sha256' => str_repeat( 'a', 64 ) ) ) ) && false === $f7_vn( array( 'source' => array( 'file' => 'a/../b.js', 'sha256' => str_repeat( 'a', 64 ) ) ) ) && false === $f7_vn( array( 'source' => array( 'file' => 'a.js', 'sha256' => 'xyz' ) ) )
	&& false === $f7_vn( array( 'source' => array( 'file' => 'a.js', 'sha256' => str_repeat( 'a', 64 ), 'ek' => 1 ) ) ) );
mb_test( 'Faz 7 doğrulayıcı (referans): geçerli sahte kayıtlar kabul', true === $F7_RV::validate_reference( $f7_refs[0] )['valid'] && true === $F7_RV::validate_reference( $f7_refs[1] )['valid'] && true === $F7_RV::validate_reference( $f7_refs[2] )['valid'] );
mb_test( 'Faz 7 doğrulayıcı (referans): dizi olmayan, fazla/eksik anahtar, yanlış tip -> ret',
	false === $F7_RV::validate_reference( null )['valid'] && false === $f7_vr( array( 'website_url' => 'https://x.example' ) ) && false === $f7_vr( array(), array( 'name' ) ) && false === $f7_vr( array(), array( 'slug' ) ) && false === $f7_vr( array(), array( 'logo_file' ) ) && false === $f7_vr( array(), array( 'alt' ) )
	&& false === $f7_vr( array(), array( 'source_index' ) ) && false === $f7_vr( array(), array( 'source' ) ) && false === $f7_vr( array( 'name' => 5 ) ) && false === $f7_vr( array( 'alt' => null ) ) && false === $f7_vr( array( 'source_index' => '0' ) ) );
mb_test( 'Faz 7 doğrulayıcı (referans): slug = slugify(name) ve source_key = "reference:"+slug zorunlu; boş ad/alt reddedilir',
	false === $f7_vr( array( 'slug' => 'baska-slug', 'source_key' => 'reference:baska-slug' ) ) && false === $f7_vr( array( 'source_key' => 'reference:baska' ) ) && false === $f7_vr( array( 'source_key' => 'news:zz-test-ref-a' ) ) && false === $f7_vr( array( 'name' => '' ) )
	&& false === $f7_vr( array( 'alt' => '' ) ) && false === $f7_vr( array( 'name' => 'ZZ  Test <b>Ref</b>' ) ) && false === $f7_vr( array( 'slug' => 'ZZ-test-ref-a', 'source_key' => 'reference:ZZ-test-ref-a' ) ) && false === $f7_vr( array( 'source_index' => -1 ) ) );
mb_test( 'Faz 7 doğrulayıcı (referans): Türkçe adın slug\'ı Faz 6A slugify ile birebir (ZZ Çağrı Şişli Ünvan -> zz-cagri-sisli-unvan)',
	true === $F7_RV::validate_reference( array_merge( $f7_refs[0], array( 'name' => 'ZZ Çağrı Şişli Ünvan', 'slug' => 'zz-cagri-sisli-unvan', 'source_key' => 'reference:zz-cagri-sisli-unvan' ) ) )['valid']
	&& false === $F7_RV::validate_reference( array_merge( $f7_refs[0], array( 'name' => 'ZZ Çağrı Şişli Ünvan', 'slug' => 'zz-cagri-sisli-nvan', 'source_key' => 'reference:zz-cagri-sisli-nvan' ) ) )['valid'] );
mb_test( 'Faz 7 doğrulayıcı (referans): logo_file yalnız bilgi; mutlak yol / ".." / boş reddedilir',
	false === $f7_vr( array( 'logo_file' => '' ) ) && false === $f7_vr( array( 'logo_file' => '/var/www/x.svg' ) ) && false === $f7_vr( array( 'logo_file' => 'C:\\x\\a.svg' ) ) && false === $f7_vr( array( 'logo_file' => '../a.svg' ) ) && false === $f7_vr( array( 'logo_file' => 5 ) ) );

/* --- D) manifest ve bağımlılık şekli --- */
$f7_cat = array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array() );
mb_test( 'Faz 7 manifest şekli: yalnız üç katalog anahtarı hâlâ geçerli (news/references İSTEĞE BAĞLI)', true === $F7_RV::validate_manifest_shape( $f7_cat )['valid'] );
mb_test( 'Faz 7 manifest şekli: news+references listeleri kabul; liste olmayan/null/scalar reddedilir; bilinmeyen üst anahtar reddedilir; katalog anahtarları hâlâ zorunlu',
	true === $F7_RV::validate_manifest_shape( array_merge( $f7_cat, array( 'news' => $f7_news, 'references' => $f7_refs ) ) )['valid'] && true === $F7_RV::validate_manifest_shape( array_merge( $f7_cat, array( 'news' => array() ) ) )['valid']
	&& false === $F7_RV::validate_manifest_shape( array_merge( $f7_cat, array( 'news' => 'x' ) ) )['valid'] && false === $F7_RV::validate_manifest_shape( array_merge( $f7_cat, array( 'references' => null ) ) )['valid'] && false === $F7_RV::validate_manifest_shape( array_merge( $f7_cat, array( 'news' => array( 'a' => 1 ) ) ) )['valid']
	&& false === $F7_RV::validate_manifest_shape( array_merge( $f7_cat, array( 'locations' => array() ) ) )['valid'] && false === $F7_RV::validate_manifest_shape( array( 'news' => array(), 'references' => array() ) )['valid'] );
$f7_dv = function ( $news ) use ( $F7_RV ) {
	return $F7_RV::validate_dependencies_shape( array_merge( mb_empty_dependencies(), array( 'news_type_term_ids' => $news ) ) )['valid'];
};
mb_test( 'Faz 7 bağımlılık DTO: mevcut üç anahtar zorunlu kalır; news_type_term_ids İSTEĞE BAĞLI (yokluğu geçerli, boş dizi geçerli)',
	true === $F7_RV::validate_dependencies_shape( mb_empty_dependencies() )['valid'] && true === $f7_dv( array() ) && true === $f7_dv( $f7_deps['news_type_term_ids'] )
	&& false === $F7_RV::validate_dependencies_shape( array( 'news_type_term_ids' => array() ) )['valid'] && array( 'sector_term_ids', 'sector_image_attachment_ids', 'qualification_post_ids' ) === $F7_RV::ALLOWED_DEPENDENCY_KEYS );
mb_test( 'Faz 7 bağımlılık DTO: news_type_term_ids kapalı şekil — type_verified true olmalı, id pozitif int, anahtar yalnız haber/duyuru, fazla alan/liste/scalar reddedilir',
	false === $f7_dv( array( 'haber' => array( 'id' => 7 ) ) ) && false === $f7_dv( array( 'haber' => array( 'id' => 7, 'type_verified' => false ) ) ) && false === $f7_dv( array( 'haber' => array( 'id' => '7', 'type_verified' => true ) ) ) && false === $f7_dv( array( 'haber' => array( 'id' => 0, 'type_verified' => true ) ) )
	&& false === $f7_dv( array( 'haber' => array( 'id' => 7, 'type_verified' => true, 'x' => 1 ) ) ) && false === $f7_dv( array( 'etkinlik' => array( 'id' => 7, 'type_verified' => true ) ) ) && false === $f7_dv( array( array( 'id' => 7, 'type_verified' => true ) ) ) && false === $f7_dv( 'haber' ) && false === $f7_dv( null )
	&& false === $F7_RV::validate_dependencies_shape( array_merge( mb_empty_dependencies(), array( 'news_type_term_ids' => array(), 'baska' => array() ) ) )['valid'] );

/* --- E) yönetilen alanlar: projeksiyon + hash determinizmi --- */
mb_test( 'Faz 7 yönetilen alanlar: news tek kaynak listesi TAM ve sıralı; reference tek kaynak listesi TAM',
	array( 'slug', 'title', 'content', 'excerpt', 'published_on', 'news_type_term_id', 'approval_status' ) === $F7_MF::NEWS_FIELDS && array( 'slug', 'title', 'reference_status', 'record_status', 'sort_order', 'website_url', 'logo_attachment_id' ) === $F7_MF::REFERENCE_FIELDS );
mb_test( 'Faz 7 projeksiyon (haber): slug/title/content(=body ham)/excerpt(=summary)/published_on/news_type_term_id(çözülmüş)/approval_status=in_review',
	array( 'slug' => 'zz-test-haber-a', 'title' => 'TEST Haber A', 'content' => $f7_news[0]['body'], 'excerpt' => $f7_news[0]['summary'], 'published_on' => '2026-01-15', 'news_type_term_id' => 501, 'approval_status' => 'in_review' ) === $f7_news_fx
	&& 502 === $F7_MF::project_news( $f7_news[1], array( 'news_type_term_id' => 502 ) )['fields']['news_type_term_id'] && $f7_news[2]['body'] === $F7_MF::project_news( $f7_news[2], array( 'news_type_term_id' => 501 ) )['fields']['content'] );
mb_test( 'Faz 7 projeksiyon (referans): sabitler representative/active/website_url=""/logo=0; sort_order = source_index+1',
	array( 'slug' => 'zz-test-ref-a', 'title' => 'ZZ Test Ref A', 'reference_status' => 'representative', 'record_status' => 'active', 'sort_order' => 1, 'website_url' => '', 'logo_attachment_id' => 0 ) === $f7_ref_fx
	&& 3 === $F7_MF::project_reference( $f7_refs[2], array() )['fields']['sort_order'] );
mb_test( 'Faz 7 hash: deterministik (iki hesap eşit), 64-hex; anahtar sırasından bağımsız; her yönetilen alan değişimi hash\'i değiştirir; marker/hash alanı girdide YOK',
	$f7_news_h === MaviBelge_Core_Import_Hash::hash( $F7_MF::project_news( $f7_news[0], array( 'news_type_term_id' => 501 ) )['fields'] ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', $f7_news_h ) && $f7_news_h === MaviBelge_Core_Import_Hash::hash( array_reverse( $f7_news_fx, true ) )
	&& (function () use ( $f7_news_fx, $f7_news_h ) {
		foreach ( array( 'slug' => 'x', 'title' => 'x', 'content' => 'x', 'excerpt' => 'x', 'published_on' => '2020-01-01', 'news_type_term_id' => 9, 'approval_status' => 'approved' ) as $k => $v ) {
			if ( MaviBelge_Core_Import_Hash::hash( array_merge( $f7_news_fx, array( $k => $v ) ) ) === $f7_news_h ) {
				return false;
			}
		}
		return ! array_key_exists( '_mb_import_source_key', $f7_news_fx ) && ! array_key_exists( '_mb_last_applied_hash', $f7_news_fx );
	})() );
mb_test( 'Faz 7 hash: referans alan değişimleri hash\'i değiştirir; iki tür aynı hash üretmez',
	$f7_ref_h === MaviBelge_Core_Import_Hash::hash( $F7_MF::project_reference( $f7_refs[0], array() )['fields'] ) && $f7_ref_h !== MaviBelge_Core_Import_Hash::hash( array_merge( $f7_ref_fx, array( 'sort_order' => 2 ) ) ) && $f7_ref_h !== MaviBelge_Core_Import_Hash::hash( array_merge( $f7_ref_fx, array( 'title' => 'x' ) ) ) && $f7_ref_h !== $f7_news_h );

/* --- F) yönetilen alan kümesi doğrulaması (mevcut durum + yük ortak kural) --- */
$f7_ok_n = function ( array $patch ) use ( $F7_RV, $f7_news_fx ) {
	return $F7_RV::is_valid_managed_field_set( 'news', array_merge( $f7_news_fx, $patch ) );
};
$f7_ok_r = function ( array $patch ) use ( $F7_RV, $f7_ref_fx ) {
	return $F7_RV::is_valid_managed_field_set( 'reference', array_merge( $f7_ref_fx, $patch ) );
};
mb_test( 'Faz 7 alan kümesi (haber): geçerli; eksik/fazla anahtar; yanlış tip/biçim -> ret',
	true === $f7_ok_n( array() ) && false === $F7_RV::is_valid_managed_field_set( 'news', array_diff_key( $f7_news_fx, array( 'content' => 1 ) ) ) && false === $F7_RV::is_valid_managed_field_set( 'news', array_merge( $f7_news_fx, array( 'x' => 1 ) ) )
	&& false === $f7_ok_n( array( 'slug' => 'A B' ) ) && false === $f7_ok_n( array( 'title' => '  ' ) ) && false === $f7_ok_n( array( 'content' => 5 ) ) && false === $f7_ok_n( array( 'excerpt' => null ) ) && false === $f7_ok_n( array( 'published_on' => '2026-02-30' ) ) && false === $f7_ok_n( array( 'published_on' => '' ) )
	&& false === $f7_ok_n( array( 'news_type_term_id' => 0 ) ) && false === $f7_ok_n( array( 'news_type_term_id' => '7' ) ) && false === $f7_ok_n( array( 'approval_status' => 'publish' ) ) && true === $f7_ok_n( array( 'approval_status' => 'approved' ) ) );
mb_test( 'Faz 7 alan kümesi (referans): geçerli; sabit alanların zorunlu enumu; tip/aralık ihlalleri -> ret',
	true === $f7_ok_r( array() ) && false === $f7_ok_r( array( 'reference_status' => 'fake' ) ) && true === $f7_ok_r( array( 'reference_status' => 'real' ) ) && false === $f7_ok_r( array( 'record_status' => 'draft' ) ) && true === $f7_ok_r( array( 'record_status' => 'passive' ) )
	&& false === $f7_ok_r( array( 'sort_order' => -1 ) ) && false === $f7_ok_r( array( 'sort_order' => '1' ) ) && false === $f7_ok_r( array( 'website_url' => null ) ) && false === $f7_ok_r( array( 'logo_attachment_id' => -1 ) ) && false === $f7_ok_r( array( 'slug' => '' ) ) && false === $f7_ok_r( array( 'title' => '' ) ) && false === $F7_RV::is_valid_managed_field_set( 'location', array() ) );

/* --- G) yazma yükü (atomik, kapalı şekil) --- */
$f7_pn = $F7_WP::prepare( 'news', $f7_news_fx, 'news:zz-test-haber-a', $f7_news_h );
$f7_pr = $F7_WP::prepare( 'reference', $f7_ref_fx, 'reference:zz-test-ref-a', $f7_ref_h );
mb_test( 'Faz 7 yük: news payload çekirdek alanları (post_name/content/excerpt/post_date "Y-m-d 12:00:00"), yönetilen meta ve mb_haber_turu terimi; type/marker/hash',
	true === $f7_pn['ok'] && array( 'post_type' => 'mb_haber', 'post_title' => 'TEST Haber A', 'post_name' => 'zz-test-haber-a', 'post_content' => $f7_news[0]['body'], 'post_excerpt' => $f7_news[0]['summary'], 'post_date' => '2026-01-15 12:00:00' ) === $f7_pn['payload']['post']
	&& array( '_mb_approval_status' => 'in_review', '_mb_import_source_key' => 'news:zz-test-haber-a', '_mb_last_applied_hash' => $f7_news_h ) === $f7_pn['payload']['post_meta'] && array( 'mb_haber_turu' => array( 501 ) ) === $f7_pn['payload']['terms']
	&& 'news' === $f7_pn['payload']['type'] && $f7_news_h === $f7_pn['payload']['managed_hash'] );
mb_test( 'Faz 7 yük: reference payload (post_name + beş yönetilen meta; ek/terim YOK; logo 0; website_url "")',
	true === $f7_pr['ok'] && array( 'post_type' => 'mb_referans', 'post_title' => 'ZZ Test Ref A', 'post_name' => 'zz-test-ref-a' ) === $f7_pr['payload']['post'] && ! isset( $f7_pr['payload']['terms'] )
	&& array( '_mb_reference_status' => 'representative', '_mb_record_status' => 'active', '_mb_sort_order' => 1, '_mb_website_url' => '', '_mb_logo_attachment_id' => 0, '_mb_import_source_key' => 'reference:zz-test-ref-a', '_mb_last_applied_hash' => $f7_ref_h ) === $f7_pr['payload']['post_meta'] );
mb_test( 'Faz 7 yük: MANAGED_POST_META news/reference TAM listeleri ve yükün ürettiği meta anahtarlarıyla birebir; qualification/fee değişmedi',
	array( '_mb_approval_status', '_mb_import_source_key', '_mb_last_applied_hash' ) === $F7_WP::MANAGED_POST_META['news'] && array( '_mb_reference_status', '_mb_record_status', '_mb_sort_order', '_mb_website_url', '_mb_logo_attachment_id', '_mb_import_source_key', '_mb_last_applied_hash' ) === $F7_WP::MANAGED_POST_META['reference']
	&& (function () use ( $f7_pn, $f7_pr, $F7_WP ) {
		$a = array_keys( $f7_pn['payload']['post_meta'] );
		$b = array_keys( $f7_pr['payload']['post_meta'] );
		sort( $a );
		sort( $b );
		$ea = $F7_WP::MANAGED_POST_META['news'];
		$eb = $F7_WP::MANAGED_POST_META['reference'];
		sort( $ea );
		sort( $eb );
		return $a === $ea && $b === $eb;
	})() && 6 === count( $F7_WP::MANAGED_POST_META['qualification'] ) && 20 === count( $F7_WP::MANAGED_POST_META['fee'] ) );
mb_test( 'Faz 7 yük: yanlış hash / yanlış aile marker / geçersiz marker / bozuk alan -> ret, payload null (kısmi yük YOK)',
	false === $F7_WP::prepare( 'news', $f7_news_fx, 'news:zz-test-haber-a', str_repeat( 'a', 64 ) )['ok'] && false === $F7_WP::prepare( 'news', $f7_news_fx, 'reference:zz-test-haber-a', $f7_news_h )['ok'] && false === $F7_WP::prepare( 'news', $f7_news_fx, 'news:A', $f7_news_h )['ok']
	&& false === $F7_WP::prepare( 'reference', $f7_ref_fx, 'news:zz-test-ref-a', $f7_ref_h )['ok'] && null === $F7_WP::prepare( 'news', $f7_news_fx, '', $f7_news_h )['payload'] && false === $F7_WP::prepare( 'news', array_merge( $f7_news_fx, array( 'x' => 1 ) ), 'news:zz-test-haber-a', $f7_news_h )['ok']
	&& false === $F7_WP::prepare( 'location', $f7_news_fx, 'location:x', $f7_news_h )['ok'] );
$f7_forced = function ( $type, array $fields, $sk ) use ( $F7_WP ) {
	return $F7_WP::prepare( $type, $fields, $sk, MaviBelge_Core_Import_Hash::hash( $fields ) );
};
mb_test( 'Faz 7 yük: import ASLA onaylı/yayın haber veya gerçek/pasif referans yazmaz (hash tutarlı olsa bile yük reddedilir)',
	false === $f7_forced( 'news', array_merge( $f7_news_fx, array( 'approval_status' => 'approved' ) ), 'news:zz-test-haber-a' )['ok'] && false === $f7_forced( 'news', array_merge( $f7_news_fx, array( 'approval_status' => 'draft' ) ), 'news:zz-test-haber-a' )['ok']
	&& false === $f7_forced( 'reference', array_merge( $f7_ref_fx, array( 'reference_status' => 'real' ) ), 'reference:zz-test-ref-a' )['ok'] && false === $f7_forced( 'reference', array_merge( $f7_ref_fx, array( 'record_status' => 'passive' ) ), 'reference:zz-test-ref-a' )['ok']
	&& false === $f7_forced( 'reference', array_merge( $f7_ref_fx, array( 'website_url' => 'https://x.example' ) ), 'reference:zz-test-ref-a' )['ok'] && false === $f7_forced( 'reference', array_merge( $f7_ref_fx, array( 'logo_attachment_id' => 5 ) ), 'reference:zz-test-ref-a' )['ok'] );
mb_test( 'Faz 7 yük: meta şemasında news/reference sistem alanları (marker + hash) tanımlı, salt-okunur ve system_managed',
	(function () {
		foreach ( array( 'mb_haber', 'mb_referans' ) as $pt ) {
			$f = MaviBelge_Core_Meta_Schema::get_fields_for( $pt );
			foreach ( array( '_mb_import_source_key', '_mb_last_applied_hash' ) as $k ) {
				if ( ! isset( $f[ $k ] ) || empty( $f[ $k ]['readonly'] ) || empty( $f[ $k ]['system_managed'] ) ) {
					return false;
				}
			}
		}
		return true;
	})() );

/* --- H) planlayıcı kararları --- */
$f7_lk = function ( $sourceKey, array $fields, $lastHash = null, $targetId = 41 ) {
	return array(
		$sourceKey => array(
			'target_found' => true, 'target_id' => $targetId, 'target_type_matches' => true, 'has_source_key_marker' => true,
			'last_applied_hash' => null === $lastHash ? MaviBelge_Core_Import_Hash::hash( $fields ) : $lastHash, 'current_managed_fields' => $fields,
		),
	);
};
$f7_none = array( 'news:zz-test-haber-a' => array( 'target_found' => false, 'natural_key' => 'none' ), 'reference:zz-test-ref-a' => array( 'target_found' => false, 'natural_key' => 'none' ) );
$f7_pn_create = $F7_PL::plan_news( $f7_news[0], $f7_none, $f7_deps );
mb_test( 'Faz 7 plan (haber): hedef yok + doğal anahtar none -> create; incoming_hash yönetilen alanların hash\'i; changed_fields boş',
	'create' === $f7_pn_create['decision'] && 'news' === $f7_pn_create['type'] && $f7_news_h === $f7_pn_create['incoming_hash'] && 'none' === $f7_pn_create['natural_key_check'] && null === $f7_pn_create['target_id'] );
mb_test( 'Faz 7 plan (haber): türü çözülmemiş -> blocked_dependency (create ÜRETİLMEZ), unresolved_dependencies=[news_type_term_id]',
	(function () use ( $F7_PL, $f7_news, $f7_none ) {
		$e = $F7_PL::plan_news( $f7_news[0], $f7_none, mb_empty_dependencies() );
		$e2 = $F7_PL::plan_news( $f7_news[0], $f7_none, array_merge( mb_empty_dependencies(), array( 'news_type_term_ids' => array( 'duyuru' => array( 'id' => 502, 'type_verified' => true ) ) ) ) );
		return 'blocked_dependency' === $e['decision'] && array( 'news_type_term_id' ) === $e['unresolved_dependencies'] && null === $e['incoming_hash'] && 'blocked_dependency' === $e2['decision'];
	})() );
mb_test( 'Faz 7 plan (haber): geçersiz kayıt (bozuk tarih) -> invalid; bozuk bağımlılık DTO -> invalid; hash yok',
	'invalid' === $F7_PL::plan_news( array_merge( $f7_news[0], array( 'published_on' => '2026-13-40' ) ), $f7_none, $f7_deps )['decision'] && 'invalid' === $F7_PL::plan_news( $f7_news[0], $f7_none, array_merge( mb_empty_dependencies(), array( 'news_type_term_ids' => array( 'haber' => 7 ) ) ) )['decision'] );
mb_test( 'Faz 7 plan (haber): mevcut hedef ve hash üçlüsü eşit -> unchanged (hash_match)',
	'unchanged' === $F7_PL::plan_news( $f7_news[0], $f7_lk( 'news:zz-test-haber-a', $f7_news_fx ), $f7_deps )['decision'] && 41 === $F7_PL::plan_news( $f7_news[0], $f7_lk( 'news:zz-test-haber-a', $f7_news_fx ), $f7_deps )['target_id'] );
$f7_old_news = array_merge( $f7_news_fx, array( 'content' => 'Eski gövde', 'excerpt' => 'Eski özet' ) );
$f7_up = $F7_PL::plan_news( $f7_news[0], $f7_lk( 'news:zz-test-haber-a', $f7_old_news ), $f7_deps );
mb_test( 'Faz 7 plan (haber): current===last ve incoming farklı -> update; changed_fields yalnız değişen alan ADLARI (içerik değeri sızmaz)',
	'update' === $f7_up['decision'] && array( 'content', 'excerpt' ) === $f7_up['changed_fields'] && false === strpos( json_encode( $f7_up ), 'Eski gövde' ) );
$f7_cf = $F7_PL::plan_news( $f7_news[0], $f7_lk( 'news:zz-test-haber-a', array_merge( $f7_news_fx, array( 'content' => 'Editör düzenlemesi' ) ), MaviBelge_Core_Import_Hash::hash( $f7_news_fx ) ), $f7_deps );
mb_test( 'Faz 7 plan (haber): editör yönetilen alanı (içerik) değiştirmiş -> conflict (manual_edit_detected), update DEĞİL',
	'conflict' === $f7_cf['decision'] && 'manual_edit_detected' === $f7_cf['reason'] );
$f7_appr = $F7_PL::plan_news( $f7_news[0], $f7_lk( 'news:zz-test-haber-a', array_merge( $f7_news_fx, array( 'approval_status' => 'approved' ) ), $f7_news_h ), $f7_deps );
mb_test( 'Faz 7 plan (haber): editör haberi onaylamış (approval_status=approved) -> conflict; import onayı geri almaz', 'conflict' === $f7_appr['decision'] && 'manual_edit_detected' === $f7_appr['reason'] );
mb_test( 'Faz 7 plan (haber): marker var ama hash yok -> legacy_missing_hash conflict; marker yok + hedef var -> yanlış/marker\'sız conflict; iki hedef -> duplicate',
	'conflict' === $F7_PL::plan_news( $f7_news[0], array( 'news:zz-test-haber-a' => array( 'target_found' => true, 'target_id' => 41, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => null, 'current_managed_fields' => $f7_news_fx ) ), $f7_deps )['decision']
	&& 'conflict_duplicate_target' === $F7_PL::plan_news( $f7_news[0], array( 'news:zz-test-haber-a' => array( 'target_found' => false, 'duplicate_targets' => true ) ), $f7_deps )['decision']
	&& 'conflict_wrong_target_type' === $F7_PL::plan_news( $f7_news[0], array( 'news:zz-test-haber-a' => array( 'target_found' => true, 'target_id' => 41, 'target_type_matches' => false, 'has_source_key_marker' => true, 'last_applied_hash' => null, 'current_managed_fields' => null ) ), $f7_deps )['decision'] );
mb_test( 'Faz 7 plan (haber): marker ile hedef yok ama doğal anahtar dolu (unmanaged/foreign/duplicate/query_error) -> conflict; doğal anahtar kanıtı yok (null) -> create ama uygun DEĞİL (eligibility)',
	(function () use ( $F7_PL, $f7_news, $f7_deps, $F7_AE ) {
		foreach ( array( 'unmanaged', 'foreign_marker', 'undiscovered_marker', 'corrupt_marker', 'wrong_marker_prefix', 'duplicate', 'query_error' ) as $state ) {
			$e = $F7_PL::plan_news( $f7_news[0], array( 'news:zz-test-haber-a' => array( 'target_found' => false, 'natural_key' => $state ) ), $f7_deps );
			if ( 'conflict' !== $e['decision'] && 'conflict_duplicate_target' !== $e['decision'] ) {
				return false;
			}
		}
		$plain = $F7_PL::plan_news( $f7_news[0], array( 'news:zz-test-haber-a' => array( 'target_found' => false ) ), $f7_deps );
		$plan  = array( 'entries' => array( $plain ), 'summary' => array( 'applicable' => true, 'total' => 1 ), 'errors' => array() );
		return 'create' === $plain['decision'] && false === $F7_AE::evaluate_plan( $plan )['eligible'];
	})() );
mb_test( 'Faz 7 plan (haber): mevcut durum bozuk (sahte tip/eksik alan) -> invalid_target_state conflict; hiçbir sessiz varsayılan',
	'conflict' === $F7_PL::plan_news( $f7_news[0], $f7_lk( 'news:zz-test-haber-a', array_merge( $f7_news_fx, array( 'news_type_term_id' => '501' ) ) ), $f7_deps )['decision'] && 'conflict' === $F7_PL::plan_news( $f7_news[0], $f7_lk( 'news:zz-test-haber-a', array_diff_key( $f7_news_fx, array( 'excerpt' => 1 ) ), $f7_news_h ), $f7_deps )['decision'] );
$f7_rn = array( 'reference:zz-test-ref-a' => array( 'target_found' => false, 'natural_key' => 'none' ) );
mb_test( 'Faz 7 plan (referans): create (bağımlılık gerekmez, boş DTO ile de); unchanged; sort_order değişimi update; editör pasife almış -> conflict; geçersiz -> invalid',
	'create' === $F7_PL::plan_reference( $f7_refs[0], $f7_rn, mb_empty_dependencies() )['decision'] && $f7_ref_h === $F7_PL::plan_reference( $f7_refs[0], $f7_rn, mb_empty_dependencies() )['incoming_hash']
	&& 'unchanged' === $F7_PL::plan_reference( $f7_refs[0], $f7_lk( 'reference:zz-test-ref-a', $f7_ref_fx ), mb_empty_dependencies() )['decision']
	&& array( 'sort_order' ) === $F7_PL::plan_reference( $f7_refs[0], $f7_lk( 'reference:zz-test-ref-a', array_merge( $f7_ref_fx, array( 'sort_order' => 9 ) ) ), mb_empty_dependencies() )['changed_fields']
	&& 'update' === $F7_PL::plan_reference( $f7_refs[0], $f7_lk( 'reference:zz-test-ref-a', array_merge( $f7_ref_fx, array( 'sort_order' => 9 ) ) ), mb_empty_dependencies() )['decision']
	&& 'conflict' === $F7_PL::plan_reference( $f7_refs[0], $f7_lk( 'reference:zz-test-ref-a', array_merge( $f7_ref_fx, array( 'record_status' => 'passive' ) ), $f7_ref_h ), mb_empty_dependencies() )['decision']
	&& 'invalid' === $F7_PL::plan_reference( array_merge( $f7_refs[0], array( 'slug' => 'baska', 'source_key' => 'reference:baska' ) ), $f7_rn, mb_empty_dependencies() )['decision'] );
$f7_full = $F7_PL::plan( array_merge( $f7_cat, array( 'news' => $f7_news, 'references' => $f7_refs ) ), array(), $f7_deps );
mb_test( 'Faz 7 plan: news+references içeren manifest tam plan; toplam 6; by_type news/reference sayılır; sum(by_type)=total',
	6 === $f7_full['summary']['total'] && 3 === $f7_full['summary']['by_type']['news'] && 3 === $f7_full['summary']['by_type']['reference'] && 0 === $f7_full['summary']['by_type']['sector'] && array_sum( $f7_full['summary']['by_type'] ) === $f7_full['summary']['total'] && true === $f7_full['summary']['total_matches_input'] );
$f7_cat_plan = $F7_PL::plan( $f7_cat, array(), mb_empty_dependencies() );
mb_test( 'Faz 7 plan: news/references anahtarı OLMAYAN katalog manifesti aynen çalışır; by_type YALNIZ üç katalog anahtarı (mevcut çıktı şekli değişmedi)',
	array( 'sector', 'qualification', 'fee' ) === array_keys( $f7_cat_plan['summary']['by_type'] ) && array() === $f7_cat_plan['entries'] && array() === $f7_cat_plan['errors'] );
mb_test( 'Faz 7 plan: news ve references ARASINDA ve katalogla tekrar eden source_key -> plan hatası, entry yok',
	(function () use ( $F7_PL, $f7_cat, $f7_news, $f7_refs, $f7_deps ) {
		$dup = $f7_refs;
		$dup[2] = $f7_refs[0];
		$dup[2]['source_index'] = 2;
		$p1 = $F7_PL::plan( array_merge( $f7_cat, array( 'news' => $f7_news, 'references' => $dup ) ), array(), $f7_deps );
		$dupn = $f7_news;
		$dupn[1] = array_merge( $f7_news[0], array( 'source_index' => 1 ) );
		$p2 = $F7_PL::plan( array_merge( $f7_cat, array( 'news' => $dupn ) ), array(), $f7_deps );
		return array() === $p1['entries'] && ! empty( $p1['errors'] ) && array() === $p2['entries'] && ! empty( $p2['errors'] );
	})() );
mb_test( 'Faz 7 plan: news kaydında yanlış source_index konumu / kaynak provenance kayması -> plan hatası',
	(function () use ( $F7_PL, $f7_cat, $f7_news, $f7_deps ) {
		$bad = $f7_news;
		$bad[1]['source_index'] = 5;
		$drift = $f7_news;
		$drift[1]['source']['sha256'] = str_repeat( 'b', 64 );
		$a = $F7_PL::plan( array_merge( $f7_cat, array( 'news' => $bad ) ), array(), $f7_deps );
		$b = $F7_PL::plan( array_merge( $f7_cat, array( 'news' => $drift ) ), array(), $f7_deps );
		return array() === $a['entries'] && ! empty( $a['errors'] ) && array() === $b['entries'] && ! empty( $b['errors'] );
	})() );
mb_test( 'Faz 7 project_for_apply: news/reference planlayıcıyla aynı alanlar; çözülmemiş bağımlılık -> ok=false',
	true === $F7_PL::project_for_apply( 'news', $f7_news[0], $f7_deps )['ok'] && $f7_news_fx === $F7_PL::project_for_apply( 'news', $f7_news[0], $f7_deps )['fields'] && $f7_ref_fx === $F7_PL::project_for_apply( 'reference', $f7_refs[0], mb_empty_dependencies() )['fields']
	&& false === $F7_PL::project_for_apply( 'news', $f7_news[0], mb_empty_dependencies() )['ok'] && false === $F7_PL::project_for_apply( 'location', $f7_news[0], $f7_deps )['ok'] );

/* --- I) aşama modeli --- */
mb_test( 'Faz 7 aşama: content aşaması eklendi; mevcut üç aşama ve tür listeleri AYNEN',
	array( 'sectors', 'qualifications', 'all' ) === $F7_AP::STAGES && array( 'content' ) === $F7_AP::CONTENT_STAGES && array( 'sectors', 'qualifications', 'all', 'content' ) === $F7_AP::ALL_STAGES && array( 'news', 'reference' ) === $F7_AP::STAGE_TYPES['content'] && array( 'sector' ) === $F7_AP::STAGE_TYPES['sectors'] && array( 'sector', 'qualification' ) === $F7_AP::STAGE_TYPES['qualifications'] && array( 'sector', 'qualification', 'fee' ) === $F7_AP::STAGE_TYPES['all']
	&& 'news' === $F7_AP::TYPE_LISTS['news'] && 'references' === $F7_AP::TYPE_LISTS['reference'] && 'sectors' === $F7_AP::TYPE_LISTS['sector'] && 'fees' === $F7_AP::TYPE_LISTS['fee'] );
mb_test( 'Faz 7 aşama: yazma sırası news sonra reference; rollback sırası reference sonra news; katalog sırası aynen (sektör<yeterlilik<ücret; ücret->yeterlilik->sektör)',
	(function () use ( $F7_AP ) {
		$w = array( array( 'type' => 'reference', 'source_key' => 'r' ), array( 'type' => 'news', 'source_key' => 'n' ), array( 'type' => 'fee', 'source_key' => 'f' ), array( 'type' => 'sector', 'source_key' => 's' ), array( 'type' => 'qualification', 'source_key' => 'q' ) );
		$order = array_map( function ( $x ) {
			return $x['source_key'];
		}, $F7_AP::order_writes( $w ) );
		$items = array(
			array( 'type' => 'news', 'seq' => 1 ), array( 'type' => 'news', 'seq' => 2 ), array( 'type' => 'reference', 'seq' => 3 ), array( 'type' => 'reference', 'seq' => 4 ),
		);
		$rb = array_map( function ( $x ) {
			return $x['type'] . $x['seq'];
		}, $F7_AP::rollback_order( $items ) );
		$cat = array_map( function ( $x ) {
			return $x['type'] . $x['seq'];
		}, $F7_AP::rollback_order( array( array( 'type' => 'sector', 'seq' => 1 ), array( 'type' => 'fee', 'seq' => 2 ), array( 'type' => 'qualification', 'seq' => 3 ) ) ) );
		return array( 's', 'q', 'f', 'n', 'r' ) === $order && array( 'reference4', 'reference3', 'news2', 'news1' ) === $rb && array( 'fee2', 'qualification3', 'sector1' ) === $cat;
	})() );
mb_test( 'Faz 7 aşama: filter_manifest content -> yalnız news+references dolu (katalog listeleri boş); katalog aşamaları news/references anahtarı EKLEMEZ',
	(function () use ( $F7_AP, $f7_cat, $f7_news, $f7_refs ) {
		$full = array_merge( array( 'sectors' => array( 1 ), 'qualifications' => array( 2 ), 'fees' => array( 3 ) ), array( 'news' => $f7_news, 'references' => $f7_refs ) );
		$c    = $F7_AP::filter_manifest( $full, 'content' );
		$a    = $F7_AP::filter_manifest( $full, 'all' );
		$s    = $F7_AP::filter_manifest( array( 'sectors' => array( 1 ), 'qualifications' => array( 2 ), 'fees' => array( 3 ) ), 'sectors' );
		$noc  = $F7_AP::filter_manifest( $f7_cat, 'content' );
		return array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array(), 'news' => $f7_news, 'references' => $f7_refs ) === $c && array( 'sectors', 'qualifications', 'fees' ) === array_keys( $a ) && array( 'sectors' => array( 1 ), 'qualifications' => array(), 'fees' => array() ) === $s
			&& array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array(), 'news' => array(), 'references' => array() ) === $noc && null === $F7_AP::filter_manifest( array( 'sectors' => array() ), 'content' ) && null === $F7_AP::filter_manifest( array_merge( $f7_cat, array( 'news' => 'x' ) ), 'content' ) && null === $F7_AP::filter_manifest( $f7_cat, 'kontent' );
	})() );

/* --- J) rollback kaydı ve audit context --- */
$f7_rb_c = $F7_AE::build_rollback_record( 'news', 'news:zz-test-haber-a', 41, 'create', null, $f7_news_fx, null, $f7_news_h, str_repeat( 'c', 64 ) );
$f7_rb_r = $F7_AE::build_rollback_record( 'reference', 'reference:zz-test-ref-a', 42, 'create', null, $f7_ref_fx, null, $f7_ref_h, str_repeat( 'c', 64 ) );
mb_test( 'Faz 7 rollback kaydı: news/reference create kaydı geçerli; changed_fields tüm allowlist (deterministik); validate_rollback_record true',
	is_array( $f7_rb_c ) && $F7_MF::NEWS_FIELDS === $f7_rb_c['changed_fields'] && true === $F7_AE::validate_rollback_record( $f7_rb_c ) && is_array( $f7_rb_r ) && $F7_MF::REFERENCE_FIELDS === $f7_rb_r['changed_fields'] && true === $F7_AE::validate_rollback_record( $f7_rb_r ) );
$f7_old_h  = MaviBelge_Core_Import_Hash::hash( $f7_old_news );
$f7_rb_u   = $F7_AE::build_rollback_record( 'news', 'news:zz-test-haber-a', 41, 'update', $f7_old_news, $f7_news_fx, $f7_old_h, $f7_news_h );
mb_test( 'Faz 7 rollback kaydı: update kaydı changed_fields = yalnız farklı alanlar; alan–hash uyumsuzluğu, yanlış tür/anahtar ailesi, kurcalanmış alan -> null/false',
	is_array( $f7_rb_u ) && array( 'content', 'excerpt' ) === $f7_rb_u['changed_fields'] && null === $F7_AE::build_rollback_record( 'news', 'news:zz-test-haber-a', 41, 'update', $f7_old_news, $f7_news_fx, str_repeat( 'a', 64 ), $f7_news_h )
	&& null === $F7_AE::build_rollback_record( 'news', 'reference:zz-test-ref-a', 41, 'create', null, $f7_news_fx, null, $f7_news_h, str_repeat( 'c', 64 ) ) && null === $F7_AE::build_rollback_record( 'news', 'news:zz-test-haber-a', 41, 'create', null, $f7_news_fx, null, $f7_news_h )
	&& false === $F7_AE::validate_rollback_record( array_merge( $f7_rb_c, array( 'type' => 'reference' ) ) ) && false === $F7_AE::validate_rollback_record( array_merge( $f7_rb_c, array( 'new_fields' => array_merge( $f7_news_fx, array( 'content' => 'Kurcalanmış' ) ) ) ) ) && null === $F7_AE::build_rollback_record( 'location', 'location:x', 1, 'create', null, $f7_news_fx, null, $f7_news_h, str_repeat( 'c', 64 ) ) );
mb_test( 'Faz 7 rollback: hedefin şu anki hash\'i new_hash\'e eşit değilse (editör içeriği değiştirmiş) izin YOK',
	true === $F7_AE::rollback_allowed( $f7_rb_c, $f7_news_h ) && false === $F7_AE::rollback_allowed( $f7_rb_c, MaviBelge_Core_Import_Hash::hash( array_merge( $f7_news_fx, array( 'content' => 'Editör' ) ) ) ) );
$AC7 = 'MaviBelge_Core_Import_Audit_Context';
mb_test( 'Faz 7 audit: news/reference source_key + tür + değişen alan ADLARI (content/excerpt/news_type_term_id/sort_order/...) kabul; içerik değeri taşınamaz',
	is_array( $AC7::build( array( 'run_id' => str_repeat( 'a', 32 ), 'items' => array( array( 'source_key' => 'news:zz-test-haber-a', 'type' => 'news', 'decision' => 'create', 'target_id' => 41, 'old_hash' => null, 'new_hash' => $f7_news_h, 'changed_fields' => $F7_MF::NEWS_FIELDS ) ) ) ) )
	&& is_array( $AC7::build( array( 'source_key' => 'reference:zz-test-ref-a', 'type' => 'reference', 'changed_fields' => $F7_MF::REFERENCE_FIELDS ) ) )
	&& null === $AC7::build( array( 'changed_fields' => array( 'body' ) ) ) && null === $AC7::build( array( 'items' => array( array( 'source_key' => 'news:zz-test-haber-a', 'new_fields' => array( 'content' => 'x' ) ) ) ) ) );
mb_test( 'Faz 7 audit: bilinmeyen tür (location/document/faq) ve yanlış aile source_key reddedilir',
	null === $AC7::build( array( 'type' => 'location' ) ) && null === $AC7::build( array( 'source_key' => 'location:x' ) ) && null === $AC7::build( array( 'source_key' => 'news:A' ) ) && null === $AC7::build( array( 'source_key' => 'news:' ) ) && null === $AC7::build( array( 'type' => 'document' ) ) );

/* --- K) depo SAF kurucuları (WordPress çağırmaz) --- */
$f7_raw_n = array( 'slug' => 'zz-test-haber-a', 'title' => 'TEST Haber A', 'content' => 'g', 'excerpt' => 'o', 'post_date' => '2026-01-15 12:00:00', 'news_type_term_ids' => array( 501 ), 'approval_status' => 'in_review' );
$f7_bn = function ( array $patch, $drop = array() ) use ( $F7_RP, $f7_raw_n ) {
	$r = array_merge( $f7_raw_n, $patch );
	foreach ( $drop as $k ) {
		unset( $r[ $k ] );
	}
	return $F7_RP::news_fields_from_raw( $r );
};
mb_test( 'Faz 7 depo (haber): ham değerlerden yönetilen alanlar; post_date\'ten yalnız YYYY-MM-DD alınır; tek terim int olarak',
	array( 'slug' => 'zz-test-haber-a', 'title' => 'TEST Haber A', 'content' => 'g', 'excerpt' => 'o', 'published_on' => '2026-01-15', 'news_type_term_id' => 501, 'approval_status' => 'in_review' ) === $f7_bn( array() ) && '2026-01-15' === $f7_bn( array( 'post_date' => '2026-01-15 23:59:59' ) )['published_on'] && 501 === $f7_bn( array( 'news_type_term_ids' => array( '501' ) ) )['news_type_term_id'] );
mb_test( 'Faz 7 depo (haber): TEK bozuk alan tüm alan kümesini null yapar (dizi/int/bool/null alan, bozuk tarih, 0/2+ terim, eksik/fazla anahtar); sahte varsayılan yok',
	null === $f7_bn( array( 'slug' => array( 'x' ) ) ) && null === $f7_bn( array( 'title' => 5 ) ) && null === $f7_bn( array( 'content' => null ) ) && null === $f7_bn( array( 'excerpt' => false ) ) && null === $f7_bn( array( 'approval_status' => 1 ) )
	&& null === $f7_bn( array( 'post_date' => '' ) ) && null === $f7_bn( array( 'post_date' => '2026-02-30 12:00:00' ) ) && null === $f7_bn( array( 'post_date' => '15.01.2026' ) ) && null === $f7_bn( array( 'post_date' => 20260115 ) ) && null === $f7_bn( array( 'post_date' => "2026-01-15 12:00:00\n" ) )
	&& null === $f7_bn( array( 'news_type_term_ids' => array() ) ) && null === $f7_bn( array( 'news_type_term_ids' => array( 501, 502 ) ) ) && null === $f7_bn( array( 'news_type_term_ids' => 501 ) ) && null === $f7_bn( array( 'news_type_term_ids' => array( 'x' ) ) ) && null === $f7_bn( array( 'news_type_term_ids' => array( 0 ) ) )
	&& null === $f7_bn( array(), array( 'excerpt' ) ) && null === $f7_bn( array( 'fazla' => 1 ) ) );
$f7_raw_r = array( 'slug' => 'zz-test-ref-a', 'title' => 'ZZ Test Ref A', 'reference_status' => 'representative', 'record_status' => 'active', 'sort_order' => '1', 'website_url' => '', 'logo_attachment_id' => '' );
$f7_br = function ( array $patch, $drop = array() ) use ( $F7_RP, $f7_raw_r ) {
	$r = array_merge( $f7_raw_r, $patch );
	foreach ( $drop as $k ) {
		unset( $r[ $k ] );
	}
	return $F7_RP::reference_fields_from_raw( $r );
};
mb_test( 'Faz 7 depo (referans): meta metin temsilleri katı dönüştürülür ("1"->1, ""->0); bozuk her alan tüm kümeyi null yapar',
	array( 'slug' => 'zz-test-ref-a', 'title' => 'ZZ Test Ref A', 'reference_status' => 'representative', 'record_status' => 'active', 'sort_order' => 1, 'website_url' => '', 'logo_attachment_id' => 0 ) === $f7_br( array() ) && 0 === $f7_br( array( 'sort_order' => '' ) )['sort_order']
	&& null === $f7_br( array( 'sort_order' => '1x' ) ) && null === $f7_br( array( 'sort_order' => '-1' ) ) && null === $f7_br( array( 'sort_order' => array( 1 ) ) ) && null === $f7_br( array( 'logo_attachment_id' => '1.5' ) ) && null === $f7_br( array( 'website_url' => array() ) ) && null === $f7_br( array( 'reference_status' => 5 ) )
	&& null === $f7_br( array( 'slug' => null ) ) && null === $f7_br( array(), array( 'title' ) ) && null === $f7_br( array( 'x' => 1 ) ) );

/* --- L) manifest yükleyici: içerik dosyaları YALNIZ content aşaması için --- */
$ML7 = 'MaviBelge_Core_Import_Manifest_Loader';
$f7_dir_c = mb6b2_temp_dir( 'f7content' );
mb_content_fixture_write_dir( $f7_dir_c, mb_content_fixture_envelopes() );
$f7_dir_a = mb6b2_temp_dir( 'f7both' );
mb_apply_fixture_write_dir( $f7_dir_a, mb_apply_fixture_envelopes() );
mb_content_fixture_write_dir( $f7_dir_a, mb_content_fixture_envelopes() );
$f7_dir_k = mb6b2_temp_dir( 'f7catonly' );
mb_apply_fixture_write_dir( $f7_dir_k, mb_apply_fixture_envelopes() );
$f7_lc = $ML7::load_content( $f7_dir_c );
mb_test( 'Faz 7 yükleyici: load_content news+references kayıtlarını OLDUĞU GİBİ (sırasıyla) döndürür; sabit dosya adları',
	true === $f7_lc['ok'] && array( 'news', 'references' ) === array_keys( $f7_lc['manifest'] ) && $f7_news === $f7_lc['manifest']['news'] && $f7_refs === $f7_lc['manifest']['references'] && 'news.manifest.json' === $ML7::CONTENT_FILES['news'] && 'references.manifest.json' === $ML7::CONTENT_FILES['reference'] );
mb_test( 'Faz 7 yükleyici: katalog load_all içerik dosyaları YOKKEN de VARKEN de aynı sonucu verir; yalnız üç katalog dosyası (FILES değişmedi)',
	true === $ML7::load_all( $f7_dir_k )['ok'] && true === $ML7::load_all( $f7_dir_a )['ok'] && $ML7::load_all( $f7_dir_k ) === $ML7::load_all( $f7_dir_a ) && array( 'sectors', 'qualifications', 'fees' ) === array_keys( $ML7::load_all( $f7_dir_a )['manifest'] ) && 3 === count( $ML7::FILES ) );
mb_test( 'Faz 7 yükleyici: içerik dosyası yoksa load_content hata verir (katalog etkilenmez)', false === $ML7::load_content( $f7_dir_k )['ok'] && null === $ML7::load_content( $f7_dir_k )['manifest'] && ! empty( $ML7::load_content( $f7_dir_k )['errors'] ) );
$f7_bad = function ( callable $mutate ) use ( $ML7 ) {
	$dir = mb6b2_temp_dir( 'f7bad' );
	$env = mb_content_fixture_envelopes();
	$env = $mutate( $env );
	mb_content_fixture_write_dir( $dir, $env );
	return $ML7::load_content( $dir );
};
mb_test( 'Faz 7 yükleyici: count uyuşmazlığı, yanlış record_type, yanlış source.file, provenance kayması, fazla zarf alanı, eksik notes -> ret',
	false === $f7_bad( function ( $e ) {
		$e['news']['count'] = 99;
		return $e;
	} )['ok'] && false === $f7_bad( function ( $e ) {
		$e['news']['record_type'] = 'reference';
		return $e;
	} )['ok'] && false === $f7_bad( function ( $e ) {
		$e['reference']['source']['file'] = 'tanitim-site/assets/data/news.js';
		return $e;
	} )['ok'] && false === $f7_bad( function ( $e ) {
		$e['news']['records'][1]['source']['sha256'] = str_repeat( 'd', 64 );
		return $e;
	} )['ok'] && false === $f7_bad( function ( $e ) {
		$e['reference']['ek'] = 1;
		return $e;
	} )['ok'] && false === $f7_bad( function ( $e ) {
		unset( $e['news']['notes'] );
		return $e;
	} )['ok'] );
mb_test( 'Faz 7 yükleyici: dosyalardan biri eksikse load_content TÜMÜNÜ reddeder (kısmi içerik manifesti yok)',
	false === $f7_bad( function ( $e ) {
		unset( $e['reference'] );
		return $e;
	} )['ok'] );

/** Çözümleyici arayüzünü UYGULAMAYAN salt-okunur depo sarmalayıcısı. */
class MB_Fake_Plain_Repository implements MaviBelge_Core_Import_Target_Repository {
	private $inner;
	public function __construct( MB_Fake_World_Repository $inner ) {
		$this->inner = $inner;
	}
	public function find_target_by_source_key( $type, $sourceKey ) {
		return $this->inner->find_target_by_source_key( $type, $sourceKey );
	}
	public function resolve_sector_term_id( $sectorSlug ): ?array {
		return $this->inner->resolve_sector_term_id( $sectorSlug );
	}
	public function resolve_qualification_post_id( $mykCode ): ?array {
		return $this->inner->resolve_qualification_post_id( $mykCode );
	}
	public function resolve_sector_image_attachment_id( $sectorSlug ): ?array {
		return $this->inner->resolve_sector_image_attachment_id( $sectorSlug );
	}
	public function get_diagnostics() {
		return $this->inner->get_diagnostics();
	}
}

/* --- M) sahte dünyada uçtan uca (dry-run servisi, apply, rollback) --- */
function f7ic_seed( MB_Fake_World $w ) {
	$w->terms[501] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'haber', 'name' => 'Haber', 'description' => '', 'parent' => 0, 'meta' => array() );
	$w->terms[502] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'duyuru', 'name' => 'Duyuru', 'description' => '', 'parent' => 0, 'meta' => array() );
}
function f7ic_env( $dir, $seed = true ) {
	$env = mb_fake_apply_env( $dir );
	if ( $seed ) {
		f7ic_seed( $env->world );
	}
	return $env;
}
function f7ic_apply( $env, $stage = 'content', $batch = null ) {
	$p = $env->apply->preview( $stage );
	return $env->apply->apply( $stage, (string) $p['plan_digest'], $batch, 1 );
}
function f7ic_posts( $env, $type, $status = null ) {
	$out = array();
	foreach ( $env->world->posts as $id => $p ) {
		if ( $p['post_type'] === $type && ( null === $status || $p['status'] === $status ) ) {
			$out[ $id ] = $p;
		}
	}
	return $out;
}
function f7ic_by_key( $env, $type, $sourceKey ) {
	foreach ( f7ic_posts( $env, $type ) as $id => $p ) {
		if ( isset( $p['meta']['_mb_import_source_key'] ) && $p['meta']['_mb_import_source_key'] === $sourceKey ) {
			return array( $id, $p );
		}
	}
	return array( null, null );
}
function f7ic_rollback( $env, $uid ) {
	$pv = $env->rollback->preview( $uid );
	return $env->rollback->rollback( $uid, (string) $pv['rollback_digest'], null );
}
$f7_untouched = function ( $env ) {
	return array() === $env->world->writeLog && array() === $env->world->runs && array() === $env->world->audit && array() === $env->world->items;
};

$E7a = f7ic_env( $f7_dir_c );
$E7ap = $E7a->apply->preview( 'content' );
mb_test( 'Faz 7 apply: temiz sahte dünyada content aşaması önizlemesi — 6 create (3 haber + 3 referans), applicable, eligible, 64-hex digest; salt okunur',
	true === $E7ap['ok'] && true === $E7ap['eligible'] && 6 === $E7ap['writes'] && 6 === $E7ap['summary']['operations']['create'] && 3 === $E7ap['summary']['by_type']['news'] && 3 === $E7ap['summary']['by_type']['reference'] && true === $E7ap['summary']['applicable']
	&& 1 === preg_match( '/^[0-9a-f]{64}\z/', $E7ap['plan_digest'] ) && $f7_untouched( $E7a ) );
$E7ar = $E7a->apply->apply( 'content', $E7ap['plan_digest'], null, 1 );
mb_test( 'Faz 7 apply: content aşaması completed, 6 item, tek batch, run satırı stage=content',
	true === $E7ar['ok'] && 'completed' === $E7ar['status'] && 6 === $E7ar['committed_items'] && 1 === $E7ar['committed_batches'] && 'content' === $E7a->store->get_run( $E7ar['run_uid'] )['stage'] && 6 === count( $E7a->world->items ) );
mb_test( 'Faz 7 apply: yazma sırası news sonra reference (haber a,b,c; referans a,b,c)',
	array( 'create_post:news:zz-test-haber-a', 'create_post:news:zz-test-haber-b', 'create_post:news:zz-test-haber-c', 'create_post:reference:zz-test-ref-a', 'create_post:reference:zz-test-ref-b', 'create_post:reference:zz-test-ref-c' ) === $E7a->world->writeLog );
list( $f7_id_a, $f7_p_a ) = f7ic_by_key( $E7a, 'mb_haber', 'news:zz-test-haber-a' );
list( $f7_id_b, $f7_p_b ) = f7ic_by_key( $E7a, 'mb_haber', 'news:zz-test-haber-b' );
mb_test( 'Faz 7 apply: haber DRAFT olarak yazıldı (asla publish), post_name/başlık/ham içerik/özet/tarih doğru, onay durumu in_review, tür terimi doğru (haber=501, duyuru=502)',
	null !== $f7_p_a && 'draft' === $f7_p_a['status'] && 'zz-test-haber-a' === $f7_p_a['name'] && 'TEST Haber A' === $f7_p_a['title'] && $f7_news[0]['body'] === $f7_p_a['content'] && $f7_news[0]['summary'] === $f7_p_a['excerpt'] && '2026-01-15 12:00:00' === $f7_p_a['date']
	&& 'in_review' === $f7_p_a['meta']['_mb_approval_status'] && array( 501 ) === $f7_p_a['terms']['mb_haber_turu'] && array( 502 ) === $f7_p_b['terms']['mb_haber_turu'] && 3 === count( f7ic_posts( $E7a, 'mb_haber', 'draft' ) ) && 0 === count( f7ic_posts( $E7a, 'mb_haber', 'publish' ) ) );
mb_test( 'Faz 7 apply: çok satırlı gövde ham korunur; marker + hash yazıldı ve plan hash\'ine eşit',
	"Sahte haber C gövdesi.\nİkinci satır." === f7ic_by_key( $E7a, 'mb_haber', 'news:zz-test-haber-c' )[1]['content'] && $f7_news_h === $f7_p_a['meta']['_mb_last_applied_hash'] && 'news:zz-test-haber-a' === $f7_p_a['meta']['_mb_import_source_key'] );
list( $f7_id_ra, $f7_p_ra ) = f7ic_by_key( $E7a, 'mb_referans', 'reference:zz-test-ref-a' );
mb_test( 'Faz 7 apply: referans DRAFT, post_name=slug, temsili/aktif, sort_order 1/2/3, website_url "" ve logo 0 (ek eşlemesi yok); marker+hash',
	null !== $f7_p_ra && 'draft' === $f7_p_ra['status'] && 'zz-test-ref-a' === $f7_p_ra['name'] && 'ZZ Test Ref A' === $f7_p_ra['title'] && 'representative' === $f7_p_ra['meta']['_mb_reference_status'] && 'active' === $f7_p_ra['meta']['_mb_record_status'] && '1' === $f7_p_ra['meta']['_mb_sort_order']
	&& '2' === f7ic_by_key( $E7a, 'mb_referans', 'reference:zz-test-ref-b' )[1]['meta']['_mb_sort_order'] && '3' === f7ic_by_key( $E7a, 'mb_referans', 'reference:zz-test-ref-c' )[1]['meta']['_mb_sort_order'] && '' === $f7_p_ra['meta']['_mb_website_url'] && '0' === $f7_p_ra['meta']['_mb_logo_attachment_id']
	&& $f7_ref_h === $f7_p_ra['meta']['_mb_last_applied_hash'] && 3 === count( f7ic_posts( $E7a, 'mb_referans', 'draft' ) ) && array() === $f7_p_ra['terms'] );
mb_test( 'Faz 7 apply: katalog türlerine dokunulmadı (yalnız iki kontrollü haber türü terimi); audit started -> batch_committed -> completed; audit yalnız alan ADLARI',
	2 === count( $E7a->world->terms ) && 0 === count( f7ic_posts( $E7a, 'mb_yeterlilik' ) ) && 0 === count( f7ic_posts( $E7a, 'mb_ucret' ) ) && array( 'import_run_started', 'import_batch_committed', 'import_run_completed' ) === array_map( function ( $a ) {
		return $a['event'];
	}, $E7a->world->audit ) && false === strpos( json_encode( $E7a->world->audit ), 'gövdesi' ) );
$f7_rb_items = $E7a->store->get_items( $E7a->world->runs[1]['id'] );
mb_test( 'Faz 7 apply: her item için doğrulanmış rollback kaydı (create + 64-hex unmanaged_fingerprint) saklandı',
	6 === count( $f7_rb_items ) && (function () use ( $f7_rb_items ) {
		foreach ( $f7_rb_items as $row ) {
			$rec = MaviBelge_Core_Import_Rollback_Codec::decode( $row['rollback_record'] );
			if ( null === $rec || 'create' !== $rec['decision'] || 1 !== preg_match( '/^[0-9a-f]{64}\z/', (string) $rec['unmanaged_fingerprint'] ) || ! in_array( $rec['type'], array( 'news', 'reference' ), true ) ) {
				return false;
			}
		}
		return true;
	})() );
mb_test( 'Faz 7 idempotent: plan yeniden -> 6 unchanged, applicable, 0 yazma; ikinci apply noop (yeni run/item/audit/yazma YOK)',
	(function () use ( $E7a ) {
		$p = $E7a->apply->preview( 'content' );
		$n = f7ic_apply( $E7a );
		return 6 === $p['summary']['operations']['unchanged'] && true === $p['summary']['applicable'] && 0 === $p['writes'] && 'noop' === $n['status'] && 1 === count( $E7a->world->runs ) && 6 === count( $E7a->world->items ) && 3 === count( $E7a->world->audit ) && 6 === count( $E7a->world->writeLog );
	})() );

// Rollback -> yeniden apply döngüsü.
$f7_uid  = $E7a->world->runs[1]['uid'];
$f7_rbp  = $E7a->rollback->preview( $f7_uid );
mb_test( 'Faz 7 rollback önizleme: 6 bekleyen item, engel yok, 64-hex digest', true === $f7_rbp['ok'] && 6 === $f7_rbp['items_pending'] && array() === $f7_rbp['blockers'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $f7_rbp['rollback_digest'] ) );
$E7a->world->writeLog = array();
$f7_rbr = $E7a->rollback->rollback( $f7_uid, $f7_rbp['rollback_digest'], null );
mb_test( 'Faz 7 rollback: rolled_back, 6 item; yazmalar reference SONRA news sırasında (ters bağımlılık)',
	true === $f7_rbr['ok'] && 'rolled_back' === $f7_rbr['status'] && 6 === $f7_rbr['rolled_back_items'] && 'rolled_back' === $E7a->store->get_run( $f7_uid )['status']
	&& (function () use ( $E7a, $f7_id_a, $f7_id_b, $f7_id_ra ) {
		$ids = array_map( function ( $l ) {
			return (int) substr( $l, strlen( 'trash_post:' ) );
		}, $E7a->world->writeLog );
		$types = array_map( function ( $id ) use ( $E7a ) {
			return $E7a->world->posts[ $id ]['post_type'];
		}, $ids );
		return 6 === count( $ids ) && array( 'mb_referans', 'mb_referans', 'mb_referans', 'mb_haber', 'mb_haber', 'mb_haber' ) === $types;
	})() );
mb_test( 'Faz 7 rollback: postlar ÇÖPE gider (kalıcı silme yok), marker/hash/yönetilen meta ve haber türü ilişkisi temizlenir, post_name doğal anahtarı boşalır',
	3 === count( f7ic_posts( $E7a, 'mb_haber', 'trash' ) ) && 3 === count( f7ic_posts( $E7a, 'mb_referans', 'trash' ) ) && 6 === count( $E7a->world->posts )
	&& (function () use ( $E7a ) {
		foreach ( $E7a->world->posts as $p ) {
			if ( array() !== $p['meta'] || '' !== $p['name'] || array() !== $p['terms'] ) {
				return false;
			}
		}
		return true;
	})() );
$f7_re = $E7a->apply->preview( 'content' );
mb_test( 'Faz 7 rollback sonrası: aynı manifest yeniden 6 create, applicable (doğal anahtar none, conflict=0); yeniden apply çalışır, 6 YENİ post',
	6 === $f7_re['summary']['operations']['create'] && 0 === $f7_re['summary']['operations']['conflict'] && true === $f7_re['summary']['applicable'] && 'completed' === f7ic_apply( $E7a )['status'] && 3 === count( f7ic_posts( $E7a, 'mb_haber', 'draft' ) ) && 12 === count( $E7a->world->posts ) );

// Güncelleme: içerik ve tür değişimi; referans sıralama değişimi.
$f7_v2 = mb6b2_temp_dir( 'f7v2' );
$f7_env2 = mb_content_fixture_envelopes( array( 'news' => array( 'news:zz-test-haber-a' => array( 'body' => 'Güncellenmiş sahte gövde.', 'summary' => 'Güncellenmiş özet.' ), 'news:zz-test-haber-c' => array( 'news_type' => 'duyuru', 'published_on' => '2026-03-01' ) ) ) );
$f7_swap = $f7_env2['reference']['records'];
$f7_swap = array( $f7_swap[1], $f7_swap[0], $f7_swap[2] );
foreach ( $f7_swap as $i => $r ) {
	$f7_swap[ $i ]['source_index'] = $i;
}
$f7_env2['reference']['records'] = $f7_swap;
mb_content_fixture_write_dir( $f7_v2, $f7_env2 );
$E7b = f7ic_env( $f7_dir_c );
f7ic_apply( $E7b );
$E7b->dryRun = new MaviBelge_Core_Import_Dry_Run_Service( $E7b->repo, $f7_v2 );
$E7b->apply  = new MaviBelge_Core_Import_Apply_Service( $E7b->dryRun, $E7b->writer, $E7b->tx, $E7b->store, $E7b->audit );
$f7_up_p = $E7b->apply->preview( 'content' );
$f7_up_entries = array();
foreach ( $f7_up_p['plan']['entries'] as $e ) {
	$f7_up_entries[ $e['source_key'] ] = $e;
}
mb_test( 'Faz 7 güncelleme planı: haber-a update(content+excerpt), haber-c update(published_on+news_type_term_id), referans a/b update(sort_order), diğerleri unchanged; changed_fields yalnız ad',
	'update' === $f7_up_entries['news:zz-test-haber-a']['decision'] && array( 'content', 'excerpt' ) === $f7_up_entries['news:zz-test-haber-a']['changed_fields'] && 'update' === $f7_up_entries['news:zz-test-haber-c']['decision'] && array( 'published_on', 'news_type_term_id' ) === $f7_up_entries['news:zz-test-haber-c']['changed_fields']
	&& 'unchanged' === $f7_up_entries['news:zz-test-haber-b']['decision'] && 'update' === $f7_up_entries['reference:zz-test-ref-a']['decision'] && array( 'sort_order' ) === $f7_up_entries['reference:zz-test-ref-a']['changed_fields'] && 'update' === $f7_up_entries['reference:zz-test-ref-b']['decision'] && 'unchanged' === $f7_up_entries['reference:zz-test-ref-c']['decision']
	&& true === $f7_up_p['eligible'] && 4 === $f7_up_p['writes'] && false === strpos( json_encode( $f7_up_p['plan'] ), 'Güncellenmiş sahte gövde' ) );
list( $f7_id_ua, $f7_p_ua_before ) = f7ic_by_key( $E7b, 'mb_haber', 'news:zz-test-haber-a' );
list( $f7_id_uc, $f7_p_uc_before ) = f7ic_by_key( $E7b, 'mb_haber', 'news:zz-test-haber-c' );
$f7_up_r = $E7b->apply->apply( 'content', $f7_up_p['plan_digest'], null, 1 );
mb_test( 'Faz 7 güncelleme apply: completed; aynı post ID\'leri güncellenir (yeni post YOK), post_status DEĞİŞMEZ (draft), yalnız yönetilen alanlar; tür terimi değişti; readback unchanged',
	true === $f7_up_r['ok'] && 'completed' === $f7_up_r['status'] && 4 === $f7_up_r['committed_items'] && 6 === count( $E7b->world->posts ) && 'Güncellenmiş sahte gövde.' === $E7b->world->posts[ $f7_id_ua ]['content'] && 'draft' === $E7b->world->posts[ $f7_id_ua ]['status'] && 'draft' === $E7b->world->posts[ $f7_id_uc ]['status']
	&& array( 502 ) === $E7b->world->posts[ $f7_id_uc ]['terms']['mb_haber_turu'] && '2026-03-01 12:00:00' === $E7b->world->posts[ $f7_id_uc ]['date'] && 'ZZ Test Ref A' === f7ic_by_key( $E7b, 'mb_referans', 'reference:zz-test-ref-a' )[1]['title'] && '2' === f7ic_by_key( $E7b, 'mb_referans', 'reference:zz-test-ref-a' )[1]['meta']['_mb_sort_order']
	&& 6 === $E7b->apply->preview( 'content' )['summary']['operations']['unchanged'] );
$f7_rb_up = f7ic_rollback( $E7b, $f7_up_r['run_uid'] );
mb_test( 'Faz 7 güncelleme rollback: eski yönetilen alanlar geri yazıldı (gövde, özet, tarih, tür terimi, sıralama); yönetilmeyen alana dokunulmadı; yeniden planlama v1 manifestine karşı unchanged',
	true === $f7_rb_up['ok'] && 'rolled_back' === $f7_rb_up['status'] && $f7_news[0]['body'] === $E7b->world->posts[ $f7_id_ua ]['content'] && array( 501 ) === $E7b->world->posts[ $f7_id_uc ]['terms']['mb_haber_turu'] && '2025-12-31 12:00:00' === $E7b->world->posts[ $f7_id_uc ]['date'] && 'draft' === $E7b->world->posts[ $f7_id_ua ]['status']
	&& '1' === f7ic_by_key( $E7b, 'mb_referans', 'reference:zz-test-ref-a' )[1]['meta']['_mb_sort_order'] && 6 === count( $E7b->world->posts ) );

/* --- N) çakışma, kirli hedef ve reddedilen apply'lar --- */
$E7c = f7ic_env( $f7_dir_c, false );
$E7cp = $E7c->apply->preview( 'content' );
mb_test( 'Faz 7 reddedilen apply: iki kontrollü tür terimi YOKKEN haberler blocked_dependency (3), referanslar create; applicable=false; apply plan_not_applicable; dünya dokunulmadı',
	3 === $E7cp['summary']['operations']['blocked'] && 3 === $E7cp['summary']['operations']['create'] && false === $E7cp['eligible'] && 'plan_not_applicable' === $E7c->apply->apply( 'content', (string) $E7cp['plan_digest'], null, 1 )['error_code'] && $f7_untouched( $E7c ) );
$f7_seed_one = f7ic_env( $f7_dir_c, false );
$f7_seed_one->world->terms[501] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'haber', 'name' => 'Haber', 'description' => '', 'parent' => 0, 'meta' => array() );
$f7_one_p = $f7_seed_one->apply->preview( 'content' );
mb_test( 'Faz 7 reddedilen apply: yalnız "haber" terimi varken "duyuru" haberi blocked (kısmi plan uygulanmaz)',
	1 === $f7_one_p['summary']['operations']['blocked'] && false === $f7_one_p['eligible'] && 'plan_not_applicable' === $f7_seed_one->apply->apply( 'content', (string) $f7_one_p['plan_digest'], null, 1 )['error_code'] && $f7_untouched( $f7_seed_one ) );
$f7_wrongtax = f7ic_env( $f7_dir_c, false );
$f7_wrongtax->world->terms[501] = array( 'taxonomy' => 'mb_sektor', 'slug' => 'haber', 'name' => 'Sahte', 'description' => '', 'parent' => 0, 'meta' => array() );
mb_test( 'Faz 7 bağımlılık türü: slug "haber" olan ama mb_haber_turu OLMAYAN terim çözülmüş sayılmaz', 3 === $f7_wrongtax->apply->preview( 'content' )['summary']['operations']['blocked'] );

$f7_kirli = function ( $type, $slug, array $extra = array() ) use ( $f7_dir_c ) {
	$env = f7ic_env( $f7_dir_c );
	// Gerçek WordPress çöpteki postun adını `<slug>__trashed` yapar ve asıl slug'ı `_wp_desired_post_slug`ta saklar.
	$env->world->posts[900] = array_merge( array( 'post_type' => $type, 'title' => 'Elle oluşturuldu', 'status' => 'trash', 'content' => 'kullanıcı içeriği', 'name' => $slug . '__trashed', 'desired_slug' => $slug, 'excerpt' => '', 'date' => '', 'extra' => array(), 'meta' => array(), 'terms' => array() ), $extra );
	return $env;
};
$E7d = $f7_kirli( 'mb_haber', 'zz-test-haber-b' );
$E7dp = $E7d->apply->preview( 'content' );
mb_test( 'Faz 7 çakışma: çöpteki kullanıcı haberi aynı post_name\'i tutuyor (marker yok) -> conflict (unmanaged doğal anahtar); BÜTÜN run reddedilir, hiçbir şey yazılmaz',
	1 === $E7dp['summary']['operations']['conflict'] && false === $E7dp['eligible'] && 'plan_not_applicable' === $E7d->apply->apply( 'content', (string) $E7dp['plan_digest'], null, 1 )['error_code'] && array() === $E7d->world->writeLog && array() === $E7d->world->runs && 1 === count( $E7d->world->posts ) );
$E7e = $f7_kirli( 'mb_referans', 'zz-test-ref-c', array( 'status' => 'publish', 'name' => 'zz-test-ref-c', 'desired_slug' => null ) );
mb_test( 'Faz 7 çakışma: yayında kullanıcı referansı aynı post_name\'i tutuyor -> conflict, apply reddedilir', 1 === $E7e->apply->preview( 'content' )['summary']['operations']['conflict'] && false === $E7e->apply->preview( 'content' )['eligible'] );
$E7dd = f7ic_env( $f7_dir_c );
$E7dd->world->posts[904] = array( 'post_type' => 'mb_haber', 'title' => 'Import kabuğu', 'status' => 'trash', 'content' => '', 'name' => '', 'excerpt' => '', 'date' => '', 'extra' => array(), 'meta' => array(), 'terms' => array() );
$E7dd->world->posts[905] = array( 'post_type' => 'mb_referans', 'title' => 'Başka kabuk', 'status' => 'trash', 'content' => '', 'name' => 'zz-test-ref-a__trashed', 'desired_slug' => '', 'excerpt' => '', 'date' => '', 'extra' => array(), 'meta' => array(), 'terms' => array() );
mb_test( 'Faz 7 çakışma: importun kendi rollback kabuğu (post_name boş / "__trashed" + boş istenen slug) doğal anahtarı TUTMAZ -> 6 create, applicable',
	6 === $E7dd->apply->preview( 'content' )['summary']['operations']['create'] && true === $E7dd->apply->preview( 'content' )['eligible'] );
$E7f = $f7_kirli( 'mb_haber', 'zz-test-haber-a', array( 'meta' => array( '_mb_import_source_key' => 'news:zz-test-haber-x' ) ) );
mb_test( 'Faz 7 çakışma: post_name aynı ama marker BAŞKA source_key\'e ait (foreign_marker) -> conflict', 1 === $E7f->apply->preview( 'content' )['summary']['operations']['conflict'] && false === $E7f->apply->preview( 'content' )['eligible'] );
$E7g = $f7_kirli( 'mb_haber', 'baska-bir-haber', array( 'status' => 'draft', 'meta' => array( '_mb_import_source_key' => 'reference:zz-test-ref-a' ) ) );
$E7gp = $E7g->apply->preview( 'content' );
mb_test( 'Faz 7 çakışma: reference marker\'ı bir mb_haber postunda -> reference:zz-test-ref-a conflict_wrong_target_type (tür uyuşmazlığı); apply reddedilir',
	1 === $E7gp['summary']['by_decision']['conflict_wrong_target_type'] && false === $E7gp['eligible'] && 'plan_not_applicable' === $E7g->apply->apply( 'content', (string) $E7gp['plan_digest'], null, 1 )['error_code'] );
$E7h = f7ic_env( $f7_dir_c );
$E7h->world->posts[901] = array( 'post_type' => 'mb_haber', 'title' => 'A', 'status' => 'draft', 'content' => '', 'name' => 'x', 'excerpt' => '', 'date' => '', 'extra' => array(), 'meta' => array( '_mb_import_source_key' => 'news:zz-test-haber-a' ), 'terms' => array() );
$E7h->world->posts[902] = array( 'post_type' => 'mb_haber', 'title' => 'B', 'status' => 'draft', 'content' => '', 'name' => 'y', 'excerpt' => '', 'date' => '', 'extra' => array(), 'meta' => array( '_mb_import_source_key' => 'news:zz-test-haber-a' ), 'terms' => array() );
mb_test( 'Faz 7 çakışma: aynı marker iki haberde (duplicate) -> conflict_duplicate_target', 1 === $E7h->apply->preview( 'content' )['summary']['by_decision']['conflict_duplicate_target'] && false === $E7h->apply->preview( 'content' )['eligible'] );
mb_test( 'Faz 7 dry-run servisi: repository çözümleyici arayüzünü uygulamıyorsa haber türü çözülmez (news blocked_dependency); varsayılan (uygulayan) depoda çözülür',
	(function () use ( $f7_dir_c ) {
		$w = new MB_Fake_World();
		f7ic_seed( $w );
		$plain = new MB_Fake_Plain_Repository( new MB_Fake_World_Repository( $w ) );
		$svc   = new MaviBelge_Core_Import_Dry_Run_Service( $plain, $f7_dir_c );
		$run   = $svc->run_stage( 'content' );
		return true === $run['ok'] && 3 === $run['plan']['summary']['operations']['blocked'] && 3 === $run['plan']['summary']['operations']['create'];
	})() );

/* --- O) aşama yalıtımı: katalog + içerik aynı dizinde --- */
$E7i  = f7ic_env( $f7_dir_a );
$E7ia = $E7i->apply->preview( 'all' );
$E7ic = $E7i->apply->preview( 'content' );
mb_test( 'Faz 7 aşama yalıtımı: aynı dizinde katalog+içerik dosyaları varken all aşaması yalnız katalog (11 kayıt; by_type üç anahtar), content yalnız içerik (6 kayıt)',
	11 === $E7ia['summary']['total'] && array( 'sector', 'qualification', 'fee' ) === array_keys( $E7ia['summary']['by_type'] ) && 6 === $E7ic['summary']['total'] && array( 'sector', 'qualification', 'fee', 'news', 'reference' ) === array_keys( $E7ic['summary']['by_type'] ) && $E7ia['manifest_digest'] !== $E7ic['manifest_digest'] );
mb_test( 'Faz 7 aşama yalıtımı: content apply katalog tablolarına/terimlerine dokunmaz; sonra sectors aşaması bağımsız uygulanır (sektör terimleri haber türü terimlerinden ayrı sayılır)',
	'completed' === f7ic_apply( $E7i )['status'] && 0 === count( f7ic_posts( $E7i, 'mb_yeterlilik' ) ) && 'completed' === f7ic_apply( $E7i, 'sectors' )['status'] && 5 === count( $E7i->world->terms ) && 6 === count( $E7i->world->posts ) && 3 === $E7i->apply->preview( 'sectors' )['summary']['operations']['unchanged'] );
$E7j = f7ic_env( $f7_dir_k );
$E7jc = $E7j->apply->preview( 'content' );
mb_test( 'Faz 7 aşama yalıtımı: içerik dosyaları YOKKEN katalog aşamaları çalışır (sectors 3 create), content aşaması yükleme hatasıyla reddedilir, dünya dokunulmadı',
	3 === $E7j->apply->preview( 'sectors' )['summary']['operations']['create'] && false === $E7jc['ok'] && false === $E7jc['eligible'] && ! empty( $E7jc['errors'] ) && 'plan_not_applicable' === $E7j->apply->apply( 'content', str_repeat( 'a', 64 ), null, 1 )['error_code'] && $f7_untouched( $E7j ) );

/* --- P) drift koruması: yönetilmeyen düzenleme rollback'i engeller --- */
$f7_drift = function ( callable $mutate, $stage = 'content' ) use ( $f7_dir_c ) {
	$env = f7ic_env( $f7_dir_c );
	$run = f7ic_apply( $env );
	list( $id ) = f7ic_by_key( $env, 'mb_haber', 'news:zz-test-haber-a' );
	list( $rid ) = f7ic_by_key( $env, 'mb_referans', 'reference:zz-test-ref-a' );
	$mutate( $env->world, $id, $rid );
	$env->world->writeLog = array();
	$res = f7ic_rollback( $env, $run['run_uid'] );
	return array( $env, $res, $id, $rid );
};
$f7_no_trash = function ( $env ) {
	return array() === f7ic_posts( $env, 'mb_haber', 'trash' ) && array() === f7ic_posts( $env, 'mb_referans', 'trash' ) && array() === $env->world->writeLog;
};
list( $Ed1, $Ed1r ) = $f7_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['meta']['_yoast_wpseo_title'] = 'SEO başlığı';
	}
);
mb_test( 'Faz 7 drift: haberdeki yönetilmeyen SEO meta rollback\'i ENGELLER (drift_detected); hiçbir post çöpe gitmez, hiçbir şey yazılmaz; run rollback_failed', false === $Ed1r['ok'] && 'drift_detected' === $Ed1r['error_code'] && $f7_no_trash( $Ed1 ) && 'rollback_failed' === $Ed1->store->get_run( $Ed1r['run_uid'] )['status'] );
list( $Ed2, $Ed2r ) = $f7_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['terms']['category'] = array( 3 );
	}
);
mb_test( 'Faz 7 drift: haberdeki başka taksonomi (yönetilmeyen) ilişkisi rollback\'i engeller; mb_haber_turu ise YÖNETİLEN (yeniden apply\'ı/rollback\'i drift saymaz)', 'drift_detected' === $Ed2r['error_code'] && $f7_no_trash( $Ed2 ) );
list( $Ed3, $Ed3r ) = $f7_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['extra']['menu_order'] = 7;
	}
);
mb_test( 'Faz 7 drift: menu_order gibi yönetilmeyen çekirdek alan rollback\'i engeller', 'drift_detected' === $Ed3r['error_code'] && $f7_no_trash( $Ed3 ) );
list( $Ed4, $Ed4r ) = $f7_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['status'] = 'publish';
	}
);
mb_test( 'Faz 7 drift: haber yayınlanmış/durumu değişmiş -> rollback engellenir (kullanıcı kararı korunur)', 'drift_detected' === $Ed4r['error_code'] && $f7_no_trash( $Ed4 ) );
list( $Ed5, $Ed5r, $Ed5id ) = $f7_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['content'] = 'Editör bu haberi yeniden yazdı.';
	}
);
mb_test( 'Faz 7 drift: YÖNETİLEN alan (post_content) düzenlenmiş -> hash uyuşmazlığı drift_detected; içerik editör metninde kalır, çöpe gitmez',
	'drift_detected' === $Ed5r['error_code'] && $f7_no_trash( $Ed5 ) && 'Editör bu haberi yeniden yazdı.' === $Ed5->world->posts[ $Ed5id ]['content'] );
$f7_pl_after = $Ed5->apply->preview( 'content' );
$f7_ent = array();
foreach ( $f7_pl_after['plan']['entries'] as $e ) {
	$f7_ent[ $e['source_key'] ] = $e;
}
mb_test( 'Faz 7 drift: aynı düzenleme sonrası yeniden planlama conflict (manual_edit_detected); import editör içeriğini ezmez', 'conflict' === $f7_ent['news:zz-test-haber-a']['decision'] && 'manual_edit_detected' === $f7_ent['news:zz-test-haber-a']['reason'] && false === $f7_pl_after['eligible'] );
list( $Ed6, $Ed6r ) = $f7_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['meta']['_mb_approval_status'] = 'approved';
	}
);
mb_test( 'Faz 7 drift: editör haberi onaylamış -> rollback engellenir (drift_detected)', 'drift_detected' === $Ed6r['error_code'] && $f7_no_trash( $Ed6 ) );
list( $Ed7, $Ed7r ) = $f7_drift(
	function ( $w, $id, $rid ) {
		$w->posts[ $rid ]['meta']['_mb_record_status'] = 'passive';
	}
);
mb_test( 'Faz 7 drift: editör referansı pasife almış -> rollback engellenir; hiçbir haber/referans çöpe gitmez (kısmi rollback YOK)', 'drift_detected' === $Ed7r['error_code'] && $f7_no_trash( $Ed7 ) );
list( $Ed8, $Ed8r ) = $f7_drift(
	function ( $w, $id, $rid ) {
		$w->posts[ $rid ]['meta']['_mb_seo_noindex'] = '1';
		$w->posts[ $rid ]['content'] = 'Referansa elle eklenen içerik';
	}
);
mb_test( 'Faz 7 drift: referansta yönetilmeyen meta veya post_content (referans için yönetilmez) rollback\'i engeller', 'drift_detected' === $Ed8r['error_code'] && $f7_no_trash( $Ed8 ) );
list( $Ed9, $Ed9r ) = $f7_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['name'] = 'editor-degisti';
	}
);
mb_test( 'Faz 7 drift: haber post_name (yönetilen) değişmiş -> hash uyuşmazlığı, rollback engellenir', 'drift_detected' === $Ed9r['error_code'] && $f7_no_trash( $Ed9 ) );
list( $Ed10, $Ed10r ) = $f7_drift( function ( $w, $id ) {} );
mb_test( 'Faz 7 drift kontrolü (negatif kontrol): değişiklik yoksa temiz rollback çalışır (3 haber + 3 referans çöpte)', true === $Ed10r['ok'] && 3 === count( f7ic_posts( $Ed10, 'mb_haber', 'trash' ) ) && 3 === count( f7ic_posts( $Ed10, 'mb_referans', 'trash' ) ) );
list( $Ed11, $Ed11r ) = $f7_drift(
	function ( $w, $id ) {
		$w->terms[501]['name'] = 'Editör adı değiştirdi'; // Tür teriminin kendisi: haber içeriği değil.
	}
);
mb_test( 'Faz 7 drift: mb_haber_turu teriminin kendi adı değişse de rollback etkilenmez (terim import\'un değil)', true === $Ed11r['ok'] );
// Drift blokajı sonrası aynı run ancak düzeltme sonrası geri alınabilir.
$Ed1->world->posts[ f7ic_by_key( $Ed1, 'mb_haber', 'news:zz-test-haber-a' )[0] ]['meta'] = array_diff_key( $Ed1->world->posts[ f7ic_by_key( $Ed1, 'mb_haber', 'news:zz-test-haber-a' )[0] ]['meta'], array( '_yoast_wpseo_title' => 1 ) );
$f7_retry = $Ed1->rollback->preview( $Ed1r['run_uid'] );
mb_test( 'Faz 7 drift: SEO meta kullanıcı tarafından kaldırıldıktan sonra aynı run temiz geri alınabilir (rollback_failed run yeniden denenebilir)',
	true === $f7_retry['ok'] && array() === $f7_retry['blockers'] && true === $Ed1->rollback->rollback( $Ed1r['run_uid'], $f7_retry['rollback_digest'], null )['ok'] );

/* --- Q) atomik finalizer ve batch atomikliği content aşamasında --- */
$E7k = f7ic_env( $f7_dir_c );
$E7k->audit->failEvents = array( 'import_run_completed' );
$E7kr = f7ic_apply( $E7k );
mb_test( 'Faz 7 atomiklik: run_completed audit\'i yazılamazsa content run\'ı rollback_required (running KALMAZ), run_failed(finalization_failed) atomik, 6 post commit edilmiş',
	false === $E7kr['ok'] && 'finalization_failed' === $E7kr['error_code'] && 'rollback_required' === $E7kr['status'] && 'rollback_required' === $E7k->store->get_run( $E7kr['run_uid'] )['status'] && 'import_run_failed' === end( $E7k->world->audit )['event'] && 6 === count( $E7k->world->posts ) );
$f7_kr = f7ic_rollback( $E7k, $E7kr['run_uid'] );
mb_test( 'Faz 7 atomiklik: rollback_required content run\'ı doğrulanmış kayıtlarla temiz geri alınır (6 çöp, rolled_back)', true === $f7_kr['ok'] && 'rolled_back' === $f7_kr['status'] && 3 === count( f7ic_posts( $E7k, 'mb_haber', 'trash' ) ) && 3 === count( f7ic_posts( $E7k, 'mb_referans', 'trash' ) ) );
$E7l = f7ic_env( $f7_dir_c );
$E7l->audit->failEvents = array( 'import_run_completed', 'import_run_failed' );
$E7lr = f7ic_apply( $E7l );
mb_test( 'Faz 7 atomiklik: hem completed hem failed audit yazılamazsa yalnız durum güvenle rollback_required', 'rollback_required' === $E7lr['status'] && 'rollback_required' === $E7l->store->get_run( $E7lr['run_uid'] )['status'] && false === in_array( 'import_run_completed', array_map( function ( $a ) {
	return $a['event'];
}, $E7l->world->audit ), true ) );
$E7m = f7ic_env( $f7_dir_c );
$E7m->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'news:zz-test-haber-c' ) );
$E7mr = f7ic_apply( $E7m, 'content', 2 );
$E7mrun = $E7m->store->get_run( $E7mr['run_uid'] );
mb_test( 'Faz 7 batch atomikliği: 2. batch\'te yazma hatası -> 2. batch TAMAMEN geri alınır (yarım haber/referans kalmaz), 1. batch korunur, run rollback_required',
	false === $E7mr['ok'] && 'write_failed' === $E7mr['error_code'] && 'rollback_required' === $E7mrun['status'] && 1 === $E7mrun['committed_batches'] && 2 === $E7mrun['committed_items'] && 2 === count( f7ic_posts( $E7m, 'mb_haber' ) ) && 0 === count( f7ic_posts( $E7m, 'mb_referans' ) ) && 2 === count( $E7m->store->get_items( $E7mrun['id'] ) ) );
$f7_m_rb = f7ic_rollback( $E7m, $E7mr['run_uid'] );
mb_test( 'Faz 7 batch atomikliği: yarım run\'ın yalnız commit edilen 2 item\'ı geri alınır', true === $f7_m_rb['ok'] && 2 === $f7_m_rb['rolled_back_items'] && 2 === count( f7ic_posts( $E7m, 'mb_haber', 'trash' ) ) );
$E7n = f7ic_env( $f7_dir_c );
$E7n->world->faults = array( array( 'op' => 'corrupt_meta', 'source_key' => 'reference:zz-test-ref-b' ) );
$E7nr = f7ic_apply( $E7n, 'content', 20 );
mb_test( 'Faz 7 readback: yazılan referansın meta değeri bozulursa readback_mismatch -> BÜTÜN batch geri alınır, run failed (hiçbir post kalmaz)',
	false === $E7nr['ok'] && 'readback_mismatch' === $E7nr['error_code'] && 'failed' === $E7n->store->get_run( $E7nr['run_uid'] )['status'] && 0 === count( $E7n->world->posts ) );
$E7o = f7ic_env( $f7_dir_c );
$E7op = $E7o->apply->preview( 'content' );
$E7o->world->posts[903] = array( 'post_type' => 'mb_haber', 'title' => 'Arada eklendi', 'status' => 'draft', 'content' => '', 'name' => 'zz-test-haber-c', 'excerpt' => '', 'date' => '', 'extra' => array(), 'meta' => array(), 'terms' => array() );
$E7or = $E7o->apply->apply( 'content', $E7op['plan_digest'], null, 1 );
mb_test( 'Faz 7 TOCTOU/onay: plan sonrası beliren doğal anahtar (aynı slug) onay digest\'ini geçersiz kılar; hiçbir yazma yapılmaz', false === $E7or['ok'] && in_array( $E7or['error_code'], array( 'confirmation_mismatch', 'plan_not_applicable' ), true ) && array() === $E7o->world->writeLog && array() === $E7o->world->runs );
mb_test( 'Faz 7 onay digest\'i: önizlenen digest ile başka aşamanın digest\'i değiştirilemez (content digest\'i sectors aşamasında reddedilir)',
	'confirmation_mismatch' === $E7i->apply->apply( 'sectors', $E7ic['plan_digest'], null, 1 )['error_code'] || 'plan_not_applicable' === $E7i->apply->apply( 'sectors', $E7ic['plan_digest'], null, 1 )['error_code'] );

/* --- R) CLI/yardım metni sözleşmesi (kaynak taraması) --- */
$f7_cli = file_get_contents( dirname( __DIR__, 2 ) . '/includes/import/class-import-cli-command.php' );
mb_test( 'Faz 7 CLI: --stage seçeneklerinde content var ve yardım metni içerik aşamasını açıklar; kararı bu sınıf yazmaz (Apply_Plan::STAGES kullanılır)',
	false !== strpos( $f7_cli, '- content' ) && false !== strpos( $f7_cli, 'content =' ) && false !== strpos( $f7_cli, 'MaviBelge_Core_Import_Apply_Plan::ALL_STAGES' ) && false !== strpos( $f7_cli, '--stage=content' ) );
