'use strict';
/**
 * Faz 12f — online başvuru: `meslek` ön seçimi, tasarım ve form kapısı. GERÇEK WordPress (mbfx_ fixture, gerçek katalog,
 * /index.php/%postname%/) + GERÇEK headless Chrome (CDP). Gerçek kişi verisi YOKTUR (sentetik .example değerleri).
 *
 *   node application-form-test.js closed <qual-fixtures.json> <app-fixtures.json> <ekran-dizini>
 *   node application-form-test.js open   <qual-fixtures.json> <app-fixtures.json> <ekran-dizini>
 */
const fs = require('fs');
const path = require('path');
const { launch } = require('./lib/cdp.js');
const { PROBE } = require('./lib/header-geometry.js');

const [, , PHASE, QF, AF, SHOTS] = process.argv;
const qfx = JSON.parse(fs.readFileSync(QF, 'utf8').replace(/^[^{]*/, ''));
const afx = JSON.parse(fs.readFileSync(AF, 'utf8').replace(/^[^{]*/, ''));
fs.mkdirSync(SHOTS, { recursive: true });
const BASE = 'http://127.0.0.1:18673';
const PAGE = BASE + '/index.php/online-basvuru/';
const AHSAP = { code: '11UY0011-3/03', label: 'Ahşap Kalıpçı — 11UY0011-3/03 (Seviye 3)' };
const IPLIK = { code: '11UY0036-2/01', label: 'İplik Bitim İşleri Operatörü — 11UY0036-2/01 (Seviye 2)' };
const WIDTHS = [390, 768, 820, 1024, 1279, 1280, 1920];
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
const dec = (s) => s.replace(/&amp;/g, '&').replace(/&#038;/g, '&').replace(/&#8212;/g, '—');
const mailCount = () => {
	try {
		return require('child_process').execFileSync('docker', ['exec', 'mbruntime6b2-wp-1', 'sh', '-c', 'wc -l < /tmp/mbfx-mail.count'], { encoding: 'utf8' }).trim();
	} catch (e) {
		return 'yok';
	}
};
async function get(url) {
	const r = await fetch(url.indexOf('http') === 0 ? url : BASE + url, { redirect: 'manual' });
	return { status: r.status, body: await r.text(), location: r.headers.get('location') || '' };
}
async function post(url, data) {
	const body = new URLSearchParams();
	Object.keys(data).forEach((k) => body.append(k, data[k]));
	const r = await fetch(url, { method: 'POST', body, redirect: 'manual', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
	return { status: r.status, body: await r.text(), location: r.headers.get('location') || '' };
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
function selectBlock(body, name) {
	const re = new RegExp('<select id="' + name + '"[^>]*>[\\s\\S]*?<\\/select>');
	return (body.match(re) || [''])[0];
}
function selected(block) {
	const m = block.match(/<option value="([^"]*)"\s+selected='selected'>([^<]*)<\/option>/g) || [];
	return m.map((o) => {
		const x = o.match(/value="([^"]*)"[^>]*>([^<]*)</);
		return { value: dec(x[1]), label: dec(x[2]) };
	});
}
const common = (label, r) => {
	check(label + ': 200, tek H1, PHP uyarısı yok', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && !/(Warning|Notice|Deprecated|Fatal error):/.test(r.body), r.status);
};

async function closedPhase() {
	const QSEL = 'mb-preview-qualification';
	check('URL üretimi (/index.php/%postname%/): /index.php/online-basvuru/?meslek=11UY0011-3%2F03', afx.url_pathinfo === afx.home + '/index.php/online-basvuru/?meslek=11UY0011-3%2F03', afx.url_pathinfo);
	check('URL üretimi (temiz /%postname%/): /online-basvuru/?meslek=11UY0011-3%2F03 (index.php YOK)', afx.url_clean === afx.home + '/online-basvuru/?meslek=11UY0011-3%2F03', afx.url_clean);
	check('URL üretimi: parametresiz genel sayfa her iki yapıda sorgu taşımaz', afx.url_plain_pathinfo === afx.home + '/index.php/online-basvuru/' && afx.url_plain_clean === afx.home + '/online-basvuru/');

	let r = await get(PAGE);
	common('parametresiz', r);
	let b = r.body;
	const hero = b.slice(b.indexOf('<section class="page-hero"'), b.indexOf('</section>', b.indexOf('<section class="page-hero"')));
	check('kahraman: kırıntı Anasayfa / Sınav ve Başvuru (gerçek sayfa bağlantısı) / Online Başvuru; eyebrow; H1; açıklama', /Anasayfa/.test(hero) && hero.indexOf('href="' + BASE + '/index.php/sinav-ve-basvuru/"') !== -1 && /aria-current="page">Online Başvuru</.test(hero) && /class="eyebrow">Sınav ve Başvuru</.test(hero) && /<h1>Online Başvuru<\/h1>/.test(hero) && /Mesleki yeterlilik sınavına başvurmak için aşağıdaki adımları tamamlayın\./.test(hero), hero.slice(0, 300));
	const main = b.slice(b.indexOf('<main'), b.indexOf('</main>'));
	check('kapalı kapı: <form> YOK, name= YOK, <input> YOK, nonce/jeton YOK (kişisel veri alınmaz)', !/<form[ >]/.test(main) && !/\bname="/.test(main) && !/<input\b/.test(main) && !/_mb_nonce|mb_token/.test(main));
	check('kapalı kapı: "şu anda kullanılamıyor" + gerçek başvuru gönderilmediği yazılı; Devam Et devre dışı + açıklama', /şu anda kullanılamıyor/.test(main) && /gerçek başvuru gönderilmez/.test(main) && /<button type="button" class="btn btn-primary" disabled aria-describedby="mb-application-closed-note">Devam Et/.test(main) && /id="mb-application-closed-note"/.test(main));
	check('adım göstergesi: 4 adım, ilki aria-current="step"', /<ol class="step-indicator"/.test(main) && count(main.slice(main.indexOf('step-indicator'), main.indexOf('</ol>', main.indexOf('step-indicator'))), /<li/g) === 4 && /<li class="is-active" aria-current="step">1\. Meslek Seçimi<\/li>/.test(main) && /2\. Kişisel Bilgiler/.test(main) && /3\. Belgeler/.test(main) && /4\. Onay/.test(main));
	const qb = selectBlock(b, QSEL);
	check('parametresiz: meslek seçimi BOŞ (hiçbir seçenek selected değil); seçenek etiketi "Ad — Kod (Seviye N)"', qb !== '' && selected(qb).length === 0 && dec(qb).indexOf('>' + AHSAP.label + '<') !== -1 && dec(qb).indexOf('>' + IPLIK.label + '<') !== -1, qb.slice(0, 200));
	check('seçenekler: taslak ve pasif yeterlilik YOK; yinelenen kod (11UY0014-3/02) fail-closed dışlanır', qb.indexOf('99UY9999-3/00') === -1 && qb.indexOf('98UY9998-3/00') === -1 && qb.indexOf('11UY0014-3/02') === -1);
	check('sınav alanı seçimi 3 doğrulanmış seçenek, seçim boş', count(selectBlock(b, 'mb-preview-exam_location'), /<option value="[a-z]+"/g) === 3 && selected(selectBlock(b, 'mb-preview-exam_location')).length === 0);

	const cases = [
		['?meslek=11UY0011-3%2F03', AHSAP],
		['?meslek=11UY0036-2%2F01', IPLIK],
		['?meslek=11UY0011-3/03', AHSAP],
	];
	for (const [q, want] of cases) {
		r = await get(PAGE + q);
		common('geçerli ' + q, r);
		const s = selected(selectBlock(r.body, QSEL));
		check('geçerli ' + q + ': yalnız "' + want.label + '" seçili', s.length === 1 && s[0].value === want.code && s[0].label === want.label, JSON.stringify(s));
	}
	const bad = {
		'bilinmeyen kod': '?meslek=10UY9999-3%2F00',
		'BOZUK': '?meslek=BOZUK',
		'dizi parametresi': '?meslek%5B%5D=11UY0011-3%2F03',
		'taslak yeterlilik kodu': '?meslek=99UY9999-3%2F00',
		'pasif yeterlilik kodu': '?meslek=98UY9998-3%2F00',
		'yinelenen kod': '?meslek=11UY0014-3%2F02',
		'HTML/JS': '?meslek=%3Cscript%3Ezzqqxss()%3C%2Fscript%3E',
		'çift kodlanmış': '?meslek=11UY0011-3%252F03',
		'küçük harf': '?meslek=11uy0011-3%2F03',
		'aşırı uzun': '?meslek=' + '1'.repeat(5000),
	};
	for (const [label, q] of Object.entries(bad)) {
		r = await get(PAGE + q);
		common(label, r);
		check(label + ': hiçbir yeterlilik seçili değil; ham değer sayfaya basılmaz', selected(selectBlock(r.body, QSEL)).length === 0 && r.body.indexOf('zzqqxss') === -1 && r.body.indexOf('1'.repeat(200)) === -1 && r.body.indexOf('BOZUK') === -1, JSON.stringify(selected(selectBlock(r.body, QSEL))));
	}

	/* arşiv/detay bağlantıları ve genel CTA */
	r = await get(qfx.archive);
	const card = (r.body.match(/<article class="qual-card">(?:(?!<\/article>)[\s\S])*Ahşap Kalıpçı(?:(?!<\/article>)[\s\S])*<\/article>/) || [''])[0];
	check('arşiv kartı: Başvuru Yap -> /index.php/online-basvuru/?meslek=11UY0011-3%2F03 (tek kodlama, %252F yok)', card.indexOf('href="' + BASE + '/index.php/online-basvuru/?meslek=11UY0011-3%2F03"') !== -1 && r.body.indexOf('%252F') === -1 && r.body.indexOf('/index.php/index.php/') === -1, card.slice(0, 300));
	r = await get(qfx.quals.iplik.url);
	check('detay CTA: Bu Yeterlilik İçin Başvur -> ?meslek=11UY0036-2%2F01', r.body.indexOf('href="' + BASE + '/index.php/online-basvuru/?meslek=11UY0036-2%2F01">Bu Yeterlilik İçin Başvur') !== -1);
	check('header/footer genel CTA: sorgusuz /index.php/online-basvuru/', /class="btn btn-primary" href="http:\/\/127\.0\.0\.1:18673\/index\.php\/online-basvuru\/">Online Başvuru/.test(r.body) && !/href="[^"]*online-basvuru\/\?meslek=[^"]*">Online Başvuru/.test(r.body));

	/* kapalı kapıya POST: işlenmez, e-posta yok, kişisel veri URL'de yok */
	const m0 = mailCount();
	r = await post(PAGE + '?meslek=11UY0011-3%2F03', { mb_form: 'application', mb_token: 'x', _mb_nonce: 'x', qualification: AHSAP.code, exam_location: 'iskenderun', full_name: 'TEST Sentetik Aday', national_id: '10000000146', phone: '0000 000 00 00', email: 'aday@ornek.example', consent: '1' });
	check('kapalı kapıya POST -> 503, e-posta YOK, yanıtta kişisel veri yankısı YOK', r.status === 503 && mailCount() === m0 && r.body.indexOf('10000000146') === -1 && r.body.indexOf('aday@ornek.example') === -1 && r.location === '', r.status + ' mail=' + mailCount());

	/* diğer dört form sayfası (kapalı) */
	for (const slug of ['iletisim', 'sinav-talepleri', 'itiraz-sikayet', 'is-basvurusu']) {
		r = await get('/index.php/' + slug + '/');
		const mm = r.body.slice(r.body.indexOf('<main'), r.body.indexOf('</main>'));
		check('regresyon ' + slug + ': 200, tek H1, kapalı form <form> çizmez, "şu anda kullanılamıyor"', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && !/<form[ >]/.test(mm) && /şu anda kullanılamıyor/.test(mm), r.status);
	}

	/* gerçek Chrome */
	const b2 = await launch();
	try {
		for (const w of WIDTHS) {
			for (const [tag, q, want] of [['meslek', '?meslek=11UY0011-3%2F03', AHSAP], ['parametresiz', '', null]]) {
				await b2.viewport(w, 900);
				await b2.goto(PAGE + q);
				const g = await b2.eval(PROBE);
				const st = await b2.eval(`(() => { const vw=document.documentElement.clientWidth; const sel=document.getElementById('${QSEL}'); const card=document.querySelector('.mb-application-preview .form-card'); const r=card.getBoundingClientRect(); const hero=document.querySelector('.page-hero'); const ind=document.querySelector('.step-indicator'); const lis=[...ind.children].map(li=>li.getBoundingClientRect()); let lap=false; for(let i=0;i<lis.length;i++) for(let j=i+1;j<lis.length;j++){ const a=lis[i],c=lis[j]; if(a.left<c.right-0.5&&c.left<a.right-0.5&&a.top<c.bottom-0.5&&c.top<a.bottom-0.5) lap=true; } return { vw, val: sel.value, text: sel.selectedIndex>=0? sel.options[sel.selectedIndex].text : '', cardL: r.left, cardR: r.right, cardW: r.width, hero: !!hero && getComputedStyle(hero).display!=='none' && hero.getBoundingClientRect().height>100, h1: document.querySelector('.page-hero h1').textContent.trim(), indVis: getComputedStyle(ind).display!=='none', indLap: lap, sw: document.documentElement.scrollWidth, selOver: sel.scrollWidth > sel.clientWidth + 40 && sel.getBoundingClientRect().right > vw }; })()`);
				check(w + 'px ' + tag + ': başlık sağlıklı (kesişme/taşma yok)', g.problems.length === 0, g.problems.join(' | '));
				check(w + 'px ' + tag + ': kahraman + H1, form kartı görüntü alanında (kırpılmıyor), gösterge görünür ve kesişmesiz, yatay taşma yok', st.hero && st.h1 === 'Online Başvuru' && st.cardL >= -0.5 && st.cardR <= st.vw + 0.5 && st.cardW > 200 && st.indVis && !st.indLap && st.sw <= st.vw, JSON.stringify(st));
				if (want) check(w + 'px meslek: tarayıcıda seçili değer ' + want.code + ' ve metin "' + want.label + '"', st.val === want.code && st.text === want.label, st.val + ' / ' + st.text);
				else check(w + 'px parametresiz: seçim boş ("Seçiniz")', st.val === '' && st.text === 'Seçiniz', st.val);
				await b2.screenshot(path.join(SHOTS, 'kapali-' + tag + '-' + w + '.png'));
			}
		}
		// arşivde Ahşap Kalıpçı kartındaki "Başvuru Yap" -> gerçek fare tıklaması
		await b2.viewport(1280, 900);
		await b2.goto(qfx.archive);
		const pos = await b2.eval(`(() => { const card=[...document.querySelectorAll('.qual-card')].find(c=>c.querySelector('.qual-card-title').textContent.trim()==='Ahşap Kalıpçı'); const a=[...card.querySelectorAll('a')].find(x=>x.textContent.trim()==='Başvuru Yap'); a.scrollIntoView({block:'center'}); const r=a.getBoundingClientRect(); return {x:r.left+r.width/2, y:r.top+r.height/2}; })()`);
		const nav = new Promise((res) => setTimeout(res, 2500));
		await b2.click(pos.x, pos.y);
		await nav;
		const after = await b2.eval(`({ href: location.href, val: document.getElementById('${QSEL}') ? document.getElementById('${QSEL}').value : null, text: (s => s ? s.options[s.selectedIndex].text : '')(document.getElementById('${QSEL}')) })`);
		check('Chrome: arşivde Ahşap Kalıpçı "Başvuru Yap" tıklaması -> /index.php/online-basvuru/?meslek=11UY0011-3%2F03 ve seçim "' + AHSAP.label + '"', after.href === PAGE + '?meslek=11UY0011-3%2F03' && after.val === AHSAP.code && after.text === AHSAP.label, JSON.stringify(after));
		await b2.screenshot(path.join(SHOTS, 'arsivden-tiklama-1280.png'));
		// detaydan "Bu Yeterlilik İçin Başvur"
		await b2.goto(qfx.quals.iplik.url);
		const pos2 = await b2.eval(`(() => { const a=[...document.querySelectorAll('a')].find(x=>x.textContent.trim()==='Bu Yeterlilik İçin Başvur'); a.scrollIntoView({block:'center'}); const r=a.getBoundingClientRect(); return {x:r.left+r.width/2, y:r.top+r.height/2}; })()`);
		const nav2 = new Promise((res) => setTimeout(res, 2500));
		await b2.click(pos2.x, pos2.y);
		await nav2;
		const after2 = await b2.eval(`({ href: location.href, val: document.getElementById('${QSEL}').value, text: (s => s.options[s.selectedIndex].text)(document.getElementById('${QSEL}')) })`);
		check('Chrome: detayda "Bu Yeterlilik İçin Başvur" tıklaması -> seçim "' + IPLIK.label + '"', after2.href === PAGE + '?meslek=11UY0036-2%2F01' && after2.val === IPLIK.code && after2.text === IPLIK.label, JSON.stringify(after2));
		check('Chrome: konsolda hata yok', b2.consoleErrors.length === 0, b2.consoleErrors.join(' | '));
	} finally {
		await b2.close();
	}
}

async function openPhase() {
	check('fixture: application kapısı yalnız mbfx_ klonunda SENTETİK ayarla açık', afx.open === true);
	let r = await get(PAGE + '?meslek=11UY0011-3%2F03');
	common('açık form', r);
	let b = r.body;
	const qs = selected(selectBlock(b, 'mbf-application-qualification'));
	check('açık form: gerçek güvenli form (post + multipart + nonce + jeton + bal küpü) ve meslek ön seçimi Ahşap Kalıpçı', /<form method="post" action="[^"]*" enctype="multipart\/form-data" novalidate data-step-form>/.test(b) && /name="_mb_nonce"/.test(b) && /name="mb_token" value="[^"]+"/.test(b) && /class="hp-field" aria-hidden="true"/.test(b) && qs.length === 1 && qs[0].value === AHSAP.code && qs[0].label === AHSAP.label, JSON.stringify(qs));
	check('açık form: action sorgusuz (meslek/ham değer form hedefinde taşınmaz)', /<form method="post" action="http:\/\/127\.0\.0\.1:18673\/index\.php\/online-basvuru\/"/.test(b));
	check('açık form: 4 adım fieldset + JS yokken gizli gösterge ve adım eylemleri; gönder düğmesi DOM\'da', count(b, /<fieldset class="form-step" data-step="/g) === 4 && /<ol class="step-indicator" data-step-indicator hidden>/.test(b) && count(b, /data-step-actions hidden/g) === 4 && /data-step-submit/.test(b));
	r = await get(PAGE);
	check('açık form parametresiz: meslek seçimi boş', selected(selectBlock(r.body, 'mbf-application-qualification')).length === 0);
	r = await get(PAGE + '?meslek=BOZUK');
	check('açık form geçersiz meslek: seçim boş, ham değer yok', selected(selectBlock(r.body, 'mbf-application-qualification')).length === 0 && r.body.indexOf('BOZUK') === -1);

	/* POST doğrulama hatası: POST seçimi GET'ten öncelikli; boş POST seçimi GET ile doldurulmaz */
	const tok = (h) => ({ mb_form: 'application', mb_token: (h.match(/name="mb_token" value="([^"]+)"/) || [])[1], _mb_nonce: (h.match(/name="_mb_nonce" value="([^"]+)"/) || [])[1], mb_hp_url: '' });
	let sec = tok(b);
	await sleep(3500);
	const m0 = mailCount();
	r = await post(PAGE + '?meslek=11UY0011-3%2F03', Object.assign({}, sec, { qualification: IPLIK.code, exam_location: 'payas', full_name: '', national_id: '10000000146', phone: '0000 000 00 00', email: 'aday@ornek.example', consent: '1' }));
	let ps = selected(selectBlock(r.body, 'mbf-application-qualification'));
	check('POST doğrulama hatası: 200 + hata özeti; seçim POST değeri (İplik), GET meslek (Ahşap) ezmez; T.C. kimlik geri doldurulmaz; e-posta gitmez', r.status === 200 && /form-error-summary/.test(r.body) && ps.length === 1 && ps[0].value === IPLIK.code && r.body.indexOf('10000000146') === -1 && mailCount() === m0, r.status + ' ' + JSON.stringify(ps));
	check('POST doğrulama hatası: hatalı alan aria-invalid + aria-describedby (Ad Soyad, 2. adım)', /id="mbf-application-full_name"[^>]*aria-describedby="mbf-application-full_name-error" aria-invalid="true"/.test(r.body));
	sec = tok(r.body);
	await sleep(3500);
	r = await post(PAGE + '?meslek=11UY0011-3%2F03', Object.assign({}, sec, { qualification: '', exam_location: 'payas', full_name: 'TEST Sentetik Aday', national_id: '10000000146', phone: '0000 000 00 00', email: 'aday@ornek.example', consent: '1' }));
	ps = selected(selectBlock(r.body, 'mbf-application-qualification'));
	check('POST boş seçim + GET meslek: GET kullanıcının (boş) seçimini ezmez', r.status === 200 && ps.length === 0, JSON.stringify(ps));

	/* geçerli gönderim: PRG, Location kişisel veri / meslek taşımaz */
	sec = tok(r.body);
	await sleep(3500);
	r = await post(PAGE + '?meslek=11UY0011-3%2F03', Object.assign({}, sec, { qualification: AHSAP.code, exam_location: 'iskenderun', full_name: 'TEST Sentetik Aday', national_id: '10000000146', phone: '0000 000 00 00', email: 'aday@ornek.example', consent: '1' }));
	check('geçerli gönderim: 303 PRG; Location yalnız mb_form_status/mb_form (meslek, T.C., e-posta YOK); e-posta 1 kez (kısa devre)', r.status === 303 && /mb_form_status=success/.test(r.location) && !/meslek|10000000146|ornek|Sentetik/.test(r.location) && String(Number(m0) + 1) === mailCount(), r.status + ' ' + r.location + ' mail=' + mailCount());

	/* gerçek Chrome: adımlar, klavye, hata sonrası adım, JS'siz yedek */
	const br = await launch();
	try {
		await br.viewport(1280, 900);
		await br.goto(PAGE + '?meslek=11UY0011-3%2F03');
		const s0 = await br.eval(`(() => { const st=[...document.querySelectorAll('[data-step]')]; const tabs=[...document.querySelectorAll('[data-step-goto]')]; return { ind: !document.querySelector('[data-step-indicator]').hidden, vis: st.map(s=>!s.hidden), cur: tabs.map(t=>t.getAttribute('aria-current')), sub: !document.querySelector('[data-step-submit]').hidden, isBtn: tabs.every(t=>t.tagName==='BUTTON'), val: document.getElementById('mbf-application-qualification').value }; })()`);
		check('JS: gösterge görünür, gerçek <button>; yalnız 1. adım görünür; aria-current="step" ilk adımda; gönder gizli; meslek seçili', s0.ind && s0.isBtn && JSON.stringify(s0.vis) === '[true,false,false,false]' && s0.cur[0] === 'step' && s0.cur.slice(1).every((c) => c === null) && !s0.sub && s0.val === AHSAP.code, JSON.stringify(s0));
		// sınav alanı boşken Devam Et: ilerlemez
		await br.eval(`document.querySelector('[data-step="0"] [data-step-next]').click()`);
		let s1 = await br.eval(`[...document.querySelectorAll('[data-step]')].map(s=>!s.hidden)`);
		check('JS: zorunlu sınav alanı boşken Devam Et ilerlemez (adım doğrulaması)', JSON.stringify(s1) === '[true,false,false,false]', JSON.stringify(s1));
		// klavye: sınav alanını seç, Devam Et'e Tab ile gel, Enter
		await br.eval(`document.getElementById('mbf-application-exam_location').value='payas'; document.getElementById('mbf-application-exam_location').focus()`);
		let onNext = false;
		for (let i = 0; i < 6 && !onNext; i++) {
			await br.key('Tab', 'Tab', 9);
			onNext = await br.eval(`document.activeElement && document.activeElement.hasAttribute('data-step-next')`);
		}
		await br.key('Enter', 'Enter', 13);
		s1 = await br.eval(`(() => ({ vis: [...document.querySelectorAll('[data-step]')].map(s=>!s.hidden), cur: [...document.querySelectorAll('[data-step-goto]')].map(t=>t.getAttribute('aria-current')), focus: document.activeElement.className }))()`);
		check('klavye: Tab ile Devam Et, Enter ile 2. adım; aria-current 2. adımda; odak adım başlığında', onNext && JSON.stringify(s1.vis) === '[false,true,false,false]' && s1.cur[1] === 'step' && /form-step-title/.test(s1.focus), JSON.stringify(s1));
		await br.eval(`document.querySelector('[data-step="1"] [data-step-prev]').click()`);
		s1 = await br.eval(`[...document.querySelectorAll('[data-step]')].map(s=>!s.hidden)`);
		check('Geri: 1. adıma döner', JSON.stringify(s1) === '[true,false,false,false]');
		await br.screenshot(path.join(SHOTS, 'acik-adim1-1280.png'));
		// hata sonrası doğru adım: 2. adımda Ad Soyad boş gönder (JS doğrulamasını aşmak için doğrudan submit)
		await sleep(3500);
		await br.eval(`(() => { const f=document.querySelector('form[data-step-form]'); f.querySelector('#mbf-application-full_name').value=''; f.querySelector('#mbf-application-phone').value='0000 000 00 00'; f.querySelector('#mbf-application-email').value='aday@ornek.example'; f.querySelector('#mbf-application-consent').checked=true; HTMLFormElement.prototype.submit.call(f); return true; })()`);
		await sleep(2500);
		const s2 = await br.eval(`(() => ({ vis: [...document.querySelectorAll('[data-step]')].map(s=>!s.hidden), cur: [...document.querySelectorAll('[data-step-goto]')].map(t=>t.getAttribute('aria-current')), focus: document.activeElement.className, inv: document.querySelector('[aria-invalid="true"]') ? document.querySelector('[aria-invalid="true"]').id : '', sumVis: getComputedStyle(document.querySelector('.form-error-summary')).display !== 'none' && document.querySelector('.form-error-summary').getBoundingClientRect().height > 20, errVis: getComputedStyle(document.getElementById('mbf-application-full_name-error')).display !== 'none', val: document.getElementById('mbf-application-qualification').value }))()`);
		check('hata sonrası: hatalı alanın adımı (2. adım) açık, hata özeti odakta, seçim korunur (Ahşap)', JSON.stringify(s2.vis) === '[false,true,false,false]' && s2.cur[1] === 'step' && /form-error-summary/.test(s2.focus) && s2.inv === 'mbf-application-full_name' && s2.val === AHSAP.code, JSON.stringify(s2));
		check('hata sonrası: hata özeti ve alan hata iletisi GERÇEKTEN görünür (display != none)', s2.sumVis && s2.errVis, JSON.stringify(s2));
		await br.screenshot(path.join(SHOTS, 'acik-hata-adim2-1280.png'));
		// JS'siz yedek
		await br.send('Emulation.setScriptExecutionDisabled', { value: true });
		await br.goto(PAGE + '?meslek=11UY0011-3%2F03');
		const s3 = await br.eval(`(() => ({ vis: [...document.querySelectorAll('[data-step]')].map(s=>getComputedStyle(s).display!=='none'), ind: getComputedStyle(document.querySelector('[data-step-indicator]')).display, sub: getComputedStyle(document.querySelector('[data-step-submit]')).display, val: document.getElementById('mbf-application-qualification').value }))()`).catch(() => null);
		await br.send('Emulation.setScriptExecutionDisabled', { value: false });
		check('JS kapalı: bütün adımlar görünür, gösterge gizli, gönder düğmesi görünür, meslek seçili (sunucu tarafı)', s3 && JSON.stringify(s3.vis) === '[true,true,true,true]' && s3.ind === 'none' && s3.sub !== 'none' && s3.val === AHSAP.code, JSON.stringify(s3));
		await br.viewport(390, 900);
		await br.goto(PAGE + '?meslek=11UY0011-3%2F03');
		const g = await br.eval(PROBE);
		const m = await br.eval(`({ sw: document.documentElement.scrollWidth, vw: document.documentElement.clientWidth })`);
		check('açık form 390px: başlık sağlıklı, yatay taşma yok', g.problems.length === 0 && m.sw <= m.vw, g.problems.join(' | ') + JSON.stringify(m));
		await br.screenshot(path.join(SHOTS, 'acik-390.png'));
		check('Chrome (açık): konsolda hata yok', br.consoleErrors.length === 0, br.consoleErrors.join(' | '));
	} finally {
		await br.close();
	}
}

(async () => {
	if (PHASE === 'closed') await closedPhase();
	else if (PHASE === 'open') await openPhase();
	else throw new Error('faz: closed|open');
	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 12f online başvuru testi (' + PHASE + ') geçti.');
	process.exit(fail ? 1 : 0);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
