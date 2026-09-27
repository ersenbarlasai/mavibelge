# Mavi Belge Canlı Geçiş Tasarımı

> Tarih: 27 Eylül 2026
> Durum: Kullanıcı tarafından yaklaşımı onaylanmış tasarım; uygulama planı ve canlı işlem onayı henüz ayrı kapılardır.
> Hedef: Staging'de kabul edilen WordPress sitesini `mavibelge.com.tr` üzerinde geri alınabilir biçimde canlıya almak.

## 1. Amaç ve başarı ölçütleri

Canlıdaki eski WordPress kurulumu, staging'de doğrulanmış yeni WordPress sitesiyle değiştirilir. Geçiş başarılı sayılabilmesi için:

- ana alan adı HTTPS üzerinden yeni siteyi sunar;
- tema `0.6.8`, Mavi Belge Core `0.5.3` ve WordPress `6.9.9` çalışır;
- eski canlı kurulum dosya ve veritabanı olarak geri döndürülebilir kalır;
- ana alan adında temiz `/%postname%/` kalıcı bağlantı yapısı çalışır; çalışmazsa geçiş kabul edilmez ve geri dönüş değerlendirilir;
- ana sayfa, katalog, yeterlilik detayları, ücretler, haberler, dokümanlar, iletişim ve hata sayfası doğru render edilir;
- beş form SMTP üzerinden tek e-posta üretir ve alıcı kutusuna ulaşır;
- üretim `robots.txt` ve sitemap erişilebilir, `noindex` kaldırılmıştır;
- DNS, MX/SPF/DKIM/DMARC ve posta kutuları değiştirilmez;
- canlıda kritik 5xx, veri kaybı, form kaybı veya yaygın 404 oluşmaz.

## 2. Doğrulanmış başlangıç durumu

### Staging

- WordPress `6.9.9`, PHP `7.3.33`, MariaDB `10.6.19`, LiteSpeed.
- Özel tema `mavibelge 0.6.8`, özel eklenti `mavibelge-core 0.5.3`.
- Yaklaşık 98 MB toplam boyut.
- Post SMTP `4.0.2` PHP 7.3 üzerinde kuruldu; test e-postası teslim edildi.
- Beş form (`contact`, `application`, `exam_request`, `complaint`, `job_application`) gerçek SMTP zincirinde kullanıcı tarafından onaylandı.
- Staging görünümü kullanıcı tarafından kabul edildi.

### Mevcut canlı

- WordPress `6.8.10`, PHP `7.3.33`, MariaDB `10.6.19`, LiteSpeed.
- Yaklaşık 4,71 GB toplam boyut; 19 etkin eski eklenti ve eski özel tema.
- Kalıcı bağlantı yapısı `/%postname%/` ve ana alan adında çalışıyor.
- Staging hazırlandıktan sonra birleştirilmesi gereken yeni canlı içerik oluşmadığı kullanıcı tarafından teyit edildi.
- Dosya ve veritabanının iki ayrı doğrulanmış yedeği ile geri yükleme provası tamamlandı.

### Kaynak kod

- Onaylı kod GitHub `main` dalında merge edilmiştir.
- Tema ve eklenti ZIP'leri deterministik paketleme kapılarından geçmiştir.
- Veritabanı, SMTP parolası, `wp-config.php`, `.wpress` paketi ve kişisel veri GitHub'a girmez.

## 3. Kısıtlar ve risk kabulü

- Erişim yalnız DirectAdmin File Manager, phpMyAdmin ve WordPress yönetim panelidir; SSH/WP-CLI yoktur.
- DirectAdmin hesabında WordPress klonlama/Push to Live aracı görünmemektedir.
- PHP 7.3 ve CentOS 7 değiştirilemez; ikisi de EOL'dir. WordPress 6.9.x hattı koşullu/legacy kabul edilir.
- WordPress.org erişiminde staging'de IPv6 `cURL error 7` görülmüştür; bu canlı açılışı doğrudan engellemez fakat güncelleme/bağlantı izlemi gerektirir.
- Eski URL envanteri ve yönlendirmelerin tümü kurumsal olarak doğrulanmış değildir. Açılıştan önce mevcut sitemap ve erişilebilen eski URL listesi saklanır; yalnız doğrulanmış yönlendirmeler uygulanır.
- Post SMTP e-posta günlüğü kişisel/form içeriğini uzun süre saklamamalıdır; ayrıntılı günlük kapatılır veya en kısa saklama süresine alınır.

## 4. Değerlendirilen geçiş yöntemleri

### A. All-in-One WP Migration tam aktarımı — seçilen yöntem

Staging'in dosya/veritabanı içeriği özel `.wpress` paketi olarak dışa aktarılır ve aynı eklenti sürümü üzerinden canlıya alınır. Araç alan adı değişimini ve serileştirilmiş WordPress verisini güvenli biçimde işler. PHP 7.3 uyumluluğu resmî eklenti metadata'sında karşılanır.

Avantajlar: File Manager/phpMyAdmin sınırında uygulanabilir; serileştirilmiş veriyi korur; alan adı değişimini yönetir; özel kod gerektirmez.
Riskler: aktarım boyutu/timeout, eski canlıda kalan kullanılmayan dosyalar, çekirdek sürüm farkı. Bunlar ön kontrol, aynı eklenti sürümü, WordPress 6.9.9 eşitlemesi ve son dosya envanteriyle kapatılır.

### B. File Manager + phpMyAdmin ile ham kopya — yedek yöntem

Dosya arşivi ve SQL içe aktarımı elle yapılır. Ham SQL `REPLACE()` serileştirilmiş veriyi bozabileceği için birincil yöntem değildir. Yalnız A yöntemi staging'de paket üretemez veya canlıda import edilemezse, ayrıca onaylanmış ayrıntılı prosedürle kullanılır.

### C. Belge kökü yönlendirmesi — reddedildi

Hesapta belge kökü/sunucu yapılandırma yetkisi yoktur; staging ile üretimi birbirine bağlar ve geri dönüş/ayrışma modelini zayıflatır.

## 5. GitHub ve dağıtım artefaktı sınırı

GitHub şunları tutar:

- tema ve eklenti kaynakları;
- testler ve yayın/runbook belgeleri;
- sürüm commit'i, tag ve GitHub Release kaydı;
- tema ve özel eklenti ZIP'leri ile SHA-256 değerleri.

GitHub şunları **tutmaz**:

- `.wpress` aktarım paketi;
- veritabanı SQL dökümü;
- `wp-config.php` ve gizli anahtarlar;
- SMTP parolası/API anahtarı;
- form gönderimleri veya kişisel veriler;
- canlı/staging tam dosya yedekleri.

`.wpress` paketi yalnız özel yönetici cihazında ve/veya erişim kontrollü hosting alanında geçici tutulur; geçiş ve kabul tamamlanınca güvenli biçimde kaldırılır.

## 6. Geçiş akışı

### 6.1. Ön hazırlık

1. Bakım penceresi ve sorumlular belirlenir; yeni canlı içerik girişi dondurulur.
2. İki doğrulanmış canlı yedeğinin tarih, boyut ve saklama konumu kaydedilir.
3. Eski canlı sitemap/URL listesi ve mevcut kritik sayfaların ekran görüntüleri alınır.
4. GitHub `main`, yerel paket SHA-256 değerleri ve staging sürümleri karşılaştırılır.
5. Staging ve canlıya aynı, PHP 7.3 uyumlu All-in-One WP Migration sürümü kurulur.
6. Canlı WordPress çekirdeği, veritabanı aktarımından önce `6.9.9` ile eşitlenir ve yönetim/ön yüz smoke testi yapılır.

### 6.2. Özel aktarım paketinin üretimi

1. Staging bakım penceresine alınır; form gönderimi durdurulur.
2. All-in-One WP Migration ile tam export alınır.
3. Cache, debug log ve eski aktarım/yedek paketleri export dışında tutulur; tema, özel eklenti, Post SMTP, uploads ve veritabanı dahil edilir.
4. `.wpress` dosyası indirilir; byte boyutu ve SHA-256 kaydedilir.
5. Paket GitHub'a veya herkese açık web dizinine konmaz.

### 6.3. Canlı cutover

1. Canlı bakım moduna alınır.
2. Canlıda son dosya+DB yedeği alınır; önceki iki kopyaya ek olarak korunur.
3. All-in-One WP Migration import başlatılır; yarıda kalırsa tekrar tekrar denenmez, hata kaydı alınır ve geri dönüş uygulanır.
4. Import sonrası WordPress oturumu staging yönetici hesabına dönüşebilir; yeniden giriş yapılır.
5. `Ayarlar → Genel`: iki URL `https://mavibelge.com.tr` olmalıdır.
6. `Ayarlar → Kalıcı Bağlantılar`: ana alan adında daha önce çalıştığı kanıtlanan `/%postname%/` seçilir ve kaydedilir.
7. Ortam `production`, arama motoru görünürlüğü açık yapılır; staging'e özgü `noindex` kaldırılır.
8. Post SMTP alan adı/gönderen ayarları ve e-posta günlük politikası doğrulanır.
9. Yalnız `mavibelge-core`, Post SMTP ve gerçekten gerekli sistem eklentileri etkin kalır.

### 6.4. Kabul ve bakım modundan çıkış

Bakım modu ancak Bölüm 7'deki kritik testler geçtikten sonra kaldırılır. Ardından aynı testlerin anonim tarayıcıyla kısa turu tekrarlanır.

## 7. Canlı kabul matrisi

### Kritik P0

- HTTPS ve ana sayfa 200.
- `/yeterlilikler/`, en az iki sektör, bir yeterlilik detayı ve `/sinav-ucretleri/` 200.
- Header, footer, masaüstü/mobil menü ve logo bağlantıları doğru.
- WordPress yönetimine giriş yapılabiliyor.
- Post SMTP test e-postası alıcı kutusuna ulaşıyor.
- Beş formun her biri tek e-posta üretiyor; iki dosyalı formda ek geliyor.
- Form başarı yönlendirmesinde kişisel veri URL'ye yazılmıyor.
- Kritik 5xx, PHP uyarısı veya boş beyaz sayfa yok.

### P1

- Haberler, duyurular, dokümanlar, SSS, referanslar, iletişim, KVKK ve gizlilik sayfaları.
- `robots.txt` üretim politikası ve `wp-sitemap.xml`.
- Eski kritik URL'lerin 200 veya doğrulanmış 301 sonucu.
- 404 sayfası, arama ve sayfalama.
- 390, 768, 1280 ve 1440 px görünüm; yatay taşma ve konsol kaynak hatası yok.

### Operasyonel gözlem

- İlk 30 dakika yoğun; ilk 24 saat düzenli 404/5xx ve SMTP log kontrolü.
- Post SMTP başarısız iletileri ve sunucu mail kuyruğu izlenir.
- Eski canlı dosya ve veritabanı geri dönüş penceresi sonuna kadar silinmez.

## 8. Geri dönüş tasarımı

### Anında geri dönüş ölçütleri

- ana sayfa/yönetim açılamıyor veya yaygın 5xx;
- import eksik/yarım kaldı;
- katalog ya da içerik bütünlüğü kayıp;
- beş formdan herhangi biri e-posta üretmiyor;
- temiz kalıcı bağlantılar yaygın 404 üretiyor;
- kritik güvenlik/kişisel veri sızıntısı gözleniyor.

### Geri dönüş işlemi

1. Bakım modu korunur.
2. Import öncesi canlı dosya ve veritabanı yedeği geri yüklenir.
3. Eski WordPress sürümü, eski `wp-config.php` ve eski aktif eklenti durumu birlikte geri gelir; yalnız DB veya yalnız dosya geri yüklenmez.
4. Ana sayfa, yönetim, eski formlar ve e-posta smoke testi yapılır.
5. Bakım modu kaldırılır; başarısız yeni paket ve hata kaydı özel alanda saklanır.

Eski canlı kopya, kullanıcı ayrıca onay vermeden ve gözlem penceresi tamamlanmadan silinmez. Mail kutuları/yedekleri hiçbir aşamada silinmez.

## 9. Güvenlik ve veri koruma

- Gerçek parola/anahtar ekran görüntüsüne, rapora, GitHub'a veya mesajlara yazılmaz.
- Canlı yedekler web kökü dışında veya erişim kontrollü alanda tutulur.
- Geçici migration eklentisi ve paketi kabul sonrasında kaldırılır; kullanılmayan eski eklenti/tema dosyaları web kökünde bırakılmaz.
- Post SMTP günlüklerinde hassas form gövdesi ve ekler kalıcı tutulmaz.
- Form testi yetkili sentetik veriyle yapılır; gerçek T.C. kimlik/CV test verisi kullanılmaz.
- DNS ve mail DNS kayıtları bu geçişin kapsamı dışındadır ve değiştirilmez.

## 10. Ayrı onay kapıları

Bu tasarım aşağıdaki eylemleri tek başına yetkilendirmez:

1. Git tag/GitHub Release oluşturma ve release artefaktı yükleme.
2. All-in-One WP Migration'ı canlıda güncelleme/etkinleştirme.
3. Canlı WordPress çekirdeğini `6.9.9`a eşitleme.
4. `.wpress` paketini canlıya import etme.
5. Bakım modundan çıkıp yeni siteyi kamuya açma.
6. Eski canlı dosya/veritabanı veya geçici paketleri silme.

Uygulama planı bu kapıları tek tek, doğrulama ve geri dönüş adımlarıyla komut/ekran seviyesinde tarif edecektir.
