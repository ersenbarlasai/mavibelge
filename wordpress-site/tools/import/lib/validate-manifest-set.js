'use strict';
/**
 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı — TEK paylaşılan doğrulayıcı.
 * build-manifest.js (yazmadan ÖNCE) ve verify-manifest.js (diskle
 * karşılaştırmadan sonra) AYNI bu modülü çağırır — önceki sürümde şema/
 * çapraz-alan kontrolleri yalnız verify-manifest.js'de vardı, bu da
 * bağımsız incelemenin bulduğu asıl kusurdu ("build ve verify farklı
 * doğrulama yolları kullanıyor").
 *
 * Hiçbir I/O yan etkisi yoktur (yalnız data/schema/*.json okunur);
 * WordPress'e/diske asla yazmaz.
 */

const fs = require('fs');
const path = require('path');

const { validateAgainstSchema, assertKnownKeywords } = require('./mini-schema');
const { parseMykCode } = require('./myk-code');
const { slugify } = require('./slug');
const { EXPECTED } = require('./expected-counts');
const { buildSectorNotes, buildQualificationNotes } = require('./manifest-notes');

function loadSchema(schemaDir, fileName) {
	return JSON.parse(fs.readFileSync(path.join(schemaDir, fileName), 'utf8'));
}

/** Loads and keyword-validates every schema file this project ships. Throws on any unknown/unsupported keyword (e.g. an unresolved $ref). */
function loadAllSchemas(schemaDir) {
	const files = {
		sector: 'sector.schema.json',
		qualification: 'qualification.schema.json',
		fee: 'fee.schema.json',
		mappingRow: 'mapping-row.schema.json',
		unmatchedFee: 'unmatched-fee.schema.json',
		envelope: 'manifest-envelope.schema.json',
		validationSummary: 'validation-summary.schema.json',
	};
	const schemas = {};
	Object.keys(files).forEach(function (key) {
		const schema = loadSchema(schemaDir, files[key]);
		assertKnownKeywords(schema, files[key]);
		schemas[key] = schema;
	});
	return schemas;
}

/**
 * §4 (Faz 6A Son Kabul Düzeltmesi) — mini-schema `oneOf` desteklemediği için
 * tek, muğlak "records veya rows, notes/counts belki var belki yok"
 * ortak zarf şeması bırakmak yerine, HER dosya türü için TAM izinli VE
 * zorunlu üst-seviye anahtar kümesi burada, programatik olarak
 * uygulanıyor. `manifest-envelope.schema.json` yalnız paylaşılan alan
 * TİPLERİNİ (schema_version const, record_type enum, count>=0, vb.)
 * doğrulamak için ayrıca kullanılmaya devam ediyor — üst-seviye anahtar
 * kümesi kısıtı ise SADECE burada, dosya türüne özel olarak var.
 */
const ENVELOPE_CONTRACTS = {
	'content/sectors.manifest.json': {
		recordType: 'sector',
		listKey: 'records',
		topLevelKeys: ['schema_version', 'record_type', 'count', 'source', 'notes', 'records'],
		hasSource: true,
		hasCounts: false,
	},
	'content/qualifications.manifest.json': {
		recordType: 'qualification',
		listKey: 'records',
		topLevelKeys: ['schema_version', 'record_type', 'count', 'source', 'notes', 'records'],
		hasSource: true,
		hasCounts: false,
	},
	'content/fees.manifest.json': {
		recordType: 'fee',
		listKey: 'records',
		topLevelKeys: ['schema_version', 'record_type', 'count', 'counts', 'source', 'records'],
		hasSource: true,
		hasCounts: true,
	},
	'mapping/mapping.manifest.json': {
		recordType: 'mapping',
		listKey: 'rows',
		topLevelKeys: ['schema_version', 'record_type', 'count', 'rows'],
		hasSource: false,
		hasCounts: false,
	},
	'mapping/unmatched-fees.manifest.json': {
		recordType: 'unmatched_fee',
		listKey: 'records',
		topLevelKeys: ['schema_version', 'record_type', 'count', 'records'],
		hasSource: false,
		hasCounts: false,
	},
};

/** Exact, real shape of fees.manifest.json's "counts" object (verify-manifest.js's own EXPECTED-derived counters). */
const FEE_COUNTS_KEYS = [
	'total', 'priceOptionsTotal', 'pricingSingle', 'pricingMulti',
	'multiOptionsTotal', 'feesWithCode', 'feesWithoutCode', 'linkedCount',
];

function checkSourceShape(relPath, source, errors) {
	if (!source || 'object' !== typeof source || Array.isArray(source)) {
		errors.push(relPath + ': "source" bir obje olmalı.');
		return;
	}
	const keys = Object.keys(source);
	const allowed = ['file', 'sha256'];
	keys.forEach(function (k) {
		if (allowed.indexOf(k) === -1) {
			errors.push(relPath + ': "source" içinde beklenmeyen ek alan ("' + k + '").');
		}
	});
	allowed.forEach(function (k) {
		if (!Object.prototype.hasOwnProperty.call(source, k)) {
			errors.push(relPath + ': "source" içinde zorunlu alan eksik ("' + k + '").');
		}
	});
	if ('string' !== typeof source.file || '' === source.file) {
		errors.push(relPath + ': "source.file" boş olmayan string olmalı.');
	}
	if ('string' !== typeof source.sha256 || !/^[0-9a-f]{64}$/.test(source.sha256)) {
		errors.push(relPath + ': "source.sha256" tam 64 küçük-hex karakter olmalı.');
	}
}

function checkCountsShape(relPath, counts, expectedCount, errors) {
	if (!counts || 'object' !== typeof counts || Array.isArray(counts)) {
		errors.push(relPath + ': "counts" bir obje olmalı.');
		return;
	}
	const keys = Object.keys(counts);
	keys.forEach(function (k) {
		if (FEE_COUNTS_KEYS.indexOf(k) === -1) {
			errors.push(relPath + ': "counts" içinde beklenmeyen ek alan ("' + k + '").');
		}
	});
	FEE_COUNTS_KEYS.forEach(function (k) {
		if (!Object.prototype.hasOwnProperty.call(counts, k)) {
			errors.push(relPath + ': "counts" içinde zorunlu sayaç eksik ("' + k + '").');
			return;
		}
		if (!Number.isInteger(counts[k]) || counts[k] < 0) {
			errors.push(relPath + ': "counts.' + k + '" negatif olmayan bir tam sayı olmalı.');
		}
	});
	if (Number.isInteger(counts.total) && counts.total !== expectedCount) {
		errors.push(relPath + ': "counts.total" (' + counts.total + ') üst-seviye "count" (' + expectedCount + ') ile uyuşmuyor.');
	}
}

/** File-type-specific envelope contract: exact top-level key set, records XOR rows, count match, source/counts shape. */
function checkEnvelope(relPath, fileValue, envelopeSchema, errors) {
	const contract = ENVELOPE_CONTRACTS[relPath];
	if (!contract) {
		errors.push(relPath + ': dahili hata — bu dosya için zarf sözleşmesi tanımlı değil.');
		return;
	}

	// Paylaşılan TİP kontrolleri (schema_version const, record_type enum, count>=0, vb.) — bkz. manifest-envelope.schema.json.
	errors.push.apply(
		errors,
		validateAgainstSchema(fileValue, envelopeSchema).map(function (e) {
			return relPath + ' (zarf)' + e.slice(1);
		})
	);

	// Dosya türüne özel TAM üst-seviye anahtar kümesi — fazladan/eksik reddedilir.
	const actualKeys = Object.keys(fileValue);
	actualKeys.forEach(function (k) {
		if (contract.topLevelKeys.indexOf(k) === -1) {
			errors.push(relPath + ': beklenmeyen üst-seviye alan ("' + k + '").');
		}
	});
	contract.topLevelKeys.forEach(function (k) {
		if (!Object.prototype.hasOwnProperty.call(fileValue, k)) {
			errors.push(relPath + ': zorunlu üst-seviye alan eksik ("' + k + '").');
		}
	});

	if (fileValue.record_type !== contract.recordType) {
		errors.push(relPath + ': record_type ("' + fileValue.record_type + '") bu dosya için beklenen ("' + contract.recordType + '") değil.');
	}

	// "records" ile "rows" birbirinin yerine geçemez ve BİRLİKTE bulunamaz.
	const otherListKey = 'rows' === contract.listKey ? 'records' : 'rows';
	if (Object.prototype.hasOwnProperty.call(fileValue, otherListKey)) {
		errors.push(relPath + ': bu dosya türü için yalnız "' + contract.listKey + '" beklenir, "' + otherListKey + '" de mevcut.');
	}
	const list = fileValue[contract.listKey];
	if (!Array.isArray(list)) {
		errors.push(relPath + ': "' + contract.listKey + '" bir dizi olmalı.');
	} else if (fileValue.count !== list.length) {
		errors.push(relPath + ': count (' + fileValue.count + ') gerçek "' + contract.listKey + '" uzunluğuyla (' + list.length + ') eşleşmiyor.');
	}

	if (contract.hasSource) {
		checkSourceShape(relPath, fileValue.source, errors);
	} else if (Object.prototype.hasOwnProperty.call(fileValue, 'source')) {
		errors.push(relPath + ': bu dosya türünde "source" beklenmiyor.');
	}

	if (contract.hasCounts) {
		checkCountsShape(relPath, fileValue.counts, fileValue.count, errors);
	} else if (Object.prototype.hasOwnProperty.call(fileValue, 'counts')) {
		errors.push(relPath + ': bu dosya türünde "counts" beklenmiyor (yalnız fees.manifest.json\'da olabilir).');
	}
}

/** §5.1 (Faz 6A Doğrulayıcı Bütünlük Kapanışı) — the exact, only 5 file keys this whole pipeline ever produces or consumes. */
const EXPECTED_FILE_KEYS = [
	'content/sectors.manifest.json',
	'content/qualifications.manifest.json',
	'content/fees.manifest.json',
	'mapping/mapping.manifest.json',
	'mapping/unmatched-fees.manifest.json',
];

/** Fee record fields that are FIXED planning constants — no importer/build logic may vary them yet (Faz 6B decision fields, not written to WordPress in this phase). */
const FEE_PLANNED_CONSTANTS = {
	source_attachment_id: 0,
	planned_tariff_period: '2026',
	planned_record_status: 'draft',
	planned_valid_from: '',
	planned_valid_until: '',
};

const UNMATCHED_FEE_NOTE = 'qualificationCode kaynakta boş bırakılmış (PDF\'de MYK kodu yok); tahmin/fuzzy eşleştirme yapılmadı, qualification_source_key kasıtlı olarak null.';

/** True if `arr` holds every integer 0..arr.length-1 exactly once (order in the array itself doesn't matter, only the SET does). */
function isCompleteZeroBasedIndexSet(values) {
	const sorted = values.slice().sort(function (a, b) { return a - b; });
	for (let i = 0; i < sorted.length; i++) {
		if (sorted[i] !== i) {
			return false;
		}
	}
	return true;
}

/** Pushes an error for every value in `values` that isn't unique (reported once per duplicate value, not once per occurrence). */
function checkUnique(values, label, errors) {
	const seen = {};
	const duplicates = {};
	values.forEach(function (v) {
		if (Object.prototype.hasOwnProperty.call(seen, v)) {
			duplicates[v] = true;
		}
		seen[v] = true;
	});
	Object.keys(duplicates).forEach(function (v) {
		errors.push(label + ': "' + v + '" birden fazla kayıtta tekrarlanıyor (benzersiz olmalı).');
	});
}

/**
 * §5 (Faz 6A Tam Kapsam ve Sayaç Kapanışı) — fail-closed shape guard for
 * the raw `sources` input (extractAll()'s output). Never throws; returns
 * `true` only when every one of the three source blocks is safe to
 * dereference (`.data` is a real array, `.repoRelativePath` a non-empty
 * string, `.sha256` a real 64-char lowercase hex digest). On any
 * violation, pushes ONE clear error per problem and returns `false` —
 * every caller in this module MUST skip source-dependent comparisons
 * entirely when this returns `false`, rather than risk a second-order
 * TypeError from indexing into a null/non-array `.data`.
 */
function validateSourcesShape(sources, errors) {
	if (!isPlainObjectForValidation(sources)) {
		errors.push('sources: bir obje olmalı (yok, null veya dizi).');
		return false;
	}
	let ok = true;
	['sectors', 'qualifications', 'fees'].forEach(function (key) {
		const block = sources[key];
		if (!isPlainObjectForValidation(block)) {
			errors.push('sources.' + key + ': bir obje olmalı (yok, null veya dizi).');
			ok = false;
			return;
		}
		if (!Array.isArray(block.data)) {
			errors.push('sources.' + key + '.data: bir dizi olmalı.');
			ok = false;
		}
		if ('string' !== typeof block.repoRelativePath || '' === block.repoRelativePath) {
			errors.push('sources.' + key + '.repoRelativePath: boş olmayan string olmalı.');
			ok = false;
		}
		if ('string' !== typeof block.sha256 || !/^[0-9a-f]{64}$/.test(block.sha256)) {
			errors.push('sources.' + key + '.sha256: tam 64 küçük-hex karakter olmalı.');
			ok = false;
		}
	});
	return ok;
}

function isPlainObjectForValidation(v) {
	return null !== v && 'object' === typeof v && !Array.isArray(v);
}

/** Deep, order-sensitive comparison — used for mapping.rows / unmatched.records independent reconstruction. Returns a short mismatch description or null. */
function deepEqual(a, b) {
	return JSON.stringify(a) === JSON.stringify(b);
}

/**
 * @param {{sources: object, files: object}} input
 *   sources: extractAll()'ın ham çıktısı — {sectors:{data,...}, qualifications:{...}, fees:{...}}
 *   files: build-manifest.js computeAll()'ın bellek-içi ürettiği 5 dosya değeri (relPath -> value)
 * @param {string} schemaDir wordpress-site/data/schema mutlak yolu
 * @returns {{errors: string[]}}
 */
function validateManifestSet(input, schemaDir) {
	const errors = [];
	const schemas = loadAllSchemas(schemaDir);
	const files = input && input.files ? input.files : null;
	const sources = input ? input.sources : null;

	// §5.1/§5.2 — the files set itself must be exactly the 5 expected keys,
	// fail-closed and REPORTED (never a thrown TypeError) on anything else.
	if (!files || 'object' !== typeof files || Array.isArray(files)) {
		errors.push('files: bir obje olmalı (relPath -> manifest değeri).');
		return { errors: errors };
	}
	const actualFileKeys = Object.keys(files);
	actualFileKeys.forEach(function (k) {
		if (EXPECTED_FILE_KEYS.indexOf(k) === -1) {
			errors.push('files: beklenmeyen manifest dosya anahtarı ("' + k + '").');
		}
	});
	const missingFileKeys = EXPECTED_FILE_KEYS.filter(function (k) { return actualFileKeys.indexOf(k) === -1; });
	missingFileKeys.forEach(function (k) {
		errors.push('files: zorunlu manifest dosya anahtarı eksik ("' + k + '").');
	});
	if (missingFileKeys.length > 0) {
		// Every other check below assumes all 5 files exist as objects —
		// stop here with a clear, already-reported error instead of a
		// TypeError from reading a property of undefined.
		return { errors: errors };
	}

	const sectorFile = files['content/sectors.manifest.json'];
	const qualificationFile = files['content/qualifications.manifest.json'];
	const feeFile = files['content/fees.manifest.json'];
	const mappingFile = files['mapping/mapping.manifest.json'];
	const unmatchedFile = files['mapping/unmatched-fees.manifest.json'];

	if (!isPlainObjectForValidation(sectorFile) || !isPlainObjectForValidation(qualificationFile) ||
		!isPlainObjectForValidation(feeFile) || !isPlainObjectForValidation(mappingFile) || !isPlainObjectForValidation(unmatchedFile)) {
		errors.push('files: her manifest değeri bir obje olmalı (bir tanesi obje değil).');
		return { errors: errors };
	}

	// --- per-record schema validation ---
	// Array.isArray guards: a malformed fixture (wrong list key, list
	// replaced by a scalar, ...) must produce a normal validation ERROR
	// here, never a thrown TypeError that skips straight past this
	// function's caller — checkEnvelope() below still runs and reports
	// the actual key-name problem in full.
	if (Array.isArray(sectorFile.records)) {
		sectorFile.records.forEach(function (r, i) {
			errors.push.apply(errors, validateAgainstSchema(r, schemas.sector).map(function (e) { return 'sectors[' + i + ']' + e.slice(1); }));
		});
	}
	if (Array.isArray(qualificationFile.records)) {
		qualificationFile.records.forEach(function (r, i) {
			errors.push.apply(errors, validateAgainstSchema(r, schemas.qualification).map(function (e) { return 'qualifications[' + i + ']' + e.slice(1); }));
		});
	}
	if (Array.isArray(feeFile.records)) {
		feeFile.records.forEach(function (r, i) {
			errors.push.apply(errors, validateAgainstSchema(r, schemas.fee).map(function (e) { return 'fees[' + i + ']' + e.slice(1); }));
		});
	}
	if (Array.isArray(mappingFile.rows)) {
		mappingFile.rows.forEach(function (r, i) {
			errors.push.apply(errors, validateAgainstSchema(r, schemas.mappingRow).map(function (e) { return 'mapping.rows[' + i + ']' + e.slice(1); }));
		});
	}
	if (Array.isArray(unmatchedFile.records)) {
		unmatchedFile.records.forEach(function (r, i) {
			errors.push.apply(errors, validateAgainstSchema(r, schemas.unmatchedFee).map(function (e) { return 'unmatched[' + i + ']' + e.slice(1); }));
		});
	}

	// --- envelope shape + exact top-level key set + count consistency + record_type match, all 5 files ---
	checkEnvelope('content/sectors.manifest.json', sectorFile, schemas.envelope, errors);
	checkEnvelope('content/qualifications.manifest.json', qualificationFile, schemas.envelope, errors);
	checkEnvelope('content/fees.manifest.json', feeFile, schemas.envelope, errors);
	checkEnvelope('mapping/mapping.manifest.json', mappingFile, schemas.envelope, errors);
	checkEnvelope('mapping/unmatched-fees.manifest.json', unmatchedFile, schemas.envelope, errors);

	// --- cross-field checks below all assume real arrays; a missing/wrong-typed
	// list already produced a clear error above (per-record + envelope) —
	// skip cross-field/full-comparison entirely rather than throw on it twice.
	if (!Array.isArray(qualificationFile.records) || !Array.isArray(feeFile.records) || !Array.isArray(sectorFile.records) ||
		!Array.isArray(mappingFile.rows) || !Array.isArray(unmatchedFile.records)) {
		return { errors: errors };
	}

	// §5 (Faz 6A Tam Kapsam ve Sayaç Kapanışı) — `sources` fail-closed
	// şekil koruması. Geçersizse (yok/null/dizi, alt-alanları eksik/null/
	// dizi-dışı, `data` dizi değil, `repoRelativePath`/`sha256` eksik/
	// yanlış tip/biçim) TEK bir açık hata basıp kaynakla ilgili TÜM
	// karşılaştırmaları atlıyoruz — yüzlerce ikincil hata veya kontrolsüz
	// `TypeError` üretmek yerine.
	const sourcesUsable = validateSourcesShape(sources, errors);

	// §6 — zarf `source` alanı doğrudan gerçek çıkarım meta verisiyle
	// karşılaştırılıyor (kayıt dizileri üzerinden DOLAYLI değil).
	if (sourcesUsable) {
		[
			['content/sectors.manifest.json', sectorFile.source, sources.sectors],
			['content/qualifications.manifest.json', qualificationFile.source, sources.qualifications],
			['content/fees.manifest.json', feeFile.source, sources.fees],
		].forEach(function (entry) {
			const label = entry[0];
			const envelopeSource = entry[1];
			const extractionMeta = entry[2];
			if (!isPlainObjectForValidation(envelopeSource)) {
				return; // already reported by checkEnvelope()'s checkSourceShape()
			}
			if (envelopeSource.file !== extractionMeta.repoRelativePath) {
				errors.push(label + ': zarf source.file gerçek çıkarım meta verisiyle (' + extractionMeta.repoRelativePath + ') uyuşmuyor.');
			}
			if (envelopeSource.sha256 !== extractionMeta.sha256) {
				errors.push(label + ': zarf source.sha256 gerçek çıkarım meta verisiyle uyuşmuyor.');
			}
		});
	}

	// §3.1 — SEKTÖR: (a) her zaman: source_key formülü (kayıt-içi, kaynak
	// gerekmez), benzersizlik/0..n-1 tam kapsam, `EXPECTED.sectors` sabiti;
	// (b) yalnız sources kullanılabilirse: kaynakla TAM alan-alan
	// karşılaştırması + kayıt sayısı === kaynak satır sayısı + kayıt-içi
	// kaynak meta verisinin çıkarım tarafı.
	const sectorBySlug = {};
	sectorFile.records.forEach(function (r) {
		sectorBySlug[r.slug] = r;
		if ('sector:' + r.slug !== r.source_key) {
			errors.push('sectors[' + r.source_index + ']: source_key ("' + r.source_key + '") "sector:" + slug formülüyle ("sector:' + r.slug + '") uyuşmuyor.');
		}
		checkRecordSourceMeta('sectors[' + r.source_index + ']', r.source, null, sectorFile.source, errors);
		if (sourcesUsable) {
			const src = sources.sectors.data[r.source_index];
			if (!src) {
				errors.push('sectors[' + r.source_index + ']: kaynakta karşılık gelen satır yok (source_index geçersiz).');
				return;
			}
			if (src.slug !== r.slug) { errors.push('sectors[' + r.source_index + ']: slug kaynakla uyuşmuyor.'); }
			if ((src.name || '') !== r.name) { errors.push('sectors[' + r.source_index + ']: name kaynakla uyuşmuyor.'); }
			if ((src.desc || '') !== r.description) { errors.push('sectors[' + r.source_index + ']: description kaynağın desc alanıyla uyuşmuyor.'); }
			if ((src.icon || '') !== r.icon) { errors.push('sectors[' + r.source_index + ']: icon kaynakla uyuşmuyor.'); }
			if ((src.image || '') !== r.image) { errors.push('sectors[' + r.source_index + ']: image kaynakla uyuşmuyor.'); }
			checkRecordSourceMeta('sectors[' + r.source_index + ']', r.source, sources.sectors, null, errors);
		}
	});
	checkUnique(sectorFile.records.map(function (r) { return r.source_index; }), 'sectors.source_index', errors);
	checkUnique(sectorFile.records.map(function (r) { return r.slug; }), 'sectors.slug', errors);
	checkUnique(sectorFile.records.map(function (r) { return r.source_key; }), 'sectors.source_key', errors);
	if (!isCompleteZeroBasedIndexSet(sectorFile.records.map(function (r) { return r.source_index; }))) {
		errors.push('sectors: source_index kümesi 0..n-1 aralığını tam kapsamıyor.');
	}
	if (sectorFile.records.length !== EXPECTED.sectors) {
		errors.push('sectors: kayıt sayısı (' + sectorFile.records.length + ') doğrulanmış sabit sayıyla (' + EXPECTED.sectors + ') uyuşmuyor.');
	}
	if (sourcesUsable && sectorFile.records.length !== sources.sectors.data.length) {
		errors.push('sectors: kayıt sayısı (' + sectorFile.records.length + ') kaynak satır sayısıyla (' + sources.sectors.data.length + ') uyuşmuyor — kaynakta karşılığı olmayan bir manifest kaydı VEYA manifestte hiç görünmeyen bir kaynak satırı olabilir.');
	}
	// §6 — sektör "notes" alanı, kayıt listesinden BAĞIMSIZ yeniden
	// türetilip birebir karşılaştırılıyor (yalnız `build-manifest.js`'in
	// AYNI paylaşılan `buildSectorNotes()` fonksiyonu — iki ayrı elle
	// yazılmış metin kopyası yok).
	const expectedSectorNotes = buildSectorNotes(sectorBySlug);
	if (!deepEqual(expectedSectorNotes, sectorFile.notes)) {
		errors.push('sectors.notes: yeniden türetilen beklenen değerle uyuşmuyor (keyfi eklenmiş/silinmiş/değiştirilmiş olabilir).');
	}

	// §3.2 — YETERLİLİK: aynı ayrım — (a) her zaman kod-türetimi/formül/
	// sabitler, (b) yalnız sources varsa kaynak karşılaştırması+kardinalite.
	const sectorSlugSet = {};
	sectorFile.records.forEach(function (r) { sectorSlugSet[r.slug] = true; });

	let qualificationFormatGapCount = 0;
	const qualificationBySourceKey = {};
	qualificationFile.records.forEach(function (r) {
		qualificationBySourceKey[r.source_key] = r;

		if ('qualification:' + r.code !== r.source_key) {
			errors.push('qualifications[' + r.source_index + ']: source_key ("' + r.source_key + '") "qualification:" + code formülüyle uyuşmuyor.');
		}
		if (!Object.prototype.hasOwnProperty.call(sectorSlugSet, r.sector_slug)) {
			errors.push('qualifications[' + r.source_index + ']: sector_slug ("' + r.sector_slug + '") gerçek sektör manifestinde yok.');
		}
		if ('active' !== r.planned_record_status) {
			errors.push('qualifications[' + r.source_index + ']: planned_record_status builder\'ın bağlayıcı sabit değeri ("active") ile uyuşmuyor.');
		}
		checkRecordSourceMeta('qualifications[' + r.source_index + ']', r.source, null, qualificationFile.source, errors);

		const parsed = parseMykCode(r.code);
		if (!parsed.valid) {
			errors.push('qualifications[' + r.source_index + ']: MYK kodu (' + r.code + ') çapraz-kontrol ayrıştırmasında geçersiz.');
		} else {
			if (Number(parsed.level_from_code) !== r.level) {
				errors.push('qualifications[' + r.source_index + ']: kod içine gömülü seviye (' + parsed.level_from_code + ') kayıt alanı level (' + r.level + ') ile uyuşmuyor.');
			}
			if (parsed.has_revision !== r.has_revision) {
				errors.push('qualifications[' + r.source_index + ']: has_revision alanı kodun gerçek biçimiyle uyuşmuyor.');
			}
			if (parsed.has_revision && parsed.revision !== r.revision) {
				errors.push('qualifications[' + r.source_index + ']: kod içine gömülü revizyon (' + parsed.revision + ') kayıt alanı revision (' + r.revision + ') ile uyuşmuyor.');
			}
			if (!parsed.has_revision && '' !== r.revision) {
				errors.push('qualifications[' + r.source_index + ']: revizyon eki yok ama revision alanı boş bırakılmamış ("' + r.revision + '").');
			}
		}
		const expectedLegacyFlag = /^[0-9]{2}UY[0-9]{4}-[0-9]{1,2}\/[0-9]{2}$/.test(r.code);
		if (expectedLegacyFlag !== r.matches_legacy_revision_required_format) {
			errors.push('qualifications[' + r.source_index + ']: matches_legacy_revision_required_format, koddan yeniden hesaplanan değerle uyuşmuyor.');
		}
		if (!expectedLegacyFlag) {
			qualificationFormatGapCount++;
		}

		if (sourcesUsable) {
			const src = sources.qualifications.data[r.source_index];
			if (!src) {
				errors.push('qualifications[' + r.source_index + ']: kaynakta karşılık gelen satır yok (source_index geçersiz).');
				return;
			}
			if (src.code !== r.code) { errors.push('qualifications[' + r.source_index + ']: code kaynakla uyuşmuyor.'); }
			if ((src.name || '') !== r.name) { errors.push('qualifications[' + r.source_index + ']: name kaynakla uyuşmuyor.'); }
			if (src.level !== r.level) { errors.push('qualifications[' + r.source_index + ']: level kaynakla uyuşmuyor.'); }
			if (src.sector !== r.sector_slug) { errors.push('qualifications[' + r.source_index + ']: sector_slug kaynakla uyuşmuyor.'); }
			checkRecordSourceMeta('qualifications[' + r.source_index + ']', r.source, sources.qualifications, null, errors);
		}
	});
	checkUnique(qualificationFile.records.map(function (r) { return r.source_index; }), 'qualifications.source_index', errors);
	checkUnique(qualificationFile.records.map(function (r) { return r.code; }), 'qualifications.code', errors);
	checkUnique(qualificationFile.records.map(function (r) { return r.source_key; }), 'qualifications.source_key', errors);
	if (!isCompleteZeroBasedIndexSet(qualificationFile.records.map(function (r) { return r.source_index; }))) {
		errors.push('qualifications: source_index kümesi 0..n-1 aralığını tam kapsamıyor.');
	}
	if (qualificationFile.records.length !== EXPECTED.qualifications) {
		errors.push('qualifications: kayıt sayısı (' + qualificationFile.records.length + ') doğrulanmış sabit sayıyla (' + EXPECTED.qualifications + ') uyuşmuyor.');
	}
	if (sourcesUsable && qualificationFile.records.length !== sources.qualifications.data.length) {
		errors.push('qualifications: kayıt sayısı (' + qualificationFile.records.length + ') kaynak satır sayısıyla (' + sources.qualifications.data.length + ') uyuşmuyor.');
	}
	const expectedQualificationNotes = buildQualificationNotes(qualificationFormatGapCount);
	if (!deepEqual(expectedQualificationNotes, qualificationFile.notes)) {
		errors.push('qualifications.notes: yeniden türetilen beklenen değerle uyuşmuyor (keyfi eklenmiş/silinmiş/değiştirilmiş olabilir).');
	}

	// §3.3 — ÜCRET: aynı ayrım. Ayrıca §4 — 8 sayacın TAMAMI feeFile.records
	// üzerinden BAĞIMSIZ yeniden hesaplanıp counts ile birebir karşılaştırılıyor.
	let recomputedPriceOptionsTotal = 0;
	let recomputedPricingSingle = 0;
	let recomputedPricingMulti = 0;
	let recomputedMultiOptionsTotal = 0;
	let recomputedFeesWithCode = 0;
	let recomputedFeesWithoutCode = 0;
	let recomputedLinkedCount = 0;

	feeFile.records.forEach(function (fee) {
		const expectedSourceKey = 'fee:' + fee.sector_slug + ':' + fee.level + ':' + slugify(fee.profession_name);
		if (expectedSourceKey !== fee.source_key) {
			errors.push('fees[' + fee.source_index + ']: source_key ("' + fee.source_key + '") kararlı formülle ("' + expectedSourceKey + '") uyuşmuyor.');
		}
		if (!Object.prototype.hasOwnProperty.call(sectorSlugSet, fee.sector_slug)) {
			errors.push('fees[' + fee.source_index + ']: sector_slug ("' + fee.sector_slug + '") gerçek sektör manifestinde yok.');
		}

		// §4 sayaç muhasebesi — her kayıt tam olarak bir kez sayılır.
		if (Array.isArray(fee.price_options)) {
			recomputedPriceOptionsTotal += fee.price_options.length;
		}
		if ('single' === fee.pricing_type) {
			recomputedPricingSingle++;
		} else {
			recomputedPricingMulti++;
			if (Array.isArray(fee.price_options)) {
				recomputedMultiOptionsTotal += fee.price_options.length;
			}
		}
		if ('' === fee.qualification_code) {
			recomputedFeesWithoutCode++;
			if (null !== fee.qualification_source_key) {
				errors.push('fees[' + fee.source_index + ']: qualification_code boş olmasına rağmen qualification_source_key null DEĞİL.');
			}
		} else {
			recomputedFeesWithCode++;
			if (null === fee.qualification_source_key) {
				errors.push('fees[' + fee.source_index + ']: qualification_code dolu olmasına rağmen qualification_source_key null.');
			} else {
				const target = qualificationBySourceKey[fee.qualification_source_key];
				if (!target) {
					errors.push('fees[' + fee.source_index + ']: qualification_source_key (' + fee.qualification_source_key + ') gerçek bir yeterlilik kaydına işaret etmiyor.');
				} else {
					if (target.code !== fee.qualification_code) {
						errors.push('fees[' + fee.source_index + ']: qualification_source_key, qualification_code (' + fee.qualification_code + ') ile TAM eşleşen kodu göstermiyor.');
					}
					if (target.level !== fee.level) {
						errors.push('fees[' + fee.source_index + ']: ücret seviyesi (' + fee.level + ') bağlı yeterliliğin seviyesiyle (' + target.level + ') uyuşmuyor.');
					}
					if (target.sector_slug !== fee.sector_slug) {
						errors.push('fees[' + fee.source_index + ']: ücret sektörü (' + fee.sector_slug + ') bağlı yeterliliğin sektörüyle (' + target.sector_slug + ') uyuşmuyor.');
					}
				}
			}
		}
		if (null !== fee.qualification_source_key) {
			recomputedLinkedCount++;
		}

		Object.keys(FEE_PLANNED_CONSTANTS).forEach(function (key) {
			if (fee[key] !== FEE_PLANNED_CONSTANTS[key]) {
				errors.push('fees[' + fee.source_index + ']: ' + key + ' builder\'ın bağlayıcı sabit değeriyle (' + JSON.stringify(FEE_PLANNED_CONSTANTS[key]) + ') uyuşmuyor (bulunan: ' + JSON.stringify(fee[key]) + ').');
			}
		});
		checkRecordSourceMeta('fees[' + fee.source_index + ']', fee.source, null, feeFile.source, errors);

		if (Array.isArray(fee.price_options)) {
			fee.price_options.forEach(function (opt, i) {
				if (opt.sort_order !== i) {
					errors.push('fees[' + fee.source_index + '].price_options[' + i + ']: sort_order (' + opt.sort_order + ') dizin sırasıyla (' + i + ') uyuşmuyor — eksik/fazla/yeniden sıralanmış seçenek olabilir.');
				}
			});
			if (fee.price_options.length > 0) {
				const amounts = fee.price_options.map(function (o) { return o.amount_kurus; });
				const recomputedMin = Math.min.apply(null, amounts);
				const recomputedMax = Math.max.apply(null, amounts);
				if (fee.min_amount_kurus !== recomputedMin) {
					errors.push('fees[' + fee.source_index + ']: min_amount_kurus (' + fee.min_amount_kurus + ') fiyat seçeneklerinden yeniden hesaplananla (' + recomputedMin + ') uyuşmuyor.');
				}
				if (fee.max_amount_kurus !== recomputedMax) {
					errors.push('fees[' + fee.source_index + ']: max_amount_kurus (' + fee.max_amount_kurus + ') fiyat seçeneklerinden yeniden hesaplananla (' + recomputedMax + ') uyuşmuyor.');
				}
				if (fee.min_amount_kurus > fee.max_amount_kurus) {
					errors.push('fees[' + fee.source_index + ']: min_amount_kurus max_amount_kurus\'tan büyük.');
				}
			}
		}

		if (sourcesUsable) {
			const src = sources.fees.data[fee.source_index];
			if (!src) {
				errors.push('fees[' + fee.source_index + ']: kaynakta karşılık gelen satır yok (source_index geçersiz).');
				return;
			}
			if ((src.name || '') !== fee.profession_name) { errors.push('fees[' + fee.source_index + ']: profession_name kaynakla uyuşmuyor.'); }
			if (src.level !== fee.level) { errors.push('fees[' + fee.source_index + ']: level kaynakla uyuşmuyor.'); }
			if (src.sector !== fee.sector_slug) { errors.push('fees[' + fee.source_index + ']: sector_slug kaynakla uyuşmuyor.'); }
			if ((src.qualificationCode || '') !== fee.qualification_code) { errors.push('fees[' + fee.source_index + ']: qualification_code kaynakla uyuşmuyor.'); }
			if ((src.pricingType || '') !== fee.pricing_type) { errors.push('fees[' + fee.source_index + ']: pricing_type kaynakla uyuşmuyor.'); }
			if ((true === src.vatIncluded) !== fee.vat_included) { errors.push('fees[' + fee.source_index + ']: vat_included kaynakla uyuşmuyor.'); }
			if (src.certificatePrintFeeExcluded * 100 !== fee.certificate_print_fee_kurus) { errors.push('fees[' + fee.source_index + ']: certificate_print_fee_kurus kaynaktan (x100) sapıyor.'); }
			if ((src.source || '') !== fee.source_name) { errors.push('fees[' + fee.source_index + ']: source_name kaynakla uyuşmuyor.'); }
			if ((typeof src.sourcePage === 'number' ? src.sourcePage : null) !== fee.source_page) { errors.push('fees[' + fee.source_index + ']: source_page kaynakla uyuşmuyor.'); }
			checkRecordSourceMeta('fees[' + fee.source_index + ']', fee.source, sources.fees, null, errors);

			if (!Array.isArray(src.options) || !Array.isArray(fee.price_options) || src.options.length !== fee.price_options.length) {
				errors.push('fees[' + fee.source_index + ']: price_options sayısı kaynakla uyuşmuyor.');
			} else {
				src.options.forEach(function (srcOpt, i) {
					const built = fee.price_options[i];
					if (!built) {
						return; // length mismatch already reported above
					}
					if ((srcOpt.label || '').trim() !== built.label) { errors.push('fees[' + fee.source_index + '].price_options[' + i + ']: label kaynakla uyuşmuyor.'); }
					if (srcOpt.amount * 100 !== built.amount_kurus) { errors.push('fees[' + fee.source_index + '].price_options[' + i + ']: amount_kurus kaynaktan (x100) sapıyor.'); }
					const srcUnits = Array.isArray(srcOpt.units) ? srcOpt.units : [];
					if (JSON.stringify(srcUnits) !== JSON.stringify(built.units)) { errors.push('fees[' + fee.source_index + '].price_options[' + i + ']: units kaynakla uyuşmuyor.'); }
				});
			}
		}
	});
	checkUnique(feeFile.records.map(function (r) { return r.source_index; }), 'fees.source_index', errors);
	checkUnique(feeFile.records.map(function (r) { return r.source_key; }), 'fees.source_key', errors);
	if (!isCompleteZeroBasedIndexSet(feeFile.records.map(function (r) { return r.source_index; }))) {
		errors.push('fees: source_index kümesi 0..n-1 aralığını tam kapsamıyor.');
	}
	if (feeFile.records.length !== EXPECTED.fees) {
		errors.push('fees: kayıt sayısı (' + feeFile.records.length + ') doğrulanmış sabit sayıyla (' + EXPECTED.fees + ') uyuşmuyor.');
	}
	if (sourcesUsable && feeFile.records.length !== sources.fees.data.length) {
		errors.push('fees: kayıt sayısı (' + feeFile.records.length + ') kaynak satır sayısıyla (' + sources.fees.data.length + ') uyuşmuyor.');
	}

	// §4 — 8 sayacın TAMAMI: yalnız tip/`counts.total===count` değil, HER
	// sayaç feeFile.records'tan bağımsız yeniden hesaplanıp karşılaştırılıyor.
	if (isPlainObjectForValidation(feeFile.counts)) {
		const recomputedCounts = {
			total: feeFile.records.length,
			priceOptionsTotal: recomputedPriceOptionsTotal,
			pricingSingle: recomputedPricingSingle,
			pricingMulti: recomputedPricingMulti,
			multiOptionsTotal: recomputedMultiOptionsTotal,
			feesWithCode: recomputedFeesWithCode,
			feesWithoutCode: recomputedFeesWithoutCode,
			linkedCount: recomputedLinkedCount,
		};
		Object.keys(recomputedCounts).forEach(function (key) {
			if (feeFile.counts[key] !== recomputedCounts[key]) {
				errors.push('fees.counts.' + key + ' (' + feeFile.counts[key] + ') feeFile.records\'tan bağımsız yeniden hesaplanan değerle (' + recomputedCounts[key] + ') uyuşmuyor.');
			}
		});
		if (recomputedCounts.pricingSingle + recomputedCounts.pricingMulti !== recomputedCounts.total) {
			errors.push('fees.counts: pricingSingle+pricingMulti toplamı total ile uyuşmuyor (dahili tutarsızlık).');
		}
		if (recomputedCounts.feesWithCode + recomputedCounts.feesWithoutCode !== recomputedCounts.total) {
			errors.push('fees.counts: feesWithCode+feesWithoutCode toplamı total ile uyuşmuyor (dahili tutarsızlık).');
		}
		Object.keys(EXPECTED).forEach(function (key) {
			if (Object.prototype.hasOwnProperty.call(recomputedCounts, key) && recomputedCounts[key] !== EXPECTED[key]) {
				errors.push('fees.counts.' + key + ': yeniden hesaplanan değer (' + recomputedCounts[key] + ') doğrulanmış sabit sayıyla (' + EXPECTED[key] + ') uyuşmuyor.');
			}
		});
	}

	// §4.1 — MAPPING: içerik manifestlerinden BAĞIMSIZ olarak yeniden
	// türetilen tam satır listesiyle (sıra dahil) birebir karşılaştırma —
	// yalnız satır sayısı değil, HER satırın HER alanı.
	const expectedMappingRows = [];
	sectorFile.records.forEach(function (r) {
		expectedMappingRows.push({
			type: 'sector', source_index: r.source_index, source_key: r.source_key,
			natural_key: r.slug, resolution: 'mapped', target_source_key: null, validation_status: 'valid',
		});
	});
	qualificationFile.records.forEach(function (r) {
		expectedMappingRows.push({
			type: 'qualification', source_index: r.source_index, source_key: r.source_key,
			natural_key: r.code, resolution: 'mapped', target_source_key: 'sector:' + r.sector_slug, validation_status: 'valid',
		});
	});
	feeFile.records.forEach(function (r) {
		expectedMappingRows.push({
			type: 'fee', source_index: r.source_index, source_key: r.source_key,
			natural_key: r.profession_name + '|' + r.level + '|' + r.sector_slug,
			resolution: r.qualification_source_key ? 'mapped' : 'unmatched',
			target_source_key: r.qualification_source_key, validation_status: 'valid',
		});
	});
	if (mappingFile.rows.length !== expectedMappingRows.length) {
		errors.push('mapping.rows: uzunluk (' + mappingFile.rows.length + ') içerik manifestlerinden bağımsız türetilen beklenen uzunlukla (' + expectedMappingRows.length + ') uyuşmuyor.');
	} else {
		expectedMappingRows.forEach(function (expected, i) {
			if (!deepEqual(expected, mappingFile.rows[i])) {
				errors.push('mapping.rows[' + i + ']: içerik manifestlerinden bağımsız türetilen beklenen satırla uyuşmuyor (eksik/fazla/yeniden sıralanmış/değiştirilmiş alan).');
			}
		});
	}

	// §4.2 — UNMATCHED: feeFile'dan BAĞIMSIZ türetilen tam ve birebir izdüşüm.
	const expectedUnmatched = feeFile.records
		.filter(function (r) { return null === r.qualification_source_key; })
		.map(function (r) {
			return {
				source_index: r.source_index, source_key: r.source_key, profession_name: r.profession_name,
				level: r.level, sector_slug: r.sector_slug, note: UNMATCHED_FEE_NOTE,
			};
		});
	if (unmatchedFile.records.length !== expectedUnmatched.length) {
		errors.push('unmatched.records: uzunluk (' + unmatchedFile.records.length + ') fees.manifest.json\'dan bağımsız türetilen beklenen uzunlukla (' + expectedUnmatched.length + ') uyuşmuyor.');
	} else {
		expectedUnmatched.forEach(function (expected, i) {
			if (!deepEqual(expected, unmatchedFile.records[i])) {
				errors.push('unmatched.records[' + i + ']: fees.manifest.json\'dan bağımsız türetilen beklenen kayıtla uyuşmuyor (eksik/fazla/bağlı-ücret/değiştirilmiş alan).');
			}
		});
	}

	return { errors: errors };
}

/** Cross-checks a record's own embedded `source` against BOTH the raw extraction meta (sources.<type>) AND the file's own envelope-level `source` — all three must describe the exact same input file. */
function checkRecordSourceMeta(label, recordSource, extractionMeta, envelopeSource, errors) {
	if (!isPlainObjectForValidation(recordSource)) {
		errors.push(label + ': "source" bir obje olmalı.');
		return;
	}
	if (extractionMeta) {
		if (recordSource.file !== extractionMeta.repoRelativePath) {
			errors.push(label + ': source.file gerçek çıkarım meta verisiyle uyuşmuyor.');
		}
		if (recordSource.sha256 !== extractionMeta.sha256) {
			errors.push(label + ': source.sha256 gerçek çıkarım meta verisiyle uyuşmuyor.');
		}
	}
	if (envelopeSource) {
		if (recordSource.file !== envelopeSource.file) {
			errors.push(label + ': source.file dosyanın kendi zarf "source" alanıyla uyuşmuyor.');
		}
		if (recordSource.sha256 !== envelopeSource.sha256) {
			errors.push(label + ': source.sha256 dosyanın kendi zarf "source" alanıyla uyuşmuyor.');
		}
	}
}

module.exports = { validateManifestSet, loadAllSchemas, ENVELOPE_CONTRACTS, validateSourcesShape };
