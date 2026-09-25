<?php
/**
 * Registers the four mavibelge-core taxonomies.
 *
 * No terms are seeded in this phase — the sector list (14 terms, per
 * the user's Faz 2 decision, see docs/content-model.md §Sektör) is
 * created by the validated, idempotent import in Faz 6, not here.
 *
 * mb_haber_turu is a controlled vocabulary: only two terms are ever
 * meant to exist ("haber" and "duyuru"), carrying the haber/duyuru
 * split already present in tanitim-site/assets/data/news.js. This is
 * enforced on BOTH paths:
 * - creation: the 'pre_insert_term' filter (wp-includes/taxonomy.php,
 *   wp_insert_term()). Its real signature is
 *   apply_filters( 'pre_insert_term', $term, $taxonomy, $args ) where
 *   $term is the term NAME STRING being inserted (not an array) —
 *   returning a WP_Error here is explicitly checked by wp_insert_term()
 *   and aborts the insert.
 * - update: the 'wp_update_term_data' filter (wp-includes/taxonomy.php,
 *   wp_update_term(), added in WP 4.9.8 — present in the 6.9.x target
 *   family), signature
 *   apply_filters( 'wp_update_term_data', $data, $term_id, $taxonomy, $args ).
 *   wp_update_term() has no WP_Error-abort filter, so renaming/re-slugging
 *   is blocked by coercing $data back to the term's existing name/slug
 *   for the two controlled terms rather than by rejecting the request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Taxonomies {

	const HABER_TURU_ALLOWED_SLUGS = array( 'haber', 'duyuru' );

	/** mb_sektor term-meta sözleşmesinin sahibi olan taksonomi (bkz. sector_term_meta_contract()). */
	const SECTOR_TAXONOMY = 'mb_sektor';

	/**
	 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı Bulgu #2 kapatıldı: mb_sektor
	 * artık gerçek WordPress term-meta hedeflerine sahip
	 * (_mb_icon_key/_mb_image_attachment_id), tanitim-site/assets/data/
	 * sectors.js'in icon/image alanlarını kayıpsız taşıyabilecek şekilde.
	 * BURADA HİÇBİR MEDYA/TERİM İÇE AKTARILMAZ — yalnız ŞEMA/register_term_meta
	 * eklendi, WordPress veritabanına hiçbir yazma yapılmadı (Faz 6B'nin
	 * konusu). Sınır: en fazla 50 karakter, sanitize_key() ile
	 * küçük-harf/rakam/tire/alt-çizgiye indirgenir; bunun ötesinde bir
	 * ikon kütüphanesi anahtar listesi henüz sabitlenmediği için (front-end
	 * ikon seti bu fazın kapsamı dışında) sıkı bir enum yerine biçim
	 * kısıtı uygulanır — sanitize_key() zaten yalnız [a-z0-9_-] üretir.
	 */
	const MAX_SECTOR_ICON_KEY_LENGTH = 50;

	/**
	 * Canonical, controlled Turkish display names for the two allowed
	 * mb_haber_turu terms. Applied on creation (restrict_haber_turu_terms)
	 * so the visible label is consistent regardless of what a user typed.
	 */
	const HABER_TURU_LABELS = array(
		'haber'  => 'Haber',
		'duyuru' => 'Duyuru',
	);

	public static function register_all() {
		register_taxonomy(
			'mb_sektor',
			array( 'mb_yeterlilik' ),
			array(
				'labels'            => self::labels( 'Sektör', 'Sektörler' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'rewrite'           => array( 'slug' => 'sektor', 'with_front' => false ),
				'query_var'         => true,
				'capabilities'      => self::term_capabilities( 'edit_mb_yeterlilikler' ),
			)
		);

		self::register_sector_term_meta();

		register_taxonomy(
			'mb_haber_turu',
			array( 'mb_haber' ),
			array(
				'labels'            => self::labels( 'Haber Türü', 'Haber Türleri' ),
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'rewrite'           => array( 'slug' => 'haber-turu', 'with_front' => false ),
				'query_var'         => true,
				'capabilities'      => self::term_capabilities( 'edit_mb_haberler' ),
				// Editors choose among existing terms; only an
				// administrator-level intervention (filtered below)
				// could ever add a third term.
				'meta_box_cb'       => 'post_categories_meta_box',
			)
		);

		register_taxonomy(
			'mb_dokuman_kategori',
			array( 'mb_dokuman' ),
			array(
				'labels'            => self::labels( 'Doküman Kategorisi', 'Doküman Kategorileri' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'rewrite'           => array( 'slug' => 'dokuman-kategori', 'with_front' => false ),
				'query_var'         => true,
				'capabilities'      => self::term_capabilities( 'edit_mb_dokumanlar' ),
			)
		);

		register_taxonomy(
			'mb_sss_kategori',
			array( 'mb_sss' ),
			array(
				'labels'            => self::labels( 'SSS Kategorisi', 'SSS Kategorileri' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'rewrite'           => array( 'slug' => 'sss-kategori', 'with_front' => false ),
				'query_var'         => true,
				'capabilities'      => self::term_capabilities( 'edit_mb_sss_kayitlari' ),
			)
		);
	}

	/**
	 * Faz 6A Güvenlik/Şema/Sözleşme Kapanışı Bulgu #2 kapatıldı + Faz 6B
	 * idempotency ön-hazırlığı (item 12) — register_term_meta() ile 4 yeni
	 * mb_sektor term-meta alanı: ikisi normal-editilebilir içerik alanı
	 * (icon/image), ikisi salt-okunur/sistem-yönetimli import kimliği
	 * (source_key/last_applied_hash). show_in_rest=false her ikisinde de
	 * — bu fazda REST yazma yüzeyi kapalı kalıyor (AGENTS.md / görev
	 * kartı 02 §2.8 ile aynı kural). Hiçbir terim burada oluşturulmaz.
	 */
	private static function register_sector_term_meta() {
		// Faz 6B3 Önkoşul — aşağıdaki sanitize callback'leri geçersiz girişte
		// MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE döndürür; bu
		// filtreler yazmayı reddeder (önceki geçerli değer ASLA '' / 0 ile
		// ezilmez). Meta kaydıyla aynı yerde bağlanır.
		MaviBelge_Core_Meta_Schema::register_reject_filters();
		// By-Mid Kapsam Kapanışı: kayıt ve by-mid kapsam denetimi
		// (is_managed_sector_term_meta()) AYNI tek sözleşmeyi kullanır.
		foreach ( self::sector_term_meta_contract() as $meta_key => $args ) {
			register_term_meta( self::SECTOR_TAXONOMY, $meta_key, $args );
		}
	}

	/**
	 * mb_sektor term-meta sözleşmesinin TEK kaynağı: meta anahtarı =>
	 * register_term_meta() argümanları. Saf (WordPress fonksiyonu
	 * çağırmaz). register_sector_term_meta() bu listeyi kaydeder;
	 * MaviBelge_Core_Meta_Schema by-mid kapsam denetimi aynı listeye bakar.
	 *
	 * @return array<string, array>
	 */
	public static function sector_term_meta_contract() {
		return array(
			'_mb_icon_key'            => array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_sector_icon_key' ),
				'auth_callback'     => array( __CLASS__, 'term_meta_auth_callback' ),
			),
			'_mb_image_attachment_id' => array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_sector_image_attachment_id' ),
				'auth_callback'     => array( __CLASS__, 'term_meta_auth_callback' ),
			),
			// Faz 6B hazırlığı — SİSTEM YÖNETİMLİ, salt-okunur, admin UI'da
			// düzenlenemez, REST'e kapalı. Üç-hash çakışma çözümü tasarımı
			// için bkz.
			// raporlar/veri-aktarim-raporlari/faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md.
			'_mb_import_source_key'   => array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_sector_import_source_key' ),
				'auth_callback'     => '__return_false', // System-managed: never writable through the generic meta API surface.
			),
			'_mb_last_applied_hash'   => array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_sha256_hex' ),
				'auth_callback'     => '__return_false',
			),
		);
	}

	/** Anahtar, mb_sektor term-meta sözleşmesinde tanımlı mı (taksonomiden bağımsız)? Saf. */
	public static function is_sector_term_meta_key( $meta_key ) {
		return is_string( $meta_key ) && '' !== $meta_key && array_key_exists( $meta_key, self::sector_term_meta_contract() );
	}

	/** Taksonomi TAM mb_sektor VE anahtar sözleşmede tanımlıysa true; başka taksonomi/anahtar MaviBelge kapsamı değildir. Saf. */
	public static function is_managed_sector_term_meta( $taxonomy, $meta_key ) {
		return is_string( $taxonomy ) && self::SECTOR_TAXONOMY === $taxonomy && self::is_sector_term_meta_key( $meta_key );
	}

	/**
	 * Faz 6B3 Önkoşul: string olmayan (dizi/nesne) girdi "Array" metnine
	 * CAST EDİLMEZ; boş string alanı temizler; geçersiz/aşırı uzun değer
	 * yazmayı REDDEDER (önceki değer korunur). sanitize_key() çıktıyı zaten
	 * [a-z0-9_-] ile sınırlar.
	 */
	public static function sanitize_sector_icon_key( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}
		if ( ! is_string( $value ) ) {
			return MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE;
		}
		$key = sanitize_key( $value );
		if ( '' === $key || strlen( $key ) > self::MAX_SECTOR_ICON_KEY_LENGTH ) {
			return MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE;
		}
		return $key;
	}

	/**
	 * Fail-closed: a positive value is kept ONLY if it is a genuinely
	 * digit-only string/int (no "123abc" cast trick) AND resolves to a
	 * real 'attachment' post. Anything else (including a positive ID
	 * pointing at a non-attachment post, or a not-yet-uploaded ID) comes
	 * back as 0 — "unmapped" — never a dangling/fabricated reference. No
	 * media is imported or created by this sanitizer.
	 */
	public static function sanitize_sector_image_attachment_id( $value ) {
		// Faz 6B3 Önkoşul: yalnız açık "görsel yok" temsili (0 / "0" / "" /
		// null) 0 olarak yazılır. Pozitif ama attachment olmayan/biçimsiz
		// değer artık sessizce 0'a (mevcut görselin silinmesine) DÜŞMEZ —
		// yazma reddedilir, önceki değer korunur.
		if ( null === $value || '' === $value || 0 === $value || '0' === $value ) {
			return 0;
		}
		if ( ! MaviBelge_Core_Validator::is_valid_positive_integer_string( $value ) ) {
			return MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE;
		}
		$id = (int) ( is_string( $value ) ? trim( $value ) : $value );
		if ( ! MaviBelge_Core_Validator::post_exists_of_type( $id, 'attachment' ) ) {
			return MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE;
		}
		return $id;
	}

	/**
	 * Faz 6A Son Kabul Düzeltmesi §7.2 — yalnız `sanitize_text_field()`
	 * artık YETERLİ SAYILMIYOR: `sector:<geçerli-slug>` biçimini uygular,
	 * tek paylaşılan `MaviBelge_Core_Validator::is_valid_import_source_key()`
	 * kuralı üzerinden (post-meta tarafındaki `_mb_import_source_key`
	 * alanlarıyla AYNI mantık, kopya regex yok).
	 */
	public static function sanitize_sector_import_source_key( $value ) {
		// Faz 6B3 Önkoşul: yanlış önek / biçimsiz / dizi-nesne marker ASLA
		// sessizce '' yapılmaz — yazma reddedilir. Kural tek kanonik
		// MaviBelge_Core_Validator::classify_import_source_key()'dir.
		$clean = is_string( $value ) ? trim( $value ) : $value;
		$class = MaviBelge_Core_Validator::classify_import_source_key( $clean, 'sector' );
		if ( MaviBelge_Core_Validator::IMPORT_KEY_EMPTY === $class ) {
			return '';
		}
		return MaviBelge_Core_Validator::IMPORT_KEY_VALID === $class ? $clean : MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE;
	}

	/** Exactly 64 lowercase hex characters (a SHA-256 digest) or empty; delegates to the ONE shared rule (post-meta `_mb_last_applied_hash` uses the same). */
	public static function sanitize_sha256_hex( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}
		if ( ! is_string( $value ) ) {
			return MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE; // Faz 6B3 Önkoşul: cast yok, ret.
		}
		$clean = trim( $value );
		return MaviBelge_Core_Validator::is_valid_sha256_hash( $clean ) ? $clean : MaviBelge_Core_Meta_Schema::REJECTED_META_WRITE;
	}

	public static function term_meta_auth_callback() {
		return current_user_can( 'mb_manage_taxonomies' );
	}

	/**
	 * Term creation/edit/delete is reserved to a dedicated capability
	 * (granted only to administrator and mb_site_manager, see
	 * roles/class-roles.php); assigning existing terms to a post is
	 * allowed to whoever can edit that post type.
	 */
	private static function term_capabilities( $assign_cap ) {
		return array(
			'manage_terms' => 'mb_manage_taxonomies',
			'edit_terms'   => 'mb_manage_taxonomies',
			'delete_terms' => 'mb_manage_taxonomies',
			'assign_terms' => $assign_cap,
		);
	}

	/**
	 * Faz 7: iki KONTROLLÜ haber türü terimini (haber, duyuru) idempotent oluşturur (sabit sözlük; kişisel veri yok).
	 * Sürüm seçeneği yalnız iki terim de doğrulandıktan sonra yazılır; hata sessizdir (bir sonraki admin_init yeniden dener).
	 */
	public static function ensure_haber_turu_terms() {
		if ( '1' === get_option( 'mavibelge_core_haber_turu_seeded' ) || ! taxonomy_exists( 'mb_haber_turu' ) ) {
			return;
		}
		foreach ( self::HABER_TURU_ALLOWED_SLUGS as $slug ) {
			if ( ! get_term_by( 'slug', $slug, 'mb_haber_turu' ) ) {
				wp_insert_term( self::HABER_TURU_LABELS[ $slug ], 'mb_haber_turu', array( 'slug' => $slug ) );
			}
		}
		foreach ( self::HABER_TURU_ALLOWED_SLUGS as $slug ) {
			if ( ! get_term_by( 'slug', $slug, 'mb_haber_turu' ) ) {
				return;
			}
		}
		update_option( 'mavibelge_core_haber_turu_seeded', '1', false );
	}

	/**
	 * Blocks creation of any mb_haber_turu term other than the two
	 * controlled slugs; canonicalizes the Turkish label for those two.
	 *
	 * $term is always the term NAME STRING here (wp_insert_term()'s
	 * first argument), never an array — see the class docblock for the
	 * real 'pre_insert_term' signature this relies on.
	 */
	public static function restrict_haber_turu_terms( $term, $taxonomy, $args = array() ) {
		if ( 'mb_haber_turu' !== $taxonomy ) {
			return $term;
		}

		$slug_candidate = ! empty( $args['slug'] ) ? sanitize_title( $args['slug'] ) : sanitize_title( $term );

		if ( in_array( $slug_candidate, self::HABER_TURU_ALLOWED_SLUGS, true ) ) {
			return isset( self::HABER_TURU_LABELS[ $slug_candidate ] ) ? self::HABER_TURU_LABELS[ $slug_candidate ] : $term;
		}

		return new WP_Error(
			'mavibelge_core_haber_turu_locked',
			sprintf( '"%s" haber türü olarak eklenemez. Yalnız "Haber" ve "Duyuru" terimleri kullanılabilir.', $term )
		);
	}

	/**
	 * Update-path guard: once a controlled term (slug "haber" or
	 * "duyuru") exists, its name/slug can no longer be changed via
	 * wp_update_term(). Other taxonomy data (e.g. description) is left
	 * alone. A term_id that isn't one of the two controlled terms is
	 * untouched by this function — normal hierarchical taxonomies never
	 * reach mb_haber_turu anyway since $taxonomy is checked first.
	 */
	public static function lock_haber_turu_term_data( $data, $term_id, $taxonomy, $args ) {
		if ( 'mb_haber_turu' !== $taxonomy ) {
			return $data;
		}

		$existing = get_term( $term_id, $taxonomy );
		if ( ! $existing || is_wp_error( $existing ) ) {
			return $data;
		}

		if ( in_array( $existing->slug, self::HABER_TURU_ALLOWED_SLUGS, true ) ) {
			$data['slug'] = $existing->slug;
			$data['name'] = isset( self::HABER_TURU_LABELS[ $existing->slug ] ) ? self::HABER_TURU_LABELS[ $existing->slug ] : $existing->name;
		}

		return $data;
	}

	private static function labels( $singular, $plural ) {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			'search_items'  => $singular . ' Ara',
			'all_items'     => 'Tüm ' . $plural,
			'edit_item'     => $singular . ' Düzenle',
			'update_item'   => $singular . ' Güncelle',
			'add_new_item'  => 'Yeni ' . $singular . ' Ekle',
			'new_item_name' => 'Yeni ' . $singular . ' Adı',
			'not_found'     => $singular . ' bulunamadı.',
		);
	}
}
