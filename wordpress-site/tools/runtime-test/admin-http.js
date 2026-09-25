// Host tarafı: izole test WordPress'ine GERÇEK HTTP istekleri (tarayıcı değil).
// Kullanım:
//   node admin-http.js login  <env-dosyası> <durum-dizini>   (oturum aç + ısınma GET'leri)
//   node admin-http.js warmup <durum-dizini>                 (parolasız: jar'lar make-cookies.php ile üretilir)
//   node admin-http.js tests  <env-dosyası|-> <durum-dizini> <cli-json>
// Parolalar yalnız `login` modunda env dosyasından okunur; hiçbir yere yazdırılmaz.
// `warmup` ve `tests` modları env dosyası OKUMAZ.
'use strict';
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:18673';
const PAGE = '/wp-admin/tools.php?page=mavibelge-core-import-dry-run';
const NONCE_NAME = 'mavibelge_core_import_dry_run_nonce';

const argv = process.argv.slice(2);
const mode = argv[0];
const envFile = mode === 'warmup' ? null : argv[1];
const stateDir = mode === 'warmup' ? argv[1] : argv[2];
const cliJson = argv[3];
function readEnv() {
	return Object.fromEntries(fs.readFileSync(envFile, 'utf8').split(/\r?\n/).filter(Boolean).map((l) => {
		const i = l.indexOf('=');
		return [l.slice(0, i), l.slice(i + 1)];
	}));
}

function jarFile(user) { return path.join(stateDir, 'cookies-' + user + '.json'); }
function loadJar(user) { return JSON.parse(fs.readFileSync(jarFile(user), 'utf8')); }
function cookieHeader(jar) { return Object.entries(jar).map(([k, v]) => k + '=' + v).join('; '); }
function absorb(jar, res) {
	const set = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
	for (const c of set) {
		const [pair] = c.split(';');
		const i = pair.indexOf('=');
		const k = pair.slice(0, i).trim();
		const v = pair.slice(i + 1).trim();
		if (/expires=Thu, 01[- ]Jan[- ]1970/i.test(c) || v === 'deleted') { delete jar[k]; } else { jar[k] = v; }
	}
}

async function req(jar, method, url, form) {
	const headers = { cookie: cookieHeader(jar) };
	let body;
	if (form) {
		headers['content-type'] = 'application/x-www-form-urlencoded';
		body = form;
	}
	const res = await fetch(BASE + url, { method, headers, body, redirect: 'manual' });
	absorb(jar, res);
	const text = await res.text();
	return { status: res.status, location: res.headers.get('location'), text };
}

async function login(user, pw) {
	const jar = { wordpress_test_cookie: 'WP%20Cookie%20check' };
	await req(jar, 'GET', '/wp-login.php');
	const form = new URLSearchParams({ log: user, pwd: pw, 'wp-submit': 'Log In', testcookie: '1', redirect_to: BASE + '/wp-admin/' }).toString();
	const r = await req(jar, 'POST', '/wp-login.php', form);
	if (r.status !== 302 || !Object.keys(jar).some((k) => k.startsWith('wordpress_logged_in_'))) {
		throw new Error('giriş başarısız: ' + user + ' status=' + r.status);
	}
	fs.writeFileSync(jarFile(user), JSON.stringify(jar));
	return jar;
}

function nonceFrom(html) {
	const m = new RegExp('name="' + NONCE_NAME + '" value="([0-9a-f]+)"').exec(html);
	return m ? m[1] : null;
}

function decodeHtml(s) {
	return s.replace(/&#0?39;/g, "'").replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
}

function rowsFrom(html) {
	const tbodyStart = html.lastIndexOf('<table class="widefat striped">');
	if (tbodyStart === -1) { return []; }
	const seg = html.slice(tbodyStart, html.indexOf('</table>', tbodyStart));
	const rows = [];
	const trRe = /<tr class="[^"]*">([\s\S]*?)<\/tr>/g;
	let m;
	while ((m = trRe.exec(seg))) {
		const cells = [...m[1].matchAll(/<td>([\s\S]*?)<\/td>/g)].map((c) => decodeHtml(c[1]));
		rows.push({ source_key: cells[0], type: cells[1], decision: cells[2], reason: cells[3], target_id: cells[4] === '—' ? null : Number(cells[4]), changed: cells[5], unresolved: cells[6] });
	}
	return rows;
}

async function runLogin() {
	fs.mkdirSync(stateDir, { recursive: true });
	const env = readEnv();
	const out = {};
	for (const [user, pw] of [['mbadmin', env.WP_ADMIN_PASSWORD], ['mbeditor', env.WP_LOW_PASSWORD], ['mbsubscriber', env.WP_LOW_PASSWORD]]) {
		const jar = await login(user, pw);
		// Isınma: oturum açıldıktan sonra tek seferlik yönetim yazılarını (ör. kullanıcı ayarları)
		// başlangıç anlık görüntüsünden ÖNCE tamamlamak için.
		const a = await req(jar, 'GET', '/wp-admin/');
		const b = await req(jar, 'GET', PAGE);
		fs.writeFileSync(jarFile(user), JSON.stringify(jar));
		out[user] = { dashboard: a.status, page: b.status };
	}
	console.log(JSON.stringify(out));
}

async function runTests() {
	const results = [];
	const t = (name, ok, detail) => { results.push({ name, ok: !!ok, detail }); };
	const admin = loadJar('mbadmin');

	// 1) Yetkisiz kullanıcılar.
	for (const user of ['mbeditor', 'mbsubscriber']) {
		const jar = loadJar(user);
		const g = await req(jar, 'GET', PAGE);
		t(user + ' GET sayfa erişemez', g.status === 403 && !g.text.includes('Salt Okunur Dry-Run Çalıştır'), 'status=' + g.status);
		const p = await req(jar, 'POST', PAGE, new URLSearchParams({ [NONCE_NAME]: 'x', mb_paged: '1' }).toString());
		t(user + ' POST dry-run çalıştıramaz', p.status === 403 && !p.text.includes('<h2>Sonuç</h2>'), 'status=' + p.status);
	}

	// 2) Yönetici GET: form var, sonuç yok, apply/import düğmesi yok.
	const g = await req(admin, 'GET', PAGE);
	const nonce = nonceFrom(g.text);
	t('admin GET 200 + form + nonce', g.status === 200 && !!nonce && g.text.includes('Salt Okunur Dry-Run Çalıştır'), 'status=' + g.status);
	t('admin GET dry-run ÇALIŞTIRMAZ (sonuç bölümü yok)', !g.text.includes('<h2>Sonuç</h2>'), '');
	const formSeg = g.text.slice(g.text.indexOf('<div class="wrap"><h1>İçe Aktarım Dry-Run'));
	const submitValues = [...formSeg.matchAll(/type="submit"[^>]*value="([^"]*)"/g)].map((m) => m[1]);
	t('admin sayfasında yalnız "Salt Okunur Dry-Run Çalıştır" düğmesi (apply/import/yaz yok)', submitValues.length === 1 && submitValues[0] === 'Salt Okunur Dry-Run Çalıştır' && !/apply|uygula|içe aktar(?!ım)|import et|yaz\b/i.test(submitValues.join(' ')), JSON.stringify(submitValues));

	// 3) Nonce fail-closed.
	const pMissing = await req(admin, 'POST', PAGE, new URLSearchParams({ mb_paged: '1' }).toString());
	t('POST nonce eksik -> 400 wp_die, dry-run yok', pMissing.status === 400 && !pMissing.text.includes('<h2>Sonuç</h2>'), 'status=' + pMissing.status);
	const pBad = await req(admin, 'POST', PAGE, new URLSearchParams({ [NONCE_NAME]: 'deadbeef00', mb_paged: '1' }).toString());
	t('POST nonce bozuk -> 403 wp_die, dry-run yok', pBad.status === 403 && !pBad.text.includes('<h2>Sonuç</h2>'), 'status=' + pBad.status);
	const pArr = await req(admin, 'POST', PAGE, NONCE_NAME + '%5B%5D=' + nonce + '&mb_paged=1');
	t('POST nonce dizi -> 400 wp_die, dry-run yok', pArr.status === 400 && !pArr.text.includes('<h2>Sonuç</h2>'), 'status=' + pArr.status);
	const pObj = await req(admin, 'POST', PAGE, NONCE_NAME + '%5Ba%5D=' + nonce + '&mb_paged=1');
	t('POST nonce anahtarlı dizi (nesne biçimi) -> 400', pObj.status === 400 && !pObj.text.includes('<h2>Sonuç</h2>'), 'status=' + pObj.status);

	// 4) Geçerli nonce ile dry-run + tüm sayfalar; CLI ile karşılaştırma.
	const post = (paged) => req(admin, 'POST', PAGE, new URLSearchParams({ [NONCE_NAME]: nonce, mb_paged: paged }).toString());
	const p1 = await post('1');
	t('POST geçerli nonce -> 200 + sonuç', p1.status === 200 && p1.text.includes('<h2>Sonuç</h2>'), 'status=' + p1.status);
	const totalM = /<tr><th>Toplam<\/th><td>(\d+)<\/td>/.exec(p1.text);
	t('admin sonuç Toplam=200', totalM && totalM[1] === '200', totalM ? totalM[1] : 'yok');
	let adminRows = [];
	for (let page = 1; page <= 8; page++) {
		const r = page === 1 ? p1 : await post(String(page));
		const rows = rowsFrom(r.text);
		t('sayfa ' + page + ' -> 25 satır', rows.length === 25, 'rows=' + rows.length);
		adminRows = adminRows.concat(rows);
	}
	const cli = JSON.parse(fs.readFileSync(cliJson, 'utf8').replace(/^[^{]*/, ''));
	const cliRows = cli.entries.map((e) => ({ source_key: e.source_key, type: e.type, decision: e.decision, reason: e.reason, target_id: e.target_id, changed: e.changed_fields.join(', '), unresolved: e.unresolved_dependencies.join(', ') }));
	const same = JSON.stringify(adminRows) === JSON.stringify(cliRows);
	t('admin 8 sayfanın 200 satırı CLI JSON ile BİREBİR aynı (source_key/type/decision/reason/target_id/changed/unresolved)', same, 'admin=' + adminRows.length + ' cli=' + cliRows.length);
	const opsAdmin = {};
	for (const op of ['create', 'update', 'unchanged', 'conflict', 'blocked', 'invalid']) {
		const m = new RegExp('<tr><th>' + op + '</th><td>(\\d+)</td>').exec(p1.text);
		opsAdmin[op] = m ? Number(m[1]) : null;
	}
	t('admin operations özeti CLI summary.operations ile aynı', JSON.stringify(opsAdmin) === JSON.stringify(cli.summary.operations), JSON.stringify(opsAdmin));

	// 5) mb_paged biçimi: yalnız pozitif kanonik onluk kabul; diğerleri sayfa 1'e düşer.
	const page2first = cliRows[25].source_key;
	const page1first = cliRows[0].source_key;
	const firstKey = (html) => { const r = rowsFrom(html); return r.length ? r[0].source_key : null; };
	const p2 = await post('2');
	t('mb_paged="2" kabul -> 2. sayfa', firstKey(p2.text) === page2first, firstKey(p2.text));
	const p10 = await post('10');
	t('mb_paged="10" kabul -> son sayfaya (8) sıkıştırılır', rowsFrom(p10.text).length === 25 && rowsFrom(p10.text)[0].source_key === cliRows[175].source_key, '');
	for (const bad of ['2\n', '2\r\n', '1e2', '2.0', '-2', '0', '+2', ' 2', '02']) {
		const r = await post(bad);
		t('mb_paged=' + JSON.stringify(bad) + ' reddedilir -> sayfa 1', r.status === 200 && firstKey(r.text) === page1first, firstKey(r.text));
	}
	for (const [label, body] of [['dizi', NONCE_NAME + '=' + nonce + '&mb_paged%5B%5D=2'], ['anahtarlı dizi/nesne', NONCE_NAME + '=' + nonce + '&mb_paged%5Ba%5D=2']]) {
		const r = await req(admin, 'POST', PAGE, body);
		t('mb_paged ' + label + ' reddedilir -> sayfa 1', r.status === 200 && firstKey(r.text) === page1first, firstKey(r.text));
	}

	// 6) Kaçış: tablo hücrelerinde ham HTML yok; hücre içerikleri güvenli karakter kümesinde.
	// Kapsam: yalnız eklentinin sonuç bölümü (<h2>Sonuç</h2> -> .wrap kapanışından önceki son </table>);
	// WordPress'in kendi admin footer <script> etiketleri bu kontrolün dışındadır.
	const cellsOk = adminRows.every((r) => Object.values(r).every((v) => v === null || typeof v === 'number' || /^[\p{L}\p{N}:\/_, .-]*$/u.test(v)));
	const resStart = p1.text.indexOf('<h2>Sonuç</h2>');
	const resEnd = p1.text.indexOf('</table>', p1.text.lastIndexOf('<table class="widefat striped">'));
	const resultSection = p1.text.slice(resStart, resEnd);
	const tags = [...new Set([...resultSection.matchAll(/<([a-zA-Z0-9]+)[\s>]/g)].map((m) => m[1].toLowerCase()))].sort();
	const allowedTags = ['code', 'div', 'em', 'h2', 'h3', 'li', 'p', 'strong', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'ul'];
	t('admin sonuç bölümü yalnız beklenen etiketleri içeriyor; hücrelerde ham HTML yok', cellsOk && resStart !== -1 && tags.every((x) => allowedTags.includes(x)), tags.join(','));

	fs.writeFileSync(path.join(stateDir, 'admin-p1.html'), p1.text);
	const passed = results.filter((r) => r.ok).length;
	for (const r of results) { console.log((r.ok ? 'PASS  ' : 'FAIL  ') + r.name + (r.detail ? '  [' + r.detail + ']' : '')); }
	console.log('\n' + passed + '/' + results.length + ' admin HTTP testi geçti.');
	process.exit(passed === results.length ? 0 : 1);
}

async function runWarmup() {
	// Parolasız: jar'lar önceden make-cookies.php ile durum dizinine kopyalanır.
	const out = {};
	for (const user of ['mbadmin', 'mbeditor', 'mbsubscriber']) {
		const jar = loadJar(user);
		const a = await req(jar, 'GET', '/wp-admin/');
		const b = await req(jar, 'GET', PAGE);
		fs.writeFileSync(jarFile(user), JSON.stringify(jar));
		out[user] = { dashboard: a.status, page: b.status };
	}
	console.log(JSON.stringify(out));
}

(mode === 'login' ? runLogin() : mode === 'warmup' ? runWarmup() : runTests()).catch((e) => { console.error(e.message); process.exit(2); });
