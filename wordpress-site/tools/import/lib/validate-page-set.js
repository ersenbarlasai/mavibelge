'use strict';
/**
 * Faz 12 — `pages.manifest.json` TEK doğrulayıcısı (üretici yazmadan ÖNCE, doğrulayıcı disk üzerinde çağırır) ve envanterin
 * bağımsız kaynaklarla çapraz denetimi. Ağ/WordPress yok.
 */
const fs = require('fs');
const path = require('path');
const { sha256Hex } = require('./hash');
const { PAGE_INVENTORY, PAGE_EXPECTED, PENDING_CODES, PUBLISH_REQUIRES } = require('./page-inventory');
const { sanitizeCheck, convertApprovedFragment } = require('./page-content');

const REPO_ROOT = path.resolve(__dirname, '..', '..', '..', '..');
const STATIC_DIR = path.join(REPO_ROOT, 'tanitim-site');
const MAP_PATH = path.join(REPO_ROOT, 'wordpress-site', 'docs', 'page-template-map.md');
const LAYOUTS_PATH = path.join(REPO_ROOT, 'wordpress-site', 'wp-content', 'themes', 'mavibelge', 'inc', 'page-layouts.php');

const APPROVED_DIR_REL = 'wordpress-site/data/sources/approved';
const APPROVED_MANIFEST = path.join(REPO_ROOT, APPROVED_DIR_REL, 'approved-sources.manifest.json');

/** Onaylı kaynak künyesini yükler; hata errors listesine yazılır. @returns {{bySlug: Object}} */
function loadApprovedSources(errors) {
	const out = { bySlug: {} };
	let doc;
	try {
		doc = JSON.parse(fs.readFileSync(APPROVED_MANIFEST, 'utf8'));
	} catch (e) {
		errors.push('approved-sources.manifest.json okunamadı: ' + e.message);
		return out;
	}
	if (!doc || !Array.isArray(doc.sources)) {
		errors.push('approved-sources.manifest.json: sources dizisi yok');
		return out;
	}
	doc.sources.forEach(function (e) {
		if (!e || typeof e.slug !== 'string' || out.bySlug[e.slug]) {
			errors.push('approved-sources.manifest.json: geçersiz/tekrarlı slug');
			return;
		}
		if (e.fragment_file !== APPROVED_DIR_REL + '/' + e.slug + '.html') {
			errors.push(e.slug + ': fragment_file beklenen yolda değil');
			return;
		}
		let actual = null;
		try {
			actual = fileSha(e.fragment_file);
		} catch (err) {
			errors.push(e.slug + ': fragment dosyası okunamadı');
			return;
		}
		if (actual !== e.fragment_sha256) {
			errors.push(e.slug + ': fragment_sha256 künyedekiyle uyuşmuyor');
		}
		if ('string' !== typeof e.source_url || !/^https:\/\/[^\s"<>]+$/.test(e.source_url) || !/^\d{4}-\d{2}-\d{2}$/.test(String(e.fetched_on))) {
			errors.push(e.slug + ': source_url/fetched_on geçersiz');
		}
		if (e.kind !== 'fetched' && e.kind !== 'local_authored') {
			errors.push(e.slug + ': kind geçersiz');
		}
		if (e.cta_url !== null && e.cta_url !== e.source_url) {
			errors.push(e.slug + ': cta_url onaylı kaynak adresinin birebir kopyası olmalı');
		}
		out.bySlug[e.slug] = e;
	});
	return out;
}

const SCHEMA_VERSION = '2.0.0';
const RECORD_KEYS = ['schema_version', 'source_key', 'source_index', 'slug', 'title', 'content', 'excerpt', 'parent_source_key', 'menu_order', 'page_template', 'post_status', 'layout', 'content_sha256', 'pending_decisions', 'publish_hold', 'publish_requires', 'source'];
const TOP_KEYS = ['schema_version', 'record_type', 'count', 'source', 'notes', 'records'];
const SLUG_RE = /^[a-z0-9]+(-[a-z0-9]+)*$/;
const NON_PAGE_STATIC = ['index', '404', 'meslekler', 'sektor', 'yeterlilik', 'haberler', 'duyurular', 'haber-detay', 'dokumanlar'];

function fileSha(rel) {
	return sha256Hex(fs.readFileSync(path.join(REPO_ROOT, rel)));
}

/** Envanter özeti: tüm kayıt kaynak dosyalarının sıralı özeti (zarf source.sha256). */
function inventoryDigest(records) {
	return sha256Hex(records.map((r) => r.slug + ':' + r.source.sha256 + '\n').join(''));
}

/** @returns {string[]} */
function crossCheckInventory() {
	const errors = [];
	if (PAGE_INVENTORY.length !== PAGE_EXPECTED) {
		errors.push('envanter ' + PAGE_INVENTORY.length + ' kayıt, ' + PAGE_EXPECTED + ' olmalı');
	}
	// (a) statik dizin: 41 dosya = 32 envanter + 9 page-dışı rota
	const staticSlugs = fs.readdirSync(STATIC_DIR).filter((f) => /\.html$/.test(f)).map((f) => f.replace(/\.html$/, '')).sort();
	const expectedStatic = PAGE_INVENTORY.map((p) => p.slug).concat(NON_PAGE_STATIC).sort();
	if (JSON.stringify(staticSlugs) !== JSON.stringify(expectedStatic)) {
		errors.push('tanitim-site/*.html dosya kümesi envanter + page-dışı rotalarla eşleşmiyor: ' + staticSlugs.filter((s) => !expectedStatic.includes(s)).concat(expectedStatic.filter((s) => !staticSlugs.includes(s))).join(', '));
	}
	PAGE_INVENTORY.forEach(function (p) {
		if (p.file !== p.slug + '.html') {
			errors.push(p.slug + ': file adı slug ile tutarsız');
		}
	});
	// (b) page-template-map.md
	const md = fs.readFileSync(MAP_PATH, 'utf8').replace(/\r/g, '');
	const mapRows = [];
	md.split('\n').forEach(function (line) {
		const m = /^\| (\d+) \| `([^`]+)` \| ([^|]+) \| ([^|]+) \| ([^|]+) \| ([^|]+) \| /.exec(line);
		if (!m) {
			return;
		}
		const tpl = m[6].trim();
		const tm = /^`(page(?:-([a-z-]+))?\.php)`(?: \(layout: ([a-z-]+)\))?/.exec(tpl);
		if (!tm) {
			return;
		}
		const file = m[2].replace(/`/g, '').replace(/\?.*$/, '');
		const slug = file.replace(/\.html$/, '');
		const layout = tm[2] ? 'cpt-page' : tm[3];
		mapRows.push({ slug, title: m[3].trim().replace(/ \(Demo\)$/, ''), layout });
	});
	if (mapRows.length !== PAGE_EXPECTED) {
		errors.push('page-template-map.md tablosunda ' + mapRows.length + ' page satırı var, ' + PAGE_EXPECTED + ' olmalı');
	}
	if (JSON.stringify(mapRows) !== JSON.stringify(PAGE_INVENTORY.map((p) => ({ slug: p.slug, title: p.title, layout: p.layout })))) {
		errors.push('envanter (slug/başlık/layout/sıra) page-template-map.md ile birebir eşleşmiyor');
	}
	// (c) tema page-layouts.php
	const php = fs.readFileSync(LAYOUTS_PATH, 'utf8');
	const grab = (name) => {
		const m = new RegExp('\\$' + name + '\\s*=\\s*array\\(([^)]*)\\)').exec(php);
		return m ? (m[1].match(/'([^']+)'/g) || []).map((s) => s.replace(/'/g, '')).sort() : null;
	};
	const hub = PAGE_INVENTORY.filter((p) => p.layout === 'hub').map((p) => p.slug).sort();
	const form = PAGE_INVENTORY.filter((p) => p.layout === 'form-disabled').map((p) => p.slug).sort();
	if (JSON.stringify(grab('hub')) !== JSON.stringify(hub)) {
		errors.push('tema hub slug kümesi envanterle eşleşmiyor');
	}
	if (JSON.stringify(grab('form_disabled')) !== JSON.stringify(form)) {
		errors.push('tema form-disabled slug kümesi envanterle eşleşmiyor');
	}
	return errors;
}

/** @returns {string[]} hata listesi (boş = geçerli) */
function validatePageManifest(m) {
	const errors = [];
	const approvedSources = loadApprovedSources(errors);
	if (m === null || typeof m !== 'object' || Array.isArray(m)) {
		return ['manifest nesne değil'];
	}
	const extraTop = Object.keys(m).filter((k) => !TOP_KEYS.includes(k));
	const missTop = TOP_KEYS.filter((k) => !(k in m));
	extraTop.forEach((k) => errors.push('bilinmeyen üst-seviye anahtar: ' + k));
	missTop.forEach((k) => errors.push('eksik üst-seviye anahtar: ' + k));
	if (missTop.length || !Array.isArray(m.records)) {
		return errors.concat(Array.isArray(m.records) ? [] : ['records dizi değil']);
	}
	if (m.schema_version !== SCHEMA_VERSION) {
		errors.push('schema_version ' + SCHEMA_VERSION + ' olmalı');
	}
	if (m.record_type !== 'page') {
		errors.push('record_type "page" olmalı');
	}
	if (m.records.length !== PAGE_EXPECTED) {
		errors.push('kayıt sayısı ' + m.records.length + ', tam ' + PAGE_EXPECTED + ' olmalı');
	}
	if (m.count !== m.records.length) {
		errors.push('count (' + m.count + ') kayıt sayısıyla (' + m.records.length + ') uyuşmuyor');
	}
	const slugs = {};
	const keys = {};
	m.records.forEach(function (r, i) {
		const w = 'records[' + i + ']';
		if (r === null || typeof r !== 'object' || Array.isArray(r)) {
			errors.push(w + ': kayıt nesne değil');
			return;
		}
		Object.keys(r).filter((k) => !RECORD_KEYS.includes(k)).forEach((k) => errors.push(w + ': bilinmeyen alan ' + k));
		RECORD_KEYS.filter((k) => !(k in r)).forEach((k) => errors.push(w + ': eksik alan ' + k));
		if (RECORD_KEYS.some((k) => !(k in r))) {
			return;
		}
		if (r.schema_version !== SCHEMA_VERSION) {
			errors.push(w + ': schema_version geçersiz');
		}
		if (typeof r.slug !== 'string' || !SLUG_RE.test(r.slug)) {
			errors.push(w + ': slug biçimi geçersiz');
		} else {
			if (slugs[r.slug]) {
				errors.push(w + ': slug tekrar ediyor (benzersiz olmalı): ' + r.slug);
			}
			slugs[r.slug] = true;
		}
		if (typeof r.source_key !== 'string' || r.source_key !== 'page:' + r.slug) {
			errors.push(w + ': source_key "page:<slug>" olmalı');
		}
		if (typeof r.source_key === 'string') {
			if (keys[r.source_key]) {
				errors.push(w + ': source_key tekrar ediyor: ' + r.source_key);
			}
			keys[r.source_key] = true;
		}
		const inv = PAGE_INVENTORY[i];
		if (!inv || inv.slug !== r.slug) {
			errors.push(w + ': slug envanter sırasıyla/kümesiyle eşleşmiyor (envanter: ' + (inv ? inv.slug : 'yok') + ')');
		}
		if (inv) {
			if (r.title !== inv.title) {
				errors.push(w + ': başlık envanterle eşleşmiyor');
			}
			if (r.layout !== inv.layout) {
				errors.push(w + ': layout envanterle eşleşmiyor');
			}
			const expectedSource = 'approved' === inv.mode ? APPROVED_DIR_REL + '/' + inv.slug + '.html' : 'tanitim-site/' + inv.file;
			if (r.source.file !== expectedSource) {
				errors.push(w + ': source.file envanterle eşleşmiyor');
			}
		}
		if (r.source_index !== i) {
			errors.push(w + ': source_index sıra dışı');
		}
		if (r.menu_order !== i + 1) {
			errors.push(w + ': menu_order ' + (i + 1) + ' olmalı');
		}
		if (typeof r.title !== 'string' || r.title.trim() === '' || r.title !== r.title.trim() || /demo/i.test(r.title) || /[<>]/.test(r.title)) {
			errors.push(w + ': başlık geçersiz (boş/kırpılmamış/demo/işaretleme)');
		}
		if (typeof r.excerpt !== 'string' || r.excerpt.length > 300 || /[<>&\n]/.test(r.excerpt) || r.excerpt !== r.excerpt.trim()) {
			errors.push(w + ': excerpt geçersiz (düz metin, en çok 300 karakter)');
		}
		if (r.page_template !== '') {
			errors.push(w + ': page_template bu sürümde boş olmalı (tema slug/layout ile seçer)');
		}
		if (r.post_status !== 'draft') {
			errors.push(w + ': hedef post_status "draft" olmalı (yayınlama ayrı işlemdir)');
		}
		if (r.parent_source_key !== null && typeof r.parent_source_key !== 'string') {
			errors.push(w + ': parent_source_key string veya null olmalı');
		}
		if (inv && 'approved' === inv.mode && typeof r.content === 'string') {
			// Anlamsal doğrulama: içerik onaylı kaynak parçasından YENİDEN türetilenle birebir eşit (hukuki/banka metni değişmemiş),
			// CTA bağlantısı künyedeki onaylı adresle birebir eşit, başka dış bağlantı yok, iframe yok.
			const entry = approvedSources.bySlug[inv.slug];
			if (!entry) {
				errors.push(w + ': onaylı kaynak künyesi yok');
			} else {
				let fresh = null;
				try {
					fresh = convertApprovedFragment(fs.readFileSync(path.join(REPO_ROOT, entry.fragment_file), 'utf8')).content;
				} catch (e) {
					errors.push(w + ': onaylı kaynak parçası dönüştürülemedi: ' + e.message);
				}
				if (fresh !== null && fresh !== r.content) {
					errors.push(w + ': content onaylı kaynak parçasından türetilenle eşit değil');
				}
				if (r.excerpt !== (inv.excerpt || '')) {
					errors.push(w + ': excerpt envanterle eşleşmiyor');
				}
				const hrefs = [];
				r.content.replace(/<a href="([^"]*)">/g, function (all, h) {
					hrefs.push(h.replace(/&amp;/g, '&'));
					return all;
				});
				const external = hrefs.filter((h) => /^https:/.test(h));
				if (entry.cta_url !== null) {
					if (external.length !== 1 || external[0] !== entry.cta_url) {
						errors.push(w + ': dış CTA bağlantısı künyedeki onaylı adresle BİREBİR eşit ve tek olmalı');
					}
				} else if (external.length > 0 && entry.kind === 'local_authored') {
					errors.push(w + ': beklenmeyen dış bağlantı');
				}
				if (/<iframe|<script|<form/i.test(r.content)) {
					errors.push(w + ': iframe/script/form yasak');
				}
			}
		}
		const htmlErrors = sanitizeCheck(r.content);
		htmlErrors.forEach((e) => errors.push(w + ': content ' + e));
		if (typeof r.content === 'string' && r.content_sha256 !== sha256Hex(Buffer.from(r.content, 'utf8'))) {
			errors.push(w + ': content_sha256 içerikten hesaplanandan farklı');
		}
		if (!Array.isArray(r.pending_decisions) || r.pending_decisions.some((c) => typeof c !== 'string' || !Object.prototype.hasOwnProperty.call(PENDING_CODES, c))) {
			errors.push(w + ': pending_decisions kapalı sözlükte olmayan kod içeriyor');
		} else {
			const hold = r.pending_decisions.some((c) => PENDING_CODES[c].blocking);
			if (r.publish_hold !== hold) {
				errors.push(w + ': publish_hold bekleyen kararlardan türetilene eşit olmalı');
			}
			if (inv && JSON.stringify(r.pending_decisions) !== JSON.stringify(inv.pending)) {
				errors.push(w + ': pending_decisions envanterle eşleşmiyor');
			}
		}
		if (!Array.isArray(r.publish_requires) || r.publish_requires.some((c) => typeof c !== 'string' || !PUBLISH_REQUIRES.includes(c)) || new Set(r.publish_requires).size !== r.publish_requires.length) {
			errors.push(w + ': publish_requires kapalı kümeden (faq/reference) benzersiz kodlar içermeli');
		} else if (inv && JSON.stringify(r.publish_requires) !== JSON.stringify(inv.publishRequires || [])) {
			errors.push(w + ': publish_requires envanterle eşleşmiyor');
		}
		if (r.source === null || typeof r.source !== 'object' || JSON.stringify(Object.keys(r.source)) !== JSON.stringify(['file', 'sha256'])) {
			errors.push(w + ': source {file, sha256} biçiminde olmalı');
		} else if (typeof r.source.file === 'string' && /^(tanitim-site|wordpress-site\/data\/sources\/approved)\/[a-z0-9-]+\.html$/.test(r.source.file) && fs.existsSync(path.join(REPO_ROOT, r.source.file))) {
			if (r.source.sha256 !== fileSha(r.source.file)) {
				errors.push(w + ': kaynak sha256 gerçek dosyayla uyuşmuyor');
			}
		} else {
			errors.push(w + ': kaynak dosya geçersiz/yok');
		}
	});
	// parent grafiği: eksik hedef ve döngü.
	const byKey = {};
	m.records.forEach((r) => {
		if (r && typeof r.source_key === 'string') {
			byKey[r.source_key] = r;
		}
	});
	m.records.forEach(function (r, i) {
		if (!r || r.parent_source_key === null || r.parent_source_key === undefined) {
			return;
		}
		if (!byKey[r.parent_source_key]) {
			errors.push('records[' + i + ']: parent_source_key manifestte yok: ' + r.parent_source_key);
			return;
		}
		const seen = {};
		let cur = r;
		while (cur && cur.parent_source_key) {
			if (seen[cur.source_key]) {
				errors.push('records[' + i + ']: parent döngüsü');
				break;
			}
			seen[cur.source_key] = true;
			cur = byKey[cur.parent_source_key];
		}
	});
	if (m.source === null || typeof m.source !== 'object' || JSON.stringify(Object.keys(m.source)) !== JSON.stringify(['file', 'sha256'])) {
		errors.push('zarf source {file, sha256} biçiminde olmalı');
	} else {
		if (m.source.file !== 'tanitim-site/*.html') {
			errors.push('zarf source.file "tanitim-site/*.html" olmalı');
		}
		if (m.records.every((r) => r && r.source && typeof r.source.sha256 === 'string') && m.source.sha256 !== inventoryDigest(m.records)) {
			errors.push('zarf source.sha256 kayıt kaynaklarının özetiyle uyuşmuyor');
		}
	}
	if (!Array.isArray(m.notes) || m.notes.some((n) => typeof n !== 'string' || n === '')) {
		errors.push('notes string dizisi olmalı');
	}
	return errors;
}

module.exports = { loadApprovedSources, APPROVED_DIR_REL, validatePageManifest, crossCheckInventory, inventoryDigest, RECORD_KEYS, SCHEMA_VERSION, REPO_ROOT };
