#!/usr/bin/env node
'use strict';
/**
 * Yerel, DETERMİNİSTİK yayın paketi üretimi (DEPLOY DEĞİL: sunucuya/FTP/SSH/DirectAdmin bağlantısı YOKTUR).
 *
 *   node tools/package/build-packages.js --write   wordpress-site/dist-packages/ altına iki zip + checksum manifesti yazar
 *   node tools/package/build-packages.js --check   diskteki paketler kaynaktan üretilenle BYTE-EŞİT mi (aksi hâlde çıkış 1)
 *
 * Çıktılar: mavibelge-theme-<sürüm>.zip, mavibelge-core-<sürüm>.zip, checksums.sha256, package-manifest.json.
 * Zip: girişler ada göre sıralı, sabit zaman damgası (1980-01-01), sabit izinler (0644), UTF-8 adlar, deflate düzey 9 —
 * aynı kaynak + aynı Node sürümü = byte-eşit çıktı. Paket ALLOWLIST ile kurulur (yalnız çalışma zamanı dosyaları); test,
 * fixture, rapor, gizli dosya, geliştirme aracı ve kaynak-dist kopyaları pakete GİRMEZ (tools/package/test-package.js doğrular).
 */
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const crypto = require('crypto');

const ROOT = path.join(__dirname, '..', '..');
const OUT = path.join(ROOT, 'dist-packages');
const TARGETS = [
	{ name: 'mavibelge-theme', dir: path.join('wp-content', 'themes', 'mavibelge'), prefix: 'mavibelge', versionFile: 'style.css', versionRe: /^\s*Version:\s*([0-9][0-9A-Za-z.\-]*)/m },
	{ name: 'mavibelge-core', dir: path.join('wp-content', 'plugins', 'mavibelge-core'), prefix: 'mavibelge-core', versionFile: 'mavibelge-core.php', versionRe: /^\s*\*\s*Version:\s*([0-9][0-9A-Za-z.\-]*)/m },
];
/** Çalışma zamanı uzantı allowlist'i. */
const ALLOWED_EXT = new Set(['.php', '.css', '.js', '.json', '.png', '.jpg', '.jpeg', '.webp', '.svg', '.gif', '.ico', '.woff', '.woff2', '.pot', '.po', '.mo', '.txt']);
/** Yol bileşeni olarak GİRMEYECEKLER. */
const DENY_DIRS = new Set(['tests', 'fixtures', 'node_modules', '.git', '.github', 'docs', 'tmp', 'build']);
/** Tema kaynak dizini: yalnız editör stili pakete girer (dist zaten birleşik paket). */
const THEME_SRC_ALLOW = new Set(['assets/src/css/editor.css']);
const DENY_NAME_RE = /(^\.env|\.bak$|\.orig$|\.log$|\.zip$|\.map$|\.sh$|\.md$|(^|\/)\.[^/]+$|Thumbs\.db$|\.DS_Store$)/i;

function walk(dir, base, out) {
	for (const entry of fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => (a.name < b.name ? -1 : 1))) {
		const rel = base ? base + '/' + entry.name : entry.name;
		if (entry.isDirectory()) {
			if (!DENY_DIRS.has(entry.name)) walk(path.join(dir, entry.name), rel, out);
		} else {
			out.push(rel);
		}
	}
	return out;
}

function selectFiles(target) {
	const abs = path.join(ROOT, target.dir);
	return walk(abs, '', []).filter((rel) => {
		if (DENY_NAME_RE.test(rel)) return false;
		if (!ALLOWED_EXT.has(path.extname(rel).toLowerCase())) return false;
		if (target.name === 'mavibelge-theme' && rel.startsWith('assets/src/') && !THEME_SRC_ALLOW.has(rel)) return false;
		return true;
	});
}

function crc32(buf) {
	return zlib.crc32(buf) >>> 0;
}

/** Deterministik zip (store/deflate). */
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
		const crc = crc32(raw);
		const lh = Buffer.alloc(30);
		lh.writeUInt32LE(0x04034b50, 0);
		lh.writeUInt16LE(20, 4);
		lh.writeUInt16LE(0x0800, 6);
		lh.writeUInt16LE(method, 8);
		lh.writeUInt16LE(0, 10); // dos time 00:00
		lh.writeUInt16LE(0x0021, 12); // dos date 1980-01-01
		lh.writeUInt32LE(crc, 14);
		lh.writeUInt32LE(body.length, 18);
		lh.writeUInt32LE(raw.length, 22);
		lh.writeUInt16LE(name.length, 26);
		lh.writeUInt16LE(0, 28);
		locals.push(lh, name, body);
		const ch = Buffer.alloc(46);
		ch.writeUInt32LE(0x02014b50, 0);
		ch.writeUInt16LE(0x0314, 4); // unix, v2.0
		ch.writeUInt16LE(20, 6);
		ch.writeUInt16LE(0x0800, 8);
		ch.writeUInt16LE(method, 10);
		ch.writeUInt16LE(0, 12);
		ch.writeUInt16LE(0x0021, 14);
		ch.writeUInt32LE(crc, 16);
		ch.writeUInt32LE(body.length, 20);
		ch.writeUInt32LE(raw.length, 24);
		ch.writeUInt16LE(name.length, 28);
		ch.writeUInt32LE(((0o100644 << 16) >>> 0), 38);
		ch.writeUInt32LE(offset, 42);
		centrals.push(ch, name);
		offset += lh.length + name.length + body.length;
	}
	const centralBuf = Buffer.concat(centrals);
	const end = Buffer.alloc(22);
	end.writeUInt32LE(0x06054b50, 0);
	end.writeUInt16LE(entries.length, 8);
	end.writeUInt16LE(entries.length, 10);
	end.writeUInt32LE(centralBuf.length, 12);
	end.writeUInt32LE(offset, 16);
	return Buffer.concat([...locals, centralBuf, end]);
}

function sha256(buf) {
	return crypto.createHash('sha256').update(buf).digest('hex');
}

function buildAll() {
	const outputs = {};
	const manifest = { schema_version: '1.0.0', deterministic: true, node_major: Number(process.versions.node.split('.')[0]), packages: [] };
	const sums = [];
	for (const t of TARGETS) {
		const versionSrc = fs.readFileSync(path.join(ROOT, t.dir, t.versionFile), 'utf8');
		const m = versionSrc.match(t.versionRe);
		if (!m) throw new Error(t.name + ': sürüm okunamadı (' + t.versionFile + ')');
		const files = selectFiles(t);
		if (files.length === 0) throw new Error(t.name + ': paket boş');
		const entries = files.map((rel) => ({ name: t.prefix + '/' + rel, data: fs.readFileSync(path.join(ROOT, t.dir, rel)) }));
		const zip = buildZip(entries);
		const fname = t.name + '-' + m[1] + '.zip';
		outputs[fname] = zip;
		sums.push(sha256(zip) + '  ' + fname);
		manifest.packages.push({
			file: fname,
			version: m[1],
			zip_sha256: sha256(zip),
			file_count: entries.length,
			files: entries.map((e) => ({ path: e.name, bytes: e.data.length, sha256: sha256(e.data) })),
		});
	}
	outputs['checksums.sha256'] = Buffer.from(sums.join('\n') + '\n');
	outputs['package-manifest.json'] = Buffer.from(JSON.stringify(manifest, null, 2) + '\n');
	return outputs;
}

module.exports = { buildAll, selectFiles, TARGETS };

if (require.main === module) {
	const mode = process.argv[2];
	const outputs = buildAll();
	if (mode === '--write') {
		fs.mkdirSync(OUT, { recursive: true });
		for (const f of fs.readdirSync(OUT)) fs.unlinkSync(path.join(OUT, f));
		for (const [name, buf] of Object.entries(outputs)) {
			fs.writeFileSync(path.join(OUT, name), buf);
			console.log('yazıldı  dist-packages/' + name + ' (' + buf.length + ' bayt)');
		}
		console.log('\n' + outputs['checksums.sha256'].toString().trim());
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
