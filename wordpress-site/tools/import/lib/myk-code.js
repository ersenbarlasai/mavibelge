'use strict';
/**
 * Faz 6A — deterministic MYK code parser. Pure, no I/O.
 *
 * Real source shape (tanitim-site/assets/data/qualifications.js, 83
 * kayıt yeniden sayıldı): 63 kod "<2 rakam>UY<4 rakam>-<seviye>/<2 rakam
 * revizyon>" biçiminde (ör. "10UY0002-3/03"), 20 kod ise revizyon eki
 * OLMADAN "<2 rakam>UY<4 rakam>-<seviye>" biçiminde (ör. "13UY0145-3").
 * Bu, mevcut `MaviBelge_Core_Validator::is_valid_myk_code_format()`
 * regex'inin (`^[0-9]{2}UY[0-9]{4}-[0-9]{1,2}\/[0-9]{2}$`) REDDEDECEĞİ
 * gerçek, kaynaktan doğrulanmış bir veri şeklidir — bkz.
 * raporlar/veri-aktarim-raporlari/faz6a-manifest-sozlesmesi.md
 * "Bloklayıcı sözleşme açığı #1". Bu ayrıştırıcı revizyon eksikliğini
 * ASLA uydurmaz — yalnız gerçekten var olan revizyonu çıkarır, yoksa
 * boş string bırakır ve `has_revision: false` işaretler.
 */

const WITH_REVISION_RE = /^([0-9]{2}UY[0-9]{4})-([0-9]{1,2})\/([0-9]{2})$/;
const WITHOUT_REVISION_RE = /^([0-9]{2}UY[0-9]{4})-([0-9]{1,2})$/;

/**
 * @param {string} code
 * @returns {{valid: boolean, base?: string, level_from_code?: string, revision: string, has_revision: boolean}}
 */
function parseMykCode(code) {
	if (typeof code !== 'string') {
		return { valid: false, revision: '', has_revision: false };
	}
	let m = WITH_REVISION_RE.exec(code);
	if (m) {
		return { valid: true, base: m[1], level_from_code: m[2], revision: m[3], has_revision: true };
	}
	m = WITHOUT_REVISION_RE.exec(code);
	if (m) {
		return { valid: true, base: m[1], level_from_code: m[2], revision: '', has_revision: false };
	}
	return { valid: false, revision: '', has_revision: false };
}

/**
 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı'nda ARTIK ESKİ bir regex'i
 * (revizyon eki zorunlu) yansıtır — MaviBelge_Core_Validator::is_valid_myk_code_format()
 * bu görevde revizyonu OPSİYONEL kabul edecek şekilde gevşetildi (bkz.
 * class-validator.php). Bu fonksiyon artık canlı eklenti davranışını
 * DEĞİL, yalnız kapatılmış "Bloklayıcı sözleşme açığı #1" bulgusunun
 * tarihsel/bilgi amaçlı sayımını üretir (qualifications.manifest.json
 * "notes" alanı) — hiçbir kaydı reddetmez/değiştirmez.
 */
function matchesLegacyRevisionRequiredFormat(code) {
	return /^[0-9]{2}UY[0-9]{4}-[0-9]{1,2}\/[0-9]{2}$/.test( String( code ) );
}

module.exports = { parseMykCode, matchesLegacyRevisionRequiredFormat };
