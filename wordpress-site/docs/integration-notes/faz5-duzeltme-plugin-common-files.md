# Faz 5 Düzeltme ve Kabul — Eklenti Ortak Dosya Entegrasyon Notu

> AGENTS.md §3 / brief §1.8: `mavibelge-core.php` ortak dosyadır; yalnız ana orkestratör değiştirir. `includes/class-plugin.php` çekirdek agentının kendi serbest alanıdır (`includes/**`), ayrı not gerektirmez ve doğrudan düzenlenmiştir.

## Hedef dosya

```text
wordpress-site/wp-content/plugins/mavibelge-core/mavibelge-core.php
```

## Neden gerekli

Bulgu §2.5 (pasif yeterliliğin doğrudan erişimini kapatma) yeni bir sınıf ekliyor: `public/class-visibility-guard.php`. Bu sınıf `template_redirect` kancasına bağlanır — hem ziyaretçi hem yönetici isteklerinde, `is_admin()` durumundan bağımsız her front-end isteğinde çalışması gerekir. Diğer "her zaman gerekli" sınıflar gibi (`class-catalog-query.php`, `class-catalog-service.php`) `mavibelge-core.php`'nin en üstünde koşulsuz yüklenmelidir.

## Eklenecek kod

Mevcut koşulsuz require bloğunda, `public/class-catalog-service.php`'den hemen sonra:

```php
require_once MAVIBELGE_CORE_PATH . 'public/class-catalog-service.php';
require_once MAVIBELGE_CORE_PATH . 'public/class-visibility-guard.php';
```

`class-catalog-service.php`'nin `present_fee()` metodu artık `MaviBelge_Core_Visibility_Guard::is_public_qualification()`'ı çağırıyor (`class_exists()` kapılı) — bu yüzden sınıf, servisten *sonra* değil, **service dosyasının kendisi her çağrıldığında zaten yüklenmiş olmalı**; iki dosya da aynı koşulsuz blokta olduğu için yükleme sırası bu ihtiyacı otomatik karşılar (her ikisi de `plugins_loaded`'dan çok önce, dosyanın en üstünde yüklenir).

## Sürüm

Bu görev promptu §1.8 "gerekmeyen sürüm artışı yapma" der — Faz 5 zaten 0.3.0'a yükseltilmişti (bir önceki tur); bu düzeltme turu **sürüm numarasını artırmaz**.

## Çakışma analizi

`class-visibility-guard.php` yeni bir dosyadır, mevcut hiçbir sınıf/fonksiyon adıyla çakışmaz. `template_redirect` kancasına başka hiçbir mavibelge-core dosyası bağlı değildir (`grep -r "template_redirect" wordpress-site/wp-content/plugins/mavibelge-core` bu değişiklik öncesi sıfır sonuç verir).

## Geri alma yöntemi

Eklenen `require_once` satırını kaldırmak yeterlidir; `includes/class-plugin.php`'deki `MaviBelge_Core_Visibility_Guard::init()` çağrısı da birlikte kaldırılmalıdır (aksi halde tanımsız sınıf fatal hatası — ama bu iki değişiklik her zaman birlikte yapılır/geri alınır).

## Uygulama durumu

Bu not yazıldıktan hemen sonra ana orkestratör tarafından **uygulanmıştır**.
