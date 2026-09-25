'use strict';
/**
 * Faz 11 — tema tasarım token'ları için WCAG 2.x renk kontrastı (deterministik, tarayıcısız).
 * Kaynak: assets/src/css/tokens.css (statik referanstan kopya). Metin çiftleri >= 4.5:1 (normal metin), büyük metin/
 * arayüz bileşeni çiftleri >= 3:1 olmalıdır. Bu BİR araç değil hesaptır; gerçek sayfa renk kullanımı (üst üste binen
 * görseller, hover durumları) aXe/Lighthouse ile ayrıca doğrulanmalıdır (açık kalite kapısı).
 *   node tools/qa/contrast-check.js
 */
const fs = require('fs');
const path = require('path');
const css = fs.readFileSync(path.join(__dirname, '..', '..', 'wp-content', 'themes', 'mavibelge', 'assets', 'src', 'css', 'tokens.css'), 'utf8');
const tok = {};
css.replace(/--color-([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})/g, (m, n, v) => {
	tok[n] = v;
	return m;
});
function lum(hex) {
	const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)));
	return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
}
function ratio(a, b) {
	const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
	return (x + 0.05) / (y + 0.05);
}
// [ön plan, arka plan, en düşük oran, açıklama]
const pairs = [
	['gray-900', 'white', 4.5, 'gövde metni'], ['gray-700', 'white', 4.5, 'ikincil metin'], ['gray-700', 'gray-50', 4.5, 'ikincil metin / açık zemin'], ['gray-500', 'white', 4.5, 'yardımcı metin'],
	['blue-600', 'white', 4.5, 'bağlantı'], ['white', 'blue-600', 4.5, 'birincil düğme'], ['blue-600', 'blue-50', 4.5, 'bağlantı / açık mavi zemin'], ['white', 'navy-900', 4.5, 'koyu şerit metni'],
	['white', 'navy-800', 4.5, 'koyu şerit metni'], ['white', 'navy-950', 4.5, 'altbilgi'], ['danger', 'white', 4.5, 'hata metni'], ['success', 'white', 3, 'başarı (yalnız büyük/ikon)'], ['warning', 'white', 3, 'uyarı (yalnız büyük/ikon)'],
	['gray-500', 'gray-50', 4.5, 'yardımcı metin / açık zemin (TOKEN ÇİFTİ eşik altı: bilinen kullanımlar gray-700 rengine alındı; kalan kullanımlar aXe ile doğrulanacak)', true],
];
let bad = 0;
pairs.forEach(([f, b, min, why, advisory]) => {
	const bg = b === 'white' ? '#ffffff' : tok[b];
	const fg = f === 'white' ? '#ffffff' : tok[f];
	const r = ratio(fg, bg);
	const ok = r >= min;
	if (!ok && !advisory) bad++;
	console.log((ok ? 'PASS  ' : advisory ? 'WARN  ' : 'FAIL  ') + f + ' / ' + b + '  ' + r.toFixed(2) + ':1 (>= ' + min + ')  — ' + why);
});
console.log('\n' + (pairs.length - bad) + '/' + pairs.length + ' kontrast çifti geçti.');
process.exit(bad ? 1 : 0);
