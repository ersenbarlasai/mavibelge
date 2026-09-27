# Mavi Belge WordPress Sitesi — `wordpress-site/`

Bu klasör, Mavi Belge'nin WordPress mimarisine ait özel tema ve özel eklenti kaynak kodunu içerir. Faz 1 minimal, çalışabilir iskeleti kurdu. Faz 2, `mavibelge-core` eklentisine içerik modelini (7 CPT, 4 taksonomi, alan sözleşmesi, roller, denetim günlüğü) ekledi — ayrıntı: [`docs/content-model.md`](docs/content-model.md), [`docs/roles-capabilities.md`](docs/roles-capabilities.md). Faz 3, temaya onaylı statik tasarımın ortak kabuğunu (header/footer, tasarım tokenları, global bileşen kataloğu) ekledi — ayrıntı: [`docs/design-system.md`](docs/design-system.md), [`docs/component-catalog.md`](docs/component-catalog.md). Faz 4, 41/41 statik sayfanın WordPress şablon karşılığını ekledi — ayrıntı: [`docs/page-template-map.md`](docs/page-template-map.md), [`docs/template-architecture.md`](docs/template-architecture.md); ardından Faz 4 Nihai Kabul Düzeltmesi ve Dokümantasyon Kapanışı ile Faz 4 statik kod incelemesi düzeyinde kabul edildi. Faz 5, `mavibelge-core`'a tek genel katalog servisini (`public/class-catalog-service.php` — meslek/sektör arama, aktif tarife dönemi kurallarına uyan ücret sorguları) ve temaya gerçek arama/filtre/ücret gösterimini ekledi — ayrıntı: [`docs/catalog-service-contract.md`](docs/catalog-service-contract.md), [`docs/admin-catalog-experience.md`](docs/admin-catalog-experience.md). Veri importu ve üçüncü taraf eklenti seçimi henüz eklenmemiştir.

Bağlayıcı mimari karar: [`../raporlar/wordpress-ana-uygulama-plani.md`](../raporlar/wordpress-ana-uygulama-plani.md). PHP 7.3 karar kaydı: [`../raporlar/karar-kaydi-wordpress-php73.md`](../raporlar/karar-kaydi-wordpress-php73.md).

## Kök ve sınırlar

- Depo çalışma dizini: `E:\PROJELER\MaviBelge`
- WordPress özel kod kökü: `wordpress-site/` (bu klasör)
- [`../tanitim-site/`](../tanitim-site/) yalnız okunur görsel referanstır; buradan hiçbir dosya kopyalanmamıştır.
- WordPress çekirdeği, `wp-config.php`, uploads, cache, log ve veritabanı dump'ları Git'e **girmez** (bkz. `.gitignore`).
- Bu fazda yerel WordPress runtime **oluşturulmamıştır**.
- Üretimde Composer/Node bulunması **gerekmez**; derleme yerelde yapılır.
- Staging hedefi `/domains/cms-yeni.mavibelge.com.tr/public_html` — henüz oluşturulmamıştır.
- Statik referans `/domains/yeni.mavibelge.com.tr/public_html` üzerine yazılmaz.
- Üretim `/domains/mavibelge.com.tr/public_html` son kabulden önce değiştirilmez.
- Dosya yükleme yöntemi ve gerçek sunucu yolları DevOps fazında doğrulanacaktır.

## Yapı

```text
wordpress-site/
├── composer.json          PHP >=7.3 hedefi; çalışma zamanı bağımlılığı yok
├── package.json            Yalnız metadata; bağımlılık yok, npm install çalıştırılmadı
├── docs/                   Mimari, uyumluluk, içerik modeli, rol matrisi, tasarım sistemi, bileşen kataloğu, dağıtım sözleşmesi
├── wp-content/
│   ├── themes/mavibelge/   Özel tema (Faz 1 iskeleti) — bkz. aşağıda
│   ├── plugins/mavibelge-core/  Özel eklenti (Faz 2 içerik modeli) — bkz. aşağıda
│   └── mu-plugins/         Boş, ileride kullanılacak
├── tools/                  Boş, ileride kullanılacak
├── tests/                  Boş, ileride kullanılacak (depo kökü — eklenti kendi testlerini `mavibelge-core/tests/` içinde tutar)
└── deploy/                 Boş, ileride kullanılacak (DevOps agentının alanı)
```

## Tema (`wp-content/themes/mavibelge/`)

Faz 3 ile: `header.php`/`footer.php` (onaylı statik tasarımın kabuğu — trust bar, sticky header, çok seviyeli menü, MYK/TÜRKAK güven bloğu, footer sütunları/lokasyon/back-to-top), özel `MaviBelge_Nav_Walker` (`inc/class-nav-walker.php`), menü atanmadan önceki güvenli fallback (`inc/menu-fallback.php` — asla `href="#"` üretmez), tasarım tokenları + global bileşen CSS (`assets/src/css/**` → `assets/dist/style.css`), erişilebilir navigasyon JS (`assets/src/js/**` → `assets/dist/main.js`), 11 parçalık yeniden kullanılabilir bileşen kataloğu (`template-parts/components/**`), orijinal 4 logo dosyası (SHA-256 doğrulanmış, `assets/images/logos/`). Ayrıntı: [`docs/design-system.md`](docs/design-system.md), [`docs/component-catalog.md`](docs/component-catalog.md).

Faz 4 ile eklendi: `front-page.php` (9 bölümlü onaylı ana sayfa düzeni), `page.php` + slug→layout haritası (hub/prose/form-disabled), 41/41 statik sayfanın WordPress karşılığı — bkz. [`docs/page-template-map.md`](docs/page-template-map.md), [`docs/template-architecture.md`](docs/template-architecture.md).

Faz 5 ile eklendi: gerçek meslek/sektör arama-filtre formu (`template-parts/catalog/**`), yeterlilik detayında ve `page-sinav-ucretleri.php`'de doğrulanmış aktif ücret gösterimi, ana sayfa hero aramasının gerçek GET akışı — tamamı `inc/catalog-helpers.php` üzerinden `mavibelge-core`'un tek katalog servisini çağırır, kendi sorgusunu yazmaz. Bu fazda **hâlâ yok**: gerçek form gönderimi, veri importu, SEO/AIO alanları — bkz. [`docs/faz3-dependencies.md`](docs/faz3-dependencies.md), [`docs/catalog-service-contract.md`](docs/catalog-service-contract.md).

## Eklenti (`wp-content/plugins/mavibelge-core/`)

Faz 2 ile: 7 özel içerik türü (`includes/class-content-types.php`), 4 taksonomi (`includes/class-taxonomies.php`), merkezi alan sözleşmesi (`includes/class-meta-schema.php`), sanitize/validate servisleri (`includes/class-validator.php`, `includes/class-field-repository.php` — kaydetme ve yayın hazırlığının paylaştığı **tek** alan doğrulayıcı), yayın öncesi zorunlu alan kapısı (`includes/class-publish-readiness.php`), 4 özel rol + yetenek matrisi (`roles/class-roles.php`, v2), denetim günlüğü tablosu (`audit/class-audit-log.php`), Türkçe yönetim meta kutuları/liste sütunları/aktif dönem ekranı (`admin/**`). Ayrıntı: [`docs/content-model.md`](docs/content-model.md), [`docs/roles-capabilities.md`](docs/roles-capabilities.md).

Faz 5 ile eklendi: tek genel katalog servisi (`public/class-catalog-service.php` + saf `includes/class-catalog-query.php`) — meslek/sektör arama, aktif tarife dönemi/geçerlilik tarihi/fiyat geçerliliği kurallarına uyan ücret sorguları; yönetim liste ekranlarına sektör/meslek/MYK/fiyatlandırma türü kolonları ve filtreleri (`admin/class-list-filters.php`); aktif tarife dönemi ekranına eksik-eşleşme uyarısı ve gerçek kayıtlardan türetilmiş dönem önerisi. Ayrıntı: [`docs/catalog-service-contract.md`](docs/catalog-service-contract.md), [`docs/admin-catalog-experience.md`](docs/admin-catalog-experience.md).

Bu fazda **yok**: veri importu (statik kaynak WordPress'e yazılmadı), REST endpoint, form, SEO/schema üretimi, üçüncü taraf eklenti bağımlılığı.

## Admin katalog aktarımı (Faz 6B4; güncel: eklenti 0.5.3)

SSH/WP-CLI olmayan hosting (DirectAdmin) için, mevcut Faz 6B3 apply/rollback motorunu **yönetim panelinden**, kesintiye dayanıklı çalıştırır (Araçlar → İçe Aktarım Dry-Run):
sektör görsel eşleme (Ortam Kütüphanesi, `mavibelge_core_sector_image_map`, kapalı zarf + manifest özeti + attachment doğrulaması; tahmin yok), aşama önizleme (`pages → sectors → qualifications → all → content`,
sunucu tarafı sıra kapısı), `wp_ajax_*` ile başlat/ilerlet (her istekte **en çok 10 kayıt**, kalıcı plan snapshot'ı ve checkpoint, `ready → running → paused → … → completed`), çok istekli rollback
(`rollback_ready → rolling_back → rollback_paused → … → rolled_back`, her istekte kalan bütün item'lar drift için önceden doğrulanır) ve WP-CLI ile **aynı** `MaviBelge_Core_Import_Runtime_Factory` grafiği.
Yazma kapıları: yalnız POST + action'a özel nonce + `manage_options` ve `mb_manage_tariff_period` + HTTPS; apply/rollback için ayrıca `MAVIBELGE_IMPORT_APPLY_ENABLED` ve `MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED` (`=== true`, wp-config.php'den),
staging'de `WP_ENVIRONMENT_TYPE === 'staging'`, üretimde ayrıca `MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED` ve birebir `MAVIBELGE_IMPORT_PRODUCTION_HOST`. Onay: `UYGULA <stage> <plan özeti ilk 12>` / `GERI AL <run ilk 8> <rollback özeti ilk 12>`.
İşletim adımları: [`docs/admin-import-operations.md`](docs/admin-import-operations.md). Testler: `tests/suites/faz6b4-admin-import.php` (saf), `tools/test-admin-import-static-contract.js` (statik),
`tools/runtime-test/admin-import.sh` (gerçek WordPress), `tools/runtime-test/admin-import-http.sh` (gerçek HTTP). Paket: `node tools/package/build-packages.js --write` → `dist-packages/mavibelge-core-0.5.0.zip`.

**Faz 12 — sayfalar (yerel kod tamam; staging'de çalıştırılmadı):** `pages` aşaması `data/content/pages.manifest.json` ile 32 gerçek `page` kaydını **taslak** oluşturur; yayınlama AYRI, açık onaylı, yalnız staging işlemidir (`YAYINLA <özet12>`, istek başına ≤10; kurum kararı bekleyen 7 sayfa bekletilir). Ayrıntı: [`docs/admin-import-operations.md`](docs/admin-import-operations.md) §8.1–8.2, [`docs/content-import-contract.md`](docs/content-import-contract.md) §9. Testler: `tools/import/test-page-manifest.js`, `tools/test-faz12-static-contract.js`, `tools/runtime-test/pages-render.sh`.
**Faz 12g — İletişim sayfası (tema 0.6.7; yerel):** `/iletisim/` statik referans düzeninde (`template-parts/page/content-contact.php`); lokasyon/sosyal/birincil iletişim footer ile ORTAK tek kaynaktan (`inc/contact-helpers.php`, `template-parts/components/social-links.php`); form yalnız `gate()` açıkken. Test: `tools/runtime-test/contact-page-test.js` (gerçek WP + headless Chrome; beş formun güvenlik matrisi dahil; `qualification-render.sh` içinde).

## Doğrulama

Bkz. [`docs/compatibility.md`](docs/compatibility.md).
