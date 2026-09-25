'use strict';
/**
 * Faz 9 — tanitim-site/*.html içindeki DOĞRULANMIŞ 41 sayfanın <title> ve <meta name="description"> değerlerini
 * (final-rapor.md §7.1: statik site 145 sayfada aynı başlık ve eksik açıklama sorununu zaten çözmüştür) WordPress
 * eklentisinin kullanacağı deterministik bir PHP veri dosyasına aktarır. Kaynak dosyalar DEĞİŞTİRİLMEZ; ağ erişimi yok.
 *
 *   node tools/seo/build-page-defaults.js --write   wp-content/plugins/mavibelge-core/includes/seo/data/page-defaults.php yazılır
 *   node tools/seo/build-page-defaults.js --check   diskteki dosya kaynaktan üretilenle BYTE-EŞİT mi (aksi hâlde çıkış 1)
 *
 * Kayıt anahtarı sayfa slug'ıdır (dosya adı, .html olmadan; ana sayfa `index` -> `` (boş) anahtarı yerine `front-page`).
 * Başlık/açıklama bulunamayan sayfa varsa üretim BAŞARISIZ olur (sessiz boş değer yok).
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..', '..');
const SITE = path.join(ROOT, 'tanitim-site');
const OUT = path.join(__dirname, '..', '..', 'wp-content', 'plugins', 'mavibelge-core', 'includes', 'seo', 'data', 'page-defaults.php');

function decode(s) {
	return s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').trim();
}
function phpString(s) {
	return "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

const files = fs.readdirSync(SITE).filter((f) => /\.html$/.test(f)).sort();
const rows = [];
const errors = [];
files.forEach((f) => {
	const html = fs.readFileSync(path.join(SITE, f), 'utf8');
	const t = html.match(/<title>([^<]*)<\/title>/);
	const d = html.match(/<meta\s+name="description"\s+content="([^"]*)"/);
	const slug = f === 'index.html' ? 'front-page' : f.replace(/\.html$/, '');
	if (!t || !decode(t[1])) {
		errors.push(f + ': <title> yok/boş');
	}
	if (!d || !decode(d[1])) {
		errors.push(f + ': meta description yok/boş');
	}
	if (t && d) {
		rows.push({ slug, title: decode(t[1]), description: decode(d[1]) });
	}
});
if (errors.length || rows.length !== 41) {
	console.error('Sayfa varsayılanları üretilemedi (beklenen 41 sayfa, bulunan ' + rows.length + '):\n' + errors.join('\n'));
	process.exit(1);
}
const titles = new Set(rows.map((r) => r.title));
if (titles.size !== rows.length) {
	console.error('Başlıklar benzersiz değil (41 benzersiz title bekleniyordu).');
	process.exit(1);
}

let php = '<?php\n/**\n * Faz 9 — statik referanstan (tanitim-site) doğrulanmış 41 sayfanın title/description başlangıç değerleri.\n * BU DOSYA ÜRETİLİR: node wordpress-site/tools/seo/build-page-defaults.js --write (elle düzenlemeyin).\n * Anahtar: sayfa slug\'ı (ana sayfa: front-page). Editör alanı (_mb_seo_title/_mb_seo_description) doludur ise o önceliklidir.\n */\n\nif ( ! defined( \'ABSPATH\' ) ) {\n\texit;\n}\n\nreturn array(\n';
rows.forEach((r) => {
	php += '\t' + phpString(r.slug) + ' => array( ' + phpString(r.title) + ', ' + phpString(r.description) + ' ),\n';
});
php += ');\n';

const mode = process.argv[2];
if (mode === '--write') {
	fs.mkdirSync(path.dirname(OUT), { recursive: true });
	fs.writeFileSync(OUT, php);
	console.log('yazıldı  ' + path.relative(ROOT, OUT) + ' (' + rows.length + ' sayfa)');
} else if (mode === '--check') {
	const same = fs.existsSync(OUT) && fs.readFileSync(OUT, 'utf8') === php;
	console.log((same ? 'EŞİT   ' : 'FARKLI ') + path.relative(ROOT, OUT) + ' (' + rows.length + ' sayfa)');
	process.exit(same ? 0 : 1);
} else {
	console.error('Kullanım: --write | --check');
	process.exit(2);
}
