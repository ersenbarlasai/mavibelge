# SEO İçerik Modeli Taslağı (Laravel + Filament — Planlama)

> Bu belge [`seo-aio-agent-gorev-karti.md`](./seo-aio-agent-gorev-karti.md) §4 ve §11 Aşama 2-3'ün detayıdır. Sahibi: `seo-aio-agent` (öneri), uygulaması veritabanı/backend agentı. `final-rapor.md` §12 çekirdek veri modeliyle uyumludur, onu değiştirmez, SEO alanları için genişletir. Agent kayıt/yönetişimi için bkz. [`agent-mimarisi.md`](./agent-mimarisi.md).
>
> Bu aşamada migration yazılmaz. Aşağıdaki alanlar tasarım önerisidir.
>
> **Admin paneli kararı:** yönetim paneli **Filament** ile yapılacaktır (mimari karar, kesinleşmiştir). Filament ana sürümü şimdiden sabitlenmez. Kullanılacak Laravel, PHP ve Filament sürümleri; hosting firmasının desteklediği PHP sürümü doğrulandıktan sonra, birbiriyle uyumlu güncel kararlı sürümler arasından seçilecektir (`final-rapor.md` §11.2, §18.2 madde 1 ve 3 ile uyumlu).

## 1. Yaklaşım: Tekrar Kullanılabilir SEO Mimarisi

Her SEO alanını her tabloya (pages, sectors, qualifications, posts, documents, locations…) tekrar tekrar eklemek yerine önerilen yaklaşım: **çok biçimli (polymorphic) `seo_meta` tablosu**.

```
seo_meta
  id
  seoable_type          (ör. Page, Sector, Qualification, Post, Document, Location)
  seoable_id
  seo_title
  meta_description
  canonical_url         (override; sistem varsayılanı yoksa kullanılır)
  robots_index          (bool)
  robots_follow         (bool)
  og_title
  og_description
  og_image
  schema_editorial_type (opsiyonel, salt editoryal sınıflandırma — bkz. §3)
  updated_at            (Laravel'in teknik kayıt güncelleme alanı; içeriğin
                         "son güncelleme tarihi" olarak KULLANILMAZ, bkz. aşağıdaki not)
```

**`seo_meta` yalnızca SEO'ya ait alanları içerir.** İçerik yayın bilgisi bu tabloda tutulmaz; sahibi içerik modelidir (bkz. "İçerik Yayın Bilgisi" bölümü).

`seo_meta.updated_at` yalnızca Laravel'in kendi kayıt güncelleme zaman damgasıdır (teknik amaçlı). AIO/GEO ve editör arayüzünde gösterilen "son güncelleme tarihi", içerik modelindeki `content_updated_at` alanından okunur — `seo_meta.updated_at` bu amaçla kullanılmaz, karıştırılmamalıdır.

Gerekçe: `slug` her modelin kendi tablosunda kalır (URL üretimi ilgili modele özgüdür — bkz. `final-rapor.md` §14.1); geri kalan SEO alanları tek yerde yönetilir, Filament'te tek bir "SEO" ilişkisel form bileşeni (relation manager / trait) tüm içerik türlerinde yeniden kullanılabilir.

Alternatif (Filament sürümü/polymorphic ilişki kısıtı ortaya çıkarsa): her tabloya `seo_title`, `meta_description` vb. doğrudan sütun ekleme. Bu, Laravel/Filament sürüm kilidi kesinleşince değerlendirilir (yukarıdaki admin paneli kararı notuna bkz.).

## 1.1. İçerik Yayın Bilgisi (sahibi: içerik modeli, `seo_meta` değil)

Aşağıdaki alanlar içerik modelinde (ör. `pages`, `posts`, `qualifications` vb.) veya tekrar kullanılabilir ayrı bir "içerik yayın bilgisi" yapısında (ör. `content_publishing_info` — polymorphic, `seo_meta`'dan bağımsız) tutulur:

| Alan | Tip | Not |
|---|---|---|
| `published_at` | datetime | İlk yayın tarihi |
| `content_updated_at` | datetime | İçeriğin gerçek son güncelleme tarihi (AIO/GEO §16.3 için görünür alan budur) |
| `author_id` | FK → users | İçeriği yazan/giren kişi |
| `reviewer_id` | FK → users | Kontrol eden kişi |
| `reviewed_at` | datetime | Son kontrol tarihi |
| `approval_status` | enum | Ör. `taslak`, `incelemede`, `onaylandi`, `yayinda` |

`seo-aio-agent` bu alanları **görüntüler** ve yapılandırılmış veri (`Article`/`NewsArticle` gibi) üretiminde **kullanır**; ancak sahibi ve yazılı-yetkilisi içerik agentıdır. Bu ayrım, SEO agentının içerik onay/yazarlık verisini tek taraflı değiştirmesini önler (bkz. görev kartı §3, §5).

## 2. Alan Sözlüğü (`seo_meta`)

| Alan | Tip | Not |
|---|---|---|
| `seo_title` | string | Boşsa H1/başlıktan otomatik üretim fallback'i olabilir |
| `meta_description` | string (~120-155 karakter önerilir) | Bkz. §2.1 doğrulama kuralları |
| `slug` | string | İlgili modelin kendi tablosunda; `final-rapor.md` §14.1 kurallarına tabi |
| `canonical_url` | string | Mümkün olduğunca sistemce üretilir; override yalnızca yetkili kullanıcı tarafından, bkz. §2.1 |
| `robots_index` | bool | Varsayılan true; filtre/arama sonucu gibi düşük değerli sayfalarda false |
| `robots_follow` | bool | Varsayılan true |
| `og_title` / `og_description` / `og_image` | string | Boşsa seo_title/meta_description/varsayılan görsele düşer; `og_image` boyut/dosya doğrulamasına tabi (§2.1) |
| `schema_editorial_type` | enum/string, opsiyonel | Yalnızca editoryal sınıflandırma etiketi; JSON-LD üretimini tek başına belirlemez (bkz. §3) |

### 2.1. Meta Alanlarında Doğrulama Kuralları

- `seo_title` ve `meta_description` için **veritabanı seviyesinde katı unique kısıtı uygulanmaz** (farklı sayfalarda meşru kısmi benzerlik olabilir; katı unique kısıtı yanlış pozitif üretir).
- Bunun yerine **editoryal kalite kontrolü**: aynı veya çok benzer title/description üreten sayfalar periyodik raporla tespit edilir ve editöre bildirilir (bkz. görev kartı §13 kalite kapıları).
- Panelde uzunluk önerisi ve canlı SERP önizlemesi gösterilir (title/description karakter sayacı + görsel önizleme).
- Aynı veya çok benzer başlık/açıklama girildiğinde editöre uyarı gösterilir (kaydı engellemez, bilgilendirir).
- `canonical_url` mümkün olduğunca sistem tarafından üretilir (slug + domain'den otomatik); override yalnızca yetkili rol tarafından yapılabilir ve denetim izine (`audit_logs`) yazılır.
- Canonical override girişinde relative path mi mutlak URL mi olduğu ve domain'in siteye ait olduğu doğrulanır (yanlışlıkla dış domain'e canonical verilmesini önlemek için).
- `og_image` için dosya türü, boyut sınırı ve önerilen en-boy oranı doğrulanır (paylaşımda kırpılma/bozulma önlemek için).

## 3. İçerik Türlerine Göre SEO İhtiyaçları

Bir sayfa aynı anda birden fazla yapılandırılmış veri düğümü içerebilir (ör. bir yeterlilik sayfası aynı anda `WebPage` + `BreadcrumbList` + varsa `FAQPage` içerir; site geneli `Organization`/`WebSite` her sayfada tekrarlanabilir). Aşağıdaki tablodaki değerler o sayfada **hangi düğümlerin üretileceğini** gösterir, tek bir `schema_type` sütunu değildir (bkz. §3.1 SchemaBuilder).

| İçerik türü | Üretilecek şema düğümleri | Özel not |
|---|---|---|
| Sabit sayfalar (`pages`) | `WebPage`, `BreadcrumbList` | Kurumsal politika sayfalarında breadcrumb zorunlu |
| Haberler (`posts` — haber) | `NewsArticle`, `BreadcrumbList` | Yayın tarihi + görsel zorunlu (`final-rapor.md` §7.3, eski haberler görselleriyle taşınacak); tarih `content_updated_at`/`published_at`'ten okunur |
| Duyurular (`posts` — duyuru) | `Article`, `BreadcrumbList` | Geçerlilik/son tarih varsa belirtilmeli |
| Sektörler (`sectors`) | `WebPage`, `BreadcrumbList`, `ItemList` (bağlı meslekler) | AIO zinciri başlangıcı: Sektör → Meslek |
| Meslekler/yeterlilikler (`qualifications`) | `WebPage`, `BreadcrumbList`, (yalnızca gerçek görünür SSS varsa) `FAQPage` | `Course` **kullanılmaz**; MYK kodu/seviye/revizyon HTML'de erişilebilir olmalı |
| Ücret tarifeleri (`fees`/`fee_periods`) | `WebPage`, `BreadcrumbList` | Geçerlilik dönemi görünür olmalı; uydurma fiyat yasak |
| Dokümanlar (`documents`) | `WebPage`, `BreadcrumbList` | Sürüm ve geçerlilik tarihi zorunlu (`final-rapor.md` §7.4, §12.4) |
| Şubeler/lokasyonlar (`locations`) | `WebPage`, `PostalAddress`, `ContactPoint` | Gerçek, doğrulanmış adres/telefon dışında veri yasak |
| Sınav süreçleri | `WebPage`, `BreadcrumbList` | Adım adım süreç; kısa doğrudan cevap + açıklama (AIO formatı §16.3) |
| Belge yenileme | `WebPage`, `BreadcrumbList` | Son güncelleme tarihi kritik (mevzuat değişebilir); `content_updated_at`'ten okunur |
| Kurumsal sayfalar (hakkımızda, yetki/akreditasyon, tarafsızlık beyanı vb.) | `WebPage`, `BreadcrumbList` + site geneli `Organization` | `Organization` şeması site genelinde tek ve tutarlı, her sayfada aynı veriyle tekrarlanır |

Site geneli, her sayfada sabit: `Organization`, `WebSite`. Sayfa bazlı: `WebPage`, `BreadcrumbList`. İçerik bazlı: `Article`/`NewsArticle`, `FAQPage`, `ItemList`, `PostalAddress`/`ContactPoint`.

## 3.1. Merkezi SchemaBuilder — Çoklu Düğüm (`@graph`) Yaklaşımı

- JSON-LD, editörün elle yazdığı serbest bir alandan değil, **merkezi bir `SchemaBuilder` servisinden** üretilir. Servis, sayfanın gerçek modelinden (içerik + `seo_meta` + içerik yayın bilgisi) veri okur ve o sayfa için geçerli düğümleri oluşturur.
- Bir sayfada birden fazla düğüm gerekiyorsa tek bir `@graph` dizisi içinde üretilir (ör. `{"@context":"https://schema.org","@graph":[{"@type":"Organization",...},{"@type":"WebPage",...},{"@type":"BreadcrumbList",...}]}`).
- Editörlere **kontrolsüz serbest JSON alanı sunulmaz.**
- Özel durum (istisnai veri sapması) gerektiğinde:
  - yalnızca önceden izin verilmiş alanlar override edilebilir (serbest şema eklenemez),
  - her override alan bazında doğrulama kuralına tabidir (tip, uzunluk, format),
  - her override `audit_logs`'a kaydedilir (kim, ne zaman, hangi alan),
  - override, sayfanın görünür içeriğiyle eşleşmek zorundadır — görünmeyen/uydurma veri üretmek için kullanılamaz.
- `schema_editorial_type` (yukarıda §2) yalnızca editörün panelde "bu sayfa haber gibi davransın" türünde bir sınıflandırma seçmesini sağlar; SchemaBuilder bu etiketi girdi olarak kullanır ama nihai `@graph` çıktısını tek başına belirlemez.

**Önemli:** Yapılandırılmış veri, arama motoruna/AI sistemine içerik hakkında **açıklama sağlar**; zengin sonuç, AI Overview görünürlüğü veya sıralama **garanti etmez** (bkz. görev kartı §1, §3). `FAQPage` yalnızca sayfada kullanıcıya gerçekten görünen soru-cevap içeriği varsa üretilir; görünür olmayan içerik üretmek için şema kullanılmaz.

## 4. Yönlendirme (Redirect) Modeli

`final-rapor.md` §12.2'de zaten tanımlı `redirects` tablosunu SEO açısından şu şekilde tamamlar:

```
redirects
  old_path
  new_path        (null ise 410)
  status_code     (301 | 410 | none — "korunacak" veya "incelenecek" kararları için)
  decision        (301 | 410 | korunacak | incelenecek | noindex-teknik-adres)
  reason          (ör. "birleştirildi", "yazım hatası", "bilinçli kaldırıldı")
  source          (ör. "sitemap", "search-console", "server-log", "analytics", "backlink")
  decided_by
  decided_at
  verified_at     (test sonucu tarihi)
```

### 4.1. Envanter Kapsamı

`final-rapor.md` §14.2'de anılan **145 sayısı, raporda ilk tespit edilen sayfa ve haber URL'lerinden oluşan başlangıç kapsamıdır** — sitenin kesin/nihai eski URL toplamı değildir. Nihai eski URL envanteri, yayın öncesi şu kaynakların birleştirilmesiyle oluşturulur:

- WordPress sitemap dosyaları
- Sayfa ve haber sitemap kayıtları
- Kategori ve etiket adresleri
- Medya/attachment adresleri
- Mevcut (varsa) yönlendirmeler
- Google Search Console (indekslenmiş/taranmış URL listesi)
- Sunucu erişim kayıtları
- Analitik verileri (gerçek ziyaret almış eski URL'ler)
- Değerli dış bağlantı (backlink) alan eski URL'ler

Her URL için verilecek kararlardan biri:

- Yeni eşdeğer sayfaya **301**
- Bilinçli kaldırılan içerik için **410**
- **Korunacak** adres (değişmeyecek)
- **İncelenecek** (karar henüz verilemedi)
- **İndekslenmemesi gereken teknik/eklenti adresi** (noindex veya erişime kapatma)

Alakasız eski adresler topluca ana sayfaya yönlendirilmez.

## 5. Sınırlar

- Bu taslak alan/ilişki önerisidir; migration değildir.
- Şema override alanları serbest metin/uydurma alanı değildir — yalnızca doğrulanmış, denetim izli istisna verisi için (bkz. §3.1).
- Kişisel veri (aday adı, TC kimlik, iletişim bilgisi) bu modele veya herhangi bir public/SEO alanına girmez.
