<?php
/**
 * Sanitization and validation helpers used by the meta schema and admin
 * meta boxes.
 *
 * Methods are split in two groups:
 * - "sanitize_*" methods never fail; they always return a clean value
 *   (used as register_post_meta sanitize_callback and as the first pass
 *   before saving from the admin screen).
 * - "validate_*" methods return true or a Turkish error message string;
 *   used by the admin save handler to decide whether to keep the
 *   sanitized value or fall back to the previous stored value with a
 *   visible notice (see class-meta-boxes.php).
 *
 * Format/range checks ("format" group below) use no WordPress functions
 * and can be exercised by tests/ without a WordPress bootstrap. Checks
 * that need the database ("db" group) are kept separate for that reason.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Validator {

	/**
	 * Limits enforced on the ücret price-options repeater. Documented
	 * here (and in docs/content-model.md) so max_input_vars risk stays
	 * bounded — see görev kartı 02 §8 / Faz2 düzeltme brief §3.3.
	 */
	const MAX_PRICE_OPTIONS       = 20;
	const MAX_OPTION_LABEL_LENGTH = 200;
	const MAX_UNITS_PER_OPTION    = 10;
	const MAX_UNIT_LENGTH         = 50;

	/* ------------------------------------------------------------------ *
	 * Format group — no WordPress functions, pure PHP.
	 * ------------------------------------------------------------------ */

	public static function is_valid_integer_range( $value, $min, $max = null ) {
		if ( is_string( $value ) && ! preg_match( '/^-?[0-9]+$/', trim( $value ) ) ) {
			return false;
		}
		if ( ! is_numeric( $value ) || ( is_float( $value ) && floor( $value ) !== $value ) ) {
			return false;
		}
		$int = (int) $value;
		if ( $int < $min ) {
			return false;
		}
		if ( null !== $max && $int > $max ) {
			return false;
		}
		return true;
	}

	public static function is_valid_ymd_date( $value ) {
		if ( '' === $value || null === $value ) {
			return true; // Empty is allowed; required-ness is checked separately.
		}
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $value ) );
		return checkdate( $m, $d, $y );
	}

	public static function is_valid_datetime( $value ) {
		if ( '' === $value || null === $value ) {
			return true;
		}
		// Hour 00-23, minute/second 00-59 — a loose \d{2}:\d{2} pattern
		// would let "99:99" through; this bounds the ranges directly.
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} (?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value ) ) {
			return false;
		}
		$date_part = substr( $value, 0, 10 );
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date_part ) );
		return checkdate( $m, $d, $y );
	}

	public static function is_valid_select( $value, array $allowed ) {
		return in_array( $value, $allowed, true );
	}

	public static function is_valid_slug_format( $value ) {
		if ( '' === $value ) {
			return true;
		}
		return (bool) preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $value );
	}

	/**
	 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı Bulgu #1 kapatıldı: gerçek
	 * kaynakta (tanitim-site/assets/data/qualifications.js, 83 kayıt)
	 * 63 kod "/NN" revizyon eki İLE, 20 kod ise revizyon eki OLMADAN
	 * geliyor (ör. "13UY0145-3") — eski regex revizyon ekini ZORUNLU
	 * kılıyor ve bu 20 gerçek kaydı reddediyordu. Revizyon artık
	 * OPSİYONEL: hem "10UY0002-3/03" hem "13UY0145-3" biçimi geçerli.
	 */
	public static function is_valid_myk_code_format( $value ) {
		if ( '' === $value ) {
			return true;
		}
		return (bool) preg_match( '/^[0-9]{2}UY[0-9]{4}-[0-9]{1,2}(\/[0-9]{2})?$/', $value );
	}

	/**
	 * TEK ayrıştırma noktası — kod içine gömülü seviye/revizyonu çıkarır.
	 * Hem admin kaydı (class-meta-boxes.php), hem yayın hazırlık kapısı
	 * (class-publish-readiness.php), hem de ileride programatik içe
	 * aktarım (Faz 6B) bu TEK metodu çağırmalı; ikinci bir regex kopyası
	 * açılmamalıdır.
	 *
	 * @return array{valid:bool, level:int|null, revision:string, has_revision:bool}
	 */
	public static function parse_myk_code( $code ) {
		$code = trim( (string) $code );
		if ( '' === $code ) {
			return array( 'valid' => false, 'level' => null, 'revision' => '', 'has_revision' => false );
		}
		if ( preg_match( '/^[0-9]{2}UY[0-9]{4}-([0-9]{1,2})\/([0-9]{2})$/', $code, $m ) ) {
			return array( 'valid' => true, 'level' => (int) $m[1], 'revision' => $m[2], 'has_revision' => true );
		}
		if ( preg_match( '/^[0-9]{2}UY[0-9]{4}-([0-9]{1,2})$/', $code, $m ) ) {
			return array( 'valid' => true, 'level' => (int) $m[1], 'revision' => '', 'has_revision' => false );
		}
		return array( 'valid' => false, 'level' => null, 'revision' => '', 'has_revision' => false );
	}

	/**
	 * Çapraz-alan kuralı (Faz 6A Son Kabul Düzeltmesi'nde kesinleştirildi):
	 *
	 * 1. Kod boşsa her zaman true (kod opsiyonel, mevcut davranış korunur).
	 * 2. Kod DOLU ise biçimi geçerli olmalı.
	 * 3. Koda gömülü seviye _mb_level ile TAM eşleşmeli.
	 * 4. Kod "/NN" taşıyorsa _mb_revision TAM OLARAK "NN" olmalı.
	 * 5. Kod suffix TAŞIMIYORSA _mb_revision TAM OLARAK BOŞ olmalı.
	 *
	 * Önceki sürümde 5. kural yoktu — revizyonsuz bir kodda _mb_revision
	 * "serbest alan" sayılıyordu (ör. "13UY0145-3" + revizyon "99" true
	 * dönüyordu). Bağımsız inceleme bunun Node tarafındaki manifest
	 * doğrulayıcısıyla (revizyonsuz kodda revision alanının kesinlikle
	 * boş olmasını şart koşan lib/validate-manifest-set.js) çeliştiğini
	 * gösterdi — artık ikisi de AYNI kesin kuralı uyguluyor.
	 */
	public static function myk_code_matches_level_revision( $code, $level, $revision ) {
		$code = trim( (string) $code );
		if ( '' === $code ) {
			return true;
		}
		$parsed = self::parse_myk_code( $code );
		if ( ! $parsed['valid'] ) {
			return false;
		}
		if ( (int) $level !== $parsed['level'] ) {
			return false;
		}
		$revision = trim( (string) $revision );
		if ( $parsed['has_revision'] ) {
			return $revision === $parsed['revision'];
		}
		return '' === $revision;
	}

	/**
	 * Faz 6A Son Kabul Düzeltmesi §7 — Faz 6B'nin sistem-yönetimli import
	 * kimlik alanları (`_mb_import_source_key`) için TEK paylaşılan biçim
	 * kuralı. Boş değer her zaman geçerlidir (alan henüz doldurulmamış
	 * demektir — bu fazda hiçbir değer YAZILMAZ). `$type` şu anki manifest
	 * source_key ailelerinden biriyle TAM eşleşmeli: 'qualification'
	 * (yeterlilik), 'fee' (ücret), 'sector' (mb_sektor term-meta). Kontrol
	 * karakteri/boşluk/başka prefix, regex'in kendisi [a-z0-9/:-] dışına
	 * hiç izin vermediği için ayrıca kontrol edilmesine gerek kalmadan
	 * reddedilir.
	 */
	public static function is_valid_import_source_key( $value, $type ) {
		// Faz 6B3 Önkoşul — dizi/nesne/sayı ASLA (string) cast ile "Array"
		// vb. metne çevrilmez; sınıflandırma tek kanonik
		// classify_import_source_key() kuralına devredilir. Mevcut
		// sözleşme korunur: yalnız string girdide baş/son boşluk kırpılır,
		// boş (veya null = meta yok) geçerli "henüz doldurulmamış" sayılır.
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		$class = self::classify_import_source_key( $value, $type );
		return self::IMPORT_KEY_EMPTY === $class || self::IMPORT_KEY_VALID === $class;
	}

	/** classify_import_source_key() sonuç sınıfları. */
	const IMPORT_KEY_EMPTY        = 'empty';
	const IMPORT_KEY_VALID        = 'valid';
	const IMPORT_KEY_WRONG_PREFIX = 'wrong_prefix';
	const IMPORT_KEY_MALFORMED    = 'malformed';
	const IMPORT_KEY_NOT_STRING   = 'not_string';

	/**
	 * Faz 6B3 Önkoşul ve Yazma Güvenliği — `_mb_import_source_key` için TEK
	 * kanonik sınıflandırıcı (is_valid_import_source_key(), meta sanitize
	 * callback'leri, yazma yükü doğrulayıcısı ve doğal anahtar preflight'ı
	 * hepsi BUNU kullanır; bağımsız kopya regex yoktur).
	 *
	 * Kırpma (trim) YAPMAZ — ham değeri olduğu gibi sınıflandırır:
	 * - null veya ''                              -> empty
	 * - string olmayan (dizi/nesne/int/bool/float) -> not_string
	 * - `$type . ':'` önekiyle başlamayan string   -> wrong_prefix (başka aile, bilinmeyen önek, boşluk dahil)
	 * - doğru önek ama biçim/uzunluk geçersiz      -> malformed
	 * - tam biçim eşleşmesi                        -> valid
	 * Geçerli aileler: 'sector', 'qualification', 'fee', 'news', 'reference'
	 * (Faz 7: news/reference sector ile aynı slug kuralı). Desenler `\z`
	 * ile biter (satır-sonu toleranslı `$` kullanılmaz).
	 *
	 * @param mixed  $value
	 * @param string $type
	 * @return string self::IMPORT_KEY_*
	 */
	public static function classify_import_source_key( $value, $type ) {
		if ( null === $value || '' === $value ) {
			return self::IMPORT_KEY_EMPTY;
		}
		if ( ! is_string( $value ) ) {
			return self::IMPORT_KEY_NOT_STRING;
		}
		if ( ! in_array( $type, array( 'qualification', 'fee', 'sector', 'news', 'reference' ), true ) ) {
			return self::IMPORT_KEY_MALFORMED;
		}
		if ( 0 !== strpos( $value, $type . ':' ) ) {
			return self::IMPORT_KEY_WRONG_PREFIX;
		}
		if ( strlen( $value ) > 200 ) {
			return self::IMPORT_KEY_MALFORMED;
		}
		switch ( $type ) {
			case 'qualification':
				$ok = preg_match( '/^qualification:[0-9]{2}UY[0-9]{4}-[0-9]{1,2}(\/[0-9]{2})?\z/', $value );
				break;
			case 'fee':
				$ok = preg_match( '/^fee:[a-z0-9]+(-[a-z0-9]+)*:[1-8]:[a-z0-9]+(-[a-z0-9]+)*\z/', $value );
				break;
			case 'news':
				// Faz 7 içerik aktarımı: news:<slug> (sector ile AYNI slug kuralı).
				$ok = preg_match( '/^news:[a-z0-9]+(-[a-z0-9]+)*\z/', $value );
				break;
			case 'reference':
				$ok = preg_match( '/^reference:[a-z0-9]+(-[a-z0-9]+)*\z/', $value );
				break;
			default: // sector
				$ok = preg_match( '/^sector:[a-z0-9]+(-[a-z0-9]+)*\z/', $value );
				break;
		}
		return 1 === $ok ? self::IMPORT_KEY_VALID : self::IMPORT_KEY_MALFORMED;
	}

	/**
	 * Faz 6B3 Önkoşul — TEK, saf, PHP 7.3 uyumlu onluk-string -> int
	 * ayrıştırıcı (Faz 6B2 Son Kapanış'ta repository içinde yazılmıştı;
	 * kanonik kuruş kuralı da aynı algoritmayı kullansın diye buraya
	 * taşındı — repository artık buna DEVREDER, iki kopya yoktur).
	 *
	 * Kabul: `^-?(0|[1-9][0-9]*)\z`. Baş/son boşluk, `+`, ondalık, bilimsel
	 * gösterim, sondaki satır sonu, leading zero ("007") ve "-0" reddedilir.
	 * Taşma: cast'ten ÖNCE, float KULLANILMADAN basamak-string'i
	 * PHP_INT_MAX (pozitif) / PHP_INT_MIN (negatif, işaretsiz büyüklük)
	 * ile önce uzunluk, eşit uzunlukta strcmp() ile karşılaştırılır;
	 * cast SONRASI `(string) $value === $raw` round-trip kontrolü yapılır.
	 *
	 * @param mixed $raw
	 * @return array{ok: bool, value?: int}
	 */
	public static function parse_canonical_decimal_int( $raw ) {
		if ( ! is_string( $raw ) ) {
			return array( 'ok' => false );
		}
		if ( 1 !== preg_match( '/^(-?)(0|[1-9][0-9]*)\z/', $raw, $m ) ) {
			return array( 'ok' => false );
		}
		$negative = '-' === $m[1];
		$digits   = $m[2];
		if ( $negative && '0' === $digits ) {
			return array( 'ok' => false );
		}
		$limit  = $negative ? substr( (string) PHP_INT_MIN, 1 ) : (string) PHP_INT_MAX;
		$len    = strlen( $digits );
		$limLen = strlen( $limit );
		if ( $len > $limLen || ( $len === $limLen && strcmp( $digits, $limit ) > 0 ) ) {
			return array( 'ok' => false );
		}
		$value = (int) $raw;
		if ( (string) $value !== $raw ) {
			return array( 'ok' => false );
		}
		return array( 'ok' => true, 'value' => $value );
	}

	/**
	 * Faz 6B3 Önkoşul — kanonik kuruş (integer, >= 0) doğrulayıcısı. TL
	 * DÖNÜŞTÜRMEZ: girdi zaten kuruştur. Kabul: gerçek PHP int >= 0 veya
	 * parse_canonical_decimal_int() biçiminde >= 0 string. Negatif, float,
	 * bilimsel gösterim, "1.0", "007", taşma, bool, null, dizi, nesne -> null.
	 * Sıfır geçerlidir (alan sözleşmesi: "tanımlı değil/ücret yok"); pozitif
	 * zorunluluğu olan alanlar bunu ayrıca uygular.
	 *
	 * @param mixed $raw
	 * @return int|null
	 */
	public static function canonical_kurus( $raw ) {
		if ( is_int( $raw ) ) {
			return $raw >= 0 ? $raw : null;
		}
		$parsed = self::parse_canonical_decimal_int( $raw );
		return ( $parsed['ok'] && $parsed['value'] >= 0 ) ? $parsed['value'] : null;
	}

	/**
	 * Faz 6A Son Kabul Düzeltmesi §7 — `_mb_last_applied_hash` (post meta)
	 * VE mb_sektor term-meta'daki eşdeğeri için TEK paylaşılan SHA-256
	 * biçim kuralı: tam 64 küçük-harf hex karakter, ya da boş (alan henüz
	 * doldurulmamış). Büyük harf hex ("A1B2...") kasıtlı olarak
	 * REDDEDİLİR — kaynak üretimi (Node `sha256Hex()`) her zaman küçük
	 * harf üretir; büyük harfe izin vermek aynı özeti iki farklı string
	 * olarak saklama riskini açardı.
	 */
	public static function is_valid_sha256_hash( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return true;
		}
		return (bool) preg_match( '/^[0-9a-f]{64}$/', $value );
	}

	/**
	 * True only for a string of digits (no sign, no decimal point, no
	 * leading/trailing junk after trimming). Used to check an ID BEFORE
	 * casting it to int, so "123abc" or "-5" can't silently become a
	 * valid-looking integer via PHP's (int) cast (which would read
	 * "123abc" as 123).
	 */
	public static function is_valid_positive_integer_string( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		$trimmed = trim( $value );
		return '' !== $trimmed && (bool) preg_match( '/^[0-9]+$/', $trimmed ) && (int) $trimmed > 0;
	}

	/**
	 * Converts a Turkish-formatted TL amount to a positive integer kuruş
	 * value, or null if the input isn't one of the documented formats.
	 *
	 * Single, unambiguous rule (matches the brief's own examples:
	 * "17.000", "17000", "17.000,00", "17000,00"):
	 * - "." is ONLY a thousands separator and must group digits in
	 *   threes (e.g. "1.575.000"); a "." that doesn't form a full
	 *   3-digit group is rejected rather than guessed at.
	 * - "," is ONLY a decimal separator and must be followed by
	 *   exactly 2 digits.
	 * - No sign, no scientific notation, no letters.
	 * - The result must be a positive amount (zero/negative rejected —
	 *   a price option's amount is never free or negative).
	 */
	public static function try_lira_to_kurus( $raw ) {
		if ( is_int( $raw ) || is_float( $raw ) ) {
			$raw = (string) $raw;
		}
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$raw = trim( $raw );
		if ( '' === $raw || ! preg_match( '/^[0-9.,]+$/', $raw ) ) {
			return null;
		}

		$lira  = null;
		$kurus = 0;

		if ( preg_match( '/^(\d{1,3}(?:\.\d{3})*),(\d{2})$/', $raw, $m ) ) {
			$lira  = (int) str_replace( '.', '', $m[1] );
			$kurus = (int) $m[2];
		} elseif ( preg_match( '/^(\d{1,3}(?:\.\d{3})*)$/', $raw, $m ) ) {
			$lira = (int) str_replace( '.', '', $m[1] );
		} elseif ( preg_match( '/^(\d+),(\d{2})$/', $raw, $m ) ) {
			$lira  = (int) $m[1];
			$kurus = (int) $m[2];
		} elseif ( preg_match( '/^(\d+)$/', $raw, $m ) ) {
			$lira = (int) $m[1];
		} else {
			return null;
		}

		// Overflow guard: PHP silently promotes an int*int result to
		// float once it exceeds PHP_INT_MAX instead of erroring, which
		// would let an absurdly large TL string turn into an
		// imprecise float "amount" downstream. Reject before that can
		// happen rather than trying to detect it after the fact.
		if ( $lira > (int) floor( ( PHP_INT_MAX - $kurus ) / 100 ) ) {
			return null;
		}

		$total_kurus = ( $lira * 100 ) + $kurus;

		// Belt-and-suspenders: the guard above should make this
		// unreachable, but never return anything except a positive int.
		if ( ! is_int( $total_kurus ) || $total_kurus <= 0 ) {
			return null;
		}

		return $total_kurus;
	}

	/**
	 * Formats a kuruş integer back to a Turkish-locale TL string for
	 * display (e.g. 1700000 -> "17.000,00"). Pure formatting, no
	 * WordPress dependency.
	 */
	public static function kurus_to_lira_display( $kurus ) {
		$kurus = (int) $kurus;
		return number_format( $kurus / 100, 2, ',', '.' );
	}

	/**
	 * Normalizes and validates a raw price-options array (each row
	 * already carrying an integer amount_kurus — TL-string conversion
	 * happens one layer up via try_lira_to_kurus(), see
	 * admin/class-meta-boxes.php::save_price_options()).
	 *
	 * Never throws. Deep-sanitizes label/unit text, enforces bounded
	 * limits (MAX_PRICE_OPTIONS etc.) so a request can't grow
	 * unboundedly large, and rejects unexpected array/object values in
	 * places a scalar is expected instead of silently stringifying them.
	 *
	 * This function alone does NOT decide whether to persist anything —
	 * see evaluate_price_options() for the atomic all-or-nothing
	 * decision the caller should act on.
	 *
	 * @return array{options: array, errors: array, min_kurus: int, max_kurus: int}
	 */
	public static function normalize_price_options( $raw ) {
		$options   = array();
		$errors    = array();
		$min_kurus = null;
		$max_kurus = null;

		if ( ! is_array( $raw ) ) {
			return array(
				'options'   => array(),
				'errors'    => array(),
				'min_kurus' => 0,
				'max_kurus' => 0,
			);
		}

		$sort       = 0;
		$dolu_count = 0;

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				$errors[] = 'Geçersiz fiyat seçeneği yapısı yok sayıldı.';
				continue;
			}

			$label_raw = isset( $row['label'] ) ? $row['label'] : '';
			if ( is_array( $label_raw ) || is_object( $label_raw ) ) {
				$errors[] = 'Fiyat seçeneği etiketi metin olmalıdır.';
				continue;
			}
			$label = self::clean_short_text( (string) $label_raw );

			$amount_raw = isset( $row['amount_kurus'] ) ? $row['amount_kurus'] : '';
			if ( is_array( $amount_raw ) || is_object( $amount_raw ) ) {
				$errors[] = 'Fiyat seçeneği tutarı geçersiz.';
				continue;
			}

			// A fully empty row (progressive-enhancement filler row) is
			// silently skipped, not reported as an error.
			if ( '' === $label && ( '' === $amount_raw || null === $amount_raw ) ) {
				continue;
			}

			if ( '' === $label ) {
				$errors[] = 'Fiyat seçeneği etiketi boş bırakılamaz.';
				continue;
			}

			if ( self::str_length( $label ) > self::MAX_OPTION_LABEL_LENGTH ) {
				$errors[] = sprintf( '"%s" etiketi çok uzun (en fazla %d karakter).', $label, self::MAX_OPTION_LABEL_LENGTH );
				continue;
			}

			if ( ! self::is_valid_integer_range( $amount_raw, 1 ) ) {
				$errors[] = sprintf( '"%s" seçeneği için tutar pozitif bir tam sayı (kuruş) olmalıdır.', $label );
				continue;
			}

			$dolu_count++;
			if ( $dolu_count > self::MAX_PRICE_OPTIONS ) {
				$errors[] = sprintf( 'En fazla %d fiyat seçeneği eklenebilir.', self::MAX_PRICE_OPTIONS );
				continue;
			}

			$amount = (int) $amount_raw;

			/*
			 * Faz 5 Son Kapanış Düzeltmesi §2: every one of the three old
			 * "units" failure modes below used to be silently repaired
			 * (a malformed nested item dropped, an oversized list quietly
			 * truncated, an overlong unit quietly shortened) instead of
			 * rejecting the row. That let a caller's clearly-invalid
			 * input silently turn into different, "corrected" stored
			 * data — which is exactly the kind of silent data mutation
			 * this validator's atomic-reject design (see
			 * evaluate_price_options()) exists to prevent for every OTHER
			 * field. All three now push an error and reject this row
			 * (which — because $errors becomes non-empty — rejects the
			 * ENTIRE submitted list once evaluate_price_options() sees
			 * it, never a partial one).
			 */
			$units       = array();
			$units_valid = true;
			if ( isset( $row['units'] ) ) {
				if ( ! is_array( $row['units'] ) ) {
					$errors[]    = sprintf( '"%s" seçeneği için birim listesi geçersiz.', $label );
					$units_valid = false;
				} else {
					foreach ( $row['units'] as $unit_raw ) {
						if ( is_array( $unit_raw ) || is_object( $unit_raw ) ) {
							$errors[]    = sprintf( '"%s" seçeneği için birim listesinde geçersiz (iç içe dizi/nesne) bir öğe var.', $label );
							$units_valid = false;
							break;
						}
						$unit = self::clean_short_text( (string) $unit_raw );
						if ( '' === $unit ) {
							continue; // A blank unit entry is not "dolu" — skipped, not counted, not an error.
						}
						if ( self::str_length( $unit ) > self::MAX_UNIT_LENGTH ) {
							$errors[]    = sprintf( '"%s" seçeneği için bir birim çok uzun (en fazla %d karakter).', $label, self::MAX_UNIT_LENGTH );
							$units_valid = false;
							break;
						}
						if ( count( $units ) >= self::MAX_UNITS_PER_OPTION ) {
							$errors[]    = sprintf( '"%s" seçeneği için en fazla %d birim eklenebilir.', $label, self::MAX_UNITS_PER_OPTION );
							$units_valid = false;
							break;
						}
						$units[] = $unit;
					}
				}
			}
			if ( ! $units_valid ) {
				continue;
			}

			/*
			 * Faz 5 Son Kapanış Düzeltmesi §2: sort_order is OPTIONAL —
			 * omitting it entirely keeps the documented backward-
			 * compatible fallback to submission position ($sort). But if
			 * the field IS present, it must be a genuinely valid
			 * non-negative integer; a present-but-empty/negative/non-
			 * numeric/array/object value used to silently fall back to
			 * the same position value as if it had never been submitted
			 * at all — indistinguishable from "not sent", which hid a
			 * caller's bug instead of rejecting it.
			 */
			if ( array_key_exists( 'sort_order', $row ) ) {
				if ( ! self::is_valid_integer_range( $row['sort_order'], 0 ) ) {
					$errors[] = sprintf( '"%s" seçeneği için sıra değeri geçerli, negatif olmayan bir tam sayı olmalıdır.', $label );
					continue;
				}
				$sort_order = (int) $row['sort_order'];
			} else {
				$sort_order = $sort;
			}

			$options[] = array(
				'label'        => $label,
				'units'        => $units,
				'amount_kurus' => $amount,
				'sort_order'   => $sort_order,
				// Internal-only: original kept-row submission order, used
				// solely as the deterministic sort() tie-breaker below —
				// stripped before this array is ever returned.
				'_orig'        => $sort,
			);

			$min_kurus = ( null === $min_kurus ) ? $amount : min( $min_kurus, $amount );
			$max_kurus = ( null === $max_kurus ) ? $amount : max( $max_kurus, $amount );
			$sort++;
		}

		/*
		 * Canonical, stable ordering. usort() is NOT guaranteed stable on
		 * PHP < 8.0 (this codebase targets 7.3), so two rows sharing the
		 * same sort_order could otherwise come out in an unpredictable
		 * order on repeated runs — an explicit '_orig' (submission order
		 * among kept rows) tie-break makes the result deterministic
		 * regardless of PHP version or sort algorithm. The primary
		 * comparison uses the spaceship operator (<=>), not subtraction —
		 * sort_order is only range-checked against a lower bound (0), so
		 * two very large values could otherwise overflow a subtraction
		 * into a sign-flipped float and sort incorrectly.
		 */
		usort(
			$options,
			function ( $a, $b ) {
				$cmp = $a['sort_order'] <=> $b['sort_order'];
				return 0 !== $cmp ? $cmp : ( $a['_orig'] <=> $b['_orig'] );
			}
		);
		foreach ( $options as $index => $option ) {
			unset( $options[ $index ]['_orig'] );
		}

		return array(
			'options'   => array_values( $options ),
			'errors'    => $errors,
			'min_kurus' => null === $min_kurus ? 0 : $min_kurus,
			'max_kurus' => null === $max_kurus ? 0 : $max_kurus,
		);
	}

	/**
	 * Wraps normalize_price_options() with the atomic save/keep-existing
	 * decision as a pure, directly testable result: 'replace' is true
	 * only when every non-blank submitted row was valid. The caller
	 * (admin/class-meta-boxes.php) must not write anything when
	 * 'replace' is false — the entire old list is kept, not a partial
	 * one (Faz2 düzeltme brief §3.3: "kısmi liste kaydetme").
	 *
	 * @return array{options: array, errors: array, min_kurus: int, max_kurus: int, replace: bool}
	 */
	public static function evaluate_price_options( $raw ) {
		$result             = self::normalize_price_options( $raw );
		$result['replace']  = empty( $result['errors'] );
		return $result;
	}

	/**
	 * The ONE place that parses the admin repeater's raw request shape
	 * (rows of label / amount_try (TL string) / units (comma-separated
	 * string) / sort_order) into kuruş-based rows and evaluates them.
	 * Both admin/class-meta-boxes.php::save_price_options() and
	 * includes/class-publish-readiness.php call this — no second copy
	 * of the parsing exists (Faz2 ikinci düzeltme brief §2: "iki ayrı
	 * ve zamanla ayrışacak dönüştürme kopyası bırakma").
	 *
	 * Every field's *shape* (scalar vs. array/object) is checked BEFORE
	 * any cast — this is exactly what a prior version got wrong: it
	 * cast $row['label'] to (string) unconditionally, so a crafted
	 * `label[]=x` request silently became the literal text "Array"
	 * instead of being rejected. Nothing here reaches (string)$x
	 * without first confirming $x isn't an array/object.
	 *
	 * Error messages never echo the raw submitted value back (only the
	 * row's own label, once it's confirmed to be a plain string) —
	 * Faz2 ikinci düzeltme brief §2's "ham saldırgan içeriği ...
	 * yönetim ekranına veya loga yazma" requirement.
	 *
	 * @return array{options: array, errors: array, min_kurus: int, max_kurus: int, replace: bool}
	 */
	public static function evaluate_admin_price_rows( $raw_rows ) {
		$parsed = array();
		$errors = array();

		if ( ! is_array( $raw_rows ) ) {
			$errors[] = 'Fiyat seçenekleri beklenmeyen biçimde gönderildi.';
			return array(
				'options'   => array(),
				'errors'    => $errors,
				'min_kurus' => 0,
				'max_kurus' => 0,
				'replace'   => false,
			);
		}

		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				$errors[] = 'Geçersiz fiyat seçeneği satırı yok sayıldı.';
				continue;
			}

			$label_raw = isset( $row['label'] ) ? $row['label'] : '';
			if ( is_array( $label_raw ) || is_object( $label_raw ) ) {
				$errors[] = 'Fiyat seçeneği etiketi metin olmalıdır.';
				continue;
			}
			$label = trim( (string) $label_raw );
			$safe_label = '' !== $label ? $label : 'İsimsiz seçenek';

			$amount_try_raw = isset( $row['amount_try'] ) ? $row['amount_try'] : '';
			if ( is_array( $amount_try_raw ) || is_object( $amount_try_raw ) ) {
				$errors[] = sprintf( '"%s" seçeneği için tutar geçersiz biçimde gönderildi.', $safe_label );
				continue;
			}
			$amount_try = trim( (string) $amount_try_raw );

			$units_raw = isset( $row['units'] ) ? $row['units'] : '';
			if ( is_array( $units_raw ) || is_object( $units_raw ) ) {
				$errors[] = sprintf( '"%s" seçeneği için birim listesi geçersiz biçimde gönderildi.', $safe_label );
				continue;
			}
			$units_raw = (string) $units_raw;

			$sort_order_raw = isset( $row['sort_order'] ) ? $row['sort_order'] : '';
			if ( is_array( $sort_order_raw ) || is_object( $sort_order_raw ) ) {
				$errors[] = sprintf( '"%s" seçeneği için sıra değeri geçersiz biçimde gönderildi.', $safe_label );
				continue;
			}

			if ( '' === $label && '' === $amount_try ) {
				continue; // Blank progressive-enhancement filler row — not an error.
			}

			if ( '' === $amount_try ) {
				$errors[] = sprintf( '"%s" seçeneği için TL tutarı girilmelidir.', $safe_label );
				continue;
			}

			$kurus = self::try_lira_to_kurus( $amount_try );
			if ( null === $kurus ) {
				// Deliberately not echoing the raw $amount_try value here.
				$errors[] = sprintf( '"%s" seçeneği için geçerli bir TL tutarı girilmedi (örn. 17.000 veya 17.000,00).', $safe_label );
				continue;
			}

			/*
			 * Faz 5 Son Kapanış Düzeltmesi §2: normalize_price_options()
			 * treats a PRESENT 'sort_order' key as a real, required
			 * value — invalid/empty/negative/array/object now rejects
			 * the whole submission, it no longer silently falls back to
			 * submission position. The real admin repeater
			 * (admin/assets/meta-boxes.js) always renders each row's
			 * sort_order <input type="number"> pre-filled with that
			 * row's numeric index at creation time — a genuinely blank
			 * value only happens if an admin manually clears that field,
			 * which is exactly the "sent something invalid" case this
			 * strictness is meant to catch, not a normal empty-filler-row
			 * state (that's the separate, still-allowed "label AND
			 * amount_try both blank" skip above). So the key is always
			 * forwarded as submitted, with no special-casing here.
			 */
			$parsed[] = array(
				'label'        => $label,
				'units'        => explode( ',', $units_raw ),
				'amount_kurus' => $kurus,
				'sort_order'   => $sort_order_raw,
			);
		}

		$normalized = self::normalize_price_options( $parsed );
		$all_errors = array_merge( $errors, $normalized['errors'] );

		return array(
			'options'   => $normalized['options'],
			'errors'    => $all_errors,
			'min_kurus' => $normalized['min_kurus'],
			'max_kurus' => $normalized['max_kurus'],
			'replace'   => empty( $all_errors ),
		);
	}

	/**
	 * Short free-text cleaner shared by price-option labels/units. Uses
	 * WordPress's sanitize_text_field() when available (admin save
	 * path); falls back to an equivalent pure-PHP pass so this stays
	 * exercisable from the standalone test runner (tests/run.php),
	 * which has no WordPress bootstrap.
	 */
	private static function clean_short_text( $value ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return trim( sanitize_text_field( $value ) );
		}
		$value = trim( strip_tags( $value ) );
		return preg_replace( '/\s+/', ' ', $value );
	}

	/** mbstring-safe length, falling back to byte length if unavailable. */
	private static function str_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/** mbstring-safe truncate, falling back to byte substr if unavailable. */
	private static function str_truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	public static function normalize_string_list( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $item ) {
			if ( is_array( $item ) || is_object( $item ) ) {
				continue; // Nested array/object where a scalar is expected — skip, don't stringify.
			}
			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	public static function normalize_phone_list( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $item ) {
			if ( is_array( $item ) || is_object( $item ) ) {
				continue;
			}
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}
			// Keep digits, spaces, +, ( ) and -; strip everything else.
			$clean = trim( preg_replace( '/[^0-9+\s()\-]/', '', $item ) );
			$clean = preg_replace( '/\s+/', ' ', $clean );
			if ( '' !== $clean ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * DB-dependent group — needs WordPress functions.
	 * ------------------------------------------------------------------ */

	public static function post_exists_of_type( $post_id, $post_type ) {
		if ( ! function_exists( 'get_post' ) ) {
			return false;
		}
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		$post = get_post( $post_id );
		return $post && $post->post_type === $post_type;
	}

	public static function normalize_id_list_of_type( $raw, $post_type ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $id ) {
			// Format check BEFORE casting: (int) "123abc" silently
			// yields 123 in PHP, which would let garbage input through
			// as if it were a real ID.
			if ( ! self::is_valid_positive_integer_string( $id ) ) {
				continue;
			}
			$id_int = (int) ( is_string( $id ) ? trim( $id ) : $id );
			if ( self::post_exists_of_type( $id_int, $post_type ) ) {
				$out[] = $id_int;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function user_exists( $user_id ) {
		if ( ! function_exists( 'get_user_by' ) ) {
			return false;
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return true; // 0 = "unassigned", allowed.
		}
		return (bool) get_user_by( 'id', $user_id );
	}

	/**
	 * MYK code + level + revision uniqueness. Empty codes never collide
	 * with each other or with anything else.
	 *
	 * Uses post__not_in (not "exclude" — that key is not read by
	 * WP_Query/get_posts at all, so a prior version of this method
	 * silently included the post being saved as its own candidate and
	 * could clear a valid code on every re-save; see Faz2 düzeltme
	 * brief §3.1).
	 *
	 * Trash policy: post_status intentionally does NOT include 'trash'.
	 * A trashed qualification's MYK code is free to be reused by
	 * another (or the same, if restored and re-edited) record — trash
	 * is being discarded, not an active duplicate. Documented in
	 * docs/content-model.md.
	 *
	 * @param int    $post_id  Current post being saved (excluded from the check).
	 * @param string $code
	 * @param int    $level
	 * @param string $revision
	 * @return bool True if unique (or code is empty), false if a collision exists.
	 */
	public static function is_myk_combination_unique( $post_id, $code, $level, $revision ) {
		$code = trim( (string) $code );
		if ( '' === $code ) {
			return true;
		}
		if ( ! function_exists( 'get_posts' ) ) {
			return true; // Cannot check outside WordPress; caller decides.
		}

		$candidates = get_posts(
			array(
				'post_type'      => 'mb_yeterlilik',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( (int) $post_id ),
				'meta_query'     => array(
					array(
						'key'   => '_mb_myk_code',
						'value' => $code,
					),
				),
			)
		);

		foreach ( $candidates as $other_id ) {
			$other_level    = get_post_meta( $other_id, '_mb_level', true );
			$other_revision = get_post_meta( $other_id, '_mb_revision', true );
			if ( (int) $other_level === (int) $level && trim( (string) $other_revision ) === trim( (string) $revision ) ) {
				return false;
			}
		}

		return true;
	}
}
