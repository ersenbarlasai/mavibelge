# WordPress Faz 0 — Üretim Uyumluluk Matrisi

> Tarih: 10 Eylül 2026
>
> Kapsam: Mevcut üretim ortamının (CentOS 7 / DirectAdmin Legacy / PHP 7.3) WordPress ile uyumluluğu. Sunucuya bağlanılmadı; bu belge yalnız kullanıcının bildirdiği doğrulanmış bilgiler ile resmî WordPress/PHP/Red Hat kaynaklarından üretilmiştir.
>
> Sürüm bilgileri 10 Eylül 2026 tarihinde resmî kaynaklardan çekilmiştir. Kaynak listesi §12'dedir.
>
> Bu belge kullanıcının açık kararına aykırı olarak işletim sistemi yükseltmesi, yeni sunucu veya kontrol paneli değişikliği **dayatmaz**. Riskleri gizlemez, karar kullanıcınındır.

---

## 1. Doğrulanmış Üretim Bilgileri

Kaynak: kullanıcının bu görevdeki açık bildirimi ve [`wordpress-ana-uygulama-plani.md`](./wordpress-ana-uygulama-plani.md) §2.

| Alan | Değer | Kaynak |
|---|---|---|
| Sunucu | Dell R210-II Dedicated | Kullanıcı bildirimi |
| RAM | 16 GB | Kullanıcı bildirimi |
| Disk | 250 GB SSD + 1 TB SATA | Kullanıcı bildirimi |
| Hostname | `srv.mavibelge.com.tr` | Kullanıcı bildirimi |
| Birincil IP | `5.250.247.218` | Kullanıcı bildirimi |
| IP ağı | `5.250.247.217/29` | Kullanıcı bildirimi |
| Nameserver | `ns1.mavibelge.com.tr`, `ns2.mavibelge.com.tr` | Kullanıcı bildirimi |
| İşletim sistemi | CentOS 7 | Kullanıcı bildirimi |
| Kontrol paneli | DirectAdmin Legacy | Kullanıcı bildirimi |
| DirectAdmin kullanıcı ekranında görünen PHP | **PHP 7.3 ve PHP 5.6** | Kullanıcı bildirimi |
| Mevcut canlı CMS | WordPress 6.8.8 | [`final-rapor.md`](./final-rapor.md) §4.2 (17 Ağu 2026 incelemesi) |
| Mevcut web sunucusu | LiteSpeed | [`final-rapor.md`](./final-rapor.md) §4.2 (17 Ağu 2026 incelemesi) |

**Uyarı:** Son iki satır 17 Ağustos 2026 tarihli uzaktan inceleme bulgusudur, sunucu içi doğrulama değildir. Bugünkü değerleri farklı olabilir; §2'de yeniden doğrulanacaklar arasındadır.

---

## 2. Henüz Bilinmeyen Sunucu Bilgileri

Tamamı [`wordpress-sunucu-bilgi-talebi.md`](./wordpress-sunucu-bilgi-talebi.md) ile talep edilecektir. Hiçbiri tahmin edilmemiştir.

| # | Bilinmeyen | Neden kritik |
|---:|---|---|
| 1 | **PHP 7.4 veya üzeri bir sürüm sunucuda kurulu/kurulabilir mi** | §3'teki tek engelleyici karar buna bağlı |
| 2 | Gerçek PHP web (`php-fpm`/LSAPI) ve CLI sürümleri | DirectAdmin ekranı tüm kurulu sürümleri göstermeyebilir |
| 3 | Etkin PHP uzantıları (`mysqli`, `gd`/`imagick`, `curl`, `mbstring`, `zip`, `dom`, `xml`, `json`, `openssl`, `intl`, `exif`, `fileinfo`, `iconv`, `simplexml`) | WordPress medya, HTTPS istekleri ve çoklu dil için gerekli |
| 4 | PHP limitleri (`memory_limit`, `max_execution_time`, `upload_max_filesize`, `post_max_size`, `max_input_vars`) | Medya yükleme ve toplu içe aktarma bunlara bağlı |
| 5 | MySQL/MariaDB türü ve kesin sürümü | Veritabanı uyumluluğu (§7) |
| 6 | Web sunucusu türü/sürümü ve rewrite desteği | Kalıcı bağlantı yapısı (§8) |
| 7 | Disk bölümleri, boş alan, inode kullanımı | Yedekleme ve medya kapasitesi (§10) |
| 8 | Web / veritabanı / aktif mail verisinin ayrı boyutları | Mail arşivleme planı |
| 9 | DirectAdmin sürümü ve güncelleme kanalı | CustomBuild ile PHP sürüm ekleme imkânı |
| 10 | SSL sertifikası ve otomatik yenileme durumu | HTTPS WordPress için zorunludur |
| 11 | Gerçek sistem cron desteği | WP-Cron yerine güvenilir zamanlama |
| 12 | Yedekleme/geri yükleme imkânı | Yayına geçiş ön koşulu |
| 13 | Mail servisleri, kuyruk, SPF, DKIM, DMARC, PTR | Form/bildirim e-postalarının teslimi (§9) |
| 14 | Mevcut sitenin tema/eklenti/özel kod envanteri | Geçiş ve eski URL planı |

---

## 3. WordPress Çekirdeği Uyumluluğu

### 3.1. Resmî gereksinimler (wordpress.org, 10 Eylül 2026)

| Bileşen | WordPress.org ifadesi |
|---|---|
| PHP | "**Version 8.3 or greater**" gerekli olarak listelenir. PHP 7.4+ üzerinde hâlâ çalışabileceği ancak bu sürümlerin "resmî yaşam sonuna ulaştığı" ve güvenlik riski taşıdığı belirtilir. |
| Veritabanı | "**MariaDB 10.11+**" veya "**MySQL 8.0+**". MySQL 5.5.5+ için eski (legacy) destek vardır, aynı güvenlik uyarısıyla. |
| HTTPS | "**Required for every install**" — her kurulum için zorunlu. |
| Web sunucusu | Apache veya Nginx "en sağlam ve özellikli" olarak önerilir; PHP ve MySQL destekleyen her sunucu çalışır. |

### 3.2. Çekirdek minimum PHP sürümü — belirleyici bulgu

| Kaynak | Bulgu |
|---|---|
| `api.wordpress.org/core/version-check/1.7/` | Sunulan güncel sürüm **WordPress 7.1**; bildirilen `php_version` = **7.4**, `mysql_version` = 5.5.5 |
| Make WordPress Core, "Dropping support for PHP 7.2 and 7.3" (9 Oca 2026) | **WordPress 7.0, PHP 7.2 ve 7.3 desteğini kaldırmıştır.** Yeni minimum PHP **7.4.0**; önerilen minimum 8.3 olarak kalmıştır. WordPress 7.0 Nisan 2026'da yayınlanmak üzere planlanmıştı. |
| `wordpress.org/download/releases/` | Güncel sürüm **WordPress 7.1** (19 Ağu 2026). Sayfa açıkça şunu yazar: "**Only the most recent in the 7.1 series is safe to use and actively maintained.**" |

### 3.3. Doğrudan sonuç

> **PHP 7.3 üzerinde güncel WordPress kurulamaz.** WordPress 7.0 ve sonrası PHP 7.4.0 minimumunu şart koşar. PHP 7.3 ile kurulabilecek en son WordPress hattı **6.9**'dur ve wordpress.org yalnız 7.1 serisinin aktif bakımda olduğunu belirtmektedir.

Bu, çekirdek minimumu nedeniyle "nasılsa çalışır" varsayımının **geçersiz** olduğu anlamına gelir. Sorun tema/eklenti ekosistemi değil, çekirdeğin kendisidir. Bu teknik gerçek **gizlenmemiştir** ve §3.4'teki karar bunu değiştirmez.

### 3.4. Karar

> **Güncelleme (10 Eylül 2026, Faz 1):** Kullanıcı, PHP 7.3'ü şimdilik değiştirilmeyecek **kabul edilmiş bağlayıcı üretim kısıtı** olarak onaylamıştır. Ayrıntı: [`karar-kaydi-wordpress-php73.md`](./karar-kaydi-wordpress-php73.md). Bu, aşağıdaki `Engelli` sınıflandırmalarını **teknik olarak değiştirmez** — WordPress 7.1 hâlâ PHP 7.3 ile kurulamaz ve WordPress 6.9 hattı hâlâ legacy/bakımsızdır. Değişen, projenin bu riski nasıl ele aldığıdır: proje artık sunucu/PHP değişikliğini beklemek yerine, kabul edilen kısıt içinde en sağlıklı mimariyle (özel tema + özel eklenti, minimal üçüncü taraf bağımlılık, sıkı kod kalite kapıları) ilerler.

| Senaryo | Sonuç |
|---|---|
| PHP 7.3 + güncel WordPress (7.1) | **Engelli.** Teknik olarak imkânsız — çekirdek minimum 7.4.0. Değişmedi. |
| PHP 7.3 + WordPress 6.9 | **Kabul edilmiş yüksek riskli üretim kısıtı.** Kurulabilir, ancak aktif bakımdaki hat değildir; bu risk kullanıcı tarafından bilerek kabul edilmiştir (`karar-kaydi-wordpress-php73.md`). `wordpress-ana-uygulama-plani.md` §9.2 zaten "sorun tema/eklenti sürümünü düşürerek gizlenmez" der; bu ilke geçerliliğini korur. |
| **PHP 7.4+ (aynı sunucuda) + güncel WordPress** | **Koşullu — hâlâ tercih edilir, ancak artık Faz 1 önkoşulu değil.** Sunucuda PHP 7.4+ bulunup bulunmadığı paralel olarak doğrulanabilir (§2 madde 1). Doğrulanırsa karar §7'deki tetikleyicilerden biri olarak yeniden değerlendirilir. |
| PHP 8.3+ (aynı sunucuda) + güncel WordPress | **Uygun.** WordPress'in kendi önerdiği hedef; değişmedi. |

### 3.5. Kullanıcı kararına saygı ve dürüst risk kaydı

Kullanıcı sunucu donanımı, işletim sistemi, kontrol paneli ve mail altyapısında değişiklik istememektedir. **Bu belge o kararı değiştirmeyi önermez.**

Ancak dikkat edilmesi gereken ayrım şudur: **PHP sürümünü yükseltmek, işletim sistemini veya sunucuyu değiştirmek değildir.** DirectAdmin, CustomBuild aracılığıyla aynı sunucu ve aynı işletim sistemi üzerinde birden fazla PHP sürümünü yan yana kurabilir. DirectAdmin kullanıcı ekranında yalnız PHP 7.3 ve 5.6 görünmesi, sunucuda başka sürüm **kurulamayacağı** anlamına gelmez; yalnızca şu an **kurulu olanların** bunlar olduğunu gösterir.

Bu nedenle §2 madde 1 en yüksek öncelikli doğrulama kalemidir ve bu, kullanıcının "sunucu/OS/panel/mail değişmesin" kararının **dışında** kalan bir sorudur.

**Doğrulanamadı:** CentOS 7 EOL olduğu için DirectAdmin CustomBuild'in bu makinede PHP 7.4+ derleyip derleyemeyeceği yerel olarak bilinemez. Sistem yöneticisi tarafından doğrulanmalıdır.

---

## 4. Tema PHP 7.3 Kodlama Sınırları

> Bu bölüm, **PHP 7.3 hedefi geçerli kaldığı sürece** bağlayıcıdır. §3'e göre PHP 7.4+ doğrulanırsa bu sınırlar gevşetilir ve bu belge güncellenir.

`mavibelge` teması kodunda kullanılamayacak sözdizimi/API (PHP 7.4 ve sonrasında gelmiştir):

| Özellik | Geldiği sürüm | PHP 7.3'te |
|---|---|---|
| Ok fonksiyonları `fn() =>` | 7.4 | **Kullanılamaz** |
| Tipli sınıf özellikleri `private string $x;` | 7.4 | **Kullanılamaz** |
| Null birleştirme atama `??=` | 7.4 | **Kullanılamaz** |
| Dizi içinde açma `[...$a, ...$b]` | 7.4 | **Kullanılamaz** |
| Sayısal ayraç `1_000_000` | 7.4 | **Kullanılamaz** |
| `str_contains()`, `str_starts_with()`, `str_ends_with()` | 8.0 | **Kullanılamaz** |
| Adlandırılmış argümanlar | 8.0 | **Kullanılamaz** |
| Constructor property promotion | 8.0 | **Kullanılamaz** |
| `match` ifadesi | 8.0 | **Kullanılamaz** |
| Null-safe operatör `?->` | 8.0 | **Kullanılamaz** |
| `union` tip bildirimleri | 8.0 | **Kullanılamaz** |
| `enum` | 8.1 | **Kullanılamaz** |
| `readonly` özellik | 8.1 | **Kullanılamaz** |
| İlk sınıf callable `foo(...)` | 8.1 | **Kullanılamaz** |
| `never` dönüş tipi | 8.1 | **Kullanılamaz** |

### 4.1. Tema için ek kurallar

- Şablon dosyalarında yalnız PHP 7.3'te bulunan çekirdek fonksiyonlar kullanılır.
- Blok stilleri statik CSS ile verilir; sunucu tarafında blok render'ı gerektiren yaklaşımlar sınırlı tutulur.
- Tema derlenmiş, üretime hazır CSS/JS ile sevk edilir. Sunucuda Node.js bulunacağı **varsayılmaz** (`wordpress-ana-uygulama-plani.md` §2.1).
- Sözdizimi uyumu her teslimde otomatik doğrulanır (§11.1).

---

## 5. Özel Eklenti (`mavibelge-core`) PHP 7.3 Kodlama Sınırları

§4'teki tüm sözdizimi kısıtları `mavibelge-core` için de aynen geçerlidir. Ek olarak:

| Alan | Kural | Gerekçe |
|---|---|---|
| Composer bağımlılığı | Üretim sunucusunda Composer bulunacağı varsayılmaz. Gerekliyse `vendor/` derlenmiş olarak pakete konur. | `wordpress-ana-uygulama-plani.md` §2.1 |
| Composer paket sürümleri | Seçilen her paketin `composer.json` içindeki `require.php` alanı PHP 7.3'ü kapsamalıdır | Çoğu güncel PHP kütüphanesi `^8.1` şart koşar — **çoğu kullanılamaz** |
| Tip bildirimleri | Yalnız PHP 7.3'te geçerli skaler/sınıf tipleri; `union`, `never`, `readonly`, `enum` yok | §4 tablosu |
| CPT/taksonomi kaydı | Standart `register_post_type` / `register_taxonomy` | Çekirdek API sürümden bağımsız |
| REST uçları | Salt okunur uçlar dahil, `permission_callback` her uçta zorunlu | Güvenlik |
| Veri doğrulama | `sanitize_*`, `wp_kses_*`, `esc_*`, `wp_verify_nonce`, `current_user_can` zorunlu | `wordpress-ana-uygulama-plani.md` §9.1 |
| CSV içe/dışa aktarma | `fgetcsv`/`fputcsv` — PHP 7.3'te mevcut | Ücret toplu güncelleme |
| `enum` yerine | Sınıf sabitleri (`const`) veya kontrollü dizi | Başvuru/onay durumları |

---

## 6. Üçüncü Taraf Eklenti Seçim Kuralları

Bu görevde hiçbir eklenti **kilitlenmemiştir.** Aşağıdakiler seçim kurallarıdır.

### 6.1. Zorunlu kurallar

1. Eklentinin `readme.txt` içindeki **"Requires PHP"** değeri, seçilen üretim PHP sürümünü **karşılamalıdır**. Bu değer wordpress.org/plugins sayfasında görünür ve seçim öncesi tek tek kontrol edilir.
2. Eklentinin **"Tested up to"** değeri, kurulacak WordPress sürümünü kapsamalıdır.
3. Son güncelleme tarihi ve aktif kurulum sayısı bakım göstergesi olarak değerlendirilir; terk edilmiş eklenti kurulmaz.
4. Aynı işi yapan ikinci bir eklenti kurulmaz (`wordpress-ana-uygulama-plani.md` §3.1).
5. Elementor, WPBakery ve benzeri sayfa oluşturucular **yasaktır**.
6. Her eklentinin kaynağı ve lisansı kayıt altına alınır.
7. Kurulmadan önce eş üretim ortamında (aynı PHP + aynı veritabanı sürümü) denenir.

### 6.2. PHP 7.3 senaryosunun eklenti ekosistemine etkisi

**Bu, ayrıca değerlendirilmesi gereken bağımsız bir risktir.** Çekirdek bir sürümde çalışsa bile, güncel eklentilerin büyük kısmı `Requires PHP: 7.4` veya `8.0+` bildirir. PHP 7.3'te kalınırsa:

- SEO, form, önbellek, güvenlik ve SMTP kategorilerinin her birinde eklenti seçenekleri belirgin biçimde daralır.
- Seçilebilen eklentiler eski sürümlere sabitlenmek zorunda kalır ve **eklenti güvenlik güncellemeleri alınamaz**.
- Eklenti güvenlik açığı ortaya çıktığında güncelleme yolu kapalı olur.

**Karar: PHP 7.3'te üçüncü taraf eklenti ekosistemi `Engelli` sayılır.** Kesin eklenti listesi ancak üretim PHP sürümü doğrulandıktan sonra hazırlanabilir.

---

## 7. Veritabanı Uyumluluğu

| Konu | Durum |
|---|---|
| WordPress önerisi | MariaDB 10.11+ veya MySQL 8.0+ |
| WordPress eski (legacy) desteği | MySQL 5.5.5+ (`api.wordpress.org` `mysql_version` alanı da 5.5.5 bildirir) |
| Üretimdeki tür ve sürüm | **Doğrulanamadı** — §2 madde 5 |

### 7.1. Karar

**Koşullu.** Mevcut canlı site zaten WordPress 6.8.8 çalıştırdığına göre kullanılabilir bir MySQL/MariaDB kurulu olması güçlü olasılıktır; ancak sürümü doğrulanmadan hiçbir şey kilitlenmez.

### 7.2. Doğrulanınca karara bağlanacaklar

| Kontrol | Neden |
|---|---|
| Sunucu karakter seti ve collation | Türkçe karakterler için `utf8mb4` / `utf8mb4_unicode_520_ci` gerekir; `utf8` (3 bayt) **yetersizdir** |
| `innodb` motoru varsayılan mı | Performans ve bütünlük |
| `max_allowed_packet` | Toplu içe aktarma ve yedek geri yükleme |
| Veritabanı kullanıcı yetkileri | `CREATE`, `ALTER`, `INDEX` gerekir |
| Mevcut veritabanı boyutu | Yedekleme penceresi |

**Uyarı:** Mevcut canlı sitede karakter bozulmaları raporlanmıştır (`final-rapor.md` §3.3). Bunun kaynağı collation olabilir. Veri aktarımından önce mevcut veritabanının karakter seti mutlaka tespit edilmelidir.

---

## 8. Web Sunucusu, Rewrite ve HTTPS

| Konu | Durum |
|---|---|
| Mevcut web sunucusu | LiteSpeed (`final-rapor.md` §4.2, 17 Ağu 2026 — sunucu içi doğrulanmadı) |
| WordPress önerisi | Apache veya Nginx; PHP+MySQL destekleyen her sunucu çalışır |
| LiteSpeed uyumu | LiteSpeed Apache `.htaccess` kurallarını yorumlar; WordPress kalıcı bağlantıları genellikle çalışır — **doğrulanmalı** |
| HTTPS | WordPress tarafından **zorunlu** ("Required for every install") |
| Mevcut SSL durumu | **Doğrulanamadı** — §2 madde 10 |

### 8.1. Karar

**Koşullu.** Doğrulanacaklar:

1. `.htaccess` etkin mi ve `mod_rewrite` eşdeğeri çalışıyor mu (kalıcı bağlantı için zorunlu).
2. Kabul ortamı `cms-yeni.mavibelge.com.tr` için ayrı geçerli SSL sertifikası alınabiliyor mu.
3. SSL otomatik yenileme çalışıyor mu (Let's Encrypt 90 günlük sertifikalar için kritik).
4. Canlı geçişte HTTPS yönlendirmesi ve güvenlik başlıkları hangi katmanda uygulanacak.

### 8.2. Statik siteden gelen kısıt

[`wordpress-faz0-depo-envanteri.md`](./wordpress-faz0-depo-envanteri.md) §7.3'te tespit edildiği üzere üç sosyal medya bağlantısı `http://` şemasıyla yazılmıştır. HTTPS zorunluluğu ve karışık içerik uyarıları nedeniyle bunlar temaya taşınırken düzeltilmelidir.

---

## 9. Mail/SMTP Etkileri

Kullanıcı mail altyapısında değişiklik istememektedir. Bu bölüm mevcut altyapıyı değiştirmeyi önermez; WordPress'in ondan ne beklediğini kaydeder.

| Konu | Durum |
|---|---|
| WordPress varsayılan gönderim | `wp_mail()` → PHP `mail()` → yerel MTA. Teslim güvenilirliği düşüktür. |
| Önerilen | Kimlik doğrulamalı SMTP eklentisi ile kurumun kendi mail sunucusuna gönderim |
| SPF / DKIM / DMARC / PTR | **Doğrulanamadı** — §2 madde 13 |
| Mail kuyruğu ve boyutu | **Doğrulanamadı** — §2 madde 8 |

### 9.1. Karar

**Koşullu.** Formlar ve başvuru bildirimleri e-posta teslimine bağlıdır (`wordpress-ana-uygulama-plani.md` §6). SPF/DKIM/DMARC doğrulanmadan form modülü kabul edilemez.

### 9.2. Mail arşivleme

Mail arşivlerinin kurumun kendi harici disklerine alınması planlanmaktadır. Bağlayıcı kural değişmemiştir:

> **İki doğrulanmış kopya oluşmadan sunucudan mail silinmeyecektir.** Mail arşivi, WordPress ve sunucu yedeğinin yerine geçmez (`wordpress-ana-uygulama-plani.md` §10).

### 9.3. SMTP eklentisi seçimi

Kilitlenmemiştir. §6.1 kurallarına tabidir; özellikle "Requires PHP" değeri üretim PHP sürümüyle uyumlu olmalıdır. SMTP parolası `wp-config.php` veya veritabanında düz metin tutulmamalı, Git'e **hiçbir koşulda** girmemelidir.

---

## 10. Performans, Disk ve Yedekleme Sınırları

### 10.1. Donanım değerlendirmesi

16 GB RAM ve 250 GB SSD, tek bir kurumsal WordPress sitesi için **fazlasıyla yeterlidir**. Donanım bu projede darboğaz değildir.

| Konu | Durum |
|---|---|
| RAM | **Uygun** — 16 GB yeterli |
| SSD/SATA görev dağılımı | **Doğrulanamadı** — §2 madde 7 |
| Boş alan ve inode | **Doğrulanamadı** — §2 madde 7 |
| Web/DB/mail veri boyutları | **Doğrulanamadı** — §2 madde 8 |
| Yedekleme/geri yükleme imkânı | **Doğrulanamadı** — §2 madde 12 |
| Gerçek sistem cron | **Doğrulanamadı** — §2 madde 11 |

### 10.2. inode uyarısı

Mail arşivleri çok sayıda küçük dosya üretir. Disk **boş görünse bile inode tükenmiş olabilir**; bu durumda WordPress medya yükleyemez ve yedek alınamaz. inode kullanımı ayrıca sorulmuştur.

### 10.3. Performans yaklaşımı

Sunucu tarafında üretilmiş hafif HTML, WebP + `srcset`, ertelenmiş kritik olmayan JS, sayfa/tarayıcı önbelleği. Hedefler `wordpress-ana-uygulama-plani.md` §8'de tanımlıdır. Önbellek eklentisi §6.1 kurallarına tabidir ve LiteSpeed kullanılıyorsa sunucu düzeyi önbellekle çakışmamalıdır.

### 10.4. Yedekleme

`wordpress-ana-uygulama-plani.md` §10 bağlayıcıdır: veritabanı günlük; `wp-content/uploads` günlük artımlı + haftalık tam; tema/eklenti Git + dağıtım paketi; en az iki harici disk dönüşümlü; bir kopya fiziksel olarak ayrı; aylık örnek geri yükleme testi.

**Kesin kural:** Yedekten geri dönüş denenmeden canlı yayın yapılmaz.

---

## 11. Karar Tablosu

| # | Alan | Karar | Gerekçe / bağımlılık |
|---:|---|---|---|
| 1 | Donanım (16 GB RAM, SSD) | **Uygun** | Kurumsal tek site için fazlasıyla yeterli |
| 2 | HTML/CSS/JS varlıklarının yeniden kullanımı | **Uygun** | PHP'den bağımsız; envanter §10'da doğrulandı |
| 3 | 83 yeterlilik + 103 ücret + 6 haber verisinin aktarımı | **Uygun** | Veri yerelde doğrulandı; 19 ücret kaydında manuel kod eşleştirmesi gerekir |
| 4 | Tasarım token'ları ve tema dönüşümü | **Uygun** | `tokens.css` doğrudan taşınır |
| 5 | **PHP 7.3 + güncel WordPress (7.1)** | **Engelli (teknik, değişmedi)** | Çekirdek minimum PHP 7.4.0 (WordPress 7.0'dan itibaren). Teknik olarak imkânsız. |
| 6 | **PHP 7.3 + WordPress 6.9 (son uyumlu hat)** | **Kabul edilmiş yüksek riskli üretim kısıtı** | wordpress.org yalnız 7.1 serisini aktif bakımda sayar; bu hat legacy'dir. Kullanıcı bu riski `karar-kaydi-wordpress-php73.md` ile bilerek kabul etmiştir — risk gizlenmedi, engel değil kısıt olarak kayıtlıdır. |
| 7 | **PHP 7.3 + güncel üçüncü taraf eklenti ekosistemi** | **Kabul edilmiş yüksek riskli üretim kısıtı** | Güncel eklentilerin çoğu `Requires PHP 7.4+`/`8.0+`; eklenti güvenlik güncellemeleri alınamaz. Risk azaltma: eklenti bağımlılığı minimumda tutulur (`karar-kaydi-wordpress-php73.md` §5). |
| 8 | **Aynı sunucuda PHP 7.4+ kurulumu** | **Doğrulanamadı** | DirectAdmin CustomBuild ile mümkün olabilir; CentOS 7 EOL nedeniyle yerelde bilinemez. **En yüksek öncelikli soru.** |
| 9 | PHP 8.3+ (WordPress'in önerdiği hedef) | **Doğrulanamadı** | §2 madde 1 |
| 10 | Veritabanı (MySQL/MariaDB) | **Koşullu** | Tür/sürüm/collation doğrulanmalı; `utf8mb4` zorunlu |
| 11 | Web sunucusu ve rewrite/kalıcı bağlantı | **Koşullu** | LiteSpeed `.htaccess` yorumlar; doğrulanmalı |
| 12 | HTTPS | **Koşullu** | WordPress için zorunlu; `cms-yeni` için ayrı sertifika ve otomatik yenileme doğrulanmalı |
| 13 | Mail/SMTP teslimi | **Koşullu** | SPF/DKIM/DMARC/PTR doğrulanmalı |
| 14 | Yedekleme ve geri yükleme | **Koşullu** | İmkân doğrulanmalı; geri dönüş denenmeden yayın yok |
| 15 | Disk boş alan ve inode | **Doğrulanamadı** | §2 madde 7 |
| 16 | Cron / WP-Cron | **Doğrulanamadı** | §2 madde 11 |
| 17 | **CentOS 7 (EOL 30 Haziran 2024)** | **Yüksek risk — açık** | Kullanıcı kararıyla değiştirilmiyor. Çekirdek/OpenSSL/sistem kütüphaneleri için güvenlik yaması gelmiyor. Kapatılmadı, kayıtta açık tutuluyor. |
| 18 | **PHP 7.3 (EOL 6 Aralık 2021)** | **Yüksek risk — açık** | 4 yıl 9 aydır güvenlik yaması almıyor |
| 19 | PHP 5.6 (EOL 31 Aralık 2018) | **Engelli** | WordPress için kullanılmaz; sunucuda varsa devre dışı bırakılması önerilir |
| 20 | DirectAdmin Legacy sürümü | **Doğrulanamadı** | §2 madde 9 |
| 21 | Mevcut sitenin tema/eklenti envanteri | **Doğrulanamadı** | §2 madde 14 |
| 22 | Nihai eski URL envanteri | **Doğrulanamadı** | Yerelde yalnız 57 URL; canlı sitemap/GSC/log gerekli |
| 23 | Kesin WordPress sürüm kilidi | **Doğrulanamadı** | Kasıtlı olarak kilitlenmedi — §2 madde 1, 3, 5 çözülmeden verilemez |
| 24 | Kesin eklenti listesi ve sürümleri | **Doğrulanamadı** | Kasıtlı olarak kilitlenmedi — üretim PHP sürümüne bağlı |

---

## 12. Sürüm Doğrulama Kayıtları

10 Eylül 2026 tarihinde çekilen resmî kaynaklar:

| Kaynak | Adres | Alınan bilgi |
|---|---|---|
| WordPress.org Requirements | `https://wordpress.org/about/requirements/` | PHP 8.3+; MariaDB 10.11+ / MySQL 8.0+; HTTPS zorunlu |
| WordPress core version-check API | `https://api.wordpress.org/core/version-check/1.7/` | Güncel sürüm 7.1; `php_version` 7.4; `mysql_version` 5.5.5 |
| Make WordPress Core | `https://make.wordpress.org/core/2026/01/09/dropping-support-for-php-7-2-and-7-3/` | WordPress 7.0 PHP 7.2/7.3 desteğini kaldırdı; yeni minimum 7.4.0; önerilen 8.3 |
| WordPress Releases | `https://wordpress.org/download/releases/` | WordPress 7.1 (19 Ağu 2026); "yalnız 7.1 serisinin en yenisi güvenli ve aktif bakımda" |
| PHP Supported Versions | `https://www.php.net/supported-versions.php` | Desteklenen dallar: 8.2 (yalnız güvenlik), 8.3, 8.4, 8.5. 7.3 listede yok |
| PHP EOL | `https://www.php.net/eol.php` | PHP 7.3 EOL: 6 Aralık 2021. PHP 5.6 EOL: 31 Aralık 2018 |
| Red Hat / CentOS | `https://www.redhat.com/en/topics/linux/centos-linux-eol` | CentOS Linux 7 EOL: 30 Haziran 2024 |

### 12.1. Yeniden doğrulanması gerekenler

| Konu | Kontrol adımı |
|---|---|
| PHP 7.3 ile çalışan en son WordPress hattının **6.9** olduğu | `https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/` tablosu görsel olarak okunmalı. Bu görevde alınan otomatik özet çelişkili döndü; iki bağlayıcı kaynak (WP 7.0'ın 7.2/7.3 desteğini kaldırdığı ve WP 7.1'in `php_version` 7.4 bildirdiği) hattın **6.9** olduğunu gösterir ama tablo satırı gözle teyit edilmelidir. |
| WordPress'in 6.9 hattına güvenlik yaması geri-taşıyıp taşımadığı | `https://wordpress.org/about/security/` ve sürüm notları |
| Her aday eklentinin `Requires PHP` değeri | İlgili `wordpress.org/plugins/<slug>/` sayfası, eklenti seçimi anında |

### 12.2. Sürüm kilidi kuralı

Bu aşamada **hiçbir WordPress veya eklenti sürümü kilitlenmemiştir.** Kilit kararı ancak şunlar doğrulandıktan sonra verilir:

1. Üretimde kullanılabilecek en yüksek PHP sürümü (§2 madde 1-2)
2. Etkin PHP uzantıları ve limitler (§2 madde 3-4)
3. Veritabanı türü ve sürümü (§2 madde 5)

---

## 13. Özet Değerlendirme

Depo tarafı hazırdır: statik site varlıkları, 83 yeterlilik, 103 ücret ve tasarım token'ları PHP sürümünden bağımsız olarak yeniden kullanılabilir durumdadır.

PHP 7.3 üzerinde güvenlik bakımı alan bir WordPress kurulumu **hâlâ mümkün değildir**; bu, WordPress 7.0'ın minimum PHP'yi 7.4.0'a çıkarmasının doğrudan sonucudur ve değişmemiştir. **Güncelleme (Faz 1, 10 Eylül 2026):** kullanıcı bu riski bilerek kabul etmiş ve PHP 7.3'ü bağlayıcı üretim kısıtı olarak onaylamıştır ([`karar-kaydi-wordpress-php73.md`](./karar-kaydi-wordpress-php73.md)); bu artık Faz 1 öncesi çözülmesi gereken bir engel değildir.

Bu, kullanıcının "sunucu, işletim sistemi, kontrol paneli ve mail altyapısı değişmesin" kararıyla **çelişmez**. Sunucuda DirectAdmin CustomBuild ile PHP 7.4+ (tercihen 8.3+) kurulup kurulamayacağı sorusu hâlâ değerlidir ve paralel olarak araştırılabilir, ancak artık sonraki fazların önkoşulu değildir.

Sonraki adımlar: Faz 2 içerik modeli (paralel: [`wordpress-sunucu-bilgi-talebi.md`](./wordpress-sunucu-bilgi-talebi.md)).
