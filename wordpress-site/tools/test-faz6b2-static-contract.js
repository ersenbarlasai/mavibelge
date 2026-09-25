'use strict';
/**
 * Faz 6B2 — statik kaynak-metni sözleşme testi (PHP çalıştırılamadığı için
 * — bu ortamda `php` yok — bu, WordPress'i derlemeden yapılabilen en güçlü
 * kontroldür). Yalnız KAYNAK METNİ üzerinde regex/string arar; bir PHP
 * ayrıştırıcısı veya WordPress runtime testi DEĞİLDİR — bu, teslim
 * raporunda ayrıca belirtilir.
 *
 * Run: node wordpress-site/tools/test-faz6b2-static-contract.js
 */

const fs = require('fs');
const path = require('path');

const PLUGIN_ROOT = path.resolve(__dirname, '..', 'wp-content', 'plugins', 'mavibelge-core');

const FILES = {
	bootstrap: path.join(PLUGIN_ROOT, 'mavibelge-core.php'),
	loader: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-manifest-loader.php'),
	repository: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-wordpress-target-repository.php'),
	service: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-dry-run-service.php'),
	cli: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-cli-command.php'),
	admin: path.join(PLUGIN_ROOT, 'admin', 'class-import-dry-run-page.php'),
	interfaceFile: path.join(PLUGIN_ROOT, 'includes', 'import', 'interface-import-target-repository.php'),
	planner: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-dry-run-planner.php'),
	tests: path.join(PLUGIN_ROOT, 'tests', 'run.php'),
	// Faz 6B3 Önkoşul ve Yazma Güvenliği
	validator: path.join(PLUGIN_ROOT, 'includes', 'class-validator.php'),
	fieldRepo: path.join(PLUGIN_ROOT, 'includes', 'class-field-repository.php'),
	metaSchema: path.join(PLUGIN_ROOT, 'includes', 'class-meta-schema.php'),
	taxonomies: path.join(PLUGIN_ROOT, 'includes', 'class-taxonomies.php'),
	metaBoxes: path.join(PLUGIN_ROOT, 'admin', 'class-meta-boxes.php'),
	decision: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-decision.php'),
	recordValidator: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-record-validator.php'),
	payload: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-write-payload.php'),
	eligibility: path.join(PLUGIN_ROOT, 'includes', 'import', 'class-import-apply-eligibility.php'),
};

/** PHP /* * / block ve // satır yorumlarını kaba biçimde temizler — bu dosya
 * içinde bir yorumun İÇİNDE bir yasak çağrıyı/bayrağı ANLATMAK (ör. "set_transient()
 * bu dosyada YOKTUR") ile GERÇEKTEN o çağrıyı yapmayı ayırt etmek için. String
 * literal içindeki `//`'yi de yanlışlıkla yorum sayabilir (bu dosyada hiçbir
 * gerçek kod satırında `//` içeren bir string literal yok) — bir PHP
 * ayrıştırıcısı DEĞİLDİR, yalnız bu taramanın kendi kaba sadeleştirmesidir. */
function stripPhpComments(code) {
	return code
		.replace(/\/\*[\s\S]*?\*\//g, '')
		.split('\n')
		.map((line) => {
			const idx = line.indexOf('//');
			return idx === -1 ? line : line.slice(0, idx);
		})
		.join('\n');
}

const src = {};
const srcCode = {}; // Yorumları temizlenmiş kod — YALNIZ gerçek çağrı/bayrak taramaları için.
for (const key of Object.keys(FILES)) {
	src[key] = fs.readFileSync(FILES[key], 'utf8');
	srcCode[key] = stripPhpComments(src[key]);
}

let failures = 0;
let total = 0;

function test(label, condition) {
	total++;
	if (condition) {
		process.stdout.write('PASS  ' + label + '\n');
	} else {
		failures++;
		process.stdout.write('FAIL  ' + label + '\n');
	}
}

// --- §10 — kesin yazma yasağı: 6B2'nin YENİ dosyalarının hiçbirinde
// yazma API'si çağrılmaz. ---
const FORBIDDEN_WRITE_CALLS = [
	'wp_insert_post', 'wp_update_post', 'wp_delete_post',
	'wp_insert_term', 'wp_update_term', 'wp_delete_term',
	'add_post_meta', 'update_post_meta', 'delete_post_meta',
	'add_term_meta', 'update_term_meta', 'delete_term_meta',
	'add_option', 'update_option', 'delete_option',
	'set_transient',
];
const WRITE_SCAN_FILES = ['loader', 'repository', 'service', 'cli', 'admin', 'payload', 'eligibility', 'decision', 'recordValidator', 'planner'];
for (const fileKey of WRITE_SCAN_FILES) {
	for (const call of FORBIDDEN_WRITE_CALLS) {
		test(
			`yazma yasağı: ${fileKey} dosyasında "${call}(" GERÇEK ÇAĞRISI YOK (yorumlar hariç)`,
			srcCode[fileKey].indexOf(call + '(') === -1
		);
	}
	test(
		`yazma yasağı: ${fileKey} dosyasında "$wpdb->insert/update/delete/query/replace" GERÇEK ÇAĞRISI YOK (yorumlar hariç)`,
		!/\$wpdb\s*->\s*(insert|update|delete|query|replace)\s*\(/.test(srcCode[fileKey])
	);
}

// --- CLI'de --write/--commit/--force (ve conflict atlama) bayrağı YOK (yorumlar hariç —
// docblock'un "bu bayrak YOKTUR" diye AÇIKLAMASI gerçek bir tanım SAYILMAZ).
// Faz 6B3: `--apply` artık TANIMLI ama varsayılan KAPALI ve kapılı — aşağıdaki
// "Faz 6B3 CLI" testleri kapıları ayrıca sabitler. ---
for (const flag of ['--write', '--commit', '--force', '--skip-conflicts', '--partial']) {
	test(
		`CLI komutu: "${flag}" seçeneği GERÇEKTEN tanımlı DEĞİL (yorumlar hariç)`,
		srcCode.cli.indexOf(flag) === -1 && src.cli.indexOf('[' + flag) === -1
	);
}
test(
	'Admin ekranı: "Apply"/"Uygula"/"İçe Aktar" düğmesi metni YOK (yalnız "Salt Okunur Dry-Run Çalıştır" var)',
	!/Uygula|İçe Aktar(?!ım)/.test(src.admin) && src.admin.indexOf('Salt Okunur Dry-Run Çalıştır') !== -1
);

// --- §5.3 — üç dependency üst anahtarı hem servis hem planlayıcı kaynağında GEÇER ---
for (const key of ['sector_term_ids', 'sector_image_attachment_ids', 'qualification_post_ids']) {
	test(`Servis kaynağı: "${key}" anahtarı geçiyor`, src.service.indexOf(key) !== -1);
}

// --- §5.2 — resolver'lar "?array" dönüş tipi taşımaya devam ediyor (arayüz + gerçek uygulama) ---
for (const method of ['resolve_sector_term_id', 'resolve_qualification_post_id', 'resolve_sector_image_attachment_id']) {
	const re = new RegExp('function\\s+' + method + '\\s*\\([^)]*\\)\\s*:\\s*\\?array');
	test(`Arayüz: ${method}() "?array" dönüş tipi taşıyor`, re.test(src.interfaceFile));
	test(`Gerçek repository: ${method}() "?array" dönüş tipi taşıyor`, re.test(src.repository));
}

// --- Eski, düzeltilmiş "slug => int" docblock kalıntısı yok ---
test(
	'Planlayıcı docblock: eski "sector_slug => int term_id" (dürüst olmayan eski şekil) İFADESİ YOK',
	src.planner.indexOf('sector_slug => int term_id') === -1
);
test(
	'Planlayıcı docblock: güncel typed dependency şekli ("type_verified: true") AÇIKÇA belgeleniyor',
	src.planner.indexOf('type_verified: true') !== -1
);

// --- §3 — WP-CLI ve admin AYNI TEK servis sınıfını çağırıyor, kendi karar mantığını YAZMIYOR ---
test(
	'CLI komutu: MaviBelge_Core_Import_Dry_Run_Service örnekleniyor (kendi karar mantığı YOK)',
	src.cli.indexOf('new MaviBelge_Core_Import_Dry_Run_Service(') !== -1
);
test(
	'Admin ekranı: MaviBelge_Core_Import_Dry_Run_Service örnekleniyor (kendi karar mantığı YOK)',
	src.admin.indexOf('new MaviBelge_Core_Import_Dry_Run_Service(') !== -1
);
test(
	'CLI komutu: MaviBelge_Core_Import_Dry_Run_Planner::plan() DOĞRUDAN çağırmıyor (yalnız servis üzerinden)',
	src.cli.indexOf('Dry_Run_Planner::plan(') === -1
);
test(
	'Admin ekranı: MaviBelge_Core_Import_Dry_Run_Planner::plan() DOĞRUDAN çağırmıyor (yalnız servis üzerinden)',
	src.admin.indexOf('Dry_Run_Planner::plan(') === -1
);

// --- §4 — manifest dosya listesi kapalı: yalnız 3 sabit dosya adı ---
test(
	'Loader: FILES sabitinde tam 3 giriş var',
	(src.loader.match(/=>\s*'(sectors|qualifications|fees)\.manifest\.json'/g) || []).length === 3
);
test(
	'Loader: istemciden gelen bir yol parametresi ALMIYOR (load_all() imzası yalnız isteğe bağlı $baseDirOverride — test amaçlı, üretim çağıranları geçmez)',
	/function load_all\(\s*\$baseDirOverride\s*=\s*null\s*\)/.test(src.loader)
);

// --- §8 — admin ekranı manage_options + nonce + POST zorunluluğu ---
test('Admin ekranı: manage_options yetkisi kontrol ediliyor', src.admin.indexOf("current_user_can( self::CAPABILITY )") !== -1 && src.admin.indexOf("const CAPABILITY  = 'manage_options'") !== -1);
test('Admin ekranı: wp_verify_nonce() çağrısı var', src.admin.indexOf('wp_verify_nonce(') !== -1);
test('Admin ekranı: yalnız POST isteğinde çalışıyor ("REQUEST_METHOD" kontrolü var)', src.admin.indexOf("REQUEST_METHOD") !== -1);
test('Admin ekranı: esc_html/esc_attr kullanıyor (çıktı kaçışı)', src.admin.indexOf('esc_html(') !== -1 && src.admin.indexOf('esc_attr(') !== -1);

// --- Bootstrap: yükleme sırası — arayüz -> saf sınıflar -> loader/repository/service -> koşullu CLI ---
const bootstrapOrder = [
	'interface-import-target-repository.php',
	'class-import-hash.php',
	'class-import-managed-fields.php',
	'class-import-decision.php',
	'class-import-record-validator.php',
	'class-import-dry-run-planner.php',
	'class-import-manifest-loader.php',
	'class-import-wordpress-target-repository.php',
	'class-import-dry-run-service.php',
];
let lastIndex = -1;
let orderOk = true;
for (const file of bootstrapOrder) {
	const idx = src.bootstrap.indexOf(file);
	if (idx === -1 || idx < lastIndex) {
		orderOk = false;
		break;
	}
	lastIndex = idx;
}
test('mavibelge-core.php: Faz 6B1/6B2 dosyaları BELGELENEN sırayla require ediliyor', orderOk);
test('mavibelge-core.php: CLI komutu yalnız "defined( \'WP_CLI\' ) && WP_CLI" koşuluyla yükleniyor', /defined\(\s*'WP_CLI'\s*\)\s*&&\s*WP_CLI/.test(src.bootstrap));

/* ================================================================
 * Düzeltme ve Kabul (Codex bağımsız incelemesinin 6 bloklayıcı bulgusu)
 * — §4.2'nin istediği REGRESYON sabitleri. Bunlar yalnız anahtar
 * kelime varlığı DEĞİL, kaynak-düzeyinde somut sözleşme gerçeklerini
 * doğrular (ör. "repository X'i sorguluyor" değil, "repository X VE Y
 * VE Z'yi AYNI ANDA sorguluyor").
 * ================================================================ */

// --- §2.1 — repository üç import hedef alanının TAMAMINI source-key
// keşfinde sorguluyor (yalnız BEKLENEN türü değil). ---
test(
	'Repository: discover_candidates() mb_sektor/mb_yeterlilik/mb_ucret ÜÇÜNÜ DE aynı meta_key ile sorguluyor',
	srcCode.repository.indexOf("'taxonomy'   => 'mb_sektor'") !== -1
		&& srcCode.repository.indexOf("'post_type'      => $postType") !== -1
		&& srcCode.repository.indexOf("TYPE_QUALIFICATION => 'mb_yeterlilik'") !== -1
		&& srcCode.repository.indexOf("TYPE_FEE           => 'mb_ucret'") !== -1
);
test(
	'Repository: wrong-type sonucu (target_type_matches=false) GERÇEK KAYNAK KODUNDA üretilebiliyor (yalnız fake repository testinde değil)',
	srcCode.repository.indexOf('wrong_type_result') !== -1
		&& /'target_type_matches'\s*=>\s*false/.test(srcCode.repository)
);
test(
	'Repository: 2+ aday (candidate) TÜRDEN BAĞIMSIZ duplicate_targets=true üretiyor',
	/\$count\s*>\s*1/.test(srcCode.repository) && srcCode.repository.indexOf("'duplicate_targets' => true") !== -1
);
test(
	'Repository: discover_candidates() sorgu hatasında (WP_Error/beklenmeyen şekil) fail-closed "query_error" sinyali döner — "hedef yok"tan AYRI',
	srcCode.repository.indexOf("'target_found' => 'query_error'") !== -1
);

// --- §2.2 — malformed meta artık sessizce geçerli varsayılana dönüşmüyor. ---
test(
	'Repository: current_fee_fields() içinde HAM "(bool) get_post_meta(" cast\'i YOK (kök neden kapatıldı)',
	!/\(bool\)\s*get_post_meta/.test(srcCode.repository)
);
test(
	'Repository: current_*_fields() metotları strict_* dönüştürücülerin "ok" bayrağını kontrol ediyor (sessiz varsayılan YOK)',
	(srcCode.repository.match(/if \( ! \$\w+Conv\['ok'\] \)/g) || []).length >= 5
);
test(
	'Repository: strict_nonneg_int_or_empty_zero() negatif/dizi/kısmi-sayısal girdide "value" => 0 İLE dönmüyor (yalnız ok=false)',
	!/return array\( 'ok' => false, 'value' => 0 \)/.test(srcCode.repository)
);
test(
	'tests/run.php: eski "to_int(\'abc\') === 0" / "to_nonneg_int(...) === 0" iddiası YOK (kök nedenin YANLIŞ yönde olduğu kabul edildi, testler strict_* dönüştürücülere geçti)',
	srcCode.tests.indexOf('MaviBelge_Core_Import_WordPress_Target_Repository::to_int(') === -1
		&& srcCode.tests.indexOf('MaviBelge_Core_Import_WordPress_Target_Repository::to_nonneg_int(') === -1
);

// --- §2.3 — interface get_diagnostics()'i taşıyor, method_exists() incir yaprağı yok. ---
test(
	'Arayüz: get_diagnostics() metodu TANIMLI (servisin bel kırılan çağrısı artık arayüzde var)',
	/function\s+get_diagnostics\s*\(/.test(srcCode.interfaceFile)
);
test(
	'Servis: repository->get_diagnostics() çağrısı method_exists() İLE korunmuyor (artık arayüz garantisi)',
	!/method_exists\(\s*\$this->repository/.test(srcCode.service)
);
test(
	'Arayüz docblock: eski "No class in this repository implements this interface yet" iddiası YOK',
	src.interfaceFile.indexOf('No class in this repository implements this interface yet') === -1
);

// --- §2.4 — WP-CLI class_exists('WP_CLI\Utils') kök nedeni kapatıldı. ---
test(
	'CLI komutu: "class_exists( \'WP_CLI\\\\Utils\' )" ARTIK KULLANILMIYOR (her zaman false dönen kök neden)',
	srcCode.cli.indexOf("class_exists( 'WP_CLI") === -1 && srcCode.cli.indexOf('class_exists(\'WP_CLI') === -1
);
test(
	'CLI komutu: doğru "function_exists( \'WP_CLI\\\\Utils\\\\format_items\' )" kontrolü VAR',
	srcCode.cli.indexOf("function_exists( 'WP_CLI\\\\Utils\\\\format_items' )") !== -1
);
test(
	'CLI komutu: bilinmeyen --format değeri WP_CLI::error() ile fail-closed reddediliyor',
	srcCode.cli.indexOf('WP_CLI::error(') !== -1 && /in_array\(\s*\$format,\s*array\(\s*'table',\s*'json'/.test(srcCode.cli)
);
test(
	'CLI komutu: format_items() yoksa satırlar SESSİZCE ATLANMIYOR (fallback fonksiyonu çağrılıyor)',
	srcCode.cli.indexOf('print_fallback_rows(') !== -1
);

// --- §2.5 — loader: yol gizliliği + notes/counts/source ZORUNLU + kapalı şekil. ---
test(
	'Loader: dizin-bulunamadı hata metni $baseDir/baseReal/real DEĞİŞKENİNİ birleştirmiyor (mutlak yol sızmıyor)',
	!/dizini bulunamadı[\s\S]{0,40}\{\$baseDir\}/.test(src.loader)
);
test(
	'Loader: dosyaya özel alan (notes/counts) artık YALNIZ izin verilen değil ZORUNLU (ENVELOPE_EXTRA_REQUIRED_KEY + array_key_exists kontrolü)',
	src.loader.indexOf('ENVELOPE_EXTRA_REQUIRED_KEY') !== -1 && srcCode.loader.indexOf('eksik (bu dosya türünde zorunlu)') !== -1
);
test(
	'Loader: source yalnız {file, sha256} KAPALI şekli taşıyor (validate_source_object + extra-key reddi)',
	src.loader.indexOf('validate_source_object') !== -1 && srcCode.loader.indexOf("array( 'file', 'sha256' )") !== -1
);
test(
	'Loader: source.file beklenen SABİT Faz 6A kaynak yoluyla eşleştiriliyor (EXPECTED_SOURCE_FILE)',
	src.loader.indexOf('EXPECTED_SOURCE_FILE') !== -1
);
test(
	'Loader: kayıt source\'u zarf source\'uyla (provenance) çapraz kontrol ediliyor',
	srcCode.loader.indexOf('provenance drift') !== -1
);
test(
	'Loader: fee counts tam 8 anahtarlı kapalı şema taşıyor (FEE_COUNTS_KEYS)',
	src.loader.indexOf('FEE_COUNTS_KEYS') !== -1 && (src.loader.match(/'total'|'priceOptionsTotal'|'pricingSingle'|'pricingMulti'|'multiOptionsTotal'|'feesWithCode'|'feesWithoutCode'|'linkedCount'/g) || []).length >= 8
);

// --- §2.6 — admin: nonce/mb_paged fail-closed, scalar kontrolü unslash'ten ÖNCE. ---
test(
	'Admin ekranı: POST + eksik/array/geçersiz nonce artık wp_die() ile FAIL-CLOSED reddediliyor (sessizce GET görünümüne düşmüyor)',
	srcCode.admin.indexOf('wp_die(') !== -1 && /is_string\(\s*\$_POST\[\s*self::NONCE_NAME\s*\]\s*\)/.test(srcCode.admin)
);
test(
	'Admin ekranı: mb_paged is_numeric() İLE DEĞİL, kesin dize sonlu "^[1-9][0-9]*\\z" deseniyle doğrulanıyor; satır-sonu-toleranslı "$" deseni YOK',
	srcCode.admin.indexOf('is_numeric(') === -1
		&& srcCode.admin.indexOf("'/^[1-9][0-9]*\\z/'") !== -1
		&& srcCode.admin.indexOf("'/^[1-9][0-9]*$/'") === -1
);
test(
	'Admin ekranı: yetki kontrolü (current_user_can) wp_verify_nonce() çağrısından ÖNCE çalışıyor',
	src.admin.indexOf('current_user_can( self::CAPABILITY )') < src.admin.indexOf('wp_verify_nonce(')
);

/* ================================================================
 * Son Kapanış Düzeltmesi — string meta fail-closed, integer taşması ve
 * durum belgesi kapanışı. Mümkün olduğunca GERÇEK fonksiyon gövdeleri
 * (yorumlar temizlenmiş, süslü parantez eşleştirmeli) incelenir.
 * ================================================================ */

/** Yorumu temizlenmiş PHP kaynağında `function NAME(` gövdesini döndürür (bulunamazsa null). */
function phpFunctionBody(code, name) {
	const re = new RegExp('function\\s+' + name + '\\s*\\(');
	const m = re.exec(code);
	if (!m) {
		return null;
	}
	const open = code.indexOf('{', m.index);
	if (open === -1) {
		return null;
	}
	let depth = 0;
	for (let i = open; i < code.length; i++) {
		if (code[i] === '{') {
			depth++;
		} else if (code[i] === '}') {
			depth--;
			if (depth === 0) {
				return code.slice(open, i + 1);
			}
		}
	}
	return null;
}

/** Markdown `## Başlık` bölümünü bir sonraki `## ` başlığına kadar döndürür. */
function mdSection(md, heading) {
	const start = md.indexOf('\n## ' + heading);
	if (start === -1) {
		return null;
	}
	const next = md.indexOf('\n## ', start + 4);
	return next === -1 ? md.slice(start) : md.slice(start, next);
}

const REPO_ROOT = path.resolve(__dirname, '..', '..');
const statusDoc = fs.readFileSync(path.join(REPO_ROOT, 'raporlar', 'proje-durumu.md'), 'utf8');
const rootReadme = fs.readFileSync(path.join(REPO_ROOT, 'README.md'), 'utf8');

const repoCode = srcCode.repository;
const bodies = {};
for (const fn of [
	'strict_string', 'strict_int', 'strict_nonneg_int_or_empty_zero', 'parse_canonical_decimal_int',
	'sector_fields_from_raw', 'qualification_fields_from_raw', 'fee_fields_from_raw',
	'current_qualification_fields', 'current_fee_fields', 'build_sector_result', 'build_post_result',
	'wrong_type_result', 'marker_raw_matches', 'normalize_last_applied_hash_raw',
]) {
	bodies[fn] = phpFunctionBody(repoCode, fn);
}

// 1-2 — doğrudan (string) get_*_meta(...) kalmadı.
test('Son Kapanış: repository\'de "(string) get_post_meta(" YOK', !/\(string\)\s*get_post_meta\s*\(/.test(repoCode));
test('Son Kapanış: repository\'de "(string) get_term_meta(" YOK', !/\(string\)\s*get_term_meta\s*\(/.test(repoCode));
test(
	'Son Kapanış: repository\'de çekirdek alan/ID cast\'i ("(string) $term->", "(string) $post->", "(int) $term->", "(int) $ids[", "(int) $id") YOK',
	!/\((string|int)\)\s*\$(term|post)\s*->/.test(repoCode) && !/\(int\)\s*\$ids\s*\[/.test(repoCode) && !/\(int\)\s*\$id\b/.test(repoCode)
);

// 3-4 — strict string helper var ve cast etmiyor.
test(
	'Son Kapanış: public static strict_string() tanımlı',
	/public\s+static\s+function\s+strict_string\s*\(/.test(repoCode) && bodies.strict_string !== null
);
test(
	'Son Kapanış: strict_string() gövdesi yalnız is_string() ile kabul ediyor; (string)/strval/settype/sanitize/json_encode YOK',
	bodies.strict_string !== null
		&& bodies.strict_string.indexOf('is_string( $raw )') !== -1
		&& !/\(string\)|strval\s*\(|settype\s*\(|sanitize_|json_encode\s*\(|print_r\s*\(/.test(bodies.strict_string)
);

// 5-6 — tek ayrıştırıcı + sınır kontrolü + kontrolsüz (int) cast yasağı.
// Faz 6B3 Önkoşul: algoritma kanonik kuruş kuralıyla paylaşılmak üzere Validator'a taşındı;
// repository yalnız ona devreder (aşağıdaki kontrol).
const parseBody = phpFunctionBody(srcCode.validator, 'parse_canonical_decimal_int') || '';
test(
	'Son Kapanış: parse_canonical_decimal_int() PHP_INT_MAX VE PHP_INT_MIN sınırlarını cast\'ten ÖNCE kontrol ediyor',
	parseBody.indexOf('PHP_INT_MAX') !== -1 && parseBody.indexOf('PHP_INT_MIN') !== -1
		&& parseBody.indexOf('(int) $raw') !== -1
		&& parseBody.indexOf('PHP_INT_MAX') < parseBody.indexOf('(int) $raw')
		&& parseBody.indexOf('strcmp(') !== -1 && parseBody.indexOf('strcmp(') < parseBody.indexOf('(int) $raw')
);
test(
	'Son Kapanış: parse_canonical_decimal_int() float kullanmıyor, \\z çapası + round-trip kontrolü taşıyor',
	!/\(float\)|floatval\s*\(|\(double\)|intval\s*\(|is_numeric\s*\(/.test(parseBody)
		&& parseBody.indexOf('\\z/') !== -1
		&& parseBody.indexOf('(string) $value !== $raw') !== -1
);
test(
	'Son Kapanış: strict_int() ve strict_nonneg_int_or_empty_zero() gövdelerinde doğrudan (int) cast YOK; ikisi de TEK parse_canonical_decimal_int()\'i çağırıyor',
	bodies.strict_int !== null && bodies.strict_nonneg_int_or_empty_zero !== null
		&& !/\(int\)|intval\s*\(/.test(bodies.strict_int) && !/\(int\)|intval\s*\(/.test(bodies.strict_nonneg_int_or_empty_zero)
		&& bodies.strict_int.indexOf('self::parse_canonical_decimal_int(') !== -1
		&& bodies.strict_nonneg_int_or_empty_zero.indexOf('self::parse_canonical_decimal_int(') !== -1
);
test(
	'Son Kapanış (6B3 güncel): repository kodunda (int) cast YOK; parse_canonical_decimal_int() yalnız Validator\'daki TEK algoritmaya devrediyor',
	(repoCode.match(/\(int\)/g) || []).length === 0
		&& (bodies.parse_canonical_decimal_int || '').indexOf('MaviBelge_Core_Validator::parse_canonical_decimal_int( $raw )') !== -1
		&& parseBody.indexOf('(int) $raw') !== -1
);

// Kurucu/okuyucu gövdeleri — string alanları gerçekten katı yoldan geçiyor.
test(
	'Son Kapanış: sector_fields_from_raw() icon_key\'i strict_string_fields() listesinde doğruluyor',
	bodies.sector_fields_from_raw !== null && /strict_string_fields\([\s\S]*'icon_key'/.test(bodies.sector_fields_from_raw)
);
test(
	'Son Kapanış: fee_fields_from_raw() source_name dahil 10 string alanı strict_string_fields() ile doğruluyor',
	bodies.fee_fields_from_raw !== null && ['title', 'profession_name', 'sector_slug', 'qualification_code', 'pricing_type', 'source_name', 'tariff_period', 'record_status', 'valid_from', 'valid_until']
		.every((k) => new RegExp("strict_string_fields\\([\\s\\S]*'" + k + "'[\\s\\S]*\\);").test(bodies.fee_fields_from_raw))
);
test(
	'Son Kapanış: qualification_fields_from_raw() myk_code/revision/record_status/title\'ı strict_string_fields() ile doğruluyor',
	bodies.qualification_fields_from_raw !== null && ['title', 'myk_code', 'revision', 'record_status']
		.every((k) => new RegExp("strict_string_fields\\([^;]*'" + k + "'").test(bodies.qualification_fields_from_raw))
);
test(
	'Son Kapanış: current_qualification_fields()/current_fee_fields() yalnız ham değer toplayıp saf kurucuya veriyor (cast YOK)',
	bodies.current_qualification_fields !== null && bodies.current_fee_fields !== null
		&& bodies.current_qualification_fields.indexOf('self::qualification_fields_from_raw(') !== -1
		&& bodies.current_fee_fields.indexOf('self::fee_fields_from_raw(') !== -1
		&& !/\((string|int|bool)\)/.test(bodies.current_qualification_fields + bodies.current_fee_fields)
);
test(
	'Son Kapanış: build_sector_result()/build_post_result()/wrong_type_result() marker\'ı marker_raw_matches() ile, hash\'i normalize_last_applied_hash_raw() ile doğruluyor',
	[bodies.build_sector_result, bodies.build_post_result].every((b) => b !== null
		&& b.indexOf('self::marker_raw_matches(') !== -1 && b.indexOf('self::normalize_last_applied_hash_raw(') !== -1
		&& b.indexOf('self::invalid_meta_result()') !== -1)
		&& bodies.wrong_type_result !== null && bodies.wrong_type_result.indexOf('self::marker_raw_matches(') !== -1
);
test(
	'Son Kapanış: marker_raw_matches() ve normalize_last_applied_hash_raw() strict_string() kullanıyor, cast YOK',
	bodies.marker_raw_matches !== null && bodies.normalize_last_applied_hash_raw !== null
		&& bodies.marker_raw_matches.indexOf('self::strict_string(') !== -1
		&& bodies.normalize_last_applied_hash_raw.indexOf('self::strict_string(') !== -1
		&& !/\(string\)/.test(bodies.marker_raw_matches + bodies.normalize_last_applied_hash_raw)
);

// 7-8 — tests/run.php karşı örnekleri.
test(
	'Son Kapanış: tests/run.php strict_string() array / nested array / object / int / bool / float / null karşı örneklerini içeriyor',
	['strict_string( array( \'x\' ) )', 'strict_string( array( array(', 'strict_string( new stdClass() )', 'strict_string( 5 )', 'strict_string( true )', 'strict_string( 1.5 )', 'strict_string( null )']
		.every((needle) => srcCode.tests.indexOf(needle) !== -1)
);
test(
	'Son Kapanış: tests/run.php icon_key=array ve source_name=array karşı örneklerini + hash\'e ulaşmadan invalid_target_state kanıtını içeriyor',
	srcCode.tests.indexOf("sector_fields_from_raw( array_merge( $mb_raw_sector, array( 'icon_key' => array(") !== -1
		&& srcCode.tests.indexOf("fee_fields_from_raw( array_merge( $mb_raw_fee, array( 'source_name' => array(") !== -1
		&& srcCode.tests.indexOf("null === $plan['current_hash']") !== -1
);
test(
	'Son Kapanış: tests/run.php pozitif VE negatif taşma karşı örneklerini string tabanlı üretiyor (float yok)',
	srcCode.tests.indexOf('function mb_decimal_string_increment(') !== -1
		&& srcCode.tests.indexOf('strict_int( $mb_int_max_plus_one )') !== -1
		&& srcCode.tests.indexOf('strict_int( $mb_int_min_minus_one )') !== -1
		&& srcCode.tests.indexOf('strict_nonneg_int_or_empty_zero( $mb_int_max_plus_one )') !== -1
		&& srcCode.tests.indexOf('strict_int( $mb_long_neg )') !== -1
		&& !/PHP_INT_MAX\s*\+\s*1/.test(srcCode.tests)
);

// Runtime Doğrulama turu — PHP 7.3'te bulunan iki kusurun regresyon sabitleri.
test(
	'Runtime turu: admin mb_paged kararı saf normalize_paged_value() içinde; tests/run.php "1\\n" ve "1\\r\\n" retlerini test ediyor',
	/public\s+static\s+function\s+normalize_paged_value\s*\(/.test(srcCode.admin)
		&& srcCode.tests.indexOf('normalize_paged_value( $mb_bad )') !== -1
		&& src.tests.indexOf('"1\\n"') !== -1 && src.tests.indexOf('"1\\r\\n"') !== -1
);
test(
	'Runtime turu: admin istek/nonce doğrulaması çıktıdan ÖNCE "load-{hook}" aşamasında yapılıyor (wp_die HTTP durumu uygulanabilsin)',
	/\$hook\s*=\s*add_submenu_page\(/.test(srcCode.admin)
		&& /add_action\(\s*'load-'\s*\.\s*\$hook\s*,\s*array\(\s*__CLASS__\s*,\s*'handle_request_before_output'\s*\)\s*\)/.test(srcCode.admin)
		&& (phpFunctionBody(srcCode.admin, 'handle_request_before_output') || '').indexOf('self::validate_request()') !== -1
		&& (phpFunctionBody(srcCode.admin, 'validate_request') || '').indexOf('wp_verify_nonce(') !== -1
		&& (phpFunctionBody(srcCode.admin, 'render_page') || '').indexOf('wp_verify_nonce(') === -1
);
test(
	'Runtime turu: tests/run.php izole fixture dizinlerini temizliyor ve kalmadığını assert ediyor',
	/function\s+mb6b2_cleanup_temp_dirs\s*\(/.test(srcCode.tests)
		&& /\nmb6b2_cleanup_temp_dirs\(\);/.test(srcCode.tests)
		&& srcCode.tests.indexOf("glob( sys_get_temp_dir() . '/mb6b2_*_' . getmypid() . '_*', GLOB_ONLYDIR )") !== -1
);

/* ================================================================
 * Faz 6B3 Önkoşul ve Yazma Güvenliği — kaynak düzeyi regresyon sabitleri.
 * Asıl kanıt gerçek PHP 7.3 testleri ve WordPress 6.9.9 runtime'ıdır;
 * bunlar yalnız geri dönüşü yakalar.
 * ================================================================ */
const PHP_WP_CALL_RE = /\b(get_(post|term)_meta|get_posts|get_terms|get_term_by|get_post|wp_[a-z_]+|update_[a-z_]+|add_[a-z_]+|delete_[a-z_]+|apply_filters|do_action)\s*\(/;
const classifyBody = phpFunctionBody(srcCode.validator, 'classify_import_source_key') || '';
const isValidKeyBody = phpFunctionBody(srcCode.validator, 'is_valid_import_source_key') || '';
// Faz 7: news + reference aileleri eklendi -> 5 kanonik desen (sector/qualification/fee + news/reference), hâlâ TEK sınıflandırıcı.
test('6B3 marker: tek kanonik classify_import_source_key() var; \\z çapalı (5 aile: sector/qualification/fee/news/reference), (string) cast yok',
	classifyBody !== '' && (classifyBody.match(/\\z\//g) || []).length === 5 &&!/\(string\)/.test(classifyBody) && classifyBody.indexOf('is_string( $value )') !== -1);
test('6B3 marker: is_valid_import_source_key() classify\'a devrediyor, (string) cast YOK',
	isValidKeyBody.indexOf('self::classify_import_source_key(') !== -1 && !/\(string\)/.test(isValidKeyBody));
test('6B3 marker: term marker sanitize\'ı classify kullanıyor ve geçersizde REJECTED_META_WRITE döndürüyor (\'\' değil)',
	/sanitize_sector_import_source_key[\s\S]*?classify_import_source_key\([\s\S]*?REJECTED_META_WRITE/.test(srcCode.taxonomies)
		&& !/is_valid_import_source_key\(\s*\$clean,\s*'sector'\s*\)\s*\?\s*\$clean\s*:\s*''/.test(srcCode.taxonomies));
test('6B3 marker: doğal anahtar marker durumu tek classify kuralından türetiliyor (bağımsız regex kopyası yok)',
	(phpFunctionBody(repoCode, 'natural_key_state_from_marker') || '').indexOf('MaviBelge_Core_Validator::classify_import_source_key(') !== -1
		&& !/preg_match\(\s*'\/\^(sector|qualification|fee):/.test(repoCode));

const kurusBody = phpFunctionBody(srcCode.validator, 'canonical_kurus') || '';
const fieldRepoSav = phpFunctionBody(srcCode.fieldRepo, 'sanitize_and_validate') || '';
test('6B3 kuruş: canonical_kurus() TEK ayrıştırıcıyı kullanıyor, TL dönüştürmüyor, float yok',
	kurusBody.indexOf('self::parse_canonical_decimal_int(') !== -1 && kurusBody.indexOf('try_lira_to_kurus') === -1 && !/\(float\)|floatval/.test(kurusBody));
test('6B3 kuruş: sanitize_and_validate() money_kurus kanonik (canonical_kurus) — içinde TL dönüşümü (try_lira_to_kurus) YOK',
	/case 'money_kurus':[\s\S]*?canonical_kurus\(/.test(fieldRepoSav) && fieldRepoSav.indexOf('try_lira_to_kurus') === -1);
test('6B3 kuruş: money_try tipi hiçbir kod yolunda yok (şema/field repository/meta kutuları, yorumlar hariç)',
	srcCode.metaSchema.indexOf("'money_try'") === -1 && srcCode.fieldRepo.indexOf("'money_try'") === -1 && srcCode.metaBoxes.indexOf("'money_try'") === -1);
test('6B3 kuruş: üç kanonik kuruş alanı şemada money_kurus; belge basım ücreti admin_input=try',
	/'_mb_certificate_print_fee_kurus'\s*=>\s*array\([\s\S]{0,200}?'type'\s*=>\s*'money_kurus'[\s\S]{0,120}?'admin_input'\s*=>\s*'try'/.test(srcCode.metaSchema)
		&& /'_mb_min_amount_kurus'\s*=>\s*array\([\s\S]{0,200}?'type'\s*=>\s*'money_kurus'/.test(srcCode.metaSchema)
		&& /'_mb_max_amount_kurus'\s*=>\s*array\([\s\S]{0,200}?'type'\s*=>\s*'money_kurus'/.test(srcCode.metaSchema));
test('6B3 kuruş: TL girişi YALNIZ admin_input_to_storage()\'da çevriliyor ve admin kaydetme döngüsü onu sanitize_and_validate()\'ten ÖNCE çağırıyor',
	(phpFunctionBody(srcCode.fieldRepo, 'admin_input_to_storage') || '').indexOf('try_lira_to_kurus(') !== -1
		&& /admin_input_to_storage\([\s\S]{0,400}sanitize_and_validate\(/.test(srcCode.metaBoxes));

const sfrBody = phpFunctionBody(srcCode.metaSchema, 'sanitize_for_registration') || '';
test('6B3 ezme yok: sanitize_for_registration() geçersizde REJECTED_META_WRITE döndürüyor; safe_default_for_type() / boş dizi fallback\'i ÇAĞIRMIYOR',
	(sfrBody.match(/return self::REJECTED_META_WRITE;/g) || []).length === 3 && sfrBody.indexOf('safe_default_for_type(') === -1 && !/return array\(\);/.test(sfrBody));
test('6B3 ezme yok: ret filtresi update/add post/term metadata\'ya bağlanıyor ve kayıtla birlikte çağrılıyor',
	(phpFunctionBody(srcCode.metaSchema, 'register_reject_filters') || '').indexOf("'update_post_metadata', 'add_post_metadata', 'update_term_metadata', 'add_term_metadata'") !== -1
		&& (phpFunctionBody(srcCode.metaSchema, 'register_all') || '').indexOf('self::register_reject_filters()') !== -1
		&& srcCode.taxonomies.indexOf('MaviBelge_Core_Meta_Schema::register_reject_filters()') !== -1
		&& /REJECTED_META_WRITE === \$meta_value\s*\)\s*\{\s*return false;/.test(phpFunctionBody(srcCode.metaSchema, 'reject_marked_meta_write') || ''));
test('6B3 ezme yok: PHP kaynaklarında ham NUL baytı yok (ret işareti yalnız "\\0" kaçışıyla)',
	src.metaSchema.indexOf(String.fromCharCode(0)) === -1 && /REJECTED_META_WRITE = "\\0mavibelge-core:rejected-meta-write\\0";/.test(src.metaSchema));

test('6B3 doğal anahtar: lookup kapalı kümesinde natural_key + 8 durumlu NATURAL_KEY_STATES',
	/ALLOWED_LOOKUP_KEYS = array\([\s\S]*?'natural_key'/.test(srcCode.recordValidator)
		&& /NATURAL_KEY_STATES = array\(\s*'none', 'unmanaged', 'corrupt_marker', 'wrong_marker_prefix',\s*'foreign_marker', 'undiscovered_marker', 'duplicate', 'query_error',\s*\)/.test(srcCode.recordValidator));
const classifyDecision = phpFunctionBody(srcCode.decision, 'classify') || '';
test('6B3 doğal anahtar: karar motorunda doğal anahtar conflict\'i invalid_target_state\'ten SONRA, bağımlılık ve create\'ten ÖNCE',
	classifyDecision.indexOf('! $target_state_valid') < classifyDecision.indexOf('self::natural_key_result(')
		&& classifyDecision.indexOf('self::natural_key_result(') < classifyDecision.indexOf('! $dependency_resolved')
		&& classifyDecision.indexOf('self::natural_key_result(') < classifyDecision.indexOf('self::CREATE'));
test('6B3 doğal anahtar: yedi yeni neden kodu tanımlı',
	['unmanaged_natural_key', 'corrupt_marker', 'wrong_marker_prefix', 'foreign_marker', 'undiscovered_marker', 'duplicate_natural_key', 'natural_key_query_error'].every((r) => srcCode.decision.indexOf("'" + r + "'") !== -1));
test('6B3 doğal anahtar: repository marker bulunamayınca her zaman natural_key ekliyor (çıplak "hedef yok" create adayı yok)',
	/if \( 0 === \$count \) \{[\s\S]{0,400}'natural_key' => \$this->natural_key_state\( \$type, \$sourceKey \)/.test(repoCode));
const nkCandidates = phpFunctionBody(repoCode, 'natural_key_candidates') || '';
test('6B3 doğal anahtar: aday sorgusu yalnız WordPress okuma API\'leri + tam eşitlik; fuzzy/LIKE/benzerlik YOK; çöp kutusu dahil',
	nkCandidates.indexOf('get_terms(') !== -1 && nkCandidates.indexOf('get_posts(') !== -1
		&& !/similar_text|levenshtein|soundex|metaphone|'compare'\s*=>\s*'LIKE'|stripos|strpos/.test(nkCandidates)
		&& repoCode.indexOf("'trash' )") !== -1
		&& nkCandidates.indexOf('profession_slug(') !== -1);
test('6B3 doğal anahtar: planlayıcı natural_key_check alanını girdi çıktısına ekliyor (build + invalid)',
	(srcCode.planner.match(/'natural_key_check'\s*=>/g) || []).length === 2);

test('6B3 yük/uygunluk: saf sınıflar hiçbir WordPress fonksiyonu çağırmıyor',
	!PHP_WP_CALL_RE.test(srcCode.payload) && !PHP_WP_CALL_RE.test(srcCode.eligibility));
test('6B3 yük: tek geçersiz alan bütün yükü reddediyor (payload null) ve alanlar tek kanonik doğrulayıcıdan geçiyor',
	/function reject\([\s\S]*?'payload' => null/.test(srcCode.payload) && srcCode.payload.indexOf('MaviBelge_Core_Import_Record_Validator::is_valid_managed_field_set(') !== -1
		&& srcCode.payload.indexOf('MaviBelge_Core_Validator::classify_import_source_key(') !== -1 && srcCode.payload.indexOf('MaviBelge_Core_Import_Hash::hash(') !== -1);
test('6B3 uygunluk: yalnız create/update yazılır, unchanged no-op, kanıtsız create reddedilir, uygunsuzlukta writes boş',
	/WRITE_DECISIONS = array\( 'create', 'update' \)/.test(srcCode.eligibility) && /NOOP_DECISIONS = array\( 'unchanged' \)/.test(srcCode.eligibility)
		&& srcCode.eligibility.indexOf("'none' !== $e['natural_key_check']") !== -1
		&& /function not_eligible\([\s\S]*?'writes' => array\(\), 'noops' => array\(\)/.test(srcCode.eligibility)
		&& srcCode.eligibility.indexOf("true !== $plan['summary']['applicable']") !== -1);
test('6B3 uygunluk: TOCTOU yeniden kontrolü ve rollback körlemesine-ezme koruması var',
	(phpFunctionBody(srcCode.eligibility, 'toctou_recheck') || '').indexOf("$observed['current_hash'] !== $write['expected_current_hash']") !== -1
		&& (phpFunctionBody(srcCode.eligibility, 'rollback_allowed') || '').indexOf("$currentHashNow === $record['new_hash']") !== -1);
test('6B3 tests/run.php: kuruş x100, atomik yük, doğal anahtar matrisi, batch atomikliği ve TOCTOU testleri mevcut',
	['15000000 DEĞİL', 'BÜTÜN yükü reddeder', 'karar matrisi: marker yok + natural_key=', 'BÜTÜN batch durur', 'TOCTOU: update — kullanıcı arada', 'rollback: yalnız hedefin şu anki hash'].every((n) => src.tests.indexOf(n) !== -1));

// 6B3 Önkoşul Son Kabul Düzeltmesi — Codex'in iki bloklayıcısının kaynak düzeyi regresyon sabitleri
// (asıl kanıt PHP 7.3 tests/run.php ve WordPress runtime write-safety testidir).
const rejectFiltersBody = phpFunctionBody(srcCode.metaSchema, 'register_reject_filters') || '';
const byMidBody = phpFunctionBody(srcCode.metaSchema, 'reject_invalid_meta_write_by_mid') || '';
test('6B3 son kabul by-mid: update_post_metadata_by_mid ve update_term_metadata_by_mid filtreleri öncelik 1, 4 argümanla kayıtlı',
	rejectFiltersBody.indexOf("'update_post_metadata_by_mid' => 'reject_invalid_post_meta_write_by_mid'") !== -1
		&& rejectFiltersBody.indexOf("'update_term_metadata_by_mid' => 'reject_invalid_term_meta_write_by_mid'") !== -1
		&& /add_filter\(\s*\$hook,\s*array\(\s*__CLASS__,\s*\$method\s*\),\s*1,\s*4\s*\)/.test(rejectFiltersBody));
// By-Mid Kapsam Kapanışı: anahtar çözümü artık kapsam denetiminden ÖNCE, filtre tetiklemeyen okuma ile.
test('6B3 son kabul by-mid: callback meta ID\'den kaydı çözüyor; false anahtar -> kayıttaki anahtar, açık string -> o anahtar, diğer -> fail-closed',
	/get_metadata_by_mid\(\s*\$meta_type,\s*\$meta_id\s*\)/.test(byMidBody)
		&& /if \( false === \$meta_key \) \{\s*\$key = self::read_meta_key_by_mid\( \$meta_type, \$meta_id \);/.test(byMidBody)
		&& /elseif \( is_string\( \$meta_key \) \) \{\s*\$key = \$meta_key;/.test(byMidBody)
		&& /else \{\s*return false;\s*\}/.test(byMidBody)
		&& /false === \$meta_key && \$meta->meta_key !== \$key \) \{\s*return false;/.test(byMidBody));
const writeSafetySrc = fs.readFileSync(path.join(__dirname, 'runtime-test', 'scripts', 'write-safety-test.php'), 'utf8');
const readKeyBody = phpFunctionBody(srcCode.metaSchema, 'read_meta_key_by_mid') || '';
const managedKeyBody = phpFunctionBody(srcCode.metaSchema, 'is_managed_meta_key') || '';
const candidateBody = phpFunctionBody(srcCode.metaSchema, 'is_mavibelge_meta_key_candidate') || '';
const taxSrc = fs.readFileSync(path.join(PLUGIN_ROOT, 'includes', 'class-taxonomies.php'), 'utf8');
const taxCode = stripPhpComments(taxSrc);
const sectorContractBody = phpFunctionBody(taxCode, 'sector_term_meta_contract') || '';
test('By-mid kapsam: MaviBelge dışı anahtarda sanitize_meta()/get_metadata_by_mid() ÖNCESİ null ile çıkılıyor; kesin kapsam sanitize_meta()\'dan önce denetleniyor',
	/if \( ! self::is_mavibelge_meta_key_candidate\( \$meta_type, \$key \) \) \{\s*return null;/.test(byMidBody)
		&& /if \( ! self::is_managed_meta_key\( \$meta_type, \$subtype, \$key \) \) \{\s*return null;/.test(byMidBody)
		&& byMidBody.indexOf('is_mavibelge_meta_key_candidate(') < byMidBody.indexOf('get_metadata_by_mid(')
		&& byMidBody.indexOf('is_mavibelge_meta_key_candidate(') < byMidBody.indexOf('sanitize_meta(')
		&& byMidBody.indexOf('is_managed_meta_key(') < byMidBody.indexOf('sanitize_meta(')
		&& (byMidBody.match(/sanitize_meta\(/g) || []).length === 1);
test('By-mid kapsam: post kapsamı get_fields_for($subtype), term kapsamı Taxonomies sözleşmesi (anahtar listesi Meta_Schema\'da KOPYALANMIYOR)',
	managedKeyBody.indexOf('array_key_exists( $meta_key, self::get_fields_for( $subtype ) )') !== -1
		&& managedKeyBody.indexOf('MaviBelge_Core_Taxonomies::is_managed_sector_term_meta( $subtype, $meta_key )') !== -1
		&& candidateBody.indexOf('MaviBelge_Core_Taxonomies::is_sector_term_meta_key( $meta_key )') !== -1
		&& ['_mb_icon_key', '_mb_image_attachment_id'].every((k) => srcCode.metaSchema.indexOf("'" + k + "'") === -1));
test('By-mid kapsam: mb_sektor term-meta kaydı ve kapsam denetimi TEK sözleşmeden (sector_term_meta_contract) besleniyor; dört anahtar yalnız orada',
	/foreach \( self::sector_term_meta_contract\(\) as \$meta_key => \$args \) \{\s*register_term_meta\( self::SECTOR_TAXONOMY, \$meta_key, \$args \);/.test(taxCode)
		&& (taxCode.match(/register_term_meta\(/g) || []).length === 1
		&& ['_mb_icon_key', '_mb_image_attachment_id', '_mb_import_source_key', '_mb_last_applied_hash'].every((k) => sectorContractBody.indexOf("'" + k + "'") !== -1 && (taxCode.split("'" + k + "'").length - 1) === 1)
		&& /const SECTOR_TAXONOMY = 'mb_sektor';/.test(taxCode)
		&& /self::SECTOR_TAXONOMY === \$taxonomy && self::is_sector_term_meta_key\( \$meta_key \)/.test(phpFunctionBody(taxCode, 'is_managed_sector_term_meta') || ''));
test('By-mid kapsam: anahtar okuma yalnız salt okunur tek SELECT; Meta_Schema ve Taxonomies\'te $wpdb yazma/özyinelemeli update çağrısı yok',
	/\$wpdb->get_var\( \$wpdb->prepare\( "SELECT meta_key FROM \{\$table\} WHERE meta_id = %d", \$meta_id \) \)/.test(readKeyBody)
		&& !/\$wpdb\s*->\s*(insert|update|delete|query|replace)\s*\(/.test(srcCode.metaSchema) && !/\$wpdb\s*->\s*(insert|update|delete|query|replace)\s*\(/.test(taxCode)
		&& !/\bupdate_metadata(_by_mid)?\s*\(|\bupdate_(post|term)_meta\s*\(/.test(srcCode.metaSchema));
test('By-mid kapsam testleri: run.php saf kapsam testleri ve runtime harici sanitizer tek-çağrı testleri mevcut',
	['by-mid kapsam (term): başka taksonomi', 'sanitize_meta() ÇAĞIRMADAN null döner'].every((n) => src.tests.indexOf(n) !== -1)
		&& writeSafetySrc.indexOf('1 === $extCalls') !== -1 && writeSafetySrc.indexOf("register_post_meta( 'mb_yeterlilik', $extPostKey") !== -1
		&& writeSafetySrc.indexOf("register_term_meta( 'mb_sektor', $extTermKey") !== -1);
test('6B3 son kabul by-mid: nesne ID ve alt tür çözülüyor, sanitize_meta çalışıyor; YALNIZ işaret sonucu reddediliyor, aksi hâlde null (normal yol)',
	byMidBody.indexOf('get_object_subtype( $meta_type, $objectId )') !== -1
		&& byMidBody.indexOf('sanitize_meta( $key, $meta_value, $meta_type, $subtype )') !== -1
		&& /self::REJECTED_META_WRITE === \$sanitized \) \{\s*return false;\s*\}\s*return null;\s*\}?$/.test(byMidBody.trim())
		&& byMidBody.indexOf('update_metadata_by_mid(') === -1 && byMidBody.indexOf('$wpdb') === -1);
const rbValidateBody = phpFunctionBody(srcCode.eligibility, 'validate_rollback_record') || '';
const rbBuildBody = phpFunctionBody(srcCode.eligibility, 'build_rollback_record') || '';
const rbAllowedBody = phpFunctionBody(srcCode.eligibility, 'rollback_allowed') || '';
test('6B3 son kabul rollback: tek kapalı validate_rollback_record() var; build ve rollback_allowed onu çağırıyor (allowed ÖNCE doğruluyor)',
	rbValidateBody !== '' && rbBuildBody.indexOf('self::validate_rollback_record( $record )') !== -1
		&& rbAllowedBody.indexOf('self::validate_rollback_record( $record )') !== -1
		&& rbAllowedBody.indexOf('self::validate_rollback_record( $record )') < rbAllowedBody.indexOf("$currentHashNow === $record['new_hash']"));
test('6B3 son kabul rollback: hash\'ler alanlardan yeniden hesaplanıyor, changed_fields deterministik üretilip katı karşılaştırılıyor, anahtar kümesi kapalı',
	rbValidateBody.indexOf("self::safe_hash( $record['new_fields'] )") !== -1 && rbValidateBody.indexOf("self::safe_hash( $record['old_fields'] )") !== -1
		&& rbValidateBody.indexOf("$newHash !== $record['new_hash']") !== -1 && rbValidateBody.indexOf("$oldHash !== $record['old_hash']") !== -1
		&& rbValidateBody.indexOf('$oldHash === $newHash') !== -1
		&& rbValidateBody.indexOf("$record['changed_fields'] !== $changed") !== -1 && rbValidateBody.indexOf('self::ROLLBACK_KEYS') !== -1
		&& rbBuildBody.indexOf('self::expected_changed_fields(') !== -1);
test('6B3 son kabul testleri: run.php rollback bütünlük testleri ve runtime by-mid testleri mevcut',
	['rollback bütünlük (Codex exploit)', 'rollback kayıt (Codex exploit): yalnız schema + new_hash', 'eşit olsa bile malformed kayıt uygun DEĞİL'].every((n) => src.tests.indexOf(n) !== -1)
		&& writeSafetySrc.indexOf("update_metadata_by_mid( 'post'") !== -1 && writeSafetySrc.indexOf("update_metadata_by_mid( 'term'") !== -1);

// 9-10 — durum belgesi ve README kapanışı.
const activeSection = mdSection(statusDoc, 'Aktif iş paketi') || '';
const nextSection = mdSection(statusDoc, 'Bir sonraki güvenli iş paketi') || '';
test(
	'Son Kapanış: proje-durumu.md "Aktif iş paketi" artık Faz 6B1 incelemesini/yazılmamış Faz 6B2\'yi sıradaki iş olarak GÖSTERMİYOR',
	activeSection !== ''
		&& activeSection.indexOf('Sıradaki eylem: Faz 6B1\'in Codex bağımsız incelemesi') === -1
		&& activeSection.indexOf('hiçbiri bu görevde yazılmadı') === -1
		&& activeSection.indexOf('Faz 6B2 Son Kapanış Düzeltmesi') !== -1
		&& activeSection.indexOf('Codex bağımsız incelemesine sunuldu') !== -1
);
test(
	'Son Kapanış: proje-durumu.md "Bir sonraki güvenli iş paketi" Faz 6B2\'yi sıradaki geliştirme işi olarak GÖSTERMİYOR',
	nextSection !== '' && !/\n\*\*Faz 6B2\b/.test(nextSection) && nextSection.indexOf('Faz 6B2 — salt okunur keşif, bağımlılık çözümü') === -1
);
test(
	// Faz 6B3 Apply Kodlaması (24 Eylül 2026): "Faz 6B3 henüz başlamadı" artık GÜNCEL değildir (yalnız
	// tarihsel kayıt olarak durur); güncel iddia "gerçek apply çalıştırılmadı"dır.
	'Faz 6B3 Apply: proje-durumu.md gerçek apply\'ın çalıştırılmadığını ve runtime/staging/kurum/yedek/geri dönüş/onay önkoşullarını koruyor',
	['**Faz 6B3 gerçek apply çalıştırılmadı.**', 'Faz 6B2 bağımsız incelemesi tamamlanmadan Faz 6B3 başlatılmaz', 'PHP testleri', 'staging dry-run', 'kurum incelemesi', 'veritabanı ve dosya yedeği', 'geri dönüş provası', 'açık onayı']
		.every((needle) => nextSection.indexOf(needle) !== -1)
		&& nextSection.indexOf('- **Faz 6B3 henüz başlamadı.**') === -1
);
test(
	'Son Kapanış: proje-durumu.md ve README Faz 6B2 için "nihai kabul edildi" YAZMIYOR',
	!/Faz 6B2[^\n]{0,120}nihai kabul edil(di|miştir)/.test(statusDoc) && !/Faz 6B2[^\n]{0,120}nihai kabul edil(di|miştir)/.test(rootReadme)
);
test(
	'Son Kapanış: README Son Kapanış düzeltmesini, Node/PHP ayrımını ve Faz 6B3\'ün başlamadığını belirtiyor',
	rootReadme.indexOf('Faz 6B2 Son Kapanış Düzeltmesi') !== -1 && rootReadme.indexOf('Faz 6B3 başlamadı') !== -1
		&& rootReadme.indexOf('PHP testleri çalıştırılmadı') !== -1
);
// Runtime Engellerinin Kapatılması turu — üç bloklayıcının kaynak düzeyi regresyon sabitleri
// (asıl kanıt PHP 7.3 lint/test ve WordPress runtime'dır; bunlar yalnız geri dönüşü yakalar).
const fieldRepoSrc = fs.readFileSync(path.join(PLUGIN_ROOT, 'includes', 'class-field-repository.php'), 'utf8');
const themeRoot = path.resolve(PLUGIN_ROOT, '..', '..', 'themes', 'mavibelge');
const buttonSrc = fs.readFileSync(path.join(themeRoot, 'template-parts', 'components', 'button.php'), 'utf8');
test(
	'Engel kapanışı: select doğrulaması seçenek anahtarlarını kanonik string listesine çeviriyor (tüm select alanları için genel)',
	/\$allowed_values\s*=\s*array_map\(\s*'strval'\s*,\s*array_keys\(\s*\$config\['options'\]\s*\)\s*\)/.test(fieldRepoSrc)
		&& /is_valid_select\(\s*\$raw_str\s*,\s*\$allowed_values\s*\)/.test(fieldRepoSrc)
		&& !/is_valid_select\(\s*\$raw_str\s*,\s*array_keys\(/.test(fieldRepoSrc)
);
test(
	'Engel kapanışı: tests/run.php sayısal select (_mb_level) ve string select regresyonlarını içeriyor',
	srcCode.tests.indexOf("get_fields_for( 'mb_yeterlilik' )['_mb_level']") !== -1
		&& srcCode.tests.indexOf("get_fields_for( 'mb_ucret' )['_mb_level']") !== -1
		&& src.tests.indexOf("'03', '1.0', '1e1'") !== -1
		&& srcCode.tests.indexOf('$mb_all_select_ok') !== -1
);
test(
	'Engel kapanışı: button.php docblock\'u yorumu erken kapatan "aria-*/data-*" metnini içermiyor',
	buttonSrc.indexOf('aria-*/data-*') === -1 && (buttonSrc.match(/\*\//g) || []).length === 1
);
test(
	'Durum belgeleri: runtime raporuna bağlanıyor ve onaylı "hazırdır" ifadesini zorunlu çekincelerle birlikte kullanıyor',
	rootReadme.indexOf('faz6b2-php-wordpress-runtime-dogrulama.md') !== -1
		&& statusDoc.indexOf('faz6b2-php-wordpress-runtime-dogrulama.md') !== -1
		&& statusDoc.indexOf('Faz 6B2, PHP 7.3.33 ve izole WordPress 6.9.9 runtime doğrulaması düzeyinde Codex bağımsız nihai kabul incelemesine hazırdır.') !== -1
		&& rootReadme.indexOf('Faz 6B2, PHP 7.3.33 ve izole WordPress 6.9.9 runtime doğrulaması düzeyinde Codex bağımsız nihai kabul incelemesine hazırdır.') !== -1
		&& ['Gerçek staging yapılmadı', 'Gerçek tarayıcı testi yapılmadı', 'Üretim sürümü kilitlenmedi', 'Apply hazır değil'].every((n) => statusDoc.indexOf(n) !== -1 && rootReadme.indexOf(n) !== -1)
);

// ================================================================
// Faz 6B3 — APPLY/BATCH/AUDIT/ROLLBACK statik kapıları (kaynak metni; asıl
// kanıt PHP 7.3 tests/run.php + izole WordPress apply-cycle testidir).
// ================================================================
const IMPORT_DIR = path.join(PLUGIN_ROOT, 'includes', 'import');
const f6 = (name) => fs.readFileSync(path.join(IMPORT_DIR, name), 'utf8');
const F6 = {
	writerIf: 'interface-import-target-writer.php', txIf: 'interface-import-transaction.php', storeIf: 'interface-import-run-store.php', auditIf: 'interface-import-audit-sink.php',
	runState: 'class-import-run-state.php', applyPlan: 'class-import-apply-plan.php', codec: 'class-import-rollback-codec.php', auditCtx: 'class-import-audit-context.php',
	applySvc: 'class-import-apply-service.php', rollbackSvc: 'class-import-rollback-service.php', finalizer: 'class-import-run-finalizer.php',
	writer: 'class-import-wordpress-target-writer.php', tx: 'class-import-wpdb-transaction.php', store: 'class-import-wpdb-run-store.php', sink: 'class-import-wp-audit-sink.php',
};
const s6 = {};
const c6 = {};
for (const k of Object.keys(F6)) {
	s6[k] = f6(F6[k]);
	c6[k] = stripPhpComments(s6[k]);
}
const WRITE_FNS = ['wp_insert_post', 'wp_update_post', 'wp_delete_post', 'wp_trash_post', 'wp_insert_term', 'wp_update_term', 'wp_delete_term', 'wp_set_object_terms',
	'add_post_meta', 'update_post_meta', 'delete_post_meta', 'add_term_meta', 'update_term_meta', 'delete_term_meta', 'update_metadata', 'add_option', 'update_option', 'delete_option', 'set_transient'];
const writeHits = (code) => WRITE_FNS.filter((fn) => new RegExp('(?<![>:\\w$])' + fn + '\\s*\\(').test(code));

// 1) Yazma çağrıları YALNIZ yeni yazma adapterında; dry-run/saf/servis katmanlarında sıfır.
for (const k of ['writerIf', 'txIf', 'storeIf', 'auditIf', 'runState', 'applyPlan', 'codec', 'auditCtx', 'applySvc', 'rollbackSvc', 'tx', 'sink']) {
	test(`Faz 6B3 yazma sınırı: ${F6[k]} içinde WordPress içerik/meta/option yazma çağrısı YOK`, writeHits(c6[k]).length === 0);
}
test('Faz 6B3 yazma sınırı: içerik/meta yazma çağrıları yalnız yazma adapterında (wp_insert_term/wp_update_term/wp_insert_post/wp_update_post/update_*_meta/delete_post_meta/wp_set_object_terms/wp_trash_post/wp_delete_term)',
	['wp_insert_term', 'wp_update_term', 'wp_insert_post', 'wp_update_post', 'update_term_meta', 'update_post_meta', 'delete_post_meta', 'wp_set_object_terms', 'wp_trash_post', 'wp_delete_term'].every((fn) => writeHits(c6.writer).includes(fn))
		&& writeHits(c6.writer).every((fn) => ['wp_insert_term', 'wp_update_term', 'wp_insert_post', 'wp_update_post', 'update_term_meta', 'update_post_meta', 'delete_post_meta', 'wp_set_object_terms', 'wp_trash_post', 'wp_delete_term'].includes(fn))
		&& !/wp_delete_post\s*\(/.test(c6.writer));
test('Faz 6B3 yazma sınırı: dry-run sınıfları (loader/repository/service/planner/decision/validator/payload/eligibility/cli/admin) sıfır yazma çağrısı taşır',
	['loader', 'repository', 'service', 'planner', 'decision', 'recordValidator', 'payload', 'eligibility', 'cli', 'admin'].every((k) => writeHits(srcCode[k]).length === 0));
test('Faz 6B3 $wpdb: yazma adapterında ve servislerde $wpdb YOK; içerik/meta tablolarına ham SQL yazımı yok',
	c6.writer.indexOf('$wpdb') === -1 && c6.applySvc.indexOf('$wpdb') === -1 && c6.rollbackSvc.indexOf('$wpdb') === -1 && c6.sink.indexOf('$wpdb') === -1);
test('Faz 6B3 $wpdb: run deposu yalnız kendi iki tablosuna insert/update yapar (runs_table/items_table); query/replace/delete yok',
	(c6.store.match(/\$wpdb->(insert|update)\(\s*self::(runs_table|items_table)\(\)/g) || []).length === (c6.store.match(/\$wpdb->(insert|update)\(/g) || []).length
		&& !/\$wpdb->(query|replace|delete)\s*\(/.test(c6.store));
test('Faz 6B3 $wpdb: transaction sınıfı yalnız START TRANSACTION / COMMIT / ROLLBACK çalıştırır ve sonucu kontrol eder',
	(c6.tx.match(/self::run\(\s*'([A-Z ]+)'\s*\)/g) || []).map((m) => m.replace(/self::run\(\s*'|'\s*\)/g, '')).sort().join('|') === 'COMMIT|ROLLBACK|START TRANSACTION'
		&& /\$wpdb->query\(\s*\$statement\s*\)/.test(c6.tx) && (c6.tx.match(/\$wpdb->query\(/g) || []).length === 1
		&& /return false !== \$result && '' === \$wpdb->last_error;/.test(c6.tx) && /'innodb' !== \$engines\[ \$table \]/.test(c6.tx)
		&& /'utf8mb4' !== \$wpdb->charset/.test(c6.tx) && /0 !== strpos\( \$collations\[ \$table \], 'utf8mb4' \)/.test(c6.tx));

// 2) Servisler WordPress fonksiyonu çağırmaz (bütün erişim enjekte arayüzlerde).
const WP_CALL = /(?<![>:\w$])\b(get_[a-z_]+|wp_[a-z_]+|update_[a-z_]+|add_[a-z_]+|delete_[a-z_]+|current_user_can|apply_filters|do_action|esc_[a-z_]+|sanitize_[a-z_]+)\s*\(/;
for (const k of ['runState', 'applyPlan', 'codec', 'auditCtx', 'applySvc', 'rollbackSvc']) {
	test(`Faz 6B3 saflık: ${F6[k]} WordPress fonksiyonu çağırmıyor`, !WP_CALL.test(c6[k]));
}

// 3) Tek kaynak: ikinci karar/doğrulama sistemi yok.
test('Faz 6B3 tek kaynak: apply planı/uygunluk/yük/TOCTOU/rollback kaydı mevcut sınıflardan (Eligibility, Write_Payload, Planner, Hash, Codec)',
	c6.applySvc.indexOf('MaviBelge_Core_Import_Apply_Eligibility::evaluate_plan(') !== -1 && c6.applySvc.indexOf('MaviBelge_Core_Import_Apply_Eligibility::toctou_recheck(') !== -1
		&& c6.applySvc.indexOf('MaviBelge_Core_Import_Write_Payload::prepare(') !== -1 && c6.applySvc.indexOf('MaviBelge_Core_Import_Dry_Run_Planner::project_for_apply(') !== -1
		&& c6.applySvc.indexOf('MaviBelge_Core_Import_Apply_Eligibility::build_rollback_record(') !== -1 && c6.applySvc.indexOf('MaviBelge_Core_Import_Rollback_Codec::encode(') !== -1
		&& c6.rollbackSvc.indexOf('MaviBelge_Core_Import_Rollback_Codec::decode(') !== -1 && c6.rollbackSvc.indexOf('MaviBelge_Core_Import_Apply_Eligibility::rollback_allowed(') !== -1
		&& c6.codec.indexOf('MaviBelge_Core_Import_Apply_Eligibility::validate_rollback_record(') !== -1);
test('Faz 6B3 tek kaynak: TOCTOU ve readback planlayıcıyla AYNI yoldan (Dry_Run_Service::observe_record); readback kaydın "unchanged" olmasını ister',
	(c6.applySvc.match(/\$this->dryRun->observe_record\(/g) || []).length === 2 && /'unchanged' !== \$ae\['decision'\]/.test(c6.applySvc)
		&& /MaviBelge_Core_Import_Dry_Run_Planner::plan_(sector|qualification|fee)\(/.test(srcCode.service));
test('Faz 6B3 tek kaynak: planlayıcının plan_*() ve project_for_apply() AYNI prepare_projection() yolunu kullanır',
	(srcCode.planner.match(/self::prepare_projection\(/g) || []).length === 2 && /function project_for_apply\(/.test(srcCode.planner));

// 4) Apply kapısı sırası: argüman -> digest -> uygunluk -> yük -> kurulum -> ön kontrol -> kilit -> çözülmemiş run -> yürütme.
const applyBody = phpFunctionBody(c6.applySvc, 'apply') || '';
const order6 = ["'invalid_stage'", "'invalid_batch_size'", "'invalid_confirmation'", '$digest !== $confirmDigest', "! $eligibility['eligible']", '$this->prepare_items(', '$this->store->ensure_installed()', '$this->tx->preflight()', '$this->store->acquire_lock()', 'BLOCKS_NEW_APPLY', '$this->execute('];
test('Faz 6B3 kapı sırası: yazma/kurulumdan önce bütün plan kapıları; kilit ve çözülmemiş run kontrolü yürütmeden önce',
	order6.every((n) => applyBody.indexOf(n) !== -1) && order6.every((n, i) => i === 0 || applyBody.indexOf(order6[i - 1]) < applyBody.indexOf(n))
		&& /finally \{\s*\$this->store->release_lock\(\);/.test(applyBody));
test('Faz 6B3 batch: her batch begin/commit ile sarılı; hata -> tx rollback -> fail_run (transaction dışında run_failed audit)',
	/true !== \$this->tx->begin\(\)/.test(c6.applySvc) && /true !== \$this->tx->commit\(\)/.test(c6.applySvc) && /true !== \$this->tx->rollback\(\)/.test(c6.applySvc)
		&& /return \$this->fail_run\(/.test(c6.applySvc) && /ROLLBACK_REQUIRED : MaviBelge_Core_Import_Run_State::FAILED/.test(c6.applySvc));
test('Faz 6B3 batch: tek merkezi batch sabiti 20 ve 1..20 sınırı', /const DEFAULT_BATCH_SIZE = 20;/.test(c6.applyPlan) && /const MAX_BATCH_SIZE\s+= 20;/.test(c6.applyPlan) && /const MIN_BATCH_SIZE\s+= 1;/.test(c6.applyPlan));
test('Faz 6B3 aşama: stage kümesi kapalı ve bağımlılık sırasına göre önek (sectors ⊂ qualifications ⊂ all)',
	/const STAGES = array\( 'sectors', 'qualifications', 'all' \);/.test(c6.applyPlan));

// 5) Kaçış yolu yok.
test('Faz 6B3 kaçış yolu yok: force/skip/partial parametresi veya kısmi plan uygulama kodu yok (servisler + CLI)',
	['applySvc', 'rollbackSvc', 'applyPlan'].every((k) => !/\bforce\b|skip_conflict|skip-conflict|partial_apply/i.test(c6[k])) && !/\bforce\b/i.test(srcCode.cli.replace(/Error:[^'"]*/g, '')));

// 6) CLI kapıları.
const applyCat = phpFunctionBody(srcCode.cli, 'apply_catalog') || '';
test('Faz 6B3 CLI: --apply sinopsiste isteğe bağlı bayrak; dry-run varsayılan; --dry-run ile birlikte reddedilir',
	src.cli.indexOf(' * [--apply]') !== -1 && src.cli.indexOf(' * [--dry-run]') !== -1 && /--dry-run ve --apply birlikte verilemez/.test(srcCode.cli));
test('Faz 6B3 CLI: apply sırası = MAVIBELGE_IMPORT_APPLY_ENABLED -> yetki (manage_options + mb_manage_tariff_period) -> --confirm -> servis',
	/defined\( 'MAVIBELGE_IMPORT_APPLY_ENABLED' \) && true === MAVIBELGE_IMPORT_APPLY_ENABLED/.test(srcCode.cli)
		&& /get_current_user_id\(\) > 0 && current_user_can\( 'manage_options' \) && current_user_can\( 'mb_manage_tariff_period' \)/.test(srcCode.cli)
		&& /if \( ! self::apply_enabled\(\) \) \{\s*WP_CLI::error\(/.test(applyCat) && /if \( ! self::user_can_import\(\) \) \{\s*WP_CLI::error\(/.test(applyCat)
		&& /if \( ! isset\( \$assoc_args\['confirm'\] \) \) \{\s*WP_CLI::error\(/.test(applyCat)
		&& ['self::apply_enabled()', 'self::user_can_import()', "isset( $assoc_args['confirm'] )", '$service->apply('].every((n, i, arr) => applyCat.indexOf(n) !== -1 && (i === 0 || applyCat.indexOf(arr[i - 1]) < applyCat.indexOf(n))));
test('Faz 6B3 CLI: rollback ayrı komut, --run-id zorunlu, --confirm yoksa salt okunur önizleme; yürütme anahtar + yetki ister',
	/public function rollback\(/.test(srcCode.cli) && src.cli.indexOf(' * --run-id=<uid>') !== -1 && /\$service->preview\( \$uid \)/.test(srcCode.cli)
		&& (phpFunctionBody(srcCode.cli, 'rollback') || '').indexOf('self::apply_enabled()') !== -1 && (phpFunctionBody(srcCode.cli, 'rollback') || '').indexOf('self::user_can_import()') !== -1);
test('Faz 6B3 CLI: status salt okunur (is_installed kontrolü, kurulum çağrısı yok)', (phpFunctionBody(srcCode.cli, 'status') || '').indexOf('ensure_installed') === -1 && (phpFunctionBody(srcCode.cli, 'status') || '').indexOf('is_installed()') !== -1);
test('Faz 6B3 CLI: dry-run yolu yazma sınıflarını örneklemez (yalnız repository + Dry_Run_Service)',
	(phpFunctionBody(srcCode.cli, 'catalog') || '').indexOf('Target_Writer') === -1 && (phpFunctionBody(srcCode.cli, 'catalog') || '').indexOf('Wpdb_') === -1);

// 7) Salt okunur repository arayüzü DEĞİŞMEDİ; yazma ayrı dar arayüzde.
// Faz 7: aynı dosyaya İSTEĞE BAĞLI ikinci arayüz eklendi; Target_Repository'nin KENDİSİ hâlâ yalnız 5 okuma metodu taşır.
const repoIfSplit = srcCode.interfaceFile.split('interface MaviBelge_Core_Import_Content_Dependency_Resolver');
const ifMethods = (code) => (code.match(/public function (\w+)\(/g) || []).map((m) => m.replace(/public function |\(/g, '')).sort().join(',');
test('Faz 6B3 arayüz: MaviBelge_Core_Import_Target_Repository hâlâ yalnız 5 okuma metodu taşır (Faz 7 çözümleyici arayüzü AYRI)',
	repoIfSplit.length === 2 && ifMethods(repoIfSplit[0]) === 'find_target_by_source_key,get_diagnostics,resolve_qualification_post_id,resolve_sector_image_attachment_id,resolve_sector_term_id');
test('Faz 7 arayüz: ayrı Content_Dependency_Resolver arayüzü TEK metot taşır (resolve_news_type_term_id)', repoIfSplit.length === 2 && ifMethods(repoIfSplit[1]) === 'resolve_news_type_term_id');
test('Faz 6B3 arayüz: yazma adapterı arayüzü dar (create/update sektör+post, rollback_created_post, delete_sector_term, parmak izi, terim referansları)',
	(c6.writerIf.match(/public function (\w+)\(/g) || []).map((m) => m.replace(/public function |\(/g, '')).sort().join(',') === 'create_post,create_sector,delete_sector_term,rollback_created_post,sector_term_references,unmanaged_fingerprint,update_post,update_sector');

// 8) Yönetilmeyen alan / readback / çöp kutusu kuralları (yazma adapterı).
test('Faz 6B3 adapter: her meta yazımı katı readback ile doğrulanır; yalnız yönetilen meta listesi yazılabilir',
	(c6.writer.match(/self::stored_equals\(/g) || []).length === 2 && /array\(\) !== array_diff\( array_keys\( \$payload\['post_meta'\] \), MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META/.test(c6.writer)
		&& /array\(\) === array_diff\( array_keys\( \$payload\['term_meta'\] \), array_keys\( MaviBelge_Core_Taxonomies::sector_term_meta_contract\(\) \) \)/.test(c6.writer));
test('Faz 6B3 adapter: değerler wp_slash ile verilir (WordPress unslash eder); post create draft; EMPTY_TRASH_DAYS=0 iken çöp yerine kalıcı silme YAPILMAZ',
	(c6.writer.match(/wp_slash\(/g) || []).length >= 6 && /'post_status'\s*=>\s*'draft'/.test(c6.writer) && /defined\( 'EMPTY_TRASH_DAYS' \) && ! EMPTY_TRASH_DAYS/.test(c6.writer));
test('Faz 6B3 adapter: aktif tarife seçeneği (mb_active_tariff_period) hiçbir import dosyasında YAZILMAZ/geçmez',
	Object.keys(c6).every((k) => c6[k].indexOf('mb_active_tariff_period') === -1));

// 9) Rollback kuralları.
test('Faz 6B3 rollback: sektör terimi yalnız term_blocker (alt terim yok + bağlı her post bu run\'ın ya da geri alınmış import create\'inin ve çöpte) geçerse silinir',
	/'create' === \$record\['decision'\]/.test(c6.rollbackSvc) && /'sector' === \$record\['type'\]\s*\)\s*\{\s*return \$this->term_blocker\(/.test(c6.rollbackSvc) && /0 !== \$refs\['child_count'\]/.test(c6.rollbackSvc)
		&& /rolled_back_create_target_ids\( 'qualification' \)/.test(c6.rollbackSvc) && /! in_array\( \$postId, \$refs\['trashed_ids'\], true \)/.test(c6.rollbackSvc));
test('Faz 6B3 rollback: ön kontrol engelinde yazma yok -> rollback_failed; kısmi rollback açıkça rollback_failed; sıra rollback_order()',
	/\$blockers = \$this->preflight\( \$run, \$items \);\s*if \( ! empty\( \$blockers \) \)/.test(c6.rollbackSvc) && /MaviBelge_Core_Import_Apply_Plan::rollback_order\( \$items \)/.test(c6.rollbackSvc)
		&& /finalizer->settle\(\s*\$run,\s*MaviBelge_Core_Import_Run_State::ROLLING_BACK/.test(c6.rollbackSvc) && /'to'\s*=>\s*MaviBelge_Core_Import_Run_State::ROLLBACK_FAILED/.test(c6.rollbackSvc));

// 10) Run/checkpoint şeması ve audit.
test('Faz 6B3 şema: iki ayrı tablo (runs + run_items), LONGTEXT (JSON sütunu yok), sürüm seçeneği yalnız tablolar doğrulandıktan sonra',
	/CREATE TABLE \{\$runs\}/.test(s6.store) && /CREATE TABLE \{\$items\}/.test(s6.store) && /rollback_record LONGTEXT NOT NULL/.test(s6.store) && !/\bJSON\b[^_]/.test(c6.store.replace(/JSON[^\n]*\*/g, ''))
		&& c6.store.indexOf('if ( ! self::table_exists( $runs ) || ! self::table_exists( $items ) ) {') < c6.store.indexOf('update_option( self::VERSION_OPTION'));
test('Faz 6B3 şema: run uid tahmin edilemez (random_bytes); durum geçişi CAS (WHERE id + status) ve Run_State::can_transition',
	/bin2hex\( random_bytes\( 16 \) \)/.test(c6.store) && /array\( 'id' => \(int\) \$runId, 'status' => \$from \)/.test(c6.store) && /MaviBelge_Core_Import_Run_State::can_transition\( \$from, \$to \)/.test(c6.store));
test('Faz 6B3 audit: yedi olay sabiti; sink yalnız bunları ve kapalı context izin listesini kabul eder',
	['import_run_started', 'import_batch_committed', 'import_run_completed', 'import_run_failed', 'import_rollback_started', 'import_rollback_completed', 'import_rollback_failed'].every((e) => fs.readFileSync(path.join(PLUGIN_ROOT, 'audit', 'class-audit-log.php'), 'utf8').indexOf("'" + e + "'") !== -1)
		&& /MaviBelge_Core_Import_Audit_Context::build\( \$context \)/.test(c6.sink) && /in_array\( \$event, self::EVENTS, true \)/.test(c6.sink));

// 11) PHP 7.4+/8.x sözdizimi yok (yeni dosyalar + değişen dosyalar).
const PHP74 = [/\bfn\s*\(/, /\?\?=/, /\bmatch\s*\(/, /\?->/, /\b(public|private|protected)\s+(static\s+)?(\?\s*)?(int|string|array|bool|float|iterable|object|mixed|self|[A-Z]\w+)\s+\$/, /\bstr_contains\s*\(/, /\bstr_starts_with\s*\(/, /\bstr_ends_with\s*\(/, /\barray_is_list\s*\(/, /\b(public|private|protected)\s+readonly\b|\breadonly\s+(class|public|private|protected)\b/, /^\s*enum\s+\w+/m, /function __construct\(\s*(public|private|protected)\s/, /\w+:\s*\$\w+\s*[,)]/];
const phpFilesForSyntax = Object.keys(c6).map((k) => c6[k]).concat([srcCode.cli, srcCode.planner, srcCode.service, srcCode.payload, srcCode.metaSchema]);
test('Faz 6B3 PHP 7.3: yeni/değişen import dosyalarında PHP 7.4+/8.x sözdizimi yok (arrow fn, ??=, match, ?->, typed property, str_contains, array_is_list, readonly, enum, promotion)',
	phpFilesForSyntax.every((code) => PHP74.slice(0, 12).every((re) => !re.test(code))));

// 12) Yükleme sırası (ortak dosya) ve test varlığı.
const mainSrc = src.bootstrap;
const loadOrder6 = ['interface-import-target-writer.php', 'interface-import-transaction.php', 'interface-import-run-store.php', 'interface-import-audit-sink.php', 'class-import-run-state.php', 'class-import-apply-plan.php', 'class-import-rollback-codec.php', 'class-import-audit-context.php', 'class-import-run-finalizer.php', 'class-import-apply-service.php', 'class-import-rollback-service.php', 'class-import-wordpress-target-writer.php', 'class-import-wpdb-transaction.php', 'class-import-wpdb-run-store.php', 'class-import-wp-audit-sink.php'];
test('Faz 6B3 yükleme: mavibelge-core.php yeni 15 dosyayı arayüz -> saf -> servis -> WordPress uygulaması sırasıyla yüklüyor (eligibility sonrası)',
	loadOrder6.every((n, i) => mainSrc.indexOf(n) !== -1 && (i === 0 || mainSrc.indexOf(loadOrder6[i - 1]) < mainSrc.indexOf(n))) && mainSrc.indexOf('class-import-apply-eligibility.php') < mainSrc.indexOf(loadOrder6[0]));
test('Faz 6B3 düzeltme: servisler durum geçişini ve audit olayını YALNIZ Run_Finalizer üzerinden (atomik) yapar; doğrudan store->transition / audit->record(RUN_/ROLLBACK_ *) çağrısı yok',
	!/\$this->store->transition\(/.test(c6.applySvc) && !/\$this->store->transition\(/.test(c6.rollbackSvc)
		&& !/audit->record\(\s*MaviBelge_Core_Audit_Log::EVENT_IMPORT_(RUN_STARTED|RUN_COMPLETED|RUN_FAILED|ROLLBACK_STARTED|ROLLBACK_COMPLETED|ROLLBACK_FAILED)/.test(c6.applySvc + c6.rollbackSvc)
		&& /tx->begin\(\)/.test(c6.finalizer) && /tx->commit\(\)/.test(c6.finalizer) && /tx->rollback\(\)/.test(c6.finalizer) && writeHits(c6.finalizer).length === 0);
test('Faz 6B3 düzeltme: create rollback kaydı unmanaged_fingerprint taşır (create için zorunlu 64-hex, update için null); rollback servisi drift kontrolü yapar ve yazma adapterı fingerprint ister',
	/'unmanaged_fingerprint'/.test(f6('class-import-apply-eligibility.php')) && /unmanaged_fingerprint\( \$record\['type'\], \$record\['target_id'\] \)/.test(c6.rollbackSvc)
		&& /rollback_created_post\( \$postId, \$postType, \$expectedFingerprint \)/.test(c6.writerIf) && /delete_sector_term\( \$termId, \$expectedFingerprint \)/.test(c6.writerIf)
		&& /createFingerprint/.test(c6.applySvc));
test('Faz 6B3 testler: run.php apply/rollback senaryoları ve runtime apply-cycle testi mevcut',
	['6B3 apply red: tek conflict', '6B3 TOCTOU:', '6B3 batch hata:', '6B3 rollback drift:', '6B3 kısmi rollback', '6B3 kodek: bozuk JSON', '6B3 durum: geçersiz geçişler'].every((n) => src.tests.indexOf(n) !== -1)
		&& fs.existsSync(path.join(__dirname, 'runtime-test', 'scripts', 'apply-cycle-test.php')) && fs.existsSync(path.join(__dirname, 'runtime-test', 'apply-cycle.sh')));

/* ================================================================
 * Faz 7 — içerik aktarımı (news/reference): kaynak düzeyi regresyon sabitleri.
 * Asıl kanıt: tests/suites/faz7-import-content.php (PHP 7.3.33) ve
 * tools/runtime-test/scripts/apply-cycle-test-3.php (WordPress 6.9.9).
 * ================================================================ */
const TOOLS_IMPORT = path.join(__dirname, 'import');
const DATA_DIR7 = path.resolve(__dirname, '..', 'data');
const read7 = (p) => fs.readFileSync(p, 'utf8');
const code7 = {
	managed: stripPhpComments(f6('class-import-managed-fields.php')),
	recordValidator: srcCode.recordValidator,
	planner: srcCode.planner,
	payload: srcCode.payload,
	eligibility: srcCode.eligibility,
	auditCtx: c6.auditCtx,
	applyPlan: c6.applyPlan,
	applySvc: c6.applySvc,
	writer: c6.writer,
	loader: srcCode.loader,
	service: srcCode.service,
	repo: srcCode.repository,
};
const runtime7 = read7(path.join(__dirname, 'runtime-test', 'scripts', 'apply-cycle-test-3.php'));
const cycleSh7 = read7(path.join(__dirname, 'runtime-test', 'apply-cycle.sh'));
const fixtureDb7 = read7(path.join(__dirname, 'runtime-test', 'scripts', 'fixture-db.php'));
const fixture7 = read7(path.join(PLUGIN_ROOT, 'tests', 'fixtures', 'apply-fixture.php'));
const suite7 = read7(path.join(PLUGIN_ROOT, 'tests', 'suites', 'faz7-import-content.php'));

test('Faz 7 yönetilen alanlar: NEWS_FIELDS/REFERENCE_FIELDS/TYPES tek kaynakta; eligibility, kayıt doğrulayıcı, audit context ve payload TEK fields_for()/TYPES kullanır (kopya allowlist yok)',
	/const NEWS_FIELDS = array\( 'slug', 'title', 'content', 'excerpt', 'published_on', 'news_type_term_id', 'approval_status' \)/.test(code7.managed)
		&& /const REFERENCE_FIELDS = array\( 'slug', 'title', 'reference_status', 'record_status', 'sort_order', 'website_url', 'logo_attachment_id' \)/.test(code7.managed)
		&& /const TYPES = array\( 'sector', 'qualification', 'fee', 'news', 'reference' \)/.test(code7.managed)
		&& code7.eligibility.indexOf('Managed_Fields::fields_for(') !== -1 && code7.recordValidator.indexOf('Managed_Fields::fields_for(') !== -1
		&& code7.eligibility.indexOf('Managed_Fields::TYPES') !== -1 && code7.payload.indexOf('Managed_Fields::TYPES') !== -1 && code7.auditCtx.indexOf('Managed_Fields::TYPES') !== -1
		&& code7.eligibility.indexOf('SECTOR_FIELDS') === -1 && code7.eligibility.indexOf("array( 'sector', 'qualification', 'fee' )") === -1);
test('Faz 7 sabitler: haber onayı YALNIZ in_review, referans YALNIZ representative/active/""/0; import hiçbir yerde "publish" yazmaz (yük, projeksiyon, yazıcı, servisler)',
	/'in_review' !== \$f\['approval_status'\]/.test(code7.payload) && /'representative' !== \$f\['reference_status'\]/.test(code7.payload)
		&& code7.managed.indexOf("'approval_status'   => 'in_review'") !== -1 && code7.managed.indexOf("'reference_status'   => 'representative'") !== -1
		&& ['payload', 'managed', 'writer', 'applySvc'].every((k) => !/'publish'|"publish"/.test(code7[k])));
test('Faz 7 yazıcı: post HER ZAMAN draft açılır; update post_status yazmaz; edit_date açık; kalıcı silme (wp_delete_post/wp_delete_term dışında sektör) yok; yönetilen çekirdek alan ve taksonomi listeleri tanımlı',
	/'post_status'\s*=>\s*'draft'/.test(code7.writer) && !/'post_status'\s*=>\s*'(?!draft')/.test(code7.writer) && /\$args\['edit_date'\] = true/.test(code7.writer) && !/wp_delete_post\s*\(/.test(code7.writer)
		&& /const MANAGED_CORE_FIELDS = array\(\s*'news'\s*=> array\( 'post_name', 'post_content', 'post_excerpt', 'post_date' \),\s*'reference' => array\( 'post_name' \)/.test(code7.writer)
		&& /const MANAGED_TAXONOMIES = array\(\s*'qualification' => array\( 'mb_sektor' \),\s*'news'\s*=> array\( 'mb_haber_turu' \)/.test(code7.writer));
test('Faz 7 yazıcı: unmanaged_fingerprint yönetilen çekirdek alanları ve yönetilen taksonomiyi dışlar; rollback post_name\'i boşaltıp çöpe alır ve serbest kaldığını readback ile doğrular',
	/MANAGED_CORE_FIELDS\[ \$type \]/.test((phpFunctionBody(code7.writer, 'unmanaged_fingerprint') || '')) && /MANAGED_TAXONOMIES\[ \$type \]/.test((phpFunctionBody(code7.writer, 'unmanaged_fingerprint') || ''))
		&& /'post_name' => ''/.test((phpFunctionBody(code7.writer, 'rollback_created_post') || '')) && (phpFunctionBody(code7.writer, 'rollback_created_post') || '').indexOf('post_name_not_released') !== -1 && (phpFunctionBody(code7.writer, 'rollback_created_post') || '').indexOf('wp_trash_post( $postId )') !== -1);
test('Faz 7 aşama: STAGES üç katalog aşaması (DEĞİŞMEDİ), CONTENT_STAGES=[content], ALL_STAGES dört; TYPE_LISTS news/references; TYPE_RANK news=3 reference=4; rollback sırası sabit "2 -" içermez; plan özeti/apply/CLI ALL_STAGES kullanır',
	/const STAGES = array\( 'sectors', 'qualifications', 'all' \);/.test(code7.applyPlan) && /const CONTENT_STAGES = array\( 'content' \);/.test(code7.applyPlan) && /const ALL_STAGES = array\( 'sectors', 'qualifications', 'all', 'content' \);/.test(code7.applyPlan)
		&& /'news'\s*=> 'news'/.test(code7.applyPlan) && /'reference'\s*=> 'references'/.test(code7.applyPlan) && /'news'\s*=> 3/.test(code7.applyPlan) && /'reference'\s*=> 4/.test(code7.applyPlan) && !/\$ra = 2 -/.test(code7.applyPlan)
		&& code7.applyPlan.indexOf('self::ALL_STAGES') !== -1 && code7.applySvc.indexOf('Apply_Plan::ALL_STAGES') !== -1 && srcCode.cli.indexOf('Apply_Plan::ALL_STAGES') !== -1);
test('Faz 7 yükleyici: load_all() içerik dosyalarına HİÇ bakmaz (FILES 3 katalog dosyası); load_content() yalnız CONTENT_FILES; içerik zarfı katalogla aynı güvenli okuma yolunu kullanır',
	!/CONTENT_FILES/.test(phpFunctionBody(code7.loader, 'load_all') || '') && /CONTENT_FILES/.test(phpFunctionBody(code7.loader, 'load_content') || '') && (code7.loader.match(/'sectors\.manifest\.json'|'qualifications\.manifest\.json'|'fees\.manifest\.json'/g) || []).length === 3
		&& /'news'\s*=> 'news\.manifest\.json'/.test(code7.loader) && /'reference'\s*=> 'references\.manifest\.json'/.test(code7.loader) && (phpFunctionBody(code7.loader, 'load_content') || '').indexOf('self::load_one(') !== -1);
test('Faz 7 servis: içerik aşaması YALNIZ iki içerik dosyasını yükler (load_for_stage); haber türü çözümü yalnız Content_Dependency_Resolver uygulayan depoda; news_type_term_ids DTO anahtarı yalnız manifest news anahtarı taşıyorsa eklenir; katalog aşama DTO\'su aynı',
	/STAGE_CONTENT === \$stage/.test(phpFunctionBody(code7.service, 'load_for_stage') || '') && (phpFunctionBody(code7.service, 'load_for_stage') || '').indexOf('load_content(') !== -1 && /instanceof MaviBelge_Core_Import_Content_Dependency_Resolver/.test(code7.service)
		&& /array_key_exists\( 'news', \$manifest \)/.test(code7.service) && /current_manifest_digest\( \$stage \)/.test(code7.applySvc));
test('Faz 7 depo: iki arayüzü uygular (katalog arayüzü DEĞİŞMEDİ); haber türü çözümü salt okunur get_term_by + taksonomi doğrulaması, terim OLUŞTURMAZ; doğal anahtar post_name + çöpteki _wp_desired_post_slug; marker her zaman mb_haber/mb_referans\'ta da aranır',
	/class MaviBelge_Core_Import_WordPress_Target_Repository implements MaviBelge_Core_Import_Target_Repository, MaviBelge_Core_Import_Content_Dependency_Resolver/.test(code7.repo)
		&& /get_term_by\( 'slug', \$typeSlug, 'mb_haber_turu' \)/.test(code7.repo) && !/wp_insert_term\s*\(/.test(code7.repo) && code7.repo.indexOf('_wp_desired_post_slug') !== -1 && /'name'\s*=> \$key\['slug'\]/.test(code7.repo)
		&& code7.repo.indexOf("=> 'mb_haber',") !== -1 && code7.repo.indexOf("=> 'mb_referans',") !== -1);
test('Faz 7 doğrulayıcılar: validate_news/validate_reference kapalı şema anahtarları; haber tarihi takvim kontrolü; slug = slugify(name); dependency DTO\'da news_type_term_ids OPSİYONEL (yalnız haber/duyuru); manifest news/references OPSİYONEL',
	/const NEWS_SCHEMA_KEYS\s*= array\( 'schema_version', 'source_key', 'source_index', 'slug', 'title', 'published_on', 'news_type', 'summary', 'body', 'source' \)/.test(code7.recordValidator)
		&& /const REFERENCE_SCHEMA_KEYS = array\( 'schema_version', 'source_key', 'source_index', 'name', 'slug', 'logo_file', 'alt', 'source' \)/.test(code7.recordValidator)
		&& (phpFunctionBody(code7.recordValidator, 'validate_news') || '').indexOf('is_valid_ymd_date') !== -1 && (phpFunctionBody(code7.recordValidator, 'validate_reference') || '').indexOf('slugify_tr(') !== -1
		&& /const OPTIONAL_DEPENDENCY_KEYS = array\( 'news_type_term_ids' \)/.test(code7.recordValidator) && /const ALLOWED_DEPENDENCY_KEYS = array\( 'sector_term_ids', 'sector_image_attachment_ids', 'qualification_post_ids' \)/.test(code7.recordValidator)
		&& (phpFunctionBody(code7.recordValidator, 'validate_manifest_shape') || '').indexOf("'news', 'references'") !== -1);
test('Faz 7 planlayıcı: plan_news/plan_reference TEK plan_typed() yolundan; news/reference by_type YALNIZ içerik anahtarı taşıyan manifestte (katalog özet şekli aynı)',
	/public static function plan_news\(/.test(code7.planner) && /public static function plan_reference\(/.test(code7.planner) && /plan_typed\( self::TYPE_NEWS,/.test(code7.planner) && /plan_typed\( self::TYPE_REFERENCE,/.test(code7.planner)
		&& /if \( \$withContent \) \{/.test(code7.planner) && /\$withContent = array_key_exists\( 'news', \$manifest \) \|\| array_key_exists\( 'references', \$manifest \)/.test(code7.planner));
test('Faz 7 admin: dry-run sayfası katalog-only kaldı (yalnız run_dry_run(); content aşaması yok)', /run_dry_run\(\)/.test(srcCode.admin) && srcCode.admin.indexOf('content') === -1 || srcCode.admin.indexOf("'content'") === -1);
test('Faz 7 CLI: --stage seçenekleri content içerir, yardım metni içerik aşamasını açıklar ve iki kontrollü terimi import\'un OLUŞTURMADIĞINI belirtir',
	src.cli.indexOf(' *   - content') !== -1 && src.cli.indexOf('content = haberler sonra referanslar') !== -1 && src.cli.indexOf('import bunları oluşturmaz') !== -1 && src.cli.indexOf('--stage=content') !== -1);
test('Faz 7 meta şeması: mb_haber ve mb_referans marker+hash alanları salt-okunur/system_managed; alan repository news/reference marker biçimini doğrular; is_valid_import_source_key aynı kanonik kuraldan',
	(src.metaSchema.match(/'format'\s*=> 'import_source_key_news'/g) || []).length === 1 && (src.metaSchema.match(/'format'\s*=> 'import_source_key_reference'/g) || []).length === 1
		&& src.fieldRepo.indexOf("is_valid_import_source_key( $clean, 'news' )") !== -1 && src.fieldRepo.indexOf("is_valid_import_source_key( $clean, 'reference' )") !== -1);
test('Faz 7 Node: yeni araçlar kod ÇALIŞTIRMAZ (vm/eval/new Function/child_process/ağ yok) ve yalnız data/content altına yazar; extract-source yalnız beş sabit kaynak yolu',
	['build-content-manifest.js', 'verify-content-manifest.js', 'test-content-manifest.js', path.join('lib', 'validate-content-set.js')].every((f) => {
		const s = read7(path.join(TOOLS_IMPORT, f));
		return !/\brequire\(\s*['"](vm|child_process|http|https|net|dgram)['"]\s*\)/.test(s) && !/\beval\s*\(/.test(s) && !/\bnew\s+Function\s*\(/.test(s);
	}) && (read7(path.join(TOOLS_IMPORT, 'extract-source.js')).match(/repoRelativePath: 'tanitim-site\/assets\/data\/[a-z]+\.js'/g) || []).length === 5
		&& /const DATA_DIR = path\.join\(REPO_ROOT, 'wordpress-site', 'data'\)/.test(read7(path.join(TOOLS_IMPORT, 'build-content-manifest.js'))));
test('Faz 7 şemalar: news/reference şeması kapalı (additionalProperties=false) ve zorunlu alanlı; zarf şeması enum\'u news+reference ile genişledi, eski beş değer korunur',
	['news', 'reference'].every((n) => {
		const sc = JSON.parse(read7(path.join(DATA_DIR7, 'schema', n + '.schema.json')));
		return false === sc.additionalProperties && Array.isArray(sc.required) && sc.required.indexOf('source_key') !== -1 && sc.required.indexOf('source') !== -1;
	}) && JSON.stringify(JSON.parse(read7(path.join(DATA_DIR7, 'schema', 'manifest-envelope.schema.json'))).properties.record_type.enum) === JSON.stringify(['sector', 'qualification', 'fee', 'news', 'reference', 'mapping', 'unmatched_fee'])
		&& JSON.parse(read7(path.join(DATA_DIR7, 'schema', 'news.schema.json'))).properties.news_type.enum.join() === 'haber,duyuru');
test('Faz 7 manifestler: news.manifest.json 6 kayıt, references.manifest.json 12 kayıt; kaynak anahtarı öneki; ekran/beş katalog manifesti yerinde',
	(() => {
		const n = JSON.parse(read7(path.join(DATA_DIR7, 'content', 'news.manifest.json')));
		const r = JSON.parse(read7(path.join(DATA_DIR7, 'content', 'references.manifest.json')));
		return n.record_type === 'news' && n.count === 6 && n.records.length === 6 && n.records.every((x) => x.source_key === 'news:' + x.slug)
			&& r.record_type === 'reference' && r.count === 12 && r.records.length === 12 && r.records.every((x) => x.source_key === 'reference:' + x.slug)
			&& ['sectors', 'qualifications', 'fees'].every((f) => fs.existsSync(path.join(DATA_DIR7, 'content', f + '.manifest.json'))) && fs.existsSync(path.join(DATA_DIR7, 'mapping', 'mapping.manifest.json'));
	})());
test('Faz 7 runtime betiği: yalnız mbfx_ öneki (ön ek koruması), yalnız /tmp/mbfx- sahte manifest dizinleri; iki tür terimini kendisi wp_insert_term ile oluşturur; gerçek data/content manifestini ve MAVIBELGE_IMPORT_MANIFEST_DIR\'i kullanmaz; her serviste dizin AÇIKÇA verilir',
	/'mbfx_' !== \$wpdb->prefix/.test(runtime7) && /\$v1 = '\/tmp\/mbfx-content-manifest'/.test(runtime7) && /\$v2 = '\/tmp\/mbfx-content-manifest-v2'/.test(runtime7) && /wp_insert_term\( 'Haber', 'mb_haber_turu'/.test(runtime7) && /wp_insert_term\( 'Duyuru', 'mb_haber_turu'/.test(runtime7)
		&& stripPhpComments(runtime7).indexOf('MAVIBELGE_IMPORT_MANIFEST_DIR') === -1 && !/dirname\(|__DIR__|\/var\/www\/html\/data/.test(stripPhpComments(runtime7)) && /new MaviBelge_Core_Import_Dry_Run_Service\( \$repo, \$dir \)/.test(runtime7) && (runtime7.match(/wp_insert_term\(/g) || []).length === 3);
test('Faz 7 runtime kabloları: apply-cycle.sh 4c adımı test-3\'ü taze fixture ve sahte içerik manifestiyle çalıştırır (4b\'den SONRA, 5\'ten ÖNCE); fixture-db.php içerik manifestlerini yalnız sahte fixture fonksiyonlarıyla yazar ve rmmanifest siler',
	cycleSh7.indexOf('== 4b)') !== -1 && cycleSh7.indexOf('== 4c)') > cycleSh7.indexOf('== 4b)') && cycleSh7.indexOf('== 5)') > cycleSh7.indexOf('== 4c)') && cycleSh7.indexOf('apply-cycle-test-3.php') !== -1 && /--stage=content/.test(cycleSh7)
		&& fixtureDb7.indexOf('mb_content_fixture_write_dir') !== -1 && fixtureDb7.indexOf('mb_content_fixture_envelopes') !== -1 && fixtureDb7.indexOf('/tmp/mbfx-content-manifest-v2') !== -1 && fixtureDb7.indexOf("news.manifest.json") === -1);
test('Faz 7 testler: yeni PHP paketi mevcut, yalnız AÇIKÇA SAHTE veri (zz-test-haber-*/zz-test-ref-*) kullanır; gerçek haber slug\'ı/referans adı içermez; fixture içerik zarfları sahte notlu',
	/zz-test-haber-a/.test(suite7) && /zz-test-ref-a/.test(suite7) && !/mobilya-sektoru|bilgilendirme-subat|6-dilde-myk|Atlas End|Doruk Yap|Nova Metal|Kent Asans/.test(suite7 + fixture7) && /SAHTE içerik fixture — gerçek haber verisi değildir/.test(fixture7) && /gerçek müşteri referansı değildir/.test(fixture7));
test('Faz 7 belgeler: content-import-contract.md mevcut; türler, yönetilen alanlar, doğal anahtar, content aşaması, bağımlılık, rollback, draft gerekçesi ve NE İMPORT EDİLMEZ (lokasyon/doküman/SSS/gerçek referans) bölümleri var; tools/import/README.md kısa bölüm içerir',
	(() => {
		const d = read7(path.join(__dirname, '..', 'docs', 'content-import-contract.md'));
		const rd = read7(path.join(TOOLS_IMPORT, 'README.md'));
		return ['## 1. Kaynak ve manifest', '## 2. PHP tarafı', '## 3. Yönetilen alanlar', '### Neden `draft`', '## 4. Doğal anahtar', '## 5. Bağımlılık', '## 6. Yazma, drift ve rollback', '## 7. NE İMPORT EDİLMEZ'].every((h) => d.indexOf(h) !== -1)
			&& /Lokasyon/.test(d) && /doküman/i.test(d) && /SSS/.test(d) && /Gerçek referans/.test(d) && rd.indexOf('## Faz 7 — içerik aktarımı') !== -1;
	})());

if (failures > 0) {
	process.stderr.write('\n' + failures + '/' + total + ' Faz 6B2 statik sözleşme testi BAŞARISIZ.\n');
	process.exit(1);
}
process.stdout.write('\nTüm ' + total + ' Faz 6B2 statik sözleşme testi geçti.\n');
