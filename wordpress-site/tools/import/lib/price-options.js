'use strict';
/**
 * Faz 6A — price-option shaping and limit checks, mirroring the SAME
 * bounds `MaviBelge_Core_Validator::normalize_price_options()`
 * (wp-content/plugins/mavibelge-core/includes/class-validator.php)
 * enforces at import/save time, so a manifest that passes here is not
 * silently rejected later by the plugin's own canonical validator.
 * This is a re-implementation of the LIMITS (label length, unit
 * length/count, option count), not a copy of the plugin's sanitize
 * logic — the manifest never runs PHP.
 */

const { liraIntToKurus } = require('./money');

const MAX_PRICE_OPTIONS = 20;
const MAX_OPTION_LABEL_LENGTH = 200;
const MAX_UNITS_PER_OPTION = 10;
const MAX_UNIT_LENGTH = 50;

/**
 * @param {Array<{label?:string, units?:string[], amount:number}>} sourceOptions
 * @returns {{ok: boolean, options?: Array, errors: string[]}}
 */
function buildCanonicalPriceOptions(sourceOptions) {
	const errors = [];
	if (!Array.isArray(sourceOptions) || sourceOptions.length === 0) {
		return { ok: false, errors: ['price_options_empty'] };
	}
	if (sourceOptions.length > MAX_PRICE_OPTIONS) {
		return { ok: false, errors: ['price_options_exceeds_max_' + MAX_PRICE_OPTIONS] };
	}

	const options = [];
	sourceOptions.forEach(function (row, index) {
		if (!row || typeof row !== 'object' || Array.isArray(row)) {
			errors.push('option[' + index + ']_not_an_object');
			return;
		}
		const label = typeof row.label === 'string' ? row.label.trim() : '';
		if ('' === label) {
			errors.push('option[' + index + ']_label_empty');
			return;
		}
		if (label.length > MAX_OPTION_LABEL_LENGTH) {
			errors.push('option[' + index + ']_label_too_long');
			return;
		}

		const conversion = liraIntToKurus(row.amount);
		if (!conversion.ok) {
			errors.push('option[' + index + ']_amount_invalid_(' + conversion.reason + ')');
			return;
		}

		let units = [];
		if (Object.prototype.hasOwnProperty.call(row, 'units')) {
			if (!Array.isArray(row.units)) {
				errors.push('option[' + index + ']_units_not_an_array');
				return;
			}
			if (row.units.length > MAX_UNITS_PER_OPTION) {
				errors.push('option[' + index + ']_units_exceeds_max_' + MAX_UNITS_PER_OPTION);
				return;
			}
			for (let i = 0; i < row.units.length; i++) {
				const u = row.units[i];
				if (typeof u !== 'string') {
					errors.push('option[' + index + ']_unit[' + i + ']_not_a_string');
					return;
				}
				if (u.length > MAX_UNIT_LENGTH) {
					errors.push('option[' + index + ']_unit[' + i + ']_too_long');
					return;
				}
			}
			units = row.units.slice();
		}

		// Canonical sort_order: source arrays carry no explicit ordering
		// field — the array's own submission position IS the intended
		// display order (matches the plugin's own documented fallback
		// for an omitted sort_order: "satır pozisyonuna düşer").
		options.push({ label: label, units: units, amount_kurus: conversion.kurus, sort_order: index });
	});

	if (errors.length > 0) {
		return { ok: false, errors: errors };
	}
	return { ok: true, options: options, errors: [] };
}

module.exports = {
	buildCanonicalPriceOptions,
	MAX_PRICE_OPTIONS,
	MAX_OPTION_LABEL_LENGTH,
	MAX_UNITS_PER_OPTION,
	MAX_UNIT_LENGTH,
};
