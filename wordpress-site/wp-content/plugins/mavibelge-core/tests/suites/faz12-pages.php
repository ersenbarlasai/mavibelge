<?php
/**
 * Faz 12 — `page` (WordPress çekirdek sayfa türü) içe aktarımı: 32 sayfa, `pages` aşaması.
 *
 * Sahte veri YOKTUR: gerçek `data/content/pages.manifest.json` (32 kayıt) sahte, bellek içi WordPress dünyasına
 * (tests/support/import-apply-fakes.php) uygulanır; gerçek WordPress davranışı tools/runtime-test/ ile ayrıca sınanır.
 * Bu paket YALNIZ motor sözleşmesini sınar: doğrulayıcı, kapalı HTML izin listesi, yükleyici, plan, apply/rollback/reapply,
 * drift koruması, işaretçi/doğal anahtar hataları, batch/audit/checkpoint atomikliği ve aşama sırası.
 */

$F12_RV = 'MaviBelge_Core_Import_Record_Validator';
$F12_V  = 'MaviBelge_Core_Validator';
$F12_MF = 'MaviBelge_Core_Import_Managed_Fields';
$F12_PL = 'MaviBelge_Core_Import_Dry_Run_Planner';
$F12_AP = 'MaviBelge_Core_Import_Apply_Plan';
$F12_WP = 'MaviBelge_Core_Import_Write_Payload';
$F12_RP = 'MaviBelge_Core_Import_WordPress_Target_Repository';
$F12_ML = 'MaviBelge_Core_Import_Manifest_Loader';

$f12_manifest_file = dirname( __DIR__, 5 ) . '/data/content/pages.manifest.json';
$f12_real          = json_decode( (string) file_get_contents( $f12_manifest_file ), true );
$f12_records       = $f12_real['records'];

/** Zarf source.sha256'ı kayıt kaynaklarından yeniden hesaplar (Node inventoryDigest ile AYNI kural). */
function f12_seal( array $env ) {
	$lines = '';
	foreach ( $env['records'] as $r ) {
		$lines .= $r['slug'] . ':' . $r['source']['sha256'] . "\n";
	}
	$env['source']['sha256'] = hash( 'sha256', $lines );
	$env['count']            = count( $env['records'] );
	return $env;
}
/** Manifest dizini: gerçek manifestin (isteğe bağlı değiştirilmiş) kopyası. */
function f12_dir( $f12_real, callable $mutate = null, $seal = true, $label = 'f12' ) {
	$env = $f12_real;
	if ( null !== $mutate ) {
		$env = $mutate( $env );
	}
	if ( $seal ) {
		$env = f12_seal( $env );
	}
	$dir = mb6b2_temp_dir( $label );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true );
	}
	file_put_contents( $dir . '/pages.manifest.json', json_encode( $env, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
	return $dir;
}
function f12_env( $dir ) {
	return mb_fake_apply_env( $dir );
}
function f12_apply( $env, $batch = null ) {
	$p = $env->apply->preview( 'pages' );
	return $env->apply->apply( 'pages', (string) $p['plan_digest'], $batch, 1 );
}
function f12_rollback( $env, $uid ) {
	$pv = $env->rollback->preview( $uid );
	return $env->rollback->rollback( $uid, (string) $pv['rollback_digest'], null );
}
function f12_pages( $env, $status = null ) {
	$out = array();
	foreach ( $env->world->posts as $id => $p ) {
		if ( 'page' === $p['post_type'] && ( null === $status || $p['status'] === $status ) ) {
			$out[ $id ] = $p;
		}
	}
	return $out;
}
function f12_by_slug( $env, $slug ) {
	foreach ( f12_pages( $env ) as $id => $p ) {
		if ( isset( $p['meta']['_mb_import_source_key'] ) && 'page:' . $slug === $p['meta']['_mb_import_source_key'] ) {
			return array( $id, $p );
		}
	}
	return array( null, null );
}
function f12_entries( array $plan ) {
	$out = array();
	foreach ( $plan['entries'] as $e ) {
		$out[ $e['source_key'] ] = $e;
	}
	return $out;
}

$f12_dir_real = f12_dir( $f12_real );

/* ================================================================ A) marker ailesi ve doğal anahtar */
mb_test( 'Faz 12 source_key: page:<slug> yalnız kendi ailesinde geçerli; başka aileler ve biçim hataları ret',
	$F12_V::IMPORT_KEY_VALID === $F12_V::classify_import_source_key( 'page:hakkimizda', 'page' ) && $F12_V::IMPORT_KEY_WRONG_PREFIX === $F12_V::classify_import_source_key( 'page:hakkimizda', 'news' )
	&& $F12_V::IMPORT_KEY_WRONG_PREFIX === $F12_V::classify_import_source_key( 'news:hakkimizda', 'page' ) && $F12_V::IMPORT_KEY_MALFORMED === $F12_V::classify_import_source_key( 'page:Hakkimizda', 'page' )
	&& $F12_V::IMPORT_KEY_MALFORMED === $F12_V::classify_import_source_key( "page:a\n", 'page' ) && $F12_V::IMPORT_KEY_MALFORMED === $F12_V::classify_import_source_key( 'page:', 'page' ) && $F12_V::IMPORT_KEY_NOT_STRING === $F12_V::classify_import_source_key( array( 'page:a' ), 'page' )
	&& $F12_V::IMPORT_KEY_EMPTY === $F12_V::classify_import_source_key( '', 'page' ) );
mb_test( 'Faz 12 doğal anahtar: page:<slug> -> {slug}; geçersiz/yanlış aile null; marker durumları tek sınıflandırıcıdan',
	array( 'slug' => 'hakkimizda' ) === $F12_RP::natural_key_from_source_key( 'page', 'page:hakkimizda' ) && null === $F12_RP::natural_key_from_source_key( 'page', 'news:hakkimizda' ) && null === $F12_RP::natural_key_from_source_key( 'page', 'page:X' )
	&& 'unmanaged' === $F12_RP::natural_key_state_from_marker( '', 'page', 'page:a' ) && 'foreign_marker' === $F12_RP::natural_key_state_from_marker( 'page:b', 'page', 'page:a' ) && 'wrong_marker_prefix' === $F12_RP::natural_key_state_from_marker( 'news:a', 'page', 'page:a' )
	&& 'corrupt_marker' === $F12_RP::natural_key_state_from_marker( array( 'page:a' ), 'page', 'page:a' ) && 'undiscovered_marker' === $F12_RP::natural_key_state_from_marker( 'page:a', 'page', 'page:a' ) );
mb_test( 'Faz 12 meta şeması: çekirdek `page` YALNIZ iki sistem alanı taşır (yeni post type yok); düzenlenebilir alan yok (meta kutusu çizilmez)',
	array( '_mb_import_source_key', '_mb_last_applied_hash' ) === array_keys( MaviBelge_Core_Meta_Schema::get_fields_for( 'page' ) ) && false === MaviBelge_Core_Meta_Schema::has_editable_fields( 'page' ) && true === MaviBelge_Core_Meta_Schema::has_editable_fields( 'mb_haber' )
	&& ! array_key_exists( 'mb_sayfa', MaviBelge_Core_Meta_Schema::get_schema() ) && array_key_exists( 'page', MaviBelge_Core_Meta_Schema::get_schema() ) );

/* ================================================================ B) kayıt doğrulayıcı */
$f12_all_valid = true;
foreach ( $f12_records as $r ) {
	$f12_all_valid = $f12_all_valid && true === $F12_RV::validate_page( $r )['valid'];
}
mb_test( 'Faz 12 doğrulayıcı: gerçek manifestin 32 kaydının HEPSİ geçerli; sayı tam 32', 32 === count( $f12_records ) && true === $f12_all_valid && 32 === $F12_ML::PAGE_EXPECTED_COUNT );
$f12_vp = function ( array $patch, $drop = array() ) use ( $F12_RV, $f12_records ) {
	$r = array_merge( $f12_records[3], $patch );
	foreach ( $drop as $k ) {
		unset( $r[ $k ] );
	}
	return $F12_RV::validate_page( $r )['valid'];
};
mb_test( 'Faz 12 doğrulayıcı: dizi olmayan, bilinmeyen alan, her zorunlu alanın eksikliği -> ret',
	false === $F12_RV::validate_page( 'x' )['valid'] && false === $f12_vp( array( 'ekstra' => 1 ) ) && false === $f12_vp( array( 'post_type' => 'page' ) )
	&& (function () use ( $f12_vp, $F12_RV ) {
		foreach ( $F12_RV::PAGE_SCHEMA_KEYS as $key ) {
			if ( true === $f12_vp( array(), array( $key ) ) ) {
				return false;
			}
		}
		return true;
	})() );
mb_test( 'Faz 12 doğrulayıcı: yanlış tipler (int başlık, dizi içerik, string menu_order, float source_index, string publish_hold) -> ret',
	false === $f12_vp( array( 'title' => 5 ) ) && false === $f12_vp( array( 'content' => array( 'x' ) ) ) && false === $f12_vp( array( 'menu_order' => '4' ) ) && false === $f12_vp( array( 'source_index' => 3.0 ) ) && false === $f12_vp( array( 'publish_hold' => 'false' ) ) && false === $f12_vp( array( 'excerpt' => null ) ) );
mb_test( 'Faz 12 doğrulayıcı: slug/source_key biçimi ve tutarlılığı, source_index/menu_order aralığı, source.file eşleşmesi',
	false === $f12_vp( array( 'slug' => 'Hakkimizda', 'source_key' => 'page:Hakkimizda' ) ) && false === $f12_vp( array( 'source_key' => 'page:baska' ) ) && false === $f12_vp( array( 'source_key' => 'news:hakkimizda' ) ) && false === $f12_vp( array( 'source_index' => 32 ) ) && false === $f12_vp( array( 'source_index' => -1 ) )
	&& false === $f12_vp( array( 'menu_order' => 0 ) ) && false === $f12_vp( array( 'menu_order' => 33 ) ) && false === $f12_vp( array( 'schema_version' => '1.0.0' ) ) && false === $f12_vp( array( 'source' => array( 'file' => 'tanitim-site/baska.html', 'sha256' => str_repeat( 'a', 64 ) ) ) ) && false === $f12_vp( array( 'source' => array( 'file' => 'tanitim-site/hakkimizda.html' ) ) ) );
mb_test( 'Faz 12 doğrulayıcı: hedef durum yalnız draft; parent_source_key yalnız null; page_template boş; layout kapalı küme',
	false === $f12_vp( array( 'post_status' => 'publish' ) ) && false === $f12_vp( array( 'post_status' => 'private' ) ) && false === $f12_vp( array( 'parent_source_key' => 'page:kurumsal' ) ) && false === $f12_vp( array( 'page_template' => 'page-x.php' ) ) && false === $f12_vp( array( 'layout' => 'sidebar' ) ) && true === $f12_vp( array( 'layout' => 'hub' ) ) );
mb_test( 'Faz 12 doğrulayıcı: başlık kanonik düz metin ve "demo" içermez; özet düz metin (<>&, satır sonu, 300+ karakter yok)',
	false === $f12_vp( array( 'title' => ' Hakkımızda' ) ) && false === $f12_vp( array( 'title' => 'Online Başvuru (Demo)' ) ) && false === $f12_vp( array( 'title' => 'A<b>' ) ) && false === $f12_vp( array( 'title' => '' ) )
	&& false === $f12_vp( array( 'excerpt' => 'a <b>' ) ) && false === $f12_vp( array( 'excerpt' => 'a & b' ) ) && false === $f12_vp( array( 'excerpt' => "a\nb" ) ) && false === $f12_vp( array( 'excerpt' => str_repeat( 'a', 301 ) ) ) && true === $f12_vp( array( 'excerpt' => '' ) ) );
mb_test( 'Faz 12 doğrulayıcı: content_sha256 içerikten yeniden hesaplanır; içerik değişirse özet uyuşmazlığı ret',
	false === $f12_vp( array( 'content_sha256' => str_repeat( 'a', 64 ) ) ) && false === $f12_vp( array( 'content' => '<p>Başka</p>' ) ) && true === $f12_vp( array( 'content' => '<p>Başka</p>', 'content_sha256' => hash( 'sha256', '<p>Başka</p>' ) ) ) );
mb_test( 'Faz 12 doğrulayıcı: bekleyen kararlar kapalı sözlükten; çözülen altı kurum kararı kodu KALDIRILDI (kabul edilmez); publish_hold türetilenle birebir',
	false === $f12_vp( array( 'pending_decisions' => array( 'uydurma' ) ) ) && (function () use ( $f12_vp ) {
		foreach ( array( 'kvkk_text_not_approved', 'bank_details_not_approved', 'exam_calendar_url_missing', 'myk_query_url_missing', 'references_not_real', 'faq_content_not_approved' ) as $code ) {
			if ( true === $f12_vp( array( 'pending_decisions' => array( $code ), 'publish_hold' => true ) ) || true === $f12_vp( array( 'pending_decisions' => array( $code ), 'publish_hold' => false ) ) ) {
				return false;
			}
		}
		return true;
	})()
	&& true === $f12_vp( array( 'pending_decisions' => array( 'location_data_pending' ), 'publish_hold' => false ) ) && false === $f12_vp( array( 'pending_decisions' => array( 'location_data_pending' ), 'publish_hold' => true ) ) && false === $f12_vp( array( 'pending_decisions' => 'location_data_pending' ) ) && false === $f12_vp( array( 'pending_decisions' => array( 5 ) ) )
	&& array() === array_filter( $F12_RV::PAGE_PENDING_DECISIONS ) );
mb_test( 'Faz 12b doğrulayıcı: publish_requires kapalı kümeden (faq/reference) benzersiz liste; eksik anahtar/bilinmeyen kod/yinelenen/string reddedilir',
	true === $f12_vp( array( 'publish_requires' => array( 'faq' ) ) ) && true === $f12_vp( array( 'publish_requires' => array( 'reference' ) ) ) && true === $f12_vp( array( 'publish_requires' => array() ) ) && false === $f12_vp( array( 'publish_requires' => array( 'uydurma' ) ) ) && false === $f12_vp( array( 'publish_requires' => array( 'faq', 'faq' ) ) ) && false === $f12_vp( array( 'publish_requires' => 'faq' ) ) && false === $f12_vp( array(), array( 'publish_requires' ) ) && array( 'faq', 'reference' ) === $F12_RV::PAGE_PUBLISH_REQUIRES );

/* ================================================================ C) kapalı HTML izin listesi */
$f12_bad_html = array(
	'script' => '<p>x</p><script>alert(1)</script>', 'onclick' => '<p onclick="x()">a</p>', 'iframe' => '<iframe src="https://e.com"></iframe>', 'img' => '<p><img src="/a.png"></p>', 'style' => '<p style="color:red">a</p>',
	'javascript' => '<p><a href="javascript:alert(1)">a</a></p>', 'data' => '<p><a href="data:text/html,x">a</a></p>', 'protokolsuz' => '<p><a href="//evil.com">a</a></p>', 'http' => '<p><a href="http://example.com">a</a></p>', 'vbscript' => '<p><a href="vbscript:x">a</a></p>',
	'target' => '<p><a href="/x/" target="_blank">a</a></p>', 'div' => '<div>a</div>', 'yorum' => '<p>a</p><!-- x -->', 'ic_ice_a' => '<p><a href="/a/"><a href="/b/">x</a></a></p>', 'kapanmamis' => '<p>a', 'ham_lt' => '<p>a < b</p>', 'bilinmeyen_varlik' => '<p>a &foo; b</p>',
	'buyuk_harf' => '<P>a</P>', 'h1' => '<h1>a</h1>', 'tablo' => '<table><tr><td>a</td></tr></table>', 'blok_metin' => 'serbest metin', 'bos_br' => '<p>a<br>b</p>', 'ic_ice_p' => '<p><p>a</p></p>', 'li_disari' => '<li>a</li>', 'kapanis_uyumsuz' => '<p>a</strong>',
);
$f12_html_ok = true;
foreach ( $f12_bad_html as $k => $html ) {
	if ( array() === $F12_RV::page_content_errors( $html ) ) {
		$f12_html_ok = false;
		echo "  (kabul edildi: {$k})\n";
	}
}
mb_test( 'Faz 12 HTML: tehlikeli/yasak yapıların HEPSİ reddedilir (script, olay işleyici, iframe, görsel, stil, javascript:/data:/vbscript:/http:/protokolsüz URL, yorum, iç içe/kapanmamış etiket, ham < ve bilinmeyen varlık)', true === $f12_html_ok );
$f12_good = "<h2>Başlık</h2>\n<p>Merhaba <strong>dünya</strong> <a href=\"/iletisim/\">iletişim</a> ve <a href=\"mailto:info@mavibelge.com.tr\">e-posta</a> &amp; <a href=\"tel:+905426196284\">tel</a> <a href=\"https://www.mavibelge.com.tr/x?a=1&amp;b=2\">dış</a><br />satır</p>\n<ul>\n<li>bir</li>\n<li>iki <em>vurgu</em></li>\n</ul>";
mb_test( 'Faz 12 HTML: kanonik güvenli içerik (başlık, paragraf, güçlü/vurgu, bağlantı türleri, br, liste) kabul; boş içerik geçerli', array() === $F12_RV::page_content_errors( $f12_good ) && array() === $F12_RV::page_content_errors( '' ) && array() !== $F12_RV::page_content_errors( 5 ) );

/* ================================================================ D) yükleyici */
$f12_l = $F12_ML::load_pages( $f12_dir_real );
mb_test( 'Faz 12 yükleyici: load_pages 32 kaydı OLDUĞU GİBİ (sıra dahil) döndürür; sabit dosya adı pages.manifest.json', true === $f12_l['ok'] && array( 'pages' ) === array_keys( $f12_l['manifest'] ) && $f12_records === $f12_l['manifest']['pages'] && 'pages.manifest.json' === $F12_ML::PAGE_FILE );
$f12_bad_load = function ( callable $mutate, $seal = true ) use ( $F12_ML, $f12_real ) {
	return $F12_ML::load_pages( f12_dir( $f12_real, $mutate, $seal, 'f12bad' ) );
};
mb_test( 'Faz 12 yükleyici: 31 ve 33 kayıt reddedilir (sayı tam 32)',
	false === $f12_bad_load( function ( $e ) {
		array_pop( $e['records'] );
		return $e;
	} )['ok'] && false === $f12_bad_load( function ( $e ) {
		$x                  = $e['records'][0];
		$x['slug']          = 'fazla';
		$x['source_key']    = 'page:fazla';
		$x['source_index']  = 32;
		$e['records'][]     = $x;
		return $e;
	} )['ok'] );
mb_test( 'Faz 12 yükleyici: zarf source özeti/dosya, count, record_type, schema_version, fazla anahtar, eksik notes, kayıt source şekli -> ret',
	false === $f12_bad_load( function ( $e ) {
		$e['source']['sha256'] = str_repeat( 'a', 64 );
		return $e;
	}, false )['ok'] && false === $f12_bad_load( function ( $e ) {
		$e['source']['file'] = 'tanitim-site/assets/data/news.js';
		return $e;
	} )['ok'] && false === $f12_bad_load( function ( $e ) {
		$e['count'] = 31;
		return $e;
	}, false )['ok'] && false === $f12_bad_load( function ( $e ) {
		$e['record_type'] = 'news';
		return $e;
	} )['ok'] && false === $f12_bad_load( function ( $e ) {
		$e['schema_version'] = '1.0.0';
		return $e;
	} )['ok'] && false === $f12_bad_load( function ( $e ) {
		$e['ek'] = 1;
		return $e;
	} )['ok'] && false === $f12_bad_load( function ( $e ) {
		unset( $e['notes'] );
		return $e;
	} )['ok'] && false === $f12_bad_load( function ( $e ) {
		$e['records'][4]['source']['sha256'] = str_repeat( 'b', 64 );
		return $e;
	}, false )['ok'] && false === $f12_bad_load( function ( $e ) {
		$e['records'][4]['source']['file'] = '../etc/passwd';
		return $e;
	} )['ok'] );
mb_test( 'Faz 12 yükleyici: dosya yoksa hata (katalog/içerik yükleyicileri etkilenmez); load_all/load_content pages dosyasına BAKMAZ',
	false === $F12_ML::load_pages( mb6b2_temp_dir( 'f12yok' ) )['ok'] && 3 === count( $F12_ML::FILES ) && 3 === count( $F12_ML::CONTENT_FILES ) && ! in_array( 'pages.manifest.json', $F12_ML::FILES, true ) && ! in_array( 'pages.manifest.json', $F12_ML::CONTENT_FILES, true ) );

/* ================================================================ E) aşama modeli, projeksiyon, plan */
mb_test( 'Faz 12 aşama modeli: pages ilk aşama; ALL_STAGES sırası pages, sectors, qualifications, all, content; STAGE_TYPES/TYPE_LISTS/rank',
	array( 'pages', 'sectors', 'qualifications', 'all', 'content' ) === $F12_AP::ALL_STAGES && array( 'pages' ) === $F12_AP::PAGE_STAGES && array( 'page' ) === $F12_AP::STAGE_TYPES['pages'] && 'pages' === $F12_AP::TYPE_LISTS['page'] && 6 === $F12_AP::TYPE_RANK['page']
	&& array( 'sectors', 'qualifications', 'all' ) === $F12_AP::STAGES && in_array( 'pages', $F12_AP::OPTIONAL_LISTS, true ) );
mb_test( 'Faz 12 filter_manifest: pages aşaması yalnız pages listesini taşır (katalog listeleri boş); diğer aşamalar pages anahtarı EKLEMEZ',
	(function () use ( $F12_AP, $f12_records ) {
		$cat  = array( 'sectors' => array( 1 ), 'qualifications' => array( 2 ), 'fees' => array( 3 ) );
		$full = array_merge( $cat, array( 'news' => array(), 'references' => array(), 'pages' => $f12_records ) );
		$p    = $F12_AP::filter_manifest( $full, 'pages' );
		return array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array(), 'pages' => $f12_records ) === $p && array( 'sectors', 'qualifications', 'fees' ) === array_keys( $F12_AP::filter_manifest( $full, 'all' ) )
			&& array( 'sectors', 'qualifications', 'fees', 'news', 'references', 'faqs' ) === array_keys( $F12_AP::filter_manifest( $full, 'content' ) ) && null === $F12_AP::filter_manifest( array_merge( $cat, array( 'pages' => 'x' ) ), 'pages' );
	})() );
$f12_fx = array();
foreach ( $f12_records as $r ) {
	$f12_fx[ $r['slug'] ] = $F12_MF::project_page( $r )['fields'];
}
mb_test( 'Faz 12 projeksiyon: yönetilen alanlar tam PAGE_FIELDS; parent_id 0; menu_order = sıra+1; post durumu yönetilen alan DEĞİL',
	array( 'slug', 'title', 'content', 'excerpt', 'parent_id', 'menu_order' ) === $F12_MF::PAGE_FIELDS && $F12_MF::PAGE_FIELDS === array_keys( $f12_fx['hakkimizda'] ) && 0 === $f12_fx['hakkimizda']['parent_id'] && 4 === $f12_fx['hakkimizda']['menu_order']
	&& ! in_array( 'post_status', $F12_MF::PAGE_FIELDS, true ) && ! in_array( 'status', $F12_MF::PAGE_FIELDS, true ) && $F12_MF::PAGE_FIELDS === $F12_MF::fields_for( 'page' ) && in_array( 'page', $F12_MF::TYPES, true ) );
$f12_ph = MaviBelge_Core_Import_Hash::hash( $f12_fx['hakkimizda'] );
$F12_R  = f12_env( $f12_dir_real );
$f12_pv = $F12_R->apply->preview( 'pages' );
mb_test( 'Faz 12 plan: boş dünyada 32 create, 0 conflict/blocked/invalid, applicable + eligible, by_type yalnız katalog üçü + page; 64-hex digest; dünya dokunulmadı (salt okunur)',
	true === $f12_pv['ok'] && 32 === $f12_pv['summary']['total'] && 32 === $f12_pv['summary']['operations']['create'] && 0 === $f12_pv['summary']['operations']['conflict'] && 0 === $f12_pv['summary']['operations']['blocked'] && 0 === $f12_pv['summary']['operations']['invalid']
	&& true === $f12_pv['summary']['applicable'] && true === $f12_pv['eligible'] && 32 === $f12_pv['writes'] && array( 'sector', 'qualification', 'fee', 'page' ) === array_keys( $f12_pv['summary']['by_type'] ) && 32 === $f12_pv['summary']['by_type']['page']
	&& 1 === preg_match( '/^[0-9a-f]{64}\z/', $f12_pv['plan_digest'] ) && array() === $F12_R->world->writeLog && array() === $F12_R->world->runs && array() === $F12_R->world->posts );
$f12_pv2 = f12_env( $f12_dir_real )->apply->preview( 'pages' );
mb_test( 'Faz 12 determinizm: iki bağımsız plan aynı digest, aynı manifest özeti, byte-eşit JSON', $f12_pv['plan_digest'] === $f12_pv2['plan_digest'] && $f12_pv['manifest_digest'] === $f12_pv2['manifest_digest'] && json_encode( $f12_pv['plan']['entries'] ) === json_encode( $f12_pv2['plan']['entries'] ) );
$f12_ent = f12_entries( $f12_pv['plan'] );
mb_test( 'Faz 12 plan: her giriş page:<slug>, type page, decision create, natural_key none, hash = projeksiyon hash\'i; sıra manifest sırası',
	32 === count( $f12_ent ) && 'page:kurumsal' === $f12_pv['plan']['entries'][0]['source_key'] && 'page:kvkk' === $f12_pv['plan']['entries'][31]['source_key'] && 'create' === $f12_ent['page:hakkimizda']['decision'] && 'page' === $f12_ent['page:hakkimizda']['type'] && 'none' === $f12_ent['page:hakkimizda']['natural_key_check'] && $f12_ph === $f12_ent['page:hakkimizda']['incoming_hash'] );
mb_test( 'Faz 12 plan: içerik değeri plana sızmaz (yalnız alan adları/hash)', false === strpos( json_encode( $f12_pv['plan'] ), 'İskenderun' ) && false === strpos( json_encode( $f12_pv['plan'] ), '<p>' ) );
mb_test( 'Faz 12 plan: katalog aşamaları pages dosyası olmadan/varken AYNI davranır; pages aşaması katalog dosyaları OLMADAN çalışır',
	true === $f12_pv['ok'] && false === f12_env( $f12_dir_real )->apply->preview( 'sectors' )['ok'] );
mb_test( 'Faz 12 plan: yinelenen slug/source_key ve bozuk kayıt fail-closed (plan üretilmez / invalid); bozuk HTML içerikli kayıt invalid ve HİÇBİR yazma yapılmaz',
	(function () use ( $f12_real, $F12_ML ) {
		$dupSlug = f12_dir( $f12_real, function ( $e ) {
			$e['records'][1]['slug']       = $e['records'][0]['slug'];
			$e['records'][1]['source_key'] = $e['records'][0]['source_key'];
			return $e;
		}, true, 'f12dup' );
		$bad = f12_dir( $f12_real, function ( $e ) {
			$e['records'][5]['content']        = '<p onclick="x()">a</p>';
			$e['records'][5]['content_sha256'] = hash( 'sha256', $e['records'][5]['content'] );
			return $e;
		}, true, 'f12badhtml' );
		$envDup = f12_env( $dupSlug );
		$pDup   = $envDup->apply->preview( 'pages' );
		$envBad = f12_env( $bad );
		$pBad   = $envBad->apply->preview( 'pages' );
		return ( false === $pDup['eligible'] || true !== $pDup['summary']['applicable'] ) && 1 === $pBad['summary']['operations']['invalid'] && false === $pBad['eligible'] && 'plan_not_applicable' === $envBad->apply->apply( 'pages', (string) $pBad['plan_digest'], null, 1 )['error_code'] && array() === $envBad->world->writeLog && array() === $envBad->world->posts;
	})() );

/* ================================================================ F) apply -> idempotent -> rollback -> reapply */
$F12_A  = f12_env( $f12_dir_real );
$f12_ar = f12_apply( $F12_A, 10 );
mb_test( 'Faz 12 apply: 32 sayfa completed; batch=10 -> 4 batch, 32 item; run stage=pages',
	true === $f12_ar['ok'] && 'completed' === $f12_ar['status'] && 32 === $f12_ar['committed_items'] && 4 === $f12_ar['committed_batches'] && 'pages' === $F12_A->store->get_run( $f12_ar['run_uid'] )['stage'] && 32 === count( $F12_A->world->items ) );
mb_test( 'Faz 12 apply: 32 fiziksel `page` kaydı DRAFT olarak oluştu (asla publish/pending); başka post türüne/terime dokunulmadı',
	32 === count( f12_pages( $F12_A ) ) && 32 === count( f12_pages( $F12_A, 'draft' ) ) && 0 === count( f12_pages( $F12_A, 'publish' ) ) && 32 === count( $F12_A->world->posts ) && array() === $F12_A->world->terms );
list( $f12_id_h, $f12_p_h ) = f12_by_slug( $F12_A, 'hakkimizda' );
$f12_field_ok = true;
foreach ( $f12_records as $i => $r ) {
	list( , $p ) = f12_by_slug( $F12_A, $r['slug'] );
	$f12_field_ok = $f12_field_ok && null !== $p && $r['slug'] === $p['name'] && $r['title'] === $p['title'] && $r['content'] === $p['content'] && $r['excerpt'] === $p['excerpt'] && $i + 1 === $p['menu_order'] && 0 === $p['parent'] && 'draft' === $p['status']
		&& 'page:' . $r['slug'] === $p['meta']['_mb_import_source_key'] && MaviBelge_Core_Import_Hash::hash( $f12_fx[ $r['slug'] ] ) === $p['meta']['_mb_last_applied_hash'] && array() === array_diff( array_keys( $p['meta'] ), $F12_WP::MANAGED_POST_META['page'] );
}
mb_test( 'Faz 12 apply: her sayfanın ad/başlık/içerik/özet/menü sırası/üst sayfa manifestle BİREBİR; marker + hash yazıldı; başka meta yok', true === $f12_field_ok );
mb_test( 'Faz 12 apply: audit started -> batch_committed x4 -> completed; audit yalnız alan ADLARI (içerik yok)',
	array( 'import_run_started', 'import_batch_committed', 'import_batch_committed', 'import_batch_committed', 'import_batch_committed', 'import_run_completed' ) === array_map( function ( $a ) {
		return $a['event'];
	}, $F12_A->world->audit ) && false === strpos( json_encode( $F12_A->world->audit ), 'İskenderun' ) );
$f12_items_ok = true;
foreach ( $F12_A->store->get_items( $F12_A->world->runs[1]['id'] ) as $row ) {
	$rec          = MaviBelge_Core_Import_Rollback_Codec::decode( $row['rollback_record'] );
	$f12_items_ok = $f12_items_ok && null !== $rec && 'create' === $rec['decision'] && 'page' === $rec['type'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', (string) $rec['unmanaged_fingerprint'] );
}
mb_test( 'Faz 12 apply: 32 item için doğrulanmış rollback kaydı (create + 64-hex unmanaged_fingerprint) saklandı', true === $f12_items_ok && 32 === count( $F12_A->store->get_items( $F12_A->world->runs[1]['id'] ) ) );
$f12_idem = $F12_A->apply->preview( 'pages' );
$f12_wlog = count( $F12_A->world->writeLog );
$f12_noop = f12_apply( $F12_A );
mb_test( 'Faz 12 idempotent: ikinci plan 32 unchanged, applicable, 0 yazma; ikinci apply noop (yeni run/item/audit/yazma YOK, kopya sayfa YOK)',
	32 === $f12_idem['summary']['operations']['unchanged'] && true === $f12_idem['summary']['applicable'] && 0 === $f12_idem['writes'] && 'noop' === $f12_noop['status'] && 1 === count( $F12_A->world->runs ) && 32 === count( $F12_A->world->items ) && $f12_wlog === count( $F12_A->world->writeLog ) && 32 === count( $F12_A->world->posts ) );
$f12_uid = $F12_A->world->runs[1]['uid'];
$f12_rbp = $F12_A->rollback->preview( $f12_uid );
mb_test( 'Faz 12 rollback önizleme: 32 bekleyen item, engel yok, 64-hex digest', true === $f12_rbp['ok'] && 32 === $f12_rbp['items_pending'] && array() === $f12_rbp['blockers'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $f12_rbp['rollback_digest'] ) );
$f12_rbr = $F12_A->rollback->rollback( $f12_uid, $f12_rbp['rollback_digest'], null );
mb_test( 'Faz 12 rollback: rolled_back, 32 item; sayfalar ÇÖPE gider (kalıcı silme yok), marker/hash temizlenir, post_name doğal anahtarı boşalır',
	true === $f12_rbr['ok'] && 'rolled_back' === $f12_rbr['status'] && 32 === $f12_rbr['rolled_back_items'] && 32 === count( f12_pages( $F12_A, 'trash' ) ) && 32 === count( $F12_A->world->posts )
	&& (function () use ( $F12_A ) {
		foreach ( $F12_A->world->posts as $p ) {
			if ( array() !== $p['meta'] || '' !== $p['name'] ) {
				return false;
			}
		}
		return true;
	})() );
$f12_re = $F12_A->apply->preview( 'pages' );
mb_test( 'Faz 12 rollback sonrası: aynı manifest yeniden 32 create (doğal anahtar none, conflict=0), applicable; reapply çalışır (yeni 32 sayfa draft)',
	32 === $f12_re['summary']['operations']['create'] && 0 === $f12_re['summary']['operations']['conflict'] && true === $f12_re['summary']['applicable'] && 'completed' === f12_apply( $F12_A )['status'] && 32 === count( f12_pages( $F12_A, 'draft' ) ) && 64 === count( $F12_A->world->posts ) && 32 === $F12_A->apply->preview( 'pages' )['summary']['operations']['unchanged'] );

/* ================================================================ G) kullanıcı değişikliği: update / conflict / drift */
$F12_B = f12_env( $f12_dir_real );
$f12_br = f12_apply( $F12_B );
list( $f12_idb, ) = f12_by_slug( $F12_B, 'hakkimizda' );
$F12_B->world->posts[ $f12_idb ]['content'] = '<p>Editör bu sayfayı yeniden yazdı.</p>';
$f12_bp = $F12_B->apply->preview( 'pages' );
$f12_be = f12_entries( $f12_bp['plan'] );
mb_test( 'Faz 12 kullanıcı değişikliği: yönetilen alan (içerik) elle değişti -> conflict manual_edit_detected; BÜTÜN aşama reddedilir; editör içeriği ezilmez',
	'conflict' === $f12_be['page:hakkimizda']['decision'] && 'manual_edit_detected' === $f12_be['page:hakkimizda']['reason'] && 31 === $f12_bp['summary']['operations']['unchanged'] && false === $f12_bp['eligible'] && 'plan_not_applicable' === $F12_B->apply->apply( 'pages', (string) $f12_bp['plan_digest'], null, 1 )['error_code']
	&& '<p>Editör bu sayfayı yeniden yazdı.</p>' === $F12_B->world->posts[ $f12_idb ]['content'] );
$f12_rb_before = $F12_B->world->posts;
$f12_bb        = f12_rollback( $F12_B, $f12_br['run_uid'] );
mb_test( 'Faz 12 rollback drift: yönetilen alan elle değişmişse rollback ENGELLENİR (drift_detected); HİÇBİR sayfa çöpe gitmez (kısmi rollback yok); veri korunur; run rollback_failed',
	false === $f12_bb['ok'] && 'drift_detected' === $f12_bb['error_code'] && 0 === count( f12_pages( $F12_B, 'trash' ) ) && $f12_rb_before === $F12_B->world->posts && 'rollback_failed' === $F12_B->store->get_run( $f12_br['run_uid'] )['status'] );
$f12_drift = function ( callable $mutate ) use ( $f12_dir_real ) {
	$env = f12_env( $f12_dir_real );
	$run = f12_apply( $env );
	list( $id ) = f12_by_slug( $env, 'iletisim' );
	$mutate( $env->world, $id );
	$res = f12_rollback( $env, $run['run_uid'] );
	return array( $env, $res, $id );
};
list( $Ed1, $Ed1r ) = $f12_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['meta']['_yoast_wpseo_title'] = 'SEO';
	}
);
mb_test( 'Faz 12 drift: yönetilmeyen meta (SEO) rollback\'i engeller; hiçbir şey yazılmaz', 'drift_detected' === $Ed1r['error_code'] && 0 === count( f12_pages( $Ed1, 'trash' ) ) );
list( $Ed2, $Ed2r, $Ed2id ) = $f12_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['status'] = 'publish';
	}
);
$f12_pub_plan = $Ed2->apply->preview( 'pages' );
mb_test( 'Faz 12 drift: kullanıcı sayfayı yayınladı -> plan hâlâ unchanged (durum yönetilmez, sahte conflict yok) AMA rollback engellenir (yayındaki içerik korunur)',
	32 === $f12_pub_plan['summary']['operations']['unchanged'] && true === $f12_pub_plan['eligible'] && 'drift_detected' === $Ed2r['error_code'] && 0 === count( f12_pages( $Ed2, 'trash' ) ) && 'publish' === $Ed2->world->posts[ $Ed2id ]['status'] );
list( $Ed3, $Ed3r ) = $f12_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['extra']['post_password'] = 'x';
		$w->posts[ $id ]['terms']['category']     = array( 3 );
	}
);
mb_test( 'Faz 12 drift: başka taksonomi/yönetilmeyen çekirdek alan rollback\'i engeller', 'drift_detected' === $Ed3r['error_code'] && 0 === count( f12_pages( $Ed3, 'trash' ) ) );
list( $Ed4, $Ed4r ) = $f12_drift(
	function ( $w, $id ) {
		$w->posts[ $id ]['menu_order'] = 99;
	}
);
mb_test( 'Faz 12 drift: menu_order (yönetilen) elle değişti -> hash uyuşmazlığı, rollback engellenir', 'drift_detected' === $Ed4r['error_code'] && 0 === count( f12_pages( $Ed4, 'trash' ) ) );
list( $Ed5, $Ed5r ) = $f12_drift( function ( $w, $id ) {} );
mb_test( 'Faz 12 drift negatif kontrol: değişiklik yoksa temiz rollback çalışır (32 çöp)', true === $Ed5r['ok'] && 32 === count( f12_pages( $Ed5, 'trash' ) ) );

// Manifest güncellemesi (kaynak değişti): update; durum korunur; rollback eski alanları geri yazar.
$f12_dir2 = f12_dir(
	$f12_real,
	function ( $e ) {
		$e['records'][6]['title'] = 'Tarafsızlık Beyanımız';
		$e['records'][6]['excerpt'] = 'Yeni özet.';
		return $e;
	},
	true,
	'f12upd'
);
$F12_C = f12_env( $f12_dir_real );
f12_apply( $F12_C );
list( $f12_idt, ) = f12_by_slug( $F12_C, 'tarafsizlik-beyani' );
$F12_C->world->posts[ $f12_idt ]['status'] = 'publish'; // editör yayınlamış: güncelleme durumu DEĞİŞTİRMEMELİ.
$F12_C->dryRun = new MaviBelge_Core_Import_Dry_Run_Service( $F12_C->repo, $f12_dir2 );
$F12_C->apply  = new MaviBelge_Core_Import_Apply_Service( $F12_C->dryRun, $F12_C->writer, $F12_C->tx, $F12_C->store, $F12_C->audit );
$f12_up_p = $F12_C->apply->preview( 'pages' );
$f12_up_e = f12_entries( $f12_up_p['plan'] );
mb_test( 'Faz 12 güncelleme planı: yalnız tarafsizlik-beyani update (changed_fields yalnız alan adları: title, excerpt); diğer 31 unchanged',
	'update' === $f12_up_e['page:tarafsizlik-beyani']['decision'] && array( 'title', 'excerpt' ) === $f12_up_e['page:tarafsizlik-beyani']['changed_fields'] && 31 === $f12_up_p['summary']['operations']['unchanged'] && 1 === $f12_up_p['summary']['operations']['update'] && true === $f12_up_p['eligible'] && 1 === $f12_up_p['writes'] );
$f12_up_r = $F12_C->apply->apply( 'pages', $f12_up_p['plan_digest'], null, 1 );
mb_test( 'Faz 12 güncelleme apply: aynı post güncellenir (yeni sayfa YOK), yayın durumu DEĞİŞMEZ (publish kalır), yalnız yönetilen alanlar; readback unchanged',
	true === $f12_up_r['ok'] && 'completed' === $f12_up_r['status'] && 1 === $f12_up_r['committed_items'] && 32 === count( $F12_C->world->posts ) && 'Tarafsızlık Beyanımız' === $F12_C->world->posts[ $f12_idt ]['title'] && 'Yeni özet.' === $F12_C->world->posts[ $f12_idt ]['excerpt'] && 'publish' === $F12_C->world->posts[ $f12_idt ]['status']
	&& 32 === $F12_C->apply->preview( 'pages' )['summary']['operations']['unchanged'] );
$f12_up_rb = f12_rollback( $F12_C, $f12_up_r['run_uid'] );
mb_test( 'Faz 12 güncelleme rollback: eski başlık/özet geri yazıldı; yönetilmeyen alana (yayın durumu) dokunulmadı', true === $f12_up_rb['ok'] && 'rolled_back' === $f12_up_rb['status'] && $f12_records[6]['title'] === $F12_C->world->posts[ $f12_idt ]['title'] && $f12_records[6]['excerpt'] === $F12_C->world->posts[ $f12_idt ]['excerpt'] && 'publish' === $F12_C->world->posts[ $f12_idt ]['status'] );
// Hem kullanıcı hem kaynak değişti -> both_changed.
$F12_D = f12_env( $f12_dir_real );
f12_apply( $F12_D );
list( $f12_idd, ) = f12_by_slug( $F12_D, 'tarafsizlik-beyani' );
$F12_D->world->posts[ $f12_idd ]['title'] = 'Editör başlığı';
$F12_D->dryRun = new MaviBelge_Core_Import_Dry_Run_Service( $F12_D->repo, $f12_dir2 );
$F12_D->apply  = new MaviBelge_Core_Import_Apply_Service( $F12_D->dryRun, $F12_D->writer, $F12_D->tx, $F12_D->store, $F12_D->audit );
$f12_both = f12_entries( $F12_D->apply->preview( 'pages' )['plan'] );
mb_test( 'Faz 12 çakışma: hem editör hem kaynak değişti -> conflict both_changed; editör başlığı ezilmez', 'both_changed' === $f12_both['page:tarafsizlik-beyani']['reason'] && 'Editör başlığı' === $F12_D->world->posts[ $f12_idd ]['title'] );

/* ================================================================ H) işaretçi / doğal anahtar / tür hataları (fail-closed) */
$f12_seed = function ( array $post ) use ( $f12_dir_real ) {
	$env = f12_env( $f12_dir_real );
	$env->world->posts[900] = array_merge( array( 'post_type' => 'page', 'title' => 'Elle oluşturuldu', 'status' => 'draft', 'content' => 'kullanıcı içeriği', 'name' => 'hakkimizda', 'excerpt' => '', 'date' => '', 'parent' => 0, 'menu_order' => 0, 'extra' => array(), 'meta' => array(), 'terms' => array() ), $post );
	return $env;
};
$f12_deny = function ( $env ) {
	$p = $env->apply->preview( 'pages' );
	return $p['summary']['operations']['conflict'] >= 1 && false === $p['eligible'] && 'plan_not_applicable' === $env->apply->apply( 'pages', (string) $p['plan_digest'], null, 1 )['error_code'] && array() === $env->world->writeLog && array() === $env->world->runs && 1 === count( $env->world->posts );
};
mb_test( 'Faz 12 işaretçi: kullanıcının elle oluşturduğu aynı slug\'lı sayfa (marker yok) -> create YAPILMAZ, unmanaged conflict; bütün aşama reddedilir, hiçbir yazma yok', true === $f12_deny( $f12_seed( array() ) ) );
mb_test( 'Faz 12 işaretçi: aynı slug + BOZUK marker (dizi) -> corrupt_marker conflict, create yok', 'corrupt_marker' === f12_entries( $f12_seed( array( 'meta' => array( '_mb_import_source_key' => array( 'page:hakkimizda' ) ) ) )->apply->preview( 'pages' )['plan'] )['page:hakkimizda']['reason'] && true === $f12_deny( $f12_seed( array( 'meta' => array( '_mb_import_source_key' => array( 'page:hakkimizda' ) ) ) ) ) );
mb_test( 'Faz 12 işaretçi: aynı slug + yabancı marker (başka source_key) ve yanlış önekli marker -> conflict, create yok',
	'foreign_marker' === f12_entries( $f12_seed( array( 'meta' => array( '_mb_import_source_key' => 'page:baska' ) ) )->apply->preview( 'pages' )['plan'] )['page:hakkimizda']['reason'] && 'wrong_marker_prefix' === f12_entries( $f12_seed( array( 'meta' => array( '_mb_import_source_key' => 'news:hakkimizda' ) ) )->apply->preview( 'pages' )['plan'] )['page:hakkimizda']['reason']
	&& true === $f12_deny( $f12_seed( array( 'meta' => array( '_mb_import_source_key' => 'page:baska' ) ) ) ) );
mb_test( 'Faz 12 işaretçi: çöpteki kullanıcı sayfası aynı slug\'ı tutuyor (__trashed + istenen slug) -> conflict, create yok', true === $f12_deny( $f12_seed( array( 'status' => 'trash', 'name' => 'hakkimizda__trashed', 'desired_slug' => 'hakkimizda' ) ) ) );
mb_test( 'Faz 12 işaretçi: yayındaki kullanıcı sayfası aynı slug\'ı tutuyor -> conflict, create yok', true === $f12_deny( $f12_seed( array( 'status' => 'publish' ) ) ) );
$f12_wrong = $f12_seed( array( 'post_type' => 'mb_haber', 'name' => 'baska-haber', 'meta' => array( '_mb_import_source_key' => 'page:hakkimizda' ) ) );
$f12_wp    = $f12_wrong->apply->preview( 'pages' );
mb_test( 'Faz 12 işaretçi: page marker\'ı mb_haber postunda -> conflict_wrong_target_type; apply reddedilir', 1 === $f12_wp['summary']['by_decision']['conflict_wrong_target_type'] && false === $f12_wp['eligible'] && 'plan_not_applicable' === $f12_wrong->apply->apply( 'pages', (string) $f12_wp['plan_digest'], null, 1 )['error_code'] );
$f12_dupe = $f12_seed( array( 'name' => 'x', 'meta' => array( '_mb_import_source_key' => 'page:hakkimizda', '_mb_last_applied_hash' => str_repeat( 'a', 64 ) ) ) );
$f12_dupe->world->posts[901] = array_merge( $f12_dupe->world->posts[900], array( 'name' => 'y' ) );
mb_test( 'Faz 12 işaretçi: aynı marker iki sayfada -> conflict_duplicate_target', 1 === $f12_dupe->apply->preview( 'pages' )['summary']['by_decision']['conflict_duplicate_target'] && false === $f12_dupe->apply->preview( 'pages' )['eligible'] );
$f12_legacy = $f12_seed( array( 'name' => 'x', 'meta' => array( '_mb_import_source_key' => 'page:hakkimizda' ) ) );
mb_test( 'Faz 12 işaretçi: marker var ama hash yok (legacy) -> conflict legacy_missing_hash', 'legacy_missing_hash' === f12_entries( $f12_legacy->apply->preview( 'pages' )['plan'] )['page:hakkimizda']['reason'] );
$f12_import_shell = f12_env( $f12_dir_real );
$f12_import_shell->world->posts[904] = array( 'post_type' => 'page', 'title' => 'Import kabuğu', 'status' => 'trash', 'content' => '', 'name' => '', 'excerpt' => '', 'date' => '', 'parent' => 0, 'menu_order' => 0, 'extra' => array(), 'meta' => array(), 'terms' => array() );
mb_test( 'Faz 12 işaretçi: importun kendi rollback kabuğu (post_name boş) doğal anahtarı TUTMAZ -> 32 create, applicable', 32 === $f12_import_shell->apply->preview( 'pages' )['summary']['operations']['create'] && true === $f12_import_shell->apply->preview( 'pages' )['eligible'] );

/* ================================================================ I) batch / audit / checkpoint / readback atomikliği */
$F12_E = f12_env( $f12_dir_real );
$F12_E->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'page:banka-hesap-bilgileri' ) );
$f12_er   = f12_apply( $F12_E, 10 );
$f12_erun = $F12_E->store->get_run( $f12_er['run_uid'] );
mb_test( 'Faz 12 batch atomikliği: 3. batch\'te yazma hatası -> o batch TAMAMEN geri alınır (yarım sayfa kalmaz), ilk 2 batch (20 sayfa) korunur, run rollback_required',
	false === $f12_er['ok'] && 'write_failed' === $f12_er['error_code'] && 'rollback_required' === $f12_erun['status'] && 2 === $f12_erun['committed_batches'] && 20 === $f12_erun['committed_items'] && 20 === count( f12_pages( $F12_E ) ) && 20 === count( $F12_E->store->get_items( $f12_erun['id'] ) ) );
$f12_er_rb = f12_rollback( $F12_E, $f12_er['run_uid'] );
mb_test( 'Faz 12 güvenli devam: yarım run yalnız commit edilen 20 item için geri alınır; hata giderilince tam aşama yeniden uygulanır (32 draft)',
	true === $f12_er_rb['ok'] && 20 === $f12_er_rb['rolled_back_items'] && 20 === count( f12_pages( $F12_E, 'trash' ) ) && (function () use ( $F12_E ) {
		$F12_E->world->faults = array();
		return 'completed' === f12_apply( $F12_E, 10 )['status'] && 32 === count( f12_pages( $F12_E, 'draft' ) );
	})() );
$F12_F = f12_env( $f12_dir_real );
$F12_F->audit->failEvents = array( 'import_batch_committed' );
$f12_fr = f12_apply( $F12_F, 10 );
mb_test( 'Faz 12 audit atomikliği: batch audit\'i yazılamazsa batch transaction\'ı GERİ ALINIR; hiçbir sayfa kalmaz, run failed', false === $f12_fr['ok'] && 'audit_failed' === $f12_fr['error_code'] && 0 === count( $F12_F->world->posts ) && 'failed' === $F12_F->store->get_run( $f12_fr['run_uid'] )['status'] );
$F12_G = f12_env( $f12_dir_real );
$F12_G->store->failWrites = array( 'checkpoint' );
$f12_gr = f12_apply( $F12_G, 10 );
mb_test( 'Faz 12 checkpoint atomikliği: checkpoint yazılamazsa checkpoint_failed; batch geri alınır, yarım başarı YOK (0 sayfa, sayaç 0), run failed',
	false === $f12_gr['ok'] && 'checkpoint_failed' === $f12_gr['error_code'] && 'failed' === $f12_gr['status'] && array() === $F12_G->world->posts && 0 === $F12_G->store->get_run( $f12_gr['run_uid'] )['committed_items'] );
$F12_H = f12_env( $f12_dir_real );
$F12_H->audit->failEvents = array( 'import_run_completed' );
$f12_hr = f12_apply( $F12_H );
mb_test( 'Faz 12 finalizer: run_completed audit\'i yazılamazsa run rollback_required (running KALMAZ); doğrulanmış kayıtlarla temiz geri alınır',
	false === $f12_hr['ok'] && 'rollback_required' === $f12_hr['status'] && 'rollback_required' === $F12_H->store->get_run( $f12_hr['run_uid'] )['status'] && 32 === count( f12_pages( $F12_H ) ) && true === f12_rollback( $F12_H, $f12_hr['run_uid'] )['ok'] && 32 === count( f12_pages( $F12_H, 'trash' ) ) );
$F12_I = f12_env( $f12_dir_real );
$F12_I->world->faults = array( array( 'op' => 'touch_unmanaged', 'source_key' => 'page:kariyer' ) );
$f12_ir = f12_apply( $F12_I, 20 );
mb_test( 'Faz 12 readback: yazma sırasında yönetilen alan bozulursa readback_mismatch -> BÜTÜN batch geri alınır', false === $f12_ir['ok'] && 'readback_mismatch' === $f12_ir['error_code'] && 0 === count( $F12_I->world->posts ) );
$F12_J = f12_env( $f12_dir_real );
$f12_jp = $F12_J->apply->preview( 'pages' );
$F12_J->world->posts[903] = array( 'post_type' => 'page', 'title' => 'Arada eklendi', 'status' => 'draft', 'content' => '', 'name' => 'kvkk', 'excerpt' => '', 'date' => '', 'parent' => 0, 'menu_order' => 0, 'extra' => array(), 'meta' => array(), 'terms' => array() );
$f12_jr = $F12_J->apply->apply( 'pages', $f12_jp['plan_digest'], null, 1 );
mb_test( 'Faz 12 TOCTOU/onay: plan sonrası beliren doğal anahtar onay digest\'ini geçersiz kılar; hiçbir yazma yapılmaz', false === $f12_jr['ok'] && in_array( $f12_jr['error_code'], array( 'confirmation_mismatch', 'plan_not_applicable' ), true ) && array() === $F12_J->world->writeLog && array() === $F12_J->world->runs );
mb_test( 'Faz 12 onay digest\'i: yanlış digest ve başka aşamanın digest\'i reddedilir', 'confirmation_mismatch' === f12_env( $f12_dir_real )->apply->apply( 'pages', str_repeat( 'a', 64 ), null, 1 )['error_code'] && false === f12_env( $f12_dir_real )->apply->apply( 'pages', $f12_pv['plan_digest'] . 'x', null, 1 )['ok'] );

/* ================================================================ J) yazma yükü (yük düzeyi kapılar) */
$f12_payload = $F12_WP::prepare( 'page', $f12_fx['hakkimizda'], 'page:hakkimizda', $f12_ph );
mb_test( 'Faz 12 yazma yükü: geçerli sayfa yükü hazırlanır; durum alanı taşımaz; yalnız marker+hash meta; post_type page',
	true === $f12_payload['ok'] && 'page' === $f12_payload['payload']['post']['post_type'] && ! array_key_exists( 'post_status', $f12_payload['payload']['post'] ) && array( '_mb_import_source_key', '_mb_last_applied_hash' ) === array_keys( $f12_payload['payload']['post_meta'] ) && 0 === $f12_payload['payload']['post']['post_parent'] && 4 === $f12_payload['payload']['post']['menu_order'] );
$f12_bad_fields = array_merge( $f12_fx['hakkimizda'], array( 'content' => '<script>x</script>' ) );
mb_test( 'Faz 12 yazma yükü: güvensiz içerik, parent_id != 0, yanlış marker/hash, fazla alan -> ret (yük hazırlanmaz)',
	false === $F12_WP::prepare( 'page', $f12_bad_fields, 'page:hakkimizda', MaviBelge_Core_Import_Hash::hash( $f12_bad_fields ) )['ok']
	&& false === $F12_WP::prepare( 'page', array_merge( $f12_fx['hakkimizda'], array( 'parent_id' => 5 ) ), 'page:hakkimizda', MaviBelge_Core_Import_Hash::hash( array_merge( $f12_fx['hakkimizda'], array( 'parent_id' => 5 ) ) ) )['ok']
	&& false === $F12_WP::prepare( 'page', $f12_fx['hakkimizda'], 'news:hakkimizda', $f12_ph )['ok'] && false === $F12_WP::prepare( 'page', $f12_fx['hakkimizda'], 'page:hakkimizda', str_repeat( 'a', 64 ) )['ok']
	&& false === $F12_WP::prepare( 'page', array_merge( $f12_fx['hakkimizda'], array( 'fazla' => 1 ) ), 'page:hakkimizda', $f12_ph )['ok'] );

/* ================================================================ K) depo saf kurucusu */
$f12_raw = array( 'slug' => 'hakkimizda', 'title' => 'Hakkımızda', 'content' => '<p>x</p>', 'excerpt' => 'ö', 'parent_id' => 0, 'menu_order' => 4 );
$f12_br  = function ( array $patch, $drop = array() ) use ( $F12_RP, $f12_raw ) {
	$r = array_merge( $f12_raw, $patch );
	foreach ( $drop as $k ) {
		unset( $r[ $k ] );
	}
	return $F12_RP::page_fields_from_raw( $r );
};
mb_test( 'Faz 12 depo: ham değerlerden yönetilen alanlar; TEK bozuk alan tüm kümeyi null yapar (sahte varsayılan yok)',
	$f12_raw === $f12_br( array() ) && array( 'menu_order' => 4 ) === array( 'menu_order' => $f12_br( array( 'menu_order' => '4' ) )['menu_order'] )
	&& null === $f12_br( array( 'slug' => array( 'x' ) ) ) && null === $f12_br( array( 'title' => 5 ) ) && null === $f12_br( array( 'content' => null ) ) && null === $f12_br( array( 'excerpt' => false ) ) && null === $f12_br( array( 'parent_id' => -1 ) ) && null === $f12_br( array( 'menu_order' => 'x' ) ) && null === $f12_br( array( 'menu_order' => array( 1 ) ) )
	&& null === $f12_br( array(), array( 'excerpt' ) ) && null === $f12_br( array( 'fazla' => 1 ) ) );

/* ================================================================ L) sayfa YAYINLAMA (ayrı, onaylı işlem) */
$F12_G  = 'MaviBelge_Core_Import_Admin_Gates';
$F12_PP = 'MaviBelge_Core_Import_Page_Publisher';
mb_test( 'Faz 12 yayın kapıları: preview_publish read, publish_pages publish seviyesi; kapalı request anahtarları; onay ifadesi YAYINLA <özet ilk 12> (apply/rollback ifadelerinden AYRI)',
	'read' === $F12_G::ACTIONS['preview_publish']['level'] && array() === $F12_G::ACTIONS['preview_publish']['keys'] && 'publish' === $F12_G::ACTIONS['publish_pages']['level'] && array( 'plan_digest', 'confirm_phrase', 'expected_remaining' ) === $F12_G::ACTIONS['publish_pages']['keys'] && in_array( 'publish', $F12_G::LEVELS, true )
	&& 'YAYINLA ' . substr( $f12_pv['plan_digest'], 0, 12 ) === $F12_G::publish_phrase( $f12_pv['plan_digest'] ) && null === $F12_G::publish_phrase( 'kısa' ) && $F12_G::publish_phrase( $f12_pv['plan_digest'] ) !== $F12_G::apply_phrase( 'pages', $f12_pv['plan_digest'] ) );
$f12_snap = array( 'method' => 'POST', 'is_ssl' => true, 'user_id' => 7, 'can_manage_options' => true, 'can_tariff' => true, 'constants' => array( $F12_G::CONST_APPLY => true, $F12_G::CONST_ADMIN_APPLY => true ), 'environment_type' => 'staging', 'host' => 'cms-yeni.example.test' );
$f12_gate = function ( array $patch ) use ( $F12_G, $f12_snap ) {
	return $F12_G::evaluate( 'publish', array_merge( $f12_snap, $patch ) );
};
mb_test( 'Faz 12 yayın kapısı: staging + iki sabit + iki yetki + POST + HTTPS ile açılır; her kapı tek tek kapatılınca reddeder',
	true === $f12_gate( array() )['ok'] && in_array( 'method_not_post', $f12_gate( array( 'method' => 'GET' ) )['codes'], true ) && in_array( 'not_https', $f12_gate( array( 'is_ssl' => false ) )['codes'], true ) && in_array( 'not_logged_in', $f12_gate( array( 'user_id' => 0 ) )['codes'], true )
	&& in_array( 'missing_capability', $f12_gate( array( 'can_tariff' => false ) )['codes'], true ) && in_array( 'apply_disabled', $f12_gate( array( 'constants' => array( $F12_G::CONST_ADMIN_APPLY => true ) ) )['codes'], true ) && in_array( 'admin_apply_disabled', $f12_gate( array( 'constants' => array( $F12_G::CONST_APPLY => true ) ) )['codes'], true )
	&& in_array( 'apply_disabled', $f12_gate( array( 'constants' => array( $F12_G::CONST_APPLY => 1, $F12_G::CONST_ADMIN_APPLY => true ) ) )['codes'], true ) );
mb_test( 'Faz 12 yayın kapısı: üretimde (tüm üretim bayrakları açık olsa bile) yayınlama YOK -> publish_staging_only; local/boş ortam da reddedilir',
	in_array( 'publish_staging_only', $f12_gate( array( 'environment_type' => 'production', 'host' => 'x.test', 'constants' => array( $F12_G::CONST_APPLY => true, $F12_G::CONST_ADMIN_APPLY => true, $F12_G::CONST_PRODUCTION => true, $F12_G::CONST_PRODUCTION_HOST => 'x.test' ) ) )['codes'], true )
	&& in_array( 'environment_not_allowed', $f12_gate( array( 'environment_type' => 'local' ) )['codes'], true ) && in_array( 'publish_staging_only', $f12_gate( array( 'environment_type' => 'local' ) )['codes'], true ) && false === $f12_gate( array( 'environment_type' => '' ) )['ok'] );
$f12_nonce = 'abc1234567';
$f12_pub_post = array( 'action' => 'mavibelge_import_publish_pages', $F12_G::NONCE_FIELD => $f12_nonce, 'plan_digest' => $f12_pv['plan_digest'], 'confirm_phrase' => 'YAYINLA x', 'expected_remaining' => '25' );
$f12_nr = $F12_G::normalize_request( 'publish_pages', $f12_pub_post, array() );
mb_test( 'Faz 12 yayın request: kapalı şekil (fazla/eksik anahtar, GET, kanonik olmayan sayı, kısa digest reddedilir); expected_remaining int olur',
	true === $f12_nr['ok'] && 25 === $f12_nr['data']['expected_remaining'] && false === $F12_G::normalize_request( 'publish_pages', array_merge( $f12_pub_post, array( 'ekstra' => '1' ) ), array() )['ok'] && false === $F12_G::normalize_request( 'publish_pages', array_diff_key( $f12_pub_post, array( 'expected_remaining' => 1 ) ), array() )['ok']
	&& false === $F12_G::normalize_request( 'publish_pages', $f12_pub_post, array( 'x' => '1' ) )['ok'] && false === $F12_G::normalize_request( 'publish_pages', array_merge( $f12_pub_post, array( 'expected_remaining' => '025' ) ), array() )['ok'] && false === $F12_G::normalize_request( 'publish_pages', array_merge( $f12_pub_post, array( 'expected_remaining' => '-1' ) ), array() )['ok']
	&& false === $F12_G::normalize_request( 'publish_pages', array_merge( $f12_pub_post, array( 'plan_digest' => 'abc' ) ), array() )['ok'] && true === $F12_G::normalize_request( 'preview_publish', array( 'action' => 'mavibelge_import_preview_publish', $F12_G::NONCE_FIELD => $f12_nonce ), array() )['ok']
	&& false === $F12_G::normalize_request( 'preview_publish', array( 'action' => 'mavibelge_import_preview_publish', $F12_G::NONCE_FIELD => $f12_nonce, 'stage' => 'pages' ), array() )['ok'] );

/** Yönetim servisi: gerçek fabrika grafiği, sahte dünya bileşenleriyle. */
function f12_admin( $dir ) {
	$world = new MB_Fake_World();
	$store = new MB_Fake_Run_Store( $world );
	$store->installed = true;
	$o          = new stdClass();
	$o->world   = $world;
	$o->store   = $store;
	$o->audit   = new MB_Fake_Audit_Sink( $world );
	$o->writer  = new MB_Fake_Writer( $world );
	$o->factory = new MaviBelge_Core_Import_Runtime_Factory(
		array(
			'manifest_dir'         => $dir,
			'image_map_raw'        => null,
			'attachment_inspector' => function ( $id ) {
				return null;
			},
			'repository_factory'   => function ( array $map ) use ( $world ) {
				return new MB_Fake_World_Repository( $world, $map );
			},
			'writer'               => $o->writer,
			'tx'                   => new MB_Fake_Transaction( $world ),
			'store'                => $store,
			'audit'                => $o->audit,
		)
	);
	$o->svc = new MaviBelge_Core_Import_Admin_Run_Service( $o->factory );
	return $o;
}
function f12_admin_apply_pages( $o ) {
	$p = $o->svc->preview_stage( 'pages' );
	$s = $o->svc->start_apply( 'pages', (string) $p['plan_digest'], (string) MaviBelge_Core_Import_Admin_Gates::apply_phrase( 'pages', (string) $p['plan_digest'] ), 7 );
	$last = $s;
	for ( $i = 0; $i < 10 && $s['ok']; $i++ ) {
		$last = $o->svc->advance_apply( $s['run_uid'], $i, 7 );
		if ( ! $last['ok'] || 'paused' !== $last['status'] ) {
			break;
		}
	}
	return $last;
}
function f12_publish_all( $o, $limit = 10 ) {
	$out = array();
	for ( $i = 0; $i < $limit; $i++ ) {
		$pv = $o->svc->preview_publish();
		if ( 0 === $pv['summary']['ready'] ) {
			break;
		}
		$r     = $o->svc->publish_pages( (string) $pv['plan_digest'], (string) $pv['phrase'], $pv['summary']['ready'], 7 );
		$out[] = $r;
		if ( ! $r['ok'] || 'paused' !== $r['status'] ) {
			break;
		}
	}
	return $out;
}

$F12_P  = f12_admin( $f12_dir_real );
$f12_p0 = $F12_P->svc->preview_publish();
mb_test( 'Faz 12 yayın önizleme (henüz sayfa yok): 32 satır; yedi sayfanın kurum kararı çözüldü (bekletilen 0); hepsi not_created; ready 0; 64-hex digest; güvenli DTO (içerik/yol YOK)',
	true === $f12_p0['ok'] && 32 === $f12_p0['summary']['total'] && 0 === $f12_p0['summary']['held_pending_decision'] && 32 === $f12_p0['summary']['not_created'] && 0 === $f12_p0['summary']['ready'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $f12_p0['plan_digest'] ) && false === strpos( json_encode( $f12_p0 ), 'İskenderun' )
	&& array( 'blocking', 'code', 'label' ) === (function ( $k ) {
		sort( $k );
		return $k;
	})( array_keys( $f12_p0['rows'][3]['pending'][0] ) ) && 'referanslar' === $f12_p0['rows'][9]['slug'] && 'tanitim-site/referanslar.html' === $f12_p0['rows'][9]['source_file'] && false === $f12_p0['rows'][9]['publish_hold'] && array( 'reference' ) === $f12_p0['rows'][9]['publish_requires'] && array( 'faq' ) === $f12_p0['rows'][18]['publish_requires'] );
$f12_none = $F12_P->svc->publish_pages( (string) $f12_p0['plan_digest'], (string) $f12_p0['phrase'], 0, 7 );
mb_test( 'Faz 12 yayın: hiç hazır sayfa yokken publish completed/0 (yazma yok); sayfalar oluşturulmadan hiçbir şey yayınlanmaz', true === $f12_none['ok'] && 'completed' === $f12_none['status'] && 0 === $f12_none['published'] && array() === $F12_P->world->writeLog );
$f12_ap = f12_admin_apply_pages( $F12_P );
mb_test( 'Faz 12 yayın önkoşulu: pages aşaması admin servisiyle (istek başına 10) completed; 32 sayfa TASLAK; oluşturma yayınlamaz', true === $f12_ap['ok'] && 'completed' === $f12_ap['status'] && 32 === count( f12_pages( $F12_P ) ) && 32 === count( f12_pages( $F12_P, 'draft' ) ) && 0 === count( f12_pages( $F12_P, 'publish' ) ) );
$f12_p1 = $F12_P->svc->preview_publish();
mb_test( 'Faz 12 yayın önizleme (32 taslak): ready 30; referanslar ve sss content_not_ready (içerik kayıtları yok); her satırda başlık/slug/kaynak/durum/neden/bekleyen kararlar görünür; kvkk artık hazır',
	30 === $f12_p1['summary']['ready'] && 2 === $f12_p1['summary']['content_not_ready'] && 0 === $f12_p1['summary']['held_pending_decision'] && $f12_p1['plan_digest'] !== $f12_p0['plan_digest'] && 'draft' === $f12_p1['rows'][3]['status'] && 'ready' === $f12_p1['rows'][3]['reason'] && 'Hakkımızda' === $f12_p1['rows'][3]['title'] && 'kvkk' === $f12_p1['rows'][31]['slug'] && 'ready' === $f12_p1['rows'][31]['reason'] && array() === $f12_p1['rows'][31]['pending']
	&& 'referanslar' === $f12_p1['rows'][9]['slug'] && 'content_not_ready' === $f12_p1['rows'][9]['reason'] && 'sss' === $f12_p1['rows'][18]['slug'] && 'content_not_ready' === $f12_p1['rows'][18]['reason'] );
$f12_phr = (string) $f12_p1['phrase'];
$f12_r_bad_phrase = $F12_P->svc->publish_pages( (string) $f12_p1['plan_digest'], strtolower( $f12_phr ), 30, 7 );
$f12_r_bad_digest = $F12_P->svc->publish_pages( str_repeat( 'a', 64 ), (string) $F12_G::publish_phrase( str_repeat( 'a', 64 ) ), 30, 7 );
$f12_r_bad_short  = $F12_P->svc->publish_pages( 'kısa', 'x', 30, 7 );
$f12_r_apply_ph   = $F12_P->svc->publish_pages( (string) $f12_p1['plan_digest'], (string) $F12_G::apply_phrase( 'pages', (string) $f12_p1['plan_digest'] ), 30, 7 );
mb_test( 'Faz 12 yayın onayı: yanlış/harf-hatalı ifade, apply ifadesi, geçersiz/başka digest reddedilir; HİÇBİR sayfa yayınlanmaz',
	'confirmation_phrase_mismatch' === $f12_r_bad_phrase['error_code'] && 'confirmation_mismatch' === $f12_r_bad_digest['error_code'] && 'invalid_confirmation' === $f12_r_bad_short['error_code'] && 'confirmation_phrase_mismatch' === $f12_r_apply_ph['error_code'] && 0 === count( f12_pages( $F12_P, 'publish' ) ) );
$f12_r_stale = $F12_P->svc->publish_pages( (string) $f12_p1['plan_digest'], $f12_phr, 29, 7 );
mb_test( 'Faz 12 yayın: bayat/yanlış expected_remaining -> stale_request; yazma YOK', 'stale_request' === $f12_r_stale['error_code'] && false === $f12_r_stale['ok'] && 30 === $f12_r_stale['remaining'] && 0 === count( f12_pages( $F12_P, 'publish' ) ) );
$f12_r1 = $F12_P->svc->publish_pages( (string) $f12_p1['plan_digest'], $f12_phr, 30, 7 );
mb_test( 'Faz 12 yayın: ilk istek EN ÇOK 10 sayfa yayınlar (paused, 20 kaldı); yalnız draft->publish', true === $f12_r1['ok'] && 'paused' === $f12_r1['status'] && 10 === $f12_r1['published'] && 20 === $f12_r1['remaining'] && 10 === count( f12_pages( $F12_P, 'publish' ) ) && 22 === count( f12_pages( $F12_P, 'draft' ) ) );
$f12_r_dbl = $F12_P->svc->publish_pages( (string) $f12_p1['plan_digest'], $f12_phr, 30, 7 );
mb_test( 'Faz 12 yayın: çift tıklama (aynı expected_remaining=30) -> stale_request; ikinci yayın YOK (hâlâ 10)', 'stale_request' === $f12_r_dbl['error_code'] && 20 === $f12_r_dbl['remaining'] && 10 === count( f12_pages( $F12_P, 'publish' ) ) );
$f12_p2 = $F12_P->svc->preview_publish();
mb_test( 'Faz 12 yayın: özet yayın ilerledikçe DEĞİŞMEZ (aynı onay ifadesi tüm istekler için geçerli); ready 20, published 10', $f12_p2['plan_digest'] === $f12_p1['plan_digest'] && 20 === $f12_p2['summary']['ready'] && 10 === $f12_p2['summary']['published'] );
$f12_r2 = $F12_P->svc->publish_pages( (string) $f12_p1['plan_digest'], $f12_phr, 20, 7 );
$f12_r3 = $F12_P->svc->publish_pages( (string) $f12_p1['plan_digest'], $f12_phr, 10, 7 );
mb_test( 'Faz 12 yayın: 10 + 10 + 10 istekle 30 sayfa yayınlandı (completed); içerik bağımlılığı olan 2 sayfa (referanslar, sss) TASLAK kaldı; başka post/terim dokunulmadı',
	true === $f12_r2['ok'] && 'paused' === $f12_r2['status'] && 10 === $f12_r2['published'] && true === $f12_r3['ok'] && 'completed' === $f12_r3['status'] && 10 === $f12_r3['published'] && 0 === $f12_r3['remaining'] && 30 === count( f12_pages( $F12_P, 'publish' ) ) && 2 === count( f12_pages( $F12_P, 'draft' ) ) && 32 === count( $F12_P->world->posts )
	&& (function () use ( $F12_P ) {
		foreach ( f12_pages( $F12_P, 'draft' ) as $p ) {
			if ( ! in_array( $p['name'], array( 'referanslar', 'sss' ), true ) ) {
				return false;
			}
		}
		return true;
	})() );
$f12_audit_pub = array_values(
	array_filter(
		$F12_P->world->audit,
		function ( $a ) {
			return 'import_pages_published' === $a['event'];
		}
	)
);
mb_test( 'Faz 12 yayın audit: her batch için import_pages_published (3 olay: 10+10+10 madde); context yalnız source_key/tür/hedef ID (içerik YOK)',
	3 === count( $f12_audit_pub ) && 10 === count( $f12_audit_pub[0]['context']['items'] ) && 10 === count( $f12_audit_pub[2]['context']['items'] ) && array( 'source_key', 'target_id', 'type' ) === (function ( $k ) {
		sort( $k );
		return $k;
	})( array_keys( $f12_audit_pub[0]['context']['items'][0] ) ) && false === strpos( json_encode( $f12_audit_pub ), 'İskenderun' ) );
mb_test( 'Faz 12 yayın sonrası: pages planı hâlâ 32 unchanged (yayın durumu yönetilen alan DEĞİL, sahte conflict yok); ikinci yayın noop (completed/0)',
	32 === $F12_P->svc->preview_stage( 'pages' )['summary']['operations']['unchanged'] && (function () use ( $F12_P, $f12_p1, $f12_phr ) {
		$r = $F12_P->svc->publish_pages( (string) $f12_p1['plan_digest'], $f12_phr, 0, 7 );
		return true === $r['ok'] && 'completed' === $r['status'] && 0 === $r['published'];
	})() );
$f12_uid_p = $F12_P->world->runs[1]['uid'];
$f12_pub_rb = $F12_P->svc->preview_rollback( $f12_uid_p );
$f12_pub_rbs = $F12_P->svc->start_rollback( $f12_uid_p, (string) $f12_pub_rb['rollback_digest'], (string) $F12_G::rollback_phrase( $f12_uid_p, (string) $f12_pub_rb['rollback_digest'] ) );
mb_test( 'Faz 12 yayın sonrası rollback: önizleme yayındaki 30 sayfayı drift engeli olarak gösterir; rollback BAŞLATILAMAZ (drift_detected); hiçbir sayfa çöpe gitmez, yayın korunur',
	30 === count( $f12_pub_rb['blockers'] ) && 'drift_detected' === $f12_pub_rb['blockers'][0]['code'] && false === $f12_pub_rbs['ok'] && 'drift_detected' === $f12_pub_rbs['error_code'] && 0 === count( f12_pages( $F12_P, 'trash' ) ) && 30 === count( f12_pages( $F12_P, 'publish' ) ) );

// Kullanıcı değişikliği / bozuk durum / hata enjeksiyonu.
$F12_Q = f12_admin( $f12_dir_real );
f12_admin_apply_pages( $F12_Q );
list( $f12_qid, ) = f12_by_slug( $F12_Q, 'hakkimizda' );
$F12_Q->world->posts[ $f12_qid ]['content'] = '<p>Editör yeniden yazdı.</p>';
$f12_q1 = $F12_Q->svc->preview_publish();
mb_test( 'Faz 12 yayın: elle değiştirilmiş sayfa not_unchanged (yayınlanmaz, editör içeriği korunur); özet değişir; diğer hazırlar ready (29)',
	'not_unchanged' === $f12_q1['rows'][3]['reason'] && 29 === $f12_q1['summary']['ready'] && 1 === $f12_q1['summary']['not_unchanged'] && $f12_q1['plan_digest'] !== $f12_p1['plan_digest'] );
$F12_Q->world->posts[ $f12_qid ]['content'] = $f12_records[3]['content'];
$f12_q1b = $F12_Q->svc->preview_publish();
mb_test( 'Faz 12 yayın: elle değişiklik geri alınınca özet eski haline döner (özet yalnız içerik/kimlik/yayınlanabilirliğe bağlıdır)', $f12_q1b['plan_digest'] === $f12_p1['plan_digest'] && 30 === $f12_q1b['summary']['ready'] );
$F12_Q->world->posts[ $f12_qid ]['status'] = 'pending';
mb_test( 'Faz 12 yayın: beklenmeyen durumdaki (pending) sayfa unexpected_status olarak görünür ve yayınlanmaz', 'unexpected_status' === $F12_Q->svc->preview_publish()['rows'][3]['reason'] && 29 === $F12_Q->svc->preview_publish()['summary']['ready'] );
$F12_Q->world->posts[ $f12_qid ]['status'] = 'draft';

$F12_Fa = f12_admin( $f12_dir_real );
f12_admin_apply_pages( $F12_Fa );
$f12_fa_pv = $F12_Fa->svc->preview_publish();
list( $f12_fa_id, ) = f12_by_slug( $F12_Fa, 'hakkimizda' );
$F12_Fa->world->faults = array( array( 'op' => 'publish_page', 'source_key' => (string) $f12_fa_id ) );
$f12_fa_r = $F12_Fa->svc->publish_pages( (string) $f12_fa_pv['plan_digest'], (string) $f12_fa_pv['phrase'], 30, 7 );
mb_test( 'Faz 12 yayın atomikliği: batch içinde yazma hatası -> BÜTÜN batch geri alınır (0 sayfa yayınlandı), write_failed; audit yazılmadı',
	false === $f12_fa_r['ok'] && 'write_failed' === $f12_fa_r['error_code'] && 0 === count( f12_pages( $F12_Fa, 'publish' ) ) && 0 === count( array_filter( $F12_Fa->world->audit, function ( $a ) {
		return 'import_pages_published' === $a['event'];
	} ) ) );
$F12_Fb = f12_admin( $f12_dir_real );
f12_admin_apply_pages( $F12_Fb );
$F12_Fb->audit->failEvents = array( 'import_pages_published' );
$f12_fb_pv = $F12_Fb->svc->preview_publish();
$f12_fb_r  = $F12_Fb->svc->publish_pages( (string) $f12_fb_pv['plan_digest'], (string) $f12_fb_pv['phrase'], 30, 7 );
mb_test( 'Faz 12 yayın atomikliği: audit yazılamazsa batch transaction\'ı GERİ ALINIR (0 yayın), audit_failed', false === $f12_fb_r['ok'] && 'audit_failed' === $f12_fb_r['error_code'] && 0 === count( f12_pages( $F12_Fb, 'publish' ) ) );
$F12_Fc = f12_admin( $f12_dir_real );
f12_admin_apply_pages( $F12_Fc );
list( $f12_fc_id, ) = f12_by_slug( $F12_Fc, 'kalite-politikamiz' );
$F12_Fc->world->faults = array( array( 'op' => 'publish_corrupt', 'source_key' => (string) $f12_fc_id ) );
$f12_fc_pv = $F12_Fc->svc->preview_publish();
$f12_fc_r  = $F12_Fc->svc->publish_pages( (string) $f12_fc_pv['plan_digest'], (string) $f12_fc_pv['phrase'], 30, 7 );
mb_test( 'Faz 12 yayın readback: yayın sırasında yönetilen alan bozulursa readback_mismatch -> batch geri alınır (0 yayın)', false === $f12_fc_r['ok'] && 'readback_mismatch' === $f12_fc_r['error_code'] && 0 === count( f12_pages( $F12_Fc, 'publish' ) ) );
$F12_Fd = f12_admin( $f12_dir_real );
f12_admin_apply_pages( $F12_Fd );
$F12_Fd->world->runs[99] = array_merge( $F12_Fd->world->runs[1], array( 'id' => 99, 'uid' => sprintf( '%032x', 99 ), 'status' => 'paused' ) );
$f12_fd_pv = $F12_Fd->svc->preview_publish();
$f12_fd_r  = $F12_Fd->svc->publish_pages( (string) $f12_fd_pv['plan_digest'], (string) $f12_fd_pv['phrase'], 30, 7 );
mb_test( 'Faz 12 yayın: çözülmemiş (paused) import run\'ı varken yayınlanmaz (unresolved_run_exists)', false === $f12_fd_r['ok'] && 'unresolved_run_exists' === $f12_fd_r['error_code'] && 0 === count( f12_pages( $F12_Fd, 'publish' ) ) );
$F12_Fe = f12_admin( $f12_dir_real );
f12_admin_apply_pages( $F12_Fe );
$F12_Fe->store->locked = true;
$f12_fe_pv = $F12_Fe->svc->preview_publish();
$f12_fe_r  = $F12_Fe->svc->publish_pages( (string) $f12_fe_pv['plan_digest'], (string) $f12_fe_pv['phrase'], 30, 7 );
mb_test( 'Faz 12 yayın: paralel istek (kilit başka istekte) -> locked; yazma YOK', 'locked' === $f12_fe_r['error_code'] && 0 === count( f12_pages( $F12_Fe, 'publish' ) ) );

/* ================================================================ L2) Faz 12b: SSS/referans sayfaları yalnız içerik kayıtları oluşup YAYINLANDIKTAN sonra yayınlanır (sunucu tarafı) */
function f12_content_dir( $pagesDir, $faqCount = 3 ) {
	$dir = mb6b2_temp_dir( 'f12c' );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true );
	}
	copy( $pagesDir . '/pages.manifest.json', $dir . '/pages.manifest.json' );
	mb_content_fixture_write_dir( $dir, mb_content_fixture_envelopes( array(), $faqCount ) );
	return $dir;
}
function f12_content_apply( $o ) {
	// haber türü terimleri (content aşaması bağımlılığı) + content aşaması (doğrudan apply servisi; zincir admin katmanındadır)
	$o->world->terms[501] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'haber', 'name' => 'Haber', 'description' => '', 'parent' => 0, 'meta' => array() );
	$o->world->terms[502] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'duyuru', 'name' => 'Duyuru', 'description' => '', 'parent' => 0, 'meta' => array() );
	// Zincir (pages->...->all) admin katmanındadır; burada content aşaması DOĞRUDAN apply servisiyle uygulanır (yayın kapısı içerik kayıtlarına bakar).
	$p = $o->factory->apply_service()->preview( 'content' );
	return $o->factory->apply_service()->apply( 'content', (string) $p['plan_digest'], null, 7 );
}
function f12_records_of( $o, $postType ) {
	return array_filter(
		$o->world->posts,
		function ( $p ) use ( $postType ) {
			return $postType === $p['post_type'];
		}
	);
}
function f12_publish_content_records( $o ) {
	foreach ( $o->world->posts as $id => $post ) {
		if ( in_array( $post['post_type'], array( 'mb_sss', 'mb_referans' ), true ) ) {
			$o->world->posts[ $id ]['status'] = 'publish';
		}
	}
}
$f12_cdir = f12_content_dir( $f12_dir_real );
$F12_C    = f12_admin( $f12_cdir );
f12_admin_apply_pages( $F12_C );
$f12_c0 = $F12_C->svc->preview_publish();
mb_test( 'Faz 12b yayın kapısı: content aşaması uygulanmadan referanslar ve sss YAYINLANAMAZ (content_not_ready); diğer 30 sayfa hazır',
	2 === $f12_c0['summary']['content_not_ready'] && 30 === $f12_c0['summary']['ready'] && 'content_not_ready' === $f12_c0['rows'][9]['reason'] && 'content_not_ready' === $f12_c0['rows'][18]['reason'] );
$f12_c_r = f12_publish_all( $F12_C );
mb_test( 'Faz 12b yayın kapısı: 30 sayfa yayınlanır; referanslar/sss taslak kalır (sunucu tarafı; istemciye güvenilmez)',
	30 === count( f12_pages( $F12_C, 'publish' ) ) && 'draft' === f12_by_slug( $F12_C, 'referanslar' )[1]['status'] && 'draft' === f12_by_slug( $F12_C, 'sss' )[1]['status'] );
$f12_c_ap = f12_content_apply( $F12_C );
$f12_c1   = $F12_C->svc->preview_publish();
mb_test( 'Faz 12b yayın kapısı: content aşaması tamamlandı ama SSS/referans kayıtları TASLAK -> sayfalar hâlâ content_not_ready (boş liste yayınlanmaz)',
	true === $f12_c_ap['ok'] && 'completed' === $f12_c_ap['status'] && 3 === count( f12_records_of( $F12_C, 'mb_sss' ) ) && 3 === count( f12_records_of( $F12_C, 'mb_referans' ) ) && 2 === $f12_c1['summary']['content_not_ready'] && 0 === $f12_c1['summary']['ready'] );
f12_publish_content_records( $F12_C );
$f12_c2 = $F12_C->svc->preview_publish();
mb_test( 'Faz 12b yayın kapısı: kayıtlar oluşup yayınlanınca referanslar ve sss ready',
	2 === $f12_c2['summary']['ready'] && 0 === $f12_c2['summary']['content_not_ready'] && 'ready' === $f12_c2['rows'][9]['reason'] && 'ready' === $f12_c2['rows'][18]['reason'] );
$f12_c_r2 = $F12_C->svc->publish_pages( (string) $f12_c2['plan_digest'], (string) $f12_c2['phrase'], 2, 7 );
mb_test( 'Faz 12b yayın: içerik hazırken referanslar ve sss yayınlanır (32/32 yayında)',
	true === $f12_c_r2['ok'] && 'completed' === $f12_c_r2['status'] && 2 === $f12_c_r2['published'] && 32 === count( f12_pages( $F12_C, 'publish' ) ) );

// Yalnız SSS hazır, referanslar değil (bir referans kaydı taslak): yalnız sss yayınlanır.
$F12_C2 = f12_admin( f12_content_dir( $f12_dir_real ) );
f12_admin_apply_pages( $F12_C2 );
f12_content_apply( $F12_C2 );
f12_publish_content_records( $F12_C2 );
foreach ( $F12_C2->world->posts as $id => $post ) {
	if ( 'mb_referans' === $post['post_type'] && 'referans-02' === $post['name'] ) {
		$F12_C2->world->posts[ $id ]['status'] = 'draft';
	}
}
$f12_c3 = $F12_C2->svc->preview_publish();
mb_test( 'Faz 12b yayın kapısı: tek bir referans kaydı yayında değilse referanslar sayfası content_not_ready; sss ready',
	'content_not_ready' === $f12_c3['rows'][9]['reason'] && 'ready' === $f12_c3['rows'][18]['reason'] );

// Geçersiz/eksik logo: bağlı logo attachment'ı silinirse (özet uyuşmazlığı) referanslar sayfası yayınlanamaz.
$F12_C3 = f12_admin( f12_content_dir( $f12_dir_real ) );
f12_admin_apply_pages( $F12_C3 );
f12_content_apply( $F12_C3 );
f12_publish_content_records( $F12_C3 );
$F12_C3->world->logoAttachments = array();
$f12_c4 = $F12_C3->svc->preview_publish();
mb_test( 'Faz 12b yayın kapısı: referansın logo attachment\'ı geçersizse (silinmiş/özet uyuşmuyor) referanslar sayfası YAYINLANAMAZ; sss ready',
	'content_not_ready' === $f12_c4['rows'][9]['reason'] && 'ready' === $f12_c4['rows'][18]['reason'] );

// TOCTOU: önizleme sonrası içerik bozulursa yayın anında yeniden doğrulanır.
$F12_C4 = f12_admin( f12_content_dir( $f12_dir_real ) );
f12_admin_apply_pages( $F12_C4 );
f12_content_apply( $F12_C4 );
f12_publish_content_records( $F12_C4 );
$f12_c5 = $F12_C4->svc->preview_publish();
foreach ( $F12_C4->world->posts as $id => $post ) {
	if ( 'mb_sss' === $post['post_type'] ) {
		$F12_C4->world->posts[ $id ]['status'] = 'draft';
		break;
	}
}
$f12_c6 = $F12_C4->svc->publish_pages( (string) $f12_c5['plan_digest'], (string) $f12_c5['phrase'], 32, 7 );
mb_test( 'Faz 12b yayın TOCTOU: önizlemeden sonra SSS kaydı taslağa dönerse yayın stale_request/content_not_ready ile reddedilir; hiçbir sayfa yayınlanmaz',
	false === $f12_c6['ok'] && in_array( $f12_c6['error_code'], array( 'stale_request', 'content_not_ready', 'confirmation_mismatch' ), true ) && 0 === count( f12_pages( $F12_C4, 'publish' ) ) );

/* ================================================================ M) aşama zinciri: sunucu tarafında zorunlu */
mb_test( 'Faz 12 zincir: TEK kaynak Apply_Plan::PREREQUISITE_STAGE; admin servisi aynı haritayı kullanır; pages ilk aşama (önkoşulsuz)',
	array( 'pages' => null, 'sectors' => 'pages', 'qualifications' => 'sectors', 'all' => 'qualifications', 'content' => 'all' ) === $F12_AP::PREREQUISITE_STAGE && $F12_AP::PREREQUISITE_STAGE === MaviBelge_Core_Import_Admin_Run_Service::PREREQUISITE_STAGE && array_keys( $F12_AP::PREREQUISITE_STAGE ) === $F12_AP::ALL_STAGES );
$F12_Z = f12_admin( $f12_dir_real );
$f12_z_pre = $F12_Z->svc->prerequisites( 'sectors' );
mb_test( 'Faz 12 zincir: pages tamamlanmadan sectors önkoşulu karşılanmaz (requires pages); pages önkoşulsuz karşılanır; bilinmeyen aşama karşılanmaz', false === $f12_z_pre['met'] && 'pages' === $f12_z_pre['requires'] && true === $F12_Z->svc->prerequisites( 'pages' )['met'] && false === $F12_Z->svc->prerequisites( 'kontent' )['met'] );
f12_admin_apply_pages( $F12_Z );
mb_test( 'Faz 12 zincir: pages gerçek readback ile unchanged olunca sectors önkoşulu karşılanır', true === $F12_Z->svc->prerequisites( 'sectors' )['met'] );
$F12_Z->world->posts[ f12_by_slug( $F12_Z, 'myk' )[0] ]['title'] = 'Elle değişti';
mb_test( 'Faz 12 zincir: pages sonradan elle değişirse (conflict) sectors önkoşulu yeniden KARŞILANMAZ (zincir her istekte yeniden doğrulanır)', false === $F12_Z->svc->prerequisites( 'sectors' )['met'] );
$f12_cli_src = file_get_contents( dirname( __DIR__, 2 ) . '/includes/import/class-import-cli-command.php' );
mb_test( 'Faz 12 CLI: --apply admin ile AYNI zincir kuralını (Admin_Run_Service::prerequisites) çağırır; --stage seçeneklerinde pages ilk sırada',
	false !== strpos( $f12_cli_src, '->prerequisites( $stage )' ) && false !== strpos( $f12_cli_src, "options:\n	 *   - pages\n	 *   - sectors" ) && strpos( $f12_cli_src, '->prerequisites( $stage )' ) < strpos( $f12_cli_src, '$service->apply( $stage' ) );
