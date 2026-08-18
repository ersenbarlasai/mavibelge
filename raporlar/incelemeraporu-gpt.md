# Mavi Belge Web Sitesi Yeniden Yapılandırma İnceleme Raporu

**İncelenen site:** https://mavibelge.com.tr/  
**İnceleme tarihi:** 17 Ağustos 2026  
**Raporun amacı:** Mevcut sitedeki bilgi, belge ve iş akışlarını kaybetmeden; daha anlaşılır, hızlı, erişilebilir, güvenli ve yönetilebilir yeni bir PHP tabanlı web sitesinin bilgi mimarisini ve teknik temelini belirlemek.

---

## 1. Yönetici özeti

Mevcut Mavi Belge sitesi, içerik bakımından zengin fakat bilgi mimarisi bakımından parçalıdır. Aynı işleve ait bağlantılar birden fazla yerde tekrarlanmakta; bazı meslekler bağımsız detay sayfasına, bazıları yalnızca PDF dosyasına, bazıları ise yanlış veya ortak bir detay sayfasına bağlanmaktadır. Ana menü, ikinci hızlı menü, üst ikonlar, sayfa içi sektör bağlantıları, haber yan alanı ve footer aynı içeriğe farklı yollarla ulaşmaya çalıştığı için kullanıcı nereden başlaması gerektiğini kolayca anlayamamaktadır.

Teknik incelemede site şu anda WordPress 6.8.8, özel bir `MaviBelge` teması, WPBakery, Contact Form 7, Bootstrap 4.3.1, jQuery ve birden fazla slider/logo eklentisi kullanmaktadır. Ana sayfa HTML çıktısı yaklaşık 115 KB'dir ve yalnızca ilk yükte 135 ayrı statik varlık bağlantısı üretmektedir. 145 temel içerik URL'sinin tamamı HTTP 200 yanıtı vermektedir; ayrıca 54 referans-logo kaydı, 2 kategori arşivi, 38 etiket arşivi ve 1 yazar arşivi sitemap içinde görünmektedir.

Yeni site için önerim, doğrudan “düz PHP sayfalar” değil, **Laravel tabanlı modüler bir PHP uygulaması**dır. Böylece admin paneli, rol/yetki, form kayıtları, dosya yönetimi, içerik versiyonlama, güvenlik, yönlendirme ve SEO ihtiyaçları kontrol edilebilir olur. Arayüz Blade tabanlı sunucu tarafı render edilmeli; yalnızca filtre, modal ve küçük etkileşimlerde hafif JavaScript kullanılmalıdır. Bu proje için ağır bir tek sayfa uygulaması (SPA) zorunlu değildir.

En önemli yeniden yapılanma kararı şudur:

> “Mesleklerimiz” alanı serbest metin sayfalarından çıkarılmalı; `Sektör → Yeterlilik Programı → Seviye/Revizyon → Program dokümanı → Başvuru` ilişkisiyle veritabanında tutulmalıdır.

Bu yapı kurulursa, hem kullanıcı doğru mesleği birkaç adımda bulabilir hem de yönetici yeni bir yeterlilik, revizyon, ücret veya PDF eklediğinde menüler ve liste sayfaları otomatik güncellenir.

---

## 2. İnceleme yöntemi ve kapsamı

İnceleme aşağıdaki kaynaklar üzerinden yapılmıştır:

- Ana sayfanın masaüstü görünümü ve gerçek DOM yapısı.
- Üst menü, açılır menüler, hızlı işlem şeridi, footer ve sayfa içi bağlantılar.
- WordPress sitemap dosyaları:
  - `/wp-sitemap-posts-page-1.xml`
  - `/wp-sitemap-posts-post-1.xml`
  - `/wp-sitemap-posts-logosliderwp-1.xml`
  - kategori, etiket ve kullanıcı sitemap'leri.
- 114 sabit sayfa ve 31 yazı olmak üzere 145 temel içerik URL'sinin HTTP erişilebilirliği.
- Kurumsal, mevzuat, sınav süreci, başvuru, fiyat, banka, meslek kategori/detay, haber, iletişim, KVKK, insan kaynakları ve belge sayfalarından temsilî örneklerin ayrıntılı DOM incelemesi.
- 12 meslek/ sektör sayfasındaki bütün görünen yeterlilik kayıtları.
- Ana sayfadaki form, resim, başlık, SEO, erişilebilirlik ve güvenlik başlıkları.

### İncelemenin sınırları

- WordPress yönetim paneline giriş yapılmadı; dolayısıyla yönetim tarafındaki mevcut özel alanlar ve editör iş akışı doğrudan görülmedi.
- Formlar gerçek kişisel veri gönderilmeden incelendi; başvuru veya iletişim kaydı oluşturulmadı.
- WordPress REST API güvenlik eklentisi tarafından 401 ile engellendi. İçerik envanteri sitemap ve herkese açık sayfalar üzerinden çıkarıldı.
- Ücret, mevzuat, yetki ve KVKK metinleri hukuki doğruluk bakımından onaylanmış kabul edilmemelidir. Bunlar yayına geçmeden önce Mavi Belge'nin yetkili birimlerince güncellik kontrolünden geçirilmelidir.

---

## 3. Mevcut sitenin teknik profili

### 3.1. Altyapı

| Katman | Tespit |
|---|---|
| CMS | WordPress 6.8.8 |
| Tema | Özel `MaviBelge` teması |
| Sayfa oluşturucu | WPBakery Page Builder |
| CSS çatısı | Bootstrap 4.3.1 + özel tema CSS'i |
| JavaScript | jQuery, jQuery Migrate, Popper, Bootstrap JS |
| Formlar | Contact Form 7 + Material Design eklentisi + Akismet |
| Slider | MetaSlider/FlexSlider |
| Referans logoları | Logo Slider WP ve Super Logo Showcase |
| Açılır pencere | Halfdata Green Popups |
| Web sunucusu | LiteSpeed |
| Yazı tipi / ikon | Google Fonts Montserrat, Font Awesome 4/5 karışımı |

### 3.2. Teknik yük ve bakım etkisi

- Ana sayfa kaynak HTML'i yaklaşık **115.600 bayt**.
- Ana sayfada yaklaşık **135 statik varlık bağlantısı** bulundu.
- Bunların en az 5'i üçüncü taraf CDN'lerden yükleniyor.
- Aynı iş için iki ayrı logo slider eklentisi kullanıldığı görülüyor.
- jQuery'nin hem WordPress sürümü hem başka bir CDN sürümü sayfada bulunabiliyor; bu durum gereksiz yük ve çakışma riski yaratır.
- Bootstrap 4.3.1 ve Font Awesome 5.7.2 güncel tasarım ve güvenlik yaklaşımına göre eski bir temel oluşturuyor.
- Tema CSS dosyasında yalnızca birkaç mobil medya kuralı var. Bir mobil blokta geçersiz iç içe CSS yapısı (`.myklogo { .baslik img { ... } }`) bulunuyor.
- Ana sayfa `Cache-Control: no-store, must-revalidate, no-cache` döndürüyor; kamuya açık içerik için bu, CDN ve tarayıcı önbelleğinin faydasını azaltır.

### 3.3. Güvenlik başlıkları

Ana sayfa yanıtında aşağıdaki başlıklar görülmedi:

- `Strict-Transport-Security`
- `Content-Security-Policy`
- `X-Content-Type-Options`
- `X-Frame-Options`
- `Referrer-Policy`
- `Permissions-Policy`

Bu, tek başına aktif bir güvenlik açığı kanıtı değildir; ancak yeni sitede güvenlik başlıkları, oturum çerezleri, CSRF, dosya yükleme denetimleri ve oran sınırlaması başlangıçtan itibaren uygulanmalıdır.

---

## 4. Mevcut bilgi mimarisi

### 4.1. Üst bölüm

Masaüstü üst alanı üç ayrı gezinme seviyesinden oluşuyor:

1. Logo ve işlem ikonları:
   - Başvuru Formu
   - Sınav Sonucu
   - Belge Sorgula
   - Sınav Takvimi
   - Europass
   - MYK
   - TÜRKAK
2. Ana menü:
   - Anasayfa
   - Kurumsal
   - Mesleki Yeterlilik
   - Sınav Belgelendirme
   - Mesleklerimiz
   - Medya
   - Referanslarımız
   - İnsan Kaynakları
   - İletişim
3. İkinci hızlı menü:
   - Sınav Ücretleri
   - Banka Hesap Bilgileri
   - Sınav Sonuçları
   - Belge Yenileme Süreci
   - Online Başvuru
   - Neden Zorunlu?
   - Yetki Belgelerimiz

Bu yapı masaüstünde bütün seçenekleri aynı anda gösteriyor ve bilişsel yük oluşturuyor. Bir kullanıcı açısından “Başvuracağım”, “Sonucuma bakacağım” ve “Bir meslek hakkında bilgi alacağım” görevleri ile kurumsal içerikler birbirine karışıyor.

### 4.2. Ana menünün ayrıntılı sınıflandırması

#### Kurumsal

- Mavi Belge Hakkında
- Misyon & Vizyon
- Kalite Politikamız
- Üst Yönetimin Tarafsızlık Beyanı
- Yasal Dayanağımız
- Gizlilik Politikamız
- KVKK

#### Mesleki Yeterlilik

- Mesleki Yeterlilik Kurumu (MYK)
- Türk Akreditasyon Kurumu (TÜRKAK)
- Ulusal Meslek Standartları (UMS) ve Ulusal Yeterlilikler (UY)
- Mevzuat

#### Sınav Belgelendirme

- Sınav ve Belgelendirme Süreci
- Sınav Ücretleri
- Sınav ve Belge Kuralları
- Öneri, İtiraz ve Şikayetler
- Aday Taahhütnamesi (doğrudan PDF)
- Belge İptali ve Askıya Alma
- Logo Kullanım Talimatı

#### Mesleklerimiz

- Makine
- Metalurji
- Metal
- Ulaştırma & Lojistik / Liman
- Plastik
- Elektrik
- Cam
- Tekstil
- İnşaat
- Mobilya
- Maden & Mermer
- Güzellik ve Saç Bakım

#### Medya

- Haberler
- Katalog 2022 (doğrudan PDF)

#### Diğer

- Referanslarımız
- İnsan Kaynakları → İş Başvurusu
- İletişim

### 4.3. Footer

Footer üç sütundan oluşuyor:

- Kısa “Mavi Belge Hakkında” metni ve 2016 tarihli telif satırı.
- Yetkili olunan meslek sektörlerinin tekrar listesi.
- Merkez adresi, üç telefon numarası, e-posta ve sosyal medya ikonları.

Footer'daki sektör listesi ana menüdeki içeriği yeniden tekrar ediyor. Yeni tasarımda footer yalnızca temel kurumsal bağlantılar, iletişim, yasal metinler ve kısa işlem bağlantıları içermelidir.

---

## 5. Ana sayfa incelemesi

### 5.1. Mevcut akış

Ana sayfanın görünen sırası şöyledir:

1. Geniş logo/akreditasyon üst alanı.
2. Dört işlem ikonu ve dış kurum logoları.
3. Ana menü.
4. Hızlı işlem şeridi.
5. Büyük görsel slider.
6. Slider'ın yanında “Sorum Var” formu.
7. Haber/duyuru kartları.
8. Referans logoları slider'ı.
9. Footer.

### 5.2. Güçlü yönler

- Başvuru, sonuç, belge sorgulama ve sınav takvimi işlemleri üst alanda görünür.
- MYK ve TÜRKAK yetkisi kurumsal güven unsuru olarak hemen fark ediliyor.
- Gerçek referans kurumlarının kullanılması sosyal kanıt oluşturuyor.
- İletişim bilgileri footer'da erişilebilir.

### 5.3. Sorunlar

- Üst alan yüksek ve kalabalık; kullanıcı esas içeriğe geç ulaşır.
- Dört ana işlem hem ikonlarda hem ikinci menüde tekrar edilir.
- Slider görselleri kullanıcıyı belirli bir göreve yönlendirmiyor.
- “Sorum Var” formunda alanların programatik etiketleri, `required` işaretleri ve erişilebilir açıklamaları bulunmuyor.
- Haber kartlarında metinler bozuk karakterle kesilebiliyor (`ZORUNLULU�...`).
- Ana sayfada anlamlı bir H1 başlığı ve net bir değer önerisi yok.
- Yetkili sektör ve meslekleri bulmak için doğrudan arama/filtre alanı yok.
- Fiyat bilgileri metin/tablo yerine yüksek çözünürlüklü görseller olarak sunuluyor.

### 5.4. Yeni ana sayfa önerisi

Önerilen sıra:

1. İnce güven bandı: “MYK Yetkili Belgelendirme Kuruluşu YB-0052 / TÜRKAK AB-0104-P”.
2. Sade header: logo, ana menü, “Online Başvuru” birincil butonu.
3. Hero:
   - H1: “Mesleki yeterliliğinizi belgelendirin”
   - Kısa açıklama
   - “Mesleğimi Bul” ve “Online Başvuru” çağrıları
4. Dört görev kartı:
   - Mesleğimi Bul
   - Sınav Takvimi
   - Sonuç / Belge Sorgula
   - Belge Yenile
5. Aranabilir sektör ve yeterlilik alanı.
6. “Nasıl işler?”: Başvuru → Sınav → Değerlendirme → Belgelendirme.
7. Güncel duyurular; eski haberlerden ayrılmış kritik bildirimler.
8. Kurumsal güven: yetki belgeleri, rakamsal göstergeler, lokasyonlar.
9. Seçili referanslar.
10. İletişim/teklif çağrısı ve sade footer.

---

## 6. İçerik envanteri

### 6.1. Sayısal envanter

| İçerik türü | Adet | Not |
|---|---:|---|
| WordPress sabit sayfası | 114 | Ana sayfa ve meslek detayları dahil |
| Haber/yazı | 31 | 2016–2023 ağırlıklı; bazıları 2026'da güncellenmiş |
| Temel içerik URL toplamı | 145 | İnceleme sırasında tamamı HTTP 200 |
| Referans-logo özel kaydı | 54 | Tek tek sitemap'te yayınlanıyor |
| Kategori arşivi | 2 | `haber`, `uncategorized` |
| Etiket arşivi | 38 | Çoğu düşük değerli veya tekil |
| Yazar arşivi | 1 | Kullanıcı adı URL'de açık |

### 6.2. İçerik türlerine göre gerçek ayrım

Mevcut WordPress'te çok farklı işlevler “sayfa” türü altında tutuluyor. Yeni sistemde aşağıdaki ayrım yapılmalıdır:

1. Kurumsal sayfa
2. Sektör
3. Yeterlilik programı / meslek
4. Program revizyonu ve seviye
5. Doküman
6. Ücret tarifesi
7. Duyuru
8. Haber
9. Sınav/lokasyon bağlantısı
10. Referans
11. Başvuru formu ve form kaydı
12. İletişim lokasyonu
13. SSS
14. Yasal metin

Bu ayrım yapılmadığında admin paneli yine “hangi sayfayı nereden güncelleyeceğiz?” problemine dönüşür.

---

## 7. Meslek ve yeterlilik mimarisi

Mevcut sitede 12 sektör başlığı altında **83 görünen yeterlilik kaydı** tespit edilmiştir. Bunların bir kısmı bağımsız HTML detay sayfasına, bir kısmı doğrudan PDF'ye, bir kısmı ise aynı ortak detay URL'sine bağlıdır.

### 7.1. Makine — 13 kayıt

- 10UY0002-3/03 Makine Bakımcı
- 10UY0002-4/03 Makine Bakımcı
- 10UY0002-5/03 Makine Bakımcı
- 12UY0105-3/00 Makine Montajcısı
- 12UY0083-3/02 Metal Kesimci
- 12UY0084-4/02 Metal Kesim Operatörü
- 12UY0086-3/02 Metal Levha İşleme Tezgâh İşçisi
- 12UY0087-4/02 Metal Levha İşleme Tezgâh Operatörü
- 14UY0202-3/01 NC/CNC Tezgâh İşçisi
- 14UY0202-4/01 NC/CNC Tezgâh İşçisi
- 18UY0351-4/00 Tel Makineleri Operatörü
- 15UY0227-3/00 Tornacı
- 12UY0081-3/00 Frezeci

### 7.2. Metalurji — 7 kayıt

- 13UY0148-3/01 Haddeci
- 13UY0148-4/01 Haddeci
- 12UY0070-3/00 Refrakterci
- 12UY0070-4/00 Refrakterci
- 13UY0149-4/00 İzabeci
- 13UY0178-3/00 Maçacı
- 13UY0173-4/00 Dökümcü

### 7.3. Metal — 5 kayıt

- 11UY0013-3/02 Endüstriyel Boru Montajcısı
- 11UY0010-3/04 Çelik Kaynakçısı
- 11UY0014-3/02 Alüminyum Kaynakçısı
- 11UY0016-4/03 Kaynak Operatörü
- 11UY0015-4/03 Direnç Kaynak Ayarcısı

### 7.4. Ulaştırma & Lojistik / Liman — 12 kayıt

- 15UY0218-2/01 İşaretçi
- 12UY0061-3/04 Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci)
- 12UY0088-3/04 Liman Forklift Operatörü
- 13UY0170-3/02 Liman Kuru Yük Operasyon Elemanı (Puantör)
- 13UY0145-3 Endüstriyel Taşımacı
- 15UY0220-4 Liman Operasyon Planlamacısı
- 12UY0063-3 Liman Pompa ve Tank Saha Operatörü
- 17UY0268-3 Liman RTG Operatörü
- 12UY0064-3 Liman Saha İstif Makineleri Operatörü (CRS ve ECS)
- 17UY0269-3 Liman SSG Operatörü
- 15UY0221-3 Terminal Çekici Operatörü
- 17UY0328-3 Servis Aracı Şoförü

Son sekiz kaydın çoğu bağımsız HTML detay sayfası yerine doğrudan veya dolaylı PDF içeriğine bağlıdır.

### 7.5. Plastik — 3 kayıt

- 12UY0069-3/02 Plastik Enjeksiyon Üretim Elemanı
- 13UY0143-3/01 Plastik Şişirme Film Üretim Operatörü (Ekstrüzyon)
- 13UY0142-3/01 Plastik Profil Üretim Operatörü (Ekstrüzyon)

### 7.6. Elektrik — 5 kayıt

- 12UY0075-3 Elektrik Pano Montajcısı
- 15UY0241-3 Elektrik Tesisatçısı
- 15UY0241-4 Elektrik Tesisatçısı
- 15UY0206-3 Elektro-Mekanik Montaj İşçisi
- 13UY0121-5 İşletme Elektrik Bakımcısı

Bu kayıtlar kategori sayfasında yer alıyor; bağımsız detay yapıları düzenli değil ve bazıları yalnızca PDF talimatına bağlı.

### 7.7. Cam — 3 kayıt

- 18UY0356-4 Endüstriyel Cam Isıl İşlem Elemanı
- 18UY0357-4 Endüstriyel Cam İşleme Elemanı
- 18UY0358-4 Endüstriyel Cam Kesim Elemanı

### 7.8. Tekstil — 6 kayıt

- 11UY0036-2/01 İplik Bitim İşleri Operatörü
- 11UY0037-2/01 İplik Eğirme Operatörü
- 11UY0039-3/02 Ön İplik Operatörü
- 13UY0137-3/01 Bitim İşlemleri Operatörü
- 13UY0138-3/01 Boyama Operatörü
- 13UY0139-3/01 Ön Terbiye Operatörü

### 7.9. İnşaat — 12 kayıt

- 11UY0011-3/03 Ahşap Kalıpçı
- 11UY0012-3/03 Betonarme Demircisi
- 11UY0024-3/02 Sıvacı
- 11UY0023-3/02 İnşaat Boyacısı
- B12UY0054-3/00 Alçı Levha Uygulayıcısı
- 12UY0055-3/00 Alçı Sıva Uygulayıcısı
- 12UY0048-3/01 Duvarcı
- 16UY0253-2/00 İnşaat İşçisi
- 14UY0195-3/00 PVC Doğrama Montajcısı
- 12UY0051-3/01 Seramik Karo Kaplamacısı
- 12UY0057-3/01 Isı Yalıtımcısı
- 12UY0056-3/01 İskele Kurulum Elemanı

`/insaat/` kısa URL'si İnşaat kategori sayfasına değil, İnşaat Boyacısı detay sayfasına yönlenmektedir. Yeni URL planında bu çakışma düzeltilmeli, eski URL için kontrollü 301 yönlendirmesi tanımlanmalıdır.

### 7.10. Mobilya — 3 kayıt

- 17UY0301-3/00 Ahşap Mobilya İmalatçısı
- 17UY0301-4/00 Ahşap Mobilya İmalatçısı
- 17UY0300-3/00 Mobilya Döşemecisi

### 7.11. Maden & Mermer — 7 kayıt

- 16UY0266-3/01 Mermer Doğaltaş Ocakçısı
- 17UY0315-3/00 Mermer-Doğaltaş İmalat Elemanı
- 18UY0363-4 Mekanizasyon İşçisi (Maden)
- 16UY0267-4/01 Mermer-Doğaltaş Özel İmalat Elemanı
- 18UY0379-4 Yeraltı Hazırlık İşçisi
- 18UY0379-3 Yeraltı Hazırlık İşçisi
- 16UY0265-3 Kırma Eleme Tesis Operatörü

Önemli hata: Bu başlıktaki birden fazla farklı meslek aynı “Mermer-Doğaltaş Özel İmalat Elemanı” URL'sine bağlanmaktadır. Yeni sitede her program ayrı bir kayda ve benzersiz URL'ye sahip olmalıdır.

### 7.12. Güzellik ve Saç Bakım — 7 kayıt

- 18UY0344-4/00 Epilasyon Uzmanı
- 16UY0244-4/02 Güzellik Uzmanı
- 16UY0245-4/02 Kuaför
- 17UY0280-3/01 Cilt Bakım Uygulayıcısı
- 17UY0286-3/01 Kozmetik Ürünler Tanıtım ve Uygulama Elemanı
- 16UY0242-3/02 Makyaj Uygulayıcısı
- 16UY0247-3/00 Protez Tırnak Uygulayıcısı

### 7.13. Meslek detay sayfası şablonu

Mevcut detay sayfalarında genellikle şunlar var:

- Yeterlilik kodu, seviye ve program adı.
- “Tanımı” başlığı altında açıklama.
- Ulusal yeterliliğin mevzuat ve hazırlanma bağlamı.
- “Belgelendirme Programı İnceleme Formu” PDF bağlantısı.
- Alakasız biçimde H6 içine yerleştirilmiş diğer sektör bağlantıları.
- Ortak haber yan alanı ve tekrar eden footer.

Yeni detay şablonu şu alanları içermelidir:

| Alan | Yönetilebilirlik |
|---|---|
| Yeterlilik kodu | Zorunlu, benzersiz |
| Meslek adı | Zorunlu |
| Seviye | Filtrelenebilir |
| Revizyon numarası/tarihi | Ayrı alan |
| Sektör | İlişkili kayıt |
| Kısa tanım | Kart ve SEO için |
| Ayrıntılı açıklama | Zengin metin |
| Sınav birimleri | Yapılandırılmış liste |
| Sınav koşulları | Yapılandırılmış alan |
| Belge geçerlilik süresi | Ayrı alan |
| Gözetim/yenileme bilgisi | Ayrı alan |
| Güncel ücret | Ücret tarifesine ilişki |
| Program dokümanı | Versiyonlu dosya |
| Başvuru uygunluğu | Aktif/pasif |
| İlgili sınav takvimi | Dış sistem bağlantısı veya entegrasyon |
| SEO başlığı/açıklaması | Ayrı alan |

---

## 8. Kritik işlevsel sayfalar

### 8.1. Online Başvuru

`/basvuru-formlari/` sayfası şu içerikleri sunuyor:

- F32 başvuru formu PDF'si.
- Belge Kullanım Sözleşmesi PDF'si.
- Ad soyad, e-posta, telefon, dosya yükleme, mesaj ve basit matematik doğrulaması bulunan Contact Form 7 formu.

Sorun: Kullanıcıdan meslek, seviye, sınav yeri, başvuru türü ve KVKK onayı yapılandırılmış biçimde alınmıyor. Yüklenen dosyanın türü/boyutu ve saklama politikası kullanıcıya açık değil.

Yeni başvuru akışı önerisi:

1. Başvuru türü: ilk belgelendirme / kapsam genişletme / belge yenileme.
2. Sektör ve yeterlilik seçimi.
3. Aday temel bilgileri.
4. İletişim bilgileri.
5. Gerekli belgeler ve kontrollü dosya yükleme.
6. KVKK aydınlatma metni ve açık rıza gerekip gerekmediğine ilişkin doğru hukuki ayrım.
7. Özet ekranı ve başvuru numarası.
8. Başvuru sahibine e-posta/SMS bildirimi.
9. Admin panelinde durum: yeni, inceleniyor, eksik evrak, onaylandı, reddedildi, sınava yönlendirildi.

### 8.2. Sınav Sonucu ve Belge Sorgulama

Her iki işlem de MYK portalındaki aynı dış bağlantıya gidiyor. Yeni sitede bunlar tek görev olarak “Sonuç ve Belge Sorgula” şeklinde açıklanabilir. Dış siteye geçildiği kullanıcıya belirtilmeli ve yeni sekmede açılmalıdır.

### 8.3. Sınav Takvimi

Takvim `mavibelge.pratikteorik.com` alan adına yönleniyor. Yeni sitede mümkünse API/iframe yerine güvenli bir sunucu tarafı entegrasyonu değerlendirilmelidir. Entegrasyon mümkün değilse dış bağlantı açıkça işaretlenmelidir.

### 8.4. Sınav Ücretleri

2026 ücret tarifesi dört büyük JPG sayfa, güzellik tarifesi ise iki ayrı JPG olarak yayınlanmaktadır. Bu yaklaşım:

- arama motorları tarafından okunamaz,
- ekran okuyucular için erişilemez,
- mobilde yakınlaştırma gerektirir,
- yönetimde fiyat değişikliklerini zorlaştırır,
- meslek sayfasındaki güncel ücretle otomatik bağ kurulmasını engeller.

Yeni sistemde ücretler veritabanında yapılandırılmış kayıtlar olmalı; kullanıcı sektör, meslek ve seviye ile filtreleyebilmelidir. İstenirse aynı veriden yazdırılabilir PDF otomatik üretilmelidir.

### 8.5. Banka Hesap Bilgileri

Üç ayrı ödeme hesabı bulunuyor:

- Bireysel başvuru
- Tek nokta başvurusu
- AB hibe fon ödemeleri

Bu alan hassas operasyonel içeriktir. Admin panelinde yalnızca yetkili rol güncelleyebilmeli; değişiklik geçmişi tutulmalı ve çift onay seçeneği düşünülmelidir. IBAN kopyalama butonu eklenebilir; ancak kimlik ve meslek bilgisinin dekont açıklamasına yazılması yönlendirmesi KVKK açısından yeniden gözden geçirilmelidir.

### 8.6. Belge Yenileme

Mevcut yapıda `/belge-yenileme/`, `/belge-yenileme-sureci/`, belge yenileme ücret haberi ve gözetim/yenileme duyurusu birbirinden kopuktur. `/belge-yenileme-sureci/` sayfasında başlık dışında anlamlı ana içerik görülmemektedir.

Yeni sitede tek bir “Belge Yenileme” merkezi olmalı:

- Kimler yenilemeli?
- Ne zaman başvurmalı?
- Yöntemler ve kanıtlar.
- Gerekli belgeler.
- Ücret.
- Başvuru bağlantısı.
- SSS.

### 8.7. İtiraz ve Şikayetler

Mevcut sayfa prosedür ve formu iki ayrı, bazıları `http://` olan PDF bağlantısıyla sunuyor. Yeni sitede:

- Süreç özeti,
- başvuru koşulları,
- beklenen yanıt süresi,
- indirilebilir form,
- çevrimiçi başvuru,
- takip numarası,
- admin durum yönetimi

bir arada olmalıdır. İtiraz/şikayet kayıtları normal iletişim mesajlarından ayrı ve erişimi kısıtlı tutulmalıdır.

### 8.8. İnsan Kaynakları

İş başvuru formunda ad soyad, e-posta, telefon, çalışılan firma, CV/dosya, mesaj ve matematik doğrulaması bulunuyor. Yeni sistemde iş başvuruları aday sınav başvurularından tamamen ayrı bir modül olmalıdır. CV'ler özel depolamada tutulmalı, süre sonunda otomatik silinmeli ve yalnızca İK rolü erişebilmelidir.

### 8.9. İletişim ve lokasyonlar

Mevcut sayfada üç konum var:

- İskenderun/Hatay merkez ofis.
- Payas sınav alanı.
- İzmir Aliağa sınav alanı.

Yeni modelde her lokasyonun adı, türü, açık adresi, telefonları, koordinatı, harita bağlantısı, çalışma saatleri, erişilebilirlik bilgisi ve aktif/pasif durumu ayrı tutulmalıdır.

---

## 9. Kurumsal, yasal ve belge içerikleri

### 9.1. Kurumsal içerikler

Hakkımızda metni Mavi Belge'nin 02.05.2016 tarihinde TÜRKAK tarafından akredite, 16.08.2016 tarihinde MYK tarafından yetkilendirilmiş olduğunu anlatıyor. Bu temel güven mesajı korunmalıdır; fakat uzun footer metni yerine kurumsal sayfada zaman çizelgesi ve yetki kapsamı ile sunulmalıdır.

### 9.2. Yetki belgeleri

Mevcut sayfada MYK Yetki Belgesi ve TÜRKAK Akreditasyon Belgesi görsel olarak yer alıyor. Yeni sistemde:

- belge adı,
- kurum,
- belge/kapsam numarası,
- yayın tarihi,
- geçerlilik tarihi,
- doğrulama bağlantısı,
- PDF veya yüksek çözünürlüklü belge,
- önceki sürüm/arşiv

alanları olmalıdır.

### 9.3. Mevzuat

Mevzuat sayfasında 16'ya yakın harici PDF ve kurum bağlantısı var; bağlantıların önemli bölümü `http://` ile başlıyor ve eski MYK dosya yollarına bağlı. Yeni sitede mevzuat bir bağlantı deposu şeklinde yönetilmeli; belge adı, kurum, tür, yayın tarihi, son kontrol tarihi ve aktiflik durumu tutulmalıdır. Zamanlanmış bağlantı kontrolü yapılmalıdır.

### 9.4. KVKK ve gizlilik

KVKK sayfası adaylardan alınan kimlik, iletişim, çalışma, eğitim, sağlık/engel ve IBAN verilerini; sınav kamera kayıtlarını; MYK, TÜRKAK, mali müşavir ve yazılım firması gibi alıcı gruplarını açıklıyor. Ayrıca bazı saklama sürelerinden söz ediyor.

Yeni site için gerekenler:

- Başvuru, iletişim, iş başvurusu ve çerez süreçleri için ayrı veri işleme envanteri.
- Her formda amaca uygun aydınlatma metnine bağlantı.
- Gereksiz veri toplamama ilkesi.
- Dosya ve CV saklama süresi.
- Kayıt silme/anonimleştirme işi.
- KVKK başvuru kanalı.
- Yetki bazlı erişim ve işlem kaydı.
- Çerez tercih merkezi; zorunlu olmayan analitik/pazarlama çerezleri için izin.

Mevcut metin yeni sisteme doğrudan kopyalanmadan önce hukuk ve kalite birimlerince güncellenmelidir.

---

## 10. Haber, duyuru ve referans yapısı

### 10.1. Haberler

Sitede 31 yazı vardır. İçerikler; kurumsal haber, eğitim, fuar, mevzuat duyurusu, belge zorunluluğu, ücret duyurusu ve afet bilgilendirmesi gibi farklı türleri tek listede toplar.

Örnek başlık grupları:

- MYK belge zorunluluğu ve mevzuat değişiklikleri.
- Belge yenileme/gözetim ve ücret duyuruları.
- Eğitim, seminer ve çalıştaylar.
- Fuar ve medya katılımları.
- Yeni yetki/kapsam haberleri.
- Sosyal sorumluluk ve afet bilgilendirmeleri.

Yeni modelde “Duyurular” ve “Haberler” ayrılmalıdır. Duyurulara başlangıç/bitiş tarihi, önem seviyesi ve hedef kitle eklenmelidir. Süresi geçen kritik duyurular ana sayfadan otomatik kalkmalı, arşivde kalmalıdır.

### 10.2. Referanslar

Sitemap'te 54 ayrı logo kaydı bulunuyor. Ana sayfada 151 resmin 145'inde boş `alt` değeri görüldü; referans logolarının büyük bölümü buna dahildir. Bazı logolar yüklenmediğinde boyutları sıfır olarak görünmektedir.

Yeni referans modülü:

- kurum adı,
- logo,
- sektör,
- web sitesi,
- sıralama,
- öne çıkarma,
- yayın izni/onay durumu,
- erişilebilir `alt` metni

alanlarını içermelidir. Her logo için ayrı kamuya açık içerik sayfası üretilmemelidir; bu 54 düşük değerli sitemap URL'si kaldırılmalı veya `noindex` yapılmalıdır.

---

## 11. Kullanılabilirlik değerlendirmesi

### 11.1. Temel kullanıcı grupları

1. Belge almak isteyen bireysel aday.
2. Belgesini yenilemek isteyen belgeli kişi.
3. Personeli için toplu sınav planlayan işveren/kurum.
4. Sınav sonucu veya belge doğrulamak isteyen kişi.
5. İtiraz/şikayet yapmak isteyen aday.
6. Mevzuat veya yetki kapsamı inceleyen denetçi/kurum.
7. İş başvurusu yapmak isteyen aday.
8. İçeriği yöneten Mavi Belge çalışanı.

Mevcut menü kurumsal iç sınıflandırmayı yansıtıyor; kullanıcı görevlerini yansıtmıyor. Yeni menü görev odaklı olmalıdır.

### 11.2. Önerilen ana menü

- **Meslekler ve Belgeler**
  - Tüm Meslekler
  - Sektörler
  - Belge Yenileme
- **Sınav ve Başvuru**
  - Nasıl Başvururum?
  - Online Başvuru
  - Sınav Takvimi
  - Ücretler
  - Sonuç ve Belge Sorgulama
- **Kurumsal**
  - Hakkımızda
  - Yetki ve Akreditasyon
  - Politikalar
  - Referanslar
- **Bilgi Merkezi**
  - Duyurular
  - Haberler
  - Mevzuat ve Dokümanlar
  - Sık Sorulan Sorular
- **İletişim**

Header'da ayrıca tek, belirgin **Online Başvuru** butonu olmalıdır.

### 11.3. Arama ve filtre

Meslek bulucu en kritik kullanılabilirlik bileşenidir. Arama şu alanlarda çalışmalıdır:

- meslek adı,
- yeterlilik kodu,
- sektör,
- seviye,
- anahtar kelime.

Sonuç kartında kod, ad, seviye, sektör, ücret, aktiflik ve “Detay / Başvur” işlemleri görünmelidir.

---

## 12. Erişilebilirlik değerlendirmesi

Ana sayfada yapılan temel ölçüm:

- 151 resmin 145'inde boş `alt` metni.
- 118 resimde HTML `width`/`height` niteliği yok.
- Anlamlı H1 başlığı yok.
- Form alanlarında programatik label, `aria-label` ve gerçek `required` bilgisi yok.
- İçeriğe atlama bağlantısı yok.
- Bazı ikon butonlarının erişilebilir adı yok.
- Renk, küçük metin ve yoğun büyük harf kullanımı okunabilirliği zorlaştırıyor.

Yeni site için hedef **WCAG 2.2 AA** olmalıdır:

- Her sayfada tek H1 ve mantıklı başlık sırası.
- Klavye ile tam kullanım ve görünür odak.
- Form hata özetleri ve alan bazlı açıklamalar.
- Renk kontrastı ve yalnızca renge dayanmayan durum göstergeleri.
- Anlamlı görsellere açıklayıcı alt metin; dekoratif görsellere `alt=""`.
- PDF'lerin mümkün olduğunca HTML karşılığı.
- Mobilde en az 44×44 piksel etkileşim alanı.
- Hareket azaltma tercihi ve otomatik slider kontrolü.

---

## 13. SEO değerlendirmesi

145 temel içerik URL'si üzerinde yapılan kontrolde:

- Bütün sayfalar aynı `<title>` değerini kullanıyor: “Mavi Belge Mesleki Yeterlilik ve Belgelendirme”.
- 145 sayfanın tamamında meta description eksik.
- 144 sayfada H1 yok; kalan tek H1 anlamlı sayfa başlığı değil, referans alanındaki “Browser not supported” geri dönüş metni.
- Ana sayfada Open Graph, Twitter Card ve JSON-LD yapılandırılmış veri bulunmuyor.
- Ana sayfada canonical mevcut.
- Etiket, yazar ve logo özel kayıtları düşük değerli çok sayıda sitemap URL'si üretiyor.

Yeni sitede:

- Benzersiz title ve description.
- Temiz H1–H2 hiyerarşisi.
- `Organization`, `LocalBusiness`, `BreadcrumbList`, `Article`, `FAQPage` ve uygun olduğunda hizmet şemaları.
- Otomatik XML sitemap ayrımları.
- Düşük değerli filtre ve admin sayfalarında `noindex`.
- Haber ve duyurularda yayın/güncelleme tarihleri.
- Eski 145 URL için 301 yönlendirme tablosu.
- 404 raporu ve kırık bağlantı izleme.
- Sosyal paylaşım görselleri.

uygulanmalıdır.

---

## 14. Önerilen PHP mimarisi

### 14.1. Neden Laravel?

PHP seçimi bu proje için uygundur; ancak yönetilebilir alanlar olduğu için yalnızca klasörlere ayrılmış düz PHP dosyaları uzun vadede risklidir. Laravel yaklaşımı şu ihtiyaçlara hazır ve düzenli çözümler sağlar:

- yönlendirme ve eski URL'lerin 301 yönetimi,
- güvenli oturum ve CSRF koruması,
- kullanıcı/rol/yetki,
- form doğrulama,
- dosya yükleme ve özel depolama,
- kuyruk, e-posta ve bildirim,
- zamanlanmış görevler,
- veritabanı migration'ları,
- test edilebilir servis katmanı,
- cache ve performans yönetimi.

### 14.2. Önerilen yaklaşım

| Katman | Öneri |
|---|---|
| Backend | Laravel tabanlı modüler monolit |
| PHP | Yayına geçiş anındaki desteklenen kararlı sürüm |
| Veritabanı | MySQL 8+ veya uyumlu MariaDB |
| Görünüm | Blade bileşenleri |
| Etkileşim | Hafif Alpine.js/benzeri yaklaşım; gerekmedikçe SPA yok |
| CSS | Tasarım token'ları ve bileşen tabanlı modern CSS/Tailwind veya sade bir sistem |
| Admin | Aynı uygulama içinde, ayrı `/admin` alanı ve rol bazlı yetki |
| Dosya | Yerel özel disk veya S3 uyumlu obje depolama |
| Arama | İlk aşamada veritabanı full-text; ihtiyaç halinde ayrı arama servisi |
| Cache | Uygulama/cache katmanı; kamu sayfalarında HTTP cache |
| Kuyruk | E-posta, dosya işleme, bildirim ve raporlar için |
| Test | Birim + özellik + tarayıcı kabul testleri |

### 14.3. Modüller

1. Kimlik ve yetki yönetimi
2. Sayfa yönetimi
3. Sektör ve yeterlilik yönetimi
4. Doküman ve revizyon yönetimi
5. Ücret tarifesi yönetimi
6. Haber ve duyuru yönetimi
7. Başvuru yönetimi
8. İtiraz/şikayet yönetimi
9. İş başvurusu yönetimi
10. Lokasyon yönetimi
11. Referans yönetimi
12. Menü ve footer yönetimi
13. SEO ve yönlendirme yönetimi
14. Ayarlar ve entegrasyonlar
15. İşlem kayıtları / denetim izi

---

## 15. Önerilen veri modeli

### 15.1. Temel tablolar

| Tablo | Ana alanlar |
|---|---|
| `users` | ad, e-posta, parola özeti, durum, son giriş |
| `roles`, `permissions` | rol ve yetki ilişkileri |
| `pages` | başlık, slug, özet, içerik, şablon, yayın durumu, yayın tarihi |
| `sectors` | ad, slug, açıklama, ikon, sıra, aktiflik |
| `qualifications` | kod, ad, seviye, revizyon, sektör, açıklama, aktiflik |
| `qualification_documents` | yeterlilik, doküman türü, dosya, sürüm, tarih |
| `fee_schedules` | ad, başlangıç/bitiş, durum |
| `fees` | tarife, yeterlilik, sınav birimi/türü, ücret, para birimi |
| `documents` | başlık, tür, dosya, kurum, sürüm, yayın/geçerlilik tarihi |
| `posts` | tür: haber/duyuru, başlık, özet, içerik, önem, tarihler |
| `applications` | başvuru no, tür, yeterlilik, kişi, durum, zamanlar |
| `application_files` | başvuru, belge türü, özel dosya yolu, doğrulama |
| `complaints` | takip no, tür, konu, içerik, durum, sorumlu |
| `job_applications` | aday, iletişim, mesaj, CV, durum, silinme tarihi |
| `locations` | ad, tür, adres, koordinat, telefon, saatler, aktiflik |
| `references` | kurum, logo, sektör, URL, sıra, aktiflik |
| `redirects` | eski yol, yeni yol, HTTP kodu, sayaç |
| `seo_meta` | varlık türü/id, title, description, canonical, OG görseli |
| `settings` | kurum bilgileri, sosyal bağlantılar, entegrasyon ayarları |
| `audit_logs` | kullanıcı, işlem, varlık, önceki/yeni değer, IP, zaman |

### 15.2. Kritik modelleme kararı

Yeterlilik kodu, meslek adı, seviye ve revizyon tek bir serbest metin başlığa gömülmemelidir. Ayrı alan olmalıdır. Örneğin:

```text
Kod: 10UY0002
Seviye: 3
Revizyon: 03
Ad: Makine Bakımcı
Sektör: Makine
```

Bu sayede filtre, güncelleme, revizyon geçmişi, ücret eşleştirme ve arama sağlıklı çalışır.

---

## 16. Admin paneli gereksinimleri

### 16.1. Roller

- Süper yönetici
- İçerik editörü
- Kalite/yasal onaylayan
- Başvuru sorumlusu
- İtiraz/şikayet sorumlusu
- İnsan kaynakları
- Sadece rapor görüntüleyen

### 16.2. Yayın akışı

Özellikle fiyat, banka, yetki, mevzuat ve KVKK içerikleri için:

1. Taslak
2. İnceleme bekliyor
3. Onaylandı
4. Zamanlandı
5. Yayında
6. Arşivlendi

durumları kullanılmalıdır.

### 16.3. Denetim izi

Şunlar kaydedilmelidir:

- kim neyi değiştirdi,
- eski ve yeni değer,
- değişiklik zamanı,
- yayınlayan/onaylayan,
- dosya sürümü,
- geri alma bilgisi.

### 16.4. Dosya güvenliği

- Uzantıya değil gerçek MIME türüne göre doğrulama.
- Boyut sınırı.
- Zararlı dosya taraması.
- CV ve kimlik belgesi gibi dosyaları public klasör dışında saklama.
- Süreli, imzalı indirme bağlantısı.
- Rol bazlı erişim.
- Saklama süresi sonunda otomatik silme/anonimleştirme.

---

## 17. URL planı ve içerik geçişi

### 17.1. Önerilen URL'ler

```text
/
/meslekler
/meslekler/makine
/meslekler/makine/10uy0002-3-makine-bakimci
/sinav-ve-basvuru
/sinav-ve-basvuru/online-basvuru
/sinav-ve-basvuru/ucretler
/belge-yenileme
/duyurular
/haberler
/dokumanlar
/kurumsal/hakkimizda
/kurumsal/yetki-ve-akreditasyon
/iletisim
```

### 17.2. Eski URL koruması

Mevcut 145 temel içerik URL'si için geçiş tablosu hazırlanmalıdır. Kural:

- Aynı içerik varsa yeni karşılığına 301.
- Birleştirilen içerik varsa en yakın üst merkez sayfaya 301.
- Gerçekten kaldırılan ve karşılığı olmayan içerik 410.
- Eski PDF yolu korunacaksa dosya değişse bile 301 ile son sürüme değil, mümkünse ilgili doküman detay sayfasına yönlendirilmeli.
- `/insaat/` gibi çakışan yollar manuel test edilmelidir.

### 17.3. İçerik temizliği

Geçiş sırasında her kayıt şu kararlardan birini almalıdır:

- Aynen taşı
- Güncelleyerek taşı
- Başka içerikle birleştir
- Arşivle
- Kaldır

Özellikle pandemi sayfası, eski ücretler, eski zorunluluk rakamları, 2022 kataloğu ve geçmiş etkinlikler güncellik/arsiv etiketiyle ele alınmalıdır.

---

## 18. Performans ve kalite hedefleri

Yeni site için kabul hedefleri:

- Mobil öncelikli responsive tasarım.
- İlk ekranda gereksiz slider ve büyük JS paketleri olmaması.
- Modern resim formatları, `srcset`, lazy-load ve ölçü nitelikleri.
- Kritik CSS ve font optimizasyonu.
- Kamu sayfalarında sunucu ve CDN cache.
- Form ve admin uçlarında rate limit.
- Lighthouse hedefi: performans, erişilebilirlik, iyi uygulamalar ve SEO'da yüksek skor; skor tek başına kabul ölçütü olmamalı.
- Core Web Vitals gerçek kullanıcı ölçümü.
- 404, 500 ve form hata izleme.
- Günlük otomatik yedek ve geri yükleme tatbikatı.

---

## 19. Aşamalı uygulama planı

### Faz 0 — İçerik doğrulama

- 145 URL'nin içerik sahibi atanır.
- 83 yeterlilik kaydı MYK kodu/revizyonu bakımından doğrulanır.
- Güncel ücret, banka, adres, telefon, yetki ve KVKK bilgileri onaylanır.
- PDF'lerin güncel/eski durumu belirlenir.

### Faz 1 — Temel sistem

- Laravel proje iskeleti.
- Veritabanı ve migration'lar.
- Kullanıcı/rol/yetki.
- Admin paneli temel kabuğu.
- Tasarım sistemi ve ortak layout.

### Faz 2 — İçerik modülleri

- Kurumsal sayfalar.
- Sektör/yeterlilik.
- Doküman.
- Haber/duyuru.
- Referans/lokasyon.
- SEO ve yönlendirme.

### Faz 3 — İş akışları

- Online başvuru.
- Belge yenileme başvurusu.
- İtiraz/şikayet.
- İş başvurusu.
- Bildirimler ve admin durumları.

### Faz 4 — Veri geçişi

- WordPress içeriklerinin kontrollü aktarımı.
- Resim/PDF dosyalarının hash ile tekrar kontrolü.
- URL yönlendirme tablosu.
- İçerik karşılaştırma raporu.

### Faz 5 — Kabul ve yayın

- Masaüstü, tablet ve mobil tarayıcı testleri.
- Klavye ve ekran okuyucu temel kabulü.
- Form güvenliği ve dosya yükleme testleri.
- 145 eski URL'nin otomatik yönlendirme testi.
- SEO meta ve sitemap testi.
- Yedek/geri yükleme testi.
- Analitik ve hata izleme.
- Kademeli canlıya geçiş ve geri dönüş planı.

---

## 20. Önceliklendirilmiş sorun listesi

### P0 — Yayından önce çözülmesi gerekenler

- Meslek/yeterlilik kayıtlarının tek veri modelinde toplanması.
- Eski URL yönlendirme haritası.
- Başvuru ve CV dosyalarının güvenli özel depolanması.
- KVKK, banka, ücret ve yetki bilgilerinin güncellik onayı.
- Rol/yetki ve denetim izi.
- Formlarda CSRF, rate limit ve güvenli dosya doğrulama.

### P1 — Çok yüksek değer

- Görev odaklı ana menü ve sade ana sayfa.
- Meslek arama/filtreleme.
- Resim yerine erişilebilir ücret tablosu.
- Belge yenileme merkez sayfası.
- Duyuru/haber ayrımı.
- Benzersiz SEO title/description ve H1.
- WCAG 2.2 AA temel uyumu.

### P2 — İyileştirme

- Referansların kategorilenmesi.
- SSS modülü.
- Başvuru durum takibi.
- Otomatik kırık bağlantı kontrolü.
- PDF otomatik üretimi.
- Gelişmiş arama ve raporlar.

---

## 21. Kabul kriterleri

Yeni site “tamamlandı” sayılmadan önce en az şu kriterler karşılanmalıdır:

- 83 mevcut yeterlilik kaydının tamamı yeni sistemde karşılık bulur veya yetkili kişi tarafından kaldırma kararı verilmiştir.
- 145 eski temel URL'nin her biri doğru 200/301/410 sonucunu verir.
- Meslek, kod, seviye ve sektörle arama yapılabilir.
- Ücretler metin/tablo olarak okunabilir ve admin panelinden yönetilebilir.
- Online başvuru kayıtları admin panelinde takip edilir.
- CV, kimlik veya başvuru dosyaları public URL ile erişilemez.
- Her sayfada benzersiz title, description ve tek anlamlı H1 vardır.
- Formlar klavye ile kullanılabilir; alan etiketleri ve hata mesajları erişilebilirdir.
- Mobilde yatay taşma yoktur.
- Dış MYK ve sınav takvimi bağlantıları açık biçimde işaretlenir.
- Fiyat, banka, yetki ve yasal metin değişikliklerinde kim/ne zaman kaydı tutulur.
- Yedekten geri dönüş denenmiştir.

---

## 22. Sonuç ve net öneri

Mavi Belge sitesi sıfırdan içerik üretilerek değil, mevcut zengin içerik **yeniden modellenerek** yapılmalıdır. Tasarım yenilemesi tek başına sorunu çözmez. Önce bilgi mimarisi ve veritabanı modeli kurulmalı; ardından arayüz bu modele bağlanmalıdır.

Net teknik öneri:

- PHP kullanılmalı.
- Düz PHP yerine Laravel tabanlı modüler monolit tercih edilmeli.
- Sunucu tarafı Blade arayüz ve hafif JavaScript kullanılmalı.
- Admin paneli sonradan eklenen ayrı bir parça değil, veri modelinin doğal yönetim yüzü olarak ilk günden tasarlanmalı.
- En kritik modül “Sektör/Yeterlilik/Doküman/Ücret” ilişkisidir.
- Eski WordPress doğrudan tema kopyası olarak taşınmamalı; içerik temizlenip yapılandırılmış veri olarak aktarılmalı.
- Görsel dil, Mavi Belge'nin mavi tonları ve akreditasyon güvenini korurken daha ferah, görev odaklı ve mobil öncelikli olmalıdır.

Bu rapor, tasarım brief'i, veritabanı tasarımı, admin panel kapsamı ve içerik geçiş planı için başlangıç dokümanı olarak kullanılabilir. Bir sonraki doğru adım; 83 yeterlilik kaydının doğrulandığı bir içerik matrisi ile wireframe/site haritasının birlikte hazırlanmasıdır.

---

## 23. İncelenen başlıca kaynak URL'ler

- Ana site: https://mavibelge.com.tr/
- Sitemap: https://mavibelge.com.tr/wp-sitemap.xml
- Kurumsal: https://mavibelge.com.tr/sirket-hakkinda/
- Sınav süreci: https://mavibelge.com.tr/sinav-ve-belgelendirme-sureci/
- Başvuru: https://mavibelge.com.tr/basvuru-formlari/
- Ücretler: https://mavibelge.com.tr/fiyat-list/
- Mevzuat: https://mavibelge.com.tr/mevzuat/
- KVKK: https://mavibelge.com.tr/kvkk-2/
- Haberler: https://mavibelge.com.tr/haberler/
- İletişim: https://mavibelge.com.tr/iletisim/
- Meslek sektörleri: `/makine/`, `/metalurji/`, `/metal-meslekleri/`, `/liman-meslekleri/`, `/plastik-meslekleri/`, `/elektrik/`, `/cam/`, `/tekstil/`, `/insaat-meslekleri/`, `/mobilya-meslekleri/`, `/mermer-meslekleri/`, `/guzellik-ve-sac-bakim/`

