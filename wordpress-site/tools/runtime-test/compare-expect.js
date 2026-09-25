// Host tarafı: fixture'ların ÖNCEDEN yazdığı beklenen kararları gerçek CLI JSON dry-run çıktısıyla karşılaştırır.
// Kullanım: node compare-expect.js <expect.json> <cli.json> [expect-6b3.json]
//   expect-6b3.json: {"overrides": {...}, "added": {...}} — overrides yalnız temelde VAR olan anahtarı,
//   added yalnız temelde OLMAYAN anahtarı değiştirebilir/ekleyebilir (sessiz çakışma yok).
'use strict';
const fs = require('fs');
const exp = Object.assign({}, JSON.parse(fs.readFileSync(process.argv[2], 'utf8')).expect);
const cli = JSON.parse(fs.readFileSync(process.argv[3], 'utf8').replace(/^[^{]*/, ''));
if (process.argv[4]) {
	const extra = JSON.parse(fs.readFileSync(process.argv[4], 'utf8'));
	for (const [k, v] of Object.entries(extra.overrides || {})) {
		if (!(k in exp)) { throw new Error('override temelde yok: ' + k); }
		exp[k] = v;
	}
	for (const [k, v] of Object.entries(extra.added || {})) {
		if (k in exp) { throw new Error('added temelde zaten var (override kullanılmalı): ' + k); }
		exp[k] = v;
	}
}
const byKey = Object.fromEntries(cli.entries.map((e) => [e.source_key, e]));
let fail = 0;
for (const [key, [decision, reason, scenario]] of Object.entries(exp)) {
	const e = byKey[key];
	const ok = e && e.decision === decision && e.reason === reason;
	if (!ok) { fail++; }
	const extra = e ? ' target_id=' + e.target_id + (e.changed_fields.length ? ' changed=' + e.changed_fields.join(',') : '') + (e.unresolved_dependencies.length ? ' unresolved=' + e.unresolved_dependencies.join(',') : '') + (e.natural_key_check !== undefined ? ' natural_key_check=' + e.natural_key_check : '') : '';
	console.log((ok ? 'PASS  ' : 'FAIL  ') + '[' + scenario + '] ' + key + ' beklenen=' + decision + '/' + reason + ' gerçek=' + (e ? e.decision + '/' + e.reason : 'YOK') + extra);
}
console.log('\n' + (Object.keys(exp).length - fail) + '/' + Object.keys(exp).length + ' senaryo beklentisi karşılandı.');
process.exit(fail ? 1 : 0);
