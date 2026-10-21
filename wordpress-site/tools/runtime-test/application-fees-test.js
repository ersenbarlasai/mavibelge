'use strict';
/**
 * Faz 16 — online başvuru meslek seçenekleri ziyaretçiye açık aktif ÜCRET kayıtlarından. GERÇEK WordPress 6.9.9 + PHP 7.3
 * (YALNIZ mbfx_ fixture; gerçek 14/83/103 katalog, 103 ücret yayında + aktif — canlıdaki kullanıcı yayını gibi) + gerçek HTTP +
 * GERÇEK headless Chrome. Form kapısı yalnız bu klonda SENTETİK ayarla açık; e-posta gönderilmez (pre_wp_mail kısa devre; içerik
 * yalnız /tmp/mbfx-mail-capture işaretiyle geçici dosyaya yazılır, veriler SENTETİKTİR).
 *
 *   node application-fees-test.js <setup.json> <ekran-dizini>
 */
const fs = require('fs');
const path = require('path');
const cp = require('child_process');
const { launch } = require('./lib/cdp.js');

const [, , SETUP, SHOTS] = process.argv;
const fx = JSON.parse(fs.readFileSync(SETUP, 'utf8').replace(/^[^{]*/, ''));
fs.mkdirSync(SHOTS, { recursive: true });
const BASE = 'http://127.0.0.1:18673';
const WP = 'mbruntime6b2-wp-1';
const DATA = path.join(__dirname, '..', '..', 'data', 'content');
const APP = fx.application;
const SEL = 'mbf-application-qualification';
const GONE = 'Seçtiğiniz meslek/tarife artık online başvuruya açık değil. Lütfen listeden yeniden seçin.';
let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail !== undefined ? '  [' + String(detail).slice(0, 500) + ']' : ''));
	}
}
const readJson = (p) => JSON.parse(fs.readFileSync(p, 'utf8').replace(/^[^{]*/, ''));
const dec = (s) => String(s).replace(/&#0?39;/g, "'").replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&#8212;/g, '—').replace(/&#038;/g, '&').replace(/&amp;/g, '&');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
function docker(args, input) {
	return cp.execFileSync('docker', args, { encoding: 'utf8', input });
}
function fixture(...args) {
	const out = docker(['exec', '-u', 'www-data', WP, 'wp', '--path=/var/www/html', '--require=/opt/mb-runtime/fixture-env.php', 'eval-file', '/opt/mb-runtime/application-fee-fixtures.php', ...args.map(String)]);
	return JSON.parse(out.replace(/^[^{]*/, ''));
}
const sh = (cmd) => docker(['exec', WP, 'sh', '-c', cmd]);
const mailCount = () => Number(sh('wc -l < /tmp/mbfx-mail.count').trim());
const lastMail = () => {
	try {
		return sh('cat /tmp/mbfx-mail.last');
	} catch (e) {
		return '';
	}
};
async function get(url) {
	const r = await fetch(url.indexOf('http') === 0 ? url : BASE + url, { redirect: 'manual' });
	return { status: r.status, body: await r.text(), location: r.headers.get('location') || '' };
}
async function post(url, data) {
	const body = new URLSearchParams();
	for (const [k, v] of Object.entries(data)) {
		if (Array.isArray(v)) v.forEach((x) => body.append(k + '[]', x));
		else body.append(k, v);
	}
	const r = await fetch(url, { method: 'POST', body, redirect: 'manual', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
	return { status: r.status, body: await r.text(), location: r.headers.get('location') || '' };
}
function selectBlock(body) {
	return (body.match(new RegExp('<select id="' + SEL + '"[^>]*>[\\s\\S]*?<\\/select>')) || [''])[0];
}
function options(body) {
	return (selectBlock(body).match(/<option value="([^"]+)"[^>]*>([^<]*)<\/option>/g) || []).map((o) => {
		const x = o.match(/value="([^"]+)"[^>]*>([^<]*)</);
		return { value: x[1], label: dec(x[2]), selected: /selected/.test(o) };
	});
}
const selectedOf = (body) => options(body).filter((o) => o.selected);
const feeTotal = (body) => {
	const m = dec(body).match(/Toplam (\d+) ücret kaydından/);
	return m ? Number(m[1]) : (/Aramanızla eşleşen bir ücret kaydı bulunamadı|ücret kaydı bulunmuyor/.test(dec(body)) ? 0 : -1);
};
const tok = (h) => ({ mb_form: 'application', mb_token: (h.match(/name="mb_token" value="([^"]+)"/) || [])[1] || '', _mb_nonce: (h.match(/name="_mb_nonce" value="([^"]+)"/) || [])[1] || '', mb_hp_url: '' });
const PERSON = { exam_location: 'iskenderun', full_name: 'TEST Sentetik Aday', national_id: '10000000146', phone: '0000 000 00 00', email: 'aday@ornek.example', consent: '1' };

/* Türkçe sıralama (PHP Forms_Fee_Options ile BAĞIMSIZ yeniden yazım). */
const ALPHA = 'abcçdefgğhıijklmnoöpqrsştuüvwxyz';
const W = {};
[...ALPHA].forEach((c, i) => { W[c] = 200 + i; });
W['â'] = W.a; W['î'] = W.i; W['û'] = W.u;
const UP = { I: 'ı', 'İ': 'i', 'Ç': 'ç', 'Ğ': 'ğ', 'Ö': 'ö', 'Ş': 'ş', 'Ü': 'ü', 'Â': 'â', 'Î': 'î', 'Û': 'û' };
function skey(t) {
	return [...t].map((ch) => {
		let c = UP[ch] || (ch.length === 1 && ch.charCodeAt(0) < 128 ? ch.toLowerCase() : ch);
		const w = W[c] !== undefined ? W[c] : /[0-9]/.test(c) ? 100 + Number(c) : c === ' ' ? 10 : 50;
		return String(w).padStart(3, '0');
	}).join('');
}
const label = (r) => r.profession_name + (/^[1-8]$/.test(String(r.level)) ? ' — Seviye ' + r.level : '') + (r.qualification_code ? ' — ' + r.qualification_code : '');

(async () => {
	const fees = readJson(path.join(DATA, 'fees.manifest.json')).records;
	const idOf = fx.fees;
	const MV1 = String(idOf['fee:is-makineleri:3:mobil-vinc-operatoru-mhc-sahil-ve-gemi-vinci']);
	const MV2 = String(idOf['fee:is-makineleri:3:mobil-vinc-operatoru-is-makineleri-grubu']);
	const BEKO = String(idOf['fee:is-makineleri:3:kazici-yukleyici-beko-loder-operatoru']);
	const DOK3 = String(idOf['fee:tekstil:3:dokuma-operatoru']);
	const DOK4 = String(idOf['fee:tekstil:4:dokuma-operatoru']);
	const MAK3 = String(idOf['fee:makine:3:makine-bakimci']);
	const byId = {};
	fees.forEach((r) => { byId[String(idOf[r.source_key])] = r; });

	/* ---- 1) liste: 103 ücret -> 103 seçenek, ücret kaydı başına bir seçenek ---- */
	check('fixture: 103 ücret yayında + aktif (ziyaretçiye görünen 103), form SENTETİK ayarla açık, /index.php/ yapısı', fx.visible === 103 && fx.open === true && /\/index\.php\/online-basvuru\/$/.test(APP) && Object.keys(idOf).length === 103, JSON.stringify({ v: fx.visible, o: fx.open, app: APP }));
	let r = await get(APP);
	let opts = options(r.body);
	const priceOptions = fees.reduce((a, f) => a + f.price_options.length, 0);
	check('liste: 200, gerçek form + jeton; 103 seçenek (145 fiyat seçeneği 145 meslek seçeneğine DÖNÜŞMEZ)', r.status === 200 && /<form method="post"/.test(r.body) && tok(r.body).mb_token.length > 20 && opts.length === 103 && priceOptions === 145, opts.length + ' / ' + priceOptions);
	check('liste: her seçenek değeri gerçek bir ücret kaydı kimliği; kimlikler benzersiz ve 103 ücretle birebir', new Set(opts.map((o) => o.value)).size === 103 && opts.every((o) => /^[1-9][0-9]*$/.test(o.value) && byId[o.value]), opts.filter((o) => !byId[o.value]).slice(0, 3));
	check('liste: etiket ücret kaydının onaylı adından "Ad — Seviye N — Kod" (kod yoksa uydurulmaz)', opts.every((o) => o.label === label(byId[o.value])), opts.filter((o) => o.label !== label(byId[o.value])).slice(0, 3).map((o) => o.label + ' != ' + label(byId[o.value])));
	const unlinked = opts.filter((o) => byId[o.value].qualification_code === '');
	check('liste: 19 bağlantısız/kodsuz ücret seçilebilir, etiketlerinde sahte kod yok', unlinked.length === 19 && unlinked.every((o) => !/UY[0-9]/.test(o.label)), unlinked.length);
	check('liste: Dokuma Operatörü seviyeleri ayrı ("— Seviye 3" ve "— Seviye 4", farklı kimlik)', opts.some((o) => o.value === DOK3 && o.label === 'Dokuma Operatörü — Seviye 3') && opts.some((o) => o.value === DOK4 && o.label === 'Dokuma Operatörü — Seviye 4') && DOK3 !== DOK4);
	check('liste: iki Mobil Vinç tarifesi ayrı kimlik ve adla (birleştirilmez)', opts.some((o) => o.value === MV1 && o.label === 'Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci) — Seviye 3 — 12UY0061-3/04') && opts.some((o) => o.value === MV2 && o.label === 'Mobil Vinç Operatörü (İş Makineleri Grubu) — Seviye 3 — 12UY0061-3/04') && MV1 !== MV2);
	check('liste: Kazıcı Yükleyici (Beko Loder) kodsuz ve seçilebilir; Makine Bakımcı Seviye 3 kodlu', opts.some((o) => o.value === BEKO && o.label === 'Kazıcı Yükleyici (Beko Loder) Operatörü — Seviye 3') && opts.some((o) => o.value === MAK3 && o.label === 'Makine Bakımcı — Seviye 3 — 10UY0002-3/03'));
	const expOrder = opts.slice().sort((a, b) => {
		const x = skey(byId[a.value].profession_name), y = skey(byId[b.value].profession_name);
		if (x !== y) return x < y ? -1 : 1;
		const l = Number(byId[a.value].level) - Number(byId[b.value].level);
		if (l) return l;
		const c = byId[a.value].qualification_code.localeCompare(byId[b.value].qualification_code, 'en');
		return c || Number(a.value) - Number(b.value);
	}).map((o) => o.value);
	check('liste: Türkçe sıralama (bağımsız yeniden hesaplama ile aynı; ç/ğ/ı/ö/ş/ü ve I/İ kuralları)', JSON.stringify(opts.map((o) => o.value)) === JSON.stringify(expOrder), opts.slice(0, 5).map((o) => o.label).join(' | '));
	check('liste: aynı etiket iki kez yok (ayrışmayan yinelenen başlık yok)', new Set(opts.map((o) => o.label)).size === opts.length);
	check('liste: hiçbir seçenek önceden seçili değil (parametresiz)', selectedOf(r.body).length === 0);
	let fp = await get(fx.fee_page);
	check('aynı kural: Sınav Ücretleri "Toplam 103 ücret kaydından"', feeTotal(fp.body) === 103, feeTotal(fp.body));

	/* ---- 2) ön seçim ---- */
	r = await get(APP + '?ucret=' + MV1);
	check('?ucret=<Mobil Vinç MHC> -> yalnız o tarife seçili, belirsizlik bildirimi yok', JSON.stringify(selectedOf(r.body).map((o) => o.value)) === JSON.stringify([MV1]) && !/application-ambiguous/.test(r.body));
	r = await get(APP + '?meslek=12UY0061-3%2F04');
	const amb = (r.body.match(/<div class="alert alert-info application-ambiguous" role="status">[\s\S]*?<\/div>/) || [''])[0];
	check('?meslek=12UY0061-3/04 (iki tarife) -> HİÇBİRİ seçilmez; bildirimde iki tarifenin adı; ziyaretçiden seçim istenir', selectedOf(r.body).length === 0 && dec(amb).indexOf('Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci) — Seviye 3 — 12UY0061-3/04') !== -1 && dec(amb).indexOf('Mobil Vinç Operatörü (İş Makineleri Grubu) — Seviye 3 — 12UY0061-3/04') !== -1 && /birden fazla tarife/.test(dec(amb)), amb.slice(0, 300));
	r = await get(APP + '?meslek=10UY0002-3%2F03');
	check('eski ?meslek=10UY0002-3/03 (tek ücrete çözülür) -> Makine Bakımcı Seviye 3 seçili', JSON.stringify(selectedOf(r.body).map((o) => o.value)) === JSON.stringify([MAK3]));
	r = await get(APP + '?meslek=10UY0002-3/03');
	check('eski ?meslek= kodlanmamış eğik çizgiyle de çalışır', JSON.stringify(selectedOf(r.body).map((o) => o.value)) === JSON.stringify([MAK3]));
	r = await get(APP + '?ucret=' + MV2 + '&meslek=12UY0061-3%2F04');
	check('?ucret ?meslek\'ten öncelikli: belirsiz koda rağmen tekil tarife seçilir', JSON.stringify(selectedOf(r.body).map((o) => o.value)) === JSON.stringify([MV2]) && !/application-ambiguous/.test(r.body));
	const badPre = { sıfır: '0', 'baştaki sıfır': '0' + MV1, negatif: '-' + MV1, bilinmeyen: '999999999', 'yeterlilik kimliği': String(fx.qual_any), 'sayfa kimliği': String(fx.page_id), HTML: '%3Cscript%3Ezzqqxss()%3C%2Fscript%3E', 'aşırı uzun': '9'.repeat(5000), ondalık: MV1 + '.0', boşluk: '%20' + MV1 };
	for (const [k, v] of Object.entries(badPre)) {
		r = await get(APP + '?ucret=' + v);
		check('geçersiz ?ucret (' + k + ') -> seçim yok, ham değer basılmaz', r.status === 200 && selectedOf(r.body).length === 0 && r.body.indexOf('zzqqxss') === -1 && r.body.indexOf('9'.repeat(200)) === -1);
	}
	r = await get(APP + '?ucret%5B%5D=' + MV1);
	check('?ucret[]= dizi -> seçim yok', selectedOf(r.body).length === 0);

	/* ---- 3) dışlama + önbellek (gerçek ziyaretçi yolu önbellekli) ---- */
	sh('touch /tmp/mbfx-cache-on; chmod 644 /tmp/mbfx-cache-on');
	const X = DOK3;
	const orig = fixture('get', X).state;
	const today = fx.today;
	const d = fixture('dates', 'UTC');
	const yesterdayOf = (s) => { const t = new Date(s + 'T12:00:00Z'); t.setUTCDate(t.getUTCDate() - 1); return t.toISOString().slice(0, 10); };
	const tomorrowOf = (s) => { const t = new Date(s + 'T12:00:00Z'); t.setUTCDate(t.getUTCDate() + 1); return t.toISOString().slice(0, 10); };
	await get(APP); // önbelleği doldur
	const cases = [
		['taslak (post_status draft)', ['set', X, 'post_status', 'draft'], ['set', X, 'post_status', 'publish']],
		['Kayıt Durumu arşivlendi (pasif)', ['set', X, '_mb_record_status', 'archived'], ['set', X, '_mb_record_status', 'active']],
		['Kayıt Durumu taslak', ['set', X, '_mb_record_status', 'draft'], ['set', X, '_mb_record_status', 'active']],
		['süresi bitmiş (valid_until dün)', ['set', X, '_mb_valid_until', yesterdayOf(today)], ['set', X, '_mb_valid_until', orig.until]],
		['gelecekte başlayacak (valid_from yarın)', ['set', X, '_mb_valid_from', tomorrowOf(today)], ['set', X, '_mb_valid_from', orig.from]],
		['yanlış dönem', ['set', X, '_mb_tariff_period', 'TEST-2099'], ['set', X, '_mb_tariff_period', orig.period]],
		['çöpe atılmış', ['trash', X], ['untrash', X]],
	];
	for (const [name, mutate, restore] of cases) {
		const g0 = fixture('gen').gen;
		const m = fixture(...mutate);
		r = await get(APP);
		fp = await get(fx.fee_page);
		const pre = await get(APP + '?ucret=' + X);
		check('dışlama ' + name + ': önbellek nesli arttı; listede YOK (102 seçenek); Sınav Ücretleri 102; ?ucret ön seçmez', Number(m.gen) > Number(g0) && options(r.body).length === 102 && !options(r.body).some((o) => o.value === X) && feeTotal(fp.body) === 102 && selectedOf(pre.body).length === 0, JSON.stringify({ g0, g1: m.gen, n: options(r.body).length, fee: feeTotal(fp.body) }));
		fixture(...restore);
		r = await get(APP);
		check('geri alma ' + name + ': kayıt listeye döndü (103), önbellekte eski uygunsuzluk kalmadı', options(r.body).length === 103 && options(r.body).some((o) => o.value === X), options(r.body).length);
	}
	check('geri alma sonrası kayıt durumu başlangıçla aynı', JSON.stringify(fixture('get', X).state) === JSON.stringify(orig), JSON.stringify([fixture('get', X).state, orig]));

	/* ---- 4) gün sınırı: admin kaydı OLMADAN tarih değişince önbellek eski uygunluğu taşımaz ---- */
	const pp = fixture('dates', 'Pacific/Pago_Pago');
	fixture('set', X, '_mb_valid_until', pp.today);
	sh("printf 'Pacific/Pago_Pago' > /tmp/mbfx-tz; chmod 644 /tmp/mbfx-tz");
	r = await get(APP);
	const g1 = fixture('gen').gen;
	check('gün sınırı (önce): site saatiyle bugün = son geçerlilik günü -> listede', options(r.body).some((o) => o.value === X));
	sh("printf 'Pacific/Kiritimati' > /tmp/mbfx-tz");
	r = await get(APP);
	const g2 = fixture('gen').gen;
	check('gün sınırı (sonra): tarih ilerledi, HİÇBİR yazma/kayıt yok (nesil aynı) -> kayıt listeden düştü', g1 === g2 && !options(r.body).some((o) => o.value === X) && options(r.body).length === 102, JSON.stringify({ g1, g2, n: options(r.body).length }));
	sh('rm -f /tmp/mbfx-tz');
	fixture('set', X, '_mb_valid_until', orig.until);

	/* ---- 5) aktif tarife dönemi değişikliği ve uygun kayıt yokken form ---- */
	fixture('period', 'TEST-YOK-DONEM');
	r = await get(APP);
	const main = r.body.slice(r.body.indexOf('<main'), r.body.indexOf('</main>'));
	check('dönem değişti, uygun ücret yok: <form>/jeton/nonce YOK; "online başvuruya açık meslek bulunmuyor" + telefon/e-posta alternatifi', !/<form[ >]/.test(main) && !/mb_token|_mb_nonce/.test(main) && /Şu anda online başvuruya açık meslek bulunmuyor/.test(main) && /href="tel:/.test(main) && /href="mailto:/.test(main), main.slice(0, 300));
	fp = await get(fx.fee_page);
	check('aynı kural: Sınav Ücretleri de 0 kayıt', feeTotal(fp.body) <= 0, feeTotal(fp.body));
	fixture('period', fx.period);
	r = await get(APP);
	check('dönem geri alındı: 103 seçenek, form geri geldi', options(r.body).length === 103 && /<form method="post"/.test(r.body));

	/* ---- 6) 200 ve 500 (aday parçası) üzerindeki uygun kayıt: sessiz kesme yok ---- */
	const bulk = fixture('bulk-add', 400);
	r = await get(APP);
	fp = await get(fx.fee_page);
	check('503 uygun ücret (200 ve 500 aday sınırının üstü) -> 503 seçenek; Sınav Ücretleri 503; servis sayısı 503', bulk.visible === 503 && options(r.body).length === 503 && feeTotal(fp.body) === 503, JSON.stringify({ svc: bulk.visible, opts: options(r.body).length, page: feeTotal(fp.body) }));
	const clean = fixture('bulk-clean');
	r = await get(APP);
	check('toplu test kayıtları silindi -> 103', clean.deleted === 400 && options(r.body).length === 103, JSON.stringify(clean));

	/* ---- 7) gönderim: sunucu doğrulaması, uygunluğunu yitiren seçim, sahte kimlikler, e-posta ---- */
	sh('touch /tmp/mbfx-mail-capture; chmod 644 /tmp/mbfx-mail-capture; rm -f /tmp/mbfx-mail.last');
	const lose = [
		['taslağa alındı', ['set', X, 'post_status', 'draft'], ['set', X, 'post_status', 'publish']],
		['pasifleştirildi', ['set', X, '_mb_record_status', 'archived'], ['set', X, '_mb_record_status', 'active']],
		['dönemi değişti', ['set', X, '_mb_tariff_period', 'TEST-2099'], ['set', X, '_mb_tariff_period', orig.period]],
		['süresi bitti', ['set', X, '_mb_valid_until', yesterdayOf(today)], ['set', X, '_mb_valid_until', orig.until]],
		['silindi (çöp)', ['trash', X], ['untrash', X]],
	];
	for (const [name, mutate, restore] of lose) {
		r = await get(APP + '?ucret=' + X);
		const sec = tok(r.body);
		const ok = selectedOf(r.body).map((o) => o.value).join() === X;
		fixture(...mutate);
		await sleep(3200);
		const m0 = mailCount();
		const p = await post(APP, Object.assign({}, sec, PERSON, { qualification: X }));
		const err = (p.body.match(new RegExp('id="' + SEL + '-error"[^>]*>([^<]*)<')) || [])[1] || '';
		check('form açıldıktan sonra ' + name + ' -> POST reddedilir (200 + anlaşılır Türkçe hata), e-posta YOK, seçim boş, kullanıcı yeniden seçebilir', ok && p.status === 200 && dec(err) === GONE && mailCount() === m0 && selectedOf(p.body).length === 0 && options(p.body).length === 102 && /form-error-summary/.test(p.body), JSON.stringify({ st: p.status, err: dec(err), mail: mailCount() - m0 }));
		fixture(...restore);
	}
	// Önbellek BAYATKEN (kancasız durum değişikliği, nesil aynı): liste kaydı hâlâ gösterir, gönderim önbelleksiz yeniden
	// doğrulamayla (get_public_fee) reddedilir.
	r = await get(APP + '?ucret=' + X);
	const staleSec = tok(r.body);
	const gs0 = fixture('gen').gen;
	fixture('raw-status', X, 'draft');
	const stale = await get(APP);
	await sleep(3200);
	const ms0 = mailCount();
	const sp = await post(APP, Object.assign({}, staleSec, PERSON, { qualification: X }));
	const serr = (sp.body.match(new RegExp('id="' + SEL + '-error"[^>]*>([^<]*)<')) || [])[1] || '';
	check('bayat önbellek: liste kaydı hâlâ gösteriyor (nesil değişmedi) ama POST önbelleksiz yeniden doğrulamayla reddedilir, e-posta YOK', fixture('gen').gen === gs0 && options(stale.body).some((o) => o.value === X) && sp.status === 200 && dec(serr) === GONE && mailCount() === ms0, JSON.stringify({ listed: options(stale.body).some((o) => o.value === X), st: sp.status, err: dec(serr), mail: mailCount() - ms0 }));
	fixture('raw-status', X, 'publish');
	fixture('bump');
	r = await get(APP);
	check('bayat önbellek senaryosu sonrası kayıt yayında ve listede', options(r.body).some((o) => o.value === X) && options(r.body).length === 103);
	let sec = tok(r.body);
	await sleep(3200);
	const m1 = mailCount();
	const fakes = { bilinmeyen: '999999999', 'başka içerik türü (yeterlilik)': String(fx.qual_any), 'başka içerik türü (sayfa)': String(fx.page_id), sıfır: '0', negatif: '-5', üs: '1e3', 'baştaki boşluk': ' ' + X, 'sondaki boşluk': X + ' ', 'baştaki sıfır': '0' + X, HTML: '<script>zzqqxss()</script>', 'MYK kodu': '12UY0061-3/04' };
	for (const [k, v] of Object.entries(fakes)) {
		const p = await post(APP, Object.assign({}, sec, PERSON, { qualification: v }));
		const err = (p.body.match(new RegExp('id="' + SEL + '-error"[^>]*>([^<]*)<')) || [])[1] || '';
		check('sahte/bozuk kimlik (' + k + ') -> 200 doğrulama hatası, kabul edilmez, ham değer basılmaz', p.status === 200 && err !== '' && selectedOf(p.body).length === 0 && p.body.indexOf('zzqqxss') === -1, p.status + ' ' + dec(err));
	}
	let pa = await post(APP, Object.assign({}, sec, PERSON, { qualification: [X] }));
	check('dizi girdisi qualification[] -> 200 "Geçersiz değer."', pa.status === 200 && /id="mbf-application-qualification-error"[^>]*>Geçersiz değer\./.test(pa.body));
	check('sahte kimlik denemelerinde hiç e-posta gitmedi', mailCount() === m1, mailCount() - m1);

	// geçerli gönderim: bağlantısız/kodsuz ücret (Beko Loder)
	pa = await post(APP, Object.assign({}, sec, PERSON, { qualification: BEKO }));
	const mail1 = lastMail();
	check('bağlantısız ücretle geçerli gönderim: 303 PRG (Location kişisel veri/kimlik taşımaz), test mailer\'ına tam 1 ileti', pa.status === 303 && /mb_form_status=success/.test(pa.location) && !/ucret|qualification|10000000146|ornek/.test(pa.location) && mailCount() === m1 + 1, pa.status + ' ' + pa.location);
	check('e-posta (bağlantısız): konu sabit; meslek etiketi + sunucu kaydından ayrıntılar; "MYK kodu: Belirtilmemiş", "Bağlı yeterlilik: Bağlantısız"',
		mail1.indexOf('Subject: [Mavi Belge] Online Başvuru formu\n') === 0 && mail1.indexOf('Başvurulacak meslek/yeterlilik: Kazıcı Yükleyici (Beko Loder) Operatörü — Seviye 3\n') !== -1
		&& mail1.indexOf('  Ücret kaydı: #' + BEKO + '\n') !== -1 && mail1.indexOf('  Meslek: Kazıcı Yükleyici (Beko Loder) Operatörü\n') !== -1 && mail1.indexOf('  Seviye: 3\n') !== -1
		&& mail1.indexOf('  MYK kodu: Belirtilmemiş\n') !== -1 && mail1.indexOf('  Bağlı yeterlilik: Bağlantısız\n') !== -1 && mail1.indexOf('KVKK onayı: verildi (sürüm test-16)') !== -1, mail1.slice(0, 600));
	pa = await post(APP, Object.assign({}, sec, PERSON, { qualification: MV2 }));
	check('aynı jetonla ikinci gönderim (replay) -> 400, e-posta YOK', pa.status === 400 && mailCount() === m1 + 1, pa.status);
	r = await get(APP + '?meslek=12UY0061-3%2F04');
	sec = tok(r.body);
	await sleep(3200);
	pa = await post(APP, Object.assign({}, sec, PERSON, { qualification: MV2 }));
	const mail2 = lastMail();
	check('bağlı ücretle (Mobil Vinç İş Makineleri) geçerli gönderim: 303 + e-postada tarife adı, kod ve gerçek yeterlilik ilişkisi',
		pa.status === 303 && mailCount() === m1 + 2 && mail2.indexOf('Başvurulacak meslek/yeterlilik: Mobil Vinç Operatörü (İş Makineleri Grubu) — Seviye 3 — 12UY0061-3/04\n') !== -1
		&& mail2.indexOf('  MYK kodu: 12UY0061-3/04\n') !== -1 && mail2.indexOf('  Bağlı yeterlilik: ' + fx.mobil_qual.title + ' (' + fx.mobil_qual.code + ') — kayıt #' + fx.mobil_qual.id + '\n') !== -1 && mail2.indexOf('MHC') === -1, mail2.slice(0, 600));
	sh('rm -f /tmp/mbfx-mail-capture /tmp/mbfx-mail.last');

	/* ---- 8) yeterlilik detayı: aynı yeterliliğe iki tarife -> tekil ?ucret= bağlantıları ---- */
	r = await get(fx.mobil_qual.url);
	const cta = (r.body.match(/<aside class="sticky-cta"[\s\S]*?<\/aside>/) || [''])[0];
	check('yeterlilik detayı (12UY0061-3/04): her tarifede "Bu tarifeyle başvur" -> ?ucret=<kimlik>; genel ?meslek= bağlantısı korunur', cta.indexOf('href="' + APP + '?ucret=' + MV1 + '"') !== -1 && cta.indexOf('href="' + APP + '?ucret=' + MV2 + '"') !== -1 && cta.indexOf('href="' + APP + '?meslek=12UY0061-3%2F04">Bu Yeterlilik İçin Başvur') !== -1, cta.slice(0, 400));

	/* ---- 9) gerçek Chrome: seçim, ileri/geri, hata sonrası seçim, mobil ---- */
	const b = await launch();
	try {
		await b.viewport(1280, 900);
		await b.goto(APP + '?ucret=' + MV1);
		let s = await b.eval(`(() => { const q=document.getElementById('${SEL}'); return { val: q.value, text: q.options[q.selectedIndex].text, n: q.options.length, vis: [...document.querySelectorAll('[data-step]')].map(x=>!x.hidden) }; })()`);
		check('Chrome: ?ucret ile tarife seçili (değer = ücret kimliği, metin = tarife etiketi); 103 + "Seçiniz"; yalnız 1. adım görünür', s.val === MV1 && s.text === 'Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci) — Seviye 3 — 12UY0061-3/04' && s.n === 104 && JSON.stringify(s.vis) === '[true,false,false,false]', JSON.stringify(s));
		await b.eval(`document.querySelector('[data-step="0"] [data-step-next]').click()`);
		s = await b.eval(`[...document.querySelectorAll('[data-step]')].map(x=>!x.hidden)`);
		check('Chrome: sınav alanı boşken Devam Et ilerlemez', JSON.stringify(s) === '[true,false,false,false]', JSON.stringify(s));
		await b.eval(`(() => { const q=document.getElementById('${SEL}'); q.value='${MV2}'; q.dispatchEvent(new Event('change',{bubbles:true})); const e=document.getElementById('mbf-application-exam_location'); e.value='payas'; e.dispatchEvent(new Event('change',{bubbles:true})); return true; })()`);
		await b.eval(`document.querySelector('[data-step="0"] [data-step-next]').click()`);
		s = await b.eval(`[...document.querySelectorAll('[data-step]')].map(x=>!x.hidden)`);
		check('Chrome: kullanıcı diğer Mobil Vinç tarifesini seçti; Devam Et -> 2. adım', JSON.stringify(s) === '[false,true,false,false]', JSON.stringify(s));
		await b.eval(`document.querySelector('[data-step="1"] [data-step-prev]').click()`);
		s = await b.eval(`(() => ({ vis: [...document.querySelectorAll('[data-step]')].map(x=>!x.hidden), val: document.getElementById('${SEL}').value }))()`);
		check('Chrome: Geri -> 1. adım, seçim korunur', JSON.stringify(s.vis) === '[true,false,false,false]' && s.val === MV2, JSON.stringify(s));
		await sleep(3200);
		await b.eval(`(() => { const f=document.querySelector('form[data-step-form]'); f.querySelector('#mbf-application-full_name').value=''; f.querySelector('#mbf-application-national_id').value='10000000146'; f.querySelector('#mbf-application-phone').value='0000 000 00 00'; f.querySelector('#mbf-application-email').value='aday@ornek.example'; f.querySelector('#mbf-application-consent').checked=true; HTMLFormElement.prototype.submit.call(f); return true; })()`);
		await sleep(2500);
		s = await b.eval(`(() => ({ vis: [...document.querySelectorAll('[data-step]')].map(x=>!x.hidden), val: document.getElementById('${SEL}').value, inv: (document.querySelector('[aria-invalid="true"]')||{}).id, nid: document.getElementById('mbf-application-national_id').value }))()`);
		check('Chrome: sunucu doğrulama hatası sonrası hatalı adım (2) açık, tarife seçimi korunur (POST yankısı), T.C. kimlik geri doldurulmaz', JSON.stringify(s.vis) === '[false,true,false,false]' && s.val === MV2 && s.inv === 'mbf-application-full_name' && s.nid === '', JSON.stringify(s));
		await b.screenshot(path.join(SHOTS, 'hata-sonrasi-1280.png'));
		for (const w of [375, 390, 768]) {
			for (const [tag, q] of [['ucret', '?ucret=' + MV1], ['belirsiz', '?meslek=12UY0061-3%2F04']]) {
				await b.viewport(w, 900);
				await b.goto(APP + q);
				const m = await b.eval(`(() => { const vw=document.documentElement.clientWidth; const q=document.getElementById('${SEL}'); const r=q.getBoundingClientRect(); const n=document.querySelector('.application-ambiguous'); const nr=n?n.getBoundingClientRect():null; return { vw, sw: document.documentElement.scrollWidth, selL: r.left, selR: r.right, noteR: nr?nr.right:0, noteOver: n? n.scrollWidth>n.clientWidth+1 : false, note: !!n, val: q.value }; })()`);
				check('Chrome ' + w + 'px ' + tag + ': yatay taşma yok; uzun tarife etiketiyle seçim kutusu ve bildirim görüntü alanında', m.sw <= m.vw && m.selL >= 0 && m.selR <= m.vw + 0.5 && m.noteR <= m.vw + 0.5 && !m.noteOver && (tag === 'belirsiz' ? m.note && m.val === '' : !m.note && m.val === MV1), JSON.stringify(m));
				await b.screenshot(path.join(SHOTS, 'basvuru-' + tag + '-' + w + '.png'));
			}
		}
		check('Chrome: konsolda hata yok', b.consoleErrors.filter((e) => e.indexOf('/favicon.ico') === -1).length === 0, b.consoleErrors.join(' | '));
	} finally {
		await b.close();
	}
	sh('rm -f /tmp/mbfx-cache-on');
	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 16 başvuru meslek seçenekleri testi ' + (fail ? 'BAŞARISIZ' : 'geçti') + '.');
	process.exitCode = fail ? 1 : 0;
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + (e && e.stack ? e.stack : e));
	process.exitCode = 1;
});
