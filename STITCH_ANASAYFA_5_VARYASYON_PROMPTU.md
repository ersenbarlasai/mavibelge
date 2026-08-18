# Mavi Belge — Beş Yeni Ana Sayfa Varyasyonu İçin Stitch Promptu

Bu çalışmada mevcut ana sayfayı düzeltme veya kopyalama. Mavi Belge için ana sayfa tasarımlarını sıfırdan oluştur. Önceki üretilmiş ekranları referans alma. Aşağıdaki kurallara uyan, birbirinden yapısal olarak farklı beş ana sayfa tasarım yönü hazırla ve her yönün hem masaüstü hem mobil ekranını üret.

## 1. Üretilecek ekranlar

Toplam tam olarak 10 yüksek doğruluklu ekran oluştur:

1. `ANASAYFA / V1 Görev Odaklı / Desktop 1440`
2. `ANASAYFA / V1 Görev Odaklı / Mobile 390`
3. `ANASAYFA / V2 Endüstriyel Kurumsal / Desktop 1440`
4. `ANASAYFA / V2 Endüstriyel Kurumsal / Mobile 390`
5. `ANASAYFA / V3 Arama Odaklı Minimal / Desktop 1440`
6. `ANASAYFA / V3 Arama Odaklı Minimal / Mobile 390`
7. `ANASAYFA / V4 Sektör Odaklı / Desktop 1440`
8. `ANASAYFA / V4 Sektör Odaklı / Mobile 390`
9. `ANASAYFA / V5 Güven ve Haber Odaklı / Desktop 1440`
10. `ANASAYFA / V5 Güven ve Haber Odaklı / Mobile 390`

Yalnızca moodboard, bileşen koleksiyonu, görsel galeri veya açıklama üretme. On ekranın her biri header'dan footer'a kadar tamamlanmış gerçek web sayfası mockup'ı olsun. Beş varyasyon sadece renk, fotoğraf veya kart stili değişikliği olmasın; bilgi hiyerarşisi, hero düzeni, görev alanlarının sunumu, arama deneyimi, haber sunumu ve sektör gezinmesi bakımından belirgin biçimde farklılaşsın. Buna rağmen marka, içerik, menü, işlemler ve kurumsal güven unsurları tüm varyasyonlarda tutarlı kalsın.

### Çalışma alanındaki yerleşim

- Beş masaüstü tasarımını çalışma alanının üst sırasında, soldan sağa V1, V2, V3, V4 ve V5 sırasıyla yan yana yerleştir.
- Beş mobil tasarımı masaüstü sırasının altında, ayrı bir ikinci sırada, soldan sağa V1, V2, V3, V4 ve V5 sırasıyla yan yana yerleştir.
- Her mobil ekran kendi masaüstü varyasyonuyla aynı tasarım dilini taşısın; ancak masaüstünün daraltılmış kopyası olmasın.
- Ekranları üst üste bindirme. Her frame arasında karşılaştırmayı kolaylaştıracak düzenli ve yeterli boşluk bırak.
- Masaüstü ve mobil sıralarını büyük ve açık grup etiketleriyle ayır: `WEB TASARIMLARI` ve `MOBİL TASARIMLAR`.

## 2. Kaynaklar ve içerik doğruluğu

İçerik için kaynak önceliği:

1. Projeye eklenmiş `final-rapor.md`
2. Gerçek metin, bağlantı, iletişim bilgisi ve görseller için `https://mavibelge.com.tr/`
3. Orijinal iletişim bilgileri için `https://mavibelge.com.tr/iletisim/`

Site tamamen Türkçe olacak. Lorem ipsum, İngilizce menü, İngilizce buton, uydurma şirket adı, uydurma yetki numarası, uydurma istatistik, uydurma müşteri yorumu veya uydurma sertifika kullanma. Gerçek veri bulunmayan yerde rakam üretme.

## 3. Logo ve kurumsal işaretler — değiştirilemez kural

- Projeye eklenen şu üç dosya tek ve zorunlu logo kaynağıdır:
  - `header-logo.png` — Mavi Belge ana kurum logosu, 424×99 px
  - `myk_logo.png` — MYK orijinal logosu, 71×95 px
  - `turkak_logo.png` — TÜRKAK orijinal logosu, 66×95 px
- Mavi Belge'nin ana kurum logosu olarak kesinlikle `header-logo.png` dosyasını kullan.
- MYK işareti olarak kesinlikle `myk_logo.png`, TÜRKAK işareti olarak kesinlikle `turkak_logo.png` dosyasını kullan.
- Logo yeniden çizilmesin, yapay zekâ ile üretilmesin, yazısı değiştirilmesin, oranı bozulmasın, farklı fontla taklit edilmesin ve efekt uygulanmasın.
- Masaüstü header'ın sol tarafında `header-logo.png`; sağ tarafına doğru, menü/iletişim düzeninden sonra bir güven grubu içinde önce `myk_logo.png`, sonra `turkak_logo.png` yer alsın.
- MYK ve TÜRKAK logolarını header'ın soluna veya ana logonun hemen yanına sıkıştırma. Sağ tarafta, dengeli ve açıkça seçilebilir biçimde konumlandır.
- Mobil header'da `header-logo.png` solda; `myk_logo.png` ve `turkak_logo.png` sağ tarafta kompakt güven grubu olarak yer alsın. Menü düğmesiyle çakışma olursa menü düğmesini ayrı satır/alan içinde çöz; MYK ve TÜRKAK logolarını kaldırma.
- MYK ve TÜRKAK logolarını yeniden üretme veya benzerlerini çizme; yalnızca resmi/orijinal dosyaları kullan.
- Bu logoları Mavi Belge logosuyla eşit marka gibi sunma. Görsel hiyerarşide Mavi Belge ana marka olsun; MYK ve TÜRKAK daha küçük boyutta, uygun ayırıcıyla ve sırasıyla “MYK tarafından yetkilendirilmiş” ve “TÜRKAK tarafından akredite” güven ifadeleriyle gösterilsin.
- Logoları kırpma, maskeleme, tek renge çevirme, arka planlarını değiştirme, üstüne yazı bindirme veya CSS ile yeniden renklendirme.
- `header-logo.png` dosyasının geniş yatay oranını koru. MYK ve TÜRKAK dosyalarının dikey oranlarını koru ve yüksekliklerini kendi aralarında eşitle.
- Logoların çevresinde yeterli boşluk bırak, okunabilirliklerini koru ve mobilde küçülüp belirsiz hale gelmelerini önle.
- Dosyalara erişilemiyorsa sahte logo üretme; ilgili alanı açıkça `ORİJİNAL LOGO DOSYASI EKSİK` şeklinde işaretle.

## 4. Tüm varyasyonlarda kullanılacak gerçek header içeriği

Header içeriği gerçek Mavi Belge sitesinden alınmalı ve tüm varyasyonlarda aynı bilgi setini taşımalı:

- Mavi Belge orijinal logosu
- MYK orijinal logosu
- TÜRKAK orijinal logosu
- Ana menü:
  - Anasayfa
  - Kurumsal
  - Mesleki Yeterlilik
  - Sınav Belgelendirme
  - Mesleklerimiz
  - Medya
  - Referanslarımız
  - İnsan Kaynakları
  - İletişim
- Telefon: `0850 215 44 22`
- E-posta: `info@mavibelge.com.tr`
- Belirgin ana eylem: `Online Başvuru`

Masaüstünde menüyü okunaklı ve sade biçimde çöz. Gerekirse ikincil menüleri mega menü altında grupla; ancak gerçek menü başlıklarını değiştirme veya kaybetme. Mobilde logo, erişilebilir menü düğmesi ve `Online Başvuru` eylemi görünür olsun. Mobil menü açıldığında tüm gerçek menüler, telefon ve e-posta erişilebilir olsun.

Header sabitlenebilir ancak gereksiz yüksek, kalabalık veya iki ayrı dev menü şeklinde olmasın. MYK ve TÜRKAK logoları masaüstünde güven şeridi veya header'ın kontrollü ikinci katmanında; mobilde ise sıkışıklık yaratmadan kompakt güven satırında sunulabilir.

## 5. Ana sayfanın en önemli beş işlem alanı

Aşağıdaki beş işlem bütün varyasyonlarda hero alanında veya hero'nun hemen altında, ilk ekran yüksekliği içinde açıkça görülmeli:

1. `Sınav Ücretleri`
   - Yardımcı metin: `Mesleğinize ait güncel sınav ve belgelendirme ücretlerini inceleyin.`
2. `Belge Sorgulama`
   - Yardımcı metin: `MYK portalı üzerinden belgenizi güvenle sorgulayın.`
   - Bunun dış bağlantı olduğunu uygun bir dış bağlantı simgesi veya kısa bilgiyle belirt.
3. `Belge Yenileme`
   - Yardımcı metin: `Geçerlilik süresi yaklaşan belgeniz için yenileme adımlarını öğrenin.`
4. `Sınav Süreçleri`
   - Yardımcı metin: `Başvurudan sınav ve belgelendirmeye kadar tüm süreci görün.`
5. `Sınav Talepleri`
   - Yardımcı metin: `Bireysel veya kurumsal sınav talebinizi bize iletin.`

Bu beş alan sıradan küçük bağlantılar gibi görünmemeli. Güçlü ikon, kısa açıklama ve net eylem diliyle birincil görevler olarak tasarlanmalı. `Sınav Talepleri` ile `Online Başvuru` görsel ve anlamsal olarak birbirine karıştırılmamalı. Dokunma alanları mobilde en az 44×44 px olmalı.

## 6. “Mesleğini Bul” araması

Tüm varyasyonlarda belirgin, gerçek bir arama deneyimi tasarla:

- Başlık: `Mesleğini Bul`
- Açıklama: `Meslek adını veya ulusal yeterlilik kodunu yazarak belgelendirme programını bulun.`
- Arama placeholder'ı: `Örn. Kaynakçı, CNC Programcısı veya 11UY0010-3`
- Buton: `Mesleği Ara`
- Arama önerileri için erişilebilir açılır liste tasarla.
- Öneri satırlarında meslek adı, yeterlilik kodu, seviye ve sektör bilgisi yer alabilecek bir düzen göster.
- `Tüm Meslekleri Gör` ikincil bağlantısını ekle.
- Masaüstünde güçlü bir arama alanı; mobilde tam genişlikte, kolay dokunulur alan ve alt alta sonuç yapısı kullan.
- Sahte arama sonucu veya doğrulanmamış yeterlilik kodu üretme. Örnek veri kullanılıyorsa resmi sitede doğrulanabilen gerçek meslek adlarından yararlan.

## 7. Yetkilendirilmiş sektörler

Ana sayfada aşağıdaki 12 sektörün tamamını göster; hiçbirini atlama:

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

Bölüm başlığı: `Yetkilendirilmiş Sektörler`

Her sektör kartı veya satırı sektör adını, ayırt edilebilir sade bir ikonu/görseli ve `Meslekleri İncele` eylemini taşısın. Sektörleri dekoratif bir logo şeridi gibi değil, kullanıcıyı ilgili yeterliliklere götüren gerçek gezinme öğeleri olarak tasarla. Mobilde yatay kaydırmaya mecbur bırakma; erişilebilir 2 sütunlu veya tek sütunlu düzen kullan. Masaüstünde tüm sektörlerin rahat taranabildiği dengeli grid veya liste oluştur.

## 8. Ana sayfada bulunması gereken diğer bölümler

Dört varyasyonun her birinde aşağıdaki içerikleri tasarım yönüne uygun sırada kullan:

- Kurumsal güven bölümü:
  - Başlık: `Yetkilendirilmiş Belgelendirme Kuruluşu`
  - Gerçek açıklama: `Mavi Belge, 02.05.2016 tarihinde Türk Akreditasyon Kurumu (TÜRKAK) tarafından TS EN ISO / IEC 17024:2012 standardında akredite edilmiş, 16.08.2016 tarihinde Mesleki Yeterlilik Kurumu (MYK) tarafından sınav ve belgelendirme faaliyetleri alanında yetkilendirilmiş belgelendirme kuruluşudur.`
  - Eylemler: `Mavi Belge Hakkında` ve `Yetki Belgelerimizi İnceleyin`
- Süreç özeti:
  - `Mesleğini Seç`
  - `Başvurunu Tamamla`
  - `Sınava Katıl`
  - `Belgeni Al`
- Zorunlu Haberler bölümü:
  - Bölüm başlığı kesinlikle `Haberler ve Duyurular` olsun.
  - Gerçek `mavibelge.com.tr` sitesinden alınan gerçek haber başlıklarını, yayın tarihlerini, kısa özetlerini ve mevcut gerçek haber görsellerini kullan.
  - Masaüstünde en az 3 haber kartı göster; mobilde aynı haberleri okunabilir tek sütun veya kontrollü kaydırma olmadan dikey akışta göster.
  - Bir haberi görsel/hiyerarşi açısından öne çıkarabilir, diğerlerini ikincil kartlar halinde gösterebilirsin.
  - Kartlarda kategori/etiket, tarih, başlık, kısa özet ve `Haberi Oku` eylemi bulunabilecek bir düzen kur.
  - Bölüm sonunda `Tüm Haberler` bağlantısı yer alsın.
  - Uydurma haber, uydurma tarih veya yapay başlık üretme.
- Referanslar: yalnızca gerçek sitedeki orijinal referans logoları; logoları yeniden çizme, uydurma marka ekleme. Sonsuz ve hızlı slider yerine sakin, erişilebilir grid veya kontrollü carousel kullan.
- Son eylem alanı: `Mesleki yeterlilik belgeniz için ilk adımı atın.`; `Online Başvuru` ve `Sınav Talebi Oluştur` butonları.

Ana sayfayı aşırı uzun ve tekrarlarla dolu hale getirme. Aynı işlemi farklı bölümlerde gereksiz yere tekrarlama. İçerik hiyerarşisi kullanıcıların şu üç sorusunu hızlı cevaplamalı: “Mesleğim kapsamda mı?”, “Ne kadar ücret ödeyeceğim?”, “Nasıl başvuracağım?”

## 9. Gerçek footer içeriği

Footer bilgilerini orijinal siteden al ve çağdaş, okunabilir biçimde yeniden düzenle. Şu bilgiler yer almalı:

- Mavi Belge orijinal logosu
- Kuruluş adı: `Mavi Belge Uluslararası Sert. ve Göz. Hiz. Ltd. Şti.`
- Kısa kurum tanıtımı
- Merkez ofis:
  - `Mustafa Kemal Mah. İbrahim Karaoğlanoğlu Cad. Atay İş Merkezi Kat:12 Daire:63–64 İskenderun / HATAY Pk:31200`
- Telefonlar:
  - `0850 215 44 22`
  - `0326 441 44 22`
  - `0542 618 62 84`
- E-posta: `info@mavibelge.com.tr`
- Payas Sınav Alanı:
  - `Yıldırım Beyazıt, Özkul Çolak Cd., 31900 Payas / Dörtyol / Hatay`
- İzmir Aliağa Sınav Alanı:
  - `Siteler Mahallesi, 35800 Aliağa / İzmir`
  - Telefon: `0542 619 62 84`
- Hızlı bağlantılar:
  - Online Başvuru
  - Sınav Ücretleri
  - Belge Sorgulama
  - Belge Yenileme
  - Sınav Süreçleri
  - Sınav Talepleri
- Kurumsal bağlantılar:
  - Hakkımızda
  - Yetki Belgelerimiz
  - Haberler
  - Referanslarımız
  - İletişim
- Yasal bağlantılar:
  - KVKK
  - Gizlilik Politikası
  - Çerez Tercihleri
- Telif satırında yıl dinamik gösterilecek biçimde tasarlansın: `© Mavi Belge. Tüm hakları saklıdır.`

Sosyal medya hesabı doğrulanamıyorsa sosyal medya ikonu veya bağlantısı uydurma. Footer masaüstünde mantıklı kolonlara ayrılmış, mobilde okunabilir akordeonlar veya açık bölümler halinde olsun. Telefonlar ve e-posta mobilde tıklanabilir görünsün.

## 10. Beş varyasyonun tasarım yönleri

### V1 — Görev Odaklı Kurumsal

- Kullanıcı işlemlerini merkeze alan, güven veren kurumsal portal yaklaşımı.
- Hero'nun solunda güçlü başlık ve kısa açıklama, sağında `Mesleğini Bul` araması.
- Beş önemli işlem hero'nun hemen altında büyük ve eşit ağırlıklı görev kartları olarak yer alsın.
- Ardından süreç adımları, yetkilendirilmiş sektörler, kurumsal güven, haberler, referanslar ve footer gelsin.
- Kart düzeni temiz, hızlı taranabilir ve resmi kurum ciddiyetine uygun olsun.
- Hero arka planında mesleki yeterlilik ve endüstriyel çalışma ortamını destekleyen, metni boğmayan geniş bir görsel kullan.

### V2 — Endüstriyel Kurumsal

- Güçlü fakat abartısız endüstriyel fotoğraf kullanılan, daha etkileyici kurumsal tanıtım yaklaşımı.
- Hero'da Türkiye'deki gerçek çalışma ortamlarını temsil eden geniş fotoğraf; fotoğraf üzerinde okunabilir koyu katman ve kısa mesaj.
- `Mesleğini Bul` araması hero içinde öne çıksın.
- Beş işlem, hero'nun alt sınırına bindirilmiş kontrollü bir görev paneli olarak sunulsun.
- Sektörler fotoğraf/ikon destekli grid; ardından kurumun yetki ve akreditasyon güven bölümü gelsin.
- Koyu lacivert, beyaz ve markadan alınan kontrollü vurgu rengi kullan; güvenlik ekipmanı doğru olmayan veya yapay görünen sahneler üretme.
- Bu varyasyon görsel açıdan en güçlü seçenek olsun: büyük hero fotoğrafı, seçilmiş sektör fotoğrafları ve haber görselleri dengeli kullanılsın; okunabilirlikten ödün verme.

### V3 — Arama Odaklı Minimal

- Daha sakin, çok temiz ve içerik odaklı bir yaklaşım.
- Sayfanın ilk odağı büyük `Mesleğini Bul` araması olsun; kullanıcı bir arama motoru sadeliğinde mesleğe ulaşabilsin.
- Arama alanının altında beş önemli işlem kompakt ama çok belirgin kısa yollar halinde yer alsın.
- Yetkilendirilmiş sektörler alfabetik ve kolay taranan liste/grid yaklaşımıyla gösterilsin.
- Boşluk, tipografi, ince ayırıcılar ve az sayıda kaliteli görsel kullan.
- Minimal olmak adına kurumsal güven unsurlarını veya eylemleri gizleme.
- Hero arkasında tam ekran ağır fotoğraf yerine açık tonlu, profesyonel ve kontrollü bir arka plan görseli/kolaj kullanılabilir.

### V4 — Sektör Odaklı Keşif

- Kullanıcıların önce sektör üzerinden ilerleyebildiği, görsel fakat kurumsal bir keşif yaklaşımı.
- Hero içinde kısa kurumsal mesaj, `Mesleğini Bul` araması ve ana başvuru eylemi yer alsın.
- Hero sonrasında 12 sektör güçlü, erişilebilir bir sektör gezgini olarak öne çıksın.
- Beş önemli işlem sektör bölümünden hemen sonra yatay olmayan, güçlü görev matrisi olarak sunulsun.
- Seçili sektör kartında o sektördeki mesleklerin açılabileceğini anlatan örnek etkileşim durumu göster; ancak doğrulanmamış meslek veya sayı üretme.
- Sonraki sıra: süreç özeti, kurumsal güven, haberler, referanslar ve footer.
- Sektör kartlarında tekdüze jenerik ikonlar yerine tutarlı fotoğraf kırpmaları, kaliteli çizgisel ikonlar veya ikisinin dengeli birleşimini kullan.

### V5 — Kurumsal Güven ve Haber Odaklı

- Yetkilendirme, akreditasyon, güncel gelişmeler ve kurumun güvenilirliğini öne çıkaran editoryal kurumsal yaklaşım.
- Hero alanında güçlü bir arka plan görseli, kısa kurumsal mesaj, `Mesleğini Bul` araması ve `Online Başvuru` eylemi birlikte çözülsün.
- Hero'nun hemen altında beş kritik işlem belirgin görev şeridi/matrisi olarak gösterilsin.
- Ardından kurumsal güven bölümü ve MYK/TÜRKAK yetkilendirme anlatısı gelsin.
- `Haberler ve Duyurular` bu varyasyonda özellikle güçlü olsun: bir büyük öne çıkan haber ile yanında iki ikincil haber kullan; görseller, tarihler ve başlıklar rahat taransın.
- Sonraki sıra: yetkilendirilmiş sektörler, süreç özeti, referanslar, son eylem alanı ve footer.
- Editoryal dergi görünümüne kaçmadan, resmi bir belgelendirme kuruluşunun güncel ve şeffaf yüzünü hissettir.

## 11. Görsel üretim kuralları

Gerekli yerlerde destekleyici yeni görseller oluşturabilirsin. Ancak:

- Öncelik orijinal Mavi Belge sitesindeki gerçek ve kullanılabilir görsellerdir.
- Yeni üretilen görseller yalnızca dekoratif/destekleyici kurumsal fotoğraf veya illüstrasyon olabilir.
- Mavi Belge logosu, MYK logosu, TÜRKAK logosu, akreditasyon işareti, sertifika, belge, resmi mühür veya referans firma logosu üretme.
- Üretilen görselleri gerçek Mavi Belge personeli, gerçek sınav veya gerçek etkinlik kaydı gibi sunma.
- Türk çalışma ortamlarını yansıtan, fotogerçekçi fakat yapay görünmeyen sahneler kullan.
- Kişisel veri, okunabilir sahte belge, sahte tabela veya sahte marka oluşturma.
- İş sağlığı ve güvenliği ekipmanları sektöre uygun ve doğru olsun.
- Stok fotoğraf klişelerinden, yapay el sıkışma sahnelerinden ve aşırı poz verilmiş insanlardan kaçın.
- Hero alanlarında arka plan görseli kullanabilirsin. Metin okunabilirliği için kontrollü koyu/açık katman, odak noktası doğru kırpma ve yeterli kontrast uygula.
- Sayfaları görsel açıdan zenginleştir; ancak her bölüme rastgele fotoğraf koyma. Hero, sektörler, haberler ve kurumsal güven bölümlerindeki görseller içerikle anlamlı ilişki kursun.
- Beş tasarımın tamamında aynı hero görselini kullanma. Her varyasyon için farklı fakat aynı marka dünyasına ait görsel kompozisyon seç.

## 12. Görsel dil ve erişilebilirlik

- Genel karakter: kurumsal, sade, modern, güvenilir, erişilebilir ve Türkiye'deki mesleki yeterlilik hizmetine uygun.
- Generic SaaS görünümü, neon renkler, ağır glassmorphism, aşırı gradient, dev yuvarlatılmış oyuncak kartlar, gereksiz 3D ikonlar ve kripto/fintech estetiği kullanma.
- Renkleri orijinal Mavi Belge logosundan türet; ana marka rengi, koyu lacivert, beyaz ve nötr gri taban kullan.
- Başlıklar güçlü fakat aşırı büyük olmasın; Türkçe karakterleri doğru gösteren profesyonel bir sans-serif yazı tipi kullan.
- WCAG 2.2 AA kontrast hedefle.
- Bilgiyi yalnızca renkle anlatma.
- Odak durumları, hover durumları, aktif menü, buton ve arama önerisi durumlarını göster.
- Mobilde yatay taşma, sıkışmış kart, okunamayacak küçük yazı veya masaüstünün küçültülmüş kopyası olmasın.
- Masaüstü tasarım genişliği 1440 px; içerik maksimum genişliği yaklaşık 1200–1280 px.
- Mobil tasarım genişliği 390 px; kenar boşlukları tutarlı ve dokunma alanları en az 44 px.
- Her ekran header'dan footer'a kadar tamamlanmış olsun.

## 13. Son kontrol ve teslim biçimi

Üretim tamamlanmadan önce on ekranı tek tek kontrol et:

- Beş varyasyon gerçekten farklı mı?
- Her varyasyonun masaüstü ve mobil ekranı var mı?
- Beş web ekranı üst sırada yan yana, beş mobil ekran ayrı alt sırada yan yana mı?
- Beş kritik işlem ilk ekran yüksekliğinde görünür mü?
- `Mesleğini Bul` araması belirgin mi?
- 12 yetkilendirilmiş sektörün tamamı var mı?
- Her varyasyonda `Haberler ve Duyurular` bölümü gerçek içerikle var mı?
- `header-logo.png`, `myk_logo.png` ve `turkak_logo.png` dosyaları aynen kullanılmış mı?
- MYK ve TÜRKAK logoları masaüstü header'ın sağ tarafına doğru konumlandırılmış mı?
- Header ve footer gerçek bilgileri kullanıyor mu?
- Tüm metinler Türkçe mi?
- Uydurma sayı, belge, müşteri, yorum veya logo var mı?
- Mobil ekranlarda yatay taşma veya masaüstünün küçültülmüş hali var mı?

Eksik varsa açıklama yazmakla yetinme; ekranları düzelt. Sonuçta on tam sayfa ekranını aynı proje içinde, yukarıdaki frame adları ve iki yatay sıra düzeniyle sun.
