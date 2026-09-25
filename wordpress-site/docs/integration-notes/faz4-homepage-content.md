# Faz 4 Ana Sayfa İçerik Entegrasyon Notu

> Bu bir "ortak dosya" entegrasyon notu değildir (hiçbir ortak dosyaya dokunma ihtiyacı doğmadı — bkz. `docs/template-architecture.md` §2). Bu belge, brief §5'in açıkça istediği "ana sayfa bölüm verisinin gelecekte nereden yönetileceği henüz `mavibelge-core` tarafından tanımlanmadıysa... gereken alanları, varsayılan metni, sorumlu agentı ve hedef fazı öneri olarak yaz" maddesinin karşılığıdır. `mavibelge-core`'a dokunulmadı; bunlar yalnız öneridir.

## 1. Kapanan bulgu — sayaç bandındaki 4. istatistik

Faz 4 görev promptu §5, ana sayfa sayaç bandı için 4 değer sayar: **11** (Yıllık Tecrübe), **100.000+** (Belge Teslimi), **14** (Yetkilendirilmiş Sektör), **81** (İlde Hizmet).

Önceki tur, dondurulmuş `tanitim-site/index.html`'in yalnız 3 `.impact-stat` bloğu içerdiğini (11 / 100.000+ / 14) doğru tespit etmiş, ancak bunu "81 uydurma/doğrulanmamış sayı, eklenemez" olarak yanlış yorumlamıştı. **Bu yorum hatalıydı ve bu turda düzeltildi.** "81" bir geliştiricinin tahmini değildir — Faz 4 görev promptunun kendisinde (§5) ve kullanıcının önceki açık kararında yazılı, onaylı içerik kararıdır. AGENTS.md §1'deki yetki sırası açıktır: "kullanıcının o görevdeki açık talimatları" `tanitim-site/`'in dondurulmuş statik referansından **daha yüksek** önceliklidir; dondurulmuş üç bloklu anlık görüntü sonraki açık görev talimatını geçersiz kılmaz.

**Karar:** `template-parts/home/stats.php` artık 4 değerin tamamını gösteriyor: 11 / 100.000+ / 14 / 81. "81 — İlde Hizmet" bu turda eklendi (bkz. `raporlar/proje-durumu.md`, Faz 4 Nihai Kabul Düzeltmesi kaydı).

## 2. Sayaç değerlerinin geleceği — yönetilebilir ayar önerisi

4 sayaç değeri (`11`, `100.000+`, `14`, `81`) şu an `template-parts/home/stats.php` içinde sabit metin. Öneri:

- **Alan adayları:** `mb_stat_years_experience` (integer), `mb_stat_documents_delivered` (integer + görünen "+" son eki bayrağı), `mb_stat_sector_count` (integer — muhtemelen zaten `wp_count_terms('mb_sektor')` ile **gerçek zamanlı türetilebilir**, ayrı bir alana gerek kalmayabilir), `mb_stat_province_count` (integer — "81 İlde Hizmet").
- **Önerilen konum:** Yeni bir CPT değil — tek bir `option` grubu (`admin/class-settings.php`'nin zaten kurduğu "Ücret Kaydı → Aktif Tarife Dönemi" ekranına benzer bir "Ana Sayfa İstatistikleri" ayar ekranı).
- **Sorumlu agent:** WordPress çekirdek/eklenti agentı (görev kartı 02) — ayar ekranı + option kaydı; Tema agentı yalnız `get_option()` ile okur.
- **Hedef faz:** Faz 6 (veri aktarımı) veya Faz 10 (performans/ayarlar) — ilk gerçek içerik importu sırasında en doğal an.

## 3. Meslek arama paneli — kapsam netliği

Ana sayfa hero'sundaki "Mesleğini Bul" paneli (`template-parts/home/hero.php`) bu fazda WordPress'in **gerçek** `get_search_form()` çıktısına bağlanmıştır — statik referanstaki JS autosuggest/öneri kutusu (`assets/js/search.js`, `.search-suggestions[aria-live]`) **taşınmadı**. Aranan terim gerçek arama sonuç sayfasına (`search.php`) gider; bu, "sahte sonuç üretme" yasağını (brief §5) en doğrudan karşılayan yoldur. Meslek-özel (yalnız `mb_yeterlilik` içinde, MYK koduna göre de arayan) gerçek arama deneyimi Faz 5'in kapsamıdır.

## 4. Referans/haber bölümleri

Her ikisi de gerçek, sınırlı (`posts_per_page` ile sınırlı) `WP_Query` kullanıyor; eklenti pasifken veya veri yokken dürüst boş durum gösteriyor. Slider/otomatik oynatma bilinçli olarak eklenmedi (brief §7.3, `docs/template-architecture.md` §9).
