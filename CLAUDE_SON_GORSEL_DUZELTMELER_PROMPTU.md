# CLAUDE — MÜŞTERİ SUNUMU ÖNCESİ SON GÖRSEL DÜZELTMELER

`E:\PROJELER\MaviBelge\tanitim-site` içindeki mevcut çalışmayı koru. Site genelindeki tamamlanmış Stitch uyumu, 41 sayfa, 83 yeterlilik, gerçek görseller, orijinal logolar ve responsive düzeltmeleri bozma. Yalnızca aşağıdaki üç kalan görsel/kullanılabilirlik sorununu düzelt.

## 1. Ana sayfa görev bölümü hero ile çakışıyor

QA kanıtlarında:

- `qa-screenshots/desktop-1440/home.png`
- `qa-screenshots/mobile-390/home.png`

`En Çok Aranan İşlemler` başlığı ve açıklaması hero alt sınırında koyu görsel/overlay ile çakışıyor. Başlık masaüstünde kararıyor, mobilde kısmen hero altında kalıyor.

İstenen düzen:

- Yalnızca beş görev kartı hero alt kenarına kontrollü biçimde binsin.
- `En Çok Aranan İşlemler` başlığı ve açıklaması tamamen beyaz/açık bölüm zemininde, yüksek kontrastla ve kesintisiz görünsün.
- Negatif margin'i tüm `.hero-tasks` bölümüne değil gerekiyorsa yalnızca kart gridine uygula.
- Hero ile başlık arasında görsel çakışma, kırpılma veya koyu metin/koyu arka plan problemi kalmasın.
- Masaüstünde beş kart tek sıra; mobilde ilk kart tam genişlik ve diğer dört kart 2×2 düzenini korusun.

## 2. Mobil ücret tablosunu gerçek kart görünümüne dönüştür

`qa-screenshots/mobile-390/sinav-ucretleri.png` ekranında masaüstü tablosu dar alana sıkıştırılmış. Yalnızca ilk sütunlar görünür; sektör ve ücret bilgisi müşteriye açık biçimde sunulmuyor. Yatay taşma olmaması tek başına yeterli değil; bilgi kaybı olmamalı.

768 px ve altında:

- Masaüstü tablo başlığını gizle.
- Her yeterliliği ayrı bir ücret kartı/satır kartı olarak göster.
- Her kartta aşağıdaki alanların tamamı açık etiketlerle bulunsun:
  - Meslek
  - MYK Kodu
  - Seviye
  - Sektör
  - Ücret: `Bilgi güncellenecektir`
- Kartlar tek sütun, rahat taranır ve Stitch mobil bileşen diline uygun olsun.
- Sektör filtresi üstte çalışmaya devam etsin.
- Masaüstü 1440 görünümünde mevcut tablo korunsun.
- Veriyi ikinci kez kopyalama; aynı `qualifications.js` kaynağından render et.

## 3. Meslekler sayfasına istemci tarafı sayfalama ekle

`qa-screenshots/desktop-1440/meslekler.png` ekranında 83 kartın tamamı tek sayfada gösterildiği için ekran aşırı uzun. Stitch `PUB-02` referansındaki gibi sayfalama ekle.

İstenen davranış:

- Masaüstü: sayfa başına 12 kayıt.
- Mobil: sayfa başına 8 kayıt.
- Filtre veya arama değiştiğinde otomatik olarak ilk sayfaya dön.
- Gösterim bilgisi dinamik olsun: örneğin `Toplam 83 meslekten 1–12 arası gösteriliyor.`
- `Önceki`, sayfa numaraları ve `Sonraki` kontrolleri bulunsun.
- Aktif sayfa açıkça işaretlensin.
- Kontroller klavyeyle çalışsın ve anlaşılır `aria-label` değerleri taşısın.
- Filtre sonucu tek sayfaya sığıyorsa gereksiz sayfalama gizlensin.
- Sektöre Göre Gözat bölümü ve footer korunmalı.

## Yeniden QA

Düzeltmelerden sonra mevcut QA aracını tekrar çalıştır ve şu dosyaları yenile:

- `qa-screenshots/desktop-1440/home.png`
- `qa-screenshots/mobile-390/home.png`
- `qa-screenshots/desktop-1440/meslekler.png`
- `qa-screenshots/mobile-390/meslekler.png`
- `qa-screenshots/desktop-1440/sinav-ucretleri.png`
- `qa-screenshots/mobile-390/sinav-ucretleri.png`
- `qa-screenshots/measurements.json`

375 ve 390 px genişlikte yeniden `scrollWidth === clientWidth` doğrulaması yap.

## Kabul kriterleri

- Ana sayfa görev başlığı hero altında tamamen okunuyor ve kartlarla çakışmıyor.
- Hero üstüne yalnızca görev kartlarının kontrollü kısmı biniyor.
- Mobil ücret ekranında beş alanın tamamı kart içinde görünüyor.
- Meslekler sayfasında 83 kart tek seferde gösterilmiyor; sayfalama çalışıyor.
- Arama/filtre ve sayfalama birlikte doğru çalışıyor.
- Mevcut 41 sayfa, 83 yeterlilik, logolar, gerçek içerikler ve demo davranışları bozulmuyor.
- 375/390 mobil taşma ölçümü hâlâ sıfır.

Yalnızca rapor yazma; düzeltmeleri doğrudan uygula ve yenilenmiş ekran görüntüleriyle kanıtla.
