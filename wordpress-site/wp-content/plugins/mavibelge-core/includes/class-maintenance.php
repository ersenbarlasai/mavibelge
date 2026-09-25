<?php
/**
 * Faz 10 — günlük bakım (WP-Cron: `mavibelge_core_daily_maintenance`).
 *
 * GERÇEK SİSTEM CRON'U VARSAYILMAZ: WP-Cron yalnız site ziyareti olduğunda çalışır; `DISABLE_WP_CRON` true ise ve sunucuda
 * gerçek bir cron yoksa bakım HİÇ çalışmaz (sağlık ekranı bunu uyarır). Zamanlama idempotenttir (`wp_next_scheduled`),
 * devre dışı bırakmada `wp_clear_scheduled_hook` ile kaldırılır.
 *
 * Yapılanlar: (1) audit saklama silmesi (form 90 / diğer 365 gün); (2) `mavibelge-forms-tmp` dizininde 1 saatten eski,
 * rastgele adlı (32 hex + uzantı) geçici dosyaların silinmesi. Süresi dolmuş `mbf_*` transient'ları WordPress tarafından
 * silinir; özel işlem gerekmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Maintenance {

	const HOOK          = 'mavibelge_core_daily_maintenance';
	const TMP_MAX_AGE   = 3600;
	const TMP_DIR       = 'mavibelge-forms-tmp';

	/* ------------------------------------------------------------- SAF kısım */

	/**
	 * Geçici form dosyası silinmeli mi? YALNIZ `Forms_Security::random_filename()` biçimli (32 hex + . + uzantı) ve
	 * `$maxAge` saniyeden eski dosyalar; `.htaccess`/`index.php` ve diğer adlar ASLA silinmez.
	 */
	public static function is_stale_temp_file( $name, $mtime, $now, $maxAge = self::TMP_MAX_AGE ) {
		if ( ! is_string( $name ) || 1 !== preg_match( '/^[0-9a-f]{32}\.[a-z0-9]{1,5}$/', $name ) ) {
			return false;
		}
		return is_int( $mtime ) && $mtime > 0 && ( (int) $now - $mtime ) > (int) $maxAge;
	}

	/* -------------------------------------------------- WordPress bağlantısı */

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
	}

	/** Idempotent zamanlama: zaten planlıysa hiçbir şey yapmaz. @return bool Planlı mı. */
	public static function schedule() {
		if ( false !== wp_next_scheduled( self::HOOK ) ) {
			return true;
		}
		return false !== wp_schedule_event( time() + 300, 'daily', self::HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** @return array{audit: array|null, tmp_deleted: int} */
	public static function run() {
		$audit = MaviBelge_Core_Audit_Log::prune();
		return array( 'audit' => $audit, 'tmp_deleted' => self::cleanup_forms_tmp() );
	}

	/** @return int Silinen dosya sayısı. */
	public static function cleanup_forms_tmp( $now = null ) {
		$uploads = wp_get_upload_dir();
		if ( ! is_array( $uploads ) || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return 0;
		}
		$dir = $uploads['basedir'] . '/' . self::TMP_DIR;
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$now     = null === $now ? time() : (int) $now;
		$deleted = 0;
		$entries = scandir( $dir );
		foreach ( is_array( $entries ) ? $entries : array() as $name ) {
			$path  = $dir . '/' . $name;
			$mtime = is_file( $path ) && ! is_link( $path ) ? filemtime( $path ) : false;
			if ( is_int( $mtime ) && self::is_stale_temp_file( $name, $mtime, $now ) && unlink( $path ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}
}
