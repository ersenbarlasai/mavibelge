'use strict';
/**
 * Faz 12 — fiziksel WordPress `page` kaydı gerektiren 32 sayfanın TEK yetkili envanteri.
 *
 * Kaynak doğrulamaları (bkz. lib/validate-page-set.js -> crossCheckInventory()): bu liste
 *   (a) tanitim-site/*.html dosya adlarıyla,
 *   (b) wordpress-site/docs/page-template-map.md tablosunun `page.php` / `page-*.php` satırlarıyla (slug, başlık, layout),
 *   (c) tema inc/page-layouts.php'deki hub/form-disabled slug kümeleriyle
 * BİREBİR eşit olmak zorundadır. Sayı (32) ve kategori dağılımı (3/21/5/3) burada TEK yerde sabittir; üretici, doğrulayıcı,
 * testler ve PHP yükleyici aynı sayıyı bağlar.
 *
 * `page` manifestine GİRMEYENLER: front page sistem rotası (index), 404, CPT arşivleri (meslekler, haberler, dokumanlar),
 * taksonomi rotaları (sektor, duyurular), CPT tekilleri (yeterlilik, haber-detay).
 *
 * `mode`:
 *   'extract' — statik sayfanın ana içeriği (hero'dan sonrası) güvenli HTML'e dönüştürülür;
 *   'approved' — kurum/kullanıcı onaylı kaynaktan (wordpress-site/data/sources/approved/<slug>.html; künye:
 *               approved-sources.manifest.json) alınan gövde parçası kapalı izin listesine dönüştürülür; hukuki/banka
 *               metni YENİDEN YAZILMAZ, yer-tutucu cümle süzgeci UYGULANMAZ;
 *   'empty'   — içerik BOŞ bırakılır (`reason`): tema dinamik olarak çizer (hub kartları, form altyapısı, CPT listeleri) veya
 *               statik metin kurumca onaylanmamış/tanıtım özeti olduğu için uydurulmaz.
 *
 * `excerpt: false` — hero özeti aktarılmaz (ör. referanslar: özet gerçek müşteri iddiası taşır).
 *
 * `pending`: kurum kararı bekleyen konu kodları (kapalı sözlük: PENDING_CODES). `blocking: true` kodlar sayfayı yayın
 * işleminden ALIKOR (publish_hold) — yayınlama işlemi bu sayfaları atlar ve nedenini gösterir. Şu an bloklayıcı bekleyen karar YOKTUR
 * (yedi sayfanın kurum kararı çözüldü; bkz. wordpress-site/docs/institution-decisions.md).
 *
 * `publishRequires`: sayfanın YAYINI, ilgili içerik kayıtlarının (mb_referans / mb_sss) content aşamasında gerçekten oluşmasını ve
 * yayınlanmış olmasını gerektirir (kapalı küme PUBLISH_REQUIRES; sunucu tarafında publisher zorlar).
 */

const PENDING_CODES = {
	form_gate_institution_decisions: { blocking: false, label: 'Form kapısı: KVKK metni, alıcı e-posta, hassas alan/dosya yükleme onayı' },
	location_data_pending: { blocking: false, label: 'Güncel lokasyon/iletişim bilgilerinin kurum onayı' },
	fee_tariff_documents_pending: { blocking: false, label: 'Kaynak tarife PDF belgeleri (medya kütüphanesi eşlemesi)' },
	accreditation_documents_pending: { blocking: false, label: 'MYK/TÜRKAK yetki belgesi taramaları ve logo görselleri' },
	legislation_links_pending: { blocking: false, label: 'Mevzuat metinlerinin doğrulanmış bağlantıları' },
	static_counter_block_omitted: { blocking: false, label: 'Statik sayaç bloğu (12 sektör) çelişkili olduğu için aktarılmadı' },
};

const PUBLISH_REQUIRES = ['faq', 'reference'];

const L = { HUB: 'hub', DEFAULT: 'default', FORM: 'form-disabled', CPT: 'cpt-page' };

// Sıra: page-template-map.md tablo sırası (menu_order = sıra + 1).
const PAGE_INVENTORY = [
	{ slug: 'kurumsal', file: 'kurumsal.html', title: 'Kurumsal', layout: L.HUB, mode: 'empty', reason: 'Hub kartlarını tema (inc/page-layouts.php) çizer.', pending: [] },
	{ slug: 'bilgi-merkezi', file: 'bilgi-merkezi.html', title: 'Bilgi Merkezi', layout: L.HUB, mode: 'empty', reason: 'Hub kartlarını tema (inc/page-layouts.php) çizer.', pending: [] },
	{ slug: 'sinav-ve-basvuru', file: 'sinav-ve-basvuru.html', title: 'Sınav ve Başvuru', layout: L.HUB, mode: 'empty', reason: 'Hub kartlarını tema (inc/page-layouts.php) çizer.', pending: [] },
	{ slug: 'hakkimizda', file: 'hakkimizda.html', title: 'Hakkımızda', layout: L.DEFAULT, mode: 'extract', pending: ['static_counter_block_omitted'] },
	{ slug: 'misyon-vizyon', file: 'misyon-vizyon.html', title: 'Misyon ve Vizyon', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'kalite-politikamiz', file: 'kalite-politikamiz.html', title: 'Kalite Politikamız', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'tarafsizlik-beyani', file: 'tarafsizlik-beyani.html', title: 'Tarafsızlık Beyanı', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'yasal-dayanagimiz', file: 'yasal-dayanagimiz.html', title: 'Yasal Dayanağımız', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'yetki-akreditasyon', file: 'yetki-akreditasyon.html', title: 'Yetki ve Akreditasyon', layout: L.DEFAULT, mode: 'extract', pending: ['accreditation_documents_pending'] },
	{ slug: 'referanslar', file: 'referanslar.html', title: 'Referanslarımız', layout: L.CPT, mode: 'empty', excerpt: false, reason: 'Liste mb_referans kayıtlarından (gerçek logo eklentileri) çizilir; statik metin temsili logolar hakkındadır, içeri alınmaz.', pending: [], publishRequires: ['reference'] },
	{ slug: 'sosyal-sorumluluk', file: 'sosyal-sorumluluk.html', title: 'Sosyal Sorumluluk', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'kariyer', file: 'kariyer.html', title: 'Kariyer', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'belge-yenileme', file: 'belge-yenileme.html', title: 'Belge Yenileme', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'mevzuat', file: 'mevzuat.html', title: 'Mevzuat', layout: L.DEFAULT, mode: 'extract', pending: ['legislation_links_pending'] },
	{ slug: 'myk', file: 'myk.html', title: 'MYK Nedir?', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'turkak', file: 'turkak.html', title: 'TÜRKAK Nedir?', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'ulusal-meslek-standartlari', file: 'ulusal-meslek-standartlari.html', title: 'Ulusal Meslek Standartları', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'ulusal-yeterlilikler', file: 'ulusal-yeterlilikler.html', title: 'Ulusal Yeterlilikler', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'sss', file: 'sss.html', title: 'Sık Sorulan Sorular', layout: L.CPT, mode: 'empty', reason: 'Soru/cevaplar mb_sss kayıtlarından çizilir (kurumca onaylı SSS içeriği content aşamasında içe aktarılır).', pending: [], publishRequires: ['faq'] },
	{ slug: 'nasil-basvururum', file: 'nasil-basvururum.html', title: 'Nasıl Başvururum?', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'online-basvuru', file: 'online-basvuru.html', title: 'Online Başvuru', layout: L.FORM, mode: 'empty', reason: 'Form altyapısını tema/eklenti çizer; statik içerik demo formudur.', pending: ['form_gate_institution_decisions'] },
	{ slug: 'sinav-takvimi', file: 'sinav-takvimi.html', title: 'Sınav Takvimi', layout: L.DEFAULT, mode: 'approved', excerpt: 'Sınav takvimi harici hizmette yayınlanmaktadır.', pending: [] },
	{ slug: 'sinav-ucretleri', file: 'sinav-ucretleri.html', title: 'Sınav Ücretleri', layout: L.CPT, mode: 'empty', reason: 'Ücret listesi katalog servisinden çizilir; kaynak tarife PDF bağlantıları medya kütüphanesi eşlemesi bekler.', pending: ['fee_tariff_documents_pending'] },
	{ slug: 'sonuc-belge-sorgulama', file: 'sonuc-belge-sorgulama.html', title: 'Sonuç ve Belge Sorgulama', layout: L.DEFAULT, mode: 'approved', excerpt: 'Sonuç ve belge sorgulama MYK portalında yapılır.', pending: [] },
	{ slug: 'sinav-surecleri', file: 'sinav-surecleri.html', title: 'Sınav Süreçleri', layout: L.DEFAULT, mode: 'extract', pending: [] },
	{ slug: 'sinav-talepleri', file: 'sinav-talepleri.html', title: 'Sınav Talepleri', layout: L.FORM, mode: 'empty', reason: 'Form altyapısını tema/eklenti çizer; statik içerik demo formudur.', pending: ['form_gate_institution_decisions'] },
	{ slug: 'banka-hesap-bilgileri', file: 'banka-hesap-bilgileri.html', title: 'Banka Hesap Bilgileri', layout: L.DEFAULT, mode: 'approved', pending: [] },
	{ slug: 'itiraz-sikayet', file: 'itiraz-sikayet.html', title: 'İtiraz ve Şikayet', layout: L.FORM, mode: 'extract', pending: ['form_gate_institution_decisions'] },
	{ slug: 'iletisim', file: 'iletisim.html', title: 'İletişim', layout: L.FORM, mode: 'empty', reason: 'Lokasyon kartlarını tema (mb_lokasyon veya doğrulanmış statik yedek) çizer; sosyal medya bağlantıları http:// ve hesap kararı bekler.', pending: ['form_gate_institution_decisions', 'location_data_pending'] },
	{ slug: 'is-basvurusu', file: 'is-basvurusu.html', title: 'İş Başvurusu', layout: L.FORM, mode: 'empty', reason: 'Form altyapısını tema/eklenti çizer; statik içerik demo formudur.', pending: ['form_gate_institution_decisions'] },
	{ slug: 'gizlilik-politikasi', file: 'gizlilik-politikasi.html', title: 'Gizlilik Politikası', layout: L.DEFAULT, mode: 'approved', pending: [] },
	{ slug: 'kvkk', file: 'kvkk.html', title: 'KVKK Aydınlatma Metni', layout: L.DEFAULT, mode: 'approved', pending: [] },
];

const PAGE_EXPECTED = 32;

const LAYOUT_COUNTS = PAGE_INVENTORY.reduce(function (acc, p) {
	acc[p.layout] = (acc[p.layout] || 0) + 1;
	return acc;
}, { hub: 0, default: 0, 'form-disabled': 0, 'cpt-page': 0 });

/** Statik `*.html` bağlantı hedefi -> WordPress yolu. Envanterdeki her slug + sabit CPT/arşiv rotaları (page-template-map.md). */
const FIXED_ROUTES = {
	'index.html': '/',
	'meslekler.html': '/yeterlilikler/',
	'haberler.html': '/haberler/',
	'dokumanlar.html': '/dokumanlar/',
	'duyurular.html': '/haber-turu/duyuru/',
};

function linkRouteFor(htmlName) {
	if (Object.prototype.hasOwnProperty.call(FIXED_ROUTES, htmlName)) {
		return FIXED_ROUTES[htmlName];
	}
	const hit = PAGE_INVENTORY.find((p) => p.file === htmlName);
	return hit ? '/' + hit.slug + '/' : null;
}

module.exports = { PUBLISH_REQUIRES, PAGE_INVENTORY, PAGE_EXPECTED, LAYOUT_COUNTS, PENDING_CODES, FIXED_ROUTES, linkRouteFor };
