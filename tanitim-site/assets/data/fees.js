// Mavi Belge — 2026 sınav ücretleri
// Kaynaklar (görsel olarak sayfa sayfa incelendi, sadece OCR'a güvenilmedi):
//   1) "2026 GÜZELLİK FİYAT LİSTESİ (1) (1).pdf" — ucret/ klasörü, sayfa 2 (tablo)
//   2) "2026 YENİ MESLEKLİ ÜCRET TARİFESİ.pdf" — ucret/ klasörü, sayfa 1-4
// Her iki kaynakta da: "Fiyatlarımıza %20 KDV dahildir. Belge basım ücreti (1.500 TL) dahil değildir."
// qualificationCode: assets/data/qualifications.js içinde ad+seviye eşleşmesi bulunan kayıtlarda dolduruldu.
// Eşleşme bulunamayan meslekler için "" bırakıldı (PDF'de MYK kodu yazmıyor, uydurma kod eklenmedi).
// sector: eşleşen qualifications.js kaydı varsa onun sector alanı kullanıldı; eşleşme yoksa PDF'nin kendi
// grup başlığı ve bu görevde verilen sektör eşleştirme kurallarına göre atandı (bkz. teslim raporu).
window.MB_FEES = [
  // ===== GÜZELLİK VE KUAFÖRLÜK GRUBU (Güzellik PDF, sayfa 2) =====
  {
    name: "Güzellik Uzmanı", level: 4, sector: "guzellik-sac-bakim", qualificationCode: "16UY0244-4/02",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", units: ["A1", "A2", "A3", "A4"], amount: 17000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Güzellik Fiyat Listesi", sourcePage: 2
  },
  {
    name: "Epilasyon Uzmanı", level: 4, sector: "guzellik-sac-bakim", qualificationCode: "18UY0344-4/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", units: ["A1", "B1", "B2"], amount: 13500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Güzellik Fiyat Listesi", sourcePage: 2
  },
  {
    name: "Kuaför", level: 4, sector: "guzellik-sac-bakim", qualificationCode: "16UY0245-4/02",
    pricingType: "package",
    options: [
      { label: "A1+B1+B2", units: ["A1", "B1", "B2"], amount: 15750 },
      { label: "A1+B1+B2+B4+B5", units: ["A1", "B1", "B2", "B4", "B5"], amount: 24750 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Güzellik Fiyat Listesi", sourcePage: 2
  },
  {
    name: "Cilt Bakım Uygulayıcısı", level: 3, sector: "guzellik-sac-bakim", qualificationCode: "17UY0280-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", units: ["A1", "A2"], amount: 8250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Güzellik Fiyat Listesi", sourcePage: 2
  },
  {
    name: "Makyaj Uygulayıcısı", level: 3, sector: "guzellik-sac-bakim", qualificationCode: "16UY0242-3/02",
    pricingType: "unit",
    options: [
      { label: "Standart Makyaj Uygulayıcısı (A1+B1)", units: ["A1", "B1"], amount: 9750 },
      { label: "Kalıcı Makyaj Uygulayıcısı (A1+B2)", units: ["A1", "B2"], amount: 9750 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Güzellik Fiyat Listesi", sourcePage: 2
  },
  {
    name: "Protez Tırnak Uygulayıcısı", level: 3, sector: "guzellik-sac-bakim", qualificationCode: "16UY0247-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", units: ["A1", "A2"], amount: 14250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Güzellik Fiyat Listesi", sourcePage: 2
  },
  {
    name: "Kozmetik Ürünler Tanıtım ve Uygulama Elemanı", level: 3, sector: "guzellik-sac-bakim", qualificationCode: "17UY0286-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", units: ["A1", "A2"], amount: 6450 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Güzellik Fiyat Listesi", sourcePage: 2
  },

  // ===== MAKİNE GRUBU (Yeni Meslekler PDF, sayfa 1) =====
  {
    name: "Makine Bakımcı", level: 3, sector: "makine", qualificationCode: "10UY0002-3/03",
    pricingType: "unit",
    options: [
      { label: "Tek birim fiyatı (B1 veya B2)", amount: 10500 },
      { label: "B1+B2 (birlikte)", amount: 20250 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Makine Bakımcı", level: 4, sector: "makine", qualificationCode: "10UY0002-4/03",
    pricingType: "unit",
    options: [
      { label: "B1-Önleyici Bakım", amount: 11250 },
      { label: "B2-Düzeltici Bakım", amount: 12000 },
      { label: "B1+B2 (birlikte)", amount: 22500 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Makine Bakımcı", level: 5, sector: "makine", qualificationCode: "10UY0002-5/03",
    pricingType: "unit",
    options: [
      { label: "B1-Önleyici Bakım (tek birim)", amount: 14250 },
      { label: "B2-Düzeltici Bakım (tek birim)", amount: 15000 },
      { label: "B3-Kestirme (tek birim)", amount: 11250 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "NC/CNC Tezgâh İşçisi", level: 3, sector: "makine", qualificationCode: "14UY0202-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti (B1-Tornalama, B2-Frezeleme)", amount: 10500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "NC/CNC Tezgâh İşçisi", level: 4, sector: "makine", qualificationCode: "14UY0202-4/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti (B1-Tornalama, B2-Frezeleme)", amount: 11250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Tel Makineleri Operatörü", level: 4, sector: "makine", qualificationCode: "18UY0351-4/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti (B1-Tel Çekme, B2-Tel Örme)", amount: 13650 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Makine Montajcısı", level: 3, sector: "makine", qualificationCode: "12UY0105-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 13500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Tornacı", level: 3, sector: "makine", qualificationCode: "15UY0227-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 13000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Frezeci", level: 3, sector: "makine", qualificationCode: "12UY0081-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 10800 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },

  // ===== ENERJİ GRUBU (Yeni Meslekler PDF, sayfa 1) =====
  {
    name: "Sıcak Su Kazanı Operatörü", level: 3, sector: "enerji", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Tek birim fiyatı (B1/B2/B3 — yakıt tipine göre)", amount: 11250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Kızgın Yağ Operatörü", level: 4, sector: "enerji", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Tek birim fiyatı (B1/B2/B3 — yakıt tipine göre)", amount: 13250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Buhar Kazanı Operatörü", level: 4, sector: "enerji", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Tek birim fiyatı (B1/B2/B3 — yakıt tipine göre)", amount: 14500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },

  // ===== ULAŞTIRMA & LOJİSTİK GRUBU → sitede "Lojistik" (Yeni Meslekler PDF, sayfa 1) =====
  {
    name: "Köprülü Vinç Operatörü", level: 3, sector: "lojistik", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 10500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Endüstriyel Taşımacı", level: 3, sector: "lojistik", qualificationCode: "13UY0145-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti (B1-B5 birimlerinden seçime göre)", amount: 10500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Servis Aracı Şoförü", level: 3, sector: "lojistik", qualificationCode: "17UY0328-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "İşaretçi", level: 2, sector: "lojistik", qualificationCode: "15UY0218-2/01",
    pricingType: "unit",
    options: [
      { label: "A2-Liman Sapancılık İşlemleri", amount: 6450 },
      { label: "B1-İş Organizasyonu ve Elleçleme İşlemleri", amount: 9100 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Liman Kuru Yük Operasyon Elemanı/Puantör", level: 3, sector: "lojistik", qualificationCode: "13UY0170-3/02",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Gemi, B2-Saha, B3-Ambar ve CFS Puantörlüğü)", amount: 10200 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci)", level: 3, sector: "is-makineleri", qualificationCode: "12UY0061-3/04",
    pricingType: "unit",
    options: [
      { label: "B1-İş Organizasyonu, Gemi/Yük ve Liman Elleçleme Sahalarını Tanıma", amount: 14800 },
      { label: "B2-Mobil Vinci Yürütme, Konumlandırma ve Yük Elleçleme", amount: 14800 },
      { label: "B3-Çok Amaçlı Vinçleri Kullanma", amount: 10200 },
      { label: "B4-Sahil ve/veya Gemi Vincini Kullanma", amount: 14800 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Liman RTG Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "17UY0268-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Liman Forklift Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "12UY0088-3/04",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 9000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Liman Pompa ve Tank Saha Operatörü", level: 3, sector: "lojistik", qualificationCode: "12UY0063-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Liman Operasyon Planlamacısı", level: 4, sector: "lojistik", qualificationCode: "15UY0220-4",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 10500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Liman Saha İstif Makineleri Operatörü (CRS ve ECS)", level: 3, sector: "is-makineleri", qualificationCode: "12UY0064-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12150 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Liman SSG Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "17UY0269-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Terminal Çekici Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "15UY0221-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12150 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },

  // ===== METALURJİ GRUBU (Yeni Meslekler PDF, sayfa 1) =====
  {
    name: "Haddeci", level: 3, sector: "metalurji", qualificationCode: "13UY0148-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Haddeci", level: 4, sector: "metalurji", qualificationCode: "13UY0148-4/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti (B1-Sıcak Haddeleme, B2-Soğuk Haddeleme)", amount: 13000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Dökümcü", level: 4, sector: "metalurji", qualificationCode: "13UY0173-4/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti (B1-Ergitmek, B2-Döküm Yapmak)", amount: 12000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Maçacı", level: 3, sector: "metalurji", qualificationCode: "13UY0178-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 14100 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Refrakterci", level: 3, sector: "metalurji", qualificationCode: "12UY0070-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 10500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "Refrakterci", level: 4, sector: "metalurji", qualificationCode: "12UY0070-4/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 11250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },
  {
    name: "İzabeci", level: 4, sector: "metalurji", qualificationCode: "13UY0149-4/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 10500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 1
  },

  // ===== MERMER & MADEN GRUBU → sitede "Mermer" / "Maden" (Yeni Meslekler PDF, sayfa 2) =====
  {
    name: "Kırma Eleme Tesis Operatörü", level: 3, sector: "maden", qualificationCode: "16UY0265-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 7800 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Mermer-Doğaltaş Ocakçısı", level: 3, sector: "mermer", qualificationCode: "16UY0266-3/01",
    pricingType: "unit",
    options: [
      { label: "B1-Elmas Tel ile Blok Üretme", amount: 10500 },
      { label: "B2-Ana Kütlede Elmas Tel ile Büyük Kesim", amount: 11250 },
      { label: "B3-Ana Kütlede Delik Açma", amount: 11250 },
      { label: "B4-Kollu Kesme Yöntemi ile Kesim", amount: 9750 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Mermer-Doğaltaş İmalat Elemanı", level: 3, sector: "mermer", qualificationCode: "17UY0315-3/00",
    pricingType: "unit",
    options: [
      { label: "B1-Mini Sayalama, Monotel veya Monolama ile Kesim ve Blok Ebatlama ve Şekillendirme", amount: 10350 },
      { label: "B2-Levha Plakadan Ebatlı Taş Üretme", amount: 10350 },
      { label: "B3-Yüzey İşleme", amount: 9000 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Mermer-Doğaltaş Özel İmalat Elemanı", level: 4, sector: "mermer", qualificationCode: "16UY0267-4/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12150 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Yeraltı Hazırlık İşçisi", level: 3, sector: "maden", qualificationCode: "18UY0379-3",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Klasik Sistemde Galeri Açma, B3-Çelik Bağ, B4-Ahşap Bağ ile Tahkimat)", amount: 13650 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Yeraltı Hazırlık İşçisi", level: 4, sector: "maden", qualificationCode: "18UY0379-4",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Klasik Sistemde Galeri Açma İşlemini Yürütme, B3-Çelik Bağ, B4-Ahşap Bağ ile Tahkimat Yaptırma)", amount: 14250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Mekanizasyon İşçisi (Maden)", level: 4, sector: "maden", qualificationCode: "18UY0363-4",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 9000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },

  // ===== MOBİLYA GRUBU (Yeni Meslekler PDF, sayfa 2) =====
  {
    name: "Ahşap Mobilya İmalatçısı", level: 3, sector: "mobilya", qualificationCode: "17UY0301-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 9000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Ahşap Mobilya İmalatçısı", level: 4, sector: "mobilya", qualificationCode: "17UY0301-4/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 11000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Mobilya Döşemecisi", level: 3, sector: "mobilya", qualificationCode: "17UY0300-3/00",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Levha ve Kolan Üzerine Döşeme, B2-Yay Üzerine Döşeme)", amount: 9750 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },

  // ===== ELEKTRİK GRUBU → sitede "Enerji" (Yeni Meslekler PDF, sayfa 2) =====
  {
    name: "İşletme Elektrik Bakımcısı", level: 5, sector: "enerji", qualificationCode: "13UY0121-5",
    pricingType: "multiple",
    options: [
      { label: "Tek birim fiyatı", amount: 13000 },
      { label: "B1-AG Tesislerinde Bakım Onarım + B2-Kurulum ve Söküm (birlikte)", amount: 25100 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Elektrik Pano Montajcısı", level: 3, sector: "enerji", qualificationCode: "12UY0075-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 11250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Elektrik Tesisatçısı", level: 3, sector: "enerji", qualificationCode: "15UY0241-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Elektrik Tesisatçısı", level: 4, sector: "enerji", qualificationCode: "15UY0241-4",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 13500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Elektro-Mekanik Montaj İşçisi", level: 3, sector: "enerji", qualificationCode: "15UY0206-3",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 13500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },

  // ===== PLASTİK GRUBU (Yeni Meslekler PDF, sayfa 2) =====
  {
    name: "Plastik Enjeksiyon Üretim Elemanı", level: 3, sector: "plastik", qualificationCode: "12UY0069-3/02",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 13500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Plastik Şişirme Film Üretim Operatörü (Ekstrüzyon)", level: 3, sector: "plastik", qualificationCode: "13UY0143-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 15000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Plastik Profil Üretim Operatörü (Ekstrüzyon)", level: 3, sector: "plastik", qualificationCode: "13UY0142-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },

  // ===== CAM GRUBU (Yeni Meslekler PDF, sayfa 2) =====
  {
    name: "Endüstriyel Cam Isıl İşlem Elemanı", level: 4, sector: "cam", qualificationCode: "18UY0356-4",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Düz Cam, B2-Bombeli Cam Isıl İşlem Uygulamaları)", amount: 8700 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Endüstriyel Cam İşleme Elemanı", level: 4, sector: "cam", qualificationCode: "18UY0357-4",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Kenar İşleme, B3-Asit, B4-Kum, B5-Boyama Uygulamaları)", amount: 10000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },
  {
    name: "Endüstriyel Cam Kesim Elemanı", level: 4, sector: "cam", qualificationCode: "18UY0358-4",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Düz Cam, B2-Lamine Cam Kesim Uygulamaları)", amount: 9250 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 2
  },

  // ===== METAL GRUBU (Yeni Meslekler PDF, sayfa 3) =====
  {
    name: "Çelik Kaynakçısı", level: 3, sector: "metal", qualificationCode: "11UY0010-3/04",
    pricingType: "multiple",
    options: [
      { label: "Ana sınav ücreti (B1-Elektrotla Ark, B6-Metal-Ark Aktif Gaz (135), B7-Özlü Tel Metal Ark, B9-TIG Kaynağı)", amount: 7500 },
      { label: "Sınavlı yenileme", amount: 6000 },
      { label: "İlave birim", amount: 6000 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Alüminyum Kaynakçısı", level: 3, sector: "metal", qualificationCode: "11UY0014-3/02",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 9000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Direnç Kaynak Ayarcısı", level: 4, sector: "metal", qualificationCode: "11UY0015-4/03",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 7750 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Kaynak Operatörü", level: 4, sector: "metal", qualificationCode: "11UY0016-4/03",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 8000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Endüstriyel Boru Montajcısı", level: 3, sector: "metal", qualificationCode: "11UY0013-3/02",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Metal Kesimci", level: 3, sector: "makine", qualificationCode: "12UY0083-3/02",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Giyotin, B2-Oksi-Gaz, B3-Şerit Testere, B4-Daire Testere, B6-Lazer, B7-Plazma Kesim)", amount: 9000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Metal Kesim Operatörü", level: 4, sector: "makine", qualificationCode: "12UY0084-4/02",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Sac Kesme-Dilimleme, B3-Plazma Kesim)", amount: 10500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Metal Levha İşleme Tezgâh İşçisi", level: 3, sector: "makine", qualificationCode: "12UY0086-3/02",
    pricingType: "single",
    options: [{ label: "Sınav ücreti (B2-Pres)", amount: 9750 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Metal Levha İşleme Tezgâh Operatörü", level: 4, sector: "makine", qualificationCode: "12UY0087-4/02",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Abkant Pres, B2-Açık Profil Çekme/Rollform, B4-Punch Pres)", amount: 10500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Metal Kumlama İşçisi", level: 3, sector: "metal", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Püskürtme, B2-Makine/Tamburda Metal Kumlama)", amount: 9750 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Metal Yüzey Kaplamacı", level: 4, sector: "metal", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Eloksal, B2-Elektroliz, B3-Plastik, B4-Beton, B5-Sıcak Daldırma Kaplama)", amount: 15500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },

  // ===== İNŞAAT GRUBU (Yeni Meslekler PDF, sayfa 3) =====
  {
    name: "Ahşap Kalıpçı", level: 3, sector: "insaat", qualificationCode: "11UY0011-3/03",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 15000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Alçı Levha Uygulayıcısı", level: 3, sector: "insaat", qualificationCode: "12UY0054-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 15000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Alçı Sıva Uygulayıcısı", level: 3, sector: "insaat", qualificationCode: "12UY0055-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 15000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Betonarme Demircisi", level: 3, sector: "insaat", qualificationCode: "11UY0012-3/03",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 15000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Duvarcı", level: 3, sector: "insaat", qualificationCode: "12UY0048-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 14500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "İnşaat Boyacısı", level: 3, sector: "insaat", qualificationCode: "11UY0023-3/02",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 12000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "İnşaat İşçisi", level: 2, sector: "insaat", qualificationCode: "16UY0253-2/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 9000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Isı Yalıtımcısı", level: 3, sector: "insaat", qualificationCode: "12UY0057-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 15000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "İskele Kurulum Elemanı", level: 3, sector: "insaat", qualificationCode: "12UY0056-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 13500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "PVC Doğrama Montajcısı", level: 3, sector: "insaat", qualificationCode: "14UY0195-3/00",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 14500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Seramik Karo Kaplamacısı", level: 3, sector: "insaat", qualificationCode: "12UY0051-3/01",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 15000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Sıvacı", level: 3, sector: "insaat", qualificationCode: "11UY0024-3/02",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 15000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },

  // ===== İŞ MAKİNELERİ GRUBU (Yeni Meslekler PDF, sayfa 3) =====
  {
    name: "Kule Vinç Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 34000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Ekskavatör Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 31000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Mobil Vinç Operatörü (İş Makineleri Grubu)", level: 3, sector: "is-makineleri", qualificationCode: "12UY0061-3/04",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 9500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Kazıcı Yükleyici (Beko Loder) Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 23000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Beton Santral Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 33000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Beton Transmikser Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 30000 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },
  {
    name: "Beton Pompa Operatörü", level: 3, sector: "is-makineleri", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 31500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 3
  },

  // ===== TEKSTİL GRUBU (Yeni Meslekler PDF, sayfa 4) =====
  {
    name: "Ön Terbiye Operatörü", level: 3, sector: "tekstil", qualificationCode: "13UY0139-3/01",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Yakma, B2-Yıkama, B3-Merserizasyon, B4-Karbonizasyon, B5-Ağartma, B6-Haşıl Sökme)", amount: 12200 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "Boyama Operatörü", level: 3, sector: "tekstil", qualificationCode: "13UY0138-3/01",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Bobin, B2-Çile, B3-Elyaf-Tops, B4-Jet, B5-Jigger, B6-Pad-Batch, B7-Pad-Steam, B8-Parça Boyama)", amount: 7800 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "Bitim İşlemleri Operatörü", level: 3, sector: "tekstil", qualificationCode: "13UY0137-3/01",
    pricingType: "unit",
    options: [
      { label: "B1-Dekatür", amount: 7800 }, { label: "B2-Kalandır", amount: 7800 },
      { label: "B3-Sanfor", amount: 7800 }, { label: "B4-Şardon", amount: 7800 },
      { label: "B5-Traşlama", amount: 7800 }, { label: "B6-Zımpara", amount: 7800 },
      { label: "B7-Lisa", amount: 7800 }, { label: "B8-Balon Sıkma", amount: 7800 },
      { label: "B9-Santrifuj", amount: 9000 }, { label: "B10-Fikse", amount: 7800 },
      { label: "B11-Kurutma", amount: 7800 }, { label: "B12-Dinkleme", amount: 7800 },
      { label: "B13-Ram", amount: 7800 }, { label: "B14-Kaplama", amount: 7800 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "Ön İplik Operatörü", level: 3, sector: "tekstil", qualificationCode: "11UY0039-3/02",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-B9 hazırlık/şerit/fitil birimlerinden seçime göre)", amount: 7800 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "İplik Eğirme Operatörü", level: 2, sector: "tekstil", qualificationCode: "11UY0037-2/01",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Ring, B2-Open End, B3-Hava Jetli İplik Eğirme)", amount: 6500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "İplik Bitim İşleri Operatörü", level: 2, sector: "tekstil", qualificationCode: "11UY0036-2/01",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Bobinleme, B2-Katlama, B3-Büküm, B4-Fikseleme)", amount: 6500 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },

  // ===== DOKUMA (Yeni Meslekler PDF, sayfa 4 — site sektörü: Tekstil) =====
  {
    name: "Dokuma Operatörü", level: 3, sector: "tekstil", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 6450 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "Dokuma Operatörü", level: 4, sector: "tekstil", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Sınav ücreti", amount: 8400 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "Tahar Operatörü", level: 3, sector: "tekstil", qualificationCode: "",
    pricingType: "unit",
    options: [
      { label: "B1-El ile Tahar Yapma", amount: 7800 },
      { label: "B2-Makine ile Tahar Yapma", amount: 8400 },
      { label: "B3-Çözgü İpliği Düğümleme", amount: 7800 },
      { label: "B4-Jakar Dizilimi Yapma", amount: 8400 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "Çözgü Operatörü", level: 3, sector: "tekstil", qualificationCode: "",
    pricingType: "single",
    options: [{ label: "Tek birim (B1-Seri, B2-Konik Çözgü Hazırlama, B3-Haşıllama, B4-Halat Sarma, B5-Halat Açma)", amount: 7125 }],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },

  // ===== ÖRME (Yeni Meslekler PDF, sayfa 4 — site sektörü: Tekstil) =====
  {
    name: "Örme Operatörü", level: 4, sector: "tekstil", qualificationCode: "",
    pricingType: "unit",
    options: [
      { label: "B1-Raşel/Trikot Örme", amount: 8400 },
      { label: "B2-Kroşet Örme", amount: 7125 },
      { label: "B3-Yuvarlak Örme", amount: 7800 },
      { label: "B4-Düz (Triko) Örme", amount: 7800 },
      { label: "B5-Çorap Örme", amount: 7125 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "Çözgülü Örme Operatörü", level: 3, sector: "tekstil", qualificationCode: "",
    pricingType: "unit",
    options: [
      { label: "B1-Raşel/Trikot Örme", amount: 7125 },
      { label: "B2-Kroşet Örme", amount: 7125 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  },
  {
    name: "Atkılı Örme Operatörü", level: 3, sector: "tekstil", qualificationCode: "",
    pricingType: "unit",
    options: [
      { label: "B1-Yuvarlak Örme", amount: 6525 },
      { label: "B2-Düz (Triko) Örme", amount: 6525 },
      { label: "B3-Çorap Örme", amount: 6525 }
    ],
    vatIncluded: true, certificatePrintFeeExcluded: 1500,
    source: "2026 Yeni Meslekler Ücret Tarifesi", sourcePage: 4
  }
];
