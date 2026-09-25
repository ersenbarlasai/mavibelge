<?php
/**
 * Faz 10 — çalışma zamanı sürüm uyumluluk kontrolü. Bu dosya BİLEREK eski PHP sözdizimiyle yazılmıştır (tür ipucu,
 * kısa dizi, ?? yok) ki desteklenmeyen bir PHP'de bile ayrıştırılıp uyarı verebilsin; eklentinin geri kalanı yüklenmez.
 * Saf fonksiyon: WordPress fonksiyonu çağırmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Compat {

	const MIN_PHP = '7.3';
	const MIN_WP  = '6.9';

	/**
	 * @param string $phpVersion
	 * @param string $wpVersion
	 * @return string[] Karşılanmayan gereksinimlerin Türkçe açıklamaları (boş = uyumlu).
	 */
	public static function unmet( $phpVersion, $wpVersion ) {
		$problems = array();
		if ( version_compare( (string) $phpVersion, self::MIN_PHP, '<' ) ) {
			$problems[] = 'PHP ' . self::MIN_PHP . ' veya üstü gerekir (bulunan: ' . $phpVersion . ').';
		}
		if ( '' !== (string) $wpVersion && version_compare( (string) $wpVersion, self::MIN_WP, '<' ) ) {
			$problems[] = 'WordPress ' . self::MIN_WP . ' veya üstü gerekir (bulunan: ' . $wpVersion . ').';
		}
		return $problems;
	}
}
