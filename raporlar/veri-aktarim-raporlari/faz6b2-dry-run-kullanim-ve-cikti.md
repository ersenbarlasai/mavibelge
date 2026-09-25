# Faz 6B2 — Dry-Run Kullanımı ve Çıktı Biçimi

Bu belge WP-CLI komutunu ve admin ekranını nasıl çalıştıracağını ve çıktıyı nasıl okuyacağını anlatır. **İkisi de salt okunurdur — hiçbir kayıt yazılmaz.**

## WP-CLI

```
wp mavibelge import catalog --dry-run
wp mavibelge import catalog --dry-run --format=json
```

- `--dry-run` bu fazdaki TEK çalışma biçimidir; verilmese de davranış aynıdır.
- `--apply`/`--write`/`--commit`/`--force` veya eşdeğer BİR SEÇENEK YOKTUR.
- `errors` (loader veya plan düzeyinde) doluysa komut **non-zero exit** üretir (`WP_CLI::halt(1)`).
- Bilinmeyen bir `--format` değeri (yalnız `table`/`json` kabul edilir) `WP_CLI::error()` ile fail-closed reddedilir.
- WP-CLI sınıfları yoksa eklenti fatal VERMEZ — komut yalnız gerçek WP-CLI ortamında yüklenir.
- **Düzeltme ve Kabul (21 Eylül 2026):** önceki turda tablo çıktısı `class_exists('WP_CLI\Utils')` kontrolü YÜZÜNDEN hiç satır göstermiyordu (`WP_CLI\Utils` bir sınıf değil, fonksiyon içeren bir namespace — kontrol her zaman `false` dönüyordu). Artık doğru `function_exists('WP_CLI\Utils\format_items')` kullanılıyor; fonksiyon bulunamazsa satırlar sessizce atlanmaz, okunabilir bir metin-tablosu fallback'i yazdırılır.

Çıktıda: özet sayaçları (`create`/`update`/`unchanged`/`conflict`/`blocked`/`invalid`), `structurally_valid`/`applicable`, kayıt başına `source_key`/`type`/`decision`/`reason`/`target_id`/`changed_fields`(yalnız alan ADLARI)/`unresolved_dependencies`/hash'ler, ve repository tanıları (`ambiguous_qualification_dependency` vb.). Tablo biçimi ayrıca `changed_fields`/`unresolved_dependencies` kolonlarını görüntüler. **Tam alan DEĞERLERİ, kişisel veri, sunucu yolu veya kimlik bilgisi hiçbir zaman yazdırılmaz.**

## Admin ekranı

Araçlar > İçe Aktarım Dry-Run (`manage_options` yetkisi gerekir).

- Sayfa yalnız TEK eylem sunar: "Salt Okunur Dry-Run Çalıştır" — apply/import/write düğmesi YOKTUR.
- Tetikleme POST + nonce iledir; GET isteği (sayfa yeniden yüklendiğinde) hiçbir ağır sorgu ÇALIŞTIRMAZ, yalnız formu gösterir.
- **Düzeltme ve Kabul (21 Eylül 2026):** POST isteğinde nonce eksik/array/geçersizse artık `wp_die()` ile açık, fail-closed reddedilir — önceki turda bu durum sessizce normal GET formuna düşüyordu (hiçbir hata mesajı yoktu). Yetki kontrolü (`manage_options`) nonce kontrolünden ÖNCE çalışır. `mb_paged` artık yalnız katı `^[1-9][0-9]*$` deseni (pozitif onluk tam sayı) kabul eder — önceki `is_numeric()` kontrolü bilimsel gösterimi (`"1e2"`) ve ondalığı (`"1.5"`) da geçerli sayıyordu; artık bunlar güvenli varsayılana (sayfa 1) düşer.
- Sonuç hiçbir option/transient/post/meta/log dosyasına YAZILMAZ — bu yüzden sayfalama da bir GET bağlantısı OLAMAZ (GET bir sonraki istekte sonucu kaybederdi); her sayfa numarası kendi nonce'lı POST mini-formuyla (dry-run'ı yeniden çalıştırarak, ama planlayıcıya HER ZAMAN TAM 14/83/103 kaydı vererek) seçilir — sayfalama yalnız GÖRÜNTÜ katmanındadır.
- Tüm çıktı `esc_html()`/`esc_attr()` ile kaçırılır; karar/tip değerleri allowlist üzerinden CSS sınıfına dönüştürülür (ham değer sınıf adına basılmaz).

## `applicable` alanının anlamı

`summary.applicable === true`, yalnız plan hesabı tutarlıysa VE hiçbir `conflict*`/`blocked_dependency`/`invalid` kaydı YOKSA (yalnız `create`/`update`/`unchanged`) doğrudur. **Bu, gelecekteki Faz 6B3 için bir ADAYLIK işaretidir — otomatik uygulama YETKİSİ DEĞİLDİR.** Bu fazda hiçbir kod `applicable=true` sonucunu okuyup otomatik bir yazma işlemi tetiklemez; böyle bir kod bu görevde hiç yazılmadı.
