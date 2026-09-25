# Bileşen Kataloğu — Faz 3

> Kaynak: `wp-content/themes/mavibelge/template-parts/components/**`. Her bileşen bağımsız, sunum-odaklı bir `template-part`'tır: **veritabanı sorgusu yapmaz, iş kuralı içermez**, yalnız `$args` ile verilen veriyi bağlamına göre kaçış (`esc_html`/`esc_attr`/`esc_url`/sınırlı `wp_kses_post`) uygulayarak gösterir.

Kullanım: `get_template_part( 'template-parts/components/<isim>', null, $args );`

## Bileşenler

| Dosya | Amaç | Zorunlu `$args` | Notlar |
|---|---|---|---|
| `button.php` | Buton/link (primary/secondary/ghost, sm, block) | `label` | `url` boşsa `<button>` üretir, **asla `href="#"` üretmez**. `attrs` içindeki öznitelik ADLARI bir allowlist'ten geçer (`id`/`name`/`value`/`disabled`/`download`/`aria-*`/`data-*`) — `onclick`/`style`/`formaction` gibi adlar sessizce atılır. `disabled` gerçek HTML boolean niteliği olarak ele alınır: falsy değer HİÇ nitelik üretmez (`disabled=""` bile devre dışı bırakır), truthy değer yalnız çıplak `disabled` üretir; `download` boolean veya dosya adı değeri kabul eder; array/object değer sessizce atılır, "Array" olarak sızmaz; geçersiz `variant` `primary`'ye düşer (Faz 3 İkinci Kabul Düzeltmesi) |
| `card.php` | Genel kart kabuğu | — | `content` (düz metin, kaçışlı) veya `content_html` — **`wp_kses_post()` ile süzülür** (Faz 3 Düzeltme ve Kabul; önceden ham `echo` idi) |
| `section-heading.php` | Eyebrow + başlık + açıklama | `title` | `level` ile `<h2>`–`<h4>` arası |
| `breadcrumb.php` | Breadcrumb **yalnız sunum kabuğu** | `items` | SEO/`BreadcrumbList` şeması **üretmez** (görev kartı 05, Faz 9) |
| `badge.php` | Etiket/rozet | `label` | `variant`: `''`\|`duyuru`\|`neutral` |
| `alert.php` | Uyarı **veya** boş durum | `message` | `type`: `alert`\|`empty-state`; alert `variant`: `info`\|`success`\|`warning`\|`danger`. **Yeni bileşen** — bkz. `design-system.md` §5.1 |
| `table-wrap.php` | Erişilebilir tablo sarmalayıcı | `head`, `rows` | Yatay taşma `overflow-x:auto` ile korunur; hücreler düz metin (kaçışlı) |
| `form-field.php` | Form alanı kabuğu + hata özeti | `id`, `label` | Gerçek form/submit **yok**; `hint`/`error` için `aria-describedby` bağlanır; `type` allowlist'ten geçmezse `text`'e düşer, `error` set edilince `aria-invalid="true"` eklenir (Faz 3 Düzeltme ve Kabul) |
| `pagination.php` | Sayfalama sunumu | `current`, `total`, `url_for` (callable) | `url_for` her zaman gerçek URL döndürmeli — **asla `#` yok**. Aktif sayfa `<button>` değil `<span aria-current="page">` (tıklanamaz, semantik doğru); `current` `[1,total]` aralığına sıkıştırılır (Faz 3 Düzeltme ve Kabul) |
| `trust-logo.php` | MYK/TÜRKAK güven bloğu | — | Varsayılan: gerçek `YB-0052`/`AB-0104-P`; açıklama metni eklenmez |
| `back-to-top.php` | Sayfa başına dön düğmesi | — | Davranış `assets/src/js/components.js`'te |

## Erişilebilirlik kuralları (tüm bileşenler)

- Dekoratif SVG ikonlar `aria-hidden="true"` taşır.
- Tüm dinamik metin `esc_html()`; tüm URL `esc_url()`; tüm HTML özniteliği `esc_attr()`.
- Etkileşimli öğeler (`button`, `a`) en az 44×44px dokunma alanına sahip olacak biçimde `components.css`'teki `.btn`/`.page-btn` boyutlarını kullanır.
- Form alanı bileşeni `label`/`for` eşleşmesini ve `aria-describedby` zincirini otomatik kurar.

## Faz 5 — katalog template-part'ları (`template-parts/catalog/**`)

Bu dosyalar genel `components/**` kataloğunun bir parçası değildir (yalnız meslek/ücret sayfaları bağlamında anlamlıdır, `home/**`'in kendi bölümlerine benzer), ama aynı kuralları izler: veritabanı sorgusu yapmazlar, veriyi `inc/catalog-helpers.php` üzerinden çağırana bırakırlar.

| Dosya | Amaç | Notlar |
|---|---|---|
| `filter-form.php` | GET-tabanlı meslek/ücret filtre formu | Gerçek `method="get"` form, JS gerektirmez; `sectors` boş/verilmezse sektör alanı gizlenir (taksonomi sayfasında sektör kilitli bağlam) |
| `results-meta.php` | "Toplam X ...'dan Y–Z arası gösteriliyor" cümlesi | `aria-live` **yok** — tam sayfa GET yeniden yüklemesi olduğu için canlı bölge gereksiz gürültü olurdu |
| `fee-options.php` | Tek fiyat **veya** `<details><summary>` çok seçenekli fiyat gösterimi | Yerleşik `<details>`/`<summary>`, JS yok (`page-sss.php`'deki gerekçeyle aynı) |
| `fee-table.php` | Masaüstü ücret tablosu | `table-wrap.php`'i kullanmaz — Ücret kolonu zengin içerik (fiyat seçenekleri) taşır; ≤768px'te `display:none` |
| `fee-cards.php` | Mobil ücret kartları | `fee-table.php` ile aynı veri, farklı düzen; `display:none`/`display:flex` ile karşılıklı görünürlük — **`display:none` bir öğeyi erişilebilirlik ağacından tamamen çıkarır**, bu yüzden aynı kayıt hiçbir zaman ekran okuyucuya iki kez sunulmaz |

## Bilinçli olarak eklenmeyenler

- Slider/carousel bileşeni (referans slider'ı Faz 4/7'ye ait).
- Meslek adı/MYK kodu arama kutusu Faz 5'te gerçek GET formu olarak eklendi (`template-parts/catalog/filter-form.php`, `template-parts/home/hero.php`); JS tabanlı öneri/autosuggest listesi hâlâ eklenmedi (brief §6.5, gelecek faz kapsamı).
- Akordeon (SSS içeriği Faz 7/8'e ait; statik `components.js`'teki akordeon davranışı bu fazda taşınmadı — bkz. `assets/src/js/components.js` üst yorum).
- Gerçek form gönderimi (Faz 8).

## Faz 7/8 ek şablon parçaları

| Parça | Görev | Veri kaynağı |
|---|---|---|
| `template-parts/content/news-card.php` | Haber/duyuru kartı (tür rozeti, `<time>`) | `Content_Service::build_news_dto()` |
| `template-parts/content/document-card.php` | Doküman kartı; indirme yalnız doğrulanmış dosyada | `build_document_dto()` |
| `template-parts/content/location-card.php`, `location-list.php` | Lokasyon kartı / iletişim bölümü (`tel:` + https harita) | `build_location_dto()` |
| `template-parts/content/reference-grid.php` | Referans ızgarası (slider yok, "Temsili görsel" notu) | `build_reference_dto()` |
| `template-parts/content/faq-list.php` | `<details>` SSS akordeonu | `build_faq_dto()` |
| `template-parts/content/filter-links.php` | Gerçek bağlantılı filtre şeridi (`aria-current`) | `mavibelge_content_filter_links()` |
| `template-parts/forms/form.php`, `form-status.php` | Gerçek form / başarı-hata iletisi (JS gerektirmez) | `Forms_Service::describe()` |
| `template-parts/page/content-form-live.php` | Kapı açıkken form sayfası gövdesi | — |
Tümü yalnız DTO render eder; doğrudan sorgu yoktur (`tests/static/content-forms-contract.test.js`).
