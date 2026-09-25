'use strict';
/**
 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı — extract-source.js'in literal-only
 * ayrıştırıcısının (parseLiteral) tüm reddetme sınıflarını gerçekten
 * kapattığını kanıtlar. Hiçbir test burada gerçek kod çalıştırmaz; hepsi
 * ayrıştırıcının kendisini crafted string girdilerle çağırır.
 *
 * Ayrıca: extract-source.js modülünün kaynak metninde `vm`/`eval`/
 * `new Function`/child_process çağrısı OLMADIĞINI statik olarak doğrular
 * — bu, "bir sonraki değişiklik yanlışlıkla execute-then-eval'a geri
 * dönerse" regresyonunu yakalar.
 *
 * Run: node wordpress-site/tools/import/test-extract-safety.js
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');
const { _internal, extractAll, RECORD_SHAPES } = require('./extract-source');

let failures = 0;
let total = 0;

function test(label, fn) {
	total++;
	try {
		fn();
		process.stdout.write('PASS  ' + label + '\n');
	} catch (e) {
		failures++;
		process.stdout.write('FAIL  ' + label + ' — ' + e.message + '\n');
	}
}

function expectRejected(label, src, globalName) {
	test(label, function () {
		assert.throws(function () {
			_internal.parseLiteral(_internal.stripFullLineComments(src).trim(), globalName || 'MB_SECTORS');
		});
	});
}

function expectAllowedValue(label, src, globalName, expectedJson) {
	test(label, function () {
		const value = _internal.parseLiteral(_internal.stripFullLineComments(src).trim(), globalName || 'MB_SECTORS');
		assert.strictEqual(JSON.stringify(value), expectedJson);
	});
}

// --- the real shape, must pass and produce the exact expected value ---
expectAllowedValue(
	'gerçek biçim (yorum + tek atama) kabul edilir ve doğru değer üretir',
	'// bir yorum satırı\nwindow.MB_SECTORS = [\n  { slug: "a" }\n];\n',
	'MB_SECTORS',
	'[{"slug":"a"}]'
);
expectAllowedValue('sondaki ; opsiyonel', 'window.MB_SECTORS = [{ slug: "a" }]', 'MB_SECTORS', '[{"slug":"a"}]');
expectAllowedValue('sondaki virgül (trailing comma) izinli', 'window.MB_SECTORS = [{ slug: "a", },];', 'MB_SECTORS', '[{"slug":"a"}]');
expectAllowedValue('boş dizi izinli', 'window.MB_SECTORS = [];', 'MB_SECTORS', '[]');
expectAllowedValue(
	'iç içe dizi/obje (fees.js şekli) doğru ayrıştırılır',
	'window.MB_FEES = [{ name: "X", level: 3, options: [{ label: "a", units: ["A1","A2"], amount: 100 }], vatIncluded: true, sourcePage: 2 }];',
	'MB_FEES',
	'[{"name":"X","level":3,"options":[{"label":"a","units":["A1","A2"],"amount":100}],"vatIncluded":true,"sourcePage":2}]'
);
expectAllowedValue('null değeri kabul edilir', 'window.MB_SECTORS = [{ image: null }];', 'MB_SECTORS', '[{"image":null}]');
expectAllowedValue('negatif tam sayı kabul edilir', 'window.MB_SECTORS = [{ level: -1 }];', 'MB_SECTORS', '[{"level":-1}]');
test('http:// içeren string yorum sanılıp silinmez, olduğu gibi ayrıştırılır', function () {
	const value = _internal.parseLiteral('window.MB_SECTORS = [{ image: "http://example.com/a.png" }];', 'MB_SECTORS');
	assert.strictEqual(value[0].image, 'http://example.com/a.png');
});
test('stripFullLineComments bir satır-içi "//" alt dizisini SİLMEZ (yalnız tam satır yorumları)', function () {
	const src = 'window.MB_SECTORS = [{ image: "http://example.com" }];';
	assert.strictEqual(_internal.stripFullLineComments(src), src);
});

// --- the exact exploit reported by the independent review ---
expectRejected(
	'KRİTİK: window.constructor.constructor("return process.version")() Function-constructor kaçışı reddedilir',
	'window.MB_SECTORS = [{ value: window.constructor.constructor("return process.version")() }];'
);
expectRejected(
	'KRİTİK: IIFE çağrısının sonucu dizi elemanı olarak verilmesi reddedilir',
	'window.MB_SECTORS = [(function () { return { slug: "a" }; })()];'
);
expectRejected(
	'KRİTİK: ikinci "window.X = []; window.X = [];" ataması reddedilir (yalnız TEK ifadeye izin var)',
	'window.MB_SECTORS = []; window.MB_SECTORS = [];'
);

// --- other bypass classes required by the task ---
expectRejected('process global erişimi (tanımlayıcı olarak) reddedilir', 'window.MB_SECTORS = [{ slug: process.env.SECRET }];');
expectRejected('require() çağrısı reddedilir', 'window.MB_SECTORS = [{ slug: require("fs").readFileSync("x") }];');
expectRejected('fetch()/dış ağ çağrısı denemesi reddedilir', 'window.MB_SECTORS = [{ slug: "a" }]; fetch("https://evil.example/");');
expectRejected('fonksiyon çağrısı (herhangi) reddedilir', 'window.MB_SECTORS = [String(1)];');
expectRejected('new ile nesne oluşturma reddedilir', 'window.MB_SECTORS = [new Array(1)];');
expectRejected('this ifadesi reddedilir', 'window.MB_SECTORS = [this];');
expectRejected('class tanımı reddedilir', 'class X {}; window.MB_SECTORS = [];');
expectRejected('spread operatörü (dizi içinde) reddedilir', 'window.MB_SECTORS = [...[1,2]];');
expectRejected('spread operatörü (obje içinde) reddedilir', 'window.MB_SECTORS = [{ ...{} }];');
expectRejected('hesaplanmış anahtar ([expr]:) reddedilir', 'window.MB_SECTORS = [{ [1+1]: "a" }];');
expectRejected('getter tanımı reddedilir', 'window.MB_SECTORS = [{ get slug() { return 1; } }];');
expectRejected('template literal reddedilir', 'window.MB_SECTORS = [{ slug: `a${1}` }];');
expectRejected('regex literal reddedilir', 'window.MB_SECTORS = [/a/g];');
expectRejected('aritmetik operatör reddedilir', 'window.MB_SECTORS = [1 + 1];');
expectRejected('mantıksal operatör reddedilir', 'window.MB_SECTORS = [true && false];');
expectRejected('tanımlayıcı/değişken değer olarak reddedilir', 'window.MB_SECTORS = [undefinedVar];');
expectRejected('yinelenen obje anahtarı reddedilir', 'window.MB_SECTORS = [{ slug: "a", slug: "b" }];');
expectRejected('constructor anahtarı reddedilir', 'window.MB_SECTORS = [{ constructor: 1 }];');
expectRejected('prototype anahtarı reddedilir', 'window.MB_SECTORS = [{ prototype: 1 }];');
expectRejected('__proto__ anahtarı reddedilir', 'window.MB_SECTORS = [{ __proto__: {} }];');
expectRejected('izin listesi dışı anahtar reddedilir', 'window.MB_SECTORS = [{ evilKey: 1 }];');
expectRejected('ondalık sayı reddedilir (yalnız tam sayı izinli)', 'window.MB_SECTORS = [{ level: 1.5 }];');
expectRejected('üstel gösterim reddedilir', 'window.MB_SECTORS = [{ level: 1e10 }];');
expectRejected('hex sayı reddedilir', 'window.MB_SECTORS = [{ level: 0x1F }];');
expectRejected('NaN reddedilir', 'window.MB_SECTORS = [{ level: NaN }];');
expectRejected('Infinity reddedilir', 'window.MB_SECTORS = [{ level: Infinity }];');
expectRejected('yanlış global ada atama reddedilir', 'window.MB_OTHER = [{ slug: "a" }];');
expectRejected('dizi değil obje ataması reddedilir (parantez farkı)', 'window.MB_SECTORS = { slug: "a" };');
expectRejected('eval çağrısı içeren kaynak reddedilir', 'window.MB_SECTORS = eval("[{slug:\'a\'}]");');
expectRejected('boş kaynak reddedilir', '');
expectRejected('sonsuz döngü payload\'ı hiç ÇALIŞTIRILMADAN, ayrıştırma aşamasında reddedilir', 'window.MB_SECTORS = [(function(){while(true){}})()];');

// --- §3 (Faz 6A Son Kabul Düzeltmesi): kaynak türüne özel derin şekil sözleşmesi ---
// Gerçek parse+shape zinciri (parseLiteral -> validateShape), tek başına
// parseLiteral değil — 2.1 bulgusunun (birleşik ALLOWED_KEYS bir kaynak
// türünün alanının BAŞKA bir türe sızmasını engellemiyordu) kapatıldığını
// tam zincirle kanıtlar.
function expectShapeRejected(label, src, globalName, shapeKey) {
	test(label, function () {
		assert.throws(function () {
			const value = _internal.parseLiteral(_internal.stripFullLineComments(src).trim(), globalName);
			value.forEach(function (record, i) {
				_internal.validateShape(record, RECORD_SHAPES[shapeKey], 'test[' + i + ']');
			});
		});
	});
}

expectShapeRejected(
	'2.1 KAPANDI: sektör kaydında ücrete ait "sourcePage" alanı reddedilir',
	'window.MB_SECTORS = [{ slug: "a", name: "A", desc: "D", icon: "x", image: "", sourcePage: 2 }];',
	'MB_SECTORS',
	'sectors'
);
expectShapeRejected(
	'yeterlilik kaydında ücrete ait "options" alanı reddedilir',
	'window.MB_QUALIFICATIONS = [{ code: "10UY0002-3/03", name: "X", level: 3, sector: "makine", options: [] }];',
	'MB_QUALIFICATIONS',
	'qualifications'
);
expectShapeRejected(
	'ücret ana kaydında sektöre ait "desc" alanı reddedilir',
	'window.MB_FEES = [{ name: "X", level: 3, sector: "makine", qualificationCode: "", pricingType: "single", options: [{label:"a",amount:1}], vatIncluded: true, certificatePrintFeeExcluded: 1, source: "s", sourcePage: 1, desc: "sızan alan" }];',
	'MB_FEES',
	'fees'
);
expectShapeRejected(
	'fiyat seçeneği nesnesinde üst-seviyeye ait "name" alanı reddedilir',
	'window.MB_FEES = [{ name: "X", level: 3, sector: "makine", qualificationCode: "", pricingType: "single", options: [{label:"a",amount:1,name:"sızan"}], vatIncluded: true, certificatePrintFeeExcluded: 1, source: "s", sourcePage: 1 }];',
	'MB_FEES',
	'fees'
);
expectShapeRejected(
	'zorunlu alan eksikliği reddedilir (sektörde "image" yok)',
	'window.MB_SECTORS = [{ slug: "a", name: "A", desc: "D", icon: "x" }];',
	'MB_SECTORS',
	'sectors'
);
expectShapeRejected(
	'yanlış scalar tip reddedilir (yeterlilikte level string)',
	'window.MB_QUALIFICATIONS = [{ code: "10UY0002-3/03", name: "X", level: "3", sector: "makine" }];',
	'MB_QUALIFICATIONS',
	'qualifications'
);
expectShapeRejected(
	'yanlış dizi/scalar tip reddedilir (ücrette options bir dizi değil)',
	'window.MB_FEES = [{ name: "X", level: 3, sector: "makine", qualificationCode: "", pricingType: "single", options: "yanlış", vatIncluded: true, certificatePrintFeeExcluded: 1, source: "s", sourcePage: 1 }];',
	'MB_FEES',
	'fees'
);
expectShapeRejected(
	'seçenek nesnesinde fazladan alan reddedilir (units yerine "unit")',
	'window.MB_FEES = [{ name: "X", level: 3, sector: "makine", qualificationCode: "", pricingType: "single", options: [{label:"a",amount:1,unit:"A1"}], vatIncluded: true, certificatePrintFeeExcluded: 1, source: "s", sourcePage: 1 }];',
	'MB_FEES',
	'fees'
);

test('Object.create(null) ile kurulan obje literalinde "__proto__" ayarı gerçek prototip zincirini DEĞİŞTİRMEZ (savunma derinliği)', function () {
	// __proto__ zaten FORBIDDEN_KEYS ile parse aşamasında reddediliyor
	// (yukarıdaki test) — bu, o katman hiç var olmasa bile parseObject()'in
	// null-prototip objeleri kullandığını ayrıca kanıtlar.
	const value = _internal.parseLiteral('window.MB_SECTORS = [{ slug: "a" }];', 'MB_SECTORS');
	assert.strictEqual(Object.getPrototypeOf(value[0]), null);
});

// --- static source-text check: no execution primitive exists in the module ---
test('extract-source.js kaynak metninde vm/eval/Function/child_process çağrısı YOK (statik regresyon kontrolü)', function () {
	const src = fs.readFileSync(path.join(__dirname, 'extract-source.js'), 'utf8');
	const forbidden = [
		/\brequire\(\s*['"]vm['"]\s*\)/,
		/\bnew\s+Function\s*\(/,
		/\beval\s*\(/,
		/\brequire\(\s*['"]child_process['"]\s*\)/,
		/\.runInContext\s*\(/,
		/\.runInNewContext\s*\(/,
	];
	forbidden.forEach(function (re) {
		assert.ok(!re.test(src), 'yasaklı çalıştırma deseni bulundu: ' + re);
	});
});

// --- positive end-to-end: the 3 real frozen sources still parse without executing anything ---
test('3 gerçek kaynak dosya (sectors/qualifications/fees) ayrıştırıcıdan hatasız geçer', function () {
	const all = extractAll();
	assert.ok(Array.isArray(all.sectors.data) && all.sectors.data.length === 14);
	assert.ok(Array.isArray(all.qualifications.data) && all.qualifications.data.length === 83);
	assert.ok(Array.isArray(all.fees.data) && all.fees.data.length === 103);
});

// ---------------------------------------------------------------------
// Faz 7 — news.js / references.js (içerik kaynakları) için EK güvenlik testleri.
// Aynı fail-closed kurallar: kod ÇALIŞTIRILMAZ, yalnız literal ayrıştırılır; yeni tek yenilik,
// TAM SATIR tek satırlık blok yorumunun (references.js dosya başlığı) silinebilmesidir.
// ---------------------------------------------------------------------
const { extractContent, SOURCES, ALLOWED_KEYS } = require('./extract-source');

expectAllowedValue(
	'Faz 7: tam satır tek satırlık blok yorumu (references.js başlığı) silinir, ardındaki atama ayrıştırılır',
	'/* Temsili referans logo verisi — gerçek müşteri değildir */\nwindow.MB_REFERENCES = [\n  { name: "A", file: "a.svg", alt: "x" }\n];\n',
	'MB_REFERENCES',
	'[{"name":"A","file":"a.svg","alt":"x"}]'
);
expectRejected('Faz 7: satır ortasında blok yorumu (dizi içinde) hâlâ reddedilir', 'window.MB_REFERENCES = [ /* x */ ];', 'MB_REFERENCES');
expectRejected('Faz 7: blok yorumunun ardından AYNI satırda kod gelirse yorum silinmez, kod ayrıştırıcıda reddedilir', '/* x */ window.MB_REFERENCES = [];', 'MB_REFERENCES');
expectRejected('Faz 7: çok satırlı blok yorumu silinmez, belirteçleyici reddeder (fail-closed)', '/*\nyorum\n*/\nwindow.MB_REFERENCES = [];', 'MB_REFERENCES');
expectRejected('Faz 7: kapatılmamış blok yorumu reddedilir', '/* kapanmıyor\nwindow.MB_REFERENCES = [];', 'MB_REFERENCES');
expectRejected('Faz 7: blok yorumunun İÇİNE gizlenmiş bir atama değeri olarak kullanılamaz (yorum değer değildir)', 'window.MB_REFERENCES = /* [] */;', 'MB_REFERENCES');
test('Faz 7: blok yorumu yalnız YORUM satırıdır — içindeki kod ASLA değerlendirilmez (ikinci atama sızmaz)', function () {
	const src = '/* window.MB_REFERENCES = [{ name: "gizli", file: "x", alt: "y" }]; */\nwindow.MB_REFERENCES = [];\n';
	const value = _internal.parseLiteral(_internal.stripFullLineComments(src).trim(), 'MB_REFERENCES');
	assert.strictEqual(JSON.stringify(value), '[]');
});
test('Faz 7: stripFullLineComments string değeri içindeki "/* */" veya "//" metnini SİLMEZ', function () {
	const src = 'window.MB_NEWS = [\n  { slug: "a", title: "x /* y */ z // w", date: "2026-01-01", type: "haber", image: "i", summary: "s", body: "b" }\n];\n';
	const value = _internal.parseLiteral(_internal.stripFullLineComments(src).trim(), 'MB_NEWS');
	assert.strictEqual(value[0].title, 'x /* y */ z // w');
});
expectRejected('Faz 7: MB_NEWS global adı beklenirken MB_REFERENCES atanmışsa reddedilir', 'window.MB_REFERENCES = [];', 'MB_NEWS');
expectRejected('Faz 7: news kaydında yasaklı anahtar (constructor) reddedilir', 'window.MB_NEWS = [{ constructor: "x" }];', 'MB_NEWS');
expectRejected('Faz 7: news kaydında fonksiyon çağrısı/IIFE değeri reddedilir', 'window.MB_NEWS = [{ slug: (function(){ return "a"; })() }];', 'MB_NEWS');
expectRejected('Faz 7: references kaydında şablon literali/değişken değer reddedilir', 'window.MB_REFERENCES = [{ name: `a`, file: x, alt: "y" }];', 'MB_REFERENCES');
test('Faz 7: news kayıt şekli — geçerli kabul; fazladan (katalog) alan, eksik alan, yanlış tip, boş metin reddedilir', function () {
	const ok = { slug: 'a', title: 't', date: '2026-01-01', type: 'haber', image: 'i', summary: 's', body: 'b' };
	_internal.validateShape(ok, RECORD_SHAPES.news, 'n');
	assert.throws(function () { _internal.validateShape(Object.assign({}, ok, { sourcePage: 3 }), RECORD_SHAPES.news, 'n'); });
	assert.throws(function () { _internal.validateShape(Object.assign({}, ok, { desc: 'x' }), RECORD_SHAPES.news, 'n'); });
	const noBody = Object.assign({}, ok);
	delete noBody.body;
	assert.throws(function () { _internal.validateShape(noBody, RECORD_SHAPES.news, 'n'); });
	assert.throws(function () { _internal.validateShape(Object.assign({}, ok, { date: 20260101 }), RECORD_SHAPES.news, 'n'); });
	assert.throws(function () { _internal.validateShape(Object.assign({}, ok, { title: '' }), RECORD_SHAPES.news, 'n'); });
});
test('Faz 7: references kayıt şekli — geçerli kabul; fazladan alan (desc/slug), eksik alan, yanlış tip reddedilir', function () {
	const ok = { name: 'A', file: 'a.svg', alt: 'x' };
	_internal.validateShape(ok, RECORD_SHAPES.references, 'r');
	assert.throws(function () { _internal.validateShape(Object.assign({}, ok, { desc: 'x' }), RECORD_SHAPES.references, 'r'); });
	assert.throws(function () { _internal.validateShape(Object.assign({}, ok, { slug: 'a' }), RECORD_SHAPES.references, 'r'); });
	assert.throws(function () { _internal.validateShape({ name: 'A', file: 'a.svg' }, RECORD_SHAPES.references, 'r'); });
	assert.throws(function () { _internal.validateShape(Object.assign({}, ok, { alt: 5 }), RECORD_SHAPES.references, 'r'); });
});
test('Faz 7: SOURCES tam beş sabit, tanitim-site/assets/data altında yol taşır; ALLOWED_KEYS yeni yedi anahtarı içerir, yasaklıları içermez', function () {
	assert.deepStrictEqual(Object.keys(SOURCES).sort(), ['fees', 'news', 'qualifications', 'references', 'sectors']);
	Object.keys(SOURCES).forEach(function (k) {
		assert.ok(/^tanitim-site\/assets\/data\/[a-z]+\.js$/.test(SOURCES[k].repoRelativePath), k + ' yolu sabit kalıba uymuyor');
	});
	['title', 'date', 'type', 'summary', 'body', 'file', 'alt'].forEach(function (k) { assert.ok(ALLOWED_KEYS.has(k)); });
	['constructor', 'prototype', '__proto__', 'proto', 'eval'].forEach(function (k) { assert.ok(!ALLOWED_KEYS.has(k)); });
});
test('Faz 7: iki gerçek içerik kaynağı (news.js 6, references.js 12) ayrıştırıcıdan hatasız geçer; extractAll() hâlâ YALNIZ üç katalog kaynağı', function () {
	const c = extractContent();
	assert.strictEqual(c.news.data.length, 6);
	assert.strictEqual(c.references.data.length, 12);
	assert.ok(/^[0-9a-f]{64}$/.test(c.news.sha256) && /^[0-9a-f]{64}$/.test(c.references.sha256));
	assert.deepStrictEqual(Object.keys(extractAll()), ['sectors', 'qualifications', 'fees']);
});
test('Faz 7: kaynak özeti mevcut yöntemle (dosyanın ham UTF-8 baytlarının SHA-256\'sı) hesaplanır', function () {
	const crypto = require('crypto');
	const c = extractContent();
	const raw = fs.readFileSync(path.resolve(__dirname, '..', '..', '..', 'tanitim-site', 'assets', 'data', 'news.js'));
	assert.strictEqual(c.news.sha256, crypto.createHash('sha256').update(raw).digest('hex'));
});

if (failures > 0) {
	process.stderr.write('\n' + failures + '/' + total + ' güvenlik testi BAŞARISIZ.\n');
	process.exit(1);
}
process.stdout.write('\nTüm ' + total + ' güvenlik testi geçti.\n');
