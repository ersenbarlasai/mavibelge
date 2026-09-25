# Tasarım Sistemi — Faz 3 (Temel Tema)

> Kaynak: `tanitim-site/assets/css/tokens.css`, `base.css`, `components.css` (header/footer/button/badge/form/table/pagination bölümleri), `layout.css`, `responsive.css`; `tanitim-site/index.html` header/footer markup'ı (41 sayfada birebir aynı — `git`/`awk` ile doğrulandı, bkz. teslim raporu). Bu belge kararların kaydıdır; çelişki halinde kod esastır.
>
> Tasarım yönü: **"V1 Görev Odaklı / Desktop 1440 — Düzeltilmiş v2"**. Yeni varyasyon üretilmedi.

## 1. Tasarım tokenları (kaynakla birebir)

`assets/src/css/tokens.css`, `tanitim-site/assets/css/tokens.css` ile **değer bazında birebir** — hiçbir renk/tipografi/aralık/radius/gölge/container/geçiş değeri değiştirilmedi.

| Grup | Değerler |
|---|---|
| Marka renkleri | `--color-navy-950:#0f1b26` · `--color-navy-900:#2b3e50` · `--color-navy-800:#35495d` · `--color-navy-700:#3f5568` · `--color-blue-600:#066aab` · `--color-blue-500:#1f86c9` · `--color-blue-100:#d7ecf9` · `--color-blue-50:#eef7fc` |
| Nötr | `--color-white:#ffffff` · `--color-gray-50:#f4f7f6` · `-100:#eceef2` · `-200:#dde1e8` · `-300:#c3c9d4` · `-500:#6b7385` · `-700:#3b4254` · `-900:#1a1e29` |
| Durum | `--color-success:#1b8a5a` · `--color-warning:#b5760a` · `--color-danger:#c0392b` |
| Tipografi | `--font-sans:"Inter","Segoe UI","Helvetica Neue",Arial,sans-serif` · `--fs-xs:0.8125rem` … `--fs-3xl:2.75rem` (8 kademe) |
| Aralık | `--space-1:4px` … `--space-9:96px` (9 kademe) |
| Radius | `--radius-sm:4px` · `-md:6px` · `-lg:8px` · `-pill:999px` |
| Gölge | `--shadow-sm/md/lg` — `rgba(14,35,71,…)` tabanlı |
| Container/Header | `--container-max:1280px` · `--header-h:128px` (768px altında `64px`'e düşer) |
| Geçiş | `--transition-fast:150ms ease` · `--transition-base:220ms ease` |

## 2. Header — masaüstü / tablet / mobil yapı

```text
Masaüstü (≥1025px)
┌──────────────────────────────────────────────────────────────────┐
│ trust-bar: tel · e-posta · "MYK tarafından yetkilendirilmiş..."   │
├──────────────────────────────────────────────────────────────────┤
│ [logo]   [Ana menü: 5 öğe, 4'ü dropdown]   [MYK] [TÜRKAK]  [CTA]  │
│                                              YB-0052 AB-0104-P     │
└──────────────────────────────────────────────────────────────────┘
  min-height: 96px · sticky top:0 · alt öğeli üst seviye öğe İKİ ayrı
  kontrol üretir: <a class="nav-parent-link" href="..."> (gerçek hedef
  sayfa, hiçbir zaman kaybolmaz) + <button class="nav-toggle"
  aria-controls="submenu-ID"> (yalnız <ul id="submenu-ID" class="submenu">
  açar/kapatır) — hover/focus-within/tıklama ile açılır. Alt öğesi olmayan
  üst seviye öğe tek bir <a> kalır. Derinlik sözleşmesi: yalnız 2 seviye
  (üst seviye + 1 alt seviye) desteklenir — wp_nav_menu() 'depth' => 2 ile
  çağrılır (bkz. header.php), 3. seviye WordPress tarafından hiç
  render'a gönderilmez (sessizce bozuk HTML değil, hiç üretilmeyen HTML).
  Düzeltme kaydı: Faz 3 Düzeltme ve Kabul, class-nav-walker.php.

  **Faz 3 İkinci Kabul Düzeltmesi (nitelik sözleşmesi):** `title`/`target`/
  `rel`/`href`/`aria-current` değerleri gerçek `$item->attr_title`/
  `$item->target`/`$item->xfn`/`$item->url`/`$item->current` alanlarından
  **tek bir** `$atts` dizisine kuruluyor, bu dizi `nav_menu_link_attributes`
  filtresinden **bir kez** geçiyor ve son HTML yalnız o filtrenin döndürdüğü
  değerlerden üretiliyor — `aria-current` bu nedenle asla iki kez basılamaz.
  `nav_menu_item_title` filtresi başlığa uygulanıyor. `nav_menu_css_class` ve
  `nav_menu_item_id` korunuyor.

  **Faz 3 Walker Sözleşmesi Kapanışı (`walker_nav_menu_start_el` sınırı):**
  Bir önceki turda `walker_nav_menu_start_el` filtresine yanlışlıkla `<li>`
  açılış/kapanışını da içeren tam öğe çıktısı veriliyordu; gerçek WordPress
  çekirdek sözleşmesinde (`wp-includes/class-walker-nav-menu.php`,
  `Walker_Nav_Menu::start_el()`) `<li id="..." class="...">` filtre
  ÖNCESİNDE doğrudan `$output`'a yazılır, filtre yalnız öğe-içi `<a>`/ikon
  içeriğini görür, ve çağrı gerçekte **4 argümanlıdır**
  (`$item_output, $item, $depth, $args` — `$id`/`$current_object_id` yok).
  Bu düzeltmede: `<li>` doğrudan `$output`'a taşındı, `$item_output`
  yalnız link+toggle içeriğini tutuyor, filtre çağrısı 4 argümana indirildi,
  `</li>` artık `end_el()`'de her derinlikte (0 ve 1) koşulsuz ekleniyor
  (önceden yalnız derinlik 0'da ekleniyor, derinlik 1'de `start_el()` içinde
  erken kapanıyordu). Ayrıca `nav_menu_item_args` filtresi eklendi ve
  sonucu (`$args`) sonraki tüm filtrelere (`nav_menu_css_class`,
  `nav_menu_item_id`, `nav_menu_link_attributes`, `nav_menu_item_title`,
  `walker_nav_menu_start_el`) aktarılıyor. Statik kaynak-metin sözleşme
  testi: `tests/static/nav-walker-filter-contract.test.js`.

  **Mobil odak tuzağı (navigation.js):** Toggle düğmesi gerçek DOM'da
  `nav#main-nav`'dan SONRA geliyor (header.php sırası korunuyor — onaylı
  görsel tasarım değişmedi). Mantıksal döngü `toggle → ilk nav öğesi → ... →
  son nav öğesi → toggle` olduğundan ve bu gerçek DOM sırasıyla örtüşmediğinden,
  dört sınır geçişinin tamamı (`resolveTrapFocusTarget()`) JS'te açıkça
  yönetiliyor; nav öğeleri arası hareket tarayıcının doğal Tab sırasına
  bırakılıyor (onlar DOM'da bitişik). `Escape` artık TEK bir doküman
  seviyeli dinleyiciden yürüyor (önce açık alt menü varsa yalnız onu kapatır,
  yoksa mobil menüyü kapatır) — eski iki-dinleyici yarışı kaldırıldı.

Tablet (769–1024px)
  Aynı yapı, daralmış boşluk/yazı boyutu (header-main min-height:84px,
  trust-logo-item img height:56px).

Mobil (≤768px)
┌──────────────────────────┐
│ [☰] [logo]      [MYK][TR]│  ← header-main flex-wrap
├──────────────────────────┤
│ (tıklanınca) Ana menü     │  ← .main-nav.is-open, dikey liste
│ + "Online Başvuru" CTA    │  ← yalnız mobil menü içinde (mobile-header-cta)
└──────────────────────────┘
  header-cta (masaüstü CTA) mobilde gizli (display:none) — CTA mükerrer
  görünmez, yalnız mobil menü içindeki mobile-header-cta kalır.
```

## 3. Footer — masaüstü / mobil yapı

```text
Masaüstü (≥1025px)
┌───────────┬──────────────┬───────────┬──────────────┐
│ Marka +   │ Hızlı        │ Kurumsal  │ İletişim +   │
│ açıklama  │ Bağlantılar  │           │ sosyal medya │
└───────────┴──────────────┴───────────┴──────────────┘
  footer-grid: 1.4fr 1fr 1fr 1fr
┌──────────────────────────────────────────────────────┐
│ Lokasyon kartları (auto-fit, min 210px)                │
├──────────────────────────────────────────────────────┤
│ © yıl · KVKK/Gizlilik/Çerez                            │
└──────────────────────────────────────────────────────┘

Mobil (≤768px)
  footer-grid: 1fr (dikey yığın) · footer-bottom: dikey
```

## 4. Ayırt edici imza

- Kurumsal lacivert/mavi (`navy-950`→`blue-500`) düzen; nötr beyaz/açık gri zeminler.
- Görev odaklı hiyerarşi: header her zaman "ne yapmak istiyorsun" eylemlerine (Online Başvuru CTA) öncelik verir.
- Sağda özgün MYK/TÜRKAK güven bloğu — logo + akreditasyon numarası (`YB-0052` / `AB-0104-P`), açıklama metni **yok** (kaldırılmış metinler geri eklenmedi).
- Header `position: sticky` — statik referansta zaten böyle, Faz 3'te aynen korundu (brief §6.1: "Sabit/sticky davranış yalnız statik referansta varsa aynı şekilde uygulanır" — referansta var, uygulandı).

## 5. Statik tasarımdan sapmayan/sapan noktalar

**Sapmayan (birebir):** tüm token değerleri, header/footer görsel yapısı, buton/badge/tablo/form/pagination stilleri, breakpoint eşikleri (1024/768/480).

**Bilinçli, dar kapsamlı sapmalar (belgelenmiş):**

1. **`.alert` / `.empty-state` bileşeni yeni** — statik sitede jenerik bir uyarı/boş durum bileşeni yoktu (yalnız dağınık `.form-success`/`.field-error` desenleri vardı). Faz 3 kataloğunun istediği bu bileşen, mevcut tokenlardan inşa edildi; renk/radius/gölge kuralları diğer bileşenlerle tutarlıdır. Bkz. `component-catalog.md`.
2. **Klavye erişilebilirliği eklentileri** — `:focus-visible` zaten vardı; yeni eklenenler: dropdown içi ok tuşu gezinmesi, mobil menüde odak tuzağı + `body.mavibelge-nav-open` scroll kilidi, kapanışta odağın açan düğmeye dönmesi. Bunlar CSS/JS davranış eklemeleridir, **görsel** hiçbir token/ölçü değişmedi.
3. **`prefers-reduced-motion`**e back-to-top'un smooth-scroll'u JS tarafında da koşullu hale getirildi (CSS'teki `animation-duration`/`transition-duration` sıfırlama zaten statik sitede vardı; `scrollTo({behavior})` JS çağrısı CSS'ten etkilenmediği için ayrıca JS'te kontrol edildi).
4. **Footer menü konumu ikiye bölündü** (`footer_quick` + `footer_kurumsal`, eski tek `footer` konumu yerine) — statik footer'ın iki ayrı başlıklı listesini (Hızlı Bağlantılar / Kurumsal) tek düz WordPress menüsüne sıkıştırmak görsel/yapısal sadakati bozardı. `inc/setup.php`'de Türkçe etiketlerle kayıtlı.

**Faz 3'te bilinçli olarak Faz 4'e ertelenmiş, Faz 4'te tamamlanmış olanlar:** hero, page-hero, CTA band, task/sector/news/qual kart gridleri, tüm 41 sayfanın şablonları ve içerik bölümleri. Bu görev (Faz 3 — Temel Tema) bunların CSS'ini **taşımadı** — `assets/src/css/layout.css` o turda bilinçli olarak minimal bırakılmıştı. Faz 4 (Sayfa Şablonları), bu sunum CSS'inin büyük kısmını gerçekten ekledi: hero/page-hero, kart gridleri, sayaç bandı ve sayfa şablonu düzenleri `assets/src/css/pages.css` içine, bunların responsive kırılımları `assets/src/css/responsive.css` içine eklendi (bkz. §7).

**Hâlâ sonraki fazlara ait (Faz 4'te de tamamlanmadı):** sidebar layout (`layout-with-sidebar`/`side-nav`/`filter-bar`) ve ücret tablosu sayfaya özel varyantları — gelişmiş meslek/sektör/ücret filtre ve eşleştirme mantığı **Faz 5**'in kapsamıdır; referans slider ve arama kutusu gelişmiş davranışı (`search.js`/`filters.js`) de Faz 4 brief'i gereği bilinçli olarak eklenmedi (bkz. `docs/template-architecture.md` §9), aynı şekilde Faz 5'e bırakılmıştır. Gerçek içerik verisi (yeterlilik/ücret/haber/referans) importu **Faz 6**, lokasyon/SSS/haber özel sorgu servisleri **Faz 7**, gerçek form gönderimi **Faz 8**, meta/canonical/OG/JSON-LD/sitemap **Faz 9**'dur — bu dört faz henüz başlamamıştır.

## 6. Font kararı

Harici Google Fonts/CDN isteği **eklenmedi**, font dosyası **indirilmedi/uydurulmadı**. Onaylı stack aynen korundu:

```css
--font-sans: "Inter", "Segoe UI", "Helvetica Neue", Arial, sans-serif;
```

**Açık nokta:** Depoda yerel bir Inter `.woff2` dosyası yok (statik sitede de yoktu — `wordpress-faz0-depo-envanteri.md` §6.4'te zaten kayıtlı: site fiilen sistem fontlarıyla render ediliyor, "Inter" yalnız yüklü sistemlerde uygulanıyor). Bu fazda da aynı durum korunur; yerel Inter gömme kararı (varsa) ayrı bir gelecek görev.

## 7. CSS mimarisi — kaynak/dist senkronizasyonu

```text
assets/src/css/
  tokens.css      — token'lar (§1)
  base.css        — reset, tipografi, container, skip-link, focus, section ritmi
  layout.css      — YALNIZ jenerik grid yardımcıları (card-grid-2/3); sayfa
                    düzeni Faz 4'te pages.css'e eklendi (aşağıya bakın)
  components.css  — buton, badge, breadcrumb, tablo, form alanı, pagination,
                    alert/empty-state (bkz. §5.1)
  pages.css       — Faz 4'te eklendi: hero, page-hero, task/sector/news/
                    qual kart gridleri, sayaç bandı (impact-stats), hub/
                    form-disabled sayfa düzenleri ve 41 sayfa şablonunun
                    kullandığı diğer sayfa-özel sunum kuralları
  header.css      — trust-bar, site-header, main-nav, trust-group, menu-toggle
  footer.css       — site-footer, footer-grid, back-to-top
  responsive.css  — header/footer/bileşen medya kuralları (1024/768/480) VE
                    Faz 4'te eklenen sayfa/grid kırılımları (hero, task/
                    sector/news/ref grid, impact-stats-grid, süreç adımları,
                    layout-with-sidebar vb.) — tek dosyada birlikte tutulur
  editor.css      — Gutenberg içerik alanı için minimal tipografi eşleşmesi
                    (add_editor_style() ile ayrı yüklenir, dist paketine GİRMEZ)

assets/dist/
  style.css       — tokens+base+layout+components+pages+header+footer+
                    responsive'in SIRALI birleşimi (editor.css hariç)
  main.js         — navigation+components+main'in sıralı birleşimi
```

**Senkronizasyon yöntemi (bu fazda):** `dist/*` dosyaları, `src/*` dosyalarının basit `cat` (sıralı birleştirme) ile üretildiği **statik, elle üretilmiş** çıktılardır — çalışma zamanında Node/derleme aracı **gerekmez**. Yeni bir npm bağımlılığı veya build framework eklenmedi; `package.json`'daki `build:css`/`build:js` script yer tutucuları bu fazda **değiştirilmedi** (kapsam dışı dosya — bkz. görev izinleri). `src/*` dosyalarından biri değiştirildiğinde `dist/*` dosyası **elle yeniden üretilmelidir** (aynı `cat` sırasıyla) — bu manuel adım, sonraki bir fazda gerçek bir build script'ine (muhtemelen `package.json`'a bağlanarak) dönüştürülebilir; bu görevde o adım atılmadı.

CSS seçici hijyeni: her dosya kendi bileşen/bölge önekine sahip sınıflar kullanır (`.site-header`, `.site-footer`, `.btn-*`, `.form-field`, vb.); global element seçicisi yalnız `base.css`'te (html/body/a/h1-h4/p/ul/button gibi gerçekten evrensel öğeler) tanımlıdır. Specificity çakışması riski düşük — hiçbir seçici `!important` kullanmaz (tek istisna: `prefers-reduced-motion` altındaki animasyon sıfırlama, statik kaynaktan aynen taşındı).

## 8. Erişilebilirlik/hareket davranışları

- `prefers-reduced-motion: reduce` → `html{scroll-behavior:auto}` + tüm animasyon/geçiş süreleri `0.01ms`'e düşer (statik kaynaktan aynen).
- `:focus-visible` global outline; dropdown submenu linkleri için ayrıca eklendi (§5.2).
- `forced-colors: active` (yüksek kontrast modu) için buton/badge/tablo/form alanlarına `CanvasText` kenarlık — **yeni**, statik sitede yoktu, WCAG 2.2 AA hedefine katkı için eklendi.
- `@media print` — header/footer/back-to-top/skip-link/menu-toggle gizlenir — **yeni**.
