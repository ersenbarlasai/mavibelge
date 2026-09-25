# Şablon Mimarisi — Faz 4

> 41/41 sayfa eşleştirmesi: [`page-template-map.md`](page-template-map.md). Bu belge yalnız **neden bu dosya ailelerini seçtiğimizi** gerekçelendirir.

## 1. Neden "41 neredeyse aynı PHP dosyası" değil

Faz 4 brief'i açıkça "tek dev switch dosyası ya da 41 neredeyse aynı şablon oluşturma" diyor. Üç aile kuruldu:

1. **Gerçek WordPress template hierarchy dosyaları** — yalnız gerçekten farklı sunum gerektiren yerler: `front-page.php`, `404.php`, `search.php`, `archive.php`/`single.php` (genel fallback), yedi tane `archive-mb_*.php`/`single-mb_*.php`/`taxonomy-mb_sektor.php`/`taxonomy.php` (gerçek CPT/taksonomi adlarıyla, WordPress'in kendisi yönlendiriyor — hiçbir route icat edilmedi).
2. **`page.php` + slug→layout haritası** (`inc/page-layouts.php`) — 22 "düz metin" sayfası, 3 hub sayfası, 5 "form henüz kapalı" sayfası **aynı** `page.php`'den geçiyor; hangi `template-parts/page/content-*.php`'in çağrılacağına `post_name` slug'ına bakan küçük, saf bir fonksiyon karar veriyor. Bu, brief'in "kontrollü slug→layout eşleme yardımcı fonksiyonu" seçeneğidir — 30 sayfa için 30 dosya yerine 1 router + 3 içerik varyantı.
3. **İki gerçek `page-{slug}.php` istisnası** — `page-referanslar.php`, `page-sss.php`. Bunlar `page.php`'nin router'ından **geçmez** çünkü `mb_referans`/`mb_sss` `has_archive => false` olduğundan (`docs/content-model.md`) kendi CPT arşivleri yok; bu iki sayfa kendi sınırlı `WP_Query`'sini çalıştırıyor — bu, genel `page.php`'nin yapmadığı bir şey, bu yüzden ayrı gerçek dosya hak ediyor (brief §4.3'ün "az sayıda page-{slug}.php" seçeneği).

## 2. `inc/page-layouts.php` neden `functions.php`'e girmedi

`functions.php` ortak dosyadır (AGENTS.md §3); tema agentı doğrudan değiştiremez. `inc/**` ise tema agentının tam yetkisinde ve zaten `inc/bootstrap.php` üzerinden `require_once` ile yükleniyor — yeni bir ortak-dosya entegrasyon notu gerektirmedi, çünkü hiçbir ortak dosyaya dokunulmadı.

## 3. Hub kart linkleri neden `inc/menu-fallback.php`'i tekrarlıyor

`kurumsal`/`bilgi-merkezi`/`sinav-ve-basvuru` hub sayfalarının kart listeleri, Faz 3'te zaten yazılmış ve iki kabul turundan geçmiş `inc/menu-fallback.php`'deki `mavibelge_primary_nav_fallback()`'in aynı üç alt menü grubuyla **birebir aynı** (8/9/9 öğe, sayılar `tanitim-site/kurumsal.html` vb. doğrudan okunarak yeniden doğrulandı). Bu veri, `mavibelge-core`'un yönettiği yeterlilik/ücret/haber/referans **verisi değil** — site navigasyon yapısıdır, tıpkı `menu-fallback.php`'nin kendisi gibi "onaylı, güvenli fallback" sınıfına girer. `menu-fallback.php`'yi refactor edip ortak bir kaynağa çıkarmak yerine (bu, zaten sertleşmiş/kabul edilmiş bir dosyada risk demektir), `inc/page-layouts.php` içinde küçük, açıkça belgelenmiş bir kopya tutuldu. Gelecekte gerçek bir refactor uygun görülürse iki dosya aynı fonksiyonu paylaşabilir.

`mavibelge_resolve_hub_link_url()` de aynı gerekçeyle `inc/page-layouts.php`'de yaşıyor, `template-parts/page/content-hub.php` içinde değil: bir template part `get_template_part()` ile aynı istek içinde birden fazla kez yüklenebilir ve template part gövdesinde bir `function` bildirimi ikinci yüklemede fatal "cannot redeclare" hatası üretir. `inc/page-layouts.php` ise `inc/bootstrap.php` üzerinden `require_once` ile **tam olarak bir kez** yüklenir, bu yüzden fonksiyon tanımı için güvenli tek yerdir. Bu, Faz 4 Nihai Kabul Düzeltmesi turunda düzeltildi (bkz. `raporlar/proje-durumu.md`).

## 4. `duyurular.html` neden `taxonomy-mb_haber_turu.php` değil

`mb_haber_turu` yalnız iki terim taşıyacak şekilde tasarlandı (`haber`/`duyuru`, `docs/content-model.md`). Ayrı bir `taxonomy-mb_haber_turu.php` dosyası, `archive-mb_haber.php`'nin neredeyse birebir kopyası olurdu. Bunun yerine tek `taxonomy.php` (genel taksonomi fallback'i) içinde `$term->taxonomy === 'mb_haber_turu'` kontrolüyle `mb_haber` kartlarıyla aynı sunum (`template-parts/content/content-card.php`) kullanılıyor. `mb_sektor` ayrı bir dosya (`taxonomy-mb_sektor.php`) aldı çünkü onun sunumu (yeterlilik kartı + sektör başlığı) yeterince farklı ve kendi başına anlamlı.

## 5. `template-parts/content/content-card.php` — post-type-aware tek dosya

`mb_haber`/`mb_dokuman` arşiv kartları (ve genel `taxonomy.php`'nin `mb_haber_turu` dalı) görsel olarak `template-parts/components/card.php` şeklini paylaşıyor; yalnız üstteki rozet satırı (haber türü/tarih, doküman sürümü) türe göre değişiyor. `get_post_type()`'a göre dallanıyor — WordPress'in `get_template_part( $slug, $name )` ikinci parametresi kasıtlı olarak **kullanılmadı**.

`mb_yeterlilik`'in kendi dalı da hâlâ bu dosyada durur (geriye dönük uyumluluk için, bkz. `git log`), ama Faz 5 Düzeltme ve Kabul §2.6'dan sonra **hiçbir çağıran onu kullanmıyor** — `archive-mb_yeterlilik.php` ve `taxonomy-mb_sektor.php` artık `template-parts/catalog/qualification-card.php`'yi çağırıyor; bu, katalog servisinin zaten hazırladığı DTO'dan (`title`/`permalink`/`myk_code`/`level`/`sectors[]`) doğrudan render eder, aynı kayıt için `get_post()`/`get_post_meta()`/`get_the_terms()`'i **tekrar** çağırmaz (brief'in "aynı isteğe iki sorgu" itirazı buydu). `content-card.php`'nin `mb_yeterlilik` dalı kasıtlı olarak silinmedi — genel amaçlı, post_id tabanlı bir yeterlilik kartına ileride ihtiyaç duyulursa (ör. front-page.php'de) hâlâ çalışır durumda kalır; ama katalog arama/filtre akışının kendisi artık ona bağlı değildir.

## 6. Ücret (`mb_ucret`) hiçbir şablonda yok

`mb_ucret` `public => false`, `publicly_queryable => false` (`class-content-types.php`) — kasıtlı olarak public değil, Faz 5'te de değişmedi. `single-mb_ucret.php`/`archive-mb_ucret.php` hâlâ **oluşturulmadı** ve hiçbir şablon bu CPT'yi doğrudan sorgulamıyor. Faz 4'te `sinav-ucretleri.html` düz bir `page` (`layout: default`) olarak eşlenmişti; Faz 5'te gerçek fiyat gösterimi/eşleştirmesi eklendiğinde bu sayfa `page-sinav-ucretleri.php`'ye taşındı (bkz. §10.1, `page-template-map.md` madde 32) — ama erişim yine yalnız `MaviBelge_Core_Catalog_Service` üzerinden, hiçbir zaman doğrudan `WP_Query`/`$wpdb` ile değil.

## 7. Form sayfaları neden gerçek `<form>` etiketi içermiyor

`template-parts/page/content-form-disabled.php` kasıtlı olarak `<form>`/`<input>` üretmiyor — yalnız `the_content()` + bir "henüz etkin değil" `alert` bileşeni + amaçlanan alan adlarının düz metin listesi. Bu, brief §9'daki tüm yasakları (action="#", sahte AJAX, disabled-ama-yine-de-form-gibi-görünen belirsizlik) kaynağında ortadan kaldırıyor: ortada gönderilebilecek bir `<form>` yok.

## 8. SSS akordeonu neden JS değil `<details>`/`<summary>`

Statik sitedeki `.accordion-item` JS ile aç/kapa yapıyordu (`assets/js/components.js`). `page-sss.php` yerine tarayıcının yerleşik `<details>`/`<summary>` elemanını kullanıyor: JS kapalıyken de çalışır, klavye erişimi ücretsiz gelir, ve Faz 4 brief §7.3'ün "yalnız gerçekten gerekli, bağımsız vanilla JS" kısıtına hiç girmeden erişilebilirlik sağlanmış olur.

## 9. Referans/haber izgarası neden slider değil

`.ref-slider`/autoplay/swipe, brief §7.3'te açıkça bu fazda yasak. Hem `front-page.php`'nin referans bölümü hem `page-referanslar.php` aynı düz, erişilebilir `.ref-grid` düzenini kullanıyor — CSS'te `.ref-slider`/`.ref-track`/`.ref-nav` sınıfları hiç üretilmedi (tanitim-site'de var, Faz 4'e taşınmadı).

## 10. Faz 5 — meslek/sektör/ücret şablon kararları

### 10.1 `page-sinav-ucretleri.php` neden gerçek dosya, `page.php` router'ından geçmiyor

`inc/page-layouts.php`'nin slug→layout haritası (hub/prose/form-disabled) yalnız **veritabanı sorgusu yapmayan** düz sunum varyantları içindir (`template-parts/page/content-*.php`, hiçbiri kendi `WP_Query`'sini çalıştırmaz — brief §7.1 "Template part doğrudan veritabanı sorgusu yapmasın"). `sinav-ucretleri.html`'in Faz 5 karşılığı artık `MaviBelge_Core_Catalog_Service::get_active_fee_results()` çağıran, filtre/sayfalama işleyen gerçek bir sayfa mantığıdır — bu, `page-referanslar.php`/`page-sss.php`'nin zaten kurduğu "kendi sınırlı sorgusunu çalıştıran gerçek `page-{slug}.php`" sınıfına girer (Faz 4, §1 madde 3). Sayfa bu yüzden generic router'dan **çıkarıldı**; `page-template-map.md` madde 32 bu geçişi kaydeder.

### 10.2 `filter-form.php` neden `template-parts/components/form-field.php`'i kullanmıyor

`form-field.php` (Faz 3) kasıtlı olarak minimal bir kabuktur: `value`/`selected`/`checked` durumunu desteklemez, yalnız boş bir alan render eder (gerçek form gönderimi henüz yoktu). Faz 5'in filtre formu ise **mevcut GET değerlerini** forma geri yansıtmak zorunda (kullanıcı "Filtrele"ye bastıktan sonra seçtiği sektör/seviye/arama terimi kaybolmamalı) — bu, `form-field.php`'nin sözleşmesinin dışındadır. `form-field.php`'yi bu ihtiyaca göre genişletmek (Faz 8'in gerçek form alanlarını da etkileyecek ortak bir dosyada risk almak) yerine, `template-parts/catalog/filter-form.php` kendi küçük, açıkça belgelenmiş `<label>`/`<input>`/`<select>` işaretlemesini kullanıyor — `fee-table.php`'nin `table-wrap.php` yerine kendi `<table>`'ını kullanmasıyla aynı gerekçe sınıfı.

### 10.3 `fee-table.php`/`fee-cards.php` çifti — neden iki ayrı DOM, tek erişilebilirlik kaydı

Masaüstü tablo ve mobil kart listesi **aynı veriyi** iki farklı düzende gösteriyor. Brief §6.4 "aynı kayıt masaüstü tablo ve mobil kartta iki kez ekran okuyucuya sunulmamalı" der. Çözüm: `assets/src/css/responsive.css`'te ≤768px'te `.fee-table-wrap { display:none }` / `.fee-cards { display:flex }` (masaüstünde tersi). CSS `display:none`, standart tarayıcı/AT davranışı olarak bir öğeyi **tamamen erişilebilirlik ağacından çıkarır** — bu, `hidden` HTML özniteliğiyle aynı garantiyi verir, yalnız medya sorgusuna bağlı olarak koşulludur. Böylece her genişlikte yalnız bir kopya "var" sayılır; ayrı bir ARIA `aria-hidden` yönetimi gerekmez.

### 10.4 Neden ham SQL/`$wpdb` hiçbir yerde yok

`MaviBelge_Core_Catalog_Service` ve `admin/class-list-filters.php`, "başlık VEYA meta" aramasını WordPress'in `s` parametresiyle tek bir `meta_query`'de ifade edemediği için, sınırlı (`CANDIDATE_CAP`/`SEARCH_CANDIDATE_CAP`) bir aday kümesini normal `WP_Query`/`get_posts()` ile çeker, ardından PHP'de `MaviBelge_Core_Catalog_Query::text_contains_ci()` ile filtreler. Bkz. `docs/catalog-service-contract.md` §4 için tam gerekçe ve bilinen sınırlar (Türkçe büyük/küçük harf uç durumları, veri ölçeği varsayımı).

### 10.5 Faz 5 Düzeltme ve Kabul — pasif yeterliliğin doğrudan erişimi neden temada değil eklentide kapatıldı

Bağımsız incelemede bulunan §2.5: bir yeterlilik `post_status=publish` ama `_mb_record_status=passive` olduğunda, katalog listelerinden (arşiv/sektör/arama) hariç tutulmasına rağmen gerçek tekil URL'sinden doğrudan açılabiliyordu — çünkü `single-mb_yeterlilik.php` (ya da genel WordPress rotalaması) `_mb_record_status`'ten habersiz. Bu, temada bir `if` ile değil, `wp-content/plugins/mavibelge-core/public/class-visibility-guard.php`'de `template_redirect` kancasıyla çözüldü: şablon hiç yüklenmeden önce, halka açık olmayan bir tekil isteği gerçek 404'e düşürür. Neden eklentide: (1) "hangi yeterlilik halka açık" kararı zaten `get_qualification_results()`'in `meta_query`'siyle eklentide yaşıyor — aynı kararın ikinci bir kopyasını temada tutmak, ikisinin zamanla ayrışması riskini taşırdı (Faz 2'nin `Field_Repository` dersiyle aynı sınıf sorun); (2) `template_redirect` şablon seçiminden önce çalışır, bu yüzden temanın hiçbir dosyasını değiştirmeden isteği tamamen durdurabilir. `MaviBelge_Core_Catalog_Service::present_fee()`'nin `qualification_permalink` alanı da aynı `is_public_qualification()` kararını kullanır — pasif bir yeterliliğe asla "çalışan" bir bağlantı üretilmez.

## 11. CSS/JS kaynak-dist senkronu

`assets/dist/style.css` sırası artık `tokens, base, layout, components, pages, header, footer, responsive` (Faz 3'ün sırasına `pages.css` eklendi — bkz. `docs/design-system.md`). `pages.css`, `tanitim-site/assets/css/components.css` + `pages.css`'ten yalnız gerçekten bir Faz 4 şablonunun ürettiği kurallar seçilerek taşındı; fiyat-tablosu açılır/kapanır detay stilleri (`.fee-price-*`) bilinçli olarak **taşınmadı** çünkü hiçbir Faz 4 şablonu onları üretmiyor (`mb_ucret` public değil). JS hiç değişmedi; `assets/dist/main.js` Faz 3'ten aynen kalıyor.
