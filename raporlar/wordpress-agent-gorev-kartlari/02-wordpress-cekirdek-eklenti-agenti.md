# Görev Kartı — WordPress Çekirdek/Eklenti Agentı

> Merkezi kayıt: [`../wordpress-agent-mimarisi.md`](../wordpress-agent-mimarisi.md) · Bağlayıcı plan: [`../wordpress-ana-uygulama-plani.md`](../wordpress-ana-uygulama-plani.md) · Çalışma kuralları: [`../../AGENTS.md`](../../AGENTS.md)
>
> Buradaki WordPress yolları **henüz mevcut değildir**; gelecekteki hedef yol/desenlerdir.

## 1. Amaç

`mavibelge-core` eklentisini geliştirmek. Kurumsal veri ve iş kuralları temadan bağımsız olarak burada yaşar; tema değişse bile veri kaybolmaz (`wordpress-ana-uygulama-plani.md` §3.1).

## 2. Sorumluluklar

1. **İçerik türleri:** Yeterlilik/Meslek, Ücret tarifesi, Haber, Doküman, Referans, Lokasyon, SSS (`wordpress-ana-uygulama-plani.md` §4.2).
2. **Taksonomi:** Sektörleri hiyerarşik taksonomi olarak modellemek. Adlandırma kurum onayına bağlıdır — bkz. §8 uyarısı.
3. **Alanlar ve doğrulamalar:** `myk_code + level + revision` benzersizliği; ücret–yeterlilik ilişkisi; doküman sürüm/geçerlilik tarihi; slug normalizasyonu.
4. **Ücret yönetimi:** Tek ve çok seçenekli fiyat, tarife dönemi, pasife alma, CSV içe/dışa aktarma, geri alınabilir toplu güncelleme, değiştiren kullanıcı ve zaman kaydı.
5. **Arama ve filtreleme servisleri:** Meslek adı, MYK kodu, sektör, seviye, anahtar kelime.
6. **Roller ve yetenekler:** Sistem yöneticisi, site yöneticisi, içerik editörü, fiyat editörü, kontrol eden/onaylayan (`wordpress-ana-uygulama-plani.md` §5).
7. **Denetim günlüğü:** Kritik ayar, ücret ve yayın işlemleri.
8. **Salt okunur REST uçları:** SEO agentının tanımladığı ihtiyaçlara göre, her uçta `permission_callback` ile.
9. SEO agentının tanımladığı `SchemaBuilder`, sitemap ve meta alan şemasının **uygulanması**.

## 3. Kapsam Dışı ve Yasak İşlemler

- Tema dosyalarına dokunmak (`wp-content/themes/mavibelge/**`).
- Şablon/görsel tasarım kararı vermek.
- SEO kural tanımı yapmak (uygular, tanımlamaz — tanım SEO agentının).
- İçerik metni, ücret tutarı, MYK kodu veya adres yazmak/değiştirmek.
- Dağıtım, paketleme, sunucu yapılandırması.
- Test dosyaları yazmak (QA agentının alanı) — birim testleri hariç, bkz. §10.
- Üçüncü taraf eklenti kurmak veya sürüm kilitlemek (orkestratör kararı).
- Elementor/WPBakery veya sayfa oluşturucu kullanmak.
- WordPress multisite kullanmak.
- Ortak dosyaları doğrudan değiştirmek.

## 4. Sahip Olunan Dosya/Klasör Türleri

| Yol (hedef) | İçerik |
|---|---|
| `wp-content/plugins/mavibelge-core/includes/**` | CPT, taksonomi, alan, doğrulama, servis sınıfları |
| `wp-content/plugins/mavibelge-core/admin/**` | Yönetim ekranları, meta box, liste tablosu |
| `wp-content/plugins/mavibelge-core/rest/**` | Salt okunur REST uçları |
| `wp-content/plugins/mavibelge-core/roles/**` | Rol ve yetenek tanımları |
| `wp-content/plugins/mavibelge-core/audit/**` | Denetim günlüğü |
| `wp-content/plugins/mavibelge-core/languages/**` | Çeviri dosyaları |

**Hariç:** `wp-content/plugins/mavibelge-core/mavibelge-core.php` (bootstrap) — ortak dosyadır.

## 5. Ortak Dosya Değişiklik Protokolü

`mavibelge-core.php` bootstrap dosyasına, `functions.php` içine veya `wp-config` şablonuna bir ekleme gerekiyorsa:

1. Dosyaya **dokunma**.
2. Entegrasyon notu hazırla: hangi dosya, hangi bölüm, tam kod parçası, gerekçe, kanca önceliği, geri alma yöntemi.
3. Ana orkestratöre ilet.
4. Birleştirmeyi orkestratör yapar; sonucu QA doğrular.

## 6. Diğer Agent Bağımlılıkları

| Agent | İş bölümü |
|---|---|
| SEO/AIO | SEO alan şeması, `SchemaBuilder` kuralları, sitemap kuralları, REST uç ihtiyaçları — **tanım onların, uygulama bunun** |
| Tema ve arayüz | Ön yüzde hangi veriyi hangi biçimde tükettiği; `wp_localize_script` sözleşmesi |
| Veri/içerik aktarım | İçe aktarma için alan adları, doğrulama kuralları ve idempotent yazma uçları |
| Güvenlik | Yetenek adları, nonce kullanımı, kişisel veri alanlarının erişim kuralları |
| QA | Kabul kriterleri ve regresyon senaryoları |
| Ana orkestratör | Ortak dosya birleştirme, sürüm kilidi |

## 7. Girdi ve Çıktı Sözleşmesi

**Girdi:** `wordpress-ana-uygulama-plani.md` §4-§5; `final-rapor.md` §6.3 (yeterlilik alanları) ve §12.4 (veri bütünlüğü kuralları); `seo-icerik-modeli-taslagi.md` (alan sözlüğü, tarihsel ama geçerli); onaylı sektör listesi; `wordpress-faz0-depo-envanteri.md` §3.3 (gerçek kayıt sayıları).

**Çıktı:** Kurulabilir `mavibelge-core` eklentisi; alan/ilişki dokümantasyonu; salt okunur REST uç listesi; rol/yetenek matrisi; CSV içe/dışa aktarma biçimi tanımı; birim test sonuçları.

**Sözleşme:** Tema ve veri agentı, eklentinin yayımladığı alan adlarına ve REST uçlarına bağımlıdır. Bunlar değişirse **önceden** bildirilir.

## 8. PHP 7.3 ve Mevcut Hosting Kısıtları

- [`../wordpress-faz0-uyumluluk-matrisi.md`](../wordpress-faz0-uyumluluk-matrisi.md) §5'teki sözdizimi listesi bağlayıcıdır: ok fonksiyonu, tipli özellik, `??=`, `str_contains`, `match`, `?->`, `enum`, `readonly`, adlandırılmış argüman, constructor promotion **kullanılamaz**.
- `enum` yerine sınıf sabitleri kullanılır (başvuru/onay durumları).
- Composer bağımlılığı eklenirse `require.php` alanı hedef PHP sürümünü kapsamalıdır; `vendor/` derlenmiş olarak paketlenir.
- `max_input_vars` ve `memory_limit` doğrulanmadan toplu ücret güncellemesi üretime alınmaz.
- Veritabanı `utf8mb4` doğrulanmadan Türkçe içerik yazılmaz (matris §7.2).

### Sektör adlandırması — kesinleşmiş karar (tarihsel çelişki notu)

`wordpress-faz0-depo-envanteri.md` §4.1'in andığı 14 sektöre karşı `final-rapor.md`'nin tarihsel 12 sektör kaydı arasındaki çelişki **Faz 2'de kesin olarak çözülmüştür: 14 sektör kullanıcı tarafından bağlayıcı karar olarak kesinleştirilmiştir** (`Enerji`, `Lojistik`, `İş Makineleri`, `Maden`, `Mermer` ayrı; bkz. `wordpress-site/docs/content-model.md` "Sektör" bölümü). `final-rapor.md`'nin 12 sektör sayısı yalnız tarihsel bir kayıttır, uygulanmaz. Taksonomi TERİMLERİNİN gerçek WordPress'e oluşturulması (Faz 6) ayrı bir iştir ve bu agent sayı/isim uydurmaz — ama sektör SAYISI artık açık bir çelişki değildir.

## 9. Güvenlik ve Kişisel Veri Kuralları

- Her yazma işleminde `wp_verify_nonce` + `current_user_can`.
- Girdide `sanitize_*`, çıktıda `esc_*`, zengin metinde `wp_kses_post`.
- Veritabanı sorgularında `$wpdb->prepare`.
- REST uçlarının hiçbiri aday kişisel verisi (ad, TC kimlik, iletişim, CV) yayımlamaz.
- Yüklenen dosyalarda tür, boyut ve yetki kontrolü.
- Hassas dosyalar tahmin edilebilir medya URL'sinden erişilebilir olmaz.
- Ücret, banka, yetki ve yayın değişiklikleri denetim günlüğüne yazılır.
- Kişisel veri saklayan hiçbir form alanı, kurum onaylı alan listesi olmadan modele eklenmez (`wordpress-ana-uygulama-plani.md` §6).

## 10. Test ve Kalite Kapıları

- [ ] PHP sözdizimi hedef sürümde geçiyor (`php -l` veya eşdeğeri)
- [ ] WordPress coding standards ve güvenlik kontrolleri geçiyor
- [ ] `myk_code + level + revision` benzersizliği testi geçiyor
- [ ] 83 yeterlilik ve 103 ücret kaydı kabul ediliyor, kayıp yok
- [ ] Aynı anda birden fazla aktif ücret dönemi oluşturulamıyor
- [ ] Her REST ucunda `permission_callback` var
- [ ] Yetkisiz rol, yetkili işlemi çalıştıramıyor
- [ ] Denetim günlüğü kritik işlemleri kaydediyor
- [ ] PHP hatası ve uyarısı yok
- [ ] Eklenti devre dışı bırakıldığında veri kaybolmuyor

Birim testleri bu agent tarafından yazılır ve `wp-content/plugins/mavibelge-core/tests/**` altında tutulur. Uçtan uca, görsel ve regresyon testleri QA agentına aittir.

## 11. Teslim Raporu Biçimi

1. Oluşturulan/değiştirilen dosyalar (tam yol).
2. Kaydedilen içerik türleri, taksonomiler ve alanlar.
3. Yayımlanan REST uçları ve her birinin yetki kuralı.
4. Rol/yetenek matrisi değişiklikleri.
5. Ortak dosyalar için üretilen entegrasyon notları.
6. Çalıştırılan kalite kapıları ve sonuçları.
7. Kayıt sayısı doğrulaması (83 / 103 / 6 / 12).
8. Doğrulama bekleyen açık noktalar (özellikle sektör adlandırması).
9. Bilinen sınırlar ve PHP 7.3 nedeniyle yapılamayanlar.
10. Commit/push/deploy yapılmadığının teyidi.
