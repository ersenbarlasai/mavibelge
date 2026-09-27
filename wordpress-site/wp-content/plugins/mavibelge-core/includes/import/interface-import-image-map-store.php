<?php
/**
 * Faz 6B4 son kabul düzeltmesi — sektör görsel eşlemesinin ATOMİK kaydı için dar depo sözleşmesi.
 *
 * `MaviBelge_Core_Import_Sector_Image_Map::save()` option yazımı + audit kaydını tek transaction'da yapar; bu arayüz o
 * işlemin dokunduğu HER dış etkiyi (option, audit, transaction, önbellek, attachment incelemesi) soyutlar. Yalnız bu
 * işlemin dokunduğu option ve audit tabloları için altyapı doğrulaması yapılır: import run tabloları KURULMAZ ve
 * doğrulanmaz (eşleme, run tabloları kurulmadan önce kaydedilebilir).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MaviBelge_Core_Import_Image_Map_Store {

	/** Eşzamanlı kaydı serileştiren kilit (bloklayıcı, sınırlı bekleme). Alınamazsa false; alınamadıysa unlock() ÇAĞRILMAZ. @return bool */
	public function lock();

	/** Kilidi bırakır. @return bool */
	public function unlock();

	/** Option + audit tabloları InnoDB/utf8mb4 (SALT OKUNUR) ve audit hedefi hazır mı? Tablo kurmaz. @return bool */
	public function ready();

	/** Depodaki ham option değeri; option yoksa null. @return mixed */
	public function read();

	/** Attachment incelemesi: array{post_type,post_status,mime,readable}|null. @param int $id @return array|null */
	public function inspect( $id );

	/** @return bool */
	public function begin();

	/**
	 * Option'ı (autoload=no) yazar. @param array $stored Kapalı zarf. @param bool $exists Option zaten var mı.
	 *
	 * @return bool
	 */
	public function write( array $stored, $exists );

	/** Audit kaydı; yazılamazsa false. @param array $context old_digest, new_digest, changed_slugs. @return bool */
	public function audit( array $context );

	/** @return bool */
	public function commit();

	/** @return bool Başarısızsa çağıran işlemi başarısız sayar ve sabit hata kodu döndürür. */
	public function rollback();

	/** Option önbelleklerini (option/alloptions/notoptions) temizler; geri alınmış yeni değer kalmamalıdır. */
	public function flush();
}
