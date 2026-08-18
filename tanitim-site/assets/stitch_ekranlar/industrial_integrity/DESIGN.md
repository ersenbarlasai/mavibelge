---
name: Industrial Integrity
colors:
  surface: '#fbf9f8'
  surface-dim: '#dbd9d9'
  surface-bright: '#fbf9f8'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f5f3f3'
  surface-container: '#f0eded'
  surface-container-high: '#eae8e7'
  surface-container-highest: '#e4e2e2'
  on-surface: '#1b1c1c'
  on-surface-variant: '#43474c'
  inverse-surface: '#303030'
  inverse-on-surface: '#f2f0f0'
  outline: '#74777d'
  outline-variant: '#c4c7cc'
  surface-tint: '#4d6074'
  primary: '#142839'
  on-primary: '#ffffff'
  primary-container: '#2b3e50'
  on-primary-container: '#95a9be'
  inverse-primary: '#b5c9df'
  secondary: '#00629f'
  on-secondary: '#ffffff'
  secondary-container: '#71b8fe'
  on-secondary-container: '#004877'
  tertiary: '#232727'
  on-tertiary: '#ffffff'
  tertiary-container: '#393d3d'
  on-tertiary-container: '#a4a8a7'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d0e5fc'
  primary-fixed-dim: '#b5c9df'
  on-primary-fixed: '#081d2e'
  on-primary-fixed-variant: '#36495b'
  secondary-fixed: '#d0e4ff'
  secondary-fixed-dim: '#9bcbff'
  on-secondary-fixed: '#001d34'
  on-secondary-fixed-variant: '#004a7a'
  tertiary-fixed: '#e0e3e2'
  tertiary-fixed-dim: '#c4c7c6'
  on-tertiary-fixed: '#181c1c'
  on-tertiary-fixed-variant: '#434847'
  background: '#fbf9f8'
  on-background: '#1b1c1c'
  surface-variant: '#e4e2e2'
  success-green: '#2E7D32'
  warning-orange: '#ED6C02'
  error-red: '#D32F2F'
  accent-blue: '#337AB7'
typography:
  headline-xl:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 14px
    letterSpacing: 0.02em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 8px
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 32px
  stack-xs: 4px
  stack-sm: 12px
  stack-md: 24px
  stack-lg: 48px
---

## Marka ve Stil
Bu tasarım sistemi, **Mavi Belge** için kurumsal güven, endüstriyel uzmanlık ve operasyonel verimlilik ilkeleri üzerine inşa edilmiştir. Hedef kitle; sertifikasyon arayan profesyoneller, kurumsal çözüm ortakları ve sınav koordinatörleridir.

Tasarım dili **Modern Kurumsal** ve **Endüstriyel Minimalizm** akımlarının bir birleşimidir. Karmaşadan uzak, bilgi odaklı ve disiplinli bir görsel hiyerarşi hedeflenir. Güven duygusunu pekiştirmek için geniş beyaz alanlar, rasyonel bir ızgara yapısı ve tutarlı bir tipografik düzen kullanılır. Görsel süslemeler yerine işlevsellik ön plandadır; gereksiz animasyonlar, neon renkler ve karmaşık dokulardan kaçınılır.

## Renkler
Renk paleti, markanın mevcut kimliğini modern bir profesyonellikle harmanlar.

- **Birincil Lacivert (#2B3E50):** Kurumsal ciddiyeti ve otoriteyi temsil eder. Headerlar, yan menüler ve ağır basan bölümlerde kullanılır.
- **İkincil Aksiyon Mavisi (#066AAB):** Ana etkileşim rengidir. Düğmeler, linkler ve önemli ikonlarda "aksiyon" sinyali verir.
- **Zemin Grisi (#F4F7F6):** İçerik bölümlerini birbirinden ayırmak için nötr bir fon sağlar.
- **Durum Renkleri:** WCAG 2.2 AA standartlarına uygun olarak seçilmiştir. Başarı durumlarında yeşil, kritik hatalarda kırmızı ve dikkat gerektiren durumlarda turuncu kullanılır.

Karanlık mod bu aşamada sistemin birincil önceliği değildir; ancak yönetim panelinde göz yorgunluğunu azaltmak için lacivert tonlarının hakim olduğu koyu yan menü yapıları tercih edilir.

## Tipografi
Sistemde yazı tipi olarak **Inter** tercih edilmiştir. Bu seçim, hem kurumsal web sitesindeki yoğun okuma metinleri hem de yönetim panelindeki veri odaklı arayüzler için yüksek okunabilirlik sağlar.

- **Hiyerarşi:** Başlıklarda kalın (Bold/700) ağırlıklar kullanılarak net bir bilgi mimarisi oluşturulur.
- **Okunabilirlik:** Gövde metinlerinde satır aralığı (line-height) geniş tutularak uzun süreli okumalarda konfor sağlanır.
- **Mobil Uyumluluk:** Büyük başlıklar (XL ve LG) mobil cihazlarda otomatik olarak ölçeklenir; `headline-lg-mobile` standardı uygulanır.

## Yerleşim ve Boşluklar
Tasarım, **8px tabanlı bir ızgara sistemi** üzerine kuruludur. Tüm bileşenler ve boşluklar bu birimin katları olarak belirlenir.

- **Izgara Yapısı:** Masaüstünde 12 sütunlu fluid grid kullanılır. Maksimum içerik genişliği 1280px ile sınırlandırılmıştır.
- **Yönetim Paneli:** Yan menü sabit (240px-280px), içerik alanı ise dinamik olarak genişleyen yapıdadır.
- **Hizalama:** Formlar ve veri tabloları gibi kritik bileşenlerde sol hizalama kuralı esastır. 
- **Dokunma Alanları:** Erişilebilirlik gereği, butoni girdi alanı ve link gibi etkileşimli öğeler için minimum 44x44px dokunma alanı (padding dahil) korunur.

## Yükseklik ve Derinlik
Endüstriyel karakteri korumak için gölge kullanımı minimize edilmiştir. Derinlik hissi, gölgelerden ziyade **tonal katmanlar** ve **ince dış hatlar** ile sağlanır.

- **Yüzeyler:** Birincil içerik alanları saf beyaz (#FFFFFF) zemin üzerine oturur. Yan içerik panelleri veya bölümleri hafif gri (#F4F7F6) arka planlarla ayrıştırılır.
- **Dış Hatlar:** Kartlar ve giriş alanları için 1px kalınlığında, düşük kontrastlı gri (#E0E0E0) kenarlıklar kullanılır.
- **Odak Durumu (Focus):** Erişilebilirlik için form alanları ve butonlar odaklandığında, İkincil Mavi (#066AAB) renginde 2px kalınlığında net bir dış çerçeve (outline) gösterilir.

## Formlar ve Şekiller
Sistemin "Keskin Hatlı" ve "Endüstriyel" karakterini yansıtmak amacıyla yuvarlatma değerleri düşük tutulmuştur.

- **Köşe Yarıçapı:** Standart bileşenlerde (butonlar, inputlar, kartlar) **4px** yuvarlatma kullanılır. Bu, modern bir dokunuş sağlarken kurumsal ciddiyeti bozmaz.
- **İkonografi:** Çizgisel (outlined), 2px kalınlığında ve geometrik ikon setleri tercih edilir. İkon köşeleri de sistemdeki genel yuvarlatma ile uyumlu olmalıdır.

## Bileşenler

### Butonlar
- **Birincil Buton:** Arka plan #066AAB, metin #FFFFFF. Aktif durumda %10 daha koyu ton.
- **İkincil Buton:** Kenarlık 1px #2B3E50, metin #2B3E50, arka plan şeffaf.
- **Görev Odaklı Butonlar:** "Mesleğimi Bul" gibi navigasyonel butonlarda sağ tarafta ok ikonu kullanılır.

### Form Elemanları
- **Girdi Alanları:** Kalıcı etiketler (floating olmayan, üstte sabit), 44px minimum yükseklik.
- **Hata Durumu:** 1px kalınlığında #D32F2F kenarlık ve hemen altında açıklayıcı metin.
- **Onay Kutuları:** Keskin köşeli, seçildiğinde ikincil mavi dolgulu.

### Veri Tabloları (Yönetim Paneli)
- **Satırlar:** Zebra şeritleme (Zemin grisi ve beyaz dönüşümlü).
- **Başlıklar:** Koyu Lacivert (#2B3E50) metin, kalın font, alt çizgi ayıracı.
- **Eylem İkonları:** Tablo satır sonlarında düzenleme ve silme işlemleri için standartlaştırılmış araç çubukları.

### Kartlar
- Beyaz zemin, 1px ince gri çerçeve, sıfır veya çok hafif yayılımlı gölge.
- Başlık alanı içerikten ince bir yatay çizgi ile ayrılmalıdır.

### Navigasyon
- **Ana Menü:** Üstte sabit, beyaz zemin, aktif sekme altında 3px lacivert çizgi göstergesi.
- **Hızlı Erişim:** Ana sayfada "Sınav Takvimi" ve "Sonuç Sorgula" gibi alanlar için geniş, ikonlu kartlar.