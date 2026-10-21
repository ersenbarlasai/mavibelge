# Faz 16 — Online Başvuru Meslek Listesi Ücret Kayıtlarından (eklenti 0.5.6, tema 0.6.11)

> Tarih: 2026-10-20. Çalışma alanı: `E:\PROJELER\MaviBelge`, dal `codex/application-fee-occupations` (taban `main` = `9aca081`).
> Kod 21 Ekim 2026'da `codex/application-fee-occupations` dalında commit edildi (`6b47268`), PR #5 açıldı. Deploy **yapılmadı**. Canlı/staging bağlantısı, yönetici girişi, veri veya ayar değişikliği **yok**.
> Ücret verisi, tutarlar ve yayın kararları değiştirilmedi; ücret verisi yeniden üretilmedi ya da import edilmedi.

## 1. Ne değişti

**Davranış.** `/online-basvuru/` sayfasındaki "Başvurulacak meslek/yeterlilik" listesi artık yeterlilik kataloğundan değil,
ziyaretçiye açık aktif **ücret kayıtlarından** otomatik oluşur. Ayrı bir manuel liste yoktur; Ücret Kayıtları ekranındaki her
değişiklik listeye yansır.

| Katman | Değişiklik |
|---|---|
| Eklenti `public/class-catalog-service.php` | Görünürlük kararı tek fonksiyonda: `public_fee_dto()` (yayında + Kayıt Durumu aktif + aktif dönem + geçerlilik penceresi + kanonik fiyat + gerçek sektör). Ücret aday sorgusu `CANDIDATE_CAP` (500) büyüklüğünde parçalı (`paged`), sessiz kesme yok; güvenlik sınırı aşılırsa `candidates_truncated()`. Yeni: `get_public_fee()` (önbelleksiz tek kayıt), `get_application_fee_rows()`, `application_fee_row()` |
| Eklenti `includes/forms/class-forms-fee-options.php` (yeni, saf) | Etiket "Ad — Seviye N — Kod", Türkçe sıralama (intl/mbstring gerekmez), kimlik biçimi, `?ucret=`/`?meslek=` ön seçim kararı, e-posta ayrıntıları |
| Eklenti `class-forms-schema.php` | Online başvuru meslek alanı `options_source => application_fees` (sınav talebi formu `qualifications` olarak kaldı) |
| Eklenti `public/class-forms-service.php` | Liste, ön seçim (`preselect_ambiguous`), seçeneksiz form (`options_missing`, jeton üretilmez), gönderimde önbelleksiz yeniden doğrulama ve anlaşılır Türkçe ret, e-posta ayrıntısı sunucu kaydından |
| Eklenti `class-forms-mail-builder.php` | İsteğe bağlı `$details`: seçilen tarife satırı + "Ücret kaydı / Meslek / Seviye / MYK kodu / Bağlı yeterlilik" alt satırları (konu ve başlıklar değişmedi) |
| Eklenti SEO | `online-basvuru` başlığı "Online Başvuru — Mavi Belge", açıklama gerçek işlevi anlatır (üreticide yalnız bu sayfa için açık istisna; `tanitim-site` değişmedi) |
| Tema | `content-application.php`: belirsiz ön seçim bildirimi (`role="status"`) ve "Şu anda online başvuruya açık meslek bulunmuyor" + telefon/e-posta; `single-mb_yeterlilik.php`: aynı yeterlilikte birden fazla tarife varsa her tarifede "Bu tarifeyle başvur" (`?ucret=`); `mavibelge_application_fee_url()`; dar ekran CSS |
| Sürümler | eklenti 0.5.5 → **0.5.6**, tema 0.6.10 → **0.6.11** |

## 2. Liste kuralı

Başvurulabilir ücret = Sınav Ücretleri sayfasında görünen ücret: WordPress durumu **Yayında**, **Kayıt Durumu = Aktif**, **Tarife
Dönemi = Aktif Tarife Dönemi**, bugün (site saati) **geçerlilik aralığında**, en az bir geçerli **fiyat seçeneği** ve gerçek bir
**sektör**. Kural yeniden yazılmadı; liste ve Sınav Ücretleri aynı `public_fee_dto()` kararını ve aynı nesil önbelleğini kullanır.
Önbellek anahtarı aktif dönem + günü içerir; ücret kaydı, `_mb_` meta, durum/çöp ve dönem değişikliği nesli artırır.

Kimlik: ücret kaydının WordPress yazı kimliği (MYK kodu değil). Gönderimde değer katı biçimde okunur, listede olmalıdır ve kayıt
önbelleksiz yeniden çözülür; meslek adı/seviye/kod/ilişki POST'tan alınmaz.

## 3. Test sonuçları

**Bu görevde gerçekten çalıştırılanlar (2026-10-20/21, yerel):**

| Test | Sonuç |
|---|---|
| Tam kalite kapısı `run-all-gates.sh --runtime` (depo dışı LF klon `E:\PROJELER\MaviBelge-qa-faz16`; 55 CRLF dosyası Windows çalışma kopyası düzeninde) | **60 PASS / 0 FAIL**, `TÜM KAPILAR GEÇTİ (yerel).`, çıkış kodu **0 (doğrudan yakalandı)**; 23:10:07 → 00:05:19 |
| Kod parmak izi | 817 dosya, koşu öncesi ve sonrası **aynı**; ana klasördeki 33 değişen/yeni dosya test edilen klonla CR hariç byte-eşit |
| PHP 7.3.33 lint | 199 dosya temiz |
| Eklenti birim testleri `tests/run.php` | **1653/1653** ×2 byte-eşit (yeni `faz16-application-fees.php`: 25) |
| Faz 16 runtime + Chrome `application-fees.sh` | **87/87**; ana DB/dosya/uploads farkı 0; debug.log 0 |
| Faz 12f başvuru (yeni sözleşmeye taşındı) | kapalı 113/113, açık 20/20 |
| Beş form güvenlik matrisi (iletişim) | 212/212 |
| KVKK onay bağlantısı | 156/156 |
| Faz 15 ücret kataloğu | 71/71 + 9/9 |
| Yeterlilik render / başlık geometrisi | 112/112, 94/94 |
| Paket testi / ücret veri paketi | 27/27, 13/13 (veri paketi aynı ad ve SHA-256 `a7a4173b…3fcae` ile yeniden üretildi: ücret verisi değişmedi) |
| Tema statik başvuru sözleşmesi | 32/32 |

**Faz 16 runtime senaryoları (87 kontrol):** 103 ücret → 103 seçenek (145 fiyat seçeneği 145 seçeneğe dönüşmez); değer = ücret kimliği;
etiket kayıttan; 19 kodsuz/bağlantısız ücret seçilebilir; Dokuma Operatörü Seviye 3/4 ayrı; iki Mobil Vinç tarifesi ayrı kimlik ve adla;
Beko Loder kodsuz; Türkçe sıra bağımsız yeniden hesaplamayla aynı; Sınav Ücretleri toplamı her adımda listeyle aynı. Ön seçim: `?ucret=`,
eski `?meslek=` (tek ücret), `?meslek=12UY0061-3/04` belirsiz (hiçbiri seçilmez, iki ad gösterilir), `?ucret` öncelik, 11 geçersiz
`?ucret` biçimi (sıfır, baştaki sıfır, negatif, bilinmeyen, yeterlilik/sayfa kimliği, HTML, aşırı uzun, ondalık, boşluk, dizi).
Dışlama + önbellek (önbellek açık): taslak, Kayıt Durumu arşivlendi/taslak, süresi bitmiş, gelecekte başlayan, yanlış dönem, çöp —
her biri listeden ve Sınav Ücretleri'nden düşer (102), nesil artar, geri alınca 103. Gün sınırı: yönetici kaydı olmadan site tarihi
ilerleyince (nesil aynı) kayıt düşer. Aktif dönem değişince uygun kayıt yok: form/jeton yok, telefon/e-posta alternatifi. 503 uygun ücret
(200 ve 500 aday sınırının üstü) → 503 seçenek ve Sınav Ücretleri 503. Gönderim: form açıldıktan sonra taslak/pasif/dönem/süre/çöp →
anlaşılır Türkçe ret, e-posta yok; **bayat önbellekte** (liste kaydı hâlâ gösterirken) POST önbelleksiz yeniden doğrulamayla reddedilir;
11 sahte/bozuk kimlik + dizi girdisi reddedilir; bağlantısız ücretle geçerli gönderim → 303 PRG + test mailer'ında doğru içerik
("MYK kodu: Belirtilmemiş", "Bağlı yeterlilik: Bağlantısız"); replay 400; bağlı ücretle gönderimde gerçek yeterlilik ilişkisi.
Yeterlilik detayında iki tarife için `?ucret=` bağlantıları. Chrome: seçim, Devam Et doğrulaması, ileri/geri, sunucu hatası sonrası adım +
korunan seçim (T.C. kimlik geri doldurulmaz), 375/390/768 px'te taşma yok, konsol temiz.

**Bu görevde düzeltilen test ortamı sorunları (kodun değil):** (1) ilk Faz 16 koşusu bellek yetersizliğinden Claude Code tarafından
durduruldu; toplu kayıt 420 → 400'e indirildi ve WP-CLI nesne önbelleği boşaltılarak yeniden koşuldu. (2) İki koşuda ana DB
karşılaştırması WordPress çekirdeğinin 30 dakikalık tema desen önbelleği (`_site_transient_wp_theme_files_patterns-*`) yüzünden kırmızıydı
(satırlar aynı; bir koşuda zaman aşımı yenilendi, bir koşuda AUTO_INCREMENT +2). Faz 16 runner'ının ana önekteki yardımcıları
(anlık görüntü, klon, silme; yalnız `$wpdb` kullanırlar) artık `--skip-themes --skip-plugins` ile çalışır; ölçüt gevşetilmedi. Son tam
koşu bu düzeltmeyle 60/0.
**Çalıştırılmayan:** staging/canlı doğrulaması (erişim yok), gerçek SMTP teslimi, aXe/Lighthouse, mutasyon (kırmızı) kanıtı koşusu.
**Loglar (Git dışı):** `E:\PROJELER\MaviBelge-qa-faz16-loglar\tam-kapi-2\` (son tam koşu), `deneme2\`, `deneme3\`, `tam-kapi\` (önceki).

## 4. Kurulum (staging önce; canlı ayrı onay)

1. Yedek: veritabanı + `wp-content` + mevcut tema/eklenti zip'leri (0.6.10/0.5.5 veya ortamda kurulu olan); geri yükleme provası.
2. `sha256sum -c checksums.sha256` (`wordpress-site/dist-packages/faz16-20261020/`).
3. Eklentiler → Yeni Ekle → Eklenti Yükle → `mavibelge-core-0.5.6.zip` → "Geçerli olanı yüklenenle değiştir"; eklenti etkin kalır.
4. Görünüm → Temalar → Tema Yükle → `mavibelge-theme-0.6.11.zip` → "Geçerli olanı yüklenenle değiştir".
5. Araçlar → Mavi Belge Sağlık: hata yok; Eklentiler/Temalar ekranında 0.5.6 / 0.6.11.
6. Kontrol (anonim tarayıcı): `/online-basvuru/` listesinde seçenek sayısı = Sınav Ücretleri'ndeki "Toplam N ücret kaydından" N;
   iki Mobil Vinç tarifesi ayrı; Beko Loder kodsuz; `?meslek=12UY0061-3%2F04` hiçbir şey seçmez ve iki tarifeyi listeler;
   `?meslek=10UY0002-3%2F03` Makine Bakımcı Seviye 3'ü seçer; sekme başlığı "Online Başvuru — Mavi Belge".
7. Sayfanın editörde `_mb_seo_title`/`_mb_seo_description` alanı doluysa o önceliklidir; "Demo" yazıyorsa elle düzeltilmelidir
   (bu görevde ortamdaki değer okunmadı).
8. Gerçek gönderim denemesi yalnız kurumun onayladığı sentetik/test verisiyle ve alıcı adresine erişimi olan kişiyle yapılır. Bu
   görevde gerçek SMTP teslimi doğrulanmadı.

Veri, ücret kararı, `wp-config.php` sabiti veya aktarım adımı GEREKMEZ.

## 5. Geri dönüş

- **Hızlı:** `mavibelge-core-0.5.5.zip` ve `mavibelge-theme-0.6.10.zip` aynı yöntemle yeniden yüklenir. Veritabanı şeması değişmediği
  için ek adım yoktur; liste yeniden yeterlilik kataloğundan gelir (eski davranış).
- **Tam:** yedekten veritabanı + `wp-content` geri yüklemesi.
- Eklenti ve tema birlikte geri alınmalıdır: 0.6.11 teması 0.5.5 eklentisiyle çalışır ama belirsiz ön seçim bildirimi görünmez;
  0.5.6 eklentisi 0.6.10 temasıyla çalışır ama "uygun meslek yok" bildirimi yerine boş form kabuğu görünür.

## 6. Açık / kapsam dışı

- Staging ve canlıda kurulum/doğrulama yapılmadı; ortamın kurulu sürümleri bilinmiyor (son bilinen: canlı 0.6.9/0.5.4, ücret paketi
  sonrası kullanıcı tarafından doğrulanmamış).
- Sınav talebi formunun isteğe bağlı meslek alanı değiştirilmedi (görev kapsamı online başvuru).
- Fiyat/birim seçimi başvuruya eklenmedi (kapsam dışı).
