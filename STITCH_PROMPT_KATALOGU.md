# Mavi Belge — Stitch Prompt Kataloğu

Bu katalog, Mavi Belge web sitesi ve yönetim paneli tasarımlarının Stitch üzerinde sıfırdan, tutarlı ve denetlenebilir biçimde oluşturulması için hazırlanmıştır.

## Uygulama yöntemi

1. Önce yeni ve boş bir Stitch projesi oluşturun. Önceki başarısız projenin ekranlarını yeni projeye taşımayın.
2. `E:\PROJELER\MaviBelge\raporlar\final-rapor.md` dosyasını Stitch projesine kaynak dosya olarak yükleyin.
3. Aşağıdaki promptları numara sırasıyla, tek tek verin.
4. Bir promptun ekranları tamamlanmadan sonraki prompta geçmeyin.
5. Stitch yalnız açıklama, moodboard veya görsel galerisi üretirse aynı promptun sonuna `Bu bir açıklama görevi değildir; bitmiş web arayüzü frame'lerini şimdi canvas üzerinde oluştur.` cümlesini ekleyerek yeniden çalıştırın.
6. Her aşamada mevcut tasarım sistemi korunmalı; yeni prompt önceki ekranların stilini yeniden yorumlamamalıdır.

---

## PROMPT 00 — Projeyi sıfırdan başlat ve değiştirilemez kuralları tanımla

```text
MAVİ BELGE KURUMSAL WEB SİTESİ VE YÖNETİM PANELİ
SIFIRDAN TASARIM BAŞLANGIÇ TALİMATI

Bu çalışma önceki tasarımların devamı veya düzeltmesi değildir. Sıfırdan yeni bir tasarım sistemi ve yeni ekran seti oluştur. Önceki projelerden ekran, yerleşim, kart sistemi veya üretilmiş görsel kopyalama.

Bu aşamada yalnız açıklama, doküman özeti, moodboard, kaynak görsel galerisi veya tasarım önerisi üretme. Sonraki promptlarda gerçek web arayüzü frame'leri oluşturulacaktır. Bu promptta projenin kaynaklarını, tasarım sınırlarını ve ekran üretim kurallarını kesin olarak kabul et.

KAYNAK ÖNCELİĞİ

1. Projeye yüklenen final-rapor.md:
   - Kapsam
   - Yeni bilgi mimarisi
   - Sayfa sınıflandırmaları
   - Veri alanları
   - Güvenlik ve erişilebilirlik ilkeleri
   - Kabul kriterleri

2. https://mavibelge.com.tr/:
   - Gerçek kurumsal metinler
   - Gerçek sayfa ve menü adları
   - Gerçek sektör ve meslek adları
   - Gerçek haber ve duyurular
   - Gerçek iletişim bilgileri
   - Gerçek adres ve telefonlar
   - Gerçek referans logoları
   - Gerçek kurumsal fotoğraflar
   - Orijinal Mavi Belge logosu
   - Orijinal MYK, TÜRKAK ve Europass işaretleri
   - Gerçek yetki belgeleri, dokümanlar ve formlar

3. https://demo.patavatsiz.com/mavi-belge/:
   - Yalnız görsel yaklaşım ve kullanılabilirlik referansı
   - Endüstriyel hero yaklaşımı
   - Meslek arama alanı
   - Kullanıcı görev kartları
   - Sektör ve yeterlilik kartları
   - Kurumsal güven alanları

Çelişki halinde bu öncelik sırasını uygula. Demo sitedeki metin veya görselleri gerçek kurumsal veri kabul etme.

DİL KURALI

- Kamu sitesi ve yönetim panelinin tamamı Türkçe olacak.
- Menü, buton, filtre, form, doğrulama, hata, başarı, boş durum, tablo, modal ve admin ekranlarında İngilizce arayüz ifadesi kullanma.
- Dashboard yerine Yönetim Paneli; Search yerine Ara; Apply yerine Başvur; Download yerine İndir; Read More yerine Devamını Oku kullan.
- MYK, TÜRKAK, Europass, ISO/IEC, KVKK ve resmî yeterlilik kodları özgün biçimleriyle korunabilir.
- Türkçe karakterlerde bozulma olmasın.
- Lorem ipsum kullanma.

MARKA VE LOGO KURALI

- Mavi Belge logosunu yalnız orijinal resmî siteden al.
- MYK, TÜRKAK ve Europass logolarını yalnız orijinal sitedeki gerçek dosyalardan al.
- Logoları yeniden çizme, yapay zekâyla üretme, oranlarını değiştirme, kırpma, renklendirme veya başka işaretlerle birleştirme.
- Orijinal dosyaya erişilemezse sahte logo üretme; açık bir logo yer tutucusu kullan.
- Sahte akreditasyon belgesi, yetki belgesi, sertifika, referans logosu veya kurum mührü üretme.

GÖRSEL ÜRETİM KURALI

- Mevcut sitedeki gerçek ve kullanılabilir fotoğrafları öncelikli kullan.
- Belirli bir bölüm için uygun gerçek görsel yoksa dekoratif destek görseli üretebilirsin.
- Üretilen görseller fotogerçekçi, kurumsal, doğal ve Türkiye'deki sanayi çalışma ortamlarına uygun olsun.
- İş güvenliği ekipmanı doğru kullanılsın.
- Kaynakçı, makine bakım personeli, elektrikçi, liman/vinç operatörü, inşaat çalışanı, tekstil çalışanı ve sınav değerlendirme ortamları kullanılabilir.
- Üretilen görsellerde yazı, marka, sahte logo, sertifika, kimlik belgesi veya okunabilir kişisel veri bulunmasın.
- Yapay görseli gerçek Mavi Belge etkinliği, gerçek personel veya gerçek referans fotoğrafı gibi sunma. Üretilmiş görsel yalnız dekoratif amaçlı olsun.
- İnsanları klişe stok fotoğraf pozu yerine gerçek iş akışı içinde göster.

ÜRÜN SINIRLARI

- Site yalnız Türkçe olacak.
- İlk sürümde aday hesabı ve aday portalı olmayacak.
- Online ödeme olmayacak.
- SMS olmayacak; bildirim kanalı e-posta olacak.
- Sınav takvimi yerel olarak yönetilmeyecek; mevcut dış bağlantıya yönlendirecek.
- Belge sorgulama ve sınav sonucu dış MYK sistemine yönlendirecek.
- Tam online başvuru, güvenli belge yükleme ve yönetim panelinde durum yönetimi bulunacak.
- Doküman merkezi herkese açık ve versiyonlu olacak.
- Eski haberler gerçek görselleriyle korunacak.
- Sosyal sorumluluk içerikleri korunacak.

TASARIM KARAKTERİ

- Kurumsal, sade, modern, güvenilir ve endüstriyel.
- Kamu kurumu sitesi kadar açık; modern özel sektör sitesi kadar kullanılabilir.
- Mevcut Mavi Belge logosundaki mavi tonları temel alınmalı.
- Lacivert kurumsal yüzeyler, ana mavi aksiyonlar, beyaz içerik alanları ve açık gri bölüm zeminleri kullanılabilir.
- Başarı için yeşil; uyarı ve hata için sınırlı turuncu/kırmızı kullan.
- Generic teknoloji/SaaS görünümü, neon renk, aşırı gradient, cam efekti, yoğun blur ve gereksiz animasyon kullanma.
- Otomatik slider kullanma.
- Her içeriği büyük yuvarlatılmış karta dönüştürme.
- Uzun metinlerde rahat okuma genişliği kullan.

RESPONSIVE VE ERİŞİLEBİLİRLİK

- Her kamu ekranı için Desktop 1440 px ve Mobile 390 px frame oluşturulacak.
- Kritik admin ekranları için de Desktop 1440 px ve Mobile 390 px frame oluşturulacak.
- Tablet davranışı ortak tasarım sistemi üzerinde açıklanacak.
- WCAG 2.2 AA kontrastı hedefle.
- Minimum dokunma alanı 44x44 px.
- Kalıcı form label'ları, görünür focus, klavye erişimi ve anlamlı hata mesajları kullan.
- Durum bilgisini yalnız renkle anlatma.
- Mobilde yatay sayfa taşması oluşturma.
- Büyük tabloları mobilde kart veya özet/detay yapısına dönüştür.

FRAME KURALI

- Her ekran bağımsız ve açık isimli frame olacak.
- Masaüstü frame adı `D / ekran-kodu / ekran-adı` biçiminde olacak.
- Mobil frame adı `M / ekran-kodu / ekran-adı` biçiminde olacak.
- Açıklama panoları gerçek ekran frame'i yerine sayılmayacak.
- Aynı ekranı gereksiz tekrarlarla çoğaltma.
- Veriyle çoğalacak sayfalarda bir kanonik şablon ve birkaç gerçek içerik varyantı üret.

Bu kuralları projenin değiştirilemez tasarım sözleşmesi olarak kabul et. Henüz sayfa üretme; bir sonraki prompt ortak tasarım sistemi ve kabuğu oluşturacaktır.
```

---

## PROMPT 01 — Tasarım sistemi, header, footer ve ortak kabuk

```text
PROMPT 00'DA TANIMLANAN KURALLARI DEĞİŞTİRMEDEN UYGULA.

Şimdi Mavi Belge için gerçek bir Design System / UI Kit ve ortak kamu sitesi kabuğu oluştur. Açıklama yazmakla yetinme; bileşenleri ve desktop/mobile arayüz frame'lerini canvas üzerinde üret.

OLUŞTURULACAK FRAME'LER

1. DS-01 / Tasarım Tokenları
2. DS-02 / Tipografi ve Grid
3. DS-03 / Butonlar ve Bağlantılar
4. DS-04 / Form Bileşenleri
5. DS-05 / Kartlar, Tablolar ve Durumlar
6. D / SHELL-01 / Kamu Sitesi Ortak Kabuk
7. M / SHELL-01 / Kamu Sitesi Ortak Kabuk

TASARIM TOKENLARI

- Orijinal logo renklerine dayanan ana lacivert, kurumsal mavi, açık mavi, beyaz, açık gri ve koyu metin renkleri.
- Başarı, uyarı ve hata renkleri.
- Renklerin adını ve kullanım amacını göster.
- Türkçe karakter destekleyen modern sans-serif tipografi.
- H1, H2, H3, H4, gövde, küçük metin, etiket ve buton stilleri.
- 8 px tabanlı spacing sistemi.
- Masaüstü 12 kolon grid, 1200–1280 px içerik genişliği.
- Mobilde 16–20 px yatay güvenli alan.

BİLEŞENLER

- Ana, ikincil, outline ve text buton.
- Normal, hover, focus, active ve disabled durumları.
- Text input, e-posta, telefon, select, autocomplete, textarea, checkbox, radio, dosya yükleme ve arama.
- Hata, başarı ve yardım metni.
- Sektör kartı, yeterlilik kartı, haber kartı, duyuru kartı, doküman satırı, ücret kartı, lokasyon kartı ve güven kartı.
- Durum etiketi, alert, accordion, modal, drawer, pagination, breadcrumb, skeleton, empty state ve toast.

MASAÜSTÜ HEADER

1. İnce yardımcı bant:
   - Sınav Takvimi
   - Sınav Ücretleri
   - Neden Zorunlu?
   - Gerçek sosyal medya bağlantıları

2. Marka satırı:
   - Solda orijinal Mavi Belge logosu
   - Yanında `MYK & TÜRKAK Yetkili Sınav ve Belgelendirme Kuruluşu`
   - Sağda orijinal MYK ve TÜRKAK logoları
   - Belirgin Online Başvuru butonu

3. Ana navigasyon:
   - Meslekler ve Belgeler
   - Sınav ve Başvuru
   - Kurumsal
   - Bilgi Merkezi
   - İletişim
   - Sağda Belge Sorgula ve telefon

Gerçek mevcut sayfaları bu sade grupların dropdown menülerinde koru. Sayfa uydurma veya gerçek içeriği silme.

MOBİL HEADER

- Orijinal logo
- Başvuru aksiyonu
- Menü butonu
- Slide-in menü
- Accordion alt menüler
- Telefon ve Belge Sorgulama
- MYK/TÜRKAK güven alanı

FOOTER

- Orijinal logo ve kısa gerçek kurumsal açıklama
- Meslekler ve Belgeler
- Sınav ve Başvuru
- Kurumsal
- Bilgi Merkezi
- İletişim
- Gerçek adres, telefon ve e-posta
- Sosyal medya
- KVKK, Gizlilik, Kalite ve Tarafsızlık
- Telif bilgisi

Footer'da 83 mesleği tek tek listeleme. Ana gruplar ve Tüm Meslekler bağlantısı yeterli.

Bu aşamada ana sayfa veya alt sayfa üretme. Ortak kabuğu tamamla ve sonraki tüm ekranların bu kabuğu kullanmasını sağla.
```

---

## PROMPT 02 — Ana sayfa masaüstü ve mobil

```text
PROMPT 00 VE PROMPT 01 KURALLARINI DEĞİŞTİRMEDEN UYGULA.

Şimdi Mavi Belge ana sayfasını sıfırdan oluştur. Design System ve ortak header/footer aynen kullanılacak.

FRAME'LER

- D / PUB-01 / Ana Sayfa
- M / PUB-01 / Ana Sayfa

SAYFA BÖLÜMLERİ

1. Ortak header
2. Hero
3. Yetki ve güven bandı
4. Meslek/yeterlilik arama
5. Kullanıcı türüne göre dört başlangıç kartı
6. 12 sektör
7. Başvuru ve belgelendirme süreci
8. Güncel duyurular ve haberler
9. MYK belgesi neden zorunlu?
10. Yetki ve akreditasyon
11. Sınav merkezleri/lokasyonlar
12. Seçilmiş gerçek referanslar
13. İletişim çağrısı
14. Ortak footer

HERO

H1: `Sanayi işçisinin güvenli belgesi, mesleki geleceğin güvencesi.`

Alt metinde 12 sektör ve 83 görünür yeterlilik bilgisi final-rapor.md ile uyumlu kullanılmalı.

Aksiyonlar:
- Mesleğini Bul ve Başvur
- Kurumsal Toplu Sınav

Hero için mevcut sitede yeterli gerçek görsel varsa onu kullan. Yoksa gerçekçi bir endüstriyel çalışma ortamında, doğru kişisel koruyucu donanıma sahip kaynakçı görseli üret. Görselde yazı, logo, sertifika veya marka bulunmasın. Koyu lacivert overlay ile metin okunaklı olsun. Otomatik slider kullanma.

GÜVEN BANDI

- Gerçek TÜRKAK akreditasyon tarihi
- Gerçek MYK yetkilendirme tarihi
- İskenderun, Payas ve Aliağa

MESLEK ARAMA

Başlık: `Mesleğini bul, başvurunu yap`

Arama örneği: `Kaynakçı, Vinç Operatörü, Elektrikçi...`

Meslek adı, MYK kodu, sektör ve seviye üzerinden arama hissi ver.

KULLANICI KARTLARI

- Sahadaki Adayım
- İşveren / İK Sorumlusuyum
- Belge Sahibiyim
- Belgemi Yenileyeceğim

SEKTÖRLER

Gerçek 12 sektörün tamamını kullan:
- Makine
- Metalurji
- Metal
- Ulaştırma & Lojistik
- Plastik
- Elektrik
- Cam
- Tekstil
- İnşaat
- Mobilya
- Maden & Mermer
- Güzellik ve Saç Bakım

Gerçek program sayıları final-rapor.md'den alınmalı. Sektöre uygun gerçek veya dekoratif yardımcı görseller kullanılabilir.

HABER VE DUYURULAR

- Mevcut sitedeki gerçek başlıkları ve gerçek görselleri kullan.
- Haber ve duyuruyu aynı tür gibi gösterme.
- Tarih, kategori ve Devamını Oku aksiyonu bulunmalı.

REFERANSLAR

- Yalnız gerçek referans logoları.
- Otomatik logo slider yok.
- Sade grid veya kontrollü yatay liste.

MOBİL

- Hero metni görsel üzerinde rahat okunmalı.
- Ana aksiyonlar ilk ekranda erişilebilir olmalı.
- Sektörler tek/iki kolonlu akışa dönüşmeli.
- WhatsApp butonu önemli aksiyonları kapatmamalı.
- Yatay taşma olmamalı.

Bu aşamada yalnız bu iki bitmiş ana sayfa frame'ini oluştur. Açıklama veya alternatif ana sayfa üretme.
```

---

## PROMPT 03 — Meslekler, sektörler ve yeterlilik ekranları

```text
ÖNCEKİ TASARIM SİSTEMİ VE ORTAK KABUĞU DEĞİŞTİRMEDEN UYGULA.

Şimdi meslek keşfi, sektör ve yeterlilik ekranlarını oluştur.

FRAME'LER

- D/M PUB-02 Meslekler ve Yeterlilikler
- D/M PUB-03 Sektörler
- D/M PUB-04A Makine Meslekleri
- D/M PUB-04B Metalurji Meslekleri
- D/M PUB-04C Metal Meslekleri
- D/M PUB-04D Ulaştırma & Lojistik Meslekleri
- D/M PUB-04E Plastik Meslekleri
- D/M PUB-04F Elektrik Meslekleri
- D/M PUB-04G Cam Meslekleri
- D/M PUB-04H Tekstil Meslekleri
- D/M PUB-04I İnşaat Meslekleri
- D/M PUB-04J Mobilya Meslekleri
- D/M PUB-04K Maden & Mermer Meslekleri
- D/M PUB-04L Güzellik ve Saç Bakım Meslekleri
- D/M PUB-05A Çelik Kaynakçısı Yeterlilik Detayı
- D/M PUB-05B Makine Bakımcı Yeterlilik Detayı
- D/M PUB-05C İnşaat Boyacısı Yeterlilik Detayı

PUB-02

- Meslek adı/MYK kodu arama
- Sektör, seviye ve aktiflik filtreleri
- Sonuç sayısı
- Gerçek yeterlilik kartları
- Sayfalama
- Sonuç bulunamadı durumu

Yeterlilik kartı:
- Gerçek meslek adı
- MYK kodu
- Seviye
- Sektör
- Kısa gerçek açıklama
- Detay
- Ücret
- Başvur

PUB-03

- 12 gerçek sektör
- Gerçek program sayıları
- Gerçek kısa açıklama
- Sektöre uygun gerçek veya üretilmiş dekoratif görsel

PUB-04 SERİSİ

Aynı kanonik sektör şablonunu kullan. Her sektörde gerçek isim, gerçek yeterlilik listesi, doğru program sayısı ve uygun görsel kullan.

- Breadcrumb
- Sektör banner'ı
- Sektör adı ve açıklaması
- Program sayısı
- Arama ve seviye filtresi
- Yeterlilik listesi
- Kurumsal toplu sınav çağrısı
- İlgili dokümanlar
- Diğer sektörler

PUB-05 SERİSİ

- Breadcrumb
- Yeterlilik adı
- MYK kodu, seviye, revizyon ve sektör
- Kısa tanım
- Online Başvur
- Güncel Ücreti Gör
- Sınav Takvimine Git
- Program amacı ve kapsamı
- Giriş şartları
- Yeterlilik birimleri
- Teorik ve performans sınavları
- Geçme ölçütleri
- Geçerlilik, gözetim ve yenileme
- Belge zorunluluğu
- Resmî dayanak
- Dokümanlar
- İlgili yeterlilikler
- SSS

Masaüstünde sticky başvuru kartı; mobilde sabit alt aksiyon çubuğu kullan.

83 ayrı yeterlilik tasarımı üretme. Üç gerçek varyant, veriyle çoğalacak kanonik şablonu kanıtlamalı.
```

---

## PROMPT 04 — Başvuru, sınav, ücret, doküman ve operasyon sayfaları

```text
ÖNCEKİ TASARIM SİSTEMİ VE ORTAK KABUĞU DEĞİŞTİRMEDEN UYGULA.

FRAME'LER

- D/M PUB-06 Online Başvuru
- D/M PUB-06S Başvuru Başarılı
- D/M PUB-07 Sınav ve Belgelendirme Süreci
- D/M PUB-08 Sınav Ücretleri
- D/M PUB-09 Mevzuat ve Doküman Merkezi
- D/M PUB-16 Belge Yenileme Süreci
- D/M PUB-17 Öneri, İtiraz ve Şikâyetler
- D/M PUB-18 İş Başvurusu
- D/M PUB-21 Banka Hesap Bilgileri
- D/M PUB-22 Sık Sorulan Sorular

ONLINE BAŞVURU

Beş adımlı form:
1. Yeterlilik seçimi
2. Kişisel ve iletişim bilgileri
3. Bireysel/kurumsal başvuru
4. Güvenli belge yükleme ve kontrol
5. Özet ve tamamlandı

Aday hesabı, aday portalı ve online ödeme tasarlama.

Dosya yüklemede kabul edilen türler, boyut, ilerleme, başarı, hata, kaldırma ve güvenlik kontrolü göster. KVKK aydınlatması ile açık rızayı otomatik tek checkbox haline getirme.

SINAV VE BELGELENDİRME

- Başvuru
- Evrak kontrolü
- Sınav
- Değerlendirme
- Belgelendirme
- Gözetim ve yenileme
- Sınav kuralları
- Neden zorunlu?
- SSS

ÜCRETLER

- JPG kullanma.
- Aranabilir ve filtrelenebilir HTML tablo tasarla.
- Yeterlilik, kod, seviye, teorik, performans, toplam, belge basım, yenileme ve açıklama sütunları.
- Son güncelleme, geçerlilik tarihi, PDF indir, yazdır ve önceki tarifeler.
- Doğrulanmış ücret yoksa rakam uydurma.
- Mobilde kartlara dönüştür.

DOKÜMAN MERKEZİ

- Arama ve filtreler
- Doküman adı, kategori, kurum, revizyon, yayın/geçerlilik tarihi, tür, boyut, indir ve önceki sürümler
- Güncel ve arşiv ayrımı

İTİRAZ/ŞİKÂYET VE İŞ BAŞVURUSU

- Gerçek alanları kullan.
- Dosya yükleme, aydınlatma, hata ve başarı durumlarını göster.
- Hassas veri girişlerinde güven veren sade form dili kullan.

BANKA BİLGİLERİ

- Yalnız doğrulanmış gerçek banka/IBAN bilgisi.
- Bilgiye ulaşılamıyorsa sayı üretme.
- Kopyala aksiyonu ve ödeme açıklaması güvenlik uyarısı.
```

---

## PROMPT 05 — Haber, kurumsal, referans ve iletişim sayfaları

```text
ÖNCEKİ TASARIM SİSTEMİ VE ORTAK KABUĞU DEĞİŞTİRMEDEN UYGULA.

FRAME'LER

- D/M PUB-10 Haberler ve Duyurular
- D/M PUB-11 Haber Detayı
- D/M PUB-11B Duyuru Detayı
- D/M PUB-12 Kurumsal
- D/M PUB-13 Yetki Belgeleri ve Akreditasyon
- D/M PUB-14 Referanslarımız
- D/M PUB-15 İletişim ve Lokasyonlar
- D/M PUB-19 Sosyal Sorumluluk
- D/M PUB-20A Mesleki Yeterlilik Kurumu
- D/M PUB-20B Türk Akreditasyon Kurumu
- D/M PUB-20C Ulusal Meslek Standartları ve Ulusal Yeterlilikler
- D/M PUB-23 Genel Politika/Yasal Metin Şablonu

HABERLER VE DUYURULAR

- Eski sitedeki gerçek haber başlıklarını ve görsellerini kullan.
- Haber ve duyuru farklı içerik türleri olarak tasarlansın.
- Duyuruda önem seviyesi, yayın ve bitiş tarihi.
- Haberde gerçek görsel, kategori, tarih, başlık ve özet.
- Arşivlenmiş duyuru durumunu göster.

KURUMSAL

- Mavi Belge Hakkında
- Misyon ve Vizyon
- Kalite Politikası
- Tarafsızlık Beyanı
- Yasal Dayanak
- Gizlilik ve KVKK
- İnsan Kaynakları
- Sosyal Sorumluluk

Uzun metinleri tek duvar halinde verme. İçindekiler veya sticky anchor navigasyon kullan.

YETKİ BELGELERİ

- Yalnız gerçek MYK/TÜRKAK işaretleri ve gerçek belgeler.
- Sahte sertifika üretme.
- Yetki kapsamı, gerçek tarihler, indirilebilir belgeler ve resmî doğrulama bağlantıları.

REFERANSLAR

- Yalnız gerçek referans logoları.
- Otomatik slider yok.
- Responsive logo grid ve firma adları.

İLETİŞİM

- İskenderun Merkez Ofis
- Payas Sınav Alanı
- Aliağa Sınav Alanı
- Gerçek adres, telefon, e-posta ve WhatsApp
- Harita, yol tarifi ve iletişim formu

SOSYAL SORUMLULUK

- Yalnız gerçek projeler ve gerçek görseller.
- Gerçek görsel yoksa sahte etkinlik fotoğrafı üretme; nötr yer tutucu kullan.

POLİTİKA ŞABLONU

Kalite, tarafsızlık, gizlilik, KVKK, yasal dayanak, logo kullanımı, belge iptali ve sınav kuralları için ortak okunabilir şablon üret.
```

---

## PROMPT 06 — Sistem durumları ve eksik kullanıcı durumları

```text
ÖNCEKİ TASARIM SİSTEMİNİ DEĞİŞTİRMEDEN UYGULA.

FRAME'LER

- D/M STATE-01 404 Sayfa Bulunamadı
- D/M STATE-02 Arama Sonucu Bulunamadı
- D/M STATE-03 Form Gönderim Hatası
- D/M STATE-04 Başvuru Başarılı
- D/M STATE-05 Doküman Bulunamadı
- D/M STATE-06 Dış Sisteme Yönlendirme
- D/M STATE-07 Bakım Modu
- D/M STATE-08 Erişim Engellendi
- D/M STATE-09 Çerez Tercihleri
- D/M STATE-10 Yükleniyor/Skeleton

Tüm metinler Türkçe olsun. Kullanıcıyı suçlayan teknik hata dili kullanma. Her durumda uygun geri dönüş aksiyonu ver.

Dış sistem yönlendirme ekranında Sınav Takvimi ve Belge Sorgulama bağlantısının Mavi Belge dışındaki bir sisteme gittiğini açıkça belirt.

404 ekranında:
- Meslekleri Ara
- Online Başvuru
- Ana Sayfa
- İletişim

aksiyonları bulunsun.
```

---

## PROMPT 07 — Yönetim paneli masaüstü ve mobil

```text
KAMU SİTESİNİN MARKA TOKENLARINI KULLAN; FAKAT ADMIN İÇİN AYRI UYGULAMA KABUĞU OLUŞTUR. KAMU HEADER VE FOOTER'INI ADMIN PANELİNDE KULLANMA.

FRAME'LER

- D/M ADM-01 Yönetici Girişi
- D/M ADM-02 Yönetim Paneli
- D/M ADM-03 Başvuru Listesi
- D/M ADM-04 Başvuru Detayı
- D ADM-05 Sektör ve Yeterlilik Yönetimi
- D ADM-06 Yeterlilik Düzenleme
- D ADM-07 Haber ve Duyuru Yönetimi
- D ADM-08 Doküman ve Sürüm Yönetimi
- D ADM-09 Ücret Yönetimi
- D ADM-10 Kullanıcı, Rol ve Yetkiler
- D ADM-11 Denetim Kayıtları
- D ADM-12 Lokasyon ve Referans Yönetimi
- D ADM-13 SEO ve Yönlendirmeler
- D/M ADM-14 Profil ve İki Aşamalı Doğrulama

ADMIN KABUĞU

- Sol navigasyon
- Üst bar
- Breadcrumb
- Sayfa başlığı ve ana aksiyon
- Bildirimler
- Kullanıcı menüsü
- Mobil drawer

BAŞVURU DURUMLARI

- Yeni
- İncelemede
- Eksik Belge
- Onaylandı
- Sınav Planlandı
- Tamamlandı
- Reddedildi
- İptal Edildi

Durumu yalnız renkle anlatma.

BAŞVURU DETAYI

- Başvuru özeti
- Aday bilgileri
- Yeterlilik
- Güvenli belgeler
- Durum geçmişi
- Sorumlu personel
- İç notlar
- E-posta geçmişi
- Denetim izi
- Eksik belge talebi
- Reddetme gerekçesi

İÇERİK MODÜLLERİ

- Sayfalar
- Sektörler
- Yeterlilikler
- Ücretler
- Dokümanlar
- Haberler
- Duyurular
- Referanslar
- Lokasyonlar
- Yönlendirmeler
- SEO alanları

YETKİLER

- Süper Yönetici
- Başvuru Yöneticisi
- Başvuru İnceleme Personeli
- İçerik Editörü
- Yayın Onay Yetkilisi
- Salt Okunur Denetçi

Admin ekranlarında gerçek operasyon dili kullan. Generic satış grafikleri veya anlamsız istatistikler üretme.
```

---

## PROMPT 08 — Son tutarlılık ve kalite denetimi

```text
Şimdi projedeki bütün frame'leri denetle ve sorunlu olanları aynı tasarım sistemiyle düzelt.

Bu aşamada yeni bir görsel yön üretme. Mevcut onaylı tasarım sistemini uygula.

KONTROL LİSTESİ

1. Bütün kamu ekranlarının Desktop 1440 ve Mobile 390 frame'i var mı?
2. Kritik admin ekranlarının desktop ve mobil frame'i var mı?
3. Bütün ekranlar Türkçe mi?
4. İngilizce buton, durum, filtre veya form etiketi kaldı mı?
5. Orijinal Mavi Belge, MYK, TÜRKAK ve Europass logoları mı kullanıldı?
6. Yapay veya değiştirilmiş logo var mı? Varsa kaldır.
7. Gerçek haber, sektör, meslek ve kurumsal içerikler kullanıldı mı?
8. Uydurma fiyat, telefon, adres, tarih, yetki veya referans var mı? Varsa kaldır.
9. Header ve footer bütün kamu ekranlarında aynı mı?
10. Admin kabuğu bütün admin ekranlarında aynı mı?
11. Renk, tipografi, buton, kart ve form sistemi tutarlı mı?
12. Mobilde yatay taşma var mı?
13. Form label'ları kalıcı mı?
14. Focus ve hata durumları görünür mü?
15. Büyük tablolar mobilde kullanılabilir biçime dönüşüyor mu?
16. Dış bağlantılar açıkça belirtiliyor mu?
17. WhatsApp veya floating buton önemli aksiyonları kapatıyor mu?
18. Otomatik slider, sahte sertifika, sahte referans veya anlamsız dekorasyon var mı?
19. Her frame doğru kod ve adla isimlendirilmiş mi?
20. Açıklama panoları yanlışlıkla gerçek ekran yerine mi kullanılmış?

Eksik veya hatalı ekranları doğrudan düzelt. Yalnız rapor yazma.

Tamamlandığında son olarak ayrı bir `QA / Ekran Envanteri` frame'i oluştur ve bütün ekran kodlarını şu durumlarla listele:
- Desktop hazır
- Mobil hazır
- Gerçek içerik doğrulandı
- Orijinal logo doğrulandı
- Responsive kontrol edildi
- Erişilebilirlik kontrol edildi
```

