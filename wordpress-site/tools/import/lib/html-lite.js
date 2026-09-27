'use strict';
/**
 * Faz 12 — küçük, bağımlılıksız, deterministik HTML ayrıştırıcı (yalnız dondurulmuş statik referans sayfalarını
 * okumak için). Tam HTML5 uyumlu DEĞİLDİR ve kod ÇALIŞTIRMAZ: metni belirteçlere ayırır, ağaç kurar. Bilinmeyen adlandırılmış
 * karakter varlığında hata fırlatır (sessiz bozulma yok).
 */

const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
const RAW_TEXT = new Set(['script', 'style']);
const NAMED = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };

function decodeEntities(s) {
	return s.replace(/&(#x[0-9a-fA-F]+|#[0-9]+|[A-Za-z][A-Za-z0-9]*);/g, function (m, body) {
		if (body[0] === '#') {
			const cp = body[1] === 'x' || body[1] === 'X' ? parseInt(body.slice(2), 16) : parseInt(body.slice(1), 10);
			if (!(cp > 0 && cp <= 0x10ffff)) {
				throw new Error('geçersiz sayısal karakter varlığı: ' + m);
			}
			return String.fromCodePoint(cp);
		}
		if (Object.prototype.hasOwnProperty.call(NAMED, body)) {
			return NAMED[body];
		}
		throw new Error('bilinmeyen adlandırılmış karakter varlığı: ' + m);
	});
}

function parseAttrs(src) {
	const attrs = {};
	const re = /([^\s"'<>\/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
	let m;
	while ((m = re.exec(src))) {
		const name = m[1].toLowerCase();
		const raw = m[2] !== undefined ? m[2] : m[3] !== undefined ? m[3] : m[4] !== undefined ? m[4] : '';
		attrs[name] = decodeEntities(raw);
	}
	return attrs;
}

/** @returns {{type:'root', children:Array}} */
function parseHtml(html) {
	const root = { type: 'root', tag: '#root', attrs: {}, children: [], parent: null };
	let cur = root;
	let i = 0;
	const n = html.length;
	function pushText(t) {
		if (t === '') {
			return;
		}
		cur.children.push({ type: 'text', text: decodeEntities(t), parent: cur });
	}
	while (i < n) {
		const lt = html.indexOf('<', i);
		if (lt === -1) {
			pushText(html.slice(i));
			break;
		}
		if (lt > i) {
			pushText(html.slice(i, lt));
		}
		if (html.startsWith('<!--', lt)) {
			const end = html.indexOf('-->', lt + 4);
			if (end === -1) {
				throw new Error('kapanmamış yorum');
			}
			i = end + 3;
			continue;
		}
		if (html.startsWith('<!', lt) || html.startsWith('<?', lt)) {
			const end = html.indexOf('>', lt);
			i = end === -1 ? n : end + 1;
			continue;
		}
		const close = /^<\/([A-Za-z][A-Za-z0-9-]*)\s*>/.exec(html.slice(lt, lt + 80));
		if (close) {
			const tag = close[1].toLowerCase();
			let node = cur;
			while (node && node !== root && node.tag !== tag) {
				node = node.parent;
			}
			if (node && node !== root) {
				cur = node.parent;
			}
			i = lt + close[0].length;
			continue;
		}
		const open = /^<([A-Za-z][A-Za-z0-9-]*)((?:"[^"]*"|'[^']*'|[^'">])*?)(\/?)>/.exec(html.slice(lt, lt + 4000));
		if (!open) {
			pushText('<');
			i = lt + 1;
			continue;
		}
		const tag = open[1].toLowerCase();
		const el = { type: 'el', tag, attrs: parseAttrs(open[2]), children: [], parent: cur };
		cur.children.push(el);
		i = lt + open[0].length;
		if (RAW_TEXT.has(tag)) {
			const re = new RegExp('</' + tag + '\\s*>', 'i');
			const m = re.exec(html.slice(i));
			const end = m ? i + m.index : n;
			el.children.push({ type: 'text', text: html.slice(i, end), parent: el, raw: true });
			i = m ? end + m[0].length : n;
			continue;
		}
		if (!VOID.has(tag) && open[3] !== '/') {
			cur = el;
		}
	}
	return root;
}

function classesOf(el) {
	return el.attrs && el.attrs.class ? el.attrs.class.split(/\s+/).filter(Boolean) : [];
}

function textOf(node) {
	if (node.type === 'text') {
		return node.raw ? '' : node.text;
	}
	return (node.children || []).map(textOf).join('');
}

function walk(node, fn) {
	fn(node);
	(node.children || []).forEach((c) => walk(c, fn));
}

function findAll(node, pred, out) {
	out = out || [];
	walk(node, (x) => {
		if (x.type === 'el' && pred(x)) {
			out.push(x);
		}
	});
	return out;
}

module.exports = { parseHtml, classesOf, textOf, walk, findAll, decodeEntities, VOID };
