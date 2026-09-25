'use strict';
/**
 * Faz 6A — pure helpers shared by extract/build/verify. No network, no
 * process/env access beyond what the caller explicitly passes in.
 */
const crypto = require('crypto');

/** @param {string|Buffer} data @returns {string} lowercase hex SHA-256 */
function sha256Hex(data) {
	return crypto.createHash('sha256').update(data).digest('hex');
}

/**
 * Deterministic JSON serialization: JS objects already preserve string-key
 * insertion order (ECMA-262 [[OwnPropertyKeys]] for non-integer-like
 * string keys), so as long as every object in the tree is BUILT with the
 * same fixed key order on every run (true here — no Set/Map iteration,
 * no Date.now()/Math.random() anywhere in the tree), JSON.stringify's
 * output is byte-identical across runs over the same input. A trailing
 * newline is added so the file has a real POSIX final line.
 */
function toDeterministicJson(value) {
	return JSON.stringify(value, null, 2) + '\n';
}

module.exports = { sha256Hex, toDeterministicJson };
