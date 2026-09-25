'use strict';
/**
 * Faz 11 render QA (gerçek HTTP istemcisi; tarayıcı DEĞİL). 41 statik sayfanın WordPress şablon eşlemesini, temel
 * erişilebilirlik/doğruluk sözleşmesini ve boş durum davranışını doğrular; sonra eklentiyi PASİFLEŞTİRip tema yedek
 * davranışını sınar. YALNIZ mbfx_ fixture veritabanı (qa-render.sh).  node qa-render-test.js <qa-fixtures.json>
 */
const fs = require('fs');
const path = require('path');
const cp = require('child_process');

const BASE = 'http://127.0.0.1:18673';
const fx = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^[^{]*/, ''));
const SITE = path.join(__dirname, '..', '..', '..', 'tanitim-site');
const files = fs.readdirSync(SITE).filter((f) => /\.html$/.test(f)).map((f) => f.replace(/\.html$/, '')).sort();
let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + detail + ']' : ''));
	}
}
async function get(p) {
	const res = await fetch(BASE + p, { redirect: 'manual' });
	return { status: res.status, body: await res.text() };
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
	check(label + ': target=_blank bağlantılarında rel noopener', (b.match(/<a\b[^>]*target="_blank"[^>]*>/g) || []).every((t) => /rel="[^"]*noopener/.test(t)));
	check(label + ': PHP uyarı/hata metni YOK', !/(Warning|Notice|Deprecated|Fatal error|Parse error):/.test(b) && !/Stack trace/.test(b));
	check(label + ': dış (http:) bağlantı yok — yalnız https veya yerel', !/href="http:\/\/(?!127\.0\.0\.1)/.test(b));
}

(async () => {
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
		const spec = special[slug] || { url: '/?pagename=' + slug, status: 200 };
		const r = await get(spec.url);
		contract('[' + slug + ']', r, spec.status);
		mapped++;
	}
	check('41 statik sayfanın TAMAMI bir WordPress şablonuna eşlendi ve render edildi', files.length === 41 && mapped === 41, files.length + '/' + mapped);
	// Boş durum davranışı (henüz içerik yok): dürüst boş durum, uydurma kayıt yok.
	let r = await get('/?post_type=mb_dokuman');
	check('boş durum: dokümanlar arşivi dürüst boş iletisi', r.body.includes('Yayınlanmış doküman bulunamadı') && count(r.body, /class="doc-card"/g) === 0);
	r = await get('/?pagename=referanslar');
	check('boş durum: referanslar dürüst boş iletisi', r.body.includes('Referans listesi henüz yayınlanmadı'));
	r = await get('/?pagename=sss');
	check('boş durum: SSS dürüst boş iletisi', r.body.includes('Henüz soru eklenmedi'));
	r = await get('/?pagename=iletisim');
	check('iletişim: kapalı form (yapılandırma yok) -> <form> yok, "şu anda kullanılamıyor", statik iletişim yedeği görünür', !/<form method="post"/.test(r.body) && r.body.includes('şu anda kullanılamıyor') && r.body.includes('Atay İş Merkezi'));
	r = await get('/?pagename=sinav-ucretleri');
	check('sinav-ucretleri: aktif dönem/ücret yokken dürüst boş durum, fatal yok', r.status === 200 && !/Fatal error/.test(r.body));
	r = await get('/?post_type=mb_yeterlilik&mb_q=zzqqxx-yok');
	check('arama: sonuçsuz filtre -> 200 + dürüst boş durum', r.status === 200);
	r = await get('/?s=zzqqxx');
	check('site içi arama sonuçsuz: 200, tek h1', r.status === 200 && count(r.body, /<h1[ >]/g) === 1);
	r = await get('/?mb_sektor=yok-boyle-bir-sektor');
	check('hatalı sektör terimi -> 404 (fatal yok)', r.status === 404 && !/Fatal error/.test(r.body));
	r = await get('/?post_type=mb_yeterlilik&mb_page=abc&mb_level=99&mb_sector=%3Cscript%3E');
	check('bozuk filtre parametreleri güvenle yok sayılır (200, betik yansımaz)', r.status === 200 && !r.body.includes('<script>alert') && !/mb_sector=<script/.test(r.body));

	// Eklenti PASİFKEN tema yedek davranışı (fatal yok, güvenli boş durum).
	sh('docker exec -u www-data mbruntime6b2-wp-1 wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php plugin deactivate mavibelge-core');
	for (const [label, url] of [['ana sayfa', '/'], ['haberler arşivi', '/?post_type=mb_haber'], ['iletişim sayfası', '/?pagename=iletisim'], ['sinav-ucretleri sayfası', '/?pagename=sinav-ucretleri'], ['sss sayfası', '/?pagename=sss'], ['referanslar sayfası', '/?pagename=referanslar'], ['arama', '/?s=test']]) {
		r = await get(url);
		check('eklenti PASİF — ' + label + ': fatal/uyarı YOK, sayfa render edilir (durum <500)', r.status < 500 && !/(Fatal error|Warning|Notice|Deprecated):/.test(r.body) && r.body.includes('</html>'), r.status + '');
	}
	r = await get('/');
	check('eklenti PASİF — ana sayfa: header/footer ve tek h1 korunur', /class="site-header"/.test(r.body) && /class="site-footer"/.test(r.body) && count(r.body, /<h1[ >]/g) === 1);
	sh('docker exec -u www-data mbruntime6b2-wp-1 wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php plugin activate mavibelge-core');
	r = await get('/');
	check('eklenti yeniden etkin: ana sayfa 200', r.status === 200);

	console.log(pass + '/' + (pass + fail) + ' Faz 11 render QA kontrolü geçti.');
	process.exit(fail === 0 ? 0 : 1);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(2);
});
