'use strict';
/**
 * Faz 12 — statik sayfa ana içeriğini WordPress editör içeriğine uygun, KAPALI izin listeli, kanonik semantik HTML'e
 * dönüştürür ve aynı izin listesini `sanitizeCheck()` ile denetler (PHP tarafındaki
 * MaviBelge_Core_Import_Record_Validator::page_content_errors() bunun birebir aynasıdır).
 *
 * Kanonik biçim: bloklar "\n" ile ayrılır; yalnız p, h2, h3, h4, ul, ol, li, a[href], strong, em, br (`<br />`).
 * Header/footer/gezinme/CTA düğmesi/script/form/görsel/demo metni İÇERİĞE KOPYALANMAZ (tema veya kurum kararı).
 */
const { parseHtml, classesOf, textOf } = require('./html-lite');
const { linkRouteFor } = require('./page-inventory');

const ALLOWED_TAGS = new Set(['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'strong', 'em', 'br']);
const BLOCK_TOP = new Set(['p', 'h2', 'h3', 'h4', 'ul', 'ol']);
const INLINE = new Set(['a', 'strong', 'em', 'br']);
const DROP_TAGS = new Set(['script', 'style', 'noscript', 'form', 'svg', 'img', 'iframe', 'button', 'input', 'select', 'textarea', 'label', 'nav', 'canvas', 'table', 'picture', 'video', 'audio', 'object', 'embed', 'fieldset']);
const DROP_CLASSES = new Set(['demo-notice', 'stat-row', 'stat', 'filter-bar', 'form-card', 'form-field', 'ref-slider', 'ref-slider-viewport', 'ref-track', 'ref-grid', 'accordion-item', 'accordion-panel', 'location-card', 'map-placeholder', 'doc-card', 'fee-cards', 'table-wrap', 'step-indicator', 'file-drop', 'step-num', 'footer-social-list', 'directions-link', 'section-title', 'ref-demo-note', 'visually-hidden']);
const PLACEHOLDER_RE = /tanıtım sürümü|tanıtım amaçlı|tanıtım sitesi|demo|yazılım aşamasında|yayın öncesi|bilgi güncellenecektir|eklenecektir|sağlanacak|yayınlanacaktır|netleştirilecek|kurum tarafından doğrulanarak|entegrasyon yöntemine göre|temsili olarak/i;

function escText(s) {
	return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function escAttr(s) {
	return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function collapse(s) {
	return s.replace(/ /g, ' ').replace(/[ \t\r\n]+/g, ' ');
}

/** Bağlantı normalizasyonu: yalnız güvenli hedefler; aksi halde null (bağlantı düşer, metin kalır). Bilinmeyen *.html hedefi HATA. */
function normalizeHref(raw, ctx) {
	const href = String(raw || '').trim();
	if (href === '' || href === '#') {
		ctx.dropped.push('boş/# bağlantı');
		return null;
	}
	if (/^(tel:\+?[0-9]+|mailto:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})$/.test(href)) {
		return href;
	}
	if (/^https:\/\/[^\s"<>]+$/.test(href)) {
		return href;
	}
	const m = /^([a-z0-9-]+\.html)(#[a-z0-9-]+)?$/.exec(href);
	if (m) {
		const route = linkRouteFor(m[1]);
		if (route === null) {
			throw new Error('bağlantı hedefi eşlenemedi (' + href + '): page-template-map.md/envanterde karşılığı yok; açık karar gerekir');
		}
		return route + (m[2] || '');
	}
	ctx.dropped.push('güvensiz/desteklenmeyen bağlantı: ' + href);
	return null;
}

function inline(node, ctx) {
	if (node.type === 'text') {
		return node.raw ? '' : escText(collapse(node.text));
	}
	const tag = node.tag;
	const classes = classesOf(node);
	if (classes.some((c) => DROP_CLASSES.has(c)) || DROP_TAGS.has(tag)) {
		ctx.dropped.push('<' + tag + (classes.length ? '.' + classes[0] : '') + '>');
		return '';
	}
	if (tag === 'a') {
		if (classes.includes('btn')) {
			ctx.dropped.push('CTA düğmesi: ' + collapse(textOf(node)).trim());
			return '';
		}
		const inner = node.children.map((c) => inline(c, ctx)).join('');
		const href = normalizeHref(node.attrs.href, ctx);
		return href === null ? inner : '<a href="' + escAttr(href) + '">' + inner + '</a>';
	}
	if (tag === 'strong' || tag === 'b') {
		return '<strong>' + node.children.map((c) => inline(c, ctx)).join('') + '</strong>';
	}
	if (tag === 'em' || tag === 'i') {
		return '<em>' + node.children.map((c) => inline(c, ctx)).join('') + '</em>';
	}
	if (tag === 'br') {
		return '<br />';
	}
	if (tag === 'span' || tag === 'small' || tag === 'mark') {
		return node.children.map((c) => inline(c, ctx)).join('');
	}
	throw new Error('satır içi bağlamda desteklenmeyen öğe <' + tag + '>; açık kural gerekir');
}

function tidyInline(s) {
	return s.replace(/ *(<br \/>) */g, '$1').replace(/ {2,}/g, ' ').replace(/^ +| +$/g, '').replace(/ +(<\/(?:a|strong|em)>)/g, '$1 ').replace(/(<(?:a href="[^"]*"|strong|em)>) +/g, ' $1').replace(/ {2,}/g, ' ').replace(/^ +| +$/g, '');
}

function isEmptyInline(s) {
	return s.replace(/<[^>]*>/g, '').trim() === '';
}

function hasElementChildren(node) {
	return node.children.some((c) => c.type === 'el');
}

/** Düz metin paragrafını cümlelere bölüp yer-tutucu/demo cümlelerini düşürür (yalnız satır içi öğe içermeyen p). */
function filterSentences(text, ctx) {
	// Cümle sınırı: . ! ? sonrası boşluk. Bölme kayıpsızdır (parçalar tek boşlukla birleştirilir); "02.05.2016" gibi sayılar bölünmez.
	if (ctx.keep) {
		return text.trim();
	}
	const parts = text.split(/(?<=[.!?])\s+/);
	const kept = [];
	parts.forEach(function (s) {
		if (PLACEHOLDER_RE.test(s)) {
			ctx.dropped.push('yer-tutucu/demo cümlesi: ' + s.trim().slice(0, 80));
		} else {
			kept.push(s);
		}
	});
	return kept.join(' ').trim();
}

/** İçeriksiz kalan başlıkları (sonda veya başka başlıktan hemen önce) düşürür. */
function dropOrphanHeadings(out, ctx) {
	const isH = (b) => /^<h[2-4]>/.test(b);
	let changed = true;
	while (changed) {
		changed = false;
		for (let i = out.length - 1; i >= 0; i--) {
			if (isH(out[i]) && (i === out.length - 1 || isH(out[i + 1]))) {
				ctx.dropped.push('içeriksiz kalan başlık: ' + out[i].replace(/<[^>]*>/g, ''));
				out.splice(i, 1);
				changed = true;
				break;
			}
		}
	}
}

function blocks(node, ctx, out) {
	if (node.type === 'text') {
		const t = collapse(node.text).trim();
		if (t !== '' && !node.raw) {
			out.push('<p>' + escText(t) + '</p>');
		}
		return;
	}
	const tag = node.tag;
	const classes = classesOf(node);
	if (DROP_TAGS.has(tag) || classes.some((c) => DROP_CLASSES.has(c))) {
		ctx.dropped.push('<' + tag + (classes.length ? '.' + classes.join('.') : '') + '>');
		return;
	}
	if (tag === 'h1') {
		return;
	}
	if (tag === 'hr') {
		ctx.dropped.push('<hr> ayırıcı');
		return;
	}
	if (tag === 'h2' || tag === 'h3' || tag === 'h4') {
		const inner = tidyInline(node.children.map((c) => inline(c, ctx)).join(''));
		if (!isEmptyInline(inner)) {
			out.push('<' + tag + '>' + inner + '</' + tag + '>');
		}
		return;
	}
	if (tag === 'h5' || tag === 'h6') {
		throw new Error('h5/h6 desteklenmiyor');
	}
	if (tag === 'p') {
		if (classes.includes('hint')) {
			ctx.dropped.push('ipucu paragrafı: ' + collapse(textOf(node)).trim().slice(0, 80));
			return;
		}
		let inner;
		if (!hasElementChildren(node)) {
			inner = escText(filterSentences(collapse(textOf(node)), ctx));
		} else {
			const whole = collapse(textOf(node));
			if (!ctx.keep && PLACEHOLDER_RE.test(whole)) {
				ctx.dropped.push('yer-tutucu/demo paragrafı: ' + whole.trim().slice(0, 80));
				return;
			}
			inner = node.children.map((c) => inline(c, ctx)).join('');
		}
		inner = tidyInline(inner);
		if (!isEmptyInline(inner)) {
			out.push('<p>' + inner + '</p>');
		}
		return;
	}
	if (tag === 'ul' || tag === 'ol') {
		const items = [];
		const bullets = [];
		let droppedItem = false;
		node.children.filter((c) => c.type === 'el' && c.tag === 'li').forEach(function (li) {
			const bulletEl = li.children.find((c) => c.type === 'el' && classesOf(c).includes('bullet'));
			const bulletText = bulletEl ? collapse(textOf(bulletEl)).trim() : '';
			const rest = li.children.filter((c) => c !== bulletEl);
			const whole = collapse(rest.map(textOf).join(''));
			if (!ctx.keep && PLACEHOLDER_RE.test(whole)) {
				ctx.dropped.push('yer-tutucu/demo maddesi: ' + whole.trim().slice(0, 80));
				droppedItem = true;
				return;
			}
			const inner = tidyInline(rest.map((c) => inline(c, ctx)).join(''));
			if (!isEmptyInline(inner)) {
				items.push(inner);
				bullets.push(bulletText);
			}
		});
		if (items.length === 0) {
			return;
		}
		const numbered = !droppedItem && bullets.every((b, i) => b === String(i + 1));
		const listTag = tag === 'ol' || numbered ? 'ol' : 'ul';
		out.push('<' + listTag + '>\n' + items.map((x) => '<li>' + x + '</li>').join('\n') + '\n</' + listTag + '>');
		return;
	}
	if (tag === 'a') {
		const inner = tidyInline(inline(node, ctx));
		if (!isEmptyInline(inner)) {
			out.push('<p>' + inner + '</p>');
		}
		return;
	}
	if (tag === 'div' || tag === 'section' || tag === 'article' || tag === 'main' || tag === 'aside') {
		node.children.forEach((c) => blocks(c, ctx, out));
		return;
	}
	if (tag === 'strong' || tag === 'em' || tag === 'span' || tag === 'b' || tag === 'i') {
		const inner = tidyInline(inline(node, ctx));
		if (!isEmptyInline(inner)) {
			out.push('<p>' + inner + '</p>');
		}
		return;
	}
	throw new Error('blok bağlamında desteklenmeyen öğe <' + tag + '>; açık kural gerekir');
}

/**
 * @param {string} html Statik sayfanın tam HTML'i
 * @param {{mode:string}} cfg
 * @returns {{title:string, excerpt:string, content:string, dropped:string[]}}
 */
function convertPage(html, cfg) {
	const start = html.indexOf('<main');
	const end = html.indexOf('</main>');
	if (start === -1 || end === -1 || end < start) {
		throw new Error('<main> bulunamadı');
	}
	const root = parseHtml(html.slice(start, end + 7));
	let main = null;
	(function find(n) {
		if (main) {
			return;
		}
		if (n.type === 'el' && n.tag === 'main') {
			main = n;
			return;
		}
		(n.children || []).forEach(find);
	})(root);
	if (!main) {
		throw new Error('<main> bulunamadı');
	}
	const ctx = { dropped: [] };
	const kids = main.children.filter((c) => !(c.type === 'text' && c.text.trim() === ''));
	const heroIdx = kids.findIndex((c) => c.type === 'el' && c.tag === 'section' && classesOf(c).includes('page-hero'));
	if (heroIdx !== 0) {
		throw new Error('<main> içinde ilk öğe .page-hero değil');
	}
	const hero = kids[0];
	let title = '';
	let excerpt = '';
	(function scan(n) {
		(n.children || []).forEach(function (c) {
			if (c.type !== 'el') {
				return;
			}
			if (c.tag === 'h1' && title === '') {
				title = collapse(textOf(c)).trim();
			}
			if (c.tag === 'p' && excerpt === '' && !classesOf(c).length) {
				excerpt = collapse(textOf(c)).trim();
			}
			scan(c);
		});
	})(hero);
	if (title === '') {
		throw new Error('<h1> bulunamadı');
	}
	if (PLACEHOLDER_RE.test(excerpt)) {
		ctx.dropped.push('hero özeti yer-tutucu: ' + excerpt.slice(0, 60));
		excerpt = '';
	}
	if (/[<>&]/.test(excerpt)) {
		excerpt = excerpt.replace(/&/g, 've').replace(/[<>]/g, '');
	}
	const out = [];
	if (cfg.mode === 'extract') {
		kids.slice(1).forEach((c) => blocks(c, ctx, out));
		dropOrphanHeadings(out, ctx);
	}
	if (cfg.excerpt === false) {
		excerpt = '';
	}
	return { title, excerpt, content: out.join('\n'), dropped: ctx.dropped };
}

/**
 * Onaylı kaynak gövde parçasını (kurum/kullanıcı onaylı, yeniden yazılmaz) kapalı izin listeli kanonik HTML'e dönüştürür.
 * Yer-tutucu/demo süzgeci UYGULANMAZ (metin olduğu gibi kalır); <hr> ve boş paragraflar düşer; izin dışı öğe hata fırlatır.
 *
 * @param {string} fragment Gövde parçası (ör. <p>…</p>
<ol>…</ol>)
 * @returns {{content:string, dropped:string[]}}
 */
function convertApprovedFragment(fragment) {
	const root = parseHtml('<div>' + fragment + '</div>');
	const ctx = { dropped: [], keep: true };
	const out = [];
	root.children.forEach((c) => blocks(c, ctx, out));
	return { content: out.join('\n'), dropped: ctx.dropped };
}

/** Kapalı izin listesi denetimi (kanonik biçim). @returns {string[]} hata listesi (boş = geçerli) */
function sanitizeCheck(html) {
	const errors = [];
	if (typeof html !== 'string') {
		return ['içerik string değil'];
	}
	if (html === '') {
		return errors;
	}
	if (/<!|<\?/.test(html)) {
		errors.push('yorum/doctype/işleme talimatı yasak');
	}
	const stack = [];
	const re = /<(\/?)([A-Za-z][A-Za-z0-9]*)((?:[^<>"]|"[^"]*")*)>|<|>|&[^;\s]*;?/g;
	let last = 0;
	let m;
	function textCheck(seg, where) {
		if (seg === '') {
			return;
		}
		const inInline = stack.length > 0 && ['p', 'h2', 'h3', 'h4', 'li', 'a', 'strong', 'em'].includes(stack[stack.length - 1]);
		if (!inInline && seg !== '\n') {
			errors.push('blok düzeyinde beklenmeyen metin (' + where + ')');
		}
		if (inInline && /\n/.test(seg)) {
			errors.push('satır içi bağlamda satır sonu yasak');
		}
	}
	while ((m = re.exec(html))) {
		textCheck(html.slice(last, m.index), 'metin');
		last = re.lastIndex;
		const whole = m[0];
		if (whole === '<' || whole === '>') {
			errors.push('kaçışsız ' + whole + ' karakteri');
			continue;
		}
		if (whole[0] === '&') {
			if (!/^&(amp|lt|gt|quot|#39);$/.test(whole)) {
				errors.push('izinsiz karakter varlığı: ' + whole);
			}
			const inInline = stack.length > 0;
			if (!inInline) {
				errors.push('blok düzeyinde varlık');
			}
			continue;
		}
		const closing = m[1] === '/';
		const rawTag = m[2];
		const tag = rawTag.toLowerCase();
		if (rawTag !== tag) {
			errors.push('etiket adı küçük harf olmalı: <' + rawTag + '>');
			continue;
		}
		if (!ALLOWED_TAGS.has(tag)) {
			errors.push('izinsiz etiket: <' + tag + '>');
			continue;
		}
		const attrSrc = m[3];
		if (closing) {
			if (attrSrc.trim() !== '') {
				errors.push('kapanış etiketinde nitelik');
			}
			if (stack.length === 0 || stack[stack.length - 1] !== tag) {
				errors.push('kapanış etiketi eşleşmiyor: </' + tag + '>');
			} else {
				stack.pop();
			}
			continue;
		}
		if (tag === 'br') {
			if (attrSrc !== ' /') {
				errors.push('<br /> kanonik biçimde olmalı');
			}
			if (stack.length === 0 || !['p', 'h2', 'h3', 'h4', 'li', 'strong', 'em'].includes(stack[stack.length - 1])) {
				errors.push('<br /> bu bağlamda yasak');
			}
			continue;
		}
		if (tag === 'a') {
			const am = /^ href="([^"]*)"$/.exec(attrSrc);
			if (!am) {
				errors.push('<a> yalnız href niteliği taşıyabilir');
			} else {
				const href = am[1].replace(/&amp;/g, '&');
				const ok = /^\/[a-z0-9\/_-]*(#[a-z0-9-]+)?$/.test(href)
					|| /^https:\/\/[A-Za-z0-9.-]+(:[0-9]+)?(\/[^\s"<>]*)?$/.test(href)
					|| /^tel:\+?[0-9]+$/.test(href)
					|| /^mailto:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(href);
				if (!ok || /^\/\//.test(href)) {
					errors.push('güvensiz bağlantı hedefi: ' + href);
				}
			}
			if (stack.includes('a')) {
				errors.push('iç içe <a> yasak');
			}
			if (stack.length === 0 || !['p', 'h2', 'h3', 'h4', 'li', 'strong', 'em'].includes(stack[stack.length - 1])) {
				errors.push('<a> bu bağlamda yasak');
			}
		} else if (attrSrc.trim() !== '') {
			errors.push('<' + tag + '> nitelik taşıyamaz');
		}
		const parent = stack.length ? stack[stack.length - 1] : null;
		if (BLOCK_TOP.has(tag) && parent !== null) {
			errors.push('<' + tag + '> yalnız üst düzeyde olabilir');
		}
		if (tag === 'li' && parent !== 'ul' && parent !== 'ol') {
			errors.push('<li> yalnız liste içinde');
		}
		if ((tag === 'strong' || tag === 'em') && (parent === null || parent === 'ul' || parent === 'ol')) {
			errors.push('<' + tag + '> bu bağlamda yasak');
		}
		stack.push(tag);
	}
	textCheck(html.slice(last), 'son');
	if (stack.length) {
		errors.push('kapanmamış etiket: <' + stack[stack.length - 1] + '>');
	}
	return Array.from(new Set(errors));
}

module.exports = { convertPage, convertApprovedFragment, sanitizeCheck, PLACEHOLDER_RE, ALLOWED_TAGS };
