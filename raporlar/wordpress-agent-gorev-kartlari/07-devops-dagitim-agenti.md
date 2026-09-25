# Görev Kartı — DevOps/Dağıtım Agentı

> Merkezi kayıt: [`../wordpress-agent-mimarisi.md`](../wordpress-agent-mimarisi.md) · Bağlayıcı plan: [`../wordpress-ana-uygulama-plani.md`](../wordpress-ana-uygulama-plani.md) · Çalışma kuralları: [`../../AGENTS.md`](../../AGENTS.md)
>
> Buradaki WordPress yolları **henüz mevcut değildir**; gelecekteki hedef yol/desenlerdir.

## 1. Amaç

Geliştirme, kabul ve üretim ortamlarını ayrı tutmak; paketlemeyi, yüklemeyi, yedeklemeyi ve geri dönüşü belgelenmiş ve tekrarlanabilir hale getirmek.

## 2. Sorumluluklar

1. **Ortam ayrımı** (`wordpress-ana-uygulama-plani.md` §11):

   | Ortam | Kural |
   |---|---|
   | Yerel geliştirme | Üretim PHP ve veritabanı sürümünü taklit eder |
   | `yeni.mavibelge.com.tr` | Onaylanan statik görsel referans — **WordPress kurulmaz, üzerine yazılmaz** |
   | `cms-yeni.mavibelge.com.tr` | WordPress kabul/staging — parola korumalı, `noindex`, gerçek form gönderimi kapalı |
   | `mavibelge.com.tr` | Üretim — kabul ve geri dönüş planından sonra değiştirilir |

2. **Paketleme:** Tema ve eklenti yerelde derlenir; üretime **çalışır paket** gönderilir. Sunucuda Composer, Node.js veya Git bulunacağı varsayılmaz.
3. **Yükleme:** SFTP veya DirectAdmin üzerinden. Şifreler ve `wp-config.php` Git'e girmez.
4. **Yedekleme** (`wordpress-ana-uygulama-plani.md` §10): Veritabanı günlük; `wp-content/uploads` günlük artımlı + haftalık tam; tema/eklenti Git + dağıtım paketi; en az iki harici disk dönüşümlü; bir kopya fiziksel olarak ayrı; aylık örnek geri yükleme testi.
5. **Geri dönüş paketi:** Her dağıtım için belgelenmiş, denenmiş rollback.
6. **Ortak dosya uygulaması:** SEO ve güvenlik agentlarının tanımladığı `.htaccess` ve `robots.txt` kurallarını, orkestratörün birleştirdiği biçimde ortama uygular.
7. **SSL ve HTTPS:** Sertifika durumu, otomatik yenileme, `cms-yeni` için sertifika.
8. **Cron:** Gerçek sistem cron kullanımı; WP-Cron'un düşük trafikte güvenilmezliği.
9. **Önbellek ve performans altyapısı:** Sayfa/tarayıcı önbelleği, sıkıştırma. LiteSpeed kullanılıyorsa sunucu düzeyi önbellekle eklenti önbelleğinin çakışmaması.
10. **Sunucu bilgi toplama koordinasyonu:** [`../wordpress-sunucu-bilgi-talebi.md`](../wordpress-sunucu-bilgi-talebi.md) yanıtlarının toplanması ve uyumluluk matrisine işlenmesi.
11. **Canlıya geçiş:** Bakım penceresi, son senkronizasyon, 301'lerin devreye alınması, form/mail testleri, geri dönüş hazırlığı.

## 3. Kapsam Dışı ve Yasak İşlemler

- Uygulama kodu (tema/eklenti) yazmak veya değiştirmek.
- İçerik, ücret, MYK kodu veya SEO kural tanımı yapmak.
- **`yeni.mavibelge.com.tr` üzerine WordPress kurmak veya üzerine yazmak.** Onaylanmış statik referans korunur.
- **Kullanıcının açık onayı olmadan** canlı `mavibelge.com.tr` sitesinde, DNS'te, SSL'de, DirectAdmin'de veya mail ayarlarında değişiklik yapmak.
- **İki doğrulanmış kopya oluşmadan sunucudan mail silmek.**
- Kullanıcı talimatına aykırı olarak işletim sistemi yükseltmesi, kontrol paneli değişikliği veya yeni sunucu dayatmak.
- Otomatik büyük sürüm yükseltmesi açmak.
- `wp-config.php`, parola veya özel anahtarı Git'e eklemek.
- Yedekten geri dönüş denenmeden canlı yayın yapmak.
- Ortak dosyaları kendi başına birleştirmek (uygular, birleştirmez).

## 4. Sahip Olunan Dosya/Klasör Türleri

| Yol (hedef) | İçerik |
|---|---|
| `deploy/**` | Paketleme ve yükleme betikleri |
| `deploy/ortamlar/**` | Ortam yapılandırma şablonları (**gizli değer içermez**) |
| `deploy/yedek/**` | Yedekleme ve geri yükleme prosedürleri |
| `deploy/rollback/**` | Geri dönüş paketleri ve prosedürleri |
| `raporlar/dagitim-raporlari/**` | Dağıtım ve smoke test raporları |

## 5. Ortak Dosya Değişiklik Protokolü

Bu agent, ortak dosyaların **uygulayıcısıdır ama birleştiricisi değildir**.

1. `.htaccess`, `robots.txt` veya `wp-config` şablonunda değişiklik gerekiyorsa, dosyaya **doğrudan dokunma**.
2. Entegrasyon notu hazırla: hangi dosya, hangi bölüm, tam kural metni, gerekçe, kural sırası, ortam farkı (staging/prod), geri alma yöntemi.
3. Ana orkestratöre ilet. Orkestratör SEO ve güvenlik notlarıyla birlikte birleştirir.
4. Birleştirilmiş sonucu ortamlara **sen uygularsın**.
5. QA doğrular. Sonuç `raporlar/proje-durumu.md` içine yazılır.

## 6. Diğer Agent Bağımlılıkları

| Agent | İş bölümü |
|---|---|
| Ana orkestratör | Ortak dosya birleştirme, dağıtım onayı, sürüm kilidi |
| Güvenlik | Sertleştirme kuralları, dosya izinleri, yedek şifreleme, SSL |
| SEO/AIO | Yönlendirme kuralları, `robots.txt` ortam farkı, sunucu logu erişimi |
| QA | Smoke test, dağıtım sonrası regresyon |
| WordPress çekirdek/eklenti + tema | Paketlenecek çıktı ve bağımlılıklar |
| Veri/içerik aktarım | İçe aktarma penceresinin dağıtım sırasındaki yeri |

## 7. Girdi ve Çıktı Sözleşmesi

**Girdi:** [`../wordpress-sunucu-bilgi-talebi.md`](../wordpress-sunucu-bilgi-talebi.md) yanıtları; [`../wordpress-faz0-uyumluluk-matrisi.md`](../wordpress-faz0-uyumluluk-matrisi.md) §2 ve §11; orkestratörden gelen birleştirilmiş ortak dosyalar; tema/eklenti derleme çıktıları.

**Çıktı:** Ortam kurulum prosedürü; dağıtım paketi; yükleme prosedürü; yedekleme prosedürü; **denenmiş** geri dönüş paketi; smoke test raporu; canlıya geçiş görev/sorumluluk listesi.

**Sözleşme:** Hiçbir dağıtım, geri dönüş paketi hazır ve **denenmiş** olmadan yapılmaz.

## 8. PHP 7.3 ve Mevcut Hosting Kısıtları

Bu agent, matristeki `Doğrulanamadı` satırlarını kapatmakla doğrudan sorumludur.

| Kısıt | Etki |
|---|---|
| Sunucuda Composer/Node.js/Git yok sayılır | Derleme yerelde yapılır, çalışır paket gönderilir |
| **PHP 7.3 güncel WordPress'i çalıştıramaz** | Staging kurulumu, PHP 7.4+ doğrulanmadan başlatılmaz (matris §3.3) |
| DirectAdmin CustomBuild ile PHP 7.4+/8.3 kurulabilir mi | **En yüksek öncelikli soru** — sunucu talebi B1 |
| Gerçek sistem cron desteği | Doğrulanmadı — sunucu talebi B9 |
| Disk boş alan ve **inode** | Doğrulanmadı — sunucu talebi B5. Disk dolu görünmese de inode tükenmiş olabilir |
| SSL otomatik yenileme ve `cms-yeni` sertifikası | Doğrulanmadı — sunucu talebi B8 |
| Yedekleme/geri yükleme imkânı | Doğrulanmadı — sunucu talebi B10 |
| CentOS 7 EOL | Kullanıcı kararıyla değiştirilmiyor; risk kaydında açık tutulur |

**Uyarı:** `cms-yeni.mavibelge.com.tr` subdomain'inin oluşturulduğu **varsayılmaz**. Oluşturma, kullanıcının açık onayıyla yapılır.

## 9. Güvenlik ve Kişisel Veri Kuralları

- `wp-config.php`, parola, API anahtarı ve özel anahtar Git'e **girmez**. `deploy/ortamlar/**` yalnız şablon tutar, gerçek değer tutmaz.
- Yedekler şifrelenir ve erişimi sınırlıdır.
- Sunucu çıktıları (hostname, IP, kullanıcı adı, dizin yolu) kamuya açık yere yapıştırılmaz.
- Üretimde WordPress dosya editörü kapalı tutulur.
- Dosya izinleri güvenlik agentının tanımladığı şekilde uygulanır.
- Staging parola korumalı ve `noindex` olur; üretim `robots.txt`'i ile karışmaz.
- Mail arşivleri: **iki doğrulanmış kopya oluşmadan sunucudan mail silinmez.** Mail arşivi WordPress ve sunucu yedeğinin yerine geçmez.

## 10. Test ve Kalite Kapıları

Her dağıtım öncesi:

- [ ] Dosya ve veritabanı yedeği alınmış
- [ ] Geri dönüş paketi hazır ve **denenmiş**
- [ ] Hedef ortamın PHP ve veritabanı sürümü üretimle eş
- [ ] Paket içinde gizli bilgi yok
- [ ] `wp-config.php` Git'te değil

Her dağıtım sonrası:

- [ ] Ana sayfa ve kritik sayfalar 200 dönüyor
- [ ] Kalıcı bağlantılar çalışıyor
- [ ] Medya yolları kırık değil
- [ ] Form gönderimi ve mail teslimi test edilmiş
- [ ] Staging `noindex` ve parola korumalı
- [ ] SSL geçerli ve HTTPS yönlendirmesi çalışıyor
- [ ] 301/410 kuralları doğru kod döndürüyor
- [ ] Cron görevleri çalışıyor
- [ ] PHP hata kaydı temiz
- [ ] QA regresyon seti geçiyor

## 11. Teslim Raporu Biçimi

1. Hedef ortam ve dağıtılan sürüm.
2. Paket içeriği ve derleme çıktısı.
3. Uygulanan ortak dosya kuralları ve kaynak entegrasyon notları.
4. Alınan yedekler (ne, nereye, ne zaman, boyut).
5. Geri dönüş paketi ve **denendiğinin kanıtı**.
6. Smoke test sonuçları (kanıtlı).
7. Sunucu bilgi talebinden gelen yanıtlar ve matriste kapatılan `Doğrulanamadı` satırları.
8. Açık kalan altyapı riskleri.
9. Canlıya geçişte yapılacaklar ve sorumlu kişiler.
10. Gizli bilgi paylaşılmadığının teyidi.
11. Kullanıcı onayının kaydı (canlıya etki eden her işlem için).
