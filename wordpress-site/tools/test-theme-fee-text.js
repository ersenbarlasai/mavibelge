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

test(
	'fee-options.php: yeni "sınav ücretine dahil değildir" metni gerçekten var',
	src.indexOf("Belge basım ücreti sınav ücretine dahil değildir: %s") !== -1
);
test(
	'fee-options.php: eski, yanıltıcı "Belge basım ücreti: %s" metni ARTIK YOK',
	src.indexOf("'Belge basım ücreti: %s'") === -1
);
test(
	'fee-options.php: metin hâlâ mevcut certificate_print_fee_display alanını kullanıyor (yeni boolean alan eklenmedi)',
	/certificate_print_fee_display/.test(src)
);

if (failures > 0) {
	process.stderr.write('\n' + failures + '/' + total + ' tema ücret metni testi BAŞARISIZ.\n');
	process.exit(1);
}
process.stdout.write('\nTüm ' + total + ' tema ücret metni testi geçti.\n');
