'use strict';
/**
 * Faz 15 — ücret kataloğu runtime testi (gerçek WordPress 6.9.9 + PHP 7.3, YALNIZ mbfx_ fixture DB; gerçek HTTP + gerçek
 * headless Chrome). Canlı ile aynı başlangıç (103 taslak ücret, 2 QA ücreti elle aktif) üzerinde toplu ücret yayını sonrası:
 *  - Sınav Ücretleri sayfasının TÜM sayfaları taranır; her satır (ad, seviye, MYK kodu, sektör, her seçenek etiketi/birimi/
 *    tutarı, standart KDV/basım açıklaması, detay bağlantısı) kaynak manifest ve yayın kararıyla birebir karşılaştırılır;
 *  - arama, sektör/seviye filtreleri, sayfalama; bağlantısız satırlarda sahte kod/bağlantı yok;
 *  - Chrome'da yönetim ekranı: aranabilir yeterlilik seçimi, sektör listesi, KDV oranı, TL önizlemesi, satır ekle/taşı/sil,
 *    geçersiz girişlerin reddi (önceki veri korunur), değişikliklerin liste ve yeterlilik detayına yansıması, yetki ret,
 *    375px'te taşma yok.
 * Kullanım:
 *   node fee-catalog-test.js publish <fee-fixtures.json> <durum-dizini(çerezler)> <ekran-dizini>
 *   node fee-catalog-test.js after <after.json> <publish.json>
 */
const fs = require('fs');
const path = require('path');
const { launch } = require('./lib/cdp.js');

const BASE = 'http://127.0.0.1:18673';
const DATA = path.join(__dirname, '..', '..', 'data', 'content');
const mode = process.argv[2];
let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail !== undefined ? '  [' + String(detail).slice(0, 400) + ']' : ''));
	}
}
const readJson = (p) => JSON.parse(fs.readFileSync(p, 'utf8').replace(/^[^{]*/, ''));
const dec = (s) => String(s).replace(/&#0?39;/g, "'").replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&#8211;/g, '–').replace(/&#038;/g, '&').replace(/&amp;/g, '&');
const text = (html) => dec(String(html).replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim();
async function get(url, cookies) {
	const res = await fetch(url.indexOf('http') === 0 ? url : BASE + url, { redirect: 'manual', headers: cookies ? { cookie: cookies } : {} });
	return { status: res.status, body: await res.text(), location: res.headers.get('location') };
}
function fmt(kurus) {
	const lira = String(Math.floor(kurus / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	return lira + ',' + String(kurus % 100).padStart(2, '0') + ' TL';
}
const STATEMENT = 'Fiyatlara %20 KDV dahildir. 1.500 TL MYK belge basım ücreti fiyatlara dahil değildir.';

const fees = readJson(path.join(DATA, 'fees.manifest.json')).records;
const decisions = readJson(path.join(DATA, 'fee-publication.manifest.json')).records;
const sectors = readJson(path.join(DATA, 'sectors.manifest.json')).records;
const sectorName = Object.fromEntries(sectors.map((s) => [s.slug, s.name]));
const publishKeys = new Set(decisions.filter((d) => d.decision === 'publish').map((d) => d.source_key));
const holdKeys = decisions.filter((d) => d.decision === 'hold').map((d) => d.source_key);
const QA = ['fee:makine:3:makine-bakimci', 'fee:tekstil:2:iplik-bitim-isleri-operatoru'];

/** Tablo satırlarını ayrıştırır: ad, kod, seviye, sektör, seçenekler, açıklama, detay bağlantısı. */
function parseTable(body) {
	const t0 = body.indexOf('<table class="data-table fee-table">');
	if (t0 === -1) return [];
	const seg = body.slice(body.indexOf('<tbody>', t0), body.indexOf('</tbody>', t0));
	return (seg.match(/<tr>[\s\S]*?<\/tr>/g) || []).map((tr) => {
		const tds = (tr.match(/<td>[\s\S]*?<\/td>/g) || []).map((c) => c.replace(/^<td>|<\/td>$/g, ''));
		const cell = tds[4] || '';
		const options = [];
		const single = /<strong class="fee-amount">([^<]*)<\/strong>/.exec(cell);
		if (single) {
			const s0 = cell.indexOf('fee-single-label">');
			const s1 = cell.indexOf('<p class="fee-meta">');
			options.push({ amount: dec(single[1]).trim(), text: s0 === -1 ? '' : text(cell.slice(s0 + 'fee-single-label">'.length, s1 === -1 ? undefined : s1)) });
		} else {
			for (const li of cell.match(/<li>[\s\S]*?<\/li>/g) || []) {
				const amount = /<strong class="fee-option-amount">([^<]*)<\/strong>/.exec(li);
				const label = /<span class="fee-option-label">([\s\S]*?)<\/span>\s*<strong/.exec(li);
				options.push({ amount: amount ? dec(amount[1]).trim() : '', text: label ? text(label[1]) : '' });
			}
		}
		const meta = /<p class="fee-meta">([\s\S]*?)<\/p>/.exec(cell);
		const link = /<a href="([^"]+)">Detay →<\/a>/.exec(dec(tds[5] || ''));
		return { name: text(tds[0]), code: text(tds[1]), level: text(tds[2]), sector: text(tds[3]), options, meta: meta ? text(meta[1]) : '', link: link ? link[1] : null, detailText: text(tds[5] || ''), raw: tr };
	});
}
function optionText(o) {
	return o.label + (o.units && o.units.length ? ' (' + o.units.join(', ') + ')' : '');
}
function expectedRow(rec) {
	const opts = rec.price_options.slice().sort((a, b) => a.sort_order - b.sort_order);
	return {
		name: rec.profession_name,
		code: rec.qualification_code === '' ? 'Belirtilmemiştir' : rec.qualification_code,
		level: String(rec.level),
		sector: sectorName[rec.sector_slug],
		options: opts.map((o) => ({ amount: fmt(o.amount_kurus), text: optionText(o) })),
	};
}
function pageUrl(base, params) {
	const u = new URL(base);
	for (const [k, v] of Object.entries(params || {})) u.searchParams.set(k, v);
	return u.toString();
}
async function crawl(base, params) {
	const rows = [];
	let pages = 0;
	let first = null;
	for (let p = 1; p <= 30; p++) {
		const r = await get(pageUrl(base, Object.assign({}, params, p > 1 ? { mb_page: String(p) } : {})));
		if (p === 1) first = r;
		const got = parseTable(r.body);
		if (!got.length) break;
		pages = p;
		rows.push(...got);
		if (!/<a class="page-btn page-next"[^>]*rel="next"/.test(r.body)) break;
	}
	return { rows, pages, first };
}

async function publishMode() {
	const fx = readJson(process.argv[3]);
	const stateDir = process.argv[4];
	const shots = process.argv[5];
	fs.mkdirSync(shots, { recursive: true });
	const S = fx.preview.summary;

	/* ---- 1) yayın aracı (gerçek WordPress, fixture JSON) ---- */
	check('başlangıç (canlı ile aynı): 103 ücret kaydı, ziyaretçiye görünen 2 (QA)', fx.fees === 103 && fx.before.active_visible === 2, JSON.stringify(fx.before));
	check('yayın önizlemesi: 103 satır, hazır 101, yayında 2 (QA), bekletilen 0, sapmış 0, görünmez 0, oluşturulmamış 0',
		S.total === 103 && S.ready === 101 && S.published === 2 && S.held_pending_decision === 0 && S.not_unchanged === 0 && S.not_publicly_valid === 0 && S.not_created === 0 && S.unexpected_status === 0, JSON.stringify(S));
	check('yayın önizlemesi: karar 103 yayın / 0 bekletme; 145 seçenek; bağlantısız 19 (19\'u yayınlanabilir)', S.decision_publish === 103 && S.decision_hold === 0 && S.options_total === 145 && S.unlinked === 19 && S.unlinked_publishable === 19, JSON.stringify(S));
	const M = fx.missing;
	const REQ = ['sectors.manifest.json', 'qualifications.manifest.json', 'fees.manifest.json', 'fee-publication.manifest.json'];
	const errHas = (c, f) => c.load_errors.some((e) => e.indexOf(f) === 0);
	check('dağıtım: fees.manifest.json eksik -> catalog_manifests_unavailable + dosya adı; etkinleştirme/geri alma aynı kodla reddedildi; gerçek DB (ücret/meta/audit/run) DEĞİŞMEDİ; yol sızıntısı yok',
		M.fees.preview_ok === false && M.fees.preview_error === 'catalog_manifests_unavailable' && errHas(M.fees, 'fees.manifest.json') && M.fees.activate === 'catalog_manifests_unavailable'
		&& M.fees.deactivate === 'catalog_manifests_unavailable' && M.fees.db_unchanged && !M.fees.path_leak && JSON.stringify(M.fees.required) === JSON.stringify(REQ), JSON.stringify(M.fees));
	check('dağıtım: karar dosyası eksik -> decisions_unavailable + dosya adı; etkinleştirme reddedildi; DB DEĞİŞMEDİ',
		M.decisions.preview_error === 'decisions_unavailable' && errHas(M.decisions, 'fee-publication.manifest.json') && M.decisions.activate === 'decisions_unavailable' && M.decisions.db_unchanged && !M.decisions.path_leak, JSON.stringify(M.decisions));
	check('dağıtım: manifest dizini yok -> catalog_manifests_unavailable, üç katalog dosyası adıyla; DB DEĞİŞMEDİ',
		M.no_dir.preview_error === 'catalog_manifests_unavailable' && REQ.slice(0, 3).every((f) => errHas(M.no_dir, f)) && M.no_dir.db_unchanged && !M.no_dir.path_leak, JSON.stringify(M.no_dir));
	const P = fx.package;
	check('dağıtım paketi (dist-packages zip, açılmış): önizleme gerçek data/content ile AYNI özet ve sayılar; content/ yalnız 4 JSON + index.php; kök .htaccess + index.php',
		P && P.ok === true && P.same_digest && P.same_summary && JSON.stringify(P.content_files.slice().sort()) === JSON.stringify(REQ.concat(['index.php']).sort())
		&& JSON.stringify(P.root_files.slice().sort()) === JSON.stringify(['.htaccess', 'content', 'index.php']), JSON.stringify(P));
	const I = fx.preview.inventory;
	check('envanter (gerçek DB): sitede 103 ücret kaydı, ziyaretçiye görünen 2, manifest dışı 0; fark nedeni yok (publish_gap hepsi 0), yayındaki bekletilen 0',
		I && I.site_total === 103 && I.site_visible === 2 && I.outside_manifest === 0 && !I.truncated && Object.values(S.publish_gap).every((n) => n === 0) && S.held_but_published === 0, JSON.stringify([I, S.publish_gap]));
	check('QA kayıtları published, araçla geri alınamaz (deactivatable 0)', QA.every((k) => fx.preview.reasons[k] === 'published') && S.deactivatable === 0);
	check('yanlış onay ifadesi -> confirmation_phrase_mismatch; bayat kalan sayısı -> stale_request; görünen ücret hâlâ 2',
		fx.wrong_phrase.error_code === 'confirmation_phrase_mismatch' && fx.stale.error_code === 'stale_request' && fx.after_rejects_visible === 2, JSON.stringify([fx.wrong_phrase, fx.stale]));
	const req = fx.requests;
	check('etkinleştirme: 11 istek (10×10 + 1), hepsi ok, son durum completed', req.length === 11 && req.every((r) => r.ok) && req.slice(0, 10).every((r) => r.processed === 10 && r.status === 'paused') && req[10].processed === 1 && req[10].status === 'completed' && req[10].remaining === 0, JSON.stringify(req));
	const A = fx.preview_after.summary;
	check('etkinleştirme sonrası önizleme: hazır 0, yayında 103, geri alınabilir 101 (2 QA elle yayında, araçla geri alınamaz), özet DEĞİŞMEDİ', A.ready === 0 && A.published === 103 && A.deactivatable === 101 && fx.preview_after.digest === fx.preview.digest, JSON.stringify(A));
	check('audit: 11 adet import_fees_activated olayı', fx.audit_activated === 11, fx.audit_activated);
	check('envanter etkinleştirme sonrası: 103 kayıt, ziyaretçiye görünen 103', fx.preview_after.inventory.site_total === 103 && fx.preview_after.inventory.site_visible === 103, JSON.stringify(fx.preview_after.inventory));
	check('katalog servisi: ziyaretçiye görünen aktif ücret 103', fx.active_visible === 103, fx.active_visible);
	check('karar dosyası: 103 yayın / 0 bekletme (2026-10-20 kullanıcı onayı), karar anahtarları ücret manifestiyle birebir', publishKeys.size === 103 && holdKeys.length === 0 && fees.every((f) => publishKeys.has(f.source_key)), publishKeys.size + ' / ' + holdKeys.length);
	check('ikinci aktarım önizlemesi (önce ve sonra): 103 ücret unchanged, create 0, conflict 0 — mükerrer kayıt yok, sahte conflict yok',
		[fx.all_pre, fx.all_post].every((a) => a.fee.unchanged === 103 && a.fee.create === 0 && a.fee.conflict === 0), JSON.stringify([fx.all_pre.fee, fx.all_post.fee]));

	/* ---- 2) ziyaretçi sayfası: tam tarama ve kaynakla karşılaştırma ---- */
	const page = fx.fee_page;
	check('Sınav Ücretleri sayfası yayında ve kalıcı bağlantısı var', fx.fee_page_status === 'publish' && /^http:\/\/127\.0\.0\.1:18673\//.test(page), page);
	const all = await crawl(page, {});
	check('sayfa 1: HTTP 200, PHP uyarısı yok, "Toplam 103 ücret kaydından 1–12"', all.first.status === 200 && !/(Warning|Notice|Deprecated|Fatal error):/.test(all.first.body) && /Toplam 103 ücret kaydından 1–12 arası gösteriliyor\./.test(dec(all.first.body)), text(/<p class="results-meta">[\s\S]*?<\/p>/.exec(all.first.body) || ''));
	check('sayfalama: 9 sayfa × en çok 12 satır = 103 satır', all.pages === 9 && all.rows.length === 103, all.pages + ' / ' + all.rows.length);
	const expected = fees.filter((f) => publishKeys.has(f.source_key)).map(expectedRow);
	const keyOf = (r) => r.name + '|' + r.level + '|' + r.code;
	const expMap = new Map(expected.map((e) => [keyOf(e), e]));
	check('beklenen kümede anahtar (ad|seviye|kod) benzersiz', expMap.size === 103);
	let rowsOk = 0;
	let optionsOk = 0;
	let optionsSeen = 0;
	const bad = [];
	for (const row of all.rows) {
		const e = expMap.get(keyOf(row));
		if (!e) {
			bad.push('beklenmeyen satır: ' + keyOf(row));
			continue;
		}
		let ok = row.sector === e.sector && row.meta === STATEMENT && row.options.length === e.options.length;
		e.options.forEach((o, i) => {
			optionsSeen++;
			const g = row.options[i] || {};
			if (g.amount === o.amount && g.text === o.text) optionsOk++;
			else {
				ok = false;
				bad.push(keyOf(row) + ' seçenek ' + i + ': ' + JSON.stringify(g) + ' != ' + JSON.stringify(o));
			}
		});
		if (!ok && row.meta !== STATEMENT) bad.push(keyOf(row) + ' açıklama: ' + row.meta);
		if (ok) rowsOk++;
		expMap.delete(keyOf(row));
	}
	check('103/103 satır kaynakla birebir (ad, seviye, MYK kodu, sektör adı, seçenek sayısı, standart açıklama)', rowsOk === 103 && expMap.size === 0, bad.slice(0, 5).join(' || ') + ' eksik=' + [...expMap.keys()].slice(0, 5).join(','));
	const pubOptions = fees.filter((f) => publishKeys.has(f.source_key)).reduce((a, f) => a + f.price_options.length, 0);
	check('yayınlanan ' + pubOptions + ' fiyat seçeneğinin (145) tamamı etiket + birim + tutar olarak kaynakla birebir (KDV yeniden EKLENMEDİ: tutar = kaynak tutarı)', pubOptions === 145 && optionsSeen === pubOptions && optionsOk === pubOptions, optionsOk + '/' + optionsSeen);
	check('her satırda standart açıklama: "' + STATEMENT + '"', all.rows.every((r) => r.meta === STATEMENT), all.rows.find((r) => r.meta !== STATEMENT));
	const unlinked = all.rows.filter((r) => r.code === 'Belirtilmemiştir');
	check('bağlantısız 19 satır listede: MYK kodu "Belirtilmemiştir", detay bağlantısı YOK, satırda hiçbir <a> yok', unlinked.length === 19 && unlinked.every((r) => r.link === null && r.detailText === '—' && !/<a\s/.test(r.raw)), unlinked.length);
	const linked = all.rows.filter((r) => r.code !== 'Belirtilmemiştir');
	const qualUrls = new Set(Object.values(fx.qual_of_fee).map((q) => q.url));
	check('bağlı 84 satırın detay bağlantısı gerçek yeterlilik kalıcı bağlantısı', linked.length === 84 && linked.every((r) => r.link && qualUrls.has(r.link)), linked.filter((r) => !r.link || !qualUrls.has(r.link)).map((r) => r.name + ' ' + r.link).slice(0, 3));
	check('ücret listesinde mesleğe özel başvuru bağlantısı (meslek=) üretilmiyor', !/meslek=/.test(all.rows.map((r) => r.raw).join('')) && !/meslek=/.test(all.first.body.slice(all.first.body.indexOf('<ul class="fee-cards">'), all.first.body.indexOf('</ul>', all.first.body.indexOf('<ul class="fee-cards">')))));
	const beko = all.rows.filter((r) => r.name === 'Kazıcı Yükleyici (Beko Loder) Operatörü');
	check('Kazıcı Yükleyici (Beko Loder) Operatörü: tek satır, kodsuz ("Belirtilmemiştir") ve bağlantısız; 23.000,00 TL', beko.length === 1 && beko[0].code === 'Belirtilmemiştir' && beko[0].link === null && beko[0].options.length === 1 && beko[0].options[0].amount === '23.000,00 TL', JSON.stringify(beko.map((r) => [r.code, r.link, r.options])));
	const mobil = all.rows.filter((r) => r.code === '12UY0061-3/04');
	check('iki Mobil Vinç tarifesi listede ayrı satır, aynı kod (12UY0061-3/04), ayrı adlar ve seçenek sayıları (4 + 1)', mobil.length === 2 && mobil.some((r) => r.name === 'Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci)' && r.options.length === 4) && mobil.some((r) => r.name === 'Mobil Vinç Operatörü (İş Makineleri Grubu)' && r.options.length === 1 && r.options[0].amount === '9.500,00 TL'), JSON.stringify(mobil.map((r) => [r.name, r.options.length])));
	const cardsCount = (all.first.body.match(/<li class="fee-card">/g) || []).length;
	check('mobil kart listesi aynı veriyi taşır (sayfa 1: 12 kart, 12 satır)', cardsCount === 12, cardsCount);

	// sayfalama sınırı, arama, filtreler
	const last = await get(pageUrl(page, { mb_page: '99' }));
	check('mb_page=99 son sayfaya (9) kenetlenir; 7 satır', /Toplam 103 ücret kaydından 97–103 arası gösteriliyor\./.test(dec(last.body)) && parseTable(last.body).length === 7, text(/<p class="results-meta">[\s\S]*?<\/p>/.exec(last.body) || ''));
	const qName = await crawl(page, { mb_q: 'Kuaför' });
	check('arama (ad): "Kuaför" -> yalnız Kuaför satırı', qName.rows.length === 1 && qName.rows[0].name === 'Kuaför', qName.rows.map((r) => r.name));
	const qCode = await crawl(page, { mb_q: '16UY0244' });
	check('arama (MYK kodu): "16UY0244" -> Güzellik Uzmanı', qCode.rows.length === 1 && qCode.rows[0].name === 'Güzellik Uzmanı', qCode.rows.map((r) => r.name));
	const pubRecs = fees.filter((f) => publishKeys.has(f.source_key));
	const tekstil = await crawl(page, { mb_sector: 'tekstil' });
	check('sektör filtresi (tekstil): satır sayısı ve sektör adı kaynakla aynı', tekstil.rows.length === pubRecs.filter((f) => f.sector_slug === 'tekstil').length && tekstil.rows.every((r) => r.sector === sectorName.tekstil), tekstil.rows.length);
	const lvl4 = await crawl(page, { mb_level: '4' });
	check('seviye filtresi (4): yalnız seviye 4, sayı kaynakla aynı', lvl4.rows.length === pubRecs.filter((f) => f.level === 4).length && lvl4.rows.every((r) => r.level === '4'), lvl4.rows.length);
	const combo = await crawl(page, { mb_sector: 'is-makineleri', mb_level: '3' });
	check('birleşik filtre (iş makineleri + seviye 3) kaynakla aynı', combo.rows.length === pubRecs.filter((f) => f.sector_slug === 'is-makineleri' && f.level === 3).length, combo.rows.length);
	const noSector = await get(pageUrl(page, { mb_sector: 'yok-boyle-sektor' }));
	check('var olmayan sektör -> sıfır satır + "bulunamadı" mesajı (tüm liste gösterilmez)', parseTable(noSector.body).length === 0 && /Aramanızla eşleşen bir ücret kaydı bulunamadı/.test(dec(noSector.body)));

	// yeterlilik detayı
	const gu = fx.qual_of_fee['fee:guzellik-sac-bakim:4:guzellik-uzmani'];
	const guPage = await get(gu.url);
	check('yeterlilik detayı (Güzellik Uzmanı): ücret 17.000,00 TL + etiket/birimler + standart açıklama', guPage.status === 200 && /17\.000,00 TL/.test(guPage.body) && text(guPage.body).indexOf(STATEMENT) !== -1 && /Sınav ücreti\s*<span class="fee-option-units">\(A1, A2, A3, A4\)<\/span>/.test(guPage.body));
	const dupQual = fx.qual_of_fee['fee:is-makineleri:3:mobil-vinc-operatoru-mhc-sahil-ve-gemi-vinci'];
	const dupPage = await get(dupQual.url);
	const dupNames = (dupPage.body.match(/<p class="qual-cta-fee-name">([^<]*)<\/p>/g) || []).map((s) => dec(s.replace(/<[^>]+>/g, '')));
	check('yeterlilik detayı (12UY0061-3/04, iki ücret yayında): iki Mobil Vinç tarifesi ADLARIYLA ayrı ayrı (14.800,00 / 10.200,00 / 9.500,00 TL)',
		dupPage.status === 200 && dupNames.length === 2 && dupNames.includes('Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci)') && dupNames.includes('Mobil Vinç Operatörü (İş Makineleri Grubu)')
		&& /14\.800,00 TL/.test(dupPage.body) && /10\.200,00 TL/.test(dupPage.body) && /9\.500,00 TL/.test(dupPage.body), JSON.stringify(dupNames));

	/* ---- 3) gerçek Chrome: yönetim ekranı ve mobil ---- */
	const jar = (u) => JSON.parse(fs.readFileSync(path.join(stateDir, 'cookies-' + u + '.json'), 'utf8'));
	const b = await launch();
	const setUser = async (u) => {
		await b.send('Network.clearBrowserCookies');
		if (!u) return;
		for (const [name, value] of Object.entries(jar(u))) await b.send('Network.setCookie', { url: BASE + '/', name, value: decodeURIComponent(value), path: '/' });
	};
	const edit = (id) => BASE + '/wp-admin/post.php?post=' + id + '&action=edit';
	const save = async () => {
		await b.eval("document.getElementById('publish').click(), true");
		await new Promise((r) => setTimeout(r, 300));
		for (let i = 0; i < 40; i++) {
			await new Promise((r) => setTimeout(r, 250));
			const ready = await b.eval("document.readyState === 'complete' && /message=|post.php\\?post=/.test(location.href) && !!document.getElementById('publish')").catch(() => false);
			if (ready) break;
		}
		await new Promise((r) => setTimeout(r, 300));
	};
	const notices = () => b.eval("Array.from(document.querySelectorAll('.notice, .error, .updated')).map(n => n.textContent.replace(/\\s+/g,' ').trim()).join(' | ')");
	const rowsState = () => b.eval("Array.from(document.querySelectorAll('.mavibelge-core-price-options tbody .mavibelge-core-price-row')).map(r => ({ l: r.querySelector('.mb-label-input').value, u: r.querySelector('.mb-units-input').value, a: r.querySelector('.mb-amount-input').value, s: r.querySelector('.mb-sort-input').value, p: r.querySelector('.mb-amount-preview').textContent })).filter(r => r.l || r.a || r.u)");
	const setVal = (sel, v) => b.eval('(() => { const e = document.querySelector(' + JSON.stringify(sel) + '); e.value = ' + JSON.stringify(v) + "; e.dispatchEvent(new Event('input', {bubbles:true})); e.dispatchEvent(new Event('change', {bubbles:true})); return e.value; })()");
	try {
		await b.send('Network.enable');
		await setUser('mbadmin');
		await b.viewport(1280, 1000);
		const guId = fx.fee_ids['fee:guzellik-sac-bakim:4:guzellik-uzmani'];
		await b.goto(edit(guId));
		const ui = await b.eval(`(() => { const q = document.getElementById('_mb_qualification_id'); const s = document.getElementById('_mb_sector_slug'); const f = document.querySelector('.mavibelge-core-select-filter');
			return { qTag: q && q.tagName, qSel: q && q.options[q.selectedIndex].textContent, qCount: q && q.options.length, filterVisible: f && getComputedStyle(f).display !== 'none', sTag: s && s.tagName, sVal: s && s.value, sCount: s && s.options.length,
			vat: document.getElementById('_mb_vat_rate_percent').value, vatMax: document.getElementById('_mb_vat_rate_percent').max, cert: document.getElementById('_mb_certificate_print_fee_kurus').value, vatInc: document.getElementById('_mb_vat_included').checked,
			code: document.getElementById('_mb_qualification_code').value, stmt: (document.querySelector('.mavibelge-core-fee-statement')||{}).textContent, minmax: document.body.textContent.indexOf('17.000,00 TL') !== -1,
			addVisible: getComputedStyle(document.querySelector('.mavibelge-core-add-price-row')).display !== 'none', minInput: !!document.querySelector('[name=_mb_min_amount_kurus]') }; })()`);
		check('admin: Bağlı Yeterlilik aranabilir <select> (meslek — MYK kodu — seviye), arama kutusu JS ile görünür; 83 yeterlilik + Bağlantısız',
			ui.qTag === 'SELECT' && /^Güzellik Uzmanı — 16UY0244-4\/02 — Seviye 4$/.test(ui.qSel) && ui.qCount === 84 && ui.filterVisible, JSON.stringify(ui));
		check('admin: Sektör <select> 14 gerçek sektör + "seçin" (değer slug: guzellik-sac-bakim)', ui.sTag === 'SELECT' && ui.sCount === 15 && ui.sVal === 'guzellik-sac-bakim', JSON.stringify(ui));
		check('admin: KDV oranı alanı 20 (kayıtta yok -> varsayılan), max 100; KDV dahil işaretli; basım ücreti 1.500,00; Min./Maks. elle girilemez',
			ui.vat === '20' && ui.vatMax === '100' && ui.vatInc && ui.cert === '1.500,00' && !ui.minInput && ui.minmax, JSON.stringify(ui));
		check('admin: ziyaretçi açıklaması önizlemesi standart cümle', ui.stmt && ui.stmt.indexOf(STATEMENT) !== -1, ui.stmt);
		check('admin: + Seçenek Ekle görünür (JS)', ui.addVisible);
		for (const w of [1024, 1280, 1600]) {
			await b.viewport(w, 1000);
			await b.goto(edit(guId));
			const lay = await b.eval(`(() => { const box = document.getElementById('mavibelge_core_fields'); const br = box.getBoundingClientRect(); const inside = (el) => { const r = el.getBoundingClientRect(); return r.left >= br.left - 1 && r.right <= br.right + 1; };
				const row = document.querySelector('.mavibelge-core-price-options tbody .mavibelge-core-price-row'); const fields = ['.mb-label-input', '.mb-units-input', '.mb-amount-input'].map(s => row.querySelector(s));
				const th = document.querySelector('.mavibelge-core-price-options-table thead th.mb-col-units');
				return { boxOver: box.scrollWidth - box.clientWidth, docOver: document.documentElement.scrollWidth - document.documentElement.clientWidth,
					selectInside: inside(document.getElementById('_mb_qualification_id')), filterInside: inside(document.querySelector('.mavibelge-core-select-filter')), fieldsInside: fields.every(inside),
					widths: fields.map(f => Math.round(f.getBoundingClientRect().width)), amountClipped: fields[2].scrollWidth > fields[2].clientWidth + 1,
					headH: th && getComputedStyle(th).display !== 'none' ? Math.round(th.getBoundingClientRect().height) : 0,
					offenders: Array.from(box.querySelectorAll('*')).filter(e => e.getBoundingClientRect().right > br.right + 1 && e.getBoundingClientRect().width > 0).slice(0, 5).map(e => e.tagName + '.' + (e.className || '') + '#' + (e.id || '') + ':' + Math.round(e.getBoundingClientRect().right - br.right)) }; })()`);
			check('admin ' + w + 'px: meta kutusu ve sayfa yatay taşmaz; yeterlilik seçimi/arama ve fiyat alanları kutu içinde; etiket ≥150, birim ≥100, tutar ≥120 px; tutar kesilmez; başlık satırı dikey kırılmaz',
				lay.boxOver <= 1 && lay.docOver <= 0 && lay.selectInside && lay.filterInside && lay.fieldsInside && lay.widths[0] >= 150 && lay.widths[1] >= 100 && lay.widths[2] >= 120 && !lay.amountClipped && lay.headH < 60, JSON.stringify(lay));
			await b.screenshot(path.join(shots, 'admin-ucret-' + w + '-once.png'), true);
		}
		await b.viewport(1280, 1000);
		await b.goto(edit(guId));
		await setVal('.mavibelge-core-select-filter', '16UY0244');
		const filtered = await b.eval("(() => { const q = document.getElementById('_mb_qualification_id'); return { n: q.options.length, sel: q.value, texts: Array.from(q.options).map(o => o.textContent) }; })()");
		check('admin: yeterlilik araması MYK koduyla daraltır, seçili değer korunur', filtered.n <= 3 && filtered.n >= 2 && filtered.texts.some((t) => /16UY0244/.test(t)) && String(filtered.sel) === String(fx.qual_of_fee['fee:guzellik-sac-bakim:4:guzellik-uzmani'].id), JSON.stringify(filtered));
		await setVal('.mavibelge-core-select-filter', '');
		// TL önizlemesi
		await setVal('.mavibelge-core-price-options tbody .mb-amount-input', '18.250');
		let rs = await rowsState();
		check('admin: TL önizlemesi "18.250" -> "18.250,00 TL"', rs[0].p === '18.250,00 TL', JSON.stringify(rs[0]));
		await setVal('.mavibelge-core-price-options tbody .mb-amount-input', '18.25');
		rs = await rowsState();
		check('admin: TL önizlemesi geçersiz biçimi gösterir ("18.25")', /Geçersiz/.test(rs[0].p), rs[0].p);
		// A) geçerli fiyat + KDV oranı + basım ücreti değişikliği
		await setVal('.mavibelge-core-price-options tbody .mb-amount-input', '18.250');
		await setVal('#_mb_vat_rate_percent', '18');
		await setVal('#_mb_certificate_print_fee_kurus', '2.000');
		await save();
		let after = await b.eval("({ a: document.querySelector('.mavibelge-core-price-options tbody .mb-amount-input').value, vat: document.getElementById('_mb_vat_rate_percent').value, cert: document.getElementById('_mb_certificate_print_fee_kurus').value, stmt: document.querySelector('.mavibelge-core-fee-statement').textContent, status: document.getElementById('post_status') ? document.getElementById('post_status').value : '' })");
		check('admin kaydı: fiyat 18.250,00, KDV %18, basım 2.000,00 kalıcı; açıklama önizlemesi kayıttan üretildi; kayıt yayında kaldı',
			after.a === '18.250,00' && after.vat === '18' && after.cert === '2.000,00' && after.stmt.indexOf('Fiyatlara %18 KDV dahildir. 2.000 TL MYK belge basım ücreti fiyatlara dahil değildir.') !== -1 && after.status === 'publish', JSON.stringify(after));
		await b.screenshot(path.join(shots, 'admin-ucret-1280.png'), true);
		const q2 = await crawl(page, { mb_q: 'Güzellik Uzmanı' });
		check('liste: admin değişikliği yansıdı (18.250,00 TL + "%18 ... 2.000 TL ..." açıklaması) — önbellek geçersizleşti',
			q2.rows.length === 1 && q2.rows[0].options[0].amount === '18.250,00 TL' && q2.rows[0].meta === 'Fiyatlara %18 KDV dahildir. 2.000 TL MYK belge basım ücreti fiyatlara dahil değildir.', JSON.stringify(q2.rows[0] && { o: q2.rows[0].options, m: q2.rows[0].meta }));
		const guPage2 = await get(gu.url);
		check('yeterlilik detayı: admin değişikliği yansıdı (18.250,00 TL, %18)', /18\.250,00 TL/.test(guPage2.body) && !/17\.000,00 TL/.test(guPage2.body) && text(guPage2.body).indexOf('Fiyatlara %18 KDV dahildir.') !== -1);
		// C) geçersiz tutar -> hiçbir şey yazılmaz
		await setVal('.mavibelge-core-price-options tbody .mb-amount-input', '18.25');
		await save();
		let n = await notices();
		after = await b.eval("document.querySelector('.mavibelge-core-price-options tbody .mb-amount-input').value");
		check('admin: geçersiz TL ("18.25") reddedilir, anlaşılır hata, önceki 18.250,00 korunur', /geçerli bir TL tutarı girilmedi/.test(n) && /önceki liste korundu/.test(n) && after === '18.250,00', n.slice(0, 300) + ' / ' + after);
		// D) yalnız birim yazılmış satır -> atomik ret
		await b.eval("(() => { const rows = document.querySelectorAll('.mavibelge-core-price-options tbody .mavibelge-core-price-row'); const r = rows[rows.length - 1]; r.querySelector('.mb-units-input').value = 'B9'; return true; })()");
		await save();
		n = await notices();
		rs = await rowsState();
		check('admin: yalnız birim yazılmış satır reddedilir (hata görünür), seçenek listesi değişmez', /Birim girilmiş bir satırda/.test(n) && rs.length === 1 && rs[0].a === '18.250,00', n.slice(0, 300) + ' ' + JSON.stringify(rs));
		// G) sektör manipülasyonu -> önceki değer korunur
		await b.eval("(() => { const s = document.getElementById('_mb_sector_slug'); const o = document.createElement('option'); o.value = 'yok-boyle-sektor'; o.textContent = 'x'; s.appendChild(o); s.value = 'yok-boyle-sektor'; return s.value; })()");
		await save();
		n = await notices();
		const sec = await b.eval("document.getElementById('_mb_sector_slug').value");
		check('admin: gerçek terime çözülmeyen sektör yazılmaz, uyarı + önceki değer (guzellik-sac-bakim)', /Sektör: seçilen değer mevcut bir sektöre karşılık gelmiyor/.test(n) && sec === 'guzellik-sac-bakim', n.slice(0, 200) + ' ' + sec);
		// E) çok seçenekli kayıtta satır ekle / yukarı taşı / sil + min/max
		const multiKey = fees.find((f) => f.price_options.length > 2 && publishKeys.has(f.source_key) && !QA.includes(f.source_key)).source_key;
		const multiRec = fees.find((f) => f.source_key === multiKey);
		await b.goto(edit(fx.fee_ids[multiKey]));
		const before = await rowsState();
		await b.eval("document.querySelector('.mavibelge-core-add-price-row').click(), true");
		await b.eval("document.querySelector('.mavibelge-core-add-price-row').click(), true");
		const added = await b.eval("(() => { const rows = Array.from(document.querySelectorAll('.mavibelge-core-price-options tbody .mavibelge-core-price-row')); const names = rows.map(r => r.querySelector('.mb-label-input').name); return { n: rows.length, unique: new Set(names).size === names.length, focus: document.activeElement && document.activeElement.className }; })()");
		check('admin: + Seçenek Ekle iki satır ekler, satır adları benzersiz (silme sonrası çakışma yok), odak yeni etikette', added.unique && /mb-label-input/.test(added.focus), JSON.stringify(added));
		await b.eval("(() => { const rows = document.querySelectorAll('.mavibelge-core-price-options tbody .mavibelge-core-price-row'); const r = rows[rows.length - 1]; r.querySelector('.mavibelge-core-remove-price-row').click(); return true; })()");
		await b.eval("(() => { const rows = document.querySelectorAll('.mavibelge-core-price-options tbody .mavibelge-core-price-row'); const r = rows[rows.length - 1]; r.querySelector('.mb-label-input').value = 'TEST ek seçenek'; r.querySelector('.mb-units-input').value = 'Z1, Z2'; const a = r.querySelector('.mb-amount-input'); a.value = '1.234,56'; a.dispatchEvent(new Event('input', {bubbles:true})); for (let i = 0; i < 30; i++) { const up = r.querySelector('.mavibelge-core-move-up'); if (!r.previousElementSibling) break; up.click(); } return true; })()");
		const moved = await rowsState();
		check('admin: yeni satır en üste taşındı, sıra alanları 0..n yeniden numaralandı, önizleme 1.234,56 TL', moved[0].l === 'TEST ek seçenek' && moved[0].s === '0' && moved[0].p === '1.234,56 TL' && moved.every((r, i) => r.s === String(i)), JSON.stringify(moved));
		await save();
		const saved = await rowsState();
		const minmaxTxt = await b.eval("document.body.textContent");
		check('admin kaydı: ' + (before.length + 1) + ' seçenek, yeni seçenek ilk sırada; Min. tutar seçeneklerden türetildi (1.234,56 TL)',
			saved.length === before.length + 1 && saved[0].l === 'TEST ek seçenek' && saved[0].a === '1.234,56' && /1\.234,56 TL/.test(minmaxTxt), JSON.stringify(saved));
		const q3 = await crawl(page, { mb_q: multiRec.profession_name });
		const q3row = q3.rows.find((r) => r.level === String(multiRec.level));
		check('liste: eklenen seçenek ilk sırada ve doğru tutarla görünüyor', q3row && q3row.options[0].text === 'TEST ek seçenek (Z1, Z2)' && q3row.options[0].amount === '1.234,56 TL' && q3row.options.length === multiRec.price_options.length + 1, JSON.stringify(q3row && q3row.options));
		// F) bağlantısız kayıtta yeterlilik seçimi ipucu (kaynak MYK kodu değişmez)
		const unlinkedKey = fees.find((f) => f.qualification_code === '' && publishKeys.has(f.source_key)).source_key;
		await b.goto(edit(fx.fee_ids[unlinkedKey]));
		const hint0 = await b.eval("document.querySelector('.mavibelge-core-myk-hint').textContent");
		await b.eval("(() => { const q = document.getElementById('_mb_qualification_id'); q.selectedIndex = 1; q.dispatchEvent(new Event('change', {bubbles:true})); return true; })()");
		const hint1 = await b.eval("({ h: document.querySelector('.mavibelge-core-myk-hint').textContent, code: document.getElementById('_mb_qualification_code').value })");
		check('admin: bağlantısız kayıtta "Bağlantısız" ipucu; yeterlilik seçilince MYK kodu ipucu görünür ama Kaynak MYK Kodu alanı DEĞİŞMEZ (boş kalır)', /Bağlantısız/.test(hint0) && /MYK kodu/.test(hint1.h) && hint1.code === '', JSON.stringify([hint0, hint1]));
		// mobil admin
		await b.viewport(375, 900);
		await b.goto(edit(fx.fee_ids[multiKey]));
		const mob = await b.eval("(() => { const c = document.querySelector('.mavibelge-core-price-options'); const a = document.querySelector('.mb-amount-input'); const l = document.querySelector('.mb-label-input'); return { cOver: c.scrollWidth - c.clientWidth, aOver: a.scrollWidth - a.clientWidth, aW: a.getBoundingClientRect().width, lW: l.getBoundingClientRect().width, headHidden: getComputedStyle(document.querySelector('.mavibelge-core-price-options-table thead')).display === 'none' }; })()");
		check('admin 375px: fiyat tablosu taşmaz (kart düzeni), tutar ve etiket alanları okunur genişlikte, tutar metni kesilmez', mob.cOver <= 1 && mob.aOver <= 1 && mob.aW >= 200 && mob.lW >= 200 && mob.headHidden, JSON.stringify(mob));
		await b.screenshot(path.join(shots, 'admin-ucret-375.png'), true);
		const adminErrors = b.consoleErrors.filter((e) => e.indexOf('/favicon.ico') === -1);
		check('tarayıcı konsolu (yönetici oturumu, tüm düzenleme senaryoları): hata yok', adminErrors.length === 0, adminErrors.join(' | '));
		b.consoleErrors.length = 0;
		// yetki: fiyat editörü ve abone
		await setUser('mbpriceed');
		await b.viewport(1280, 1000);
		await b.goto(edit(guId));
		const denied = await b.eval("({ form: !!document.getElementById('post'), body: document.body.textContent.replace(/\\s+/g,' ').slice(0, 300) })");
		check('yetki: fiyat editörü başkasının yayındaki ücretini düzenleyemez (form yok)', !denied.form, denied.body);
		await b.goto(BASE + '/wp-admin/edit.php?post_type=mb_ucret&page=mavibelge-core-active-period');
		const period = await b.eval("({ input: !!document.querySelector('input[name=mb_active_tariff_period]'), body: document.body.textContent.replace(/\\s+/g,' ').slice(0, 200) })");
		check('yetki: fiyat editörü aktif tarife dönemi ekranına erişemez', !period.input, period.body);
		await b.goto(edit(fx.price_editor_own));
		await b.eval("(() => { document.getElementById('title').value = 'TEST Fiyat Editörü Taslağı'; const s = document.getElementById('_mb_record_status'); Array.from(s.options).forEach(o => o.disabled = false); s.value = 'active'; const r = document.querySelector('.mavibelge-core-price-options tbody .mavibelge-core-price-row'); r.querySelector('.mb-label-input').value = 'Sınav ücreti'; r.querySelector('.mb-amount-input').value = '1.000'; return true; })()");
		const saveBtn = await b.eval("(document.getElementById('save-post') ? 'save-post' : 'publish')");
		await b.eval("document.getElementById(" + JSON.stringify(saveBtn) + ").click(), true");
		await new Promise((r) => setTimeout(r, 800));
		for (let i = 0; i < 60; i++) {
			const ready = await b.eval("document.readyState === 'complete' && !!document.getElementById('_mb_record_status') && !!document.querySelector('.mavibelge-core-price-options tbody .mb-amount-input')").catch(() => false);
			if (ready) break;
			await new Promise((r) => setTimeout(r, 250));
		}
		const own = await b.eval("({ st: document.getElementById('_mb_record_status').value, n: Array.from(document.querySelectorAll('.notice, .error, .updated')).map(n => n.textContent.replace(/\\s+/g,' ').trim()).join(' | '), a: document.querySelector('.mavibelge-core-price-options tbody .mb-amount-input').value })");
		check('yetki: fiyat editörü kendi taslağında fiyat kaydedebilir ama Kayıt Durumu "Aktif" sunucuda geri alınır (draft) + hata bildirimi',
			own.st === 'draft' && /aktif veya arşivlenmiş yapma yetkiniz yok/.test(own.n) && own.a === '1.000,00', JSON.stringify(own));
		await setUser('mbsubscriber');
		await b.goto(edit(guId));
		const sub = await b.eval("({ form: !!document.getElementById('post') })");
		check('yetki: abone ücret düzenleme formuna erişemez', !sub.form);
		// Sınırlı rollerin konsolu (yalnız BİLGİ, kapı değil): Faz 10 REST sertleştirmesi list_users yetkisi olmayan oturumda
		// /wp/v2/users uçlarını kaldırır; WordPress çekirdek admin betiğinin users/me isteği 404/403 alır (bu işten bağımsız).
		fs.writeFileSync(path.join(shots, 'sinirli-rol-konsol.txt'), b.consoleErrors.join('\n') + '\n');
		b.consoleErrors.length = 0;
		// ziyaretçi mobil/masaüstü
		await setUser(null);
		for (const w of [375, 768, 1280]) {
			await b.viewport(w, 900);
			await b.goto(page);
			const v = await b.eval("(() => { const t = document.querySelector('.fee-table-wrap'); const c = document.querySelector('.fee-cards'); const d = document.documentElement; return { over: d.scrollWidth - d.clientWidth, table: getComputedStyle(t).display, cards: getComputedStyle(c).display, visibleCards: Array.from(document.querySelectorAll('.fee-card')).filter(e => e.offsetParent !== null).length, overflowing: Array.from(document.querySelectorAll('.fee-card, .fee-table td')).filter(e => e.offsetParent !== null && e.scrollWidth > e.clientWidth + 1).length }; })()");
			const mobile = w <= 768;
			check('ziyaretçi ' + w + 'px: yatay taşma yok; ' + (mobile ? 'kartlar görünür (12), tablo gizli' : 'tablo görünür, kartlar gizli') + '; hücre/kart taşması yok',
				v.over <= 0 && v.overflowing === 0 && (mobile ? v.cards !== 'none' && v.table === 'none' && v.visibleCards === 12 : v.table !== 'none' && v.cards === 'none'), JSON.stringify(v));
			await b.screenshot(path.join(shots, 'sinav-ucretleri-' + w + '.png'), true);
		}
		const realErrors = b.consoleErrors.filter((e) => e.indexOf('/favicon.ico') === -1 && !(e.indexOf('status of 403') !== -1 && (e.indexOf('/wp-admin/post.php') !== -1 || e.indexOf('/wp-admin/edit.php') !== -1)));
		check('tarayıcı konsolu (ziyaretçi, 375/768/1280): hata yok', realErrors.length === 0, realErrors.join(' | '));
		fs.writeFileSync(path.join(shots, 'edited.json'), JSON.stringify({ edited: ['fee:guzellik-sac-bakim:4:guzellik-uzmani', multiKey] }));
	} finally {
		await b.close();
	}
}

async function afterMode() {
	const fx = readJson(process.argv[3]);
	const pub = readJson(process.argv[4]);
	const edited = JSON.parse(fs.readFileSync(process.argv[5], 'utf8')).edited;
	const conflicts = Object.keys(fx.all.fee_conflicts).sort();
	check('admin düzenlemesi sonrası aktarım önizlemesi: yalnız panelden değiştirilen 2 ücret conflict (manual_edit_detected), 101 unchanged, create 0 — sessizce ezilmez',
		conflicts.join(',') === edited.slice().sort().join(',') && conflicts.every((k) => fx.all.fee_conflicts[k] === 'manual_edit_detected') && fx.all.fee.unchanged === 101 && fx.all.fee.create === 0 && fx.all.eligible === false, JSON.stringify(fx.all));
	check('yayın önizlemesi: değiştirilen 2 kayıt not_unchanged (araç dokunmaz)', edited.every((k) => fx.preview.reasons[k] === 'not_unchanged'), JSON.stringify(edited.map((k) => fx.preview.reasons[k])));
	const IV = fx.preview.inventory;
	check('gerçek ortam farkı: uygun-ama-hazır-değil nedeni "sapmış 2" (aktarım kararı conflict 2); envanterde manifest dışı 1 (fiyat editörünün TEST taslağı, görünmez) — araç dokunmaz',
		fx.preview.summary.publish_gap.not_unchanged === 2 && fx.preview.summary.publish_gap.not_created === 0 && JSON.stringify(fx.preview.summary.not_unchanged_by_import_decision) === '{"conflict":2}' && IV.outside_manifest === 1 && IV.outside_manifest_visible === 0
		&& IV.outside_rows.length === 1 && IV.outside_rows[0].has_source_key === false && IV.site_total === 104, JSON.stringify([fx.preview.summary.publish_gap, IV]));
	const d = fx.deactivate;
	const processed = d.reduce((a, r) => a + r.processed, 0);
	check('geri alma: yalnız aracın etkinleştirdiği ve değişmemiş 99 ücret taslağa alındı (10 istek), audit 10 olay', processed === 99 && d.length === 10 && d.every((r) => r.ok) && d[d.length - 1].status === 'completed' && fx.audit_deactivated === 10, JSON.stringify(d));
	const S = fx.preview_after_deactivate.summary;
	check('geri alma sonrası: hazır 99 (yeniden yayınlanabilir), yayında 2 QA, sapmış 2, bekletilen 0', S.ready === 99 && S.published === 2 && S.not_unchanged === 2 && S.held_pending_decision === 0 && S.deactivatable === 0, JSON.stringify(S));
	check('geri alma sonrası: QA kayıtları ve elle değiştirilenler yayında; diğerleri draft/draft, işaret yok',
		QA.every((k) => fx.states[k].post_status === 'publish') && edited.every((k) => fx.states[k].post_status === 'publish')
		&& Object.keys(fx.states).filter((k) => !QA.includes(k) && !edited.includes(k)).every((k) => fx.states[k].post_status === 'draft' && fx.states[k].record_status === 'draft' && !fx.states[k].marker));
	check('katalog: görünen ücret 4 (2 QA + 2 elle değiştirilmiş yayında)', fx.active_visible === 4, fx.active_visible);
	const list = await get(pub.fee_page);
	check('ziyaretçi: Sınav Ücretleri "Toplam 4 ücret kaydından"', /Toplam 4 ücret kaydından 1–4 arası gösteriliyor\./.test(dec(list.body)) && parseTable(list.body).length === 4);
	check('eski (0.5.4) hash biçimli, içeriği değişmemiş kayıt aktarım önizlemesinde unchanged (canlıdaki 101 taslak kayıt bu durumda)', fx.legacy_decision === 'unchanged', fx.legacy_decision);
}

(async () => {
	if (mode === 'publish') await publishMode();
	else if (mode === 'after') await afterMode();
	else {
		console.log('Kullanım: publish <fixtures.json> <durum> <ekranlar> | after <after.json> <publish.json> <edited.json>');
		process.exit(2);
	}
	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 15 ücret kataloğu runtime testi ' + (fail ? 'BAŞARISIZ' : 'geçti') + '.');
	// process.exit() yerine exitCode: Windows'ta açık fetch soketleri kapanırken libuv iddia hatasıyla çökmeyi önler.
	process.exitCode = fail ? 1 : 0;
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + (e && e.stack ? e.stack : e));
	process.exitCode = 1;
});
