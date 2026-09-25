'use strict';
/**
 * Faz 9 — yerelde kayıtlı 57 eski URL'den DETERMİNİSTİK yönlendirme manifesti ve eşleme raporu üretir.
 * Ağ erişimi YOK; canlı siteye bağlanılmaz. Bilinmeyen eski URL UYDURULMAZ: yalnız depoda metin olarak geçen adresler.
 *
 *   node tools/redirects/build-redirect-manifest.js --write   data/redirects/redirects.manifest.json + docs/redirect-mapping-report.md
 *   node tools/redirects/build-redirect-manifest.js --check   diskteki çıktılar üretilenle BYTE-EŞİT mi (aksi hâlde çıkış 1)
 *
 * Tarama kapsamı: depo köküne göre wordpress-site/, tmp/, node_modules/, .git/ HARİÇ metin dosyaları (.md/.js/.html/.json/.txt).
 * Karar tablosu: tools/redirects/known-url-decisions.js — tablo ile tarama sonucu BİREBİR aynı 57 yolu içermelidir;
 * herhangi bir sapma (yeni URL / eksik karar) üretimi BAŞARISIZ kılar.
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const decisions = require('./known-url-decisions');

const ROOT = path.join(__dirname, '..', '..', '..');
const OUT_JSON = path.join(__dirname, '..', '..', 'data', 'redirects', 'redirects.manifest.json');
const OUT_MD = path.join(__dirname, '..', '..', 'docs', 'redirect-mapping-report.md');
const EXPECTED_COUNT = 57;
const URL_RE = /https?:\/\/(?:www\.)?mavibelge\.com\.tr\/[a-zA-Z0-9/_.%-]*/g;
const SKIP_DIRS = new Set(['.git', 'node_modules', 'wordpress-site', 'tmp']);
const EXTS = new Set(['.md', '.js', '.html', '.json', '.txt']);

function walk(dir, files) {
	for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
		if (entry.isDirectory()) {
			if (!SKIP_DIRS.has(entry.name)) walk(path.join(dir, entry.name), files);
		} else if (EXTS.has(path.extname(entry.name).toLowerCase())) {
			files.push(path.join(dir, entry.name));
		}
	}
	return files;
}

function collect() {
	const found = new Map(); // yol -> dosya listesi
	for (const file of walk(ROOT, [])) {
		const text = fs.readFileSync(file, 'utf8');
		for (const m of text.match(URL_RE) || []) {
			const p = m.replace(/^https?:\/\/(?:www\.)?mavibelge\.com\.tr/, '') || '/';
			const list = found.get(p) || new Set();
			list.add(path.relative(ROOT, file).split(path.sep).join('/'));
			found.set(p, list);
		}
	}
	return found;
}

function build() {
	const found = collect();
	const paths = Array.from(found.keys()).sort();
	const errors = [];
	if (paths.length !== EXPECTED_COUNT) {
		errors.push('Beklenen ' + EXPECTED_COUNT + ' benzersiz eski URL, bulunan ' + paths.length + '.');
	}
	paths.forEach((p) => {
		if (!Object.prototype.hasOwnProperty.call(decisions, p)) errors.push('Karar tablosunda YOK: ' + p);
	});
	Object.keys(decisions).forEach((p) => {
		if (!found.has(p)) errors.push('Karar tablosunda var ama depoda geçmiyor: ' + p);
	});
	if (errors.length) {
		console.error('Yönlendirme manifesti üretilemedi:\n  - ' + errors.join('\n  - '));
		process.exit(1);
	}
	const rules = [];
	const counts = { keep: 0, redirect: 0, infrastructure: 0, needs_decision: 0 };
	const rows = [];
	paths.forEach((p) => {
		const d = decisions[p];
		counts[d.decision]++;
		rows.push({ old_path: p, decision: d.decision, origin: d.origin || null, target: d.target || null, rationale: d.rationale });
		if (d.decision === 'redirect') {
			rules.push({
				source: p,
				target: d.target,
				status: d.status,
				origin: d.origin,
				active: d.origin === 'verified', // proposed -> PASİF (kurum/GSC onayı olmadan çalışmaz)
				note: d.rationale.slice(0, 300),
			});
		}
	});
	const sha = crypto.createHash('sha256').update(JSON.stringify(paths)).digest('hex');
	const manifest = {
		schema_version: '1.0.0',
		record_type: 'redirect_rules',
		source: {
			method: 'depoda metin olarak kayıtlı mavibelge.com.tr adresleri (wordpress-site/, tmp/, node_modules/, .git/ hariç); ağ erişimi yok',
			old_url_count: paths.length,
			old_url_list_sha256: sha,
			complete: false,
			note: 'Nihai eski URL envanteri en az 145 adrestir; canlı sitemap, Search Console, sunucu kayıtları, analitik ve backlink kaynakları olmadan tamamlanamaz.',
		},
		counts: Object.assign({ rules: rules.length, active_rules: rules.filter((r) => r.active).length }, counts),
		rules,
	};
	const json = JSON.stringify(manifest, null, 2) + '\n';
	const md = report(manifest, rows, found);
	return { json, md };
}

function report(manifest, rows, found) {
	const L = [];
	L.push('# Eski URL Eşleme Raporu (Faz 9 — yerelde kayıtlı 57 URL)');
	L.push('');
	L.push('> Bu belge `wordpress-site/tools/redirects/build-redirect-manifest.js` tarafından ÜRETİLİR (elle düzenlemeyin).');
	L.push('> Kaynak: depoda metin olarak geçen `mavibelge.com.tr` adresleri; **canlı siteye bağlanılmadı**. Nihai eski URL envanteri (en az 145) canlı sitemap/Search Console/sunucu kaydı/analitik/backlink kaynakları olmadan **tamamlanamaz** — bu rapor o envanterin yerel alt kümesidir.');
	L.push('');
	L.push('## Özet');
	L.push('');
	L.push('| Karar | Adet |');
	L.push('|---|---:|');
	L.push('| Toplam benzersiz eski URL | ' + manifest.source.old_url_count + ' |');
	L.push('| keep (aynı yol yeni sitede var; kural yok) | ' + manifest.counts.keep + ' |');
	L.push('| redirect (kural üretildi) | ' + manifest.counts.redirect + ' |');
	L.push('| ↳ verified (aktif aday) | ' + manifest.rules.filter((r) => r.origin === 'verified').length + ' |');
	L.push('| ↳ proposed (PASİF; onay bekliyor) | ' + manifest.rules.filter((r) => r.origin === 'proposed').length + ' |');
	L.push('| infrastructure (kural yok) | ' + manifest.counts.infrastructure + ' |');
	L.push('| needs_decision (bire bir karşılık DOĞRULANAMADI; kural yok) | ' + manifest.counts.needs_decision + ' |');
	L.push('');
	L.push('Uygulama kuralları: yalnız `origin=verified` kurallar aktif adaydır ve hedef WordPress\'te gerçekten yoksa apply onları da PASİF yazar; `proposed` kurallar kurum/GSC onayı olmadan çalışmaz; toplu ana sayfa yönlendirmesi yoktur; yönlendirme yalnız istek gerçekten 404 ise uygulanır.');
	L.push('');
	L.push('## Karar tablosu');
	L.push('');
	L.push('| Eski yol | Karar | Köken | Hedef | Gerekçe |');
	L.push('|---|---|---|---|---|');
	rows.forEach((r) => {
		L.push('| `' + r.old_path + '` | ' + r.decision + ' | ' + (r.origin || '—') + ' | ' + (r.target ? '`' + r.target + '`' : '—') + ' | ' + r.rationale.replace(/\|/g, '\\|') + ' |');
	});
	L.push('');
	L.push('## Kural-seti özeti');
	L.push('');
	L.push('`data/redirects/redirects.manifest.json` — ' + manifest.counts.rules + ' kural (' + manifest.counts.active_rules + ' aktif aday). Doğrulama ve dry-run: `wp mavibelge redirects import --file=<manifest>` (PHP doğrulayıcısı: çakışma/döngü/zincir/dış hedef/korumalı yol reddi). Apply varsayılan kapalıdır (`MAVIBELGE_REDIRECTS_APPLY_ENABLED`).');
	L.push('');
	return L.join('\n');
}

const mode = process.argv[2];
const out = build();
if (mode === '--write') {
	fs.mkdirSync(path.dirname(OUT_JSON), { recursive: true });
	fs.writeFileSync(OUT_JSON, out.json);
	fs.writeFileSync(OUT_MD, out.md);
	console.log('yazıldı  ' + path.relative(ROOT, OUT_JSON) + '\nyazıldı  ' + path.relative(ROOT, OUT_MD));
} else if (mode === '--check') {
	const okJ = fs.existsSync(OUT_JSON) && fs.readFileSync(OUT_JSON, 'utf8') === out.json;
	const okM = fs.existsSync(OUT_MD) && fs.readFileSync(OUT_MD, 'utf8') === out.md;
	console.log((okJ ? 'EŞİT   ' : 'FARKLI ') + path.relative(ROOT, OUT_JSON) + '\n' + (okM ? 'EŞİT   ' : 'FARKLI ') + path.relative(ROOT, OUT_MD));
	process.exit(okJ && okM ? 0 : 1);
} else {
	console.error('Kullanım: --write | --check');
	process.exit(2);
}
