'use strict';
/**
 * Faz 7 — haber + referans (İÇERİK) manifest çiftinin TEK doğrulayıcısı. Hem
 * build-content-manifest.js (yazmadan ÖNCE) hem verify-content-manifest.js (disk
 * karşılaştırması) hem test-content-manifest.js AYNI fonksiyonu kullanır; ikinci,
 * ayrışabilecek bir kural kopyası yoktur. Katalog doğrulayıcısına
 * (lib/validate-manifest-set.js) DOKUNMAZ.
 *
 * Kontroller (hepsi hata listesine yazılır, ilk hatada durmaz):
 *  1. Zarf: kapalı üst-seviye anahtar kümesi, record_type, count === records.length,
 *     count === CONTENT_EXPECTED, source.file/sha256 gerçek kaynakla eşit, notes string listesi.
 *  2. Her kayıt ilgili şemaya (lib/mini-schema.js) uyar.
 *  3. Çapraz alan: source_index === konum, source_key === önek + slug, source_key ve slug benzersiz,
 *     kayıt.source === zarf.source, haber tarihi takvimde var, referans slug === slugify(name).
 *  4. TAZE TÜRETİM eşitliği: kayıtlar kaynaktan yeniden türetilenle birebir aynı (değiştirilmiş
 *     slug/tarih/tür/gövde, eksik veya fazla kayıt burada da yakalanır).
 */

const fs = require('fs');
const path = require('path');

const { validateAgainstSchema, assertKnownKeywords } = require('./mini-schema');
const { CONTENT_EXPECTED } = require('./expected-counts');
const { slugify } = require('./slug');

const NEWS_PATH = 'content/news.manifest.json';
const REFERENCES_PATH = 'content/references.manifest.json';
const FAQS_PATH = 'content/faqs.manifest.json';
const REPO_ROOT = path.resolve(__dirname, '..', '..', '..', '..');
const { sha256Hex } = require('./hash');

const ENVELOPE_KEYS = ['schema_version', 'record_type', 'count', 'source', 'notes', 'records'];

const SPEC = {
	news: { relPath: NEWS_PATH, recordType: 'news', prefix: 'news:', schemaFile: 'news.schema.json', expected: CONTENT_EXPECTED.news, sourceKey: 'news' },
	references: { relPath: REFERENCES_PATH, recordType: 'reference', prefix: 'reference:', schemaFile: 'reference.schema.json', expected: CONTENT_EXPECTED.references, sourceKey: 'references' },
	faqs: { relPath: FAQS_PATH, recordType: 'faq', prefix: 'faq:', schemaFile: 'faq.schema.json', expected: CONTENT_EXPECTED.faqs, sourceKey: 'faqs' },
};

function loadSchema(schemaDir, file, errors) {
	try {
		const schema = JSON.parse(fs.readFileSync(path.join(schemaDir, file), 'utf8'));
		assertKnownKeywords(schema, file);
		return schema;
	} catch (e) {
		errors.push('şema okunamadı/geçersiz (' + file + '): ' + e.message);
		return null;
	}
}

function isRealCalendarDate(value) {
	if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
		return false;
	}
	const y = parseInt(value.slice(0, 4), 10);
	const m = parseInt(value.slice(5, 7), 10);
	const d = parseInt(value.slice(8, 10), 10);
	const dt = new Date(Date.UTC(y, m - 1, d));
	return dt.getUTCFullYear() === y && dt.getUTCMonth() === m - 1 && dt.getUTCDate() === d;
}

/** Düz metin kanonik biçimi — PHP tarafındaki MaviBelge_Core_Import_Record_Validator::is_canonical_plain_text() ile AYNI kural. */
function isCanonicalPlainText(value) {
	return 'string' === typeof value && '' !== value && value === value.trim() && !/[<>]/.test(value) && !/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/.test(value);
}

function validateEnvelope(spec, file, sources, errors) {
	const label = spec.relPath;
	if (null === file || 'object' !== typeof file || Array.isArray(file)) {
		errors.push(label + ': zarf bir obje olmalı.');
		return false;
	}
	const keys = Object.keys(file);
	keys.forEach(function (k) {
		if (ENVELOPE_KEYS.indexOf(k) === -1) {
			errors.push(label + ': zarfta beklenmeyen üst-seviye alan ("' + k + '").');
		}
	});
	ENVELOPE_KEYS.forEach(function (k) {
		if (!Object.prototype.hasOwnProperty.call(file, k)) {
			errors.push(label + ': zarfta zorunlu alan eksik ("' + k + '").');
		}
	});
	if ('2.0.0' !== file.schema_version) {
		errors.push(label + ': schema_version "2.0.0" olmalı.');
	}
	if (spec.recordType !== file.record_type) {
		errors.push(label + ': record_type "' + spec.recordType + '" olmalı.');
	}
	if (!Array.isArray(file.records)) {
		errors.push(label + ': records bir dizi olmalı.');
		return false;
	}
	if (!Number.isInteger(file.count) || file.count !== file.records.length) {
		errors.push(label + ': count (' + file.count + ') records uzunluğuyla (' + file.records.length + ') uyuşmuyor.');
	}
	if (file.records.length !== spec.expected) {
		errors.push(label + ': beklenen ' + spec.expected + ' kayıt, gerçek ' + file.records.length + ' (sayı uyuşmazlığı).');
	}
	const src = sources[spec.sourceKey];
	if (!file.source || 'object' !== typeof file.source || Object.keys(file.source).sort().join(',') !== 'file,sha256') {
		errors.push(label + ': source yalnız {file, sha256} taşıyan bir obje olmalı.');
	} else if (file.source.file !== src.repoRelativePath || file.source.sha256 !== src.sha256) {
		errors.push(label + ': source.file/sha256 gerçek kaynak dosyayla (' + src.repoRelativePath + ') eşleşmiyor.');
	}
	if (!Array.isArray(file.notes) || !file.notes.every(function (n) { return 'string' === typeof n; })) {
		errors.push(label + ': notes bir string listesi olmalı.');
	}
	return true;
}

function validateRecords(spec, file, schema, errors) {
	const label = spec.relPath;
	const seenKeys = new Set();
	const seenSlugs = new Set();
	const seenLogoSha = new Set();
	file.records.forEach(function (record, index) {
		const where = label + ' records[' + index + ']';
		if (schema) {
			validateAgainstSchema(record, schema).forEach(function (e) {
				errors.push(where + ' şema: ' + e);
			});
		}
		if (!record || 'object' !== typeof record) {
			return;
		}
		if (record.source_index !== index) {
			errors.push(where + ': source_index (' + record.source_index + ') konumla (' + index + ') uyuşmuyor.');
		}
		if (spec.prefix + record.slug !== record.source_key) {
			errors.push(where + ': source_key ("' + record.source_key + '") "' + spec.prefix + '" + slug ile tutarsız.');
		}
		if (seenKeys.has(record.source_key)) {
			errors.push(where + ': source_key tekrar ediyor ("' + record.source_key + '").');
		}
		seenKeys.add(record.source_key);
		if (seenSlugs.has(record.slug)) {
			errors.push(where + ': slug tekrar ediyor ("' + record.slug + '").');
		}
		seenSlugs.add(record.slug);
		if (JSON.stringify(record.source) !== JSON.stringify(file.source)) {
			errors.push(where + ': source zarf source ile tutarsız (provenance drift).');
		}
		if ('news' === spec.sourceKey) {
			if ('string' !== typeof record.published_on || !isRealCalendarDate(record.published_on)) {
				errors.push(where + ': published_on takvimde var olan bir YYYY-AA-GG tarihi değil.');
			}
			['title', 'summary', 'body'].forEach(function (f) {
				if (!isCanonicalPlainText(record[f])) {
					errors.push(where + ': ' + f + ' kanonik düz metin değil (boş/baş-son boşluk/"<" ">"/kontrol karakteri).');
				}
			});
		} else if ('faqs' === spec.sourceKey) {
			if ('string' !== typeof record.question || slugify(record.question) !== record.slug) {
				errors.push(where + ': slug slugify(question) ile tutarsız.');
			}
			['question', 'answer'].forEach(function (f) {
				if (!isCanonicalPlainText(record[f])) {
					errors.push(where + ': ' + f + ' kanonik düz metin değil.');
				}
			});
		} else {
			const num = String(index + 1).padStart(2, '0');
			if (record.name !== 'Referans ' + num || 'string' !== typeof record.name || slugify(record.name) !== record.slug) {
				errors.push(where + ': name nötr sıra etiketi ("Referans NN") ve slug slugify(name) olmalı (firma adı tahmin edilmez).');
			}
			['name', 'alt'].forEach(function (f) {
				if (!isCanonicalPlainText(record[f])) {
					errors.push(where + ': ' + f + ' kanonik düz metin değil.');
				}
			});
			if ('string' === typeof record.logo_file && (record.logo_file.indexOf('..') !== -1 || record.logo_file.charAt(0) === '/')) {
				errors.push(where + ': logo_file mutlak yol veya ".." içeremez.');
			}
			if (record.logo_file !== 'wordpress-site/data/sources/reference-logos/ref-' + num + '.png') {
				errors.push(where + ': logo_file sıra numarasıyla beklenen yolda değil.');
			} else {
				let buf = null;
				try {
					buf = fs.readFileSync(path.join(REPO_ROOT, record.logo_file));
				} catch (e) {
					errors.push(where + ': logo dosyası yok (' + record.logo_file + ').');
				}
				if (null !== buf && (sha256Hex(buf) !== record.logo_sha256 || buf.length !== record.logo_bytes)) {
					errors.push(where + ': logo_sha256/logo_bytes gerçek dosyayla uyuşmuyor.');
				}
			}
			if (seenLogoSha.has(record.logo_sha256)) {
				errors.push(where + ': aynı logo tekrar ediyor (logo_sha256).');
			}
			seenLogoSha.add(record.logo_sha256);
		}
	});
}

/**
 * @param {{'content/news.manifest.json': object, 'content/references.manifest.json': object, 'content/faqs.manifest.json': object}} files
 * @param {{news, references, faqs: {data, repoRelativePath, sha256}}} sources extractContent() çıktısı
 * @param {string} schemaDir wordpress-site/data/schema
 * @param {Function} freshFn () => {'content/...': fresh manifest} — kaynaktan yeniden türetim (dairesel bağımlılık olmasın diye enjekte edilir)
 * @returns {{errors: string[]}}
 */
function validateContentFiles(files, sources, schemaDir, freshFn) {
	const errors = [];
	const envelopeSchema = loadSchema(schemaDir, 'manifest-envelope.schema.json', errors);
	Object.keys(SPEC).forEach(function (key) {
		const spec = SPEC[key];
		const file = files[spec.relPath];
		if (envelopeSchema && file && 'object' === typeof file) {
			validateAgainstSchema(file, envelopeSchema).forEach(function (e) {
				errors.push(spec.relPath + ' zarf şeması: ' + e);
			});
		}
		if (!validateEnvelope(spec, file, sources, errors)) {
			return;
		}
		validateRecords(spec, file, loadSchema(schemaDir, spec.schemaFile, errors), errors);
	});
	Object.keys(files).forEach(function (rel) {
		if (rel !== NEWS_PATH && rel !== REFERENCES_PATH && rel !== FAQS_PATH) {
			errors.push('beklenmeyen içerik manifest dosyası: ' + rel);
		}
	});
	if ('function' === typeof freshFn) {
		const fresh = freshFn();
		Object.keys(fresh).forEach(function (rel) {
			if (JSON.stringify(fresh[rel]) !== JSON.stringify(files[rel])) {
				errors.push(rel + ': kaynaktan yeniden türetilen içerikle eşit değil (değiştirilmiş/eksik/fazla kayıt veya alan).');
			}
		});
	}
	return { errors: errors };
}

module.exports = { validateContentFiles, isRealCalendarDate, isCanonicalPlainText, NEWS_PATH, REFERENCES_PATH, FAQS_PATH, SPEC };
