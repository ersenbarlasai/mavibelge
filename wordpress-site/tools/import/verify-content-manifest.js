'use strict';
/**
 * Faz 7 — İÇERİK manifest DOĞRULAMA modu: kaynaktan bellekte yeniden üretir (diske YAZMAZ),
 * diskteki wordpress-site/data/content/{news,references,faqs}.manifest.json ile byte-eşitliğini,
 * şema (lib/mini-schema.js) ve çapraz-alan sözleşmesini ve doğrulanmış kaynak sayılarını
 * (haber 6, referans logosu 15, SSS 6; lib/expected-counts.js CONTENT_EXPECTED) kontrol eder. Herhangi bir
 * fark/başarısız kontrolde sıfırdan farklı çıkış kodu. Katalog doğrulayıcısına
 * (verify-manifest.js) dokunmaz. WordPress'e dokunmaz, ağ isteği yapmaz.
 *
 * Run: node wordpress-site/tools/import/verify-content-manifest.js
 */

const fs = require('fs');
const path = require('path');

const { computeContent, deriveFiles, loadContentSources, DATA_DIR, SCHEMA_DIR, NEWS_PATH, REFERENCES_PATH, FAQS_PATH } = require('./build-content-manifest');
const { validateContentFiles } = require('./lib/validate-content-set');
const { toDeterministicJson } = require('./lib/hash');
const { CONTENT_EXPECTED } = require('./lib/expected-counts');

const EXPECTED_CONTENT_FILES = ['news.manifest.json', 'references.manifest.json', 'faqs.manifest.json'];
const KNOWN_OTHER_MANIFESTS = [
	'sectors.manifest.json', 'qualifications.manifest.json', 'fees.manifest.json',
	// Faz 12: sayfa manifesti ayrı hatta (verify-page-manifest.js).
	'pages.manifest.json',
];

/** content/ altında beklenmeyen *.manifest.json dosyaları (bilinen 3 katalog + 2 içerik dışında). */
function findUnexpectedContentFiles(dataDir) {
	const dir = path.join(dataDir, 'content');
	if (!fs.existsSync(dir)) {
		return [];
	}
	return fs.readdirSync(dir).filter(function (name) {
		return /\.manifest\.json$/.test(name) && EXPECTED_CONTENT_FILES.indexOf(name) === -1 && KNOWN_OTHER_MANIFESTS.indexOf(name) === -1;
	}).map(function (name) {
		return 'content/' + name;
	});
}

/** Diskteki iki içerik dosyasını okur; yoksa/bozuksa hata döner. */
function readDisk(dataDir) {
	const files = {};
	const raw = {};
	const errors = [];
	[NEWS_PATH, REFERENCES_PATH, FAQS_PATH].forEach(function (rel) {
		const abs = path.join(dataDir, rel);
		if (!fs.existsSync(abs)) {
			errors.push('dosya yok: ' + rel);
			return;
		}
		raw[rel] = fs.readFileSync(abs, 'utf8');
		try {
			files[rel] = JSON.parse(raw[rel]);
		} catch (e) {
			errors.push('JSON parse hatası (' + rel + '): ' + e.message);
		}
	});
	return { files: files, raw: raw, errors: errors };
}

/**
 * Taze üretim ile diskin byte-eşitliğini kıyaslar (test edilebilir: dataDir enjekte edilir).
 *
 * @returns {{matches: boolean, mismatchDetails: string[]}}
 */
function compareFreshToDisk(freshFiles, dataDir) {
	const mismatchDetails = [];
	const disk = readDisk(dataDir);
	disk.errors.forEach(function (e) {
		mismatchDetails.push(e);
	});
	Object.keys(freshFiles).forEach(function (rel) {
		if (undefined === disk.raw[rel]) {
			return;
		}
		if (toDeterministicJson(freshFiles[rel]) !== disk.raw[rel]) {
			mismatchDetails.push(rel + ': disk içeriği yeniden hesaplanan manifestle byte-eşit değil.');
		}
	});
	return { matches: 0 === mismatchDetails.length, mismatchDetails: mismatchDetails };
}

const checks = [];
function record(id, label, ok, detail) {
	checks.push({ id: id, label: label, ok: !!ok, detail: undefined === detail ? '' : String(detail) });
}

function main() {
	const fresh1 = computeContent();
	record('fresh_build_ok', 'kaynaktan taze üretim hatasız (kaynak doğrulaması + şema + çapraz alan)', 0 === fresh1.errors.length, fresh1.errors.join(' | '));
	if (0 !== fresh1.errors.length) {
		return finish();
	}
	const fresh2 = computeContent();
	record('deterministic', 'iki ardışık taze üretim byte-eşit (deterministik)', JSON.stringify(fresh1.files) === JSON.stringify(fresh2.files) && toDeterministicJson(fresh1.files[NEWS_PATH]) === toDeterministicJson(fresh2.files[NEWS_PATH]));

	const cmp = compareFreshToDisk(fresh1.files, DATA_DIR);
	record('disk_matches_recompute', 'diskteki üç içerik dosyası taze üretimle byte-eşit', cmp.matches, cmp.mismatchDetails.join(' | '));

	const disk = readDisk(DATA_DIR);
	record('disk_readable', 'üç içerik dosyası diskte var ve geçerli JSON', 0 === disk.errors.length, disk.errors.join(' | '));
	if (0 === disk.errors.length) {
		const validation = validateContentFiles(disk.files, fresh1.sources, SCHEMA_DIR, function () {
			return deriveFiles(fresh1.sources).files;
		});
		record('disk_schema_and_cross_fields', 'disk: zarf + şema (news/reference) + çapraz alan + taze türetim eşitliği', 0 === validation.errors.length, validation.errors.slice(0, 5).join(' | '));
		const news = disk.files[NEWS_PATH];
		const refs = disk.files[REFERENCES_PATH];
		record('count_news', 'haber sayısı ' + CONTENT_EXPECTED.news, news.records.length === CONTENT_EXPECTED.news && news.count === CONTENT_EXPECTED.news, news.records.length);
		record('count_references', 'referans (logo) sayısı ' + CONTENT_EXPECTED.references, refs.records.length === CONTENT_EXPECTED.references && refs.count === CONTENT_EXPECTED.references, refs.records.length);
		const faqs = disk.files[FAQS_PATH];
		record('count_faqs', 'SSS sayısı ' + CONTENT_EXPECTED.faqs, faqs.records.length === CONTENT_EXPECTED.faqs && faqs.count === CONTENT_EXPECTED.faqs, faqs.records.length);
		const keys = news.records.map(function (r) { return r.source_key; }).concat(refs.records.map(function (r) { return r.source_key; }), faqs.records.map(function (r) { return r.source_key; }));
		record('unique_source_keys', 'üç dosya birlikte 27 benzersiz source_key', new Set(keys).size === keys.length && 27 === keys.length, keys.length);
		record('reference_name_unverified', 'her referans kaydı name_status=unverified taşır ve zarf notu firma adının tahmin edilmediğini belirtir', refs.records.every(function (r) { return 'unverified' === r.name_status; }) && refs.notes.length > 1 && /TAHMİN EDİLMEDİ/.test(refs.notes[1]));
	}

	const unexpected = findUnexpectedContentFiles(DATA_DIR);
	record('no_unexpected_files', 'content/ altında beklenmeyen manifest dosyası yok', 0 === unexpected.length, unexpected.join(', '));

	const src = loadContentSources();
	record('source_sha_matches', 'kayıtların source.sha256 değeri gerçek kaynak dosya özetidir (haber + referans envanteri + SSS)', disk.files[NEWS_PATH] && disk.files[NEWS_PATH].source.sha256 === src.news.sha256 && disk.files[REFERENCES_PATH] && disk.files[REFERENCES_PATH].source.sha256 === src.references.sha256 && disk.files[FAQS_PATH] && disk.files[FAQS_PATH].source.sha256 === src.faqs.sha256);

	return finish();
}

function finish() {
	const failed = checks.filter(function (c) { return !c.ok; });
	checks.forEach(function (c) {
		process.stdout.write((c.ok ? 'PASS  ' : 'FAIL  ') + c.label + (c.ok || '' === c.detail ? '' : ' — ' + c.detail) + '\n');
	});
	const passed = checks.length - failed.length;
	process.stdout.write('\n' + passed + '/' + checks.length + ' içerik manifest kontrolü geçti.\n');
	if (failed.length > 0) {
		process.exit(1);
	}
}

if (require.main === module) {
	main();
}

module.exports = { main, compareFreshToDisk, findUnexpectedContentFiles, readDisk };
