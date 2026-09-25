# Faz 3 Bağımlılıkları — Sonraki Fazlara Bırakılanlar

> Faz 3 (Temel Tema) sırasında karşılaşılan, bu görevin kapsamı dışında kalan ama bir sonraki fazda çözülmesi gereken maddeler.

| Madde | Neden Faz 3'te değil | Hedef faz / sahip agent |
|---|---|---|
| Footer iletişim bilgisi (adres/telefon/e-posta) sabit metin olarak kodlandı | `mavibelge-core` henüz lokasyon/iletişim ayarı CPT/servisi sunmuyor (Faz 2 kapsamı buna girmedi) | Faz 7, WordPress çekirdek/eklenti agentı (servis) + Tema agentı (bağlama) |
| Footer lokasyon kartları (4 adet) sabit metin | Aynı neden | Faz 7 |
| Sosyal medya bağlantıları statik `references.js`/`index.html`'den taşındı; Twitter/X hesabının güncelliği/marka adı kurum tarafından teyit edilmedi | Kurum onayı bekleyen kalem (`proje-durumu.md` "Twitter/X bağlantısı ve marka adı") | Kurum onayı → İçerik/Veri agentı |
| Ana menü (`primary`) ve footer menüleri (`footer_quick`, `footer_kurumsal`) henüz WordPress admin'de gerçek bir menüye atanmadı; şu an `inc/menu-fallback.php`'deki planlanan-yol fallback'i devrede | Yerel WordPress runtime bu fazda hâlâ kurulmadı (Faz 1 kısıtı devam ediyor) | Menü, gerçek sayfalar oluşturulduktan sonra (Faz 4) admin'de atanmalı |
| Fallback menü linkleri (`/meslekler/`, `/sinav-ucretleri/` vb.) henüz var olmayan sayfalara işaret ediyor | 41 sayfanın WordPress karşılıkları bu görevin kapsamı dışında | Faz 4 — Tema agentı |
| MYK/TÜRKAK logolarının header'daki gerçek görünürlüğü (deforme olmama, okunabilir kod) yalnız statik ölçümle doğrulandı | Gerçek WordPress/tarayıcı runtime yok | Faz 3 bağımsız kabul incelemesi — QA agentı |
| `package.json`'daki `build:css`/`build:js` script yer tutucuları gerçek bir derleme adımına bağlanmadı; `dist/*` dosyaları elle (`cat` ile) üretildi | `package.json` bu görevin izin verilen dosya listesinde değil | İleride bir DevOps/Tema görevi |
| Gutenberg blok stilleri yalnız temel tipografi eşleşmesiyle sınırlı (`editor.css`); özel blok/blok stili yok | Görev kapsamı dışı (§5) | Faz 4+ gerektiğinde |
| `robots`/canonical/JSON-LD, breadcrumb şeması, sitemap kuralları | Görev kapsamı dışı (§9) | Faz 9 — SEO/AIO agentı |
| Gerçek form gönderimi, arama/filtre işlevi | Görev kapsamı dışı (§11) | Faz 5 (arama/filtre), Faz 8 (form) |
| Veri importu / seed (83 yeterlilik, 103 ücret, 14 sektör, 6 haber, 12 referans) | Görev kapsamı dışı (§11) | Faz 6 — Veri/içerik aktarım agentı |
| Ekran görüntüsü tabanlı görsel karşılaştırma (`qa/baseline`) | Gerçek WordPress runtime ve tarayıcı otomasyon ortamı bu oturumda kullanılamadı (bkz. teslim raporu §10) | Faz 3 bağımsız kabul incelemesi — QA agentı |
