<?php
/**
 * Faz 6B1 Düzeltme ve Kabul — TEK, fail-closed kayıt doğrulama katmanı.
 *
 * Bağımsız incelemenin bulduğu kök neden: `MaviBelge_Core_Import_Dry_Run_Planner`'ın
 * üç `plan_*()` yolu yalnız birkaç anahtarın `isset()` olup olmadığını
 * kontrol ediyordu; eksik/yanlış-tipli her diğer alan `MaviBelge_Core_Import_Managed_Fields`'in
 * kendi `isset(...) ? ... : ''`/`(int)`/`continue` "kurtarma" mantığıyla
 * sessizce `''`/`0`/atlanmış satır olarak projeksiyona giriyor ve `create`/
 * `update` planlanabiliyordu. Bu dosya bunu kapatır: projeksiyon
 * fonksiyonlarının HİÇBİRİ artık kendi başına çağrılmaz — üç `plan_*()` yolu
 * da projeksiyondan ÖNCE bu sınıftan geçmek ZORUNDADIR; doğrulama
 * başarısızsa hiçbir projeksiyon/hash üretilmez, kayıt `invalid` olur.
 *
 * Bu dosya HİÇBİR WordPress fonksiyonu çağırmaz (yalnız
 * `MaviBelge_Core_Validator`'ın "format grubu" — kendisi de WordPress'siz
 * çalışan — yardımcılarını kullanır) — `tests/run.php` ile WordPress
 * bootstrap'ı olmadan tam test edilebilir.
 *
 * Alan sözleşmesi kaynağı: Faz 6A `schema_version=2.0.0` manifest şeması
 * (`wordpress-site/data/schema/{sector,qualification,fee}.schema.json`).
 *
 * Faz 6B1 Son Kapanış Düzeltmesi: bağımsız incelemenin bulduğu üzere,
 * önceki turda bu doğrulayıcı yalnız PLANLAYICININ okuduğu/hash'lediği
 * alt kümeyi zorunlu kılıyor, `schema_version`/`source_index`/`source`/
 * yeterliliğin `matches_legacy_revision_required_format`+`planned_record_status`/
 * ücretin `min_amount_kurus`+`max_amount_kurus` gibi şemanın GERİ KALAN
 * `required` alanlarını hiç doğrulamıyordu — bu, görev promptunun "Faz 6A
 * schema_version=2.0.0 manifest sözleşmesini uygula" ve "tam zorunlu alan
 * kümesi" şartına aykırıydı. Bu turda üç `validate_*()` metodu artık
 * ilgili `data/schema/*.schema.json` dosyasının TAM `required` kümesini
 * uyguluyor — `additionalProperties: false` ile AYNI kapalı politika
 * (§ EK-ALAN POLİTİKASI aşağıda).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Record_Validator {

	/**
	 * EK-ALAN POLİTİKASI (Faz 6B1 Son Kapanış Düzeltmesi'nde şemayla
	 * eşitlendi): şema dosyaları `additionalProperties: false` taşıdığı
	 * için bu doğrulayıcı da artık AYNI kapalı politikayı uyguluyor —
	 * beklenmeyen bir üst-seviye alan `invalid` üretir. `tests/run.php`
	 * fixture'ları bu turda TAM şema-uyumlu hale getirildi (bkz. görev
	 * promptu §3 "Test kolaylığı için üretim doğrulamasını gevşetme").
	 */
	const SECTOR_SCHEMA_KEYS        = array( 'schema_version', 'source_key', 'source_index', 'slug', 'name', 'description', 'icon', 'image', 'source' );
	const QUALIFICATION_SCHEMA_KEYS = array( 'schema_version', 'source_key', 'source_index', 'code', 'name', 'level', 'sector_slug', 'revision', 'has_revision', 'matches_legacy_revision_required_format', 'planned_record_status', 'source' );
	const FEE_SCHEMA_KEYS           = array(
		'schema_version', 'source_key', 'source_index', 'profession_name', 'level', 'sector_slug',
		'qualification_code', 'qualification_source_key', 'pricing_type', 'price_options',
		'min_amount_kurus', 'max_amount_kurus', 'vat_included', 'certificate_print_fee_kurus',
		'source_name', 'source_page', 'source_attachment_id',
		'planned_tariff_period', 'planned_record_status', 'planned_valid_from', 'planned_valid_until',
		'source',
	);

	/**
	 * Faz 7 içerik aktarımı — news/reference manifest kaydı şemaları
	 * (wordpress-site/data/schema/{news,reference}.schema.json ile AYNI kapalı politika).
	 */
	const NEWS_SCHEMA_KEYS      = array( 'schema_version', 'source_key', 'source_index', 'slug', 'title', 'published_on', 'news_type', 'summary', 'body', 'source' );
	const REFERENCE_SCHEMA_KEYS = array( 'schema_version', 'source_key', 'source_index', 'name', 'slug', 'logo_file', 'logo_sha256', 'logo_bytes', 'logo_width', 'logo_height', 'alt', 'name_status', 'source' );

	/** Faz 12b — SSS (mb_sss) manifest kaydı (data/schema/faq.schema.json ile AYNI kapalı politika). */
	const FAQ_SCHEMA_KEYS = array( 'schema_version', 'source_key', 'source_index', 'slug', 'question', 'answer', 'source' );

	/**
	 * Faz 12 — `page` (WordPress çekirdek sayfa türü) manifest kaydı (data/schema/page.schema.json ile AYNI kapalı politika).
	 * Yeni post type YOKTUR.
	 */
	const PAGE_SCHEMA_KEYS = array( 'schema_version', 'source_key', 'source_index', 'slug', 'title', 'content', 'excerpt', 'parent_source_key', 'menu_order', 'page_template', 'post_status', 'layout', 'content_sha256', 'pending_decisions', 'publish_hold', 'publish_requires', 'source' );

	/** Kurum/kullanıcı onaylı sayfa kaynak parçalarının (hukuk/banka metni, harici hizmet bilgilendirmesi) depo yolu (künye: approved-sources.manifest.json). */
	const PAGE_APPROVED_SOURCE_DIR = 'wordpress-site/data/sources/approved';

	/** Sayfa düzenleri (tools/import/lib/page-inventory.js ile AYNI kapalı küme). */
	const PAGE_LAYOUTS = array( 'hub', 'default', 'form-disabled', 'cpt-page' );

	/**
	 * Bekleyen kurum kararı kodları => bloklayıcı mı (yayın işlemini durdurur). Node tarafındaki PENDING_CODES ile AYNI kapalı sözlük
	 * (tools/test-faz6b2-static-contract.js iki tarafın eşitliğini doğrular).
	 */
	const PAGE_PENDING_DECISIONS = array(
		'form_gate_institution_decisions' => false,
		'location_data_pending'           => false,
		'fee_tariff_documents_pending'    => false,
		'accreditation_documents_pending' => false,
		'legislation_links_pending'       => false,
		'static_counter_block_omitted'    => false,
	);

	/** Sayfa yayınının bağımlı olduğu içerik türleri (kapalı küme; tools/import/lib/page-inventory.js PUBLISH_REQUIRES ile AYNI). */
	const PAGE_PUBLISH_REQUIRES = array( 'faq', 'reference' );

	/** İçerikte izin verilen (kapalı) etiketler. */
	const PAGE_CONTENT_TAGS = array( 'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'strong', 'em', 'br' );

	/** Haber türü (mb_haber_turu kontrollü sözlüğüyle aynı iki slug). */
	const NEWS_TYPES = array( 'haber', 'duyuru' );

	/** @return array{valid: bool, errors: string[]} */
	public static function validate_sector( $record ) {
		$errors = array();

		if ( ! is_array( $record ) ) {
			return self::fail( array( 'Sektör kaydı bir dizi (object) değil.' ) );
		}

		$extra = self::extra_keys( $record, self::SECTOR_SCHEMA_KEYS );
		if ( ! empty( $extra ) ) {
			$errors[] = 'Beklenmeyen alan(lar): ' . implode( ', ', $extra ) . '.';
		}

		self::require_string( $record, 'schema_version', $errors, 1 );
		self::require_string( $record, 'source_key', $errors, 1 );
		self::require_int( $record, 'source_index', $errors );
		self::require_string( $record, 'slug', $errors, 1 );
		self::require_string( $record, 'name', $errors, 1 );
		self::require_string( $record, 'description', $errors, 0 );
		self::require_string( $record, 'icon', $errors, 0 );
		self::require_string( $record, 'image', $errors, 0 );
		self::require_source_object( $record, $errors );

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( '2.0.0' !== $record['schema_version'] ) {
			$errors[] = 'schema_version "2.0.0" olmalı.';
		}
		if ( $record['source_index'] < 0 ) {
			$errors[] = 'source_index negatif olamaz.';
		}
		if ( ! preg_match( '/^sector:[a-z0-9]+(-[a-z0-9]+)*$/', $record['source_key'] ) ) {
			$errors[] = 'source_key biçimi geçersiz ("sector:<slug>" olmalı).';
		}
		if ( ! MaviBelge_Core_Validator::is_valid_slug_format( $record['slug'] ) || '' === $record['slug'] ) {
			$errors[] = 'slug biçimi geçersiz.';
		}
		if ( empty( $errors ) && 'sector:' . $record['slug'] !== $record['source_key'] ) {
			$errors[] = 'source_key, slug ile tutarlı değil ("sector:" + slug olmalı).';
		}

		return empty( $errors ) ? self::ok() : self::fail( $errors );
	}

	/** @return array{valid: bool, errors: string[]} */
	public static function validate_qualification( $record ) {
		$errors = array();

		if ( ! is_array( $record ) ) {
			return self::fail( array( 'Yeterlilik kaydı bir dizi (object) değil.' ) );
		}

		$extra = self::extra_keys( $record, self::QUALIFICATION_SCHEMA_KEYS );
		if ( ! empty( $extra ) ) {
			$errors[] = 'Beklenmeyen alan(lar): ' . implode( ', ', $extra ) . '.';
		}

		self::require_string( $record, 'schema_version', $errors, 1 );
		self::require_string( $record, 'source_key', $errors, 1 );
		self::require_int( $record, 'source_index', $errors );
		self::require_string( $record, 'code', $errors, 1 );
		self::require_string( $record, 'name', $errors, 1 );
		self::require_int( $record, 'level', $errors );
		self::require_string( $record, 'sector_slug', $errors, 1 );
		self::require_string( $record, 'revision', $errors, 0 );
		self::require_bool( $record, 'has_revision', $errors );
		self::require_bool( $record, 'matches_legacy_revision_required_format', $errors );
		self::require_string( $record, 'planned_record_status', $errors, 1 );
		self::require_source_object( $record, $errors );

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( '2.0.0' !== $record['schema_version'] ) {
			$errors[] = 'schema_version "2.0.0" olmalı.';
		}
		if ( $record['source_index'] < 0 ) {
			$errors[] = 'source_index negatif olamaz.';
		}
		if ( ! MaviBelge_Core_Validator::is_valid_myk_code_format( $record['code'] ) || '' === $record['code'] ) {
			$errors[] = 'code (MYK kodu) biçimi geçersiz.';
		}
		if ( $record['level'] < 1 || $record['level'] > 8 ) {
			$errors[] = 'level 1-8 aralığında olmalı.';
		}
		if ( ! MaviBelge_Core_Validator::is_valid_slug_format( $record['sector_slug'] ) || '' === $record['sector_slug'] ) {
			$errors[] = 'sector_slug biçimi geçersiz.';
		}
		if ( $record['has_revision'] ) {
			if ( ! preg_match( '/^[0-9]{2}$/', $record['revision'] ) ) {
				$errors[] = 'has_revision=true iken revision tam 2 haneli rakam olmalı.';
			}
		} elseif ( '' !== $record['revision'] ) {
			$errors[] = 'has_revision=false iken revision KESİNLİKLE boş olmalı.';
		}
		if ( ! in_array( $record['planned_record_status'], array( 'active', 'passive' ), true ) ) {
			$errors[] = 'planned_record_status enum dışı ("active"/"passive" olmalı).';
		} elseif ( 'active' !== $record['planned_record_status'] ) {
			// Faz 6A build-manifest.js bu alanı her zaman "active" üretir
			// (bkz. tools/import/build-manifest.js satır 151) — bu fazda
			// KESİN sabittir; projeksiyon da bunu okur, hard-code etmez.
			$errors[] = 'planned_record_status bu fazda KESİNLİKLE "active" olmalı.';
		}

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		// Çapraz-alan: koda gömülü seviye/revizyon, ayrı alanlarla TAM eşleşmeli
		// (admin kaydı ve yayın kapısıyla PAYLAŞILAN tek kural).
		if ( ! MaviBelge_Core_Validator::myk_code_matches_level_revision( $record['code'], $record['level'], $record['revision'] ) ) {
			$errors[] = 'code içine gömülü seviye/revizyon, level/revision alanlarıyla uyuşmuyor.';
		}
		if ( 'qualification:' . $record['code'] !== $record['source_key'] ) {
			$errors[] = 'source_key, code ile tutarlı değil ("qualification:" + code olmalı).';
		}
		// matches_legacy_revision_required_format, has_revision'ın tarihsel
		// (artık kapatılmış) katı-regex karşılığıdır — TEK ayrıştırma
		// noktasından (parse_myk_code) her ikisi de AYNI eşleşmeyle
		// üretildiği için birebir eşit olmak ZORUNDADIR (bkz.
		// tools/import/lib/myk-code.js matchesLegacyRevisionRequiredFormat()).
		if ( $record['matches_legacy_revision_required_format'] !== $record['has_revision'] ) {
			$errors[] = 'matches_legacy_revision_required_format, has_revision ile tutarlı değil.';
		}

		return empty( $errors ) ? self::ok() : self::fail( $errors );
	}

	/**
	 * Faz 7 — haber kaydı. Başlık/özet/gövde DÜZ METİN olmalı: boş olamaz,
	 * baş/son boşluk taşıyamaz (kanonik biçim; WordPress yazma-okuma turunda
	 * sessizce değişmesin), işaretleme karakteri (`<`, `>`) ve kontrol
	 * karakteri (satır sonu/sekme hariç) taşıyamaz, geçerli UTF-8 olmalı.
	 *
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate_news( $record ) {
		$errors = array();

		if ( ! is_array( $record ) ) {
			return self::fail( array( 'Haber kaydı bir dizi (object) değil.' ) );
		}

		$extra = self::extra_keys( $record, self::NEWS_SCHEMA_KEYS );
		if ( ! empty( $extra ) ) {
			$errors[] = 'Beklenmeyen alan(lar): ' . implode( ', ', $extra ) . '.';
		}

		self::require_string( $record, 'schema_version', $errors, 1 );
		self::require_string( $record, 'source_key', $errors, 1 );
		self::require_int( $record, 'source_index', $errors );
		self::require_string( $record, 'slug', $errors, 1 );
		self::require_string( $record, 'title', $errors, 1 );
		self::require_string( $record, 'published_on', $errors, 1 );
		self::require_string( $record, 'news_type', $errors, 1 );
		self::require_string( $record, 'summary', $errors, 1 );
		self::require_string( $record, 'body', $errors, 1 );
		self::require_source_object( $record, $errors );

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( '2.0.0' !== $record['schema_version'] ) {
			$errors[] = 'schema_version "2.0.0" olmalı.';
		}
		if ( $record['source_index'] < 0 ) {
			$errors[] = 'source_index negatif olamaz.';
		}
		if ( 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $record['slug'] ) ) {
			$errors[] = 'slug biçimi geçersiz.';
		}
		if ( 1 !== preg_match( '/^news:[a-z0-9]+(-[a-z0-9]+)*\z/', $record['source_key'] ) ) {
			$errors[] = 'source_key biçimi geçersiz ("news:<slug>" olmalı).';
		}
		if ( empty( $errors ) && 'news:' . $record['slug'] !== $record['source_key'] ) {
			$errors[] = 'source_key, slug ile tutarlı değil ("news:" + slug olmalı).';
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}\z/', $record['published_on'] ) || ! MaviBelge_Core_Validator::is_valid_ymd_date( $record['published_on'] ) ) {
			$errors[] = 'published_on takvimde var olan bir YYYY-AA-GG tarihi olmalı.';
		}
		if ( ! in_array( $record['news_type'], self::NEWS_TYPES, true ) ) {
			$errors[] = 'news_type "haber" veya "duyuru" olmalı.';
		}
		foreach ( array( 'title', 'summary', 'body' ) as $field ) {
			if ( ! self::is_canonical_plain_text( $record[ $field ] ) ) {
				$errors[] = "{$field} kanonik düz metin olmalı (baş/son boşluk, '<', '>', kontrol karakteri veya geçersiz UTF-8 içeremez).";
			}
		}

		return empty( $errors ) ? self::ok() : self::fail( $errors );
	}

	/**
	 * Faz 12b — referans kaydı (gerçek logo). `name` NÖTR sıra etiketidir ("Referans NN"): firma adı görselden tahmin edilmez
	 * (name_status KESİNLİKLE "unverified"). slug her zaman slugify(name) (tools/import/lib/slug.js ile AYNI kural).
	 * logo_file depo yoludur; içe aktarımda dosya (SHA-256 + PNG + bayt boyutu doğrulanarak) gerçek WordPress attachment'ına çevrilir.
	 *
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate_reference( $record ) {
		$errors = array();

		if ( ! is_array( $record ) ) {
			return self::fail( array( 'Referans kaydı bir dizi (object) değil.' ) );
		}

		$extra = self::extra_keys( $record, self::REFERENCE_SCHEMA_KEYS );
		if ( ! empty( $extra ) ) {
			$errors[] = 'Beklenmeyen alan(lar): ' . implode( ', ', $extra ) . '.';
		}

		self::require_string( $record, 'schema_version', $errors, 1 );
		self::require_string( $record, 'source_key', $errors, 1 );
		self::require_int( $record, 'source_index', $errors );
		self::require_string( $record, 'name', $errors, 1 );
		self::require_string( $record, 'slug', $errors, 1 );
		self::require_string( $record, 'logo_file', $errors, 1 );
		self::require_string( $record, 'logo_sha256', $errors, 1 );
		self::require_int( $record, 'logo_bytes', $errors );
		self::require_int( $record, 'logo_width', $errors );
		self::require_int( $record, 'logo_height', $errors );
		self::require_string( $record, 'alt', $errors, 1 );
		self::require_string( $record, 'name_status', $errors, 1 );
		self::require_source_object( $record, $errors );

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( '2.0.0' !== $record['schema_version'] ) {
			$errors[] = 'schema_version "2.0.0" olmalı.';
		}
		if ( $record['source_index'] < 0 ) {
			$errors[] = 'source_index negatif olamaz.';
		}
		if ( 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $record['slug'] ) ) {
			$errors[] = 'slug biçimi geçersiz.';
		}
		if ( 1 !== preg_match( '/^reference:[a-z0-9]+(-[a-z0-9]+)*\z/', $record['source_key'] ) ) {
			$errors[] = 'source_key biçimi geçersiz ("reference:<slug>" olmalı).';
		}
		if ( empty( $errors ) && 'reference:' . $record['slug'] !== $record['source_key'] ) {
			$errors[] = 'source_key, slug ile tutarlı değil ("reference:" + slug olmalı).';
		}
		if ( ! self::is_canonical_plain_text( $record['name'] ) || ! self::is_canonical_plain_text( $record['alt'] ) ) {
			$errors[] = "name/alt kanonik düz metin olmalı (baş/son boşluk, '<', '>', kontrol karakteri veya geçersiz UTF-8 içeremez).";
		} else {
			$expectedSlug = self::slugify_tr( $record['name'] );
			if ( null === $expectedSlug || $expectedSlug !== $record['slug'] ) {
				$errors[] = 'slug, slugify(name) ile tutarlı değil.';
			}
			if ( 1 !== preg_match( '/^Referans [0-9]{2}\z/', $record['name'] ) ) {
				$errors[] = 'name nötr sıra etiketi ("Referans NN") olmalı (firma adı tahmin edilmez).';
			}
		}
		if ( 'unverified' !== $record['name_status'] ) {
			$errors[] = 'name_status KESİNLİKLE "unverified" olmalı.';
		}
		if ( 1 !== preg_match( '#^wordpress-site/data/sources/reference-logos/ref-[0-9]{2}\.png\z#', $record['logo_file'] ) ) {
			$errors[] = 'logo_file "wordpress-site/data/sources/reference-logos/ref-NN.png" olmalı.';
		}
		if ( 1 !== preg_match( '/^[0-9a-f]{64}\z/', $record['logo_sha256'] ) ) {
			$errors[] = 'logo_sha256 tam 64 küçük-harf hex olmalı.';
		}
		if ( $record['logo_bytes'] < 1 || $record['logo_width'] < 1 || $record['logo_height'] < 1 ) {
			$errors[] = 'logo_bytes/logo_width/logo_height pozitif olmalı.';
		}

		return empty( $errors ) ? self::ok() : self::fail( $errors );
	}

	/**
	 * Faz 12b — SSS kaydı (mb_sss). slug = slugify(question); soru başlığa, cevap gövdeye DÜZ METİN olarak yazılır.
	 *
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate_faq( $record ) {
		$errors = array();

		if ( ! is_array( $record ) ) {
			return self::fail( array( 'SSS kaydı bir dizi (object) değil.' ) );
		}

		$extra = self::extra_keys( $record, self::FAQ_SCHEMA_KEYS );
		if ( ! empty( $extra ) ) {
			$errors[] = 'Beklenmeyen alan(lar): ' . implode( ', ', $extra ) . '.';
		}

		self::require_string( $record, 'schema_version', $errors, 1 );
		self::require_string( $record, 'source_key', $errors, 1 );
		self::require_int( $record, 'source_index', $errors );
		self::require_string( $record, 'slug', $errors, 1 );
		self::require_string( $record, 'question', $errors, 1 );
		self::require_string( $record, 'answer', $errors, 1 );
		self::require_source_object( $record, $errors );

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( '2.0.0' !== $record['schema_version'] ) {
			$errors[] = 'schema_version "2.0.0" olmalı.';
		}
		if ( $record['source_index'] < 0 ) {
			$errors[] = 'source_index negatif olamaz.';
		}
		if ( 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $record['slug'] ) ) {
			$errors[] = 'slug biçimi geçersiz.';
		}
		if ( 1 !== preg_match( '/^faq:[a-z0-9]+(-[a-z0-9]+)*\z/', $record['source_key'] ) ) {
			$errors[] = 'source_key biçimi geçersiz ("faq:<slug>" olmalı).';
		}
		if ( empty( $errors ) && 'faq:' . $record['slug'] !== $record['source_key'] ) {
			$errors[] = 'source_key, slug ile tutarlı değil ("faq:" + slug olmalı).';
		}
		if ( ! self::is_canonical_plain_text( $record['question'] ) || ! self::is_canonical_plain_text( $record['answer'] ) ) {
			$errors[] = "question/answer kanonik düz metin olmalı (baş/son boşluk, '<', '>', kontrol karakteri veya geçersiz UTF-8 içeremez).";
		} else {
			$expectedSlug = self::slugify_tr( $record['question'] );
			if ( null === $expectedSlug || $expectedSlug !== $record['slug'] ) {
				$errors[] = 'slug, slugify(question) ile tutarlı değil.';
			}
		}

		return empty( $errors ) ? self::ok() : self::fail( $errors );
	}

	/**
	 * Faz 12 — sayfa kaydı. `parent_source_key` bu sürümde KESİNLİKLE null'dır (WordPress sayfa hiyerarşisi kalıcı bağlantıları
	 * değiştirirdi; 32 sayfanın hepsi düz URL'dir). Manifest düzeyindeki parent grafiği (kayıp/döngü) Node doğrulayıcısında ayrıca
	 * denetlenir; PHP tarafı yalnız null'ı kabul ederek bağımlılık çözümü gerektiren durumu YAPISAL olarak dışlar.
	 * `content` KAPALI izin listeli kanonik HTML olmalıdır (bkz. page_content_errors()); `content_sha256` içerikten yeniden hesaplanır.
	 *
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate_page( $record ) {
		$errors = array();

		if ( ! is_array( $record ) ) {
			return self::fail( array( 'Sayfa kaydı bir dizi (object) değil.' ) );
		}

		$extra = self::extra_keys( $record, self::PAGE_SCHEMA_KEYS );
		if ( ! empty( $extra ) ) {
			$errors[] = 'Beklenmeyen alan(lar): ' . implode( ', ', $extra ) . '.';
		}

		self::require_string( $record, 'schema_version', $errors, 1 );
		self::require_string( $record, 'source_key', $errors, 1 );
		self::require_int( $record, 'source_index', $errors );
		self::require_string( $record, 'slug', $errors, 1 );
		self::require_string( $record, 'title', $errors, 1 );
		self::require_string( $record, 'content', $errors, 0 );
		self::require_string( $record, 'excerpt', $errors, 0 );
		self::require_string_or_null( $record, 'parent_source_key', $errors );
		self::require_int( $record, 'menu_order', $errors );
		self::require_string( $record, 'page_template', $errors, 0 );
		self::require_string( $record, 'post_status', $errors, 1 );
		self::require_string( $record, 'layout', $errors, 1 );
		self::require_string( $record, 'content_sha256', $errors, 1 );
		self::require_array( $record, 'pending_decisions', $errors );
		self::require_bool( $record, 'publish_hold', $errors );
		self::require_array( $record, 'publish_requires', $errors );
		self::require_source_object( $record, $errors );

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( '2.0.0' !== $record['schema_version'] ) {
			$errors[] = 'schema_version "2.0.0" olmalı.';
		}
		if ( ! self::is_list_array( $record['publish_requires'] ) || $record['publish_requires'] !== array_values( array_unique( $record['publish_requires'] ) ) || array() !== array_diff( $record['publish_requires'], self::PAGE_PUBLISH_REQUIRES ) ) {
			$errors[] = 'publish_requires kapalı kümeden (faq/reference) benzersiz kodlar içeren bir liste olmalı.';
		}
		if ( $record['source_index'] < 0 || $record['source_index'] >= 32 ) {
			$errors[] = 'source_index 0..31 aralığında olmalı.';
		}
		if ( 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $record['slug'] ) ) {
			$errors[] = 'slug biçimi geçersiz.';
		}
		if ( 1 !== preg_match( '/^page:[a-z0-9]+(-[a-z0-9]+)*\z/', $record['source_key'] ) ) {
			$errors[] = 'source_key biçimi geçersiz ("page:<slug>" olmalı).';
		}
		if ( empty( $errors ) && 'page:' . $record['slug'] !== $record['source_key'] ) {
			$errors[] = 'source_key, slug ile tutarlı değil ("page:" + slug olmalı).';
		}
		if ( ! self::is_canonical_plain_text( $record['title'] ) || 1 === preg_match( '/demo/i', $record['title'] ) ) {
			$errors[] = 'title kanonik düz metin olmalı ve "demo" içermemeli.';
		}
		if ( '' !== $record['excerpt'] && ( ! self::is_canonical_plain_text( $record['excerpt'] ) || false !== strpos( $record['excerpt'], '&' ) || false !== strpos( $record['excerpt'], "\n" ) || self::str_length( $record['excerpt'] ) > 300 ) ) {
			$errors[] = 'excerpt düz metin olmalı (en çok 300 karakter, işaretleme/satır sonu yok).';
		}
		if ( null !== $record['parent_source_key'] ) {
			$errors[] = 'parent_source_key bu sürümde KESİNLİKLE null olmalı (düz URL yapısı).';
		}
		if ( $record['menu_order'] < 1 || $record['menu_order'] > 32 ) {
			$errors[] = 'menu_order 1..32 aralığında olmalı.';
		}
		if ( '' !== $record['page_template'] ) {
			$errors[] = 'page_template bu sürümde boş olmalı (tema slug/layout ile seçer).';
		}
		if ( 'draft' !== $record['post_status'] ) {
			$errors[] = 'hedef post_status KESİNLİKLE "draft" olmalı (yayınlama ayrı, onaylı işlemdir).';
		}
		if ( ! in_array( $record['layout'], self::PAGE_LAYOUTS, true ) ) {
			$errors[] = 'layout kapalı kümede değil.';
		}
		foreach ( self::page_content_errors( $record['content'] ) as $contentError ) {
			$errors[] = 'content ' . $contentError . '.';
		}
		if ( 1 !== preg_match( '/^[0-9a-f]{64}\z/', $record['content_sha256'] ) || hash( 'sha256', $record['content'] ) !== $record['content_sha256'] ) {
			$errors[] = 'content_sha256 içerikten hesaplanandan farklı.';
		}
		$hold = false;
		if ( ! self::is_list_array( $record['pending_decisions'] ) ) {
			$errors[] = 'pending_decisions liste olmalı.';
		} else {
			foreach ( $record['pending_decisions'] as $code ) {
				if ( ! is_string( $code ) || ! array_key_exists( $code, self::PAGE_PENDING_DECISIONS ) ) {
					$errors[] = 'pending_decisions kapalı sözlükte olmayan kod içeriyor.';
					break;
				}
				$hold = $hold || true === self::PAGE_PENDING_DECISIONS[ $code ];
			}
			if ( $record['publish_hold'] !== $hold ) {
				$errors[] = 'publish_hold bekleyen kararlardan türetilene eşit olmalı.';
			}
		}
		if ( is_string( $record['slug'] ) && ! in_array( $record['source']['file'], array( 'tanitim-site/' . $record['slug'] . '.html', self::PAGE_APPROVED_SOURCE_DIR . '/' . $record['slug'] . '.html' ), true ) ) {
			$errors[] = 'source.file "tanitim-site/<slug>.html" veya "' . self::PAGE_APPROVED_SOURCE_DIR . '/<slug>.html" olmalı.';
		}

		return empty( $errors ) ? self::ok() : self::fail( $errors );
	}

	/**
	 * Faz 12 — sayfa içeriği KAPALI izin listesi denetimi (tools/import/lib/page-content.js `sanitizeCheck()` ile AYNI kurallar).
	 * Kanonik biçim: bloklar "\n" ile ayrılır; yalnız p, h2-h4, ul, ol, li, a[href], strong, em, br (`<br />`). Script, olay işleyici,
	 * iframe, görsel, stil, yorum, tehlikeli URL şemaları (javascript:, data:, vbscript:, //, http:) ve iç içe/kapanmamış etiket reddedilir.
	 *
	 * @param mixed $html
	 * @return string[] Hata listesi (boş = geçerli).
	 */
	public static function page_content_errors( $html ) {
		if ( ! is_string( $html ) ) {
			return array( 'string değil' );
		}
		if ( '' === $html ) {
			return array();
		}
		$errors = array();
		if ( 1 !== preg_match( '//u', $html ) ) {
			return array( 'geçersiz UTF-8' );
		}
		if ( 1 === preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $html ) ) {
			$errors[] = 'kontrol karakteri yasak';
		}
		if ( 1 === preg_match( '/<!|<\?/', $html ) ) {
			$errors[] = 'yorum/doctype/işleme talimatı yasak';
		}
		$inlineParents = array( 'p', 'h2', 'h3', 'h4', 'li', 'strong', 'em' );
		$textParents   = array( 'p', 'h2', 'h3', 'h4', 'li', 'a', 'strong', 'em' );
		$stack         = array();
		$last          = 0;
		$count         = preg_match_all( '/<(\/?)([A-Za-z][A-Za-z0-9]*)((?:[^<>"]|"[^"]*")*)>|<|>|&[^;\s]*;?/', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		if ( false === $count ) {
			return array( 'ayrıştırılamadı' );
		}
		$textCheck = function ( $seg ) use ( &$stack, &$errors, $textParents ) {
			if ( '' === $seg ) {
				return;
			}
			$top      = empty( $stack ) ? null : $stack[ count( $stack ) - 1 ];
			$inInline = null !== $top && in_array( $top, $textParents, true );
			if ( ! $inInline && "\n" !== $seg ) {
				$errors[] = 'blok düzeyinde beklenmeyen metin';
			}
			if ( $inInline && false !== strpos( $seg, "\n" ) ) {
				$errors[] = 'satır içi bağlamda satır sonu yasak';
			}
		};
		foreach ( $matches as $m ) {
			$whole  = $m[0][0];
			$offset = $m[0][1];
			$textCheck( substr( $html, $last, $offset - $last ) );
			$last = $offset + strlen( $whole );
			if ( '<' === $whole || '>' === $whole ) {
				$errors[] = 'kaçışsız ' . $whole . ' karakteri';
				continue;
			}
			if ( '&' === $whole[0] ) {
				if ( 1 !== preg_match( '/^&(amp|lt|gt|quot|#39);\z/', $whole ) ) {
					$errors[] = 'izinsiz karakter varlığı';
				}
				if ( empty( $stack ) ) {
					$errors[] = 'blok düzeyinde varlık';
				}
				continue;
			}
			$closing = '/' === $m[1][0];
			$rawTag  = $m[2][0];
			$tag     = strtolower( $rawTag );
			if ( $rawTag !== $tag ) {
				$errors[] = 'etiket adı küçük harf olmalı';
				continue;
			}
			if ( ! in_array( $tag, self::PAGE_CONTENT_TAGS, true ) ) {
				$errors[] = 'izinsiz etiket: <' . $tag . '>';
				continue;
			}
			$attr = $m[3][0];
			$top  = empty( $stack ) ? null : $stack[ count( $stack ) - 1 ];
			if ( $closing ) {
				if ( '' !== trim( $attr ) ) {
					$errors[] = 'kapanış etiketinde nitelik';
				}
				if ( null === $top || $top !== $tag ) {
					$errors[] = 'kapanış etiketi eşleşmiyor: </' . $tag . '>';
				} else {
					array_pop( $stack );
				}
				continue;
			}
			if ( 'br' === $tag ) {
				if ( ' /' !== $attr ) {
					$errors[] = '<br /> kanonik biçimde olmalı';
				}
				if ( null === $top || ! in_array( $top, $inlineParents, true ) ) {
					$errors[] = '<br /> bu bağlamda yasak';
				}
				continue;
			}
			if ( 'a' === $tag ) {
				if ( 1 !== preg_match( '/^ href="([^"]*)"\z/', $attr, $am ) ) {
					$errors[] = '<a> yalnız href niteliği taşıyabilir';
				} else {
					$href = str_replace( '&amp;', '&', $am[1] );
					$ok   = 1 === preg_match( '#^/[a-z0-9/_-]*(\#[a-z0-9-]+)?\z#', $href )
						|| 1 === preg_match( '#^https://[A-Za-z0-9.-]+(:[0-9]+)?(/[^\s"<>]*)?\z#', $href )
						|| 1 === preg_match( '/^tel:\+?[0-9]+\z/', $href )
						|| 1 === preg_match( '/^mailto:[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\z/', $href );
					if ( ! $ok || 0 === strpos( $href, '//' ) ) {
						$errors[] = 'güvensiz bağlantı hedefi';
					}
				}
				if ( in_array( 'a', $stack, true ) ) {
					$errors[] = 'iç içe <a> yasak';
				}
				if ( null === $top || ! in_array( $top, $inlineParents, true ) ) {
					$errors[] = '<a> bu bağlamda yasak';
				}
			} elseif ( '' !== trim( $attr ) ) {
				$errors[] = '<' . $tag . '> nitelik taşıyamaz';
			}
			if ( in_array( $tag, array( 'p', 'h2', 'h3', 'h4', 'ul', 'ol' ), true ) && null !== $top ) {
				$errors[] = '<' . $tag . '> yalnız üst düzeyde olabilir';
			}
			if ( 'li' === $tag && 'ul' !== $top && 'ol' !== $top ) {
				$errors[] = '<li> yalnız liste içinde';
			}
			if ( ( 'strong' === $tag || 'em' === $tag ) && ( null === $top || 'ul' === $top || 'ol' === $top ) ) {
				$errors[] = '<' . $tag . '> bu bağlamda yasak';
			}
			$stack[] = $tag;
		}
		$textCheck( substr( $html, $last ) );
		if ( ! empty( $stack ) ) {
			$errors[] = 'kapanmamış etiket: <' . $stack[ count( $stack ) - 1 ] . '>';
		}
		return array_values( array_unique( $errors ) );
	}

	/** Düz metin kanonik biçimi (bkz. validate_news()). */
	private static function is_canonical_plain_text( $value ) {
		if ( ! is_string( $value ) || '' === $value || $value !== trim( $value ) ) {
			return false;
		}
		if ( 1 !== preg_match( '//u', $value ) || false !== strpos( $value, '<' ) || false !== strpos( $value, '>' ) ) {
			return false;
		}
		return 1 !== preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value );
	}

	/**
	 * @return array{valid: bool, errors: string[], normalized_price_options?: array}
	 *   `normalized_price_options` yalnız valid=true iken bulunur — KANONİK
	 *   `MaviBelge_Core_Validator::evaluate_price_options()` (Faz 5'in atomik
	 *   ret + sıralama kuralı) çıktısıdır; projeksiyon bunu OLDUĞU GİBİ
	 *   kullanır, ikinci bir gevşek cast/skip yolu açmaz.
	 */
	public static function validate_fee( $record ) {
		$errors = array();

		if ( ! is_array( $record ) ) {
			return self::fail( array( 'Ücret kaydı bir dizi (object) değil.' ) );
		}

		$extra = self::extra_keys( $record, self::FEE_SCHEMA_KEYS );
		if ( ! empty( $extra ) ) {
			$errors[] = 'Beklenmeyen alan(lar): ' . implode( ', ', $extra ) . '.';
		}

		self::require_string( $record, 'schema_version', $errors, 1 );
		self::require_string( $record, 'source_key', $errors, 1 );
		self::require_int( $record, 'source_index', $errors );
		self::require_string( $record, 'profession_name', $errors, 1 );
		self::require_int( $record, 'level', $errors );
		self::require_string( $record, 'sector_slug', $errors, 1 );
		self::require_string( $record, 'qualification_code', $errors, 0 );
		self::require_string_or_null( $record, 'qualification_source_key', $errors );
		self::require_string( $record, 'pricing_type', $errors, 1 );
		self::require_array( $record, 'price_options', $errors );
		self::require_int( $record, 'min_amount_kurus', $errors );
		self::require_int( $record, 'max_amount_kurus', $errors );
		self::require_bool( $record, 'vat_included', $errors );
		self::require_int( $record, 'certificate_print_fee_kurus', $errors );
		self::require_string( $record, 'source_name', $errors, 0 );
		self::require_int_or_null( $record, 'source_page', $errors );
		self::require_int( $record, 'source_attachment_id', $errors );
		self::require_string( $record, 'planned_tariff_period', $errors, 1 );
		self::require_string( $record, 'planned_record_status', $errors, 1 );
		self::require_string( $record, 'planned_valid_from', $errors, 0 );
		self::require_string( $record, 'planned_valid_until', $errors, 0 );
		self::require_source_object( $record, $errors );

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( '2.0.0' !== $record['schema_version'] ) {
			$errors[] = 'schema_version "2.0.0" olmalı.';
		}
		if ( $record['source_index'] < 0 ) {
			$errors[] = 'source_index negatif olamaz.';
		}
		if ( ! preg_match( '/^fee:[a-z0-9]+(-[a-z0-9]+)*:[1-8]:[a-z0-9]+(-[a-z0-9]+)*$/', $record['source_key'] ) ) {
			$errors[] = 'source_key biçimi geçersiz ("fee:<sektör>:<seviye>:<meslek>" olmalı).';
		}
		if ( $record['level'] < 1 || $record['level'] > 8 ) {
			$errors[] = 'level 1-8 aralığında olmalı.';
		}
		if ( ! MaviBelge_Core_Validator::is_valid_slug_format( $record['sector_slug'] ) || '' === $record['sector_slug'] ) {
			$errors[] = 'sector_slug biçimi geçersiz.';
		}
		if ( $record['min_amount_kurus'] < 1 ) {
			$errors[] = 'min_amount_kurus pozitif bir tam sayı olmalı.';
		}
		if ( $record['max_amount_kurus'] < 1 ) {
			$errors[] = 'max_amount_kurus pozitif bir tam sayı olmalı.';
		}
		if ( empty( $errors ) && $record['min_amount_kurus'] > $record['max_amount_kurus'] ) {
			$errors[] = 'min_amount_kurus, max_amount_kurus\'tan büyük olamaz.';
		}
		// Ücretin source_key'i, Faz 6A'nın TEK kararlı formülüyle
		// ("fee:" + sector_slug + ":" + level + ":" + slugify_tr(profession_name),
		// bkz. tools/import/lib/slug.js) YENİDEN üretilip TAM eşitlik
		// kontrol edilir — yalnız önek karşılaştırması DEĞİL. Fuzzy
		// eşleştirme yapılmaz; slugify başarısızsa (geçersiz UTF-8) kayıt
		// fail-closed reddedilir.
		if ( empty( $errors ) ) {
			$professionSlug = self::slugify_tr( $record['profession_name'] );
			if ( null === $professionSlug ) {
				$errors[] = 'profession_name geçersiz UTF-8 içeriyor; source_key formülü hesaplanamadı.';
			} else {
				$expectedSourceKey = 'fee:' . $record['sector_slug'] . ':' . $record['level'] . ':' . $professionSlug;
				if ( $expectedSourceKey !== $record['source_key'] ) {
					$errors[] = 'source_key, "fee:" + sector_slug + ":" + level + ":" + slugify(profession_name) formülüyle tutarlı değil.';
				}
			}
		}
		if ( ! MaviBelge_Core_Validator::is_valid_myk_code_format( $record['qualification_code'] ) ) {
			$errors[] = 'qualification_code biçimi geçersiz.';
		}
		if ( ! in_array( $record['pricing_type'], array( 'single', 'unit', 'package', 'multiple' ), true ) ) {
			$errors[] = 'pricing_type geçersiz (single/unit/package/multiple olmalı).';
		}
		if ( null === $record['source_page'] ) {
			// Şema null'a izin verir — geçerli.
		} elseif ( $record['source_page'] < 1 ) {
			$errors[] = 'source_page pozitif bir tam sayı veya null olmalı.';
		}
		if ( 0 !== $record['source_attachment_id'] ) {
			$errors[] = 'source_attachment_id bu fazda KESİNLİKLE 0 olmalı (henüz medya aktarılmadı).';
		}
		if ( 'draft' !== $record['planned_record_status'] ) {
			$errors[] = 'planned_record_status bu fazda KESİNLİKLE "draft" olmalı.';
		}
		if ( '2026' !== $record['planned_tariff_period'] ) {
			$errors[] = 'planned_tariff_period bu fazda KESİNLİKLE "2026" olmalı.';
		}
		if ( '' !== $record['planned_valid_from'] ) {
			$errors[] = 'planned_valid_from bu fazda KESİNLİKLE boş olmalı.';
		}
		if ( '' !== $record['planned_valid_until'] ) {
			$errors[] = 'planned_valid_until bu fazda KESİNLİKLE boş olmalı.';
		}

		// Çapraz-alan: kod boş <=> source_key null; kod dolu <=> source_key "qualification:"+kod.
		if ( '' === $record['qualification_code'] ) {
			if ( null !== $record['qualification_source_key'] ) {
				$errors[] = 'qualification_code boşken qualification_source_key KESİNLİKLE null olmalı.';
			}
		} elseif ( 'qualification:' . $record['qualification_code'] !== $record['qualification_source_key'] ) {
			$errors[] = 'qualification_source_key, qualification_code ile tutarlı değil.';
		}

		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		// Fiyat seçenekleri: KANONİK, atomik-ret doğrulayıcı — kısmi/sessiz
		// normalizasyon yok. Herhangi bir satır geçersizse (veya hiç geçerli
		// satır kalmazsa) TÜM kayıt invalid olur; hiçbir seçenek hashlenmez.
		$priceResult = MaviBelge_Core_Validator::evaluate_price_options( $record['price_options'] );
		if ( ! $priceResult['replace'] ) {
			return self::fail( array_merge( array( 'price_options: en az bir seçenek geçersiz; TÜM liste reddedildi (atomik ret).' ), $priceResult['errors'] ) );
		}
		if ( count( $priceResult['options'] ) !== count( $record['price_options'] ) ) {
			// evaluate_price_options() yalnız TAMAMEN boş "ilerlemeli-form
			// doldurma" satırlarını sessizce atlar (bkz. class-validator.php);
			// gerçek manifest verisinde böyle bir satır asla olmamalı — varsa
			// bu, kaynakla hash'lenecek veri arasında sessiz bir sayı
			// farkı demektir, fail-closed reddedilir.
			return self::fail( array( 'price_options: gönderilen satır sayısı ile geçerli sayılan satır sayısı uyuşmuyor (sessiz atlama şüphesi).' ) );
		}
		if ( empty( $priceResult['options'] ) ) {
			return self::fail( array( 'price_options: en az bir geçerli fiyat seçeneği olmalı.' ) );
		}

		// Çapraz-alan: min_amount_kurus/max_amount_kurus, GEÇERLİ fiyat
		// seçeneklerinden YENİDEN hesaplanan değerle birebir eşleşmeli —
		// sessizce iki bağımsız kopya birbirinden sapamaz (bkz.
		// tools/import/build-manifest.js'in aynı hesaplamayı yaptığı satır
		// 270-271, TEK kaynak burada da yeniden uygulanıyor).
		$amounts = array();
		foreach ( $priceResult['options'] as $option ) {
			$amounts[] = $option['amount_kurus'];
		}
		$recomputedMin = min( $amounts );
		$recomputedMax = max( $amounts );
		if ( $recomputedMin !== $record['min_amount_kurus'] ) {
			return self::fail( array( 'min_amount_kurus, price_options\'tan yeniden hesaplanan değerle uyuşmuyor.' ) );
		}
		if ( $recomputedMax !== $record['max_amount_kurus'] ) {
			return self::fail( array( 'max_amount_kurus, price_options\'tan yeniden hesaplanan değerle uyuşmuyor.' ) );
		}

		return array( 'valid' => true, 'errors' => array(), 'normalized_price_options' => $priceResult['options'] );
	}

	/**
	 * Üst-seviye plan() girdisi: `{sectors: array, qualifications: array,
	 * fees: array}` — eksik anahtar, null, scalar, liste-olmayan değer veya
	 * fazladan üst-seviye anahtar → tek, açık hata; hiçbir entry üretilmez
	 * (görev promptu §5).
	 *
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate_manifest_shape( $manifest ) {
		if ( ! is_array( $manifest ) ) {
			return self::fail( array( 'manifest bir dizi (object) değil.' ) );
		}

		$expectedKeys = array( 'sectors', 'qualifications', 'fees' );
		// Faz 7 — İSTEĞE BAĞLI içerik listeleri: varsa liste olmalı, yoksa boş sayılır.
		$optionalKeys = array( 'news', 'references', 'faqs', 'pages' );
		$errors       = array();

		$extraKeys = array_diff( array_keys( $manifest ), array_merge( $expectedKeys, $optionalKeys ) );
		if ( ! empty( $extraKeys ) ) {
			$errors[] = 'manifest üst seviyesinde beklenmeyen anahtar(lar): ' . implode( ', ', $extraKeys );
		}

		foreach ( $optionalKeys as $key ) {
			if ( array_key_exists( $key, $manifest ) && ( ! is_array( $manifest[ $key ] ) || ! self::is_list_array( $manifest[ $key ] ) ) ) {
				$errors[] = "manifest.{$key} bir liste (array) olmalı.";
			}
		}

		foreach ( $expectedKeys as $key ) {
			if ( ! array_key_exists( $key, $manifest ) ) {
				$errors[] = "manifest.{$key} eksik.";
				continue;
			}
			if ( ! is_array( $manifest[ $key ] ) || ! self::is_list_array( $manifest[ $key ] ) ) {
				$errors[] = "manifest.{$key} bir liste (array) olmalı.";
			}
		}

		return empty( $errors ) ? self::ok() : self::fail( $errors );
	}

	/**
	 * Üç listedeki TÜM source_key'lerin (yalnız kendi listesi içinde değil,
	 * ÜÇ LİSTE BİRLİKTE) benzersiz olduğunu kontrol eder. Biçimsiz/eksik
	 * source_key taşıyan kayıtlar da (tip string değilse) burada ayrıca
	 * raporlanır — sessizce taramadan kaçıp belirsiz bir entry üretmesinler
	 * diye.
	 *
	 * @return array{errors: string[], duplicates: string[]}
	 */
	public static function find_source_key_problems( array $allRecords ) {
		$seen        = array();
		$duplicates  = array();
		$malformed   = 0;
		foreach ( $allRecords as $record ) {
			if ( ! is_array( $record ) || ! array_key_exists( 'source_key', $record ) ) {
				$malformed++;
				continue;
			}
			$key = $record['source_key'];
			if ( ! is_string( $key ) || '' === $key ) {
				$malformed++;
				continue;
			}
			if ( isset( $seen[ $key ] ) ) {
				$duplicates[ $key ] = true;
			}
			$seen[ $key ] = true;
		}
		$errors = array();
		if ( $malformed > 0 ) {
			$errors[] = $malformed . ' kayıtta source_key eksik/boş/string-olmayan — bu kayıtlar kendi tip-doğrulayıcısında (invalid olarak) ayrıca raporlanır.';
		}
		return array( 'errors' => $errors, 'duplicates' => array_keys( $duplicates ) );
	}

	/**
	 * Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri §2.2 — TEK türdeki (sektör/
	 * yeterlilik/ücret) bir kayıt listesinin BATCH bütünlüğünü kontrol
	 * eder. `validate_manifest_shape()` yalnız her listenin gerçek bir
	 * `0..n-1` liste (sequential PHP array) olduğunu garanti eder — bu,
	 * kayıtların KENDİ `source_index` ALANININ gerçek liste konumuyla
	 * eşleştiğini VEYA aynı listedeki tüm kayıtların AYNI kaynak dosya/
	 * SHA-256'ya işaret ettiğini garanti ETMEZ. Bu metod ikisini de
	 * doğrular:
	 *
	 * 1. Her kaydın `source_index`'i (gerçek int ise) kendi listedeki
	 *    GERÇEK sıfır-tabanlı konumuyla BİREBİR eşleşmeli — tekrarlanan,
	 *    atlanan veya ters çevrilmiş bir `source_index` reddedilir.
	 * 2. Aynı listedeki TÜM kayıtların `source.file`/`source.sha256`'ı
	 *    BİRBİRİYLE AYNI olmalı — tek bir kaynak dosyadan üretilmiş bir
	 *    manifestte "provenance drift" (bir kaydın başka bir dosyadan/
	 *    sürümden geldiğini iddia etmesi) sessizce kabul edilmez.
	 *
	 * Şekli zaten bozuk olan kayıtlar (dizi değil, `source_index`/`source`
	 * eksik/yanlış tip) burada ATLANIR — onlar zaten kendi
	 * `validate_sector()`/`validate_qualification()`/`validate_fee()`
	 * yolunda `invalid` olarak raporlanır; bu metod yalnız İYİ-ŞEKİLLİ
	 * kayıtlar ARASINDAKİ pozisyon/provenance TUTARLILIĞINI denetler.
	 *
	 * @param array  $records   Tek bir türün listesi (ör. `manifest['fees']`).
	 * @param string $typeLabel Hata mesajlarında kullanılan kısa etiket (ör. "fees").
	 * @return string[] Hata mesajları (boşsa sorun yok).
	 */
	public static function check_batch_positional_integrity( array $records, $typeLabel ) {
		$errors        = array();
		$expectedFile  = null;
		$expectedSha   = null;
		$provenanceSet = false;

		foreach ( $records as $index => $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			if ( array_key_exists( 'source_index', $record ) && is_int( $record['source_index'] ) && $record['source_index'] !== $index ) {
				$errors[] = "{$typeLabel}[{$index}]: source_index ({$record['source_index']}) gerçek liste konumuyla ({$index}) uyuşmuyor.";
			}
			if ( 'pages' !== $typeLabel && array_key_exists( 'source', $record ) && is_array( $record['source'] ) ) {
				$file = array_key_exists( 'file', $record['source'] ) ? $record['source']['file'] : null;
				$sha  = array_key_exists( 'sha256', $record['source'] ) ? $record['source']['sha256'] : null;
				if ( ! $provenanceSet ) {
					$expectedFile  = $file;
					$expectedSha   = $sha;
					$provenanceSet = true;
				} elseif ( $file !== $expectedFile || $sha !== $expectedSha ) {
					$errors[] = "{$typeLabel}[{$index}]: source.file/source.sha256, aynı türdeki diğer kayıtlarla tutarsız (provenance drift).";
				}
			}
		}

		return $errors;
	}

	/**
	 * Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri §2.3 — bir dependency
	 * haritasındaki bir çözümün GERÇEKTEN "çözülmüş" sayılabilmesi için
	 * artık salt bir `int` YETMEZ: adapter'ın hedefin BEKLENEN türde
	 * olduğunu (gerçek `mb_sektor` term'ü / gerçek `mb_yeterlilik` post'u /
	 * gerçek attachment) DOĞRULADIĞININ kanıtını da taşıyan typed bir
	 * sonuç OLMAK ZORUNDADIR: `{id: int>0, type_verified: true}`. Bu saf
	 * katman WordPress'i sorgulayıp türü kendisi kontrol EDEMEZ — bu
	 * yüzden kanıtı, veriyle birlikte TAŞINAN bir bayrak olarak talep eder
	 * (bkz. `faz6b2-entegrasyon-notu.md`). Aşağıdakilerin HERHANGİ biri
	 * GEÇERSİZ sayılır (çözülmemiş, `blocked_dependency` — create/update
	 * ASLA üretilmez): üst container/alt map dizi değil, `itemKey` alt
	 * map'te yok, girdi dizi değil, `id`/`type_verified` DIŞINDA fazladan
	 * anahtar var, `id` gerçek pozitif `int` değil, `type_verified` GERÇEK
	 * `true` değil (eksik/`false`/numeric/string "true" dahil).
	 *
	 * @param mixed  $dependencies Ham `$dependencies` (dizi olmayabilir — fail-closed `null` döner, fırlatmaz).
	 * @param string $mapKey       ör. `sector_term_ids`, `qualification_post_ids`, `sector_image_attachment_ids`.
	 * @param string $itemKey      ör. sektör slug'ı / MYK kodu.
	 * @return int|null Gerçek pozitif ID, ya da çözülmemiş/geçersiz ise `null`.
	 */
	public static function resolve_verified_dependency( $dependencies, $mapKey, $itemKey ) {
		if ( ! is_array( $dependencies ) || ! array_key_exists( $mapKey, $dependencies ) || ! is_array( $dependencies[ $mapKey ] ) ) {
			return null;
		}
		$map = $dependencies[ $mapKey ];
		if ( ! array_key_exists( $itemKey, $map ) || ! is_array( $map[ $itemKey ] ) ) {
			return null;
		}
		$entry = $map[ $itemKey ];
		if ( ! empty( self::extra_keys( $entry, array( 'id', 'type_verified' ) ) ) ) {
			return null;
		}
		if ( ! array_key_exists( 'id', $entry ) || ! is_int( $entry['id'] ) || $entry['id'] <= 0 ) {
			return null;
		}
		if ( ! array_key_exists( 'type_verified', $entry ) || true !== $entry['type_verified'] ) {
			return null;
		}
		return $entry['id'];
	}

	/**
	 * Sözleşme Eşitleme §2.1 — `$dependencies` ÜST DTO'sunun izin verdiği
	 * TEK anahtar kümesi (kapalı politika). `resolve_verified_dependency()`
	 * yalnız ÇAĞRILAN alt map+öğeyi inceliyordu; bu, iki somut açık
	 * bırakıyordu: (a) `$dependencies`'e fazladan/bilinmeyen bir üst anahtar
	 * eklenmesi sessizce yok sayılıyordu, (b) bir alt map hiç KULLANILMASA
	 * bile (ör. görselsiz bir sektöre verilmiş bozuk
	 * `sector_image_attachment_ids` container'ı — sektörün görseli
	 * olmadığı için o anahtar HİÇ okunmuyor) hiç incelenmeden geçebiliyordu.
	 * `validate_dependencies_shape()` bunu kapatır — bkz. docblock'u.
	 */
	const ALLOWED_DEPENDENCY_KEYS = array( 'sector_term_ids', 'sector_image_attachment_ids', 'qualification_post_ids' );

	/**
	 * Faz 7 — TEK isteğe bağlı bağımlılık anahtarı: `news_type_term_ids`
	 * (`haber`/`duyuru` => {id: int>0, type_verified: true}). Verilmezse
	 * haber kayıtları çözülmemiş sayılır (`blocked_dependency`); verilmişse
	 * diğer üç anahtarla AYNI kapalı şekil uygulanır.
	 */
	const OPTIONAL_DEPENDENCY_KEYS = array( 'news_type_term_ids' );

	/**
	 * `plan()`/`plan_sector()`/`plan_qualification()`/`plan_fee()`'nin
	 * HEPSİNİN ortak, TEK `$dependencies` üst-DTO doğrulama kapısı.
	 *
	 * Faz 6B1 Zorunlu Dependency DTO Kapanışı: önceki turda (Sözleşme
	 * Eşitleme) bu metod bilinçli olarak üç anahtarın HEPSİNİN VERİLMİŞ
	 * olmasını ZORUNLU KILMIYORDU ("eksik anahtar" yapısal hata
	 * SAYILMIYORDU) — bu, bağımsız incelemede bulunan somut bir açık
	 * olarak işaretlendi: boş `$dependencies = array()` ile tam `plan()`
	 * çağrıldığında görselsiz sektörler/kodsuz ücretler `create`
	 * olabiliyor, DİĞER kayıtlar `blocked_dependency` oluyordu — oysa
	 * adapter sözleşmesinin KENDİSİ bozuk olduğu için planın baştan
	 * `entries=[]` ile reddedilmesi gerekirdi. Bu tur bu açığı kapatır:
	 *
	 * Kapsam:
	 * - `$dependencies` gerçek bir dizi DEĞİLSE yapısal hata.
	 * - `ALLOWED_DEPENDENCY_KEYS` DIŞINDA bir üst-seviye anahtar VARSA
	 *   yapısal hata.
	 * - Üç anahtardan HERHANGİ BİRİ EKSİKSE (artık) yapısal hata — üçü de
	 *   MUTLAKA verilmiş olmalı (boş `array()` değer olarak GEÇERLİDİR,
	 *   anahtarın kendisinin YOKLUĞU geçerli DEĞİLDİR).
	 * - Her alt map GERÇEK bir dizi olmalı; boş olmayan sayısal (0-tabanlı
	 *   ardışık index'li) bir liste geçerli bir map SAYILMAZ — anahtarlar
	 *   ilgili kimlik biçimini (sektör map'lerinde geçerli slug, yeterlilik
	 *   map'inde geçerli MYK kodu) taşımalı.
	 * - Her map DEĞERİ kapalı `{id: int>0, type_verified: true}` şeklinde
	 *   olmalı — ilgili kayıt o plan turunda hiç KULLANILMASA bile
	 *   (görselsiz sektörde `sector_image_attachment_ids` içindeki
	 *   kullanılmayan bir girdi gibi) bozuk/fazla anahtarlı bir typed değer
	 *   sessizce kalamaz.
	 * - Geçerli tam DTO içinde boş map veya ilgili item'ın YOKLUĞU yapısal
	 *   hata DEĞİLDİR — bu, `resolve_verified_dependency()`'nin ayrı
	 *   görevidir ve gerçek "çözülemedi" (`blocked_dependency`) durumunu
	 *   üretmeye devam eder.
	 *
	 * @param mixed $dependencies
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate_dependencies_shape( $dependencies ) {
		if ( ! is_array( $dependencies ) ) {
			return self::fail( array( 'dependencies bir dizi (object) değil.' ) );
		}
		$errors = array();
		$extra  = self::extra_keys( $dependencies, array_merge( self::ALLOWED_DEPENDENCY_KEYS, self::OPTIONAL_DEPENDENCY_KEYS ) );
		if ( ! empty( $extra ) ) {
			$errors[] = 'dependencies üst seviyesinde beklenmeyen anahtar(lar): ' . implode( ', ', $extra ) . '.';
		}
		$checkKeys = self::ALLOWED_DEPENDENCY_KEYS;
		foreach ( self::OPTIONAL_DEPENDENCY_KEYS as $optionalKey ) {
			if ( array_key_exists( $optionalKey, $dependencies ) ) {
				$checkKeys[] = $optionalKey;
			}
		}
		foreach ( $checkKeys as $key ) {
			if ( ! array_key_exists( $key, $dependencies ) ) {
				$errors[] = "dependencies.{$key} eksik (bu anahtar her zaman verilmiş olmalı — boş dizi geçerlidir, yokluğu geçerli değildir).";
				continue;
			}
			if ( ! is_array( $dependencies[ $key ] ) ) {
				$errors[] = "dependencies.{$key} bir dizi (map) değil.";
				continue;
			}
			$map = $dependencies[ $key ];
			if ( ! empty( $map ) && self::is_list_array( $map ) ) {
				$errors[] = "dependencies.{$key} sayısal liste olamaz; anahtarlı map (slug/MYK kodu => {id,type_verified}) olmalı.";
				continue;
			}
			$isQualificationMap = ( 'qualification_post_ids' === $key );
			foreach ( $map as $itemKey => $itemValue ) {
				if ( ! is_string( $itemKey ) || '' === $itemKey ) {
					$errors[] = "dependencies.{$key} içinde string olmayan/boş bir anahtar var.";
					continue;
				}
				if ( 'news_type_term_ids' === $key ) {
					if ( ! in_array( $itemKey, self::NEWS_TYPES, true ) ) {
						$errors[] = "dependencies.{$key}[{$itemKey}] anahtarı yalnız haber/duyuru olabilir.";
						continue;
					}
				} elseif ( $isQualificationMap ) {
					if ( ! MaviBelge_Core_Validator::is_valid_myk_code_format( $itemKey ) ) {
						$errors[] = "dependencies.{$key}[{$itemKey}] anahtarı geçerli bir MYK kodu değil.";
						continue;
					}
				} elseif ( ! MaviBelge_Core_Validator::is_valid_slug_format( $itemKey ) ) {
					$errors[] = "dependencies.{$key}[{$itemKey}] anahtarı geçerli bir sektör slug'ı değil.";
					continue;
				}
				if ( ! is_array( $itemValue ) ) {
					$errors[] = "dependencies.{$key}[{$itemKey}] değeri bir dizi değil.";
					continue;
				}
				$itemExtra = self::extra_keys( $itemValue, array( 'id', 'type_verified' ) );
				if ( ! empty( $itemExtra ) ) {
					$errors[] = "dependencies.{$key}[{$itemKey}] beklenmeyen alan(lar) taşıyor: " . implode( ', ', $itemExtra ) . '.';
					continue;
				}
				$idOk = array_key_exists( 'id', $itemValue ) && is_int( $itemValue['id'] ) && $itemValue['id'] > 0;
				if ( ! $idOk ) {
					$errors[] = "dependencies.{$key}[{$itemKey}].id gerçek pozitif tam sayı olmalı.";
				}
				if ( ! array_key_exists( 'type_verified', $itemValue ) || true !== $itemValue['type_verified'] ) {
					$errors[] = "dependencies.{$key}[{$itemKey}].type_verified kesinlikle true olmalı.";
				}
			}
		}
		return empty( $errors ) ? self::ok() : self::fail( $errors );
	}

	/** `targetLookups[source_key]` sözleşmesinin izin verdiği TEK anahtar kümesi — kapalı politika (bkz. görev promptu §4 "beklenen lookup anahtar kümesi ve eksik/fazla anahtar politikası açık olsun"). */
	const ALLOWED_LOOKUP_KEYS = array(
		'target_found', 'target_id', 'duplicate_targets', 'target_type_matches',
		'has_source_key_marker', 'last_applied_hash', 'current_managed_fields',
		'natural_key',
	);

	/**
	 * Faz 6B3 Önkoşul — marker ile hedef BULUNAMADIĞINDA (target_found=false)
	 * gerçek adapter'ın doğal anahtar preflight sonucu. Yalnız bu kapalı
	 * kümeden bir string olabilir; yalnız target_found=false VE
	 * duplicate_targets=false iken verilebilir. Verilmemişse (null) "doğal
	 * anahtar KONTROL EDİLMEDİ" anlamına gelir — karar motoru geriye dönük
	 * uyumluluk için create üretebilir, ama plan girdisi
	 * natural_key_check=null taşır ve Faz 6B3 apply uygunluk katmanı
	 * (MaviBelge_Core_Import_Apply_Eligibility) böyle bir create'i ASLA
	 * yazma adayı saymaz.
	 * - none                : doğal anahtarla eşleşen hedef yok (create adayı olabilir)
	 * - unmanaged           : tek hedef var, marker'ı boş (import dışı/elle oluşturulmuş)
	 * - corrupt_marker      : tek hedef var, marker dizi/nesne veya doğru önekli ama biçimsiz
	 * - wrong_marker_prefix : tek hedef var, marker başka/bilinmeyen önek taşıyor
	 * - foreign_marker      : tek hedef var, marker aynı ailede geçerli ama FARKLI source_key
	 * - undiscovered_marker : tek hedef var, marker tam bu source_key ama marker keşfi onu görmedi (ör. çöp kutusundaki kayıt)
	 * - duplicate           : doğal anahtarla birden fazla hedef
	 * - query_error         : doğal anahtar sorgusu güvenilir yapılamadı
	 */
	const NATURAL_KEY_STATES = array(
		'none', 'unmanaged', 'corrupt_marker', 'wrong_marker_prefix',
		'foreign_marker', 'undiscovered_marker', 'duplicate', 'query_error',
	);

	/**
	 * `targetLookups[source_key]` sözleşmesi için TEK doğrulama/normalizasyon
	 * kapısı (görev promptu §7, Son Kapanış Düzeltmesi'nde katılaştırıldı).
	 * Girdi çelişkiliyse/biçimsizse ASLA istisna fırlatmaz veya "güvenli"
	 * bir tahminle devam etmez — `target_state_valid` false döner ve
	 * planlayıcı bunu kontrollü `conflict`/`invalid_target_state` olarak
	 * sınıflandırır (bkz. MaviBelge_Core_Import_Decision::classify()).
	 *
	 * Bu tur eklenenler:
	 * - `$lookup` gerçekten dizi DEĞİLSE (ör. `targetLookups[$sourceKey]`
	 *   yanlışlıkla bir string/int/bool olarak gelmişse) artık "hedef yok"
	 *   gibi sessizce boş diziye ÇEVRİLMEZ — doğrudan invalid_target_state.
	 * - Kapalı anahtar kümesi: `ALLOWED_LOOKUP_KEYS` dışında bir anahtar varsa invalid.
	 * - `target_found`/`duplicate_targets`/`has_source_key_marker` artık
	 *   `!empty()` ile DEĞİL, GERÇEK `bool` olarak zorunlu kılınır.
	 * - `target_found=true` iken `target_type_matches` anahtarı MUTLAKA
	 *   VERİLMİŞ olmalı (adapter'ın hedefin GERÇEK türünü kontrol ettiğinin
	 *   kanıtı — sessizce `true` varsayılmaz).
	 * - `target_found=false` iken `target_type_matches` (verilmişse) `true`
	 *   dışında bir değer, `has_source_key_marker=true`, veya dolu bir
	 *   `last_applied_hash` — hepsi çelişkili sayılır (bulunmayan bir
	 *   hedefin "türü uyuşuyor/marker'ı var/hash'i var" olması anlamsızdır).
	 * - Marker YOKKEN (`has_source_key_marker=false`) `last_applied_hash`
	 *   dolu olması da çelişkili sayılır (hash, yalnız markerlı bir kayıtta
	 *   anlamlıdır).
	 * - `target_found=true` VE `target_type_matches=true` iken
	 *   `current_managed_fields` ARTIK eksik/null OLAMAZ (gerçek bir eşleşen
	 *   hedefin yönetilen alan durumu okunabilir olmak ZORUNDADIR) — yalnız
	 *   anahtar kümesi değil, HER alanın DEĞER tipi/biçimi de
	 *   `validate_current_field_values()` ile ayrıca doğrulanır (yanlış
	 *   tipli bir mevcut-alan değeri artık sessizce hash'e giremez).
	 *
	 * @param mixed  $lookup Ham `$targetLookups[$sourceKey]` (yoksa boş dizi; dizi DEĞİLSE fail-closed).
	 * @param string $type   MaviBelge_Core_Import_Dry_Run_Planner::TYPE_* — hangi ALLOWLIST ile karşılaştırılacağını belirler.
	 * @return array{
	 *     target_state_valid: bool,
	 *     target_found: bool,
	 *     target_id: int|null,
	 *     duplicate_targets: bool,
	 *     target_type_matches: bool,
	 *     has_source_key_marker: bool,
	 *     last_applied_hash: string|null,
	 *     current_managed_fields: array|null,
	 * }
	 */
	public static function normalize_target_lookup( $lookup, $type ) {
		if ( ! is_array( $lookup ) ) {
			return self::invalid_target_lookup( false );
		}

		$unexpectedKeys = self::extra_keys( $lookup, self::ALLOWED_LOOKUP_KEYS );
		if ( ! empty( $unexpectedKeys ) ) {
			return self::invalid_target_lookup( false );
		}

		// Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri §2.1 — `target_found`
		// artık MUTLAKA açıkça verilmiş olmalı (varsayılan `false`'a
		// GÜVENİLMEZ). Bu, planlayıcının kanonik "not_found" DTO'sunu
		// (`array('target_found' => false)`) KENDİSİ ürettiği durumdan
		// AYRIDIR — caller `targetLookups[$sourceKey]`'i AÇIKÇA `[]` gibi
		// eksik bir değerle doldurduysa (anahtar VAR ama zorunlu
		// `target_found` bilgisi YOK), bu artık sessizce "bulunamadı"
		// sayılmaz, `invalid_target_state` olur.
		if ( ! array_key_exists( 'target_found', $lookup ) ) {
			return self::invalid_target_lookup( false );
		}
		if ( ! is_bool( $lookup['target_found'] ) ) {
			return self::invalid_target_lookup( false );
		}
		$targetFound = $lookup['target_found'];

		if ( array_key_exists( 'duplicate_targets', $lookup ) && ! is_bool( $lookup['duplicate_targets'] ) ) {
			return self::invalid_target_lookup( false );
		}
		$duplicateTargets = array_key_exists( 'duplicate_targets', $lookup ) ? $lookup['duplicate_targets'] : false;

		if ( array_key_exists( 'has_source_key_marker', $lookup ) && ! is_bool( $lookup['has_source_key_marker'] ) ) {
			return self::invalid_target_lookup( $duplicateTargets );
		}
		$hasMarker = array_key_exists( 'has_source_key_marker', $lookup ) ? $lookup['has_source_key_marker'] : false;

		$hasTypeMatchesKey = array_key_exists( 'target_type_matches', $lookup );
		if ( $hasTypeMatchesKey && ! is_bool( $lookup['target_type_matches'] ) ) {
			return self::invalid_target_lookup( $duplicateTargets );
		}

		// Faz 6B3 Önkoşul — natural_key: yalnız kapalı küme; yalnız
		// target_found=false VE duplicate_targets=false iken anlamlı.
		$naturalKey = null;
		if ( array_key_exists( 'natural_key', $lookup ) ) {
			if ( ! is_string( $lookup['natural_key'] ) || ! in_array( $lookup['natural_key'], self::NATURAL_KEY_STATES, true ) ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			if ( $targetFound || $duplicateTargets ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			$naturalKey = $lookup['natural_key'];
		}

		if ( ! $targetFound ) {
			// target_found=false iken "bulundu" anlamına gelecek HER
			// çelişkili alan (target_id, current_managed_fields, dolu
			// marker, dolu hash, false type-match) reddedilir.
			if ( $hasTypeMatchesKey && true !== $lookup['target_type_matches'] ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			if ( $hasMarker ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			if ( array_key_exists( 'last_applied_hash', $lookup ) && null !== $lookup['last_applied_hash'] && '' !== $lookup['last_applied_hash'] ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			$targetIdRaw = array_key_exists( 'target_id', $lookup ) ? $lookup['target_id'] : null;
			if ( null !== $targetIdRaw ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			if ( array_key_exists( 'current_managed_fields', $lookup ) && null !== $lookup['current_managed_fields'] ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}

			return array(
				'target_state_valid'     => true,
				'target_found'           => false,
				'target_id'              => null,
				'duplicate_targets'      => $duplicateTargets,
				'target_type_matches'    => true,
				'has_source_key_marker'  => false,
				'last_applied_hash'      => null,
				'current_managed_fields' => null,
				'natural_key'            => $naturalKey,
			);
		}

		// --- $targetFound === true ---

		// Adapter'ın hedefin GERÇEK türünü kontrol ettiğinin kanıtı: bu
		// bayrak sessizce `true` varsayılamaz, MUTLAKA açıkça verilmelidir.
		if ( ! $hasTypeMatchesKey ) {
			return self::invalid_target_lookup( $duplicateTargets );
		}
		$targetTypeMatches = $lookup['target_type_matches'];

		$targetIdRaw = array_key_exists( 'target_id', $lookup ) ? $lookup['target_id'] : null;
		if ( ! is_int( $targetIdRaw ) || $targetIdRaw <= 0 ) {
			return self::invalid_target_lookup( $duplicateTargets );
		}
		$targetId = $targetIdRaw;

		// Marker yokken dolu bir hash çelişkilidir (hash yalnız markerlı
		// bir kayıtta anlamlıdır) — bu, malformed-hash-to-null
		// normalizasyonundan ÖNCE, ham değer üzerinde kontrol edilir.
		$lastAppliedHashRaw = array_key_exists( 'last_applied_hash', $lookup ) ? $lookup['last_applied_hash'] : null;
		if ( ! $hasMarker && null !== $lastAppliedHashRaw && '' !== $lastAppliedHashRaw ) {
			return self::invalid_target_lookup( $duplicateTargets );
		}

		if ( null !== $lastAppliedHashRaw && ! MaviBelge_Core_Validator::is_valid_sha256_hash( $lastAppliedHashRaw ) ) {
			// Marker var + hash biçimsiz -> null'a düşür, karar motoru
			// bunu (marker var + hash yok) legacy_missing_hash olarak
			// sınıflandırır (create/update DEĞİL).
			$lastAppliedHash = null;
		} else {
			$lastAppliedHash = ( '' === $lastAppliedHashRaw ) ? null : $lastAppliedHashRaw;
		}

		$currentManagedFieldsRaw = array_key_exists( 'current_managed_fields', $lookup ) ? $lookup['current_managed_fields'] : null;
		$currentManagedFields    = null;

		if ( $targetTypeMatches ) {
			// Gerçek, türü uyuşan bir hedef bulunduysa mevcut yönetilen
			// alan durumu OKUNABİLİR olmak ZORUNDADIR — eksik/null artık
			// sessizce "karşılaştırma verisi yok" sayılmaz.
			if ( null === $currentManagedFieldsRaw || ! is_array( $currentManagedFieldsRaw ) ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			$expectedKeys = self::allowlist_for_type( $type );
			if ( null === $expectedKeys ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			$actualKeys = array_keys( $currentManagedFieldsRaw );
			sort( $expectedKeys );
			sort( $actualKeys );
			if ( $expectedKeys !== $actualKeys ) {
				// Eksik/fazla mevcut-alan anahtarı -> hash'e sokup
				// sahte manual-edit üretmek yerine kontrollü conflict.
				return self::invalid_target_lookup( $duplicateTargets );
			}
			if ( ! self::validate_current_field_values( $type, $currentManagedFieldsRaw ) ) {
				// Anahtar kümesi doğru ama en az bir alanın DEĞER tipi/
				// biçimi yanlış (ör. level="3" string, image_attachment_id
				// negatif) — hash'e sokup sahte manual-edit/update
				// üretmek yerine kontrollü conflict.
				return self::invalid_target_lookup( $duplicateTargets );
			}
			$currentManagedFields = $currentManagedFieldsRaw;
		} elseif ( null !== $currentManagedFieldsRaw ) {
			// Yanlış türde bir hedeften "yönetilen alan durumu" okumak
			// anlamsızdır — verilmişse yine de şekli/tipi doğrulanır (zarar
			// vermez), ama YOKLUĞU burada hataya sayılmaz.
			if ( ! is_array( $currentManagedFieldsRaw ) ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			$expectedKeys = self::allowlist_for_type( $type );
			$actualKeys   = array_keys( $currentManagedFieldsRaw );
			sort( $expectedKeys );
			sort( $actualKeys );
			if ( $expectedKeys !== $actualKeys || ! self::validate_current_field_values( $type, $currentManagedFieldsRaw ) ) {
				return self::invalid_target_lookup( $duplicateTargets );
			}
			$currentManagedFields = $currentManagedFieldsRaw;
		}

		return array(
			'target_state_valid'     => true,
			'target_found'           => true,
			'target_id'              => $targetId,
			'duplicate_targets'      => $duplicateTargets,
			'target_type_matches'    => $targetTypeMatches,
			'has_source_key_marker'  => $hasMarker,
			'last_applied_hash'      => $lastAppliedHash,
			'current_managed_fields' => $currentManagedFields,
			'natural_key'            => null,
		);
	}

	private static function invalid_target_lookup( $duplicateTargets = false ) {
		return array(
			'target_state_valid'     => false,
			'target_found'           => false,
			'target_id'              => null,
			'duplicate_targets'      => $duplicateTargets,
			'target_type_matches'    => true,
			'has_source_key_marker'  => false,
			'last_applied_hash'      => null,
			'current_managed_fields' => null,
			'natural_key'            => null,
		);
	}

	/**
	 * Faz 6B3 Önkoşul — yazma yükü doğrulayıcısının (class-import-write-payload.php)
	 * kullandığı TEK public giriş: yönetilen alan kümesi, ilgili türün
	 * allowlist'iyle TAM anahtar eşitliği taşıyor VE her değer, mevcut
	 * durum doğrulamasıyla AYNI tip/biçim kurallarını geçiyor mu. Kopya
	 * kural yok — iç validate_current_field_values() çağrılır.
	 *
	 * @param string $type
	 * @param mixed  $fields
	 * @return bool
	 */
	public static function is_valid_managed_field_set( $type, $fields ) {
		if ( ! is_array( $fields ) ) {
			return false;
		}
		$expected = self::allowlist_for_type( $type );
		if ( null === $expected ) {
			return false;
		}
		$actual = array_keys( $fields );
		sort( $expected );
		sort( $actual );
		if ( $expected !== $actual ) {
			return false;
		}
		return self::validate_current_field_values( $type, $fields );
	}

	/**
	 * Faz 6B3 Önkoşul — ücret doğal anahtarı için Faz 6A source_key
	 * formülünün slug kuralı (tek kaynak; doğal anahtar preflight'ı BUNU
	 * kullanır, kopya kural yok). Bkz. slugify_tr().
	 *
	 * @return string|null
	 */
	public static function profession_slug( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		return self::slugify_tr( $value );
	}

	private static function allowlist_for_type( $type ) {
		return MaviBelge_Core_Import_Managed_Fields::fields_for( $type );
	}

	/**
	 * Görev promptu §4/§6 — `current_managed_fields`'in yalnız anahtar
	 * KÜMESİ değil, HER alanın DEĞER tipi/biçimi de doğrulanır. Bir
	 * WordPress meta/term-meta alanı yanlış tipte saklanmışsa (ör.
	 * `level="3"` string, `image_attachment_id=-1`, bozuk `price_options`)
	 * bu artık sessizce hash'e girip sahte manual-edit/update
	 * ÜRETEMEZ — `false` döner, çağıran taraf kaydı `invalid_target_state`
	 * conflict'ine düşürür. Gerçek WordPress term/post enum'ları için bkz.
	 * `wordpress-site/docs/content-model.md` (`_mb_record_status` alanları).
	 *
	 * @return bool
	 */
	private static function validate_current_field_values( $type, array $fields ) {
		switch ( $type ) {
			case 'sector':
				return self::validate_current_sector_fields( $fields );
			case 'qualification':
				return self::validate_current_qualification_fields( $fields );
			case 'fee':
				return self::validate_current_fee_fields( $fields );
			case 'news':
				return self::validate_current_news_fields( $fields );
			case 'reference':
				return self::validate_current_reference_fields( $fields );
			case 'faq':
				return self::validate_current_faq_fields( $fields );
			case 'page':
				return self::validate_current_page_fields( $fields );
			default:
				return false;
		}
	}

	/**
	 * Faz 7 — haberin mevcut/yazılacak yönetilen alan değerleri. İçerik/özet
	 * yalnız string olmalıdır (editör HTML yazmış olabilir: bu geçersiz durum
	 * değil, hash uyuşmazlığıyla `conflict` olur); onay durumu şema
	 * sözlüğünden biri olabilir (editör onaylamış olabilir — yine conflict).
	 * Import'un YAZDIĞI değerler ayrıca MaviBelge_Core_Import_Write_Payload'da
	 * sabitlenir (in_review).
	 */
	private static function validate_current_news_fields( array $f ) {
		if ( ! is_string( $f['slug'] ) || 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $f['slug'] ) ) {
			return false;
		}
		if ( ! is_string( $f['title'] ) || '' === trim( $f['title'] ) ) {
			return false;
		}
		if ( ! is_string( $f['content'] ) || ! is_string( $f['excerpt'] ) ) {
			return false;
		}
		if ( ! is_string( $f['published_on'] ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}\z/', $f['published_on'] ) || ! MaviBelge_Core_Validator::is_valid_ymd_date( $f['published_on'] ) ) {
			return false;
		}
		if ( ! is_int( $f['news_type_term_id'] ) || $f['news_type_term_id'] <= 0 ) {
			return false;
		}
		return is_string( $f['approval_status'] ) && in_array( $f['approval_status'], MaviBelge_Core_Meta_Schema::APPROVAL_STATUS, true );
	}

	/**
	 * Faz 12 — sayfanın mevcut/yazılacak yönetilen alan değerleri. İçerik/özet yalnız string olmalıdır (editör HTML'i
	 * değiştirmiş olabilir: geçersiz durum DEĞİL, hash uyuşmazlığıyla `conflict` olur; İÇERİĞİN güvenli olma şartı yalnız
	 * import'un YAZDIĞI değer için validate_page()'te uygulanır). parent_id ve menu_order negatif olamaz.
	 */
	private static function validate_current_page_fields( array $f ) {
		if ( ! is_string( $f['slug'] ) || 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $f['slug'] ) ) {
			return false;
		}
		if ( ! is_string( $f['title'] ) || '' === trim( $f['title'] ) ) {
			return false;
		}
		if ( ! is_string( $f['content'] ) || ! is_string( $f['excerpt'] ) ) {
			return false;
		}
		return is_int( $f['parent_id'] ) && $f['parent_id'] >= 0 && is_int( $f['menu_order'] ) && $f['menu_order'] >= 0;
	}

	/**
	 * Faz 12b — referansın mevcut/yazılacak yönetilen alan değerleri. logo_sha256: bağlı attachment'ın GERÇEK dosya özeti
	 * (geçerli/okunabilir logo yoksa '' — boş özet hash'te sapma üretir ve kayıt planda conflict olur, sahte "unchanged" olmaz).
	 */
	private static function validate_current_reference_fields( array $f ) {
		if ( ! is_string( $f['slug'] ) || 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $f['slug'] ) ) {
			return false;
		}
		if ( ! is_string( $f['title'] ) || '' === trim( $f['title'] ) ) {
			return false;
		}
		if ( ! is_string( $f['reference_status'] ) || ! in_array( $f['reference_status'], MaviBelge_Core_Meta_Schema::REFERENCE_STATUS, true ) ) {
			return false;
		}
		if ( ! is_string( $f['record_status'] ) || ! in_array( $f['record_status'], MaviBelge_Core_Meta_Schema::RECORD_STATUS_ACTIVE_PASSIVE, true ) ) {
			return false;
		}
		if ( ! is_int( $f['sort_order'] ) || $f['sort_order'] < 0 ) {
			return false;
		}
		if ( ! is_string( $f['website_url'] ) ) {
			return false;
		}
		return is_string( $f['logo_sha256'] ) && ( '' === $f['logo_sha256'] || 1 === preg_match( '/^[0-9a-f]{64}\z/', $f['logo_sha256'] ) );
	}

	/** Faz 12b — SSS'in mevcut/yazılacak yönetilen alan değerleri (editör cevabı değiştirmiş olabilir: hash uyuşmazlığıyla conflict). */
	private static function validate_current_faq_fields( array $f ) {
		if ( ! is_string( $f['slug'] ) || 1 !== preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*\z/', $f['slug'] ) ) {
			return false;
		}
		if ( ! is_string( $f['title'] ) || '' === trim( $f['title'] ) || ! is_string( $f['content'] ) ) {
			return false;
		}
		if ( ! is_int( $f['sort_order'] ) || $f['sort_order'] < 0 ) {
			return false;
		}
		return is_string( $f['record_status'] ) && in_array( $f['record_status'], MaviBelge_Core_Meta_Schema::RECORD_STATUS_ACTIVE_PASSIVE, true );
	}

	private static function validate_current_sector_fields( array $f ) {
		if ( ! is_string( $f['slug'] ) || '' === $f['slug'] || ! MaviBelge_Core_Validator::is_valid_slug_format( $f['slug'] ) ) {
			return false;
		}
		if ( ! is_string( $f['name'] ) || '' === trim( $f['name'] ) ) {
			return false;
		}
		if ( ! is_string( $f['description'] ) ) {
			return false;
		}
		if ( ! is_string( $f['icon_key'] ) ) {
			return false;
		}
		if ( ! is_int( $f['image_attachment_id'] ) || $f['image_attachment_id'] < 0 ) {
			return false;
		}
		return true;
	}

	private static function validate_current_qualification_fields( array $f ) {
		if ( ! is_string( $f['title'] ) || '' === trim( $f['title'] ) ) {
			return false;
		}
		if ( ! is_string( $f['myk_code'] ) || ! MaviBelge_Core_Validator::is_valid_myk_code_format( $f['myk_code'] ) ) {
			return false;
		}
		if ( ! is_int( $f['level'] ) || $f['level'] < 1 || $f['level'] > 8 ) {
			return false;
		}
		if ( ! is_string( $f['revision'] ) || ! preg_match( '/^([0-9]{2})?$/', $f['revision'] ) ) {
			return false;
		}
		if ( ! is_string( $f['record_status'] ) || ! in_array( $f['record_status'], array( 'active', 'passive' ), true ) ) {
			return false;
		}
		if ( ! is_int( $f['sector_term_id'] ) || $f['sector_term_id'] <= 0 ) {
			return false;
		}
		return true;
	}

	private static function validate_current_fee_fields( array $f ) {
		if ( ! is_string( $f['title'] ) || '' === trim( $f['title'] ) ) {
			return false;
		}
		if ( ! is_string( $f['profession_name'] ) || '' === trim( $f['profession_name'] ) ) {
			return false;
		}
		// Çapraz-alan (Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri §2.4):
		// projeksiyon `title`'ı her zaman `profession_name`'den türetir
		// (bkz. class-import-managed-fields.php::project_fee()) — ikisi
		// AYNI kaydın MEVCUT durumunda birbirinden SAPAMAZ.
		if ( $f['title'] !== $f['profession_name'] ) {
			return false;
		}
		if ( ! is_int( $f['level'] ) || $f['level'] < 1 || $f['level'] > 8 ) {
			return false;
		}
		if ( ! is_string( $f['sector_slug'] ) || '' === $f['sector_slug'] || ! MaviBelge_Core_Validator::is_valid_slug_format( $f['sector_slug'] ) ) {
			return false;
		}
		if ( ! is_int( $f['qualification_post_id'] ) || $f['qualification_post_id'] < 0 ) {
			return false;
		}
		if ( ! is_string( $f['qualification_code'] ) || ! MaviBelge_Core_Validator::is_valid_myk_code_format( $f['qualification_code'] ) ) {
			return false;
		}
		// Çapraz-alan: kod boş <=> post ID KESİNLİKLE 0; kod dolu <=> post
		// ID KESİNLİKLE pozitif (Faz 6A'nın 19 kodsuz ücret kararı, bkz.
		// class-import-managed-fields.php::project_fee(), MEVCUT durumda
		// da aynen geçerli).
		if ( '' === $f['qualification_code'] ) {
			if ( 0 !== $f['qualification_post_id'] ) {
				return false;
			}
		} elseif ( $f['qualification_post_id'] <= 0 ) {
			return false;
		}
		if ( ! is_string( $f['pricing_type'] ) || ! in_array( $f['pricing_type'], array( 'single', 'unit', 'package', 'multiple' ), true ) ) {
			return false;
		}
		if ( ! self::validate_current_price_options_shape( $f['price_options'] ) ) {
			return false;
		}
		if ( ! is_bool( $f['vat_included'] ) ) {
			return false;
		}
		if ( ! is_int( $f['certificate_print_fee_kurus'] ) || $f['certificate_print_fee_kurus'] < 0 ) {
			return false;
		}
		if ( ! is_string( $f['source_name'] ) ) {
			return false;
		}
		if ( ! is_int( $f['source_page'] ) || $f['source_page'] < 0 ) {
			return false;
		}
		if ( ! is_int( $f['source_attachment_id'] ) || $f['source_attachment_id'] < 0 ) {
			return false;
		}
		if ( ! is_string( $f['tariff_period'] ) || '' === trim( $f['tariff_period'] ) ) {
			return false;
		}
		if ( ! is_string( $f['record_status'] ) || ! in_array( $f['record_status'], array( 'draft', 'active', 'archived' ), true ) ) {
			return false;
		}
		if ( ! is_string( $f['valid_from'] ) || ! MaviBelge_Core_Validator::is_valid_ymd_date( $f['valid_from'] ) ) {
			return false;
		}
		if ( ! is_string( $f['valid_until'] ) || ! MaviBelge_Core_Validator::is_valid_ymd_date( $f['valid_until'] ) ) {
			return false;
		}
		// Çapraz-alan: ikisi de doluysa ters aralık (valid_from > valid_until)
		// reddedilir — `Y-m-d` sabit genişlikli biçim olduğundan string
		// karşılaştırması kronolojik sırayla BİREBİR örtüşür.
		if ( '' !== $f['valid_from'] && '' !== $f['valid_until'] && $f['valid_from'] > $f['valid_until'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Sözleşme Eşitleme §2.3 — `current_managed_fields['price_options']`
	 * için KANONİK, KATI eşitlik kontrolü.
	 *
	 * ÖNCEKİ TASARIM (Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri) bilinçli
	 * olarak kanonik `evaluate_price_options()`'ı KULLANMIYORDU — kendi
	 * elle yazılmış şekil/sınır kontrolünü uyguluyordu ("o fonksiyon
	 * label/units metnini temizler, bozuk mevcut durumu sessizce
	 * düzeltilmiş gösterebilir" gerekçesiyle). Bağımsız incelemenin
	 * bulduğu üzere bu YANLIŞ yöndeydi: iki BAĞIMSIZ, ZAMANLA AYRIŞACAK
	 * doğrulama kopyası (biri admin kaydı/yayın kapısı için kanonik, biri
	 * yalnız burası için elle yazılmış) tam olarak `label='<b>X</b>'` veya
	 * temizlenmesi gereken bir unit gibi durumların BURADAN geçip
	 * kanonik yoldan REDDEDİLMESİNE (veya sessizce farklı bir şekilde
	 * kabul edilmesine) yol açıyordu.
	 *
	 * YENİ TASARIM: current listesi doğrudan KANONİK
	 * `MaviBelge_Core_Validator::evaluate_price_options()`'tan geçirilir
	 * VE kanonik çıktı (`options`) ham current listeyle KATI PHP `===`
	 * eşitliğinden geçmek ZORUNDADIR. `evaluate_price_options()` HERHANGİ
	 * bir temizleme/cast/atlama uyguladıysa (HTML etiketi temizleme, baş/
	 * son boşluk kırpma, boş unit atlama, `sort_order`'a göre yeniden
	 * sıralama, bilinmeyen bir satır alanını atma) sonuç dizisi orijinal
	 * current listeyle ARTIK BİREBİR AYNI OLMAZ — bu durumda current
	 * state `invalid_target_state` sayılır (bkz. görev promptu §2.3: "current
	 * fiyat listesi kanonik doğrulayıcı ile katı biçimde karşılaştırılır;
	 * temizleme/cast gerekiyorsa current state geçersizdir").
	 *
	 * Bu, sınır/sıra/şekil kurallarının İKİNCİ, zamanla ayrışacak bir
	 * kopyasını burada TUTMAZ — TEK kaynak `class-validator.php`'deki
	 * kanonik yoldur (görev promptu §2.3 son madde).
	 */
	private static function validate_current_price_options_shape( $priceOptions ) {
		if ( ! is_array( $priceOptions ) || ! self::is_list_array( $priceOptions ) ) {
			return false;
		}
		$canonical = MaviBelge_Core_Validator::evaluate_price_options( $priceOptions );
		if ( ! $canonical['replace'] ) {
			// En az bir satır kanonik doğrulayıcı için geçersiz (atomik ret) —
			// mevcut WordPress durumu ZATEN bozuk sayılır.
			return false;
		}
		if ( empty( $canonical['options'] ) ) {
			return false;
		}
		// KATI, kanonikleştirmeden ÖNCEKİ ham listeyle birebir eşitlik.
		// `evaluate_price_options()`'ın ürettiği her satır TAM OLARAK
		// {label, units, amount_kurus, sort_order} anahtar sırasıyla
		// döner (bkz. class-validator.php normalize_price_options()) —
		// current listede fazla/eksik bir anahtar, farklı bir tip
		// (ör. sayısal string tutar), temizlenmemiş metin veya
		// kanonik-olmayan sıra varsa bu eşitlik SAPAR.
		return $canonical['options'] === $priceOptions;
	}

	/* ------------------------------------------------------------------ *
	 * Küçük, tekrar kullanılan alan-şekli yardımcıları.
	 * ------------------------------------------------------------------ */

	private static function require_string( array $record, $key, array &$errors, $minLength ) {
		if ( ! array_key_exists( $key, $record ) || ! is_string( $record[ $key ] ) ) {
			$errors[] = "{$key} eksik veya string değil.";
			return;
		}
		if ( $minLength > 0 && '' === trim( $record[ $key ] ) ) {
			$errors[] = "{$key} boş bırakılamaz.";
		}
	}

	private static function require_string_or_null( array $record, $key, array &$errors ) {
		if ( ! array_key_exists( $key, $record ) ) {
			$errors[] = "{$key} eksik.";
			return;
		}
		if ( null !== $record[ $key ] && ! is_string( $record[ $key ] ) ) {
			$errors[] = "{$key} null veya string olmalı.";
		}
	}

	private static function require_int( array $record, $key, array &$errors ) {
		if ( ! array_key_exists( $key, $record ) || ! is_int( $record[ $key ] ) ) {
			$errors[] = "{$key} eksik veya tam sayı değil.";
		}
	}

	private static function require_int_or_null( array $record, $key, array &$errors ) {
		if ( ! array_key_exists( $key, $record ) ) {
			$errors[] = "{$key} eksik.";
			return;
		}
		if ( null !== $record[ $key ] && ! is_int( $record[ $key ] ) ) {
			$errors[] = "{$key} null veya tam sayı olmalı.";
		}
	}

	private static function require_bool( array $record, $key, array &$errors ) {
		if ( ! array_key_exists( $key, $record ) || ! is_bool( $record[ $key ] ) ) {
			$errors[] = "{$key} eksik veya bool değil.";
		}
	}

	private static function require_array( array $record, $key, array &$errors ) {
		if ( ! array_key_exists( $key, $record ) || ! is_array( $record[ $key ] ) ) {
			$errors[] = "{$key} eksik veya dizi değil.";
		}
	}

	/** @return string[] $record'daki $allowedKeys DIŞINDA kalan anahtarlar. */
	private static function extra_keys( array $record, array $allowedKeys ) {
		return array_values( array_diff( array_keys( $record ), $allowedKeys ) );
	}

	/**
	 * `source: {file, sha256}` zarfı — Faz 6A şemasının TÜM içerik
	 * türlerinde ortak, sabit şekli. `file` boş olmayan, mutlak olmayan
	 * (baştan `/` ile başlamayan) ve `..` dizin geçişi İÇERMEYEN bir
	 * repo-relative yol olmalı; `sha256` tam 64 küçük-harf hex karakter.
	 */
	private static function require_source_object( array $record, array &$errors ) {
		if ( ! array_key_exists( 'source', $record ) || ! is_array( $record['source'] ) ) {
			$errors[] = 'source eksik veya dizi değil.';
			return;
		}
		$source = $record['source'];
		$extra  = self::extra_keys( $source, array( 'file', 'sha256' ) );
		if ( ! empty( $extra ) ) {
			$errors[] = 'source içinde beklenmeyen alan(lar): ' . implode( ', ', $extra ) . '.';
		}
		if ( ! array_key_exists( 'file', $source ) || ! is_string( $source['file'] ) || '' === trim( $source['file'] ) ) {
			$errors[] = 'source.file eksik, string değil veya boş.';
		} else {
			$file = $source['file'];
			if ( 0 === strpos( $file, '/' ) || 1 === preg_match( '#^[A-Za-z]:[\\\\/]#', $file ) ) {
				$errors[] = 'source.file mutlak bir yol olamaz.';
			}
			if ( false !== strpos( $file, '..' ) ) {
				$errors[] = 'source.file dizin geçişi ("..") içeremez.';
			}
		}
		if ( ! array_key_exists( 'sha256', $source ) || ! is_string( $source['sha256'] ) || ! preg_match( '/^[0-9a-f]{64}$/', $source['sha256'] ) ) {
			$errors[] = 'source.sha256 tam 64 küçük-harf hex karakter olmalı.';
		}
	}

	/**
	 * Faz 6A'nın `tools/import/lib/slug.js::slugify()` ile BİREBİR AYNI,
	 * deterministik Türkçe-duyarlı slugify — yalnız ücretin `source_key`
	 * formülünü (profession_name → slug) doğrulamak için kullanılır,
	 * fuzzy eşleştirme için DEĞİL. Adımlar (slug.js ile bire bir):
	 * 1) Unicode kod noktası bazında ayrıştır (JS'in `split('')`'ine denk —
	 *    bu karakter kümesi için BMP tek code-unit'lik karakterlerden
	 *    oluştuğundan `Array.from()`/kod-noktası ayrımıyla eşdeğerdir);
	 * 2) TURKISH_MAP ile katlama (ç/Ç→c, ğ/Ğ→g, ı/I/İ/i→i, ö/Ö→o, ş/Ş→s, ü/Ü→u);
	 * 3) küçük harfe çevir; 4) `[^a-z0-9]+` dizilerini TEK `-` ile değiştir
	 *    (haritalanmayan diğer Unicode karakterler — ör. "â" — burada
	 *    a-z0-9 dışı sayılıp `-`'ye döner, slug.js ile AYNI davranış);
	 * 5) baştaki/sondaki `-` karakterlerini kırp.
	 *
	 * @return string|null Geçersiz UTF-8 girdide null (fail-closed).
	 */
	private static function slugify_tr( $value ) {
		$chars = preg_split( '//u', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
		if ( false === $chars ) {
			return null;
		}
		$map = array(
			'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i', 'İ' => 'i', 'i' => 'i',
			'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
		);
		$folded = '';
		foreach ( $chars as $ch ) {
			$folded .= array_key_exists( $ch, $map ) ? $map[ $ch ] : $ch;
		}
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $folded, 'UTF-8' ) : strtolower( $folded );
		$slug  = preg_replace( '/[^a-z0-9]+/u', '-', $lower );
		if ( null === $slug ) {
			return null;
		}
		return trim( $slug, '-' );
	}

	/** mbstring-safe uzunluk, yoksa byte uzunluğuna düşer (bkz. class-validator.php'deki eşdeğeri). */
	private static function str_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/** PHP 7.3-safe liste tespiti (bkz. class-import-hash.php'deki aynı kural). */
	private static function is_list_array( array $arr ) {
		$expected = 0;
		foreach ( $arr as $key => $unused_value ) {
			if ( $key !== $expected ) {
				return false;
			}
			++$expected;
		}
		return true;
	}

	private static function ok() {
		return array( 'valid' => true, 'errors' => array() );
	}

	private static function fail( array $errors ) {
		return array( 'valid' => false, 'errors' => $errors );
	}
}
