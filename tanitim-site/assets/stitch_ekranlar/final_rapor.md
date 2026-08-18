# Mavi Belge Yeni Web Sitesi — Birleştirilmiş İnceleme ve Temel Karar Raporu

**Kaynak raporlar:** `incelemeraporu-gpt.md`, `incelemeraporu-claude.md`  
**İncelenen mevcut site:** https://mavibelge.com.tr/  
**Hazırlanma tarihi:** 17 Ağustos 2026  
**Belgenin statüsü:** Kodlama öncesi ortak değerlendirme ve karar zemini  

---

## 1. Bu belgenin amacı

Bu rapor, mevcut Mavi Belge web sitesi için hazırlanmış iki ayrı incelemeyi karşılaştırır, doğrulanabilen bulguları birleştirir, hatalı veya kanıtsız ifadeleri düzeltir ve yeni web sitesinin temelleri için tek bir referans belge oluşturur.

Bu belge henüz ayrıntılı yazılım şartnamesi değildir. Özellikle sınav takviminin devralınması, aday portalı, ödeme, çoklu dil ve başvuru operasyonu gibi kapsamı ciddi biçimde büyütecek konular kullanıcıyla mutabakat sağlanmadan zorunlu geliştirme kapsamına alınmamıştır.

Kodlama bu rapordan hemen sonra başlamamalıdır. Temel ürün kararları 18. bölümde karara bağlanmıştır; kalan teknik doğrulamalar ve kullanıcının sistemle ilgili soruları tamamlandıktan sonra Claude için hazırlanacak uygulama promptları bu belgede kabul edilen kapsam ve kabul kriterlerine dayanmalıdır.

---

## 2. Sonuç özeti

İki raporun ortak ve doğru ana sonucu şudur:

> Mevcut sitenin temel sorunu görsel eskilikten önce içerik modelinin olmamasıdır. Sektörler, yeterlilik programları, ücretler, belgeler, haberler ve başvurular çoğunlukla bağımsız WordPress sayfaları ve manuel bağlantılar halinde tutulmaktadır.

Bu nedenle yalnızca yeni bir tema çizmek veya eski sayfaları PHP dosyalarına dönüştürmek doğru çözüm değildir. Yeni sistemde içerik önce yapılandırılmış veriye dönüştürülmeli, arayüz ve admin paneli bu veri modelinin üzerinde kurulmalıdır.

Birleştirilmiş öneri:

- PHP kullanımı uygundur.
- Düz ve dağınık PHP yerine Laravel tabanlı modüler bir uygulama tercih edilmelidir.
- Ön yüz sunucu tarafında render edilmeli; Blade bileşenleri ve gerektiği kadar hafif JavaScript kullanılmalıdır.
- İlk sürümün merkezi `Sektör → Yeterlilik → Seviye/Revizyon → Doküman → Ücret → Başvuru` ilişkisidir.
- Eski WordPress içeriği doğrudan kopyalanmamalı; doğrulanmalı, temizlenmeli ve yeni veri modeline taşınmalıdır.
- Admin paneli başlangıçtan itibaren planlanmalı; ancak sınav operasyonu, aday portalı ve ödeme sistemi ayrıca onaylanmadıkça ilk sürüme dahil edilmemelidir.
- 145 temel içerik URL'si için yayın öncesi yönlendirme veya kaldırma kararı verilmelidir.
- Tasarım görev odaklı, mobil öncelikli, erişilebilir ve sade olmalıdır.

---

## 3. İki raporun karşılaştırması

### 3.1. Güçlü ve ortak bulgular

Her iki rapor aşağıdaki konularda büyük ölçüde aynı sonuca ulaşmıştır:

- Mevcut sistem WordPress, WPBakery ve özel `MaviBelge` teması kullanıyor.
- İçerik zengin, sınıflandırma ve ilişkilendirme zayıf.
- 12 sektör bulunuyor.
- Sektör ve yeterlilikler özel veri tipi olarak modellenmemiş.
- Ücretler okunabilir tablo yerine JPG görseller olarak yayınlanıyor.
- Ana navigasyon gereksiz tekrar içeriyor.
- Sınav sonucu ve belge sorgulama aynı MYK sayfasına gidiyor.
- Sınav takvimi başka bir alan adına çıkıyor.
- Bütün sayfalarda aynı title kullanılıyor ve meta description bulunmuyor.
- Anlamlı H1 yapısı yok.
- Haber ve duyuru ayrımı yapılmamış.
- Formlarda erişilebilirlik ve veri yönetimi eksikleri var.
- Eski URL'ler ve hatalı slug'lar için 301 planı gerekli.
- Laravel, admin paneli ve uzun vadeli bakım açısından saf PHP'den daha uygun.

### 3.2. GPT raporunun güçlü tarafları

- Sitemap sayıları eksiksiz ve doğrulanmış biçimde verilmiştir.
- 12 sektör altındaki 83 görünür yeterlilik kaydı ayrı ayrı sayılmıştır.
- Erişilebilirlik ölçümü daha ayrıntılıdır.
- Güvenlik başlıkları, HTTP cache ve ana sayfa varlık yükü incelenmiştir.
- KVKK'da aydınlatma ile açık rızanın her durumda aynı şey olmadığına dikkat edilmiştir.
- Admin rolleri, yayın akışı, denetim izi, dosya güvenliği ve kabul kriterleri güçlüdür.
- Uygulama kapsamını aşamalara ayırır ve eski 145 URL'nin tek tek doğrulanmasını kabul ölçütü yapar.

### 3.3. Claude raporunun güçlü tarafları

- Hatalı slug ve yanlış hedefe giden iç bağlantıları somut örneklerle gösterir.
- Elektrik, Cam, Liman ve Maden–Mermer tarafındaki detay sayfası tutarsızlığını açık biçimde anlatır.
- Karakter bozulmaları, tekrar eden açıklamalar ve standart dışı doküman adları gibi içerik kalitesi sorunlarını daha görünür kılar.
- Önerilen sayfa şablonlarını ve admin modüllerini somutlaştırır.
- Yeterlilik birimleri, sınav türleri, geçme notu ve geçerlilik süresi gibi adayın ihtiyaç duyacağı alanları veri modeline ekler.
- Karar bekleyen konuları ayrı bir bölümde toplar.

### 3.4. Claude raporundaki düzeltilmesi gereken noktalar

| Konu | Claude raporundaki ifade | Doğrulanan durum / düzeltme |
|---|---|---|
| Sabit sayfa sayısı | “~120 sayfa” | Sitemap'te tam olarak **114 sabit sayfa** var. |
| Toplam envanter | Sayfa ve iki kategori öne çıkarılmış | Ayrıca **31 yazı, 54 logo kaydı, 38 etiket arşivi ve 1 yazar arşivi** var. |
| Taksonomi | “Sadece category” | `post_tag` sitemap'inde **38 etiket** bulunuyor. |
| Sınav & Belgelendirme | Başlık “8 sayfa” | Aynı bölümde fiilen **10 URL** listelenmiş. |
| Maden & Mermer | “3/3 program” | Sayfada **7 görünür yeterlilik** var; yalnızca 3 farklı detay URL'si bulunuyor ve 5 kayıt aynı detay URL'sine bağlanıyor. |
| Online başvuru alanları | “meslek” alanı var | Formda `your-name`, `your-mail`, `your-phone`, `file-1`, `your-message`, `quiz-1` var; **meslek seçimi yok**. |
| Başvuruların DB'ye yazılmaması | Kesin hüküm | Contact Form 7 varsayılan olarak e-posta gönderir; ancak yönetim/eklenti erişimi olmadan kayıt tutulmadığı kesin söylenemez. Bu bir **risk ve güçlü olasılık**, doğrulanmış gerçek değildir. |
| Açık rıza | Her formda zorunlu gibi sunulmuş | Aydınlatma yükümlülüğü zorunludur; açık rıza ise veri işleme amacına ve hukuki sebebe göre değerlendirilmelidir. Gereksiz açık rıza alınmamalıdır. |
| Framework güvenliği | “Framework garantisi” | Laravel riski azaltır ve güvenli araçlar sağlar; **güvenliği garanti etmez**. Doğru yapılandırma, güncelleme ve test gerekir. |
| Çoklu dil gerekçesi | 6 dilde belge → site çok dilli olmalı | Belgenin çok dilli olması, sitenin mutlaka çok dilli olması gerektiğini kanıtlamaz. Çoklu dil hedef kitle ve iş ihtiyacı kararıdır. |
| Süre tahmini | 10–13 hafta | Ekip kapasitesi, onay süreci, entegrasyon ve veri temizliği netleşmeden güvenilir süre verilemez. |
| Laravel/PHP sürümü | Sabit sürüm numaraları | Kesin sürümler proje başlatılırken desteklenen kararlı sürümlere göre kilitlenmelidir. |

### 3.5. GPT raporunda dikkat edilmesi gereken noktalar

- 83 kayıt, mevcut sektör sayfalarında görünen programların toplamıdır; bunların hepsi bağımsız HTML detay sayfası değildir.
- “Ana sayfada 151 resim” sayısı tarayıcıda slider eklentilerinin ürettiği klonlarla birlikte oluşur. Kaynak HTML'de 93 resim etiketi vardır. Her iki sayı kendi bağlamında doğrudur.
- Aday başvurusu, şikayet yönetimi ve iş başvurusu için önerilen tam operasyon modülleri işlevsel olarak değerlidir; ancak bunların tamamı ilk sürüm için kullanıcı tarafından henüz onaylanmış değildir.
- Sınav takviminin yeni veritabanına alınması teknik olarak ancak mevcut sistemin API veya veri paylaşım imkânı doğrulandıktan sonra kararlaştırılabilir.

---

## 4. Doğrulanmış mevcut sistem envanteri

### 4.1. Sitemap sayıları

| İçerik türü | Kesin sayı |
|---|---:|
| Sabit sayfa (`page`) | 114 |
| Yazı (`post`) | 31 |
| Temel içerik URL toplamı | 145 |
| Referans logo özel kaydı | 54 |
| Kategori arşivi | 2 |
| Etiket arşivi | 38 |
| Yazar arşivi | 1 |

145 temel içerik URL'si inceleme sırasında HTTP 200 yanıtı vermiştir. Bu, içeriklerin doğru veya güncel olduğu anlamına gelmez; yalnızca erişilebilir olduklarını gösterir.

### 4.2. Teknik altyapı

| Bileşen | Mevcut durum |
|---|---|
| CMS | WordPress 6.8.8 |
| Sayfa kurucu | WPBakery Page Builder |
| Tema | Özel `MaviBelge` teması |
| CSS | Bootstrap 4.3.1 + özel tema CSS'i |
| JavaScript | jQuery, jQuery Migrate, Popper, Bootstrap JS ve eklenti scriptleri |
| Formlar | Contact Form 7 tabanlı |
| Slider | MetaSlider/FlexSlider |
| Referans logoları | Birden fazla logo slider eklentisi |
| Sunucu | LiteSpeed |
| REST API | Güvenlik ayarı nedeniyle herkese açık erişim 401 ile engelleniyor |

### 4.3. Ana sayfa kaynak yükü

- Kaynak HTML yaklaşık 115 KB.
- Kaynak HTML'de 93 resim etiketi var.
- Resimlerin 89'unda boş alt değeri bulunuyor.
- Yalnızca 11 resimde `loading="lazy"` görülüyor.
- WebP resmi tespit edilmedi.
- Slider ve logo eklentileri çalıştıktan sonra tarayıcı DOM'unda resim sayısı 151'e kadar çıkıyor.
- Ana sayfada yaklaşık 135 statik varlık bağlantısı var.
- Kamuya açık ana sayfa `no-store/no-cache` döndürüyor.

Bu sayılar yeni site için performans bütçesi oluşturulması gerektiğini gösterir; doğrudan yeni sistem hedefi olarak kullanılmamalıdır.

---

## 5. Mevcut bilgi mimarisi

### 5.1. Aynı anda çalışan dört navigasyon katmanı

1. Logo yanındaki işlem ikonları.
2. Ana açılır menü.
3. İkinci yatay hızlı işlem şeridi.
4. Footer'daki tekrar eden sektör ve iletişim bağlantıları.

Bu katmanlarda aynı bağlantılar tekrarlanmakta, bazı içerikler yalnızca bir katmanda bulunmakta ve sektör adları değişmektedir. Kullanıcı kurumsal sınıflandırmayı değil kendi görevini düşünür; bu nedenle yeni yapı görev odaklı olmalıdır.

### 5.2. Mevcut ana menü

- Anasayfa
- Kurumsal
- Mesleki Yeterlilik
- Sınav Belgelendirme
- Mesleklerimiz
- Medya
- Referanslarımız
- İnsan Kaynakları
- İletişim

### 5.3. Mevcut hızlı görevler

- Başvuru Formu
- Sınav Sonucu
- Belge Sorgulama
- Sınav Takvimi
- Sınav Ücretleri
- Banka Hesap Bilgileri
- Belge Yenileme
- Neden Zorunlu?
- Yetki Belgelerimiz

### 5.4. Adlandırma tutarsızlıkları

| Alan | Kullanılan adlar |
|---|---|
| Ulaştırma | “Ulaştırma & Lojistik”, “Liman Meslekleri” |
| Maden | “Maden & Mermer”, “Mermer Meslekleri” |
| Elektrik ve Cam | Üst menüde var, footer'da yok |

Bu isimler yeni veri aktarımından önce kurum tarafından tekilleştirilmelidir.

---

## 6. Sektör ve yeterlilik envanteri

Mevcut sektör sayfalarında toplam **83 görünür yeterlilik kaydı** vardır:

| Sektör | Görünür kayıt | Bağımsız detay durumu |
|---|---:|---|
| Makine | 13 | 13 detay bağlantısı |
| Metalurji | 7 | 7 detay bağlantısı |
| Metal | 5 | 5 detay bağlantısı |
| Ulaştırma & Lojistik / Liman | 12 | 4 HTML detay; diğerleri metin/PDF |
| Plastik | 3 | 3 detay bağlantısı |
| Elektrik | 5 | Bağımsız HTML detay yok; kategori/PDF |
| Cam | 3 | Bağımsız HTML detay yok |
| Tekstil | 6 | 6 detay bağlantısı |
| İnşaat | 12 | 12 detay bağlantısı |
| Mobilya | 3 | 3 detay bağlantısı |
| Maden & Mermer | 7 | 3 farklı detay URL'si; 5 kayıt aynı URL'ye bağlı |
| Güzellik ve Saç Bakım | 7 | 7 detay bağlantısı |
| **Toplam** | **83** | Yapı tutarsız |

### 6.1. En önemli yapısal hatalar

- `/insaat/` adresi sektör sayfasına değil İnşaat Boyacısı detayına yönleniyor. Doğru sektör URL'si `/insaat-meslekleri/`.
- Bazı detay sayfalarındaki manuel “diğer sektörler” bloğu yalnızca 6 sektör gösteriyor.
- Elektrik ve Cam yeterlilikleri bağımsız detay sayfasına sahip değil.
- Liman/Ulaştırma kayıtlarının sekizi kategori sayfasında metin veya PDF düzeyinde kalıyor.
- Maden & Mermer sayfasındaki şu beş ayrı kayıt aynı detay URL'sine bağlanıyor:
  - Mekanizasyon İşçisi Seviye 4
  - Mermer-Doğaltaş Özel İmalat Elemanı Seviye 4
  - Yeraltı Hazırlık İşçisi Seviye 4
  - Yeraltı Hazırlık İşçisi Seviye 3
  - Kırma Eleme Tesis Operatörü Seviye 3
- Çok sayıda slug yazım veya WordPress çoğaltma izi taşıyor: `doagltas`, `montajcisibelgelendirme`, `kvkk-2`, `kuafor-2`, `3114-2`, sondaki gereksiz tireler vb.

### 6.2. Yeni model için temel kural

Her yeterlilik benzersiz bir kayıt olmalıdır. Program adı, MYK kodu, seviye ve revizyon yalnızca başlık metni içine gömülmemelidir.

Örnek:

```text
MYK kodu: 10UY0002
Seviye: 3
Revizyon: 03
Ad: Makine Bakımcı
Sektör: Makine
Tam kod: 10UY0002-3/03
```

Bu kayıt kendi detay URL'sine, güncel dokümanlarına, ücretine ve başvuru bağlantısına sahip olmalıdır.

### 6.3. Yeterlilik detayında bulunması önerilen alanlar

- MYK kodu, seviye ve revizyon.
- Kısa tanım ve ayrıntılı tanım.
- Yeterliliğin amacı ve hukuki dayanağı.
- Varsa giriş/ön koşullar.
- Yeterlilik birimleri.
- Teorik, performans ve varsa diğer sınav türleri.
- Geçme ölçütleri.
- Belge geçerlilik süresi.
- Gözetim ve yenileme bilgisi.
- Belge zorunluluğu durumu.
- Güncel ücret ilişkisi.
- Güncel program/talimat/form dokümanları.
- Yaklaşan sınav bağlantısı veya dış takvim bağlantısı.
- “Bu yeterlilik için başvur” çağrısı.
- İlgili yeterlilikler.

Bu alanların mevzuata uygunluğu ve güncelliği Mavi Belge tarafından doğrulanmalıdır; mevcut sayfalardaki metinler otomatik olarak doğru kabul edilmemelidir.

---

## 7. İçerik ve işlev sorunları

### 7.1. SEO

- 145 temel sayfanın title değeri aynı.
- 145 sayfada meta description yok.
- Anlamlı sayfa H1'i bulunmuyor.
- Open Graph, Twitter Card ve JSON-LD yapılandırılmış veri yok.
- Logo, etiket ve yazar arşivleri düşük değerli sitemap URL'leri oluşturuyor.
- Breadcrumb bulunmuyor.

Yeni sistemde benzersiz title/description, tek H1, canonical, breadcrumb, sosyal paylaşım meta verisi ve uygun schema türleri zorunlu olmalıdır.

### 7.2. Ücretler

2026 tarifesi dört büyük JPG, güzellik tarifesi iki JPG olarak sunuluyor. Ücretler yeni sistemde veritabanı kayıtları olmalıdır. Filtrelenebilir HTML tablo birincil kaynak olmalı; istenirse aynı veriden PDF üretilmelidir.

### 7.3. Haber ve duyurular

31 yazı; ücret değişikliği, mevzuat, deprem duyurusu, fuar, eğitim ve kurumsal haberleri tek listede topluyor. Yeni sistemde en az:

- Duyuru
- Haber

ayrımı yapılmalıdır. Duyuruya önem seviyesi, yayın başlangıcı, bitişi ve hedef kitle eklenmelidir.

### 7.4. Dokümanlar

- Dosya adları ve revizyon biçimleri standart değil.
- Bazı mevzuat bağlantıları eski `http://` kurum adreslerine gidiyor.
- Dokümanın güncel sürümü kolay anlaşılmıyor.

Yeni doküman kaydında kategori, kurum, sürüm/revizyon, yayın tarihi, geçerlilik tarihi, dosya, eski sürümler ve son bağlantı kontrol tarihi olmalıdır.

### 7.5. Eski veya şüpheli içerikler

Aşağıdaki kayıtlar otomatik silinmemeli; içerik sahibiyle karar verilmelidir:

- `/slider/`
- `/logos/`
- `/online-basvuru/`
- `/basvuru-formlari-2/`
- `/belge-yenileme/`
- `/pandemi-surecinde-alinan-tedbirler1/`
- `/sosyal-sorumluluk/`
- `/beyteks-sorumluluk/`
- `/matkim-2/`
- `/crt-metal/`
- `/sekizlivinc-2/`
- `/mesleklerimiz/`

Her biri “taşı, birleştir, arşivle, 301 ile yönlendir veya 410 ile kaldır” kararlarından birini almalıdır.

---

## 8. Formlar, veri ve KVKK

### 8.1. Mevcut formlar

#### Ana sayfa “Sorum Var”

- Ad soyad
- Telefon
- Meslek
- Mesaj
- Basit matematik sorusu

#### Online başvuru

- Ad soyad
- E-posta
- Telefon
- Dosya
- Mesaj
- Basit matematik sorusu

Mevcut online başvuru formunda yapılandırılmış meslek/yeterlilik seçimi yoktur.

#### İş başvurusu

- Ad soyad
- E-posta
- Telefon
- Çalışılan firma
- CV/dosya
- Mesaj
- Basit matematik sorusu

### 8.2. Yeni sistemde temel ilkeler

- Aday başvurusu, iletişim mesajı, itiraz/şikayet ve iş başvurusu ayrı veri türleri olmalıdır.
- Her form yalnızca amacı için gerekli veriyi toplamalıdır.
- Aydınlatma metni ilgili forma açıkça bağlanmalıdır.
- Açık rıza yalnızca uygun hukuki sebep gerçekten rıza ise alınmalıdır.
- Rıza gerekiyorsa metin sürümü, zaman ve kanıt saklanmalıdır.
- CV, kimlik, dekont ve diğer kişisel dosyalar public klasörde tutulmamalıdır.
- MIME, boyut, zararlı dosya ve erişim kontrolleri uygulanmalıdır.
- Saklama ve silme süreleri tanımlanmalıdır.
- Bot koruması erişilebilir ve gizlilik açısından uygun bir yöntemle yapılmalıdır.
- Başvurular veritabanında tutulacaksa rol bazlı erişim ve denetim izi zorunludur.

### 8.3. Online başvuru önerisi

İlk sürümde online başvurunun kapsamı ayrıca kararlaştırılmalıdır:

**Seçenek A — Talep toplama:** Kullanıcı yeterlilik ve iletişim bilgisini bırakır; operasyon ekibi geri döner. Daha dar, hızlı ve düşük riskli kapsam.

**Seçenek B — Tam başvuru:** Kişisel bilgiler, gerekli belgeler, dekont, başvuru numarası, durum yönetimi ve bildirimler. Daha yüksek veri güvenliği ve operasyon gerektirir.

Mutabakat olmadan B seçeneği varsayılmamalıdır.

---

## 9. Kullanılabilirlik ve yeni bilgi mimarisi

### 9.1. Temel kullanıcı görevleri

- Mesleğini/yeterliliğini bulmak.
- Nasıl başvuracağını öğrenmek.
- Ücreti görmek.
- Sınav tarihini görmek.
- Sonuç veya belge sorgulamak.
- Belge yenilemek.
- Kurumsal yetkiyi doğrulamak.
- İtiraz/şikayet iletmek.
- Mavi Belge ile iletişime geçmek.

### 9.2. Önerilen ana menü

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

Header'da ayrıca belirgin bir “Online Başvuru” butonu olmalıdır.

### 9.3. Önerilen ana sayfa

1. İnce yetki/akreditasyon güven bandı.
2. Sade logo ve ana menü.
3. H1, kısa değer önerisi ve iki ana çağrı:
   - Mesleğimi Bul
   - Online Başvuru
4. Dört görev kartı:
   - Mesleğimi Bul
   - Sınav Takvimi
   - Sonuç ve Belge Sorgula
   - Belge Yenile
5. Aranabilir sektör/yeterlilik alanı.
6. Başvuru → Sınav → Değerlendirme → Belgelendirme süreci.
7. Güncel duyurular.
8. Yetki belgeleri, lokasyonlar ve kurumsal güven.
9. Seçilmiş referanslar.
10. İletişim çağrısı ve sade footer.

Otomatik büyük slider zorunlu değildir. Kullanıcının görevini desteklemiyorsa kaldırılmalıdır.

### 9.4. Arama

Yeterlilik araması şu alanlarda çalışmalıdır:

- meslek adı,
- MYK kodu,
- sektör,
- seviye,
- anahtar kelime.

Sonuç kartında kod, ad, seviye, sektör, aktiflik, güncel ücret bağlantısı ve başvuru işlemi bulunmalıdır.

---

## 10. Erişilebilirlik ve responsive hedefi

Mevcut sitedeki doğrulanmış sorunlar:

- Ana sayfa kaynak HTML'indeki 93 resmin 89'unda boş alt değeri.
- Render edilen DOM'da çok sayıda slider klonu.
- Anlamlı H1 yok.
- Form alanlarının programatik etiketleri ve gerçek zorunluluk bilgileri eksik.
- İçeriğe atlama bağlantısı yok.
- Bazı ikonların erişilebilir adı yok.
- Material Icons yüklenmediğinde `account_circle`, `phone`, `work` gibi ham metinler görünür olabiliyor.
- Tema yalnızca sınırlı mobil kurallar içeriyor ve özel CSS'te geçersiz iç içe bir kural bulunuyor.

Yeni sistem hedefi **WCAG 2.2 AA** olmalıdır:

- Her sayfada bir anlamlı H1.
- Doğru başlık sırası.
- Klavye ile eksiksiz kullanım.
- Görünür odak.
- Form hata özeti ve alan hataları.
- Yeterli kontrast.
- 44×44 piksel hedefler.
- Anlamlı görsellere doğru alt metin.
- İçeriğe atlama bağlantısı.
- Hareket azaltma tercihi.
- Mobilde yatay taşma olmaması.
- PDF'lerin önemli içeriği için HTML karşılığı.

---

## 11. Birleştirilmiş teknik mimari önerisi

### 11.1. Mimari yön

Öneri: **Laravel tabanlı modüler monolit**.

Gerekçe:

- Admin panel ve rol/yetki ihtiyaçları.
- Yapılandırılmış sektör/yeterlilik verisi.
- Dosya ve form güvenliği.
- İçerik yayın akışı.
- 301 yönlendirme yönetimi.
- Test edilebilirlik.
- Veritabanı migration'ları.
- E-posta, bildirim, queue ve zamanlanmış görevler için standart yapı.

Laravel güvenliği garanti etmez; ancak güvenli geliştirme için yerleşik araçlar sağlar. Güncelleme, doğru yapılandırma, kod incelemesi ve test yine zorunludur.

### 11.2. Kesin sürüm politikası

Bu aşamada Laravel ve PHP sürüm numarası sabitlenmemelidir. Kodlama başlarken:

- desteklenen kararlı PHP sürümü,
- desteklenen kararlı Laravel sürümü,
- seçilecek admin kütüphanesinin uyumluluğu,
- hosting gereksinimleri

birlikte kontrol edilip sürüm kilidi oluşturulmalıdır.

Mevcut hosting kullanılacaktır. Bu nedenle kodlama başlamadan önce hosting üzerinde PHP sürümü, gerekli PHP eklentileri, Composer/SSH erişimi, document root ayarı, zamanlanmış görev, queue çalıştırma imkânı, veritabanı sürümü, e-posta çıkışı, dosya/depolama kotası ve yedekleme özellikleri doğrulanmalıdır. Mevcut hosting Laravel gereksinimlerini karşılamazsa mimariyi zayıflatmak yerine hosting sağlayıcısından uygun paket/ayar talep edilmelidir.

Admin panelinde hazır bir Laravel kütüphanesi kullanılacaktır. Kesin kütüphane, Laravel sürümü ve mevcut hosting uyumluluğu doğrulandıktan sonra seçilecektir.

### 11.3. Ön yüz

- Blade bileşenleri.
- Tasarım token'ları ve bileşen tabanlı CSS.
- Tailwind veya temiz bir özel CSS sistemi; seçim tasarım aşamasında yapılmalı.
- Küçük etkileşimler için hafif JavaScript.
- jQuery zorunlu değil.
- SPA zorunlu değil.
- WebP/AVIF, `srcset`, lazy loading ve resim ölçüleri.

### 11.4. Depolama ve altyapı

- MySQL 8+ veya uyumlu MariaDB.
- Kişisel dosyalar için private disk veya S3 uyumlu özel obje depolama.
- E-posta ve ağır işlemler için queue.
- Kamu sayfaları için cache.
- Günlük yedek ve doğrulanmış geri yükleme prosedürü.
- Hata izleme ve 404/301 raporu.

### 11.5. İlk sürümün kesin kapsam sınırları

İlk sürümde:

- Tam başvuru, belge yükleme ve başvuru durum yönetimi bulunacaktır.
- Başvuru durum bildirimleri e-posta ile yapılacaktır.
- Sınav takvimi yalnızca mevcut dış sisteme açıklamalı bağlantı verecektir.
- Aday hesabı/portalı olmayacaktır.
- Online ödeme olmayacaktır.
- SMS entegrasyonu olmayacaktır.
- Site yalnızca Türkçe olacaktır.
- Sınav sonuçları yerel veritabanında tutulmayacak; MYK sorgu sayfasına yönlendirme yapılacaktır.
- Doküman merkezi herkese açık ve versiyonlu olacaktır.
- Eski haberlerin tamamı görselleriyle birlikte taşınacaktır.
- Sosyal sorumluluk sayfaları korunacaktır.
- Mevcut logo korunacaktır.
- Tasarım kurumsal, sade ve modern olacaktır.

Slider yönetimi, gelişmiş CRM, sınav oturumu yönetimi ve benzeri ek modüller ayrıca onaylanmadıkça kapsam dışıdır. Mimari ileride eklenmelerini engellememeli; ilk sürümü gereksiz büyütmemelidir.

---

## 12. Önerilen çekirdek veri modeli

### 12.1. İçerik

| Tablo/model | Amaç |
|---|---|
| `pages` | Kurumsal ve statik sayfalar |
| `sectors` | 12 sektör ve gelecekteki değişiklikler |
| `qualifications` | MYK kodu, seviye, revizyon ve meslek bilgisi |
| `qualification_units` | A1/B1 gibi yeterlilik birimleri; kapsam onaylanırsa |
| `documents` | Kurumsal ve mevzuat dokümanları |
| `qualification_documents` | Programa bağlı güncel/eski PDF'ler |
| `fee_periods` | Yıllık veya dönemsel ücret tarifesi |
| `fees` | Yeterlilik bazlı ücretler |
| `posts` | Haber ve duyuru |
| `post_categories` | Haber/duyuru sınıflandırması |
| `locations` | Merkez ve sınav alanları |
| `references` | Referans kurumlar |

### 12.2. Site yönetimi

| Tablo/model | Amaç |
|---|---|
| `users`, `roles`, `permissions` | Yönetici kullanıcı ve yetki |
| `settings` | Kurum bilgileri ve kontrollü site ayarları |
| `media` | Merkezi medya kütüphanesi |
| `redirects` | Eski URL → yeni URL yönetimi |
| `audit_logs` | Değişiklik geçmişi |

Menü yapısı ilk sürümde tamamen serbest bırakılmamalıdır. Ana bilgi mimarisi kod/konfigürasyonla sabit tutulabilir; yalnızca yetkili yönetici kontrollü bağlantı ve sıra değişikliği yapabilir.

### 12.3. Form ve başvuru kayıtları

| Tablo/model | Durum |
|---|---|
| `contact_messages` | Çekirdek kapsam için önerilir |
| `applications` | Tam başvuru ve durum yönetimi için çekirdek kapsam |
| `application_files` | Başvuru belgelerinin özel depolanması için çekirdek kapsam |
| `application_status_history` | Her durum değişikliğinin geçmişi ve açıklaması |
| `email_notifications` | Başvuru e-postalarının teslim/durum kaydı |
| `complaints` | Mevcut itiraz/şikayet sürecinin çevrimiçi yürütülmesi için önerilir |
| `job_applications` | Korunacak İK formu için gerekli |

Başvuru statüleri en az `yeni`, `incelemede`, `eksik_belge`, `onaylandi`, `reddedildi`, `sinava_yonlendirildi` durumlarını desteklemelidir. Durum adları ve geçiş yetkileri kodlama öncesi iş akışı toplantısında kesinleştirilmelidir.

### 12.4. Önemli veri bütünlüğü kuralları

- `myk_code + level + revision` benzersiz olmalıdır.
- Ücret kaydı yeterlilik ve dönemle ilişkilendirilmelidir.
- Aynı anda yalnızca bir aktif ücret dönemi bulunmalıdır veya çakışma açıkça engellenmelidir.
- Doküman sürümü ve geçerlilik tarihi tutulmalıdır.
- Slug benzersiz ve normalize olmalıdır.
- Silme yerine gereken yerlerde arşivleme kullanılmalıdır.
- Hassas dosya public yol alamamalıdır.

---

## 13. Admin paneli — önerilen kapsam

### 13.1. Çekirdek modüller

1. Gösterge paneli
2. Sektörler
3. Yeterlilikler
4. Dokümanlar ve revizyonlar
5. Ücret tarifeleri
6. Haberler ve duyurular
7. Kurumsal sayfalar
8. Lokasyonlar
9. Referanslar
10. Medya kütüphanesi
11. SEO alanları
12. 301 yönlendirmeler
13. Kullanıcı, rol ve yetki
14. Denetim izi
15. Site ayarları

### 13.2. Başvuru ve iletişim modülleri

İlk sürümde bulunacaklar:

- Başvurular ve durum yönetimi
- Başvuru dosyaları
- İletişim mesajları
- İş başvuruları
- E-posta bildirimleri

İtiraz ve şikayetlerin çevrimiçi form ve durum yönetimi kapsamı, mevcut kalite prosedürü incelendikten sonra ayrıntılandırılacaktır. Excel içe/dışa aktarma, gerçek operasyon ihtiyacına göre eklenebilir.

İlk sürümde bulunmayacaklar:

- Yerel sınav takvimi/oturum yönetimi
- SMS bildirimleri
- Aday portalı
- Online ödeme

### 13.3. Roller

İhtiyaca göre:

- Süper yönetici
- İçerik editörü
- Kalite/yasal onaylayan
- Başvuru operasyonu
- İtiraz/şikayet sorumlusu
- İnsan kaynakları
- Sadece rapor görüntüleyen

### 13.4. Yayın akışı

Özellikle fiyat, banka, yetki, mevzuat ve KVKK içerikleri için:

1. Taslak
2. İnceleme bekliyor
3. Onaylandı
4. Zamanlandı
5. Yayında
6. Arşivlendi

En azından kimin neyi ne zaman değiştirdiği kaydedilmelidir.

---

## 14. Önerilen URL yapısı

```text
/
/meslekler
/meslekler/makine
/meslekler/makine/makine-bakimci-seviye-3
/sinav-ve-basvuru
/sinav-ve-basvuru/nasil-basvururum
/sinav-ve-basvuru/online-basvuru
/sinav-ve-basvuru/ucretler
/sinav-takvimi
/belge-yenileme
/sonuc-ve-belge-sorgulama
/duyurular
/duyurular/{slug}
/haberler
/haberler/{slug}
/dokumanlar
/kurumsal/hakkimizda
/kurumsal/yetki-ve-akreditasyon
/kurumsal/politikalar/{slug}
/referanslar
/kariyer
/iletisim
```

### 14.1. Slug ilkeleri

- Türkçe karakterler URL'de normalize edilmeli.
- Kelimeler tireyle ayrılmalı.
- Tekil ve anlamlı olmalı.
- WordPress `-2` ekleri taşınmamalı.
- Tek canonical trailing-slash politikası seçilmeli.
- MYK kodu sayfa içeriğinde ve aramada bulunmalı; URL'ye eklenip eklenmeyeceği SEO ve kalıcılık açısından tasarım aşamasında kararlaştırılmalı.

### 14.2. Eski URL geçişi

Her eski URL için şu alanlar tutulmalıdır:

| Eski URL | İçerik türü | Yeni URL | Karar | Onaylayan | Test sonucu |
|---|---|---|---|---|---|

Kararlar:

- bire bir karşılık → 301,
- birleştirilmiş içerik → ilgili yeni sayfaya 301,
- karşılığı olmayan ve bilinçli kaldırılan içerik → 410,
- geçici olarak taşınmayan içerik → yayından önce yeniden değerlendirme.

Toplu şekilde her şeyi ana sayfaya yönlendirmek yapılmamalıdır.

---

## 15. Güvenlik, gizlilik ve operasyon ilkeleri

- HTTPS ve HSTS.
- Uygun Content Security Policy.
- `X-Content-Type-Options`, frame politikası, referrer ve permissions politikaları.
- CSRF koruması.
- Sunucu tarafı doğrulama.
- Rate limit.
- Güvenli oturum ve cookie ayarları.
- Yönetim panelinde çok faktörlü doğrulama seçeneği.
- Hassas ayarların kaynak kod dışında tutulması.
- Dosya MIME/boyut/tarama kontrolleri.
- Private depolama ve süreli erişim bağlantıları.
- Yetki bazlı veri erişimi.
- Denetim izi.
- Veri saklama ve silme politikası.
- Günlük yedek, şifreleme ve geri yükleme testi.
- Bağımlılık ve güvenlik güncelleme süreci.
- OWASP odaklı test.

---

## 16. Performans ve SEO hedefleri

### 16.1. Performans

- Slider ve eklenti kaynaklı tekrarların kaldırılması.
- Sadece gereken CSS/JS'in yüklenmesi.
- WebP/AVIF ve responsive resimler.
- Resim ölçülerinin HTML'de belirtilmesi.
- Font optimizasyonu.
- Kamu sayfası cache ve gerektiğinde CDN.
- Gerçek kullanıcı Core Web Vitals ölçümü.
- Performans bütçesi ve yayın öncesi tarayıcı testi.

### 16.2. SEO

- Her sayfada benzersiz title ve description.
- Tek anlamlı H1.
- Breadcrumb.
- Canonical.
- Open Graph/Twitter meta verileri.
- Organization, LocalBusiness, BreadcrumbList, Article ve uygun olduğunda FAQ yapılandırılmış verisi.
- Temiz sitemap ayrımları.
- Düşük değerli admin/filtre sayfalarında noindex.
- Eski URL yönlendirme testleri.
- 404 izleme.

Yeterlilik sayfaları için `Course` şeması otomatik seçilmemelidir; içerik gerçekten eğitim kursu değil belgelendirme programıdır. Yayından önce uygun schema türü doğrulanmalıdır.

### 16.3. AIO / GEO — yapay zekâ tabanlı aramalarda görünürlük

Yeni sitenin içerik ve teknik SEO yaklaşımı; ChatGPT, Google Gemini, Perplexity, Google AI Overviews ve benzeri yanıt motorlarının içeriği doğru anlayabilmesini hedefleyecektir. Bu hedef klasik SEO'nun alternatifi değil, onun üzerine kurulan ek bir görünürlük katmanıdır.

Temel gereksinimler:

- Kurum adı, yetki numaraları, lokasyonlar ve iletişim bilgilerinde site genelinde tutarlılık.
- Her yeterlilik için açık soru-cevap yapısı ve kısa, doğrudan tanımlar.
- MYK kodu, seviye, revizyon, ücret, geçerlilik ve başvuru adımlarının makinece okunabilir HTML olarak sunulması.
- Güncelleme tarihi, içerik sorumlusu ve kaynak kurum bağlantılarının görünür olması.
- Uygun ve doğrulanmış yapılandırılmış veri.
- Duyuru, mevzuat, ücret ve dokümanlarda sürüm/geçerlilik bilgisinin açık olması.
- Özgün SSS içerikleri; yalnızca anahtar kelime doldurmak için yapay metin üretilmemesi.
- Hızlı, erişilebilir ve sunucu tarafında render edilen sayfalar.
- Botlara özel farklı içerik sunulmaması.
- Yapay zekâ kaynak gösterimi ve marka görünürlüğünün ölçülebilmesi için periyodik sorgu setleri ve kayıtlı değerlendirme süreci.

`llms.txt` gibi yeni veya standartlaşmamış yöntemler tek başına çözüm kabul edilmeyecektir. Uygulama aşamasında güncel arama motoru ve yapay zekâ platformu yönergeleri yeniden doğrulanacaktır.

### 16.4. Analitik ve çerez kararı henüz açık

AIO/GEO bir görünürlük hedefidir; ziyaretçi analitiği ve çerez yönetimi değildir. Ayrı olarak şu kararlar verilmelidir:

- Ziyaretçi ölçümü için hangi araç kullanılacak?
- Çerezsiz/az verili analitik tercih edilecek mi?
- Arama, yeterlilik görüntüleme, dış sınav takvimi tıklaması ve başvuru dönüşümü hangi olaylarla ölçülecek?
- Kullanılacak araç çerez veya kişisel veri işliyorsa izin yönetimi nasıl yapılacak?

Bu karar verilene kadar zorunlu olmayan analitik veya pazarlama çerezi varsayılmayacaktır.

---

## 17. Aşamalı proje planı

### Faz 0 — Karar ve içerik doğrulama

- Karara bağlanan kapsam esas alınır; kalan teknik doğrulamalar tamamlanır.
- Hosting ve teknik ortam netleştirilir.
- 83 görünür yeterlilik kurum tarafından doğrulanır.
- MYK kod, seviye, revizyon ve kapsamları kontrol edilir.
- Ücret, banka, adres, yetki ve KVKK içerikleri onaylanır.
- Eski içerik matrisi hazırlanır.

### Faz 1 — Ürün ve tasarım temeli

- Kesin site haritası.
- Kullanıcı akışları.
- Wireframe'ler.
- Tasarım sistemi.
- Mobil/masaüstü prototip.
- Sayfa şablonları ve içerik alanları.

### Faz 2 — Teknik iskelet

- Laravel proje iskeleti.
- Desteklenen sürümlerin kilitlenmesi.
- Veritabanı migration'ları.
- Kimlik, rol ve yetki.
- Admin kabuğu.
- Ortak ön yüz layout ve bileşenleri.
- Test altyapısı.

### Faz 3 — Çekirdek içerik modülleri

- Sektörler.
- Yeterlilikler.
- Dokümanlar.
- Ücretler.
- Haber/duyuru.
- Sayfalar.
- Lokasyonlar.
- Referanslar.
- SEO ve yönlendirmeler.

### Faz 4 — Başvuru ve iletişim iş akışları

- Tam online başvuru.
- Güvenli belge yükleme.
- Başvuru durum geçmişi.
- Yönetici inceleme ve durum değiştirme ekranları.
- Adaya e-posta bildirimleri.
- İletişim kayıtları.
- İş başvurusu.
- Kalite prosedürüyle uyumlu itiraz/şikayet akışı.
- Sınav takvimi ve MYK sorgusu için açıklamalı dış bağlantılar.

### Faz 5 — Veri geçişi

- WordPress içeriğinin aktarımı.
- Karakter ve yazım düzeltmeleri.
- PDF/görsel taşıma ve hash kontrolü.
- 83 yeterlilik eşleştirmesi.
- 145 eski URL kararının uygulanması.
- İçerik sahibi onayı.

### Faz 6 — Kabul ve yayın

- Mobil, tablet ve masaüstü tarayıcı testleri.
- Erişilebilirlik testi.
- Form ve dosya güvenliği.
- SEO meta ve sitemap.
- 301/410 otomatik testleri.
- Yedek/geri yükleme.
- Hata izleme.
- Kademeli yayın ve geri dönüş planı.

Süre tahmini, bu fazların kapsamı ve Claude ile uygulanacak iş paketleri kesinleşince yapılmalıdır.

---

## 18. Karara bağlanan kapsam ve kalan açık konular

### 18.1. Karara bağlananlar

| Konu | Karar |
|---|---|
| Hosting | Mevcut hosting kullanılacak; Laravel teknik uygunluğu doğrulanacak |
| Backend | Laravel |
| Admin paneli | Hazır Laravel admin kütüphanesi |
| Başvuru | Tam belge yükleme ve durum yönetimi |
| Sınav takvimi | Yalnızca mevcut dış bağlantı |
| Aday portalı | İlk sürümde yok |
| Online ödeme | Yok |
| Bildirim | Yalnızca e-posta; SMS yok |
| Dil | Yalnızca Türkçe |
| Sektör adları | “Ulaştırma & Lojistik” ve “Maden & Mermer” kesin |
| Doküman merkezi | Herkese açık ve versiyonlu |
| Eski haberler | Tamamı görselleriyle taşınacak |
| Sosyal sorumluluk | Sayfalar korunacak |
| Logo | Mevcut logo korunacak |
| Tasarım | Kurumsal, sade, modern |
| Yapay zekâ görünürlüğü | AIO/GEO hedefi dahil |

### 18.2. Teknik incelemeyle netleştirilecekler

1. Mevcut hostingin Laravel, queue, cron, private depolama ve e-posta gereksinimlerini karşılayıp karşılamadığı.
2. Kullanılacak Laravel admin kütüphanesi.
3. Laravel/PHP/veritabanı sürüm kilidi.
4. E-posta servisinin gönderim kotası ve teslim güvenilirliği.
5. Tam başvuruda zorunlu alanlar, belge türleri, dosya boyutları ve saklama süreleri.
6. Başvuru durumları, izin verilen geçişler ve sorumlu roller.
7. İtiraz/şikayet akışının kalite prosedürü ve SLA kuralları.
8. Dokümanların herkese açık olanları ile yalnızca yönetim/aday erişimine açık olanların ayrımı.
9. Analitik aracı ve çerez yaklaşımı.
10. AIO/GEO başarısını ölçmek için kullanılacak sorgu ve raporlama yöntemi.

---

## 19. Claude ile kodlama çalışma yöntemi

Kodlama Visual Studio Code içinde Claude tarafından yapılacağı için görevler büyük tek prompt halinde verilmemelidir. Her prompt bir iş paketi olmalı ve şu yapıyı taşımalıdır:

1. Amaç.
2. Ön koşullar ve mevcut durum.
3. Değiştirilebilecek dosya/kapsam.
4. Değiştirilmemesi gereken alanlar.
5. Veri modeli ve iş kuralları.
6. Tasarım/erişilebilirlik gereksinimleri.
7. Güvenlik gereksinimleri.
8. Testler.
9. Kabul kriterleri.
10. Teslim sonunda istenecek kanıtlar.

Önerilen çalışma döngüsü:

1. Bu rapordaki kararlar kullanıcıyla kesinleştirilir.
2. Site haritası ve tasarım temeli hazırlanır.
3. Ben her aşama için Claude'a uygulanabilir görev promptu hazırlarım.
4. Claude kodu uygular ve test sonuçlarını sunar.
5. Değişiklikler gerçek dosya ve tarayıcı kanıtlarıyla incelenir.
6. Kabul edilmeden sonraki büyük faza geçilmez.

Bu yöntem, mimari kararların kod sırasında rastgele değişmesini ve birbirine bağlı işlerin aynı anda bozulmasını önler.

---

## 20. Kodlama öncesi kabul edilen temel ilkeler

Aşağıdaki ilkeler iki rapordan çıkan ve şimdiden güvenle kabul edilebilecek ortak zemindir:

- Mevcut sitedeki bilgi kaybedilmeyecek; her içerik için taşıma kararı verilecek.
- Yeni sistem görev odaklı ve sade olacak.
- Meslek/yeterlilikler yapılandırılmış veriye dönüşecek.
- Ücretler JPG değil veri tabanlı HTML olacak.
- Haber ve duyurular ayrılacak.
- Dokümanlar versiyonlu yönetilecek.
- SEO alanları her içerikte yönetilebilir olacak.
- Eski URL'ler kontrolsüz biçimde kırılmayacak.
- Mobil ve erişilebilirlik sonradan eklenen özellik değil, başlangıç gereksinimi olacak.
- Kişisel dosyalar public tutulmayacak.
- Admin işlemleri rol/yetki ve denetim iziyle yönetilecek.
- Kodlama Laravel yönünde planlanacak; kesin sürüm ve admin aracı başlangıçta doğrulanacak.
- Admin panelinde uygun bir hazır Laravel kütüphanesi kullanılacak.
- İlk sürüm tam başvuru ve durum yönetimini içerecek.
- Aday portalı, ödeme, SMS, çoklu dil ve yerel sınav takvimi ilk sürümde olmayacak.
- Eski haberler görselleriyle, sosyal sorumluluk içerikleri ve mevcut logo korunarak taşınacak.
- AIO/GEO hedefi klasik teknik SEO ve içerik güvenilirliği üzerine kurulacak.

---

## 21. Yayın kabul kriterleri

Yeni site yayınlanmadan önce:

- 83 görünür yeterliliğin tamamı eşleştirilmiş veya kaldırma kararı onaylanmış olmalı.
- 145 eski temel URL'nin her biri 200, doğru 301 veya bilinçli 410 sonucu vermeli.
- Meslek adı, MYK kodu, seviye ve sektörle arama çalışmalı.
- Ücretler erişilebilir HTML tablo olarak yönetilebilmeli.
- Her sayfada benzersiz title, description ve anlamlı H1 bulunmalı.
- Form alanları etiketli ve klavye ile kullanılabilir olmalı.
- Tam başvuru; güvenli dosya yükleme, durum geçmişi, yetkili yönetici işlemleri ve e-posta bildirimleriyle çalışmalı.
- Mobilde yatay taşma olmamalı.
- Dış sistem bağlantıları açıkça belirtilmeli.
- Kişisel dosyalar public URL'den erişilememeli.
- Fiyat, banka, yetki ve yasal içerik değişiklikleri kayda alınmalı.
- 301/410 listesi otomatik test edilmeli.
- Yedekten geri yükleme denenmiş olmalı.
- Temel güvenlik ve erişilebilirlik testleri geçmeli.

---

## 22. Nihai değerlendirme

İki rapor birbirini büyük ölçüde desteklemektedir. Claude raporu sayfa bazlı tutarsızlık ve önerilen uygulama şablonlarında daha somut; GPT raporu sayısal doğrulama, erişilebilirlik, güvenlik, kapsam kontrolü ve kabul kriterlerinde daha güçlüdür.

Birleştirilmiş değerlendirmeye göre doğru başlangıç:

1. Önce kapsam kararlarını vermek.
2. 83 yeterlilik için doğrulanmış içerik matrisi hazırlamak.
3. Yeni site haritası ve wireframe üzerinde mutabık kalmak.
4. Laravel tabanlı çekirdek veri modelini kesinleştirmek.
5. Claude'a küçük, test edilebilir ve kabul kriterli iş paketleri vermek.

Bu belge, bundan sonraki görüşmelerde ana referans olarak kullanılmalıdır. Kaynak iki rapor araştırma ve kanıt amacıyla korunmalı; uygulama kararı gerektiğinde öncelik `final-rapor.md` belgesine verilmelidir.

---

## 23. Başlıca doğrulama kaynakları

- https://mavibelge.com.tr/
- https://mavibelge.com.tr/wp-sitemap.xml
- https://mavibelge.com.tr/wp-sitemap-posts-page-1.xml
- https://mavibelge.com.tr/wp-sitemap-posts-post-1.xml
- https://mavibelge.com.tr/wp-sitemap-posts-logosliderwp-1.xml
- https://mavibelge.com.tr/wp-sitemap-taxonomies-category-1.xml
- https://mavibelge.com.tr/wp-sitemap-taxonomies-post_tag-1.xml
- https://mavibelge.com.tr/makine/
- https://mavibelge.com.tr/liman-meslekleri/
- https://mavibelge.com.tr/elektrik/
- https://mavibelge.com.tr/cam/
- https://mavibelge.com.tr/mermer-meslekleri/
- https://mavibelge.com.tr/basvuru-formlari/
- https://mavibelge.com.tr/fiyat-list/
- https://mavibelge.com.tr/mevzuat/
- https://mavibelge.com.tr/kvkk-2/
- https://mavibelge.com.tr/iletisim/
