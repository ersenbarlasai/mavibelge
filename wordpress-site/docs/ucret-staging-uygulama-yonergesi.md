# Ücret Yayını — Staging Uygulama ve Geri Dönüş Yönergesi (eklenti 0.5.5 / tema 0.6.10 / ücret veri paketi)

> **Bu belge bir kontrol listesidir; kendi başına uygulama yetkisi değildir.** Bu depo sunucuya bağlanmaz. Adımları **sistem yöneticisi (SY)**
> (dosya, `wp-config.php`, yedek) ve **WordPress yöneticisi (YÖN)** (yönetim paneli) birlikte uygular; bir **ikinci kişi (KONTROL)** açma/kapama
> adımlarını doğrular. Üretim (`mavibelge.com.tr`) bu belgenin kapsamı dışındadır (§11).
>
> Ayrıntılı kapı/hata kodu açıklamaları: [`admin-import-operations.md`](admin-import-operations.md). Günlük ücret yönetimi:
> [`ucret-yonetimi-kullanim.md`](ucret-yonetimi-kullanim.md).

## Güncel durum (20 Ekim 2026)

- **Karar dosyası:** `fee-publication.manifest.json` → 103 ücret, 145 seçenek; **103 `publish` / 0 `hold`**. Altı kaydın (iki Mobil Vinç tarifesi,
  Metal Levha ×2, Isı Yalıtımcısı, Kazıcı Yükleyici/Beko Loder) kullanıcı onayı kayıt SHA-256 özetlerine bağlıdır
  ([`raporlar/ucret-alti-kayit-onayi-20261020.md`](../../raporlar/ucret-alti-kayit-onayi-20261020.md)). İki Mobil Vinç tarifesi ayrı kalır;
  Beko Loder kodsuz ve bağlantısızdır.
- **Geçerli veri paketi:** `mavibelge-ucret-verisi-20261020-7b720433.zip`, SHA-256
  `a7a4173bf00aef24e20608b11bf990587efb405a03d5948ee049b4073753fcae`. Önceki `mavibelge-ucret-verisi-20261019-c29b3e89.zip` (97/6 kararı)
  **kullanılmaz**; yalnız tarihsel olarak korunur.
- **Kullanıcının bildirdiği yayın sonucu:** kullanıcı yeni veri paketini yükledi ve ücretleri **yönetim panelinden elle** aktif yaptı. Kullanıcının
  ekran görüntüsündeki yayın özeti: 103 ücret, 145 fiyat seçeneği; hazır 0, yayında 103, araçla geri alınabilir 0; bekletilen 0, sapmış 0,
  görünmez 0, oluşturulmamış 0, beklenmeyen 0; bağlantısız ücret 19, hepsi yayında. Kaynak yalnız bu ekran görüntüsüdür. Ortamdaki eklenti/tema
  sürümü ve sayılar bu depodan sunucuya erişilerek **doğrulanmadı**.
- **Elle etkinleştirmede "araçla geri alınabilir 0" beklenen değerdir.** Bu sayı yalnız §7.2 aracının yayın işareti (`_mb_fee_publication_marker`)
  yazdığı kayıtları sayar. Elle etkinleştirilen kayıtlar §9.1 ile geri alınamaz; geri dönüş §9.2 (kayıt bazlı) veya §9.3 (yedekten) ile yapılır.
- **Kullanıcının düzenlediği ücretler yeniden aktarımla ezilmez.** Bu yayından sonra `all` aşaması **uygulanmaz** (§7.1). Önizleme panelden
  değişmiş kaydı `conflict` olarak gösterir; araç onu yazmaz. `conflict` görülürse §5.3'teki gibi durulur.
- Aşağıdaki §5.3 yerel değerleri karşılaştırma içindir; gerçek ortamın sayıları her seferinde önizlemede **yeniden hesaplanır**.

## 0. Temel kurallar

- Sayılar **sabit kabul edilmez**. Yerel testte ölçülen değerler (§5.3) yalnız karşılaştırma içindir; staging'in gerçek kayıtları önizlemede yeniden sayılır.
- Staging'de 101 taslak ücretin bulunduğu **varsayılmaz**. Önizleme her ücreti tek tek arar; bulunamayan kayıt `oluşturulmamış` görünür.
- Önizlemeler **salt okunurdur** ve izin sabitleri kapalıyken de çalışır. Yazma yapan her adım (aktarım, yayın, geri alma) yalnız §6'daki sabitler açıkken ve birebir onay ifadesiyle çalışır.
- Herhangi bir hata kodu veya §5.4'teki bir durma koşulu görülürse **durulur**: sabitler kapatılır, ekran görüntüsü ve hata kodu kaydedilir.

## 1. Dağıtılacak dosyalar ve yerel doğrulama

Hepsi `wordpress-site/dist-packages/` altındadır. Yüklemeden **önce** yerelde doğrulayın:

```bash
cd wordpress-site/dist-packages
sha256sum -c checksums.sha256                                   # tema + eklenti
sha256sum -c mavibelge-ucret-verisi-20261020-7b720433.zip.sha256  # ücret veri paketi
```

| Dosya | Nereye | Yöntem |
|---|---|---|
| `mavibelge-core-0.5.5.zip` | WordPress eklentisi | Eklentiler → Yeni Ekle → Eklenti Yükle → "Geçerli olanı yüklenenle değiştir" |
| `mavibelge-theme-0.6.10.zip` | WordPress teması | Görünüm → Temalar → Yeni Ekle → Tema Yükle → "Geçerli olanı yüklenenle değiştir" |
| `mavibelge-ucret-verisi-20261020-7b720433.zip` | **Web kökü dışında** bir dizin (ör. `/home/<hesap>/`) | DirectAdmin Dosya Yöneticisi → yükle → "Extract" |

Ücret veri paketinin içeriği **yalnız** şudur (kaynak, rapor, şema, PDF, eşleme dosyası yoktur):

```
mavibelge-data/.htaccess                              # web erişimini kapatır (dizin yanlışlıkla web kökü altına konursa)
mavibelge-data/index.php                              # boş
mavibelge-data/content/index.php                      # boş
mavibelge-data/content/sectors.manifest.json          # çalışma anında okunur
mavibelge-data/content/qualifications.manifest.json   # çalışma anında okunur
mavibelge-data/content/fees.manifest.json             # çalışma anında okunur
mavibelge-data/content/fee-publication.manifest.json  # çalışma anında okunur (kayıt bazlı yayın kararı)
```

Dosya başına özetler `mavibelge-ucret-verisi-20261020-7b720433-icerik.sha256` ve `...-manifest.json` dosyalarındadır. SSH yoksa sunucuda
`sha256sum` çalıştırılamaz; bunun yerine §5'teki önizleme bütünlüğü doğrular: karar dosyasının her satırı ücret kaydının kanonik özetine bağlıdır,
tek bir bayt farkı satırı "bayat" (bekletilir) veya belgeyi geçersiz yapar.

Bu dört dosya dışında `data/` dizinindeki hiçbir dosya **ücret yayını için** gerekmez. (`pages`/`content` aşamaları başka dosyalar ister;
bu iş kapsamında o aşamalar çalıştırılmaz.)

## 2. Kurulumdan ÖNCE salt okunur durum kaydı (staging)

YÖN, aşağıdakileri ekran görüntüsüyle kaydeder (değerleri tahmin etmeyin, okuyun):

1. **Ücret Kayıtları** listesi: "Tümü (N) | Yayında (M) | Taslak (K)" sayıları.
2. **Sınav Ücretleri** sayfası: "Toplam N ücret kaydından" satırı.
3. Eklentiler ekranında `mavibelge-core` sürümü; Görünüm → Temalar'da `mavibelge` sürümü (staging'in 0.6.8 olduğu bildiriliyor; doğrulayın).
4. Araçlar → İçe Aktarım Dry-Run → **1) Sistem kapıları**: "Ortam türü" ve "Apply/rollback şu an" (**KAPALI** olmalı).

SY, `wp-config.php`'de şu sabitlerin **var olup olmadığını** ve değerini kaydeder (değeri rapora yazmayın, yalnız var/yok ve dizin adı):
`WP_ENVIRONMENT_TYPE`, `MAVIBELGE_IMPORT_MANIFEST_DIR`, `MAVIBELGE_IMPORT_APPLY_ENABLED`, `MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED`,
`MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED`, `MAVIBELGE_IMPORT_PRODUCTION_HOST`. Staging'de son ikisi **tanımlı olmamalıdır**; apply sabitleri açık
bulunursa önce kapatılır ve nedeni araştırılır.

## 3. Yedek ve geri yükleme provası (ATLANAMAZ)

1. Veritabanı yedeği, `wp-content` yedeği, `wp-config.php` kopyası ve (varsa) mevcut veri dizininin kopyası alınır; iki farklı konuma kopyalanır.
2. Veritabanı yedeği **ayrı, boş** bir veritabanına geri yüklenip tablo sayısı ve örnek bir kayıt karşılaştırılır (`admin-import-operations.md` §2).
3. Geri yükleme doğrulanmadan §4'e geçilmez. Uygulama içi geri alma (§9.1) yedeğin yerine geçmez.

## 4. Kurulum (sıra önemlidir)

### 4.1. Veri paketi (SY)

1. `mavibelge-ucret-verisi-20261020-7b720433.zip` dosyasını **web kökü dışında** bir dizine açın; sonuç: `/home/<hesap>/mavibelge-data/content/...`.
2. `wp-config.php`'de, "That's all, stop editing!" satırından **önce**:

   ```php
   define( 'MAVIBELGE_IMPORT_MANIFEST_DIR', '/home/<hesap>/mavibelge-data/content' );
   ```

   Bu sabit **izin değildir**, yalnız okuma konumudur. Kalıcı kalabilir.
3. Sabit zaten başka bir dizini gösteriyorsa eski dizini **silmeyin**; yeni dizini ayrı açın ve sabiti yeni dizine çevirin. Eski değeri not edin (geri dönüş §9.3).
4. Dizin zorunlu olarak web kökü altına konursa: tarayıcıda `https://<staging>/<yol>/mavibelge-data/content/fees.manifest.json` adresi **403**
   vermelidir. 200 dönüyorsa `.htaccess` devrede değildir; dosyayı web kökü dışına taşıyın.
5. PHP `open_basedir` dizine erişime izin vermezse önizleme "manifest dizini bulunamadı veya erişilemiyor" der ve **hiçbir şey yazmaz**.

### 4.2. Eklenti, sonra tema (YÖN)

1. `mavibelge-core-0.5.5.zip` → yükle → "Geçerli olanı yüklenenle değiştir". Eklenti etkin kalmalıdır.
2. `mavibelge-theme-0.6.10.zip` → yükle → "Geçerli olanı yüklenenle değiştir".
3. Araçlar → **Mavi Belge Sağlık**: hata yok. Eklentiler/Temalar ekranında sürümler 0.5.5 / 0.6.10.

## 5. Gerçek ortam önizlemesi (salt okunur; sabitler KAPALI)

### 5.1. Aktarım önizlemesi

Araçlar → İçe Aktarım Dry-Run → **3) Aşama önizleme** → `all` → **Önizle**. Ücret satırlarındaki `create` / `unchanged` / `update` /
`conflict` / `blocked` / `invalid` sayılarını kaydedin.

### 5.2. Ücret yayın özeti

**6) Ücret yayını** → **Ücret yayın özetini göster**. Ekranda, gerçek kayıtlardan hesaplanan şu satırlar çıkar; hepsini kaydedin:

- Toplam / hazır / yayında (araçla geri alınabilir) / bekletilen / sapmış / görünmez / oluşturulmamış / beklenmeyen.
- "Yayına uygun N kayıttan hazır/yayında olan: X; Y kayıt şu nedenle değil: …" (fark dökümü; geçerli karar dosyasıyla N = 103).
- "Sapmış kayıtların aktarım kararı: update … / conflict … / blocked …" (varsa).
- "UYARI: bekletilen N kayıt şu an yayında/aktif" (varsa).
- "Sitedeki ücret kaydı: T (ziyaretçiye görünen V); manifest dışı O (görünen OV)" ve manifest dışı kayıtların tablosu.
- Ücret yayın planı özeti (64 hane) ve satır tablosu (her ücretin durumu ve nedeni).

Dosya eksik veya bozuksa özet yerine hata çıkar ve **hiçbir kayıt değiştirilmez**:

| Kod | Anlamı | Yapılacak |
|---|---|---|
| `catalog_manifests_unavailable` | `sectors`/`qualifications`/`fees.manifest.json` okunamadı (dosya adları listelenir) | §4.1'i kontrol edin |
| `decisions_unavailable` | `fee-publication.manifest.json` okunamadı | §4.1'i kontrol edin |
| `decisions_invalid` | Karar dosyası şeması geçersiz veya ücret manifestiyle eşleşmiyor (karışık sürüm) | Paketi bütün olarak yeniden açın |

### 5.3. Sonucu yorumlama

**Yerel karşılaştırma değeri (beklenti DEĞİL):** canlıya benzer başlangıçla (103 taslak, 2 QA kaydı elle aktif) yerel fixture testinde ölçülen değerler.
103 publish / 0 hold kararıyla (Faz 15 ek rapor §1.2 varsayım testi): toplam 103, hazır 101, yayında 2, bekletilen 0; etkinleştirme sonrası
103/103 satır ve 145/145 seçenek. Tarihsel 97/6 kararıyla ölçülen değer: hazır 95, yayında 2, bekletilen 6. Gerçek ortamın sayıları önizlemede
yeniden hesaplanır; bu değerler oraya taşınmaz.

**Elle etkinleştirilmiş ortam (kullanıcı ekran görüntüsü, 20 Ekim 2026):** hazır 0, yayında 103, araçla geri alınabilir 0, bekletilen/sapmış/
görünmez/oluşturulmamış/beklenmeyen 0. Bu durum beklenendir: kayıtlar §7.2 aracıyla değil panelden yayınlandığı için yayın işareti yoktur.

Staging'in sonucu farklıysa nedeni ekrandaki satırdan okunur:

| Görülen | Olası neden | Yapılacak |
|---|---|---|
| `oluşturulmamış` > 0 | Kayıt staging'de yok (hiç aktarılmamış veya silinmiş) | §7.1: önce `all` aşaması (taslak oluşturur), sonra özeti yenileyin |
| `sapmış`, aktarım kararı `update` | Staging'deki kayıt eski bir manifestten yazılmış | §7.1: `all` aşaması (`conflict` yoksa) kayıtları günceller; özeti yenileyin |
| `sapmış`, aktarım kararı `conflict` | Kayıt panelden elle değiştirilmiş | **Durun.** Araç ezmez; elle değişikliğin korunup korunmayacağı kurum kararıdır |
| `sapmış`, aktarım kararı `blocked`/`invalid` | Sektör/yeterlilik bağımlılığı eksik | **Durun.** `all` önizlemesinin bağımlılık listesini inceleyin |
| `görünmez` > 0 | Not sütunundaki engel (aktif dönem, geçerlilik tarihi, fiyat listesi, sektör) | **Durun.** Aktif tarife dönemini değiştirmek diğer ücretleri gizler; kurum kararı |
| `beklenmeyen` > 0 | Bekleyen/özel/zamanlanmış veya yarım durumlu kayıt | **Durun.** Kaydı panelden inceleyin |
| "bekletilen … şu an yayında" | Bekletilen kayıt daha önce elle yayınlanmış | Araç dokunmaz; kurum kararıyla elle ele alın |
| manifest dışı görünen > 0 | Listede kaynakta olmayan ek ücret satırı var (referansla eşitliği bozar) | Kurum kararı: kaydı elle taslağa almak veya bırakmak |
| yayında > 2 veya < 2 | Daha önce farklı sayıda kayıt elle aktifleştirilmiş | Satır tablosundan hangileri olduğunu kaydedin; araç bunları yeniden yazmaz |
| yayında 103, araçla geri alınabilir 0 | Ücretler panelden elle yayınlanmış | Beklenen. §7 uygulanmaz; geri dönüş yalnız §9.2/§9.3 |

### 5.4. Durma koşulları

`hazır` dışındaki herhangi bir "fark" satırı açıklanmadan, `conflict`/`blocked`/`invalid`/`görünmez`/`beklenmeyen` sıfırdan büyükken veya bir hata
kodu varken **§6'ya geçilmez**.

## 6. Geçici izinler (tam adlar, açma/kapama koşulları)

### 6.1. Açılacak sabitler (staging)

SY, `wp-config.php`'nin o anki kopyasını alır ve "That's all, stop editing!" satırından **önce** yalnız şu iki satırı ekler:

```php
define( 'MAVIBELGE_IMPORT_APPLY_ENABLED', true );
define( 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED', true );
```

- Değer **yalnız** PHP `true` olmalıdır; `'1'`, `1`, `'true'` kabul edilmez.
- `WP_ENVIRONMENT_TYPE` staging'de **zaten** `'staging'` olmalıdır (§2'de okundu). Tanımlı değilse SY yalnız staging için
  `define( 'WP_ENVIRONMENT_TYPE', 'staging' );` ekler. `production` yazılmaz.
- Staging'de `MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED` ve `MAVIBELGE_IMPORT_PRODUCTION_HOST` **tanımlanmaz**.
- Bu sabitler ekrandan, URL'den veya veritabanı seçeneğinden açılamaz.

Ek ön koşullar (sabit değil):

- İşlemi yapan hesapta `manage_options` **ve** `mb_manage_tariff_period` yetkisi olmalıdır (yönetici veya site yöneticisi rolü).
- Yönetim paneline **HTTPS** ile girilmelidir. TLS bir vekil sunucuda sonlanıyorsa `wp-config.php`'de standart `$_SERVER['HTTPS'] = 'on'`
  ayarı gerekir; aksi hâlde kapı `not_https` ile kapalı kalır.

### 6.2. Açma koşulları (hepsi sağlanmalı)

1. §3 yedek + geri yükleme provası tamam.
2. §1 paket özetleri doğrulandı; §4 kurulumu tamam; Sağlık ekranı temiz.
3. §5 önizlemesi kaydedildi; §5.4 durma koşulu yok.
4. SY, YÖN ve KONTROL aynı anda hazır; bakım penceresi belirlenmiş (öneri: en çok 60 dakika).

**Açıldığını doğrulama (KONTROL):** sayfayı yenileyin → **1) Sistem kapıları**: "Ortam türü: staging", "Apply/rollback şu an: **AÇIK**", hiç
"Engel" satırı yok.

### 6.3. Kapanma koşulları (herhangi biri oluşunca HEMEN)

- İşlem tamamlandı (özet: `hazır 0`), **veya**
- herhangi bir hata kodu döndü, **veya**
- bir durma koşulu görüldü, **veya**
- pencere süresi doldu ya da işlem yarıda bırakıldı.

**Kapatma (SY):** §6.1'de eklenen iki satır (ve yalnız bu iş için eklendiyse `WP_ENVIRONMENT_TYPE` satırı) **silinir**. `false` yapmak da kapatır,
ama silmek tercih edilir.

**Kapandığını doğrulama (KONTROL):** sayfayı yenileyin → "Apply/rollback şu an: **KAPALI**" ve "Engel: MAVIBELGE_IMPORT_APPLY_ENABLED sabiti true
değil (kapalı)." satırı görünür. `wp-config.php` açma öncesi kopyasıyla karşılaştırılır: kalıcı olarak yalnız `MAVIBELGE_IMPORT_MANIFEST_DIR`
satırı fark olarak kalabilir.

## 7. Uygulama (sabitler AÇIK)

### 7.1. Gerekirse önce `all` aşaması

Yalnız §5.3 `oluşturulmamış` veya `update` gösterdiyse: **3) Aşama önizleme** → `all` → önizle; `conflict`/`blocked`/`invalid` 0 olmalı.
Onay ifadesi `UYGULA all <özet ilk 12>` birebir yazılır → uygula (her istek en çok 10 kayıt). Bu adım ücretleri **taslak** yazar, yayınlamaz.
Bitince **6) Ücret yayını** özetini yeniden gösterin ve §5.3'e göre tekrar değerlendirin.

### 7.2. Ücret yayını

1. **Ücret yayın özetini göster** (yeniden). Plan özetinin ilk 12 hanesini ve `hazır` sayısını kaydedin.
2. "Yayın onay ifadesi" kutusuna ekranda gösterilen `UCRET YAYINLA <özet ilk 12>` ifadesini **birebir** yazın → **Hazır ücretleri yayınla**.
3. Araç istek başına en çok 10 ücret işler ve kendiliğinden devam eder; istek sayısı `⌈hazır / 10⌉` olmalıdır. Her istekte "İşlenen, kalan"
   görünür; son durum `completed`.
4. Bir hata kodu görülürse durun (§6.3) ve kodu kaydedin: `confirmation_mismatch` (özet değişti; özeti yenileyin), `stale_request`
   (eski/çift istek), `locked` (başka işlem), `toctou_drift` (kayıt arada değişti; batch yazılmadı), `not_publicly_valid`, `write_failed`,
   `readback_mismatch`, `audit_failed`, `commit_failed` (batch geri alındı), `transaction_rollback_failed` (**yedeğe dönüş değerlendirilir**).
   Batch'ler tek transaction'dır: bir batch ya tamamen yazılır ya hiç yazılmaz.

Sonra §6.3'e göre sabitleri **kapatın**.

## 8. İşlem sonrası kontrol (sabitler KAPALI)

| Kontrol | Beklenen |
|---|---|
| Ücret yayın özeti | `hazır 0`; `yayında` = önceki yayında + etkinleştirilen; "araçla geri alınabilir" = araçla etkinleştirilen (elle yayınlananlarda 0); bekletilen sayısı değişmedi |
| Sınav Ücretleri sayfası | "Toplam N ücret kaydından": N = özetteki ziyaretçiye görünen sayı |
| Örnek satırlar | Güzellik Uzmanı: "Sınav ücreti (A1, A2, A3, A4)" 17.000,00 TL; çok seçenekli bir kayıtta her seçenek etiketi + tutarı; her satırda "Fiyatlara %20 KDV dahildir. 1.500 TL MYK belge basım ücreti fiyatlara dahil değildir." |
| Bağlantısız kayıt | MYK kodu "Belirtilmemiştir", detay bağlantısı yok |
| Bağlı kaydın yeterlilik detayı | Ücret ve aynı açıklama görünür |
| `all` önizlemesi | Ücretler `unchanged`; yeni `conflict` yok |
| Audit | `import_fees_activated` olay sayısı = istek sayısı |
| `wp-content/debug.log` | İşlem süresince yeni satır yok |
| Sistem kapıları | "Apply/rollback şu an: KAPALI" |
| Mobil | 375 px'te Sınav Ücretleri kartları taşmadan görünür |

## 9. Geri dönüş

### 9.1. Araçla geri alma (yalnız bu aracın yayınladıkları)

Sabitler §6'daki **aynı koşullarla** yeniden açılır. **6) Ücret yayını** → özeti göster → "Geri alma onay ifadesi" kutusuna
`UCRET YAYINDAN KALDIR <özet ilk 12>` birebir → **Bu aracın yayınladıklarını taslağa al**. Yalnız işareti bu aracın yazdığı değerle eşleşen ve
içeriği değişmemiş kayıtlar `draft/draft` olur (audit `import_fees_deactivated`). Önceden elle aktifleştirilmiş (QA) ve panelden değiştirilmiş
kayıtlara dokunulmaz (elle yayınlanan kayıtlarda bu araç 0 kayıt geri alır). Sonra sabitler kapatılır (§6.3) ve §8 kontrolleri tersinden yapılır (görünen sayı önceki değere döner).

### 9.2. Kayıt bazlı

Ücret Kayıtları → kayıt → **Kayıt Durumu = Taslak** ve WordPress durumu **Taslak** → Güncelle. Sabit gerekmez.

### 9.3. Tam geri dönüş (yedekten)

1. Veritabanını §3 yedeğinden geri yükleyin.
2. Eklenti ve temayı yedekteki önceki sürümlere döndürün (staging'in kurulum öncesi sürümleri §2'de kaydedildi). Veritabanı geri yüklenmeden
   yalnız eklentiyi 0.5.4'e döndürmeyin: 0.5.4, 0.5.5'in yayınladığı ücretleri aktarım önizlemesinde `conflict` olarak görür.
3. `MAVIBELGE_IMPORT_MANIFEST_DIR` sabitini eski değerine döndürün veya (önceden yoksa) silin. Yeni `mavibelge-data` dizini ancak geri dönüş
   doğrulandıktan sonra silinebilir.
4. §2'deki kayıtla karşılaştırın: ücret sayıları, Sınav Ücretleri toplamı, sürümler, Sistem kapıları KAPALI.

## 10. Bekletilen 6 kayıt — karar verildi (20 Ekim 2026)

Karar verildi: karar dosyası 103 publish / 0 hold, yeni paket `mavibelge-ucret-verisi-20261020-7b720433.zip` (bkz. Güncel durum).
Aşağıdaki paragraf, ileride bir kaydın kararı değişirse izlenecek yoldur:

Karar yalnız `tools/fees/build-approval-matrix.js` içindeki kurum kararı kaydıyla verilir; dosya yeniden üretilir ve **yeni adlı** bir veri paketi
çıkar (`node tools/package/build-fee-data-package.js --write`; içerik değişince ad da değişir, eski paket ezilmez). Yeni paket §4.1'e göre ayrı
dizine açılır, sabit yeni dizine çevrilir, §5–§8 tekrarlanır. Ücret kayıtlarının içeriği değişmediği için yeniden aktarım gerekmez; özet bu 6
kaydı `hazır` gösterir. Kararsız elle yayın da mümkündür (kayıt bazlı), ama özet bunları "bekletilen ama yayında" diye uyarır.

## 11. Üretim (ayrı ve açık onay olmadan YAPILMAZ)

Üretimde ayrıca: ayrı yedek ve geri dönüş provası, `WP_ENVIRONMENT_TYPE` = `'production'`, ve §6.1'deki iki sabite ek olarak

```php
define( 'MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED', true );
define( 'MAVIBELGE_IMPORT_PRODUCTION_HOST', '<üretim host adı, tarayıcı adres çubuğundakiyle birebir>' );
```

Host, isteğin `Host` başlığıyla **birebir** eşleşmelidir (`www.` öneki, büyük/küçük harf ve port dahil); eşleşmezse Sistem kapıları
"MAVIBELGE_IMPORT_PRODUCTION_HOST … birebir eşleşmiyor" der. Canlıdaki kayıtlar da §5 önizlemesiyle **ayrıca** sayılır; staging sonucu canlıya
taşınmaz. Bu belge üretim onayı değildir.
