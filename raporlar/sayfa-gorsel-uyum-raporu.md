# Sayfa Görsel Uyum Raporu — Faz 13 (Referans Sayfa Aileleri)

> Tarih: 27 Eylül 2026 · Dal: `claude/reference-page-parity` · Koordinatör: Opus 5.5
> Kaynak önceliği: kullanıcı talimatı → `wordpress-ana-uygulama-plani.md` → `README.md` → `tanitim-site/**` (salt okunur) → tarihsel raporlar.

## 1. Keşif — kanıtlı bulgular (kod yazmadan önce)

| Soru | Bulgu (dosya) |
|---|---|
| Generic sayfalar | `page.php` → `mavibelge_page_layout_for_slug()` (`inc/page-layouts.php`): `hub` / `form-disabled` / `default`. 14 hedefin 11'i `page`: 10'u `default` → `template-parts/page/content-default.php` (tek `section-tight` + satır içi `max-width:820px` + `the_content()`), `itiraz-sikayet` `form-disabled`. |
| Özel şablon | Yok (bu 11 sayfa için). `page-referanslar.php`, `page-sss.php`, `page-sinav-ucretleri.php` kapsam dışı. |
| Arşiv / taksonomi | `/haberler/` = `archive-mb_haber.php`, `/dokumanlar/` = `archive-mb_dokuman.php`, `/haber-turu/duyuru/` = `taxonomy.php` (`mb_haber_turu`). Üçünde de **page-hero YOK**: kırıntı + `section-heading` (H1) aynı `container section-tight` içinde; taksonomide kırıntı da yok. Doküman listesi satır içi `style="display:grid;gap:16px"`. |
| Kahraman | `template-parts/page/page-hero.php` ortak; eyebrow/açıklama/üst kırıntı yalnız `iletisim` ve `online-basvuru` için (`mavibelge_page_hero_for_slug`). Diğer sayfalarda kırıntı yalnız "Başlık" (Anasayfa yok), eyebrow/lead yok. |
| İçerik kaynağı | WordPress editörü (`post_content`), `pages` aşamasıyla `data/content/pages.manifest.json`'dan içe aktarılır: sade anlamsal HTML (`h2/h3/h4/p/ol/ul/a`). Statik sarmalayıcı sınıflar (`info-card`, `process-step`, `icon-list`…) **içe aktarılmaz**. Lead için sayfa **özeti** (`post_excerpt`) dolu (banka hariç). |
| Banka bilgisi | `banka-hesap-bilgileri` sayfasının `post_content`'i (Faz 12b onaylı kaynak: canlı sayfa gövdesi, künyeli). Temada banka verisi YOK. Statik sayfadaki banka bilgisi bir demo uyarısıdır ve KULLANILMADI. → Yeni eklenti modeli gerekmez; mevcut yönetilebilir kaynak (sayfa editörü) korunur. |
| İtiraz/şikâyet formu | `mavibelge-core` `MaviBelge_Core_Forms_Service` (`complaint`), kapı `Forms_Config::gate()`; tema `content-form-disabled.php` / `content-form-live.php` + `forms/form.php`. Varsayılan KAPALI. |
| Haber/duyuru/doküman sorguları | `MaviBelge_Core_Content_Service::get_news()` / `get_documents()` (tema `mavibelge_get_news()`/`mavibelge_get_documents()`), taksonomi WordPress ana sorgusu. |
| Süzgeç / sayfalama | `?mb_type=haber|duyuru`, `?mb_cat=<slug>`, `?mb_page=N` (JS'siz bağlantılar); taksonomi `the_posts_pagination()`. |
| Kalıcı bağlantı | `mavibelge_url()` (`inc/urls.php`): arşiv haritası, `duyurular` → terim bağlantısı, sayfa → `get_permalink()`, yoksa etkin permastruct. **Bulgu**: editör içeriğindeki kök-göreli bağlantılar (`/online-basvuru/`, `/yeterlilikler/`…) `/index.php/%postname%/` yapısında 404 olur — içerik değiştirilmeden sunum anında çözülmeli. |
| Varlık derleme | `tools/build/build-theme-assets.js` (`CSS_ORDER`, `JS_ORDER`) → `assets/dist/style.css`, `main.js`; `--check` byte-eşitlik. |
| Sürümler (dosyadan) | Tema `style.css` `Version: 0.6.7`; eklenti `mavibelge-core.php` `Version: 0.5.3`. |
| Ortak dosyalar (yalnız şef) | `functions.php`, `style.css` başlık bloğu, `mavibelge-core.php`, `README.md`, `AGENTS.md`, `raporlar/proje-durumu.md`. `inc/page-layouts.php` tema sahipliğinde, sunum kaydı. |

Statik envanter (Haiku alt ajanı, salt okunur): 14 sayfanın tamamı aynı kahraman kalıbında (`section.page-hero` →
`.breadcrumb` Anasayfa/Üst/Sayfa → `.eyebrow` → `h1` → `p`). Gövdeler: `section.section-tight > .container` ve
genişlik 1280 (varsayılan) / 820 (`myk`, `turkak`, `mevzuat`) / 760 (`banka`, `sinav-takvimi`, `sonuc-belge-sorgulama`).
Bileşenler: `.icon-list` (numaralı/madde), `.process-steps > .process-step`, `.card-grid-2/3 > .info-card`, `.doc-card`,
`.news-grid`, `.filter-bar`, `.form-card`, CTA `.btn.btn-primary`.

## 2. Kök neden

Görsel farkların kaynağı CSS değil **şablon yapısı**:
1. Sunum kaydı yalnız 2 sayfayı biliyordu → 11 sayfada eyebrow/lead/üst kırıntı eksik.
2. `content-default.php` her sayfayı tek bir 820px düz metin bloğu olarak çiziyordu; içerik yapısı (adımlar, kartlar, liste)
   görsel bileşene dönüştürülmüyordu.
3. Arşiv/taksonomi şablonları ortak `page-hero`'yu hiç kullanmıyordu (farklı başlık hiyerarşisi, koyu kahraman yok).
4. İçerikteki kök-göreli iç bağlantılar kalıcı bağlantı yapısından bağımsız yazılmıştı.

## 3. Değerlendirilen seçenekler ve karar

| Seçenek | Artı | Eksi | Karar |
|---|---|---|---|
| 1. Merkezi sunum kaydı + tekrar kullanılabilir gövde aileleri | Tek harita; yeni sayfa = 1 kayıt satırı; içerik editörde kalır; mevcut `page.php` yönlendirmesiyle uyumlu | Bölümleme yardımcısı gerekir | **SEÇİLDİ** |
| 2. Birkaç ortak özel şablon + bileşen | Açık dosya ayrımı | Şablon dosyası sayısı artar; `page.php` yönlendirmesi ile ikili yapı | Reddedildi |
| 3. Yalnız CSS | En az kod | Kahraman/kırıntı/arşiv yapısı CSS ile çözülemez; içerik → bileşen dönüşümü yok | Reddedildi |

**Seçilen mimari:**
- `inc/page-layouts.php` → `mavibelge_page_presentation( $slug )`: aile (`prose|list|card|cards|steps|split`), genişlik,
  eyebrow, üst kırıntılar, yedek lead, bölüm başlık etiketi, sütun, CTA (yalnız `mavibelge_url()` yolu), logo anahtarı.
  `iletisim`/`online-basvuru` aynı haritaya taşındı; `mavibelge_page_hero_for_slug()` delege eder (ikinci harita yok).
- `inc/page-layouts.php` → `mavibelge_archive_presentation( $key )` (`haberler`, `dokumanlar`, `haber-turu`).
- `inc/presentation-helpers.php`: `mavibelge_page_hero_args()` / `mavibelge_archive_hero_args()` (kırıntı Anasayfa'dan;
  lead = WordPress özeti → kayıt yedeği), `mavibelge_split_content_sections()` (editör HTML'ini başlıklardan bölümlere ayırır;
  metin değişmez, başlık atlamasız seviyede yeniden yazılır), `mavibelge_localize_content_links()` (kök-göreli tek bölümlü
  iç bağlantıları `mavibelge_url()` ile etkin yapıya çevirir; saklanan içerik değişmez).
- `template-parts/page/content-presentation.php`: TEK ortak gövde, aileye göre sunum.
- Form sayfası: mevcut `content-form-disabled.php` / `content-form-live.php` `split` düzeni alır (kapı, alanlar, nonce aynı).
- Arşivler: ortak `page-hero` + mevcut servis sorgusu/süzgeç/sayfalama; doküman listesi sınıfa taşındı.

## 4. Aile ↔ rota eşlemesi

| # | Statik referans | WordPress rotası | Tür | Aile | Genişlik | Eyebrow / üst | Lead kaynağı |
|---:|---|---|---|---|---|---|---|
| 1 | `sinav-surecleri.html` | `/sinav-surecleri/` | page | list (numaralı) + CTA | 1280 | Sınav ve Başvuru | özet |
| 2 | `banka-hesap-bilgileri.html` | `/banka-hesap-bilgileri/` | page | card (h4 → kart) | 760 | Sınav ve Başvuru | kayıt yedeği (özet boş) |
| 3 | `yetki-akreditasyon.html` | `/yetki-akreditasyon/` | page | cards (h2 → 2 sütun, logolu) | 1280 | Kurumsal | özet |
| 4 | `haberler.html` | `/haberler/` | CPT arşivi | archive (hero + süzgeç + ızgara + sayfalama) | 1280 | Bilgi Merkezi | kayıt |
| 5 | `duyurular.html` | `/haber-turu/duyuru/` | taksonomi | archive | 1280 | Bilgi Merkezi | terim açıklaması → kayıt |
| 6 | `dokumanlar.html` | `/dokumanlar/` | CPT arşivi | archive (doküman listesi) | 1280 | Bilgi Merkezi | kayıt |
| 7 | `mevzuat.html` | `/mevzuat/` | page | list (madde) | 820 | Bilgi Merkezi | özet |
| 8 | `myk.html` | `/myk/` | page | prose + logo | 820 | Bilgi Merkezi | özet |
| 9 | `turkak.html` | `/turkak/` | page | prose + logo | 820 | Bilgi Merkezi | özet |
| 10 | `nasil-basvururum.html` | `/nasil-basvururum/` | page | steps (h3 → adım) + CTA | 1280 | Sınav ve Başvuru | özet |
| 11 | `sinav-takvimi.html` | `/sinav-takvimi/` | page | card | 760 | Sınav ve Başvuru | özet |
| 12 | `sonuc-belge-sorgulama.html` | `/sonuc-belge-sorgulama/` | page | card | 760 | Sınav ve Başvuru | özet |
| 13 | `itiraz-sikayet.html` | `/itiraz-sikayet/` | page (form) | split (içerik + form) | 1280 | Sınav ve Başvuru | özet |
| 14 | `belge-yenileme.html` | `/belge-yenileme/` | page | cards (h3 → 3 sütun) + kalan metin + CTA | 1280 | Meslekler ve Belgeler | özet |

H1 her zaman WordPress başlığıdır (statik metinle değiştirilmez). Eyebrow ve üst kırıntı gezinme yapısıdır (statik
referans + `inc/menu-fallback.php` ile aynı etiketler). CTA etiketleri statik referanstaki gezinme düğmeleridir; hedef
her zaman `mavibelge_url()`.

## 5. RED kanıtı (uygulamadan önce)

`node wp-content/themes/mavibelge/tests/static/page-presentation-contract.test.js` → **9/77** (68 FAIL), uygulamadan önce.
Gerçek WordPress/Chrome RED kanıtı §6'da.

## 6. Uygulama sonuçları

**Dosyalar (tema):** `inc/page-layouts.php` (kayıtlar), `inc/presentation-helpers.php` (yeni), `inc/bootstrap.php`,
`page.php`, `template-parts/page/content-presentation.php` (yeni), `content-form-disabled.php`, `content-form-live.php`,
`content-default.php`, `content-hub.php`, `content-contact.php`, `content-application.php` (iç bağlantı yerelleştirme),
`archive-mb_haber.php`, `archive-mb_dokuman.php`, `taxonomy.php`, `template-parts/components/card.php` (`media_html`),
`template-parts/content/news-card.php`, `content-card.php`, `assets/src/css/pages.css`, `responsive.css`, `assets/dist/style.css`
(derleme aracıyla), `style.css` sürüm 0.6.8. Eklenti DEĞİŞMEDİ.

**Testler:** `tests/static/page-presentation-contract.test.js` (80), güncellenen `heading-contract.test.js` ve
`application-form-contract.test.js` (H1 artık ortak kahramanda — bilinçli sözleşme değişikliği),
`tools/runtime-test/page-parity-test.js` + `scripts/page-parity-fixtures.php` (`pages-render.sh` içinde; `up/down` kipi eklendi),
`contact-page-test.js` (beş formda form tek kez çizilir + complaint iki sütun kontrolü; 212/212).

| Kanıt | Önce (eski kod) | Sonra |
|---|---|---|
| Statik sözleşme | 9/77 | 80/80 |
| Parite HTTP, güzel yapı | 90/175 | 213/213 |
| Parite HTTP, `/index.php/` | 88/175 | 213/213 |
| Parite tarayıcı (5 genişlik × 14 rota) | 197/293 | 293/293 |
| `run-all-gates.sh --runtime` | — | 56/56 |

**Bağımsız inceleme bulguları ve sonuç:**
- Opus kod incelemesi — orta: (M1) iç bağlantı yerelleştirmesi yalnız 3 gövdedeydi → tüm sayfa gövdeleri `mavibelge_rendered_content()`;
  (M2) metinsiz intro/rest (görsel/iframe) atılıyordu → ham HTML kontrolü; (M3) iç içe başlıkta etiket dengesi bozulabilirdi →
  sarmalayıcı derinliği izlenir, iç içe başlıkta bölümleme yapılmaz. Düşük: başlık `id` korunur, daha derin alt başlık bölümde kalır,
  `data-href`/`wp-admin`/`feed` yerelleştirilmez, boş içerikte split tek sütun, kart düğme stili yalnız dar gövdede, `]]>` kaçışı.
  Hepsi PHP vaka testleriyle (`helper-cases`) doğrulandı. `ol[start]` sayacı desteklenmez (belgelendi).
- Opus form/güvenlik incelemesi — yüksek/orta yok; düşük: form çoğalmasını yakalayan test yoktu → eklendi.
- Sonnet bağımsız tarayıcı QA (kendi betiği, 20 rota × 5 genişlik): 14 rotada WP hatası yok; haber kartlarında öne çıkan görsel
  gösterilmiyordu → düzeltildi ve test edildi. Kapsam dışı notlar: `/sinav-ucretleri/` h1→h3, `/sss/`-`/sinav-ucretleri/` kahramanında
  eyebrow/kırıntı yok (dokunulmadı).

**Bilinçli farklar (statik ↔ WordPress):** haber süzgeci JS'siz bağlantı (statikte arama+seçim, istemci tarafı); statik kart/liste
`div`'leri yerine anlamsal `ol`; statik h1→h3 atlamaları WordPress'te h2 ile düzeltildi; kart görselleri yalnız WordPress öne çıkan
görseli varsa; doküman/haber sayısı gerçek kayıtlara bağlı.
