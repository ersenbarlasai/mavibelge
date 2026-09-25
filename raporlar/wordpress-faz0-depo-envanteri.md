# WordPress Faz 0 — Depo ve Statik Site Envanteri

> Tarih: 10 Eylül 2026
>
> Yöntem: Yalnız salt okunur yerel denetim. Hiçbir dosya değiştirilmedi, canlı sisteme bağlanılmadı.
>
> Kural: Bu belgedeki sayılar önceki raporlardan kopyalanmadı; her sayım yerel dosyalar üzerinde yeniden hesaplandı. Önceki bir raporla çelişki varsa her iki değer de gösterildi ve **gerçek dosya esas alındı**.
>
> Yetkili kaynak sırası ve çalışma kuralları: [`../AGENTS.md`](../AGENTS.md). Güncel mimari karar: [`wordpress-ana-uygulama-plani.md`](./wordpress-ana-uygulama-plani.md).

---

## 1. Depo Kök Yapısı

```text
E:\PROJELER\MaviBelge\
├── .gitignore
├── README.md
├── AGENTS.md                                   (bu Faz 0 görevinde oluşturuldu)
├── CLAUDE_HTML_TANITIM_SITE_PROMPTU.md
├── CLAUDE_SON_GORSEL_DUZELTMELER_PROMPTU.md
├── CLAUDE_STITCH_GORSEL_UYUM_DUZELTME_PROMPTU.md
├── CLAUDE_LARAVEL_FAZ0_YONETISIM_VE_HAFIZA_PROMPTU.md     (takip edilmiyor — kullanıcı dosyası)
├── CLAUDE_WORDPRESS_FAZ0_ENVANTER_UYUMLULUK_VE_YONETISIM_PROMPTU.md  (takip edilmiyor — kullanıcı dosyası)
├── STITCH_ANASAYFA_5_VARYASYON_PROMPTU.md
├── STITCH_ANASAYFA_ZORUNLU_DUZELTME_PROMPTLARI.md
├── STITCH_PROMPT_KATALOGU.md
├── header-logo.png / myk_logo.png / turkak_logo.png       (orijinal kaynak logolar)
├── raporlar/
└── tanitim-site/
└── tmp/                                        (.gitignore ile hariç tutulmuş)
```

### 1.1. `raporlar/` içeriği (Faz 0 öncesi)

| Dosya | Satır | Rol |
|---|---:|---|
| `wordpress-ana-uygulama-plani.md` | 275 | **Güncel ve bağlayıcı mimari karar** |
| `final-rapor.md` | 1052 | Tarihsel inceleme ve içerik kaynağı (Laravel dönemi) |
| `incelemeraporu-gpt.md` | — | Kaynak inceleme |
| `incelemeraporu-claude.md` | — | Kaynak inceleme |
| `agent-mimarisi.md` | 32 | Laravel dönemi agent kaydı (tarihsel) |
| `seo-aio-agent-gorev-karti.md` | 206 | Laravel dönemi SEO görev kartı (içerik değeri yüksek) |
| `seo-icerik-modeli-taslagi.md` | 156 | Laravel dönemi SEO veri modeli (içerik değeri yüksek) |

`tmp/` klasörü `.gitignore` tarafından hariç tutulmuştur ve bu görevde okunmuş ama değiştirilmemiştir. İçinde `deploy-yeni-mavibelge-v1/`, `pdfs/`, `static_server.mjs`, `test_deploy_bundle.py` ve bir dağıtım ZIP kopyası bulunmaktadır.

---

## 2. HTML Sayfa Envanteri

| Ölçüm | Yeniden hesaplanan değer | Yöntem |
|---|---:|---|
| `tanitim-site/` kökündeki HTML sayfası | **41** | `find . -maxdepth 1 -name "*.html" \| wc -l` |
| Stitch tasarım çıktısı `code.html` dosyası | **69** | `find . -name "*.html" \| wc -l` (110) eksi 41 |
| Depoda takip edilen `tanitim-site` dosyası | **285** | `git ls-files tanitim-site \| wc -l` |
| Stitch klasörü hariç takip edilen dosya | **110** | aynı komut + `grep -v stitch_ekranlar` |

`README.md` "41 HTML sayfası" diyor. **Doğrulandı — çelişki yok.**

Stitch `code.html` dosyaları üretim sayfası değildir; `assets/stitch_ekranlar/` altındaki tasarım referans çıktılarıdır ve sayfa sayımına dahil edilmemiştir.

### 2.1. Tam sayfa listesi (41)

| # | Dosya | Rol |
|---:|---|---|
| 1 | `index.html` | Ana sayfa |
| 2 | `meslekler.html` | Meslek arama/filtre |
| 3 | `sektor.html` | Sektör detay şablonu (`?slug=` ile dinamik) |
| 4 | `yeterlilik.html` | Yeterlilik detay şablonu (`?slug=` ile dinamik) |
| 5 | `sinav-ve-basvuru.html` | Bölüm giriş sayfası |
| 6 | `nasil-basvururum.html` | Başvuru rehberi |
| 7 | `online-basvuru.html` | Çok adımlı demo form |
| 8 | `sinav-takvimi.html` | Dış sistem yönlendirme sayfası |
| 9 | `sinav-ucretleri.html` | Ücret tablosu |
| 10 | `sinav-surecleri.html` | Süreç anlatımı |
| 11 | `sinav-talepleri.html` | Demo form |
| 12 | `sonuc-belge-sorgulama.html` | Dış sistem yönlendirme sayfası |
| 13 | `belge-yenileme.html` | Bilgi sayfası |
| 14 | `banka-hesap-bilgileri.html` | Bilgi sayfası |
| 15 | `itiraz-sikayet.html` | Demo form |
| 16 | `kurumsal.html` | Bölüm giriş sayfası |
| 17 | `hakkimizda.html` | Kurumsal |
| 18 | `misyon-vizyon.html` | Kurumsal |
| 19 | `kalite-politikamiz.html` | Kurumsal |
| 20 | `tarafsizlik-beyani.html` | Kurumsal |
| 21 | `yasal-dayanagimiz.html` | Kurumsal |
| 22 | `yetki-akreditasyon.html` | Kurumsal |
| 23 | `sosyal-sorumluluk.html` | Kurumsal |
| 24 | `referanslar.html` | Referans grid/slider |
| 25 | `bilgi-merkezi.html` | Bölüm giriş sayfası |
| 26 | `haberler.html` | Haber listesi |
| 27 | `duyurular.html` | Duyuru listesi |
| 28 | `haber-detay.html` | Haber detay şablonu |
| 29 | `dokumanlar.html` | Doküman merkezi |
| 30 | `mevzuat.html` | Mevzuat |
| 31 | `sss.html` | Sık sorulan sorular |
| 32 | `myk.html` | Bilgilendirme |
| 33 | `turkak.html` | Bilgilendirme |
| 34 | `ulusal-meslek-standartlari.html` | Bilgilendirme |
| 35 | `ulusal-yeterlilikler.html` | Bilgilendirme |
| 36 | `iletisim.html` | Demo form + lokasyon |
| 37 | `kariyer.html` | İK giriş |
| 38 | `is-basvurusu.html` | Demo form (CV yükleme) |
| 39 | `kvkk.html` | Hukuki metin |
| 40 | `gizlilik-politikasi.html` | Hukuki metin |
| 41 | `404.html` | Hata durumu |

---

## 3. CSS, JavaScript ve Veri Kaynakları

### 3.1. CSS (6 dosya, 1116 satır)

| Dosya | Satır | İçerik |
|---|---:|---|
| `assets/css/tokens.css` | 60 | **Tasarım token'ları** — renk, tipografi, boşluk, radius, gölge, container |
| `assets/css/base.css` | 91 | Reset ve temel tipografi |
| `assets/css/components.css` | 739 | Bileşen kütüphanesi (en büyük dosya) |
| `assets/css/layout.css` | 72 | Yerleşim |
| `assets/css/pages.css` | 40 | Sayfa özel kuralları |
| `assets/css/responsive.css` | 114 | Kırılma noktaları |

Yükleme sırası 41 sayfanın tamamında aynıdır: `tokens → base → components → layout → pages → responsive`.

### 3.2. JavaScript (8 dosya, 873 satır)

| Dosya | Satır | İşlev |
|---|---:|---|
| `assets/js/filters.js` | 350 | Meslek/sektör filtreleme |
| `assets/js/reference-slider.js` | 175 | Referans logo slider'ı |
| `assets/js/forms-demo.js` | 113 | Demo form gönderimi (veri göndermez) |
| `assets/js/search.js` | 85 | Yeterlilik arama |
| `assets/js/counter.js` | 70 | Ana sayfa etki sayaçları |
| `assets/js/navigation.js` | 49 | Menü/mobil menü |
| `assets/js/components.js` | 26 | Ortak bileşen davranışı |
| `assets/js/main.js` | 5 | Giriş noktası |

Dış JavaScript kütüphanesi (jQuery, Bootstrap vb.) **yoktur**. Tamamı vanilla JS'tir.

### 3.3. Veri dosyaları — yeniden sayılan kayıt sayıları

| Dosya | Global | Kayıt | Sayım yöntemi |
|---|---|---:|---|
| `assets/data/qualifications.js` | `window.MB_QUALIFICATIONS` | **83** | `grep -c '^\s*{ code:'` |
| `assets/data/sectors.js` | `window.MB_SECTORS` | **14** | `grep -c '^\s*{ slug:'` |
| `assets/data/fees.js` | `window.MB_FEES` | **103** | `grep -c 'pricingType:'` |
| `assets/data/references.js` | `window.MB_REFERENCES` | **12** | `grep -c '^\s*{ name:'` |
| `assets/data/news.js` | `window.MB_NEWS` | **6** | `grep -c '^\s*slug:'` |

#### Yeterlilik kod benzersizliği

83 kaydın **83'ü de benzersiz** MYK koduna sahiptir. Mükerrer kod yoktur (`sort -u` ile doğrulandı). `YETERLILIK_ESLESTIRME_RAPORU.md` içinde tarif edilen Metal sektörü mükerrer kayıt düzeltmesi veride uygulanmış durumdadır.

#### Sektör bazında yeterlilik dağılımı (yeniden sayıldı)

| Sektör slug | Kayıt |
|---|---:|
| `makine` | 13 |
| `insaat` | 12 |
| `metalurji` | 7 |
| `guzellik-sac-bakim` | 7 |
| `tekstil` | 6 |
| `lojistik` | 6 |
| `is-makineleri` | 6 |
| `metal` | 5 |
| `enerji` | 5 |
| `maden` | 4 |
| `plastik` | 3 |
| `mobilya` | 3 |
| `mermer` | 3 |
| `cam` | 3 |
| **Toplam** | **83** |

#### Ücret verisi yapısı

| `pricingType` | Kayıt |
|---|---:|
| `single` | 87 |
| `unit` | 13 |
| `multiple` | 2 |
| `package` | 1 |
| **Toplam** | **103** |

103 ücret kaydının **19'unda `qualificationCode` boştur** — kaynak PDF'lerde MYK kodu yazmadığı için uydurulmamıştır. Bu, WordPress'e aktarımda ücret↔yeterlilik ilişkisinin 19 kayıt için manuel eşleştirme gerektireceği anlamına gelir.

#### Haber verisi

6 kaydın 3'ü `type: "haber"`, 3'ü `type: "duyuru"`. Haber/duyuru ayrımı statik veride **zaten yapılmıştır** (`final-rapor.md` §7.3 gereksinimi karşılanmış).

---

## 4. Önceki Raporlarla Çelişkiler

| Konu | Önceki kayıt | Yerel dosyada doğrulanan | Esas alınan |
|---|---|---|---|
| **Sektör sayısı** | `README.md`: "12 sektör"; `final-rapor.md` §6: 12 sektör | `assets/data/sectors.js`: **14 sektör** | **14 (gerçek dosya)** |
| HTML sayfa sayısı | `README.md`: 41 | 41 | Uyumlu |
| Yeterlilik sayısı | `README.md` / `final-rapor.md`: 83 | 83 | Uyumlu |
| Referans logosu | `README.md` / `IMAGE_SOURCES.md`: 12 temsili | 12 | Uyumlu |

### 4.1. Sektör farkının açıklaması

`assets/data/sectors.js` ve `assets/data/qualifications.js` dosya başı yorumları 2026-08-28 tarihli bir yeniden sınıflandırma kaydeder:

- `Elektrik` → **`Enerji`** olarak yeniden adlandırıldı.
- `Ulaştırma & Lojistik` → **`Lojistik`** ve **`İş Makineleri`** olarak ikiye ayrıldı.
- `Maden & Mermer` → **`Maden`** ve **`Mermer`** olarak ikiye ayrıldı.

Sonuç: 12 → 14 sektör. Yorum satırı açıkça "Toplam 83 kayıt korunmuştur, kayıt eklenmemiş/silinmemiştir" der ve sektör bazı dağılım toplamı bunu doğrular (83).

**Kritik uyum notu:** `raporlar/wordpress-ana-uygulama-plani.md` ve `final-rapor.md` §18.1 hâlâ "Ulaştırma & Lojistik" ve "Maden & Mermer" adlarını **kesin** olarak kaydeder. `final-rapor.md` §5.4 zaten bu adların kurum tarafından tekilleştirilmesini istemektedir. Statik site 14 sektörle kullanıcı görsel kabulünden geçmiş olduğundan, WordPress taksonomisi kurulmadan önce **hangi listenin geçerli olduğu kurum tarafından yazılı olarak onaylanmalıdır**. Bu belge sayı uydurmaz; yalnız çelişkiyi kaydeder.

`README.md`'deki "12 sektör" ifadesi bu Faz 0 görevinde **değiştirilmemiştir** (görev kapsamı README'de yalnız yeni belge bağlantısı eklemeye izin verir). Düzeltme, sektör adlandırması kurumca onaylandıktan sonra ayrı bir iş paketinde yapılmalıdır.

---

## 5. Ortak Header/Footer Yapısı

| Ölçüm | Sonuç |
|---|---|
| `site-header` içeren sayfa | **41 / 41** |
| `<footer>` içeren sayfa | **41 / 41** |
| Sunucu tarafı veya JS ile include kullanan sayfa | **0** |

Header ve footer 41 sayfanın **her birine düz metin olarak kopyalanmıştır**. Hiçbir şablon/include mekanizması yoktur.

### 5.1. Header katmanları (kaynak: `index.html`)

1. `.skip-link` — "İçeriğe atla" (erişilebilirlik; 41 sayfada da var).
2. `.trust-bar` — telefon, e-posta, MYK yetki ifadesi.
3. `header.site-header` — marka logosu, 5 ana menü öğesi (4'ü açılır alt menülü), `.trust-group` MYK/TÜRKAK logoları, mobil CTA.

Ana menü yapısı: **Meslekler ve Belgeler** (3 alt), **Sınav ve Başvuru** (9 alt), **Kurumsal** (8 alt), **Bilgi Merkezi** (9 alt), **İletişim**.

### 5.2. WordPress karşılığı

Bu yapı WordPress'te doğrudan `header.php` + `footer.php` + kayıtlı `nav_menu` konumuna karşılık gelir. 41 kopyanın tekilleştirilmesi tema dönüşümünün ilk ve en yüksek kazançlı adımıdır.

---

## 6. Görsel, PDF ve Font Envanteri

### 6.1. Görseller (44 dosya: 24 PNG + 20 SVG)

| Klasör | Dosya | Tür | Durum |
|---|---:|---|---|
| `assets/images/logos/` | 4 | PNG | `header-logo.png`, `myk_logo.png`, `turkak_logo.png` **orijinal**; `favicon.png` |
| `assets/images/content/` | 19 | PNG | `real-*.png` — mavibelge.com.tr'den yerel indirilmiş **gerçek** fotoğraflar |
| `assets/images/hero/` | 3 | PNG+SVG | `hero-home.png`, `hero-home.svg`, `hero-generic.svg` (nötr) |
| `assets/images/references/` | 12 | SVG | **Temsili** — gerçek müşteri logosu değil |
| `assets/images/news/` | 6 | SVG | Nötr kategori görselleri |
| `assets/images/sectors/` | 0 | — | Boş klasör |

### 6.2. Orijinal / temsili ayrımı

**Orijinal ve korunması zorunlu (3 dosya):** `header-logo.png`, `myk_logo.png`, `turkak_logo.png`. Kırpılmamış, renklendirilmemiş, üzerine yazı bindirilmemiştir.

**Gerçek fotoğraf (19 dosya):** `assets/images/content/real-*.png`. Kaynağı `IMAGE_SOURCES.md` içinde WordPress `wp-content/uploads` yollarıyla tek tek belgelenmiştir. Hotlink yoktur. Yapay/AI üretimi değildir.

**Temsili ve gerçek sanılmaması gereken (12 dosya):** `assets/images/references/*.svg`. Kurum onaylı gerçek referans logoları geldiğinde `assets/data/references.js` ile birlikte değiştirilmelidir.

**Gerçek fotoğrafı bulunmayan sektörler — üç kaynak birbirini tutmuyor:**

| Kaynak | Görselsiz sektör iddiası |
|---|---|
| `IMAGE_SOURCES.md` | 3: Plastik, Mobilya, Güzellik ve Saç Bakım |
| `sectors.js` dosya başı yorumu | 5: Plastik, Mobilya, Enerji, İş Makineleri, Güzellik ve Saç Bakım |
| **`sectors.js` gerçek veri (`image: ""`)** | **4: `plastik`, `mobilya`, `is-makineleri`, `guzellik-sac-bakim`** |

Esas alınan: **gerçek veri, 4 sektör.** `Enerji` sektörüne fiilen `real-elektrik-1.png` atanmıştır; `sectors.js` yorumu bu noktada kendi verisiyle çelişir. `IMAGE_SOURCES.md` ise 2026-08-28 sektör bölünmesinden önce yazıldığı için `is-makineleri` kaydını içermez.

**Ek bulgu — paylaşılan görsel:** `maden` ve `mermer` sektörlerinin **ikisi de** `real-mermer.png` kullanır. İki ayrı sektör aynı görselle temsil edilmektedir. Aktarımdan önce içerik agentı kararı gerekir.

Hem `IMAGE_SOURCES.md` hem `sectors.js` yorum bloğu bu yönleriyle **güncel değildir**; ikisi de içerik agentı tarafından düzeltilmelidir. Bu Faz 0 görevinde ikisi de değiştirilmemiştir (`tanitim-site/**` dondurulmuştur).

### 6.3. PDF (2 dosya)

`tanitim-site/ucret/` altında:

- `2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf`
- `2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf`

Bu iki PDF, `assets/data/fees.js` içindeki 103 ücret kaydının **birincil kaynağıdır** (`fees.js` dosya başı yorumu her kaydın kaynak dosyasını ve sayfa numarasını `source`/`sourcePage` alanlarında taşır). Dosya adlarındaki boşluk ve `(1) (1)` eki WordPress medya kütüphanesine aktarımda normalize edilmelidir.

### 6.4. Font

**Yerel font dosyası yoktur.** `tokens.css` `--font-sans: "Inter", "Segoe UI", "Helvetica Neue", Arial, sans-serif` tanımlar ancak hiçbir HTML sayfası Google Fonts veya başka bir font kaynağına `<link>` vermez. Yani site fiilen **sistem fontlarıyla** render edilmektedir; "Inter" yalnız yüklü sistemlerde uygulanır.

WordPress temasında karar gerekir: (a) mevcut davranışı koru (sıfır dış istek, en hızlı), veya (b) Inter'i yerel `woff2` olarak temaya göm. Dış Google Fonts isteği KVKK/gizlilik açısından ayrıca değerlendirilmelidir.

---

## 7. Form, Buton ve Dış Bağlantı Envanteri (demo durumu)

### 7.1. Formlar (7 adet, 5'i demo)

| Sayfa | Form | Durum |
|---|---|---|
| `online-basvuru.html` | `<form data-demo-form novalidate>` | Demo |
| `sinav-talepleri.html` | `<form data-demo-form novalidate>` | Demo |
| `itiraz-sikayet.html` | `<form data-demo-form novalidate>` | Demo |
| `iletisim.html` | `<form data-demo-form novalidate>` | Demo |
| `is-basvurusu.html` | `<form data-demo-form novalidate>` | Demo |
| `index.html` | `<form class="search-form" role="search">` | İstemci içi arama |
| `404.html` | `<form class="search-form" role="search">` | İstemci içi arama |

**Hiçbir formda `action` özniteliği yoktur.** 5 demo formu `assets/js/forms-demo.js` tarafından yakalanır ve tanıtım mesajı gösterir; hiçbir veri hiçbir yere gönderilmez. Bu, statik prototipin dışarı veri sızdırmadığının **kanıtıdır**.

### 7.2. Ölü/yer tutucu bağlantılar (`href="#"` — 7 adet)

| Sayfa | Adet | Not |
|---|---:|---|
| `dokumanlar.html` | 4 | "İndir" eylemleri — gerçek PDF bağlanmamış |
| `sinav-takvimi.html` | 1 | **Dış sınav takvimi sistemi bağlantısı eksik** |
| `sonuc-belge-sorgulama.html` | 1 | **MYK sorgu bağlantısı eksik** |
| `yeterlilik.html` | 1 | Detay eylemi |

**Önemli bulgu:** `final-rapor.md` §11.5 ve `wordpress-ana-uygulama-plani.md`, sınav takvimi ve sonuç/belge sorgulamanın "mevcut dış sisteme açıklamalı bağlantı" vermesini karara bağlamıştır. Statik sitede bu iki bağlantı **henüz gerçek adres içermez** (`href="#"`). Gerçek dış sistem URL'leri kurumdan alınmadan WordPress sayfaları tamamlanamaz. Bu belge adres uydurmamıştır.

### 7.3. Dış bağlantılar (yalnız 4 farklı hedef host)

| Hedef | Görülme | Not |
|---|---:|---|
| `https://www.google.com/maps/search/?api=1&query=1176%20Sokak%20No%3A28%20Ostim%20Ankara` | 42 | Footer harita bağlantısı |
| `http://www.instagram.com/mavi_belge` | 42 | **`http://` — HTTPS'e çevrilmeli** |
| `http://www.facebook.com/mavibelge31` | 42 | **`http://` — HTTPS'e çevrilmeli** |
| `http://twitter.com/mavibelge31` | 42 | **`http://` ve eski marka adı — gözden geçirilmeli** |

42 sayı 41 sayfanın üzerindedir çünkü bir sayfada aynı hedef iki kez geçmektedir. Üç sosyal medya bağlantısının tamamı **şifresiz `http://`** şemasıyla yazılmıştır; WordPress temasına taşınırken düzeltilmelidir.

`mavibelge.com.tr`, `myk.gov.tr` veya `turkak.gov.tr` adreslerine giden hiçbir dış bağlantı statik sitede **yoktur**.

---

## 8. SEO Teknik Durumu (statik site)

| Öğe | Durum | Kanıt |
|---|---|---|
| `<title>` | **41 sayfada 41 benzersiz başlık** | `grep -h -oE '<title>[^<]*</title>' *.html \| sort -u \| wc -l` = 41 |
| `<meta name="description">` | **41 / 41 sayfada var** | `grep -l 'name="description"' *.html \| wc -l` = 41 |
| `<html lang>` | **41 / 41 sayfada `tr`** | Tek değer, tutarlı |
| `rel="canonical"` | **0 sayfada** | Yok |
| Open Graph / Twitter Card | **0 sayfada** | Yok |
| JSON-LD (`application/ld+json`) | **0 sayfada** | Yok |
| `<meta name="robots">` | **0 sayfada** | Yok |
| `robots.txt` | **Yok** | Dosya mevcut değil |
| `sitemap.xml` | **Yok** | Dosya mevcut değil |
| `.htaccess` | **Yok** | Dosya mevcut değil |

**Değerlendirme:** Statik site, mevcut canlı WordPress sitesinin en büyük iki SEO sorununu (145 sayfada aynı title, meta description yokluğu — `final-rapor.md` §7.1) **zaten çözmüştür**: 41 benzersiz title ve 41 meta description mevcuttur. Bu metinler WordPress'e doğrudan aktarılabilir başlangıç değerleridir.

Buna karşılık canonical, Open Graph, JSON-LD, robots ve sitemap **hiç uygulanmamıştır**. Bunlar statik prototipte beklenmez; WordPress'te SEO/AIO agentının birinci iş paketidir. Yönlendirme kuralı (`.htaccess`) da yerelde bulunmadığından, eski URL yönlendirmeleri sıfırdan üretilecektir.

---

## 9. Eski URL Yönlendirme Envanteri İçin Yerel Kaynaklar

Depo genelinde `mavibelge.com.tr` altındaki **57 benzersiz eski URL** metin olarak kayıtlıdır.

Sayım: `grep -rohE 'https?://(www\.)?mavibelge\.com\.tr/[a-zA-Z0-9/_.%-]*' . | sed 's|https://www\.|https://|' | sort -u | wc -l` = **57**

Bu URL'leri içeren yerel dosyalar:

- `raporlar/final-rapor.md` (§23 doğrulama kaynakları ve gövde metni)
- `raporlar/incelemeraporu-gpt.md`, `raporlar/incelemeraporu-claude.md`
- `tanitim-site/assets/stitch_ekranlar/extracted_text_from_https_mavibelge.com.tr.md`
- `tanitim-site/IMAGE_SOURCES.md` (medya `wp-content/uploads` yolları)
- `tanitim-site/assets/data/qualifications.js`, `news.js` (kaynak yorumları)
- Çeşitli `CLAUDE_*.md` / `STITCH_*.md` prompt kayıtları

### 9.1. Sonuç: yerel kaynaklar yeterli değil

| Kaynak | Kapsam |
|---|---:|
| Yerelde kayıtlı benzersiz eski URL | **57** |
| `final-rapor.md` §4.1 başlangıç kapsamı | **145** |
| Kesin/nihai envanter | **Doğrulanamadı** |

Yerel depo, `final-rapor.md`'nin başlangıç kapsamı saydığı 145 URL'nin yalnız bir alt kümesini içerir ve nihai envanter zaten 145'ten büyüktür (`seo-icerik-modeli-taslagi.md` §4.1). Yönlendirme planı **yalnız yerel dosyalarla üretilemez**. Gerekli ek kaynaklar — hiçbiri bu depoda yok:

1. `https://mavibelge.com.tr/wp-sitemap.xml` ve alt sitemap dosyaları (canlı çekim)
2. Google Search Console indekslenmiş/taranmış URL dışa aktarımı
3. Sunucu erişim kayıtları (`access_log`)
4. Analitik verisi (gerçek ziyaret almış eski URL'ler)
5. Backlink kaynakları
6. Mevcut sitede tanımlı yönlendirmeler (`.htaccess` veya eklenti)
7. Kategori/etiket/medya arşiv adresleri

Bu kaynaklar, [`wordpress-sunucu-bilgi-talebi.md`](./wordpress-sunucu-bilgi-talebi.md) ile talep edilecek bilgilerin bir parçasıdır.

---

## 10. WordPress'e Doğrudan Yeniden Kullanılabilir Varlıklar

Aşağıdaki tablo, statik siteden WordPress temasına/eklentisine **yeniden yazılmadan** taşınabilecek varlıkları listeler. Sahiplik sütunu [`wordpress-agent-mimarisi.md`](./wordpress-agent-mimarisi.md) ile uyumludur.

| Varlık | Hedef | Yeniden kullanım | Sahip agent |
|---|---|---|---|
| `assets/css/tokens.css` (60 satır) | Tema | **Doğrudan** — tasarım token'ları olduğu gibi taşınır | Tema agentı |
| `assets/css/base.css`, `components.css`, `layout.css`, `pages.css`, `responsive.css` | Tema | **Yüksek** — WordPress gövde/blok sınıfları için ek kurallar gerekir | Tema agentı |
| `assets/js/*.js` (8 dosya) | Tema | **Yüksek** — `wp_enqueue_script` ile kaydedilir; veri kaynağı `window.MB_*` yerine REST/`wp_localize_script` olur | Tema agentı |
| `assets/images/logos/*` (3 orijinal + favicon) | Tema | **Doğrudan** | Tema agentı |
| `assets/images/content/real-*.png` (19) | Medya kütüphanesi | **Doğrudan** — WebP dönüşümü ve `srcset` eklenir | Veri/içerik agentı |
| `assets/images/hero/`, `news/` (9) | Medya kütüphanesi | **Doğrudan** | Veri/içerik agentı |
| `assets/images/references/*.svg` (12) | Medya kütüphanesi | **Geçici** — gerçek logolar gelene kadar, "temsili" işaretiyle | Veri/içerik agentı |
| `assets/data/qualifications.js` (83) | `mavibelge-core` CPT | **Doğrudan** — import kaynağı | Veri/içerik agentı |
| `assets/data/sectors.js` (14) | Taksonomi | **Koşullu** — 12/14 adlandırma kurum onayı bekliyor | Veri/içerik agentı |
| `assets/data/fees.js` (103) | `mavibelge-core` ücret modeli | **Doğrudan** — 19 kayıtta MYK kodu manuel eşleştirilecek | Veri/içerik agentı |
| `assets/data/news.js` (6) | `post` | **Doğrudan** — haber/duyuru ayrımı hazır | Veri/içerik agentı |
| `assets/data/references.js` (12) | CPT | **Geçici** — temsili işaretiyle | Veri/içerik agentı |
| 41 sayfanın `<title>` + `<meta description>` | SEO alanları | **Doğrudan** — başlangıç değerleri | SEO/AIO agentı |
| Header/footer/menü yapısı | `header.php`, `footer.php`, `nav_menu` | **Yüksek** — 41 kopya tek şablona indirilir | Tema agentı |
| 5 demo formunun alan yapısı | Form eklentisi | **Yalnız alan listesi** — KVKK onayı olmadan canlı veri toplanmaz | Güvenlik agentı + Tema agentı |
| `ucret/*.pdf` (2) | Medya kütüphanesi | **Doğrudan** — dosya adı normalize edilerek | Veri/içerik agentı |
| `qa-screenshots/measurements.json` | QA temel çizgisi | **Doğrudan** — görsel regresyon karşılaştırması | QA agentı |

### 10.1. Yeniden kullanılamayacaklar

- **`assets/stitch_ekranlar/**` (69 `code.html`)** — Tailwind CDN tabanlı tasarım taslaklarıdır; üretim kodu değildir, temaya taşınmaz.
- **`href="#"` yer tutucuları (7)** — gerçek hedef adres kurumdan alınmadan taşınamaz.
- **`http://` sosyal medya bağlantıları (3)** — HTTPS'e çevrilmeden taşınmamalıdır.
- **`window.MB_*` global veri yükleme deseni** — WordPress'te veritabanı + REST ile değiştirilir; JS dosyaları bu noktada uyarlanacaktır.

---

## 11. Doğrulanamayanlar

| Konu | Neden doğrulanamadı | Doğrulama yöntemi |
|---|---|---|
| 12 mi 14 mü sektör | Yerel kaynaklar birbiriyle çelişiyor | Kurum yazılı onayı |
| Nihai eski URL envanteri | Yerelde yalnız 57 URL var; canlı sitemap/GSC/log yok | Canlı sitemap çekimi + GSC dışa aktarımı + sunucu logu |
| Sınav takvimi ve MYK sorgu gerçek adresleri | Statik sitede `href="#"` | Kurumdan resmî adres |
| Gerçek referans kurumları | Yalnız 12 temsili logo var | Kurum onaylı logo dosyaları |
| 19 ücret kaydının MYK kodu | Kaynak PDF'lerde kod yazmıyor | Kurum/MYK karşılaştırması |
| Mevcut canlı sitenin tema/eklenti envanteri | Depoda yok, canlıya bağlanılmadı | [`wordpress-sunucu-bilgi-talebi.md`](./wordpress-sunucu-bilgi-talebi.md) |
| Üretim PHP/veritabanı/web sunucusu kesin sürümleri | Sunucuya bağlanılmadı | [`wordpress-sunucu-bilgi-talebi.md`](./wordpress-sunucu-bilgi-talebi.md) |

---

## 12. Güvenlik Taraması Sonucu

Salt okunur tarama yapıldı:

| Kontrol | Sonuç |
|---|---|
| `.env` / `.env.*` dosyası | **0 bulundu** |
| `*.pem`, `*.key`, `id_rsa*`, `*.ppk` | **0 bulundu** |
| Metin dosyalarında parola/API anahtarı deseni | **0 bulundu** |

`.gitignore` `.env`, `.env.*`, `vendor/`, `node_modules/`, `tmp/`, `.claude/` ve QA PNG'lerini hariç tutmaktadır. Bu görevde `.gitignore` **değiştirilmemiştir**.

**Gizli bilgi bulunmamıştır.**

---

## 13. Bir Sonraki Adım İçin Bağlantılar

- Üretim uyumluluk kararı: [`wordpress-faz0-uyumluluk-matrisi.md`](./wordpress-faz0-uyumluluk-matrisi.md)
- Sunucu bilgi toplama paketi: [`wordpress-sunucu-bilgi-talebi.md`](./wordpress-sunucu-bilgi-talebi.md)
- WordPress agent yönetişimi: [`wordpress-agent-mimarisi.md`](./wordpress-agent-mimarisi.md)
- Proje durumu ve oturum hafızası: [`proje-durumu.md`](./proje-durumu.md)
