<?php
/**
 * Faz 6B3 Önkoşul ve Yazma Güvenliği — SAF, YAZMAYAN yazma yükü hazırlayıcı.
 *
 * Gelecekteki Faz 6B3 apply işlemi genel WordPress meta sanitizasyonuna
 * güvenerek YAZMAMALIDIR. Bu sınıf, bir kaydın import tarafından yönetilen
 * alanlarını ve sistem alanlarını (marker + hash) TEK SEFERDE doğrular ve
 * yalnız TAMAMEN geçerliyse gelecekteki güvenilir iç yazma katmanının
 * kullanacağı kanonik yükü (payload) döndürür.
 *
 * Bağlayıcı kurallar:
 * 1. Tüm yönetilen alanlar önce tek seferde doğrulanır
 *    (MaviBelge_Core_Import_Record_Validator::is_valid_managed_field_set() —
 *    mevcut-durum doğrulamasıyla AYNI kurallar, kopya yok).
 * 2. Bir alan bile geçersizse BÜTÜN kayıt reddedilir; `payload` null döner
 *    (kısmi alan listesi ASLA döndürülmez).
 * 3. Bu sınıf HİÇBİR WordPress fonksiyonu çağırmaz, hiçbir şey YAZMAZ.
 * 4. Marker tek kanonik MaviBelge_Core_Validator::classify_import_source_key()
 *    ile doğrulanır (dolu + doğru aile + tam biçim); hash tam 64 küçük hex
 *    olmalı VE yönetilen alanların deterministik hash'ine EŞİT olmalı.
 * 5. Para alanları kanonik integer kuruştur (MaviBelge_Core_Validator::canonical_kurus());
 *    TL dönüşümü YAPILMAZ. min/max, kanonik fiyat seçeneklerinden türetilir.
 * 6. Yükteki her meta anahtarı ilgili türün şemasında tanımlı olmalı; import
 *    tarafından yönetilmeyen hiçbir alan (ör. diğer system_managed alanlar)
 *    yüke sızamaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Write_Payload {

	/** Yükte izin verilen system-managed meta anahtarları (başka hiçbiri). */
	const ALLOWED_SYSTEM_META = array( '_mb_import_source_key', '_mb_last_applied_hash', '_mb_min_amount_kurus', '_mb_max_amount_kurus' );

	/**
	 * Faz 6B3 — türe göre yükün yazdığı post-meta anahtarlarının TAM listesi
	 * (marker + hash dahil). Yazma adapterı "yönetilmeyen alan" parmak izini
	 * bu listenin DIŞINDAKİ meta üzerinden hesaplar; tests/run.php yükün
	 * gerçekten tam olarak bu anahtarları ürettiğini doğrular.
	 */
	const MANAGED_POST_META = array(
		'qualification' => array( '_mb_myk_code', '_mb_level', '_mb_revision', '_mb_record_status', '_mb_import_source_key', '_mb_last_applied_hash' ),
		'fee'           => array(
			'_mb_profession_name', '_mb_level', '_mb_sector_slug', '_mb_qualification_id', '_mb_qualification_code', '_mb_pricing_type',
			'_mb_price_options', '_mb_min_amount_kurus', '_mb_max_amount_kurus', '_mb_vat_included', '_mb_certificate_print_fee_kurus',
			'_mb_source_name', '_mb_source_page', '_mb_source_attachment_id', '_mb_tariff_period', '_mb_record_status',
			'_mb_valid_from', '_mb_valid_until', '_mb_import_source_key', '_mb_last_applied_hash',
		),
		// Faz 7 içerik aktarımı. Haber: onay durumu + marker/hash (tür ilişkisi taksonomidir, meta değil).
		'news'          => array( '_mb_approval_status', '_mb_import_source_key', '_mb_last_applied_hash' ),
		'reference'     => array( '_mb_reference_status', '_mb_record_status', '_mb_sort_order', '_mb_website_url', '_mb_logo_attachment_id', '_mb_import_source_key', '_mb_last_applied_hash' ),
	);

	/** Tür => yazma yükünün post türü (sektör bir terimdir; post türü yok). */
	const POST_TYPES = array(
		'qualification' => 'mb_yeterlilik',
		'fee'           => 'mb_ucret',
		'news'          => 'mb_haber',
		'reference'     => 'mb_referans',
	);

	/**
	 * @param string $type          'sector' | 'qualification' | 'fee' | 'news' | 'reference'
	 * @param mixed  $managedFields Yönetilen alanlar (MaviBelge_Core_Import_Managed_Fields projeksiyon şekli).
	 * @param mixed  $sourceKey     Yazılacak `_mb_import_source_key`.
	 * @param mixed  $incomingHash  Yazılacak `_mb_last_applied_hash` (= yönetilen alanların hash'i).
	 * @return array{ok: bool, errors: string[], payload: array|null}
	 */
	public static function prepare( $type, $managedFields, $sourceKey, $incomingHash ) {
		$errors = array();

		if ( ! in_array( $type, MaviBelge_Core_Import_Managed_Fields::TYPES, true ) ) {
			return self::reject( array( 'bilinmeyen tür.' ) );
		}
		if ( ! MaviBelge_Core_Import_Record_Validator::is_valid_managed_field_set( $type, $managedFields ) ) {
			$errors[] = 'yönetilen alan kümesi eksik/fazla anahtar veya geçersiz değer taşıyor.';
		}
		if ( MaviBelge_Core_Validator::IMPORT_KEY_VALID !== MaviBelge_Core_Validator::classify_import_source_key( $sourceKey, $type ) ) {
			$errors[] = 'import marker (_mb_import_source_key) dolu, doğru aileden ve tam biçimde olmalı.';
		}
		if ( ! is_string( $incomingHash ) || 1 !== preg_match( '/^[0-9a-f]{64}\z/', $incomingHash ) ) {
			$errors[] = '_mb_last_applied_hash tam 64 küçük-harf hex olmalı.';
		}
		if ( ! empty( $errors ) ) {
			return self::reject( $errors );
		}

		try {
			$computed = MaviBelge_Core_Import_Hash::hash( $managedFields );
		} catch ( InvalidArgumentException $e ) {
			return self::reject( array( 'yönetilen alanlar deterministik olarak hash\'lenemedi.' ) );
		}
		if ( $computed !== $incomingHash ) {
			return self::reject( array( '_mb_last_applied_hash yönetilen alanların hash\'iyle eşleşmiyor.' ) );
		}

		switch ( $type ) {
			case 'sector':
				$payload = self::sector_payload( $managedFields );
				break;
			case 'qualification':
				$payload = self::qualification_payload( $managedFields );
				break;
			case 'news':
				$payload = self::news_payload( $managedFields );
				break;
			case 'reference':
				$payload = self::reference_payload( $managedFields );
				break;
			default:
				$payload = self::fee_payload( $managedFields );
				break;
		}
		if ( null === $payload ) {
			return self::reject( array( 'yük türetilemedi (para/fiyat seçeneği veya alan eşlemesi geçersiz).' ) );
		}
		$payload['type']         = $type;
		$payload['source_key']   = $sourceKey;
		$payload['managed_hash'] = $incomingHash;

		$metaKey = 'sector' === $type ? 'term_meta' : 'post_meta';
		$payload[ $metaKey ]['_mb_import_source_key'] = $sourceKey;
		$payload[ $metaKey ]['_mb_last_applied_hash'] = $incomingHash;

		if ( 'sector' !== $type && ! self::post_meta_keys_are_schema_keys( self::POST_TYPES[ $type ], $payload['post_meta'] ) ) {
			return self::reject( array( 'yük şemada tanımlı olmayan veya import tarafından yönetilmeyen bir meta anahtarı taşıyor.' ) );
		}

		return array( 'ok' => true, 'errors' => array(), 'payload' => $payload );
	}

	private static function sector_payload( array $f ) {
		return array(
			'term'      => array(
				'taxonomy'    => 'mb_sektor',
				'slug'        => $f['slug'],
				'name'        => $f['name'],
				'description' => $f['description'],
			),
			'term_meta' => array(
				'_mb_icon_key'            => $f['icon_key'],
				'_mb_image_attachment_id' => $f['image_attachment_id'],
			),
		);
	}

	private static function qualification_payload( array $f ) {
		return array(
			'post'      => array( 'post_type' => 'mb_yeterlilik', 'post_title' => $f['title'] ),
			'post_meta' => array(
				'_mb_myk_code'      => $f['myk_code'],
				// _mb_level şemada string anahtarlı 'select'tir: kanonik string temsil.
				'_mb_level'         => (string) $f['level'],
				'_mb_revision'      => $f['revision'],
				'_mb_record_status' => $f['record_status'],
			),
			'terms'     => array( 'mb_sektor' => array( $f['sector_term_id'] ) ),
		);
	}

	/**
	 * Faz 7 — haber yükü. Import ASLA onaylı/yayın haber yazmaz: onay durumu
	 * yalnız `in_review` olabilir (mevcut-durum doğrulayıcısı editörün onayını
	 * geçerli sayar, yazma yükü SAYMAZ). post_date taslak için `Y-m-d 12:00:00`
	 * (post_date_gmt hiç verilmez; taslakta sıfır kalır).
	 */
	private static function news_payload( array $f ) {
		if ( 'in_review' !== $f['approval_status'] ) {
			return null;
		}
		return array(
			'post'      => array(
				'post_type'    => 'mb_haber',
				'post_title'   => $f['title'],
				'post_name'    => $f['slug'],
				'post_content' => $f['content'],
				'post_excerpt' => $f['excerpt'],
				'post_date'    => $f['published_on'] . ' 12:00:00',
			),
			'post_meta' => array(
				'_mb_approval_status' => $f['approval_status'],
			),
			'terms'     => array( 'mb_haber_turu' => array( $f['news_type_term_id'] ) ),
		);
	}

	/** Faz 7 — referans yükü: yalnız TEMSİLİ/aktif, boş web sitesi, logo 0 (bkz. Managed_Fields::REFERENCE_FIELDS). */
	private static function reference_payload( array $f ) {
		if ( 'representative' !== $f['reference_status'] || 'active' !== $f['record_status'] || '' !== $f['website_url'] || 0 !== $f['logo_attachment_id'] ) {
			return null;
		}
		return array(
			'post'      => array(
				'post_type'  => 'mb_referans',
				'post_title' => $f['title'],
				'post_name'  => $f['slug'],
			),
			'post_meta' => array(
				'_mb_reference_status'   => $f['reference_status'],
				'_mb_record_status'      => $f['record_status'],
				'_mb_sort_order'         => $f['sort_order'],
				'_mb_website_url'        => $f['website_url'],
				'_mb_logo_attachment_id' => $f['logo_attachment_id'],
			),
		);
	}

	private static function fee_payload( array $f ) {
		$certificate = MaviBelge_Core_Validator::canonical_kurus( $f['certificate_print_fee_kurus'] );
		if ( null === $certificate ) {
			return null;
		}
		$eval = MaviBelge_Core_Validator::evaluate_price_options( $f['price_options'] );
		if ( empty( $eval['replace'] ) || ! empty( $eval['errors'] ) || $eval['options'] !== $f['price_options'] ) {
			return null; // Kanonik olmayan fiyat listesi — kısmi/yeniden sıralanmış liste yazılmaz.
		}
		$min = MaviBelge_Core_Validator::canonical_kurus( $eval['min_kurus'] );
		$max = MaviBelge_Core_Validator::canonical_kurus( $eval['max_kurus'] );
		if ( null === $min || null === $max || $min > $max ) {
			return null;
		}
		return array(
			'post'      => array( 'post_type' => 'mb_ucret', 'post_title' => $f['title'] ),
			'post_meta' => array(
				'_mb_profession_name'             => $f['profession_name'],
				'_mb_level'                       => (string) $f['level'],
				'_mb_sector_slug'                 => $f['sector_slug'],
				'_mb_qualification_id'            => $f['qualification_post_id'],
				'_mb_qualification_code'          => $f['qualification_code'],
				'_mb_pricing_type'                => $f['pricing_type'],
				'_mb_price_options'               => $f['price_options'],
				'_mb_min_amount_kurus'            => $min,
				'_mb_max_amount_kurus'            => $max,
				'_mb_vat_included'                => $f['vat_included'],
				'_mb_certificate_print_fee_kurus' => $certificate,
				'_mb_source_name'                 => $f['source_name'],
				'_mb_source_page'                 => $f['source_page'],
				'_mb_source_attachment_id'        => $f['source_attachment_id'],
				'_mb_tariff_period'               => $f['tariff_period'],
				'_mb_record_status'               => $f['record_status'],
				'_mb_valid_from'                  => $f['valid_from'],
				'_mb_valid_until'                 => $f['valid_until'],
			),
		);
	}

	/**
	 * Her yük meta anahtarı ilgili post türünün şemasında tanımlı olmalı;
	 * readonly/system_managed olanlar YALNIZ ALLOWED_SYSTEM_META ise kabul.
	 */
	private static function post_meta_keys_are_schema_keys( $postType, array $meta ) {
		$fields = MaviBelge_Core_Meta_Schema::get_fields_for( $postType );
		foreach ( array_keys( $meta ) as $key ) {
			if ( ! isset( $fields[ $key ] ) ) {
				return false;
			}
			$systemish = ! empty( $fields[ $key ]['readonly'] ) || ! empty( $fields[ $key ]['system_managed'] );
			if ( $systemish && ! in_array( $key, self::ALLOWED_SYSTEM_META, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function reject( array $errors ) {
		return array( 'ok' => false, 'errors' => $errors, 'payload' => null );
	}
}
