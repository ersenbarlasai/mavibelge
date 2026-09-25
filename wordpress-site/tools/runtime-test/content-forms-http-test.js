'use strict';
/**
 * Faz 7/8 HTTP render + form testleri (gerçek HTTP istemcisi; tarayıcı DEĞİL). YALNIZ mbfx_ fixture veritabanına
 * yönlendirilmiş yerel test ortamında çalışır (bkz. content-http.sh, scripts/mu-fixture-http.php). Sahte içerik
 * (TEST ...), gerçek e-posta GÖNDERİLMEZ (pre_wp_mail kısa devre + sayaç).
 *   node content-forms-http-test.js <fixtures.json>
 */
const fs = require('fs');
const cp = require('child_process');

const BASE = 'http://127.0.0.1:18673';
const ids = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^[^{]*/, ''));
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
async function get(path, opts) {
	const res = await fetch(BASE + path, Object.assign({ redirect: 'manual' }, opts || {}));
	return { status: res.status, body: await res.text(), location: res.headers.get('location') || '' };
}
async function post(path, fields) {
	const body = new URLSearchParams();
	Object.keys(fields).forEach((k) => body.append(k, fields[k]));
	const res = await fetch(BASE + path, { method: 'POST', body, redirect: 'manual', headers: { 'content-type': 'application/x-www-form-urlencoded' } });
	const out = { status: res.status, body: await res.text(), location: res.headers.get('location') || '' };
	if (process.env.MBFX_DEBUG) console.log('  POST ' + path + ' -> ' + out.status + ' ' + out.location + ' | ' + (out.body.match(/(Çok fazla deneme|Form süresi|Oturum süresi|Gönderim şu anda)[^<]*/) || [''])[0]);
	return out;
}
const count = (s, re) => (s.match(re) || []).length;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
function mailCount() {
	try {
		return cp.execSync('docker exec mbruntime6b2-wp-1 sh -c "wc -l < /tmp/mbfx-mail.count 2>/dev/null || echo 0"').toString().trim() * 1;
	} catch (e) {
		return -1;
	}
}
function hidden(html, name) {
	const m = html.match(new RegExp('name="' + name + '" value="([^"]*)"'));
	return m ? m[1] : '';
}

(async () => {
	/* ---------- haber arşivi ---------- */
	let r = await get('/?post_type=mb_haber');
	check('haberler arşivi 200, tek H1', r.status === 200 && count(r.body, /<h1[ >]/g) === 1, r.status + ' h1=' + count(r.body, /<h1[ >]/g));
	check('haberler: yalnız ONAYLI haberler listelenir (onaysız-yayında ve taslak YOK)', r.body.includes('TEST Haber 13') && !r.body.includes('TEST Onaysız Yayında Haber') && !r.body.includes('TEST Taslak Haber'));
	check('haberler: sayfa boyutu 12 (13 onaylı haberden 12 kart)', count(r.body, /class="card-shell"/g) === 12, 'kart=' + count(r.body, /class="card-shell"/g));
	check('haberler: sayfalama nav var, ikinci sayfa bağlantısı gerçek URL (# değil), aria-current sayfa 1', r.body.includes('<nav class="pagination"') && /href="[^"]*mb_page=2/.test(r.body) && r.body.includes('aria-current="page"'));
	check('haberler: tür filtre şeridi gerçek bağlantılar (Tümü/Haberler/Duyurular), geçerli olan aria-current', r.body.includes('mb_type=duyuru') && r.body.includes('mb_type=haber') && r.body.includes('filter-links'));
	check('haberler: breadcrumb var (Bilgi Merkezi > Haberler)', r.body.includes('class="breadcrumb"'));
	r = await get('/?post_type=mb_haber&mb_page=2');
	check('haberler sayfa 2: kalan 1 kart (TEST Haber 01), 13 haberin toplamı korunur', r.status === 200 && count(r.body, /class="card-shell"/g) === 1 && r.body.includes('TEST Haber 01'), 'kart=' + count(r.body, /class="card-shell"/g));
	r = await get('/?post_type=mb_haber&mb_page=999');
	check('haberler: sayfa numarası aralık dışı -> son sayfaya sıkıştırılır (200, kartlı)', r.status === 200 && count(r.body, /class="card-shell"/g) >= 1);
	r = await get('/?post_type=mb_haber&mb_page=abc&mb_type=%3Cscript%3E');
	check('haberler: bozuk mb_page/mb_type güvenle yok sayılır (200, ilk sayfa), betik yansımaz', r.status === 200 && count(r.body, /class="card-shell"/g) === 12 && !r.body.includes('<script>alert') && !/mb_type=<script/i.test(r.body));
	r = await get('/?post_type=mb_haber&mb_type=duyuru');
	check('haberler: mb_type=duyuru yalnız duyuruları listeler (13 haberin 1,5,9,13 numaralıları)', r.status === 200 && r.body.includes('TEST Haber 13') && r.body.includes('TEST Haber 01') && !r.body.includes('TEST Haber 02') && count(r.body, /class="card-shell"/g) === 4, 'kart=' + count(r.body, /class="card-shell"/g));
	r = await get('/?post_type=mb_haber&p=' + ids.news_hidden);
	check('haber (yayında ama ONAYSIZ) tekil URL: gerçek 404', r.status === 404, String(r.status));
	r = await get('/?post_type=mb_haber&p=' + ids.news_last);
	check('onaylı haber tekil URL: 200, tek H1', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && r.body.includes('TEST Haber 13'), String(r.status));
	r = await get('/');
	check('ana sayfa 200: en yeni 3 haber şeridi (onaylı) ve boş referans/kart hatası yok', r.status === 200 && r.body.includes('Haberler ve Duyurular') && r.body.includes('TEST Haber 13') && !r.body.includes('TEST Onaysız'), String(r.status));

	/* ---------- dokümanlar ---------- */
	r = await get('/?post_type=mb_dokuman');
	check('dokümanlar arşivi 200, tek H1', r.status === 200 && count(r.body, /<h1[ >]/g) === 1, String(r.status));
	check('dokümanlar: pasif doküman YOK; geçerli, dosyasız, zararlı-MIME ve süresi dolmuş listelenir', r.body.includes('TEST Doküman Geçerli') && r.body.includes('TEST Doküman Dosyasız') && r.body.includes('TEST Doküman Zararlı MIME') && r.body.includes('TEST Doküman Süresi Dolmuş') && !r.body.includes('TEST Doküman Pasif'));
	check('dokümanlar: indirme bağlantısı yalnız doğrulanmış PDF için (2 geçerli dosyalı kart), dosyasız/zararlı MIME için "henüz eklenmedi" notu', count(r.body, /İndir \(PDF/g) === 2 && count(r.body, /Doküman dosyası henüz eklenmedi/g) === 2, 'indir=' + count(r.body, /İndir \(PDF/g));
	check('dokümanlar: süresi dolmuş rozet gösterilir; javascript:/data: bağlantısı yok', r.body.includes('Geçerlilik süresi dolmuş') && !/href="\s*(javascript|data):/i.test(r.body));
	check('dokümanlar: kategori filtre şeridi (kalite, formlar) gerçek bağlantılar', r.body.includes('mb_cat=kalite') && r.body.includes('mb_cat=formlar'));
	r = await get('/?post_type=mb_dokuman&mb_cat=kalite');
	check('dokümanlar: mb_cat=kalite yalnız o kategori (1 kayıt)', r.status === 200 && r.body.includes('TEST Doküman Geçerli') && !r.body.includes('TEST Doküman Süresi Dolmuş') && count(r.body, /class="doc-card"/g) === 1, 'doc=' + count(r.body, /class="doc-card"/g));
	r = await get('/?post_type=mb_dokuman&mb_cat=yok-boyle-bir-kategori');
	check('dokümanlar: var olmayan kategori -> SIFIR sonuç + dürüst boş durum (filtre sessizce yok sayılmaz)', r.status === 200 && count(r.body, /class="doc-card"/g) === 0 && r.body.includes('Yayınlanmış doküman bulunamadı'));
	r = await get('/?post_type=mb_dokuman&p=' + ids.doc_passive);
	check('pasif doküman tekil URL: gerçek 404', r.status === 404, String(r.status));
	r = await get('/?post_type=mb_dokuman&p=' + ids.doc_ok);
	check('doküman tekil: 200, tek H1, indirme düğmesi (doğrulanmış PDF)', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && r.body.includes('Dokümanı İndir'), String(r.status));
	r = await get('/?post_type=mb_dokuman&p=' + ids.doc_badmime);
	check('zararlı MIME\'lı doküman tekil: indirme düğmesi YOK, uyarı var', r.status === 200 && !r.body.includes('Dokümanı İndir') && r.body.includes('henüz eklenmemiş'));

	/* ---------- referanslar ---------- */
	r = await get('/?pagename=referanslar');
	check('referanslar sayfası 200, tek H1', r.status === 200 && count(r.body, /<h1[ >]/g) === 1, String(r.status));
	check('referanslar: pasif referans YOK; "Temsili görsel" notu yalnız gerçek olmayanlarda (1), gerçek referansta yok', r.body.includes('TEST Referans Gerçek') && r.body.includes('TEST Referans Temsili') && !r.body.includes('TEST Referans Pasif') && count(r.body, /ref-demo-note/g) === 1, 'not=' + count(r.body, /ref-demo-note/g));
	check('referanslar: sıralama (sort_order): Temsili(1) Gerçek(2)\'den önce; slider/otomatik oynatma yok', r.body.indexOf('TEST Referans Temsili') < r.body.indexOf('TEST Referans Gerçek') && !/autoplay|swiper|carousel/i.test(r.body));
	r = await get('/?post_type=mb_referans&p=' + ids.ref_rep);
	check('temsili referans tekil: javascript: web sitesi bağlantısı ÜRETİLMEZ; "Temsili Görsel" rozeti var', r.status === 200 && !/href="javascript:/i.test(r.body) && r.body.includes('Temsili Görsel'), String(r.status));
	r = await get('/?post_type=mb_referans&p=' + ids.ref_real);
	check('gerçek referans tekil: https bağlantı rel=noopener noreferrer target=_blank ile', r.status === 200 && /href="https:\/\/ornek\.example\/"[^>]*rel="noopener noreferrer"/.test(r.body) && r.body.includes('Gerçek Referans'));
	r = await get('/?post_type=mb_referans&p=' + ids.ref_pass);
	check('pasif referans tekil: gerçek 404', r.status === 404, String(r.status));

	/* ---------- SSS ---------- */
	r = await get('/?pagename=sss');
	check('SSS sayfası 200, tek H1; akordeon 2 aktif soru, pasif YOK; <script> temizlenir, <strong> korunur', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && count(r.body, /class="accordion-item"/g) === 2 && !r.body.includes('TEST Soru Pasif') && !r.body.includes('alert(1)') && r.body.includes('<strong>kalın</strong>'), 'item=' + count(r.body, /class="accordion-item"/g) + ' h1=' + count(r.body, /<h1[ >]/g) + ' pasif=' + r.body.includes('TEST Soru Pasif') + ' alert=' + r.body.includes('alert(1)') + ' strong=' + r.body.includes('<strong>kalın</strong>'));
	r = await get('/?pagename=sss&mb_cat=genel');
	check('SSS: mb_cat=genel yalnız o kategori (1 soru)', r.status === 200 && count(r.body, /class="accordion-item"/g) === 1);
	r = await get('/?pagename=sss&mb_cat=yok');
	check('SSS: var olmayan kategori -> sıfır soru + dürüst boş durum', r.status === 200 && count(r.body, /class="accordion-item"/g) === 0 && r.body.includes('Henüz soru eklenmedi'));

	/* ---------- lokasyon / iletişim ---------- */
	r = await get('/?post_type=mb_lokasyon&p=' + ids.loc_pass);
	check('pasif lokasyon tekil: gerçek 404 ve gizli adres sızmaz', r.status === 404 && !r.body.includes('TEST Gizli Adres'), String(r.status));
	r = await get('/?post_type=mb_lokasyon&p=' + ids.loc_b);
	check('lokasyon tekil: javascript: harita bağlantısı üretilmez', r.status === 200 && !/href="javascript:/i.test(r.body) && r.body.includes('TEST Adres Satırı B'));
	r = await get('/?pagename=iletisim');
	check('iletişim sayfası 200, tek H1; lokasyon kartları (2 aktif, pasif YOK); tel: ve https harita bağlantısı', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && r.body.includes('TEST Adres Satırı A') && !r.body.includes('TEST Gizli Adres') && r.body.includes('href="tel:02120000001"') && r.body.includes('https://harita.example/a') && !/href="javascript:/i.test(r.body));
	const footerLoc = (r.body.match(/<div class="footer-locations">[\s\S]*?<div class="footer-bottom">/) || [''])[0];
	check('iletişim: footer lokasyon bloğu mb_lokasyon verisini kullanır (TEST Lokasyon A var, statik yedek "Payas Sınav Alanı" YOK)', footerLoc.includes('TEST Lokasyon A') && !footerLoc.includes('Payas Sınav Alanı') && !footerLoc.includes('TEST Gizli'));
	const contactHtml = r.body;
	check('iletişim: form AÇIK — <form>, gizli alanlar (mb_form/mb_token/_mb_nonce), bal küpü (aria-hidden, tabindex=-1), onaylı rıza metni', /<form method="post"/.test(contactHtml) && hidden(contactHtml, 'mb_form') === 'contact' && hidden(contactHtml, 'mb_token').length > 20 && hidden(contactHtml, '_mb_nonce').length >= 8 && contactHtml.includes('class="hp-field" aria-hidden="true"') && contactHtml.includes('tabindex="-1"') && contactHtml.includes('sentetik onay metni'));
	check('iletişim: her alan için <label for> ve required/aria-required; e-posta type=email, telefon type=tel', /<label for="mbf-contact-email">/.test(contactHtml) && /type="email"[^>]*required aria-required="true"/.test(contactHtml) && contactHtml.includes('type="tel"'));
	for (const slug of ['online-basvuru', 'sinav-talepleri', 'itiraz-sikayet', 'is-basvurusu']) {
		r = await get('/?pagename=' + slug);
		check('kapalı form sayfası (' + slug + '): 200, tek H1, <form> ÇİZİLMEZ, "şu anda kullanılamıyor", kapalı NEDENİ ziyaretçiye gösterilmez', r.status === 200 && count(r.body, /<h1[ >]/g) === 1 && !/<form[ >]/.test(r.body.replace(/<form[^>]*role="search"[\s\S]*?<\/form>/g, '').replace(/<form[^>]*class="[^"]*search[^"]*"[\s\S]*?<\/form>/g, '')) && r.body.includes('şu anda kullanılamıyor') && !r.body.includes('KVKK açık rıza metni kurumca onaylanmadı'), String(r.status));
	}

	/* ---------- form gönderimi ---------- */
	const path = '/?pagename=iletisim';
	const mail0 = mailCount();
	const valid = (extra) => Object.assign({ full_name: 'Test Kullanici', phone: '0212 000 00 00', email: 'test@ornek.example', message: 'Sentetik mesaj.', consent: '1' }, extra || {});
	async function fresh() {
		const g = await get(path);
		return { mb_form: 'contact', mb_token: hidden(g.body, 'mb_token'), _mb_nonce: hidden(g.body, '_mb_nonce'), mb_hp_url: '' };
	}
	let sec = await fresh();
	r = await post(path, Object.assign({}, sec, valid()));
	check('form: token < 3 sn -> reddedilir (çok hızlı), e-posta GİTMEZ, 400', r.status === 400 && mailCount() === mail0, r.status + ' mail=' + mailCount());
	await sleep(3500);
	r = await post(path, Object.assign({}, sec, valid({ _mb_nonce: 'gecersiz' })));
	check('form: geçersiz nonce -> reddedilir, e-posta GİTMEZ', r.status === 400 && mailCount() === mail0);
	r = await post(path, Object.assign({}, sec, valid({ mb_token: sec.mb_token.replace(/.$/, sec.mb_token.slice(-1) === 'a' ? 'b' : 'a') })));
	check('form: kurcalanmış jeton imzası -> reddedilir, e-posta GİTMEZ', r.status === 400 && mailCount() === mail0);
	r = await post(path, Object.assign({}, sec, valid({ email: 'kotu@ornek.example\r\nBcc: x@ornek.example' })));
	check('form: e-posta alanında CRLF (başlık enjeksiyonu) -> doğrulama hatası, e-posta GİTMEZ; hata özeti role=alert', r.status === 200 && r.body.includes('role="alert"') && r.body.includes('Geçerli bir e-posta') && mailCount() === mail0, r.status + '');
	r = await post(path, Object.assign({}, sec, { full_name: '', phone: '', email: '', message: '', consent: '' }));
	check('form: boş zorunlu alanlar -> 200, hata özeti + alan hataları (aria-invalid), URL/Location kişisel veri taşımaz', r.status === 200 && r.body.includes('form-error-summary') && r.body.includes('aria-invalid="true"') && r.location === '' && mailCount() === mail0);
	r = await post(path, Object.assign({}, sec, valid({ full_name: 'Yeniden Dolan Ad', message: '' })));
	check('form: hata sonrası yeniden çizimde hassas olmayan değerler geri doldurulur (ad korunur)', r.status === 200 && r.body.includes('value="Yeniden Dolan Ad"'));
	r = await post(path, Object.assign({}, sec, valid({ mb_hp_url: 'http://spam.example' })));
	check('form: bal küpü dolu -> sahte başarı (303 PRG), e-posta GİTMEZ', r.status === 303 && r.location.includes('mb_form_status=success') && mailCount() === mail0, r.status + ' mail=' + mailCount());
	sec = await fresh();
	await sleep(3500);
	r = await post(path, Object.assign({}, sec, valid()));
	check('form: geçerli gönderim -> 303 PRG, Location yalnız mb_form_status/mb_form (kişisel veri YOK), e-posta 1 kez kabul edildi', r.status === 303 && /mb_form_status=success/.test(r.location) && !/Test|ornek\.example|0212/i.test(r.location) && mailCount() === mail0 + 1, r.status + ' ' + r.location + ' mail=' + mailCount());
	const rr = await post(path, Object.assign({}, sec, valid()));
	check('form: AYNI jeton/nonce ile tekrar gönderim (replay) reddedilir, ikinci e-posta GİTMEZ', rr.status === 400 && mailCount() === mail0 + 1, rr.status + ' mail=' + mailCount());
	r = await get(r.location.replace(BASE, ''));
	check('form: PRG sonrası GET -> başarı iletisi, form yeniden çizilmez (yenileme tekrar göndermez)', r.status === 200 && r.body.includes('Talebiniz alındı') && !/<form method="post"/.test(r.body));
	r = await post('/?pagename=itiraz-sikayet', { mb_form: 'complaint', mb_token: 'x', _mb_nonce: 'x', notice_type: 'objection', full_name: 'A', email: 'a@ornek.example', subject: 'S', description: 'D', consent: '1' });
	check('form: kapalı form (complaint, rıza onaysız) POST -> 503 unavailable, e-posta GİTMEZ', r.status === 503 && mailCount() === mail0 + 1, r.status + '');
	r = await post('/?pagename=iletisim', { mb_form: 'application', mb_token: 'x', _mb_nonce: 'x' });
	check('form: başka formun kimliği bu sayfada işlenmez (sayfa slug uyuşmazlığı) -> form işlenmedi (200 normal sayfa)', r.status === 200 && mailCount() === mail0 + 1);
	// Oran sınırı: aynı istemciden ard arda geçersiz denemeler 8 sonra 429.
	let limited = 0;
	for (let i = 0; i < 12; i++) {
		const s = await fresh();
		await sleep(3100);
		const x = await post(path, Object.assign({}, s, { full_name: '', phone: '', email: '', message: '', consent: '' }));
		if (x.status === 429) limited++;
	}
	check('form: oran sınırı — 8 denemeden sonra 429 (fail-closed), e-posta sayısı değişmez', limited >= 1 && mailCount() === mail0 + 1, 'limited=' + limited);

	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 7/8 HTTP render + form testi geçti.');
	process.exit(fail === 0 ? 0 : 1);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(2);
});
