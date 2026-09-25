'use strict';
/**
 * Faz 6A Tam Kapsam ve Sayaç Kapanışı — the ONE place the 9 documented,
 * doğrulanmış source counts (14/83/103/145/87/16/58/84/19) live. Both
 * build-manifest.js (its own EXPECTED gate, unchanged behavior) and
 * lib/validate-manifest-set.js (cardinality binding, §3) import this
 * SAME object — no second, driftable copy of these numbers exists.
 */
const EXPECTED = {
	sectors: 14,
	qualifications: 83,
	fees: 103,
	priceOptionsTotal: 145,
	pricingSingle: 87,
	pricingMulti: 16,
	multiOptionsTotal: 58,
	feesWithCode: 84,
	feesWithoutCode: 19,
};

/**
 * Faz 7 — İÇERİK aktarımının (haber + referans) doğrulanmış kaynak sayıları. Mevcut `EXPECTED`
 * (katalog, 9 sayı) ile KARIŞTIRILMAZ: ayrı sabit, ayrı tüketiciler (build-content-manifest.js,
 * verify-content-manifest.js). Kaynak: tanitim-site/assets/data/news.js (6 gerçek haber/duyuru)
 * ve references.js (12 TEMSİLİ referans logosu — gerçek müşteri DEĞİL).
 */
const CONTENT_EXPECTED = {
	news: 6,
	references: 12,
};

module.exports = { EXPECTED, CONTENT_EXPECTED };
