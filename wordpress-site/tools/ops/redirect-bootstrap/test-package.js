#!/usr/bin/env node
/**
 * Geçici yönlendirme loader ZIP'i testleri.
 *
 *   node wordpress-site/tools/ops/redirect-bootstrap/test-package.js
 *
 * Paketi bellekte iki kez üretir (determinizm), içeriğini kendi bağımsız ZIP okuyucusuyla açar ve şunları doğrular:
 * yalnız iki dosya, kök düzeyde (mu-plugins içine doğrudan çıkar), loader kaynağıyla bayt-eşit, manifest yetkili
 * data/redirects kopyasıyla bayt-eşit ve SHA-256 değeri loader sabitiyle aynı, CRC doğru, yol geçişi yok.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const crypto = require('crypto');
const { buildPackage, LOADER_SRC, MANIFEST_SRC, EXPECTED_MANIFEST_SHA256, ENTRY_NAMES } = require('./build-package.js');

let total = 0;
let failures = 0;
function check(desc, cond) {
	total++;
	if (cond) {
		console.log('PASS  ' + desc);
	} else {
		failures++;
		console.log('FAIL  ' + desc);
	}
}
const sha256 = (buf) => crypto.createHash('sha256').update(buf).digest('hex');

/** Bağımsız, küçük merkezi-dizin okuyucusu (yalnız store/deflate). */
function readZip(buf) {
	const eocd = buf.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
	if (eocd < 0) throw new Error('EOCD yok');
	const count = buf.readUInt16LE(eocd + 10);
	let off = buf.readUInt32LE(eocd + 16);
	const out = [];
	for (let i = 0; i < count; i++) {
		if (buf.readUInt32LE(off) !== 0x02014b50) throw new Error('merkezi kayıt bozuk');
		const method = buf.readUInt16LE(off + 10);
		const crc = buf.readUInt32LE(off + 16);
		const csize = buf.readUInt32LE(off + 20);
		const usize = buf.readUInt32LE(off + 24);
		const nlen = buf.readUInt16LE(off + 28);
		const elen = buf.readUInt16LE(off + 30);
		const clen = buf.readUInt16LE(off + 32);
		const lho = buf.readUInt32LE(off + 42);
		const name = buf.slice(off + 46, off + 46 + nlen).toString('utf8');
		const lnlen = buf.readUInt16LE(lho + 26);
		const lelen = buf.readUInt16LE(lho + 28);
		const body = buf.slice(lho + 30 + lnlen + lelen, lho + 30 + lnlen + lelen + csize);
		const data = method === 8 ? zlib.inflateRawSync(body) : body;
		out.push({ name, method, crc, usize, data });
		off += 46 + nlen + elen + clen;
	}
	return out;
}

const a = buildPackage();
const b = buildPackage();
check('Paket deterministik: iki üretim bayt-eşit', Buffer.compare(a.zip, b.zip) === 0);

const entries = readZip(a.zip);
const names = entries.map((e) => e.name).sort();
check('ZIP yalnız iki dosya içerir: ' + ENTRY_NAMES.join(', '), JSON.stringify(names) === JSON.stringify([...ENTRY_NAMES].sort()));
check('Dosyalar kök düzeyde (klasör öneki/yol geçişi/mutlak yol yok) — mu-plugins içine doğrudan çıkar',
	entries.every((e) => !e.name.includes('/') && !e.name.includes('\\') && !e.name.includes('..')));
check('Her girdinin CRC ve boyutu doğru', entries.every((e) => (zlib.crc32(e.data) >>> 0) === e.crc && e.data.length === e.usize));

const loader = entries.find((e) => e.name === 'mavibelge-redirect-bootstrap.php');
const manifest = entries.find((e) => e.name === 'redirects.manifest.json');
check('Loader girdisi kaynak dosyayla bayt-eşit', loader && Buffer.compare(loader.data, fs.readFileSync(LOADER_SRC)) === 0);
check('Manifest girdisi yetkili data/redirects kopyasıyla bayt-eşit', manifest && Buffer.compare(manifest.data, fs.readFileSync(MANIFEST_SRC)) === 0);
check('Manifest SHA-256 = onaylı değer (EBD84CE8…97792)', manifest && sha256(manifest.data) === EXPECTED_MANIFEST_SHA256
	&& EXPECTED_MANIFEST_SHA256 === 'ebd84ce828efbc1b12490b79b838d6534c70141bf04da849aab7070d34597792');
check('Loader içindeki MANIFEST_SHA256 sabiti manifestin gerçek hash değeriyle aynı',
	loader && loader.data.toString('utf8').includes("const MANIFEST_SHA256 = '" + EXPECTED_MANIFEST_SHA256 + "'"));
const parsed = manifest ? JSON.parse(manifest.data.toString('utf8')) : {};
check('Manifest 29 kural / 4 etkin', Array.isArray(parsed.rules) && parsed.rules.length === 29 && parsed.rules.filter((r) => r.active === true).length === 4);
check('Paket özeti (rapor) ZIP hash ve boyutunu doğru bildirir', a.sha256 === sha256(a.zip) && a.bytes === a.zip.length);

const tampered = Buffer.from(fs.readFileSync(MANIFEST_SRC));
tampered[tampered.length - 3] ^= 0x01;
let refused = false;
try {
	buildPackage({ manifestBytes: tampered });
} catch (e) {
	refused = /SHA-256/.test(e.message);
}
check('Hash tutmayan manifestle paket ÜRETİLMEZ (build reddeder)', refused);

console.log('\n' + total + ' test, ' + (total - failures) + ' geçti, ' + failures + ' başarısız.');
process.exit(failures ? 1 : 0);
