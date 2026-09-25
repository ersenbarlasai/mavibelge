// Host tarafı: `wp mavibelge import catalog --format=json` çıktısını özetler.
// Kullanım: node summarize-plan.js <json-dosyası>
'use strict';
const fs = require('fs');
const raw = fs.readFileSync(process.argv[2], 'utf8');
const start = raw.indexOf('{');
const j = JSON.parse(raw.slice(start));
const byType = {};
const byDecisionReason = {};
for (const e of j.entries) {
	byType[e.type] = (byType[e.type] || 0) + 1;
	const k = e.type + ' ' + e.decision + '/' + e.reason;
	byDecisionReason[k] = (byDecisionReason[k] || 0) + 1;
}
const ops = j.summary.operations;
const opsTotal = Object.values(ops).reduce((a, b) => a + b, 0);
const leakRe = /\/var\/www|\/home\/|C:\\|password|DB_PASSWORD|AUTH_KEY/i;
console.log(JSON.stringify({
	read_only: j.read_only,
	load_errors: j.load_errors.length,
	plan_errors: j.plan_errors.length,
	entries: j.entries.length,
	by_type: byType,
	summary_total: j.summary.total,
	operations: ops,
	operations_sum_equals_total: opsTotal === j.summary.total,
	structurally_valid: j.summary.structurally_valid,
	applicable: j.summary.applicable,
	diagnostics: j.diagnostics,
	diagnostics_closed_shape: j.diagnostics.every((d) => Object.keys(d).sort().join(',') === 'code,source_key,type'),
	path_or_secret_leak: leakRe.test(raw),
	by_decision_reason: byDecisionReason,
}, null, 1));
