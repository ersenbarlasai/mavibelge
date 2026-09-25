'use strict';
/**
 * Faz 6A Tam Kapsam ve Sayaç Kapanışı §6 — the ONE place that formulates
 * the `notes` array text for sectors.manifest.json and
 * qualifications.manifest.json. Both build-manifest.js (which writes the
 * notes) and lib/validate-manifest-set.js (which independently re-derives
 * and compares them) call these SAME pure functions — no second,
 * driftable copy of this text exists in two files.
 */

/**
 * @param {Object<string, {slug:string, image:string}>} bySlug sector records keyed by slug
 * @returns {string[]} 0 or 1 element
 */
function buildSectorNotes(bySlug) {
	const maden = bySlug.maden;
	const mermer = bySlug.mermer;
	if (maden && mermer && '' !== maden.image && maden.image === mermer.image) {
		return ['maden ve mermer sektörleri aynı görseli paylaşıyor (' + maden.image + ') — kurum onayı bekliyor, bkz. raporlar/proje-durumu.md.'];
	}
	return [];
}

/**
 * @param {number} formatGapCount count of qualification codes lacking a revision suffix
 * @returns {string[]} 0 or 1 element
 */
function buildQualificationNotes(formatGapCount) {
	if (formatGapCount > 0) {
		return [
			formatGapCount +
				' kayıtta MYK kodu revizyon eki (/NN) taşımıyor (ör. "13UY0145-3") — Faz 6A Güvenlik/Şema/Sözleşme Kapanışı\'nda MaviBelge_Core_Validator::is_valid_myk_code_format() revizyonu OPSİYONEL kabul edecek şekilde güncellendi, bu kayıtlar artık eklenti tarafından KABUL EDİLİYOR; bkz. faz6a-manifest-sozlesmesi.md "Bloklayıcı sözleşme açığı #1" (çözüldü). Kod uydurulmadı; matches_legacy_revision_required_format alanı yalnız tarihsel/bilgi amaçlıdır, artık bir engel değildir.',
		];
	}
	return [];
}

module.exports = { buildSectorNotes, buildQualificationNotes };
