<?php
/**
 * Faz 6B3 — apply planının SAF yardımcıları (WordPress fonksiyonu çağırmaz).
 *
 * Stage (aşama) modeli — neden var: yeterlilikler var olan `mb_sektor`
 * terimine, kodlu ücretler var olan `mb_yeterlilik` postuna bağlıdır. Boş
 * bir hedefte tam katalog planı bu bağımlılıklar yüzünden
 * `blocked_dependency` taşır ve `summary.applicable === true` OLAMAZ. Plan
 * kapısını gevşetmek yerine manifest, bağımlılık sırasına göre KAPALI
 * öneklere bölünür; her aşamanın planı gerçek planlayıcının tam planıdır ve
 * kendi başına `applicable === true` olmak ZORUNDADIR:
 *
 *   sectors        -> yalnız sektörler
 *   qualifications -> sektörler + yeterlilikler (sektörler unchanged olmalı)
 *   all            -> tam katalog (sektörler + yeterlilikler unchanged olmalı)
 *   content        -> Faz 7 içerik: haberler sonra referanslar (katalogdan BAĞIMSIZ;
 *                     mevcut üç aşamanın kapsamı ve sırası DEĞİŞMEZ)
 *
 * Böylece bir bağımlılık çözülmeden sonraki kayıt yazılamaz; kısmi plan
 * uygulanmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Apply_Plan {

	/** Faz 12 — çekirdek `page` aşaması (32 sayfa; katalogdan BAĞIMSIZ, zincirin İLK aşaması). */
	const STAGE_PAGES          = 'pages';
	const STAGE_SECTORS        = 'sectors';
	const STAGE_QUALIFICATIONS = 'qualifications';
	const STAGE_ALL            = 'all';
	const STAGE_CONTENT        = 'content';

	/** Katalog aşamaları (Faz 6B3; DEĞİŞMEDİ). */
	const STAGES = array( 'sectors', 'qualifications', 'all' );

	/** Faz 7 içerik aşaması (katalogdan bağımsız). */
	const CONTENT_STAGES = array( 'content' );

	/** Faz 12 sayfa aşaması (katalogdan bağımsız). */
	const PAGE_STAGES = array( 'pages' );

	/**
	 * Tanınan BÜTÜN aşamalar: plan özeti, apply ve CLI doğrulaması BUNU kullanır. SIRA bağlayıcıdır (sunucu tarafında zorunlu:
	 * MaviBelge_Core_Import_Admin_Run_Service::PREREQUISITE_STAGE): pages -> sectors -> qualifications -> all -> content.
	 * Gerekçe: sayfalar katalog verisine BAĞIMLI DEĞİLDİR (teknik bir bağımlılık yoktur); sayfa iskeleti (kalıcı bağlantılar, hub/form/CPT
	 * sayfaları) veri aktarımından ÖNCE kurulup doğrulanır ve staging'de katalog aktarımı boş bir siteye yapılmaz.
	 */
	const ALL_STAGES = array( 'pages', 'sectors', 'qualifications', 'all', 'content' );

	/**
	 * Aşama => önkoşul aşaması (bu aşamanın TAM planı GERÇEK readback sonucunda unchanged olmalı). TEK kaynak: admin (Admin_Run_Service)
	 * ve WP-CLI (--apply) aynı haritayı sunucu tarafında zorunlu kılar; istemci sırasına güvenilmez.
	 * Zincir: pages -> sectors -> qualifications -> all -> content.
	 */
	const PREREQUISITE_STAGE = array(
		'pages'          => null,
		'sectors'        => 'pages',
		'qualifications' => 'sectors',
		'all'            => 'qualifications',
		'content'        => 'all',
	);

	/** Aşama => kapsadığı import türleri (bağımlılık sırasıyla kapalı önek). */
	const STAGE_TYPES = array(
		'sectors'        => array( 'sector' ),
		'qualifications' => array( 'sector', 'qualification' ),
		'all'            => array( 'sector', 'qualification', 'fee' ),
		'content'        => array( 'news', 'reference', 'faq' ),
		'pages'          => array( 'page' ),
	);

	/** Tür => manifest listesi anahtarı. */
	const TYPE_LISTS = array(
		'sector'        => 'sectors',
		'qualification' => 'qualifications',
		'fee'           => 'fees',
		'news'          => 'news',
		'reference'     => 'references',
		'faq'           => 'faqs',
		'page'          => 'pages',
	);

	/** Manifestte İSTEĞE BAĞLI olan tür listeleri (yoksa boş sayılır); üç katalog listesi zorunludur. */
	const OPTIONAL_LISTS = array( 'news', 'references', 'faqs', 'pages' );

	/** İsteğe bağlı liste => YALNIZ o aşamada manifestte yer alan liste (diğer aşamalar eski şekli korur). */
	const OPTIONAL_LIST_STAGE = array( 'news' => 'content', 'references' => 'content', 'faqs' => 'content', 'pages' => 'pages' );

	/** Yazma sırası (apply). Rollback bunun tersidir. */
	const TYPE_RANK = array(
		'sector'        => 0,
		'qualification' => 1,
		'fee'           => 2,
		'news'          => 3,
		'reference'     => 4,
		'faq'           => 5,
		'page'          => 6,
	);

	/** Tek merkezi batch boyutu sabitleri. */
	const DEFAULT_BATCH_SIZE = 20;
	const MIN_BATCH_SIZE     = 1;
	const MAX_BATCH_SIZE     = 20;

	const PLAN_DIGEST_SCHEMA     = 'mavibelge-import-plan-digest/1';
	const ROLLBACK_DIGEST_SCHEMA = 'mavibelge-import-rollback-digest/1';

	/**
	 * Aşamanın kapsamadığı tür listelerini boşaltır; kapsananları DEĞİŞTİRMEZ.
	 *
	 * @param mixed $manifest {sectors, qualifications, fees} + isteğe bağlı {news, references}
	 * @param mixed $stage
	 * @return array|null Bilinmeyen aşama, üç katalog anahtarı eksik/liste olmayan veya
	 *   liste olmayan news/references taşıyan manifestte null. Katalog aşamalarının çıktısı
	 *   ESKİSİYLE aynı üç anahtardır; news/references anahtarları YALNIZ content aşamasında eklenir.
	 */
	public static function filter_manifest( $manifest, $stage ) {
		if ( ! is_string( $stage ) || ! array_key_exists( $stage, self::STAGE_TYPES ) || ! is_array( $manifest ) ) {
			return null;
		}
		foreach ( self::TYPE_LISTS as $list ) {
			$optional = in_array( $list, self::OPTIONAL_LISTS, true );
			if ( ! array_key_exists( $list, $manifest ) ) {
				if ( $optional ) {
					continue;
				}
				return null;
			}
			if ( ! is_array( $manifest[ $list ] ) ) {
				return null;
			}
		}
		$out = array();
		foreach ( self::TYPE_LISTS as $type => $list ) {
			$inStage = in_array( $type, self::STAGE_TYPES[ $stage ], true );
			if ( in_array( $list, self::OPTIONAL_LISTS, true ) ) {
				if ( self::OPTIONAL_LIST_STAGE[ $list ] !== $stage ) {
					continue; // Diğer aşamalar eski şekli korur (katalog: üç anahtar; content: news+references).
				}
				$out[ $list ] = $inStage && array_key_exists( $list, $manifest ) ? $manifest[ $list ] : array();
				continue;
			}
			$out[ $list ] = $inStage ? $manifest[ $list ] : array();
		}
		return $out;
	}

	/**
	 * @param mixed $raw null => varsayılan; gerçek int veya kanonik onluk string.
	 * @return int|null MIN..MAX dışında veya kanonik değilse null.
	 */
	public static function normalize_batch_size( $raw ) {
		if ( null === $raw ) {
			return self::DEFAULT_BATCH_SIZE;
		}
		if ( is_int( $raw ) ) {
			$value = $raw;
		} elseif ( is_string( $raw ) ) {
			$parsed = MaviBelge_Core_Validator::parse_canonical_decimal_int( $raw );
			if ( ! $parsed['ok'] ) {
				return null;
			}
			$value = $parsed['value'];
		} else {
			return null;
		}
		return ( $value >= self::MIN_BATCH_SIZE && $value <= self::MAX_BATCH_SIZE ) ? $value : null;
	}

	/** @return string|null Manifestin deterministik hash'i; hesaplanamazsa null. */
	public static function manifest_digest( $manifest ) {
		if ( ! is_array( $manifest ) ) {
			return null;
		}
		try {
			return MaviBelge_Core_Import_Hash::hash( $manifest );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Kullanıcının onaylayacağı plan özeti. Karar/hash/hedef/doğal anahtar/
	 * değişen alan ADLARI/özet/hata listesini kapsar; insan-okunur `message`
	 * metnini kapsamaz (metin değişikliği plan değişikliği değildir).
	 *
	 * @return string|null 64 hex; girdi geçersizse null.
	 */
	public static function plan_digest( $stage, $manifestDigest, $plan ) {
		if ( ! is_string( $stage ) || ! in_array( $stage, self::ALL_STAGES, true ) || ! self::is_digest( $manifestDigest ) ) {
			return null;
		}
		if ( ! is_array( $plan ) || ! isset( $plan['entries'], $plan['summary'], $plan['errors'] ) || ! is_array( $plan['entries'] ) ) {
			return null;
		}
		$entries = array();
		foreach ( $plan['entries'] as $entry ) {
			if ( ! is_array( $entry ) ) {
				return null;
			}
			$row = array();
			foreach ( array( 'source_key', 'type', 'decision', 'reason', 'target_id', 'incoming_hash', 'current_hash', 'last_applied_hash', 'natural_key_check', 'changed_fields', 'unresolved_dependencies' ) as $key ) {
				$row[ $key ] = array_key_exists( $key, $entry ) ? $entry[ $key ] : null;
			}
			$entries[] = $row;
		}
		try {
			return MaviBelge_Core_Import_Hash::hash(
				array(
					'schema'   => self::PLAN_DIGEST_SCHEMA,
					'stage'    => $stage,
					'manifest' => $manifestDigest,
					'entries'  => $entries,
					'summary'  => $plan['summary'],
					'errors'   => $plan['errors'],
				)
			);
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Rollback onayının kapsadığı item listesi özeti.
	 *
	 * @param string $runUid
	 * @param array  $items get_items() satırları (id, source_key, type, decision, target_id) + çözülmüş new_hash.
	 * @return string|null
	 */
	public static function rollback_digest( $runUid, array $items ) {
		if ( ! is_string( $runUid ) || '' === $runUid ) {
			return null;
		}
		$rows = array();
		foreach ( $items as $item ) {
			$rows[] = array( $item['id'], $item['source_key'], $item['type'], $item['decision'], $item['target_id'], $item['new_hash'] );
		}
		try {
			return MaviBelge_Core_Import_Hash::hash( array( 'schema' => self::ROLLBACK_DIGEST_SCHEMA, 'run' => $runUid, 'items' => $rows ) );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	/** @param mixed $value */
	public static function is_digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{64}\z/', $value );
	}

	/** Kararlı sıralama: sektör -> yeterlilik -> ücret -> haber -> referans; tür içinde plan sırası korunur. */
	public static function order_writes( array $writes ) {
		$indexed = array();
		foreach ( array_values( $writes ) as $i => $w ) {
			$indexed[] = array( self::TYPE_RANK[ $w['type'] ], $i, $w );
		}
		usort(
			$indexed,
			function ( $a, $b ) {
				return array( $a[0], $a[1] ) <=> array( $b[0], $b[1] );
			}
		);
		$out = array();
		foreach ( $indexed as $row ) {
			$out[] = $row[2];
		}
		return $out;
	}

	/** Rollback sırası: referans -> haber -> ücret -> yeterlilik -> sektör (apply sırasının tersi); tür içinde ters seq. */
	public static function rollback_order( array $items ) {
		$list = array_values( $items );
		$top  = count( self::TYPE_RANK ) - 1;
		usort(
			$list,
			function ( $a, $b ) use ( $top ) {
				$ra = $top - self::TYPE_RANK[ $a['type'] ];
				$rb = $top - self::TYPE_RANK[ $b['type'] ];
				return array( $ra, $b['seq'] ) <=> array( $rb, $a['seq'] );
			}
		);
		return $list;
	}

	/** @return array[] Sabit boyutlu batch'ler (son batch daha küçük olabilir). */
	public static function batches( array $list, $size ) {
		if ( ! is_int( $size ) || $size < self::MIN_BATCH_SIZE ) {
			return array();
		}
		return array_chunk( array_values( $list ), $size );
	}
}
