<?php
/**
 * Central meta field contract for every mavibelge-core content type.
 *
 * This is the single source of truth consumed by:
 * - register_post_meta() registration (self::register_all())
 * - the generic admin meta box renderer (admin/class-meta-boxes.php)
 * - the save handler's sanitize/validate pass
 *
 * Field "type" values map to a sanitizer/validator pair implemented in
 * class-field-repository.php and class-validator.php: text, textarea,
 * url, date, datetime, integer, select, checkbox, id, user_id,
 * money_kurus, string_list, id_list, phone_list, price_options.
 * (Faz 6B3 Önkoşul: eski 'money_try' tipi kaldırıldı — bkz. money_kurus.)
 *
 * Documented, tracked field: this data model adds "multiple" as a
 * fourth allowed _mb_pricing_type value alongside the three named in
 * the task brief (single/unit/package). Two of the 103 real fee
 * records in tanitim-site/assets/data/fees.js use pricingType:
 * "multiple"; omitting it would make the schema lossy for those rows,
 * which the brief's own "kayıpsız taşıyabilir" acceptance criterion
 * forbids. See docs/content-model.md §Ücret kaydı.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Meta_Schema {

	const RECORD_STATUS_ACTIVE_PASSIVE = array( 'active', 'passive' );
	const PRICING_TYPES                = array( 'single', 'unit', 'package', 'multiple' );
	const UCRET_RECORD_STATUS          = array( 'draft', 'active', 'archived' );
	const APPROVAL_STATUS              = array( 'draft', 'in_review', 'approved', 'rejected' );
	const REFERENCE_STATUS             = array( 'real', 'representative' );

	/**
	 * @return array<string, array<string, array>> post_type => field_key => field config
	 */
	public static function get_schema() {
		return array(
			'mb_yeterlilik' => self::yeterlilik_fields(),
			'mb_ucret'      => self::ucret_fields(),
			'mb_haber'      => self::haber_fields(),
			'mb_dokuman'    => self::dokuman_fields(),
			'mb_referans'   => self::referans_fields(),
			'mb_lokasyon'   => self::lokasyon_fields(),
			'mb_sss'        => self::sss_fields(),
		);
	}

	private static function yeterlilik_fields() {
		return array(
			'_mb_myk_code'     => array(
				'label'       => 'MYK Kodu',
				'type'        => 'text',
				'format'      => 'myk_code',
				'description' => 'Örn. 10UY0002-3/03. Boş bırakılabilir.',
			),
			'_mb_level'        => array(
				'label'   => 'Seviye',
				'type'    => 'select',
				'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8' ),
				'default' => '',
			),
			'_mb_revision'     => array(
				'label'       => 'Revizyon',
				'type'        => 'text',
				'description' => 'Koddan bağımsız açık alan, boş olabilir.',
			),
			'_mb_record_status' => array(
				'label'   => 'Kayıt Durumu',
				'type'    => 'select',
				'options' => array(
					'active'  => 'Aktif',
					'passive' => 'Pasif',
				),
				'default' => 'active',
			),
			'_mb_sort_order'   => array(
				'label'   => 'Sıralama',
				'type'    => 'integer',
				'min'     => 0,
				'default' => 0,
			),
			// Faz 6B idempotency hazırlığı (Faz 6A Güvenlik/Şema/Sözleşme
			// Kapanışı item 12) — SİSTEM YÖNETİMLİ, salt-okunur, admin
			// UI'da düzenlenemez, REST'e kapalı. Bu fazda hiçbir kayda
			// değer YAZILMAZ; yalnız şema tanımı eklendi. Üç-hash çakışma
			// çözümü (last_applied_hash/current_managed_hash/incoming_hash)
			// tasarımı için bkz.
			// raporlar/veri-aktarim-raporlari/faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md.
			'_mb_import_source_key' => array(
				'label'          => 'İçe Aktarım Kaynak Anahtarı (sistem)',
				'type'           => 'text',
				'format'         => 'import_source_key_qualification',
				'readonly'       => true,
				'system_managed' => true,
			),
			'_mb_last_applied_hash'  => array(
				'label'          => 'Son Uygulanan Veri Özeti (sistem)',
				'type'           => 'text',
				'format'         => 'sha256_hash',
				'readonly'       => true,
				'system_managed' => true,
			),
		);
	}

	private static function ucret_fields() {
		return array(
			'_mb_qualification_id'   => array(
				'label'       => 'Bağlı Yeterlilik (ID)',
				'type'        => 'id',
				'ref_post_type' => 'mb_yeterlilik',
				'description' => 'Eşleşme yoksa 0 bırakılabilir.',
				'default'     => 0,
			),
			'_mb_qualification_code' => array(
				'label'       => 'Kaynak MYK Kodu (denetim kopyası)',
				'type'        => 'text',
				'format'      => 'myk_code',
				'description' => 'Boş olabilir; uydurulmaz.',
			),
			'_mb_profession_name'    => array(
				'label' => 'Meslek Adı',
				'type'  => 'text',
			),
			'_mb_level'              => array(
				'label'   => 'Seviye',
				'type'    => 'select',
				'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8' ),
			),
			'_mb_sector_slug'        => array(
				'label'       => 'Sektör Slug (import eşlemesi)',
				'type'        => 'text',
				'format'      => 'slug',
				'description' => 'Küçük harf, tire ile ayrılmış normalize slug.',
			),
			'_mb_tariff_period'      => array(
				'label'       => 'Tarife Dönemi',
				'type'        => 'text',
				'description' => 'Örn. 2026.',
			),
			'_mb_valid_from'         => array(
				'label' => 'Geçerlilik Başlangıcı',
				'type'  => 'date',
			),
			'_mb_valid_until'        => array(
				'label' => 'Geçerlilik Bitişi',
				'type'  => 'date',
			),
			'_mb_record_status'      => array(
				'label'   => 'Kayıt Durumu',
				'type'    => 'select',
				'options' => array(
					'draft'    => 'Taslak',
					'active'   => 'Aktif',
					'archived' => 'Arşivlendi',
				),
				'default' => 'draft',
				// Only a user who can publish ücret records may move
				// this to active/archived; enforced server-side in
				// admin/class-meta-boxes.php::guard_ucret_record_status()
				// and reflected here only so the UI disables the
				// options it cannot actually apply (no misleading
				// "active" control shown as if it would work).
				'restricted_option_caps' => array(
					'active'   => 'publish_mb_ucretler',
					'archived' => 'publish_mb_ucretler',
				),
			),
			'_mb_pricing_type'       => array(
				'label'   => 'Fiyatlandırma Türü',
				'type'    => 'select',
				'options' => array(
					'single'   => 'Tek fiyat',
					'unit'     => 'Birim bazlı',
					'package'  => 'Paket',
					'multiple' => 'Çok seçenekli',
				),
			),
			'_mb_vat_included'       => array(
				'label'   => 'KDV Dahil',
				'type'    => 'checkbox',
				'default' => true,
			),
			// Faz 6B3 Önkoşul — kök neden: bu alan eskiden 'money_try' idi;
			// kayıtlı sanitize callback'i HER update_post_meta çağrısında
			// değeri TL sayıp kuruşa çeviriyordu. Admin döngüsü ve
			// write_meta() zaten kuruşa çevrilmiş değeri yazdığı için değer
			// 100 ile ikinci kez çarpılıyordu (1.500 TL -> 15000000 kuruş).
			// Artık saklanan temsil kanonik integer kuruştur ('money_kurus');
			// TL girişi YALNIZ admin form katmanında ('admin_input' => 'try')
			// kuruşa çevrilir. Kayıtlı sanitize kanonik değerde idempotenttir.
			'_mb_certificate_print_fee_kurus' => array(
				'label'       => 'Belge Basım Ücreti (TL)',
				'type'        => 'money_kurus',
				'admin_input' => 'try',
			),
			'_mb_source_name'        => array(
				'label' => 'Kaynak Tarife Adı',
				'type'  => 'text',
			),
			'_mb_source_page'        => array(
				'label' => 'Kaynak Sayfa No',
				'type'  => 'integer',
				'min'   => 0,
			),
			'_mb_source_attachment_id' => array(
				'label' => 'Kaynak Dosya (Medya ID)',
				'type'  => 'id',
				'ref_post_type' => 'attachment',
				'default' => 0,
			),
			'_mb_price_options'      => array(
				'label' => 'Fiyat Seçenekleri',
				'type'  => 'price_options',
			),
			// Derived, read-only — computed server-side from
			// _mb_price_options. Stored as kuruş (see class-validator.php),
			// but displayed as TL text (system_managed — see
			// admin/class-meta-boxes.php::format_system_managed_value()),
			// never as a raw kuruş integer in the admin screen.
			'_mb_min_amount_kurus'   => array(
				'label'          => 'Min. Tutar (TL, türetilmiş)',
				'type'           => 'money_kurus',
				'min'            => 0,
				'readonly'       => true,
				'system_managed' => true,
			),
			'_mb_max_amount_kurus'   => array(
				'label'          => 'Maks. Tutar (TL, türetilmiş)',
				'type'           => 'money_kurus',
				'min'            => 0,
				'readonly'       => true,
				'system_managed' => true,
			),
			// Faz 6B idempotency hazırlığı — bkz. aynı notun yeterlilik
			// alanları için yukarıdaki açıklaması.
			'_mb_import_source_key'  => array(
				'label'          => 'İçe Aktarım Kaynak Anahtarı (sistem)',
				'type'           => 'text',
				'format'         => 'import_source_key_fee',
				'readonly'       => true,
				'system_managed' => true,
			),
			'_mb_last_applied_hash'  => array(
				'label'          => 'Son Uygulanan Veri Özeti (sistem)',
				'type'           => 'text',
				'format'         => 'sha256_hash',
				'readonly'       => true,
				'system_managed' => true,
			),
		);
	}

	private static function haber_fields() {
		return array(
			'_mb_content_updated_at' => array(
				'label' => 'İçerik Güncelleme Tarihi',
				'type'  => 'datetime',
			),
			// readonly => never editable via the generic per-field save
			// loop (same mechanism already used for the derived ücret
			// min/max fields); system_managed => rendered as plain,
			// human-readable display text instead of an editable ID/date
			// box. Both fields are written ONLY by
			// admin/class-meta-boxes.php::guard_haber_approval_status()
			// at the moment an authorized approve/reject transition
			// happens — never from raw POST, never freely editable.
			'_mb_reviewer_user_id'   => array(
				'label'          => 'İnceleyen Kullanıcı',
				'type'           => 'user_id',
				'default'        => 0,
				'readonly'       => true,
				'system_managed' => true,
			),
			'_mb_reviewed_at'        => array(
				'label'          => 'İnceleme Tarihi',
				'type'           => 'datetime',
				'readonly'       => true,
				'system_managed' => true,
			),
			'_mb_approval_status'    => array(
				'label'   => 'Onay Durumu',
				'type'    => 'select',
				'options' => array(
					'draft'     => 'Taslak',
					'in_review' => 'İncelemede',
					'approved'  => 'Onaylandı',
					'rejected'  => 'Reddedildi',
				),
				'default' => 'draft',
				// See _mb_record_status on mb_ucret above for the same pattern.
				'restricted_option_caps' => array(
					'approved' => 'publish_mb_haberler',
					'rejected' => 'publish_mb_haberler',
				),
			),
			// Faz 7 içerik aktarımı — SİSTEM YÖNETİMLİ, salt-okunur (yeterlilik/ücret ile AYNI
			// sözleşme): yalnız `wp mavibelge import catalog --stage=content` yazar.
			'_mb_import_source_key'  => array(
				'label'          => 'İçe Aktarım Kaynak Anahtarı (sistem)',
				'type'           => 'text',
				'format'         => 'import_source_key_news',
				'readonly'       => true,
				'system_managed' => true,
			),
			'_mb_last_applied_hash'  => array(
				'label'          => 'Son Uygulanan Veri Özeti (sistem)',
				'type'           => 'text',
				'format'         => 'sha256_hash',
				'readonly'       => true,
				'system_managed' => true,
			),
		);
	}

	private static function dokuman_fields() {
		return array(
			'_mb_attachment_id'             => array(
				'label'         => 'Doküman Dosyası (Medya ID)',
				'type'          => 'id',
				'ref_post_type' => 'attachment',
				'default'       => 0,
			),
			'_mb_document_version'          => array(
				'label' => 'Sürüm',
				'type'  => 'text',
			),
			'_mb_publish_date'              => array(
				'label' => 'Yayın Tarihi',
				'type'  => 'date',
			),
			'_mb_valid_until'               => array(
				'label' => 'Geçerlilik Bitişi',
				'type'  => 'date',
			),
			'_mb_record_status'             => array(
				'label'   => 'Kayıt Durumu',
				'type'    => 'select',
				'options' => array(
					'active'  => 'Aktif',
					'passive' => 'Pasif',
				),
				'default' => 'active',
			),
			'_mb_related_qualification_ids' => array(
				'label'         => 'İlgili Yeterlilikler (ID listesi)',
				'type'          => 'id_list',
				'ref_post_type' => 'mb_yeterlilik',
			),
		);
	}

	private static function referans_fields() {
		return array(
			'_mb_logo_attachment_id' => array(
				'label'         => 'Logo (Medya ID)',
				'type'          => 'id',
				'ref_post_type' => 'attachment',
				'default'       => 0,
			),
			'_mb_website_url'        => array(
				'label' => 'Web Sitesi',
				'type'  => 'url',
			),
			'_mb_sort_order'         => array(
				'label' => 'Sıralama',
				'type'  => 'integer',
				'min'   => 0,
				'default' => 0,
			),
			'_mb_reference_status'   => array(
				'label'   => 'Referans Türü',
				'type'    => 'select',
				'options' => array(
					'real'           => 'Gerçek',
					'representative' => 'Temsili',
				),
				'default' => 'representative',
			),
			'_mb_record_status'      => array(
				'label'   => 'Kayıt Durumu',
				'type'    => 'select',
				'options' => array(
					'active'  => 'Aktif',
					'passive' => 'Pasif',
				),
				'default' => 'active',
			),
			// Faz 7 içerik aktarımı — SİSTEM YÖNETİMLİ, salt-okunur (yeterlilik/ücret ile AYNI
			// sözleşme): yalnız `wp mavibelge import catalog --stage=content` yazar.
			'_mb_import_source_key'  => array(
				'label'          => 'İçe Aktarım Kaynak Anahtarı (sistem)',
				'type'           => 'text',
				'format'         => 'import_source_key_reference',
				'readonly'       => true,
				'system_managed' => true,
			),
			'_mb_last_applied_hash'  => array(
				'label'          => 'Son Uygulanan Veri Özeti (sistem)',
				'type'           => 'text',
				'format'         => 'sha256_hash',
				'readonly'       => true,
				'system_managed' => true,
			),
		);
	}

	private static function lokasyon_fields() {
		return array(
			'_mb_address'        => array(
				'label' => 'Adres',
				'type'  => 'textarea',
			),
			'_mb_phone_numbers'  => array(
				'label' => 'Telefon Numaraları',
				'type'  => 'phone_list',
			),
			'_mb_map_url'        => array(
				'label' => 'Harita Bağlantısı',
				'type'  => 'url',
			),
			'_mb_working_hours'  => array(
				'label' => 'Çalışma Saatleri',
				'type'  => 'textarea',
				'maxlength' => 500,
			),
			'_mb_sort_order'     => array(
				'label' => 'Sıralama',
				'type'  => 'integer',
				'min'   => 0,
				'default' => 0,
			),
			'_mb_record_status'  => array(
				'label'   => 'Kayıt Durumu',
				'type'    => 'select',
				'options' => array(
					'active'  => 'Aktif',
					'passive' => 'Pasif',
				),
				'default' => 'active',
			),
		);
	}

	private static function sss_fields() {
		return array(
			'_mb_sort_order'                => array(
				'label' => 'Sıralama',
				'type'  => 'integer',
				'min'   => 0,
				'default' => 0,
			),
			'_mb_record_status'             => array(
				'label'   => 'Kayıt Durumu',
				'type'    => 'select',
				'options' => array(
					'active'  => 'Aktif',
					'passive' => 'Pasif',
				),
				'default' => 'active',
			),
			'_mb_related_page_ids'          => array(
				'label'         => 'İlgili Sayfalar (ID listesi)',
				'type'          => 'id_list',
				'ref_post_type' => 'page',
			),
			'_mb_related_qualification_ids' => array(
				'label'         => 'İlgili Yeterlilikler (ID listesi)',
				'type'          => 'id_list',
				'ref_post_type' => 'mb_yeterlilik',
			),
		);
	}

	/**
	 * Registers every field with register_post_meta so values are
	 * validated at the WordPress API layer too, not only in the admin
	 * save handler. show_in_rest is false everywhere: this phase keeps
	 * the REST write surface closed (see AGENTS.md / görev kartı 02 §2.8).
	 */
	public static function register_all() {
		// Faz 6B3 Önkoşul — sanitize callback'inin ret işaretini uygulayan
		// filtreler, meta kaydıyla AYNI yerde bağlanır (biri olmadan diğeri olmaz).
		self::register_reject_filters();
		foreach ( self::get_schema() as $post_type => $fields ) {
			foreach ( $fields as $meta_key => $config ) {
				$meta_type = self::meta_type_for( $config['type'] );
				register_post_meta(
					$post_type,
					$meta_key,
					array(
						'type'              => $meta_type,
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => array( 'MaviBelge_Core_Meta_Schema', 'sanitize_for_registration' ),
						'auth_callback'     => array( 'MaviBelge_Core_Meta_Schema', 'auth_callback' ),
					)
				);
			}
		}
	}

	private static function meta_type_for( $field_type ) {
		switch ( $field_type ) {
			case 'integer':
			case 'id':
			case 'user_id':
			case 'money_kurus':
				return 'integer';
			case 'checkbox':
				return 'boolean';
			case 'id_list':
			case 'string_list':
			case 'phone_list':
			case 'price_options':
				return 'array';
			default:
				return 'string';
		}
	}

	/**
	 * Field-aware register_post_meta() sanitizer — a safety net for
	 * programmatic writes that go straight through update_post_meta()
	 * rather than through the admin save handler (which runs the same
	 * MaviBelge_Core_Field_Repository::sanitize_and_validate() with a
	 * chance to keep the previous value and show a notice on failure).
	 *
	 * REAL WordPress callback signature (verified against
	 * wp-includes/meta.php — sanitize_meta() and register_meta()):
	 * register_post_meta( $post_type, $key, $args ) internally calls
	 * register_meta( 'post', $key, array_merge( $args, array(
	 * 'object_subtype' => $post_type ) ) ), which hooks the
	 * subtype-specific filter tag
	 * "sanitize_{$object_type}_meta_{$meta_key}_for_{$object_subtype}".
	 * sanitize_meta() then calls that filter with FOUR arguments:
	 * apply_filters( $tag, $meta_value, $meta_key, $object_type,
	 * $object_subtype ). $object_type here is the generic type — the
	 * literal string "post" — NOT the specific CPT; the CPT only ever
	 * arrives as the FOURTH argument, $object_subtype.
	 *
	 * A prior version of this method declared only three parameters
	 * ($meta_value, $meta_key, $object_subtype), so PHP silently never
	 * delivered the real 4th argument, and what that third parameter
	 * actually received on every call was the *literal string "post"*
	 * — not the post type. self::get_fields_for('post') is always
	 * empty, so the field-aware branch below was dead code: every
	 * field, for every post type, fell through the "unknown meta key"
	 * branch and got only the generic is_string()+sanitize_text_field()
	 * treatment — arrays (price_options, id_list, phone_list,
	 * string_list) passed through completely untouched.
	 *
	 * Faz 6B3 Önkoşul — ESKİ SINIR KAPATILDI: bu callback eskiden geçersiz
	 * girişi alan tipinin "güvenli varsayılanına" ('' / 0 / boş dizi)
	 * çeviriyordu; bu, geçerli bir mevcut değerin sessizce EZİLMESİNE yol
	 * açıyordu (PHP 7.3 + WordPress 6.9.9 runtime'ında kanıtlandı: "5"
	 * kayıtlı iken update_post_meta(..., "9") -> ''). register_meta()
	 * sanitize callback'i yazmayı kendi başına reddedemez; bu yüzden şema
	 * alanı için geçersiz girişte self::REJECTED_META_WRITE işareti
	 * döndürülür ve reject_marked_meta_write() filtresi
	 * (update_post_metadata / add_post_metadata, öncelik 1) bu işareti
	 * görünce yazmayı REDDEDER: hiçbir şey yazılmaz, önceki değer korunur.
	 * Pass/fail geri bildirimi isteyen çağıranlar yine
	 * MaviBelge_Core_Field_Repository::write_meta() kullanmalıdır.
	 * Şemada tanımlı OLMAYAN anahtarlar/alt türler için genel temizleme
	 * davranışı değişmedi.
	 */
	public static function sanitize_for_registration( $meta_value, $meta_key, $object_type, $object_subtype = '' ) {
		// Defensive: this callback is only ever registered for post
		// meta (register_post_meta() always sets object_type='post').
		// If WordPress ever calls it for a different object type —
		// or object_subtype is missing/blank, which means the schema
		// can't be resolved at all — do NOT return the raw value
		// unsanitized; fall back to the generic scalar-only cleaning
		// instead of trusting an unrecognized shape.
		if ( 'post' !== $object_type || '' === $object_subtype ) {
			return is_string( $meta_value ) ? sanitize_text_field( $meta_value ) : self::generic_safe_fallback( $meta_value );
		}

		$fields = self::get_fields_for( $object_subtype );
		if ( ! isset( $fields[ $meta_key ] ) ) {
			return is_string( $meta_value ) ? sanitize_text_field( $meta_value ) : self::generic_safe_fallback( $meta_value );
		}
		$config = $fields[ $meta_key ];

		if ( 'price_options' === $config['type'] ) {
			if ( ! is_array( $meta_value ) ) {
				return self::REJECTED_META_WRITE;
			}
			$result = MaviBelge_Core_Validator::normalize_price_options( $meta_value );
			if ( ! empty( $result['errors'] ) ) {
				// This callback has no way to REJECT the write (see the
				// class docblock above) and no way to fall back to the
				// previous stored value either — but it must NOT save a
				// PARTIAL list. Returning $result['options'] here (a prior
				// version did) would let one valid row survive alongside
				// a silently dropped invalid one, which is exactly the
				// "kısmi liste kaydı" the Faz2 kapanış brief §2 forbids.
				// The type-stable safe fallback is an empty array — the
				// same "nothing kept" outcome sanitize_for_registration()
				// already applies to every other invalid field below.
				// Faz 6B3 Önkoşul: artık boş listeye DE düşmez — yazma
				// reddedilir, önceki geçerli liste korunur.
				return self::REJECTED_META_WRITE;
			}
			return $result['options'];
		}

		list( $clean, $error ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $config, $meta_value );
		if ( null !== $error ) {
			return self::REJECTED_META_WRITE;
		}
		return $clean;
	}

	/**
	 * Faz 6B3 Önkoşul — sanitize callback'lerinin "bu yazmayı reddet"
	 * işareti. Geçerli bir girdi hiçbir alan tipinde bu değeri (NUL
	 * baytları içeren iç string) üretemez; hiçbir zaman saklanmaz, çünkü
	 * reject_marked_meta_write() filtresi onu görünce yazmayı kısa devre
	 * eder. Term-meta sanitize callback'leri (class-taxonomies.php) AYNI
	 * işareti kullanır.
	 */
	const REJECTED_META_WRITE = "\0mavibelge-core:rejected-meta-write\0";

	/**
	 * update_{post,term}_metadata / add_{post,term}_metadata filtresi:
	 * sanitize sonucu REJECTED_META_WRITE ise yazmayı reddeder (false döner,
	 * WordPress hiçbir satır yazmaz/değiştirmez); aksi hâlde zinciri
	 * olduğu gibi bırakır.
	 *
	 * @param null|bool $check
	 * @param int       $object_id
	 * @param string    $meta_key
	 * @param mixed     $meta_value sanitize_meta() SONRASI değer.
	 * @return null|bool
	 */
	public static function reject_marked_meta_write( $check, $object_id, $meta_key, $meta_value ) {
		if ( is_string( $meta_value ) && self::REJECTED_META_WRITE === $meta_value ) {
			return false;
		}
		return $check;
	}

	/** Ret filtrelerini (post + term, update + add) bir kez bağlar; register_all() ve term-meta kaydı çağırır. */
	public static function register_reject_filters() {
		foreach ( array( 'update_post_metadata', 'add_post_metadata', 'update_term_metadata', 'add_term_metadata' ) as $hook ) {
			if ( false === has_filter( $hook, array( __CLASS__, 'reject_marked_meta_write' ) ) ) {
				add_filter( $hook, array( __CLASS__, 'reject_marked_meta_write' ), 1, 4 );
			}
		}
		// Faz 6B3 Önkoşul Son Kabul Düzeltmesi — update_metadata_by_mid() yolu.
		$byMid = array(
			'update_post_metadata_by_mid' => 'reject_invalid_post_meta_write_by_mid',
			'update_term_metadata_by_mid' => 'reject_invalid_term_meta_write_by_mid',
		);
		foreach ( $byMid as $hook => $method ) {
			if ( false === has_filter( $hook, array( __CLASS__, $method ) ) ) {
				add_filter( $hook, array( __CLASS__, $method ), 1, 4 );
			}
		}
	}

	/** update_post_metadata_by_mid filtresi — bkz. reject_invalid_meta_write_by_mid(). */
	public static function reject_invalid_post_meta_write_by_mid( $check, $meta_id, $meta_value, $meta_key ) {
		return self::reject_invalid_meta_write_by_mid( 'post', $check, $meta_id, $meta_value, $meta_key );
	}

	/** update_term_metadata_by_mid filtresi — bkz. reject_invalid_meta_write_by_mid(). */
	public static function reject_invalid_term_meta_write_by_mid( $check, $meta_id, $meta_value, $meta_key ) {
		return self::reject_invalid_meta_write_by_mid( 'term', $check, $meta_id, $meta_value, $meta_key );
	}

	/**
	 * Faz 6B3 Önkoşul Son Kabul Düzeltmesi — kök neden (WordPress 6.9.9
	 * wp-includes/meta.php kaynağından doğrulandı): update_metadata_by_mid()
	 * önce "update_{$meta_type}_metadata_by_mid" kısa-devre filtresini
	 * çalıştırır, SONRA sanitize_meta() çağırıp doğrudan $wpdb->update
	 * yapar; sanitize SONRASI bir filtre yoktur. Bu yüzden
	 * reject_marked_meta_write() bu yola hiç ulaşmıyor ve geçersiz girişte
	 * REJECTED_META_WRITE işareti veritabanına YAZILIYORDU (Codex runtime
	 * kanıtı: _mb_level "5" üzerine by-mid "9" -> true + işaret saklandı).
	 *
	 * Bu kısa-devre filtresi, çekirdeğin birazdan yapacağı değerlendirmeyi
	 * YALNIZ MaviBelge'nin kayıtlı ve yönettiği meta alanları için ÖNCEDEN
	 * yapar: meta kaydını ID ile SALT OKUNUR çözer, gerçek meta anahtarını
	 * (false -> mevcut anahtar, açık string -> o anahtar) ve nesnenin gerçek
	 * alt türünü (post_type / taxonomy) bulur, ham yeni değeri AYNI
	 * sanitize_meta() zinciriyle değerlendirir. Sonuç ret işaretiyse
	 * güncellemeyi false ile kısa devre eder (hiçbir şey yazılmaz, önceki
	 * değer korunur); geçerli değerde null döner ve çekirdeğin normal yolu
	 * devam eder. sanitize_meta() by-mid filtresini tetiklemez — özyineleme
	 * yoktur. Başka bir eklenti zaten karar verdiyse ($check !== null) ona
	 * dokunmaz.
	 *
	 * By-Mid Kapsam Kapanışı — kök neden (Codex runtime kanıtı:
	 * {"result":true,"sanitize_calls":2}): filtre global olduğu hâlde
	 * anahtarın MaviBelge'ye ait olup olmadığına bakmadan sanitize_meta()
	 * çağırıyordu; geçerli değerde çekirdek sanitize_meta()'yı tekrar
	 * çağırdığı için BAŞKA eklentilerin sanitizer'ları iki kez çalışıyordu.
	 * Artık kapsam iki adımda denetlenir ve kapsam dışı her durumda null
	 * döner (ön sanitizasyon yok, get_{type}_metadata_by_mid okuma filtresi
	 * de tetiklenmez; davranış tamamen WordPress'e ve ilgili eklentiye kalır):
	 *  1. Aday anahtar (alt türden bağımsız): post için get_schema()'daki
	 *     herhangi bir alan, term için mb_sektor term-meta sözleşmesi
	 *     (MaviBelge_Core_Taxonomies::sector_term_meta_contract()). $meta_key
	 *     false ise anahtar, filtre tetiklemeyen salt okunur tek satırlık
	 *     SELECT ile okunur.
	 *  2. Kesin kapsam: gerçek alt tür çözüldükten sonra
	 *     is_managed_meta_key() — post: get_fields_for($subtype); term:
	 *     yalnız mb_sektor + sözleşmedeki dört anahtar.
	 * MaviBelge adayı olduğu belirlendikten sonra kayıt/nesne/alt tür
	 * çözülemezse fail-closed false. Meta ID bulunamazsa veya anahtar string
	 * değilse false (çekirdek de bu durumlarda false döner).
	 *
	 * @return null|bool
	 */
	private static function reject_invalid_meta_write_by_mid( $meta_type, $check, $meta_id, $meta_value, $meta_key ) {
		if ( null !== $check ) {
			return $check;
		}
		if ( false === $meta_key ) {
			$key = self::read_meta_key_by_mid( $meta_type, $meta_id );
			if ( null === $key ) {
				return false;
			}
		} elseif ( is_string( $meta_key ) ) {
			$key = $meta_key;
		} else {
			return false;
		}
		if ( ! self::is_mavibelge_meta_key_candidate( $meta_type, $key ) ) {
			return null; // MaviBelge dışı: ön sanitizasyon YOK, karar çekirdeğe/ilgili eklentiye.
		}
		$meta   = get_metadata_by_mid( $meta_type, $meta_id );
		$column = 'post' === $meta_type ? 'post_id' : 'term_id';
		if ( ! is_object( $meta ) || ! isset( $meta->meta_key, $meta->{$column} ) || ! is_string( $meta->meta_key ) ) {
			return false;
		}
		if ( false === $meta_key && $meta->meta_key !== $key ) {
			return false; // Okunan anahtar ile çekirdeğin göreceği kayıt tutarsız: fail-closed.
		}
		$rawObjectId = $meta->{$column};
		$parsedId    = is_int( $rawObjectId ) ? array( 'ok' => true, 'value' => $rawObjectId ) : MaviBelge_Core_Validator::parse_canonical_decimal_int( $rawObjectId );
		if ( ! $parsedId['ok'] || $parsedId['value'] <= 0 ) {
			return false;
		}
		$objectId = $parsedId['value'];
		$subtype  = get_object_subtype( $meta_type, $objectId );
		if ( ! is_string( $subtype ) || '' === $subtype ) {
			return false;
		}
		if ( ! self::is_managed_meta_key( $meta_type, $subtype, $key ) ) {
			return null; // Aynı adlı anahtar ama MaviBelge dışı alt tür (ör. 'post' yazısında _mb_level).
		}
		$sanitized = sanitize_meta( $key, $meta_value, $meta_type, $subtype );
		if ( is_string( $sanitized ) && self::REJECTED_META_WRITE === $sanitized ) {
			return false;
		}
		return null;
	}

	/**
	 * By-mid yolunda $meta_key false iken anahtarı okur: tek satırlık,
	 * SALT OKUNUR SELECT; hiçbir filtre/sanitizer tetiklenmez. Bulunamazsa null.
	 */
	private static function read_meta_key_by_mid( $meta_type, $meta_id ) {
		global $wpdb;
		$table = _get_meta_table( $meta_type );
		if ( ! $table || ! is_int( $meta_id ) || $meta_id <= 0 ) {
			return null;
		}
		$key = $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM {$table} WHERE meta_id = %d", $meta_id ) );
		return is_string( $key ) ? $key : null;
	}

	/**
	 * Alt türden BAĞIMSIZ aday denetimi (saf): anahtar herhangi bir MaviBelge
	 * post türünün şemasında (post) veya mb_sektor term-meta sözleşmesinde
	 * (term) tanımlı mı? false ise anahtar kesinlikle MaviBelge alanı değildir.
	 */
	public static function is_mavibelge_meta_key_candidate( $meta_type, $meta_key ) {
		if ( ! is_string( $meta_key ) || '' === $meta_key ) {
			return false;
		}
		if ( 'post' === $meta_type ) {
			foreach ( self::get_schema() as $fields ) {
				if ( array_key_exists( $meta_key, $fields ) ) {
					return true;
				}
			}
			return false;
		}
		if ( 'term' === $meta_type ) {
			return class_exists( 'MaviBelge_Core_Taxonomies' ) && MaviBelge_Core_Taxonomies::is_sector_term_meta_key( $meta_key );
		}
		return false;
	}

	/**
	 * Kesin kapsam (saf): post -> anahtar get_fields_for($subtype) içinde;
	 * term -> taksonomi tam mb_sektor VE anahtar sektör term-meta
	 * sözleşmesinde. Diğer meta türleri, taksonomiler ve anahtarlar
	 * MaviBelge kapsamı değildir.
	 */
	public static function is_managed_meta_key( $meta_type, $subtype, $meta_key ) {
		if ( ! is_string( $subtype ) || '' === $subtype || ! is_string( $meta_key ) || '' === $meta_key ) {
			return false;
		}
		if ( 'post' === $meta_type ) {
			return array_key_exists( $meta_key, self::get_fields_for( $subtype ) );
		}
		if ( 'term' === $meta_type ) {
			return class_exists( 'MaviBelge_Core_Taxonomies' ) && MaviBelge_Core_Taxonomies::is_managed_sector_term_meta( $subtype, $meta_key );
		}
		return false;
	}

	/**
	 * For a non-string value reaching an unresolvable field (unknown
	 * meta key, unknown/wrong object type or subtype): never pass an
	 * array/object straight through unsanitized. An empty array is a
	 * safe, type-stable fallback for the array-shaped field types this
	 * schema actually has (price_options/id_list/phone_list/string_list);
	 * anything else scalar-but-not-a-string (int/float/bool/null) is
	 * left as-is since it isn't the array-smuggling shape this guards
	 * against.
	 */
	private static function generic_safe_fallback( $meta_value ) {
		if ( is_array( $meta_value ) || is_object( $meta_value ) ) {
			return array();
		}
		return $meta_value;
	}

	private static function safe_default_for_type( array $config ) {
		switch ( $config['type'] ) {
			case 'checkbox':
				return false;
			case 'integer':
			case 'id':
			case 'user_id':
			case 'money_kurus':
				return isset( $config['default'] ) ? $config['default'] : 0;
			case 'id_list':
			case 'string_list':
			case 'phone_list':
			case 'price_options':
				// price_options never actually reaches this switch today
				// (sanitize_for_registration() returns early for that
				// type, above) — listed explicitly anyway so this stays
				// type-stable if that branch's shape ever changes.
				return array();
			default:
				return isset( $config['default'] ) ? $config['default'] : '';
		}
	}

	/**
	 * register_post_meta()'s 'auth_callback' — gates WordPress's own
	 * generic meta-capability checks (e.g. the REST API's meta
	 * controller, or a plugin calling current_user_can('edit_post_meta',
	 * ...)). The documented signature is ($allowed, $meta_key, $post_id,
	 * $user_id, $cap, $caps) — unlike sanitize_callback, WordPress does
	 * NOT append an object_subtype argument here; the post type is
	 * derived below via get_post_type( $post_id ) instead.
	 *
	 * REAL LIMIT OF THIS GUARD: this is not, and cannot be, a wall
	 * against this plugin's OWN PHP code. update_post_meta() and
	 * get_post_meta() are low-level WordPress functions that never
	 * consult auth_callback at all — that check only happens on paths
	 * that go through WordPress's meta-capability system (REST, or code
	 * that explicitly calls current_user_can() for a meta capability).
	 *
	 * Two DIFFERENT things in this plugin are easy to conflate — they
	 * are not the same guarantee:
	 * - admin/class-meta-boxes.php's save() and its guard_*() methods
	 *   (guard_ucret_record_status(), guard_haber_approval_status())
	 *   DO call current_user_can() themselves, directly, every time —
	 *   real authorization enforcement.
	 * - MaviBelge_Core_Field_Repository::write_meta() does NOT call
	 *   current_user_can() anywhere in its own code. It is a data
	 *   validation + controlled-write API, not an authorization layer
	 *   (this is stated on write_meta()'s own docblock). It must only
	 *   ever be called from trusted internal/import code that has
	 *   already checked the caller's capability for the relevant CPT —
	 *   this callback provides no protection for it.
	 */
	public static function auth_callback( $allowed, $meta_key, $post_id, $user_id, $cap, $caps ) {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return false;
		}

		$fields = self::get_fields_for( $post_type );
		if ( ! isset( $fields[ $meta_key ] ) ) {
			return false; // Unrecognized field for this post type — deny by default.
		}

		$config = $fields[ $meta_key ];
		if ( ! empty( $config['readonly'] ) || ! empty( $config['system_managed'] ) ) {
			// Never directly writable through the generic WordPress meta
			// API — only through the controlled server-side flows above.
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	public static function get_fields_for( $post_type ) {
		$schema = self::get_schema();
		return isset( $schema[ $post_type ] ) ? $schema[ $post_type ] : array();
	}
}
