# Faz 10 — Performans, Güvenlik ve Bakım

Kapsam: `mavibelge-core` eklentisi ve `mavibelge` teması. Sunucuya, canlıya, staging'e, DNS'e veya e-postaya dokunulmamıştır; her şey yerel izole ortamda (PHP 7.3.33 + WordPress 6.9.9 + MariaDB 10.11, silinebilir `mbfx_` klonu) doğrulanmıştır.

> **AÇIK RİSK (çözülmüş gibi gösterilmez):** Üretim PHP 7.3 (EOL) ve CentOS 7 (EOL) üzerindedir. Bu, kullanıcının kabul ettiği bağlayıcı bir kısıttır; **güvenli platform değildir.** WordPress 6.9 hattı PHP 7.3 ile uyumlu son ana hattır ve aktif güvenlik bakımında değildir; hiçbir WordPress/eklenti sürümü kilitlenmemiştir. Bu fazın sertleştirmeleri (oran sınırı, REST/XML-RPC kapatma, başlıklar, audit bütünlüğü) saldırı yüzeyini azaltır, **işletim sistemi/PHP yama eksikliğini gidermez.** Sağlık ekranı bu durumu `Uyarı` olarak açıkça yazar.

## 1. Ne yapıldı (kodda etkin)

| # | Madde | Yer | Kanıt |
|---|---|---|---|
| 1 | Ön yüzde emoji, wp-embed/oEmbed, `wp_generator`, RSD/WLW/shortlink, blok kitaplığı/klasik tema/global styles, jQuery + jquery-migrate kaldırıldı (yalnız ön yüz; yönetim/editör/özelleştirici etkilenmez). Süzgeçler: `mavibelge_strip_wp_defaults`, `mavibelge_dequeue_jquery`. Sürüm: dosya `filemtime` (yoksa tema sürümü). | tema `inc/assets.php` | HTTP 44/44 |
| 2 | Yerel görseller: header logo `width/height` + `decoding=async` + `fetchpriority=high` (lazy yok); header güven logoları ekran üstü (lazy/fetchpriority yok); footer logosu `lazy`. Boyutlar `getimagesize` ile gerçek dosyadan; CSS'in sabit yüksekliğine orantılı ölçeklenir (footer logosunda bozulma olmaz). | tema `inc/images.php`, `header.php`, `footer.php`, `trust-logo.php` | HTTP (oran = gerçek PNG oranı) |
| 3 | Kısa ömürlü önbellek (TTL 300 sn): `MaviBelge_Core_Cache` — anahtar `mbc_<nesil>_<sha1(tür\|dil\|sayfa\|filtre)>`; nesil sayacı tek atomik `UPDATE` ile artar. Kapalı: `WP_DEBUG`, `MAVIBELGE_CACHE_DISABLED`, düzenleme yetkili oturum. Okuma fail-open. Saf DTO dışında (nesne) saklanmaz. Serbest metin araması ve var olmayan sayfa/filtre **saklanmaz** (anahtar uzayı sınırlı). | `includes/class-cache.php`; Content/Catalog servisleri | birim + runtime B1–B21 |
| 4 | N+1: ek/logo/küçük resim kimlikleri toplu ısıtılır (`_prime_post_caches`). 12 kartlık haber listesi 7 sorgu, 4 kart ile 12 kart arası fark 1 (eşik ≤ 3). | Content_Service | runtime C1–C4 |
| 5 | Sertleştirme: XML-RPC kapalı (403), anonim REST 401 (`mavibelge_core_rest_public_routes` allowlist'i boş), `list_users` yetkisiz oturumda kullanıcı uç noktaları 404, `?author=` ve yazar arşivi 404 (yönlendirme sızıntısı yok), genel giriş hata mesajı, giriş oran sınırı (10 başarısız / 15 dk / HMAC istemci; sayaç hatasında **fail-closed**). | `includes/class-hardening.php` | HTTP + runtime D1–D7 |
| 6 | Güvenli başlıklar (yalnız alt küme): `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options: SAMEORIGIN`, `Permissions-Policy`; `X-Powered-By`/`X-Pingback` kaldırılır. **HSTS ve CSP PHP'de YOKTUR.** Süzgeç: `mavibelge_core_security_headers`. | Hardening | HTTP |
| 8 | Tek oran sınırı altyapısı `MaviBelge_Core_Rate_Limit` (formlar ve giriş paylaşır); Faz 8 davranışı ve transient anahtarları aynen korunur. | `includes/class-rate-limit.php`, `class-forms-service.php` | mevcut form testleri + HTTP oran sınırı |
| 9 | Audit bütünlüğü: tablo sürüm 2 (`prev_hash`, `row_hash`), `GET_LOCK` ile serileşen zincir (kilit alınamazsa satır **yazılmaz**), `verify_chain()`, saklama (form 90 / diğer 365 gün; iki ayrı zincir olduğu için silme yalnız ön eki siler, ilk kalan satırın `prev_hash`'i çapa). Günlük bakım WP-Cron: `mavibelge_core_daily_maintenance` (idempotent zamanlama, devre dışı bırakınca kaldırılır) + 1 saatten eski `mavibelge-forms-tmp` dosyalarının silinmesi. | `audit/class-audit-chain.php`, `class-audit-log.php`, `includes/class-maintenance.php` | birim + runtime A0–A21 |
| 10 | Salt okunur sağlık ekranı (Araçlar → Mavi Belge Sağlık, `manage_options`): PHP/uzantı/InnoDB/collation/kalıcı bağlantı/cron/disk/yazma izni/WordPress sürümü/debug/dosya düzenleme/HTTPS/ortam/`blog_public`/form kapıları/yönlendirme/audit zinciri/önbellek. Hiçbir ayarı değiştirmez; gizli bilgi göstermez. | `includes/class-health-checks.php` (saf), `admin/class-health-page.php` | birim + HTTP + runtime E1–E6 |
| 11 | Sürüm uyumluluk: PHP < 7.3 veya WordPress < 6.9 ise yönetim uyarısı ve eklentinin hiçbir işlevi yüklenmez (fatal yok). Uninstall: **varsayılan hiçbir şey silinmez**; yalnız `mavibelge_core_delete_data_on_uninstall` açıkça `true` ise kapalı allowlist (audit/import tabloları, eklenti seçenekleri, cron kancası). İçerik, roller ve `mb_active_tariff_period` **asla** silinmez. | `includes/class-compat.php`, `class-uninstall-scope.php`, `uninstall.php` | birim + runtime F1–F4 |
| 12 | Kaynak taraması: `var_dump/print_r/phpinfo` yok, `error_log()` yalnız sabit metinle; eklenti/tema kullanıcıya hata ayrıntısı göstermez. | — | birim (2 test) |

## 2. Şablon olarak kaldı (uygulanmadı, sunucu doğrulaması ister)

`deploy/` altında: `wp-config-hardening.template.php`, `htaccess-hardening.template`, `uploads-htaccess.template`, `security-headers.htaccess.template` (bkz. `deploy/README.md`). **Apache sözdizimi bu depoda doğrulanamaz**; Apache sürümü, modüller (`mod_headers`, `mod_rewrite`), PHP çalışma biçimi (mod_php/suPHP/FPM) bilinmiyor. HSTS ve CSP yorum satırındadır; HTTPS/CDN doğrulanmadan açılmaz.

## 3. Kurum kararı / sunucu doğrulaması gereken maddeler

- PHP 7.3 / CentOS 7 / DirectAdmin Legacy'nin yenilenmesi veya izlenen risk olarak kabulün yenilenmesi.
- Gerçek cron: `DISABLE_WP_CRON` yalnız gerçek bir cron kurulup doğrulandıktan sonra `true` yapılır; aksi hâlde günlük bakım yalnız ziyaretle tetiklenir (düşük trafikte gecikir).
- HTTPS/HSTS/CSP, `FORCE_SSL_ADMIN` (sertifika yenileme ve http→https yönlendirmesi doğrulanınca).
- WordPress 6.9.x yama sürümü, otomatik güncelleme politikası (`WP_AUTO_UPDATE_CORE`, `AUTOMATIC_UPDATER_DISABLED`), eklenti/tema uyum kilidi.
- Webroot dışı log yolu; `wp-content/debug.log` erişim engeli.
- Anonim REST tamamen kapalıdır. İleride bir genel REST ihtiyacı (ör. harici form) olursa yalnız `mavibelge_core_rest_public_routes` allowlist'iyle, kurum onayıyla açılır. Not: WordPress Site Health'in bazı REST/oturumsuz döngü testleri bu nedenle uyarı verebilir.
- Kalıcı bağlantılar: yerel test ortamında düz bağlantıdır; üretimde güzel bağlantı ve sunucu `mod_rewrite` doğrulanmalıdır (sağlık ekranı uyarır).
- Audit saklama süreleri (90/365) kurum/KVKK kararına göre `mavibelge_core_audit_retention_days` süzgeciyle değiştirilir (en az 30).

## 4. Bilinen sınırlar

- **Audit zinciri** DEĞİŞTİRME ve ARADAN silmeyi yakalar; zincirin SONUNDAKİ satırların silinmesi ve veritabanı yöneticisinin tüm zinciri yeniden hesaplaması tespit edilemez (dış imza/yedek gerekir). Eski (v1) satırlar zincir dışıdır (`legacy`). İçe aktarım (import) apply'ı transaction içindeyse geri alınan audit satırı zincire girmez (zincir her yazımda veritabanındaki son hash'ten okunur).
- **Önbellek**: nesne önbelleği olmayan ortamda transient'lar `options` tablosundadır (autoload dışı, TTL 300 sn). Süreye/tarihe bağlı alanlar (doküman süresi, ücret geçerlilik penceresi) anahtara gün olarak girer; en fazla 5 dk bayat veri mümkündür.
- **Giriş oran sınırı** istemciyi yalnız `REMOTE_ADDR`'dan ayırır (vekil başlıklarına güvenilmez); ters vekil arkasında tüm ziyaretçiler tek istemci gibi görünebilir — sunucu vekil yapısı doğrulanmalıdır. Sınıra takılan istemci, doğru parola girse bile 15 dk'ya kadar kilitli kalır (çekirdek parola denetimi yine çalışır; sonuç geçersiz kılınır).
- **Hero görseli** CSS `background-image` olduğundan `srcset`/`loading` uygulanamaz; ayrı boyutlu dosya (`hero-home.png` 865 KB tek dosya) yoktur. Sıkıştırma/ikinci boyut üretimi görsel varlık kararıdır.
- Şablonlar Apache ile denenmedi (bkz. §2).

## 5. Bakım kontrol listesi

Aylık: sağlık ekranı `Uyarı/Hata` gözden geçirilir (özellikle audit zinciri, disk, cron); WordPress/eklenti yaması uyum matrisiyle denenir (staging); yedek + **geri dönüş denemesi**. Her yayın öncesi: `tests/run.php` (PHP 7.3), statik sözleşme, `content-http.sh`. Üç ayda bir: audit tablosu boyutu ve saklama; kullanılmayan kullanıcı/uygulama parolası; `mavibelge_core_rest_public_routes` allowlist'inin hâlâ boş/gerekli olduğu.

## 6. Geri alma

- Önbellek: `define( 'MAVIBELGE_CACHE_DISABLED', true )` veya `add_filter( 'mavibelge_core_cache_enabled', '__return_false' )`; nesil sıfırlama: `do_action( 'mavibelge_core_cache_flush' )`. Transient'lar 5 dk içinde kendiliğinden ölür.
- Sertleştirme kancaları: eklenti pasifleştirilir (veri silinmez) veya ilgili süzgeç kullanılır (`mavibelge_core_security_headers` → boş dizi, `mavibelge_strip_wp_defaults` → false, `mavibelge_core_rest_public_routes`).
- Audit v2: sütunlar geriye dönük uyumludur (eski satırlar `row_hash=''`); v1'e dönmek gerekmez. Zincir sütunları yoksa `record()` eski biçimde yazar.
- Bakım cron'u: eklenti pasifleştirilince kanca kaldırılır; elle: `wp cron event delete mavibelge_core_daily_maintenance`.
- Şablonlar: eklenen bloğu silmek yeterlidir.
- Uninstall'ı denemeden önce mutlaka yedek alın; varsayılan güvenlidir.

## 7. Testler

| Komut | Sonuç |
|---|---|
| `php tests/run.php` (PHP 7.3.33; konteynerde `mavibelge-core/`) | 1117/1117, iki ardışık koşu byte-eşit (56 yeni test: `tests/suites/faz10-perf-security.php`) |
| `bash tools/runtime-test/with-lock.sh bash tools/runtime-test/content-http.sh <çıktı>` | 55/55 (içerik/form) + 38/38 (SEO) + **44/44 (Faz 10 HTTP)** + **66/66 (Faz 10 runtime)**; ana DB/dosya/uploads farkı 0, debug.log 0 |
| `node tools/test-faz10-deploy-templates.js` | 41/41 (Apache sözdizimini DOĞRULAMAZ) |

Mutasyon doğrulaması: önbellek düzenleme-yetkisi kapısı, audit satır-değişikliği denetimi, oran sınırı fail-closed kapısı (birim) ve anonim REST kapatma (HTTP) tek tek bozuldu, testler yakaladı, geri alındı.
