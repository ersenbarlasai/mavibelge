'use strict';
/**
 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı §7 — statik kaynak-metni testi:
 * belge basım ücretinin sınav ücretine DAHİL OLMADIĞINI açıkça belirten
 * metnin gerçekten şablonda olduğunu doğrular. PHP çalıştırılmadığı için
 * (bu ortamda `php` yok) bu, WordPress'in kendisini derlemeden yapılabilen
 * en güçlü kontrol — tam metni ve yanlış/eski metnin YOKLUĞUNU statik
 * olarak kontrol eder.
 *
 * Run: node wordpress-site/tools/test-theme-fee-text.js
 */

const fs = require('fs');
const path = require('path');

const FILE = path.resolve(__dirname, '..', 'wp-content', 'themes', 'mavibelge', 'template-parts', 'catalog', 'fee-options.php');

let failures = 0;
let total = 0;

function test(label, condition) {
	total++;
	if (condition) {
		process.stdout.write('PASS  ' + label + '\n');
	} else {
		failures++;
		process.stdout.write('FAIL  ' + label + '\n');
	}
}

const src = fs.readFileSync(FILE, 'utf8');
const code = src.replace(/\/\*[\s\S]*?\*\//g, '').split('\n').filter((l) => !/^\s*(\/\/|\*)/.test(l)).join('\n');
const QUERY = fs.readFileSync(path.resolve(__dirname, '..', 'wp-content', 'plugins', 'mavibelge-core', 'includes', 'class-catalog-query.php'), 'utf8')
	.replace(/\/\*[\s\S]*?\*\//g, '').split('\n').filter((l) => !/^\s*(\/\/|\*)/.test(l)).join('\n');
const SERVICE = fs.readFileSync(path.resolve(__dirname, '..', 'wp-content', 'plugins', 'mavibelge-core', 'public', 'class-catalog-service.php'), 'utf8');

test(
	'Faz 15 fee-options.php: ziyaretçi açıklaması eklentinin pricing_note alanından basılır (esc_html)',
	/esc_html\(\s*\$presented\['pricing_note'\]\s*\)/.test(code)
);
test(
	'Faz 15 fee-options.php: tema KDV oranı / belge basım tutarı / KDV cümlesi YAZMAZ (koda gömülü "%20", "1.500", "KDV" yok)',
	!/%20|1\.500|1500|KDV/.test(code)
);
test(
	'fee-options.php: eski, yanıltıcı "Belge basım ücreti: %s" metni ARTIK YOK',
	src.indexOf("'Belge basım ücreti: %s'") === -1
);
test(
	'Faz 15 eklenti: standart cümleler kaydın değerlerinden üretilir — oran %<oran>, tutar kurus_to_lira_compact_display(), "fiyatlara dahil değildir"',
	/public static function fee_pricing_statements\(/.test(QUERY) && /'Fiyatlara ' \. \$rate \. 'KDV dahildir\.'/.test(QUERY)
		&& /kurus_to_lira_compact_display\( \$certificate_kurus \) \. ' TL MYK belge basım ücreti fiyatlara dahil değildir\.'/.test(QUERY)
		&& !/'%20|20 KDV|1\.500 TL MYK/.test(QUERY)
);
test(
	'Faz 15 eklenti: present_fee() pricing_note/vat_statement/certificate_statement döndürür ve oranı DTO\'dan (vat_rate_percent) alır',
	/'pricing_note'\s*=> \$statements\['pricing_note'\]/.test(SERVICE) && /'vat_rate_percent'\s*=> MaviBelge_Core_Meta_Schema::fee_vat_rate_percent\(/.test(SERVICE)
);

if (failures > 0) {
	process.stderr.write('\n' + failures + '/' + total + ' tema ücret metni testi BAŞARISIZ.\n');
	process.exit(1);
}
process.stdout.write('\nTüm ' + total + ' tema ücret metni testi geçti.\n');
