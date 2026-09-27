'use strict';
/**
 * Tarayıcı içinde değerlendirilen başlık geometrisi ölçümü (getBoundingClientRect). Döndürdüğü nesne:
 *  mode: 'hamburger' | 'desktop'; problems: string[] (boşsa başlık sağlıklı).
 * Ölçülen: görünür başlık kardeşleri (marka, menü, güven logoları, CTA, hamburger) kesişmez; masaüstü menüsünde üst seviye her
 * etiket kendi kutusuna sığar ve etiketler birbirine binmez; hamburger modunda KAPALI menü görünmez ve erişilebilirlik ağacından
 * düşer (display:none); hiçbir görünür başlık öğesi görüntü alanı dışına taşmaz; belge yatay kaydırma üretmez.
 */
const PROBE = `(() => {
	const vw = document.documentElement.clientWidth;
	const vis = (el) => { if (!el) return false; const s = getComputedStyle(el); const r = el.getBoundingClientRect(); return s.display !== 'none' && s.visibility !== 'hidden' && r.width > 0 && r.height > 0; };
	const R = (el) => { const r = el.getBoundingClientRect(); return { l: r.left, r: r.right, t: r.top, b: r.bottom, w: r.width, h: r.height }; };
	const hit = (a, b) => a.l < b.r - 0.5 && b.l < a.r - 0.5 && a.t < b.b - 0.5 && b.t < a.b - 0.5;
	const name = (el) => (el.id ? '#' + el.id : '') + '.' + String(el.className || el.tagName).trim().split(/\\s+/).join('.');
	const hdr = document.querySelector('.header-main');
	const problems = [];
	if (!hdr) return { mode: 'none', problems: ['.header-main yok'] };
	const toggle = document.querySelector('.menu-toggle');
	const nav = document.getElementById('main-nav');
	const mode = vis(toggle) ? 'hamburger' : 'desktop';
	const navOpen = !!(nav && nav.classList.contains('is-open'));
	const kids = [...hdr.children].filter(vis).filter((k) => !(mode === 'hamburger' && k === nav));
	for (let i = 0; i < kids.length; i++) for (let j = i + 1; j < kids.length; j++) {
		if (hit(R(kids[i]), R(kids[j]))) problems.push('kesişme: ' + name(kids[i]) + ' × ' + name(kids[j]));
	}
	const items = [...hdr.querySelectorAll('.brand-logo img, .trust-logo-item, .trust-code, .header-cta, .menu-toggle')].filter(vis);
	items.forEach((el) => { const r = R(el); if (r.r > vw + 0.5 || r.l < -0.5) problems.push('görüntü alanı dışında: ' + name(el) + ' [' + Math.round(r.l) + ',' + Math.round(r.r) + '] vw=' + vw); });
	const trustItems = [...hdr.querySelectorAll('.trust-logo-item')].filter(vis);
	for (let i = 0; i < trustItems.length; i++) for (let j = i + 1; j < trustItems.length; j++) if (hit(R(trustItems[i]), R(trustItems[j]))) problems.push('güven logoları kesişiyor');
	trustItems.forEach((t) => { const img = t.querySelector('img'); if (img && img.getBoundingClientRect().height < 32) problems.push('güven logosu çok küçük: ' + Math.round(img.getBoundingClientRect().height) + 'px'); });
	const labels = nav ? [...nav.querySelectorAll(':scope > ul > li > a, :scope > ul > li > .nav-parent-link, :scope > ul > li > button.nav-toggle')].filter(vis) : [];
	if (mode === 'desktop') {
		if (!vis(nav)) problems.push('masaüstü modunda menü görünmüyor');
		labels.forEach((a) => { if (a.scrollWidth > a.clientWidth + 1) problems.push('etiket kutusuna sığmıyor: "' + a.textContent.trim() + '" ' + a.scrollWidth + '>' + a.clientWidth); });
		const lis = nav ? [...nav.querySelectorAll(':scope > ul > li')].filter(vis) : [];
		for (let i = 1; i < lis.length; i++) if (hit(R(lis[i - 1]), R(lis[i]))) problems.push('üst menü öğeleri kesişiyor: ' + i);
		if (nav) { const ul = nav.querySelector(':scope > ul'); if (ul && ul.scrollWidth > nav.clientWidth + 1) problems.push('menü listesi menü alanını aşıyor: ' + ul.scrollWidth + '>' + nav.clientWidth); }
	} else {
		if (!navOpen && vis(nav)) problems.push('hamburger modunda KAPALI menü görünür');
		if (!navOpen && nav && getComputedStyle(nav).display !== 'none') problems.push('kapalı menü erişilebilirlik ağacında (display!=none)');
		const tr = R(toggle);
		if (tr.w < 44 || tr.h < 44) problems.push('hamburger dokunma alanı < 44×44: ' + Math.round(tr.w) + '×' + Math.round(tr.h));
		if (navOpen) {
			const rows = labels.map(R);
			for (let i = 0; i < rows.length; i++) for (let j = i + 1; j < rows.length; j++) if (hit(rows[i], rows[j])) problems.push('açık menüde bağlantılar kesişiyor: ' + labels[i].textContent.trim() + ' × ' + labels[j].textContent.trim());
			labels.forEach((a) => { const r = R(a); if (r.r > vw + 0.5) problems.push('açık menü öğesi taşıyor: ' + a.textContent.trim()); if (r.h < 44) problems.push('açık menü öğesi < 44px: ' + a.textContent.trim()); });
			[...nav.querySelectorAll(':scope > ul > li')].forEach((li) => { const link = li.querySelector(':scope > .nav-parent-link'); const tg = li.querySelector(':scope > button.nav-toggle'); if (link && tg && vis(link) && vis(tg)) { const a = R(link); const t = R(tg); if (t.t >= a.b - 0.5 || t.b <= a.t + 0.5) problems.push('alt menü düğmesi üst bağlantıyla aynı satırda değil: ' + link.textContent.trim()); } });
		}
	}
	if (document.documentElement.scrollWidth > vw + 0.5) problems.push('yatay taşma: scrollWidth=' + document.documentElement.scrollWidth + ' > ' + vw);
	return { mode, navOpen, vw, problems };
})()`;

module.exports = { PROBE };
