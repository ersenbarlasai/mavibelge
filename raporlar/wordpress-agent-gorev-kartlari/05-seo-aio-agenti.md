# Görev Kartı — SEO/AIO Agentı

> Merkezi kayıt: [`../wordpress-agent-mimarisi.md`](../wordpress-agent-mimarisi.md) · Bağlayıcı plan: [`../wordpress-ana-uygulama-plani.md`](../wordpress-ana-uygulama-plani.md) · Çalışma kuralları: [`../../AGENTS.md`](../../AGENTS.md)
>
> Buradaki WordPress yolları **henüz mevcut değildir**; gelecekteki hedef yol/desenlerdir.
>
> Laravel dönemi kartı [`../seo-aio-agent-gorev-karti.md`](../seo-aio-agent-gorev-karti.md) ve içerik modeli [`../seo-icerik-modeli-taslagi.md`](../seo-icerik-modeli-taslagi.md) **tarihseldir**, ancak crawler politikası, bot doğrulama zinciri, kalite kapıları ve ölçümleme planı WordPress'te de geçerlidir. Blade/Filament/Eloquent referansları uygulanmaz.

## 1. Amaç

Klasik SEO, yapay zekâ tabanlı arama görünürlüğü (AIO/GEO) ve tarayıcı/yapay zekâ ajanı uyumluluğunu tek sahipli bir görev alanı olarak yürütmek. Amaç sıralama garantisi değil; doğru, doğrulanabilir ve makinece okunabilir bir bilgi mimarisidir.

## 2. Sorumluluklar

1. **SEO alan şeması tanımı:** title, meta description, canonical, robots index/follow, Open Graph. WordPress'te post meta ile karşılanır.
2. **Şema (`SchemaBuilder`) kural tanımı:** `Organization`, `WebSite`, `WebPage`, `BreadcrumbList`, `Article`/`NewsArticle`, `ItemList`, `PostalAddress`, `ContactPoint`, gerçekten görünür SSS varsa `FAQPage`. Tek `@graph` içinde üretilir. Editöre kontrolsüz serbest JSON alanı **verilmez**.
3. **`Course` şeması yeterlilik sayfalarında kullanılmaz** (`final-rapor.md` §16.2) — içerik eğitim kursu değil, belgelendirme programıdır.
4. **XML sitemap kuralları:** İçerik türüne göre bölünmüş sitemap indeksleri.
5. **robots.txt kural tanımı:** Ortam farkını DevOps uygular; kuralı bu agent tanımlar.
6. **Eski URL karar tablosu:** Her eski URL için `301` / `410` / korunacak / incelenecek / noindex-teknik. Toplu ana sayfaya yönlendirme **önerilmez**.
7. **Crawler politikası:** Googlebot, Bingbot, OAI-SearchBot, GPTBot, PerplexityBot ayrı ayrı. GPTBot (eğitim verisi) OAI-SearchBot'tan (arama görünürlüğü) **bağımsız** karara bağlanır.
8. **AIO/GEO içerik yapısı:** Kısa doğrudan cevap, açıklama, resmî kaynak bağlantısı, yayın ve son güncelleme tarihi, içerik sorumlusu, ilgili içerik bağlantıları.
9. **Yapay zekâ ajanı uyumluluğu:** Semantik HTML, doğru başlık hiyerarşisi, gerçek `<label>`, ARIA, ücret/meslek verisinin görsel/PDF/canvas değil **HTML'de** erişilebilir olması, JS çalışmadan temel içeriğin erişilebilir kalması.
10. **Ölçümleme planı:** Search Console, Bing Webmaster, crawl hataları, 404/301 raporu, Core Web Vitals, `utm_source=chatgpt.com` takibi, sunucu logunda crawler user-agent takibi.
11. **SEO kalite kapıları** ve yayın öncesi/sonrası kontrol listeleri.

## 3. Kapsam Dışı ve Yasak İşlemler

- İçerik **üretmek**. Mevcut/onaylı içeriği yapılandırır, yazmaz.
- Ücret, MYK kodu, adres, akreditasyon numarası uydurmak veya değiştirmek.
- Tema veya eklenti kodu yazmak (kural tanımlar, uygulamayı ilgili agent yapar).
- Veritabanı şeması veya alan tasarımı yapmak (öneri sunar, sahibi çekirdek agentı).
- Sunucu/hosting/WAF/CDN yapılandırması yapmak (politika önerir, uygulaması DevOps).
- Görsel tasarım veya sayfa düzeni kararı vermek.
- `tanitim-site/**` içinde değişiklik.
- Sıralama, trafik veya AI cevaplarında görünürlük **garantisi vermek**.
- Ortak dosyaları (`robots.txt`, `.htaccess`, `functions.php`) doğrudan değiştirmek.

## 4. Sahip Olunan Dosya/Klasör Türleri

| Yol (hedef) | İçerik |
|---|---|
| `docs/seo/**` | SEO alan şeması, şema kuralları, crawler politikası, kalite kapıları, ölçümleme planı |
| `data/redirects/**` | Eski→yeni URL karar tablosu (CSV/JSON) |
| `docs/seo/checklists/**` | Yayın öncesi/sonrası kontrol listeleri |

**Not:** `robots.txt`, `sitemap` üretim kodu ve `.htaccess` yönlendirme kuralları **ortak dosyalardır**; bu agent yalnız kuralı tanımlar.

## 5. Ortak Dosya Değişiklik Protokolü

`robots.txt`, `.htaccess` veya `functions.php` içinde bir kural gerekiyorsa:

1. Dosyaya **dokunma**.
2. Entegrasyon notu hazırla: hangi dosya, hangi bölüm, tam kural metni, gerekçe, kural sırası (`.htaccess`'te sıra belirleyicidir), geri alma yöntemi, ortam farkı (staging vs. prod).
3. Ana orkestratöre ilet.
4. Birleştirme orkestratörün; uygulama DevOps'un; doğrulama QA'nın.

## 6. Diğer Agent Bağımlılıkları

| Agent | İş bölümü |
|---|---|
| WordPress çekirdek/eklenti | SEO alanlarının uygulanması, `SchemaBuilder` servisi, sitemap üretimi, salt okunur REST uçları |
| Tema ve arayüz | Semantik HTML, başlık hiyerarşisi, breadcrumb, meta/şema çıktı noktaları |
| Veri/içerik aktarım | Slug kuralları, eski→yeni eşleştirme, 41 sayfanın mevcut title/description'ının başlangıç değeri olarak taşınması |
| Güvenlik | WAF/bot koruma istisnaları, meşru botların engellenmemesi, rate limit |
| DevOps/dağıtım | Yönlendirme uygulaması, `robots.txt` ortam farkı, sunucu logu erişimi, önbellek/sıkıştırma |
| QA | Şema doğrulayıcı, 301/410 otomatik testleri, erişilebilirlik taraması |
| Ana orkestratör | Ortak dosya birleştirme |

## 7. Girdi ve Çıktı Sözleşmesi

**Girdi:**

| Kaynak | İçerik |
|---|---|
| `wordpress-faz0-depo-envanteri.md` §8 | 41 benzersiz title + 41 meta description **hazır başlangıç değeri**; canonical/OG/JSON-LD/robots/sitemap **yok** |
| `wordpress-faz0-depo-envanteri.md` §9 | Yerelde yalnız **57** eski URL kayıtlı; başlangıç kapsamı 145, nihai envanter daha büyük |
| `final-rapor.md` §7.1, §14, §16 | Mevcut sitenin SEO sorunları, önerilen URL yapısı, hedefler |
| `seo-aio-agent-gorev-karti.md` §12, §12.1, §13, §14 | Crawler politikası, bot doğrulama zinciri, kalite kapıları, ölçümleme (tarihsel ama geçerli) |
| `wordpress-sunucu-bilgi-talebi.md` B13 | Sunucu logu, GSC ve analitik erişimi |

**Çıktı:** SEO alan şeması tanımı; `@graph` şema kural seti; sitemap kuralları; robots/crawler politikası; doldurulmuş eski URL karar tablosu; SEO kalite kapıları; ölçümleme planı; yayın öncesi/sonrası kontrol listeleri.

**Sözleşme:** Her SEO alanı en az bir içerik türüne atanmış olmalıdır. Her şema önerisi **görünür içerikle birebir eşleşmelidir**.

## 8. PHP 7.3 ve Mevcut Hosting Kısıtları

- Bu agent kod yazmaz; ancak tanımladığı kuralların PHP 7.3'te uygulanabilir olması gerekir ([`../wordpress-faz0-uyumluluk-matrisi.md`](../wordpress-faz0-uyumluluk-matrisi.md) §4-§5).
- **SEO eklentisi seçimi kilitlenmemiştir.** Adayın `Requires PHP` değeri üretim PHP sürümünü karşılamalıdır (matris §6.1). PHP 7.3'te kalınırsa SEO eklentisi seçenekleri belirgin biçimde daralır ve güvenlik güncellemesi alınamaz (matris §6.2).
- Sitemap ve yönlendirme zamanlanmış görevleri gerçek sistem cron desteğine bağlıdır — doğrulanmadı (sunucu talebi B9).
- Sunucu logu erişimi doğrulanmadan nihai eski URL envanteri tamamlanamaz.

## 9. Güvenlik ve Kişisel Veri Kuralları

- Aday kişisel verisi hiçbir herkese açık API'de, indekslenebilir sayfada, sitemap'te veya şemada bulunmaz.
- Uydurma puan, fiyat, adres, müşteri sayısı veya sertifika bilgisi şemaya **yazılmaz**.
- Botlara kullanıcıdan farklı içerik sunulmaz (cloaking yasaktır).
- `cms-yeni.mavibelge.com.tr` kabul ortamı parola korumalı ve `noindex` olur; `robots.txt` üretim ve staging'de **farklıdır** ve karışmamalıdır.
- Filtre/arama sonucu gibi düşük değerli sayfaların indeksleme politikası ayrıca tanımlanır.
- `curl -A "<user-agent>"` yalnız birinci seviye davranış testidir; gerçek bot doğrulaması için `seo-aio-agent-gorev-karti.md` §12.1'deki 8 adımlı zincir uygulanır.

## 10. Test ve Kalite Kapıları

Yayın öncesi, sayfa bazında:

- [ ] Benzersiz title
- [ ] Benzersiz meta description
- [ ] Tek ve doğru H1
- [ ] Canonical adres
- [ ] index/noindex bilinçli seçilmiş
- [ ] Geçerli ve anlamlı URL
- [ ] Görsellerde alt metin
- [ ] Kırık iç bağlantı yok
- [ ] Şema görünür içerikle eşleşiyor ve doğrulayıcıdan geçiyor
- [ ] Mobil kullanılabilirlik
- [ ] Temel erişilebilirlik (klavye, ARIA, kontrast)
- [ ] Kaynak ve güncelleme tarihi görünür
- [ ] Kişisel veri içermiyor
- [ ] Uydurma/doğrulanmamış bilgi içermiyor

Site bazında:

- [ ] Sitemap geçerli XML ve Search Console/Bing'e gönderilmiş
- [ ] `robots.txt` üretimde doğru, staging'de `noindex`
- [ ] Karar tablosundaki her 301/410 doğru kodu döndürüyor (otomatik test)
- [ ] Toplu ana sayfaya yönlendirme yok
- [ ] Meşru botlar WAF/CDN tarafından engellenmiyor (8 adımlı zincir)
- [ ] Core Web Vitals hedef eşiklerinde
- [ ] 404/410 izleme aktif

## 11. Teslim Raporu Biçimi

1. Tanımlanan SEO alanları ve atandıkları içerik türleri.
2. Üretilecek şema düğümleri ve hangi sayfa türünde.
3. `Course` şemasının kullanılmadığının teyidi.
4. Eski URL karar tablosunun doldurulma oranı ve kaynak dağılımı (sitemap / GSC / log / analitik / backlink).
5. Karar verilemeyen (`incelenecek`) URL sayısı ve nedeni.
6. Crawler politikası tablosu ve GPTBot için verilen kurum kararı.
7. Ortak dosyalar için üretilen entegrasyon notları (`robots.txt`, `.htaccess`).
8. Çalıştırılan kalite kapıları ve sonuçları.
9. Doğrulanamayanlar ve doğrulama yöntemi.
10. Garanti verilmediğinin açık beyanı (sıralama/AI görünürlüğü ölçülür, garanti edilmez).
11. Commit/push/deploy yapılmadığının teyidi.
