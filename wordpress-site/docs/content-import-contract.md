# İçerik Aktarımı Sözleşmesi — `news` (haber) ve `reference` (referans)

> **Kapsam:** Faz 6B'nin katalog import hattına (sektör / yeterlilik / ücret) eklenen **iki içerik türü**: `news` → `mb_haber`, `reference` → `mb_referans`. Aynı sözleşmeler geçerlidir: deterministik `source_key`, üç-hash karar motoru, dry-run, kapılı apply, rollback, `unmanaged_fingerprint`, atomik `Run_Finalizer`. **İkinci bir karar sistemi/doğrulayıcı yoktur**; mevcut tekiller genişletildi. Katalog türlerinin davranışı, sayıları (14/83/103/145/87/16/58/84/19) ve manifestleri değişmedi.
>
> Bu belge kod bloğu değil sözleşmedir. Kabul edilen üretim kısıtları (PHP 7.3 vb.) için `AGENTS.md` §8. Katalog tarafı için `raporlar/veri-aktarim-raporlari/`.

## 1. Kaynak ve manifest

| | Haber | Referans |
|---|---|---|
| Kaynak (donmuş) | `tanitim-site/assets/data/news.js` (`window.MB_NEWS`) | `tanitim-site/assets/data/references.js` (`window.MB_REFERENCES`) |
| Kayıt sayısı | **6** gerçek haber/duyuru (başlık ve tarihler gerçek) | **12** TEMSİLİ referans logosu — **gerçek müşteri DEĞİL** |
| Manifest | `wordpress-site/data/content/news.manifest.json` | `wordpress-site/data/content/references.manifest.json` |
| Şema | `data/schema/news.schema.json` | `data/schema/reference.schema.json` |
| `source_key` | `news:<slug>` (slug kaynaktaki `slug`) | `reference:<slug>` (slug = `slugify(name)`) |
| Kayıt alanları | `slug, title, published_on (YYYY-MM-DD), news_type (haber\|duyuru), summary, body` | `name, slug, logo_file, alt` |
| Ortak alanlar | `schema_version` ("2.0.0"), `source_key`, `source_index`, `source {file, sha256}` | aynı |

Zarf `sectors.manifest.json` ile aynıdır (`schema_version, record_type, count, source, notes, records`; `record_type` = `news` / `reference`). Bu iki dosya, beş katalog manifestinden **ayrıdır**; katalog üretimi/doğrulaması (`build-manifest.js`, `verify-manifest.js`) bunların **içeriğini** üretmez/doğrulamaz (yalnız `verify-manifest.js` bu iki dosya adını "beklenmeyen manifest" saymaz).

Kaynaktan gelmeyen hiçbir alan uydurulmaz: kurum, tarih, bağlantı yoktur. Kaynaktaki haber `image` alanı okunur ama **aktarılmaz** (yazıya özel fotoğraf yok). Referansın `file` yolu manifestte `logo_file` olarak (repo-göreli) yalnız **bilgi** amaçlı taşınır.

Metinler (başlık/özet/gövde/ad/alt) **kanonik düz metin** olmalıdır: boş olamaz, baş/son boşluk taşıyamaz, `<`/`>` ve kontrol karakteri (satır sonu/sekme hariç) içeremez, geçerli UTF-8 olmalıdır. Amaç: WordPress'e yazıp geri okuma turunda (kses/kırpma) değerin sessizce değişmemesi ve işaretleme enjeksiyonunun manifestten geçememesi.

### Üretim / doğrulama araçları

```bash
node wordpress-site/tools/import/build-content-manifest.js    # üretir (deterministik; ya-hep-ya-hiç)
node wordpress-site/tools/import/verify-content-manifest.js   # taze üretim = disk (byte), şema, 6/12 sayıları
node wordpress-site/tools/import/test-content-manifest.js     # negatif/regresyon testleri
```

Sayılar `tools/import/lib/expected-counts.js` içindeki **ayrı** `CONTENT_EXPECTED` sabitindedir (haber 6, referans 12); katalog `EXPECTED` sabitine dokunulmadı. Ön-doğrulama hatasında yazıcı hiç çağrılmaz; yazma sırasında ikinci yeniden adlandırma başarısız olursa ilk dosya eski içeriğine (veya yokluğuna) döndürülür.

## 2. PHP tarafı: türler, aşama, sıra

- Yeni türler `news` ve `reference`; yeni aşama **`content`** (`--stage=content`): önce haberler, sonra referanslar. Katalogdan bağımsızdır; `sectors` / `qualifications` / `all` aşamaları ve tür listeleri **aynen** kalır (`Apply_Plan::STAGES` üç katalog aşaması, `CONTENT_STAGES` = `content`, tanınan tümü `ALL_STAGES`).
- **Rollback sırası** apply sırasının tersidir: referans sonra haber (katalogda ücret → yeterlilik → sektör).
- İçerik dosyaları **yalnız `content` aşaması için** yüklenir (`Manifest_Loader::load_content()`); katalog aşamaları bu iki dosya yokken de varken de aynı sonucu verir.
- Planlayıcı `plan()` manifest şekli: `sectors` / `qualifications` / `fees` **zorunlu** kalır (boş dizi olabilir); `news` ve `references` **isteğe bağlı** listelerdir (varsa liste olmalı, yoksa boş sayılır). Katalog aşamalarının özet çıktısı (`by_type` üç anahtar) değişmez; `news`/`references` içeren planda `by_type` beş anahtardır.
- Komut: `wp mavibelge import catalog --dry-run --stage=content --format=json`; apply/rollback aynı kapılarla (`MAVIBELGE_IMPORT_APPLY_ENABLED`, `manage_options` + `mb_manage_tariff_period`, `--confirm=<digest>`).

## 3. Yönetilen alanlar (tek kaynak: `class-import-managed-fields.php`)

**Haber (`mb_haber`):**

| Alan | WordPress karşılığı | Değer |
|---|---|---|
| `slug` | `post_name` (doğal anahtar) | manifest `slug` |
| `title` | `post_title` | manifest `title` |
| `content` | `post_content` | manifest `body`, **ham dizge** |
| `excerpt` | `post_excerpt` | manifest `summary` |
| `published_on` | `post_date` | `YYYY-MM-DD`; yazılan `YYYY-MM-DD 12:00:00`, `post_date_gmt` sıfır (taslak) |
| `news_type_term_id` | `mb_haber_turu` terimi | **çözülmüş bağımlılık** (int > 0) |
| `approval_status` | `_mb_approval_status` | **sabit `in_review`** — import ASLA `approved`/`publish` yapmaz |

**Referans (`mb_referans`):** `slug` (`post_name`), `title` (`post_title`), `reference_status` = sabit `representative` (`_mb_reference_status`), `record_status` = sabit `active`, `sort_order` = `source_index + 1` (`_mb_sort_order`), `website_url` = sabit `""`, `logo_attachment_id` = sabit `0`. Ek eşlemesi yoktur (SVG izinli logo MIME türü değildir).

Ek olarak ikisi de sistem alanları `_mb_import_source_key` ve `_mb_last_applied_hash` taşır (meta şemasına `mb_haber`/`mb_referans` için salt-okunur, `system_managed` olarak eklendi; hash girdisine **girmezler**).

### Neden `draft`

İçe aktarma içerik **yayınlamaz**. Post her zaman `draft` olarak oluşturulur; `update` `post_status`'u **değiştirmez** (editör yayınlamışsa durum değişikliği, yönetilmeyen alan olarak drift sayılır). Haber `in_review` yazılır: onay geçişi (`approved`/`rejected`) `publish_mb_haberler` yetkili kullanıcıya aittir; editör onayladıktan sonra import onayı **geri almaz** — yönetilen alan değiştiği için plan `conflict` (`manual_edit_detected`) üretir. Referanslar temsilidir; gerçek müşteri gibi görünmemeleri için `representative` sabitlenir.

## 4. Doğal anahtar ve karar

Doğal anahtar preflight'ı (bulanık eşleme YOK):

- **news:** `mb_haber` içinde `post_name == slug`, çöp dahil.
- **reference:** `mb_referans` içinde `post_name == slug`, çöp dahil.

WordPress çöpe giden postun adına `__trashed` ekler ve asıl slug'ı `_wp_desired_post_slug` metasında saklar; bu yüzden **kullanıcının çöpteki** kaydı da doğal anahtar sorgusunda (`post_name` **veya** çöpteki `_wp_desired_post_slug`) bulunur ve `create`'i `conflict` yapar. İmport'un **kendi** rollback kabuğu bu izi taşımaz (aşağıya bakın), bu yüzden aynı manifest temiz rollback sonrası yeniden uygulanabilir.

Marker keşfi (tür uyuşmazlığı, duplicate, marker'sız/yabancı marker) katalog türleriyle aynı kanonik yoldan gider: `MaviBelge_Core_Validator::classify_import_source_key()` artık `news:<slug>` ve `reference:<slug>` biçimlerini de tanır (sector ile aynı slug kuralı; başka her biçim reddedilir).

## 5. Bağımlılık: `news_type_term_ids`

`Dependencies` DTO'sunda mevcut üç anahtar aynen kalır; **tek isteğe bağlı** anahtar `news_type_term_ids`: `haber` / `duyuru` → `{ id: int > 0, type_verified: true }` (aynı kapalı şekil; başka slug reddedilir). Anahtar yoksa veya ilgili tür çözülmemişse o haber `blocked_dependency` olur (tür **tahmin edilmez**); herhangi bir haber blocked ise plan `applicable=false` ve apply reddedilir.

İki kontrollü `mb_haber_turu` terimini (`haber`, `duyuru`) **import oluşturmaz**; salt okunur çözer (`get_term_by('slug', …, 'mb_haber_turu')`, taksonomi doğrulanır). Çözümleyici, mevcut `Target_Repository` arayüzünü bozmamak için **ayrı, isteğe bağlı** `MaviBelge_Core_Import_Content_Dependency_Resolver` arayüzündedir (`resolve_news_type_term_id()`); uygulamayan bir repository'de haberler `blocked_dependency` olur.

## 6. Yazma, drift ve rollback

- **Yazma:** doğrulanmış tek yük (`Write_Payload`) → dar `Target_Writer`. Yük kapalı şekillidir; haber için `approval_status` yalnız `in_review`, referans için yalnız `representative`/`active`/`""`/`0` yazılabilir. Yönetilen çekirdek alanlar (haber: `post_name`, `post_content`, `post_excerpt`, `post_date`; referans: `post_name`) **yalnız doğrulanmış yükten** yazılır ve geri okunarak doğrulanır. Taslağın açık tarihi `post_date_gmt` verilmeden korunur; güncellemede `edit_date` açıkça verilir (aksi hâlde `wp_update_post()` taslak tarihini "şimdi"ye sıfırlar) — gerçek WordPress'te doğrulandı.
- **`unmanaged_fingerprint`:** türün yönetilen çekirdek alanlarını (haber: `post_name`, `post_content`, `post_excerpt`, `post_date`; referans: `post_name`) ve haber için `mb_haber_turu` taksonomisini **dışlar**; geri kalan her şey (SEO/başka meta, başka taksonomi, `menu_order`, `post_status`, referanstaki `post_content`, …) drift olarak yakalanır. Yönetilen alanın editörce değişmesi ise hash uyuşmazlığıyla `drift_detected` verir.
- **Rollback (create):** parmak izi hâlâ eşleşiyorsa yalnız import'un yönettiği meta/terimi temizler, `post_name`'i boşaltır, sonra postu **çöpe** alır (asla kalıcı silme; `EMPTY_TRASH_DAYS=0` fail-closed). Çöpteki kabuk slug'ı ne `post_name` ne `_wp_desired_post_slug` olarak tutar; readback doğal anahtar sorgusunun `none` döndüğünü doğrular. Drift varsa hiçbir post çöpe gitmez, hiçbir terim/alan değişmez.
- **Rollback (update):** yalnız eski yönetilen alanlar (ve eski marker/hash) geri yazılır; yönetilmeyen alanlara dokunulmaz.
- Run durumu ve audit olayı atomiktir (`Run_Finalizer`); audit yalnız alan **adlarını** taşır (`content`, `excerpt`, …), içerik değerini asla.

## 7. NE İMPORT EDİLMEZ

Doğrulanmış kaynak bulunmadığı için aşağıdakiler bu hatta **yoktur** ve uydurulmaz:

- **Lokasyon** (`mb_lokasyon`) ve **doküman** (`mb_dokuman`): kaynakta doğrulanmış veri yok (adres/telefon/çalışma saati/doküman dosyası kurumdan gelmeli).
- ~~SSS ve gerçek referans logoları~~ **Faz 12b'de içe aktarılır** (aşağıdaki §10). Gerçek referans firma ADLARI hâlâ uydurulmaz: 15 logonun adı doğrulanmamıştır.
- Haber görselleri, SEO/AIO alanları, yayınlama/onay kararları: import bunları yazmaz.

## 8. Doğrulama kaydı

- Saf PHP birim/entegrasyon: `tests/suites/faz7-import-content.php` (sahte veri `zz-test-haber-*`, `zz-test-ref-*`; bellek içi sahte dünyada content aşamasının apply → rollback → yeniden-apply döngüsü, drift, atomik finalizer).
- Gerçek WordPress: `tools/runtime-test/scripts/apply-cycle-test-3.php` (yalnız silinebilir `mbfx_` fixture veritabanı ve sahte manifest; iki kontrollü tür terimini betik kendisi oluşturur), `tools/runtime-test/apply-cycle.sh` adım 4c ile birlikte WP-CLI `--stage=content` kapıları.
- Node: `test-extract-safety.js`, `test-manifest-validation.js`, `verify-manifest.js`, `test-content-manifest.js`, `verify-content-manifest.js`, `tools/verify-source-counts.js`, `tools/test-faz6b2-static-contract.js`.
- Gerçek `data/content/{news,references}.manifest.json` **hiçbir** runtime testinde apply edilmez; gerçek staging/canlı apply çalıştırılmadı.

## 9. Faz 12 — `pages` kaynağı/aşaması (32 gerçek `page` kaydı)

- **Yeni post type YOK**: çekirdek `page`. Manifest: `data/content/pages.manifest.json` (şema `data/schema/page.schema.json`, `schema_version` 2.0.0, kapalı 16 anahtar: `schema_version`, `source_key` (`page:<slug>`), `source_index`, `slug`, `title`, `content`, `excerpt`, `parent_source_key` (şu an hepsi null), `menu_order`, `page_template`, `post_status` (hedef: `draft`), `layout`, `content_sha256`, `pending_decisions`, `publish_hold`, `source`). Tek yetkili envanter `tools/import/lib/page-inventory.js` (32 kayıt); `verify-page-manifest.js` bunu statik dosyalar, `docs/page-template-map.md` ve tema `$hub`/`$form_disabled` listeleriyle çapraz doğrular ve manifesti iki kez üretip byte-eşitliğini sınar.
- **İçerik kaynağı**: yalnız `tanitim-site/*.html` ana içeriği. Kapalı HTML izin listesi Node (`sanitizeCheck`) ve PHP (`Record_Validator::page_content_errors`) tarafında aynıdır (`test-faz12-static-contract.js` eşitliği doğrular). Betik/olay işleyici/iframe/tehlikeli URL ret sebebidir. Kurumca onaysız KVKK/banka/alıcı/referans/URL verisi uydurulmaz (`pending_decisions` kapalı sözlüğü, 12 kod, 6'sı bloklayıcı).
- **Yönetilen alanlar** (`Managed_Fields::PAGE_FIELDS`): `slug, title, content, excerpt, parent_id, menu_order`; marker `_mb_import_source_key` + `_mb_last_applied_hash`. `post_status` yönetilmez.
- **Draft varsayılanı**: apply yalnız `draft` oluşturur/günceller; yayınlama ayrı işlemdir (`Page_Publisher`, bkz. `admin-import-operations.md` §8.2).
- **Aşama zinciri (tek kaynak)**: `pages → sectors → qualifications → all → content` (`Apply_Plan::PREREQUISITE_STAGE`); admin ve CLI aynı doğrulamayı kullanır.
- Doğrulama: `tests/suites/faz12-pages.php` (bellek içi sahte dünya), `tools/import/test-page-manifest.js`, `tools/test-faz12-static-contract.js`, gerçek WordPress: `admin-import.sh` (`pages` fazı: apply → readback → rollback → reapply → yayın), `admin-import-http.sh` (HTTP kapıları + yayın), `apply-cycle.sh` (CLI zincir), `pages-render.sh` (41 rota, güzel bağlantı, robots/sitemap).

## 10. Faz 12b — SSS (`mb_sss`), gerçek logolu referanslar ve onaylı sayfa kaynakları

Kullanıcı/kurum, yedi bekleyen sayfanın kaynaklarını açıkça onayladı (26 Eylül 2026). Derleme sırasında **ağ isteği yapılmaz**; onaylı içerik yerel, deterministik kaynak dosyalarına alındı ve künyelendi.

### 10.1 Üç içerik manifesti (`content` aşaması: news → reference → faq)

| | Haber | Referans | SSS |
|---|---|---|---|
| Kaynak | `tanitim-site/assets/data/news.js` | `wordpress-site/data/sources/reference-logos/` (onaylı canlı sayfadan bir kez alınmış 15 PNG + `reference-logos.manifest.json`: URL, alınma tarihi, SHA-256, bayt, boyut) | `tanitim-site/sss.html` (kurumca onaylanmış, dondurulmuş, yalnız okunur) |
| Kayıt sayısı | 6 | **15** | **6** |
| `source_key` | `news:<slug>` | `reference:referans-NN` | `faq:<slug>` (`slugify(soru)`) |
| WordPress | `mb_haber` | `mb_referans` + **gerçek attachment** | `mb_sss` (başlık = soru, gövde = cevap, düz metin) |

**Referans adı tahmin edilmez.** Canlı sayfadaki logo kaydırıcısı (Super Logo Showcase) logolar için başlık/alt metin/bağlantı taşımıyor; `logosliderwp` kayıtları yalnız sayısal ad taşır. Bu yüzden `name` nötr sıra etiketidir ("Referans 01" … "Referans 15") ve her kayıtta `name_status = "unverified"`. Gerçek firma adları kurumca doğrulanınca ayrı bir onaylı karar ve manifest güncellemesiyle girilir. Alt metin: "Referans NN — kuruluş logosu".

### 10.2 Logo → gerçek attachment

- Yönetilen alan `logo_sha256` (`REFERENCE_FIELDS`; `logo_attachment_id` alandan çıktı). **Manifestteki değer** logo dosyasının SHA-256'sıdır; **mevcut durum** değeri, bağlı attachment'ın GERÇEK dosya özetidir (`Repository::attachment_logo_sha256()`: attachment, çöpte değil, `image/png`, dosya okunabilir). Geçersiz/silinmiş/okunamayan logo '' üretir → kayıt `unchanged` sayılmaz (`conflict`); "geçerli ve erişilebilir logo" ayrı bir kontrol değil karar hash'inin kendisidir.
- Yazıcı (`Target_Writer`, yeni `ensure_logo_attachment()`): önce AYNI içerik özetli attachment aranır (aynı logo tekrar eklenmez; rollback→reapply logoyu yeniden kullanır); yoksa özet, logo dizinindeki `ref-NN.png` dosyalarından **SHA-256 eşleşmesiyle** bulunur, uploads'a kopyalanır, gerçek `attachment` kaydı oluşturulur (yeniden boyutlandırma/kırpma yok; oran korunur), `_mb_logo_attachment_id` yazılır ve dosya özetiyle geri okunur. Hatalar sabit kodludur (`logo_source_missing`, `logo_copy_mismatch`, `logo_readback_mismatch`, …) ve tüm batch'i geri alır. Rollback attachment SİLMEZ.
- Yükleyici (`Manifest_Loader::load_content()`): her logo dosyası (varlık, bayt, SHA-256, PNG imzası/IHDR boyutu, benzersizlik) içe aktarımdan ÖNCE doğrulanır; hata metni mutlak yol içermez; herhangi bir hata TÜM içerik manifestini reddettirir (**fail-closed**). Logo dizini manifest dizininin kardeşidir (`data/content` → `data/sources/reference-logos`): sunucuya **`data/` dizini bir bütün olarak** (içerik + sources) konur.
- Referans kayıtları ve SSS kayıtları **taslak** oluşturulur (`draft`; hiçbir içerik türü otomatik yayınlanmaz). `mb_referans` yayın hazırlığı zaten logo attachment'ı ister (`Publish_Readiness`).

### 10.2a Dosya sistemi yan etkisi telafisi (Faz 12c; eklenti 0.5.2)

**Sorun (0.5.1):** logo dosyası uploads'a `copy()` ile kopyalanır; attachment satırı DB transaction'ı içinde oluşur. DB rollback'i **dosyayı geri almaz**; batch audit/checkpoint/commit/readback hatasında DB temizlenir ama dosya yetim kalırdı.

**Çözüm:** `Target_Writer` üç yöntemli bir yan etki kapsamı taşır: `begin_side_effect_scope()` (batch transaction'ı açıldıktan sonra), `commit_side_effect_scope()` (başarılı commit sonrası; dosyalar KALIR), `compensate_side_effect_scope($dbRolledBack)` (hata yolunda, DB rollback denendikten SONRA).

- Yalnız **bu batch'te YENİ oluşturulan** logo dosyaları günlüğe yazılır. SHA eşleşmesiyle **yeniden kullanılan** mevcut attachment/dosya günlüğe hiç girmez → asla silinmez.
- Telafi yalnız `$dbRolledBack === true` iken çalışır. DB rollback başarısızsa **hiçbir dosya silinmez** (fail-closed); çağıran `transaction_rollback_failed` döner, günlük bırakılır ve uploads elle incelenmelidir.
- Silmeden önce doğrulama: ad kalıbı `mavibelge-referans-logo-<12 hex>[-N].png`; `realpath()` ile uploads `basedir` içinde; symlink değil; dizin atlama yok; attachment satırı artık yok; başka hiçbir attachment `_wp_attached_file` ile bu dosyaya işaret etmiyor. Aksi hâlde silinmez ve sabit kod döner.
- Sabit hata kodları (mutlak yol/içerik/sır içermez): `side_effect_scope_failed`, `side_effect_path_rejected`, `side_effect_attachment_still_present`, `side_effect_file_referenced`, `side_effect_cleanup_failed` (rollback başarısızsa `transaction_rollback_failed`). Temizlik hatası **yutulmaz**: run `failed`/`rollback_required` olur, başarı raporlanmaz.
- Aynı kapsam `Rollback_Service` batch döngülerine de bağlıdır (geri yükleme `update_post` yolu attachment oluşturabilir).

**Sınırlar (dürüst):** (a) Explicit run rollback, **commit edilmiş** attachment/dosyaları SİLMEZ (başka kayıt paylaşıyor olabilir; kanıt yok) — logo attachment'ları rollback sonrası kalır ve reapply'da yeniden kullanılır. Kapatılan açık: commit edilmemiş/geri alınmış batch'ten yetim dosya sızıntısı. (b) PHP süreci batch ortasında ölürse (fatal/OOM/güç kesintisi) günlük yalnız bellektedir; DB transaction'ı otomatik geri alınır ama o batch'in dosyası yetim kalabilir (dosya adı kalıbı `mavibelge-referans-logo-*` ile elle tespit edilir; `_mb_import_logo_sha256` meta'sı olmayan dosya yetimdir). (c) Kapsam yalnız logo dosyasını izler; başka dosya yan etkisi yoktur.

### 10.3 Yedi sayfa

| Sayfa | Kaynak | Uygulama |
|---|---|---|
| `kvkk` | `https://mavibelge.com.tr/kvkk-2/` | gövde parçası `data/sources/approved/kvkk.html`; yeniden yazım/özet yok |
| `gizlilik-politikasi` | `https://mavibelge.com.tr/gizlilik-politikamiz/` | aynı |
| `banka-hesap-bilgileri` | `https://mavibelge.com.tr/banka-hesap-bilgileri/` | aynı; banka/hesap verisi üretilmez/normalleştirilmez (rapora/teste yazılmaz) |
| `sinav-takvimi` | `https://mavibelge.pratikteorik.com/home/examcalendar` | yerel kısa bilgilendirme + tek CTA (birebir adres); iframe yok |
| `sonuc-belge-sorgulama` | MYK portalı `…layout=aday_bilgi_sorgu` | yerel kısa bilgilendirme + tek CTA (query string birebir); kimlik bilgisi/form/iframe yok |
| `referanslar` | canlı referans sayfası | içerik boş; liste `mb_referans` kayıtlarından (gerçek logo) çizilir |
| `sss` | `tanitim-site/sss.html` | içerik boş; liste `mb_sss` kayıtlarından çizilir |

Künye: `data/sources/approved/approved-sources.manifest.json` (URL, alınma tarihi, tam sayfa SHA-256, fragment SHA-256, `cta_url`). Sayfa manifestinde `source.file` bu fragment'tır; Node doğrulayıcı içeriği fragment'tan YENİDEN türetip eşitliğini, CTA adresinin künyeyle birebirliğini ve iframe/script/form yokluğunu denetler (anlamsal doğrulama). Kapalı HTML izin listesi Node ve PHP'de aynıdır.

### 10.4 Yayın kapıları (`pending_decisions` → `publish_requires`)

Altı çözülen kod (`kvkk_text_not_approved`, `bank_details_not_approved`, `exam_calendar_url_missing`, `myk_query_url_missing`, `references_not_real`, `faq_content_not_approved`) **kaldırıldı** (Node ve PHP sözlükleri). 32 sayfanın hiçbiri kurum kararı nedeniyle bekletilmez. Yeni kapalı alan `publish_requires` (`faq` / `reference`): `referanslar` → `reference`, `sss` → `faq`. **SUNUCU tarafında** (`Page_Publisher`): bir sayfa yalnız, ilgili içerik türünün TÜM manifest kayıtları gerçek readback ile `unchanged` (referans için geçerli logo dahil) ve **yayında** ise `ready` olur; aksi halde `content_not_ready`. Kapı yayın anında yeniden doğrulanır (TOCTOU); JS yalnız gösterir. Stale run, çift tıklama, kilit, özet uyuşmazlığı, çözülmemiş run ve ortam (yalnız staging) kapıları aynen korunur.

### 10.5 Eski URL yönlendirmeleri

`/kvkk-2/` → `/kvkk/` ve `/gizlilik-politikamiz/` → `/gizlilik-politikasi/`: `origin=verified` (içerik bu adreslerden onayla aktarıldı), 301, tek atlamalı, döngüsüz; hedef gerçekten yoksa kural pasif yazılır, yalnız istek 404 ise uygulanır.

### 10.6 Doğrulama
`tests/suites/faz12b-faq-references.php` (bellek içi sahte dünya: dry-run/apply/readback/idempotency/rollback/reapply, logo yeniden kullanımı, fail-closed yükleyici, atomiklik), `tests/suites/faz12-pages.php` (yayın kapısı), `tools/import/test-content-manifest.js`, `tools/import/test-page-manifest.js`, `tools/test-faz12-static-contract.js`; gerçek WordPress: `apply-cycle.sh` (gerçek attachment), `admin-import.sh` (`pages` fazı: içerik kapısı), `admin-import-http.sh`, `pages-render.sh` (gerçek 15 logo + 6 SSS + onaylı sayfalar, 32/32 yayın).
