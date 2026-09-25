'use strict';
/**
 * Faz 7 — içerik (haber + referans) manifest hattı için negatif/regresyon testleri.
 * Hiçbir test gerçek data/ dosyalarını DEĞİŞTİRMEZ: yazma testleri yalnız os.tmpdir() altındaki
 * geçici dizinlerde çalışır; bellek içi mutasyonlar taze üretimin derin kopyası üzerindedir.
 *
 * Run: node wordpress-site/tools/import/test-content-manifest.js
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('assert');

const {
	computeContent, deriveFiles, runBuild, writeContentAtomic, DATA_DIR, SCHEMA_DIR, NEWS_PATH, REFERENCES_PATH,
} = require('./build-content-manifest');
const { compareFreshToDisk, findUnexpectedContentFiles } = require('./verify-content-manifest');
const { validateContentFiles } = require('./lib/validate-content-set');
const { extractContent } = require('./extract-source');
const { toDeterministicJson } = require('./lib/hash');
const { EXPECTED, CONTENT_EXPECTED } = require('./lib/expected-counts');

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

const sources = extractContent();
const base = computeContent();
assert.strictEqual(base.errors.length, 0, 'taze üretim hatasız olmalı: ' + base.errors.join(' | '));

function clone(value) {
	return JSON.parse(JSON.stringify(value));
}
function freshFiles() {
	return clone(base.files);
}
function validate(files, freshFn) {
	return validateContentFiles(files, sources, SCHEMA_DIR, freshFn === undefined ? function () { return deriveFiles(sources).files; } : freshFn).errors;
}
function tmpDir(label) {
	return fs.mkdtempSync(path.join(os.tmpdir(), 'mbcontent-' + label + '-'));
}
function rmTree(dir) {
	if (!fs.existsSync(dir)) {
		return;
	}
	fs.readdirSync(dir).forEach(function (name) {
		const p = path.join(dir, name);
		if (fs.statSync(p).isDirectory()) {
			rmTree(p);
		} else {
			fs.unlinkSync(p);
		}
	});
	fs.rmdirSync(dir);
}
function expectErrors(label, mutate, needle) {
	test(label, function () {
		const files = freshFiles();
		mutate(files);
		const errors = validate(files);
		assert.ok(errors.length > 0, 'hata beklenirdi, hiç yok');
		if (needle) {
			assert.ok(errors.some(function (e) { return e.indexOf(needle) !== -1; }), 'beklenen ipucu "' + needle + '" bulunamadı: ' + errors.slice(0, 3).join(' | '));
		}
	});
}

/* --- A) pozitif taban ve sabitler --- */
test('taze üretim: haber 6, referans 12; doğrulayıcı hatasız', function () {
	assert.strictEqual(base.files[NEWS_PATH].records.length, 6);
	assert.strictEqual(base.files[REFERENCES_PATH].records.length, 12);
	assert.deepStrictEqual(validate(freshFiles()), []);
});
test('sayı sabitleri: CONTENT_EXPECTED 6/12; katalog EXPECTED 14/83/103/145/87/16/58/84/19 DEĞİŞMEDİ', function () {
	assert.deepStrictEqual(CONTENT_EXPECTED, { news: 6, references: 12 });
	assert.deepStrictEqual(EXPECTED, { sectors: 14, qualifications: 83, fees: 103, priceOptionsTotal: 145, pricingSingle: 87, pricingMulti: 16, multiOptionsTotal: 58, feesWithCode: 84, feesWithoutCode: 19 });
});
test('deterministik: iki taze üretim byte-eşit JSON', function () {
	const again = computeContent();
	assert.strictEqual(toDeterministicJson(again.files[NEWS_PATH]), toDeterministicJson(base.files[NEWS_PATH]));
	assert.strictEqual(toDeterministicJson(again.files[REFERENCES_PATH]), toDeterministicJson(base.files[REFERENCES_PATH]));
});
test('kayıt şekli: haber {slug,title,published_on,news_type,summary,body}; referans {name,slug,logo_file,alt}; image/website alanı YOK', function () {
	const n = base.files[NEWS_PATH].records[0];
	const r = base.files[REFERENCES_PATH].records[0];
	assert.deepStrictEqual(Object.keys(n), ['schema_version', 'source_key', 'source_index', 'slug', 'title', 'published_on', 'news_type', 'summary', 'body', 'source']);
	assert.deepStrictEqual(Object.keys(r), ['schema_version', 'source_key', 'source_index', 'name', 'slug', 'logo_file', 'alt', 'source']);
	assert.strictEqual(n.source_key, 'news:' + n.slug);
	assert.strictEqual(r.source_key, 'reference:' + r.slug);
});
test('gerçek kaynak değerleri birebir taşınır (uydurma yok): ilk haber slug/tarih/tür, referans adları', function () {
	const news = base.files[NEWS_PATH].records;
	news.forEach(function (rec, i) {
		assert.strictEqual(rec.slug, sources.news.data[i].slug);
		assert.strictEqual(rec.published_on, sources.news.data[i].date);
		assert.strictEqual(rec.news_type, sources.news.data[i].type);
		assert.strictEqual(rec.body, sources.news.data[i].body);
	});
	base.files[REFERENCES_PATH].records.forEach(function (rec, i) {
		assert.strictEqual(rec.name, sources.references.data[i].name);
		assert.strictEqual(rec.alt, sources.references.data[i].alt);
	});
});
test('diskteki gerçek içerik manifestleri taze üretimle byte-eşit (yalnız okuma)', function () {
	const cmp = compareFreshToDisk(base.files, DATA_DIR);
	assert.deepStrictEqual(cmp.mismatchDetails, []);
	assert.strictEqual(cmp.matches, true);
});
test('katalog manifestleri DEĞİŞMEDİ: 14 / 83 / 103 kayıt; content/ altında yalnız beklenen 5 manifest dosyası', function () {
	const c = function (f) { return JSON.parse(fs.readFileSync(path.join(DATA_DIR, 'content', f), 'utf8')).count; };
	assert.strictEqual(c('sectors.manifest.json'), 14);
	assert.strictEqual(c('qualifications.manifest.json'), 83);
	assert.strictEqual(c('fees.manifest.json'), 103);
	assert.deepStrictEqual(findUnexpectedContentFiles(DATA_DIR), []);
});

/* --- B) değiştirilmiş slug / tarih / tür --- */
expectErrors('haber slug\'ı değiştirilmiş -> ret (source_key tutarsız + taze türetimle eşit değil)', function (f) { f[NEWS_PATH].records[0].slug = 'baska-bir-slug'; }, 'tutarsız');
expectErrors('haber slug\'ı ve source_key\'i birlikte değiştirilmiş -> taze türetimle eşit değil', function (f) {
	f[NEWS_PATH].records[0].slug = 'baska-bir-slug';
	f[NEWS_PATH].records[0].source_key = 'news:baska-bir-slug';
}, 'yeniden türetilen');
expectErrors('haber slug biçimi geçersiz (büyük harf/çift tire) -> şema ret', function (f) {
	f[NEWS_PATH].records[1].slug = 'Kotu--Slug';
	f[NEWS_PATH].records[1].source_key = 'news:Kotu--Slug';
}, 'pattern');
expectErrors('haber tarihi takvimde olmayan gün (2026-02-30) -> ret', function (f) { f[NEWS_PATH].records[0].published_on = '2026-02-30'; }, 'takvimde');
expectErrors('haber tarihi biçimi bozuk (26-1-5) -> ret', function (f) { f[NEWS_PATH].records[0].published_on = '26-1-5'; }, 'pattern');
expectErrors('haber tarihi geçerli ama kaynaktan farklı -> taze türetimle eşit değil', function (f) { f[NEWS_PATH].records[0].published_on = '2030-01-01'; }, 'yeniden türetilen');
expectErrors('haber türü enum dışı (etkinlik) -> şema ret', function (f) { f[NEWS_PATH].records[0].news_type = 'etkinlik'; }, 'enum');
expectErrors('haber türü değiştirilmiş (haber -> duyuru) -> taze türetimle eşit değil', function (f) {
	const r = f[NEWS_PATH].records.find(function (x) { return 'haber' === x.news_type; });
	r.news_type = 'duyuru';
}, 'yeniden türetilen');
expectErrors('haber gövdesinde işaretleme (<script>) -> düz metin ret', function (f) { f[NEWS_PATH].records[0].body = 'Gövde <script>x</script>'; }, 'düz metin');
expectErrors('haber başlığı baş/son boşluklu -> düz metin ret', function (f) { f[NEWS_PATH].records[0].title = ' ' + f[NEWS_PATH].records[0].title; }, 'düz metin');
expectErrors('referans slug\'ı ad ile tutarsız -> ret (slug = slugify(name))', function (f) {
	f[REFERENCES_PATH].records[0].slug = 'baska';
	f[REFERENCES_PATH].records[0].source_key = 'reference:baska';
}, 'slugify');
expectErrors('referans logo yolu ".." içeriyor -> ret', function (f) { f[REFERENCES_PATH].records[0].logo_file = '../gizli.svg'; }, 'logo_file');
expectErrors('referans alt metni boş -> şema ret', function (f) { f[REFERENCES_PATH].records[0].alt = ''; }, 'minLength');

/* --- C) eksik/fazla kayıt, sayı uyuşmazlığı, tekrarlar --- */
expectErrors('eksik haber kaydı (5) -> sayı uyuşmazlığı', function (f) { f[NEWS_PATH].records.pop(); f[NEWS_PATH].count = 5; }, 'sayı uyuşmazlığı');
expectErrors('fazla haber kaydı (7) -> sayı uyuşmazlığı', function (f) {
	const extra = clone(f[NEWS_PATH].records[0]);
	extra.slug = 'fazladan-haber';
	extra.source_key = 'news:fazladan-haber';
	extra.source_index = 6;
	f[NEWS_PATH].records.push(extra);
	f[NEWS_PATH].count = 7;
}, 'sayı uyuşmazlığı');
expectErrors('eksik referans (11) -> sayı uyuşmazlığı', function (f) { f[REFERENCES_PATH].records.pop(); f[REFERENCES_PATH].count = 11; }, 'sayı uyuşmazlığı');
expectErrors('fazla referans (13) -> sayı uyuşmazlığı', function (f) {
	const extra = clone(f[REFERENCES_PATH].records[0]);
	extra.name = 'Fazladan Ref';
	extra.slug = 'fazladan-ref';
	extra.source_key = 'reference:fazladan-ref';
	extra.source_index = 12;
	f[REFERENCES_PATH].records.push(extra);
	f[REFERENCES_PATH].count = 13;
}, 'sayı uyuşmazlığı');
expectErrors('count alanı records uzunluğuyla uyuşmuyor (haber count=99) -> ret', function (f) { f[NEWS_PATH].count = 99; }, 'count');
expectErrors('count alanı records uzunluğuyla uyuşmuyor (referans count=3) -> ret', function (f) { f[REFERENCES_PATH].count = 3; }, 'count');
expectErrors('tekrar eden haber source_key -> ret', function (f) {
	f[NEWS_PATH].records[1].source_key = f[NEWS_PATH].records[0].source_key;
	f[NEWS_PATH].records[1].slug = f[NEWS_PATH].records[0].slug;
}, 'tekrar');
expectErrors('tekrar eden referans slug -> ret', function (f) {
	f[REFERENCES_PATH].records[2].slug = f[REFERENCES_PATH].records[0].slug;
	f[REFERENCES_PATH].records[2].source_key = f[REFERENCES_PATH].records[0].source_key;
	f[REFERENCES_PATH].records[2].name = f[REFERENCES_PATH].records[0].name;
}, 'tekrar');
expectErrors('source_index konumla uyuşmuyor -> ret', function (f) { f[NEWS_PATH].records[2].source_index = 9; }, 'source_index');
expectErrors('kayıt sırası değiştirilmiş (iki referans yer değiştirdi, source_index düzeltilmiş) -> taze türetimle eşit değil', function (f) {
	const a = f[REFERENCES_PATH].records;
	const tmp = a[0];
	a[0] = a[1];
	a[1] = tmp;
	a[0].source_index = 0;
	a[1].source_index = 1;
}, 'yeniden türetilen');

/* --- D) zarf ve provenance --- */
expectErrors('zarfta fazladan üst-seviye alan -> ret', function (f) { f[NEWS_PATH].counts = { total: 6 }; }, 'beklenmeyen üst-seviye');
expectErrors('zarfta notes eksik -> ret', function (f) { delete f[REFERENCES_PATH].notes; }, 'notes');
expectErrors('yanlış record_type -> ret', function (f) { f[NEWS_PATH].record_type = 'reference'; }, 'record_type');
expectErrors('yanlış source.file (haber zarfı references.js gösteriyor) -> ret', function (f) { f[NEWS_PATH].source.file = 'tanitim-site/assets/data/references.js'; }, 'source');
expectErrors('source.sha256 gerçek dosya özetiyle eşleşmiyor -> ret', function (f) { f[REFERENCES_PATH].source.sha256 = 'b'.repeat(64); }, 'source');
expectErrors('kayıt source\'u zarftan farklı (provenance drift) -> ret', function (f) { f[NEWS_PATH].records[3].source.sha256 = 'c'.repeat(64); }, 'source');
expectErrors('kayıtta fazladan alan (image) -> şema ret', function (f) { f[NEWS_PATH].records[0].image = 'assets/x.png'; }, 'beklenmeyen ek alan');
expectErrors('kayıtta fazladan alan (website_url) referansta -> şema ret', function (f) { f[REFERENCES_PATH].records[0].website_url = 'https://ornek.example'; }, 'beklenmeyen ek alan');
expectErrors('kayıttan zorunlu alan eksik (body) -> şema ret', function (f) { delete f[NEWS_PATH].records[0].body; }, 'zorunlu alan eksik');
expectErrors('fazladan dosya (content/locations.manifest.json) doğrulayıcıya verilirse -> ret', function (f) { f['content/locations.manifest.json'] = { schema_version: '2.0.0' }; }, 'beklenmeyen içerik manifest dosyası');
expectErrors('bir dosya tamamen eksik -> ret', function (f) { delete f[REFERENCES_PATH]; });

/* --- E) disk düzeyi: fazladan dosya, karışık set, byte-eşitlik --- */
test('fazladan content/*.manifest.json dosyası (geçici dizin) findUnexpectedContentFiles ile yakalanır; beklenen 5 dosya yakalanmaz', function () {
	const dir = tmpDir('extra');
	try {
		fs.mkdirSync(path.join(dir, 'content'));
		['sectors', 'qualifications', 'fees', 'news', 'references'].forEach(function (n) { fs.writeFileSync(path.join(dir, 'content', n + '.manifest.json'), '{}'); });
		assert.deepStrictEqual(findUnexpectedContentFiles(dir), []);
		fs.writeFileSync(path.join(dir, 'content', 'locations.manifest.json'), '{}');
		fs.writeFileSync(path.join(dir, 'content', 'notlar.txt'), 'x');
		assert.deepStrictEqual(findUnexpectedContentFiles(dir), ['content/locations.manifest.json']);
	} finally {
		rmTree(dir);
	}
});
test('karışık disk seti (haber taze, referans bayat) compareFreshToDisk tarafından reddedilir', function () {
	const dir = tmpDir('mixed');
	try {
		fs.mkdirSync(path.join(dir, 'content'));
		fs.writeFileSync(path.join(dir, NEWS_PATH), toDeterministicJson(base.files[NEWS_PATH]));
		const stale = clone(base.files[REFERENCES_PATH]);
		stale.records[0].alt = 'bayat alt';
		fs.writeFileSync(path.join(dir, REFERENCES_PATH), toDeterministicJson(stale));
		const cmp = compareFreshToDisk(base.files, dir);
		assert.strictEqual(cmp.matches, false);
		assert.ok(cmp.mismatchDetails.some(function (d) { return d.indexOf('references.manifest.json') !== -1; }));
		assert.ok(!cmp.mismatchDetails.some(function (d) { return d.indexOf('news.manifest.json') !== -1; }));
	} finally {
		rmTree(dir);
	}
});
test('disk dosyası eksik veya JSON bozuksa compareFreshToDisk reddeder', function () {
	const dir = tmpDir('missing');
	try {
		fs.mkdirSync(path.join(dir, 'content'));
		assert.strictEqual(compareFreshToDisk(base.files, dir).matches, false);
		fs.writeFileSync(path.join(dir, NEWS_PATH), '{bozuk');
		fs.writeFileSync(path.join(dir, REFERENCES_PATH), toDeterministicJson(base.files[REFERENCES_PATH]));
		const cmp = compareFreshToDisk(base.files, dir);
		assert.strictEqual(cmp.matches, false);
		assert.ok(cmp.mismatchDetails.some(function (d) { return d.indexOf('JSON parse') !== -1; }));
	} finally {
		rmTree(dir);
	}
});
test('byte-eşitlik: bir baytlık fark (sondaki boşluk) compareFreshToDisk tarafından yakalanır', function () {
	const dir = tmpDir('bytes');
	try {
		fs.mkdirSync(path.join(dir, 'content'));
		fs.writeFileSync(path.join(dir, NEWS_PATH), toDeterministicJson(base.files[NEWS_PATH]));
		fs.writeFileSync(path.join(dir, REFERENCES_PATH), toDeterministicJson(base.files[REFERENCES_PATH]) + ' ');
		assert.strictEqual(compareFreshToDisk(base.files, dir).matches, false);
	} finally {
		rmTree(dir);
	}
});

/* --- F) üretim reddi: geçersiz kaynak hiçbir şey yazdırmaz --- */
function mutatedSources(mutator) {
	const s = clone(sources);
	mutator(s);
	return s;
}
test('kaynakta tekrar eden haber slug\'ı -> computeContent hata verir, files=null', function () {
	const r = computeContent(mutatedSources(function (s) { s.news.data[1].slug = s.news.data[0].slug; }));
	assert.ok(r.errors.length > 0 && null === r.files);
});
test('kaynakta geçersiz haber tarihi / türü -> computeContent hata verir', function () {
	assert.ok(computeContent(mutatedSources(function (s) { s.news.data[0].date = '2022-13-01'; })).errors.length > 0);
	assert.ok(computeContent(mutatedSources(function (s) { s.news.data[0].type = 'etkinlik'; })).errors.length > 0);
});
test('kaynakta haber sayısı 6\'dan farklı (5) veya referans 12\'den farklı (13) -> computeContent hata verir', function () {
	assert.ok(computeContent(mutatedSources(function (s) { s.news.data.pop(); })).errors.some(function (e) { return e.indexOf('beklenen 6') !== -1; }));
	assert.ok(computeContent(mutatedSources(function (s) { s.references.data.push({ name: 'Ek Kurum', file: 'assets/images/references/atlas-endustri.svg', alt: 'Ek Kurum — temsili' }); })).errors.some(function (e) { return e.indexOf('beklenen 12') !== -1; }));
});
test('kaynakta aynı adlı iki referans (aynı slug) veya var olmayan logo dosyası -> computeContent hata verir', function () {
	assert.ok(computeContent(mutatedSources(function (s) { s.references.data[1].name = s.references.data[0].name; })).errors.length > 0);
	assert.ok(computeContent(mutatedSources(function (s) { s.references.data[0].file = 'assets/images/references/yok-boyle-bir-dosya.svg'; })).errors.length > 0);
});
test('kaynakta işaretleme içeren haber gövdesi -> computeContent hata verir', function () {
	assert.ok(computeContent(mutatedSources(function (s) { s.news.data[0].body = 'a <b>b</b>'; })).errors.length > 0);
});
test('runBuild: geçersiz sonuçta yazıcı ASLA çağrılmaz (spy = 0 çağrı)', function () {
	let calls = 0;
	const out = runBuild(
		function () { return { errors: ['sahte hata'], files: null }; },
		function () { calls++; }
	);
	assert.strictEqual(calls, 0);
	assert.strictEqual(out.wrote, false);
});
test('runBuild: geçerli sonuçta yazıcı TAM BİR kez ve iki dosya ile çağrılır', function () {
	let calls = 0;
	let seen = null;
	const out = runBuild(
		function () { return base; },
		function (dir, files) { calls++; seen = Object.keys(files); }
	);
	assert.strictEqual(calls, 1);
	assert.deepStrictEqual(seen, [NEWS_PATH, REFERENCES_PATH]);
	assert.strictEqual(out.wrote, true);
});

/* --- G) yazma: ya hep ya hiç --- */
test('writeContentAtomic: geçici dizine iki dosyayı yazar; iki ardışık yazım byte-eşit; artık .tmp dosyası kalmaz', function () {
	const dir = tmpDir('write');
	try {
		writeContentAtomic(dir, base.files);
		const a1 = fs.readFileSync(path.join(dir, NEWS_PATH), 'utf8') + fs.readFileSync(path.join(dir, REFERENCES_PATH), 'utf8');
		writeContentAtomic(dir, base.files);
		const a2 = fs.readFileSync(path.join(dir, NEWS_PATH), 'utf8') + fs.readFileSync(path.join(dir, REFERENCES_PATH), 'utf8');
		assert.strictEqual(a1, a2);
		assert.strictEqual(a1, toDeterministicJson(base.files[NEWS_PATH]) + toDeterministicJson(base.files[REFERENCES_PATH]));
		assert.deepStrictEqual(fs.readdirSync(path.join(dir, 'content')).filter(function (n) { return n.indexOf('.tmp-') !== -1; }), []);
	} finally {
		rmTree(dir);
	}
});
function failingRenameFs(failOnNth) {
	let renames = 0;
	return Object.assign({}, fs, {
		renameSync: function (from, to) {
			renames++;
			if (renames === failOnNth) {
				throw new Error('sahte rename hatası');
			}
			return fs.renameSync(from, to);
		},
	});
}
test('writeContentAtomic: ikinci yeniden adlandırma başarısız -> birinci dosya ESKİ içeriğine döner, geçici dosya kalmaz, hata yeniden fırlatılır', function () {
	const dir = tmpDir('rollback-old');
	try {
		fs.mkdirSync(path.join(dir, 'content'));
		fs.writeFileSync(path.join(dir, NEWS_PATH), 'ESKI-HABER');
		fs.writeFileSync(path.join(dir, REFERENCES_PATH), 'ESKI-REFERANS');
		assert.throws(function () { writeContentAtomic(dir, base.files, failingRenameFs(2)); }, /sahte rename hatası/);
		assert.strictEqual(fs.readFileSync(path.join(dir, NEWS_PATH), 'utf8'), 'ESKI-HABER');
		assert.strictEqual(fs.readFileSync(path.join(dir, REFERENCES_PATH), 'utf8'), 'ESKI-REFERANS');
		assert.deepStrictEqual(fs.readdirSync(path.join(dir, 'content')).filter(function (n) { return n.indexOf('.tmp-') !== -1; }), []);
	} finally {
		rmTree(dir);
	}
});
test('writeContentAtomic: ilk yazımda ikinci rename başarısız -> ilk dosya KALDIRILIR (yarım set yok)', function () {
	const dir = tmpDir('rollback-new');
	try {
		assert.throws(function () { writeContentAtomic(dir, base.files, failingRenameFs(2)); }, /sahte rename hatası/);
		assert.strictEqual(fs.existsSync(path.join(dir, NEWS_PATH)), false);
		assert.strictEqual(fs.existsSync(path.join(dir, REFERENCES_PATH)), false);
		assert.deepStrictEqual(fs.readdirSync(path.join(dir, 'content')), []);
	} finally {
		rmTree(dir);
	}
});
test('writeContentAtomic: birinci yeniden adlandırma başarısız -> hiçbir dosya değişmez', function () {
	const dir = tmpDir('rollback-first');
	try {
		fs.mkdirSync(path.join(dir, 'content'));
		fs.writeFileSync(path.join(dir, NEWS_PATH), 'ESKI-HABER');
		assert.throws(function () { writeContentAtomic(dir, base.files, failingRenameFs(1)); }, /sahte rename hatası/);
		assert.strictEqual(fs.readFileSync(path.join(dir, NEWS_PATH), 'utf8'), 'ESKI-HABER');
		assert.strictEqual(fs.existsSync(path.join(dir, REFERENCES_PATH)), false);
	} finally {
		rmTree(dir);
	}
});

if (failures > 0) {
	process.stderr.write('\n' + failures + '/' + total + ' içerik manifest testi BAŞARISIZ.\n');
	process.exit(1);
}
process.stdout.write('\nTüm ' + total + ' içerik manifest testi geçti.\n');
