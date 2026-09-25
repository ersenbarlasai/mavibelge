'use strict';
/**
 * Faz 6B2 Runtime Engelleri — taxonomy-mb_sektor.php "sarkan else" regresyon
 * testi. Kök neden: dış `if ( … ) : … else : … endif;` bloğunun içinde
 * süslü parantezli `if ( '' !== $term_url ) { … }` bulunuyordu; PHP ardından
 * gelen `else :`'i içteki if'e bağladı ve dosya hiç ayrıştırılamadı (PHP 7.3
 * `php -l`: "unexpected ':'"). Bu test yalnız kaynak metni tarar — gerçek
 * sözdizimi kanıtı PHP 7.3 `php -l`'dir (runtime raporu).
 *
 * Run: node wordpress-site/wp-content/themes/mavibelge/tests/static/taxonomy-sector-syntax.test.js
 */
const fs = require('fs');
const path = require('path');

// MB_TAXONOMY_FILE yalnız mutasyon (eski hatalı sürüm) kanıtı içindir.
const file = process.env.MB_TAXONOMY_FILE || path.resolve(__dirname, '..', '..', 'taxonomy-mb_sektor.php');
const raw = fs.readFileSync(file, 'utf8');
// Kaba yorum temizliği: blok yorumlar ve satır sonu // yorumları (bu dosyada string içinde // yok).
const code = raw
	.replace(/\/\*[\s\S]*?\*\//g, '')
	.split('\n')
	.map((l) => { const i = l.indexOf('//'); return i === -1 ? l : l.slice(0, i); })
	.join('\n');

let total = 0;
let failures = 0;
function test(label, ok) {
	total++;
	if (!ok) { failures++; }
	process.stdout.write((ok ? 'PASS  ' : 'FAIL  ') + label + '\n');
}

const IF_ALT = /\bif\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)\s*:/g;
const ifAltCount = (code.match(IF_ALT) || []).length;
const endifCount = (code.match(/\bendif\s*;/g) || []).length;
const elseAltCount = (code.match(/\belse\s*:/g) || []).length;

test('Sarkan else yok: "}" hemen ardından "else :" gelmiyor', !/\}\s*(\?>\s*<\?php\s*)?else\s*:/.test(code));
test('Alternatif if/endif dengeli (if … : sayısı = endif; sayısı)', ifAltCount > 0 && ifAltCount === endifCount);
test('else : sayısı alternatif if sayısını aşmıyor', elseAltCount <= ifAltCount);

const termIfIdx = code.indexOf("if ( '' !== $term_url ) :");
const pagIdx = code.indexOf("'template-parts/components/pagination'");
const endifAfterPag = code.indexOf('endif;', pagIdx);
const outerElseIdx = code.indexOf('else :', endifAfterPag);
test('Sayfalama yalnız geçerli $term_url dalında (if ( \'\' !== $term_url ) : … pagination … endif;)',
	termIfIdx !== -1 && pagIdx > termIfIdx && endifAfterPag > pagIdx && !/\belse\b/.test(code.slice(termIfIdx, endifAfterPag)));

const noneIdx = code.indexOf("'template-parts/content/content-none'");
const lastEndif = code.lastIndexOf('endif;');
test('Boş sonuç bileşeni dış else dalında (sayfalama bloğunun endif; ardından else : … content-none … endif;)',
	outerElseIdx > endifAfterPag && noneIdx > outerElseIdx && lastEndif > noneIdx);
test('Sonuç kartları dış if (! empty( $results[\'items\'] )) : dalında kaldı',
	code.indexOf("if ( ! empty( $results['items'] ) ) :") !== -1
		&& code.indexOf("if ( ! empty( $results['items'] ) ) :") < code.indexOf("'template-parts/catalog/qualification-card'")
		&& code.indexOf("'template-parts/catalog/qualification-card'") < outerElseIdx);

if (failures) {
	process.stderr.write('\n' + failures + '/' + total + ' taxonomy sözdizimi testi BAŞARISIZ.\n');
	process.exit(1);
}
process.stdout.write('\nTüm ' + total + ' taxonomy sözdizimi testi geçti.\n');
