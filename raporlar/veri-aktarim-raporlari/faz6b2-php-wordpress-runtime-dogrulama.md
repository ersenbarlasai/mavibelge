# Faz 6B2 — PHP 7.3 / WordPress Runtime Doğrulaması

> Tarih: 23 Eylül 2026. Ortam: yerel, izole, silinebilir Docker ortamı. Canlı, staging (`cms-yeni`), DirectAdmin, FTP, SSH, DNS, SSL ve mail sistemine **bağlanılmadı**. Commit/push/deploy **yapılmadı**. Faz 6B3 **başlamadı**; `--apply` yok, katalog importu yok.
>
> Harness: [`wordpress-site/tools/runtime-test/`](../../wordpress-site/tools/runtime-test/README.md).

> **Güncelleme (23 Eylül 2026, Runtime Engellerinin Kapatılması):** Aşağıdaki özet kararın dayandığı üç engel (iki tema parse hatası ve `_mb_level` select hatası) **bu turda kapatıldı**. Tüm kapılar yeniden çalıştırıldı ve geçti: PHP 7.3 lint 109/109, `tests/run.php` 598/598 ×2, sıfır-yazma A=B=C. Güncel sonuç raporun sonundaki **"Faz 6B2 Runtime Engellerinin Kapatılması"** bölümündedir. Aşağıdaki özet ve §1–§17, tarihsel ilk runtime turunun kaydı olarak korunuyor.

**Özet karar (ilk runtime turu, tarihsel):** Faz 6B2'ye ait bütün runtime kapıları gerçek PHP 7.3.33 ve izole WordPress 6.9.9'da **geçti**: `mavibelge-core` lint 35/35, `tests/run.php` 557/557 (×2, birebir aynı), 49/49 repository senaryosu, 37/37 admin HTTP testi, CLI ve admin dry-run sonrasında veritabanı/dosya farkı sıfır, debug log 0 satır. Görevin tanımladığı PHP 7.3 lint kapısı ise `wordpress-site` geneli için **BAŞARISIZ**: 105 dosyadan 2 **tema** dosyası PHP ayrıştırma hatası veriyor ve tema etkinken ana sayfa HTTP 500 dönüyor. Ayrıca Faz 2 meta şemasında `_mb_level`'ın hiç kaydedilemediğini gösteren bir hata bulundu. Bu nedenle bu rapor "Codex nihai kabul incelemesine hazırdır" ifadesini **kullanmaz** (bkz. §15, §17).

## 1. Ortam parmak izi

| Bileşen | Değer | Kaynak |
|---|---|---|
| Ana makine | Windows 10 Pro 10.0.19045, Docker Desktop 29.6.2 (linux/amd64, WSL2) | `docker version` |
| Konteyner OS | Debian GNU/Linux 11 (bullseye), x86_64 | `/etc/os-release`, `uname -m` |
| PHP | **7.3.33** (cli, NTS, built Mar 18 2022), **64-bit** (`PHP_INT_SIZE=8`) | `php -v` |
| PHP uzantıları | Core ctype curl date dom fileinfo filter ftp hash iconv json libxml mbstring **mysqli** mysqlnd openssl pcre PDO pdo_sqlite Phar posix readline Reflection session SimpleXML sodium SPL sqlite3 standard tokenizer xml xmlreader xmlwriter zlib | `php -m` |
| WordPress | **6.9.9** (en_US) — resmî `wordpress-6.9.9.tar.gz` + yayınlanan sha1 doğrulandı; `wp core verify-checksums` başarılı | `wp core version` |
| WP-CLI | **2.12.0** — resmî GitHub sürümü, yayınlanan sha512 doğrulandı | `wp --version` |
| Veritabanı | **MariaDB 10.11.19** | `mariadb --version`, `$wpdb->db_server_info()` |
| Web sunucusu | Apache/2.4.52 (Debian), mod_php | `apache2 -v` |

**WordPress sürüm gerekçesi (tahmin değil):** `api.wordpress.org/core/stable-check/1.0/` (23 Eylül 2026) 6.9–6.9.8'i `insecure`, **6.9.9'u `outdated`** (6.9 hattının `insecure` olmayan tek yaması), 7.1.2'yi `latest` olarak listeliyor. İndirilen 6.9.9'un kendi `wp-includes/version.php` dosyası `$required_php_version = '7.2.24'` diyor, yani PHP 7.3 ile uyumlu. WordPress 7.x PHP 7.4 istediği için (proje karar kaydı) kullanılamıyor. **Bu seçim bir üretim sürüm kilidi değildir**; üretim PHP/DB sürümleri hâlâ doğrulanmamıştır (AGENTS.md §8).

**Üretimden bilinen farklar:** Test ortamında `gd`/`imagick`, `zip`, `intl`, `exif` yok (dry-run bunları kullanmıyor). MariaDB 10.11, WordPress'in önerdiği sürüm; üretim DB sürümü doğrulanmadı.

**Test izolasyonu:** `WP_DEBUG=true`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY=false`, `DISABLE_WP_CRON=true`, `WP_HTTP_BLOCK_EXTERNAL=true`, `AUTOMATIC_UPDATER_DISABLED=true`. Yalnız 127.0.0.1'e yayın. Eklenti, tema ve manifestler salt okunur bağlandı. Test ortamına özel mu-plugin (`mu-runtime-isolation.php`) yalnız çekirdeğin wordpress.org güncelleme denetimlerini kapatır; neden: dış HTTP kapalıyken bu denetimler debug log'a uyarı yazıyor ve `update_*` transient'lerini güncelliyor. Parolalar rastgele üretildi ve depo dışında tutuldu.

## 2. Başlangıç Git durumu

`## main...origin/main`; ` M README.md` (kullanıcı değişikliği, korundu); takip edilmeyen: `AGENTS.md`, `CLAUDE_*.md`, `raporlar/*`, `tanitim-site/yeni-mavibelge-v1.zip`, `wordpress-site/`. Son commit: `c0f4ef9 docs: define agent architecture and SEO AIO governance`.

## 3. Değişen dosyalar

| Dosya | Değişiklik | Neden |
|---|---|---|
| `mavibelge-core/admin/class-import-dry-run-page.php` | (a) `mb_paged` kararı saf `normalize_paged_value()`'a ayrıldı, desen `/^[1-9][0-9]*$/` → `/^[1-9][0-9]*\z/`; (b) istek/nonce doğrulaması `validate_request()`'e taşındı ve çıktıdan önce `load-{hook}` aşamasında çalışıyor (`render_page()` içinde savunma amaçlı yedek var) | (a) §5 — PHP 7.3'te `"1\n"`'in eski desenle kabul edildiği kanıtlandı. (b) Runtime bulgusu: `wp_die()` admin başlığından sonra çağrıldığı için 400/403 durumu uygulanamıyor, hata HTTP 200 ile sayfaya gömülüyordu |
| `mavibelge-core/tests/run.php` | 12 `mb_paged` testi; geçici fixture dizini temizliği (`mb6b2_cleanup_temp_dirs()`) + temizlik assertion'ı; NOT covered notu | Runtime bulgusu: her çalıştırma `/tmp` altında 21 dizin bırakıyordu |
| `mavibelge-core/tests/bootstrap.php` | Admin sınıf dosyası yüklendi (yalnız saf `normalize_paged_value()` test ediliyor) | §5 testleri |
| `wordpress-site/tools/test-faz6b2-static-contract.js` | `\z` deseni, `load-{hook}` doğrulaması, temizlik ve `mb_paged` regresyon sabitleri ve durum belgesi kapanış sabiti (160 → 164) | Regresyon koruması |
| `wordpress-site/tools/runtime-test/**` (yeni) | Harness (yalnız test) | §9–§14 |
| Bu rapor, `raporlar/proje-durumu.md`, `README.md` | Durum kaydı | §17–§18 |

Üç kod düzeltmesinin hepsinde §16 sırası izlendi: önce başarısız regresyon testi yazıldı ve PHP 7.3'te başarısız olduğu görüldü (temizlik: 544/545, `mb_paged`: 556/557, admin nonce: 32/37), sonra düzeltme uygulandı. Tema ve Faz 2 dosyalarına **dokunulmadı** (başka agentların sahipliği; bkz. §15).

## 4. PHP lint sonucu

Komut: her dosya için ayrı `php -l` (PHP 7.3.33, `wordpress-site` altındaki bütün `*.php`).

| Kapsam | Lint edilen | Başarısız |
|---|---|---|
| `wordpress-site` geneli (runtime-test betikleri dahil) | 105 | **2** |
| `mavibelge-core` | 35 | 0 |

Başarısız dosyalar (ikisi de **tema**, ikisi de PHP dilbilgisi gereği sürümden bağımsız hata; yalnız PHP 7.3 ile çalıştırıldı):

1. `wp-content/themes/mavibelge/template-parts/components/button.php:29` — `Parse error: syntax error, unexpected '*'`. Docblock içindeki `aria-*/data-*` metnindeki `*/` yorumu erken kapatıyor. **Runtime etkisi:** tema etkinken ana sayfa (`/`) **HTTP 500** veriyor, debug log: `PHP Parse error ... button.php on line 29`.
2. `wp-content/themes/mavibelge/taxonomy-mb_sektor.php:104` — `Parse error: syntax error, unexpected ':'`. `if (...) :` bloğunun içindeki süslü parantezli `if ( '' !== $term_url ) { … }` bloğundan sonra gelen `else :`, PHP'nin sarkan-else kuralı gereği içteki `if`'e bağlanıyor.

**Kapı sonucu (ilk tur): BAŞARISIZ** — **bu turda kapatıldı**, bkz. "Faz 6B2 Runtime Engellerinin Kapatılması" (109/109). (görev §6: "Bir dosya dahi lint hatası verirse kapı başarısızdır").

## 5. `tests/run.php` gerçek sonuçları

Komut: `php -d display_errors=1 -d error_reporting=-1 wordpress-site/wp-content/plugins/mavibelge-core/tests/run.php` (PHP 7.3.33, kaynak salt okunur bağlı).

| Çalıştırma | Exit | Sonuç | FAIL | stderr |
|---|---|---|---|---|
| 1 | 0 | **557/557 assertions passed** | 0 | 0 bayt |
| 2 | 0 | **557/557 assertions passed** | 0 | 0 bayt |

- İki çıktı `cmp` ile birebir aynı. Warning, notice, deprecated, fatal ve "Array to string conversion" yok.
- Çalıştırma sonunda `/tmp`'de `mb6b2_*` dizini **kalmıyor**; temizlik assertion'ı geçiyor.
- Bu turun ilk çalıştırması, düzeltme öncesi kodla: 544/544 geçti ama 21 geçici dizin kaldı (bkz. §3).

Çalıştığı gerçek çıktıdan sayılan kategoriler (PASS satırları): `strict_string` 10, `strict_int` 29, `strict_nonneg_int_or_empty_zero` 27, taşma testleri 11 (PHP_INT_MAX 5, PHP_INT_MIN 3), marker 5, hash (bozuk) 3, iç içe/nested 16, Faz 6B2 loader 28 (gerçek 14/83/103 yükleme dahil), `invalid_target_state` 47, duplicate 4, wrong-type 6, dependency/type_verified 32, hash (genel) 34, idempotency/aynı plan 3, `mb_paged` 12, temizlik 1. `parse_canonical_decimal_int()` private olduğu için `strict_int()`/`strict_nonneg_int_or_empty_zero()` üzerinden sınandı.

## 6. Node kalite kapıları

| Komut | Exit | Sonuç |
|---|---|---|
| `node wordpress-site/tools/import/test-extract-safety.js` | 0 | 54/54 |
| `node wordpress-site/tools/import/test-manifest-validation.js` | 0 | 85/85 (çıktıdaki "Manifest üretimi BAŞARISIZ — 1 hata" satırı kasıtlı writer-spy negatif testinin log'u) |
| `node wordpress-site/tools/import/verify-manifest.js` | 0 | 32/32 + FINALIZATION PASS |
| `node wordpress-site/tools/verify-source-counts.js` | 0 | 9/9 |
| `node wordpress-site/tools/test-theme-fee-text.js` | 0 | 3/3 |
| `node wordpress-site/tools/test-faz6b2-static-contract.js` | 0 | **164/164** |
| `node --check` (`wordpress-site/tools/**/*.js`, 20 dosya) | 0 | temiz |

## 7. WordPress aktivasyon sonucu

- Temiz kurulum: `wp core install` başarılı. `wp plugin activate mavibelge-core` ve `wp theme activate mavibelge` başarılı.
- **Aktivasyon aşamasının debug log'u: 0 satır.** Kurulumun ilk denemesinde görülen tek uyarı eklentiden değil, WP-CLI `plugin list`'in kendi çağırdığı çekirdek `wp_update_plugins()`'ten geliyordu.
- Kayıtlar (`check-registration.php`): 7 içerik türü (`mb_yeterlilik`, `mb_ucret`, `mb_haber`, `mb_dokuman`, `mb_referans`, `mb_lokasyon`, `mb_sss`); 4 taksonomi (`mb_sektor`, `mb_haber_turu`, `mb_dokuman_kategori`, `mb_sss_kategori`); `mb_sektor` term meta (`_mb_icon_key`, `_mb_image_attachment_id`, `_mb_import_source_key`, `_mb_last_applied_hash`); `mb_yeterlilik`/`mb_ucret` post meta şemaları; 4 özel rol (`mb_site_manager`, `mb_content_editor`, `mb_price_editor`, `mb_reviewer`); eklentinin `wp_mb_audit_log` tablosu.
- Repository sınıfı arayüzü uyguluyor. CLI sınıfı yüklü. `wp help mavibelge import catalog` exit 0, sinopsis `[--dry-run] [--format=<format>]` (table/json).
- Admin sayfası: `mbadmin` GET 200; `mbeditor` (`mb_content_editor`) ve `mbsubscriber` GET **403**.

## 8. Repository senaryo matrisi

Fixture'lar dry-run'dan **önce**, test veritabanına yazıldı (`scripts/fixtures.php`). Normal değerler WordPress API'leriyle; bozuk dizi/nesne meta ve çapraz-tür marker'lar DB bozulmasını modellemek için `$wpdb->insert` ile serileştirilmiş ham satır olarak yazıldı. Beklenen kararlar fixture tarafından **önceden** yazıldı ve gerçek CLI JSON'uyla karşılaştırıldı. Aynı source_key için ikinci kez beklenti yazılırsa fixture hata veriyor. Sonuç: **49/49**.

| # | Senaryo | Kayıt | Gerçek karar |
|---|---|---|---|
| 1 | Hedef yok → create | `qualification:17UY0280-3/01`, `fee:plastik:3:…` | create/no_target |
| 2 | Marker + hash + alanlar aynı → unchanged | `sector:plastik`, `qualification:12UY0069-3/02`, `qualification:16UY0244-4/02`, `fee:guzellik-sac-bakim:4:guzellik-uzmani` | unchanged/hash_match |
| 3 | Mevcut alan eski, hash=mevcut → update | `sector:mobilya` (changed=description), `fee:guzellik-sac-bakim:3:cilt-bakim-uygulayicisi` | update/safe_update |
| 4 | Marker var, hash yok | `sector:is-makineleri` | conflict/legacy_missing_hash |
| 5 | Aynı marker iki terimde | `sector:makine` | conflict_duplicate_target |
| 6 | Marker yanlış içerik türünde | `sector:tekstil` (mb_ucret'te), mermer ücreti (mb_yeterlilik'te) | conflict_wrong_target_type |
| 7a | Marker **yalnız** serileştirilmiş dizi | `sector:guzellik-sac-bakim` | create/no_target — **gözlem, bkz. §15** |
| 7b | Marker satırları [dizi, doğru string] | `sector:cam` | conflict/invalid_target_state |
| 8a | Hash dizi | `sector:lojistik` | conflict/invalid_target_state |
| 8b | Hash biçimsiz string | `sector:insaat` | conflict/legacy_missing_hash (sözleşme gereği) |
| 9 | String meta dizi | `sector:enerji` (icon_key), `qualification:16UY0245-4/02` (record_status), `fee:…:kuafor` (source_name) | conflict/invalid_target_state |
| 10 | Sayısal meta taşması (`_mb_level`="99999999999999999999") | `qualification:13UY0143-3/01` | conflict/invalid_target_state |
| 11 | Sektör bağımlılığı yok | `qualification:11UY0036-2/01`, `sector:maden`, `sector:mermer` | blocked_dependency |
| 12 | Yeterlilik eşleşmesi yok | `fee:cam:4:endustriyel-cam-isil-islem-elemani` | blocked_dependency |
| 13 | Aynı MYK kodlu iki yeterlilik | fee `…:epilasyon-uzmani` blocked + diagnostic `ambiguous_qualification_dependency` (qualification:18UY0344-4/00) | blocked_dependency + diagnostic |
| 14 | Görsel ID attachment değil | `sector:metalurji` | blocked_dependency (image_attachment_id) |
| 15 | Gerçek attachment | `sector:metal` | create (görsel çözüldü) |
| 16 | 19 kodsuz ücret | 19/19 | create/no_target (tahmini ilişki yok) |
| 17 | Sıfır / iki sektör terimi | `qualification:17UY0301-4/00`, `…-3/00` | conflict/invalid_target_state |
| 18 | Gerçek ID tipleri | `get_terms`/`get_posts`/`wp_get_post_terms` ids → `array(integer)`; `get_term_by()->term_id` → integer; attachment → integer + `post_type=attachment` | doğrulandı |
| 19 | Gerçek meta tipleri | tekil meta string döner (`'3'`, `'1'`, `'0'`, eksik → `''`); serileştirilmiş dizi → array; aynı anahtarda iki satır → `single` ilkini döner | doğrulandı |
| 20 | İki çalıştırma aynı plan | CLI json1 == json2 (`generated_at_utc` hariç) | doğrulandı |

Term ID 4-5 ile post ID 4-5 bu veritabanında sayısal olarak çakıştı; tür+ID ayrımı bu çakışmayla da doğru çalıştı.

## 9. WP-CLI dry-run sonucu

| Komut | Exit | Not |
|---|---|---|
| `wp mavibelge import catalog --dry-run --format=json` ×2 | 0, 0 | stderr 0 bayt; iki çalıştırmada anlamsal olarak aynı sonuç |
| `wp mavibelge import catalog --dry-run` (table) | 0 | 206 satır, stderr 0 |
| `wp mavibelge import catalog` (bayraksız) | 0 | aynı salt okunur davranış |
| `--format=xml` | **1** | WP-CLI: `Invalid value specified for 'format'` |
| `--apply` | **1** | WP-CLI: `unknown --apply parameter` |

Fixture'lı veritabanında sonuç (gerçek; önceden uydurulmadı): 200 kayıt (sektör 14 / yeterlilik 83 / ücret 103), `summary.total=200`; create 89, update 2, unchanged 4, conflict 13, blocked 92, invalid 0 (toplam = 200). `structurally_valid=true`, `applicable=false`, `load_errors=0`, `plan_errors=0`. Diagnostics kapalı `{code,type,source_key}` şeklinde. Çıktıda mutlak yol, parola veya anahtar sızıntısı yok. Fixture'sız boş veritabanında: create 23 (4 görselsiz sektör + 19 kodsuz ücret), blocked 177. CLI ortak `MaviBelge_Core_Import_Dry_Run_Service`'i çağırıyor; admin'in 200 satırı CLI JSON'u ile birebir aynı (§10).

## 10. Admin / yetki / nonce sonucu

Gerçek HTTP istemcisiyle (`admin-http.js`, `wp-login.php` oturumu + çerez + nonce) **37/37**. **Gerçek tarayıcı otomasyonu kullanılmadı**; klavye, görsel ve JavaScript davranışı test edilmedi.

- `mbeditor` ve `mbsubscriber`: GET 403, POST 403; dry-run çalışmadı.
- Admin GET: 200; form ve nonce var; sonuç bölümü yok (GET dry-run çalıştırmıyor). Tek submit düğmesi "Salt Okunur Dry-Run Çalıştır"; apply/import/yazma düğmesi yok.
- Nonce eksik → **400**; bozuk → **403**; dizi → **400**; anahtarlı dizi → **400**. Hiçbirinde dry-run çalışmadı. Düzeltme öncesi bu dört durum HTTP 200 dönüyordu (bkz. §3).
- Geçerli nonce: 200. Toplam 200. 8 sayfanın her biri 25 satır. 200 satır CLI JSON'u ile birebir aynı (source_key/type/decision/reason/target_id/changed/unresolved). Operations özeti CLI ile aynı.
- `mb_paged`: `"2"` → 2. sayfa; `"10"` → son sayfaya (8) sıkıştırılıyor. `"2\n"`, `"2\r\n"`, `"1e2"`, `"2.0"`, `"-2"`, `"0"`, `"+2"`, `" 2"`, `"02"`, dizi ve anahtarlı dizi → reddediliyor (sayfa 1).
- Kaçış: sonuç bölümü yalnız beklenen etiketleri içeriyor (code, div, em, h2, h3, li, p, strong, table, tbody, td, th, thead, tr, ul); hücrelerde ham HTML yok. **Sınır:** manifest salt okunur olduğu için saldırgan kontrollü HTML içeren bir `source_key` runtime'da denenemedi; kaçış kanıtı kaynakta `esc_html`/`esc_attr` kullanımı ve bu etiket kontrolüyle sınırlı.

## 11. Önce/sonra veritabanı karşılaştırması

Sıra: kurulum, aktivasyon, fixture, kullanıcı girişi ve ısınma istekleri → **snapshot A** → CLI dry-run (json×2, table, bayraksız, xml, --apply) → **snapshot B** → admin testlerinin tamamı (GET'ler, geçerli/geçersiz POST'lar, 8 sayfa, `mb_paged` retleri, yetkisiz kullanıcılar) → **snapshot C**.

Snapshot içeriği: `mariadb-dump --skip-dump-date --skip-comments --skip-extended-insert --order-by-primary` (satır başına bir INSERT; `AUTO_INCREMENT` dahil). Ayrıca her tablo için `COUNT(*)` ve `CHECKSUM TABLE … EXTENDED`. Tablolar: commentmeta, comments, links, **mb_audit_log**, options, postmeta, posts, termmeta, terms, term_relationships, term_taxonomy, usermeta, users.

| Karşılaştırma | db.sql fark satırı | Tablo sayısı/checksum farkı |
|---|---|---|
| A → B (CLI) | **0** | **0** |
| B → C (admin) | **0** | **0** |
| A → C | **0** | **0** |

Dedektör duyarlılık kanıtı: izole ortamda kasıtlı bir kanarya yazısı (1 termmeta satırı + 1 dosya) sonraki snapshot'ta db.sql'de 6 fark satırı, tablo listesinde 4 fark satırı (termmeta satır sayısı ve checksum değişimi) ve 2 dosya fark satırı üretti. Karşılaştırma kör değil.

## 12. Önce/sonra dosya karşılaştırması

WordPress ağacının tamamı (`/var/www/html`, `debug.log` hariç; çekirdek, eklenti, tema, mu-plugins, uploads, manifestler) sha256 listesi: 3497 dosya. A→B, B→C ve A→C: **0 fark**. `wp-content/uploads` listesi: 0 fark.

## 13. Debug log sonucu

| Aşama | Satır |
|---|---|
| Aktivasyon | 0 |
| Fixture yazımı | 0 |
| Snapshot A / B / C anı (CLI ve admin dahil) | 0 / 0 / 0 |
| Snapshot'lardan sonra, bilinçli ön yüz isteği (`GET /`) | 1 — `PHP Parse error … themes/mavibelge/template-parts/components/button.php on line 29` (tema; §4) |

## 14. Çalıştırılamayan testler

- Gerçek tarayıcı (klavye/görsel/JS/aXe) — yapılmadı; admin testleri HTTP istemcisiyle yapıldı.
- Gerçek staging (`cms-yeni.mavibelge.com.tr`) — **kullanıcı onayı ve bağlantı bilgisi bekliyor**; oluşturulmadı, bağlanılmadı.
- Üretim PHP uzantıları ve DB sürümüyle birebir ortam — üretim bilgisi doğrulanmadı.
- 32-bit PHP sınır testleri — yalnız 64-bit çalıştırıldı.
- Saldırgan kontrollü HTML içeren manifest ile kaçış — manifest salt okunur, denenmedi.
- Tema şablonlarının runtime render'ı (Faz 3/4/5) — Faz 6B2 kapsamı dışı; yalnız `GET /` gözlendi (500).

## 15. Açık engeller

1. **[BU TURDA KAPATILDI] Tema PHP parse hataları (bloklayıcı, Faz 3/4/5 tema dosyaları).** §4'teki iki dosya. Tema agentının sahipliğinde oldukları için değiştirilmedi. Entegrasyon notu:
   - `template-parts/components/button.php:29` — docblock'taki `aria-*/data-*` metni `aria-* / data-*` (veya `aria-*, data-*`) olarak yazılmalı. Gerekçe: `*/` yorumu kapatıyor. Geri alma: tek satır. Sıra bağımlılığı: yok.
   - `taxonomy-mb_sektor.php:86-103` — içteki `if ( '' !== $term_url ) { … }` bloğu `if ( '' !== $term_url ) : … endif;` biçimine çevrilmeli (veya dış blok süslü paranteze alınmalı). Gerekçe: sarkan else. Geri alma: blok sözdizimi eski hâline.
   - Sonra PHP 7.3 lint yeniden çalıştırılmalı ve ön yüz render'ı test edilmeli. Faz 3/4/5 "statik kabul" kayıtları bu dosyalar parse edilmezken verilmiştir; statik incelemenin PHP lint yerine geçmediğini gösteren somut bir örnek.
2. **[BU TURDA KAPATILDI] Faz 2 meta şeması: `_mb_level` hiç kaydedilemiyor (bloklayıcı; Faz 6B3 ve admin düzenleme için).** `Field_Repository::sanitize_and_validate()` select seçeneklerini `array_keys( $config['options'] )` ile kontrol ediyor. `'1'…'8'` anahtarları PHP'de int'e dönüşüyor; `MaviBelge_Core_Validator::is_valid_select()` ise katı `in_array(…, true)` kullanıyor. Sonuç olarak `"3"`, `3` ve `"03"` reddediliyor ve `register_post_meta` sanitize'ı değeri sessizce `''` yapıyor. WordPress 6.9.9'da doğrulandı. Faz 6B2 dry-run bu kaydı doğru biçimde `invalid_target_state` olarak işaretliyor; Faz 6B2 kusuru değil. Önerilen tek satırlık düzeltme (içerik modeli agentı): `array_map( 'strval', array_keys( $config['options'] ) )`, sayısal anahtarlı select'ler için bir test ile birlikte. Değiştirilmedi.
3. **Faz 6B3 tasarım bulguları (engel değil, önkoşul).**
   - (a) `_mb_certificate_print_fee_kurus` kayıtlı sanitize'ı TL girdisini kuruşa çeviriyor (150000 → 15000000); apply `update_post_meta` ile kuruş yazarsa değer 100 katına çıkar.
   - (b) `mb_ucret`/`mb_yeterlilik` üzerindeki marker sanitize'ı yanlış önekli marker'ı `''` yapıyor; çapraz-tür marker yalnız DB düzeyinde oluşabiliyor.
   - (c) **Yalnız serileştirilmiş dizi olarak bozulmuş bir marker `meta_value` sorgusuyla bulunamıyor** (senaryo 7a) ve kayıt create adayı görünüyor. Bir apply adımı böyle bir kayıt için ikinci bir terim/yazı oluşturabilir. Faz 6B3'te slug/kod düzeyinde ek bir çakışma kontrolü gerekiyor.

   Bu üçü Faz 6B3 apply tasarımında ele alınmalı.
4. Gerçek staging doğrulaması kullanıcı onayı/bilgisi bekliyor.

## 16. Faz 6B3'e geçiş önkoşulları

`faz6b3-apply-onkosullari.md`'deki yedi madde korunuyor. Durumları:

| Madde | Durum |
|---|---|
| 1. PHP 7.3 gerçek parser/test | **Yerel olarak karşılandı** (`mavibelge-core` lint 35/35, 557/557) |
| 2. Staging'de repository doğrulaması | **Yerel izole WordPress'te yapıldı**; gerçek staging yapılmadı |
| 3. Kurum incelemesi | Açık |
| 4. Conflict/duplicate/blocked kayıtların çözümü | Açık |
| 5. DB + uploads + yapılandırma yedeği | Açık |
| 6. Rollback provası | Açık |
| 7. Kullanıcının açık onayı | Açık |

Ek önkoşullar: §15'teki tema ve `_mb_level` hatalarının düzeltilmesi; §15.3'teki (a)–(c) tasarım bulgularının Faz 6B3 sözleşmesine eklenmesi.

## 17. Kabul önerisi

- **Faz 6B2 kodu (mavibelge-core import/CLI/admin):** PHP 7.3.33 ve izole WordPress 6.9.9 runtime'ında test edilen bütün kapıları geçti. Kanıtlar: lint 35/35; 557/557 (×2); 49/49 senaryo; 37/37 admin; CLI kontrolleri; A=B=C sıfır-yazma; debug log 0. Bu turda bulunan üç Faz 6B2 kusuru (geçici dizin temizliği, `mb_paged` `$` çapası, `wp_die` HTTP durumu) regresyon testiyle düzeltildi.
- **Görev §6 ve §18'e göre:** `wordpress-site` genelinde PHP 7.3 lint kapısı 2 tema dosyası nedeniyle **başarısız** olduğu için "Faz 6B2, PHP 7.3 ve izole WordPress runtime doğrulaması düzeyinde Codex bağımsız nihai kabul incelemesine hazırdır" ifadesi **kullanılmamıştır**.
- Öneri: tema parse hataları (ve tercihen `_mb_level` hatası) sahip agentlarca düzeltilip PHP 7.3 lint yeniden çalıştırıldıktan sonra Faz 6B2 Codex incelemesine sunulmalı. Codex, Faz 6B2 kapsamını tema engelinden ayrı değerlendirmek isterse bu rapordaki Faz 6B2'ye özgü kanıtlar yeterli ayrıntıdadır.
- Claude bu çalışmaya kabul vermez. Staging'de doğrulandığı, canlıda çalıştığı, apply'ın hazır olduğu veya Faz 6B3'ün başladığı **iddia edilmez**.

---

## Faz 6B2 Runtime Engellerinin Kapatılması (23 Eylül 2026)

> Bu bölüm, yukarıdaki ilk runtime turunun §15.1–§15.2'de listelenen üç bloklayıcısını kapatır ve bütün kapıları yeniden çalıştırır.
>
> Ortam: **aynı**, mevcut izole `mbruntime6b2` konteynerleri (PHP 7.3.33, WordPress 6.9.9, MariaDB 10.11.19, WP-CLI 2.12.0). Konteyner ve volume silinmedi, yeniden kurulmadı.
>
> Bu turda **hiçbir parola veya env dosyası okunmadı**:
> - komutlar konteyner adıyla `docker exec` üzerinden çalıştırıldı;
> - veritabanı anlık görüntüsü WordPress'in kendi `$wpdb` bağlantısıyla alındı (`scripts/db-snapshot.php`);
> - admin oturumu, WP-CLI'nin ürettiği oturum çerezleriyle açıldı (`scripts/make-cookies.php`); çerezler ekrana basılmadı.
>
> Sürücü: `wordpress-site/tools/runtime-test/run-regression.sh`.

### 1. Üç kök neden → dosya → düzeltme

| # | Kök neden | Dosya | Düzeltme |
|---|---|---|---|
| 1 | Docblock içindeki `aria-*/data-*` metnindeki `*/` yorumu erken kapatıyordu. Kalan açıklama PHP kodu olarak ayrıştırılıyordu (`unexpected '*'`). Tema etkinken ana sayfa HTTP 500 veriyordu. | `themes/mavibelge/template-parts/components/button.php` | Yalnız yorum metni: `aria-* / data-*`. Davranışsal kod değişmedi. |
| 2 | Dış `if ( … ) : … else : … endif;` bloğunun içinde süslü parantezli `if ( '' !== $term_url ) { … }` vardı. PHP, ardından gelen `else :`'i içteki if'e bağladı (sarkan else, `unexpected ':'`). | `themes/mavibelge/taxonomy-mb_sektor.php` | İçteki koşul `if ( '' !== $term_url ) : … endif;` sözdizimine çevrildi. Dış `if/else/endif`, HTML, sorgular, sayfalama ve boş sonuç davranışı değişmedi. |
| 3 | `'1'…'8'` gibi sayısal string seçenek anahtarları PHP dizisinde int'e dönüşüyor. `array_keys()` int listesi verdiği için `is_valid_select()`'in katı `in_array()`'i, `"3" !== 3` nedeniyle bütün seviyeleri reddediyordu. Sonuç: `_mb_level` ne admin'den ne de `update_post_meta` yolundan kaydedilebiliyordu. | `plugins/mavibelge-core/includes/class-field-repository.php` | Genel ve tek çözüm: `$allowed_values = array_map( 'strval', array_keys( $config['options'] ) )` doğrulayıcıya veriliyor. Bütün select alanları için geçerli; `_mb_level`'a özel bir kod yok. `$raw_str` string kalıyor, kanonik string dönüyor. |

### 2. Değişen dosyalar

| Dosya | Tür |
|---|---|
| `wp-content/themes/mavibelge/template-parts/components/button.php` | Düzeltme 1 (yalnız yorum) |
| `wp-content/themes/mavibelge/taxonomy-mb_sektor.php` | Düzeltme 2 |
| `wp-content/plugins/mavibelge-core/includes/class-field-repository.php` | Düzeltme 3 |
| `wp-content/plugins/mavibelge-core/tests/run.php` | +41 select regresyon assertion'ı (557 → 598); NOT covered notu |
| `wp-content/themes/mavibelge/tests/static/taxonomy-sector-syntax.test.js` (yeni) | Tema sarkan-else regresyon testi (6 kontrol) |
| `wordpress-site/tools/test-faz6b2-static-contract.js` | +3 engel kapanış sabiti; durum belgesi kontrolü güncellendi (164 → 167) |
| `wordpress-site/tools/runtime-test/scripts/{level-meta-test,render-fixtures,db-snapshot,make-cookies}.php`, `run-regression.sh`, `admin-http.js` (parolasız `warmup` modu), `README.md` | Yalnız test harness'i |
| Bu rapor, `raporlar/proje-durumu.md`, `README.md` | Durum kaydı |

Faz 6B3 bulgularına (kuruş ×100, yalnız dizi olarak bozulmuş marker, yanlış önekli marker) **dokunulmadı**.

### 3. Eklenen regresyon testleri

Her biri önce başarısız, düzeltmeden sonra geçen biçimde doğrulandı.

**`tests/run.php` select bloğu (41 assertion).** Gerçek `mb_yeterlilik`/`mb_ucret` `_mb_level` şema config'leriyle:
- Kabul: `"1"`, `"3"`, `"8"` aynı string olarak döner; int `3` kanonik `"3"` olarak döner.
- Boş değer: `""` ve `null` güvenli boş değer döner.
- Ret: `"0"`, `"9"`, `"03"`, `"1.0"`, `"1e1"`, `" 3"`, `"3 "`, `"+3"`, `"-1"`, dizi, nesne.
- String select regresyonu: `active`/`passive` kabul; `deleted`, dizi, nesne ve `"Array"` metni ret.
- Şemadaki **her** select alanının **her** seçeneği kendi kanonik değeriyle kabul ediliyor.

Düzeltme öncesi PHP 7.3'te exit 1, **589/598 (9 FAIL)**: sayısal select'in 8 kabul testi ve genel select testi. Düzeltme sonrası 598/598.

**`taxonomy-sector-syntax.test.js` (6 kontrol):**
- sarkan else yok;
- `if :`/`endif;` dengeli;
- sayfalama yalnız geçerli `$term_url` dalında;
- boş sonuç bileşeni dış `else` dalında;
- sonuç kartları dış `if` dalında.

Mutasyon kanıtı: eski yapıya karşı 4/6 FAIL; PHP 7.3 aynı mutasyonu "unexpected ':'" ile reddediyor. Güncel yapı 6/6 PASS.

**Statik sözleşme (+3):**
- select kanonikleştirmesi kaynakta mevcut;
- `tests/run.php` select regresyonlarını içeriyor;
- `button.php` docblock'unda `aria-*/data-*` yok (tek `*/`).

### 4. PHP 7.3 lint sonucu

PHP 7.3.33 ile `wordpress-site` altında bulunan **109** PHP dosyası tek tek `php -l` edildi: eklenti 35, tema 65, runtime-test betikleri 9.

Sonuç: **109/109 temiz, 0 başarısız**. Dosya sayısı önceki turdaki 105'ten, eklenen 4 test betiği kadar arttı.

Ayrıca `/var/www/html/wp-content/…` altında ayrı ayrı çalıştırıldı; üçü de "No syntax errors detected":
- `button.php`
- `taxonomy-mb_sektor.php`
- `class-field-repository.php`

### 5. Saf PHP test sonucu

Komut (iki kez): `php -d display_errors=1 -d error_reporting=-1 /var/www/html/wp-content/plugins/mavibelge-core/tests/run.php`

- İki çalıştırma da exit 0, **598/598**, 0 FAIL, stderr 0 bayt.
- İki çıktı `cmp` ile birebir aynı.
- Warning, notice, deprecated, fatal veya "Array to string conversion" çıktısı yok. Tek metin eşleşmesi, "NOT covered" listesindeki açıklama satırı.
- `/tmp/mb6b2_*` dizini kalmıyor.
- Select PASS satırları çıktıda görülüyor.
- Toplam önceki 557'den düşmedi (+41).

Tema `tests/php/button-attrs.test.php` dosyası bu projede ilk kez gerçek PHP 7.3'te çalıştırıldı: **6/6 geçti**.

### 6. `_mb_level` gerçek WordPress kayıt testi

`scripts/level-meta-test.php`, izole test veritabanında geçici `mb_yeterlilik`/`mb_ucret` yazıları oluşturur ve test sonunda kalıcı olarak siler. Sonuç: **42/42**, artı sayıma girmeyen 12 INFO gözlemi.

**(A) Kayıtlı sanitize yolu (`update_post_meta`):**
- `"3"` → `"3"`; int `4` → `"4"`.
- `"03"`, `"0"`, `"9"`, `"1.0"`, dizi ve nesne geçerli seviyeye dönüşmüyor.

**(B) Korumalı `Field_Repository::write_meta()`:**
- `"6"` → `"6"`; int `7` → `"7"`.
- `"03"`, `"0"`, `"9"`, `"1e1"`, dizi ve nesne `success=false` ile reddediliyor; önceki `"7"` **korunuyor**.

**String select'ler:**
- yeterlilik: `passive`/`active` kabul; `deleted` reddediliyor ve önceki değer korunuyor;
- ücret: `_mb_pricing_type` (single/unit/package/multiple) ve `_mb_record_status` (draft/active/archived) kaydediliyor.

**Açıkça raporlanan sözleşme gözlemi (INFO, görev maddesi 7).** Kayıtlı `update_post_meta` sanitize yolunda, geçerli `"5"` kaydedildikten sonra geçersiz bir giriş (`"03"`/`"0"`/`"9"`/`"1.0"`/dizi/nesne) gönderilirse saklanan değer **sessizce `''` oluyor**; önceki geçerli değer kaybolur.
- Neden: `class-meta-schema.php`'de belgelenmiş WordPress sınırı; `register_meta` sanitize callback'i bir yazmayı reddedemez.
- Güvenli yol: `write_meta()`. Önceki değeri koruduğu gerçekten doğrulandı. Admin meta kutusu da hatalı alanı atlıyor ve "Önceki değer korundu" diyor.
- Sonuç: Faz 6B3 apply kodu `update_post_meta` yerine `write_meta()` veya eşdeğer, reddetmeyi destekleyen bir yol kullanmalı.

**Ek kanıt:** 14 render test yeterliliği gerçek `wp_insert_post` + `meta_input` + yayın hazırlık kapısı yolundan `_mb_level="3"` ile **yayımlandı**. Düzeltme öncesinde bu yol seviyeyi hiç kaydedemiyordu.

### 7. Tema HTTP render sonucu

- İstemci: **curl** (gerçek HTTP, tarayıcı değil).
- Kalıcı bağlantılar `plain`: test imajında `mod_rewrite` etkin değil; bağlantılar `get_term_link()` ile sorgu biçiminde üretiliyor.
- Render fixture'ı: `plastik` sektöründe 14 yayımlanmış test yeterliliği (sayfa boyutu 12 → 2 sayfa). Kodlar manifestte olmayan `99UY90NN-3/01` ailesinden; import marker'ı yok, dry-run eşleşmelerini etkilemiyor.

| İstek | HTTP | Gözlem |
|---|---|---|
| `/` (ana sayfa) | **200** | `btn` bileşeni 6×, fatal/parse yok (önceki tur: **500**) |
| `/?mb_sektor=plastik` (sonuçlu sektör arşivi) | **200** | 12 kart, `<nav class="pagination">` var |
| `/?mb_sektor=plastik&mb_page=2` | **200** | 2 kart, sayfalama var |
| `/?mb_sektor=plastik&mb_q=zzqqxx-yok` (filtreyle boş) | **200** | boş sonuç bileşeni ("eşleşen yeterlilik kaydı yok"), sayfalama yok, `btn` var |
| `/?mb_sektor=mobilya` (yayımlanmış kaydı yok) | **200** | boş sonuç bileşeni |
| `/?mb_sektor=yok-boyle-bir-sektor` (geçersiz terim) | **404** | 404 şablonu, `btn` bileşeni var, fatal yok |
| `/?mb_sektor=plastik&mb_page=abc` (bozuk sayfa parametresi) | **200** | 1. sayfa, fatal yok |
| `/?p=999999` (iç sayfa: 404) | **404** | `btn` bileşeni render edildi |

Render sonrası WordPress debug log: **0 satır**. Yeni PHP parse error yok.

### 8. Node kapıları

| Kapı | Sonuç |
|---|---|
| `test-extract-safety.js` | 54/54 |
| `test-manifest-validation.js` | 85/85 |
| `verify-manifest.js` | 32/32 + FINALIZATION PASS |
| `verify-source-counts.js` | 9/9 |
| `test-theme-fee-text.js` | 3/3 |
| `test-faz6b2-static-contract.js` | **167/167** (durum belgeleri güncellendikten sonra) |
| Tema statik testleri (`tests/static/*`) | catalog-contract, heading-contract, nav-walker-filter-contract, **taxonomy-sector-syntax 6/6** — hepsi exit 0, 0 FAIL |
| Tema JS testleri (`tests/js/*`) | nav-active-page 6/6, mobile-focus-trap — hepsi exit 0, 0 FAIL |
| `node --check` (`wordpress-site` altındaki 31 JS dosyası) | Temiz |

### 9. Repository / CLI / admin sonucu

**Aktivasyon ve kayıtlar.** Eklenti aktif (mevcut kurulum). `check-registration.php` 7 CPT, 4 taksonomi, meta şemaları, 4 özel rol ve CLI sınıfını doğruladı. Gerçek ID ve meta tip yoklaması önceki turla aynı.

**Repository senaryoları.** 20 senaryo, **49/49** beklenti. Fixture beklentileri önceki turda, kod çalışmadan önce yazılmıştı; aynı veritabanı durumu kullanıldı.

**WP-CLI:**
- json ×2: exit 0, stderr 0; iki çalıştırma anlamsal olarak aynı.
- table: exit 0 (206 satır). Bayraksız çalıştırma: exit 0.
- `--format=xml`: exit 1. `--apply`: exit 1 (`unknown --apply parameter`).
- 200 kayıt (14/83/103). Karar dağılımı fixture durumundan yeniden hesaplandı: create 89, update 2, unchanged 4, conflict 13, blocked 92, invalid 0 (toplam = 200).
- `structurally_valid=true`, `applicable=false`; load/plan hatası 0.
- Tanı kaydı kapalı `{code,type,source_key}` şeklinde (`ambiguous_qualification_dependency`); yol veya gizli bilgi sızıntısı yok.

**Admin** (gerçek HTTP, parolasız oturum çerezi) — **37/37**:
- Yetkisiz iki kullanıcı 403 alıyor.
- Nonce eksik/bozuk/dizi/anahtarlı dizi → 400/403.
- `mb_paged` şu değerleri reddediyor: `"2\n"`, `"2\r\n"`, `"1e2"`, `"2.0"`, `"-2"`, `"0"`, `"+2"`, `" 2"`, `"02"`, dizi, anahtarlı dizi.
- 8 sayfa × 25 satır; 200 satır CLI JSON'u ile birebir aynı; operations özeti CLI ile aynı.
- Sonuç bölümünde yalnız beklenen HTML etiketleri var.

### 10. DB ve dosya sıfır-yazma sonucu

**Sıra:**
1. Düzeltmeler kaynakta yerinde (snapshot A'nın dosya listesi bu turun değişen dosyalarını ve yeni testi içeriyor).
2. Hazırlık: `_mb_level` meta testi, oturum çerezleri, ısınma istekleri.
3. Snapshot **A** → CLI dry-run → snapshot **B** → admin dry-run'ın tamamı → snapshot **C**.

**Snapshot içeriği:**
- `$wpdb` ile her tablo için `SHOW CREATE TABLE` (`AUTO_INCREMENT` dahil), birincil anahtara göre sıralı bütün satırlar, `COUNT(*)` ve `CHECKSUM TABLE … EXTENDED`.
- Tablolar: posts, postmeta, terms, term_taxonomy, term_relationships, termmeta, options, users, usermeta, comments, commentmeta, links, **mb_audit_log**.
- 3498 dosyanın sha256 listesi ve uploads listesi.

| Karşılaştırma | DB fark satırı | Tablo sayı/checksum farkı | Dosya farkı | Uploads farkı |
|---|---|---|---|---|
| A → B (CLI) | **0** | **0** | **0** | **0** |
| B → C (admin) | **0** | **0** | **0** | **0** |
| A → C | **0** | **0** | **0** | **0** |

**Kanarya:** kasıtlı olarak 1 termmeta satırı ve 1 dosya yazıldı. C→D farkı yakalandı: DB 10 fark satırı, tablo listesinde 4 fark satırı, dosya 2, uploads 4 fark satırı. Dedektör çalışıyor. Kanarya ardından geri alındı.

### 11. Debug log sonucu

Şu aşamaların hepsinde **0 satır**: hazırlık, snapshot A/B/C ve render. `level-meta-test.php` ve `render-fixtures.php` sonrasında da 0 satır.

### 12. Çalıştırılamayan testler

- Gerçek tarayıcı (klavye, görsel, JS, aXe, Lighthouse) — **yapılmadı**. HTTP testleri curl ve Node `fetch` ile yapıldı.
- Gerçek staging (`cms-yeni`) — yapılmadı; kullanıcı onayı ve bağlantı bilgisi bekliyor.
- Kalıcı bağlantılı (pretty permalink) URL'ler — test imajında `mod_rewrite` kapalı; yalnız sorgu biçimli bağlantılar test edildi.
- Üretimle birebir eklenti/DB sürümü, 32-bit PHP ve saldırgan kontrollü HTML içeren manifest.
- Ortamın sıfırdan yeniden kurulumu — bu turda yapılmadı (görev: mevcut ortamı kullan, parola okuma). Sıfırdan kurulum kanıtı önceki turdadır (`run-all.sh`).

### 13. Faz 6B3'e taşınan tasarım bulguları (çözülmedi, Faz 6B3 önkoşulu)

1. `_mb_certificate_print_fee_kurus` kayıtlı sanitize'ı TL girdisini kuruşa çeviriyor (×100).
2. Yalnız serileştirilmiş dizi olarak bozulmuş bir marker `meta_value` sorgusuyla bulunamıyor; kayıt create adayı görünüyor.
3. Yanlış önekli marker kayıtlı sanitize tarafından `''` yapılıyor; çapraz-tür marker yalnız DB düzeyinde oluşabiliyor.
4. **(Bu turda eklendi)** Kayıtlı `update_post_meta` yolunda geçersiz giriş, önceki geçerli değeri sessizce `''` ile eziyor (§6). Apply, `write_meta()` gibi reddetmeyi destekleyen bir yol kullanmalı.

### 14. Commit / push / deploy

Commit, push, tag, release veya deploy **yapılmadı**. Hiçbir dosya stage edilmedi.

### 15. Canlı sistem

- Canlı site, `yeni.mavibelge.com.tr`, `cms-yeni`, DNS, SSL, DirectAdmin, FTP, SSH ve mail sistemine **bağlanılmadı, dokunulmadı**.
- `tanitim-site/**` değişmedi.
- Katalog verisi aktarılmadı; yalnız yerel izole test veritabanına test fixture'ları yazıldı.
- Faz 6B3 başlamadı.

### Sonuç

Bütün yerel PHP 7.3 ve izole WordPress runtime kapıları geçti.

**Faz 6B2, PHP 7.3.33 ve izole WordPress 6.9.9 runtime doğrulaması düzeyinde Codex bağımsız nihai kabul incelemesine hazırdır.**

Sınırlar:
- Gerçek staging yapılmadı.
- Gerçek tarayıcı testi yapılmadı.
- Üretim sürümü kilitlenmedi.
- Faz 6B3 başlamadı; apply hazır değil.
- Kurum incelemesi, staging dry-run, yedek, geri dönüş provası ve kullanıcı onayı hâlâ açık.
- §13'teki Faz 6B3 tasarım bulguları çözülmedi.

Claude bu çalışmaya kabul vermez.

---

## Sonraki tur notu — Faz 6B3 Önkoşul ve Yazma Güvenliği Kapanışı (23 Eylül 2026)

Bu rapordaki §15.3 ve "Runtime Engellerinin Kapatılması" §13'te listelenen dört Faz 6B3 tasarım bulgusu **çözüldü**:

- kuruşun 100 katına çıkması;
- yalnız dizi olarak bozulmuş marker'ın `create` adayı görünmesi;
- yanlış önekli marker'ın sessizce `''` olması;
- geçersiz girişin önceki geçerli değeri ezmesi.

Kuruş bulgusunun mevcut admin kaydetme yolunu da etkilediği bu turda ölçüldü ve o da giderildi. Kanıt ve sözleşme: [`faz6b3-yazma-guvenligi-sozlesmesi.md`](./faz6b3-yazma-guvenligi-sozlesmesi.md). Yukarıdaki tarihsel bulgular olduğu gibi korunmuştur.
