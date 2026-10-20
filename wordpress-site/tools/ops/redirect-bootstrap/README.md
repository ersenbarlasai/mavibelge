# Geçici yönlendirme deposu loader'ı (tek kullanımlık canlı operasyon aracı)

**Amaç:** Canlıda `mavibelge_core_redirects` seçeneği hiç oluşturulmadığı için Mavi Belge Core'un yönlendirme
kayıt sistemi boş çalışıyor (Sağlık ekranı: "Toplam 0, etkin 0"; `/kvkk-2/` ve `/gizlilik-politikamiz/` 404).
Sunucuda SSH/WP-CLI olmadığından `wp mavibelge redirects import` kullanılamaz. Bu araç, aynı doğrulama ve kayıt
yolunu (`MaviBelge_Core_Redirects_Rules::validate_set()` + `MaviBelge_Core_Redirects_Service::save_rules()`)
WordPress yönetiminden, tek seferlik ve geri alınabilir biçimde çalıştırır.

Kalıcı ürün kodu **değildir**; tema/eklenti paketlerine girmez. Canlıda iş bitince iki dosya silinir; WordPress
seçeneği (depo) kalır.

## Dosyalar

| Dosya | Görev |
|---|---|
| `mavibelge-redirect-bootstrap.php` | MU-loader kaynağı (PHP 7.3). ZIP'e bayt-eşit girer. |
| `build-package.js` | Deterministik ZIP üretir; manifest SHA-256 tutmazsa üretmez. `--write` → `dist/` (Git dışı). |
| `test-package.js` | ZIP yapısı, bayt eşitliği, hash, determinizm. |
| `tests/run.php`, `tests/fake-wp.php` | Loader birim testleri (gerçek Core yönlendirme sınıfları + sahte WordPress yüzeyi). |
| `tests/runtime.sh` | Gerçek WordPress 6.9.9 + PHP 7.3.33 (mevcut `mbruntime6b2-*` Docker, silinebilir `mbfx_` klonu) uçtan uca test; ana DB/dosya farkı ölçülür. |
| `tests/runtime-wp.php` | Runtime: hedef fixture'ları + WP-CLI düzeyinde gerçek seçenek/nonce/yetki testleri. |
| `tests/runtime-http.js` | Runtime: gerçek admin-post import/rollback, Sağlık 29/4, dört kaynağın 301 → 200 davranışı. |

Manifest kopyası depoda tutulmaz; paket, yetkili `wordpress-site/data/redirects/redirects.manifest.json`
dosyasından üretilir (SHA-256 `EBD84CE828EFBC1B12490B79B838D6534C70141BF04DA849AAB7070D34597792`, 29 kural / 4 etkin).

**Satır sonu notu:** Onaylı SHA-256, Windows çalışma kopyasındaki **CRLF** baytlarına aittir (9.012 bayt). Git blob'u
LF'dir (8.759 bayt, SHA-256 `c1b6888d…9b364`); iki kopya CRLF→LF dönüşümü dışında bayt-eşit ve JSON olarak aynıdır.
LF çalışma kopyasından paket üretilmek istenirse `build-package.js` hash uyuşmazlığıyla **reddeder** (güvenli taraf);
canlıya giden ZIP'teki manifest onaylı baytları taşır ve loader onu aynı sabitle doğrular.

## Güvenlik modeli

- Doğrudan web çağrısı: `ABSPATH` yoksa `exit` (çıktı yok). Manifest statik JSON'dur; gizli/kişisel veri taşımaz.
- Kamu isteği (`is_admin()` false): hiçbir kanca kaydedilmez, hiçbir şey okunmaz/yazılmaz.
- Yalnız yönetim panelinde, `manage_options` yetkisiyle, **Araçlar → Yönlendirme deposu kurulumu** ekranı.
- Kendiliğinden çalışmaz; yalnız nonce korumalı POST `admin-post.php` eylemleri (oturumsuz `nopriv` eylemi yok).
- Import kapıları (sırayla; ilk hata işlemi durdurur, depoya **hiçbir şey** yazılmaz):
  POST → yetki → nonce → Core sınıfları/yöntemleri ve seçenek adı → önceki başarılı import yok → depo boş
  (seçenek yok veya boş dizi) → manifest var, ≤256 KB → SHA-256 sabit değerle `hash_equals` → JSON nesne →
  `schema_version=1.0.0`, `record_type=redirect_rules` → `rules` boş olmayan liste → 29 toplam / 4 etkin →
  `validate_set()` geçerli → her etkin kural `verified` ve hedefi WordPress'te var (`target_exists()`) →
  depo hâlâ boş → durum kaydı `importing` yazılır ve geri okunur → `save_rules()` → geri okuma: 29/4 ve
  `digest()` eşit → durum `imported`. Geri okuma başarısızsa depo başlangıç durumuna döndürülür, durum kaydı silinir.
- Durum kaydı `mavibelge_redirect_bootstrap_state` (autoload kapalı): durum, kural özeti, manifest SHA-256,
  başlangıç durumu (`absent`/`empty_array`), sayılar, loader sürümü, UTC zaman. Kullanıcı/kişisel veri yok.
- Rollback: yetki + ayrı nonce; yalnız durum kaydı `importing/imported` ve depo özeti import anındakiyle aynıysa
  depo başlangıç durumuna döner (seçenek silinir veya boş diziye döner). Başlangıçta dolu depo, loader'ın
  oluşturmadığı depo veya import sonrası değiştirilmiş depo rollback **edilmez**.
- Mesajlar sabit metinlerdir; istek verisi, kişisel veri, sunucu yolu yansıtılmaz.
- Tema, eklenti sürümü, formlar, SMTP, içerik, `.htaccess`, kalıcı bağlantı ayarı değişmez; dosya yazma/silme yok;
  loader kendini silmez.

## Yerel doğrulama

```bash
php  wordpress-site/tools/ops/redirect-bootstrap/tests/run.php          # PHP 7.3.33 (Docker php:7.3)
node wordpress-site/tools/ops/redirect-bootstrap/test-package.js
node wordpress-site/tools/ops/redirect-bootstrap/build-package.js --write
```

Canlı uygulama adımları ve kayıt: `raporlar/canli-gecis-kayit-sablonu.md` §6.5.
