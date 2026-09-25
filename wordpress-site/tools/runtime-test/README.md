# Faz 6B2 Runtime Doğrulama Harness'i (yalnız yerel test)

İzole, silinebilir bir PHP 7.3 + WordPress 6.9.9 + MariaDB 10.11 + WP-CLI ortamında
Faz 6B2 salt okunur dry-run'ını doğrular. **Üretim, staging veya canlı sistemle hiçbir
bağlantısı yoktur.** Sonuç raporu:
[`raporlar/veri-aktarim-raporlari/faz6b2-php-wordpress-runtime-dogrulama.md`](../../../raporlar/veri-aktarim-raporlari/faz6b2-php-wordpress-runtime-dogrulama.md).

## Güvenlik sınırları

- Yalnız `127.0.0.1:18673` yayınlanır; veritabanı dışarı açılmaz.
- Parolalar depoda **yoktur**: depo dışındaki bir env dosyasından okunur
  (`DB_PASSWORD`, `DB_ROOT_PASSWORD`, `WP_ADMIN_PASSWORD`, `WP_LOW_PASSWORD`).
- Eklenti, tema ve manifestler konteynere **salt okunur** bağlanır.
- `scripts/fixtures.php` yalnız test veritabanına **test fixture** yazar; dry-run'dan
  ÖNCE çalışır ve üretim koduna eklenmez.
- `scripts/mu-runtime-isolation.php` yalnız bu ortamda WordPress çekirdeğinin
  wordpress.org güncelleme denetimlerini kapatır (dış HTTP kapalı olduğundan).

## Çalıştırma

```bash
# env dosyası depo DIŞINDA olmalı
bash wordpress-site/tools/runtime-test/run-all.sh /depo/disi/.env /depo/disi/cikti
# kaldırma
docker compose -p mbruntime6b2 -f wordpress-site/tools/runtime-test/docker-compose.yml down -v
```

`run-all.sh` sırası: sıfırdan ortam → kurulum/aktivasyon/fixture → oturum açma + ısınma →
snapshot A → WP-CLI dry-run → snapshot B → admin HTTP testleri → snapshot C → karşılaştırma.

### Mevcut ortamda parolasız regresyon

```bash
bash wordpress-site/tools/runtime-test/run-regression.sh /depo/disi/cikti /depo/disi/expect.json
```

Env dosyası veya parola **okumaz**. Mevcut `mbruntime6b2-*` konteynerlerini `docker exec` ile
kullanır; DB anlık görüntüsünü `$wpdb` ile alır (`scripts/db-snapshot.php`); admin oturumunu
WP-CLI'nin ürettiği oturum çerezleriyle açar (`scripts/make-cookies.php`, çerezler ekrana
basılmaz). Ek olarak `_mb_level` gerçek meta testi (`scripts/level-meta-test.php`), kanarya
duyarlılık kontrolü ve tema HTTP render testleri (curl) çalıştırılır. Render fixture'ı:
`scripts/render-fixtures.php` (`wp eval-file … --user=mbadmin`, bir kez).

## Dosyalar

| Dosya | Görev |
|---|---|
| `Dockerfile`, `docker-compose.yml` | PHP 7.3 + mysqli + WP-CLI 2.12.0 (sha512 doğrulamalı), MariaDB 10.11 |
| `scripts/setup.sh` | WordPress 6.9.9 (sha1 + checksum), config, kurulum, aktivasyon, kullanıcılar, fixture |
| `scripts/fixtures.php` | Test fixture yazımı + önceden yazılmış beklenen kararlar |
| `scripts/check-registration.php`, `scripts/probe-types.php`, `scripts/diagnose.php` | Salt okunur doğrulama/tanılama |
| `snapshot.sh` | DB dökümü + tablo satır/checksum + dosya sha256 listesi |
| `admin-http.js` | Gerçek HTTP (tarayıcı değil) admin/yetki/nonce/mb_paged testleri |
| `compare-expect.js`, `scripts/summarize-plan.js` | CLI JSON çıktısının beklenti karşılaştırması ve özeti |

### Faz 6B3 Önkoşul ve Yazma Güvenliği turu ek betikleri

| Dosya | Görev |
|---|---|
| `scripts/fixtures-natural-key.php` | Doğal anahtar senaryoları (TEST FIXTURE). `fixtures.php`'den sonra bir kez çalışır; `/tmp/mb-fixture-expect-6b3.json` üretir: önceki beklentilerin gerekçeli `overrides`'ı ve yeni `added` senaryolar. |
| `scripts/write-safety-test.php` | Gerçek kayıtlı meta yollarında x100 olmaması, ezme olmaması, marker reddi ve 200 manifest yükünün sanitize idempotansı. Geçici kayıtlarını siler ve silindiğini doğrular. |
| `compare-expect.js <expect> <cli> [expect-6b3]` | `overrides` yalnız var olan beklentiyi değiştirebilir, `added` yalnız yeni anahtar ekleyebilir; sessiz çakışma yoktur. |

`run-regression.sh` üçüncü argüman olarak `expect-6b3.json` alır ve `write-safety-test.php`'yi hazırlık aşamasında (snapshot A'dan önce) çalıştırır.

### Faz 6B3 Apply/Batch/Audit/Rollback turu ek betikleri

| Dosya | Görev |
|---|---|
| `apply-cycle.sh <çıktı-dizini>` | Apply/rollback döngüsünün sürücüsü. Mevcut ortamda, parola okumadan çalışır. Ana `wp_` veritabanına YAZMAZ: `wp_` tablolarını `mbfx_` önekiyle silinebilir bir klona kopyalar, bütün apply/rollback'i orada ve AÇIKÇA SAHTE fixture manifestiyle (`zz-test-*`, `97UY7xxx`) yapar, sonunda klonu ve manifesti siler; ana DB + dosyalar + uploads başlangıç anlık görüntüsüyle karşılaştırılır ve debug log sayılır. |
| `scripts/fixture-db.php clone\|drop\|manifest\|rmmanifest` | `mbfx_` klonunu oluşturur/siler (kullanıcı yetki meta anahtarları ve rol seçeneği yeni öneke taşınır); sahte manifestleri `/tmp/mbfx-manifest` ve `-v2` dizinlerine yazar/siler. Yalnız `wp_` önekiyle çalışır. |
| `scripts/fixture-env.php` | `wp --require=...`: WP-CLI sürecini `after_wp_config_load` kancasıyla `mbfx_` önekine yönlendirir; `MB_FX_MANIFEST` (varsayılan sahte manifest; `none` = gerçek manifest) ve `MB_FX_APPLY=1` (`MAVIBELGE_IMPORT_APPLY_ENABLED`) ortam değişkenlerini okur. Dosya/eklenti kurmaz. |
| `scripts/apply-enable.php` | `wp --require=...`: yalnız o süreçte apply anahtarını açar; `run-regression.sh` gerçek manifest planının ana DB'de de (A→B sıfır yazma penceresinde) reddedildiğini göstermek için kullanır. |
| `scripts/apply-cycle-test.php` | Gerçek WordPress uygulamalarıyla (yazma adapterı, `$wpdb` transaction, run deposu, audit) servis düzeyi döngü: MyISAM ön kontrolü, TOCTOU, başarılı apply, idempotency, rollback döngüsü + canlı içerik parmak izi, batch ortasında hata enjeksiyonu, update + yönetilmeyen alan koruması, update rollback, drift reddi. Hata enjeksiyonu yalnız bu süreçte eklenen WordPress filtreleriyle yapılır; üretim koduna test kancası eklenmez. |

Hata enjeksiyonu yöntemi: (1) TOCTOU — `created_mb_sektor` eylemi, A terimi oluşturulurken aynı transaction içinde C'nin doğal anahtarına yönetilmeyen bir terim ekler; (2) batch hatası — `update_post_metadata` filtresi 3. ücretin `_mb_price_options` yazımını `false` ile kısa devre eder (post ve bazı meta yazılmışken readback başarısız olur); (3) ön kontrol — fixture `postmeta` tablosu geçici olarak MyISAM yapılır. Her enjeksiyon sonrası batch'in gerçek veritabanı transaction'ı ile TAMAMEN geri alındığı (araya eklenen terim/post dahil) satır düzeyinde doğrulanır.

```bash
bash wordpress-site/tools/runtime-test/apply-cycle.sh /depo/disi/cikti
```

### Faz 7 içerik aktarımı (`news` / `reference`, `content` aşaması)

| Dosya | Görev |
|---|---|
| `scripts/apply-cycle-test-3.php` | Gerçek WordPress 6.9.9'da `content` aşamasının servis düzeyi kanıtı: dry-run → apply → readback (draft, `post_date` + sıfır `post_date_gmt`, ham içerik, tür terimi) → noop → rollback (çöp, marker/terim temizliği, `post_name` + `_wp_desired_post_slug` serbest) → dry-run (applicable, conflict=0) → yeniden apply; v2 manifestiyle güncelleme + geri alma (`edit_date`); drift (SEO meta, ek taksonomi, `menu_order`, durum, `post_content`, onay, referans alanları); kullanıcının çöp/taslak kaydı HÂLÂ conflict; atomik finalizer. YALNIZ `mbfx_` önekinde (ön ek koruması) ve sahte `/tmp/mbfx-content-manifest{,-v2}` ile çalışır; iki kontrollü `mb_haber_turu` terimini betik kendisi oluşturur/kaldırır. |
| `apply-cycle.sh` adım 4c | Taze fixture DB'de WP-CLI `--stage=content` kapıları (terimler yokken blocked + apply reddi, terimlerle 6 create, apply, noop, rollback önizleme/yürütme, yeniden plan) ve ardından `apply-cycle-test-3.php`. |
| `scripts/fixture-db.php manifest` | Ek olarak sahte içerik manifestlerini `/tmp/mbfx-content-manifest` ve `-v2` dizinlerine yazar; `rmmanifest` dört dizini de siler. GERÇEK `data/content/{news,references}.manifest.json` hiçbir betikte kullanılmaz. |

Fixture DB'ye yazan betikler seri çalıştırılmalıdır (`bash wordpress-site/tools/runtime-test/with-lock.sh bash wordpress-site/tools/runtime-test/apply-cycle.sh /depo/disi/cikti`).

### Faz 10 — performans/güvenlik/bakım testleri

`content-http.sh` SEO testinden (ve staging işaretinin kaldırılmasından) SONRA, mbfx_ klonunda iki adım daha çalıştırır:

| Dosya | Görev |
|---|---|
| `perf-security-http-test.js <çerez-dizini>` | Ön yüzden kaldırılan WordPress varsayılanları, yerel görsel öznitelikleri (gerçek PNG oranı), güvenli başlıklar (HSTS/CSP yok), anonim REST 401, `?author=` 404, XML-RPC 403, genel giriş hatası + 10 denemelik oran sınırı, yönetim/jQuery/REST (nonce'lu) etkilenmezliği, sağlık ekranı ve yetki. Oturum çerezleri `scripts/make-cookies.php` ile YALNIZ klona yazılır. |
| `scripts/perf-security-runtime.php` | WP-CLI (`--user=mbadmin`): audit v1→v2 yükseltme/zincir/kurcalama/saklama/`GET_LOCK`, bakım cron'u, önbellek (nesil, kapalı kipler, fail-open), N+1 sorgu sayıları, oran sınırı (gerçek transient deposu), giriş, sağlık ekranı render'ı, uninstall (klonun eklenti tablolarını siler — bu yüzden EN SON). Upload dizini `/tmp` altına yönlendirilir. |

Not: bu ortamda kalıcı bağlantılar düz olduğundan REST/yazar testleri `?rest_route=` / `?author=` biçimini kullanır.
