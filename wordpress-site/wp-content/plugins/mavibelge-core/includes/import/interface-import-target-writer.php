<?php
/**
 * Faz 6B3 — DAR kapsamlı yazma adapterı sözleşmesi.
 *
 * Salt okunur `MaviBelge_Core_Import_Target_Repository` arayüzü DEĞİŞTİRİLMEZ;
 * yazma ayrı, bu arayüzün arkasındadır. Yalnız
 * `MaviBelge_Core_Import_Write_Payload::prepare()`'in ürettiği, önceden
 * doğrulanmış kanonik yükü yazar; import tarafından yönetilmeyen hiçbir
 * alana dokunmaz. Her yazma, yazılan her değeri saklanan kanonik
 * temsilden KATI biçimde geri okuyarak doğrular (`update_*_meta()`'nın
 * `false` dönüşü "hata" mı "değer değişmedi" mi, yalnız bu okuma ayırır).
 *
 * Bütün metotlar `array{ok: bool, id: int|null, error: string|null}` döner;
 * `error` yalnız sabit, içerik taşımayan bir hata kodudur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MaviBelge_Core_Import_Target_Writer {

	/** @param array $payload `Write_Payload::prepare()` yükü (type=sector). */
	public function create_sector( array $payload );

	/** @param int $termId */
	public function update_sector( $termId, array $payload );

	/** @param array $payload `Write_Payload::prepare()` yükü (type=qualification|fee). */
	public function create_post( array $payload );

	/** @param int $postId */
	public function update_post( $postId, array $payload );

	/**
	 * Create rollback'i (DAR operasyon): (1) kalıcı silme olmadığını doğrular
	 * (EMPTY_TRASH_DAYS=0 fail-closed), (2) postun yönetilmeyen-durum parmak
	 * izinin create sonrası kaydedilenle AYNI olduğunu doğrular (uyuşmazlık
	 * `drift_detected`, HİÇBİR şey yazılmaz), (3) YALNIZ import tarafından
	 * yönetilen post meta alanlarını ve yönetilen sektör ilişkilerini kaldırır
	 * (marker/doğal anahtarı oluşturan alanlar; readback ile doğrulanır,
	 * parmak izi hâlâ aynı olmalı), (4) postu çöp kutusuna alır (kalıcı silmez).
	 * Yönetilmeyen içeriğe dokunulmaz. Çağıran bunu batch transaction'ı içinde
	 * çalıştırır; herhangi bir hata batch'i TAMAMEN geri alır.
	 *
	 * @param int    $postId
	 * @param string $postType Beklenen post type (başka türdeki bir kayda dokunulmaz).
	 * @param string $expectedFingerprint Create sonrası kaydedilen 64-hex parmak izi.
	 */
	public function rollback_created_post( $postId, $postType, $expectedFingerprint );

	/**
	 * Create rollback'i: mb_sektor terimini siler (terimlerin çöp kutusu yoktur).
	 * Silmeden önce yönetilmeyen-durum parmak izi (parent, term_group,
	 * yönetilmeyen term meta) kaydedilenle AYNI olmalıdır; değilse `drift_detected`.
	 *
	 * @param int    $termId
	 * @param string $expectedFingerprint
	 */
	public function delete_sector_term( $termId, $expectedFingerprint );

	/**
	 * Yönetilen alanlar DIŞINDAKİ durumun parmak izi (update öncesi/sonrası
	 * karşılaştırılır; fark varsa yönetilmeyen bir alana dokunulmuş demektir).
	 *
	 * @param string $type sector|qualification|fee
	 * @param int    $id
	 * @return string|null Okunamazsa null (fail-closed).
	 */
	public function unmanaged_fingerprint( $type, $id );

	/**
	 * Sektör terimi silinmeden önce SALT OKUNUR dış bağımlılık taraması.
	 *
	 * @param int $termId
	 * @return array{ok: bool, slug: string|null, object_ids: int[], fee_ids: int[], trashed_ids: int[], child_count: int}
	 *   object_ids: terime bağlı TÜM nesneler (çöp kutusu dahil);
	 *   fee_ids: `_mb_sector_slug` ile bu terimin slug'ına işaret eden TÜM ücretler (çöp dahil);
	 *   trashed_ids: bu iki listedeki postlardan durumu `trash` olanlar;
	 *   child_count: alt terim sayısı.
	 */
	public function sector_term_references( $termId );

	/**
	 * Faz 12 — bir çekirdek `page` kaydının durumu (SALT OKUNUR).
	 *
	 * @param int $postId
	 * @return string|null post_status; sayfa yoksa/okunamazsa null.
	 */
	public function page_status( $postId );

	/**
	 * Faz 12 — YALNIZ `draft` durumundaki bir sayfayı `publish` yapar; başka hiçbir alan değişmez. Katı readback yapar.
	 *
	 * @param int $postId
	 * @return array{ok: bool, id: int|null, error: string|null}
	 */
	public function publish_page( $postId );

	/**
	 * Faz 12b — bir içerik kaydının (SSS: mb_sss / referans: mb_referans) durumu (SALT OKUNUR).
	 *
	 * @param string $type   'faq' | 'reference'
	 * @param int    $postId
	 * @return string|null post_status; kayıt yoksa/türü uyuşmuyorsa null.
	 */
	public function content_post_status( $type, $postId );

	/**
	 * Faz 12c — dosya sistemi yan etkisi kapsamı (DB transaction'ı dosyaları geri almaz). Batch başında açılır; YALNIZ bu batch'te
	 * YENİ oluşturulan dosyalar/attachment'lar kaydedilir (yeniden kullanılan mevcut attachment/dosya ASLA kaydedilmez).
	 *
	 * @return bool Kapsam açıldı mı (zaten açıksa false).
	 */
	public function begin_side_effect_scope();

	/** Başarılı DB commit sonrası kapsamı kapatır; kaydedilen dosyalar KALIR. @return bool */
	public function commit_side_effect_scope();

	/**
	 * DB rollback sonrası telafi. $dbRolledBack false ise (rollback başarısız) HİÇBİR dosya silinmez (fail-closed).
	 * Yalnız bu kapsamda oluşturulmuş, uploads sınırı içindeki, attachment satırı artık olmayan doğrulanmış dosyalar silinir.
	 *
	 * @param bool $dbRolledBack
	 * @return array{ok: bool, removed: int, error: string|null} Hata kodları sabittir; mutlak yol içermez.
	 */
	public function compensate_side_effect_scope( $dbRolledBack );
}
