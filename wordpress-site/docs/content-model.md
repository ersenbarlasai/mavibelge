# İçerik Modeli — `mavibelge-core` (Faz 2)

> Kaynak: `wp-content/plugins/mavibelge-core/includes/class-content-types.php`, `class-taxonomies.php`, `class-meta-schema.php`, `class-validator.php`, `class-field-repository.php`, `class-publish-readiness.php`, `admin/class-meta-boxes.php`. Bu belge o dosyalardaki gerçek tanımların insan-okunur özetidir; çelişki halinde kod esastır.
>
> Faz 2 düzeltme turu (10-11 Eylül 2026, iki geçiş): MYK kendi-kendine çakışma sorgusu, biçim doğrulaması (MYK kodu/slug), fiyat seçeneklerinde derin temizleme + atomik kaydetme + TL girişi, alan bazlı yetkilendirme (ücret durumu, haber onayı), yayın öncesi zorunlu alan kapısı ve denetim günlüğü kapsamı genişletildi. **İkinci geçiş:** gerçek yönetim kaydı ve programatik `register_post_meta` yazımı artık **tek** paylaşılan doğrulayıcıdan (`MaviBelge_Core_Field_Repository`) geçiyor — önceki sürümde fiyat satırı ayrıştırması cast işlemini tür kontrolünden önce yaptığı için `label[]=x` gibi bir istek "Array" metnine dönüşüp dizi/nesne reddi kuralını atlatabiliyordu; TL/kuruş dönüşümünde taşma koruması eklendi; audit tablo `SHOW TABLES` sorgusu `esc_like()` ile literal hale getirildi; yayın hazırlığı artık kaydetmeyle birebir aynı kuralları (slug biçimi, `_mb_pricing_type` enum'u, gerçek `mb_sektor` terimi, tüm fiyat satırlarının geçerliliği, `meta_input` doğrulaması) uyguluyor. Ayrıntı ilgili alt bölümlerde.
>
> Kapsam dışı (bu fazda yok): veri importu, REST endpoint, ön yüz filtreleme/arama, SEO alanları, form.

## İçerik türleri

| Post type | Türkçe adı | Public | Arşiv | REST | Rewrite slug | Capability type |
|---|---|---|---|---|---|---|
| `mb_yeterlilik` | Yeterlilik | Evet | Evet | Kapalı | `yeterlilikler` | `mb_yeterlilik` / `mb_yeterlilikler` |
| `mb_ucret` | Ücret Kaydı | **Hayır** | Hayır | Kapalı | yok (sorgulanamaz) | `mb_ucret` / `mb_ucretler` |
| `mb_haber` | Haber | Evet | Evet | Kapalı | `haberler` | `mb_haber` / `mb_haberler` |
| `mb_dokuman` | Doküman | Evet | Evet | Kapalı | `dokumanlar` | `mb_dokuman` / `mb_dokumanlar` |
| `mb_referans` | Referans | Evet | Hayır | Kapalı | `referanslar` | `mb_referans` / `mb_referanslar` |
| `mb_lokasyon` | Lokasyon | Evet | Hayır | Kapalı | `lokasyonlar` | `mb_lokasyon` / `mb_lokasyonlar` |
| `mb_sss` | SSS | Evet | Hayır | Kapalı | `sss` | `mb_sss` / `mb_sss_kayitlari` |

`show_in_rest` her tür için `false`'tur. Bu, WordPress'i klasik (Gutenberg olmayan) düzenleme ekranına düşürür; blok editörü ihtiyacı yoktur ve REST yazma yüzeyi kapalı tutulur (görev kartı 02 §2.8/§3, karar-kaydi-wordpress-php73.md §5). `mb_ucret` ayrıca `public => false`, `publicly_queryable => false`, `rewrite => false`, `query_var => false` ile hiçbir doğrudan URL üretmez.

## Taksonomiler

| Taksonomi | Türkçe adı | Hiyerarşik | Bağlı post type | Not |
|---|---|---|---|---|
| `mb_sektor` | Sektör | Evet | `mb_yeterlilik` | Terim seed edilmedi; bkz. aşağıdaki "Sektör" bölümü |
| `mb_haber_turu` | Haber Türü | Hayır | `mb_haber` | Kontrollü kelime dağarcığı: yalnız `haber`/`duyuru` |
| `mb_dokuman_kategori` | Doküman Kategorisi | Evet | `mb_dokuman` | Terim seed edilmedi |
| `mb_sss_kategori` | SSS Kategorisi | Evet | `mb_sss` | Terim seed edilmedi |

Terim yönetimi (`manage_terms`/`edit_terms`/`delete_terms`) `mb_manage_taxonomies` özel yetkisine bağlıdır (yalnız `administrator` ve `mb_site_manager`). Mevcut terimi bir içeriğe atamak (`assign_terms`), o içerik türünü düzenleyebilen herkese açıktır.

### Sektör — bu fazda kesinleşen veri sözleşmesi

Kullanıcının bu görevdeki kararı: **14 sektör** — Makine, Metalurji, Metal, Lojistik, İş Makineleri, Plastik, Enerji, Cam, Tekstil, İnşaat, Mobilya, Maden, Mermer, Güzellik ve Saç Bakım. Liman ayrı sektör değildir (Lojistik'e dahildir); Maden ve Mermer ayrı sektörlerdir.

Terimler **bu fazda oluşturulmaz** — yalnız isim/slug sözleşmesi burada dokümante edilir; gerçek terim oluşturma Faz 6'daki doğrulanmış, idempotent import işidir.

| Slug (planlanan) | Ad |
|---|---|
| `makine` | Makine |
| `metalurji` | Metalurji |
| `metal` | Metal |
| `lojistik` | Lojistik |
| `is-makineleri` | İş Makineleri |
| `plastik` | Plastik |
| `enerji` | Enerji |
| `cam` | Cam |
| `tekstil` | Tekstil |
| `insaat` | İnşaat |
| `mobilya` | Mobilya |
| `maden` | Maden |
| `mermer` | Mermer |
| `guzellik-sac-bakim` | Güzellik ve Saç Bakım |

Slug'lar `tanitim-site/assets/data/sectors.js` içindeki mevcut slug'larla birebir aynıdır (yeniden doğrulandı, bkz. bu belgenin altındaki "Yeniden hesaplanan sayılar").

### Haber Türü — kontrollü kelime dağarcığı

`mb_haber_turu` yalnız iki terimi barındırmak üzere tasarlanmıştır: `haber` ve `duyuru` (`tanitim-site/assets/data/news.js` içindeki mevcut ayrımla birebir), görünen Türkçe etiketleri sırasıyla "Haber" ve "Duyuru" olarak sabitlenmiştir (`MaviBelge_Core_Taxonomies::HABER_TURU_LABELS`). Kısıt hem **oluşturma** hem **güncelleme** yolunda uygulanır:

- Oluşturma: `pre_insert_term` filtresi (`wp_insert_term()`, imza `($term, $taxonomy, $args)` — `$term` her zaman terim adı metnidir, dizi değil) bu iki slug dışındaki her denemeyi `WP_Error` ile reddeder ve kabul edilen terimin adını kanonik Türkçe etikete çevirir.
- Güncelleme: `wp_update_term_data` filtresi (`wp_update_term()`, imza `($data, $term_id, $taxonomy, $args)`, WP 4.9.8+ — hedef 6.9.x hattında mevcuttur) bu iki terimin `slug`/`name` alanlarını her zaman orijinaline sabitler; `wp_update_term()`'in `pre_insert_term` gibi bir "reddet" filtresi olmadığından, kısıt değeri orijinaline **geri yazarak** uygulanır.

Bu fazda terim seed edilmedi; ekleme yetkisi olan bir kullanıcı ilk kullanımda bu iki terimi elle oluşturabilir.

## Alan sözleşmesi

Meta anahtarları `_mb_` önekiyle özeldir (WordPress "protected meta" kuralı — özel meta kutusu dışında meta kutusu API'sinde görünmez, `register_post_meta` ile REST'e kapalı olarak kayıtlıdır).

### Yeterlilik (`mb_yeterlilik`)

| Anahtar | Tür | Saklama biçimi |
|---|---|---|
| `_mb_myk_code` | text | string, trim edilmiş; boş olabilir |
| `_mb_level` | select | string `"1"`–`"8"` |
| `_mb_revision` | text | string, boş olabilir |
| `_mb_record_status` | select | `active` \| `passive` |
| `_mb_sort_order` | integer | `>= 0` |

**İş kuralı:** dolu `_mb_myk_code` için `kod + seviye + revizyon` üçlüsü başka bir yeterlilikte tekrar edemez. Çakışma tespit edilirse kayıt yazılmaz; yalnız çakışan `_mb_myk_code` alanı boşaltılır ve görünür Türkçe uyarı gösterilir (`class-meta-boxes.php::enforce_myk_uniqueness()`). Boş kodlar asla çakışmış sayılmaz. Benzersizlik sorgusu `post__not_in` ile mevcut kaydı hariç tutar (ilk sürümde yanlışlıkla `exclude` anahtarı kullanılmıştı — `WP_Query`/`get_posts()` bu anahtarı okumaz, bu yüzden kayıt kendi kendisiyle çakışıp her yeniden kaydedişte dolu bir kodu boşaltabiliyordu; düzeltildi, bkz. `class-validator.php::is_myk_combination_unique()`).

**Trash politikası:** Benzersizlik sorgusu `post_status` listesine `trash` **eklemez**. Çöpe atılmış bir yeterliliğin MYK kodu, başka (veya geri yüklenip yeniden düzenlenen aynı) bir kayıt tarafından serbestçe yeniden kullanılabilir; çöp aktif bir mükerrer sayılmaz.

**Biçim doğrulaması (Faz 6A Güvenlik/Şema/Sözleşme Kapanışı'nda güncellendi):** `_mb_myk_code` ve `_mb_qualification_code` (mb_ucret) doluysa gerçek MYK biçimine uymalıdır — **revizyon eki artık opsiyoneldir**: `^[0-9]{2}UY[0-9]{4}-[0-9]{1,2}(/[0-9]{2})?$` (ör. hem `10UY0002-3/03` HEM `13UY0145-3` geçerli). Gerçek kaynakta (`tanitim-site/assets/data/qualifications.js`, 83 kayıt) 63 kod revizyon ekiyle, 20 kod ekSİZ gelir — eski regex bu 20 gerçek kaydı reddediyordu (bkz. `raporlar/veri-aktarim-raporlari/faz6a-manifest-sozlesmesi.md` Bulgu #1, artık kapatıldı). `_mb_sector_slug` doluysa `a-z0-9` + tire biçiminde olmalıdır. Geçersiz biçim alanı **yazılmaz**, önceki değer korunur ve Türkçe bildirim gösterilir.

**Çapraz-alan kuralı (Faz 6A Son Kabul Düzeltmesi'nde kesinleştirildi):** `_mb_myk_code` DOLU ise, koda gömülü seviye `_mb_level` ile eşleşmelidir. Kod bir revizyon eki (`/NN`) taşıyorsa `_mb_revision` **tam olarak** o ek ile eşleşmelidir; kod eki taşımıyorsa `_mb_revision` **tam olarak boş** olmalıdır — ~~"serbest bir alan"~~ **DEĞİLDİR** (önceki metin yanlıştı: revizyonsuz bir kodla dolu bir `_mb_revision` değerinin birlikte var olabileceğini ima ediyordu; bağımsız inceleme bunun Node tarafındaki manifest doğrulayıcısıyla çeliştiğini gösterdi). Uyuşmazlıkta kayıt yazılır ama `_mb_myk_code` boşaltılır ve Türkçe bildirim gösterilir. Tek paylaşılan kural: `MaviBelge_Core_Validator::myk_code_matches_level_revision()` — hem `class-meta-boxes.php::enforce_myk_uniqueness()` (admin kaydı) hem `class-publish-readiness.php::check_yeterlilik()` (yayın kapısı) bu AYNI metodu çağırır; ikinci bir regex kopyası yoktur.

### Ücret kaydı (`mb_ucret`)

| Anahtar | Tür | Saklama biçimi |
|---|---|---|
| `_mb_qualification_id` | id (→ `mb_yeterlilik`) | integer, eşleşme yoksa `0` |
| `_mb_qualification_code` | text | string, boş olabilir — uydurulmaz |
| `_mb_profession_name` | text | string |
| `_mb_level` | select | string `"1"`–`"8"` |
| `_mb_sector_slug` | text | normalize slug (`a-z0-9-`) |
| `_mb_tariff_period` | text | kısa string, örn. `2026` |
| `_mb_valid_from`, `_mb_valid_until` | date | `Y-m-d` veya boş |
| `_mb_record_status` | select | `draft` \| `active` \| `archived` |
| `_mb_pricing_type` | select | `single` \| `unit` \| `package` \| **`multiple`** |
| `_mb_vat_included` | checkbox | boolean |
| `_mb_certificate_print_fee_kurus` | money_try | depoda kuruş; yönetim ekranında **TL** girilir/gösterilir (ör. `1.500,00`) |
| `_mb_source_name` | text | string |
| `_mb_source_page` | integer | `>= 0` |
| `_mb_source_attachment_id` | id (→ `attachment`) | integer, yoksa `0` |
| `_mb_price_options` | price_options | dizi (bkz. aşağıda) |
| `_mb_min_amount_kurus`, `_mb_max_amount_kurus` | integer, **salt okunur/türetilmiş/sistem yönetimli** | depoda kuruş; sunucu tarafında `_mb_price_options`'tan hesaplanır; yönetim ekranında her zaman **TL** olarak gösterilir (`kurus_to_lira_display()`), editör alanı doğrudan değiştiremez |

**Kasıtlı sapma — `multiple` değeri:** Görev promptu §8.2'de `_mb_pricing_type` için üç değer sayılmıştır (`single`, `unit`, `package`). Gerçek kaynak veride (`tanitim-site/assets/data/fees.js`) 103 kayıttan **2'si** `pricingType: "multiple"` taşır (yeniden sayıldı, bkz. altta). Bu şema `multiple`'ı dördüncü izinli değer olarak ekler; aksi halde bu iki kayıt kayıpsız taşınamaz ve görevin kendi "103/145 kayıpsız taşınabilir" kabul kriteri ihlal edilir. Statik veri veya alan sözleşmesi değiştirilmedi — yalnız izinli değer listesi genişletildi.

**Para birimi kuralı:** Tüm tutarlar **kuruş cinsinden integer**'dır, float değildir. `fees.js` kaynağındaki TL tutarları (ör. `17000`) tamsayıdır ve ondalık kullanmaz (yeniden doğrulandı: 145 `amount` değerinin hiçbirinde ondalık yok); Faz 6 importu bu tutarları ×100 ile kuruşa çevirecektir. Bu fazda gerçek import **yapılmadı**.

**Fiyat seçeneği yapısı** (`_mb_price_options` dizisinin her elemanı — depoda hep kuruş):

```text
label: zorunlu düz metin (deep-sanitize edilmiş, en fazla 200 karakter)
units: sıralı, boş olabilen düz metin listesi (her biri en fazla 50 karakter, en fazla 10 birim)
amount_kurus: pozitif integer
sort_order: sıfır veya pozitif integer, VERİLMEDİYSE satır pozisyonuna düşer
```

Bir kayıtta en fazla 20 fiyat seçeneği olabilir (`MaviBelge_Core_Validator::MAX_PRICE_OPTIONS`); bu sınırlar `max_input_vars` riskini sınırlar ve `docs/compatibility.md`'de de anılır.

**Katı atomik ret (Faz 5 Son Kapanış Düzeltmesi):** Önceki sürümde `units` listesindeki bozuk (iç içe dizi/nesne) bir öğe sessizce atlanıyor, `MAX_UNITS_PER_OPTION`'ı (10) aşan bir liste sessizce ilk 10'a kesiliyor, `MAX_UNIT_LENGTH`'i (50) aşan bir birim sessizce kısaltılıyordu — bu üç durum artık **hata üretir ve satırı reddeder** (ki bu, `evaluate_price_options()`/`evaluate_admin_price_rows()`'un atomik "tek satır bozuksa tüm liste reddedilir" kuralı gereği tüm listeyi reddeder). `sort_order` alanı **hiç gönderilmediyse** eski davranış (satır pozisyonuna düşme) korunur; ama alan **gönderilip** boş/negatif/sayısal-olmayan/dizi/nesne ise artık hata üretir — eskiden bu da sessizce pozisyona düşüyordu, "gönderilmedi" ile "gönderildi ama geçersiz" ayırt edilemiyordu. Yönetim formunun kendi boş (henüz numaralanmamış yeni satır) `sort_order` alanı, `evaluate_admin_price_rows()` tarafından "gönderilmedi" olarak ele alınmaya devam eder (`class-validator.php`, yalnız `sort_order` **dolu** ise anahtar `normalize_price_options()`'a iletilir) — yani bu katılaştırma, admin ekranındaki sıradan boş yeni satır davranışını bozmaz, yalnız gerçekten gönderilmiş geçersiz bir değeri artık kabul etmez.

**Kanonik, kararlı sıralama:** Eşit `sort_order` değerlerine sahip seçenekler artık her zaman kendi **gönderim sırasına** göre (deterministik tie-break) sıralanır — PHP 7.3'te `usort()` kararlı değildir, bu yüzden eşitlik durumunda gizli bir tie-break olmadan sonuç çalıştırmadan çalıştırmaya değişebilirdi. Karşılaştırıcı `<=>` (spaceship) kullanır, çıkarma değil — iki büyük `sort_order` değerinin çıkarılması taşıp yanlış sıralamaya yol açabilirdi.

`_mb_min_amount_kurus`/`_mb_max_amount_kurus`, kayıt her kaydedildiğinde `_mb_price_options`'tan sunucu tarafında yeniden hesaplanır (`class-meta-boxes.php::save_price_options()`); admin ekranında salt okunur gösterilir, doğrudan POST edilse bile geçersiz sayılır.

**Yönetim ekranı girdisi TL, depo kuruş:** Editör tutarı **TL** olarak girer (ör. `17.000` veya `17.000,00`); sunucu bunu tek, belgelenmiş bir kuralla kuruşa çevirir (`MaviBelge_Core_Validator::try_lira_to_kurus()`):

- `.` yalnız binlik ayraçtır ve tam 3'lü basamak grupları oluşturmalıdır (ör. `1.575.000`); 3 haneli grup oluşturmayan bir `.` reddedilir.
- `,` yalnız ondalık ayraçtır ve tam olarak 2 hane ile takip edilmelidir.
- İşaret, bilimsel gösterim veya harf kabul edilmez.
- Sonuç sıfır veya negatif olamaz.

Depoda gösterim için `MaviBelge_Core_Validator::kurus_to_lira_display()` aynı kuralı tersine uygular (ör. `1700000` → `"17.000,00"`).

**Taşma koruması:** `try_lira_to_kurus()`, çarpma/toplamadan **önce** sonucun `PHP_INT_MAX`'ı aşıp aşmayacağını kontrol eder ve aşacaksa `null` döner. PHP, `int*int` sonucu `PHP_INT_MAX`'ı aştığında hata vermeden sessizce `float`'a yükseltir; bu koruma olmadan aşırı büyük bir TL girişi hassasiyet kaybına uğrayan bir float "tutar" üretebilirdi. Dönüşün her zaman pozitif `int` olduğu ayrıca `is_int()` ile de doğrulanır (savunma katmanı).

**Tek ayrıştırma noktası (Faz 2 ikinci düzeltme):** Ham yönetim formu satırlarını (label/`amount_try`/units/sort_order) ayrıştırıp değerlendiren kod artık **tek yerde** yaşar: `MaviBelge_Core_Validator::evaluate_admin_price_rows()`. Hem `admin/class-meta-boxes.php::save_price_options()` hem de `includes/class-publish-readiness.php`'nin ücret hazırlık kontrolü bu fonksiyonu çağırır — ikinci, zamanla ayrışabilecek bir kopya yoktur. Bu fonksiyon her alanın (etiket, tutar, birim, sıra) **dizi/nesne mi yoksa scalar mı** olduğunu, `(string)` dönüşümünden **önce** kontrol eder; önceki sürümde bu kontrol cast'ten sonra yapıldığı için `label[]=x` gibi hazırlanmış bir istek `label` alanını sessizce `"Array"` metnine çevirip dizi/nesne reddi kuralını atlatabiliyordu. Hata mesajları satırın ham, doğrulanmamış değerini **asla** içermez — yalnız (kendisi de doğrulanmış) etiketi kullanır.

**Atomik kaydetme:** Fiyat seçenekleri listesi **tek parça** kaydedilir. Gönderilen dolu satırlardan **herhangi biri** geçersizse (kötü TL biçimi, boş etiket, sınır aşımı, beklenmeyen dizi/nesne değeri vb.) **hiçbir şey yazılmaz** — önceki `_mb_price_options`, `_mb_min_amount_kurus` ve `_mb_max_amount_kurus` aynen korunur ve Türkçe hata bildirimi gösterilir. Bu karar, saf ve doğrudan test edilebilir `evaluate_admin_price_rows()`/`evaluate_price_options()` fonksiyonlarının `replace` alanıyla temsil edilir; `class-meta-boxes.php::save_price_options()` yalnız `replace === true` olduğunda `update_post_meta` çağırır.

**Kayıt Durumu yetkilendirmesi:** `_mb_record_status`'ü `active` veya `archived` yapmak yalnız `publish_mb_ucretler` yetkisine sahip kullanıcılara (administrator, `mb_site_manager`, `mb_reviewer`) açıktır; `mb_price_editor` bunu **yapamaz**. Yönetim ekranında bu iki seçenek, yetkisi olmayan kullanıcı için `<option disabled>` ile görsel olarak da kapatılır (yanıltıcı, çalışmayan bir kontrol gösterilmez) — ancak gerçek zorunlu kural sunucu tarafındadır: `class-meta-boxes.php::guard_ucret_record_status()`, genel alan döngüsünün yazdığı değeri, yetkisiz bir geçiş tespit ederse eski değere geri döndürür (doğrudan POST manipülasyonuna karşı da geçerlidir).

**Aktif tarife dönemi:** Ziyaretçiye gösterilecek dönem, çok sayıda `mb_ucret` kaydından **bağımsız**, tek bir `mb_active_tariff_period` option'ı ile temsil edilir (`admin/class-settings.php`, "Ücret Kaydı → Aktif Tarife Dönemi" ekranı). Yalnız `mb_manage_tariff_period` yetkisi olan kullanıcı (administrator, `mb_site_manager`) değiştirebilir; `mb_price_editor` değiştiremez. Bu fazda ekran ve option mekanizması kuruldu; gerçek bir dönem ataması veya ücret importu **yapılmadı**.

### Haber (`mb_haber`)

WordPress'in yerleşik başlık/özet/içerik/öne çıkan görsel/yayın tarihi alanlarına ek olarak:

| Anahtar | Tür | Saklama biçimi |
|---|---|---|
| `_mb_content_updated_at` | datetime | `Y-m-d H:i:s` veya boş |
| `_mb_reviewer_user_id` | user_id, **sistem yönetimli** | integer, atanmamışsa `0` |
| `_mb_reviewed_at` | datetime, **sistem yönetimli** | `Y-m-d H:i:s` (UTC) veya boş |
| `_mb_approval_status` | select | `draft` \| `in_review` \| `approved` \| `rejected` |
| `_mb_import_source_key` | text, **sistem yönetimli** (salt okunur) | `news:<slug>` veya boş — yalnız `wp mavibelge import catalog --stage=content` yazar (bkz. `content-import-contract.md`) |
| `_mb_last_applied_hash` | text, **sistem yönetimli** (salt okunur) | tam 64 küçük-hex veya boş |

**Onay geçişi yetkilendirmesi:** `_mb_approval_status`'ü `approved` veya `rejected` yapmak yalnız `publish_mb_haberler` yetkisine sahip kullanıcılara açıktır; `mb_content_editor` en fazla `draft`/`in_review` seçebilir. Yetkisiz bir geçiş denemesi sunucu tarafında eski değere geri döndürülür (`class-meta-boxes.php::guard_haber_approval_status()`), doğrudan POST manipülasyonu dahil.

**`_mb_reviewer_user_id` ve `_mb_reviewed_at` hiçbir zaman serbest alan olarak sunulmaz.** İkisi de `readonly` + `system_managed` işaretlidir: yönetim ekranında düzenlenebilir bir ID/tarih kutusu yerine salt okunur, okunabilir metin gösterilir ("İncelendi: Ad Soyad (ID 5)" gibi) ve genel alan kaydetme döngüsü tarafından hiçbir zaman işlenmez. Tek yazma noktaları, yetkili bir kullanıcının `_mb_approval_status`'ü fiilen `approved`/`rejected`'a **geçirdiği** an (`old !== 'approved'/'rejected'` iken yeni değer bunlardan biri olduğunda) — bu anda sunucu geçerli kullanıcıyı ve geçerli UTC zamanını yazar; aynı durumda tekrar kaydetmek zaman damgasını yeniden yazmaz.

SEO meta alanları bu modele eklenmedi; SEO/AIO agentının tanımlayacağı ayrı sözleşmedir (görev kartı 05).

### Doküman (`mb_dokuman`)

| Anahtar | Tür | Saklama biçimi |
|---|---|---|
| `_mb_attachment_id` | id (→ `attachment`) | integer, yoksa `0` |
| `_mb_document_version` | text | string |
| `_mb_publish_date`, `_mb_valid_until` | date | `Y-m-d` veya boş |
| `_mb_record_status` | select | `active` \| `passive` |
| `_mb_related_qualification_ids` | id_list (→ `mb_yeterlilik`) | integer dizisi, yalnız gerçek `mb_yeterlilik` ID'leri |

Hassas/özel aday belgesi yükleme sistemi bu modelde **yoktur**; `mb_dokuman` yalnız kamuya açık kurumsal dokümanlar içindir.

### Referans (`mb_referans`)

| Anahtar | Tür | Saklama biçimi |
|---|---|---|
| `_mb_logo_attachment_id` | id (→ `attachment`) | integer, yoksa `0` |
| `_mb_website_url` | url | `esc_url_raw` ile temizlenmiş veya boş |
| `_mb_sort_order` | integer | `>= 0` |
| `_mb_reference_status` | select | `real` \| `representative`, **varsayılan `representative`** |
| `_mb_record_status` | select | `active` \| `passive` |
| `_mb_import_source_key` | text, **sistem yönetimli** (salt okunur) | `reference:<slug>` veya boş (bkz. `content-import-contract.md`) |
| `_mb_last_applied_hash` | text, **sistem yönetimli** (salt okunur) | tam 64 küçük-hex veya boş |

### Lokasyon (`mb_lokasyon`)

| Anahtar | Tür | Saklama biçimi |
|---|---|---|
| `_mb_address` | textarea | düz metin |
| `_mb_phone_numbers` | phone_list | normalize edilmiş telefon dizisi |
| `_mb_map_url` | url | `esc_url_raw` ile temizlenmiş veya boş |
| `_mb_working_hours` | textarea (sınırlı) | en fazla 500 karakter |
| `_mb_sort_order` | integer | `>= 0` |
| `_mb_record_status` | select | `active` \| `passive` |

Koordinat (enlem/boylam) alanı bu fazda **eklenmedi** — brief'in "aksi halde bu fazda ekleme" koşulu gereği.

### SSS (`mb_sss`)

Başlık = soru, içerik = cevap.

| Anahtar | Tür | Saklama biçimi |
|---|---|---|
| `_mb_sort_order` | integer | `>= 0` |
| `_mb_record_status` | select | `active` \| `passive` |
| `_mb_related_page_ids` | id_list (→ `page`) | integer dizisi, yalnız gerçek `page` ID'leri |
| `_mb_related_qualification_ids` | id_list (→ `mb_yeterlilik`) | integer dizisi |

FAQ şeması bu fazın kapsamı dışındadır (SEO/AIO agentı, görev kartı 05).

## Tek alan doğrulayıcı — `MaviBelge_Core_Field_Repository`

Faz 2 ikinci düzeltmesiyle, "bir alanı doğrula" mantığı tek bir yerde toplandı: `includes/class-field-repository.php`. Üç tüketicisi var:

1. **`sanitize_and_validate( $config, $raw )`** — bir alan şeması ve ham değeri alır, `array($clean, $error)` döner. `admin/class-meta-boxes.php::save()` ve `includes/class-publish-readiness.php` **aynı** bu metodu çağırır; ikinci, ayrışabilecek bir kopya yoktur. Her `*_list` türü (`id_list`/`phone_list`/`string_list`) hem çok satırlı metin (yönetim formu) hem de doğrudan dizi (programatik yazım) girdisini kabul eder — hangisi olduğu `(string)` dönüşümünden **önce** kontrol edilir.
2. **`resolve_effective_value( $meta_key, $config, $post_id, $postarr )`** — bir alanın "geçerli" değerini, belgelenmiş öncelik sırasıyla çözer: **(1)** bu isteğin `$_POST`'u (doğrulanmış) → **(2)** `$postarr['meta_input']` (doğrulanmış) → **(3)** veritabanındaki mevcut değer (yazıldığı anda zaten doğrulanmış kabul edilir). `includes/class-publish-readiness.php` yayın hazırlığını buna göre değerlendirir.
3. **`write_meta( $post_id, $post_type, $meta_key, $raw_value )`** — `array('success'=>bool, 'value'|'error')` döner. `register_post_meta()`'nun `sanitize_callback`'i (aşağıda) reddedemez, yalnız temizleyebilir; bu metot gerçek başarı/hata geri bildirimi isteyen programatik yazıcılar (Faz 6 import akışının kullanması **beklenir**) içindir. **Yetkilendirme yapmaz** — `current_user_can()` kontrolü içermez; yalnız yetki kontrolünü önceden yapmış güvenilir iç kod tarafından çağrılmalıdır (bkz. aşağıdaki "`auth_callback` — kapsam ve gerçek sınırı" bölümü). Bu fazda hiçbir çağıran bu metodu kullanmıyor — yalnız altyapı olarak hazır. Gelecekte bir yönetim ekranı veya HTTP giriş noktasına bağlanırsa, o çağıran ilgili CPT capability'sini kendisi kontrol etmek zorundadır.

## `register_post_meta` sanitizer'ı — alan-duyarlı, ama reddedemez

`MaviBelge_Core_Meta_Schema::sanitize_for_registration()`, meta anahtarına göre doğru alan şemasını bulur ve `Field_Repository::sanitize_and_validate()`'i çağırır.

**Gerçek WordPress callback imzası (son düzeltmede düzeltildi):** `sanitize_meta()` ve `register_meta()` (wp-includes/meta.php) doğrulandı: `register_post_meta( $post_type, $key, $args )` içeride `register_meta( 'post', $key, array_merge( $args, array( 'object_subtype' => $post_type ) ) )` çağırır, bu da `"sanitize_{$object_type}_meta_{$meta_key}_for_{$object_subtype}"` etiketli, **alt türe özel** filtreyi bağlar. `sanitize_meta()` bu filtreyi **dört** argümanla çağırır: `apply_filters( $tag, $meta_value, $meta_key, $object_type, $object_subtype )`. `$object_type` burada genel türdür — yalnız `"post"` metni — gerçek içerik türü (ör. `mb_ucret`) yalnız **dördüncü** argüman olan `$object_subtype`'tır.

Önceki sürüm callback'i yalnız üç parametreyle bildiriyordu (`$meta_value, $meta_key, $object_subtype`); PHP fazla argümanı sessizce yok saydığı için üçüncü parametre gerçekte hep `"post"` değerini alıyordu — `self::get_fields_for('post')` her zaman boş döner, yani alan-duyarlı dal **hiç çalışmıyordu**. Her post type için her alan, "bilinmeyen meta anahtarı" dalına düşüp yalnız `is_string()` + `sanitize_text_field()` temizliğinden geçiyordu; array değerler (`price_options`, `id_list`, `phone_list`, `string_list`) **hiç temizlenmeden** olduğu gibi kaydedilebiliyordu. Düzeltme: callback artık dört parametre alıyor (`$object_type`, `$object_subtype`), ve `$object_type !== 'post'` veya `$object_subtype` boşsa (şema çözülemiyorsa) ham array/object değeri **asla** olduğu gibi geçirmiyor — boş diziye düşürüyor (`generic_safe_fallback()`).

**Belgelenmiş sınır (imza düzeltmesinden bağımsız, hâlâ geçerli):** `register_post_meta()`'nun `sanitize_callback`'i yazımı **reddetme** imkânı vermez, yalnız temizleyebilir. Bu nedenle geçersiz bir değer, önceki (muhtemelen doğru) değeri korumak yerine alanın güvenli boş/varsayılan değerine (`safe_default_for_type()`) indirgenir. Bu, yönetim ekranındaki "eski değeri koru + bildirim göster" davranışından **kasıtlı olarak farklıdır**. Gerçek doğrulama geri bildirimi gereken her yeni programatik yazıcı `Field_Repository::write_meta()`'yı kullanmalıdır.

## `auth_callback` — kapsam ve gerçek sınırı

`MaviBelge_Core_Meta_Schema::auth_callback()` artık yalnız `current_user_can('edit_post', ...)` değil; `get_post_type($post_id)` ile gerçek post type'ı çözer, alanı o türün şemasında arar ve `readonly`/`system_managed` işaretli alanlar (ör. `_mb_reviewer_user_id`, `_mb_min_amount_kurus`) için **her zaman** `false` döner — bilinmeyen alan/post type da `false`.

**Gerçek sınırı:** Bu callback yalnız WordPress'in **kendi** meta-yetki mekanizmasını (REST meta uçları, `current_user_can('edit_post_meta', ...)` gibi çağrılar) kapılar. Eklentinin kendi PHP kodunun doğrudan çağırdığı `update_post_meta()`/`get_post_meta()` bu callback'i **hiç görmez** — WordPress'in düşük seviye meta fonksiyonları yetki kontrolüne hiç danışmaz.

**İki farklı şeyi birbirine karıştırmamak gerekir:**

- `admin/class-meta-boxes.php::save()` ve `guard_ucret_record_status()`/`guard_haber_approval_status()` gibi guard'lar **kendi** `current_user_can()` kontrolünü doğrudan yapar — gerçek yetkilendirme uygular.
- `MaviBelge_Core_Field_Repository::write_meta()` **hiçbir** `current_user_can()` kontrolü yapmaz (kendi docblock'unda da böyle belirtilir). Bu, bir yetkilendirme katmanı değil, yalnız veri doğrulama + kontrollü yazma API'sidir. Yalnız çağıranın ilgili CPT için yetki kontrolünü **önceden** yaptığı güvenilir iç servis/import kodundan çağrılmalıdır — bu callback ona hiçbir koruma sağlamaz.

## Doğrulama akışı (özet)

Sıra (`admin/class-meta-boxes.php::save()`): 1) autosave/revizyon kontrolü → 2) nonce → 3) o post type'ın kendi `edit_post` meta cap'i → 4) alan bazlı sanitize+validate (`Field_Repository::sanitize_and_validate()`) → 5) geçersiz alan tespit edilirse o alan **yazılmaz**, önceki değer korunur ve Türkçe bildirim kuyruğa alınır (`admin/class-admin-notices.php`). `id`/`user_id`/`id_list` alanları hedef post/kullanıcının **gerçekten var olduğunu** doğrular; uydurma veya silinmiş ID sessizce düşer. `id_list` biçim kontrolü **cast'ten önce** yapılır (`MaviBelge_Core_Validator::is_valid_positive_integer_string()`) — aksi halde PHP'nin `(int)"123abc"` ifadesi sessizce `123` üretir ve geçersiz bir girdi geçerli bir ID gibi kabul edilebilirdi.

## Yayın öncesi zorunlu alan doğrulaması

`MaviBelge_Core_Publish_Readiness` (`includes/class-publish-readiness.php`), bir kaydın `post_status = publish`'e **gerçekten** ulaşmasını, aşağıdaki asgari alanlar dolu olana kadar engeller:

| Post type | Yayın için zorunlu |
|---|---|
| `mb_yeterlilik` | başlık, geçerli seviye (1-8), en az bir **gerçekten var olan** `mb_sektor` terimi (MYK kodu opsiyonel kalır) |
| `mb_ucret` | meslek adı, geçerli seviye, **geçerli biçimde** sektör slug, tarife dönemi, **listede geçerli** bir fiyatlandırma türü, gönderilen dolu fiyat satırlarının **tamamı** geçerli olmalı ve en az bir seçenek kalmalı (bağlı yeterlilik ID'si `0` olabilir) |
| `mb_haber` | başlık, içerik, `_mb_approval_status === 'approved'` |
| `mb_dokuman` | başlık, geçerli (var olan) attachment ID |
| `mb_referans` | başlık, geçerli (var olan) logo attachment ID |
| `mb_lokasyon` | başlık, adres |
| `mb_sss` | başlık (soru), içerik (cevap) |

**Kaydetmeyle birebir aynı kurallar (ikinci düzeltme):** Önceki sürüm `_mb_sector_slug` ve `_mb_pricing_type` için yalnız "boş değil mi" kontrolü yapıyordu — biçim/enum kontrolü yoktu, bu yüzden bir istek yayın kapısını geçip gerçek kaydetmede reddedilebiliyordu. Artık her zorunlu alan `MaviBelge_Core_Field_Repository::resolve_effective_value()` üzerinden okunur; bu, kaydetmenin kullandığı **aynı** `sanitize_and_validate()`'i çalıştırır, yani slug biçimi ve `_mb_pricing_type`'ın `MaviBelge_Core_Meta_Schema::PRICING_TYPES` kümesinde olması da yayın öncesinde zorunlu hale gelir.

**Sektör terimi doğrulaması:** `tax_input['mb_sektor']` içindeki her aday ID, `get_term( $id, 'mb_sektor' )` ile **gerçekten var olan** bir terime karşılık gelmelidir; yalnız `! empty()` yeterli değildir. `mb_sektor` hiyerarşiktir ve WordPress'in varsayılan kategori tarzı meta kutusunu kullanır (özel `meta_box_cb` tanımlanmadı) — bu meta kutusunda "+ Yeni Ekle" her zaman **ayrı, önceki** bir AJAX isteğiyle (`add-tag`) çalışır ve dönen gerçek `term_id` ana form gönderiminde onay kutusu olarak gelir. Bu yüzden "aynı istekte yeni terim oluşturma" yarışı `mb_sektor` için **oluşmaz** — bu varsayım kodda açıkça belgelenmiştir (`has_sector_term()`), ancak gerçek bir WordPress oturumuyla doğrulanmadı.

**Fiyat seçenekleri — "en az bir" yetmez:** `mb_ucret` yayın kontrolü artık `MaviBelge_Core_Validator::evaluate_admin_price_rows()`'un (kaydetmenin kullandığı **aynı** fonksiyon) `replace === true` sonucunu ister — yani gönderilen dolu satırlardan biri bile geçersizse (önceki: yalnız "en az bir geçerli seçenek var mı" bakılırdı) yayın engellenir.

**Üç katmanlı çözümleme (Faz2 ikinci düzeltme brief §4):** 1) bu isteğin doğrulanmış `$_POST` alanı → 2) `$postarr['meta_input']` (doğrulanmış) → 3) veritabanındaki mevcut `postmeta` (güvenilir, çünkü yalnız daha önce bu doğrulamadan geçerek oraya ulaşabilmiştir). Fiyat seçenekleri için ayrı ama paralel bir üç katman vardır: `$_POST['_mb_price_options']` (ham yönetim formu şekli) → `$postarr['meta_input']['_mb_price_options']` (eklentinin kendi normalize edilmiş DB şekli — kuruş zaten hesaplanmış; programatik/Faz 6 import sözleşmesi budur) → kayıtlı `postmeta`.

**Mekanizma:** `wp_insert_post_data` filtresine bağlıdır — bu filtre `wp_insert_post()`'un veritabanına yazmadan **önce** çalışır ve `$data['post_status']`'ü değiştirebilir. Zorunlu alan eksik/geçersizse durum otomatik olarak önceki (yayınlanmamış) duruma veya `draft`'a düşürülür ve Türkçe bildirim gösterilir. Bilinçli olarak `save_post` + `wp_update_post()` deseni **kullanılmaz**: bu ikinci yazma her zaman `save_post`'u yeniden tetikler ve sonsuz döngüyü önlemek için yeniden-giriş (re-entrancy) koruması gerektirirdi — `wp_insert_post_data` filtresi tek bir yazımla çalıştığından bu sınıf hatası baştan yoktur.

## Denetim günlüğü — izlenen kritik alanlar

`_mb_record_status` (tüm türler) ve `_mb_approval_status` (haber) değişiklikleri zaten izleniyordu. Ek olarak şu alanlar, **yalnız gerçek bir değişiklikte** (ilk kez doldurulma hariç) denetim günlüğüne yazılır:

| Post type | İzlenen alan | "Boş" sentinel (ilk atama sayılmaz) |
|---|---|---|
| `mb_ucret` | `_mb_qualification_id` (ilişki) | `0` |
| `mb_ucret` | `_mb_tariff_period` | `''` |
| `mb_ucret` | `_mb_pricing_type` | `''` |
| `mb_ucret` | `_mb_source_name` | `''` |
| `mb_referans` | `_mb_reference_status` (gerçek/temsili) | `''` |
| `mb_dokuman` | `_mb_attachment_id` (doküman eki) | `0` |

Adres, telefon, uzun içerik, nonce, parola, token, dosya içeriği veya kişisel veri **hiçbir zaman** audit context'e yazılmaz; yukarıdaki alanların tamamı kısa ID/enum/başlık niteliğindedir.

**Faz 6B3 — katalog içe aktarım olayları:** `import_run_started`, `import_batch_committed`, `import_run_completed`, `import_run_failed`, `import_rollback_started`, `import_rollback_completed`, `import_rollback_failed` (`object_type` = `mb_import_run`). Context yalnız `MaviBelge_Core_Import_Audit_Context::build()`'in kapalı izin listesinden geçer: run ID, source key, tür, karar, hedef ID, eski/yeni hash, değişen alan ADLARI, sabit hata kodu, batch/checkpoint numarası. Alan içeriği yazılmaz. Rollback verisi audit tablosunda değil, ayrı `mb_import_runs` / `mb_import_run_items` tablolarındadır; ayrıntı `raporlar/veri-aktarim-raporlari/faz6b3-apply-mimarisi.md`.

**Kurulum başarısızlığı:** `MaviBelge_Core_Audit_Log::install()`, `dbDelta()` sonrasında tablonun **gerçekten** oluştuğunu `SHOW TABLES` ile doğrular; yalnız doğrulama geçerse sürüm option'ı yazılır. Doğrulama başarısız olursa (ör. yetersiz veritabanı yetkisi) sürüm option'ı **yazılmaz** ve bir sonraki `admin_init`'te kurulum yeniden denenir; hata yalnız sunucu `error_log`'una düşer, ziyaretçiye hiçbir çıktı verilmez. `record()`, yazmadan önce tablonun var olduğunu kontrol eder ve INSERT sonucunu döndürür; tablo yoksa veya yazım başarısızsa sessizce `false` döner — asla fatal hata veya ziyaretçi çıktısı üretmez.

**LIKE joker düzeltmesi (ikinci düzeltme):** `SHOW TABLES LIKE %s` sorgusunda tablo adı `$wpdb->esc_like()` ile temizlenmeden geçiliyordu. `_` ve `%` MySQL `LIKE` joker karakterleridir; `$wpdb->prefix` genelde `_` içerir (ör. `wp_`), bu yüzden tablo adı literal değil joker desen olarak yorumlanabiliyordu — benzer adlı başka bir tablo yanlışlıkla "var" sayılabilirdi. `esc_like()` artık `%`/`_`'yi kaçırıyor, `prepare()` hâlâ tırnaklamayı yapıyor.

## Yeniden hesaplanan sayılar (bu görevde)

Kaynak dosyalar doğrudan sayıldı (uydurulmadı, kopyalanmadı):

| Kaynak | Sayım komutu (özet) | Sonuç |
|---|---|---:|
| `sectors.js` | satır sayımı | 14 |
| `qualifications.js` | `grep -c '^\s*{ code:'` | 83 |
| `fees.js` ana kayıt | `grep -c 'pricingType:'` | 103 |
| `fees.js` fiyat seçeneği | `grep -o 'label:' \| wc -l` | 145 |
| `fees.js` boş `qualificationCode` | `grep -c 'qualificationCode: ""'` | 19 |
| `fees.js` dolu `qualificationCode` | `grep -cE 'qualificationCode: "[^"]+"'` | 84 |
| `news.js` | satır bazlı kayıt sayımı | 6 (3 haber + 3 duyuru) |
| `references.js` | satır bazlı kayıt sayımı | 12 (tümü temsili) |
