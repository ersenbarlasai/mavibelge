# Mavi Belge — Statik HTML Tanıtım Sitesi

Bu klasör, Mavi Belge'nin yeni web sitesi için müşteriye sunulacak, gerçek bir web sitesi gibi gezilebilen statik HTML tanıtım prototipidir. Yalnızca HTML5, CSS3 ve hafif JavaScript kullanır; backend, veritabanı, admin paneli veya gerçek form gönderimi içermez.

## Siteyi çalıştırma

Herhangi bir derleme adımı gerekmez. Bir yerel sunucu ile açmanız yeterlidir (dosya:// protokolünde arama/veri betikleri düzgün çalışmayabileceğinden yerel sunucu önerilir):

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

## Klasör yapısı

```
tanitim-site/
├── index.html                  Ana sayfa
├── meslekler.html               Tüm meslekler — arama/filtre
├── sektor.html?slug=...         12 sektörün tamamı için dinamik statik şablon
├── yeterlilik.html?slug=...     Yeterlilik detay şablonu
├── ... (bkz. tam sayfa listesi aşağıda)
├── 404.html
├── assets/
│   ├── css/    tokens, base, components, layout, pages, responsive
│   ├── js/     navigation, search, filters, forms-demo, components, main
│   ├── data/   sectors.js, qualifications.js, news.js
│   └── images/ logos, hero, sectors, news, references, content
├── IMAGE_SOURCES.md
└── README.md
```

## Tasarım sistemi

Ana sayfa, seçilen `V1 Görev Odaklı` tasarım yönüne dayanır: lacivert/mavi marka renkleri, beyaz/açık gri zeminler, görev kartı odaklı hiyerarşi. Bu sistem `assets/css/tokens.css` içindeki tasarım token'ları üzerinden tüm sayfalara tutarlı biçimde uygulanmıştır.

## Demo/simüle işlemler

Aşağıdaki işlemler görsel olarak tam çalışır ancak veri göndermez; gönderimde "Bu alan tanıtım sürümüdür. Gerçek başvuru işlemi yazılım aşamasında etkinleştirilecektir." mesajı gösterilir:

- Online Başvuru (çok adımlı form)
- Sınav Talepleri formu
- İtiraz ve Şikayet formu
- İletişim formu
- İş Başvurusu formu (CV dosya seçimi dahil)
- Doküman "İndir" eylemleri
- Sınav Takvimi ve Belge Sorgulama dış sistem bağlantıları

## Veri kaynağı

`assets/data/qualifications.js`, `assets/data/sectors.js` ve `assets/data/news.js` dosyaları gerçek `mavibelge.com.tr` içeriğinden doğrulanmıştır. Ayrıntılar için `IMAGE_SOURCES.md` dosyasına bakın.
