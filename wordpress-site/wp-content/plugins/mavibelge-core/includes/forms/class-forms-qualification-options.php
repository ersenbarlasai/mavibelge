<?php
/**
 * Faz 12f — form yeterlilik seçenekleri ve `meslek` ön seçimi (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Satırlar çağırandan gelir (MaviBelge_Core_Forms_Service::dynamic_options(): yayında mb_yeterlilik + Visibility_Guard).
 * Kurallar:
 *  - Seçenek anahtarı yalnız MYK kodu; etiket "{Ad} — {Kod} (Seviye {N})". Seviye 1-8 değilse seviye UYDURULMAZ (ad + kod).
 *  - Herkese açık olmayan veya kodsuz kayıt seçenek olmaz.
 *  - Aynı MYK koduna birden fazla herkese açık kayıt bir veri bütünlüğü sorunudur: o kod TAMAMEN dışlanır (fail-closed);
 *    rastgele ilk/son kayıt seçilmez.
 *  - `meslek` ön seçimi: yalnız string, katı MYK biçimi (satır sonu/boşluk/kodlanmış karakter yok) ve seçeneklerde TAM eşleşme.
 *    Aksi hâlde boş (hiçbir yeterlilik seçilmez). Değer asla HTML'e/URL'ye ham olarak taşınmaz — yalnız seçenek anahtarı döner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Qualification_Options {

	/** MYK kodu için makul üst sınır (en uzun geçerli biçim 13 karakterdir). */
	const MAX_CODE_LENGTH = 32;

	/**
	 * @param array<int, array{code: string, title: string, level: string, public: bool}> $rows Başlık sırasıyla.
	 * @return array<string, string> kod => etiket
	 */
	public static function build( array $rows ) {
		$dups    = array_flip( self::duplicates( $rows ) );
		$options = array();
		foreach ( $rows as $row ) {
			$code = isset( $row['code'] ) ? (string) $row['code'] : '';
			if ( '' === $code || empty( $row['public'] ) || isset( $dups[ $code ] ) ) {
				continue;
			}
			$label = (string) $row['title'] . ' — ' . $code;
			$level = isset( $row['level'] ) ? (string) $row['level'] : '';
			if ( 1 === preg_match( '/^[1-8]$/', $level ) ) {
				$label .= ' (Seviye ' . $level . ')';
			}
			$options[ $code ] = $label;
		}
		return $options;
	}

	/**
	 * Birden fazla HERKESE AÇIK kayıtta geçen MYK kodları.
	 *
	 * @return string[]
	 */
	public static function duplicates( array $rows ) {
		$count = array();
		foreach ( $rows as $row ) {
			$code = isset( $row['code'] ) ? (string) $row['code'] : '';
			if ( '' === $code || empty( $row['public'] ) ) {
				continue;
			}
			$count[ $code ] = isset( $count[ $code ] ) ? $count[ $code ] + 1 : 1;
		}
		return array_keys(
			array_filter(
				$count,
				function ( $n ) {
					return $n > 1;
				}
			)
		);
	}

	/**
	 * `meslek` sorgu değerinden güvenli ön seçim.
	 *
	 * @param mixed                $raw     $_GET['meslek'] (WordPress tarafından URL çözümlenmiş; ek decode YAPILMAZ).
	 * @param array<string,string> $options build() çıktısı.
	 * @return string Seçilecek seçenek anahtarı veya ''.
	 */
	public static function requested( $raw, array $options ) {
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > self::MAX_CODE_LENGTH ) {
			return '';
		}
		if ( 1 !== preg_match( '/\A[0-9]{2}UY[0-9]{4}-[0-9]{1,2}(\/[0-9]{2})?\z/', $raw ) ) {
			return '';
		}
		return array_key_exists( $raw, $options ) ? $raw : '';
	}
}
