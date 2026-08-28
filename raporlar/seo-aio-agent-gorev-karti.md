# seo-aio-agent — Görev Kartı

> Bu belge, Mavi Belge mimarisinde **“SEO, AIO/GEO ve Yapay Zekâ Ajanı Uyumluluğu”** görev alanının ana kaynağıdır. `seo-aio-agent` adlı agent bu alandan sorumludur.
>
> Bu belge `raporlar/final-rapor.md` ile çelişmez; onun 11, 12, 13, 14, 16.2, 16.3 ve 17. bölümlerini uygulama seviyesinde detaylandırır. Fikir ayrılığı olursa `final-rapor.md` esas alınır ve bu belge güncellenir.
>
> İlişkili belgeler: [`seo-icerik-modeli-taslagi.md`](./seo-icerik-modeli-taslagi.md) (Laravel/Filament SEO veri modeli), [`agent-mimarisi.md`](./agent-mimarisi.md) (agent merkezi kayıt/yönetişim belgesi — tam agent listesi ve durumlar oradadır, burada tekrar edilmez).
>
> Bu belge yalnızca planlama içindir. Laravel kurulumu, migration, paket kurulumu veya tanıtım sitesi değişikliği içermez.

---

## 1. Amaç

Mevcut statik tanıtım sitesinden gelecekteki Laravel + Filament sistemine geçişte; klasik SEO, yapay zekâ tabanlı arama görünürlüğü (AIO/GEO) ve tarayıcı/yapay zekâ ajanlarının siteyi doğru okuyabilmesi konularının **tek sahipli, sürdürülebilir bir görev alanı** olmasını sağlamak. Amaç sıralama garantisi değil; doğru, doğrulanabilir ve makinece okunabilir bir bilgi mimarisi kurmaktır.

## 2. Sorumluluk Alanları

### A. Teknik SEO
Sayfa title/description, canonical, index/noindex ve follow/nofollow politikası, XML sitemap ve sitemap indeksleri, robots.txt, eski WordPress URL → yeni URL 301 planı (bkz. `final-rapor.md` §14.2), 404/soft-404 denetimi, breadcrumb, Open Graph/Twitter meta, görsel alt metni, mobil uyumluluk, Core Web Vitals, sunucu tarafı render edilmiş taranabilir HTML, sayfalama/filtre URL indeksleme politikası.

### B. Yapılandırılmış Veri
Görünür içerikle birebir eşleşen JSON-LD: `Organization`, `WebSite`, `WebPage`, `BreadcrumbList`, `Article`/`NewsArticle`, `PostalAddress`, `ContactPoint`, `ItemList`, uygun ve gerçekten desteklenen durumlarda `FAQPage`. Bir sayfa bu düğümlerden birden fazlasını aynı anda içerebilir; üretim tek bir `schema_type` alanına değil, merkezi bir `SchemaBuilder` servisine ve gerektiğinde `@graph` yapısına dayanır (bkz. `seo-icerik-modeli-taslagi.md` §3.1). Editörlere kontrolsüz serbest JSON alanı sunulmaz; istisnai override sınırlı, doğrulamalı ve denetim izlidir. `final-rapor.md` §16.2 uyarınca yeterlilik sayfalarında `Course` şeması **kullanılmaz** (belgelendirme programı, eğitim kursu değil). `FAQPage` yalnızca sayfada gerçekten görünür soru-cevap içeriği varsa üretilir. Uydurma puan, fiyat, adres, müşteri, sertifika bilgisi yasak. Yapılandırılmış veri arama motoruna/AI sistemine içerik açıklaması sağlar; zengin sonuç veya AI görünürlüğü **garanti etmez**.

### C. AIO/GEO
Hedef platformlar: Google AI Overview, Google AI Mode, ChatGPT Search, Google Gemini, Perplexity, Bing/Copilot. İçerik ilişki zinciri: **Sektör → Meslek/Yeterlilik → MYK Kodu → Seviye → Ücret → Sınav Süreci → Belge Yenileme → Dokümanlar → İlgili Haberler**. Her önemli içerikte hedeflenen unsurlar: kısa doğrudan cevap, açıklayıcı içerik, resmî kaynak bağlantısı, yayın tarihi, son güncelleme tarihi, içerik sorumlusu/kontrol eden kişi, ilgili içerik bağlantıları, kurumsal güven unsurları. Bkz. `final-rapor.md` §16.3 (temel gereksinimler listesi zaten karara bağlı, burada tekrar edilmez).

### D. Yapay Zekâ Ajanı Uyumluluğu
Semantik HTML, doğru başlık hiyerarşisi, açık bağlantı/buton metni, gerçek `<label>` kullanımı, doğru ARIA rol/durum/açıklama, klavye erişilebilirliği, anlaşılır form hata/başarı mesajları, ücret/meslek/yeterlilik verisinin yalnızca görsel/PDF/canvas/JS içinde tutulmaması (HTML'de erişilebilir olmalı), kararlı ve anlamlı URL yapısı, JS çalışmadan da temel içeriğin erişilebilir kalması, ileride kullanılabilecek belgelenmiş salt-okunur veri uçları. Aday kişisel verisi hiçbir şekilde herkese açık API veya indekslenebilir sayfada bulunmaz.

## 3. Yetki Sınırları

- `seo-aio-agent` içerik **üretmez**, mevcut/onaylı içeriği SEO ve erişilebilirlik açısından **yapılandırır**.
- Ücret, MYK kodu, adres, akreditasyon numarası gibi verileri **uydurmaz veya değiştirmez**; yalnızca içerik agentının/kurumun onayladığı veriyi kullanır.
- Başka bir agentın sahip olduğu dosyayı doğrudan değiştiremez; önce görev kartı/entegrasyon notu hazırlar, ortak dosya entegrasyonunu yalnızca ana orkestratör yapar (bkz. §6).
- Sıralama, trafik veya AI cevaplarında görünürlük **garanti edemez**.

## 4. Sahip Olduğu Dosya ve Modüller (gelecekteki Laravel sisteminde)

- `robots.txt`, `sitemap.xml` / sitemap indeksleri üretim mantığı
- SEO meta alanları şeması (title, description, canonical, robots, OG, schema — bkz. içerik modeli taslağı)
- JSON-LD üretim şablonları
- 301/410 yönlendirme kural seti (veri: backend/DB agentı ile birlikte, kural tanımı: seo-aio-agent)
- Crawler erişim politikası (user-agent bazlı kural tanımı; uygulaması DevOps/güvenlik agentı)
- Erişilebilirlik/semantik HTML kontrol listesi (uygulaması arayüz agentı)
- SEO ölçümleme/izleme planı

## 5. Değiştirmemesi Gereken Alanlar

- Mevcut `tanitim-site` HTML/CSS/JS/içerik/ücret/görsel dosyaları (bu görev kapsamında dondurulmuş).
- Veritabanı şeması ve migration dosyaları (Veritabanı agentının sahipliğinde; seo-aio-agent yalnızca alan önerisi sunar).
- Sunucu/hosting/WAF/CDN yapılandırması (DevOps/Güvenlik agentının sahipliğinde; seo-aio-agent yalnızca politika önerir).
- Görsel tasarım, sayfa düzeni, bileşen kodu (Arayüz agentının sahipliğinde).
- Fiyat/MYK kodu/kurumsal bilgi (İçerik agentının sahipliğinde).

## 6. Birlikte Çalışacağı Agentlar

Tam agent listesi, sahiplik alanları ve durumlar için ana kaynak: [`agent-mimarisi.md`](./agent-mimarisi.md). Burada yalnızca `seo-aio-agent` açısından iş bölümü özetlenir; tekrar bir liste tanımlanmaz.

| Agent | Bu agentla iş bölümü |
|---|---|
| Arayüz agentı | Semantik HTML, başlık düzeni, erişilebilirlik, performans uygulaması |
| Backend agentı | SEO servisleri (sitemap, `SchemaBuilder`), yönlendirme motoru, salt-okunur veri uçları |
| Veritabanı agentı | SEO alanları, ilişkiler, indeksler, migration |
| İçerik agentı | Başlık, açıklama, kaynak, içerik kalitesi/doğruluğu; yayın bilgisi (`published_at`, `content_updated_at`, yazar/kontrol eden) sahipliği — bkz. `seo-icerik-modeli-taslagi.md` §1.1 |
| Güvenlik agentı | Crawler erişimi, WAF/bot koruma istisnaları, rate limit, kişisel veri güvenliği |
| DevOps/hosting agentı | Yönlendirmeler, sıkıştırma, önbellek, SSL, sunucu logları |

Kural: bir agent başka bir agentın sahip olduğu dosyayı değiştirmeden önce görev kartı veya entegrasyon notu oluşturur. Ortak dosyaların (ör. `.htaccess`, ana layout, ortak middleware) entegrasyonu yalnızca ana orkestratör tarafından yapılır. Bağımlılık matrisi: bkz. §11.

## 7. Girdi ve Çıktılar

**Girdi:** `final-rapor.md`, onaylı içerik envanteri, MYK/TÜRKAK doğrulanmış veriler, eski WordPress URL başlangıç kapsamı (raporda tespit edilen 145 sayfa/haber URL'si — nihai envanter değil, bkz. `seo-icerik-modeli-taslagi.md` §4.1), hosting/CDN/WAF teknik yetenekleri.

**Çıktı:** SEO içerik modeli taslağı, robots/crawler politikası, 301/410 karar tablosu, JSON-LD şablon planı, SEO kalite kapıları, ölçümleme planı, yayın öncesi/sonrası kontrol listeleri.

## 8. Kabul Kriterleri

- Her önerilen SEO alanı en az bir içerik türüne (sabit sayfa, haber, sektör, meslek, ücret, doküman, şube, sınav süreci, belge yenileme, kurumsal sayfa) atanmış olmalı.
- Her yapılandırılmış veri önerisi görünür içerikle birebir eşleşmeli; uydurma alan içermemeli.
- Crawler politikası Googlebot, Bingbot, OAI-SearchBot, GPTBot, PerplexityBot için ayrı ayrı tanımlanmış olmalı.
- Nihai eski URL envanterindeki (bkz. `seo-icerik-modeli-taslagi.md` §4.1) her URL için karar mekanizması (301/410/korunacak/incelenecek/noindex-teknik) tanımlı olmalı; toplu ana sayfaya yönlendirme önerilmemeli.
- `llms.txt` yardımcı/deneysel olarak konumlandırılmış, zorunlu veya garanti aracı olarak sunulmamış olmalı.

## 9. Test ve Doğrulama Yöntemi

- Yapılandırılmış veri: Google Rich Results Test / Schema.org doğrulayıcı ile şema-içerik eşleşme kontrolü.
- Sitemap/robots: sözdizimi doğrulama + Search Console/Bing Webmaster Tools gönderim testi.
- 301/410: otomatik test seti (`final-rapor.md` §17 Faz 6 ile uyumlu — “301/410 otomatik testleri”).
- Erişilebilirlik: klavye gezinme testi, ekran okuyucu ile temel akış testi, otomatik aXe/Lighthouse taraması.
- Performans: Core Web Vitals (gerçek kullanıcı verisi + Lighthouse).
- Crawler erişimi (bkz. §12 ayrıntılı doğrulama zinciri): `curl -A "<user-agent>"` yalnızca **ilk seviye davranış testi**dir (sayfa bot user-agent'ına 200/403/engelli mi dönüyor) — tek başına gerçek bot doğrulaması **değildir**, çünkü user-agent taklit edilebilir. Gerçek doğrulama için robots.txt kontrolü, WAF/CDN bot politikası incelemesi, sağlayıcının yayımladığı resmî bot IP aralıkları, gerekirse ters DNS doğrulaması, gerçek sunucu erişim/engelleme kayıtları ve Search Console/Bing Webmaster tarama testleri birlikte kullanılır.

## 10. Riskler

- Admin paneli mimari olarak Filament olarak kararlaştırıldı; ancak Filament ana sürümü, Laravel sürümü ve PHP sürümü henüz kilitlenmedi — hosting firmasının desteklediği PHP sürümü doğrulanana kadar bekliyor (`final-rapor.md` §11.2, §18.2 madde 1 ve 3). SEO alanlarının panel UX'i sürüm kilidine bağlı; kesinleşene kadar alan şeması genel tutulmalı.
- Mevcut hosting Laravel/queue/cron gereksinimlerini karşılamayabilir (`final-rapor.md` §18.2 madde 1) — sitemap/redirect zamanlanmış görevleri buna bağlı.
- Nihai eski URL envanteri (145, başlangıç kapsamıdır) henüz tüm kaynaklardan (sitemap, GSC, sunucu logu, analitik, backlink) birleştirilip doldurulmadı; veri geçişi (Faz 5) tamamlanmadan 301 planı kesinleşemez.
- WAF/CDN sağlayıcısı meşru AI botlarını (OAI-SearchBot, GPTBot, PerplexityBot) yanlışlıkla engelleyebilir — hosting/güvenlik agentı ile erken doğrulama gerekir.
- AIO/GEO görünürlüğü ölçülebilir ama garanti edilemez; kurumsal beklenti yönetimi gerekir.
- `Course` şeması gibi yanlış schema türü seçimi yanıltıcı olabilir (final-rapor.md §16.2'de zaten uyarılmış).

## 11. Agentlar Arası Bağımlılık Matrisi

| # | Aşama | Sorumlu agent | Bağımlılık | Beklenen çıktı | Kabul kriteri |
|---|---|---|---|---|---|
| 1 | Mevcut site URL ve içerik envanteri | seo-aio-agent + içerik agentı | `final-rapor.md` §14.2 şablonu, 145 URL'lik başlangıç kapsamı + sitemap/GSC/sunucu logu/analitik/backlink kaynakları (`seo-icerik-modeli-taslagi.md` §4.1) | Birleştirilmiş, doldurulmuş nihai URL karar tablosu | Her satırda eski URL, içerik türü, yeni URL, karar (301/410/korunacak/incelenecek/noindex-teknik), onaylayan, test sonucu dolu |
| 2 | Laravel içerik ve SEO veri modeli | seo-aio-agent + veritabanı agentı | Aşama 1, `final-rapor.md` §12 | SEO alan şeması taslağı (bkz. içerik modeli taslağı) | Alanlar içerik türlerine atanmış, gereksiz tekrar yok |
| 3 | Yönetim paneli SEO alanları | seo-aio-agent + backend agentı | Aşama 2, admin kütüphane seçimi | Panel form alanı önerisi | Editör SEO alanlarını kod bilmeden doldurabilir |
| 4 | Teknik SEO servisleri | backend agentı | Aşama 2-3 | Sitemap üretici, meta render servisi tasarımı | Sitemap geçerli XML, meta alanları render'da kullanılıyor |
| 5 | Yapılandırılmış veri | seo-aio-agent + içerik agentı | Aşama 3-4 | JSON-LD şablonları | Şema görünür içerikle eşleşiyor, doğrulayıcıdan geçiyor |
| 6 | Eski URL yönlendirmeleri | backend agentı + veritabanı agentı | Aşama 1 | `redirects` tablosu + kural motoru | Her 301/410 doğru kod döndürüyor |
| 7 | Crawler ve yapay zekâ bot politikası | seo-aio-agent + güvenlik agentı + DevOps agentı | Hosting/WAF teknik doğrulaması | robots.txt kural seti + WAF istisna listesi | Meşru botlar engellenmiyor, istenmeyenler kısıtlı |
| 8 | Erişilebilirlik ve agent uyumluluğu | arayüz agentı + seo-aio-agent | Aşama 3, tasarım sistemi | Semantik HTML/ARIA kontrol listesi uygulaması | aXe/Lighthouse temel kontrollerden geçiyor |
| 9 | Analitik ve ölçümleme | seo-aio-agent + backend agentı | Analitik/çerez kararı (`final-rapor.md` §16.4 — henüz açık) | Ölçüm planı ve olay listesi | Kararlı araç ve olaylar tanımlı |
| 10 | Yayın öncesi SEO kabul testi | seo-aio-agent | Aşama 1-9 tamamlanmış | SEO kalite kapıları raporu (§13) | Tüm kalite kapıları geçilmiş |
| 11 | Yayın sonrası indeksleme ve görünürlük takibi | seo-aio-agent | Yayın | GSC/Bing indeksleme raporu, crawl hata raporu | İlk 2 haftada kritik crawl hatası yok |

## 12. Crawler Politikası

| Bot | Amaç | Politika önerisi |
|---|---|---|
| Googlebot | Klasik arama indeksleme | Serbest erişim, sitemap gönderimi |
| Bingbot | Klasik arama + Copilot kaynağı | Serbest erişim, sitemap gönderimi |
| OAI-SearchBot | ChatGPT arama sonuçlarında görünürlük | Serbest erişim — arama/görünürlük amaçlı, ayrı değerlendirilir |
| GPTBot | Eğitim/model amaçlı erişim | Kurumun tercihine göre ayrı karar; OAI-SearchBot'tan **bağımsız** ele alınmalı (biri arama görünürlüğü, diğeri eğitim verisi) |
| PerplexityBot | Perplexity arama sonucu | Serbest erişim önerilir, kurum onayına bağlı |

Not: hosting güvenlik duvarı, CDN, WAF ve bot koruma sistemlerinin meşru arama botlarını yanlışlıkla engellememesi gerekir — bu, DevOps/hosting agentı ile ortak doğrulanmalı (bkz. §11 Aşama 7).

### 12.1. Bot Doğrulama Zinciri

`curl -A "<user-agent>"` yalnızca **ilk seviye davranış testidir** — user-agent taklit edilebildiği için tek başına gerçek bot doğrulaması değildir. Tam doğrulama zinciri:

1. `robots.txt` kontrolü (kurallar doğru mu, bot doğru yorumluyor mu).
2. WAF/CDN bot politikası incelemesi (hangi kural hangi botu neden engelliyor).
3. Sağlayıcının yayımladığı resmî bot IP aralıkları ile karşılaştırma (Google, Bing, OpenAI, Perplexity'nin yayımladığı IP listeleri).
4. Gerekli durumlarda ters DNS (reverse DNS) doğrulaması — gelen isteğin gerçekten iddia ettiği bota ait olduğunu teyit etmek için.
5. Gerçek sunucu erişim ve engelleme kayıtlarının incelenmesi.
6. Search Console ve Bing Webmaster Tools üzerinden canlı tarama (fetch/crawl) testleri.
7. OAI-SearchBot ve PerplexityBot için erişim kayıtlarının ayrıca izlenmesi (bu botlar için resmî doğrulama araçları Google/Bing kadar olgun değildir).
8. Sahte user-agent kullanan botlara karşı güvenlik değerlendirmesi (rate limit, davranışsal engelleme) — bu, meşru bot politikasından ayrı ele alınır ve güvenlik agentı ile birlikte yürütülür.

`llms.txt`: yardımcı ve deneysel bir dosya olarak değerlendirilir. Zorunlu veya sıralama/görünürlük garantisi sağlayan bir standart olarak sunulmaz (`final-rapor.md` §16.3 ile uyumlu).

## 13. SEO Kalite Kapıları (Yayına Alma Öncesi)

Bir sayfa yayına alınmadan önce:

- [ ] Benzersiz title
- [ ] Benzersiz meta description
- [ ] Tek ve doğru H1
- [ ] Canonical adres
- [ ] İndeksleme tercihi (index/noindex) bilinçli seçilmiş
- [ ] Geçerli ve anlamlı URL
- [ ] Görsellerde alt metin
- [ ] Kırık iç bağlantı yok
- [ ] Yapılandırılmış veri görünür içerikle eşleşiyor
- [ ] Mobil kullanılabilirlik
- [ ] Temel erişilebilirlik (klavye, ARIA, kontrast)
- [ ] Kaynak ve güncelleme tarihi görünür
- [ ] Kişisel veri içermiyor
- [ ] Uydurma/doğrulanmamış bilgi içermiyor

## 14. Ölçümleme

- Google Search Console, Bing Webmaster Tools
- Yapılandırılmış veri doğrulama (periyodik)
- Sitemap ve indeksleme takibi
- Crawl hataları, 404 ve yönlendirme raporları
- Core Web Vitals
- ChatGPT yönlendirmelerinde `utm_source=chatgpt.com` takibi
- Perplexity ve diğer AI kaynaklı ziyaret takibi
- Sunucu loglarında crawler user-agent takibi
- Site içi arama sorguları ve sonuç bulunamayan meslek aramaları raporu
- İçerik güncellik raporu (son güncelleme tarihi geçmiş içerik listesi)

Analitik aracı seçimi `final-rapor.md` §16.4'te henüz açık karar olarak işaretli; bu ölçümleme planı o karar netleşince araca bağlanacaktır.

## 15. Yayına Geçiş Kontrol Listesi

- [ ] robots.txt üretim ortamına uygun (staging'de farklı, prod'da doğru)
- [ ] sitemap.xml/indeksi Search Console ve Bing Webmaster'a gönderilmiş
- [ ] Nihai eski URL envanterindeki kararların tamamı uygulanmış ve test edilmiş
- [ ] Kritik sayfalarda JSON-LD (`@graph`) doğrulanmış
- [ ] WAF/CDN meşru botları engellemiyor (bkz. §12.1 tam doğrulama zinciri; yalnızca curl testi yeterli sayılmaz)
- [ ] Core Web Vitals hedef eşiklerinde
- [ ] Analitik/ölçüm olayları çalışıyor
- [ ] 404/410 izleme aktif
- [ ] Kişisel veri sızıntısı taraması yapılmış (public sayfa/API)

---

## Uygulama Yol Haritası — Özet

Ayrıntılı aşama tablosu için §11. Bu bölüm `final-rapor.md` §17 Faz 0-6 ile şu şekilde eşleşir:

| Bu belgedeki aşama | final-rapor.md fazı |
|---|---|
| 1-2 | Faz 0 / Faz 2 |
| 3-5 | Faz 2 / Faz 3 |
| 6 | Faz 5 |
| 7-8 | Faz 3 / Faz 6 |
| 9 | Faz 3 |
| 10 | Faz 6 |
| 11 | Faz 6 sonrası (yayın sonrası) |
