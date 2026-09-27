'use strict';
/**
 * Faz 6B4 — admin katalog aktarımı için statik KAYNAK-METNİ sözleşme testi. PHP ayrıştırıcısı veya WordPress runtime
 * testi DEĞİLDİR (onlar tests/run.php ve tools/runtime-test/scripts/admin-import-runtime.php'dedir); yalnız kaynakta
 * yapısal olarak DOĞRULANABİLEN güvenlik sözleşmelerini denetler: action kaydı, girişsiz/REST/`admin-post` yazma
 * yüzeyinin yokluğu, tek runtime factory, toplu apply()'ın admin'den çağrılmaması, sızıntı yok, JS'te karar mantığı yok,
 * şema/sürüm/paket tutarlılığı.
 *
 * Run: node wordpress-site/tools/test-admin-import-static-contract.js
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PLUGIN = path.join(ROOT, 'wp-content', 'plugins', 'mavibelge-core');
const read = (...p) => fs.readFileSync(path.join(...p), 'utf8');

function stripPhpComments(code) {
	return code
		.replace(/\/\*[\s\S]*?\*\//g, '')
		.split('\n')
		.map((line) => {
			const idx = line.indexOf('//');
			if (idx === -1) return line;
			// yalnız satır başındaki (girintili) yorumlar veya boşlukla ayrılmış son-satır yorumları
			return /(^|\s)\/\//.test(line) && !/['"][^'"]*\/\/[^'"]*['"]/.test(line) ? line.slice(0, line.search(/(^|\s)\/\//)) : line;
		})
		.join('\n');
}

const SRC = {
	cli: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-cli-command.php')),
	page: stripPhpComments(read(PLUGIN, 'admin', 'class-import-apply-page.php')),
	dryRunPage: stripPhpComments(read(PLUGIN, 'admin', 'class-import-dry-run-page.php')),
	gates: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-admin-gates.php')),
	adminSvc: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-admin-run-service.php')),
	factory: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-runtime-factory.php')),
	imageMap: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-sector-image-map.php')),
	imageStore: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-wp-image-map-store.php')),
	imageStoreIf: stripPhpComments(read(PLUGIN, 'includes', 'import', 'interface-import-image-map-store.php')),
	wpTx: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-wpdb-transaction.php')),
	store: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-wpdb-run-store.php')),
	snapshot: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-plan-snapshot.php')),
	applySvc: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-apply-service.php')),
	rollbackSvc: stripPhpComments(read(PLUGIN, 'includes', 'import', 'class-import-rollback-service.php')),
	plugin: stripPhpComments(read(PLUGIN, 'includes', 'class-plugin.php')),
	bootstrap: read(PLUGIN, 'mavibelge-core.php'),
	uninstall: stripPhpComments(read(PLUGIN, 'includes', 'class-uninstall-scope.php')),
};
const js = read(PLUGIN, 'admin', 'assets', 'import-apply.js').replace(/\/\*[\s\S]*?\*\//g, '').split('\n').filter((l) => !/^\s*\/\//.test(l)).join('\n');
// Saf çekirdek bölgesi: snapshot() (WordPress'ten okuma) hariç; ifade/host/kapı mantığı burada.
const gatesCore = SRC.gates.slice(0, SRC.gates.indexOf('public static function snapshot'));

let passed = 0;
let failed = 0;
function test(name, ok) {
	if (ok) {
		passed++;
		console.log(`PASS  ${name}`);
	} else {
		failed++;
		console.log(`FAIL  ${name}`);
	}
}

const ACTIONS = ['save_sector_image_map', 'preview_stage', 'start_apply', 'advance_apply', 'preview_rollback', 'start_rollback', 'advance_rollback', 'preview_publish', 'publish_pages'];

/* ---- HTTP yüzeyi ---- */
test('Admin yüzeyi: yalnız wp_ajax_ ile kayıtlı (yedi action ACTIONS sabitinden döngüyle); wp_ajax_nopriv_ HİÇ YOK', /add_action\(\s*'wp_ajax_'\s*\.\s*MaviBelge_Core_Import_Admin_Gates::ACTION_PREFIX\s*\.\s*\$action/.test(SRC.page) && !/nopriv/i.test(SRC.page));
test('Admin yüzeyi: ACTIONS sabiti tam olarak dokuz action tanımlar (kayıt, düğüm, nonce, seviye ayrı ayrı)', ACTIONS.every((a) => new RegExp("'" + a + "'\\s*=>\\s*array\\(\\s*'level'").test(SRC.gates)) && (SRC.gates.match(/'level'\s*=>/g) || []).length === 9);
test('Admin yüzeyi: her action için controller giriş noktası (handle_<action>) var', ACTIONS.every((a) => new RegExp('public static function handle_' + a + '\\(').test(SRC.page)));
test('Admin yüzeyi: REST route, admin-post.php action\'ı, doğrudan erişilebilir runner, cron veya shutdown yazımı YOK',
	!/register_rest_route|rest_api_init|admin_post_|admin-post\.php|wp_schedule_|register_shutdown_function|file_put_contents|fopen\(|exec\(|shell_exec|system\(|passthru|eval\(/.test(SRC.page + SRC.adminSvc + SRC.gates + SRC.factory));
test('Admin yüzeyi: tek dağıtıcı sırası — kapı seviyesi -> kapalı request şekli -> action nonce\'ı -> servis',
	(() => {
		const d = SRC.page.slice(SRC.page.indexOf('private static function dispatch'));
		const i1 = d.indexOf('Admin_Gates::evaluate(');
		const i2 = d.indexOf('Admin_Gates::normalize_request(');
		const i3 = d.indexOf('wp_verify_nonce(');
		const i4 = d.indexOf('new MaviBelge_Core_Import_Admin_Run_Service(');
		return i1 > 0 && i1 < i2 && i2 < i3 && i3 < i4;
	})());
test('Admin yüzeyi: nonce action\'ı action\'a ÖZEL (Admin_Gates::nonce_action($action)); tek genel nonce YOK', /wp_verify_nonce\(\s*\$request\['nonce'\],\s*MaviBelge_Core_Import_Admin_Gates::nonce_action\(\s*\$action\s*\)/.test(SRC.page) && /wp_create_nonce\(\s*MaviBelge_Core_Import_Admin_Gates::nonce_action\(\s*\$action\s*\)/.test(SRC.page));
test('Admin yüzeyi: apply/rollback başlat-ilerlet ve eşleme kaydı yalnız Admin_Run_Service üzerinden; controller repository/writer/store/$wpdb\'ye DOĞRUDAN yazmaz',
	!/\$wpdb|update_option|add_option|delete_option|set_transient|wp_insert_|wp_update_|wp_delete_|update_post_meta|update_term_meta/.test(SRC.page) && /\$service->save_image_map\(/.test(SRC.page) && /\$service->start_apply\(/.test(SRC.page) && /\$service->advance_apply\(/.test(SRC.page)
	&& /\$service->start_rollback\(/.test(SRC.page) && /\$service->advance_rollback\(/.test(SRC.page));
test('Admin yüzeyi: toplu Apply_Service::apply() ve Rollback_Service::rollback() admin dosyalarından ASLA çağrılmaz (yalnız start/advance primitive\'leri)',
	!/->apply\(\s*\$/.test(SRC.page + SRC.adminSvc) && !/->rollback\(\s*\$/.test(SRC.page + SRC.adminSvc) && /start_resumable\(/.test(SRC.adminSvc) && /advance_resumable\(/.test(SRC.adminSvc));
test('Admin yüzeyi: istemciye exception mesajı, SQL, yol veya alan değeri sızdıran çağrı YOK (getMessage/getTraceAsString/last_error/print_r/var_dump/__FILE__ yok)',
	!/getMessage|getTraceAsString|last_error|print_r|var_dump|var_export|__FILE__|ABSPATH\s*\./.test(SRC.page + SRC.adminSvc + SRC.gates));
test('Admin yüzeyi: ignore_user_abort yalnız advance action\'larında', /in_array\(\s*\$action,\s*array\(\s*'advance_apply',\s*'advance_rollback',\s*'publish_pages'\s*\)/.test(SRC.page) && /ignore_user_abort\(\s*true\s*\)/.test(SRC.page));
test('Admin yüzeyi: dry-run sayfası yalnız bölümleri render eder; kendi dry-run kodu yazma çağrısı taşımaz', /MaviBelge_Core_Import_Apply_Page::render_sections\(\)/.test(SRC.dryRunPage) && !/->(apply|rollback|start_resumable|advance_resumable)\(/.test(SRC.dryRunPage));
test('Admin yüzeyi: bir istek bir batch — advance servis çağrısı döngüsüz (controller ve Admin_Run_Service\'te while/for/foreach ile advance çağrısı YOK)',
	!/(while|for|foreach)\s*\([^)]*\)\s*\{[^}]*advance_(apply|rollback|resumable)/.test(SRC.page + SRC.adminSvc));

/* ---- kapılar ---- */
test('Kapılar: sabitler yalnız gerçek true (=== true) ile kabul edilir; bool olmayan değer açmaz', (SRC.gates.match(/true\s*!==\s*\$constants\[/g) || []).length >= 3);
test('Kapılar: evaluate() istek verisi ($_POST/$_GET/$_REQUEST/$_COOKIE) OKUMAZ; yalnız snapshot() WordPress durumunu okur ve o da istek verisi okumaz',
	!/\$_(POST|GET|REQUEST|COOKIE|FILES)/.test(SRC.gates));
test('Kapılar: production için host sabiti birebir (!==) karşılaştırılır; strtolower/rtrim/parse_url/preg ile host düzeltmesi YOK',
	/\$snapshot\['host'\]\s*!==\s*\$expectedHost/.test(SRC.gates) && !/strtolower|strtoupper|rtrim|trim\(|parse_url|idn_to/.test(gatesCore));
test('Kapılar: onay ifadesi hash_equals ile birebir karşılaştırılır; trim/strtolower/preg_replace ile SESSİZ düzeltme YOK', /hash_equals\(\s*\$expected,\s*\$submitted\s*\)/.test(SRC.gates) && !/trim\(|strtolower|strtoupper|preg_replace|str_replace/.test(gatesCore));
test('Kapılar: staging dışı ortam (local/development/boş/bilinmeyen) reddedilir; yalnız staging veya production', /'staging'\s*===\s*\$snapshot\['environment_type'\]/.test(SRC.gates) && /'production'\s*===\s*\$snapshot\['environment_type'\]/.test(SRC.gates) && /environment_not_allowed/.test(SRC.gates));

/* ---- ortak runtime factory (CLI ve admin AYNI grafik) ---- */
test('Factory: CLI komutu repository/dry-run/writer/transaction/run store/audit sınıflarını KENDİSİ kurmaz — MaviBelge_Core_Import_Runtime_Factory kullanır',
	/new MaviBelge_Core_Import_Runtime_Factory\(/.test(SRC.cli) && !/new MaviBelge_Core_Import_(WordPress_Target_Repository|Dry_Run_Service|WordPress_Target_Writer|Wpdb_Transaction|Wpdb_Run_Store|WP_Audit_Sink|Apply_Service|Rollback_Service)\(/.test(SRC.cli));
test('Factory: admin controller da yalnız factory\'den servis alır (kendi repository/writer/store kurmaz; yalnız salt okunur durum için Wpdb_Run_Store/WP_Audit_Sink örnekleri sistem kapıları bölümündedir)',
	/new MaviBelge_Core_Import_Runtime_Factory\(/.test(SRC.page) && !/new MaviBelge_Core_Import_(WordPress_Target_Repository|Dry_Run_Service|WordPress_Target_Writer|Wpdb_Transaction|Apply_Service|Rollback_Service)\(/.test(SRC.page));
test('Factory: doğrulanmamış görsel map repository\'ye verilmez (yalnız $state[\'valid\'] ise mappings, aksi hâlde boş dizi)', /\$state\['valid'\]\s*\?\s*\$state\['mappings'\]\s*:\s*array\(\)/.test(SRC.factory));
test('Factory: Admin_Run_Service faktörden gelen servisleri kullanır; kendi dry-run/planlayıcı/hash mantığı yazmaz (yeni karar motoru YOK)',
	!/Dry_Run_Planner::|Apply_Eligibility::|Write_Payload::|MaviBelge_Core_Import_Hash::|Record_Validator::/.test(SRC.adminSvc));

/* ---- görsel eşleme ---- */
test('Görsel map: option adı sabit; autoload=no (add_option/update_option \'no\') yalnız WP deposunda ve transaction içinde yazılır', /const OPTION\s*=\s*'mavibelge_core_sector_image_map'/.test(SRC.imageMap) && /add_option\(\s*\$option,\s*\$stored,\s*'',\s*'no'\s*\)/.test(SRC.imageStore) && /update_option\(\s*\$option,\s*\$stored,\s*'no'\s*\)/.test(SRC.imageStore) && !/add_option|update_option|delete_option/.test(SRC.imageMap));
test('Görsel map: yalnız validate_envelope\'tan geçen map yazılır; dosya adı/benzerlik tahmini (glob/similar_text/levenshtein/basename ile eşleme) YOK',
	/self::validate_envelope\(/.test(SRC.imageMap.slice(SRC.imageMap.indexOf('public static function save'))) && !/similar_text|levenshtein|soundex|metaphone|glob\(|scandir|get_posts|WP_Query|attachment_url_to_postid/.test(SRC.imageMap + SRC.imageStore));
test('Görsel map ATOMİKLİK: save() option yazımı + audit + commit tek transaction\'da; sonuçların HEPSİ kontrol edilir (true !== ...) ve hata halinde rollback + önbellek temizliği yapılır',
	(() => {
		const save = SRC.imageMap.slice(SRC.imageMap.indexOf('public static function save'), SRC.imageMap.indexOf('public static function audit_context'));
		const order = ['$store->ready()', '$store->begin()', '$store->write(', '$store->audit(', '$store->commit()', '$store->rollback()', '$store->flush()'].map((k) => save.indexOf(k));
		return order.every((i) => i > 0) && order.slice(0, 5).every((v, i, a) => i === 0 || v > a[i - 1]) && /true !== \$store->audit\(/.test(save) && /true !== \$store->commit\(\)/.test(save) && /true !== \$store->write\(/.test(save) && /transaction_rollback_failed/.test(save) && !/Audit_Log::record/.test(SRC.imageMap);
	})());
test('Görsel map ATOMİKLİK: WP deposu audit dönüş değerini kontrol eder, transaction sonuçlarını true ile karşılaştırır, önbelleği (options/alloptions/notoptions) temizler; hata metni/SQL döndürmez',
	/true === MaviBelge_Core_Audit_Log::record\(/.test(SRC.imageStore) && /true === \$this->tx->(begin|commit|rollback)\(\)/.test(SRC.imageStore) && /wp_cache_delete\( \$option, 'options' \)/.test(SRC.imageStore) && /'alloptions'/.test(SRC.imageStore) && /'notoptions'/.test(SRC.imageStore) && !/last_error|getMessage/.test(SRC.imageStore + SRC.imageMap));
test('Görsel map ATOMİKLİK: altyapı doğrulaması YALNIZ option + audit tabloları içindir (preflight_tables); run tabloları doğrulanmaz, kurulmaz ve store run store\'a dokunmaz',
	/preflight_tables\(\s*array\(\s*\$wpdb->options,\s*MaviBelge_Core_Audit_Log::table_name\(\)\s*\)/.test(SRC.imageStore) && !/Run_Store|ensure_installed|dbDelta|runs_table|items_table/.test(SRC.imageStore + SRC.imageMap + SRC.imageStoreIf) && /public function preflight_tables\( array \$tables \)/.test(SRC.wpTx) && /return \$this->preflight_tables\(/.test(SRC.wpTx));
test('Görsel map ATOMİKLİK: audit context kapalı (yalnız old_digest/new_digest/changed_slugs) ve gerçek değişiklik yoksa no-op (audit/transaction yok)',
	/'old_digest' => \$oldDigest, 'new_digest' => \$newDigest, 'changed_slugs' => \$changedSlugs/.test(SRC.imageMap) && /array\(\) === \$changed && \$previous\['valid'\]/.test(SRC.imageMap));

test('Görsel map: gerekli slug kümesi kod içinde sabitlenmez (10 slug adı kaynakta geçmez)', !/'makine'|'metalurji'|'lojistik'|'enerji'|'tekstil'|'insaat'|'mermer'/.test(SRC.imageMap + SRC.adminSvc + SRC.factory + SRC.page));

/* ---- run store şeması ---- */
test('Run store: TABLE_VERSION 2, üç tablo, plan_items tablosu snapshot KEYS sütunlarının hepsini taşır ve içerik sütunu taşımaz', (() => {
	const keys = ['seq', 'source_key', 'type', 'decision', 'target_id', 'expected_incoming_hash', 'expected_current_hash', 'expected_last_applied_hash', 'natural_key_check'];
	const ct = SRC.store.slice(SRC.store.indexOf('CREATE TABLE {$plan}'), SRC.store.indexOf('CREATE TABLE {$plan}') + 900);
	return /TABLE_VERSION\s*=\s*'2'/.test(SRC.store) && keys.every((k) => new RegExp('\\b' + k + '\\b').test(ct)) && !/(content|fields|payload|rollback_record|description|title)\b/.test(ct)
		&& new RegExp("KEYS\\s*=\\s*array\\(\\s*'seq'").test(SRC.snapshot);
})());
test('Run store: uninstall kapsamı yeni tabloyu ve map option\'ını içerir', /'mb_import_run_plan_items'/.test(SRC.uninstall) && /'mavibelge_core_sector_image_map'/.test(SRC.uninstall));
test('Run store/servis: compare-and-set — record_checkpoint beklenen sayaçla, transition mevcut durumla; GET_LOCK sıfır bekleme', /WHERE|'committed_batches'\s*\]\s*=\s*\(int\)\s*\$expectedBatches/.test(SRC.store) && /GET_LOCK\(%s, 0\)/.test(SRC.store) && /'rollback_batches'\s*=>\s*\$expectedBatches/.test(SRC.store));
test('Apply servisi: resumable start Plan_Snapshot::from_writes ile kapalı snapshot üretir ve run+snapshot\'ı AYNI transaction\'da oluşturur', /Plan_Snapshot::from_writes\(/.test(SRC.applySvc) && /create_run_with_plan\(/.test(SRC.applySvc));
test('Apply servisi: eski toplu apply() korunur ve ortak run_batch() primitive\'ini kullanır (CLI yolu)', /public function apply\(/.test(SRC.applySvc) && (SRC.applySvc.match(/\$this->run_batch\(/g) || []).length === 2);
test('Rollback servisi: eski toplu rollback() korunur; resumable start/advance ayrı; her advance kalan bütün item\'ları önceden doğrular', /public function rollback\(/.test(SRC.rollbackSvc) && /public function start_resumable\(/.test(SRC.rollbackSvc) && /public function advance_resumable\(/.test(SRC.rollbackSvc)
	&& /\$blockers = \$this->preflight\( \$run, \$items \);/.test(SRC.rollbackSvc.slice(SRC.rollbackSvc.indexOf('public function advance_resumable'))));

/* ---- bootstrap ---- */
test('Bootstrap: yeni sınıflar mavibelge-core.php\'de yüklenir; admin controller yalnız admin bağlamında (class-plugin.php)',
	['class-import-plan-snapshot.php', 'class-import-sector-image-map.php', 'class-import-runtime-factory.php', 'class-import-admin-gates.php', 'class-import-admin-run-service.php'].every((f) => SRC.bootstrap.includes("'includes/import/" + f + "'"))
	&& /require_once \$path \. 'admin\/class-import-apply-page\.php';/.test(read(PLUGIN, 'includes', 'class-plugin.php')) && /MaviBelge_Core_Import_Apply_Page::init\(\);/.test(SRC.plugin));
test('Bootstrap: eklenti sürümü başlıkta ve sabitte 0.5.3', /\*\s+Version:\s+0\.5\.3/.test(SRC.bootstrap) && /define\(\s*'MAVIBELGE_CORE_VERSION',\s*'0\.5\.3'\s*\)/.test(SRC.bootstrap));

/* ---- JavaScript: yalnız orkestrasyon ---- */
test('JS: innerHTML/outerHTML/insertAdjacentHTML/document.write/eval/Function( YOK — sunucu yanıtı yalnız textContent ile gösterilir', !/innerHTML|outerHTML|insertAdjacentHTML|document\.write|\beval\(|new Function|setTimeout\(\s*['"]/.test(js));
test('JS: karar mantığı YOK (hash/digest hesabı, sabit okuma, aşama sırası, checkpoint üretimi yok) — checkpoint yalnız sunucu yanıtından', !/crypto|sha256|md5|digest\s*=|window\.MAVIBELGE|typeof\s+MAVIBELGE|localStorage|sessionStorage/.test(js) && /advanceLoop\(\s*kind,\s*uid,\s*data\.checkpoint\s*\)/.test(js));
test('JS: yalnız cfg.ajaxUrl\'ye POST; action + action-özel nonce + çağıranın alanları gönderilir', (js.match(/window\.fetch\(/g) || []).length === 1 && /method:\s*'POST'/.test(js) && /body\.append\(\s*'action',\s*cfg\.actions\[\s*name\s*\]\s*\)/.test(js) && /body\.append\(\s*cfg\.nonceField,\s*cfg\.nonces\[\s*name\s*\]\s*\)/.test(js));
test('JS: çift tıklama koruması — busy bayrağı ve düğme kilidi; run listesi eylemleri kilitliyken çalışmaz', /var busy = false/.test(js) && /function lock\(\s*on\s*\)/.test(js) && (js.match(/if \( busy/g) || []).length >= 4);
test('JS: sayfa yenilenince sunucu durumundan devam — data-checkpoint / data-rollback-checkpoint özniteliklerinden', /getAttribute\( 'data-checkpoint' \)/.test(js) && /getAttribute\( 'data-rollback-checkpoint' \)/.test(js));
test('JS: WordPress Ortam Kütüphanesi (wp.media) ile görsel seçilir; dosya adı/yolu ile eşleme YOK', /window\.wp\.media\(/.test(js) && !/\.filename|\.url\s*\.match|basename/.test(js));

console.log(`\n${passed}/${passed + failed} Faz 6B4 admin statik sözleşme testi geçti.`);
process.exit(failed === 0 ? 0 : 1);
