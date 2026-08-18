# CLAUDE İÇİN UYGULAMA PROMPTU — MAVİ BELGE STATİK HTML TANITIM SİTESİ

## Görev

Mavi Belge'nin yeni web sitesi için müşteriye sunulacak, gerçek bir web sitesi gibi gezilebilen statik HTML tanıtım sitesi hazırla. Bu çalışma yalnızca ana sayfa değildir; müşterinin yeni sitenin genel yapısını, sayfa hiyerarşisini, tasarım dilini, içerik sunumunu ve mobil kullanımını anlayabileceği bütünlüklü bir ön yüz prototipidir.

Seçilen ve bütün sitenin görsel temeli olacak ana sayfa tasarımı:

`ANASAYFA / V1 Görev Odaklı / Desktop 1440 - Düzeltildi (v2)`

Bu tasarım yönünü değiştirme ve başka ana sayfa varyasyonu üretme. Ana sayfanın renklerini, tipografisini, header/footer yapısını, kart dilini, boşluk sistemini, butonlarını, görsel yaklaşımını ve görev odaklı hiyerarşisini bütün alt sayfalara tutarlı biçimde uygula.

## Bu aşamanın sınırı

Bu bir müşteri tanıtım prototipidir:

- Yalnızca HTML5, CSS3 ve gerektiği kadar sade JavaScript kullan.
- Laravel veya başka bir backend framework kurma.
- Veritabanı oluşturma.
- Yönetim paneli oluşturma.
- Giriş/kullanıcı hesabı oluşturma.
- Gerçek e-posta gönderimi veya form kaydı yapma.
- Online ödeme, SMS, aday portalı veya yerel sınav yönetimi ekleme.
- Bu aşamada otomatik test altyapısı, kapsamlı güvenlik katmanı, CI/CD veya deployment yapılandırması hazırlama.
- React, Vue, Angular, Next.js veya ağır bir frontend framework kullanma.
- Site herhangi bir derleme işlemi gerektirmeden açılabilsin. Basit bir yerel web sunucusunda doğrudan çalışsın.

Formlar ve dış işlemler görsel olarak çalışır durumda görünsün. Form gönderildiğinde veri göndermek yerine açık bir demo mesajı göster:

`Bu alan tanıtım sürümüdür. Gerçek başvuru işlemi yazılım aşamasında etkinleştirilecektir.`

## Çalışma dizini ve mevcut dosyalar

Çalışma dizini:

`E:\PROJELER\MaviBelge`

Yeni tanıtım sitesini aşağıdaki klasörde oluştur:

`E:\PROJELER\MaviBelge\tanitim-site`

Çalışma dizinindeki raporları ve logo dosyalarını silme, taşıma veya değiştirme. Kaynak olarak incele:

- `E:\PROJELER\MaviBelge\raporlar\final-rapor.md`
- `E:\PROJELER\MaviBelge\STITCH_ANASAYFA_5_VARYASYON_PROMPTU.md`
- `E:\PROJELER\MaviBelge\STITCH_ANASAYFA_ZORUNLU_DUZELTME_PROMPTLARI.md`
- `E:\PROJELER\MaviBelge\header-logo.png`
- `E:\PROJELER\MaviBelge\myk_logo.png`
- `E:\PROJELER\MaviBelge\turkak_logo.png`

Seçilen Stitch ekranının görseli veya export dosyası çalışma dizinine daha sonra eklenmişse önce onu bul ve incele. Bulunmuyorsa durma; bu prompttaki ayrıntılı tasarım tanımını ve ilgili Stitch promptlarını temel al.

## Kaynak önceliği

İçerik ve yapı için şu öncelik sırasını kullan:

1. `raporlar/final-rapor.md`: yeni sitenin kapsamı ve bilgi mimarisi.
2. Seçilen Stitch tasarımı: görsel dil ve yerleşim.
3. `https://mavibelge.com.tr/`: gerçek Türkçe metinler, haberler, iletişim bilgileri, menü içerikleri ve mevcut görseller.
4. Mevcut üç yerel logo dosyası: logo için tek geçerli kaynak.

Lorem ipsum, İngilizce arayüz metni, uydurma haber, uydurma fiyat, uydurma yeterlilik kodu, uydurma yetki numarası, uydurma müşteri yorumu veya uydurma kurum bilgisi kullanma.

## Zorunlu orijinal logolar

Şu dosyaları `tanitim-site/assets/images/logos/` klasörüne kopyala ve bütün sayfalarda gerçek dosya olarak kullan:

- `header-logo.png`
- `myk_logo.png`
- `turkak_logo.png`

Kurallar:

- Mavi Belge logosu header'ın solunda yer alsın.
- MYK ve TÜRKAK logoları header'ın sağ tarafına doğru ayrı bir güven grubu içinde yer alsın.
- Sıralama MYK, ardından TÜRKAK olsun.
- Logoları yeniden çizme, benzerini üretme, SVG olarak taklit etme veya metinle değiştirme.
- Kırpma, renk değiştirme, filtre uygulama, oran bozma veya üzerlerine yazı bindirme.
- Mavi Belge ana marka olarak daha büyük; MYK ve TÜRKAK küçük fakat rahat tanınabilir olsun.
- Mobil header'da da üç logo görünür olsun. Gerekirse kontrollü iki satırlı mobil header kullan.

## Tasarım karakteri

Seçilen V1 tasarımına bağlı kal:

- Kurumsal, sade, modern ve güvenilir.
- Kullanıcı görevlerini ilk sıraya alan yapı.
- Mavi Belge logosundan türetilmiş lacivert/mavi ana renkler.
- Beyaz ve açık gri zeminler; kontrollü koyu lacivert bölümler.
- Güçlü fakat abartısız tipografi.
- Net kartlar, tutarlı köşe yarıçapları ve hafif gölgeler.
- Yoğun gradient, neon renk, glassmorphism, kripto/SaaS estetiği, gereksiz 3D efekt ve aşırı animasyon kullanma.
- Her sayfada anlamlı, kaliteli ve içerikle ilişkili görseller kullanarak siteyi görsel açıdan zengin tut.
- Hero alanlarında arka plan fotoğrafı veya ilgili görsel bulunmalı; yalnızca düz renk hero yapma.
- Hero görsellerinin üzerinde metin okunabilirliği için kontrollü lacivert overlay kullan.
- Aynı hero görselini bütün sayfalarda tekrar etme.
- Gerçek Mavi Belge sitesinden alınan kullanılabilir görselleri yerel olarak indirip `assets/images/content/` altında sakla; uzak siteye hotlink yapma.
- Uygun gerçek görsel bulunmayan statik sayfalarda, kurumsal ve mesleki bağlama uygun lisans sorunu yaratmayacak geçici demo görselleri kullan ve kaynaklarını `IMAGE_SOURCES.md` dosyasında belirt.

## Ortak header

Bütün sayfalarda tek ortak header görünümü kullan:

1. İnce üst bilgi/güven bandı:
   - `0850 215 44 22`
   - `info@mavibelge.com.tr`
   - `MYK tarafından yetkilendirilmiş belgelendirme kuruluşu`
2. Ana header:
   - solda Mavi Belge logosu,
   - ortada ana menü,
   - sağa doğru MYK ve TÜRKAK logoları,
   - belirgin `Online Başvuru` butonu.
3. Ana menü:
   - Meslekler ve Belgeler
   - Sınav ve Başvuru
   - Kurumsal
   - Bilgi Merkezi
   - İletişim

Masaüstünde alt menüler hover ve klavye odağıyla açılabilsin. Mobilde erişilebilir hamburger menü çalışsın; alt menüler dokunmayla açılıp kapanabilsin.

## Ortak footer

Bütün sayfalarda aynı kapsamlı footer'ı kullan:

- Orijinal Mavi Belge logosu.
- Kuruluş adı: `Mavi Belge Uluslararası Sert. ve Göz. Hiz. Ltd. Şti.`
- Kısa kurum tanıtımı.
- Merkez ofis: `Mustafa Kemal Mah. İbrahim Karaoğlanoğlu Cad. Atay İş Merkezi Kat:12 Daire:63–64 İskenderun / HATAY Pk:31200`
- Telefonlar:
  - `0850 215 44 22`
  - `0326 441 44 22`
  - `0542 618 62 84`
- E-posta: `info@mavibelge.com.tr`
- Payas sınav alanı: `Yıldırım Beyazıt, Özkul Çolak Cd., 31900 Payas / Dörtyol / Hatay`
- İzmir Aliağa sınav alanı: `Siteler Mahallesi, 35800 Aliağa / İzmir`
- Aliağa telefonu: `0542 619 62 84`
- Hızlı bağlantılar.
- Kurumsal bağlantılar.
- KVKK, Gizlilik Politikası ve Çerez Tercihleri bağlantıları.
- `© Mavi Belge. Tüm hakları saklıdır.`

Doğrulanmamış sosyal medya bağlantısı ekleme.

## Ana sayfa

Dosya: `index.html`

Ana sayfayı seçilen `V1 Görev Odaklı - Düzeltildi (v2)` tasarımına mümkün olduğunca sadık oluştur.

Zorunlu sıralama ve içerikler:

1. Ortak güven bandı ve header.
2. Arka plan görselli hero:
   - H1: `Mesleki Yeterliliğinizi Belgeleyin, Geleceğinizi Güvence Altına Alın`
   - kısa açıklama,
   - `Mesleğini Bul` arama alanı,
   - `Online Başvuru` eylemi.
3. İlk ekran içinde veya hemen devamında beş belirgin görev kartı:
   - Sınav Ücretleri
   - Belge Sorgulama
   - Belge Yenileme
   - Sınav Süreçleri
   - Sınav Talepleri
4. `Yetkilendirilmiş Sektörler` bölümü; 12 sektörün tamamı.
5. `Başvurudan Belgelendirmeye` süreç bölümü:
   - Mesleğini Seç
   - Başvurunu Tamamla
   - Sınava Katıl
   - Belgeni Al
6. Kurumsal güven ve yetkilendirme bölümü.
7. `Haberler ve Duyurular`; gerçek siteden alınan en az üç gerçek haber, tarih, kısa özet ve gerçek görsel.
8. Gerçek referans logolarından seçilmiş bölüm.
9. İletişim/başvuru çağrısı.
10. Ortak footer.

## Mesleğini Bul işlevi

Statik prototipte gerçekçi çalışan bir ön yüz araması oluştur:

- Arama meslek adı, MYK kodu, sektör, seviye ve anahtar kelime alanlarında çalışsın.
- Verileri `assets/data/qualifications.js` veya `assets/data/qualifications.json` içinde tut.
- Yalnızca mevcut site ve raporda doğrulanan meslek/yeterlilikleri ekle.
- Arama yazıldıkça öneri listesi göster.
- Sonuçta meslek adı, kod, seviye, sektör, `Detayları Gör` ve `Başvuru Yap` eylemleri bulunsun.
- JSON dosyasının `file://` kısıtına takılmaması için gerekiyorsa veriyi JS modülü veya normal script değişkeni olarak yükle; site basit yerel sunucuda sorunsuz çalışsın.

## Yetkilendirilmiş 12 sektör

Adları aynen kullan:

1. Makine
2. Metalurji
3. Metal
4. Ulaştırma & Lojistik
5. Plastik
6. Elektrik
7. Cam
8. Tekstil
9. İnşaat
10. Mobilya
11. Maden & Mermer
12. Güzellik ve Saç Bakım

## Oluşturulacak sayfalar

Müşterinin bütün site yapısını anlayabilmesi için aşağıdaki sayfaları oluştur. Bütün sayfalar birbirine gerçek bağlantılarla bağlı olsun ve ortak tasarım sistemini kullansın.

### 1. Meslekler ve Belgeler

- `meslekler.html` — tüm meslekler/yeterlilikler, arama ve filtreleme.
- `sektor.html?slug=makine` gibi çalışan dinamik statik sektör şablonu.
- Aynı sektör şablonu 12 sektörün tamamını query parametresi veya hash üzerinden gösterebilsin.
- `yeterlilik.html?slug=...` — yeterlilik detay şablonu.
- Yeterlilik detayında örnek yapı:
  - meslek adı,
  - MYK kodu,
  - seviye,
  - revizyon,
  - sektör,
  - kısa açıklama,
  - yeterlilik birimleri,
  - sınav yapısı,
  - belge geçerliliği,
  - ücret bağlantısı,
  - ilgili dokümanlar,
  - başvuru eylemi.
- `belge-yenileme.html`.

### 2. Sınav ve Başvuru

- `sinav-ve-basvuru.html` — bölüm giriş sayfası.
- `nasil-basvururum.html`.
- `online-basvuru.html` — çok adımlı görünümlü demo başvuru formu.
- `sinav-ucretleri.html` — erişilebilir HTML tablo, sektör/meslek filtresi.
- `sinav-takvimi.html` — mevcut dış takvim sistemine yönlendirmeyi açıklayan sayfa ve dış bağlantı.
- `sonuc-belge-sorgulama.html` — MYK sistemine gidileceğini açıklayan sayfa ve dış bağlantı.
- `sinav-surecleri.html`.
- `sinav-talepleri.html` — bireysel/kurumsal seçimli demo form.
- `banka-hesap-bilgileri.html`.
- `itiraz-sikayet.html` — demo form ve süreç açıklaması.

Formların görsel doğrulama, adım ilerleme ve demo başarı mesajları JavaScript ile çalışsın; hiçbir veri gönderilmesin.

### 3. Kurumsal

- `kurumsal.html` — kurumsal bölüm giriş sayfası.
- `hakkimizda.html`.
- `misyon-vizyon.html`.
- `kalite-politikamiz.html`.
- `tarafsizlik-beyani.html`.
- `yasal-dayanagimiz.html`.
- `yetki-akreditasyon.html` — MYK ve TÜRKAK logoları ile gerçek yetkilendirme anlatısı.
- `gizlilik-politikasi.html`.
- `kvkk.html`.
- `referanslar.html` — gerçek referans logoları.
- `sosyal-sorumluluk.html` — mevcut sosyal sorumluluk içeriklerini temsil eden giriş sayfası.

### 4. Bilgi Merkezi

- `bilgi-merkezi.html` — bölüm giriş sayfası.
- `haberler.html` — gerçek haber kartları, arama ve kategori filtresi.
- `haber-detay.html?slug=...` — gerçek bir haber içeriğini gösterebilen detay şablonu.
- `duyurular.html`.
- `dokumanlar.html` — herkese açık doküman merkezi; kategori, sürüm, tarih ve indirme eylemi.
- `mevzuat.html`.
- `sss.html` — açılır/kapanır erişilebilir soru-cevaplar.
- `myk.html`.
- `turkak.html`.
- `ulusal-meslek-standartlari.html`.
- `ulusal-yeterlilikler.html`.

### 5. İletişim ve kariyer

- `iletisim.html` — merkez ve sınav lokasyonları, telefonlar, e-posta, harita yer tutucuları ve demo iletişim formu.
- `kariyer.html` — kurum/çalışma tanıtımı.
- `is-basvurusu.html` — demo iş başvuru formu ve dosya seçme arayüzü; dosya gönderme yok.

### 6. Yardımcı durum sayfası

- `404.html` — kullanıcıyı meslek aramasına, ana sayfaya ve iletişime yönlendiren tasarımlı sayfa.

## Dosya ve bileşen yapısı

En az şu yapıyı oluştur:

```text
tanitim-site/
├── index.html
├── meslekler.html
├── sektor.html
├── yeterlilik.html
├── ...diğer HTML sayfaları
├── 404.html
├── assets/
│   ├── css/
│   │   ├── tokens.css
│   │   ├── base.css
│   │   ├── components.css
│   │   ├── layout.css
│   │   ├── pages.css
│   │   └── responsive.css
│   ├── js/
│   │   ├── components.js
│   │   ├── navigation.js
│   │   ├── search.js
│   │   ├── filters.js
│   │   ├── forms-demo.js
│   │   └── main.js
│   ├── data/
│   │   ├── sectors.js
│   │   ├── qualifications.js
│   │   └── news.js
│   └── images/
│       ├── logos/
│       ├── hero/
│       ├── sectors/
│       ├── news/
│       ├── references/
│       └── content/
├── README.md
└── IMAGE_SOURCES.md
```

Statik HTML'de ortak header/footer tekrarını yönetmek için basit ve güvenilir bir JavaScript bileşen yaklaşımı kullanılabilir. Ancak içerik görünmeden önce uzun süre boş sayfa oluşturma. JavaScript kapalıyken temel içeriklerin mümkün olduğunca erişilebilir kalmasını gözet. Gereksiz soyutlama yapma.

## Responsive davranış

Site yalnızca masaüstü görünümü değildir:

- 1440 px genişlikte seçilen Stitch tasarımına uygun dengeli görünüm.
- 1024 px tablet görünümü.
- 768 px küçük tablet görünümü.
- 390 px mobil görünüm.
- Mobilde yatay taşma olmasın.
- Mobil navigasyon çalışsın.
- Kartlar uygun şekilde 4/3/2/1 sütuna düşsün.
- Tablolar mobilde bilgi kaybetmeden kart veya kontrollü kaydırmalı erişilebilir kapsayıcıya dönüşsün.
- Üç logo mobil header'da görünür fakat dengeli olsun.
- Dokunma hedefleri en az 44×44 px olsun.

## Etkileşimler

Şu etkileşimler gerçekten çalışsın:

- Masaüstü açılır menüler.
- Mobil menü ve alt menüler.
- Mesleğini Bul canlı arama önerileri.
- Meslek/sektör filtreleri.
- Haber filtreleri.
- SSS akordeonları.
- Çok adımlı demo başvuru formu.
- Demo form başarı modalı veya bildirimi.
- Sayfa başına dön düğmesi.
- Dış bağlantılarda dış sisteme gidildiğini belirten ikon/metin.

Animasyonlar kısa ve kontrollü olsun; `prefers-reduced-motion` durumunda kapanabilsin.

## İçerik ilkeleri

- Site tamamen Türkçe olacak.
- Gerçek Mavi Belge metinlerini kullan; yazım/karakter bozulmalarını düzelt.
- Mevcut siteyi görsel olarak kopyalama; içeriği yeni tasarım diliyle yeniden düzenle.
- Haberler ve duyurular birbirinden ayrılabilsin.
- Ücretleri resim olarak değil HTML tablo olarak göster.
- Dokümanlarda sürüm ve tarih bilgisi göster.
- Dış sınav takvimi, belge sorgulama ve sınav sonucu bağlantılarını açıkça dış sistem olarak işaretle.
- Gerçek veri bulunmayan yerde rakam veya belge uydurma; `Bilgi güncellenecektir` gibi dürüst demo metni kullan.

## Kalite beklentisi

Bu aşamada test altyapısı kurma; ancak teslim etmeden önce elle kontrol et:

- Bütün HTML bağlantıları çalışıyor mu?
- Ana menüden bütün bölümlere ulaşılabiliyor mu?
- Logo dosyaları gerçekten görünüyor mu?
- MYK ve TÜRKAK logoları header'ın sağında mı?
- Ana sayfada arka plan görselli hero var mı?
- Ana sayfada Haberler ve Duyurular var mı?
- 12 sektörün tamamı gösteriliyor mu?
- Beş kritik görev alanı belirgin mi?
- Web ve mobil görünümde yatay taşma var mı?
- Türkçe karakterler doğru mu?
- Kırık görsel var mı?
- Formlar veri göndermeden demo mesajı gösteriyor mu?

## Yapılmaması gerekenler

- Yalnızca ana sayfa yapıp bırakma.
- Beş ana sayfa varyasyonu üretme; yalnızca seçilen V1 v2 tasarımını kullan.
- Laravel, PHP veya veritabanı ekleme.
- Admin paneli oluşturma.
- Tasarım yerine uzun açıklama veya plan dosyası sunup kodlamayı erteleme.
- Orijinal logolar yerine CSS, metin, emoji veya benzer logo kullanma.
- Sayfaları birbirinden kopuk mockup'lar halinde bırakma.
- Lorem ipsum kullanma.
- Uydurma içerik ve istatistik üretme.
- CDN üzerinden ağır UI kütüphanesi yükleme.
- Mevcut rapor veya logo dosyalarını değiştirme.

## Teslim çıktısı

Görevi tamamladığında:

1. Oluşturulan sayfaların listesini ver.
2. Ana giriş dosyasının tam yolunu belirt.
3. Siteyi yerelde açmak için en kolay komutu yaz.
4. Kullanılan gerçek/dış görselleri `IMAGE_SOURCES.md` içinde listele.
5. Henüz yalnızca demo olan işlemleri açıkça listele.
6. Ana sayfa, bir sektör sayfası, bir yeterlilik detayı, ücretler, haberler ve iletişim sayfasının masaüstü ve mobil görünümlerini kontrol ettiğini belirt.

Plan yazıp onay bekleme. Önce mevcut dosyaları incele, ardından doğrudan `tanitim-site` klasörünü oluşturup bütün statik tanıtım sitesini uygula.
