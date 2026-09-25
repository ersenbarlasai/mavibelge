'use strict';
/**
 * Faz 6A — PRODUCTION mode: builds the normalized catalog manifest from
 * the three frozen tanitim-site source files. Writes JSON only under
 * wordpress-site/data/**. Never touches WordPress, never makes a
 * network request, never writes outside this repo.
 *
 * Run: node wordpress-site/tools/import/build-manifest.js
 *
 * Two SEPARATE guarantees exist here — do not conflate them:
 *
 * 1. PRE-VALIDATION failure (a count that doesn't match the documented
 *    14/83/103/145/87/16/58/84/19, a duplicate key, an unresolved
 *    sector reference, a schema/cross-field violation caught by
 *    lib/validate-manifest-set.js, ...): this exits 1 and writes
 *    NOTHING AT ALL — the writer (writeAllAtomic()) is never even
 *    called (see runBuild() below). This IS an absolute guarantee.
 *
 * 2. A write-time I/O failure AFTER validation already passed (disk
 *    full, permission change mid-run, a rename that fails partway
 *    through the 5 files): this is NOT an absolute guarantee — see
 *    writeAllAtomic()'s own docblock below for the honest, narrower
 *    claim ("per-file atomic, set-level best-effort") and
 *    test-manifest-validation.js's rename-mid-failure test for proof
 *    that a mixed old/new final-file set can genuinely occur here.
 */

const fs = require('fs');
const path = require('path');

const { extractAll, REPO_ROOT } = require('./extract-source');
const { sha256Hex, toDeterministicJson } = require('./lib/hash');
const { parseMykCode, matchesLegacyRevisionRequiredFormat } = require('./lib/myk-code');
const { liraIntToKurus } = require('./lib/money');
const { buildCanonicalPriceOptions } = require('./lib/price-options');
const { slugify } = require('./lib/slug');
const { validateManifestSet } = require('./lib/validate-manifest-set');
const { EXPECTED } = require('./lib/expected-counts');
const { buildSectorNotes, buildQualificationNotes } = require('./lib/manifest-notes');

const SCHEMA_VERSION = '2.0.0';

const DATA_DIR = path.join(REPO_ROOT, 'wordpress-site', 'data');
const CONTENT_DIR = path.join(DATA_DIR, 'content');
const MAPPING_DIR = path.join(DATA_DIR, 'mapping');
const SCHEMA_DIR = path.join(DATA_DIR, 'schema');

/** Reports failure to stderr only — does NOT call process.exit(), so runBuild() stays testable (injectable) without killing the test process. Only main() (the real CLI entry point) turns a failed runBuild() into a non-zero process exit. */
function fail(errors) {
	process.stderr.write('\nManifest üretimi BAŞARISIZ — ' + errors.length + ' hata:\n');
	errors.forEach(function (e) {
		process.stderr.write('  - ' + e + '\n');
	});
	process.stderr.write('\nHiçbir dosya yazılmadı (tümü-ya-da-hiçbiri).\n');
}

function buildSectorManifest(sourceSectors, sourceMeta) {
	const errors = [];
	const bySlug = {};
	const records = [];

	sourceSectors.data.forEach(function (row, index) {
		const slug = typeof row.slug === 'string' ? row.slug : '';
		const sourceKey = 'sector:' + slug;

		if ('' === slug || !/^[a-z0-9]+(-[a-z0-9]+)*$/.test(slug)) {
			errors.push('sectors[' + index + ']: slug biçimi geçersiz ("' + slug + '").');
			return;
		}
		if (Object.prototype.hasOwnProperty.call(bySlug, slug)) {
			errors.push('sectors[' + index + ']: slug tekrarlanıyor ("' + slug + '").');
			return;
		}

		const record = {
			schema_version: SCHEMA_VERSION,
			source_key: sourceKey,
			source_index: index,
			slug: slug,
			name: typeof row.name === 'string' ? row.name : '',
			description: typeof row.desc === 'string' ? row.desc : '',
			icon: typeof row.icon === 'string' ? row.icon : '',
			image: typeof row.image === 'string' ? row.image : '',
			source: { file: sourceMeta.repoRelativePath, sha256: sourceMeta.sha256 },
		};
		bySlug[slug] = record;
		records.push(record);
	});

	if (records.length !== EXPECTED.sectors) {
		errors.push('sectors: beklenen ' + EXPECTED.sectors + ' kayıt, gerçek ' + records.length + '.');
	}

	return { errors: errors, records: records, bySlug: bySlug };
}

function buildQualificationManifest(sourceQualifications, sourceMeta, sectorBySlug) {
	const errors = [];
	const byCode = {};
	const comboSeen = {};
	const records = [];
	let formatGapCount = 0;

	sourceQualifications.data.forEach(function (row, index) {
		const code = typeof row.code === 'string' ? row.code : '';
		const parsed = parseMykCode(code);
		if (!parsed.valid) {
			errors.push('qualifications[' + index + ']: MYK kodu ayrıştırılamadı ("' + code + '").');
			return;
		}
		if (Object.prototype.hasOwnProperty.call(byCode, code)) {
			errors.push('qualifications[' + index + ']: MYK kodu tekrarlanıyor ("' + code + '").');
			return;
		}

		const sectorSlug = typeof row.sector === 'string' ? row.sector : '';
		if (!Object.prototype.hasOwnProperty.call(sectorBySlug, sectorSlug)) {
			errors.push('qualifications[' + index + ']: sector_slug ("' + sectorSlug + '") gerçek sektör manifestinde yok (kod ' + code + ').');
			return;
		}

		const level = typeof row.level === 'number' && Number.isInteger(row.level) ? row.level : null;
		if (null === level || level < 1 || level > 8) {
			errors.push('qualifications[' + index + ']: seviye geçersiz (kod ' + code + ').');
			return;
		}

		const combo = code + '|' + level + '|' + parsed.revision;
		if (Object.prototype.hasOwnProperty.call(comboSeen, combo)) {
			errors.push('qualifications[' + index + ']: code+level+revision üçlüsü tekrarlanıyor (' + combo + ').');
			return;
		}
		comboSeen[combo] = true;

		const matchesCurrentFormat = matchesLegacyRevisionRequiredFormat(code);
		if (!matchesCurrentFormat) {
			formatGapCount++;
		}

		const record = {
			schema_version: SCHEMA_VERSION,
			source_key: 'qualification:' + code,
			source_index: index,
			code: code,
			name: typeof row.name === 'string' ? row.name : '',
			level: level,
			sector_slug: sectorSlug,
			revision: parsed.revision,
			has_revision: parsed.has_revision,
			matches_legacy_revision_required_format: matchesCurrentFormat,
			planned_record_status: 'active',
			source: { file: sourceMeta.repoRelativePath, sha256: sourceMeta.sha256 },
		};
		byCode[code] = record;
		records.push(record);
	});

	if (records.length !== EXPECTED.qualifications) {
		errors.push('qualifications: beklenen ' + EXPECTED.qualifications + ' kayıt, gerçek ' + records.length + '.');
	}

	return { errors: errors, records: records, byCode: byCode, formatGapCount: formatGapCount };
}

function buildFeeManifest(sourceFees, sourceMeta, sectorBySlug, qualificationByCode) {
	const errors = [];
	const bySourceKey = {};
	const records = [];
	const unmatched = [];

	let priceOptionsTotal = 0;
	let pricingSingle = 0;
	let pricingMulti = 0;
	let multiOptionsTotal = 0;
	let feesWithCode = 0;
	let feesWithoutCode = 0;
	let linkedCount = 0;

	const ALLOWED_PRICING_TYPES = ['single', 'unit', 'package', 'multiple'];

	sourceFees.data.forEach(function (row, index) {
		const professionName = typeof row.name === 'string' ? row.name : '';
		const level = typeof row.level === 'number' && Number.isInteger(row.level) ? row.level : null;
		const sectorSlug = typeof row.sector === 'string' ? row.sector : '';
		const qualificationCode = typeof row.qualificationCode === 'string' ? row.qualificationCode : '';
		const pricingType = typeof row.pricingType === 'string' ? row.pricingType : '';

		if (null === level) {
			errors.push('fees[' + index + ']: seviye geçersiz (' + professionName + ').');
			return;
		}
		if (!Object.prototype.hasOwnProperty.call(sectorBySlug, sectorSlug)) {
			errors.push('fees[' + index + ']: sector_slug ("' + sectorSlug + '") gerçek sektör manifestinde yok (' + professionName + ').');
			return;
		}
		if (ALLOWED_PRICING_TYPES.indexOf(pricingType) === -1) {
			errors.push('fees[' + index + ']: pricing_type geçersiz ("' + pricingType + '", ' + professionName + ').');
			return;
		}

		const sourceKey = 'fee:' + sectorSlug + ':' + level + ':' + slugify(professionName);
		if (Object.prototype.hasOwnProperty.call(bySourceKey, sourceKey)) {
			errors.push('fees[' + index + ']: kararlı source_key çakışması ("' + sourceKey + '") — sessiz suffix üretilmedi, iş durduruldu.');
			return;
		}

		const priceResult = buildCanonicalPriceOptions(row.options);
		if (!priceResult.ok) {
			errors.push('fees[' + index + ']: fiyat seçenekleri geçersiz (' + professionName + '): ' + priceResult.errors.join('; '));
			return;
		}

		const certConversion = liraIntToKurus(row.certificatePrintFeeExcluded);
		if (!certConversion.ok) {
			errors.push('fees[' + index + ']: belge basım ücreti geçersiz (' + professionName + '): ' + certConversion.reason);
			return;
		}

		let qualificationSourceKey = null;
		if ('' !== qualificationCode) {
			feesWithCode++;
			const targetQualification = qualificationByCode[qualificationCode];
			if (!targetQualification) {
				errors.push(
					'fees[' + index + ']: qualificationCode ("' + qualificationCode + '") 83 yeterlilik manifestinde TAM eşleşmiyor (' +
						professionName + ') — beklenen 84 dolu-kod sayısı zorla tutturulmadı, gerçek fark raporlanıyor.'
				);
				return;
			}
			qualificationSourceKey = targetQualification.source_key;
			linkedCount++;
		} else {
			feesWithoutCode++;
			unmatched.push({
				source_index: index,
				source_key: sourceKey,
				profession_name: professionName,
				level: level,
				sector_slug: sectorSlug,
				note: 'qualificationCode kaynakta boş bırakılmış (PDF\'de MYK kodu yok); tahmin/fuzzy eşleştirme yapılmadı, qualification_source_key kasıtlı olarak null.',
			});
		}

		if ('single' === pricingType) {
			pricingSingle++;
		} else {
			pricingMulti++;
			multiOptionsTotal += priceResult.options.length;
		}
		priceOptionsTotal += priceResult.options.length;

		const minKurus = priceResult.options.reduce(function (min, o) {
			return null === min ? o.amount_kurus : Math.min(min, o.amount_kurus);
		}, null);
		const maxKurus = priceResult.options.reduce(function (max, o) {
			return null === max ? o.amount_kurus : Math.max(max, o.amount_kurus);
		}, null);

		const record = {
			schema_version: SCHEMA_VERSION,
			source_key: sourceKey,
			source_index: index,
			profession_name: professionName,
			level: level,
			sector_slug: sectorSlug,
			qualification_code: qualificationCode,
			qualification_source_key: qualificationSourceKey,
			pricing_type: pricingType,
			price_options: priceResult.options,
			min_amount_kurus: minKurus,
			max_amount_kurus: maxKurus,
			vat_included: true === row.vatIncluded,
			certificate_print_fee_kurus: certConversion.kurus,
			source_name: typeof row.source === 'string' ? row.source : '',
			source_page: typeof row.sourcePage === 'number' ? row.sourcePage : null,
			source_attachment_id: 0,
			// Faz 6B karar alanları — bkz.
			// raporlar/veri-aktarim-raporlari/faz6a-manifest-sozlesmesi.md
			// "Planlanan alanlar": kaynağın kendi dosya başlığı "2026 sınav
			// ücretleri" der (fees.js satır 1) — bu, TEK doğrudan kaynaktan
			// çıkarılabilir tarife dönemi ipucudur, uydurulmadı.
			// planned_record_status "draft": şemanın mb_ucret için zaten
			// tanımlı varsayılan değeri (class-meta-schema.php) — içe
			// aktarım hiçbir kaydı otomatik "active" yapmaz.
			planned_tariff_period: '2026',
			planned_record_status: 'draft',
			planned_valid_from: '',
			planned_valid_until: '',
			source: { file: sourceMeta.repoRelativePath, sha256: sourceMeta.sha256 },
		};
		bySourceKey[sourceKey] = record;
		records.push(record);
	});

	if (records.length !== EXPECTED.fees) {
		errors.push('fees: beklenen ' + EXPECTED.fees + ' ana kayıt, gerçek ' + records.length + '.');
	}
	if (priceOptionsTotal !== EXPECTED.priceOptionsTotal) {
		errors.push('fees: beklenen ' + EXPECTED.priceOptionsTotal + ' toplam fiyat seçeneği, gerçek ' + priceOptionsTotal + '.');
	}
	if (pricingSingle !== EXPECTED.pricingSingle) {
		errors.push('fees: beklenen ' + EXPECTED.pricingSingle + ' "single" kayıt, gerçek ' + pricingSingle + '.');
	}
	if (pricingMulti !== EXPECTED.pricingMulti) {
		errors.push('fees: beklenen ' + EXPECTED.pricingMulti + ' çok-fiyatlı (unit/package/multiple) kayıt, gerçek ' + pricingMulti + '.');
	}
	if (multiOptionsTotal !== EXPECTED.multiOptionsTotal) {
		errors.push('fees: beklenen ' + EXPECTED.multiOptionsTotal + ' çok-fiyatlı seçenek toplamı, gerçek ' + multiOptionsTotal + '.');
	}
	if (feesWithCode !== EXPECTED.feesWithCode) {
		errors.push('fees: beklenen ' + EXPECTED.feesWithCode + ' dolu qualificationCode, gerçek ' + feesWithCode + '.');
	}
	if (feesWithoutCode !== EXPECTED.feesWithoutCode) {
		errors.push('fees: beklenen ' + EXPECTED.feesWithoutCode + ' boş qualificationCode, gerçek ' + feesWithoutCode + '.');
	}
	if (linkedCount !== EXPECTED.feesWithCode) {
		errors.push('fees: tam kod eşleşmesiyle bağlanan kayıt sayısı (' + linkedCount + ') dolu-kod sayısından (' + feesWithCode + ') farklı.');
	}

	return {
		errors: errors,
		records: records,
		unmatched: unmatched,
		counts: {
			total: records.length,
			priceOptionsTotal: priceOptionsTotal,
			pricingSingle: pricingSingle,
			pricingMulti: pricingMulti,
			multiOptionsTotal: multiOptionsTotal,
			feesWithCode: feesWithCode,
			feesWithoutCode: feesWithoutCode,
			linkedCount: linkedCount,
		},
	};
}

function buildMappingManifest(sectorResult, qualificationResult, feeResult) {
	const rows = [];
	sectorResult.records.forEach(function (r) {
		rows.push({ type: 'sector', source_index: r.source_index, source_key: r.source_key, natural_key: r.slug, resolution: 'mapped', target_source_key: null, validation_status: 'valid' });
	});
	qualificationResult.records.forEach(function (r) {
		rows.push({ type: 'qualification', source_index: r.source_index, source_key: r.source_key, natural_key: r.code, resolution: 'mapped', target_source_key: 'sector:' + r.sector_slug, validation_status: 'valid' });
	});
	feeResult.records.forEach(function (r) {
		rows.push({
			type: 'fee',
			source_index: r.source_index,
			source_key: r.source_key,
			natural_key: r.profession_name + '|' + r.level + '|' + r.sector_slug,
			resolution: r.qualification_source_key ? 'mapped' : 'unmatched',
			target_source_key: r.qualification_source_key,
			validation_status: 'valid',
		});
	});
	return rows;
}

/**
 * Pure computation — extracts sources and builds every manifest file's
 * in-memory value, WITHOUT writing anything to disk. Used by both
 * build-manifest.js (writes the result) and verify-manifest.js
 * (recomputes and diffs against what's on disk, writes nothing).
 *
 * @returns {{errors: string[], files: Object|null, counts: Object|null, unmatchedCount: number|null}}
 */
function computeAll() {
	const sources = extractAll();

	const sectorResult = buildSectorManifest(sources.sectors, sources.sectors);
	const qualificationResult = buildQualificationManifest(sources.qualifications, sources.qualifications, sectorResult.bySlug);
	const feeResult = buildFeeManifest(sources.fees, sources.fees, sectorResult.bySlug, qualificationResult.byCode);

	const allErrors = [].concat(sectorResult.errors, qualificationResult.errors, feeResult.errors);
	if (allErrors.length > 0) {
		return { errors: allErrors, files: null, counts: null, unmatchedCount: null };
	}

	const mappingRows = buildMappingManifest(sectorResult, qualificationResult, feeResult);

	const sectorManifestFile = {
		schema_version: SCHEMA_VERSION,
		record_type: 'sector',
		count: sectorResult.records.length,
		source: { file: sources.sectors.repoRelativePath, sha256: sources.sectors.sha256 },
		notes: buildSectorNotes(sectorResult.bySlug),
		records: sectorResult.records,
	};
	const qualificationManifestFile = {
		schema_version: SCHEMA_VERSION,
		record_type: 'qualification',
		count: qualificationResult.records.length,
		source: { file: sources.qualifications.repoRelativePath, sha256: sources.qualifications.sha256 },
		notes: buildQualificationNotes(qualificationResult.formatGapCount),
		records: qualificationResult.records,
	};
	const feeManifestFile = {
		schema_version: SCHEMA_VERSION,
		record_type: 'fee',
		count: feeResult.records.length,
		counts: feeResult.counts,
		source: { file: sources.fees.repoRelativePath, sha256: sources.fees.sha256 },
		records: feeResult.records,
	};
	const mappingManifestFile = {
		schema_version: SCHEMA_VERSION,
		record_type: 'mapping',
		count: mappingRows.length,
		rows: mappingRows,
	};
	const unmatchedFeesFile = {
		schema_version: SCHEMA_VERSION,
		record_type: 'unmatched_fee',
		count: feeResult.unmatched.length,
		records: feeResult.unmatched,
	};

	const fileSet = {
		'content/sectors.manifest.json': sectorManifestFile,
		'content/qualifications.manifest.json': qualificationManifestFile,
		'content/fees.manifest.json': feeManifestFile,
		'mapping/mapping.manifest.json': mappingManifestFile,
		'mapping/unmatched-fees.manifest.json': unmatchedFeesFile,
	};

	// Faz 6A Güvenlik/Şema/Sözleşme Kapanışı: build ve verify AYNI paylaşılan
	// doğrulayıcıyı kullanır — şema + zarf + çapraz-alan + tam alan
	// karşılaştırması burada da (yazmadan ÖNCE) çalışır, yalnız
	// verify-manifest.js'de değil.
	const sharedValidation = validateManifestSet({ sources: sources, files: fileSet }, SCHEMA_DIR);
	if (sharedValidation.errors.length > 0) {
		return { errors: sharedValidation.errors, files: null, counts: null, unmatchedCount: null };
	}

	return {
		errors: [],
		files: fileSet,
		sources: sources,
		counts: feeResult.counts,
		unmatchedCount: feeResult.unmatched.length,
	};
}

/**
 * Writes every file with PER-FILE atomicity: each is first written to a
 * sibling `.tmp-<random>` path, then renamed into place — this rules out
 * a single JSON file ever being observed half-written (a "torn write").
 *
 * §5.2 (Faz 6A Son Kabul Düzeltmesi) — HONEST scope statement, corrected
 * after independent review: this is NOT a cross-file transaction. If
 * rename #1 succeeds and rename #2 then throws (disk full, permission
 * change mid-run, ...), file #1's NEW content is already live under its
 * final name while the remaining files keep their OLD content — a real,
 * observable MIXED set of final files. This function does not, and does
 * not claim to, roll the earlier renames back. The remaining not-yet-
 * renamed temp files are cleaned up on a best-effort basis and the error
 * is re-thrown; catching a mixed set is `verify-manifest.js`'s job, not
 * this writer's — `disk_matches_recompute` will independently FAIL for
 * every file whose final content doesn't match a fresh recompute,
 * exactly the set-level protection this function itself cannot give.
 *
 * `fsImpl` defaults to the real `fs` module; a test can inject a fake
 * implementation whose `renameSync` throws partway through, without
 * touching any real file on disk.
 */
function writeAllAtomic(dataDir, fileSet, fsImpl) {
	const impl = fsImpl || fs;
	const tmpPaths = [];
	try {
		Object.keys(fileSet).forEach(function (relPath) {
			const absPath = path.join(dataDir, relPath);
			impl.mkdirSync(path.dirname(absPath), { recursive: true });
			const tmpPath = absPath + '.tmp-' + process.pid + '-' + Math.floor(Math.random() * 1e9);
			impl.writeFileSync(tmpPath, toDeterministicJson(fileSet[relPath]), { encoding: 'utf8' });
			tmpPaths.push({ tmpPath: tmpPath, absPath: absPath, renamed: false });
		});
		tmpPaths.forEach(function (entry) {
			impl.renameSync(entry.tmpPath, entry.absPath);
			entry.renamed = true;
		});
	} catch (e) {
		tmpPaths.forEach(function (entry) {
			if (entry.renamed) {
				return; // already live under its final name — not this function's temp file to clean up.
			}
			try {
				if (impl.existsSync(entry.tmpPath)) {
					impl.unlinkSync(entry.tmpPath);
				}
			} catch (cleanupError) {
				// best-effort cleanup only — the original error is what matters
			}
		});
		throw e;
	}
}

/**
 * §5.1 (Faz 6A Son Kabul Düzeltmesi) — the actual "did the writer run"
 * decision, factored out so a test can inject a fake `computeAllFn`
 * (returning a deliberately invalid result) and a spy `writerFn`, and
 * assert the spy was called ZERO times — proving the invalid-result path
 * never reaches disk, instead of a test that only re-checks an already-
 * valid `computeAll()` result (the previous, misleading "12th test").
 *
 * @param {Function} computeAllFn defaults to the real computeAll()
 * @param {Function} writerFn defaults to the real writeAllAtomic()
 * @returns {{wrote: boolean, result: object}}
 */
function runBuild(computeAllFn, writerFn) {
	const compute = computeAllFn || computeAll;
	const writer = writerFn || writeAllAtomic;

	const result = compute();
	if (result.errors.length > 0) {
		fail(result.errors);
		return { wrote: false, result: result };
	}

	writer(DATA_DIR, result.files);

	process.stdout.write('Manifest üretimi tamam.\n');
	process.stdout.write('  sektör: ' + result.files['content/sectors.manifest.json'].count + '\n');
	process.stdout.write('  yeterlilik: ' + result.files['content/qualifications.manifest.json'].count + '\n');
	process.stdout.write('  ücret: ' + result.files['content/fees.manifest.json'].count + ' (fiyat seçeneği toplam: ' + result.counts.priceOptionsTotal + ')\n');
	process.stdout.write('  bağlantısız ücret (qualification_source_key=null): ' + result.unmatchedCount + '\n');
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

module.exports = { main, runBuild, writeAllAtomic, computeAll, EXPECTED, SCHEMA_VERSION, DATA_DIR, SCHEMA_DIR };
