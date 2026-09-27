'use strict';
/**
 * Faz 12b — İÇERİK aktarımının YEREL kaynakları: (1) referans logo envanteri (onaylı canlı sayfadan bir kez alınmış,
 * wordpress-site/data/sources/reference-logos/), (2) SSS (kurumca onaylanmış tanitim-site/sss.html; YALNIZ okunur).
 * Ağ isteği YOKTUR; yalnız sabit yollar okunur (dışarıdan yol kurulmaz). Haber kaynağı (news.js) extract-source.js'te kalır.
 */
const fs = require('fs');
const path = require('path');
const { sha256Hex } = require('./hash');
const { parseHtml, classesOf, textOf } = require('./html-lite');

const REPO_ROOT = path.resolve(__dirname, '..', '..', '..', '..');
const LOGO_DIR_REL = 'wordpress-site/data/sources/reference-logos';
const LOGO_INVENTORY_REL = LOGO_DIR_REL + '/reference-logos.manifest.json';
const FAQ_SOURCE_REL = 'tanitim-site/sss.html';

const LOGO_KEYS = ['index', 'slug', 'file', 'original_url', 'original_id', 'sha256', 'bytes', 'width', 'height', 'name_status'];

function collapse(s) {
	return s.replace(/ /g, ' ').replace(/[ \t\r\n]+/g, ' ').trim();
}

/** PNG başlığından (imza + IHDR) genişlik/yükseklik; PNG değilse null. */
function pngSize(buf) {
	if (buf.length < 24 || buf.readUInt32BE(0) !== 0x89504e47 || buf.readUInt32BE(4) !== 0x0d0a1a0a || buf.toString('ascii', 12, 16) !== 'IHDR') {
		return null;
	}
	return { width: buf.readUInt32BE(16), height: buf.readUInt32BE(20) };
}

/**
 * Referans logo envanterini okur ve HER logo dosyasını (varlık, PNG, boyut, bayt, SHA-256) doğrular.
 * @returns {{repoRelativePath:string, sha256:string, data:Array, errors:string[], envelope:Object|null}}
 */
function loadReferenceLogoSource() {
	const errors = [];
	const abs = path.join(REPO_ROOT, LOGO_INVENTORY_REL);
	let raw;
	let doc = null;
	try {
		raw = fs.readFileSync(abs);
		doc = JSON.parse(raw.toString('utf8'));
	} catch (e) {
		errors.push('logo envanteri okunamadı: ' + e.message);
		return { repoRelativePath: LOGO_INVENTORY_REL, sha256: '', data: [], errors: errors, envelope: null };
	}
	if (!doc || !Array.isArray(doc.logos)) {
		errors.push('logo envanteri: logos dizisi yok');
		return { repoRelativePath: LOGO_INVENTORY_REL, sha256: sha256Hex(raw), data: [], errors: errors, envelope: doc };
	}
	const seenSha = new Set();
	const seenFile = new Set();
	doc.logos.forEach(function (l, i) {
		const w = 'logo[' + i + ']';
		if (!l || typeof l !== 'object' || JSON.stringify(Object.keys(l)) !== JSON.stringify(LOGO_KEYS)) {
			errors.push(w + ': alan kümesi/sırası geçersiz');
			return;
		}
		if (l.index !== i || l.slug !== 'referans-' + String(i + 1).padStart(2, '0')) {
			errors.push(w + ': index/slug sıra dışı');
		}
		if (typeof l.file !== 'string' || l.file !== LOGO_DIR_REL + '/ref-' + String(i + 1).padStart(2, '0') + '.png') {
			errors.push(w + ': dosya yolu beklenen kalıpta değil');
			return;
		}
		if (l.name_status !== 'unverified') {
			errors.push(w + ': name_status "unverified" olmalı (firma adı görselden tahmin edilmez)');
		}
		if (!/^https:\/\/mavibelge\.com\.tr\/wp-content\/uploads\/sls-wp-uploads\/images\/\d+\/ori_\d+\.png$/.test(String(l.original_url))) {
			errors.push(w + ': original_url onaylı kaynak kalıbında değil');
		}
		let buf;
		try {
			buf = fs.readFileSync(path.join(REPO_ROOT, l.file));
		} catch (e) {
			errors.push(w + ': logo dosyası yok');
			return;
		}
		if (sha256Hex(buf) !== l.sha256 || buf.length !== l.bytes) {
			errors.push(w + ': logo SHA-256/bayt künyeyle uyuşmuyor');
		}
		const size = pngSize(buf);
		if (size === null || size.width !== l.width || size.height !== l.height || l.width < 1 || l.height < 1) {
			errors.push(w + ': logo geçerli PNG değil veya boyut künyeyle uyuşmuyor');
		}
		if (seenSha.has(l.sha256)) {
			errors.push(w + ': aynı logo tekrar ediyor (SHA-256)');
		}
		seenSha.add(l.sha256);
		if (seenFile.has(l.file)) {
			errors.push(w + ': dosya tekrar ediyor');
		}
		seenFile.add(l.file);
	});
	return { repoRelativePath: LOGO_INVENTORY_REL, sha256: sha256Hex(raw), data: doc.logos, errors: errors, envelope: doc };
}

/**
 * tanitim-site/sss.html (YALNIZ okunur) içindeki soru-cevap çiftlerini çıkarır. Cevap düz metindir (satır içi işaretleme yok).
 * @returns {{repoRelativePath:string, sha256:string, data:Array<{question:string, answer:string}>, errors:string[]}}
 */
function loadFaqSource() {
	const errors = [];
	const buf = fs.readFileSync(path.join(REPO_ROOT, FAQ_SOURCE_REL));
	const html = buf.toString('utf8');
	const start = html.indexOf('<main');
	const end = html.indexOf('</main>');
	if (start === -1 || end === -1) {
		errors.push('sss.html: <main> yok');
		return { repoRelativePath: FAQ_SOURCE_REL, sha256: sha256Hex(buf), data: [], errors: errors };
	}
	const root = parseHtml(html.slice(start, end + 7));
	const items = [];
	(function walk(n) {
		(n.children || []).forEach(function (c) {
			if (c.type !== 'el') {
				return;
			}
			if (classesOf(c).includes('accordion-item')) {
				items.push(c);
				return;
			}
			walk(c);
		});
	})(root);
	const data = [];
	items.forEach(function (item, i) {
		let question = null;
		let answer = null;
		(function walk(n) {
			(n.children || []).forEach(function (c) {
				if (c.type !== 'el') {
					return;
				}
				if (c.tag === 'button' && classesOf(c).includes('accordion-trigger')) {
					question = collapse(c.children.filter((x) => !(x.type === 'el' && classesOf(x).includes('icon'))).map(textOf).join(''));
					return;
				}
				if (classesOf(c).includes('accordion-panel')) {
					if (c.children.some((x) => x.type === 'el' && x.tag !== 'p')) {
						errors.push('sss[' + i + ']: cevap yalnız <p> içerebilir');
					}
					answer = collapse(textOf(c));
					return;
				}
				walk(c);
			});
		})(item);
		if (!question || !answer) {
			errors.push('sss[' + i + ']: soru/cevap çıkarılamadı');
			return;
		}
		data.push({ question: question, answer: answer });
	});
	return { repoRelativePath: FAQ_SOURCE_REL, sha256: sha256Hex(buf), data: data, errors: errors };
}

module.exports = { loadReferenceLogoSource, loadFaqSource, pngSize, LOGO_DIR_REL, LOGO_INVENTORY_REL, FAQ_SOURCE_REL, REPO_ROOT };
