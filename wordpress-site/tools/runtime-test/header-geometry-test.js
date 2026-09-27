'use strict';
/**
 * Faz 12e kapanış — başlık geometrisi ve menü etkileşimi, GERÇEK headless Chrome (CDP; bağımlılık yok). Yalnız yerel mbfx_
 * fixture sunucusu (qualification-render.sh ayaktayken). Ölçüm getBoundingClientRect ile yapılır (lib/header-geometry.js):
 * görünür başlık öğeleri kesişmez, masaüstü etiketleri kutusuna sığar, hamburger modunda kapalı menü görünmez, taşma yok.
 *
 *   node header-geometry-test.js <qual-fixtures.json> <ekran-görüntüsü-dizini>
 */
const fs = require('fs');
const path = require('path');
const { launch } = require('./lib/cdp.js');
const { PROBE } = require('./lib/header-geometry.js');

const fx = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^[^{]*/, ''));
const SHOTS = process.argv[3];
fs.mkdirSync(SHOTS, { recursive: true });
const BASE = 'http://127.0.0.1:18673';
const WIDTHS = [390, 480, 768, 769, 820, 1024, 1025, 1180, 1279, 1280, 1920];
const BREAKPOINT = 1279; // <= hamburger, > masaüstü
let pass = 0;
let fail = 0;
const table = [];
function check(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + String(detail).slice(0, 400) + ']' : ''));
	}
}

(async () => {
	const b = await launch();
	try {
		const pages = {
			'arşiv': fx.archive,
			'iplik-detay': fx.quals.iplik.url,
		};
		const smoke = { 'ana-sayfa': BASE + '/', haberler: BASE + '/index.php/haberler/', sss: BASE + '/index.php/sss/' };

		/* 1) her genişlikte kapalı + (hamburger ise) açık menü geometrisi */
		for (const w of WIDTHS) {
			for (const [pname, url] of Object.entries(pages)) {
				await b.viewport(w, 900);
				await b.goto(url);
				const closed = await b.eval(PROBE);
				const expectMode = w <= BREAKPOINT ? 'hamburger' : 'desktop';
				check(pname + ' ' + w + 'px: başlık modu ' + expectMode, closed.mode === expectMode, closed.mode);
				check(pname + ' ' + w + 'px: kapalı başlık — kesişme/taşma yok', closed.problems.length === 0, closed.problems.join(' | '));
				let open = null;
				if (closed.mode === 'hamburger') {
					await b.eval("document.querySelector('.menu-toggle').click()");
					open = await b.eval(PROBE);
					check(pname + ' ' + w + 'px: menü açılır (is-open) ve açık menüde kesişme/taşma yok', open.navOpen && open.problems.length === 0, JSON.stringify(open));
					if (pname === 'arşiv' || w === 820) await b.screenshot(path.join(SHOTS, pname + '-' + w + '-acik.png'));
					await b.eval("document.querySelector('.menu-toggle').click()");
				}
				await b.screenshot(path.join(SHOTS, pname + '-' + w + '-kapali.png'));
				table.push({ page: pname, w, mode: closed.mode, closed: closed.problems.length ? 'ÇAKIŞMA' : 'yok', open: open ? (open.problems.length ? 'ÇAKIŞMA' : 'yok') : '-', hscroll: closed.problems.some((p) => /yatay taşma/.test(p)) ? 'VAR' : 'yok' });
			}
		}

		/* 2) hamburger etkileşimi (tablet 820 ve breakpoint altı 1279) */
		for (const w of [820, 1279]) {
			await b.viewport(w, 900);
			await b.goto(pages['arşiv']);
			const st = () => b.eval(`(() => { const t=document.querySelector('.menu-toggle'); const n=document.getElementById('main-nav'); const a=document.activeElement; const sub=n.querySelector('li.menu-item-has-children > .submenu, li > .submenu'); return { exp: t.getAttribute('aria-expanded'), open: n.classList.contains('is-open'), navDisplay: getComputedStyle(n).display, active: a ? (a.className||a.tagName) : '', subDisplay: sub ? getComputedStyle(sub).display : 'yok' }; })()`);
			let s = await st();
			check(w + 'px: başlangıçta aria-expanded=false, menü kapalı (display:none — erişilebilirlik ağacında yok)', s.exp === 'false' && !s.open && s.navDisplay === 'none', JSON.stringify(s));
			// klavye: Tab ile hamburger düğmesine gel, görünür odak, Enter ile aç
			let reached = false;
			for (let i = 0; i < 25 && !reached; i++) {
				await b.key('Tab', 'Tab', 9);
				reached = await b.eval("document.activeElement && document.activeElement.classList.contains('menu-toggle')");
			}
			const ring = await b.eval("(() => { const s=getComputedStyle(document.activeElement); return { v: document.activeElement.matches(':focus-visible'), o: s.outlineStyle + ' ' + s.outlineWidth, sh: s.boxShadow }; })()");
			check(w + 'px: klavye ile hamburger düğmesine ulaşılır ve odak görünür (:focus-visible + outline)', reached && ring.v && !/^none/.test(ring.o) && ring.o !== 'none 0px', JSON.stringify(ring));
			await b.key('Enter', 'Enter', 13);
			s = await st();
			check(w + 'px: Enter ile açılır, aria-expanded=true, menü görünür', s.exp === 'true' && s.open && s.navDisplay !== 'none', JSON.stringify(s));
			// alt menü: ilk nav-toggle ile aç (fare tıklaması), aria-expanded
			const subRes = await b.eval(`(() => { const btn=document.querySelector('#main-nav > ul > li > button.nav-toggle'); if(!btn) return {ok:false}; btn.click(); const sub=document.getElementById(btn.getAttribute('aria-controls')); const r=btn.getBoundingClientRect(); return { exp: btn.getAttribute('aria-expanded'), disp: getComputedStyle(sub).display, w: r.width, h: r.height, links: sub.querySelectorAll('a').length }; })()`);
			check(w + 'px: alt menü düğmesi ile açılır (aria-expanded=true, görünür, düğme >=44x44)', subRes.exp === 'true' && subRes.disp !== 'none' && subRes.w >= 44 && subRes.h >= 44 && subRes.links > 0, JSON.stringify(subRes));
			const g = await b.eval(PROBE);
			check(w + 'px: alt menü açıkken başlık bağlantıları kesişmez/taşmaz', g.problems.length === 0, g.problems.join(' | '));
			// alt menüyü dokunma ile kapat/aç (touch emülasyonu)
			await b.send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 1 });
			const pos = await b.eval("(() => { const r=document.querySelector('#main-nav > ul > li > button.nav-toggle').getBoundingClientRect(); return {x:r.left+r.width/2, y:r.top+r.height/2}; })()");
			await b.tap(pos.x, pos.y);
			const afterTap = await b.eval("document.querySelector('#main-nav > ul > li > button.nav-toggle').getAttribute('aria-expanded')");
			await b.tap(pos.x, pos.y);
			const afterTap2 = await b.eval("document.querySelector('#main-nav > ul > li > button.nav-toggle').getAttribute('aria-expanded')");
			await b.send('Emulation.setTouchEmulationEnabled', { enabled: false });
			check(w + 'px: alt menü dokunmayla kapanır ve yeniden açılır', afterTap === 'false' && afterTap2 === 'true', afterTap + '/' + afterTap2);
			// Escape (navigation.js önceliği): 1. basış yalnız açık alt menüyü kapatır ve odağı onun düğmesine verir (alt menü
			// GÖRÜNMEZ olmalı — odak düğmede kalsa bile); 2. basış hamburger menüyü kapatır, odak hamburger düğmesine döner.
			await b.key('Escape', 'Escape', 27);
			s = await st();
			const sub1 = await b.eval("document.querySelector('#main-nav > ul > li > button.nav-toggle').getAttribute('aria-expanded')");
			check(w + 'px: 1. Escape alt menüyü kapatır (aria-expanded=false VE görünmez), hamburger menü açık kalır, odak alt menü düğmesinde', sub1 === 'false' && s.subDisplay === 'none' && s.open && /nav-toggle/.test(s.active), JSON.stringify(s) + ' sub=' + sub1);
			await b.key('Escape', 'Escape', 27);
			s = await st();
			check(w + 'px: 2. Escape menüyü kapatır, aria-expanded=false, odak hamburger düğmesinde', s.exp === 'false' && !s.open && /menu-toggle/.test(s.active), JSON.stringify(s));
			// dışarı tıklama
			await b.eval("document.querySelector('.menu-toggle').click()");
			s = await st();
			const opened = s.open;
			await b.click(w - 20, 700);
			s = await st();
			check(w + 'px: dışarı tıklamayla kapanır', opened && !s.open && s.exp === 'false', JSON.stringify(s));
		}

		/* 3) masaüstü (>=1280): alt menü klavye ile açılır */
		for (const w of [1280, 1920]) {
			await b.viewport(w, 900);
			await b.goto(pages['iplik-detay']);
			const r = await b.eval(`(() => { const btn=document.querySelector('#main-nav > ul > li > button.nav-toggle'); btn.focus(); return true; })()`);
			await b.key('Enter', 'Enter', 13);
			const d = await b.eval(`(() => { const btn=document.querySelector('#main-nav > ul > li > button.nav-toggle'); const sub=document.getElementById(btn.getAttribute('aria-controls')); const r=btn.getBoundingClientRect(); return { exp: btn.getAttribute('aria-expanded'), disp: getComputedStyle(sub).display, h: r.height }; })()`);
			check(w + 'px masaüstü: alt menü klavye (Enter) ile açılır, aria-expanded=true, düğme yüksekliği >=44', r && d.exp === 'true' && d.disp !== 'none' && d.h >= 44, JSON.stringify(d));
			await b.key('Escape', 'Escape', 27);
		}

		/* 4) diğer ekranlar bozulmadı (masaüstü 1920 + tablet 820 + mobil 390) */
		for (const [pname, url] of Object.entries(smoke)) {
			for (const w of [390, 820, 1920]) {
				await b.viewport(w, 900);
				await b.goto(url);
				const g = await b.eval(PROBE);
				const h1 = await b.eval("document.querySelectorAll('h1').length");
				check(pname + ' ' + w + 'px: başlık sağlıklı, tek H1', g.problems.length === 0 && h1 === 1, g.problems.join(' | ') + ' h1=' + h1);
				if (w !== 820) await b.screenshot(path.join(SHOTS, pname + '-' + w + '.png'));
			}
		}
		// yeterlilik tasarımı: arşiv 3 sütun (1920), 2 (820), 1 (390); detay 2 sütun (1920) / 1 (820)
		const cols = async (w, url, sel) => {
			await b.viewport(w, 900);
			await b.goto(url);
			return b.eval(`getComputedStyle(document.querySelector('${sel}')).gridTemplateColumns.split(' ').length`);
		};
		check('yeterlilik arşivi 3/2/1 sütun korunur', (await cols(1920, pages['arşiv'], '.qual-list')) === 3 && (await cols(820, pages['arşiv'], '.qual-list')) === 2 && (await cols(390, pages['arşiv'], '.qual-list')) === 1);
		check('yeterlilik detayı 2/1 sütun korunur', (await cols(1920, pages['iplik-detay'], '.qual-detail-grid')) === 2 && (await cols(820, pages['iplik-detay'], '.qual-detail-grid')) === 1);
		await b.viewport(1920, 1000);
		await b.goto(pages['iplik-detay']);
		await b.screenshot(path.join(SHOTS, 'iplik-detay-1920-tam.png'), true);
		await b.goto(pages['arşiv']);
		await b.screenshot(path.join(SHOTS, 'arsiv-1920-tam.png'), true);
		check('tarayıcı konsolunda hata yok', b.consoleErrors.length === 0, b.consoleErrors.join(' | '));
	} finally {
		await b.close();
	}
	console.log('\ngenişlik | sayfa | mod | kapalı çakışma | açık çakışma | yatay taşma');
	table.forEach((r) => console.log(r.w + ' | ' + r.page + ' | ' + r.mode + ' | ' + r.closed + ' | ' + r.open + ' | ' + r.hscroll));
	console.log('\n' + pass + '/' + (pass + fail) + ' başlık geometrisi/etkileşim testi geçti.');
	process.exit(fail ? 1 : 0);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + e.message);
	process.exit(1);
});
