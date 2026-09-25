<?php
/**
 * Faz 6B3 — `MaviBelge_Core_Import_Target_Writer`'ın WordPress uygulaması.
 *
 * YALNIZ WordPress API'leri kullanılır (wp_insert_term/wp_update_term,
 * wp_insert_post/wp_update_post, update_term_meta/update_post_meta,
 * wp_set_object_terms, wp_trash_post, wp_delete_term). İçerik/meta için ham
 * SQL YOKTUR. WordPress'in meta/post/term fonksiyonları girdiyi
 * `wp_unslash()` ettiği için bütün değerler `wp_slash()` ile verilir.
 *
 * Her yazılan değer, saklanan temsil üzerinden KATI olarak geri okunur
 * (`stored_equals()`): `update_*_meta()`'nın `false` dönüşü tek başına hata
 * sayılmaz ("değer zaten aynıydı" olabilir); yalnız okunan değer beklenen
 * kanonik değere eşit değilse hata sayılır. Yalnız yükte bulunan (import
 * tarafından yönetilen) alanlar yazılır; yönetilmeyen alanlar için
 * `unmanaged_fingerprint()` sağlanır.
 *
 * Bu sınıf medya oluşturmaz, e-posta göndermez, dış HTTP çağırmaz ve
 * `mb_active_tariff_period` seçeneğine dokunmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_WordPress_Target_Writer implements MaviBelge_Core_Import_Target_Writer {

	const POST_TYPES = array(
		'qualification' => 'mb_yeterlilik',
		'fee'           => 'mb_ucret',
		'news'          => 'mb_haber',
		'reference'     => 'mb_referans',
	);

	/**
	 * Faz 7 — türe göre YÖNETİLEN çekirdek post alanları (yalnız doğrulanmış yükten
	 * yazılır ve okunur). `post_title` her türde yönetilir (POST_FIELDS_EXCLUDED).
	 * Bu alanlar `unmanaged_fingerprint()`'a GİRMEZ: yönetilen alanların yeniden
	 * uygulanması/rollback'i drift sayılmaz; yönetilmeyen her düzenleme sayılır.
	 * Haber: post_name/post_content/post_excerpt/post_date. Referans: post_name.
	 */
	const MANAGED_CORE_FIELDS = array(
		'news'      => array( 'post_name', 'post_content', 'post_excerpt', 'post_date' ),
		'reference' => array( 'post_name' ),
	);

	/** Türe göre YÖNETİLEN taksonomiler (parmak izine girmez; yalnız yükteki ilişki yazılır). */
	const MANAGED_TAXONOMIES = array(
		'qualification' => array( 'mb_sektor' ),
		'news'          => array( 'mb_haber_turu' ),
	);

	/**
	 * Post parmak izine GİRMEYEN çekirdek alanlar: yönetilen başlık + WordPress'in kendi yönettiği alanlar
	 * (değişiklik zamanları ve `guid` — wp_update_post() `&` karakterini `&#038;` yapar; kullanıcı kaydetmese
	 * bile). Taslağın tarih alanları (`post_date`/`post_date_gmt`) tarih hiç girilmediği (gmt = sıfır) sürece
	 * WordPress tarafından her güncellemede yenilenir; bu durumda da dışlanır.
	 */
	const POST_FIELDS_EXCLUDED = array( 'post_title', 'post_modified', 'post_modified_gmt', 'guid', 'filter' );

	const ZERO_GMT_DATE = '0000-00-00 00:00:00';

	public function create_sector( array $payload ) {
		if ( ! self::is_sector_payload( $payload ) ) {
			return self::fail( 'payload_shape' );
		}
		$t      = $payload['term'];
		$result = wp_insert_term( wp_slash( $t['name'] ), MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY, array( 'slug' => $t['slug'], 'description' => wp_slash( $t['description'] ) ) );
		if ( is_wp_error( $result ) || ! is_array( $result ) || ! isset( $result['term_id'] ) ) {
			return self::fail( 'term_insert_failed' );
		}
		$termId = (int) $result['term_id'];
		if ( $termId <= 0 ) {
			return self::fail( 'term_insert_failed' );
		}
		return $this->finish_sector( $termId, $payload );
	}

	public function update_sector( $termId, array $payload ) {
		if ( ! self::is_sector_payload( $payload ) || ! is_int( $termId ) || $termId <= 0 || ! ( get_term( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY ) instanceof WP_Term ) ) {
			return self::fail( 'term_missing' );
		}
		$t      = $payload['term'];
		$result = wp_update_term( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY, array( 'name' => wp_slash( $t['name'] ), 'slug' => $t['slug'], 'description' => wp_slash( $t['description'] ) ) );
		if ( is_wp_error( $result ) ) {
			return self::fail( 'term_update_failed' );
		}
		return $this->finish_sector( $termId, $payload );
	}

	private function finish_sector( $termId, array $payload ) {
		clean_term_cache( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY );
		$term = get_term( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY );
		$t    = $payload['term'];
		if ( ! ( $term instanceof WP_Term ) || $term->slug !== $t['slug'] || $term->name !== $t['name'] || $term->description !== $t['description'] ) {
			return self::fail( 'term_readback_mismatch' );
		}
		foreach ( $payload['term_meta'] as $key => $value ) {
			update_term_meta( $termId, $key, wp_slash( $value ) );
			if ( ! self::stored_equals( $value, get_term_meta( $termId, $key, true ) ) ) {
				return self::fail( 'meta_readback_mismatch' );
			}
		}
		return self::ok( $termId );
	}

	public function create_post( array $payload ) {
		$postType = self::payload_post_type( $payload );
		if ( null === $postType ) {
			return self::fail( 'payload_shape' );
		}
		// Post HER ZAMAN taslak açılır (asla publish/pending). Yönetilen çekirdek alanlar
		// (haber: ad/içerik/özet/tarih; referans: ad) YALNIZ doğrulanmış yükten gelir; diğer
		// türlerde içerik/özet boş kalır. post_date_gmt VERİLMEZ: WordPress taslakta sıfır tutar
		// ve açık post_date'i korur (readback ve runtime testiyle doğrulanır).
		$insert = array(
			'post_type'    => $postType,
			'post_title'   => $payload['post']['post_title'],
			'post_status'  => 'draft',
			'post_content' => '',
			'post_excerpt' => '',
		);
		foreach ( self::managed_core_values( $payload ) as $field => $value ) {
			$insert[ $field ] = $value;
		}
		$postId = wp_insert_post( wp_slash( $insert ), true );
		if ( is_wp_error( $postId ) || ! is_int( $postId ) || $postId <= 0 ) {
			return self::fail( 'post_insert_failed' );
		}
		return $this->finish_post( $postId, $postType, $payload );
	}

	public function update_post( $postId, array $payload ) {
		$postType = self::payload_post_type( $payload );
		if ( null === $postType || ! is_int( $postId ) || $postId <= 0 ) {
			return self::fail( 'payload_shape' );
		}
		$post = get_post( $postId );
		if ( ! ( $post instanceof WP_Post ) || $post->post_type !== $postType || 'trash' === $post->post_status ) {
			return self::fail( 'post_missing' );
		}
		$wanted = array_merge( array( 'post_title' => $payload['post']['post_title'] ), self::managed_core_values( $payload ) );
		$diff   = array();
		foreach ( $wanted as $field => $value ) {
			if ( $post->$field !== $value ) {
				$diff[ $field ] = $value;
			}
		}
		if ( ! empty( $diff ) ) {
			// Yalnız DEĞİŞEN yönetilen alanlar verilerek güncellenir; post_status ASLA değiştirilmez.
			$args = array_merge( array( 'ID' => $postId ), $diff );
			if ( isset( $wanted['post_date'] ) ) {
				// `edit_date` olmadan wp_update_post() taslağın tarihini "şimdi"ye sıfırlar (yalnız başlık
				// değişse bile); yönetilen tarih HER güncellemede açıkça verilir.
				$args['post_date'] = $wanted['post_date'];
				$args['edit_date'] = true;
			}
			$result = wp_update_post( wp_slash( $args ), true );
			if ( is_wp_error( $result ) || $result !== $postId ) {
				return self::fail( 'post_update_failed' );
			}
		}
		return $this->finish_post( $postId, $postType, $payload );
	}

	private function finish_post( $postId, $postType, array $payload ) {
		clean_post_cache( $postId );
		$post = get_post( $postId );
		if ( ! ( $post instanceof WP_Post ) || $post->post_type !== $postType || $post->post_title !== $payload['post']['post_title'] ) {
			return self::fail( 'post_readback_mismatch' );
		}
		foreach ( self::managed_core_values( $payload ) as $field => $value ) {
			if ( $post->$field !== $value ) {
				return self::fail( 'post_readback_mismatch' );
			}
		}
		if ( isset( $payload['post']['post_date'] ) && self::ZERO_GMT_DATE !== $post->post_date_gmt ) {
			return self::fail( 'post_readback_mismatch' ); // Taslak tarihi floating kalmalı (post_date_gmt sıfır).
		}
		foreach ( $payload['post_meta'] as $key => $value ) {
			update_post_meta( $postId, $key, wp_slash( $value ) );
			if ( ! self::stored_equals( $value, get_post_meta( $postId, $key, true ) ) ) {
				return self::fail( 'meta_readback_mismatch' );
			}
		}
		if ( isset( $payload['terms'] ) ) {
			foreach ( $payload['terms'] as $taxonomy => $termIds ) {
				if ( ! in_array( $taxonomy, self::managed_taxonomies_for_post_type( $postType ), true ) ) {
					return self::fail( 'payload_shape' );
				}
				$set = wp_set_object_terms( $postId, $termIds, $taxonomy, false );
				if ( is_wp_error( $set ) ) {
					return self::fail( 'terms_write_failed' );
				}
				$got = wp_get_object_terms( $postId, $taxonomy, array( 'fields' => 'ids' ) );
				if ( is_wp_error( $got ) || ! is_array( $got ) ) {
					return self::fail( 'terms_readback_mismatch' );
				}
				$got      = array_map( 'intval', $got );
				$expected = $termIds;
				sort( $got );
				sort( $expected );
				if ( $got !== $expected ) {
					return self::fail( 'terms_readback_mismatch' );
				}
			}
		}
		return self::ok( $postId );
	}

	public function rollback_created_post( $postId, $postType, $expectedFingerprint ) {
		if ( defined( 'EMPTY_TRASH_DAYS' ) && ! EMPTY_TRASH_DAYS ) {
			// EMPTY_TRASH_DAYS=0 iken wp_trash_post() KALICI siler; rollback kalıcı silme yapmaz.
			return self::fail( 'trash_disabled' );
		}
		$type = array_search( $postType, self::POST_TYPES, true );
		$post = is_int( $postId ) && $postId > 0 ? get_post( $postId ) : null;
		if ( false === $type || ! ( $post instanceof WP_Post ) || $post->post_type !== $postType || 'trash' === $post->post_status ) {
			return self::fail( 'post_missing' );
		}
		if ( ! self::is_fingerprint( $expectedFingerprint ) || $this->unmanaged_fingerprint( $type, $postId ) !== $expectedFingerprint ) {
			return self::fail( 'drift_detected' );
		}
		// Yalnız import tarafından yönetilen alanları kaldır: marker + doğal anahtar alanları
		// çöp kutusundaki kabuk kayıtta kalmasın (aynı manifest yeniden uygulanabilsin).
		foreach ( MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META[ $type ] as $key ) {
			delete_post_meta( $postId, $key );
		}
		foreach ( self::MANAGED_TAXONOMIES[ $type ] ?? array() as $taxonomy ) {
			$cleared = wp_set_object_terms( $postId, array(), $taxonomy, false );
			if ( is_wp_error( $cleared ) ) {
				return self::fail( 'terms_write_failed' );
			}
		}
		clean_post_cache( $postId );
		foreach ( MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META[ $type ] as $key ) {
			if ( metadata_exists( 'post', $postId, $key ) ) {
				return self::fail( 'managed_meta_not_cleared' );
			}
		}
		foreach ( self::MANAGED_TAXONOMIES[ $type ] ?? array() as $taxonomy ) {
			$left = wp_get_object_terms( $postId, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $left ) || ! is_array( $left ) || array() !== $left ) {
				return self::fail( 'managed_terms_not_cleared' );
			}
		}
		if ( $this->unmanaged_fingerprint( $type, $postId ) !== $expectedFingerprint ) {
			return self::fail( 'unmanaged_field_changed' );
		}
		// post_name DOĞAL ANAHTARDIR: çöpe giden kabuk slug'ı tutmasın (aynı manifest yeniden
		// uygulanabilsin). Taslakta boş post_name geçerlidir; wp_trash_post() sonra kendi
		// `__trashed` sonekini ekler. Sonuç readback ile doğrulanır (slug'a EŞİT olmamalı).
		$slug = isset( self::MANAGED_CORE_FIELDS[ $type ] ) && in_array( 'post_name', self::MANAGED_CORE_FIELDS[ $type ], true ) ? $post->post_name : null;
		if ( null !== $slug && '' !== $slug ) {
			$blank = wp_update_post( wp_slash( array( 'ID' => $postId, 'post_name' => '', 'edit_date' => true ) ), true );
			if ( is_wp_error( $blank ) || $blank !== $postId ) {
				return self::fail( 'post_name_clear_failed' );
			}
			clean_post_cache( $postId );
			if ( $this->unmanaged_fingerprint( $type, $postId ) !== $expectedFingerprint ) {
				return self::fail( 'unmanaged_field_changed' );
			}
		}
		if ( ! wp_trash_post( $postId ) ) {
			return self::fail( 'trash_failed' );
		}
		clean_post_cache( $postId );
		if ( 'trash' !== get_post_status( $postId ) ) {
			return self::fail( 'trash_readback_mismatch' );
		}
		if ( null !== $slug && '' !== $slug ) {
			$trashed = get_post( $postId );
			if ( ! ( $trashed instanceof WP_Post ) || $trashed->post_name === $slug ) {
				return self::fail( 'post_name_not_released' );
			}
		}
		return self::ok( $postId );
	}

	public function delete_sector_term( $termId, $expectedFingerprint ) {
		if ( ! is_int( $termId ) || $termId <= 0 || ! ( get_term( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY ) instanceof WP_Term ) ) {
			return self::fail( 'term_missing' );
		}
		if ( ! self::is_fingerprint( $expectedFingerprint ) || $this->unmanaged_fingerprint( 'sector', $termId ) !== $expectedFingerprint ) {
			return self::fail( 'drift_detected' );
		}
		if ( true !== wp_delete_term( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY ) ) {
			return self::fail( 'term_delete_failed' );
		}
		clean_term_cache( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY );
		if ( null !== get_term( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY ) ) {
			return self::fail( 'term_delete_readback_mismatch' );
		}
		return self::ok( $termId );
	}

	private static function is_fingerprint( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', $value );
	}

	public function unmanaged_fingerprint( $type, $id ) {
		if ( ! is_int( $id ) || $id <= 0 ) {
			return null;
		}
		if ( 'sector' === $type ) {
			$term = get_term( $id, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY );
			if ( ! ( $term instanceof WP_Term ) ) {
				return null;
			}
			$meta = get_term_meta( $id );
			if ( ! is_array( $meta ) ) {
				return null;
			}
			foreach ( array_keys( MaviBelge_Core_Taxonomies::sector_term_meta_contract() ) as $key ) {
				unset( $meta[ $key ] );
			}
			ksort( $meta );
			return hash( 'sha256', serialize( array( 'parent' => (int) $term->parent, 'term_group' => (int) $term->term_group, 'meta' => $meta ) ) );
		}
		if ( ! isset( self::POST_TYPES[ $type ] ) ) {
			return null;
		}
		$post = get_post( $id, ARRAY_A );
		if ( ! is_array( $post ) || self::POST_TYPES[ $type ] !== $post['post_type'] ) {
			return null;
		}
		foreach ( self::POST_FIELDS_EXCLUDED as $field ) {
			unset( $post[ $field ] );
		}
		// Bu türün YÖNETİLEN çekirdek alanları (haber: ad/içerik/özet/tarih; referans: ad) drift değildir.
		foreach ( isset( self::MANAGED_CORE_FIELDS[ $type ] ) ? self::MANAGED_CORE_FIELDS[ $type ] : array() as $field ) {
			unset( $post[ $field ] );
		}
		if ( isset( $post['post_date_gmt'] ) && self::ZERO_GMT_DATE === $post['post_date_gmt'] ) {
			unset( $post['post_date'] ); // Kullanıcı tarih girmemiş: WordPress otomatik yönetir.
		}
		ksort( $post );
		$meta = get_post_meta( $id );
		if ( ! is_array( $meta ) ) {
			return null;
		}
		foreach ( MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META[ $type ] as $key ) {
			unset( $meta[ $key ] );
		}
		ksort( $meta );
		$terms = array();
		foreach ( get_object_taxonomies( self::POST_TYPES[ $type ] ) as $taxonomy ) {
			if ( in_array( $taxonomy, isset( self::MANAGED_TAXONOMIES[ $type ] ) ? self::MANAGED_TAXONOMIES[ $type ] : array(), true ) ) {
				continue; // Yönetilen alan (qualification: sector_term_id; news: news_type_term_id).
			}
			$ids = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $ids ) || ! is_array( $ids ) ) {
				return null;
			}
			$ids = array_map( 'intval', $ids );
			if ( array() === $ids ) {
				continue; // Boş atama parmak izine girmez: sonradan kaydedilen taksonomiler sahte drift üretmesin.
			}
			sort( $ids );
			$terms[ $taxonomy ] = $ids;
		}
		ksort( $terms );
		return hash( 'sha256', serialize( array( 'post' => $post, 'meta' => $meta, 'terms' => $terms ) ) );
	}

	public function sector_term_references( $termId ) {
		$none = array( 'ok' => false, 'slug' => null, 'object_ids' => array(), 'fee_ids' => array(), 'trashed_ids' => array(), 'child_count' => 0 );
		$term = is_int( $termId ) && $termId > 0 ? get_term( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY ) : null;
		if ( ! ( $term instanceof WP_Term ) ) {
			return $none;
		}
		$objects = get_objects_in_term( $termId, MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY );
		if ( is_wp_error( $objects ) || ! is_array( $objects ) ) {
			return $none;
		}
		$fees = get_posts(
			array(
				'post_type'        => self::POST_TYPES['fee'],
				'post_status'      => array_keys( get_post_stati() ),
				'fields'           => 'ids',
				'meta_key'         => '_mb_sector_slug',
				'meta_value'       => $term->slug,
				'posts_per_page'   => 500,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		if ( ! is_array( $fees ) || count( $fees ) >= 500 ) {
			return $none; // Tam tarama güvenilir değil: fail-closed.
		}
		$children = get_terms(
			array(
				'taxonomy'   => MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY,
				'parent'     => $termId,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $children ) || ! is_array( $children ) ) {
			return $none;
		}
		$objectIds = array_map( 'intval', $objects );
		$feeIds    = array_map( 'intval', $fees );
		$trashed   = array();
		foreach ( array_unique( array_merge( $objectIds, $feeIds ) ) as $postId ) {
			if ( 'trash' === get_post_status( $postId ) ) {
				$trashed[] = $postId;
			}
		}
		return array(
			'ok'          => true,
			'slug'        => $term->slug,
			'object_ids'  => $objectIds,
			'fee_ids'     => $feeIds,
			'trashed_ids' => $trashed,
			'child_count' => count( $children ),
		);
	}

	/**
	 * WordPress'in sakladığı temsil ile beklenen kanonik değerin KATI
	 * karşılaştırması: string -> aynı string; int -> aynı int veya onun
	 * kanonik onluk string'i; bool -> '1' / '' (veya 1/0, true/false);
	 * dizi -> birebir aynı dizi (unserialize sonrası tipler korunur).
	 */
	public static function stored_equals( $expected, $stored ) {
		if ( is_string( $expected ) ) {
			return $stored === $expected;
		}
		if ( is_int( $expected ) ) {
			return $stored === $expected || $stored === (string) $expected;
		}
		if ( is_bool( $expected ) ) {
			return $expected ? in_array( $stored, array( '1', 1, true ), true ) : in_array( $stored, array( '', '0', 0, false ), true );
		}
		if ( is_array( $expected ) ) {
			return $stored === $expected;
		}
		return false;
	}

	private static function is_sector_payload( array $payload ) {
		return isset( $payload['type'], $payload['term'], $payload['term_meta'] ) && 'sector' === $payload['type'] && is_array( $payload['term'] ) && is_array( $payload['term_meta'] )
			&& isset( $payload['term']['taxonomy'], $payload['term']['slug'], $payload['term']['name'], $payload['term']['description'] )
			&& MaviBelge_Core_Taxonomies::SECTOR_TAXONOMY === $payload['term']['taxonomy']
			&& array() === array_diff( array_keys( $payload['term_meta'] ), array_keys( MaviBelge_Core_Taxonomies::sector_term_meta_contract() ) );
	}

	/** @return string[] Bu post türünde import'un yönettiği taksonomiler. */
	private static function managed_taxonomies_for_post_type( $postType ) {
		$type = array_search( $postType, self::POST_TYPES, true );
		return false !== $type && isset( self::MANAGED_TAXONOMIES[ $type ] ) ? self::MANAGED_TAXONOMIES[ $type ] : array();
	}

	/**
	 * Yükteki yönetilen çekirdek post alanları (yalnız türün MANAGED_CORE_FIELDS listesindekiler,
	 * yalnız yükte verilenler). Yük şekli payload_post_type() ile ÖNCEDEN doğrulanmıştır.
	 *
	 * @return array<string, string>
	 */
	private static function managed_core_values( array $payload ) {
		$out  = array();
		$type = isset( $payload['type'] ) ? $payload['type'] : null;
		foreach ( isset( self::MANAGED_CORE_FIELDS[ $type ] ) ? self::MANAGED_CORE_FIELDS[ $type ] : array() as $field ) {
			if ( isset( $payload['post'][ $field ] ) && is_string( $payload['post'][ $field ] ) ) {
				$out[ $field ] = $payload['post'][ $field ];
			}
		}
		return $out;
	}

	/** @return string|null */
	private static function payload_post_type( array $payload ) {
		if ( ! isset( $payload['type'], $payload['post'], $payload['post_meta'] ) || ! isset( self::POST_TYPES[ $payload['type'] ] ) || ! is_array( $payload['post'] ) || ! is_array( $payload['post_meta'] ) ) {
			return null;
		}
		$postType = self::POST_TYPES[ $payload['type'] ];
		if ( ! isset( $payload['post']['post_type'], $payload['post']['post_title'] ) || $postType !== $payload['post']['post_type'] || ! is_string( $payload['post']['post_title'] ) ) {
			return null;
		}
		if ( array() !== array_diff( array_keys( $payload['post_meta'] ), MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META[ $payload['type'] ] ) ) {
			return null; // Yönetilen liste dışında tek bir meta anahtarı bile yazılmaz.
		}
		// Yükteki post alanları YALNIZ post_type/post_title + türün yönetilen çekirdek alanları olabilir
		// (haber: post_name/post_content/post_excerpt/post_date; referans: post_name).
		$allowedPost = array_merge( array( 'post_type', 'post_title' ), isset( self::MANAGED_CORE_FIELDS[ $payload['type'] ] ) ? self::MANAGED_CORE_FIELDS[ $payload['type'] ] : array() );
		if ( array() !== array_diff( array_keys( $payload['post'] ), $allowedPost ) ) {
			return null;
		}
		if ( isset( $payload['terms'] ) ) {
			if ( ! is_array( $payload['terms'] ) || array() !== array_diff( array_keys( $payload['terms'] ), isset( self::MANAGED_TAXONOMIES[ $payload['type'] ] ) ? self::MANAGED_TAXONOMIES[ $payload['type'] ] : array() ) ) {
				return null;
			}
		}
		return $postType;
	}

	private static function ok( $id ) {
		return array( 'ok' => true, 'id' => $id, 'error' => null );
	}

	private static function fail( $code ) {
		return array( 'ok' => false, 'id' => null, 'error' => $code );
	}
}
