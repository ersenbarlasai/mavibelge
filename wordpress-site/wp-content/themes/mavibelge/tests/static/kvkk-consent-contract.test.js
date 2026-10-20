'use strict';
/**
 * Faz 14 — KVKK onay kutusu tema sözleşmesi (statik metin taraması; PHP çalıştırmaz):
 *  - beş formun TEK ortak onay çizicisi template-parts/forms/field.php'dir; onay işaretlemesi başka dosyaya kopyalanmaz;
 *  - görünen kesin metin "KVKK Metni’ni okudum ve kabul ediyorum."; yalnız "KVKK Metni" bağlantıdır;
 *  - bağlantı mavibelge_url( 'kvkk' ) + esc_url ile üretilir (alan adı / /index.php/ gömülmez), yeni sekme
 *    (target=_blank + rel="noopener noreferrer") ve ekran okuyucu için "(yeni sekmede açılır)";
 *  - required / aria-required / hata bağlantısı (aria-describedby + aria-invalid) / görünür yıldız korunur;
 *  - yapılandırmadaki uzun consent_text ve alan etiketi onay kutusunun yanında BASILMAZ; inline stil yok;
 *  - CSS amaç odaklı sınıflarla (form-consent*), bağlantının görünür odak durumu var.
 *   node tests/static/kvkk-consent-contract.test.js
 */
const fs = require('fs');
const path = require('path');
const THEME = path.join(__dirname, '..', '..');
const REPO_WS = path.join(THEME, '..', '..', '..');
let pass = 0;
let fail = 0;
function test(label, cond, detail) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + detail + ']' : ''));
	}
}
function walk(dir, out, exts) {
	fs.readdirSync(dir, { withFileTypes: true }).forEach((e) => {
		const p = path.join(dir, e.name);
		if (e.isDirectory()) {
			if (e.name !== 'tests' && e.name !== 'node_modules' && e.name !== 'dist') walk(p, out, exts);
		} else if (exts.test(e.name)) out.push(p);
	});
	return out;
}
const read = (p) => fs.readFileSync(p, 'utf8');
const strip = (s) => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const rel = (p) => path.relative(THEME, p).split(path.sep).join('/');
const phpFiles = walk(THEME, [], /\.php$/);
const field = strip(read(path.join(THEME, 'template-parts/forms/field.php')));
const branch = (field.match(/if \( 'consent' === \$field\['type'\] \) : \?>([\s\S]*?)<\?php elseif/) || ['', ''])[1];

// 1) ortak çizici
test('field.php: consent dalı var (beş formun ortak alan çizicisi)', branch.length > 0);
const checkboxFiles = phpFiles.filter((f) => /type="checkbox"/.test(strip(read(f)))).map(rel);
test('onay kutusu işaretlemesi yalnız field.php (+ filtre formundaki mb_priced kutusu) içinde; sayfa şablonlarına kopyalanmaz', JSON.stringify(checkboxFiles.sort()) === JSON.stringify(['template-parts/catalog/filter-form.php', 'template-parts/forms/field.php']), checkboxFiles.join(','));
const kvkkCopies = phpFiles.filter((f) => /okudum ve kabul ediyorum/.test(strip(read(f)))).map(rel);
test('"okudum ve kabul ediyorum" metni TEK dosyada (field.php)', JSON.stringify(kvkkCopies) === JSON.stringify(['template-parts/forms/field.php']), kvkkCopies.join(','));
const formParts = ['template-parts/page/content-contact.php', 'template-parts/page/content-application.php', 'template-parts/page/content-presentation.php', 'template-parts/page/content-form-disabled.php', 'template-parts/page/content-form-live.php', 'template-parts/forms/form.php'];
formParts.forEach((f) => {
	const exists = fs.existsSync(path.join(THEME, f));
	const s = exists ? strip(read(path.join(THEME, f))) : '';
	test(f + ': dosya var ve kendi onay kutusu/KVKK bağlantısı çizmez', exists && !/type="checkbox"|form-consent|mavibelge_url\( 'kvkk' \)/.test(s), exists ? '' : 'dosya yok');
});

// 2) bağlantı
test("bağlantı mavibelge_url( 'kvkk' ) + esc_url ile üretilir", /esc_url\( mavibelge_url\( 'kvkk' \) \)/.test(branch));
test('bağlantı: class="form-consent-link", target="_blank", rel="noopener noreferrer"', /<a class="form-consent-link" href="<\?php echo esc_url\( mavibelge_url\( 'kvkk' \) \); \?>" target="_blank" rel="noopener noreferrer">/.test(branch));
test('bağlantı metni yalnız "KVKK Metni" + ekran okuyucu "(yeni sekmede açılır)"', /esc_html_e\( 'KVKK Metni', 'mavibelge' \); \?><span class="screen-reader-text"> <\?php esc_html_e\( '\(yeni sekmede açılır\)', 'mavibelge' \); \?><\/span><\/a>/.test(branch));
test('bağlantıdan sonra kesin metin "’ni okudum ve kabul ediyorum." (U+2019 kesme işareti)', /<\/a><\?php esc_html_e\( '’ni okudum ve kabul ediyorum\.', 'mavibelge' \); \?>/.test(branch));
test('tam görünür cümle: "KVKK Metni" + "’ni okudum ve kabul ediyorum." (tek bağlantı)', (branch.match(/<a\b/g) || []).length === 1);
test('onay kutusunun erişilebilir adı tam cümle (gizli, aria-labelledby ile başvurulan metin)', /aria-labelledby="<\?php echo esc_attr\( \$id \. '-name' \); \?>"/.test(branch) && /<span id="<\?php echo esc_attr\( \$id \. '-name' \); \?>" hidden><\?php esc_html_e\( 'KVKK Metni’ni okudum ve kabul ediyorum\.', 'mavibelge' \); \?><\/span>/.test(branch));

// 3) zorunluluk + hata bağlantısı
test('onay kutusu: type=checkbox, name, value=1, required aria-required="true", $describe (aria-describedby + aria-invalid)', /<input type="checkbox" class="form-consent-control" id="<\?php echo esc_attr\( \$id \); \?>" name="<\?php echo esc_attr\( \$name \); \?>" value="1" required aria-required="true"/.test(branch) && /\$describe;/.test(branch));
test('$describe hata bağlantısı: aria-describedby="…-error" + aria-invalid="true" (değişmedi)', /\$describe = \$has_err \? ' aria-describedby="' \. esc_attr\( \$err_id \) \. '" aria-invalid="true"' : '';/.test(field));
test('etiket onay kutusuna bağlı (<label for=id>) ve görünür yıldız aria-hidden', /<label for="<\?php echo esc_attr\( \$id \); \?>" class="form-consent-copy">/.test(branch) && /<span class="form-consent-required" aria-hidden="true">\*<\/span>/.test(branch));
test('hata iletisi alanın altında (field-error, role=alert) ortak blokta kalır', /<span class="field-error" id="<\?php echo esc_attr\( \$err_id \); \?>" role="alert">/.test(field));

// 4) uzun metin / etiket basılmaz, inline stil yok, ham HTML yok
test('consent dalında $field[\'label\'] ve consent_text BASILMAZ', !/\$field\['label'\]|consent_text/.test(branch));
test('consent dalında inline style yok; wp_kses/ham HTML geçişi yok', !/style=|wp_kses|echo \$field/.test(branch));
test('form sarmalayıcısı amaç odaklı sınıf: form-field form-consent', /<div class="form-field<\?php echo 'consent' === \$field\['type'\] \? ' form-consent' : ''; \?>/.test(field));

// 5) kaynakta alan adı / /index.php/ gömülü değil
const all = walk(THEME, [], /\.(php|js|css)$/).concat(walk(path.join(REPO_WS, 'wp-content', 'plugins', 'mavibelge-core'), [], /\.php$/));
const hard = all.filter((f) => /mavibelge\.com\.tr\/kvkk|index\.php\/kvkk/.test(read(f))).map((f) => path.relative(REPO_WS, f));
test('kaynakta "https://mavibelge.com.tr/kvkk/" veya "/index.php/kvkk" gömülü değil (tema + eklenti)', hard.length === 0, hard.join(','));

// 6) CSS
const comp = read(path.join(THEME, 'assets/src/css/components.css'));
test('CSS: .form-consent (esnek satır, sarma), .form-consent-control, .form-consent-copy, .form-consent-link tanımlı', /\.form-consent \{[^}]*display: flex/.test(comp) && /\.form-consent-control \{[^}]*flex: 0 0 auto[^}]*width: 20px[^}]*height: 20px/.test(comp) && /\.form-consent-copy \{[^}]*overflow-wrap: anywhere/.test(comp) && /\.form-consent-link \{/.test(comp));
test('CSS: bağlantının görünür odak durumu (.form-consent-link:focus-visible outline)', /\.form-consent-link:focus-visible \{[^}]*outline: 3px solid/.test(comp));

// 7) form.php belge notu artık kısa bağlantıyı anlatır
const formDoc = read(path.join(THEME, 'template-parts/forms/form.php'));
test('form.php belge notu: onay kutusu metni yapılandırmadan gelmez; kısa bağlantılı KVKK etiketi', !/Onay kutusunun metni yapılandırmadaki/.test(formDoc) && /KVKK Metni/.test(formDoc));

// 8) PHP 7.3
test('field.php: PHP 7.4+/8.x sözdizimi yok', !/\?->|\?\?=|\bfn\s*\(|\bmatch\s*\(|\bstr_contains\s*\(|\benum\s+\w+\s*\{/.test(field));

console.log(pass + '/' + (pass + fail) + ' tema KVKK onay sözleşme testi geçti.');
process.exit(fail ? 1 : 0);
