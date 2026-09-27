<?php
/**
 * Faz 6B4 — sektör görsel eşlemesi (açık, elle onaylanan, WordPress'çe doğrulanan).
 *
 * Neden var: görselli 10 sektör, kaynak manifestte yalnız bir görsel YOLU taşır; bir WordPress attachment
 * ID'si taşımaz ve ID'ler ortamdan ortama farklıdır (Git'e/manifeste girmez). Dry-run bu yüzden görselli
 * sektörleri `blocked_dependency` bırakır. Bu sınıf, yetkili bir kullanıcının Ortam Kütüphanesinden SEÇTİĞİ
 * eşlemeyi kapalı, sürümlü bir option zarfında saklar ve doğrular. Dosya adına/benzerliğe göre TAHMİN yoktur.
 *
 * Kalıcı biçim — option `mavibelge_core_sector_image_map` (autoload=no):
 *   { schema_version: "1.0.0", manifest_digest: <64-hex>, mappings: { <sektör-slug>: <pozitif attachment ID> } }
 *
 * Doğrulama (SAF çekirdek: `validate_envelope()`; WordPress fonksiyonu çağırmaz, attachment inceleyicisi ENJEKTE edilir):
 * - kapalı üst anahtar kümesi, sürüm ve güncel manifest digest'i BİREBİR eşleşmeli (manifest değişince eski map reddedilir);
 * - `mappings` anahtar kümesi, manifestte `image` alanı dolu sektör slug'larının kümesine TAM eşit olmalı
 *   (eksik ve fazla slug reddedilir); gereksinim ikinci bir listeden değil, manifest kayıtlarından türetilir;
 * - her ID GERÇEK pozitif integer olmalı (string/float/bool cast edilmez);
 * - attachment gerçek `attachment` olmalı, çöpte olmamalı, MIME `image/*` olmalı, dosyası okunabilmeli;
 * - aynı attachment birden çok slug'a YALNIZ bu slug'ların manifestteki kaynak görsel yolu aynıysa atanabilir.
 *
 * Kayıt (`save()`) WordPress'e bağlıdır; yalnız `validate_envelope()`'tan geçen map yazılır ve değişiklik audit'e
 * (eski/yeni map digest'i + değişen slug adları; dosya yolu veya alan içeriği YOK) yazılır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Sector_Image_Map {

	const OPTION         = 'mavibelge_core_sector_image_map';
	const SCHEMA_VERSION = '1.0.0';
	const TOP_KEYS       = array( 'schema_version', 'manifest_digest', 'mappings' );
	const DIGEST_SCHEMA  = 'mavibelge-sector-image-map-digest/1';

	/** Kabul edilen attachment durumları (`trash` ve diğerleri reddedilir). */
	const ATTACHMENT_STATUSES = array( 'inherit', 'private', 'publish' );

	/**
	 * Manifestin görselli sektörleri: slug => kaynak görsel yolu. Görselsiz (boş `image`) sektör yoktur.
	 *
	 * @param array $sectorRecords Yüklenmiş sektör manifest kayıtları.
	 * @return array<string, string>
	 */
	public static function required_image_sources( array $sectorRecords ) {
		$out = array();
		foreach ( $sectorRecords as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['slug'], $record['image'] ) || ! is_string( $record['slug'] ) || '' === $record['slug'] || ! is_string( $record['image'] ) || '' === $record['image'] ) {
				continue;
			}
			$out[ $record['slug'] ] = $record['image'];
		}
		return $out;
	}

	/**
	 * Map'in bağlandığı manifest özeti: `sectors.manifest.json` KAYITLARININ deterministik hash'i (map yalnız
	 * sektör kayıtlarına — slug ve `image` — bağlıdır; ücret/yeterlilik değişimi map'i geçersiz KILMAZ,
	 * sektör manifesti değişince eski map fail-closed reddedilir).
	 *
	 * @return string|null
	 */
	public static function manifest_digest_for( array $sectorRecords ) {
		try {
			return MaviBelge_Core_Import_Hash::hash( array( 'schema' => 'mavibelge-sector-image-manifest/1', 'records' => array_values( $sectorRecords ) ) );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Ham option değerinden durum (SAF çekirdeği kullanır; WordPress çağırmaz). `null`/`false` = option yok.
	 *
	 * @return array{present: bool, valid: bool, errors: string[], mappings: array<string,int>, digest: string|null}
	 */
	public static function state_from_raw( $raw, array $sectorRecords, $inspect, $manifestDigest ) {
		if ( null === $raw || false === $raw ) {
			return array( 'present' => false, 'valid' => false, 'errors' => array(), 'mappings' => array(), 'digest' => null );
		}
		$result            = self::validate_envelope( $raw, $sectorRecords, $inspect, $manifestDigest );
		$result['present'] = true;
		return $result;
	}

	/**
	 * @param mixed    $raw            Option'dan okunan ham değer.
	 * @param array    $sectorRecords  Güncel sektör manifest kayıtları.
	 * @param callable $inspect        function( int $id ): array{post_type,post_status,mime,readable}|null
	 * @param mixed    $manifestDigest Güncel manifest digest'i (64-hex).
	 * @return array{valid: bool, errors: string[], mappings: array<string,int>, digest: string|null}
	 */
	public static function validate_envelope( $raw, array $sectorRecords, $inspect, $manifestDigest ) {
		$errors = array();
		if ( ! is_array( $raw ) || array() === $raw ) {
			return self::invalid( array( 'envelope_not_array' ) );
		}
		$keys = array_keys( $raw );
		sort( $keys, SORT_STRING );
		$expected = self::TOP_KEYS;
		sort( $expected, SORT_STRING );
		if ( $keys !== $expected ) {
			return self::invalid( array( 'unknown_top_keys' ) );
		}
		if ( self::SCHEMA_VERSION !== $raw['schema_version'] ) {
			$errors[] = 'schema_version_mismatch';
		}
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $manifestDigest ) || $raw['manifest_digest'] !== $manifestDigest ) {
			$errors[] = 'manifest_digest_mismatch';
		}
		if ( ! is_array( $raw['mappings'] ) ) {
			$errors[] = 'mappings_not_array';
			return self::invalid( $errors );
		}

		$required = self::required_image_sources( $sectorRecords );
		$given    = array();
		foreach ( $raw['mappings'] as $slug => $id ) {
			$given[ (string) $slug ] = $id;
		}
		foreach ( array_keys( $required ) as $slug ) {
			if ( ! array_key_exists( $slug, $given ) ) {
				$errors[] = 'missing_slug:' . $slug;
			}
		}
		foreach ( array_keys( $given ) as $slug ) {
			if ( ! array_key_exists( $slug, $required ) ) {
				$errors[] = 'unexpected_slug:' . $slug;
			}
		}

		$byId = array();
		foreach ( $required as $slug => $source ) {
			if ( ! array_key_exists( $slug, $given ) ) {
				continue;
			}
			$id = $given[ $slug ];
			if ( ! is_int( $id ) || $id <= 0 ) {
				$errors[] = 'invalid_id:' . $slug;
				continue;
			}
			$attachmentError = self::attachment_error( $id, $inspect );
			if ( null !== $attachmentError ) {
				$errors[] = $attachmentError . ':' . $slug;
				continue;
			}
			$byId[ $id ][] = $slug;
		}
		foreach ( $byId as $slugs ) {
			if ( count( $slugs ) < 2 ) {
				continue;
			}
			$sources = array();
			foreach ( $slugs as $slug ) {
				$sources[ $required[ $slug ] ] = true;
			}
			if ( count( $sources ) > 1 ) {
				foreach ( $slugs as $slug ) {
					$errors[] = 'shared_id_different_source:' . $slug;
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return self::invalid( $errors );
		}
		$mappings = array();
		foreach ( array_keys( $required ) as $slug ) {
			$mappings[ $slug ] = $given[ $slug ];
		}
		return array( 'valid' => true, 'errors' => array(), 'mappings' => $mappings, 'digest' => self::digest( $manifestDigest, $mappings ) );
	}

	/**
	 * Map'in deterministik özeti (manifest digest'ine ve içeriğe bağlıdır).
	 *
	 * @param array<string,int> $mappings
	 * @return string|null
	 */
	public static function digest( $manifestDigest, array $mappings ) {
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $manifestDigest ) ) {
			return null;
		}
		ksort( $mappings, SORT_STRING );
		try {
			return MaviBelge_Core_Import_Hash::hash( array( 'schema' => self::DIGEST_SCHEMA, 'manifest_digest' => $manifestDigest, 'mappings' => $mappings ) );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	/** @param array<string,int> $mappings */
	public static function build_envelope( array $mappings, $manifestDigest ) {
		return array( 'schema_version' => self::SCHEMA_VERSION, 'manifest_digest' => $manifestDigest, 'mappings' => $mappings );
	}

	/**
	 * Depodaki map'i güncel manifestle DOĞRULAR (yazmaz). `present=false`: option yok (map henüz girilmedi).
	 *
	 * @return array{present: bool, valid: bool, errors: string[], mappings: array<string,int>, digest: string|null}
	 */
	public static function load_validated( array $sectorRecords, $manifestDigest ) {
		return self::state_from_raw( get_option( self::OPTION, null ), $sectorRecords, array( __CLASS__, 'inspect_attachment' ), $manifestDigest );
	}

	/**
	 * Yetkili kullanıcının seçtiği map'i doğrular ve ATOMİK olarak yazar (yetki/nonce çağıran katmanda).
	 *
	 * Gerçek değişiklik varsa option yazımı + audit kaydı (eski/yeni digest + değişen slug adları) + COMMIT tek transaction'dır:
	 * üçünden biri başarısızsa transaction geri alınır, option önbelleği temizlenir ve  + SABİT hata kodu döner
	 * (infrastructure_unavailable | transaction_begin_failed | option_write_failed | audit_failed | commit_failed |
	 * transaction_rollback_failed | audit_context_invalid). Gerçek değişiklik yoksa no-op başarıdır (audit/transaction yok).
	 * Altyapı doğrulaması yalnız option + audit tablolarını kapsar; import run tabloları kurulmaz ve gerekmez.
	 *
	 * @param mixed  slug => attachment ID (gerçek int)
	 * @return array{ok: bool, errors: string[], digest: string|null, changed_slugs: string[]}
	 */
	public static function save( $submitted, array $sectorRecords, $manifestDigest, MaviBelge_Core_Import_Image_Map_Store $store = null ) {
		$store = null === $store ? new MaviBelge_Core_Import_Wp_Image_Map_Store() : $store;
		if ( ! is_array( $submitted ) ) {
			return self::save_failed( 'mappings_not_array' );
		}
		$envelope = self::build_envelope( $submitted, $manifestDigest );
		$result   = self::validate_envelope( $envelope, $sectorRecords, array( $store, 'inspect' ), $manifestDigest );
		if ( ! $result['valid'] ) {
			return array( 'ok' => false, 'errors' => $result['errors'], 'digest' => null, 'changed_slugs' => array() );
		}
		// Eşzamanlı iki yönetici: okuma -> karşılaştırma -> yazma -> audit tek kilit altında yapılır. Kilit ALINDIKTAN SONRA
		// yeniden okunur; aksi hâlde audit'teki old_digest başka yöneticinin yazımıyla çelişebilirdi.
		if ( true !== $store->lock() ) {
			return self::save_failed( 'locked' );
		}
		try {
			return self::save_locked( $result, $sectorRecords, $manifestDigest, $store );
		} finally {
			try {
				$store->unlock();
			} catch ( Throwable $e ) {
				// kilit bırakma hatası sonucu değiştirmez; GET_LOCK bağlantı sonunda zaten düşer.
			}
		}
	}

	/**
	 * Kilit altında çalışır. @param array $result validate_envelope() sonucu (geçerli).
	 *
	 * @return array{ok: bool, errors: string[], digest: string|null, changed_slugs: string[]}
	 */
	private static function save_locked( array $result, array $sectorRecords, $manifestDigest, MaviBelge_Core_Import_Image_Map_Store $store ) {
		$raw      = $store->read();
		$previous = self::state_from_raw( $raw, $sectorRecords, array( $store, 'inspect' ), $manifestDigest );
		$old      = $previous['valid'] ? $previous['mappings'] : array();
		$changed  = array();
		foreach ( $result['mappings'] as $slug => $id ) {
			if ( ! isset( $old[ $slug ] ) || $old[ $slug ] !== $id ) {
				$changed[] = $slug;
			}
		}
		if ( array() === $changed && $previous['valid'] ) {
			// Gerçek değişiklik yok: no-op başarı; option/audit/transaction YOK (sahte audit olayı üretilmez).
			return array( 'ok' => true, 'errors' => array(), 'digest' => $result['digest'], 'changed_slugs' => array() );
		}
		$context = self::audit_context( $previous['valid'] ? $previous['digest'] : null, $result['digest'], $changed );
		if ( null === $context ) {
			return self::save_failed( 'audit_context_invalid' );
		}
		// ATOMİK: option yazımı + audit kaydı + commit birlikte başarılı olmalı; aksi hâlde option ESKİ durumuna döner.
		if ( true !== $store->ready() ) {
			return self::save_failed( 'infrastructure_unavailable' );
		}
		if ( true !== $store->begin() ) {
			return self::save_failed( 'transaction_begin_failed' );
		}
		$error = null;
		try {
			if ( true !== $store->write( self::build_envelope( $result['mappings'], $manifestDigest ), null !== $raw ) ) {
				$error = 'option_write_failed';
			} elseif ( true !== $store->audit( $context ) ) {
				$error = 'audit_failed';
			} elseif ( true !== $store->commit() ) {
				$error = 'commit_failed';
			}
		} catch ( Throwable $e ) {
			$error = 'unexpected_exception';
		}
		if ( null !== $error ) {
			$rolledBack = false;
			try {
				$rolledBack = true === $store->rollback();
			} catch ( Throwable $e ) {
				$rolledBack = false;
			}
			$store->flush(); // geri alınmış yeni değer option/object cache'te KALMAZ
			return self::save_failed( $rolledBack ? $error : 'transaction_rollback_failed' );
		}
		$store->flush();
		return array( 'ok' => true, 'errors' => array(), 'digest' => $result['digest'], 'changed_slugs' => $changed );
	}

	/**
	 * Audit context'i KAPALI şekildedir: yalnız old_digest (64-hex|null), new_digest (64-hex) ve slug adları.
	 * Dosya yolu, görsel içeriği veya başka bir alan taşınamaz.
	 *
	 * @param array<int,string> $changedSlugs
	 * @return array{old_digest: string|null, new_digest: string, changed_slugs: string[]}|null
	 */
	public static function audit_context( $oldDigest, $newDigest, array $changedSlugs ) {
		if ( ( null !== $oldDigest && ! MaviBelge_Core_Import_Apply_Plan::is_digest( $oldDigest ) ) || ! MaviBelge_Core_Import_Apply_Plan::is_digest( $newDigest ) || array() === $changedSlugs || array_values( $changedSlugs ) !== $changedSlugs ) {
			return null;
		}
		foreach ( $changedSlugs as $slug ) {
			if ( ! is_string( $slug ) || 1 !== preg_match( '/^[a-z][a-z0-9]*(-[a-z0-9]+)*\z/', $slug ) ) {
				return null;
			}
		}
		return array( 'old_digest' => $oldDigest, 'new_digest' => $newDigest, 'changed_slugs' => $changedSlugs );
	}

	private static function save_failed( $code ) {
		return array( 'ok' => false, 'errors' => array( $code ), 'digest' => null, 'changed_slugs' => array() );
	}

	/**
	 * Gerçek WordPress attachment incelemesi (yalnız okur; dosya yolu döndürülmez).
	 *
	 * @param int $id
	 * @return array{post_type: string, post_status: string, mime: string, readable: bool}|null
	 */
	public static function inspect_attachment( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post ) {
			return null;
		}
		$file = get_attached_file( (int) $id );
		return array(
			'post_type'   => (string) $post->post_type,
			'post_status' => (string) $post->post_status,
			'mime'        => (string) get_post_mime_type( $post ),
			'readable'    => is_string( $file ) && '' !== $file && is_readable( $file ),
		);
	}

	/** @return string|null Hata öneki (slug SONRADAN eklenir). */
	private static function attachment_error( $id, $inspect ) {
		try {
			$info = is_callable( $inspect ) ? call_user_func( $inspect, $id ) : null;
		} catch ( Throwable $e ) {
			$info = null;
		}
		if ( ! is_array( $info ) || ! isset( $info['post_type'], $info['post_status'], $info['mime'] ) || ! array_key_exists( 'readable', $info )
			|| ! is_string( $info['post_type'] ) || ! is_string( $info['post_status'] ) || ! is_string( $info['mime'] ) || ! is_bool( $info['readable'] ) ) {
			return 'attachment_missing';
		}
		if ( 'attachment' !== $info['post_type'] ) {
			return 'attachment_wrong_type';
		}
		if ( 'trash' === $info['post_status'] ) {
			return 'attachment_trashed';
		}
		if ( ! in_array( $info['post_status'], self::ATTACHMENT_STATUSES, true ) ) {
			return 'attachment_status_invalid';
		}
		if ( 1 !== preg_match( '#^image/[a-z0-9][a-z0-9.+-]*\z#i', $info['mime'] ) ) {
			return 'attachment_not_image';
		}
		if ( true !== $info['readable'] ) {
			return 'attachment_unreadable';
		}
		return null;
	}

	private static function invalid( array $errors ) {
		return array( 'valid' => false, 'errors' => array_values( $errors ), 'mappings' => array(), 'digest' => null );
	}
}
