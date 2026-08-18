# mavibelge.com.tr — Mevcut Durum İnceleme Raporu ve Yeni Sistem Mimarisi

**Hazırlanma tarihi:** 17 Ağustos 2026
**Kapsam:** https://mavibelge.com.tr/ sitesinin tüm sayfa yapısı, içerik sınıflandırması, teknik altyapısı ve tespit edilen sorunlar; yeni PHP tabanlı, admin panel yönetimli sistem için mimari öneri.
**Yöntem:** Sitenin `wp-sitemap.xml` indeksi üzerinden tam URL envanteri çıkarıldı; ana sayfa, tüm ana kategori (sektör) sayfaları, temsili program detay sayfaları, süreç sayfaları, form sayfaları ve `robots.txt` doğrudan çekilerek analiz edildi.

---

## 1. Yönetici Özeti

Mavi Belge sitesi, teknik olarak **WordPress + WPBakery Page Builder + özel (custom) tema** üzerine kurulu, 2016–2019 döneminin yapısını taşıyan bir kurumsal sitedir. İçerik açısından **zengindir** — 12 sektör, ~60 belgelendirme programı, süreç metinleri, mevzuat, referanslar, haberler — ancak bu içerik **yapısal olarak modellenmemiştir**. Her şey "sayfa" olarak tutulmuş, sayfalar arası ilişkiler manuel link ile kurulmuş, ücret gibi kritik veriler ise **JPG görsel** olarak yayınlanmıştır.

Bunun pratik sonuçları:

- Sitedeki **her sayfanın `<title>` etiketi aynı**: "Mavi Belge Mesleki Yeterlilik ve Belgelendirme". Meta description yok. Bu, arama motoru görünürlüğü açısından en ağır tek problemdir.
- Aynı kavram üç farklı isimle geçiyor (menüde "ULAŞTIRMA & LOJİSTİK", sayfa başlığında "LİMAN MESLEKLERİ", footer'da yine "LİMAN"). Üst menüde 12 sektör var, footer'da 10 — Elektrik ve Cam footer listesinde yok.
- Bazı programların detay sayfası var, bazılarının yok (Elektrik ve Cam sektörlerinin tamamı, Liman'ın yarısı sadece kategori sayfasında düz metin). Kullanıcı için tutarsız, yönetim için sürdürülemez.
- Ücret tarifesi **görsel** olarak yayınlanıyor → aranamaz, mobilde okunamaz, ekran okuyucu göremez, güncellemek için görsel tasarım gerekiyor.
- Site içinde en az bir **kırık link** (`/insaat/`), birden fazla **yazım hatalı slug** (`mermer-doagltas-...`), ve **10'dan fazla artık/ölü sayfa** mevcut.
- Sınav takvimi tamamen üçüncü parti bir alan adına (`mavibelge.pratikteorik.com`) çıkıyor; "sınav sonucu" ve "belge sorgulama" butonları **aynı MYK URL'sine** gidiyor.

**Sonuç:** Yeniden yapılandırma doğru karardır ve PHP tercihi de yerindedir. Ancak asıl kazanç kodun dilinden değil, **içeriğin veri modeline oturtulmasından** gelecektir. Bu raporun 6. ve 7. bölümleri bu modeli tanımlıyor.

---

## 2. Mevcut Sistemin Teknik Künyesi

| Bileşen | Tespit | Kanıt |
|---|---|---|
| CMS | WordPress | `/wp-content/`, `/wp-sitemap.xml`, `/wp-admin/` |
| Sayfa kurucu | WPBakery Page Builder (eski adıyla Visual Composer) | `meta-generator: Powered by WPBakery Page Builder` |
| Tema | Özel tema, klasör adı `MaviBelge` | `/wp-content/themes/MaviBelge/images/header-logo.png` |
| Sitemap | WordPress 5.5+ yerleşik sitemap | `wp-sitemap.xml` (Yoast/RankMath **yok**) |
| SEO eklentisi | **Yok** | Tüm sayfalarda aynı title, meta description yok |
| Form eklentisi | Contact Form 7 (yüksek olasılık) | Formlardaki gizli `Δ` alanı, `1+2?` quiz alanı CF7 imzasıdır |
| Logo/slider eklentisi | LogoSliderWP | `wp-sitemap-posts-logosliderwp-1.xml` özel içerik tipi |
| İkon seti | Google Material Icons (metin olarak sızıyor) | Formlarda `account_circle`, `phone`, `work`, `file_copy` |
| Medya | `/wp-content/uploads/YYYY/MM/` | 2019–2026 arası birikmiş |
| robots.txt | Varsayılan WP çıktısı | `Disallow: /wp-admin/` + sitemap satırı |
| İçerik tipleri | `post`, `page`, `logosliderwp` | Sitemap indeksinden |
| Taksonomi | Sadece `category` → `haber`, `uncategorized` | `wp-sitemap-taxonomies-category-1.xml` |

**Kritik gözlem:** Site 12 sektör ve ~60 mesleki yeterlilik programı yayınlıyor ama bunların hiçbiri özel içerik tipi (CPT) veya taksonomi olarak modellenmemiş. Hepsi düz `page` olarak, WPBakery ile elle dizilmiş. Bu, mevcut karmaşıklığın tek ve temel kaynağıdır.

---

## 3. Tam Sayfa Envanteri

Sitemap'e göre toplam: **~120 sayfa (`page`)**, **31 yazı (`post`)**, **2 kategori**.

### 3.1 Kurumsal (7 sayfa)

| Başlık | URL | Son güncelleme |
|---|---|---|
| Mavi Belge Hakkında | `/sirket-hakkinda/` | 17.05.2026 |
| Misyon & Vizyon | `/misyonumuz/` | 12.07.2019 |
| Kalite Politikamız | `/kalite-politikamiz/` | 12.07.2019 |
| Üst Yönetimin Tarafsızlık Beyanı | `/ust-yonetimin-tarafsizlik-beyani/` | 05.08.2025 |
| Yasal Dayanağımız | `/yasal-dayanagimiz/` | 12.07.2019 |
| Gizlilik Politikamız | `/gizlilik-politikamiz/` | 12.07.2019 |
| KVKK | `/kvkk-2/` | 23.07.2025 |

### 3.2 Mesleki Yeterlilik / Bilgilendirme (5 sayfa)

`/mesleki-yeterlilik-kurumu-myk/`, `/turk-akreditasyon-kurumu-turkak/`, `/ulusal-meslek-standartlari-ums-ve-ulusal-yeterlilikler-uy/`, `/mevzuat/`, `/neden-zorunlu/`

### 3.3 Sınav & Belgelendirme (8 sayfa)

`/sinav-ve-belgelendirme-sureci/`, `/fiyat-list/`, `/sinav-ve-belge-kurallari/`, `/itiraz-ve-sikayetler/`, `/belge-iptali-ve-askiya-alma/`, `/logo-kullanim-talimati/`, `/aday-taahhutnamesi/`, `/belge-yenileme-sureci/`, `/banka-hesap-bilgileri/`, `/yetki-belgelerimiz/`

### 3.4 Sektörler — 12 kategori sayfası

| # | Sektör (menüdeki ad) | URL | Sayfa başlığı | Detay sayfalı program |
|---|---|---|---|---|
| 1 | Makine Meslekleri | `/makine/` | MAKİNE MESLEKLERİ… | 13 / 13 |
| 2 | Metalurji Meslekleri | `/metalurji/` | METALURJİ… | 7 / 7 |
| 3 | Metal Meslekleri | `/metal-meslekleri/` | METAL… | 5 / 5 |
| 4 | **Ulaştırma & Lojistik** | `/liman-meslekleri/` | **LİMAN MESLEKLERİ…** | 4 / 12 |
| 5 | Plastik Meslekleri | `/plastik-meslekleri/` | PLASTİK… | 3 / 3 |
| 6 | Elektrik Meslekleri | `/elektrik/` | ELEKTRİK… | **0 / 5** |
| 7 | Cam Meslekleri | `/cam/` | CAM… | **0 / 3** |
| 8 | Tekstil Meslekleri | `/tekstil/` | TEKSTİL… | 6 / 6 |
| 9 | İnşaat Meslekleri | `/insaat-meslekleri/` | İNŞAAT… | 12 / 12 |
| 10 | Mobilya Meslekleri | `/mobilya-meslekleri/` | MOBİLYA… | 3 / 3 |
| 11 | **Maden & Mermer** | `/mermer-meslekleri/` | **MERMER MESLEKLERİ…** | 3 / 3 |
| 12 | Güzellik ve Saç Bakım | `/guzellik-ve-sac-bakim/` | GÜZELLİK VE SAÇ BAKIM… | 7 / 7 |

### 3.5 Program (yeterlilik) detay sayfaları — ~60 adet

Sektörlere göre gruplanmış, tespit edilen MYK kodlarıyla:

**Makine:** 10UY0002-3/03 Makine Bakımcı · 10UY0002-4/03 Makine Bakımcı · 10UY0002-5/03 Makine Bakımcı · 12UY0105-3/00 Makine Montajcısı · 12UY0083-3/02 Metal Kesimci · 12UY0084-4/02 Metal Kesim Operatörü · 12UY0086-3/02 Metal Levha İşleme Tezgâh İşçisi · 12UY0087-4/02 Metal Levha İşleme Tezgâh Operatörü · 14UY0202-3/01 NC/CNC Tezgâh İşçisi · 14UY0202-4/01 NC/CNC Tezgâh İşçisi · 18UY0351-4/00 Tel Makineleri Operatörü · 15UY0227-3/00 Tornacı · 12UY0081-3/00 Frezeci

**Metalurji:** 13UY0148-3/01 Haddeci · 13UY0148-4/01 Haddeci · 12UY0070-3/00 Refrakterci · 12UY0070-4/00 Refrakterci · 13UY0149-4/00 İzabeci · 13UY0178-3/00 Maçacı · 13UY0173-4/00 Dökümcü

**Metal:** 11UY0013-3/02 Endüstriyel Boru Montajcısı · 11UY0010-3/04 Çelik Kaynakçısı · 11UY0014-3/02 Alüminyum Kaynakçısı · 11UY0016-4/03 Kaynak Operatörü · 11UY0015-4/03 Direnç Kaynak Ayarcısı

**Ulaştırma & Lojistik (Liman):** 15UY0218-2/01 İşaretçi · 12UY0061-3/04 Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci) · 12UY0088-3/04 Liman Forklift Operatörü · 13UY0170-3/02 Liman Kuru Yük Operasyon Elemanı (Puantör) · *(detay sayfası olmayanlar:)* 13UY0145-3 Endüstriyel Taşımacı · 15UY0220-4 Liman Operasyon Planlamacısı · 12UY0063-3 Liman Pompa ve Tank Saha Operatörü · 17UY0268-3 Liman RTG Operatörü · 12UY0064-3 Liman Saha İstif Makineleri Operatörü (CRS/ECS) · 17UY0269-3 Liman SSG Operatörü · 15UY0221-3 Terminal Çekici Operatörü · 17UY0328-3 Servis Aracı Şoförü

**Elektrik (hiçbirinin detay sayfası yok):** 12UY0075-3 Elektrik Pano Montajcısı · 15UY0241-3 Elektrik Tesisatçısı · 15UY0241-4 Elektrik Tesisatçısı · 15UY0206-3 Elektro-Mekanik Montaj İşçisi · 13UY0121-5 İşletme Elektrik Bakımcısı

**Cam (hiçbirinin detay sayfası yok):** 18UY0356-4 Endüstriyel Cam Isıl İşlem Elemanı · 18UY0357-4 Endüstriyel Cam İşleme Elemanı · 18UY0358-4 Endüstriyel Cam Kesim Elemanı

**Tekstil:** 11UY0036-2/01 İplik Bitim İşleri Operatörü · 11UY0037-2/01 İplik Eğirme Operatörü · 11UY0039-3/02 Ön İplik Operatörü · 13UY0137-3/01 Bitim İşlemleri Operatörü · 13UY0138-3/01 Boyama Operatörü · 13UY0139-3/01 Ön Terbiye Operatörü

**İnşaat:** 11UY0011-3/03 Ahşap Kalıpçı · 11UY0012-3/03 Betonarme Demircisi · 11UY0024-3/02 Sıvacı · 11UY0023-3/02 İnşaat Boyacısı · 12UY0054-3/00 Alçı Levha Uygulayıcısı · 12UY0055-3/00 Alçı Sıva Uygulayıcısı · 12UY0048-3/01 Duvarcı · 16UY0253-2/00 İnşaat İşçisi · 14UY0195-3/00 PVC Doğrama Montajcısı · 12UY0051-3/01 Seramik Karo Kaplamacısı · 12UY0057-3/01 Isı Yalıtımcısı · 12UY0056-3/01 İskele Kurulum Elemanı

**Plastik:** Plastik Enjeksiyon Üretim Elemanı (S3) · Plastik Profil Üretim Operatörü — Ekstrüzyon (S3) · Plastik Şişirme Film Üretim Operatörü — Ekstrüzyon (S3)

**Mobilya:** Ahşap Mobilya İmalatçısı (S3) · Ahşap Mobilya İmalatçısı (S4) · Mobilya Döşemecisi (S3)

**Maden & Mermer:** Mermer-Doğaltaş Ocakçısı (S3) · Mermer-Doğaltaş İmalat Elemanı (S3) · Mermer-Doğaltaş Özel İmalat Elemanı (S4)

**Güzellik ve Saç Bakım:** 18UY0344-4/00 Epilasyon Uzmanı · 16UY0244-4/02 Güzellik Uzmanı · 16UY0245-4/02 Kuaför · 17UY0280-3/01 Cilt Bakım Uygulayıcısı · 17UY0286-3/01 Kozmetik Ürünler Tanıtım ve Uygulama Elemanı · 16UY0242-3/02 Makyaj Uygulayıcısı · 16UY0247-3/00 Protez Tırnak Uygulayıcısı

### 3.6 Form / Etkileşim sayfaları (4)

`/basvuru-formlari/` (Online Başvuru — ad-soyad, e-posta, telefon, dosya yükleme, meslek, matematik quiz) · `/is-basvurusu/` · `/itiraz-ve-sikayetler/` · ana sayfadaki "Sorum Var" formu

### 3.7 Haberler / Duyurular (31 yazı, 2016–2026)

Öne çıkanlar: `/bilgilendirme/` (deprem duyurusu, 2023) · `/belge-yenileme-ucretleri/` (güncel — 14.01.2026) · `/mobilya-sektoru-belge-zorunlulugu/` · `/myk-belgesi-zorunlulugu-getirilen-yeni-meslekler/` ve `-2/` · `/6-dilde-myk-belgesi-gecerliligi/` · `/gecerlilik-suresi-dolan-myk-...-duyurular/` · `/3114-2/` (MÜLTECİLERİN BELGELENDİRİLMESİ — anlamsız slug)

### 3.8 Referanslar

`/referanslar/` + ana sayfada ~53 firma logosu (LogoSliderWP CPT). Örnek: Tosyalı, Sanko, Kipaş, İsko, Çalık Denim, Coats, DSİ, Toyota Boshoku, Tersan, Hatay BB, Bayraklı Belediyesi…

### 3.9 Ölü / artık / şüpheli sayfalar (temizlenmeli)

| URL | Durum |
|---|---|
| `/slider/` | Tema altyapı sayfası, içerik değil |
| `/logos/` | Aynı şekilde altyapı sayfası |
| `/online-basvuru/` | `/basvuru-formlari/` ile mükerrer (2019'dan beri güncellenmemiş) |
| `/basvuru-formlari-2/` | Mükerrer (2021) |
| `/belge-yenileme/` | `/belge-yenileme-sureci/` ile mükerrer |
| `/pandemi-surecinde-alinan-tedbirler1/` | Güncelliğini yitirdi (2020) |
| `/sosyal-sorumluluk/`, `/beyteks-sorumluluk/`, `/matkim-2/`, `/crt-metal/`, `/sekizlivinc-2/` | Menüde yok, erişilemiyor — muhtemelen proje sayfaları |
| `/mesleklerimiz/` | Haber olarak yayınlanmış, sektör listesiyle karışıyor |

---

## 4. Bilgi Mimarisi Analizi

### 4.1 Mevcut navigasyon

Üst menü 8 ana başlık: ANASAYFA · KURUMSAL · MESLEKİ YETERLİLİK · SINAV BELGELENDİRME · MESLEKLERİMİZ · MEDYA · REFERANSLARIMIZ · İNSAN KAYNAKLARI · İLETİŞİM

Bunun **dışında** üç ayrı navigasyon katmanı daha var:

1. **Header ikon şeridi:** Başvuru Formu · Sınav Sonucu · Belge Sorgulama · Sınav Takvimi · Europass
2. **Yatay hızlı erişim şeridi:** sınav ücretleri · banka hesap bilgileri · sınav sonuçları · Belge Yenileme Süreci · online başvuru · neden zorunlu? · yetki belgelerimiz
3. **Footer:** Hakkında özeti + "Yetkili Olduğumuz Mesleklerimiz" (10 kalem) + iletişim + sosyal medya

Yani kullanıcı aynı hedefe **üç farklı yerden** ulaşabiliyor ve bu üç yer birbiriyle tutarlı değil. `neden-zorunlu`, `banka-hesap-bilgileri`, `belge-yenileme-sureci`, `yetki-belgelerimiz` **sadece** hızlı erişim şeridinde var — ana menüde yok. Bu, sitenin "karışık" hissettirmesinin birinci nedenidir.

### 4.2 Sınıflandırma tutarsızlıkları

| Konu | Üst menü | Sayfa H1 | Footer |
|---|---|---|---|
| Liman/lojistik | ULAŞTIRMA & LOJİSTİK MESLEKLERİ | LİMAN MESLEKLERİ | LİMAN MESLEKLERİ |
| Mermer | MADEN & MERMER MESLEKLERİ | MERMER MESLEKLERİ | MERMER MESLEKLERİ |
| Elektrik | Var | Var | **Yok** |
| Cam | Var | Var | **Yok** |

Ayrıca `liman-meslekleri` slug'ı artık içeriği karşılamıyor: sayfa Servis Aracı Şoförü ve Endüstriyel Taşımacı gibi limanla ilgisi olmayan yeterlilikleri de barındırıyor.

### 4.3 Sayfa şablonu tipleri (mevcut)

Mevcut sitede fiilen **6 şablon** var:

1. **Ana sayfa** — slider, hızlı erişim, galeri, "Sorum Var" formu, haber kartları, referans logoları
2. **Sektör listesi** — H2 başlık + program kartları (görsel + başlık + açıklama)
3. **Program detay** — H2 başlık + dikey görsel + "Tanımı" + mevzuat paragrafı + PDF linki + sektör çapraz linkleri
4. **Statik metin** — kurumsal/mevzuat/süreç sayfaları
5. **Form** — açıklama + CF7 formu
6. **Görsel galeri** — `/fiyat-list/`, `/yetki-belgelerimiz/` (sadece resim)

Şablon 2 ve 3 arasında **ciddi kopukluk** var: Elektrik ve Cam sektörlerinde şablon 3 hiç kullanılmamış, içerik şablon 2'nin içine gömülmüş.

---

## 5. Tespit Edilen Sorunlar

Önem sırasına göre, kanıtlarıyla:

### 5.1 Kritik — SEO ve bulunabilirlik

| # | Sorun | Kanıt / Etki |
|---|---|---|
| K1 | **Tüm sayfalarda aynı `<title>`** | Ana sayfa, `/makine/`, `/fiyat-list/`, `/iletisim/` — hepsi "Mavi Belge Mesleki Yeterlilik ve Belgelendirme". Google arama sonuçlarında sayfalar birbirinden ayırt edilemiyor. |
| K2 | **Meta description yok** | Hiçbir sayfada `<meta name="description">` yok. Arama sonuç kartları rastgele metin gösteriyor. |
| K3 | **Yapısal veri (schema.org) yok** | `Organization`, `Course`, `FAQPage`, `BreadcrumbList`, `LocalBusiness` hiçbiri yok. |
| K4 | **Ücret tarifesi görsel** | `/fiyat-list/` sayfası 6 adet JPG (`2026-YENI-MESLEKLI-UCRET-TARIFESI_Sayfa_1.jpg` vb.). "Kuaför belgesi ücreti" araması bu sayfayı asla bulamaz. Ayrıca bir görsel `-scaled` ekiyle, yani optimize edilmemiş büyük dosya. |
| K5 | **Breadcrumb yok** | Kullanıcı 3. seviyedeki bir program sayfasında nerede olduğunu bilmiyor. |
| K6 | **Çoklu dil yok** | Site "MYK belgesi 6 dilde geçerlidir" diyor ama kendisi yalnızca Türkçe. Suriyeli/yabancı aday hedef kitlesi var (`/3114-2/` MÜLTECİLERİN BELGELENDİRİLMESİ yazısı bunu doğruluyor). |

### 5.2 Kritik — Bilgi mimarisi ve tutarlılık

| # | Sorun | Kanıt |
|---|---|---|
| K7 | **Yanlış hedefe giden iç link** | `/makine-bakimci-3-belgelendirme-programi/` sayfasındaki "İnşaat Meslekleri… Tıklayınız" linki `/insaat/` adresine gidiyor. Bu adres 404 vermiyor — WordPress'in kısmi slug eşleştirmesi devreye girip kullanıcıyı **`/insaat-boyacisi-belgelendirme-programi/` sayfasına** atıyor. Yani aday, İnşaat sektör listesi beklerken tek bir mesleğin detay sayfasında buluyor kendini. Doğrusu `/insaat-meslekleri/`. Tespit edilebilir en sinsi hata türü: analitikte 404 olarak görünmez. |
| K7b | **Çapraz link bloğu sayfadan sayfaya farklı** | Program detay sayfalarının altındaki "diğer sektörler" bloğu elle kopyalanmış: `/insaat-boyacisi-.../` sayfasında link doğru (`/insaat-meslekleri/`), `/makine-bakimci-3-.../` sayfasında hatalı (`/insaat/`). Ayrıca bu blok yalnızca 6 sektörü listeliyor — 12 sektörün 6'sı (Liman, Plastik, Elektrik, Cam, Mobilya, Mermer) hiçbir program sayfasından erişilemiyor. |
| K8 | **Hatalı/eksik slug'lar** | `mermer-doagltas-imalat-elemani-3-...` ve `mermer-doagltas-ozel-imalat-elemani-4-...` ("doğaltaş" → "doagltas" yazım hatası) · `haddeci-3-belgelendirme-program` (sondaki "ı" eksik) · `endustriyel-boru-montajcisibelgelendirme-programi` (kelimeler bitişik) · `kvkk-2`, `guzellik-uzmani-2`, `kuafor-2`, `direnc-kaynak-ayarcisi-2`, `3114-2` (WordPress otomatik `-2` ekleri) · `fiyat-list` (Türkçe-İngilizce karışık) |
| K9 | **Trailing slash tutarsızlığı** | Kategori sayfalarında bazı linkler `/metal-kesimci-3` (slash yok), bazıları `/tornaci-3-belgelendirme-programi/` (slash var). Bir link `/nc-cnc-tezgah-iscisi-3-` şeklinde sondaki tire ile hatalı. |
| K10 | **Sektör adlandırma çakışması** | Bkz. 4.2 tablosu. Menü/başlık/footer üç farklı isim kullanıyor. |
| K11 | **Footer eksik** | Elektrik ve Cam sektörleri footer meslek listesinde yok. |
| K12 | **Program detay sayfası tutarsızlığı** | Elektrik (5), Cam (3) ve Liman'ın 8 programı için detay sayfası yok; bilgi kategori sayfasına düz metin olarak gömülü. |
| K13 | **Mükerrer sayfalar** | `/online-basvuru/` ↔ `/basvuru-formlari/` ↔ `/basvuru-formlari-2/`; `/belge-yenileme/` ↔ `/belge-yenileme-sureci/` |
| K14 | **İki buton tek hedef** | Header'daki "Sınav Sonucu" ve "Belge Sorgulama" ikonlarının ikisi de aynı MYK URL'sine (`portal.myk.gov.tr/...aday_bilgi_sorgu`) gidiyor. |

### 5.3 Yüksek — İçerik kalitesi

| # | Sorun | Kanıt |
|---|---|---|
| Y1 | **Karakter kodlaması bozuk** | `/elektrik/` sayfası, 15UY0206-3 açıklaması: "Elektro-Mekanik Montaj **ĠĢçisi**", "**çalıĢılması**", "**BirleĢtirilmesi**". PDF'ten kopyala-yapıştır sırasında Türkçe karakterler bozulmuş, düzeltilmemiş. |
| Y2 | **Program detay içeriği çok zayıf** | Bir program sayfasında yalnızca "Tanımı" + mevzuat paragrafı + 1 PDF var. **Yok olanlar:** sınav ücreti, ön şart, sınav türleri (teorik/performans/mülakat), yeterlilik birimleri, geçme notu, belge geçerlilik süresi, gözetim periyodu, belge zorunluluğu durumu, teşvik bilgisi. Adayın en çok aradığı bilgiler bunlar. |
| Y3 | **Aynı metnin tekrarı** | Tekstil sektöründeki 6 programın açıklaması neredeyse birebir aynı (sadece meslek adı değişiyor). Cam sektöründe 18UY0356-4 açıklaması **iki kez üst üste** yazılmış. |
| Y4 | **Görsel alt metinleri anlamsız** | `alt="makinebakim"`, `alt="01_y"`, `alt="Company"`, `alt="BU004346"`. Erişilebilirlik ve görsel SEO sıfır. |
| Y5 | **Güncelliğini yitirmiş içerik** | Footer'da sabit "2016 MAVİ BELGE" telif · `/pandemi-surecinde-alinan-tedbirler1/` · Katalog 2022 · Twitter (X) linki eski marka |
| Y6 | **Haber/duyuru ayrımı yok** | 31 yazının hepsi tek kategori. Deprem duyurusu, ücret değişikliği ve fuar katılımı aynı listede. |
| Y7 | **Görsel boyutları düzensiz** | Haber kapakları 300×300, 300×155, 214×300 karışık → ana sayfada hizasız kart ızgarası. Program detay sayfalarında bazen orijinal boyut (`boyaci.jpg`), bazen türetilmiş boyut (`makinebakim-576x1024.jpeg`) kullanılıyor. |
| Y8 | **Doküman adlandırma standardı yok** | Aynı işlevdeki dosyalar farklı isim ve konumda: `2021/08/MBS3-BPF.pdf` (Makine Bakımcı) ↔ `2024/08/T50.INSAAT-BOYACISI-3-BELGELENDIRME-FORMU-INCELEME-TALIMATI.pdf` (İnşaat Boyacısı). Versiyon/revizyon takibi imkânsız; hangi PDF'in güncel olduğu anlaşılmıyor. |

### 5.4 Yüksek — Form, veri ve KVKK

| # | Sorun | Kanıt |
|---|---|---|
| F1 | **Matematik quiz (`1+2?`) captcha olarak yetersiz** | Hem ana sayfa "Sorum Var" hem online başvuru formunda. Bot koruması pratikte sıfır; reCAPTCHA/hCaptcha/Turnstile yok. |
| F2 | **Formlarda açık KVKK rıza kutusu görünmüyor** | Online başvuru formu ad-soyad, e-posta, telefon, **dosya (başvuru formu — TC no dahil kişisel veri içeriyor)** topluyor. Ayrı bir "Aydınlatma metnini okudum / açık rıza veriyorum" onay kutusu ve rıza kaydı (zaman damgası, IP) yok. |
| F3 | **Başvurular veritabanına yazılmıyor** | CF7 varsayılan davranışı yalnızca e-posta göndermektir. Başvuru takibi, durum yönetimi, raporlama yapılamıyor. |
| F4 | **Dosya yükleme güvenlik kontrolü belirsiz** | Uzantı/MIME/boyut kısıtı ve yüklenen dosyanın web'den erişilebilirliği doğrulanamadı — yeni sistemde mutlaka web kök dizini dışına alınmalı. |
| F5 | **Sınav takvimi dış sistemde** | `mavibelge.pratikteorik.com/home/examcalendar` — farklı alan adı, farklı tasarım, site içinde veri olarak yok. Kullanıcı siteden çıkıyor. |

### 5.5 Orta — Performans, erişilebilirlik, teknik borç

| # | Sorun |
|---|---|
| O1 | WPBakery shortcode'ları içeriği kilitliyor — eklenti kaldırılırsa tüm sayfalar bozulur (vendor lock-in) |
| O2 | Ana sayfada ~53 referans logosu + 20 galeri görseli aynı anda yükleniyor; lazy-loading ve WebP yok |
| O3 | Başlık hiyerarşisi bozuk — sayfa başlıkları `<h2>`, program adları `<h5>`, ana sayfada tek `<h1>` yok |
| O4 | Material Icons ligature metni ekrana sızıyor (`account_circle` gibi) — font yüklenmediğinde kullanıcı ham metin görüyor |
| O5 | `robots.txt` varsayılan; `Disallow` ile korunan yönetim yolu yok, güvenlik başlıkları (CSP, HSTS, X-Frame-Options) durumu belirsiz |
| O6 | 2019'dan beri güncellenmemiş çok sayıda sayfa; WordPress + eklenti güncellemeleri her seferinde tema uyumsuzluk riski taşıyor |
| O7 | Ana sayfa galerisinde 20 görsel, hiçbirinin bağlamı/başlığı yok (`01_y`, `03--` gibi) |

---

## 6. Yeni Sistem — Mimari Önerisi

### 6.1 Teknoloji tercihi

PHP kararı doğru. İki seçenek var:

| | **A) Laravel 11/12** | **B) Saf PHP 8.3 + hafif MVC** |
|---|---|---|
| Admin panel geliştirme hızı | Çok yüksek (Filament/Nova ile 1-2 haftada tam panel) | Düşük (her CRUD elle yazılır) |
| Kimlik doğrulama, yetkilendirme, validation, migration | Hazır | Elle veya paketle |
| Güvenlik (CSRF, XSS, SQL injection, şifreleme) | Framework garantisi | Geliştiricinin sorumluluğunda |
| Hosting gereksinimi | Composer + PHP 8.2+, tercihen VPS | Paylaşımlı hosting'de bile çalışır |
| Öğrenme eğrisi | Orta | Yok |
| Uzun vadeli bakım | Kolay (standart yapı, dokümantasyon) | Ekibe bağımlı |

**Öneri: A — Laravel.** Gerekçe: bu projenin ağırlık merkezi "site" değil, **admin panel + başvuru yönetimi**. Laravel'de rol/yetki, dosya yükleme, e-posta bildirimi, Excel dışa aktarma, denetim izi hazır gelir. Paylaşımlı hosting zorunluluğu varsa B'ye dönülebilir; o durumda mutlaka Composer, PDO prepared statement, Twig/Blade benzeri bir şablon motoru ve bir router kullanılmalı — "her sayfa bir .php dosyası" yaklaşımı tekrarlanmamalı.

**Ön yüz:** Framework şart değil. Tailwind CSS (veya CSS değişkenleriyle kendi tasarım sistemi) + vanilla JS yeterli. jQuery ve slider eklentisi yükü kaldırılmalı. Görsellerde WebP/AVIF + `loading="lazy"` + `srcset`.

### 6.2 Dizin yapısı (Laravel varyantı)

```
mavibelge/
├── app/
│   ├── Http/Controllers/
│   │   ├── Site/            # HomeController, SectorController,
│   │   │                    # QualificationController, PostController,
│   │   │                    # ApplicationController, ContactController
│   │   └── Admin/           # her modül için resource controller
│   ├── Models/              # Sector, Qualification, Fee, Post, Page,
│   │                        # Application, ExamCenter, ExamSession...
│   ├── Services/            # ApplicationService, FeeImportService,
│   │                        # PdfExportService, NotificationService
│   ├── Policies/            # rol bazlı yetki
│   └── Support/             # Seo, Breadcrumb, Slugger helpers
├── database/migrations/
├── database/seeders/        # mevcut siteden aktarılan içerik
├── resources/
│   ├── views/
│   │   ├── layouts/         # app.blade.php, admin.blade.php
│   │   ├── components/      # card, breadcrumb, form-field, cta
│   │   ├── site/            # home, sector, qualification, post,
│   │   │                    # page, fees, exam-calendar, contact
│   │   └── admin/
│   ├── css/  resources/js/
├── routes/web.php  routes/admin.php
├── storage/app/private/     # ← yüklenen başvuru dosyaları (web dışı)
├── public/                  # tek giriş noktası: index.php
└── tests/
```

### 6.3 Veritabanı şeması

Aşağıdaki 22 tablo mevcut içeriğin tamamını karşılar ve admin panelden yönetilebilir kılar.

#### İçerik çekirdeği

```sql
sectors                      -- 12 sektör
  id, slug, name, short_name, title, description, intro_html,
  icon, cover_image, sort_order, is_active,
  seo_title, seo_description, seo_image, created_at, updated_at

qualifications               -- ~60 belgelendirme programı
  id, sector_id, slug,
  myk_code,                  -- "11UY0010"
  level,                     -- 2,3,4,5,6
  revision,                  -- "04"
  full_code,                 -- "11UY0010-3/04"  (generated)
  name, short_description, definition_html,
  purpose_html, legal_basis_html,
  prerequisites_html,        -- ön şartlar
  certificate_validity_months,
  surveillance_period_months,
  renewal_info_html,
  is_mandatory,              -- belge zorunluluğu var mı
  incentive_info_html,       -- teşvik/iade bilgisi
  image, sort_order, is_active,
  seo_title, seo_description, created_at, updated_at
  UNIQUE(myk_code, level, revision)

qualification_sector         -- çoklu sektör gösterimi (opsiyonel pivot)
  qualification_id, sector_id, is_primary

qualification_units          -- yeterlilik birimleri (A1, B2...)
  id, qualification_id, code, name,
  is_mandatory, exam_type,   -- teorik | performans | mulakat
  passing_score, sort_order

qualification_documents      -- PDF'ler (belgelendirme programı, talimat, form)
  id, qualification_id, type, title,
  file_path, file_size, mime, version, published_at, download_count
```

#### Ücretler — görsel değil, veri

```sql
fee_periods                  -- "2026 Ücret Tarifesi"
  id, name, year, valid_from, valid_to, is_active, note_html, pdf_path

fees
  id, fee_period_id, qualification_id,
  exam_fee, certificate_fee, unit_fee, total_fee,
  vat_included, is_incentive_eligible, note
  UNIQUE(fee_period_id, qualification_id)
```

> Bu iki tablo, `/fiyat-list/` sayfasındaki JPG'lerin yerini alır. Admin panelden Excel/CSV ile toplu yükleme yapılabilir, site tarafında filtrelenebilir/aranabilir tablo olarak render edilir ve istenirse aynı veriden PDF üretilir.

#### Sınav operasyonu

```sql
exam_centers                 -- İskenderun, Payas, Aliağa
  id, name, address, district, city, postal_code,
  phone, email, map_url, latitude, longitude,
  image, sort_order, is_active

exam_sessions                -- sınav takvimi (3. partiden içeri alınır)
  id, qualification_id, exam_center_id,
  exam_date, exam_time, exam_type,
  application_deadline, quota, registered_count,
  status,                    -- planlandi | basvuruya_acik | doldu | tamamlandi | iptal
  note, created_at, updated_at
```

#### Başvuru ve talep yönetimi

```sql
applications                 -- online sınav başvurusu
  id, application_no,        -- MB-2026-000123
  full_name, national_id_masked, birth_date,
  email, phone,
  qualification_id, sector_id, exam_center_id, exam_session_id,
  education_level, company_name,
  is_renewal,                -- ilk belge / belge yenileme
  message, file_path, receipt_path,
  kvkk_consent, kvkk_consent_at, kvkk_text_version,
  ip_address, user_agent,
  status,                    -- yeni | incelemede | eksik_evrak | onaylandi | reddedildi | sinava_atandi
  assigned_to, admin_note,
  created_at, updated_at

job_applications
  id, full_name, email, phone, position,
  cv_path, cover_letter, kvkk_consent, kvkk_consent_at,
  status, admin_note, created_at

complaints                   -- öneri / itiraz / şikayet
  id, ref_no, type,          -- oneri | itiraz | sikayet
  full_name, email, phone, certificate_no,
  subject, body, attachment_path,
  kvkk_consent, status,      -- acik | incelemede | sonuclandi
  resolution_html, resolved_at, resolved_by, created_at

contact_messages             -- "Sorum Var" + iletişim formu
  id, name, email, phone, profession, message,
  source_form, status, ip_address, created_at
```

#### Yayın ve site yönetimi

```sql
posts                        -- haber / duyuru / mevzuat bildirimi
  id, post_category_id, slug, title, excerpt, body_html,
  cover_image, published_at, is_featured, is_pinned,
  view_count, author_id, seo_title, seo_description

post_categories
  id, slug, name, sort_order

pages                        -- kurumsal/statik sayfalar
  id, parent_id, slug, title, body_html, template,
  sort_order, is_active, seo_title, seo_description

documents                    -- kurumsal doküman kütüphanesi
  id, category, title, description,
  file_path, file_size, version, revision_date,
  is_public, download_count, sort_order

references                   -- referans firmalar
  id, name, logo, website, sector_id, sort_order, is_active

sliders
  id, title, subtitle, image, image_mobile, alt_text,
  link_url, link_label, sort_order, is_active, starts_at, ends_at

menus / menu_items           -- navigasyon admin panelden yönetilir
  menu_items: id, menu_id, parent_id, label,
              link_type,     -- page | sector | qualification | post | external | route
              target_id, url, open_in_new_tab, sort_order, is_active

settings                     -- iletişim, sosyal medya, SEO varsayılanları, SMTP
  key, value, group, type, label
```

#### Sistem

```sql
users            id, name, email, password, is_active, last_login_at
roles            id, name, slug          -- admin | editor | operasyon | muhasebe
permissions      id, name, slug
role_user / permission_role
activity_logs    id, user_id, action, model_type, model_id,
                 old_values(json), new_values(json), ip_address, created_at
redirects        id, old_path, new_path, status_code, hit_count, created_at
media            id, path, disk, mime, size, width, height,
                 alt_text, title, folder, uploaded_by
```

### 6.4 Yeni URL yapısı

```
/                                           Ana sayfa
/kurumsal/hakkimizda
/kurumsal/misyon-vizyon
/kurumsal/kalite-politikamiz
/kurumsal/tarafsizlik-beyani
/kurumsal/yasal-dayanak
/kurumsal/yetki-belgelerimiz
/kurumsal/gizlilik-politikasi
/kurumsal/kvkk
/mesleki-yeterlilik/myk
/mesleki-yeterlilik/turkak
/mesleki-yeterlilik/ums-ve-uy
/mesleki-yeterlilik/mevzuat
/mesleki-yeterlilik/neden-zorunlu
/belgelendirme/surec
/belgelendirme/ucretler                     ← veri tabanlı, aranabilir tablo
/belgelendirme/kurallar
/belgelendirme/belge-yenileme
/belgelendirme/iptal-ve-askiya-alma
/belgelendirme/logo-kullanimi
/belgelendirme/itiraz-ve-sikayet
/belgelendirme/banka-hesaplarimiz
/meslekler                                  ← 12 sektörün genel görünümü + arama
/meslekler/{sektor}                         ← örn. /meslekler/makine
/meslekler/{sektor}/{program}               ← örn. /meslekler/makine/makine-bakimci-seviye-3
/sinav-takvimi
/sinav-merkezlerimiz
/basvuru                                    ← tek, birleşik online başvuru
/haberler  ·  /haberler/{slug}
/duyurular ·  /duyurular/{slug}
/referanslar
/kariyer
/iletisim
/belge-dogrula                              ← MYK sorgusuna yönlendiren açıklamalı sayfa
```

**Program slug kuralı:** `{meslek-adi}-seviye-{n}` (örn. `celik-kaynakcisi-seviye-3`). MYK kodu URL'de değil, sayfa içeriğinde ve arama filtresinde kullanılır. Tüm slug'lar Türkçe karakterden arındırılmış, tire ayraçlı, tekil ve **sondaki `/` ile normalize** edilir (tek kanonik biçim, diğeri 301).

### 6.5 301 yönlendirme haritası (örnekler — tam liste geçiş fazında çıkarılacak)

| Eski | Yeni |
|---|---|
| `/sirket-hakkinda/` | `/kurumsal/hakkimizda` |
| `/misyonumuz/` | `/kurumsal/misyon-vizyon` |
| `/kvkk-2/` | `/kurumsal/kvkk` |
| `/fiyat-list/` | `/belgelendirme/ucretler` |
| `/sinav-ve-belgelendirme-sureci/` | `/belgelendirme/surec` |
| `/itiraz-ve-sikayetler/` | `/belgelendirme/itiraz-ve-sikayet` |
| `/makine/` | `/meslekler/makine` |
| `/liman-meslekleri/` | `/meslekler/ulastirma-lojistik` |
| `/mermer-meslekleri/` | `/meslekler/maden-mermer` |
| `/makine-bakimci-3-belgelendirme-programi/` | `/meslekler/makine/makine-bakimci-seviye-3` |
| `/mermer-doagltas-imalat-elemani-3-.../` | `/meslekler/maden-mermer/mermer-dogaltas-imalat-elemani-seviye-3` |
| `/basvuru-formlari/`, `/online-basvuru/`, `/basvuru-formlari-2/` | `/basvuru` |
| `/belge-yenileme/`, `/belge-yenileme-sureci/` | `/belgelendirme/belge-yenileme` |
| `/slider/`, `/logos/`, `/pandemi-surecinde-alinan-tedbirler1/` | **410 Gone** veya ilgili üst sayfaya 301 |

Bu harita `redirects` tablosunda tutulur ve admin panelden yönetilir; ayrıca sunucu seviyesinde (nginx/.htaccess) statik olarak da yazılabilir.

---

## 7. Admin Panel Kapsamı

Rol bazlı erişim (admin · editör · operasyon · muhasebe) ile 15 modül:

| # | Modül | İşlevler |
|---|---|---|
| 1 | **Gösterge paneli** | Bekleyen başvuru sayısı, okunmamış mesajlar, yaklaşan sınavlar, son 30 gün başvuru grafiği |
| 2 | **Sektörler** | CRUD, sürükle-bırak sıralama, kapak görseli, SEO alanları, aktif/pasif |
| 3 | **Yeterlilikler (Programlar)** | CRUD, sektör ataması, MYK kodu/seviye/revizyon, birimler, ön şartlar, gözetim/geçerlilik süreleri, doküman ekleme, çoklu sektör |
| 4 | **Ücret tarifeleri** | Yıl bazlı dönem oluşturma, program bazlı ücret girişi, **Excel/CSV toplu içe aktarma**, önceki dönemi kopyalama, otomatik PDF üretimi |
| 5 | **Sınav takvimi** | Oturum ekleme, kontenjan, başvuru son tarihi, duruma göre otomatik listeleme |
| 6 | **Sınav merkezleri** | Adres, harita koordinatı, görsel, iletişim |
| 7 | **Başvurular** | Liste + filtre (durum/meslek/tarih/merkez), detay, durum değiştirme, personel atama, dahili not, **Excel dışa aktarma**, adaya otomatik e-posta/SMS |
| 8 | **İş başvuruları** | CV indirme, durum takibi |
| 9 | **Öneri / İtiraz / Şikayet** | Referans numarası üretimi, süreç takibi, sonuçlandırma metni, SLA uyarısı (17024 gerekliliği) |
| 10 | **İletişim mesajları** | Okundu/yanıtlandı işaretleme, dışa aktarma |
| 11 | **Haberler & Duyurular** | Kategori, öne çıkarma, sabitleme, zamanlanmış yayın, kapak görseli, SEO |
| 12 | **Sayfalar & Menü** | Statik sayfa editörü (blok/WYSIWYG), sürükle-bırak menü yöneticisi |
| 13 | **Referanslar & Slider & Medya** | Logo yükleme, sıralama, slider tarih aralığı, merkezi medya kütüphanesi (alt metin zorunlu) |
| 14 | **Ayarlar** | İletişim bilgileri, sosyal medya, SMTP, varsayılan SEO, KVKK metin sürümü, form alıcı adresleri |
| 15 | **Sistem** | Kullanıcılar & roller, 301 yönlendirme yöneticisi, denetim izi (activity log), yedek durumu |

---

## 8. Ön Yüz Sayfa Şablonları (Yeni)

| Şablon | Kullanıldığı yer | Ana bileşenler |
|---|---|---|
| `home` | / | Hero + arama, 4 hızlı eylem, sektör ızgarası, öne çıkan duyurular, sınav takvimi özeti, referans şeridi, SSS, CTA |
| `sector-index` | /meslekler | 12 sektör kartı + meslek arama kutusu (kod/ad ile) |
| `sector-detail` | /meslekler/{sektor} | Sektör açıklaması + program kartları (seviye rozeti, zorunluluk rozeti, ücret) + filtre |
| `qualification-detail` | /meslekler/{s}/{p} | Künye kutusu (MYK kodu, seviye, geçerlilik, gözetim), tanım, ön şartlar, birim tablosu, sınav türleri, ücret, dokümanlar, yakın sınav tarihleri, **"Bu meslek için başvur" CTA**, ilgili meslekler |
| `fees` | /belgelendirme/ucretler | Filtrelenebilir/aranabilir ücret tablosu, sektöre göre gruplama, PDF indir |
| `exam-calendar` | /sinav-takvimi | Takvim/liste görünümü, merkez ve meslek filtresi, doğrudan başvuru linki |
| `process` | /belgelendirme/surec | Adım adım görsel süreç (başvuru → sınav → belge → gözetim → yenileme) |
| `application-form` | /basvuru | Çok adımlı form: meslek seçimi → kişisel bilgi → belge yükleme → KVKK onayı → özet |
| `post-index` / `post-detail` | /haberler, /duyurular | Kategori filtreli liste, ilgili içerikler |
| `page` | Kurumsal/statik | İçindekiler navigasyonu + doküman indirme bloğu |
| `contact` | /iletisim | 3 lokasyon kartı + harita + form |
| `search` | /arama | Site geneli arama (meslek, haber, doküman) |

---

## 9. Uygulama Yol Haritası

**Faz 0 — Hazırlık (1 hafta)**
İçerik envanterinin CSV'ye dökülmesi · MYK portalından güncel yeterlilik verilerinin (kod, seviye, revizyon, birimler, geçerlilik süreleri) toplanması · 12 sektörün nihai adlandırmasının kararlaştırılması · marka/tasarım yönü

**Faz 1 — Altyapı (1-2 hafta)**
Proje iskeleti, migration'lar, kimlik doğrulama, rol/yetki, medya kütüphanesi, temel admin düzeni

**Faz 2 — İçerik yönetimi (2-3 hafta)**
Sektör, Yeterlilik, Doküman, Sayfa, Haber, Menü, Referans, Slider modülleri + veri aktarımı (mevcut siteden scrape + elle temizlik). **Karakter kodlaması bozuk metinler bu aşamada düzeltilir.**

**Faz 3 — Operasyonel modüller (2 hafta)**
Ücret tarifeleri + Excel içe aktarma · Sınav merkezleri · Sınav takvimi · Başvuru/şikayet/iş başvurusu formları ve yönetim ekranları · e-posta bildirimleri

**Faz 4 — Ön yüz (2-3 hafta)**
Tüm şablonların kodlanması, responsive, erişilebilirlik (WCAG 2.1 AA hedefi), performans optimizasyonu

**Faz 5 — SEO & geçiş (1 hafta)**
Sayfa bazlı title/description, schema.org, sitemap.xml, 301 haritasının devreye alınması, Search Console'a yeni sitemap bildirimi, 404 izleme

**Faz 6 — Test & yayın (1 hafta)**
Form testleri, yük testi, güvenlik kontrolü (OWASP Top 10), yedekleme kurulumu, canlıya alma, ilk 30 gün 404/301 takibi

**Toplam tahmini süre: 10-13 hafta**

---

## 10. Karar Bekleyen Konular

1. **Framework:** Laravel mi, saf PHP + hafif MVC mi? (Hosting türü bu kararı belirler — VPS varsa Laravel.)
2. **Sınav takvimi:** `mavibelge.pratikteorik.com` sistemi tamamen mi devralınacak, yoksa entegrasyon/veri aktarımı mı yapılacak?
3. **Aday portalı:** Adayın kendi başvuru durumunu görebileceği bir giriş alanı istenir mi? (Bu, çağrı merkezi yükünü ciddi düşürür ama kapsamı büyütür.)
4. **Online ödeme:** Sınav ücreti için sanal POS entegrasyonu kapsamda mı? (Şu an sadece banka havalesi var.)
5. **Çoklu dil:** Arapça ve İngilizce eklenecek mi? (Mülteci belgelendirme ve 6 dilli belge vurgusu bunu destekliyor.) Veri modeli baştan çok dilli kurgulanırsa maliyeti düşük olur.
6. **Sektör adlandırma:** "Ulaştırma & Lojistik" mi "Liman" mı; "Maden & Mermer" mi "Mermer" mi? Tek isim seçilip her yerde kullanılmalı.
7. **Kurumsal doküman havuzu:** ISO 17024 kapsamındaki form/talimat PDF'leri için versiyonlu, herkese açık bir doküman merkezi istenir mi?
8. **Eski içerik:** 2016-2019 arası haberlerin tamamı taşınsın mı, yoksa arşiv olarak mı işaretlensin?

---

## 11. Kaynaklar

Bu rapordaki tüm tespitler aşağıdaki sayfaların doğrudan incelenmesine dayanmaktadır:

- [Ana sayfa](https://mavibelge.com.tr/)
- [Sitemap indeksi](https://mavibelge.com.tr/wp-sitemap.xml) · [Sayfalar](https://mavibelge.com.tr/wp-sitemap-posts-page-1.xml) · [Yazılar](https://mavibelge.com.tr/wp-sitemap-posts-post-1.xml) · [Kategoriler](https://mavibelge.com.tr/wp-sitemap-taxonomies-category-1.xml)
- [robots.txt](https://mavibelge.com.tr/robots.txt)
- Sektör sayfaları: [Makine](https://mavibelge.com.tr/makine/) · [Metalurji](https://mavibelge.com.tr/metalurji/) · [Metal](https://mavibelge.com.tr/metal-meslekleri/) · [Liman](https://mavibelge.com.tr/liman-meslekleri/) · [Elektrik](https://mavibelge.com.tr/elektrik/) · [Cam](https://mavibelge.com.tr/cam/) · [Tekstil](https://mavibelge.com.tr/tekstil/) · [İnşaat](https://mavibelge.com.tr/insaat-meslekleri/) · [Güzellik ve Saç Bakım](https://mavibelge.com.tr/guzellik-ve-sac-bakim/)
- Program detay örneği: [Makine Bakımcı Seviye 3](https://mavibelge.com.tr/makine-bakimci-3-belgelendirme-programi/)
- Süreç ve form sayfaları: [Sınav ve Belgelendirme Süreci](https://mavibelge.com.tr/sinav-ve-belgelendirme-sureci/) · [Sınav Ücretleri](https://mavibelge.com.tr/fiyat-list/) · [Online Başvuru](https://mavibelge.com.tr/basvuru-formlari/) · [İletişim](https://mavibelge.com.tr/iletisim/) · [Yetki Belgelerimiz](https://mavibelge.com.tr/yetki-belgelerimiz/)
