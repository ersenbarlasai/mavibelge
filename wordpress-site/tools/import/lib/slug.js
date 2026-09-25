'use strict';
/**
 * Faz 6A — deterministic Turkish-aware slugify, used ONLY to build a
 * printable fee source_key component from a real field (profession
 * name) — never used to fuzzy-match/relate two different records to
 * each other. Pure, no I/O, no Unicode library dependency.
 */

const TURKISH_MAP = {
	ç: 'c', Ç: 'c',
	ğ: 'g', Ğ: 'g',
	ı: 'i', I: 'i',
	İ: 'i', i: 'i',
	ö: 'o', Ö: 'o',
	ş: 's', Ş: 's',
	ü: 'u', Ü: 'u',
};

function slugify(value) {
	const folded = String(value)
		.split('')
		.map(function (ch) {
			return Object.prototype.hasOwnProperty.call(TURKISH_MAP, ch) ? TURKISH_MAP[ch] : ch;
		})
		.join('');
	return folded
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

module.exports = { slugify };
