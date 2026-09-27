'use strict';
/**
 * Faz 12g — İletişim sayfası + beş formun güvenlik/regresyon matrisi. GERÇEK WordPress (mbfx_ fixture, /index.php/%postname%/)
 * + GERÇEK headless Chrome (CDP). Gerçek kişi verisi/e-posta YOKTUR: değerler sentetik (.example); wp_mail mu-fixture ile kısa
 * devre (yalnız sayaç). Form ayarları yalnız bu klonda sentetik açılır ve sonunda kapatılır.
 *
 *   node contact-page-test.js <qual-fixtures.json> <ekran-dizini>
 */
const fs = require('fs');
const path = require('path');
const cp = require('child_process');
const { launch } = require('./lib/cdp.js');
const { PROBE } = require('./lib/header-geometry.js');

const [, , QF, SHOTS] = process.argv;
const qfx = JSON.parse(fs.readFileSync(QF, 'utf8').replace(/^[^{]*/, ''));
fs.mkdirSync(SHOTS, { recursive: true });
const BASE = 'http://127.0.0.1:18673';
const PAGE = BASE + '/index.php/iletisim/';
const WP = 'mbruntime6b2-wp-1';
const WIDTHS = [390, 480, 768, 820, 1024, 1279, 1280, 1920];
let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + String(detail).slice(0, 400) + ']' : ''));
	}
}
const count = (s, re) => (s.match(re) || []).length;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const dx = (args) => cp.execFileSync('docker', ['exec', '-u', 'www-data', WP].concat(args), { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
const fixture = (...a) => JSON.parse(dx(['wp', '--path=/var/www/html', '--require=/opt/mb-runtime/fixture-env.php', '--user=mbadmin', 'eval-file', '/opt/mb-runtime/contact-fixtures.php'].concat(a)).replace(/^[^{]*/, ''));
const config = (cfg) => fixture('config', Buffer.from(JSON.stringify(cfg)).toString('base64'));
const mailCount = () => Number(dx(['sh', '-c', 'cat /tmp/mbfx-mail.count 2>/dev/null | wc -l']).trim());
const tmpLeft = () => dx(['sh', '-c', 'ls -A /var/www/html/wp-content/uploads/mavibelge-forms-tmp 2>/dev/null | grep -v -e "^.htaccess$" -e "^index.php$" | wc -l']).trim();
const SYN = { enabled: '1', recipient_email: 'kurum@ornek.example', consent_approved: '1', consent_text: 'TEST: sentetik onay metni (kurum kararı DEĞİL).', consent_version: 'test-1', sensitive_fields_approved: '1', uploads_approved: '1' };
const RATE = { per_client: 200, window: 600, global: 2000, global_window: 3600 };

async function get(url) {
	const r = await fetch(url.indexOf('http') === 0 ? url : BASE + url, { redirect: 'manual' });
	return { status: r.status, body: await r.text(), location: r.headers.get('location') || '' };
}
async function post(url, data, files) {
	let body;
	const headers = {};
	if (files) {
		body = new FormData();
		Object.keys(data).forEach((k) => body.append(k, data[k]));
		files.forEach((f) => body.append(f.name, new Blob([f.bytes], { type: f.type }), f.filename));
	} else {
		body = new URLSearchParams();
		Object.keys(data).forEach((k) => body.append(k, data[k]));
		headers['Content-Type'] = 'application/x-www-form-urlencoded';
	}
	const r = await fetch(url.indexOf('http') === 0 ? url : BASE + url, { method: 'POST', body, headers, redirect: 'manual' });
	return { status: r.status, body: await r.text(), location: r.headers.get('location') || '' };
}
const mainOf = (b) => b.slice(b.indexOf('<main'), b.indexOf('</main>'));
const hidden = (html, name) => ((html.match(new RegExp('name="' + name + '" value="([^"]*)"')) || [])[1] || '');
function common(label, r) {
	check(label + ': 200, tek H1, PHP uyarısı yok, href="#" yok', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && !/(Warning|Notice|Deprecated|Fatal error):/.test(r.body) && !/href="#"/.test(r.body), r.status);
	const levels = (mainOf(r.body).match(/<h([1-6])[ >]/g) || []).map((h) => Number(h[2]));
	let ok = levels[0] === 1;
	for (let i = 1; i < levels.length; i++) if (levels[i] > levels[i - 1] + 1) ok = false;
	check(label + ': başlık sırası atlamasız', ok, levels.join(','));
}

/* ---------------------------------------------------------------- İletişim: kapalı + yedek lokasyonlar */
async function contactClosed(b) {
	fixture('locations', 'clear');
	config({ forms: {} });
	const r = await get(PAGE);
	common('iletişim kapalı', r);
	const m = mainOf(r.body);
	const hero = r.body.slice(r.body.indexOf('<section class="page-hero"'), r.body.indexOf('</section>', r.body.indexOf('<section class="page-hero"')));
	check('kahraman: kırıntı Anasayfa (gerçek ana sayfa) / İletişim; eyebrow İletişim; H1; açıklama', hero.indexOf('href="' + BASE + '/"') !== -1 && /aria-current="page">İletişim</.test(hero) && /class="eyebrow">İletişim</.test(hero) && /<h1>İletişim<\/h1>/.test(hero) && /Merkez ofisimiz, sınav alanlarımız ve iletişim bilgilerimiz\./.test(hero));
	const cards = m.match(/<div class="location-card">[\s\S]*?<\/div>\s*(?=<div class="location-card">|<\/div>\s*<\/div>\s*<\/section>)/g) || [];
	check('lokasyonlar (mb_lokasyon yok -> doğrulanmış yedek): 4 kart, sırası referansla aynı, TEK kez çizilir', count(m, /class="location-card"/g) === 4 && count(m, /class="location-grid"/g) === 1 && m.indexOf('Merkez Ofis — İskenderun/Hatay') < m.indexOf('Payas Sınav Alanı') && m.indexOf('Payas Sınav Alanı') < m.indexOf('İzmir Aliağa Sınav Alanı') && m.indexOf('İzmir Aliağa Sınav Alanı') < m.indexOf('Ankara Ofisi'), cards.length);
	check('lokasyon bölümü: tek H2 (görsel gizli), kart başlıkları H3', /<h2 id="locations-heading" class="screen-reader-text">/.test(m) && count(m.slice(m.indexOf('location-grid'), m.indexOf('contact-social')), /<h3>/g) === 4);
	check('yedek veriler statik referansla birebir: tel: bağlantıları, mailto, yalnız Ankara harita (https + noopener noreferrer + yeni sekme metni), diğerlerinde yer tutucu', ['tel:08502154422', 'tel:03264414422', 'tel:05426186284', 'tel:05426196284', 'tel:+905426196284', 'tel:+905426226284'].every((t) => m.indexOf('href="' + t + '"') !== -1) && m.indexOf('href="mailto:info@mavibelge.com.tr"') !== -1 && count(m, /class="directions-link" href="https:\/\/www\.google\.com\/maps\/search\/\?api=1&#038;query=1176%20Sokak%20No%3A28%20Ostim%20Ankara" target="_blank" rel="noopener noreferrer"/g) === 1 && /\(yeni sekmede açılır\)/.test(m) && count(m, /class="map-placeholder"/g) === 3 && !/<iframe|maps\.googleapis/.test(r.body));
	const foot = (r.body.match(/<div class="footer-locations">[\s\S]*?<div class="footer-bottom">/) || [''])[0];
	check('footer lokasyon şeridi aynı yedekten; önceki görünüm korunur (4 lokasyon; Merkez/Payas telefonsuz; İzmir + Ankara telefonlu; Ankara yol tarifi)', count(foot, /<h4>/g) === 4 && foot.indexOf('tel:08502154422') === -1 && foot.indexOf('tel:05426196284') !== -1 && foot.indexOf('tel:+905426226284') !== -1 && count(foot, /class="directions-link"/g) === 1);
	const soc = m.slice(m.indexOf('contact-social'), m.indexOf('contact-write'));
	check('sosyal medya: H2 + açıklama + footer ile aynı 3 hesap (https, noopener noreferrer, aria-label, ikon aria-hidden)', /<h2 id="social-heading">Sosyal Medya<\/h2>/.test(soc) && /Güncel duyurularımızı ve gelişmelerimizi resmi sosyal medya hesaplarımızdan takip edebilirsiniz\./.test(soc) && ['https://www.facebook.com/mavibelge31', 'https://www.instagram.com/mavi_belge', 'https://twitter.com/mavibelge31'].every((u) => soc.indexOf('href="' + u + '" target="_blank" rel="noopener noreferrer" aria-label="') !== -1) && count(soc, /aria-label="Mavi Belge [^"]+ \(yeni sekmede açılır\)"/g) === 3 && count(soc, /<svg class="icon-18" aria-hidden="true"/g) === 3);
	const footSoc = (r.body.match(/<div class="footer-social">[\s\S]*?<\/ul>/) || [''])[0];
	check('footer sosyal bağlantıları aynı parça (aynı 3 URL)', ['https://www.facebook.com/mavibelge31', 'https://www.instagram.com/mavi_belge', 'https://twitter.com/mavibelge31'].every((u) => footSoc.indexOf(u) !== -1));
	const w = m.slice(m.indexOf('contact-write'));
	check('Bize Yazın (kapalı): H2; <form>/name=/input/textarea/select/nonce/jeton/bal küpü YOK', /<h2 id="write-heading">Bize Yazın<\/h2>/.test(w) && !/<form\b|\bname="|<input\b|<textarea\b|<select\b|_mb_nonce|mb_token|hp-field/.test(m));
	check('Bize Yazın (kapalı): "şu anda kullanılamıyor" + "gerçek mesaj gönderilemez" + tel:/mailto: kanalı + istenecek alanların listesi; kapalı nedeni ziyaretçiye YOK', /İletişim formu şu anda kullanılamıyor\./.test(w) && /gerçek mesaj gönderilemez/.test(w) && /href="tel:08502154422"/.test(w) && /href="mailto:info@mavibelge\.com\.tr"/.test(w) && /Ad Soyad/.test(w) && /Mesajınız/.test(w) && !/Yönetici notu/.test(r.body));
	const m0 = mailCount();
	const p = await post(PAGE, { mb_form: 'contact', mb_token: 'x', _mb_nonce: 'x', full_name: 'TEST Sentetik', email: 'aday@ornek.example', message: 'TEST mesaj', consent: '1' });
	check('kapalı forma POST: 503 fail-closed, e-posta YOK, yanıtta kişisel veri yok', p.status === 503 && mailCount() === m0 && p.body.indexOf('aday@ornek.example') === -1, p.status);

	for (const w2 of WIDTHS) {
		await b.viewport(w2, 900);
		await b.goto(PAGE);
		await geometry(b, 'kapalı ' + w2 + 'px', w2, false);
		await b.screenshot(path.join(SHOTS, 'iletisim-kapali-' + w2 + '.png'));
	}
	await b.viewport(1920, 1000);
	await b.goto(PAGE);
	await b.screenshot(path.join(SHOTS, 'iletisim-kapali-1920-tam.png'), true);
}

async function geometry(b, label, w, open) {
	const g = await b.eval(PROBE);
	check(label + ': başlık sağlıklı (kesişme/taşma yok)', g.problems.length === 0, g.problems.join(' | '));
	const s = await b.eval(`(() => {
		const vw = document.documentElement.clientWidth;
		const grid = document.querySelector('.location-grid');
		const cols = grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : 0;
		const over = [...document.querySelectorAll('.location-card, .location-card p, .location-card a, .contact-social a, .contact-write .form-card, .contact-write .demo-notice, .contact-write input:not(.hp-field input), .contact-write textarea, .contact-write button')].filter((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && (r.right > vw + 0.5 || r.left < -0.5); }).map((el) => el.className || el.tagName);
		const cardOver = [...document.querySelectorAll('.location-card')].filter((c) => c.scrollWidth > c.clientWidth + 1).length;
		const soc = [...document.querySelectorAll('.contact-social .footer-social-list a')].map((a) => { const r = a.getBoundingClientRect(); return { w: r.width, h: r.height }; });
		const hero = document.querySelector('.page-hero').getBoundingClientRect();
		const main = document.querySelector('main').getBoundingClientRect();
		const foot = document.querySelector('.site-footer').getBoundingClientRect();
		const wrap = document.querySelector('.contact-write .contact-form-wrap').getBoundingClientRect();
		const card = document.querySelector('.contact-write .form-card, .contact-write .contact-closed .demo-notice');
		const cr = card ? card.getBoundingClientRect() : null;
		const note = document.querySelector('.contact-write .demo-notice');
		return { vw, cols, over, cardOver, soc, heroH: hero.height, heroW: hero.width, overlap: main.bottom > foot.top + 0.5, cardIn: cr ? (cr.left >= wrap.left - 0.5 && cr.right <= wrap.right + 0.5) : false, sw: document.documentElement.scrollWidth, noteH: note ? note.getBoundingClientRect().height : -1 };
	})()`);
	const wantCols = w <= 768 ? 1 : w <= 1024 ? 2 : 3;
	check(label + ': lokasyon ızgarası ' + wantCols + ' sütun', s.cols === wantCols, JSON.stringify(s.cols));
	check(label + ': yatay taşma yok; kart/adres/telefon/sosyal/form öğeleri görüntü alanında; kart içi taşma yok', s.sw <= s.vw && s.over.length === 0 && s.cardOver === 0, JSON.stringify({ over: s.over, cardOver: s.cardOver, sw: s.sw }));
	check(label + ': sosyal ikonlar kırpılmıyor ve >=44x44', s.soc.length === 3 && s.soc.every((x) => x.w >= 44 && x.h >= 44), JSON.stringify(s.soc));
	check(label + ': kahraman kırpılmıyor, Bize Yazın kartı kendi alanında, footer ile çakışma yok', s.heroH > 150 && s.heroW >= s.vw - 1 && s.cardIn && !s.overlap, JSON.stringify(s));
	if (!open) check(label + ': kapalı form bildirimi okunur (görünür yükseklik)', s.noteH > 40, s.noteH);
}

/* ---------------------------------------------------------------- lokasyonlar: merkezi servis (DTO) */
async function contactLocations(b) {
	const f = fixture('locations');
	const r = await get(PAGE);
	common('iletişim (mb_lokasyon)', r);
	const m = mainOf(r.body);
	check('yayında + aktif 4 lokasyon, sort_order sırasıyla, TEK kez; taslak ve pasif GÖRÜNMEZ; yedek kullanılmaz', count(m, /class="location-card"/g) === 4 && m.indexOf('TEST Lokasyon Bir') < m.indexOf('TEST Lokasyon İki') && m.indexOf('TEST Lokasyon İki') < m.indexOf('TEST Lokasyon Üç') && m.indexOf('TEST Lokasyon Üç') < m.indexOf('TEST Lokasyon Dört') && m.indexOf('TEST Taslak Adres') === -1 && r.body.indexOf('TEST Gizli Adres') === -1 && m.indexOf('Payas Sınav Alanı') === -1, JSON.stringify(f));
	check('telefonlar tel: (yalnız rakam), çalışma saatleri gösterilir', m.indexOf('href="tel:00000000001"') !== -1 && m.indexOf('href="tel:00000000002"') !== -1 && m.indexOf('TEST 09:00-18:00') !== -1);
	check('harita: yalnız güvenli https bağlantı (noopener noreferrer); javascript:/http:/boş -> bağlantı YOK, yer tutucu', count(m, /class="directions-link"/g) === 1 && m.indexOf('href="https://harita.example/bir" target="_blank" rel="noopener noreferrer"') !== -1 && !/javascript:|harita\.example\/guvensiz/.test(r.body) && count(m, /class="map-placeholder"/g) === 3);
	const foot = (r.body.match(/<div class="footer-locations">[\s\S]*?<div class="footer-bottom">/) || [''])[0];
	check('footer aynı merkezi veriyi kullanır (TEST Lokasyon Bir var, yedek "Payas" yok, pasif yok)', foot.indexOf('TEST Lokasyon Bir') !== -1 && foot.indexOf('Payas Sınav Alanı') === -1 && foot.indexOf('TEST Gizli') === -1);
	for (const w of [390, 1280]) {
		await b.viewport(w, 900);
		await b.goto(PAGE);
		await geometry(b, 'mb_lokasyon ' + w + 'px', w, false);
		await b.screenshot(path.join(SHOTS, 'iletisim-lokasyon-' + w + '.png'));
	}
}

/* ---------------------------------------------------------------- iletişim formu açık */
async function contactOpen(b) {
	config({ forms: { contact: SYN }, rate_limit: RATE });
	let r = await get(PAGE);
	common('iletişim açık', r);
	const m = mainOf(r.body);
	check('açık: gerçek güvenli form (post, action sorgusuz, mb_form=contact, jeton, nonce, bal küpü)', /<form method="post" action="http:\/\/127\.0\.0\.1:18673\/index\.php\/iletisim\/" novalidate>/.test(m) && hidden(m, 'mb_form') === 'contact' && hidden(m, 'mb_token').length > 20 && hidden(m, '_mb_nonce').length >= 8 && /class="hp-field" aria-hidden="true"/.test(m));
	const names = (m.match(/<(?:input|textarea|select)[^>]*\bname="([^"]+)"/g) || []).map((t) => t.match(/name="([^"]+)"/)[1]).filter((n) => ['mb_form', 'mb_token', '_mb_nonce', 'mb_hp_url'].indexOf(n) === -1);
	check('açık: alanlar yalnız merkezi şemadan (full_name, phone, email, profession, message, consent)', JSON.stringify(names) === JSON.stringify(['full_name', 'phone', 'email', 'profession', 'message', 'consent']), names.join(','));
	check('açık: Ad Soyad + Telefon aynı .form-row satırında; zorunlular aria-required; onaylı (sentetik) rıza metni; "Mesajı Gönder"', /<div class="form-row"><div class="form-field">\s*<label for="mbf-contact-full_name">[\s\S]*?<label for="mbf-contact-phone">/.test(m) && /id="mbf-contact-full_name"[^>]*required aria-required="true"/.test(m) && /id="mbf-contact-email"[^>]*required aria-required="true"/.test(m) && !/id="mbf-contact-phone"[^>]*required/.test(m) && /sentetik onay metni/.test(m) && />Mesajı Gönder</.test(m));
	check('açık: lokasyon ve sosyal bölümleri korunur', count(m, /class="location-card"/g) >= 1 && /id="social-heading"/.test(m));

	// doğrulama hatası: görünür hata özeti + alan hataları + güvenli geri doldurma
	let sec = { mb_form: 'contact', mb_token: hidden(m, 'mb_token'), _mb_nonce: hidden(m, '_mb_nonce'), mb_hp_url: '' };
	await sleep(3500);
	const m0 = mailCount();
	r = await post(PAGE, Object.assign({}, sec, { full_name: 'TEST Sentetik Ad', phone: '', email: 'bozuk', message: '', consent: '', zz_istenmeyen: 'TEST enjekte' }));
	check('geçersiz gönderim: 200, hata özeti role=alert tabindex=-1, alan hataları aria-invalid + aria-describedby, ad geri doldurulur, istenmeyen alan yankılanmaz, e-posta YOK', r.status === 200 && /class="form-error-summary" role="alert" tabindex="-1"/.test(r.body) && /id="mbf-contact-email"[^>]*aria-describedby="mbf-contact-email-error" aria-invalid="true"/.test(r.body) && /id="mbf-contact-message"[^>]*aria-describedby="mbf-contact-message-error" aria-invalid="true"/.test(r.body) && r.body.indexOf('value="TEST Sentetik Ad"') !== -1 && r.body.indexOf('TEST enjekte') === -1 && mailCount() === m0, r.status);
	sec = { mb_form: 'contact', mb_token: hidden(r.body, 'mb_token'), _mb_nonce: hidden(r.body, '_mb_nonce'), mb_hp_url: '' };
	await sleep(3500);
	r = await post(PAGE, Object.assign({}, sec, { full_name: 'TEST Sentetik', phone: '0000 000 00 00', email: 'aday@ornek.example', profession: 'TEST meslek', message: 'TEST mesaj', consent: '1' }));
	check('geçerli sentetik gönderim: 303 PRG, Location yalnız mb_form_status/mb_form, kişisel veri YOK, sahte e-posta TAM 1', r.status === 303 && /\/index\.php\/iletisim\/\?mb_form_status=success&mb_form=contact$/.test(r.location) && !/Sentetik|ornek|0000/.test(r.location) && mailCount() === m0 + 1, r.status + ' ' + r.location + ' mail=' + (mailCount() - m0));
	const rr = await post(PAGE, Object.assign({}, sec, { full_name: 'TEST Sentetik', phone: '', email: 'aday@ornek.example', message: 'TEST mesaj', consent: '1' }));
	check('replay (aynı jeton/nonce): 400, ikinci e-posta YOK', rr.status === 400 && mailCount() === m0 + 1, rr.status);
	const g = await get(r.location);
	check('PRG sonrası GET: başarı iletisi, form yeniden çizilmez (yenileme yeniden göndermez)', g.status === 200 && /Talebiniz alındı|Mesajınız alındı/.test(g.body) && !/<form method="post"/.test(mainOf(g.body)));

	// gerçek Chrome: 8 genişlik + klavye + hata görünürlüğü + JS'siz
	for (const w of WIDTHS) {
		await b.viewport(w, 900);
		await b.goto(PAGE);
		await geometry(b, 'açık ' + w + 'px', w, true);
		const u = await b.eval(`[...document.querySelectorAll('.contact-write input:not([type=hidden]):not([tabindex="-1"]), .contact-write textarea')].map(el => el.getBoundingClientRect().width)`);
		check('açık ' + w + 'px: form alanları kullanılabilir genişlikte', u.length === 6 && u.filter((x, i) => i !== 5).every((x) => x > 120), JSON.stringify(u));
		const row = await b.eval(`(() => { const a=document.getElementById('mbf-contact-full_name').getBoundingClientRect(), c=document.getElementById('mbf-contact-phone').getBoundingClientRect(); return { same: Math.abs(a.top-c.top) < 2, stacked: c.top > a.bottom }; })()`);
		check('açık ' + w + 'px: Ad Soyad + Telefon ' + (w <= 768 ? 'alt alta (mobil tek sütun)' : 'yan yana'), w <= 768 ? row.stacked : row.same, JSON.stringify(row));
		if (w === 390 || w === 1280) await b.screenshot(path.join(SHOTS, 'iletisim-acik-' + w + '.png'));
	}
	await b.viewport(1280, 900);
	await b.goto(PAGE);
	const order = [];
	for (let i = 0; i < 80 && order.indexOf('submit') === -1; i++) {
		await b.key('Tab', 'Tab', 9);
		const a = await b.eval(`(() => { const e=document.activeElement; if(!e) return ''; if(e.closest && e.closest('.contact-write form')) return e.type==='submit' ? 'submit' : (e.name||''); return ''; })()`);
		if (a && order.indexOf(a) === -1) order.push(a);
	}
	check('klavye: Tab ile bütün alanlara ve gönder düğmesine sırayla ulaşılır (bal küpü atlanır)', JSON.stringify(order) === JSON.stringify(['full_name', 'phone', 'email', 'profession', 'message', 'consent', 'submit']), order.join(','));
	await sleep(3500);
	await b.eval(`(() => { const f=document.querySelector('.contact-write form'); HTMLFormElement.prototype.submit.call(f); return 1; })()`);
	await sleep(2500);
	const err = await b.eval(`(() => { const s=document.querySelector('.form-error-summary'); s.focus(); const e=document.getElementById('mbf-contact-full_name-error'); return { vis: getComputedStyle(s).display !== 'none' && s.getBoundingClientRect().height > 20, focus: document.activeElement === s, fieldErr: e ? getComputedStyle(e).display !== 'none' : false, inv: document.getElementById('mbf-contact-full_name').getAttribute('aria-invalid') }; })()`);
	check('Chrome: boş zorunlu alanlar -> hata özeti görünür ve odaklanabilir; alan hatası görünür; aria-invalid', err.vis && err.focus && err.fieldErr && err.inv === 'true', JSON.stringify(err));
	await b.screenshot(path.join(SHOTS, 'iletisim-acik-hata-1280.png'));
	await b.send('Emulation.setScriptExecutionDisabled', { value: true });
	await b.goto(PAGE);
	const nojs = await b.eval(`(() => ({ form: !!document.querySelector('.contact-write form[method=post]'), fields: document.querySelectorAll('.contact-write form input:not([type=hidden]), .contact-write form textarea').length, btn: getComputedStyle(document.querySelector('.contact-write button[type=submit]')).display !== 'none' }))()`).catch(() => null);
	await b.send('Emulation.setScriptExecutionDisabled', { value: false });
	check('JS kapalı: açık form eksiksiz ve gönderilebilir (JS bağımlılığı yok)', nojs && nojs.form && nojs.fields === 7 && nojs.btn, JSON.stringify(nojs));
}

/* ---------------------------------------------------------------- beş form güvenlik matrisi */
const FORMS = {
	contact: { slug: 'iletisim', sensitive: false, file: null },
	application: { slug: 'online-basvuru', sensitive: true, file: { name: 'documents[]', required: false } },
	exam_request: { slug: 'sinav-talepleri', sensitive: false, file: null },
	complaint: { slug: 'itiraz-sikayet', sensitive: false, file: null },
	job_application: { slug: 'is-basvurusu', sensitive: true, file: { name: 'cv', required: true } },
};
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');
function valuesFor(html, id) {
	const v = {};
	const fields = html.match(/<(?:input|textarea|select)[^>]*\bname="([^"]+)"[^>]*>/g) || [];
	fields.forEach((t) => {
		const name = t.match(/name="([^"]+)"/)[1];
		if (['mb_form', 'mb_token', '_mb_nonce', 'mb_hp_url'].indexOf(name) !== -1 || /type="file"/.test(t)) return;
		if (/^<select/.test(t)) {
			const block = (html.match(new RegExp('<select[^>]*name="' + name + '"[\\s\\S]*?<\\/select>')) || [''])[0];
			const opt = (block.match(/<option value="([^"]+)"/) || [])[1];
			v[name] = opt ? opt.replace(/&#0?38;|&amp;/g, '&') : '';
		} else if (/type="radio"/.test(t)) {
			if (!(name in v)) v[name] = t.match(/value="([^"]+)"/)[1];
		} else if (/type="checkbox"/.test(t)) v[name] = '1';
		else if (/type="email"/.test(t)) v[name] = 'aday@ornek.example';
		else if (/type="tel"/.test(t)) v[name] = '0000 000 00 00';
		else if (name === 'national_id') v[name] = '10000000146';
		else if (name === 'candidate_count') v[name] = '5';
		else if (/^<textarea/.test(t)) v[name] = 'TEST mesaj';
		else v[name] = 'TEST Sentetik';
	});
	return v;
}
async function formsMatrix() {
	config({ forms: {} });
	const d0 = fixture('describe');
	check('varsayılan: beş form da KAPALI (kurum kararı yok); doğru sayfa slugları', Object.keys(FORMS).every((id) => d0[id].open === false && d0[id].page === FORMS[id].slug), JSON.stringify(d0));
	for (const id of Object.keys(FORMS)) {
		const r = await get('/index.php/' + FORMS[id].slug + '/');
		check(id + ' varsayılan kapalı: sayfa 200, tek H1, <form method="post"> YOK', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && !/<form[^>]*method="post"/.test(mainOf(r.body)), r.status);
	}
	// hassas / dosya ek kapıları
	config({ forms: { application: Object.assign({}, SYN, { sensitive_fields_approved: '' }), job_application: Object.assign({}, SYN, { uploads_approved: '' }), contact: Object.assign({}, SYN, { consent_approved: '' }), exam_request: Object.assign({}, SYN, { recipient_email: '' }), complaint: Object.assign({}, SYN, { enabled: '' }) }, rate_limit: RATE });
	const d1 = fixture('describe');
	check('ek kapılar: application (hassas onay yok), job_application (yükleme onayı yok), contact (rıza yok), exam_request (alıcı yok), complaint (etkin değil) -> hepsi KAPALI ve doğru neden', d1.application.open === false && d1.application.reasons.indexOf('sensitive_fields_not_approved') !== -1 && d1.job_application.open === false && d1.job_application.reasons.indexOf('uploads_not_approved') !== -1 && d1.contact.open === false && d1.contact.reasons.indexOf('consent_not_approved') !== -1 && d1.exam_request.open === false && d1.exam_request.reasons.indexOf('recipient_not_configured') !== -1 && d1.complaint.open === false && d1.complaint.reasons.indexOf('not_enabled') !== -1, JSON.stringify(d1));
	for (const id of Object.keys(FORMS)) {
		const m0 = mailCount();
		const p = await post('/index.php/' + FORMS[id].slug + '/', { mb_form: id, mb_token: 'x', _mb_nonce: 'x', full_name: 'TEST Sentetik', email: 'aday@ornek.example', consent: '1' });
		check(id + ' ek kapı kapalıyken POST: 503, e-posta YOK', p.status === 503 && mailCount() === m0, p.status);
	}

	for (const id of Object.keys(FORMS)) {
		const F = FORMS[id];
		const url = BASE + '/index.php/' + F.slug + '/';
		const forms = {};
		forms[id] = SYN;
		config({ forms, rate_limit: RATE });
		let r = await get(url);
		const m = mainOf(r.body);
		const vals = valuesFor(m, id);
		check(id + ' açık: gerçek form (mb_form, jeton, nonce, bal küpü)' + (F.file ? ' + multipart' : ''), hidden(m, 'mb_form') === id && hidden(m, 'mb_token').length > 20 && hidden(m, '_mb_nonce').length >= 8 && /class="hp-field" aria-hidden="true"/.test(m) && (!F.file || /enctype="multipart\/form-data"/.test(m)));
		const sec = () => ({ mb_form: id, mb_token: hidden(r.body, 'mb_token'), _mb_nonce: hidden(r.body, '_mb_nonce'), mb_hp_url: '' });
		const m0 = mailCount();
		// yanlış sayfadan POST: işlenmez
		const other = id === 'contact' ? '/index.php/sss/' : '/index.php/iletisim/';
		let p = await post(other, Object.assign({}, sec(), vals));
		check(id + ': yanlış sayfaya POST işlenmez (form yanıtı/e-posta yok)', mailCount() === m0 && !/mb_form_status=success/.test(p.location) && p.status !== 303, p.status);
		await sleep(3500);
		p = await post(url, Object.assign({}, sec(), vals, { _mb_nonce: 'gecersiz' }));
		check(id + ': bozuk nonce -> 400, e-posta YOK', p.status === 400 && mailCount() === m0, p.status);
		const t = sec().mb_token;
		p = await post(url, Object.assign({}, sec(), vals, { mb_token: t.slice(0, -1) + (t.slice(-1) === 'a' ? 'b' : 'a') }));
		check(id + ': kurcalanmış jeton -> 400, e-posta YOK', p.status === 400 && mailCount() === m0, p.status);
		p = await post(url, Object.assign({}, sec(), vals, { mb_hp_url: 'http://spam.example' }));
		check(id + ': bal küpü dolu -> sahte başarı 303, e-posta YOK', p.status === 303 && mailCount() === m0, p.status);
		// doğrulama hatası (zorunlu alanlar boş) + izinli olmayan alan
		const empty = {};
		Object.keys(vals).forEach((k) => (empty[k] = ''));
		p = await post(url, Object.assign({}, sec(), empty, { zz_istenmeyen: 'TEST enjekte' }));
		check(id + ': zorunlu alanlar boş -> 200, hata özeti (role=alert) + aria-invalid; izinsiz alan yok sayılır/yankılanmaz; e-posta YOK', p.status === 200 && /class="form-error-summary" role="alert"/.test(p.body) && /aria-invalid="true"/.test(p.body) && p.body.indexOf('TEST enjekte') === -1 && mailCount() === m0, p.status);
		// geçerli sentetik gönderim (+ dosya) -> PRG
		r = await get(url);
		await sleep(3500);
		const files = F.file ? [{ name: F.file.name, bytes: PDF, type: 'application/pdf', filename: 'sentetik-cv.pdf' }] : null;
		p = await post(url, Object.assign({}, sec(), valuesFor(mainOf(r.body), id)), files);
		check(id + ': geçerli sentetik gönderim -> 303 PRG (Location yalnız sabit durum; kişisel veri YOK), sahte e-posta TAM 1', p.status === 303 && new RegExp('/index\\.php/' + F.slug + '/\\?mb_form_status=success&mb_form=' + id + '$').test(p.location) && !/Sentetik|ornek|0000|10000000146/.test(p.location) && mailCount() === m0 + 1, p.status + ' ' + p.location + ' mail=' + (mailCount() - m0) + ' ' + (p.status === 200 ? (p.body.match(/class="field-error"[^>]*>([^<]*)/g) || []).join('|') : ''));
		if (F.file) check(id + ': geçici yükleme dosyaları gönderim sonrası silinir (yalnız .htaccess/index.php kalır)', tmpLeft() === '0', tmpLeft());
		const rp = await post(url, Object.assign({}, sec(), valuesFor(mainOf(r.body), id)), files);
		check(id + ': replay -> 400, ikinci e-posta YOK', rp.status === 400 && mailCount() === m0 + 1, rp.status);
		if (F.file && F.file.required) {
			r = await get(url);
			await sleep(3500);
			p = await post(url, Object.assign({}, sec(), valuesFor(mainOf(r.body), id)), [{ name: F.file.name, bytes: Buffer.from('MZ bu bir pdf değil'), type: 'application/pdf', filename: 'sahte.pdf' }]);
			check(id + ': uzantısı pdf ama içeriği pdf olmayan dosya (finfo) reddedilir, e-posta YOK, geçici dosya kalmaz', p.status === 200 && /aria-invalid="true"|field-error/.test(p.body) && mailCount() === m0 + 1 && tmpLeft() === '0', p.status + ' tmp=' + tmpLeft());
		}
	}
	// oran sınırı (fail-closed)
	config({ forms: { complaint: SYN }, rate_limit: { per_client: 2, window: 600, global: 2000, global_window: 3600 } });
	let limited = 0;
	for (let i = 0; i < 4; i++) {
		const g = await get('/index.php/itiraz-sikayet/');
		await sleep(3100);
		const x = await post(BASE + '/index.php/itiraz-sikayet/', { mb_form: 'complaint', mb_token: hidden(g.body, 'mb_token'), _mb_nonce: hidden(g.body, '_mb_nonce'), mb_hp_url: '', full_name: '' });
		if (x.status === 429) limited++;
	}
	check('oran sınırı (complaint, istemci başına 2): aşılınca 429 (fail-closed)', limited >= 1, 'limited=' + limited);
	const a = fixture('audit-scan');
	check('audit: form olayları kayıtlı; bağlamlarda sentetik kişisel veri izi YOK', a.rows > 0 && a.pii_hits === 0, JSON.stringify(a));
	config({ forms: {} });
	const d2 = fixture('describe');
	check('temizlik: form ayarları kapalıya döndü (beş form kapalı)', Object.keys(FORMS).every((id) => d2[id].open === false));
}

(async () => {
	const b = await launch();
	try {
		await contactClosed(b);
		await contactOpen(b);
		config({ forms: {} });
		await contactLocations(b);
		await formsMatrix();
		check('Chrome: konsolda hata yok', b.consoleErrors.length === 0, b.consoleErrors.join(' | '));
	} finally {
		await b.close();
		try {
			config({ forms: {} });
		} catch (e) {}
	}
	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 12g iletişim + beş form testi geçti.');
	process.exit(fail ? 1 : 0);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
