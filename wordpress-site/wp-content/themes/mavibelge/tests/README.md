# Tema testleri — Faz 3 (üç düzeltme turu) + Faz 4 (Sayfa Şablonları)

Bu klasör, bu turlarda eklenen **statik mantık testlerini** içerir.
Hiçbiri gerçek bir WordPress/tarayıcı ortamı çalıştırmaz — aşağıda hangisinin
ne kanıtladığı ve neyin **açık kaldığı** ayrı ayrı yazılmıştır.

## Neler çalıştırıldı (bu ortamda, gerçekten)

- `node tests/js/nav-active-page.test.js` — `markActivePage()` içindeki
  `normalizePath()` algoritmasının (trailing-slash düzeltmesi) saf mantık
  testi. DOM/`location` kullanmaz, algoritmayı izole biçimde yeniden
  uygular. **Sonuç: 6/6 geçti.**
- `node tests/js/mobile-focus-trap.test.js` — **gerçek**
  `assets/src/js/navigation.js` dosyasını minimal bir `document`/`window`
  taslağıyla Node'da `require()` eder (kopya mantık değil) ve dosyanın
  kendi ihraç ettiği `window.__mavibelgeNavTestHooks__.resolveTrapFocusTarget`
  ile `normalizePath` fonksiyonlarını gerçek kaynaktan çağırır. Mobil odak
  tuzağının 4 zorunlu sınır geçişini (Tab/Shift+Tab × toggle/ilk-öğe/son-öğe)
  ve ilgisiz bir odak hedefinde müdahale edilmediğini doğrular. **Sonuç:
  8/8 kontrol geçti.** Taslak `document.readyState = 'loading'` bıraktığı
  için `initDropdowns()`/`initMobileMenu()` gerçekten çağrılmaz — yalnız
  saf karar fonksiyonu test edilir, tam DOM click/focus akışı değil (bkz.
  aşağıdaki "çalıştırılamadı" listesi).
- `node --check assets/src/js/navigation.js` ve
  `node --check assets/dist/main.js` — kaynak ve birleştirilmiş JS
  paketinin sözdizimi doğrulaması. **Sonuç: ikisi de geçti.**
- `assets/dist/main.js`/`assets/dist/style.css` senkron kanıtı: banner +
  `src/**` dosyaları yeniden `cat` ile birleştirilip geçici dosyaya yazıldı,
  ardından mevcut `dist/*` ile **byte-eşit** olduğu `diff` ile doğrulandı
  (yalnız `grep -c` sayımı değil — tam içerik karşılaştırması).
- `node tests/static/nav-walker-filter-contract.test.js` — **yeni**
  (Faz 3 Walker Sözleşmesi Kapanışı). Gerçek `inc/class-nav-walker.php`
  kaynak metnini okuyup regex tabanlı yapısal kontrollerle doğrular:
  `<li` yalnız `$output`'a ekleniyor (`$item_output`'a değil),
  `walker_nav_menu_start_el` tam 4 argümanla çağrılıyor (`$id` yok),
  `end_el()` her derinlikte koşulsuz `</li>` ekliyor, `start_el()` kendisi
  hiç `</li>` eklemiyor, `nav_menu_item_args` filtresi uygulanıp `$args`'a
  atanıyor. **Sonuç: 7/7 kontrol geçti.** Bu bir metin/regex taraması —
  gerçek bir PHP ayrıştırıcı veya `Walker_Nav_Menu` çalıştırması değil.
- Statik metin taramaları (bu görev raporunda ayrıca listelenmiştir):
  PHP 7.4+/8.x'e özgü sözdizim taraması (`?->`, `fn(`, `match(`, `readonly`,
  `enum`, `??=`), `href="#"`/`javascript:`/harici `http://` taraması,
  4 logo dosyasının (favicon dahil) SHA-256 karşılaştırması, `wp_head`/
  `wp_body_open`/`wp_footer`/tek `#main` sözleşmesi kontrolü,
  `git diff --check`.

## Neler çalıştırılAMADI (bu ortamda PHP/WordPress runtime yok)

- `tests/php/button-attrs.test.php` — **yazıldı, ÇALIŞTIRILMADI.** Bu
  ortamda `php` komutu bulunamadı (`command -v php` boş döndü). Dosya
  gerçek `button.php`'yi WP escaping fonksiyonlarının minimal taslaklarıyla
  `include` edip çıktısını doğrulayacak şekilde yazılmıştır; `php` mevcut
  olduğunda `php tests/php/button-attrs.test.php` ile çalıştırılabilir.
  "disabled=>false hiç nitelik üretmez", "disabled=>true yalnız çıplak
  disabled üretir", "array değer sızmaz", "on*/style/formaction reddedilir",
  "geçersiz variant primary'ye düşer" iddiaları bu testte kodludur ama
  **doğrulanmış değildir** — yalnız statik kod okumasıyla mantık kontrol
  edilmiştir.
- `class-nav-walker.php`'nin gerçek `Walker_Nav_Menu` üzerinden gerçek bir
  WordPress menüsüyle render edilmesi (title/target/rel/aria-current'ın
  gerçek `$item` nesnesinden gerçekten okunduğu, `nav_menu_item_args`/
  `nav_menu_link_attributes`/`walker_nav_menu_start_el` filtrelerinin
  gerçekten tetiklendiği, bir eklentinin `walker_nav_menu_start_el`'e
  taktığı kancanın gerçekten yalnız link+toggle içeriğini görüp `<li>`
  sınırını görmediği) — bu PHP + WordPress çekirdeği gerektirir, bu
  ortamda yok. `tests/static/nav-walker-filter-contract.test.js` bunun
  yerine geçmez, yalnız kaynak metnini tarar.
- `card.php`/`form-field.php`/`pagination.php` bileşenlerinin gerçek
  WordPress fonksiyonlarıyla render edilip DOM'da doğrulanması.
- Gerçek tarayıcıda: mobil menü focus trap'inin gerçek Tab/Shift+Tab ile
  denenmesi (bu turda yalnız karar fonksiyonu test edildi, gerçek
  `document.activeElement`/`focus()` akışı değil), `matchMedia` resize
  senaryosu, aXe/Lighthouse erişilebilirlik taraması, 1024/768/480px
  viewport görsel kontrolü.

Bu maddeler açık kalite kapısı olarak kalır — bkz.
`raporlar/proje-durumu.md` ve `docs/design-system.md`. Bu düzeltme
turları için "WordPress'te çalıştı", "tam erişilebilir" veya "Faz 3 nihai
kabul edildi" iddiası edilmemiştir.

## Faz 4 (Sayfa Şablonları) — çalıştırılanlar ve çalıştırılamayanlar

Bu ortamda `php` hâlâ yok (bu turda da yeniden doğrulandı: `command -v php`
boş döndü, Docker daemon da çalışmıyordu — bir PHP 7.3 konteyneriyle
`php -l` denenemedi). Bu nedenle **gerçekten çalıştırılabilen** şey yine
statik taramalarla sınırlı:

- Tüm yeni Faz 4 PHP dosyalarında (`front-page.php`, `page.php`,
  `404.php`, `search.php`, `archive.php`, `single.php`, 10 CPT
  `archive-*`/`single-*`/`taxonomy-*` dosyası, `page-referanslar.php`,
  `page-sss.php`, `inc/page-layouts.php`, `inc/form-shells.php`, tüm yeni
  `template-parts/**`) `grep` tabanlı taramalar: PHP 7.4+/8.x yasak
  sözdizimi (`?->`, `fn(`, `match(`, `readonly`, `enum`, `??=`) — **temiz**;
  `href="#"`/`javascript:`/harici `http://` — **temiz**; `ABSPATH` guard'ı
  her dosyada mevcut; `query_posts()`/doğrudan `$wpdb`/`eval`/`exec` —
  **yok**; her `new WP_Query()` çağrısının aynı dosyada bir
  `wp_reset_postdata()` çağrısı var — **doğrulandı**; fonksiyon adı
  çakışması yok.
- `assets/dist/style.css` yeniden üretildi (yeni `pages.css` eklendiği
  için sıraya dahil edildi: tokens, base, layout, components, pages,
  header, footer, responsive) ve fresh-concat ile **byte-eşit** olduğu
  `diff` ile iki kez doğrulandı; CSS `{`/`}` sayımı dengeli (350/350).
  `assets/dist/main.js` bu turda **değişmedi** — yine byte-eşit olduğu
  doğrulandı.
- Faz 3'ten kalan tüm JS testleri (`nav-active-page.test.js`,
  `mobile-focus-trap.test.js`, `nav-walker-filter-contract.test.js`)
  yeniden çalıştırıldı, hepsi geçti — bu turda hiçbir JS kaynağı
  değişmedi.
- 4 logo + 2 yeni hero görseli (`hero-home.png`, `hero-generic.svg`)
  kaynak/hedef SHA-256 ile **6/6** doğrulandı.

**Çalıştırılamayanlar (Faz 4):**

- Hiçbir yeni PHP şablonu gerçek WordPress üzerinde render edilmedi —
  `front-page.php`/`page.php`/CPT şablonlarının gerçekten doğru HTML
  ürettiği, `WP_Query`'lerin gerçek verilerle beklenen sırayı verdiği,
  `get_template_part()` çağrılarının gerçekten doğru dosyayı bulduğu
  **doğrulanmadı** — yalnız statik kod okumasıyla kontrol edildi.
  `get_search_form()`, `wp_get_attachment_image()`,
  `get_post_type_archive_link()`, `get_term_link()` gibi çekirdek
  fonksiyonların gerçek çıktısı görülmedi.
  Gerçek tarayıcıda: 375/390/768/1024/1440 px taşma kontrolü, klavye
  gezinme, aXe/Lighthouse, konsol/network hatası kontrolü. "41 sayfa
  çalışıyor", "responsive geçti" veya "WordPress'te çalıştı"
  iddia edilmemiştir.

## Faz 4 Nihai Kabul Düzeltmesi — çalıştırılanlar ve çalıştırılamayanlar

Bu turda da `php`/WordPress/tarayıcı ortamı yok; yine yalnız statik
kontroller gerçekten çalıştırıldı:

- **Yeni** `tests/static/heading-contract.test.js` — `node
  tests/static/heading-contract.test.js` ile çalıştırıldı, **geçti**.
  Gerçek kaynak metnini okuyup: `section-heading.php`'in level 1'i kabul
  ettiğini ve varsayılanı 2'de tuttuğunu, yedi sayfa şablonunun (`archive.php`,
  `archive-mb_yeterlilik.php`, `archive-mb_haber.php`,
  `archive-mb_dokuman.php`, `taxonomy.php`, `taxonomy-mb_sektor.php`,
  `search.php`) sayfa başlığını `'level' => 1` ile çağırdığını,
  `index.php`'in döngü başlıklarının `<h2>` olduğunu ve ayrı bir sayfa
  düzeyi `<h1>` ürettiğini, `template-parts/home/**` bölüm başlıklarının
  yanlışlıkla `level => 1`'e çevrilmediğini doğrular. Bu bir metin/regex
  taraması — gerçek bir PHP ayrıştırıcı veya WordPress `get_template_part()`
  çalıştırması değil.
- Faz 3/4'ten kalan dört JS/statik test (`nav-active-page.test.js`,
  `mobile-focus-trap.test.js`, `nav-walker-filter-contract.test.js`) yeniden
  çalıştırıldı — **4/4 geçti**, bu turda JS kaynağı değişmedi.
- `node --check assets/src/js/navigation.js` ve
  `node --check assets/dist/main.js` — **geçti** (JS içeriği değişmedi).
- PHP 7.4+/8.x yasak sözdizimi (`?->`, `fn(`, `match(`, `readonly`, `enum`,
  `str_contains`, `??=`), `href="#"`/`javascript:`/harici `http://`,
  `query_posts`/doğrudan `$wpdb`/`eval`/`exec` taraması bu turda değişen tüm
  dosyalarda tekrarlandı — **temiz**.
- `get_term_link()` kullanılan iki döngüde (`template-parts/home/sector-grid.php`,
  `archive-mb_yeterlilik.php`) artık `is_wp_error()` kapısı olduğu kaynak
  okumasıyla doğrulandı.
- `mavibelge_resolve_hub_link_url()` artık yalnız `inc/page-layouts.php`
  içinde tanımlı olduğu `grep -RnF "function mavibelge_resolve_hub_link_url"`
  ile doğrulandı (tek eşleşme); `inc/bootstrap.php`'nin bu dosyayı
  `require_once` ile tek sefer yüklediği doğrulandı.
- `assets/dist/style.css` yeniden üretildi (yalnız `pages.css`/`responsive.css`
  içindeki `.impact-stats-grid` kural değişiklikleri) ve belgelenmiş sırayla
  (`tokens, base, layout, components, pages, header, footer, responsive`)
  taze `cat` birleştirmesiyle **byte-eşit** olduğu `diff` ile iki kez
  doğrulandı. `assets/dist/main.js` bu turda **değişmedi**.
- `git diff --check` — temiz (yalnız README.md için önceden var olan
  LF/CRLF bilgi uyarısı, bu turun bir sonucu değil).

**Çalıştırılamayanlar (bu turda da):** Yukarıdaki Faz 4 bölümündeki tüm
gerçek WordPress/tarayıcı kontrolleri (render, `WP_Query` gerçek sonuçları,
375/390/768/1024/1440 px taşma, klavye, aXe/Lighthouse, konsol/network)
hâlâ açık — bu turda da çalıştırılmadı, "geçti" denmedi.

## Faz 5 — Meslek, Sektör ve Ücret Deneyimi

Bu turda da `php`/WordPress/tarayıcı yok (Docker da denendi, daemon
çalışmıyordu). Gerçekten çalıştırılanlar:

- **Yeni** `tests/static/catalog-contract.test.js` — `node
  tests/static/catalog-contract.test.js` ile çalıştırıldı, **geçti**
  (6 kontrol grubu). Gerçek kaynak metnini okuyup: katalog şablonlarının
  (`archive-mb_yeterlilik.php`, `taxonomy-mb_sektor.php`,
  `single-mb_yeterlilik.php`, `page-sinav-ucretleri.php`) `$wpdb`
  kullanmadığını ve kendi `WP_Query`'sini kurmadığını, en az bir
  `mavibelge_*` katalog yardımcısını çağırdığını; `inc/catalog-helpers.php`
  içindeki her servis çağrısının `class_exists()` ile korunduğunu;
  `class-catalog-query.php`'nin `normalize_filters()` metodunun tam
  olarak 5 belgelenmiş GET anahtarını (`mb_q`/`mb_sector`/`mb_level`/
  `mb_priced`/`mb_page`) okuduğunu; `filter-form.php`'nin dört alanı
  (`mb_page` hariç, kasıtlı) gerçek form kontrolü olarak ürettiğini;
  yeni `get_term_link()` çağrılarının `is_wp_error()` ile korunduğunu;
  `fee-table.php`/`fee-cards.php` çiftinin CSS'te karşılıklı
  `display:none`/`display:flex` ile korunduğunu; `mavibelge-core.php`'nin
  iki yeni sınıfı doğru sırayla (`class-catalog-query.php` önce)
  yüklediğini doğrular. Bu bir metin/regex taraması — gerçek bir PHP
  ayrıştırıcı veya WordPress çalıştırması değil.
- `tests/static/heading-contract.test.js` yeniden çalıştırıldı — **geçti**
  (Faz 5'te değişen `archive-mb_yeterlilik.php`/`taxonomy-mb_sektor.php`
  hâlâ `'level' => 1` ile tek sayfa H1'i üretiyor).
- Faz 3/4'ten kalan diğer üç JS/statik test (`nav-active-page.test.js`,
  `mobile-focus-trap.test.js`, `nav-walker-filter-contract.test.js`)
  yeniden çalıştırıldı — **5/5 (toplam) geçti**, JS kaynağı bu turda
  değişmedi.
- `node --check assets/src/js/navigation.js` ve
  `node --check assets/dist/main.js` — **geçti** (JS içeriği değişmedi;
  Faz 5 yeni JS eklemedi — brief §6.6 gereği).
- PHP 7.4+/8.x yasak sözdizimi (`?->`, `fn(`, `match(`, `readonly`, `enum`,
  `str_contains`, `??=`), `href="#"`/`javascript:`/harici `http://`,
  `query_posts`/gerçek `$wpdb->` kullanımı/`eval`/`exec` taraması bu
  turda değişen/yeni tüm tema ve eklenti dosyalarında yapıldı —
  **temiz** (yalnız docblock içinde "$wpdb" kelimesinin geçtiği, gerçek
  kullanım olmayan iki kaçak eşleşme elendi).
- Her yeni `new WP_Query()` çağrısının (`public/class-catalog-service.php`,
  2 adet) aynı fonksiyon içinde bir `wp_reset_postdata()` çağrısı olduğu
  doğrulandı.
- **Yeni** `tools/verify-source-counts.js` — `node
  tools/verify-source-counts.js` ile çalıştırıldı, **9/9 sayı doğrulandı**
  (14 sektör, 83 yeterlilik, 103 ücret, 145 fiyat seçeneği, 87 tek
  fiyatlı, 16 çok fiyatlı, 58 çok-fiyatlı seçenek, 84 dolu/19 boş
  `qualificationCode`) — doğrudan `tanitim-site/assets/data/*.js`'ten,
  hiçbir WordPress bağlantısı veya yazma olmadan.
- `assets/dist/style.css` yeniden üretildi (`pages.css`'e Faz 5 filtre/
  ücret/hero-arama kuralları, `responsive.css`'e karşılık gelen 768px
  kırılımı eklendiği için) ve belgelenmiş sırayla (`tokens, base, layout,
  components, pages, header, footer, responsive`) taze `cat`
  birleştirmesiyle **byte-eşit** olduğu `diff` ile doğrulandı. CSS
  `{`/`}` sayımı `pages.css` (123/123), `responsive.css` (81/81) ve
  birleşik `dist/style.css` (385/385) için dengeli. `assets/dist/main.js`
  bu turda **değişmedi**.
- `git diff --check` — temiz (yalnız README.md için önceden var olan
  LF/CRLF bilgi uyarısı, bu turun bir sonucu değil).

**Çalıştırılamayanlar (Faz 5):**

- `public/class-catalog-service.php`'nin gerçek WordPress üzerinde
  çalıştırılması — `WP_Query`/`get_terms()`/`get_term_link()`/
  `current_time()`/`get_option()` gibi hiçbir çekirdek fonksiyon bu
  ortamda çalıştırılamadı. `docs/catalog-service-contract.md` §2'de her
  metodun sözleşmesi yazılı; gerçek doğrulama gereken senaryolar
  `wp-content/plugins/mavibelge-core/tests/run.php`'nin sonundaki "NOT
  covered" listesine eklendi (sektör+seviye+kelime birlikte filtreleme,
  aktif dönem/tarih/fiyat geçerliliği sınırları, yalnız
  `_mb_qualification_id` ile eşleşme, 19 kodsuz kaydın kaybolmaması).
- `admin/class-list-filters.php`'nin gerçek admin ekranında dropdown
  filtreleri ve meta-duyarlı aramayı üretmesi — gerçek `WP_Query`/admin
  ortamı gerektirir.
- `php -l` (tüm değişen PHP dosyaları) — `php` komutu yok; bunun yerine
  yukarıdaki PHP 7.4+/8.x desen taraması ve brace/paren denge sayımı
  yapıldı (kesin sözdizimi doğrulaması değildir).
- Gerçek tarayıcıda: 375/390/768/1024/1440 px'te filtre formu/ücret
  tablosu-kart geçişinin görsel doğrulaması, klavye Tab/Shift+Tab ile
  `<details>` açma, form gönderimi/geri tuşu/URL parametresi davranışı,
  aXe/Lighthouse, konsol/network. "Arama tamamen doğru çalışıyor",
  "responsive geçti" veya "WordPress'te çalıştı" iddia edilmemiştir.

## Faz 5 Düzeltme ve Kabul

Yine `php`/WordPress/tarayıcı yok. Gerçekten çalıştırılanlar:

- `tests/static/catalog-contract.test.js` yeniden çalıştırıldı ve 8 yeni
  regresyon kontrolü eklendi — **geçti**: `mavibelge-core.php`'nin
  `public/class-visibility-guard.php`'yi yüklediği; `archive-mb_yeterlilik.php`/
  `taxonomy-mb_sektor.php`'nin artık `template-parts/content/content-card.php`
  yerine `template-parts/catalog/qualification-card.php`'yi (DTO tabanlı)
  kullandığı; `inc/catalog-helpers.php`'nin artık `mavibelge_sector_name_for_slug()`
  tanımlamadığı; `fee-table.php`/`fee-cards.php`'nin artık kendi taksonomi
  çözümlemesi yerine `$fee['sector_name']`'i kullandığı.
- Diğer dört JS/statik test (`nav-active-page`, `mobile-focus-trap`,
  `nav-walker-filter-contract`, `heading-contract`) yeniden çalıştırıldı —
  **5/5 (toplam) geçti**; bu turda ilgili kaynaklar değişmedi.
- `node --check` source/dist JS — **geçti** (JS içeriği değişmedi).
- Değişen/yeni tüm PHP dosyalarında PHP 7.4+/8.x desen taraması,
  `href="#"`/`javascript:`/harici `http://`, gerçek `$wpdb->` kullanımı/
  `eval`/`exec` taraması — **temiz**.
- `assets/dist/style.css`/`assets/dist/main.js` bu turda **değişmedi**
  (yalnız PHP mantığı düzeltildi, CSS/JS'e dokunulmadı) — byte-eşitlik
  yeniden doğrulanmadı çünkü kaynak değişmedi.

**Çalıştırılamayanlar (Faz 5 Düzeltme ve Kabul):**

- `wp-content/plugins/mavibelge-core/tests/run.php`'ye eklenen 25+ yeni
  saf fonksiyon testi (`has_priced_options`/`canonicalize_price_options`/
  `is_within_validity_window`/`normalize_filters` unslash zinciri) —
  **yazıldı, çalıştırılmadı**; bu ortamda `php` yok, Node PHP kodu
  çalıştırmış sayılmaz.
- `public/class-visibility-guard.php`'nin gerçek `template_redirect`/404
  davranışı, `resolve_sector_filter()`'ın gerçek `get_term_by()` sonucu,
  `get_active_fee_count()`'un gerçek veri üzerindeki davranışı — hepsi
  gerçek WordPress runtime gerektirir, `tests/run.php`'nin "NOT covered"
  listesine eklendi.
- Gerçek tarayıcıda hiçbir yeni doğrulama yapılmadı (bu tur kod
  düzeltmesidir, görsel/CSS değişikliği yoktur).

## Faz 5 Son Kapanış Düzeltmesi

Yine `php`/WordPress/tarayıcı yok. Gerçekten çalıştırılanlar:

- `tests/static/catalog-contract.test.js`'e 10 yeni regresyon kontrolü
  eklendi ve dosya yeniden çalıştırıldı — **geçti**: `class-catalog-service.php`
  `resolve_sector_name_or_null()` tanımlıyor ve eski ham-slug-fallback
  `resolve_sector_name()`'i artık tanımlamıyor; kaynak metinde
  `return $sector_slug;` fallback deseni yok; `get_all_valid_active_fees()`
  `resolve_sector_name_or_null()` `null` döndüğünde satırı `continue` ile
  atlıyor; `sector_slug_to_name_map()` tanımlı (istek-içi önbellek);
  `class-validator.php` artık `str_truncate()` ile birim kısaltmıyor,
  birim sınırını sessiz `break;` ile kesmiyor, `sort_order` karşılaştırması
  `<=>` kullanıyor ve `_orig` ile deterministik tie-break yapıyor.
- Diğer dört JS/statik test (`nav-active-page`, `mobile-focus-trap`,
  `nav-walker-filter-contract`, `heading-contract`) yeniden çalıştırıldı —
  **5/5 (toplam) geçti**; kaynakları bu turda değişmedi.
- `node --check` source/dist JS — **geçti** (JS değişmedi).
- Değişen PHP dosyalarında (`class-validator.php`, `class-catalog-service.php`)
  PHP 7.4+/8.x desen taraması, `href="#"`/`javascript:`/gerçek `$wpdb->`
  kullanımı/`eval`/`exec` taraması, brace/paren denge sayımı — **temiz**.
- `assets/dist/style.css`/`assets/dist/main.js` bu turda **değişmedi**
  (yalnız PHP mantığı düzeltildi).

**Çalıştırılamayanlar (Faz 5 Son Kapanış Düzeltmesi):**

- `wp-content/plugins/mavibelge-core/tests/run.php`'ye eklenen 25+ yeni
  saf test (`units`/`sort_order` atomik ret, deterministik sıralama,
  taşma-güvenli karşılaştırma) — **yazıldı, çalıştırılmadı**; bu ortamda
  `php` yok.
- `resolve_sector_name_or_null()`'ın gerçek `get_terms()`/`get_term_by()`
  sonucu, sektörsüz/geçersiz-sektörlü bir ücretin gerçekten `get_active_fee_results()`'ten
  düştüğü, katılaştırılmış `normalize_price_options()`'ın gerçek admin
  kaydetme yolundaki davranışı — hepsi gerçek WordPress runtime
  gerektirir, `tests/run.php`'nin "NOT covered" listesine eklendi.
- Gerçek tarayıcıda hiçbir yeni doğrulama yapılmadı (bu tur da kod
  düzeltmesidir, görsel/CSS değişikliği yoktur).
