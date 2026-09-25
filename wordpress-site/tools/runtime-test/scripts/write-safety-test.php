<?php
/**
 * TEST (Faz 6B3 Önkoşul) — yalnız izole runtime test veritabanında
 * `wp eval-file`. Kayıtlı WordPress meta yollarının yazma güvenliğini
 * GERÇEK WordPress 6.9.9 üzerinde doğrular:
 *  (1) kanonik kuruş hiçbir yolda x100 olmaz,
 *  (2) geçersiz giriş mevcut geçerli değeri EZMEZ (yazma reddedilir),
 *  (3) yanlış önekli/dizi marker sessizce '' olmaz (reddedilir),
 *  (4) saf yazma yükünün her meta değeri kayıtlı sanitize yolundan
 *      DEĞİŞMEDEN geçer (apply ileride yazsa bile dönüşmez) — bu adımda
 *      yük YAZILMAZ, yalnız sanitize_meta() ile sınanır.
 * Geçici test yazıları/terimleri oluşturur ve sonunda kalıcı olarak siler;
 * temizlik ayrıca doğrulanır. Üretim koduna yazma yolu eklemez.
 */
$results = array();
$t = function ( $label, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array( $label, (bool) $ok, $detail );
};
$show = function ( $v ) {
	return var_export( $v, true );
};
$pm = function ( $pid, $key ) {
	wp_cache_delete( $pid, 'post_meta' );
	return get_post_meta( $pid, $key, true );
};
$tm = function ( $tid, $key ) {
	wp_cache_delete( $tid, 'term_meta' );
	return get_term_meta( $tid, $key, true );
};
$createdPosts = array();
$createdTerms = array();

$fee = wp_insert_post( array( 'post_type' => 'mb_ucret', 'post_status' => 'draft', 'post_title' => 'write-safety-test ücret' ), true );
$qual = wp_insert_post( array( 'post_type' => 'mb_yeterlilik', 'post_status' => 'draft', 'post_title' => 'write-safety-test yeterlilik' ), true );
$createdPosts = array( $fee, $qual );
$cfg = MaviBelge_Core_Meta_Schema::get_fields_for( 'mb_ucret' )['_mb_certificate_print_fee_kurus'];

// (1) Kuruş — x100 yok.
update_post_meta( $fee, '_mb_certificate_print_fee_kurus', 150000 );
$t( 'kuruş: update_post_meta(150000) -> 150000', '150000' === (string) $pm( $fee, '_mb_certificate_print_fee_kurus' ), $show( $pm( $fee, '_mb_certificate_print_fee_kurus' ) ) );
update_post_meta( $fee, '_mb_certificate_print_fee_kurus', '1' );
list( $storage, $e1 ) = MaviBelge_Core_Field_Repository::admin_input_to_storage( $cfg, '1.500' );
list( $clean, $e2 )   = MaviBelge_Core_Field_Repository::sanitize_and_validate( $cfg, $storage );
update_post_meta( $fee, '_mb_certificate_print_fee_kurus', $clean );
$t( 'kuruş: admin zinciri "1.500" TL -> saklanan 150000 (önceki hata: 15000000)', null === $e1 && null === $e2 && '150000' === (string) $pm( $fee, '_mb_certificate_print_fee_kurus' ), $show( $pm( $fee, '_mb_certificate_print_fee_kurus' ) ) );
$r = MaviBelge_Core_Field_Repository::write_meta( $fee, 'mb_ucret', '_mb_certificate_print_fee_kurus', 175000 );
$t( 'kuruş: write_meta(175000) -> 175000', ! empty( $r['success'] ) && '175000' === (string) $pm( $fee, '_mb_certificate_print_fee_kurus' ), $show( $pm( $fee, '_mb_certificate_print_fee_kurus' ) ) );
update_post_meta( $fee, '_mb_min_amount_kurus', 1700000 );
update_post_meta( $fee, '_mb_max_amount_kurus', 2500000 );
$t( 'kuruş: min/max (money_kurus) kanonik kalır', '1700000' === (string) $pm( $fee, '_mb_min_amount_kurus' ) && '2500000' === (string) $pm( $fee, '_mb_max_amount_kurus' ), $show( $pm( $fee, '_mb_min_amount_kurus' ) ) );

// (2) Geçersiz giriş mevcut geçerli değeri EZMEZ.
foreach ( array( 'TL metni' => '1.500', 'negatif' => -1, 'float' => 1.5, 'bilimsel' => '1e5', 'taşma' => '99999999999999999999999', 'dizi' => array( 1 ), 'nesne' => new stdClass() ) as $label => $bad ) {
	update_post_meta( $fee, '_mb_certificate_print_fee_kurus', $bad );
	$t( "ezme yok: kuruş alanına {$label} -> önceki 175000 korunur", '175000' === (string) $pm( $fee, '_mb_certificate_print_fee_kurus' ), $show( $pm( $fee, '_mb_certificate_print_fee_kurus' ) ) );
}
update_post_meta( $qual, '_mb_level', '5' );
foreach ( array( '"9"' => '9', '"03"' => '03', 'dizi' => array( '3' ) ) as $label => $bad ) {
	update_post_meta( $qual, '_mb_level', $bad );
	$t( "ezme yok: _mb_level \"5\" üzerine {$label} -> \"5\" korunur (önceki tur: '')", '5' === $pm( $qual, '_mb_level' ), $show( $pm( $qual, '_mb_level' ) ) );
}
update_post_meta( $fee, '_mb_pricing_type', 'single' );
update_post_meta( $fee, '_mb_pricing_type', 'bogus' );
$t( 'ezme yok: _mb_pricing_type "single" üzerine "bogus" -> "single"', 'single' === $pm( $fee, '_mb_pricing_type' ), $show( $pm( $fee, '_mb_pricing_type' ) ) );
$validOptions = array( array( 'label' => 'Sınav ücreti', 'units' => array(), 'amount_kurus' => 100000, 'sort_order' => 0 ) );
update_post_meta( $fee, '_mb_price_options', $validOptions );
update_post_meta( $fee, '_mb_price_options', 'not an array' );
update_post_meta( $fee, '_mb_price_options', array( array( 'label' => '', 'units' => array(), 'amount_kurus' => -5, 'sort_order' => 0 ) ) );
$t( 'ezme yok: geçerli fiyat listesi, dizi-olmayan ve bozuk liste denemelerinden sonra korunur', $validOptions === $pm( $fee, '_mb_price_options' ), $show( $pm( $fee, '_mb_price_options' ) ) );
update_post_meta( $fee, '_mb_certificate_print_fee_kurus', '' );
$t( 'ezme yok kuralı açık temizlemeyi engellemez: kuruş alanına "" -> 0 (tanımlı değil)', '0' === (string) $pm( $fee, '_mb_certificate_print_fee_kurus' ), $show( $pm( $fee, '_mb_certificate_print_fee_kurus' ) ) );

// (3) Marker sözleşmesi (post + term).
update_post_meta( $fee, '_mb_import_source_key', 'sector:makine' );
$t( 'marker: mb_ucret\'e yanlış önekli marker -> yazılmaz (\'\' ile de yazılmaz; meta hiç oluşmaz)', '' === $pm( $fee, '_mb_import_source_key' ) && false === metadata_exists( 'post', $fee, '_mb_import_source_key' ), $show( $pm( $fee, '_mb_import_source_key' ) ) );
update_post_meta( $fee, '_mb_import_source_key', 'fee:test:3:write-safety' );
update_post_meta( $fee, '_mb_import_source_key', 'qualification:10UY0002-3/03' );
update_post_meta( $fee, '_mb_import_source_key', array( 'fee:test:3:x' ) );
update_post_meta( $fee, '_mb_import_source_key', 'fee:BOZUK' );
$t( 'marker: geçerli fee marker yazılır; ardından yanlış önek/dizi/biçimsiz denemeleri onu EZMEZ', 'fee:test:3:write-safety' === $pm( $fee, '_mb_import_source_key' ), $show( $pm( $fee, '_mb_import_source_key' ) ) );
$term = wp_insert_term( 'Write Safety Test Sektör', 'mb_sektor', array( 'slug' => 'write-safety-test' ) );
$tid  = (int) $term['term_id'];
$createdTerms[] = $tid;
update_term_meta( $tid, '_mb_import_source_key', 'sector:write-safety-test' );
update_term_meta( $tid, '_mb_import_source_key', 'fee:x:3:y' );
update_term_meta( $tid, '_mb_import_source_key', array( 'sector:write-safety-test' ) );
$t( 'marker (term): yanlış önek / dizi geçerli sektör marker\'ını ezmez', 'sector:write-safety-test' === $tm( $tid, '_mb_import_source_key' ), $show( $tm( $tid, '_mb_import_source_key' ) ) );
update_term_meta( $tid, '_mb_last_applied_hash', str_repeat( 'a', 64 ) );
update_term_meta( $tid, '_mb_last_applied_hash', 'ZZZ' );
update_term_meta( $tid, '_mb_last_applied_hash', array( 'x' ) );
$t( 'hash (term): geçersiz/dizi hash geçerli hash\'i ezmez', str_repeat( 'a', 64 ) === $tm( $tid, '_mb_last_applied_hash' ), $show( $tm( $tid, '_mb_last_applied_hash' ) ) );
$att = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'write-safety-att', 'post_status' => 'inherit' ), false );
$createdPosts[] = $att;
update_term_meta( $tid, '_mb_image_attachment_id', $att );
update_term_meta( $tid, '_mb_image_attachment_id', $qual );
update_term_meta( $tid, '_mb_image_attachment_id', 'abc' );
$t( 'görsel (term): attachment olmayan ID / biçimsiz değer mevcut görseli 0\'a düşürmez', (string) $att === (string) $tm( $tid, '_mb_image_attachment_id' ), $show( $tm( $tid, '_mb_image_attachment_id' ) ) );
update_term_meta( $tid, '_mb_icon_key', 'gear' );
update_term_meta( $tid, '_mb_icon_key', array( 'bolt' ) );
$t( 'icon (term): dizi mevcut icon_key\'i ezmez, "Array" yazılmaz', 'gear' === $tm( $tid, '_mb_icon_key' ), $show( $tm( $tid, '_mb_icon_key' ) ) );

// (3b) Son Kabul Düzeltmesi — update_metadata_by_mid() yolu (post + term).
global $wpdb;
$pmid = function ( $pid, $key ) use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1", $pid, $key ) );
};
$tmid = function ( $tid, $key ) use ( $wpdb ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1", $tid, $key ) );
};
$sentinelRows = function () use ( $wpdb ) {
	$like = '%' . $wpdb->esc_like( 'rejected-meta-write' ) . '%';
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like ) )
		+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_value LIKE %s", $like ) );
};

update_post_meta( $qual, '_mb_level', '5' );
$mid = $pmid( $qual, '_mb_level' );
$r1  = update_metadata_by_mid( 'post', $mid, '9' );
$t( 'by-mid (post, $meta_key=false): _mb_level "5" üzerine "9" -> false, "5" korunur', false === $r1 && '5' === $pm( $qual, '_mb_level' ), $show( $r1 ) . ' ' . $show( $pm( $qual, '_mb_level' ) ) );
$r2 = update_metadata_by_mid( 'post', $mid, '03', '_mb_level' );
$t( 'by-mid (post, açık $meta_key): _mb_level "03" -> false, "5" korunur', false === $r2 && '5' === $pm( $qual, '_mb_level' ), $show( $r2 ) );
$r3 = update_metadata_by_mid( 'post', $mid, array( '3' ) );
$t( 'by-mid (post): _mb_level dizi -> false, "5" korunur', false === $r3 && '5' === $pm( $qual, '_mb_level' ), $show( $r3 ) );
$r4 = update_metadata_by_mid( 'post', $mid, '6' );
$t( 'by-mid (post, $meta_key=false): geçerli "6" -> true ve "6"', true === $r4 && '6' === $pm( $qual, '_mb_level' ), $show( $r4 ) );
$r5 = update_metadata_by_mid( 'post', $mid, '7', '_mb_level' );
$t( 'by-mid (post, açık $meta_key): geçerli "7" -> true ve "7"', true === $r5 && '7' === $pm( $qual, '_mb_level' ), $show( $r5 ) );

update_post_meta( $fee, '_mb_certificate_print_fee_kurus', 150000 );
$mid = $pmid( $fee, '_mb_certificate_print_fee_kurus' );
$ok  = false === update_metadata_by_mid( 'post', $mid, '1.500' ) && false === update_metadata_by_mid( 'post', $mid, -1 ) && false === update_metadata_by_mid( 'post', $mid, 1.5 );
$t( 'by-mid (post, money_kurus): TL metni / negatif / float -> false, 150000 korunur', $ok && '150000' === (string) $pm( $fee, '_mb_certificate_print_fee_kurus' ), $show( $pm( $fee, '_mb_certificate_print_fee_kurus' ) ) );
$t( 'by-mid (post, money_kurus): geçerli 175000 -> true, x100 yok', true === update_metadata_by_mid( 'post', $mid, 175000 ) && '175000' === (string) $pm( $fee, '_mb_certificate_print_fee_kurus' ), $show( $pm( $fee, '_mb_certificate_print_fee_kurus' ) ) );

$mid = $pmid( $fee, '_mb_price_options' );
$ok  = false === update_metadata_by_mid( 'post', $mid, 'not an array' ) && false === update_metadata_by_mid( 'post', $mid, array( array( 'label' => '', 'units' => array(), 'amount_kurus' => -5, 'sort_order' => 0 ) ) );
$t( 'by-mid (post, price_options): dizi-olmayan / bozuk liste -> false, geçerli liste korunur', $ok && $validOptions === $pm( $fee, '_mb_price_options' ), '' );

$mid = $pmid( $fee, '_mb_import_source_key' );
$ok  = false === update_metadata_by_mid( 'post', $mid, 'sector:makine' ) && false === update_metadata_by_mid( 'post', $mid, array( 'fee:test:3:x' ) ) && false === update_metadata_by_mid( 'post', $mid, 'fee:BOZUK' );
$t( 'by-mid (post, _mb_import_source_key): yanlış önek / dizi / biçimsiz -> false, geçerli marker korunur', $ok && 'fee:test:3:write-safety' === $pm( $fee, '_mb_import_source_key' ), $show( $pm( $fee, '_mb_import_source_key' ) ) );
$t( 'by-mid (post, _mb_import_source_key): geçerli fee marker -> true', true === update_metadata_by_mid( 'post', $mid, 'fee:test:3:write-safety-2' ) && 'fee:test:3:write-safety-2' === $pm( $fee, '_mb_import_source_key' ), '' );

update_post_meta( $fee, '_mb_last_applied_hash', str_repeat( 'c', 64 ) );
$mid = $pmid( $fee, '_mb_last_applied_hash' );
$ok  = false === update_metadata_by_mid( 'post', $mid, 'ZZZ' ) && false === update_metadata_by_mid( 'post', $mid, array( 'x' ) );
$t( 'by-mid (post, _mb_last_applied_hash): geçersiz / dizi -> false, geçerli hash korunur', $ok && str_repeat( 'c', 64 ) === $pm( $fee, '_mb_last_applied_hash' ), '' );

$t( 'by-mid: var olmayan meta ID -> false (fail-closed)', false === update_metadata_by_mid( 'post', 999999999, '5' ) && false === update_metadata_by_mid( 'term', 999999999, 'x' ), '' );

$mid = $tmid( $tid, '_mb_import_source_key' );
$ok  = false === update_metadata_by_mid( 'term', $mid, 'fee:x:3:y' ) && false === update_metadata_by_mid( 'term', $mid, array( 'sector:write-safety-test' ) );
$t( 'by-mid (term, marker): yanlış önek / dizi -> false, geçerli marker korunur', $ok && 'sector:write-safety-test' === $tm( $tid, '_mb_import_source_key' ), $show( $tm( $tid, '_mb_import_source_key' ) ) );
$t( 'by-mid (term, marker): geçerli sektör marker -> true', true === update_metadata_by_mid( 'term', $mid, 'sector:write-safety-test-2' ) && 'sector:write-safety-test-2' === $tm( $tid, '_mb_import_source_key' ), '' );
$mid = $tmid( $tid, '_mb_last_applied_hash' );
$t( 'by-mid (term, hash): geçersiz -> false, korunur', false === update_metadata_by_mid( 'term', $mid, 'ZZZ' ) && str_repeat( 'a', 64 ) === $tm( $tid, '_mb_last_applied_hash' ), '' );
$mid = $tmid( $tid, '_mb_icon_key' );
$t( 'by-mid (term, icon): dizi -> false, "gear" korunur; geçerli "bolt" -> true', false === update_metadata_by_mid( 'term', $mid, array( 'bolt' ) ) && 'gear' === $tm( $tid, '_mb_icon_key' ) && true === update_metadata_by_mid( 'term', $mid, 'bolt' ) && 'bolt' === $tm( $tid, '_mb_icon_key' ), '' );
$mid = $tmid( $tid, '_mb_image_attachment_id' );
$t( 'by-mid (term, görsel): attachment olmayan ID -> false, mevcut görsel korunur', false === update_metadata_by_mid( 'term', $mid, $qual ) && (string) $att === (string) $tm( $tid, '_mb_image_attachment_id' ), '' );
$t( 'by-mid: REJECTED_META_WRITE işareti postmeta/termmeta\'da HİÇ saklanmadı', 0 === $sentinelRows(), (string) $sentinelRows() );
// (3c) By-Mid Kapsam Kapanışı — MaviBelge DIŞI meta alanlarında by-mid
// filtresi ön sanitizasyon YAPMAZ: harici sanitizer yalnız çekirdeğin normal
// çağrısıyla TAM BİR KEZ çalışır (Codex kanıtı: önceden sanitize_calls=2).
$extCalls = 0;
$extSanitizer = function ( $value ) use ( &$extCalls ) {
	$extCalls++;
	return $value;
};
$extPostKey = '_mb_test_external_meta';
$extTermKey = '_mb_test_external_term_meta';
register_post_meta( 'mb_yeterlilik', $extPostKey, array( 'type' => 'string', 'single' => true, 'sanitize_callback' => $extSanitizer ) );
register_term_meta( 'mb_sektor', $extTermKey, array( 'type' => 'string', 'single' => true, 'sanitize_callback' => $extSanitizer ) );
// Aynı adlı MaviBelge anahtarları ama MaviBelge DIŞI alt tür: 'post' yazısında
// _mb_level, 'category' teriminde _mb_icon_key (kesin kapsam denetimi).
register_post_meta( 'post', '_mb_level', array( 'type' => 'string', 'single' => true, 'sanitize_callback' => $extSanitizer ) );
register_term_meta( 'category', '_mb_icon_key', array( 'type' => 'string', 'single' => true, 'sanitize_callback' => $extSanitizer ) );
$plainPost = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'write-safety-test harici yazı' ), true );
$createdPosts[] = $plainPost;
$catTerm = wp_insert_term( 'Write Safety Test Kategori', 'category', array( 'slug' => 'write-safety-test-kategori' ) );
$catTid  = (int) $catTerm['term_id'];

add_post_meta( $qual, $extPostKey, 'first', true );
$mid      = $pmid( $qual, $extPostKey );
$extCalls = 0;
$r        = update_metadata_by_mid( 'post', $mid, 'second' );
$t( 'by-mid kapsam (harici post meta, $meta_key=false): true, "second" saklandı, sanitizer TAM 1 kez', true === $r && 'second' === $pm( $qual, $extPostKey ) && 1 === $extCalls, 'result=' . $show( $r ) . ' sanitize_calls=' . $extCalls );
$extCalls = 0;
$r        = update_metadata_by_mid( 'post', $mid, 'third', $extPostKey );
$t( 'by-mid kapsam (harici post meta, açık $meta_key): true, "third" saklandı, sanitizer TAM 1 kez', true === $r && 'third' === $pm( $qual, $extPostKey ) && 1 === $extCalls, 'result=' . $show( $r ) . ' sanitize_calls=' . $extCalls );

add_term_meta( $tid, $extTermKey, 'first', true );
$mid      = $tmid( $tid, $extTermKey );
$extCalls = 0;
$r        = update_metadata_by_mid( 'term', $mid, 'second' );
$t( 'by-mid kapsam (harici term meta, mb_sektor): true, "second" saklandı, sanitizer TAM 1 kez', true === $r && 'second' === $tm( $tid, $extTermKey ) && 1 === $extCalls, 'result=' . $show( $r ) . ' sanitize_calls=' . $extCalls );

add_post_meta( $plainPost, '_mb_level', 'x', true );
$mid      = $pmid( $plainPost, '_mb_level' );
$extCalls = 0;
$r        = update_metadata_by_mid( 'post', $mid, 'harici-deger' );
$t( 'by-mid kapsam (\'post\' yazısında _mb_level = MaviBelge dışı alt tür): MaviBelge doğrulaması uygulanmaz, true, sanitizer TAM 1 kez', true === $r && 'harici-deger' === $pm( $plainPost, '_mb_level' ) && 1 === $extCalls, 'result=' . $show( $r ) . ' sanitize_calls=' . $extCalls );

add_term_meta( $catTid, '_mb_icon_key', 'x', true );
$mid      = $tmid( $catTid, '_mb_icon_key' );
$extCalls = 0;
$r        = update_metadata_by_mid( 'term', $mid, 'Harici Değer' );
$t( 'by-mid kapsam (category teriminde _mb_icon_key = başka taksonomi): true, sanitizer TAM 1 kez', true === $r && 'Harici Değer' === $tm( $catTid, '_mb_icon_key' ) && 1 === $extCalls, 'result=' . $show( $r ) . ' sanitize_calls=' . $extCalls );

// Kayıtsız (sanitizer'sız) harici anahtar: çekirdek davranışı aynen.
add_post_meta( $qual, '_mb_test_unregistered_meta', 'a', true );
$mid = $pmid( $qual, '_mb_test_unregistered_meta' );
$t( 'by-mid kapsam (kayıtsız harici anahtar): çekirdek normal güncellemeyi yapar', true === update_metadata_by_mid( 'post', $mid, 'b' ) && 'b' === $pm( $qual, '_mb_test_unregistered_meta' ), '' );

// MaviBelge alanı AYNI çalıştırmada hâlâ korunuyor (kapsam denetimi korumayı gevşetmedi).
$mid = $pmid( $qual, '_mb_level' );
$t( 'by-mid kapsam: MaviBelge _mb_level hâlâ korunuyor (geçersiz "9" -> false, geçerli "5" -> true)', false === update_metadata_by_mid( 'post', $mid, '9' ) && true === update_metadata_by_mid( 'post', $mid, '5' ) && '5' === $pm( $qual, '_mb_level' ), $show( $pm( $qual, '_mb_level' ) ) );

unregister_post_meta( 'mb_yeterlilik', $extPostKey );
unregister_term_meta( 'mb_sektor', $extTermKey );
unregister_post_meta( 'post', '_mb_level' );
unregister_term_meta( 'category', '_mb_icon_key' );
wp_delete_term( $catTid, 'category' );
$t( 'by-mid kapsam: geçici category terimi silindi ve harici meta kayıtları geri alındı', ! get_term( $catTid, 'category' ) && ! registered_meta_key_exists( 'post', $extPostKey, 'mb_yeterlilik' ) && ! registered_meta_key_exists( 'term', '_mb_icon_key', 'category' ), '' );
$t( 'by-mid: REJECTED_META_WRITE işareti (3c sonrası) postmeta/termmeta\'da HİÇ saklanmadı', 0 === $sentinelRows(), (string) $sentinelRows() );


// (4) Saf yazma yükü kayıtlı sanitize yolundan DEĞİŞMEDEN geçer (yazılmaz).
$M = MaviBelge_Core_Import_Manifest_Loader::load_all()['manifest'];
$idempotent = function ( $payloadMeta, $objectType, $subtype ) {
	$bad = array();
	foreach ( $payloadMeta as $key => $value ) {
		$after = sanitize_meta( $key, $value, $objectType, $subtype );
		if ( $after !== $value ) {
			$bad[] = $key;
		}
	}
	return $bad;
};
$allOk = true;
$checked = 0;
foreach ( $M['fees'] as $rec ) {
	$v    = MaviBelge_Core_Import_Record_Validator::validate_fee( $rec );
	$deps = '' !== $rec['qualification_code'] ? array( 'qualification_post_id' => $qual ) : array();
	$f    = MaviBelge_Core_Import_Managed_Fields::project_fee( $rec, $deps, $v['normalized_price_options'] )['fields'];
	$p    = MaviBelge_Core_Import_Write_Payload::prepare( 'fee', $f, $rec['source_key'], MaviBelge_Core_Import_Hash::hash( $f ) );
	if ( ! $p['ok'] ) {
		$allOk = false;
		$t( 'yük: gerçek ücret kaydı hazırlanamadı ' . $rec['source_key'], false, implode( ';', $p['errors'] ) );
		continue;
	}
	$bad = $idempotent( $p['payload']['post_meta'], 'post', 'mb_ucret' );
	if ( $bad ) {
		$allOk = false;
		$t( 'yük: ' . $rec['source_key'] . ' meta değerleri sanitize\'da değişti', false, implode( ',', $bad ) );
	}
	$checked++;
}
$t( "yük (gerçek manifest): {$checked}/103 ücret yükünün tüm post_meta değerleri kayıtlı sanitize yolundan değişmeden geçer (kuruş x100 yok)", $allOk && 103 === $checked, '' );
$qOk = 0;
foreach ( $M['qualifications'] as $rec ) {
	$f = MaviBelge_Core_Import_Managed_Fields::project_qualification( $rec, array( 'sector_term_id' => $tid ) )['fields'];
	$p = MaviBelge_Core_Import_Write_Payload::prepare( 'qualification', $f, $rec['source_key'], MaviBelge_Core_Import_Hash::hash( $f ) );
	if ( $p['ok'] && array() === $idempotent( $p['payload']['post_meta'], 'post', 'mb_yeterlilik' ) ) {
		$qOk++;
	}
}
$t( "yük (gerçek manifest): {$qOk}/83 yeterlilik yükü sanitize'dan değişmeden geçer (_mb_level dahil)", 83 === $qOk, '' );
$sOk = 0;
foreach ( $M['sectors'] as $rec ) {
	$f = MaviBelge_Core_Import_Managed_Fields::project_sector( $rec, '' !== $rec['image'] ? array( 'image_attachment_id' => $att ) : array() )['fields'];
	$p = MaviBelge_Core_Import_Write_Payload::prepare( 'sector', $f, $rec['source_key'], MaviBelge_Core_Import_Hash::hash( $f ) );
	if ( $p['ok'] && array() === $idempotent( $p['payload']['term_meta'], 'term', 'mb_sektor' ) ) {
		$sOk++;
	}
}
$t( "yük (gerçek manifest): {$sOk}/14 sektör yükünün term_meta değerleri sanitize'dan değişmeden geçer", 14 === $sOk, '' );

// Temizlik (yalnız bu testin oluşturduğu kayıtlar) + doğrulama.
foreach ( $createdPosts as $pid ) {
	wp_delete_post( $pid, true );
}
foreach ( $createdTerms as $termId ) {
	wp_delete_term( $termId, 'mb_sektor' );
}
$left = 0;
foreach ( $createdPosts as $pid ) {
	$left += get_post( $pid ) ? 1 : 0;
}
foreach ( $createdTerms as $termId ) {
	$left += get_term( $termId, 'mb_sektor' ) ? 1 : 0;
}
$t( 'temizlik: bu testin oluşturduğu tüm yazı/terimler silindi', 0 === $left, (string) $left );
$extLike = $wpdb->esc_like( '_mb_test_' ) . '%';
$extLeft = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $extLike ) ) + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $extLike ) );
$t( 'temizlik: harici test meta satırı (_mb_test_*) kalmadı', 0 === $extLeft, (string) $extLeft );

$pass = 0;
foreach ( $results as $r ) {
	echo ( $r[1] ? 'PASS  ' : 'FAIL  ' ) . $r[0] . ( '' !== $r[2] ? '  [' . $r[2] . ']' : '' ) . "\n";
	$pass += $r[1] ? 1 : 0;
}
echo "\n{$pass}/" . count( $results ) . " yazma güvenliği WordPress runtime testi geçti.\n";
