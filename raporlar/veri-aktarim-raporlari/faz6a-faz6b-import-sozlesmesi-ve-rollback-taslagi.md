# Faz 6B — WordPress İçe Aktarma Sözleşmesi ve Rollback Taslağı

> **Bu bir tasarım taslağıdır — kod içermez.** Faz 6A'nın ürettiği `wordpress-site/data/**` manifestlerini gerçek WordPress'e yazacak importer, bu görevin kapsamı dışıdır. Şema değişikliği ihtiyacı doğarsa bu belge onu **çözmez**, yalnız işaretler (bkz. §8).

## 1. Idempotent doğal/harici anahtar stratejisi (Faz 6A Güvenlik/Şema/Sözleşme Kapanışı'nda güncellendi)

İçe aktarma, WordPress post/term ID'lerini asla "zaten var mı" kontrolü için **kullanmaz** (ID'ler her ortamda farklı olabilir). Bunun yerine, Faz 6A'nın manifest sözleşmesindeki **kararlı `source_key`** (`sector:<slug>`, `qualification:<kod>`, `fee:<sector>:<level>:<slug(ad)>`) bir postmeta/termmeta alanında saklanır.

**Bağımsız inceleme bulgusu (kapatıldı):** yalnız `source_key` eşleştirmesi, `create`/`update` ayrımını yapabilir ama **gerçek bir çakışma tespiti sağlayamaz** — hedef kayıt importer'ın son yazdığından beri elle değiştirilmiş mi, yoksa yalnız kaynak veri mi değişmiş, bu tek alanla ayırt edilemez. Bu yüzden şema artık **iki** sistem-yönetimli alan taşır (bu görevde eklendi, `mb_sektor` termmeta + `mb_yeterlilik`/`mb_ucret` postmeta — hepsi `readonly`/`system_managed`, `show_in_rest=false`, admin UI'da düzenlenemez; bkz. `class-meta-schema.php` ve `class-taxonomies.php::register_sector_term_meta()`):

- `_mb_import_source_key` — Faz 6A manifest `source_key`'in aynısı (eşleştirme anahtarı).
- `_mb_last_applied_hash` — importer'ın SON BAŞARILI yazışında, o kaydın YÖNETTİĞİ alan kümesinin (importer'ın kendi yazdığı meta alanları, editörün serbestçe değiştirdiği `post_content` gibi alanlar DAHİL DEĞİL) deterministik SHA-256 özeti; tam 64 küçük-harf hex karakter (`MaviBelge_Core_Validator::is_valid_sha256_hash()` ile doğrulanır — post-meta ve term-meta AYNI kuralı kullanır).

Bu, Faz 6A'nın kendi manifest bütünlüğü için kullandığı `sha256Hex`/`toDeterministicJson` deseninin (bkz. `wordpress-site/tools/import/lib/hash.js`) WordPress tarafındaki karşılığıdır — Faz 6B bu ikisini birbirinden bağımsız olarak, aynı deterministik serileştirme kuralıyla uygulamalıdır.

- Sektör: `mb_sektor` terimi, `slug` zaten WordPress'in kendi benzersizlik kısıtını taşır — `source_key` ek bir güvence katmanıdır.
- Yeterlilik: `_mb_myk_code` + `_mb_level` + `_mb_revision` üçlüsü zaten benzersizlik kuralı taşıyor (Faz 2) — `source_key` bu üçlünün makine-okunur karşılığıdır.
- Ücret: `mb_ucret` başlığı benzersizlik taşımaz; `_mb_import_source_key` **tek** güvenilir eşleştirme anahtarı olur.

### 1.1 Kararlı `source_key`'in gerçek kapsamı — evrensel/değişmez bir kimlik DEĞİLDİR

`fee:<sector_slug>:<level>:<slugify(profession_name)>` biçimindeki ücret `source_key`'i yalnız ŞU ANKİ, dondurulmuş 2026 kaynağı (`tanitim-site/assets/data/fees.js`, 103 kayıt) için benzersiz ve kararlıdır — bu görevde yeniden doğrulandı. Bu, **evrensel/kalıcı bir doğal kimlik değildir**:

- Bir meslek adı veya sektör ataması gelecekte değişirse (ör. kurumsal yeniden adlandırma), yeni `source_key` bu şemaya göre FARKLI bir anahtar üretir — Faz 6B importer'ı bunu "aynı kaydın güncellemesi" değil, **yeni bir doğal anahtar** olarak görecektir. Bu, mevcut 103 anahtar için bir hata değildir; gelecekteki bir veri değişikliğinin importer'ın idempotency varsayımını nasıl etkileyeceğinin açık belgelenmesidir.
- İleride yeni bir tarife/kaynak serisi (`fees.js`in 2026'dan sonraki bir sürümü, ya da tamamen farklı bir kaynak) eklenirse, bu serinin kendi ad alanı/kimlik stratejisi konusunda **açık bir karar** (ör. `source_key`'e seri/yıl öneki eklemek, ya da tamamen ayrı bir eşleştirme stratejisi) Faz 6B başlamadan ÖNCE verilmelidir — bu belge o kararı vermez, yalnız ihtiyacı işaretler.
- Bu görevde mevcut 103 ücret `source_key`'i **kasıtlı olarak değiştirilmedi** — yalnız belgesi netleştirildi.

## 2. create/update/unchanged/conflict durumları — üç-hash karar tablosu

Eski tasarım (`source_key` eşleşti mi/eşleşmedi mi) tek başına `conflict`'i tespit edemiyordu. Yeni karar, üç değeri karşılaştırır:

- **`last_applied_hash`** — hedef kayıtta saklı `_mb_last_applied_hash` (importer'ın son yazdığı özet).
- **`current_managed_hash`** — hedef kaydın importer'ın YÖNETTİĞİ alanlarının, AYNI deterministik kurala göre, ŞU ANDAKİ (okuma anındaki) özeti.
- **`incoming_hash`** — bu çalıştırmanın manifestinden gelen, yeni yazılacak alan kümesinin özeti.

| # | last_applied_hash | current_managed_hash | incoming_hash | Durum | Davranış |
|---|---|---|---|---|---|
| 1 | yok (kayıt hiç yok) | — | — | `create` | Yeni post/term oluşturulur; `_mb_import_source_key` ve `_mb_last_applied_hash = incoming_hash` yazılır. |
| 2 | = current | = last | = last | `unchanged` | Hiçbir yazma yapılmaz (gereksiz `update_post_meta`/denetim kaydı üretilmez). |
| 3 | = current | = last | ≠ last | `update` (güvenli) | Kayıt importer'ın son bildiği haliyle DEĞİŞMEMİŞ (elle düzenlenmemiş) — yalnız kaynak değişmiş. Değişen alanlar güncellenir, `_mb_last_applied_hash = incoming_hash` yazılır. |
| 4 | ≠ current | = last | herhangi | `conflict` (elle değiştirilmiş) | Hedef kayıt, importer'ın son yazdığından beri elle değiştirilmiş (`current` artık `last`'tan farklı). **Otomatik ezilmez** — çakışma raporda listelenir, yönetici kararına bırakılır. |
| 5 | ≠ current | ≠ last | herhangi | `conflict` (hem elle değiştirilmiş hem kaynak değişmiş) | Hem kayıt elle değiştirilmiş HEM kaynak değişmiş — iki farklı değişiklik aynı anda üst üste biner. **Asla otomatik ezilmez**, en yüksek öncelikli çakışma sınıfı olarak raporlanır. |
| 6 | `_mb_import_source_key` VAR ama `_mb_last_applied_hash` YOK/geçersiz | — | — | `conflict` (eski/geçiş kaydı) | Bu kayıt eski (yalnız `source_key`'e dayanan) bir sürümle veya importer dışı bir yolla oluşturulmuş — üç-hash karşılaştırması yapılamaz. Otomatik işlem yapılmaz, elle inceleme gerekir. |

`editörün serbestçe değiştirdiği alanlar (ör. `post_content`, SEO alanları) importer'ın "yönettiği" alan kümesine hiçbir zaman dahil edilmez — bu alanlar hash hesabına girmez, dolayısıyla hiçbir zaman `conflict` üretmez ve importer tarafından asla ezilmez (eski tasarımla aynı ilke, artık gerçek bir tespit mekanizmasıyla).

## 3. Dry-run varsayılanı, açık uygulama adımı

İçe aktarıcının **varsayılan** çalışma modu her zaman dry-run'dır: create/update/unchanged/conflict sayılarını ve örnek kayıtları raporlar, **hiçbir yazma yapmaz**. Gerçek yazma yalnız açık bir ikinci bayrak/adımla (`--apply` benzeri) ve yalnız `mb_manage_tariff_period`/eşdeğer üst-düzey yetkiye sahip bir kullanıcının açık onayıyla tetiklenir. Bu, Faz 5'in "aktif tarife dönemi asla otomatik atanmaz" ilkesinin içe aktarma sürecine genişletilmiş halidir.

## 4. 19 bağlantısız ücret politikası

19 kodsuz ücret, Faz 6B'de `_mb_qualification_id = 0` ile yazılır (uydurma ID yok). İçe aktarıcı bu 19 kayıt için hiçbir otomatik eşleştirme **denemez** — Faz 6A'nın kararı (bkz. `faz6a-kodsuz-ucret-raporu.md`) aynen taşınır. Kurum ileride gerçek MYK kodu sağlarsa, bu **ayrı, elle tetiklenen** bir güncelleme olur, importer'ın kendiliğinden yapacağı bir tahmin değil.

## 5. Aktif tarife döneminin otomatik etkinleştirilmemesi

İçe aktarma, `_mb_tariff_period` meta alanına manifestin `planned_tariff_period` (`"2026"`) değerini yazabilir — bu yalnız KAYIT üzerindeki bir etikettir. `mb_active_tariff_period` **option'ı importer tarafından asla değiştirilmez**; bu, yalnız `admin/class-settings.php` üzerinden, yetkili bir insanın elle yaptığı, denetim kaydı tutulan bir eylem olarak kalır (Faz 5 sözleşmesi, değişmedi).

## 6. Batch/checkpoint yaklaşımı

- Sabit, küçük batch boyutu (ör. 20 kayıt/istek) — `max_execution_time`/`memory_limit` bilinmediği için büyük tek seferlik döngü kurulmaz (bkz. `wordpress-sunucu-bilgi-talebi.md`, hâlâ yanıt bekliyor).
- Her batch sonunda ilerleme durumu kalıcı bir yere (option veya özel tablo) yazılır — bir isteğin zaman aşımına uğraması, önceki batch'lerin tekrar işlenmesine yol açmaz (idempotent `source_key` eşleştirmesi zaten bunu doğal olarak sağlar, checkpoint yalnız gereksiz yeniden taramayı önler).
- Checkpoint, WP-CLI **ve** admin fallback yollarının ikisinde de aynı formatta olmalı — iki farklı checkpoint şeması olursa biri diğerini bozabilir.

## 7. WP-CLI / admin fallback

- **WP-CLI varsa:** `wp mavibelge import catalog --dry-run` (varsayılan) / `--apply`. Çıktı: create/update/unchanged/conflict sayıları + ilk N örnek satır.
- **WP-CLI yoksa (`wordpress-faz0-uyumluluk-matrisi.md`, sunucuda WP-CLI varlığı doğrulanmadı):** yalnız `manage_options`/`mb_manage_tariff_period` yetkisine sahip kullanıcıya açık, nonce korumalı bir yönetim ekranı; her istek küçük bir batch işler ve sayfa kendini otomatik yeniler (meta-refresh veya küçük bir AJAX döngüsü — Faz 8'in gerçek form altyapısına ihtiyaç duymaz, salt-okunur bir ilerleme göstergesidir).

## 8. Audit kaydı

Her `create`/`update`/`conflict` olayı mevcut `MaviBelge_Core_Audit_Log` mekanizmasına yazılır (Faz 2'den beri var, değişmiyor) — `event`, `object_type`, `object_id`, `context` (kısa: `source_key`, değişen alan adları — asla tam içerik/kişisel veri). `unchanged` olaylar denetim günlüğünü şişirmemek için loglanmaz.

## 9. Rollback — oluşturulan ID'ler ve önceki-durum anlık görüntüsü

- İçe aktarıcı, bu çalıştırmada **oluşturduğu** her post/term ID'sini ayrı bir listeye yazar (`create` olan kayıtlar) — bir rollback bu listedeki ID'leri `wp_trash_post`/`wp_delete_term` ile geri alabilir (kalıcı silme değil, çöp kutusu — WordPress'in kendi güvenli varsayılanı).
- İçe aktarıcı, **güncellediği** her kaydın güncelleme ÖNCESİ meta değerlerini de aynı listeye yazar (`update` olan kayıtlar) — bir rollback bu eski değerleri geri yazabilir.
- Bu liste, gerçek veritabanı **yedeğinin yerine geçmez** (AGENTS.md §5/§6 — mail/veri geri dönüşü kuralına paralel: "iki doğrulanmış kopya oluşmadan" ilkesiyle tutarlı olarak, gerçek aktarım öncesi tam veritabanı yedeği ayrıca alınmalıdır, bkz. §11).

## 10. İki kez çalıştırmada mükerrer oluşturmama testi

Faz 6B'nin WP entegrasyon test sözleşmesine eklenmesi gereken asgari senaryo: aynı manifest **iki kez** `--apply` ile çalıştırılır; ikinci çalıştırma sonunda toplam post/term sayısı **birinci çalıştırmayla birebir aynı** olmalı (0 yeni `create`, hepsi `unchanged` veya kasıtlı bir manifest değişikliği varsa `update`). Bu, `source_key` eşleştirmesinin gerçekten çalıştığının kanıtıdır. **Bu görevde çalıştırılmadı** — gerçek WordPress runtime gerektirir.

## 11. PHP 7.3 ve `utf8mb4` önkoşulu

İçe aktarıcı PHP 7.3 üzerinde çalışacak şekilde yazılmalı (mevcut proje kısıtı, değişmedi). Gerçek aktarımdan **önce** veritabanının `utf8mb4` karakter setinde olduğu doğrulanmalı (`wordpress-sunucu-bilgi-talebi.md`, hâlâ "Doğrulanamadı") — doğrulanmadan Türkçe içerik (83 yeterlilik adı, 103 meslek adı, tümü Türkçe karakter taşıyor) yazılırsa karakter bozulması riski `raporlar/proje-durumu.md`'nin "Yüksek riskler" listesinde zaten kayıtlıdır.

## 12. Gerçek aktarım öncesi veritabanı yedeği ve geri dönüş provası

Faz 6B'nin gerçek `--apply` çalıştırması **öncesinde**: (1) tam veritabanı yedeği alınır, (2) bu yedekten geri dönüşün fiilen çalıştığı **denenir** (AGENTS.md §5/açık risk listesi: "Yedekten geri dönüş hiç denenmemiş olabilir — Yayın ön koşulu"). Bu iki adım tamamlanmadan gerçek `--apply` çalıştırılmaz.

## Faz 6A'da tespit edilen engeller — durumu (Faz 6A Güvenlik/Şema/Sözleşme Kapanışı'nda güncellendi)

1. **MYK kod biçimi** — ~~20/83 kodda revizyon eki yok, mevcut `is_valid_myk_code_format()` bunları reddeder~~ **ÇÖZÜLDÜ**: `is_valid_myk_code_format()` revizyonu opsiyonel kabul edecek şekilde gevşetildi; yeni çapraz-alan kuralı (`myk_code_matches_level_revision()`) koda gömülü seviye/revizyonu `_mb_level`/`_mb_revision` ile tutarlı tutuyor. Bkz. `faz6a-manifest-sozlesmesi.md` §7 Bulgu #1 (çözüldü) ve `class-validator.php`.
2. **Sektör `icon`/`image` için term meta yok** — **ÇÖZÜLDÜ (şema düzeyinde)**: `class-taxonomies.php::register_sector_term_meta()` artık `_mb_icon_key` (sanitize_key + uzunluk sınırı) ve `_mb_image_attachment_id` (gerçek `attachment` post doğrulamalı, aksi halde 0) term-meta alanlarını kayıtlı tutuyor. Hiçbir terim/medya bu görevde OLUŞTURULMADI/İÇE AKTARILMADI — yalnız hedef şema hazır. Bkz. aynı belge, Bulgu #2 (çözüldü).
3. **İdempotency meta alanları henüz mevcut şemada tanımlı değildi** — **ÇÖZÜLDÜ (şema düzeyinde)**: `_mb_import_source_key` VE `_mb_last_applied_hash` artık `mb_sektor` termmeta'sında ve `mb_yeterlilik`/`mb_ucret` postmeta'sında tanımlı (readonly/system_managed, `show_in_rest=false`). Bu görevde hiçbir kayda gerçek değer YAZILMADI — yalnız §1/§2'deki üç-hash tasarımının dayanacağı alanlar hazırlandı. **Faz 6A Son Kabul Düzeltmesi'nde ayrıca kapatıldı:** her iki alan artık gerçek biçim doğrulaması taşıyor — `_mb_import_source_key` yalnız `qualification:`/`fee:`/`sector:` prefix ailelerinden birine uyan bir değeri kabul eder (`MaviBelge_Core_Validator::is_valid_import_source_key()`), `_mb_last_applied_hash` yalnız tam 64 küçük-hex karakteri kabul eder (`MaviBelge_Core_Validator::is_valid_sha256_hash()`) — her iki kural da post-meta (`class-field-repository.php`) ve term-meta (`class-taxonomies.php`) tarafında AYNI, tek paylaşılan Validator metoduyla uygulanıyor.

**Kalan, Faz 6B'yi hâlâ engelleyen madde:** §1.1'de belgelenen `source_key` kapsam sınırı bir "engel" değil, bir tasarım kısıtıdır — gelecekte yeni bir tarife serisi/ad değişikliği olursa AYRI bir karar gerekecektir, ama mevcut 2026 verisiyle Faz 6B'yi başlatmayı engellemez. Gerçek engel: bu üç madde **yalnız şema düzeyinde** çözüldü — importer kodunun kendisi (create/update/conflict mantığı, WP-CLI/admin fallback, batch/checkpoint, rollback) hâlâ YAZILMADI ve bu görevin kapsamı dışındadır (bkz. dosyanın en üstündeki uyarı). PHP/WordPress runtime doğrulaması (register_term_meta'nın gerçekten çalıştığı, attachment doğrulamasının gerçek bir medya kütüphanesine karşı davrandığı) da hâlâ açık bir kapı — bu görevde `php` ikili dosyası yoktu, hiçbir WordPress kodu çalıştırılmadı.

## Faz 6B3 Önkoşul — Son Kabul Düzeltmesi (23 Eylül 2026) — rollback kaydına etkisi

§9'daki rollback kaydı artık tek kapalı `MaviBelge_Core_Import_Apply_Eligibility::validate_rollback_record()` ile doğrulanır: `old_hash`/`new_hash` alanlardan yeniden hesaplanır, `changed_fields` deterministik üretilip birebir karşılaştırılır, anahtar kümesi kapalıdır; `rollback_allowed()` önce bu doğrulamayı yapar (yalnız `schema` + `new_hash` taşıyan kayıt reddedilir). Codex bulgusu üzerine eklendi; bağımsız incelemeye yeniden sunuldu. Rollback uygulaması hâlâ yazılmadı ve çalıştırılmadı.

## Faz 6B3 Önkoşul ve Yazma Güvenliği Kapanışı (23 Eylül 2026) — bu taslağa etkisi

Tarihsel metin yukarıda olduğu gibi korunmuştur. Bağlayıcı güncellemeler [`faz6b3-yazma-guvenligi-sozlesmesi.md`](./faz6b3-yazma-guvenligi-sozlesmesi.md) belgesindedir.

- **§1 güncellemesi — doğal anahtar:** "Ücret: `_mb_import_source_key` tek güvenilir eşleştirme anahtarı olur" ifadesi eşleştirme için hâlâ doğrudur. Ancak marker ile hedef bulunamadığında artık doğrudan `create` verilmez. Önce salt okunur doğal anahtar preflight'ı çalışır:
  - sektör: term slug;
  - yeterlilik: MYK kodu;
  - ücret: sektör slug + seviye + meslek slug'ı (Faz 6A formülü, tam eşitlik, fuzzy yok).

  Doğal anahtarla eşleşen yönetilmeyen, bozuk veya duplicate bir hedef fail-closed conflict üretir.
- **§2 tablosuna ek:** "#1 kayıt hiç yok → create" satırı artık yalnız `natural_key = none` iken geçerlidir. Diğer durumlar için karar matrisi sözleşme §6'dadır.
- **§8/§9 güncellemesi — audit/rollback:** kapalı rollback kayıt şekli (`mavibelge-import-rollback/1`) ve "rollback yalnız hedefin şu anki hash'i new_hash'e eşitse" kuralı `MaviBelge_Core_Import_Apply_Eligibility` içinde saf olarak tanımlandı. Gerçek audit/rollback **uygulanmadı**. Veritabanı yedeğinin yerine geçmez.
- **Batch:** tek bir conflict, blocked, invalid veya kanıtsız create bütün batch'i yazmadan durdurur (sözleşme §10). Checkpoint formatı (§6) hâlâ açık.
- **Para:** manifest ve saklanan temsil integer kuruştur. `_mb_certificate_print_fee_kurus`, `_mb_min_amount_kurus` ve `_mb_max_amount_kurus` artık `money_kurus` tipindedir; importer kuruşu TL dönüştürücüsünden geçirmez (sözleşme §1-§2).

## Faz 6B3 Apply Kodlaması (24 Eylül 2026) — bu taslağa etkisi

Tarihsel metin yukarıda olduğu gibi korunmuştur. Taslağın §3, §6–§10 maddeleri kodlandı ve yalnız yerel, silinebilir test ortamında doğrulandı; bağlayıcı ayrıntı [`faz6b3-apply-mimarisi.md`](./faz6b3-apply-mimarisi.md).

- **§3 (dry-run varsayılanı, açık apply):** `wp mavibelge import catalog` varsayılanı hâlâ salt okunur. `--apply` şunları gerektirir:
  - `MAVIBELGE_IMPORT_APPLY_ENABLED === true`;
  - `manage_options` + `mb_manage_tariff_period` yetkisi;
  - dry-run'da gösterilen `plan_digest`'in `--confirm` ile birebir onayı.
- **§6 (batch/checkpoint):**
  - Sabit batch boyutu 20'dir (1..20, tek merkezi sabit). Her batch kendi veritabanı transaction'ındadır.
  - Checkpoint `{prefix}mb_import_runs` / `{prefix}mb_import_run_items` tablolarında tutulur; WP-CLI ve ileride admin fallback aynı formatı kullanır.
  - Bağımlılık sırası için aşama (stage) modeli eklendi: `sectors` → `qualifications` → `all`.
- **§7 (admin fallback):** Uygulanmadı; entegrasyon notu mimari belgesi §9'dadır.
- **§8 (audit):** Yedi import olayı eklendi. Context kapalı izin listesinden geçer; `unchanged` audit üretmez.
- **§9 (rollback):**
  - Postlar çöp kutusuna alınır.
  - Terimlerin çöp kutusu olmadığı için sektör terimi yalnız sıkı koşullarla silinir.
  - Update'te yalnız yönetilen alanlar ve eski marker/hash geri yazılır.
  - Arada kullanıcı değişikliği varsa rollback reddedilir.
  - Rollback veritabanı yedeğinin yerine geçmez.
- **§10 (iki kez çalıştırma):** İzole WordPress'te ikinci apply `noop` döndü; yeni run, audit veya kayıt oluşmadı.
- **§11–§12 (utf8mb4, yedek ve geri dönüş provası):**
  - utf8mb4 ve InnoDB artık apply anında fail-closed denetleniyor.
  - Gerçek yedek ve geri dönüş provası hâlâ açık.
