# Katalog Servis Sözleşmesi — Faz 5

> Kaynak: `wp-content/plugins/mavibelge-core/includes/class-catalog-query.php`, `public/class-catalog-service.php`, `public/class-visibility-guard.php`. Bu belge o dosyalardaki gerçek davranışın insan-okunur özetidir; çelişki halinde kod esastır.
>
> **Faz 5 Düzeltme ve Kabul (12 Eylül 2026):** bağımsız incelemede bulunan sorunlar giderildi — kanonik tek fiyat doğrulayıcı, GET'te `wp_unslash()` sırası, ücret sektör filtresinde gerçek terim doğrulaması, aktif dönem yönetim uyarısının doğru sayacı, pasif yeterliliğin doğrudan erişiminin kapatılması, tarih penceresinin katı doğrulanması. Ayrıntı her ilgili bölümde "Faz 5 Düzeltme ve Kabul" etiketiyle işaretlidir.
>
> **Faz 5 Son Kapanış Düzeltmesi (12 Eylül 2026):** kanonik fiyat doğrulayıcı gerçekten katılaştırıldı (`units`/`sort_order`'daki eski sessiz düzeltmeler artık atomik ret), bir ücret kaydının **kendi** `_mb_sector_slug` alanı artık gerçek bir terime çözülmüyorsa halka açık gösterilmiyor (eskiden çözülemeyen slug ham metin olarak gösteriliyordu). Ayrıntı "Faz 5 Son Kapanış Düzeltmesi" etiketiyle işaretlidir.
>
> Tema, bu servis dışında `mb_yeterlilik`/`mb_ucret` için hiçbir sorgu/iş kuralı yazmaz (AGENTS.md, brief §7). Tema tarafındaki tek erişim noktası `wp-content/themes/mavibelge/inc/catalog-helpers.php`'deki ince, `class_exists()` kapılı sarmalayıcı fonksiyonlardır.

## 1. GET parametreleri

Beş sabit ad, `MaviBelge_Core_Catalog_Query::normalize_filters()` tarafından okunur:

| Parametre | Anlam | Normalize sonucu |
|---|---|---|
| `mb_q` | Meslek adı veya MYK kodu (serbest metin) | `wp_unslash()` → trim → iç boşlukları tek boşluğa indirgenmiş, en fazla 100 karakter; dizi/nesne değeri boş metne düşer |
| `mb_sector` | `mb_sektor` terim slug'ı | `wp_unslash()` sonrası yalnız `a-z0-9` + tire biçimi kabul edilir (küçük harfe çevrilir); biçim uymuyorsa boş |
| `mb_level` | MYK seviyesi | `wp_unslash()` sonrası yalnız `"1"`–`"8"`; başka her şey boş |
| `mb_priced` | Yalnız geçerli aktif ücreti bulunanlar | `wp_unslash()` sonrası yalnız tam olarak `"1"` `true` sayılır |
| `mb_page` | 1 tabanlı sayfa numarası | `wp_unslash()` sonrası pozitif tam sayı; geçersiz/boş `1`'e düşer |

**Faz 5 Düzeltme ve Kabul §2.2:** `wp_unslash()`, dizi/nesne biçim kontrolünden (`scalar_or_empty()`) **sonra**, ilgili `normalize_*()` fonksiyonundan **önce** uygulanır — beşi için de aynı sırayla. WordPress `$_GET`'e uyguladığı ters-eğik-çizgi kaçışını (`wp_unslash()` bunu geri alır — genel bir "sanitize" değildir) atlamak, kesme işareti içeren bir arama teriminin `sanitize_text_field()`'a hâlâ kaçışlı ulaşmasına yol açardı. Standalone test ortamında (`tests/run.php`) `wp_unslash()` yoksa `stripslashes()`'e düşer.

Bu beş anahtar dışında hiçbir GET parametresi servis tarafından okunmaz. Sanitizasyon **tamamen** `MaviBelge_Core_Catalog_Query`'de yaşar — tema bu mantığı asla tekrarlamaz.

## 2. `MaviBelge_Core_Catalog_Service` genel metotları

### `get_sector_terms()`

Gerçek `mb_sektor` terimlerini döner (`hide_empty=false`, en fazla 40). Taksonomi yoksa veya `WP_Error` dönerse **boş dizi** — asla uydurma/sabit sektör listesi yok.

### `get_qualification_results( array $raw_filters )`

```text
{ items: DTO[], total: int, total_pages: int, page: int, page_size: int, filters: {...normalize_filters sonucu} }
```

- `post_status = publish`, `_mb_record_status = active` her zaman uygulanır.
- `mb_sector` geçerli bir gerçek terime karşılık gelmiyorsa (yanlış slug) sonuç **sıfırdır** — filtre sessizce yok sayılıp tüm liste gösterilmez. Terim çözümlemesi `resolve_sector_filter()` üzerinden yapılır (bkz. altta).
- `mb_priced=1` iken yalnız `get_active_fee_qualification_ids()`'in döndürdüğü ID kümesindeki yeterlilikler döner.
- `mb_q`, başlık **veya** `_mb_myk_code` içinde büyük/küçük harf duyarsız alt-dize eşleşmesi arar (`text_contains_ci()`; bkz. §4 sınırlama).
- Sayfalama: sabit `DEFAULT_PAGE_SIZE = 12`; `page`, gerçek `total_pages`'e `clamp_page()` ile kenetlenir (asla 0, asla son sayfayı aşmaz).
- Sonuçlar `MaviBelge_Core_Catalog_Service::build_qualification_dto()`'nun DTO'sudur (`id`,`title`,`permalink`,`myk_code`,`level`,`excerpt`,`sectors[{name,slug,url}]`). **Faz 5 Düzeltme ve Kabul §2.6:** tema bu DTO'yu doğrudan render eder (`template-parts/catalog/qualification-card.php`) — aynı kayıt için `get_post()`/`get_post_meta()`/`get_the_terms()`'i tekrar çağırmaz.

### `get_active_fee_results( array $raw_filters )`

Aynı dönüş şekli, ama yalnız **§3'teki tüm görünürlük kurallarını geçmiş** ücret kayıtları üzerinde çalışır; `mb_priced` anahtarı burada anlamsızdır (her satır zaten "priced") ve yok sayılır. `mb_q`, meslek adı veya kaynak MYK kodu içinde arar. Sıralama: meslek adına göre `strnatcasecmp`, eşitlikte ID. **Faz 5 Düzeltme ve Kabul §2.3:** `mb_sector` filtresi, `get_qualification_results()` ile **aynı** `resolve_sector_filter()` çağrısından geçer — biçimi geçerli ama var olmayan bir sektör slug'ı burada da sonucu sıfıra düşürür; eskiden yalnız `_mb_sector_slug` metin eşitliği kontrol ediliyordu (gerçek terim doğrulaması yoktu).

### `get_active_fees_for_qualification( $qualification_id )`

Yalnız gerçek, kayıtlı `_mb_qualification_id` eşleşmesiyle döner — ad/kod benzerliğiyle **asla** fallback yapmaz (brief §4). Normal olarak 0 veya 1 sonuç; teorik olarak birden fazla geçerli aktif kayıt varsa hepsi döner (uydurulmaz, gizlenmez).

### `get_active_fee_count()`

**Faz 5 Düzeltme ve Kabul §2.4 ile eklendi.** `get_all_valid_active_fees()`'in toplam eleman sayısı — ilişkili olsun olmasın, §3'teki **tüm** kuralları geçen her ücret sayılır. `admin/class-settings.php`'nin "aktif dönemde eşleşen ücret yok" uyarısı **yalnız bunu** kullanır. `get_active_fee_qualification_ids()` (yalnız `_mb_qualification_id > 0` olanlar) buna **karıştırılmaz** — o yalnız `mb_priced` yeterlilik filtresi içindir; eskiden yönetim uyarısı yanlışlıkla bunu kullanıyordu ve tamamen geçerli ama bağlantısız (yeterlilik ID'si `0`) ücretler varken bile "eşleşen ücret yok" diyordu.

### `present_fee( array $fee )`

Sorgu yapmaz — yalnız formatlama. `single_amount_display` (tek seçenek varsa), `options[]` (`label`, `units[]`, `amount_display`), `vat_label` (**`mavibelge-core` metin alanı** — Faz 5 Düzeltme ve Kabul §2.7, eskiden yanlışlıkla tema alanı `mavibelge` kullanılıyordu), `certificate_print_fee_display` (**0 veya boşsa boş döner — asla "0 TL" yazmaz**), `source_url` (yalnız gerçek, var olan bir attachment URL'si varsa), `qualification_permalink`.

**Faz 5 Düzeltme ve Kabul §2.5:** `qualification_permalink` artık yalnız `MaviBelge_Core_Visibility_Guard::is_public_qualification()` `true` döndürüyorsa üretilir (`publish` **ve** `_mb_record_status = active`) — eskiden yalnız `post_status === 'publish'` kontrol ediliyordu, bu yüzden pasif ama yayınlanmış bir yeterliliğe bağlı bir ücret, gerçekte 404 dönecek bir bağlantı üretebiliyordu.

## 3. Aktif ücret görünürlük kuralları (brief §5, birebir uygulanan)

Bir `mb_ucret` kaydı yalnız **tümü** doğruysa `get_all_valid_active_fees()`'ten geçer:

1. `post_status === 'publish'`.
2. `_mb_record_status === 'active'`.
3. `_mb_tariff_period`, `mb_active_tariff_period` option'ıyla **birebir** eşleşir. **İki katmanlı uygulanır** (Faz 5 Düzeltme ve Kabul §3.1 tutarlılık düzeltmesi): önce `WP_Query`'nin `meta_query`'si DB seviyesinde `_mb_tariff_period = $active_period` ile daraltır; ardından PHP döngüsünde **aynı** saf karar fonksiyonu `is_period_match()` tekrar çağrılarak sonuç doğrulanır — belgelenen sözleşme ile gerçek çalışma zamanı artık iki ayrı, birbirinden bağımsız uygulama değil, aynı kuralın iki katmanıdır. Option boşsa **hiçbir kayıt** eşleşmez, "hepsini göster"e asla düşmez.
4. `current_time('Y-m-d')` (sunucu saati değil, **WordPress site saati**), `_mb_valid_from`/`_mb_valid_until` aralığında (her iki sınır **dahil**; boş taraf sınırsız) — `is_within_validity_window()`. **Faz 5 Düzeltme ve Kabul §2.8 ile sıkılaştırıldı:** eskiden yalnız ham metin karşılaştırması yapılıyordu (`"2026-13-40" < "2026-09-12"` gibi anlamsız ama "geçen" bir karşılaştırma). Artık `today`/`valid_from`/`valid_until` dolu oldukları her durumda `MaviBelge_Core_Validator::is_valid_ymd_date()` ile **gerçek bir takvim tarihi** olduğu doğrulanır (`"2026-02-30"` gibi biçimi doğru ama takvimde var olmayan bir tarih reddedilir) ve `valid_from > valid_until` (ters aralık) da açıkça reddedilir. Herhangi biri geçersizse ücret **kapalı yönde** (gizli) başarısız olur.
5. `_mb_price_options`, `MaviBelge_Core_Validator::normalize_price_options()` — kaydetme ve yayın hazırlığının kullandığı **aynı** kanonik doğrulayıcı — üzerinden **sıfır hatayla** geçiyor ve en az bir seçenek üretiyor (`has_priced_options()` → `canonicalize_price_options()`). **Faz 5 Düzeltme ve Kabul §2.1'de düzeltildi, Faz 5 Son Kapanış Düzeltmesi §2'de gerçekten katılaştırıldı:** eskiden yalnız her satırın pozitif `amount_kurus` taşıdığı kontrol ediliyordu; sonraki turda etiketsiz/aşırı-uzun-etiketli/20-sınırını-aşan satırlar için atomik ret eklendi, **ama `units` içindeki iç içe dizi/nesne öğesi hâlâ sessizce atlanıyor, 10 birim sınırını aşan liste sessizce kesiliyor, 50 karakteri aşan birim sessizce kısaltılıyor, ve gönderilmiş-ama-geçersiz `sort_order` sessizce satır pozisyonuna düşüyordu** — dördü de artık hata üretip **tüm listeyi** reddediyor (kısmi/"düzeltilmiş" liste asla gösterilmez). `sort_order` **hiç gönderilmediyse** pozisyon fallback'i hâlâ geçerlidir — yalnız *gönderilip geçersiz* olan değer artık reddedilir. Eşit `sort_order`'da sıralama artık `_orig` (gönderim sırası) ile **deterministiktir** (PHP 7.3'te `usort()` kararlı değildir) ve karşılaştırıcı `<=>` kullanır (büyük değerlerde çıkarma taşması riski yok).
6. **(Faz 5 Son Kapanış Düzeltmesi ile eklendi)** Kaydın **kendi** `_mb_sector_slug` alanı, biçim olarak geçerli **ve** gerçek, hâlâ var olan bir `mb_sektor` terimine çözülüyor — `resolve_sector_name_or_null()` `null` dönerse (boş, biçimsiz, taksonomi yok, veya terim bulunamadı) kayıt **kapalı yönde** başarısız olur ve hiçbir halka açık sonuca girmez. Görünür kalan her kaydın `sector_name`'i her zaman gerçek terim adıdır — ham slug **asla** fallback olarak gösterilmez.

### 3.1 Sektör bütünlüğü ile sektör filtresi — bilinçli ayrım

İki farklı "sektör" doğrulaması vardır, kasıtlı olarak ayrı fonksiyonlarda yaşar:

- **`resolve_sector_filter( $sector_slug )`** — bir ziyaretçinin **istediği filtre** değerini doğrular (`mb_sector` GET parametresi). Boş değer geçerlidir ("filtre yok" anlamına gelir); yalnız *dolu ama var olmayan* bir slug sonucu sıfıra düşürür. `get_qualification_results()` ve `get_active_fee_results()`'in ikisi de bunu kullanır.
- **`resolve_sector_name_or_null( $sector_slug )`** — bir **kaydın kendi** `_mb_sector_slug` alanının bütünlüğünü doğrular (kural 6, yukarıda). Burada boş değer **asla** geçerli değildir — her görünür ücret kaydının gerçek bir sektörü olmak zorundadır. Yalnız `get_all_valid_active_fees()` tarafından, her ücret satırı için çağrılır.

İkisini karıştırmamak önemlidir: bir filtre olarak boş sektör "tümünü göster" demektir, ama bir kaydın kendi alanı olarak boş sektör "bu kayıt asla gösterilmez" demektir.

**İstek-içi önbellek:** `sector_slug_to_name_map()` gerçek `mb_sektor` terimlerini (`get_sector_terms()` üzerinden, tek bir bounded `get_terms()` çağrısı) bir kere çeker ve `slug => name` haritası olarak statik önbellekte tutar; `get_all_valid_active_fees()`'in döngüsündeki her ücret satırı için ayrı bir `get_term_by()` sorgusu **yapılmaz**.

### 3.2 `MaviBelge_Core_Visibility_Guard` — pasif yeterliliğin doğrudan erişimi

**Faz 5 Düzeltme ve Kabul §2.5 ile eklendi.** WordPress'in kendi rotalama mekanizması `_mb_record_status` meta alanından habersizdir: `post_status=publish` + `_mb_record_status=passive` bir yeterlilik, katalog listelerinden (arşiv/sektör/arama) hariç tutulsa bile gerçek permalink'inden **doğrudan açılabiliyordu**. `public/class-visibility-guard.php`:

- `is_public_qualification( $post_id )` — tek karar fonksiyonu: var mı, gerçekten `mb_yeterlilik` mi, `publish` mi, `_mb_record_status === 'active'` mi.
- `template_redirect` kancasına bağlı `maybe_block_passive_qualification()` — pasif/yayınlanmamış bir yeterliliğin tekil URL'sine gelen, o kaydı düzenleme yetkisi olmayan her istek gerçek bir 404'e düşürülür (`$wp_query->set_404()` + `status_header(404)` + `nocache_headers()`). Düzenleme yetkisi olan kullanıcı (yazar/editör/admin önizlemesi) normal şekilde görmeye devam eder — bu bir yetki değişikliği değildir, yalnız **halka açık** görünürlüğü daraltır.
- Tema bu kuralı **hiçbir yerde** tekrarlamaz; `single-mb_yeterlilik.php` değişmeden kaldı, engelleme `template_redirect`'te şablon yüklenmeden önce gerçekleşir.

## 4. Sorgu stratejisi ve bilinen sınırlar

- **`CANDIDATE_CAP = 500`**: her iç aday sorgusu (mb_yeterlilik veya mb_ucret) en fazla bu kadar satır çeker — asla `-1`. Gerçek ölçek (83 yeterlilik, 103 ücret — `docs/content-model.md`) bu sınırın çok altında; sınır aşılırsa bu sabit ve sayfalama stratejisi birlikte gözden geçirilmelidir.
- **Neden PHP-side filtreleme:** Serbest metin arama (başlık **veya** meta alanı) ve tarih-penceresi/fiyat-geçerliliği kontrolleri, tek bir güvenli `WP_Query` `meta_query`/`s` kombinasyonuyla doğru ifade edilemiyor (WordPress çekirdeği `s` ile meta OR'unu desteklemez). Bunun yerine: sektör/seviye/durum/dönem gibi basit eşitlikler DB seviyesinde (`meta_query`/`tax_query`) uygulanır; serbest metin ve tarih/fiyat geçerliliği, sınırlı aday kümesi üzerinde PHP'de uygulanır. Hiçbir adımda ham SQL veya `$wpdb` kullanılmaz.
- **Türkçe arama sınırı:** `text_contains_ci()` yalnız `mb_strtolower()`/`mb_strpos()` tabanlı düz alt-dize eşleşmesidir; İ/i noktalı-noktasız harf gibi Türkçe'ye özgü uç durumlarda beklenmedik sonuç verebilir. ICU/Intl bağımlılığı **varsayılmaz**. Gelişmiş/bulanık arama Faz 5'in kapsamı dışıdır.
- **`get_active_fee_qualification_ids()`**: yalnız `mb_priced=1` filtresi için kullanılır; her çağrıda `get_all_valid_active_fees()`'i yeniden çalıştırır — önbellek yoktur (gerçek veri henüz sıfır olduğu için performans kaygısı bu fazda erken optimizasyon olurdu).
- **`resolve_sector_filter( $sector_slug )`**: hem `get_qualification_results()` hem `get_active_fee_results()`'in kullandığı **tek** sektör-doğrulama yolu (Faz 5 Düzeltme ve Kabul §2.3). Boş slug → filtre yok (`valid=true, term=null`); taksonomi yoksa veya terim bulunamazsa → `valid=false` (çağıran sıfır sonuç döner); terim varsa → `valid=true, term=WP_Term`.
- **`resolve_sector_name_or_null( $sector_slug )`**: ücret DTO'sundaki `sector_name` alanını üretir (bkz. §3.1) — gerçek `mb_sektor` terimi varsa adı, **yoksa `null`** (Faz 5 Son Kapanış Düzeltmesi öncesi ham slug'a düşüyordu; artık kaydın kendisi `get_all_valid_active_fees()`'te tamamen gizleniyor, `sector_name` hiçbir zaman ham metin taşımıyor). Tema bu çözümlemeyi **kendisi yapmaz**; eskiden `inc/catalog-helpers.php`'de `mavibelge_sector_name_for_slug()` adında ayrı bir kopyası vardı, kaldırıldı.

## 5. Yönetim/eklenti tarafı — servis dışında

`admin/class-list-filters.php`, admin liste ekranındaki dropdown filtreleri ve "meslek adı/MYK kodu"nu bulan aramayı uygular — **ayrı, admin'e özel** bir mantıktır, `MaviBelge_Core_Catalog_Service`'i çağırmaz (o yalnız ziyaretçi tarafı içindir). Raw SQL kullanmaz: adayları `get_posts()`/`WP_Query` ile (mevcut dropdown filtreleriyle zaten daraltılmış) çeker, ardından aynı `MaviBelge_Core_Catalog_Query::text_contains_ci()` ile başlık/meta eşleştirmesini PHP'de yapıp `post__in` olarak WordPress'e geri verir.
