# Görev Kartı — Veri/İçerik Aktarım Agentı

> Merkezi kayıt: [`../wordpress-agent-mimarisi.md`](../wordpress-agent-mimarisi.md) · Bağlayıcı plan: [`../wordpress-ana-uygulama-plani.md`](../wordpress-ana-uygulama-plani.md) · Çalışma kuralları: [`../../AGENTS.md`](../../AGENTS.md)
>
> Buradaki WordPress yolları **henüz mevcut değildir**; gelecekteki hedef yol/desenlerdir.

## 1. Amaç

Statik tanıtım sitesindeki doğrulanmış veriyi ve mevcut canlı WordPress sitesindeki içeriği, kayıp ve uydurma olmadan yeni `mavibelge-core` modeline aktarmak.

## 2. Sorumluluklar

1. **Yeterlilik aktarımı:** `tanitim-site/assets/data/qualifications.js` → 83 kayıt. Kod, ad, seviye, sektör.
2. **Ücret aktarımı:** `tanitim-site/assets/data/fees.js` → 103 kayıt. 19 kaydın MYK kodu boştur; manuel eşleştirme yapılır, **kod uydurulmaz**.
3. **Sektör/taksonomi aktarımı:** `tanitim-site/assets/data/sectors.js` → 14 kayıt. Kurum onaylı ada bağlı; bkz. §8.
4. **Haber/duyuru aktarımı:** `tanitim-site/assets/data/news.js` → 6 kayıt (3 haber, 3 duyuru) + mevcut canlı sitedeki 31 yazının görselleriyle taşınması.
5. **Referans aktarımı:** 12 temsili logo, **"temsili" işareti korunarak**.
6. **Medya aktarımı:** 44 görsel (24 PNG + 20 SVG) + 2 ücret PDF'i. WebP dönüşümü, `srcset` boyutları, dosya adı normalizasyonu, hash doğrulaması.
7. **Sayfa içeriği aktarımı:** 41 statik sayfanın metni ve mevcut canlı sitedeki 114 sabit sayfanın taşıma/birleştirme/arşivleme kararına göre aktarımı.
8. **Karakter ve yazım düzeltmeleri:** Mevcut sitedeki karakter bozulmaları (`final-rapor.md` §3.3).
9. **Slug normalizasyonu:** WordPress `-2` ekleri, `doagltas`, `kuafor-2`, `3114-2` gibi izler taşınmaz (`final-rapor.md` §6.1).
10. **Eşleştirme tablosu:** Her kaynak kayıt → hedef kayıt, doğrulama durumu.
11. **Idempotent içe aktarma:** Aynı içe aktarma iki kez çalıştırıldığında mükerrer kayıt oluşmaz.

## 3. Kapsam Dışı ve Yasak İşlemler

- **Veri uydurmak.** Ücret tutarı, MYK kodu, seviye, revizyon, adres, akreditasyon numarası, tarih, referans kurumu — hiçbiri uydurulmaz.
- Tema veya eklenti kodu yazmak.
- Şema, alan veya ilişki tasarımı yapmak (çekirdek agentının alanı).
- SEO kural tanımı veya yönlendirme kararı vermek (SEO agentının alanı).
- `tanitim-site/**` içinde değişiklik. **Yalnız okunur kaynaktır.**
- Canlı `mavibelge.com.tr` sitesinde yazma işlemi. Okuma yalnız kullanıcının açık onayıyla ve salt okunur biçimde yapılır.
- Kurum onayı olmadan içerik silmek. Karar "taşı, birleştir, arşivle, 301, 410" seçeneklerinden biri olmalıdır.
- Kişisel veri içeren kayıt (aday başvurusu, iletişim mesajı, CV) aktarmak.
- Ortak dosyaları doğrudan değiştirmek.

## 4. Sahip Olunan Dosya/Klasör Türleri

| Yol (hedef) | İçerik |
|---|---|
| `tools/import/**` | İçe aktarma betikleri ve WP-CLI komutları |
| `data/mapping/**` | Kaynak→hedef eşleştirme tabloları (CSV/JSON) |
| `data/content/**` | Doğrulanmış aktarım kaynağı, dışa aktarılmış ara veri |
| `raporlar/veri-aktarim-raporlari/**` | Aktarım ve doğrulama raporları |

## 5. Ortak Dosya Değişiklik Protokolü

İçe aktarma bir WP-CLI komutu veya kanca kaydı gerektiriyorsa (`mavibelge-core.php` veya `functions.php`):

1. Dosyaya **dokunma**.
2. Entegrasyon notu hazırla: hangi dosya, tam kod parçası, gerekçe, çalıştırma sırası, geri alma yöntemi.
3. Ana orkestratöre ilet.
4. Birleştirme orkestratörün; doğrulama QA'nın.

## 6. Diğer Agent Bağımlılıkları

| Agent | İş bölümü |
|---|---|
| WordPress çekirdek/eklenti | Alan adları, doğrulama kuralları, idempotent yazma uçları — **model onların** |
| SEO/AIO | Slug kuralları, eski→yeni URL eşleştirmesi, meta başlangıç değerleri |
| Tema ve arayüz | Medya boyutları, görsel adlandırma, alt metin kaynağı |
| Güvenlik | Kişisel veri filtresi, dosya türü kontrolü |
| QA | Kayıt sayısı ve içerik doğruluğu kabul testi |
| Ana orkestratör | Ortak dosya birleştirme, kurum onaylarının kaydı |

## 7. Girdi ve Çıktı Sözleşmesi

**Girdi (yeniden doğrulanmış sayılar — [`../wordpress-faz0-depo-envanteri.md`](../wordpress-faz0-depo-envanteri.md) §3.3):**

| Kaynak | Kayıt |
|---|---:|
| `qualifications.js` | 83 (83'ü benzersiz kod) |
| `fees.js` | 103 (19'unda MYK kodu boş) |
| `sectors.js` | 14 (4'ünde görsel yok) |
| `news.js` | 6 (3 haber + 3 duyuru) |
| `references.js` | 12 (tamamı temsili) |
| Görsel | 44 (24 PNG + 20 SVG) |
| PDF | 2 (`tanitim-site/ucret/`) |
| Mevcut canlı site | 114 sayfa + 31 yazı (`final-rapor.md` §4.1) |

**Çıktı:** İçe aktarma betikleri; kaynak→hedef eşleştirme tablosu; aktarım raporu (aktarılan/atlanan/hatalı sayılar); doğrulanamayan kayıt listesi; kurum onayı bekleyen karar listesi.

**Sözleşme:** Aktarım raporu, her kaynak kaydın hedefte ne olduğunu tek tek gösterir. "Toplu olarak taşındı" ifadesi kabul edilmez.

## 8. PHP 7.3 ve Mevcut Hosting Kısıtları

- İçe aktarma betikleri PHP 7.3 sözdizimiyle yazılır ([`../wordpress-faz0-uyumluluk-matrisi.md`](../wordpress-faz0-uyumluluk-matrisi.md) §5).
- Sunucuda WP-CLI bulunacağı **varsayılmaz**. Bulunmazsa yönetim ekranından çalışan, adım adım ilerleyen ve zaman aşımına dayanıklı bir içe aktarıcı gerekir.
- `max_execution_time`, `memory_limit`, `max_input_vars` ve `upload_max_filesize` doğrulanmadan toplu aktarma denenmez.
- Veritabanı `utf8mb4` doğrulanmadan Türkçe içerik yazılmaz. Mevcut sitedeki karakter bozulmalarının kaynağı collation olabilir (matris §7.2).
- Medya aktarımı öncesi disk boş alanı **ve inode** kullanımı doğrulanır (matris §10.2).

### Kurum onayı bekleyen kararlar

> Not: "12 mi 14 mü sektör" maddesi bu listede **artık yok** — Faz 2'de kesin karara bağlanmıştır: **14 sektör kullanıcı tarafından bağlayıcı olarak kesinleştirilmiştir** (bkz. `wordpress-site/docs/content-model.md` "Sektör" bölümü, `raporlar/proje-durumu.md`). Aşağıdaki liste yalnız gerçekten hâlâ açık olan diğer maddeleri içerir.

| Konu | Durum |
|---|---|
| `maden` ve `mermer` aynı görseli kullanıyor | **Onay bekliyor** |
| Gerçek referans kurumları | **Onay bekliyor** — 12 logo temsili |
| 19 ücret kaydının MYK kodu | **Onay bekliyor** |
| Sınav takvimi / MYK sorgu gerçek adresleri | **Onay bekliyor** — envanter §7.2 |
| Maden & Mermer'de 5 kaydın aynı detay URL'sine bağlanması | **Onay bekliyor** — `YETERLILIK_ESLESTIRME_RAPORU.md` |

## 9. Güvenlik ve Kişisel Veri Kuralları

- Aday başvurusu, iletişim mesajı, iş başvurusu ve CV **aktarılmaz**. Bunlar kişisel veridir ve ayrı hukuki karar gerektirir.
- Aktarılan hiçbir dosya tahmin edilebilir medya URL'sinden erişilebilir hassas belge olmaz.
- İçe aktarma betikleri veritabanı kimlik bilgilerini kod içine yazmaz.
- Örnek/test verisi gerçek kişi bilgisi içermez.
- Canlı siteden okuma yapılıyorsa yalnız salt okunur, kullanıcının açık onayıyla ve hız sınırlı olarak yapılır.

## 10. Test ve Kalite Kapıları

- [ ] Kayıt sayıları kaynakla birebir eşleşiyor: 83 / 103 / 14 / 6 / 12
- [ ] Hiçbir MYK kodu mükerrer değil (83 benzersiz)
- [ ] `myk_code + level + revision` benzersizlik kuralı ihlal edilmiyor
- [ ] Uydurulmuş tek bir alan yok — her kayıt kaynak dosyaya izlenebiliyor
- [ ] Türkçe karakterler doğru görüntüleniyor (bozulma yok)
- [ ] Slug'larda `-2` eki ve yazım hatası izi yok
- [ ] Tüm medya dosyaları hash ile doğrulanmış
- [ ] Kırık medya yolu yok
- [ ] "Temsili" referanslar açıkça işaretli
- [ ] İçe aktarma iki kez çalıştırıldığında mükerrer kayıt oluşmuyor
- [ ] Kişisel veri içeren hiçbir kayıt aktarılmamış
- [ ] Geri alma (rollback) yöntemi belgelenmiş ve denenmiş

## 11. Teslim Raporu Biçimi

1. Çalıştırılan içe aktarma betikleri ve sırası.
2. Kaynak→hedef kayıt sayısı tablosu (aktarılan / atlanan / hatalı).
3. Uydurulmayan, boş bırakılan alanların listesi ve gerekçesi.
4. Manuel eşleştirilen kayıtlar (özellikle 19 ücret kaydı) ve eşleştirme kanıtı.
5. Karakter/slug düzeltme listesi.
6. Medya aktarım ve hash doğrulama sonucu.
7. Kurum onayı bekleyen kararlar (§8 tablosu).
8. Ortak dosyalar için üretilen entegrasyon notları.
9. Çalıştırılan kalite kapıları ve sonuçları.
10. Geri alma yöntemi ve denendiğinin kanıtı.
11. Commit/push/deploy yapılmadığının teyidi.
