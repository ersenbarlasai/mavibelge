'use strict';
/**
 * Faz 7/8 tema sözleşmesi (statik metin taraması; PHP çalıştırmaz):
 *  - tema haber/doküman/referans/lokasyon/SSS için doğrudan WP_Query/get_posts/$wpdb KULLANMAZ (servis katmanı);
 *  - içerik adaptörleri class_exists korumalı (eklenti pasifken fatal yok);
 *  - form şablonları: bal küpü gizli, nonce alanı, alan başına <label for>, hata özeti role=alert, hassas alan geri doldurulmaz;
 *  - href="#" / javascript: bağlantısı yok; harici bağlantılarda rel=noopener;
 *  - yeni şablonlarda PHP 7.4+/8.x sözdizimi yok.
 *   node tests/static/content-forms-contract.test.js
 */
const fs = require('fs');
const path = require('path');
const THEME = path.join(__dirname, '..', '..');
let pass = 0;
let fail = 0;
function test(label, cond) {
	if (cond) pass++;
	else {
		fail++;
		console.log('FAIL  ' + label);
	}
}
function walk(dir, out) {
	fs.readdirSync(dir, { withFileTypes: true }).forEach((e) => {
		const p = path.join(dir, e.name);
		if (e.isDirectory()) {
			if (e.name !== 'tests' && e.name !== 'assets') walk(p, out);
		} else if (/\.php$/.test(e.name)) out.push(p);
	});
	return out;
}
const strip = (s) => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const files = walk(THEME, []);
const src = {};
files.forEach((f) => {
	src[path.relative(THEME, f).split(path.sep).join('/')] = strip(fs.readFileSync(f, 'utf8'));
});
const rel = (p) => src[p] || '';

// 1) Doğrudan sorgu yasağı (mb_yeterlilik/mb_ucret için zaten inc/catalog-helpers.php; içerik türleri için)
const contentTypes = /mb_haber|mb_dokuman|mb_referans|mb_lokasyon|mb_sss/;
Object.keys(src).forEach((f) => {
	const code = src[f];
	const direct = /new\s+WP_Query\s*\(|get_posts\s*\(|\$wpdb\b|query_posts\s*\(/.test(code);
	test(f + ': doğrudan WP_Query/get_posts/$wpdb KULLANMAZ (servis katmanı)', !direct);
});
// 2) Adaptörler korumalı
const helpers = rel('inc/content-helpers.php');
test('content-helpers: eklenti pasifken güvenli boş dönüş (class_exists)', /class_exists\(\s*'MaviBelge_Core_Content_Service'\s*\)/.test(helpers) && /function mavibelge_form_dto[\s\S]*class_exists\(\s*'MaviBelge_Core_Forms_Service'\s*\)/.test(helpers));
test('content-helpers: içerik servisi dışında hiçbir sorgu/meta okuması yok', !/get_post_meta\(|WP_Query|\$wpdb/.test(helpers));
['archive-mb_haber.php', 'archive-mb_dokuman.php', 'page-referanslar.php', 'page-sss.php', 'template-parts/home/news.php', 'template-parts/home/references.php'].forEach((f) => {
	test(f + ': içerik yalnız mavibelge_get_* adaptörlerinden gelir', /mavibelge_get_(news|latest_news|documents|references|faqs)\(/.test(rel(f)));
});
// 3) Form şablonları
const form = rel('template-parts/forms/form.php');
test('form: bal küpü aria-hidden + tabindex=-1 + autocomplete=off', /class="hp-field" aria-hidden="true"/.test(form) && /tabindex="-1" autocomplete="off"/.test(form));
test('form: wp_nonce_field + token + form kimliği gizli alanları', /wp_nonce_field\(/.test(form) && /names'\]\['token'\]/.test(form) && /names'\]\['form'\]/.test(form));
test('form: her alan etiketi <label for> ile bağlı; zorunlu alanlar aria-required; hatalar aria-describedby + aria-invalid', /<label for="<\?php echo esc_attr\( \$id \)/.test(form) && /aria-required="true"/.test(form) && /aria-describedby/.test(form) && /aria-invalid="true"/.test(form));
test('form: hata özeti role=alert ve odaklanabilir (tabindex=-1)', /class="form-error-summary" role="alert" tabindex="-1"/.test(form));
test('form: hassas alan değeri geri doldurulmaz', /\$field\['sensitive'\] \? '' : \$value/.test(form));
test('form: kimlik/telefon/e-posta için doğru type ve autocomplete; T.C. kimlik autocomplete=off', /'national_id' => 'off'/.test(form) && /'email' => 'email'/.test(form) && /'phone' => 'tel'/.test(form));
test('form: dosya alanı accept öznitelikli; çoklu dosya için name[]', /accept="<\?php echo esc_attr\( \$field\['accept'\] \)/.test(form) && /'\[\]'/.test(form));
test('page.php: form yalnız kapı AÇIKKEN (veya PRG başarı) çizilir, kapalıysa content-form-disabled', /content-form-live/.test(rel('page.php')) && /content-form-disabled/.test(rel('page.php')));
test('content-form-disabled: kapalı NEDENLERİ yalnız manage_options yetkisine gösterilir', /current_user_can\(\s*'manage_options'\s*\)/.test(rel('template-parts/page/content-form-disabled.php')));
test('content-form-disabled: <form> çizmez', !/<form\b/.test(rel('template-parts/page/content-form-disabled.php')));
// 4) Bağlantı hijyeni
Object.keys(src).forEach((f) => {
	test(f + ': href="#" ve javascript: bağlantısı YOK', !/href="#"/.test(src[f].replace(/href="#main"/g, '')) && !/href="\s*javascript:/i.test(src[f]));
	const blanks = src[f].match(/<a\b[^>]*target="_blank"[^>]*>/g) || [];
	test(f + ': target=_blank bağlantıları rel noopener noreferrer taşır', blanks.every((t) => /rel="noopener noreferrer"/.test(t)));
});
// 5) PHP 7.3
['inc/content-helpers.php', 'archive-mb_haber.php', 'archive-mb_dokuman.php', 'page-referanslar.php', 'page-sss.php', 'template-parts/forms/form.php', 'template-parts/forms/form-status.php', 'template-parts/content/news-card.php', 'template-parts/content/document-card.php', 'template-parts/content/location-card.php', 'template-parts/content/reference-grid.php', 'template-parts/content/faq-list.php', 'template-parts/content/location-list.php', 'template-parts/content/filter-links.php'].forEach((f) => {
	test(f + ': PHP 7.4+/8.x sözdizimi yok', !/\?->|\?\?=|\bfn\s*\(|\bmatch\s*\(|\bstr_contains\s*\(|\benum\s+\w+\s*\{/.test(rel(f)) && rel(f) !== '');
});
console.log(pass + '/' + (pass + fail) + ' tema içerik/form sözleşme testi geçti.');
process.exit(fail ? 1 : 0);
