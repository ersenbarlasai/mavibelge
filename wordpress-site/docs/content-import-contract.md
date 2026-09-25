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

- **Lokasyon** (`mb_lokasyon`), **doküman** (`mb_dokuman`), **SSS** (`mb_sss`): kaynakta doğrulanmış veri yok (adres/telefon/çalışma saati/doküman dosyası/SSS metni kurumdan gelmeli).
- **Gerçek referans / müşteri logosu:** `references.js` yalnız temsili logolardır; gerçek müşteri referansı için kurum onayı ve gerçek logo gerekir. Logo eki (attachment) bu fazda eşlenmez.
- Haber görselleri, SEO/AIO alanları, yayınlama/onay kararları: import bunları yazmaz.

## 8. Doğrulama kaydı

- Saf PHP birim/entegrasyon: `tests/suites/faz7-import-content.php` (sahte veri `zz-test-haber-*`, `zz-test-ref-*`; bellek içi sahte dünyada content aşamasının apply → rollback → yeniden-apply döngüsü, drift, atomik finalizer).
- Gerçek WordPress: `tools/runtime-test/scripts/apply-cycle-test-3.php` (yalnız silinebilir `mbfx_` fixture veritabanı ve sahte manifest; iki kontrollü tür terimini betik kendisi oluşturur), `tools/runtime-test/apply-cycle.sh` adım 4c ile birlikte WP-CLI `--stage=content` kapıları.
- Node: `test-extract-safety.js`, `test-manifest-validation.js`, `verify-manifest.js`, `test-content-manifest.js`, `verify-content-manifest.js`, `tools/verify-source-counts.js`, `tools/test-faz6b2-static-contract.js`.
- Gerçek `data/content/{news,references}.manifest.json` **hiçbir** runtime testinde apply edilmez; gerçek staging/canlı apply çalıştırılmadı.
