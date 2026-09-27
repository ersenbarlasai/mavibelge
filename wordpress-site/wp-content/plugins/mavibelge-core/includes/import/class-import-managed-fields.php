<?php
/**
 * Faz 6B1 — managed-field allowlists + manifest-record → managed-field
 * projection, one per content type (sector/qualification/fee).
 *
 * "Managed" means: this importer OWNS these fields and will overwrite
 * them on `update`. Anything NOT listed here (post_content, SEO fields,
 * an editor's free-text notes, ...) is explicitly OUT of scope — it is
 * never read, hashed, or written by this importer, so it can never
 * trigger a `conflict` and is never at risk of being silently overwritten.
 *
 * `_mb_import_source_key` and `_mb_last_applied_hash` themselves are
 * ALWAYS excluded from every projection (bkz. class-import-hash.php'nin
 * bağlayıcı kararı aşağıda tekrar belgelenmiştir):
 *
 * BAĞLAYICI KARAR — `_mb_import_source_key` hash girdisine GİRMEZ: bu
 * alan bir kaydın hash'lenecek İÇERİĞİ değil, o kaydı BULMAK için
 * kullanılan bir eşleştirme anahtarıdır (source_key zaten hedefi bulmak
 * için ayrıca kullanılıyor — kendini hash'e dahil etmek dairesel/
 * gereksiz olur ve source_key hiç değişmediği için hash'e hiçbir ayırt
 * edici bilgi katmaz). `_mb_last_applied_hash` kendi hash girdisine asla
 * giremez (dairesel olurdu) — bu ikisi TÜM tiplerde (sektör/yeterlilik/
 * ücret) ve TÜM kod yollarında (mevcut durum hash'i VE incoming hash)
 * AYNI şekilde, projeksiyon fonksiyonlarının hiçbirine hiç eklenmeyerek
 * uygulanır.
 *
 * Bu dosya HİÇBİR WordPress fonksiyonu çağırmaz — yalnız manifest
 * kaydını (Faz 6A JSON şekli) ve ÇÖZÜLMÜŞ bağımlılık sonucunu
 * (ör. sector_term_id, qualification_post_id) girdi olarak alır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Managed_Fields {

	/**
	 * mb_sektor için yönetilen alanlar. `slug`/`name`/`description` WP
	 * terim çekirdek alanlarıdır (post-meta değil); `icon_key`/
	 * `image_attachment_id` term-meta'dır (`_mb_icon_key`/
	 * `_mb_image_attachment_id`, bkz. class-taxonomies.php).
	 */
	const SECTOR_FIELDS = array( 'slug', 'name', 'description', 'icon_key', 'image_attachment_id' );

	/**
	 * mb_yeterlilik için yönetilen alanlar. `sector_term_id` ÇÖZÜLMÜŞ bir
	 * bağımlılıktır (manifest'in `sector_slug`'ından türetilir) —
	 * çözülemezse bu kaydın tamamı `blocked_dependency` olur, projeksiyon
	 * hiç üretilmez (bkz. MaviBelge_Core_Import_Dry_Run_Planner).
	 */
	const QUALIFICATION_FIELDS = array( 'title', 'myk_code', 'level', 'revision', 'record_status', 'sector_term_id' );

	/**
	 * mb_ucret için yönetilen alanlar. `qualification_post_id` ÇÖZÜLMÜŞ
	 * bir bağımlılıktır — dolu `qualification_code` için çözülemezse
	 * `blocked_dependency`; boş kod için HER ZAMAN `0` (tahmin/fuzzy
	 * eşleştirme yok, Faz 6A'nın 19 kodsuz ücret kararı aynen taşınır).
	 * `_mb_min_amount_kurus`/`_mb_max_amount_kurus` BİLEREK dışlandı —
	 * bunlar `price_options`'ın türevidir (mevcut eklenti bunları zaten
	 * `price_options`'tan yeniden hesaplıyor, bkz. class-validator.php
	 * normalize_price_options()); iki kez, birbirinden bağımsız olarak
	 * hem hesaplanıp hem hash'e ayrı ayrı girmesi gereksiz drift riski
	 * yaratırdı.
	 */
	const FEE_FIELDS = array(
		'title', 'profession_name', 'level', 'sector_slug', 'qualification_post_id', 'qualification_code',
		'pricing_type', 'price_options', 'vat_included', 'certificate_print_fee_kurus',
		'source_name', 'source_page', 'source_attachment_id', 'tariff_period', 'record_status',
		'valid_from', 'valid_until',
	);

	/**
	 * Faz 7 içerik aktarımı — mb_haber (post) için yönetilen alanlar.
	 * `slug` = post_name (DOĞAL ANAHTAR), `title` = post_title, `content` =
	 * post_content (manifest `body` HAM dizge), `excerpt` = post_excerpt
	 * (`summary`), `published_on` = post_date'in `Y-m-d` kısmı (taslak için
	 * `Y-m-d 12:00:00`; post_date_gmt sıfır kalır), `news_type_term_id` =
	 * ÇÖZÜLMÜŞ `mb_haber_turu` terimi (bağımlılık: çözülemezse kaydın tamamı
	 * `blocked_dependency`), `approval_status` = SABİT `in_review` (import
	 * edilen haber ASLA approved/publish olmaz). Post durumu bu listede YOK:
	 * import her zaman `draft` yazar ve `update` post_status'u DEĞİŞTİRMEZ.
	 */
	const NEWS_FIELDS = array( 'slug', 'title', 'content', 'excerpt', 'published_on', 'news_type_term_id', 'approval_status' );

	/**
	 * Faz 12b — mb_referans (post) için yönetilen alanlar. reference_status SABİT "real" (canlı referans sayfasından alınmış gerçek
	 * logolar), record_status "active", website_url '' (bağlantı bilinmiyor; uydurulmaz). `logo_sha256` = logo dosyasının SHA-256'sı:
	 * incoming değer manifestteki özet; MEVCUT değer bağlı attachment'ın GERÇEK dosya özetidir (attachment yoksa/geçersizse '').
	 * Böylece "geçerli ve erişilebilir logo" hash karşılaştırmasının kendisidir; attachment kimliği ortama özgüdür ve hash'e girmez.
	 * `sort_order` = source_index + 1. Firma adı yönetilen alan DEĞİL nötr etiket ("Referans NN") olarak title'a yazılır (tahmin yok).
	 */
	const REFERENCE_FIELDS = array( 'slug', 'title', 'reference_status', 'record_status', 'sort_order', 'website_url', 'logo_sha256' );

	/**
	 * Faz 12b — mb_sss (post) için yönetilen alanlar. slug = post_name (DOĞAL ANAHTAR), title = soru (post_title), content = cevap
	 * (post_content, düz metin), sort_order = source_index + 1, record_status SABİT "active". Post durumu bu listede YOK: import her
	 * zaman draft yazar ve update post_status'u DEĞİŞTİRMEZ (yayın editör işlemidir).
	 */
	const FAQ_FIELDS = array( 'slug', 'title', 'content', 'sort_order', 'record_status' );

	/**
	 * Faz 12 — WordPress çekirdek `page` türü (yeni post type YOK). `slug` = post_name (DOĞAL ANAHTAR), `title`, `content` (post_content, KAPALI
	 * izin listeli kanonik HTML), `excerpt` (post_excerpt), `parent_id` (bu sürümde SABİT 0 — düz URL yapısı), `menu_order` (= sıra + 1).
	 * Post durumu bu listede YOK: import her zaman `draft` yazar, `update` post_status'u DEĞİŞTİRMEZ; yayınlama ayrı, onaylı işlemdir
	 * (MaviBelge_Core_Import_Page_Publisher). `page_template` yönetilmez (tema slug/layout ile seçer).
	 */
	const PAGE_FIELDS = array( 'slug', 'title', 'content', 'excerpt', 'parent_id', 'menu_order' );

	/** Bilinen import türleri (sıra: apply sırası). */
	const TYPES = array( 'sector', 'qualification', 'fee', 'news', 'reference', 'faq', 'page' );

	/** Tür => yönetilen alan allowlist'i (TEK kaynak; tanınmayan tür için null). */
	public static function fields_for( $type ) {
		switch ( $type ) {
			case 'sector':
				return self::SECTOR_FIELDS;
			case 'qualification':
				return self::QUALIFICATION_FIELDS;
			case 'fee':
				return self::FEE_FIELDS;
			case 'news':
				return self::NEWS_FIELDS;
			case 'reference':
				return self::REFERENCE_FIELDS;
			case 'faq':
				return self::FAQ_FIELDS;
			case 'page':
				return self::PAGE_FIELDS;
			default:
				return null;
		}
	}

	/**
	 * @param array $newsRecord Faz 7 news.manifest.json kaydı — ÇAĞRIDAN ÖNCE
	 *   `MaviBelge_Core_Import_Record_Validator::validate_news()` ile doğrulanmış olmalı.
	 * @param array $resolvedDependencies array{news_type_term_id: int} — zorunlu; çözülemezse planlayıcı `blocked_dependency` üretir, bu metot çağrılmaz.
	 * @return array{fields: array}
	 */
	public static function project_news( array $newsRecord, array $resolvedDependencies ) {
		return array(
			'fields' => array(
				'slug'              => $newsRecord['slug'],
				'title'             => $newsRecord['title'],
				'content'           => $newsRecord['body'],
				'excerpt'           => $newsRecord['summary'],
				'published_on'      => $newsRecord['published_on'],
				'news_type_term_id' => (int) $resolvedDependencies['news_type_term_id'],
				'approval_status'   => 'in_review',
			),
		);
	}

	/**
	 * @param array $pageRecord Faz 12 pages.manifest.json kaydı — ÇAĞRIDAN ÖNCE
	 *   `MaviBelge_Core_Import_Record_Validator::validate_page()` ile doğrulanmış olmalı (parent_source_key null, içerik güvenli).
	 * @param array $resolvedDependencies Bağımlılık yok (parametre imza tutarlılığı için).
	 * @return array{fields: array}
	 */
	public static function project_page( array $pageRecord, array $resolvedDependencies = array() ) {
		return array(
			'fields' => array(
				'slug'       => $pageRecord['slug'],
				'title'      => $pageRecord['title'],
				'content'    => $pageRecord['content'],
				'excerpt'    => $pageRecord['excerpt'],
				'parent_id'  => 0,
				'menu_order' => $pageRecord['menu_order'],
			),
		);
	}

	/**
	 * @param array $referenceRecord references.manifest.json kaydı — ÇAĞRIDAN ÖNCE
	 *   `MaviBelge_Core_Import_Record_Validator::validate_reference()` ile doğrulanmış olmalı.
	 * @param array $resolvedDependencies Bağımlılık yok (parametre imza tutarlılığı için).
	 * @return array{fields: array}
	 */
	public static function project_reference( array $referenceRecord, array $resolvedDependencies = array() ) {
		return array(
			'fields' => array(
				'slug'             => $referenceRecord['slug'],
				'title'            => $referenceRecord['name'],
				'reference_status' => 'real',
				'record_status'    => 'active',
				'sort_order'       => $referenceRecord['source_index'] + 1,
				'website_url'      => '',
				'logo_sha256'      => $referenceRecord['logo_sha256'],
			),
		);
	}

	/**
	 * @param array $faqRecord faqs.manifest.json kaydı — ÇAĞRIDAN ÖNCE
	 *   `MaviBelge_Core_Import_Record_Validator::validate_faq()` ile doğrulanmış olmalı.
	 * @param array $resolvedDependencies Bağımlılık yok (parametre imza tutarlılığı için).
	 * @return array{fields: array}
	 */
	public static function project_faq( array $faqRecord, array $resolvedDependencies = array() ) {
		return array(
			'fields' => array(
				'slug'          => $faqRecord['slug'],
				'title'         => $faqRecord['question'],
				'content'       => $faqRecord['answer'],
				'sort_order'    => $faqRecord['source_index'] + 1,
				'record_status' => 'active',
			),
		);
	}

	/**
	 * @param array $sectorRecord Faz 6A sectors.manifest.json kaydı —
	 *   ÇAĞRIDAN ÖNCE `MaviBelge_Core_Import_Record_Validator::validate_sector()`
	 *   ile doğrulanmış olmalı; bu metod artık kendi başına sanitize/
	 *   kurtarma yapmaz — eksik bir alanı `''`'e çevirmez.
	 * @param array $resolvedDependencies array{image_attachment_id?:int}
	 *   — `image_attachment_id` yalnız kaynakta GERÇEKTEN bir görsel VARSA
	 *   ve bu görsel gerçek bir attachment'a ÇÖZÜLMÜŞSE burada bulunur;
	 *   kaynakta görsel yoksa 0 olarak GEÇERLİ sayılır (dependency yok);
	 *   kaynakta görsel VARSA ama henüz çözülmemişse bu anahtar HİÇ
	 *   verilmemeli — planlayıcı bunu `blocked_dependency` sayar (bkz.
	 *   MaviBelge_Core_Import_Dry_Run_Planner::plan_sector()).
	 * @return array{fields: array} SECTOR_FIELDS anahtarlarına sahip, WordPress'e YAZILACAK değerler.
	 */
	public static function project_sector( array $sectorRecord, array $resolvedDependencies ) {
		return array(
			'fields' => array(
				'slug'                => $sectorRecord['slug'],
				'name'                => $sectorRecord['name'],
				'description'         => $sectorRecord['description'],
				'icon_key'            => $sectorRecord['icon'],
				'image_attachment_id' => isset( $resolvedDependencies['image_attachment_id'] ) ? (int) $resolvedDependencies['image_attachment_id'] : 0,
			),
		);
	}

	/**
	 * @param array $qualificationRecord Faz 6A qualifications.manifest.json
	 *   kaydı — ÇAĞRIDAN ÖNCE
	 *   `MaviBelge_Core_Import_Record_Validator::validate_qualification()`
	 *   ile doğrulanmış olmalı.
	 * @param array $resolvedDependencies array{sector_term_id: int} — zorunlu, her zaman çözülebilir olmalı (sektör manifesti zaten kapalı bir küme).
	 * @return array{fields: array}
	 */
	public static function project_qualification( array $qualificationRecord, array $resolvedDependencies ) {
		return array(
			'fields' => array(
				'title'          => $qualificationRecord['name'],
				'myk_code'       => $qualificationRecord['code'],
				'level'          => $qualificationRecord['level'],
				'revision'       => $qualificationRecord['revision'],
				// Faz 6B1 Son Kapanış Düzeltmesi: artık hard-code EDİLMİYOR —
				// `MaviBelge_Core_Import_Record_Validator::validate_qualification()`
				// bu alanın KESİNLİKLE "active" olduğunu ÖNCEDEN doğruladı
				// (manifest'in bağlayıcı sabiti, bkz. faz6a-manifest-sozlesmesi.md);
				// bozuk/eksik bir planned_record_status artık aynı hash'e
				// sessizce giremez — doğrulama başarısız olur, projeksiyon hiç çağrılmaz.
				'record_status'  => $qualificationRecord['planned_record_status'],
				'sector_term_id' => (int) $resolvedDependencies['sector_term_id'],
			),
		);
	}

	/**
	 * @param array $feeRecord Faz 6A fees.manifest.json kaydı — ÇAĞRIDAN ÖNCE
	 *   `MaviBelge_Core_Import_Record_Validator::validate_fee()` ile
	 *   doğrulanmış olmalı; bu metod artık kendi başına sanitize/kurtarma
	 *   yapan ikinci bir doğrulayıcı DEĞİLDİR (bkz. görev promptu §4) —
	 *   eksik/yanlış tipli bir alanı `''`/`0`/boş diziye çevirmez.
	 * @param array $resolvedDependencies array{qualification_post_id?: int}
	 *   — `qualification_code` boşsa bu anahtar hiç gerekmez (0 sabit
	 *   kullanılır); doluysa ÇÖZÜLMÜŞ olmalı, aksi halde planlayıcı
	 *   `blocked_dependency` üretir.
	 * @param array $validatedPriceOptions `MaviBelge_Core_Import_Record_Validator::validate_fee()`'nin
	 *   döndürdüğü `normalized_price_options` — KANONİK
	 *   `MaviBelge_Core_Validator::evaluate_price_options()` çıktısı,
	 *   OLDUĞU GİBİ kullanılır; burada ikinci bir cast/skip yolu YOKTUR.
	 * @return array{fields: array}
	 */
	public static function project_fee( array $feeRecord, array $resolvedDependencies, array $validatedPriceOptions ) {
		$qualificationCode = $feeRecord['qualification_code'];
		$qualificationPostId = 0;
		if ( '' !== $qualificationCode ) {
			$qualificationPostId = (int) $resolvedDependencies['qualification_post_id'];
		}

		$priceOptions = array();
		foreach ( $validatedPriceOptions as $option ) {
			$priceOptions[] = array(
				'label'        => $option['label'],
				'units'        => array_values( $option['units'] ),
				'amount_kurus' => $option['amount_kurus'],
				'sort_order'   => $option['sort_order'],
			);
		}

		return array(
			'fields' => array(
				'title'                       => $feeRecord['profession_name'],
				'profession_name'             => $feeRecord['profession_name'],
				'level'                       => $feeRecord['level'],
				'sector_slug'                 => $feeRecord['sector_slug'],
				'qualification_post_id'       => $qualificationPostId,
				'qualification_code'          => $qualificationCode,
				'pricing_type'                => $feeRecord['pricing_type'],
				'price_options'               => $priceOptions,
				'vat_included'                => $feeRecord['vat_included'],
				'certificate_print_fee_kurus' => $feeRecord['certificate_print_fee_kurus'],
				'source_name'                 => $feeRecord['source_name'],
				'source_page'                 => null === $feeRecord['source_page'] ? 0 : $feeRecord['source_page'],
				'source_attachment_id'        => $feeRecord['source_attachment_id'],
				'tariff_period'               => $feeRecord['planned_tariff_period'],
				'record_status'               => $feeRecord['planned_record_status'],
				'valid_from'                  => $feeRecord['planned_valid_from'],
				'valid_until'                 => $feeRecord['planned_valid_until'],
			),
		);
	}
}
