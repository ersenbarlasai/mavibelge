# Faz 6B2 için Entegrasyon Notu — Salt Okunur Keşif, Bağımlılık Çözümü, WP-CLI/Admin Dry-Run Arayüzü

> Bu bir **not**tur, kod değildir. Faz 6B1'in saf karar motorunu (`includes/import/**`) gerçek WordPress verisine bağlayacak Faz 6B2 için hazırlık. **Faz 6B2 kodu bu görevde YAZILMADI.**

## 1. Neyin hazır olduğu

- `MaviBelge_Core_Import_Hash` — deterministik hash (saf).
- `MaviBelge_Core_Import_Managed_Fields` — allowlist + projeksiyon (saf).
- `MaviBelge_Core_Import_Decision` — üç-hash karar tablosu (saf).
- `MaviBelge_Core_Import_Dry_Run_Planner` — bunların hepsini birleştiren planlayıcı (saf) — girdi olarak `$targetLookups` ve `$dependencies` dizilerini BEKLER, kendisi hiçbir WordPress sorgusu yapmaz.
- `MaviBelge_Core_Import_Target_Repository` — Faz 6B2'nin dolduracağı salt-okunur arayüz SÖZLEŞMESİ (uygulama yok).

## 2. Faz 6B2'nin yazması gereken (yazılmadı, yalnız kapsamı burada)

### 2.1 `MaviBelge_Core_Import_Target_Repository`'nin gerçek bir uygulaması

- `find_target_by_source_key( $type, $sourceKey )`: `get_terms()`/`get_posts()`'u `meta_query` ile `_mb_import_source_key = $sourceKey` filtresiyle çağırır (SALT OKUNUR). Birden fazla sonuç dönerse `duplicate_targets=true` işaretlenir — **ilk sonucu sessizce seçme YOK**. Bulunan kaydın gerçek türü (`post_type`/taxonomy) `$type` ile uyuşmuyorsa `target_type_matches=false`. **Faz 6B1 Düzeltme ve Kabul'de eklenen, Son Kapanış Düzeltmesi'nde katılaştırılan `MaviBelge_Core_Import_Record_Validator::normalize_target_lookup()` kapısı** artık şu şekli ZORUNLU kılıyor — Faz 6B2'nin uygulaması bunlara UYMAK ZORUNDA, aksi halde planlayıcı sonucu `invalid_target_state` conflict'ine düşürür:
  - **Dönüş değeri HER ZAMAN gerçek bir dizi olmalı** — `null`/`false`/istisna DEĞİL (hedef yoksa `array('target_found' => false)` gibi minimal ama GERÇEK bir dizi dön).
  - **Yalnız YEDİ anahtar** dönebilir (kapalı küme): `target_found`, `duplicate_targets`, `target_id`, `target_type_matches`, `has_source_key_marker`, `last_applied_hash`, `current_managed_fields`. Fazladan bir anahtar (ör. hata ayıklama amaçlı eklenen bir alan) TÜM lookup'ı `invalid_target_state` yapar.
  - `target_found`/`duplicate_targets`/`has_source_key_marker` GERÇEK `bool` olmalı (`0`/`1`, `"true"`/`"false"` string'i KABUL EDİLMEZ — `(bool)` cast'ini bu adapter, döndürmeden ÖNCE yapmalı).
  - `target_found=true` iken `target_id` GERÇEK pozitif `int` (WordPress'in `get_the_ID()`/term ID'si zaten int döner, ama meta_query sonucundan gelen bir değer yanlışlıkla string/float olabilir — çağıran taraf `(int)` cast'ini KENDİSİ, döndürmeden ÖNCE yapmalı).
  - **`target_found=true` iken `target_type_matches` anahtarı MUTLAKA verilmeli** — gerçek türü KONTROL ETMEDEN sessizce `true` dönmek artık `invalid_target_state`'e düşer (bu, adapter'ın "hedefin gerçek türünü kontrol ettiğinin kanıtı"dır).
  - `target_type_matches` GERÇEK `bool`.
  - `target_found=false` iken `target_id`/`current_managed_fields`/dolu `last_applied_hash`/`has_source_key_marker=true`/`target_type_matches=false` gibi "bulundu" anlamına gelecek HİÇBİR alan dönmemeli.
  - Marker YOKKEN (`has_source_key_marker=false`) `last_applied_hash` dönmemeli (dolu bir hash, marker olmadan anlamsızdır).
  - `_mb_last_applied_hash` ya `null` ya `^[0-9a-f]{64}$`.
  - **`target_found=true` VE `target_type_matches=true` iken `current_managed_fields` ARTIK eksik/null OLAMAZ** — gerçek, türü uyuşan bir hedef bulunduysa mevcut durumu OKUMAK zorunludur. İlgili tipin allowlist'iyle (bkz. §2.2) TAM anahtar eşitliği taşımalı (eksik/fazla alan bırakmamalı) VE **her alanın DEĞERİ projeksiyonla AYNI PHP tipinde olmalı** — `level`/`sector_term_id`/`image_attachment_id`/`qualification_post_id`/`source_page`/`certificate_print_fee_kurus` GERÇEK `int`, `vat_included` GERÇEK `bool`, WordPress meta'sının ham STRING hâli DEĞİL (WordPress post-meta/term-meta değerleri varsayılan olarak string döner — bu `(int)`/`(bool)` cast'ini bu adapter, döndürmeden ÖNCE yapmak ZORUNDADIR; saf katman bunu yapmaz, yalnız tipi doğrular).
- `resolve_sector_term_id( $slug )`: `get_term_by( 'slug', $slug, 'mb_sektor' )` — salt okunur.
- `resolve_qualification_post_id( $mykCode )`: `_mb_myk_code` meta_query ile `get_posts()` — salt okunur; birden fazla sonuç aynı şekilde `null` (unresolved) döner (bu metodun dönüş tipi Sözleşme Eşitleme'de `{id, type_verified:true}|null`'a çıktı — bkz. §2.1a); çağıran kod tarafında ayrıca bir "ambiguous" sinyali de düşünülmeli — bu görevde arayüz bunu henüz zorunlu kılmıyor, Faz 6B2'nin açık kararı gerekir (bkz. §3.2).
- `resolve_sector_image_attachment_id( $slug )`: gerçek bir medya eşleştirme stratejisi GEREKİR (dosya adı/yol eşleştirmesi, elle harita, ya da "henüz çözülmüyor, hep null dön" gibi bilinçli bir MVP kararı) — bu görevde bir strateji SEÇİLMEDİ, yalnız arayüz imzası tanımlandı.

### 2.1a Dependency map artık `{id, type_verified}` typed sonucu gerektiriyor (Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri, Sözleşme Eşitleme'de arayüzle eşitlendi)

`MaviBelge_Core_Import_Dry_Run_Planner::plan()`'a verilecek `$dependencies` dizisindeki (`sector_term_ids`/`sector_image_attachment_ids`/`qualification_post_ids`) her ÇÖZÜM artık salt bir `int` DEĞİL — `MaviBelge_Core_Import_Record_Validator::resolve_verified_dependency()` şu TAM şekli zorunlu kılar:

```php
$dependencies['sector_term_ids']['makine'] = array( 'id' => 42, 'type_verified' => true );
```

**Sözleşme Eşitleme:** `resolve_sector_term_id()`/`resolve_qualification_post_id()`/`resolve_sector_image_attachment_id()` arayüz metotlarının kendisi artık eski `int|null` DEĞİL, doğrudan bu AYNI typed şekli döner — `interface-import-target-repository.php` bkz. güncel docblock. **Zorunlu Dependency DTO Kapanışı'nda dürüstleştirildi:** arayüz metotları artık PHP dönüş tipi olarak `?array` TAŞIR (PHP 7.3 seviyesinde ZORUNLU tek şey budur), ama bu tek başına bir uygulamanın türü GERÇEKTEN doğrulamadan `type_verified => true` döndürmesini FİZİKSEL olarak ENGELLEMEZ — PHP bunu denetleyemez. Doğru ifade: bir Faz 6B2 uygulaması türü GERÇEKTEN kontrol etmeden `type_verified => true` DÖNDÜRMEMELİDİR (davranışsal/sözleşmesel yükümlülük), ve bunun GERÇEKTEN yapıldığı Faz 6B2 kod incelemesinin doğrulaması gereken AYRI bir sorumluluktur. Bu nedenle **orkestrasyon kodunun ayrıca bir "int'i typed sonuca sarma" adımı YAPMASINA gerek yoktur** — her resolver kendi typed sonucunu ÜRETİR, orkestrasyon kodu bunu `$dependencies` haritasına OLDUĞU GİBİ koyar:

```php
$typed = $repository->resolve_sector_term_id( $slug ); // null veya {id, type_verified:true}
if ( null !== $typed ) {
    $dependencies['sector_term_ids'][ $slug ] = $typed;
}
// null ise anahtar hiç eklenmez -> unresolved -> blocked_dependency.
```

Her resolver'ın kendi içinde şunu yapması ZORUNLUDUR:

1. Döndürülen ID'nin GERÇEKTEN beklenen türde olduğunu (sektör için gerçek `mb_sektor` term'ü, yeterlilik için gerçek `mb_yeterlilik` post'u, görsel için gerçek bir attachment post'u) doğrulamalı — bu resolver'ların KENDİSİ zaten doğru taksonomi/post_type'a sorgu attığı için bu doğrulama genelde sorgunun İÇİNDE zaten sağlanmış olur, ama metod bunu AÇIKÇA `type_verified => true` ile dönüş değerine yansıtmalıdır,
2. Yalnız BU doğrulama GERÇEKTEN yapıldıysa `array('id' => $id, 'type_verified' => true)` dönmeli,
3. Doğrulama yapılamadıysa/hedef bulunamadıysa/birden fazla eşleşme varsa (duplicate) `null` DÖNMELİ — sessizce `type_verified=false` içeren bir dizi dönüp "aslında çözülmedi ama öyle görünen" bir sonuç üretmemeli.

Kapalı iki-anahtarlı şekil ZORUNLUDUR — üçüncü bir anahtar (ör. hata ayıklama notu) eklenirse `resolve_verified_dependency()` tüm çözümü GEÇERSİZ sayar.

**Zorunlu Dependency DTO Kapanışı (bu tur):** `plan()`'a verilecek `$dependencies` dizisinin ÜÇ üst anahtarı (`sector_term_ids`, `sector_image_attachment_ids`, `qualification_post_ids`) artık HER ZAMAN, hiçbiri eksik OLMADAN verilmelidir — Faz 6B2 orkestrasyon kodu her plan turunda üçünü de (hiçbiri kullanılmayacak olsa BİLE) en azından boş dizi (`array()`) olarak doldurmalıdır. Eksik bir anahtar, `validate_dependencies_shape()` tarafından artık YAPISAL hata sayılır ve TÜM plan() çağrısını (yalnız o kaydı değil) fail-closed reddeder. Ayrıca her map'in anahtarları (sektör/görsel map'lerinde slug, yeterlilik map'inde MYK kodu) ilgili biçimi taşımalı ve her değer kapalı `{id, type_verified}` şeklinde olmalıdır — bu, o map o turda HİÇ KULLANILMASA (ör. görselsiz bir sektöre denk gelen bozuk bir `sector_image_attachment_ids` girdisi) bile geçerlidir.

### 2.2 `current_managed_fields` üretimi

Gerçek bir hedef bulunduğunda, `MaviBelge_Core_Import_Managed_Fields::SECTOR_FIELDS`/`QUALIFICATION_FIELDS`/`FEE_FIELDS` listesindeki HER alanın WordPress'teki GERÇEK şu anki değerini (get_term/get_post_meta ile, salt okunur) aynı adlarla (`slug`, `name`, ..., `qualification_post_id`, ...) bir diziye toplayıp `find_target_by_source_key()`'in `current_managed_fields` anahtarına koymak Faz 6B2'nin işi. Bu dizi `MaviBelge_Core_Import_Hash::hash()`'e AYNI şekilde verilecek — planlayıcı zaten bunu yapıyor (`class-import-dry-run-planner.php::build_entry()`).

### 2.3 WP-CLI / admin dry-run arayüzü

- `raporlar/veri-aktarim-raporlari/faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md` §7'deki WP-CLI/admin fallback tasarımı hâlâ geçerli: `wp mavibelge import catalog --dry-run` (varsayılan), WP-CLI yoksa nonce korumalı, salt-okunur bir admin ekranı.
- Bu arayüz yalnız `MaviBelge_Core_Import_Dry_Run_Planner::plan()`'ı ÇAĞIRIR ve sonucu (bkz. `faz6b1-dry-run-cikti-semasi.md`) insan-okunur bir tabloya döker — kendi karar mantığını YAZMAZ, planlayıcıyı tekrar ETMEZ.
- Bu adım da **hiçbir yazma yapmaz** — yalnız `plan()`'ın salt-okunur çıktısını gösterir.

### 2.4 Manifest kayıtları `plan()`'a OLDUĞU GİBİ verilmeli (Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri — YENİ)

`plan()` artık her tür listesi için `source_index`'in gerçek liste konumuyla eşleştiğini VE tüm kayıtların aynı `source.file`/`source.sha256`'ya işaret ettiğini doğruluyor (bkz. `faz6b1-karar-motoru-ve-hash-sozlesmesi.md` §3.7). Bu nedenle Faz 6B2'nin manifest-yükleme adımı `data/content/{sectors,qualifications,fees}.manifest.json`'daki `records[]` dizilerini **filtrelemeden/yeniden sıralamadan/bölmeden** OLDUĞU GİBİ (tam liste, orijinal sıra) `plan()`'a vermelidir — bir alt küme (ör. "yalnız değişen kayıtlar") göndermek istenirse, o alt kümenin KENDİ `source_index`'i o alt-listenin gerçek konumuna göre yeniden numaralandırılmalıdır, aksi halde toplu plan `errors` doldurup HİÇBİR entry üretmez.

## 3. Faz 6B1'den kalan açık kararlar (Faz 6B2 başlamadan önce netleşmeli)

1. Sektör görseli eşleştirme stratejisi (yukarıda §2.1) — MVP karar gerekiyor.
2. `resolve_qualification_post_id()`'in birden fazla eşleşme bulduğu durumda arayüzün nasıl sinyal vereceği (şu an yalnız `null` (unresolved) döner — Sözleşme Eşitleme'de dönüş tipi `{id, type_verified:true}|null`'a çıktı, ama "ambiguous" durumunu `duplicate_targets` mantığına nasıl bağlayacağı netleşmedi).
3. Gerçek PHP/WordPress runtime testi — `class-import-hash.php`'nin JSON kodlama davranışının GERÇEK bir PHP yorumlayıcısında (bu ortamda yok) doğrulanması hâlâ açık.

Bu üç madde çözülmeden Faz 6B2 **başlatılmamalıdır**.
