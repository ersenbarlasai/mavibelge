# Faz 5 — Tema Ortak Dosya Entegrasyon Notu

> AGENTS.md §3 / brief §7: `style.css` başlık bloğu ortak dosyadır; yalnız ana orkestratör değiştirir. `inc/bootstrap.php`, `inc/catalog-helpers.php` gibi `inc/**` altındaki dosyalar tema agentının **kendi** serbest alanıdır (brief §11.2) — bu ikisi için ayrı bir entegrasyon notuna gerek yoktur ve doğrudan düzenlenmiştir.

## Hedef dosya

```text
wordpress-site/wp-content/themes/mavibelge/style.css
```

## Değişiklik

Başlık bloğunda yalnız iki satır:

```text
Description: ... Faz 4: ... → ... Faz 4: ...; Faz 5: meslek/sektor/ucret arama-filtre deneyimi, ucret gosterimi ve sinav-ucretleri sayfasi (mavibelge-core Catalog_Service uzerinden). Veri importu (Faz 6) henuz yapilmadi.
Version: 0.4.0 → 0.5.0
```

## Gerekçe

Faz 5, temaya gerçek bir kullanıcı-görünür özellik seti ekledi (arama/filtre formu, ücret tablosu/kartları, hero arama entegrasyonu) — sürüm numarasının bunu yansıtması, Faz 3/4'te izlenen aynı "her gerçek faz kapanışında minor sürüm artışı" alışkanlığıyla tutarlıdır (0.3.0→0.4.0 Faz 4'te olduğu gibi).

## Çakışma analizi

Yalnız `Description` ve `Version` satırları değişir; `Theme Name`, `Requires PHP`, `Text Domain` vb. diğer başlık alanları dokunulmadan kalır. Hiçbir CSS kuralı bu dosyada değişmez (asıl stiller `assets/src/css/**`'te).

## Geri alma yöntemi

İki satırı eski değerlerine döndürmek yeterlidir; hiçbir kod bu sürüm dizesine (temada `mavibelge_asset_version()` kendi `filemtime()`'ını kullanır, bu sabiti okumaz) bağımlı değildir.

## Uygulama durumu

Bu not yazıldıktan hemen sonra ana orkestratör tarafından **uygulanmıştır**.
