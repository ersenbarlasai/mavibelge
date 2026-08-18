# CLAUDE UYGULAMA PROMPTU — STITCH GÖRSEL UYUM VE RESPONSIVE DÜZELTME

## Görev

`E:\PROJELER\MaviBelge\tanitim-site` klasöründeki mevcut 41 sayfalık statik HTML tanıtım sitesini koruyarak görsel açıdan yeniden düzenle. Yeni siteyi baştan kurma, sayfaları azaltma veya Laravel/veritabanı/admin paneli ekleme.

Ana hedef: mevcut tanıtım sitesinin tasarımını, kullanıcının seçtiği aşağıdaki Stitch ekranına ve aynı Stitch projesindeki kamu sayfası tasarım sistemine yaklaştırmak:

`ANASAYFA / V1 Görev Odaklı / Desktop 1440 - Düzeltildi (v2)`

Seçilen ana referans dosyaları:

- Masaüstü ekran:
  `assets/stitch_ekranlar/anasayfa_v1_g_rev_odakl_desktop_1440_d_zeltildi_v2/screen.png`
- Masaüstü kaynak tasarım kodu:
  `assets/stitch_ekranlar/anasayfa_v1_g_rev_odakl_desktop_1440_d_zeltildi_v2/code.html`
- Mobil V1 düzen referansı:
  `assets/stitch_ekranlar/anasayfa_v1_g_rev_odakl_mobile_390_d_zeltildi/screen.png`
- Mobil kaynak tasarım kodu:
  `assets/stitch_ekranlar/anasayfa_v1_g_rev_odakl_mobile_390_d_zeltildi/code.html`
- Tasarım tokenları:
  `assets/stitch_ekranlar/industrial_integrity/DESIGN.md`

## Çok önemli kaynak ayrımı

Stitch dosyaları yalnızca **görsel düzen ve tasarım kaynağıdır**. Stitch ekranlarında bulunan örnek/fake adresleri, telefonları, haberleri, sektörleri, fiyatları, yeterlilik sayılarını veya logoları içerik olarak kopyalama.

İçerik önceliği:

1. Mevcut `tanitim-site` içindeki doğrulanmış gerçek içerikler.
2. `E:\PROJELER\MaviBelge\raporlar\final-rapor.md`.
3. Resmî `mavibelge.com.tr` içeriği.
4. Stitch yalnızca yerleşim, boşluk, renk, tipografi ve bileşen görünümü.

Örnek: Stitch ana sayfasındaki Ankara/Yenimahalle adresini veya uydurma 2024 haberlerini kesinlikle kullanma. Mevcut gerçek İskenderun/Hatay, Payas ve Aliağa bilgilerini koru. Stitch ücret ekranındaki rakamları kullanma; doğrulanmış ücret bulunmadığı için mevcut `Bilgi güncellenecektir` ifadesini koru.

## Bu aşamanın sınırı

- Statik HTML, CSS ve sade JavaScript yapısını koru.
- Veritabanı, backend, admin paneli veya gerçek form gönderimi ekleme.
- Otomatik test altyapısı kurma.
- Tailwind CDN'yi veya Stitch `code.html` dosyalarını doğrudan üretim sayfası olarak kullanma.
- Stitch'in görsel sistemini mevcut `tokens.css`, `base.css`, `components.css`, `layout.css`, `pages.css` ve `responsive.css` dosyalarına temiz biçimde aktar.
- Mevcut 41 sayfa ve çalışan demo etkileşimleri korunmalı.

## Önce incele, sonra uygula

Kod değiştirmeden önce:

1. Mevcut `tanitim-site` dosya ağacını incele.
2. Yukarıdaki seçilen ana sayfa referansının hem `screen.png` hem `code.html` dosyasını tamamen incele.
3. `industrial_integrity/DESIGN.md` dosyasını tamamen oku.
4. Aşağıda eşleştirilen Stitch kamu ekranlarının hem `screen.png` hem `code.html` dosyalarını incele.
5. Mevcut HTML'deki gerçek içerik ve işlevleri kaybetmeden ortak CSS/bileşen düzeltmelerini planla.

Plan raporu yazıp bekleme; incelemeden sonra doğrudan uygulamaya geç.

## Kullanılacak Stitch alt sayfa referansları

Bu klasörleri ilgili mevcut sayfaların görsel düzeni için kullan:

- Meslek listesi:
  - `d_pub_02_meslekler_ve_yeterlilikler`
  - `m_pub_02_meslekler_ve_yeterlilikler`
- Sektörler:
  - `d_pub_03_sekt_rler`
- Sektör detayı:
  - `d_pub_04i_i_n_aat_meslekleri`
  - `m_pub_04i_i_n_aat_meslekleri`
- Yeterlilik detayı:
  - `d_pub_05a_elik_kaynak_s_yeterlilik_detay`
  - `m_pub_05a_elik_kaynak_s_yeterlilik_detay`
- Online başvuru:
  - `d_pub_06_online_ba_vuru`
  - `m_pub_06_online_ba_vuru`
- Sınav süreci:
  - `d_pub_07_s_nav_ve_belgelendirme_s_reci`
  - `m_pub_07_s_nav_ve_belgelendirme_s_reci`
- Ücretler:
  - `d_pub_08_s_nav_cretleri`
  - `m_pub_08_s_nav_cretleri`
- Doküman merkezi:
  - `d_pub_09_mevzuat_ve_dok_man_merkezi`
  - `m_pub_09_mevzuat_ve_dok_man_merkezi`
- Haberler:
  - `d_pub_10_haberler_ve_duyurular`
  - `m_pub_10_haberler_ve_duyurular`
- Kurumsal:
  - `d_pub_12_kurumsal_sayfas`
  - `m_pub_12_kurumsal_sayfas`
- Yetki ve akreditasyon:
  - `d_pub_13_yetki_belgeleri_ve_akreditasyon`
  - `m_pub_13_yetki_belgeleri_ve_akreditasyon`
- Referanslar:
  - `d_pub_14_referanslar_m_z`
  - `m_pub_14_referanslar_m_z`
- İletişim:
  - `d_pub_15_i_leti_im_ve_lokasyonlar`
  - `m_pub_15_i_leti_im_ve_lokasyonlar`
- İtiraz/şikâyet:
  - `d_pub_17_neri_i_tiraz_ve_ik_yetler`
  - `m_pub_17_neri_i_tiraz_ve_ik_yetler`
- Banka bilgileri:
  - `d_pub_21_banka_hesap_bilgileri`
  - `m_pub_21_banka_hesap_bilgileri`
- Ortak kabuk:
  - `d_shell_01_kamu_sitesi_ortak_kabuk`
  - `m_shell_01_kamu_sitesi_ortak_kabuk`
- 404:
  - `d_state_01_404_sayfa_bulunamad`
  - `m_state_01_404_sayfa_bulunamad`
- Dış sisteme yönlendirme:
  - `d_state_06_d_sisteme_y_nlendirme`

Klasör adları Türkçe karakter kaybı içeriyor olabilir; tam adları dizin listesinden doğrula.

## Tasarım sistemi

`industrial_integrity/DESIGN.md` temel alınarak şu görsel sistem bütün sayfalarda tutarlı olsun:

- Font: Inter.
- Maksimum içerik genişliği: 1280 px.
- Masaüstü dış boşluk: 32 px; mobil: 16 px.
- 8 px tabanlı aralık sistemi.
- Ana lacivert: `#142839` / koyu bölümlerde uyumlu ton.
- Aksiyon mavisi: `#00629f` veya tasarımdaki `#066AAB`.
- Sayfa zemini: `#fbf9f8`.
- Açık bölüm zemini: `#f4f7f6` civarı.
- Kartlar: beyaz zemin, 1 px ince gri kenarlık, çok hafif veya sıfır gölge.
- Standart köşe yarıçapı: çoğunlukla 4 px; aşırı yuvarlak kartlar kullanma.
- Masaüstü H1: tasarıma göre 48/56 px'e kadar; alt sayfalarda 40–48 px.
- Mobil ana başlık: yaklaşık 28/36 px.
- Buton ve input minimum yüksekliği: 44 px.
- Aktif menü altında ince mavi çizgi.
- Çizgisel, sade ve aynı aileden ikonlar.

## P0 — Ortak header hatalarını düzelt

Mevcut uygulamada masaüstü header'da `Online Başvuru` iki kez görünüyor; biri menünün altında neredeyse tam genişlikte gereksiz mavi şerit oluşturuyor. Bunun nedeni mobil CTA'nın masaüstünde gizlenmemesidir.

Zorunlu düzeltmeler:

1. `.mobile-header-cta` varsayılan olarak masaüstünde `display:none` olmalı; yalnızca mobil menü açıldığında görünmeli.
2. Masaüstünde yalnızca sağ taraftaki tek `Online Başvuru` butonu bulunsun.
3. Header toplam yüksekliğini Stitch referansına yaklaştır; gereksiz iki kat yüksek görünümü kaldır.
4. Orijinal `header-logo.png` solda, MYK ve TÜRKAK logoları sağa doğru yerinde kalmalı.
5. Orijinal logo dosyalarını değiştirme, yeniden çizme veya Stitch ekranındaki sahte/yazıyla oluşturulmuş logoları kullanma.
6. Navigasyon hizaları, aktif çizgi, boşluklar ve ikon/button boyutları Stitch ortak kabuğuna yaklaşmalı.
7. İnce telefon/e-posta güven bandı korunabilir; yüksekliği kompakt olmalı ve header'ı ağırlaştırmamalı.
8. Mobilde logo, MYK, TÜRKAK ve menü düğmesi aynı görünümde çakışmamalı.

## P0 — Mobil yatay taşmayı tamamen kaldır

Gerçek tarayıcı incelemesinde 390 px viewport altında sayfa client genişliği yaklaşık 375 px iken `scrollWidth` 426 px ölçüldü. Hero içeriği sağa taşıyor.

Zorunlu düzeltmeler:

- Her 41 sayfada `document.documentElement.scrollWidth <= document.documentElement.clientWidth` olmalı.
- Hero grid çocuklarına ve form/grid öğelerine gerekli `min-width:0` kurallarını ekle.
- Uzun placeholder ve metinlerin grid'i genişletmesini engelle.
- Input, select, textarea, tablo kapsayıcısı ve butonlarda `max-width:100%`/uygun `min-width:0` kullan.
- `.container` her breakpoint'te viewport içinde kalmalı.
- `overflow-x:hidden` ile hatayı yalnızca gizleme; taşmaya neden olan bileşeni düzelt.
- 390 px mobil görünümde sağa-sola kaydırma olmamalı.

## P0 — Ana sayfayı seçilen Stitch V1 v2'ye yaklaştır

Ana sayfada mevcut gerçek metinleri ve 12 sektörü koruyarak şu yapıyı uygula:

1. Hero, seçilen Stitch ekranındaki gibi geniş arka plan fotoğraflı ve açık/kurumsal kompozisyonlu olsun.
2. Sol tarafta güçlü başlık, açıklama ve iki CTA.
3. Sağ tarafta beyaz/açık yüzeyli `Mesleğini Bul` paneli.
4. Beş kritik görev kartı hero alt sınırına kontrollü biçimde bindirilsin:
   - Sınav Ücretleri
   - Belge Sorgulama
   - Belge Yenileme
   - Sınav Süreçleri
   - Sınav Talepleri
5. Masaüstünde görev kartları tek sırada beş sütun.
6. Mobilde `Sınav Ücretleri` tam genişlikte birincil kart; kalan dört görev iki sütun × iki satır düzeninde olsun. Mevcut tek sütunlu beş uzun kart düzenini kullanma.
7. Masaüstünde sektörler seçilen referans gibi 6 sütun × 2 satır, kompakt ve kolay taranır olsun.
8. Mobilde sektörler tek sütun yerine 2 sütun × 6 satır olsun.
9. 12 gerçek sektör adı aynen korunmalı; mobil Stitch örneğindeki Enerji, Kimya, Tasarım, Otomotiv, Bilişim, Gıda gibi Mavi Belge kapsamı dışı örnek sektörleri kopyalama.
10. Başvurudan Belgelendirmeye bölümü masaüstünde 4 yatay adım; mobilde okunabilir dikey akış.
11. Kurumsal güven bölümü ve CTA'lar korunmalı.
12. `Haberler ve Duyurular` üç görselli kart içermeli.
13. Referanslar bölümü gerçek logolarla görsel bir grid olmalı; yalnızca metin linki olarak bırakma.
14. Footer gerçek iletişim bilgilerini korurken Stitch'in daha derli toplu görsel hiyerarşisine yaklaşmalı.

## P0 — Hero ve haber görsellerini düzelt

Mevcut ana sayfa hero'su soyut koyu SVG olduğu için seçilen Stitch fotoğraflı tasarımdan belirgin biçimde uzak. Haber kartlarında da yerel SVG'ler tarayıcı tam sayfa görüntüsünde büyük beyaz boş alanlar oluşturuyor ve görsel zenginlik hedefi karşılanmıyor.

Yapılacaklar:

1. Seçilen Stitch `code.html` dosyasında kullanılan hero ve haber görsel URL'lerini incele.
2. Kullanılabilir olanları yerel dosya olarak `assets/images/hero/` ve `assets/images/news/` klasörlerine indir; uzak URL'ye hotlink yapma.
3. Stitch görselleri erişilemezse `assets/stitch_ekranlar` altındaki uygun gerçek kaynak görselleri veya kullanıcı tarafından eklenen görsel varlıkları kullan.
4. Haber kartlarında raster WebP/JPG/PNG tercih et; kart görselleri 16:9 oranında, `object-fit:cover` ile görünmeli.
5. İlk ekrandaki/ilk üç haber görselini `loading="eager"` veya uygun öncelikle yükle; diğerlerinde lazy loading kullanılabilir.
6. Görsel yüklenemediğinde kartın dev boş kalmasını önleyen kontrollü fallback oluştur.
7. Kullanılan dış/generatif görselleri `IMAGE_SOURCES.md` içinde güncelle.
8. Orijinal Mavi Belge, MYK ve TÜRKAK logolarına dokunma.

## Alt sayfaları Stitch yapısına yaklaştır

### Meslekler

- Stitch `PUB-02` gibi güçlü başlık, kısa açıklama, tek satırlı arama/sektör/seviye filtre alanı kullan.
- Mevcut uzun tam genişlikte satır listesini masaüstünde üç sütunlu yeterlilik kart gridine dönüştür.
- Her kartta MYK kodu, seviye, meslek adı, sektör, `Detay` ve `Başvur` eylemleri olsun.
- Mobilde kartlar tek sütun ve rahat okunur olsun.
- Sonuç sayısı gerçek veri sayısını göstermeli.

### Sektör ve sektör detayı

- `PUB-03` ve `PUB-04I` referanslarının düzenini uygula.
- 12 sektör için gerçek veriyi koru.
- Sektör detayında sektör tanıtımı, ilgili yeterlilik kartları ve başvuru CTA'sı bulunmalı.

### Yeterlilik detayı

- `PUB-05A` düzenine yaklaş: görselli başlık bölümü, MYK kodu/seviye/revizyon, içerik kartları ve sağda başvuru işlemleri paneli.
- Mevcut doğrulanmış içerikten fazlasını uydurma.
- Geçersiz veya bulunamayan query parametresinde ilk yeterliliği sessizce göstermeyi bırak. Açık `Yeterlilik bulunamadı` durumu ve meslekler sayfasına dönüş linki göster.
- URL parametresi kod ise bunu açık biçimde ele al; değişkeni yanıltıcı biçimde `slug` olarak adlandırma veya gerçek slug sistemi uygula.

### Ücretler

- `PUB-08` görsel düzenini kullan: sektör, meslek ve arama filtresi; okunaklı tablo; mobil kart dönüşümü.
- Stitch örneğindeki uydurma fiyatları kopyalama.
- Mevcut `Bilgi güncellenecektir` değerlerini koru.
- Prototip açıklamasını müşteriye teknik uygulama notu gibi uzun göstermeden, kısa bir bilgi kutusuna dönüştür.

### Haberler

- `PUB-10` düzenine yaklaş.
- Gerçek 6 haberin başlık/tarih/özet bilgilerini koru.
- Haber görselleri görünür ve düzgün oranlı olmalı.
- Filtre ve arama tasarımı Stitch bileşen diline uymalı.

### Kurumsal, yetki, referanslar, iletişim ve formlar

- İlgili PUB referanslarındaki grid, kart, başlık ve yan panel düzenlerini uygula.
- Gerçek iletişim bilgilerini koru.
- İletişim sayfasındaki düz çizgili `Harita yer tutucusu` bloklarını daha sunuma uygun harita önizleme kartlarına dönüştür; gerçek embed kullanmayacaksan demo olduğunu temiz biçimde belirt.
- Referanslar sayfasında mevcut gerçek referans logolarını görsel gridde göster.
- Formlar veri göndermeden mevcut demo mesajını göstermeye devam etsin.

## 75 / 83 yeterlilik farkını çöz

Mevcut sitede 75 yeterlilik kaydı var; `final-rapor.md` ve Stitch PUB-02 referansı 83 görünür kayıt olduğunu belirtiyor. Bu fark açıklanmadan sayıyı 83 olarak sabit metinle yazma ve eksik kod uydurma.

Yapılacaklar:

1. `assets/data/qualifications.js`, `final-rapor.md`, `assets/stitch_ekranlar/extracted_text_from_https_mavibelge.com.tr.md` ve mevcut sektör sayfalarını karşılaştır.
2. Eksik sekiz kaydı ad, sektör, kod, seviye ve kaynak URL bakımından doğrula.
3. Doğrulanabilenleri veri dosyasına ekle.
4. Adı doğrulanıp kodu/seviyesi doğrulanamayan kayıt varsa kod uydurma; veri durumunu açıkça işaretle ve `YETERLILIK_ESLESTIRME_RAPORU.md` dosyasında belirt.
5. Yinelenen veya aynı detaya bağlanan legacy kayıtları ayrıca belirt.
6. Arayüzdeki sonuç sayısını gerçek veri dizisinin uzunluğundan hesapla.

## Footer

Mevcut footer'daki gerçek bilgiler korunmalı; Stitch'teki sahte iletişim içeriği kopyalanmamalı. Görsel olarak:

- Masaüstünde dört ana kolon ve altında üç lokasyon satırı kullanılabilir.
- Dikey boşluklar azaltılsın; footer gereksiz uzun olmasın.
- Mobilde lokasyonlar ve bağlantı grupları erişilebilir akordeon veya kompakt kartlar olarak düzenlensin.
- Logo doğru kontrastla görünür olsun.
- Telefon ve e-posta tıklanabilir kalsın.

## Responsive kabul ölçüleri

Şu viewport'larda gerçek tarayıcıyla kontrol et:

- 1440 × 1000
- 1024 × 768
- 768 × 1024
- 390 × 844
- 375 × 812

Özellikle `index.html`, `meslekler.html`, `sektor.html?slug=insaat`, geçerli bir yeterlilik detayı, `sinav-ucretleri.html`, `haberler.html`, `online-basvuru.html` ve `iletisim.html` sayfalarını kontrol et.

Otomatik test paketi kurma; ancak gerçek tarayıcı incelemesini yap ve ekran görüntülerini `tanitim-site/qa-screenshots/` altında şu yapıyla kaydet:

```text
qa-screenshots/
├── desktop-1440/
├── tablet-1024/
└── mobile-390/
```

En az ana sayfa ile yukarıdaki temsilî alt sayfaların masaüstü ve mobil görüntülerini kaydet.

## Kabul kriterleri

Görev yalnızca aşağıdakilerin tamamı gerçekleştiğinde tamamlanmış sayılır:

- 41 mevcut sayfa korunuyor ve gezilebiliyor.
- Ana sayfa seçilen Stitch V1 v2 tasarım hiyerarşisine belirgin biçimde yaklaştı.
- Masaüstü header'da mükerrer/tam genişlikte Online Başvuru yok.
- Üç orijinal logo bütün sayfalarda doğru konumda görünüyor.
- Ana sayfa hero'sunda gerçek/kaliteli görsel var; soyut boş SVG görünümü yok.
- Haber kartlarında görünür görseller var; büyük boş beyaz alan yok.
- Masaüstünde görevler 5'li, sektörler 6×2 düzende.
- Mobilde ilk görev tam genişlik, diğer dört görev 2×2; sektörler 2×6 düzende.
- 390 ve 375 px'de yatay taşma yok.
- Meslek listesi masaüstünde kart gridine dönüştü.
- Geçersiz yeterlilik parametresi yanlış ilk kaydı göstermiyor.
- Gerçek iletişim bilgileri korunuyor.
- Stitch'teki uydurma fiyat, adres, haber ve sektörler siteye taşınmıyor.
- 75/83 farkı doğrulanmış veya raporlanmış.
- Desktop ve mobil QA ekran görüntüleri oluşturulmuş.

## Teslim raporu

Tamamladığında kısa ve kanıt odaklı rapor ver:

1. Değiştirilen dosyalar.
2. Ana görsel düzeltmeler.
3. Mobil taşma ölçüm sonucu (`scrollWidth` ve `clientWidth`).
4. Yeterlilik eşleştirme sonucu: doğrulanan toplam sayı ve eksik/şüpheli kayıtlar.
5. Oluşturulan QA ekran görüntülerinin yolları.
6. Hâlâ demo olan işlemler.

Yalnızca öneri veya plan sunma; mevcut dosyaları doğrudan düzelt, gerçek tarayıcıda doğrula ve kanıtları üret.
