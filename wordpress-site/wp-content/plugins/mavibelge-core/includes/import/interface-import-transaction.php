<?php
/**
 * Faz 6B3 — batch transaction sözleşmesi.
 *
 * Her batch kendi veritabanı transaction'ı içinde atomiktir. Bu arayüz
 * HTTP istekleri veya CLI süreçleri arasında küresel bir transaction
 * VAAT ETMEZ. `begin()`/`commit()`/`rollback()` sonuçları çağıran tarafından
 * kontrol edilmek ZORUNDADIR; `false` fail-closed demektir.
 *
 * WordPress hook'larının veritabanı DIŞI yan etkileri (dosya, e-posta, dış
 * HTTP, kalıcı nesne önbelleği) transaction ile geri alınamaz; bu faz medya
 * oluşturmaz, e-posta göndermez ve dış servis çağırmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MaviBelge_Core_Import_Transaction {

	/**
	 * Transaction'ın gerçekten atomik olabileceğini SALT OKUNUR doğrular
	 * (ör. yazılacak her tablonun transactional motor kullanması).
	 *
	 * @return array{ok: bool, error: string|null}
	 */
	public function preflight();

	/** @return bool */
	public function begin();

	/** @return bool */
	public function commit();

	/** @return bool Başarısızsa çağıran run'ı fail-closed işaretler. */
	public function rollback();
}
