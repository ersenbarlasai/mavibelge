<?php
/**
 * No ABSPATH guard here by design: this file is a CLI-only entry
 * point (`php tests/run.php`), never included by WordPress or served
 * over HTTP, so there is no direct-access risk to guard against.
 *
 * Standalone test runner for the WordPress-independent half of
 * MaviBelge_Core_Validator. Run with: php tests/run.php
 *
 * No PHPUnit/Composer dependency — this phase's composer.json
 * deliberately carries no runtime or dev packages (see
 * wordpress-site/composer.json), and a PHPCS/PHPCompatibility choice
 * is still pending (see docs/compatibility.md). This is NOT built on
 * PHP's assert() function — it is a small, dependency-free custom
 * pass/fail result writer (mb_test() below prints PASS/FAIL per case
 * and a final count), named precisely here because an earlier report
 * mis-described it as an "assert() harness".
 *
 * Coverage note: only tests/run.php's "format group" is covered here.
 * DB-dependent validator methods and the full CPT/taxonomy/roles/audit
 * registration flow need a real WordPress test environment, which was
 * not available when this suite was written (no `php` binary was
 * present in the authoring environment either — this file has not
 * been executed there; see the Faz 2 delivery report).
 */

require_once __DIR__ . '/bootstrap.php';

$failures = 0;
$total    = 0;

function mb_test( $description, $condition ) {
	global $failures, $total;
	$total++;
	if ( $condition ) {
		echo "PASS  {$description}\n";
	} else {
		$failures++;
		echo "FAIL  {$description}\n";
	}
}

/**
 * Faz 6B1 Zorunlu Dependency DTO Kapanışı §3.2 — TEK kanonik fixture: bu
 * dosyanın 100'den fazla `plan()`/`plan_sector()`/`plan_qualification()`/
 * `plan_fee()` çağrısının kullandığı, üç üst anahtarı (artık ZORUNLU,
 * bkz. `MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape()`)
 * boş-ama-GEÇERLİ map'lerle dolduran taban DTO. Belirli bir bağımlılık
 * gerektiren testler bunun üzerine yalnız ilgili anahtarı
 * `array_merge( mb_empty_dependencies(), array( '<anahtar>' => ... ) )`
 * ile ekler — kör metin değiştirme YOK, yalnız argüman-konumu bilinçli
 * olarak bu tek yardımcıya yönlendirildi.
 *
 * @return array{sector_term_ids: array, sector_image_attachment_ids: array, qualification_post_ids: array}
 */
function mb_empty_dependencies() {
	return array(
		'sector_term_ids'             => array(),
		'sector_image_attachment_ids' => array(),
		'qualification_post_ids'      => array(),
	);
}

/* -------------------------------------------------------------- *
 * is_valid_integer_range
 * -------------------------------------------------------------- */
mb_test( 'integer range: "3" within 1-8 is valid', MaviBelge_Core_Validator::is_valid_integer_range( '3', 1, 8 ) );
mb_test( 'integer range: "9" above max 8 is invalid', ! MaviBelge_Core_Validator::is_valid_integer_range( '9', 1, 8 ) );
mb_test( 'integer range: "0" below min 1 is invalid', ! MaviBelge_Core_Validator::is_valid_integer_range( '0', 1, 8 ) );
mb_test( 'integer range: "3.5" is invalid (not an integer)', ! MaviBelge_Core_Validator::is_valid_integer_range( '3.5', 0 ) );
mb_test( 'integer range: "abc" is invalid', ! MaviBelge_Core_Validator::is_valid_integer_range( 'abc', 0 ) );
mb_test( 'integer range: 0 with min 0 and no max is valid', MaviBelge_Core_Validator::is_valid_integer_range( 0, 0 ) );
mb_test( 'integer range: negative "-1" below min 0 is invalid', ! MaviBelge_Core_Validator::is_valid_integer_range( '-1', 0 ) );

/* -------------------------------------------------------------- *
 * is_valid_ymd_date
 * -------------------------------------------------------------- */
mb_test( 'date: empty string is allowed (optional field)', MaviBelge_Core_Validator::is_valid_ymd_date( '' ) );
mb_test( 'date: "2026-09-10" is valid', MaviBelge_Core_Validator::is_valid_ymd_date( '2026-09-10' ) );
mb_test( 'date: "2026-02-30" is invalid (no Feb 30)', ! MaviBelge_Core_Validator::is_valid_ymd_date( '2026-02-30' ) );
mb_test( 'date: "10-09-2026" wrong format is invalid', ! MaviBelge_Core_Validator::is_valid_ymd_date( '10-09-2026' ) );
mb_test( 'date: "2026/09/10" wrong separator is invalid', ! MaviBelge_Core_Validator::is_valid_ymd_date( '2026/09/10' ) );

/* -------------------------------------------------------------- *
 * is_valid_datetime
 * -------------------------------------------------------------- */
mb_test( 'datetime: "2026-09-10 14:30" is valid', MaviBelge_Core_Validator::is_valid_datetime( '2026-09-10 14:30' ) );
mb_test( 'datetime: "2026-09-10 14:30:00" with seconds is valid', MaviBelge_Core_Validator::is_valid_datetime( '2026-09-10 14:30:00' ) );
mb_test( 'datetime: "2026-13-10 14:30" invalid month is invalid', ! MaviBelge_Core_Validator::is_valid_datetime( '2026-13-10 14:30' ) );

/* -------------------------------------------------------------- *
 * is_valid_select / is_valid_slug_format / is_valid_myk_code_format
 * -------------------------------------------------------------- */
mb_test( 'select: "active" in allowed list is valid', MaviBelge_Core_Validator::is_valid_select( 'active', array( 'active', 'passive' ) ) );
mb_test( 'select: "deleted" not in allowed list is invalid', ! MaviBelge_Core_Validator::is_valid_select( 'deleted', array( 'active', 'passive' ) ) );

mb_test( 'slug: "guzellik-sac-bakim" is valid', MaviBelge_Core_Validator::is_valid_slug_format( 'guzellik-sac-bakim' ) );
mb_test( 'slug: "Güzellik Saç" with Turkish chars/spaces is invalid', ! MaviBelge_Core_Validator::is_valid_slug_format( 'Güzellik Saç' ) );

mb_test( 'myk code: "10UY0002-3/03" matches real qualifications.js pattern', MaviBelge_Core_Validator::is_valid_myk_code_format( '10UY0002-3/03' ) );
mb_test( 'myk code: empty string is allowed', MaviBelge_Core_Validator::is_valid_myk_code_format( '' ) );
mb_test( 'myk code: "not-a-code" is invalid', ! MaviBelge_Core_Validator::is_valid_myk_code_format( 'not-a-code' ) );

/* -------------------------------------------------------------- *
 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı Bulgu #1 — revizyon eki artık
 * OPSİYONEL. Aşağıdaki iki liste, tanitim-site/assets/data/qualifications.js
 * içindeki GERÇEK 83 kodun tam ayrımıdır (wordpress-site/tools/import/
 * extract-source.js ile 2026-09-13'te yeniden çıkarılıp sayıldı: 63
 * revizyonlu + 20 revizyonsuz = 83). Hiçbir kod uydurulmadı.
 *
 * NOT: bu dosya bu görevde de ÇALIŞTIRILMADI — ortamda `php` ikili
 * dosyası yok (bkz. teslim raporu "Çalıştırılamayan kontroller"). Bu
 * testler yalnız YAZILDI; gelecekte php mevcut olduğunda
 * `php tests/run.php` ile çalıştırılabilir.
 * -------------------------------------------------------------- */
$myk_codes_with_revision = array(
	'10UY0002-3/03', '10UY0002-4/03', '10UY0002-5/03', '12UY0105-3/00', '12UY0083-3/02',
	'12UY0084-4/02', '12UY0086-3/02', '12UY0087-4/02', '14UY0202-3/01', '14UY0202-4/01',
	'18UY0351-4/00', '15UY0227-3/00', '12UY0081-3/00', '13UY0148-3/01', '13UY0148-4/01',
	'12UY0070-3/00', '12UY0070-4/00', '13UY0149-4/00', '13UY0178-3/00', '13UY0173-4/00',
	'11UY0013-3/02', '11UY0010-3/04', '11UY0014-3/02', '11UY0016-4/03', '11UY0015-4/03',
	'15UY0218-2/01', '13UY0170-3/02', '12UY0061-3/04', '12UY0088-3/04', '12UY0069-3/02',
	'13UY0143-3/01', '13UY0142-3/01', '11UY0036-2/01', '11UY0037-2/01', '11UY0039-3/02',
	'13UY0137-3/01', '13UY0138-3/01', '13UY0139-3/01', '11UY0011-3/03', '11UY0012-3/03',
	'11UY0024-3/02', '11UY0023-3/02', '12UY0054-3/00', '12UY0055-3/00', '12UY0048-3/01',
	'16UY0253-2/00', '14UY0195-3/00', '12UY0051-3/01', '12UY0057-3/01', '12UY0056-3/01',
	'17UY0301-3/00', '17UY0301-4/00', '17UY0300-3/00', '16UY0266-3/01', '17UY0315-3/00',
	'16UY0267-4/01', '18UY0344-4/00', '16UY0244-4/02', '16UY0245-4/02', '17UY0280-3/01',
	'17UY0286-3/01', '16UY0242-3/02', '16UY0247-3/00',
);
$myk_codes_without_revision = array(
	'13UY0145-3', '15UY0220-4', '12UY0063-3', '17UY0328-3', '17UY0268-3',
	'12UY0064-3', '17UY0269-3', '15UY0221-3', '12UY0075-3', '15UY0241-3',
	'15UY0241-4', '15UY0206-3', '13UY0121-5', '18UY0356-4', '18UY0357-4',
	'18UY0358-4', '18UY0363-4', '18UY0379-4', '18UY0379-3', '16UY0265-3',
);
mb_test( 'myk code: gerçek kaynaktaki 63 revizyonlu kod sayısı doğru', 63 === count( $myk_codes_with_revision ) );
mb_test( 'myk code: gerçek kaynaktaki 20 revizyonsuz kod sayısı doğru', 20 === count( $myk_codes_without_revision ) );

$all_with_revision_valid = true;
foreach ( $myk_codes_with_revision as $code ) {
	if ( ! MaviBelge_Core_Validator::is_valid_myk_code_format( $code ) ) {
		$all_with_revision_valid = false;
		break;
	}
}
mb_test( 'myk code: 63 revizyonlu gerçek kodun HEPSİ geçerli kabul edilir', $all_with_revision_valid );

$all_without_revision_valid = true;
foreach ( $myk_codes_without_revision as $code ) {
	if ( ! MaviBelge_Core_Validator::is_valid_myk_code_format( $code ) ) {
		$all_without_revision_valid = false;
		break;
	}
}
mb_test( 'myk code: 20 revizyonsuz gerçek kodun HEPSİ artık geçerli kabul edilir (eski bloklayıcı açık kapatıldı)', $all_without_revision_valid );

/* -------------------------------------------------------------- *
 * parse_myk_code / myk_code_matches_level_revision — çapraz-alan kuralı
 * -------------------------------------------------------------- */
$parsed_with = MaviBelge_Core_Validator::parse_myk_code( '10UY0002-3/03' );
mb_test( 'parse_myk_code: revizyonlu kod seviye/revizyon/has_revision doğru çıkarılır', 3 === $parsed_with['level'] && '03' === $parsed_with['revision'] && true === $parsed_with['has_revision'] );

$parsed_without = MaviBelge_Core_Validator::parse_myk_code( '13UY0145-3' );
mb_test( 'parse_myk_code: revizyonsuz kod seviye doğru, revision boş, has_revision false', 3 === $parsed_without['level'] && '' === $parsed_without['revision'] && false === $parsed_without['has_revision'] );

mb_test( 'myk_code_matches_level_revision: boş kod her zaman true', MaviBelge_Core_Validator::myk_code_matches_level_revision( '', 3, '03' ) );
mb_test( 'myk_code_matches_level_revision: seviye/revizyon eşleşince true', MaviBelge_Core_Validator::myk_code_matches_level_revision( '10UY0002-3/03', 3, '03' ) );
mb_test( 'myk_code_matches_level_revision: seviye uyuşmayınca false', ! MaviBelge_Core_Validator::myk_code_matches_level_revision( '10UY0002-3/03', 4, '03' ) );
mb_test( 'myk_code_matches_level_revision: revizyon uyuşmayınca false', ! MaviBelge_Core_Validator::myk_code_matches_level_revision( '10UY0002-3/03', 3, '99' ) );
// Faz 6A Son Kabul Düzeltmesi: revizyonsuz kodda _mb_revision artık "serbest
// alan" DEĞİL — kesinlikle BOŞ olmalı (eski test burada tam tersini iddia
// ediyordu, bağımsız incelemede Node/PHP sözleşme farkı olarak bulundu).
mb_test( 'myk_code_matches_level_revision: revizyonsuz kod + DOLU revizyon artık false (eski davranış: "serbest alan", ARTIK YANLIŞ)', ! MaviBelge_Core_Validator::myk_code_matches_level_revision( '13UY0145-3', 3, '99' ) );
mb_test( 'myk_code_matches_level_revision: revizyonsuz kod + BOŞ revizyon true', MaviBelge_Core_Validator::myk_code_matches_level_revision( '13UY0145-3', 3, '' ) );
mb_test( 'myk_code_matches_level_revision: revizyonsuz kodda seviye yine de kontrol edilir', ! MaviBelge_Core_Validator::myk_code_matches_level_revision( '13UY0145-3', 4, '' ) );

/* -------------------------------------------------------------- *
 * Faz 6A Son Kabul Düzeltmesi §7 — is_valid_import_source_key() /
 * is_valid_sha256_hash(): Faz 6B sistem-yönetimli import alanlarının
 * (post-meta VE mb_sektor term-meta) TEK paylaşılan biçim kuralları.
 * -------------------------------------------------------------- */
mb_test( 'import_source_key: boş değer her zaman geçerli (üç tip için de)', MaviBelge_Core_Validator::is_valid_import_source_key( '', 'qualification' ) && MaviBelge_Core_Validator::is_valid_import_source_key( '', 'fee' ) && MaviBelge_Core_Validator::is_valid_import_source_key( '', 'sector' ) );
mb_test( 'import_source_key: doğru "qualification:" biçimi (revizyonlu) geçerli', MaviBelge_Core_Validator::is_valid_import_source_key( 'qualification:10UY0002-3/03', 'qualification' ) );
mb_test( 'import_source_key: doğru "qualification:" biçimi (revizyonsuz) geçerli', MaviBelge_Core_Validator::is_valid_import_source_key( 'qualification:13UY0145-3', 'qualification' ) );
mb_test( 'import_source_key: doğru "fee:" biçimi geçerli', MaviBelge_Core_Validator::is_valid_import_source_key( 'fee:makine:3:makine-bakimci', 'fee' ) );
mb_test( 'import_source_key: doğru "sector:" biçimi geçerli', MaviBelge_Core_Validator::is_valid_import_source_key( 'sector:guzellik-sac-bakim', 'sector' ) );
mb_test( 'import_source_key: yanlış prefix reddedilir ("fee:" değeri "qualification" tipine verilince)', ! MaviBelge_Core_Validator::is_valid_import_source_key( 'fee:makine:3:makine-bakimci', 'qualification' ) );
mb_test( 'import_source_key: boşluk içeren değer reddedilir', ! MaviBelge_Core_Validator::is_valid_import_source_key( 'sector: guzellik', 'sector' ) );
mb_test( 'import_source_key: kontrol karakteri içeren değer reddedilir', ! MaviBelge_Core_Validator::is_valid_import_source_key( "sector:guzellik\tsac", 'sector' ) );
mb_test( 'import_source_key: tamamen alakasız prefix reddedilir', ! MaviBelge_Core_Validator::is_valid_import_source_key( 'evil:payload', 'sector' ) );
mb_test( 'import_source_key: bilinmeyen tip her zaman false döner', ! MaviBelge_Core_Validator::is_valid_import_source_key( 'sector:guzellik', 'unknown-type' ) );

mb_test( 'sha256_hash: boş değer geçerli', MaviBelge_Core_Validator::is_valid_sha256_hash( '' ) );
mb_test( 'sha256_hash: tam 64 küçük-hex karakter geçerli', MaviBelge_Core_Validator::is_valid_sha256_hash( str_repeat( 'a1', 32 ) ) );
mb_test( 'sha256_hash: 63 karakter (bir eksik) reddedilir', ! MaviBelge_Core_Validator::is_valid_sha256_hash( str_repeat( 'a', 63 ) ) );
mb_test( 'sha256_hash: 65 karakter (bir fazla) reddedilir', ! MaviBelge_Core_Validator::is_valid_sha256_hash( str_repeat( 'a', 65 ) ) );
mb_test( 'sha256_hash: büyük harfli hex reddedilir (kaynak her zaman küçük harf üretir)', ! MaviBelge_Core_Validator::is_valid_sha256_hash( str_repeat( 'A', 64 ) ) );
mb_test( 'sha256_hash: hex olmayan karakter içeren 64 karakter reddedilir', ! MaviBelge_Core_Validator::is_valid_sha256_hash( str_repeat( 'g', 64 ) ) );

/* -------------------------------------------------------------- *
 * normalize_price_options — the core "103 ücret / 145 seçenek
 * kayıpsız taşınabilir" acceptance criterion.
 * -------------------------------------------------------------- */
$multi_option_raw = array(
	array( 'label' => 'A1+B1+B2', 'units' => array( 'A1', 'B1', 'B2' ), 'amount_kurus' => 1575000, 'sort_order' => 1 ),
	array( 'label' => 'A1+B1+B2+B4+B5', 'units' => array( 'A1', 'B1', 'B2', 'B4', 'B5' ), 'amount_kurus' => 2475000, 'sort_order' => 0 ),
);
$result = MaviBelge_Core_Validator::normalize_price_options( $multi_option_raw );
mb_test( 'price options: two valid rows both kept (no data loss)', 2 === count( $result['options'] ) );
mb_test( 'price options: no errors for well-formed rows', empty( $result['errors'] ) );
mb_test( 'price options: sorted by sort_order (0 before 1)', 'A1+B1+B2+B4+B5' === $result['options'][0]['label'] );
mb_test( 'price options: min_kurus is the smaller amount', 1575000 === $result['min_kurus'] );
mb_test( 'price options: max_kurus is the larger amount', 2475000 === $result['max_kurus'] );

$blank_filler_row = array(
	array( 'label' => 'Sınav ücreti', 'units' => array( 'A1' ), 'amount_kurus' => 1700000, 'sort_order' => 0 ),
	array( 'label' => '', 'units' => array(), 'amount_kurus' => '', 'sort_order' => 0 ), // empty admin filler row
);
$result2 = MaviBelge_Core_Validator::normalize_price_options( $blank_filler_row );
mb_test( 'price options: empty filler row is silently dropped, not an error', 1 === count( $result2['options'] ) && empty( $result2['errors'] ) );

$invalid_amount_row = array(
	array( 'label' => 'Geçersiz', 'units' => array(), 'amount_kurus' => '-5', 'sort_order' => 0 ),
);
$result3 = MaviBelge_Core_Validator::normalize_price_options( $invalid_amount_row );
mb_test( 'price options: negative amount produces an error and is dropped', 0 === count( $result3['options'] ) && 1 === count( $result3['errors'] ) );

/* -------------------------------------------------------------- *
 * normalize_string_list / normalize_phone_list
 * -------------------------------------------------------------- */
mb_test(
	'string list: blank lines dropped, order kept',
	array( 'A1', 'B1' ) === MaviBelge_Core_Validator::normalize_string_list( array( 'A1', '', ' B1 ' ) )
);

mb_test(
	'phone list: strips letters, keeps digits/format chars',
	array( '0312 555 00 00' ) === MaviBelge_Core_Validator::normalize_phone_list( array( 'Tel: 0312 555 00 00' ) )
);

/* -------------------------------------------------------------- *
 * is_valid_datetime — hour/minute/second bounds (Faz2 düzeltme §3.5).
 * A loose \d{2}:\d{2} pattern let "99:99" through; the fix bounds
 * hour to 00-23 and minute/second to 00-59.
 * -------------------------------------------------------------- */
mb_test( 'datetime: "23:59:59" at the upper bound is valid', MaviBelge_Core_Validator::is_valid_datetime( '2026-09-10 23:59:59' ) );
mb_test( 'datetime: "00:00:00" at the lower bound is valid', MaviBelge_Core_Validator::is_valid_datetime( '2026-09-10 00:00:00' ) );
mb_test( 'datetime: "99:99" out-of-range hour/minute is invalid', ! MaviBelge_Core_Validator::is_valid_datetime( '2026-09-10 99:99' ) );
mb_test( 'datetime: "24:00" hour 24 is invalid (23 is the max)', ! MaviBelge_Core_Validator::is_valid_datetime( '2026-09-10 24:00' ) );
mb_test( 'datetime: "12:60" minute 60 is invalid (59 is the max)', ! MaviBelge_Core_Validator::is_valid_datetime( '2026-09-10 12:60' ) );

/* -------------------------------------------------------------- *
 * is_valid_positive_integer_string — ID list format check BEFORE
 * casting to int (Faz2 düzeltme §3.6). (int)"123abc" === 123 in PHP,
 * which is exactly the silent-garbage-becomes-valid bug being tested
 * against here.
 * -------------------------------------------------------------- */
mb_test( 'positive int string: "123" is valid', MaviBelge_Core_Validator::is_valid_positive_integer_string( '123' ) );
mb_test( 'positive int string: "123abc" is invalid (PHP (int) cast would silently accept it as 123)', ! MaviBelge_Core_Validator::is_valid_positive_integer_string( '123abc' ) );
mb_test( 'positive int string: "abc123" is invalid', ! MaviBelge_Core_Validator::is_valid_positive_integer_string( 'abc123' ) );
mb_test( 'positive int string: "-5" is invalid (no sign allowed)', ! MaviBelge_Core_Validator::is_valid_positive_integer_string( '-5' ) );
mb_test( 'positive int string: "0" is invalid (must be positive)', ! MaviBelge_Core_Validator::is_valid_positive_integer_string( '0' ) );
mb_test( 'positive int string: " 42 " with surrounding whitespace is valid (trimmed)', MaviBelge_Core_Validator::is_valid_positive_integer_string( ' 42 ' ) );

/* -------------------------------------------------------------- *
 * try_lira_to_kurus — Türkçe TL -> kuruş dönüşümü (Faz2 düzeltme §3.4).
 * The brief's own four documented examples, plus the rejection cases
 * it explicitly calls out (negative, zero, >2 decimals, letters,
 * scientific notation).
 * -------------------------------------------------------------- */
mb_test( 'TL->kuruş: "17.000" (thousands, no decimal) is 1700000', 1700000 === MaviBelge_Core_Validator::try_lira_to_kurus( '17.000' ) );
mb_test( 'TL->kuruş: "17000" (plain digits) is 1700000', 1700000 === MaviBelge_Core_Validator::try_lira_to_kurus( '17000' ) );
mb_test( 'TL->kuruş: "17.000,00" (thousands + comma decimal) is 1700000', 1700000 === MaviBelge_Core_Validator::try_lira_to_kurus( '17.000,00' ) );
mb_test( 'TL->kuruş: "17000,00" (plain + comma decimal) is 1700000', 1700000 === MaviBelge_Core_Validator::try_lira_to_kurus( '17000,00' ) );
mb_test( 'TL->kuruş: "17.000,50" is 1700050 (50 kuruş)', 1700050 === MaviBelge_Core_Validator::try_lira_to_kurus( '17.000,50' ) );
mb_test( 'TL->kuruş: "1.575.000" (two thousands groups) is 157500000', 157500000 === MaviBelge_Core_Validator::try_lira_to_kurus( '1.575.000' ) );
mb_test( 'TL->kuruş: "-17.000" negative is rejected', null === MaviBelge_Core_Validator::try_lira_to_kurus( '-17.000' ) );
mb_test( 'TL->kuruş: "0" zero is rejected', null === MaviBelge_Core_Validator::try_lira_to_kurus( '0' ) );
mb_test( 'TL->kuruş: "17,5" only 1 decimal digit is rejected (must be exactly 2)', null === MaviBelge_Core_Validator::try_lira_to_kurus( '17,5' ) );
mb_test( 'TL->kuruş: "17.000,123" more than 2 decimals is rejected', null === MaviBelge_Core_Validator::try_lira_to_kurus( '17.000,123' ) );
mb_test( 'TL->kuruş: "17.00" period without a full 3-digit group is rejected', null === MaviBelge_Core_Validator::try_lira_to_kurus( '17.00' ) );
mb_test( 'TL->kuruş: "1e5" scientific notation is rejected', null === MaviBelge_Core_Validator::try_lira_to_kurus( '1e5' ) );
mb_test( 'TL->kuruş: "on yedi bin" letters are rejected', null === MaviBelge_Core_Validator::try_lira_to_kurus( 'on yedi bin' ) );

mb_test( 'kuruş->TL display: 1700000 formats as "17.000,00"', '17.000,00' === MaviBelge_Core_Validator::kurus_to_lira_display( 1700000 ) );
mb_test( 'kuruş->TL display: 1700050 formats as "17.000,50"', '17.000,50' === MaviBelge_Core_Validator::kurus_to_lira_display( 1700050 ) );

/* -------------------------------------------------------------- *
 * normalize_price_options — deep sanitize + limits (Faz2 düzeltme §3.3).
 * -------------------------------------------------------------- */
$html_label_result = MaviBelge_Core_Validator::normalize_price_options(
	array( array( 'label' => '<script>alert(1)</script>A1', 'units' => array( '<b>A1</b>', 'B1' ), 'amount_kurus' => 1000, 'sort_order' => 0 ) )
);
mb_test(
	'price options: label/unit text is deep-sanitized (tags stripped), row still kept',
	1 === count( $html_label_result['options'] )
	&& false === strpos( $html_label_result['options'][0]['label'], '<script>' )
	&& false === strpos( $html_label_result['options'][0]['units'][0], '<b>' )
);

$array_as_label_result = MaviBelge_Core_Validator::normalize_price_options(
	array( array( 'label' => array( 'unexpected' => 'array' ), 'amount_kurus' => 1000 ) )
);
mb_test(
	'price options: an array where a label string is expected is rejected, not stringified',
	0 === count( $array_as_label_result['options'] ) && 1 === count( $array_as_label_result['errors'] )
);

$too_many_rows = array();
for ( $i = 0; $i < MaviBelge_Core_Validator::MAX_PRICE_OPTIONS + 1; $i++ ) {
	$too_many_rows[] = array( 'label' => 'Seçenek ' . $i, 'amount_kurus' => 100 + $i, 'sort_order' => $i );
}
$too_many_result = MaviBelge_Core_Validator::normalize_price_options( $too_many_rows );
mb_test(
	'price options: submitting more than MAX_PRICE_OPTIONS rows produces an error',
	! empty( $too_many_result['errors'] )
);

/* -------------------------------------------------------------- *
 * Faz 5 Son Kapanış Düzeltmesi §2 — units/sort_order must reject
 * atomically, not silently repair (drop/truncate/positional-fallback).
 * -------------------------------------------------------------- */

$nested_unit_result = MaviBelge_Core_Validator::normalize_price_options(
	array( array( 'label' => 'A1', 'amount_kurus' => 1000, 'units' => array( 'adet', array( 'nested' => true ) ) ) )
);
mb_test( 'price options: a nested array/object unit item rejects the WHOLE list (0 options, non-empty errors)', 0 === count( $nested_unit_result['options'] ) && ! empty( $nested_unit_result['errors'] ) );

$ten_units_row       = array( 'label' => 'A1', 'amount_kurus' => 1000, 'units' => array( 'u1', 'u2', 'u3', 'u4', 'u5', 'u6', 'u7', 'u8', 'u9', 'u10' ) );
$ten_units_result    = MaviBelge_Core_Validator::normalize_price_options( array( $ten_units_row ) );
mb_test( 'price options: exactly MAX_UNITS_PER_OPTION (10) units -> accepted, no errors', 1 === count( $ten_units_result['options'] ) && empty( $ten_units_result['errors'] ) );

$eleven_units_row    = $ten_units_row;
$eleven_units_row['units'][] = 'u11';
$eleven_units_result = MaviBelge_Core_Validator::normalize_price_options( array( $eleven_units_row ) );
mb_test(
	'price options: MAX_UNITS_PER_OPTION + 1 (11) units -> the WHOLE list is rejected, not silently trimmed to 10',
	0 === count( $eleven_units_result['options'] ) && ! empty( $eleven_units_result['errors'] )
);

$boundary_unit_row    = array( 'label' => 'A1', 'amount_kurus' => 1000, 'units' => array( str_repeat( 'x', MaviBelge_Core_Validator::MAX_UNIT_LENGTH ) ) );
$boundary_unit_result = MaviBelge_Core_Validator::normalize_price_options( array( $boundary_unit_row ) );
mb_test( 'price options: a unit at exactly MAX_UNIT_LENGTH -> accepted, unchanged', 1 === count( $boundary_unit_result['options'] ) && MaviBelge_Core_Validator::MAX_UNIT_LENGTH === strlen( $boundary_unit_result['options'][0]['units'][0] ) );

$over_length_unit_row    = array( 'label' => 'A1', 'amount_kurus' => 1000, 'units' => array( str_repeat( 'x', MaviBelge_Core_Validator::MAX_UNIT_LENGTH + 1 ) ) );
$over_length_unit_result = MaviBelge_Core_Validator::normalize_price_options( array( $over_length_unit_row ) );
mb_test(
	'price options: a unit one character over MAX_UNIT_LENGTH -> the WHOLE list is rejected, not silently truncated',
	0 === count( $over_length_unit_result['options'] ) && ! empty( $over_length_unit_result['errors'] )
);

$omitted_sort_order_result = MaviBelge_Core_Validator::normalize_price_options(
	array(
		array( 'label' => 'First', 'amount_kurus' => 1000 ),
		array( 'label' => 'Second', 'amount_kurus' => 2000 ),
	)
);
mb_test(
	'price options: sort_order OMITTED entirely on every row -> documented positional fallback (0, 1, in submission order), no errors',
	empty( $omitted_sort_order_result['errors'] )
	&& 0 === $omitted_sort_order_result['options'][0]['sort_order']
	&& 1 === $omitted_sort_order_result['options'][1]['sort_order']
	&& 'First' === $omitted_sort_order_result['options'][0]['label']
);

foreach ( array( '', '-1', 'abc', array( 'x' ) ) as $bad_sort_order ) {
	$bad_sort_order_result = MaviBelge_Core_Validator::normalize_price_options(
		array( array( 'label' => 'A1', 'amount_kurus' => 1000, 'sort_order' => $bad_sort_order ) )
	);
	mb_test(
		'price options: sort_order PRESENT but invalid (' . ( is_array( $bad_sort_order ) ? 'array' : var_export( $bad_sort_order, true ) ) . ') -> the row (and whole list) is rejected, not silently positional-fallback',
		0 === count( $bad_sort_order_result['options'] ) && ! empty( $bad_sort_order_result['errors'] )
	);
}

$equal_sort_order_result = MaviBelge_Core_Validator::normalize_price_options(
	array(
		array( 'label' => 'Third', 'amount_kurus' => 3000, 'sort_order' => 5 ),
		array( 'label' => 'Fourth', 'amount_kurus' => 4000, 'sort_order' => 5 ),
		array( 'label' => 'Fifth', 'amount_kurus' => 5000, 'sort_order' => 5 ),
	)
);
mb_test(
	'price options: equal sort_order values -> deterministic tie-break by original submission order (Third, Fourth, Fifth)',
	empty( $equal_sort_order_result['errors'] )
	&& array( 'Third', 'Fourth', 'Fifth' ) === array_map(
		function ( $option ) {
			return $option['label'];
		},
		$equal_sort_order_result['options']
	)
);

$large_sort_order_result = MaviBelge_Core_Validator::normalize_price_options(
	array(
		array( 'label' => 'Big', 'amount_kurus' => 1000, 'sort_order' => PHP_INT_MAX ),
		array( 'label' => 'Small', 'amount_kurus' => 2000, 'sort_order' => 0 ),
	)
);
mb_test(
	'price options: comparator does not overflow on very large sort_order values (Small still sorts before Big)',
	empty( $large_sort_order_result['errors'] )
	&& 'Small' === $large_sort_order_result['options'][0]['label']
	&& 'Big' === $large_sort_order_result['options'][1]['label']
);

/*
 * NOTE: normalize_price_options() itself does NOT zero out 'options' when
 * 'errors' is non-empty — it returns whatever valid rows it collected
 * alongside the errors (see its own docblock: "This function alone does
 * NOT decide whether to persist anything"). The all-or-nothing guarantee
 * is enforced by its wrappers — canonicalize_price_options() (used for
 * public display) and evaluate_price_options()/evaluate_admin_price_rows()
 * (used for saving, via the 'replace' flag) — so the atomic-reject
 * assertion below exercises canonicalize_price_options(), not
 * normalize_price_options() directly.
 */
$one_valid_one_broken_raw = array(
	array( 'label' => 'Valid', 'amount_kurus' => 1000 ),
	array( 'label' => 'Broken', 'amount_kurus' => 2000, 'units' => 'not-an-array' ),
);
$one_valid_one_broken_normalized = MaviBelge_Core_Validator::normalize_price_options( $one_valid_one_broken_raw );
mb_test(
	'price options: normalize_price_options() itself still reports the error for the broken row',
	! empty( $one_valid_one_broken_normalized['errors'] )
);
mb_test(
	'price options: canonicalize_price_options() rejects the WHOLE list (empty array) when one row is valid and one is structurally broken — never the valid one kept alone',
	array() === MaviBelge_Core_Catalog_Query::canonicalize_price_options( $one_valid_one_broken_raw )
);
$one_valid_one_broken_eval = MaviBelge_Core_Validator::evaluate_price_options( $one_valid_one_broken_raw );
mb_test(
	'price options: evaluate_price_options() reports replace=false for the same one-valid-one-broken input (save path must keep the old list entirely)',
	false === $one_valid_one_broken_eval['replace']
);

/* -------------------------------------------------------------- *
 * evaluate_price_options — the atomic "replace whole list, or keep
 * everything as-is" decision as a pure, directly testable result
 * (Faz2 düzeltme §3.3: "tüm eski ... değerlerini koru; kısmi liste
 * kaydetme"). The caller in admin/class-meta-boxes.php must check
 * $result['replace'] and, when false, write nothing at all.
 * -------------------------------------------------------------- */
$all_valid_rows = array(
	array( 'label' => 'A1', 'amount_kurus' => 1000, 'sort_order' => 0 ),
	array( 'label' => 'B1', 'amount_kurus' => 2000, 'sort_order' => 1 ),
);
$eval_ok = MaviBelge_Core_Validator::evaluate_price_options( $all_valid_rows );
mb_test( 'evaluate_price_options: all-valid rows -> replace is true', true === $eval_ok['replace'] );
mb_test( 'evaluate_price_options: all-valid rows -> both options kept', 2 === count( $eval_ok['options'] ) );

$one_bad_row = array(
	array( 'label' => 'A1', 'amount_kurus' => 1000, 'sort_order' => 0 ),
	array( 'label' => 'B1', 'amount_kurus' => -5, 'sort_order' => 1 ), // invalid: negative
);
$eval_bad = MaviBelge_Core_Validator::evaluate_price_options( $one_bad_row );
mb_test(
	'evaluate_price_options: one invalid dolu row among valid ones -> replace is false (atomic — caller must preserve the entire old list, not save A1 alone)',
	false === $eval_bad['replace']
);

$only_blank_rows = array(
	array( 'label' => '', 'amount_kurus' => '', 'sort_order' => 0 ),
	array( 'label' => '', 'amount_kurus' => '', 'sort_order' => 1 ),
);
$eval_blank = MaviBelge_Core_Validator::evaluate_price_options( $only_blank_rows );
mb_test( 'evaluate_price_options: only blank filler rows -> replace is true with an empty list (not an error)', true === $eval_blank['replace'] && 0 === count( $eval_blank['options'] ) );

/* -------------------------------------------------------------- *
 * evaluate_admin_price_rows — the REAL admin-form entry point (raw
 * label/amount_try/units/sort_order shape), and the exact bug the
 * Faz2 ikinci düzeltme brief §2 flagged: a prior version cast
 * $row['label'] to (string) unconditionally, so a crafted
 * `label[]=x` request produced the literal text "Array" instead of
 * being rejected as a malformed row. Every case below sends an
 * array/object where a scalar is expected and checks it is
 * REJECTED, not silently stringified.
 * -------------------------------------------------------------- */
$array_label_admin = MaviBelge_Core_Validator::evaluate_admin_price_rows(
	array( array( 'label' => array( 'x' ), 'amount_try' => '100', 'units' => '', 'sort_order' => 0 ) )
);
mb_test(
	'evaluate_admin_price_rows: array label is rejected (not coerced to "Array")',
	false === $array_label_admin['replace'] && 0 === count( $array_label_admin['options'] )
);

$array_amount_admin = MaviBelge_Core_Validator::evaluate_admin_price_rows(
	array( array( 'label' => 'Sınav', 'amount_try' => array( '100' ), 'units' => '', 'sort_order' => 0 ) )
);
mb_test(
	'evaluate_admin_price_rows: array amount_try is rejected (not coerced to "Array")',
	false === $array_amount_admin['replace'] && 0 === count( $array_amount_admin['options'] )
);

$array_units_admin = MaviBelge_Core_Validator::evaluate_admin_price_rows(
	array( array( 'label' => 'Sınav', 'amount_try' => '100', 'units' => array( 'A1' => array( 'nested' ) ), 'sort_order' => 0 ) )
);
mb_test(
	'evaluate_admin_price_rows: array-shaped units where a comma-string is expected is rejected',
	false === $array_units_admin['replace'] && 0 === count( $array_units_admin['options'] )
);

mb_test(
	'evaluate_admin_price_rows: error message never echoes the raw invalid amount_try value',
	( function () {
		$eval = MaviBelge_Core_Validator::evaluate_admin_price_rows(
			array( array( 'label' => 'Sınav', 'amount_try' => 'ON_YEDI_BIN_TL_DEGIL', 'units' => '', 'sort_order' => 0 ) )
		);
		foreach ( $eval['errors'] as $message ) {
			if ( false !== strpos( $message, 'ON_YEDI_BIN_TL_DEGIL' ) ) {
				return false;
			}
		}
		return true;
	} )()
);

$mixed_valid_and_malformed = MaviBelge_Core_Validator::evaluate_admin_price_rows(
	array(
		array( 'label' => 'A1', 'amount_try' => '100,00', 'units' => 'A1', 'sort_order' => 0 ),
		array( 'label' => 'B1', 'amount_try' => 'geçersiz', 'units' => '', 'sort_order' => 1 ),
	)
);
mb_test(
	'evaluate_admin_price_rows: one valid + one malformed row -> atomic reject (replace is false, caller keeps entire old list)',
	false === $mixed_valid_and_malformed['replace']
);

$blank_filler_admin = MaviBelge_Core_Validator::evaluate_admin_price_rows(
	array(
		array( 'label' => '', 'amount_try' => '', 'units' => '', 'sort_order' => 0 ),
		array( 'label' => '', 'amount_try' => '', 'units' => '', 'sort_order' => 1 ),
	)
);
mb_test(
	'evaluate_admin_price_rows: blank filler rows produce no errors',
	true === $blank_filler_admin['replace'] && empty( $blank_filler_admin['errors'] )
);

$exactly_twenty_admin_rows = array();
for ( $i = 0; $i < MaviBelge_Core_Validator::MAX_PRICE_OPTIONS; $i++ ) {
	$exactly_twenty_admin_rows[] = array( 'label' => 'Seçenek ' . $i, 'amount_try' => ( 100 + $i ) . ',00', 'units' => '', 'sort_order' => $i );
}
$exactly_twenty_eval = MaviBelge_Core_Validator::evaluate_admin_price_rows( $exactly_twenty_admin_rows );
mb_test( 'evaluate_admin_price_rows: exactly MAX_PRICE_OPTIONS valid rows -> replace is true', true === $exactly_twenty_eval['replace'] );

$twenty_one_admin_rows   = $exactly_twenty_admin_rows;
$twenty_one_admin_rows[] = array( 'label' => 'Bir fazla', 'amount_try' => '999,00', 'units' => '', 'sort_order' => 20 );
$twenty_one_eval         = MaviBelge_Core_Validator::evaluate_admin_price_rows( $twenty_one_admin_rows );
mb_test( 'evaluate_admin_price_rows: MAX_PRICE_OPTIONS + 1 rows -> the whole submission is rejected', false === $twenty_one_eval['replace'] );

/* -------------------------------------------------------------- *
 * try_lira_to_kurus — overflow guard (Faz2 ikinci düzeltme §5).
 * PHP silently promotes int*int to float past PHP_INT_MAX instead of
 * erroring; an absurdly large TL string must be rejected, not turned
 * into an imprecise float "amount".
 * -------------------------------------------------------------- */
$huge_lira = (string) PHP_INT_MAX; // Far larger than any real price; lira*100 would overflow.
mb_test( 'TL->kuruş: an amount whose ×100 would overflow PHP_INT_MAX is rejected, not silently turned into a float', null === MaviBelge_Core_Validator::try_lira_to_kurus( $huge_lira ) );
mb_test( 'TL->kuruş: a realistic large amount ("1.000.000") still converts to a plain positive int', is_int( MaviBelge_Core_Validator::try_lira_to_kurus( '1.000.000' ) ) );

/* -------------------------------------------------------------- *
 * MaviBelge_Core_Meta_Schema::sanitize_for_registration() — the real
 * WordPress register_meta()/sanitize_meta() callback signature is
 * ($meta_value, $meta_key, $object_type, $object_subtype), NOT
 * ($meta_value, $meta_key, $object_subtype) — a prior version declared
 * only 3 params, so PHP silently dropped the real 4th argument and
 * what the 3rd param actually received on every call was the literal
 * string "post", never the real post type. get_fields_for('post') is
 * always empty, so the field-aware branch was permanently dead code
 * and every array-shaped field (price_options/id_list/phone_list/
 * string_list) passed through completely unsanitized (Faz2 son
 * düzeltme brief §2).
 *
 * These tests only exercise field types whose sanitize_and_validate()
 * branch is pure PHP (select, checkbox) — text/textarea/url call real
 * WordPress functions (sanitize_text_field() etc.) that don't exist in
 * this standalone bootstrap; see tests/bootstrap.php.
 * -------------------------------------------------------------- */
mb_test(
	'sanitize_for_registration: wrong object_type ("term", not "post") never lets a raw array pass through',
	array() === MaviBelge_Core_Meta_Schema::sanitize_for_registration( array( 'x' ), '_mb_pricing_type', 'term', 'mb_ucret' )
);

mb_test(
	'sanitize_for_registration: empty object_subtype (schema unresolvable) never lets a raw array pass through',
	array() === MaviBelge_Core_Meta_Schema::sanitize_for_registration( array( 'x' ), '_mb_pricing_type', 'post', '' )
);

mb_test(
	'sanitize_for_registration: unknown meta key for a real post type never lets a raw array pass through',
	array() === MaviBelge_Core_Meta_Schema::sanitize_for_registration( array( 'x' ), '_mb_does_not_exist', 'post', 'mb_ucret' )
);

mb_test(
	'sanitize_for_registration: correct 4-arg resolution + valid enum value is kept as-is',
	'single' === MaviBelge_Core_Meta_Schema::sanitize_for_registration( 'single', '_mb_pricing_type', 'post', 'mb_ucret' )
);

mb_test(
	'sanitize_for_registration: correct 4-arg resolution + invalid enum value -> yazma REDDİ işareti (Faz 6B3 Önkoşul: artık güvenli varsayılana (boş değere) düşüp eski değeri EZMEZ), ham geçersiz string de değil',
	MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE === MaviBelge_Core_Meta_Schema::sanitize_for_registration( 'bogus_pricing_type', '_mb_pricing_type', 'post', 'mb_ucret' )
);

mb_test(
	'sanitize_for_registration: a resolved KNOWN field still rejects an array-smuggling attempt -> ret işareti (Faz 6B3 Önkoşul)',
	MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE === MaviBelge_Core_Meta_Schema::sanitize_for_registration( array( 'sneaky' ), '_mb_pricing_type', 'post', 'mb_ucret' )
);

mb_test(
	'sanitize_for_registration: array raw value on a checkbox field -> ret işareti (Faz 6B3 Önkoşul: false değerine düşüp mevcut değeri ezmez), dizi veya (bool) true da değil',
	MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE === MaviBelge_Core_Meta_Schema::sanitize_for_registration( array( 'x' ), '_mb_vat_included', 'post', 'mb_ucret' )
);

mb_test(
	'sanitize_for_registration: ordinary checkbox "1" still works (regression check)',
	true === MaviBelge_Core_Meta_Schema::sanitize_for_registration( '1', '_mb_vat_included', 'post', 'mb_ucret' )
);

/* -------------------------------------------------------------- *
 * sanitize_for_registration() — price_options must never save a
 * PARTIAL list (Faz2 kapanış düzeltme brief §2). A prior version
 * returned normalize_price_options()'s $result['options'] even when
 * $result['errors'] was non-empty, so a direct update_post_meta() call
 * with one valid row + one malformed row could silently persist the
 * valid row alone — exactly the "kısmi liste kaydı" the accepted rule
 * forbids everywhere else in this plugin (see evaluate_price_options()/
 * evaluate_admin_price_rows()'s atomic 'replace' contract).
 * -------------------------------------------------------------- */
$one_valid_one_bad_row = array(
	array( 'label' => 'A1', 'units' => array( 'A1' ), 'amount_kurus' => 1000, 'sort_order' => 0 ),
	array( 'label' => 'B1', 'units' => array(), 'amount_kurus' => -5, 'sort_order' => 1 ), // invalid: negative amount
);
mb_test(
	'sanitize_for_registration (price_options): one valid + one malformed row -> ret işareti (Faz 6B3 Önkoşul: kısmi liste YOK, boş listeyle eski listeyi ezmek de YOK)',
	MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE === MaviBelge_Core_Meta_Schema::sanitize_for_registration( $one_valid_one_bad_row, '_mb_price_options', 'post', 'mb_ucret' )
);

$two_fully_valid_rows = array(
	array( 'label' => 'A1', 'units' => array(), 'amount_kurus' => 1000, 'sort_order' => 0 ),
	array( 'label' => 'B1', 'units' => array(), 'amount_kurus' => 2000, 'sort_order' => 1 ),
);
$two_valid_result = MaviBelge_Core_Meta_Schema::sanitize_for_registration( $two_fully_valid_rows, '_mb_price_options', 'post', 'mb_ucret' );
mb_test(
	'sanitize_for_registration (price_options): two fully valid rows are BOTH kept',
	2 === count( $two_valid_result ) && 'A1' === $two_valid_result[0]['label'] && 'B1' === $two_valid_result[1]['label']
);

mb_test(
	'sanitize_for_registration (price_options): non-array raw value -> ret işareti (Faz 6B3 Önkoşul; never coerced, never fatals)',
	MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE === MaviBelge_Core_Meta_Schema::sanitize_for_registration( 'not an array', '_mb_price_options', 'post', 'mb_ucret' )
);

/* -------------------------------------------------------------- *
 * Field_Repository::sanitize_and_validate() — string_list/phone_list
 * atomic rejection of a nested array/object element, and checkbox
 * array/object rejection (Faz2 son düzeltme brief §3). Ad-hoc $config
 * arrays are used here (not the real schema) since sanitize_and_validate()
 * only needs 'type' (and 'maxlength' for textarea, unused here) — this
 * keeps the test focused on the type-dispatch logic itself.
 * -------------------------------------------------------------- */
$string_list_config = array( 'label' => 'Test', 'type' => 'string_list' );

list( $sl_clean, $sl_err ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $string_list_config, array( 'A1', 'B1' ) );
mb_test( 'string_list: well-formed scalar items pass through normally', null === $sl_err && array( 'A1', 'B1' ) === $sl_clean );

list( $sl_clean2, $sl_err2 ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $string_list_config, array( 'A1', array( 'nested' ) ) );
mb_test(
	'string_list: one nested-array item among otherwise-valid ones is an ATOMIC reject for the whole field (not silently dropped while "A1" is kept)',
	null !== $sl_err2 && null === $sl_clean2
);

$phone_list_config = array( 'label' => 'Test', 'type' => 'phone_list' );

list( $pl_clean, $pl_err ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $phone_list_config, array( '0312 555 00 00' ) );
mb_test( 'phone_list: a well-formed phone number passes through normally', null === $pl_err && array( '0312 555 00 00' ) === $pl_clean );

list( $pl_clean2, $pl_err2 ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $phone_list_config, array( '0312 555 00 00', array( 'obj' => 'ect' ) ) );
mb_test(
	'phone_list: one nested-object item among otherwise-valid ones is an ATOMIC reject for the whole field',
	null !== $pl_err2 && null === $pl_clean2
);

$checkbox_config = array( 'label' => 'Test', 'type' => 'checkbox' );

list( $cb_clean1, $cb_err1 ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $checkbox_config, array( 'x' ) );
mb_test( 'checkbox: an array raw value is rejected as an error, not coerced to (bool) true', null !== $cb_err1 );

list( $cb_clean2, $cb_err2 ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $checkbox_config, new stdClass() );
mb_test( 'checkbox: an object raw value is rejected as an error', null !== $cb_err2 );

list( $cb_clean3, $cb_err3 ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $checkbox_config, '1' );
mb_test( 'checkbox: ordinary "1" -> true (regression, documented scalar contract)', null === $cb_err3 && true === $cb_clean3 );

list( $cb_clean4, $cb_err4 ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $checkbox_config, '' );
mb_test( 'checkbox: empty string -> false (regression, documented scalar contract)', null === $cb_err4 && false === $cb_clean4 );

list( $cb_clean5, $cb_err5 ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $checkbox_config, '0' );
mb_test( 'checkbox: "0" -> false (documented PHP truthiness contract, not a special case)', null === $cb_err5 && false === $cb_clean5 );

/* --- Faz 6B2 Runtime Engelleri — select doğrulaması. Kök neden (PHP 7.3 +
 * WordPress 6.9.9 runtime'ında bulundu): '1'..'8' gibi sayısal string
 * seçenek anahtarları PHP dizisinde int'e dönüşür; array_keys() int listesi
 * verir ve is_valid_select()'in katı in_array()'i "3" !== 3 nedeniyle GEÇERLİ
 * her seviyeyi reddediyordu (_mb_level hiç kaydedilemiyordu). GERÇEK şema
 * config'i kullanılır. --- */
$mb_level_cfg_q  = MaviBelge_Core_Meta_Schema::get_fields_for( 'mb_yeterlilik' )['_mb_level'];
$mb_level_cfg_f  = MaviBelge_Core_Meta_Schema::get_fields_for( 'mb_ucret' )['_mb_level'];
$mb_status_cfg_q = MaviBelge_Core_Meta_Schema::get_fields_for( 'mb_yeterlilik' )['_mb_record_status'];
$mb_sel          = function ( $cfg, $raw ) {
	return MaviBelge_Core_Field_Repository::sanitize_and_validate( $cfg, $raw );
};
foreach ( array( 'mb_yeterlilik' => $mb_level_cfg_q, 'mb_ucret' => $mb_level_cfg_f ) as $mb_pt => $mb_cfg ) {
	foreach ( array( '1', '3', '8' ) as $mb_v ) {
		mb_test( "select sayısal ({$mb_pt}): \"{$mb_v}\" kabul -> \"{$mb_v}\"", array( $mb_v, null ) === $mb_sel( $mb_cfg, $mb_v ) );
	}
	mb_test( "select sayısal ({$mb_pt}): int 3 kabul -> kanonik \"3\"", array( '3', null ) === $mb_sel( $mb_cfg, 3 ) );
	mb_test( "select sayısal ({$mb_pt}): \"\" -> boş değer (isteğe bağlı sözleşme)", array( '', null ) === $mb_sel( $mb_cfg, '' ) );
	mb_test( "select sayısal ({$mb_pt}): null -> güvenli boş değer, hata yok", array( '', null ) === $mb_sel( $mb_cfg, null ) );
	foreach ( array( '0', '9', '03', '1.0', '1e1', ' 3', '3 ', '+3', '-1' ) as $mb_bad ) {
		$mb_r = $mb_sel( $mb_cfg, $mb_bad );
		mb_test( "select sayısal ({$mb_pt}): " . json_encode( $mb_bad ) . ' ret', null === $mb_r[0] && null !== $mb_r[1] );
	}
	foreach ( array( 'dizi' => array( '3' ), 'nesne' => new stdClass() ) as $mb_label => $mb_bad ) {
		$mb_r = $mb_sel( $mb_cfg, $mb_bad );
		mb_test( "select sayısal ({$mb_pt}): {$mb_label} ret, \"Array\" string'ine dönüşmez", null === $mb_r[0] && null !== $mb_r[1] );
	}
}
mb_test( 'select string: "active" kabul', array( 'active', null ) === $mb_sel( $mb_status_cfg_q, 'active' ) );
mb_test( 'select string: "passive" kabul', array( 'passive', null ) === $mb_sel( $mb_status_cfg_q, 'passive' ) );
mb_test( 'select string: "deleted" ret', null !== $mb_sel( $mb_status_cfg_q, 'deleted' )[1] );
mb_test( 'select string: dizi ret, "Array" değil', array( null, 'listede olmayan bir değer seçildi.' ) === $mb_sel( $mb_status_cfg_q, array( 'active' ) ) );
mb_test( 'select string: nesne ret', null !== $mb_sel( $mb_status_cfg_q, new stdClass() )[1] );
mb_test( 'select string: "Array" metninin kendisi de geçerli değer değil', null !== $mb_sel( $mb_status_cfg_q, 'Array' )[1] );
$mb_all_select_ok = true;
foreach ( array( 'mb_yeterlilik', 'mb_ucret', 'mb_haber', 'mb_dokuman', 'mb_referans', 'mb_lokasyon', 'mb_sss' ) as $mb_pt ) {
	foreach ( MaviBelge_Core_Meta_Schema::get_fields_for( $mb_pt ) as $mb_key => $mb_cfg ) {
		if ( 'select' !== $mb_cfg['type'] ) {
			continue;
		}
		foreach ( array_keys( $mb_cfg['options'] ) as $mb_opt ) {
			$mb_r = $mb_sel( $mb_cfg, (string) $mb_opt );
			if ( array( (string) $mb_opt, null ) !== $mb_r ) {
				$mb_all_select_ok = false;
				echo "  (select regresyon) {$mb_pt}.{$mb_key} seçeneği '{$mb_opt}' reddedildi\n";
			}
		}
	}
}
mb_test( 'select genel: şemadaki HER select alanının HER seçeneği kendi kanonik string değeriyle kabul ediliyor', $mb_all_select_ok );

/*
 * Field_Repository::write_meta() is NOT tested here at all: its first
 * lines call get_post() (a real WordPress function, undefined in this
 * standalone bootstrap), so any call would fatal rather than exercise
 * the intended non-array-price_options guard. That guard was verified
 * by code review only, not by a runnable test in this phase — it is
 * listed under "NOT covered" below along with the rest of write_meta()'s
 * DB-touching behavior.
 */

/* -------------------------------------------------------------- *
 * Faz 5 — MaviBelge_Core_Catalog_Query (pure, no WordPress function
 * calls at class-definition time; see docs/catalog-service-contract.md)
 * -------------------------------------------------------------- */

mb_test( 'catalog: normalize_query_term trims and collapses internal whitespace', 'kaynak makine' === MaviBelge_Core_Catalog_Query::normalize_query_term( '  kaynak    makine  ' ) );
mb_test( 'catalog: normalize_query_term truncates beyond MAX_QUERY_LENGTH', 100 === strlen( MaviBelge_Core_Catalog_Query::normalize_query_term( str_repeat( 'a', 500 ) ) ) );
mb_test( 'catalog: normalize_query_term empty stays empty', '' === MaviBelge_Core_Catalog_Query::normalize_query_term( '' ) );

mb_test( 'catalog: normalize_sector_slug accepts a real slug shape', 'guzellik-sac-bakim' === MaviBelge_Core_Catalog_Query::normalize_sector_slug( 'guzellik-sac-bakim' ) );
mb_test( 'catalog: normalize_sector_slug lowercases', 'makine' === MaviBelge_Core_Catalog_Query::normalize_sector_slug( 'MAKINE' ) );
mb_test( 'catalog: normalize_sector_slug rejects spaces/underscores as empty', '' === MaviBelge_Core_Catalog_Query::normalize_sector_slug( 'guzellik sac_bakim' ) );

mb_test( 'catalog: normalize_level "3" within 1-8 accepted', '3' === MaviBelge_Core_Catalog_Query::normalize_level( '3' ) );
mb_test( 'catalog: normalize_level "9" out of range rejected to empty', '' === MaviBelge_Core_Catalog_Query::normalize_level( '9' ) );
mb_test( 'catalog: normalize_level "0" rejected to empty', '' === MaviBelge_Core_Catalog_Query::normalize_level( '0' ) );
mb_test( 'catalog: normalize_level non-numeric rejected to empty', '' === MaviBelge_Core_Catalog_Query::normalize_level( 'abc' ) );

mb_test( 'catalog: normalize_priced_flag only literal "1" is true', true === MaviBelge_Core_Catalog_Query::normalize_priced_flag( '1' ) );
mb_test( 'catalog: normalize_priced_flag "0" is false', false === MaviBelge_Core_Catalog_Query::normalize_priced_flag( '0' ) );
mb_test( 'catalog: normalize_priced_flag "true" is false (only "1" counts)', false === MaviBelge_Core_Catalog_Query::normalize_priced_flag( 'true' ) );

mb_test( 'catalog: normalize_page "3" -> 3', 3 === MaviBelge_Core_Catalog_Query::normalize_page( '3' ) );
mb_test( 'catalog: normalize_page "0" -> 1 (never 0)', 1 === MaviBelge_Core_Catalog_Query::normalize_page( '0' ) );
mb_test( 'catalog: normalize_page "-1" -> 1 (never negative)', 1 === MaviBelge_Core_Catalog_Query::normalize_page( '-1' ) );
mb_test( 'catalog: normalize_page non-numeric -> 1', 1 === MaviBelge_Core_Catalog_Query::normalize_page( 'abc' ) );
mb_test( 'catalog: normalize_page empty -> 1', 1 === MaviBelge_Core_Catalog_Query::normalize_page( '' ) );

mb_test( 'catalog: clamp_page keeps an in-range page unchanged', 3 === MaviBelge_Core_Catalog_Query::clamp_page( 3, 5 ) );
mb_test( 'catalog: clamp_page clamps a too-high page down to the last real page', 5 === MaviBelge_Core_Catalog_Query::clamp_page( 99, 5 ) );
mb_test( 'catalog: clamp_page clamps a zero/negative page up to 1', 1 === MaviBelge_Core_Catalog_Query::clamp_page( 0, 5 ) && 1 === MaviBelge_Core_Catalog_Query::clamp_page( -3, 5 ) );
mb_test( 'catalog: clamp_page with zero total pages still returns 1, not 0', 1 === MaviBelge_Core_Catalog_Query::clamp_page( 1, 0 ) );

mb_test( 'catalog: normalize_filters rejects an array value for mb_q instead of casting to "Array"', '' === MaviBelge_Core_Catalog_Query::normalize_filters( array( 'mb_q' => array( 'x' ) ) )['q'] );
mb_test( 'catalog: normalize_filters rejects an object-shaped value for mb_sector', '' === MaviBelge_Core_Catalog_Query::normalize_filters( array( 'mb_sector' => new stdClass() ) )['sector'] );

/* Faz 5 Düzeltme ve Kabul §2.2 — wp_unslash() applied before sanitize/validate. */
mb_test(
	'catalog: normalize_filters un-escapes a backslash-escaped apostrophe in mb_q (magic-quotes-style $_GET escaping)',
	"kaynak'çı" === MaviBelge_Core_Catalog_Query::normalize_filters( array( 'mb_q' => "kaynak\\'çı" ) )['q']
);
mb_test(
	'catalog: normalize_filters leaves an ordinary unescaped mb_q value unchanged',
	'kaynakçı' === MaviBelge_Core_Catalog_Query::normalize_filters( array( 'mb_q' => 'kaynakçı' ) )['q']
);
mb_test(
	'catalog: normalize_filters unslash step never touches a non-string (array) value before the shape check rejects it',
	'' === MaviBelge_Core_Catalog_Query::normalize_filters( array( 'mb_q' => array( "won't\\'crash" ) ) )['q']
);

mb_test( 'catalog: is_period_match requires a non-empty active period', false === MaviBelge_Core_Catalog_Query::is_period_match( '2026', '' ) );
mb_test( 'catalog: is_period_match true on exact match', true === MaviBelge_Core_Catalog_Query::is_period_match( '2026', '2026' ) );
mb_test( 'catalog: is_period_match false on mismatch', false === MaviBelge_Core_Catalog_Query::is_period_match( '2025', '2026' ) );

mb_test( 'catalog: validity window — both bounds empty is always valid', MaviBelge_Core_Catalog_Query::is_within_validity_window( '', '', '2026-09-12' ) );
mb_test( 'catalog: validity window — today exactly equal to valid_from is valid (inclusive)', MaviBelge_Core_Catalog_Query::is_within_validity_window( '2026-09-12', '', '2026-09-12' ) );
mb_test( 'catalog: validity window — today exactly equal to valid_until is valid (inclusive)', MaviBelge_Core_Catalog_Query::is_within_validity_window( '', '2026-09-12', '2026-09-12' ) );
mb_test( 'catalog: validity window — today before valid_from is invalid', ! MaviBelge_Core_Catalog_Query::is_within_validity_window( '2026-09-13', '', '2026-09-12' ) );
mb_test( 'catalog: validity window — today after valid_until is invalid', ! MaviBelge_Core_Catalog_Query::is_within_validity_window( '', '2026-09-11', '2026-09-12' ) );
// Faz 5 Düzeltme ve Kabul §2.8 — strict date validation (was: raw string compare only).
mb_test( 'catalog: validity window — empty today is invalid (fails closed)', ! MaviBelge_Core_Catalog_Query::is_within_validity_window( '', '', '' ) );
mb_test( 'catalog: validity window — malformed today ("not-a-date") is invalid', ! MaviBelge_Core_Catalog_Query::is_within_validity_window( '', '', 'not-a-date' ) );
mb_test( 'catalog: validity window — impossible calendar date for valid_from ("2026-02-30") is invalid even though it is Y-m-d-shaped', ! MaviBelge_Core_Catalog_Query::is_within_validity_window( '2026-02-30', '', '2026-09-12' ) );
mb_test( 'catalog: validity window — impossible calendar date for valid_until ("2026-13-01") is invalid', ! MaviBelge_Core_Catalog_Query::is_within_validity_window( '', '2026-13-01', '2026-09-12' ) );
mb_test( 'catalog: validity window — reversed range (valid_from > valid_until) is invalid even if today sits "inside" the reversed bounds', ! MaviBelge_Core_Catalog_Query::is_within_validity_window( '2026-10-01', '2026-01-01', '2026-09-12' ) );
mb_test( 'catalog: validity window — equal valid_from/valid_until with today matching is still valid (single-day window)', MaviBelge_Core_Catalog_Query::is_within_validity_window( '2026-09-12', '2026-09-12', '2026-09-12' ) );

/* Faz 5 Düzeltme ve Kabul §2.1 — canonical, single price-options validator (was: amount_kurus-only check). */
mb_test( 'catalog: has_priced_options false on empty array', ! MaviBelge_Core_Catalog_Query::has_priced_options( array() ) );
mb_test( 'catalog: has_priced_options false on non-array', ! MaviBelge_Core_Catalog_Query::has_priced_options( 'x' ) );
mb_test(
	'catalog: has_priced_options false when a row has a positive amount_kurus but NO label (old bug: this used to pass)',
	! MaviBelge_Core_Catalog_Query::has_priced_options( array( array( 'amount_kurus' => 1700000 ) ) ) )
;
mb_test(
	'catalog: has_priced_options true for a single fully-valid row (label + positive amount_kurus)',
	MaviBelge_Core_Catalog_Query::has_priced_options( array( array( 'label' => 'Sınav Ücreti', 'amount_kurus' => 1700000, 'units' => array(), 'sort_order' => 0 ) ) )
);
mb_test(
	'catalog: has_priced_options false when a row carries malformed (non-array) units — whole list rejected, not just that row',
	! MaviBelge_Core_Catalog_Query::has_priced_options( array( array( 'label' => 'A', 'amount_kurus' => 1000, 'units' => 'not-an-array' ) ) )
);
mb_test(
	// Faz 5 Son Kapanış Düzeltmesi §2: a nested array/object unit item used
	// to be silently dropped (row still counted valid) — it now rejects
	// the ENTIRE list atomically, same as any other malformed unit shape.
	'catalog: has_priced_options false when units contains a nested array/object item (atomic reject, no longer silently dropped)',
	! MaviBelge_Core_Catalog_Query::has_priced_options( array( array( 'label' => 'A', 'amount_kurus' => 1000, 'units' => array( 'adet', array( 'nested' => true ) ) ) ) )
);
mb_test(
	'catalog: has_priced_options false on an invalid (non-positive) amount',
	! MaviBelge_Core_Catalog_Query::has_priced_options( array( array( 'label' => 'A', 'amount_kurus' => 0 ) ) )
);
mb_test(
	// Faz 5 Son Kapanış Düzeltmesi §2: a PRESENT but invalid sort_order
	// used to silently fall back to positional order, indistinguishable
	// from the field never having been sent at all — it now rejects the
	// row (atomically, the whole list). Omitting the key entirely (see
	// the next test) still falls back, unchanged.
	'catalog: has_priced_options false when sort_order is PRESENT but invalid — no longer a silent positional fallback',
	! MaviBelge_Core_Catalog_Query::has_priced_options( array( array( 'label' => 'A', 'amount_kurus' => 1000, 'sort_order' => 'not-a-number' ) ) )
);
mb_test(
	'catalog: has_priced_options true when sort_order is OMITTED entirely — falls back to positional order (unchanged, documented behavior)',
	MaviBelge_Core_Catalog_Query::has_priced_options( array( array( 'label' => 'A', 'amount_kurus' => 1000 ) ) )
);
$twenty_valid_rows = array();
for ( $i = 0; $i < 20; $i++ ) {
	$twenty_valid_rows[] = array( 'label' => 'Seçenek ' . $i, 'amount_kurus' => 1000 + $i, 'sort_order' => $i );
}
mb_test( 'catalog: has_priced_options true at exactly MAX_PRICE_OPTIONS (20) valid rows', MaviBelge_Core_Catalog_Query::has_priced_options( $twenty_valid_rows ) );
$twentyone_valid_rows   = $twenty_valid_rows;
$twentyone_valid_rows[] = array( 'label' => 'Seçenek 20', 'amount_kurus' => 1020, 'sort_order' => 20 );
mb_test( 'catalog: has_priced_options false at 21 rows — the WHOLE list is rejected, not trimmed to the first 20', ! MaviBelge_Core_Catalog_Query::has_priced_options( $twentyone_valid_rows ) );
mb_test(
	'catalog: has_priced_options false when one row is valid and a second row is invalid (empty label) — atomic reject of the whole list',
	! MaviBelge_Core_Catalog_Query::has_priced_options(
		array(
			array( 'label' => 'Geçerli Seçenek', 'amount_kurus' => 1700000 ),
			array( 'label' => '', 'amount_kurus' => 2000000 ),
		)
	)
);
mb_test(
	'catalog: canonicalize_price_options returns a fully-valid multi-row list, canonically sorted by sort_order',
	array(
		array( 'label' => 'A', 'units' => array(), 'amount_kurus' => 1000, 'sort_order' => 0 ),
		array( 'label' => 'B', 'units' => array(), 'amount_kurus' => 2000, 'sort_order' => 1 ),
	) === MaviBelge_Core_Catalog_Query::canonicalize_price_options(
		array(
			array( 'label' => 'B', 'amount_kurus' => 2000, 'sort_order' => 1 ),
			array( 'label' => 'A', 'amount_kurus' => 1000, 'sort_order' => 0 ),
		)
	)
);
mb_test( 'catalog: canonicalize_price_options returns empty array (not a partial list) when any row is invalid', array() === MaviBelge_Core_Catalog_Query::canonicalize_price_options( array( array( 'label' => 'A', 'amount_kurus' => 1000 ), array( 'label' => '', 'amount_kurus' => 2000 ) ) ) );

mb_test( 'catalog: text_contains_ci case-insensitive plain match', MaviBelge_Core_Catalog_Query::text_contains_ci( 'Kaynak Makinesi Operatörü', 'makinesi' ) );
mb_test( 'catalog: text_contains_ci empty needle always matches', MaviBelge_Core_Catalog_Query::text_contains_ci( 'anything', '' ) );
mb_test( 'catalog: text_contains_ci no match returns false', ! MaviBelge_Core_Catalog_Query::text_contains_ci( 'Kaynakçı', 'elektrik' ) );

mb_test(
	'catalog: filters_to_query_args omits empty/default values entirely',
	array() === MaviBelge_Core_Catalog_Query::filters_to_query_args( array( 'q' => '', 'sector' => '', 'level' => '', 'priced' => false, 'page' => 1 ) )
);
mb_test(
	'catalog: filters_to_query_args includes only the set values, never "page"',
	array( 'mb_q' => 'kaynak', 'mb_level' => '3' ) === MaviBelge_Core_Catalog_Query::filters_to_query_args( array( 'q' => 'kaynak', 'sector' => '', 'level' => '3', 'priced' => false, 'page' => 4 ) )
);

/* -------------------------------------------------------------- *
 * Faz 6B1 — İçe Aktarım Karar Motoru ve Salt Okunur Dry-Run
 * Bu bölümdeki HİÇBİR test WordPress fonksiyonu çağırmaz — dört yeni
 * sınıf da (class-import-hash.php, class-import-managed-fields.php,
 * class-import-decision.php, class-import-dry-run-planner.php) saf
 * PHP'dir, bkz. tests/bootstrap.php. Fixture'lar gerçek Faz 6A
 * manifestlerinden (wordpress-site/data/content/*.manifest.json)
 * alınmıştır — hiçbir yeni ücret/MYK kodu/veri uydurulmadı.
 * -------------------------------------------------------------- */

/* --- Deterministik hash: kanonikleştirme kuralları --- */
mb_test(
	'import hash: aynı mantıksal obje, FARKLI anahtar sırasıyla AYNI hash verir',
	MaviBelge_Core_Import_Hash::hash( array( 'b' => 2, 'a' => 1 ) ) === MaviBelge_Core_Import_Hash::hash( array( 'a' => 1, 'b' => 2 ) )
);
mb_test(
	'import hash: liste (sequential) dizide SIRA değişince hash DEĞİŞİR',
	MaviBelge_Core_Import_Hash::hash( array( 'x' => array( 'A1', 'A2' ) ) ) !== MaviBelge_Core_Import_Hash::hash( array( 'x' => array( 'A2', 'A1' ) ) )
);
mb_test(
	'import hash: tip değişince (int 1 vs string "1") hash DEĞİŞİR',
	MaviBelge_Core_Import_Hash::hash( array( 'v' => 1 ) ) !== MaviBelge_Core_Import_Hash::hash( array( 'v' => '1' ) )
);
mb_test(
	'import hash: tip değişince (string "1" vs bool true) hash DEĞİŞİR',
	MaviBelge_Core_Import_Hash::hash( array( 'v' => '1' ) ) !== MaviBelge_Core_Import_Hash::hash( array( 'v' => true ) )
);
mb_test(
	'import hash: iç içe (nested) associative anahtarlar da özyinelemeli sıralanır',
	MaviBelge_Core_Import_Hash::hash( array( 'outer' => array( 'z' => 1, 'a' => 2 ) ) ) === MaviBelge_Core_Import_Hash::hash( array( 'outer' => array( 'a' => 2, 'z' => 1 ) ) )
);
mb_test(
	'import hash: sonuç tam 64 küçük-hex karakter',
	1 === preg_match( '/^[0-9a-f]{64}$/', MaviBelge_Core_Import_Hash::hash( array( 'k' => 'v' ) ) )
);
mb_test(
	'import hash: float değer reddedilir (InvalidArgumentException)',
	( function () {
		try {
			MaviBelge_Core_Import_Hash::hash( array( 'amount' => 1.5 ) );
			return false;
		} catch ( InvalidArgumentException $e ) {
			return true;
		}
	} )()
);
mb_test(
	'import hash: nesne (stdClass) reddedilir',
	( function () {
		try {
			MaviBelge_Core_Import_Hash::hash( array( 'x' => new stdClass() ) );
			return false;
		} catch ( InvalidArgumentException $e ) {
			return true;
		}
	} )()
);
mb_test(
	'import hash: resource reddedilir',
	( function () {
		try {
			MaviBelge_Core_Import_Hash::hash( array( 'x' => fopen( 'php://memory', 'r' ) ) );
			return false;
		} catch ( InvalidArgumentException $e ) {
			return true;
		}
	} )()
);
mb_test(
	'import hash: geçersiz UTF-8 string reddedilir (json_encode başarısız -> istisna, sessiz false hashlenmez)',
	( function () {
		try {
			MaviBelge_Core_Import_Hash::hash( array( 'x' => "\xB1\x31" ) );
			return false;
		} catch ( InvalidArgumentException $e ) {
			return true;
		}
	} )()
);
mb_test(
	'import hash: boş dizi ile boş dizi (liste) tutarlı hash verir (idempotent, iki bağımsız çağrı)',
	MaviBelge_Core_Import_Hash::hash( array() ) === MaviBelge_Core_Import_Hash::hash( array() )
);

/* --- Managed fields: _mb_import_source_key / _mb_last_applied_hash asla hash girdisine girmez --- */
mb_test(
	'managed fields: sektör projeksiyonu _mb_import_source_key veya _mb_last_applied_hash İÇERMEZ',
	! array_key_exists( '_mb_import_source_key', MaviBelge_Core_Import_Managed_Fields::project_sector( array( 'slug' => 'makine', 'name' => 'Makine', 'description' => '', 'icon' => 'gear', 'image' => '' ), array() )['fields'] )
	&& ! array_key_exists( '_mb_last_applied_hash', MaviBelge_Core_Import_Managed_Fields::project_sector( array( 'slug' => 'makine', 'name' => 'Makine', 'description' => '', 'icon' => 'gear', 'image' => '' ), array() )['fields'] )
);
mb_test(
	'managed fields: editörün serbest alanı (post_content benzeri, projeksiyonda hiç yok) hash girdisine giremez',
	! in_array( 'post_content', MaviBelge_Core_Import_Managed_Fields::SECTOR_FIELDS, true )
	&& ! in_array( 'post_content', MaviBelge_Core_Import_Managed_Fields::QUALIFICATION_FIELDS, true )
	&& ! in_array( 'post_content', MaviBelge_Core_Import_Managed_Fields::FEE_FIELDS, true )
);

/* --- Gerçek Faz 6A manifest fixture'ları (wordpress-site/data/content/*.manifest.json'dan
 * BİREBİR alındı — Faz 6B1 Son Kapanış Düzeltmesi'nde TAM şema alanlarına
 * (schema_version/source_index/source dahil) yükseltildi; hiçbir alan
 * uydurulmadı, gerçek manifest kayıtlarıyla karşılaştırılarak kopyalandı. */
$fx_sector_makine = array(
	'schema_version' => '2.0.0', 'source_key' => 'sector:makine', 'source_index' => 0, 'slug' => 'makine', 'name' => 'Makine',
	'description' => 'Makine bakım, montaj, kesim ve CNC/NC tezgâh meslekleri.', 'icon' => 'gear', 'image' => 'assets/images/content/real-makine.png',
	'source' => array( 'file' => 'tanitim-site/assets/data/sectors.js', 'sha256' => 'c28688d352aa5c77cd2082581cd9111c3a89f3766353d3cdd5090c42cc5e53e1' ),
);
$fx_sector_no_image = array(
	'schema_version' => '2.0.0', 'source_key' => 'sector:is-makineleri', 'source_index' => 4, 'slug' => 'is-makineleri', 'name' => 'İş Makineleri',
	'description' => 'Vinç, forklift, istif ve saha makineleri operatörlüğü meslekleri.', 'icon' => 'crane', 'image' => '',
	'source' => array( 'file' => 'tanitim-site/assets/data/sectors.js', 'sha256' => 'c28688d352aa5c77cd2082581cd9111c3a89f3766353d3cdd5090c42cc5e53e1' ),
);
$fx_qualification = array(
	'schema_version' => '2.0.0', 'source_key' => 'qualification:10UY0002-3/03', 'source_index' => 0, 'code' => '10UY0002-3/03', 'name' => 'Makine Bakımcı',
	'level' => 3, 'sector_slug' => 'makine', 'revision' => '03', 'has_revision' => true,
	'matches_legacy_revision_required_format' => true, 'planned_record_status' => 'active',
	'source' => array( 'file' => 'tanitim-site/assets/data/qualifications.js', 'sha256' => '649deee25873e91242649861691dd320afb92d7d154d05437f716676c594bd4a' ),
);
$fx_fee_with_code = array(
	'schema_version' => '2.0.0', 'source_key' => 'fee:guzellik-sac-bakim:4:guzellik-uzmani', 'source_index' => 0, 'profession_name' => 'Güzellik Uzmanı', 'level' => 4,
	'sector_slug' => 'guzellik-sac-bakim', 'qualification_code' => '16UY0244-4/02', 'qualification_source_key' => 'qualification:16UY0244-4/02',
	'pricing_type' => 'single', 'price_options' => array( array( 'label' => 'Sınav ücreti', 'units' => array( 'A1', 'A2', 'A3', 'A4' ), 'amount_kurus' => 1700000, 'sort_order' => 0 ) ),
	'min_amount_kurus' => 1700000, 'max_amount_kurus' => 1700000,
	'vat_included' => true, 'certificate_print_fee_kurus' => 150000, 'source_name' => '2026 Güzellik Fiyat Listesi', 'source_page' => 2,
	'source_attachment_id' => 0, 'planned_tariff_period' => '2026', 'planned_record_status' => 'draft', 'planned_valid_from' => '', 'planned_valid_until' => '',
	'source' => array( 'file' => 'tanitim-site/assets/data/fees.js', 'sha256' => 'ccb170bf838246971f68d6054aea2b279fbf2cd78e1fa3e72dc30b7e848f4bdb' ),
);
$fx_fee_without_code = array(
	'schema_version' => '2.0.0', 'source_key' => 'fee:enerji:3:sicak-su-kazani-operatoru', 'source_index' => 16, 'profession_name' => 'Sıcak Su Kazanı Operatörü', 'level' => 3,
	'sector_slug' => 'enerji', 'qualification_code' => '', 'qualification_source_key' => null,
	'pricing_type' => 'single', 'price_options' => array( array( 'label' => 'Tek birim fiyatı (B1/B2/B3 — yakıt tipine göre)', 'units' => array(), 'amount_kurus' => 1125000, 'sort_order' => 0 ) ),
	'min_amount_kurus' => 1125000, 'max_amount_kurus' => 1125000,
	'vat_included' => true, 'certificate_print_fee_kurus' => 150000, 'source_name' => '2026 Yeni Meslekler Ücret Tarifesi', 'source_page' => 1,
	'source_attachment_id' => 0, 'planned_tariff_period' => '2026', 'planned_record_status' => 'draft', 'planned_valid_from' => '', 'planned_valid_until' => '',
	'source' => array( 'file' => 'tanitim-site/assets/data/fees.js', 'sha256' => 'ccb170bf838246971f68d6054aea2b279fbf2cd78e1fa3e72dc30b7e848f4bdb' ),
);

/* --- Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri §2.2 — toplu plan() testleri
 * artık kendi batch'i İÇİNDE kayıtların `source_index`'inin GERÇEK liste
 * konumuyla birebir eşleşmesini zorunlu kılıyor. `$fx_sector_no_image`
 * (gerçek source_index=4) ve `$fx_fee_without_code` (gerçek
 * source_index=16) — her ikisi de GERÇEK 14/103 kayıtlık tam listede o
 * konumdadır — burada TEK BAŞINA/ikinci eleman olarak kullanıldıklarında
 * bu artık pozisyon uyuşmazlığı sayılır. Bu, veri UYDURMAK değildir —
 * yalnız izole `plan()` testleri için source_index'i o testin KENDİ
 * (sentetik, tek/iki elemanlı) batch konumuna yeniden numaralandırır;
 * `$fx_sector_no_image`/`$fx_fee_without_code`'un gerçek 14/83/103
 * manifestindeki KENDİ kayıtları (bkz. "18/20"/"20/20" testleri) hiç
 * değiştirilmedi. */
$fx_sector_no_image_at0    = array_merge( $fx_sector_no_image, array( 'source_index' => 0 ) );
$fx_fee_without_code_at1   = array_merge( $fx_fee_without_code, array( 'source_index' => 1 ) );

/* --- Dokuz karar senaryosunun tamamı (görev promptu §5) --- */

// 1. Hedef kayıt yok -> create.
$plan1 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array(), mb_empty_dependencies() );
mb_test( 'decision 1/9: hedef yok -> create', MaviBelge_Core_Import_Decision::CREATE === $plan1['decision'] );

// 2. current===last===incoming -> unchanged.
$incoming2 = MaviBelge_Core_Import_Managed_Fields::project_sector( $fx_sector_no_image, array() )['fields'];
$hash2 = MaviBelge_Core_Import_Hash::hash( $incoming2 );
$lookup2 = array( 'sector:is-makineleri' => array(
	'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true,
	'last_applied_hash' => $hash2, 'current_managed_fields' => $incoming2,
) );
$plan2 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup2, mb_empty_dependencies() );
mb_test( 'decision 2/9: current=last=incoming -> unchanged', MaviBelge_Core_Import_Decision::UNCHANGED === $plan2['decision'] );

// 3. current===last, incoming!==last -> update.
$oldFields3 = array_merge( $incoming2, array( 'name' => 'Eski İsim' ) );
$oldHash3 = MaviBelge_Core_Import_Hash::hash( $oldFields3 );
$lookup3 = array( 'sector:is-makineleri' => array(
	'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true,
	'last_applied_hash' => $oldHash3, 'current_managed_fields' => $oldFields3,
) );
$plan3 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup3, mb_empty_dependencies() );
mb_test( 'decision 3/9: current=last, incoming farklı -> update (güvenli)', MaviBelge_Core_Import_Decision::UPDATE === $plan3['decision'] );
mb_test( 'decision 3/9 ek: değişen alan adı ("name") raporlanıyor, tam içerik değil', in_array( 'name', $plan3['changed_fields'], true ) );

// 4. current!==last -> conflict (elle değiştirilmiş).
$manuallyEditedFields4 = array_merge( $incoming2, array( 'name' => 'Admin Elle Değiştirdi' ) );
$lookup4 = array( 'sector:is-makineleri' => array(
	'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true,
	'last_applied_hash' => $hash2, 'current_managed_fields' => $manuallyEditedFields4,
) );
$plan4 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup4, mb_empty_dependencies() );
mb_test( 'decision 4/9: current!=last -> conflict (elle değiştirilmiş)', MaviBelge_Core_Import_Decision::CONFLICT === $plan4['decision'] );
mb_test( 'decision 4/9 ek: reason=manual_edit_detected (incoming=last olduğu için "both_changed" DEĞİL)', 'manual_edit_detected' === $plan4['reason'] );

// 5. _mb_import_source_key VAR ama last_applied_hash yok/geçersiz -> conflict (legacy).
$lookup5 = array( 'sector:is-makineleri' => array(
	'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true,
	'last_applied_hash' => null, 'current_managed_fields' => $incoming2,
) );
$plan5 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup5, mb_empty_dependencies() );
mb_test( 'decision 5/9: source_key var, last_applied_hash yok -> conflict (legacy)', MaviBelge_Core_Import_Decision::CONFLICT === $plan5['decision'] && 'legacy_missing_hash' === $plan5['reason'] );

// 6. Aynı source_key için birden fazla hedef -> conflict_duplicate_target.
$lookup6 = array( 'sector:is-makineleri' => array(
	'target_found' => true, 'target_id' => 501, 'duplicate_targets' => true, 'target_type_matches' => true, 'has_source_key_marker' => true,
	'last_applied_hash' => $hash2, 'current_managed_fields' => $incoming2,
) );
$plan6 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup6, mb_empty_dependencies() );
mb_test( 'decision 6/9: birden fazla hedef -> conflict_duplicate_target (ilk kayıt keyfî seçilmez)', MaviBelge_Core_Import_Decision::CONFLICT_DUPLICATE_TARGET === $plan6['decision'] );

// 7. Hedef kayıt türü uyuşmuyor -> conflict_wrong_target_type.
$lookup7 = array( 'sector:is-makineleri' => array(
	'target_found' => true, 'target_id' => 501, 'target_type_matches' => false, 'has_source_key_marker' => true,
	'last_applied_hash' => $hash2, 'current_managed_fields' => $incoming2,
) );
$plan7 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup7, mb_empty_dependencies() );
mb_test( 'decision 7/9: hedef türü yanlış -> conflict_wrong_target_type', MaviBelge_Core_Import_Decision::CONFLICT_WRONG_TARGET_TYPE === $plan7['decision'] );

// 8. Gerekli ilişki/medya hedefi çözülemiyor -> blocked_dependency (create/update SAYILMAZ).
$plan8 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, array(), mb_empty_dependencies() ); // görseli var, ama dependency map boş -> çözülemedi.
mb_test( 'decision 8/9: görseli olan sektörün attachment eşleşmesi yoksa -> blocked_dependency (create SAYILMAZ)', MaviBelge_Core_Import_Decision::BLOCKED_DEPENDENCY === $plan8['decision'] );
mb_test( 'decision 8/9 ek: unresolved_dependencies "image_attachment_id" içeriyor', in_array( 'image_attachment_id', $plan8['unresolved_dependencies'], true ) );

// 9. Geçersiz manifest/alan şekli -> invalid.
$plan9 = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( array( 'slug' => 'eksik-source-key' ), array(), mb_empty_dependencies() );
mb_test( 'decision 9/9: zorunlu alan eksik -> invalid (sessiz varsayılan/kısmi plan YOK)', MaviBelge_Core_Import_Decision::INVALID === $plan9['decision'] );

/* --- Yeterlilik/ücret bağımlılık davranışı --- */
mb_test(
	'qualification: sector_term_id çözülürse normal plan üretilir (create)',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'qualification: sector_term_id çözülemezse blocked_dependency',
	MaviBelge_Core_Import_Decision::BLOCKED_DEPENDENCY === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'fee: kodlu ücret, hedef yeterlilik ÇÖZÜLÜRSE normal plan (create)',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, array(), array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'fee: kodlu ücret, hedef yeterlilik ÇÖZÜLEMEZSE blocked_dependency (create/update SAYILMAZ)',
	MaviBelge_Core_Import_Decision::BLOCKED_DEPENDENCY === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'fee: 19 kodsuz ücretin biri — HİÇBİR bağımlılık aranmaz, ilişki her zaman 0, create olarak planlanır (tahmin/fuzzy eşleştirme yok)',
	( function () use ( $fx_fee_without_code ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_without_code, array(), mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CREATE === $plan['decision'] && empty( $plan['unresolved_dependencies'] );
	} )()
);
mb_test(
	'fee: kodsuz ücretin projeksiyonunda qualification_post_id KESİNLİKLE 0',
	( function () use ( $fx_fee_without_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_without_code );
		return $validated['valid'] && 0 === MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_without_code, array(), $validated['normalized_price_options'] )['fields']['qualification_post_id'];
	} )()
);

/* --- Toplu plan: sayım özeti ve giriş-tekrarı reddi --- */
$fullPlan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
	array( 'sectors' => array( $fx_sector_no_image_at0 ), 'qualifications' => array( $fx_qualification ), 'fees' => array( $fx_fee_with_code, $fx_fee_without_code_at1 ) ),
	array(),
	array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => true ) ), 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) )
);
mb_test( 'plan(): karar sayımları toplamı giriş kayıt sayısına (4) birebir eşit', 4 === $fullPlan['summary']['total'] && true === $fullPlan['summary']['total_matches_input'] );
mb_test( 'plan(): sektör/yeterlilik/ücret alt-sayımları doğru (1/1/2)', 1 === $fullPlan['summary']['by_type']['sector'] && 1 === $fullPlan['summary']['by_type']['qualification'] && 2 === $fullPlan['summary']['by_type']['fee'] );

$dupPlan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
	array( 'sectors' => array( $fx_sector_no_image, $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ),
	array(),
	mb_empty_dependencies()
);
mb_test( 'plan(): girişte tekrar eden source_key fail-closed reddedilir (hiçbir entry üretilmez)', empty( $dupPlan['entries'] ) && ! empty( $dupPlan['errors'] ) );

/* --- Determinizm: aynı girdiyle iki ayrı dry-run çağrısı byte-eşit sonuç verir --- */
$run1 = MaviBelge_Core_Import_Dry_Run_Planner::plan(
	array( 'sectors' => array( $fx_sector_no_image_at0 ), 'qualifications' => array( $fx_qualification ), 'fees' => array( $fx_fee_with_code ) ),
	$lookup2,
	array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => true ) ), 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) )
);
$run2 = MaviBelge_Core_Import_Dry_Run_Planner::plan(
	array( 'sectors' => array( $fx_sector_no_image_at0 ), 'qualifications' => array( $fx_qualification ), 'fees' => array( $fx_fee_with_code ) ),
	$lookup2,
	array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => true ) ), 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) )
);
mb_test(
	'plan(): aynı girdiyle iki ayrı çağrı byte-eşit (deterministik) sonuç verir',
	json_encode( $run1, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) === json_encode( $run2, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
);

/* -------------------------------------------------------------- *
 * Faz 6B1 Düzeltme ve Kabul — tek kayıt doğrulayıcı karşı-örnekleri
 * (görev promptu §9, 1-20). Gerçek manifest sayıları/SHA-256'lar
 * uydurulmadı — data/content/*.manifest.json'dan doğrudan okunur.
 * -------------------------------------------------------------- */

// 1. Yalnız source_key+slug taşıyan sektör create OLAMAZ (invalid).
mb_test(
	'record validator 1/20: yalnız source_key+slug taşıyan sektör -> invalid (create DEĞİL)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( array( 'source_key' => 'sector:x', 'slug' => 'x' ), array(), mb_empty_dependencies() )['decision']
);

// 2. Sektör name/icon/image yanlış tip -> invalid.
mb_test(
	'record validator 2/20: sektör name dizi (yanlış tip) -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( array_merge( $fx_sector_no_image, array( 'name' => array( 'x' ) ) ), array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'record validator 2/20 ek: sektör icon int (yanlış tip) -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( array_merge( $fx_sector_no_image, array( 'icon' => 5 ) ), array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'record validator 2/20 ek: sektör image bool (yanlış tip) -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( array_merge( $fx_sector_no_image, array( 'image' => true ) ), array(), mb_empty_dependencies() )['decision']
);

// 3. Yeterlilik code/name/level/revision eksik veya yanlış tip -> invalid.
mb_test(
	'record validator 3/20: yeterlilik code eksik -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( array_diff_key( $fx_qualification, array( 'code' => 1 ) ), array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'record validator 3/20 ek: yeterlilik level string ("3") -> invalid (tam sayı değil)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( array_merge( $fx_qualification, array( 'level' => '3' ) ), array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'record validator 3/20 ek: yeterlilik revision int (yanlış tip) -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( array_merge( $fx_qualification, array( 'revision' => 3 ) ), array(), mb_empty_dependencies() )['decision']
);

// 4. Yeterlilik MYK seviye/revizyon çapraz uyuşmazlığı -> invalid.
mb_test(
	'record validator 4/20: koda gömülü seviye (3) ile level alanı (5) uyuşmuyor -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( array_merge( $fx_qualification, array( 'level' => 5 ) ), array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'record validator 4/20 ek: koda gömülü revizyon ("03") ile revision alanı ("04") uyuşmuyor -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( array_merge( $fx_qualification, array( 'revision' => '04' ) ), array(), mb_empty_dependencies() )['decision']
);

// 5. Ücret price_options eksik/dizi değil/tek malformed satır -> TÜM kayıt invalid.
mb_test(
	'record validator 5/20: price_options dizi değil (string) -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( array_merge( $fx_fee_with_code, array( 'price_options' => 'not-an-array' ) ), array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'record validator 5/20 ek: iki satırdan biri malformed (amount_kurus eksik) -> TÜM liste invalid (kısmi hash YOK)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee(
		array_merge(
			$fx_fee_with_code,
			array(
				'price_options' => array(
					array( 'label' => 'Geçerli', 'units' => array(), 'amount_kurus' => 100000, 'sort_order' => 0 ),
					array( 'label' => 'Bozuk', 'units' => array(), 'amount_kurus' => -5, 'sort_order' => 1 ),
				),
			)
		),
		array(),
		array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) )
	)['decision']
);

// 6. Ücret pricing_type/durum/tarife sabitleri bozuk -> invalid.
mb_test(
	'record validator 6/20: pricing_type geçersiz değer -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( array_merge( $fx_fee_with_code, array( 'pricing_type' => 'bulk-discount' ) ), array(), array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'record validator 6/20 ek: planned_record_status "active" (bu fazda yalnız "draft" olmalı) -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( array_merge( $fx_fee_with_code, array( 'planned_record_status' => 'active' ) ), array(), array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'record validator 6/20 ek: planned_tariff_period "2025" (bu fazda yalnız "2026" olmalı) -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( array_merge( $fx_fee_with_code, array( 'planned_tariff_period' => '2025' ) ), array(), array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) )['decision']
);

// 7. Üst seviye üç liste anahtarından biri eksik/null/string -> errors dolu, entries boş.
mb_test(
	'record validator 7/20: manifest.fees eksik -> üst-seviye hata, entries boş',
	( function () use ( $fx_sector_no_image, $fx_qualification ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array( $fx_qualification ) ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'record validator 7/20 ek: manifest.sectors null -> üst-seviye hata, entries boş',
	( function () use ( $fx_qualification ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => null, 'qualifications' => array( $fx_qualification ), 'fees' => array() ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'record validator 7/20 ek: manifest.fees string (scalar) -> üst-seviye hata, entries boş',
	( function () {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array(), 'qualifications' => array(), 'fees' => 'oops' ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'record validator 7/20 ek: manifest üst seviyesinde fazladan anahtar -> üst-seviye hata',
	( function () {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array(), 'extra' => array() ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);

// 8. Geçersiz/boş/yanlış prefix source_key -> fail-closed.
mb_test(
	'record validator 8/20: sektör source_key yanlış prefix ("fee:...") -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( array_merge( $fx_sector_no_image, array( 'source_key' => 'fee:is-makineleri:3:x' ) ), array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'record validator 8/20 ek: sektör source_key boş -> invalid',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( array_merge( $fx_sector_no_image, array( 'source_key' => '' ) ), array(), mb_empty_dependencies() )['decision']
);

// 9. Dependency ID 0/negatif/numeric-string/float/bool/dizi -> artık ÜST
// DTO seviyesinde (Faz 6B1 Zorunlu Dependency DTO Kapanışı §3.1 madde 6:
// "her map değeri kapalı {id:int>0,type_verified:true} şeklinde olmalı")
// YAPISAL hata sayılır -> plan_*() dependenciesCheck'te durur -> invalid
// (blocked_dependency ARTIK DEĞİL — o yalnız yapısal olarak GEÇERLİ bir
// typed değerin item'ın KENDİSİ bulunamadığı gerçek "unresolved" durumu
// için kalır).
mb_test(
	'record validator 9/20: dependency ID numeric string ("42") -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => '42' ) ) ) )['decision']
);
mb_test(
	'record validator 9/20 ek: dependency ID 0 (dizi değil) -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => 0 ) ) ) )['decision']
);
mb_test(
	'record validator 9/20 ek: dependency ID negatif (dizi değil) -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => -3 ) ) ) )['decision']
);
mb_test(
	'record validator 9/20 ek: dependency ID float (dizi değil) -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => 42.0 ) ) ) )['decision']
);
mb_test(
	'record validator 9/20 ek: dependency ID bool (dizi değil) -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => true ) ) ) )['decision']
);
mb_test(
	'record validator 9/20 ek: dependency ID dizi ama {id,type_verified} şekli DEĞİL (fazla anahtar "0") -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 42 ) ) ) ) )['decision']
);
mb_test(
	'record validator 9/20 ek: GERÇEK pozitif integer dependency ID çözülmüş SAYILIR -> create',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => true ) ) ) ) )['decision']
);

// 10. Hedef bulundu + target_id eksik/0/negatif/string -> invalid target conflict.
mb_test(
	'record validator 10/20: target_found=true, target_id EKSİK -> conflict (invalid_target_state)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_type_matches' => true ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 10/20 ek: target_found=true, target_id=0 -> conflict (invalid_target_state)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 0, 'target_type_matches' => true ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 10/20 ek: target_found=true, target_id negatif -> conflict (invalid_target_state)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => -1, 'target_type_matches' => true ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 10/20 ek: target_found=true, target_id string ("12") -> conflict (invalid_target_state)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => '12', 'target_type_matches' => true ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 11. Marker var + hash null/kısa/uppercase/non-hex -> legacy/invalid-target conflict, create/update DEĞİL.
mb_test(
	'record validator 11/20: marker var, hash kısa (63 hex) -> legacy_missing_hash (create/update DEĞİL)',
	( function () use ( $fx_sector_no_image, $incoming2 ) {
		$shortHash = str_repeat( 'a', 63 );
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $shortHash, 'current_managed_fields' => $incoming2 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'legacy_missing_hash' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 11/20 ek: marker var, hash BÜYÜK HARF (64 karakter) -> legacy_missing_hash',
	( function () use ( $fx_sector_no_image, $incoming2, $hash2 ) {
		$upperHash = strtoupper( $hash2 );
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $upperHash, 'current_managed_fields' => $incoming2 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'legacy_missing_hash' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 11/20 ek: marker var, hash non-hex karakter içeriyor -> legacy_missing_hash',
	( function () use ( $fx_sector_no_image, $incoming2 ) {
		$badHash = str_repeat( 'z', 64 );
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $badHash, 'current_managed_fields' => $incoming2 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'legacy_missing_hash' === $plan['reason'];
	} )()
);

// 12. Current fields eksik/fazla/yanlış tip -> kontrollü conflict; hash fatal YOK.
mb_test(
	'record validator 12/20: current_managed_fields eksik anahtar taşıyor -> conflict (invalid_target_state), istisna FIRLAMAZ',
	( function () use ( $fx_sector_no_image, $incoming2, $hash2 ) {
		$missingKeyFields = $incoming2;
		unset( $missingKeyFields['description'] );
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $hash2, 'current_managed_fields' => $missingKeyFields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 12/20 ek: current_managed_fields fazladan anahtar taşıyor -> conflict (invalid_target_state)',
	( function () use ( $fx_sector_no_image, $incoming2, $hash2 ) {
		$extraKeyFields = array_merge( $incoming2, array( 'unexpected_extra_field' => 'x' ) );
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $hash2, 'current_managed_fields' => $extraKeyFields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 12/20 ek: current_managed_fields dizi değil (string) -> conflict (invalid_target_state), istisna FIRLAMAZ',
	( function () use ( $fx_sector_no_image, $hash2 ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $hash2, 'current_managed_fields' => 'not-an-array' ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 13. Lookup bayrakları çelişkili (target_found=false ama target_id/current dolu) -> kontrollü conflict/invalid.
mb_test(
	'record validator 13/20: target_found=false ama target_id dolu -> conflict (invalid_target_state)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => false, 'target_id' => 501 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 13/20 ek: target_found=false ama current_managed_fields dolu -> conflict (invalid_target_state)',
	( function () use ( $fx_sector_no_image, $incoming2 ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => false, 'current_managed_fields' => $incoming2 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'record validator 13/20 ek: target_type_matches bool DEĞİL (string "evet") -> conflict (invalid_target_state)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => false, 'target_type_matches' => 'evet' ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 14. Duplicate target + unresolved dependency birleşiminde ÖNCELİK: duplicate KAZANIR.
mb_test(
	'record validator 14/20: duplicate_targets=true VE bağımlılık çözülemiyor birlikte -> conflict_duplicate_target (dependency DEĞİL)',
	( function () use ( $fx_sector_makine ) {
		$lookup = array( 'sector:makine' => array( 'target_found' => false, 'duplicate_targets' => true ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, $lookup, mb_empty_dependencies() ); // görseli var, dependency map boş.
		return MaviBelge_Core_Import_Decision::CONFLICT_DUPLICATE_TARGET === $plan['decision'];
	} )()
);

// 15. Wrong target type + unresolved dependency birleşiminde ÖNCELİK: wrong-type KAZANIR.
mb_test(
	'record validator 15/20: target_type_matches=false VE bağımlılık çözülemiyor birlikte -> conflict_wrong_target_type (dependency DEĞİL)',
	( function () use ( $fx_sector_makine ) {
		$lookup = array( 'sector:makine' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => false ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, $lookup, mb_empty_dependencies() ); // görseli var, dependency map boş.
		return MaviBelge_Core_Import_Decision::CONFLICT_WRONG_TARGET_TYPE === $plan['decision'];
	} )()
);

// 16. Managed allowlist ile projection/current alan anahtarları TAM EŞİT.
mb_test(
	'record validator 16/20: sektör projeksiyonu anahtarları SECTOR_FIELDS ile TAM eşit',
	( function () use ( $fx_sector_no_image ) {
		$fields = array_keys( MaviBelge_Core_Import_Managed_Fields::project_sector( $fx_sector_no_image, array() )['fields'] );
		$expected = MaviBelge_Core_Import_Managed_Fields::SECTOR_FIELDS;
		sort( $fields );
		sort( $expected );
		return $fields === $expected;
	} )()
);
mb_test(
	'record validator 16/20 ek: yeterlilik projeksiyonu anahtarları QUALIFICATION_FIELDS ile TAM eşit',
	( function () use ( $fx_qualification ) {
		$fields = array_keys( MaviBelge_Core_Import_Managed_Fields::project_qualification( $fx_qualification, array( 'sector_term_id' => 42 ) )['fields'] );
		$expected = MaviBelge_Core_Import_Managed_Fields::QUALIFICATION_FIELDS;
		sort( $fields );
		sort( $expected );
		return $fields === $expected;
	} )()
);
mb_test(
	'record validator 16/20 ek: ücret projeksiyonu anahtarları FEE_FIELDS ile TAM eşit',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = array_keys( MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'] );
		$expected = MaviBelge_Core_Import_Managed_Fields::FEE_FIELDS;
		sort( $fields );
		sort( $expected );
		return $fields === $expected;
	} )()
);

// 17. Operasyon grubu ve by_type sayaç invariant'ları.
mb_test(
	'record validator 17/20: operasyon grupları toplamı summary.total ile eşit',
	array_sum( $fullPlan['summary']['operations'] ) === $fullPlan['summary']['total']
);
mb_test(
	'record validator 17/20 ek: by_type toplamı summary.total ile eşit',
	array_sum( $fullPlan['summary']['by_type'] ) === $fullPlan['summary']['total']
);
mb_test(
	'record validator 17/20 ek: hiçbir invalid yokken applicable=true',
	true === $fullPlan['summary']['applicable']
);
mb_test(
	'record validator 17/20 ek: en az bir invalid varken applicable=false',
	( function () use ( $fx_sector_no_image_at0 ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
			array( 'sectors' => array( $fx_sector_no_image_at0, array( 'source_key' => 'sector:eksik' ) ), 'qualifications' => array(), 'fees' => array() ),
			array(),
			mb_empty_dependencies()
		);
		return false === $plan['summary']['applicable'] && true === $plan['summary']['has_invalid'];
	} )()
);

/* --- 18/19. Gerçek Faz 6A manifestlerinin TAMAMI kayıt doğrulayıcıdan geçer (uydurma veri yok) --- */
$mb_manifest_dir = dirname( __DIR__, 4 ) . '/data/content';
$mb_real_sectors  = json_decode( file_get_contents( $mb_manifest_dir . '/sectors.manifest.json' ), true );
$mb_real_quals    = json_decode( file_get_contents( $mb_manifest_dir . '/qualifications.manifest.json' ), true );
$mb_real_fees     = json_decode( file_get_contents( $mb_manifest_dir . '/fees.manifest.json' ), true );

mb_test( 'record validator 18/20: gerçek sectors.manifest.json TAM 14 kayıt taşıyor', 14 === count( $mb_real_sectors['records'] ) );
mb_test( 'record validator 18/20 ek: gerçek qualifications.manifest.json TAM 83 kayıt taşıyor', 83 === count( $mb_real_quals['records'] ) );
mb_test( 'record validator 18/20 ek: gerçek fees.manifest.json TAM 103 kayıt taşıyor', 103 === count( $mb_real_fees['records'] ) );

mb_test(
	'record validator 18/20 ek: gerçek 14 sektör kaydının TAMAMI validate_sector()\'dan GEÇER',
	( function () use ( $mb_real_sectors ) {
		foreach ( $mb_real_sectors['records'] as $record ) {
			if ( ! MaviBelge_Core_Import_Record_Validator::validate_sector( $record )['valid'] ) {
				return false;
			}
		}
		return true;
	} )()
);
mb_test(
	'record validator 18/20 ek: gerçek 83 yeterlilik kaydının TAMAMI validate_qualification()\'dan GEÇER',
	( function () use ( $mb_real_quals ) {
		foreach ( $mb_real_quals['records'] as $record ) {
			if ( ! MaviBelge_Core_Import_Record_Validator::validate_qualification( $record )['valid'] ) {
				return false;
			}
		}
		return true;
	} )()
);
mb_test(
	'record validator 18/20 ek: gerçek 103 ücret kaydının TAMAMI validate_fee()\'den GEÇER',
	( function () use ( $mb_real_fees ) {
		foreach ( $mb_real_fees['records'] as $record ) {
			if ( ! MaviBelge_Core_Import_Record_Validator::validate_fee( $record )['valid'] ) {
				return false;
			}
		}
		return true;
	} )()
);

// 19. Gerçek 4 görselsiz sektör doğru ayrılır (belgede önceki "5" düzeltildi).
mb_test(
	'record validator 19/20: gerçek manifestte TAM 4 görselsiz sektör var (is-makineleri, plastik, mobilya, guzellik-sac-bakim)',
	( function () use ( $mb_real_sectors ) {
		$noImage = array_values( array_filter( $mb_real_sectors['records'], function ( $r ) {
			return '' === $r['image'];
		} ) );
		$slugs = array_map( function ( $r ) {
			return $r['slug'];
		}, $noImage );
		sort( $slugs );
		$expected = array( 'guzellik-sac-bakim', 'is-makineleri', 'mobilya', 'plastik' );
		return 4 === count( $noImage ) && $slugs === $expected;
	} )()
);

// 20. Aynı tam girdi (gerçek 14 sektörün TAMAMI) iki kez byte-eşit plan üretir.
mb_test(
	'record validator 20/20: gerçek 14 sektörün TAMAMIYLA iki ayrı dry-run çağrısı byte-eşit sonuç verir',
	( function () use ( $mb_real_sectors ) {
		$manifest = array( 'sectors' => $mb_real_sectors['records'], 'qualifications' => array(), 'fees' => array() );
		$runA = MaviBelge_Core_Import_Dry_Run_Planner::plan( $manifest, array(), mb_empty_dependencies() );
		$runB = MaviBelge_Core_Import_Dry_Run_Planner::plan( $manifest, array(), mb_empty_dependencies() );
		return json_encode( $runA, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) === json_encode( $runB, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	} )()
);

/* -------------------------------------------------------------- *
 * Faz 6B1 Son Kapanış Düzeltmesi — 15 karşı-örnek (görev promptu §7).
 * Bir önceki turun 20 karşı-örneği (yukarıda) hâlâ geçerli ve KORUNDU;
 * bu blok yalnız BU TURDA kapatılan sözleşme açıklarını (tam şema alanı,
 * dizi-olmayan lookup, sıkı bool/tip kontrolü, applicable yeniden tanımı,
 * project_fee() 3-argüman regresyonu) kanıtlar.
 * -------------------------------------------------------------- */

// 1/15 — Üç türde tam-schema alanlarından birinin SİLİNMESİ -> invalid.
mb_test(
	'son kapanış 1/15: sektörde schema_version SİLİNİRSE -> invalid',
	( function () use ( $fx_sector_no_image ) {
		$r = $fx_sector_no_image;
		unset( $r['schema_version'] );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 1/15 ek: yeterlilikte source_index SİLİNİRSE -> invalid',
	( function () use ( $fx_qualification ) {
		$r = $fx_qualification;
		unset( $r['source_index'] );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 1/15 ek: ücrette source (zarf) TAMAMEN SİLİNİRSE -> invalid',
	( function () use ( $fx_fee_with_code ) {
		$r = $fx_fee_with_code;
		unset( $r['source'] );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);

// 2/15 — schema_version/source_index/source.file/source.sha256 bozuklukları.
mb_test(
	'son kapanış 2/15: schema_version "1.0.0" (yanlış sürüm) -> invalid',
	( function () use ( $fx_sector_no_image ) {
		$r = array_merge( $fx_sector_no_image, array( 'schema_version' => '1.0.0' ) );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 2/15 ek: source_index string ("0") -> invalid (tam sayı değil)',
	( function () use ( $fx_sector_no_image ) {
		$r = array_merge( $fx_sector_no_image, array( 'source_index' => '0' ) );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 2/15 ek: source.file mutlak yol ("/etc/passwd") -> invalid',
	( function () use ( $fx_sector_no_image ) {
		$r = $fx_sector_no_image;
		$r['source'] = array( 'file' => '/etc/passwd', 'sha256' => $r['source']['sha256'] );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 2/15 ek: source.file dizin geçişi ("../../etc/passwd") -> invalid',
	( function () use ( $fx_sector_no_image ) {
		$r = $fx_sector_no_image;
		$r['source'] = array( 'file' => '../../etc/passwd', 'sha256' => $r['source']['sha256'] );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 2/15 ek: source.sha256 63 hex (kısa) -> invalid',
	( function () use ( $fx_sector_no_image ) {
		$r = $fx_sector_no_image;
		$r['source'] = array( 'file' => $r['source']['file'], 'sha256' => str_repeat( 'a', 63 ) );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);

// 3/15 — Yeterlilik legacy bayrağı ve planned_record_status uyuşmazlığı.
mb_test(
	'son kapanış 3/15: matches_legacy_revision_required_format, has_revision ile TERS -> invalid',
	( function () use ( $fx_qualification ) {
		$r = array_merge( $fx_qualification, array( 'matches_legacy_revision_required_format' => false ) ); // has_revision=true iken false -> tutarsız
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 3/15 ek: planned_record_status "passive" (şema enum içinde ama bu fazda YASAK) -> invalid',
	( function () use ( $fx_qualification ) {
		$r = array_merge( $fx_qualification, array( 'planned_record_status' => 'passive' ) );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 3/15 ek: planned_record_status enum dışı ("archived") -> invalid',
	( function () use ( $fx_qualification ) {
		$r = array_merge( $fx_qualification, array( 'planned_record_status' => 'archived' ) );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);

// 4/15 — Ücret min/max yeniden-hesap uyuşmazlığı ve profession_name/source_key slug uyuşmazlığı.
mb_test(
	'son kapanış 4/15: min_amount_kurus, price_options ile TUTARSIZ -> invalid',
	( function () use ( $fx_fee_with_code ) {
		$r = array_merge( $fx_fee_with_code, array( 'min_amount_kurus' => 1 ) );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $r, array(), array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) )['decision'];
	} )()
);
mb_test(
	'son kapanış 4/15 ek: max_amount_kurus, price_options ile TUTARSIZ -> invalid',
	( function () use ( $fx_fee_with_code ) {
		$r = array_merge( $fx_fee_with_code, array( 'max_amount_kurus' => 9999999999 ) );
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $r, array(), array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) )['decision'];
	} )()
);
mb_test(
	'son kapanış 4/15 ek: profession_name değişti ama source_key AYNI kaldı (slug formülü tutarsız) -> invalid',
	( function () use ( $fx_fee_with_code ) {
		$r = array_merge( $fx_fee_with_code, array( 'profession_name' => 'Başka Bir Meslek' ) ); // source_key eski haliyle kaldı
		return MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $r, array(), mb_empty_dependencies() )['decision'];
	} )()
);
mb_test(
	'son kapanış 4/15 ek: gerçek "â" içeren meslek adı (Tezgâh) DOĞRU slug\'a eşleniyor (tezg-h) — pozitif kontrol',
	( function () use ( $fx_fee_without_code ) {
		$r = array_merge(
			$fx_fee_without_code,
			array(
				'source_key' => 'fee:enerji:3:tezg-h-iscisi',
				'profession_name' => 'Tezgâh İşçisi',
			)
		);
		$result = MaviBelge_Core_Import_Record_Validator::validate_fee( $r );
		return $result['valid'];
	} )()
);

// 5/15 — Dizi olmayan lookup girdisi -> invalid_target_state, create DEĞİL.
mb_test(
	'son kapanış 5/15: targetLookups[source_key] STRING (dizi değil) -> invalid_target_state, create DEĞİL',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => 'bozuk-string-deger' );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 5/15 ek: targetLookups[source_key] INT (dizi değil) -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => 42 );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 5/15 ek: targetLookups[source_key] NULL (dizi değil) -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => null );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 6/15 — target_found/duplicate_targets/has_source_key_marker için yanlış tipler.
mb_test(
	'son kapanış 6/15: target_found string ("true") -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => 'true' ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 6/15 ek: duplicate_targets int (1) -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'duplicate_targets' => 1 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 6/15 ek: has_source_key_marker null -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => null ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 6/15 ek: targetLookups içinde kapalı kümenin DIŞINDA bir anahtar ("extra_field") -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => false, 'extra_field' => 'x' ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 7/15 — target_found=false iken marker/hash/type çelişkilerinin HER BİRİ.
mb_test(
	'son kapanış 7/15: target_found=false ama has_source_key_marker=true -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => false, 'has_source_key_marker' => true ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 7/15 ek: target_found=false ama last_applied_hash DOLU (geçerli formatta) -> invalid_target_state',
	( function () use ( $fx_sector_no_image, $hash2 ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => false, 'last_applied_hash' => $hash2 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 7/15 ek: target_found=false ama target_type_matches=false (anlamsız negatif iddia) -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => false, 'target_type_matches' => false ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 8/15 — current_managed_fields anahtarları DOĞRU ama tek alan DEĞERİ yanlış tip -> conflict, hash YOK (üç tür de).
mb_test(
	'son kapanış 8/15: sektör current_managed_fields.image_attachment_id STRING ("0") -> invalid_target_state (hash fatal YOK)',
	( function () use ( $fx_sector_no_image, $incoming2, $hash2 ) {
		$broken = $incoming2;
		$broken['image_attachment_id'] = '0';
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $hash2, 'current_managed_fields' => $broken ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 8/15 ek: yeterlilik current_managed_fields.level STRING ("3") -> invalid_target_state',
	( function () use ( $fx_qualification ) {
		$currentFields = MaviBelge_Core_Import_Managed_Fields::project_qualification( $fx_qualification, array( 'sector_term_id' => 42 ) )['fields'];
		$hash = MaviBelge_Core_Import_Hash::hash( $currentFields );
		$currentFields['level'] = '3';
		$lookup = array( 'qualification:10UY0002-3/03' => array( 'target_found' => true, 'target_id' => 900, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $hash, 'current_managed_fields' => $currentFields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, $lookup, array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 8/15 ek: ücret current_managed_fields.vat_included STRING ("1") -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$currentFields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$hash = MaviBelge_Core_Import_Hash::hash( $currentFields );
		$currentFields['vat_included'] = '1';
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $hash, 'current_managed_fields' => $currentFields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 9/15 — Hedef bulundu (target_found=true, target_type_matches=true) AMA current fields eksik/null -> conflict.
mb_test(
	'son kapanış 9/15: target_found=true, target_type_matches=true, current_managed_fields YOK -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 10/15 — Marker yok + hash var; marker var + bozuk hash: AYRI nedenler.
mb_test(
	'son kapanış 10/15: has_source_key_marker=false AMA last_applied_hash DOLU (geçerli formatta) -> invalid_target_state (legacy_missing_hash DEĞİL)',
	( function () use ( $fx_sector_no_image, $incoming2, $hash2 ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => $hash2, 'current_managed_fields' => $incoming2 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'son kapanış 10/15 ek: has_source_key_marker=true VE last_applied_hash BOZUK -> legacy_missing_hash (invalid_target_state DEĞİL)',
	( function () use ( $fx_sector_no_image, $incoming2 ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => 'bozuk-hash', 'current_managed_fields' => $incoming2 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'legacy_missing_hash' === $plan['reason'];
	} )()
);

// 11/15 — Public plan()/plan_sector() sınırında scalar/null/malformed container -> throw/fatal YOK, kontrollü sonuç.
mb_test(
	'son kapanış 11/15: plan(null, [], []) -> TypeError/fatal YOK, kontrollü hata',
	( function () {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( null, array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'son kapanış 11/15 ek: plan("bozuk-string", [], []) -> TypeError/fatal YOK, kontrollü hata',
	( function () {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( 'bozuk-string', array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'son kapanış 11/15 ek: plan(manifest, "bozuk", []) — targetLookups dizi değil -> TypeError/fatal YOK, kontrollü hata',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ), 'bozuk', mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'son kapanış 11/15 ek: plan(manifest, [], 42) — dependencies dizi değil -> TypeError/fatal YOK, kontrollü hata',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ), array(), 42 );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'son kapanış 11/15 ek: plan_sector(record, null, []) — targetLookups null -> TypeError/fatal YOK, invalid entry',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, null, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::INVALID === $plan['decision'];
	} )()
);
mb_test(
	'son kapanış 11/15 ek: plan_qualification(record, [], "bozuk") — dependencies bozuk -> TypeError/fatal YOK, invalid entry',
	( function () use ( $fx_qualification ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), 'bozuk' );
		return MaviBelge_Core_Import_Decision::INVALID === $plan['decision'];
	} )()
);

// 12/15 — Dependency tür-doğrulama bayrağı (target_type_matches) EKSİK -> create/update SAYILMAZ.
mb_test(
	'son kapanış 12/15: target_found=true AMA target_type_matches anahtarı HİÇ YOK -> invalid_target_state (sessizce true varsayılmaz)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 13/15 — Yalnız conflict/blocked içeren planlarda applicable=false; temiz planda true.
mb_test(
	'son kapanış 13/15: yalnız conflict (manual edit) içeren plan -> applicable=false (has_invalid=false OLSA BİLE)',
	( function () use ( $fx_sector_no_image_at0, $incoming2, $hash2 ) {
		$manuallyEdited = array_merge( $incoming2, array( 'name' => 'Elle Değiştirildi' ) );
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 501, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $hash2, 'current_managed_fields' => $manuallyEdited ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_no_image_at0 ), 'qualifications' => array(), 'fees' => array() ), $lookup, mb_empty_dependencies() );
		return false === $plan['summary']['applicable'] && false === $plan['summary']['has_invalid'] && true === $plan['summary']['structurally_valid'] && 1 === $plan['summary']['operations']['conflict'];
	} )()
);
mb_test(
	'son kapanış 13/15 ek: yalnız blocked_dependency içeren plan -> applicable=false',
	( function () use ( $fx_sector_makine ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_makine ), 'qualifications' => array(), 'fees' => array() ), array(), mb_empty_dependencies() );
		return false === $plan['summary']['applicable'] && 1 === $plan['summary']['operations']['blocked'];
	} )()
);
mb_test(
	'son kapanış 13/15 ek: yalnız create/update/unchanged içeren TEMİZ plan -> applicable=true',
	true === $fullPlan['summary']['applicable'] && 0 === $fullPlan['summary']['operations']['conflict'] && 0 === $fullPlan['summary']['operations']['blocked']
);

// 14/15 — project_fee()'nin YENİ üç-argümanlı imzasıyla ArgumentCountError FIRLAMADIĞI kanıtı.
mb_test(
	'son kapanış 14/15: project_fee() üç argümanla (record, dependencies, normalized_price_options) İSTİSNASIZ çağrılabilir',
	( function () use ( $fx_fee_with_code ) {
		try {
			$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
			MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] );
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	} )()
);

// 15/15 — Gerçek 14/83/103 kaydın TAMAMI TAM kayıt sözleşmesiyle geçer (bkz. "18/20" testleri yukarıda)
// ve aynı girdi iki kez byte-eşit plan verir (bkz. "20/20" testi yukarıda) — burada AYRICA
// tekrarlanmaz, mükerrer test eklenmez; bu blok yalnız üstteki iki testin BU TURUN sıkılaştırılmış
// validate_sector()/validate_qualification()/validate_fee()'sini (tam şema + min/max + slug formülü
// + legacy-bayrak çapraz kontrolü dahil) kapsadığını teyit eder.
mb_test(
	'son kapanış 15/15: gerçek kayıtlar TAM şema doğrulamasından (schema_version/source_index/source dahil) da geçer',
	( function () use ( $mb_real_sectors, $mb_real_quals, $mb_real_fees ) {
		foreach ( $mb_real_sectors['records'] as $r ) {
			if ( ! MaviBelge_Core_Import_Record_Validator::validate_sector( $r )['valid'] ) {
				return false;
			}
		}
		foreach ( $mb_real_quals['records'] as $r ) {
			if ( ! MaviBelge_Core_Import_Record_Validator::validate_qualification( $r )['valid'] ) {
				return false;
			}
		}
		foreach ( $mb_real_fees['records'] as $r ) {
			if ( ! MaviBelge_Core_Import_Record_Validator::validate_fee( $r )['valid'] ) {
				return false;
			}
		}
		return true;
	} )()
);

/* -------------------------------------------------------------- *
 * Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri — görev promptu §3'ün 10 maddesi.
 * -------------------------------------------------------------- */

// 1. Lookup anahtarı hiç yok -> kanonik not-found DTO -> create (bağımlılık uygunsa).
mb_test(
	'nokta 1/10: targetLookups[source_key] anahtarı HİÇ YOK -> kanonik not-found -> create',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array(), mb_empty_dependencies() )['decision']
);

// 2. Lookup anahtarı VAR, değer null -> invalid_target_state (create DEĞİL). Regresyon: array_key_exists() düzeltmesi.
mb_test(
	'nokta 2/10: targetLookups[source_key] VAR ama değeri null -> invalid_target_state (create DEĞİL, array_key_exists() düzeltmesi)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => null );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 3. Lookup anahtarı VAR, değer [] veya zorunlu 'target_found' anahtarı eksik -> invalid_target_state.
mb_test(
	'nokta 3/10: targetLookups[source_key] VAR ama değeri [] (boş dizi, target_found eksik) -> invalid_target_state',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array() );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 3/10 ek: target_found VAR ama diğer her şey eksik (target_found=false, minimal) -> GEÇERLİ not-found (regresyon; target_found tek başına yeterli)',
	( function () use ( $fx_sector_no_image ) {
		$lookup = array( 'sector:is-makineleri' => array( 'target_found' => false ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CREATE === $plan['decision'];
	} )()
);

// 4. source_index: tekrarlanan / atlanan / ters çevrilen / gerçek konumla uyuşmayan -> toplu plan fail-closed.
mb_test(
	'nokta 4/10: iki sektörün source_index\'i AYNI (tekrar) -> plan() fail-closed, entries boş',
	( function () use ( $fx_sector_makine, $fx_sector_no_image ) {
		$second = array_merge( $fx_sector_no_image, array( 'source_index' => 0 ) ); // fx_sector_makine da 0'da, listede pozisyonu 1
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_makine, $second ), 'qualifications' => array(), 'fees' => array() ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'nokta 4/10 ek: source_index ATLANDI (0, 2 — 1 hiç yok) -> plan() fail-closed',
	( function () use ( $fx_sector_makine, $fx_sector_no_image ) {
		$second = array_merge( $fx_sector_no_image, array( 'source_index' => 2 ) ); // gerçek pozisyonu 1 olmalıydı
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_makine, $second ), 'qualifications' => array(), 'fees' => array() ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'nokta 4/10 ek: iki source_index TERS ÇEVRİLMİŞ (1,0 sırasıyla) -> plan() fail-closed',
	( function () use ( $fx_sector_makine, $fx_sector_no_image_at0 ) {
		$first = array_merge( $fx_sector_makine, array( 'source_index' => 1 ) );  // gerçek pozisyonu 0
		$second = array_merge( $fx_sector_no_image_at0, array( 'source_index' => 0 ) ); // gerçek pozisyonu 1
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $first, $second ), 'qualifications' => array(), 'fees' => array() ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'nokta 4/10 ek: tek kaydın source_index\'i gerçek konumuyla uyuşmuyor (0 yerine 4) -> plan() fail-closed',
	( function () use ( $fx_sector_no_image ) {
		// $fx_sector_no_image'ın GERÇEK source_index'i (4) burada TEK
		// eleman olarak kullanıldığında gerçek konumla (0) uyuşmuyor.
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);

// 5. Aynı tür listesinde source.file/source.sha256 diğerlerinden farklı -> fail-closed (provenance drift).
mb_test(
	'nokta 5/10: iki sektörden biri farklı source.sha256 taşıyor -> plan() fail-closed (provenance drift)',
	( function () use ( $fx_sector_makine, $fx_sector_no_image_at0 ) {
		$drifted = $fx_sector_no_image_at0;
		$drifted['source_index'] = 1;
		$drifted['source'] = array( 'file' => $drifted['source']['file'], 'sha256' => str_repeat( 'b', 64 ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_makine, $drifted ), 'qualifications' => array(), 'fees' => array() ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);
mb_test(
	'nokta 5/10 ek: iki sektörden biri farklı source.file taşıyor -> plan() fail-closed (provenance drift)',
	( function () use ( $fx_sector_makine, $fx_sector_no_image_at0 ) {
		$drifted = $fx_sector_no_image_at0;
		$drifted['source_index'] = 1;
		$drifted['source'] = array( 'file' => 'tanitim-site/assets/data/OTHER.js', 'sha256' => $drifted['source']['sha256'] );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_makine, $drifted ), 'qualifications' => array(), 'fees' => array() ), array(), mb_empty_dependencies() );
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);

// 6. Dependency alt map null/string/list; çözüm id/type_verified eksik/false/
// string; doğru typed sonuç. Faz 6B1 Zorunlu Dependency DTO Kapanışı §3.1
// madde 3-6 gereği bunların HEPSİ artık (yalnız "eksik/list container"
// DEĞİL, map İÇİNDEKİ HERHANGİ bir typed değerin bozuk olması da) ÜST DTO
// seviyesinde YAPISAL hata sayılır -> plan_qualification()'ın
// dependenciesCheck'i durur -> invalid. `blocked_dependency` ARTIK
// yalnız yapısal olarak TAMAMEN GEÇERLİ bir DTO içinde ilgili item'ın
// GERÇEKTEN bulunamadığı (aranan anahtar map'te yok) duruma özgüdür.
mb_test(
	'sözleşme eşitleme §2.1: dependency alt map NULL (sector_term_ids => null) -> VERİLMİŞ ama bozuk container -> yapısal invalid (blocked_dependency DEĞİL)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => null ) ) )['decision']
);
mb_test(
	'sözleşme eşitleme §2.1 ek: dependency alt map STRING (sector_term_ids => "bozuk") -> VERİLMİŞ ama bozuk container -> yapısal invalid (blocked_dependency DEĞİL)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => 'bozuk' ) ) )['decision']
);
mb_test(
	'nokta 6/10 ek: dependency alt map LİSTE (numeric-keyed, slug anahtarı yok) -> map şekli geçersiz -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( array( 'id' => 42, 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'nokta 6/10 ek: çözüm girdisinde "type_verified" EKSİK (yalnız id) -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42 ) ) ) ) )['decision']
);
mb_test(
	'nokta 6/10 ek: çözüm girdisinde "type_verified" false -> map değeri kapalı şekle uymuyor -> invalid (yapısal, adapter türü doğrulayamadı)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => false ) ) ) ) )['decision']
);
mb_test(
	'nokta 6/10 ek: çözüm girdisinde "type_verified" STRING ("true") -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => 'true' ) ) ) ) )['decision']
);
mb_test(
	'nokta 6/10 ek: çözüm girdisinde "id" STRING ("42") -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => '42', 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'nokta 6/10 ek: DOĞRU typed sonuç {id:int>0, type_verified:true} -> ÇÖZÜLDÜ -> create',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'nokta 6/10 ek: DOĞRU typed sonuçta fazladan bir anahtar ("note") -> map değeri kapalı şekle uymuyor -> invalid (yapısal, kapalı iki-anahtarlı şekil)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $fx_qualification, array(), array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'makine' => array( 'id' => 42, 'type_verified' => true, 'note' => 'x' ) ) ) ) )['decision']
);

// 7. Current price_options: boş, 21 satır, 201 char label, 11 unit, 51 char unit, bozuk sıra -> invalid_target_state, hash YOK.
mb_test(
	'nokta 7/10: current price_options BOŞ dizi -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$fields['price_options'] = array();
		$hash = MaviBelge_Core_Import_Hash::hash( $fields ); // orijinal (bozulmamış) alan setinin hash'i — yalnız lookup'a konur
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => $hash, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 7/10 ek: current price_options 21 SATIR (sınır 20) -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$rows = array();
		for ( $i = 0; $i < 21; $i++ ) {
			$rows[] = array( 'label' => 'Seçenek ' . $i, 'units' => array(), 'amount_kurus' => 1000 + $i, 'sort_order' => $i );
		}
		$fields['price_options'] = $rows;
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 7/10 ek: current price_options label 201 karakter (sınır 200) -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$fields['price_options'] = array( array( 'label' => str_repeat( 'a', 201 ), 'units' => array(), 'amount_kurus' => 1000, 'sort_order' => 0 ) );
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 7/10 ek: current price_options 11 unit (sınır 10) -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$units = array();
		for ( $i = 0; $i < 11; $i++ ) { $units[] = 'U' . $i; }
		$fields['price_options'] = array( array( 'label' => 'Seçenek', 'units' => $units, 'amount_kurus' => 1000, 'sort_order' => 0 ) );
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 7/10 ek: current price_options unit 51 karakter (sınır 50) -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$fields['price_options'] = array( array( 'label' => 'Seçenek', 'units' => array( str_repeat( 'u', 51 ) ), 'amount_kurus' => 1000, 'sort_order' => 0 ) );
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 7/10 ek: current price_options BOZUK SIRALI (sort_order azalan) -> invalid_target_state (kanonik depolama sırası bozuk)',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$fields['price_options'] = array(
			array( 'label' => 'İkinci', 'units' => array(), 'amount_kurus' => 2000, 'sort_order' => 1 ),
			array( 'label' => 'Birinci', 'units' => array(), 'amount_kurus' => 1000, 'sort_order' => 0 ),
		);
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 8. Current fee title/profession_name, code/post ID, tarih aralığı çapraz uyuşmazlıkları -> invalid_target_state.
mb_test(
	'nokta 8/10: current fee title !== profession_name -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$fields['title'] = 'Başka Bir Başlık';
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 8/10 ek: current fee qualification_code BOŞ ama qualification_post_id SIFIR DEĞİL -> invalid_target_state',
	( function () use ( $fx_fee_without_code ) {
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_without_code, array(), MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_without_code )['normalized_price_options'] )['fields'];
		$fields['qualification_post_id'] = 5; // kod boşken KESİNLİKLE 0 olmalıydı
		$lookup = array( 'fee:enerji:3:sicak-su-kazani-operatoru' => array( 'target_found' => true, 'target_id' => 902, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_without_code, $lookup, mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 8/10 ek: current fee qualification_code DOLU ama qualification_post_id SIFIR -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$fields['qualification_post_id'] = 0; // kod doluyken KESİNLİKLE pozitif olmalıydı
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);
mb_test(
	'nokta 8/10 ek: current fee valid_from > valid_until (ters aralık) -> invalid_target_state',
	( function () use ( $fx_fee_with_code ) {
		$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
		$fields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
		$fields['valid_from']  = '2026-06-01';
		$fields['valid_until'] = '2026-01-01';
		$lookup = array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array( 'target_found' => true, 'target_id' => 901, 'target_type_matches' => true, 'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => $fields ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'];
	} )()
);

// 9. Gerçek 14/83/103 manifest batch'i source-index/provenance kapısından GEÇER (uydurma veri yok).
mb_test(
	'nokta 9/10: gerçek 14 sektörlük TAM liste source-index/provenance kapısından hatasız geçer',
	empty( MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $mb_real_sectors['records'], 'sectors' ) )
);
mb_test(
	'nokta 9/10 ek: gerçek 83 yeterliliklik TAM liste source-index/provenance kapısından hatasız geçer',
	empty( MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $mb_real_quals['records'], 'qualifications' ) )
);
mb_test(
	'nokta 9/10 ek: gerçek 103 ücretlik TAM liste source-index/provenance kapısından hatasız geçer',
	empty( MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $mb_real_fees['records'], 'fees' ) )
);
mb_test(
	'nokta 9/10 ek: gerçek TAM manifest (14+83+103) plan()\'a verilince üst-seviye pozisyon/provenance hatası ÜRETMEZ',
	( function () use ( $mb_real_sectors, $mb_real_quals, $mb_real_fees ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
			array( 'sectors' => $mb_real_sectors['records'], 'qualifications' => $mb_real_quals['records'], 'fees' => $mb_real_fees['records'] ),
			array(),
			mb_empty_dependencies()
		);
		// Bağımlılık haritası boş olduğundan bloklu kayıtlar beklenir —
		// yalnız üst-seviye `errors`'ın BOŞ kaldığı (pozisyon/provenance
		// kapısının reddetmediği) kanıtlanır.
		return empty( $plan['errors'] ) && 200 === $plan['summary']['total'];
	} )()
);

// 10. summary.applicable üst-seviye hata durumunda (batch fail-closed) da false kalır.
mb_test(
	'nokta 10/10: üst-seviye pozisyon hatası olan bir plan() çağrısında summary.applicable=false (boş özet varsayılanı)',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ), array(), mb_empty_dependencies() );
		return false === $plan['summary']['applicable'] && false === $plan['summary']['structurally_valid'] && 0 === $plan['summary']['total'];
	} )()
);

/* ==================================================================
 * Sözleşme Eşitleme (§2.1/§2.2/§2.3) — zorunlu karşı-örnek testleri
 * (görev promptu §3, altı madde).
 * ================================================================== */

// §3.1a — plan() içinde dependency ÜST DTO'suna fazla/bilinmeyen bir
// anahtar eklenmişse fail-closed üst hata; hiçbir entry üretilmez.
mb_test(
	'sözleşme eşitleme 1a/6: plan() dependency ÜST DTO fazla anahtar ("extra_key") -> fail-closed üst hata, entries boş',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
			array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ),
			array(),
			array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array(), 'sector_image_attachment_ids' => array(), 'qualification_post_ids' => array(), 'extra_key' => array() ) )
		);
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] ) && false === $plan['summary']['applicable'];
	} )()
);
// §3.1b — dizi OLMAYAN bir alt map (sector_image_attachment_ids => "bozuk")
// tüm plan() çağrısını fail-closed reddeder — sektörlerin GÖRSELİ olup
// olmadığına BAKMADAN (kullanılmayan bir map bile artık geçmez).
mb_test(
	'sözleşme eşitleme 1b/6: plan() dependency ÜST DTO dizi-olmayan alt map (sector_image_attachment_ids => "bozuk") -> fail-closed üst hata, entries boş',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
			array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ),
			array(),
			array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array(), 'sector_image_attachment_ids' => 'bozuk', 'qualification_post_ids' => array() ) )
		);
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] ) && false === $plan['summary']['applicable'];
	} )()
);

// §3.2 — geçerli BOŞ map + ilgili öğenin eksik olması ("gerçek unresolved")
// bozuk container'dan AYRILIR: yalnız İLGİLİ kaydı blocked_dependency yapar,
// üst plan()'ı KESMEZ (nokta 8/9 zaten bunu kanıtlıyordu — burada plan()
// TOPLU yoldan da AYNI ayrımın korunduğu doğrulanıyor).
mb_test(
	'sözleşme eşitleme 2/6: geçerli boş dependency map + eksik öğe -> TEK kayıt blocked_dependency, plan() üst hatası YOK',
	( function () use ( $fx_sector_makine ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
			array( 'sectors' => array( $fx_sector_makine ), 'qualifications' => array(), 'fees' => array() ),
			array(),
			array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array(), 'sector_image_attachment_ids' => array(), 'qualification_post_ids' => array() ) )
		);
		return empty( $plan['errors'] ) && 1 === $plan['summary']['total']
			&& MaviBelge_Core_Import_Decision::BLOCKED_DEPENDENCY === $plan['entries'][0]['decision'];
	} )()
);

// §3.3 — görselsiz sektör + VERİLMİŞ bozuk sector_image_attachment_ids
// yapısı kabul edilmez (bu bulgunun somut örneği: sektörün görseli
// olmadığı için o alt map normalde hiç OKUNMAZDI — artık okunmasa BİLE
// üst DTO seviyesinde reddedilir). plan_sector() DOĞRUDAN çağrısı ile.
mb_test(
	'sözleşme eşitleme 3/6: GÖRSELSİZ sektör + verilmiş bozuk sector_image_attachment_ids (string) -> invalid (blocked_dependency DEĞİL, create DEĞİL)',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array(), array_merge( mb_empty_dependencies(), array( 'sector_image_attachment_ids' => 'bozuk-container' ) ) );
		return MaviBelge_Core_Import_Decision::INVALID === $plan['decision'];
	} )()
);
mb_test(
	'sözleşme eşitleme 3/6 ek: aynı senaryo plan() TOPLU yoldan da -> fail-closed üst hata',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
			array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ),
			array(),
			array_merge( mb_empty_dependencies(), array( 'sector_image_attachment_ids' => 'bozuk-container' ) )
		);
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] );
	} )()
);

// §3.4 — üç doğru typed resolver sonucu kabul edilir; type_verified eksik/
// false/string veya fazla anahtarlı sonuç ARTIK ÜST DTO seviyesinde
// yapısal hata (invalid) olarak reddedilir — bkz. "nokta 6/10" bloğu
// (satır ~2052-2087) sector_term_ids ÖRNEĞİYLE zaten tam kapsanıyor
// (doğru/eksik/false/string/fazla-anahtar/string-id hepsi test edilmiş,
// Zorunlu Dependency DTO Kapanışı §3.1'de blocked_dependency'den invalid'e
// TAŞINDI) — burada AYNI kapalı şekil kuralının qualification_post_ids VE
// sector_image_attachment_ids map'lerinde de BİREBİR uygulandığı ayrıca
// doğrulanır (tek bir kopya kural değil, validate_dependencies_shape()
// HER ÜÇ map için de kullanılıyor).
mb_test(
	'sözleşme eşitleme 4/6a: qualification_post_ids -> DOĞRU typed sonuç kabul edilir (create)',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, array(), array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'sözleşme eşitleme 4/6b: qualification_post_ids -> type_verified EKSİK -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, array(), array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77 ) ) ) ) )['decision']
);
mb_test(
	'sözleşme eşitleme 4/6c: sector_image_attachment_ids -> DOĞRU typed sonuç kabul edilir (create)',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, array(), array_merge( mb_empty_dependencies(), array( 'sector_image_attachment_ids' => array( 'makine' => array( 'id' => 900, 'type_verified' => true ) ) ) ) )['decision']
);
mb_test(
	'sözleşme eşitleme 4/6d: sector_image_attachment_ids -> type_verified STRING "true" -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, array(), array_merge( mb_empty_dependencies(), array( 'sector_image_attachment_ids' => array( 'makine' => array( 'id' => 900, 'type_verified' => 'true' ) ) ) ) )['decision']
);
mb_test(
	'sözleşme eşitleme 4/6e: sector_image_attachment_ids -> fazladan anahtarlı ("note") typed sonuç -> map değeri kapalı şekle uymuyor -> invalid (yapısal)',
	MaviBelge_Core_Import_Decision::INVALID === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, array(), array_merge( mb_empty_dependencies(), array( 'sector_image_attachment_ids' => array( 'makine' => array( 'id' => 900, 'type_verified' => true, 'note' => 'x' ) ) ) ) )['decision']
);

// §3.5/§3.6 — current price_options: kanonik eşitlik. Ortak yardımcı: aynı
// fee kaydının GEÇERLİ ("temiz") current alanlarını üretir, çağıran testler
// yalnız price_options'ı bozar.
$mb_clean_current_fee_fields = ( function () use ( $fx_fee_with_code ) {
	$validated = MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_with_code );
	return MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_with_code, array( 'qualification_post_id' => 77 ), $validated['normalized_price_options'] )['fields'];
} )();

function mb_test_current_price_canonical_equality( $label, array $cleanFields, $priceOptionsOverride, $expectRejected ) {
	global $fx_fee_with_code;
	$fields = $cleanFields;
	$fields['price_options'] = $priceOptionsOverride;
	$hash = MaviBelge_Core_Import_Hash::hash( $fields );
	$lookup = array(
		'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array(
			'target_found' => true, 'target_id' => 901, 'target_type_matches' => true,
			'has_source_key_marker' => true, 'last_applied_hash' => $hash, 'current_managed_fields' => $fields,
		),
	);
	$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
	if ( $expectRejected ) {
		mb_test( $label, MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'] );
	} else {
		mb_test( $label, 'invalid_target_state' !== $plan['reason'] );
	}
}

// HTML/temizlenmesi gereken label -> kanonik evaluate_price_options() etiketi
// strip_tags ile temizler, sonuç current değerden SAPAR -> invalid_target_state.
mb_test_current_price_canonical_equality(
	'sözleşme eşitleme 5/6a: current price_options label HTML içeriyor ("<b>X</b>") -> kanonik temizleme ile SAPAR -> invalid_target_state',
	$mb_clean_current_fee_fields,
	array( array( 'label' => '<b>Sınav ücreti</b>', 'units' => array( 'A1', 'A2', 'A3', 'A4' ), 'amount_kurus' => 1700000, 'sort_order' => 0 ) ),
	true
);
// Baş/son boşluklu label -> clean_short_text() trim eder -> SAPAR.
mb_test_current_price_canonical_equality(
	'sözleşme eşitleme 5/6b: current price_options label baş/son BOŞLUKLU ("  Sınav ücreti  ") -> kanonik trim ile SAPAR -> invalid_target_state',
	$mb_clean_current_fee_fields,
	array( array( 'label' => '  Sınav ücreti  ', 'units' => array( 'A1', 'A2', 'A3', 'A4' ), 'amount_kurus' => 1700000, 'sort_order' => 0 ) ),
	true
);
// Boş unit (units içinde '' var) -> kanonik normalize_price_options() boş
// unit'i sessizce ATLAR (bkz. class-validator.php satır ~418) -> liste
// UZUNLUĞU sapar -> current !== canonical -> invalid_target_state.
mb_test_current_price_canonical_equality(
	'sözleşme eşitleme 5/6c: current price_options units içinde BOŞ birim ("") -> kanonik atlama ile SAPAR -> invalid_target_state',
	$mb_clean_current_fee_fields,
	array( array( 'label' => 'Sınav ücreti', 'units' => array( 'A1', '', 'A2' ), 'amount_kurus' => 1700000, 'sort_order' => 0 ) ),
	true
);
// Temizlenmesi gereken unit (baş/son boşluk) -> kanonik clean_short_text()
// trim eder -> SAPAR.
mb_test_current_price_canonical_equality(
	'sözleşme eşitleme 5/6d: current price_options birimi TEMİZLENMESİ gereken (" A1 ") -> kanonik trim ile SAPAR -> invalid_target_state',
	$mb_clean_current_fee_fields,
	array( array( 'label' => 'Sınav ücreti', 'units' => array( ' A1 ' ), 'amount_kurus' => 1700000, 'sort_order' => 0 ) ),
	true
);
// Fazla satır anahtarı (bilinmeyen "currency" alanı) -> kanonik projeksiyon
// bu alanı hiç ÜRETMEZ -> satır şekli SAPAR -> invalid_target_state.
mb_test_current_price_canonical_equality(
	'sözleşme eşitleme 5/6e: current price_options satırında FAZLA anahtar ("currency") -> kanonik şekilden SAPAR -> invalid_target_state',
	$mb_clean_current_fee_fields,
	array( array( 'label' => 'Sınav ücreti', 'units' => array( 'A1', 'A2', 'A3', 'A4' ), 'amount_kurus' => 1700000, 'sort_order' => 0, 'currency' => 'TRY' ) ),
	true
);
// Kanonik sırası değişecek liste: iki satır, sort_order TERS sırada
// SAKLANMIŞ (mevcut durumun kendisi zaten kanonik-olmayan sırada) ->
// evaluate_price_options() bunu artan sort_order'a göre YENİDEN SIRALAR ->
// current (ters sıra) !== canonical (doğru sıra) -> invalid_target_state.
mb_test_current_price_canonical_equality(
	'sözleşme eşitleme 5/6f: current price_options KANONİK OLMAYAN (ters) sort_order sırasında saklı -> kanonik yeniden sıralamayla SAPAR -> invalid_target_state',
	$mb_clean_current_fee_fields,
	array(
		array( 'label' => 'İkinci', 'units' => array(), 'amount_kurus' => 2000000, 'sort_order' => 1 ),
		array( 'label' => 'Birinci', 'units' => array(), 'amount_kurus' => 1000000, 'sort_order' => 0 ),
	),
	true
);
// §3.6 — tamamen kanonik current fiyat listesi katı eşitlikten GEÇER ve
// önceki karar davranışını (current=last=incoming -> unchanged, current=
// last!=incoming -> update) korur; current_hash oluşur.
mb_test(
	'sözleşme eşitleme 6/6: TAMAMEN kanonik current price_options -> katı eşitlikten GEÇER, current_hash oluşur, invalid_target_state YOK',
	( function () use ( $mb_clean_current_fee_fields, $fx_fee_with_code ) {
		$hash = MaviBelge_Core_Import_Hash::hash( $mb_clean_current_fee_fields );
		$lookup = array(
			'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array(
				'target_found' => true, 'target_id' => 901, 'target_type_matches' => true,
				'has_source_key_marker' => true, 'last_applied_hash' => $hash, 'current_managed_fields' => $mb_clean_current_fee_fields,
			),
		);
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::UNCHANGED === $plan['decision'] && null !== $plan['current_hash'];
	} )()
);

/* ==================================================================
 * Faz 6B1 Zorunlu Dependency DTO Kapanışı — görev promptu §4, 11 madde.
 * ================================================================== */

// 1. Üç üst anahtarın her biri AYRI AYRI eksik -> tam plan() fail-closed, sıfır entry.
foreach ( array( 'sector_term_ids', 'sector_image_attachment_ids', 'qualification_post_ids' ) as $mb_missing_key ) {
	$mb_partial_deps = mb_empty_dependencies();
	unset( $mb_partial_deps[ $mb_missing_key ] );
	mb_test(
		"zorunlu DTO 1/11: üst anahtar \"{$mb_missing_key}\" EKSİK -> plan() fail-closed, entries boş",
		( function () use ( $fx_sector_no_image, $mb_partial_deps ) {
			$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
				array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ),
				array(),
				$mb_partial_deps
			);
			return empty( $plan['entries'] ) && ! empty( $plan['errors'] ) && false === $plan['summary']['applicable'];
		} )()
	);
}

// 2. Tamamen boş üst DTO array() -> fail-closed, sıfır entry (üç anahtar da eksik).
mb_test(
	'zorunlu DTO 2/11: dependencies tamamen boş array() (üç anahtar da eksik) -> plan() fail-closed, entries boş',
	( function () use ( $fx_sector_no_image ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan(
			array( 'sectors' => array( $fx_sector_no_image ), 'qualifications' => array(), 'fees' => array() ),
			array(),
			array()
		);
		return empty( $plan['entries'] ) && ! empty( $plan['errors'] ) && false === $plan['summary']['applicable'];
	} )()
);

// 3. Üç anahtarlı ve üç boş map'li DTO -> yapısal olarak GEÇERLİ.
mb_test(
	'zorunlu DTO 3/11: mb_empty_dependencies() (üç anahtar, üç boş map) -> validate_dependencies_shape() valid=true',
	MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape( mb_empty_dependencies() )['valid']
);

// 4. Non-empty numeric liste alt map -> yapısal hata (genel, kayıt tipinden bağımsız doğrudan doğrulayıcı çağrısı).
mb_test(
	'zorunlu DTO 4/11: sector_term_ids sayısal (0-tabanlı) liste -> validate_dependencies_shape() valid=false',
	! MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape(
		array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( array( 'id' => 1, 'type_verified' => true ) ) ) )
	)['valid']
);

// 5. Geçersiz sektör slug map anahtarı -> yapısal hata.
mb_test(
	'zorunlu DTO 5/11: sector_term_ids anahtarı geçersiz slug ("MAKINE_X") -> validate_dependencies_shape() valid=false',
	! MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape(
		array_merge( mb_empty_dependencies(), array( 'sector_term_ids' => array( 'MAKINE_X' => array( 'id' => 1, 'type_verified' => true ) ) ) )
	)['valid']
);
mb_test(
	'zorunlu DTO 5/11 ek: sector_image_attachment_ids anahtarı geçersiz slug ("makine_x") -> validate_dependencies_shape() valid=false',
	! MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape(
		array_merge( mb_empty_dependencies(), array( 'sector_image_attachment_ids' => array( 'makine_x' => array( 'id' => 1, 'type_verified' => true ) ) ) )
	)['valid']
);

// 6. Geçersiz MYK kodu map anahtarı -> yapısal hata.
mb_test(
	'zorunlu DTO 6/11: qualification_post_ids anahtarı geçersiz MYK kodu ("not-a-code") -> validate_dependencies_shape() valid=false',
	! MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape(
		array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( 'not-a-code' => array( 'id' => 1, 'type_verified' => true ) ) ) )
	)['valid']
);

// 7. Kullanılmayan map içindeki bozuk typed değer -> yapısal hata (görselsiz
// sektörde sector_image_attachment_ids HİÇ okunmaz, ama içindeki bozuk
// öğe yine de reddedilir).
mb_test(
	'zorunlu DTO 7/11: GÖRSELSİZ sektör + KULLANILMAYAN sector_image_attachment_ids map\'inde bozuk typed öğe (id negatif) -> invalid (yapısal, unused map bile geçmez)',
	( function () use ( $fx_sector_no_image ) {
		$deps = array_merge( mb_empty_dependencies(), array( 'sector_image_attachment_ids' => array( 'baska-sektor' => array( 'id' => -1, 'type_verified' => true ) ) ) );
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array(), $deps );
		return MaviBelge_Core_Import_Decision::INVALID === $plan['decision'];
	} )()
);

// 8. Tam DTO + gereken item EKSİK -> yalnız ilgili kayıt blocked_dependency
// (yapısal hata DEĞİL) — görseli olan sektörle doğrudan kanıtlanır.
mb_test(
	'zorunlu DTO 8/11: tam (üç anahtarlı) boş DTO + görseli olan sektörün item\'ı eksik -> blocked_dependency (invalid DEĞİL)',
	( function () use ( $fx_sector_makine ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, array(), mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::BLOCKED_DEPENDENCY === $plan['decision'];
	} )()
);

// 9. Tam DTO + doğru typed item -> önceki create/update/unchanged kararları korunur.
mb_test(
	'zorunlu DTO 9/11: tam DTO + doğru typed sector_image_attachment_ids item -> create (önceki davranış korunur)',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, array(), array_merge( mb_empty_dependencies(), array( 'sector_image_attachment_ids' => array( 'makine' => array( 'id' => 900, 'type_verified' => true ) ) ) ) )['decision']
);

// 10. Görselsiz sektör VE kodsuz ücret, tam (üç anahtarlı) boş DTO ile
// HİÇ bağımlılık aranmadan planlanabilir (create).
mb_test(
	'zorunlu DTO 10/11: GÖRSELSİZ sektör + tam boş DTO -> dependency aranmadan create',
	MaviBelge_Core_Import_Decision::CREATE === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array(), mb_empty_dependencies() )['decision']
);
mb_test(
	'zorunlu DTO 10/11 ek: KODSUZ ücret + tam boş DTO -> dependency aranmadan create (ilişki her zaman 0)',
	( function () use ( $fx_fee_without_code ) {
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_without_code, array(), mb_empty_dependencies() );
		return MaviBelge_Core_Import_Decision::CREATE === $plan['decision'] && empty( $plan['unresolved_dependencies'] );
	} )()
);

// 11. Interface'teki üç resolver'ın `?array` dönüş tipi statik sözleşme testiyle sabitlenir.
mb_test(
	'zorunlu DTO 11/11: MaviBelge_Core_Import_Target_Repository resolver\'larının üçü de "?array" dönüş tipi taşır',
	( function () {
		$reflection = new ReflectionClass( 'MaviBelge_Core_Import_Target_Repository' );
		foreach ( array( 'resolve_sector_term_id', 'resolve_qualification_post_id', 'resolve_sector_image_attachment_id' ) as $methodName ) {
			$method = $reflection->getMethod( $methodName );
			$returnType = $method->getReturnType();
			if ( null === $returnType || ! $returnType->allowsNull() || 'array' !== $returnType->getName() ) {
				return false;
			}
		}
		return true;
	} )()
);

// Düzeltme ve Kabul §2.3 / §4.1 madde 13 — `MaviBelge_Core_Import_Target_Repository`
// arayüzü `get_diagnostics()`'i taşır (kök nedenin kapatılmasının statik
// kanıtı: bu turdan önce servis, arayüzde HİÇ tanımlı olmayan bir metodu
// çağırıyordu) VE her iki uygulama (gerçek WordPress adapter'ı, test
// double) da bunu taşır — ikisi de "implements" ile bildirildiği için PHP
// bunu zaten class-declaration zamanında zorunlu kılar; bu test o
// garantiyi Reflection ile AYRICA doğrular.
mb_test(
	'Düzeltme ve Kabul §2.3: arayüz get_diagnostics() metodunu taşır',
	( new ReflectionClass( 'MaviBelge_Core_Import_Target_Repository' ) )->hasMethod( 'get_diagnostics' )
);
mb_test(
	'Düzeltme ve Kabul §2.3: MaviBelge_Core_Import_WordPress_Target_Repository get_diagnostics()\'i GERÇEKTEN uygular (method_exists\'in ötesinde, arayüz uyumluluğuyla)',
	( new MaviBelge_Core_Import_WordPress_Target_Repository() ) instanceof MaviBelge_Core_Import_Target_Repository
	&& method_exists( 'MaviBelge_Core_Import_WordPress_Target_Repository', 'get_diagnostics' )
);

/* ================================================================
 * Faz 6B2 — Salt Okunur WordPress Entegrasyonu ve Dry-Run Arayüzü.
 * Bu blok yalnız WordPress'siz test edilebilen İKİ katmanı kapsar:
 *   (a) MaviBelge_Core_Import_Manifest_Loader — saf PHP, hiçbir WP
 *       fonksiyonu çağırmaz;
 *   (b) MaviBelge_Core_Import_Dry_Run_Service — yalnız arayüz +
 *       loader + saf Faz 6B1 planlayıcıya bağımlı; GERÇEK WordPress
 *       repository'si YERİNE bu blokta tanımlanan saf PHP test double
 *       (MB_Test_Fake_Import_Repository) enjekte edilir.
 * `MaviBelge_Core_Import_WordPress_Target_Repository`'nin GERÇEK
 * get_term_by()/get_posts()/get_post_meta() davranışı, WP-CLI komutu ve
 * admin ekranı (nonce/yetki/kaçış) burada ÇALIŞTIRILAMAZ — bunlar dosya
 * sonundaki "NOT covered" listesine eklendi.
 * ================================================================ */

/** Yalnız test double — gerçek WordPress'e HİÇBİR bağımlılığı yok. */
class MB_Test_Fake_Import_Repository implements MaviBelge_Core_Import_Target_Repository {
	public $lookupsBySourceKey       = array();
	public $sectorTermIdsBySlug      = array();
	public $qualificationPostIdsByCode = array();
	public $sectorImageIdsBySlug     = array();
	public $diagnostics              = array();

	public function find_target_by_source_key( $type, $sourceKey ) {
		return array_key_exists( $sourceKey, $this->lookupsBySourceKey )
			? $this->lookupsBySourceKey[ $sourceKey ]
			: array( 'target_found' => false );
	}
	public function resolve_sector_term_id( $sectorSlug ): ?array {
		return array_key_exists( $sectorSlug, $this->sectorTermIdsBySlug ) ? $this->sectorTermIdsBySlug[ $sectorSlug ] : null;
	}
	public function resolve_qualification_post_id( $mykCode ): ?array {
		return array_key_exists( $mykCode, $this->qualificationPostIdsByCode ) ? $this->qualificationPostIdsByCode[ $mykCode ] : null;
	}
	public function resolve_sector_image_attachment_id( $sectorSlug ): ?array {
		return array_key_exists( $sectorSlug, $this->sectorImageIdsBySlug ) ? $this->sectorImageIdsBySlug[ $sectorSlug ] : null;
	}
	public function get_diagnostics() {
		return $this->diagnostics;
	}
}

/* --- Düzeltme ve Kabul §2.2 / §4.1 madde 8-12 — "bozuk meta tipi
 * sessizce düzeltilip geçerli sayılmıyor" — MaviBelge_Core_Import_WordPress_Target_Repository'nin
 * tek WordPress'siz test edilebilir parçası. ÖNCEKİ tur burada
 * `to_int("abc") === 0` gibi "biçimsiz -> 0'a düşer, downstream fail-closed
 * reddedebilir" iddiası taşıyordu — bu YANLIŞ yöndeydi (0 GEÇERLİ bir
 * değerdir, downstream onu "0" olarak KABUL EDER). Bu turda değiştirildi:
 * artık `strict_*()` dönüştürücüleri "başarı + değer" ayrımı taşır —
 * biçimsiz girdi ARTIK 0/false'A DÜŞMEZ, `ok=false` döner. --- */
mb_test( 'Faz 6B2 repo yardımcı 1: strict_int(5) -> ok=true, value=5 (gerçek int aynen korunur)', array( 'ok' => true, 'value' => 5 ) === MaviBelge_Core_Import_WordPress_Target_Repository::strict_int( 5 ) );
mb_test( 'Faz 6B2 repo yardımcı 2: strict_int("5") -> ok=true, value=5 (WordPress\'in string meta\'sı doğru çevrilir)', array( 'ok' => true, 'value' => 5 ) === MaviBelge_Core_Import_WordPress_Target_Repository::strict_int( '5' ) );
mb_test( 'Faz 6B2 repo yardımcı 3: strict_int("") -> ok=false (level gibi ZORUNLU bir alanda boş meta "0" DEĞİL, BAŞARISIZLIKTIR)', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_int( '' )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 4: strict_int("abc") -> ok=false — ARTIK 0\'a SESSİZCE DÜŞMEZ', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_int( 'abc' )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 5: strict_int("123abc") -> ok=false (kısmi sayısal string KABUL EDİLMEZ — "cast trick" yok)', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_int( '123abc' )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 6: strict_int(3.5) -> ok=false (float kabul edilmez)', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_int( 3.5 )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 7: strict_nonneg_int_or_empty_zero("") -> ok=true, value=0 (yalnız GERÇEKTEN eksik/boş meta 0\'a çevrilir)', array( 'ok' => true, 'value' => 0 ) === MaviBelge_Core_Import_WordPress_Target_Repository::strict_nonneg_int_or_empty_zero( '' ) );
mb_test( 'Faz 6B2 repo yardımcı 8: strict_nonneg_int_or_empty_zero(-5) -> ok=false (negatif ARTIK 0\'a düşmez)', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_nonneg_int_or_empty_zero( -5 )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 9: strict_nonneg_int_or_empty_zero("-3") -> ok=false', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_nonneg_int_or_empty_zero( '-3' )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 10: strict_nonneg_int_or_empty_zero(array()) -> ok=false (dizi -> false, TypeError/fatal yok, ARTIK 0\'a düşmez)', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_nonneg_int_or_empty_zero( array() )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 11: strict_nonneg_int_or_empty_zero("1x") -> ok=false', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_nonneg_int_or_empty_zero( '1x' )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 12: strict_nonneg_int_or_empty_zero(3.5) -> ok=false (float ARTIK sessizce reddedilmiyor gibi görünüp geçmiyor)', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_nonneg_int_or_empty_zero( 3.5 )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 13: strict_bool(true) -> ok=true, value=true', array( 'ok' => true, 'value' => true ) === MaviBelge_Core_Import_WordPress_Target_Repository::strict_bool( true ) );
mb_test( 'Faz 6B2 repo yardımcı 14: strict_bool("1") -> ok=true, value=true (WordPress\'in belgelenmiş meta temsili)', array( 'ok' => true, 'value' => true ) === MaviBelge_Core_Import_WordPress_Target_Repository::strict_bool( '1' ) );
mb_test( 'Faz 6B2 repo yardımcı 15: strict_bool("") -> ok=true, value=false (meta yok -> false)', array( 'ok' => true, 'value' => false ) === MaviBelge_Core_Import_WordPress_Target_Repository::strict_bool( '' ) );
mb_test( 'Faz 6B2 repo yardımcı 16: strict_bool("false") -> ok=false — string "false" GEÇERSİZ (görev promptu §2.2 örneği)', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_bool( 'false' )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 17: strict_bool("yes") -> ok=false', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_bool( 'yes' )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 18: strict_bool("banana") -> ok=false', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_bool( 'banana' )['ok'] );
mb_test( 'Faz 6B2 repo yardımcı 19: strict_bool(array()) -> ok=false', false === MaviBelge_Core_Import_WordPress_Target_Repository::strict_bool( array() )['ok'] );

/* ================================================================
 * Faz 6B2 Son Kapanış Düzeltmesi §3.3 — katı string yardımcısı ve saf
 * `*_fields_from_raw()` kurucuları. Kök neden: `(string) get_*_meta(...)`
 * bozuk dizi/nesne meta'yı "Array" gibi geçerli görünen bir string'e
 * çeviriyordu (özellikle yalnız is_string() kontrolü olan icon_key /
 * source_name). Bu testler WordPress'siz çalışabilen SAF katmanı kapsar;
 * gerçek get_term_meta()/get_post_meta() davranışı "NOT covered"
 * listesindedir.
 * ================================================================ */
$mb_repo = 'MaviBelge_Core_Import_WordPress_Target_Repository';

mb_test( 'Son Kapanış string 1: strict_string("abc") -> ok=true, value aynen "abc"', array( 'ok' => true, 'value' => 'abc' ) === $mb_repo::strict_string( 'abc' ) );
mb_test( 'Son Kapanış string 2: strict_string("") -> ok=true, value="" (boş string gerçek string; zorunluluğu sonraki validator belirler)', array( 'ok' => true, 'value' => '' ) === $mb_repo::strict_string( '' ) );
mb_test( 'Son Kapanış string 3: strict_string("0") -> ok=true, value="0"', array( 'ok' => true, 'value' => '0' ) === $mb_repo::strict_string( '0' ) );
mb_test( 'Son Kapanış string 4: strict_string(array("x")) -> ok=false ("Array" string\'ine dönüşmez)', array( 'ok' => false ) === $mb_repo::strict_string( array( 'x' ) ) );
mb_test( 'Son Kapanış string 5: strict_string(nested array) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_string( array( array( 'x' ), array( 'y' => array( 'z' ) ) ) ) );
mb_test( 'Son Kapanış string 6: strict_string(object) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_string( new stdClass() ) );
mb_test( 'Son Kapanış string 7: strict_string(int 5) -> ok=false (int string sayılmaz)', array( 'ok' => false ) === $mb_repo::strict_string( 5 ) );
mb_test( 'Son Kapanış string 8: strict_string(true) ve strict_string(false) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_string( true ) && array( 'ok' => false ) === $mb_repo::strict_string( false ) );
mb_test( 'Son Kapanış string 9: strict_string(1.5) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_string( 1.5 ) );
mb_test( 'Son Kapanış string 10: strict_string(null) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_string( null ) );

// Sistem alanları — marker ve hash.
mb_test( 'Son Kapanış marker 1: gerçek string + beklenen source_key -> eşleşir', true === $mb_repo::marker_raw_matches( 'sector:makine', 'sector:makine' ) );
mb_test( 'Son Kapanış marker 2: array marker -> eşleşmez (cast yok, uyarı yok)', false === $mb_repo::marker_raw_matches( array( 'sector:makine' ), 'sector:makine' ) );
mb_test( 'Son Kapanış marker 3: object marker -> eşleşmez', false === $mb_repo::marker_raw_matches( new stdClass(), 'sector:makine' ) );
mb_test( 'Son Kapanış marker 4: farklı source_key -> eşleşmez', false === $mb_repo::marker_raw_matches( 'sector:metal', 'sector:makine' ) );
mb_test( 'Son Kapanış marker 5: boş marker / boş source_key / null -> eşleşmez', false === $mb_repo::marker_raw_matches( '', '' ) && false === $mb_repo::marker_raw_matches( null, 'sector:makine' ) && false === $mb_repo::marker_raw_matches( '', 'sector:makine' ) );
$mb_valid_hex = str_repeat( 'ab', 32 );
mb_test( 'Son Kapanış hash 1: boş string -> ok=true, value=null (hash yok)', array( 'ok' => true, 'value' => null ) === $mb_repo::normalize_last_applied_hash_raw( '' ) );
mb_test( 'Son Kapanış hash 2: 64-hex string -> ok=true, aynen', array( 'ok' => true, 'value' => $mb_valid_hex ) === $mb_repo::normalize_last_applied_hash_raw( $mb_valid_hex ) );
mb_test( 'Son Kapanış hash 3: array/object/int/null hash -> ok=false (fail-closed, "Array" legacy hash\'e dönüşmez)', array( 'ok' => false ) === $mb_repo::normalize_last_applied_hash_raw( array( $mb_valid_hex ) ) && array( 'ok' => false ) === $mb_repo::normalize_last_applied_hash_raw( new stdClass() ) && array( 'ok' => false ) === $mb_repo::normalize_last_applied_hash_raw( 5 ) && array( 'ok' => false ) === $mb_repo::normalize_last_applied_hash_raw( null ) );

// Sektör kurucusu.
$mb_raw_sector = array( 'slug' => 'makine', 'name' => 'Makine', 'description' => 'd', 'icon_key' => 'gear', 'image_attachment_id' => '900' );
$mb_sector_fields = $mb_repo::sector_fields_from_raw( $mb_raw_sector );
mb_test( 'Son Kapanış sektör 1: geçerli ham değerler -> tipli alanlar (image_attachment_id int 900)', array( 'slug' => 'makine', 'name' => 'Makine', 'description' => 'd', 'icon_key' => 'gear', 'image_attachment_id' => 900 ) === $mb_sector_fields );
mb_test(
	'Son Kapanış sektör 2: geçerli kurucu çıktısı normalize_target_lookup()\'tan geçer (pozitif kontrol)',
	true === MaviBelge_Core_Import_Record_Validator::normalize_target_lookup(
		array( 'target_found' => true, 'target_id' => 42, 'duplicate_targets' => false, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => null, 'current_managed_fields' => $mb_sector_fields ),
		MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR
	)['target_state_valid']
);
mb_test( 'Son Kapanış sektör 3: icon_key=array(...) -> null (mevcut sektör durumuna DÖNÜŞEMEZ)', null === $mb_repo::sector_fields_from_raw( array_merge( $mb_raw_sector, array( 'icon_key' => array( 'gear' ) ) ) ) );
mb_test( 'Son Kapanış sektör 4: icon_key=object -> null', null === $mb_repo::sector_fields_from_raw( array_merge( $mb_raw_sector, array( 'icon_key' => new stdClass() ) ) ) );
foreach ( array( 'slug', 'name', 'description' ) as $mb_field ) {
	mb_test( "Son Kapanış sektör 5/{$mb_field}: çekirdek alan array/null -> null (cast ile gizlenmez)", null === $mb_repo::sector_fields_from_raw( array_merge( $mb_raw_sector, array( $mb_field => array( 'x' ) ) ) ) && null === $mb_repo::sector_fields_from_raw( array_merge( $mb_raw_sector, array( $mb_field => null ) ) ) );
}
mb_test( 'Son Kapanış sektör 6: eksik veya fazla ham anahtar -> null', null === $mb_repo::sector_fields_from_raw( array_diff_key( $mb_raw_sector, array( 'icon_key' => true ) ) ) && null === $mb_repo::sector_fields_from_raw( array_merge( $mb_raw_sector, array( 'extra' => 'x' ) ) ) );
mb_test(
	'Son Kapanış sektör 7: bozuk icon_key -> current_managed_fields=null -> normalize_target_lookup() invalid (target_state_valid=false, current_managed_fields=null)',
	( function () use ( $mb_repo, $mb_raw_sector ) {
		$fields = $mb_repo::sector_fields_from_raw( array_merge( $mb_raw_sector, array( 'icon_key' => array( 'gear' ) ) ) );
		$norm   = MaviBelge_Core_Import_Record_Validator::normalize_target_lookup(
			array( 'target_found' => true, 'target_id' => 42, 'duplicate_targets' => false, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => null, 'current_managed_fields' => $fields ),
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR
		);
		return null === $fields && false === $norm['target_state_valid'] && null === $norm['current_managed_fields'];
	} )()
);

// Yeterlilik kurucusu.
$mb_raw_qual = array( 'title' => 'Makine Bakımcı', 'myk_code' => '10UY0002-3/03', 'level' => '3', 'revision' => '03', 'record_status' => 'active', 'sector_term_ids' => array( 501 ) );
mb_test( 'Son Kapanış yeterlilik 1: geçerli ham değerler -> tipli alanlar', array( 'title' => 'Makine Bakımcı', 'myk_code' => '10UY0002-3/03', 'level' => 3, 'revision' => '03', 'record_status' => 'active', 'sector_term_id' => 501 ) === $mb_repo::qualification_fields_from_raw( $mb_raw_qual ) );
foreach ( array( 'title', 'myk_code', 'revision', 'record_status' ) as $mb_field ) {
	mb_test( "Son Kapanış yeterlilik 2/{$mb_field}: array/object -> null", null === $mb_repo::qualification_fields_from_raw( array_merge( $mb_raw_qual, array( $mb_field => array( 'x' ) ) ) ) && null === $mb_repo::qualification_fields_from_raw( array_merge( $mb_raw_qual, array( $mb_field => new stdClass() ) ) ) );
}
mb_test( 'Son Kapanış yeterlilik 3: 0 / 2+ / bozuk sektör terim ID -> null (ilk terim keyfî seçilmez)', null === $mb_repo::qualification_fields_from_raw( array_merge( $mb_raw_qual, array( 'sector_term_ids' => array() ) ) ) && null === $mb_repo::qualification_fields_from_raw( array_merge( $mb_raw_qual, array( 'sector_term_ids' => array( 501, 502 ) ) ) ) && null === $mb_repo::qualification_fields_from_raw( array_merge( $mb_raw_qual, array( 'sector_term_ids' => array( 'abc' ) ) ) ) && null === $mb_repo::qualification_fields_from_raw( array_merge( $mb_raw_qual, array( 'sector_term_ids' => array( 0 ) ) ) ) );
mb_test( 'Son Kapanış yeterlilik 4: taşan level ("999999999999999999999999") -> null', null === $mb_repo::qualification_fields_from_raw( array_merge( $mb_raw_qual, array( 'level' => '999999999999999999999999' ) ) ) );

// Ücret kurucusu — GERÇEK fixture'dan türetilmiş temiz current alanların
// WordPress'in string meta temsiline çevrilmiş hâli.
$mb_raw_fee = array();
foreach ( $mb_clean_current_fee_fields as $mb_k => $mb_v ) {
	if ( is_bool( $mb_v ) ) {
		$mb_raw_fee[ $mb_k ] = $mb_v ? '1' : '';
	} elseif ( is_int( $mb_v ) ) {
		$mb_raw_fee[ $mb_k ] = (string) $mb_v;
	} else {
		$mb_raw_fee[ $mb_k ] = $mb_v;
	}
}
mb_test(
	'Son Kapanış ücret 1: WordPress string temsili -> kurucu çıktısı temiz current alanlarla BİREBİR aynı (pozitif kontrol)',
	( function () use ( $mb_repo, $mb_raw_fee, $mb_clean_current_fee_fields ) {
		$built    = $mb_repo::fee_fields_from_raw( $mb_raw_fee );
		$expected = $mb_clean_current_fee_fields;
		if ( ! is_array( $built ) ) {
			return false;
		}
		ksort( $built );
		ksort( $expected );
		return $expected === $built;
	} )()
);
mb_test( 'Son Kapanış ücret 2: source_name=array(...) -> null (mevcut ücret durumuna DÖNÜŞEMEZ)', null === $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( 'source_name' => array( 'x' ) ) ) ) );
mb_test( 'Son Kapanış ücret 3: source_name=object -> null', null === $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( 'source_name' => new stdClass() ) ) ) );
foreach ( array( 'title', 'profession_name', 'sector_slug', 'qualification_code', 'pricing_type', 'source_name', 'tariff_period', 'record_status', 'valid_from', 'valid_until' ) as $mb_field ) {
	mb_test( "Son Kapanış ücret 4/{$mb_field}: array / nested array / object / int / null -> null", null === $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( $mb_field => array( 'x' ) ) ) ) && null === $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( $mb_field => array( array( 'x' ) ) ) ) ) && null === $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( $mb_field => new stdClass() ) ) ) && null === $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( $mb_field => 2026 ) ) ) && null === $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( $mb_field => null ) ) ) );
}
mb_test( 'Son Kapanış ücret 5: taşan certificate_print_fee_kurus -> null', null === $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( 'certificate_print_fee_kurus' => '99999999999999999999999999' ) ) ) );
mb_test(
	'Son Kapanış ücret 6: bozuk source_name hash hesabına ULAŞMADAN current_managed_fields=null -> plan_fee() conflict/invalid_target_state, current_hash=null',
	( function () use ( $mb_repo, $mb_raw_fee ) {
		global $fx_fee_with_code;
		$fields = $mb_repo::fee_fields_from_raw( array_merge( $mb_raw_fee, array( 'source_name' => array( 'x' ) ) ) );
		if ( null !== $fields ) {
			return false;
		}
		$lookup = array(
			'fee:guzellik-sac-bakim:4:guzellik-uzmani' => array(
				'target_found' => true, 'target_id' => 901, 'duplicate_targets' => false, 'target_type_matches' => true,
				'has_source_key_marker' => true, 'last_applied_hash' => null, 'current_managed_fields' => $fields,
			),
		);
		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $fx_fee_with_code, $lookup, array_merge( mb_empty_dependencies(), array( 'qualification_post_ids' => array( '16UY0244-4/02' => array( 'id' => 77, 'type_verified' => true ) ) ) ) );
		return MaviBelge_Core_Import_Decision::CONFLICT === $plan['decision'] && 'invalid_target_state' === $plan['reason'] && null === $plan['current_hash'];
	} )()
);

/* ================================================================
 * Faz 6B2 Son Kapanış Düzeltmesi §4.3 — integer taşması. Sınır-dışı
 * test değerleri PHP aritmetiği/float İLE DEĞİL, saf onluk-string
 * artırımıyla üretilir.
 * ================================================================ */

/** Saf onluk-string +1 (yalnız basamaklar; float/int aritmetiği yok). */
function mb_decimal_string_increment( $digits ) {
	$out   = '';
	$carry = 1;
	for ( $i = strlen( $digits ) - 1; $i >= 0; $i-- ) {
		$d      = ord( $digits[ $i ] ) - 48 + $carry;
		$carry  = $d >= 10 ? 1 : 0;
		$out    = chr( 48 + ( $d % 10 ) ) . $out;
	}
	return $carry ? '1' . $out : $out;
}
mb_test( 'Son Kapanış taşma yardımcısı: "9"+1="10", "199"+1="200", "0"+1="1"', '10' === mb_decimal_string_increment( '9' ) && '200' === mb_decimal_string_increment( '199' ) && '1' === mb_decimal_string_increment( '0' ) );

$mb_int_max_str       = (string) PHP_INT_MAX;
$mb_int_max_plus_one  = mb_decimal_string_increment( $mb_int_max_str );
$mb_int_min_str       = (string) PHP_INT_MIN;
$mb_int_min_minus_one = '-' . mb_decimal_string_increment( substr( $mb_int_min_str, 1 ) );
$mb_long_pos          = '999999999999999999999999999999999999';
$mb_long_neg          = '-999999999999999999999999999999999999';

mb_test( 'Son Kapanış taşma 1: strict_int(PHP_INT_MAX string) -> ok=true, value=PHP_INT_MAX', array( 'ok' => true, 'value' => PHP_INT_MAX ) === $mb_repo::strict_int( $mb_int_max_str ) );
mb_test( 'Son Kapanış taşma 2: strict_int(PHP_INT_MAX değerinden bir büyük string) -> ok=false (sınır değere kırpılmaz)', array( 'ok' => false ) === $mb_repo::strict_int( $mb_int_max_plus_one ) );
mb_test( 'Son Kapanış taşma 3: strict_int(çok uzun pozitif) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_int( $mb_long_pos ) );
mb_test( 'Son Kapanış taşma 4: strict_int(PHP_INT_MIN string) -> ok=true, value=PHP_INT_MIN', array( 'ok' => true, 'value' => PHP_INT_MIN ) === $mb_repo::strict_int( $mb_int_min_str ) );
mb_test( 'Son Kapanış taşma 5: strict_int(PHP_INT_MIN değerinden bir küçük string) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_int( $mb_int_min_minus_one ) );
mb_test( 'Son Kapanış taşma 6: strict_int(çok uzun negatif) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_int( $mb_long_neg ) );
mb_test( 'Son Kapanış taşma 7: strict_nonneg_int_or_empty_zero(PHP_INT_MAX string) -> ok=true, value=PHP_INT_MAX', array( 'ok' => true, 'value' => PHP_INT_MAX ) === $mb_repo::strict_nonneg_int_or_empty_zero( $mb_int_max_str ) );
mb_test( 'Son Kapanış taşma 8: strict_nonneg_int_or_empty_zero(PHP_INT_MAX değerinden bir büyük string) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_nonneg_int_or_empty_zero( $mb_int_max_plus_one ) );
mb_test( 'Son Kapanış taşma 9: strict_nonneg_int_or_empty_zero(çok uzun pozitif) -> ok=false', array( 'ok' => false ) === $mb_repo::strict_nonneg_int_or_empty_zero( $mb_long_pos ) );
mb_test( 'Son Kapanış taşma 10: strict_nonneg_int_or_empty_zero(PHP_INT_MIN string) -> ok=false (negatif)', array( 'ok' => false ) === $mb_repo::strict_nonneg_int_or_empty_zero( $mb_int_min_str ) );
foreach ( array( '+1', '1e2', '1.0', ' 1', '1 ', '1abc', "1\n", '0x1A', "\t1" ) as $mb_bad ) {
	mb_test(
		'Son Kapanış biçim: ' . json_encode( $mb_bad ) . ' strict_int ve strict_nonneg_int_or_empty_zero tarafından reddedilir',
		array( 'ok' => false ) === $mb_repo::strict_int( $mb_bad ) && array( 'ok' => false ) === $mb_repo::strict_nonneg_int_or_empty_zero( $mb_bad )
	);
}
mb_test( 'Son Kapanış biçim: leading zero ("007", "00") reddedilir — kanonik olmayan biçim', array( 'ok' => false ) === $mb_repo::strict_int( '007' ) && array( 'ok' => false ) === $mb_repo::strict_nonneg_int_or_empty_zero( '00' ) );
mb_test( 'Son Kapanış biçim: negatif sıfır "-0" her iki yardımcıda reddedilir', array( 'ok' => false ) === $mb_repo::strict_int( '-0' ) && array( 'ok' => false ) === $mb_repo::strict_nonneg_int_or_empty_zero( '-0' ) );
mb_test( 'Son Kapanış biçim: "0" ve int 0 her iki yardımcıda 0 olarak kabul', array( 'ok' => true, 'value' => 0 ) === $mb_repo::strict_int( '0' ) && array( 'ok' => true, 'value' => 0 ) === $mb_repo::strict_int( 0 ) && array( 'ok' => true, 'value' => 0 ) === $mb_repo::strict_nonneg_int_or_empty_zero( '0' ) && array( 'ok' => true, 'value' => 0 ) === $mb_repo::strict_nonneg_int_or_empty_zero( 0 ) );
mb_test( 'Son Kapanış biçim: boş string yalnız strict_nonneg_int_or_empty_zero\'da 0; strict_int\'te ret (mevcut sözleşme korunur)', array( 'ok' => true, 'value' => 0 ) === $mb_repo::strict_nonneg_int_or_empty_zero( '' ) && array( 'ok' => false ) === $mb_repo::strict_int( '' ) );
mb_test( 'Son Kapanış biçim: strict_int("-5") -> ok=true, value=-5; strict_nonneg_int_or_empty_zero("-5") ret', array( 'ok' => true, 'value' => -5 ) === $mb_repo::strict_int( '-5' ) && array( 'ok' => false ) === $mb_repo::strict_nonneg_int_or_empty_zero( '-5' ) );
foreach ( array( 'array' => array( '1' ), 'object' => new stdClass(), 'true' => true, 'false' => false, 'float' => 1.0, 'null' => null ) as $mb_label => $mb_bad ) {
	mb_test( "Son Kapanış tip: {$mb_label} strict_int ve strict_nonneg_int_or_empty_zero tarafından reddedilir", array( 'ok' => false ) === $mb_repo::strict_int( $mb_bad ) && array( 'ok' => false ) === $mb_repo::strict_nonneg_int_or_empty_zero( $mb_bad ) );
}

/* --- Runtime Doğrulama turu — admin `mb_paged` saf biçim kararı. Desen
 * `$` yerine `\z` ile bitmeli: `$` sondaki "\n"i tolere eder. --- */
mb_test( 'mb_paged 1: "1" kabul -> 1', 1 === MaviBelge_Core_Import_Dry_Run_Page::normalize_paged_value( '1' ) );
mb_test( 'mb_paged 2: "10" kabul -> 10', 10 === MaviBelge_Core_Import_Dry_Run_Page::normalize_paged_value( '10' ) );
foreach ( array( 'int 0' => 0, '"0"' => '0', '"-1"' => '-1', '"1.0"' => '1.0', '"1e2"' => '1e2', '"1\\n"' => "1\n", '"1\\r\\n"' => "1\r\n", 'dizi' => array( '1' ), 'nesne' => new stdClass(), 'null' => null ) as $mb_label => $mb_bad ) {
	mb_test( "mb_paged ret: {$mb_label} -> null", null === MaviBelge_Core_Import_Dry_Run_Page::normalize_paged_value( $mb_bad ) );
}

/* --- §11.1 madde 1/2: manifest loader — GERÇEK üç manifest dosyası
 * (bu tarama, sonraki testlerde MAVIBELGE_IMPORT_MANIFEST_DIR
 * TANIMLANMADAN ÖNCE çalışır — o sabit yalnız BİR KEZ tanımlanabilir ve
 * aşağıda servis testleri için tanımlanacaktır). --- */
$mb6b2_real_load = MaviBelge_Core_Import_Manifest_Loader::load_all();
mb_test( 'Faz 6B2 loader 1: gerçek üç manifest dosyası başarıyla yüklenir', $mb6b2_real_load['ok'] );
mb_test( 'Faz 6B2 loader 2: sectors tam 14 kayıt', $mb6b2_real_load['ok'] && 14 === count( $mb6b2_real_load['manifest']['sectors'] ) );
mb_test( 'Faz 6B2 loader 3: qualifications tam 83 kayıt', $mb6b2_real_load['ok'] && 83 === count( $mb6b2_real_load['manifest']['qualifications'] ) );
mb_test( 'Faz 6B2 loader 4: fees tam 103 kayıt', $mb6b2_real_load['ok'] && 103 === count( $mb6b2_real_load['manifest']['fees'] ) );
mb_test( 'Faz 6B2 loader 5: kayıtlar filtrelenmeden/yeniden sıralanmadan verilir — ilk sektörün source_key\'i "sector:makine"', $mb6b2_real_load['ok'] && isset( $mb6b2_real_load['manifest']['sectors'][0]['source_key'] ) && 'sector:makine' === $mb6b2_real_load['manifest']['sectors'][0]['source_key'] );
$mb6b2_real_load_again = MaviBelge_Core_Import_Manifest_Loader::load_all();
mb_test( 'Faz 6B2 loader 6: iki ayrı yükleme BİREBİR aynı diziyi üretir (deterministik, byte-eşit)', $mb6b2_real_load === $mb6b2_real_load_again );
mb_test(
	'Faz 6B2 loader 7: FILES sabiti yalnız üç düz dosya adı içerir — yol ayırıcı/".." YOK (path traversal yüzeyi yapısal olarak kapalı)',
	3 === count( MaviBelge_Core_Import_Manifest_Loader::FILES ) && array_reduce(
		MaviBelge_Core_Import_Manifest_Loader::FILES,
		function ( $carry, $f ) {
			return $carry && is_string( $f ) && false === strpos( $f, '/' ) && false === strpos( $f, '\\' ) && false === strpos( $f, '..' );
		},
		true
	)
);

/** İzole, tek kullanımlık bir dizine bir zarf JSON seti yazar; $overrides[$type] false ise o dosya hiç yazılmaz. */
function mb6b2_write_fixture_envelope( $dir, array $overrides = array() ) {
	@mkdir( $dir, 0777, true );
	$sha  = str_repeat( 'a', 64 );
	$base = array(
		'sector'        => array( 'schema_version' => '2.0.0', 'record_type' => 'sector', 'count' => 0, 'source' => array( 'file' => 'x', 'sha256' => $sha ), 'notes' => array(), 'records' => array() ),
		'qualification' => array( 'schema_version' => '2.0.0', 'record_type' => 'qualification', 'count' => 0, 'source' => array( 'file' => 'x', 'sha256' => $sha ), 'notes' => array(), 'records' => array() ),
		'fee'           => array( 'schema_version' => '2.0.0', 'record_type' => 'fee', 'count' => 0, 'source' => array( 'file' => 'x', 'sha256' => $sha ), 'counts' => array(), 'records' => array() ),
	);
	foreach ( MaviBelge_Core_Import_Manifest_Loader::FILES as $type => $filename ) {
		if ( array_key_exists( $type, $overrides ) && false === $overrides[ $type ] ) {
			continue; // Dosya hiç yazılmaz -> "bulunamadı" senaryosu.
		}
		$content = array_key_exists( $type, $overrides ) ? $overrides[ $type ] : $base[ $type ];
		file_put_contents( $dir . '/' . $filename, is_string( $content ) ? $content : json_encode( $content ) );
	}
}

function mb6b2_temp_dir( $label ) {
	$dir = sys_get_temp_dir() . '/mb6b2_' . $label . '_' . getmypid() . '_' . mt_rand( 1000, 9999 );
	return $dir;
}

/**
 * Yalnız BU sürecin (getmypid) sys_get_temp_dir() altında mb6b2_temp_dir()
 * ile oluşturduğu düz fixture dizinlerini siler. Fixture'lar yalnız düz
 * *.json dosyaları içerir (alt dizin/symlink oluşturulmaz); beklenmeyen bir
 * alt dizin/symlink görülürse ona dokunulmaz ve dizin yerinde bırakılır —
 * bu durumda yukarıdaki temizlik assertion'ı başarısız olur.
 */
function mb6b2_cleanup_temp_dirs() {
	$dirs = glob( sys_get_temp_dir() . '/mb6b2_*_' . getmypid() . '_*', GLOB_ONLYDIR );
	if ( ! is_array( $dirs ) ) {
		return;
	}
	foreach ( $dirs as $dir ) {
		if ( is_link( $dir ) ) {
			continue;
		}
		$entries = scandir( $dir );
		if ( ! is_array( $entries ) ) {
			continue;
		}
		$onlyFiles = true;
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_link( $path ) || ! is_file( $path ) ) {
				$onlyFiles = false;
				continue;
			}
			unlink( $path );
		}
		if ( $onlyFiles ) {
			rmdir( $dir );
		}
	}
}

// §11.1 madde 2 — eksik/bozuk/fazla alanlı zarf, yanlış record_type/count, geçersiz JSON, aşırı dosya.
$mb6b2_dir_missing = mb6b2_temp_dir( 'missing' );
mb6b2_write_fixture_envelope( $mb6b2_dir_missing, array( 'sector' => false ) );
mb_test( 'Faz 6B2 loader 8: eksik dosya (sectors.manifest.json yok) -> ok=false', false === MaviBelge_Core_Import_Manifest_Loader::load_all( $mb6b2_dir_missing )['ok'] );

$mb6b2_dir_empty = mb6b2_temp_dir( 'empty' );
mb6b2_write_fixture_envelope( $mb6b2_dir_empty, array( 'sector' => '' ) );
mb_test( 'Faz 6B2 loader 9: boş dosya -> ok=false', false === MaviBelge_Core_Import_Manifest_Loader::load_all( $mb6b2_dir_empty )['ok'] );

$mb6b2_dir_badjson = mb6b2_temp_dir( 'badjson' );
mb6b2_write_fixture_envelope( $mb6b2_dir_badjson, array( 'sector' => '{not valid json' ) );
mb_test( 'Faz 6B2 loader 10: geçersiz JSON -> ok=false', false === MaviBelge_Core_Import_Manifest_Loader::load_all( $mb6b2_dir_badjson )['ok'] );

$mb6b2_dir_wrongtype = mb6b2_temp_dir( 'wrongtype' );
mb6b2_write_fixture_envelope(
	$mb6b2_dir_wrongtype,
	array( 'sector' => array( 'schema_version' => '2.0.0', 'record_type' => 'qualification', 'count' => 0, 'source' => array( 'file' => 'x', 'sha256' => str_repeat( 'a', 64 ) ), 'notes' => array(), 'records' => array() ) )
);
mb_test( 'Faz 6B2 loader 11: yanlış record_type (sectors.manifest.json içinde "qualification") -> ok=false', false === MaviBelge_Core_Import_Manifest_Loader::load_all( $mb6b2_dir_wrongtype )['ok'] );

$mb6b2_dir_countmismatch = mb6b2_temp_dir( 'countmismatch' );
mb6b2_write_fixture_envelope(
	$mb6b2_dir_countmismatch,
	array( 'sector' => array( 'schema_version' => '2.0.0', 'record_type' => 'sector', 'count' => 5, 'source' => array( 'file' => 'x', 'sha256' => str_repeat( 'a', 64 ) ), 'notes' => array(), 'records' => array() ) )
);
mb_test( 'Faz 6B2 loader 12: count (5), records uzunluğuyla (0) uyuşmuyor -> ok=false', false === MaviBelge_Core_Import_Manifest_Loader::load_all( $mb6b2_dir_countmismatch )['ok'] );

$mb6b2_dir_extrakey = mb6b2_temp_dir( 'extrakey' );
mb6b2_write_fixture_envelope(
	$mb6b2_dir_extrakey,
	array( 'sector' => array( 'schema_version' => '2.0.0', 'record_type' => 'sector', 'count' => 0, 'source' => array( 'file' => 'x', 'sha256' => str_repeat( 'a', 64 ) ), 'notes' => array(), 'records' => array(), 'bogus_extra_field' => 'x' ) )
);
mb_test( 'Faz 6B2 loader 13: fazladan üst-seviye alan ("bogus_extra_field") -> ok=false (kapalı zarf politikası)', false === MaviBelge_Core_Import_Manifest_Loader::load_all( $mb6b2_dir_extrakey )['ok'] );

$mb6b2_dir_oversize = mb6b2_temp_dir( 'oversize' );
mb6b2_write_fixture_envelope( $mb6b2_dir_oversize, array( 'sector' => str_repeat( 'a', MaviBelge_Core_Import_Manifest_Loader::MAX_FILE_BYTES + 1 ) ) );
mb_test( 'Faz 6B2 loader 14: izin verilen boyutu aşan dosya -> ok=false', false === MaviBelge_Core_Import_Manifest_Loader::load_all( $mb6b2_dir_oversize )['ok'] );

$mb6b2_dir_nondict = mb6b2_temp_dir( 'nondict' );
mb6b2_write_fixture_envelope( $mb6b2_dir_nondict, array( 'sector' => '[1,2,3]' ) );
mb_test( 'Faz 6B2 loader 15: üst seviye bir nesne değil (JSON dizisi) -> ok=false', false === MaviBelge_Core_Import_Manifest_Loader::load_all( $mb6b2_dir_nondict )['ok'] );

/* --- Düzeltme ve Kabul §2.5 / §4.1 madde 17-19 — dosyaya özel alan
 * ZORUNLU + kapalı `source` şekli + yol gizliliği. Her senaryo ÜÇÜNÜ DE
 * geçerli/eşleşen bir `source.file` ile override eder ki sınanan alan
 * TEK başına başarısızlık nedeni olsun (yalnız test okunurluğu için). --- */
function mb6b2_valid_sector_envelope() {
	$sha = str_repeat( 'd', 64 );
	return array( 'schema_version' => '2.0.0', 'record_type' => 'sector', 'count' => 0, 'source' => array( 'file' => 'tanitim-site/assets/data/sectors.js', 'sha256' => $sha ), 'notes' => array(), 'records' => array() );
}
function mb6b2_valid_qualification_envelope() {
	$sha = str_repeat( 'e', 64 );
	return array( 'schema_version' => '2.0.0', 'record_type' => 'qualification', 'count' => 0, 'source' => array( 'file' => 'tanitim-site/assets/data/qualifications.js', 'sha256' => $sha ), 'notes' => array(), 'records' => array() );
}
function mb6b2_valid_fee_envelope() {
	$sha = str_repeat( 'f', 64 );
	return array(
		'schema_version' => '2.0.0', 'record_type' => 'fee', 'count' => 0,
		'source' => array( 'file' => 'tanitim-site/assets/data/fees.js', 'sha256' => $sha ), 'records' => array(),
		'counts' => array( 'total' => 0, 'priceOptionsTotal' => 0, 'pricingSingle' => 0, 'pricingMulti' => 0, 'multiOptionsTotal' => 0, 'feesWithCode' => 0, 'feesWithoutCode' => 0, 'linkedCount' => 0 ),
	);
}
/** İzole senaryo: yalnız $type'ın envelope'unu $mutator ile bozar, diğer ikisi tam geçerli. */
function mb6b2_isolated_loader_case( $label, $type, callable $mutator ) {
	$dir = mb6b2_temp_dir( $label );
	$envelopes = array(
		'sector'        => mb6b2_valid_sector_envelope(),
		'qualification' => mb6b2_valid_qualification_envelope(),
		'fee'           => mb6b2_valid_fee_envelope(),
	);
	$envelopes[ $type ] = $mutator( $envelopes[ $type ] );
	mb6b2_write_fixture_envelope( $dir, $envelopes );
	return MaviBelge_Core_Import_Manifest_Loader::load_all( $dir );
}

// Baseline — üç tam geçerli, izole envelope BAŞARIYLA yüklenmeli (aşağıdaki tüm "tek alan bozuk" testlerinin gerçekten TEK nedenden başarısız olduğunu kanıtlar).
$mb6b2_isolated_baseline = mb6b2_isolated_loader_case( 'isobaseline', 'sector', function ( $e ) { return $e; } );
mb_test( 'Faz 6B2 loader 16: üç tam geçerli izole envelope -> ok=true (izolasyon fixture\'ının kendisi sağlam)', true === $mb6b2_isolated_baseline['ok'] );

mb_test(
	'Faz 6B2 loader 17: §2.5 madde 2 — notes EKSİK (sektör) -> ok=false (artık yalnız izin verilen değil ZORUNLU)',
	false === mb6b2_isolated_loader_case( 'notesmissing', 'sector', function ( $e ) { unset( $e['notes'] ); return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 18: §2.5 madde 2 — counts EKSİK (ücret) -> ok=false',
	false === mb6b2_isolated_loader_case( 'countsmissing', 'fee', function ( $e ) { unset( $e['counts'] ); return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 19: §2.5 madde 3 — notes string-olmayan öğe içeriyor -> ok=false',
	false === mb6b2_isolated_loader_case( 'notesbadtype', 'sector', function ( $e ) { $e['notes'] = array( 123 ); return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 20: §2.5 madde 4 — counts eksik anahtar -> ok=false',
	false === mb6b2_isolated_loader_case( 'countsmissingkey', 'fee', function ( $e ) { unset( $e['counts']['linkedCount'] ); return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 21: §2.5 madde 4 — counts fazladan anahtar -> ok=false',
	false === mb6b2_isolated_loader_case( 'countsextrakey', 'fee', function ( $e ) { $e['counts']['bogus'] = 1; return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 22: §2.5 madde 4 — counts negatif değer -> ok=false',
	false === mb6b2_isolated_loader_case( 'countsnegative', 'fee', function ( $e ) { $e['counts']['total'] = -1; return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 23: §2.5 madde 5 — source fazladan alan taşıyor -> ok=false (kapalı şekil)',
	false === mb6b2_isolated_loader_case( 'sourceextra', 'sector', function ( $e ) { $e['source']['bogus'] = 'x'; return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 24: §2.5 madde 5 — source.file beklenen sabit yolla uyuşmuyor -> ok=false',
	false === mb6b2_isolated_loader_case( 'sourcewrongfile', 'sector', function ( $e ) { $e['source']['file'] = 'yanlis/yol.js'; return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 25: §2.5 madde 5 — source.sha256 65 karakter (fazladan hane) -> ok=false',
	false === mb6b2_isolated_loader_case( 'shatoolong', 'sector', function ( $e ) { $e['source']['sha256'] .= 'a'; return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 26: §2.5 madde 5 — source.sha256 büyük harf içeriyor -> ok=false',
	false === mb6b2_isolated_loader_case( 'shauppercase', 'sector', function ( $e ) { $e['source']['sha256'] = strtoupper( $e['source']['sha256'] ); return $e; } )['ok']
);
mb_test(
	'Faz 6B2 loader 27: §2.5 madde 6 — kayıt source\'u zarf source\'uyla tutarsız (provenance drift) -> ok=false',
	false === mb6b2_isolated_loader_case(
		'provdrift',
		'sector',
		function ( $e ) {
			$e['count']   = 1;
			$e['records'] = array(
				array(
					'schema_version' => '2.0.0', 'source_key' => 'sector:x', 'source_index' => 0, 'slug' => 'x', 'name' => 'X', 'description' => '', 'icon' => '', 'image' => '',
					'source' => array( 'file' => $e['source']['file'], 'sha256' => str_repeat( '9', 64 ) ), // sha256 zarftan FARKLI.
				),
			);
			return $e;
		}
	)['ok']
);
mb_test(
	'Faz 6B2 loader 28: §2.5 madde 1 — dizin bulunamadığında hata metninde mutlak yol/gizli sunucu ayrıntısı GEÇMEZ',
	( function () {
		$missingDir = sys_get_temp_dir() . '/mb6b2_kesinlikle_olmayan_dizin_' . getmypid() . '_' . mt_rand( 100000, 999999 );
		$result     = MaviBelge_Core_Import_Manifest_Loader::load_all( $missingDir );
		if ( $result['ok'] ) {
			return false; // beklenmedik — dizin gerçekten yok sayılmalıydı.
		}
		foreach ( $result['errors'] as $error ) {
			if ( false !== strpos( $error, $missingDir ) ) {
				return false; // mutlak yol sızdı.
			}
		}
		return true;
	} )()
);

/* --- §11.1 madde 12/13/14/3/5/6/9/10 — Dry-Run Service, saf PHP test
 * double (MB_Test_Fake_Import_Repository) ile UÇTAN UCA. Servis kendi
 * mantığını YAZMIYOR — yalnız loader + repository + Faz 6B1
 * planlayıcısını AYNI şekilde birleştiriyor, WP-CLI/admin bu servisi
 * çağıracak (bkz. class-import-cli-command.php / admin/class-import-
 * dry-run-page.php — statik sözleşme testi tools/test-faz6b2-static-
 * contract.js'de).
 *
 * MAVIBELGE_IMPORT_MANIFEST_DIR SABİTİ burada TEK SEFER tanımlanır —
 * bu satırdan SONRA `load_all()`'ın override'sız (varsayılan) her
 * çağrısı (servis dahil) bu küçük, kontrollü fixture setini kullanır.
 * Yukarıdaki TÜM loader testleri (gerçek 14/83/103 dahil) bu satırdan
 * ÖNCE çalıştığı için ETKİLENMEZ. --- */
$mb6b2_service_dir = mb6b2_temp_dir( 'service' );
$mb6b2_sha_a        = str_repeat( 'a', 64 );
$mb6b2_sha_b        = str_repeat( 'b', 64 );
$mb6b2_sha_c        = str_repeat( 'c', 64 );
mb6b2_write_fixture_envelope(
	$mb6b2_service_dir,
	array(
		'sector'        => array(
			'schema_version' => '2.0.0', 'record_type' => 'sector', 'count' => 2,
			'source' => array( 'file' => 'tanitim-site/assets/data/sectors.js', 'sha256' => $mb6b2_sha_a ), 'notes' => array(),
			'records' => array(
				array( 'schema_version' => '2.0.0', 'source_key' => 'sector:test-a', 'source_index' => 0, 'slug' => 'test-a', 'name' => 'Test A', 'description' => 'd', 'icon' => 'gear', 'image' => 'img.png', 'source' => array( 'file' => 'tanitim-site/assets/data/sectors.js', 'sha256' => $mb6b2_sha_a ) ),
				array( 'schema_version' => '2.0.0', 'source_key' => 'sector:test-b', 'source_index' => 1, 'slug' => 'test-b', 'name' => 'Test B', 'description' => 'd', 'icon' => 'gear', 'image' => '', 'source' => array( 'file' => 'tanitim-site/assets/data/sectors.js', 'sha256' => $mb6b2_sha_a ) ),
			),
		),
		'qualification' => array(
			'schema_version' => '2.0.0', 'record_type' => 'qualification', 'count' => 1,
			'source' => array( 'file' => 'tanitim-site/assets/data/qualifications.js', 'sha256' => $mb6b2_sha_b ), 'notes' => array(),
			'records' => array(
				array( 'schema_version' => '2.0.0', 'source_key' => 'qualification:10UY0002-3/03', 'source_index' => 0, 'code' => '10UY0002-3/03', 'name' => 'Test Q', 'level' => 3, 'sector_slug' => 'test-a', 'revision' => '03', 'has_revision' => true, 'matches_legacy_revision_required_format' => true, 'planned_record_status' => 'active', 'source' => array( 'file' => 'tanitim-site/assets/data/qualifications.js', 'sha256' => $mb6b2_sha_b ) ),
			),
		),
		'fee'           => array(
			'schema_version' => '2.0.0', 'record_type' => 'fee', 'count' => 2,
			'source' => array( 'file' => 'tanitim-site/assets/data/fees.js', 'sha256' => $mb6b2_sha_c ), 'counts' => array( 'total' => 2, 'priceOptionsTotal' => 2, 'pricingSingle' => 2, 'pricingMulti' => 0, 'multiOptionsTotal' => 0, 'feesWithCode' => 1, 'feesWithoutCode' => 1, 'linkedCount' => 1 ),
			'records' => array(
				array( 'schema_version' => '2.0.0', 'source_key' => 'fee:test-a:3:test-meslek', 'source_index' => 0, 'profession_name' => 'Test Meslek', 'level' => 3, 'sector_slug' => 'test-a', 'qualification_code' => '10UY0002-3/03', 'qualification_source_key' => 'qualification:10UY0002-3/03', 'pricing_type' => 'single', 'price_options' => array( array( 'label' => 'Sınav ücreti', 'units' => array(), 'amount_kurus' => 100000, 'sort_order' => 0 ) ), 'min_amount_kurus' => 100000, 'max_amount_kurus' => 100000, 'vat_included' => true, 'certificate_print_fee_kurus' => 0, 'source_name' => 'x', 'source_page' => null, 'source_attachment_id' => 0, 'planned_tariff_period' => '2026', 'planned_record_status' => 'draft', 'planned_valid_from' => '', 'planned_valid_until' => '', 'source' => array( 'file' => 'tanitim-site/assets/data/fees.js', 'sha256' => $mb6b2_sha_c ) ),
				array( 'schema_version' => '2.0.0', 'source_key' => 'fee:test-a:3:test-meslek-2', 'source_index' => 1, 'profession_name' => 'Test Meslek 2', 'level' => 3, 'sector_slug' => 'test-a', 'qualification_code' => '', 'qualification_source_key' => null, 'pricing_type' => 'single', 'price_options' => array( array( 'label' => 'Sınav ücreti', 'units' => array(), 'amount_kurus' => 200000, 'sort_order' => 0 ) ), 'min_amount_kurus' => 200000, 'max_amount_kurus' => 200000, 'vat_included' => true, 'certificate_print_fee_kurus' => 0, 'source_name' => 'x', 'source_page' => null, 'source_attachment_id' => 0, 'planned_tariff_period' => '2026', 'planned_record_status' => 'draft', 'planned_valid_from' => '', 'planned_valid_until' => '', 'source' => array( 'file' => 'tanitim-site/assets/data/fees.js', 'sha256' => $mb6b2_sha_c ) ),
			),
		),
	)
);
define( 'MAVIBELGE_IMPORT_MANIFEST_DIR', $mb6b2_service_dir );

// Test A — hiçbir hedef yok, TÜM bağımlılıklar çözülüyor -> beş kayıt da create.
$mb6b2_repo_a = new MB_Test_Fake_Import_Repository();
$mb6b2_repo_a->sectorTermIdsBySlug['test-a']          = array( 'id' => 501, 'type_verified' => true );
$mb6b2_repo_a->sectorImageIdsBySlug['test-a']         = array( 'id' => 900, 'type_verified' => true );
$mb6b2_repo_a->qualificationPostIdsByCode['10UY0002-3/03'] = array( 'id' => 700, 'type_verified' => true );
$mb6b2_result_a = ( new MaviBelge_Core_Import_Dry_Run_Service( $mb6b2_repo_a ) )->run_dry_run();

mb_test( 'Faz 6B2 servis A/1: loader başarıyla yüklenir, load_errors boş', empty( $mb6b2_result_a['load_errors'] ) );
mb_test( 'Faz 6B2 servis A/2: plan.errors boş (yapısal hata yok)', empty( $mb6b2_result_a['plan']['errors'] ) );
mb_test( 'Faz 6B2 servis A/3: toplam 5 entry (2 sektör + 1 yeterlilik + 2 ücret) — hiçbiri filtrelenmedi', 5 === $mb6b2_result_a['plan']['summary']['total'] );
mb_test( 'Faz 6B2 servis A/4: beş kaydın TAMAMI create (hedef yok, bağımlılıklar çözüldü)', 5 === $mb6b2_result_a['plan']['summary']['operations']['create'] );
mb_test( 'Faz 6B2 servis A/5: summary.applicable=true (yalnız create/update/unchanged)', true === $mb6b2_result_a['plan']['summary']['applicable'] );
mb_test( 'Faz 6B2 servis A/6: read_only=true', true === $mb6b2_result_a['read_only'] );
mb_test( 'Faz 6B2 servis A/7: kayıt SIRASI korunur — ilk entry "sector:test-a"', 'sector:test-a' === $mb6b2_result_a['plan']['entries'][0]['source_key'] );
mb_test( 'Faz 6B2 servis A/8: kayıt SIRASI korunur — son entry "fee:test-a:3:test-meslek-2"', 'fee:test-a:3:test-meslek-2' === $mb6b2_result_a['plan']['entries'][4]['source_key'] );

// Test B — hiçbir bağımlılık ÇÖZÜLMÜYOR: görseli olan sektör + yeterlilik +
// kodlu ücret blocked_dependency; görselsiz sektör + kodsuz ücret YİNE de
// create (tahmin/fuzzy aranmadan).
$mb6b2_repo_b   = new MB_Test_Fake_Import_Repository(); // Tüm resolver map'leri boş -> hiçbir dependency çözülmez.
$mb6b2_result_b = ( new MaviBelge_Core_Import_Dry_Run_Service( $mb6b2_repo_b ) )->run_dry_run();
mb_test( 'Faz 6B2 servis B/1: 3 kayıt blocked_dependency (görselli sektör + yeterlilik + kodlu ücret)', 3 === $mb6b2_result_b['plan']['summary']['operations']['blocked'] );
mb_test( 'Faz 6B2 servis B/2: 2 kayıt YİNE create (görselsiz sektör + kodsuz ücret — dependency aranmadan)', 2 === $mb6b2_result_b['plan']['summary']['operations']['create'] );
mb_test( 'Faz 6B2 servis B/3: summary.applicable=false (blocked_dependency var)', false === $mb6b2_result_b['plan']['summary']['applicable'] );

// Test C — hedef bulundu ama marker var + last_applied_hash YOK -> conflict (legacy_missing_hash).
$mb6b2_repo_c = new MB_Test_Fake_Import_Repository();
$mb6b2_repo_c->lookupsBySourceKey['sector:test-b'] = array(
	'target_found' => true, 'target_id' => 42, 'duplicate_targets' => false, 'target_type_matches' => true,
	'has_source_key_marker' => true, 'last_applied_hash' => null,
	'current_managed_fields' => array( 'slug' => 'test-b', 'name' => 'Test B', 'description' => 'd', 'icon_key' => 'gear', 'image_attachment_id' => 0 ),
);
$mb6b2_result_c = ( new MaviBelge_Core_Import_Dry_Run_Service( $mb6b2_repo_c ) )->run_dry_run();
mb_test(
	'Faz 6B2 servis C: marker VAR + last_applied_hash YOK -> conflict/legacy_missing_hash',
	( function () use ( $mb6b2_result_c ) {
		foreach ( $mb6b2_result_c['plan']['entries'] as $entry ) {
			if ( 'sector:test-b' === $entry['source_key'] ) {
				return MaviBelge_Core_Import_Decision::CONFLICT === $entry['decision'] && MaviBelge_Core_Import_Decision::REASON_LEGACY_MISSING_HASH === $entry['reason'];
			}
		}
		return false;
	} )()
);

// Test D — hedef bulundu ama TÜRÜ yanlış -> conflict_wrong_target_type.
$mb6b2_repo_d = new MB_Test_Fake_Import_Repository();
$mb6b2_repo_d->lookupsBySourceKey['sector:test-b'] = array(
	'target_found' => true, 'target_id' => 42, 'duplicate_targets' => false, 'target_type_matches' => false,
	'has_source_key_marker' => false, 'last_applied_hash' => null, 'current_managed_fields' => null,
);
$mb6b2_result_d = ( new MaviBelge_Core_Import_Dry_Run_Service( $mb6b2_repo_d ) )->run_dry_run();
mb_test(
	'Faz 6B2 servis D: hedef bulundu ama türü UYUŞMUYOR -> conflict_wrong_target_type',
	( function () use ( $mb6b2_result_d ) {
		foreach ( $mb6b2_result_d['plan']['entries'] as $entry ) {
			if ( 'sector:test-b' === $entry['source_key'] ) {
				return MaviBelge_Core_Import_Decision::CONFLICT_WRONG_TARGET_TYPE === $entry['decision'];
			}
		}
		return false;
	} )()
);

// Test E — aynı source_key'de BİRDEN FAZLA hedef bulundu -> conflict_duplicate_target (ilk kayıt KEYFÎ seçilmez).
$mb6b2_repo_e = new MB_Test_Fake_Import_Repository();
$mb6b2_repo_e->lookupsBySourceKey['sector:test-b'] = array( 'target_found' => false, 'duplicate_targets' => true );
$mb6b2_result_e = ( new MaviBelge_Core_Import_Dry_Run_Service( $mb6b2_repo_e ) )->run_dry_run();
mb_test(
	'Faz 6B2 servis E: aynı source_key\'de BİRDEN FAZLA hedef -> conflict_duplicate_target',
	( function () use ( $mb6b2_result_e ) {
		foreach ( $mb6b2_result_e['plan']['entries'] as $entry ) {
			if ( 'sector:test-b' === $entry['source_key'] ) {
				return MaviBelge_Core_Import_Decision::CONFLICT_DUPLICATE_TARGET === $entry['decision'];
			}
		}
		return false;
	} )()
);

// §11.1 madde 14 — AYNI fixture + AYNI repository durumuyla iki ayrı
// dry-run çağrısı karar/hash bakımından BİREBİR aynı sonucu üretir;
// yalnız generated_at_utc değişebilir (o alan plan'ın DIŞINDADIR).
$mb6b2_repo_a2   = new MB_Test_Fake_Import_Repository();
$mb6b2_repo_a2->sectorTermIdsBySlug['test-a']              = array( 'id' => 501, 'type_verified' => true );
$mb6b2_repo_a2->sectorImageIdsBySlug['test-a']             = array( 'id' => 900, 'type_verified' => true );
$mb6b2_repo_a2->qualificationPostIdsByCode['10UY0002-3/03'] = array( 'id' => 700, 'type_verified' => true );
$mb6b2_result_a2 = ( new MaviBelge_Core_Import_Dry_Run_Service( $mb6b2_repo_a2 ) )->run_dry_run();
mb_test( 'Faz 6B2 servis A/9 (idempotency): aynı girdiyle iki ayrı dry-run\'ın plan\'ı BİREBİR aynı (generated_at_utc hariç)', $mb6b2_result_a['plan'] === $mb6b2_result_a2['plan'] );

// §11.1 madde 12 — her servis çağrısında üç dependency üst anahtarı, hiçbir
// bağımlılık gerekmese bile (ör. loader tamamen boş bir manifest üretse
// bile) HER ZAMAN mevcuttur. build_dependencies() private olduğu için
// Reflection ile doğrudan çağrılır.
mb_test(
	'Faz 6B2 servis 12/12: build_dependencies() sonucu, hiç ihtiyaç OLMASA bile üç anahtarın TAMAMINI taşır',
	( function () {
		$service    = new MaviBelge_Core_Import_Dry_Run_Service( new MB_Test_Fake_Import_Repository() );
		$reflection = new ReflectionMethod( $service, 'build_dependencies' );
		$reflection->setAccessible( true );
		$result = $reflection->invoke( $service, array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array() ) );
		$expectedKeys = array( 'sector_term_ids', 'sector_image_attachment_ids', 'qualification_post_ids' );
		sort( $expectedKeys );
		$actualKeys = array_keys( $result );
		sort( $actualKeys );
		return $expectedKeys === $actualKeys;
	} )()
);

/* ================================================================
 * Faz 6B3 Önkoşul ve Yazma Güvenliği — saf testler (gerçek manifest
 * fixture'ları: $fx_sector_makine, $fx_sector_no_image, $fx_qualification,
 * $fx_fee_with_code, $mb_clean_current_fee_fields). WordPress gerektiren
 * kısımlar (post marker sanitize'ı, gerçek get_terms/get_posts preflight'ı,
 * gerçek update_post_meta) izole WordPress runtime testindedir.
 * ================================================================ */

/* --- A) TL / kuruş sözleşmesi: kanonik kuruş asla ikinci kez x100 olmaz --- */
$mb_fee_schema   = MaviBelge_Core_Meta_Schema::get_fields_for( 'mb_ucret' );
$mb_cert_cfg     = $mb_fee_schema['_mb_certificate_print_fee_kurus'];
$mb_min_cfg      = $mb_fee_schema['_mb_min_amount_kurus'];
$mb_money_sv     = function ( $raw ) use ( $mb_cert_cfg ) {
	return MaviBelge_Core_Field_Repository::sanitize_and_validate( $mb_cert_cfg, $raw );
};
mb_test( 'kuruş: _mb_certificate_print_fee_kurus/_mb_min/_mb_max şemada money_kurus; money_try hiçbir alanda yok',
	'money_kurus' === $mb_cert_cfg['type'] && 'try' === $mb_cert_cfg['admin_input'] && 'money_kurus' === $mb_min_cfg['type'] && 'money_kurus' === $mb_fee_schema['_mb_max_amount_kurus']['type']
	&& ! in_array( 'money_try', array_map( function ( $c ) { return $c['type']; }, $mb_fee_schema ), true ) );
mb_test( 'kuruş: 150000 (int) yazıldığında 150000 kalır', array( 150000, null ) === $mb_money_sv( 150000 ) );
mb_test( 'kuruş: "150000" (kanonik string) -> 150000, 15000000 DEĞİL', array( 150000, null ) === $mb_money_sv( '150000' ) );
mb_test( 'kuruş: sanitize üç kez uygulansa da 150000 (idempotent, x100 yok)', array( 150000, null ) === $mb_money_sv( $mb_money_sv( $mb_money_sv( 150000 )[0] )[0] ) );
mb_test( 'kuruş: 0 / "0" / "" / null -> 0 (alan sözleşmesi: tanımlı değil/ücret yok)', array( 0, null ) === $mb_money_sv( 0 ) && array( 0, null ) === $mb_money_sv( '0' ) && array( 0, null ) === $mb_money_sv( '' ) && array( 0, null ) === $mb_money_sv( null ) );
foreach ( array( 'negatif int' => -1, 'negatif string' => '-1', 'float' => 1500.5, 'float tam' => 150000.0, 'ondalık string' => '1500.50', 'bilimsel' => '1e5', 'TL biçimi' => '1.500', 'TL virgül' => '1500,00', 'boşluklu' => ' 150000', 'leading zero' => '0150000', 'sayısal görünümlü' => '150000abc', 'taşma' => '99999999999999999999999', 'dizi' => array( 150000 ), 'nesne' => new stdClass(), 'bool' => true ) as $mb_label => $mb_bad ) {
	$mb_r = $mb_money_sv( $mb_bad );
	mb_test( "kuruş: {$mb_label} reddedilir (dönüştürülmez)", null === $mb_r[0] && null !== $mb_r[1] );
}
mb_test( 'kuruş: kayıtlı sanitize (sanitize_for_registration) kanonik 150000\'i aynen döndürür (x100 yok)', 150000 === MaviBelge_Core_Meta_Schema::sanitize_for_registration( 150000, '_mb_certificate_print_fee_kurus', 'post', 'mb_ucret' ) );
mb_test( 'kuruş: kayıtlı sanitize TL metnini ("1.500") SAKLAMAZ, yazmayı reddeder', MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE === MaviBelge_Core_Meta_Schema::sanitize_for_registration( '1.500', '_mb_certificate_print_fee_kurus', 'post', 'mb_ucret' ) );
mb_test( 'kuruş: admin girişi "1.500" -> 150000; "1.500,00" -> 150000; "" -> 0', array( 150000, null ) === MaviBelge_Core_Field_Repository::admin_input_to_storage( $mb_cert_cfg, '1.500' ) && array( 150000, null ) === MaviBelge_Core_Field_Repository::admin_input_to_storage( $mb_cert_cfg, '1.500,00' ) && array( 0, null ) === MaviBelge_Core_Field_Repository::admin_input_to_storage( $mb_cert_cfg, '' ) );
mb_test( 'kuruş: admin girişi "abc" / dizi / "-5" reddedilir', null !== MaviBelge_Core_Field_Repository::admin_input_to_storage( $mb_cert_cfg, 'abc' )[1] && null !== MaviBelge_Core_Field_Repository::admin_input_to_storage( $mb_cert_cfg, array( '1' ) )[1] && null !== MaviBelge_Core_Field_Repository::admin_input_to_storage( $mb_cert_cfg, '-5' )[1] );
mb_test( 'kuruş: admin_input_to_storage TL dönüşümünü YALNIZ admin_input=try money_kurus alanında yapar (min alanında ham değer aynen döner)', array( '1.500', null ) === MaviBelge_Core_Field_Repository::admin_input_to_storage( $mb_min_cfg, '1.500' ) );
mb_test(
	'kuruş: gerçek belge basım ücreti (Güzellik Uzmanı, 150000) TL gösterimi -> admin girişi -> doğrulama -> kayıtlı sanitize zinciri sonunda AYNI 150000',
	( function () use ( $fx_fee_with_code, $mb_cert_cfg ) {
		$display = MaviBelge_Core_Validator::kurus_to_lira_display( $fx_fee_with_code['certificate_print_fee_kurus'] );
		list( $storage, $e1 ) = MaviBelge_Core_Field_Repository::admin_input_to_storage( $mb_cert_cfg, $display );
		list( $clean, $e2 )   = MaviBelge_Core_Field_Repository::sanitize_and_validate( $mb_cert_cfg, $storage );
		$stored               = MaviBelge_Core_Meta_Schema::sanitize_for_registration( $clean, '_mb_certificate_print_fee_kurus', 'post', 'mb_ucret' );
		return '1.500,00' === $display && null === $e1 && null === $e2 && 150000 === $stored;
	} )()
);
mb_test(
	'kuruş: fiyat seçeneklerinden türetilen min/max kanonik kuruşlarla tutarlı (manifest 1700000/1700000) ve money_kurus sanitize\'ında değişmez',
	( function () use ( $fx_fee_with_code, $mb_min_cfg ) {
		$eval = MaviBelge_Core_Validator::evaluate_price_options( $fx_fee_with_code['price_options'] );
		return $eval['replace'] && $fx_fee_with_code['min_amount_kurus'] === $eval['min_kurus'] && $fx_fee_with_code['max_amount_kurus'] === $eval['max_kurus']
			&& array( $eval['min_kurus'], null ) === MaviBelge_Core_Field_Repository::sanitize_and_validate( $mb_min_cfg, $eval['min_kurus'] )
			&& 1700000 === $eval['options'][0]['amount_kurus'];
	} )()
);
mb_test( 'kuruş: canonical_kurus PHP_INT_MAX kabul, bir büyüğü ret (float yok)', PHP_INT_MAX === MaviBelge_Core_Validator::canonical_kurus( (string) PHP_INT_MAX ) && null === MaviBelge_Core_Validator::canonical_kurus( mb_decimal_string_increment( (string) PHP_INT_MAX ) ) );
mb_test( 'ayrıştırıcı tek kaynak: repository strict_int ile Validator::parse_canonical_decimal_int aynı sonucu verir', MaviBelge_Core_Validator::parse_canonical_decimal_int( '42' ) === MaviBelge_Core_Import_WordPress_Target_Repository::strict_int( '42' ) && array( 'ok' => false ) === MaviBelge_Core_Validator::parse_canonical_decimal_int( '007' ) );

/* --- B) Marker sözleşmesi: tek kanonik sınıflandırıcı, cast yok --- */
$mb_ck = function ( $v, $t ) {
	return MaviBelge_Core_Validator::classify_import_source_key( $v, $t );
};
mb_test( 'marker: "sector:makine" -> valid', 'valid' === $mb_ck( 'sector:makine', 'sector' ) );
mb_test( 'marker: başka aile öneki -> wrong_prefix', 'wrong_prefix' === $mb_ck( 'fee:makine:3:x', 'sector' ) && 'wrong_prefix' === $mb_ck( 'qualification:10UY0002-3/03', 'fee' ) );
mb_test( 'marker: bilinmeyen önek ve baştaki boşluk -> wrong_prefix (kırpma yok)', 'wrong_prefix' === $mb_ck( 'sektor:makine', 'sector' ) && 'wrong_prefix' === $mb_ck( ' sector:makine', 'sector' ) );
mb_test( 'marker: doğru önek, biçimsiz -> malformed (büyük harf, sondaki \\n, boş gövde)', 'malformed' === $mb_ck( 'sector:Makine', 'sector' ) && 'malformed' === $mb_ck( "sector:makine\n", 'sector' ) && 'malformed' === $mb_ck( 'sector:', 'sector' ) );
mb_test( 'marker: dizi / iç içe dizi / nesne / int / bool -> not_string ("Array" metnine dönüşmez)', 'not_string' === $mb_ck( array( 'sector:makine' ), 'sector' ) && 'not_string' === $mb_ck( array( array( 'x' ) ), 'sector' ) && 'not_string' === $mb_ck( new stdClass(), 'sector' ) && 'not_string' === $mb_ck( 5, 'sector' ) && 'not_string' === $mb_ck( true, 'sector' ) );
mb_test( 'marker: "" ve null -> empty', 'empty' === $mb_ck( '', 'sector' ) && 'empty' === $mb_ck( null, 'sector' ) );
mb_test( 'marker: is_valid_import_source_key dizi girdide false (eski (string) cast "Array" yolu kapandı)', false === MaviBelge_Core_Validator::is_valid_import_source_key( array( 'sector:makine' ), 'sector' ) && false === MaviBelge_Core_Validator::is_valid_import_source_key( new stdClass(), 'sector' ) );
mb_test( 'marker: is_valid_import_source_key mevcut sözleşme korunur (string trim, boş geçerli)', true === MaviBelge_Core_Validator::is_valid_import_source_key( ' sector:makine ', 'sector' ) && true === MaviBelge_Core_Validator::is_valid_import_source_key( '', 'fee' ) );

/* --- C) Doğal anahtar preflight: saf parçalar + karar matrisi --- */
$mb_nk = 'MaviBelge_Core_Import_WordPress_Target_Repository';
mb_test( 'doğal anahtar: source_key ayrıştırma (sektör slug / MYK kodu / ücret üçlüsü)',
	array( 'slug' => 'makine' ) === $mb_nk::natural_key_from_source_key( 'sector', 'sector:makine' )
	&& array( 'code' => '10UY0002-3/03' ) === $mb_nk::natural_key_from_source_key( 'qualification', 'qualification:10UY0002-3/03' )
	&& array( 'sector_slug' => 'guzellik-sac-bakim', 'level' => '4', 'profession_slug' => 'guzellik-uzmani' ) === $mb_nk::natural_key_from_source_key( 'fee', 'fee:guzellik-sac-bakim:4:guzellik-uzmani' ) );
mb_test( 'doğal anahtar: geçersiz/yanlış aileli source_key -> null (fail-closed)', null === $mb_nk::natural_key_from_source_key( 'sector', 'fee:x:3:y' ) && null === $mb_nk::natural_key_from_source_key( 'fee', 'fee:x:9:y' ) && null === $mb_nk::natural_key_from_source_key( 'unknown', 'sector:x' ) && null === $mb_nk::natural_key_from_source_key( 'sector', array( 'sector:x' ) ) );
foreach ( array(
	'boş marker (sanitize sonucu boşalmış)' => array( '', 'unmanaged' ),
	'marker yok (null)'                    => array( null, 'unmanaged' ),
	'serileştirilmiş dizi marker'          => array( array( 'sector:makine' ), 'corrupt_marker' ),
	'nesne marker'                         => array( new stdClass(), 'corrupt_marker' ),
	'doğru önek biçimsiz'                  => array( 'sector:Makine', 'corrupt_marker' ),
	'yanlış önek'                          => array( 'fee:makine:3:x', 'wrong_marker_prefix' ),
	'aynı aile farklı key'                 => array( 'sector:metal', 'foreign_marker' ),
	'tam bu key (keşfedilmemiş)'           => array( 'sector:makine', 'undiscovered_marker' ),
) as $mb_label => $mb_case ) {
	mb_test( "doğal anahtar marker durumu: {$mb_label} -> {$mb_case[1]}", $mb_case[1] === $mb_nk::natural_key_state_from_marker( $mb_case[0], 'sector', 'sector:makine' ) );
}
mb_test( 'doğal anahtar: ücret meslek slug kuralı Faz 6A formülü (tam eşitlik; fuzzy yok)', 'kizgin-yag-operatoru' === MaviBelge_Core_Import_Record_Validator::profession_slug( 'Kızgın Yağ Operatörü' ) && 'kizgin-yag-operatoru-eski' === MaviBelge_Core_Import_Record_Validator::profession_slug( 'Kızgın Yağ Operatörü (eski)' ) && null === MaviBelge_Core_Import_Record_Validator::profession_slug( array( 'x' ) ) );

$mb_nk_norm = function ( $lookup ) {
	return MaviBelge_Core_Import_Record_Validator::normalize_target_lookup( $lookup, 'sector' );
};
mb_test( 'lookup: natural_key kapalı kümeden -> geçerli, normalize çıktısında taşınır', true === $mb_nk_norm( array( 'target_found' => false, 'natural_key' => 'unmanaged' ) )['target_state_valid'] && 'unmanaged' === $mb_nk_norm( array( 'target_found' => false, 'natural_key' => 'unmanaged' ) )['natural_key'] );
mb_test( 'lookup: natural_key verilmemiş -> null (kontrol edilmedi)', null === $mb_nk_norm( array( 'target_found' => false ) )['natural_key'] );
mb_test( 'lookup: bilinmeyen natural_key / string olmayan -> invalid_target_state', false === $mb_nk_norm( array( 'target_found' => false, 'natural_key' => 'banana' ) )['target_state_valid'] && false === $mb_nk_norm( array( 'target_found' => false, 'natural_key' => array( 'none' ) ) )['target_state_valid'] );
mb_test( 'lookup: target_found=true iken natural_key -> çelişki, invalid', false === $mb_nk_norm( array( 'target_found' => true, 'target_id' => 5, 'target_type_matches' => false, 'has_source_key_marker' => true, 'natural_key' => 'none' ) )['target_state_valid'] );
mb_test( 'lookup: duplicate_targets=true iken natural_key -> çelişki, invalid', false === $mb_nk_norm( array( 'target_found' => false, 'duplicate_targets' => true, 'natural_key' => 'none' ) )['target_state_valid'] );

$mb_nk_plan = function ( $state ) use ( $fx_sector_no_image ) {
	$lookup = array( 'target_found' => false );
	if ( null !== $state ) {
		$lookup['natural_key'] = $state;
	}
	return MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array( 'sector:is-makineleri' => $lookup ), mb_empty_dependencies() );
};
foreach ( array(
	'none'                => array( 'create', 'no_target' ),
	'unmanaged'           => array( 'conflict', 'unmanaged_natural_key' ),
	'corrupt_marker'      => array( 'conflict', 'corrupt_marker' ),
	'wrong_marker_prefix' => array( 'conflict', 'wrong_marker_prefix' ),
	'foreign_marker'      => array( 'conflict', 'foreign_marker' ),
	'undiscovered_marker' => array( 'conflict', 'undiscovered_marker' ),
	'duplicate'           => array( 'conflict_duplicate_target', 'duplicate_natural_key' ),
	'query_error'         => array( 'conflict', 'natural_key_query_error' ),
) as $mb_state => $mb_exp ) {
	$mb_e = $mb_nk_plan( $mb_state );
	mb_test( "karar matrisi: marker yok + natural_key={$mb_state} -> {$mb_exp[0]}/{$mb_exp[1]}, natural_key_check={$mb_state}", $mb_exp[0] === $mb_e['decision'] && $mb_exp[1] === $mb_e['reason'] && $mb_state === $mb_e['natural_key_check'] );
}
mb_test( 'karar matrisi: natural_key verilmemiş (eski/test adapter) -> create AMA natural_key_check=null (apply adayı değil, bkz. uygunluk testleri)', 'create' === $mb_nk_plan( null )['decision'] && null === $mb_nk_plan( null )['natural_key_check'] );
mb_test( 'karar matrisi: doğal anahtar conflict, bağımlılık blokajından ÖNCE gelir (görselli sektör, görsel çözülmemiş + unmanaged -> conflict, blocked değil)',
	'unmanaged_natural_key' === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, array( 'sector:makine' => array( 'target_found' => false, 'natural_key' => 'unmanaged' ) ), mb_empty_dependencies() )['reason'] );
mb_test( 'karar matrisi: marker doğru ve tek hedef -> mevcut üç-hash kararı sürer (unchanged), natural_key_check=null',
	( function () use ( $fx_sector_no_image ) {
		$f = MaviBelge_Core_Import_Managed_Fields::project_sector( $fx_sector_no_image, array() )['fields'];
		$e = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 9, 'duplicate_targets' => false, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => MaviBelge_Core_Import_Hash::hash( $f ), 'current_managed_fields' => $f ) ), mb_empty_dependencies() );
		return 'unchanged' === $e['decision'] && null === $e['natural_key_check'];
	} )() );
mb_test( 'karar matrisi: yanlış tür hedef -> mevcut conflict_wrong_target_type sürer', 'conflict_wrong_target_type' === MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array( 'sector:is-makineleri' => array( 'target_found' => true, 'target_id' => 9, 'duplicate_targets' => false, 'target_type_matches' => false, 'has_source_key_marker' => true, 'last_applied_hash' => null, 'current_managed_fields' => null ) ), mb_empty_dependencies() )['decision'] );

/* --- D) Atomik yazma yükü doğrulaması (yazmaz) --- */
$mb_wp_fee_fields = $mb_clean_current_fee_fields;
$mb_wp_fee_hash   = MaviBelge_Core_Import_Hash::hash( $mb_wp_fee_fields );
$mb_wp_fee_key    = $fx_fee_with_code['source_key'];
$mb_wp            = function ( $type, $fields, $key, $hash ) {
	return MaviBelge_Core_Import_Write_Payload::prepare( $type, $fields, $key, $hash );
};
$mb_wp_ok = $mb_wp( 'fee', $mb_wp_fee_fields, $mb_wp_fee_key, $mb_wp_fee_hash );
mb_test( 'yük: tamamen geçerli ücret kabul edilir', true === $mb_wp_ok['ok'] && array() === $mb_wp_ok['errors'] && is_array( $mb_wp_ok['payload'] ) );
mb_test( 'yük: ücret kuruşları kanonik (belge 150000, min/max 1700000), seviye kanonik string "4", marker+hash yükte',
	150000 === $mb_wp_ok['payload']['post_meta']['_mb_certificate_print_fee_kurus'] && 1700000 === $mb_wp_ok['payload']['post_meta']['_mb_min_amount_kurus'] && 1700000 === $mb_wp_ok['payload']['post_meta']['_mb_max_amount_kurus']
	&& '4' === $mb_wp_ok['payload']['post_meta']['_mb_level'] && $mb_wp_fee_key === $mb_wp_ok['payload']['post_meta']['_mb_import_source_key'] && $mb_wp_fee_hash === $mb_wp_ok['payload']['post_meta']['_mb_last_applied_hash'] );
mb_test( 'yük: her post_meta anahtarı mb_ucret şemasında tanımlı; system_managed yalnız marker/hash/min/max',
	( function () use ( $mb_wp_ok ) {
		$fields = MaviBelge_Core_Meta_Schema::get_fields_for( 'mb_ucret' );
		foreach ( array_keys( $mb_wp_ok['payload']['post_meta'] ) as $k ) {
			if ( ! isset( $fields[ $k ] ) ) {
				return false;
			}
			if ( ( ! empty( $fields[ $k ]['system_managed'] ) || ! empty( $fields[ $k ]['readonly'] ) ) && ! in_array( $k, MaviBelge_Core_Import_Write_Payload::ALLOWED_SYSTEM_META, true ) ) {
				return false;
			}
		}
		return true;
	} )() );
mb_test( 'yük: geçerli sektör ve yeterlilik de kabul edilir',
	( function () use ( $fx_sector_no_image, $fx_qualification ) {
		$s = MaviBelge_Core_Import_Managed_Fields::project_sector( $fx_sector_no_image, array() )['fields'];
		$q = MaviBelge_Core_Import_Managed_Fields::project_qualification( $fx_qualification, array( 'sector_term_id' => 501 ) )['fields'];
		$rs = MaviBelge_Core_Import_Write_Payload::prepare( 'sector', $s, 'sector:is-makineleri', MaviBelge_Core_Import_Hash::hash( $s ) );
		$rq = MaviBelge_Core_Import_Write_Payload::prepare( 'qualification', $q, 'qualification:10UY0002-3/03', MaviBelge_Core_Import_Hash::hash( $q ) );
		return $rs['ok'] && $rq['ok'] && array( 501 ) === $rq['payload']['terms']['mb_sektor'] && '3' === $rq['payload']['post_meta']['_mb_level'] && 'sector:is-makineleri' === $rs['payload']['term_meta']['_mb_import_source_key'];
	} )() );
$mb_wp_bad = function ( array $override, $label ) use ( $mb_wp, $mb_wp_fee_fields, $mb_wp_fee_key ) {
	$f = array_merge( $mb_wp_fee_fields, $override );
	$r = $mb_wp( 'fee', $f, $mb_wp_fee_key, MaviBelge_Core_Import_Hash::hash( $f ) );
	mb_test( "yük: tek geçersiz alan ({$label}) BÜTÜN yükü reddeder, kısmi alan dönmez", false === $r['ok'] && null === $r['payload'] && ! empty( $r['errors'] ) );
};
$mb_wp_bad( array( 'certificate_print_fee_kurus' => -1 ), 'negatif kuruş' );
$mb_wp_bad( array( 'certificate_print_fee_kurus' => '150000' ), 'kuruş string (yönetilen alan int olmalı)' );
$mb_wp_bad( array( 'level' => 9 ), 'seviye 9' );
$mb_wp_bad( array( 'source_name' => array( 'x' ) ), 'source_name dizi' );
$mb_wp_bad( array( 'price_options' => array() ), 'boş fiyat listesi' );
$mb_wp_bad( array( 'bilinmeyen_alan' => 'x' ), 'bilinmeyen alan' );
$mb_wp_bad( array( '_mb_reviewer_user_id' => 1 ), 'import tarafından yönetilmeyen/system-managed alan sızdırma denemesi' );
mb_test( 'yük: eksik alan -> red', false === $mb_wp( 'fee', array_diff_key( $mb_wp_fee_fields, array( 'source_name' => 1 ) ), $mb_wp_fee_key, $mb_wp_fee_hash )['ok'] );
foreach ( array( 'yanlış önek' => 'sector:makine', 'boş' => '', 'dizi' => array( $mb_wp_fee_key ), 'biçimsiz' => 'fee:X:4:y' ) as $mb_label => $mb_bad_key ) {
	mb_test( "yük: marker {$mb_label} -> red", false === $mb_wp( 'fee', $mb_wp_fee_fields, $mb_bad_key, $mb_wp_fee_hash )['ok'] );
}
mb_test( 'yük: hash alanlarla eşleşmiyor / büyük harf / dizi -> red', false === $mb_wp( 'fee', $mb_wp_fee_fields, $mb_wp_fee_key, str_repeat( 'a', 64 ) )['ok'] && false === $mb_wp( 'fee', $mb_wp_fee_fields, $mb_wp_fee_key, strtoupper( $mb_wp_fee_hash ) )['ok'] && false === $mb_wp( 'fee', $mb_wp_fee_fields, $mb_wp_fee_key, array( $mb_wp_fee_hash ) )['ok'] );
mb_test( 'yük: para alanı geçersizken başka hiçbir alan uygulanabilir sayılmaz (payload null, post_meta yok)',
	( function () use ( $mb_wp, $mb_wp_fee_fields, $mb_wp_fee_key ) {
		$f = array_merge( $mb_wp_fee_fields, array( 'certificate_print_fee_kurus' => 1.5 ) );
		$r = $mb_wp( 'fee', $f, $mb_wp_fee_key, 'x' );
		return false === $r['ok'] && null === $r['payload'] && ! array_key_exists( 'post_meta', $r );
	} )() );

/* --- E) Apply uygunluğu, batch atomikliği, TOCTOU, rollback --- */
$mb_ap_sector_fields = MaviBelge_Core_Import_Managed_Fields::project_sector( $fx_sector_no_image, array() )['fields'];
$mb_ap_hash          = MaviBelge_Core_Import_Hash::hash( $mb_ap_sector_fields );
$mb_ap_create        = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_no_image, array( 'sector:is-makineleri' => array( 'target_found' => false, 'natural_key' => 'none' ) ), mb_empty_dependencies() );
$mb_ap_conflict      = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $fx_sector_makine, array( 'sector:makine' => array( 'target_found' => true, 'target_id' => 7, 'duplicate_targets' => false, 'target_type_matches' => true, 'has_source_key_marker' => true, 'last_applied_hash' => null, 'current_managed_fields' => null ) ), mb_empty_dependencies() );
$mb_ap_plan          = function ( array $entries, $applicable = true, $total = null ) {
	return array( 'entries' => $entries, 'errors' => array(), 'summary' => array( 'total' => null === $total ? count( $entries ) : $total, 'applicable' => $applicable ) );
};
$mb_ap_update = array(
	'source_key' => 'sector:mobilya', 'type' => 'sector', 'decision' => 'update', 'reason' => 'safe_update', 'target_id' => 3,
	'incoming_hash' => str_repeat( 'b', 64 ), 'current_hash' => str_repeat( 'c', 64 ), 'last_applied_hash' => str_repeat( 'c', 64 ),
	'changed_fields' => array( 'description' ), 'unresolved_dependencies' => array(), 'warnings' => array(), 'natural_key_check' => null,
);
$mb_ap_noop = array_merge( $mb_ap_update, array( 'source_key' => 'sector:plastik', 'decision' => 'unchanged', 'reason' => 'hash_match', 'target_id' => 2, 'incoming_hash' => str_repeat( 'c', 64 ), 'changed_fields' => array() ) );
$mb_ap_ok = MaviBelge_Core_Import_Apply_Eligibility::evaluate_plan( $mb_ap_plan( array( $mb_ap_create, $mb_ap_update, $mb_ap_noop ) ) );
mb_test( 'uygunluk: create(natural_key=none) + güvenli update + unchanged -> uygun; yalnız create/update yazma adayı, unchanged no-op',
	true === $mb_ap_ok['eligible'] && 2 === count( $mb_ap_ok['writes'] ) && array( 'sector:plastik' ) === $mb_ap_ok['noops'] && 'create' === $mb_ap_ok['writes'][0]['decision'] && 'update' === $mb_ap_ok['writes'][1]['decision'] );
mb_test( 'uygunluk fixture\'ı: planlayıcının ürettiği create girdisi natural_key_check=none, conflict girdisi gerçekten conflict/invalid_target_state',
	'create' === $mb_ap_create['decision'] && 'none' === $mb_ap_create['natural_key_check'] && 'conflict' === $mb_ap_conflict['decision'] && 'invalid_target_state' === $mb_ap_conflict['reason'] );
$mb_ap_reject = function ( $plan, $label ) {
	$r = MaviBelge_Core_Import_Apply_Eligibility::evaluate_plan( $plan );
	mb_test( "uygunluk: {$label} -> BÜTÜN batch durur, writes/noops boş (kısmi batch yok)", false === $r['eligible'] && array() === $r['writes'] && array() === $r['noops'] && ! empty( $r['errors'] ) );
};
$mb_ap_reject( $mb_ap_plan( array( $mb_ap_create, $mb_ap_update, $mb_ap_conflict ) ), 'tek conflict kaydı (invalid_target_state) batch içinde' );
$mb_ap_reject( $mb_ap_plan( array( array_merge( $mb_ap_create, array( 'natural_key_check' => null ) ) ) ), 'doğal anahtar kanıtı olmayan create' );
$mb_ap_reject( $mb_ap_plan( array( array_merge( $mb_ap_create, array( 'natural_key_check' => 'unmanaged' ) ) ) ), 'natural_key_check=unmanaged create' );
$mb_ap_reject( $mb_ap_plan( array( $mb_ap_create ), false ), 'summary.applicable=false' );
$mb_ap_reject( $mb_ap_plan( array( $mb_ap_create ), true, 2 ), 'summary.total uyuşmazlığı' );
$mb_ap_reject( $mb_ap_plan( array( $mb_ap_create, $mb_ap_create ) ), 'batch içinde tekrar eden source_key' );
$mb_ap_reject( $mb_ap_plan( array( array_merge( $mb_ap_update, array( 'current_hash' => str_repeat( 'd', 64 ) ) ) ) ), 'update ama current!==last (elle değişiklik)' );
foreach ( array( 'blocked_dependency', 'invalid', 'conflict_duplicate_target', 'conflict_wrong_target_type' ) as $mb_d ) {
	$mb_ap_reject( $mb_ap_plan( array( $mb_ap_create, array_merge( $mb_ap_update, array( 'decision' => $mb_d ) ) ) ), "karar {$mb_d} hiçbir zaman yazılmaz" );
}
$mb_ap_reject( array( 'entries' => array( $mb_ap_create ), 'errors' => array( 'x' ), 'summary' => array( 'total' => 1, 'applicable' => true ) ), 'plan girdi hatası' );
$mb_ap_reject( 'plan değil', 'plan şekli geçersiz' );

$mb_tt_create = $mb_ap_ok['writes'][0];
$mb_tt_update = $mb_ap_ok['writes'][1];
$mb_tt        = function ( $w, $o ) {
	return MaviBelge_Core_Import_Apply_Eligibility::toctou_recheck( $w, $o )['ok'];
};
mb_test( 'TOCTOU: create — hedef hâlâ yok, doğal anahtar hâlâ none, incoming aynı -> ok', true === $mb_tt( $mb_tt_create, array( 'target_found' => false, 'natural_key' => 'none', 'incoming_hash' => $mb_tt_create['expected_incoming_hash'] ) ) );
mb_test( 'TOCTOU: create — arada doğal anahtarla kayıt belirdi -> yazma yok', false === $mb_tt( $mb_tt_create, array( 'target_found' => false, 'natural_key' => 'unmanaged', 'incoming_hash' => $mb_tt_create['expected_incoming_hash'] ) ) );
mb_test( 'TOCTOU: create — arada marker\'lı hedef belirdi -> yazma yok', false === $mb_tt( $mb_tt_create, array( 'target_found' => true, 'target_id' => 3, 'incoming_hash' => $mb_tt_create['expected_incoming_hash'] ) ) );
mb_test( 'TOCTOU: update — hedef/current/last aynı -> ok', true === $mb_tt( $mb_tt_update, array( 'target_found' => true, 'target_id' => 3, 'current_hash' => str_repeat( 'c', 64 ), 'last_applied_hash' => str_repeat( 'c', 64 ), 'incoming_hash' => str_repeat( 'b', 64 ) ) ) );
mb_test( 'TOCTOU: update — kullanıcı arada alanı değiştirdi (current farklı) -> yazma yok', false === $mb_tt( $mb_tt_update, array( 'target_found' => true, 'target_id' => 3, 'current_hash' => str_repeat( 'e', 64 ), 'last_applied_hash' => str_repeat( 'c', 64 ), 'incoming_hash' => str_repeat( 'b', 64 ) ) ) );
mb_test( 'TOCTOU: update — last_applied_hash / hedef ID / incoming değişti -> yazma yok', false === $mb_tt( $mb_tt_update, array( 'target_found' => true, 'target_id' => 3, 'current_hash' => str_repeat( 'c', 64 ), 'last_applied_hash' => str_repeat( 'f', 64 ), 'incoming_hash' => str_repeat( 'b', 64 ) ) ) && false === $mb_tt( $mb_tt_update, array( 'target_found' => true, 'target_id' => 4, 'current_hash' => str_repeat( 'c', 64 ), 'last_applied_hash' => str_repeat( 'c', 64 ), 'incoming_hash' => str_repeat( 'b', 64 ) ) ) && false === $mb_tt( $mb_tt_update, array( 'target_found' => true, 'target_id' => 3, 'current_hash' => str_repeat( 'c', 64 ), 'last_applied_hash' => str_repeat( 'c', 64 ), 'incoming_hash' => str_repeat( 'a', 64 ) ) ) );
mb_test( 'TOCTOU: gözlenen durum şekli bozuk -> yazma yok', false === $mb_tt( $mb_tt_update, 'x' ) && false === $mb_tt( $mb_tt_update, array( 'target_found' => 'true' ) ) );

$mb_rb_new = array_merge( $mb_ap_sector_fields, array( 'description' => 'Yeni açıklama' ) );
$mb_rb     = MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'update', $mb_ap_sector_fields, $mb_rb_new, $mb_ap_hash, MaviBelge_Core_Import_Hash::hash( $mb_rb_new ) );
mb_test( 'rollback: update kaydı kapalı şekil + yalnız yönetilen alanlar + changed_fields=[description]',
	is_array( $mb_rb ) && array( 'schema', 'source_key', 'type', 'decision', 'target_id', 'old_hash', 'new_hash', 'old_fields', 'new_fields', 'changed_fields', 'unmanaged_fingerprint' ) === array_keys( $mb_rb ) && null === $mb_rb['unmanaged_fingerprint'] && array( 'description' ) === $mb_rb['changed_fields'] );
mb_test( 'rollback: create kaydı old null ile geçerli', is_array( MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'create', null, $mb_ap_sector_fields, null, $mb_ap_hash, str_repeat( 'c', 64 ) ) ) && null === MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'create', null, $mb_ap_sector_fields, null, $mb_ap_hash ) );
mb_test( 'rollback: fazladan (yönetilmeyen/kişisel) alan / conflict kararı / bozuk marker / hedefsiz -> null',
	null === MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'create', null, array_merge( $mb_ap_sector_fields, array( 'email' => 'x@example.invalid' ) ), null, $mb_ap_hash )
	&& null === MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'conflict', null, $mb_ap_sector_fields, null, $mb_ap_hash )
	&& null === MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record( 'sector', 'fee:x:3:y', 4, 'create', null, $mb_ap_sector_fields, null, $mb_ap_hash )
	&& null === MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record( 'sector', 'sector:is-makineleri', 0, 'create', null, $mb_ap_sector_fields, null, $mb_ap_hash ) );
mb_test( 'rollback: yalnız hedefin şu anki hash\'i new_hash\'e eşitse izinli (kullanıcı değişikliği körlemesine ezilmez)', true === MaviBelge_Core_Import_Apply_Eligibility::rollback_allowed( $mb_rb, $mb_rb['new_hash'] ) && false === MaviBelge_Core_Import_Apply_Eligibility::rollback_allowed( $mb_rb, $mb_ap_hash ) );
/* --- Son Kabul Düzeltmesi — rollback kaydı alan–hash bütünlüğü ve kapalı şekil
 * (Codex bulgusu: ilgisiz 'aaaa…'/'bbbb…' hash'li kayıt kabul ediliyordu;
 * yalnız schema+new_hash taşıyan kayıt rollback_allowed()'dan geçiyordu). --- */
$mb_rv_old    = $mb_ap_sector_fields;
$mb_rv_new    = $mb_rb_new;
$mb_rv_oldh   = MaviBelge_Core_Import_Hash::hash( $mb_rv_old );
$mb_rv_newh   = MaviBelge_Core_Import_Hash::hash( $mb_rv_new );
$mb_rv_E      = 'MaviBelge_Core_Import_Apply_Eligibility';
$mb_rv_update = $mb_rv_E::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'update', $mb_rv_old, $mb_rv_new, $mb_rv_oldh, $mb_rv_newh );
$mb_rv_create = $mb_rv_E::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'create', null, $mb_rv_old, null, $mb_rv_oldh, str_repeat( 'c', 64 ) );
mb_test( 'rollback bütünlük: doğru update kaydı kabul (validate_rollback_record da true)', is_array( $mb_rv_update ) && true === $mb_rv_E::validate_rollback_record( $mb_rv_update ) );
mb_test( 'rollback bütünlük: doğru create kaydı kabul; changed_fields tüm allowlist (deterministik sıra)', is_array( $mb_rv_create ) && MaviBelge_Core_Import_Managed_Fields::SECTOR_FIELDS === $mb_rv_create['changed_fields'] && true === $mb_rv_E::validate_rollback_record( $mb_rv_create ) );
mb_test( 'rollback bütünlük (Codex exploit): geçerli alanlar + ilgisiz aaaa/bbbb hash -> null', null === $mb_rv_E::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'update', $mb_rv_old, $mb_rv_new, str_repeat( 'a', 64 ), str_repeat( 'b', 64 ) ) );
mb_test( 'rollback bütünlük: alanlarla ilgisiz old hash -> null', null === $mb_rv_E::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'update', $mb_rv_old, $mb_rv_new, str_repeat( 'a', 64 ), $mb_rv_newh ) );
mb_test( 'rollback bütünlük: alanlarla ilgisiz new hash -> null (create ve update)', null === $mb_rv_E::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'update', $mb_rv_old, $mb_rv_new, $mb_rv_oldh, str_repeat( 'b', 64 ) ) && null === $mb_rv_E::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'create', null, $mb_rv_old, null, str_repeat( 'b', 64 ) ) );
mb_test( 'rollback bütünlük: update\'te old===new (hash eşit) -> null', null === $mb_rv_E::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'update', $mb_rv_old, $mb_rv_old, $mb_rv_oldh, $mb_rv_oldh ) );
mb_test( 'rollback bütünlük: create\'te old_fields/old_hash dolu -> null', null === $mb_rv_E::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'create', $mb_rv_old, $mb_rv_new, $mb_rv_oldh, $mb_rv_newh ) );
$mb_rv_mut = function ( array $patch ) use ( $mb_rv_update, $mb_rv_E ) {
	return $mb_rv_E::validate_rollback_record( array_merge( $mb_rv_update, $patch ) );
};
mb_test( 'rollback kayıt: tek alan değiştiği halde yanlış changed_fields -> ret', false === $mb_rv_mut( array( 'changed_fields' => array( 'name' ) ) ) );
mb_test( 'rollback kayıt: eksik changed_fields (boş liste) -> ret', false === $mb_rv_mut( array( 'changed_fields' => array() ) ) );
mb_test( 'rollback kayıt: fazladan changed_fields -> ret', false === $mb_rv_mut( array( 'changed_fields' => array( 'description', 'name' ) ) ) );
mb_test( 'rollback kayıt: tekrar eden changed field -> ret', false === $mb_rv_mut( array( 'changed_fields' => array( 'description', 'description' ) ) ) );
mb_test( 'rollback kayıt: changed_fields string olmayan / allowlist dışı / liste olmayan -> ret', false === $mb_rv_mut( array( 'changed_fields' => array( 5 ) ) ) && false === $mb_rv_mut( array( 'changed_fields' => array( 'email' ) ) ) && false === $mb_rv_mut( array( 'changed_fields' => array( 3 => 'description' ) ) ) && false === $mb_rv_mut( array( 'changed_fields' => 'description' ) ) );
mb_test( 'rollback kayıt: fazladan anahtar (ör. kişisel veri) -> ret', false === $mb_rv_E::validate_rollback_record( array_merge( $mb_rv_update, array( 'email' => 'x@example.invalid' ) ) ) );
mb_test( 'rollback kayıt: eksik anahtar -> ret', false === $mb_rv_E::validate_rollback_record( array_diff_key( $mb_rv_update, array( 'changed_fields' => 1 ) ) ) && false === $mb_rv_E::validate_rollback_record( array_diff_key( $mb_rv_update, array( 'old_hash' => 1 ) ) ) );
mb_test( 'rollback kayıt (Codex exploit): yalnız schema + new_hash -> ret', false === $mb_rv_E::validate_rollback_record( array( 'schema' => 'mavibelge-import-rollback/2', 'new_hash' => str_repeat( 'a', 64 ) ) ) );
mb_test( 'rollback kayıt: yanlış type / source_key / decision / target_id / schema -> ret',
	false === $mb_rv_mut( array( 'type' => 'fee' ) ) && false === $mb_rv_mut( array( 'type' => 'qualification' ) )
	&& false === $mb_rv_mut( array( 'source_key' => 'fee:x:3:y' ) ) && false === $mb_rv_mut( array( 'source_key' => 'sector:' ) ) && false === $mb_rv_mut( array( 'source_key' => 5 ) ) && false === $mb_rv_mut( array( 'decision' => 'conflict' ) ) && false === $mb_rv_mut( array( 'decision' => 'unchanged' ) )
	&& false === $mb_rv_mut( array( 'target_id' => 0 ) ) && false === $mb_rv_mut( array( 'target_id' => '4' ) ) && false === $mb_rv_mut( array( 'schema' => 'mavibelge-import-rollback/1' ) ) && false === $mb_rv_mut( array( 'schema' => 'mavibelge-import-rollback/3' ) ) );
mb_test( 'rollback kayıt: alanlar değiştirilmiş ama hash\'ler eski (kurcalanmış kayıt) -> ret', false === $mb_rv_mut( array( 'new_fields' => array_merge( $mb_rv_new, array( 'name' => 'Kurcalanmış' ) ) ) ) );
mb_test( 'rollback kayıt: yönetilmeyen alan içeren new_fields -> ret', false === $mb_rv_mut( array( 'new_fields' => array_merge( $mb_rv_new, array( 'email' => 'x' ) ) ) ) );
mb_test( 'rollback kayıt: dizi olmayan kayıt -> ret', false === $mb_rv_E::validate_rollback_record( 'kayıt' ) && false === $mb_rv_E::validate_rollback_record( null ) );
mb_test( 'rollback_allowed: current hash new_hash\'e eşit olsa bile malformed kayıt uygun DEĞİL', false === $mb_rv_E::rollback_allowed( array( 'schema' => 'mavibelge-import-rollback/2', 'new_hash' => str_repeat( 'a', 64 ) ), str_repeat( 'a', 64 ) ) && false === $mb_rv_E::rollback_allowed( array_merge( $mb_rv_update, array( 'changed_fields' => array() ) ), $mb_rv_newh ) );
mb_test( 'rollback_allowed: geçerli kayıt + current hash === new_hash -> uygun', true === $mb_rv_E::rollback_allowed( $mb_rv_update, $mb_rv_newh ) && true === $mb_rv_E::rollback_allowed( $mb_rv_create, $mb_rv_oldh ) );
mb_test( 'rollback_allowed: geçerli kayıt + current hash farklı (kullanıcı değiştirmiş) -> uygun DEĞİL', false === $mb_rv_E::rollback_allowed( $mb_rv_update, $mb_rv_oldh ) && false === $mb_rv_E::rollback_allowed( $mb_rv_update, null ) );

/* --- By-Mid Kapsam Kapanışı — by-mid ret filtresi YALNIZ MaviBelge alanlarını
 * kapsar (Codex bulgusu: global filtre harici anahtarlarda da sanitize_meta()
 * çağırıyordu; çekirdek geçerli değerde tekrar çağırdığı için harici
 * sanitizer'lar iki kez çalışıyordu — {"sanitize_calls":2}). Asıl kanıt
 * write-safety-test.php (gerçek WordPress 6.9.9, sayaçlı harici sanitizer). --- */
$mb_sc_S = 'MaviBelge_Core_Meta_Schema';
mb_test( 'by-mid kapsam (post): kayıtlı MaviBelge alanları kapsamda (mb_yeterlilik/_mb_level, mb_ucret/_mb_certificate_print_fee_kurus, mb_ucret/_mb_import_source_key)',
	true === $mb_sc_S::is_managed_meta_key( 'post', 'mb_yeterlilik', '_mb_level' ) && true === $mb_sc_S::is_managed_meta_key( 'post', 'mb_ucret', '_mb_certificate_print_fee_kurus' )
	&& true === $mb_sc_S::is_managed_meta_key( 'post', 'mb_ucret', '_mb_import_source_key' ) );
mb_test( 'by-mid kapsam (post): şema dışı anahtar / MaviBelge dışı post türü / aynı ad başka türde -> kapsam DIŞI; Faz 12: çekirdek `page` YALNIZ iki sistem anahtarıyla kapsamda',
	false === $mb_sc_S::is_managed_meta_key( 'post', 'mb_yeterlilik', '_mb_test_external_meta' ) && false === $mb_sc_S::is_managed_meta_key( 'post', 'post', '_mb_level' )
	&& true === $mb_sc_S::is_managed_meta_key( 'post', 'page', '_mb_import_source_key' ) && true === $mb_sc_S::is_managed_meta_key( 'post', 'page', '_mb_last_applied_hash' )
	&& false === $mb_sc_S::is_managed_meta_key( 'post', 'page', '_mb_level' ) && false === $mb_sc_S::is_managed_meta_key( 'post', 'page', '_wp_page_template' ) && false === $mb_sc_S::is_managed_meta_key( 'post', 'mb_sss', '_mb_certificate_print_fee_kurus' ) );
mb_test( 'by-mid kapsam: her MaviBelge post türünün her şema alanı kapsamda (şemanın kendisi tek kaynak)',
	(function () use ( $mb_sc_S ) {
		$n = 0;
		foreach ( $mb_sc_S::get_schema() as $postType => $fields ) {
			foreach ( array_keys( $fields ) as $key ) {
				if ( true !== $mb_sc_S::is_managed_meta_key( 'post', $postType, $key ) || true !== $mb_sc_S::is_mavibelge_meta_key_candidate( 'post', $key ) ) {
					return false;
				}
				$n++;
			}
		}
		return $n > 0;
	})() );
mb_test( 'by-mid kapsam (term): sektör sözleşmesi TAM dört anahtar (_mb_icon_key, _mb_image_attachment_id, _mb_import_source_key, _mb_last_applied_hash), taksonomi mb_sektor',
	array( '_mb_icon_key', '_mb_image_attachment_id', '_mb_import_source_key', '_mb_last_applied_hash' ) === array_keys( MaviBelge_Core_Taxonomies::sector_term_meta_contract() )
	&& 'mb_sektor' === MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY );
mb_test( 'by-mid kapsam (term): mb_sektor + dört anahtar kapsamda',
	true === $mb_sc_S::is_managed_meta_key( 'term', 'mb_sektor', '_mb_icon_key' ) && true === $mb_sc_S::is_managed_meta_key( 'term', 'mb_sektor', '_mb_image_attachment_id' )
	&& true === $mb_sc_S::is_managed_meta_key( 'term', 'mb_sektor', '_mb_import_source_key' ) && true === $mb_sc_S::is_managed_meta_key( 'term', 'mb_sektor', '_mb_last_applied_hash' ) );
mb_test( 'by-mid kapsam (term): başka taksonomi (category, mb_haber_turu, post_tag) veya sözleşme dışı anahtar -> kapsam DIŞI',
	false === $mb_sc_S::is_managed_meta_key( 'term', 'category', '_mb_icon_key' ) && false === $mb_sc_S::is_managed_meta_key( 'term', 'mb_haber_turu', '_mb_import_source_key' )
	&& false === $mb_sc_S::is_managed_meta_key( 'term', 'post_tag', '_mb_last_applied_hash' ) && false === $mb_sc_S::is_managed_meta_key( 'term', 'mb_sektor', '_mb_test_external_term_meta' )
	&& false === $mb_sc_S::is_managed_meta_key( 'term', 'mb_sektor', '_mb_level' ) && false === $mb_sc_S::is_managed_meta_key( 'term', 'MB_SEKTOR', '_mb_icon_key' ) );
mb_test( 'by-mid kapsam: diğer meta türleri (user/comment) ve string olmayan/boş girdiler -> kapsam DIŞI',
	false === $mb_sc_S::is_managed_meta_key( 'user', 'mb_yeterlilik', '_mb_level' ) && false === $mb_sc_S::is_managed_meta_key( 'comment', 'mb_sektor', '_mb_icon_key' )
	&& false === $mb_sc_S::is_managed_meta_key( 'post', 'mb_yeterlilik', array( '_mb_level' ) ) && false === $mb_sc_S::is_managed_meta_key( 'post', 'mb_yeterlilik', '' )
	&& false === $mb_sc_S::is_managed_meta_key( 'post', '', '_mb_level' ) && false === $mb_sc_S::is_managed_meta_key( 'term', null, '_mb_icon_key' )
	&& false === $mb_sc_S::is_mavibelge_meta_key_candidate( 'user', '_mb_level' ) && false === $mb_sc_S::is_mavibelge_meta_key_candidate( 'post', 5 ) );
mb_test( 'by-mid kapsam: aday denetimi (alt türden bağımsız) — MaviBelge anahtar adları aday, harici anahtarlar değil; post/term anahtarları karışmaz',
	true === $mb_sc_S::is_mavibelge_meta_key_candidate( 'post', '_mb_level' ) && true === $mb_sc_S::is_mavibelge_meta_key_candidate( 'term', '_mb_icon_key' )
	&& false === $mb_sc_S::is_mavibelge_meta_key_candidate( 'post', '_mb_test_external_meta' ) && false === $mb_sc_S::is_mavibelge_meta_key_candidate( 'term', '_mb_test_external_term_meta' )
	&& false === $mb_sc_S::is_mavibelge_meta_key_candidate( 'term', '_mb_level' ) && false === $mb_sc_S::is_mavibelge_meta_key_candidate( 'post', '_edit_lock' ) );
// Davranış kanıtı: bu bağımsız bootstrap'te WordPress meta fonksiyonları YOK;
// callback harici anahtarda sanitize_meta()/get_metadata_by_mid() çağırsaydı
// "undefined function" ile düşerdi. null dönmesi, hiçbirine dokunmadığını gösterir.
mb_test( 'by-mid kapsam: harici açık anahtarda post/term callback\'i sanitize_meta() ÇAĞIRMADAN null döner (karar çekirdeğe kalır)',
	! function_exists( 'sanitize_meta' ) && ! function_exists( 'get_metadata_by_mid' )
	&& null === $mb_sc_S::reject_invalid_post_meta_write_by_mid( null, 12, 'second', '_mb_test_external_meta' )
	&& null === $mb_sc_S::reject_invalid_term_meta_write_by_mid( null, 12, 'second', '_mb_test_external_term_meta' )
	&& null === $mb_sc_S::reject_invalid_term_meta_write_by_mid( null, 12, 'x', '_mb_level' )
	&& null === $mb_sc_S::reject_invalid_post_meta_write_by_mid( null, 12, 'x', '' ) );
mb_test( 'by-mid kapsam: başka callback\'in önceki non-null kararı (true/false) korunur; string olmayan anahtar çekirdekteki gibi false',
	true === $mb_sc_S::reject_invalid_post_meta_write_by_mid( true, 12, '9', '_mb_level' ) && false === $mb_sc_S::reject_invalid_term_meta_write_by_mid( false, 12, 'x', '_mb_test_external_term_meta' )
	&& false === $mb_sc_S::reject_invalid_post_meta_write_by_mid( null, 12, 'x', array( '_mb_level' ) ) );




require_once __DIR__ . '/fixtures/apply-fixture.php';
require_once __DIR__ . '/support/import-apply-fakes.php';

/* ================================================================
 * Faz 6B3 — APPLY / BATCH / CHECKPOINT / AUDIT / ROLLBACK (saf PHP).
 * Sahte, bellek içi WordPress dünyası (tests/support/import-apply-fakes.php)
 * ve AÇIKÇA SAHTE fixture manifesti (tests/fixtures/apply-fixture.php).
 * Gerçek WordPress davranışı ayrıca izole WordPress 6.9.9'da doğrulanır.
 * ================================================================ */
$S6  = 'MaviBelge_Core_Import_Run_State';
$AP6 = 'MaviBelge_Core_Import_Apply_Plan';
$CD6 = 'MaviBelge_Core_Import_Rollback_Codec';
$AC6 = 'MaviBelge_Core_Import_Audit_Context';

/* --- 6B3-A) Run durum makinesi: kapalı geçişler --- */
mb_test( '6B3 durum: eski sekiz durum hâlâ tanımlı (Faz 6B4 dört bekleme durumu ekledi; bkz. faz6b4 paketi)', array() === array_diff( array( 'planned', 'running', 'failed', 'completed', 'rollback_required', 'rolling_back', 'rolled_back', 'rollback_failed' ), $S6::ALL ) && 12 === count( $S6::ALL ) );
mb_test( '6B3 durum: izinli geçişler (planned->running, running->completed/failed/rollback_required, completed/rollback_required/rollback_failed->rolling_back, rolling_back->rolled_back/rollback_failed)',
	$S6::can_transition( 'planned', 'running' ) && $S6::can_transition( 'planned', 'failed' ) && $S6::can_transition( 'running', 'completed' ) && $S6::can_transition( 'running', 'failed' )
	&& $S6::can_transition( 'running', 'rollback_required' ) && $S6::can_transition( 'completed', 'rolling_back' ) && $S6::can_transition( 'rollback_required', 'rolling_back' )
	&& $S6::can_transition( 'rollback_failed', 'rolling_back' ) && $S6::can_transition( 'rolling_back', 'rolled_back' ) && $S6::can_transition( 'rolling_back', 'rollback_failed' ) );
mb_test( '6B3 durum: geçersiz geçişler reddedilir (terminal durumdan çıkış, atlama, geri dönüş, bilinmeyen/tip hatası)',
	! $S6::can_transition( 'failed', 'running' ) && ! $S6::can_transition( 'rolled_back', 'rolling_back' ) && ! $S6::can_transition( 'planned', 'completed' )
	&& ! $S6::can_transition( 'completed', 'running' ) && ! $S6::can_transition( 'running', 'rolled_back' ) && ! $S6::can_transition( 'completed', 'rolled_back' )
	&& ! $S6::can_transition( 'running', 'running' ) && ! $S6::can_transition( 'bogus', 'running' ) && ! $S6::can_transition( 'planned', 'RUNNING' ) && ! $S6::can_transition( null, 'running' ) );

/* --- 6B3-B) Stage, batch boyutu, digest --- */
mb_test( '6B3 plan: stage kümesi kapalı (sectors, qualifications, all); bağımlılık kapalı önek',
	array( 'sectors', 'qualifications', 'all' ) === $AP6::STAGES && array( 'sector' ) === $AP6::STAGE_TYPES['sectors'] && array( 'sector', 'qualification' ) === $AP6::STAGE_TYPES['qualifications'] && array( 'sector', 'qualification', 'fee' ) === $AP6::STAGE_TYPES['all'] );
$m6_manifest = array( 'sectors' => array( 1 ), 'qualifications' => array( 2 ), 'fees' => array( 3 ) );
mb_test( '6B3 plan: filter_manifest sonraki türleri boşaltır, önceki türleri korur; bilinmeyen stage/şekil null',
	array( 'sectors' => array( 1 ), 'qualifications' => array(), 'fees' => array() ) === $AP6::filter_manifest( $m6_manifest, 'sectors' )
	&& array( 'sectors' => array( 1 ), 'qualifications' => array( 2 ), 'fees' => array() ) === $AP6::filter_manifest( $m6_manifest, 'qualifications' )
	&& $m6_manifest === $AP6::filter_manifest( $m6_manifest, 'all' ) && null === $AP6::filter_manifest( $m6_manifest, 'fees' ) && null === $AP6::filter_manifest( 'x', 'all' ) && null === $AP6::filter_manifest( array( 'sectors' => array() ), 'all' ) );
mb_test( '6B3 plan: batch boyutu tek merkezi sabitten, 1..20 sınırlı; varsayılan 20; kanonik olmayan girdi reddedilir',
	20 === $AP6::DEFAULT_BATCH_SIZE && 20 === $AP6::MAX_BATCH_SIZE && 20 === $AP6::normalize_batch_size( null ) && 1 === $AP6::normalize_batch_size( 1 ) && 7 === $AP6::normalize_batch_size( '7' )
	&& null === $AP6::normalize_batch_size( 0 ) && null === $AP6::normalize_batch_size( 21 ) && null === $AP6::normalize_batch_size( '07' ) && null === $AP6::normalize_batch_size( '2.0' ) && null === $AP6::normalize_batch_size( array( 2 ) ) && null === $AP6::normalize_batch_size( -1 ) );
mb_test( '6B3 plan: yazma sırası sektör -> yeterlilik -> ücret (kararlı), batch\'lere bölünür',
	array( 's1', 's2', 'q1', 'f1', 'f2' ) === array_map(
		function ( $w ) {
			return $w['source_key'];
		},
		$AP6::order_writes( array( array( 'source_key' => 'f1', 'type' => 'fee' ), array( 'source_key' => 's1', 'type' => 'sector' ), array( 'source_key' => 'q1', 'type' => 'qualification' ), array( 'source_key' => 'f2', 'type' => 'fee' ), array( 'source_key' => 's2', 'type' => 'sector' ) ) )
	) && 3 === count( $AP6::batches( array( 1, 2, 3, 4, 5 ), 2 ) ) && array( array( 5 ) ) === array_slice( $AP6::batches( array( 1, 2, 3, 4, 5 ), 2 ), 2 ) );
$m6_plan_a = array( 'entries' => array( array( 'source_key' => 'sector:a', 'type' => 'sector', 'decision' => 'create', 'reason' => 'no_target', 'target_id' => null, 'incoming_hash' => str_repeat( 'a', 64 ), 'current_hash' => null, 'last_applied_hash' => null, 'natural_key_check' => 'none', 'changed_fields' => array(), 'unresolved_dependencies' => array(), 'warnings' => array(), 'message' => 'x' ) ), 'summary' => array( 'total' => 1, 'applicable' => true ), 'errors' => array() );
$m6_plan_b = $m6_plan_a;
$m6_plan_b['entries'][0]['incoming_hash'] = str_repeat( 'b', 64 );
$m6_d1 = $AP6::plan_digest( 'sectors', str_repeat( 'c', 64 ), $m6_plan_a );
mb_test( '6B3 plan: plan_digest deterministik 64-hex; plan, stage veya manifest değişirse değişir; mesaj metni digest\'e girmez',
	is_string( $m6_d1 ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', $m6_d1 ) && $m6_d1 === $AP6::plan_digest( 'sectors', str_repeat( 'c', 64 ), $m6_plan_a )
	&& $m6_d1 !== $AP6::plan_digest( 'sectors', str_repeat( 'c', 64 ), $m6_plan_b ) && $m6_d1 !== $AP6::plan_digest( 'all', str_repeat( 'c', 64 ), $m6_plan_a )
	&& $m6_d1 !== $AP6::plan_digest( 'sectors', str_repeat( 'd', 64 ), $m6_plan_a )
	&& $m6_d1 === $AP6::plan_digest( 'sectors', str_repeat( 'c', 64 ), array_replace_recursive( $m6_plan_a, array( 'entries' => array( array( 'message' => 'başka metin' ) ) ) ) )
	&& null === $AP6::plan_digest( 'sectors', 'kısa', $m6_plan_a ) && null === $AP6::plan_digest( 'sectors', str_repeat( 'c', 64 ), 'plan değil' ) );

mb_test( '6B3 plan: rollback sırası ücret -> yeterlilik -> sektör; tür içinde ters seq',
	array( 'f2', 'f1', 'q1', 's2', 's1' ) === array_map(
		function ( $i ) {
			return $i['source_key'];
		},
		$AP6::rollback_order( array( array( 'source_key' => 's1', 'type' => 'sector', 'seq' => 1 ), array( 'source_key' => 's2', 'type' => 'sector', 'seq' => 2 ), array( 'source_key' => 'q1', 'type' => 'qualification', 'seq' => 3 ), array( 'source_key' => 'f1', 'type' => 'fee', 'seq' => 4 ), array( 'source_key' => 'f2', 'type' => 'fee', 'seq' => 5 ) ) )
	) );

/* --- 6B3-C) Rollback kodeki: JSON bozulması fail-closed --- */
$m6_rb_old = $mb_ap_sector_fields;
$m6_rb_new = array_merge( $mb_ap_sector_fields, array( 'description' => 'Yeni' ) );
$m6_rb     = MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record( 'sector', 'sector:is-makineleri', 4, 'update', $m6_rb_old, $m6_rb_new, MaviBelge_Core_Import_Hash::hash( $m6_rb_old ), MaviBelge_Core_Import_Hash::hash( $m6_rb_new ) );
$m6_json   = $CD6::encode( $m6_rb );
mb_test( '6B3 kodek: geçerli kayıt JSON\'a kodlanır ve BİREBİR geri çözülür (round-trip)', is_string( $m6_json ) && $m6_rb === $CD6::decode( $m6_json ) );
mb_test( '6B3 kodek: geçersiz kayıt kodlanmaz (encode null)', null === $CD6::encode( array( 'schema' => 'mavibelge-import-rollback/2', 'new_hash' => str_repeat( 'a', 64 ) ) ) && null === $CD6::encode( 'x' ) );
$m6_mut = function ( array $patch, array $unset = array() ) use ( $m6_rb, $CD6 ) {
	$r = array_merge( $m6_rb, $patch );
	foreach ( $unset as $k ) {
		unset( $r[ $k ] );
	}
	return $CD6::decode( json_encode( $r ) );
};
mb_test( '6B3 kodek: bozuk JSON / JSON olmayan / dizi olmayan kök / boş string -> null',
	null === $CD6::decode( '{bozuk' ) && null === $CD6::decode( '"string"' ) && null === $CD6::decode( '' ) && null === $CD6::decode( null ) && null === $CD6::decode( array() ) && null === $CD6::decode( substr( $m6_json, 0, -3 ) ) );
mb_test( '6B3 kodek: eksik/fazla anahtar -> null', null === $m6_mut( array(), array( 'changed_fields' ) ) && null === $m6_mut( array( 'email' => 'x@example.invalid' ) ) );
mb_test( '6B3 kodek: hash-alan uyumsuzluğu -> null (new_fields değişmiş ama hash eski)', null === $m6_mut( array( 'new_fields' => array_merge( $m6_rb_new, array( 'name' => 'Kurcalanmış' ) ) ) ) && null === $m6_mut( array( 'old_hash' => str_repeat( 'a', 64 ) ) ) );
mb_test( '6B3 kodek: changed_fields uyumsuzluğu -> null', null === $m6_mut( array( 'changed_fields' => array( 'name' ) ) ) && null === $m6_mut( array( 'changed_fields' => array() ) ) );

/* --- 6B3-D) Audit context: kapalı izin listesi --- */
$m6_ctx = $AC6::build( array( 'run_id' => str_repeat( 'a', 32 ), 'batch_no' => 1, 'checkpoint' => 1, 'items' => array( array( 'source_key' => 'sector:a', 'type' => 'sector', 'decision' => 'create', 'target_id' => 5, 'old_hash' => null, 'new_hash' => str_repeat( 'b', 64 ), 'changed_fields' => array( 'name' ) ) ) ) );
mb_test( '6B3 audit: izinli context (run_id, batch_no, checkpoint, items[source_key,type,decision,target_id,old/new hash,changed_fields]) kabul', is_array( $m6_ctx ) && 1 === $m6_ctx['batch_no'] && 'sector:a' === $m6_ctx['items'][0]['source_key'] );
mb_test( '6B3 audit: alan İÇERİĞİ, kişisel veri, nonce, bilinmeyen anahtar, bozuk hash, uzun hata metni -> null (yazılmaz)',
	null === $AC6::build( array( 'run_id' => str_repeat( 'a', 32 ), 'fields' => array( 'name' => 'x' ) ) ) && null === $AC6::build( array( 'email' => 'x@example.invalid' ) )
	&& null === $AC6::build( array( 'nonce' => 'abc' ) ) && null === $AC6::build( array( 'new_hash' => 'ZZ' ) ) && null === $AC6::build( array( 'error_code' => 'Hata: parola=123' ) )
	&& null === $AC6::build( array( 'items' => array( array( 'source_key' => 'sector:a', 'new_fields' => array() ) ) ) ) && null === $AC6::build( array( 'changed_fields' => array( 'post_content' ) ) )
	&& null === $AC6::build( array( 'run_id' => 'tahmin-edilebilir' ) ) );
mb_test( '6B3 audit: yedi import olay sabiti tanımlı', class_exists( 'MaviBelge_Core_Audit_Log' ) && 'import_run_started' === MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_STARTED && 'import_batch_committed' === MaviBelge_Core_Audit_Log::EVENT_IMPORT_BATCH_COMMITTED
	&& 'import_run_completed' === MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_COMPLETED && 'import_run_failed' === MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED && 'import_rollback_started' === MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_STARTED
	&& 'import_rollback_completed' === MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_COMPLETED && 'import_rollback_failed' === MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_FAILED );

/* --- 6B3-E) Apply için projeksiyon: planlayıcıyla TEK kaynak --- */
$m6_proj = MaviBelge_Core_Import_Dry_Run_Planner::project_for_apply( 'sector', $fx_sector_no_image, mb_empty_dependencies() );
mb_test( '6B3 projeksiyon: project_for_apply planlayıcının incoming_hash\'iyle AYNI alanları üretir', true === $m6_proj['ok'] && MaviBelge_Core_Import_Hash::hash( $m6_proj['fields'] ) === $mb_ap_create['incoming_hash'] );
mb_test( '6B3 projeksiyon: bağımlılığı çözülmemiş (görselli sektör / yeterlilik) veya geçersiz kayıt -> ok=false, alan yok',
	false === MaviBelge_Core_Import_Dry_Run_Planner::project_for_apply( 'sector', $fx_sector_makine, mb_empty_dependencies() )['ok'] && null === MaviBelge_Core_Import_Dry_Run_Planner::project_for_apply( 'sector', $fx_sector_makine, mb_empty_dependencies() )['fields']
	&& false === MaviBelge_Core_Import_Dry_Run_Planner::project_for_apply( 'qualification', $fx_qualification, mb_empty_dependencies() )['ok']
	&& false === MaviBelge_Core_Import_Dry_Run_Planner::project_for_apply( 'sector', array_merge( $fx_sector_no_image, array( 'name' => '' ) ), mb_empty_dependencies() )['ok']
	&& false === MaviBelge_Core_Import_Dry_Run_Planner::project_for_apply( 'bogus', $fx_sector_no_image, mb_empty_dependencies() )['ok'] );

/* --- 6B3-F) Apply servisi — sahte dünya ile uçtan uca --- */
$m6_dir = mb6b2_temp_dir( 'apply6b3' );
mb_apply_fixture_write_dir( $m6_dir, mb_apply_fixture_envelopes() );
$m6_all_sectors = function ( $env ) {
	return count( $env->world->terms );
};
$m6_posts = function ( $env, $type, $status = null ) {
	$n = 0;
	foreach ( $env->world->posts as $p ) {
		if ( $p['post_type'] === $type && ( null === $status || $p['status'] === $status ) ) {
			$n++;
		}
	}
	return $n;
};
$m6_events = function ( $env ) {
	return array_map(
		function ( $a ) {
			return $a['event'];
		},
		$env->world->audit
	);
};
$m6_apply_stage = function ( $env, $stage, $batchSize = null ) {
	$p = $env->apply->preview( $stage );
	return $env->apply->apply( $stage, $p['plan_digest'], $batchSize, 1 );
};

$E1   = mb_fake_apply_env( $m6_dir );
$E1p  = $E1->apply->preview( 'sectors' );
mb_test( '6B3 apply: temiz sahte dünyada sectors stage önizlemesi uygulanabilir (3 create, digest 64-hex, eligible)', true === $E1p['ok'] && true === $E1p['eligible'] && 3 === $E1p['writes'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $E1p['plan_digest'] ) && array() === $E1->world->writeLog );
$E1r  = $E1->apply->apply( 'sectors', $E1p['plan_digest'], null, 1 );
mb_test( '6B3 apply: başarılı apply -> completed, 3 terim, marker+hash yazıldı, run satırı completed, 3 rollback item',
	true === $E1r['ok'] && 'completed' === $E1r['status'] && 3 === $m6_all_sectors( $E1 ) && 3 === $E1r['committed_items'] && 1 === $E1r['committed_batches']
	&& 'completed' === $E1->store->get_run( $E1r['run_uid'] )['status'] && 3 === count( $E1->world->items ) );
mb_test( '6B3 apply: audit olayları sırası run_started -> batch_committed -> run_completed', array( 'import_run_started', 'import_batch_committed', 'import_run_completed' ) === $m6_events( $E1 ) );
mb_test( '6B3 apply: plan yeniden -> tüm sektörler unchanged (readback tek karar motoruyla)',
	0 === $E1->apply->preview( 'sectors' )['writes'] && 3 === $E1->apply->preview( 'sectors' )['summary']['operations']['unchanged'] );
$E1n = $m6_apply_stage( $E1, 'sectors' );
mb_test( '6B3 apply idempotent: ikinci apply -> noop; YENİ run, item, audit veya yazma YOK', true === $E1n['ok'] && 'noop' === $E1n['status'] && 1 === count( $E1->world->runs ) && 3 === count( $E1->world->items ) && 3 === count( $E1->world->audit ) && 3 === count( $E1->world->writeLog ) );
$E1q = $m6_apply_stage( $E1, 'qualifications' );
$E1f = $m6_apply_stage( $E1, 'all', 2 );
mb_test( '6B3 apply: qualifications sonra all (batch=2) -> 3 yeterlilik, 5 ücret; ücret run\'ı 3 batch',
	'completed' === $E1q['status'] && 'completed' === $E1f['status'] && 3 === $m6_posts( $E1, 'mb_yeterlilik' ) && 5 === $m6_posts( $E1, 'mb_ucret' ) && 3 === $E1f['committed_batches'] && 5 === $E1f['committed_items'] );
$m6_fee1 = null;
foreach ( $E1->world->posts as $p ) {
	if ( 'mb_ucret' === $p['post_type'] && 'fee:zz-test-a:3:test-meslek-bir' === $p['meta']['_mb_import_source_key'] ) {
		$m6_fee1 = $p;
	}
}
mb_test( '6B3 apply: kuruş x100 YOK (belge ücreti 150000, min/max 1000000 saklandı); kodsuz ücret _mb_qualification_id=0',
	null !== $m6_fee1 && '150000' === $m6_fee1['meta']['_mb_certificate_print_fee_kurus'] && '1000000' === $m6_fee1['meta']['_mb_min_amount_kurus'] && '1000000' === $m6_fee1['meta']['_mb_max_amount_kurus']
	&& (function () use ( $E1 ) {
		foreach ( $E1->world->posts as $p ) {
			if ( 'mb_ucret' === $p['post_type'] && '' === $p['meta']['_mb_qualification_code'] && '0' !== $p['meta']['_mb_qualification_id'] ) {
				return false;
			}
		}
		return true;
	})() );
mb_test( '6B3 apply: tam katalog planı artık tamamen unchanged (applicable, 0 yazma)', 0 === $E1->apply->preview( 'all' )['writes'] && true === $E1->apply->preview( 'all' )['summary']['applicable'] );

// Reddedilen apply'lar: HİÇBİR yazma, run, item veya audit.
$m6_untouched = function ( $env ) {
	return array() === $env->world->writeLog && array() === $env->world->runs && array() === $env->world->audit && array() === $env->world->items;
};
$E2 = mb_fake_apply_env( $m6_dir );
$E2r = $E2->apply->apply( 'sectors', str_repeat( 'f', 64 ), null, 1 );
mb_test( '6B3 apply red: onay digest\'i planla eşleşmiyor -> confirmation_mismatch, dünya dokunulmadı', false === $E2r['ok'] && 'confirmation_mismatch' === $E2r['error_code'] && $m6_untouched( $E2 ) );
mb_test( '6B3 apply red: onay yok/biçimsiz, bilinmeyen stage, geçersiz batch boyutu -> reddedilir, dokunulmadı',
	'invalid_confirmation' === $E2->apply->apply( 'sectors', null, null, 1 )['error_code'] && 'invalid_confirmation' === $E2->apply->apply( 'sectors', 'abc', null, 1 )['error_code']
	&& 'invalid_stage' === $E2->apply->apply( 'fees', str_repeat( 'a', 64 ), null, 1 )['error_code'] && 'invalid_batch_size' === $E2->apply->apply( 'sectors', str_repeat( 'a', 64 ), 50, 1 )['error_code'] && $m6_untouched( $E2 ) );
$E2q = $E2->apply->preview( 'qualifications' );
mb_test( '6B3 apply red: sektörler yokken qualifications stage -> blocked_dependency, applicable=false, apply reddedilir',
	false === $E2q['eligible'] && 3 === $E2q['summary']['operations']['blocked'] && 'plan_not_applicable' === $E2->apply->apply( 'qualifications', $E2q['plan_digest'], null, 1 )['error_code'] && $m6_untouched( $E2 ) );
$E3 = mb_fake_apply_env( $m6_dir );
$E3->world->terms[5] = array( 'taxonomy' => 'mb_sektor', 'slug' => 'zz-test-b', 'name' => 'Elle oluşturulmuş', 'description' => '', 'parent' => 0, 'meta' => array() );
$E3p = $E3->apply->preview( 'sectors' );
$E3r = $E3->apply->apply( 'sectors', $E3p['plan_digest'], null, 1 );
mb_test( '6B3 apply red: tek conflict (yönetilmeyen doğal anahtar) -> BÜTÜN run reddedilir; diğer iki create de yazılmaz',
	false === $E3p['eligible'] && 'plan_not_applicable' === $E3r['error_code'] && array() === $E3->world->writeLog && 1 === count( $E3->world->terms ) && array() === $E3->world->runs );
$m6_dir_inv = mb6b2_temp_dir( 'apply6b3inv' );
mb_apply_fixture_write_dir( $m6_dir_inv, mb_apply_fixture_envelopes( array( 'sector' => array( 'sector:zz-test-c' => array( 'name' => '' ) ) ) ) );
$E4 = mb_fake_apply_env( $m6_dir_inv );
$E4p = $E4->apply->preview( 'sectors' );
mb_test( '6B3 apply red: tek invalid kayıt -> bütün run reddedilir', false === $E4p['eligible'] && 1 === $E4p['summary']['operations']['invalid'] && 'plan_not_applicable' === $E4->apply->apply( 'sectors', $E4p['plan_digest'], null, 1 )['error_code'] && $m6_untouched( $E4 ) );
$m6_dir_dup = mb6b2_temp_dir( 'apply6b3dup' );
$m6_env_dup = mb_apply_fixture_envelopes();
$m6_env_dup['sector']['records'][2] = array_merge( $m6_env_dup['sector']['records'][0], array( 'source_index' => 2 ) );
mb_apply_fixture_write_dir( $m6_dir_dup, $m6_env_dup );
$E5 = mb_fake_apply_env( $m6_dir_dup );
$E5p = $E5->apply->preview( 'sectors' );
mb_test( '6B3 apply red: tekrar eden source_key -> plan hatası, apply reddedilir', false === $E5p['eligible'] && ! empty( $E5p['errors'] ) && 'plan_not_applicable' === $E5->apply->apply( 'sectors', (string) $E5p['plan_digest'], null, 1 )['error_code'] && $m6_untouched( $E5 ) );

/** Doğal anahtar kanıtı TAŞIMAYAN (natural_key bilgisini atan) repository sarmalayıcısı. */
class MB_Fake_No_Natural_Key_Repository extends MB_Fake_World_Repository {
	public function find_target_by_source_key( $type, $sourceKey ) {
		$r = parent::find_target_by_source_key( $type, $sourceKey );
		unset( $r['natural_key'] );
		return $r;
	}
}
$E6 = mb_fake_apply_env( $m6_dir );
$E6repo = new MB_Fake_No_Natural_Key_Repository( $E6->world );
$E6svc  = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $E6repo, $m6_dir ), $E6->writer, $E6->tx, $E6->store, $E6->audit );
$E6p    = $E6svc->preview( 'sectors' );
mb_test( '6B3 apply red: doğal anahtar kanıtı olmayan create -> uygun değil, reddedilir', false === $E6p['eligible'] && 'plan_not_applicable' === $E6svc->apply( 'sectors', $E6p['plan_digest'], null, 1 )['error_code'] && $m6_untouched( $E6 ) );

$E7 = mb_fake_apply_env( $m6_dir );
$E7->tx->preflightOk = false;
$E7p = $E7->apply->preview( 'sectors' );
mb_test( '6B3 apply red: transaction ön kontrolü başarısız (transactional olmayan tablo) -> infrastructure_unavailable, run yok', 'infrastructure_unavailable' === $E7->apply->apply( 'sectors', $E7p['plan_digest'], null, 1 )['error_code'] && $m6_untouched( $E7 ) );
$E7->tx->preflightOk = true;
$E7->audit->readyFlag = false;
mb_test( '6B3 apply red: audit hedefi hazır değil -> infrastructure_unavailable, run yok', 'infrastructure_unavailable' === $E7->apply->apply( 'sectors', $E7p['plan_digest'], null, 1 )['error_code'] && $m6_untouched( $E7 ) );
$E7->audit->readyFlag = true;
$E7->store->locked = true;
mb_test( '6B3 apply red: başka apply/rollback kilidi tutuyor -> locked, run yok', 'locked' === $E7->apply->apply( 'sectors', $E7p['plan_digest'], null, 1 )['error_code'] && $m6_untouched( $E7 ) );
$E7->store->locked = false;
$E7->audit->failEvents = array( 'import_run_started' );
$E7r = $E7->apply->apply( 'sectors', $E7p['plan_digest'], null, 1 );
mb_test( '6B3 apply: run_started audit yazılamazsa run failed(run_start_failed) — planned/running KALMAZ, run_started audit\'i YOK, HİÇBİR içerik yazılmaz',
	false === $E7r['ok'] && 'run_start_failed' === $E7r['error_code'] && 'failed' === $E7r['status'] && 'failed' === $E7->store->get_run( $E7r['run_uid'] )['status'] && array() === $E7->world->writeLog && array() === $E7->world->terms
	&& array( 'import_run_failed' ) === $m6_events( $E7 ) );
mb_test( '6B3 apply: kilit her sonuçta serbest bırakılır', false === $E7->store->locked );

/* TOCTOU: plan sonrası, yazmadan hemen önce bir hedef belirir. */
class MB_Fake_Drift_Repository extends MB_Fake_World_Repository {
	public $calls = array();
	public $onCall = array();
	private $wref;
	public function __construct( MB_Fake_World $w ) {
		parent::__construct( $w );
		$this->wref = $w;
	}
	public function find_target_by_source_key( $type, $sourceKey ) {
		$this->calls[ $sourceKey ] = isset( $this->calls[ $sourceKey ] ) ? $this->calls[ $sourceKey ] + 1 : 1;
		if ( isset( $this->onCall[ $sourceKey ] ) && $this->onCall[ $sourceKey ][0] === $this->calls[ $sourceKey ] ) {
			call_user_func( $this->onCall[ $sourceKey ][1], $this->wref );
		}
		return parent::find_target_by_source_key( $type, $sourceKey );
	}
}
$m6_drift_env = function ( $dir ) {
	$env         = mb_fake_apply_env( $dir );
	$env->repo   = new MB_Fake_Drift_Repository( $env->world );
	$env->dryRun = new MaviBelge_Core_Import_Dry_Run_Service( $env->repo, $dir );
	$env->apply  = new MaviBelge_Core_Import_Apply_Service( $env->dryRun, $env->writer, $env->tx, $env->store, $env->audit );
	$env->rollback = new MaviBelge_Core_Import_Rollback_Service( $env->repo, $env->writer, $env->tx, $env->store, $env->audit );
	return $env;
};
$E8 = $m6_drift_env( $m6_dir );
$E8p = $E8->apply->preview( 'sectors' );
$E8->repo->calls = array();
$E8->repo->onCall['sector:zz-test-c'] = array(
	2,
	function ( $w ) {
		$w->terms[900] = array( 'taxonomy' => 'mb_sektor', 'slug' => 'zz-test-c', 'name' => 'Arada eklendi', 'description' => '', 'parent' => 0, 'meta' => array(), 'external' => true );
	},
);
$E8r = $E8->apply->apply( 'sectors', $E8p['plan_digest'], null, 1 );
mb_test( '6B3 TOCTOU: plan sonrası beliren doğal anahtar yazmayı engeller; batch tamamen geri alınır (a,b dahil), run failed(toctou_drift)',
	false === $E8r['ok'] && 'toctou_drift' === $E8r['error_code'] && 'failed' === $E8->store->get_run( $E8r['run_uid'] )['status']
	&& array( 900 ) === array_keys( $E8->world->terms ) && array() === $E8->world->items && in_array( 'rollback', $E8->tx->log, true ) );
mb_test( '6B3 TOCTOU: başarısız batch\'in audit\'i yok; run_failed ayrı ve batch sonrası yazıldı', array( 'import_run_started', 'import_run_failed' ) === $m6_events( $E8 ) && 'toctou_drift' === $E8->world->audit[1]['context']['error_code'] );

/* Batch ortasında hata enjeksiyonu. */
$m6_full = function ( $dir ) use ( $m6_apply_stage ) {
	$env = mb_fake_apply_env( $dir );
	$m6_apply_stage( $env, 'sectors' );
	$m6_apply_stage( $env, 'qualifications' );
	return $env;
};
$E9 = $m6_full( $m6_dir );
$E9->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'fee:zz-test-c:5:test-meslek-uc' ) );
$E9r = $m6_apply_stage( $E9, 'all', 2 );
$E9run = $E9->store->get_run( $E9r['run_uid'] );
mb_test( '6B3 batch hata: 2. batch ortasında yazma hatası -> 2. batch TAMAMEN geri alındı, 1. batch korunur, run rollback_required',
	false === $E9r['ok'] && 'write_failed' === $E9r['error_code'] && 'rollback_required' === $E9run['status'] && 1 === $E9run['committed_batches'] && 2 === $E9run['committed_items'] && 2 === $m6_posts( $E9, 'mb_ucret' ) );
mb_test( '6B3 batch hata: yalnız commit edilen 2 item\'ın rollback kaydı var; yarım item/meta yok',
	2 === count( $E9->store->get_items( $E9run['id'] ) ) && (function () use ( $E9 ) {
		foreach ( $E9->world->posts as $p ) {
			if ( 'mb_ucret' === $p['post_type'] && in_array( $p['meta']['_mb_import_source_key'], array( 'fee:zz-test-c:5:test-meslek-uc', 'fee:zz-test-a:2:test-meslek-dort' ), true ) ) {
				return false;
			}
		}
		return true;
	})() );
mb_test( '6B3 batch hata: audit = run_started, batch_committed(1), run_failed(batch_no=2, write_failed)',
	array( 'import_run_started', 'import_batch_committed', 'import_run_failed' ) === array_slice( $m6_events( $E9 ), -3 ) && 2 === end( $E9->world->audit )['context']['batch_no'] && 'write_failed' === end( $E9->world->audit )['context']['error_code'] );
$E9b = $m6_apply_stage( $E9, 'all', 2 );
mb_test( '6B3 batch hata: çözülmemiş rollback_required run varken yeni apply reddedilir', 'unresolved_run_exists' === $E9b['error_code'] && 2 === $m6_posts( $E9, 'mb_ucret' ) );

$E10 = $m6_full( $m6_dir );
$E10->world->faults = array( array( 'op' => 'corrupt_meta', 'source_key' => 'fee:zz-test-a:3:test-meslek-bir' ) );
$E10r = $m6_apply_stage( $E10, 'all' );
mb_test( '6B3 readback: saklanan değer beklenenle eşleşmezse batch geri alınır (readback_mismatch), run failed, ücret yok',
	'readback_mismatch' === $E10r['error_code'] && 'failed' === $E10->store->get_run( $E10r['run_uid'] )['status'] && 0 === $m6_posts( $E10, 'mb_ucret' ) );
$E11 = $m6_full( $m6_dir );
$E11->audit->failEvents = array( 'import_batch_committed' );
$E11r = $m6_apply_stage( $E11, 'all' );
mb_test( '6B3 audit: batch_committed yazılamazsa batch commit edilmez (geri alınır), run failed(audit_failed)', 'audit_failed' === $E11r['error_code'] && 0 === $m6_posts( $E11, 'mb_ucret' ) && 'failed' === $E11->store->get_run( $E11r['run_uid'] )['status'] );
$E12 = $m6_full( $m6_dir );
$E12->tx->fail_commit_in( 1 ); // 1. commit run_started içindir; batch commit'i başarısız olur.
$E12r = $m6_apply_stage( $E12, 'all' );
mb_test( '6B3 transaction: COMMIT başarısız -> ROLLBACK çağrılır, run failed(commit_failed), ücret yok', 'commit_failed' === $E12r['error_code'] && 0 === $m6_posts( $E12, 'mb_ucret' ) );
$E13 = $m6_full( $m6_dir );
$E13->tx->fail_begin_in( 1 ); // 1. begin run_started içindir; batch begin'i başarısız olur.
$E13r = $m6_apply_stage( $E13, 'all' );
mb_test( '6B3 transaction: START TRANSACTION başarısız -> hiçbir yazma, run failed(transaction_begin_failed)', 'transaction_begin_failed' === $E13r['error_code'] && 0 === $m6_posts( $E13, 'mb_ucret' ) );
$E14 = $m6_full( $m6_dir );
$E14->world->runs[99] = array_merge( reset( $E14->world->runs ), array( 'id' => 99, 'uid' => str_repeat( '9', 32 ), 'status' => 'running', 'committed_items' => 0 ) );
$E14r = $m6_apply_stage( $E14, 'all' );
mb_test( '6B3 checkpoint: kilit alındığında "running" kalmış eski run bayat sayılır -> failed(stale_run); yeni apply devam eder',
	'failed' === $E14->world->runs[99]['status'] && 'stale_run' === $E14->world->runs[99]['error_code'] && 'completed' === $E14r['status'] );

/* Update + unmanaged alan koruması. */
$m6_dir_v2 = mb6b2_temp_dir( 'apply6b3v2' );
$m6_v2 = mb_apply_fixture_envelopes(
	array(
		'fee'    => array( 'fee:zz-test-a:3:test-meslek-bir' => array( 'price_options' => array( array( 'label' => 'Sınav ücreti', 'units' => array( 'A1', 'A2' ), 'amount_kurus' => 1100000, 'sort_order' => 0 ) ), 'min_amount_kurus' => 1100000, 'max_amount_kurus' => 1100000 ) ),
		'sector' => array( 'sector:zz-test-a' => array( 'description' => 'Güncellenmiş sahte açıklama.' ) ),
	)
);
mb_apply_fixture_write_dir( $m6_dir_v2, $m6_v2 );
$E15 = $m6_full( $m6_dir );
$m6_apply_stage( $E15, 'all' );
$m6_fee_id = null;
foreach ( $E15->world->posts as $id => $p ) {
	if ( 'mb_ucret' === $p['post_type'] && 'fee:zz-test-a:3:test-meslek-bir' === $p['meta']['_mb_import_source_key'] ) {
		$m6_fee_id = $id;
	}
}
$E15->world->posts[ $m6_fee_id ]['content']               = 'Editörün serbest metni';
$E15->world->posts[ $m6_fee_id ]['meta']['_yoast_title']  = 'SEO başlığı';
$E15->world->posts[ $m6_fee_id ]['meta']['_ucuncu_taraf'] = array( 'x' => 1 );
$E15v2 = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $E15->repo, $m6_dir_v2 ), $E15->writer, $E15->tx, $E15->store, $E15->audit );
$E15vp = $E15v2->preview( 'all' );
$E15vr = $E15v2->apply( 'all', $E15vp['plan_digest'], null, 1 );
mb_test( '6B3 update: sektör açıklaması ve ücret fiyatı güncellendi (2 update); editör içeriği, SEO ve üçüncü taraf meta KORUNDU',
	'completed' === $E15vr['status'] && 2 === $E15vp['writes'] && '1100000' === $E15->world->posts[ $m6_fee_id ]['meta']['_mb_min_amount_kurus']
	&& 'Editörün serbest metni' === $E15->world->posts[ $m6_fee_id ]['content'] && 'SEO başlığı' === $E15->world->posts[ $m6_fee_id ]['meta']['_yoast_title'] && array( 'x' => 1 ) === $E15->world->posts[ $m6_fee_id ]['meta']['_ucuncu_taraf'] );
$E16 = $m6_full( $m6_dir );
$m6_apply_stage( $E16, 'all' );
$E16->world->faults = array( array( 'op' => 'touch_unmanaged', 'source_key' => 'fee:zz-test-a:3:test-meslek-bir' ) );
$E16v2 = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $E16->repo, $m6_dir_v2 ), $E16->writer, $E16->tx, $E16->store, $E16->audit );
$E16vp = $E16v2->preview( 'all' );
$E16vr = $E16v2->apply( 'all', $E16vp['plan_digest'], null, 1 );
mb_test( '6B3 update: yazıcı yönetilmeyen bir alana dokunursa (parmak izi farkı) batch geri alınır: unmanaged_field_changed', 'unmanaged_field_changed' === $E16vr['error_code'] && 'failed' === $E16->store->get_run( $E16vr['run_uid'] )['status'] );

/* --- 6B3-G) Rollback servisi --- */
$R1  = $m6_full( $m6_dir );
$R1f = $m6_apply_stage( $R1, 'all' );
$R1pv = $R1->rollback->preview( $R1f['run_uid'] );
mb_test( '6B3 rollback önizleme: 5 bekleyen item, 64-hex digest, engel yok, HİÇBİR yazma', true === $R1pv['ok'] && 5 === $R1pv['items_pending'] && 1 === preg_match( '/^[0-9a-f]{64}\z/', $R1pv['rollback_digest'] ) && array() === $R1pv['blockers'] );
$R1w = count( $R1->world->writeLog );
mb_test( '6B3 rollback red: yanlış/eksik onay -> reddedilir, dokunulmadı', 'confirmation_mismatch' === $R1->rollback->rollback( $R1f['run_uid'], str_repeat( 'e', 64 ), 1 )['error_code'] && 'invalid_confirmation' === $R1->rollback->rollback( $R1f['run_uid'], '', 1 )['error_code'] && $R1w === count( $R1->world->writeLog ) && 'completed' === $R1->store->get_run( $R1f['run_uid'] )['status'] );
$R1sector_run = null;
foreach ( $R1->world->runs as $run ) {
	if ( 'sectors' === $run['stage'] ) {
		$R1sector_run = $run;
	}
}
$R1sp = $R1->rollback->preview( $R1sector_run['uid'] );
$R1sr = $R1->rollback->rollback( $R1sector_run['uid'], $R1sp['rollback_digest'], 1 );
mb_test( '6B3 rollback red: sektör terimine run dışı yeterlilik/ücret bağlıyken terim rollback\'i reddedilir (rollback_failed, term_has_external_dependents), hiçbir şey silinmez',
	false === $R1sr['ok'] && 'term_has_external_dependents' === $R1sr['error_code'] && 'rollback_failed' === $R1->store->get_run( $R1sector_run['uid'] )['status'] && 3 === count( $R1->world->terms ) );
$R1r = $R1->rollback->rollback( $R1f['run_uid'], $R1pv['rollback_digest'], 1 );
mb_test( '6B3 rollback: ücret run\'ı geri alındı -> 5 ücret çöp kutusunda (silinmedi), run rolled_back, item\'lar rolled_back',
	true === $R1r['ok'] && 'rolled_back' === $R1->store->get_run( $R1f['run_uid'] )['status'] && 5 === $m6_posts( $R1, 'mb_ucret', 'trash' ) && 5 === $R1r['rolled_back_items'] );
mb_test( '6B3 rollback: audit rollback_started -> rollback_completed', array( 'import_rollback_started', 'import_rollback_completed' ) === array_slice( $m6_events( $R1 ), -2 ) );
mb_test( '6B3 rollback red: rolled_back run tekrar geri alınamaz', 'run_not_rollbackable' === $R1->rollback->rollback( $R1f['run_uid'], $R1pv['rollback_digest'], 1 )['error_code'] );
$R1qrun = null;
foreach ( $R1->world->runs as $run ) {
	if ( 'qualifications' === $run['stage'] ) {
		$R1qrun = $run;
	}
}
$R1qr = $R1->rollback->rollback( $R1qrun['uid'], $R1->rollback->preview( $R1qrun['uid'] )['rollback_digest'], 1 );
$R1sr2 = $R1->rollback->rollback( $R1sector_run['uid'], $R1->rollback->preview( $R1sector_run['uid'] )['rollback_digest'], 1 );
mb_test( '6B3 rollback sırası: ücret -> yeterlilik -> sektör; önceki rollback_failed run tekrar denenebilir; terimler silindi, postlar çöpte',
	true === $R1qr['ok'] && true === $R1sr2['ok'] && 0 === count( $R1->world->terms ) && 3 === $m6_posts( $R1, 'mb_yeterlilik', 'trash' ) && 'rolled_back' === $R1->store->get_run( $R1sector_run['uid'] )['status'] );

$R2 = $m6_full( $m6_dir );
$R2f = $m6_apply_stage( $R2, 'all' );
foreach ( $R2->world->posts as $id => $p ) {
	if ( 'mb_ucret' === $p['post_type'] && 'fee:zz-test-b:1:test-meslek-bes' === $p['meta']['_mb_import_source_key'] ) {
		$R2->world->posts[ $id ]['meta']['_mb_source_name'] = 'Kullanıcı düzeltmesi';
		$m6_user_fee = $id;
	}
}
$R2w  = count( $R2->world->writeLog );
$R2pv = $R2->rollback->preview( $R2f['run_uid'] );
$R2r  = $R2->rollback->rollback( $R2f['run_uid'], $R2pv['rollback_digest'], 1 );
mb_test( '6B3 rollback drift: import sonrası kullanıcı değişikliği -> rollback reddedilir (drift_detected), HİÇBİR yazma, kullanıcı değişikliği korunur',
	! empty( $R2pv['blockers'] ) && false === $R2r['ok'] && 'drift_detected' === $R2r['error_code'] && $R2w === count( $R2->world->writeLog )
	&& 'Kullanıcı düzeltmesi' === $R2->world->posts[ $m6_user_fee ]['meta']['_mb_source_name'] && 0 === $m6_posts( $R2, 'mb_ucret', 'trash' ) && 'rollback_failed' === $R2->store->get_run( $R2f['run_uid'] )['status'] );

$R3 = $m6_full( $m6_dir );
$m6_apply_stage( $R3, 'all' );
foreach ( $R3->world->posts as $id => $p ) {
	if ( 'mb_ucret' === $p['post_type'] && 'fee:zz-test-a:3:test-meslek-bir' === $p['meta']['_mb_import_source_key'] ) {
		$m6_fee_id3 = $id;
	}
}
$R3->world->posts[ $m6_fee_id3 ]['content']              = 'Editör metni';
$R3->world->posts[ $m6_fee_id3 ]['meta']['_yoast_title'] = 'SEO';
$R3v2 = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $R3->repo, $m6_dir_v2 ), $R3->writer, $R3->tx, $R3->store, $R3->audit );
$R3u  = $R3v2->apply( 'all', $R3v2->preview( 'all' )['plan_digest'], null, 1 );
$R3r  = $R3->rollback->rollback( $R3u['run_uid'], $R3->rollback->preview( $R3u['run_uid'] )['rollback_digest'], 1 );
mb_test( '6B3 update rollback: yalnız yönetilen alanlar eski değerlere döner (fiyat 1000000, açıklama eski), marker/hash eski sözleşmeye; editör/SEO korunur',
	true === $R3r['ok'] && '1000000' === $R3->world->posts[ $m6_fee_id3 ]['meta']['_mb_min_amount_kurus'] && 'Editör metni' === $R3->world->posts[ $m6_fee_id3 ]['content'] && 'SEO' === $R3->world->posts[ $m6_fee_id3 ]['meta']['_yoast_title']
	&& 'Sahte test sektörü (yalnız yerel apply fixture).' === reset( $R3->world->terms )['description'] && 2 === $R3v2->preview( 'all' )['writes'] );
$R3v1 = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $R3->repo, $m6_dir ), $R3->writer, $R3->tx, $R3->store, $R3->audit );
mb_test( '6B3 update rollback: geri alınan durum ilk (v1) fixture planına göre tamamen unchanged (11/11, 0 yazma)', 0 === $R3v1->preview( 'all' )['writes'] && 11 === $R3v1->preview( 'all' )['summary']['operations']['unchanged'] );

$R4 = $m6_full( $m6_dir );
$R4f = $m6_apply_stage( $R4, 'all', 2 );
foreach ( $R4->world->items as $iid => $item ) {
	if ( 'fee:zz-test-b:4:test-meslek-iki' === $item['source_key'] ) {
		$R4->world->items[ $iid ]['rollback_record'] = '{"schema":"mavibelge-import-rollback/2"';
	}
}
$R4w = count( $R4->world->writeLog );
$R4pv = $R4->rollback->preview( $R4f['run_uid'] );
mb_test( '6B3 rollback: bozuk checkpoint/rollback JSON -> fail-closed (rollback_record_invalid), hiçbir yazma',
	false === $R4pv['ok'] && 'rollback_record_invalid' === $R4pv['error_code'] && 'rollback_record_invalid' === $R4->rollback->rollback( $R4f['run_uid'], str_repeat( 'a', 64 ), 1 )['error_code'] && $R4w === count( $R4->world->writeLog ) );

$R4b = $m6_full( $m6_dir );
$R4bf = $m6_apply_stage( $R4b, 'all' );
foreach ( $R4b->world->items as $iid => $item ) {
	if ( 'fee:zz-test-a:3:test-meslek-bir' === $item['source_key'] ) {
		$m6_tampered = json_decode( $item['rollback_record'], true );
		$m6_tampered['changed_fields'] = array( 'title' );
		$R4b->world->items[ $iid ]['rollback_record'] = json_encode( $m6_tampered );
	}
}
$R4bw = count( $R4b->world->writeLog );
mb_test( '6B3 rollback: GEÇERLİ JSON ama kurcalanmış kayıt (changed_fields) -> tek doğrulayıcı reddeder (rollback_record_invalid), hiçbir yazma',
	'rollback_record_invalid' === $R4b->rollback->preview( $R4bf['run_uid'] )['error_code'] && 'rollback_record_invalid' === $R4b->rollback->rollback( $R4bf['run_uid'], str_repeat( 'a', 64 ), 1 )['error_code'] && $R4bw === count( $R4b->world->writeLog ) );

$R5 = $m6_full( $m6_dir );
$R5f = $m6_apply_stage( $R5, 'all', 2 );
$m6_trash_fault_id = null;
foreach ( $R5->store->get_items( $R5->store->get_run( $R5f['run_uid'] )['id'] ) as $item ) {
	if ( 'fee:zz-test-b:4:test-meslek-iki' === $item['source_key'] ) {
		$m6_trash_fault_id = (string) $item['target_id'];
	}
}
$R5->world->faults = array( array( 'op' => 'trash_post', 'source_key' => $m6_trash_fault_id ) );
$R5pv = $R5->rollback->preview( $R5f['run_uid'] );
$R5r  = $R5->rollback->rollback( $R5f['run_uid'], $R5pv['rollback_digest'], 2 );
mb_test( '6B3 kısmi rollback AÇIKÇA hata: sonraki batch\'te hata -> rollback_failed; önceki batch item\'ları rolled_back, hatalı batch geri alındı',
	false === $R5r['ok'] && 'rollback_failed' === $R5->store->get_run( $R5f['run_uid'] )['status'] && $R5r['rolled_back_items'] > 0 && $R5r['rolled_back_items'] < 5 && 'import_rollback_failed' === end( $R5->world->audit )['event'] );
$R5->world->faults = array();
$R5r2 = $R5->rollback->rollback( $R5f['run_uid'], $R5->rollback->preview( $R5f['run_uid'] )['rollback_digest'], 2 );
mb_test( '6B3 kısmi rollback: yeniden deneme kalan item\'ları tamamlar (yeniden başlatma güvenliği) -> rolled_back, 5 ücret çöpte',
	true === $R5r2['ok'] && 'rolled_back' === $R5->store->get_run( $R5f['run_uid'] )['status'] && 5 === $m6_posts( $R5, 'mb_ucret', 'trash' ) );
/* --- 6B3-H) Yazma adapterının saf parçaları --- */
$W6 = 'MaviBelge_Core_Import_WordPress_Target_Writer';
mb_test( '6B3 adapter: stored_equals KATI — string aynen; int yalnız aynı int/kanonik string; bool 1/"" temsili; dizi birebir',
	$W6::stored_equals( 'abc', 'abc' ) && ! $W6::stored_equals( 'abc', 'abc ' ) && $W6::stored_equals( 150000, '150000' ) && ! $W6::stored_equals( 150000, '15000000' ) && ! $W6::stored_equals( 150000, '150000.0' ) && ! $W6::stored_equals( 0, '' )
	&& $W6::stored_equals( true, '1' ) && ! $W6::stored_equals( true, 'true' ) && $W6::stored_equals( false, '' ) && ! $W6::stored_equals( false, 'false' )
	&& $W6::stored_equals( array( 'a' => 1 ), array( 'a' => 1 ) ) && ! $W6::stored_equals( array( 'a' => 1 ), array( 'a' => '1' ) ) && ! $W6::stored_equals( null, '' ) );
$m6_payload_keys = function ( $type, $fields, $sk ) {
	$p = MaviBelge_Core_Import_Write_Payload::prepare( $type, $fields, $sk, MaviBelge_Core_Import_Hash::hash( $fields ) );
	$k = array_keys( $p['payload']['post_meta'] );
	sort( $k );
	return $k;
};
$m6_expect = function ( $type ) {
	$k = MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META[ $type ];
	sort( $k );
	return $k;
};
$m6_qfields = MaviBelge_Core_Import_Managed_Fields::project_qualification( $fx_qualification, array( 'sector_term_id' => 5 ) )['fields'];
$m6_ffields = MaviBelge_Core_Import_Managed_Fields::project_fee( $fx_fee_without_code, array(), MaviBelge_Core_Import_Record_Validator::validate_fee( $fx_fee_without_code )['normalized_price_options'] )['fields'];
mb_test( '6B3 adapter: MANAGED_POST_META (yönetilmeyen alan parmak izinin dışladığı liste) yükün yazdığı meta anahtarlarıyla BİREBİR aynı (yeterlilik + ücret)',
	$m6_expect( 'qualification' ) === $m6_payload_keys( 'qualification', $m6_qfields, $fx_qualification['source_key'] ) && $m6_expect( 'fee' ) === $m6_payload_keys( 'fee', $m6_ffields, $fx_fee_without_code['source_key'] ) );

$R6 = $m6_full( $m6_dir );
$R6f = $m6_apply_stage( $R6, 'all' );
mb_test( '6B3 rollback: bilinmeyen run -> run_not_found', 'run_not_found' === $R6->rollback->preview( str_repeat( '0', 32 ) )['error_code'] && 'run_not_found' === $R6->rollback->rollback( 'yok', str_repeat( 'a', 64 ), 1 )['error_code'] );

/* --- 6B3-DÜZELTME-1) Create rollback: kullanıcı değişikliğini koruma (unmanaged_fingerprint) --- */
$m6_first_of = function ( $env, $type, $decision, $stageRunUid ) {
	foreach ( $env->store->get_items( $env->store->get_run( $stageRunUid )['id'] ) as $item ) {
		if ( $item['type'] === $type && $decision === $item['decision'] ) {
			return $item;
		}
	}
	return null;
};
$m6_rb_drift = function ( $env, $runUid ) {
	$pv = $env->rollback->preview( $runUid );
	$w  = count( $env->world->writeLog );
	$r  = $env->rollback->rollback( $runUid, $pv['rollback_digest'], 1 );
	return array( 'preview' => $pv, 'result' => $r, 'wrote' => count( $env->world->writeLog ) !== $w );
};
$D1  = $m6_full( $m6_dir );
$D1f = $m6_apply_stage( $D1, 'all' );
$D1fee = $m6_first_of( $D1, 'fee', 'create', $D1f['run_uid'] );
$D1->world->posts[ $D1fee['target_id'] ]['content'] = 'Kullanıcının yazdığı içerik';
$D1r = $m6_rb_drift( $D1, $D1f['run_uid'] );
mb_test( '6B3 düzeltme create rollback: import ücret postunun post_content alanı değişti -> önizleme drift_detected, rollback reddedilir, post çöpe GİTMEZ, içerik korunur',
	in_array( 'drift_detected', array_column( $D1r['preview']['blockers'], 'code' ), true ) && false === $D1r['result']['ok'] && 'drift_detected' === $D1r['result']['error_code'] && false === $D1r['wrote']
	&& 0 === $m6_posts( $D1, 'mb_ucret', 'trash' ) && 'Kullanıcının yazdığı içerik' === $D1->world->posts[ $D1fee['target_id'] ]['content'] );
$D2  = $m6_full( $m6_dir );
$D2f = $m6_apply_stage( $D2, 'all' );
$D2fee = $m6_first_of( $D2, 'fee', 'create', $D2f['run_uid'] );
$D2->world->posts[ $D2fee['target_id'] ]['meta']['_yoast_wpseo_title'] = 'SEO başlığı';
$D2r = $m6_rb_drift( $D2, $D2f['run_uid'] );
mb_test( '6B3 düzeltme create rollback: import postuna SEO metası eklendi -> drift_detected, çöpe gitmez',
	'drift_detected' === $D2r['result']['error_code'] && false === $D2r['wrote'] && 0 === $m6_posts( $D2, 'mb_ucret', 'trash' ) );
$D3  = $m6_full( $m6_dir );
$D3f = $m6_apply_stage( $D3, 'all' );
$D3fee = $m6_first_of( $D3, 'fee', 'create', $D3f['run_uid'] );
$D3->world->posts[ $D3fee['target_id'] ]['terms']['mb_haber_turu'] = array( 7 );
$D3r = $m6_rb_drift( $D3, $D3f['run_uid'] );
mb_test( '6B3 düzeltme create rollback: import postuna yönetilmeyen taksonomi terimi bağlandı -> drift_detected, çöpe gitmez',
	'drift_detected' === $D3r['result']['error_code'] && false === $D3r['wrote'] && 0 === $m6_posts( $D3, 'mb_ucret', 'trash' ) );
$D3q  = $m6_full( $m6_dir );
$D3qf = $m6_apply_stage( $D3q, 'all' );
$D3qrun = null;
foreach ( $D3q->world->runs as $run ) {
	if ( 'qualifications' === $run['stage'] ) {
		$D3qrun = $run;
	}
}
$D3qi = $m6_first_of( $D3q, 'qualification', 'create', $D3qrun['uid'] );
$D3q->world->posts[ $D3qi['target_id'] ]['content'] = 'Yeterlilik içeriği kullanıcı';
$D3qr = $m6_rb_drift( $D3q, $D3qrun['uid'] );
mb_test( '6B3 düzeltme create rollback: import yeterlilik postunun içeriği değişti -> drift_detected, çöpe gitmez',
	'drift_detected' === $D3qr['result']['error_code'] && false === $D3qr['wrote'] && 0 === $m6_posts( $D3q, 'mb_yeterlilik', 'trash' ) );
$m6_sector_run = function ( $env ) {
	foreach ( $env->world->runs as $run ) {
		if ( 'sectors' === $run['stage'] ) {
			return $run;
		}
	}
	return null;
};
$D4  = mb_fake_apply_env( $m6_dir );
$D4f = $m6_apply_stage( $D4, 'sectors' );
$D4item = $m6_first_of( $D4, 'sector', 'create', $D4f['run_uid'] );
$D4->world->terms[ $D4item['target_id'] ]['meta']['_yonetilmeyen_term_meta'] = 'kullanıcı';
$D4r = $m6_rb_drift( $D4, $D4f['run_uid'] );
mb_test( '6B3 düzeltme create rollback: import sektör terimine yönetilmeyen term meta eklendi -> drift_detected, HİÇ terim silinmez',
	'drift_detected' === $D4r['result']['error_code'] && false === $D4r['wrote'] && 3 === count( $D4->world->terms ) );
$D5  = mb_fake_apply_env( $m6_dir );
$D5f = $m6_apply_stage( $D5, 'sectors' );
$D5item = $m6_first_of( $D5, 'sector', 'create', $D5f['run_uid'] );
$D5->world->terms[ $D5item['target_id'] ]['parent'] = 999;
$D5r = $m6_rb_drift( $D5, $D5f['run_uid'] );
mb_test( '6B3 düzeltme create rollback: import sektör teriminin parent değeri değişti -> drift_detected, HİÇ terim silinmez',
	'drift_detected' === $D5r['result']['error_code'] && false === $D5r['wrote'] && 3 === count( $D5->world->terms ) );
$D6  = mb_fake_apply_env( $m6_dir );
$D6f = $m6_apply_stage( $D6, 'sectors' );
$D6item = $m6_first_of( $D6, 'sector', 'create', $D6f['run_uid'] );
$D6->world->terms[ $D6item['target_id'] ]['term_group'] = 3;
$D6r = $m6_rb_drift( $D6, $D6f['run_uid'] );
mb_test( '6B3 düzeltme create rollback: import sektör teriminin term_group değeri değişti -> drift_detected, HİÇ terim silinmez',
	'drift_detected' === $D6r['result']['error_code'] && false === $D6r['wrote'] && 3 === count( $D6->world->terms ) );
$D7  = $m6_full( $m6_dir );
$D7f = $m6_apply_stage( $D7, 'all' );
$D7r = $m6_rb_drift( $D7, $D7f['run_uid'] );
mb_test( '6B3 düzeltme create rollback: değişiklik yoksa temiz rollback çalışır (5 ücret çöpte, rolled_back)', true === $D7r['result']['ok'] && 'rolled_back' === $D7->store->get_run( $D7f['run_uid'] )['status'] && 5 === $m6_posts( $D7, 'mb_ucret', 'trash' ) );
mb_test( '6B3 düzeltme rollback kaydı: create için unmanaged_fingerprint ZORUNLU 64-hex; update için null; eksik/bozuk kayıt reddedilir',
	(function () use ( $D7 ) {
		$AE = 'MaviBelge_Core_Import_Apply_Eligibility';
		$ok = false;
		foreach ( $D7->world->items as $item ) {
			$rec = json_decode( $item['rollback_record'], true );
			if ( 'create' !== $rec['decision'] ) {
				continue;
			}
			$ok = is_string( $rec['unmanaged_fingerprint'] ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', $rec['unmanaged_fingerprint'] ) && $AE::validate_rollback_record( $rec );
			$noFp = $rec;
			unset( $noFp['unmanaged_fingerprint'] );
			$nullFp = $rec;
			$nullFp['unmanaged_fingerprint'] = null;
			$badFp = $rec;
			$badFp['unmanaged_fingerprint'] = 'ZZ';
			$ok = $ok && ! $AE::validate_rollback_record( $noFp ) && ! $AE::validate_rollback_record( $nullFp ) && ! $AE::validate_rollback_record( $badFp );
			break;
		}
		return $ok;
	})() );

/* --- 6B3-DÜZELTME-2) Rollback sonrası aynı manifest yeniden uygulanabilir --- */
$m6_conflicts = function ( $env, $stage ) {
	$p = $env->apply->preview( $stage );
	return array( 'conflict' => $p['summary']['operations']['conflict'], 'create' => $p['summary']['operations']['create'], 'applicable' => $p['summary']['applicable'], 'eligible' => $p['eligible'] );
};
$m6_rollback_all_runs = function ( $env, array $stages ) {
	foreach ( $stages as $stage ) {
		foreach ( array_reverse( $env->world->runs, true ) as $run ) {
			if ( $run['stage'] === $stage && 'completed' === $run['status'] ) {
				$r = $env->rollback->rollback( $run['uid'], $env->rollback->preview( $run['uid'] )['rollback_digest'], 1 );
				if ( true !== $r['ok'] ) {
					return $r;
				}
			}
		}
	}
	return array( 'ok' => true );
};
$C1 = mb_fake_apply_env( $m6_dir );
$m6_apply_stage( $C1, 'sectors' );
$m6_apply_stage( $C1, 'qualifications' );
$m6_apply_stage( $C1, 'all' );
$C1rb = $m6_rollback_all_runs( $C1, array( 'all' ) );
$C1c  = $m6_conflicts( $C1, 'all' );
mb_test( '6B3 düzeltme reapply: sectors->qualifications->all uygula -> all rollback -> all planı applicable=true, conflict=0, 5 create adayı (ücretler)',
	true === $C1rb['ok'] && true === $C1c['applicable'] && true === $C1c['eligible'] && 0 === $C1c['conflict'] && 5 === $C1c['create'] );
$C1r = $m6_apply_stage( $C1, 'all' );
mb_test( '6B3 düzeltme reapply: aynı manifest yeniden apply -> completed, 5 ücret yeniden var (canlı + çöpteki kabuklar ayrı)', true === $C1r['ok'] && 'completed' === $C1r['status'] && 3 === count( $C1->world->terms ) && 10 === $m6_posts( $C1, 'mb_ucret' ) && 5 === $m6_posts( $C1, 'mb_ucret', 'trash' ) );
$C1b = mb_fake_apply_env( $m6_dir );
$m6_apply_stage( $C1b, 'sectors' );
$m6_apply_stage( $C1b, 'qualifications' );
$m6_apply_stage( $C1b, 'all' );
$C1brb = $m6_rollback_all_runs( $C1b, array( 'all', 'qualifications', 'sectors' ) );
$C1bs = $m6_conflicts( $C1b, 'sectors' );
mb_test( '6B3 düzeltme reapply: TÜM runlar (all, qualifications, sectors) geri alındıktan sonra sectors planı applicable=true/conflict=0; sectors->qualifications->all zinciri yeniden completed',
	true === $C1brb['ok'] && 0 === count( $C1b->world->terms ) && true === $C1bs['applicable'] && 0 === $C1bs['conflict'] && 3 === $C1bs['create']
	&& 'completed' === $m6_apply_stage( $C1b, 'sectors' )['status'] && 'completed' === $m6_apply_stage( $C1b, 'qualifications' )['status'] && 'completed' === $m6_apply_stage( $C1b, 'all' )['status'] );
$C2 = mb_fake_apply_env( $m6_dir );
$m6_apply_stage( $C2, 'sectors' );
$C2q = $m6_apply_stage( $C2, 'qualifications' );
$C2rb = $C2->rollback->rollback( $C2q['run_uid'], $C2->rollback->preview( $C2q['run_uid'] )['rollback_digest'], 1 );
$C2c = $m6_conflicts( $C2, 'qualifications' );
mb_test( '6B3 düzeltme reapply: qualifications apply -> rollback -> dry-run applicable=true, conflict=0 -> yeniden apply completed',
	true === $C2rb['ok'] && true === $C2c['applicable'] && 0 === $C2c['conflict'] && 3 === $C2c['create'] && 'completed' === $m6_apply_stage( $C2, 'qualifications' )['status'] );
$C3 = mb_fake_apply_env( $m6_dir );
$C3s = $m6_apply_stage( $C3, 'sectors' );
$C3rb = $C3->rollback->rollback( $C3s['run_uid'], $C3->rollback->preview( $C3s['run_uid'] )['rollback_digest'], 1 );
$C3c = $m6_conflicts( $C3, 'sectors' );
mb_test( '6B3 düzeltme reapply: sectors apply -> rollback -> dry-run applicable=true, conflict=0 -> yeniden apply completed',
	true === $C3rb['ok'] && true === $C3c['applicable'] && 0 === $C3c['conflict'] && 'completed' === $m6_apply_stage( $C3, 'sectors' )['status'] );
$C4 = $m6_full( $m6_dir );
$C4f = $m6_apply_stage( $C4, 'all' );
$m6_rollback_all_runs( $C4, array( 'all' ) );
/* Kullanıcıya ait keyfi çöp kaydı ve başka run'ın marker'ı HÂLÂ conflict üretir (fuzzy/görmezden gelme YOK). */
$C4->world->posts[ 800 ] = array( 'post_type' => 'mb_ucret', 'title' => 'Kullanıcının çöp kaydı', 'status' => 'trash', 'content' => '', 'terms' => array(),
	'meta' => array( '_mb_profession_name' => 'Test Meslek Bir', '_mb_sector_slug' => 'zz-test-a', '_mb_level' => '3' ) );
$C4c = $m6_conflicts( $C4, 'all' );
mb_test( '6B3 düzeltme reapply: kullanıcının aynı doğal anahtarlı çöp kaydı HÂLÂ conflict üretir (plan applicable=false)', false === $C4c['applicable'] && $C4c['conflict'] >= 1 );
$C5 = $m6_full( $m6_dir );
$m6_apply_stage( $C5, 'all' );
$m6_rollback_all_runs( $C5, array( 'all' ) );
$C5->world->posts[ 801 ] = array( 'post_type' => 'mb_ucret', 'title' => 'Başka run', 'status' => 'trash', 'content' => '', 'terms' => array(),
	'meta' => array( '_mb_profession_name' => 'Test Meslek Bir', '_mb_sector_slug' => 'zz-test-a', '_mb_level' => '3', '_mb_import_source_key' => 'fee:zz-test-a:3:test-meslek-bir' ) );
$C5c = $m6_conflicts( $C5, 'all' );
mb_test( '6B3 düzeltme reapply: başka bir run\'ın bıraktığı (rollback edilmemiş) marker\'lı çöp kaydı HÂLÂ conflict üretir', false === $C5c['applicable'] && $C5c['conflict'] >= 1 );
$C6 = $m6_full( $m6_dir );
$m6_apply_stage( $C6, 'all' );
$m6_rollback_all_runs( $C6, array( 'all' ) );
$m6_managed_left = 0;
foreach ( $C6->world->posts as $p ) {
	if ( 'trash' === $p['status'] && 'mb_ucret' === $p['post_type'] ) {
		foreach ( MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META['fee'] as $k ) {
			$m6_managed_left += isset( $p['meta'][ $k ] ) ? 1 : 0;
		}
	}
}
mb_test( '6B3 düzeltme reapply: geri alınmış create postlarında yönetilen meta (marker/doğal anahtar) kalmaz; kalıcı silme yok (5 post çöpte durur)', 0 === $m6_managed_left && 5 === $m6_posts( $C6, 'mb_ucret', 'trash' ) );

/* --- 6B3-DÜZELTME-3) Run durumu + audit olayları ATOMİK (yarım durum yok) --- */
$m6_status = function ( $env, $uid ) {
	return $env->store->get_run( $uid )['status'];
};
/* $m6_full önceki aşamaların (sectors/qualifications) olaylarını üretir; negatif audit kontrolleri yalnız SONRAKİ olaylara bakar. */
$m6_since = function ( $env ) {
	$all = array_map(
		function ( $a ) {
			return $a['event'];
		},
		$env->world->audit
	);
	return array_slice( $all, $env->n0 );
};
/* (a) transaction altyapısı baştan bozuk: run_started atomik geçişi olmaz -> run failed, audit yarım kalmaz. */
$A0 = $m6_full( $m6_dir ); $A0->n0 = count( $A0->world->audit );
$A0p = $A0->apply->preview( 'all' );
$A0->tx->failCommit = true;
$A0r = $A0->apply->apply( 'all', $A0p['plan_digest'], null, 1 );
mb_test( '6B3 atomiklik: COMMIT altyapısı baştan bozuk -> run_start_failed; kalıcı durum failed (planned KALMAZ), audit\'te started/completed YOK, yazma YOK',
	false === $A0r['ok'] && 'run_start_failed' === $A0r['error_code'] && 'failed' === $A0r['status'] && 'failed' === $m6_status( $A0, $A0r['run_uid'] )
	&& ! in_array( 'import_run_started', $m6_since( $A0 ), true ) && ! in_array( 'import_run_completed', $m6_since( $A0 ), true ) && 0 === $m6_posts( $A0, 'mb_ucret' ) );
/* (b) run_completed audit yazılamaz: audit completed + run running OLUŞMAZ. */
$A1 = $m6_full( $m6_dir ); $A1->n0 = count( $A1->world->audit );
$A1->audit->failEvents = array( 'import_run_completed' );
$A1r = $m6_apply_stage( $A1, 'all' );
$A1run = $A1->store->get_run( $A1r['run_uid'] );
mb_test( '6B3 atomiklik: run_completed audit yazılamazsa run rollback_required (running KALMAZ), completed audit\'i YOK, sonuç gerçek durumu raporlar, run_failed(finalization_failed) atomik yazıldı',
	false === $A1r['ok'] && 'finalization_failed' === $A1r['error_code'] && 'rollback_required' === $A1r['status'] && 'rollback_required' === $A1run['status'] && 'finalization_failed' === $A1run['error_code']
	&& ! in_array( 'import_run_completed', $m6_since( $A1 ), true ) && 'import_run_failed' === end( $A1->world->audit )['event'] && array() === $A1r['errors'] );
/* (c) hem completed hem failed audit'i yazılamaz: yalnız durum güvenle rollback_required. */
$A2 = $m6_full( $m6_dir ); $A2->n0 = count( $A2->world->audit );
$A2->audit->failEvents = array( 'import_run_completed', 'import_run_failed' );
$A2r = $m6_apply_stage( $A2, 'all' );
mb_test( '6B3 atomiklik: completed VE failed audit\'i yazılamazsa run yine güvenle rollback_required (audit\'siz), yalancı completed/audit YOK, açık hata satırı var',
	'rollback_required' === $A2r['status'] && 'rollback_required' === $m6_status( $A2, $A2r['run_uid'] ) && ! in_array( 'import_run_completed', $m6_since( $A2 ), true ) && ! in_array( 'import_run_failed', $m6_since( $A2 ), true ) && ! empty( $A2r['errors'] ) );
/* (d) completed durum geçişi başarısız: audit completed geri alınır (orphan audit YOK). */
$A3 = $m6_full( $m6_dir ); $A3->n0 = count( $A3->world->audit );
$A3->store->failTransitionTo = array( 'completed' );
$A3r = $m6_apply_stage( $A3, 'all' );
mb_test( '6B3 atomiklik: completed durum geçişi başarısızsa aynı transaction\'daki run_completed audit\'i GERİ ALINIR (orphan yok); sonuç completed UYDURMAZ, gerçek durum rollback_required',
	false === $A3r['ok'] && 'completed' !== $A3r['status'] && 'rollback_required' === $A3r['status'] && 'rollback_required' === $m6_status( $A3, $A3r['run_uid'] ) && ! in_array( 'import_run_completed', $m6_since( $A3 ), true ) );
/* (e) hiçbir güvenli geçiş uygulanamaz: açık fail-closed hata + GERÇEK kalıcı durum (running). */
$A4 = $m6_full( $m6_dir ); $A4->n0 = count( $A4->world->audit );
$A4->store->failTransitionTo = array( 'completed', 'rollback_required' );
$A4r = $m6_apply_stage( $A4, 'all' );
mb_test( '6B3 atomiklik: hiçbir geçiş uygulanamazsa sonuç açık hata + GERÇEK kalıcı durum (running); completed uydurulmaz, orphan completed/failed audit YOK',
	false === $A4r['ok'] && 'finalization_failed' === $A4r['error_code'] && 'running' === $A4r['status'] && 'running' === $m6_status( $A4, $A4r['run_uid'] ) && ! empty( $A4r['errors'] )
	&& ! in_array( 'import_run_completed', $m6_since( $A4 ), true ) && ! in_array( 'import_run_failed', $m6_since( $A4 ), true ) );
/* (f) batch başarısız + run_failed audit yazılamaz: yalnız durum kapanır. */
$A5 = $m6_full( $m6_dir ); $A5->n0 = count( $A5->world->audit );
$A5->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'fee:zz-test-c:5:test-meslek-uc' ) );
$A5->audit->failEvents = array( 'import_run_failed' );
$A5r = $m6_apply_stage( $A5, 'all', 2 );
mb_test( '6B3 atomiklik: batch hatası + run_failed audit yazılamazsa run yine rollback_required\'a kapanır (audit\'siz), sonuç gerçek durumu ve audit hatasını bildirir',
	'write_failed' === $A5r['error_code'] && 'rollback_required' === $A5r['status'] && 'rollback_required' === $m6_status( $A5, $A5r['run_uid'] ) && ! empty( $A5r['errors'] ) );
/* (g) rollback_started audit yazılamaz: durum ve veri DEĞİŞMEZ, sonuç gerçek durumu raporlar. */
$A6 = $m6_full( $m6_dir ); $A6->n0 = count( $A6->world->audit );
$A6f = $m6_apply_stage( $A6, 'all' );
$A6->audit->failEvents = array( 'import_rollback_started' );
$A6pv = $A6->rollback->preview( $A6f['run_uid'] );
$A6w  = count( $A6->world->writeLog );
$A6r  = $A6->rollback->rollback( $A6f['run_uid'], $A6pv['rollback_digest'], 1 );
mb_test( '6B3 atomiklik: rollback_started audit yazılamazsa durum completed KALIR (rolling_back OLUŞMAZ), started audit\'i yok, hiçbir yazma, sonuç gerçek durumu bildirir',
	false === $A6r['ok'] && 'state_transition_failed' === $A6r['error_code'] && 'completed' === $A6r['status'] && 'completed' === $m6_status( $A6, $A6f['run_uid'] ) && $A6w === count( $A6->world->writeLog ) && ! in_array( 'import_rollback_started', $m6_since( $A6 ), true ) );
/* (h) rollback_completed audit yazılamaz: rollback_failed (completed audit YOK). */
$A7 = $m6_full( $m6_dir ); $A7->n0 = count( $A7->world->audit );
$A7f = $m6_apply_stage( $A7, 'all' );
$A7->audit->failEvents = array( 'import_rollback_completed' );
$A7pv = $A7->rollback->preview( $A7f['run_uid'] );
$A7r  = $A7->rollback->rollback( $A7f['run_uid'], $A7pv['rollback_digest'], 1 );
mb_test( '6B3 atomiklik: rollback_completed audit yazılamazsa run rollback_failed (rolling_back KALMAZ), completed audit\'i YOK; rollback_failed(finalization_failed) atomik; sonuç gerçek durum',
	false === $A7r['ok'] && 'finalization_failed' === $A7r['error_code'] && 'rollback_failed' === $A7r['status'] && 'rollback_failed' === $m6_status( $A7, $A7f['run_uid'] )
	&& ! in_array( 'import_rollback_completed', $m6_since( $A7 ), true ) && 'import_rollback_failed' === end( $A7->world->audit )['event'] );
/* (i) rolled_back durum geçişi başarısız: completed audit'i geri alınır, güvenli rollback_failed. */
$A8 = $m6_full( $m6_dir ); $A8->n0 = count( $A8->world->audit );
$A8f = $m6_apply_stage( $A8, 'all' );
$A8->store->failTransitionTo = array( 'rolled_back' );
$A8r = $A8->rollback->rollback( $A8f['run_uid'], $A8->rollback->preview( $A8f['run_uid'] )['rollback_digest'], 1 );
mb_test( '6B3 atomiklik: rolled_back durum geçişi başarısızsa rollback_completed audit\'i GERİ ALINIR; sonuç rolled_back UYDURMAZ (rollback_failed)',
	false === $A8r['ok'] && 'rolled_back' !== $A8r['status'] && 'rollback_failed' === $A8r['status'] && 'rollback_failed' === $m6_status( $A8, $A8f['run_uid'] ) && ! in_array( 'import_rollback_completed', $m6_since( $A8 ), true ) );
/* (j) rollback finalizasyonu hiçbir güvenli geçişi uygulayamaz: gerçek kalıcı durum rolling_back. */
$A9 = $m6_full( $m6_dir ); $A9->n0 = count( $A9->world->audit );
$A9f = $m6_apply_stage( $A9, 'all' );
$A9->store->failTransitionTo = array( 'rolled_back', 'rollback_failed' );
$A9r = $A9->rollback->rollback( $A9f['run_uid'], $A9->rollback->preview( $A9f['run_uid'] )['rollback_digest'], 1 );
mb_test( '6B3 atomiklik: rollback finalizasyonu hiçbir geçişi uygulayamazsa açık hata + GERÇEK kalıcı durum (rolling_back); rolled_back uydurulmaz',
	false === $A9r['ok'] && 'rolling_back' === $A9r['status'] && 'rolling_back' === $m6_status( $A9, $A9f['run_uid'] ) && ! empty( $A9r['errors'] ) && ! in_array( 'import_rollback_completed', $m6_since( $A9 ), true ) );
/* (k) bayat run kapatma: audit yazılamasa da durum kapanır; audit yazılırsa atomik. */
$A10 = $m6_full( $m6_dir ); $A10->n0 = count( $A10->world->audit );
$A10->world->runs[98] = array_merge( reset( $A10->world->runs ), array( 'id' => 98, 'uid' => str_repeat( '8', 32 ), 'status' => 'running', 'committed_items' => 2 ) );
$A10->audit->failEvents = array( 'import_run_failed' );
$A10r = $m6_apply_stage( $A10, 'all' );
mb_test( '6B3 atomiklik: bayat run kapatılırken audit yazılamasa da run kapanır (rollback_required, stale_run); yeni apply bu çözülmemiş run nedeniyle reddedilir',
	'rollback_required' === $A10->world->runs[98]['status'] && 'stale_run' === $A10->world->runs[98]['error_code'] && 'unresolved_run_exists' === $A10r['error_code'] );

/* --- Faz 7+ paketleri: tests/suites/*.php, ada göre sıralı yüklenir (aynı mb_test() sayacı). --- */
$mb_suite_files = glob( __DIR__ . '/suites/*.php' );
sort( $mb_suite_files );
foreach ( $mb_suite_files as $mb_suite_file ) {
	require $mb_suite_file;
}

/* Runtime Doğrulama turu — PHP 7.3'te gerçekten çalıştırıldığında
 * bulunan kusur: mb6b2_temp_dir() ile oluşturulan izole fixture dizinleri
 * hiç silinmiyordu (her çalıştırma sys_get_temp_dir() altında ~21 dizin
 * bırakıyordu). Bu assertion, BU sürecin (getmypid) oluşturduğu hiçbir
 * mb6b2_* dizininin çalıştırma sonunda kalmadığını doğrular. */
mb6b2_cleanup_temp_dirs();
mb_test(
	'Faz 6B2 temizlik: bu sürecin oluşturduğu mb6b2_* geçici fixture dizinlerinin HİÇBİRİ kalmadı',
	array() === glob( sys_get_temp_dir() . '/mb6b2_*_' . getmypid() . '_*', GLOB_ONLYDIR )
);

/* -------------------------------------------------------------- */
printf( "\n%d/%d assertions passed.\n", $total - $failures, $total );

echo "\nNOT covered by this standalone runner (need a real WordPress\n";
echo "test environment — none was available in this phase; see the\n";
echo "Faz2 ikinci düzeltme delivery report):\n";
echo "  - MaviBelge_Core_Validator::is_myk_combination_unique() — needs get_posts()/get_post_meta();\n";
echo "    must specifically re-verify: saving a qualification unchanged does not clear its own MYK code\n";
echo "    (the post__not_in fix), and a genuine second record with the same code+level+revision IS caught\n";
echo "  - normalize_id_list_of_type() / post_exists_of_type() / user_exists() — need a real DB\n";
echo "  - MaviBelge_Core_Field_Repository::resolve_effective_value() — all three tiers (request,\n";
echo "    meta_input, DB) need a real \$_POST/\$postarr/postmeta to resolve against\n";
echo "  - MaviBelge_Core_Field_Repository::write_meta() — needs get_post()/get_post_type()/update_post_meta();\n";
echo "    must specifically re-verify: post not found -> error, no write; post_type mismatch -> error, no write;\n";
echo "    non-array price_options raw_value -> error, no write (the Faz2 son düzeltme brief §4 fix — a prior\n";
echo "    version silently coerced a non-array to an empty list, which could wipe an existing valid price list);\n";
echo "    one valid + one invalid price row -> atomic reject, all three related meta fields (_mb_price_options/\n";
echo "    _mb_min_amount_kurus/_mb_max_amount_kurus) left untouched; fully valid list -> all three updated together\n";
echo "  - MaviBelge_Core_Meta_Schema::auth_callback() — needs get_post_type()/current_user_can(); must\n";
echo "    specifically re-verify: readonly/system_managed fields (_mb_reviewer_user_id etc.) always return\n";
echo "    false even for a user who can edit_post; unknown meta key/post type also returns false\n";
echo "  - MaviBelge_Core_Meta_Schema::register_all() actually wiring sanitize_for_registration() with 4\n";
echo "    accepted_args through a REAL register_post_meta()/sanitize_meta() call (this phase's fix was\n";
echo "    verified by matching the documented wp-includes/meta.php behavior from memory/task-provided\n";
echo "    references, not by executing WordPress's own code — see the delivery report)\n";
echo "  - admin/class-meta-boxes.php save()/guard_*() capability enforcement — needs current_user_can();\n";
echo "    must specifically re-verify: mb_price_editor cannot flip _mb_record_status to active/archived\n";
echo "    via a crafted POST, mb_content_editor cannot flip _mb_haber approval to approved/rejected\n";
echo "  - MaviBelge_Core_Roles v1->v2 migration removing the stale mb_yeterlilik caps from mb_content_editor\n";
echo "  - MaviBelge_Core_Taxonomies::restrict_haber_turu_terms() (creation) and\n";
echo "    ::lock_haber_turu_term_data() (update, via the 'wp_update_term_data' filter) — need\n";
echo "    wp_insert_term()/wp_update_term()\n";
echo "  - MaviBelge_Core_Audit_Log::install()/record() failure handling, incl. the esc_like() fix —\n";
echo "    needs \$wpdb; must specifically verify a similarly-prefixed table does NOT produce a false\n";
echo "    'table exists' positive\n";
echo "  - MaviBelge_Core_Publish_Readiness — needs the full wp_insert_post() request lifecycle. Test\n";
echo "    contract to cover once a WP test environment exists:\n";
echo "      * new post, classic admin form: missing required field blocks publish, complete fields allow it\n";
echo "      * existing post, classic admin form: same, re-checked on every save while status=publish is requested\n";
echo "      * new post via wp_insert_post(array('meta_input'=>...)): meta_input values are validated with\n";
echo "        the same rules as the admin form (a malformed _mb_sector_slug in meta_input blocks publish)\n";
echo "      * mb_yeterlilik: publish is blocked with zero mb_sektor terms, allowed with >=1 REAL term;\n";
echo "        a bogus/nonexistent term_id in tax_input does not count as ready\n";
echo "      * mb_ucret: publish is blocked if ANY submitted non-blank price-option row is invalid, not\n";
echo "        just when all of them are — one bad row among otherwise-valid ones must still block\n";
echo "  - public/class-catalog-service.php (Faz 5) in its entirety — every public method calls WP_Query,\n";
echo "    get_terms(), get_term_link(), current_time(), get_post_meta()/get_option(), none of which exist\n";
echo "    in this standalone bootstrap. Contract to cover once a WP test environment exists:\n";
echo "      * get_qualification_results(): sektör+seviye+kelime birlikte filtreleme; draft/private/trash\n";
echo "        kayıtların sonuçtan çıkması; geçersiz mb_sector slug -> sıfır sonuç (asla filtresiz tüm liste);\n";
echo "        mb_priced=1 iken yalnız gerçekten aktif ücreti olan yeterliliklerin dönmesi\n";
echo "      * get_active_fee_results()/get_active_fees_for_qualification(): aktif dönem boş/yanlış/doğru;\n";
echo "        valid_from/valid_until bugün sınırları (inclusive); boş/geçersiz _mb_price_options'ın gizlenmesi;\n";
echo "        yalnız _mb_qualification_id ile eşleşme (ad/kod benzerliği asla fallback olmaz); 19 kodsuz\n";
echo "        kaydın listeden düşmemesi\n";
echo "      * present_fee(): kaynak attachment gerçekten var olmadığında source_url boş dönmesi; belge\n";
echo "        basım ücreti 0 iken certificate_print_fee_display boş dönmesi (yanlış \"0 TL\" metni yok)\n";
echo "  - admin/class-list-filters.php'nin meta-aware admin araması ve dropdown filtreleri — needs a real\n";
echo "    WP_Query/get_posts() ve admin ekranı; must specifically re-verify: filtre yalnız kendi post\n";
echo "    type'ına uygulanıyor, başka bir post type listesini etkilemiyor\n";
echo "  - Faz 5 Düzeltme ve Kabul additions — all need a real WordPress test environment:\n";
echo "      * MaviBelge_Core_Catalog_Service::resolve_sector_filter() — a format-valid but nonexistent\n";
echo "        sector slug must yield zero results in BOTH get_qualification_results() and\n";
echo "        get_active_fee_results(), never a silently-ignored filter\n";
echo "      * get_active_fee_count() vs. get_active_fee_qualification_ids() — must specifically verify a\n";
echo "        fully-valid active fee with _mb_qualification_id=0 is counted by the former (used by the\n";
echo "        admin settings warning) but NOT by the latter (used by the mb_priced archive filter)\n";
echo "      * public/class-visibility-guard.php::maybe_block_passive_qualification() — a published-but-\n";
echo "        passive mb_yeterlilik's real single URL must 404 for an anonymous visitor, but still render\n";
echo "        normally for a user who can edit_post that record (author/editor/admin preview)\n";
echo "      * present_fee()'s qualification_permalink must be empty for a fee linked to a passive\n";
echo "        qualification (same is_public_qualification() decision as the guard above)\n";
echo "      * canonical price-options validation end-to-end: a stored _mb_ucret with a label-less price\n";
echo "        option must be completely hidden from get_active_fee_results()/get_active_fees_for_qualification(),\n";
echo "        not partially shown\n";
echo "  - Faz 5 Son Kapanış Düzeltmesi additions — need a real WordPress test environment:\n";
echo "      * a ücret record with a REAL, existing mb_sektor slug is visible in\n";
echo "        get_active_fee_results()/get_active_fee_count() with sector_name = the real term name\n";
echo "      * a ücret record with an unknown/empty/malformed-format _mb_sector_slug is COMPLETELY\n";
echo "        invisible — not in get_active_fee_results(), not counted by get_active_fee_count(), and\n";
echo "        never surfaces its raw slug text anywhere\n";
echo "      * sector_slug_to_name_map() is built from a real get_terms() call exactly once per request\n";
echo "        (not once per ücret row) — verify via a query-count assertion in a real WP test\n";
echo "      * normalize_price_options()'s stricter units/sort_order behavior end-to-end through the real\n";
echo "        admin save path (admin/class-meta-boxes.php::save_price_options()) and the real\n";
echo "        register_post_meta sanitize_callback path — both still only ever accept an all-or-nothing list\n";
echo "  - Faz 6B2 Düzeltme ve Kabul — MaviBelge_Core_Import_WordPress_Target_Repository's actual WordPress\n";
echo "    behavior remains untestable without a real WP environment (only its 3 pure static converters —\n";
echo "    strict_int/strict_nonneg_int_or_empty_zero/strict_bool — are exercised above). Contract to cover\n";
echo "    once a WP test environment exists:\n";
echo "      * discover_candidates()'s cross-type marker search (§2.1 karar matrisi) against REAL get_terms()/\n";
echo "        get_posts() results: a marker planted only on the expected type resolves cleanly; a marker\n";
echo "        planted on the WRONG content type (e.g. a qualification:.. marker on an mb_ucret post) must\n";
echo "        actually produce target_found=true+target_type_matches=false (conflict_wrong_target_type),\n";
echo "        not silently fall through to target_found=false as it did before this fix; a marker duplicated\n";
echo "        across a correct-type AND a wrong-type record must be duplicate_targets=true, not a silent\n";
echo "        pick of the correct one; a genuine get_terms()/get_posts() WP_Error must produce the\n";
echo "        target_found='query_error' fail-closed signal, never target_found=false (which would look like\n";
echo "        a legitimate create candidate)\n";
echo "      * current_sector_fields()/current_qualification_fields()/current_fee_fields() against REAL,\n";
echo "        deliberately-corrupted term/post meta (non-numeric _mb_image_attachment_id, 'false' string in\n";
echo "        _mb_vat_included, non-array _mb_price_options, zero or 2+ mb_sektor terms on a qualification\n";
echo "        post) — each must make the whole current_managed_fields null -> invalid_target_state, not a\n";
echo "        silently-defaulted 0/false value that a real WordPress meta table can actually contain\n";
echo "      * resolve_sector_image_attachment_id()'s attachment post_type check against a real non-attachment\n";
echo "        post ID stored in _mb_image_attachment_id\n";
echo "  - class-import-cli-command.php — needs a real WP-CLI environment: WP_CLI\\Utils\\format_items()\n";
echo "    actually rendering entry rows (the class_exists('WP_CLI\\Utils') bug fixed this round made this\n";
echo "    silently never happen before); the fallback text-table path when format_items is absent; --format\n";
echo "    with an unknown value producing WP_CLI::error() + non-zero exit\n";
echo "  - admin/class-import-dry-run-page.php — needs a real wp-admin request: missing/array/invalid nonce\n";
echo "    on POST now hard-rejects via wp_die() instead of silently re-showing the GET form (this round's\n";
echo "    fix); mb_paged with '1e2'/'1.5'/negative/array input falling back to page 1 instead of being\n";
echo "    accepted by the old is_numeric() check; a non-manage_options user seeing wp_die() before any nonce\n";
echo "    check runs\n";
echo "  - NOT (Runtime Doğrulama turu): bu standalone koşucu aşağıdaki Faz 6B2 WordPress maddelerini hâlâ\n";
echo "    KAPSAMAZ; ancak repository keşfi/bozuk meta/CLI/admin nonce-mb_paged maddeleri ayrı\n";
echo "    wordpress-site/tools/runtime-test/ harness'i ile PHP 7.3.33 + WordPress 6.9.9 izole ortamında\n";
echo "    çalıştırıldı — bkz. raporlar/veri-aktarim-raporlari/faz6b2-php-wordpress-runtime-dogrulama.md.\n";
echo "  - Faz 6B3 Önkoşul ve Yazma Güvenliği — bu standalone koşucu yalnız SAF parçaları kapsar\n";
echo "    (kanonik kuruş, marker sınıflandırıcı, doğal anahtar ayrıştırma/durum, karar matrisi,\n";
echo "    atomik yazma yükü, apply uygunluğu/TOCTOU/rollback şekli). Kayıtlı meta yolunda x100\n";
echo "    olmaması, geçersiz girişin önceki değeri ezmemesi, yanlış önekli marker reddi, gerçek\n";
echo "    get_terms/get_posts doğal anahtar preflight'ı ve 200 manifest yükünün sanitize\n";
echo "    idempotansı wordpress-site/tools/runtime-test/scripts/{write-safety-test,\n";
echo "    fixtures-natural-key}.php ile PHP 7.3.33 + WordPress 6.9.9 izole ortamında çalıştırıldı —\n";
echo "    bkz. raporlar/veri-aktarim-raporlari/faz6b3-yazma-guvenligi-sozlesmesi.md. Doğal anahtar\n";
echo "    sorgusunun GERÇEK bir WP_Error döndürdüğü durum runtime'da üretilemedi (yalnız saf test).\n";
echo "  - Faz 6B2 Son Kapanış Düzeltmesi — WordPress runtime bekliyor (yalnız saf strict_string/\n";
echo "    parse_canonical_decimal_int yolu ve *_fields_from_raw()/marker_raw_matches()/\n";
echo "    normalize_last_applied_hash_raw() kurucuları yukarıda yazıldı; php yoksa ÇALIŞTIRILMADI):\n";
echo "      * gerçek get_term_meta('_mb_icon_key') / get_post_meta('_mb_source_name') serileştirilmiş\n";
echo "        dizi/nesne döndürdüğünde find_target_by_source_key() sonucunun normalize_target_lookup()'ta\n";
echo "        invalid_target_state'e düştüğü ve PHP 'Array to string conversion' uyarısı üretmediği\n";
echo "      * gerçek _mb_import_source_key / _mb_last_applied_hash meta'sı dizi/nesne iken\n";
echo "        target_found='invalid_meta' sinyalinin üretildiği (legacy_missing_hash'e DÜŞMEDİĞİ)\n";
echo "      * gerçek wp_get_post_terms()/get_term_by() ID'lerinin int döndüğü varsayımının WordPress\n";
echo "        sürümünde doğrulanması (positive_int_or_null() string/bozuk ID'yi reddeder)\n";
echo "      * 64-bit ve (varsa) 32-bit PHP derlemesinde PHP_INT_MAX/PHP_INT_MIN sınır testlerinin\n";
echo "        gerçek yorumlayıcıda geçtiği; (int)'-9223372036854775808' === PHP_INT_MIN round-trip'i\n";
echo "  - Faz 6B3 WordPress uygulamaları (MaviBelge_Core_Import_WordPress_Target_Writer,\n";
echo "    _Wpdb_Transaction, _Wpdb_Run_Store, _WP_Audit_Sink): burada yalnız YÜKLENİR (arayüz uyumu) ve\n";
echo "    saf stored_equals()/MANAGED_POST_META sınanır; gerçek davranış (transaction geri alma, readback,\n";
echo "    çöp kutusu, terim silme, dbDelta, GET_LOCK, audit) tools/runtime-test/apply-cycle.sh ile izole\n";
echo "    WordPress 6.9.9'da sınanır. Apply/rollback servisleri burada sahte dünyayla uçtan uca sınanır.\n";

exit( $failures > 0 ? 1 : 0 );
