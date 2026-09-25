'use strict';
/**
 * Faz 6A — TL(integer)->kuruş conversion. Pure, no I/O.
 *
 * Source amounts (fees.js `amount`/`certificatePrintFeeExcluded`) are
 * always already-integer Turkish Lira (re-verified: none of the 145
 * price-option amounts or the 103 certificatePrintFeeExcluded values
 * carry a decimal point). This is therefore a plain, exact `amount *
 * 100`, never a string-parsing/locale conversion — that parsing (for
 * admin-entered "17.000,00"-style TL strings) is
 * MaviBelge_Core_Validator::try_lira_to_kurus()'s job, not this
 * manifest builder's; the manifest already stores integer kuruş.
 */

/** Mirrors PHP's overflow guard in try_lira_to_kurus(): reject before amount*100 could lose precision. */
const MAX_LIRA = Math.floor(Number.MAX_SAFE_INTEGER / 100);

/**
 * @param {*} amountLira
 * @returns {{ok: boolean, kurus?: number, reason?: string}}
 */
function liraIntToKurus(amountLira) {
	if (typeof amountLira !== 'number' || !Number.isInteger(amountLira)) {
		return { ok: false, reason: 'not_an_integer' };
	}
	if (amountLira <= 0) {
		return { ok: false, reason: 'not_positive' };
	}
	if (amountLira > MAX_LIRA) {
		return { ok: false, reason: 'overflow' };
	}
	return { ok: true, kurus: amountLira * 100 };
}

module.exports = { liraIntToKurus, MAX_LIRA };
