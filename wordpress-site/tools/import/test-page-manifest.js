'use strict';
/**
 * Faz 12 — `page` manifest üretici/doğrulayıcı testleri (negatif + regresyon). WordPress/ağ yok.
 * Run: node wordpress-site/tools/import/test-page-manifest.js
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const { PAGE_INVENTORY, PAGE_EXPECTED, LAYOUT_COUNTS } = require('./lib/page-inventory');
const { buildPageManifest } = require('./build-page-manifest');
const { validatePageManifest, crossCheckInventory } = require('./lib/validate-page-set');
const { sanitizeCheck } = require('./lib/page-content');
const { parseHtml } = require('./lib/html-lite');
const { toDeterministicJson } = require('./lib/hash');
const { REPO_ROOT } = require('./extract-source');

let pass = 0;
const failures = [];
function t(label, ok, detail) {
	if (ok) {
		pass++;
	} else {
		failures.push(label + (detail ? '  [' + detail + ']' : ''));
	}
}
const clone = (x) => JSON.parse(JSON.stringify(x));
const sha = (p) => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');

// 1. Envanter: tam 32; kategori sayıları 3/21/5/3.
t('envanter tam 32 kayıt', PAGE_INVENTORY.length === 32 && PAGE_EXPECTED === 32, String(PAGE_INVENTORY.length));
t('kategori sayıları hub 3 / default 21 / form 5 / cpt-page 3', JSON.stringify(LAYOUT_COUNTS) === JSON.stringify({ hub: 3, default: 21, 'form-disabled': 5, 'cpt-page': 3 }), JSON.stringify(LAYOUT_COUNTS));
t('envanterde tekrar eden slug yok', new Set(PAGE_INVENTORY.map((p) => p.slug)).size === 32);
const excluded = ['index', '404', 'meslekler', 'sektor', 'yeterlilik', 'haberler', 'duyurular', 'haber-detay', 'dokumanlar'];
t('front page/404/CPT arşiv/taksonomi/tekil rotalar envanterde YOK', excluded.every((s) => !PAGE_INVENTORY.some((p) => p.slug === s)));

// 2. Envanter kaynak (statik dizin + page-template-map.md + tema page-layouts.php) ile birebir.
const cross = crossCheckInventory();
t('envanter == statik dosyalar == page-template-map.md == tema layout haritası', cross.length === 0, cross.join(' | '));

// 3. Manifest üretimi.
const built = buildPageManifest();
t('üretim doğrulamayı geçti (ön-doğrulama hatası yok)', built.errors.length === 0, built.errors.join(' | '));
const manifest = built.manifest;
t('manifest 32 kayıt taşır, count=32', manifest.records.length === 32 && manifest.count === 32);
const REC_KEYS = ['schema_version', 'source_key', 'source_index', 'slug', 'title', 'content', 'excerpt', 'parent_source_key', 'menu_order', 'page_template', 'post_status', 'layout', 'content_sha256', 'pending_decisions', 'publish_hold', 'publish_requires', 'source'];
t('her kayıt kapalı anahtar kümesini taşır', manifest.records.every((r) => JSON.stringify(Object.keys(r)) === JSON.stringify(REC_KEYS)));
t('hedef post_status her kayıtta draft', manifest.records.every((r) => r.post_status === 'draft'));
t('source_key = page:<slug>; menu_order = sıra+1', manifest.records.every((r, i) => r.source_key === 'page:' + r.slug && r.menu_order === i + 1 && r.source_index === i));
t('başlıkta "Demo" YOK; başlıklar statik <h1> ile aynı', manifest.records.every((r) => !/demo/i.test(r.title)));
t('content_sha256 içerikten yeniden hesaplanır', manifest.records.every((r) => r.content_sha256 === crypto.createHash('sha256').update(r.content, 'utf8').digest('hex')));
t('kaynak sha256 gerçek dosyayla birebir', manifest.records.every((r) => r.source.sha256 === sha(path.join(REPO_ROOT, r.source.file))));
t('içerikte header/footer/CTA/script/form/demo/yer-tutucu/.html bağlantısı YOK', manifest.records.every((r) => !/<(script|style|form|img|svg|iframe|nav|header|footer|button|input)\b|href="#"|href="[^"]*\.html|demo|tanıtım sürümü|yazılım aşamasında|bilgi güncellenecektir|eklenecektir/i.test(r.content + ' ' + r.excerpt)));
t('12 sektör (eski) sayaç bloğu kopyalanmadı', !manifest.records.some((r) => /Yetkilendirilmiş Sektör|stat-row/.test(r.content)));
t('tüm parent_source_key null (düz URL yapısı)', manifest.records.every((r) => r.parent_source_key === null));

const APPROVED_SLUGS = ['kvkk', 'gizlilik-politikasi', 'banka-hesap-bilgileri', 'sinav-takvimi', 'sonuc-belge-sorgulama'];
const rec = (s) => manifest.records.find((x) => x.slug === s);
const textOfHtml = (h) => require('./lib/html-lite').textOf(parseHtml('<div>' + h + '</div>')).replace(/\s+/g, ' ').trim();
const fragOf = (s) => fs.readFileSync(path.join(REPO_ROOT, 'wordpress-site', 'data', 'sources', 'approved', s + '.html'), 'utf8');
t('beş onaylı sayfa: içerik dolu, bekleyen karar YOK, publish_hold false', APPROVED_SLUGS.every((s) => rec(s).content.length > 100 && rec(s).pending_decisions.length === 0 && rec(s).publish_hold === false));
t('onaylı sayfalar draft olarak planlanır (yayın ayrı işlem)', APPROVED_SLUGS.every((s) => rec(s).post_status === 'draft'));
t('KVKK/gizlilik/banka metni onaylı kaynakla kelimesi kelimesine aynı (yeniden yazım/özet yok)', ['kvkk', 'gizlilik-politikasi', 'banka-hesap-bilgileri'].every((s) => textOfHtml(rec(s).content) === textOfHtml(fragOf(s))));
t('onaylı sayfa source.file künyedeki fragment yoludur ve sha256 dosyayla birebir', APPROVED_SLUGS.every((s) => rec(s).source.file === 'wordpress-site/data/sources/approved/' + s + '.html' && rec(s).source.sha256 === sha(path.join(REPO_ROOT, rec(s).source.file))));
const approvedDoc = JSON.parse(fs.readFileSync(path.join(REPO_ROOT, 'wordpress-site', 'data', 'sources', 'approved', 'approved-sources.manifest.json'), 'utf8'));
t('künye: her kaynak için URL, alınma tarihi ve SHA-256 kayıtlı; gizli/derleme-zamanı ağ yok', approvedDoc.sources.length === 5 && approvedDoc.sources.every((e) => /^https:\/\//.test(e.source_url) && /^\d{4}-\d{2}-\d{2}$/.test(e.fetched_on) && /^[0-9a-f]{64}$/.test(e.fragment_sha256)));
t('dış CTA adresleri onaylı kaynakla BİREBİR (sınav takvimi + MYK sorgu, query string bozulmadan)', (() => {
	const cta = (s) => { const m = /<a href="([^"]*)">/.exec(rec(s).content); return m ? m[1].replace(/&amp;/g, '&') : null; };
	return cta('sinav-takvimi') === 'https://mavibelge.pratikteorik.com/home/examcalendar'
		&& cta('sonuc-belge-sorgulama') === 'https://portal.myk.gov.tr/index.php?option=com_belgelendirme&view=belgelendirme_islemleri&layout=aday_bilgi_sorgu';
})());
t('CTA sayfalarında dış sisteme gidileceği açıkça yazılır', /harici/i.test(rec('sinav-takvimi').content) && /dışındaki/i.test(rec('sonuc-belge-sorgulama').content));
t('MYK sayfası kimlik/kişisel bilgi istemez; form/iframe/script yok', !/<(form|iframe|input|script)\b/i.test(rec('sonuc-belge-sorgulama').content) && /istenmez/.test(rec('sonuc-belge-sorgulama').content));
t('hiçbir sayfada iframe/script/form yok', manifest.records.every((r) => !/<(iframe|script|form|object|embed)\b/i.test(r.content)));
t('hukuk/banka sayfalarında izin listesi dışı HTML yok', ['kvkk', 'gizlilik-politikasi', 'banka-hesap-bilgileri'].every((s) => sanitizeCheck(rec(s).content).length === 0));
t('32 sayfanın TAMAMI için kurum kararı çözülmüş: hiçbir kayıtta bloklayıcı bekleyen karar / publish_hold yok', manifest.records.every((r) => r.publish_hold === false && !r.pending_decisions.some((c) => ['kvkk_text_not_approved', 'bank_details_not_approved', 'exam_calendar_url_missing', 'myk_query_url_missing', 'references_not_real', 'faq_content_not_approved'].includes(c))));
t('yayın bağımlılıkları: referanslar -> reference, sss -> faq; başka sayfada bağımlılık yok', manifest.records.every((r) => JSON.stringify(r.publish_requires) === JSON.stringify(r.slug === 'referanslar' ? ['reference'] : r.slug === 'sss' ? ['faq'] : [])));

// 4. Deterministik: iki üretim byte-eşit.
const a = toDeterministicJson(buildPageManifest().manifest);
const b = toDeterministicJson(buildPageManifest().manifest);
t('iki üretim byte-eşit', a === b && a.length > 1000);
const onDisk = path.join(REPO_ROOT, 'wordpress-site', 'data', 'content', 'pages.manifest.json');
t('diskteki pages.manifest.json taze üretimle byte-eşit', fs.existsSync(onDisk) && fs.readFileSync(onDisk, 'utf8') === a);

// 5. Doğrulayıcı negatifleri.
function rejects(label, mutate, expectRe) {
	const m = clone(manifest);
	mutate(m);
	const r = validatePageManifest(m);
	t(label, r.length > 0 && (!expectRe || r.some((e) => expectRe.test(e))), r.slice(0, 2).join(' | '));
}
t('geçerli manifest doğrulayıcıdan sıfır hatayla geçer', validatePageManifest(clone(manifest)).length === 0);
rejects('31 kayıt reddedilir', (m) => { m.records.pop(); m.count = 31; }, /32/);
rejects('33 kayıt reddedilir', (m) => { const x = clone(m.records[0]); x.slug = 'fazla'; x.source_key = 'page:fazla'; x.source_index = 32; m.records.push(x); m.count = 33; }, /32/);
rejects('count ile kayıt sayısı uyuşmazsa reddedilir', (m) => { m.count = 31; }, /count/);
rejects('yinelenen slug reddedilir', (m) => { m.records[1].slug = m.records[0].slug; m.records[1].source_key = m.records[0].source_key; }, /tekrar|yinelen|benzersiz/i);
rejects('yinelenen source_key reddedilir', (m) => { m.records[1].source_key = m.records[0].source_key; }, /source_key/);
rejects('bilinmeyen alan reddedilir', (m) => { m.records[3].ekstra = 'x'; }, /bilinmeyen|ekstra/i);
rejects('eksik alan reddedilir', (m) => { delete m.records[3].excerpt; }, /excerpt|eksik/i);
rejects('bilinmeyen üst-seviye anahtar reddedilir', (m) => { m.fazla = 1; }, /üst-seviye|fazla/i);
rejects('kayıp parent reddedilir', (m) => { m.records[2].parent_source_key = 'page:yok-boyle-sayfa'; }, /parent/);
rejects('parent döngüsü reddedilir', (m) => { m.records[0].parent_source_key = m.records[1].source_key; m.records[1].parent_source_key = m.records[0].source_key; }, /döngü|parent/);
rejects('kendine parent reddedilir', (m) => { m.records[0].parent_source_key = m.records[0].source_key; }, /parent|döngü/);
rejects('hedef durum draft dışında reddedilir', (m) => { m.records[0].post_status = 'publish'; }, /draft|post_status/);
rejects('content_sha256 uyuşmazlığı reddedilir', (m) => { m.records[6].content_sha256 = 'a'.repeat(64); }, /content_sha256/);
rejects('kaynak sha256 uyuşmazlığı reddedilir', (m) => { m.records[6].source.sha256 = 'b'.repeat(64); }, /sha256|kaynak/);
rejects('menu_order sıra dışı reddedilir', (m) => { m.records[4].menu_order = 99; }, /menu_order/);
rejects('slug biçimi geçersizse reddedilir', (m) => { m.records[4].slug = 'Kötü Slug'; }, /slug/);
rejects('bilinmeyen bekleyen karar kodu reddedilir', (m) => { m.records[4].pending_decisions = ['uydurma_kod']; }, /pending_decisions/);
rejects('layout envanterle uyuşmazsa reddedilir', (m) => { m.records[4].layout = 'hub'; }, /layout/);
rejects('envanter dışı slug reddedilir', (m) => { m.records[4].slug = 'hakkinda'; m.records[4].source_key = 'page:hakkinda'; }, /envanter|slug/);

rejects('bilinmeyen yayın bağımlılığı reddedilir', (m) => { m.records[0].publish_requires = ['uydurma']; }, /publish_requires/);
rejects('sss yayın bağımlılığı silinirse reddedilir (envanterle eşleşmeli)', (m) => { m.records.find((x) => x.slug === 'sss').publish_requires = []; }, /publish_requires/);
rejects('yanlış sayfaya (kvkk) yayın bağımlılığı eklenirse reddedilir', (m) => { m.records.find((x) => x.slug === 'kvkk').publish_requires = ['faq']; }, /publish_requires/);
rejects('CTA adresi değiştirilirse reddedilir', (m) => { const r = m.records.find((x) => x.slug === 'sinav-takvimi'); r.content = r.content.replace('examcalendar', 'examcalendar2'); r.content_sha256 = crypto.createHash('sha256').update(r.content, 'utf8').digest('hex'); }, /CTA|eşit/);
rejects('MYK query string değişirse reddedilir', (m) => { const r = m.records.find((x) => x.slug === 'sonuc-belge-sorgulama'); r.content = r.content.replace('aday_bilgi_sorgu', 'baska'); r.content_sha256 = crypto.createHash('sha256').update(r.content, 'utf8').digest('hex'); }, /CTA|eşit/);
rejects('KVKK metni değiştirilirse reddedilir', (m) => { const r = m.records.find((x) => x.slug === 'kvkk'); r.content = r.content.replace('6698', '6699'); r.content_sha256 = crypto.createHash('sha256').update(r.content, 'utf8').digest('hex'); }, /onaylı kaynak/);
rejects('banka metni değiştirilirse reddedilir', (m) => { const r = m.records.find((x) => x.slug === 'banka-hesap-bilgileri'); r.content = r.content.replace('Şube Kodu', 'Sube Kodu'); r.content_sha256 = crypto.createHash('sha256').update(r.content, 'utf8').digest('hex'); }, /onaylı kaynak/);
rejects('onaylı sayfaya iframe eklenirse reddedilir', (m) => { const r = m.records.find((x) => x.slug === 'sinav-takvimi'); r.content += '\n<iframe src="https://x.example"></iframe>'; r.content_sha256 = crypto.createHash('sha256').update(r.content, 'utf8').digest('hex'); }, /iframe|izinsiz|eşit/);
rejects('onaylı sayfa source.file tanitim-site\'a çevrilirse reddedilir', (m) => { m.records.find((x) => x.slug === 'kvkk').source.file = 'tanitim-site/kvkk.html'; }, /source|kaynak/);

// 6. HTML/URL güvenliği (kapalı izin listesi).
const bad = {
	script: '<p>x</p><script>alert(1)</script>',
	onclick: '<p onclick="x()">a</p>',
	iframe: '<iframe src="https://e.com"></iframe>',
	img: '<p><img src="/a.png"></p>',
	style: '<p style="color:red">a</p>',
	javascript_url: '<p><a href="javascript:alert(1)">a</a></p>',
	data_url: '<p><a href="data:text/html,x">a</a></p>',
	protocol_relative: '<p><a href="//evil.com">a</a></p>',
	http_url: '<p><a href="http://example.com">a</a></p>',
	vbscript: '<p><a href="vbscript:x">a</a></p>',
	target: '<p><a href="/x/" target="_blank">a</a></p>',
	unknown_tag: '<div>a</div>',
	comment: '<p>a</p><!-- x -->',
	nested_a: '<p><a href="/a/"><a href="/b/">x</a></a></p>',
	unclosed: '<p>a',
	raw_lt: '<p>a < b</p>',
	entity_unknown: '<p>a &foo; b</p>',
	upper_tag: '<P>a</P>',
	h1: '<h1>a</h1>',
	table: '<table><tr><td>a</td></tr></table>',
};
Object.keys(bad).forEach(function (k) {
	t('tehlikeli/yasak HTML reddedilir: ' + k, sanitizeCheck(bad[k]).length > 0, bad[k]);
});
const good = '<h2>Başlık</h2>\n<p>Merhaba <strong>dünya</strong> <a href="/iletisim/">iletişim</a> ve <a href="mailto:info@mavibelge.com.tr">e-posta</a> &amp; <a href="tel:+905426196284">tel</a><br />satır</p>\n<ul>\n<li>bir</li>\n<li>iki</li>\n</ul>';
t('kanonik güvenli HTML kabul edilir', sanitizeCheck(good).length === 0, sanitizeCheck(good).join('|'));
t('HTML ayrıştırıcı iç içe yapıyı ve kaçışı doğru çözer', (() => { const d = parseHtml('<div class="a"><p>x &amp; y &#39;z&#39;</p></div>'); const p = d.children[0].children[0]; return p.tag === 'p' && p.children[0].text === "x & y 'z'"; })());

// 7. Sayılar 32'ye sabit tek kaynaktan gelir: manifest/şema/PHP yüklemesi aynı sayı.
const phpLoader = fs.readFileSync(path.join(REPO_ROOT, 'wordpress-site', 'wp-content', 'plugins', 'mavibelge-core', 'includes', 'import', 'class-import-manifest-loader.php'), 'utf8');
t('PHP loader sayfa sayısını 32 olarak bağlar', /'page'\s*=>\s*32/.test(phpLoader) || /PAGE_EXPECTED_COUNT\s*=\s*32/.test(phpLoader));

if (failures.length) {
	process.stderr.write(failures.length + ' BAŞARISIZ / ' + (pass + failures.length) + ' test:\n');
	failures.forEach((f) => process.stderr.write('  FAIL ' + f + '\n'));
	process.exit(1);
}
console.log('Tüm ' + pass + ' sayfa manifest testi geçti.');
