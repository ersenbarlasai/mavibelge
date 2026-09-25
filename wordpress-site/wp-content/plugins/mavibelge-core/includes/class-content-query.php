<?php
/**
 * Faz 7 — haber/doküman/referans/lokasyon/SSS için SAF (WordPress'ten
 * bağımsız) sorgu sözleşmesi. Hiçbir metot WordPress veritabanı
 * fonksiyonu çağırmaz; `tests/run.php` bağımsız bootstrap'iyle doğrudan
 * sınanır. Kullanıcıdan gelen GET parametreleri burada normalize edilir;
 * tema hiçbir zaman bu temizliği kendisi yapmaz
 * (MaviBelge_Core_Content_Service tek çağıran).
 *
 * Genel GET parametre adları (Faz 5 katalog sözleşmesiyle aynı önek):
 *
 *   mb_type  haber türü: `haber` | `duyuru` (kontrollü kelime dağarcığı)
 *   mb_cat   doküman/SSS kategorisi terim slug'ı (yalnız biçim kontrolü)
 *   mb_page  1 tabanlı sayfa numarası
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Content_Query {

	const NEWS_TYPES       = array( 'haber', 'duyuru' );
	const NEWS_PAGE_SIZE   = 12;
	const DOC_PAGE_SIZE    = 12;
	const REFERENCE_LIMIT  = 60;
	const LOCATION_LIMIT   = 20;
	const FAQ_LIMIT        = 100;
	const MAX_PAGE         = 500;

	/** Genel görünür kayıt durumu: `passive` dışındaki her şey (eksik meta = varsayılan `active`) görünür. */
	const STATUS_PASSIVE = 'passive';

	/**
	 * Doküman eki için izinli MIME kümesi (kamuya açık kurumsal doküman). Çalıştırılabilir/betik türleri yok.
	 */
	const DOCUMENT_MIME_LABELS = array(
		'application/pdf'                                                            => 'PDF',
		'application/msword'                                                        => 'DOC',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document'  => 'DOCX',
		'application/vnd.ms-excel'                                                  => 'XLS',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'        => 'XLSX',
	);

	/** Referans logosu için izinli görsel MIME kümesi (SVG bilinçli olarak YOK: sanitize edilmemiş XML). */
	const LOGO_MIME_TYPES = array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' );

	/**
	 * @param array $raw $_GET biçimli dizi.
	 * @return array{type: string, page: int}
	 */
	public static function normalize_news_args( array $raw ) {
		return array(
			'type' => self::normalize_news_type( self::scalar( $raw, 'mb_type' ) ),
			'page' => self::normalize_page( self::scalar( $raw, 'mb_page' ) ),
		);
	}

	/** @return array{category: string, page: int} */
	public static function normalize_document_args( array $raw ) {
		return array(
			'category' => self::normalize_slug( self::scalar( $raw, 'mb_cat' ) ),
			'page'     => self::normalize_page( self::scalar( $raw, 'mb_page' ) ),
		);
	}

	/** @return array{category: string} */
	public static function normalize_faq_args( array $raw ) {
		return array( 'category' => self::normalize_slug( self::scalar( $raw, 'mb_cat' ) ) );
	}

	/** `haber`/`duyuru` dışındaki her şey (dizi, büyük harf, boşluk) boş kabul edilir: "filtre yok". */
	public static function normalize_news_type( $raw ) {
		$value = is_string( $raw ) ? trim( $raw ) : '';
		return in_array( $value, self::NEWS_TYPES, true ) ? $value : '';
	}

	/** Yalnız biçim kontrolü (küçük harf/rakam/tire); gerçek terimin varlığı servisin işidir. */
	public static function normalize_slug( $raw ) {
		$value = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';
		return 1 === preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $value ) && strlen( $value ) <= 100 ? $value : '';
	}

	/** Pozitif tam sayı dizgesi; aksi hâlde 1. Üst sınır MAX_PAGE (taşma/DoS koruması). */
	public static function normalize_page( $raw ) {
		$value = is_string( $raw ) || is_int( $raw ) ? trim( (string) $raw ) : '';
		if ( 1 !== preg_match( '/^[0-9]{1,6}$/', $value ) ) {
			return 1;
		}
		$int = (int) $value;
		return $int > 0 ? min( $int, self::MAX_PAGE ) : 1;
	}

	/** [1, max(toplam,1)] aralığına sıkıştırır. */
	public static function clamp_page( $page, $totalPages ) {
		return min( max( 1, (int) $page ), max( 1, (int) $totalPages ) );
	}

	/** `passive` dışındaki her şey görünürdür; eksik/bozuk değer varsayılan `active` sayılır (yalnız açık `passive` gizler). */
	public static function is_publicly_active( $recordStatus ) {
		return self::STATUS_PASSIVE !== $recordStatus;
	}

	/** Haber yalnız `approved` ise görünür (yayın durumuna EK kapı; kalıcı yayınlanmış ama onaysız kayıt asla listelenmez). */
	public static function is_news_approved( $approvalStatus ) {
		return 'approved' === $approvalStatus;
	}

	/** Doküman `_mb_valid_until` (Y-m-d) bugünden önceyse süresi dolmuştur; boş = süresiz. Biçim bozuksa süresi dolmuş sayılmaz (fail-open DEĞİL: gösterim etiketi, gizleme değil). */
	public static function is_document_expired( $validUntil, $todayYmd ) {
		if ( ! is_string( $validUntil ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $validUntil ) ) {
			return false;
		}
		return $validUntil < $todayYmd;
	}

	/** Toplam sayfa sayısı (en az 1). */
	public static function total_pages( $total, $pageSize ) {
		$size = max( 1, (int) $pageSize );
		return max( 1, (int) ceil( max( 0, (int) $total ) / $size ) );
	}

	/**
	 * Yalnız https bağlantı (harita/web sitesi). `javascript:`, `data:`, protokolsüz ve http bağlantıları
	 * boş döner — güvenli dış bağlantı kuralı (yalnız şifreli hedef).
	 */
	public static function safe_external_url( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$value = $raw; // Kırpma YOK: başta/sonda boşluk veya NUL dahil hiçbir kontrol karakteri kabul edilmez.
		if ( '' === $value || 1 === preg_match( '/[\x00-\x20\x7f<>"\'\\\\]/', $value ) ) {
			return '';
		}
		$parts = parse_url( $value );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || 'https' !== strtolower( $parts['scheme'] ) || '' === $parts['host'] ) {
			return '';
		}
		return $value;
	}

	/** İzinli doküman MIME'ı için kısa etiket (PDF/DOC/…); izinli değilse null. */
	public static function document_type_label( $mime ) {
		return is_string( $mime ) && isset( self::DOCUMENT_MIME_LABELS[ $mime ] ) ? self::DOCUMENT_MIME_LABELS[ $mime ] : null;
	}

	public static function is_allowed_logo_mime( $mime ) {
		return is_string( $mime ) && in_array( $mime, self::LOGO_MIME_TYPES, true );
	}

	/** İnsan-okunur dosya boyutu (KB/MB); negatif/bozuk -> boş. */
	public static function format_bytes( $bytes ) {
		if ( ! is_int( $bytes ) || $bytes < 0 ) {
			return '';
		}
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1048576 ) {
			return number_format( $bytes / 1024, 0, ',', '.' ) . ' KB';
		}
		return number_format( $bytes / 1048576, 1, ',', '.' ) . ' MB';
	}

	private static function scalar( array $raw, $key ) {
		if ( ! isset( $raw[ $key ] ) || is_array( $raw[ $key ] ) || is_object( $raw[ $key ] ) ) {
			return '';
		}
		$value = $raw[ $key ];
		return is_string( $value ) && function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;
	}
}
