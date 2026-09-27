'use strict';
/**
 * Faz 12 — `pages` aşaması ve sayfa yayınlama için statik KAYNAK-METNİ sözleşme testi (PHP çalıştırmaz).
 * Node ile PHP arasındaki paylaşılan sözlüklerin (bekleyen kurum kararı kodları/etiketleri/bloklayıcılık, HTML izin listesi,
 * kayıt sayısı, aşama zinciri) EŞİTLİĞİNİ ve yayın işleminin kapı/UI sözleşmesini doğrular.
 *
 * Run: node wordpress-site/tools/test-faz12-static-contract.js
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const IMPORT = path.join(ROOT, 'wp-content', 'plugins', 'mavibelge-core', 'includes', 'import');
const ADMIN = path.join(ROOT, 'wp-content', 'plugins', 'mavibelge-core', 'admin');
const read = (...p) => fs.readFileSync(path.join(...p), 'utf8');

const { PENDING_CODES, PAGE_INVENTORY, LAYOUT_COUNTS } = require('./import/lib/page-inventory.js');

const SRC = {
	validator: read(IMPORT, 'class-import-record-validator.php'),
	publisher: read(IMPORT, 'class-import-page-publisher.php'),
	gates: read(IMPORT, 'class-import-admin-gates.php'),
	adminSvc: read(IMPORT, 'class-import-admin-run-service.php'),
	plan: read(IMPORT, 'class-import-apply-plan.php'),
	loader: read(IMPORT, 'class-import-manifest-loader.php'),
	cli: read(IMPORT, 'class-import-cli-command.php'),
	page: read(ADMIN, 'class-import-apply-page.php'),
	js: read(ADMIN, 'assets', 'import-apply.js'),
};

let failed = 0;
let total = 0;
function test(name, ok) {
	total++;
	if (ok) {
		console.log('PASS  ' + name);
	} else {
		failed++;
		console.log('FAIL  ' + name);
	}
}

/** `const NAME = array( 'k' => v, ... );` bloğunu { k: v } olarak okur (yalnız string anahtar + true/false/string değer). */
function phpMap(code, name) {
	const m = code.match(new RegExp('const ' + name + ' = array\\(([\\s\\S]*?)\\n\\t\\);'));
	if (!m) return null;
	const out = {};
	const re = /'([a-z_]+)'\s*=>\s*(true|false|'((?:[^'\\]|\\.)*)')\s*,/g;
	let e;
	while ((e = re.exec(m[1])) !== null) {
		out[e[1]] = e[2] === 'true' ? true : e[2] === 'false' ? false : e[3];
	}
	return out;
}

const phpBlocking = phpMap(SRC.validator, 'PAGE_PENDING_DECISIONS');
const phpLabels = phpMap(SRC.publisher, 'PENDING_LABELS');
const nodeCodes = Object.keys(PENDING_CODES).sort();

test('Bekleyen karar sözlüğü: PHP kodları == Node kodları (6 kod; çözülen altı kurum kararı kodu KALDIRILDI)', phpBlocking !== null && JSON.stringify(Object.keys(phpBlocking).sort()) === JSON.stringify(nodeCodes) && nodeCodes.length === 6 && ['kvkk_text_not_approved', 'bank_details_not_approved', 'exam_calendar_url_missing', 'myk_query_url_missing', 'references_not_real', 'faq_content_not_approved'].every((c) => !nodeCodes.includes(c) && !Object.prototype.hasOwnProperty.call(phpBlocking, c)));
test('Bekleyen karar sözlüğü: bloklayıcılık PHP == Node (hiçbir kod bloklayıcı DEĞİL; yayın kapısı publish_requires ile sunucuda zorlanır)', phpBlocking !== null && nodeCodes.every((c) => phpBlocking[c] === PENDING_CODES[c].blocking) && nodeCodes.filter((c) => PENDING_CODES[c].blocking).length === 0);
test('Bekleyen karar sözlüğü: PHP yayın etiketleri == Node etiketleri (birebir)', phpLabels !== null && nodeCodes.every((c) => phpLabels[c] === PENDING_CODES[c].label) && Object.keys(phpLabels).length === nodeCodes.length);
test('Envanter: 32 sayfa; düzen dağılımı 3 hub + 21 içerik + 5 form + 3 CPT; PHP yükleyici tam 32 bekler',
	PAGE_INVENTORY.length === 32 && JSON.stringify(LAYOUT_COUNTS) === JSON.stringify({ hub: 3, default: 21, 'form-disabled': 5, 'cpt-page': 3 }) && /PAGE_EXPECTED_COUNT = 32;/.test(SRC.loader));

// HTML izin listesi eşitliği: Node sanitizeCheck ve PHP PAGE_CONTENT_TAGS.
const phpTags = (SRC.validator.match(/const PAGE_CONTENT_TAGS = array\(([^)]*)\)/) || ['', ''])[1].match(/'[a-z0-9]+'/g) || [];
const phpTagList = phpTags.map((t) => t.replace(/'/g, '')).sort();
test('HTML izin listesi: PHP PAGE_CONTENT_TAGS == kapalı küme (a, br, em, h2-h4, li, ol, p, strong, ul)', JSON.stringify(phpTagList) === JSON.stringify(['a', 'br', 'em', 'h2', 'h3', 'h4', 'li', 'ol', 'p', 'strong', 'ul']));
const pageContent = read(__dirname, 'import', 'lib', 'page-content.js');
test('HTML izin listesi: Node sanitizeCheck aynı kümeyi kullanır', phpTagList.every((t) => new RegExp("'" + t + "'").test(pageContent)) && /sanitizeCheck/.test(pageContent));

// Aşama zinciri: tek kaynak Apply_Plan::PREREQUISITE_STAGE; admin ve CLI onu kullanır, kendi zincirini tutmaz.
test('Aşama zinciri: PREREQUISITE_STAGE pages→null, sectors→pages, qualifications→sectors, all→qualifications, content→all',
	/const PREREQUISITE_STAGE = array\(\s*'pages'\s*=> null,\s*'sectors'\s*=> 'pages',\s*'qualifications'\s*=> 'sectors',\s*'all'\s*=> 'qualifications',\s*'content'\s*=> 'all',\s*\);/.test(SRC.plan));
test('Aşama zinciri: admin servisi sabitten okur; CLI aynı servisin prerequisites() yolunu çağırır (kopya harita yok)', /Apply_Plan::PREREQUISITE_STAGE/.test(SRC.adminSvc) && /Admin_Run_Service\( new MaviBelge_Core_Import_Runtime_Factory\(\) \) \)->prerequisites\(/.test(SRC.cli) && !/'sectors'\s*=>\s*'pages'/.test(SRC.adminSvc + SRC.cli));

// Yayın işlemi kapıları.
test('Yayın kapıları: preview_publish seviyesi read; publish_pages seviyesi publish ve kapalı anahtarlar (plan_digest, confirm_phrase, expected_remaining)',
	/'preview_publish'\s*=> array\( 'level' => 'read', 'keys' => array\(\) \)/.test(SRC.gates) && /'publish_pages'\s*=> array\( 'level' => 'publish', 'keys' => array\( 'plan_digest', 'confirm_phrase', 'expected_remaining' \) \)/.test(SRC.gates));
test('Yayın kapıları: publish seviyesi run kapılarının hepsini + YALNIZ staging şartını uygular (publish_staging_only)', /'run' === \$level \|\| 'publish' === \$level/.test(SRC.gates) && /'publish' === \$level && 'staging' !== \$snapshot\['environment_type'\]/.test(SRC.gates) && /'publish_staging_only'/.test(SRC.gates));
test('Yayın onayı: ifade "YAYINLA <özet ilk 12>" ve apply/rollback ifadelerinden AYRI', /YAYINLA /.test(SRC.publisher) && /publish_phrase\(/.test(SRC.gates) && /publish_phrase\(/.test(SRC.adminSvc));
test('Yayın publisher: istek başına en çok 10, expected_remaining bayat koruması, kilit + çözülmemiş run koruması, audit, transaction',
	/const BATCH_LIMIT = 10;/.test(SRC.publisher) && /stale_request/.test(SRC.publisher) && /acquire_lock\(\)/.test(SRC.publisher) && /unresolved_run_exists/.test(SRC.publisher) && /EVENT_IMPORT_PAGES_PUBLISHED/.test(SRC.publisher) && /tx->begin\(\)/.test(SRC.publisher) && /tx->rollback\(\)/.test(SRC.publisher));
test('Yayın publisher: bloklayıcı kurum kararı olan sayfa yayınlanmaz (held_pending_decision) ve KVKK/gizlilik metni yayın planına giremez', /held_pending_decision/.test(SRC.publisher) && /publish_hold/.test(SRC.publisher));
test('Yayın controller: publish_pages ignore_user_abort altında; her action için handle_* var; yayın yolu Admin_Run_Service üzerinden',
	/'advance_rollback',\s*'publish_pages'/.test(SRC.page) && /function handle_preview_publish\(/.test(SRC.page) && /function handle_publish_pages\(/.test(SRC.page) && /\$service->publish_pages\(/.test(SRC.page) && !/page_publisher\(/.test(SRC.page));

// Yayın UI (JS): innerHTML yok, karar yok; yalnız sunucu özeti.
test('Yayın JS: innerHTML/eval yok; plan_digest ve ifade sunucudan; expected_remaining sunucunun remaining değerinden (istemci hesaplamaz)',
	!/innerHTML|outerHTML|insertAdjacentHTML|document\.write|eval\(/.test(SRC.js.replace(/\/\*[\s\S]*?\*\//g, '')) && /post\( 'publish_pages'/.test(SRC.js) && /expected_remaining: expected/.test(SRC.js) && /publishLoop\( digest, data\.remaining \)/.test(SRC.js) && /el\( 'mb-publish-phrase-hint' \)\.textContent = data\.phrase/.test(SRC.js));
test('Yayın JS: bloklayıcı bekleyen kararlar tabloda görünür ([BLOKLAYICI]) ve bekletilen sayfa nedeni gösterilir', /\[BLOKLAYICI\]/.test(SRC.js) && /held_pending_decision/.test(SRC.js));
test('Yayın UI: sayfa aşaması metni zinciri gösterir; yayın bölümü ve onay alanı render edilir', /pages → sectors → qualifications → all → content/.test(SRC.page) && /mb-publish-phrase/.test(SRC.page) && /mb-preview-publish/.test(SRC.page));
test('Yayın önizlemesi: pages aşama önizlemesi kurum kararı bekleyen sayfaları notices olarak döndürür ve JS bunları görünür yapar', /'notices'\s*=> \$notices/.test(SRC.adminSvc) && /data\.notices/.test(SRC.js));

// ---- Faz 12b: içerik bağımlılığı (SSS/referans) + onaylı kaynaklar + yönlendirmeler ----
const { PUBLISH_REQUIRES } = require('./import/lib/page-inventory.js');
const writerSrc = read(IMPORT, 'class-import-wordpress-target-writer.php');
const writerIf = read(IMPORT, 'interface-import-target-writer.php');
const phpRequires = ((SRC.validator.match(/const PAGE_PUBLISH_REQUIRES = array\(([^)]*)\)/) || ['', ''])[1].match(/'[a-z]+'/g) || []).map((t) => t.replace(/'/g, '')).sort();
test('Yayın bağımlılığı: PHP PAGE_PUBLISH_REQUIRES == Node PUBLISH_REQUIRES (faq, reference); yalnız referanslar->reference ve sss->faq',
	JSON.stringify(phpRequires) === JSON.stringify(PUBLISH_REQUIRES.slice().sort()) && PAGE_INVENTORY.filter((p) => (p.publishRequires || []).length).map((p) => p.slug + ':' + p.publishRequires.join(',')).join('|') === 'referanslar:reference|sss:faq');
test('Yayın kapısı SUNUCU tarafında: publisher content_not_ready nedenini üretir; içerik bağımlılığı unchanged + YAYINDA (content_post_status) olmadan sayfa ready olamaz; yayın anında yeniden doğrulanır (TOCTOU); JS yalnız gösterir',
	/'content_not_ready'/.test(SRC.publisher) && /function requirements_met\(/.test(SRC.publisher) && /function content_type_ready\(/.test(SRC.publisher) && /'unchanged' !== \$obs\['entry'\]\['decision'\]/.test(SRC.publisher) && /content_post_status\( \$type, \$id \)/.test(SRC.publisher)
	&& /\$this->contentReadyMemo = array\(\);\s*if \( array\(\) !== \$record\['publish_requires'\] && ! \$this->requirements_met/.test(SRC.publisher) && /STAGE_CONTENT/.test(SRC.publisher) && /content_not_ready/.test(SRC.js) && !/requirements_met|content_type_ready/.test(SRC.js));
test('Yayın kapısı: yazıcı arayüzü content_post_status() tanımlar ve gerçek yazıcı yalnız faq/reference için (tür-post türü eşleşmesiyle) durum okur',
	/public function content_post_status\( \$type, \$postId \);/.test(writerIf) && /function content_post_status\( \$type, \$postId \)/.test(writerSrc) && /in_array\( \$type, array\( 'faq', 'reference' \), true \)/.test(writerSrc));
test('Logo attachment: yazıcı içerik özetiyle mevcut logoyu yeniden kullanır (aynı logo tekrar eklenmez); dosya yalnız sabit ref-NN.png kalıbıyla ve SHA-256 eşleşmesiyle bulunur; kopya/attachment/geri okuma hataları sabit kodla fail-closed; attachment silinmez',
	/LOGO_SHA_META = '_mb_import_logo_sha256'/.test(writerSrc) && /function ensure_logo_attachment\(/.test(writerSrc) && /ref-\[0-9\]\[0-9\]\.png/.test(writerSrc) && /'logo_source_missing'/.test(writerSrc) && /'logo_copy_mismatch'/.test(writerSrc) && /'logo_readback_mismatch'/.test(writerSrc) && !/wp_delete_attachment/.test(writerSrc) && !/file_get_contents\(\s*\$_(GET|POST|REQUEST)/.test(writerSrc));
test('Faz 12c yan etki telafisi: arayüz 3 kapsam yöntemi; apply ve rollback servisi telafiyi YALNIZ DB rollback SONRASI çağırır (rollback sonucu iletilir); yazıcı yalnız ad kalıbı + realpath uploads sınırı + symlink reddi + attachment yokluğu ile siler; wp_delete_attachment yok; sabit hata kodları mutlak yol içermez',
	/public function begin_side_effect_scope\(\);/.test(writerIf) && /public function commit_side_effect_scope\(\);/.test(writerIf) && /public function compensate_side_effect_scope\( \$dbRolledBack \);/.test(writerIf)
	&& /\$txRolledBack = true === \$this->tx->rollback\(\);\s*\/\/[^\n]*\n\s*\$comp = \$this->writer->compensate_side_effect_scope\( \$txRolledBack \);/.test(read(IMPORT, 'class-import-apply-service.php')) && (read(IMPORT, 'class-import-rollback-service.php').match(/compensate_side_effect_scope\( \$txRolledBack \)/g) || []).length === 2
	&& /JOURNAL_NAME_PATTERN = '\/\^mavibelge-referans-logo-\[0-9a-f\]\{12\}/.test(writerSrc) && /realpath\(/.test(writerSrc) && /is_link\( \$file \)/.test(writerSrc) && /'side_effect_attachment_still_present'/.test(writerSrc) && /'side_effect_file_referenced'/.test(writerSrc) && /'side_effect_path_rejected'/.test(writerSrc) && /'side_effect_cleanup_failed'/.test(writerSrc) && /'compensation_skipped_db_rollback_failed'/.test(writerSrc)
	&& !/wp_delete_attachment/.test(writerSrc) && !/\$this->journal\[\][^;]*\$existing/.test(writerSrc));
test('Logo doğrulaması: yükleyici her logo dosyasını (varlık, bayt, SHA-256, PNG imzası/IHDR boyutu) içe aktarımdan ÖNCE doğrular; hata metni mutlak yol içermez; mevcut durum logo_sha256 alanını GERÇEK dosya özetinden okur',
	/function verify_reference_logos\(/.test(SRC.loader) && /IHDR/.test(SRC.loader) && /hash\( 'sha256', \$bytes \)/.test(SRC.loader) && /function attachment_logo_sha256\(/.test(read(IMPORT, 'class-import-wordpress-target-repository.php')) && /@hash_file\( 'sha256', \$file \)/.test(read(IMPORT, 'class-import-wordpress-target-repository.php')));
const approvedDir = path.join(ROOT, 'data', 'sources', 'approved');
const approvedDoc = JSON.parse(fs.readFileSync(path.join(approvedDir, 'approved-sources.manifest.json'), 'utf8'));
test('Onaylı kaynaklar: yedi sayfanın beşi için künye (URL, alınma tarihi, SHA-256, fragment); dış CTA adresleri onaylı adresle BİREBİR; iframe/script/form yok',
	approvedDoc.sources.length === 5 && approvedDoc.sources.every((e) => /^https:\/\//.test(e.source_url) && /^\d{4}-\d{2}-\d{2}$/.test(e.fetched_on) && /^[0-9a-f]{64}$/.test(e.fragment_sha256))
	&& approvedDoc.sources.find((e) => e.slug === 'sinav-takvimi').cta_url === 'https://mavibelge.pratikteorik.com/home/examcalendar'
	&& approvedDoc.sources.find((e) => e.slug === 'sonuc-belge-sorgulama').cta_url === 'https://portal.myk.gov.tr/index.php?option=com_belgelendirme&view=belgelendirme_islemleri&layout=aday_bilgi_sorgu'
	&& approvedDoc.sources.every((e) => !/<(iframe|script|form)\b/i.test(fs.readFileSync(path.join(ROOT, '..', e.fragment_file), 'utf8'))));
const redirects = JSON.parse(fs.readFileSync(path.join(ROOT, 'data', 'redirects', 'redirects.manifest.json'), 'utf8'));
const ruleFor = (from) => redirects.rules.find((r) => r.from === from || r.source === from || r.old_path === from);
test('Eski hukuk sayfası yönlendirmeleri: /kvkk-2/ -> /kvkk/ ve /gizlilik-politikamiz/ -> /gizlilik-politikasi/ verified, aktif aday, 301, tek atlamalı (hedef başka bir kuralın kaynağı DEĞİL)',
	(() => {
		const a = ruleFor('/kvkk-2/');
		const b = ruleFor('/gizlilik-politikamiz/');
		if (!a || !b) {
			return false;
		}
		const tgt = (r) => r.to || r.target || r.new_path;
		const sources = new Set(redirects.rules.map((r) => r.from || r.source || r.old_path));
		return tgt(a) === '/kvkk/' && tgt(b) === '/gizlilik-politikasi/' && a.origin === 'verified' && b.origin === 'verified' && a.status === 301 && b.status === 301 && !sources.has('/kvkk/') && !sources.has('/gizlilik-politikasi/');
	})());

console.log('\n' + (total - failed) + '/' + total + ' Faz 12 statik sözleşme testi geçti.');
process.exit(failed === 0 ? 0 : 1);
