# Admin Katalog Aktarımı — Staging İşletim Runbook'u (Faz 6B4; güncel: eklenti 0.5.3, tema 0.6.7)

> **Bu belge staging (ve ancak ayrıca onaylanırsa üretim) için bir kontrol listesidir; kendi başına apply yetkisi DEĞİLDİR.**
> Bu depo sunucuya, FTP/SSH/DirectAdmin'e, DNS'e, SSL'e veya mail sistemine bağlanmaz. Aşağıdaki adımları sistem yöneticisi ve kurum
> yetkilisi, `raporlar/proje-durumu.md` ve `wordpress-site/docs/release-runbook.md` ile birlikte uygular. Sunucuda SSH/WP-CLI **gerekmez**:
> bütün adımlar WordPress yönetim panelinden ve `wp-config.php` dosyasından yapılır.

## 0. Ne yapılır, ne yapılmaz

- Aktarım **admin panelinden** (Araçlar → İçe Aktarım Dry-Run) yapılır; her HTTP isteği **en çok 10 kayıt** yazar. Sekme kapanırsa işlem
  `paused` kalır ve aynı ekrandan **Devam et** ile sürer.
- Kararları yeni bir motor vermez: mevcut dry-run planlayıcı, karar tablosu, hash/TOCTOU/readback, rollback kayıtları ve audit zinciri kullanılır.
- Staging'de gerçek apply yalnız **bu belgedeki sırayla, yedek alındıktan ve sabitler geçici açıldıktan sonra** yapılır. Üretim için ayrı ve açık onay gerekir (§13).
- **Durum notu (Faz 12c):** staging'de `content` aşaması (haber/referans/SSS) HENÜZ UYGULANMADI; bu belge yalnız yerel kod ve prosedürü tanımlar. Eklenti 0.5.2, batch hatasında yeni logo dosyalarını telafi eder (aşağıda §10).
- Görsel eşleme **elle** yapılır; dosya adına/benzerliğe göre tahmin **yoktur**.

## 1. Ön koşullar

| Koşul | Kontrol |
|---|---|
| Eklenti `mavibelge-core` **0.5.3** (`mavibelge-core-0.5.3.zip`, `sha256sum -c` ile doğrulanmış) | Eklentiler ekranında sürüm |
| Tema `mavibelge` **0.6.7** (`mavibelge-theme-0.6.7.zip`; SSS ikon 24x24 + dahili bağlantılar etkin permalink yapısına göre + yeterlilik liste/detay referans tasarımı + tablet başlık: ≤1279px hamburger + online başvuru ekranı ve `?meslek=` ön seçimi + iletişim sayfası referans düzeni) | Görünüm → Temalar |
| **`data/` dizini bir bütün olarak** sunucuya konmuş (`data/content/*.manifest.json` + `data/sources/reference-logos/ref-NN.png`; `MAVIBELGE_IMPORT_MANIFEST_DIR` = `.../data/content`). Logo dosyaları yoksa `content` aşaması yüklemede reddedilir | Sistem kapıları / dry-run |
| Yönetici hesabı: `manage_options` **ve** `mb_manage_tariff_period` | Kullanıcılar → yetki |
| Yönetim paneline **HTTPS** ile erişim (kapı WordPress `is_ssl()` sonucunu kullanır; TLS'i bir vekil/CDN sonlandırıyorsa `wp-config.php`'de standart `$_SERVER['HTTPS'] = 'on'` ayarı gerekir, aksi hâlde kapı `not_https` ile kapalı kalır) | Adres çubuğu; Sistem kapıları bölümü |
| `WP_ENVIRONMENT_TYPE` = `staging` (wp-config.php) | Sistem kapıları bölümü "Ortam türü" satırı |
| Staging alanı parola korumalı ve `noindex` | `release-runbook.md` §3 |
| Kurumsal kararlar (`institution-decisions.md`) katalog için engel değil | — |

## 2. Yedek (ATLANAMAZ)

1. **Veritabanı yedeği**: DirectAdmin → MySQL Yönetimi → yedek/indirme (veya sistem yöneticisinin araçları). Dosya adına tarih/saat yazın.
2. **`wp-content` yedeği**: eklentiler, temalar, `uploads` (DirectAdmin dosya yöneticisi/arşiv).
3. İkisini **farklı iki konuma** kopyalayın (ör. sunucu dışı bir depolama + yerel bilgisayar).
4. **Geri yükleme doğrulaması**: veritabanı yedeğini *ayrı, boş* bir veritabanına aktarın; tablo sayısını ve örnek bir kaydı (`wp_options` içinde `siteurl`) karşılaştırın.
   Dosya yedeğinin arşivini açıp `wp-content/plugins/mavibelge-core` klasörünü kontrol edin. Doğrulanmamış yedekle **başlanmaz**.
5. Rollback (uygulama içi geri alma) **yedeğin yerine geçmez**.

## 3. Eklentiyi güncelleme (0.4.0 / 0.5.0 / 0.5.1 / 0.5.2 → 0.5.3)

1. Eklentiler → Yeni Ekle → Eklenti Yükle → `mavibelge-core-0.5.3.zip` → **Şimdi Kur** → "Geçerli olanı yüklenenle değiştir".
2. Eklenti etkin kalır; **yeniden etkinleştirmeniz gerekmez**. Etkin değilse etkinleştirin (önce eklenti, tema zaten etkin).
3. Araçlar → **Sağlık** ekranı: hata yok. (Run tabloları 0.5.x sürümüne **ilk apply'da** otomatik yükseltilir; önizleme tablo oluşturmaz.)
4. Araçlar → **İçe Aktarım Dry-Run**: sayfa açılır, altta "Katalog Aktarımı (Admin Apply)" bölümü görünür; "Apply/rollback şu an: **KAPALI**".

## 4. Dokuz benzersiz sektör görselini yükleme

Manifestteki on görselli sektör **dokuz** kaynak görseli kullanır (maden ve mermer aynı görseli paylaşır). Kaynak dosyalar depoda
`tanitim-site/assets/images/content/` altındadır (kurumun onayladığı asıl görselleri kullanın):

| Sektör(ler) | Kaynak görsel |
|---|---|
| makine | `real-makine.png` |
| metalurji | `real-haddeci.png` |
| metal | `real-metal-isleme.png` |
| lojistik | `real-liman.png` |
| enerji | `real-elektrik-1.png` |
| cam | `real-cam.png` |
| tekstil | `real-tekstil-iplik.png` |
| insaat | `real-insaat.png` |
| maden **ve** mermer | `real-mermer.png` (tek dosya, tek ekleme) |

Ortam Kütüphanesi → Yeni Ekle ile dokuz dosyayı yükleyin. Yüklemeler **gerçek görsel** (PNG) olmalıdır; başka türde dosya eşlemede reddedilir.

## 5. On sektör eşlemesi

1. Araçlar → İçe Aktarım Dry-Run → **2) Sektör görsel eşleme**.
2. Her sektör satırında **Görsel seç** → Ortam Kütüphanesinden ilgili görseli seçin. `maden` ve `mermer` için aynı görseli seçin.
3. **Eşlemeyi doğrula ve kaydet**. Kayıt yalnız şu koşullarda kabul edilir: on slug'ın **tamamı** var (eksik/fazla yok), her ID gerçek bir görsel
   (attachment, çöpte değil, MIME `image/*`, dosyası okunabilir), aynı görseli birden çok sektör kullanıyorsa bunların kaynak görseli manifestte aynı.
4. Eşleme, sektör manifestinin özetine bağlıdır; manifest değişirse eski eşleme otomatik **geçersiz** sayılır ve yeniden kaydedilmelidir.
5. Eşleme kaydı **apply sabitleri açılmadan önce** yapılır (eşleme içerik yazmaz; import run tabloları gerekmez ve bu adımda kurulmaz). Kayıt ile audit kaydı (özet + değişen sektör adları) **tek işlemdir**: audit yazılamazsa, transaction başlatılamazsa veya commit başarısız olursa eşleme **kaydedilmez** ve ekranda sabit hata kodu görünür (`infrastructure_unavailable`, `transaction_begin_failed`, `option_write_failed`, `audit_failed`, `commit_failed`, `transaction_rollback_failed`); önceki eşleme aynen kalır. Aynı eşlemeyi yeniden kaydetmek değişiklik sayılmaz (audit olayı üretilmez).
6. Sektörler **apply edildikten sonra** eşlemeyi başka bir görselle değiştirmeyin: yazılmış terimin görseli ile yeni eşleme çelişir, sektör `blocked` olur (`sector_image_map_conflict`). Görseli değiştirmek için önce ilgili run'ı geri alın.

## 6. Önizleme ve beklenen sayılar

Aşama seçin → **Önizle (salt okunur)**. Boş bir hedefte beklenen sonuçlar:

| Durum | Sonuç |
|---|---|
| Eşleme **yok**, tam katalog (`all`) | toplam 200 · create 23 · blocked 177 · invalid 0 · conflict 0 · uygulanabilir **hayır** |
| Eşleme **tam**, `sectors` | toplam 14 · create 14 · blocked 0 · invalid 0 · conflict 0 · uygulanabilir **evet** |

`invalid` veya `conflict` görürseniz **durun** (§10). `blocked` yalnız bağımlılığı henüz yazılmamış kayıtlarda beklenir.

## 7. Apply sabitlerini geçici açma (yalnız işlem penceresinde)

Sistem yöneticisi `wp-config.php` içine, "That's all, stop editing!" satırından **önce** ekler:

```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );                 // zaten tanımlıysa tekrar eklenmez
define( 'MAVIBELGE_IMPORT_APPLY_ENABLED', true );
define( 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED', true );
```

Sabitler yalnızca bu dosyadan açılır; ekrandan, URL'den veya seçenekten **açılamaz**. Sayfayı yenileyin: "Apply/rollback şu an: **AÇIK**".
Pencere bitince sabitler **hemen kaldırılır** (§12).

## 8. Beş aşama: sırayla, her biri ayrı ayrı

Sıra sunucu tarafında zorunludur (admin ekranı **ve** WP-CLI aynı tek kaynaktan, `Apply_Plan::PREREQUISITE_STAGE`): **pages → sectors → qualifications → all → content**.
`pages` ilk aşamadır ve önkoşulsuzdur. Sayfaların katalog kayıtlarına veri bağımlılığı yoktur; sıra, tek bir denetlenebilir zincir olsun ve hiçbir aşama atlanmasın diye yine de sunucuda zorlanır (CLI'da `Aşama sırası` hatası, admin'de `prerequisite_not_met`). Bir aşama yalnız önceki aşamanın kayıtları gerçek okuma sonucunda
*unchanged* olunca açılır. Her aşama için aynı disiplin:

1. **Önizle**; sayaçları ve `Plan özeti` (64 hane) değerini inceleyin. Kurum/sorumlu inceleme tutanağına yazın.
2. Onay kutusuna, ekranda gösterilen ifadeyi **birebir** yazın: `UYGULA <aşama> <plan özetinin ilk 12 karakteri>` (büyük/küçük harf ve boşluk aynen; sessiz düzeltme yoktur).
3. **Uygula**. İlerleme çubuğu her istekte 10 kayıt ilerler. Sekmeyi kapatmayın; kapanırsa **Run listesi**'nden **Devam et**.
4. `completed` görülünce aynı aşamayı **yeniden önizleyin**: hepsi `unchanged`, `create 0` olmalı (readback).
5. Audit: Araçlar → Sağlık → audit zinciri sağlam; `debug.log` boş.
6. Sonraki aşamaya geçmeden önce sabitleri **kapatmak** (ve bir sonraki pencerede yeniden açmak) önerilir (staging disiplini).

Beklenen büyüklükler (gerçek manifest): `pages` 32 sayfa (**taslak**) · `sectors` 14 · `qualifications` 83 (+14 unchanged) · `all` 103 ücret (+önceki 97 unchanged) · `content` 6 haber + **15 logolu referans** (gerçek attachment; firma adı doğrulanmamış, nötr "Referans NN") + **6 SSS** = 27 kayıt (hepsi taslak).
`content` aşaması için `haber` ve `duyuru` haber-türü terimleri önceden mevcut olmalıdır (eklenti etkinleştirmede oluşturulur; import onları oluşturmaz). Referans logoları `data/sources/reference-logos/` altındaki dosyalardan (SHA-256 eşleşmesiyle) uploads'a **gerçek attachment** olarak kopyalanır; aynı logo tekrar eklenmez, rollback attachment silmez.

### 8.1. `pages` aşaması: 32 gerçek WordPress `page` kaydı (Faz 12)

- Kaynak: `data/content/pages.manifest.json` (32 kayıt = 3 hub + 21 içerik + 5 form + 3 CPT-verili sayfa). İçerik yalnız `tanitim-site/*.html` **ana içeriğinden** üretilir; başlık/alt bilgi/CTA/betik/tema sahibi dinamik bölüm alınmaz. Kapalı HTML izin listesi: `p, h2–h4, ul, ol, li, a[href], strong, em, br`.
- **Sayfalar her zaman TASLAK (`draft`) oluşturulur.** Apply hiçbir sayfayı yayınlamaz. Sayfa durumu (`post_status`) yönetilen alan DEĞİLDİR: yayınlanmış bir sayfa, import rollback'i için kullanıcı değişikliği (drift) sayılır ve rollback reddedilir (silinmez).
- Ekranın *önizleme* çıktısı bilgilendirici uyarıları (`[UYARI]`) listeler; Faz 12b'den itibaren bloklayıcı kurum kararı yoktur.
- **Yedi sayfa (Faz 12b):** `kvkk`, `gizlilik-politikasi`, `banka-hesap-bilgileri` onaylı canlı kaynaklardan (yeniden yazılmadan), `sinav-takvimi` ve `sonuc-belge-sorgulama` harici hizmete tek CTA'lı yerel bilgilendirme olarak dolu oluşur (bkz. `content-import-contract.md` §10). `referanslar` ve `sss` sayfalarının içeriği boştur: liste `content` aşamasıyla oluşan `mb_referans`/`mb_sss` kayıtlarından çizilir.

### 8.2. Sayfa yayınlama (AYRI işlem, yalnız staging)

Bölüm **5) Sayfa yayınlama**. Sayfa oluşturma ile birleştirilmez; apply/rollback kapılarının hepsi + **yalnız `staging`** ortam şartı (üretimde `publish_staging_only`) uygulanır.

1. **Yayın özetini göster** (salt okunur): 32 satır — başlık, slug, kaynak dosya, durum, yayınlanabilirlik nedeni. Özet: hazır / yayında / bekletilen (kurum kararı; artık 0) / içerik bekleyen / oluşturulmamış.
2. **İçerik bağımlılığı (sunucu tarafında zorunlu):** `referanslar` ve `sss` sayfaları yalnız `content` aşaması tamamlanıp ilgili kayıtlar (15 referans, 6 SSS) gerçek readback ile `unchanged` (referans için geçerli logo dahil) **ve yayında** ise `ready` olur; aksi halde `content_not_ready` (yayınlanmaz). Tipik sıra: `pages` → `sectors` → `qualifications` → `all` → `content` → WordPress'te 6 SSS + 15 referans kaydını gözden geçirip **yayınla** (toplu düzenleme) → yayın özetini yenile → kalan 2 sayfayı yayınla. Bağımlılık yayın anında yeniden doğrulanır. Diğer 30 sayfa içerik aşamasından bağımsız yayınlanabilir.
3. Onay ifadesi: `YAYINLA <yayın planı özetinin ilk 12 karakteri>` (birebir). Onay, önizlemedeki özet ve `expected_remaining` sayacı sunucuda yeniden doğrulanır: özet değişmişse `confirmation_mismatch`, çift tıklama/bayat istek `stale_request`, çözülmemiş bir run varsa `unresolved_run_exists`, kilit `locked`.
4. Her istek en çok **10** sayfa yayınlar (transaction + katı readback + audit `import_pages_published`); tarayıcı kalan sayıyla sürdürür. Tekrar onay idempotenttir (`completed`, 0 yayın).

Rollback sonrası yeniden apply: oluşturulan sayfalar çöpe alınır (kalıcı silme yok), aynı manifest yeniden 32 taslak olarak uygulanır. Yayın sonrası import rollback'i reddedilir (drift); yayını geri almak için sayfa el ile taslağa alınır.

## 9. Kesinti ve çift tıklama davranışı

- İstek yarıda kesilirse (sekme kapandı, ağ koptu): o batch ya tamamen yazılmıştır ya hiç yazılmamıştır; run `paused` kalır → **Devam et**.
- Aynı düğmeye iki kez basmak veya eski sayfadan devam etmek güvenlidir: `stale_request` döner, ikinci yazma olmaz.
- Başka bir işlem çalışıyorsa `locked` döner; 30 sn bekleyip tekrar deneyin.
- Yalnız **bir** çözülmemiş (ready/paused/rollback_*) run olabilir; yenisi başlamaz.

## 10. Hata halinde durma kuralları

**Şu durumlardan herhangi birinde DURUN**: yeni run başlatmayın, aşama atlamayın, sabitleri kapatın, hata kodunu ve run kimliğini not edin.

| Kod / durum | Anlamı | Yapılacak |
|---|---|---|
| `confirmation_mismatch` | Plan onaydan sonra değişti | Önizlemeyi yenileyin, yeniden inceleyin |
| `confirmation_phrase_mismatch` | İfade birebir değil | İfadeyi aynen yazın |
| `plan_not_applicable` | conflict/blocked/invalid var | Önizleme kayıt listesini inceleyin; kurum kararı gerekebilir |
| `prerequisite_not_met` | Önceki aşama tamamlanmadı | Önceki aşamayı bitirin |
| `unresolved_run_exists` | Bekleyen run var | Önce onu tamamlayın veya geri alın |
| `manifest_changed` / `map_changed` | Çalışma sırasında manifest/eşleme değişti | Run durdurulur; yazılanları geri alın, nedeni araştırın |
| `toctou_drift` | Hedef planlamadan sonra değişti (kullanıcı/başka süreç) | O batch yazılmadı; durumu inceleyin, gerekirse geri alın |
| `finalization_failed`, `rollback_required`, `rollback_failed`, `failed` | Kalıcı durum hatası | Run listesinden durumu okuyun; yazılmış kayıt varsa **geri alma önizle**; çözülmezse yedeğe dönün |
| `transaction_rollback_failed` | DB geri alma başarısız; yeni logo dosyaları **bilerek silinmedi** (fail-closed) | Yeni run başlatmayın; uploads altında `mavibelge-referans-logo-*` dosyalarını ve DB durumunu sistem yöneticisiyle inceleyin; gerekirse yedeğe dönün |
| `side_effect_cleanup_failed` / `side_effect_attachment_still_present` / `side_effect_file_referenced` / `side_effect_path_rejected` / `side_effect_scope_failed` | Batch DB'de geri alındı ama yeni logo dosyası temizliği doğrulanamadı/güvenlik sınırı nedeniyle yapılmadı (bkz. `content-import-contract.md` §10.2a) | Kalan `mavibelge-referans-logo-*` dosyasını, `_mb_import_logo_sha256` meta'sı taşıyan bir attachment'a bağlı DEĞİLSE elle silin; sonra aşamayı yeniden önizleyin |
| `drift_detected` (geri alma) | Kullanıcı değişikliği bulundu | Hiçbir kayıt geri alınmadı; değişikliği inceleyin |

Belirsizlik varsa **yedeğe dönüş** (§2) esastır.

## 11. Rollback (geri alma)

1. Run listesinde ilgili run için **Geri alma önizle**: bekleyen kayıt sayısı, **engeller** ve rollback özeti görünür. Engel varsa hiçbir şey geri alınmaz.
2. Ifadeyi birebir yazın: `GERI AL <run kimliği ilk 8> <rollback özeti ilk 12>` (apply ifadesi geçerli DEĞİLDİR) → **Geri al**.
3. İstekler ters sırada (ücret → yeterlilik → sektör), her biri en çok 10 kayıt; **kullanıcı değişikliği** (drift) bulunursa kalan **hiçbir kayıt** geri alınmaz.
4. Geri alınan yazılar çöp kutusuna gider (kalıcı silme yok); sektör terimleri yalnız bağımlısı kalmadıysa silinir.
5. **Logo attachment'ları (Faz 12b/12c):** rollback, commit edilmiş logo attachment'larını/dosyalarını **silmez** (paylaşılıyor olabilir); reapply bunları SHA-256 eşleşmesiyle yeniden kullanır. Yalnız commit edilmemiş/geri alınmış batch'in yeni dosyaları otomatik temizlenir.
6. Sonra aynı aşamayı önizleyin: yeniden `create` görünür (yeniden apply mümkün). Rollback provası, yedeğe ek olarak en az bir kontrollü run üzerinde yapılmalıdır.

## 12. Sabitleri tekrar kapatma

İşlem penceresi bitince `wp-config.php`'den şu satırları **silin** (veya `false` yapın): `MAVIBELGE_IMPORT_APPLY_ENABLED`, `MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED`.
Sayfayı yenileyin: "Apply/rollback şu an: **KAPALI**". Sabitler açık unutulursa risk büyüktür; pencere sonunda ikinci bir kişi kontrol etsin.

## 13. Sağlık, audit ve debug-log kontrolü

- Araçlar → **Sağlık**: veritabanı, audit tablosu, önbellek; hata yok.
- Audit: her run için `import_run_started`, `import_batch_committed`(×batch), `import_run_completed`; eşleme değişiklikleri `import_image_map_changed`. Alan içeriği/dosya yolu audit'te yoktur.
- `wp-content/debug.log`: import süresince **boş** olmalı (uyarı/fatal yok).
- 41 sayfa, arama/filtre, mobil görünüm, erişilebilirlik ve konsol kontrolleri (`release-runbook.md` §6) staging'de gerçek tarayıcıda yapılır.

## 14. Üretim için ayrı açık kullanıcı onayı

Bu mekanizma üretimde **varsayılan kapalıdır** ve ancak ayrı, açık bir kullanıcı onayıyla kullanılabilir. Ek koşullar: üretim için ayrı yedek + geri dönüş provası,
`WP_ENVIRONMENT_TYPE` = `production`, `MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED` = `true` ve `MAVIBELGE_IMPORT_PRODUCTION_HOST` = üretim host'unun **birebir**
(büyük/küçük harf, port ve sondaki nokta dahil) yazılışı. Bu belgeyi uygulamak üretim onayı DEĞİLDİR.

## 15. Bu turda doğrulanmayanlar (dürüst liste)

- Gerçek staging sunucusunda çalıştırma **yapılmadı**; DirectAdmin ortamı, gerçek PHP/MySQL sürümleri ve tarayıcı davranışı doğrulanmadı.
- Ortam Kütüphanesi modalı ve JavaScript orkestrasyonu gerçek bir tarayıcıda **denenmedi** (yerel Docker'da tarayıcı yoktu); sunucu tarafı HTTP ve saf/statik testlerle sınandı.
- Faz 12/12b: `pages` aşaması, yayınlama, SSS/referans (gerçek attachment) içe aktarımı ve içerik yayın kapısı yalnız yerel izole fixture DB'de (gerçek WordPress 6.9.9 + PHP 7.3.33) sınandı; gerçek staging'de **çalıştırılmadı**. Yayınlama admin ekranı JavaScript'i gerçek tarayıcıda denenmedi (sunucu tarafı HTTP testleri ve statik sözleşme testleri var).
- Gerçek katalog/haber manifestleri hiçbir gerçek staging/canlı veritabanına **apply edilmedi** (yerel `pages-render.sh` izole fixture DB'sinde gerçek 15 logo + 6 SSS içerik aşaması çalıştırıldı); yalnız salt okunur dry-run ve sahte fixture manifestleriyle çok istekli apply/rollback sınandı.
