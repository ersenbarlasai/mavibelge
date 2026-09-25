# Görev Kartı — QA Agentı

> Merkezi kayıt: [`../wordpress-agent-mimarisi.md`](../wordpress-agent-mimarisi.md) · Bağlayıcı plan: [`../wordpress-ana-uygulama-plani.md`](../wordpress-ana-uygulama-plani.md) · Çalışma kuralları: [`../../AGENTS.md`](../../AGENTS.md)
>
> Buradaki WordPress yolları **henüz mevcut değildir**; gelecekteki hedef yol/desenlerdir.

## 1. Amaç

Görsel, işlevsel, responsive, erişilebilirlik ve veri doğruluğu testlerini kanıtlı biçimde yürütmek; bir fazın kabul kriterlerinin gerçekten geçtiğini bağımsız olarak doğrulamak.

## 2. Sorumluluklar

1. **Görsel karşılaştırma:** WordPress çıktısını onaylanmış statik referansla karşılaştırmak. Temel çizgi: `tanitim-site/qa-screenshots/measurements.json` ve mevcut ekran görüntüleri.
2. **Responsive testi:** 375, 390, 768, 1024 ve 1440 piksel. Mobilde yatay taşma **sıfır** olmalı.
3. **Erişilebilirlik testi:** Klavye gezinme, ekran okuyucu temel akışı, aXe/Lighthouse taraması, kontrast, odak görünürlüğü, form etiketleri, 44×44 hedefler.
4. **İşlevsel test:** Arama, filtreleme, formlar, dış sistem bağlantıları, doküman indirme, sayfalama.
5. **Veri doğruluğu testi:** 83 yeterlilik, 103 ücret, 14 sektör, 6 haber, 12 referans kaydının kaynakla karşılaştırılması.
6. **Bağlantı ve medya testi:** Kırık iç bağlantı, kırık medya yolu, `href="#"` kalıntısı, `http://` şeması kalıntısı.
7. **Regresyon seti:** Her ortak dosya birleştirmesinden ve her dağıtımdan sonra çalıştırılır.
8. **301/410 otomatik testi:** SEO agentının karar tablosundaki her satırın doğru kodu döndürdüğünü doğrulamak.
9. **Konsol ve hata kaydı denetimi:** Tarayıcı konsolu ve PHP hata kaydı temiz olmalı.
10. **Kabul kanıtı üretmek:** Ekran görüntüsü, ölçüm, komut çıktısı. "Test edildi" ifadesi tek başına kabul edilmez.

## 3. Kapsam Dışı ve Yasak İşlemler

- **Bulduğu hatayı kendisi düzeltmek.** Raporlar; düzeltmeyi sahibi agent yapar.
- Tema, eklenti, içe aktarma veya dağıtım kodunu değiştirmek.
- İçerik, ücret, MYK kodu veya SEO kararı vermek.
- Sunucu yapılandırması veya dağıtım yapmak.
- `tanitim-site/**` içinde değişiklik. Karşılaştırma temel çizgisi **dondurulmuştur**.
- Canlı `mavibelge.com.tr` üzerinde form göndermek veya yönetici girişi denemek.
- Testlerde gerçek kişisel veri kullanmak.
- Kabul kriterini kanıt olmadan "geçti" işaretlemek.
- Ortak dosyaları değiştirmek.

## 4. Sahip Olunan Dosya/Klasör Türleri

| Yol (hedef) | İçerik |
|---|---|
| `tests/**` | Uçtan uca ve entegrasyon testleri (depo kökü) |
| `qa/baseline/**` | Görsel karşılaştırma temel çizgisi |
| `qa/sonuclar/**` | Test çalıştırma çıktıları ve kanıtlar |
| `qa/kontrol-listeleri/**` | Faz bazlı kabul kontrol listeleri |
| `raporlar/qa-raporlari/**` | Faz kabul raporları |

**Sahiplik ayrımı:** Depo kökündeki `tests/**` QA agentına aittir. Eklenti içi birim testleri (`wp-content/plugins/mavibelge-core/tests/**`) çekirdek agentına, tema içi testler (`wp-content/themes/mavibelge/tests/**`) tema agentına aittir. Çakışma yoktur.

## 5. Ortak Dosya Değişiklik Protokolü

QA agentı ortak dosyaları **değiştirmez**. Ortak dosya kaynaklı bir sorun bulursa:

1. Bulguyu hangi ortak dosyanın hangi bölümünden kaynaklandığıyla birlikte raporla.
2. Hangi agentın entegrasyon notundan geldiğini tespit et.
3. Ana orkestratöre ilet.
4. Orkestratör düzeltmeyi birleştirir; QA regresyon setini yeniden çalıştırır.

## 6. Diğer Agent Bağımlılıkları

| Agent | İş bölümü |
|---|---|
| Tema ve arayüz | Görsel, responsive ve erişilebilirlik bulguları |
| WordPress çekirdek/eklenti | İşlevsel ve veri bütünlüğü bulguları |
| Veri/içerik aktarım | Kayıt sayısı ve içerik doğruluğu bulguları |
| SEO/AIO | 301/410 test seti, şema doğrulama, meta kontrolleri |
| Güvenlik | Güvenlik regresyon senaryoları, yetki testleri |
| DevOps/dağıtım | Smoke test, dağıtım sonrası doğrulama |
| Ana orkestratör | Her birleştirme sonrası regresyon talebi |

## 7. Girdi ve Çıktı Sözleşmesi

**Girdi:**

| Kaynak | İçerik |
|---|---|
| `tanitim-site/qa-screenshots/measurements.json` | Ölçüm temel çizgisi |
| `tanitim-site/assets/stitch_ekranlar/qa_ekran_envanteri.md` | 28 ekranlık denetim envanteri (PUB/ADM/STATE/SHELL kodları) |
| `wordpress-faz0-depo-envanteri.md` §3.3 | Doğrulanmış kayıt sayıları |
| `wordpress-faz0-depo-envanteri.md` §7-§8 | Bilinen demo/eksik/`http://` sorunları |
| Her agentın kabul kriterleri | Görev kartlarının §10 bölümleri |

**Çıktı:** Faz kabul raporu; bulgu listesi (önem derecesi ve sahip agent ile); kanıt dosyaları; regresyon seti; açık kalan kabul kriterleri.

**Sözleşme:** Her "geçti" işareti kanıta bağlıdır. Kanıtsız kabul verilmez.

## 8. PHP 7.3 ve Mevcut Hosting Kısıtları

- Testler, üretim PHP ve veritabanı sürümünü taklit eden ortamda çalıştırılır. Farklı sürümde alınan sonuç kabul kanıtı sayılmaz.
- **PHP sözdizimi uyum kontrolü bir kalite kapısıdır.** Hedef sürümde çalışmayan sözdizimi bulunursa faz kabul edilmez ([`../wordpress-faz0-uyumluluk-matrisi.md`](../wordpress-faz0-uyumluluk-matrisi.md) §4-§5).
- Sunucuda test aracı bulunacağı varsayılmaz; testler yerelde çalıştırılır.
- Üretim PHP sürümü doğrulanmadan Faz 12 (staging) kabul testi başlatılmaz.

## 9. Güvenlik ve Kişisel Veri Kuralları

- Test verisinde gerçek ad, TC kimlik, telefon, e-posta veya CV kullanılmaz. Açıkça sahte, tanınabilir test verisi kullanılır.
- Canlı sitede form gönderilmez, yönetici girişi denenmez.
- Test çıktılarında parola, oturum çerezi veya API anahtarı bulunmaz.
- Kişisel veri sızıntısı taraması (public sayfa, API, sitemap) her faz kabulünde çalıştırılır.
- Ekran görüntüleri yayınlanmadan önce kişisel veri içermediği kontrol edilir.

## 10. Test ve Kalite Kapıları

Faz kabulü için:

- [ ] PHP sözdizimi hedef sürümde geçiyor
- [ ] WordPress coding standards geçiyor
- [ ] PHP hatası/uyarısı yok
- [ ] Tarayıcı konsolunda hata yok
- [ ] 375 / 390 / 768 / 1024 / 1440 px'te yatay taşma yok
- [ ] Klavye erişimi, odak durumu, form etiketleri ve kontrast geçiyor
- [ ] aXe/Lighthouse temel kontrolleri geçiyor
- [ ] Her sayfada tek anlamlı H1, benzersiz title ve description
- [ ] Kırık iç bağlantı ve medya yolu yok
- [ ] `href="#"` ve `http://` kalıntısı yok
- [ ] 83 / 103 / 14 / 6 / 12 kayıt sayıları kaynakla eşleşiyor
- [ ] Gerçek ve temsili referanslar açıkça ayrılmış
- [ ] Arama meslek adı, MYK kodu, sektör ve seviyeyle çalışıyor
- [ ] Ücretler erişilebilir HTML tablo olarak görüntüleniyor (JPG değil)
- [ ] 301/410 otomatik testleri geçiyor
- [ ] Şema görünür içerikle eşleşiyor ve doğrulayıcıdan geçiyor
- [ ] Staging arama motoruna kapalı
- [ ] Kişisel dosyalar public URL'den erişilemiyor
- [ ] Görsel karşılaştırma statik referansla kabul edilmiş
- [ ] Yedekten geri dönüş denenmiş (yayın öncesi)

## 11. Teslim Raporu Biçimi

1. Test edilen faz ve ortam (PHP + veritabanı sürümü dahil).
2. Çalıştırılan test setleri ve komutlar.
3. Kalite kapısı tablosu: geçti / kaldı / uygulanamadı, her biri **kanıtla**.
4. Bulgu listesi: önem derecesi, etkilenen alan, sahip agent, yeniden üretme adımları.
5. Görsel karşılaştırma sonucu ve fark ekran görüntüleri.
6. Responsive ve erişilebilirlik ölçüm çıktıları.
7. Veri doğruluğu karşılaştırması (83 / 103 / 14 / 6 / 12).
8. 301/410 test sonuçları.
9. Kişisel veri sızıntısı tarama sonucu.
10. Faz kabul edildi mi — açık cevap ve gerekçe.
11. Açık kalan kabul kriterleri ve engelleyiciler.
12. Testlerde gerçek kişisel veri kullanılmadığının teyidi.
