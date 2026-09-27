#!/usr/bin/env node
'use strict';
/**
 * Bağımsız YARDIMCI kontrol: PDF metin katmanındaki (pdftotext) 4-5 haneli tutar belirteçlerini, elle yapılan görsel
 * transkripsiyonun (pdf-observations.json) sayfa bazlı tutar çoklu kümesiyle karşılaştırır. Metin katmanının fontu
 * bozuk olduğu için YALNIZ rakamlar güvenilirdir; ad/etiket/yapı bu kontrolde KULLANILMAZ (görsel doğrulama esastır).
 *
 * Kapsam sınırı (dürüst): "2026 GÜZELLİK FİYAT LİSTESİ" sayfa 2'deki fiyatlar vektör/görüntü olarak çizilmiştir; metin katmanında
 * yoktur — bu sayfa için bu kontrol UYGULANAMAZ (ATLANDI) ve yalnız görsel okuma geçerlidir.
 *
 * BAĞIMLILIK: poppler `pdftotext` (Git for Windows: /mingw64/bin/pdftotext; Debian: poppler-utils). Başka bir ikili
 * MB_PDFTOTEXT ortam değişkeniyle verilebilir.
 *
 *   node tools/fees/verify-pdf-text-layer.js
 *
 * Çıkış kodları: 0 = Yeni Meslekler sayfalarının hepsi EŞİT; 1 = en az bir sayfa FARKLI; 3 = EKSİK BAĞIMLILIK (pdftotext
 * bulunamadı/çalışmadı — kontrol YAPILAMADI). run-all-gates.sh sıfır olmayan her çıkışı BAŞARISIZ sayar: bağımlılık yoksa kapı
 * sessizce geçmez. Güzellik PDF'i her durumda ATLANDI olarak raporlanır (metin katmanında fiyat yok; yalnız görsel doğrulama).
 */
const fs = require('fs');
const path = require('path');
const cp = require('child_process');

const REPO = path.join(__dirname, '..', '..', '..');
const PDF_DIR = path.join(REPO, 'tanitim-site', 'ucret');
const NOISE_LINE = /mavibelge\.com\.tr|0\(542\)|0\(326\)|KDV|BASIM|BAS[İI]M|(?<!\d)1500\s*TL|[Aa]daylar|belge \S+cretini/i;

const BIN = process.env.MB_PDFTOTEXT || 'pdftotext';

/** @return {ok:boolean, reason?:string} — pdftotext gerçekten çalışabiliyor mu. */
function probe() {
	const r = cp.spawnSync(BIN, ['-v'], { stdio: 'ignore' });
	if (r.error) return { ok: false, reason: r.error.code === 'ENOENT' ? 'bulunamadı' : String(r.error.code || r.error.message) };
	return { ok: true };
}

function pageNumbers(file, page) {
	const txt = cp.execFileSync(BIN, ['-layout', '-f', String(page), '-l', String(page), path.join(PDF_DIR, file), '-'], { encoding: 'latin1', stdio: ['ignore', 'pipe', 'ignore'] });
	const lines = txt.split('\n').filter((l) => !NOISE_LINE.test(l));
	return (lines.join('\n').match(/(?<![\d.,/-])\d{4,5}(?![\d/-])/g) || []).map(Number).sort((a, b) => a - b);
}
function observedAmounts(obs, key, page) {
	const out = [];
	for (const e of obs.entries) {
		if (e.pdf !== key || e.page !== page) continue;
		for (const c of e.cells) {
			const printed = /her biri ayrı hücre/.test(c.label) ? c.n : 1; // n: manifest seçenek sayısı; basılı hücre sayısı ayrı
			for (let k = 0; k < printed; k++) out.push(c.amount_try);
		}
	}
	return out.sort((a, b) => a - b);
}
function counts(a) {
	return a.reduce((m, v) => ((m[v] = (m[v] || 0) + 1), m), {});
}

function run() {
	const obs = JSON.parse(fs.readFileSync(path.join(__dirname, 'pdf-observations.json'), 'utf8'));
	const pr = probe();
	if (!pr.ok) return { missing: true, reason: pr.reason, results: [] };
	const results = [];
	for (const key of Object.keys(obs.pdfs)) {
		const pdf = obs.pdfs[key];
		for (let pg = 1; pg <= pdf.pages; pg++) {
			if (key === 'guz') {
				results.push({ pdf: key, page: pg, status: 'ATLANDI', detail: 'Güzellik PDF fiyatları metin katmanında yok (vektör/görüntü) veya sayfa fiyat içermiyor; yalnız görsel okuma' });
				continue;
			}
			const text = pageNumbers(pdf.file, pg);
			const mine = observedAmounts(obs, key, pg);
			const same = JSON.stringify(text) === JSON.stringify(mine);
			let detail = text.length + ' tutar';
			if (!same) {
				const A = counts(text);
				const B = counts(mine);
				detail = Object.keys(Object.assign({}, A, B)).filter((v) => A[v] !== B[v]).map((v) => v + ': metin=' + (A[v] || 0) + ' gözlem=' + (B[v] || 0)).join(' | ');
			}
			results.push({ pdf: key, page: pg, status: same ? 'EŞİT' : 'FARKLI', detail });
		}
	}
	return { missing: false, results };
}

module.exports = { run, probe, pageNumbers, observedAmounts, BIN };

if (require.main === module) {
	const r = run();
	if (r.missing) {
		console.log('EKSİK BAĞIMLILIK: ' + BIN + ' ' + r.reason + ' — metin katmanı çapraz kontrolü YAPILAMADI (geçti SAYILMAZ). poppler-utils/pdftotext kurun veya MB_PDFTOTEXT verin.');
		process.exit(3);
	}
	for (const x of r.results) console.log(x.status.padEnd(8) + x.pdf + ' s.' + x.page + ' — ' + x.detail);
	process.exit(r.results.some((x) => x.status === 'FARKLI') ? 1 : 0);
}
