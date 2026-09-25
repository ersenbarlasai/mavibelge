# Faz 6B2 — Salt Okunur WordPress Entegrasyonu ve Dry-Run Arayüzü

> Bu görev **hiçbir içerik, terim, meta, medya, seçenek, dosya veya veritabanı kaydı oluşturmaz/değiştirmez/silmez.** WordPress'e tek bir yazma API'si çağrısı yapılmadı — bkz. §6 "Yazma yasağı taraması".

## 1. Mimari

```
sabit manifest dosyaları (data/content/*.manifest.json)
    -> MaviBelge_Core_Import_Manifest_Loader (güvenli, salt okunur)
    -> MaviBelge_Core_Import_WordPress_Target_Repository (salt okunur WP okuma)
    -> MaviBelge_Core_Import_Dry_Run_Service (targetLookups + 3 dependency map inşa eder)
    -> MaviBelge_Core_Import_Dry_Run_Planner::plan() (Faz 6B1, DEĞİŞTİRİLMEDİ, TEK çağrı)
    -> tek dry-run sonuç DTO'su {plan, diagnostics, generated_at_utc, read_only, load_errors}
    -> WP-CLI (`wp mavibelge import catalog --dry-run`) VEYA admin ekranı (Araçlar > İçe Aktarım Dry-Run)
```

WP-CLI komutu ve admin ekranı **kendi karar mantığını yazmaz** — ikisi de yalnız `MaviBelge_Core_Import_Dry_Run_Service::run_dry_run()` çağırır (bkz. `tools/test-faz6b2-static-contract.js`'deki statik kanıt).

## 2. Yeni dosyalar

| Dosya | Sorumluluk | WordPress bağımlılığı |
|---|---|---|
| `includes/import/class-import-manifest-loader.php` | Yalnız 3 sabit dosyayı güvenli okur, zarf şeklini doğrular | Yok — saf PHP (`tests/bootstrap.php`'de standalone yüklenir) |
| `includes/import/class-import-wordpress-target-repository.php` | `MaviBelge_Core_Import_Target_Repository` arayüzünün GERÇEK uygulaması | Var — yalnız OKUMA fonksiyonları |
| `includes/import/class-import-dry-run-service.php` | Loader+repository+planlayıcıyı TEK sonuçta birleştirir | Yok (repository ENJEKTE edilir) |
| `includes/import/class-import-cli-command.php` | `wp mavibelge import catalog --dry-run` | Var (yalnız WP-CLI ortamında yüklenir) |
| `admin/class-import-dry-run-page.php` | Araçlar menüsü altında salt okunur ekran | Var (yalnız `is_admin()` bağlamında yüklenir) |

## 3. Manifest loader güvenlik sözleşmesi

- Yalnız `sectors.manifest.json`/`qualifications.manifest.json`/`fees.manifest.json` — istemciden (WP-CLI argümanı, admin form alanı, GET/POST) gelen HİÇBİR dosya yolu/glob/dosya adı kabul edilmez.
- Temel dizin: `MAVIBELGE_IMPORT_MANIFEST_DIR` sabiti (üretimde açıkça tanımlanabilir) veya kaynak ağaçtaki varsayılan `wordpress-site/data/content` (bu dosyadan `__DIR__` göreli hesaplanır — `MAVIBELGE_CORE_PATH`'e bağımlı değildir, bu yüzden `tests/bootstrap.php`'de de çağrılabilir).
- `realpath()` sonrası dosyanın gerçekten temel dizinin İÇİNDE kaldığı ayrıca doğrulanır (symlink/`..` kaçışına karşı savunma derinliği).
- Eksik/okunamaz/boş/5 MiB üstü/geçersiz-UTF-8/geçersiz-JSON dosya fail-closed reddedilir; PHP warning/fatal üretmez.
- Zarf (üst-seviye şekil) KAPALI politika ile doğrulanır: `schema_version`/`record_type`/`count`/`source`/`records` zorunlu + dosya türüne özel TEK ek alan (`notes` sektör/yeterlilikte, `counts` ücrette) — **Düzeltme ve Kabul §2.5 (kapatıldı):** bu ek alan önceki turda yalnız "izin verilenler" listesindeydi (verilmese de zarf geçerli sayılıyordu); artık `ENVELOPE_EXTRA_REQUIRED_KEY` ile ZORUNLU — eksikse zarf reddedilir. `notes` gerçekten sıralı string listesi olmalı; `counts` Faz 6A'nın TAM 8 anahtarlı kapalı kümesini (`total`/`priceOptionsTotal`/`pricingSingle`/`pricingMulti`/`multiOptionsTotal`/`feesWithCode`/`feesWithoutCode`/`linkedCount`), hepsi nonnegative int, taşımalı (sayaçlar PHP'de kaynaktan yeniden HESAPLANMAZ — yalnız zarf şeklinin kapalı/eksiksiz olduğu doğrulanır). `record_type` dosya adıyla, `count` `records` uzunluğuyla eşleşmeli. `source` artık yalnız `is_array()` DEĞİL — kapalı `{file, sha256}` şekli taşımalı: `file` beklenen SABİT Faz 6A kaynak yoluyla (`EXPECTED_SOURCE_FILE`) birebir eşleşmeli, `sha256` tam 64 küçük-harf hex olmalı; ayrıca zarf `source`'u her kayıttaki `source` provenance'ıyla çapraz kontrol edilir (tek uyuşmazlık tüm dosyayı reddeder).
- Kayıtlar filtrelenmeden/yeniden sıralanmadan/`source_index` değiştirilmeden olduğu gibi verilir.
- **Düzeltme ve Kabul §2.5 madde 1 (kapatıldı):** dizin bulunamadığında hata metnine artık mutlak `$baseDir` yolu KONMAZ — yalnız sabit hata kodu + izinli dosya adı.
- **Sınır:** loader Node üretim doğrulayıcısının (`tools/import/lib/validate-manifest-set.js`) tam alan/çapraz-alan sözleşmesini YENİDEN YAZMAZ — yalnız zarfı ve güvenli okumayı doğrular. Kayıtların TAM alan sözleşmesi zaten Faz 6B1 `MaviBelge_Core_Import_Record_Validator` + planlayıcı tarafından, planlanmadan hemen önce, AYRICA doğrulanır.

## 4. Repository — target lookup ve resolver davranışı

**Düzeltme ve Kabul (21 Eylül 2026) ile güncellendi** — bu bölümün önceki hâli iki yanlış iddia taşıyordu, ikisi de bağımsız (Codex) incelemede bulundu ve kapatıldı:

- **§2.1 (bloklayıcı, kapatıldı):** Önceki iddia "sorgu zaten doğru taksonomi/post_type'a karşı çalıştırıldığı için tür kontrolü kanıtlanmıştır, `target_type_matches` HER ZAMAN true" idi. Bu YANLIŞTI — sorgu yalnız BEKLENEN türü arıyordu; `_mb_import_source_key` marker'ı YANLIŞ bir içerik türünde bulunsa (ör. bir `qualification:...` marker'ı bir `mb_ucret` kaydında) beklenen-tür sorgusu boş dönüyor, kayıt sessizce "hedef yok" (create adayı) sayılıyordu — `conflict_wrong_target_type` yolu gerçek adapterda fiilen ERİŞİLEMEZDİ. Artık `find_target_by_source_key()` yeni bir `discover_candidates()` katmanıyla marker'ı ÜÇ hedef türünün (`mb_sektor`/`mb_yeterlilik`/`mb_ucret`) TAMAMINDA arar, tür+ID kimliğiyle dedupe eder ve şu karar matrisini uygular: 0 aday → `target_found=false`; 1 aday doğru türde → tam sonuç; **1 aday YANLIŞ türde → `target_found=true, target_type_matches=false`** (artık gerçekten üretilebiliyor); 2+ aday (türden bağımsız) → `duplicate_targets=true`, doğru olan sessizce seçilmez; sorgu hatası (`WP_Error`/beklenmeyen şekil) → fail-closed `invalid_target_state` sinyali, ASLA "hedef yok" ile karıştırılmaz.
- **§2.2 (bloklayıcı, kapatıldı):** Önceki iddia "WordPress'in string meta değerleri `to_int()`/`to_nonneg_int()` ile gerçek int'e çevrilir — biçimsiz bir değer 0'a düşer ve downstream fail-closed reddedebilir" idi. Bu da YANLIŞ yöndeydi: `0` GEÇERLİ bir değerdir, downstream onu geçerli `0` olarak KABUL EDER — yani biçimsiz bir meta değeri (`"abc"`, negatif, dizi, `(bool)` cast'inin `"false"` string'ini `true` sayması gibi) SESSİZCE geçerli bir varsayılana dönüşüp hash'e girebiliyordu. `to_int()`/`to_nonneg_int()` kaldırıldı; yerine "başarı + değer" ayrımını koruyan `strict_int()`/`strict_nonneg_int_or_empty_zero()`/`strict_bool()` geldi. `current_sector_fields()`/`current_qualification_fields()`/`current_fee_fields()` artık HERHANGİ bir alan dönüşümü başarısız olursa (veya `price_options` array değilse, veya yeterlilikte sektör terimi sıfır/birden-çoksa) tüm `current_managed_fields`'i `null` döner — bu, `target_type_matches=true` iken zaten var olan `normalize_target_lookup()` fail-closed kuralına (current_managed_fields ASLA null olamaz) düşerek otomatik `invalid_target_state` üretir.
- `find_target_by_source_key()` hâlâ `_mb_import_source_key` eşitliğiyle arar (sektörde term-meta, yeterlilik/ücrette post-meta), yalnız artık ÜÇ türde birden. Birden fazla eşleşmede **ilkini keyfî seçmez**.
- Üç resolver (`resolve_sector_term_id`/`resolve_qualification_post_id`/`resolve_sector_image_attachment_id`) yalnız `null` veya kapalı `{id: int>0, type_verified: true}` döner. Yeterlilik kodunda birden fazla eşleşme: `null` + `ambiguous_qualification_dependency` diagnostic — ilk kaydı seçmez.
- Sektör görseli: **yalnız** açık `_mb_image_attachment_id` term-meta ilişkisi + `get_post_type() === 'attachment'` doğrulamasıyla çözülür. Dosya adı/benzerlik TAHMİNİ yoktur.
- **§2.3 (bloklayıcı, kapatıldı):** `MaviBelge_Core_Import_Dry_Run_Service`, arayüzde HİÇ tanımlı olmayan `get_diagnostics()`'i çağırıyordu — arayüzü doğru uygulayan ama bu ek metodu taşımayan bir adapter fatal verirdi. `get_diagnostics()` artık `MaviBelge_Core_Import_Target_Repository` arayüzünün KENDİSİNDE tanımlı; servis ayrıca döndürülen diagnostics dizisini kapalı `{code,type,source_key}` şekli ve bilinen `type` değerleriyle SÜZER (repository'nin fazladan/hassas bir alan taşıması çıktı katmanına sızmaz).

## 5. Dependency üst DTO

Her `run_dry_run()` çağrısında üç anahtar HER ZAMAN mevcuttur (`sector_term_ids`/`sector_image_attachment_ids`/`qualification_post_ids`) — servis yalnız MANİFESTTE GERÇEKTEN gereken kimlikleri (görselli sektörlerin slug'ı, yeterliliklerin sector_slug'ı, kodlu ücretlerin qualification_code'u) çözmeye çalışır; çözülemeyen bir kimlik map'e HİÇ eklenmez (planlayıcı bunu `blocked_dependency` sayar).

**Düzeltme ve Kabul §2.4 (kapatıldı):** CLI komutundaki tablo çıktısı önceki turda hiç çalışmıyordu — `class_exists('WP_CLI\Utils')` her zaman `false` döner (`WP_CLI\Utils` bir SINIF değil, fonksiyon içeren bir NAMESPACE'tir), bu yüzden `format_items()` hiç çağrılmıyor, kayıt satırları sessizce hiç gösterilmiyordu. Artık doğru `function_exists('WP_CLI\Utils\format_items')` kontrolü var; fonksiyon yoksa (WP-CLI'nin özel bir sürümü) satırlar sessizce atlanmaz, okunabilir bir metin-tablosu fallback'i (`print_fallback_rows()`) yazdırılır. Tablo artık `source_key`/`type`/`decision`/`reason`/`target_id`'nin yanında `changed_fields`/`unresolved_dependencies` de gösterir. Bilinmeyen `--format` değeri artık `WP_CLI::error()` ile fail-closed reddedilir (önceden sessizce `table`'a düşüyordu).

**Düzeltme ve Kabul §2.6 (kapatıldı):** Admin ekranında POST + eksik/array/geçersiz nonce önceden sessizce normal GET formuna düşüyordu (kullanıcıya hiçbir hata gösterilmeden `if` koşulu başarısız oluyordu). Artık `wp_die()` ile açık, fail-closed reddediliyor — nonce alanının önce `is_string()` ile scalar şekli doğrulanıyor, sonra unslash/`wp_verify_nonce()` uygulanıyor. `mb_paged` önceden `is_numeric()` ile doğrulanıyordu — bu, bilimsel gösterimi (`"1e2"`), ondalığı (`"1.5"`) ve öndeki `+`'yı da GEÇERLİ sayıyordu; artık yalnız katı `^[1-9][0-9]*$` deseni (veya gerçek pozitif PHP int) kabul edilir, aksi hâlde güvenli varsayılan (sayfa 1) kullanılır.

## 6. Yazma yasağı taraması

`tools/test-faz6b2-static-contract.js` (gerçekten çalıştırıldı, **137/137** geçti — önceki 113/113'ten Düzeltme ve Kabul turunun 24 yeni regresyon/sözleşme kontrolüyle arttı) şunları doğrular: `wp_insert_*`/`wp_update_*`/`wp_delete_*`/`add_*_meta`/`update_*_meta`/`delete_*_meta`/`add_option`/`update_option`/`delete_option`/`$wpdb->insert|update|delete|query|replace`/`set_transient` GERÇEK çağrılarının (yorumlar hariç) beş dosyanın HİÇBİRİNDE olmadığı; CLI'de `--apply`/`--write`/`--commit`/`--force` GERÇEK tanımının olmadığı; admin ekranında apply/import düğmesi metninin olmadığı; ayrıca bu turun 6 bulgusuna özel kaynak-düzeyi regresyon sabitleri (discover_candidates() üç türü de sorguluyor, wrong-type sonucu gerçek kaynak kodunda üretilebiliyor, `(bool) get_post_meta` yok, eski `to_int`/`to_nonneg_int` çağrısı yok, arayüzde `get_diagnostics()` var, `class_exists('WP_CLI\Utils')` yok, loader hata metninde mutlak yol yok, `notes`/`counts`/`source` zorunlu+kapalı, admin nonce scalar kontrolü + `is_numeric()` yok).

## 7. Kanıt sınırı

`php`/WordPress runtime bu turda da yoktu (`where php` → bulunamadı). Gerçekten çalıştırılanlar: `tools/test-faz6b2-static-contract.js` (137/137), Faz 6A Node kapıları (54/54, 85/85, 32/32+finalizasyon, 9/9, 3/3, hepsi regresyonsuz), `node --check`, PHP 7.4+/8.x yasak sözdizimi taraması (temiz), brace/paren dengesi taraması (tüm değişen dosyalarda temiz, PHP string/yorum kurallarını izleyen bir lexer ile). `tests/run.php`'ye yazılan Faz 6B2 PHP testleri (loader gerçek 14/83/103 + 8 negatif zarf senaryosu + 12 yeni izole §2.5 senaryosu + `MB_Test_Fake_Import_Repository` test double ile servis uçtan uca 5 senaryo + idempotency + dependency-üç-anahtar Reflection testi + `get_diagnostics()` arayüz Reflection testi + 19 strict_* dönüştürücü testi) **`php` yokluğu nedeniyle çalıştırılamadı** — yazıldı, kaynak olarak gözden geçirildi. `MaviBelge_Core_Import_WordPress_Target_Repository`'nin GERÇEK `get_terms()`/`get_posts()`/`get_post_meta()` davranışı (özellikle bu turun düzelttiği çapraz-tür keşfi ve malformed-meta reddi), WP-CLI komutunun gerçek `format_items()` çalışması ve admin ekranının nonce/yetki/kaçış davranışı **hiç çalıştırılmadı** — gerçek WordPress test ortamı gerektirir (bkz. `tests/run.php`'nin genişletilmiş "NOT covered" listesi).

**Bu round kendi kendine kabul edilmemiştir** — teslim Codex bağımsız incelemesine sunulmuştur. Faz 6B3 (apply/batch/audit/rollback) bu görevde YAZILMADI — yalnız önkoşul notu (`faz6b3-apply-onkosullari.md`).
