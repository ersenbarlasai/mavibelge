# WordPress Agent Mimarisi — Merkezi Kayıt ve Yönetişim

> Tarih: 10 Eylül 2026
>
> Bu belge, Mavi Belge **WordPress** dönüşümünde çalışacak agentların merkezi kayıt ve yönetişim belgesidir. Ayrıntı burada tekrar edilmez; her agentın kendi görev kartına yazılır.
>
> Bu belge, Laravel dönemine ait [`agent-mimarisi.md`](./agent-mimarisi.md) belgesinin **yerine geçer**. O belge tarihsel kayıt olarak korunur ve **uygulama otoritesi değildir**.
>
> Bağlayıcı mimari karar: [`wordpress-ana-uygulama-plani.md`](./wordpress-ana-uygulama-plani.md) §12. Çelişki halinde o belge esas alınır.

---

## 1. Temel Yönetişim Kuralları

1. **Bir agent, başka bir agentın sahip olduğu dosyayı değiştirmez.** Değişiklik gerekiyorsa entegrasyon notu hazırlar ve ana orkestratöre iletir.
2. **Ortak dosyaları yalnız ana orkestratör birleştirir.** Hiçbir agent ortak dosyada tek taraflı birleştirme yapmaz. Ortak dosya listesi §4'tedir.
3. Her agent kendi teslim raporunu, görev kartındaki biçimde verir.
4. Bir agent kapsamı dışında bir sorun tespit ederse çözmeye kalkmaz; bulguyu raporlar ve ilgili agenta yönlendirilmesini ister.
5. Hiçbir agent uydurma veri (ücret, MYK kodu, adres, akreditasyon numarası, referans, tarih) üretmez.
6. Hiçbir agent canlı `mavibelge.com.tr` sitesine, DNS'e, SSL'e, DirectAdmin'e, FTP'ye veya mail ayarlarına dokunmaz. Bu yetki yalnız kullanıcının açık onayıyla DevOps agentına verilir.
7. Hiçbir agent commit, push, tag, release veya deploy işlemini kullanıcı onayı olmadan yapmaz.
8. Hiçbir agent `tanitim-site/**` içeriğini değiştirmez. Statik referans **dondurulmuştur** ve yalnız okunur.

---

## 2. Agent Listesi

Durum sütunu, bu Faz 0 görevi sonundaki gerçek durumu gösterir.

| # | Agent | Amaç | Sahip olduğu alan | Değiştirmemesi gereken alan | Bağımlılıklar | Durum |
|---:|---|---|---|---|---|---|
| 1 | **Ana orkestratör** | Görev sırasını yönetir, ortak dosyaları birleştirir, karar kaydını tutar | Ortak dosyalar (§4), `AGENTS.md`, `raporlar/proje-durumu.md`, `README.md`, faz sırası, karar kaydı | Hiçbir agentın kendi modül içine tek taraflı müdahale etmez | Tüm agentlar | Görev kartı hazır |
| 2 | **WordPress çekirdek/eklenti agentı** | `mavibelge-core` eklentisi ve iş kuralları | `wp-content/plugins/mavibelge-core/**` (bootstrap dosyası hariç) | Tema dosyaları, dağıtım, test dosyaları, SEO kural tanımı, içerik metni | SEO, güvenlik, veri, tema | Görev kartı hazır |
| 3 | **Tema ve arayüz agentı** | `mavibelge` teması, şablonlar, tasarım sistemi, erişilebilirlik | `wp-content/themes/mavibelge/**` (bootstrap dosyaları hariç) | Eklenti iş mantığı, veritabanı, dağıtım, içerik metni | Çekirdek, SEO, QA | Görev kartı hazır |
| 4 | **Veri/içerik aktarım agentı** | Statik veriden WordPress'e doğrulanmış aktarım | `tools/import/**`, `data/mapping/**`, aktarım raporları | Tema, eklenti kodu, sunucu, SEO kural tanımı | Çekirdek, SEO, QA | Görev kartı hazır |
| 5 | **SEO/AIO agentı** | SEO, AIO/GEO, şema, sitemap, eski URL kararları | `docs/seo/**`, `data/redirects/**`, SEO alan/şema/robots **kural tanımı** | Tema/eklenti kodu, içerik metni, sunucu yapılandırması, ücret/MYK verisi | Çekirdek, tema, veri, DevOps, güvenlik | Görev kartı hazır |
| 6 | **Güvenlik agentı** | Kod güvenliği, roller, formlar, kişisel veri | `docs/guvenlik/**`, güvenlik kontrol listeleri, KVKK alan onay kayıtları | Uygulama kodu (bulgu raporlar, kendisi yazmaz), tema, dağıtım | Tüm agentlar | Görev kartı hazır |
| 7 | **DevOps/dağıtım agentı** | Ortam, paketleme, yükleme, yedek, geri dönüş | `deploy/**`, paketleme betikleri, dağıtım ve geri dönüş prosedürleri | Uygulama kodu, içerik, SEO kural tanımı | Orkestratör, QA, güvenlik | Görev kartı hazır |
| 8 | **QA agentı** | Görsel, işlevsel, responsive, erişilebilirlik, regresyon | `tests/**`, `qa/**`, test raporları | Uygulama kodu (bulgu raporlar, düzeltmez), dağıtım | Tüm agentlar | Görev kartı hazır |

Görev kartları: [`wordpress-agent-gorev-kartlari/`](./wordpress-agent-gorev-kartlari/)

| Agent | Görev kartı |
|---|---|
| Ana orkestratör | [`01-ana-orkestrator.md`](./wordpress-agent-gorev-kartlari/01-ana-orkestrator.md) |
| WordPress çekirdek/eklenti | [`02-wordpress-cekirdek-eklenti-agenti.md`](./wordpress-agent-gorev-kartlari/02-wordpress-cekirdek-eklenti-agenti.md) |
| Tema ve arayüz | [`03-tema-arayuz-agenti.md`](./wordpress-agent-gorev-kartlari/03-tema-arayuz-agenti.md) |
| Veri/içerik aktarım | [`04-veri-icerik-aktarim-agenti.md`](./wordpress-agent-gorev-kartlari/04-veri-icerik-aktarim-agenti.md) |
| SEO/AIO | [`05-seo-aio-agenti.md`](./wordpress-agent-gorev-kartlari/05-seo-aio-agenti.md) |
| Güvenlik | [`06-guvenlik-agenti.md`](./wordpress-agent-gorev-kartlari/06-guvenlik-agenti.md) |
| DevOps/dağıtım | [`07-devops-dagitim-agenti.md`](./wordpress-agent-gorev-kartlari/07-devops-dagitim-agenti.md) |
| QA | [`08-qa-agenti.md`](./wordpress-agent-gorev-kartlari/08-qa-agenti.md) |

---

## 3. Sahiplik Haritası

> **Uyarı:** Aşağıdaki WordPress yollarının **hiçbiri henüz mevcut değildir.** Bunlar gelecekteki hedef yol/desenlerdir. Bu Faz 0 görevinde hiçbir WordPress dosyası oluşturulmamıştır.

| Yol / desen (hedef) | Tek sahip |
|---|---|
| `wp-content/themes/mavibelge/**` | Tema ve arayüz agentı |
| `wp-content/plugins/mavibelge-core/**` | WordPress çekirdek/eklenti agentı |
| `tools/import/**` | Veri/içerik aktarım agentı |
| `data/mapping/**` | Veri/içerik aktarım agentı |
| `data/content/**` | Veri/içerik aktarım agentı |
| `data/redirects/**` | SEO/AIO agentı |
| `docs/seo/**` | SEO/AIO agentı |
| `docs/guvenlik/**` | Güvenlik agentı |
| `deploy/**` | DevOps/dağıtım agentı |
| `tests/**` (depo kökü) | QA agentı |
| `qa/**` | QA agentı |
| `raporlar/proje-durumu.md` | Ana orkestratör |
| `raporlar/wordpress-agent-mimarisi.md` | Ana orkestratör |
| `raporlar/wordpress-agent-gorev-kartlari/**` | Ana orkestratör |
| `raporlar/veri-aktarim-raporlari/**` | Veri/içerik aktarım agentı |
| `raporlar/guvenlik-raporlari/**` | Güvenlik agentı |
| `raporlar/dagitim-raporlari/**` | DevOps/dağıtım agentı |
| `raporlar/qa-raporlari/**` | QA agentı |
| `AGENTS.md` | Ana orkestratör |
| `README.md` | Ana orkestratör |

Her yol tam olarak **bir** agenta aittir. Çakışma yoktur.

### 3.1. Test dosyalarının sahiplik ayrımı

Üç ayrı test yolu vardır ve birbiriyle çakışmaz:

| Yol | Sahip | Kapsam |
|---|---|---|
| `wp-content/plugins/mavibelge-core/tests/**` | WordPress çekirdek/eklenti agentı | Eklenti birim testleri |
| `wp-content/themes/mavibelge/tests/**` | Tema ve arayüz agentı | Tema birim/anlık görüntü testleri |
| `tests/**` (depo kökü) + `qa/**` | QA agentı | Uçtan uca, entegrasyon, görsel, erişilebilirlik, regresyon |

### 3.2. Bu Faz 0 görevinde oluşturulan gerçek dosyalar

Aşağıdakiler hedef değil, **mevcut** dosyalardır:

- `AGENTS.md`
- `raporlar/proje-durumu.md`
- `raporlar/wordpress-faz0-depo-envanteri.md`
- `raporlar/wordpress-faz0-uyumluluk-matrisi.md`
- `raporlar/wordpress-sunucu-bilgi-talebi.md`
- `raporlar/wordpress-agent-mimarisi.md`
- `raporlar/wordpress-agent-gorev-kartlari/01..08-*.md`

Hiçbir WordPress dosyası (`wp-content/**`) oluşturulmamıştır.

---

## 4. Ortak Dosyalar — Yalnız Ana Orkestratör Birleştirir

Aşağıdaki dosyalara birden fazla agent katkı verir. Hiçbir agent bunları doğrudan değiştirmez; **kendi bölümünü ayrı bir yama/entegrasyon notu olarak teslim eder**, ana orkestratör birleştirir.

| Ortak dosya (hedef) | Katkı veren agentlar | Neden ortak |
|---|---|---|
| `wp-content/themes/mavibelge/functions.php` | Tema, çekirdek, SEO, güvenlik | Tema önyüklemesi, enqueue, tema desteği, güvenlik sabitleri ve SEO kancaları burada buluşur |
| `wp-content/themes/mavibelge/style.css` (tema başlık bloğu) | Tema, orkestratör | Tema kimlik/sürüm bilgisi |
| `wp-content/plugins/mavibelge-core/mavibelge-core.php` (bootstrap) | Çekirdek, güvenlik, SEO | Eklenti başlığı, sürüm, etkinleştirme kancaları, yetenek kaydı |
| `.htaccess` | DevOps, SEO, güvenlik | Kalıcı bağlantı + yönlendirme + güvenlik başlıkları aynı dosyada |
| `robots.txt` | SEO, DevOps | Kural tanımı SEO'nun, ortam farkı (staging/prod) DevOps'un |
| `wp-config.php` şablonu (`wp-config-sample-mavibelge.php`) | Güvenlik, DevOps, çekirdek | `DISALLOW_FILE_EDIT`, tuzlar, hata ayıklama, veritabanı ayarları |
| `composer.json` / `package.json` | Çekirdek, tema, DevOps | Bağımlılık ve derleme |
| `README.md`, `AGENTS.md`, `raporlar/proje-durumu.md` | Tümü | Proje hafızası |

### 4.1. Ortak dosya değişiklik protokolü

Her agent için aynıdır:

1. **Tespit** — değişikliğin hangi ortak dosyada gerektiği belirlenir.
2. **Entegrasyon notu** — agent şunları yazar: hangi dosya, hangi bölüm, eklenecek/değişecek tam kod parçası, gerekçe, çalıştırma sırası bağımlılığı (varsa), geri alma yöntemi.
3. **Teslim** — not ana orkestratöre iletilir. Agent dosyaya **dokunmaz**.
4. **Birleştirme** — ana orkestratör çakışmayı çözer, sırayı belirler ve tek elden uygular.
5. **Doğrulama** — birleştirme sonrası QA agentı ilgili kalite kapılarını yeniden çalıştırır.
6. **Kayıt** — birleştirme kararı `raporlar/proje-durumu.md` içine yazılır.

---

## 5. Bağımlılık ve Sıra

Fazlar `wordpress-ana-uygulama-plani.md` §13'te tanımlıdır. Agent sırası:

| Faz | Baş agent | Destek |
|---|---|---|
| Faz 0 — Envanter ve uyumluluk | Ana orkestratör | — (tamamlandı) |
| Faz 1 — İskelet | DevOps + orkestratör | Çekirdek, tema |
| Faz 2 — İçerik modeli | Çekirdek | SEO, güvenlik |
| Faz 3 — Temel tema | Tema | Çekirdek, QA |
| Faz 4 — Sayfa şablonları | Tema | Çekirdek, SEO |
| Faz 5 — Meslek/sektör/ücret | Çekirdek + tema | Veri |
| Faz 6 — Veri aktarımı | Veri | Çekirdek, QA |
| Faz 7 — Haber/doküman/referans/lokasyon | Çekirdek + tema | Veri |
| Faz 8 — Formlar | Güvenlik + tema | Çekirdek |
| Faz 9 — SEO/AIO ve eski URL'ler | SEO | Çekirdek, DevOps |
| Faz 10 — Performans ve güvenlik | Güvenlik + DevOps | Tema, çekirdek |
| Faz 11 — QA ve içerik kabulü | QA | Tümü |
| Faz 12 — Staging dağıtımı | DevOps | QA, güvenlik |
| Faz 13 — Canlıya geçiş | DevOps + orkestratör | Tümü |

Bir fazın kabul kriterleri geçmeden sonraki faza başlanmaz.

---

## 6. Laravel Dönemi Belgelerinin Statüsü

| Belge | Statü |
|---|---|
| [`agent-mimarisi.md`](./agent-mimarisi.md) | **Tarihsel.** Yerine bu belge geçti. Laravel/Filament agent ayrımı uygulanmaz. |
| [`seo-aio-agent-gorev-karti.md`](./seo-aio-agent-gorev-karti.md) | **Tarihsel ama içerik değeri yüksek.** Crawler politikası, bot doğrulama zinciri, SEO kalite kapıları ve ölçümleme planı WordPress'te de geçerlidir. Laravel/Filament/Blade referansları uygulanmaz. |
| [`seo-icerik-modeli-taslagi.md`](./seo-icerik-modeli-taslagi.md) | **Tarihsel ama içerik değeri yüksek.** `seo_meta` polimorfik tablo yaklaşımı WordPress'te post meta ile karşılanır. Alan sözlüğü, doğrulama kuralları ve `@graph` yaklaşımı geçerlidir. |
| [`final-rapor.md`](./final-rapor.md) | **Tarihsel araştırma ve içerik kaynağı.** İçerik/URL/erişilebilirlik bulguları geçerlidir. §11, §12, §13 (Laravel/Filament mimarisi) **uygulanmaz**. |

**Kural:** Hiçbir WordPress agentı Laravel, Filament, Blade, Eloquent migration, queue veya yeni sunucu/hosting kararını güncel karar gibi uygulamaz.
