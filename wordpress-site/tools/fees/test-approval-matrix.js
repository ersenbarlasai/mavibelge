#!/usr/bin/env node
'use strict';
/**
 * Ücret onay matrisi üreticisi testleri (salt okunur; ağ/WordPress yok):
 * determinizm, 103 kayıt tam kapsam, 84/19 ve 96/7 dağılımı, eksik/fazla/çift kayıt reddi, PDF sayfa kapsamı,
 * Türkçe karakter korunumu, kurum kararı önceden doldurulmamış, üretilen dosyaların diskle byte-eşitliği.
 *
 * Run: node tools/fees/test-approval-matrix.js
 */
const fs = require('fs');
const path = require('path');
const { build, renderOutputs, loadInputs, normName, extractCodes, canonicalJson, recordDigest, OUT_DIR, DATA_DIR, PUBLICATION_FILE, HOLD_REASONS, PUBLICATION_NOTES } = require('./build-approval-matrix.js');

let pass = 0;
let fail = 0;
function check(label, ok, detail) {
	if (ok) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + detail + ']' : ''));
	}
}
const clone = (o) => JSON.parse(JSON.stringify(o));
const WS = path.join(__dirname, '..', '..');
const fees = JSON.parse(fs.readFileSync(path.join(WS, 'data', 'content', 'fees.manifest.json'), 'utf8'));
const quals = JSON.parse(fs.readFileSync(path.join(WS, 'data', 'content', 'qualifications.manifest.json'), 'utf8'));
const sectors = JSON.parse(fs.readFileSync(path.join(WS, 'data', 'content', 'sectors.manifest.json'), 'utf8'));
const obs = JSON.parse(fs.readFileSync(path.join(__dirname, 'pdf-observations.json'), 'utf8'));
const base = (over) => Object.assign({ fees: clone(fees), quals: clone(quals), sectors: clone(sectors), obs: clone(obs) }, over || {});
const throwsOn = (opts) => {
	try {
		renderOutputs(opts);
		return null;
	} catch (e) {
		return e.message;
	}
};

// --- gerçek girdilerle
const A = renderOutputs();
const B = renderOutputs();
check('deterministik: iki üretim byte-eşit (tüm çıktılar)', Object.keys(A.outputs).every((k) => A.outputs[k] === B.outputs[k]) && Object.keys(A.outputs).length === 7);
const S = A.S;
check('103 kaydın tamamı kapsandı (satır sayısı, benzersiz source_key)', A.rows.length === 103 && new Set(A.rows.map((r) => r.rec.source_key)).size === 103);
check('kaynak dağılımı 96 + 7', S.bySource['2026 Yeni Meslekler Ücret Tarifesi'] === 96 && S.bySource['2026 Güzellik Fiyat Listesi'] === 7);
check('bağlantı dağılımı 84 + 19', S.linked === 84 && S.unlinked === 19 && S.distinctQualifications === 83);
check('seçenek sayıları: 145 toplam, 87 tek, 16 çok seçenekli (58 seçenek); pricing_type single=87', S.options === 145 && S.singleOption === 87 && S.multiOption === 16 && S.multiOptionsTotal === 58 && S.single === 87 && S.notSingle === 16);
check('manifest sayaç bloğu ile bağımsız yeniden sayım mutabık', S.reconcileOk === true);
check('karar toplamı 103 ve yalnız dört izinli değer', Object.keys(S.verdict).sort().join(',') === 'DECISION_REQUIRED,MISMATCH,UNREADABLE,VERIFIED' && Object.values(S.verdict).reduce((a, b) => a + b, 0) === 103);
check('her bağlantısız kayıt DECISION_REQUIRED (veya daha kötü); hiçbiri VERIFIED değil', A.rows.filter((r) => !r.linked).every((r) => r.v.verdict !== 'VERIFIED') && A.rows.filter((r) => !r.linked).length === 19);
check('Faz 15: Güzellik 7 kayıt KDV belirsizliği 2026-10-19 kurum kararıyla çözüldü -> VERIFIED (KURUM_KARARI, yalnız INFO notu)', A.rows.filter((r) => r.obs.pdf === 'guz').length === 7 && A.rows.filter((r) => r.obs.pdf === 'guz').every((r) => r.v.checks.vat === 'KURUM_KARARI' && r.v.verdict === 'VERIFIED' && r.v.diffs.every((d) => d.kind === 'INFO')));
check('Faz 15: KDV/basım kuralı 103 kaydın tamamında kurum kararıyla tutarlı (aykırı kayıt yok)', A.rows.every((r) => r.v.checks.vat === 'OK' || r.v.checks.vat === 'KURUM_KARARI') && S.vatInstitution === 7);
check('Faz 15: kurum kararı sütunu — 103 satırda KDV/basım ONAY; yayın UYGUN 103 / BEKLIYOR 0 (önceden toplu "ONAY" yok)', (function () {
	const lines = A.outputs['onay-matrisi.csv'].replace(/^﻿/, '').split('\r\n').filter(Boolean).slice(1);
	return lines.length === 103 && lines.every((l) => /KDV\/basım: ONAY \(2026-10-19\); yayın: (UYGUN|BEKLIYOR)/.test(l))
		&& lines.filter((l) => /yayın: UYGUN/.test(l)).length === 103 && lines.filter((l) => /yayın: BEKLIYOR/.test(l)).length === 0;
})());
check('Faz 15: yayına uygunluk yalnız "YAYINA UYGUN — etkinleştirme ayrı, onaylı işlemdir" veya "BEKLETİLİR: <neden>"', A.rows.every((r) => /^BEKLETİLİR: /.test(r.eligibility) || /^YAYINA UYGUN.*etkinleştirme ayrı, onaylı işlemdir$/.test(r.eligibility)));
check('Faz 15: 2026-10-20 onayı altı kaydın ad ve çift bağlantı bekletmesini çözer', (function () {
	const held = A.rows.filter((r) => 'hold' === r.pub.decision).map((r) => r.i + 1).join(',');
	const dup = A.rows.filter((r) => r.pub.hold_reasons.indexOf('duplicate_qualification_link') !== -1).map((r) => r.i + 1).join(',');
	const name = A.rows.filter((r) => r.pub.hold_reasons.indexOf('name_differs_from_pdf') !== -1).map((r) => r.i + 1).join(',');
	return held === '' && dup === '' && name === '' && S.publish === 103 && S.hold === 0;
})());
check('Faz 15: bağlantısız 19 kayıt listelenir (not: unlinked_listed_without_qualification), #87 onayla kodsuz yayımlanır; MYK kodu kararı açık (verdict DECISION_REQUIRED kalır)',
	S.publishUnlinked === 19 && A.rows.filter((r) => !r.linked && 'publish' === r.pub.decision).every((r) => r.pub.notes.indexOf('unlinked_listed_without_qualification') !== -1 && r.v.verdict === 'DECISION_REQUIRED')
	&& 'publish' === A.rows[86].pub.decision && !A.rows[86].linked);
check('Faz 15: yayın kararı manifesti — 103 kayıt, sıra/source_key manifestle birebir, record_sha256 kanonik kayıt özeti, kapalı kod sözlükleri', (function () {
	const pm = JSON.parse(A.dataOutputs[PUBLICATION_FILE]);
	return pm.schema_version === '1.0.0' && pm.record_type === 'fee_publication' && pm.count === 103 && pm.records.length === 103 && pm.decided_on === '2026-10-20'
		&& pm.vat_rule.included === true && pm.vat_rule.rate_percent === 20 && pm.vat_rule.certificate_print_fee_kurus === 150000
		&& pm.records.every((x, i) => x.source_index === i && x.source_key === fees.records[i].source_key && x.record_sha256 === recordDigest(fees.records[i]) && /^[0-9a-f]{64}$/.test(x.record_sha256)
			&& (x.decision === 'publish') === (x.hold_reasons.length === 0) && x.hold_reasons.every((c) => HOLD_REASONS.indexOf(c) !== -1) && x.notes.every((c) => PUBLICATION_NOTES.indexOf(c) !== -1))
		&& pm.records.filter((x) => x.decision === 'publish').length === 103;
})());
check('Faz 15: kanonik JSON anahtarları özyinelemeli sıralar, liste sırasını korur, float reddeder', canonicalJson({ b: 1, a: [{ d: 'x', c: null }, 2] }) === '{"a":[{"c":null,"d":"x"},2],"b":1}' && (function () { try { canonicalJson({ a: 1.5 }); return false; } catch (e) { return true; } })());
check('Faz 15: yayın kararı manifesti diskteki dosyayla byte-eşit ve üretim deterministik', fs.readFileSync(path.join(DATA_DIR, PUBLICATION_FILE), 'utf8') === A.dataOutputs[PUBLICATION_FILE] && A.dataOutputs[PUBLICATION_FILE] === B.dataOutputs[PUBLICATION_FILE]);
check('MYK kodu üretilmedi: matristeki kod sütunu manifestteki qualification_code ile birebir', A.rows.every((r) => (r.rec.qualification_code || '') === (fees.records[r.i].qualification_code || '')) && A.rows.filter((r) => !r.rec.qualification_code).length === 19);
check('PDF sayfa kapsamı: Yeni 4 sayfa (32/21/30/13), Güzellik sayfa 2 = 7; her sayfa ≥1 kayıt', S.byPdfPage['yeni|1'] === 32 && S.byPdfPage['yeni|2'] === 21 && S.byPdfPage['yeni|3'] === 30 && S.byPdfPage['yeni|4'] === 13 && S.byPdfPage['guz|2'] === 7 && Object.keys(S.byPdfPage).length === 5);
check('her kaydın source_page değeri PDF gözlemiyle eşit', A.rows.every((r) => r.v.checks.page === 'OK'));
check('Türkçe karakterler korunur (CSV BOM + Ğ/İ/Ş/Ü/Ö/Ç içeren ad ve etiketler)', (function () {
	const c = A.outputs['onay-matrisi.csv'];
	return c.charCodeAt(0) === 0xfeff && /Güzellik Uzmanı/.test(c) && /İplik Bitim İşleri Operatörü/.test(c) && /Tezgâh İşçisi/.test(c) && /Şardon/.test(c) && /Çözgü/.test(c) && !/�/.test(c) && /Öğ|Ön Terbiye/.test(c);
})());
check('Markdown çıktıları Türkçe karakter içerir ve şablon değişkeni kalmadı', Object.keys(A.outputs).every((k) => !/\{\{[A-Z0-9_]+\}\}/.test(A.outputs[k]) && !/�/.test(A.outputs[k])));
check('bağlantısız CSV: 19 satır + başlık', A.outputs['baglantisiz-19-kayit.csv'].replace(/^﻿/, '').split('\r\n').filter(Boolean).length === 20);
check('üretilen dosyalar diskteki dosyalarla byte-eşit (yoksa önce --write)', Object.keys(A.outputs).every((k) => fs.existsSync(path.join(OUT_DIR, k)) && fs.readFileSync(path.join(OUT_DIR, k), 'utf8') === A.outputs[k]));
check('normName: Türkçe büyük/küçük ve noktalama farkını yok sayar; farklı adı ayırır', normName('Isı Yalıtımcısı') === normName('ISI YALITIMCISI') && normName('Liman Kuru Yük Operasyon Elemanı/Puantör') === normName('LİMAN KURU YÜK OPERASYON ELEMANI (PUANTÖR)') && normName('ISI YALITIMCI') !== normName('Isı Yalıtımcısı'));
check('extractCodes: tekil, aralık (B1-B9) ve karışık etiketleri açar', extractCodes(['B1-B9 birimlerinden']).length === 9 && extractCodes(['A1+B1+B2']).join(',') === 'A1,B1,B2' && extractCodes(['Tek birim (B1/B2/B3)']).join(',') === 'B1,B2,B3');

check('Onay kayıt özetine bağlı: onaylı kayıtta değişiklik ad farkı bekletmesini geri getirir', (function () {
 const rec = clone(A.rows[67].rec); rec.price_options[0].amount_kurus += 1;
 const result = require('./build-approval-matrix.js').publicationDecision(rec, A.rows[67].v, A.rows[67].linked, { qLinkCount: new Map() });
 return result.decision === 'hold' && result.hold_reasons.includes('name_differs_from_pdf');
})());
// --- negatif testler (fail-closed)
let o = base();
o.fees.records.pop();
check('eksik kayıt (102) reddedilir', /103 kayıt beklenir/.test(throwsOn(o) || ''));
o = base();
o.fees.records.push(clone(o.fees.records[0]));
check('fazla kayıt (104) reddedilir', /103 kayıt beklenir/.test(throwsOn(o) || ''));
o = base();
o.fees.records[5].source_key = o.fees.records[4].source_key;
check('çift source_key reddedilir', /çift source_key/.test(throwsOn(o) || ''));
o = base();
o.obs.entries.pop();
check('eksik PDF gözlemi reddedilir', /gözlem eksik/.test(throwsOn(o) || ''));
o = base();
o.obs.entries.push(clone(o.obs.entries[3]));
check('çift PDF gözlemi reddedilir', /gözlem çift/.test(throwsOn(o) || ''));
o = base();
o.obs.entries[10].i = 999;
check('manifestte olmayan gözlem reddedilir', /gözlem fazla/.test(throwsOn(o) || ''));
o = base();
o.obs.pdfs.yeni.sha256 = '0'.repeat(64);
check('PDF SHA-256 değişirse durur (transkripsiyon geçersiz)', /SHA-256 değişmiş/.test(throwsOn(o) || ''));
const mut = (fn) => {
	const x = base();
	fn(x);
	const inp = loadInputs(Object.assign({}, x, { skipPdfHash: true }));
	return build(Object.assign({}, x, { skipPdfHash: true })).rows.map((r) => r.v.verdict).concat([inp ? 'x' : '']);
};
check('tutar farkı -> MISMATCH', mut((x) => { x.fees.records[13].price_options[0].amount_kurus += 100; x.fees.records[13].min_amount_kurus += 100; x.fees.records[13].max_amount_kurus += 100; })[13] === 'MISMATCH');
check('kaynak sayfa farkı -> MISMATCH', mut((x) => { x.fees.records[14].source_page = 3; })[14] === 'MISMATCH');
check('seviye farkı -> MISMATCH', mut((x) => { x.fees.records[15].level = 4; })[15] === 'MISMATCH');
check('belge basım ücreti farkı -> MISMATCH', mut((x) => { x.fees.records[20].certificate_print_fee_kurus = 100000; })[20] === 'MISMATCH');
check('birim kodu farkı -> MISMATCH', mut((x) => { x.fees.records[10].price_options[0].label = 'Sınav ücreti (B1-Tornalama)'; })[10] === 'MISMATCH');
check('bağlı yeterlilik kodu farkı -> MISMATCH', mut((x) => { x.quals.records.find((q) => q.source_key === x.fees.records[25].qualification_source_key).level = 4; })[25] === 'MISMATCH');
check('KDV çelişkisi (PDF: dahil, manifest: hariç) -> MISMATCH', mut((x) => { x.fees.records[30].vat_included = false; })[30] === 'MISMATCH');
check('Faz 15: Güzellik kaydında manifest KDV hariç derse kurum kararına aykırı -> MISMATCH (sessiz düzeltme yok)', mut((x) => { x.fees.records[2].vat_included = false; })[2] === 'MISMATCH');
const pubOf = (fn) => {
	const x = base();
	fn(x);
	return build(Object.assign({}, x, { skipPdfHash: true })).rows;
};
check('Faz 15: MISMATCH kayıt yayın kararında bekletilir (pdf_mismatch)', (function () { const r = pubOf((x) => { x.fees.records[14].source_page = 3; })[14]; return 'hold' === r.pub.decision && r.pub.hold_reasons.indexOf('pdf_mismatch') !== -1; })());
check('Faz 15: ad farkı oluşturulursa kayıt bekletilir (name_differs_from_pdf)', (function () { const r = pubOf((x) => { x.obs.entries[40].name = 'TAMAMEN FARKLI AD'; })[40]; return 'hold' === r.pub.decision && r.pub.hold_reasons.indexOf('name_differs_from_pdf') !== -1; })());

// --- UNREADABLE (okunabilirlik açık ve fail-closed)
check('mevcut 103 gözlemin hepsi açıkça readable; sayaçlar 79/0/0/24 (Faz 15: 7 Güzellik kaydı KDV kurum kararıyla VERIFIED)', obs.entries.length === 103 && obs.entries.every((e) => e.legibility === 'readable') && S.verdict.VERIFIED === 79 && S.verdict.MISMATCH === 0 && S.verdict.UNREADABLE === 0 && S.verdict.DECISION_REQUIRED === 24);
const unreadable = (x, i) => {
	Object.assign(x.obs.entries[i], { legibility: 'unreadable', name: null, level: null, cells: [], codes: [], note: 'test: sayfa bulanık' });
};
const vOf = (fn) => {
	const x = base();
	fn(x);
	const o2 = Object.assign({}, x, { skipPdfHash: true });
	return build(o2);
};
let U = vOf((x) => unreadable(x, 13)); // #14 Makine Montajcısı (VERIFIED idi)
check('UNREADABLE üretilir: okunamayan gözlem VERIFIED değil, UNREADABLE; sayaç 78/0/1/24', U.rows[13].v.verdict === 'UNREADABLE' && U.S.verdict.VERIFIED === 78 && U.S.verdict.UNREADABLE === 1 && U.S.verdict.DECISION_REQUIRED === 24, JSON.stringify(U.S.verdict));
check('UNREADABLE kayıt yayına aday değil (BEKLETİLİR: PDF okunamadı)', /^BEKLETİLİR: .*PDF okunamadı/.test(U.rows[13].eligibility) && 'hold' === U.rows[13].pub.decision);
check('UNREADABLE özet satırı ve fark listesinde görünür', /`UNREADABLE`: 1 kayıt okunamadı/.test(renderOutputs(Object.assign(base(), { skipPdfHash: true, obs: (function () { const x = base(); unreadable(x, 13); return x.obs; })() })).outputs['dogrulama-ozeti.md']));
U = vOf((x) => unreadable(x, 16)); // #17 bağlantısız (DECISION_REQUIRED idi)
check('öncelik UNREADABLE > DECISION_REQUIRED (bağlantısız + okunamaz -> UNREADABLE)', U.rows[16].v.verdict === 'UNREADABLE');
U = vOf((x) => { unreadable(x, 20); x.fees.records[20].source_page = 3; });
check('öncelik MISMATCH > UNREADABLE (okunamaz + sayfa farkı -> MISMATCH)', U.rows[20].v.verdict === 'MISMATCH');
o = base();
Object.assign(o.obs.entries[13], { legibility: 'unreadable', name: null, level: null, codes: [] }); // cells (fiyat) bırakıldı
check('okunamayan gözlemde elle fiyat uydurma reddedilir', /okunamayan gözlemde fiyat\/birim\/ad\/seviye bulunamaz/.test(throwsOn(o) || ''));
o = base();
delete o.obs.entries[13].legibility;
check('okunabilirliği tanımsız gözlem reddedilir', /okunabilirliği tanımsız/.test(throwsOn(o) || ''));
o = base();
o.obs.pdfs.yeni.unreadable_pages = [1];
check('okunamayan sayfadaki gözlem readable işaretlenemez', /okunamayan sayfadaki gözlem readable işaretlenemez/.test(throwsOn(o) || ''));

console.log('\n' + pass + '/' + (pass + fail) + ' ücret onay matrisi testi geçti.');
process.exit(fail ? 1 : 0);
