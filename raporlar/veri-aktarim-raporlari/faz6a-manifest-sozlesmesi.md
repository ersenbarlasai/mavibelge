# Faz 6A — Katalog Veri Manifest Sözleşmesi

> Kaynak kod: `wordpress-site/tools/import/**`. Üretilen dosyalar: `wordpress-site/data/content/*.manifest.json`, `wordpress-site/data/mapping/*.manifest.json`. Bu belge o dosyaların gerçek alan sözleşmesini ve üretim sırasında bulunan **bloklayıcı sözleşme açıklarını** kaydeder. Bu görevde WordPress'e hiçbir yazma yapılmamıştır; şema **değiştirilmemiştir**.

## 0. Şema sürümü

`schema_version: "1.0.0"` — her manifest dosyasının ve her kaydın kendi içinde taşıdığı sabit sürüm dizesi. Faz 6B'de alan eklenir/değişirse bu sürüm artırılır.

## 1. Güvenli kaynak çıkarımı — özet

`wordpress-site/tools/import/extract-source.js`, yalnız üç sabit kodlanmış yolu okur (`tanitim-site/assets/data/{sectors,qualifications,fees}.js`). Hiçbir yol dışarıdan/CLI argümanından kurulmaz. Okuma öncesi:

1. Yorumlar (yalnız tam-satır `//` yorumları) çıkarılır.
2. Kalan metin **tam olarak** `window.MB_<AD> = [ ... ];` biçimine uymuyorsa iş durur (ikinci ifade, fonksiyon çağrısı, `require(...)` içeren her şey burada reddedilir).
3. Kod, yalnız boş bir `window` nesnesi içeren izole bir `vm` bağlamında çalıştırılır (`codeGeneration: { strings: false, wasm: false }` — sandbox içinde bile `eval()`/`new Function()`/WASM string-kod üretimi kapalı); `runInContext` 1000 ms zaman aşımı taşır.
4. Çalıştırma sonrası sandbox'ın üst-seviye anahtarları çalıştırma öncesiyle karşılaştırılır — `window` dışında herhangi bir global oluşturulmuşsa iş durur.
5. `window` üzerinde beklenen isim dışında bir özellik varsa iş durur.
6. Sonuç bir dizi değilse iş durur.

`wordpress-site/tools/import/test-extract-safety.js` bu altı katmanı 16 ayrı kötü niyetli/gerçek girdiyle doğrudan test eder (`process`/`require` erişimi, `eval`, ikinci ifade, IIFE, sonsuz döngü, yanlış global ad, dizi-olmayan sonuç, vb.) — **gerçekten çalıştırıldı, 16/16 geçti**.

## 2. Kararlı `source_key` kuralları

| Tür | Kural | Neden benzersiz |
|---|---|---|
| Sektör | `sector:<slug>` | `sectors.js`'teki 14 slug zaten benzersiz (doğrulandı). |
| Yeterlilik | `qualification:<tam MYK kodu>` | `qualifications.js`'teki 83 kod zaten benzersiz (doğrulandı). |
| Ücret | `fee:<sector_slug>:<level>:<slugify(profession_name)>` | Kaynaktaki gerçek `(name, level, sector)` üçlüsünün 103 kayıtta **benzersiz olduğu doğrudan doğrulandı** (`sort \| uniq -c` ile tekrar sayısı her zaman 1). Başlık benzerliğine dayanan bulanık/fuzzy bir anahtar **kullanılmadı** — üçü de kaynağın kendi gerçek alanlarından deterministik türetilir. Çakışma tespit edilirse `build-manifest.js` sessiz bir sonek eklemez; hata ile durur (`test-extract-safety.js` bunu ayrıca test etmez, ama `build-manifest.js`'in çakışma kontrolü kod içinde açık ve koşulsuzdur). |

## 3. Sektör manifesti alanları (`content/sectors.manifest.json`)

| Alan | Kaynak | Not |
|---|---|---|
| `source_key`, `source_index` | türetilmiş | — |
| `slug` | `sectors.js: slug` | WordPress alan sözleşmesine uygun (`a-z0-9` + tire) doğrulandı. |
| `name` | `sectors.js: name` | — |
| `description` | `sectors.js: desc` | **Alan adı kasıtlı olarak yeniden adlandırıldı** (`desc` → `description`) — WP terim tablosunun kendi yerleşik `description` alanına karşılık gelir (bkz. §7 Bulgu #2). |
| `icon`, `image` | `sectors.js: icon, image` | **Bloklayıcı sözleşme açığı #2** — bkz. §7. Boş `image` (5 sektörde) aynen boş bırakıldı, uydurulmadı. |
| `source.file`, `source.sha256` | — | Repo-göreli yol + SHA-256. |

Üst seviye `notes[]`: `maden`/`mermer` aynı görseli paylaştığı tespit edilirse otomatik not eklenir (bu turda: evet, `real-mermer.png` ikisinde de — kurum onayı bekliyor, bkz. `raporlar/proje-durumu.md`).

## 4. Yeterlilik manifesti alanları (`content/qualifications.manifest.json`)

| Alan | Kaynak | Not |
|---|---|---|
| `code` | `qualifications.js: code` | Değiştirilmedi. |
| `name` | `qualifications.js: name` | Değiştirilmedi. |
| `level` | `qualifications.js: level` | Integer, 1-8 doğrulandı. |
| `sector_slug` | `qualifications.js: sector` | Gerçek sektör manifestinde varlığı doğrulandı. |
| `revision`, `has_revision` | **koddan ayrıştırıldı** | Bkz. §7 Bulgu #1 — kural: `^([0-9]{2}UY[0-9]{4})-([0-9]{1,2})\/([0-9]{2})$` eşleşirse `/NN` sonrası revizyon; `^([0-9]{2}UY[0-9]{4})-([0-9]{1,2})$` eşleşirse (revizyon eki yok) `revision=""`, `has_revision=false`. **Asla uydurulmadı.** Kod: `wordpress-site/tools/import/lib/myk-code.js`. |
| `matches_legacy_revision_required_format` | türetilmiş | Faz 6A Son Kabul Düzeltmesi'nde yeniden adlandırıldı (eski adı: `myk_code_matches_current_plugin_format` — GÜNCEL eklenti davranışını artık yansıtmıyordu, bkz. §7 Bulgu #1 "çözüldü" notu). Yalnız tarihsel/bilgi amaçlıdır; güncel `is_valid_myk_code_format()` bu 20 kaydı KABUL EDER. Manifest `schema_version` bu yeniden adlandırma nedeniyle `2.0.0`'a çıktı. |
| `planned_record_status` | sabit `"active"` | `mb_yeterlilik` için `MaviBelge_Core_Meta_Schema`'daki `_mb_record_status` alanının kendi varsayılan değeri — yeni bir karar değil, mevcut şemanın taşıdığı varsayılanın manifestte açık yazılmış hali. |

## 5. Ücret manifesti alanları (`content/fees.manifest.json`)

| Alan | Kaynak | Not |
|---|---|---|
| `profession_name`, `level`, `sector_slug` | `fees.js: name, level, sector` | Sektör referansı doğrulandı. |
| `qualification_code` | `fees.js: qualificationCode` | Boş olabilir (19 kayıt). |
| `qualification_source_key` | **türetilmiş** | Dolu kodlarda (84) tam eşleşen yeterlilik kaydının `source_key`'i; boş kodlarda (19) **kasıtlı `null`** — bkz. §6. |
| `pricing_type` | `fees.js: pricingType` | `single/unit/package/multiple` — mevcut `MaviBelge_Core_Meta_Schema::PRICING_TYPES` ile birebir aynı, **fark yok**. |
| `price_options[]` | `fees.js: options[]` | Her satır `{label, units[], amount_kurus, sort_order}`; `sort_order` kaynakta yok, **dizi içindeki sıra** kanonik `sort_order` olarak kullanıldı (satır pozisyonu — mevcut `MaviBelge_Core_Validator::normalize_price_options()`'ın "sort_order verilmediyse pozisyona düşer" davranışıyla tutarlı). Etiket/birim uzunluğu ve sayısı sınırları (`MAX_OPTION_LABEL_LENGTH=200`, `MAX_UNITS_PER_OPTION=10`, `MAX_UNIT_LENGTH=50`, `MAX_PRICE_OPTIONS=20`) **aynı sabitlerle** yeniden uygulandı (`wordpress-site/tools/import/lib/price-options.js`) — 145 seçeneğin tamamı bu sınırların içinde. |
| `min_amount_kurus`, `max_amount_kurus` | türetilmiş | `price_options[].amount_kurus`'tan hesaplandı. |
| `vat_included` | `fees.js: vatIncluded` | 103 kaydın 103'ü de `true` (kaynakta değişken değil). |
| `certificate_print_fee_kurus` | `fees.js: certificatePrintFeeExcluded * 100` | Bkz. §7 "İncelenip gap OLMADIĞI belirlenen" madde. |
| `source_name`, `source_page` | `fees.js: source, sourcePage` | Değiştirilmedi. |
| `source_attachment_id` | sabit `0` | Gerçek PDF henüz WordPress medya kütüphanesine yüklenmedi — **uydurulmadı**, Faz 6B/medya aktarımının işi. |
| `planned_tariff_period` | sabit `"2026"` | **Tek doğrudan kaynaktan çıkarılabilir ipucu**: `fees.js` dosyasının kendi 1. satır başlık yorumu "Mavi Belge — 2026 sınav ücretleri" der. Uydurulmadı; kaynak satırı belgelenmiştir. Faz 6B, `mb_active_tariff_period` option'ını **otomatik etkinleştirmez** (bkz. `faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md`). |
| `planned_record_status` | sabit `"draft"` | `mb_ucret` için `MaviBelge_Core_Meta_Schema`'daki `_mb_record_status`'ün kendi varsayılan değeri — içe aktarım hiçbir kaydı otomatik "active" yapmaz. |
| `planned_valid_from`, `planned_valid_until` | sabit `""` | Kaynakta bu bilgi **hiç yok** — açıkça karar bekleyen alan olarak boş bırakıldı, uydurulmadı. |

## 6. 19 kodsuz ücret — politika

`qualificationCode` kaynakta boş olan 19 kayıt (`data/mapping/unmatched-fees.manifest.json`, ayrıca bkz. `faz6a-kodsuz-ucret-raporu.md`): `qualification_source_key` **kasıtlı olarak `null`** bırakılır. Ad/kod benzerliğine dayanan hiçbir otomatik veya elle eşleştirme **yapılmadı** — brief §6.3 ve §7'nin açık yasağı budur.

## 7. Bulgular

### Bloklayıcı sözleşme açığı #1 — MYK kod biçimi, revizyon eki opsiyonel olmalı — **ÇÖZÜLDÜ (Faz 6A Güvenlik/Şema/Sözleşme Kapanışı)**

*(Aşağıdaki üç paragraf orijinal, ilk tespit anındaki metindir — tarihsel bulgu olarak silinmeden korunmuştur.)*

`MaviBelge_Core_Validator::is_valid_myk_code_format()` (`wp-content/plugins/mavibelge-core/includes/class-validator.php`) şu an **zorunlu** revizyon eki bekliyor: `^[0-9]{2}UY[0-9]{4}-[0-9]{1,2}\/[0-9]{2}$`. Gerçek kaynakta (`qualifications.js`, 83 kayıt yeniden sayıldı) **63 kod bu biçime uyuyor, 20 kod uymuyor** (`/NN` eki yok, ör. `"13UY0145-3"`). Faz 6B bu 20 kaydı olduğu gibi (revizyon uydurmadan) yazmaya çalışırsa **mevcut validator onları reddedecektir**.

**Bu görev şemayı değiştirmedi** — yalnız bulguyu manifestte (`myk_code_matches_current_plugin_format: false` bayrağı, 20 kayıtta) ve burada kayda geçirdi. Faz 6B'den önce çekirdek agentının kararı gerekir: (a) regex'i revizyon eki opsiyonel olacak şekilde gevşetmek, ya da (b) kurumdan eksik 20 revizyon bilgisini istemek. İkisi de bu görevin kapsamı dışıdır.

**Çözüm kanıtı (Faz 6A Güvenlik/Şema/Sözleşme Kapanışı, bu turda yapıldı):** (a) seçeneği uygulandı. `is_valid_myk_code_format()` artık `^[0-9]{2}UY[0-9]{4}-[0-9]{1,2}(\/[0-9]{2})?$` — revizyon eki opsiyonel. Yeni `parse_myk_code()`/`myk_code_matches_level_revision()` çapraz-alan kuralı eklendi: kod DOLU ise koda gömülü seviye `_mb_level` ile, (varsa) revizyon `_mb_revision` ile eşleşmelidir — bu TEK kural hem `class-meta-boxes.php::enforce_myk_uniqueness()` (admin kaydı) hem `class-publish-readiness.php::check_yeterlilik()` (yayın kapısı) tarafından çağrılır, ikinci bir regex kopyası yoktur. `tests/run.php`'ye gerçek 63 revizyonlu + 20 revizyonsuz kodun TAMAMINI kapsayan testler eklendi (**yazıldı, `php` ikili dosyası olmadığı için ÇALIŞTIRILMADI** — bkz. teslim raporu). `wordpress-site/docs/content-model.md` güncellendi. `build-manifest.js`'in ürettiği `qualifications.manifest.json` "notes" alanı da bu çözümü yansıtacak şekilde güncellendi (artık "reddediyor" değil "artık kabul ediliyor" diyor). **Kalan açık kapı:** PHP/WordPress runtime doğrulaması (gerçek admin kaydı/yayın akışı) hâlâ yapılmadı.

### Bloklayıcı sözleşme açığı #2 — sektör `icon`/`image` için term meta yok — **ÇÖZÜLDÜ (şema düzeyinde, Faz 6A Güvenlik/Şema/Sözleşme Kapanışı)**

*(Aşağıdaki iki paragraf orijinal, ilk tespit anındaki metindir — tarihsel bulgu olarak silinmeden korunmuştur.)*

`sectors.js`'teki her kayıt bir `icon` (ör. `"gear"`) ve çoğu kayıt bir `image` (yerel dosya yolu) taşıyor. Mevcut `mavibelge-core` şeması `mb_sektor` taksonomisi için **hiçbir özel term meta alanı tanımlamıyor** (`register_term_meta()` çağrısı yok, `wordpress-site/docs/content-model.md`'nin Sektör bölümü yalnız `slug`/`name` sayıyor). WordPress'in yerleşik terim `description` alanı `desc`'i karşılayabilir (gerçek bir gap değil — bkz. §3), ama `icon` ve `image` için **hiçbir hedef alan yok**.

**Bu görev şemayı değiştirmedi.** `icon`/`image` manifestte olduğu gibi taşınıyor (uydurulmadı, atılmadı — brief §7'nin "hiçbir alan sessizce atılmasın" kuralı) ama Faz 6B'nin gerçek WordPress'e yazacağı bir hedefleri yok. Çekirdek agentı Faz 6B öncesi karar vermeli: yeni term meta alanı ekle, ya da bu iki alanı bilinçli olarak "henüz aktarılmıyor" say.

**Çözüm kanıtı (Faz 6A Güvenlik/Şema/Sözleşme Kapanışı, bu turda yapıldı):** `class-taxonomies.php::register_sector_term_meta()` eklendi — `_mb_icon_key` (`sanitize_key()` + 50 karakter sınırı) ve `_mb_image_attachment_id` (yalnız gerçek bir `attachment` post'una işaret eden pozitif ID kabul edilir, aksi halde `0` = eşlenmemiş) artık `mb_sektor` için kayıtlı `register_term_meta()` alanları, `show_in_rest=false`, PHP 7.3 uyumlu. **Bu görevde hiçbir terim oluşturulmadı, hiçbir medya içe aktarılmadı** — yalnız hedef şema hazırlandı; sektör verisinin gerçek WordPress'e yazılması Faz 6B'nin konusu olarak kalıyor. **Kalan açık kapı:** PHP/WordPress runtime doğrulaması (gerçek `register_term_meta()` çağrısının çalıştığı, attachment doğrulamasının gerçek medya kütüphanesine karşı davrandığı) hâlâ yapılmadı.

### İncelenip gap OLMADIĞI belirlenen — `certificatePrintFeeExcluded`

Alan adı "Excluded" ("hariç") içerse de kaynak yorumu bunun anlamını netleştiriyor: "Belge basım ücreti (1.500 TL) dahil değildir" — yani sınav ücretine dahil olmayan, **ayrı** bir kalemdir. Bu, mevcut `_mb_certificate_print_fee_kurus` alanının zaten temsil ettiği şeydir (ayrı bir tutar alanı; "dahil/hariç" için ayrı bir boolean gerekmez — alanın kendisi zaten "ayrı ücret" anlamına gelir). 103 kaydın 103'ü de sabit `1500` TL taşıyor. **Kayıpsız, birebir eşleşen bir alan — bloklayıcı değildir.**

## 8. Kaynak verideki hiçbir alan sessizce atılmadı

`sectors.js`, `qualifications.js`, `fees.js`'teki her alan ya doğrudan bir manifest alanına taşındı ya da (icon/image gibi) yukarıda açık bir bulgu olarak kaydedildi. `unmapped_fields` listesi **boştur** — kaynağın hiçbir alanı hedef modelde karşılığı olmadığı için görünmez bırakılmadı.
