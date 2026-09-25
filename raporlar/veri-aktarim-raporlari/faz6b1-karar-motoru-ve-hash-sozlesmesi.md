# Faz 6B1 — İçe Aktarım Karar Motoru, Hash Sözleşmesi ve Dry-Run Çıktı Şeması

> Bu belge, `wordpress-site/wp-content/plugins/mavibelge-core/includes/import/**` altındaki saf (WordPress fonksiyonu çağırmayan) sınıfların sözleşmesini belgeler. **Bu görevde WordPress veritabanına hiçbir yazma yapılmadı** — bu belge yalnız karar motorunun kendisini, `raporlar/veri-aktarim-raporlari/faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md`'nin üç-hash tasarımının GERÇEK kod karşılığını tanımlar.
>
> **Faz 6B1 Düzeltme ve Kabul (13 Eylül 2026) güncellemesi:** bağımsız (Codex) incelemenin bulduğu sığ doğrulama açığı (`plan_*()` yolları yalnız 1-2 alanın `isset()` olup olmadığını kontrol ediyordu, geri kalanı sessizce `''`/`0`'a düşüyordu) yeni `class-import-record-validator.php` ile kapatıldı; §3.4-3.6 ve §4'teki güncellenmiş karar önceliği bu turun eklemeleridir. WordPress'e hâlâ hiçbir yazma yapılmadı, Faz 6B2 hâlâ başlamadı.
>
> **Faz 6B1 Son Kapanış Düzeltmesi (13 Eylül 2026) güncellemesi:** bağımsız incelemenin bulduğu 10 kalan sözleşme açığı kapatıldı — (1) doğrulayıcı artık Faz 6A şemasının **TAM** `required` kümesini (schema_version/source_index/source dahil) zorunlu kılıyor VE `additionalProperties:false` ile **KAPALI** ek-alan politikası uyguluyor (§3.4, aşağıda düzeltildi); (2) ücretin `source_key`'i artık yalnız önek değil, `"fee:" + sector_slug + ":" + level + ":" + slugify_tr(profession_name)` formülüyle **TAM** eşitlik kontrol ediyor; (3) yeterliliğin `planned_record_status` alanı artık projeksiyonda hard-code EDİLMİYOR, doğrulanmış manifest alanından okunuyor; (4) `targetLookups[source_key]` dizi DEĞİLSE artık "hedef yok" sayılmıyor, doğrudan `invalid_target_state`; (5) lookup bayrakları (`target_found`/`duplicate_targets`/`has_source_key_marker`) artık GERÇEK `bool` zorunlu, `!empty()` kurtarması yok; (6) `current_managed_fields`'in yalnız anahtar kümesi değil HER ALANIN DEĞER TİPİ de doğrulanıyor; (7) hedef bulunduğunda (`target_found=true` + `target_type_matches=true`) `current_managed_fields` artık eksik/null OLAMAZ; (8) marker/hash tutarlılığı (marker yokken hash var → `invalid_target_state`) ayrıca kontrol ediliyor; (9) `plan()`/`plan_sector()`/`plan_qualification()`/`plan_fee()` artık `array` tip ipucu TAŞIMIYOR — scalar/null girişte `TypeError` yerine kontrollü hata dönüyor; (10) `summary.applicable` fail-closed yeniden tanımlandı — yeni `structurally_valid` alanı eski anlamı taşıyor, `applicable` artık yalnız conflict/blocked/invalid SIFIRSA true. `tests/run.php`'nin BEŞ fixture'ı (`$fx_sector_makine`, `$fx_sector_no_image`, `$fx_qualification`, `$fx_fee_with_code`, `$fx_fee_without_code`) artık gerçek manifest kayıtlarının TAM (schema_version/source_index/source dahil) kopyalarıdır. WordPress'e hâlâ hiçbir yazma yapılmadı, Faz 6B2 hâlâ başlamadı.
>
> **Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri (13 Eylül 2026) güncellemesi:** bağımsız incelemenin Son Kapanış Düzeltmesi'nde bulduğu 4 somut açık kapatıldı — (1) `build_entry()`'deki `isset()` → `array_key_exists()` hatası: `targetLookups[source_key] = null` artık PHP'nin `isset()`-null davranışı yüzünden sessizce "hedef yok"a düşmüyor; `normalize_target_lookup()` ayrıca artık `target_found` anahtarının MUTLAKA açıkça verilmiş olmasını zorunlu kılıyor (§3.6 güncellendi); (2) her tür listesi artık `plan()` içinde YENİ `check_batch_positional_integrity()` ile `source_index`'in gerçek liste konumuyla birebir eşleştiğini VE tüm kayıtların aynı `source.file`/`source.sha256`'ya işaret ettiğini (provenance drift yok) doğruluyor — tek bir bozuklukta `errors` dolu, `entries` boş (§3.7 YENİ); (3) dependency çözümü artık salt bir `int` DEĞİL, adapter'ın hedefin gerçek türünü doğruladığının kanıtını taşıyan `{id: int>0, type_verified: true}` typed sonucu OLMAK ZORUNDA — yeni `resolve_verified_dependency()`, eski `is_resolved_dependency_id()`'nin YERİNE geçti (§3.2/§3.8 güncellendi); (4) current ücret DTO'sunun fiyat seçenekleri artık kanonik `MaviBelge_Core_Validator` sınırlarının (20 seçenek/200 karakter etiket/10 birim/50 karakter birim) TAMAMINI VE kanonik (azalmayan) depolama sırasını uyguluyor; ayrıca `title===profession_name`, kod/post-ID tutarlılığı ve `valid_from<=valid_until` çapraz-alan kontrolleri eklendi (§3.6 güncellendi). `tests/run.php`'ye 30'dan fazla yeni karşı-örnek eklendi (yazıldı, `php` yokluğu nedeniyle çalıştırılmadı; gerçek 14/83/103 kaydın source-index/provenance kapısından geçtiği Node ile bağımsız doğrulandı). WordPress'e hâlâ hiçbir yazma yapılmadı, Faz 6B2 hâlâ başlamadı.

## 1. Dosyalar ve sahiplik

| Dosya | Sahip agent | Sorumluluk |
|---|---|---|
| `includes/import/class-import-hash.php` | WordPress çekirdek/eklenti agentı | Deterministik kanonikleştirme + SHA-256 hash |
| `includes/import/class-import-managed-fields.php` | WordPress çekirdek/eklenti agentı | Alan allowlist'leri + manifest kaydı → yönetilen alan izdüşümü (yalnız ÖNCEDEN doğrulanmış kayıt kabul eder) |
| `includes/import/class-import-record-validator.php` | WordPress çekirdek/eklenti agentı | **YENİ (Faz 6B1 Düzeltme ve Kabul)** — TEK, fail-closed kayıt doğrulama katmanı: üç tip için tam alan/tip/çapraz-alan doğrulaması, üst-seviye manifest şekli, dependency ID geçerliliği, target lookup normalizasyonu |
| `includes/import/class-import-decision.php` | WordPress çekirdek/eklenti agentı | Üç-hash karar tablosu (9 senaryo + Düzeltme turunda eklenen `invalid_target_state`, toplam 10) |
| `includes/import/class-import-dry-run-planner.php` | WordPress çekirdek/eklenti agentı | Manifest + hedef arama + bağımlılık sonucu → dry-run planı |
| `includes/import/interface-import-target-repository.php` | WordPress çekirdek/eklenti agentı | Faz 6B2'nin uygulayacağı salt-okunur adapter SÖZLEŞMESİ (arayüz, uygulama yok) |
| `tests/bootstrap.php` / `tests/run.php` | WordPress çekirdek/eklenti agentı | Yeni sınıfların yüklenmesi + testleri (yazıldı, `php` yokluğu nedeniyle ÇALIŞTIRILMADI) |
| Bu belge + `faz6b1-dry-run-cikti-semasi.md` + `faz6b2-entegrasyon-notu.md` + `faz6b3-taslak-notu.md` | Veri/içerik aktarım agentı | Sözleşme/rapor belgeleri |

`mavibelge-core.php`, `README.md`, `raporlar/proje-durumu.md` bu görevde **değiştirilmedi** — yalnız görevin en sonunda, ana orkestratör tarafından, mevcut yükleme sırası bozulmadan güncellenecektir. Yeni dört sınıf bu fazda eklenti bootstrap'ına (`class-plugin.php`/`mavibelge-core.php`) **bağlanmadı** — hiçbiri şu an çalışan hiçbir hook'a takılı değil, yalnız `tests/bootstrap.php` üzerinden test edilebilir durumda. Bağlama gerekirse (Faz 6B2), önce `wordpress-site/docs/integration-notes/` altında tam not yazılacak.

## 2. Deterministik hash sözleşmesi (bkz. `class-import-hash.php`)

`MaviBelge_Core_Import_Hash::hash( $managedFields )` şu kuralları uygular:

1. Associative array anahtarları **özyinelemeli olarak `SORT_STRING` (byte sırası) ile sıralanır**.
2. Liste (sequential `0..n-1` integer anahtarlı) diziler **sırasını korur**, yeniden sıralanmaz.
3. Skaler tipler AYNEN korunur: `null`, `bool`, `int`, `string`. **`float` REDDEDİLİR** (`InvalidArgumentException`) — bu projede tüm sayısal alanlar zaten tam sayı (kuruş, seviye, sayfa no); bir float'ın sessizce hash'e sızması yuvarlama/temsil farkına bağlı gizli drift riski taşırdı.
4. `object`/`resource`/başka her tip (`is_scalar()`+`null`+dizi dışında her şey) **REDDEDİLİR**.
5. JSON kodlaması `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` bayraklarıyla — Node'un `JSON.stringify()`'ı da `/` karakterini kaçmaz, Unicode karakterleri `\uXXXX` yapmaz; bu iki bayrak PHP'nin varsayılanını Node'un davranışıyla eşitler (bkz. `wordpress-site/tools/import/lib/hash.js`'in kendi dokblok'u — Faz 6A'nın Node tarafı sözleşmesiyle AYNI karar).
6. `json_encode()` başarısız olursa (ör. geçersiz UTF-8) sessizce `false` HASH'LENMEZ — `InvalidArgumentException` fırlatılır.
7. Sonuç `hash('sha256', $json)` — tam 64 küçük-harf hex karakter.
8. **`array_is_list()` kullanılmaz** (PHP 8.1+) — PHP 7.3 uyumlu, elle yazılmış `is_list_array()` kullanılır (dizinin `0..count-1` sıralı integer anahtarlı olup olmadığını kontrol eder; boş dizi liste sayılır).

### 2.1 `_mb_import_source_key` / `_mb_last_applied_hash` dahil/hariç kararı — BAĞLAYICI

**İkisi de HİÇBİR projeksiyonun hash girdisine ASLA girmez.**

- `_mb_last_applied_hash`: kendi hash girdisine girmesi dairesel olurdu.
- `_mb_import_source_key`: bu alan hash'lenecek İÇERİK değil, hedefi BULMAK için kullanılan bir eşleştirme anahtarıdır. Bir kaydın `source_key`'i (Faz 6A'nın kararlı formülüyle) zaten değişmez — hash'e dahil edilse bile hiçbir ayırt edici bilgi katmaz, yalnız dairesel bir bağımlılık riski (source_key zaten hedefi bulmak için ayrıca kullanılıyor) ekler.

Bu karar TÜM üç tipte (sektör/yeterlilik/ücret) VE tüm yollarda (mevcut durumun hash'i — `current_managed_hash` — VE gelen manifestin hash'i — `incoming_hash`) **aynı şekilde** uygulanır: `MaviBelge_Core_Import_Managed_Fields`'in üç `project_*()` metodu da bu iki alanı hiçbir zaman döndürdükleri `fields` dizisine eklemez — kod düzeyinde tek bir yerde garanti edilir, ayrışabilecek iki kopya yoktur.

## 3. Yönetilen alan allowlist'leri (bkz. `class-import-managed-fields.php`)

| Tip | Sabit | Alanlar |
|---|---|---|
| Sektör | `SECTOR_FIELDS` | `slug`, `name`, `description`, `icon_key`, `image_attachment_id` |
| Yeterlilik | `QUALIFICATION_FIELDS` | `title`, `myk_code`, `level`, `revision`, `record_status`, `sector_term_id` |
| Ücret | `FEE_FIELDS` | `title`, `profession_name`, `level`, `sector_slug`, `qualification_post_id`, `qualification_code`, `pricing_type`, `price_options`, `vat_included`, `certificate_print_fee_kurus`, `source_name`, `source_page`, `source_attachment_id`, `tariff_period`, `record_status`, `valid_from`, `valid_until` |

Bilinçli hariç tutulanlar:

- **`_mb_min_amount_kurus`/`_mb_max_amount_kurus`** (ücret) — bunlar `price_options`'ın TÜREVİDİR (mevcut eklenti bunları zaten `class-validator.php::normalize_price_options()` ile yeniden hesaplıyor); iki kez bağımsız hesaplanıp hash'e ayrı ayrı girmesi gereksiz drift riski yaratırdı.
- **Editörün serbest alanları** (`post_content`, SEO alanları, elle yazılmış haber metni vb.) — hiçbirinde importer'ın yazacağı bir karşılığı yok, dolayısıyla hiçbir projeksiyonda yer almazlar, hash'e hiç girmezler, importer tarafından asla ezilmezler.
- **WordPress'in teknik ID'leri** (post ID, term ID) hiçbir zaman manifest kimliği SAYILMAZ — yalnız `source_key` (Faz 6A formülü) kimlik olarak kullanılır; teknik ID'ler yalnız ÇÖZÜLMÜŞ bir bağımlılık DEĞERİ olarak (`sector_term_id`, `qualification_post_id`) projeksiyona girer.

### 3.1 Sektör görseli — "henüz çözülmedi" ile "kaynakta görsel yok" birbirinden AYRIDIR

- Kaynakta (`sectors.js`) görsel yolu boş olan bir sektör (**4** gerçek kayıt — `is-makineleri`, `plastik`, `mobilya`, `guzellik-sac-bakim`; önceki turda hatalı biçimde "5" yazılmıştı, bu düzeltme turunda kapatıldı) için **hiçbir bağımlılık aranmaz** — `image_attachment_id` doğrudan `0` ile geçerli sayılır.
- Kaynakta GERÇEKTEN bir görsel yolu olan bir sektör için, bu yol henüz bir WordPress attachment'a çözülmediyse, kayıt **`blocked_dependency`** olur — uydurma `0` ile "başarılı" sayılmaz. Çözüm (gerçek medya arama/eşleştirme) Faz 6B2'nin salt-okunur adapter'ının işidir; bu görev yalnız bu ayrımı planlayıcıda uyguladı (`class-import-dry-run-planner.php::plan_sector()`).

### 3.2 19 kodsuz ücret — tahmin/fuzzy eşleştirme YOK

`qualification_code` boş olan bir ücret için `class-import-dry-run-planner.php::plan_fee()` **hiçbir bağımlılık aramaz** — `qualification_post_id` projeksiyonda her zaman kesinlikle `0`'dır (Faz 6A'nın 19 kodsuz ücret kararı aynen taşınır, bkz. `faz6a-kodsuz-ucret-raporu.md`). Kodlu bir ücret için hedef yeterlilik çözülemezse (bkz. §3.8 — artık salt `int` değil, `{id, type_verified:true}` typed sonucu gerekir) kayıt `blocked_dependency` olur; `create`/`update` SAYILMAZ.

### 3.3 Aktif tarife dönemi / `draft` kaydı

Manifest'in `planned_tariff_period` (`"2026"`) alanı yalnız KAYIT üzerindeki `tariff_period` yönetilen alanına gider — `mb_active_tariff_period` option'ı bu görevde ve Faz 6B'nin importer'ında **asla** değiştirilmez (Faz 5 sözleşmesi, değişmedi). Ücretler `record_status="draft"` olarak PLANLANIR; hiçbir kayıt otomatik `active`/`publish` yapılmaz — `record_status` yönetilen alan listesinde OLDUĞU için, bir yönetici kaydı elle `active` yaparsa bu, sonraki bir dry-run'da `current_managed_hash !== last_applied_hash` üretir ve **`conflict`** olarak raporlanır (otomatik `draft`'a geri döndürülmez) — kasıtlı bir tasarım kararıdır.

### 3.4 TEK, fail-closed kayıt doğrulama katmanı — Faz 6B1 Düzeltme ve Kabul (YENİ)

Bağımsız incelemenin bulduğu kök neden: ilk teslimde `plan_sector()`/`plan_qualification()`/`plan_fee()` yalnız 1-2 anahtarın (`source_key`, `slug`/`sector_slug`) `isset()` olup olmadığını kontrol ediyordu; geri kalan HER alan (`name`, `description`, `icon`, `image`, `level`, `revision`, `pricing_type`, `price_options`, planlanan sabitler vb.) eksik/yanlış tipli olsa bile `MaviBelge_Core_Import_Managed_Fields`'in kendi `isset(...) ? ... : ''`/`(int)`/`continue` "kurtarma" mantığıyla sessizce `''`/`0`/atlanmış satır olarak projeksiyona giriyor ve `create`/`update` planlanabiliyordu — görev promptunun "geçersiz manifest/alan şekli → invalid; sessiz varsayılan veya kısmi plan yok" iddiası koda YANSIMIYORDU.

Bu turda yeni `includes/import/class-import-record-validator.php` eklendi — üç `plan_*()` yolunun HER BİRİ artık projeksiyondan (ve dolayısıyla hash hesabından) ÖNCE bu sınıfın `validate_sector()`/`validate_qualification()`/`validate_fee()` metodundan geçmek ZORUNDADIR:

- **Faz 6B1 Son Kapanış Düzeltmesi'nde TAM şema kümesine yükseltildi**: her tür artık ilgili `data/schema/{sector,qualification,fee}.schema.json` dosyasının **TAM** `required` kümesini (schema_version/source_index/source dahil; yeterlilikte ayrıca `matches_legacy_revision_required_format`+`planned_record_status`; ücrette ayrıca `min_amount_kurus`+`max_amount_kurus`) zorunlu kılar — bir önceki turda bu zarf alanları hiç doğrulanmıyordu, bu bulgu kapatıldı.
- Tipi (string/int/bool/dizi) cast edilmeden ÖNCE kontrol edilir.
- Format kuralları mevcut PAYLAŞILAN `MaviBelge_Core_Validator` metotlarını kullanır — ikinci bir regex kopyası açılmaz: `is_valid_myk_code_format()`, `myk_code_matches_level_revision()`, `is_valid_slug_format()`, `evaluate_price_options()`, `is_valid_sha256_hash()`, `is_valid_ymd_date()`.
- Çapraz-alan kuralları: sektör/yeterlilik `source_key` kendi türünün formülüyle (`"sector:" . slug`, `"qualification:" . code`) TAM eşleşmeli; **ücretin `source_key`'i artık yalnız önek DEĞİL** — `"fee:" . sector_slug . ":" . level . ":" . slugify_tr(profession_name)` formülüyle (Faz 6A'nın `tools/import/lib/slug.js::slugify()` ile birebir aynı, PHP 7.3-uyumlu Türkçe-duyarlı slugify — bkz. `class-import-record-validator.php::slugify_tr()`) **TAM** eşleşmeli; yeterlilikte koda gömülü seviye/revizyon `level`/`revision` alanlarıyla (paylaşılan `myk_code_matches_level_revision()` ile) TAM eşleşmeli; `matches_legacy_revision_required_format` her zaman `has_revision` ile birebir eşit olmalı (ikisi de aynı ayrıştırma noktasından türer); ücrette `qualification_code`/`qualification_source_key` birbirini KESİN belirler (biri boşsa diğeri KESİNLİKLE null); ücretin `min_amount_kurus`/`max_amount_kurus`'u GEÇERLİ fiyat seçeneklerinden YENİDEN hesaplanan değerle birebir eşleşmeli.
- Ücretin PLANLANAN sabitleri (`planned_tariff_period === "2026"`, `planned_record_status === "draft"`, `planned_valid_from`/`planned_valid_until === ""`, `source_attachment_id === 0`) VE yeterliliğin `planned_record_status === "active"` sabiti bu fazda KATI biçimde zorunlu kılınır — gerçek 103/83 kaydın tamamı bu sabitleri taşıdığı doğrulandı (bkz. §8). **Projeksiyon artık bu değeri hard-code ETMİYOR** — `MaviBelge_Core_Import_Managed_Fields::project_qualification()` doğrulanmış `planned_record_status` alanını okur.
- `price_options`, kanonik `MaviBelge_Core_Validator::evaluate_price_options()` (Faz 5'in atomik-ret + kanonik sıralama kuralı) ile doğrulanır: TEK bir satır bile geçersizse TÜM liste reddedilir; ayrıca gönderilen satır sayısı ile geçerli sayılan satır sayısı KARŞILAŞTIRILIR (evaluate_price_options()'ın kendi "tamamen boş ilerlemeli-form satırı" atlama davranışının gerçek manifest verisinde HİÇ tetiklenmediğini garanti eder — tetiklenirse fail-closed reddedilir).
- **Ek-alan politikası (Faz 6B1 Son Kapanış Düzeltmesi'nde KAPALI politikaya çevrildi)**: doğrulayıcı artık şemanın `additionalProperties:false` kuralıyla AYNI şekilde beklenmeyen bir üst-seviye (veya `source` zarfı içi) alan bulunmasını REDDEDER — önceki turun "açık politika" kararı (extra alanları kabul etme) geri alındı; bunun için `tests/run.php`'nin BEŞ fixture'ı bu turda gerçek manifest kayıtlarının TAM (schema_version/source_index/source dahil) kopyalarına yükseltildi.

Doğrulama başarısızsa: hiçbir projeksiyon/hash üretilmez, kayıt `invalid` olur, `warnings[]` alanına kısa, alan-adı temelli (bozuk DEĞERİ asla basmayan) hata listesi yazılır.

**Projeksiyonlar artık ikinci bir "kurtarma" katmanı değil.** `project_sector()`/`project_qualification()`/`project_fee()` artık ÇAĞRIDAN ÖNCE doğrulanmış bir kayıt varsayar — `isset(...) ? ... : ''` deseni kaldırıldı, alanlara doğrudan erişilir; `project_fee()` artık kendi başına fiyat seçeneklerini `(string)`/`(int)` ile cast edip geçersiz satırı `continue` ile atlayan ikinci bir yol İÇERMİYOR — `validate_fee()`'nin döndürdüğü KANONİK `normalized_price_options`'ı olduğu gibi kullanıyor.

### 3.5 Üst-seviye manifest şekli — fail-closed (YENİ)

`plan()` artık `{sectors: array, qualifications: array, fees: array}` şeklini `validate_manifest_shape()` ile ÖNCE doğrular: eksik anahtar, `null`, scalar, liste-olmayan değer veya fazladan üst-seviye anahtar → TEK üst-seviye hata (`errors[]`), hiçbir `entries` üretilmez (`manifest.sectors`/`.qualifications`/`.fees` artık ASLA sessizce boş diziye çevrilmez — önceki `isset(...) && is_array(...) ? ... : array()` deseni kaldırıldı).

### 3.6 `targetLookups[source_key]` — tek doğrulama/normalizasyon kapısı (YENİ)

`class-import-record-validator.php::normalize_target_lookup()` her `build_entry()` çağrısında ham lookup'ı şu invariant'lara göre kontrol eder — herhangi biri ihlal edilirse `target_state_valid=false` döner (istisna FIRLAMAZ, "güvenli" bir tahminle devam ETMEZ). **Faz 6B1 Son Kapanış Düzeltmesi'nde katılaştırılanlar kalın işaretlidir:**

- **`$lookup`'ın kendisi dizi DEĞİLSE** (ör. `targetLookups[$sourceKey]` string/int/bool/null dönmüşse) artık "hedef yok" gibi sessizce boş diziye ÇEVRİLMEZ — doğrudan `invalid_target_state`. (Önceki turda `build_entry()` `is_array($rawLookup) ? $rawLookup : array()` ile sessizce "bulunamadı" sayıyordu — kapatıldı.)
- **Kapalı anahtar kümesi**: `target_found`, `target_id`, `duplicate_targets`, `target_type_matches`, `has_source_key_marker`, `last_applied_hash`, `current_managed_fields` DIŞINDA bir anahtar varsa `invalid_target_state`.
- **`target_found`/`duplicate_targets`/`has_source_key_marker` artık GERÇEK `bool` ZORUNLU** — önceki `!empty()` kurtarması (string/int/null'ı sessizce truthy/falsy'e çeviren) kaldırıldı.
- `target_found=false` iken `target_id`/`current_managed_fields` gibi "bulundu" anlamına gelecek alanlar dolu OLAMAZ; **ayrıca (YENİ) `has_source_key_marker=true`, dolu bir `last_applied_hash`, veya `target_type_matches=false` da çelişkili sayılır** (bulunmayan bir hedefin "markerı/hash'i var" veya "türü uyuşmuyor" olması anlamsızdır).
- `target_found=true` iken `target_id` GERÇEK pozitif `int` OLMALI (numeric string/float/bool/negatif/`0`/dizi kabul edilmez); **ayrıca (YENİ) `target_type_matches` anahtarı MUTLAKA VERİLMİŞ olmalı** — adapter'ın hedefin gerçek türünü kontrol ettiğinin kanıtı, sessizce `true` varsayılmaz (bkz. `faz6b2-entegrasyon-notu.md`).
- `target_type_matches` GERÇEK `bool` OLMALI (string/int gibi başka bir tip fail-closed reddedilir).
- **(YENİ) Marker yokken (`has_source_key_marker=false`) dolu bir `last_applied_hash` çelişkilidir** → `invalid_target_state` (hash, yalnız markerlı bir kayıtta anlamlıdır — bu, "marker var ama hash yok/bozuk" → `legacy_missing_hash` durumundan AYRI, ters bir çelişkidir).
- `_mb_last_applied_hash` yalnız `^[0-9a-f]{64}$` ise kabul edilir; marker VAR ama hash null/kısa/büyük-harf/non-hex ise `null`'a düşürülür — karar motoru bunu `legacy_missing_hash` olarak sınıflandırır (create/update SAYILMAZ).
- **`target_found=true` VE `target_type_matches=true` iken `current_managed_fields` ARTIK eksik/null OLAMAZ** (önceki turda bu durumda eksik current field'lar sessizce "karşılaştırma verisi yok" sayılıyordu — kapatıldı). VARSA, ilgili tipin allowlist'iyle (`SECTOR_FIELDS`/`QUALIFICATION_FIELDS`/`FEE_FIELDS`) TAM anahtar eşitliği taşımalı — eksik/fazla anahtar veya dizi-olmayan bir değer `invalid_target_state`'e düşer, hash'e hiç sokulmaz (sahte manual-edit üretmez).
- **(YENİ) `current_managed_fields`'in yalnız anahtar KÜMESİ değil, HER ALANIN DEĞER TİPİ/BİÇİMİ de doğrulanır** (`validate_current_field_values()`) — ör. sektörde `image_attachment_id` string/negatif, yeterlilikte `level` string, ücrette `vat_included` string/`price_options` bozuk şekilli artık sessizce hash'e giremez, `invalid_target_state`'e düşer. Fiyat seçenekleri burada kanonik `evaluate_price_options()` İLE DEĞİL, ayrı bir KATI/sanitize-etmeyen şekil kontrolüyle (`validate_current_price_options_shape()`) doğrulanır — MEVCUT (potansiyel olarak bozuk) WordPress verisini "düzeltip" sahte-temiz bir hash üretmemek için. **(Kabul Öncesi Nokta Düzeltmeleri'nde GENİŞLETİLDİ)** bu şekil artık `MaviBelge_Core_Validator`'ın kanonik sınırlarının (`MAX_PRICE_OPTIONS`=20, `MAX_OPTION_LABEL_LENGTH`=200, `MAX_UNITS_PER_OPTION`=10, `MAX_UNIT_LENGTH`=50 — AYNI sabitler, ikinci bir kopya YOK) TAMAMINI VE listenin `sort_order`'a göre zaten KANONİK (azalmayan) sırada saklandığını uyguluyor; ücret current DTO'sunda ayrıca `title===profession_name`, `qualification_code` boş⇔`qualification_post_id===0` (dolu⇔pozitif), ve `valid_from<=valid_until` (ikisi de doluysa) çapraz-alan kontrolleri eklendi.

`build_entry()` ayrıca hem incoming hem current hash hesaplamasını `try/catch` ile sarar — `MaviBelge_Core_Import_Hash::hash()`'in fırlattığı `InvalidArgumentException` artık PLANIN TAMAMINI kesmez, yalnız o kaydı güvenli biçimde `invalid_target_state` conflict'ine düşürür.

**(YENİ) Public sınırda `TypeError` YOK**: `plan()`/`plan_sector()`/`plan_qualification()`/`plan_fee()` artık `array` tip ipucu TAŞIMIYOR — `$manifest`/`$targetLookups`/`$dependencies` scalar/null geçilirse `is_array()` ile İÇERİDE kontrollü, `errors[]` dolu bir sonuca (veya `plan_*()` düzeyinde `invalid` bir entry'ye) çevrilir; PHP'nin kendi ölümcül `TypeError`'ı asla fırlamaz.

**(Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri) `isset()` → `array_key_exists()` düzeltmesi:** `build_entry()` daha önce `isset($targetLookups[$sourceKey]) ? ... : array()` kullanıyordu — PHP'de `isset()` değeri `null` olan bir anahtarda `false` döner, bu yüzden `targetLookups[source_key] = null` sessizce boş diziye (= "hedef yok" = create adayı) çevrilebiliyordu. Artık `array_key_exists()` kullanılıyor: anahtar hiç yoksa açık kanonik `array('target_found' => false)` DTO'su üretilir; anahtar VARSA (değeri `null` dahil) ham değer OLDUĞU GİBİ `normalize_target_lookup()`'a verilir — o da (yukarıdaki "`target_found` MUTLAKA açıkça verilmiş olmalı" kuralıyla birlikte) dizi-olmayan/eksik-zorunlu-alanlı bir girdiyi kendi başına `invalid_target_state` yapar.

### 3.7 Toplu (`plan()`) batch bütünlüğü: `source_index` pozisyonu + provenance tutarlılığı (YENİ)

`validate_manifest_shape()` yalnız her listenin gerçek bir `0..n-1` PHP listesi olduğunu garanti eder — kayıtların KENDİ `source_index` alanının gerçek liste konumuyla eşleştiğini veya aynı listedeki tüm kayıtların aynı kaynak dosya/SHA-256'ya işaret ettiğini garanti ETMEZ. Yeni `class-import-record-validator.php::check_batch_positional_integrity( $records, $typeLabel )`, `plan()` içinde girişte-tekrar-eden-source_key kontrolünden HEMEN SONRA, entry üretiminden ÖNCE, HER tür listesi (sectors/qualifications/fees ayrı ayrı) için şunu doğrular:

1. Her kaydın `source_index`'i (gerçek int ise) kendi listedeki GERÇEK sıfır-tabanlı konumuyla BİREBİR eşleşmeli — tekrarlanan, atlanan veya ters çevrilmiş bir `source_index` reddedilir.
2. Aynı listedeki TÜM kayıtların `source.file`/`source.sha256`'ı BİRBİRİYLE AYNI olmalı — "provenance drift" (bir kaydın başka bir dosyadan/sürümden geldiğini iddia etmesi) sessizce kabul edilmez.

Şekli zaten bozuk olan kayıtlar (dizi değil, `source_index`/`source` eksik/yanlış tip) burada ATLANIR — onlar zaten kendi `validate_sector()`/`validate_qualification()`/`validate_fee()` yolunda `invalid` olarak raporlanır; bu kapı yalnız İYİ-ŞEKİLLİ kayıtlar ARASINDAKİ pozisyon/provenance tutarlılığını denetler. Herhangi bir ihlalde `find_source_key_problems()`'ın duplicate dalıyla AYNI fail-closed desen uygulanır: `errors` dolu, `entries` boş, `summary` varsayılan (`applicable=false`). Gerçek 14/83/103 manifest kaydının TAMAMI bu kapıdan hatasız geçtiği hem Node simülasyonuyla hem `tests/run.php`'deki "nokta 9/10" testleriyle doğrulandı.

### 3.8 Dependency çözümü artık typed sonuç gerektiriyor: `{id, type_verified}` (YENİ)

Önceki turlarda bir dependency haritasındaki (`sector_term_ids`/`sector_image_attachment_ids`/`qualification_post_ids`) bir çözüm salt bir `int` idi ve `is_resolved_dependency_id()` yalnız "gerçek pozitif PHP integer mi" diye bakıyordu. Bağımsız incelemenin bulduğu üzere bu, adapter'ın hedefin GERÇEKTEN beklenen türde (gerçek `mb_sektor` term'ü / gerçek `mb_yeterlilik` post'u / gerçek attachment) olduğunu doğruladığına dair HİÇBİR kanıt taşımıyordu — yalnız bir sayı, yanlış türde bir WordPress kaydına da ait olabilirdi.

`is_resolved_dependency_id()` kaldırıldı; yerine `MaviBelge_Core_Import_Record_Validator::resolve_verified_dependency( $dependencies, $mapKey, $itemKey )` geldi. Bir çözüm artık yalnız şu TAM şekilde GEÇERLİ sayılır:

```text
$dependencies[$mapKey][$itemKey] = array( 'id' => <int>0>, 'type_verified' => true )
```

Aşağıdakilerin HERHANGİ biri çözümü GEÇERSİZ yapar (`blocked_dependency` — create/update ASLA üretilmez): `$dependencies` veya `$dependencies[$mapKey]` dizi değil; `$itemKey` alt map'te yok; girdi dizi değil; `id`/`type_verified` DIŞINDA fazladan bir anahtar var (kapalı iki-anahtarlı şekil); `id` gerçek pozitif `int` değil (numeric string/float/bool/negatif/`0` dahil); `type_verified` GERÇEK `true` değil (eksik/`false`/`"true"` string'i dahil).

Bu saf katman WordPress'i sorgulayıp türü kendisi kontrol EDEMEZ — bu yüzden kanıtı, veriyle birlikte TAŞINAN bir bayrak olarak talep eder. Faz 6B2'nin gerçek adapter'ı bu bayrağı yalnız GERÇEKTEN `get_term_by()`/`get_post_type()` ile hedefin türünü doğruladıktan SONRA `true` olarak koymalıdır (bkz. `faz6b2-entegrasyon-notu.md`, güncellendi). Kodsuz 19 ücret hâlâ HİÇBİR bağımlılık aramaz, ilişkisi hâlâ her zaman `0`'dır — bu değişmedi.

## 4. Üç-hash karar tablosu — kod karşılığı (bkz. `class-import-decision.php`)

| # | Koşul | Karar sabiti | Neden kodu |
|---|---|---|---|
| 1 | Hedef kayıt yok | `CREATE` | `no_target` |
| 2 | `current===last===incoming` | `UNCHANGED` | `hash_match` |
| 3 | `current===last`, `incoming!==last` | `UPDATE` | `safe_update` |
| 4 | `current!==last`, `incoming===last` | `CONFLICT` | `manual_edit_detected` |
| 4b | `current!==last`, `incoming!==last` | `CONFLICT` | `both_changed` |
| 5 | source_key işareti var, `last_applied_hash` yok/geçersiz | `CONFLICT` | `legacy_missing_hash` |
| 6 | Aynı source_key için birden fazla hedef | `CONFLICT_DUPLICATE_TARGET` | `duplicate_target` |
| 7 | Hedef türü beklenenle uyuşmuyor | `CONFLICT_WRONG_TARGET_TYPE` | `wrong_target_type` |
| 8 | Zorunlu ilişki/medya çözülemedi | `BLOCKED_DEPENDENCY` | `dependency_unresolved` |
| 9 | Geçersiz manifest/alan şekli | `INVALID` | `invalid_shape` |
| 10 | Hedef lookup'ın şekli/bayrak tutarlılığı geçersiz (çelişkili `target_found`/`target_id`/`current_managed_fields`/`target_type_matches`) — Faz 6B1 Düzeltme ve Kabul'de eklendi | `CONFLICT` | `invalid_target_state` |

**Kontrol sırası (Faz 6B1 Düzeltme ve Kabul'de güncellendi — bağımsız incelemenin bulduğu "hedef durumu çelişkili olsa bile dependency/wrong-type kararı verilebiliyor" açığını kapatmak için `invalid_target_state` kontrolü artık duplicate'ten hemen sonra, wrong-type VE dependency'den ÖNCE gelir):**

```text
invalid_shape (madde 9)
  → duplicate_target (madde 6)
  → invalid_target_state (madde 10 — YENİ, bkz. class-import-record-validator.php::normalize_target_lookup())
  → wrong_target_type (madde 7)
  → dependency_unresolved (madde 8)
  → no_target / create (madde 1)
  → legacy_missing_hash (madde 5)
  → hash-tablosu (madde 2/3/4)
```

Gerekçe: bir hedefin "bulunma durumu" veya "türü" bile güvenilir değilse (çelişkili/biçimsiz lookup), o hedef üzerinden verilecek bir dependency veya wrong-type kararı da güvenilir sayılamaz — önce hedefin durumunun GEÇERLİ olduğu doğrulanır, ancak ondan sonra türe/bağımlılığa bakılır. `duplicate_target` yine EN öncelikli kalır (birden fazla hedef varken hangi hedefin "geçersiz" sayılacağı bile belirsizdir). Bu sıra, birleşik karşı-örnek testleriyle (aynı anda hem duplicate hem dependency-unresolved; hem wrong-type hem dependency-unresolved) doğrulanmıştır — bkz. `tests/run.php`, "record validator 14/20" ve "15/20".

## 5. Dry-run plan girdisi ve özet (bkz. `class-import-dry-run-planner.php`)

Her plan girdisi (`entries[]`):

```text
source_key, type, decision, reason, message,
target_id (yalnız sayısal ID veya null),
incoming_hash, current_hash, last_applied_hash (tam hash — kişisel veri yok, yalnız SHA-256 özet),
changed_fields (ALAN ADLARI, tam içerik değerleri DEĞİL),
unresolved_dependencies, warnings
```

Özet (`summary`, Faz 6B1 Düzeltme ve Kabul'de bağlayıcı operasyon grupları + invariant'larla genişletildi):

```text
total, by_decision (8 karar sabitinin her biri için ayrıntılı sayaç), by_type (sector/qualification/fee),
operations (bağlayıcı gruplar: create, update, unchanged, conflict [generic+duplicate+wrong-type'ın TOPLAMI], blocked, invalid),
total_matches_input (bool — toplamın giriş kayıt sayısına birebir eşitliği),
has_invalid (bool, en az bir kayıt invalid mi),
structurally_valid (Faz 6B1 Son Kapanış Düzeltmesi'nde EKLENDİ — bool: total_matches_input VE bilinmeyen-decision-yok VE has_invalid=false; ESKİ "applicable" tanımı buraya taşındı),
applicable (Faz 6B1 Son Kapanış Düzeltmesi'nde YENİDEN TANIMLANDI, fail-closed — bool: structurally_valid VE operations.conflict===0 VE operations.blocked===0 VE operations.invalid===0; yani yalnız create/update/unchanged kararları taşıyan TAMAMEN TEMİZ bir plan "uygulanabilir aday" sayılır)
```

**Neden değişti:** bağımsız incelemenin bulduğu üzere, eski `applicable` tanımı yalnız `has_invalid=false`'a bakıyordu — yalnız `conflict`/`conflict_duplicate_target`/`conflict_wrong_target_type`/`blocked_dependency` içeren, hiçbir `invalid` kaydı OLMAYAN bir plan da eskiden `applicable=true` sayılıyordu. Bu, Faz 6B3'ün (bu görevde yazılmadı) apply katmanı için TEHLİKELİYDİ — böyle bir plan hiçbir zaman otomatik çalıştırılmamalı. Yeni tanım fail-closed: `applicable=true` yalnız plan TAMAMEN temizse (hiç conflict/blocked/invalid yoksa).

Invariant'lar (kod ve `tests/run.php`'deki "record validator 17/20" ve "son kapanış 13/15" testleriyle zorunlu kılınır): `sum(operations) === total`, `sum(by_type) === total === entries kayıt sayısı`, bilinmeyen bir `decision` değeri sessizce sayım dışı KALMAZ (varsa `structurally_valid=false`'a, dolayısıyla `applicable=false`'a düşürür).

`plan()` girişte tekrar eden bir `source_key` bulursa **fail-closed** davranır: hiçbir `entries` üretmez, `errors[]`'e açık bir mesaj yazar (bkz. görev promptu §10 madde 13). Biçimsiz/eksik (string olmayan veya boş) `source_key` taşıyan kayıtlar da artık bu taramadan KAÇMAZ — ayrıca raporlanır (kendi tip-doğrulayıcısında zaten `invalid` sayılırlar).

Dry-run raporunda kişisel veri, parola, dosya sistemi mutlak yolu veya tam içerik gövdesi **yoktur** — yalnız alan ADLARI, SHA-256 özetleri ve sayısal ID'ler taşınır.

## 6. Yazma çağrılarının bulunmadığının statik kanıtı

```bash
grep -rnE "wp_insert_post|wp_update_post|wp_delete_post|wp_trash_post|wp_insert_term|wp_update_term|wp_delete_term|update_post_meta|add_post_meta|delete_post_meta|update_term_meta|add_term_meta|delete_term_meta|update_option|delete_option|\\\$wpdb|get_posts|WP_Query" wordpress-site/wp-content/plugins/mavibelge-core/includes/import/
```

Bu tarama **sıfır gerçek çağrı** buldu (yalnız dokblok/yorum metinlerinde bahsi geçiyor, kod olarak hiçbiri yok) — bu görevde çalıştırılan, gerçek bir statik kalite kapısıdır (bkz. teslim raporu).
