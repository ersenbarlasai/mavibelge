# İçerik Servisleri Sözleşmesi (Faz 7 — haber, doküman, referans, lokasyon, SSS)

Kod: `includes/class-content-query.php` (SAF), `public/class-content-service.php`, `public/class-visibility-guard.php`,
`includes/class-content-admin-rules.php` + `admin/class-content-admin.php`, tema `inc/content-helpers.php` ve `template-parts/content/*`.
İçe aktarım hattı: `docs/content-import-contract.md`.

## Görünürlük (tek tanım: `Content_Service::public_meta_query`)
Yalnız `publish`; haber → `_mb_approval_status=approved`; doküman/referans/lokasyon/SSS → `_mb_record_status` **passive değil** (eksik meta = aktif);
yeterlilik → `active`. Aynı kural: servis listeleri, ana sorgu arşivleri (`pre_get_posts`), tekil URL'ler (kapıya takılan kayıt **gerçek 404**, düzenleme yetkisi olan görür),
site içi arama sonuçları ve sitemap. Dosya/bağlantı güvenliği: doküman dosyası yalnız gerçek `attachment` + izinli MIME (PDF/DOC/DOCX/XLS/XLSX) + diskte var; logo yalnız PNG/JPEG/WebP/GIF (SVG yok);
dış bağlantı yalnız https; SSS cevabı `script/style` içerikleriyle birlikte temizlenir + `wp_kses_post`.

## Sınırlar
Haber/doküman sayfa boyutu 12 (`?mb_page=N`, en çok 500, aralık dışı → son sayfa); referans 60, lokasyon 20, SSS 100. Filtreler: `mb_type` (haber|duyuru), `mb_cat` (var olmayan kategori → **sıfır sonuç**, sessiz yok sayma yok).

## Tema
Tema içerik türleri için doğrudan `WP_Query/get_posts/$wpdb` kullanmaz (statik test 257/257). Eklenti pasifken adaptörler güvenli boş şekil döner; şablonlar dürüst boş durum gösterir (QA: 7 sayfa eklenti pasif render'ı).
Ana sayfa haber şeridi, referans ızgarası (slider yok), footer ve iletişim lokasyonları `mb_lokasyon` verisini kullanır; kayıt yoksa doğrulanmış statik yedek.

## Yönetim
Beş türde "Görünürlük" (neden gizli) ve "Yayın Hazırlığı" (eksik zorunlu alan) sütunları, kapalı allowlist filtreleri (onay/kayıt/referans türü/doküman süresi/kategori), `_mb_sort_order` sıralaması. Roller/yetkiler Faz 2 modelinde (`roles-capabilities.md`); onay geçişi yetkisi `publish_mb_haberler`.
Kayıtsız içerik: doküman/SSS/lokasyon/gerçek referans için doğrulanmış kaynak olmadığından **boş** teslim edilir; 6 haber + 12 temsili referans manifesti hazırdır (gerçek DB'ye apply edilmedi).
