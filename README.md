# Mavi Belge Web Sitesi

Bu depo, Mavi Belge'nin yeni web sitesi için müşteriye sunulacak **statik HTML tanıtım sitesini** ve projeye ait karar/inceleme raporlarını içerir.

## Bu sürüm nedir

Bu sürüm, müşteriye sunulacak bir **statik HTML/CSS/JavaScript tanıtım prototipidir**. Veritabanı, backend, admin paneli veya gerçek form gönderimi içermez. Formlar ve dış sistem bağlantıları görsel olarak tam çalışır ancak veri göndermez; demo mesajı gösterir.

Gelecekte, kurum ile mutabık kalınan kapsam üzerinden **Laravel tabanlı gerçek bir sisteme** geçilmesi planlanmaktadır (bkz. `raporlar/final-rapor.md`). Bu depo yalnızca ön yüz/tanıtım aşamasının kaynak kodudur.

## Ana site klasörü

```
tanitim-site/
```

Ana giriş dosyası: `tanitim-site/index.html`

## Siteyi yerelde çalıştırma

```bash
cd tanitim-site
python -m http.server 8080
# tarayıcıda http://localhost:8080 adresini açın
```

veya Node yüklüyse:

```bash
cd tanitim-site
npx serve .
```

## Kapsam

- Toplam **41 HTML sayfası**.
- **83 mesleki yeterlilik kaydı** (MYK kodu, seviye, sektör ile doğrulanmış).
- **12 sektör**: Makine, Metalurji, Metal, Ulaştırma & Lojistik, Plastik, Elektrik, Cam, Tekstil, İnşaat, Mobilya, Maden & Mermer, Güzellik ve Saç Bakım.
- Orijinal Mavi Belge, MYK ve TÜRKAK logoları korunmuştur; değiştirilmemiş, yeniden çizilmemiştir.
- "Referanslarımız" bölümündeki 12 logo **temsili**dir; gerçek müşteri referansı değildir (bkz. `tanitim-site/IMAGE_SOURCES.md`).
- Ücret, referans ve bazı kurumsal bilgiler kurum onayı bekliyor; doğrulanamayan yerlerde uydurma veri yerine "Bilgi güncellenecektir" gibi dürüst demo metni kullanılmıştır.

## Klasör yapısı (özet)

```
.
├── raporlar/
│   └── final-rapor.md          Kapsam ve içerik kararlarının temel referansı
├── tanitim-site/                Statik tanıtım sitesi (bkz. tanitim-site/README.md)
├── header-logo.png              Orijinal Mavi Belge logosu (kaynak)
├── myk_logo.png                 Orijinal MYK logosu (kaynak)
├── turkak_logo.png              Orijinal TÜRKAK logosu (kaynak)
└── STITCH_*.md / CLAUDE_*.md    Tasarım/uygulama prompt kayıtları
```

Ayrıntılı site yapısı, sayfa listesi ve demo işlemler için: `tanitim-site/README.md`.

## Doğrulanmamış iddialar hakkında

Bu README ve site içeriğinde doğrulanmamış başarı rakamı, müşteri sayısı veya sertifika iddiası bulunmamaktadır. Gerçek veri bulunmayan alanlar açıkça "temsili" veya "Bilgi güncellenecektir" şeklinde işaretlenmiştir.
