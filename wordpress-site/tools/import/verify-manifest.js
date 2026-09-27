'use strict';
/**
 * Faz 6A §8 — VALIDATION mode: recomputes the manifest purely in memory
 * (no write) and compares it against what is currently on disk under
 * wordpress-site/data/**. Exits non-zero on ANY difference or failed
 * check. Writes nothing except wordpress-site/data/mapping/validation-summary.json
 * (a run REPORT, not content — regenerated on every run; see README's
 * "Çıktılar" section for the same documented exception). Never touches
 * WordPress, never makes a network request.
 *
 * Run: node wordpress-site/tools/import/verify-manifest.js
 */

const fs = require('fs');
const path = require('path');

const { computeAll, EXPECTED, DATA_DIR, SCHEMA_DIR } = require('./build-manifest');
const { toDeterministicJson } = require('./lib/hash');
const { validateAgainstSchema } = require('./lib/mini-schema');
const { validateManifestSet } = require('./lib/validate-manifest-set');
const {
	MAX_PRICE_OPTIONS,
	MAX_OPTION_LABEL_LENGTH,
	MAX_UNITS_PER_OPTION,
	MAX_UNIT_LENGTH,
} = require('./lib/price-options');

const REPORT_PATH = path.join(DATA_DIR, 'mapping', 'validation-summary.json');
const VALIDATION_SUMMARY_SCHEMA_PATH = path.join(SCHEMA_DIR, 'validation-summary.schema.json');

const checks = []; // {id, label, ok, detail}

function record(id, label, ok, detail) {
	checks.push({ id: id, label: label, ok: !!ok, detail: detail === undefined ? '' : String(detail) });
}

function readDiskJson(dataDir, relPath) {
	const abs = path.join(dataDir, relPath);
	if (!fs.existsSync(abs)) {
		return { ok: false, value: null, error: 'dosya yok: ' + relPath };
	}
	const raw = fs.readFileSync(abs, 'utf8');
	try {
		return { ok: true, value: JSON.parse(raw), raw: raw };
	} catch (e) {
		return { ok: false, value: null, error: 'JSON parse hatası (' + relPath + '): ' + e.message };
	}
}

/**
 * §8 (Faz 6A Tam Kapsam ve Sayaç Kapanışı) — the ACTUAL "does disk match a
 * fresh recompute" comparison, factored out of main() so a test can point
 * it at a temp directory holding a genuinely MIXED set (some files fresh,
 * one stale) and prove it gets rejected — instead of only asserting a
 * `count` mismatch inside validateManifestSet() and calling that proof of
 * "the verifier rejects a mixed disk set" (it wasn't — the previous test's
 * name overstated what it showed).
 *
 * @param {Object} freshFiles relPath -> freshly computed manifest value
 * @param {string} dataDir directory to read the on-disk files from
 * @returns {{matches: boolean, mismatchDetails: string[], rawByPath: Object<string,string>}}
 */
function compareFreshToDisk(freshFiles, dataDir) {
	let matches = true;
	const mismatchDetails = [];
	const rawByPath = {};
	Object.keys(freshFiles).forEach(function (relPath) {
		const disk = readDiskJson(dataDir, relPath);
		if (!disk.ok) {
			matches = false;
			mismatchDetails.push(disk.error);
			return;
		}
		rawByPath[relPath] = disk.raw;
		const freshJson = toDeterministicJson(freshFiles[relPath]);
		if (freshJson !== disk.raw) {
			matches = false;
			mismatchDetails.push(relPath + ': disk içeriği yeniden hesaplanan manifestle byte-eşit değil.');
		}
	});
	return { matches: matches, mismatchDetails: mismatchDetails, rawByPath: rawByPath };
}

/** Absolute-path / secret-shaped substring scan over the raw JSON text of every on-disk manifest file. */
function scanForForbiddenContent(relPath, raw) {
	const forbiddenPatterns = [
		{ name: 'Windows sürücü mutlak yolu', re: /[A-Za-z]:\\\\?[A-Za-z0-9_\-\\\/. ]*/ },
		{ name: 'Unix ev dizini mutlak yolu', re: /\/home\/[a-zA-Z0-9_\-]+/ },
		{ name: 'Windows kullanıcı dizini', re: /C:\\\\?Users\\\\?/i },
		{ name: 'e-posta adresi', re: /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/ },
		{ name: 'AWS erişim anahtarı biçimi', re: /AKIA[0-9A-Z]{16}/ },
		{ name: 'generated_at/timestamp benzeri alan adı', re: /"(generated_at|created_at|timestamp|build_time|run_id|uuid)"\s*:/ },
	];
	const hits = [];
	forbiddenPatterns.forEach(function (p) {
		if (p.re.test(raw)) {
			hits.push(p.name);
		}
	});
	return hits;
}

function checkIndexSequence(records, label) {
	const indices = records.map(function (r) {
		return r.source_index;
	});
	const sorted = indices.slice().sort(function (a, b) {
		return a - b;
	});
	let ok = true;
	for (let i = 0; i < sorted.length; i++) {
		if (sorted[i] !== i) {
			ok = false;
			break;
		}
	}
	record('index_sequence_' + label, label + ': source_index 0..n-1 aralığında atlama/tekrar yok', ok, sorted.join(','));
}

function checkPriceOptionIntegrity(feeRecords) {
	let ok = true;
	const problems = [];
	feeRecords.forEach(function (fee) {
		if (!Array.isArray(fee.price_options) || fee.price_options.length === 0 || fee.price_options.length > MAX_PRICE_OPTIONS) {
			ok = false;
			problems.push(fee.source_key + ': seçenek sayısı sınır dışı');
			return;
		}
		fee.price_options.forEach(function (opt, i) {
			if ('string' !== typeof opt.label || '' === opt.label || opt.label.length > MAX_OPTION_LABEL_LENGTH) {
				ok = false;
				problems.push(fee.source_key + '[' + i + ']: label geçersiz');
			}
			if (!Array.isArray(opt.units) || opt.units.length > MAX_UNITS_PER_OPTION) {
				ok = false;
				problems.push(fee.source_key + '[' + i + ']: units geçersiz');
			} else {
				opt.units.forEach(function (u) {
					if ('string' !== typeof u || u.length > MAX_UNIT_LENGTH) {
						ok = false;
						problems.push(fee.source_key + '[' + i + ']: unit geçersiz');
					}
				});
			}
			if (!Number.isInteger(opt.amount_kurus) || opt.amount_kurus <= 0) {
				ok = false;
				problems.push(fee.source_key + '[' + i + ']: amount_kurus geçersiz');
			}
			if (!Number.isInteger(opt.sort_order) || opt.sort_order < 0) {
				ok = false;
				problems.push(fee.source_key + '[' + i + ']: sort_order geçersiz');
			}
		});
	});
	record('price_option_integrity', 'Fiyat seçeneklerinin alan/sınır bütünlüğü', ok, problems.slice(0, 5).join(' | '));
}

function checkMoneyReversibility(feeRecords) {
	let ok = true;
	feeRecords.forEach(function (fee) {
		if (fee.certificate_print_fee_kurus % 100 !== 0) {
			ok = false;
		}
		fee.price_options.forEach(function (opt) {
			if (opt.amount_kurus % 100 !== 0) {
				ok = false;
			}
		});
	});
	record('money_reversible', 'Tüm para dönüşümleri tam sayı ve /100 ile geri çevrilebilir (kayıpsız kuruş)', ok);
}

function main() {
	const fresh1 = computeAll();
	if (fresh1.errors.length > 0) {
		record('recompute', 'Kaynaktan manifest yeniden hesaplanabildi', false, fresh1.errors.join(' | '));
		finish();
		return;
	}
	record('recompute', 'Kaynaktan manifest yeniden hesaplanabildi (extract-source.js güvenlik kapıları dahil)', true);

	// Determinism: recompute a SECOND time, purely in memory, no disk I/O
	// in between — proves the builder itself is a pure function of the
	// frozen source files, independent of whatever is currently on disk.
	const fresh2 = computeAll();
	let inMemoryDeterministic = true;
	Object.keys(fresh1.files).forEach(function (key) {
		if (toDeterministicJson(fresh1.files[key]) !== toDeterministicJson(fresh2.files[key])) {
			inMemoryDeterministic = false;
		}
	});
	record('in_memory_determinism', 'İki ardışık bellek-içi üretim byte-eşit (disk I/O olmadan)', inMemoryDeterministic);

	// Disk comparison: every freshly computed file must match what is
	// currently checked in under wordpress-site/data/** byte-for-byte.
	const diskCompare = compareFreshToDisk(fresh1.files, DATA_DIR);
	record('disk_matches_recompute', 'Diskteki manifest dosyaları, kaynaktan yeniden hesaplanan sonuçla byte-eşit', diskCompare.matches, diskCompare.mismatchDetails.join(' | '));
	const allRawForScan = Object.keys(diskCompare.rawByPath).map(function (relPath) {
		return { relPath: relPath, raw: diskCompare.rawByPath[relPath] };
	});

	if (!diskCompare.matches) {
		finish();
		return;
	}

	// Paylaşılan doğrulayıcı — build-manifest.js'in yazmadan ÖNCE çalıştırdığı
	// AYNI fonksiyon: şema + zarf/count tutarlılığı + çapraz-alan (kod
	// içine gömülü seviye/revizyon, bağlı yeterlilik seviye/sektör, min/max
	// yeniden hesap) + kaynakla tam alan-alan karşılaştırması.
	const shared = validateManifestSet({ sources: fresh1.sources, files: fresh1.files }, SCHEMA_DIR);
	record('shared_validator', 'Paylaşılan doğrulayıcı (şema+zarf+çapraz-alan+kaynak karşılaştırması) hatasız', shared.errors.length === 0, shared.errors.slice(0, 8).join(' | '));

	const sectorFile = fresh1.files['content/sectors.manifest.json'];
	const qualificationFile = fresh1.files['content/qualifications.manifest.json'];
	const feeFile = fresh1.files['content/fees.manifest.json'];
	const mappingFile = fresh1.files['mapping/mapping.manifest.json'];
	const unmatchedFile = fresh1.files['mapping/unmatched-fees.manifest.json'];

	// Source SHA-256 — report the three values actually embedded (already
	// re-derived by extract-source.js on THIS run, not read from a
	// previously-trusted cache).
	record('source_sha256_sectors', 'sectors.js SHA-256', true, sectorFile.source.sha256);
	record('source_sha256_qualifications', 'qualifications.js SHA-256', true, qualificationFile.source.sha256);
	record('source_sha256_fees', 'fees.js SHA-256', true, feeFile.source.sha256);

	// Counts — 14/83/103/145/87/16/58/84/19.
	record('count_sectors', 'Sektör sayısı = ' + EXPECTED.sectors, sectorFile.count === EXPECTED.sectors, sectorFile.count);
	record('count_qualifications', 'Yeterlilik sayısı = ' + EXPECTED.qualifications, qualificationFile.count === EXPECTED.qualifications, qualificationFile.count);
	record('count_fees', 'Ücret ana kayıt sayısı = ' + EXPECTED.fees, feeFile.count === EXPECTED.fees, feeFile.count);
	record('count_price_options', 'Toplam fiyat seçeneği = ' + EXPECTED.priceOptionsTotal, feeFile.counts.priceOptionsTotal === EXPECTED.priceOptionsTotal, feeFile.counts.priceOptionsTotal);
	record('count_pricing_single', '"single" kayıt sayısı = ' + EXPECTED.pricingSingle, feeFile.counts.pricingSingle === EXPECTED.pricingSingle, feeFile.counts.pricingSingle);
	record('count_pricing_multi', 'Çok-fiyatlı kayıt sayısı = ' + EXPECTED.pricingMulti, feeFile.counts.pricingMulti === EXPECTED.pricingMulti, feeFile.counts.pricingMulti);
	record('count_multi_options', 'Çok-fiyatlı seçenek toplamı = ' + EXPECTED.multiOptionsTotal, feeFile.counts.multiOptionsTotal === EXPECTED.multiOptionsTotal, feeFile.counts.multiOptionsTotal);
	record('count_fees_with_code', 'Dolu qualificationCode sayısı = ' + EXPECTED.feesWithCode, feeFile.counts.feesWithCode === EXPECTED.feesWithCode, feeFile.counts.feesWithCode);
	record('count_fees_without_code', 'Boş qualificationCode sayısı = ' + EXPECTED.feesWithoutCode, feeFile.counts.feesWithoutCode === EXPECTED.feesWithoutCode, feeFile.counts.feesWithoutCode);
	record('count_unmatched_report', 'unmatched-fees.manifest.json kayıt sayısı = ' + EXPECTED.feesWithoutCode, unmatchedFile.count === EXPECTED.feesWithoutCode, unmatchedFile.count);

	// Uniqueness (re-derived independently of the builder's own internal checks).
	const sectorSlugs = sectorFile.records.map(function (r) { return r.slug; });
	record('unique_sector_slugs', 'Sektör slug benzersizliği', new Set(sectorSlugs).size === sectorSlugs.length);

	const mykCodes = qualificationFile.records.map(function (r) { return r.code; });
	record('unique_myk_codes', 'MYK kodu benzersizliği', new Set(mykCodes).size === mykCodes.length);

	const feeSourceKeys = feeFile.records.map(function (r) { return r.source_key; });
	record('unique_fee_source_keys', 'Ücret source_key benzersizliği', new Set(feeSourceKeys).size === feeSourceKeys.length);

	// Sector reference integrity.
	const sectorSlugSet = new Set(sectorSlugs);
	const qualificationSectorRefsOk = qualificationFile.records.every(function (r) { return sectorSlugSet.has(r.sector_slug); });
	record('qualification_sector_refs', 'Her yeterlilik kaydının sector_slug\'ı gerçek sektör manifestinde var', qualificationSectorRefsOk);
	const feeSectorRefsOk = feeFile.records.every(function (r) { return sectorSlugSet.has(r.sector_slug); });
	record('fee_sector_refs', 'Her ücret kaydının sector_slug\'ı gerçek sektör manifestinde var', feeSectorRefsOk);

	// Qualification linkage: exactly 84 linked with a valid target, exactly 19 null.
	const qualificationSourceKeySet = new Set(qualificationFile.records.map(function (r) { return r.source_key; }));
	const linked = feeFile.records.filter(function (r) { return null !== r.qualification_source_key; });
	const linkedValid = linked.every(function (r) { return qualificationSourceKeySet.has(r.qualification_source_key); });
	record('linked_fees_valid_targets', 'Bağlantılı (dolu kodlu) ücretlerin hepsi gerçek bir yeterliliğe işaret ediyor', linkedValid && linked.length === EXPECTED.feesWithCode, linked.length);
	const nullLinked = feeFile.records.filter(function (r) { return null === r.qualification_source_key; });
	record('null_linked_fees_count', 'qualification_source_key=null olan kayıt sayısı = ' + EXPECTED.feesWithoutCode, nullLinked.length === EXPECTED.feesWithoutCode, nullLinked.length);

	// Source-index sequence completeness per manifest.
	checkIndexSequence(sectorFile.records, 'sectors');
	checkIndexSequence(qualificationFile.records, 'qualifications');
	checkIndexSequence(feeFile.records, 'fees');

	// Price-option field/limit integrity + money reversibility.
	checkPriceOptionIntegrity(feeFile.records);
	checkMoneyReversibility(feeFile.records);

	// Mapping row count sanity: one row per sector+qualification+fee record.
	const expectedMappingRows = sectorFile.count + qualificationFile.count + feeFile.count;
	record('mapping_row_count', 'Eşleştirme dosyası satır sayısı = sektör+yeterlilik+ücret toplamı', mappingFile.rows.length === expectedMappingRows, mappingFile.rows.length + ' / ' + expectedMappingRows);

	// Forbidden-content scan (absolute paths, secrets, runtime-dependent fields).
	let forbiddenFound = [];
	allRawForScan.forEach(function (entry) {
		const hits = scanForForbiddenContent(entry.relPath, entry.raw);
		if (hits.length > 0) {
			forbiddenFound.push(entry.relPath + ': ' + hits.join(', '));
		}
	});
	record('no_forbidden_content', 'Manifestte mutlak yol / gizli bilgi / çalışma-zamanına-bağlı alan yok', forbiddenFound.length === 0, forbiddenFound.join(' | '));

	// §5.3 (Faz 6A Doğrulayıcı Bütünlük Kapanışı) — data/content ve
	// data/mapping altında beklenen 5 manifest dosyası dışında hiçbir
	// "*.manifest.json" bulunmamalı (ör. eski bir sürümden kalan/elle
	// eklenmiş bir dosya). validation-summary.json AYRI bir rapordur,
	// bu allowlist'e hiç girmez — dosya adı zaten "*.manifest.json"
	// deseniyle eşleşmiyor.
	const unexpectedManifestFiles = findUnexpectedManifestFiles(DATA_DIR);
	record('no_unexpected_manifest_files', 'data/content ve data/mapping altında beklenen 5 dosya dışında "*.manifest.json" yok', unexpectedManifestFiles.length === 0, unexpectedManifestFiles.join(', '));

	finish();
}

/**
 * §9 (Faz 6A Son Kabul Düzeltmesi) — validation-summary.json'ın GERÇEK son
 * nesnesini (geçici bir passed:0/failed:0 yer tutucusunu DEĞİL) hem kendi
 * şemasına hem dört sayısal invariant'a karşı doğrular:
 *   total_checks === checks.length
 *   passed + failed === total_checks
 *   passed === checks.filter(ok===true).length
 *   failed === checks.filter(ok===false).length
 *
 * Kasıtlı olarak "N/N kontrolden biri" DEĞİLDİR — checks[] dizisi bu
 * fonksiyon çağrıldığında zaten donmuş durumda (finish() bu noktadan
 * sonra ona hiçbir şey eklemez), bu yüzden "bu kontrolü checks'e
 * eklersem toplam değişir" döngüsü hiç oluşmaz: bu, raporlanan N/N
 * pipeline'ının parçası değil, o pipeline TAMAMLANDIKTAN SONRA çalışan
 * ayrı bir finalizasyon kapısıdır. Şema/invariant başarısız olursa
 * hiçbir dosya yazılmaz ve süreç non-zero çıkar.
 *
 * @returns {string[]} errors — empty means the summary is safe to write.
 */
/** §5.3 — pure-ish scan (fs read-only), factored out for direct testing with a real temp file, without going through the whole verify-manifest.js `main()` flow. */
function findUnexpectedManifestFiles(dataDir) {
	const expectedManifestFiles = [
		'content/sectors.manifest.json', 'content/qualifications.manifest.json', 'content/fees.manifest.json',
		'mapping/mapping.manifest.json', 'mapping/unmatched-fees.manifest.json',
		// Faz 7 — İÇERİK manifestleri (haber/referans): AYRI üretim/doğrulama hattı
		// (build-content-manifest.js / verify-content-manifest.js); katalog kontrolü bu iki dosyayı
		// "beklenmeyen" saymaz, ama içeriklerini de DOĞRULAMAZ (o, verify-content-manifest.js'in işi).
		'content/news.manifest.json', 'content/references.manifest.json', 'content/faqs.manifest.json',
		// Faz 12 — SAYFA manifesti: AYRI üretim/doğrulama hattı (build-page-manifest.js / verify-page-manifest.js).
		'content/pages.manifest.json',
	];
	const unexpected = [];
	['content', 'mapping'].forEach(function (sub) {
		const dir = path.join(dataDir, sub);
		if (!fs.existsSync(dir)) {
			return;
		}
		fs.readdirSync(dir).forEach(function (name) {
			if (!/\.manifest\.json$/.test(name)) {
				return;
			}
			const relPath = sub + '/' + name;
			if (expectedManifestFiles.indexOf(relPath) === -1) {
				unexpected.push(relPath);
			}
		});
	});
	return unexpected;
}

function validateFinalSummary(summary, schema) {
	if (!summary || 'object' !== typeof summary || Array.isArray(summary)) {
		return ['validation-summary: bir obje olmalı.'];
	}

	const errors = validateAgainstSchema(summary, schema);

	// §8 (Faz 6A Doğrulayıcı Bütünlük Kapanışı) — fail-closed dayanıklılık:
	// şema hatasından SONRA gelen dört sayısal invariant, yalnız ŞEKİL
	// GÜVENLİYSE (checks gerçekten bir dizi, passed/failed/total_checks
	// gerçekten sayı) çalışır — aksi halde `.length`/`.filter` üzerinde
	// kontrolsüz bir TypeError yerine, şemanın zaten raporladığı hatalarla
	// birlikte sessizce atlanır (şema hatası zaten "checks dizi değil"
	// gibi asıl sorunu bildirmiş olur).
	const checksIsArray = Array.isArray(summary.checks);
	const countsAreNumbers = 'number' === typeof summary.total_checks && 'number' === typeof summary.passed && 'number' === typeof summary.failed;

	if (!checksIsArray) {
		errors.push('validation-summary: "checks" bir dizi olmalı (invariant kontrolleri bu yüzden atlandı).');
	}
	if (!countsAreNumbers) {
		errors.push('validation-summary: "total_checks"/"passed"/"failed" sayı olmalı (invariant kontrolleri bu yüzden atlandı).');
	}
	if (!checksIsArray || !countsAreNumbers) {
		return errors;
	}

	if (summary.total_checks !== summary.checks.length) {
		errors.push('total_checks (' + summary.total_checks + ') checks.length (' + summary.checks.length + ') ile uyuşmuyor.');
	}
	if (summary.passed + summary.failed !== summary.total_checks) {
		errors.push('passed+failed (' + (summary.passed + summary.failed) + ') total_checks (' + summary.total_checks + ') ile uyuşmuyor.');
	}
	const realPassed = summary.checks.filter(function (c) { return c && true === c.ok; }).length;
	const realFailed = summary.checks.filter(function (c) { return c && false === c.ok; }).length;
	if (summary.passed !== realPassed) {
		errors.push('passed (' + summary.passed + ') checks içindeki gerçek ok:true sayısıyla (' + realPassed + ') uyuşmuyor.');
	}
	if (summary.failed !== realFailed) {
		errors.push('failed (' + summary.failed + ') checks içindeki gerçek ok:false sayısıyla (' + realFailed + ') uyuşmuyor.');
	}

	return errors;
}

function finish() {
	const failedChecks = checks.filter(function (c) {
		return !c.ok;
	});

	const summary = {
		schema_version: '1.0.0',
		record_type: 'validation_summary',
		total_checks: checks.length,
		passed: checks.length - failedChecks.length,
		failed: failedChecks.length,
		checks: checks,
	};

	// validation-summary.json'ın kendi şeması — manifest-envelope ile
	// KARIŞTIRILMAMASI gereken ayrı, doğru şekil (bkz. şema dosyasının
	// kendi description'ı). finish() top-level bir fonksiyon olduğu için
	// (main()'in yerel değişkenlerine kapanış yoluyla erişemez) şemayı
	// burada, kendi başına yükler.
	const validationSummarySchema = JSON.parse(fs.readFileSync(VALIDATION_SUMMARY_SCHEMA_PATH, 'utf8'));
	const finalizationErrors = validateFinalSummary(summary, validationSummarySchema);
	if (finalizationErrors.length > 0) {
		process.stderr.write('\nFİNALİZASYON KAPISI BAŞARISIZ — validation-summary.json GEÇERSİZ, hiçbir dosya yazılmadı:\n');
		finalizationErrors.forEach(function (e) {
			process.stderr.write('  - ' + e + '\n');
		});
		process.exit(1);
		return;
	}

	// The validation summary itself is a REPORT of a run, not part of the
	// deterministic content manifest — it is allowed to be regenerated on
	// every verify run (still contains no absolute paths, secrets, or
	// wall-clock/random fields, only pass/fail facts about the sources).
	fs.mkdirSync(path.dirname(REPORT_PATH), { recursive: true });
	fs.writeFileSync(REPORT_PATH, toDeterministicJson(summary), { encoding: 'utf8' });

	process.stdout.write('\nFaz 6A doğrulama sonucu: ' + summary.passed + '/' + summary.total_checks + ' kontrol geçti.\n');
	checks.forEach(function (c) {
		process.stdout.write((c.ok ? 'PASS  ' : 'FAIL  ') + c.label + (c.detail ? ' — ' + c.detail : '') + '\n');
	});
	process.stdout.write('FINALIZATION PASS  validation-summary.json şema + 4 sayısal invariant (total_checks/passed/failed) doğrulandı — bu, yukarıdaki N/N sayısına dahil DEĞİLDİR, ayrı bir yazma-öncesi kapıdır.\n');

	if (failedChecks.length > 0) {
		process.stderr.write('\n' + failedChecks.length + ' kontrol BAŞARISIZ.\n');
		process.exit(1);
	}
}

if (require.main === module) {
	main();
}

module.exports = { main, validateFinalSummary, findUnexpectedManifestFiles, compareFreshToDisk };
