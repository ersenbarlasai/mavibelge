# Görev Kartı — Ana Orkestratör

> Merkezi kayıt: [`../wordpress-agent-mimarisi.md`](../wordpress-agent-mimarisi.md) · Bağlayıcı plan: [`../wordpress-ana-uygulama-plani.md`](../wordpress-ana-uygulama-plani.md) · Çalışma kuralları: [`../../AGENTS.md`](../../AGENTS.md)
>
> Buradaki WordPress yolları **henüz mevcut değildir**; gelecekteki hedef yol/desenlerdir.

## 1. Amaç

Fazları sırayla yürütmek, agentlar arası iş bölümünü korumak, ortak dosyaları tek elden birleştirmek ve proje hafızasını güncel tutmak. Kod yazmak birincil işi değildir; **entegrasyon ve karar kaydı** birincil işidir.

## 2. Sorumluluklar

1. Faz sırasını yürütmek; bir fazın kabul kriterleri geçmeden sonrakini başlatmamak.
2. Her Claude görevi için izin verilen dosyaları, yasak alanları, girdileri, testleri ve teslim raporu biçimini yazmak.
3. **Ortak dosyaları birleştirmek** (bkz. §5). Bu yetki yalnız ondadır.
4. Agentlardan gelen entegrasyon notlarını çakışma açısından incelemek ve sıralamak.
5. `raporlar/proje-durumu.md` dosyasını her iş paketi sonunda güncellemek.
6. `AGENTS.md` ve `README.md` bağlantı/özet bakımını yapmak.
7. Agent sahiplik çakışması ortaya çıkarsa sahipliği yeniden tanımlayıp merkezi kayda işlemek.
8. Kullanıcı kararlarını (sektör adlandırması, referans logoları, form alanları, PHP sürümü) kayda geçirmek ve doğrulanmadan uygulanmasını engellemek.
9. Commit/push/deploy için kullanıcı onayını almak ve onay olmadan yapılmamasını sağlamak.

## 3. Kapsam Dışı ve Yasak İşlemler

- Bir agentın kendi modül içine tek taraflı kod müdahalesi.
- `tanitim-site/**` içinde herhangi bir değişiklik.
- `raporlar/wordpress-ana-uygulama-plani.md`, `final-rapor.md`, `agent-mimarisi.md`, `seo-aio-agent-gorev-karti.md`, `seo-icerik-modeli-taslagi.md` dosyalarını değiştirmek.
- Mevcut `CLAUDE_*.md` ve `STITCH_*.md` dosyalarını değiştirmek.
- Canlı sunucu, DNS, SSL, DirectAdmin, FTP veya mail ayarlarına dokunmak.
- `.gitignore`, Git geçmişi, remote veya branch değiştirmek.
- Kullanıcı onayı olmadan commit, push, tag, release veya deploy.
- Takip edilmeyen kullanıcı dosyalarını stage etmek.

## 4. Sahip Olunan Dosya/Klasör Türleri

| Yol | Not |
|---|---|
| `AGENTS.md` | Proje çalışma kuralları |
| `README.md` | Proje özeti ve bağlantılar |
| `raporlar/proje-durumu.md` | Oturum hafızası |
| `raporlar/wordpress-agent-mimarisi.md` | Agent merkezi kaydı |
| `raporlar/wordpress-agent-gorev-kartlari/**` | Görev kartları |
| §5'teki tüm ortak dosyalar | Yalnız birleştirme yetkisi |

## 5. Ortak Dosya Değişiklik Protokolü

Ortak dosyaların **tek birleştiricisidir**. Diğer agentlardan gelen entegrasyon notlarını şu adımlarla işler:

1. Notu al: hangi dosya, hangi bölüm, tam kod parçası, gerekçe, sıra bağımlılığı, geri alma yöntemi.
2. Aynı dosyaya gelen notlar arasında çakışma var mı kontrol et.
3. Çakışma varsa ilgili agentlardan gerekçe iste; çözümü kendisi kararlaştırır ve kayda geçirir.
4. Çalıştırma sırasını belirle (özellikle `functions.php` kanca öncelikleri ve `.htaccess` kural sırası).
5. Tek elden uygula.
6. QA agentından ilgili kalite kapılarını yeniden çalıştırmasını iste.
7. Kararı `raporlar/proje-durumu.md` içine yaz.

**Ortak dosyalar:** `wp-content/themes/mavibelge/functions.php`, `wp-content/themes/mavibelge/style.css` (başlık bloğu), `wp-content/plugins/mavibelge-core/mavibelge-core.php`, `.htaccess`, `robots.txt`, `wp-config-sample-mavibelge.php`, `composer.json`, `package.json`, `README.md`, `AGENTS.md`, `raporlar/proje-durumu.md`.

## 6. Diğer Agent Bağımlılıkları

Tüm agentlar. Hiçbir agentın çıktısını kendisi üretmez; birleştirir ve sıralar.

## 7. Girdi ve Çıktı Sözleşmesi

**Girdi:** Kullanıcı kararları; `wordpress-ana-uygulama-plani.md`; `wordpress-faz0-uyumluluk-matrisi.md`; `wordpress-faz0-depo-envanteri.md`; agentlardan gelen entegrasyon notları ve teslim raporları.

**Çıktı:** Birleştirilmiş ortak dosyalar; güncel `proje-durumu.md`; bir sonraki iş paketinin görev tanımı; karar kaydı.

## 8. PHP 7.3 ve Mevcut Hosting Kısıtları

- Üretim PHP sürümü doğrulanana kadar hedef **PHP 7.3**'tür ([`../wordpress-faz0-uyumluluk-matrisi.md`](../wordpress-faz0-uyumluluk-matrisi.md) §4).
- Sürüm kilidi (WordPress ve eklentiler) yalnız orkestratör tarafından ve **ancak** matristeki `Doğrulanamadı` satırları kapandıktan sonra verilir.
- PHP 7.3'ün güncel WordPress'i çalıştıramadığı bulgusu (matris §3.3) her faz başında yeniden değerlendirilir; çözülmeden Faz 12 (staging dağıtımı) başlatılmaz.
- Sunucuda Composer, Node.js veya Git bulunacağı varsayılmaz.

## 9. Güvenlik ve Kişisel Veri Kuralları

- Parola, özel anahtar, `.env`, FTP/SSH/DirectAdmin kimlik bilgisi hiçbir belgeye, koda veya rapora yazılmaz.
- Yanlışlıkla görülürse rapora yalnız "gizli bilgi bulundu; güvenli konuma taşınmalı" notu düşülür, değeri yazılmaz.
- Aday kişisel verisi hiçbir raporda, örnek veride veya test fixture'ında bulunmaz.
- Ortak dosya birleştirmelerinde güvenlik agentının onayı alınmadan güvenlik sabiti (`DISALLOW_FILE_EDIT` vb.) kaldırılmaz.

## 10. Test ve Kalite Kapıları

Her birleştirme sonrası:

- [ ] PHP sözdizimi hedef sürümde geçiyor
- [ ] WordPress coding standards kontrolü geçiyor
- [ ] Kanca öncelik sırası bozulmamış
- [ ] QA agentının regresyon seti geçiyor
- [ ] Sahiplik çakışması yok
- [ ] Laravel/Filament kararı taşınmamış
- [ ] `tanitim-site/**` değişmemiş
- [ ] `git diff --check` temiz
- [ ] Gizli bilgi yazılmamış

## 11. Teslim Raporu Biçimi

1. İşlenen entegrasyon notları ve kaynak agentlar.
2. Birleştirilen ortak dosyalar ve her birinde ne değişti.
3. Çözülen çakışmalar ve verilen karar.
4. Çalıştırılan kalite kapıları ve sonuçları.
5. `proje-durumu.md` içinde güncellenen başlıklar.
6. Doğrulama bekleyen açık noktalar.
7. Bir sonraki güvenli iş paketinin adı.
8. Commit/push/deploy yapılmadığının teyidi (veya kullanıcı onayının kaydı).
