'use strict';
/**
 * Faz 10 HTTP testleri: gereksiz WordPress yüklerinin ön yüzden kalkması (yönetim etkilenmez), yerel görsel öznitelikleri,
 * güvenli başlıklar, anonim REST/XML-RPC/kullanıcı numaralandırma kapalı, genel giriş hatası + oran sınırı, sağlık ekranı.
 * YALNIZ mbfx_ fixture veritabanına yönlendirilmiş yerel ortamda çalışır (content-http.sh).
 *   node perf-security-http-test.js <cookie-dizini>   (cookies-mbadmin.json / cookies-mbeditor.json / cookies-mbsubscriber.json)
 * Çerez/parola değerleri ekrana basılmaz.
 */
const fs = require('fs');
const path = require('path');

const BASE = 'http://127.0.0.1:18673';
const cookieDir = process.argv[2];
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
function jar(user) {
	const j = JSON.parse(fs.readFileSync(path.join(cookieDir, 'cookies-' + user + '.json'), 'utf8'));
	return Object.entries(j).map(([k, v]) => k + '=' + v).join('; ');
}
async function req(url, opts) {
	const res = await fetch(BASE + url, Object.assign({ redirect: 'manual' }, opts || {}));
	const headers = {};
	res.headers.forEach((v, k) => {
		headers[k] = v;
	});
	return { status: res.status, body: await res.text(), headers };
}
function pngSize(file) {
	const b = fs.readFileSync(file);
	return { w: b.readUInt32BE(16), h: b.readUInt32BE(20) };
}
const themeDir = path.join(__dirname, '..', '..', 'wp-content', 'themes', 'mavibelge');
const imgTag = (html, src) => (html.match(new RegExp('<img[^>]*' + src + '[^>]*>', 'g')) || []);
const attr = (tag, name) => (tag.match(new RegExp(' ' + name + '="([^"]*)"')) || [])[1];

(async () => {
	/* ---------- 1) ön yüz HTML: kaldırılan varsayılanlar, tema varlıkları ---------- */
	const home = await req('/');
	const h = home.body;
	check('ön yüz 200', home.status === 200, String(home.status));
	check('emoji betiği/stili YOK (wp-emoji, _wpemojiSettings)', !/wp-emoji|_wpemojiSettings|emoji-styles/.test(h));
	check('wp-embed ve oEmbed keşif bağlantısı YOK', !/wp-embed|wp-json\/oembed|type="application\/json\+oembed"/.test(h));
	check('wp_generator, RSD, WLW, shortlink bağlantıları YOK', !/name="generator"|rel="EditURI"|wlwmanifest|rel='shortlink'|rel="shortlink"/.test(h));
	check('REST keşif bağlantısı (api.w.org) YOK', !/api\.w\.org/.test(h));
	check('jQuery ve jquery-migrate YOK', !/jquery(\.min)?\.js|jquery-migrate/.test(h));
	check('blok kitaplığı / klasik tema stilleri / global styles YOK', !/wp-block-library|classic-theme-styles|global-styles-inline-css|id='global-styles|id="global-styles/.test(h));
	const styleTag = (h.match(/<link[^>]*assets\/dist\/style\.css[^>]*>/) || [''])[0];
	const scriptTag = (h.match(/<script[^>]*assets\/dist\/main\.js[^>]*>/) || [''])[0];
	check('tema CSS yüklendi ve sürümü dosya mtime (ver=rakam)', /assets\/dist\/style\.css\?ver=\d{8,}/.test(styleTag), styleTag.slice(0, 120));
	check('tema JS yüklendi (defer) ve sürümü dosya mtime', /assets\/dist\/main\.js\?ver=\d{8,}/.test(scriptTag) && /defer/.test(scriptTag), scriptTag.slice(0, 120));

	/* ---------- 2) yerel görseller ---------- */
	const logo = pngSize(path.join(themeDir, 'assets/images/logos/header-logo.png'));
	const headerImg = imgTag(h, 'header-logo\\.png')[0] || '';
	const footerImg = imgTag(h, 'header-logo\\.png')[1] || '';
	const ratio = (t) => Number(attr(t, 'width')) / Number(attr(t, 'height'));
	check('header logosu: width/height gerçek dosya oranında, decoding=async, fetchpriority=high, lazy YOK', headerImg && Math.abs(ratio(headerImg) - logo.w / logo.h) < 0.02 && attr(headerImg, 'decoding') === 'async' && attr(headerImg, 'fetchpriority') === 'high' && !/loading="lazy"/.test(headerImg), headerImg.slice(0, 200));
	check('footer logosu (fold altı): width/height oranı doğru, decoding=async, loading=lazy', footerImg && Math.abs(ratio(footerImg) - logo.w / logo.h) < 0.02 && attr(footerImg, 'decoding') === 'async' && attr(footerImg, 'loading') === 'lazy', footerImg.slice(0, 200));
	for (const [file, key] of [['myk_logo.png', 'MYK'], ['turkak_logo.png', 'TÜRKAK']]) {
		const s = pngSize(path.join(themeDir, 'assets/images/logos', file));
		const tag = imgTag(h, file.replace('.', '\\.'))[0] || '';
		check(key + ' güven logosu (header, ekran üstü): gerçek oranlı width/height, decoding=async, lazy YOK, fetchpriority YOK', tag && Math.abs(ratio(tag) - s.w / s.h) < 0.02 && attr(tag, 'decoding') === 'async' && !/loading="lazy"/.test(tag) && !/fetchpriority/.test(tag), tag.slice(0, 200));
	}

	/* ---------- 3) güvenli başlıklar ---------- */
	const hd = home.headers;
	check('güvenli başlıklar: nosniff, Referrer-Policy, X-Frame-Options, Permissions-Policy', hd['x-content-type-options'] === 'nosniff' && hd['referrer-policy'] === 'strict-origin-when-cross-origin' && hd['x-frame-options'] === 'SAMEORIGIN' && hd['permissions-policy'] === 'camera=(), microphone=(), geolocation=()', JSON.stringify(hd).slice(0, 200));
	check('HSTS ve CSP PHP tarafından GÖNDERİLMEZ (HTTPS/CDN doğrulanmadı)', !hd['strict-transport-security'] && !hd['content-security-policy'] && !hd['content-security-policy-report-only']);
	check('X-Pingback, X-Powered-By ve REST Link başlığı YOK', !hd['x-pingback'] && !hd['x-powered-by'] && !/wp-json/.test(hd['link'] || ''), (hd['link'] || '') + (hd['x-powered-by'] || ''));
	const notFound = await req('/?p=99999999');
	check('404 sayfasında da güvenli başlıklar', notFound.status === 404 && notFound.headers['x-content-type-options'] === 'nosniff');

	/* ---------- 4) anonim REST ---------- */
	for (const p of ['/?rest_route=/', '/?rest_route=/wp/v2/users', '/?rest_route=/wp/v2/users/1', '/?rest_route=/wp/v2/posts', '/?rest_route=/oembed/1.0/embed&url=http://127.0.0.1:18673/', '/index.php?rest_route=/wp/v2/pages']) {
		const r = await req(p);
		check('anonim REST kapalı (401): ' + p, r.status === 401 && /rest_forbidden/.test(r.body), String(r.status));
	}

	/* ---------- 5) kullanıcı/yazar numaralandırma ---------- */
	let r = await req('/?author=1');
	check('?author=1 -> 404 (yazar adına YÖNLENDİRME yok)', r.status === 404 && !r.headers['location'], r.status + ' ' + (r.headers['location'] || ''));
	r = await req('/?author=2');
	check('?author=2 -> 404', r.status === 404);
	r = await req('/?author_name=mbadmin');
	check('?author_name=mbadmin -> 404', r.status === 404);
	r = await req('/?author=99999');
	check('var olmayan yazar kimliği de 404 (var/yok ayrımı yok)', r.status === 404);
	r = await req('/?sitemap=users');
	check('kullanıcı sitemap yok (sızıntı yok)', !r.body.includes('<urlset') && !r.body.includes('mbadmin'));

	/* ---------- 6) XML-RPC ---------- */
	const xml = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>';
	r = await req('/xmlrpc.php', { method: 'POST', headers: { 'content-type': 'text/xml' }, body: xml });
	check('xmlrpc.php POST yanıt vermez: 403, methodResponse YOK', r.status === 403 && !/methodResponse|system\.listMethods/.test(r.body), r.status + ' ' + r.body.slice(0, 60));
	r = await req('/xmlrpc.php');
	check('xmlrpc.php GET: RSD/pingback ayrıntısı YOK (403)', r.status === 403 && !/XML-RPC server accepts POST/.test(r.body));

	/* ---------- 7) giriş: genel hata + oran sınırı ---------- */
	r = await req('/wp-login.php');
	check('wp-login.php GET çalışır (200, form) ve güvenli başlıklar var', r.status === 200 && /id="loginform"/.test(r.body) && r.headers['x-frame-options'] === 'SAMEORIGIN', String(r.status));
	const login = async (name, pw) => {
		const res = await req('/wp-login.php', { method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded', cookie: 'wordpress_test_cookie=WP%20Cookie%20check' }, body: 'log=' + encodeURIComponent(name) + '&pwd=' + encodeURIComponent(pw) + '&wp-submit=Log+In&testcookie=1' });
		const m = res.body.match(/<div id="login_error"[^>]*>([\s\S]*?)<\/div>/);
		return { status: res.status, msg: m ? m[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim() : '' };
	};
	const a = await login('yok-boyle-bir-hesap-faz10', 'yanlis1');
	const b = await login('mbadmin', 'yanlis2');
	check('yanlış giriş iki kez AYNI genel mesaj (var olmayan / var olan hesap)', a.msg !== '' && a.msg === b.msg && /Kullanıcı adı veya parola hatalı/.test(a.msg), a.msg + ' | ' + b.msg);
	check('giriş hatası hesap/parola ayrımı ve parola sıfırlama bağlantısı sızdırmaz', !/bilinmeyen|geçersiz kullanıcı|Unknown|password you entered|Şifrenizi mi unuttunuz|Lost your password|mbadmin/i.test(a.msg + b.msg), a.msg);
	let last = b;
	for (let i = 0; i < 9; i++) {
		last = await login('yok-boyle-bir-hesap-faz10', 'yanlis-' + i);
	}
	check('10 başarısız denemeden sonra oran sınırı devrede (sınır mesajı)', /Çok fazla başarısız deneme/.test(last.msg), last.msg);

	/* ---------- 8) yönetim etkilenmez ---------- */
	const admin = jar('mbadmin');
	const ah = { headers: { cookie: admin } };
	r = await req('/wp-admin/index.php', ah);
	check('yönetim paneli 200, jQuery YÜKLÜ (yönetim varsayılanları korunur)', r.status === 200 && /id="adminmenu"/.test(r.body) && /jquery(\.min)?\.js|jquery-core/.test(r.body), String(r.status));
	r = await req('/wp-admin/tools.php?page=mavibelge-core-health', ah);
	check('sağlık ekranı: admin 200, başlık, >=20 kontrol satırı, PHP hatası/uyarısı YOK', r.status === 200 && /Mavi Belge Sağlık/.test(r.body) && (r.body.match(/data-check=/g) || []).length >= 20 && !/<b>(Warning|Notice|Fatal error|Deprecated)<\/b>|Fatal error:|Warning:/.test(r.body), String(r.status));
	check('sağlık ekranı: PHP 7.3 uyarı satırı açıkça yazar (EOL, güvenli platform değil)', /data-check="php_version" data-status="warn"/.test(r.body) && /güvenli bir platform DEĞİLDİR/.test(r.body));
	check('sağlık ekranı: form/düğme YOK (salt okunur) ve gizli bilgi kalıbı YOK', !/<form|<input|<button/i.test((r.body.match(/<div class="wrap"><h1>Mavi Belge Sağlık[\s\S]*?<\/table><\/div>/) || [''])[0]) && !/AUTH_KEY|DB_PASSWORD/.test(r.body));
	const nonceRes = await req('/wp-admin/admin-ajax.php?action=rest-nonce', ah);
	const nonce = nonceRes.body.trim();
	const restAdmin = { headers: { cookie: admin, 'x-wp-nonce': nonce } };
	r = await req('/?rest_route=/wp/v2/posts&per_page=1', restAdmin);
	check('oturumlu yönetici + nonce: REST çalışır (Gutenberg/yönetim etkilenmez, 200)', nonce.length > 5 && r.status === 200, r.status + ' nonce=' + nonce.length);
	r = await req('/?rest_route=/wp/v2/users&per_page=1', restAdmin);
	check('list_users yetkili yönetici: kullanıcı uç noktası 200', r.status === 200, String(r.status));
	const editor = jar('mbeditor');
	const eNonce = (await req('/wp-admin/admin-ajax.php?action=rest-nonce', { headers: { cookie: editor } })).body.trim();
	r = await req('/?rest_route=/wp/v2/users', { headers: { cookie: editor, 'x-wp-nonce': eNonce } });
	check('list_users YOK (editör): kullanıcı uç noktası KALDIRILDI -> 404', r.status === 404, String(r.status));
	r = await req('/?rest_route=/wp/v2/users/1', { headers: { cookie: editor, 'x-wp-nonce': eNonce } });
	check('editör: tek kullanıcı uç noktası da 404', r.status === 404, String(r.status));
	r = await req('/wp-admin/tools.php?page=mavibelge-core-health', { headers: { cookie: jar('mbsubscriber') } });
	check('yetkisiz kullanıcı (abone) sağlık ekranını GÖREMEZ (403/yönlendirme)', r.status === 403 || r.status === 302, String(r.status));
	r = await req('/wp-admin/tools.php?page=mavibelge-core-health', { headers: { cookie: editor } });
	check('editör (manage_options yok) sağlık ekranını GÖREMEZ', r.status === 403 || r.status === 302, String(r.status));

	console.log('\nFaz 10 HTTP testi: ' + pass + ' geçti, ' + fail + ' başarısız.');
	process.exit(fail > 0 ? 1 : 0);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
