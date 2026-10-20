#!/usr/bin/env node
/**
 * Geçici yönlendirme loader ZIP'ini üretir (tek kullanımlık canlı operasyon paketi).
 *
 *   node wordpress-site/tools/ops/redirect-bootstrap/build-package.js           # yalnız özet (yazmaz)
 *   node wordpress-site/tools/ops/redirect-bootstrap/build-package.js --write   # dist/ altına ZIP yazar
 *
 * ZIP kökünde yalnız iki dosya bulunur; DirectAdmin File Manager'da `wp-content/mu-plugins/` içinde
 * çıkarıldığında dosyalar doğrudan doğru konuma gelir:
 *   mavibelge-redirect-bootstrap.php   (bu dizindeki denetlenmiş kaynak, bayt-eşit)
 *   redirects.manifest.json            (yetkili wordpress-site/data/redirects kopyası, bayt-eşit)
 * Manifest SHA-256 değeri onaylı değerle eşleşmezse paket ÜRETİLMEZ. Deterministiktir (sabit tarih/sıra).
 * Çıktı dizini (.zip) Git'e girmez (wordpress-site/.gitignore: *.zip).
 */
'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const crypto = require('crypto');

const HERE = __dirname;
const SITE = path.resolve(HERE, '..', '..', '..');
const LOADER_SRC = path.join(HERE, 'mavibelge-redirect-bootstrap.php');
const MANIFEST_SRC = path.join(SITE, 'data', 'redirects', 'redirects.manifest.json');
const EXPECTED_MANIFEST_SHA256 = 'ebd84ce828efbc1b12490b79b838d6534c70141bf04da849aab7070d34597792';
const ENTRY_NAMES = ['mavibelge-redirect-bootstrap.php', 'redirects.manifest.json'];
const OUT_DIR = path.join(HERE, 'dist');

const sha256 = (buf) => crypto.createHash('sha256').update(buf).digest('hex');

function loaderVersion(src) {
	const m = src.toString('utf8').match(/const VERSION\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'/);
	if (!m) throw new Error('Loader VERSION sabiti okunamadı');
	return m[1];
}

/** Deterministik ZIP (deflate/store, 1980-01-01 00:00, UTF-8 adlar, 0644). */
function buildZip(entries) {
	const locals = [];
	const centrals = [];
	let offset = 0;
	for (const e of entries) {
		const name = Buffer.from(e.name, 'utf8');
		const raw = e.data;
		const deflated = zlib.deflateRawSync(raw, { level: 9 });
		const useDeflate = deflated.length < raw.length;
		const body = useDeflate ? deflated : raw;
		const method = useDeflate ? 8 : 0;
		const crc = zlib.crc32(raw) >>> 0;
		const lh = Buffer.alloc(30);
		lh.writeUInt32LE(0x04034b50, 0);
		lh.writeUInt16LE(20, 4);
		lh.writeUInt16LE(0x0800, 6);
		lh.writeUInt16LE(method, 8);
		lh.writeUInt16LE(0, 10);
		lh.writeUInt16LE(0x0021, 12);
		lh.writeUInt32LE(crc, 14);
		lh.writeUInt32LE(body.length, 18);
		lh.writeUInt32LE(raw.length, 22);
		lh.writeUInt16LE(name.length, 26);
		lh.writeUInt16LE(0, 28);
		locals.push(lh, name, body);
		const ch = Buffer.alloc(46);
		ch.writeUInt32LE(0x02014b50, 0);
		ch.writeUInt16LE(0x0314, 4);
		ch.writeUInt16LE(20, 6);
		ch.writeUInt16LE(0x0800, 8);
		ch.writeUInt16LE(method, 10);
		ch.writeUInt16LE(0, 12);
		ch.writeUInt16LE(0x0021, 14);
		ch.writeUInt32LE(crc, 16);
		ch.writeUInt32LE(body.length, 20);
		ch.writeUInt32LE(raw.length, 24);
		ch.writeUInt16LE(name.length, 28);
		ch.writeUInt32LE((0o100644 << 16) >>> 0, 38);
		ch.writeUInt32LE(offset, 42);
		centrals.push(ch, name);
		offset += lh.length + name.length + body.length;
	}
	const central = Buffer.concat(centrals);
	const end = Buffer.alloc(22);
	end.writeUInt32LE(0x06054b50, 0);
	end.writeUInt16LE(entries.length, 8);
	end.writeUInt16LE(entries.length, 10);
	end.writeUInt32LE(central.length, 12);
	end.writeUInt32LE(offset, 16);
	return Buffer.concat([...locals, central, end]);
}

function buildPackage(opts = {}) {
	const loader = opts.loaderBytes || fs.readFileSync(LOADER_SRC);
	const manifest = opts.manifestBytes || fs.readFileSync(MANIFEST_SRC);
	const manifestSha = sha256(manifest);
	if (manifestSha !== EXPECTED_MANIFEST_SHA256) {
		throw new Error('Manifest SHA-256 onaylı değerle eşleşmiyor; paket üretilmedi (' + manifestSha + ')');
	}
	if (!loader.toString('utf8').includes("const MANIFEST_SHA256 = '" + EXPECTED_MANIFEST_SHA256 + "'")) {
		throw new Error('Loader MANIFEST_SHA256 sabiti onaylı SHA-256 değeriyle eşleşmiyor; paket üretilmedi');
	}
	const entries = [
		{ name: ENTRY_NAMES[0], data: loader },
		{ name: ENTRY_NAMES[1], data: manifest },
	];
	const zip = buildZip(entries);
	const version = loaderVersion(loader);
	return {
		file: 'mavibelge-redirect-bootstrap-' + version + '.zip',
		version,
		zip,
		bytes: zip.length,
		sha256: sha256(zip),
		files: entries.map((e) => ({ path: e.name, bytes: e.data.length, sha256: sha256(e.data) })),
	};
}

module.exports = { buildPackage, buildZip, LOADER_SRC, MANIFEST_SRC, EXPECTED_MANIFEST_SHA256, ENTRY_NAMES, OUT_DIR };

if (require.main === module) {
	const pkg = buildPackage();
	const summary = { file: pkg.file, bytes: pkg.bytes, sha256: pkg.sha256, files: pkg.files };
	if (process.argv.includes('--write')) {
		fs.mkdirSync(OUT_DIR, { recursive: true });
		const target = path.join(OUT_DIR, pkg.file);
		fs.writeFileSync(target, pkg.zip);
		summary.written = path.relative(SITE, target).split(path.sep).join('/');
	}
	console.log(JSON.stringify(summary, null, 2));
}
