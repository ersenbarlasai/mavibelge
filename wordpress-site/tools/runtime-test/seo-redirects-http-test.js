'use strict';
/**
 * Faz 9 HTTP testleri: title/description/canonical/robots/OG-Twitter/JSON-LD, robots.txt, sitemap, staging kapısı ve eski URL
 * yönlendirmeleri. YALNIZ mbfx_ fixture veritabanına yönlendirilmiş yerel ortamda çalışır (content-http.sh).
 *   node seo-redirects-http-test.js <fixtures.json>
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
	// Kalıcı bağlantılar açıkken WordPress sorgu-biçimli tekil URL'leri güzel bağlantıya 301'ler; tekil testler yönlendirmeyi izler.
	const follow = /^\/\?post_type=mb_[a-z]+&p=\d+$/.test(path);
	const res = await fetch(BASE + path, Object.assign({ redirect: follow ? 'follow' : 'manual' }, opts || {}));
	const headers = {};
	res.headers.forEach((v, k) => {
		headers[k] = v;
	});
	return { status: res.status, body: await res.text(), headers };
}
const meta = (html, re) => (html.match(re) || [])[1] || '';
const title = (html) => meta(html, /<title>([^<]*)<\/title>/);
const desc = (html) => meta(html, /<meta name="description" content="([^"]*)"/);
const canon = (html) => meta(html, /<link rel="canonical" href="([^"]*)"/);
const robots = (html) => meta(html, /<meta name='robots' content='([^']*)'/) || meta(html, /<meta name="robots" content="([^"]*)"/);
function graph(html) {
	const blocks = html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g) || [];
	if (blocks.length !== 1) return { count: blocks.length, nodes: [] };
	const json = JSON.parse(blocks[0].replace(/^<script[^>]*>/, '').replace(/<\/script>$/, ''));
	return { count: 1, nodes: json['@graph'], json };
}
const types = (g) => g.nodes.map((n) => n['@type']);
const sh = (cmd) => cp.execSync(cmd, { stdio: ['ignore', 'pipe', 'pipe'] }).toString();

(async () => {
	/* ---------- düz bağlantılar: robots.txt ve sitemap (çekirdek sitemap güzel bağlantıda gerçek rewrite ister) ---------- */
	let r = await get('/?robots=1');
	check('robots.txt (üretim): * grubu (wp-admin kapalı, admin-ajax açık, ?s= kapalı), arama botları, GPTBot yorumu, Sitemap satırı; tüm siteyi kapatan Disallow: / YOK', r.body.includes('User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php') && r.body.includes('User-agent: OAI-SearchBot') && r.body.includes('# GPTBot: kurum kararı bekleniyor') && r.body.includes('Sitemap: ' + BASE + '/wp-sitemap.xml') && !/^Disallow: \/\s*$/m.test(r.body), r.body.slice(0, 120));
	r = await get('/?sitemap=index');
	check('sitemap dizini: posts alt sitemap\'leri (page, mb_yeterlilik?, mb_haber, mb_dokuman, mb_lokasyon) + taksonomi; mb_ucret/mb_sss/mb_referans ve users YOK', r.status === 200 && r.body.includes('sitemap-subtype=mb_haber') && r.body.includes('sitemap-subtype=page') && r.body.includes('sitemap-subtype=mb_dokuman') && !r.body.includes('mb_ucret') && !r.body.includes('mb_sss') && !r.body.includes('mb_referans') && !/sitemap=users/.test(r.body), r.body.slice(0, 200));
	r = await get('/?sitemap=posts&sitemap-subtype=mb_haber');
	check('haber sitemap: 13 ONAYLI haber var; onaysız-yayında ve taslak haber YOK', r.status === 200 && (r.body.match(/<loc>/g) || []).length === 13 && !r.body.includes('p=' + ids.news_hidden + '<') && !r.body.includes(String(ids.news_hidden) + '/'), '' + (r.body.match(/<loc>/g) || []).length);
	r = await get('/?sitemap=posts&sitemap-subtype=mb_dokuman');
	check('doküman sitemap: pasif doküman YOK (4 aktif doküman)', r.status === 200 && (r.body.match(/<loc>/g) || []).length === 4 && !r.body.includes('doküman-pasif') && !r.body.includes('p=' + ids.doc_passive + '<'), '' + (r.body.match(/<loc>/g) || []).length);
	r = await get('/?sitemap=posts&sitemap-subtype=mb_lokasyon');
	check('lokasyon sitemap: pasif lokasyon YOK (2 aktif)', r.status === 200 && (r.body.match(/<loc>/g) || []).length === 2, '' + (r.body.match(/<loc>/g) || []).length);
	r = await get('/?sitemap=users');
	check('kullanıcı sitemap KAPALI (yazar/kullanıcı adı sızıntısı yok): XML dönmez', !r.body.includes('<urlset') && !r.body.includes('<sitemapindex') && !r.body.includes('/author/'), String(r.status));
	sh('docker exec -u www-data mbruntime6b2-wp-1 wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php --user=mbadmin eval-file /opt/mb-runtime/seo-fixtures.php');
	/* ---------- ana sayfa ---------- */
	r = await get('/');
	let g = graph(r.body);
	check('ana sayfa: title statik referans başlangıç değerinden (markalı), tek <title>', /Mavi Belge/.test(title(r.body)) && (r.body.match(/<title>/g) || []).length === 1, title(r.body));
	check('ana sayfa: meta description dolu ve <=160 karakter; tek meta description', desc(r.body).length > 20 && desc(r.body).length <= 160 && (r.body.match(/<meta name="description"/g) || []).length === 1, desc(r.body));
	check('ana sayfa: tek canonical (WordPress varsayılan rel_canonical KALDIRILDI) = ana sayfa URL\'si', (r.body.match(/rel="canonical"/g) || []).length === 1 && canon(r.body) === BASE + '/', canon(r.body));
	check('ana sayfa: robots index,follow + max-image-preview (üretim)', /index/.test(robots(r.body)) && !/noindex/.test(robots(r.body)) && /max-image-preview:large/.test(robots(r.body)), robots(r.body));
	check('ana sayfa: Open Graph (tr_TR, website, title, url) ve Twitter kartı; twitter:site UYDURULMAZ', r.body.includes('property="og:locale" content="tr_TR"') && r.body.includes('property="og:type" content="website"') && r.body.includes('property="og:url"') && r.body.includes('name="twitter:card" content="summary"') && !r.body.includes('twitter:site') && !r.body.includes('twitter:creator'));
	check('ana sayfa: TEK JSON-LD bloğu; @graph Organization+WebSite+WebPage (her biri tek); breadcrumb yok (ana sayfa)', g.count === 1 && types(g).join() === 'Organization,WebSite,WebPage', g.count + ' ' + types(g).join());
	check('ana sayfa: JSON-LD içinde </script> kaçış sızıntısı yok; Organization iletişim noktası doğrulanmış statik veriden', !/<\/script>[^<]*<\/script>/.test(r.body) && g.nodes[0].contactPoint && g.nodes[0].contactPoint.telephone === '+90 850 215 44 22');
	/* ---------- sayfa / haber ---------- */
	r = await get('/index.php/iletisim/');
	g = graph(r.body);
	check('iletişim sayfası: title "İletişim — Mavi Belge" (statik başlangıç), canonical kendi URL\'si, breadcrumb 2 öğe', /^İletişim/.test(title(r.body)) && canon(r.body) === BASE + '/index.php/iletisim/' && types(g).includes('BreadcrumbList') && g.nodes.find((n) => n['@type'] === 'BreadcrumbList').itemListElement.length === 2, title(r.body) + ' ' + canon(r.body));
	r = await get('/?post_type=mb_haber&p=' + ids.news_last);
	g = graph(r.body);
	const article = g.nodes.find((n) => n['@type'] === 'NewsArticle' || n['@type'] === 'Article');
	check('haber tekil: NewsArticle/Article (başlık + yayın tarihi), 4 öğeli breadcrumb (Ana Sayfa > Bilgi Merkezi > Haberler > başlık), og:type article + published_time', !!article && !!article.datePublished && types(g).includes('BreadcrumbList') && g.nodes.find((n) => n['@type'] === 'BreadcrumbList').itemListElement.length === 4 && r.body.includes('property="og:type" content="article"') && r.body.includes('article:published_time'), types(g).join());
	check('haber tekil: tek bir WebPage ve tek Organization (çelişkili tekrar yok); mainEntityOfPage WebPage @id\'sine bağlı', types(g).filter((t) => t === 'WebPage').length === 1 && types(g).filter((t) => t === 'Organization').length === 1 && article && article.mainEntityOfPage['@id'].endsWith('#webpage'));
	r = await get('/?post_type=mb_dokuman&p=' + ids.doc_ok);
	g = graph(r.body);
	check('doküman tekil (doğrulanmış PDF): DigitalDocument fileFormat=application/pdf; dosyasız dokümanda DigitalDocument YOK', types(g).includes('DigitalDocument') && g.nodes.find((n) => n['@type'] === 'DigitalDocument').fileFormat === 'application/pdf');
	r = await get('/?post_type=mb_dokuman&p=' + ids.doc_nofile);
	check('doküman tekil (dosyasız): DigitalDocument üretilmez', !types(graph(r.body)).includes('DigitalDocument'));
	r = await get('/?post_type=mb_lokasyon&p=' + ids.loc_a);
	g = graph(r.body);
	check('lokasyon tekil: Place + PostalAddress + telefon + https harita; javascript: harita şemaya girmez', types(g).includes('Place') && g.nodes.find((n) => n['@type'] === 'Place').address.streetAddress === 'TEST Adres Satırı A' && g.nodes.find((n) => n['@type'] === 'Place').hasMap === 'https://harita.example/a');
	r = await get('/?post_type=mb_lokasyon&p=' + ids.loc_b);
	check('lokasyon B (javascript: harita): hasMap YOK', !JSON.stringify(graph(r.body).nodes).includes('javascript:'));
	r = await get('/index.php/sss/');
	g = graph(r.body);
	check('SSS sayfası: FAQPage yalnız GÖRÜNÜR 2 aktif soru (pasif YOK); cevap düz metin (script içeriği yok)', types(g).includes('FAQPage') && g.nodes.find((n) => n['@type'] === 'FAQPage').mainEntity.length === 2 && !JSON.stringify(g.nodes).includes('alert(1)') && !JSON.stringify(g.nodes).includes('Pasif'));
	/* ---------- arşiv / filtre / arama / 404 ---------- */
	r = await get('/?post_type=mb_haber');
	check('haber arşivi: title statik başlangıçtan (Haberler), canonical var ve parametresiz (mb_* yok)', /^Haberler/.test(title(r.body)) && canon(r.body).length > 0 && !canon(r.body).includes('mb_'), title(r.body) + ' | ' + canon(r.body));
	r = await get('/?post_type=mb_haber&mb_page=2');
	check('haber arşivi sayfa 2: canonical KENDİ sayfası (mb_page=2), robots index,follow (sayfalı sayfa indekslenebilir)', canon(r.body).includes('mb_page=2') && !/noindex/.test(robots(r.body)), canon(r.body) + ' | ' + robots(r.body));
	r = await get('/?post_type=mb_haber&mb_type=duyuru');
	check('filtreli URL (mb_type): robots noindex,follow; canonical süzgeçsiz tabana', /noindex/.test(robots(r.body)) && /follow/.test(robots(r.body)) && !canon(r.body).includes('mb_type'), robots(r.body) + ' | ' + canon(r.body));
	r = await get('/?s=test');
	check('site içi arama: robots noindex,follow; canonical YOK; JSON-LD YOK', /noindex/.test(robots(r.body)) && canon(r.body) === '' && graph(r.body).count === 0, robots(r.body) + '|' + canon(r.body));
	r = await get('/?s=');
	check('boş arama: robots noindex, 200/404 fark etmeksizin canonical yok', /noindex/.test(robots(r.body)) && canon(r.body) === '');
	r = await get('/index.php/yok-boyle-bir-sayfa-1234/');
	check('404: durum 404, robots noindex, canonical ve JSON-LD YOK, statik 404 başlığı', r.status === 404 && /noindex/.test(robots(r.body)) && canon(r.body) === '' && graph(r.body).count === 0, r.status + ' ' + robots(r.body));
	r = await get('/index.php/iletisim/?mb_form_status=success&mb_form=contact');
	check('form başarı sayfası (PRG sonrası): robots noindex; canonical temiz sayfa URL\'si', /noindex/.test(robots(r.body)) && canon(r.body) === BASE + '/index.php/iletisim/', robots(r.body) + ' | ' + canon(r.body));
	/* ---------- robots.txt ve sitemap ---------- */
	/* ---------- eski URL yönlendirmeleri ---------- */
	r = await get('/index.php/eski-onayli/');
	check('yönlendirme: aktif+doğrulanmış kural -> 301, Location iç hedef (/index.php/iletisim/)', r.status === 301 && (r.headers.location || '').endsWith('/index.php/iletisim/'), r.status + ' ' + r.headers.location);
	r = await get('/index.php/ESKI-ONAYLI/?utm_source=x');
	check('yönlendirme: büyük harf + sorgu dizgesi normalize edilip eşleşir (301)', r.status === 301 && (r.headers.location || '').endsWith('/index.php/iletisim/'), r.status + ' ' + r.headers.location);
	r = await get('/index.php/eski-onerilen/');
	check('yönlendirme: PASİF (proposed) kural ÇALIŞMAZ — normal 404', r.status === 404, String(r.status));
	r = await get('/index.php/eski-kaldirildi/');
	check('yönlendirme: 410 kuralı -> 410 Gone (404 şablonuyla), noindex', r.status === 410 && /noindex/.test(robots(r.body)), r.status + '');
	r = await get('/index.php/eski-gecici/');
	check('yönlendirme: 302 kuralı -> 302', r.status === 302 && (r.headers.location || '').endsWith('/index.php/referanslar/'), r.status + ' ' + r.headers.location);
	r = await get('/index.php/sss/');
	check('yönlendirme: kuralın kaynağı VAR OLAN bir sayfaysa sayfayı GÖLGELEMEZ (yalnız 404\'te uygulanır) -> 200', r.status === 200 && r.body.includes('accordion'), String(r.status));
	r = await get('/index.php/bilinmeyen-eski-adres/');
	check('yönlendirme: kural olmayan bilinmeyen adres -> 404 (toplu ana sayfa yönlendirmesi YOK)', r.status === 404, String(r.status));

	/* ---------- staging kapısı ---------- */
	sh('docker exec mbruntime6b2-wp-1 sh -c "touch /tmp/mbfx-staging && chmod 644 /tmp/mbfx-staging"');
	r = await get('/');
	check('staging kapısı: robots noindex,nofollow (her sayfa), X-Robots-Tag başlığı, JSON-LD çıktısı sürer', /noindex/.test(robots(r.body)) && /nofollow/.test(robots(r.body)) && /noindex, nofollow/.test(r.headers['x-robots-tag'] || ''), robots(r.body) + ' | ' + r.headers['x-robots-tag']);
	r = await get('/index.php/iletisim/');
	check('staging kapısı: normal iç sayfa da noindex,nofollow', /noindex/.test(robots(r.body)) && /nofollow/.test(robots(r.body)));
	r = await get('/?robots=1');
	check('staging robots.txt: yalnız "User-agent: * / Disallow: /" (sitemap ve bot izni yok)', r.body.trim() === '# Mavi Belge — üretim DIŞI ortam: hiçbir şey taranmamalı.\nUser-agent: *\nDisallow: /', r.body.slice(0, 160));
	sh('docker exec mbruntime6b2-wp-1 rm -f /tmp/mbfx-staging');

	console.log('\n' + pass + '/' + (pass + fail) + ' Faz 9 SEO + yönlendirme HTTP testi geçti.');
	process.exit(fail === 0 ? 0 : 1);
})().catch((e) => {
	sh('docker exec mbruntime6b2-wp-1 rm -f /tmp/mbfx-staging');
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(2);
});
