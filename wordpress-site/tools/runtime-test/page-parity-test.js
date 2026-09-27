'use strict';
/**
 * Faz 13 — referans sayfa aileleri: 14 statik referans rotasının GERÇEK WordPress render'ı (içe aktarılmış gerçek sayfa
 * içeriği + sentetik haber/duyuru/doküman) ve GERÇEK headless Chrome. YALNIZ mbfx_ fixture klonu (pages-render.sh).
 *
 *   node page-parity-test.js <http|browser> <permalink-etiketi: pretty|index> <ekran-dizini>
 *     http    : kahraman (kırıntı/eyebrow/H1/lead), aile gövdesi, korunan veri/sorgu/süzgeç/sayfalama, form kapısı,
 *               iç bağlantıların etkin kalıcı bağlantı yapısına uygunluğu (her iç bağlantı 200), başlık sırası
 *     browser : 390x844 / 1024x768 / 1279 / 1280 / 1440x900 — başlık geometrisi (1279 hamburger, 1280 masaüstü), yatay taşma,
 *               ızgara sütunları, tablo/kart taşması, klavye odağı, reduced-motion, konsol + başarısız kaynak
 */
const fs = require('fs');
const path = require('path');
const cp = require('child_process');
const { launch } = require('./lib/cdp.js');
const { PROBE } = require('./lib/header-geometry.js');

const [, , MODE, PL, SHOTS] = process.argv;
const BASE = 'http://127.0.0.1:18673';
const PFX = PL === 'index' ? '/index.php' : '';
const ROOT = path.join(__dirname, '..', '..');
const manifest = JSON.parse(fs.readFileSync(path.join(ROOT, 'data', 'content', 'pages.manifest.json'), 'utf8'));
const rec = {};
manifest.records.forEach((r) => (rec[r.slug] = r));
let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  [' + PL + '] ' + label + (detail !== undefined ? '  [' + String(detail).slice(0, 300) + ']' : ''));
	}
}
const count = (s, re) => (s.match(re) || []).length;
const dec = (s) => String(s).replace(/&#0?38;|&amp;/g, '&').replace(/&#8217;|&#039;/g, "'").replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
const url = (p) => BASE + PFX + '/' + p.replace(/^\/|\/$/g, '') + '/';
async function get(u) {
	const r = await fetch(u, { redirect: 'manual' });
	return { status: r.status, body: await r.text(), location: r.headers.get('location') || '' };
}
const mainOf = (b) => b.slice(b.indexOf('<main'), b.indexOf('</main>'));

const SIB = { label: 'Sınav ve Başvuru', path: 'sinav-ve-basvuru' };
const BM = { label: 'Bilgi Merkezi', path: 'bilgi-merkezi' };
const KUR = { label: 'Kurumsal', path: 'kurumsal' };
const MVB = { label: 'Meslekler ve Belgeler', path: 'yeterlilikler' };
const PAGES = {
	'sinav-surecleri': { fam: 'list', width: 'wide', parent: SIB, cta: ['Online Başvuru Yap', 'online-basvuru'] },
	'banka-hesap-bilgileri': { fam: 'card', width: 'narrow', parent: SIB, lead: 'Sınav ve belgelendirme ücreti ödemeleri için banka hesap bilgileri.' },
	'yetki-akreditasyon': { fam: 'cards', width: 'wide', parent: KUR },
	mevzuat: { fam: 'list', width: 'prose', parent: BM },
	myk: { fam: 'prose', width: 'prose', parent: BM },
	turkak: { fam: 'prose', width: 'prose', parent: BM },
	'nasil-basvururum': { fam: 'steps', width: 'wide', parent: SIB, cta: ['Şimdi Başvur', 'online-basvuru'] },
	'sinav-takvimi': { fam: 'card', width: 'narrow', parent: SIB },
	'sonuc-belge-sorgulama': { fam: 'card', width: 'narrow', parent: SIB },
	'itiraz-sikayet': { fam: 'split', width: 'wide', parent: SIB },
	'belge-yenileme': { fam: 'cards', width: 'wide', parent: MVB, cta: ['Belge Yenileme Başvurusu Yap', 'online-basvuru'] },
};

function hero(body) {
	const i = body.indexOf('<section class="page-hero"');
	return i < 0 ? '' : body.slice(i, body.indexOf('</section>', i));
}
function checkHero(label, body, crumbs, eyebrow, h1, lead) {
	const h = hero(body);
	check(label + ': ortak page-hero var, TEK H1', h !== '' && count(body, /<h1[ >]/g) === 1, count(body, /<h1[ >]/g));
	const bc = (h.match(/<nav[^>]*class="breadcrumb"[\s\S]*?<\/nav>/) || [''])[0];
	const links = [...bc.matchAll(/<a href="([^"]*)">([\s\S]*?)<\/a>/g)].map((m) => [m[1], dec(m[2])]);
	check(label + ': kırıntı Anasayfa (gerçek ana sayfa) + üst sayfa(lar) + geçerli sayfa', links.length === crumbs.length && links[0] && links[0][0] === BASE + '/' && links[0][1] === 'Anasayfa' && crumbs.slice(1).every((c, k) => links[k + 1] && links[k + 1][1] === c.label && links[k + 1][0] === url(c.path)) && new RegExp('aria-current="page">' + h1.replace(/[?()]/g, '.') + '<').test(bc.replace(/&#8217;/g, "'")), JSON.stringify(links));
	check(label + ': eyebrow "' + eyebrow + '"', dec((h.match(/<span class="eyebrow">([\s\S]*?)<\/span>/) || ['', ''])[1]) === eyebrow, (h.match(/<span class="eyebrow">([\s\S]*?)<\/span>/) || [''])[0]);
	check(label + ': H1 = WordPress başlığı', dec((h.match(/<h1>([\s\S]*?)<\/h1>/) || ['', ''])[1]) === h1, (h.match(/<h1>[\s\S]*?<\/h1>/) || [''])[0]);
	check(label + ': lead', dec((h.match(/<p>([\s\S]*?)<\/p>/) || ['', ''])[1]) === lead, (h.match(/<p>[\s\S]*?<\/p>/) || [''])[0]);
}
function headingOrder(label, body) {
	const lv = (mainOf(body).match(/<h([1-6])[ >]/g) || []).map((x) => Number(x[2]));
	let ok = lv[0] === 1;
	for (let i = 1; i < lv.length; i++) if (lv[i] > lv[i - 1] + 1) ok = false;
	check(label + ': başlık sırası atlamasız', ok, lv.join(','));
}
async function internalLinks(label, body) {
	const m = mainOf(body).slice(mainOf(body).indexOf('</section>'));
	const hrefs = [...new Set([...m.matchAll(/href="([^"#]+)(#[^"]*)?"/g)].map((x) => x[1].replace(/&#038;|&amp;/g, '&')).filter((h) => h.indexOf(BASE) === 0 || h[0] === '/'))];
	const raw = hrefs.filter((h) => h[0] === '/');
	check(label + ': gövdede kök-göreli (yapıdan bağımsız) iç bağlantı kalmadı', raw.length === 0, raw.join(' '));
	if (PL === 'index') check(label + ': iç bağlantılar /index.php/ yapısında', hrefs.filter((h) => h !== BASE + '/' && h.indexOf(BASE + '/index.php/') !== 0 && h.indexOf('?') === -1 && !/\/wp-content\//.test(h)).length === 0, hrefs.join(' '));
	else check(label + ': iç bağlantılarda gereksiz /index.php/ yok', hrefs.filter((h) => h.indexOf('/index.php/') !== -1).length === 0, hrefs.join(' '));
	const bad = [];
	for (const h of hrefs) {
		if (/\/wp-content\//.test(h)) continue;
		const r = await get(h.indexOf('http') === 0 ? h : BASE + h);
		if (r.status !== 200) bad.push(h + '=' + r.status);
	}
	check(label + ': gövdedeki her iç bağlantı 200', bad.length === 0, bad.join(' '));
}

function helperCases() {
	const raw = cp.execFileSync('docker', ['exec', '-u', 'www-data', 'mbruntime6b2-wp-1', 'wp', '--path=/var/www/html', '--require=/opt/mb-runtime/fixture-env.php', 'eval-file', '/opt/mb-runtime/page-parity-fixtures.php', 'helper-cases'], { encoding: 'utf8' });
	const h = JSON.parse(raw.replace(/^[^{]*/, ''));
	check('bölümleme: iç içe (sarmalayıcı içindeki) başlık -> bölümleme YOK, içerik tek parça (etiket dengesi korunur)', h.nested.sections.length === 0 && h.nested.intro.indexOf('<div class="wp-block-group"><h3>A</h3>') === 0 && h.nested.rest === '', JSON.stringify(h.nested));
	check('bölümleme: liste içindeki başlık -> bölümleme YOK', h.nestedli.sections.length === 0, JSON.stringify(h.nestedli));
	check('bölümleme: daha derin alt başlık (h4) bölüm gövdesinde kalır; yalnız ÜST seviye (h2) "rest" başlatır', h.deeper.sections.length === 2 && /<h4>A1<\/h4>/.test(h.deeper.sections[0].body) && h.deeper.sections[1].heading === 'B' && h.deeper.rest.indexOf('<h2>R</h2>') === 0, JSON.stringify(h.deeper));
	check('bölümleme: başlık id özniteliği korunur, diğer öznitelikler (onclick) atılır', /<h2 id="basvuru" class="mb-card-title">T<\/h2>/.test(h.attrs.sections[0].emitted) && !/onclick/.test(h.attrs.sections[0].emitted), h.attrs.sections[0] && h.attrs.sections[0].emitted);
	check('bölümleme: metinsiz ön içerik (img) intro olarak korunur', /<img src="x.png" alt="a">/.test(h.media.intro) && h.media.sections.length === 1, JSON.stringify(h.media));
	check('bölümleme: başlıksız içerik bölümlenmez', h.flat.sections.length === 0 && h.flat.intro === '<p>z</p>');
	const L = h.links;
	const E = h.expected_mevzuat;
	check('iç bağlantı: /mevzuat/ -> mavibelge_url (etkin yapı)', L.page === '<a href="' + E + '">m</a>' && L.single === "<a href='" + E + "'>s</a>", L.page + ' ' + L.single);
	check('iç bağlantı: #parça korunur', L.anchor === '<a href="' + E + '#kanun">m</a>', L.anchor);
	check('iç bağlantı: data-href, /wp-admin/, /feed/, harici, çok bölümlü ve sorgulu bağlantılara dokunulmaz', L.data === '<span data-href="/mevzuat/">m</span>' && L.wpadmin === '<a href="/wp-admin/">a</a>' && L.feed === '<a href="/feed/">f</a>' && L.external === '<a href="https://ornek.example/mevzuat/">e</a>' && L.multi === '<a href="/a/b/">x</a>' && L.query === '<a href="/mevzuat/?q=1">q</a>', JSON.stringify(L));
}

async function otherPagesLinks() {
	// İnceleme bulgusu M1: kayıt DIŞI sayfalar da (content-default) içerikteki kök-göreli bağlantıları etkin yapıya çevirir.
	for (const slug of ['yasal-dayanagimiz', 'sosyal-sorumluluk', 'ulusal-meslek-standartlari', 'ulusal-yeterlilikler']) {
		const r = await get(url(slug));
		check(slug + ': 200', r.status === 200, r.status);
		await internalLinks(slug, r.body);
	}
}

async function httpPhase() {
	helperCases();
	await otherPagesLinks();
	for (const slug of Object.keys(PAGES)) {
		const P = PAGES[slug];
		const R = rec[slug];
		const r = await get(url(slug));
		const L = slug;
		check(L + ': 200, PHP uyarısı yok', r.status === 200 && !/(Warning|Notice|Deprecated|Fatal error):/.test(r.body), r.status);
		checkHero(L, r.body, [{ label: 'Anasayfa' }, P.parent], P.parent.label, R.title, P.lead || R.excerpt);
		headingOrder(L, r.body);
		const m = mainOf(r.body);
		const sec = (m.match(new RegExp('<div class="container mb-body mb-body--' + P.width + '" data-mb-family="' + P.fam + '">')) || []).length;
		check(L + ': aile gövdesi "' + P.fam + '" + genişlik "' + P.width + '"', sec === 1, (m.match(/<div class="container mb-body[^>]*>/) || ['yok'])[0]);
		// korunan içerik: editördeki her paragraf/madde metni aynen görünür
		const texts = [...R.content.matchAll(/<(p|li|h[2-4])>([\s\S]*?)<\/\1>/g)].map((x) => dec(x[2])).filter(Boolean);
		const md = dec(m.replace(/<p class="mb-cta-row">[\s\S]*?<\/p>/g, '')); // CTA gezinme düğmesi içerik değildir
		const occ = (hay, n) => hay.split(n).length - 1;
		const missing = texts.filter((t) => occ(md, t) < occ(texts.join('\u0000'), t));
		check(L + ': WordPress içeriğinin tüm metni korunur (' + texts.length + ' parça)', missing.length === 0, missing.join(' | '));
		const dup = texts.filter((t) => t.length > 20 && occ(md, t) > occ(texts.join('\u0000'), t));
		check(L + ': içerik metni çoğaltılmaz', dup.length === 0, dup.join(' | '));
		if (P.cta) check(L + ': CTA "' + P.cta[0] + '" -> ' + P.cta[1] + ' (mavibelge_url)', m.indexOf('<a class="btn btn-primary mb-cta" href="' + url(P.cta[1]) + '">' + P.cta[0] + '</a>') !== -1);
		await internalLinks(L, r.body);
		if (slug === 'sinav-surecleri') check(L + ': numaralı liste (7 madde)', /<div class="entry-content mb-list mb-list--numbered">\s*<ol>/.test(m) && count(m, /<li>/g) >= 7);
		if (slug === 'mevzuat') check(L + ': madde listesi (4)', /<div class="entry-content mb-list mb-list--bullets">\s*<ul>/.test(m) && count(m, /<li>/g) >= 4);
		if (slug === 'banka-hesap-bilgileri') {
			check(L + ': her hesap grubu ayrı kart (3), başlık h2 (metin korunur)', count(m, /class="info-card mb-card"/g) === 3 && count(m, /<h2 class="mb-card-title">/g) === 3);
			check(L + ': banka verisi yalnız WordPress içeriğinden (3 IBAN)', ['TR10 0001 2009 2230 0010 2611 21', 'TR63 0001 2009 2230 0010 2611 37', 'TR14 0001 2009 2230 0010 2615 34'].every((t) => m.indexOf(t) !== -1));
		}
		if (slug === 'sinav-takvimi' || slug === 'sonuc-belge-sorgulama') check(L + ': tek bilgi kartı + harici bağlantı korunur', count(m, /class="info-card mb-card"/g) === 1 && /href="https:\/\/(mavibelge\.pratikteorik\.com|portal\.myk\.gov\.tr)\//.test(m));
		if (slug === 'yetki-akreditasyon') check(L + ': 2 sütun kart ızgarası, h2 kart başlıkları, MYK + TÜRKAK logoları (tema varlığı, alt metinli)', /<div class="mb-cards mb-cards--2">/.test(m) && count(m, /class="info-card mb-card"/g) === 2 && /myk_logo\.png" alt="MYK"/.test(m) && /turkak_logo\.png" alt="TÜRKAK"/.test(m));
		if (slug === 'belge-yenileme') check(L + ': 3 sütun kart + kalan "Süreç" bölümü numaralı liste', /<div class="mb-cards mb-cards--3">/.test(m) && count(m, /class="info-card mb-card"/g) === 3 && /<div class="entry-content mb-list mb-list--numbered mb-rest">/.test(m));
		if (slug === 'nasil-basvururum') check(L + ': 4 adım (.process-step + .step-num 1-4, h2)', /<ol class="process-steps mb-steps">/.test(m) && count(m, /<li class="process-step">/g) === 4 && [1, 2, 3, 4].every((n) => m.indexOf('<span class="step-num" aria-hidden="true">' + n + '</span>') !== -1));
		if (slug === 'myk' || slug === 'turkak') check(L + ': logo (tema varlığı) + düz metin', new RegExp((slug === 'myk' ? 'myk_logo' : 'turkak_logo') + '\\.png" alt="' + (slug === 'myk' ? 'MYK' : 'TÜRKAK') + '"').test(m) && /<div class="entry-content mb-prose">/.test(m));
		if (slug === 'itiraz-sikayet') {
			check(L + ': iki sütun (içerik + form kartı); form kapalı: <form>/input/nonce YOK', /<div class="mb-split">/.test(m) && /class="form-card"/.test(m) && !/<form\b|<input\b|_mb_nonce|mb_token/.test(m));
			check(L + ': kapalı form bildirimi + süreç listesi', /Bu form şu anda kullanılamıyor/.test(m) && /mb-list--numbered/.test(m));
		}
	}
	// arşivler
	let r = await get(url('haberler'));
	checkHero('haberler', r.body, [{ label: 'Anasayfa' }, BM], 'Bilgi Merkezi', 'Haberler', "Mavi Belge'ye ait güncel haberler ve kurumsal gelişmeler.");
	headingOrder('haberler', r.body);
	let m = mainOf(r.body);
	check('haberler: servis sorgusu 12 kart (sayfa boyutu) + sayfalama bağlantısı mb_page=2', count(m, /class="card-shell[ \"]/g) === 12 && /mb_page=2/.test(m), count(m, /class="card-shell[ \"]/g));
	check('haberler: tür süzgeci bağlantıları (Tümü/Haberler/Duyurular) korunur', /mb_type=haber/.test(m) && /mb_type=duyuru/.test(m));
	check('haberler: gövde ortak arşiv kabı', /<section class="section-tight mb-archive">/.test(m));
	check('haberler: öne çıkan görseli olan kartlar (2) görsel taşır; olmayanlar taşımaz; görsel dekoratif (alt="")', count(m, /class="card-shell card-shell--media"/g) === 2 && count(m, /<div class="card-media"><img [^>]*alt=""/g) === 2, count(m, /card-shell--media/g));
	const p2 = (m.match(/href="([^"]*mb_page=2[^"]*)"/) || ['', ''])[1].replace(/&#038;|&amp;/g, '&');
	r = await get(p2);
	check('haberler sayfa 2: 200 + kalan kartlar', r.status === 200 && count(mainOf(r.body), /class="card-shell[ \"]/g) >= 2, r.status + ' ' + p2);
	r = await get(url('haberler') + '?mb_type=duyuru');
	check('haberler ?mb_type=duyuru: yalnız duyurular (2)', r.status === 200 && count(mainOf(r.body), /class="card-shell[ \"]/g) === 2 && mainOf(r.body).indexOf('TEST Parite Haber') === -1, count(mainOf(r.body), /class="card-shell[ \"]/g));
	r = await get(url('haber-turu/duyuru'));
	checkHero('haber-turu/duyuru', r.body, [{ label: 'Anasayfa' }, BM], 'Bilgi Merkezi', 'Duyuru', 'Ücret, mevzuat ve süreçlere ilişkin resmi duyurular.');
	headingOrder('haber-turu/duyuru', r.body);
	check('haber-turu/duyuru: öne çıkan görselli duyuru kartı görsel taşır (1)', count(mainOf(r.body), /class="card-shell card-shell--media"/g) === 1, count(mainOf(r.body), /card-shell--media/g));
	check('haber-turu/duyuru: WordPress ana sorgusu 2 duyuru kartı', count(mainOf(r.body), /class="card-shell[ \"]/g) === 2 && mainOf(r.body).indexOf('TEST Parite Duyuru') !== -1, count(mainOf(r.body), /class="card-shell[ \"]/g));
	r = await get(url('dokumanlar'));
	checkHero('dokumanlar', r.body, [{ label: 'Anasayfa' }, BM], 'Bilgi Merkezi', 'Dokümanlar', 'Herkese açık, versiyonlu kurumsal ve mesleki dokümanlar.');
	headingOrder('dokumanlar', r.body);
	m = mainOf(r.body);
	check('dokumanlar: .doc-list içinde servis kayıtları (2) + kategori süzgeci', /<div class="doc-list">/.test(m) && count(m, /class="doc-card/g) === 2 && /mb_cat=test-kategori/.test(m), count(m, /class="doc-card/g));
	check('dokumanlar: satır içi grid stili yok', !/style="display:grid/.test(m));
	r = await get(url('dokumanlar') + '?mb_cat=test-kategori');
	check('dokumanlar ?mb_cat: 200 + 2 kayıt', r.status === 200 && count(mainOf(r.body), /class="doc-card/g) === 2);
}

const VIEWS = [[390, 844], [1024, 768], [1279, 900], [1280, 900], [1440, 900]];
async function browserPhase() {
	fs.mkdirSync(SHOTS, { recursive: true });
	const b = await launch();
	const failed = [];
	b.send('Network.enable').catch(() => {});
	try {
		const routes = Object.keys(PAGES).concat(['haberler', 'haber-turu/duyuru', 'dokumanlar']);
		for (const [w, h] of VIEWS) {
			await b.viewport(w, h);
			for (const slug of routes) {
				await b.goto(url(slug));
				const L = slug + ' ' + w + 'px';
				const g = await b.eval(PROBE);
				check(L + ': başlık sağlıklı; ' + (w <= 1279 ? 'hamburger' : 'masaüstü menü'), g.problems.length === 0 && g.mode === (w <= 1279 ? 'hamburger' : 'desktop'), g.mode + ' ' + g.problems.join(' | '));
				const s = await b.eval(`(() => {
					const vw = document.documentElement.clientWidth;
					const cols = (sel) => { const el = document.querySelector(sel); return el ? getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean).length : 0; };
					const over = [...document.querySelectorAll('main *')].filter((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && (r.right > vw + 0.5) && !el.closest('.hp-field, .screen-reader-text, .table-wrap'); }).slice(0, 5).map((el) => el.tagName + '.' + el.className);
					const hero = document.querySelector('.page-hero'); const hr = hero ? hero.getBoundingClientRect() : null;
					const body = document.querySelector('.mb-body, .mb-archive .container'); const br = body ? body.getBoundingClientRect() : null;
					const foot = document.querySelector('.site-footer').getBoundingClientRect(); const main = document.querySelector('main').getBoundingClientRect();
					return { vw, sw: document.documentElement.scrollWidth, over, heroH: hr ? hr.height : 0, heroW: hr ? hr.width : 0, bodyW: br ? br.width : 0,
						cards2: cols('.mb-cards--2'), cards3: cols('.mb-cards--3'), split: cols('.mb-split'), news: cols('.mb-archive .news-grid'), steps: cols('.mb-steps'),
						overlap: main.bottom > foot.top + 0.5 };
				})()`);
				check(L + ': yatay taşma yok, main içi öğe taşmıyor', s.sw <= s.vw && s.over.length === 0, JSON.stringify({ sw: s.sw, over: s.over }));
				check(L + ': kahraman tam genişlik, footer çakışmıyor', s.heroH > 120 && s.heroW >= s.vw - 1 && !s.overlap, JSON.stringify(s));
				const P = PAGES[slug];
				if (P) {
					const maxW = P.width === 'narrow' ? 760 : P.width === 'prose' ? 820 : 1280;
					check(L + ': gövde genişliği ≤ ' + maxW, s.bodyW <= maxW + 0.5 && s.bodyW > Math.min(maxW, w) - 60, s.bodyW);
				}
				const want3 = w <= 768 ? 1 : w <= 1024 ? 2 : 3;
				const want2 = w <= 768 ? 1 : 2;
				if (slug === 'belge-yenileme') check(L + ': 3 kart ızgarası ' + want3 + ' sütun', s.cards3 === want3, s.cards3);
				if (slug === 'yetki-akreditasyon') check(L + ': 2 kart ızgarası ' + want2 + ' sütun', s.cards2 === want2, s.cards2);
				if (slug === 'itiraz-sikayet') check(L + ': içerik + form ' + (w <= 1024 ? '1' : '2') + ' sütun', s.split === (w <= 1024 ? 1 : 2), s.split);
				if (slug === 'nasil-basvururum') check(L + ': adımlar ' + (w <= 768 ? 1 : w <= 1024 ? 2 : 4) + ' sütun', s.steps === (w <= 768 ? 1 : w <= 1024 ? 2 : 4), s.steps);
				if (slug === 'haberler') check(L + ': haber kartları ' + want3 + ' sütun', s.news === want3, s.news);
				if (w === 390 || w === 1440) await b.screenshot(path.join(SHOTS, 'parity-' + slug.replace(/\//g, '-') + '-' + w + '.png'), true);
			}
		}
		// klavye: nasil-basvururum CTA'sına Tab ile ulaşılır, görünür odak
		await b.viewport(1440, 900);
		await b.goto(url('nasil-basvururum'));
		let got = null;
		for (let i = 0; i < 60; i++) {
			await b.key('Tab', 'Tab', 9);
			got = await b.eval(`(() => { const e = document.activeElement; if (!e || !e.classList.contains('mb-cta')) return null; const s = getComputedStyle(e); return { outline: s.outlineStyle + ' ' + s.outlineWidth, shadow: s.boxShadow }; })()`);
			if (got) break;
		}
		check('klavye: CTA Tab ile odaklanır ve odak görünür', got && (got.outline.indexOf('none') !== 0 || got.shadow !== 'none'), JSON.stringify(got));
		// reduced-motion
		await b.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
		await b.goto(url('nasil-basvururum'));
		const rm = await b.eval(`[...document.querySelectorAll('.mb-cta, .mb-card, .process-step')].map((e) => getComputedStyle(e).transitionDuration).filter((d) => d.split(',').some((x) => parseFloat(x) * (/ms$/.test(x.trim()) ? 0.001 : 1) > 0.001)).length`);
		check('prefers-reduced-motion: mb- bileşenlerinde geçiş yok', rm === 0, rm);
		await b.send('Emulation.setEmulatedMedia', { features: [] });
		check('Chrome: konsol hatası / başarısız kaynak yok', b.consoleErrors.length === 0, b.consoleErrors.join(' | '));
	} finally {
		await b.close();
	}
}

(async () => {
	if (MODE === 'http') await httpPhase();
	else await browserPhase();
	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 13 sayfa parite testi (' + MODE + ', ' + PL + ') geçti.');
	process.exit(fail ? 1 : 0);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
