<?php
/**
 * Single source of truth for turning a raw value (from $_POST, from
 * $postarr['meta_input'], or from a future programmatic/import caller)
 * into a clean, validated value for one mavibelge-core meta field.
 *
 * Faz2 ikinci düzeltme brief §2/§3/§4: the admin save handler
 * (admin/class-meta-boxes.php) and the pre-publish readiness gate
 * (includes/class-publish-readiness.php) previously each had their own
 * copy of "parse this raw value" logic, and the two copies had already
 * started to drift (the admin price-options parser cast an unexpected
 * array straight to a string before the type was even checked, so
 * `label[]=x` in a crafted request slipped past the "reject
 * array/object" rule entirely). Both now call this class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Field_Repository {

	/**
	 * Faz 6B3 Önkoşul — admin formunun İNSAN girişini SAKLANAN kanonik
	 * temsile çeviren TEK yer. Yalnız 'admin_input' => 'try' taşıyan
	 * 'money_kurus' alanlarında TL metnini (örn. "1.500" / "1.500,00")
	 * MaviBelge_Core_Validator::try_lira_to_kurus() ile kuruşa çevirir;
	 * boş giriş "tanımlı değil" (0) demektir. Diğer bütün alanlarda ham
	 * değeri aynen döndürür. Sonuç ardından sanitize_and_validate()'e verilir.
	 * Programatik/import yazıcıları bu metodu ÇAĞIRMAZ — onlar zaten
	 * kanonik kuruş yazar.
	 *
	 * @return array{0: mixed, 1: string|null} ($storage_value, $error)
	 */
	public static function admin_input_to_storage( array $config, $raw ) {
		if ( 'money_kurus' !== $config['type'] || empty( $config['admin_input'] ) || 'try' !== $config['admin_input'] ) {
			return array( $raw, null );
		}
		if ( ! is_string( $raw ) ) {
			return array( null, 'geçerli bir TL tutarı değil.' );
		}
		$trimmed = trim( $raw );
		if ( '' === $trimmed ) {
			return array( 0, null );
		}
		$kurus = MaviBelge_Core_Validator::try_lira_to_kurus( $trimmed );
		if ( null === $kurus ) {
			return array( null, 'geçerli bir TL tutarı değil (örn. 1.500 veya 1.500,00).' );
		}
		return array( $kurus, null );
	}

	/**
	 * Field-aware sanitize + validate for ONE simple (non price_options,
	 * non system_managed/readonly) field. Returns array($clean, $error):
	 * $error is null on success, or a Turkish message on failure — the
	 * caller decides what "failure" means for its context (admin save:
	 * keep the old value + show a notice; publish readiness: field is
	 * not ready).
	 *
	 * Both array and newline-separated-string raw shapes are accepted
	 * for the *_list types: the admin textarea always submits a single
	 * string, but a programmatic caller (register_post_meta, a future
	 * import) naturally has the values as an array already. Blindly
	 * casting an array to string here (before checking is_array()) is
	 * exactly the bug this class was created to eliminate — every case
	 * below checks the shape before touching the value.
	 */
	public static function sanitize_and_validate( array $config, $raw ) {
		$type = $config['type'];

		switch ( $type ) {
			case 'textarea':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'metin olmalıdır.' );
				}
				$clean = sanitize_textarea_field( (string) $raw );
				if ( ! empty( $config['maxlength'] ) && self::str_length( $clean ) > $config['maxlength'] ) {
					$clean = self::str_truncate( $clean, $config['maxlength'] );
				}
				return array( $clean, null );

			case 'string_list':
				if ( is_object( $raw ) ) {
					return array( null, 'liste biçimi geçersiz.' );
				}
				$items = is_array( $raw ) ? $raw : preg_split( '/\r\n|\r|\n/', (string) $raw );
				if ( self::contains_non_scalar_item( $items ) ) {
					// Atomic reject: a single malformed nested array/object
					// element invalidates the whole field rather than being
					// silently dropped while the rest of the list "succeeds"
					// (Faz2 son düzeltme brief §3).
					return array( null, 'listede beklenmeyen (iç içe dizi/nesne) bir öğe var.' );
				}
				return array( MaviBelge_Core_Validator::normalize_string_list( $items ), null );

			case 'phone_list':
				if ( is_object( $raw ) ) {
					return array( null, 'liste biçimi geçersiz.' );
				}
				$items = is_array( $raw ) ? $raw : preg_split( '/\r\n|\r|\n/', (string) $raw );
				if ( self::contains_non_scalar_item( $items ) ) {
					return array( null, 'listede beklenmeyen (iç içe dizi/nesne) bir öğe var.' );
				}
				return array( MaviBelge_Core_Validator::normalize_phone_list( $items ), null );

			case 'id_list':
				if ( is_object( $raw ) ) {
					return array( null, 'liste biçimi geçersiz.' );
				}
				$items = is_array( $raw ) ? $raw : array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $raw ) );
				return array( MaviBelge_Core_Validator::normalize_id_list_of_type( $items, $config['ref_post_type'] ), null );

			case 'url':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'geçerli bir URL değil.' );
				}
				$raw_trimmed = trim( (string) $raw );
				if ( '' === $raw_trimmed ) {
					return array( '', null );
				}
				$clean = esc_url_raw( $raw_trimmed );
				if ( '' === $clean ) {
					return array( null, 'geçerli bir URL değil.' );
				}
				return array( $clean, null );

			case 'date':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'geçerli bir takvim tarihi (YYYY-AA-GG) değil.' );
				}
				$raw_trimmed = trim( (string) $raw );
				if ( ! MaviBelge_Core_Validator::is_valid_ymd_date( $raw_trimmed ) ) {
					return array( null, 'geçerli bir takvim tarihi (YYYY-AA-GG) değil.' );
				}
				return array( $raw_trimmed, null );

			case 'datetime':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'geçerli bir tarih-saat değil.' );
				}
				$raw_trimmed = trim( str_replace( 'T', ' ', (string) $raw ) );
				if ( '' !== $raw_trimmed && strlen( $raw_trimmed ) === 16 ) {
					$raw_trimmed .= ':00';
				}
				if ( ! MaviBelge_Core_Validator::is_valid_datetime( $raw_trimmed ) ) {
					return array( null, 'geçerli bir tarih-saat değil.' );
				}
				return array( $raw_trimmed, null );

			case 'integer':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'geçerli bir tam sayı değil.' );
				}
				$min = isset( $config['min'] ) ? $config['min'] : 0;
				if ( '' === $raw || null === $raw ) {
					return array( isset( $config['default'] ) ? $config['default'] : $min, null );
				}
				if ( ! MaviBelge_Core_Validator::is_valid_integer_range( $raw, $min ) ) {
					return array( null, 'geçerli bir tam sayı değil.' );
				}
				return array( (int) $raw, null );

			case 'id':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'geçerli bir ID değil.' );
				}
				if ( '' === $raw || null === $raw || '0' === (string) $raw ) {
					return array( 0, null );
				}
				if ( ! MaviBelge_Core_Validator::is_valid_integer_range( $raw, 0 ) ) {
					return array( null, 'geçerli bir ID değil.' );
				}
				$id = (int) $raw;
				if ( 0 !== $id && ! MaviBelge_Core_Validator::post_exists_of_type( $id, $config['ref_post_type'] ) ) {
					return array( null, 'belirtilen ID uygun içerik türünde bulunamadı.' );
				}
				return array( $id, null );

			case 'user_id':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'geçerli bir kullanıcı ID değil.' );
				}
				if ( '' === $raw || null === $raw ) {
					return array( 0, null );
				}
				if ( ! MaviBelge_Core_Validator::is_valid_integer_range( $raw, 0 ) || ! MaviBelge_Core_Validator::user_exists( $raw ) ) {
					return array( null, 'geçerli bir kullanıcı ID değil.' );
				}
				return array( (int) $raw, null );

			case 'select':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'listede olmayan bir değer seçildi.' );
				}
				$raw_str = (string) $raw;
				if ( '' === $raw_str ) {
					return array( '', null );
				}
				// Seçenek anahtarları kanonik string listesine çevrilir: PHP,
				// '1'..'8' gibi sayısal string anahtarları int'e dönüştürür;
				// katı is_valid_select() aksi hâlde "3" !== 3 nedeniyle her
				// sayısal seçeneği reddederdi (_mb_level hiç kaydedilemiyordu —
				// PHP 7.3 + WordPress 6.9.9 runtime bulgusu). "03"/"1.0" gibi
				// kanonik olmayan biçimler hâlâ reddedilir.
				$allowed_values = array_map( 'strval', array_keys( $config['options'] ) );
				if ( ! MaviBelge_Core_Validator::is_valid_select( $raw_str, $allowed_values ) ) {
					return array( null, 'listede olmayan bir değer seçildi.' );
				}
				return array( $raw_str, null );

			case 'checkbox':
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'geçerli bir onay kutusu değeri değil.' );
				}
				// Documented scalar contract: the admin form only ever
				// submits "1" (checked) or omits the field entirely
				// (unchecked — handled by the caller passing $raw=false
				// before this is reached, see admin/class-meta-boxes.php
				// save()'s $has_value handling). Any other scalar is
				// coerced with ordinary PHP truthiness: "", "0", 0,
				// null, false -> false; "1", 1, true, any other
				// non-empty/non-"0" string -> true. This mirrors how
				// register_post_meta()'s 'boolean' type already behaves
				// for programmatic writers, so no behavior changes for
				// well-formed input — only array/object is newly rejected.
				return array( (bool) $raw, null );

			case 'money_kurus':
				// Faz 6B3 Önkoşul — SAKLANAN temsil: kanonik integer kuruş.
				// Burada ASLA TL -> kuruş dönüşümü yapılmaz (eski 'money_try'
				// bunu her update_post_meta'da yeniden yaptığı için değer 100
				// ile ikinci kez çarpılıyordu). Bu dönüşüm idempotenttir:
				// kanonik girdi aynen döner. Boş (''/null) = "tanımlı değil" -> 0.
				// TL girişi yalnız admin_input_to_storage() ile, form katmanında
				// çevrilir.
				if ( null === $raw || '' === $raw ) {
					return array( 0, null );
				}
				$kurus = MaviBelge_Core_Validator::canonical_kurus( $raw );
				if ( null === $kurus ) {
					return array( null, 'geçerli bir kuruş tutarı değil (negatif olmayan tam sayı kuruş bekleniyor).' );
				}
				return array( $kurus, null );

			default: // text
				if ( is_array( $raw ) || is_object( $raw ) ) {
					return array( null, 'metin olmalıdır.' );
				}
				$clean = sanitize_text_field( (string) $raw );
				if ( ! empty( $config['format'] ) ) {
					$format_ok   = true;
					$format_hint = '';
					if ( 'myk_code' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_myk_code_format( $clean );
						$format_hint = 'örn. "10UY0002-3/03"';
					} elseif ( 'slug' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_slug_format( $clean );
						$format_hint = 'örn. "guzellik-sac-bakim" (küçük harf, rakam, tire)';
					} elseif ( 'import_source_key_qualification' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_import_source_key( $clean, 'qualification' );
						$format_hint = 'örn. "qualification:10UY0002-3/03", boş bırakılabilir';
					} elseif ( 'import_source_key_fee' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_import_source_key( $clean, 'fee' );
						$format_hint = 'örn. "fee:makine:3:makine-bakimci", boş bırakılabilir';
					} elseif ( 'import_source_key_news' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_import_source_key( $clean, 'news' );
						$format_hint = 'örn. "news:mobilya-sektoru-belge-zorunlulugu", boş bırakılabilir';
					} elseif ( 'import_source_key_reference' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_import_source_key( $clean, 'reference' );
						$format_hint = 'örn. "reference:atlas-endustri", boş bırakılabilir';
					} elseif ( 'import_source_key_faq' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_import_source_key( $clean, 'faq' );
						$format_hint = 'örn. "faq:myk-mesleki-yeterlilik-belgesi-nedir", boş bırakılabilir';
					} elseif ( 'import_source_key_page' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_import_source_key( $clean, 'page' );
						$format_hint = 'örn. "page:hakkimizda", boş bırakılabilir';
					} elseif ( 'sha256_hash' === $config['format'] ) {
						$format_ok   = MaviBelge_Core_Validator::is_valid_sha256_hash( $clean );
						$format_hint = 'tam 64 küçük-hex karakter, boş bırakılabilir';
					}
					if ( ! $format_ok ) {
						return array( null, sprintf( 'beklenen biçimde değil (%s).', $format_hint ) );
					}
				}
				return array( $clean, null );
		}
	}

	/**
	 * Resolves the "effective" value of one meta field for a
	 * wp_insert_post_data-time readiness check, in a fixed, documented
	 * priority order (Faz2 ikinci düzeltme brief §4):
	 *
	 * 1. This request's own admin field, if present in $_POST — run
	 *    through sanitize_and_validate() just like the real save does.
	 * 2. $postarr['meta_input'][$meta_key], if present — a programmatic
	 *    caller's value (e.g. wp_insert_post(array('meta_input'=>...))),
	 *    run through the SAME sanitize_and_validate() — never trusted
	 *    unvalidated.
	 * 3. The value already stored in postmeta for $post_id — trusted
	 *    without re-validation, since it can only have gotten there by
	 *    passing this same validation at the time it was written
	 *    (either via the admin save handler or via write_meta() below).
	 *
	 * @return array{value: mixed, valid: bool, source: string}
	 */
	public static function resolve_effective_value( $meta_key, array $config, $post_id, array $postarr ) {
		if ( array_key_exists( $meta_key, $_POST ) ) {
			$raw = wp_unslash( $_POST[ $meta_key ] );
			list( $clean, $error ) = self::sanitize_and_validate( $config, $raw );
			return array(
				'value'  => null === $error ? $clean : null,
				'valid'  => null === $error,
				'source' => 'request',
			);
		}

		if ( isset( $postarr['meta_input'] ) && is_array( $postarr['meta_input'] ) && array_key_exists( $meta_key, $postarr['meta_input'] ) ) {
			$raw = $postarr['meta_input'][ $meta_key ];
			list( $clean, $error ) = self::sanitize_and_validate( $config, $raw );
			return array(
				'value'  => null === $error ? $clean : null,
				'valid'  => null === $error,
				'source' => 'meta_input',
			);
		}

		if ( $post_id ) {
			return array(
				'value'  => get_post_meta( $post_id, $meta_key, true ),
				'valid'  => true, // Already validated when originally written.
				'source' => 'db',
			);
		}

		return array( 'value' => null, 'valid' => false, 'source' => 'none' );
	}

	/**
	 * Reject-capable programmatic write API. Unlike
	 * register_post_meta()'s sanitize_callback (which can only clean a
	 * value — WordPress gives it no mechanism to refuse the write),
	 * this returns success/failure so a caller (intended: the Faz 6
	 * validated import flow) gets real feedback instead of having bad
	 * data silently coerced to an empty default.
	 *
	 * NOTE on capabilities: this method does NOT check
	 * current_user_can() itself — it is a data validation/write
	 * contract, not an authorization layer. A caller that exposes this
	 * to anything less trusted than trusted internal/import code (e.g.
	 * a future admin-facing tool) is responsible for its own capability
	 * check before calling this.
	 *
	 * Faz 6B3 Önkoşul — para alanları ('money_kurus') için $raw_value
	 * KANONİK KURUŞTUR (integer), TL metni DEĞİL; bu metot TL dönüşümü
	 * yapmaz (TL girişi yalnız admin formunda admin_input_to_storage() ile
	 * çevrilir). Böylece değer hiçbir yolda 100 ile ikinci kez çarpılmaz.
	 *
	 * @return array{success: bool, value?: mixed, error?: string}
	 */
	public static function write_meta( $post_id, $post_type, $meta_key, $raw_value ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return array( 'success' => false, 'error' => 'Geçerli bir post bulunamadı.' );
		}
		if ( get_post_type( $post_id ) !== $post_type ) {
			return array( 'success' => false, 'error' => 'Post türü belirtilen post_type ile uyuşmuyor.' );
		}

		$fields = MaviBelge_Core_Meta_Schema::get_fields_for( $post_type );
		if ( ! isset( $fields[ $meta_key ] ) ) {
			return array( 'success' => false, 'error' => 'Bilinmeyen alan: ' . $meta_key );
		}
		$config = $fields[ $meta_key ];

		if ( ! empty( $config['readonly'] ) || ! empty( $config['system_managed'] ) ) {
			return array( 'success' => false, 'error' => $config['label'] . ' sistem tarafından yönetilir; doğrudan yazılamaz.' );
		}

		if ( 'price_options' === $config['type'] ) {
			if ( ! is_array( $raw_value ) ) {
				// Do NOT silently coerce a non-array to an empty list —
				// that would wipe out an existing valid price list. Reject
				// outright; nothing is written (Faz2 son düzeltme brief §4).
				return array( 'success' => false, 'error' => 'Fiyat seçenekleri bir dizi olmalıdır.' );
			}
			$result = MaviBelge_Core_Validator::normalize_price_options( $raw_value );
			if ( ! empty( $result['errors'] ) ) {
				// Atomic: any invalid row rejects the whole write; the
				// three related meta fields (options/min/max) are left
				// completely untouched, not partially updated.
				return array( 'success' => false, 'error' => implode( ' ', $result['errors'] ) );
			}
			update_post_meta( $post_id, $meta_key, $result['options'] );
			update_post_meta( $post_id, '_mb_min_amount_kurus', $result['min_kurus'] );
			update_post_meta( $post_id, '_mb_max_amount_kurus', $result['max_kurus'] );
			return array( 'success' => true, 'value' => $result['options'] );
		}

		list( $clean, $error ) = self::sanitize_and_validate( $config, $raw_value );
		if ( null !== $error ) {
			return array( 'success' => false, 'error' => $config['label'] . ': ' . $error );
		}
		update_post_meta( $post_id, $meta_key, $clean );
		return array( 'success' => true, 'value' => $clean );
	}

	/** True if any element of $items is itself an array or object. */
	private static function contains_non_scalar_item( $items ) {
		if ( ! is_array( $items ) ) {
			return false;
		}
		foreach ( $items as $item ) {
			if ( is_array( $item ) || is_object( $item ) ) {
				return true;
			}
		}
		return false;
	}

	private static function str_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	private static function str_truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
