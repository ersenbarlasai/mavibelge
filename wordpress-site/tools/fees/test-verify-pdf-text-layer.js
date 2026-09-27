#!/usr/bin/env node
'use strict';
/**
 * pdftotext bağımlılık davranışı testleri: araç tek başına eksik bağımlılıkta çıkış 3 + açık mesaj verir ve
 * run-all-gates.sh'in GERÇEK gate() işlevi bu durumu BAŞARISIZ sayar (sessizce geçmez). pdftotext varsa gerçek
 * kontrol 0 ile döner ve Güzellik PDF'i her durumda ATLANDI (görsel doğrulama) olarak raporlanır.
 *
 * Run: node tools/fees/test-verify-pdf-text-layer.js
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const cp = require('child_process');

const WS = path.join(__dirname, '..', '..');
const TOOL = path.join(__dirname, 'verify-pdf-text-layer.js');
let pass = 0;
let fail = 0;
function check(label, ok, detail) {
	if (ok) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + detail + ']' : ''));
	}
}
const runTool = (env) => cp.spawnSync(process.execPath, [TOOL], { cwd: WS, env: Object.assign({}, process.env, env || {}), encoding: 'utf8' });

// 1) eksik bağımlılık: tek başına
const missing = runTool({ MB_PDFTOTEXT: path.join(os.tmpdir(), 'mb-yok-' + process.pid, 'pdftotext') });
check('pdftotext yoksa çıkış kodu 3', missing.status === 3, String(missing.status));
check('pdftotext yoksa açık "EKSİK BAĞIMLILIK" mesajı ve "geçti SAYILMAZ"', /EKSİK BAĞIMLILIK/.test(missing.stdout) && /geçti SAYILMAZ/.test(missing.stdout) && !/EŞİT/.test(missing.stdout), missing.stdout.trim());

// 2) run-all-gates.sh'in GERÇEK gate() işlevi: eksik bağımlılık -> FAIL (sessiz geçiş yok)
const gatesSrc = fs.readFileSync(path.join(WS, 'tools', 'qa', 'run-all-gates.sh'), 'utf8');
const gateFn = (gatesSrc.match(/^gate\(\) \{[\s\S]*?^\}/m) || [''])[0];
check('run-all-gates.sh gate() işlevi bulundu ve kapı listesinde metin katmanı kontrolü var', gateFn !== '' && /node tools\/fees\/verify-pdf-text-layer\.js/.test(gatesSrc));
const outDir = fs.mkdtempSync(path.join(os.tmpdir(), 'mbgate-'));
const bashRun = (envExtra) => cp.spawnSync('bash', ['-c', gateFn + '\nOUT="$1"; FAILS=0; gate "pdf katmanı" node tools/fees/verify-pdf-text-layer.js; echo "FAILS=$FAILS"', 'x', outDir.replace(/\\/g, '/')], { cwd: WS, env: Object.assign({}, process.env, envExtra), encoding: 'utf8' });
const g1 = bashRun({ MB_PDFTOTEXT: '/mb-yok/pdftotext' });
check('gate(): pdftotext yokken kapı FAIL ve FAILS=1', !g1.error && /^FAIL  pdf katmanı :: EKSİK BAĞIMLILIK/m.test(g1.stdout) && /FAILS=1/.test(g1.stdout), (g1.error ? String(g1.error.code) : '') + g1.stdout.trim());

// 3) pdftotext varsa gerçek kontrol
const real = runTool({});
if (/EKSİK BAĞIMLILIK/.test(real.stdout)) {
	check('pdftotext bu makinede YOK — gerçek metin katmanı kontrolü yapılamadı (başarısız sayılır)', false, real.stdout.trim());
} else {
	check('pdftotext mevcut: Yeni Meslekler 4/4 sayfa EŞİT, çıkış 0', real.status === 0 && (real.stdout.match(/^EŞİT +yeni s\.[1-4] /gm) || []).length === 4, real.stdout.trim());
	check('Güzellik PDF iki sayfası ATLANDI (metin katmanında fiyat yok; görsel doğrulama)', (real.stdout.match(/^ATLANDI +guz s\.[12] /gm) || []).length === 2);
	const g2 = bashRun({});
	check('gate(): pdftotext varken kapı PASS ve FAILS=0', /^PASS  pdf katmanı/m.test(g2.stdout) && /FAILS=0/.test(g2.stdout), g2.stdout.trim());
}
fs.rmSync(outDir, { recursive: true, force: true });

console.log('\n' + pass + '/' + (pass + fail) + ' pdftotext bağımlılık testi geçti.');
process.exit(fail ? 1 : 0);
