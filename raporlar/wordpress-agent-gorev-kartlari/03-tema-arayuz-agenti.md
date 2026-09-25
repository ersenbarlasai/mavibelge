# Görev Kartı — Tema ve Arayüz Agentı

> Merkezi kayıt: [`../wordpress-agent-mimarisi.md`](../wordpress-agent-mimarisi.md) · Bağlayıcı plan: [`../wordpress-ana-uygulama-plani.md`](../wordpress-ana-uygulama-plani.md) · Çalışma kuralları: [`../../AGENTS.md`](../../AGENTS.md)
>
> Buradaki WordPress yolları **henüz mevcut değildir**; gelecekteki hedef yol/desenlerdir.

## 1. Amaç

Kullanıcı tarafından görsel olarak kabul edilmiş statik tanıtım sitesini, hazır tema ve sayfa oluşturucu kullanmadan özel `mavibelge` WordPress temasına dönüştürmek.

## 2. Sorumluluklar

1. **Tasarım sistemi:** `tanitim-site/assets/css/tokens.css` (60 satır) token'larını temaya taşımak.
2. **Ortak kabuk:** 41 sayfaya kopyalanmış header/footer'ı tek `header.php` + `footer.php` + kayıtlı `nav_menu` yapısına indirmek.
3. **Şablonlar:** 41 statik sayfanın WordPress karşılıkları — `front-page.php`, `page.php`, arşiv ve tekil şablonlar, `taxonomy-sektor.php`, `single-yeterlilik.php`, `404.php`, `search.php`.
4. **Bileşenler:** Görev kartları, meslek/ücret tabloları, filtre arayüzü, referans slider'ı, haber kartları, sayaç bandı.
5. **Gutenberg blok stilleri.** Sayfa oluşturucu kullanılmaz; ana sayfa kontrollü alanlardan yönetilir ve editör temel yerleşimi bozamaz.
6. **Erişilebilirlik:** WCAG 2.2 AA hedefi — tek anlamlı H1, doğru başlık sırası, klavye erişimi, görünür odak, `<label>`, form hata özeti, kontrast, 44×44 hedefler, içeriğe atlama bağlantısı, hareket azaltma.
7. **Responsive:** 375, 390, 768, 1024 ve 1440 piksel; mobilde yatay taşma sıfır.
8. **Varlık yönetimi:** `wp_enqueue_style` / `wp_enqueue_script`; derlenmiş, üretime hazır CSS/JS.
9. **Performans:** WebP + `srcset`, kritik olmayan JS'in ertelenmesi, düşük bağımlılık.

## 3. Kapsam Dışı ve Yasak İşlemler

- `mavibelge-core` eklenti kodunu değiştirmek.
- İçerik türü, taksonomi, alan veya veritabanı kararı vermek.
- İçerik metni, ücret tutarı, MYK kodu, adres veya akreditasyon numarası yazmak/değiştirmek.
- SEO alan/şema/robots **kural tanımı** yapmak (uygular, tanımlamaz).
- `tanitim-site/**` içinde değişiklik. Statik site **yalnız okunur kaynaktır**.
- Hazır pazar teması, tema çocuğu veya Elementor/WPBakery kullanmak.
- jQuery veya gereksiz dış kütüphane eklemek. Statik sitede dış JS kütüphanesi **yoktur**; bu korunur.
- Dağıtım, paketleme, sunucu yapılandırması.
- Ortak dosyaları doğrudan değiştirmek.

## 4. Sahip Olunan Dosya/Klasör Türleri

| Yol (hedef) | İçerik |
|---|---|
| `wp-content/themes/mavibelge/*.php` | Şablon dosyaları (`functions.php` hariç) |
| `wp-content/themes/mavibelge/template-parts/**` | Şablon parçaları |
| `wp-content/themes/mavibelge/inc/**` | Tema yardımcıları, enqueue, menü kaydı |
| `wp-content/themes/mavibelge/assets/css/**` | Token ve bileşen CSS'i |
| `wp-content/themes/mavibelge/assets/js/**` | Tema JavaScript'i |
| `wp-content/themes/mavibelge/assets/images/**` | Tema görselleri ve logolar |
| `wp-content/themes/mavibelge/assets/fonts/**` | Yerel font dosyaları (karar verilirse) |
| `wp-content/themes/mavibelge/tests/**` | Tema birim/anlık görüntü testleri |

**Hariç:** `functions.php` ve `style.css` başlık bloğu — ortak dosyalardır.

## 5. Ortak Dosya Değişiklik Protokolü

`functions.php`, `style.css` başlık bloğu, `.htaccess` veya `package.json` değişikliği gerekiyorsa:

1. Dosyaya **dokunma**.
2. Entegrasyon notu hazırla: hangi dosya, hangi bölüm, tam kod parçası, gerekçe, kanca önceliği ve enqueue sırası, geri alma yöntemi.
3. Ana orkestratöre ilet.
4. Birleştirme orkestratörün; doğrulama QA'nın.

## 6. Diğer Agent Bağımlılıkları

| Agent | İş bölümü |
|---|---|
| WordPress çekirdek/eklenti | Hangi alanların ve REST uçlarının mevcut olduğu; `wp_localize_script` veri sözleşmesi |
| SEO/AIO | Semantik HTML, başlık hiyerarşisi, breadcrumb yerleşimi, meta/şema çıktı noktaları — **tanım onların** |
| Veri/içerik aktarım | Medya boyutları, görsel adlandırma, alt metin kaynağı |
| Güvenlik | Form işaretlemesi, çıktı kaçışı, dosya yükleme arayüzü kuralları |
| QA | Görsel karşılaştırma temel çizgisi, responsive ve erişilebilirlik kapıları |
| Ana orkestratör | Ortak dosya birleştirme |

## 7. Girdi ve Çıktı Sözleşmesi

**Girdi (salt okunur):**

| Kaynak | İçerik |
|---|---|
| `tanitim-site/assets/css/tokens.css` | 60 satır tasarım token'ı — doğrudan taşınır |
| `tanitim-site/assets/css/*.css` | 1116 satır CSS |
| `tanitim-site/assets/js/*.js` | 873 satır vanilla JS, 8 dosya |
| `tanitim-site/*.html` | 41 sayfanın işaretlemesi ve menü yapısı |
| `tanitim-site/qa-screenshots/measurements.json` | Ölçüm temel çizgisi |
| `tanitim-site/assets/images/logos/*` | 3 orijinal logo + favicon |

**Çıktı:** Kurulabilir `mavibelge` teması; şablon–statik sayfa eşleştirme tablosu; bileşen kataloğu; erişilebilirlik ve responsive test sonuçları.

**Sözleşme:** Tema, eklentinin yayımladığı alan adlarına ve REST uçlarına bağımlıdır. Eklenti bunları değiştirirse önceden bildirir.

## 8. PHP 7.3 ve Mevcut Hosting Kısıtları

- [`../wordpress-faz0-uyumluluk-matrisi.md`](../wordpress-faz0-uyumluluk-matrisi.md) §4 sözdizimi listesi bağlayıcıdır. Şablon dosyalarında ok fonksiyonu, `??=`, `str_contains`, `match`, `?->` **kullanılmaz**.
- Sunucuda Node.js bulunacağı varsayılmaz. CSS/JS yerelde derlenir, üretime hazır dosya sevk edilir.
- Sunucu tarafında üretilmiş HTML esastır; SPA veya istemci tarafı render kullanılmaz.

### Statik siteden taşınırken düzeltilecek bilinen sorunlar

Kaynak: [`../wordpress-faz0-depo-envanteri.md`](../wordpress-faz0-depo-envanteri.md) §7.

| Sorun | Adet | Yapılacak |
|---|---:|---|
| `http://` sosyal medya bağlantısı | 3 | HTTPS'e çevir; Twitter bağlantısını kuruma teyit ettir |
| `href="#"` yer tutucu | 7 | Gerçek adres kurumdan alınmadan yayına çıkarılmaz |
| Font kaynağı yok | — | Inter yerel `woff2` olarak gömülecek mi, yoksa sistem fontu mu — orkestratör kararı |
| `assets/images/sectors/` boş klasör | — | Taşınmaz |
| `maden` ve `mermer` aynı görseli kullanıyor | — | İçerik agentı kararına bırakılır |

## 9. Güvenlik ve Kişisel Veri Kuralları

- Tüm çıktılarda `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- Şablonlar doğrudan veritabanı sorgusu çalıştırmaz; eklentinin sunduğu servisleri kullanır.
- Form işaretlemesi, güvenlik agentının onayladığı alan listesiyle sınırlıdır. Onaysız alan eklenmez.
- Kişisel veri hiçbir şablonda, örnek içerikte veya anlık görüntü testinde yer almaz.
- Dış kaynaklı script/stylesheet eklenmez. Google Fonts dahil her dış istek gizlilik açısından ayrı onaya tabidir.

## 10. Test ve Kalite Kapıları

- [ ] PHP sözdizimi hedef sürümde geçiyor
- [ ] WordPress coding standards geçiyor
- [ ] Tarayıcı konsolunda hata yok
- [ ] 375 / 390 / 768 / 1024 / 1440 px'te yatay taşma yok
- [ ] Her sayfada tek ve anlamlı H1
- [ ] Klavye ile tam gezinme ve görünür odak
- [ ] Form alanlarının programatik etiketi var
- [ ] Renk kontrastı AA eşiğini geçiyor
- [ ] İçeriğe atlama bağlantısı çalışıyor
- [ ] Anlamlı görsellerde alt metin var
- [ ] aXe/Lighthouse temel kontrolleri geçiyor
- [ ] Kırık iç bağlantı ve medya yolu yok
- [ ] Statik referansla görsel karşılaştırma kabul edilmiş

## 11. Teslim Raporu Biçimi

1. Oluşturulan/değiştirilen dosyalar (tam yol).
2. Şablon–statik sayfa eşleştirme tablosu (41 sayfanın karşılığı).
3. Yeniden kullanılan ve yeniden yazılan CSS/JS oranı.
4. Ortak dosyalar için üretilen entegrasyon notları.
5. Responsive ve erişilebilirlik test sonuçları (kanıtlı).
6. Statik referansla görsel fark özeti.
7. Düzeltilen ve düzeltilemeyen bilinen sorunlar (§8 tablosu).
8. Doğrulama bekleyen açık noktalar.
9. PHP 7.3 nedeniyle yapılamayanlar.
10. Commit/push/deploy yapılmadığının teyidi.
