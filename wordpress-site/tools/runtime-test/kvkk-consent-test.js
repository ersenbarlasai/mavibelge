'use strict';
/**
 * Faz 14 — Beş formda kısa bağlantılı KVKK onayı. GERÇEK WordPress (mbfx_ fixture) + GERÇEK headless Chrome (CDP).
 * Gerçek kişi verisi/e-posta YOKTUR: değerler sentetik (.example, "TEST"); wp_mail mu-fixture ile kısa devre (yalnız sayaç).
 * Form ayarları ve sentetik /kvkk/ sayfası yalnız bu klonda kurulur; sonunda kapatılır/kaldırılır.
 *
 *   node kvkk-consent-test.js <ekran-dizini>
 */
const fs = require('fs');
const path = require('path');
const cp = require('child_process');
const { launch } = require('./lib/cdp.js');

const [, , SHOTS] = process.argv;
fs.mkdirSync(SHOTS, { recursive: true });
const BASE = 'http://127.0.0.1:18673';
const WP = 'mbruntime6b2-wp-1';
const WIDTHS = [390, 768, 1279, 1280, 1440];
const COPY = 'KVKK Metni’ni okudum ve kabul ediyorum.';
const LONG_MARK = 'TEST-UZUN-KVKK-METNI';
const LONG = LONG_MARK + ': ' + 'Sentetik aydınlatma paragrafı; kurum metni DEĞİLDİR. '.repeat(24).trim();
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
const wpfx = (script, ...a) => JSON.parse(dx(['wp', '--path=/var/www/html', '--require=/opt/mb-runtime/fixture-env.php', '--user=mbadmin', 'eval-file', '/opt/mb-runtime/' + script].concat(a)).replace(/^[^{]*/, ''));
const kfx = (...a) => wpfx('kvkk-fixtures.php', ...a);
const config = (cfg) => wpfx('contact-fixtures.php', 'config', Buffer.from(JSON.stringify(cfg)).toString('base64'));
const mailCount = () => Number(dx(['sh', '-c', 'cat /tmp/mbfx-mail.count 2>/dev/null | wc -l']).trim());
const SYN = { enabled: '1', recipient_email: 'kurum@ornek.example', consent_approved: '1', consent_text: LONG, consent_version: 'test-kvkk-1', sensitive_fields_approved: '1', uploads_approved: '1' };
const RATE = { per_client: 500, window: 600, global: 5000, global_window: 3600 };
const FORMS = {
	contact: { slug: 'iletisim', file: null },
	application: { slug: 'online-basvuru', file: null, stepped: true },
	exam_request: { slug: 'sinav-talepleri', file: null },
	complaint: { slug: 'itiraz-sikayet', file: null },
	job_application: { slug: 'is-basvurusu', file: { name: 'cv' } },
};
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');
const allOpen = () => {
	const forms = {};
	Object.keys(FORMS).forEach((id) => (forms[id] = SYN));
	config({ forms, rate_limit: RATE });
};

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
const decode = (s) => s.replace(/&#0?38;|&amp;/g, '&');
function valuesFor(html) {
	const v = {};
	(html.match(/<(?:input|textarea|select)[^>]*\bname="([^"]+)"[^>]*>/g) || []).forEach((t) => {
		const name = t.match(/name="([^"]+)"/)[1];
		if (['mb_form', 'mb_token', '_mb_nonce', 'mb_hp_url'].indexOf(name) !== -1 || /type="file"/.test(t)) return;
		if (/^<select/.test(t)) {
			const block = (html.match(new RegExp('<select[^>]*name="' + name + '"[\\s\\S]*?<\\/select>')) || [''])[0];
			const opt = (block.match(/<option value="([^"]+)"/) || [])[1];
			v[name] = opt ? decode(opt) : '';
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

// Tarayıcıda onay alanının gerçek durumu (görünür metin = ekran okuyucu metni hariç).
const PROBE = `(() => {
	const cb = [...document.querySelectorAll('main input[type=checkbox][name=consent]')];
	const links = [...document.querySelectorAll('main a.form-consent-link')];
	const c = cb[0], a = links[0];
	const copy = c ? document.querySelector('label[for="' + c.id + '"]') : null;
	let visible = '';
	if (copy) { const k = copy.cloneNode(true); k.querySelectorAll('.screen-reader-text').forEach((x) => x.remove()); visible = k.textContent.replace(/\\s+/g, ' ').trim(); }
	const step = c && c.closest('[data-step]');
	const steps = [...document.querySelectorAll('[data-step]')];
	return { cbs: cb.length, links: links.length, href: a ? a.href : '', target: a ? a.getAttribute('target') : '', rel: a ? a.getAttribute('rel') : '',
		linkText: a ? (() => { const k = a.cloneNode(true); k.querySelectorAll('.screen-reader-text').forEach((x) => x.remove()); return k.textContent.trim(); })() : '',
		srText: a && a.querySelector('.screen-reader-text') ? a.querySelector('.screen-reader-text').textContent.trim() : '',
		visible, required: !!(c && c.required), ariaReq: c ? c.getAttribute('aria-required') : '', inLabel: !!(a && copy && copy.contains(a)),
		stepIndex: step ? steps.indexOf(step) : -1, stepCount: steps.length, long: document.body.innerHTML.indexOf(${JSON.stringify(LONG_MARK)}) !== -1 };
})()`;

async function axNames(b) {
	await b.send('Accessibility.enable');
	const t = await b.send('Accessibility.getFullAXTree');
	const name = (n) => (n.name && n.name.value) || '';
	const role = (n) => (n.role && n.role.value) || '';
	return { checkbox: t.nodes.filter((n) => role(n) === 'checkbox').map(name), links: t.nodes.filter((n) => role(n) === 'link' && /KVKK Metni/.test(name(n))).map(name) };
}
async function targets(b) {
	try {
		const r = await b.send('Target.getTargets');
		return r.targetInfos.filter((t) => t.type === 'page');
	} catch (e) {
		return null;
	}
}
async function closeExtra(b, before) {
	const now = (await targets(b)) || [];
	for (const t of now) if (!before.some((x) => x.targetId === t.targetId)) await b.send('Target.closeTarget', { targetId: t.targetId }).catch(() => {});
}
// Online başvuru (adımlı): her adımı sentetik değerle doldurup "Devam Et" ile son adıma ilerle.
const TO_LAST_STEP = `(async () => {
	const steps = [...document.querySelectorAll('[data-step]')];
	for (let i = 0; i < steps.length - 1; i++) {
		steps[i].querySelectorAll('input, select, textarea').forEach((el) => {
			if (el.type === 'radio') { const first = steps[i].querySelector('input[type=radio][name="' + el.name + '"]'); first.checked = true; }
			else if (el.tagName === 'SELECT') { const o = [...el.options].find((x) => x.value); if (o) el.value = o.value; }
			else if (el.type === 'email') el.value = 'aday@ornek.example';
			else if (el.type === 'tel') el.value = '0000 000 00 00';
			else if (el.name === 'national_id') el.value = '10000000146';
			else if (el.type === 'file' || el.type === 'checkbox' || el.type === 'hidden') {}
			else el.value = 'TEST Sentetik';
		});
		const next = steps[i].querySelector('[data-step-next]');
		if (!next) return -2;
		next.click();
		await new Promise((r) => setTimeout(r, 80));
	}
	return steps.findIndex((s) => !s.hidden && getComputedStyle(s).display !== 'none');
})()`;

async function perForm(b, id, kvkkUrl) {
	const F = FORMS[id];
	const url = BASE + '/index.php/' + F.slug + '/';
	const r = await get(url);
	const m = mainOf(r.body);
	check(id + ' HTTP: 200, PHP uyarısı yok', r.status === 200 && !/(Warning|Notice|Deprecated|Fatal error):/.test(r.body), r.status);
	check(id + ' HTTP: TAM 1 consent onay kutusu (required aria-required="true") ve TAM 1 "KVKK Metni" bağlantısı', count(m, /<input type="checkbox"[^>]*name="consent"/g) === 1 && /<input type="checkbox"[^>]*name="consent" value="1" required aria-required="true"/.test(m) && count(m, /class="form-consent-link"/g) === 1);
	check(id + ' HTTP: bağlantı href = KVKK sayfasının permalink\'i; target=_blank; rel="noopener noreferrer"', m.indexOf('<a class="form-consent-link" href="' + kvkkUrl + '" target="_blank" rel="noopener noreferrer">') !== -1, (m.match(/<a class="form-consent-link"[^>]*>/) || [''])[0]);
	check(id + ' HTTP: uzun yapılandırma metni (consent_text) sayfada HİÇ yok', r.body.indexOf(LONG_MARK) === -1 && r.body.indexOf('Sentetik aydınlatma paragrafı') === -1);
	check(id + ' HTTP: görünür yıldız aria-hidden; ekran okuyucu "(yeni sekmede açılır)"', /class="form-consent-required" aria-hidden="true">\*</.test(m) && /class="form-consent-link"[^>]*>KVKK Metni<span class="screen-reader-text"> \(yeni sekmede açılır\)<\/span><\/a>/.test(m));

	// sunucu doğrulaması: onaysız -> red (hata özeti + aria-invalid + aria-describedby); onaylı -> PRG (akış değişmedi)
	const m0 = mailCount();
	await sleep(3200);
	const vals = valuesFor(m);
	const sec = { mb_form: id, mb_token: hidden(m, 'mb_token'), _mb_nonce: hidden(m, '_mb_nonce'), mb_hp_url: '' };
	const files = F.file ? [{ name: F.file.name, bytes: PDF, type: 'application/pdf', filename: 'sentetik-cv.pdf' }] : null;
	let p = await post(url, Object.assign({}, sec, vals, { consent: '' }), files);
	const pm = mainOf(p.body);
	check(id + ' onaysız gönderim: 200, e-posta YOK, hata özetinde #…-consent bağlantısı "KVKK Metni", onay kutusunda aria-describedby="…-consent-error" aria-invalid="true", alan hatası görünür', p.status === 200 && mailCount() === m0 && new RegExp('<a href="#mbf-' + id + '-consent">KVKK Metni</a>').test(pm) && new RegExp('name="consent" value="1" required aria-required="true" aria-labelledby="mbf-' + id + '-consent-name" aria-describedby="mbf-' + id + '-consent-error" aria-invalid="true"').test(pm) && new RegExp('id="mbf-' + id + '-consent-error" role="alert">Devam etmek için onaylamanız gerekir\\.').test(pm) && pm.indexOf(LONG_MARK) === -1, p.status + ' mail=' + (mailCount() - m0));
	const sec2 = { mb_form: id, mb_token: hidden(p.body, 'mb_token'), _mb_nonce: hidden(p.body, '_mb_nonce'), mb_hp_url: '' };
	await sleep(3200);
	p = await post(url, Object.assign({}, sec2, vals), files);
	check(id + ' onaylı gönderim: 303 PRG (Location yalnız sabit durum, kişisel veri YOK), sahte e-posta TAM 1', p.status === 303 && new RegExp('/index\\.php/' + F.slug + '/\\?mb_form_status=success&mb_form=' + id + '$').test(p.location) && !/Sentetik|ornek|0000|10000000146/.test(p.location) && mailCount() === m0 + 1, p.status + ' ' + p.location + ' mail=' + (mailCount() - m0));

	// gerçek Chrome
	for (const w of WIDTHS) {
		await b.viewport(w, 900);
		await b.goto(url);
		if (F.stepped) {
			const at = await b.eval(TO_LAST_STEP);
			check(id + ' ' + w + 'px: adımlı form "Devam Et" ile son adıma ilerler (onay son adımda)', at >= 0, at);
		}
		const s = await b.eval(PROBE);
		check(id + ' ' + w + 'px: 1 onay kutusu + 1 bağlantı; görünür metin TAM "' + COPY + ' *"; bağlantı metni "KVKK Metni"; href/target/rel doğru; uzun metin yok', s.cbs === 1 && s.links === 1 && s.visible === COPY + ' *' && s.linkText === 'KVKK Metni' && s.srText === '(yeni sekmede açılır)' && s.href === kvkkUrl && s.target === '_blank' && s.rel === 'noopener noreferrer' && !s.long && s.required && s.ariaReq === 'true', JSON.stringify(s));
		if (F.stepped) check(id + ' ' + w + 'px: onay kutusu ve bağlantı SON adımda', s.stepCount > 1 && s.stepIndex === s.stepCount - 1, JSON.stringify(s));
		const g = await b.eval(`(() => {
			const vw = document.documentElement.clientWidth;
			const c = document.querySelector('main input[name=consent]'), a = document.querySelector('main a.form-consent-link'), l = document.querySelector('label[for="' + c.id + '"]'), f = c.closest('.form-consent'), card = c.closest('.form-card');
			const R = (e) => e.getBoundingClientRect();
			const rc = R(c), ra = R(a), rl = R(l), rf = R(f), rk = R(card);
			return { vw, sw: document.documentElement.scrollWidth, fOver: f.scrollWidth > f.clientWidth + 1, lOver: l.scrollWidth > l.clientWidth + 1,
				inCard: [rc, ra, rl].every((r) => r.left >= rk.left - 0.5 && r.right <= rk.right + 0.5), inView: [rc, ra, rl].every((r) => r.left >= -0.5 && r.right <= vw + 0.5),
				side: rc.right <= rl.left + 0.5 && rc.top < rl.bottom, cbW: rc.width, cbH: rc.height, vis: rc.width > 0 && ra.width > 0 && rl.width > 0 };
		})()`);
		check(id + ' ' + w + 'px: yatay taşma yok; onay kutusu/metin/bağlantı kart ve görüntü alanı içinde; kutu metnin solunda; kutu küçültülmemiş (>=16px)', g.vis && g.sw <= g.vw && !g.fOver && !g.lOver && g.inCard && g.inView && g.side && g.cbW >= 16 && g.cbH >= 16, JSON.stringify(g));
		if (w === 390 || w === 1280) {
			await b.eval(`document.querySelector('main input[name=consent]').scrollIntoView({ block: 'center', behavior: 'instant' })`);
			await b.screenshot(path.join(SHOTS, 'kvkk-' + id + '-' + w + '.png'));
		}
	}

	// erişilebilir adlar (Chrome erişilebilirlik ağacı)
	await b.viewport(1280, 900);
	await b.goto(url);
	if (F.stepped) await b.eval(TO_LAST_STEP);
	const ax = await axNames(b);
	check(id + ' AX: onay kutusunun erişilebilir adı TAM cümle; bağlantının adı "KVKK Metni (yeni sekmede açılır)"', ax.checkbox.filter((n) => n === COPY).length === 1 && ax.links.length === 1 && ax.links[0] === 'KVKK Metni (yeni sekmede açılır)', JSON.stringify(ax));

	// klavye: onay kutusundan Tab -> bağlantı (görünür odak halkası) -> Tab ile ilerlenir; Enter bağlantıyı yeni sekmede açar
	await b.eval(`document.querySelector('main input[name=consent]').focus()`);
	await b.key('Tab', 'Tab', 9);
	const kf = await b.eval(`(() => { const a=document.activeElement; const cs=getComputedStyle(a); return { isLink: a.classList.contains('form-consent-link'), fv: a.matches(':focus-visible'), outline: cs.outlineStyle, ow: parseFloat(cs.outlineWidth) }; })()`);
	check(id + ' klavye: onay kutusundan Tab bağlantıya gelir; görünür odak (:focus-visible, >=2px outline)', kf.isLink && kf.fv && kf.outline !== 'none' && kf.ow >= 2, JSON.stringify(kf));
	const t0 = (await targets(b)) || [];
	const loc0 = await b.eval('location.href');
	const mk = mailCount();
	await b.key('Enter', 'Enter', 13);
	await sleep(900);
	const t1 = await targets(b);
	const after = await b.eval(`({ loc: location.href, checked: document.querySelector('main input[name=consent]').checked, step: (() => { const s=[...document.querySelectorAll('[data-step]')]; return s.findIndex((x) => !x.hidden && getComputedStyle(x).display !== 'none'); })() })`);
	const opened = t1 ? t1.filter((t) => !t0.some((x) => x.targetId === t.targetId)) : null;
	check(id + ' klavye Enter: bağlantı YENİ sekmede KVKK sayfasını açar; form sayfası yerinde, onay kutusu işaretlenmez, form gönderilmez', opened && opened.length === 1 && opened[0].url === kvkkUrl && after.loc === loc0 && after.checked === false && mailCount() === mk, JSON.stringify({ opened, after }));
	if (t1) await closeExtra(b, t0);
	await b.key('Tab', 'Tab', 9);
	const nx = await b.eval(`(() => { const a=document.activeElement; return a ? (a.hasAttribute('data-step-prev') ? 'step-prev' : (a.type || a.tagName)) : ''; })()`);
	check(id + ' klavye: bağlantıdan sonra Tab ' + (F.stepped ? 'son adımın "Geri" düğmesine' : 'gönder düğmesine') + ' geçer (odak tuzağı yok)', nx === (F.stepped ? 'step-prev' : 'submit'), nx);

	// fare: bağlantıya tıklamak onay kutusunu değiştirmez, formu göndermez, adımı değiştirmez; metne tıklamak kutuyu işaretler
	for (const initial of [false, true]) {
		await b.eval(`(() => { const c=document.querySelector('main input[name=consent]'); c.checked=${initial}; c.scrollIntoView({ block: 'center', behavior: 'instant' }); })()`);
		await sleep(150);
		const box = await b.eval(`(() => { const r=document.querySelector('main a.form-consent-link').getBoundingClientRect(); return { x: r.left + Math.min(20, r.width / 2), y: r.top + r.height / 2 }; })()`);
		const before = (await targets(b)) || [];
		const st0 = await b.eval(`(() => { const s=[...document.querySelectorAll('[data-step]')]; return s.findIndex((x) => !x.hidden && getComputedStyle(x).display !== 'none'); })()`);
		const mc = mailCount();
		await b.click(box.x, box.y);
		await sleep(900);
		const now = await targets(b);
		const st = await b.eval(`({ loc: location.href, checked: document.querySelector('main input[name=consent]').checked, step: (() => { const s=[...document.querySelectorAll('[data-step]')]; return s.findIndex((x) => !x.hidden && getComputedStyle(x).display !== 'none'); })() })`);
		const nw = now ? now.filter((t) => !before.some((x) => x.targetId === t.targetId)) : null;
		check(id + ' fare: bağlantıya tıklama (kutu ' + (initial ? 'işaretli' : 'boş') + ') kutuyu DEĞİŞTİRMEZ, formu göndermez, adımı değiştirmez; KVKK yeni sekmede', st.checked === initial && st.loc === loc0 && st.step === st0 && mailCount() === mc && nw && nw.length === 1 && nw[0].url === kvkkUrl, JSON.stringify({ st, st0, nw }));
		if (now) await closeExtra(b, before);
	}
	await b.eval(`(() => { const c=document.querySelector('main input[name=consent]'); c.checked=false; c.scrollIntoView({ block: 'center', behavior: 'instant' }); })()`);
	const txt = await b.eval(`(() => { const l=document.querySelector('label[for="' + document.querySelector('main input[name=consent]').id + '"]'); const a=l.querySelector('a').getBoundingClientRect(), r=l.getBoundingClientRect(); return { x: Math.min(r.right - 4, a.right + 40), y: a.top + a.height / 2 }; })()`);
	await b.click(txt.x, txt.y);
	await sleep(200);
	check(id + ' fare: etiket metnine (bağlantı dışı) tıklamak onay kutusunu işaretler (etiket bağlı)', (await b.eval(`document.querySelector('main input[name=consent]').checked`)) === true);

	// JavaScript kapalı: kutu + bağlantı kullanılabilir (adımlı formda bütün adımlar görünür)
	await b.send('Emulation.setScriptExecutionDisabled', { value: true });
	await b.goto(url);
	const nj = await b.eval(PROBE).catch(() => null);
	const njv = await b.eval(`(() => { const c=document.querySelector('main input[name=consent]'); const a=document.querySelector('main a.form-consent-link'); return { cb: c.getBoundingClientRect().width > 0, link: a.getBoundingClientRect().width > 0, submit: getComputedStyle(document.querySelector('main button[type=submit]')).display !== 'none' }; })()`).catch(() => null);
	await b.send('Emulation.setScriptExecutionDisabled', { value: false });
	check(id + ' JS kapalı: onay kutusu ve KVKK bağlantısı görünür/kullanılabilir; gönder düğmesi görünür', nj && nj.cbs === 1 && nj.links === 1 && nj.href === kvkkUrl && njv && njv.cb && njv.link && njv.submit, JSON.stringify({ nj, njv }));
}

(async () => {
	const b = await launch();
	try {
		// 0) RED/GREEN çekirdeği: DTO'da uzun metin yok (gerçek WordPress'te describe())
		const pg = kfx('page');
		check('fixture: sentetik /kvkk/ sayfası yayında', pg.exists === true && /\/index\.php\/kvkk\/$/.test(pg.url), JSON.stringify(pg));
		allOpen();
		const dc = kfx('describe-consent');
		check('describe(): beş form AÇIK; consent etiketi "KVKK Metni"; uzun consent_text DTO\'da YOK; sürüm korunur', Object.keys(FORMS).every((id) => dc[id].open === true && dc[id].label === 'KVKK Metni' && dc[id].leak === false && dc[id].text_len > 1000 && dc[id].version === 'test-kvkk-1'), JSON.stringify(dc));

		// 1) kalıcı bağlantı yapıları
		const u = kfx('urls');
		check('permalink /%postname%/: mavibelge_url(\'kvkk\') = get_permalink() = ' + BASE + '/kvkk/', u.pretty.helper === BASE + '/kvkk/' && u.pretty.permalink === u.pretty.helper, JSON.stringify(u.pretty));
		check('permalink /index.php/%postname%/: mavibelge_url(\'kvkk\') = get_permalink() = ' + BASE + '/index.php/kvkk/', u.index.helper === BASE + '/index.php/kvkk/' && u.index.permalink === u.index.helper, JSON.stringify(u.index));
		kfx('permalink', '/%postname%/');
		for (const id of Object.keys(FORMS)) {
			const r = await get('/?pagename=' + FORMS[id].slug);
			check(id + ' /%postname%/ yapısında: bağlantı href="' + BASE + '/kvkk/"', r.status === 200 && mainOf(r.body).indexOf('<a class="form-consent-link" href="' + BASE + '/kvkk/"') !== -1, r.status);
		}
		kfx('permalink', '/index.php/%postname%/');
		const kv = await get('/index.php/kvkk/');
		check('/index.php/kvkk/ -> 200 (bağlantı hedefi gerçekten açılır)', kv.status === 200 && /<h1>[^<]*KVKK/.test(kv.body), kv.status);

		// 2) beş form
		for (const id of Object.keys(FORMS)) await perForm(b, id, pg.url);

		// 3) yönetim ekranı: kayıt açıklaması + KVKK sayfası görüntüle/düzenle bağlantıları; sayfa yoksa uyarı (kapılar gevşemez)
		const ad = kfx('admin');
		check('yönetim: 5 form için onaylı metin kaydı açıklaması; eski "yanında görünür" yok; consent_text/sürüm alanları korunur; KVKK sayfası görüntüle + düzenle bağlantısı; uyarı yok', ad.record_note === 5 && ad.old_note === 0 && ad.textareas === 5 && ad.versions === 5 && ad.view >= 1 && ad.edit >= 1 && ad.warning === 0, JSON.stringify(ad));
		kfx('page', 'hide');
		const ad2 = kfx('admin');
		check('yönetim: KVKK sayfası YOKSA açık uyarı; bağlantı yok', ad2.exists === false && ad2.warning >= 1, JSON.stringify(ad2));
		const d2 = kfx('describe-consent');
		check('KVKK sayfası yokken form kapıları DEĞİŞMEZ (yapılandırma kapısı; sayfa varlığı kapıyı gevşetmez/sıkılaştırmaz)', Object.keys(FORMS).every((id) => d2[id].open === true), JSON.stringify(d2));
		const r0 = await get('/index.php/iletisim/');
		check('KVKK sayfası yokken bağlantı planlanmış yapıyı izler (/index.php/kvkk/; alan adı gömülü değil)', mainOf(r0.body).indexOf('<a class="form-consent-link" href="' + BASE + '/index.php/kvkk/"') !== -1);
		check('fixture: KVKK sayfası geri yüklendi', kfx('page', 'restore').exists === true);

		// 4) form kapalıyken: kişisel veri alanı / onay kutusu / gerçek form yok
		config({ forms: {} });
		for (const id of Object.keys(FORMS)) {
			const r = await get('/index.php/' + FORMS[id].slug + '/');
			const m = mainOf(r.body);
			check(id + ' kapalı: <form method="post">, onay kutusu, KVKK onay bağlantısı YOK', r.status === 200 && !/<form[^>]*method="post"/.test(m) && !/name="consent"|form-consent/.test(m), r.status);
		}
		check('Chrome: konsol hatası / başarısız kaynak yok', b.consoleErrors.length === 0, b.consoleErrors.join(' | '));
	} finally {
		await b.close();
		try {
			config({ forms: {} });
			kfx('page', 'clear');
			kfx('permalink', '/index.php/%postname%/');
		} catch (e) {}
	}
	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 14 KVKK onay bağlantısı testi geçti.');
	process.exit(fail ? 1 : 0);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
