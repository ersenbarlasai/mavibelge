# Yönetim Deneyimi — Meslek/Ücret (Faz 5)

> Kaynak: `admin/class-list-columns.php`, `admin/class-list-filters.php`, `admin/class-settings.php`. Faz 2/3'ten kalan meta kutusu, doğrulama zinciri, rol/yetenek ve denetim günlüğü davranışı **değişmedi** — bu belge yalnız Faz 5'te eklenen liste/filtre/dönem-uyarısı katmanını anlatır.
>
> **Faz 5 Düzeltme ve Kabul (12 Eylül 2026):** aktif dönem uyarısı yanlış sayacı kullanıyordu (§4 düzeltildi); admin aramasında `$_GET['s']` için dizi/nesne şekil kontrolü eksikti (§3 düzeltildi); `class-list-filters.php`'de kullanılmayan bir `global $wpdb;` bildirimi vardı (kaldırıldı).

## 1. `mb_yeterlilik` liste ekranı

Eklenen kolon: **Sektör** (`mb_sector` — gerçek `mb_sektor` terimlerinin adı, yoksa `—`).

Eklenen filtreler (`restrict_manage_posts`): Durum (Aktif/Pasif), Seviye (1–8), Sektör (gerçek terimler). Admin arama kutusu artık meslek **başlığı** ile birlikte **MYK kodunu** da bulur (bkz. §3).

## 2. `mb_ucret` liste ekranı

Eklenen kolonlar: Meslek (`_mb_profession_name`), MYK Kodu (`_mb_qualification_code` — boşsa "—", asla uydurulmaz), Seviye, Sektör (`_mb_sector_slug` düz metin — bu bir taksonomi ilişkisi **değildir**, bkz. `docs/content-model.md`), Fiyatlandırma Türü (Türkçe etiket), Bağlı Yeterlilik (yalnız `current_user_can('edit_post', $qualification_id)` true ise gerçek düzenleme bağlantısı; değilse yalnız başlık metni — brief §8.2 "yalnız yetkili kullanıcıya gösterilebilir"). Mevcut Dönem/Durum/Fiyat Aralığı kolonları korunur.

Eklenen filtreler: Durum (Taslak/Aktif/Arşivlendi), Seviye, Sektör (gerçek terimlerin slug'larından — `_mb_sector_slug` metin alanı gerçek terim adlarıyla eşleştirilir), Fiyatlandırma Türü, **Dönem** (bkz. §4 — gerçek kayıtlardan türetilmiş, zorlamayan öneri listesi). Admin arama meslek adı **ve** MYK kodunu bulur.

Türetilmiş `_mb_min_amount_kurus`/`_mb_max_amount_kurus` alanları hâlâ editlenemez (Faz 2'den beri `readonly`/`system_managed`); bu fazda değişmedi.

## 3. Meta-duyarlı admin arama — neden ham SQL değil

`admin/class-list-filters.php::apply_meta_aware_search()`, mevcut dropdown filtrelerinin (durum/seviye/sektör/dönem) zaten daralttığı aday kümesini `get_posts()` ile (sınırlı, `SEARCH_CANDIDATE_CAP = 500`) çeker, sonra **aynı** ön yüz servisinin kullandığı `MaviBelge_Core_Catalog_Query::text_contains_ci()` ile başlık/ilgili-meta alanını PHP'de eşleştirir ve sonucu `post__in`'e çevirir. `$wpdb`/ham SQL **kullanılmaz**; WordPress'in kendi `'s'` motoru ile meta OR'u birleştirmenin güvenli tek yolu budur (bkz. `docs/catalog-service-contract.md` §4). Eşleşme yoksa `post__in => array(0)` — asla "filtreyi yok say, hepsini göster"e düşmez.

**Faz 5 Düzeltme ve Kabul §3.5:** `$query->get('s')` sonucu, `(string)`'e çevrilmeden önce dizi/nesne olup olmadığı kontrol edilir (öyleyse arama sessizce atlanır — "Array" metnini aramaya çalışmaz) ve ardından merkezi `wp_unslash()` adımından geçirilir; ön yüz GET sözleşmesiyle aynı sıralama.

Bu arama yalnız `edit.php?post_type=mb_yeterlilik` ve `edit.php?post_type=mb_ucret` ekranlarında çalışır (`current_screen_post_type()` kapısı); başka hiçbir post type'ın sorgusuna sızmaz.

## 4. Aktif Tarife Dönemi ekranı — Faz 5 eklemeleri

- **Uyarı:** Dönem boşsa "boşken ziyaretçiye hiçbir ücret gösterilmez" uyarısı. Dönem doluysa ama `MaviBelge_Core_Catalog_Service::get_active_fee_count()` `0` dönüyorsa (bu döneme ait, aktif, geçerlilik penceresi içinde, dolu fiyat listeli **hiçbir** kayıt yok) — açık, isim veren bir uyarı gösterilir. Ziyaretçiye hiçbir ek bilgi sızmaz; bu yalnız yönetim ekranında görünür. **Faz 5 Düzeltme ve Kabul §2.4:** eskiden bu uyarı `get_active_fee_qualification_ids()`'in (yalnız gerçek bir yeterliliğe bağlı ücretleri sayan, `mb_priced` filtresine özel bir metot) boş olup olmadığına bakıyordu — `_mb_qualification_id = 0` (brief §2'nin açıkça izin verdiği "eşleşme yoksa boş bırakılabilir" durumu) olan ama diğer her kuralı geçen, gerçekten görünür ücretler varken bile yanlışlıkla "eşleşen ücret yok" diyebiliyordu.
- **Öneri listesi (`<datalist>`):** mevcut `mb_ucret` kayıtlarında **gerçekten kullanılan** `_mb_tariff_period` değerlerinden türetilir. Serbest metin `<input>` **dropdown'a çevrilmedi** — brief §8.3'ün "kayıt yokken zorla dropdown'a çevirip yönetimi kilitleme" yasağı korunur; `<datalist>` yalnız tarayıcı otomatik-tamamlama önerisidir, girişi kısıtlamaz.

## 5. Bu fazda yapılmayanlar

CSV import/export, toplu fiyat güncellemesi, gerçek 2026 verisi yazımı, sektör/yeterlilik/ücret seed etme, otomatik aktifleştirme/arşivleme, tahminle fiyat eşleştirme — hepsi Faz 6'nın kapsamındadır ve bu görevde **yapılmamıştır**.
