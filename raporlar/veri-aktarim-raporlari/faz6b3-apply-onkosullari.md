# Faz 6B3 — Apply Önkoşulları (Yalnız Önkoşul/Entegrasyon Notu — Kod YOK)

Bu belge, Faz 6B2'nin bıraktığı yerden Faz 6B3'ün (apply/batch/audit/rollback) NE ZAMAN başlayabileceğini listeler. **Bu görevde Faz 6B3 kodu yazılmadı; bu belge yalnız bir kapı listesidir.**

## Faz 6B3 başlamadan önce tamamlanması gerekenler

1. **PHP 7.3 gerçek parser/test çalışması.** `tests/run.php`'deki hiçbir test (Faz 6B1 + Faz 6B2 dahil, yüzlerce assertion) bugüne kadar gerçek bir PHP yorumlayıcısıyla çalıştırılmadı — yalnız yazılıp kaynak olarak gözden geçirildi. Apply kodu, henüz `php -l` bile geçmemiş bir karar motorunun üzerine YAZILAMAZ.
2. **Gerçek WordPress staging ortamında repository sorgularının doğrulanması.** `MaviBelge_Core_Import_WordPress_Target_Repository`'nin `get_term_by()`/`get_posts()`/`get_post_meta()`/`wp_get_post_terms()` çağrılarının GERÇEK sonuçları hiç gözlemlenmedi.
3. **Dry-run raporunun kurum tarafından incelenmesi.** `wp mavibelge import catalog --dry-run` çıktısı, gerçek `cms-yeni.mavibelge.com.tr` (henüz oluşturulmadı) staging'inde çalıştırılıp sonuçları kullanıcı tarafından gözden geçirilmeden apply YAZILMAZ.
4. **Conflict/duplicate/blocked/invalid kayıtlarının çözülmesi veya açıkça ertelenmesi.** Faz 6A'nın bilinen bloklayıcıları (20 revizyonsuz MYK kodu — Güvenlik/Şema/Sözleşme Kapanışı'nda şema düzeyinde kapatıldı; sektör icon/image şeması — aynı turda kapatıldı) artık şema düzeyinde çözülmüş olsa da, GERÇEK WordPress verisiyle karşılaşacak conflict/duplicate senaryoları henüz hiç gözlemlenmedi.
5. **Veritabanı + `wp-content/uploads` + yapılandırma yedeğinin doğrulanması.** AGENTS.md §5 "Koşulsuz yasak" kuralı burada da geçerlidir — iki doğrulanmış yedek kopya olmadan hiçbir yazma denemesi yapılmaz.
6. **Rollback yönteminin staging'de denenmesi.** `_mb_import_source_key`/`_mb_last_applied_hash` üç-hash tasarımı (bkz. `faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md`) henüz gerçek bir apply+rollback döngüsünde hiç denenmedi.
7. **Kullanıcının ayrıca açık apply onayı.** AGENTS.md §6 "Commit, push, tag, release ve deploy yalnız kullanıcının açık onayıyla yapılır" ilkesiyle aynı çizgide — Faz 6B3'ün kendisi de ayrı, açık bir kullanıcı onayı gerektirir.

## Faz 6B2'nin Faz 6B3'e bıraktığı temiz zemin

- `MaviBelge_Core_Import_Dry_Run_Service` zaten loader+repository+planlayıcıyı TEK bir noktada birleştiriyor — Faz 6B3'ün "apply service"i muhtemelen bu servisin sonucunu GİRDİ olarak alıp yalnız `applicable`/`decision=create|update` olan kayıtları işleyen AYRI, yeni bir sınıf olacaktır; mevcut planlayıcı/servis/repository DEĞİŞTİRİLMEDEN yeniden kullanılabilir.
- `MaviBelge_Core_Import_Target_Repository` arayüzü şimdiden yalnız OKUMA metotları taşıyor — Faz 6B3'ün yazma ihtiyacı (`create_sector()`/`update_qualification()` vb.) YENİ bir arayüz/metot seti gerektirecek, mevcut arayüz genişletilecek değil DEĞİŞTİRİLMEYECEK (geriye dönük uyumluluk).
- `_mb_import_source_key`/`_mb_last_applied_hash` şeması (post-meta VE term-meta) zaten Faz 6A'da register edilmiş durumda — Faz 6B3 yalnız bu alanlara İLK KEZ değer YAZACAK olan taraf olacak.

## Faz 6B3 Apply Kodlaması (24 Eylül 2026) — kapanış kaydı

Apply, batch/checkpoint, audit ve rollback kodlandı. Bağlayıcı belge: [`faz6b3-apply-mimarisi.md`](./faz6b3-apply-mimarisi.md).

| Madde | Durum |
|---|---|
| 1. PHP 7.3 gerçek parser/test | Yerel olarak karşılandı; sayılar mimari belgesinde ve teslim raporunda. Kod değiştikçe tekrarlanmalı. |
| 2. Repository sorgularının gerçek WordPress'te doğrulanması | Yerel izole WordPress 6.9.9'da yapıldı (dry-run senaryoları + apply/rollback döngüsü). Gerçek staging **yapılmadı**. |
| 3. Kurum incelemesi | Açık |
| 4. Conflict/duplicate/blocked kararları | Açık. Gerçek manifest planı bu kayıtlar ve görselli sektörler nedeniyle apply kapısında reddediliyor (doğrulandı). |
| 5. İki doğrulanmış DB + uploads + yapılandırma yedeği | Açık |
| 6. Rollback provası | **Yerel silinebilir fixture veritabanında yapıldı** (apply → rollback → canlı içerik başlangıçla birebir). Gerçek staging'de, gerçek yedekten geri dönüş provası **yapılmadı**. |
| 7. Kullanıcının açık apply onayı | Açık. Kod düzeyinde `MAVIBELGE_IMPORT_APPLY_ENABLED` + yetki + `--confirm=<plan_digest>` şartı var; bunlar kullanıcı onayının yerine geçmez. |

**Açık madde:** görselli sektör görsel eşleme stratejisi. Staging ve canlı apply prosedürü bu turda çalıştırılmadı.

## Faz 6B3 Önkoşul — By-Mid Kapsam Kapanışı (24 Eylül 2026)

Codex, aşağıdaki Son Kabul Düzeltmesi'nin iki bloklayıcısının kapandığını doğruladı. Kalan bulgu (global by-mid filtresinin MaviBelge dışı sanitizer'ları iki kez çalıştırması) kapsamlandırma ile kapatıldı; ayrıntı `faz6b3-yazma-guvenligi-sozlesmesi.md` §0.1 madde 7. Güncel sayılar: PHP 7.3.33 lint 113/113, `tests/run.php` 739/739 ×2 byte-eşit, yazma güvenliği 55/55, `_mb_level` 42/42, 52/52 senaryo, 37/37 admin, dry-run A=B=C, statik sözleşme 286/286. Codex bağımsız incelemesine yeniden sunuldu; Claude kabul vermedi. Apply kodu hâlâ yazılmadı; görsel eşleme stratejisi ve operasyonel önkoşullar açık.

## Faz 6B3 Önkoşul — Son Kabul Düzeltmesi (23 Eylül 2026)

Codex bağımsız incelemesi aşağıdaki önkoşul turunda iki açık buldu: `update_metadata_by_mid()` yolu `REJECTED_META_WRITE` işaretini veritabanına yazabiliyordu; `build_rollback_record()` hash'leri alanlardan doğrulamıyor, `rollback_allowed()` eksik kaydı kabul ediyordu. Önceki turda normal add/update testlerinin geçtiği kaydı korunur; **önkoşul kabulü bu iki sorun nedeniyle yeniden açıldı**. Düzeltme: post/term by-mid ret filtreleri ve tek kapalı `validate_rollback_record()` (ayrıntı: `faz6b3-yazma-guvenligi-sozlesmesi.md` §0.1 ve §11). Yeniden çalıştırılan kanıtlar: PHP 7.3.33 lint 113/113, `tests/run.php` 729/729 ×2 byte-eşit, izole WordPress 6.9.9 yazma güvenliği 45/45, `_mb_level` 42/42, 52/52 senaryo, 37/37 admin, dry-run A=B=C, statik sözleşme 281/281. Codex bağımsız incelemesine yeniden sunuldu; Claude kabul vermedi. Aşağıdaki tablodaki 707/707 sayısı önceki turun tarihsel kaydıdır; güncel sayı 729/729'dur. Apply kodu hâlâ yazılmadı.

## Faz 6B3 Önkoşul ve Yazma Güvenliği Kapanışı (23 Eylül 2026) — durum güncellemesi

Bu tur Faz 6B3 apply'ı **başlatmadı**. Ayrıntılı sözleşme: [`faz6b3-yazma-guvenligi-sozlesmesi.md`](./faz6b3-yazma-guvenligi-sozlesmesi.md).

| Madde | Durum |
|---|---|
| 1. PHP 7.3 gerçek parser/test | **Yerel olarak karşılandı**: PHP 7.3.33 lint 113/113, `tests/run.php` 707/707 (iki kez, byte-eşit). Kod değiştikçe tekrarlanmalı. |
| 2. WordPress'te repository sorgularının doğrulanması | **Yerel izole WordPress 6.9.9'da yapıldı**: marker keşfi ve doğal anahtar preflight'ı 52/52 senaryo. Gerçek staging **yapılmadı**. |
| 3. Kurum incelemesi | Açık |
| 4. Conflict/duplicate/blocked kayıtların çözümü | Açık. Doğal anahtar preflight'ı bu kayıtları artık görünür kılıyor; çözüm kurum/insan kararıdır. |
| 5. DB + uploads + yapılandırma yedeği | Açık |
| 6. Rollback provası | Açık. Rollback kayıt şekli ve "körlemesine ezme yok" kuralı saf olarak tanımlandı; gerçek prova yapılmadı. |
| 7. Kullanıcının açık apply onayı | Açık |

**Bu turda kapatılan tasarım önkoşulları** (Faz 6B2 runtime raporu §15.3 ve "Faz 6B2 Runtime Engellerinin Kapatılması" §13):

- **ÇÖZÜLDÜ:** kuruşun 100 katına çıkması. `money_kurus` tipiyle kapatıldı; mevcut admin kaydetme hatası da giderildi.
- **ÇÖZÜLDÜ:** yalnız dizi olarak bozulmuş marker'ın `create` adayı görünmesi. Doğal anahtar preflight'ı ile `corrupt_marker` conflict.
- **ÇÖZÜLDÜ:** yanlış önekli marker'ın sessizce `''` olması. Tek kanonik sınıflandırıcı; sanitize yazmayı reddediyor.
- **ÇÖZÜLDÜ:** geçersiz girişin önceki geçerli değeri ezmesi. `REJECTED_META_WRITE` ve ret filtresi.

**Yeni açık madde:** görselli sektörler "create + çözülmüş görsel" olamaz (görsel var olan terimin meta'sından çözülüyor). Faz 6B3 öncesinde ayrı bir görsel eşleme stratejisine karar verilmeli. Bkz. sözleşme §13.5.
