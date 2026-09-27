# Sayfa–Şablon Eşleştirme Envanteri — Faz 4

> 41/41 statik `tanitim-site/*.html` sayfasının WordPress karşılığı. "Planlanan yol" sütunu, kayıtlı gerçek CPT/taksonomi rewrite slug'larından (`wp-content/plugins/mavibelge-core/includes/class-content-types.php`, `class-taxonomies.php`) veya statik dosya adından türetilmiştir — **tahmin değildir**; her satırda kaynağı belirtilmiştir. Bu fazda hiçbir rewrite kuralı, redirect veya eklenti değişikliği yapılmamıştır.
>
> Durum sütunu: **tam sunum** (gerçek WordPress sorgusu + boş durum + tam presentation), **temel kabuk** (şablon var, veri Faz 6+ ile gelecek), **gelecek faz bağımlı** (bu fazda kasıtlı olarak yalnız `the_content()` fallback'i).

## Gerçek slug ile statik dosya adı arasındaki bilinçli farklar

Aşağıdaki 3 sayfada statik dosya adı ile CPT/taksonominin **gerçekten kayıtlı** rewrite slug'ı örtüşmüyor. Bu bir hata değil, kayıt gerçeğinin doğrudan yansıtılmasıdır — "URL tahmin yasağı" (§3) gereği örtüşüyormuş gibi davranılmamıştır:

| Statik dosya | Statik/kavramsal ad | Gerçek WordPress yolu | Kaynak |
|---|---|---|---|
| `meslekler.html` | "meslekler" | `/yeterlilikler/` (`archive-mb_yeterlilik.php`) | `class-content-types.php`: `mb_yeterlilik` → `rewrite => array('slug' => 'yeterlilikler')` |
| `sektor.html?slug=X` | "sektor" (query string) | `/sektor/{term-slug}/` (`taxonomy-mb_sektor.php`) | `class-taxonomies.php`: `mb_sektor` → `rewrite => array('slug' => 'sektor')`. Statik sitenin `?slug=` query-string yaklaşımı yerine gerçek WordPress path-segment terim URL'si kullanılır. |
| `duyurular.html` | "duyurular" | `/haber-turu/duyuru/` (`taxonomy.php`, `mb_haber_turu` terimi `duyuru`) | `class-taxonomies.php`: `mb_haber_turu` → `rewrite => array('slug' => 'haber-turu')`. `/duyurular/` kullanıcıya görünen daha doğal bir yol olurdu ama bunu üretmek bir rewrite kuralı gerektirir — **Faz 9 SEO/AIO agentının kararı**, bu fazda eklenmedi. |

## 41/41 tablo

| # | Statik dosya | Kabul edilen başlık | Planlanan WP yolu | Nesne türü | Template dosyası | Durum | Hedef faz / sahip | Bilinçli fark / açık karar |
|---:|---|---|---|---|---|---|---|---|
| 1 | `index.html` | Mavi Belge — Mesleki Yeterlilik Sınav ve Belgelendirme Kuruluşu | `/` | front page | `front-page.php` | tam sunum (kabuk); veri Faz 6 | Faz 6 (haber/referans verisi) | 9 bölüm de gerçek `index.html` ile birebir sırada; "öne çıkan meslek" diye ayrı bölüm **yoktur** (gerçek referansta da yok) |
| 2 | `404.html` | Sayfa Bulunamadı (404) | (sistem, WP 404 durumu) | 404 | `404.php` | tam sunum | — | Arama kutusu gerçek `get_search_form()`; statik JS autosuggest değil (Faz 5) |
| 3 | `kurumsal.html` | Kurumsal | `/kurumsal/` | `page` | `page.php` (layout: hub) | tam sunum (kabuk) | Faz 6 (sayfa içeriği importu) | 8 kart bağlantısı `inc/page-layouts.php`'de; içerik `the_content()` |
| 4 | `bilgi-merkezi.html` | Bilgi Merkezi | `/bilgi-merkezi/` | `page` | `page.php` (layout: hub) | tam sunum (kabuk) | Faz 6 | 9 kart bağlantısı |
| 5 | `sinav-ve-basvuru.html` | Sınav ve Başvuru | `/sinav-ve-basvuru/` | `page` | `page.php` (layout: hub) | tam sunum (kabuk) | Faz 6 | 9 kart bağlantısı |
| 6 | `hakkimizda.html` | Hakkımızda | `/hakkimizda/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | Yalnız `the_content()`; metin bu fazda yazılmadı |
| 7 | `misyon-vizyon.html` | Misyon ve Vizyon | `/misyon-vizyon/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 8 | `kalite-politikamiz.html` | Kalite Politikamız | `/kalite-politikamiz/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 9 | `tarafsizlik-beyani.html` | Tarafsızlık Beyanı | `/tarafsizlik-beyani/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 10 | `yasal-dayanagimiz.html` | Yasal Dayanağımız | `/yasal-dayanagimiz/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 11 | `yetki-akreditasyon.html` | Yetki ve Akreditasyon | `/yetki-akreditasyon/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 12 | `referanslar.html` | Referanslarımız | `/referanslar/` | `page` (bounded `mb_referans` sorgusu) | `page-referanslar.php` | tam sunum (kabuk); veri Faz 6 | Faz 6 | `mb_referans` `has_archive=>false`; CPT kendi arşivini üretmiyor, bu yüzden ayrı `page`. Slider **yok** (Faz 4 brief §7.3), erişilebilir statik grid var |
| 13 | `sosyal-sorumluluk.html` | Sosyal Sorumluluk | `/sosyal-sorumluluk/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | Yalnız `the_content()` |
| 14 | `kariyer.html` | Kariyer | `/kariyer/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 15 | `meslekler.html` | Tüm Meslekler ve Yeterlilikler | `/yeterlilikler/` (bkz. yukarıdaki fark tablosu) | `mb_yeterlilik` archive | `archive-mb_yeterlilik.php` | tam sunum; arama/filtre Faz 5'te gerçek, veri Faz 6 | Faz 6 (veri) | Faz 5: gerçek GET arama/sektör/seviye/"yalnız fiyatı bulunanlar" filtresi + sayfalama eklendi (`MaviBelge_Core_Catalog_Service`). Statik `.filter-bar`'ın JS-tabanlı canlı önerisi/otomatik-tamamlaması hâlâ eklenmedi — bkz. `docs/catalog-service-contract.md` |
| 16 | `sektor.html?slug=X` | Sektör Meslekleri | `/sektor/{term-slug}/` (bkz. fark tablosu) | `mb_sektor` taxonomy | `taxonomy-mb_sektor.php` | tam sunum; arama/filtre Faz 5'te gerçek, veri Faz 6 | Faz 6 (veri) | Path-segment terim URL'si, query-string değil. Faz 5: sektör bağlamı URL'e kilitli; arama/seviye/fiyat filtreleri o sektöre sınırlı olarak çalışır |
| 17 | `yeterlilik.html?...` | Yeterlilik Detayı | `/yeterlilikler/{slug}/` | `mb_yeterlilik` single | `single-mb_yeterlilik.php` | tam sunum; veri Faz 6 | Faz 6 (veri) | Faz 5: `get_active_fees_for_qualification()` ile gerçek, yalnız `_mb_qualification_id` ilişkili aktif ücret gösteriliyor; eşleşme yoksa dürüst boş durum + Sınav Ücretleri bağlantısı. "Yeterlilik Birimleri"/"Sınav Yapısı" gibi statik örnek metinler kopyalanmadı, yalnız gerçek meta alanları + `the_content()` gösteriliyor |
| 18 | `belge-yenileme.html` | Belge Yenileme | `/belge-yenileme/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | Yalnız `the_content()` |
| 19 | `haberler.html` | Haberler | `/haberler/` | `mb_haber` archive | `archive-mb_haber.php` | tam sunum (kabuk); veri Faz 6 | Faz 6 | Gerçek yayın/onay durumu WordPress `post_status=publish` + `_mb_approval_status` kapısından geliyor (eklenti tarafında), tema tekrar uygulamıyor |
| 20 | `duyurular.html` | Duyurular | `/haber-turu/duyuru/` (bkz. fark tablosu) | `mb_haber_turu` taxonomy (`duyuru` terimi) | `taxonomy.php` (generic) | tam sunum (kabuk); veri Faz 6 | Faz 9 (kısa/anlamlı URL kararı) | Ayrı bir `taxonomy-mb_haber_turu.php` yerine tek `taxonomy.php` içinde terim kontrolüyle dallanıyor — bkz. `template-architecture.md` |
| 21 | `haber-detay.html?slug=X` | Haber Detayı | `/haberler/{slug}/` | `mb_haber` single | `single-mb_haber.php` | tam sunum (kabuk); veri Faz 6 | Faz 6 | Öne çıkan görsel gerçek `the_post_thumbnail()`; sabit `<img>` yok |
| 22 | `dokumanlar.html` | Doküman Merkezi | `/dokumanlar/` | `mb_dokuman` archive | `archive-mb_dokuman.php` | tam sunum (kabuk); veri Faz 6 | Faz 6 | Statik 4 sabit kategori grubu kopyalanmadı; gerçek WordPress loop |
| 23 | `mevzuat.html` | Mevzuat | `/mevzuat/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | Yalnız `the_content()` |
| 24 | `myk.html` | MYK Nedir? | `/myk/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 25 | `turkak.html` | TÜRKAK Nedir? | `/turkak/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 26 | `ulusal-meslek-standartlari.html` | Ulusal Meslek Standartları | `/ulusal-meslek-standartlari/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 27 | `ulusal-yeterlilikler.html` | Ulusal Yeterlilikler | `/ulusal-yeterlilikler/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 28 | `sss.html` | Sık Sorulan Sorular | `/sss/` | `page` (bounded `mb_sss` sorgusu) | `page-sss.php` | tam sunum (kabuk); veri Faz 6/7 | Faz 6/7 | `mb_sss` `has_archive=>false`, ayrı `page`. Gerçek statik sayfa da **düz liste** — `mb_sss_kategori` taksonomisi var ama görünür grup yok; aynı davranış korundu, kategoriye göre gruplama gelecekte eklenebilir |
| 29 | `nasil-basvururum.html` | Nasıl Başvururum? | `/nasil-basvururum/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | Yalnız `the_content()` |
| 30 | `online-basvuru.html` | Online Başvuru (Demo) | `/online-basvuru/` | `page` | `page.php` (layout: form-disabled) | temel kabuk (bilinçli devre dışı) | Faz 8 | Gerçek `<form>` **yok**; yalnız alan etiketi önizlemesi + "henüz etkin değil" bildirimi |
| 31 | `sinav-takvimi.html` | Sınav Takvimi | `/sinav-takvimi/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | Yalnız `the_content()` |
| 32 | `sinav-ucretleri.html` | Sınav Ücretleri | `/sinav-ucretleri/` | `page` (bounded `mb_ucret` sorgusu, servis üzerinden) | `page-sinav-ucretleri.php` | tam sunum (kabuk); veri Faz 6 | Faz 6 | Faz 5'te gerçek `page-{slug}.php` istisnasına taşındı (`page-referanslar.php`/`page-sss.php` ile aynı sınıf — `page.php`'nin genel router'ından geçmez). `mb_ucret` hâlâ public değil; tema yalnız `MaviBelge_Core_Catalog_Service::get_active_fee_results()` çağırır, doğrudan sorgu yapmaz. Aktif dönem boşsa veya eşleşen kayıt yoksa dürüst boş durum gösterilir |
| 33 | `sonuc-belge-sorgulama.html` | Sonuç ve Belge Sorgulama | `/sonuc-belge-sorgulama/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | Yalnız `the_content()` |
| 34 | `sinav-surecleri.html` | Sınav Süreçleri | `/sinav-surecleri/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | " |
| 35 | `sinav-talepleri.html` | Sınav Talepleri | `/sinav-talepleri/` | `page` | `page.php` (layout: form-disabled) | temel kabuk (bilinçli devre dışı) | Faz 8 | Gerçek `<form>` yok, madde 30 ile aynı yaklaşım |
| 36 | `banka-hesap-bilgileri.html` | Banka Hesap Bilgileri | `/banka-hesap-bilgileri/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | Hassas/değişebilir banka bilgisi PHP'ye **gömülmedi**; yalnız `the_content()` |
| 37 | `itiraz-sikayet.html` | İtiraz ve Şikayet | `/itiraz-sikayet/` | `page` | `page.php` (layout: form-disabled) | temel kabuk (bilinçli devre dışı) | Faz 8 | Madde 30 ile aynı yaklaşım |
| 38 | `iletisim.html` | İletişim | `/iletisim/` | `page` | `page.php` (layout: form-disabled) → `template-parts/page/content-contact.php` (Faz 12g) | referans düzeni (kahraman + lokasyonlar + sosyal medya + Bize Yazın) | Faz 12g | Kahraman `inc/page-layouts.php`; lokasyonlar `mavibelge_contact_locations()` (yayında+aktif `mb_lokasyon` DTO; yoksa footer ile ORTAK doğrulanmış yedek `inc/contact-helpers.php`); sosyal bağlantılar footer ile ortak `components/social-links.php`; form yalnız `gate()` açıkken `forms/form.php` (Ad Soyad + Telefon aynı satır); kapalıyken `<form>` yok |
| 39 | `is-basvurusu.html` | İş Başvurusu | `/is-basvurusu/` | `page` | `page.php` (layout: form-disabled) | temel kabuk (bilinçli devre dışı) | Faz 8 | Madde 30 ile aynı yaklaşım; CV yükleme alanı yalnız etiket olarak listelenir |
| 40 | `gizlilik-politikasi.html` | Gizlilik Politikası | `/gizlilik-politikasi/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | `footer.php` zaten bu yola bağlanıyor (Faz 3) |
| 41 | `kvkk.html` | KVKK Aydınlatma Metni | `/kvkk/` | `page` | `page.php` (layout: default) | gelecek faz bağımlı | Faz 6 | `footer.php` zaten bu yola bağlanıyor (Faz 3) |

## Sayım doğrulaması

41 satır = front page (1) + 404 (1) + hub (3) + default/page.php prose (21) + form-disabled (5) + CPT archive (3: meslekler/haberler/dokumanlar) + CPT taxonomy (2: sektor/duyurular) + CPT single (2: yeterlilik/haber-detay) + CPT-backed page (3: referanslar/sss/sinav-ucretleri) = 1+1+3+21+5+3+2+2+3 = **41**. `tanitim-site/*.html` dosya sayısı `ls tanitim-site/*.html | wc -l` ile de 41 olarak doğrulandı. (Faz 5'te `sinav-ucretleri.html`, "default/page.php prose" (22→21) grubundan "CPT-backed page" (2→3) grubuna taşındı — bkz. madde 32.)

## Faz 7/8 güncellemesi
`haberler`, `dokumanlar`, `referanslar`, `sss`, ana sayfa haber/referans şeritleri, tekil şablonlar ve `iletisim` lokasyon bölümü artık `MaviBelge_Core_Content_Service` üzerinden beslenir.
Beş form sayfası, kurum kararları girilip kapı açılınca gerçek forma dönüşür (`institution-decisions.md`); aksi hâlde `<form>` çizilmez. 41/41 eşleme render kanıtı: `docs/qa-report.md`.

## Faz 12 notu — bu harita artık import manifestinin çapraz kontrol kaynağıdır
32 `page` kaydı (3 hub + 21 içerik + 5 form + 3 CPT-verili) `data/content/pages.manifest.json` ile içe aktarılır (taslak). Bu haritadaki slug/şablon/düzen bilgisi `tools/import/verify-page-manifest.js` tarafından envanterle karşılaştırılır; uyumsuzluk kapıyı düşürür. Harita değişmedi.

## Faz 13 güncellemesi — referans sayfa aileleri (tema 0.6.8)
Yukarıdaki tablo satırları (slug, başlık, layout) `tools/import/lib/page-inventory.js` tarafından okunur ve DEĞİŞMEDİ. Sunum artık tek merkezi kayıttan gelir:

- Kayıt: `inc/page-layouts.php` → `mavibelge_page_presentation( $slug )` (sayfalar) ve `mavibelge_archive_presentation( $key )` (arşiv/taksonomi). Kahraman: `mavibelge_page_hero_args()` / `mavibelge_archive_hero_args()` (`inc/presentation-helpers.php`) — kırıntı Anasayfa'dan, eyebrow = menü grubu, lead = WordPress sayfa özeti (yoksa kayıt metni), H1 = WordPress başlığı.
- Gövde: `template-parts/page/content-presentation.php` (tek ortak parça). Aileler: `prose` (myk, turkak), `list` (sinav-surecleri numaralı, mevzuat madde), `card` (banka-hesap-bilgileri h4→kart, sinav-takvimi, sonuc-belge-sorgulama), `cards` (yetki-akreditasyon 2 sütun + MYK/TÜRKAK logosu, belge-yenileme 3 sütun + "Süreç" listesi), `steps` (nasil-basvururum), `split` (itiraz-sikayet: içerik + form kartı; `content-form-disabled.php` / `content-form-live.php`).
- Genişlik: wide 1280 / prose 820 (myk, turkak, mevzuat) / narrow 760 (banka, sinav-takvimi, sonuc-belge-sorgulama).
- Arşivler: `archive-mb_haber.php`, `archive-mb_dokuman.php`, `taxonomy.php` ortak `page-hero` + `section.mb-archive` (servis sorgusu, süzgeç, sayfalama değişmedi; haber kartları `.news-grid`, dokümanlar `.doc-list`).
- İçerikteki kök-göreli iç bağlantılar (`/slug/`) tüm sayfa gövdelerinde `mavibelge_rendered_content()` ile etkin kalıcı bağlantı yapısına çevrilir (saklanan içerik değişmez).
- Kanıt: `tests/static/page-presentation-contract.test.js`, `tools/runtime-test/page-parity-test.js` (`pages-render.sh`; güzel + `/index.php/` yapısı, headless Chrome 390/1024/1279/1280/1440). Rapor: `raporlar/sayfa-gorsel-uyum-raporu.md`.
