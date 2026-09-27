'use strict';
/**
 * Paket doğrulaması: determinizm, allowlist, yasak içerik, gizli bilgi taraması, zip bütünlüğü, kaynak–dist eşitliği.
 *   node tools/package/test-package.js
 */
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const cp = require('child_process');
const { buildAll, selectFiles, TARGETS } = require('./build-packages');

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

/** Zip'i AYRI bir okuyucuyla açar (merkezî dizin + CRC); bütünlük kanıtı. */
function readZip(buf) {
	const eocd = buf.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
	const count = buf.readUInt16LE(eocd + 10);
	let p = buf.readUInt32LE(eocd + 16);
	const out = [];
	for (let i = 0; i < count; i++) {
		if (buf.readUInt32LE(p) !== 0x02014b50) throw new Error('bozuk merkezî dizin');
		const method = buf.readUInt16LE(p + 10);
		const crc = buf.readUInt32LE(p + 16);
		const csize = buf.readUInt32LE(p + 20);
		const usize = buf.readUInt32LE(p + 24);
		const nlen = buf.readUInt16LE(p + 28);
		const off = buf.readUInt32LE(p + 42);
		const name = buf.slice(p + 46, p + 46 + nlen).toString('utf8');
		const lnlen = buf.readUInt16LE(off + 26);
		const lxlen = buf.readUInt16LE(off + 28);
		const start = off + 30 + lnlen + lxlen;
		const body = buf.slice(start, start + csize);
		const data = method === 8 ? zlib.inflateRawSync(body) : body;
		out.push({ name, data, crcOk: (zlib.crc32(data) >>> 0) === crc && data.length === usize });
		p += 46 + nlen;
	}
	return out;
}

const a = buildAll();
const b = buildAll();
const zipNames = Object.keys(a).filter((n) => n.endsWith('.zip'));
check('iki ardışık üretim byte-eşit (zip + checksum + manifest)', Object.keys(a).every((k) => a[k].equals(b[k])));
check('iki paket üretildi (tema + eklenti) ve checksum/manifest dosyaları var', zipNames.length === 2 && a['checksums.sha256'] && a['package-manifest.json']);

const DENY_PART = /(^|\/)(tests?|fixtures?|docs|node_modules|\.git|tmp|build)(\/|$)/i;
const DENY_FILE = /(\.md$|\.sh$|\.log$|\.bak$|\.map$|\.env|\.zip$|(^|\/)\.[^/]+$)/i;
const SECRET = [/-----BEGIN [A-Z ]*PRIVATE KEY-----/, /\b(AKIA|ASIA)[0-9A-Z]{16}\b/, /\bghp_[A-Za-z0-9]{30,}\b/, /(?:define\(\s*['"](?:DB_PASSWORD|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY)['"]\s*,\s*['"][^'"]{8,}['"])/, /(?:password|passwd|secret|api[_-]?key)\s*[:=]\s*['"][A-Za-z0-9+\/_\-]{12,}['"]/i];
for (const name of zipNames) {
	const entries = readZip(a[name]);
	const names = entries.map((e) => e.name);
	check(name + ': zip bütünlüğü (her giriş CRC + boyut doğru; ayrı okuyucu)', entries.length > 0 && entries.every((e) => e.crcOk));
	check(name + ': girişler ada göre sıralı ve benzersiz', names.join('\n') === names.slice().sort().join('\n') && new Set(names).size === names.length);
	check(name + ': test/fixture/docs/rapor/gizli dosya/geliştirme aracı YOK', names.every((n) => !DENY_PART.test(n) && !DENY_FILE.test(n)), names.filter((n) => DENY_PART.test(n) || DENY_FILE.test(n)).slice(0, 3).join(','));
	check(name + ': yalnız izinli uzantılar', names.every((n) => /\.(php|css|js|json|png|jpe?g|webp|svg|gif|ico|woff2?|pot|po|mo|txt)$/i.test(n)));
	const hits = [];
	entries.forEach((e) => {
		if (/\.(php|js|css|json|txt)$/i.test(e.name)) {
			const text = e.data.toString('utf8');
			SECRET.forEach((re) => {
				if (re.test(text)) hits.push(e.name);
			});
		}
	});
	check(name + ': gizli bilgi/parola/anahtar deseni YOK', hits.length === 0, hits.slice(0, 3).join(','));
	const phpBad = [];
	entries.filter((e) => /\.php$/.test(e.name)).forEach((e) => {
		const t = e.data.toString('utf8');
		const code = t.replace(/\/\*[\s\S]*?\*\/|\/\/.*$/gm, '');
		if (/^\s*enum\s+\w+\s*[{:]/m.test(code) || /(?<![>:\w$])(?<!function )match\s*\(/.test(code)) phpBad.push(e.name);
		else if (/\?->|\?\?=|\breadonly\s+(?:public|protected|private|static|class)\b|\bstr_contains\s*\(|\bfn\s*\(/.test(t.replace(/\/\*[\s\S]*?\*\/|\/\/.*$/gm, ''))) phpBad.push(e.name);
	});
	check(name + ': PHP 7.4+/8.x yasak sözdizimi YOK (yorum dışı)', phpBad.length === 0, phpBad.slice(0, 3).join(','));
	check(name + ': her PHP dosyası ABSPATH korumalı veya sınıf/işlev bildirimi içerir (doğrudan çalıştırma koruması)', entries.filter((e) => /\.php$/.test(e.name)).every((e) => {
		const t = e.data.toString('utf8');
		return /ABSPATH|WP_UNINSTALL_PLUGIN/.test(t) || /^<\?php\s*(\/\*[\s\S]*?\*\/\s*)?\/\/ Silence is golden\.?\s*$/m.test(t) || path.basename(e.name) === 'index.php';
	}));
}
const theme = readZip(a[zipNames.find((n) => n.startsWith('mavibelge-theme'))]).map((e) => e.name);
const core = readZip(a[zipNames.find((n) => n.startsWith('mavibelge-core'))]).map((e) => e.name);
check('tema paketi gerekli dosyaları içerir (style.css, functions.php, dist/style.css, dist/main.js, header/footer)', ['style.css', 'functions.php', 'assets/dist/style.css', 'assets/dist/main.js', 'header.php', 'footer.php'].every((f) => theme.includes('mavibelge/' + f)));
check('tema paketi src/ kopyalarını İÇERMEZ (yalnız editor.css)', theme.filter((n) => n.startsWith('mavibelge/assets/src/')).join() === 'mavibelge/assets/src/css/editor.css', theme.filter((n) => n.startsWith('mavibelge/assets/src/')).join());
check('eklenti paketi gerekli dosyaları içerir (mavibelge-core.php, uninstall.php, forms/seo/redirects/import sınıfları, SEO varsayılan verisi)', ['mavibelge-core.php', 'uninstall.php', 'includes/forms/class-forms-schema.php', 'includes/seo/class-seo-schema.php', 'includes/seo/data/page-defaults.php', 'includes/redirects/class-redirects-rules.php', 'includes/import/class-import-run-finalizer.php', 'public/class-content-service.php'].every((f) => core.includes('mavibelge-core/' + f)));
check('eklenti paketi tests/ ve tools/ İÇERMEZ', core.every((n) => !/\/tests\//.test(n) && !/\/tools\//.test(n)));
// Kaynak–dist eşitliği
const run = (c) => {
	try {
		return { ok: true, out: cp.execSync(c, { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString() };
	} catch (e) {
		return { ok: false, out: String(e.stdout || '') };
	}
};
check('tema dist (style.css/main.js) src\'den üretilenle BYTE-EŞİT', run('node tools/build/build-theme-assets.js --check').ok);
check('SEO sayfa varsayılanları statik kaynaktan üretilenle BYTE-EŞİT', run('node tools/seo/build-page-defaults.js --check').ok);
check('yönlendirme manifesti + eşleme raporu kaynaktan üretilenle BYTE-EŞİT', run('node tools/redirects/build-redirect-manifest.js --check').ok);
const onDisk = fs.existsSync(path.join(ROOT, 'dist-packages')) ? fs.readdirSync(path.join(ROOT, 'dist-packages')) : [];
check('diskteki dist-packages/ kaynaktan üretilenle BYTE-EŞİT (üretilmediyse önce --write)', Object.keys(a).every((k) => onDisk.includes(k) && fs.readFileSync(path.join(ROOT, 'dist-packages', k)).equals(a[k])), onDisk.join(','));
check('paket dosya seçimi yalnız çalışma zamanı kaynaklarından; her hedef için boş değil', TARGETS.every((t) => selectFiles(t).length > 5));
// Faz 12c regresyonu: --write tarihsel zip'leri SİLMEZ (geçici çıktı dizini; gerçek dist-packages/'a dokunulmaz).
{
	const os = require('os');
	const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'mbpkg-'));
	const hist = ['mavibelge-core-0.5.0.zip', 'mavibelge-core-0.5.1.zip', 'mavibelge-theme-0.6.0.zip'];
	hist.forEach((h) => fs.writeFileSync(path.join(tmp, h), 'tarihsel-' + h));
	const r = cp.spawnSync(process.execPath, [path.join(ROOT, 'tools', 'package', 'build-packages.js'), '--write'], { cwd: ROOT, env: Object.assign({}, process.env, { MB_PACKAGE_OUT: tmp }), stdio: 'ignore' });
	const after = fs.readdirSync(tmp);
	const curCore = Object.keys(a).find((k) => /^mavibelge-core-.*\.zip$/.test(k));
	check('paket üretimi tarihsel zip\'leri SİLMEZ ve içeriklerini DEĞİŞTİRMEZ (yalnız güncel dosyaları yazar)', r.status === 0 && hist.filter((h) => h !== curCore).every((h) => after.includes(h) && fs.readFileSync(path.join(tmp, h), 'utf8') === 'tarihsel-' + h), after.join(','));
	check('paket üretimi güncel zip\'leri + checksums.sha256 + package-manifest.json yazar (geçici dizinde)', Object.keys(a).every((k) => after.includes(k) && fs.readFileSync(path.join(tmp, k)).equals(a[k])));
	fs.rmSync(tmp, { recursive: true, force: true });
}
console.log('\n' + pass + '/' + (pass + fail) + ' paket testi geçti.');
process.exit(fail ? 1 : 0);
