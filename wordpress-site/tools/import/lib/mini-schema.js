'use strict';
/**
 * Faz 6A — minimal, dependency-free JSON-Schema (draft-07 SUBSET)
 * validator. No npm package was added for this (the project's own
 * package.json intentionally carries zero runtime dependencies — see
 * wordpress-site/docs/design-system.md's build tooling notes for the
 * same "no new dependency" rule applied elsewhere in this repo).
 *
 * Supports exactly the keywords the schema files under
 * wordpress-site/data/schema/*.json actually use: type (incl. array of
 * types), required, properties, additionalProperties, enum, const,
 * pattern, minLength, maxLength, minimum, maximum, minItems, maxItems,
 * items. Anything else is ignored (not a general-purpose validator).
 */

function typeOf(value) {
	if (null === value) {
		return 'null';
	}
	if (Array.isArray(value)) {
		return 'array';
	}
	return typeof value; // 'object', 'string', 'number', 'boolean'
}

function matchesType(value, expected) {
	const actual = typeOf(value);
	if ('integer' === expected) {
		return 'number' === actual && Number.isInteger(value);
	}
	return actual === expected;
}

/**
 * @param {*} value
 * @param {object} schema
 * @param {string} path for error messages
 * @param {string[]} errors accumulator
 */
function validate(value, schema, path, errors) {
	if (schema.type) {
		const types = Array.isArray(schema.type) ? schema.type : [schema.type];
		if (!types.some(function (t) { return matchesType(value, t); })) {
			errors.push(path + ': type beklenen ' + types.join('|') + ', bulunan ' + typeOf(value));
			return; // further checks would be meaningless on a wrong-typed value
		}
	}

	if (Object.prototype.hasOwnProperty.call(schema, 'const') && value !== schema.const) {
		errors.push(path + ': const eşleşmiyor (beklenen ' + JSON.stringify(schema.const) + ')');
	}
	if (schema.enum && schema.enum.indexOf(value) === -1) {
		errors.push(path + ': enum içinde değil (' + JSON.stringify(schema.enum) + ')');
	}

	if ('string' === typeof value) {
		if (undefined !== schema.minLength && value.length < schema.minLength) {
			errors.push(path + ': minLength ' + schema.minLength + ' altında');
		}
		if (undefined !== schema.maxLength && value.length > schema.maxLength) {
			errors.push(path + ': maxLength ' + schema.maxLength + ' üstünde');
		}
		if (schema.pattern && !new RegExp(schema.pattern).test(value)) {
			errors.push(path + ': pattern eşleşmiyor (' + schema.pattern + ') değer=' + JSON.stringify(value));
		}
	}

	if ('number' === typeof value) {
		if (undefined !== schema.minimum && value < schema.minimum) {
			errors.push(path + ': minimum ' + schema.minimum + ' altında');
		}
		if (undefined !== schema.maximum && value > schema.maximum) {
			errors.push(path + ': maximum ' + schema.maximum + ' üstünde');
		}
	}

	if (Array.isArray(value)) {
		if (undefined !== schema.minItems && value.length < schema.minItems) {
			errors.push(path + ': minItems ' + schema.minItems + ' altında');
		}
		if (undefined !== schema.maxItems && value.length > schema.maxItems) {
			errors.push(path + ': maxItems ' + schema.maxItems + ' üstünde');
		}
		if (schema.items) {
			value.forEach(function (item, i) {
				validate(item, schema.items, path + '[' + i + ']', errors);
			});
		}
	}

	if (schema.properties && value && 'object' === typeOf(value)) {
		(schema.required || []).forEach(function (key) {
			if (!Object.prototype.hasOwnProperty.call(value, key)) {
				errors.push(path + ': zorunlu alan eksik ("' + key + '")');
			}
		});
		Object.keys(value).forEach(function (key) {
			if (schema.properties[key]) {
				validate(value[key], schema.properties[key], path + '.' + key, errors);
			} else if (false === schema.additionalProperties) {
				errors.push(path + ': beklenmeyen ek alan ("' + key + '")');
			}
		});
	}
}

/** @returns {string[]} errors — empty array means valid. */
function validateAgainstSchema(value, schema) {
	const errors = [];
	validate(value, schema, '$', errors);
	return errors;
}

/**
 * Keywords this validator actually understands. Anything else in a schema
 * file (a typo, an unimplemented draft-07 keyword such as `$ref`,
 * `oneOf`, `if`/`then`) is now a HARD ERROR instead of being silently
 * ignored — a schema author's mistake must be loud, not swallowed.
 * `$schema`/`$id`/`title`/`description` are meta fields, allowed anywhere.
 */
const KNOWN_KEYWORDS = new Set([
	'$schema', '$id', 'title', 'description',
	'type', 'required', 'properties', 'additionalProperties',
	'enum', 'const', 'pattern', 'minLength', 'maxLength',
	'minimum', 'maximum', 'minItems', 'maxItems', 'items',
]);

/** Recursively walks only the nodes that are actually schema definitions (root, properties.*, items). */
function collectUnknownKeywords(schema, nodePath, unknown) {
	if (!schema || 'object' !== typeof schema || Array.isArray(schema)) {
		return;
	}
	Object.keys(schema).forEach(function (key) {
		if (!KNOWN_KEYWORDS.has(key)) {
			unknown.push(nodePath + '.' + key);
		}
	});
	if (schema.properties) {
		Object.keys(schema.properties).forEach(function (propKey) {
			collectUnknownKeywords(schema.properties[propKey], nodePath + '.properties.' + propKey, unknown);
		});
	}
	if (schema.items) {
		if (Array.isArray(schema.items)) {
			schema.items.forEach(function (itemSchema, i) {
				collectUnknownKeywords(itemSchema, nodePath + '.items[' + i + ']', unknown);
			});
		} else {
			collectUnknownKeywords(schema.items, nodePath + '.items', unknown);
		}
	}
}

/** @throws {Error} if the schema uses any keyword outside KNOWN_KEYWORDS (e.g. an unresolved `$ref`). */
function assertKnownKeywords(schema, label) {
	const unknown = [];
	collectUnknownKeywords(schema, label, unknown);
	if (unknown.length > 0) {
		throw new Error('Şema desteklenmeyen/bilinmeyen anahtar kelime içeriyor (' + label + '): ' + unknown.join(', '));
	}
}

module.exports = { validateAgainstSchema, assertKnownKeywords, KNOWN_KEYWORDS };
