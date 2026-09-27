# 2026 Ücret Tarifesi — Kaynak PDF Medya Eşleme Planı

> **Bu belge plandır; hiçbir PDF yüklenmedi, hiçbir `source_attachment_id` yazılmadı.** Kurumsal veri onayı olmadan uygulanmaz.

## 1. İki kaynak PDF (salt okunur; değiştirilmez, yeniden sıkıştırılmaz)

| Kaynak PDF | SHA-256 | Bağlı ücret kaydı | Kullanılan sayfalar |
|---|---|---|---|
| 2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf | `87d1002bdc03edb8078b22c2d85b6e4fe0a62effa823c0b5028edbea7832627e` | 96 | bkz. aşağıdaki satır |
| 2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf | `e91fa707de42fb532230218b06cb33b5ebff1c578172e0111f3f3e9453b33e44` | 7 | bkz. aşağıdaki satır |

- 2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf — SHA-256 `87d1002bdc03edb8078b22c2d85b6e4fe0a62effa823c0b5028edbea7832627e` — 96 kayıt — sayfalar: s.1=32, s.2=21, s.3=30, s.4=13
- 2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf — SHA-256 `e91fa707de42fb532230218b06cb33b5ebff1c578172e0111f3f3e9453b33e44` — 7 kayıt — sayfalar: s.2=7
- Toplam: 103 kayıt = 96 + 7. Manifestteki `source_name` ("2026 Yeni Meslekler Ücret Tarifesi" / "2026 Güzellik Fiyat Listesi") kaydın PDF'ini belirler; eşleme adla değil bu alanla yapılır.
- Güzellik kayıtları: `fee:guzellik-sac-bakim:4:guzellik-uzmani`, `fee:guzellik-sac-bakim:4:epilasyon-uzmani`, `fee:guzellik-sac-bakim:4:kuafor`, `fee:guzellik-sac-bakim:3:cilt-bakim-uygulayicisi`, `fee:guzellik-sac-bakim:3:makyaj-uygulayicisi`, `fee:guzellik-sac-bakim:3:protez-tirnak-uygulayicisi`, `fee:guzellik-sac-bakim:3:kozmetik-urunler-tanitim-ve-uygulama-elemani`
- Yeni Meslekler kayıtları `onay-matrisi.csv` içindeki `kaynak PDF` sütunundan süzülür (96 satır).

## 2. Mevcut sistem 103 kayda toplu ve denetlenebilir attachment eşlemesi yapabiliyor mu? — **HAYIR** (koddan doğrulandı)

| Bulgu | Kanıt |
|---|---|
| İçe aktarma doğrulayıcısı ücret kaydında `source_attachment_id`'nin **0 olmasını zorunlu kılar**; sıfırdan farklı değer kaydı reddeder | `includes/import/class-import-record-validator.php` (ücret doğrulaması: "source_attachment_id bu fazda KESİNLİKLE 0 olmalı") |
| Sektör görselleri için var olan güvenli eşleme deposu (`Image_Map_Store`, digest'li, audit'li, nonce'lu admin ekranı) **yalnız sektör görseline** hizmet eder; PDF eşlemesi yok | `class-import-sector-image-map.php`, `interface-import-image-map-store.php`, `class-import-wp-image-map-store.php` |
| Alan yönetilen alandır (`FEE_FIELDS`); değer manifestten gelir | `class-import-managed-fields.php` |
| Ziyaretçi tarafı PDF bağlantısını yalnız `source_attachment_id > 0` ve tür `attachment` ise gösterir | `public/class-catalog-service.php` (`present_fee`) |
| Elle düzenleme mümkün: `_mb_source_attachment_id` ("Kaynak Dosya (Medya ID)") meta alanı | `includes/class-meta-schema.php` — ancak 103 tek tek düzenleme, denetim/onay/geri alma ve digest korumasından yoksundur ve **yönetilen alanı elle değiştirmek** sonraki dry-run'da `conflict`/geri almada `drift` üretir (sözleşme gereği; bu görevde WordPress'te denenmedi) |

Sonuç: mevcut yöntemle kabul edilebilir bir toplu eşleme **yoktur**. Aşağıdaki öneri, kurumsal onaydan sonra ayrı bir görev olarak istenebilir. **Bu görevde kod yazılmadı.**

## 3. Uygulama önerisi (kod YAZILMADI): "Kaynak PDF Eşleme Deposu"

**Eksik yetenek.** Manifest `source_attachment_id=0` kalırken, yönetici onayıyla `source_name → attachment` eşlemesini güvenle tanımlayıp içe aktarma planına (dry-run) katmak.

**En küçük güvenli çözüm** (sektör görsel eşlemesi kalıbının aynısı; ikinci bir motor/karar mekanizması YOK):
1. `mb_fee_source_pdf_map` adlı tek bir WordPress option'ı: `{ "<source_name>": <attachment_id> }` — yalnız iki bilinen `source_name` anahtarı kabul edilir.
2. Kaydetmeden önce sunucu tarafı doğrulama: attachment var; `post_type=attachment`; MIME `application/pdf`; dosya **SHA-256'sı** bu belgedeki onaylı özetle birebir; aksi hâlde fail-closed sabit hata kodu.
3. Dry-run planlayıcı, eşlemeyi **plan sırasında** `source_attachment_id` yönetilen alanına çözer (manifest DEĞİŞMEZ); çözülen değer karar hash'ine girer; `map_digest` run'a bağlanır ve apply sırasında yeniden doğrulanır (sektör görsel eşlemesindeki `map_changed` kuralı).
4. Eşleme yoksa/geçersizse alan `0` kalır (bugünkü davranış) — hiçbir kayıt bozulmaz.

**Etkilenecek dosyalar (öngörü):** yeni `class-import-source-pdf-map.php`, `interface-import-pdf-map-store.php`, `class-import-wp-pdf-map-store.php`; `class-import-record-validator.php` (yalnız manifestte `0` kuralı korunur; çözümleme yolu ayrı); `class-import-managed-fields.php` + `class-import-write-payload.php` (çözülen kimlik); `class-import-dry-run-planner.php` / `class-import-dry-run-service.php` / `class-import-apply-service.php` (map digest); `admin/class-import-dry-run-page.php` + `admin/assets/import-apply.js` (ekran); `class-import-admin-gates.php`; testler (`tests/suites/…`, `tools/test-*static-contract.js`, runtime betiği).

**Yetki / nonce / audit.** Ekran yalnız `manage_options` **ve** `mb_manage_tariff_period` yetkisine sahip yönetici; her kayıt için WordPress nonce; ortam kapıları (`WP_ENVIRONMENT_TYPE`, HTTPS, apply sabitleri) mevcut kapılarla aynı; her değişiklik audit'e (`import_pdf_map_saved`, eski/yeni özet, kullanıcı kimliği, mutlak yol/sır yok).

**Rollback davranışı.** Eşlemeyi kaldırmak `source_attachment_id`'yi plan sırasında `0`'a çözer → sıradan bir `update` kararı (yönetilen alan) olarak geri alınır; attachment yalnız hiçbir kayıt kullanmıyorsa ve yönetici isterse Medya Kütüphanesi'nden elle silinir (otomatik silme yok — SHA eşleşmeli paylaşımlı olabilir).

**Test planı.** (a) PHP birim: eşleme doğrulaması (yanlış MIME/SHA/olmayan kimlik/bilinmeyen anahtar → sabit kod), boş eşlemede davranış değişmezliği, hash'e girişi; (b) statik sözleşme; (c) gerçek WordPress runtime: yükle → dry-run planı → apply → readback → rollback → yeniden dry-run, A→Z DB/uploads farkı 0; (d) HTTP: yetki/nonce/GET reddi; (e) 103 kayıt için `source_page` ve PDF bağlantısının render kontrolü (tarayıcı).

## 4. Kurum onayından sonra operasyon adımları (mevcut sistemle yapılabilecek kısım)

> Yalnız **staging**; canlıya dokunulmaz. Onay olmadan başlanmaz.

1. Staging tam yedeği (DB + `wp-content`) alınır ve geri yükleme doğrulanır (`admin-import-operations.md` §2).
2. Kaynak PDF'ler **değiştirilmeden** yerel diskten Medya Kütüphanesi'ne yüklenir (Medya → Yeni Ekle). Not: WordPress yükleme sırasında **dosya adını** (Türkçe karakter/boşluk) normalleştirebilir (ör. `2026-YENI-MESLEKLI-UCRET-TARIFESI.pdf`); bayt içeriği değişmez. Yükleme sonrası dosya indirilip SHA-256 hesaplanır ve bu belgedeki özetle **birebir** karşılaştırılır; uyuşmazlıkta attachment silinip süreç durdurulur.
3. Her iki PDF'in attachment kimliği (Medya → dosya → adres çubuğundaki `post=<id>`) yazılı olarak not alınır.
4. **Kimlik bağlama:** mevcut sistemle güvenli toplu yol yoktur (bkz. §2). Seçenekler: (A) yukarıdaki öneri uygulanana kadar bağlamayı **ertelemek** (ücretler PDF bağlantısız yayınlanır; `source_name`/sayfa bilgisi zaten görünür); (B) 103 kaydı tek tek `Kaynak Dosya (Medya ID)` ile düzenlemek — **önerilmez** (denetimsiz, drift üretir). Karar kurumdadır.
5. Kamuya açık PDF kararı: PDF'ler yayınlandığında **tüm sayfalar** herkese açık olur. Güzellik PDF'inin 1. sayfası tanıtım/mevzuat metni içerir; kurum PDF'in olduğu gibi yayınlanmasını açıkça onaylamalıdır.

## 5. Geri alma (operasyon)

- Bağlanmamışsa: yüklenen attachment'ı silmek yeterlidir (hiçbir kayıt kullanmıyor).
- Bağlanmışsa (yalnız B seçeneği): her kaydın `Kaynak Dosya (Medya ID)` alanı `0`'a döndürülür, sonra attachment silinir; aksi hâlde ziyaretçi bağlantısı kırık kalır. Yedekten dönüş esastır.
