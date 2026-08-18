# Görsel Kaynakları

Bu belge, tanıtım sitesinde kullanılan tüm görsellerin kaynağını listeler.

## Zorunlu orijinal logolar (değiştirilmeden kullanılmıştır)

| Dosya | Kaynak | Kullanım |
|---|---|---|
| `assets/images/logos/header-logo.png` | Çalışma dizininde sağlanan orijinal Mavi Belge logosu (`E:\PROJELER\MaviBelge\header-logo.png`) | Header (sol), footer |
| `assets/images/logos/myk_logo.png` | Çalışma dizininde sağlanan orijinal MYK logosu (`E:\PROJELER\MaviBelge\myk_logo.png`) | Header güven grubu, Yetki ve Akreditasyon sayfası, MYK sayfası |
| `assets/images/logos/turkak_logo.png` | Çalışma dizininde sağlanan orijinal TÜRKAK logosu (`E:\PROJELER\MaviBelge\turkak_logo.png`) | Header güven grubu, Yetki ve Akreditasyon sayfası, TÜRKAK sayfası |

Bu üç dosya kırpılmadan, renklendirilmeden ve üzerine yazı bindirilmeden kullanılmıştır.

## Gerçek fotoğraflar (mavibelge.com.tr kaynaklı, yerel olarak indirildi)

Stitch tasarım incelemesi sırasında kullanıcı tarafından `assets/stitch_ekranlar/image_from_https_mavibelge.com.tr_...` klasörlerinde mavibelge.com.tr'nin gerçek yeterlilik/sektör fotoğrafları (WordPress `wp-content/uploads` yolundan, "MESLEKİ YETERLİLİK BELGESİ" marka bandı gömülü) sağlandı. Bu görseller siteye kopyalanıp `assets/images/content/real-*.png` olarak kullanılmıştır — hotlink yapılmamıştır. Görsellerin üzerinde Mavi Belge'nin kendi logosu ve gerçek meslek adı yer alır; hiçbiri yapay/AI üretimi değildir.

| Dosya | Kaynak sayfa (orijinal URL) | Kullanım |
|---|---|---|
| `real-insaat.png` | wp-content/uploads/2020/12/12/insaat.jpg | Ana sayfa hero arka planı, İnşaat sektörü sayfası |
| `real-kaynak.png` | wp-content/uploads/2020/12/09/kaynak.jpg | "Belge Ücretleri" haber kartı (nötr kategori görseli) |
| `real-liman.png` | wp-content/uploads/2021/01/01.jpg | Ulaştırma & Lojistik sektörü, "Bilgilendirme" haber kartı |
| `real-haddeci.png` | wp-content/uploads/2021/01/03.jpg | Metalurji sektörü sayfası |
| `real-tekstil-boya.png` | wp-content/uploads/2021/01/04.jpg | (yedek — Tekstil temalı içerik) |
| `real-tekstil-iplik.png` | wp-content/uploads/2021/01/05.jpg | Tekstil sektörü, "MYK Belgesi Zorunluluğu" haber kartı |
| `real-izabeci.png` | wp-content/uploads/2021/01/06.jpg | (yedek — Metalurji temalı içerik) |
| `real-dokumcu.png` | wp-content/uploads/2021/01/07.jpg | (yedek — Metalurji temalı içerik) |
| `real-metal-isleme.png` | wp-content/uploads/2021/01/08.jpg | Metal sektörü, "Mobilya Sektörü" haber kartı (nötr kategori görseli) |
| `real-metal-kesim.png` | wp-content/uploads/2021/01/09.jpg | "Geçerlilik Süresi Dolan Belgeler" haber kartı (nötr kategori görseli) |
| `real-makine.png` | wp-content/uploads/2021/01/10.jpg | Makine sektörü, "6 Dilde MYK Belgesi" haber kartı (nötr kategori görseli) |
| `real-refrakterci.png` | wp-content/uploads/2021/01/12.jpg | (yedek — Metalurji temalı içerik) |
| `real-mermer.png` | wp-content/uploads/2021/01/13.jpg | Maden & Mermer sektörü sayfası |
| `real-tasimaci.png` | wp-content/uploads/2022/08/01_y.jpg | (yedek — Ulaştırma temalı içerik) |
| `real-kirma-eleme.png` | wp-content/uploads/2022/08/02_y.jpg | (yedek — Maden & Mermer temalı içerik) |
| `real-elektrik-1.png` | wp-content/uploads/2022/08/03_y.jpg | Elektrik sektörü sayfası |
| `real-elektrik-2.png` | wp-content/uploads/2022/08/04_y.jpg | (yedek — Elektrik temalı içerik) |
| `real-maden.png` | wp-content/uploads/2022/08/05_y.jpg | (yedek — Maden & Mermer temalı içerik) |
| `real-cam.png` | wp-content/uploads/2022/08/06_y.jpg | Cam sektörü sayfası |

Haber kartlarındaki eşleştirmeler ("nötr kategori görseli") o haberin gerçek olayını göstermez; yalnızca ilgili sektör/meslek temasını temsil eder — bu, `assets/data/news.js` dosya başı yorumunda açıkça belirtilmiştir.

### Gerçek fotoğrafı bulunamayan 3 sektör

**Plastik, Mobilya, Güzellik ve Saç Bakım** sektörleri için kaynak sitede/verilen Stitch klasöründe kullanılabilir gerçek fotoğraf tespit edilemedi. Bu üç sektörün sayfa hero'sunda uydurma fotoğraf kullanılmamış, önceki nötr `hero-generic.svg` korunmuştur (`assets/data/sectors.js` içinde bu üç kayıt için `image: ""`).

| Dosya | Kullanım |
|---|---|
| `assets/images/hero/hero-generic.svg` | Plastik / Mobilya / Güzellik ve Saç Bakım sektör sayfaları, diğer tüm iç sayfa hero'ları |

## Referans kurum logoları

Kaynak sitede 54 referans logosu bir slider eklentisiyle sunulmaktadır ancak bağımsız, indirilebilir gerçek logo dosyaları bu çalışma kapsamında doğrulanamadı. **Gerçek Mavi Belge müşterisi/referansı olarak sunulmuş hiçbir logo yoktur.**

Bunun yerine, anasayfa ve `referanslar.html` sayfasındaki logo slider/grid bileşeni için **12 adet özgün, temsili SVG logo** üretildi (`assets/images/references/*.svg`). Bu logolar:

- Yapay/orijinal geometrik-monogram tasarımlardır, herhangi bir gerçek/tescilli markayı taklit etmez.
- Aşağıdaki temsili marka adlarını taşır: Atlas Endüstri, Doruk Yapı, Mavi Hat Lojistik, Nova Metal, Eksen Enerji, Usta Kaynak, Zirve Otomotiv, Form Plastik, Ada Tekstil, Kent Asansör, Natura Ahşap, Liman Teknik.
- Veri kaynağı: `assets/data/references.js`.
- Sayfa içinde ve slider altında "Gösterilen marka ve logolar tanıtım tasarımı için temsili olarak hazırlanmıştır." notu ile açıkça işaretlenmiştir.
- Gerçek referans kurumları kurum tarafından sağlandığında bu 12 dosya ve `references.js` verisi gerçek logolarla değiştirilmelidir.

## Metin/veri kaynağı

Meslek/yeterlilik listesi (MYK kodu, seviye, sektör) ve haber başlık/tarihleri `https://mavibelge.com.tr/` üzerindeki ilgili sektör ve haber sayfalarından WebFetch ile 2026-08-18 tarihinde doğrulanmıştır (bkz. `assets/data/qualifications.js`, `assets/data/news.js` dosya başı yorumları). Metal, Plastik ve Mobilya sektörlerindeki eksik kayıtlar 2026-08-18'de `metal-meslekleri/`, `plastik-meslekleri/` ve `mobilya-meslekleri/` sayfalarından yeniden doğrulanarak eklendi — bkz. `YETERLILIK_ESLESTIRME_RAPORU.md`. Ücret tutarları kaynak sitede yalnızca JPG görsel olarak yayınlandığından ve okunabilir rakam tespit edilemediğinden, ücret tablosunda "Bilgi güncellenecektir" ifadesi kullanılmıştır — uydurma tutar girilmemiştir.
