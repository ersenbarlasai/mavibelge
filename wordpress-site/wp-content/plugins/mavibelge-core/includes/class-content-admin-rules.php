<?php
/**
 * Faz 7 — haber/doküman/referans/lokasyon/SSS yönetim listeleri için SAF
 * (WordPress'ten bağımsız) kurallar: izinli filtre anahtarları/değerleri,
 * meta sorgusu üretimi ve "neden herkese açık değil" açıklaması.
 * Kullanıcı girdisi burada KAPALI allowlist'ten geçer; ham değer asla
 * sorguya girmez. WordPress bağlantısı `admin/class-content-admin.php`'dedir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Content_Admin_Rules {

	const POST_TYPES = array( 'mb_haber', 'mb_dokuman', 'mb_referans', 'mb_lokasyon', 'mb_sss' );

	/**
	 * Post type -> filtre anahtarı -> array( meta_key, izinli değer => etiket ).
	 * `_mb_valid_until` için özel `mb_f_expired` filtresi ayrıca ele alınır.
	 */
	const FILTERS = array(
		'mb_haber'    => array(
			'mb_f_approval' => array(
				'meta'    => '_mb_approval_status',
				'label'   => 'Tüm Onay Durumları',
				'options' => array( 'draft' => 'Taslak', 'in_review' => 'İncelemede', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi' ),
			),
		),
		'mb_dokuman'  => array(
			'mb_f_record'  => array(
				'meta'    => '_mb_record_status',
				'label'   => 'Tüm Kayıt Durumları',
				'options' => array( 'active' => 'Aktif', 'passive' => 'Pasif' ),
			),
			'mb_f_expired' => array(
				'meta'    => '_mb_valid_until',
				'label'   => 'Geçerlilik: Tümü',
				'options' => array( 'expired' => 'Süresi dolmuş', 'valid' => 'Süresi dolmamış / süresiz' ),
			),
		),
		'mb_referans' => array(
			'mb_f_record'    => array(
				'meta'    => '_mb_record_status',
				'label'   => 'Tüm Kayıt Durumları',
				'options' => array( 'active' => 'Aktif', 'passive' => 'Pasif' ),
			),
			'mb_f_reference' => array(
				'meta'    => '_mb_reference_status',
				'label'   => 'Tüm Referans Türleri',
				'options' => array( 'real' => 'Gerçek', 'representative' => 'Temsili' ),
			),
		),
		'mb_lokasyon' => array(
			'mb_f_record' => array(
				'meta'    => '_mb_record_status',
				'label'   => 'Tüm Kayıt Durumları',
				'options' => array( 'active' => 'Aktif', 'passive' => 'Pasif' ),
			),
		),
		'mb_sss'      => array(
			'mb_f_record' => array(
				'meta'    => '_mb_record_status',
				'label'   => 'Tüm Kayıt Durumları',
				'options' => array( 'active' => 'Aktif', 'passive' => 'Pasif' ),
			),
		),
	);

	/** Sıralanabilir sütun anahtarı -> meta anahtarı (yalnız sayısal `_mb_sort_order`). */
	const SORTABLE_META = array( 'mb_sort' => '_mb_sort_order' );

	/** @return array<string, array> Bu post type için filtre tanımları; bilinmeyen tür -> boş. */
	public static function filters_for( $postType ) {
		return is_string( $postType ) && isset( self::FILTERS[ $postType ] ) ? self::FILTERS[ $postType ] : array();
	}

	/** Ham filtre değerini kapalı allowlist'ten geçirir; dışı ('' = filtre yok). Dizi/nesne -> ''. */
	public static function sanitize_filter( $postType, $filterKey, $raw ) {
		$defs = self::filters_for( $postType );
		if ( ! is_string( $filterKey ) || ! isset( $defs[ $filterKey ] ) || ! is_string( $raw ) ) {
			return '';
		}
		return array_key_exists( $raw, $defs[ $filterKey ]['options'] ) ? $raw : '';
	}

	/**
	 * @param array $filters filtre anahtarı => ham değer (ör. $_GET)
	 * @return array WP_Query meta_query parçası (AND); filtre yoksa boş dizi.
	 */
	public static function build_meta_query( $postType, array $filters, $todayYmd ) {
		$clauses = array();
		foreach ( self::filters_for( $postType ) as $key => $def ) {
			$value = self::sanitize_filter( $postType, $key, isset( $filters[ $key ] ) ? $filters[ $key ] : '' );
			if ( '' === $value ) {
				continue;
			}
			if ( 'mb_f_expired' === $key ) {
				$clauses[] = 'expired' === $value
					? array( 'key' => '_mb_valid_until', 'value' => array( '0001-01-01', gmdate( 'Y-m-d', strtotime( $todayYmd . ' -1 day' ) ) ), 'compare' => 'BETWEEN', 'type' => 'DATE' )
					: array(
						'relation' => 'OR',
						array( 'key' => '_mb_valid_until', 'compare' => 'NOT EXISTS' ),
						array( 'key' => '_mb_valid_until', 'value' => '', 'compare' => '=' ),
						array( 'key' => '_mb_valid_until', 'value' => $todayYmd, 'compare' => '>=', 'type' => 'DATE' ),
					);
				continue;
			}
			if ( '_mb_record_status' === $def['meta'] && 'active' === $value ) {
				// Eksik meta = varsayılan aktif (genel görünürlük kuralıyla aynı).
				$clauses[] = array(
					'relation' => 'OR',
					array( 'key' => '_mb_record_status', 'value' => 'passive', 'compare' => '!=' ),
					array( 'key' => '_mb_record_status', 'compare' => 'NOT EXISTS' ),
				);
				continue;
			}
			$clauses[] = array( 'key' => $def['meta'], 'value' => $value );
		}
		return empty( $clauses ) ? array() : array_merge( array( 'relation' => 'AND' ), $clauses );
	}

	/**
	 * Bir kaydın herkese açık görünürlüğünün (Visibility_Guard/Content_Service ile AYNI kural) insan-okunur
	 * açıklaması: yayında olup gizli kayıtların NEDEN gizli olduğu yönetimde açıkça görünür.
	 *
	 * @return array{public: bool, label: string}
	 */
	public static function visibility( $postType, $postStatus, $approvalStatus, $recordStatus ) {
		if ( 'publish' !== $postStatus ) {
			return array( 'public' => false, 'label' => 'Yayında değil' );
		}
		if ( 'mb_haber' === $postType && ! MaviBelge_Core_Content_Query::is_news_approved( $approvalStatus ) ) {
			return array( 'public' => false, 'label' => 'Yayında ama onaylanmadığı için GİZLİ' );
		}
		if ( 'mb_haber' !== $postType && ! MaviBelge_Core_Content_Query::is_publicly_active( $recordStatus ) ) {
			return array( 'public' => false, 'label' => 'Yayında ama pasif olduğu için GİZLİ' );
		}
		return array( 'public' => true, 'label' => 'Herkese açık' );
	}

	/** Sıralama sütunu için güvenli meta anahtarı; bilinmeyen -> null. */
	public static function sortable_meta_key( $orderby ) {
		return is_string( $orderby ) && isset( self::SORTABLE_META[ $orderby ] ) ? self::SORTABLE_META[ $orderby ] : null;
	}
}
