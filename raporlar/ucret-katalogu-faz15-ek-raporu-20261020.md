# Faz 15 Ek Rapor — Bekletilen 6 Ücret, Nihai Kalite Kapısı, Dağıtım Bağımlılığı ve Staging Yönergesi

> **Güncel durum notu (20 Ekim 2026, konsolidasyon):** Bu rapor yazıldığı anın kaydıdır. Sonrasında: altı kayıt kullanıcı onayıyla
> yayına uygun oldu (karar dosyası 103 publish / 0 hold, veri paketi `mavibelge-ucret-verisi-20261020-7b720433.zip`;
> [`ucret-alti-kayit-onayi-20261020.md`](ucret-alti-kayit-onayi-20261020.md)); kullanıcı ücretleri yönetim panelinden elle yayınladı
> (ekran görüntüsü özeti: yayında 103, araçla geri alınabilir 0); kod Git'te commit edildi ve `main`'e alındı. "Commit yok",
> "canlı/staging değişmedi" ve "97/6" ifadeleri yalnız yazıldıkları an için geçerlidir. Güncel durum: [`proje-durumu.md`](proje-durumu.md).

> Tarih: 2026-10-20. Çalışma alanı: `E:\PROJELER\MaviBelge-kvkk`. Ana rapor: `raporlar/ucret-katalogu-faz15-raporu-20261020.md`.
> Staging/canlı kurulumu **yapılmadı**. Commit, push, sunucu bağlantısı, staging/canlı yazma veya deploy **yok**. Gerçek karar dosyası
> (`fee-publication.manifest.json`) **değiştirilmedi**: 97 yayın / 6 bekletme aynen duruyor. Mevcut KVKK (Faz 14) değişikliklerine dokunulmadı.

## 1. Bekletilen 6 ücret

Kaynak: referans `tanitim-site/assets/data/fees.js` (canlı referansla SHA-256 eşit), ücret manifesti, tarife PDF'i ("2026 Yeni Meslekler Ücret
Tarifesi"), yeterlilik manifesti (83 kayıt). Tutarlar KDV dahil, belge basım ücreti (1.500 TL) hariç.

| # | Kaynak meslek adı (referans = manifest) | PDF'teki yazım (sayfa) | Sev. / sektör | MYK kodu | Seçenekler ve tutarlar | Bekletme gerekçesi | Mevcut eşleme |
|---|---|---|---|---|---|---|---|
| 25 | Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci) | ad farkı yok (s.1) | 3 / İş Makineleri | `12UY0061-3/04` | Birim bazlı, 4 seçenek: B1-İş Organizasyonu, Gemi/Yük ve Liman Elleçleme Sahalarını Tanıma **14.800,00 TL**; B2-Mobil Vinci Yürütme, Konumlandırma ve Yük Elleçleme **14.800,00 TL**; B3-Çok Amaçlı Vinçleri Kullanma **10.200,00 TL**; B4-Sahil ve/veya Gemi Vincini Kullanma **14.800,00 TL** | Aynı yeterliliğe iki ücret bağlı (#86 ile) | Mobil Vinç Operatörü `[12UY0061-3/04]` |
| 68 | Metal Levha İşleme Tezgâh İşçisi | "METAL LEVHA TEZGÂH İŞÇİSİ" (s.3) | 3 / Makine | `12UY0086-3/02` | Sınav ücreti (B2-Pres) **9.750,00 TL** | PDF adı ≠ manifest adı | Metal Levha İşleme Tezgâh İşçisi `[12UY0086-3/02]` |
| 69 | Metal Levha İşleme Tezgâh Operatörü | "METAL LEVHA İŞL. TEZGÂH OP." (s.3) | 4 / Makine | `12UY0087-4/02` | Tek birim (B1-Abkant Pres, B2-Açık Profil Çekme/Rollform, B4-Punch Pres) **10.500,00 TL** | PDF adı ≠ manifest adı (kısaltma) | Metal Levha İşleme Tezgâh Operatörü `[12UY0087-4/02]` |
| 79 | Isı Yalıtımcısı | "ISI YALITIMCI" (s.3) | 3 / İnşaat | `12UY0057-3/01` | Sınav ücreti **15.000,00 TL** | PDF adı ≠ manifest adı | Isı Yalıtımcısı `[12UY0057-3/01]` |
| 86 | Mobil Vinç Operatörü (İş Makineleri Grubu) | "MOBİL VİNÇ OPERATÖRÜ" (s.3) | 3 / İş Makineleri | `12UY0061-3/04` | Sınav ücreti **9.500,00 TL** | PDF adı ≠ manifest adı + aynı yeterliliğe iki ücret (#25 ile) | Mobil Vinç Operatörü `[12UY0061-3/04]` |
| 87 | Kazıcı Yükleyici (Beko Loder) Operatörü | "KAZICI YÜKLEYİCİ (Beko Loder) OPERAÖTÜRÜ" (s.3) | 3 / İş Makineleri | yok | Sınav ücreti **23.000,00 TL** | PDF adı ≠ manifest adı (PDF'te yazım hatası) | Bağlantısız (83 yeterlilik içinde "Kazıcı"/"Beko" adlı kayıt yok; referansta da kodsuz) |

Toplam: 6 kayıt, **9 seçenek** (4+1+1+1+1+1). 97 + 6 = 103 kayıt; 136 + 9 = 145 seçenek.

**Bu turda yeniden doğrulananlar:** altı kaydın ad, seviye, sektör, MYK kodu, fiyatlandırma türü, seçenek etiketi ve tutarı referans `fees.js`
ile manifestte **alan alan aynı** (birimler ikisinde de boş). Yani referansın gösterdiği ad zaten manifest adıdır; PDF'teki büyük harfli/kısaltılmış
yazım referansa hiç taşınmamıştır. Eşleme tahmin edilmedi: #25/#86'nın kodu ve #68/#69/#79'un bağlantısı manifestteki kayıtlı
`qualification_source_key` değeridir; #87 için yeterlilik kataloğunda karşılığı olmadığından bağlantı üretilmedi.

### 1.1. Çözüm önerileri (karar sizde; uygulanmadı)

| Kayıt | Öneri | Gerekçe | Alternatif |
|---|---|---|---|
| #68, #69, #79, #87 (ad farkı) | **Referanstaki (manifest) adla yayınla** | Referans site bu adları gösteriyor; adlar MYK yeterlilik adına dayanıyor; PDF farkı kısaltma/yazım hatası. Tutarlar PDF ile aynı | PDF yazımını kullanmak: referansla eşitlik bozulur, önerilmez |
| #86 (ad farkı + çift bağlantı) | **Referanstaki adla ve mevcut kodla yayınla** | Referans bu satırı `12UY0061-3/04` koduyla gösteriyor | Bağlantıyı kaldırıp "Belirtilmemiştir" göstermek: referanstan farklı olur |
| #25 + #86 (aynı yeterlilik) | **İkisini de yayınla**; yeterlilik detayı iki ücreti adlarıyla ayrı ayrı gösterir (tema bunu zaten yapıyor) | Referans listede ikisini de aynı kodla gösteriyor; referansta yeterlilik detayında ücret bloğu yok, bu yüzden detay gösterimi referansla çelişmez | Detayda yalnız birini göstermek: hangisi olduğu kurum kararı olur, kod değişikliği gerekir |
| #87 MYK kodu | Bağlantısız kalsın | Kaynaklarda kod yok; uydurma kod üretilmez | Kurum gerçek kodu bildirirse yeterlilik kaydıyla birlikte eklenir |

Öneriler kabul edilirse yapılacak tek değişiklik `tools/fees/build-approval-matrix.js` içindeki kurum kararı kaydıdır. Karar dosyası ve yeni adlı
veri paketi yeniden üretilir. Ücret kayıtlarının içeriği değişmediği için yeniden aktarım gerekmez; önizleme bu 6 kaydı `hazır` gösterir.

### 1.2. Varsayım testi: altısı da yayınlanırsa referansla aynı mı?

Gerçek karar dosyasına dokunmadan, yalnız silinebilir fixture veritabanında karar dosyasının `/tmp` kopyasıyla denendi. Altı kaydın kararı
"yayınla" yapıldı (kayıt özetleri aynı kaldı); sonra mevcut araçla etkinleştirildi ve sayfa tarandı (gerçek WordPress 6.9.9 + PHP 7.3):

- önizleme: hazır 101, yayında 2 (QA), bekletilen 0;
- 11 istekte etkinleştirildi;
- "Toplam 103 ücret kaydından", 9 sayfa;
- **103/103 satır ve 145/145 seçenek** (etiket + birim + tutar) kaynakla birebir;
- 19 bağlantısız satır, 84 bağlı satır;
- `12UY0061-3/04` detayında iki ücret adlarıyla ayrı ayrı (14.800,00 / 10.200,00 / 9.500,00 TL).

Sonuç **7/7** (nihai koşuda da; §2). Yani hedefin (103 ücret / 145 seçenek referansla aynı) önündeki tek engel bu altı kaydın kurum kararıdır;
veri veya kod engeli yoktur.

## 2. Nihai kalite kapısı koşusu

Komut: `bash wordpress-site/tools/qa/run-all-gates.sh --runtime <log>/gates`. Ortam: PHP 7.3.33 + WordPress 6.9.9 Docker, headless Chrome.
Bu koşu, aşağıdaki son kod üzerinde **tek ve kesintisiz** çalıştı. Önceki koşularla birleştirilmedi.

| | |
|---|---|
| Log dizini | `E:\PROJELER\MaviBelge-kvkk\raporlar\test-loglari\faz15-nihai-kapi-20261020-071938\` |
| Zaman | 07:19:45 → 07:53:14 (2026-10-20, +03:00) |
| Sonuç | **59 PASS / 0 FAIL**, son satır `TÜM KAPILAR GEÇTİ (yerel).` |
| Çıkış kodu | **0**, betiğin son satırından türetildi (aşağıdaki nota bakın) |
| Kod parmak izi | `wordpress-site/` altındaki 458 dosyanın SHA-256 listesi; koşudan önce ve sonra **aynı** (`kod-parmak-izi-once/sonra.sha256`, liste özeti `75dbc0e6…1324`). Koşudan sonra yalnız rapor dosyaları değişti |
| Ana DB / dosya / uploads farkı | Her runtime kapısında 0. debug.log 0 satır |

**Çıkış kodu notu (dürüstlük):** çıkış kodunu `cikis-kodu.txt` dosyasına yazacak sarmalayıcı kabuk, koşu sürerken Claude Code tarafından
"sistem belleği kritik" gerekçesiyle durduruldu. `run-all-gates.sh` (PID 837) bundan etkilenmedi, kesintisiz sürdü ve sonuna kadar tamamlandı;
kapı satırları ve son satır aynı log dosyasına yazıldı. Betik `exit "$FAILS"` ile çıkar ve "TÜM KAPILAR GEÇTİ" satırını yalnız FAILS=0 iken
basar. Bu yüzden 0 değeri doğrudan yakalanmadı, bu satırdan türetildi; not `cikis-kodu.txt` içinde de yazılı. Koşu yeniden başlatılmadı.

**Başarısız ve geçersiz sayılan önceki koşu:** `faz15-nihai-kapi-20261020-065015-BASARISIZ-dosya-adi-uzun\`.

- Sonuç: 57 PASS + 1 FAIL, çıkış 1.
- FAIL nedeni: Faz 15 kapı etiketini uzatmıştım; log dosya adı 255 bayt sınırını aştı ("File name too long") ve kapı komutu **hiç çalışmadı**.
- Düzeltme: etiket kısaltıldı ve `gate()` log adını 150 karakterle sınırlıyor.
- Ardından **bütün** kapılar baştan koşuldu (yukarıdaki koşu).

Önceki turdaki "57/58 + tek başına 75/75" sonucu da tümü-geçti sayılmaz; bu ek koşuyla değiştirildi.

**Bu koşudaki gerçek sayaçlar (log dosyalarından):**

- **Statik/Node:**
  - kaynak sayıları 9/9; extract güvenlik 71; manifest 85; finalizasyon 32/32; içerik manifesti 12/12 + 77;
  - onay matrisi 52/52 + çıktılar byte-eşit;
  - PDF metin katmanı çapraz kontrolü: PASS, Güzellik PDF s.2 için önceden bilinen "ATLANDI" notuyla — metin katmanında fiyat yok;
  - pdftotext 7/7; 6B2 statik 359; admin statik 43; Faz 12 sayfa 82 + 26; tema ücret metni 5;
  - tema statik testleri (içerik/form 294, KVKK 27, yeterlilik 85, sayfa sunum 80, ikon 43 vb.); dist == src; SEO/yönlendirme byte-eşit;
    kontrast 14/14;
  - node --check, gizli bilgi / NUL / satır sonu / PHP 8 sözdizimi taramaları; tanitim-site ve korunan dosyalar değişmedi; `git diff --check`;
  - paketler 27/27; **ücret veri paketi 13/13**.
- **PHP 7.3.33 ve birim testleri:** lint 197 dosya; eklenti birim testleri **1628/1628**, iki koşu byte-eşit.
- **WordPress runtime:**
  - apply/rollback 30+30+40;
  - admin import 26+26+31+15+13+9+6+6+15+15;
  - admin HTTP 75/75; HTTP render/form 55; SEO 38;
  - sayfalar 41 + 307; parite 293+213+213;
  - yeterlilik 112; başvuru 90+20; iletişim/formlar 212; başlık 94; KVKK 156;
  - **Faz 15 ücret: 70/70 + 9/9 + 7/7 (varsayım)**;
  - render QA 388.

## 3. Dağıtım bağımlılığı

### 3.1. Çalışma anında gereken dosyalar

Kodla belirlendi (`MaviBelge_Core_Import_Manifest_Loader`, `Dry_Run_Service::run_stage('all')`, `Fee_Publisher::preview()`):

| Dosya | Okuyan | Ücret yayını için |
|---|---|---|
| `data/content/sectors.manifest.json` | `load_all()` (all aşaması) | **Gerekli** |
| `data/content/qualifications.manifest.json` | `load_all()` | **Gerekli** |
| `data/content/fees.manifest.json` | `load_all()` | **Gerekli** |
| `data/content/fee-publication.manifest.json` | `load_fee_publication()` | **Gerekli** |
| `pages.manifest.json` | `load_pages()` (pages aşaması) | Gereksiz |
| `news`/`references`/`faqs.manifest.json`, `data/sources/reference-logos/*.png` | `load_content()` (content aşaması) | Gereksiz |
| `data/schema/*`, `data/mapping/*`, `data/redirects/*`, `data/sources/approved/*` | Çalışma anında okunmaz (Node araçları/rapor; `approved` yolu yalnız metin denetimi) | Taşınmaz |

Liste, eklentide `MaviBelge_Core_Import_Fee_Publisher::REQUIRED_FILES` sabiti olarak tanımlandı. Birim testi bunun yükleyicinin sabit adlarıyla,
paket testi de paket içeriğiyle birebir aynı olduğunu doğrular.

### 3.2. Nereye, hangi yöntemle

- Konum: varsayılan konum WordPress kökü altındaki `data/content`'tir ve web'den erişilebilir. Bu yüzden önerilen konum **web kökü dışındaki** bir
  dizindir (ör. `/home/<hesap>/mavibelge-data/content`). Ayrıca `wp-config.php`'de `MAVIBELGE_IMPORT_MANIFEST_DIR` o dizini göstermelidir.
- Yöntem: sistem yöneticisi zip'i DirectAdmin Dosya Yöneticisi ile yükleyip açar (SSH/WP-CLI gerekmez). Ayrıntı: yönerge §4.1.
- Paket içinde `.htaccess` (Apache 2.4 + 2.2 erişim reddi) ve boş `index.php` dosyaları var. Dizin yanlışlıkla web kökü altına konursa bunlar korur.

### 3.3. Eksik/bozuk dosyada davranış (yeni; test edildi)

- Önceden ücret ekranında katalog manifesti eksikliği yanlış metinle ("Sayfa planı okunamadı") görünüyordu. Şimdi her durumun ayrı kodu var
  (`catalog_manifests_unavailable` / `decisions_unavailable` / `decisions_invalid`). Okunamayan dosyanın adı mutlak yol olmadan listelenir ve
  gerekli dosya listesi gösterilir.
- Bu durumlarda önizleme, etkinleştirme ve geri alma reddedilir; **hiçbir yazma yapılmaz**. Bu iki yerde kanıtlandı:
  - birim testinde sahte dünya imzasıyla;
  - gerçek WordPress'te `CHECKSUM TABLE` ile (posts, postmeta, audit, run, run-item tabloları): `fees.manifest.json` eksik, karar dosyası eksik,
    dizin yok.

### 3.4. Ayrı dağıtım paketi

`node tools/package/build-fee-data-package.js --write` deterministik üretir. Yeni kalite kapısı `test-fee-data-package.js` ile **13/13**
doğrulandı. Dosya adı içeriğe bağlıdır: içerik değişirse ad da değişir, eski paket ezilmez (aynı ada farklı içerik yazmayı reddeder).

| Dosya | Bayt | SHA-256 |
|---|---|---|
| `mavibelge-ucret-verisi-20261019-c29b3e89.zip` | 19.252 | `6c000b412ae16aaac2ac49c8f9d24101a0bd632cc2889a3b816ee1f61d3723dd` |
| `…-icerik.sha256` (7 dosyanın özeti; açıldıktan sonra `sha256sum -c`) | 737 | — |
| `…-manifest.json` (dosya, bayt, özet, kaynak yolu, 103/145, 97/6) | 2.373 | — |
| `mavibelge-core-0.5.5.zip` (yeniden üretildi) | 376.066 | `2cc3c168d542de16a18a899988e9af3aaa31d5932bd72b1804f2640e66eacc37` |
| `mavibelge-theme-0.6.10.zip` (değişmedi) | 1.063.520 | `17dfe7a9434b870bea04f8c80e21680c83d44a006651a6596d2f4d8a51d6fc44` |

`sha256sum -c checksums.sha256 mavibelge-ucret-verisi-20261019-c29b3e89.zip.sha256` → üçü de OK. `mavibelge-core-0.5.4.zip` ve
`mavibelge-theme-0.6.9.zip` koruma yedeğiyle byte-eşit (ezilmedi).

Gerçek WordPress'te zip açıldı, önizleme paketin `content/` dizininden alındı: plan özeti ve tüm sayılar gerçek `data/content` ile **aynı**.

## 4. Gerçek ortam sayımı ("95 hazır, 2 yayında, 6 bekletiliyor" sabit değil)

- Önizleme zaten her ücreti sunucudaki gerçek kayıttan sınıflandırıyordu. Ek olarak şimdi şunları da gösterir:
  - **fark dökümü:** yayına uygun olup hazır/yayında olmayanlar, neden bazında (oluşturulmamış / sapmış / görünmez / beklenmeyen);
  - sapmış kayıtların **gerçek aktarım kararı** (update = eski manifest, conflict = panelden değişmiş, blocked/invalid = bağımlılık);
  - **bekletilen ama yayında** kayıt sayısı;
  - **sitedeki tüm ücret kayıtları envanteri:** toplam, ziyaretçiye görünen ve manifest dışı (elle eklenmiş veya eski anahtarlı) kayıtlar. Araç bunlara dokunmaz.
- Önizleme, staging'de 101 taslağın var olduğunu **varsaymaz**: bulunamayan kayıt `oluşturulmamış` sayılır, yönerge §7.1'e göre önce `all` aşaması gerekir.
- Test kapsamı:
  - **birim:** manifest dışı 2 kayıt, fark dökümü, bekletilen-ama-yayında;
  - **gerçek WordPress, başlangıç durumu:** 103 kayıt, görünen 2, manifest dışı 0, fark 0;
  - **gerçek WordPress, panel düzenlemesinden sonra:** "sapmış 2 (aktarım kararı conflict 2)", manifest dışı 1 (fiyat editörünün TEST taslağı, görünmez).
- Yorum tablosu ve durma koşulları: yönerge §5.3–§5.4.

## 5. Staging uygulama ve geri dönüş yönergesi

Yeni belge: `wordpress-site/docs/ucret-staging-uygulama-yonergesi.md`. Geçici izinlerin tam adları:

| Sabit | Staging | Üretim (ayrı onay) | Ne zaman |
|---|---|---|---|
| `MAVIBELGE_IMPORT_MANIFEST_DIR` | `'/home/<hesap>/mavibelge-data/content'` | aynı | Kalıcı olabilir; izin değil, okuma konumu |
| `WP_ENVIRONMENT_TYPE` | `'staging'` (zaten olmalı) | `'production'` | — |
| `MAVIBELGE_IMPORT_APPLY_ENABLED` | `true` | `true` | Yalnız işlem penceresinde |
| `MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED` | `true` | `true` | Yalnız işlem penceresinde |
| `MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED` | **tanımlanmaz** | `true` | Yalnız üretim penceresinde |
| `MAVIBELGE_IMPORT_PRODUCTION_HOST` | **tanımlanmaz** | birebir host | Yalnız üretim penceresinde |

**Açma koşulları:**

- yedek ve geri yükleme provası tamam;
- paket özetleri doğrulandı;
- kurulum tamam ve Sağlık ekranı temiz;
- salt okunur önizleme kaydedildi ve durma koşulu yok;
- sistem yöneticisi, yönetici ve kontrol eden kişi hazır.

**Kapanma:** iş bitince, ilk hata kodunda, durma koşulunda veya pencere süresi dolunca hemen.

**Kontrol:** Sistem kapıları "AÇIK"/"KAPALI" satırı, "Engel" satırları ve `wp-config.php` kopyasıyla fark karşılaştırması.

**İşlem sonrası kontrol listesi ve üç düzeyli geri dönüş:** yönerge §8–§9 (araçla, kayıt bazlı, yedekten).

## 6. Bu turda değişen dosyalar

- Eklenti:
  - `includes/import/class-import-fee-publisher.php`:
    - eksik dosya kodları ve dosya adları;
    - `REQUIRED_FILES`;
    - envanter;
    - fark dökümü;
    - aktarım kararı sayımı;
    - bekletilen-ama-yayında sayımı.
  - `includes/import/class-import-runtime-factory.php`: envanter bağımlılığı.
  - `includes/import/class-import-admin-run-service.php`: yeni alanlar.
  - `public/class-catalog-service.php`: salt okunur `fee_inventory()`.
  - `admin/assets/import-apply.js`: yeni metinler, envanter tablosu, fark satırları.
  - `tests/suites/faz15-fee-catalog.php`: 8 yeni doğrulama (1620 → 1628).
- Araçlar:
  - `tools/package/build-fee-data-package.js` ve `test-fee-data-package.js` (yeni);
  - `build-packages.js` (yalnız `buildZip` dışa aktarımı);
  - `tools/runtime-test/lib/unzip.js` (yeni);
  - `fee-catalog.sh`, `fee-catalog-test.js`, `scripts/fee-catalog-fixtures.php`: eksik dosya, paket, envanter ve varsayım senaryoları;
  - `tools/qa/run-all-gates.sh`: veri paketi kapısı.
- Belgeler:
  - `docs/ucret-staging-uygulama-yonergesi.md` (yeni);
  - `docs/admin-import-operations.md` (§1, §8.3);
  - `docs/ucret-yonetimi-kullanim.md`;
  - `tools/fees/templates/staging-aktivasyon-plani.md` ve yeniden üretilen `raporlar/veri-aktarim-raporlari/ucret-2026-onay/staging-aktivasyon-plani.md`
    (karar dosyası byte-eşit kaldı).
- Paketler: `mavibelge-core-0.5.5.zip` yeniden üretildi (kod değişti; yeni SHA aşağıda). 0.5.5 hiçbir yere dağıtılmamıştı; ana rapordaki eski
  0.5.5 özeti (`5db7c547…`) **geçersizdir**. Tema 0.6.10 zip'i değişmedi. Eski 0.5.4/0.6.9 zip'leri ezilmedi.

## 7. Açık kalanlar

- Bekletilen 6 kayıt için kurum kararı (§1.1).
- 19 bağlantısız kaydın MYK kodu ve tarife PDF'lerinin medya eşlemesi (önceki rapordan, değişmedi).
- Staging/canlı kurulumu, gerçek ortam sayımı ve yayın: **yapılmadı**. Staging'in tema sürümü ve kayıt durumu bu turda doğrulanmadı.
- Gerçek sunucu ortamı (DirectAdmin, `open_basedir`, Apache/LiteSpeed `.htaccess` davranışı) yerelde doğrulanamaz; yönerge §4.1'de kontrol adımı var.
- Commit/push: kullanıcı onayı bekliyor.
