<?php
/**
 * Faz 10 — sistem sağlığı kontrolleri (SAF: girdi ortam dizisi, çıktı kontrol listesi). WordPress fonksiyonu çağırmaz.
 *
 * Çıktı: her öğe array( 'id', 'label', 'status' => ok|warn|fail|info, 'detail' ). Ayrıntı metinleri parola, anahtar, sunucu
 * yolu veya kullanıcı verisi İÇERMEZ. Hiçbir ayar değiştirilmez. PHP 7.3 / CentOS 7 EOL riski gizlenmez: `warn` olarak
 * açıkça yazılır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Health_Checks {

	const REQUIRED_EXTENSIONS = array( 'mysqli', 'json', 'mbstring', 'fileinfo', 'openssl', 'curl', 'dom', 'xml', 'zip' );

	const STATUSES = array( 'ok', 'warn', 'fail', 'info' );

	/**
	 * @param array $env Anahtarlar aşağıda `get()` ile okunur; eksik anahtar güvenli varsayılan alır.
	 * @return array[]
	 */
	public static function evaluate( array $env ) {
		$g          = function ( $key, $default = null ) use ( $env ) {
			return array_key_exists( $key, $env ) ? $env[ $key ] : $default;
		};
		$production = 'production' === $g( 'environment_type', 'production' );
		$out        = array();
		$add        = function ( $id, $label, $status, $detail ) use ( &$out ) {
			$out[] = array( 'id' => $id, 'label' => $label, 'status' => $status, 'detail' => $detail );
		};

		// PHP sürümü.
		$php = (string) $g( 'php_version', '' );
		if ( '' === $php || version_compare( $php, '7.3.0', '<' ) ) {
			$add( 'php_version', 'PHP sürümü', 'fail', 'PHP ' . $php . ': eklentinin gerektirdiği en düşük sürüm (7.3) karşılanmıyor.' );
		} elseif ( version_compare( $php, '7.4.0', '<' ) ) {
			$add( 'php_version', 'PHP sürümü', 'warn', 'PHP ' . $php . ': kurum tarafından kabul edilmiş EOL kısıt. Bu güvenli bir platform DEĞİLDİR; risk açıktır ve gizlenmemiştir. Güncel WordPress (7.x) PHP 7.4+ ister.' );
		} elseif ( version_compare( $php, '8.2.0', '<' ) ) {
			$add( 'php_version', 'PHP sürümü', 'warn', 'PHP ' . $php . ': güvenlik desteği sona ermiş veya ermek üzere olan bir hat.' );
		} else {
			$add( 'php_version', 'PHP sürümü', 'ok', 'PHP ' . $php . '.' );
		}

		// Gerekli PHP uzantıları.
		$exts    = (array) $g( 'extensions', array() );
		$missing = array();
		foreach ( self::REQUIRED_EXTENSIONS as $ext ) {
			if ( empty( $exts[ $ext ] ) ) {
				$missing[] = $ext;
			}
		}
		$add( 'php_extensions', 'Gerekli PHP uzantıları', empty( $missing ) ? 'ok' : 'fail', empty( $missing ) ? 'Tümü yüklü (' . implode( ', ', self::REQUIRED_EXTENSIONS ) . ').' : 'Eksik: ' . implode( ', ', $missing ) . '.' );

		// Veritabanı motoru ve karakter kümesi.
		$engines = (array) $g( 'db_engines', array() );
		$bad     = array();
		foreach ( $engines as $table => $engine ) {
			if ( 'innodb' !== strtolower( (string) $engine ) ) {
				$bad[] = $table . ' (' . ( '' === (string) $engine ? '?' : $engine ) . ')';
			}
		}
		if ( empty( $engines ) ) {
			$add( 'db_engine', 'Eklenti tabloları (InnoDB)', 'info', 'Eklenti tabloları henüz oluşmamış.' );
		} else {
			$add( 'db_engine', 'Eklenti tabloları (InnoDB)', empty( $bad ) ? 'ok' : 'fail', empty( $bad ) ? 'Tüm eklenti tabloları InnoDB.' : 'InnoDB olmayan tablo: ' . implode( ', ', $bad ) . '. İşlem (transaction) güvenliği yoktur.' );
		}
		$collate = strtolower( (string) $g( 'db_collate', '' ) );
		$charset = strtolower( (string) $g( 'db_charset', '' ) );
		$add( 'db_collation', 'Veritabanı karakter kümesi', 0 === strpos( $charset, 'utf8mb4' ) ? 'ok' : 'warn', 'charset=' . ( '' === $charset ? '?' : $charset ) . ', collation=' . ( '' === $collate ? '(varsayılan)' : $collate ) . '.' . ( 0 === strpos( $charset, 'utf8mb4' ) ? '' : ' utf8mb4 önerilir (Türkçe/emoji güvenliği).' ) );

		// Kalıcı bağlantılar, WP-Cron.
		$perma = (string) $g( 'permalink_structure', '' );
		$add( 'permalinks', 'Kalıcı bağlantı yapısı', '' !== $perma ? 'ok' : 'warn', '' !== $perma ? 'Güzel bağlantı etkin.' : 'Düz bağlantı: SEO, yönlendirme ve sitemap için güzel bağlantı gerekir.' );
		if ( $g( 'wp_cron_disabled', false ) ) {
			$add( 'wp_cron', 'WP-Cron', 'warn', 'DISABLE_WP_CRON açık. Sunucuda gerçek bir cron doğrulanmadıkça günlük bakım (audit saklama, geçici dosya temizliği) ÇALIŞMAZ.' );
		} else {
			$add( 'wp_cron', 'WP-Cron', 'info', 'WP-Cron ziyaretle tetiklenir; düşük trafikte gecikebilir.' );
		}
		$add( 'maintenance_scheduled', 'Günlük bakım zamanlaması', $g( 'maintenance_scheduled', false ) ? 'ok' : 'warn', $g( 'maintenance_scheduled', false ) ? 'mavibelge_core_daily_maintenance planlı.' : 'Günlük bakım kancası planlı değil.' );

		// Disk ve yazma izinleri.
		$free = $g( 'disk_free_bytes', null );
		if ( ! is_int( $free ) ) {
			$add( 'disk_free', 'Disk boş alanı', 'info', 'Okunamadı.' );
		} else {
			$mb = (int) floor( $free / 1048576 );
			$add( 'disk_free', 'Disk boş alanı', $mb < 200 ? 'fail' : ( $mb < 1024 ? 'warn' : 'ok' ), $mb . ' MB boş.' );
		}
		$add( 'uploads_writable', 'uploads yazma izni', $g( 'uploads_writable', false ) ? 'ok' : 'fail', $g( 'uploads_writable', false ) ? 'Yazılabilir.' : 'uploads dizini yazılamıyor.' );
		$add( 'content_writable', 'wp-content yazma izni', 'info', $g( 'content_writable', false ) ? 'Yazılabilir (güncelleme için gerekir; sertleştirmede daraltılabilir).' : 'Yazılamaz (uygulama içi güncelleme çalışmaz).' );

		// WordPress sürümü ve uyumluluk.
		$wp = (string) $g( 'wp_version', '' );
		if ( '' === $wp || version_compare( $wp, '6.9', '<' ) ) {
			$add( 'wp_version', 'WordPress sürümü', 'fail', 'WordPress ' . $wp . ': eklenti en az 6.9 ister.' );
		} elseif ( 0 === strpos( $wp, '6.9' ) ) {
			$add( 'wp_version', 'WordPress sürümü', 'warn', 'WordPress ' . $wp . ': PHP 7.3 ile uyumlu son ana hat; aktif güvenlik bakımında değildir (koşullu/legacy). Sürüm KİLİTLENMEMİŞTİR; kesin yama sürümü ve eklenti/tema uyumu sunucu doğrulamasından sonra belirlenecek.' );
		} else {
			$add( 'wp_version', 'WordPress sürümü', 'info', 'WordPress ' . $wp . ': PHP 7.3 ile uyumsuz olabilir; hedef hat koşullu/legacy olarak izlenir, sürüm kilitli değildir.' );
		}

		// Hata ayıklama, dosya düzenleyici, HTTPS, ortam.
		if ( $g( 'wp_debug_display', false ) && $production ) {
			$add( 'debug', 'Hata ayıklama ayarları', 'fail', 'Üretimde WP_DEBUG_DISPLAY açık: hata ayrıntıları ziyaretçiye görünür.' );
		} elseif ( $g( 'wp_debug', false ) && $production ) {
			$add( 'debug', 'Hata ayıklama ayarları', 'warn', 'Üretimde WP_DEBUG açık (ekrana yazdırma kapalı).' );
		} else {
			$add( 'debug', 'Hata ayıklama ayarları', 'ok', 'WP_DEBUG=' . ( $g( 'wp_debug', false ) ? 'açık' : 'kapalı' ) . ', WP_DEBUG_DISPLAY=' . ( $g( 'wp_debug_display', false ) ? 'açık' : 'kapalı' ) . '.' );
		}
		$add( 'file_edit', 'Yönetimde dosya düzenleme', $g( 'file_edit_disallowed', false ) ? 'ok' : 'warn', $g( 'file_edit_disallowed', false ) ? 'DISALLOW_FILE_EDIT açık.' : 'DISALLOW_FILE_EDIT kapalı: yönetici kod düzenleyebilir.' );
		$add( 'https', 'HTTPS', $g( 'is_https', false ) ? 'ok' : ( $production ? 'warn' : 'info' ), $g( 'is_https', false ) ? 'Site adresi https.' : 'Site adresi https değil.' );
		$add( 'environment', 'Ortam türü', 'info', 'WP_ENVIRONMENT_TYPE=' . (string) $g( 'environment_type', '?' ) . '.' );
		$public = (bool) $g( 'blog_public', true );
		if ( $production && ! $public ) {
			$add( 'blog_public', 'Arama motoru görünürlüğü', 'warn', 'Üretimde "arama motorlarından gizle" açık.' );
		} elseif ( ! $production && $public ) {
			$add( 'blog_public', 'Arama motoru görünürlüğü', 'warn', 'Üretim dışı ortam indekslenebilir görünüyor; staging noindex olmalıdır.' );
		} else {
			$add( 'blog_public', 'Arama motoru görünürlüğü', 'ok', 'blog_public=' . ( $public ? '1' : '0' ) . ' ortamla tutarlı.' );
		}

		// Form kapıları, yönlendirme, audit, önbellek.
		$forms = (array) $g( 'forms', array() );
		if ( empty( $forms ) ) {
			$add( 'forms', 'Form kapıları', 'info', 'Form bilgisi yok.' );
		} else {
			$closed  = isset( $forms['closed'] ) ? (int) $forms['closed'] : 0;
			$reasons = isset( $forms['reasons'] ) && is_array( $forms['reasons'] ) ? $forms['reasons'] : array();
			$parts   = array();
			foreach ( $reasons as $code => $n ) {
				$parts[] = $code . ' x' . (int) $n;
			}
			$add( 'forms', 'Form kapıları', 'info', ( isset( $forms['total'] ) ? (int) $forms['total'] : 0 ) . ' formdan ' . $closed . ' tanesi kapalı' . ( empty( $parts ) ? '.' : ' (' . implode( ', ', $parts ) . ').' ) );
		}
		$red = (array) $g( 'redirects', array() );
		$add( 'redirects', 'Yönlendirme kuralları', 'info', 'Toplam ' . ( isset( $red['total'] ) ? (int) $red['total'] : 0 ) . ', etkin ' . ( isset( $red['active'] ) ? (int) $red['active'] : 0 ) . '.' );

		$audit = $g( 'audit', null );
		if ( ! is_array( $audit ) ) {
			$add( 'audit', 'Audit günlüğü', 'warn', 'Audit tablosu yok veya okunamadı.' );
		} else {
			$chain = isset( $audit['chain'] ) && is_array( $audit['chain'] ) ? $audit['chain'] : array( 'ok' => true, 'checked' => 0, 'legacy' => 0 );
			$size  = round( ( isset( $audit['bytes'] ) ? (int) $audit['bytes'] : 0 ) / 1048576, 2 );
			$base  = ( isset( $audit['rows'] ) ? (int) $audit['rows'] : 0 ) . ' satır, ' . $size . ' MB; zincir: ' . (int) ( isset( $chain['checked'] ) ? $chain['checked'] : 0 ) . ' satır doğrulandı, ' . (int) ( isset( $chain['legacy'] ) ? $chain['legacy'] : 0 ) . ' eski sürüm satırı.';
			if ( empty( $chain['ok'] ) ) {
				$add( 'audit', 'Audit günlüğü', 'fail', 'Zincir BOZUK (satır ' . (int) ( isset( $chain['first_bad_id'] ) ? $chain['first_bad_id'] : 0 ) . ', ' . (string) ( isset( $chain['reason'] ) ? $chain['reason'] : '?' ) . '). ' . $base );
			} elseif ( isset( $audit['rows'] ) && (int) $audit['rows'] > 200000 ) {
				$add( 'audit', 'Audit günlüğü', 'warn', 'Tablo büyük; saklama temizliği çalışıyor mu kontrol edin. ' . $base );
			} else {
				$add( 'audit', 'Audit günlüğü', 'ok', $base );
			}
		}
		$cache = (array) $g( 'cache', array() );
		$add( 'cache', 'Önbellek', 'info', ! empty( $cache['enabled'] ) ? 'Açık (TTL 300 sn, nesil ' . ( isset( $cache['generation'] ) ? (string) $cache['generation'] : '?' ) . ').' : 'Kapalı: ' . ( isset( $cache['reason'] ) ? (string) $cache['reason'] : 'bilinmiyor' ) . '.' );

		return $out;
	}

	/** @return array<string,int> durum -> adet */
	public static function summary( array $checks ) {
		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( $checks as $check ) {
			if ( isset( $check['status'], $counts[ $check['status'] ] ) ) {
				$counts[ $check['status'] ]++;
			}
		}
		return $counts;
	}
}
