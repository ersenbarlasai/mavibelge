'use strict';
/**
 * Faz 12e — yeterlilik liste/detay HTTP render sözleşmesi (gerçek HTTP istemcisi; tarayıcı DEĞİL). GERÇEK katalog verisi
 * (qualification-render-fixtures.php), kalıcı bağlantı yapısı /index.php/%postname%/. YALNIZ mbfx_ fixture DB.
 *   node qualification-render-test.js <qual-fixtures.json>
 */
const fs = require('fs');

const BASE = 'http://127.0.0.1:18673';
const fx = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^[^{]*/, ''));
let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + String(detail).slice(0, 300) + ']' : ''));
	}
}
async function get(p) {
	const res = await fetch(p.indexOf('http') === 0 ? p : BASE + p, { redirect: 'manual' });
	return { status: res.status, body: await res.text() };
}
const count = (s, re) => (s.match(re) || []).length;
const dec = (s) => s.replace(/&amp;/g, '&').replace(/&#038;/g, '&').replace(/&#8211;/g, '–');
const mainOf = (b) => b.slice(b.indexOf('<main'), b.indexOf('</main>'));
function common(label, r) {
	const b = r.body;
	check(label + ': HTTP 200', r.status === 200, r.status);
	check(label + ': tek <h1>', count(b, /<h1[ >]/g) === 1, count(b, /<h1[ >]/g));
	check(label + ': PHP uyarı/hata metni yok', !/(Warning|Notice|Deprecated|Fatal error|Parse error):/.test(b));
	check(label + ': href="#" / javascript: yok', !/href="#"/.test(b) && !/href="\s*javascript:/i.test(b));
	const levels = (mainOf(b).match(/<h([1-6])[ >]/g) || []).map((h) => Number(h[2]));
	let ok = levels[0] === 1;
	for (let i = 1; i < levels.length; i++) if (levels[i] > levels[i - 1] + 1) ok = false;
	check(label + ': başlık sırası atlamasız (H1 -> H2 ...)', ok, levels.join(','));
	check(label + ': dahili sayfa bağlantıları /index.php/ önekli (home_url(/slug/) hatası yok)', !/href="http:\/\/127\.0\.0\.1:18673\/(online-basvuru|sinav-ucretleri|belge-yenileme|dokumanlar|yeterlilikler)\//.test(b) && !/\/index\.php\/index\.php/.test(b));
	check(label + ': iç içe <a> yok', !/<a\b[^>]*>(?:(?!<\/a>)[\s\S])*<a\b/.test(mainOf(b)));
	check(label + ': her <img> alt öznitelikli', (b.match(/<img\b[^>]*>/g) || []).every((t) => /\balt=/.test(t)));
}
function cards(b) {
	return b.match(/<article class="qual-card">[\s\S]*?<\/article>/g) || [];
}

(async () => {
	const archive = fx.archive;
	check('fixture: 14 sektör, 83 yayında yeterlilik, 103 ücret, 2 aktif ücret, arşiv /index.php/yeterlilikler/', fx.counts.sectors === 14 && fx.counts.quals === 83 && fx.counts.fees === 103 && fx.counts.active === 2 && /\/index\.php\/yeterlilikler\/$/.test(archive), JSON.stringify(fx.counts) + ' ' + archive);

	/* ---- arşiv 1. sayfa ---- */
	let r = await get(archive);
	let b = r.body;
	common('arşiv s.1', r);
	const hero = b.slice(b.indexOf('<section class="page-hero"'), b.indexOf('</section>', b.indexOf('<section class="page-hero"')));
	check('arşiv: page-hero içinde breadcrumb + eyebrow + H1 + açıklama', /class="breadcrumb"/.test(hero) && /class="eyebrow">Meslekler ve Belgeler</.test(hero) && /<h1>Tüm Meslekler ve Yeterlilikler<\/h1>/.test(hero) && /MYK ulusal yeterlilik sistemine göre belgelendirdiğimiz tüm meslekleri arayın ve filtreleyin\./.test(hero));
	check('arşiv: GET filtre formu gerçek arşiv action + mb_q/mb_sector/mb_level/mb_priced', new RegExp('<form class="catalog-filter-form" method="get" action="' + archive.replace(/[.?]/g, '\\$&') + '"').test(b) && ['mb_q', 'mb_sector', 'mb_level', 'mb_priced'].every((k) => b.indexOf('name="' + k + '"') !== -1));
	check('arşiv: sektör seçeneği 14 gerçek terim', count(b.slice(b.indexOf('id="mb_sector"'), b.indexOf('</select>', b.indexOf('id="mb_sector"'))), /<option value="[a-z-]+"/g) === 14);
	check('arşiv: sonuç özeti "Toplam 83 meslekten 1–12"', /Toplam 83 meslekten 1–12 arası gösteriliyor\./.test(dec(b)));
	const c1 = cards(b);
	check('arşiv: 12 kart, her kartta tam iki bağlantı (Detayları Gör -> gerçek permalink, Başvuru Yap -> /index.php/online-basvuru/?meslek=KOD)', c1.length === 12 && c1.every((c) => count(c, /<a\s/g) === 2 && /href="http:\/\/127\.0\.0\.1:18673\/index\.php\/yeterlilikler\/[a-z0-9-]+\/"[^>]*>Detayları Gör</.test(c) && /href="http:\/\/127\.0\.0\.1:18673\/index\.php\/online-basvuru\/\?meslek=[0-9A-Z]+-[0-9](%2F[0-9]+)?"[^>]*>Başvuru Yap</.test(dec(c))), c1[0]);
	check('arşiv: kart başlığı H2, etiketler (kod, seviye, sektör)', c1.every((c) => /<h2 class="qual-card-title">/.test(c) && count(c, /class="tag"/g) >= 3));
	const pag = b.slice(b.indexOf('<nav class="pagination"'), b.indexOf('</nav>', b.indexOf('<nav class="pagination"')));
	check('arşiv: sayfalama — Önceki devre dışı, 1 aria-current, 7 sayfa, Sonraki rel=next mb_page=2', /<span class="page-btn page-prev is-disabled" aria-disabled="true">Önceki<\/span>/.test(pag) && /<span class="page-btn is-active" aria-current="page">1<\/span>/.test(pag) && count(pag, /class="page-btn"/g) === 6 && /class="page-btn page-next" href="[^"]*mb_page=2" rel="next">Sonraki</.test(dec(pag)) && /aria-label="Sayfalama"/.test(b));
	const iPag = b.indexOf('<nav class="pagination"');
	const iBrowse = b.indexOf('id="sektorler"');
	check('arşiv: "Sektöre Göre Gözat" sonuçlar ve sayfalamadan SONRA; sonuç öncesi sektör düğmesi yok', iBrowse > iPag && iPag > b.indexOf('class="qual-list"') && b.indexOf('class="sector-card"') > iBrowse);
	const browse = b.slice(iBrowse, b.indexOf('</section>', iBrowse));
	const sc = browse.match(/<a class="sector-card" href="([^"]+)">[\s\S]*?<\/a>/g) || [];
	check('arşiv: 14 sektör kartı; gerçek get_term_link URL, sırası servisle aynı, ikon SVG aria-hidden, "Meslekleri İncele →"', sc.length === 14 && sc.every((a, i) => a.indexOf('href="' + fx.sector_links[i].url + '"') !== -1 && /<div class="sector-icon" aria-hidden="true">\s*<svg class="icon-22" aria-hidden="true"/.test(a) && /Meslekleri İncele →/.test(a) && a.indexOf('<strong>' + fx.sector_links[i].name.replace(/&/g, '&amp;') + '</strong>') !== -1), sc[0]);
	check('arşiv: sektör bölümü bg-light + H2', /<h2 id="sektorler-baslik">Sektöre Göre Gözat<\/h2>/.test(browse));
	const sectorPage = await get(fx.sector_links[0].url);
	check('arşiv: sektör kartı bağlantısı 200 döner (' + fx.sector_links[0].slug + ')', sectorPage.status === 200, sectorPage.status);

	/* ---- 2. sayfa ---- */
	r = await get(archive + '?mb_page=2');
	b = r.body;
	common('arşiv s.2', r);
	check('arşiv s.2: "13–24", Önceki rel=prev (1. sayfaya, mb_page yok), 2 aria-current', /Toplam 83 meslekten 13–24 arası/.test(dec(b)) && new RegExp('class="page-btn page-prev" href="' + archive.replace(/[.?]/g, '\\$&') + '" rel="prev"').test(b) && /aria-current="page">2</.test(b) && cards(b).length === 12);

	/* ---- filtreli ---- */
	r = await get(archive + '?mb_sector=tekstil&mb_level=2');
	b = r.body;
	common('arşiv filtreli', r);
	const fc = cards(b);
	check('arşiv filtreli: yalnız tekstil + seviye 2 sonuçları, İplik Bitim içinde; tek sayfa -> sayfalama YOK', fc.length > 0 && fc.every((c) => /Seviye 2/.test(c) && /Tekstil/.test(c)) && fc.some((c) => /İplik Bitim İşleri Operatörü/.test(c)) && !/<nav class="pagination"/.test(b), fc.length);
	check('arşiv filtreli: seçimler korunur + Temizle bağlantısı arşive', /<option value="tekstil"\s+selected='selected'/.test(b) && /<option value="2"\s+selected='selected'/.test(b) && new RegExp('class="btn btn-ghost btn-sm" href="' + archive.replace(/[.?]/g, '\\$&') + '">Temizle').test(b));
	r = await get(archive + '?mb_q=makine&mb_page=2');
	check('arşiv arama + sayfa: sayfa bağlantıları filtreyi korur (mb_q=makine)', /Toplam \d+ meslekten/.test(dec(r.body)) ? (dec(r.body).match(/class="page-btn[^"]*" href="([^"]+)"/g) || []).every((h) => /mb_q=makine/.test(h)) : true);
	r = await get(archive + '?mb_priced=1');
	b = r.body;
	common('arşiv yalnız fiyatlı', r);
	check('arşiv yalnız fiyatlı: 2 sonuç (İplik Bitim + Makine Bakımcı 3), checkbox işaretli', cards(b).length === 2 && /İplik Bitim İşleri Operatörü/.test(b) && /10UY0002-3\/03/.test(b) && /name="mb_priced" value="1"\s+checked='checked'/.test(b), cards(b).length);

	/* ---- detaylar ---- */
	const Q = fx.quals;
	for (const k of Object.keys(Q)) {
		r = await get(Q[k].url);
		common('detay ' + k, r);
		b = r.body;
		const h = b.slice(b.indexOf('<section class="page-hero qual-hero"'), b.indexOf('</section>', b.indexOf('<section class="page-hero qual-hero"')));
		check('detay ' + k + ': kahraman içinde breadcrumb (Anasayfa / Meslekler ve Belgeler / başlık) + MYK Kodu eyebrow + H1', /class="breadcrumb"/.test(h) && /Anasayfa/.test(h) && /Meslekler ve Belgeler/.test(h) && dec(h).indexOf('MYK Kodu: ' + Q[k].code) !== -1 && dec(h).indexOf('<h1>' + Q[k].title + '</h1>') !== -1);
		check('detay ' + k + ': qual-detail-grid + aside.sticky-cta + dört bilgi kartı + beş H2 bölüm', /class="container qual-detail-grid"/.test(b) && /<aside class="sticky-cta"/.test(b) && count(b.slice(b.indexOf('class="qual-facts"'), b.indexOf('</ul>', b.indexOf('class="qual-facts"'))), /<li>/g) === 4 && ['Yeterlilik Özeti', 'Yeterlilik Birimleri', 'Sınav Yapısı', 'Belge Geçerliliği', 'İlgili Dokümanlar'].every((t) => b.indexOf('<h2>' + t + '</h2>') !== -1));
		check('detay ' + k + ': örnek yapı etiketli (is-sample + "Örnek yapı"), "Bilgi güncellenecektir"', /unit-list is-sample/.test(b) && /Örnek yapı/.test(b) && count(b, /Bilgi güncellenecektir/g) >= 2);
		check('detay ' + k + ': başvuru /index.php/online-basvuru/?meslek=<kod>, ücretler/yenileme/doküman /index.php/', dec(b).indexOf('href="' + BASE + '/index.php/online-basvuru/?meslek=' + encodeURIComponent(Q[k].code) + '"') !== -1 && b.indexOf(BASE + '/index.php/sinav-ucretleri/') !== -1 && b.indexOf(BASE + '/index.php/belge-yenileme/') !== -1 && b.indexOf(BASE + '/index.php/dokumanlar/') !== -1);
		if (Q[k].image_id > 0) {
			const want = Q[k].image_url.split('/').pop();
			const m = h.match(/background-image:url\('(http:\/\/127\.0\.0\.1:18673\/wp-content\/uploads\/[^']+)'\)/);
			check('detay ' + k + ': sektör görseli kahraman arka planı (' + Q[k].sector + ' -> ' + want + ')', !!m && m[1].split('/').pop() === want, h.slice(0, 200));
			const img = m ? await fetch(m[1]) : { status: 0, headers: new Map() };
			check('detay ' + k + ': kahraman görseli erişilebilir (HTTP 200, image/png)', img.status === 200 && /image\/png/.test(img.headers.get('content-type') || ''), img.status);
		} else {
			check('detay ' + k + ': görselsiz sektörde jenerik kahraman görseli (' + Q[k].sector + ')', /background-image:url\('[^']*\/themes\/mavibelge\/assets\/images\/hero\/hero-generic\.svg'\)/.test(h), h.slice(0, 200));
		}
		const sectorLink = fx.sector_links.find((s) => s.slug === Q[k].sector);
		check('detay ' + k + ': sektör adı gerçek sektör arşivine bağlanır', !!sectorLink && h.indexOf('href="' + sectorLink.url + '"') !== -1);
	}
	b = (await get(Q.iplik.url)).body;
	check('detay iplik: tek fiyat 6.500,00 TL SAYFADA TAM BİR KEZ, aside içinde; KDV + basım + kaynak + sayfa', count(b, /6\.500,00 TL/g) === 1 && b.indexOf('6.500,00 TL') > b.indexOf('<aside class="sticky-cta"') && /KDV Dahil/.test(b) && /1\.500,00 TL/.test(b) && /2026 Yeni Meslekler Ücret Tarifesi/.test(b) && /Sayfa 4/.test(b));
	check('detay iplik: revizyon gerçek değer (01), Seviye 2, eski qual-fee-section yok', /<strong>Revizyon<\/strong><span>01<\/span>/.test(b) && /Seviye 2/.test(b) && !/qual-fee-section/.test(b));
	b = (await get(Q.multi.url)).body;
	check('detay çok seçenekli: iki seçenek açık listede (10.500,00 TL + 20.250,00 TL) her biri bir kez', /<details class="fee-options-detail" open>/.test(b) && count(b, /10\.500,00 TL/g) === 1 && count(b, /20\.250,00 TL/g) === 1 && /Fiyat Seçenekleri \(2\)/.test(b));
	b = (await get(Q.nofee.url)).body;
	check('detay ücretsiz: bilgi mesajı + Sınav Ücretleri bağlantısı; fiyat YOK', /şu an güncel bir ücret kaydı bulunmuyor/.test(b) && !/class="fee-amount"/.test(b) && !/fee-options-detail/.test(b) && /Sınav Ücretlerini Gör/.test(b));

	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 12e yeterlilik render testi geçti.');
	process.exit(fail ? 1 : 0);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
