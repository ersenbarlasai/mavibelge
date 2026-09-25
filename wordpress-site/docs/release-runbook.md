# Yayın Paketi, Staging Kurulum ve Geri Dönüş Runbook'u

> **Paket üretmek deploy yetkisi DEĞİLDİR.** Bu depo sunucuya, FTP/SSH/DirectAdmin'e, DNS'e, SSL'e veya mail sistemine bağlanmaz. Aşağıdaki adımlar
> kullanıcının **açık onayıyla**, sistem yöneticisi tarafından uygulanacak kontrol listesidir. Üretim WordPress sürümü **kilitlenmemiştir**
> (PHP 7.3 kısıtı için bkz. `compatibility.md`, `raporlar/karar-kaydi-wordpress-php73.md`; hedef WordPress 6.9.x hattı koşullu/legacy, güvenlik bakımında değildir).

## 1. Paketler (yerel, deterministik)
```bash
node wordpress-site/tools/build/build-theme-assets.js --write    # tema dist'i src'den (byte-eşit doğrulanır)
node wordpress-site/tools/package/build-packages.js --write      # wordpress-site/dist-packages/
node wordpress-site/tools/package/test-package.js                # determinizm, allowlist, gizli bilgi, PHP 7.3, zip bütünlüğü, kaynak==dist
```
Çıktı: `mavibelge-theme-<sürüm>.zip`, `mavibelge-core-<sürüm>.zip`, `checksums.sha256`, `package-manifest.json` (dosya başına SHA-256).
Allowlist: yalnız çalışma zamanı dosyaları (php/css/js/json/görsel/font/dil). Girmeyenler: `tests/`, fixture, `docs/`, `*.md`, `*.sh`, `.env*`, `*.log`, `*.zip`, tema `assets/src/` (yalnız `editor.css`), rapor, geliştirme araçları.
Doğrulama: aynı kaynak + aynı Node sürümü → byte-eşit zip. Paketi teslim alan taraf `sha256sum -c checksums.sha256` ile doğrular.

## 2. Gerekli PHP uzantıları (staging'de sistem yöneticisi doğrular)
`mysqli`, `json`, `mbstring`, `fileinfo`, `openssl`, `curl`, `dom`/`xml`, `zip` (çekirdek güncellemeleri için). Kaynak: `docs/compatibility.md`, `raporlar/wordpress-sunucu-bilgi-talebi.md`.
WordPress/PHP: hedef WordPress 6.9.x + PHP 7.3 **koşullu/legacy**; sürüm/yama kilitlenmemiştir; PHP 7.3 ve CentOS 7 EOL riski **yüksek** olarak açıktır.

## 3. Kurulum sırası (staging — `cms-yeni` **henüz yoktur**, oluşturmak ayrı onay ister)
1. **İki doğrulanmış yedek** (dosyalar + veritabanı; farklı konum; geri yükleme provası) — yoksa **başlanmaz**.
2. Parola korumalı + `noindex` staging alanı (üretim alan adına DNS/SSL değişikliği yok).
3. WordPress çekirdeği (sistem yöneticisinin belirlediği sürüm) kurulur; `wp-config.php` şablonu `deploy/wp-config-hardening.template.php` ile (gerçek anahtarlar depo dışında).
4. Tema zip'i → `wp-content/themes/`; eklenti zip'i → `wp-content/plugins/`. Önce **eklenti**, sonra **tema** etkinleştirilir. Etkinleştirme roller, tablo ve terimleri (haber/duyuru) idempotent kurar.
5. `Ayarlar → Kalıcı Bağlantılar` = "Yazı adı"; `.htaccess` şablonları (`deploy/`) sistem yöneticisi onayıyla uygulanır.
6. **Sağlık ekranı** (Araçlar) ve `wp mavibelge import catalog --dry-run` salt-okunur kontrolleri.

## 4. Veri aktarımı aşamaları (dry-run → kurum incelemesi → apply)
1. **Dry-run** (varsayılan, hiçbir şey yazmaz): `wp mavibelge import catalog --dry-run --stage=sectors|qualifications|all|content --format=json`; çıktı `plan_digest` + `applicable`.
2. **Kurum incelemesi**: rapor (`raporlar/veri-aktarim-raporlari/`) ve dry-run çıktısı kurumca onaylanır; kodsuz 19 ücret, sektör görselleri gibi açık kararlar çözülür.
3. **İki doğrulanmış yedek** + geri dönüş provası.
4. **Apply penceresi**: anahtar **yalnız pencere süresince** açılır — `define( 'MAVIBELGE_IMPORT_APPLY_ENABLED', true );` (wp-config) + `--user=<yönetici>` + `--confirm=<plan_digest>`; aşama sırası `sectors → qualifications → all` (içerik: `content`). Pencere bitince sabit **hemen kapatılır/silinir**.
5. Doğrulama: `wp mavibelge import status`; dry-run yeniden `unchanged`.
6. Redirect aynı biçimde: `wp mavibelge redirects import --file=...` dry-run; apply `MAVIBELGE_REDIRECTS_APPLY_ENABLED` penceresiyle.
Gerçek katalog/staging/canlı apply bu depoda **hiç çalıştırılmadı**.

## 5. Geri dönüş (rollback) runbook'u
- Uygulama sonrası: `wp mavibelge import rollback --run-id=<uid>` (önizleme + `--confirm=<rollback_digest>`); kullanıcı değişikliği (drift) varsa **reddedilir**, veri korunur. Rollback **yedeğin yerine geçmez**.
- Tam geri dönüş: yedekten dosya+DB geri yükleme (prova edilmiş olmalı); eklenti/tema önceki sürüm zip'ine döndürülür; `MAVIBELGE_*_APPLY_ENABLED` sabitleri kapalıdır.
- Rollback ölçütleri: 5xx artışı, form/e-posta anomalisi, indekslenme kaybı, veri bütünlüğü hatası.

## 6. Smoke test kontrol listesi (staging)
Ana sayfa, `/meslekler`, sektör sayfası, ücret sayfası, haber arşivi + tekil, doküman arşivi, iletişim (form **kapalıysa** "şu anda kullanılamıyor"), 404, arama; `robots.txt` = `Disallow: /`; sayfalarda `noindex,nofollow`; menü/mobil menü/klavye; konsol hatası yok; `debug.log` boş; `wp-sitemap.xml` yalnız izinli türler.
Otomatik karşılığı: `tools/runtime-test/content-http.sh`, `qa-render.sh`, `tools/qa/run-all-gates.sh --runtime` (yerel Docker; **staging'de çalıştırılmaz**).

## 7. Eski site ve DNS'e dokunmadan staging doğrulama
Üretim alan adı ve DNS değişmez; staging ayrı (parola korumalı, noindex) alt alan adında yürür. Hızlı kontrol için istemcide `hosts` girdisi/`curl --resolve` kullanılır (**DNS kaydı değiştirilmez**).
`https://yeni.mavibelge.com.tr/` statik referanstır — **üzerine WordPress kurulmaz**.

## 8. Mail sistemine dokunmama kontrolü
Bu iş MX/SPF/DKIM/DMARC/posta kutusu ayarlarına **dokunmaz**. Form e-postaları yalnız `wp_mail` ile gider ve formlar **varsayılan kapalıdır**. Gerçek e-posta teslim testi ayrı, açık onayla ve yalnız sentetik gönderimle yapılır. Sunucudan e-posta **silinmez**: iki doğrulanmış kopya oluşmadan silme koşulsuz yasaktır.

## 9. Canlı geçiş kontrol listesi (ayrı, açık onay gerekir)
Kurum içerik kararları tamam (`institution-decisions.md`); iki yedek + geri dönüş provası; yönlendirme kümesi onaylı (yalnız `verified`); nihai eski URL envanteri (canlı sitemap/GSC/log) işlenmiş; PHP/DB sürümleri doğrulanmış; SSL/HSTS ve güvenlik başlıkları sunucuda doğrulanmış; formlar kurum kararlarıyla açılmış ve gerçek e-posta teslimi denenmiş; izleme (404/301, sunucu logu); geri dönüş penceresi planlı. Geçiş: DNS/SSL adımları DevOps agentının kartındadır — bu depo yapmaz.

## 10. Kurulum sonrası operasyonel açıklar (kod eksikliği DEĞİL)
Kurum kararları, gerçek staging kurulumu ve dry-run, kurumun veriyi incelemesi, iki yedek + geri dönüş provası, kullanıcının ayrı apply onayı, canlı geçiş, sunucu bilgileri, gerçek e-posta teslim testi, gerçek kişisel veri formlarının aktivasyonu.
