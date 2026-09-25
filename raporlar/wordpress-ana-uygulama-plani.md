# Mavi Belge WordPress Ana Uygulama Planı

> Karar tarihi: 10 Eylül 2026
>
> Durum: Güncel ve bağlayıcı mimari karar
>
> Bu belge, yeni sitenin Laravel + Filament ile geliştirilmesine ilişkin önceki planların yerine geçer. Önceki raporlar inceleme ve tarihsel karar kaydı olarak korunur; yeni uygulamada çelişki olması halinde bu belge esas alınır.

## 1. Kesinleşen Kararlar

- Yeni site WordPress ile geliştirilecektir.
- Hazır pazar teması veya sayfa oluşturucuya bağımlı bir kurgu kullanılmayacaktır.
- Onaylanan statik tanıtım sitesi, özel Mavi Belge WordPress temasına dönüştürülecektir.
- Meslek, yeterlilik, sektör, ücret, haber, doküman ve referans verileri özel bir `mavibelge-core` eklentisinde modellenir; temaya gömülmez.
- Mevcut canlı `mavibelge.com.tr` sitesi son yayın kabulüne kadar korunur.
- `yeni.mavibelge.com.tr` onaylanmış görsel referans olarak korunur.
- Geliştirme ve kabul ortamı canlı siteden ayrılır.
- Kodlama Claude ile aşamalı ve denetlenebilir promptlar üzerinden yapılır.
- Bu aşamada sunucu donanımı, işletim sistemi, kontrol paneli veya mail altyapısı değiştirilmeyecektir.

## 2. Sabit Üretim Ortamı

| Alan | Mevcut değer |
|---|---|
| Sunucu | Dell R210-II Dedicated Server |
| RAM | 16 GB |
| Disk | 250 GB SSD + 1 TB SATA |
| Hostname | `srv.mavibelge.com.tr` |
| Birincil IP | `5.250.247.218` |
| İlişkili IP ağı | `5.250.247.217/29` |
| Nameserver | `ns1.mavibelge.com.tr`, `ns2.mavibelge.com.tr` |
| İşletim sistemi | CentOS 7 (EOL) |
| Kontrol paneli | DirectAdmin Legacy |
| Görünen PHP seçenekleri | PHP 7.3 ve PHP 5.6 |

### 2.1. Bu ortamın proje üzerindeki zorunlu etkileri

- Üretim uyumluluk hedefi, gerçek sunucu denetimi tamamlanana kadar PHP 7.3 kabul edilir.
- Tema ve özel eklenti kodunda PHP 7.3 üzerinde çalışmayan dil özellikleri kullanılmaz.
- WordPress çekirdeği ve üçüncü taraf eklenti sürümleri tahminle seçilmez; PHP 7.3 ve mevcut veritabanı sürümüyle fiilen test edilen sürümler kilitlenir.
- Sunucuda Composer veya Node.js bulunacağı varsayılmaz. PHP bağımlılıkları ve ön yüz çıktıları geliştirme ortamında hazırlanır; üretime çalışır paket gönderilir.
- Otomatik büyük sürüm yükseltmesi yapılmaz. Tüm çekirdek/tema/eklenti güncellemeleri önce eş üretim ortamında test edilir.
- CentOS 7'nin EOL ve PHP 7.3'ün eski olması çözülmüş kabul edilmez; risk kaydında açık tutulur.
- Eski altyapı nedeniyle güvenlik yalnız WordPress eklentilerine bırakılamaz. Sunucu, dosya izinleri, erişim, yedekleme ve izleme kontrolleri ayrıca yürütülür.

## 3. Hedef WordPress Mimarisi

```text
WordPress
├── wp-content/themes/mavibelge/
│   ├── Onaylı tasarım sistemi ve şablonlar
│   ├── Gutenberg blok stilleri
│   ├── Erişilebilir/responsive bileşenler
│   └── Derlenmiş, üretime hazır CSS/JS
├── wp-content/plugins/mavibelge-core/
│   ├── İçerik türleri ve taksonomiler
│   ├── Alan doğrulamaları ve ilişkiler
│   ├── Ücret/yeterlilik yönetimi
│   ├── Arama ve filtreleme servisleri
│   ├── İçe/dışa aktarma
│   └── Yetki ve işlem kayıtları
└── Sınırlı üçüncü taraf eklentiler
    ├── Özel alan yönetimi
    ├── SEO
    ├── Form/SMTP
    ├── Önbellek
    └── Güvenlik/işlem günlüğü
```

### 3.1. Mimari ilkeler

- İçerik ve iş kuralları temadan bağımsızdır.
- Tema değiştirilse bile kurumsal veriler kaybolmaz.
- Aynı işi yapan birden fazla eklenti kurulmaz.
- Elementor, WPBakery veya benzeri sayfa oluşturucular kullanılmaz.
- Ana sayfa kontrollü alanlardan yönetilir; editör temel yerleşimi yanlışlıkla bozamaz.
- WordPress multisite kullanılmaz.
- Üretimde WordPress dosya editörü kapalı tutulur.
- Kullanıcı yüklemelerinde dosya türü, boyut ve yetki kontrolü uygulanır.

## 4. İçerik Modeli

### 4.1. Standart sayfalar

Kurumsal ve bilgilendirici sayfalar WordPress `page` yapısında tutulur. Özel alanlar yalnız gerçekten ihtiyaç duyulan sayfalara eklenir.

### 4.2. Özel içerik türleri

| İçerik türü | Temel alanlar | İlişkiler |
|---|---|---|
| Yeterlilik/Meslek | Ad, MYK kodu, seviye, revizyon, açıklama, durum | Sektör, ücret tarifesi, doküman |
| Ücret tarifesi | Dönem, KDV durumu, belge bedeli, fiyat seçenekleri, kaynak | Yeterlilik/Meslek |
| Haber | Başlık, özet, içerik, görsel, yayın/güncelleme tarihi | Kategori, yazar, kontrol eden |
| Doküman | Başlık, dosya, sürüm, geçerlilik/yayın tarihi | Kategori, yeterlilik |
| Referans | Kurum adı, logo, bağlantı, sıralama, gerçek/temsili durumu | Yok |
| Lokasyon | Adres, telefon, harita, çalışma saatleri | Yok |
| SSS | Soru, cevap, kategori, sıralama | Sayfa/yeterlilik ilişkisi |

Sektörler, meslekleri sınıflandıran hiyerarşik bir taksonomi olarak tasarlanır. Nihai model ilk Claude kodlama görevinden önce örnek veriyle doğrulanır.

### 4.3. Ücret yönetimi

- Tek ve çok seçenekli fiyatları destekler.
- Tarife dönemi ve geçerlilik tarihi saklanır.
- Eski tarifeler silinmeden pasife alınabilir.
- CSV içe/dışa aktarma sağlanır.
- Toplu güncelleme kontrollü ve geri alınabilir olur.
- Değişikliği yapan kullanıcı ve zaman kaydedilir.
- Ziyaretçi yalnız yayınlanmış güncel tarifeyi görür.

## 5. Yönetim Paneli ve Roller

| Rol | Yetki |
|---|---|
| Sistem yöneticisi | Tema/eklenti/yapılandırma ve tüm içerikler |
| Site yöneticisi | İçerik, ücret, menü ve yayın yönetimi; sistem dosyalarına erişim yok |
| İçerik editörü | Sayfa, haber ve doküman taslağı oluşturma/düzenleme |
| Fiyat editörü | Ücret tarifelerini düzenleme; yayınlama onaya bağlı |
| Kontrol eden/onaylayan | İçeriği inceleme, onaylama ve yayına alma |

- Ortak yönetici hesabı kullanılmaz.
- Her kullanıcı için ayrı hesap açılır.
- Yönetici ve onaylayan rollerinde iki aşamalı doğrulama zorunludur.
- Kritik ayar, ücret ve yayın işlemleri denetim günlüğüne yazılır.

## 6. Formlar ve Kişisel Veriler

Formlar iki sınıfa ayrılır:

1. Düşük riskli: iletişim, genel sınav talebi.
2. Hassas: online başvuru, iş başvurusu, itiraz/şikâyet ve dosya yükleme.

Hassas formlar için alan listesi, hukuki dayanak, aydınlatma/onay metni, saklama süresi, erişebilecek roller ve silme prosedürü kurum tarafından onaylanmadan canlı veri toplanmaz. Dosyalar doğrudan tahmin edilebilir medya URL'leriyle yayımlanmaz.

## 7. SEO, AIO/GEO ve URL Yönetimi

- Eski URL envanteri sitemap, sunucu logları, Search Console, analitik ve backlink kaynakları birleştirilerek çıkarılır.
- Her eski URL için `301`, `410`, korunacak, incelenecek veya noindex kararı verilir.
- Title, meta description, canonical, Open Graph ve robots alanları yönetilir.
- XML sitemap içerik türlerine göre üretilir.
- `Organization`, `WebSite`, `WebPage`, `BreadcrumbList`, `NewsArticle`, görünürse `FAQPage` gibi şemalar merkezi kurallarla üretilir.
- Editörlere kontrolsüz serbest JSON-LD alanı verilmez.
- Filtre ve arama sonuçlarının indeksleme politikası ayrıca tanımlanır.
- `cms-yeni` ve diğer test ortamları parola korumalı ve `noindex` olur.

## 8. Performans Yaklaşımı

- Tema çıktısı hafif ve sunucu tarafında üretilmiş HTML olur.
- Görseller WebP/uygun format, ölçü ve `srcset` ile servis edilir.
- Kritik olmayan JavaScript ertelenir; bağımlılık sayısı düşük tutulur.
- Sayfa ve tarayıcı önbelleği kullanılır.
- Veritabanı sorguları ve WordPress transient kullanımı ölçülür.
- CDN ancak DNS/mail kayıtları korunarak ve açık onayla devreye alınır.
- Hedefler: mobil yatay taşma sıfır; temel sayfalarda Core Web Vitals için ölçülebilir kabul kriterleri; görsel kalite kaybı olmadan makul sayfa ağırlığı.

## 9. Güvenlik ve Bakım

### 9.1. WordPress katmanı

- Resmî WordPress dağıtımı kullanılır.
- Tema/eklenti kaynakları ve lisansları kayıt altına alınır.
- Kullanılmayan tema ve eklentiler silinir.
- `DISALLOW_FILE_EDIT` etkinleştirilir.
- Giriş denemeleri sınırlandırılır ve 2FA uygulanır.
- XML-RPC gerekmiyorsa kapatılır veya sınırlandırılır.
- Upload dizininde PHP çalıştırılması engellenir.
- Güvenlik başlıkları ve HTTPS yönlendirmesi doğrulanır.
- Nonce, capability, veri temizleme ve çıktı kaçış kuralları özel kod kalite kapısıdır.

### 9.2. Eski sunucu kısıtı altında bakım

- Değişiklikler doğrudan canlıda yapılmaz.
- Güncellemeler önce PHP 7.3 ve üretimle eş veritabanı sürümünde denenir.
- Güncelleme öncesi dosya ve veritabanı yedeği alınır.
- Başarısız güncelleme için belgelenmiş geri dönüş paketi hazırlanır.
- CentOS 7/PHP 7.3 kaynaklı giderilemeyen açık veya uyumsuzluk ortaya çıkarsa yayın durdurulur; sorun tema/eklenti sürümünü düşürerek gizlenmez.

## 10. Yedekleme Politikası

Mail arşivleri kurum tarafından harici disklere alınabilir; bu işlem WordPress ve sunucu yedeğinin yerine geçmez.

- WordPress veritabanı: günlük.
- `wp-content/uploads`: günlük artımlı, haftalık tam.
- Özel tema ve eklenti: Git deposu + dağıtım paketi.
- WordPress yapılandırması ve gerekli sunucu kuralları: değişiklikte yedek.
- En az iki yerel harici disk dönüşümlü kullanılır; tek diske güvenilmez.
- Bir kopya sunucudan ve çalışma bilgisayarından fiziksel olarak ayrı tutulur.
- Mail sunucudan ancak iki doğrulanmış arşiv kopyası oluştuktan sonra silinir.
- Aylık örnek geri yükleme testi yapılır.

## 11. Ortamlar ve Dağıtım

| Ortam | Amaç | Kural |
|---|---|---|
| Yerel geliştirme | Tema/eklenti kodlama ve otomatik test | Üretim PHP/veritabanı sürümünü taklit eder |
| `yeni.mavibelge.com.tr` | Onaylanan statik görsel referans | WordPress kurulumu ile üzerine yazılmaz |
| `cms-yeni.mavibelge.com.tr` | WordPress kabul/staging | Parola korumalı, noindex, gerçek form gönderimi kapalı |
| `mavibelge.com.tr` | Üretim | Kabul ve geri dönüş planından sonra değiştirilir |

Sunucuda Git/Composer/Node zorunlu tutulmaz. Derleme ve kalite kontrolleri yerelde yapılır; yalnız üretim paketi SFTP/DirectAdmin üzerinden yüklenebilir. Şifreler ve `wp-config.php` Git'e girmez.

## 12. Agent Mimarisi

| Agent | Tek sahibi olduğu alan | Teslimi |
|---|---|---|
| Ana orkestratör | Görev sırası, ortak dosya entegrasyonu, karar kaydı | Birleştirilmiş sürüm ve ilerleme raporu |
| WordPress çekirdek agentı | `mavibelge-core` eklentisi ve iş kuralları | CPT/taksonomi/alan/REST ve yönetim işlevleri |
| Tema ve arayüz agentı | `mavibelge` teması | Onaylı tasarımla uyumlu şablon ve bileşenler |
| Veri/içerik agentı | Veri eşleştirme ve aktarım dosyaları | Doğrulanmış içerik ve import raporu |
| SEO/AIO agentı | SEO alanları, sitemap, schema ve yönlendirme kararları | Teknik SEO kabul raporu |
| Güvenlik agentı | Kod güvenliği, roller, formlar ve kişisel veri kontrolleri | Güvenlik bulgu/kabul raporu |
| DevOps agentı | Ortam, paketleme, yükleme, yedek ve geri dönüş | Dağıtım prosedürü |
| QA agentı | Görsel, işlevsel, responsive, erişilebilirlik ve regresyon | Kanıtlı test raporu |

Bir agent başka bir agentın sahip olduğu dosyayı değiştirmez. Ortak dosyalar yalnız orkestratör tarafından birleştirilir. Her Claude promptunda izin verilen dosyalar, yasak alanlar, girdiler, testler ve teslim raporu biçimi açıkça yazılır.

## 13. Claude Prompt Sırası

Kodlama tek bir dev promptla yapılmayacaktır. Önerilen sıra:

1. **Faz 0 — Repo ve sunucu uyumluluk denetimi:** Değişiklik yapmadan envanter ve risk raporu.
2. **Faz 1 — WordPress iskeleti:** Tema/eklenti klasörleri, kod standartları, geliştirme ortamı ve test yapısı.
3. **Faz 2 — İçerik modeli:** CPT, taksonomi, alan şeması, roller ve doğrulamalar.
4. **Faz 3 — Temel tema:** Header/footer, tasarım tokenları, global bileşenler.
5. **Faz 4 — Sayfa şablonları:** Ana sayfa ve 41 sayfanın WordPress karşılıkları.
6. **Faz 5 — Meslek/sektör/ücret:** Arama, filtreleme, detay ve yönetim deneyimi.
7. **Faz 6 — Veri aktarımı:** Statik kaynaklardan doğrulanmış WordPress importu.
8. **Faz 7 — Haber/doküman/referans/lokasyon:** Yönetim ve ön yüz modülleri.
9. **Faz 8 — Formlar:** Onaylanan alanlar, KVKK, spam ve güvenli dosya yükleme.
10. **Faz 9 — SEO/AIO ve eski URL'ler:** Meta, schema, sitemap ve yönlendirmeler.
11. **Faz 10 — Performans ve güvenlik:** Cache, medya, hardening ve denetim.
12. **Faz 11 — QA ve içerik kabulü:** Görsel karşılaştırma, responsive, bağlantı, veri ve erişilebilirlik testleri.
13. **Faz 12 — Staging dağıtımı:** Paket, yedek, yükleme ve smoke test.
14. **Faz 13 — Canlıya geçiş:** Bakım penceresi, son senkronizasyon, 301, form/mail testleri ve geri dönüş.

Her faz bağımsız commit ile tamamlanır. Bir fazın kabul kriterleri geçmeden sonraki faza başlanmaz.

## 14. Kalite Kapıları

- PHP 7.3 sözdizimi uyumluluğu otomatik kontrol edilir.
- WordPress coding standards ve güvenlik kontrolleri geçer.
- PHP/JavaScript hatası ve tarayıcı konsol hatası bulunmaz.
- Yerel bağlantı ve medya yolları doğrulanır.
- 375, 390, 768, 1024 ve 1440 piksel genişliklerinde test yapılır.
- Klavye erişimi, odak durumu, form etiketleri ve renk kontrastı kontrol edilir.
- 83 yeterlilik ve ücret kayıtları kaynak verilerle karşılaştırılır.
- Gerçek ve temsili referanslar açıkça ayrılır.
- Staging arama motoruna kapalıdır.
- Yedekten geri dönüş denenmeden canlı yayın yapılmaz.

## 15. Faz 0'da Cevaplanacak Açık Noktalar

- Üretimdeki kesin WordPress, PHP, web sunucusu ve MySQL/MariaDB sürümleri.
- PHP 7.3'te etkin uzantılar ve limitler.
- Aktif mail kutularının toplam boyutu ve inode sayısı.
- SSD/SATA disklerin görevleri ve gerçek boş alanı.
- Sunucu yedekleme ve geri yükleme imkânı.
- Mevcut sitenin tema, eklenti ve özel kod envanteri.
- Mevcut URL, form, analitik ve Search Console kayıtları.
- Kurumun onaylayacağı gerçek referans logoları.
- Formların hangi verileri saklayacağı ve saklama süreleri.

## 16. Canlı Yayın Kabul Kriteri

Yeni WordPress sitesi ancak aşağıdakilerin tamamı sağlanınca `mavibelge.com.tr` üzerinde yayına alınır:

- Kurum görsel ve içerik kabulü.
- Kritik güvenlik bulgusu bulunmaması.
- Ücret/yeterlilik eşleştirmesinin doğrulanması.
- Form ve mail teslim testlerinin geçmesi.
- Eski URL yönlendirme planının uygulanması.
- Mobil/masaüstü regresyon testlerinin geçmesi.
- Tam yedek ve kanıtlanmış geri dönüş paketi.
- Canlı geçiş görev/sorumluluk listesinin onaylanması.

