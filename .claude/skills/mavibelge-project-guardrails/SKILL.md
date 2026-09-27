---
name: mavibelge-project-guardrails
description: Use at the start of ANY task in the Mavi Belge repository (WordPress theme `mavibelge`, plugin `mavibelge-core`, tools, reports) and before any commit, push, packaging or runtime test. Binding project rules - PHP 7.3, frozen static reference, data/secret safety, file ownership, environment bans, explicit git staging and quality gates.
---

# Mavi Belge — Proje Korumaları (bağlayıcı)

Ayrıntılı kural kaynağı `AGENTS.md`'dir; bu skill onun uygulanabilir özetidir. Çelişkide `AGENTS.md` ve kullanıcının o görevdeki açık talimatı geçerlidir.

## 1. Başlarken
- Önce `raporlar/proje-durumu.md`, sonra `AGENTS.md` okunur. Mimari otorite: `raporlar/wordpress-ana-uygulama-plani.md`.
- Sistem WordPress + özel `mavibelge` teması + özel `mavibelge-core` eklentisidir. Laravel/Filament/Blade/sayfa oluşturucu uygulanmaz.
- Güncel sürüm dosyadan okunur: `wp-content/themes/mavibelge/style.css` `Version:` ve `mavibelge-core.php` başlığı. Rapor özetine körü körüne güvenilmez.

## 2. Kod kısıtları
- Üretim PHP 7.3: `??=`, `?->`, `match`, `fn`, typed properties, `str_contains`/`str_starts_with`/`str_ends_with`, named args, union types, `mixed`, nullsafe, enum, readonly, spread-string-keys YOK. Kontrol: `run-all-gates.sh` "PHP 7.4+/8.x yasak sözdizimi" + Docker `php -l` (7.3.33).
- İçerik/ücret/MYK/banka/adres/telefon verisi tema PHP'sine gömülmez; WordPress yönetimli kaynaktan (editör, meta, `mavibelge-core` servisleri) gelir.
- Dahili bağlantılar `mavibelge_url()` / WordPress permalink API ile; `/index.php/` koda gömülmez, `.html` bağlantısı üretilmez.
- `assets/dist/*` elle yamanmaz: `node tools/build/build-theme-assets.js` (+ `--check`).

## 3. Dokunulmaz alanlar
- `tanitim-site/**` salt okunur (dondurulmuş görsel referans). `tmp/**`, tarihsel raporlar (`AGENTS.md` §7), `CLAUDE_*.md`, `STITCH_*.md`, logo/görsel/PDF/tarihsel ZIP, `.gitignore`, `.claude/settings.local.json` değişmez.
- `tanitim-site/yeni-mavibelge-v1.zip` ve `CLAUDE_*` prompt dosyaları stage/commit edilmez.

## 4. Gizli bilgi ve kişisel veri
- `.env`, parola, anahtar, FTP/SSH/DirectAdmin, `wp-config.php` gizli değerleri aranmaz, yazılmaz, çıktılanmaz. Görülürse yalnız "gizli bilgi bulundu" notu.
- Test verisi sentetiktir (`.example`, "TEST"); gerçek kişi verisi, gerçek e-posta gönderimi yoktur (`pre_wp_mail` kısa devresi / fixture sayaç).

## 5. Ortamlar
- Canlı `mavibelge.com.tr`, staging `cms-yeni`, DNS/SSL/mail/DirectAdmin/FTP/SSH: DOKUNULMAZ. Deploy, tag, release yok (kullanıcı açıkça istemedikçe).
- Gerçek işlev testi yalnız yerel Docker (`mbruntime6b2-*`) ve silinebilir `mbfx_` fixture klonunda; ana `wp_` DB farkı 0 olmalı.

## 6. Sahiplik
- Ortak dosyalar (`functions.php`, `style.css` başlık bloğu, `mavibelge-core.php`, `README.md`, `AGENTS.md`, `raporlar/proje-durumu.md`, `.htaccess`, `robots.txt`) yalnız ana orkestratör tarafından birleştirilir; alt ajan entegrasyon notu verir.
- Bir ajan başka ajanın sahip olduğu dosyayı değiştirmez (`raporlar/wordpress-agent-mimarisi.md` §3).

## 7. Git
- Doğrudan `main`'e commit/push yok; özellik dalı kullanılır.
- YASAK: `git add .`, `git add -A`, joker ile geniş stage, `git reset --hard`, `git checkout --`, `git clean`, force push, otomatik stash.
- Her commit: `git status` → dosyaları TEK TEK stage → `git diff --cached --stat` ve içerik incelemesi (gizli bilgi/kullanıcı dosyası/ZIP yok) → ilgili testler → commit.
- Test başarısızsa commit ve push YAPILMAZ.

## 8. Kalite kapıları
- Tam kapı: `bash wordpress-site/tools/qa/run-all-gates.sh --runtime <çıktı-dizini>` → "TÜM KAPILAR GEÇTİ".
- Atlanan kapı "geçti" yazılmaz: ATLANDI + neden + bağımlılık + risk.
- Raporda yalnız gerçek sayaçlar; tahmini sonuç yazılmaz.
