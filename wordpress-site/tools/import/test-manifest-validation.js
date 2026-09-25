'use strict';
/**
 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı — negatif/regresyon testleri:
 * paylaşılan doğrulayıcının (lib/validate-manifest-set.js) GERÇEKTEN
 * hata ürettiğini bellek-içi fixture'larla kanıtlar (committed manifest
 * dosyalarını bozup geri almak yerine). Hiçbir dosya değiştirilmez.
 *
 * Run: node wordpress-site/tools/import/test-manifest-validation.js
 */

const assert = require('assert');
const path = require('path');

const fs = require('fs');
const { computeAll, SCHEMA_DIR, runBuild, writeAllAtomic } = require('./build-manifest');
const { validateManifestSet } = require('./lib/validate-manifest-set');
const { assertKnownKeywords } = require('./lib/mini-schema');
const { validateFinalSummary, findUnexpectedManifestFiles, compareFreshToDisk } = require('./verify-manifest');
const { DATA_DIR } = require('./build-manifest');

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

// Gerçek, geçerli temel durum — deep-clone edip her testte bozacağız.
const base = computeAll();
assert.strictEqual(base.errors.length, 0, 'ön koşul: gerçek kaynaklardan hatasız manifest üretilebilmeli');

function cloneFiles() {
	return JSON.parse(JSON.stringify(base.files));
}

test('temel durum (bozulmamış) doğrulayıcıdan hatasız geçer', function () {
	const result = validateManifestSet({ sources: base.sources, files: cloneFiles() }, SCHEMA_DIR);
	assert.strictEqual(result.errors.length, 0, result.errors.join(' | '));
});

test('yanlış zarf record_type reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].record_type = 'qualification';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});

test('count, gerçek dizi uzunluğuyla uyuşmadığında reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].count = 999;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /count/.test(e); }));
});

test('beklenmeyen ek alan (additionalProperties: false) reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].records[0].unexpected_field = 'x';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /beklenmeyen ek alan/.test(e); }));
});

test('yanlış tipte alan (level bir string) reddedilir', function () {
	const files = cloneFiles();
	files['content/qualifications.manifest.json'].records[0].level = 'üç';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /type/.test(e); }));
});

test('kod içine gömülü seviye kayıt alanıyla uyuşmadığında reddedilir (çapraz-alan)', function () {
	const files = cloneFiles();
	files['content/qualifications.manifest.json'].records[0].level = 99;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /gömülü seviye/.test(e); }));
});

test('bağlı ücretin sektörü yeterlilikle uyuşmadığında reddedilir (çapraz-alan)', function () {
	const files = cloneFiles();
	const linkedFee = files['content/fees.manifest.json'].records.find(function (f) { return null !== f.qualification_source_key; });
	assert.ok(linkedFee, 'ön koşul: en az bir bağlı ücret olmalı');
	linkedFee.sector_slug = linkedFee.sector_slug === 'makine' ? 'metalurji' : 'makine';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /sektör.*uyuşmuyor/.test(e); }));
});

test('min_amount_kurus yanlış hesaplandığında reddedilir (çapraz-alan)', function () {
	const files = cloneFiles();
	files['content/fees.manifest.json'].records[0].min_amount_kurus = 1;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /min_amount_kurus/.test(e); }));
});

test('kaynakla alan-alan uyuşmazlığı (name değiştirilmiş) reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].records[0].name = 'Uydurma İsim';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /name kaynakla uyuşmuyor/.test(e); }));
});

test('desteklenmeyen şema anahtar kelimesi (ör. $ref) assertKnownKeywords tarafından reddedilir', function () {
	assert.throws(function () {
		assertKnownKeywords({ type: 'object', properties: { x: { $ref: '#/other' } } }, 'test-schema');
	}, /bilinmeyen anahtar kelime/);
});

test('kırık/çözümlenemeyen $ref içeren şema assertKnownKeywords tarafından reddedilir (çözmeye çalışmaz, doğrudan reddeder)', function () {
	assert.throws(function () {
		assertKnownKeywords({ type: 'array', items: { $ref: 'does-not-exist.schema.json' } }, 'test-schema');
	});
});

// --- §4 (Faz 6A Son Kabul Düzeltmesi): dosya-türüne özel zarf sözleşmesi negatif testleri ---

test('zarf: fazladan üst-seviye alan reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].unexpected_top_level = 'x';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /beklenmeyen üst-seviye alan/.test(e); }));
});

test('zarf: aynı dosyada hem "records" hem "rows" bulunması reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].rows = [];
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /yalnız "records" beklenir/.test(e); }));
});

test('zarf: yanlış liste anahtarı (mapping dosyasında "rows" yerine "records") reddedilir', function () {
	const files = cloneFiles();
	files['mapping/mapping.manifest.json'].records = files['mapping/mapping.manifest.json'].rows;
	delete files['mapping/mapping.manifest.json'].rows;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /zorunlu üst-seviye alan eksik \("rows"\)/.test(e); }));
});

test('zarf: eksik "source" reddedilir', function () {
	const files = cloneFiles();
	delete files['content/fees.manifest.json'].source;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /zorunlu üst-seviye alan eksik \("source"\)/.test(e); }));
});

test('zarf: "source" içinde ek alan reddedilir', function () {
	const files = cloneFiles();
	files['content/fees.manifest.json'].source.extra = 'x';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /"source" içinde beklenmeyen ek alan/.test(e); }));
});

test('zarf: ücret dışı dosyada "counts" bulunması reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].counts = { total: 14 };
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /bu dosya türünde "counts" beklenmiyor/.test(e); }));
});

test('zarf: "counts" içinde eksik sayaç reddedilir', function () {
	const files = cloneFiles();
	delete files['content/fees.manifest.json'].counts.linkedCount;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /"counts" içinde zorunlu sayaç eksik/.test(e); }));
});

test('zarf: "counts" içinde fazladan alan reddedilir', function () {
	const files = cloneFiles();
	files['content/fees.manifest.json'].counts.extra = 1;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /"counts" içinde beklenmeyen ek alan/.test(e); }));
});

test('zarf: "counts" içinde yanlış tip alan reddedilir', function () {
	const files = cloneFiles();
	files['content/fees.manifest.json'].counts.linkedCount = 'seksen dört';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /"counts\.linkedCount" negatif olmayan bir tam sayı olmalı/.test(e); }));
});

// --- §5.1 (Faz 6A Son Kabul Düzeltmesi): gerçek writer-spy testi ---
// Önceki "12. test" yalnız zaten-geçerli `base` sonucuna bakıyordu — hiçbir
// gerçek yazma yolu tetiklenmiyor, hiçbir writer çağrılmıyordu. Aşağıdaki
// testler runBuild()'e SAHTE bir computeAllFn + gerçek bir spy writerFn
// enjekte ederek "geçersiz sonuç -> writer SIFIR kez çağrılır" ve "geçerli
// sonuç -> writer TAM BİR kez, doğru fileSet ile çağrılır" iddialarını
// gerçekten kanıtlar. Gerçek diske hiçbir zaman dokunulmaz.

function makeWriterSpy() {
	const calls = [];
	function writerSpy(dataDir, fileSet) {
		calls.push({ dataDir: dataDir, fileSet: fileSet });
	}
	writerSpy.calls = calls;
	return writerSpy;
}

test('writer-spy: geçersiz computeAll sonucu (errors dolu) writer\'ı SIFIR kez çağırır', function () {
	const spy = makeWriterSpy();
	function fakeComputeAllInvalid() {
		return { errors: ['sahte hata: kasıtlı geçersiz sonuç'], files: null, sources: null, counts: null, unmatchedCount: null };
	}
	const outcome = runBuild(fakeComputeAllInvalid, spy);
	assert.strictEqual(outcome.wrote, false);
	assert.strictEqual(spy.calls.length, 0, 'writer hiç çağrılmamalıydı, ama ' + spy.calls.length + ' kez çağrıldı');
});

test('writer-spy: geçerli computeAll sonucu writer\'ı TAM BİR kez, gerçek fileSet ile çağırır', function () {
	const spy = makeWriterSpy();
	function fakeComputeAllValid() {
		return base;
	}
	const outcome = runBuild(fakeComputeAllValid, spy);
	assert.strictEqual(outcome.wrote, true);
	assert.strictEqual(spy.calls.length, 1, 'writer tam bir kez çağrılmalıydı, ama ' + spy.calls.length + ' kez çağrıldı');
	assert.strictEqual(spy.calls[0].fileSet, base.files);
});

// --- §5.2 (Faz 6A Son Kabul Düzeltmesi): dosya-başına atomik / set-düzeyi best-effort yazma semantiği ---

test('writeAllAtomic: rename ortasında hata -> önceki dosyalar YENİ içerikle kalır (karışık set, dürüstçe belgelenmiş davranış), kalan temp dosyalar temizlenir', function () {
	const writes = [];
	const renames = [];
	const unlinks = [];
	let renameCallCount = 0;

	const fakeFs = {
		mkdirSync: function () {},
		writeFileSync: function (tmpPath, content) {
			writes.push(tmpPath);
		},
		renameSync: function (tmpPath, absPath) {
			renameCallCount++;
			if (2 === renameCallCount) {
				throw new Error('sahte disk hatası (2. rename)');
			}
			renames.push({ tmpPath: tmpPath, absPath: absPath });
		},
		existsSync: function (p) {
			return writes.indexOf(p) !== -1 && renames.every(function (r) { return r.tmpPath !== p; });
		},
		unlinkSync: function (p) {
			unlinks.push(p);
		},
	};

	const fakeFileSet = {
		'a.json': { x: 1 },
		'b.json': { x: 2 },
		'c.json': { x: 3 },
	};

	assert.throws(function () {
		writeAllAtomic('/fake/data/dir', fakeFileSet, fakeFs);
	}, /sahte disk hatası/);

	// 3 dosya için 3 write denendi, ama yalnız İLK rename başarılı oldu —
	// bu tam olarak "karışık set" senaryosu: 1 dosya YENİ içerikle canlı,
	// geri kalan 2'si için rename hiç denenmedi/başarısız oldu.
	assert.strictEqual(writes.length, 3, 'üç dosyanın da temp yazması denenmeliydi');
	assert.strictEqual(renames.length, 1, 'yalnız ilk rename başarılı olmalıydı (karışık set)');

	// §7 (Faz 6A Doğrulayıcı Bütünlük Kapanışı) — yalnız ">= 1 temp
	// temizlendi" değil, TEMİZLENMESİ GEREKEN TAM KÜME (rename OLMAMIŞ
	// temp dosyaların hepsi, ne eksik ne fazla) eşitlik ile kanıtlanır.
	const renamedTmpPaths = renames.map(function (r) { return r.tmpPath; });
	const expectedCleanup = writes.filter(function (w) { return renamedTmpPaths.indexOf(w) === -1; });
	assert.deepStrictEqual(unlinks.slice().sort(), expectedCleanup.slice().sort(), 'temizlenen temp dosya kümesi, rename OLMAMIŞ tüm temp dosyalarla TAM eşleşmeli (ne eksik ne fazla)');
});

test('paylaşılan doğrulayıcı bariz count tutarsızlığını reddeder (yalnız bu — "verifier karışık set testi" İDDİASI DEĞİL, bkz. aşağıdaki gerçek disk fixture testi)', function () {
	const files = cloneFiles();
	files['content/fees.manifest.json'].count = files['content/fees.manifest.json'].records.length - 1;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0, 'count tutarsızlığı reddedilmeliydi');
});

// §8 (Faz 6A Tam Kapsam ve Sayaç Kapanışı) — GERÇEK karışık-set verifier
// testi: geçici bir dizine 4 GÜNCEL (taze hesaplanmış) + 1 ESKİ (önceki
// bir sürümden kalma, yapısal olarak geçerli ama artık güncel olmayan)
// dosya yazılır; `compareFreshToDisk()` (verify-manifest.js'in gerçek
// disk-karşılaştırma kodu, main()'den ayrıştırıldı) bunun GERÇEKTEN
// reddedildiğini kanıtlar. Hiçbir gerçek proje dosyası değişmez/etkilenmez;
// `finally` ile geçici dizin her koşulda temizlenir.
test('§8: gerçek karışık-set (4 güncel + 1 eski dosya) compareFreshToDisk() tarafından GERÇEKTEN reddedilir', function () {
	const os = require('os');
	const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'mb-mixed-set-'));
	try {
		const contentDir = path.join(tmpDir, 'content');
		const mappingDir = path.join(tmpDir, 'mapping');
		fs.mkdirSync(contentDir, { recursive: true });
		fs.mkdirSync(mappingDir, { recursive: true });

		const toDeterministicJson = require('./lib/hash').toDeterministicJson;
		Object.keys(base.files).forEach(function (relPath) {
			fs.writeFileSync(path.join(tmpDir, relPath), toDeterministicJson(base.files[relPath]), { encoding: 'utf8' });
		});

		// Bir dosyayı bilerek "eski" (yapısal olarak geçerli ama artık
		// güncel olmayan içerik) bırak — diğer 4 dosya güncel kalıyor.
		const staleSectors = JSON.parse(JSON.stringify(base.files['content/sectors.manifest.json']));
		staleSectors.records[0].name = 'Eski Sürümden Kalma İsim';
		fs.writeFileSync(path.join(tmpDir, 'content/sectors.manifest.json'), toDeterministicJson(staleSectors), { encoding: 'utf8' });

		const result = compareFreshToDisk(base.files, tmpDir);
		assert.strictEqual(result.matches, false, 'karışık set (4 güncel + 1 eski) reddedilmeliydi');
		assert.ok(result.mismatchDetails.some(function (d) { return /sectors\.manifest\.json/.test(d); }), 'hata mesajı hangi dosyanın eski olduğunu belirtmeli');
	} finally {
		fs.rmSync(tmpDir, { recursive: true, force: true });
	}
});
test('§8b: gerçek TAM güncel set (5/5) compareFreshToDisk() tarafından kabul edilir (regresyon/pozitif kontrol)', function () {
	const os = require('os');
	const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'mb-fresh-set-'));
	try {
		const toDeterministicJson = require('./lib/hash').toDeterministicJson;
		fs.mkdirSync(path.join(tmpDir, 'content'), { recursive: true });
		fs.mkdirSync(path.join(tmpDir, 'mapping'), { recursive: true });
		Object.keys(base.files).forEach(function (relPath) {
			fs.writeFileSync(path.join(tmpDir, relPath), toDeterministicJson(base.files[relPath]), { encoding: 'utf8' });
		});
		const result = compareFreshToDisk(base.files, tmpDir);
		assert.strictEqual(result.matches, true, result.mismatchDetails.join(' | '));
	} finally {
		fs.rmSync(tmpDir, { recursive: true, force: true });
	}
});

// --- §6 (Faz 6A Doğrulayıcı Bütünlük Kapanışı): zorunlu negatif/regresyon matrisi ---
// Her test GERÇEKTEN ilgili alanı bozar ve errors.length > 0 kanıtlar —
// yalnız zaten-geçerli `base`'i tekrar kontrol eden test SAYILMAZ. Bu 18
// test, bağımsız incelemenin altı somut karşı-örneğinin (aşağıda 1/4/6/8
// numaralı testler olarak yeniden üretildi) ve ek 12 ilişkinin artık
// gerçekten reddedildiğini kanıtlar.

function expectRejects(label, mutateFn) {
	test(label, function () {
		const files = cloneFiles();
		mutateFn(files);
		const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
		assert.ok(result.errors.length > 0, 'beklenen: en az 1 hata, gerçek: 0');
	});
}

// 1. sektör slug kaynak uyuşmazlığı (bağımsız incelemenin karşı-örneği #1)
expectRejects('§6.1: sektör slug kaynak uyuşmazlığı reddedilir', function (files) {
	files['content/sectors.manifest.json'].records[0].slug = 'baska-gecerli-slug';
});

// 2. sektör source_key !== sector:slug
expectRejects('§6.2: sektör source_key formül uyuşmazlığı reddedilir', function (files) {
	files['content/sectors.manifest.json'].records[0].source_key = 'sector:baska-slug';
});

// 3. kayıt içi kaynak SHA/dosya uyuşmazlığı (karşı-örnek #4)
expectRejects('§6.3: kayıt içi source.sha256 uyuşmazlığı reddedilir', function (files) {
	files['content/sectors.manifest.json'].records[0].source.sha256 = 'b'.repeat(64);
});
expectRejects('§6.3b: kayıt içi source.file uyuşmazlığı reddedilir', function (files) {
	files['content/qualifications.manifest.json'].records[0].source.file = 'baska/dosya.js';
});

// 4. ücret pricing_type kaynak uyuşmazlığı (karşı-örnek #2)
expectRejects('§6.4: ücret pricing_type kaynak uyuşmazlığı reddedilir', function (files) {
	const rec = files['content/fees.manifest.json'].records.find(function (r) { return 'single' === r.pricing_type; });
	rec.pricing_type = 'unit';
});

// 5. ücret source_key türetim uyuşmazlığı
expectRejects('§6.5: ücret source_key formül uyuşmazlığı reddedilir', function (files) {
	files['content/fees.manifest.json'].records[0].source_key = 'fee:makine:9:sahte-slug';
});

// 6. fiyat seçeneği sort_order bozulması (karşı-örnek #3)
expectRejects('§6.6: fiyat seçeneği sort_order bozulması reddedilir', function (files) {
	files['content/fees.manifest.json'].records[0].price_options[0].sort_order = 99;
});

// 7. fiyat seçeneklerinin yeniden sıralanması (içerik aynı kalsa da sıra değişince reddedilmeli)
expectRejects('§6.7: fiyat seçeneklerinin yeniden sıralanması reddedilir', function (files) {
	const rec = files['content/fees.manifest.json'].records.find(function (r) { return r.price_options.length > 1; });
	assert.ok(rec, 'ön koşul: birden çok seçenekli en az bir ücret olmalı');
	const opts = rec.price_options;
	const tmp = opts[0];
	opts[0] = Object.assign({}, opts[1], { sort_order: 0 });
	opts[1] = Object.assign({}, tmp, { sort_order: 1 });
});

// 8. boş kodlu ücrette dolu qualification_source_key (karşı-örnek sınıfı — yeni)
expectRejects('§6.8: boş qualification_code ile dolu qualification_source_key birlikteliği reddedilir', function (files) {
	const rec = files['content/fees.manifest.json'].records.find(function (r) { return '' === r.qualification_code; });
	assert.ok(rec, 'ön koşul: kodsuz en az bir ücret olmalı');
	rec.qualification_source_key = 'qualification:10UY0002-3/03';
});

// 9. yeterlilik source_key bozulması
expectRejects('§6.9: yeterlilik source_key formül uyuşmazlığı reddedilir', function (files) {
	files['content/qualifications.manifest.json'].records[0].source_key = 'qualification:99UY9999-9/99';
});

// 10. yeterlilik tarihsel-format bayrağı bozulması
expectRejects('§6.10: matches_legacy_revision_required_format bayrağı bozulması reddedilir', function (files) {
	const rec = files['content/qualifications.manifest.json'].records[0];
	rec.matches_legacy_revision_required_format = !rec.matches_legacy_revision_required_format;
});

// 11. mapping natural_key bozulması (karşı-örnek #5)
expectRejects('§6.11: mapping natural_key bozulması reddedilir', function (files) {
	files['mapping/mapping.manifest.json'].rows[0].natural_key = 'sahte-doğal-anahtar';
});

// 12. mapping eksik/fazla/yeniden sıralanmış satır
expectRejects('§6.12a: mapping eksik satır reddedilir', function (files) {
	files['mapping/mapping.manifest.json'].rows.pop();
	files['mapping/mapping.manifest.json'].count -= 1;
});
expectRejects('§6.12b: mapping fazla (yinelenen) satır reddedilir', function (files) {
	const rows = files['mapping/mapping.manifest.json'].rows;
	rows.push(JSON.parse(JSON.stringify(rows[0])));
	files['mapping/mapping.manifest.json'].count += 1;
});
expectRejects('§6.12c: mapping yeniden sıralanmış satırlar reddedilir', function (files) {
	const rows = files['mapping/mapping.manifest.json'].rows;
	const tmp = rows[0];
	rows[0] = rows[1];
	rows[1] = tmp;
});

// 13. unmatched profession_name bozulması (karşı-örnek #6)
expectRejects('§6.13: unmatched profession_name bozulması reddedilir', function (files) {
	files['mapping/unmatched-fees.manifest.json'].records[0].profession_name = 'Sahte Meslek Adı';
});

// 14. unmatched eksik/fazla/bağlı-ücret satırı
expectRejects('§6.14a: unmatched eksik satır reddedilir', function (files) {
	files['mapping/unmatched-fees.manifest.json'].records.pop();
	files['mapping/unmatched-fees.manifest.json'].count -= 1;
});
expectRejects('§6.14b: unmatched\'e BAĞLI bir ücretin satırı olarak eklenmesi reddedilir', function (files) {
	const linkedFee = files['content/fees.manifest.json'].records.find(function (r) { return null !== r.qualification_source_key; });
	assert.ok(linkedFee, 'ön koşul: en az bir bağlı ücret olmalı');
	files['mapping/unmatched-fees.manifest.json'].records.push({
		source_index: linkedFee.source_index, source_key: linkedFee.source_key, profession_name: linkedFee.profession_name,
		level: linkedFee.level, sector_slug: linkedFee.sector_slug, note: 'sahte satır',
	});
	files['mapping/unmatched-fees.manifest.json'].count += 1;
});

// 15/16. eksik / fazladan manifest dosya anahtarı
test('§6.15: eksik manifest dosya anahtarı reddedilir (TypeError DEĞİL, normal hata)', function () {
	const files = cloneFiles();
	delete files['mapping/unmatched-fees.manifest.json'];
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
	assert.ok(result.errors.some(function (e) { return /zorunlu manifest dosya anahtarı eksik/.test(e); }));
});
test('§6.16: fazladan manifest dosya anahtarı reddedilir', function () {
	const files = cloneFiles();
	files['content/extra.manifest.json'] = { schema_version: '2.0.0', record_type: 'sector', count: 0, records: [] };
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /beklenmeyen manifest dosya anahtarı/.test(e); }));
});

// 17. yanlış biçimli source-key aileleri (şema pattern sıkılaştırması)
test('§6.17: yeterlilik source_key artık yalnız gerçek MYK kod biçimini kabul ediyor (gevşek ".+" deseni değil)', function () {
	const files = cloneFiles();
	files['content/qualifications.manifest.json'].records[0].source_key = 'qualification:not-a-real-code';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});
test('§6.17b: ücret qualification_source_key artık yalnız gerçek MYK kod biçimini kabul ediyor', function () {
	const files = cloneFiles();
	const rec = files['content/fees.manifest.json'].records.find(function (r) { return null !== r.qualification_source_key; });
	assert.ok(rec);
	rec.qualification_source_key = 'qualification:not-a-real-code';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});

// 18. bozuk nesne/listede doğrulayıcının KONTROLLÜ hata dönmesi (throw ETMEMESİ)
test('§6.18a: files bir dizi olduğunda throw etmeden hata listesi döner', function () {
	assert.doesNotThrow(function () {
		const result = validateManifestSet({ sources: base.sources, files: [] }, SCHEMA_DIR);
		assert.ok(result.errors.length > 0);
	});
});
test('§6.18b: bir manifest değeri null olduğunda throw etmeden hata listesi döner', function () {
	assert.doesNotThrow(function () {
		const files = cloneFiles();
		files['content/sectors.manifest.json'] = null;
		const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
		assert.ok(result.errors.length > 0);
	});
});
test('§6.18c: sources hiç verilmediğinde (undefined) throw etmeden ÇALIŞIR VE gerçekten hata döner (yalnız doesNotThrow değil)', function () {
	assert.doesNotThrow(function () {
		const result = validateManifestSet({ sources: undefined, files: cloneFiles() }, SCHEMA_DIR);
		assert.ok(result.errors.length > 0, 'sources olmadan da açık bir "sources bir obje olmalı" hatası dönmeli');
	});
});

// --- §9 (Faz 6A Tam Kapsam ve Sayaç Kapanışı): zorunlu yeni regresyon testleri ---

test('§9.1: son ücret + bağımlı mapping/unmatched satırları + doğru güncellenmiş sayaçlarla BİRLİKTE tutarlı biçimde silinse bile reddedilir (bağımsız incelemenin tam karşı-örneği)', function () {
	const files = cloneFiles();
	const feeFile = files['content/fees.manifest.json'];
	const removed = feeFile.records.pop();
	feeFile.count = feeFile.records.length;
	feeFile.counts.total = feeFile.records.length;
	if (removed.qualification_source_key) {
		feeFile.counts.linkedCount--;
		feeFile.counts.feesWithCode--;
	} else {
		feeFile.counts.feesWithoutCode--;
	}
	if ('single' === removed.pricing_type) {
		feeFile.counts.pricingSingle--;
	} else {
		feeFile.counts.pricingMulti--;
		feeFile.counts.multiOptionsTotal -= removed.price_options.length;
	}
	feeFile.counts.priceOptionsTotal -= removed.price_options.length;

	const mapping = files['mapping/mapping.manifest.json'];
	mapping.rows = mapping.rows.filter(function (r) { return !('fee' === r.type && r.source_index === removed.source_index); });
	mapping.count = mapping.rows.length;

	const unmatched = files['mapping/unmatched-fees.manifest.json'];
	unmatched.records = unmatched.records.filter(function (r) { return r.source_index !== removed.source_index; });
	unmatched.count = unmatched.records.length;

	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0, 'iç-tutarlı ama kaynak kardinalitesini bozan silme reddedilmeliydi');
});

test('§9.2: son sektör kaydının (yalnız o kaydın) silinmesi reddedilir', function () {
	const files = cloneFiles();
	const sf = files['content/sectors.manifest.json'];
	sf.records.pop();
	sf.count = sf.records.length;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
	assert.ok(result.errors.some(function (e) { return /kaynak satır sayısıyla/.test(e) || /doğrulanmış sabit sayıyla/.test(e); }));
});

test('§9.3: son yeterlilik kaydının (yalnız o kaydın) silinmesi reddedilir', function () {
	const files = cloneFiles();
	const qf = files['content/qualifications.manifest.json'];
	qf.records.pop();
	qf.count = qf.records.length;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});

test('§9.4: ortadaki bir sektör kaydının eksiltilmesi (tam-kapsam bozulması) reddedilir', function () {
	const files = cloneFiles();
	const sf = files['content/sectors.manifest.json'];
	sf.records.splice(5, 1); // ortadan bir kayıt çıkar — kalan source_index'ler artık 0..12 değil, boşluklu
	sf.count = sf.records.length;
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
	assert.ok(result.errors.some(function (e) { return /tam kapsamıyor/.test(e); }));
});

// §9.5 — sekiz ücret sayacının HER BİRİ ayrı ayrı bozulur, mesajın ilgili sayaç adını içerdiği de doğrulanır.
['priceOptionsTotal', 'pricingSingle', 'pricingMulti', 'multiOptionsTotal', 'feesWithCode', 'feesWithoutCode', 'linkedCount', 'total'].forEach(function (counterKey) {
	test('§9.5: fees.counts.' + counterKey + ' bozulduğunda reddedilir ve mesaj sayaç adını içerir', function () {
		const files = cloneFiles();
		files['content/fees.manifest.json'].counts[counterKey] += 1000;
		const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
		assert.ok(result.errors.length > 0);
		assert.ok(result.errors.some(function (e) { return e.indexOf('counts.' + counterKey) !== -1 || e.indexOf(counterKey) !== -1; }), 'hata mesajı "' + counterKey + '" adını içermeliydi');
	});
});

test('§9.6a: sources.sectors.data null olduğunda throw etmez, reddedilir', function () {
	assert.doesNotThrow(function () {
		const files = cloneFiles();
		const badSources = JSON.parse(JSON.stringify(base.sources));
		badSources.sectors.data = null;
		const result = validateManifestSet({ sources: badSources, files: files }, SCHEMA_DIR);
		assert.ok(result.errors.length > 0);
	});
});
test('§9.6b: sources.qualifications dizi (obje değil) olduğunda throw etmez, reddedilir', function () {
	assert.doesNotThrow(function () {
		const files = cloneFiles();
		const badSources = JSON.parse(JSON.stringify(base.sources));
		badSources.qualifications = [];
		const result = validateManifestSet({ sources: badSources, files: files }, SCHEMA_DIR);
		assert.ok(result.errors.length > 0);
	});
});
test('§9.7a: sources.fees.repoRelativePath eksik olduğunda reddedilir', function () {
	const files = cloneFiles();
	const badSources = JSON.parse(JSON.stringify(base.sources));
	delete badSources.fees.repoRelativePath;
	const result = validateManifestSet({ sources: badSources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});
test('§9.7b: sources.sectors.sha256 geçersiz biçimde (kısa) olduğunda reddedilir', function () {
	const files = cloneFiles();
	const badSources = JSON.parse(JSON.stringify(base.sources));
	badSources.sectors.sha256 = 'kisa';
	const result = validateManifestSet({ sources: badSources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});

test('§9.8: zarf source.sha256 gerçek çıkarımla uyuşmuyorsa, KAYIT DÜZEYİNDE hiçbir bozukluk olmasa dahi reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].source.sha256 = 'c'.repeat(64);
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
	assert.ok(result.errors.some(function (e) { return /zarf source\.sha256/.test(e); }));
});

test('§9.9a: sektör notes keyfî eklenmesi reddedilir', function () {
	const files = cloneFiles();
	files['content/sectors.manifest.json'].notes = ['uydurma not'];
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /sectors\.notes/.test(e); }));
});
test('§9.9b: yeterlilik notes keyfî silinmesi reddedilir (gerçek gap notu varken boş dizi verilirse)', function () {
	const files = cloneFiles();
	const qf = files['content/qualifications.manifest.json'];
	assert.ok(qf.notes.length > 0, 'ön koşul: gerçek verideki 20 revizyonsuz kod nedeniyle bir not olmalı');
	qf.notes = [];
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.some(function (e) { return /qualifications\.notes/.test(e); }));
});

test('§9.10a: son parçası tireyle BAŞLAYAN fee source-key şema seviyesinde reddedilir', function () {
	const files = cloneFiles();
	const rec = files['content/fees.manifest.json'].records[0];
	rec.source_key = 'fee:' + rec.sector_slug + ':' + rec.level + ':-baslangic-tire';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});
test('§9.10b: son parçası tireyle BİTEN fee source-key şema seviyesinde reddedilir', function () {
	const files = cloneFiles();
	const rec = files['content/fees.manifest.json'].records[0];
	rec.source_key = 'fee:' + rec.sector_slug + ':' + rec.level + ':bitis-tire-';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});
test('§9.10c: ÇİFT tire içeren fee source-key şema seviyesinde reddedilir', function () {
	const files = cloneFiles();
	const rec = files['content/fees.manifest.json'].records[0];
	rec.source_key = 'fee:' + rec.sector_slug + ':' + rec.level + ':cift--tire';
	const result = validateManifestSet({ sources: base.sources, files: files }, SCHEMA_DIR);
	assert.ok(result.errors.length > 0);
});

// --- §9 (Faz 6A Son Kabul Düzeltmesi): validation-summary final invariant testleri ---
// Her test GERÇEK son summary şeklini (schema_version/record_type/
// total_checks/passed/failed/checks) kullanır — geçici bir passed:0/
// failed:0 yer tutucusunu DEĞİL.

const summarySchema = JSON.parse(fs.readFileSync(path.join(SCHEMA_DIR, 'validation-summary.schema.json'), 'utf8'));

function makeValidSummary() {
	return {
		schema_version: '1.0.0',
		record_type: 'validation_summary',
		total_checks: 3,
		passed: 2,
		failed: 1,
		checks: [
			{ id: 'a', label: 'A', ok: true, detail: '' },
			{ id: 'b', label: 'B', ok: true, detail: '' },
			{ id: 'c', label: 'C', ok: false, detail: 'x' },
		],
	};
}

test('final summary: geçerli nesne hatasız geçer', function () {
	const errors = validateFinalSummary(makeValidSummary(), summarySchema);
	assert.strictEqual(errors.length, 0, errors.join(' | '));
});

test('final summary: total_checks !== checks.length reddedilir', function () {
	const summary = makeValidSummary();
	summary.total_checks = 99;
	const errors = validateFinalSummary(summary, summarySchema);
	assert.ok(errors.some(function (e) { return /total_checks/.test(e); }));
});

test('final summary: passed+failed !== total_checks reddedilir', function () {
	const summary = makeValidSummary();
	summary.failed = 5;
	const errors = validateFinalSummary(summary, summarySchema);
	assert.ok(errors.some(function (e) { return /passed\+failed/.test(e); }));
});

test('final summary: passed gerçek ok:true sayısıyla uyuşmadığında reddedilir', function () {
	const summary = makeValidSummary();
	summary.passed = 3;
	summary.failed = 0;
	const errors = validateFinalSummary(summary, summarySchema);
	assert.ok(errors.some(function (e) { return /passed \(3\)/.test(e); }));
});

test('final summary: failed gerçek ok:false sayısıyla uyuşmadığında reddedilir', function () {
	const summary = makeValidSummary();
	summary.passed = 1;
	summary.failed = 2;
	const errors = validateFinalSummary(summary, summarySchema);
	assert.ok(errors.some(function (e) { return /failed \(2\)/.test(e); }));
});

// --- §8 (Faz 6A Doğrulayıcı Bütünlük Kapanışı): validateFinalSummary fail-closed dayanıklılığı ---
// Bozuk girdilerde throw ETMEDEN hata listesi dönmeli — kontrolsüz
// .length/.filter TypeError'ı DEĞİL.

test('§8.1: summary tamamen bozuk (null) olduğunda throw etmeden hata döner', function () {
	assert.doesNotThrow(function () {
		const errors = validateFinalSummary(null, summarySchema);
		assert.ok(errors.length > 0);
	});
});
test('§8.2: "checks" dizi değil (obje) olduğunda throw etmeden hata döner', function () {
	assert.doesNotThrow(function () {
		const summary = makeValidSummary();
		summary.checks = { not: 'an array' };
		const errors = validateFinalSummary(summary, summarySchema);
		assert.ok(errors.length > 0);
		assert.ok(errors.some(function (e) { return /"checks" bir dizi olmalı/.test(e); }));
	});
});
test('§8.3: "checks" tamamen eksik (undefined) olduğunda throw etmeden hata döner', function () {
	assert.doesNotThrow(function () {
		const summary = makeValidSummary();
		delete summary.checks;
		const errors = validateFinalSummary(summary, summarySchema);
		assert.ok(errors.length > 0);
	});
});
test('§8.4: "passed"/"failed"/"total_checks" yanlış tipte (string) olduğunda throw etmeden hata döner', function () {
	assert.doesNotThrow(function () {
		const summary = makeValidSummary();
		summary.total_checks = 'üç';
		summary.passed = 'iki';
		summary.failed = 'bir';
		const errors = validateFinalSummary(summary, summarySchema);
		assert.ok(errors.length > 0);
		assert.ok(errors.some(function (e) { return /sayı olmalı/.test(e); }));
	});
});
test('§8.5: dört sayısal invariant hâlâ (geçerli şekilde) çalışıyor — regresyon', function () {
	const summary = makeValidSummary();
	summary.total_checks = 999;
	const errors = validateFinalSummary(summary, summarySchema);
	assert.ok(errors.some(function (e) { return /total_checks/.test(e); }));
});

// --- §5.3 (Faz 6A Doğrulayıcı Bütünlük Kapanışı): gerçek dosya-sistemi fixture testi ---
// GERÇEK bir bogus "*.manifest.json" dosyasını wordpress-site/data/content
// altına yazar, tespit edildiğini kanıtlar, ardından HER KOŞULDA (assert
// başarısız olsa bile) temizler — committed manifestlerin hiçbiri
// değişmez, yalnız yeni bir dosya geçici olarak eklenir/silinir.
test('§5.3: data/content altına eklenen beklenmeyen "*.manifest.json" dosyası GERÇEKTEN tespit edilir', function () {
	const bogusPath = path.join(DATA_DIR, 'content', 'bogus-leftover.manifest.json');
	fs.writeFileSync(bogusPath, '{}\n', { encoding: 'utf8' });
	try {
		const unexpected = findUnexpectedManifestFiles(DATA_DIR);
		assert.ok(unexpected.indexOf('content/bogus-leftover.manifest.json') !== -1, 'bogus dosya tespit edilmeliydi');
	} finally {
		fs.unlinkSync(bogusPath);
	}
});
test('§5.3b: temizlik sonrası beklenmeyen dosya kalmıyor (regresyon)', function () {
	const unexpected = findUnexpectedManifestFiles(DATA_DIR);
	assert.deepStrictEqual(unexpected, []);
});

if (failures > 0) {
	process.stderr.write('\n' + failures + '/' + total + ' manifest doğrulama testi BAŞARISIZ.\n');
	process.exit(1);
}
process.stdout.write('\nTüm ' + total + ' manifest doğrulama testi geçti.\n');
