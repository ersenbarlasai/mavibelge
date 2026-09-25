# SEO / AIO / Yönlendirme Sözleşmesi (Faz 9)

Kod: `includes/seo/*` (SAF: `Seo_Meta`, `Seo_Schema`, `Seo_Robots`), `public/class-seo-service.php` (WordPress bağlantısı),
`includes/redirects/*`. Testler: `tests/suites/faz9-seo.php`, `faz9-redirects.php`, `tools/runtime-test/seo-redirects-http-test.js`
(38 gerçek HTTP kontrolü). Laravel/Eloquent referansları uygulanmadı; `seo-icerik-modeli-taslagi.md`'nin alan sözlüğü ve `@graph`
yaklaşımı WordPress'e uyarlandı.

## Meta
- **title**: `<baz> — Mavi Belge`, en çok 65 karakter (kelime sınırında kısaltma). Öncelik: editör alanı `_mb_seo_title` → statik
  referanstan 41 sayfanın doğrulanmış başlangıç değeri (`includes/seo/data/page-defaults.php`, `tools/seo/build-page-defaults.js`
  ile üretilir, 41 benzersiz title) → içerik başlığı.
- **description**: düz metin, en çok 160 karakter; editör alanı `_mb_seo_description` (yönetimde 180 sınırı **reddeder**, sessiz kırpmaz).
- **canonical**: sorgu dizgesiz temiz URL; sayfalı sayfa kendi numarasıyla (`?mb_page=N` / `?paged=N`); arama ve 404'te üretilmez.
  WordPress varsayılan `rel_canonical` kaldırılır (tek canonical).
- **robots** (`wp_robots`): üretim dışı ortam veya "arama motorlarından gizle" → **`noindex,nofollow` (staging kapısı)** + `X-Robots-Tag`;
  üretimde arama, filtreli URL (`mb_q/mb_sector/mb_level/mb_priced/mb_type/mb_cat/mb_form_status`), 404, editör noindex → `noindex,follow`;
  diğerleri `index,follow,max-image-preview:large`. Sayfalı sayfalar indekslenir (self-canonical).
- **Open Graph / Twitter**: tr_TR, site_name, type (haber → article + published/modified), title, description, url, görsel yalnız
  gerçek öne çıkan görselden. **`twitter:site` uydurulmaz** (yalnız `mavibelge_core_seo.twitter_site` verilirse).

## schema.org `@graph` (`MaviBelge_Core_Seo_Schema`)
Site geneli tek `Organization` (yalnız statik başlıktan doğrulanmış telefon/e-posta; `sameAs` yok) ve `WebSite`; sayfa başına tek `WebPage` +
`BreadcrumbList`. İçerik: haber → `NewsArticle`, duyuru → `Article` (başlık + yayın tarihi zorunlu); `/sss/` → `FAQPage` (yalnız görünür soru+cevap);
sektör → `ItemList`; doküman → `DigitalDocument` (yalnız doğrulanmış dosya varsa); lokasyon → `Place`+`PostalAddress`. **Üretilmeyenler**: `Course`, `Product`/
`Offer`/fiyat, `Review`, referanslar (temsili logo müşteri iddiası olur), editör serbest JSON-LD. `validate()`: benzersiz `@id`, çözülen başvurular, zorunlu alanlar,
çift `Organization/WebSite/WebPage/BreadcrumbList` reddi; geçersiz varlık düğümü atlanır. JSON `JSON_HEX_TAG|AMP|APOS|QUOT` ile kodlanır (`</script>` enjeksiyonu yok).

## robots.txt ve sitemap
`robots_txt` filtresiyle **sanal** çıktı (gerçek dosyaya/sunucuya dokunulmaz; fiziksel robots.txt varsa o kazanır). Üretimde `*` grubu (wp-admin kapalı, admin-ajax açık,
site içi arama kapalı), Googlebot/Bingbot/OAI-SearchBot/PerplexityBot serbest, **GPTBot kurum kararı** (varsayılan: kural yok). Süzgeçli/sayfalı URL'ler robots ile **engellenmez**
(noindex görülebilsin). Staging: `User-agent: * / Disallow: /`. Bot doğrulama zinciri (WAF/CDN, resmî IP, ters DNS, log — kart §12.1) **kodla kanıtlanmaz**; DevOps/güvenlik
doğrulaması açık kalır. Çekirdek sitemap: sayfa, yeterlilik (aktif), haber (onaylı), doküman/lokasyon (pasif değil) + `mb_sektor`; `mb_ucret`, SSS, referans, haber türü,
kategori taksonomileri ve **kullanıcı sitemap'i hariç**.

## Eski URL yönlendirme kayıt sistemi
Kural: `source,target,status(301|302|410),origin(verified|proposed),active,note`. Küme doğrulayıcı reddeder: çakışan kaynak, kendine yönlendirme, **döngü, zincir**, hedefi 410 olan kural,
dış/protokolsüz hedef, korumalı yollar, toplu ana sayfa hedefi. Bozuk küme **hiçbir** yönlendirme üretmez. Çalışma zamanı yalnız **gerçek 404**'te ve yalnız **aktif** kuralda uygular
(var olan içeriği gölgelemez). `wp mavibelge redirects import --file=<manifest>` **dry-run** varsayılan; apply: `MAVIBELGE_REDIRECTS_APPLY_ENABLED` + yönetici + `--confirm=<digest>`;
yalnız `verified` ve hedefi var olan kurallar aktif yazılır.
Yerelde kayıtlı **57** eski URL sınıflandırıldı (`docs/redirect-mapping-report.md`): 9 keep, 29 redirect (2 verified/aktif aday, 27 proposed/**pasif**), 6 infrastructure, 13 needs_decision.
Bilinmeyen eski URL **uydurulmadı**; nihai envanter (≥145) canlı sitemap/GSC/log olmadan tamamlanamaz. Gerçek manifest PHP doğrulayıcısından dry-run'da `applicable=true` geçti (29 create, 2 hedef henüz yok).

## AIO/GEO içerik yapısı
Açık, kaynaklı, tekrar etmeyen yapı: her sayfa tek H1, kısa özet + görünür içerik; şema yalnız görünür içerikten; kaynak olarak MYK/mevzuat bağlantıları içerik kurallarına tabidir (uydurma iddia yok).
