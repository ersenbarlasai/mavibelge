<?php
/**
 * Faz 6B1 — read-only target repository CONTRACT. This declares the
 * shape an adapter must provide to feed MaviBelge_Core_Import_Dry_Run_Planner
 * real WordPress data — the interface itself does NOT call any
 * WordPress function.
 *
 * Düzeltme ve Kabul §2.3 — bu docblock önceki turda "bu arayüzü
 * uygulayan hiçbir sınıf yok, yazmak Faz 6B2'nin işi" diyordu; bu artık
 * DOĞRU DEĞİL — gerçek, salt okunur uygulama
 * `class-import-wordpress-target-repository.php`'deki
 * `MaviBelge_Core_Import_WordPress_Target_Repository`'dir (Faz 6B2'de
 * yazıldı, bu turda §2.1/§2.2 bulgularıyla güçlendirildi).
 *
 * Every method here is a QUERY — none may have a side effect. No
 * implementation may call an insert/update/delete WordPress function
 * from inside any of these methods.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MaviBelge_Core_Import_Target_Repository {

	/**
	 * Finds every existing WordPress record (term or post, depending on
	 * $type) whose `_mb_import_source_key` equals $sourceKey.
	 *
	 * BAĞLAYICI ŞEKİL SÖZLEŞMESİ (Faz 6B1 Son Kapanış Düzeltmesi'nde
	 * `MaviBelge_Core_Import_Record_Validator::normalize_target_lookup()`
	 * ile katılaştırıldı — bir uygulama bu kurallara UYMAZSA planlayıcı
	 * sonucu kontrollü `invalid_target_state` conflict'ine düşürür):
	 *
	 * - Yalnız bu YEDİ anahtar dönebilir (kapalı küme): `target_found`,
	 *   `duplicate_targets`, `target_id`, `target_type_matches`,
	 *   `has_source_key_marker`, `last_applied_hash`, `current_managed_fields`.
	 * - `target_found`/`duplicate_targets`/`has_source_key_marker` GERÇEK
	 *   `bool` olmalı (0/1, "true"/"false" string'i KABUL EDİLMEZ).
	 * - `target_found=true` iken `target_type_matches` anahtarı MUTLAKA
	 *   verilmeli — GERÇEK türü kontrol ETMEDEN sessizce `true` DÖNME.
	 * - `target_found=true` iken `target_id` GERÇEK pozitif `int` olmalı
	 *   (WordPress'in post/term ID'si int döner; meta_query sonucundan
	 *   gelen bir değeri `(int)` cast'i BU ADAPTER, döndürmeden ÖNCE yapmalı).
	 * - `target_found=true` VE `target_type_matches=true` iken
	 *   `current_managed_fields` ASLA eksik/null OLAMAZ — ilgili tipin
	 *   `MaviBelge_Core_Import_Managed_Fields::SECTOR_FIELDS`/
	 *   `QUALIFICATION_FIELDS`/`FEE_FIELDS` allowlist'iyle TAM anahtar
	 *   eşitliği taşımalı VE her alanın DEĞERİ projeksiyonla AYNI PHP
	 *   tipinde olmalı (`level`/`sector_term_id`/`image_attachment_id` vb.
	 *   GERÇEK `int`, WordPress meta'sının ham string hâli DEĞİL —
	 *   `(int)`/`(bool)` cast'ini bu adapter yapar, saf katman yapmaz).
	 * - `target_found=false` iken `target_id`/`current_managed_fields`/
	 *   `has_source_key_marker=true`/dolu `last_applied_hash` gibi
	 *   "bulundu" anlamına gelecek HİÇBİR alan dönmemeli.
	 * - `_mb_last_applied_hash` yalnız `null` ya da tam `^[0-9a-f]{64}$`
	 *   (64 küçük-harf hex) olmalı; marker YOKKEN hash dönmemeli.
	 * - Faz 6B3 Önkoşul — isteğe bağlı SEKİZİNCİ anahtar `natural_key`:
	 *   marker ile hedef bulunamadığında (target_found=false,
	 *   duplicate_targets=false) adapter'ın SALT OKUNUR doğal anahtar
	 *   preflight sonucu; yalnız MaviBelge_Core_Import_Record_Validator::NATURAL_KEY_STATES
	 *   kümesinden bir string. `none` dışındaki her değer create'i engeller.
	 *   Verilmezse "kontrol edilmedi" sayılır: karar motoru geriye dönük
	 *   uyumluluk için create üretebilir, ama gelecekteki apply uygunluk
	 *   katmanı (MaviBelge_Core_Import_Apply_Eligibility) böyle bir create'i
	 *   ASLA yazma adayı saymaz. Gerçek WordPress adapter'ı bunu HER ZAMAN verir.
	 *
	 * @param string $type       one of MaviBelge_Core_Import_Dry_Run_Planner::TYPE_*
	 * @param string $sourceKey  Faz 6A manifest source_key (e.g. "sector:makine")
	 * @return array{
	 *     target_found: bool,
	 *     duplicate_targets: bool,
	 *     target_id: int|null,
	 *     target_type_matches: bool,
	 *     has_source_key_marker: bool,
	 *     last_applied_hash: string|null,
	 *     current_managed_fields: array|null,
	 * } — bkz. MaviBelge_Core_Import_Dry_Run_Planner'ın beklediği tam şekil.
	 */
	public function find_target_by_source_key( $type, $sourceKey );

	/**
	 * Sözleşme Eşitleme §2.2 / Zorunlu Dependency DTO Kapanışı §3.3 — bu üç
	 * resolver artık dokümantasyon seviyesinde değil, hem PHP dönüş TİPİ
	 * hem `MaviBelge_Core_Import_Record_Validator::resolve_verified_dependency()`'nin
	 * TALEP ETTİĞİ iç şekille BİREBİR AYNI TYPED dönüşü taşır: `int|null`
	 * DEĞİL, `?array` — kapalı `{id: int>0, type_verified: true}` (başarı)
	 * veya `null` (bulunamadı/doğrulanamadı). `?array` dönüş tipi PHP 7.3
	 * seviyesinde ZORUNLU kılınan tek şeydir (hangi anahtarları taşıdığı
	 * PHP'nin kendisi tarafından denetlenemez); iç `{id,type_verified}`
	 * şeklinin kapalı ve doğru olduğu runtime'da
	 * `MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape()`/
	 * `resolve_verified_dependency()` tarafından fail-closed doğrulanmaya
	 * devam eder.
	 *
	 * DÜRÜST SINIR (bu turda düzeltildi — önceki ifade "bir uygulama
	 * kontrol etmeden type_verified=true YAZAMAZ" gibi mutlak/yanlış bir
	 * teknik iddia taşıyordu, PHP bunu engellemez): bir adapter uygulaması
	 * türü GERÇEKTEN doğrulamadan `type_verified => true` YAZMAMALIDIR —
	 * bu bir davranışsal/sözleşmesel yükümlülüktür, dilin kendisinin
	 * zorunlu kıldığı bir kısıt DEĞİLDİR. Dış şekil (`?array` dönüş tipi +
	 * ortak validator'ın fail-closed iç-şekil kontrolü) yalnız BOZUK/
	 * eksik/fazla-anahtarlı bir typed değeri yakalar; adapter'ın `true`
	 * yazmadan ÖNCE gerçekten `get_term_by()`/`get_post_type()` ile türü
	 * kontrol ettiğini KANITLAMAZ — bu, Faz 6B2 kod incelemesinin
	 * doğrulaması gereken ayrı bir sorumluluktur.
	 *
	 * @param string $sectorSlug
	 * @return array{id: int, type_verified: true}|null Gerçek, pozitif
	 *   `mb_sektor` term ID'si doğrulanmış türle birlikte, ya da bulunamadı/
	 *   doğrulanamadıysa `null`. Kapalı iki-anahtarlı şekil dışında hiçbir
	 *   anahtar taşınmaz.
	 */
	public function resolve_sector_term_id( $sectorSlug ): ?array;

	/**
	 * @param string $mykCode
	 * @return array{id: int, type_verified: true}|null Gerçek, pozitif
	 *   `mb_yeterlilik` post ID'si doğrulanmış türle birlikte, ya da
	 *   bulunamadı/birden fazla eşleşme/doğrulanamadıysa `null` (birden
	 *   fazla eşleşme durumunda bu metod `null` döner — "ambiguous" sinyali
	 *   Faz 6B2'nin açık kararı, bkz. `faz6b2-entegrasyon-notu.md` §3.2).
	 */
	public function resolve_qualification_post_id( $mykCode ): ?array;

	/**
	 * @param string $sectorSlug
	 * @return array{id: int, type_verified: true}|null Gerçek, DOĞRULANMIŞ
	 *   (attachment post_type) medya ID'si doğrulanmış türle birlikte, ya da
	 *   henüz çözülmediyse `null`. `0` DÖNMEZ — "henüz çözülmedi" ile
	 *   "kaynakta görsel yok" (planlayıcı tarafında zaten ayrı ele
	 *   alınıyor, bkz. plan_sector()) burada KARIŞTIRILMAMALIDIR: bu metod
	 *   yalnız GERÇEKTEN görseli olan bir sektör için çağrılmalıdır.
	 */
	public function resolve_sector_image_attachment_id( $sectorSlug ): ?array;

	/**
	 * Düzeltme ve Kabul §2.3 — kök neden: `MaviBelge_Core_Import_Dry_Run_Service`
	 * bu metodu (arayüzde HİÇ tanımlı olmadan) çağırıyordu; arayüzü
	 * doğru şekilde uygulayan ama bu ek metodu taşımayan başka bir
	 * adapter fatal verirdi. `method_exists()` gibi bir "incir yaprağı"
	 * KULLANILMADI — metot doğrudan arayüze eklendi, her uygulama
	 * (gerçek WordPress adapter'ı + `tests/run.php`'deki test double'lar)
	 * bunu taşımak ZORUNDADIR.
	 *
	 * Çağrı sırasında biriken, gizli/hassas veri TAŞIMAYAN tanı
	 * kayıtlarını döner — yalnız sabit neden kodu + tip + manifest'teki
	 * mevcut source_key/slug/MYK kodu. `resolve_qualification_post_id()`'nin
	 * "duplicate yeterlilik" bulgusu gibi, tek bir dönüş değerine (`null`)
	 * sığmayan ek bilgi için kullanılır. Dizi KAPALI `{code, type, source_key}`
	 * şekli taşır — HİÇBİR fazladan/hassas alan taşımamalıdır (çağıran
	 * taraf, ör. WP-CLI/admin çıktısı, bu değerleri doğrudan görüntüler).
	 *
	 * @return array<int, array{code:string, type:string, source_key:string}>
	 */
	public function get_diagnostics();
}

/**
 * Faz 7 içerik aktarımı — İSTEĞE BAĞLI ayrı arayüz. `MaviBelge_Core_Import_Target_Repository`
 * (üç katalog resolver'ı + get_diagnostics) BİLEREK değiştirilmedi: onu uygulayan
 * mevcut adapterlar/test double'ları kırılmasın. Haber türü terimini çözebilen bir
 * repository ek olarak bunu uygular; uygulamayan repository'de haber kayıtları
 * `blocked_dependency` olur (tür ASLA tahmin edilmez).
 *
 * Metot bir SORGUDUR (yan etki YOK): iki kontrollü `mb_haber_turu` terimi
 * (`haber`, `duyuru`) import tarafından OLUŞTURULMAZ, yalnız çözülür.
 */
interface MaviBelge_Core_Import_Content_Dependency_Resolver {

	/**
	 * @param string $typeSlug `haber` | `duyuru`
	 * @return array{id: int, type_verified: true}|null Gerçek, pozitif `mb_haber_turu`
	 *   term ID'si (taksonomi doğrulanmış), bulunamadı/doğrulanamadıysa `null`.
	 */
	public function resolve_news_type_term_id( $typeSlug ): ?array;
}
