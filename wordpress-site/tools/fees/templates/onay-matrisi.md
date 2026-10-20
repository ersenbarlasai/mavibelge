# 2026 Ücret Tarifesi — Kurumsal Onay Matrisi ({{TOTAL}} kayıt)

> **Kurum kararı (2026-10-19):** tüm ücretlerde fiyatlar son kullanıcıya gösterilen **%20 KDV dahil** tutarlardır (yeniden KDV eklenmez); **1.500 TL MYK belge basım ücreti** fiyatlara dahil değildir. `kurum kararı` sütunu bu kuralı ve kayıt bazlı yayın kararını (UYGUN / BEKLIYOR) gösterir. Ad farkı ve aynı yeterliliğe iki ücret kararları **çözülmüş sayılmaz**; bu kayıtlar bekletilir. Bu belge hiçbir kaydı yayına/aktife ALMAZ: etkinleştirme, eklentinin ayrı ve açık onaylı ücret yayını işlemidir.
> Kayıt bazlı yayın kararı makine-okunabilir olarak `wordpress-site/data/content/fee-publication.manifest.json` dosyasındadır (aynı üreticiden).
> Makine-okunabilir eşi: `onay-matrisi.csv` (UTF-8 BOM, virgül ayraçlı, Türkçe karakterler korunmuştur).
> Üretim: `node wordpress-site/tools/fees/build-approval-matrix.js --write` (deterministik; yeniden üretim byte-eşittir).

## Doğrulama sonucu (kayıt başına tek karar)

{{VERDICT_LINES}}

Karar kuralı: fiyat/birim/sayfa/seviye/belge basım/bağlantı tutarsızlığı → `MISMATCH`; PDF sayfası okunamadı → `UNREADABLE`; fiyat verisi PDF ile tutarlı ama kurum kararı gerektiren bir nokta varsa (bağlantısız kayıt — MYK kodu kararı, PDF adı ile manifest adı farkı, aynı yeterliliğe iki ücret bağlı) → `DECISION_REQUIRED`; hiçbiri yoksa `VERIFIED`. `VERIFIED`, **yayın onayı anlamına gelmez** — yalnız kaynakla tutarlılığı gösterir.

## Kaynaklar (salt okunur; SHA-256)

- `2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf` — `{{YENI_SHA}}`
- `2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf` — `{{GUZ_SHA}}`

## Yöntem ve sınırlar

- PDF'ler sayfa görüntüsüne çevrilip **gözle** okundu (poppler `pdftoppm`); PDF metin katmanı (font kodlaması bozuk) ve OCR karşılaştırmaya esas alınmadı. Yeni Meslekler PDF'inin 4 sayfasındaki tutarlar ayrıca metin katmanındaki rakamlarla bağımsız olarak çapraz kontrol edildi (`verify-pdf-text-layer.js`); Güzellik PDF'inin fiyatları metin katmanında bulunmadığı için yalnız görsel okuma geçerlidir.
- **MYK kodu PDF'lerde yoktur.** Kod, yalnız `qualifications.manifest.json` ile bağımsız olarak doğrulanır (kod, seviye, sektör, ad tutarlılığı). Hiçbir kod üretilmedi/tahmin edilmedi.
- **Sektör:** PDF'deki grup başlıkları tarife düzenidir (MYK sektörü değildir). Grup ile manifest sektörü farklıysa yalnız bilgi notu düşülür (`[INFO]`); karar etkilenmez.
- **Tarife dönemi (2026):** PDF sayfalarında yıl basılı değildir; kanıt yalnız dosya adıdır.
- **KDV:** Yeni Meslekler PDF'i her sayfada "%20 KDV dahildir; belge basım ücreti (1500 TL) dahil değildir" der. Güzellik PDF'inde KDV ifadesi **hiçbir sayfada yoktur** (yalnız 1. sayfada "1500 ₺ MYK belge ücreti ödemelidir" notu). Bu belirsizlik 2026-10-19 kurum kararıyla çözüldü: tüm ücretlerde %20 KDV dahil, 1.500 TL belge basım ücreti hariç; Güzellik kayıtlarında bu artık yalnız bilgi notudur (`[INFO]`). Manifest bu kurala aykırı olsaydı kayıt `MISMATCH` olurdu.
- **Yayın kararı:** `MISMATCH`/`UNREADABLE`, ad farkı veya aynı yeterliliğe birden çok ücret bağlı olması → bekletilir (`hold`). Bağlantısız ama fiyatı tutarlı kayıt listelenir (bağlantısız gösterim; MYK kodu kararı açık kalır).

## Kayıtlar

{{TABLE}}
