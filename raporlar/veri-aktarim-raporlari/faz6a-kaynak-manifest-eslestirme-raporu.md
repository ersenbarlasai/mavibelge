# Faz 6A — Kaynak → Manifest Eşleştirme Raporu

> Makine-okunabilir kaynak: `wordpress-site/data/mapping/mapping.manifest.json` (200 satır — 14 sektör + 83 yeterlilik + 103 ücret). Bu belge onun insan-okunur özetidir.

## Sayım tablosu

| Kaynak | Kaynak kayıt | Manifest kaydı | Fark |
|---|---:|---:|---|
| `sectors.js` | 14 | 14 | 0 |
| `qualifications.js` | 83 | 83 | 0 |
| `fees.js` (ana kayıt) | 103 | 103 | 0 |
| `fees.js` (fiyat seçeneği toplamı) | 145 | 145 | 0 |
| `single` fiyatlandırma | 87 | 87 | 0 |
| `unit`/`package`/`multiple` (çok-fiyatlı) | 16 | 16 | 0 |
| Çok-fiyatlı seçenek toplamı | 58 | 58 | 0 |
| Dolu `qualificationCode` | 84 | 84 (hepsi tam kod eşleşmesiyle bağlandı) | 0 |
| Boş `qualificationCode` | 19 | 19 (`qualification_source_key: null`) | 0 |

Her satır `wordpress-site/tools/import/verify-manifest.js` tarafından **gerçekten yeniden hesaplanıp** doğrulandı (32/32 kontrol + ayrı bir finalizasyon kapısı geçti — bkz. `faz6a-dogrulama-sonuc-raporu.md`, güncel ve tek doğru kaynak; önceki turların 30/30, 31/31 rakamları o belgede açıklanan nedenle artık geçersizdir); hiçbiri elle yazılmadı. Her satırın alanları artık yalnız sayımla değil, içerik manifestlerinden BAĞIMSIZ türetilen tam izdüşümle birebir karşılaştırılıyor. **Bu turda (Tam Kapsam ve Sayaç Kapanışı) ayrıca:** kayıt sayısı artık gerçek kaynak satır sayısına VE doğrulanmış sabit sayıya (14/83/103) bağlanıyor — "son kaydı tutarlı biçimde sil" tarzı bir karşı-örnek de artık reddediliyor; 8 ücret sayacının tamamı bağımsız yeniden hesaplanıyor (bkz. aynı belge "Bu turda kapatılan").

## Her kaynak kayıt tam olarak bir manifest kaydına izlenebilir

`mapping.manifest.json`'daki her satır şu alanları taşır:

```text
type              "sector" | "qualification" | "fee"
source_index      kaynak dizideki 0-tabanlı sıra
source_key        kararlı, benzersiz anahtar (bkz. faz6a-manifest-sozlesmesi.md §2)
natural_key       kaynağın kendi doğal anahtarı (slug / MYK kodu / ad+seviye+sektör)
resolution        "mapped" | "unmatched"  (yalnız ücret satırlarında "unmatched" görülebilir)
target_source_key ücret→yeterlilik bağlantısı (84 satırda dolu, 19 satırda null); sektör/yeterlilik satırlarında sektöre işaret eder veya null
validation_status "valid" (bu turda tüm 200 satır valid — herhangi biri invalid olsaydı build-manifest.js hiçbir dosya yazmadan hata ile dururdu)
```

200 satır = 14 (sektör) + 83 (yeterlilik) + 103 (ücret) — `verify-manifest.js`'in `mapping_row_count` kontrolü bunu doğrular.

## Sektör referans bütünlüğü

- 83 yeterlilik kaydının **her birinin** `sector_slug`'ı gerçek 14 sektörden birine karşılık geliyor (`verify-manifest.js: qualification_sector_refs`).
- 103 ücret kaydının **her birinin** `sector_slug`'ı gerçek 14 sektörden birine karşılık geliyor (`verify-manifest.js: fee_sector_refs`).
- Hiçbir sektöre ait olmayan/geçersiz bir sektör slug'ı **yok** — bu, `fees.js`'teki sektör dağılımının 14 sektöre tam toplandığı doğrudan sayımla da doğrulandı (3+8+7+12+13+7+4+13+3+7+7+3+3+13 = 103).

## Kaynak veride hedef modelde karşılığı olmayan alan var mı?

Hayır — `unmapped_fields` boştur. `sectors.js`'in `icon`/`image` alanları manifestte **taşınıyor** (atılmadı) ama gerçek WordPress hedefi henüz yok; bu ayrı bir **bloklayıcı sözleşme açığı** olarak `faz6a-manifest-sozlesmesi.md` §7'de kayda geçirildi — "sessizce atılan alan" değildir, görünür ve belgeli bir açık kalan karardır.

## Kararlı anahtar çakışma testi

`build-manifest.js`, 103 ücret kaydının `fee:<sector_slug>:<level>:<slugify(name)>` anahtarının hepsini bir `Object` üzerinde tekilleştirerek üretir; bir çakışma olsaydı **sessiz bir sonek üretmek yerine iş anında hata ile dururdu** (kod: `build-manifest.js`, `buildFeeManifest()`, "kararlı source_key çakışması" hata mesajı). Bu turda **sıfır çakışma** tespit edildi — `verify-manifest.js`'in `unique_fee_source_keys` kontrolü bunu bağımsızca yeniden doğruladı.
