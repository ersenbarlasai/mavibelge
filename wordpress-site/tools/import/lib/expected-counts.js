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
 * Faz 7/12b — İÇERİK aktarımının doğrulanmış kaynak sayıları (haber 6, referans logosu 15, SSS 6). Mevcut `EXPECTED`
 * (katalog, 9 sayı) ile KARIŞTIRILMAZ. Kaynaklar: tanitim-site/assets/data/news.js (6 gerçek haber/duyuru), onaylı canlı
 * referans sayfasından alınmış 15 logo (data/sources/reference-logos/) ve tanitim-site/sss.html (6 soru-cevap).
 */
const CONTENT_EXPECTED = {
	news: 6,
	references: 15,
	faqs: 6,
};

module.exports = { EXPECTED, CONTENT_EXPECTED };
