# Mavi Belge Canlı Geçiş Uygulama Planı

> **Agentik uygulayıcılar için:** Bu plan görev görev uygulanırken `superpowers:executing-plans` kullanılmalıdır. Her kutu tamamlanmadan sonraki onay kapısına geçilmez.

**Amaç:** Staging'de kabul edilen WordPress kurulumunu geri alınabilir ve ölçülebilir bir bakım penceresinde `https://mavibelge.com.tr/` üzerinde canlıya almak.

**Mimari:** All-in-One WP Migration, staging'in dosya ve veritabanını tek özel `.wpress` paketiyle canlıya taşır ve serileştirilmiş URL verisini güvenli biçimde dönüştürür. DirectAdmin yedeği nihai geri dönüş kaynağıdır; GitHub yalnız kaynak kodu ve yayın belgelerini tutar. Kamuya açılma, P0 kabulü geçtikten sonra; eski sitenin silinmesi ise en az 14 günlük ayrı gözlem ve ayrı kullanıcı onayından sonra yapılır.

**Teknoloji:** WordPress 6.9.9, PHP 7.3.33, MariaDB 10.6.19, LiteSpeed, DirectAdmin File Manager + phpMyAdmin, All-in-One WP Migration, Post SMTP 4.0.2.

**Tasarım:** `raporlar/canli-gecis-tasarimi.md`

## Global kısıtlar

- Hedef ana adres tam olarak `https://mavibelge.com.tr` olacaktır; `cms-yeni.mavibelge.com.tr` staging olarak kalır.
- Canlıda kalıcı bağlantı yapısı `/%postname%/` olacaktır; yaygın 404 oluşursa kamuya açılmaz.
- PHP `7.3.33`, WordPress `6.9.9`, tema `0.6.8`, Mavi Belge Core `0.5.3` ve Post SMTP `4.0.2` sürümleri doğrulanır.
- DNS, SSL, MX/SPF/DKIM/DMARC ve posta kutuları değiştirilmez veya silinmez.
- `.wpress`, SQL, `wp-config.php`, SMTP parolası ve kişisel veri GitHub'a yüklenmez.
- Her geri döndürülemez işlem için ayrı kullanıcı onayı alınır.
- Yalnız DirectAdmin File Manager, phpMyAdmin ve WordPress yönetim paneli kullanılacağı varsayılır; SSH/WP-CLI gerektirilmez.
- Eski canlı dosya ve veritabanı en az 14 gün tutulur; silme bu planın cutover aşamasına dahil değildir.

## İnceleme odağı

- **Paket boyutu/timeout:** Export/import başlamadan boş disk alanı ve eklentinin dosya yükleme limiti paketten büyük olmalı; aksi durumda işlem başlatılmaz.
- **Yönetici oturumunun değişmesi:** Import sonrası canlıdaki eski kullanıcı yerine staging yönetici hesabıyla giriş gerekebilir; erişim doğrulanmadan bakım modu kaldırılmaz.
- **Serileştirilmiş alan adı verisi:** Ham SQL arama/değiştirme yapılmaz; yalnız migration aracının dönüşümü kullanılır.
- **Form tekrarı ve kişisel veri:** Beş formun her biri tam bir kez mail üretmeli; URL, log ve GitHub'a form verisi yazılmamalı.
- **Temiz permalink:** `/%postname%/` kaydedildikten sonra katalog, detay, arşiv, sayfalama ve 404 davranışı anonim tarayıcıyla sınanmalı.

---

### Task 1: Bakım penceresi ön uçuş kaydı

**Dosyalar:**
- Oluştur: `raporlar/canli-gecis-kayit-sablonu.md` (yalnız yerel operasyon kaydı; gizli bilgi yok)

**Üretir:** Geçiş boyunca her ölçümün yazılacağı, gizli bilgi içermeyen tek kayıt.

- [ ] **Adım 1: Bakım penceresini kaydet**

  Başlangıç saati, uygulayıcı, geri dönüş sorumlusu ve azami kesinti süresini yaz. Parola, kullanıcı adı, sunucu yolu veya IP yazma.

- [ ] **Adım 2: Yedek kanıtlarını kaydet**

  İki doğrulanmış eski canlı yedeğinin tarihini, yaklaşık boyutunu, web kökü dışındaki saklama türünü ve son geri yükleme provasının sonucunu yaz.

- [ ] **Adım 3: Sürüm ve artefakt eşleşmesini doğrula**

  GitHub `main` merge commit'i `af3ca05ddab16132264ed1dbfc80d82c1f11c55d`; tema `0.6.8`; Mavi Belge Core `0.5.3`; staging WordPress `6.9.9`; Post SMTP `4.0.2` olmalı. Tema paketinin beklenen SHA-256 değeri `04d926c2c1bcebc7418a832932729cbb7f590e1cd3ab22b0c923b51948626a4b` olmalı.

- [ ] **Adım 4: Kapasite önkoşulunu doğrula**

  DirectAdmin'de boş disk alanını, inode durumunu, PHP `upload_max_filesize=64M` sınırını ve All-in-One WP Migration import limitini kaydet. `.wpress` dosyası bu limitlerden büyükse canlı import başlatma; önce eklentinin desteklediği güvenli yükleme yöntemi için ayrı plan/onay al.

- [ ] **Adım 5: Mevcut canlı kanıtını sakla**

  Ana sayfa, iletişim, ücretler, örnek içerik ve mevcut sitemap'in ekran görüntülerini; eski kritik URL listesini özel alanda sakla. Kişisel veri içeren yönetim ekranı görüntüsü alma.

- [ ] **Adım 6: Ön uçuş kapısını değerlendir**

  Beklenen: iki geri dönüş yedeği `DOĞRULANDI`, kapasite `YETERLİ`, sürümler `EŞİT`, staging `KABUL`. Bunlardan biri eksikse burada dur.

- [ ] **Adım 7: Kayıt commit'i**

  Yalnız gizli bilgi içermeyen şablon/sonuç değiştiyse dosyayı tek başına stage et ve `docs: record production cutover preflight` mesajıyla commit et. Yedek, ekran görüntüsü veya kimlik bilgisi commit etme.

### Task 2: Staging'de özel aktarım paketini üretme

**Arayüzler:**
- Tüketir: Görev 1'in başarılı ön uçuş kaydı.
- Üretir: Özel `.wpress` paketinin dosya adı, byte boyutu ve SHA-256 kaydı; paketin kendisi repoya girmez.

- [ ] **Adım 1: Staging son dondurma kontrolü**

  Staging'de plan dışı içerik değişikliği olmadığını, tema/eklenti sürümlerini, Post SMTP testini ve beş formun önceki kabul durumunu doğrula. Export süresince yeni test formu gönderme.

- [ ] **Adım 2: Migration eklentisi eşitliği**

  Staging ve canlıda aynı PHP 7.3 uyumlu All-in-One WP Migration sürümünün kullanılabildiğini doğrula. Sürüm farkı varsa importtan önce eşitleme için ayrı kullanıcı onayı al.

- [ ] **Adım 3: Tam export al**

  All-in-One WP Migration → Dışa Aktar → Dosya yolunu kullan. Veritabanı, uploads, tema, Mavi Belge Core ve Post SMTP dahil olsun; cache, debug log ve eski migration/yedek paketleri hariç tutulsun. Alan adı için elle metin `REPLACE` kullanma.

- [ ] **Adım 4: Paketi özel alana indir**

  `.wpress` dosyasını yönetici cihazına indir. Herkese açık dizine, GitHub'a, e-posta ekine veya paylaşılabilir bulut bağlantısına koyma.

- [ ] **Adım 5: Bütünlük kaydı oluştur**

  Yerelde `Get-FileHash -Algorithm SHA256 <özel-paket-yolu>` çalıştır; yalnız dosya adı, byte boyutu ve hash'i operasyon kaydına yaz. Yerel mutlak kullanıcı yolunu veya içerik ayrıntısını kaydetme.

- [ ] **Adım 6: Export kapısını değerlendir**

  Beklenen: export hatasız, dosya boyutu sıfırdan büyük, hash oluşmuş ve canlı import kapasitesi paketten büyük. Aksi durumda canlıya geçme.

### Task 3: Canlıyı import için hazırlama

**Arayüzler:**
- Tüketir: Görev 2'nin doğrulanmış özel paketi.
- Üretir: Import öncesi son geri dönüş noktası ve erişilebilir bakım ekranı.

- [ ] **Adım 1: Ayrı canlı işlem onayı al**

  Kullanıcıdan açıkça "canlı hazırlığı ve son yedek için onaylıyorum" teyidi gelmeden bu göreve başlama.

- [ ] **Adım 2: İçerik dondurmasını başlat**

  Yönetici içerik girişini ve form gönderimlerini bakım penceresi boyunca durdur. DNS ve mail ayarlarına dokunma.

- [ ] **Adım 3: Bakım modunu etkinleştir**

  Anonim ziyaretçiye kısa bakım mesajı göster; yönetici erişimi devam etsin. Bakım aracının import sırasında silinebileceğini kaydet.

- [ ] **Adım 4: Son canlı yedeğini oluştur**

  DirectAdmin Create/Restore Backups ile canlı dosya+veritabanını aynı zaman noktasında yedekle. Boyut/tarih kaydını yaz ve File Manager'da dosyanın oluştuğunu doğrula.

- [ ] **Adım 5: Canlı WordPress çekirdeğini eşitleme kapısı**

  Canlı `6.8.10` ise WordPress `6.9.9` eşitlemesi için ayrı kullanıcı onayı al. Güncelleme sonrası yönetim, ana sayfa ve bakım ekranı smoke testini yap. Fatal/5xx olursa yeni site importuna geçmeden son yedekten geri dön.

- [ ] **Adım 6: Import öncesi eklenti durumunu kaydet**

  Eski canlıdaki aktif eklenti listesini yalnız ad+sürüm olarak kaydet. Parola veya ayar değeri kaydetme. All-in-One WP Migration'ın Görev 2 ile aynı sürümde etkin olduğunu doğrula.

- [ ] **Adım 7: Hazırlık kapısını değerlendir**

  Beklenen: bakım ekranı açık, yönetim erişilebilir, son yedek doğrulanmış, WordPress `6.9.9`, migration sürümü eşit. Herhangi biri başarısızsa import yapma.

### Task 4: `.wpress` paketini canlıya aktarma

**Arayüzler:**
- Tüketir: Görev 3'ün son geri dönüş noktası ve Görev 2'nin hash'i doğrulanmış paketi.
- Üretir: `https://mavibelge.com.tr` alan adına dönüştürülmüş yeni WordPress kurulumu; henüz kamuya açık kabul edilmez.

- [ ] **Adım 1: Ayrı import onayı al**

  Kullanıcıdan açıkça "doğrulanmış `.wpress` paketini canlıya import etmeyi onaylıyorum" teyidi gelmeden devam etme.

- [ ] **Adım 2: Paketi içe aktar**

  Canlı WordPress → All-in-One WP Migration → İçe Aktar → Dosya ile doğrulanmış paketi seç. Üzerine yazma uyarısını yalnız hedef alan adı `mavibelge.com.tr` ve son yedek doğrulandıysa kabul et.

- [ ] **Adım 3: Import sonucunu kaydet**

  Tek denemede tamamlanmasını bekle. Timeout, bağlantı kopması veya yarım importta kör tekrar yapma; hata mesajını kişisel veri olmadan kaydet ve Görev 7 geri dönüşüne geç.

- [ ] **Adım 4: Yönetici oturumunu yeniden kur**

  Import sonrası staging yönetici hesabıyla yeniden giriş yap. Yönetici erişimi yoksa bakım modunu kaldırma ve Görev 7'ye geç.

- [ ] **Adım 5: URL'leri doğrula**

  Ayarlar → Genel'de WordPress Adresi ve Site Adresi tam olarak `https://mavibelge.com.tr` olmalı. `cms-yeni` değeri görünüyorsa kamuya açma; migration aracının desteklenen URL düzeltme akışını kullan veya geri dön. Ham SQL `REPLACE` yapma.

- [ ] **Adım 6: Üretim ortamını doğrula**

  `WP_ENVIRONMENT_TYPE` üretim olmalı; arama motorlarından caydırma kapalı olmalı. `robots.txt` ve sitemap kabulü yapılmadan indekslemeyi ayrıca hızlandıracak işlem yapma.

- [ ] **Adım 7: Temiz permalink'i kaydet**

  Ayarlar → Kalıcı Bağlantılar'da `/%postname%/` seç ve bir kez kaydet. `/index.php/` bağlantılarını canlı canonical yapı olarak bırakma.

- [ ] **Adım 8: Etkin bileşenleri doğrula**

  Tema `Mavi Belge 0.6.8`; eklentiler en az `Mavi Belge Core 0.5.3` ve `Post SMTP 4.0.2` olmalı. Eski canlıya ait 19 eklentiyi yeniden etkinleştirme. Geçiş eklentisini kabul bitene kadar tut.

### Task 5: Kapalı bakım penceresinde P0 kabulü

**Arayüzler:**
- Tüketir: Görev 4'teki canlıya aktarılmış, bakım modundaki site.
- Üretir: Kamuya açma için ölçülebilir PASS/FAIL kararı.

- [ ] **Adım 1: Temel HTTP ve yönetim testi**

  HTTPS ana sayfa, `/wp-admin/`, `/yeterlilikler/`, iki farklı `/sektor/<slug>/`, bir yeterlilik detayı, `/sinav-ucretleri/` ve bilinmeyen bir 404 URL'sini doğrula. Beklenen: içerik rotaları 200, bilinmeyen rota özel 404; fatal, beyaz sayfa veya PHP uyarısı yok.

- [ ] **Adım 2: Varlık ve menü testi**

  Logo, header/footer, masaüstü menü, 1279 px hamburger, mobil menü, CSS, JS ve örnek uploads görselleri yüklenmeli. Tarayıcı konsolunda kritik kaynak hatası olmamalı.

- [ ] **Adım 3: İçerik ve ücret testi**

  En az bir yeterlilik kartı/detayı, sektör filtresi, ücret filtresi ve ücret detayını kontrol et. Tutar veya katalog verisi staging kabulünden farklıysa kamuya açma.

- [ ] **Adım 4: SMTP testi**

  Post SMTP test e-postasını yetkili alıcıya gönder. Sunucu kabulü ve gerçek alıcı kutusuna teslim birlikte doğrulanmalı.

- [ ] **Adım 5: Beş form testi**

  İletişim, online başvuru, sınav talebi, itiraz/şikâyet ve iş başvurusu formlarını yetkili sentetik veriyle birer kez gönder. Her biri tek e-posta üretmeli; dosyalı formlarda onaylı zararsız test ekleri ulaşmalı; kişisel veri URL'ye, hata sayfasına veya rapora yazılmamalı.

- [ ] **Adım 6: SMTP günlük politikasını doğrula**

  Post SMTP ayrıntılı gövde/ek günlüğünü kapat veya mevcut en kısa saklama süresine ayarla. Test mesajlarındaki sentetik veriyi gerekiyorsa eklentinin desteklenen temizleme aracıyla kaldır.

- [ ] **Adım 7: P0 kararını kaydet**

  Tüm P0 satırları PASS değilse kamuya açma; Görev 7 geri dönüş kararını uygula. PASS ise kullanıcıya test tablosunu bildir ve Görev 6 için ayrı onay iste.

### Task 6: Kamuya açma ve ilk gözlem

**Arayüzler:**
- Tüketir: Görev 5 P0 PASS sonucu.
- Üretir: `https://mavibelge.com.tr/` üzerinde kamuya açık yeni site ve zaman damgalı gözlem kaydı.

- [ ] **Adım 1: Ayrı kamuya açma onayı al**

  Kullanıcıdan açıkça "P0 sonuçlarını gördüm; yeni siteyi mavibelge.com.tr üzerinde kamuya açmayı onaylıyorum" teyidi al.

- [ ] **Adım 2: Bakım modunu kaldır**

  Siteyi anonim ziyaretçiye aç. Anonim/çıkış yapılmış tarayıcı ve özel pencerede ana sayfanın yeni site olduğunu doğrula.

- [ ] **Adım 3: Üretim indeksleme testi**

  `/robots.txt` üretim politikasını ve `/wp-sitemap.xml` erişimini doğrula. Sayfa kaynaklarında site-geneli `noindex` olmadığını kontrol et.

- [ ] **Adım 4: P1 rota turunu çalıştır**

  Haberler, duyurular, dokümanlar, SSS, referanslar, iletişim, KVKK, gizlilik, arama, sayfalama ve seçilmiş eski kritik URL'leri anonim tarayıcıyla kontrol et. Eski URL ya 200 ya da önceden doğrulanmış 301 vermeli.

- [ ] **Adım 5: Responsive turu çalıştır**

  390, 768, 1280 ve 1440 px genişliklerde ana sayfa, yeterlilik listesi/detayı ve bir ortak sayfa ailesini kontrol et. Yatay taşma, üst üste binme veya kullanılamayan menü olmamalı.

- [ ] **Adım 6: İlk 30 dakika gözlemi**

  404/5xx, PHP hata kaydı, SMTP başarısızlıkları ve form tekrarlarını izle. P0 geri dönüş ölçütü görülürse bakım moduna dön ve Görev 7'yi uygula.

- [ ] **Adım 7: İlk 24 saat ve 14 gün takibini başlat**

  İlk 24 saat düzenli; sonraki 14 gün günlük kritik rota, form/SMTP, medya, katalog, sitemap ve 404/5xx kontrolü yap. Eski dosya/DB ve geçici paketi bu sürede silme.

### Task 7: Başarısızlık halinde geri dönüş

**Arayüzler:**
- Tüketir: Görev 3'teki import öncesi son canlı yedeği ve hata kaydı.
- Üretir: Eski canlı sitenin dosya+DB olarak aynı zaman noktasına geri dönmüş durumu.

- [ ] **Adım 1: Geri dönüş eşiğini değerlendir**

  Yaygın 5xx, yönetim erişimsizliği, yarım import, veri kaybı, form teslim edememe, yaygın temiz-permalink 404'ü veya kişisel veri sızıntısından biri varsa geri dön.

- [ ] **Adım 2: Bakım modunu koru**

  Ziyaretçiye bakım ekranı göster; hatalı yeni siteyi açık bırakma.

- [ ] **Adım 3: Dosya ve DB'yi birlikte geri yükle**

  DirectAdmin'deki import öncesi aynı zaman noktalı yedeği kullan. Yalnız dosya veya yalnız DB geri yükleme yapma; eski `wp-config.php`, çekirdek, eklentiler ve DB birlikte dönmeli.

- [ ] **Adım 4: Eski site smoke testi**

  Ana sayfa, yönetim, kritik içerik, eski form ve mail teslimini doğrula. PASS olmadan bakım modunu kaldırma.

- [ ] **Adım 5: Hata paketini izole et**

  Başarısız `.wpress` paketini web kökünde bırakma. Hata özetini kişisel veri ve gizli bilgi olmadan kaydet; yeni bir import girişimi için ayrı kök neden analizi ve kullanıcı onayı iste.

### Task 8: Yayın kaydı, GitHub ve geçici araç temizliği

**Arayüzler:**
- Tüketir: Görev 6 P0/P1 sonuçları ve gözlem kaydı.
- Üretir: Gizli bilgi içermeyen yayın kaydı; isteğe bağlı tag/release.

- [ ] **Adım 1: Yayın sonucunu belgeye işle**

  Tarih, ana URL, sürümler, P0/P1 sonuçları, bilinen açık riskler ve geri dönüş noktasını yaz. Parola, sunucu yolu, IP, e-posta gövdesi veya kişisel veri yazma.

- [ ] **Adım 2: Git değişikliklerini dar kapsamla doğrula**

  `git status --short` çalıştır. Yalnız bu planın tasarım/plan/yayın kayıt dosyalarını stage et; kullanıcıya ait untracked `CLAUDE_*`, `docs/`, ZIP, `zzz/` ve korunan dosyaları stage etme.

- [ ] **Adım 3: Belge commit'i ve push**

  Test/operasyon kaydı temizse `docs: record production cutover` commit'i oluştur ve kullanıcı tarafından önceden yetkilendirilmiş `codex/live-cutover-plan` dalını push et. PR açılması/merge edilmesi ayrıca doğrulanır.

- [ ] **Adım 4: Tag/Release için ayrı onay al**

  Tag veya GitHub Release oluşturma; tema/eklenti ZIP'i yükleme için ayrıca açık onay al. `.wpress`, SQL veya tam site yedeğini release'e ekleme.

- [ ] **Adım 5: Geçici migration aracını güvenli kapat**

  Canlı kabul tamamlanınca All-in-One WP Migration'ı devre dışı bırak/kaldır ve sunucudaki geçici `.wpress` kopyasını yalnız indirilmiş özel kopya+hash doğrulandıktan sonra sil. Bu adım eski site yedeğini silmez.

### Task 9: 14 gün sonra eski siteyi hizmetten çıkarma

**Arayüzler:**
- Tüketir: 14 günlük PASS gözlem kaydı, iki eski-site yedeği ve yeni canlı sitenin doğrulanmış tam yedeği.
- Üretir: Eski web uygulaması kalıntıları kaldırılmış; mail ve yeni canlı site etkilenmemiş hosting hesabı.

- [ ] **Adım 1: Süre ve sağlık kapısını doğrula**

  Kamuya açılıştan en az 14 tam gün geçmiş olmalı. Kritik form/SMTP, 404/5xx, medya, katalog, sitemap veya yönetim sorunu açık olmamalı.

- [ ] **Adım 2: Yeni canlı yedeği ve geri yükleme kanıtı oluştur**

  Güncel canlı sitenin tam dosya+DB yedeğini al; ayrı kopyasını doğrula ve geri yüklenebilirliğini test et. Bu yedek GitHub'a girmez.

- [ ] **Adım 3: Silinecek hedefleri salt okunur envanterle**

  Yalnız eski siteye ait dosya dizini, eski veritabanı, yalnız o DB'ye ait kullanıcı ve geçici migration paketlerini açıkça listele. Ana canlı dizin, yeni DB, uploads, mail dizinleri ve DNS/mail ayarları listede bulunmamalı.

- [ ] **Adım 4: Ayrı ve kesin silme onayı al**

  Kullanıcıya hedef listesini ve geri dönüş kopyalarını göster. "Listelenen eski site hedeflerini kalıcı olarak silmeyi onaylıyorum" şeklinde açık onay gelmeden hiçbir şey silme.

- [ ] **Adım 5: Eski hedefleri kontrollü kaldır**

  File Manager/phpMyAdmin üzerinden yalnız onaylı hedefleri kaldır. E-posta kutuları, mail arşivi, DNS, MX/SPF/DKIM/DMARC, SSL ve yeni canlı site dosya/DB'sine dokunma.

- [ ] **Adım 6: Son sağlık kontrolü**

  Ana sayfa, yönetim, kritik içerik, bir form, SMTP teslimi, sitemap ve medya dosyasını yeniden doğrula. Silinen hedefleri ve saklanan yedekleri gizli bilgi olmadan kaydet.

## Plan öz-denetimi

- Tasarımdaki ön hazırlık, export, import, P0/P1 kabul, geri dönüş, veri koruma ve ayrı onay kapıları görevlere bağlandı.
- Paket limiti/timeout, yönetici değişimi, serileştirilmiş URL, form tekrarı ve temiz permalink risklerinin her biri ölçülebilir bir kontrolle karşılandı.
- Eski sitenin silinmesi cutover'dan ayrıldı; en az 14 gün, yeni canlı yedeği, salt okunur hedef envanteri ve ayrı silme onayı zorunlu yapıldı.
- DNS/mail kapsam dışı tutuldu; GitHub artefakt sınırı açıkça korundu.
