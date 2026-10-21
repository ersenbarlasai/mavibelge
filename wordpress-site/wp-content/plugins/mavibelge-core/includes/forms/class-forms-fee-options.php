<?php
/**
 * Faz 16 — online başvuru meslek seçenekleri: ziyaretçiye açık aktif ÜCRET kayıtlarından (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Satırlar çağırandan gelir (MaviBelge_Core_Catalog_Service::get_application_fee_rows(): Sınav Ücretleri sayfasının kullandığı
 * TEK görünürlük kuralı). Kurallar:
 *  - Seçenek kimliği ücret kaydının WordPress yazı kimliğidir (pozitif tam sayı). MYK kodu kimlik DEĞİLDİR: kodsuz ücretler ve
 *    aynı yeterliliğe bağlı farklı tarifeler vardır. Ücret kaydı başına TEK seçenek (fiyat seçeneği sayısı önemsizdir).
 *  - Etiket "{Meslek adı} — Seviye {N} — {MYK kodu}"; seviye 1-8 değilse ve kod yoksa o parça UYDURULMAZ.
 *  - Aynı etiketi taşıyan farklı ücretler birleştirilmez; hepsine " — Ücret kaydı #{ID}" eklenir.
 *  - Sıralama Türkçe alfabeyle (ç, ğ, ı, ö, ş, ü; I/ı, İ/i), sonra seviye, kod ve kimlik.
 *  - Ön seçim: `ucret` (kimlik) TAM eşleşme; `meslek` (MYK kodu) yalnız TEK uygun ücrete çözülürse. Birden fazla tarifeye
 *    çözülen kod hiçbir şey seçmez; çağıran ziyaretçiden seçim ister.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Fee_Options {

	/** Kimlik için makul üst sınır (PHP_INT_MAX 19 hanedir; daha uzun değer bozuk kabul edilir). */
	const MAX_ID_DIGITS = 18;

	/** Türkçe alfabe sırası (q, w, x Türkçe dışı harfler olarak komşularının arasına yerleşir). */
	const ALPHABET = 'abcçdefgğhıijklmnoöpqrsştuüvwxyz';

	/**
	 * @param array<int, array{id: int, profession_name: string, level: string, qualification_code: string}> $rows
	 * @return array<int, string> ücret kimliği => etiket (Türkçe sıralı)
	 */
	public static function build( array $rows ) {
		$valid = array();
		foreach ( $rows as $row ) {
			$id   = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$name = isset( $row['profession_name'] ) ? self::single_line( $row['profession_name'] ) : '';
			if ( $id <= 0 || '' === $name || isset( $valid[ $id ] ) ) {
				continue;
			}
			$valid[ $id ] = array(
				'id'    => $id,
				'name'  => $name,
				'level' => isset( $row['level'] ) ? (string) $row['level'] : '',
				'code'  => isset( $row['qualification_code'] ) ? self::single_line( $row['qualification_code'] ) : '',
			);
		}
		$sorted = array_values( $valid );
		usort( $sorted, array( __CLASS__, 'compare' ) );

		$labels = array();
		$counts = array();
		foreach ( $sorted as $row ) {
			$labels[ $row['id'] ] = self::label( $row['name'], $row['level'], $row['code'] );
			$counts[ $labels[ $row['id'] ] ] = isset( $counts[ $labels[ $row['id'] ] ] ) ? $counts[ $labels[ $row['id'] ] ] + 1 : 1;
		}
		foreach ( $labels as $id => $label ) {
			if ( $counts[ $label ] > 1 ) {
				$labels[ $id ] = $label . ' — Ücret kaydı #' . $id;
			}
		}
		return $labels;
	}

	/** "{Ad} — Seviye {N} — {Kod}" (seviye/kod yoksa o parça yok). */
	public static function label( $name, $level, $code ) {
		$label = self::single_line( $name );
		if ( 1 === preg_match( '/^[1-8]$/', (string) $level ) ) {
			$label .= ' — Seviye ' . $level;
		}
		$code = self::single_line( $code );
		if ( '' !== $code ) {
			$label .= ' — ' . $code;
		}
		return $label;
	}

	/** Türkçe ad, sonra seviye, kod ve kimlik. */
	public static function compare( array $a, array $b ) {
		$cmp = strcmp( self::sort_key( $a['name'] ), self::sort_key( $b['name'] ) );
		if ( 0 !== $cmp ) {
			return $cmp;
		}
		$cmp = (int) $a['level'] - (int) $b['level'];
		if ( 0 !== $cmp ) {
			return $cmp;
		}
		$cmp = strcmp( (string) $a['code'], (string) $b['code'] );
		return 0 !== $cmp ? $cmp : ( (int) $a['id'] - (int) $b['id'] );
	}

	/**
	 * Türkçe karşılaştırma anahtarı (intl/mbstring gerektirmez). Büyük/küçük harf duyarsızdır; I -> ı, İ -> i. Boşluk ve
	 * noktalama rakamlardan, rakamlar harflerden önce gelir; şapkalı â/î/û düz harf sayılır.
	 */
	public static function sort_key( $text ) {
		static $weights = null;
		if ( null === $weights ) {
			$weights = array();
			$letters = preg_split( '//u', self::ALPHABET, -1, PREG_SPLIT_NO_EMPTY );
			foreach ( $letters as $i => $letter ) {
				$weights[ $letter ] = 200 + $i;
			}
			$weights['â'] = $weights['a'];
			$weights['î'] = $weights['i'];
			$weights['û'] = $weights['u'];
		}
		$upper = array( 'I' => 'ı', 'İ' => 'i', 'Ç' => 'ç', 'Ğ' => 'ğ', 'Ö' => 'ö', 'Ş' => 'ş', 'Ü' => 'ü', 'Â' => 'â', 'Î' => 'î', 'Û' => 'û' );
		$chars = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		$key   = '';
		foreach ( is_array( $chars ) ? $chars : array() as $char ) {
			if ( isset( $upper[ $char ] ) ) {
				$char = $upper[ $char ];
			} elseif ( 1 === strlen( $char ) ) {
				$char = strtolower( $char );
			}
			if ( isset( $weights[ $char ] ) ) {
				$weight = $weights[ $char ];
			} elseif ( 1 === strlen( $char ) && ctype_digit( $char ) ) {
				$weight = 100 + (int) $char;
			} elseif ( ' ' === $char ) {
				$weight = 10;
			} else {
				$weight = 50;
			}
			$key .= sprintf( '%03d', $weight );
		}
		return $key;
	}

	/** Kimlik biçimi: yalnız string ya da tam sayı; baştaki sıfır, işaret, boşluk, ondalık yok. */
	public static function normalize_id( $raw ) {
		if ( is_int( $raw ) ) {
			return $raw > 0 ? (string) $raw : '';
		}
		if ( ! is_string( $raw ) || 1 !== preg_match( '/\A[1-9][0-9]{0,' . ( self::MAX_ID_DIGITS - 1 ) . '}\z/', $raw ) ) {
			return '';
		}
		return $raw;
	}

	/**
	 * `ucret` sorgu değerinden ön seçim: biçim geçerli VE seçeneklerde TAM eşleşme.
	 *
	 * @param mixed              $raw
	 * @param array<int, string> $options build() çıktısı
	 * @return string seçenek kimliği veya ''
	 */
	public static function requested_fee( $raw, array $options ) {
		$id = self::normalize_id( $raw );
		return '' !== $id && array_key_exists( (int) $id, $options ) ? $id : '';
	}

	/**
	 * `meslek` (MYK kodu) sorgu değerinin çözüldüğü uygun ücret kimlikleri. Satırın kendi kodu veya bağlı yeterliliğin kodu
	 * TAM eşleşmelidir. Biçim geçersizse boş liste.
	 *
	 * @param mixed $raw
	 * @param array $rows get_application_fee_rows() çıktısı (qualification_code, linked_myk_code)
	 * @return int[]
	 */
	public static function fees_for_code( $raw, array $rows ) {
		if ( ! MaviBelge_Core_Forms_Qualification_Options::is_valid_code_format( $raw ) ) {
			return array();
		}
		$ids = array();
		foreach ( $rows as $row ) {
			$codes = array(
				isset( $row['qualification_code'] ) ? (string) $row['qualification_code'] : '',
				isset( $row['linked_myk_code'] ) ? (string) $row['linked_myk_code'] : '',
			);
			if ( in_array( $raw, $codes, true ) && isset( $row['id'] ) && (int) $row['id'] > 0 ) {
				$ids[] = (int) $row['id'];
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Ön seçim kararı: önce `ucret`, yoksa `meslek`.
	 *
	 * @return array{selected: string, ambiguous: int[]} ambiguous: koda bağlı birden fazla tarife (hiçbiri seçilmedi)
	 */
	public static function preselect( $rawFee, $rawCode, array $options, array $rows ) {
		$fee = self::requested_fee( $rawFee, $options );
		if ( '' !== $fee ) {
			return array( 'selected' => $fee, 'ambiguous' => array() );
		}
		$ids = array_values(
			array_filter(
				self::fees_for_code( $rawCode, $rows ),
				function ( $id ) use ( $options ) {
					return array_key_exists( $id, $options );
				}
			)
		);
		if ( 1 === count( $ids ) ) {
			return array( 'selected' => (string) $ids[0], 'ambiguous' => array() );
		}
		return array( 'selected' => '', 'ambiguous' => count( $ids ) > 1 ? $ids : array() );
	}

	/**
	 * E-posta için sunucudaki kayıttan üretilen satırlar (POST'taki hiçbir metin kullanılmaz).
	 *
	 * @param array $row get_application_fee_rows() satırı
	 * @return array<string, string> etiket => değer
	 */
	public static function mail_details( array $row ) {
		$level = isset( $row['level'] ) ? (string) $row['level'] : '';
		$code  = isset( $row['qualification_code'] ) ? self::single_line( $row['qualification_code'] ) : '';
		$qual  = isset( $row['linked_title'] ) ? self::single_line( $row['linked_title'] ) : '';
		$qcode = isset( $row['linked_myk_code'] ) ? self::single_line( $row['linked_myk_code'] ) : '';
		$qid   = isset( $row['qualification_id'] ) ? (int) $row['qualification_id'] : 0;
		return array(
			'Ücret kaydı'     => '#' . (int) $row['id'],
			'Meslek'          => self::single_line( $row['profession_name'] ),
			'Seviye'          => 1 === preg_match( '/^[1-8]$/', $level ) ? $level : 'Belirtilmemiş',
			'MYK kodu'        => '' !== $code ? $code : 'Belirtilmemiş',
			'Bağlı yeterlilik' => ( $qid > 0 && '' !== $qual ) ? $qual . ( '' !== $qcode ? ' (' . $qcode . ')' : '' ) . ' — kayıt #' . $qid : 'Bağlantısız',
		);
	}

	private static function single_line( $value ) {
		return trim( preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $value ) );
	}
}
