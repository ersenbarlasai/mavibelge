'use strict';
/**
 * Faz 10 statik test: deploy/ şablonlarında gerçek anahtar/parola benzeri değer YOK ve gerekli yönergeler VAR.
 * Apache sözdizimini DOĞRULAMAZ (bu depoda Apache çalıştırılmaz). Kullanım: node tools/test-faz10-deploy-templates.js
 */
const fs = require('fs');
const path = require('path');
const dir = path.join(__dirname, '..', 'deploy');
let pass = 0;
let fail = 0;
function check(label, cond) {
	if (cond) {
		pass++;
		console.log('PASS  ' + label);
	} else {
		fail++;
		console.log('FAIL  ' + label);
	}
}
const read = (f) => fs.readFileSync(path.join(dir, f), 'utf8');
const active = (text) => text.split(/\r?\n/).filter((l) => !/^\s*(#|\/\/|\*|\/\*)/.test(l)).join('\n');

const cfg = read('wp-config-hardening.template.php');
const ht = read('htaccess-hardening.template');
const up = read('uploads-htaccess.template');
const hd = read('security-headers.htaccess.template');
const readme = read('README.md');
const all = cfg + ht + up + hd;

/* gizli bilgi benzeri değer yok */
check('wp-config şablonu: tuz/parola/DB satırları YALNIZ yorumda (etkin define yok)', !/define\(\s*'(AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY|AUTH_SALT|SECURE_AUTH_SALT|LOGGED_IN_SALT|NONCE_SALT|DB_PASSWORD|DB_USER|DB_NAME)'/.test(active(cfg)));
check('tuz yer tutucuları gerçek değil (<YER-TUTUCU>) ve parola satırı yer tutucu', (cfg.match(/<YER-TUTUCU>/g) || []).length === 8 && /<parola-buraya-YAZILMAZ>/.test(cfg));
check('hiçbir şablonda 24+ karakterlik yüksek entropili anahtar benzeri dize yok', !/['"](?=[A-Za-z0-9+\/=_-]*[a-z])(?=[A-Za-z0-9+\/=_-]*[0-9])[A-Za-z0-9+\/=_-]{24,}['"]/.test(all.replace(/https?:\/\/[^\s'"]+/g, '')));
check('hiçbir şablonda etkin parola/anahtar/jeton ataması yok', !/(password|passwd|secret|token|api[_-]?key)\s*[:=]\s*['"]?[A-Za-z0-9]{8,}/i.test(active(all)));
check('şablonlarda gerçek IP/kullanıcı yolu yok (yalnız <yer-tutucu> yollar)', !/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/.test(all) && !/\/home\/(?!<kullanici>)[a-z0-9_]+\//i.test(all));

/* wp-config gerekli yönergeler */
for (const [label, re] of [
	['DISALLOW_FILE_EDIT true', /^define\( 'DISALLOW_FILE_EDIT', true \);/m],
	['WP_DEBUG false', /^define\( 'WP_DEBUG', false \);/m],
	['WP_DEBUG_DISPLAY false', /^define\( 'WP_DEBUG_DISPLAY', false \);/m],
	['WP_DEBUG_LOG false (varsayılan)', /^define\( 'WP_DEBUG_LOG', false \);/m],
	['WP_ENVIRONMENT_TYPE', /^define\( 'WP_ENVIRONMENT_TYPE', 'production' \);/m],
	['WP_POST_REVISIONS', /^define\( 'WP_POST_REVISIONS', \d+ \);/m],
	['FORCE_SSL_ADMIN (yorumlu, HTTPS uyarılı)', /^\/\/ define\( 'FORCE_SSL_ADMIN', true \);/m],
	['DISABLE_WP_CRON yorumlu', /^\/\/ define\( 'DISABLE_WP_CRON', true \);/m],
	['AUTOMATIC_UPDATER_DISABLED notu (yorumlu)', /^\/\/ define\( 'AUTOMATIC_UPDATER_DISABLED', true \);/m],
	['WP_DEBUG_LOG önerisi webroot DIŞI (yorumlu, yer tutucu yol)', /WP_DEBUG_LOG', '\/home\/<kullanici>\/logs\//],
	['gerçek cron varsayılmaz açıklaması', /Gerçek cron VARSAYILMAZ|GERÇEK bir cron/],
]) {
	check('wp-config şablonu: ' + label, re.test(cfg));
}

/* .htaccess sertleştirme */
for (const [label, re] of [
	['Options -Indexes', /^Options -Indexes/m],
	['wp-config.php kapalı', /<Files "wp-config\.php">/],
	['xmlrpc.php kapalı', /<Files "xmlrpc\.php">/],
	['readme.html ve license.txt kapalı', /readme\\\.html\|license\\\.txt/],
	['*.log kapalı', /<FilesMatch "\\\.log\$">/],
	['.git kapalı', /RewriteRule \^\\\.git - \[F,L\]/],
	['wp-content/debug.log kapalı', /debug\\\.log/],
	['Apache 2.4 sözdizimi (Require all denied)', /Require all denied/],
	['eski sözdizimi IfModule !mod_authz_core.c altında', /<IfModule !mod_authz_core\.c>\s*Order allow,deny\s*Deny from all/],
	['DOĞRULANMAMIŞTIR uyarısı', /DOĞRULANMAMIŞTIR/],
]) {
	check('.htaccess şablonu: ' + label, re.test(ht));
}

/* uploads */
for (const [label, re] of [
	['php uzantıları', /php\[0-9\]\?/],
	['phtml ve pht', /phtml\|pht/],
	['phar', /phar/],
	['cgi', /cgi/],
	['Options -ExecCGI', /^Options -Indexes -ExecCGI/m],
	['Apache 2.4 + eski sözdizimi ikilisi', /Require all denied[\s\S]*Order allow,deny/],
	['DOĞRULANMAMIŞTIR uyarısı', /DOĞRULANMAMIŞTIR/],
]) {
	check('uploads şablonu: ' + label, re.test(up));
}

/* güvenlik başlıkları */
check('başlık şablonu: X-Powered-By kaldırma etkin', /^\s*Header unset X-Powered-By/m.test(hd));
check('başlık şablonu: HSTS YALNIZ yorum satırında + şartlar yazılı', /^\s*#\s*Header always set Strict-Transport-Security/m.test(hd) && !/^\s*Header (always )?set Strict-Transport-Security/m.test(hd) && /ŞARTLAR/.test(hd));
check('başlık şablonu: CSP yalnız report-only ve yorum satırında; etkin CSP yok', /#\s*Header set Content-Security-Policy-Report-Only/.test(hd) && !/^\s*Header (always )?set Content-Security-Policy/m.test(hd));
check('başlık şablonu: DOĞRULANMAMIŞTIR ve HTTPS/CDN uyarısı', /DOĞRULANMAMIŞTIR/.test(hd) && /HTTPS\/CDN/.test(hd));

/* README */
for (const f of ['wp-config-hardening.template.php', 'htaccess-hardening.template', 'uploads-htaccess.template', 'security-headers.htaccess.template']) {
	check('deploy/README.md şablonu listeler: ' + f, readme.includes('(' + f + ')'));
}

console.log('\nFaz 10 deploy şablonu statik testi: ' + pass + ' geçti, ' + fail + ' başarısız.');
process.exit(fail > 0 ? 1 : 0);
