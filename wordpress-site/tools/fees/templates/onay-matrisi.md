# 2026 Ücret Tarifesi — Kurumsal Onay Matrisi ({{TOTAL}} kayıt)

> **Bu belge onay DEĞİLDİR.** `kurum kararı` sütunu bilinçli olarak `BEKLIYOR` bırakılmıştır; hiçbir kayıt yayına/aktife alınmamıştır.
> Makine-okunabilir eşi: `onay-matrisi.csv` (UTF-8 BOM, virgül ayraçlı, Türkçe karakterler korunmuştur).
> Üretim: `node wordpress-site/tools/fees/build-approval-matrix.js --write` (deterministik; yeniden üretim byte-eşittir).

## Doğrulama sonucu (kayıt başına tek karar)

{{VERDICT_LINES}}

Karar kuralı: fiyat/birim/sayfa/seviye/belge basım/bağlantı tutarsızlığı → `MISMATCH`; PDF sayfası okunamadı → `UNREADABLE`; fiyat verisi PDF ile tutarlı ama kurum kararı gerektiren bir nokta varsa (bağlantısız kayıt, PDF'de KDV ifadesi yok, PDF adı ile manifest adı farkı, aynı yeterliliğe iki ücret bağlı) → `DECISION_REQUIRED`; hiçbiri yoksa `VERIFIED`. `VERIFIED`, **yayın onayı anlamına gelmez** — yalnız kaynakla tutarlılığı gösterir.

## Kaynaklar (salt okunur; SHA-256)

- `2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf` — `{{YENI_SHA}}`
- `2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf` — `{{GUZ_SHA}}`

## Yöntem ve sınırlar

- PDF'ler sayfa görüntüsüne çevrilip **gözle** okundu (poppler `pdftoppm`); PDF metin katmanı (font kodlaması bozuk) ve OCR karşılaştırmaya esas alınmadı. Yeni Meslekler PDF'inin 4 sayfasındaki tutarlar ayrıca metin katmanındaki rakamlarla bağımsız olarak çapraz kontrol edildi (`verify-pdf-text-layer.js`); Güzellik PDF'inin fiyatları metin katmanında bulunmadığı için yalnız görsel okuma geçerlidir.
- **MYK kodu PDF'lerde yoktur.** Kod, yalnız `qualifications.manifest.json` ile bağımsız olarak doğrulanır (kod, seviye, sektör, ad tutarlılığı). Hiçbir kod üretilmedi/tahmin edilmedi.
- **Sektör:** PDF'deki grup başlıkları tarife düzenidir (MYK sektörü değildir). Grup ile manifest sektörü farklıysa yalnız bilgi notu düşülür (`[INFO]`); karar etkilenmez.
- **Tarife dönemi (2026):** PDF sayfalarında yıl basılı değildir; kanıt yalnız dosya adıdır.
- **KDV:** Yeni Meslekler PDF'i her sayfada "%20 KDV dahildir; belge basım ücreti (1500 TL) dahil değildir" der. Güzellik PDF'inde KDV ifadesi **hiçbir sayfada yoktur** (yalnız 1. sayfada "1500 ₺ MYK belge ücreti ödemelidir" notu) — bu yüzden 7 Güzellik kaydı `DECISION_REQUIRED`.

## Kayıtlar

{{TABLE}}
