#!/usr/bin/env node
'use strict';
/**
 * 2026 ücret tarifesi — kurumsal onay matrisi üreticisi (SALT OKUNUR girdiler; deterministik çıktı; ağ/sunucu/WordPress YOK).
 *
 *   node tools/fees/build-approval-matrix.js --write   raporlar/veri-aktarim-raporlari/ucret-2026-onay/ altına çıktıları yazar
 *   node tools/fees/build-approval-matrix.js --check   diskteki çıktılar üretilenle BYTE-EŞİT mi (aksi hâlde çıkış 1)
 *
 * Girdiler (hiçbiri değiştirilmez): data/content/fees.manifest.json, data/content/qualifications.manifest.json,
 * data/content/sectors.manifest.json, tools/fees/pdf-observations.json (PDF sayfa görüntülerinden elle çıkarılmış transkripsiyon),
 * tanitim-site/ucret/*.pdf (yalnız SHA-256 doğrulaması için okunur).
 *
 * Kurum kararı sütunları ÖNCEDEN DOLDURULMAZ (BEKLIYOR). Hiçbir MYK kodu üretilmez/tahmin edilmez.
 * Doğrulama sonucu (kayıt başına TEK karar): VERIFIED | MISMATCH | UNREADABLE | DECISION_REQUIRED.
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const WS = path.join(__dirname, '..', '..');
const REPO = path.join(WS, '..');
const OUT_DIR = path.join(REPO, 'raporlar', 'veri-aktarim-raporlari', 'ucret-2026-onay');
const TEMPLATES = path.join(__dirname, 'templates');
const PDF_DIR = path.join(REPO, 'tanitim-site', 'ucret');

/** PDF grup başlığı -> bu gruba düşmesi beklenen manifest sektör slug'ları (tarife düzeni; MYK sektörü DEĞİL). */
const GROUP_SECTORS = {
	'MAKİNE GRUBU': ['makine'],
	'ENERJİ GRUBU': ['enerji'],
	'ELEKTRİK GRUBU': ['enerji'],
	'ULAŞTIRMA&LOJİSTİK GRUBU': ['lojistik'],
	'METALURJİ GRUBU': ['metalurji'],
	'MERMER &MADEN GRUBU': ['maden', 'mermer'],
	'MOBİLYA GRUBU': ['mobilya'],
	'PLASTİK GRUBU': ['plastik'],
	'CAM GRUBU': ['cam'],
	'METAL GRUBU': ['metal'],
	'İNŞAAT&İŞ MAKİNELERİ GRUBU': ['insaat'],
	'İŞ MAKİNELERİ GRUBU': ['is-makineleri'],
	'TEKSTİL GRUBU': ['tekstil'],
	'GÜZELLİK VE KUAFÖRLÜK GRUBU MESLEKLERİMİZ': ['guzellik-sac-bakim'],
};

function readJson(p) {
	return JSON.parse(fs.readFileSync(p, 'utf8'));
}
function sha256(buf) {
	return crypto.createHash('sha256').update(buf).digest('hex');
}
/** Türkçe büyük harf + ASCII katlama + yalnız harf/rakam (ad karşılaştırması; noktalama/boşluk/büyük-küçük harf yok sayılır). */
function normName(s) {
	return String(s)
		.toLocaleUpperCase('tr-TR')
		.replace(/[ÇĞİIÖŞÜÂÎÛ]/g, (c) => ({ 'Ç': 'C', 'Ğ': 'G', 'İ': 'I', 'I': 'I', 'Ö': 'O', 'Ş': 'S', 'Ü': 'U', 'Â': 'A', 'Î': 'I', 'Û': 'U' }[c]))
		.replace(/[^A-Z0-9]/g, '');
}
/** Etiket/birim metinlerinden birim kodları (A1, B7...; "B1-B9" aralığı açılır). */
function extractCodes(texts) {
	const set = new Set();
	for (const t of texts) {
		const s = String(t);
		let m;
		const range = /\bB(\d{1,2})\s*-\s*B(\d{1,2})\b/g;
		while ((m = range.exec(s)) !== null) {
			for (let k = Number(m[1]); k <= Number(m[2]); k++) set.add('B' + k);
		}
		const single = /\b([AB])(\d{1,2})\b/g;
		while ((m = single.exec(s)) !== null) set.add(m[1] + Number(m[2]));
	}
	return Array.from(set).sort((a, b) => (a[0] === b[0] ? Number(a.slice(1)) - Number(b.slice(1)) : a < b ? -1 : 1));
}
function fmtKurus(k) {
	const lira = Math.floor(k / 100);
	const kr = String(k % 100).padStart(2, '0');
	return String(lira).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + kr + ' TL';
}
function csvCell(v) {
	const s = String(v === null || v === undefined ? '' : v);
	return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}
function mdCell(v) {
	return String(v === null || v === undefined ? '' : v).replace(/\|/g, '\\|').replace(/\r?\n/g, ' ');
}
function sortedEq(a, b) {
	return a.length === b.length && a.every((v, i) => v === b[i]);
}

/**
 * Girdileri yükler ve yapısal bütünlüğü (103 kayıt, eksik/fazla/çift yok, PDF SHA-256) doğrular.
 * Hata varsa fırlatır (fail-closed).
 */
function loadInputs(opts) {
	opts = opts || {};
	const feesDoc = opts.fees || readJson(path.join(WS, 'data', 'content', 'fees.manifest.json'));
	const qualsDoc = opts.quals || readJson(path.join(WS, 'data', 'content', 'qualifications.manifest.json'));
	const sectorsDoc = opts.sectors || readJson(path.join(WS, 'data', 'content', 'sectors.manifest.json'));
	const obsDoc = opts.obs || readJson(path.join(__dirname, 'pdf-observations.json'));
	const errors = [];
	const records = feesDoc.records;
	if (!Array.isArray(records) || records.length !== 103) errors.push('fees.manifest.json: 103 kayıt beklenir, ' + (records ? records.length : 'yok'));
	const keys = new Set();
	records.forEach((r, i) => {
		if (keys.has(r.source_key)) errors.push('çift source_key: ' + r.source_key);
		keys.add(r.source_key);
		if (r.source_index !== i) errors.push('source_index sırası bozuk: #' + i);
	});
	const seen = new Set();
	for (const e of obsDoc.entries) {
		if (seen.has(e.i)) errors.push('gözlem çift: i=' + e.i);
		seen.add(e.i);
		if (!records[e.i]) errors.push('gözlem fazla (manifestte yok): i=' + e.i);
		// Okunabilirlik AÇIK olmalı; okunamayan gözlemde elle fiyat/birim/ad/seviye yazılamaz (uydurma veri reddi).
		if (e.legibility !== 'readable' && e.legibility !== 'unreadable') errors.push('gözlem okunabilirliği tanımsız (readable|unreadable): i=' + e.i);
		const pdfMeta = obsDoc.pdfs[e.pdf];
		if (!pdfMeta) errors.push('gözlem bilinmeyen PDF: i=' + e.i);
		else if ((pdfMeta.unreadable_pages || []).indexOf(e.page) !== -1 && e.legibility !== 'unreadable') errors.push('okunamayan sayfadaki gözlem readable işaretlenemez: i=' + e.i + ' s.' + e.page);
		if (e.legibility === 'unreadable' && ((e.cells && e.cells.length) || (e.codes && e.codes.length) || e.name !== null || e.level !== null)) {
			errors.push('okunamayan gözlemde fiyat/birim/ad/seviye bulunamaz (cells=[], codes=[], name=null, level=null olmalı): i=' + e.i);
		}
		if (e.legibility === 'readable' && (!Array.isArray(e.cells) || e.cells.length === 0)) errors.push('okunabilir gözlemde en az bir fiyat hücresi gerekir: i=' + e.i);
	}
	for (const k of Object.keys(obsDoc.pdfs)) {
		if (!Array.isArray(obsDoc.pdfs[k].unreadable_pages)) errors.push('PDF okunamayan sayfa listesi tanımsız (unreadable_pages): ' + k);
	}
	records.forEach((r, i) => {
		if (!seen.has(i)) errors.push('gözlem eksik: i=' + i + ' ' + r.source_key);
	});
	if (!opts.skipPdfHash) {
		for (const k of Object.keys(obsDoc.pdfs)) {
			const p = path.join(PDF_DIR, obsDoc.pdfs[k].file);
			if (!fs.existsSync(p)) errors.push('PDF yok: ' + obsDoc.pdfs[k].file);
			else if (sha256(fs.readFileSync(p)) !== obsDoc.pdfs[k].sha256) errors.push('PDF SHA-256 değişmiş (transkripsiyon geçersiz): ' + obsDoc.pdfs[k].file);
		}
	}
	if (errors.length) {
		const e = new Error('Girdi doğrulaması başarısız:\n - ' + errors.join('\n - '));
		e.errors = errors;
		throw e;
	}
	const qBySourceKey = new Map(qualsDoc.records.map((q) => [q.source_key, q]));
	const qLinkCount = new Map();
	records.forEach((r) => {
		if (r.qualification_source_key) qLinkCount.set(r.qualification_source_key, (qLinkCount.get(r.qualification_source_key) || 0) + 1);
	});
	const sectorNames = new Map((sectorsDoc.records || []).map((s) => [s.slug, s.name]));
	return { feesDoc, qualsDoc, sectorsDoc, obsDoc, qBySourceKey, qLinkCount, sectorNames };
}

function unlinkedEvidence(rec, qualsDoc) {
	const n = normName(rec.profession_name);
	const exact = qualsDoc.records.filter((q) => normName(q.name) === n && q.level === rec.level);
	const sameNameOtherLevel = qualsDoc.records.filter((q) => normName(q.name) === n && q.level !== rec.level);
	return { exact, sameNameOtherLevel };
}

/** Tek kaydın PDF + yeterlilik karşılaştırması. */
function verifyRecord(rec, obs, ctx) {
	const pdf = ctx.obsDoc.pdfs[obs.pdf];
	const diffs = []; // {kind:'MISMATCH'|'DECISION'|'INFO', text}
	const checks = {};
	// 1) kaynak/sayfa
	checks.source = rec.source_name === pdf.source_name ? 'OK' : 'FARKLI';
	if (checks.source !== 'OK') diffs.push({ kind: 'MISMATCH', text: 'kaynak adı: manifest "' + rec.source_name + '" ≠ PDF "' + pdf.source_name + '"' });
	checks.page = rec.source_page === obs.page && obs.page >= 1 && obs.page <= pdf.pages ? 'OK' : 'FARKLI';
	if (checks.page !== 'OK') diffs.push({ kind: 'MISMATCH', text: 'kaynak sayfa: manifest ' + rec.source_page + ' ≠ PDF gözlemi ' + obs.page });
	const unreadable = obs.legibility === 'unreadable';
	if (unreadable) {
		// Okunamayan kaynak: ad/seviye/tutar/birim KARŞILAŞTIRILMAZ (kanıt yok); VERIFIED/DECISION_REQUIRED olamaz.
		checks.name = checks.level = checks.amounts = checks.codes = 'OKUNAMADI';
		diffs.push({ kind: 'UNREADABLE', text: 'PDF s.' + obs.page + ' bu kayıt için okunamadı' + (obs.note ? ' (' + obs.note + ')' : '') + '; ad/seviye/fiyat/birim doğrulanamadı' });
	}
	// 2) ad + seviye
	if (!unreadable) {
		const nameOk = normName(rec.profession_name) === normName(obs.name.replace(/\s*\(.*\)\s*$/, '')) || normName(rec.profession_name) === normName(obs.name);
		checks.name = nameOk ? 'OK' : 'AD_FARKI';
		if (!nameOk) diffs.push({ kind: 'DECISION', text: 'ad: PDF "' + obs.name + '" ≠ manifest "' + rec.profession_name + '" (PDF kısaltma/yazım farkı; manifest adı MYK yeterlilik adına dayanır)' });
		checks.level = rec.level === obs.level ? 'OK' : 'FARKLI';
		if (checks.level !== 'OK') diffs.push({ kind: 'MISMATCH', text: 'seviye: manifest ' + rec.level + ' ≠ PDF ' + obs.level });
	}
	// 3) tutarlar (çoklu küme; hücre başına n)
	const mAmounts = rec.price_options.map((o) => o.amount_kurus).sort((a, b) => a - b);
	const pAmounts = [];
	for (const c of obs.cells) for (let k = 0; k < c.n; k++) pAmounts.push(Math.round(c.amount_try * 100));
	pAmounts.sort((a, b) => a - b);
	if (!unreadable) checks.amounts = sortedEq(mAmounts, pAmounts) ? 'OK' : 'FARKLI';
	if (checks.amounts === 'FARKLI') diffs.push({ kind: 'MISMATCH', text: 'tutarlar: manifest [' + mAmounts.map(fmtKurus).join('; ') + '] ≠ PDF [' + pAmounts.map(fmtKurus).join('; ') + ']' });
	const sumMin = Math.min.apply(null, mAmounts);
	const sumMax = Math.max.apply(null, mAmounts);
	if (rec.min_amount_kurus !== sumMin || rec.max_amount_kurus !== sumMax) {
		checks.minmax = 'FARKLI';
		diffs.push({ kind: 'MISMATCH', text: 'min/max tutar seçeneklerle tutarsız' });
	}
	// 4) birim kodları
	const mCodes = extractCodes([].concat(rec.price_options.map((o) => o.label), rec.price_options.reduce((a, o) => a.concat(o.units || []), [])));
	const pCodes = extractCodes(obs.codes);
	if (!unreadable) checks.codes = sortedEq(mCodes, pCodes) ? 'OK' : 'FARKLI';
	if (checks.codes === 'FARKLI') diffs.push({ kind: 'MISMATCH', text: 'birim kodları: manifest [' + mCodes.join(',') + '] ≠ PDF [' + pCodes.join(',') + ']' });
	// 5) KDV + belge basım
	if (pdf.vat_statement.included === null) {
		checks.vat = 'KANIT_YOK';
		diffs.push({ kind: 'DECISION', text: 'KDV: manifest vat_included=' + rec.vat_included + ' ama "' + pdf.file + '" hiçbir sayfasında KDV ifadesi yok (kaynak: yalnız fees.js başlık yorumu); kurumdan yazılı teyit gerekir' });
	} else if (rec.vat_included === pdf.vat_statement.included) {
		checks.vat = 'OK';
	} else {
		checks.vat = 'FARKLI';
		diffs.push({ kind: 'MISMATCH', text: 'KDV: manifest ' + rec.vat_included + ' ≠ PDF ' + pdf.vat_statement.included });
	}
	checks.print = rec.certificate_print_fee_kurus === pdf.print_fee_try * 100 ? 'OK' : 'FARKLI';
	if (checks.print !== 'OK') diffs.push({ kind: 'MISMATCH', text: 'belge basım ücreti: manifest ' + fmtKurus(rec.certificate_print_fee_kurus) + ' ≠ PDF ' + fmtKurus(pdf.print_fee_try * 100) });
	// 6) tarife dönemi (PDF sayfalarında yıl basılı değil; yalnız dosya adı)
	checks.period = rec.planned_tariff_period === '2026' && pdf.file.indexOf('2026') === 0 ? 'DOSYA_ADI' : 'FARKLI';
	if (checks.period === 'FARKLI') diffs.push({ kind: 'MISMATCH', text: 'tarife dönemi dosya adıyla tutarsız' });
	// 7) sektör (PDF grubu yalnız tarife düzenidir)
	const allowed = GROUP_SECTORS[obs.group] || [];
	checks.sector = allowed.indexOf(rec.sector_slug) !== -1 ? 'GRUP_UYUMLU' : 'GRUP_FARKLI';
	if (checks.sector === 'GRUP_FARKLI') {
		diffs.push({ kind: 'INFO', text: 'sektör: PDF grubu "' + obs.group + '" (beklenen sektör: ' + allowed.join('/') + ') ≠ manifest sektörü "' + rec.sector_slug + '"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir' });
	}
	// 8) bağlantı / MYK kodu (PDF'lerde MYK kodu YOKTUR; ikinci bağımsız kaynak: qualifications.manifest.json)
	let qual = null;
	if (rec.qualification_source_key) {
		qual = ctx.qBySourceKey.get(rec.qualification_source_key) || null;
		if (!qual) {
			checks.link = 'YETERLILIK_YOK';
			diffs.push({ kind: 'MISMATCH', text: 'bağlı yeterlilik manifestte yok: ' + rec.qualification_source_key });
		} else {
			const problems = [];
			if (qual.code !== rec.qualification_code) problems.push('kod');
			if (qual.level !== rec.level) problems.push('seviye');
			if (qual.sector_slug !== rec.sector_slug) problems.push('sektör');
			const baseName = (n) => normName(String(n).replace(/\s*\([^)]*\)\s*$/, ''));
			if (baseName(qual.name) !== baseName(rec.profession_name)) problems.push('ad');
			else if (normName(qual.name) !== normName(rec.profession_name)) diffs.push({ kind: 'INFO', text: 'ücret adı yeterlilik adına parantez ayrımı ekler: "' + rec.profession_name + '" ↔ yeterlilik "' + qual.name + '"' });
			const sharers = ctx.qLinkCount.get(rec.qualification_source_key) || 0;
			if (sharers > 1) diffs.push({ kind: 'DECISION', text: 'aynı yeterliliğe (' + qual.code + ') ' + sharers + ' ücret kaydı bağlı; yeterlilik detayında hangisinin gösterileceği kurum kararı gerektirir' });
			checks.link = problems.length ? 'TUTARSIZ' : 'OK';
			if (problems.length) diffs.push({ kind: 'MISMATCH', text: 'bağlı yeterlilik ile tutarsız alan(lar): ' + problems.join(',') });
		}
	} else {
		checks.link = 'BAGLANTISIZ';
		if (rec.qualification_code) diffs.push({ kind: 'MISMATCH', text: 'qualification_code dolu ama qualification_source_key boş' });
		diffs.push({ kind: 'DECISION', text: 'MYK kodu yok: PDF\'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)' });
	}
	// karar (öncelik: MISMATCH > UNREADABLE > DECISION_REQUIRED > VERIFIED)
	let verdict = 'VERIFIED';
	if (diffs.some((d) => d.kind === 'DECISION')) verdict = 'DECISION_REQUIRED';
	if (diffs.some((d) => d.kind === 'UNREADABLE')) verdict = 'UNREADABLE';
	if (diffs.some((d) => d.kind === 'MISMATCH')) verdict = 'MISMATCH';
	return { verdict, checks, diffs, qual };
}

function build(opts) {
	const ctx = loadInputs(opts);
	const { feesDoc, qualsDoc, obsDoc, sectorNames } = ctx;
	const obsByIdx = new Map(obsDoc.entries.map((e) => [e.i, e]));
	const rows = feesDoc.records.map((rec, i) => {
		const obs = obsByIdx.get(i);
		const v = verifyRecord(rec, obs, ctx);
		const linked = !!rec.qualification_source_key;
		const blockers = [];
		if (v.verdict === 'MISMATCH') blockers.push('PDF ile manifest arasında fark');
		if (v.verdict === 'UNREADABLE') blockers.push('PDF okunamadı');
		if (!linked) blockers.push('MYK kodu/bağlantı kararı bekliyor');
		if (v.checks.vat === 'KANIT_YOK') blockers.push('KDV kanıtı yok');
		if (v.checks.name === 'AD_FARKI') blockers.push('ad farkı kararı bekliyor');
		let eligibility;
		if (blockers.length) eligibility = 'ENGEL: ' + blockers.join('; ');
		else eligibility = 'UYGUN ADAY — yalnız kurum onayı sonrası (varsayılan: yayına ALINMAZ)';
		return { i, rec, obs, v, linked, eligibility, evidence: linked ? null : unlinkedEvidence(rec, qualsDoc) };
	});
	const count = (fn) => rows.filter(fn).length;
	const bySource = {};
	const byPdfPage = {};
	for (const r of rows) {
		bySource[r.rec.source_name] = (bySource[r.rec.source_name] || 0) + 1;
		const k = r.obs.pdf + '|' + r.obs.page;
		byPdfPage[k] = (byPdfPage[k] || 0) + 1;
	}
	const S = {
		total: rows.length,
		verdict: { VERIFIED: count((r) => r.v.verdict === 'VERIFIED'), MISMATCH: count((r) => r.v.verdict === 'MISMATCH'), UNREADABLE: count((r) => r.v.verdict === 'UNREADABLE'), DECISION_REQUIRED: count((r) => r.v.verdict === 'DECISION_REQUIRED') },
		bySource,
		byPdfPage,
		linked: count((r) => r.linked),
		unlinked: count((r) => !r.linked),
		options: rows.reduce((a, r) => a + r.rec.price_options.length, 0),
		single: count((r) => r.rec.pricing_type === 'single'),
		notSingle: count((r) => r.rec.pricing_type !== 'single'),
		singleOption: count((r) => r.rec.price_options.length === 1),
		multiOption: count((r) => r.rec.price_options.length > 1),
		multiOptionsTotal: rows.filter((r) => r.rec.price_options.length > 1).reduce((a, r) => a + r.rec.price_options.length, 0),
		byType: rows.reduce((a, r) => { a[r.rec.pricing_type] = (a[r.rec.pricing_type] || 0) + 1; return a; }, {}),
		vatUnverified: count((r) => r.v.checks.vat === 'KANIT_YOK'),
		nameDiff: count((r) => r.v.checks.name === 'AD_FARKI'),
		sectorGroupDiff: count((r) => r.v.checks.sector === 'GRUP_FARKLI'),
		distinctQualifications: new Set(rows.filter((r) => r.linked).map((r) => r.rec.qualification_source_key)).size,
		manifestCounts: feesDoc.counts,
		pdfPages: Object.keys(obsDoc.pdfs).reduce((a, k) => { a[k] = obsDoc.pdfs[k].pages; return a; }, {}),
	};
	// mutabakat: manifestin kendi sayaç bloğu ile bağımsız yeniden sayım
	S.reconcile = [
		['toplam kayıt', 103, S.total],
		['96 Yeni Meslekler + 7 Güzellik', '96+7', (bySource['2026 Yeni Meslekler Ücret Tarifesi'] || 0) + '+' + (bySource['2026 Güzellik Fiyat Listesi'] || 0)],
		['84 bağlı + 19 bağlantısız', '84+19', S.linked + '+' + S.unlinked],
		['manifest counts.feesWithCode', feesDoc.counts.feesWithCode, S.linked],
		['manifest counts.feesWithoutCode', feesDoc.counts.feesWithoutCode, S.unlinked],
		['manifest counts.priceOptionsTotal', feesDoc.counts.priceOptionsTotal, S.options],
		['manifest counts.pricingSingle', feesDoc.counts.pricingSingle, S.single],
		['manifest counts.pricingMulti', feesDoc.counts.pricingMulti, S.notSingle],
		['manifest counts.multiOptionsTotal', feesDoc.counts.multiOptionsTotal, S.multiOptionsTotal],
	];
	S.reconcileOk = S.reconcile.every((r) => String(r[1]) === String(r[2]));
	return { rows, S, ctx };
}

function optionsText(rec) {
	return rec.price_options.map((o) => o.label + ': ' + fmtKurus(o.amount_kurus)).join(' | ');
}
function verdictNote(r) {
	const parts = r.v.diffs.map((d) => '[' + d.kind + '] ' + d.text);
	return parts.length ? parts.join(' ;; ') : 'PDF ile tutarlı (fiyat, birim, sayfa, seviye, KDV, belge basım); bağlantı qualifications.manifest ile tutarlı';
}
function pdfFile(obsDoc, r) {
	return obsDoc.pdfs[r.obs.pdf].file;
}

const MATRIX_HEADERS = ['source_key', 'meslek', 'seviye', 'sektör', 'MYK kodu', 'bağlı yeterlilik', 'fiyatlandırma türü', 'fiyat seçenekleri', 'KDV', 'belge basım ücreti', 'kaynak PDF', 'kaynak sayfa', 'PDF grubu', 'doğrulama sonucu', 'fark/açıklama', 'kurum kararı', 'yayına uygunluk'];

function matrixRow(r, ctx) {
	const rec = r.rec;
	return [
		rec.source_key,
		rec.profession_name,
		rec.level,
		rec.sector_slug + ' (' + (ctx.sectorNames.get(rec.sector_slug) || '?') + ')',
		rec.qualification_code || '',
		r.v.qual ? r.v.qual.name + ' [' + r.v.qual.code + ']' : '— (bağlantısız)',
		rec.pricing_type,
		optionsText(rec),
		r.v.checks.vat === 'KANIT_YOK' ? 'KDV dahil (manifest) — PDF\'de KDV ifadesi YOK' : rec.vat_included ? 'KDV dahil (PDF: %20)' : 'KDV hariç',
		fmtKurus(rec.certificate_print_fee_kurus) + ' (fiyatlara dahil değil)',
		pdfFile(ctx.obsDoc, r),
		rec.source_page,
		r.obs.group,
		r.v.verdict,
		verdictNote(r),
		'BEKLIYOR',
		r.eligibility,
	];
}

function renderCsv(headers, rowsArr) {
	return '\uFEFF' + [headers.map(csvCell).join(',')].concat(rowsArr.map((row) => row.map(csvCell).join(','))).join('\r\n') + '\r\n';
}
function renderMdTable(headers, rowsArr) {
	return '| ' + headers.map(mdCell).join(' | ') + ' |\n|' + headers.map(() => '---').join('|') + '|\n' + rowsArr.map((row) => '| ' + row.map(mdCell).join(' | ') + ' |').join('\n') + '\n';
}

function template(name, vars) {
	let t = fs.readFileSync(path.join(TEMPLATES, name), 'utf8').replace(/\r\n/g, '\n');
	t = t.replace(/\{\{([A-Z0-9_]+)\}\}/g, (m, k) => {
		if (!(k in vars)) throw new Error('şablon değişkeni yok: ' + k + ' (' + name + ')');
		return vars[k];
	});
	return t;
}

function renderOutputs(opts) {
	const { rows, S, ctx } = build(opts);
	const { obsDoc } = ctx;
	const outputs = {};
	const matrix = rows.map((r) => matrixRow(r, ctx));
	outputs['onay-matrisi.csv'] = renderCsv(MATRIX_HEADERS, matrix);
	const mdHeaders = ['#', 'source_key', 'meslek', 'sv', 'sektör', 'MYK kodu', 'bağlı yeterlilik', 'tür', 'fiyat seçenekleri', 'KDV / basım', 'kaynak', 'sonuç', 'fark/açıklama', 'kurum kararı', 'yayına uygunluk'];
	const mdRows = rows.map((r, idx) => {
		const m = matrix[idx];
		return [idx + 1, m[0], m[1], m[2], r.rec.sector_slug, m[4] || '—', m[5], m[6], m[7], (r.v.checks.vat === 'KANIT_YOK' ? 'KDV KANITI YOK' : 'KDV dahil') + ' / 1.500,00 TL hariç', (r.obs.pdf === 'yeni' ? 'Yeni Meslekler' : 'Güzellik') + ' s.' + m[11], m[13], m[14], m[15], m[16]];
	});
	const verdictLines = Object.keys(S.verdict).map((k) => '- **' + k + '**: ' + S.verdict[k]).join('\n');
	outputs['onay-matrisi.md'] = template('onay-matrisi.md', {
		TOTAL: S.total, VERDICT_LINES: verdictLines, TABLE: renderMdTable(mdHeaders, mdRows),
		YENI_SHA: obsDoc.pdfs.yeni.sha256, GUZ_SHA: obsDoc.pdfs.guz.sha256,
	});
	// 19 bağlantısız
	const un = rows.filter((r) => !r.linked);
	const unHeaders = ['#', 'source_key', 'meslek', 'seviye', 'sektör', 'fiyat seçenekleri', 'kaynak PDF', 'kaynak sayfa', 'kaynakta MYK kodu', 'qualifications manifestinde birebir (ad+seviye) eşleşme', 'aynı adlı, farklı seviyeli yeterlilik (yalnız bilgi; eşleşme DEĞİL)', 'otomatik bağlanamama nedeni', 'gereken kurum kararı', 'öneri'];
	const unRows = un.map((r, idx) => {
		const ev = r.evidence;
		const exact = ev.exact.length ? ev.exact.map((q) => q.code + ' ' + q.name).join('; ') : 'YOK';
		const other = ev.sameNameOtherLevel.length ? ev.sameNameOtherLevel.map((q) => q.code + ' (seviye ' + q.level + ')').join('; ') : 'YOK';
		const reason = ev.exact.length ? 'Manifestte birebir ad+seviye adayı var ancak fees.js bu kayda kod atamamış; otomatik bağlanmadı' : 'PDF\'de MYK kodu yok; qualifications manifestinde birebir ad+seviye eşleşmesi yok';
		return [idx + 1, r.rec.source_key, r.rec.profession_name, r.rec.level, r.rec.sector_slug, optionsText(r.rec), pdfFile(obsDoc, r), r.rec.source_page, 'YOK (PDF\'lerde MYK kodu sütunu yok)', exact, other, reason, 'Kurum: bu meslek için geçerli MYK yeterlilik kodunu yazılı bildirmeli VEYA ücretin bağlantısız yayınlanmasını açıkça onaylamalı', ev.exact.length ? 'BAĞLANTISIZ KALMALI — KURUM KARARI BEKLİYOR (birebir aday: kurum teyit etsin)' : 'BAĞLANTISIZ KALMALI — KURUM KARARI BEKLİYOR'];
	});
	outputs['baglantisiz-19-kayit.csv'] = renderCsv(unHeaders, unRows);
	outputs['baglantisiz-19-kayit.md'] = template('baglantisiz-19-kayit.md', { COUNT: un.length, TABLE: renderMdTable(unHeaders, unRows) });
	// özet
	const pageRows = Object.keys(S.byPdfPage).sort().map((k) => {
		const [p, pg] = k.split('|');
		return [obsDoc.pdfs[p].file, pg, S.byPdfPage[k]];
	});
	const diffLines = rows.filter((r) => r.v.diffs.length).map((r) => '- **#' + (r.i + 1) + ' ' + r.rec.source_key + '** — ' + r.v.verdict + ': ' + r.v.diffs.map((d) => '[' + d.kind + '] ' + d.text).join(' ;; ')).join('\n');
	const infoOnly = rows.filter((r) => r.v.diffs.length && r.v.diffs.every((d) => d.kind === 'INFO')).length;
	outputs['dogrulama-ozeti.md'] = template('dogrulama-ozeti.md', {
		TOTAL: S.total, VERDICT_LINES: verdictLines,
		RECONCILE_TABLE: renderMdTable(['kontrol', 'beklenen', 'bulunan', 'sonuç'], S.reconcile.map((r) => [r[0], r[1], r[2], String(r[1]) === String(r[2]) ? 'EŞİT' : 'FARKLI'])),
		RECONCILE_OK: S.reconcileOk ? 'TÜM MUTABAKATLAR EŞİT' : 'MUTABAKAT HATASI VAR',
		PAGE_TABLE: renderMdTable(['PDF', 'sayfa', 'kayıt sayısı'], pageRows),
		OPTIONS: S.options, SINGLE_OPT: S.singleOption, MULTI_OPT: S.multiOption, MULTI_OPT_TOTAL: S.multiOptionsTotal,
		TYPES: Object.keys(S.byType).sort().map((k) => k + '=' + S.byType[k]).join(', '),
		DISTINCT_QUAL: S.distinctQualifications, LINKED: S.linked, UNLINKED: S.unlinked,
		VAT_UNVERIFIED: S.vatUnverified, NAME_DIFF: S.nameDiff, SECTOR_DIFF: S.sectorGroupDiff, INFO_ONLY: infoOnly,
		UNREADABLE_LINE: S.verdict.UNREADABLE === 0 ? '- `UNREADABLE`: okunamayan sayfa/kayıt yok (6 PDF sayfasının tamamı okundu).' : '- `UNREADABLE`: ' + S.verdict.UNREADABLE + ' kayıt okunamadı (ad/seviye/fiyat/birim doğrulanamadı; bu kayıtlar yayına aday OLAMAZ) — bkz. §5.',
		MISMATCH_LINE: S.verdict.MISMATCH === 0 ? '- `MISMATCH`: PDF ile manifest arasında **fiyat, birim, sayfa, seviye, belge basım ücreti veya bağlantı** farkı bulunmadı.' : '- `MISMATCH`: ' + S.verdict.MISMATCH + ' kayıtta PDF ile manifest arasında fark var — bkz. §5 (fail-closed).',
		DIFF_LINES: diffLines, YENI_SHA: obsDoc.pdfs.yeni.sha256, GUZ_SHA: obsDoc.pdfs.guz.sha256,
	});
	// PDF medya planı + aktivasyon planı
	const perPdf = {};
	for (const r of rows) {
		perPdf[r.obs.pdf] = perPdf[r.obs.pdf] || { n: 0, pages: {}, keys: [] };
		perPdf[r.obs.pdf].n++;
		perPdf[r.obs.pdf].pages[r.obs.page] = (perPdf[r.obs.pdf].pages[r.obs.page] || 0) + 1;
		perPdf[r.obs.pdf].keys.push(r.rec.source_key);
	}
	const pdfLine = (k) => obsDoc.pdfs[k].file + ' — SHA-256 `' + obsDoc.pdfs[k].sha256 + '` — ' + perPdf[k].n + ' kayıt — sayfalar: ' + Object.keys(perPdf[k].pages).map((p) => 's.' + p + '=' + perPdf[k].pages[p]).join(', ');
	outputs['pdf-medya-esleme-plani.md'] = template('pdf-medya-esleme-plani.md', {
		YENI_LINE: pdfLine('yeni'), GUZ_LINE: pdfLine('guz'), YENI_N: perPdf.yeni.n, GUZ_N: perPdf.guz.n, TOTAL: S.total,
		YENI_SHA: obsDoc.pdfs.yeni.sha256, GUZ_SHA: obsDoc.pdfs.guz.sha256,
		YENI_FILE: obsDoc.pdfs.yeni.file, GUZ_FILE: obsDoc.pdfs.guz.file,
		YENI_KEYS: perPdf.yeni.keys.map((k) => '`' + k + '`').join(', '), GUZ_KEYS: perPdf.guz.keys.map((k) => '`' + k + '`').join(', '),
	});
	outputs['staging-aktivasyon-plani.md'] = template('staging-aktivasyon-plani.md', {
		TOTAL: S.total, LINKED: S.linked, UNLINKED: S.unlinked, VERIFIED: S.verdict.VERIFIED, DECISION: S.verdict.DECISION_REQUIRED, MISMATCH: S.verdict.MISMATCH,
		BATCHES_ALL: Math.ceil(S.total / 10), BATCHES_LINKED: Math.ceil(S.linked / 10),
	});
	return { outputs, S, rows };
}

module.exports = { build, renderOutputs, verifyRecord, loadInputs, normName, extractCodes, fmtKurus, GROUP_SECTORS, OUT_DIR, MATRIX_HEADERS };

if (require.main === module) {
	const mode = process.argv[2];
	let res;
	try {
		res = renderOutputs();
	} catch (e) {
		console.error(e.message);
		process.exit(1);
	}
	if (mode === '--write') {
		fs.mkdirSync(OUT_DIR, { recursive: true });
		for (const [name, text] of Object.entries(res.outputs)) {
			fs.writeFileSync(path.join(OUT_DIR, name), text);
			console.log('yazıldı  ' + path.relative(REPO, path.join(OUT_DIR, name)).replace(/\\/g, '/') + ' (' + Buffer.byteLength(text) + ' bayt)');
		}
		console.log('\nkayıt: ' + res.S.total + ' | ' + Object.keys(res.S.verdict).map((k) => k + '=' + res.S.verdict[k]).join(' ') + ' | mutabakat: ' + (res.S.reconcileOk ? 'EŞİT' : 'HATA'));
	} else if (mode === '--check') {
		let bad = 0;
		for (const [name, text] of Object.entries(res.outputs)) {
			const p = path.join(OUT_DIR, name);
			const same = fs.existsSync(p) && fs.readFileSync(p, 'utf8') === text;
			console.log((same ? 'EŞİT   ' : 'FARKLI ') + name);
			if (!same) bad++;
		}
		process.exit(bad || !res.S.reconcileOk ? 1 : 0);
	} else {
		console.error('Kullanım: --write | --check');
		process.exit(2);
	}
}
