'use strict';
/**
 * Faz 12 — pages.manifest.json doğrulaması (salt okunur): envanter çapraz denetimi + manifest doğrulayıcı + TAZE üretim ile disk
 * byte-eşitliği + şema dosyasının kayıt anahtarlarıyla tutarlılığı. Hata varsa çıkış 1.
 * Run: node wordpress-site/tools/import/verify-page-manifest.js
 */
const fs = require('fs');
const path = require('path');
const { buildPageManifest } = require('./build-page-manifest');
const { validatePageManifest, RECORD_KEYS, REPO_ROOT } = require('./lib/validate-page-set');
const { toDeterministicJson } = require('./lib/hash');

const errors = [];
const file = path.join(REPO_ROOT, 'wordpress-site', 'data', 'content', 'pages.manifest.json');
const built = buildPageManifest();
built.errors.forEach((e) => errors.push('üretim: ' + e));
if (!fs.existsSync(file)) {
	errors.push('pages.manifest.json yok');
} else {
	const disk = fs.readFileSync(file, 'utf8');
	let parsed = null;
	try {
		parsed = JSON.parse(disk);
	} catch (e) {
		errors.push('pages.manifest.json JSON değil');
	}
	if (parsed) {
		validatePageManifest(parsed).forEach((e) => errors.push('disk: ' + e));
	}
	if (built.errors.length === 0 && disk !== toDeterministicJson(built.manifest)) {
		errors.push('disk manifesti taze üretimle byte-eşit değil (node tools/import/build-page-manifest.js --write)');
	}
}
const schema = JSON.parse(fs.readFileSync(path.join(REPO_ROOT, 'wordpress-site', 'data', 'schema', 'page.schema.json'), 'utf8'));
if (JSON.stringify(schema.required) !== JSON.stringify(RECORD_KEYS) || JSON.stringify(Object.keys(schema.properties)) !== JSON.stringify(RECORD_KEYS)) {
	errors.push('page.schema.json anahtar kümesi/sırası doğrulayıcının RECORD_KEYS değeriyle uyuşmuyor');
}
if (errors.length) {
	process.stderr.write('Sayfa manifest doğrulaması BAŞARISIZ:\n' + errors.map((e) => '  - ' + e).join('\n') + '\n');
	process.exit(1);
}
console.log('pages.manifest.json doğrulandı: 32 kayıt, envanter == statik dosyalar == page-template-map.md == tema haritası, taze üretim == disk (byte).');
