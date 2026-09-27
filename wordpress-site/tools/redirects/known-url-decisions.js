'use strict';
/**
 * Faz 9 — depoda metin olarak kayıtlı 57 BENZERSİZ eski mavibelge.com.tr URL'si için karar tablosu.
 * Karar türleri:
 *   keep            aynı yol yeni sitede de vardır (statik referansta aynı sayfa) — yönlendirme kuralı GEREKMEZ.
 *   redirect        yönlendirme kuralı üretilir. origin=verified: eşleme kaynak verisiyle doğrulanmış (ör. haber
 *                   slug'ı statik news.js slug'ıyla BİREBİR aynı) -> kural aktif adayıdır (hedef gerçekten yoksa apply
 *                   pasif yazar). origin=proposed: makul eşleme ama kurum/Search Console onayı YOK -> kural PASİF yazılır.
 *   infrastructure  robots.txt / WordPress çekirdek sitemap yolları — yeni WordPress kendisi sunar; kural yok.
 *   needs_decision  bire bir karşılık DOĞRULANAMADI (statik referansta karşılığı yok veya belirsiz) — kural ÜRETİLMEZ,
 *                   uydurma eşleme yok; canlı sitemap/GSC/log ve kurum kararıyla çözülmelidir.
 * Toplu "her şeyi ana sayfaya" yönlendirmesi YOKTUR. Bu tablo yalnız yerelde kayıtlı 57 URL'yi kapsar; nihai eski URL
 * envanteri (en az 145) canlı kaynaklar olmadan tamamlanamaz (raporlar/wordpress-faz0-depo-envanteri.md §9).
 */

const K = (why) => ({ decision: 'keep', rationale: why });
const V = (target, why) => ({ decision: 'redirect', origin: 'verified', target, status: 301, rationale: why });
const P = (target, why) => ({ decision: 'redirect', origin: 'proposed', target, status: 301, rationale: why });
const I = (why) => ({ decision: 'infrastructure', rationale: why });
const N = (why) => ({ decision: 'needs_decision', rationale: why });

const SAME = 'Statik referansta aynı yolla sayfa var (tanitim-site).';
const NEWS_SLUG = 'Yol, statik referanstaki (tanitim-site/assets/data/news.js) haber slug\'ıyla BİREBİR aynı; haber içe aktarılıp yayınlanınca hedef oluşur.';
const NEWS_TITLE = 'Başlık/konu eşleşmesi; slug farklı — kurum/GSC onayı olmadan aktif edilmez.';
const LEGAL_APPROVED = 'Kullanıcı/kurum onayı (26 Eylül 2026): sayfa içeriği bu eski adresten (onaylı kaynak) yeni WordPress sayfasına aktarıldı; hedef sayfa aynı içeriği taşır. Tek atlamalı 301.';
const SECTOR_SAME = 'Aynı adlı sektör statik referansta var (sectors.js); sektör sayfası /sektor/<slug>/ — onay bekliyor.';

module.exports = {
	'/': K('Ana sayfa.'),
	'/6-dilde-myk-belgesi-gecerliligi/': V('/haberler/6-dilde-myk-belgesi-gecerliligi/', NEWS_SLUG),
	'/banka-hesap-bilgileri/': K(SAME),
	'/basvuru-formlari/': N('Statik referansta bire bir karşılık yok (online-basvuru mu, dokümanlar mı belirsiz).'),
	'/belge-iptali-ve-askiya-alma/': N('Statik referansta karşılığı yok.'),
	'/belge-yenileme-sureci/': P('/belge-yenileme/', 'Statik belge-yenileme sayfası süreci anlatıyor; onay bekliyor.'),
	'/belge-yenileme-ucretleri/': N('Yenileme ÜCRETİ ayrı bir içerik olabilir; statik referansta net karşılık yok.'),
	'/bilgilendirme/': P('/haberler/bilgilendirme-subat-2023/', NEWS_TITLE),
	'/cam/': P('/sektor/cam/', SECTOR_SAME),
	'/elektrik/': N('Statik referansta "elektrik" adlı sektör yok (enerji ile ilişkisi doğrulanamadı).'),
	'/fiyat-list/': P('/sinav-ucretleri/', 'Fiyat listesi -> sınav ücretleri sayfası; onay bekliyor.'),
	'/gecerlilik-suresi-dolan-myk-mesleki-yeterlilik-belgelerinin-yenilenmesi-ve-gozetim-ile-ilgili-duyurular/': P('/haberler/gecerlilik-suresi-dolan-belgelerin-yenilenmesi/', NEWS_TITLE),
	'/gizlilik-politikamiz/': V('/gizlilik-politikasi/', LEGAL_APPROVED),
	'/guzellik-ve-sac-bakim/': P('/sektor/guzellik-sac-bakim/', 'Statik sektör guzellik-sac-bakim; onay bekliyor.'),
	'/haberler/': K(SAME),
	'/iletisim/': K(SAME),
	'/insaat-meslekleri/': P('/sektor/insaat/', SECTOR_SAME),
	'/is-basvurusu/': K(SAME),
	'/itiraz-ve-sikayetler/': P('/itiraz-sikayet/', 'Aynı konu, farklı slug (statik itiraz-sikayet); onay bekliyor.'),
	'/kalite-politikamiz/': K(SAME),
	'/kvkk-2/': V('/kvkk/', LEGAL_APPROVED),
	'/liman-meslekleri/': N('Statik referansta "liman" adlı sektör yok (lojistik ile ilişkisi doğrulanamadı).'),
	'/logo-kullanim-talimati/': N('Statik referansta karşılığı yok.'),
	'/makine/': P('/sektor/makine/', SECTOR_SAME),
	'/makine-bakimci-3-belgelendirme-programi/': N('Yeterlilik sayfası; hedef slug ancak yeterlilik içe aktarımı ve slug kararı sonrası bilinir.'),
	'/mermer-meslekleri/': P('/sektor/mermer/', SECTOR_SAME),
	'/mesleki-yeterlilik-kurumu-myk/': P('/myk/', 'Statik myk sayfası; onay bekliyor.'),
	'/mesleklerimiz/': P('/yeterlilikler/', 'Meslek listesi -> yeterlilik arşivi; onay bekliyor.'),
	'/metal-meslekleri/': P('/sektor/metal/', SECTOR_SAME),
	'/metalurji/': P('/sektor/metalurji/', SECTOR_SAME),
	'/mevzuat/': K(SAME),
	'/misyonumuz/': P('/misyon-vizyon/', 'Statik misyon-vizyon sayfası; onay bekliyor.'),
	'/mobilya-meslekleri/': P('/sektor/mobilya/', SECTOR_SAME),
	'/mobilya-sektoru-belge-zorunlulugu/': V('/haberler/mobilya-sektoru-belge-zorunlulugu/', NEWS_SLUG),
	'/myk-belgesi-zorunlulugu-getirilen-yeni-meslekler/': P('/haberler/myk-belgesi-zorunlulugu-yeni-meslekler-2021/', NEWS_TITLE),
	'/myk-belgesi-zorunlulugu-getirilen-yeni-meslekler-2/': P('/haberler/myk-belgesi-zorunlulugu-yeni-meslekler-2021/', NEWS_TITLE + ' (WordPress çift slug -2).'),
	'/neden-zorunlu/': N('Statik referansta net karşılığı yok (myk sayfasıyla ilişkisi doğrulanamadı).'),
	'/plastik-meslekleri/': P('/sektor/plastik/', SECTOR_SAME),
	'/referanslar/': K(SAME),
	'/robots.txt': I('Yeni site robots.txt\'i WordPress robots_txt filtresiyle sanal üretir; yönlendirme kuralı yok.'),
	'/sinav-ve-belge-kurallari/': N('Statik referansta net karşılığı yok (sinav-surecleri mi, belge-yenileme mi belirsiz).'),
	'/sinav-ve-belgelendirme-sureci/': P('/sinav-surecleri/', 'Statik sinav-surecleri sayfası; onay bekliyor.'),
	'/sirket-hakkinda/': P('/hakkimizda/', 'Statik hakkimizda sayfası; onay bekliyor.'),
	'/tekstil/': P('/sektor/tekstil/', SECTOR_SAME),
	'/turk-akreditasyon-kurumu-turkak/': P('/turkak/', 'Statik turkak sayfası; onay bekliyor.'),
	'/ulusal-meslek-standartlari-ums-ve-ulusal-yeterlilikler-uy/': N('Statikte iki ayrı sayfa var (ulusal-meslek-standartlari, ulusal-yeterlilikler); tek hedef seçilemez.'),
	'/ust-yonetimin-tarafsizlik-beyani/': P('/tarafsizlik-beyani/', 'Statik tarafsizlik-beyani sayfası; onay bekliyor.'),
	'/wp-content/uploads/2020/02/aday-taahhut.pdf': N('Medya dosyası; yeni medya kütüphanesi eşlemesi ve dosya adı kararı gerekir.'),
	'/wp-content/uploads/2022/04/mavi-katalog-2022.pdf': N('Medya dosyası; yeni medya kütüphanesi eşlemesi ve dosya adı kararı gerekir.'),
	'/wp-sitemap-posts-logosliderwp-1.xml': N('Eski eklentiye ait sitemap; yeni sitede karşılığı yok (410/yok sayma kararı gerekir).'),
	'/wp-sitemap-posts-page-1.xml': I('WordPress çekirdek sitemap yolu; yeni site aynı yolu sunar.'),
	'/wp-sitemap-posts-post-1.xml': I('Yeni sitede "post" türü sitemap\'e girmez; kural yok, canlı GSC ile ayrıca izlenir.'),
	'/wp-sitemap-taxonomies-category-1.xml': I('Yeni sitede category sitemap\'e girmez; kural yok.'),
	'/wp-sitemap-taxonomies-post_tag-1.xml': I('Yeni sitede post_tag sitemap\'e girmez; kural yok.'),
	'/wp-sitemap.xml': I('WordPress çekirdek sitemap dizini; yeni site aynı yolu sunar.'),
	'/yasal-dayanagimiz/': K(SAME),
	'/yetki-belgelerimiz/': P('/yetki-akreditasyon/', 'Statik yetki-akreditasyon sayfası; onay bekliyor.'),
};
