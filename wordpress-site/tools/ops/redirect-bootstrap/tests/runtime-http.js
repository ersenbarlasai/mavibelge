'use strict';
/**
 * Geçici yönlendirme loader'ı — GERÇEK HTTP testi (yerel Docker, `mbfx_` klonuna yönlendirilmiş WordPress 6.9.9).
 *
 *   node runtime-http.js <çerez-dizini> <manifest.json>
 *
 * Kamu isteği, yetkisiz kullanıcı, nonce, GET reddi, gerçek admin-post import, Sağlık ekranı 29/4, dört etkin
 * kaynağın tek adımlı 301 -> aynı alan adı -> 200 davranışı ve rollback uçtan uca sınanır. Çerez değerleri yazdırılmaz.
 */
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:18673';
const cookieDir = process.argv[2];
const manifest = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));
const SLUG = 'mavibelge-redirect-bootstrap';
const IMPORT = 'mavibelge_redirect_bootstrap_import';
const ROLLBACK = 'mavibelge_redirect_bootstrap_rollback';

let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) {
		pass++;
		console.log('PASS  ' + label);
	} else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + detail + ']' : ''));
	}
}
function jar(login) {
	const j = JSON.parse(fs.readFileSync(path.join(cookieDir, 'cookies-' + login + '.json'), 'utf8'));
	return Object.keys(j).map((k) => k + '=' + j[k]).join('; ');
}
async function req(p, opts = {}) {
	const headers = Object.assign({}, opts.headers || {});
	if (opts.cookie) headers.Cookie = opts.cookie;
	let body;
	if (opts.form) {
		body = new URLSearchParams(opts.form).toString();
		headers['Content-Type'] = 'application/x-www-form-urlencoded';
	}
	const res = await fetch(BASE + p, { method: opts.method || (opts.form ? 'POST' : 'GET'), headers, body, redirect: 'manual' });
	return { status: res.status, location: res.headers.get('location') || '', body: await res.text() };
}
const nonceOf = (html, action) => {
	const re = new RegExp('name="action" value="' + action + '" />\\s*<input type="hidden" id="mb_rb_nonce" name="mb_rb_nonce" value="([a-f0-9]+)"');
	const m = html.match(re);
	return m ? m[1] : '';
};
const resultOf = (loc) => {
	const m = loc.match(/mb_rb_result=([a-z_]+)/);
	return m ? m[1] : '';
};

(async () => {
	const admin = jar('mbadmin');
	const sub = jar('mbsubscriber');
	const active = manifest.rules.filter((r) => r.active === true);
	check('Manifest: 29 kural / 4 etkin', manifest.rules.length === 29 && active.length === 4);

	// Kamu / doğrudan erişim
	const direct = await req('/wp-content/mu-plugins/mavibelge-redirect-bootstrap.php');
	check('Loader PHP dosyası web üzerinden doğrudan çağrılınca hiçbir çıktı üretmez', direct.body.length === 0, 'status=' + direct.status + ' len=' + direct.body.length);
	const anonPost = await req('/wp-admin/admin-post.php', { form: { action: IMPORT, mb_rb_nonce: 'x' } });
	check('Oturumsuz admin-post import isteği sonuç üretmez (nopriv eylemi yok)', resultOf(anonPost.location) === '', 'status=' + anonPost.status);
	const pre = await req('/kvkk-2/');
	check('Import öncesi /kvkk-2/ 404 (canlıdaki P1 bulgusunun yeniden üretimi)', pre.status === 404, 'status=' + pre.status);
	const home = await req('/');
	check('Ana sayfa anonim HTTP 200 ve loader bildirimi/formu içermez', home.status === 200 && !home.body.includes(SLUG), 'status=' + home.status);

	// Yetkisiz kullanıcı
	const subPage = await req('/wp-admin/tools.php?page=' + SLUG, { cookie: sub });
	check('Abone kullanıcı loader sayfasını göremez (form/nonce yok)', !subPage.body.includes('mb_rb_nonce'), 'status=' + subPage.status);

	// Yönetici sayfası
	const page = await req('/wp-admin/tools.php?page=' + SLUG, { cookie: admin });
	const n1 = nonceOf(page.body, IMPORT);
	check('Yönetici: Araçlar sayfası 200, nonce korumalı "Yönlendirmeleri içe aktar" formu, rollback formu yok',
		page.status === 200 && page.body.includes('Yönlendirmeleri içe aktar') && n1 !== '' && nonceOf(page.body, ROLLBACK) === '', 'status=' + page.status);
	const adminNotice = await req('/wp-admin/index.php', { cookie: admin });
	check('Yönetici panelinde silme hatırlatma bildirimi görünür', adminNotice.body.includes('Geçici yönlendirme deposu kurulum aracı etkin'));

	const subPost = await req('/wp-admin/admin-post.php', { cookie: sub, form: { action: IMPORT, mb_rb_nonce: n1 } });
	check('Abone kullanıcı yöneticinin nonce\'uyla POST ederse 403 (forbidden), import olmaz', subPost.status === 403 && resultOf(subPost.location) === '', 'status=' + subPost.status);
	const bad = await req('/wp-admin/admin-post.php', { cookie: admin, form: { action: IMPORT, mb_rb_nonce: 'yanlis' } });
	check('Yönetici + yanlış nonce: 302 ve sonuç nonce_invalid', bad.status === 302 && resultOf(bad.location) === 'nonce_invalid', bad.location);
	const noNonce = await req('/wp-admin/admin-post.php', { cookie: admin, form: { action: IMPORT } });
	check('Yönetici + nonce yok: sonuç nonce_invalid', resultOf(noNonce.location) === 'nonce_invalid', noNonce.location);
	const getReq = await req('/wp-admin/admin-post.php?action=' + IMPORT + '&mb_rb_nonce=' + n1, { cookie: admin });
	check('GET ile import reddedilir: bad_method (link ile tetiklenemez)', resultOf(getReq.location) === 'bad_method', getReq.location);
	const still = await req('/kvkk-2/');
	check('Reddedilen denemeler sonrası /kvkk-2/ hâlâ 404 (hiçbir şey yazılmadı)', still.status === 404, 'status=' + still.status);

	// Import
	const imp = await req('/wp-admin/admin-post.php', { cookie: admin, form: { action: IMPORT, mb_rb_nonce: n1 } });
	check('Yönetici + geçerli nonce POST: 302 ve sonuç imported', imp.status === 302 && resultOf(imp.location) === 'imported', imp.location);
	const shown = await req('/wp-admin/tools.php?page=' + SLUG + '&mb_rb_result=imported', { cookie: admin });
	check('Sonuç sayfası başarı mesajı + silme talimatı + rollback formu; import formu yok',
		shown.body.includes('toplam 29, etkin 4') && shown.body.includes('File Manager') && nonceOf(shown.body, ROLLBACK) !== '' && nonceOf(shown.body, IMPORT) === '');
	const health = await req('/wp-admin/tools.php?page=mavibelge-core-health', { cookie: admin });
	check('Araçlar → Mavi Belge Sağlık: "Toplam 29, etkin 4."', health.status === 200 && health.body.includes('Toplam 29, etkin 4.'), 'status=' + health.status);
	const again = await req('/wp-admin/admin-post.php', { cookie: admin, form: { action: IMPORT, mb_rb_nonce: n1 } });
	check('İkinci HTTP import reddedilir: already_imported', resultOf(again.location) === 'already_imported', again.location);

	// Dört etkin kaynak: tek adımlı 301 -> aynı alan adı -> 200
	for (const r of active) {
		const first = await req(r.source);
		let loc;
		try {
			loc = new URL(first.location, BASE);
		} catch (e) {
			loc = null;
		}
		const sameHost = loc && loc.host === new URL(BASE).host;
		const target = loc ? await req(loc.pathname + loc.search) : { status: 0 };
		check(r.source + ' -> ' + r.target + ': 301, aynı alan adı, hedef tek adımda 200 (zincir/döngü yok)',
			first.status === 301 && sameHost && loc.pathname === r.target && target.status === 200,
			'status=' + first.status + ' loc=' + (loc ? loc.pathname : '') + ' target=' + target.status);
	}
	const inactive = manifest.rules.find((r) => r.active === false);
	const inact = await req(inactive.source);
	check('Pasif (proposed) bir kural yönlendirme üretmez: ' + inactive.source + ' -> 404', inact.status === 404, 'status=' + inact.status);
	const existing = await req('/kvkk/');
	check('Var olan içerik gölgelenmez: /kvkk/ 200', existing.status === 200, 'status=' + existing.status);

	// Rollback
	const n2 = nonceOf(shown.body, ROLLBACK);
	const rbBad = await req('/wp-admin/admin-post.php', { cookie: admin, form: { action: ROLLBACK, mb_rb_nonce: n1 } });
	check('Rollback import nonce\'uyla reddedilir: nonce_invalid', resultOf(rbBad.location) === 'nonce_invalid', rbBad.location);
	const rb = await req('/wp-admin/admin-post.php', { cookie: admin, form: { action: ROLLBACK, mb_rb_nonce: n2 } });
	check('Rollback: 302 ve sonuç rolled_back', rb.status === 302 && resultOf(rb.location) === 'rolled_back', rb.location);
	const after = await req('/kvkk-2/');
	const health2 = await req('/wp-admin/tools.php?page=mavibelge-core-health', { cookie: admin });
	check('Rollback sonrası /kvkk-2/ yeniden 404 ve Sağlık "Toplam 0, etkin 0."', after.status === 404 && health2.body.includes('Toplam 0, etkin 0.'), 'status=' + after.status);
	const home2 = await req('/');
	check('Rollback sonrası ana sayfa 200', home2.status === 200);

	console.log('\n' + (pass + fail) + ' test, ' + pass + ' geçti, ' + fail + ' başarısız.');
	process.exit(fail ? 1 : 0);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
