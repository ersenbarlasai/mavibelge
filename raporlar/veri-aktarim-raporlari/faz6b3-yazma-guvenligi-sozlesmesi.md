# Faz 6B3 Önkoşul — Yazma Güvenliği Sözleşmesi

> Tarih: 23 Eylül 2026.
>
> **Durum:**
> - Bu bir **önkoşul ve sözleşme** turudur. Faz 6B3 apply/import **başlamadı**; `--apply` yok; üretim kodunda hiçbir import yazma çağrısı yok.
> - WordPress'e katalog verisi **yazılmadı**. Yalnız yerel, izole test veritabanına test fixture'ları yazıldı.
> - Canlı, staging, DirectAdmin, FTP, SSH, DNS, SSL ve mail sistemine dokunulmadı. Commit, push ve deploy yapılmadı.
> - Claude bu çalışmaya kabul vermez; teslim Codex bağımsız incelemesine sunulur.
> - **Son Kabul Düzeltmesi (23 Eylül 2026):** Codex iki açık buldu — `update_metadata_by_mid()` yolunda ret işaretinin veritabanına yazılabilmesi ve rollback kaydında hash–alan bütünlüğünün doğrulanmaması. Önkoşul kabulü bu nedenle **yeniden açıldı**. Önceki turda normal `update_metadata()`/`add_metadata()` yolu testlerinin (27/27) geçtiği kaydı korunur. İki açık §0.1'de düzeltildi; gerçek testlerden sonra Codex bağımsız incelemesine yeniden sunuldu. Claude kabul vermez.
> - **By-Mid Kapsam Kapanışı (24 Eylül 2026):** Codex bu iki açığın kapandığını doğruladı. Yeni bulgu: global by-mid filtresi MaviBelge dışı anahtarlarda da `sanitize_meta()` çağırıyor, harici sanitizer'lar iki kez çalışıyordu. Koruma yalnız MaviBelge alanlarına kapsamlandı (§0.1 madde 7); üçüncü taraf meta davranışı WordPress'e bırakıldı. Codex bağımsız incelemesine yeniden sunuldu. Claude kabul vermez.
>
> **Kanıt:**
> - Gerçek PHP 7.3.33 ve izole WordPress 6.9.9 (`wordpress-site/tools/runtime-test/`) üzerinde çalıştırılan testler.
> - Kaynak eşlemesi:
>   - `tests/run.php` → §1, §3, §4, §6, §8, §9, §10
>   - `scripts/write-safety-test.php` → §1, §3, §4
>   - `scripts/fixtures-natural-key.php` + CLI dry-run → §5, §6, §7

## 0. Kapatılan dört tasarım riski

| # | Risk | Kök neden | Çözüm | Kanıt |
|---|---|---|---|---|
| 1 | Kanonik kuruşun 100 ile ikinci kez çarpılması | `_mb_certificate_print_fee_kurus` `money_try` tipindeydi. Kayıtlı `register_post_meta` sanitize callback'i her `update_post_meta` çağrısında değeri TL sayıp kuruşa çeviriyordu. **Mevcut admin kaydetme döngüsü ve `write_meta()` de etkileniyordu**: 1.500 TL girildiğinde 15.000.000 kuruş saklanıyordu (izole WordPress'te ölçüldü). | Yeni `money_kurus` tipi: saklanan temsil kanonik integer kuruş, kayıtlı sanitize idempotent. TL girişi yalnız admin form katmanında (`admin_input_to_storage()`) çevrilir. `money_try` kaldırıldı. | PHP: 150000 → 150000, üç kez sanitize → 150000. WP: admin zinciri "1.500" → **150000**. 103 gerçek ücret yükü sanitize yolundan değişmeden geçer. |
| 2 | Bozuk/eksik/serileştirilmiş marker'ın hedefi görünmez kılıp yanlış `create` üretmesi | Keşif `meta_value = source_key` ile yapılıyordu. Dizi olarak saklanan veya yanlış önekli marker hiç bulunmuyor, kayıt `create` adayı görünüyordu. | Marker ile hedef bulunamadığında **salt okunur doğal anahtar preflight'ı**. `none` dışındaki her sonuç fail-closed conflict üretir. | WP: dizi marker'lı `sector:guzellik-sac-bakim` artık `conflict/corrupt_marker` (önceden `create`). |
| 3 | Yanlış önekli marker'ın sessizce `''` yapılması | Term ve post sanitize'ları geçersiz marker'ı `''` döndürüyordu. | Tek kanonik `classify_import_source_key()`. Geçersiz marker **reddedilir**, `''` yazılmaz; önceki değer korunur. | WP: `mb_ucret`'e `sector:` önekli marker yazılamıyor (meta hiç oluşmuyor). Geçerli marker yanlış önek, dizi veya biçimsiz değerle ezilemiyor. |
| 4 | Geçersiz girişin genel sanitize yolunda eski geçerli değeri `''` ile ezmesi | `register_meta` sanitize callback'i yazmayı reddedemez. Eski kod geçersiz girişi alanın "güvenli varsayılanına" çeviriyordu. | Sanitize geçersiz girişte `REJECTED_META_WRITE` işareti döndürür. `update_/add_{post,term}_metadata` filtresi (öncelik 1) bu işareti görünce yazmayı **reddeder**. | WP: `_mb_level` `"5"` üzerine `"9"`, `"03"` veya dizi yazılınca `"5"` korunuyor (önceki tur: `''`). Kuruş, fiyat listesi, marker, hash, görsel ve ikon için de aynı davranış. |

## 0.1 Son Kabul Düzeltmesi — Codex'in bulduğu iki açık

| # | Açık | Kök neden | Çözüm | Kanıt |
|---|---|---|---|---|
| 5 | `update_metadata_by_mid()` ile ret işaretinin saklanması | WordPress 6.9.9 `update_metadata_by_mid()` önce `update_{type}_metadata_by_mid` filtresini çalıştırır (sanitize'dan ÖNCE), sonra `sanitize_meta()` sonucunu doğrudan `$wpdb->update` ile yazar; sanitize sonrası filtre yoktur. Önceki tur yalnız `update_/add_{type}_metadata` filtrelerini bağlamıştı. | `update_post_metadata_by_mid` ve `update_term_metadata_by_mid` filtreleri (öncelik 1). Callback meta ID'den kaydı çözer; `$meta_key === false` → kayıttaki anahtar, açık string → o anahtar, diğer → `false`. Nesne ID'si ve alt tür çözülür, `sanitize_meta()` çalıştırılır; sonuç işaret ise `false`, değilse `null` (normal yol). Kayıt/anahtar/nesne/alt tür çözülemezse fail-closed `false`. Özyineleme yok. | WP: yazma güvenliği 45/45 (18 yeni by-mid testi: post `_mb_level`, `money_kurus`, `price_options`, `_mb_import_source_key`, `_mb_last_applied_hash`; term marker, hash, ikon, görsel; var olmayan meta ID). Test sonunda işaret satırı 0. Mutasyon: by-mid filtreleri kaldırılınca 12 test düştü ve işaret saklandı. |
| 6 | Alanlarla ilgisiz hash'li / eksik rollback kaydının kabulü | `build_rollback_record()` hash'lerin yalnız biçimine bakıyordu; `rollback_allowed()` yalnız `new_hash` karşılaştırıyordu (yalnız `schema` + `new_hash` taşıyan kayıt geçiyordu). | Tek kapalı `validate_rollback_record()` (§11). `build_rollback_record()` kaydı kurup bundan geçirir, aksi hâlde `null`; `rollback_allowed()` önce bunu çağırır. | PHP: `tests/run.php` 729/729 (22 yeni test). Mutasyon: eski davranışta 18 test düştü. |
| 7 | By-mid filtresinin MaviBelge dışı sanitizer'ları iki kez çalıştırması (By-Mid Kapsam Kapanışı, 24 Eylül 2026) | #5'in filtresi global; callback anahtarın MaviBelge'ye ait olup olmadığına bakmadan `sanitize_meta()` çağırıyor, geçerli değerde `null` dönüyordu; çekirdek ardından `sanitize_meta()`'yı tekrar çağırıyordu (Codex: `sanitize_calls=2`). | İki adımlı kapsam. (1) Aday: post → `get_schema()`'daki herhangi bir alan, term → `MaviBelge_Core_Taxonomies::sector_term_meta_contract()` anahtarı; aday değilse `sanitize_meta()`/`get_metadata_by_mid()` çağrılmadan `null` (`$meta_key === false` iken anahtar filtre tetiklemeyen salt okunur SELECT ile okunur). (2) Kesin: gerçek alt türle `is_managed_meta_key()` — post: `get_fields_for($subtype)`; term: taksonomi tam `mb_sektor` + dört anahtar. Sektör term-meta listesi tek kaynaktır: `register_term_meta()` kaydı ve kapsam aynı sözleşmeyi kullanır. Fail-closed sınırı ve #5 korumaları aynen. | WP: yazma güvenliği 55/55 — harici post meta (`$meta_key` false/açık), harici term meta, `post` yazısında `_mb_level`, `category` teriminde `_mb_icon_key`: sanitizer tam 1 kez; MaviBelge by-mid retleri aynen. Mutasyon (kapsam denetimi kaldırıldı): bu beş test `sanitize_calls=2` ile düştü. PHP: 739/739 (10 yeni saf kapsam testi). |

## 1. TL ve kuruş temsil sözleşmesi (bağlayıcı)

- **İnsan girişi (admin):** TL biçiminde string, örn. `"1.500"`, `"1.500,00"`, `"1500"`. Yalnız `MaviBelge_Core_Field_Repository::admin_input_to_storage()` bunu `MaviBelge_Core_Validator::try_lira_to_kurus()` ile kuruşa çevirir. Boş giriş 0 ("tanımlı değil") olur.
- **Saklanan ve manifestteki değer:** integer kuruş. `MaviBelge_Core_Validator::canonical_kurus()` yalnız gerçek int ≥ 0 veya kanonik onluk string (`^(0|[1-9][0-9]*)\z`) kabul eder. Şunlar reddedilir: negatif, float, `"1.0"`, `"1e5"`, `"1.500"`, `"1500,00"`, baştaki sıfır, taşma (PHP_INT_MAX ötesi, float kullanmadan kontrol edilir), bool, dizi, nesne.
- Kanonik kuruş **hiçbir yolda** TL dönüştürücüsünden geçmez ve ikinci kez 100 ile çarpılmaz. Kayıtlı sanitize kanonik değerde idempotenttir.
- Float para saklanmaz.
- `try_lira_to_kurus()` ve `kurus_to_lira_display()` sözleşmeleri değişmedi; admin ekranı TL göstermeye ve TL almaya devam eder.
- `Field_Repository::write_meta()` para alanlarında kanonik **kuruş** bekler, TL beklemez.

## 2. Kanonik kuruş alanları

| Alan | Tür | Tip | Not |
|---|---|---|---|
| `_mb_certificate_print_fee_kurus` | `mb_ucret` | `money_kurus`, `admin_input => 'try'` | Admin'de TL girilir ve gösterilir; saklanan değer kuruştur. 0 = belge basım ücreti tanımlı değil. |
| `_mb_min_amount_kurus` | `mb_ucret` | `money_kurus` (readonly/system_managed) | Fiyat seçeneklerinden türetilir. |
| `_mb_max_amount_kurus` | `mb_ucret` | `money_kurus` (readonly/system_managed) | Fiyat seçeneklerinden türetilir. |
| `_mb_price_options[].amount_kurus` | `mb_ucret` | `price_options` içinde integer kuruş | `normalize_price_options()` kuruş olarak doğrular; TL dönüşümü yalnız admin fiyat satırı katmanında yapılır. Değişmedi, x100 riski yok (runtime'da ölçüldü). |

Başka `*_kurus` alanı yoktur (şema taraması). `money_try` tipi hiçbir alanda kullanılmıyor (statik test).

## 3. Atomik payload doğrulama kuralı

`MaviBelge_Core_Import_Write_Payload::prepare( $type, $managedFields, $sourceKey, $incomingHash )` saftır: WordPress fonksiyonu çağırmaz ve hiçbir şey yazmaz.

1. Tüm yönetilen alanlar **tek seferde** doğrulanır. Doğrulama `MaviBelge_Core_Import_Record_Validator::is_valid_managed_field_set()` ile yapılır; bu, mevcut-durum doğrulamasının AYNI kuralıdır, kopya kural yoktur. Anahtar kümesi türün allowlist'iyle tam eşleşmelidir.
2. **Tek geçersiz alan bütün kaydı reddeder.** Sonuç `{ok:false, errors, payload:null}` olur ve kısmi alan listesi döndürülmez.
3. Marker dolu, doğru aileden ve tam biçimde olmalıdır (tek kanonik sınıflandırıcı).
4. Hash tam 64 küçük hex olmalı **ve** yönetilen alanların deterministik hash'ine eşit olmalıdır.
5. Para kanonik kuruştur. min/max kanonik fiyat listesinden türetilir; kanonik olmayan veya yeniden sıralanmış liste reddedilir.
6. Yükteki her meta anahtarı ilgili türün şemasında tanımlı olmalıdır. `readonly`/`system_managed` alanlardan yalnız `_mb_import_source_key`, `_mb_last_applied_hash`, `_mb_min_amount_kurus` ve `_mb_max_amount_kurus` bulunabilir. Başka hiçbir alan sızamaz (ör. `_mb_reviewer_user_id`: test edildi, reddedildi).
7. Yük yalnız gelecekteki güvenilir iç yazma katmanına verilebilir. **Bu turda o katman yoktur; hiçbir gerçek yazma yapılmaz.**

**Runtime kanıtı:** 103 ücret, 83 yeterlilik ve 14 sektörün gerçek manifest yükü hazırlandı. Her meta değeri kayıtlı `sanitize_meta()` yolundan **değişmeden** geçti; dönüşüm ve ret işareti oluşmadı. Yükler yazılmadı.

## 4. Marker formatı ve hata politikası

Tek kanonik kural: `MaviBelge_Core_Validator::classify_import_source_key( $value, $type )`. Kırpma yapmaz, cast yapmaz.

| Girdi | Sınıf |
|---|---|
| `null`, `''` | `empty` |
| dizi / nesne / int / bool / float | `not_string` |
| `$type:` önekiyle başlamayan string (başka aile, bilinmeyen önek, baştaki boşluk) | `wrong_prefix` |
| doğru önek, geçersiz biçim/uzunluk (büyük harf, sondaki `\n`, boş gövde) | `malformed` |
| tam biçim eşleşmesi | `valid` |

- Geçerli aileler yalnız `sector:`, `qualification:` ve `fee:`. Desenler `\z` ile biter.
- `is_valid_import_source_key()` bu kurala devreder. Eski sözleşme korunur: string girdide trim uygulanır, boş değer geçerlidir. Artık dizi/nesne girdide `(string)` cast (`"Array"`) yapılmaz.
- Kayıtlı sanitize (post: `Field_Repository` format kontrolü; term: `sanitize_sector_import_source_key()`) geçersiz marker'ı **reddeder**; `''` yapmaz.
- Veritabanındaki eski/bozuk değer salt okunur doğal anahtar preflight'ında tespit edilir (§6). Bozuk marker taşıyan gerçek bir hedef yeni kayıt gerekçesi olamaz.

## 5. Doğal anahtarlar

| Tür | Doğal anahtar | Sorgu |
|---|---|---|
| Sektör | `mb_sektor` term slug'ı (tam eşitlik) | `get_terms( slug => … )` |
| Yeterlilik | `_mb_myk_code` (tam eşitlik) | `get_posts( meta_key => _mb_myk_code )`. Kod seviye ve revizyonu zaten içerir; seviye/revizyon çapraz tutarlılığı mevcut `myk_code_matches_level_revision()` sözleşmesinde kalır. |
| Ücret | `_mb_sector_slug` + `_mb_level` (tam eşitlik) **ve** `profession_slug(_mb_profession_name)` === source_key'deki meslek slug'ı | `get_posts( meta_query AND )` ile adaylar alınır, meslek slug'ı Faz 6A `slugify_tr` kuralıyla (tek kaynak) karşılaştırılır. |

Kurallar:
- Post sorgularında çöp kutusu **dahildir**. Gerekçe: geri yüklenebilecek bir kaydın kopyası oluşturulmaz.
- Ücret adayları 200 ile sınırlıdır; sınıra ulaşılırsa sonuç `query_error` sayılır.
- **Fuzzy eşleştirme yoktur.** Ad benzerliği, LIKE, levenshtein, soundex veya similar_text kullanılmaz (statik test). Örnek: "Kızgın Yağ Operatörü (eski)" kaydı `kizgin-yag-operatoru` doğal anahtarıyla eşleşmez (runtime'da doğrulandı).
- Source key'in kendisi önce kanonik sınıflandırıcıdan `valid` geçmelidir; geçmezse `query_error` olur.

## 6. Marker – doğal anahtar karar matrisi

Marker ile keşif (Faz 6B2, üç türde) önce çalışır. Keşif 0 aday döndürürse doğal anahtar preflight'ı çalışır. Lookup'a kapalı `natural_key` durumu eklenir (`MaviBelge_Core_Import_Record_Validator::NATURAL_KEY_STATES`).

| Durum | Anlam | Karar / neden |
|---|---|---|
| `none` | Doğal anahtarla hedef yok | `create` / `no_target` (natural_key_check=`none`) |
| `unmanaged` | Tek hedef, marker boş (import dışı ya da sanitize'da boşalmış) | `conflict` / `unmanaged_natural_key` |
| `corrupt_marker` | Tek hedef, marker dizi/nesne ya da doğru önekli ama biçimsiz | `conflict` / `corrupt_marker` |
| `wrong_marker_prefix` | Tek hedef, marker başka/bilinmeyen önek taşıyor | `conflict` / `wrong_marker_prefix` |
| `foreign_marker` | Tek hedef, marker aynı ailede geçerli ama farklı source_key | `conflict` / `foreign_marker` |
| `undiscovered_marker` | Tek hedef, marker tam bu source_key ama keşif görmedi (ör. çöp kutusu) | `conflict` / `undiscovered_marker` |
| `duplicate` | Doğal anahtarla 2+ hedef | `conflict_duplicate_target` / `duplicate_natural_key` |
| `query_error` | Sorgu güvenilir yapılamadı | `conflict` / `natural_key_query_error` |
| (verilmemiş) | Adapter kontrol etmedi (eski/test adapter) | Geriye dönük uyumluluk için `create`, ama `natural_key_check=null` **ve apply adayı değildir** (§8). Gerçek WordPress adapter'ı bu durumu her zaman doldurur. |

**Karar sırası:** `invalid_shape` → `duplicate_target` → `invalid_target_state` → **doğal anahtar** → `wrong_target_type` → `dependency_unresolved` → `create` → `legacy_missing_hash` → üç-hash.

Doğal anahtar bulgusu bağımlılık blokajından önce gelir: var olan yönetilmeyen veya bozuk bir hedef, "bağımlılık çözülemedi"den daha önemli bir bulgudur.

- Marker doğru ve tek hedefe bağlıysa mevcut üç-hash kararı değişmeden sürer.
- Yanlış tür hedef mevcut `conflict_wrong_target_type` kararını almaya devam eder.
- Dry-run girdisi yeni `natural_key_check` alanını taşır (CLI JSON'da da). Bu alan içerik değil, durum kodudur.

**Runtime (gerçek WordPress, fixture'lı):** 52/52 beklenti karşılandı. 3 yeni senaryo eklendi; `fixtures.php`'nin 8 önceki beklentisi gerekçeli olarak değişti (`fixtures-natural-key.php` içinde `overrides`). Gerçek adapter'ın 87 `create` kararının hepsi `natural_key_check=none` taşıyor.

## 7. Duplicate ve query-error davranışı

- Marker ile 2+ aday varsa mevcut `conflict_duplicate_target/duplicate_target` kararı sürer.
- Doğal anahtarla 2+ aday varsa `conflict_duplicate_target/duplicate_natural_key`. İlk kayıt asla seçilmez (runtime: iki marker'sız aynı MYK kodlu yeterlilik; iki aynı üçlülü ücret).
- `WP_Error`, dizi olmayan sonuç, pozitif int olmayan ID, ücret aday sınırına ulaşılması veya geçersiz source_key → `query_error` → `conflict/natural_key_query_error`. Asla `create` olmaz.
- **Sınır:** gerçek bir `WP_Error` döndüren doğal anahtar sorgusu izole WordPress'te üretilemedi. Bu yol yalnız saf testle (lookup düzeyinde) kanıtlandı.

## 8. Apply uygunluk matrisi

`MaviBelge_Core_Import_Apply_Eligibility::evaluate_plan( $plan )` saftır, yazmaz.

| Karar | Uygunluk |
|---|---|
| `create` | Yalnız `natural_key_check === 'none'` iken ve hedef/hash yokken yazma adayıdır. |
| `update` | Yalnız `target_id > 0`, `current === last`, `incoming !== last` ve `changed_fields` doluyken yazma adayıdır. |
| `unchanged` | No-op: yazma yok, audit yok. |
| `conflict*`, `blocked_dependency`, `invalid` | **Hiçbir zaman yazılmaz.** |

Batch koşulları: `summary.applicable === true`, `count(entries) === summary.total`, plan `errors` boş, batch içinde source_key tekrarı yok.

**Runtime:** gerçek dry-run planı (create 87, update 2, unchanged 5, conflict 23, blocked 83) `eligible=false`, `writes=0` sonucunu verdi. Kısmi batch oluşmadı.

## 9. TOCTOU yeniden kontrol sözleşmesi

`toctou_recheck( $write, $observed )` yazmadan **hemen önce** gözlenen durumu planla karşılaştırır. En küçük sapmada yazma yapılmaz.

| Karar | Yazmadan önce karşılanması gerekenler |
|---|---|
| Her karar | `incoming_hash` planlanan değere eşit (manifest/projeksiyon değişmemiş). |
| `create` | Marker ile hedef hâlâ yok **ve** doğal anahtar hâlâ `none`. |
| `update` | Aynı `target_id`, aynı `current_hash` (kullanıcı arada değiştirmemiş) ve aynı `last_applied_hash`. |

Gözlenen durumun şekli bozuksa yazma yapılmaz.

## 10. Batch atomikliği

- Tek bir invalid, conflict veya blocked kayıt, kanıtsız bir create, tekrar eden source_key ya da `applicable=false` durumu **bütün batch'i yazmadan önce durdurur**. `writes` ve `noops` boş döner (test edildi).
- Kısmi batch yazımı yasaktır.
- Gelecekteki apply her kayıt için şu sırayı izlemelidir: `prepare()` (atomik yük) → `toctou_recheck()` → güvenilir yazma. Herhangi bir adım başarısız olursa batch durur.
- Batch/checkpoint formatı `faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md` §6'da açık kalır.

## 11. Gelecekteki audit/rollback veri şekli

`build_rollback_record()` kapalı şekil döndürür:

```text
{ schema: "mavibelge-import-rollback/1", source_key, type, decision (create|update), target_id (int>0),
  old_hash (update: 64 hex, create: null), new_hash (64 hex),
  old_fields (update: türün TAM allowlist'i, create: null), new_fields (türün TAM allowlist'i),
  changed_fields: string[] }
```

- Yalnız import tarafından yönetilen alanlar taşınır. Fazladan alan (ör. e-posta) içeren kayıt `null` döner, yani kişisel veri taşınamaz.
- **Son Kabul Düzeltmesi — tek kapalı doğrulayıcı `validate_rollback_record( $record )`:** gerçek dizi; anahtar kümesi tam olarak yukarıdaki 10 anahtar (eksik/fazla → ret); `schema` tam eşit; `type` ∈ sector/qualification/fee; `decision` ∈ create/update; `source_key` türün önekiyle `valid`; `target_id` int > 0; `new_fields` türün tam, geçerli yönetilen alan kümesi ve `new_hash === hash(new_fields)`; create'te `old_fields === null` ve `old_hash === null`; update'te `old_fields` geçerli, `old_hash === hash(old_fields)` ve `old_hash !== new_hash`; `changed_fields` allowlist sırasıyla katı (`!==`) fark olarak yeniden hesaplanır ve birebir eşit olmalıdır (eksik/fazla/tekrarlı/sırası farklı → ret); update'te boş olamaz. Hash hesaplanamazsa fail-closed. Çağıranın verdiği hash'e yalnız biçimi doğru diye güvenilmez.
- `rollback_allowed( $record, $currentHashNow )` önce `validate_rollback_record()` çağırır; bozuk kayıt, current hash eşleşse bile uygun değildir. Geçerli kayıtta yalnız hedefin şu anki yönetilen alan hash'i `new_hash`'e hâlâ eşitse `true` döner. Kullanıcının import sonrasında yaptığı değişikliklerin üzerine körlemesine yazılmaz.
- Rollback yalnız yönetilen alanları kapsar. **Gerçek veritabanı yedeğinin yerine geçmez.**

## 12. Bu turda neden gerçek yazma yapılmadı

- Görev bir önkoşul/sözleşme turudur. Faz 6B3 apply ayrı bir görev ve **ayrı kullanıcı onayı** gerektirir (AGENTS.md §5/§6, `faz6b3-apply-onkosullari.md`).
- Yedek, geri dönüş provası, staging dry-run ve kurum incelemesi yapılmadı. Bunlar olmadan yazma yapılmaz.
- Bu turdaki bütün yazmalar yalnız izole test veritabanında test fixture'ıdır: `fixtures-natural-key.php`, geçici yazı/terimler (`write-safety-test.php`, test sonunda silindi ve silindiği doğrulandı), render fixture'ları, kanarya (geri alındı).
- Üretim import kodunda yazma çağrısı yoktur (statik tarama: 9 import dosyası + admin dry-run sayfası; her birinde 16 yasak WordPress yazma çağrısı + `$wpdb->insert/update/delete/query/replace`).

## 13. Hâlâ açık operasyonel önkoşullar

1. Gerçek WordPress staging (`cms-yeni`) dry-run: yapılmadı; kullanıcı onayı ve bağlantı bilgisi bekliyor.
2. Dry-run sonucunun kurum incelemesi.
3. Veritabanı + `wp-content/uploads` + yapılandırma yedeği ve bu yedekten geri dönüş provası.
4. Kullanıcının ayrıca açık apply onayı.
5. **Görselli sektör tasarım bulgusu (bu turda bulundu, çözülmedi):** sektör görseli, var olan `mb_sektor` teriminin `_mb_image_attachment_id` term-meta'sından çözülüyor. Bu nedenle görselli 10 sektör hiçbir zaman "create + çözülmüş görsel" olamaz: terim yoksa `blocked_dependency`, terim marker'sız varsa artık `unmanaged_natural_key` conflict. Faz 6B3 öncesinde ayrı bir görsel eşleme stratejisine karar verilmeli (örn. açık, elle onaylı attachment eşleme listesi). Fuzzy veya dosya adı tahmini yine yasak.
6. Faz 6B3 apply kodu, batch/checkpoint, gerçek audit kaydı, rollback uygulaması ve iki-kez-çalıştırma testi (`faz6a-faz6b-…` §10).
7. Gerçek tarayıcı testi yapılmadı. Admin kuruş girişi gerçek HTTP formundan değil, aynı fonksiyon zinciriyle doğrulandı.
8. Üretim PHP/DB/eklenti sürümleri doğrulanmadı; hiçbir sürüm kilitlenmedi.

## 14. Faz 6B3 Apply Kodlaması (24 Eylül 2026) — kapanış kaydı

Yukarıdaki §12–§13 bu sözleşme yazılırken geçerli olan durumdu ve tarihsel kayıt olarak korunur. Bu turda apply, batch, checkpoint, audit ve rollback **kodlandı**; ayrıntı [`faz6b3-apply-mimarisi.md`](./faz6b3-apply-mimarisi.md).

- Bu sözleşmenin §1–§11 kuralları tek kaynak olarak kullanıldı:
  - atomik yük: `Write_Payload::prepare()`;
  - uygunluk, TOCTOU ve rollback kaydı: `Apply_Eligibility`;
  - doğal anahtar preflight'ı ve `REJECTED_META_WRITE`: değişmedi.
  İkinci bir karar sistemi yazılmadı.
- §13 madde 6 (apply kodu, batch/checkpoint, audit, rollback ve iki kez çalıştırma testi) **yalnız yerel, silinebilir test ortamında** kapatıldı: PHP 7.3.33 saf testler ve izole WordPress 6.9.9 apply/rollback döngüsü.
- Gerçek staging, gerçek katalog apply'ı, kurum incelemesi, yedek, geri dönüş provası ve kullanıcı onayı (§13 madde 1–4) hâlâ açıktır.
- Görselli sektör görsel eşleme stratejisi (§13 madde 5) hâlâ açıktır. Görselli sektörler `blocked_dependency` kalır; gerçek manifest planı apply kapısında reddedilir.
