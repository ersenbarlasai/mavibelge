# 2026 Ücret Tarifesi — Doğrulama Özeti ve Mutabakat

> Bu rapor **kurumsal onay değildir**. Canlı/staging'de hiçbir işlem yapılmamıştır; manifestler ve kaynak dosyalar değiştirilmemiştir.
> İlgili dosyalar (bu dizin): `onay-matrisi.md/.csv`, `baglantisiz-19-kayit.md/.csv`, `pdf-medya-esleme-plani.md`, `staging-aktivasyon-plani.md`.

## 1. {{TOTAL}} kaydın doğrulama özeti

{{VERDICT_LINES}}

{{UNREADABLE_LINE}}
{{MISMATCH_LINE}}
- `DECISION_REQUIRED` gerekçeleri (bir kayıt birden çok gerekçe taşıyabilir): bağlantısız {{UNLINKED}} kayıt; PDF'de KDV ifadesi olmayan {{VAT_UNVERIFIED}} Güzellik kaydı; PDF adı ile manifest adı farklı {{NAME_DIFF}} kayıt; aynı yeterliliğe iki ücret kaydı bağlı 2 kayıt (#25 ve #86, kod `12UY0061-3/04`).
- Yalnız bilgi notu (`[INFO]`) taşıyan kayıtlar (karar değişmedi): PDF grubu ≠ manifest sektörü {{SECTOR_DIFF}} kayıt (ör. Liman/İş makinesi kayıtları PDF'de "Ulaştırma&Lojistik" grubunda, manifestte `is-makineleri`; Metal Kesim/Levha kayıtları PDF'de "Metal" grubunda, manifestte `makine`).

## 2. Mutabakat ({{RECONCILE_OK}})

{{RECONCILE_TABLE}}

- Toplam seçenek: {{OPTIONS}}; tek seçenekli kayıt: {{SINGLE_OPT}}; çok seçenekli kayıt: {{MULTI_OPT}} (bu kayıtların seçenek toplamı {{MULTI_OPT_TOTAL}}).
- `pricing_type` dağılımı: {{TYPES}}. (Manifestin `pricingSingle`=87 / `pricingMulti`=16 sayaçları `pricing_type`'a göredir: `single` ↔ diğer üç tür; "tek seçenek" ile aynı şey değildir.)
- Bağlı {{LINKED}} kayıt {{DISTINCT_QUAL}} farklı yeterliliğe bağlıdır (bir yeterliliğe iki ücret bağlıdır — kod `12UY0061-3/04`).

## 3. PDF bazında sayfa ve kayıt kapsamı

{{PAGE_TABLE}}

- Güzellik PDF'i 2 sayfadır: sayfa 1 tanıtım/duyuru sayfasıdır (fiyat yok; yalnız "1500 ₺ MYK belge ücreti" notu), 7 ücret kaydının tamamı sayfa 2'dedir.
- Yeni Meslekler PDF'i 4 sayfadır; her sayfada en az bir kayıt vardır ve her sayfada KDV/belge basım notu bulunur.
- Manifestteki her `source_page` gerçek PDF sayfasıyla karşılaştırıldı (gözlemle birebir).

## 4. Doğrulama yöntemi

- Sayfalar görüntüye çevrilip gözle okundu; tutarlar Yeni Meslekler PDF'inde ayrıca metin katmanı rakamlarıyla sayfa bazında çoklu küme olarak çapraz kontrol edildi (4/4 sayfa eşit). Güzellik PDF'inde çapraz kontrol uygulanamadı (fiyatlar metin katmanında yok) — yalnız görsel okuma.
- Metin katmanı çapraz kontrolünün bağımlılığı poppler `pdftotext`tir (`tools/fees/verify-pdf-text-layer.js`; ikili `MB_PDFTOTEXT` ile verilebilir). Bulunamazsa araç "EKSİK BAĞIMLILIK" yazar ve 3 ile çıkar; `run-all-gates.sh` bunu BAŞARISIZ sayar (sessiz geçiş yok).
- Okunabilirlik: her gözlem `legibility` (`readable`/`unreadable`) taşır; okunamayan gözlemde fiyat/birim/ad/seviye yazılamaz ve kayıt `UNREADABLE` olur (öncelik MISMATCH > UNREADABLE > DECISION_REQUIRED > VERIFIED). Bu turda 103 gözlemin tamamı `readable`.
- Ad benzerliğiyle eşleme yoktur: her PDF gözlemi manifest kaydına açık indeksle bağlıdır; eksik/fazla/çift kayıt üreticiyi durdurur (fail-closed).
- Kaynak PDF SHA-256: Yeni Meslekler `{{YENI_SHA}}`, Güzellik `{{GUZ_SHA}}`. Değişirse üretici durur.

## 5. Kayıt bazlı fark ve açıklama listesi

{{DIFF_LINES}}

## 6. Açık noktalar (kurum kararı gerekir)

1. **Güzellik PDF'inde KDV ifadesi yok** — `vat_included=true` yalnız `fees.js` yorumuna dayanır (yorum "her iki kaynakta da" der; Güzellik PDF'inde bu ifade görülmedi). Yazılı teyit veya PDF revizyonu gerekir.
2. **Bağlantısız {{UNLINKED}} kayıt** — bkz. `baglantisiz-19-kayit.md`.
3. **PDF adı ≠ manifest adı** kayıtları (ör. "Metal Levha Tezgâh İşçisi" ↔ "Metal Levha İşleme Tezgâh İşçisi", "Isı Yalıtımcı" ↔ "Isı Yalıtımcısı", "OPERAÖTÜRÜ" yazım hatası): kamuya hangi adın yazılacağı kararı.
4. **Aynı yeterliliğe iki ücret** (`12UY0061-3/04`: MHC/Sahil/Gemi Vinci ve İş Makineleri grubu Mobil Vinç): yeterlilik detayında ikisi de görünür; kurum hangisinin/nasıl gösterileceğini seçmeli.
5. **Tarife dönemi 2026** yalnız dosya adından çıkarılır; PDF sayfalarında yıl yoktur — kurum "2026 dönemi" teyidi vermeli.
6. **Belge basım ücreti (1.500 TL)**: fiyatlara dahil değildir (Yeni Meslekler PDF'i açık); Güzellik PDF'inde "belge ücreti" ifadesi başarı koşuluyla geçer — iki PDF'in aynı ücreti anlattığı kurumca teyit edilmeli.
