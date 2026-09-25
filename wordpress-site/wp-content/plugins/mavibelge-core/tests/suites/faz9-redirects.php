<?php
/**
 * Faz 9 — eski URL yönlendirme kayıt sistemi SAF testleri (MaviBelge_Core_Redirects_Rules). Test verisi sentetiktir
 * (ornek.example); gerçek eski URL envanteri tests/ dışındaki manifest ve Node testlerindedir.
 */

$RR = 'MaviBelge_Core_Redirects_Rules';
$rr = function ( $source, $target, array $extra = array() ) {
	return array_merge( array( 'source' => $source, 'target' => $target, 'status' => 301, 'origin' => 'verified', 'active' => true, 'note' => 'sentetik' ), $extra );
};

/* ---------------- yol normalizasyonu ---------------- */
mb_test( 'Faz 9 yönlendirme yolu: mutlak URL/yol -> küçük harf, sorgu+parça atılır, yüzde kodu çözülür, çift eğik çizgi tek, sonda eğik çizgi',
	'/eski-sayfa/' === $RR::normalize_path( 'https://ornek.example/Eski-Sayfa/?a=1#x' ) && '/eski-sayfa/' === $RR::normalize_path( '/eski-sayfa' ) && '/a/b/' === $RR::normalize_path( '//a//b' ) && '/ş/' === $RR::normalize_path( '/%C5%9F/' ) && '/eski-sayfa/' === $RR::normalize_path( 'HTTP://Ornek.Example/ESKI-SAYFA' ) );
mb_test( 'Faz 9 yönlendirme yolu: dosya uzantılı yollara eğik çizgi EKLENMEZ (.pdf, .xml); kök yol /',
	'/wp-content/uploads/2020/02/aday-taahhut.pdf' === $RR::normalize_path( 'https://ornek.example/wp-content/uploads/2020/02/aday-taahhut.pdf' ) && '/' === $RR::normalize_path( 'https://ornek.example' ) && '/' === $RR::normalize_path( '/' ) );
mb_test( 'Faz 9 yönlendirme yolu: dizin geçişi (..), kontrol karakteri, boşluk, joker (*), tırnak, ters eğik çizgi, göreli, boş, dizi, aşırı uzun -> null',
	null === $RR::normalize_path( '/a/../b/' ) && null === $RR::normalize_path( "/a\n/" ) && null === $RR::normalize_path( '/a b/' ) && null === $RR::normalize_path( '/a*/' ) && null === $RR::normalize_path( '/a"b/' ) && null === $RR::normalize_path( '/a\\b/' ) && null === $RR::normalize_path( 'a/b' )
	&& null === $RR::normalize_path( '' ) && null === $RR::normalize_path( array( '/a/' ) ) && null === $RR::normalize_path( '/' . str_repeat( 'a', 400 ) . '/' ) && null === $RR::normalize_path( '/a%00b/' ) && null === $RR::normalize_path( null ) );

/* ---------------- tek kural ---------------- */
mb_test( 'Faz 9 kural: geçerli 301/302/410 kuralı kabul; 410 hedef boş olmalı, 301/302 hedef zorunlu ve iç yol',
	array() === $RR::validate_rule( $rr( '/eski/', '/yeni/' ) ) && array() === $RR::validate_rule( $rr( '/eski/', '/yeni/', array( 'status' => 302 ) ) ) && array() === $RR::validate_rule( $rr( '/eski/', '', array( 'status' => 410 ) ) )
	&& in_array( 'gone_must_have_empty_target', $RR::validate_rule( $rr( '/eski/', '/yeni/', array( 'status' => 410 ) ) ), true ) && in_array( 'target_invalid_or_external', $RR::validate_rule( $rr( '/eski/', '' ) ), true ) );
mb_test( 'Faz 9 kural: dış/mutlak/protokolsüz-çift-eğik-çizgi hedef, javascript:/data: hedef reddedilir; toplu ana sayfa hedefi (/) REDDEDİLİR',
	in_array( 'target_invalid_or_external', $RR::validate_rule( $rr( '/eski/', 'https://kotu.example/' ) ), true ) && in_array( 'target_invalid_or_external', $RR::validate_rule( $rr( '/eski/', '//kotu.example/x' ) ), true ) && in_array( 'target_invalid_or_external', $RR::validate_rule( $rr( '/eski/', 'javascript:alert(1)' ) ), true )
	&& in_array( 'target_invalid_or_external', $RR::validate_rule( $rr( '/eski/', "/yeni/\r\nSet-Cookie: x=1" ) ), true ) && in_array( 'target_is_home_bulk_redirect', $RR::validate_rule( $rr( '/eski/', '/' ) ), true ) );
mb_test( 'Faz 9 kural: korumalı yollar (wp-admin, wp-login.php, wp-json, wp-sitemap, robots.txt, xmlrpc) ve ana sayfa kaynak olamaz',
	in_array( 'source_protected', $RR::validate_rule( $rr( '/wp-admin/x/', '/yeni/' ) ), true ) && in_array( 'source_protected', $RR::validate_rule( $rr( '/wp-login.php', '/yeni/' ) ), true ) && in_array( 'source_protected', $RR::validate_rule( $rr( '/wp-json/wp/v2/', '/yeni/' ) ), true )
	&& in_array( 'source_protected', $RR::validate_rule( $rr( '/wp-sitemap.xml', '/yeni/' ) ), true ) && in_array( 'source_protected', $RR::validate_rule( $rr( '/robots.txt', '/yeni/' ) ), true ) && in_array( 'source_is_home', $RR::validate_rule( $rr( '/', '/yeni/' ) ), true ) );
mb_test( 'Faz 9 kural: kapalı şema — fazla/eksik anahtar, geçersiz status/origin/active/note, dizi olmayan kural reddedilir',
	in_array( 'unexpected_keys', $RR::validate_rule( array_merge( $rr( '/a/', '/b/' ), array( 'ekstra' => 1 ) ) ), true ) && in_array( 'missing_keys', $RR::validate_rule( array( 'source' => '/a/' ) ), true ) && in_array( 'status_invalid', $RR::validate_rule( $rr( '/a/', '/b/', array( 'status' => 307 ) ) ), true )
	&& in_array( 'status_invalid', $RR::validate_rule( $rr( '/a/', '/b/', array( 'status' => '301' ) ) ), true ) && in_array( 'origin_invalid', $RR::validate_rule( $rr( '/a/', '/b/', array( 'origin' => 'guess' ) ) ), true ) && in_array( 'active_invalid', $RR::validate_rule( $rr( '/a/', '/b/', array( 'active' => 1 ) ) ), true )
	&& in_array( 'note_invalid', $RR::validate_rule( $rr( '/a/', '/b/', array( 'note' => str_repeat( 'n', 301 ) ) ) ), true ) && array( 'rule_not_array' ) === $RR::validate_rule( 'kural' ) );

/* ---------------- küme doğrulaması ---------------- */
$rr_codes = function ( $set ) use ( $RR ) {
	return array_column( $RR::validate_set( $set )['errors'], 'code' );
};
mb_test( 'Faz 9 küme: geçerli küme kabul; boş küme geçerli; küme dizi değilse ret',
	true === $RR::validate_set( array( $rr( '/a/', '/x/' ), $rr( '/b/', '/x/' ) ) )['valid'] && true === $RR::validate_set( array() )['valid'] && array( 'set_not_array' ) === $rr_codes( 'küme' ) );
mb_test( 'Faz 9 küme: ÇAKIŞAN kaynak (aynı normalize kaynak iki kez; aktiflik fark etmez) reddedilir',
	in_array( 'duplicate_source_conflict', $rr_codes( array( $rr( '/a/', '/x/' ), $rr( 'https://ornek.example/A', '/y/' ) ) ), true ) && in_array( 'duplicate_source_conflict', $rr_codes( array( $rr( '/a/', '/x/' ), $rr( '/a/', '/y/', array( 'active' => false ) ) ) ), true ) );
mb_test( 'Faz 9 küme: kendine yönlendirme, DÖNGÜ (A->B, B->A) ve ZİNCİR (A->B, B->C) reddedilir; pasif kural döngü/zincire girmez',
	in_array( 'self_redirect', $rr_codes( array( $rr( '/a/', '/a/' ) ) ), true ) && in_array( 'redirect_loop', $rr_codes( array( $rr( '/a/', '/b/' ), $rr( '/b/', '/a/' ) ) ), true ) && in_array( 'redirect_chain', $rr_codes( array( $rr( '/a/', '/b/' ), $rr( '/b/', '/c/' ) ) ), true )
	&& true === $RR::validate_set( array( $rr( '/a/', '/b/' ), $rr( '/b/', '/c/', array( 'active' => false ) ) ) )['valid'] && true === $RR::validate_set( array( $rr( '/a/', '/x/' ), $rr( '/b/', '/x/' ), $rr( '/c/', '/x/' ) ) )['valid'] );
mb_test( 'Faz 9 küme: 301 hedefi aktif bir 410 kaynağıysa (kırık hedef) reddedilir; uzun zincir (A->B->C->D) reddedilir',
	in_array( 'target_is_gone', $rr_codes( array( $rr( '/a/', '/b/' ), $rr( '/b/', '', array( 'status' => 410 ) ) ) ), true ) && in_array( 'redirect_chain', $rr_codes( array( $rr( '/a/', '/b/' ), $rr( '/b/', '/c/' ), $rr( '/c/', '/d/' ) ) ), true ) );
mb_test( 'Faz 9 küme: küme boyutu sınırı (2000); TEK geçersiz kural bütün seti reddeder (kısmi kabul yok)',
	in_array( 'too_many_rules', $rr_codes( array_map( function ( $i ) use ( $rr ) {
		return $rr( '/k' . $i . '/', '/t/' );
	}, range( 1, 2001 ) ) ), true ) && false === $RR::validate_set( array( $rr( '/a/', '/x/' ), $rr( '/b/', 'https://kotu.example/' ) ) )['valid'] );

/* ---------------- çalışma zamanı dizini ve eşleme ---------------- */
$rr_set = array( $rr( '/eski-1/', '/yeni-1/' ), $rr( '/eski-2/', '/yeni-2/', array( 'active' => false ) ), $rr( '/gone/', '', array( 'status' => 410 ) ) );
$rr_idx = $RR::build_index( $rr_set );
mb_test( 'Faz 9 dizin: yalnız AKTİF kurallar dizine girer (pasif yok); eşleme normalize edilmiş yolla (büyük harf, eğik çizgi, sorgu)',
	2 === count( $rr_idx ) && isset( $rr_idx['/eski-1/'], $rr_idx['/gone/'] ) && ! isset( $rr_idx['/eski-2/'] ) && '/yeni-1/' === $RR::match( $rr_idx, '/ESKI-1?utm=x' )['target'] && null === $RR::match( $rr_idx, '/eski-2/' ) && null === $RR::match( $rr_idx, '/bilinmeyen/' ) && null === $RR::match( $rr_idx, '/../x' ) && 410 === $RR::match( $rr_idx, '/gone' )['status'] );
mb_test( 'Faz 9 dizin: geçersiz küme FAIL-CLOSED — hiçbir yönlendirme dizini üretilmez (bozuk küme hiçbir şeyi yönlendirmez)',
	array() === $RR::build_index( array( $rr( '/a/', '/b/' ), $rr( '/b/', '/a/' ) ) ) && array() === $RR::build_index( 'bozuk' ) && array() === $RR::build_index( array( $rr( '/a/', 'https://kotu.example/' ) ) ) );

/* ---------------- özet ve dry-run ---------------- */
mb_test( 'Faz 9 özet: kural-seti özeti deterministik 64-hex; sıradan bağımsız; kaynak biçimi (büyük harf/mutlak URL) etkilemez; içerik değişirse değişir',
	1 === preg_match( '/^[0-9a-f]{64}\z/', $RR::digest( $rr_set ) ) && $RR::digest( $rr_set ) === $RR::digest( array_reverse( $rr_set ) ) && $RR::digest( array( $rr( '/a/', '/x/' ) ) ) === $RR::digest( array( $rr( 'https://ornek.example/A', '/x/' ) ) ) && $RR::digest( array( $rr( '/a/', '/x/' ) ) ) !== $RR::digest( array( $rr( '/a/', '/y/' ) ) ) );
$rr_dr = $RR::dry_run(
	array( $rr( '/yeni-kural/', '/hedef-var/' ), $rr( '/ayni/', '/x/' ), $rr( '/degisen/', '/y/' ), $rr( '/hedefsiz/', '/hedef-yok/' ), $rr( '/kotu/', 'https://kotu.example/' ) ),
	array( $rr( '/ayni/', '/x/' ), $rr( '/degisen/', '/eski-hedef/' ) ),
	function ( $path ) {
		return '/hedef-yok/' !== $path;
	}
);
mb_test( 'Faz 9 dry-run raporu: create/unchanged/update/invalid/inactive_target_missing sayıları; tek geçersiz kural yüzünden küme uygulanamaz (applicable=false); hiçbir şey yazmaz',
	false === $rr_dr['applicable'] && 2 === $rr_dr['summary']['create'] && 1 === $rr_dr['summary']['unchanged'] && 1 === $rr_dr['summary']['update'] && 1 === $rr_dr['summary']['invalid'] && 1 === $rr_dr['summary']['inactive_target_missing'] && 'inactive_target_missing' === $rr_dr['entries'][3]['note'] && 'invalid' === $rr_dr['entries'][4]['decision'] );
mb_test( 'Faz 9 dry-run: temiz küme applicable=true; hedef doğrulayıcı verilmezse hedef kontrolü yapılmaz; 410 kurallarında hedef kontrolü yok',
	true === $RR::dry_run( array( $rr( '/a/', '/b/' ) ), array() )['applicable'] && 0 === $RR::dry_run( array( $rr( '/a/', '/b/' ) ), array() )['summary']['inactive_target_missing'] && 0 === $RR::dry_run( array( $rr( '/a/', '', array( 'status' => 410 ) ) ), array(), function () {
		return false;
	} )['summary']['inactive_target_missing'] );
