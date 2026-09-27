'use strict';
/**
 * Faz 7/12b — İÇERİK manifest üretimi. Üç yerel kaynak, üç manifest dosyası:
 *   tanitim-site/assets/data/news.js                                   -> content/news.manifest.json       (6 gerçek haber/duyuru)
 *   wordpress-site/data/sources/reference-logos/ (onaylı, yerel)       -> content/references.manifest.json (15 logo; firma adı DOĞRULANMAMIŞ)
 *   tanitim-site/sss.html (kurumca onaylanmış, dondurulmuş)            -> content/faqs.manifest.json       (6 soru-cevap)
 * Katalog üretimi (build-manifest.js) ve onun 5 dosyası DEĞİŞMEZ.
 *
 * Yalnız wordpress-site/data/content/ altına yazar; WordPress'e dokunmaz, ağ isteği yapmaz.
 *
 * Run: node wordpress-site/tools/import/build-content-manifest.js
 *
 * Güvenceler (build-manifest.js ile AYNI dürüst çerçeve):
 *  1. ÖN-DOĞRULAMA başarısız (sayı 6/15/6'dan farklı, tekrar eden slug, geçersiz tarih/tür, logo SHA/PNG/boyut uyuşmazlığı,
 *     şema/çapraz-alan ihlali, ...) -> çıkış 1 ve HİÇBİR şey yazılmaz: yazıcı çağrılmaz (bkz. runBuild()).
 *  2. Yazma sırasında I/O hatası: üç dosya için "ya hep ya hiç" — writeContentAtomic() yeni içerik dosyalarını önce geçici dosyaya
 *     yazar, sonra yeniden adlandırır; bir yeniden adlandırma başarısız olursa daha önce yeniden adlandırılanlar ESKİ içeriğine
 *     (veya yokluğuna) geri döndürülür. Geri döndürmenin de başarısız olduğu durumda hata yeniden fırlatılır;
 *     verify-content-manifest.js karışık seti disk-eşitliği kontrolüyle yakalar.
 *  3. Deterministik: iki koşu byte-eşit çıktı üretir (zaman damgası/rastgelelik yok).
 */

const fs = require('fs');
const path = require('path');

const { extractOne, REPO_ROOT } = require('./extract-source');
const { loadReferenceLogoSource, loadFaqSource } = require('./lib/content-sources');
const { sha256Hex, toDeterministicJson } = require('./lib/hash');
const { slugify } = require('./lib/slug');
const { CONTENT_EXPECTED } = require('./lib/expected-counts');
const { validateContentFiles, isRealCalendarDate, isCanonicalPlainText, NEWS_PATH, REFERENCES_PATH, FAQS_PATH } = require('./lib/validate-content-set');

const SCHEMA_VERSION = '2.0.0';
const DATA_DIR = path.join(REPO_ROOT, 'wordpress-site', 'data');
const SCHEMA_DIR = path.join(DATA_DIR, 'schema');
const NEWS_TYPES = ['haber', 'duyuru'];
const SLUG_RE = /^[a-z0-9]+(-[a-z0-9]+)*$/;

const NEWS_NOTES = [
	'news.js: 6 gerçek haber/duyuru (başlık ve tarihler gerçektir). Kaynaktaki image alanı AKTARILMAZ (yazıya özel fotoğraf yoktur; ek eşlemesi bu fazda yok). İçe aktarılan haber taslak + in_review yazılır, asla yayınlanmaz/onaylanmaz.',
];
const REFERENCE_NOTES = [
	'Kaynak: onaylı canlı referans sayfasından (https://mavibelge.com.tr/referanslar/) bir kez alınmış 15 logo; yerel envanter wordpress-site/data/sources/reference-logos/reference-logos.manifest.json (URL, alınma tarihi, SHA-256, boyut). Derleme sırasında ağ isteği yoktur.',
	'Firma adı görselden TAHMİN EDİLMEDİ (name_status=unverified): canlı kaynak logolar için başlık/alt metin/bağlantı taşımıyor. name nötr sıra etiketidir; kurum adları doğrulanınca ayrı bir onaylı karar ve manifest güncellemesiyle girilir.',
	'Her logo içe aktarımda gerçek bir WordPress attachment\'ına çevrilir (aynı içerikli logo tekrar eklenmez); _mb_logo_attachment_id gerçek attachment kimliğidir. Kayıtlar taslak yazılır.',
];
const FAQ_NOTES = [
	'Kaynak: tanitim-site/sss.html (kullanıcı tarafından görsel kabul edilmiş, kurumca onaylanmış; dondurulmuş, yalnız okunur). 6 gerçek soru-cevap. Soru mb_sss başlığına, cevap gövdesine düz metin olarak yazılır; kayıtlar taslak yazılır.',
];

function reportFail(errors) {
	process.stderr.write('\nİçerik manifest üretimi BAŞARISIZ — ' + errors.length + ' hata:\n');
	errors.forEach(function (e) {
		process.stderr.write('  - ' + e + '\n');
	});
	process.stderr.write('\nHiçbir dosya yazılmadı (tümü-ya-da-hiçbiri).\n');
}

/** Üç yerel içerik kaynağını okur. @returns {{news, references, faqs}} */
function loadContentSources() {
	return { news: extractOne('news'), references: loadReferenceLogoSource(), faqs: loadFaqSource() };
}

function buildNewsManifest(source) {
	const errors = [];
	const records = [];
	const seen = {};
	source.data.forEach(function (row, index) {
		const where = 'news[' + index + ']';
		const slug = row.slug;
		if (!SLUG_RE.test(slug)) {
			errors.push(where + ': slug biçimi geçersiz ("' + slug + '").');
			return;
		}
		if (Object.prototype.hasOwnProperty.call(seen, slug)) {
			errors.push(where + ': slug tekrarlanıyor ("' + slug + '").');
			return;
		}
		seen[slug] = true;
		if (!isRealCalendarDate(row.date)) {
			errors.push(where + ': tarih takvimde var olan bir YYYY-AA-GG değil ("' + row.date + '").');
			return;
		}
		if (NEWS_TYPES.indexOf(row.type) === -1) {
			errors.push(where + ': tür "haber" veya "duyuru" olmalı ("' + row.type + '").');
			return;
		}
		['title', 'summary', 'body'].forEach(function (f) {
			if (!isCanonicalPlainText(row[f])) {
				errors.push(where + ': ' + f + ' kanonik düz metin değil (boş/baş-son boşluk/"<" ">"/kontrol karakteri).');
			}
		});
		records.push({
			schema_version: SCHEMA_VERSION,
			source_key: 'news:' + slug,
			source_index: index,
			slug: slug,
			title: row.title,
			published_on: row.date,
			news_type: row.type,
			summary: row.summary,
			body: row.body,
			source: { file: source.repoRelativePath, sha256: source.sha256 },
		});
	});
	if (records.length !== CONTENT_EXPECTED.news) {
		errors.push('news: beklenen ' + CONTENT_EXPECTED.news + ' kayıt, gerçek ' + records.length + '.');
	}
	return { errors: errors, records: records };
}

/** Nötr sıra etiketi ("Referans 01"): firma adı DEĞİLDİR. */
function referenceName(index) {
	return 'Referans ' + String(index + 1).padStart(2, '0');
}

function buildReferenceManifest(source) {
	const errors = (source.errors || []).slice();
	const records = [];
	const seen = {};
	source.data.forEach(function (logo, index) {
		const where = 'references[' + index + ']';
		const name = referenceName(index);
		const slug = slugify(name);
		if ('' === slug || !SLUG_RE.test(slug) || slug !== logo.slug) {
			errors.push(where + ': slug beklenen sıra etiketinden türemiyor ("' + logo.slug + '").');
			return;
		}
		if (Object.prototype.hasOwnProperty.call(seen, slug)) {
			errors.push(where + ': slug tekrarlanıyor ("' + slug + '").');
			return;
		}
		seen[slug] = true;
		records.push({
			schema_version: SCHEMA_VERSION,
			source_key: 'reference:' + slug,
			source_index: index,
			name: name,
			slug: slug,
			logo_file: logo.file,
			logo_sha256: logo.sha256,
			logo_bytes: logo.bytes,
			logo_width: logo.width,
			logo_height: logo.height,
			alt: 'Referans kuruluş logosu ' + String(index + 1).padStart(2, '0'),
			name_status: logo.name_status,
			source: { file: source.repoRelativePath, sha256: source.sha256 },
		});
	});
	if (records.length !== CONTENT_EXPECTED.references) {
		errors.push('references: beklenen ' + CONTENT_EXPECTED.references + ' kayıt, gerçek ' + records.length + '.');
	}
	return { errors: errors, records: records };
}

function buildFaqManifest(source) {
	const errors = (source.errors || []).slice();
	const records = [];
	const seen = {};
	source.data.forEach(function (row, index) {
		const where = 'faqs[' + index + ']';
		const slug = slugify(row.question);
		if ('' === slug || !SLUG_RE.test(slug)) {
			errors.push(where + ': soru geçerli bir slug üretmiyor ("' + row.question + '").');
			return;
		}
		if (Object.prototype.hasOwnProperty.call(seen, slug)) {
			errors.push(where + ': slug tekrarlanıyor ("' + slug + '").');
			return;
		}
		seen[slug] = true;
		['question', 'answer'].forEach(function (f) {
			if (!isCanonicalPlainText(row[f])) {
				errors.push(where + ': ' + f + ' kanonik düz metin değil.');
			}
		});
		records.push({
			schema_version: SCHEMA_VERSION,
			source_key: 'faq:' + slug,
			source_index: index,
			slug: slug,
			question: row.question,
			answer: row.answer,
			source: { file: source.repoRelativePath, sha256: source.sha256 },
		});
	});
	if (records.length !== CONTENT_EXPECTED.faqs) {
		errors.push('faqs: beklenen ' + CONTENT_EXPECTED.faqs + ' kayıt, gerçek ' + records.length + '.');
	}
	return { errors: errors, records: records };
}

/** Kaynaktan dosya değerlerini TÜRETİR (doğrulamasız); computeContent() ve doğrulayıcının taze türetimi bunu kullanır. */
function deriveFiles(sources) {
	const news = buildNewsManifest(sources.news);
	const refs = buildReferenceManifest(sources.references);
	const faqs = buildFaqManifest(sources.faqs);
	return {
		errors: [].concat(news.errors, refs.errors, faqs.errors),
		files: {
			[NEWS_PATH]: {
				schema_version: SCHEMA_VERSION,
				record_type: 'news',
				count: news.records.length,
				source: { file: sources.news.repoRelativePath, sha256: sources.news.sha256 },
				notes: NEWS_NOTES.slice(),
				records: news.records,
			},
			[REFERENCES_PATH]: {
				schema_version: SCHEMA_VERSION,
				record_type: 'reference',
				count: refs.records.length,
				source: { file: sources.references.repoRelativePath, sha256: sources.references.sha256 },
				notes: REFERENCE_NOTES.slice(),
				records: refs.records,
			},
			[FAQS_PATH]: {
				schema_version: SCHEMA_VERSION,
				record_type: 'faq',
				count: faqs.records.length,
				source: { file: sources.faqs.repoRelativePath, sha256: sources.faqs.sha256 },
				notes: FAQ_NOTES.slice(),
				records: faqs.records,
			},
		},
	};
}

/**
 * Saf hesaplama (diske YAZMAZ). Hem build hem verify kullanır.
 *
 * @param {object} [sourcesOverride] yalnız testler için: loadContentSources() biçiminde bellek içi kaynak.
 * @returns {{errors: string[], files: Object|null, sources: Object}}
 */
function computeContent(sourcesOverride) {
	const sources = sourcesOverride || loadContentSources();
	const derived = deriveFiles(sources);
	if (derived.errors.length > 0) {
		return { errors: derived.errors, files: null, sources: sources };
	}
	const validation = validateContentFiles(derived.files, sources, SCHEMA_DIR, function () {
		return deriveFiles(sources).files;
	});
	if (validation.errors.length > 0) {
		return { errors: validation.errors, files: null, sources: sources };
	}
	return { errors: [], files: derived.files, sources: sources };
}

/**
 * Üç dosya için "ya hep ya hiç": geçici dosyalar -> yeniden adlandırma; herhangi bir adım başarısız olursa
 * daha önce yeniden adlandırılan dosyalar ESKİ içeriğine (yoksa yokluğuna) döndürülür. `fsImpl` testlerde
 * `renameSync`'i ortada başarısız eden sahte bir uygulama olabilir.
 */
function writeContentAtomic(dataDir, fileSet, fsImpl) {
	const impl = fsImpl || fs;
	const entries = [];
	try {
		Object.keys(fileSet).forEach(function (relPath) {
			const absPath = path.join(dataDir, relPath);
			impl.mkdirSync(path.dirname(absPath), { recursive: true });
			const previous = impl.existsSync(absPath) ? impl.readFileSync(absPath, 'utf8') : null;
			const tmpPath = absPath + '.tmp-' + process.pid + '-' + Math.floor(Math.random() * 1e9);
			impl.writeFileSync(tmpPath, toDeterministicJson(fileSet[relPath]), { encoding: 'utf8' });
			entries.push({ tmpPath: tmpPath, absPath: absPath, previous: previous, renamed: false });
		});
		entries.forEach(function (entry) {
			impl.renameSync(entry.tmpPath, entry.absPath);
			entry.renamed = true;
		});
	} catch (e) {
		entries.forEach(function (entry) {
			try {
				if (entry.renamed) {
					if (null === entry.previous) {
						impl.unlinkSync(entry.absPath);
					} else {
						impl.writeFileSync(entry.absPath, entry.previous, { encoding: 'utf8' });
					}
				} else if (impl.existsSync(entry.tmpPath)) {
					impl.unlinkSync(entry.tmpPath);
				}
			} catch (cleanupError) {
				// en iyi çaba: asıl hata fırlatılır; karışık set verify-content-manifest.js tarafından yakalanır.
			}
		});
		throw e;
	}
}

/** Yazıcının çağrılıp çağrılmadığı kararı (test edilebilir): geçersiz sonuçta yazıcı ASLA çağrılmaz. */
function runBuild(computeFn, writerFn) {
	const compute = computeFn || computeContent;
	const writer = writerFn || writeContentAtomic;

	const result = compute();
	if (result.errors.length > 0) {
		reportFail(result.errors);
		return { wrote: false, result: result };
	}

	writer(DATA_DIR, result.files);

	process.stdout.write('İçerik manifest üretimi tamam.\n');
	process.stdout.write('  haber: ' + result.files[NEWS_PATH].count + '\n');
	process.stdout.write('  referans (logo; firma adı doğrulanmamış): ' + result.files[REFERENCES_PATH].count + '\n');
	process.stdout.write('  SSS: ' + result.files[FAQS_PATH].count + '\n');
	return { wrote: true, result: result };
}

function main() {
	const outcome = runBuild();
	if (!outcome.wrote) {
		process.exit(1);
	}
}

if (require.main === module) {
	main();
}

module.exports = {
	main, runBuild, computeContent, deriveFiles, writeContentAtomic, buildNewsManifest, buildReferenceManifest, buildFaqManifest, loadContentSources, referenceName,
	DATA_DIR, SCHEMA_DIR, SCHEMA_VERSION, NEWS_PATH, REFERENCES_PATH, FAQS_PATH, sha256Hex,
};
