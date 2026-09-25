'use strict';
/**
 * Faz 6A §5 (Güvenlik/Şema/Sözleşme Kapanışı revizyonu) — the ONE place
 * that reads the three frozen source files.
 *
 * BAĞLAYICI GÜVENLİK KARARI: kaynak metni HİÇBİR KOŞULDA çalıştırılmaz.
 * Önceki sürüm `vm.createContext`/`vm.Script` ile "izole" bir bağlamda
 * kodu ÇALIŞTIRIYORDU; bağımsız inceleme bunun kaçılabilir olduğunu
 * kanıtladı (`window.constructor.constructor("return process.version")()`
 * gibi bir literal, hiçbir yasaklı globale doğrudan isimle değinmeden
 * Function constructor'a yansıma yoluyla ulaşır — `codeGeneration:
 * {strings:false}` bunu engellemez çünkü ortada bir eval/Function-string
 * çağrısı yok, doğrudan bir metot çağrısı var). Ayrıca eski biçim-kontrolü
 * yalnız dış `window.NAME = [ ... ];` kabuğunu regex ile doğruluyordu,
 * dizi İÇERİĞİNİN gerçekten literal olduğunu hiç doğrulamıyordu — bu
 * yüzden bir IIFE çağrısı veya ikinci bir `window.X = []; window.X = [];`
 * ataması da sızabiliyordu.
 *
 * Bu dosya artık kod ÇALIŞTIRMAZ: `vm`, `eval`, `Function`, alt-süreçte
 * JS çalıştırma, "regex ile çıkar sonra eval et" — bunların HİÇBİRİ
 * kullanılmaz. Bunun yerine, aşağıda tanımlı çok dar bir dilbilgisini
 * (literal-only recursive-descent ayrıştırıcı) kabul eden, elle yazılmış,
 * fail-closed bir tokenizer+parser kullanılır. Ayrıştırıcı yalnız veri
 * literalini bir JS değerine çevirir; hiçbir ifadeyi değerlendirmez,
 * hiçbir tanımlayıcıyı (identifier) değer olarak çözmez.
 *
 * İzinli dilbilgisi (bkz. parseLiteral()):
 *   Program    := Assignment ';'?
 *   Assignment := 'window' '.' <globalName> '=' Value
 *   Value      := Array | Object | String | Integer | 'true' | 'false' | 'null'
 *   Array      := '[' (Value (',' Value)* ','?)? ']'
 *   Object     := '{' (Member (',' Member)* ','?)? '}'
 *   Member     := Key ':' Value
 *   Key        := Identifier | String   (izin listesindeki alan adlarından biri olmalı)
 *   Integer    := '-'? [0-9]+            (ondalık/hex/üstel/NaN/Infinity YOK)
 *   String     := '"' ... '"' | "'" ... "'"  (yalnız JSON-güvenli kaçışlar)
 *
 * Reddedilenler (tokenizer veya parser aşamasında, HER ZAMAN fail-closed):
 * fonksiyon çağrıları, IIFE, ikinci ifade/atama, tehlikeli anahtarlar
 * (constructor/prototype/__proto__), spread (...), hesaplanmış anahtar
 * ([expr]:), getter/setter, template/regex literalleri, operatörler
 * (+ - * / && || ?? vb.), tanımlayıcı/değişken değerler, class/new/this,
 * yinelenen anahtarlar, ondalık/üstel/hex sayılar, NaN/Infinity.
 */

const fs = require('fs');
const path = require('path');
const { sha256Hex } = require('./lib/hash');

const REPO_ROOT = path.resolve(__dirname, '..', '..', '..');

/**
 * Only these five files may ever be read by this module — no path is ever constructed from external input.
 * (Faz 7: `news` ve `references` iki İÇERİK kaynağıdır; extractAll() hâlâ yalnız üç KATALOG kaynağını okur,
 * içerik kaynakları extractContent() ile ayrıca okunur.)
 */
const SOURCES = {
	sectors: {
		repoRelativePath: 'tanitim-site/assets/data/sectors.js',
		globalName: 'MB_SECTORS',
	},
	qualifications: {
		repoRelativePath: 'tanitim-site/assets/data/qualifications.js',
		globalName: 'MB_QUALIFICATIONS',
	},
	fees: {
		repoRelativePath: 'tanitim-site/assets/data/fees.js',
		globalName: 'MB_FEES',
	},
	news: {
		repoRelativePath: 'tanitim-site/assets/data/news.js',
		globalName: 'MB_NEWS',
	},
	references: {
		repoRelativePath: 'tanitim-site/assets/data/references.js',
		globalName: 'MB_REFERENCES',
	},
};

/** Kaynak dosyaların gerçek alanlarının birleşimi — başka hiçbir anahtara izin verilmez. */
const ALLOWED_KEYS = new Set([
	'slug', 'name', 'desc', 'icon', 'image',
	'code', 'level', 'sector',
	'qualificationCode', 'pricingType', 'options', 'label', 'units', 'amount',
	'vatIncluded', 'certificatePrintFeeExcluded', 'source', 'sourcePage',
	// Faz 7 — news.js (title/date/type/summary/body) ve references.js (file/alt) alanları.
	'title', 'date', 'type', 'summary', 'body', 'file', 'alt',
]);

/** Yansıma/prototip kirliliği için evrensel olarak yasak anahtarlar — ALLOWED_KEYS içinde de yok, ama açıkça belgelemek için ayrıca kontrol edilir. */
const FORBIDDEN_KEYS = new Set(['constructor', 'prototype', '__proto__', '__defineGetter__', '__defineSetter__']);

/**
 * Strips only genuine FULL-LINE comments — never a mid-line substring:
 *  - `//` yorumu: baştaki boşluk + `//` + satır sonuna kadar;
 *  - Faz 7: TEK SATIRLIK blok yorumu (references.js dosya başlığı): satırın TAMAMI yalnız bu
 *    yorumdur (öncesinde/sonrasında kod yok, içinde kapanış işareti yok). Çok satırlı blok yorumu
 *    ve satır ortasındaki blok yorumu SİLİNMEZ; belirteçleyici `/` karakterini reddeder (fail-closed).
 */
function stripFullLineComments(rawSrc) {
	return rawSrc
		.replace(/^[ \t]*\/\/.*$/gm, '')
		.replace(/^[ \t]*\/\*(?:(?!\*\/)[^\r\n])*\*\/[ \t]*$/gm, '');
}

// ---------------------------------------------------------------------
// Tokenizer — no execution, only classification of characters.
// ---------------------------------------------------------------------

const PUNCT_TOKENS = {
	'[': 'LBRACKET', ']': 'RBRACKET',
	'{': 'LBRACE', '}': 'RBRACE',
	':': 'COLON', ',': 'COMMA',
	'.': 'DOT', '=': 'EQUALS', ';': 'SEMI',
};

function tokenize(src) {
	const tokens = [];
	let i = 0;
	const n = src.length;

	function error(msg) {
		throw new Error('Kaynak sözdizimi reddedildi (konum ' + i + '): ' + msg);
	}

	while (i < n) {
		const ch = src[i];

		if (' ' === ch || '\t' === ch || '\r' === ch || '\n' === ch) {
			i++;
			continue;
		}

		if (Object.prototype.hasOwnProperty.call(PUNCT_TOKENS, ch)) {
			tokens.push({ type: PUNCT_TOKENS[ch], value: ch, pos: i });
			i++;
			continue;
		}

		if ('"' === ch || "'" === ch) {
			const quote = ch;
			let out = '';
			let j = i + 1;
			let closed = false;
			while (j < n) {
				const c = src[j];
				if (c === quote) {
					closed = true;
					j++;
					break;
				}
				if ('\\' === c) {
					const next = src[j + 1];
					const simple = { '"': '"', "'": "'", '\\': '\\', '/': '/', b: '\b', f: '\f', n: '\n', r: '\r', t: '\t' };
					if (Object.prototype.hasOwnProperty.call(simple, next)) {
						out += simple[next];
						j += 2;
						continue;
					}
					if ('u' === next) {
						const hex = src.substr(j + 2, 4);
						if (!/^[0-9a-fA-F]{4}$/.test(hex)) {
							error('geçersiz \\u kaçışı');
						}
						out += String.fromCharCode(parseInt(hex, 16));
						j += 6;
						continue;
					}
					error('desteklenmeyen kaçış dizisi ("\\' + next + '")');
				}
				if ('\n' === c || '\r' === c) {
					error('satır sonu içeren kapatılmamış string');
				}
				out += c;
				j++;
			}
			if (!closed) {
				error('kapatılmamış string literal');
			}
			tokens.push({ type: 'STRING', value: out, pos: i });
			i = j;
			continue;
		}

		if ('-' === ch || (ch >= '0' && ch <= '9')) {
			let j = i;
			if ('-' === src[j]) {
				j++;
			}
			if (!(src[j] >= '0' && src[j] <= '9')) {
				error('sayı biçimi geçersiz (yalnız tam sayıya izin verilir)');
			}
			const startDigits = j;
			while (j < n && src[j] >= '0' && src[j] <= '9') {
				j++;
			}
			// Ondalık nokta, üstel (e/E), hex (0x) veya bitişik harf/alt çizgi — hepsi reddedilir.
			if ('.' === src[j] || 'e' === src[j] || 'E' === src[j] || 'x' === src[j] || 'X' === src[j] ||
				/[A-Za-z_$]/.test(src[j] || '')) {
				error('yalnız tam sayı literaline izin verilir (ondalık/üstel/hex/isim bitişik sayı reddedildi)');
			}
			const raw = src.slice(i, j);
			tokens.push({ type: 'NUMBER', value: raw, pos: i, digits: src.slice(startDigits, j) });
			i = j;
			continue;
		}

		if (/[A-Za-z_$]/.test(ch)) {
			let j = i;
			while (j < n && /[A-Za-z0-9_$]/.test(src[j])) {
				j++;
			}
			const word = src.slice(i, j);
			tokens.push({ type: 'IDENT', value: word, pos: i });
			i = j;
			continue;
		}

		error('izin verilmeyen karakter ("' + ch + '")');
	}

	tokens.push({ type: 'EOF', value: null, pos: n });
	return tokens;
}

// ---------------------------------------------------------------------
// Parser — builds a plain JS value from tokens; never evaluates code.
// ---------------------------------------------------------------------

function parseLiteral(src, globalName) {
	const tokens = tokenize(src);
	let p = 0;

	function peek() {
		return tokens[p];
	}
	function next() {
		return tokens[p++];
	}
	function expect(type, msg) {
		const t = next();
		if (t.type !== type) {
			throw new Error('Beklenmeyen belirteç (konum ' + t.pos + '): ' + (msg || type) + ' bekleniyordu, "' + t.value + '" bulundu.');
		}
		return t;
	}

	function parseValue() {
		const t = peek();
		if ('LBRACKET' === t.type) {
			return parseArray();
		}
		if ('LBRACE' === t.type) {
			return parseObject();
		}
		if ('STRING' === t.type) {
			next();
			return t.value;
		}
		if ('NUMBER' === t.type) {
			next();
			// Number.isFinite guard is redundant here (tokenizer only emits
			// plain integer digit sequences), kept as defense-in-depth.
			const num = parseInt(t.value, 10);
			if (!Number.isFinite(num) || !Number.isSafeInteger(num)) {
				throw new Error('Sayı güvenli tam sayı aralığının dışında (konum ' + t.pos + ').');
			}
			return num;
		}
		if ('IDENT' === t.type && ('true' === t.value || 'false' === t.value)) {
			next();
			return 'true' === t.value;
		}
		if ('IDENT' === t.type && 'null' === t.value) {
			next();
			return null;
		}
		throw new Error('İzin verilmeyen değer ifadesi (konum ' + t.pos + '): "' + t.value + '" — yalnız dizi/obje/string/tam sayı/true/false/null literallerine izin verilir.');
	}

	function parseArray() {
		expect('LBRACKET');
		const arr = [];
		if ('RBRACKET' === peek().type) {
			next();
			return arr;
		}
		for (;;) {
			arr.push(parseValue());
			if ('COMMA' === peek().type) {
				next();
				if ('RBRACKET' === peek().type) {
					next();
					return arr;
				}
				continue;
			}
			expect('RBRACKET');
			return arr;
		}
	}

	function parseKey() {
		const t = next();
		let key;
		if ('IDENT' === t.type) {
			key = t.value;
		} else if ('STRING' === t.type) {
			key = t.value;
		} else {
			throw new Error('Geçersiz obje anahtarı (konum ' + t.pos + '): hesaplanmış/spread/getter-setter anahtarına izin verilmez.');
		}
		if (FORBIDDEN_KEYS.has(key)) {
			throw new Error('Yasaklı anahtar tespit edildi (konum ' + t.pos + '): "' + key + '".');
		}
		if (!ALLOWED_KEYS.has(key)) {
			throw new Error('İzin listesinde olmayan anahtar (konum ' + t.pos + '): "' + key + '".');
		}
		return key;
	}

	function parseObject() {
		expect('LBRACE');
		// Object.create(null): no Object.prototype in the chain at all, so
		// even IF a forbidden key ever slipped past parseKey()'s explicit
		// FORBIDDEN_KEYS check (defense-in-depth, not the only guard),
		// `obj['__proto__'] = value` could never reach the special
		// Object.prototype accessor — there is no such accessor on a
		// null-prototype object, so it would just become an ordinary own
		// property instead of silently repointing the object's prototype.
		const obj = Object.create(null);
		if ('RBRACE' === peek().type) {
			next();
			return obj;
		}
		for (;;) {
			const key = parseKey();
			expect('COLON');
			const value = parseValue();
			if (Object.prototype.hasOwnProperty.call(obj, key)) {
				throw new Error('Yinelenen obje anahtarı: "' + key + '".');
			}
			obj[key] = value;
			if ('COMMA' === peek().type) {
				next();
				if ('RBRACE' === peek().type) {
					next();
					return obj;
				}
				continue;
			}
			expect('RBRACE');
			return obj;
		}
	}

	// --- Program := 'window' '.' globalName '=' Value ';'? EOF ---
	const winTok = expect('IDENT', '"window"');
	if ('window' !== winTok.value) {
		throw new Error('Tek izinli ifade "window.' + globalName + ' = [ ... ];" biçiminde olmalı; "' + winTok.value + '" ile başlıyor.');
	}
	expect('DOT');
	const nameTok = expect('IDENT', 'global adı');
	if (nameTok.value !== globalName) {
		throw new Error('Beklenen global "window.' + globalName + '", bulunan "window.' + nameTok.value + '".');
	}
	expect('EQUALS');
	const value = parseValue();
	if (!Array.isArray(value)) {
		throw new Error('window.' + globalName + ' bir dizi literali olmalı.');
	}
	if ('SEMI' === peek().type) {
		next();
	}
	expect('EOF', 'ifade sonu (ikinci ifade/atamaya izin verilmez)');

	return value;
}

// ---------------------------------------------------------------------
// §3 (Faz 6A Son Kabul Düzeltmesi) — kaynak türüne özel, derin şekil
// sözleşmesi. Ayrıştırıcının genel ALLOWED_KEYS'i bilinçli olarak GENİŞ
// tutulur (üç kaynağın tüm alanlarının birleşimi + iç seçenek alanları) —
// bu yalnız "hiç tanınmayan/tehlikeli anahtar" katmanıdır. Aşağıdaki katman
// AYRI ve DAHA DAR: her kayıt türü için TAM izinli+zorunlu anahtar kümesini
// ve her alanın tipini/boşluk kuralını uygular — böylece "sektör kaydında
// sourcePage" gibi bir kaynak-türleri-arası karışma da reddedilir, sadece
// "tamamen bilinmeyen bir anahtar" değil.
// ---------------------------------------------------------------------

function isPlainObjectValue(value) {
	return null !== value && 'object' === typeof value && !Array.isArray(value);
}

/**
 * @param {*} value
 * @param {{required: string[], optional?: string[], fields: Object<string, object>}} shape
 * @param {string} labelForErrors
 */
function validateShape(value, shape, labelForErrors) {
	if (!isPlainObjectValue(value)) {
		throw new Error(labelForErrors + ': kayıt bir obje literali olmalı.');
	}
	const optional = shape.optional || [];
	const allowed = new Set(shape.required.concat(optional));
	const presentKeys = Object.keys(value);

	presentKeys.forEach(function (key) {
		if (!allowed.has(key)) {
			throw new Error(labelForErrors + ': bu kayıt türü için izinli olmayan alan ("' + key + '").');
		}
	});
	shape.required.forEach(function (key) {
		if (!Object.prototype.hasOwnProperty.call(value, key)) {
			throw new Error(labelForErrors + ': zorunlu alan eksik ("' + key + '").');
		}
	});

	presentKeys.forEach(function (key) {
		validateFieldValue(value[key], shape.fields[key], labelForErrors + '.' + key);
	});
}

function validateFieldValue(fieldValue, fieldSpec, labelForErrors) {
	if (!fieldSpec) {
		throw new Error(labelForErrors + ': bu alan için tip tanımı yok (dahili hata — şema eksik).');
	}
	switch (fieldSpec.type) {
		case 'string':
			if ('string' !== typeof fieldValue) {
				throw new Error(labelForErrors + ': string olmalı.');
			}
			if (fieldSpec.nonEmpty && '' === fieldValue) {
				throw new Error(labelForErrors + ': boş bırakılamaz.');
			}
			break;
		case 'integer':
			if ('number' !== typeof fieldValue || !Number.isInteger(fieldValue)) {
				throw new Error(labelForErrors + ': tam sayı olmalı.');
			}
			if (undefined !== fieldSpec.min && fieldValue < fieldSpec.min) {
				throw new Error(labelForErrors + ': ' + fieldSpec.min + ' değerinden küçük olamaz.');
			}
			if (undefined !== fieldSpec.max && fieldValue > fieldSpec.max) {
				throw new Error(labelForErrors + ': ' + fieldSpec.max + ' değerinden büyük olamaz.');
			}
			break;
		case 'boolean':
			if ('boolean' !== typeof fieldValue) {
				throw new Error(labelForErrors + ': true/false olmalı.');
			}
			break;
		case 'array':
			if (!Array.isArray(fieldValue)) {
				throw new Error(labelForErrors + ': dizi olmalı.');
			}
			if (undefined !== fieldSpec.minItems && fieldValue.length < fieldSpec.minItems) {
				throw new Error(labelForErrors + ': en az ' + fieldSpec.minItems + ' öğe içermeli.');
			}
			fieldValue.forEach(function (item, i) {
				if (fieldSpec.itemShape) {
					validateShape(item, fieldSpec.itemShape, labelForErrors + '[' + i + ']');
				} else if (fieldSpec.itemType) {
					validateFieldValue(item, { type: fieldSpec.itemType, nonEmpty: fieldSpec.itemNonEmpty }, labelForErrors + '[' + i + ']');
				}
			});
			break;
		default:
			throw new Error(labelForErrors + ': bilinmeyen alan tipi (dahili hata).');
	}
}

/** fees.js price-option satırı — yalnız label/units/amount; units opsiyonel, diğer ikisi zorunlu. */
const FEE_OPTION_SHAPE = {
	required: ['label', 'amount'],
	optional: ['units'],
	fields: {
		label: { type: 'string', nonEmpty: true },
		amount: { type: 'integer', min: 1 },
		units: { type: 'array', itemType: 'string', itemNonEmpty: true },
	},
};

const SECTOR_RECORD_SHAPE = {
	required: ['slug', 'name', 'desc', 'icon', 'image'],
	optional: [],
	fields: {
		slug: { type: 'string', nonEmpty: true },
		name: { type: 'string', nonEmpty: true },
		desc: { type: 'string' },
		icon: { type: 'string', nonEmpty: true },
		image: { type: 'string' },
	},
};

const QUALIFICATION_RECORD_SHAPE = {
	required: ['code', 'name', 'level', 'sector'],
	optional: [],
	fields: {
		code: { type: 'string', nonEmpty: true },
		name: { type: 'string', nonEmpty: true },
		level: { type: 'integer', min: 1, max: 8 },
		sector: { type: 'string', nonEmpty: true },
	},
};

const FEE_RECORD_SHAPE = {
	required: [
		'name', 'level', 'sector', 'qualificationCode', 'pricingType',
		'options', 'vatIncluded', 'certificatePrintFeeExcluded', 'source', 'sourcePage',
	],
	optional: [],
	fields: {
		name: { type: 'string', nonEmpty: true },
		level: { type: 'integer', min: 1, max: 8 },
		sector: { type: 'string', nonEmpty: true },
		// qualificationCode is deliberately allowed to be an empty string —
		// that is the documented "no MYK code in the source PDF" case
		// (19 real records), never fabricated, never rejected here.
		qualificationCode: { type: 'string' },
		pricingType: { type: 'string', nonEmpty: true },
		options: { type: 'array', minItems: 1, itemShape: FEE_OPTION_SHAPE },
		vatIncluded: { type: 'boolean' },
		certificatePrintFeeExcluded: { type: 'integer', min: 1 },
		source: { type: 'string', nonEmpty: true },
		sourcePage: { type: 'integer', min: 1 },
	},
};

/** news.js kaydı (Faz 7): 6 gerçek haber/duyuru — tarih ve başlıklar gerçektir; image alanı okunur ama aktarılmaz. */
const NEWS_RECORD_SHAPE = {
	required: ['slug', 'title', 'date', 'type', 'image', 'summary', 'body'],
	optional: [],
	fields: {
		slug: { type: 'string', nonEmpty: true },
		title: { type: 'string', nonEmpty: true },
		date: { type: 'string', nonEmpty: true },
		type: { type: 'string', nonEmpty: true },
		image: { type: 'string', nonEmpty: true },
		summary: { type: 'string', nonEmpty: true },
		body: { type: 'string', nonEmpty: true },
	},
};

/** references.js kaydı (Faz 7): TEMSİLİ referans logoları — gerçek müşteri DEĞİL. */
const REFERENCE_RECORD_SHAPE = {
	required: ['name', 'file', 'alt'],
	optional: [],
	fields: {
		name: { type: 'string', nonEmpty: true },
		file: { type: 'string', nonEmpty: true },
		alt: { type: 'string', nonEmpty: true },
	},
};

const RECORD_SHAPES = {
	sectors: SECTOR_RECORD_SHAPE,
	qualifications: QUALIFICATION_RECORD_SHAPE,
	fees: FEE_RECORD_SHAPE,
	news: NEWS_RECORD_SHAPE,
	references: REFERENCE_RECORD_SHAPE,
};

/**
 * @param {'sectors'|'qualifications'|'fees'|'news'|'references'} key
 * @returns {{data: Array, repoRelativePath: string, sha256: string}}
 */
function extractOne(key) {
	const cfg = SOURCES[key];
	if (!cfg) {
		throw new Error('Bilinmeyen kaynak anahtarı: ' + key);
	}

	const absPath = path.join(REPO_ROOT, cfg.repoRelativePath);
	const raw = fs.readFileSync(absPath, 'utf8');
	const sha = sha256Hex(Buffer.from(raw, 'utf8'));

	const withoutComments = stripFullLineComments(raw).trim();
	if ('' === withoutComments) {
		throw new Error(cfg.repoRelativePath + ': kaynak boş (yorum hariç hiçbir ifade yok).');
	}
	const value = parseLiteral(withoutComments, cfg.globalName);

	// §3: parser'ın genel/birleşik ALLOWED_KEYS'inden BAĞIMSIZ, kaynak
	// türüne özel tam şekil doğrulaması — "sektörde sourcePage" gibi bir
	// kaynaklar-arası alan karışmasını burada, kaynak anahtarı (key)
	// bilinirken yakalar.
	const recordShape = RECORD_SHAPES[key];
	value.forEach(function (record, index) {
		validateShape(record, recordShape, cfg.repoRelativePath + '[' + index + ']');
	});

	return { data: value, repoRelativePath: cfg.repoRelativePath, sha256: sha };
}

function extractAll() {
	return {
		sectors: extractOne('sectors'),
		qualifications: extractOne('qualifications'),
		fees: extractOne('fees'),
	};
}

/** Faz 7 — iki İÇERİK kaynağı (news.js, references.js); extractAll() bunlara DOKUNMAZ. */
function extractContent() {
	return {
		news: extractOne('news'),
		references: extractOne('references'),
	};
}

module.exports = {
	extractOne,
	extractAll,
	extractContent,
	SOURCES,
	REPO_ROOT,
	ALLOWED_KEYS,
	FORBIDDEN_KEYS,
	RECORD_SHAPES,
	// Exported ONLY for tests/test-extract-safety.js to exercise the parser
	// directly against crafted in-memory payload strings — never used to
	// read an arbitrary path; extractOne()'s three hardcoded paths remain
	// the only files this module ever opens. parseLiteral() NEVER executes
	// the input; it only tokenizes and builds a plain JS value tree.
	_internal: { stripFullLineComments, parseLiteral, tokenize, validateShape },
};
