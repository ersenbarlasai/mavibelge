# Eski URL Eşleme Raporu (Faz 9 — yerelde kayıtlı 57 URL)

> Bu belge `wordpress-site/tools/redirects/build-redirect-manifest.js` tarafından ÜRETİLİR (elle düzenlemeyin).
> Kaynak: depoda metin olarak geçen `mavibelge.com.tr` adresleri; **canlı siteye bağlanılmadı**. Nihai eski URL envanteri (en az 145) canlı sitemap/Search Console/sunucu kaydı/analitik/backlink kaynakları olmadan **tamamlanamaz** — bu rapor o envanterin yerel alt kümesidir.

## Özet

| Karar | Adet |
|---|---:|
| Toplam benzersiz eski URL | 57 |
| keep (aynı yol yeni sitede var; kural yok) | 9 |
| redirect (kural üretildi) | 29 |
| ↳ verified (aktif aday) | 2 |
| ↳ proposed (PASİF; onay bekliyor) | 27 |
| infrastructure (kural yok) | 6 |
| needs_decision (bire bir karşılık DOĞRULANAMADI; kural yok) | 13 |

Uygulama kuralları: yalnız `origin=verified` kurallar aktif adaydır ve hedef WordPress'te gerçekten yoksa apply onları da PASİF yazar; `proposed` kurallar kurum/GSC onayı olmadan çalışmaz; toplu ana sayfa yönlendirmesi yoktur; yönlendirme yalnız istek gerçekten 404 ise uygulanır.

## Karar tablosu

| Eski yol | Karar | Köken | Hedef | Gerekçe |
|---|---|---|---|---|
| `/` | keep | — | — | Ana sayfa. |
| `/6-dilde-myk-belgesi-gecerliligi/` | redirect | verified | `/haberler/6-dilde-myk-belgesi-gecerliligi/` | Yol, statik referanstaki (tanitim-site/assets/data/news.js) haber slug'ıyla BİREBİR aynı; haber içe aktarılıp yayınlanınca hedef oluşur. |
| `/banka-hesap-bilgileri/` | keep | — | — | Statik referansta aynı yolla sayfa var (tanitim-site). |
| `/basvuru-formlari/` | needs_decision | — | — | Statik referansta bire bir karşılık yok (online-basvuru mu, dokümanlar mı belirsiz). |
| `/belge-iptali-ve-askiya-alma/` | needs_decision | — | — | Statik referansta karşılığı yok. |
| `/belge-yenileme-sureci/` | redirect | proposed | `/belge-yenileme/` | Statik belge-yenileme sayfası süreci anlatıyor; onay bekliyor. |
| `/belge-yenileme-ucretleri/` | needs_decision | — | — | Yenileme ÜCRETİ ayrı bir içerik olabilir; statik referansta net karşılık yok. |
| `/bilgilendirme/` | redirect | proposed | `/haberler/bilgilendirme-subat-2023/` | Başlık/konu eşleşmesi; slug farklı — kurum/GSC onayı olmadan aktif edilmez. |
| `/cam/` | redirect | proposed | `/sektor/cam/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/elektrik/` | needs_decision | — | — | Statik referansta "elektrik" adlı sektör yok (enerji ile ilişkisi doğrulanamadı). |
| `/fiyat-list/` | redirect | proposed | `/sinav-ucretleri/` | Fiyat listesi -> sınav ücretleri sayfası; onay bekliyor. |
| `/gecerlilik-suresi-dolan-myk-mesleki-yeterlilik-belgelerinin-yenilenmesi-ve-gozetim-ile-ilgili-duyurular/` | redirect | proposed | `/haberler/gecerlilik-suresi-dolan-belgelerin-yenilenmesi/` | Başlık/konu eşleşmesi; slug farklı — kurum/GSC onayı olmadan aktif edilmez. |
| `/gizlilik-politikamiz/` | redirect | proposed | `/gizlilik-politikasi/` | Aynı konu, farklı slug (statik gizlilik-politikasi); onay bekliyor. |
| `/guzellik-ve-sac-bakim/` | redirect | proposed | `/sektor/guzellik-sac-bakim/` | Statik sektör guzellik-sac-bakim; onay bekliyor. |
| `/haberler/` | keep | — | — | Statik referansta aynı yolla sayfa var (tanitim-site). |
| `/iletisim/` | keep | — | — | Statik referansta aynı yolla sayfa var (tanitim-site). |
| `/insaat-meslekleri/` | redirect | proposed | `/sektor/insaat/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/is-basvurusu/` | keep | — | — | Statik referansta aynı yolla sayfa var (tanitim-site). |
| `/itiraz-ve-sikayetler/` | redirect | proposed | `/itiraz-sikayet/` | Aynı konu, farklı slug (statik itiraz-sikayet); onay bekliyor. |
| `/kalite-politikamiz/` | keep | — | — | Statik referansta aynı yolla sayfa var (tanitim-site). |
| `/kvkk-2/` | redirect | proposed | `/kvkk/` | WordPress çift slug (-2) — statik kvkk sayfası; onay bekliyor. |
| `/liman-meslekleri/` | needs_decision | — | — | Statik referansta "liman" adlı sektör yok (lojistik ile ilişkisi doğrulanamadı). |
| `/logo-kullanim-talimati/` | needs_decision | — | — | Statik referansta karşılığı yok. |
| `/makine-bakimci-3-belgelendirme-programi/` | needs_decision | — | — | Yeterlilik sayfası; hedef slug ancak yeterlilik içe aktarımı ve slug kararı sonrası bilinir. |
| `/makine/` | redirect | proposed | `/sektor/makine/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/mermer-meslekleri/` | redirect | proposed | `/sektor/mermer/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/mesleki-yeterlilik-kurumu-myk/` | redirect | proposed | `/myk/` | Statik myk sayfası; onay bekliyor. |
| `/mesleklerimiz/` | redirect | proposed | `/yeterlilikler/` | Meslek listesi -> yeterlilik arşivi; onay bekliyor. |
| `/metal-meslekleri/` | redirect | proposed | `/sektor/metal/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/metalurji/` | redirect | proposed | `/sektor/metalurji/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/mevzuat/` | keep | — | — | Statik referansta aynı yolla sayfa var (tanitim-site). |
| `/misyonumuz/` | redirect | proposed | `/misyon-vizyon/` | Statik misyon-vizyon sayfası; onay bekliyor. |
| `/mobilya-meslekleri/` | redirect | proposed | `/sektor/mobilya/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/mobilya-sektoru-belge-zorunlulugu/` | redirect | verified | `/haberler/mobilya-sektoru-belge-zorunlulugu/` | Yol, statik referanstaki (tanitim-site/assets/data/news.js) haber slug'ıyla BİREBİR aynı; haber içe aktarılıp yayınlanınca hedef oluşur. |
| `/myk-belgesi-zorunlulugu-getirilen-yeni-meslekler-2/` | redirect | proposed | `/haberler/myk-belgesi-zorunlulugu-yeni-meslekler-2021/` | Başlık/konu eşleşmesi; slug farklı — kurum/GSC onayı olmadan aktif edilmez. (WordPress çift slug -2). |
| `/myk-belgesi-zorunlulugu-getirilen-yeni-meslekler/` | redirect | proposed | `/haberler/myk-belgesi-zorunlulugu-yeni-meslekler-2021/` | Başlık/konu eşleşmesi; slug farklı — kurum/GSC onayı olmadan aktif edilmez. |
| `/neden-zorunlu/` | needs_decision | — | — | Statik referansta net karşılığı yok (myk sayfasıyla ilişkisi doğrulanamadı). |
| `/plastik-meslekleri/` | redirect | proposed | `/sektor/plastik/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/referanslar/` | keep | — | — | Statik referansta aynı yolla sayfa var (tanitim-site). |
| `/robots.txt` | infrastructure | — | — | Yeni site robots.txt'i WordPress robots_txt filtresiyle sanal üretir; yönlendirme kuralı yok. |
| `/sinav-ve-belge-kurallari/` | needs_decision | — | — | Statik referansta net karşılığı yok (sinav-surecleri mi, belge-yenileme mi belirsiz). |
| `/sinav-ve-belgelendirme-sureci/` | redirect | proposed | `/sinav-surecleri/` | Statik sinav-surecleri sayfası; onay bekliyor. |
| `/sirket-hakkinda/` | redirect | proposed | `/hakkimizda/` | Statik hakkimizda sayfası; onay bekliyor. |
| `/tekstil/` | redirect | proposed | `/sektor/tekstil/` | Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor. |
| `/turk-akreditasyon-kurumu-turkak/` | redirect | proposed | `/turkak/` | Statik turkak sayfası; onay bekliyor. |
| `/ulusal-meslek-standartlari-ums-ve-ulusal-yeterlilikler-uy/` | needs_decision | — | — | Statikte iki ayrı sayfa var (ulusal-meslek-standartlari, ulusal-yeterlilikler); tek hedef seçilemez. |
| `/ust-yonetimin-tarafsizlik-beyani/` | redirect | proposed | `/tarafsizlik-beyani/` | Statik tarafsizlik-beyani sayfası; onay bekliyor. |
| `/wp-content/uploads/2020/02/aday-taahhut.pdf` | needs_decision | — | — | Medya dosyası; yeni medya kütüphanesi eşlemesi ve dosya adı kararı gerekir. |
| `/wp-content/uploads/2022/04/mavi-katalog-2022.pdf` | needs_decision | — | — | Medya dosyası; yeni medya kütüphanesi eşlemesi ve dosya adı kararı gerekir. |
| `/wp-sitemap-posts-logosliderwp-1.xml` | needs_decision | — | — | Eski eklentiye ait sitemap; yeni sitede karşılığı yok (410/yok sayma kararı gerekir). |
| `/wp-sitemap-posts-page-1.xml` | infrastructure | — | — | WordPress çekirdek sitemap yolu; yeni site aynı yolu sunar. |
| `/wp-sitemap-posts-post-1.xml` | infrastructure | — | — | Yeni sitede "post" türü sitemap'e girmez; kural yok, canlı GSC ile ayrıca izlenir. |
| `/wp-sitemap-taxonomies-category-1.xml` | infrastructure | — | — | Yeni sitede category sitemap'e girmez; kural yok. |
| `/wp-sitemap-taxonomies-post_tag-1.xml` | infrastructure | — | — | Yeni sitede post_tag sitemap'e girmez; kural yok. |
| `/wp-sitemap.xml` | infrastructure | — | — | WordPress çekirdek sitemap dizini; yeni site aynı yolu sunar. |
| `/yasal-dayanagimiz/` | keep | — | — | Statik referansta aynı yolla sayfa var (tanitim-site). |
| `/yetki-belgelerimiz/` | redirect | proposed | `/yetki-akreditasyon/` | Statik yetki-akreditasyon sayfası; onay bekliyor. |

## Kural-seti özeti

`data/redirects/redirects.manifest.json` — 29 kural (2 aktif aday). Doğrulama ve dry-run: `wp mavibelge redirects import --file=<manifest>` (PHP doğrulayıcısı: çakışma/döngü/zincir/dış hedef/korumalı yol reddi). Apply varsayılan kapalıdır (`MAVIBELGE_REDIRECTS_APPLY_ENABLED`).
