# Agent Mimarisi — Merkezi Kayıt

> Bu belge, Mavi Belge'nin Laravel + Filament aşamasında çalışacak agentların **merkezi kayıt ve yönetişim belgesi**dir. Görev alanı bağlantıları buradan başlar. Bu belge ayrıntılı görev kartı üretmez; yalnızca hangi agentın var olduğunu, sınırını ve durumunu kaydeder.
>
> Şu an yalnızca `seo-aio-agent` için ayrıntılı görev kartı hazırlanmıştır: [`seo-aio-agent-gorev-karti.md`](./seo-aio-agent-gorev-karti.md). Diğer agentlar için burada yazılanlar **taslak kapsam bildirimidir**, görev kartı değildir; ayrıntı bu görevin kapsamı dışındadır ve uydurulmamıştır.
>
> Temel karar kaynağı: [`final-rapor.md`](./final-rapor.md). Çelişki halinde `final-rapor.md` esas alınır.

## Yönetişim Kuralı

- Bir agent, başka bir agentın sahip olduğu dosyayı değiştirmeden önce görev kartı veya entegrasyon notu oluşturur.
- **Ortak dosyaların birleştirilmesi (ör. ana layout, `.htaccess`/middleware, ortak config) yalnızca ana orkestratör tarafından yapılır.** Hiçbir agent tek taraflı birleştirme yapmaz.
- Yeni bir agent eklendiğinde veya durumu değiştiğinde bu tablo güncellenir; ayrıntı ilgili agentın kendi görev kartına yazılır, burada tekrar edilmez.

## Agent Listesi

| Agent | Kısa amaç | Sahip olduğu alan | Değiştirmemesi gereken alan | Birlikte çalıştığı agentlar | Durum |
|---|---|---|---|---|---|
| Ana orkestratör | Agentlar arası iş bölümünü ve ortak dosya birleştirmesini yönetir | Ortak dosya entegrasyonu, agent atama/önceliklendirme | Hiçbir agentın kendi modül detayına tek taraflı müdahale etmez | Tüm agentlar | Görev kartı hazırlanacak |
| Arayüz/tasarım agentı | Ön yüz bileşenleri, semantik HTML, erişilebilirlik, performans uygulaması | Blade bileşenleri, tasarım token/CSS, sayfa şablonları | Backend servis mantığı, veri şeması, SEO alan tanımı | seo-aio-agent, backend agentı | Görev kartı hazırlanacak |
| Backend agentı | Laravel iş mantığı, servisler, entegrasyonlar | Controller/servis katmanı, sitemap/redirect/SchemaBuilder servis kodu, veri uçları | Veritabanı şeması tasarımı, ön yüz bileşen kodu | seo-aio-agent, veritabanı agentı, arayüz agentı | Görev kartı hazırlanacak |
| Veritabanı agentı | Şema, migration, ilişki ve indeks tasarımı | Migration dosyaları, tablo/ilişki/indeks kararları | İçerik/SEO alan değerleri, iş mantığı | Backend agentı, seo-aio-agent | Görev kartı hazırlanacak |
| İçerik agentı | Başlık, açıklama, kaynak, tarih ve içerik doğruluğu; yayın bilgisi (yazar/kontrol eden/yayın tarihi) sahipliği | İçerik metni, `published_at`/`content_updated_at`/`author_id`/`reviewer_id`/`reviewed_at`/`approval_status` | SEO teknik alanları, şema üretim mantığı | seo-aio-agent | Görev kartı hazırlanacak |
| Güvenlik agentı | Crawler erişimi, WAF/bot politikası, rate limit, kişisel veri güvenliği | Güvenlik kuralları, bot doğrulama süreci, kişisel veri kontrolü | SEO içerik kararları, tasarım | seo-aio-agent, DevOps/hosting agentı | Görev kartı hazırlanacak |
| DevOps/hosting agentı | Yönlendirme altyapısı, sıkıştırma, önbellek, SSL, sunucu logları | Hosting/CDN/WAF yapılandırması, sunucu erişim kayıtları | İçerik, SEO alan tanımı, veri şeması | seo-aio-agent, güvenlik agentı, backend agentı | Görev kartı hazırlanacak |
| **seo-aio-agent** | SEO, AIO/GEO ve yapay zekâ ajanı uyumluluğu | robots/sitemap kuralları, SEO meta şeması, JSON-LD/SchemaBuilder tasarımı, crawler politikası, SEO kalite kapıları | `tanitim-site` dosyaları, veritabanı şeması, hosting/WAF yapılandırması, içerik metni/fiyat/MYK kodu | Arayüz, backend, veritabanı, içerik, güvenlik, DevOps agentları | **Görev kartı hazır** — [`seo-aio-agent-gorev-karti.md`](./seo-aio-agent-gorev-karti.md) |

## Sınırlar

- Bu belge Laravel kurulumu, migration veya kod içermez; yalnızca kayıt ve yönetişimdir.
- "Görev kartı hazırlanacak" satırlarındaki alan/sınır bilgileri taslak niteliğindedir; ilgili agentın görev kartı yazılırken genişletilebilir veya değişebilir.
- Bu belge `tanitim-site` içeriğini değiştirmez.
