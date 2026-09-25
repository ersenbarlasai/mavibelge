# Faz 6A — Doğrulama Sonuç Raporu

> Makine-okunabilir kaynak: `wordpress-site/data/mapping/validation-summary.json` (her `verify-manifest.js` çalıştırmasında yeniden üretilir). Aşağıdaki çıktı, **Faz 6A Tam Kapsam ve Sayaç Kapanışı** görevinde **gerçekten çalıştırılan** `node wordpress-site/tools/import/verify-manifest.js` komutunun tam, değiştirilmemiş çıktısıdır — sayı Doğrulayıcı Bütünlük Kapanışı'ndakiyle aynı (**32/32**) çünkü bu turun yeni kontrolleri `record()`-düzeyinde ayrı bir satır değil, mevcut `shared_validator` tek kontrolünün İÇİNDE (kaynak kardinalitesi, 8 sayaç, notes, zarf-source, fail-closed `sources`) genişletildi — bkz. aşağıdaki "Bu turda kapatılan" bölümü. **Önceki turların 30/30 ve 31/31 rakamları hâlâ geçersizdir.**

```text
Faz 6A doğrulama sonucu: 32/32 kontrol geçti.
PASS  Kaynaktan manifest yeniden hesaplanabildi (extract-source.js güvenlik kapıları dahil)
PASS  İki ardışık bellek-içi üretim byte-eşit (disk I/O olmadan)
PASS  Diskteki manifest dosyaları, kaynaktan yeniden hesaplanan sonuçla byte-eşit
PASS  Paylaşılan doğrulayıcı (şema+zarf+çapraz-alan+kaynak karşılaştırması) hatasız
PASS  sectors.js SHA-256 — c28688d352aa5c77cd2082581cd9111c3a89f3766353d3cdd5090c42cc5e53e1
PASS  qualifications.js SHA-256 — 649deee25873e91242649861691dd320afb92d7d154d05437f716676c594bd4a
PASS  fees.js SHA-256 — ccb170bf838246971f68d6054aea2b279fbf2cd78e1fa3e72dc30b7e848f4bdb
PASS  Sektör sayısı = 14 — 14
PASS  Yeterlilik sayısı = 83 — 83
PASS  Ücret ana kayıt sayısı = 103 — 103
PASS  Toplam fiyat seçeneği = 145 — 145
PASS  "single" kayıt sayısı = 87 — 87
PASS  Çok-fiyatlı kayıt sayısı = 16 — 16
PASS  Çok-fiyatlı seçenek toplamı = 58 — 58
PASS  Dolu qualificationCode sayısı = 84 — 84
PASS  Boş qualificationCode sayısı = 19 — 19
PASS  unmatched-fees.manifest.json kayıt sayısı = 19 — 19
PASS  Sektör slug benzersizliği
PASS  MYK kodu benzersizliği
PASS  Ücret source_key benzersizliği
PASS  Her yeterlilik kaydının sector_slug'ı gerçek sektör manifestinde var
PASS  Her ücret kaydının sector_slug'ı gerçek sektör manifestinde var
PASS  Bağlantılı (dolu kodlu) ücretlerin hepsi gerçek bir yeterliliğe işaret ediyor — 84
PASS  qualification_source_key=null olan kayıt sayısı = 19 — 19
PASS  sectors: source_index 0..n-1 aralığında atlama/tekrar yok
PASS  qualifications: source_index 0..n-1 aralığında atlama/tekrar yok
PASS  fees: source_index 0..n-1 aralığında atlama/tekrar yok
PASS  Fiyat seçeneklerinin alan/sınır bütünlüğü
PASS  Tüm para dönüşümleri tam sayı ve /100 ile geri çevrilebilir (kayıpsız kuruş)
PASS  Eşleştirme dosyası satır sayısı = sektör+yeterlilik+ücret toplamı — 200 / 200
PASS  Manifestte mutlak yol / gizli bilgi / çalışma-zamanına-bağlı alan yok
PASS  data/content ve data/mapping altında beklenen 5 dosya dışında "*.manifest.json" yok
FINALIZATION PASS  validation-summary.json şema + 4 sayısal invariant (total_checks/passed/failed) doğrulandı — bu, yukarıdaki N/N sayısına dahil DEĞİLDİR, ayrı bir yazma-öncesi kapıdır.
```

(Tam `source_index` dizileri okunabilirlik için kısaltılmıştır; `wordpress-site/data/mapping/validation-summary.json`'da tam haliyle durur.)

## Bu turda kapatılan asıl sorun: "tam alan-alan karşılaştırması" iddiası gerçek DEĞİLDİ

Bağımsız incelemenin bellek-içi bozulma deneyleri, önceki `lib/validate-manifest-set.js`'in altı somut değişikliği **sıfır hata ile kabul ettiğini** kanıtladı: sektör `slug`, ücret `pricing_type`, fiyat seçeneği `sort_order`, kayıt-içi `source.sha256`, mapping `natural_key`, unmatched `profession_name` — hiçbiri gerçekten kontrol edilmiyordu; şema yalnız TİP/BİÇİM doğruluyordu, DEĞERİN kaynakla veya türetim formülüyle eşleştiğini değil.

`lib/validate-manifest-set.js` bu turda köklü biçimde genişletildi:

- **Sektör:** `slug`/`source_key` formülü artık kaynakla karşılaştırılıyor; `source_index`/`slug`/`source_key` benzersizlik + 0..n-1 tam kapsam kontrolü eklendi.
- **Yeterlilik:** `source_key` formülü, `planned_record_status` sabiti, `matches_legacy_revision_required_format`'ın koddan yeniden hesaplanması eklendi; benzersizlik + tam kapsam kontrolü eklendi.
- **Ücret:** `pricing_type` artık kaynakla karşılaştırılıyor; `source_key` gerçek `fee:<sector>:<level>:<slugify(ad)>` formülüyle (paylaşılan `lib/slug.js`) doğrulanıyor; boş/dolu `qualification_code` ↔ `qualification_source_key` tutarlılığı; `price_options`'ın her elemanının `sort_order`'ı gerçek dizin sırasıyla karşılaştırılıyor (eksik/fazla/yeniden sıralanmış seçenek artık yakalanıyor); `source_attachment_id`/`planned_*` sabit alanları; benzersizlik + tam kapsam.
- **Mapping:** artık yalnız satır SAYISI değil, içerik manifestlerinden BAĞIMSIZ olarak yeniden türetilen TAM satır listesiyle (sıra dahil) birebir karşılaştırılıyor.
- **Unmatched:** artık `fees.manifest.json`'dan BAĞIMSIZ türetilen tam ve birebir izdüşümle karşılaştırılıyor.
- **Kayıt-içi `source` meta verisi:** her sektör/yeterlilik/ücret kaydının kendi `source.file`/`source.sha256`'ı hem gerçek çıkarım meta verisiyle hem dosyanın kendi zarf `source` alanıyla çapraz kontrol ediliyor.
- **Dosya kümesi:** `files` nesnesi artık tam olarak 5 beklenen anahtarı içermeli; eksik/fazla anahtar, bozuk/null manifest değeri, dizi olmayan `files` — hepsi kontrollü hata döndürüyor, hiçbiri `TypeError` fırlatmıyor.
- **Şema pattern'leri sıkılaştırıldı:** `qualification:` source_key'leri artık yalnız gerçek MYK kod biçimini kabul ediyor (eski `.+` gevşek deseni değil); `fee.schema.json`'daki `qualification_source_key` ve `unmatched-fee.schema.json`'daki `source_key` de aynı şekilde sıkılaştırıldı. **Bu, yalnız doğrulayıcı katılığıdır — gerçek 103 ücret/83 yeterlilik kaydının hiçbiri bu nedenle değişmedi, `schema_version` bu yüzden artırılmadı** (mevcut veri zaten yeni, sıkı desene uyuyor).

Altı karşı-örneğin tümü, düzeltmeden sonra doğrudan yeniden üretilip **reddedildiği doğrulandı** (bkz. teslim raporu §3).

## Faz 6A Tam Kapsam ve Sayaç Kapanışı — bu turda kapatılan sorun: "benzersizlik + tam kapsam" iddiası da gerçek DEĞİLDİ

Bağımsız incelemenin yeni karşı-örneği: `fees.manifest.json`'daki SON kaydı (`source_index: 102`), bağımlı mapping/unmatched satırlarını ve 8 `counts` değerini tutarlı biçimde birlikte silmek, doğrulayıcıdan **0 hata** ile geçiyordu. Neden: `isCompleteZeroBasedIndexSet()` yalnız kalan `source_index`'lerin `0..n-1` aralığını kapsadığını kontrol ediyordu — kayıt SAYISININ gerçek kaynak satır sayısına eşit olduğunu hiç doğrulamıyordu; son kayıt eksilince kalan indeksler yine "tam kapsıyor" görünüyordu.

Kapatılan altı ek sorun:

1. **Kaynak kardinalitesi artık bağlayıcı** — her içerik türü için `records.length === sources.<type>.data.length` VE `records.length === EXPECTED.<type>` (14/83/103, `lib/expected-counts.js`'den — build ile TEK paylaşılan kaynak) zorunlu.
2. **8 ücret sayacının TAMAMI** artık `feeFile.records`'tan bağımsız yeniden hesaplanıp `counts` ile birebir karşılaştırılıyor (yalnız `counts.total===count` değil).
3. **`sources` girdisi artık fail-closed** — yok/null/dizi, `.data` dizi değil, `repoRelativePath`/`sha256` eksik/bozuk gibi durumlarda `TypeError` yerine kontrollü, tek bir açık hata.
4. **Zarf `source` alanı** artık doğrudan gerçek çıkarım meta verisiyle (`envelope.source.file/sha256 === sources.<type>.repoRelativePath/sha256`) karşılaştırılıyor.
5. **`notes` metni bağımsız yeniden türetiliyor** — `lib/manifest-notes.js` (build VE validate'in paylaştığı TEK kaynak) ile sektör/yeterlilik notes'u keyfi değiştirilirse reddediliyor.
6. **Fee/unmatched source-key şema pattern'leri** PHP'deki gerçek slug kuralıyla (`[a-z0-9]+(-[a-z0-9]+)*`, önceki gevşek `[a-z0-9-]+` değil) birebir eşitlendi.
7. **"Verifier karışık set testi" iddiası artık gerçek** — `verify-manifest.js`'in disk-karşılaştırma kodu `compareFreshToDisk()` olarak ayrıştırıldı; yeni test geçici bir dizine 4 güncel + 1 eski dosya yazıp GERÇEKTEN reddedildiğini kanıtlıyor (önceki test yalnız `validateManifestSet()` içinde bariz bir `count` uyuşmazlığı yaratıyordu, adı yanıltıcıydı — şimdi dürüstçe yeniden adlandırıldı).

Yeni karşı-örnek (son ücret+bağımlı satırlar+sayaçlar tutarlı silme) doğrudan yeniden üretilip **reddedildiği doğrulandı**.

## Ayrıca çalıştırılan, ilgili kontroller

```text
node wordpress-site/tools/import/test-extract-safety.js       → 54/54 güvenlik testi geçti (değişmedi)
node wordpress-site/tools/import/test-manifest-validation.js  → 85/85 negatif/regresyon testi geçti (18-madde + 11 yeni madde matrisi + gerçek karışık-set disk fixture testi dahil)
node wordpress-site/tools/import/build-manifest.js             → başarılı, hiçbir hata üretmedi
node wordpress-site/tools/verify-source-counts.js               → 9/9 sayı doğrulandı (regresyon)
node wordpress-site/tools/test-theme-fee-text.js                → 3/3
```

## Çalıştırılamayan kontroller

Bu görevde de PHP kodu DEĞİŞMEDİ — yalnız Node tarafı (doğrulayıcı, şemalar) genişletildi. `php` ikili dosyası bu ortamda hâlâ yok; önceki turlarda yazılan PHP testleri bu turda da çalıştırılmadı, yalnız korundu.
