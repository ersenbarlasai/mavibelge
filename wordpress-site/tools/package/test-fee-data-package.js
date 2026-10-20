'use strict';
/**
 * Faz 15 ücret veri paketi doğrulaması: determinizm, kapalı içerik listesi (yalnız çalışma anı dosyaları), kaynak ile byte-eşitlik,
 * eklentinin REQUIRED_FILES listesiyle eşitlik, zip bütünlüğü (ayrı okuyucu), checksum dosyaları, diskteki paketle eşitlik, ezmeme.
 *   node tools/package/test-fee-data-package.js
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const zlib = require('zlib');
const crypto = require('crypto');
const cp = require('child_process');
const { build, REQUIRED_FILES } = require('./build-fee-data-package');

const ROOT = path.join(__dirname, '..', '..');
let pass = 0;
let fail = 0;
function check(label, cond, detail) {
	if (cond) {
		pass++;
		console.log('PASS  ' + label);
	} else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + detail + ']' : ''));
	}
}
const sha256 = (b) => crypto.createHash('sha256').update(b).digest('hex');

/** Zip'i AYRI bir okuyucuyla açar (merkezî dizin + CRC). */
function readZip(buf) {
	const eocd = buf.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
	const count = buf.readUInt16LE(eocd + 10);
	let p = buf.readUInt32LE(eocd + 16);
	const out = [];
	for (let i = 0; i < count; i++) {
		const method = buf.readUInt16LE(p + 10);
		const crc = buf.readUInt32LE(p + 16);
		const csize = buf.readUInt32LE(p + 20);
		const usize = buf.readUInt32LE(p + 24);
		const nlen = buf.readUInt16LE(p + 28);
		const off = buf.readUInt32LE(p + 42);
		const name = buf.slice(p + 46, p + 46 + nlen).toString('utf8');
		const start = off + 30 + buf.readUInt16LE(off + 26) + buf.readUInt16LE(off + 28);
		const body = buf.slice(start, start + csize);
		const data = method === 8 ? zlib.inflateRawSync(body) : body;
		out.push({ name, data, crcOk: (zlib.crc32(data) >>> 0) === crc && data.length === usize });
		p += 46 + nlen;
	}
	return out;
}

const a = build();
const b = build();
check('iki ardışık üretim byte-eşit (zip + iki checksum + manifest)', Object.keys(a.outputs).every((k) => a.outputs[k].equals(b.outputs[k])) && Object.keys(a.outputs).length === 4);
const zip = a.outputs[a.base + '.zip'];
const entries = readZip(zip);
const names = entries.map((e) => e.name);
const EXPECTED = ['mavibelge-data/.htaccess', 'mavibelge-data/content/fee-publication.manifest.json', 'mavibelge-data/content/fees.manifest.json', 'mavibelge-data/content/index.php',
	'mavibelge-data/content/qualifications.manifest.json', 'mavibelge-data/content/sectors.manifest.json', 'mavibelge-data/index.php'];
check('zip bütünlüğü (her giriş CRC + boyut; ayrı okuyucu)', entries.length > 0 && entries.every((e) => e.crcOk));
check('içerik KAPALI liste: yalnız 4 çalışma anı JSON + .htaccess + 2 boş index.php (kaynak/rapor/şema/PDF/eşleme YOK)', names.join('\n') === EXPECTED.join('\n'), names.join(','));
const php = fs.readFileSync(path.join(ROOT, 'wp-content', 'plugins', 'mavibelge-core', 'includes', 'import', 'class-import-fee-publisher.php'), 'utf8');
const m = /const REQUIRED_FILES = array\(([^)]*)\);/.exec(php);
const phpList = m ? (m[1].match(/'([^']+)'/g) || []).map((s) => s.slice(1, -1)) : [];
check('paketteki JSON listesi = eklentinin REQUIRED_FILES (çalışma anında okunan dosyalar)', JSON.stringify(phpList) === JSON.stringify(REQUIRED_FILES), phpList.join(','));
check('her JSON kaynak data/content dosyasıyla BYTE-EŞİT', REQUIRED_FILES.every((f) => entries.find((e) => e.name === 'mavibelge-data/content/' + f).data.equals(fs.readFileSync(path.join(ROOT, 'data', 'content', f)))));
check('.htaccess web erişimini kapatır (Apache 2.4 + 2.2); index.php yalnız boş PHP', /Require all denied/.test(entries[0].data.toString()) && /Deny from all/.test(entries[0].data.toString())
	&& entries.filter((e) => e.name.endsWith('index.php')).every((e) => e.data.toString() === '<?php\n// Silence is golden.\n'));
check('dosya adı içerik özetine bağlı (karar tarihi + 8 hex)', /^mavibelge-ucret-verisi-\d{8}-[0-9a-f]{8}$/.test(a.base), a.base);
const zipSum = a.outputs[a.base + '.zip.sha256'].toString();
const content = a.outputs[a.base + '-icerik.sha256'].toString().trim().split('\n');
check('zip checksum dosyası sha256sum biçiminde ve doğru', zipSum === sha256(zip) + '  ' + a.base + '.zip\n');
check('içerik checksum listesi 7 satır, her satır zip içindeki dosyayla eşit', content.length === 7 && content.every((l) => {
	const [h, n] = l.split('  ');
	const e = entries.find((x) => x.name === n);
	return e && sha256(e.data) === h;
}));
const man = JSON.parse(a.outputs[a.base + '-manifest.json'].toString());
check('içerik manifesti: zip özeti, 103 ücret / 145 seçenek, karar 103 yayın / 0 bekletme, 7 dosya', man.zip_sha256 === sha256(zip) && man.fees.count === 103 && man.fees.price_options === 145
	&& man.decisions.publish === 103 && man.decisions.hold === 0 && man.files.length === 7, JSON.stringify([man.fees, man.decisions]));
const SECRET = [/-----BEGIN [A-Z ]*PRIVATE KEY-----/, /\b(AKIA|ASIA)[0-9A-Z]{16}\b/, /\bghp_[A-Za-z0-9]{30,}\b/, /DB_PASSWORD|AUTH_KEY|wp-config/];
check('gizli bilgi/parola/anahtar deseni YOK', entries.every((e) => SECRET.every((re) => !re.test(e.data.toString('utf8')))));
const onDisk = path.join(ROOT, 'dist-packages');
check('diskteki dist-packages/ veri paketi kaynaktan üretilenle BYTE-EŞİT (üretilmediyse önce --write)', Object.keys(a.outputs).every((k) => fs.existsSync(path.join(onDisk, k)) && fs.readFileSync(path.join(onDisk, k)).equals(a.outputs[k])));
{
	const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'mbfeepkg-'));
	fs.writeFileSync(path.join(tmp, a.base + '.zip'), 'farkli-icerik');
	fs.writeFileSync(path.join(tmp, 'mavibelge-ucret-verisi-20260101-00000000.zip'), 'tarihsel');
	const r = cp.spawnSync(process.execPath, [path.join(__dirname, 'build-fee-data-package.js'), '--write'], { cwd: ROOT, env: Object.assign({}, process.env, { MB_PACKAGE_OUT: tmp }), stdio: 'ignore' });
	check('--write aynı adlı FARKLI içerikli dosyayı EZMEZ (çıkış 1) ve tarihsel paketlere dokunmaz', r.status === 1 && fs.readFileSync(path.join(tmp, a.base + '.zip'), 'utf8') === 'farkli-icerik'
		&& fs.readFileSync(path.join(tmp, 'mavibelge-ucret-verisi-20260101-00000000.zip'), 'utf8') === 'tarihsel');
	fs.rmSync(tmp, { recursive: true, force: true });
}
console.log('\n' + pass + '/' + (pass + fail) + ' ücret veri paketi testi geçti.');
process.exit(fail ? 1 : 0);
