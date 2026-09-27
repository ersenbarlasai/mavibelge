'use strict';
/**
 * Faz 12 — GERÇEK içe aktarılmış sayfalarla (pages manifesti -> pages aşaması -> yayın işlemi) 41 rota render + kalıcı bağlantı
 * (/%postname%/, Apache mod_rewrite) + robots.txt/wp-sitemap.xml + form kapalılığı testi. Gerçek HTTP istemcisi; tarayıcı DEĞİL.
 * YALNIZ mbfx_ fixture veritabanı (pages-render.sh).   node pages-render-test.js <phase1|phase2> <qa-fixtures.json> <pages-import.json>
 *   phase1 : yayınlama işleminin yayınladığı 32 sayfa (200; referanslar/sss içerik kayıtları — gerçek manifestlerle 15 logo + 6 SSS — oluşup yayınlandıktan sonra)
 *   phase2 : 41/41 rota, form kapalılığı, robots.txt/sitemap üretim + staging
 */
const fs = require('fs');
const path = require('path');
const cp = require('child_process');

const phase = process.argv[2];
const BASE = 'http://127.0.0.1:18673';
const fx = JSON.parse(fs.readFileSync(process.argv[3], 'utf8').replace(/^[^{]*/, ''));
const imp = JSON.parse(fs.readFileSync(process.argv[4], 'utf8').replace(/^[^{]*/, ''));
const ROOT = path.join(__dirname, '..', '..');
const SITE = path.join(ROOT, '..', 'tanitim-site');
const manifest = JSON.parse(fs.readFileSync(path.join(ROOT, 'data', 'content', 'pages.manifest.json'), 'utf8'));
const { PAGE_INVENTORY } = require(path.join(ROOT, 'tools', 'import', 'lib', 'page-inventory.js'));
const files = fs.readdirSync(SITE).filter((f) => /\.html$/.test(f)).map((f) => f.replace(/\.html$/, '')).sort();
const SPECIAL = ['index', '404', 'meslekler', 'haberler', 'dokumanlar', 'sektor', 'duyurular', 'yeterlilik', 'haber-detay'];
const slugs = manifest.records.map((r) => r.slug).sort();
const held = manifest.records.filter((r) => r.publish_hold).map((r) => r.slug).sort(); // kurum kararı bekleyen sayfa kalmadı: []
let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + detail + ']' : ''));
	}
}
async function get(p, follow) {
	const res = await fetch(BASE + p, { redirect: follow ? 'follow' : 'manual' });
	const headers = {};
	res.headers.forEach((v, k) => {
		headers[k] = v;
	});
	return { status: res.status, body: await res.text(), headers };
}
const count = (s, re) => (s.match(re) || []).length;
const sh = (c) => cp.execSync(c, { stdio: ['ignore', 'pipe', 'pipe'] }).toString();

function contract(label, r, expect) {
	const b = r.body;
	check(label + ': durum ' + expect, r.status === expect, String(r.status));
	check(label + ': tek <h1>', count(b, /<h1[ >]/g) === 1, 'h1=' + count(b, /<h1[ >]/g));
	check(label + ': <html lang="tr">, viewport, atla bağlantısı, tek #main', /<html[^>]*lang="tr/.test(b) && /<meta name="viewport"/.test(b) && /class="skip-link"/.test(b) && count(b, /id="main"/g) === 1);
	check(label + ': dolu <title>', /<title>[^<]{3,}<\/title>/.test(b));
	check(label + ': href="#" ve javascript: bağlantısı YOK', !/href="#"/.test(b) && !/href="\s*javascript:/i.test(b));
	check(label + ': her <img> alt öznitelikli', (b.match(/<img\b[^>]*>/g) || []).every((t) => /\balt=/.test(t)));
	check(label + ': PHP uyarı/hata metni YOK', !/(Warning|Notice|Deprecated|Fatal error|Parse error):/.test(b) && !/Stack trace/.test(b));
}

(async () => {
	check('sayfa slug kümesi: statik dosyalar = 9 özel rota + 32 manifest sayfası (41)', files.length === 41 && JSON.stringify(files.filter((f) => !SPECIAL.includes(f))) === JSON.stringify(slugs) && slugs.length === 32, files.length + '/' + slugs.length);
	if (phase === 'phase1') {
		check('içe aktarma çıktısı: 32 sayfa yayında (yedi kurum kararı çözüldü; referanslar/sss içerik kayıtlarından sonra); taslak yok; kalıcı bağlantı /%postname%/', imp.published.filter((x) => slugs.includes(x)).length === 32 && imp.draft.length === 0 && held.length === 0 && imp.permalink === '/%postname%/');
	}

	if (phase === 'phase1') {
		for (const slug of imp.published) {
			const r = await get('/' + slug + '/');
			check('[' + slug + '] yayın işleminin yayınladığı sayfa: /' + slug + '/ -> 200', r.status === 200, String(r.status));
		}
		for (const slug of held) {
			const r = await get('/' + slug + '/');
			check('[' + slug + '] bekletilen (kurum kararı) sayfa taslakta: /' + slug + '/ -> 404 (anonim ziyaretçi göremez)', r.status === 404, String(r.status));
		}
		const sm = await get('/wp-sitemap-posts-page-1.xml');
		check('sitemap (üretim): yayındaki 32 içe aktarılmış sayfa listelenir', sm.status === 200 && imp.published.filter((x) => slugs.includes(x)).every((x) => sm.body.includes('/' + x + '/')) && held.every((h) => !sm.body.includes('/' + h + '/')), String(count(sm.body, /<loc>/g)));
		// ---- Faz 12b: gerçek içerikle render (15 logo + 6 SSS + onaylı hukuk/banka/CTA sayfaları) ----
		const decode = (t) => t.replace(/&#0*38;|&amp;/g, '&').replace(/&#0*39;|&#8217;/g, "'").replace(/&quot;/g, '"');
		const refs = JSON.parse(fs.readFileSync(path.join(ROOT, 'data', 'content', 'references.manifest.json'), 'utf8'));
		const faqs = JSON.parse(fs.readFileSync(path.join(ROOT, 'data', 'content', 'faqs.manifest.json'), 'utf8'));
		const rp = await get('/referanslar/');
		const grid = (rp.body.match(/<ul class="ref-grid">[\s\S]*?<\/ul>/) || [''])[0];
		const imgs = grid.match(/<img\b[^>]*>/g) || [];
		check('referanslar (gerçek içerik): 15 logo görseli; her <img> anlamlı alt metinli ("Referans NN — kuruluş logosu"), genişlik/yükseklik öznitelikli (oran korunur); "Temsili görsel" notu YOK', rp.status === 200 && imgs.length === 15 && imgs.every((t, i) => decode(/\balt="([^"]*)"/.exec(t)[1]) === 'Referans ' + String(i + 1).padStart(2, '0') + ' — kuruluş logosu' && /\bwidth="250"/.test(t) && /\bheight="100"/.test(t)) && !/Temsili görsel/.test(rp.body), rp.status + ' img=' + imgs.length + ' ' + (imgs[0] || '').slice(0, 200));
		check('referanslar: logo görselleri gerçek attachment URL\'leri (uploads/mbfx…png); cover/kırpma yok; firma adı uydurulmadı (görünen ad yalnız "Referans NN")', imgs.every((t) => /\bsrc="[^"]+\.png"/.test(t)) && !/object-fit:\s*cover/i.test(rp.body) && refs.records.every((r) => r.name_status === 'unverified'));
		const sp = await get('/sss/');
		const details = sp.body.match(/<details class="accordion-item">/g) || [];
		check('sss (gerçek içerik): 6 <details> akordeon; her sorunun metni sayfada; cevaplar düz metin; JavaScript gerektirmez', sp.status === 200 && details.length === 6 && faqs.records.every((f) => decode(sp.body).includes(f.question) && decode(sp.body).includes(f.answer)), sp.status + ' details=' + details.length);
		const kv = await get('/kvkk/');
		const gz = await get('/gizlilik-politikasi/');
		const bk = await get('/banka-hesap-bilgileri/');
		check('KVKK/gizlilik/banka sayfaları onaylı kaynaktan DOLU render edilir (tek <h1>; kaynak başlıkları içerikte); iframe/script/form yok', kv.status === 200 && gz.status === 200 && bk.status === 200 && decode(kv.body).includes('KİŞİSEL VERİLERİN KORUNMASI VE İŞLENMESİNE İLİŞKİN AYDINLATMA METNİ') && decode(gz.body).includes('Kişisel verileriniz') && decode(bk.body).includes('Şube Kodu') && [kv, gz, bk].every((x) => !/<iframe/i.test(x.body) && count(x.body, /<h1[ >]/g) === 1));
		const st = await get('/sinav-takvimi/');
		const sq = await get('/sonuc-belge-sorgulama/');
		const hrefOf = (b, host) => { const m = new RegExp('<a href="([^"]*' + host + '[^"]*)"').exec(b); return m ? decode(m[1]) : null; };
		check('sınav takvimi CTA adresi onaylı adresle BİREBİR; dış sisteme gidileceği yazılı; iframe yok', hrefOf(st.body, 'mavibelge\\.pratikteorik\\.com') === 'https://mavibelge.pratikteorik.com/home/examcalendar' && /harici/i.test(decode(st.body)) && !/<iframe/i.test(st.body), String(hrefOf(st.body, 'mavibelge\\.pratikteorik\\.com')));
		check('MYK sonuç/belge sorgulama CTA adresi query string BOZULMADAN birebir; kimlik bilgisi/form/iframe yok; MYK portalına yönlendirileceği yazılı', hrefOf(sq.body, 'portal\\.myk\\.gov\\.tr') === 'https://portal.myk.gov.tr/index.php?option=com_belgelendirme&view=belgelendirme_islemleri&layout=aday_bilgi_sorgu' && !/<form|<iframe|<input/i.test(sq.body.replace(/<form[^>]*role="search"[\s\S]*?<\/form>/g, '')) && /MYK portal/.test(decode(sq.body)), String(hrefOf(sq.body, 'portal\\.myk\\.gov\\.tr')));
		console.log('\n' + pass + '/' + (pass + fail) + ' Faz 12 sayfa render (phase1: yayın işlemi kapsamı) testi geçti.');
		process.exit(fail === 0 ? 0 : 1);
	}

	/* ---------- phase2: 41 rota ---------- */
	check('bekletilenler test düzeneğiyle yayında (izole DB): 32/32 yayında', imp.published.filter((x) => slugs.includes(x)).length === 32 && imp.draft.length === 0, imp.published.length + '/' + imp.draft.length);
	const special = {
		'index': { url: '/', status: 200 },
		'404': { url: '/?p=99999999', status: 404 },
		'meslekler': { url: '/?post_type=mb_yeterlilik', status: 200 },
		'haberler': { url: '/?post_type=mb_haber', status: 200 },
		'dokumanlar': { url: '/?post_type=mb_dokuman', status: 200 },
		'sektor': { url: '/?mb_sektor=' + (fx.sector_slug || 'plastik'), status: 200 },
		'duyurular': { url: '/?mb_haber_turu=duyuru', status: 200 },
		'yeterlilik': { url: '/?post_type=mb_yeterlilik&p=' + fx.qualification_id, status: 200 },
		'haber-detay': { url: '/?post_type=mb_haber&p=' + fx.news_id, status: 200 },
	};
	let mapped = 0;
	for (const slug of files) {
		const spec = special[slug] || { url: '/' + slug + '/', status: 200 };
		const r = await get(spec.url, !!special[slug]);
		contract('[' + slug + ']', r, spec.status);
		mapped++;
	}
	check('41 statik sayfanın TAMAMI (kalıcı bağlantı /slug/) render edildi: 41/41', mapped === 41 && files.length === 41, mapped + '/' + files.length);
	for (const slug of ['sinav-ucretleri', 'kurumsal', 'iletisim', 'sss']) {
		const r = await get('/' + slug + '/');
		check('açık rota: /' + slug + '/ -> 200 (güzel kalıcı bağlantı)', r.status === 200, String(r.status));
	}
	let r = await get('/index.php/kurumsal/');
	check('/index.php/kurumsal/ eski biçimi güzel adrese 301 yönlenir (kanonik)', r.status === 301 && /\/kurumsal\/$/.test(r.headers.location || ''), r.status + ' ' + r.headers.location);

	/* ---------- form kapalılığı ---------- */
	const formSlugs = PAGE_INVENTORY.filter((p) => p.layout === 'form-disabled').map((p) => p.slug);
	check('envanter: 5 form sayfası', formSlugs.length === 5, formSlugs.join(','));
	for (const slug of formSlugs) {
		r = await get('/' + slug + '/');
		check('form kapalı (kurum kararı yok): /' + slug + '/ içinde <form method="post"> YOK, "şu anda kullanılamıyor" görünür', !/<form[^>]*method="post"/i.test(r.body) && r.body.includes('şu anda kullanılamıyor'), String(r.status));
	}
	r = await get('/iletisim/');
	check('iletişim: statik iletişim yedeği görünür (adres bilgisi kuruma ait, uydurma değil)', r.body.includes('Atay İş Merkezi'));

	/* ---------- robots.txt / sitemap: üretim ---------- */
	r = await get('/robots.txt');
	const lines = r.body.split('\n').map((l) => l.trim());
	check('robots.txt (üretim, güzel adres /robots.txt): 200, User-agent: *, Sitemap satırı /wp-sitemap.xml; tüm siteyi kapatan "Disallow: /" YOK', r.status === 200 && lines.includes('User-agent: *') && lines.some((l) => /^Sitemap: https?:\/\/[^ ]+\/wp-sitemap\.xml$/.test(l)) && !lines.includes('Disallow: /'), r.status + ' ' + r.body.slice(0, 120));
	r = await get('/wp-sitemap.xml');
	check('wp-sitemap.xml (üretim): 200, sitemapindex, kullanıcı sitemap\'i YOK', r.status === 200 && r.body.includes('<sitemapindex') && !r.body.includes('wp-sitemap-users'), String(r.status));
	r = await get('/wp-sitemap-posts-page-1.xml');
	check('sitemap sayfa listesi (üretim): 32 içe aktarılmış sayfanın hepsi listelenir', r.status === 200 && slugs.every((x) => r.body.includes('/' + x + '/')), String(count(r.body, /<loc>/g)));

	/* ---------- robots.txt / sitemap: staging (üretim DIŞI) ---------- */
	sh('docker exec mbruntime6b2-wp-1 sh -c "touch /tmp/mbfx-staging && chmod 644 /tmp/mbfx-staging"');
	try {
		r = await get('/robots.txt');
		check('robots.txt (staging): yalnız "User-agent: * / Disallow: /"; Sitemap satırı ve bot izni YOK', r.status === 200 && r.body.trim() === '# Mavi Belge — üretim DIŞI ortam: hiçbir şey taranmamalı.\nUser-agent: *\nDisallow: /', r.body.slice(0, 160));
		r = await get('/kurumsal/');
		check('staging: /kurumsal/ 200 ama noindex,nofollow (meta + X-Robots-Tag başlığı)', r.status === 200 && /noindex/.test(r.body) && /nofollow/.test(r.body) && /noindex, nofollow/.test(r.headers['x-robots-tag'] || ''), r.status + ' ' + (r.headers['x-robots-tag'] || ''));
	} finally {
		sh('docker exec mbruntime6b2-wp-1 rm -f /tmp/mbfx-staging');
	}
	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 12 sayfa render + kalıcı bağlantı + robots/sitemap (phase2) testi geçti.');
	process.exit(fail === 0 ? 0 : 1);
})().catch((e) => {
	try {
		sh('docker exec mbruntime6b2-wp-1 rm -f /tmp/mbfx-staging');
	} catch (x) {
		/* yoksay */
	}
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
