# 2026 Ücret Tarifesi — Doğrulama Özeti ve Mutabakat

> Bu rapor **kurumsal onay değildir**. Canlı/staging'de hiçbir işlem yapılmamıştır; manifestler ve kaynak dosyalar değiştirilmemiştir.
> İlgili dosyalar (bu dizin): `onay-matrisi.md/.csv`, `baglantisiz-19-kayit.md/.csv`, `pdf-medya-esleme-plani.md`, `staging-aktivasyon-plani.md`.

## 1. 103 kaydın doğrulama özeti

- **VERIFIED**: 72
- **MISMATCH**: 0
- **UNREADABLE**: 0
- **DECISION_REQUIRED**: 31

- `UNREADABLE`: okunamayan sayfa/kayıt yok (6 PDF sayfasının tamamı okundu).
- `MISMATCH`: PDF ile manifest arasında **fiyat, birim, sayfa, seviye, belge basım ücreti veya bağlantı** farkı bulunmadı.
- `DECISION_REQUIRED` gerekçeleri (bir kayıt birden çok gerekçe taşıyabilir): bağlantısız 19 kayıt; PDF'de KDV ifadesi olmayan 7 Güzellik kaydı; PDF adı ile manifest adı farklı 5 kayıt; aynı yeterliliğe iki ücret kaydı bağlı 2 kayıt (#25 ve #86, kod `12UY0061-3/04`).
- Yalnız bilgi notu (`[INFO]`) taşıyan kayıtlar (karar değişmedi): PDF grubu ≠ manifest sektörü 10 kayıt (ör. Liman/İş makinesi kayıtları PDF'de "Ulaştırma&Lojistik" grubunda, manifestte `is-makineleri`; Metal Kesim/Levha kayıtları PDF'de "Metal" grubunda, manifestte `makine`).

## 2. Mutabakat (TÜM MUTABAKATLAR EŞİT)

| kontrol | beklenen | bulunan | sonuç |
|---|---|---|---|
| toplam kayıt | 103 | 103 | EŞİT |
| 96 Yeni Meslekler + 7 Güzellik | 96+7 | 96+7 | EŞİT |
| 84 bağlı + 19 bağlantısız | 84+19 | 84+19 | EŞİT |
| manifest counts.feesWithCode | 84 | 84 | EŞİT |
| manifest counts.feesWithoutCode | 19 | 19 | EŞİT |
| manifest counts.priceOptionsTotal | 145 | 145 | EŞİT |
| manifest counts.pricingSingle | 87 | 87 | EŞİT |
| manifest counts.pricingMulti | 16 | 16 | EŞİT |
| manifest counts.multiOptionsTotal | 58 | 58 | EŞİT |


- Toplam seçenek: 145; tek seçenekli kayıt: 87; çok seçenekli kayıt: 16 (bu kayıtların seçenek toplamı 58).
- `pricing_type` dağılımı: multiple=2, package=1, single=87, unit=13. (Manifestin `pricingSingle`=87 / `pricingMulti`=16 sayaçları `pricing_type`'a göredir: `single` ↔ diğer üç tür; "tek seçenek" ile aynı şey değildir.)
- Bağlı 84 kayıt 83 farklı yeterliliğe bağlıdır (bir yeterliliğe iki ücret bağlıdır — kod `12UY0061-3/04`).

## 3. PDF bazında sayfa ve kayıt kapsamı

| PDF | sayfa | kayıt sayısı |
|---|---|---|
| 2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf | 2 | 7 |
| 2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf | 1 | 32 |
| 2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf | 2 | 21 |
| 2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf | 3 | 30 |
| 2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf | 4 | 13 |


- Güzellik PDF'i 2 sayfadır: sayfa 1 tanıtım/duyuru sayfasıdır (fiyat yok; yalnız "1500 ₺ MYK belge ücreti" notu), 7 ücret kaydının tamamı sayfa 2'dedir.
- Yeni Meslekler PDF'i 4 sayfadır; her sayfada en az bir kayıt vardır ve her sayfada KDV/belge basım notu bulunur.
- Manifestteki her `source_page` gerçek PDF sayfasıyla karşılaştırıldı (gözlemle birebir).

## 4. Doğrulama yöntemi

- Sayfalar görüntüye çevrilip gözle okundu; tutarlar Yeni Meslekler PDF'inde ayrıca metin katmanı rakamlarıyla sayfa bazında çoklu küme olarak çapraz kontrol edildi (4/4 sayfa eşit). Güzellik PDF'inde çapraz kontrol uygulanamadı (fiyatlar metin katmanında yok) — yalnız görsel okuma.
- Metin katmanı çapraz kontrolünün bağımlılığı poppler `pdftotext`tir (`tools/fees/verify-pdf-text-layer.js`; ikili `MB_PDFTOTEXT` ile verilebilir). Bulunamazsa araç "EKSİK BAĞIMLILIK" yazar ve 3 ile çıkar; `run-all-gates.sh` bunu BAŞARISIZ sayar (sessiz geçiş yok).
- Okunabilirlik: her gözlem `legibility` (`readable`/`unreadable`) taşır; okunamayan gözlemde fiyat/birim/ad/seviye yazılamaz ve kayıt `UNREADABLE` olur (öncelik MISMATCH > UNREADABLE > DECISION_REQUIRED > VERIFIED). Bu turda 103 gözlemin tamamı `readable`.
- Ad benzerliğiyle eşleme yoktur: her PDF gözlemi manifest kaydına açık indeksle bağlıdır; eksik/fazla/çift kayıt üreticiyi durdurur (fail-closed).
- Kaynak PDF SHA-256: Yeni Meslekler `87d1002bdc03edb8078b22c2d85b6e4fe0a62effa823c0b5028edbea7832627e`, Güzellik `e91fa707de42fb532230218b06cb33b5ebff1c578172e0111f3f3e9453b33e44`. Değişirse üretici durur.

## 5. Kayıt bazlı fark ve açıklama listesi

- **#1 fee:guzellik-sac-bakim:4:guzellik-uzmani** — DECISION_REQUIRED: [DECISION] KDV: manifest vat_included=true ama "2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf" hiçbir sayfasında KDV ifadesi yok (kaynak: yalnız fees.js başlık yorumu); kurumdan yazılı teyit gerekir
- **#2 fee:guzellik-sac-bakim:4:epilasyon-uzmani** — DECISION_REQUIRED: [DECISION] KDV: manifest vat_included=true ama "2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf" hiçbir sayfasında KDV ifadesi yok (kaynak: yalnız fees.js başlık yorumu); kurumdan yazılı teyit gerekir
- **#3 fee:guzellik-sac-bakim:4:kuafor** — DECISION_REQUIRED: [DECISION] KDV: manifest vat_included=true ama "2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf" hiçbir sayfasında KDV ifadesi yok (kaynak: yalnız fees.js başlık yorumu); kurumdan yazılı teyit gerekir
- **#4 fee:guzellik-sac-bakim:3:cilt-bakim-uygulayicisi** — DECISION_REQUIRED: [DECISION] KDV: manifest vat_included=true ama "2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf" hiçbir sayfasında KDV ifadesi yok (kaynak: yalnız fees.js başlık yorumu); kurumdan yazılı teyit gerekir
- **#5 fee:guzellik-sac-bakim:3:makyaj-uygulayicisi** — DECISION_REQUIRED: [DECISION] KDV: manifest vat_included=true ama "2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf" hiçbir sayfasında KDV ifadesi yok (kaynak: yalnız fees.js başlık yorumu); kurumdan yazılı teyit gerekir
- **#6 fee:guzellik-sac-bakim:3:protez-tirnak-uygulayicisi** — DECISION_REQUIRED: [DECISION] KDV: manifest vat_included=true ama "2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf" hiçbir sayfasında KDV ifadesi yok (kaynak: yalnız fees.js başlık yorumu); kurumdan yazılı teyit gerekir
- **#7 fee:guzellik-sac-bakim:3:kozmetik-urunler-tanitim-ve-uygulama-elemani** — DECISION_REQUIRED: [DECISION] KDV: manifest vat_included=true ama "2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf" hiçbir sayfasında KDV ifadesi yok (kaynak: yalnız fees.js başlık yorumu); kurumdan yazılı teyit gerekir
- **#17 fee:enerji:3:sicak-su-kazani-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#18 fee:enerji:4:kizgin-yag-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#19 fee:enerji:4:buhar-kazani-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#20 fee:lojistik:3:koprulu-vinc-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#25 fee:is-makineleri:3:mobil-vinc-operatoru-mhc-sahil-ve-gemi-vinci** — DECISION_REQUIRED: [INFO] sektör: PDF grubu "ULAŞTIRMA&LOJİSTİK GRUBU" (beklenen sektör: lojistik) ≠ manifest sektörü "is-makineleri"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir ;; [INFO] ücret adı yeterlilik adına parantez ayrımı ekler: "Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci)" ↔ yeterlilik "Mobil Vinç Operatörü" ;; [DECISION] aynı yeterliliğe (12UY0061-3/04) 2 ücret kaydı bağlı; yeterlilik detayında hangisinin gösterileceği kurum kararı gerektirir
- **#26 fee:is-makineleri:3:liman-rtg-operatoru** — VERIFIED: [INFO] sektör: PDF grubu "ULAŞTIRMA&LOJİSTİK GRUBU" (beklenen sektör: lojistik) ≠ manifest sektörü "is-makineleri"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir
- **#27 fee:is-makineleri:3:liman-forklift-operatoru** — VERIFIED: [INFO] sektör: PDF grubu "ULAŞTIRMA&LOJİSTİK GRUBU" (beklenen sektör: lojistik) ≠ manifest sektörü "is-makineleri"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir
- **#30 fee:is-makineleri:3:liman-saha-istif-makineleri-operatoru-crs-ve-ecs** — VERIFIED: [INFO] sektör: PDF grubu "ULAŞTIRMA&LOJİSTİK GRUBU" (beklenen sektör: lojistik) ≠ manifest sektörü "is-makineleri"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir ;; [INFO] ücret adı yeterlilik adına parantez ayrımı ekler: "Liman Saha İstif Makineleri Operatörü (CRS ve ECS)" ↔ yeterlilik "Liman Saha İstif Makineleri Operatörü"
- **#31 fee:is-makineleri:3:liman-ssg-operatoru** — VERIFIED: [INFO] sektör: PDF grubu "ULAŞTIRMA&LOJİSTİK GRUBU" (beklenen sektör: lojistik) ≠ manifest sektörü "is-makineleri"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir
- **#32 fee:is-makineleri:3:terminal-cekici-operatoru** — VERIFIED: [INFO] sektör: PDF grubu "ULAŞTIRMA&LOJİSTİK GRUBU" (beklenen sektör: lojistik) ≠ manifest sektörü "is-makineleri"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir
- **#66 fee:makine:3:metal-kesimci** — VERIFIED: [INFO] sektör: PDF grubu "METAL GRUBU" (beklenen sektör: metal) ≠ manifest sektörü "makine"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir
- **#67 fee:makine:4:metal-kesim-operatoru** — VERIFIED: [INFO] sektör: PDF grubu "METAL GRUBU" (beklenen sektör: metal) ≠ manifest sektörü "makine"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir
- **#68 fee:makine:3:metal-levha-isleme-tezg-h-iscisi** — DECISION_REQUIRED: [DECISION] ad: PDF "METAL LEVHA TEZGÂH İŞÇİSİ" ≠ manifest "Metal Levha İşleme Tezgâh İşçisi" (PDF kısaltma/yazım farkı; manifest adı MYK yeterlilik adına dayanır) ;; [INFO] sektör: PDF grubu "METAL GRUBU" (beklenen sektör: metal) ≠ manifest sektörü "makine"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir
- **#69 fee:makine:4:metal-levha-isleme-tezg-h-operatoru** — DECISION_REQUIRED: [DECISION] ad: PDF "METAL LEVHA İŞL. TEZGÂH OP." ≠ manifest "Metal Levha İşleme Tezgâh Operatörü" (PDF kısaltma/yazım farkı; manifest adı MYK yeterlilik adına dayanır) ;; [INFO] sektör: PDF grubu "METAL GRUBU" (beklenen sektör: metal) ≠ manifest sektörü "makine"; sektör MYK yeterlilik manifestinden gelir, PDF yalnız tarife düzenidir
- **#70 fee:metal:3:metal-kumlama-iscisi** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#71 fee:metal:4:metal-yuzey-kaplamaci** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#79 fee:insaat:3:isi-yalitimcisi** — DECISION_REQUIRED: [DECISION] ad: PDF "ISI YALITIMCI" ≠ manifest "Isı Yalıtımcısı" (PDF kısaltma/yazım farkı; manifest adı MYK yeterlilik adına dayanır)
- **#84 fee:is-makineleri:3:kule-vinc-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#85 fee:is-makineleri:3:ekskavator-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#86 fee:is-makineleri:3:mobil-vinc-operatoru-is-makineleri-grubu** — DECISION_REQUIRED: [DECISION] ad: PDF "MOBİL VİNÇ OPERATÖRÜ" ≠ manifest "Mobil Vinç Operatörü (İş Makineleri Grubu)" (PDF kısaltma/yazım farkı; manifest adı MYK yeterlilik adına dayanır) ;; [INFO] ücret adı yeterlilik adına parantez ayrımı ekler: "Mobil Vinç Operatörü (İş Makineleri Grubu)" ↔ yeterlilik "Mobil Vinç Operatörü" ;; [DECISION] aynı yeterliliğe (12UY0061-3/04) 2 ücret kaydı bağlı; yeterlilik detayında hangisinin gösterileceği kurum kararı gerektirir
- **#87 fee:is-makineleri:3:kazici-yukleyici-beko-loder-operatoru** — DECISION_REQUIRED: [DECISION] ad: PDF "KAZICI YÜKLEYİCİ (Beko Loder) OPERAÖTÜRÜ" ≠ manifest "Kazıcı Yükleyici (Beko Loder) Operatörü" (PDF kısaltma/yazım farkı; manifest adı MYK yeterlilik adına dayanır) ;; [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#88 fee:is-makineleri:3:beton-santral-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#89 fee:is-makineleri:3:beton-transmikser-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#90 fee:is-makineleri:3:beton-pompa-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#97 fee:tekstil:3:dokuma-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#98 fee:tekstil:4:dokuma-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#99 fee:tekstil:3:tahar-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#100 fee:tekstil:3:cozgu-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#101 fee:tekstil:4:orme-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#102 fee:tekstil:3:cozgulu-orme-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)
- **#103 fee:tekstil:3:atkili-orme-operatoru** — DECISION_REQUIRED: [DECISION] MYK kodu yok: PDF'lerde MYK kodu sütunu yok, manifest bilinçli olarak bağlantısız (qualification_source_key=null)

## 6. Açık noktalar (kurum kararı gerekir)

1. **Güzellik PDF'inde KDV ifadesi yok** — `vat_included=true` yalnız `fees.js` yorumuna dayanır (yorum "her iki kaynakta da" der; Güzellik PDF'inde bu ifade görülmedi). Yazılı teyit veya PDF revizyonu gerekir.
2. **Bağlantısız 19 kayıt** — bkz. `baglantisiz-19-kayit.md`.
3. **PDF adı ≠ manifest adı** kayıtları (ör. "Metal Levha Tezgâh İşçisi" ↔ "Metal Levha İşleme Tezgâh İşçisi", "Isı Yalıtımcı" ↔ "Isı Yalıtımcısı", "OPERAÖTÜRÜ" yazım hatası): kamuya hangi adın yazılacağı kararı.
4. **Aynı yeterliliğe iki ücret** (`12UY0061-3/04`: MHC/Sahil/Gemi Vinci ve İş Makineleri grubu Mobil Vinç): yeterlilik detayında ikisi de görünür; kurum hangisinin/nasıl gösterileceğini seçmeli.
5. **Tarife dönemi 2026** yalnız dosya adından çıkarılır; PDF sayfalarında yıl yoktur — kurum "2026 dönemi" teyidi vermeli.
6. **Belge basım ücreti (1.500 TL)**: fiyatlara dahil değildir (Yeni Meslekler PDF'i açık); Güzellik PDF'inde "belge ücreti" ifadesi başarı koşuluyla geçer — iki PDF'in aynı ücreti anlattığı kurumca teyit edilmeli.
