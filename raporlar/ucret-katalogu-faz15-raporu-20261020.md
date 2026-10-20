# Faz 15 — Sınav Ücretleri: Tam Aktarım, KDV/Basım Kuralı ve Ücret Yönetimi (eklenti 0.5.5, tema 0.6.10)

> Tarih: 2026-10-20. Çalışma alanı: `E:\PROJELER\MaviBelge-kvkk` (dal `claude/kvkk-consent-link`, taban `af3ca05`) — canlıdaki 0.6.9/0.5.4
> kaynağını içeren TEK çalışma alanı. Commit, push, deploy, canlı/staging bağlantısı **yok**. Canlı ücretler etkinleştirilmedi.
> Değişikliklerin KVKK (Faz 14) değişikliklerinden ayrılması için taban: `E:\PROJELER\MaviBelge-koruma-20261019-195132\kvkk\patches\unstaged.patch`
> (iş başlamadan önce çalışma ağacıyla byte-eşit doğrulandı).
>
> **GÜNCELLEME (aynı gün, ek rapor):** `raporlar/ucret-katalogu-faz15-ek-raporu-20261020.md` bu raporun §6 test sonucunu, §7 kurulum adımlarını ve
> §9 eklenti paket özetini **geçersiz kılar**:
> - §6'daki "57/58 + tek başına 75/75" tümü-geçti sayılmaz. Nihai tek koşu 59/59 geçti (log: `raporlar/test-loglari/faz15-nihai-kapi-20261020-071938/`).
> - 0.5.5 zip'i yeni kodla yeniden üretildi (SHA `2cc3c168…cc37`); eski `5db7c547…` özeti dağıtılmamıştı ve geçersizdir.
> - "Beklenen hazır 95, yayında 2, bekletilen 6" bir beklenti değildir. Yalnız yerel karşılaştırma değeridir; sayılar sunucunun gerçek kayıtlarından hesaplanır.
> - `data/` yerine ayrı ücret veri paketi kullanılır. Adım adım kurulum: `wordpress-site/docs/ucret-staging-uygulama-yonergesi.md`.

## 1. Sonuç

| Konu | Durum |
|---|---|
| Kaynak doğrulama | Referans `https://yeni.mavibelge.com.tr/sinav-ucretleri.html` ↔ yerel `tanitim-site`: `fees.js` SHA-256 `ccb170bf…4bdb` **EŞİT**; HTML yalnız `noindex` satırıyla farklı. Bağımsız sayım **103 kayıt / 145 seçenek**, manifestle alan-alan **0 fark** |
| Canlıda 2 ücret nedeni | Kodla kanıtlı: import ücreti HER ZAMAN taslak yazar, toplu etkinleştirme aracı yoktu. Staging'de yalnız 2 QA kaydı elle aktifleştirildi (eski aktivasyon planı §3); canlı = staging `.wpress` kopyası. Canlıdaki görünen 2 kayıt tam olarak bu QA kayıtları (`10UY0002-3/03`, `11UY0036-2/01`). **Canlı DB'ye erişilmediği için 101 kaydın canlıda taslak olarak var olduğu doğrulanamadı** (çıkarım) |
| Yayına uygun | **97 kayıt / 136 seçenek** (18'i bağlantısız). Bekletilen **6** (ad farkı 5, aynı yeterliliğe iki ücret 2; #86 ikisi birden) |
| Kod | Ücret yayınlayıcı, KDV oranı alanı, kayıttan üretilen standart açıklama, yönetim ekranı iyileştirmeleri, ücret yayın durumu import hash'inden çıkarıldı (eski hash geçişiyle) |
| Testler | Bkz. §6 — gerçekten çalıştırılanlar ayrıca listelendi |
| Paketler | `mavibelge-core-0.5.5.zip`, `mavibelge-theme-0.6.10.zip` (eski 0.5.4/0.6.9 ezilmedi) |

## 2. Kurum kararı ve uygulanışı (2026-10-19 talimatı)

- Fiyatlar son kullanıcıya gösterilen **%20 KDV dahil** tutarlardır; **yeniden KDV eklenmez**. **1.500 TL MYK belge basım ücreti** fiyatlara dahil değildir.
- Ziyaretçi cümlesi: "Fiyatlara %20 KDV dahildir. 1.500 TL MYK belge basım ücreti fiyatlara dahil değildir." — kaydın `_mb_vat_included`,
  `_mb_vat_rate_percent` ve `_mb_certificate_print_fee_kurus` değerlerinden üretilir; tema oran/tutar yazmaz.
- Güzellik PDF'inde KDV ifadesi bulunmadığı için bekleyen 7 kayıt bu kararla çözüldü (onay matrisi: VERIFIED 72→79, DECISION_REQUIRED 31→24).
- **Çözülmüş sayılmayanlar:** 19 kaydın MYK kodu/yeterlilik ilişkisi (kod tahmin edilmedi); ad farkı (5); aynı yeterliliğe iki ücret (#25, #86).
- Bağlantısız kayıtlar: talimat gereği "ilişki yok diye listeden kaybolmaz" → **bağlantısız listelenir** (18/19; #87 ad farkı nedeniyle bekletilir).
  Bu, bağlantısız yayını kurum adına onaylanmış saymak değildir; karar dosyasında tek satırla bekletmeye çevrilebilir.

## 3. Veri karşılaştırması

Kaynak → manifest: 103/103 kayıt, 145/145 seçenek; ad, seviye, sektör, MYK kodu, fiyatlandırma türü, KDV, basım, kaynak adı/sayfa, her seçeneğin
etiketi/birimleri/tutarı (×100 kuruş) ve min/max — **fark yok**. Tutarlarda ondalık yok; tüm kayıtlarda `vat_included=true`, basım 150000 kuruş.

| Sektör | Kayıt | Seçenek | Yayına uygun | Uygun seçenek | Bağlantısız |
|---|---|---|---|---|---|
| Cam | 3 | 3 | 3 | 3 | 0 |
| Enerji | 8 | 9 | 8 | 9 | 3 |
| Güzellik ve Saç Bakım | 7 | 9 | 7 | 9 | 0 |
| İnşaat | 12 | 12 | 11 | 11 | 0 |
| İş Makineleri | 13 | 16 | 10 | 10 | 6 |
| Lojistik | 7 | 8 | 7 | 8 | 1 |
| Maden | 4 | 4 | 4 | 4 | 0 |
| Makine | 13 | 18 | 11 | 16 | 0 |
| Mermer | 3 | 8 | 3 | 8 | 0 |
| Metal | 7 | 9 | 7 | 9 | 2 |
| Metalurji | 7 | 7 | 7 | 7 | 0 |
| Mobilya | 3 | 3 | 3 | 3 | 0 |
| Plastik | 3 | 3 | 3 | 3 | 0 |
| Tekstil | 13 | 36 | 13 | 36 | 7 |
| **Toplam** | **103** | **145** | **97** | **136** | **19** |

Yayına uygun türler: tek fiyat 82, birim bazlı 12, paket 1, çok seçenekli 2.

**Bekletilen 6 kayıt:** #25 Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci) `12UY0061-3/04` — çift bağlantı; #68 Metal Levha İşleme Tezgâh İşçisi
`12UY0086-3/02` — ad farkı; #69 Metal Levha İşleme Tezgâh Operatörü `12UY0087-4/02` — ad farkı; #79 Isı Yalıtımcısı `12UY0057-3/01` — ad farkı;
#86 Mobil Vinç Operatörü (İş Makineleri Grubu) `12UY0061-3/04` — ad farkı + çift bağlantı; #87 Kazıcı Yükleyici (Beko Loder) Operatörü (kodsuz) — ad farkı.

Kayıt bazlı ayrıntı: `raporlar/veri-aktarim-raporlari/ucret-2026-onay/onay-matrisi.csv|md`, `dogrulama-ozeti.md`, `baglantisiz-19-kayit.*`,
`staging-aktivasyon-plani.md`; makine-okunur karar: `wordpress-site/data/content/fee-publication.manifest.json`.

## 4. Ne değişti

**Eklenti (0.5.5)**
- `includes/import/class-import-fee-publisher.php` (yeni): önizleme / etkinleştir / geri al; karar dosyası kapalı doğrulama + kayıt özetine bağlı;
  10 kayıt/istek; transaction → katı readback → audit (`import_fees_activated` / `import_fees_deactivated`); `expected_remaining`, kilit,
  çözülmemiş run kontrolü; panelden değişmiş kayıt atlanır; görünürlük ön kontrolü katalog servisinin AYNI kuralıyla.
- Admin: Araçlar → İçe Aktarım Dry-Run → **6) Ücret yayını** (3 yeni `wp_ajax` action; apply ile aynı kapılar; ifadeler `UCRET YAYINLA …` / `UCRET YAYINDAN KALDIR …`).
- Import hash modeli: `_mb_record_status` ücret yönetilen alanlarından çıkarıldı (create yalnız `draft` yazar; update dokunmaz); 0.5.4 hash'leri
  içerik aynıysa okumada çevrilir. Etkinleştirme sahte conflict üretmez; içerikteki elle değişiklik conflict olarak kalır.
- Şema: `_mb_vat_rate_percent` (0–100, varsayılan 20), `_mb_fee_publication_marker` (sistem); yeni kayıt varsayılanları (KDV dahil, 1.500,00 TL).
- Katalog: kural değerlendirmesi tek fonksiyona çıkarıldı; `fee_publication_blocker()`; DTO `vat_rate_percent`; `present_fee()` → `pricing_note`.
- Yönetim ekranı: aranabilir yeterlilik seçimi (Bağlantısız korunur, kaynak MYK kodu değişmez), sektör seçimi (terime çözülmeyen değer reddedilir),
  fiyat tablosu tam genişlik + 1100px altında kart, satır ekle/↑/↓/kaldır (şablon klonu; eski indeks çakışma hatası giderildi), "Kaydedilecek"
  önizlemesi, yalnız birim yazılmış satırın atomik reddi, form fiyat alanı göndermezse listenin korunması, sektör/KDV oranı/basım için audit.
- Onay matrisi üreticisi (`tools/fees/build-approval-matrix.js`): kurum kararı + yayın kararı dosyası; doğrulayıcı listelerine yeni dosya.

**Tema (0.6.10)**
- `fee-options.php`: standart açıklama (`pricing_note`); tek seçenekte de etiket ve birimler (kayıpsız gösterim). Tablo, mobil kart ve yeterlilik detayı aynı parça.
- `pages.css` + `dist/style.css` yeniden üretildi.

**Belgeler:** `docs/content-model.md`, `catalog-service-contract.md`, `admin-import-operations.md` (§8.3), `admin-catalog-experience.md` (§6),
yeni `docs/ucret-yonetimi-kullanim.md` (admin yönergesi).

## 5. Admin artık neler yapabilir

Ücret ekleme; fiyat/etiket/birim değiştirme; seçenek ekleme, sıralama, kaldırma; KDV dahil/oranı ve belge basım ücreti düzenleme; aktiflik
(Kayıt Durumu) ve tarife dönemi yönetimi; yeterlilik ilişkisini adla arayıp seçme veya bağlantısız bırakma; sektörü listeden seçme; toplu ilk
yayın ve aracın yayınladıklarını geri alma. Kısa yönerge: `wordpress-site/docs/ucret-yonetimi-kullanim.md`.

Not: `mb_price_editor` rolü (mevcut tasarım) yalnız kendi taslaklarını düzenler; yayındaki/içe aktarılmış ücretleri yönetici, site yöneticisi
veya kontrol eden rolü düzenler. Değiştirilmedi; ihtiyaç varsa ayrı karar.

## 6. Testler

**Bu turda gerçekten çalıştırıldı (PHP 7.3.33 + WordPress 6.9.9 Docker; Chrome headless):**
- `tools/qa/run-all-gates.sh --runtime` (2026-10-20): **57 PASS / 1 FAIL**. Tek FAIL, Faz 6B4 admin HTTP testinin eski "dokuz action" beklentisiydi
  (bilinçli olarak 12 action'a çıkıldı); test güncellenip kapı **tek başına yeniden çalıştırıldı: 75/75**, ana DB/dosya/uploads farkı 0.
  Koşu sırasında Claude Code bellek baskısı nedeniyle sarmalayıcı kabuğu durdurdu; kapı betiği sahipsiz olarak sürdü ve sonuna kadar tamamlandı
  (sonuç satırı `BAŞARISIZ KAPI SAYISI: 1` = yukarıdaki kapı). Önceki koşulardan kalan 8 headless Chrome süreci kapatıldı.
- Statik/Node: kaynak sayıları; manifest 85, içerik manifesti 77, onay matrisi **52/52** + çıktılar byte-eşit (yayın kararı dosyası dahil);
  6B2/6B3 statik sözleşme 359; admin statik 43; Faz 12 26; tema ücret metni 5; tüm tema statik testleri; dist == src; kontrast 14/14;
  gizli bilgi / NUL / satır sonu boşluğu / PHP 8 sözdizimi taramaları; tanitim-site ve korunan dosyalar değişmedi; paketler 27/27.
- PHP 7.3.33 lint **197** dosya hatasız; eklenti birim testleri **1620/1620**, iki koşu byte-eşit (yeni `faz15-fee-catalog` paketi 50/50).
- WordPress runtime: apply/rollback/içerik 30+30+40+13+15; admin import 15+15+26+6+31+9+6+26; HTTP render/form 55 + SEO 38; sayfa paritesi
  293+213+213 + 41 + 307; yeterlilik 112, başvuru 90+20, başlık 94, KVKK 156; **Faz 15 ücret kataloğu 64/64 + 8/8**; render QA 388.
- Faz 15 runtime kapsamı (gerçek katalog, canlı ile aynı başlangıç): yayın önizlemesi 95/2/6; yanlış ifade ve bayat istek reddi; 10 batch;
  97 satırın 9 sayfa taranıp **97/97 satır ve 136/136 seçeneğin** (etiket+birim+tutar) kaynakla birebir eşleşmesi; her satırda standart cümle;
  18 bağlantısız satırda "Belirtilmemiştir" ve bağlantı yok; `meslek=` yok; bekletilen 6 yok; arama (ad/kod), sektör/seviye/birleşik filtre,
  geçersiz sektör, sayfa kenetleme; yeterlilik detayı; ikinci aktarım 103 unchanged/0 create/0 conflict; Chrome'da: seçim/arama, KDV/basım,
  TL önizlemesi, fiyat+oran+basım değişikliğinin liste ve detaya yansıması, geçersiz tutar / yalnız birim / sahte sektör reddi (önceki veri
  korunur), satır ekle/taşı/sil + min türetme, bağlantısız ipucu (kaynak kod değişmez), fiyat editörü/abone yetki retleri, 1024/1280/1600/375px
  admin ve 375/768/1280px ziyaretçi taşma ölçümleri, konsol hatası yok; sonra panelden değişen 2 kayıt conflict, araçla geri alma 93,
  eski (0.5.4) hash'li kayıt unchanged; ana DB farkı 0, debug.log boş.
- Bilgi notu: fiyat editörü/abone oturumlarında WordPress çekirdek admin betiğinin `wp/v2/users/me` isteği 404/403 alıyor (Faz 10 REST
  sertleştirmesinin mevcut yan etkisi; bu işle ilgisiz, düzeltilmedi).

**Çalıştırılmadı / doğrulanamadı:** canlı ve staging'de hiçbir şey çalıştırılmadı; canlı DB içeriği; gerçek DirectAdmin/LiteSpeed/PHP 7.3
üretim ortamı; e-posta. Faz 14 (KVKK) kapılarının tarihsel sonuçları bu raporda yeniden iddia edilmez; bu turun tam kapı koşusu aşağıdadır.

## 7. Kurulum, staging denemesi ve geri dönüş

Ön koşul: canlı ve staging'in **yedeği** (DB + `wp-content`) alınmış ve geri yüklemesi denenmiş olmalı. Staging şu an tema **0.6.8** (hash ile
doğrulanmıştı) — önce staging canlıyla eşitlenmeli.

1. **Staging:** eklenti `mavibelge-core-0.5.5.zip` → tema `mavibelge-theme-0.6.10.zip` (bu sırayla). `data/` dizinini (yeni
   `data/content/fee-publication.manifest.json` dahil) `MAVIBELGE_IMPORT_MANIFEST_DIR` konumuna koyun. Canlıda `data/` dizininin bulunup
   bulunmadığı bilinmiyor (`.wpress` yalnız `wp-content` taşır) — kontrol edin.
2. Sınav Ücretleri'ni açın: değişmeden 2 kayıt görünmeli; satırlarda yeni standart açıklama.
3. Araçlar → İçe Aktarım Dry-Run → `all` önizlemesi: ücretlerde `create 0`; 0.5.4 hash'li taslaklar `unchanged` olmalı. Conflict varsa durun.
4. **6) Ücret yayını → özet:** sayılar gerçek kayıtlardan hesaplanır (yerel karşılaştırma: hazır 95, yayında 2, bekletilen 6); fark yorumu ve durma koşulları için GÜNCEL yönerge `docs/ucret-staging-uygulama-yonergesi.md` §5.3–§5.4.
5. `wp-config.php`'de `WP_ENVIRONMENT_TYPE=staging`, `MAVIBELGE_IMPORT_APPLY_ENABLED` ve `MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED` = `true` (HTTPS).
6. Onay ifadesini yazıp yayınlayın (10'arlık 10 istek). Sonra: "Toplam 97 ücret kaydından", bağlantısız satırlarda "Belirtilmemiştir", bir
   tek fiyatlı ve bir çok seçenekli örnek PDF ile; bir yeterlilik detayı; mobil görünüm; Araçlar → Sağlık; `debug.log` boş.
7. Sabitleri kaldırın.
8. **Canlı (ayrı açık onayla):** aynı adımlar; ayrıca `WP_ENVIRONMENT_TYPE=production`, `MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED=true`,
   `MAVIBELGE_IMPORT_PRODUCTION_HOST` = birebir host. İşlem bitince sabitler kaldırılır.

**Geri dönüş:** (a) aynı bölümde `UCRET YAYINDAN KALDIR …` — yalnız aracın yayınladığı ve değişmemiş kayıtları taslağa alır (QA ve elle
değiştirilenlere dokunmaz); (b) kayıt bazında Kayıt Durumu/WordPress durumu taslak; (c) paket geri dönüşü: önce tema 0.6.9, sonra eklenti 0.5.4
zip'i (yedek: `E:\PROJELER\MaviBelge-koruma-20261019-195132\kvkk\files\wordpress-site\dist-packages\`). 0.5.4'e dönülürse yayınlanmış ücretler
görünmeye devam eder ama o sürüm `record_status`'ü yönetilen alan saydığı için `all` önizlemesinde conflict gösterir; (d) toplu: yedeğe dönüş.

## 8. Canlıda henüz uygulanmamış işler

- 0.5.5/0.6.10 kurulumu (staging ve canlı), `data/` dizini ve karar dosyası, toplu ücret yayını — **hiçbiri yapılmadı**.
- Canlı DB'de 101 taslak ücretin varlığı ve QA kayıtlarının değişmediği **doğrulanmadı** (önizleme adımı bunu gösterir).
- Açık kurum kararları: 19 kaydın MYK kodu, 5 ad farkı, `12UY0061-3/04` çift bağlantı, tarife PDF'lerinin medya eşlemesi.
- Commit/push/PR: kullanıcı onayı bekliyor. KVKK (Faz 14) ve bu iş aynı çalışma ağacında; ayırmak için §0 taban patch'i kullanılabilir
  (çakışan dosyalar: sürüm başlıkları, `dist/style.css`, `docs/admin-import-operations.md`, `tools/test-admin-import-static-contract.js`).

## 9. Yollar

- Rapor: `E:\PROJELER\MaviBelge-kvkk\raporlar\ucret-katalogu-faz15-raporu-20261020.md`
- Paketler: `E:\PROJELER\MaviBelge-kvkk\wordpress-site\dist-packages\mavibelge-core-0.5.5.zip` (GÜNCEL SHA-256 `2cc3c168…cc37`, 376.066 bayt; eski `5db7c547…` geçersiz),
  `mavibelge-theme-0.6.10.zip` (SHA-256 `17dfe7a9…6fc44`, 1.063.520 bayt), `checksums.sha256`, `package-manifest.json`
- Karar dosyası: `E:\PROJELER\MaviBelge-kvkk\wordpress-site\data\content\fee-publication.manifest.json`
- Admin yönergesi: `E:\PROJELER\MaviBelge-kvkk\wordpress-site\docs\ucret-yonetimi-kullanim.md`
