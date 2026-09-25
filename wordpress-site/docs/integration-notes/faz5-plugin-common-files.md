# Faz 5 — Eklenti Ortak Dosya Entegrasyon Notu

> AGENTS.md §3 / brief §7: bir agent ortak dosyaya doğrudan dokunmaz; bu not hangi dosya, hangi kod, hangi sıra, gerekçe ve geri alma yöntemini belgeler. Yalnız ana orkestratör uygular.

## Hedef dosya

```text
wordpress-site/wp-content/plugins/mavibelge-core/mavibelge-core.php
```

## Neden gerekli

`public/class-catalog-service.php` ve `includes/class-catalog-query.php`, temanın **her** ön yüz isteğinde (ana sayfa arama kutusu, yeterlilik arşivi, sektör sayfası, yeterlilik detayı, sınav ücretleri sayfası) çağırdığı tek katalog servisidir. Mevcut `includes/class-plugin.php::load_files()` yalnız `is_admin()` true iken dosya yükler (`admin/**` sınıfları için doğru davranış); ama katalog servisi **ziyaretçi tarafında** (admin değilken) de kullanılmalı. Bu yüzden diğer "her zaman gerekli" sınıflar (`class-content-types.php`, `class-taxonomies.php`, `class-validator.php` vb.) gibi `mavibelge-core.php`'nin en üstünde, koşulsuz `require_once` ile yüklenmesi gerekiyor — `includes/class-plugin.php`'ye dokunmaya gerek yok.

## Eklenecek kod

`mavibelge-core.php` içinde, mevcut koşulsuz require bloğuna (16-19. satır civarı, `class-publish-readiness.php`'den hemen sonra) şu iki satır eklenir:

```php
require_once MAVIBELGE_CORE_PATH . 'includes/class-catalog-query.php';
require_once MAVIBELGE_CORE_PATH . 'public/class-catalog-service.php';
```

Sıra önemli: `class-catalog-query.php` önce (bağımsız, saf sınıf), `class-catalog-service.php` sonra (WP_Query/get_terms/vb. kullanır ve `MaviBelge_Core_Catalog_Query`'ye referans verir, ayrıca `MaviBelge_Core_Validator::kurus_to_lira_display()` çağırır — o da zaten daha önce `require_once` edilmiş).

## Sürüm ve açıklama

Aynı düzenlemede plugin header'ı:

```php
Version: 0.2.4  →  0.3.0
```

ve `Description` satırına Faz 5 katalog servisi eklendiği bilgisi kısa şekilde eklenir. `define( 'MAVIBELGE_CORE_VERSION', '0.2.4' )` de `'0.3.0'` olarak güncellenir (dist/enqueue cache-busting bu sabiti kullanmaz — tema kendi `filemtime()` yöntemini kullanıyor — ama sürüm tutarlılığı için eklenti içindeki tüm referanslar birlikte güncellenir).

## Çakışma analizi

- `class-catalog-query.php` ve `public/class-catalog-service.php` yeni dosyalardır; mevcut hiçbir sınıf/fonksiyon adıyla çakışmaz (`grep -r "class MaviBelge_Core_Catalog" wordpress-site/wp-content/plugins/mavibelge-core` bu değişiklik öncesi sıfır sonuç verir).
- `includes/class-plugin.php`, `admin/**` veya roller/audit dosyalarına dokunulmaz.
- Aktivasyon/deaktivasyon akışı (`class-activator.php`/`class-deactivator.php`) etkilenmez — yeni sınıflar yalnız tanımlanır, herhangi bir hook'a otomatik bağlanmaz (servis çağrıya dayalıdır, kendi kendine çalışmaz).

## Geri alma yöntemi

İki `require_once` satırını ve sürüm/description düzenlemesini kaldırmak yeterlidir; `includes/class-catalog-query.php` ve `public/class-catalog-service.php` dosyaları eklentiye hiç yüklenmemiş gibi davranır (tema tarafındaki `inc/catalog-helpers.php` zaten `class_exists()` kapısı kullanır, bu durumda güvenli boş sonuç döner — bkz. `docs/integration-notes/faz5-theme-common-files.md`).

## Uygulama durumu

Bu not yazıldıktan hemen sonra ana orkestratör tarafından **uygulanmıştır** — bkz. `mavibelge-core.php`'nin gerçek içeriği ve `raporlar/proje-durumu.md`.
