# Bütünleşik QA Raporu (Faz 11) — yerel, tarayıcısız

> Tek komut: `bash wordpress-site/tools/qa/run-all-gates.sh --runtime <çıktı-dizini>` (Docker: PHP 7.3.33 + WordPress 6.9.9 + MariaDB 10.11, **silinebilir `mbfx_` fixture DB**).
> Bu rapor **kendi kendine nihai kabul vermez**; bağımsız (Codex) incelemeye sunulur.

## Çalıştırılan kapılar (gerçek sonuçlar)
| Kapı | Sonuç |
|---|---|
| PHP lint (eklenti+tema) | 167 dosya, çıktı yok |
| Eklenti birim testleri (`tests/run.php`) | **1117/1117**, iki koşu byte-eşit |
| Faz 6A extract güvenliği / manifest negatif / verifier | 71 / 85 / 32 + finalizasyon; kaynak sayıları 9/9 (14/83/103/145/87/16/58/84/19) |
| İçerik manifesti (6 haber, 12 referans) | doğrulama 11/11, test 56/56 |
| Statik sözleşme (6B2/6B3/7/8) | 359/359 |
| Tema statik/JS | içerik+form 260/260; katalog, başlık, walker, taksonomi, odak tuzağı 8, aktif sayfa 6, ücret metni 3 |
| Runtime apply/rollback/reapply + audit hata enjeksiyonu + içerik aşaması | 30/30 + 30/30 + 40/40 (+ CLI kapıları) |
| HTTP: içerik/form | 55/55 |
| HTTP: SEO + yönlendirme + staging kapısı | 38/38 |
| HTTP: Faz 10 (sertleştirme/başlıklar/cache) + runtime | 44/44 + 66/66 |
| **Render QA** (41 statik sayfanın 41'i, boş durum, eklenti pasif) | **388/388** |
| Paket testleri | 25/25 (deterministik, allowlist, gizli bilgi, PHP 7.3, zip bütünlüğü, kaynak==dist) |
| Renk kontrastı | 13 çift geçti + 1 uyarı (gray-500/gray-50 = 4.41:1; bilinen kullanımlar gray-700'e alındı) |
| Ana `wp_` DB/tablo/dosya/uploads farkı | **0** (her runtime betiğinde), `debug.log` 0 satır |

## 41/41 eşleme
Her statik sayfa için: doğru durum kodu, tek `<h1>`, `lang=tr-TR`, viewport, atla bağlantısı, tek `#main`, dolu `<title>`, `href="#"`/`javascript:` yok, `alt`'sız `<img>` yok, `target=_blank` → `noopener`, PHP uyarı metni yok.
Ek: boş durumlar (doküman/referans/SSS/ücret), kapalı iletişim formu + statik iletişim yedeği, sonuçsuz arama, hatalı sektör (404), bozuk filtre parametreleri, **eklenti pasifken** 7 sayfa render'ı (fatal yok, header/footer/H1 korunur).

## Bu turda bulunup düzeltilen kod kaynaklı kusurlar
`<html lang>` site diline bağlıydı → tema `tr-TR`e sabitledi; rate-limit sayaç dizgesi → fail-closed 429 (WordPress option dizgesi); SSS cevabındaki `<script>` içeriği kalıyordu → temizleniyor;
düşük kontrast (yardımcı metin/açık zemin) → gray-700; tanımsız CSS token'ları; taslak haberde GMT tarihi sıfırken SEO tarihi boştu → yerel saat; fingerprint'te WordPress'in kendi yönettiği alanlar (guid, taslak tarihi) sahte drift üretiyordu.

## ÇALIŞTIRILAMAYAN / açık kalite kapıları (dürüst liste)
- **Gerçek tarayıcı yok** (Chrome uzantısı bağlı değil): mobil menü/dropdown/Escape/Tab davranışının canlı sınaması, 375/390/768/1024/1440 px görünüm ve **yatay taşma**, odak görünürlüğü, reduced-motion, forced-colors, yazdırma görünümü, konsol hataları, görsel referans ekran görüntüsü karşılaştırması — yalnız **kod/CSS düzeyinde** var (`prefers-reduced-motion`, `forced-colors`, `@media print`, `:focus-visible` mevcut; odak tuzağı mantığı 8 test).
- **aXe / Lighthouse** kurulu değil; sistem genelinde kurulum yapılmadı. Yerine: statik erişilebilirlik sözleşmeleri (etiket/for, aria, tek H1, alt, rel) ve token kontrast hesabı.
- Gerçek e-posta teslimi, gerçek staging/canlı, Apache şablon sözdizimi, ana-DB `run-regression.sh` (önceki turdan dış `expect.json` gerekir).
- Gerçek katalog/haber/referans manifestleri **hiçbir veritabanına** apply edilmedi.
- Mutasyon: form güvenliğinde 9/10 kapı bozulunca testler yakaladı; "başlık CRLF denetimi" kapatılınca yakalanmadı (kurucu öncesi doğrulama aynı riski zaten kapatıyor — savunma derinliği); Faz 6B3 ve Faz 10 için ayrı mutasyonlar (drift, atomik finalizer, cache/audit/REST/oran sınırı) yakalandı.
