# Faz 6A — 19 Kodsuz Ücret Raporu

> Makine-okunabilir kaynak: `wordpress-site/data/mapping/unmatched-fees.manifest.json`. Bu belge onun insan-okunur özetidir.

## Politika

`tanitim-site/assets/data/fees.js`'teki `qualificationCode` alanı bu 19 kayıtta **kaynağın kendisinde boş bırakılmıştır** ("PDF'de MYK kodu yazmıyor, uydurma kod eklenmedi" — `fees.js` satır 7). Faz 6A bu 19 kayıt için:

- **Hiçbir MYK kodu uydurmadı.**
- **Hiçbir ad/kod benzerliğine dayalı (fuzzy) eşleştirme denemedi.**
- Her kaydın `qualification_source_key` alanını **kasıtlı olarak `null`** bıraktı.
- Kaydı listeden **düşürmedi** — 103 ana ücret kaydının hepsi manifestte var, yalnız bu 19'unun ilişkisi yok.

Bu 19 kayıt, gerçek WordPress'e aktarıldığında (Faz 6B) `_mb_qualification_id = 0` ile (mevcut alan sözleşmesinin "eşleşme yoksa 0 bırakılabilir" kuralı) yazılmalıdır — asla uydurma bir ID ile değil.

## 19 kayıt

| Kaynak sırası (fees.js, 0-tabanlı) | Meslek adı | Seviye | Sektör |
|---:|---|---:|---|
| 16 | Sıcak Su Kazanı Operatörü | 3 | enerji |
| 17 | Kızgın Yağ Operatörü | 4 | enerji |
| 18 | Buhar Kazanı Operatörü | 4 | enerji |
| 19 | Köprülü Vinç Operatörü | 3 | lojistik |
| 69 | Metal Kumlama İşçisi | 3 | metal |
| 70 | Metal Yüzey Kaplamacı | 4 | metal |
| 83 | Kule Vinç Operatörü | 3 | is-makineleri |
| 84 | Ekskavatör Operatörü | 3 | is-makineleri |
| 86 | Kazıcı Yükleyici (Beko Loder) Operatörü | 3 | is-makineleri |
| 87 | Beton Santral Operatörü | 3 | is-makineleri |
| 88 | Beton Transmikser Operatörü | 3 | is-makineleri |
| 89 | Beton Pompa Operatörü | 3 | is-makineleri |
| 96 | Dokuma Operatörü | 3 | tekstil |
| 97 | Dokuma Operatörü | 4 | tekstil |
| 98 | Tahar Operatörü | 3 | tekstil |
| 99 | Çözgü Operatörü | 3 | tekstil |
| 100 | Örme Operatörü | 4 | tekstil |
| 101 | Çözgülü Örme Operatörü | 3 | tekstil |
| 102 | Atkılı Örme Operatörü | 3 | tekstil |

19 kayıt — beklenen sayıyla birebir eşleşiyor (`verify-manifest.js`, `count_fees_without_code` kontrolü).

## Dağılım gözlemi (yalnız bilgi amaçlı, karar değil)

Tekstil sektöründe 7, İş Makineleri sektöründe 6 kayıt yoğunlaşıyor — bu, PDF kaynağın (`2026 Yeni Meslekler Ücret Tarifesi`) bu iki grup için MYK kodu sütununu hiç doldurmamış olabileceğini düşündürür, ama bu bir **varsayımdır, doğrulanmış bir sonuç değildir**; kurumdan gerçek kod istenmedikçe kod eklenmeyecektir.
