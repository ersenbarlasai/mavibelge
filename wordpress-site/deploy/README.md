# deploy/

Dağıtım ve sunucu şablonları. **Hiçbiri gerçek bir sunucuya, gerçek `wp-config.php`'ye veya `.htaccess`'e uygulanmamıştır**; `cms-yeni.mavibelge.com.tr` planlanmıştır, oluşturulduğu varsayılmaz. Paketleme/yükleme/yedek/geri dönüş prosedürleri ayrı bir görevdir.

## Faz 10 — sertleştirme şablonları

| Dosya | Amaç | Durum |
|---|---|---|
| [`wp-config-hardening.template.php`](wp-config-hardening.template.php) | `DISALLOW_FILE_EDIT`, `WP_DEBUG`/`WP_DEBUG_DISPLAY` kapalı, webroot dışı log önerisi, `WP_ENVIRONMENT_TYPE`, `FORCE_SSL_ADMIN`, gerçek cron notu (`DISABLE_WP_CRON`), `WP_POST_REVISIONS`, otomatik güncelleme notu. Tuz/parola **yer tutucudur**. | Şablon; wp-config.php DEĞİL |
| [`htaccess-hardening.template`](htaccess-hardening.template) | `wp-config.php`, `xmlrpc.php`, `.git`, `*.log`, `readme.html`, `license.txt`, `debug.log` erişimi kapalı; dizin listeleme kapalı. Apache 2.4 + eski `mod_access_compat` ikilisi. | Şablon; Apache ile doğrulanmadı |
| [`uploads-htaccess.template`](uploads-htaccess.template) | `wp-content/uploads` içinde PHP/PHAR/PHTML/CGI yürütme ve erişim kapalı, `Options -ExecCGI`. | Şablon; Apache ile doğrulanmadı |
| [`security-headers.htaccess.template`](security-headers.htaccess.template) | `X-Powered-By` kaldırma; HSTS (yorum satırı + şartlar); CSP yalnız report-only örneği (yorum satırı). | Şablon; HTTPS/CDN doğrulanmadı |

**Uygulama sırası (öneri):** staging'de deneyin → `apachectl configtest` (veya DirectAdmin denetimi) → dosyaları tek tek uygulayın → siteyi ve yönetimi elle doğrulayın → sonraki dosya. **Geri alma:** eklenen bloğu silmek yeterlidir (her şablon bağımsızdır). PHP tarafında güvenli başlık alt kümesi, XML-RPC/REST/kullanıcı numaralandırma kapatma ve oran sınırı zaten `mavibelge-core` içinde etkindir; bu şablonlar ikinci katmandır. Ayrıntı ve açık riskler: [`../docs/performance-security-maintenance.md`](../docs/performance-security-maintenance.md).

Şablonların yapısını Node statik testi doğrular (`node tools/test-faz10-deploy-templates.js`): gerçek anahtar/parola benzeri değer yok, gerekli yönergeler var. Bu test Apache sözdizimini DOĞRULAMAZ.
