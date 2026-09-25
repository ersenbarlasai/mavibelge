# AGENTS.md — Mavi Belge Projesi Çalışma Kuralları

> Her yeni Claude görevi **önce** [`raporlar/proje-durumu.md`](raporlar/proje-durumu.md) dosyasını okur. Bu zorunludur.
>
> Bu belge ayrıntılı planı tekrar etmez; doğru belgeye yönlendirir.

---

## 1. Yetkili Kaynak Sırası

Çelişki halinde aşağıdaki sıra geçerlidir:

1. **Kullanıcının o görevdeki açık talimatları.**
2. [`raporlar/wordpress-ana-uygulama-plani.md`](raporlar/wordpress-ana-uygulama-plani.md) — güncel ve bağlayıcı mimari karar.
3. [`README.md`](README.md) — güncel proje özeti.
4. [`tanitim-site/`](tanitim-site/) — kullanıcı tarafından görsel kabul verilmiş statik referans ve doğrulanmış veriler.
5. [`raporlar/final-rapor.md`](raporlar/final-rapor.md) ve Laravel/Filament belgeleri — **yalnız tarihsel araştırma ve içerik kaynağı; mimari otorite değildir.**

### 1.1. Tarihsel belgeler — uygulanmayacak kararlar

| Belge | Statü |
|---|---|
| [`raporlar/final-rapor.md`](raporlar/final-rapor.md) | İçerik/URL/erişilebilirlik bulguları geçerli. §11, §12, §13 (Laravel/Filament mimarisi) **uygulanmaz**. |
| [`raporlar/agent-mimarisi.md`](raporlar/agent-mimarisi.md) | Yerine [`raporlar/wordpress-agent-mimarisi.md`](raporlar/wordpress-agent-mimarisi.md) geçti. |
| [`raporlar/seo-aio-agent-gorev-karti.md`](raporlar/seo-aio-agent-gorev-karti.md) | Crawler politikası, bot doğrulama zinciri, kalite kapıları geçerli. Blade/Filament referansları uygulanmaz. |
| [`raporlar/seo-icerik-modeli-taslagi.md`](raporlar/seo-icerik-modeli-taslagi.md) | Alan sözlüğü, doğrulama kuralları, `@graph` yaklaşımı geçerli. Eloquent/migration referansları uygulanmaz. |

**Kural:** Hiçbir agent Laravel, Filament, Blade, queue, yeni sunucu veya farklı hosting kararını güncel karar gibi uygulamaz. Yeni sistem **WordPress**, özel `mavibelge` teması ve özel `mavibelge-core` eklentisidir. Hazır tema ve Elementor/sayfa oluşturucu kullanılmaz.

---

## 2. Ortam Sınırları

| Ortam | Kural |
|---|---|
| `tanitim-site/` (yerel) | **Dondurulmuş.** Yalnız okunur kaynak. Hiçbir agent değiştirmez. |
| `https://yeni.mavibelge.com.tr/` | Kullanıcı tarafından görsel kabul edilmiş statik referans. **Üzerine WordPress kurulmaz, üzerine yazılmaz.** |
| `https://cms-yeni.mavibelge.com.tr/` | WordPress kabul/staging için **planlanan** adres. Oluşturulduğu **varsayılmaz**. Parola korumalı ve `noindex` olacaktır. |
| `https://mavibelge.com.tr/` | Canlı üretim. **Son geçişe kadar değiştirilmez.** |

---

## 3. Agent Sahipliği ve Ortak Dosya Protokolü

Sekiz agent ve tam sahiplik haritası: [`raporlar/wordpress-agent-mimarisi.md`](raporlar/wordpress-agent-mimarisi.md).
Görev kartları: [`raporlar/wordpress-agent-gorev-kartlari/`](raporlar/wordpress-agent-gorev-kartlari/).

**İki bağlayıcı kural:**

1. Bir agent, başka bir agentın sahip olduğu dosyayı **değiştirmez**.
2. **Ortak dosyaları yalnız ana orkestratör birleştirir.** Diğer agentlar dosyaya dokunmaz; entegrasyon notu hazırlar (hangi dosya, hangi bölüm, tam kod parçası, gerekçe, sıra bağımlılığı, geri alma yöntemi) ve orkestratöre iletir.

Ortak dosya listesi: `functions.php`, `style.css` başlık bloğu, `mavibelge-core.php`, `.htaccess`, `robots.txt`, `wp-config` şablonu, `composer.json`, `package.json`, `README.md`, `AGENTS.md`, `raporlar/proje-durumu.md`.

---

## 4. Gizli Bilgi

- `.env`, parola, özel anahtar, FTP/SSH/DirectAdmin kimlik bilgisi **aranmaz, yazılmaz, çıktıya dökülmez.**
- Yanlışlıkla görülürse rapora yalnız "gizli bilgi bulundu; güvenli konuma taşınmalı" notu yazılır. **Değeri asla yazılmaz.**
- `wp-config.php`, parola ve anahtarlar Git'e girmez.
- Aday kişisel verisi (ad, TC kimlik, iletişim, CV) hiçbir rapora, örnek veriye veya test verisine girmez.
- Sunucu çıktıları (hostname, IP, kullanıcı adı, dizin yolu) kamuya açık yere yapıştırılmaz.

---

## 5. Canlı Sistem

Kullanıcının açık onayı olmadan **yapılmaz**:

- Canlı `mavibelge.com.tr`, DNS, SSL, DirectAdmin, FTP veya mail ayarlarında değişiklik.
- Sunucuya SSH/FTP/DirectAdmin bağlantısı.
- Canlı URL'lerde form gönderme veya yönetici girişi denemesi.
- `cms-yeni` subdomain oluşturma.

**Koşulsuz yasak:** İki doğrulanmış kopya oluşmadan sunucudan mail silmek. Mail arşivi, WordPress ve sunucu yedeğinin yerine geçmez.

---

## 6. Git, Commit, Push, Deploy

- Commit, push, tag, release ve deploy **yalnız kullanıcının açık onayıyla** yapılır.
- Takip edilmeyen kullanıcı dosyaları stage edilmez.
- `.gitignore`, Git geçmişi, remote ve branch değiştirilmez.
- Yedekten geri dönüş denenmeden canlı yayın yapılmaz.

### 6.1. Korunacak kullanıcı dosyaları

Aşağıdakiler silinmez, taşınmaz, değiştirilmez, stage edilmez, commit edilmez:

- `CLAUDE_LARAVEL_FAZ0_YONETISIM_VE_HAFIZA_PROMPTU.md`
- `CLAUDE_WORDPRESS_FAZ0_ENVANTER_UYUMLULUK_VE_YONETISIM_PROMPTU.md`
- `tanitim-site/yeni-mavibelge-v1.zip`

---

## 7. Değiştirilmeyecek Alanlar

- `tanitim-site/**`
- `tmp/**`
- `raporlar/wordpress-ana-uygulama-plani.md`
- `raporlar/final-rapor.md`
- `raporlar/incelemeraporu-gpt.md`, `raporlar/incelemeraporu-claude.md`
- `raporlar/agent-mimarisi.md`
- `raporlar/seo-aio-agent-gorev-karti.md`
- `raporlar/seo-icerik-modeli-taslagi.md`
- Mevcut `CLAUDE_*.md` ve `STITCH_*.md` dosyaları
- Logo, görsel, PDF ve ZIP dosyaları
- `.gitignore`

---

## 8. Üretim Kısıtı — Kısa Özet

Ayrıntı: [`raporlar/wordpress-faz0-uyumluluk-matrisi.md`](raporlar/wordpress-faz0-uyumluluk-matrisi.md). Karar kaydı: [`raporlar/karar-kaydi-wordpress-php73.md`](raporlar/karar-kaydi-wordpress-php73.md).

- Üretim: Dell R210-II, CentOS 7 (EOL), DirectAdmin Legacy, görünen PHP 7.3 ve 5.6.
- **PHP 7.3, kullanıcı tarafından kabul edilmiş bağlayıcı üretim kısıtıdır.** Sunucu, işletim sistemi, kontrol paneli ve PHP sürümü şimdilik değiştirilmeyecektir. Bu, çözülmesi beklenen bir engel değil, gerçek teknik riski gizlemeden kabul edilmiş bir sınırdır. Özel kodda PHP 7.3'te bulunmayan sözdizimi/API kullanılmaz (matris §4-§5).
- **Teknik gerçek değişmedi:** Güncel WordPress (7.1) minimum PHP 7.4.0 ister ve PHP 7.3 üzerinde kullanılamaz. PHP 7.3 ile uyumlu son ana hat WordPress 6.9'dur ve bu hat aktif güvenlik bakımında değildir; WordPress 6.9 + PHP 7.3 güncel/uzun vadeli güvenli platform olarak sunulamaz. Hedef WordPress ailesi bu nedenle **koşullu/legacy** kabul edilir; kesin yama sürümü henüz kilitlenmemiştir.
- **Hiçbir WordPress veya eklenti sürümü kilitlenmemiştir.** Kilit, üretim PHP sürümü, uzantılar ve veritabanı sürümü doğrulandıktan sonra verilir.
- Sunucuda Composer, Node.js, Git veya WP-CLI bulunacağı varsayılmaz.
- CentOS 7 ve PHP 7.3 EOL riskleri **yüksek** olarak kayıtta açık tutulur; gizlenmez. Kullanıcının mevcut ortamı koruma kararı buna rağmen geçerlidir.

---

## 9. Faz 0 Belgeleri

| Belge | İçerik |
|---|---|
| [`raporlar/wordpress-faz0-depo-envanteri.md`](raporlar/wordpress-faz0-depo-envanteri.md) | Depo ve statik site envanteri, yeniden doğrulanmış sayılar |
| [`raporlar/wordpress-faz0-uyumluluk-matrisi.md`](raporlar/wordpress-faz0-uyumluluk-matrisi.md) | Üretim uyumluluk kararları ve sürüm doğrulama kayıtları |
| [`raporlar/wordpress-sunucu-bilgi-talebi.md`](raporlar/wordpress-sunucu-bilgi-talebi.md) | Aysima talebi + sistem yöneticisi salt okunur kontrol listesi |
| [`raporlar/wordpress-agent-mimarisi.md`](raporlar/wordpress-agent-mimarisi.md) | Sekiz agentın merkezi kaydı ve sahiplik haritası |
| [`raporlar/wordpress-agent-gorev-kartlari/`](raporlar/wordpress-agent-gorev-kartlari/) | Sekiz agentın görev kartı |
| [`raporlar/proje-durumu.md`](raporlar/proje-durumu.md) | **Her görevin önce okuyacağı durum kaydı** |
