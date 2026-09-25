# Uyumluluk — `wordpress-site/`

> Ayrıntılı uyumluluk kararları: [`../raporlar/wordpress-faz0-uyumluluk-matrisi.md`](../raporlar/wordpress-faz0-uyumluluk-matrisi.md). Karar kaydı: [`../raporlar/karar-kaydi-wordpress-php73.md`](../raporlar/karar-kaydi-wordpress-php73.md).

## Hedef

- **PHP:** `>= 7.3`, kabul edilmiş bağlayıcı üretim kısıtı. Bu, çözülmesi beklenen bir engel değildir.
- **WordPress ailesi:** 6.9.x, koşullu/legacy. Kesin yama sürümü henüz kilitlenmemiştir.
- **Veritabanı:** MySQL/MariaDB — sürüm ve karakter seti sunucudan doğrulanana kadar bilinmiyor; Türkçe içerik için `utf8mb4` zorunludur.

## PHP 7.3 sözdizimi kısıtları

Kullanılmaz: ok fonksiyonları (`fn() =>`), tipli sınıf özellikleri, `??=`, dizi içinde açma, sayısal ayraç, `str_contains`/`str_starts_with`/`str_ends_with`, adlandırılmış argümanlar, constructor property promotion, `match`, `?->`, union/intersection tip, `enum`, `readonly`, ilk sınıf callable, `never` dönüş tipi, attribute.

Tam liste: uyumluluk matrisi §4-§5.

## Gelecek uyumluluğu

Kod, PHP 7.3 uyumluluğu için kaldırılmış eski PHP API'lerine yaslanmaz; hedef, PHP 8.3'te de deprecated/fatal üretmemektir.

## Doğrulama yöntemi (bu fazda)

- Yerelde `php` bulunursa `php -l` ile sözdizimi kontrolü.
- `php` yoksa yalnız statik desen taraması yapılır; bu **gerçek PHP 7.3 parser testinin yerine geçmez**.
- `composer validate --no-check-publish` (kurulu ise) — install/update çalıştırılmaz.
