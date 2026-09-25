<?php
/**
 * Faz 6B2 — `MaviBelge_Core_Import_Target_Repository`'nin GERÇEK, SALT
 * OKUNUR WordPress uygulaması.
 *
 * Bu dosyada HİÇBİR yazma WordPress API'si çağrılmaz — yalnız
 * `get_terms()`/`get_term_by()`/`get_term()`/`get_term_meta()`/
 * `get_posts()`/`get_post()`/`get_post_meta()`/`get_post_type()`/
 * `wp_get_post_terms()` gibi OKUMA fonksiyonları. `wp_insert_*`,
 * `wp_update_*`, `wp_delete_*`, `*_post_meta`/`*_term_meta`'nın
 * add/update/delete varyantları, `add_option`/`update_option`,
 * `$wpdb->insert/update/delete/query/replace`, dosya yazma/silme veya
 * `set_transient()` bu dosyada YOKTUR (statik tarama Faz 6B2 teslim
 * raporunda kanıtlanır).
 *
 * `type_verified => true` yalnız bu sınıfın GERÇEKTEN doğru içerik
 * türünde (taksonomi/post_type) bir kayıt bulduğu ve/veya `get_post_type()`
 * ile ayrıca teyit ettiği durumlarda yazılır (bkz.
 * interface-import-target-repository.php'nin "dürüst sınır" notu) —
 * dosya adı/benzerlik tahmini veya fuzzy eşleştirme YOKTUR.
 *
 * Düzeltme ve Kabul §2.1 — Kök neden: önceki tur `find_sector_target()`/
 * `find_post_target()` yalnız BEKLENEN türün kendi taksonomi/post_type
 * sorgusunu çalıştırıyordu; `_mb_import_source_key` marker'ı YANLIŞ bir
 * içerik türünde bulunsa bile beklenen tür sorgusu boş dönüyor, bu da
 * "hedef yok" (create adayı) sonucuna yol açıyordu —
 * `conflict_wrong_target_type` yolu gerçek adapterda fiilen ERİŞİLEMEZ
 * hâldeydi. Bu turda TEK, paylaşılan bir aday-keşif katmanı
 * (`discover_candidates()`) eklendi: marker'ı HER ZAMAN üç hedef türünün
 * (mb_sektor/mb_yeterlilik/mb_ucret) TAMAMINDA arar, tür+ID kimliğiyle
 * dedupe eder ve §3'teki karar matrisini uygular. `find_sector_target()`/
 * `find_post_target()` artık İKİ AYRI, kısmen çoğaltılmış karar mantığı
 * DEĞİL — yalnız TİPE ÖZEL "mevcut alan okuma" katmanıdır.
 *
 * Düzeltme ve Kabul §2.2 — Kök neden: önceki tur `(bool)`/`to_int()`/
 * `to_nonneg_int()` gibi HER ZAMAN "başarılı" bir değer üreten (biçimsiz
 * girdiyi sessizce 0/false/boş diziye düşüren) gevşek cast'ler
 * kullanıyordu; bu, bozuk bir WordPress meta değerinin GEÇERLİ bir
 * varsayılana dönüşüp sessizce hash'e girebilmesine yol açıyordu. Bu
 * turda `strict_int()`/`strict_nonneg_int_or_empty_zero()`/`strict_bool()`
 * "başarı + değer" ayrımını koruyan katı dönüştürücülerle değiştirildi;
 * `current_*_fields()` metotları artık HERHANGİ bir alan dönüşümü
 * başarısız olursa (veya `price_options` array değilse, veya yeterlilikte
 * sektör terimi sıfır/birden-çoksa — ilk terim KEYFÎ seçilmez) `null`
 * döner. `current_managed_fields === null` iken `target_type_matches`
 * hâlâ `true` olduğundan, bu zaten `MaviBelge_Core_Import_Record_Validator::normalize_target_lookup()`'ın
 * mevcut fail-closed kuralına (`target_found=true` + `target_type_matches=true`
 * iken `current_managed_fields` ASLA eksik/null OLAMAZ) düşerek
 * `invalid_target_state` üretir — sahte bir varsayılan asla hash'e girmez.
 *
 * Son Kapanış Düzeltmesi §3 — Kök neden: string beklenen meta değerleri
 * (`_mb_icon_key`, `_mb_source_name`, `_mb_myk_code`, marker, hash vb.)
 * hâlâ `(string) get_*_meta(...)` ile okunuyordu; bozuk bir dizi/nesne
 * meta değeri "Array" gibi GEÇERLİ görünen bir string'e dönüşüp (yalnız
 * `is_string()` kontrolü olan `icon_key`/`source_name` alanlarında)
 * hash'e girebiliyordu. Artık HİÇBİR meta/çekirdek alan cast edilmez:
 * ham değerler toplanır, saf `*_fields_from_raw()` kurucuları
 * `strict_string()`/`strict_int()`/`strict_nonneg_int_or_empty_zero()`/
 * `strict_bool()` ile doğrular; TEK bir alan başarısızsa TÜM
 * `current_managed_fields` `null` olur (yukarıdaki fail-closed yol).
 * Marker yalnız GERÇEK string VE beklenen source_key'e birebir eşitse,
 * hash yalnız GERÇEK string ise kabul edilir; aksi hâlde `target_found`
 * gerçek bool DIŞINDA bir sinyal (`'invalid_meta'`) taşır.
 *
 * Son Kapanış Düzeltmesi §4 — Kök neden: regex'ten geçen sayısal string
 * doğrudan `(int)` ile çevriliyordu; PHP_INT_MAX'ı aşan bir değer sınır
 * değere "kırpılıp" `ok=true` alabiliyordu. Artık iki public int
 * yardımcısı TEK, saf `parse_canonical_decimal_int()`'i kullanır: float
 * kullanmadan, cast'ten ÖNCE basamak-string karşılaştırmasıyla
 * PHP_INT_MAX/PHP_INT_MIN sınırı + cast SONRASI round-trip kontrolü.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_WordPress_Target_Repository implements MaviBelge_Core_Import_Target_Repository, MaviBelge_Core_Import_Content_Dependency_Resolver {

	/**
	 * Çağrı sırasında biriken, gizli/hassas veri TAŞIMAYAN tanı kayıtları
	 * (yalnız sabit neden kodu + tip + manifest'teki mevcut source_key/
	 * slug/MYK kodu). `resolve_qualification_post_id()`'nin "duplicate
	 * yeterlilik" bulgusu gibi, tek bir dönüş değerine (`null`) sığmayan
	 * ek bilgi için kullanılır — bkz. görev promptu §5.2.
	 *
	 * @var array<int, array{code:string, type:string, source_key:string}>
	 */
	private $diagnostics = array();

	/** @inheritDoc */
	public function get_diagnostics() {
		return $this->diagnostics;
	}

	/** @inheritDoc */
	public function find_target_by_source_key( $type, $sourceKey ) {
		if ( ! is_string( $sourceKey ) || '' === $sourceKey ) {
			return array( 'target_found' => false );
		}
		if ( ! in_array( $type, MaviBelge_Core_Import_Managed_Fields::TYPES, true ) ) {
			return array( 'target_found' => false );
		}

		$discovery = $this->discover_candidates( $sourceKey );
		if ( ! $discovery['ok'] ) {
			// §3 karar matrisi — sorgu hatası / güvenilir keşif yapılamadı:
			// "hedef yok" (create adayı) İLE ASLA KARIŞTIRILMAZ. Kapalı
			// `ALLOWED_LOOKUP_KEYS` şeması yeni bir "query_error" anahtarı
			// EKLEYEMEZ — bunun yerine `target_found` GERÇEK bool DIŞINDA
			// bir değer taşır; `normalize_target_lookup()`'ın mevcut
			// `is_bool( $lookup['target_found'] )` kapısı bunu OTOMATİK
			// olarak `invalid_target_state`'e düşürür (create ASLA üretilmez).
			return array( 'target_found' => 'query_error' );
		}

		$candidates = $discovery['candidates'];
		$count      = count( $candidates );

		if ( 0 === $count ) {
			// Faz 6B3 Önkoşul — marker ile hedef yok diye doğrudan create
			// adayı SAYILMAZ: salt okunur doğal anahtar preflight'ı sonucu
			// her zaman eklenir (bkz. natural_key_state()).
			return array( 'target_found' => false, 'natural_key' => $this->natural_key_state( $type, $sourceKey ) );
		}
		if ( $count > 1 ) {
			// §3 — 2+ aday, türlerden BAĞIMSIZ: duplicate. Doğru türde bir
			// aday olsa bile "doğru olan sessizce seçilmez" (görev promptu
			// §2.1 madde 8).
			return array( 'target_found' => false, 'duplicate_targets' => true );
		}

		// count === 1.
		$candidate = $candidates[0];
		if ( $candidate['type'] !== $type ) {
			return $this->wrong_type_result( $candidate, $sourceKey );
		}
		return $this->full_target_result( $type, $candidate['id'], $sourceKey );
	}

	/**
	 * §2.1 — TEK, paylaşılan aday-keşif katmanı. `_mb_import_source_key`
	 * marker'ını üç bilinen import hedef alanının TAMAMINDA (mb_sektor
	 * term meta, mb_yeterlilik post meta, mb_ucret post meta) arar; ham
	 * SQL KULLANMAZ, yalnız WordPress'in salt okunur sorgu API'lerini
	 * kullanır. Tüm adayları ÖNCE tür+ID kimliğiyle toplar (aynı fiziksel
	 * hedef iki kez SAYILMAZ — zaten farklı taksonomi/post_type
	 * sorgularından geldiği için doğal olarak ayrık kimliklerdir).
	 *
	 * @return array{ok: bool, candidates: array<int, array{type:string, id:int}>}
	 *   `ok=false` yalnız GERÇEK bir sorgu hatası/beklenmeyen şekilde
	 *   (`WP_Error`, dizi olmayan sonuç) döner — "sıfır sonuç" bundan
	 *   AYRIDIR (o zaten geçerli bir `ok=true, candidates=[]` sonucudur).
	 */
	private function discover_candidates( $sourceKey ) {
		$candidates = array();

		$sectorIds = get_terms(
			array(
				'taxonomy'   => 'mb_sektor',
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_key'   => '_mb_import_source_key',
				'meta_value' => $sourceKey,
				'number'     => 5, // Duplicate tespiti için küçük bir üst sınır yeterli.
			)
		);
		if ( is_wp_error( $sectorIds ) || ! is_array( $sectorIds ) ) {
			return array( 'ok' => false, 'candidates' => array() );
		}
		foreach ( $sectorIds as $id ) {
			$termId = self::positive_int_or_null( $id );
			if ( null === $termId ) {
				// Son Kapanış §3.2 — ID bile gerçek pozitif tam sayı değilse
				// güvenilir keşif yapılamadı; cast ile "kurtarılmaz".
				return array( 'ok' => false, 'candidates' => array() );
			}
			$candidates[] = array( 'type' => MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR, 'id' => $termId );
		}

		$postTypeByType = array(
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_QUALIFICATION => 'mb_yeterlilik',
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FEE           => 'mb_ucret',
			// Faz 7: marker HER ZAMAN içerik türlerinde de aranır (tür uyuşmazlığı görünür kalsın).
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_NEWS          => 'mb_haber',
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_REFERENCE     => 'mb_referans',
		);
		foreach ( $postTypeByType as $importType => $postType ) {
			$ids = get_posts(
				array(
					'post_type'      => $postType,
					'post_status'    => 'any',
					'fields'         => 'ids',
					'meta_key'       => '_mb_import_source_key',
					'meta_value'     => $sourceKey,
					'posts_per_page' => 5,
					'no_found_rows'  => true,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			if ( is_wp_error( $ids ) || ! is_array( $ids ) ) {
				return array( 'ok' => false, 'candidates' => array() );
			}
			foreach ( $ids as $id ) {
				$postId = self::positive_int_or_null( $id );
				if ( null === $postId ) {
					return array( 'ok' => false, 'candidates' => array() );
				}
				$candidates[] = array( 'type' => $importType, 'id' => $postId );
			}
		}

		return array( 'ok' => true, 'candidates' => $candidates );
	}

	/**
	 * §2.1 madde 7 — tam bir aday bulundu ama BEKLENEN türde DEĞİL.
	 * `target_id` GERÇEK (pozitif), `has_source_key_marker=true`
	 * (marker'ın kendisi bu adayı bulmak için zaten eşleşti; Son Kapanış
	 * §3.2 ile ham marker ayrıca gerçek string + source_key eşitliğiyle
	 * yeniden doğrulanır, aksi hâlde `'invalid_meta'` fail-closed sinyali),
	 * `current_managed_fields=null` (yanlış türden "yönetilen alan
	 * durumu" okumak anlamsızdır — `normalize_target_lookup()` bunu
	 * `target_type_matches=false` dalında zaten YOK sayar).
	 */
	private function wrong_type_result( array $candidate, $sourceKey ) {
		// Son Kapanış §3.2 — marker'ın kendisi de GERÇEK string VE beklenen
		// source_key'e birebir eşit olmalı; aksi hâlde fail-closed.
		if ( MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR === $candidate['type'] ) {
			$markerRaw = get_term_meta( $candidate['id'], '_mb_import_source_key', true );
		} else {
			$markerRaw = get_post_meta( $candidate['id'], '_mb_import_source_key', true );
		}
		if ( ! self::marker_raw_matches( $markerRaw, $sourceKey ) ) {
			return self::invalid_meta_result();
		}
		return array(
			'target_found'           => true,
			'target_id'              => $candidate['id'],
			'duplicate_targets'      => false,
			'target_type_matches'    => false,
			'has_source_key_marker'  => true,
			'last_applied_hash'      => null,
			'current_managed_fields' => null,
		);
	}

	private function full_target_result( $type, $id, $sourceKey ) {
		switch ( $type ) {
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR:
				return $this->build_sector_result( $id, $sourceKey );
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_QUALIFICATION:
				return $this->build_post_result( $id, 'mb_yeterlilik', $sourceKey, array( $this, 'current_qualification_fields' ) );
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FEE:
				return $this->build_post_result( $id, 'mb_ucret', $sourceKey, array( $this, 'current_fee_fields' ) );
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_NEWS:
				return $this->build_post_result( $id, 'mb_haber', $sourceKey, array( $this, 'current_news_fields' ) );
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_REFERENCE:
				return $this->build_post_result( $id, 'mb_referans', $sourceKey, array( $this, 'current_reference_fields' ) );
			default:
				return array( 'target_found' => false );
		}
	}

	/**
	 * Son Kapanış §3.2 — meta/sistem alanı beklenen GERÇEK PHP tipinde
	 * değil (ör. marker/hash dizi/nesne, marker source_key'den farklı).
	 * `'query_error'` gibi `target_found`'a gerçek bool DIŞINDA bir değer
	 * koyar; `normalize_target_lookup()`'ın `is_bool()` kapısı bunu
	 * `invalid_target_state`'e düşürür — create/update ASLA üretilmez.
	 */
	private static function invalid_meta_result() {
		return array( 'target_found' => 'invalid_meta' );
	}

	private function build_sector_result( $termId, $sourceKey ) {
		$term = get_term( $termId, 'mb_sektor' );
		if ( ! $term || is_wp_error( $term ) ) {
			// Savunma derinliği — aday zaten GERÇEK bir sorgudan geldi,
			// normalde buraya düşülmez; düşerse fail-closed.
			return array( 'target_found' => 'query_error' );
		}

		if ( ! self::marker_raw_matches( get_term_meta( $termId, '_mb_import_source_key', true ), $sourceKey ) ) {
			return self::invalid_meta_result();
		}
		$hashConv = self::normalize_last_applied_hash_raw( get_term_meta( $termId, '_mb_last_applied_hash', true ) );
		if ( ! $hashConv['ok'] ) {
			return self::invalid_meta_result();
		}

		return array(
			'target_found'           => true,
			'target_id'              => $termId,
			'duplicate_targets'      => false,
			// mb_sektor taksonomisine karşı marker'ı GERÇEKTEN eşleşen tek
			// aday olduğu ve tür zaten discover_candidates() içinde
			// belirlendiği için kanıtlanmıştır.
			'target_type_matches'    => true,
			// Marker yukarıda gerçek string + source_key eşitliğiyle doğrulandı.
			'has_source_key_marker'  => true,
			'last_applied_hash'      => $hashConv['value'],
			'current_managed_fields' => self::sector_fields_from_raw(
				array(
					'slug'                => self::object_prop( $term, 'slug' ),
					'name'                => self::object_prop( $term, 'name' ),
					'description'         => self::object_prop( $term, 'description' ),
					'icon_key'            => get_term_meta( $termId, '_mb_icon_key', true ),
					'image_attachment_id' => get_term_meta( $termId, '_mb_image_attachment_id', true ),
				)
			),
		);
	}

	/** @param callable $fieldsResolver ($postId) => array|null */
	private function build_post_result( $postId, $postType, $sourceKey, $fieldsResolver ) {
		if ( $postType !== get_post_type( $postId ) ) {
			// Savunma derinliği — aday zaten bu post_type sorgusundan
			// geldi; normalde buraya düşülmez.
			return array( 'target_found' => 'query_error' );
		}

		if ( ! self::marker_raw_matches( get_post_meta( $postId, '_mb_import_source_key', true ), $sourceKey ) ) {
			return self::invalid_meta_result();
		}
		$hashConv = self::normalize_last_applied_hash_raw( get_post_meta( $postId, '_mb_last_applied_hash', true ) );
		if ( ! $hashConv['ok'] ) {
			return self::invalid_meta_result();
		}

		return array(
			'target_found'           => true,
			'target_id'              => $postId,
			'duplicate_targets'      => false,
			'target_type_matches'    => true, // get_post_type() ile yukarıda ayrıca teyit edildi.
			'has_source_key_marker'  => true, // Yukarıda gerçek string + source_key eşitliğiyle doğrulandı.
			'last_applied_hash'      => $hashConv['value'],
			'current_managed_fields' => call_user_func( $fieldsResolver, $postId ),
		);
	}

	/** Son Kapanış §3.2 — yalnız HAM değerleri toplar; doğrulama saf kurucudadır. */
	private function current_qualification_fields( $postId ) {
		return self::qualification_fields_from_raw(
			array(
				'title'           => self::object_prop( get_post( $postId ), 'post_title' ),
				'myk_code'        => get_post_meta( $postId, '_mb_myk_code', true ),
				'level'           => get_post_meta( $postId, '_mb_level', true ),
				'revision'        => get_post_meta( $postId, '_mb_revision', true ),
				'record_status'   => get_post_meta( $postId, '_mb_record_status', true ),
				'sector_term_ids' => wp_get_post_terms( $postId, 'mb_sektor', array( 'fields' => 'ids' ) ),
			)
		);
	}

	/** Faz 7 — yalnız HAM değerleri toplar (cast YOK); doğrulama saf `news_fields_from_raw()` kurucusundadır. */
	private function current_news_fields( $postId ) {
		$post = get_post( $postId );
		return self::news_fields_from_raw(
			array(
				'slug'               => self::object_prop( $post, 'post_name' ),
				'title'              => self::object_prop( $post, 'post_title' ),
				'content'            => self::object_prop( $post, 'post_content' ),
				'excerpt'            => self::object_prop( $post, 'post_excerpt' ),
				'post_date'          => self::object_prop( $post, 'post_date' ),
				'news_type_term_ids' => wp_get_object_terms( $postId, 'mb_haber_turu', array( 'fields' => 'ids' ) ),
				'approval_status'    => get_post_meta( $postId, '_mb_approval_status', true ),
			)
		);
	}

	/** Faz 7 — bkz. current_news_fields(). */
	private function current_reference_fields( $postId ) {
		$post = get_post( $postId );
		return self::reference_fields_from_raw(
			array(
				'slug'               => self::object_prop( $post, 'post_name' ),
				'title'              => self::object_prop( $post, 'post_title' ),
				'reference_status'   => get_post_meta( $postId, '_mb_reference_status', true ),
				'record_status'      => get_post_meta( $postId, '_mb_record_status', true ),
				'sort_order'         => get_post_meta( $postId, '_mb_sort_order', true ),
				'website_url'        => get_post_meta( $postId, '_mb_website_url', true ),
				'logo_attachment_id' => get_post_meta( $postId, '_mb_logo_attachment_id', true ),
			)
		);
	}

	/**
	 * Faz 7 — SAF kurucu (WordPress çağırmaz). `post_date` yalnız gerçek string
	 * `YYYY-AA-GG SS:DD:SS` olabilir; `published_on` onun tarih kısmıdır (saat
	 * yönetilen alan DEĞİL). `news_type_term_ids` tam BİR gerçek pozitif ID
	 * içermeli (0/2+ terim, WP_Error, bozuk ID -> null; ilk terim keyfî seçilmez).
	 * TEK bir alan başarısızsa TÜM küme null (sahte varsayılan yok).
	 *
	 * @param array $raw slug/title/content/excerpt/post_date/news_type_term_ids/approval_status ham değerleri
	 * @return array|null
	 */
	public static function news_fields_from_raw( array $raw ) {
		if ( ! self::has_exact_keys( $raw, array( 'slug', 'title', 'content', 'excerpt', 'post_date', 'news_type_term_ids', 'approval_status' ) ) ) {
			return null;
		}
		$strings = self::strict_string_fields( $raw, array( 'slug', 'title', 'content', 'excerpt', 'post_date', 'approval_status' ) );
		if ( null === $strings ) {
			return null;
		}
		if ( 1 !== preg_match( '/^(\d{4}-\d{2}-\d{2}) \d{2}:\d{2}:\d{2}\z/', $strings['post_date'], $m ) || ! MaviBelge_Core_Validator::is_valid_ymd_date( $m[1] ) ) {
			return null;
		}
		$termIds = $raw['news_type_term_ids'];
		if ( ! is_array( $termIds ) || 1 !== count( $termIds ) ) {
			return null;
		}
		$termIds    = array_values( $termIds );
		$newsTypeId = self::positive_int_or_null( $termIds[0] );
		if ( null === $newsTypeId ) {
			return null;
		}
		return array(
			'slug'              => $strings['slug'],
			'title'             => $strings['title'],
			'content'           => $strings['content'],
			'excerpt'           => $strings['excerpt'],
			'published_on'      => $m[1],
			'news_type_term_id' => $newsTypeId,
			'approval_status'   => $strings['approval_status'],
		);
	}

	/**
	 * Faz 7 — SAF kurucu. Meta metin temsilleri katı dönüştürülür (`"1"` -> 1, boş -> 0);
	 * TEK bir alan başarısızsa TÜM küme null.
	 *
	 * @param array $raw slug/title/reference_status/record_status/sort_order/website_url/logo_attachment_id ham değerleri
	 * @return array|null
	 */
	public static function reference_fields_from_raw( array $raw ) {
		if ( ! self::has_exact_keys( $raw, MaviBelge_Core_Import_Managed_Fields::REFERENCE_FIELDS ) ) {
			return null;
		}
		$strings = self::strict_string_fields( $raw, array( 'slug', 'title', 'reference_status', 'record_status', 'website_url' ) );
		if ( null === $strings ) {
			return null;
		}
		$sortConv = self::strict_nonneg_int_or_empty_zero( $raw['sort_order'] );
		$logoConv = self::strict_nonneg_int_or_empty_zero( $raw['logo_attachment_id'] );
		if ( ! $sortConv['ok'] || ! $logoConv['ok'] ) {
			return null;
		}
		return array(
			'slug'               => $strings['slug'],
			'title'              => $strings['title'],
			'reference_status'   => $strings['reference_status'],
			'record_status'      => $strings['record_status'],
			'sort_order'         => $sortConv['value'],
			'website_url'        => $strings['website_url'],
			'logo_attachment_id' => $logoConv['value'],
		);
	}

	/** Son Kapanış §3.2 — yalnız HAM değerleri toplar; doğrulama saf kurucudadır. */
	private function current_fee_fields( $postId ) {
		return self::fee_fields_from_raw(
			array(
				'title'                       => self::object_prop( get_post( $postId ), 'post_title' ),
				'profession_name'             => get_post_meta( $postId, '_mb_profession_name', true ),
				'level'                       => get_post_meta( $postId, '_mb_level', true ),
				'sector_slug'                 => get_post_meta( $postId, '_mb_sector_slug', true ),
				'qualification_post_id'       => get_post_meta( $postId, '_mb_qualification_id', true ),
				'qualification_code'          => get_post_meta( $postId, '_mb_qualification_code', true ),
				'pricing_type'                => get_post_meta( $postId, '_mb_pricing_type', true ),
				'price_options'               => get_post_meta( $postId, '_mb_price_options', true ),
				'vat_included'                => get_post_meta( $postId, '_mb_vat_included', true ),
				'certificate_print_fee_kurus' => get_post_meta( $postId, '_mb_certificate_print_fee_kurus', true ),
				'source_name'                 => get_post_meta( $postId, '_mb_source_name', true ),
				'source_page'                 => get_post_meta( $postId, '_mb_source_page', true ),
				'source_attachment_id'        => get_post_meta( $postId, '_mb_source_attachment_id', true ),
				'tariff_period'               => get_post_meta( $postId, '_mb_tariff_period', true ),
				'record_status'               => get_post_meta( $postId, '_mb_record_status', true ),
				'valid_from'                  => get_post_meta( $postId, '_mb_valid_from', true ),
				'valid_until'                 => get_post_meta( $postId, '_mb_valid_until', true ),
			)
		);
	}

	/**
	 * Son Kapanış §3.2 — SAF kurucu (WordPress fonksiyonu çağırmaz; bu
	 * yüzden `tests/run.php` doğrudan test edebilir). Ham değer kümesi
	 * tam `MaviBelge_Core_Import_Managed_Fields::SECTOR_FIELDS` anahtarlarını
	 * taşımalı; TEK bir alan beklenen gerçek tipte değilse `null` döner.
	 *
	 * @param array $raw slug/name/description/icon_key/image_attachment_id ham değerleri
	 * @return array|null
	 */
	public static function sector_fields_from_raw( array $raw ) {
		if ( ! self::has_exact_keys( $raw, MaviBelge_Core_Import_Managed_Fields::SECTOR_FIELDS ) ) {
			return null;
		}
		$strings = self::strict_string_fields( $raw, array( 'slug', 'name', 'description', 'icon_key' ) );
		if ( null === $strings ) {
			return null;
		}
		$imageConv = self::strict_nonneg_int_or_empty_zero( $raw['image_attachment_id'] );
		if ( ! $imageConv['ok'] ) {
			return null;
		}
		return array(
			'slug'                => $strings['slug'],
			'name'                => $strings['name'],
			'description'         => $strings['description'],
			'icon_key'            => $strings['icon_key'],
			'image_attachment_id' => $imageConv['value'],
		);
	}

	/**
	 * Son Kapanış §3.2 — SAF kurucu. `sector_term_ids` ham
	 * `wp_get_post_terms(..., fields=ids)` sonucudur: yalnız TAM BİR gerçek
	 * pozitif ID kabul edilir (sıfır/2+ terim, WP_Error, bozuk ID -> `null`;
	 * ilk terim KEYFÎ seçilmez).
	 *
	 * @param array $raw title/myk_code/level/revision/record_status/sector_term_ids ham değerleri
	 * @return array|null
	 */
	public static function qualification_fields_from_raw( array $raw ) {
		if ( ! self::has_exact_keys( $raw, array( 'title', 'myk_code', 'level', 'revision', 'record_status', 'sector_term_ids' ) ) ) {
			return null;
		}
		$strings = self::strict_string_fields( $raw, array( 'title', 'myk_code', 'revision', 'record_status' ) );
		if ( null === $strings ) {
			return null;
		}
		$levelConv = self::strict_int( $raw['level'] );
		if ( ! $levelConv['ok'] ) {
			return null;
		}
		$termIds = $raw['sector_term_ids'];
		if ( ! is_array( $termIds ) || 1 !== count( $termIds ) ) {
			return null;
		}
		$termIds      = array_values( $termIds );
		$sectorTermId = self::positive_int_or_null( $termIds[0] );
		if ( null === $sectorTermId ) {
			return null;
		}
		return array(
			'title'          => $strings['title'],
			'myk_code'       => $strings['myk_code'],
			'level'          => $levelConv['value'],
			'revision'       => $strings['revision'],
			'record_status'  => $strings['record_status'],
			'sector_term_id' => $sectorTermId,
		);
	}

	/**
	 * Son Kapanış §3.2 — SAF kurucu. Ham değer kümesi tam
	 * `MaviBelge_Core_Import_Managed_Fields::FEE_FIELDS` anahtarlarını
	 * taşımalı; string/int/bool/dizi alanlarından TEK biri başarısızsa `null`.
	 *
	 * @param array $raw FEE_FIELDS anahtarlı ham değerler
	 * @return array|null
	 */
	public static function fee_fields_from_raw( array $raw ) {
		if ( ! self::has_exact_keys( $raw, MaviBelge_Core_Import_Managed_Fields::FEE_FIELDS ) ) {
			return null;
		}
		$strings = self::strict_string_fields(
			$raw,
			array(
				'title', 'profession_name', 'sector_slug', 'qualification_code', 'pricing_type',
				'source_name', 'tariff_period', 'record_status', 'valid_from', 'valid_until',
			)
		);
		if ( null === $strings ) {
			return null;
		}
		$levelConv = self::strict_int( $raw['level'] );
		if ( ! $levelConv['ok'] ) {
			return null;
		}
		$qualIdConv = self::strict_nonneg_int_or_empty_zero( $raw['qualification_post_id'] );
		if ( ! $qualIdConv['ok'] ) {
			return null;
		}
		$certConv = self::strict_nonneg_int_or_empty_zero( $raw['certificate_print_fee_kurus'] );
		if ( ! $certConv['ok'] ) {
			return null;
		}
		$sourcePageConv = self::strict_nonneg_int_or_empty_zero( $raw['source_page'] );
		if ( ! $sourcePageConv['ok'] ) {
			return null;
		}
		$sourceAttConv = self::strict_nonneg_int_or_empty_zero( $raw['source_attachment_id'] );
		if ( ! $sourceAttConv['ok'] ) {
			return null;
		}
		$vatConv = self::strict_bool( $raw['vat_included'] );
		if ( ! $vatConv['ok'] ) {
			return null;
		}
		// §2.2 madde 6 — scalar/object ASLA boş listeye düşmez; gerçek
		// array DEĞİLSE fail-closed.
		if ( ! is_array( $raw['price_options'] ) ) {
			return null;
		}

		return array(
			'title'                       => $strings['title'],
			'profession_name'             => $strings['profession_name'],
			'level'                       => $levelConv['value'],
			'sector_slug'                 => $strings['sector_slug'],
			'qualification_post_id'       => $qualIdConv['value'],
			'qualification_code'          => $strings['qualification_code'],
			'pricing_type'                => $strings['pricing_type'],
			'price_options'               => array_values( $raw['price_options'] ),
			'vat_included'                => $vatConv['value'],
			'certificate_print_fee_kurus' => $certConv['value'],
			'source_name'                 => $strings['source_name'],
			'source_page'                 => $sourcePageConv['value'],
			'source_attachment_id'        => $sourceAttConv['value'],
			'tariff_period'               => $strings['tariff_period'],
			'record_status'               => $strings['record_status'],
			'valid_from'                  => $strings['valid_from'],
			'valid_until'                 => $strings['valid_until'],
		);
	}

	/**
	 * Son Kapanış §3.2 — SAF marker kontrolü: ham `_mb_import_source_key`
	 * yalnız GERÇEK string VE beklenen (boş olmayan) source_key'e birebir
	 * eşitse `true`. Dizi/nesne/int/null ASLA string'e çevrilmez.
	 */
	public static function marker_raw_matches( $raw, $sourceKey ) {
		if ( ! is_string( $sourceKey ) || '' === $sourceKey ) {
			return false;
		}
		$conv = self::strict_string( $raw );
		return $conv['ok'] && $conv['value'] === $sourceKey;
	}

	/**
	 * Son Kapanış §3.2 — SAF hash okuma: ham `_mb_last_applied_hash` gerçek
	 * string DEĞİLSE `ok=false` (fail-closed); boş string -> `null` (hash
	 * yok). Biçimsiz ama GERÇEK string hash, Faz 6B1 sözleşmesi gereği
	 * `normalize_target_lookup()`'a olduğu gibi verilir (orada `null`'a
	 * düşürülüp legacy_missing_hash sınıflandırılır).
	 *
	 * @return array{ok: bool, value?: string|null}
	 */
	public static function normalize_last_applied_hash_raw( $raw ) {
		$conv = self::strict_string( $raw );
		if ( ! $conv['ok'] ) {
			return array( 'ok' => false );
		}
		return array( 'ok' => true, 'value' => '' === $conv['value'] ? null : $conv['value'] );
	}

	/**
	 * Verilen anahtarların HER BİRİNİ `strict_string()` ile doğrular;
	 * biri eksik veya gerçek string değilse `null`.
	 *
	 * @return array<string, string>|null
	 */
	private static function strict_string_fields( array $raw, array $keys ) {
		$out = array();
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				return null;
			}
			$conv = self::strict_string( $raw[ $key ] );
			if ( ! $conv['ok'] ) {
				return null;
			}
			$out[ $key ] = $conv['value'];
		}
		return $out;
	}

	private static function has_exact_keys( array $raw, array $expectedKeys ) {
		$actual = array_keys( $raw );
		sort( $actual );
		sort( $expectedKeys );
		return $actual === $expectedKeys;
	}

	/** Nesne özelliğini CAST ETMEDEN okur; nesne/özellik yoksa `null` (sonraki katı kontrol reddeder). */
	private static function object_prop( $object, $property ) {
		return ( is_object( $object ) && isset( $object->$property ) ) ? $object->$property : null;
	}

	/** `strict_int()` + `> 0`; aksi hâlde `null` (cast ile kurtarma YOK). */
	private static function positive_int_or_null( $raw ) {
		$conv = self::strict_int( $raw );
		return ( $conv['ok'] && $conv['value'] > 0 ) ? $conv['value'] : null;
	}

	/** Doğal anahtar sorgusunda post'lar için taranan durumlar — çöp kutusu DAHİL (fail-closed: geri yüklenebilecek bir kaydın kopyası oluşturulmaz). */
	const NATURAL_KEY_POST_STATUSES = array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' );

	/** Ücret doğal anahtar adaylarının üst sınırı; aşılırsa sonuç güvenilir sayılmaz (query_error). */
	const NATURAL_KEY_FEE_CANDIDATE_CAP = 200;

	/**
	 * Faz 6B3 Önkoşul — SALT OKUNUR doğal anahtar preflight'ı. Yalnız
	 * WordPress okuma API'leri (get_terms/get_posts/get_*_meta); ham SQL,
	 * fuzzy/benzerlik eşleştirmesi YOK. Doğal anahtarlar:
	 * - sector        : mb_sektor term slug'ı (tam eşitlik)
	 * - qualification : _mb_myk_code (tam eşitlik; kod seviye/revizyonu zaten içerir)
	 * - fee           : _mb_sector_slug + _mb_level (tam eşitlik) VE
	 *                   profession_slug(_mb_profession_name) === source_key'deki meslek slug'ı
	 *                   (Faz 6A formülüyle aynı kural; benzerlik değil)
	 *
	 * @return string MaviBelge_Core_Import_Record_Validator::NATURAL_KEY_STATES'ten biri.
	 */
	private function natural_key_state( $type, $sourceKey ) {
		$key = self::natural_key_from_source_key( $type, $sourceKey );
		if ( null === $key ) {
			return 'query_error';
		}
		$ids = $this->natural_key_candidates( $type, $key );
		if ( null === $ids ) {
			return 'query_error';
		}
		if ( 0 === count( $ids ) ) {
			return 'none';
		}
		if ( count( $ids ) > 1 ) {
			return 'duplicate';
		}
		$markerRaw = MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR === $type
			? get_term_meta( $ids[0], '_mb_import_source_key', true )
			: get_post_meta( $ids[0], '_mb_import_source_key', true );
		return self::natural_key_state_from_marker( $markerRaw, $type, $sourceKey );
	}

	/** @return int[]|null Aday hedef ID'leri; güvenilir sorgu yapılamadıysa null. */
	private function natural_key_candidates( $type, array $key ) {
		switch ( $type ) {
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR:
				$raw = get_terms(
					array(
						'taxonomy'   => 'mb_sektor',
						'hide_empty' => false,
						'fields'     => 'ids',
						'slug'       => $key['slug'],
						'number'     => 5,
					)
				);
				break;
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_QUALIFICATION:
				$raw = get_posts(
					array(
						'post_type'      => 'mb_yeterlilik',
						'post_status'    => self::NATURAL_KEY_POST_STATUSES,
						'fields'         => 'ids',
						'meta_key'       => '_mb_myk_code',
						'meta_value'     => $key['code'],
						'posts_per_page' => 5,
						'no_found_rows'  => true,
						'orderby'        => 'ID',
						'order'          => 'ASC',
					)
				);
				break;
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FEE:
				$raw = get_posts(
					array(
						'post_type'      => 'mb_ucret',
						'post_status'    => self::NATURAL_KEY_POST_STATUSES,
						'fields'         => 'ids',
						'meta_query'     => array(
							'relation' => 'AND',
							array( 'key' => '_mb_sector_slug', 'value' => $key['sector_slug'] ),
							array( 'key' => '_mb_level', 'value' => $key['level'] ),
						),
						'posts_per_page' => self::NATURAL_KEY_FEE_CANDIDATE_CAP,
						'no_found_rows'  => true,
						'orderby'        => 'ID',
						'order'          => 'ASC',
					)
				);
				break;
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_NEWS:
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_REFERENCE:
				// Faz 7 doğal anahtar: post_name == slug (tam eşitlik, çöp DAHİL; bulanık eşleme YOK).
				// WordPress çöpe giden postun post_name'ine `__trashed` ekler ve asıl slug'ı
				// `_wp_desired_post_slug` metasında saklar; kullanıcının çöpteki kaydı bu yüzden ayrıca
				// bu metadan bulunur (çöp kaydı slug'ı hâlâ "tutar" -> create conflict olur).
				// Import'un KENDİ rollback kabuğu bu izi taşımaz (post_name önce boşaltılır).
				$postType = MaviBelge_Core_Import_Dry_Run_Planner::TYPE_NEWS === $type ? 'mb_haber' : 'mb_referans';
				$byName   = get_posts(
					array(
						'post_type'      => $postType,
						'post_status'    => self::NATURAL_KEY_POST_STATUSES,
						'fields'         => 'ids',
						'name'           => $key['slug'],
						'posts_per_page' => 5,
						'no_found_rows'  => true,
						'orderby'        => 'ID',
						'order'          => 'ASC',
					)
				);
				$byDesired = get_posts(
					array(
						'post_type'      => $postType,
						'post_status'    => 'trash',
						'fields'         => 'ids',
						'meta_key'       => '_wp_desired_post_slug',
						'meta_value'     => $key['slug'],
						'posts_per_page' => 5,
						'no_found_rows'  => true,
						'orderby'        => 'ID',
						'order'          => 'ASC',
					)
				);
				if ( is_wp_error( $byName ) || ! is_array( $byName ) || is_wp_error( $byDesired ) || ! is_array( $byDesired ) ) {
					return null;
				}
				$raw = array_merge( $byName, $byDesired );
				$seen = array();
				foreach ( $raw as $i => $candidateId ) {
					$intId = self::positive_int_or_null( $candidateId );
					if ( null === $intId || isset( $seen[ $intId ] ) ) {
						unset( $raw[ $i ] ); // Aynı post iki sorguda da görünebilir; bozuk ID aşağıda fail-closed olur.
						if ( null === $intId ) {
							return null;
						}
						continue;
					}
					$seen[ $intId ] = true;
				}
				$raw = array_values( $raw );
				break;
			default:
				return null;
		}
		if ( is_wp_error( $raw ) || ! is_array( $raw ) ) {
			return null;
		}
		$ids = array();
		foreach ( $raw as $id ) {
			$intId = self::positive_int_or_null( $id );
			if ( null === $intId ) {
				return null;
			}
			$ids[] = $intId;
		}
		if ( MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FEE === $type ) {
			if ( count( $ids ) >= self::NATURAL_KEY_FEE_CANDIDATE_CAP ) {
				return null; // Üst sınıra ulaşıldı — eksik tarama güvenilir değil.
			}
			$matched = array();
			foreach ( $ids as $id ) {
				$slug = MaviBelge_Core_Import_Record_Validator::profession_slug( get_post_meta( $id, '_mb_profession_name', true ) );
				if ( null !== $slug && $slug === $key['profession_slug'] ) {
					$matched[] = $id;
				}
			}
			return $matched;
		}
		return $ids;
	}

	/**
	 * Faz 6B3 Önkoşul — SAF: source_key'den doğal anahtarı ayrıştırır.
	 * Anahtar önce tek kanonik MaviBelge_Core_Validator::classify_import_source_key()
	 * ile VALID olmalı; aksi hâlde null (fail-closed).
	 *
	 * @return array|null sector: {slug}; qualification: {code}; fee: {sector_slug, level, profession_slug}; news/reference: {slug}
	 */
	public static function natural_key_from_source_key( $type, $sourceKey ) {
		if ( ! in_array( $type, MaviBelge_Core_Import_Managed_Fields::TYPES, true ) ) {
			return null;
		}
		if ( MaviBelge_Core_Validator::IMPORT_KEY_VALID !== MaviBelge_Core_Validator::classify_import_source_key( $sourceKey, $type ) ) {
			return null;
		}
		$rest = substr( $sourceKey, strlen( $type ) + 1 );
		switch ( $type ) {
			case 'sector':
			case 'news':
			case 'reference':
				return array( 'slug' => $rest );
			case 'qualification':
				return array( 'code' => $rest );
			default: // fee: <sector_slug>:<level>:<profession_slug>
				$parts = explode( ':', $rest );
				if ( 3 !== count( $parts ) ) {
					return null;
				}
				return array( 'sector_slug' => $parts[0], 'level' => $parts[1], 'profession_slug' => $parts[2] );
		}
	}

	/**
	 * Faz 6B3 Önkoşul — SAF: doğal anahtarla bulunan TEK hedefin ham
	 * `_mb_import_source_key` değerinden preflight durumu. Sınıflandırma tek
	 * kanonik MaviBelge_Core_Validator::classify_import_source_key() ile
	 * yapılır; ham değer CAST EDİLMEZ.
	 *
	 * @param mixed $markerRaw
	 * @return string NATURAL_KEY_STATES'ten biri (none/duplicate/query_error hariç).
	 */
	public static function natural_key_state_from_marker( $markerRaw, $type, $sourceKey ) {
		switch ( MaviBelge_Core_Validator::classify_import_source_key( $markerRaw, $type ) ) {
			case MaviBelge_Core_Validator::IMPORT_KEY_EMPTY:
				return 'unmanaged';
			case MaviBelge_Core_Validator::IMPORT_KEY_WRONG_PREFIX:
				return 'wrong_marker_prefix';
			case MaviBelge_Core_Validator::IMPORT_KEY_VALID:
				return $markerRaw === $sourceKey ? 'undiscovered_marker' : 'foreign_marker';
			default: // not_string, malformed
				return 'corrupt_marker';
		}
	}

	/**
	 * Faz 7 — `mb_haber_turu` teriminin (yalnız `haber`/`duyuru`) SALT OKUNUR çözümü.
	 * Terim OLUŞTURULMAZ; taksonomi `get_term_by()` ile doğrulanır (aynı slug'lı
	 * başka taksonomi terimi çözülmüş sayılmaz).
	 *
	 * @inheritDoc
	 */
	public function resolve_news_type_term_id( $typeSlug ): ?array {
		if ( ! is_string( $typeSlug ) || ! in_array( $typeSlug, MaviBelge_Core_Import_Record_Validator::NEWS_TYPES, true ) ) {
			return null;
		}
		$term = get_term_by( 'slug', $typeSlug, 'mb_haber_turu' );
		if ( ! $term || is_wp_error( $term ) || 'mb_haber_turu' !== self::object_prop( $term, 'taxonomy' ) ) {
			return null;
		}
		$termId = self::positive_int_or_null( self::object_prop( $term, 'term_id' ) );
		if ( null === $termId ) {
			return null;
		}
		return array( 'id' => $termId, 'type_verified' => true );
	}

	/** @inheritDoc */
	public function resolve_sector_term_id( $sectorSlug ): ?array {
		if ( ! is_string( $sectorSlug ) || '' === $sectorSlug ) {
			return null;
		}
		$term = get_term_by( 'slug', $sectorSlug, 'mb_sektor' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$termId = self::positive_int_or_null( self::object_prop( $term, 'term_id' ) );
		if ( null === $termId ) {
			return null;
		}
		return array( 'id' => $termId, 'type_verified' => true );
	}

	/** @inheritDoc */
	public function resolve_qualification_post_id( $mykCode ): ?array {
		if ( ! is_string( $mykCode ) || '' === $mykCode ) {
			return null;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'mb_yeterlilik',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_key'       => '_mb_myk_code',
				'meta_value'     => $mykCode,
				'posts_per_page' => 3,
				'no_found_rows'  => true,
			)
		);
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return null;
		}
		if ( count( $ids ) > 1 ) {
			// §5.2 — birden fazla eşleşmede ilkini KEYFÎ seçmek yok: `null`
			// dön + diagnostic kaydet (Faz 6B2'nin açık kararı, bkz.
			// faz6b2-entegrasyon-notu.md).
			$this->diagnostics[] = array(
				'code'       => 'ambiguous_qualification_dependency',
				'type'       => MaviBelge_Core_Import_Dry_Run_Planner::TYPE_QUALIFICATION,
				'source_key' => 'qualification:' . $mykCode,
			);
			return null;
		}
		$postId = self::positive_int_or_null( reset( $ids ) );
		if ( null === $postId || 'mb_yeterlilik' !== get_post_type( $postId ) ) {
			return null;
		}
		return array( 'id' => $postId, 'type_verified' => true );
	}

	/** @inheritDoc */
	public function resolve_sector_image_attachment_id( $sectorSlug ): ?array {
		if ( ! is_string( $sectorSlug ) || '' === $sectorSlug ) {
			return null;
		}
		$term = get_term_by( 'slug', $sectorSlug, 'mb_sektor' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$termId = self::positive_int_or_null( self::object_prop( $term, 'term_id' ) );
		if ( null === $termId ) {
			return null;
		}
		$attachmentConv = self::strict_nonneg_int_or_empty_zero( get_term_meta( $termId, '_mb_image_attachment_id', true ) );
		if ( ! $attachmentConv['ok'] || $attachmentConv['value'] <= 0 ) {
			// Term-meta boş/0/biçimsiz -> "kaynakta görsel var ama henüz
			// eşlenmedi" (kaynakta görsel yoksa planlayıcı bu resolver'ı
			// hiç çağırmaz — bkz. class-import-dry-run-planner.php::plan_sector()).
			return null;
		}
		$attachmentId = $attachmentConv['value'];
		if ( 'attachment' !== get_post_type( $attachmentId ) ) {
			// Dosya adı/benzerlik TAHMİNİ YOK — yalnız açık term-meta
			// ilişkisi + gerçek attachment post_type doğrulaması.
			return null;
		}
		return array( 'id' => $attachmentId, 'type_verified' => true );
	}

	/**
	 * §2.2 — "başarı + değer" ayrımını koruyan katı tam sayı dönüştürücü.
	 * "meta yoksa 0" sözleşmesi TAŞIMAZ (level gibi zorunlu alanlar için) —
	 * boş string BAŞARISIZ sayılır. Bkz. `strict_nonneg_int_or_empty_zero()`
	 * opsiyonel/varsayılan-0 alanlar için.
	 *
	 * BİLEREK `public static` — HİÇBİR WordPress fonksiyonu çağırmaz,
	 * `tests/bootstrap.php`'nin bu sınıfı standalone yükleyip YALNIZ bu
	 * yardımcıları test edebilmesini sağlar.
	 *
	 * @return array{ok: bool, value?: int}
	 */
	public static function strict_int( $raw ) {
		if ( is_int( $raw ) ) {
			return array( 'ok' => true, 'value' => $raw );
		}
		// Son Kapanış §4 — string yolu YALNIZ ortak, taşma-güvenli
		// ayrıştırıcıdan geçer; burada doğrudan cast YOKTUR.
		return self::parse_canonical_decimal_int( $raw );
	}

	/**
	 * §2.2 — WordPress'in "meta yoksa boş string döner" davranışını TEK
	 * meşru "eksik -> 0" durumu sayan katı nonnegative-int dönüştürücü.
	 * Negatif tam sayı, ondalık, bilimsel gösterim, kısmen sayısal string
	 * ("1x", "123abc"), dizi/nesne/bool/float HİÇBİRİ 0'a DÜŞMEZ —
	 * başarısız sayılır.
	 *
	 * @return array{ok: bool, value?: int}
	 */
	public static function strict_nonneg_int_or_empty_zero( $raw ) {
		if ( is_int( $raw ) ) {
			return $raw >= 0 ? array( 'ok' => true, 'value' => $raw ) : array( 'ok' => false );
		}
		if ( is_string( $raw ) ) {
			if ( '' === $raw ) {
				return array( 'ok' => true, 'value' => 0 );
			}
			// Son Kapanış §4 — strict_int() ile AYNI tek ayrıştırıcı; negatif
			// sonuç burada ayrıca reddedilir.
			$parsed = self::parse_canonical_decimal_int( $raw );
			if ( ! $parsed['ok'] || $parsed['value'] < 0 ) {
				return array( 'ok' => false );
			}
			return $parsed;
		}
		return array( 'ok' => false ); // array/object/bool/float/null.
	}

	/**
	 * Son Kapanış §4 — TEK, saf, PHP 7.3 uyumlu onluk-string -> int
	 * ayrıştırıcı. Hem `strict_int()` hem `strict_nonneg_int_or_empty_zero()`
	 * bunu kullanır (iki ayrı taşma algoritması YOK).
	 *
	 * Kabul edilen biçim: `^-?(0|[1-9][0-9]*)\z` —
	 * - baş/son boşluk, `+` işareti, ondalık, bilimsel gösterim, sondaki
	 *   satır sonu (desen `\z` ile biter, satır-sonu-toleranslı dolar
	 *   çapası KULLANILMAZ) ve kısmen sayısal string reddedilir;
	 * - leading zero politikası: yalnız tek başına "0" kabul; "007"/"00"
	 *   reddedilir (kanonik olmayan biçim, deterministik ret);
	 * - negatif sıfır ("-0") reddedilir (kanonik değil).
	 *
	 * Taşma: cast'ten ÖNCE, float KULLANILMADAN basamak-string'i platformun
	 * gerçek `PHP_INT_MAX` (pozitif) veya `PHP_INT_MIN` (negatif, işaretsiz
	 * büyüklük) değerinin string temsiliyle önce uzunluk, eşit uzunlukta
	 * `strcmp()` ile karşılaştırılır (kanonik biçimde leksikografik sıra =
	 * sayısal sıra). Sınırı aşan değer `ok=false`. Cast SONRASI ayrıca
	 * `(string) $value === $raw` round-trip kontrolü yapılır.
	 *
	 * Faz 6B3 Önkoşul: algoritma, kanonik kuruş kuralıyla PAYLAŞILMAK üzere
	 * `MaviBelge_Core_Validator::parse_canonical_decimal_int()`'e taşındı;
	 * bu metot yalnız ona devreder (iki ayrı taşma algoritması YOK).
	 *
	 * @param mixed $raw
	 * @return array{ok: bool, value?: int}
	 */
	private static function parse_canonical_decimal_int( $raw ) {
		return MaviBelge_Core_Validator::parse_canonical_decimal_int( $raw );
	}

	/**
	 * Son Kapanış §3.2 — katı string doğrulayıcı. YALNIZ gerçek PHP string
	 * kabul edilir (boş string dahil — alan zorunluluğunu sonraki
	 * validator belirler). array/object/resource/bool/int/float/null
	 * reddedilir; hiçbir değer cast/sanitize ile "kurtarılmaz".
	 *
	 * @return array{ok: bool, value?: string}
	 */
	public static function strict_string( $raw ) {
		if ( is_string( $raw ) ) {
			return array( 'ok' => true, 'value' => $raw );
		}
		return array( 'ok' => false );
	}

	/**
	 * §2.2 — yalnız WordPress'in belgelenmiş/fiilî meta bool temsillerini
	 * (`true`/`false`, `1`/`0`, `'1'`/`'0'`, boş string -> `false`) kabul
	 * eder. `'false'`, `'yes'`, `'banana'`, dizi/nesne dahil başka HİÇBİR
	 * string/tip kabul EDİLMEZ.
	 *
	 * @return array{ok: bool, value?: bool}
	 */
	public static function strict_bool( $raw ) {
		if ( is_bool( $raw ) ) {
			return array( 'ok' => true, 'value' => $raw );
		}
		if ( 1 === $raw || '1' === $raw ) {
			return array( 'ok' => true, 'value' => true );
		}
		if ( 0 === $raw || '0' === $raw || '' === $raw ) {
			return array( 'ok' => true, 'value' => false );
		}
		return array( 'ok' => false );
	}
}
