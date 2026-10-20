#!/usr/bin/env node
'use strict';
/**
 * Faz 15 — ücret yayınının ÇALIŞMA ANINDA okuduğu veri dosyaları için AYRI, DETERMİNİSTİK dağıtım paketi (DEPLOY DEĞİL: sunucuya
 * bağlantı YOKTUR). Tema/eklenti zip'lerinde bu dosyalar yoktur; eklenti onları `MAVIBELGE_IMPORT_MANIFEST_DIR` dizininden okur.
 *
 *   node tools/package/build-fee-data-package.js --write   dist-packages/ altına zip + iki checksum dosyası + içerik manifesti yazar
 *   node tools/package/build-fee-data-package.js --check   diskteki dosyalar kaynaktan üretilenle BYTE-EŞİT mi (aksi hâlde çıkış 1)
 *
 * İçerik YALNIZ şudur (kaynak/rapor/şema/PDF/eşleme dosyası GİRMEZ):
 *   mavibelge-data/.htaccess                                   web erişimini kapatır (dizin yanlışlıkla web kökü altına konursa)
 *   mavibelge-data/index.php, mavibelge-data/content/index.php dizin listelemeye karşı boş dosya
 *   mavibelge-data/content/{sectors,qualifications,fees}.manifest.json + fee-publication.manifest.json
 * Dosya adı içeriğe bağlıdır (karar tarihi + dört JSON'un özetinin ilk 8 hanesi): farklı içerik ASLA aynı adla yazılmaz, eski paket ezilmez.
 * Zip biçimi build-packages.js ile aynıdır (sıralı girişler, 1980-01-01, 0644, deflate 9).
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { buildZip } = require('./build-packages');

const ROOT = path.join(__dirname, '..', '..');
const OUT = process.env.MB_PACKAGE_OUT ? path.resolve(process.env.MB_PACKAGE_OUT) : path.join(ROOT, 'dist-packages');
const DATA = path.join(ROOT, 'data', 'content');
/** class-import-fee-publisher.php REQUIRED_FILES ile birebir (test-fee-data-package.js doğrular). */
const REQUIRED_FILES = ['sectors.manifest.json', 'qualifications.manifest.json', 'fees.manifest.json', 'fee-publication.manifest.json'];
const PREFIX = 'mavibelge-data';
const HTACCESS = [
	'# Mavi Belge veri dizini: yalnız PHP (mavibelge-core) okur; web erişimi kapalı.',
	'<IfModule mod_authz_core.c>',
	'\tRequire all denied',
	'</IfModule>',
	'<IfModule !mod_authz_core.c>',
	'\tOrder allow,deny',
	'\tDeny from all',
	'</IfModule>',
	'',
].join('\n');
const SILENCE = '<?php\n// Silence is golden.\n';

function sha256(buf) {
	return crypto.createHash('sha256').update(buf).digest('hex');
}

function build() {
	const json = REQUIRED_FILES.map((f) => ({ name: PREFIX + '/content/' + f, source: 'wordpress-site/data/content/' + f, data: fs.readFileSync(path.join(DATA, f)) }));
	const decisions = JSON.parse(json[3].data.toString('utf8'));
	if (!/^\d{4}-\d{2}-\d{2}$/.test(decisions.decided_on)) throw new Error('fee-publication.manifest.json: decided_on okunamadı');
	const contentId = sha256(Buffer.from(json.map((e) => sha256(e.data) + '  ' + e.name).join('\n'))).slice(0, 8);
	const base = 'mavibelge-ucret-verisi-' + decisions.decided_on.replace(/-/g, '') + '-' + contentId;
	const entries = [
		{ name: PREFIX + '/.htaccess', data: Buffer.from(HTACCESS) },
		...json.map((e) => ({ name: e.name, data: e.data })),
		{ name: PREFIX + '/content/index.php', data: Buffer.from(SILENCE) },
		{ name: PREFIX + '/index.php', data: Buffer.from(SILENCE) },
	].sort((a, b) => (a.name < b.name ? -1 : 1));
	const zip = buildZip(entries);
	const fees = JSON.parse(json[2].data.toString('utf8'));
	const manifest = {
		schema_version: '1.0.0',
		deterministic: true,
		package: base + '.zip',
		zip_sha256: sha256(zip),
		purpose: 'mavibelge-core ücret yayını (Araçlar > İçe Aktarım Dry-Run > 6) çalışma anı verisi; MAVIBELGE_IMPORT_MANIFEST_DIR = <kurulum>/mavibelge-data/content',
		required_files: REQUIRED_FILES,
		decisions: {
			decided_on: decisions.decided_on,
			count: decisions.count,
			publish: decisions.records.filter((r) => r.decision === 'publish').length,
			hold: decisions.records.filter((r) => r.decision === 'hold').length,
		},
		fees: { count: fees.count, price_options: fees.records.reduce((n, r) => n + r.price_options.length, 0) },
		files: entries.map((e) => {
			const src = json.find((j) => j.name === e.name);
			return { path: e.name, bytes: e.data.length, sha256: sha256(e.data), source: src ? src.source : 'build-fee-data-package.js (sabit)' };
		}),
	};
	const outputs = {};
	outputs[base + '.zip'] = zip;
	outputs[base + '.zip.sha256'] = Buffer.from(sha256(zip) + '  ' + base + '.zip\n');
	outputs[base + '-icerik.sha256'] = Buffer.from(entries.map((e) => sha256(e.data) + '  ' + e.name).join('\n') + '\n');
	outputs[base + '-manifest.json'] = Buffer.from(JSON.stringify(manifest, null, 2) + '\n');
	return { base, outputs, entries, manifest };
}

module.exports = { build, REQUIRED_FILES, PREFIX };

if (require.main === module) {
	const mode = process.argv[2];
	const { outputs } = build();
	if (mode === '--write') {
		fs.mkdirSync(OUT, { recursive: true });
		for (const [name, buf] of Object.entries(outputs)) {
			const p = path.join(OUT, name);
			// İçerik adı içerik özetine bağlı: aynı adlı dosya varsa içerik de aynı olmalı (aksi hâlde EZMEZ, hata verir).
			if (fs.existsSync(p) && !fs.readFileSync(p).equals(buf)) {
				console.error('HATA: dist-packages/' + name + ' farklı içerikle zaten var; ezilmedi.');
				process.exit(1);
			}
			fs.writeFileSync(p, buf);
			console.log('yazıldı  dist-packages/' + name + ' (' + buf.length + ' bayt)');
		}
	} else if (mode === '--check') {
		let bad = 0;
		for (const [name, buf] of Object.entries(outputs)) {
			const p = path.join(OUT, name);
			const same = fs.existsSync(p) && fs.readFileSync(p).equals(buf);
			console.log((same ? 'EŞİT   ' : 'FARKLI ') + 'dist-packages/' + name);
			if (!same) bad++;
		}
		process.exit(bad ? 1 : 0);
	} else {
		console.error('Kullanım: --write | --check');
		process.exit(2);
	}
}
