# Form Güvenlik Sözleşmesi (Faz 8)

Kod: `includes/forms/*` (SAF), `public/class-forms-service.php` (WordPress bağlantısı), `admin/class-forms-admin.php`,
tema `template-parts/forms/*`. Testler: `tests/suites/faz8-forms.php` (SAF, 40 doğrulama) ve
`tools/runtime-test/content-forms-http-test.js` (gerçek HTTP, form bölümü).

## Formlar (tek merkezi şema: `MaviBelge_Core_Forms_Schema`)
`contact` (iletisim), `application` (online-basvuru, **hassas**), `exam_request` (sinav-talepleri), `complaint`
(itiraz-sikayet), `job_application` (is-basvurusu, **hassas**). Her formun **kapalı alan allowlist'i** vardır; gönderilen başka
her anahtar yok sayılır. Alan etiketleri/yapısı statik referanstaki (tanitim-site) formlarla aynıdır.

## Etkinleştirme kapısı (`MaviBelge_Core_Forms_Config::gate`)
Varsayılan: **hepsi kapalı**. Bir form yalnız şunların **tümü** sağlanınca açılır: `enabled`, kurumca onaylı KVKK metni +
sürümü + onay işareti, geçerli alıcı e-posta, hassas alanlı formlarda `sensitive_fields_approved`, dosya alanlı formlarda
`uploads_approved`. Kapalı formda **hiçbir `<form>` çizilmez**; ziyaretçi genel "şu anda kullanılamıyor" iletisini görür, kapalı
**nedenleri yalnız `manage_options` yetkisine** (sayfada ve *Ayarlar → Mavi Belge Formları* ekranında) gösterilir. Karar
gelince yalnız yapılandırmayla etkinleşir; kod değişikliği gerekmez.

## Gönderim akışı (`MaviBelge_Core_Forms_Service::process`)
1. POST + geçerli form kimliği + formun **kendi sayfası** + kapı açık.
2. WordPress nonce (`_mb_nonce`, eylem `mb_form_<id>`).
3. Bal küpü (görünmez, `aria-hidden`, `tabindex=-1`) doluysa **sessiz sahte başarı**; e-posta yok.
4. İmzalı zaman damgalı tek-kullanımlık jeton (HMAC-SHA256): imza, ≥3 sn / ≤2 sa yaş, **tekrar kullanım (replay) reddi**.
5. **FAIL-CLOSED oran sınırı** (istemci HMAC'i + form geneli; ham IP saklanmaz; sayaç okunamaz/yazılamazsa reddedilir).
6. Sunucu tarafı doğrulama (`Forms_Validator`): tür/uzunluk/seçenek listesi/telefon/e-posta (CR/LF ve virgül/noktalı virgül
   reddi)/T.C. kimlik algoritması. Hatada **aynı yanıtta yeniden çizim**: hassas olmayan değerler geri doldurulur, hassas
   alanlar asla; hata metinleri girilen değeri **yansıtmaz**.
7. Dosyalar (yalnız izinli formlar): uzantı allowlist (pdf/jpg/jpeg/png/doc/docx), çift uzantı ve tehlikeli uzantı reddi,
   5 MB, **sunucuda finfo MIME** (istemci `type` yok sayılır), rastgele ad, `.htaccess`+`index.php` korumalı özel geçici dizin;
   **e-postadan hemen sonra silinir** (kalıcı saklama yok).
8. E-posta `MaviBelge_Core_Forms_Mailer` arayüzü arkasında (`wp_mail`); konu sabit; alıcı yapılandırmadan; başlıklarda CR/LF
   yasak (kurucu `null` döner). Başarıda jeton tüketilir ve **PRG (303)**: URL yalnız `mb_form_status=success&mb_form=<id>`.

Kişisel veri URL'ye, loga veya audit context'e **yazılmaz**: audit yalnız `form_submitted`/`form_rejected` + form kimliği +
sabit sonuç kodu taşır. Testlerde **gerçek e-posta gönderilmez** (SAF testlerde sahte adaptör; HTTP testinde
`pre_wp_mail` kısa devre + yalnız sayaç).

## Kanıt
SAF: `tests/suites/faz8-forms.php`. HTTP (gerçek WordPress, mbfx_ fixture): token<3sn, geçersiz nonce, kurcalanmış imza, CRLF
enjeksiyonu, boş alanlar + erişilebilir hata özeti, geri doldurma, bal küpü, geçerli gönderim + PRG, replay, kapalı form 503,
sayfa uyuşmazlığı, oran sınırı 429. Mutasyon kanıtı: 10 kritik kapı tek tek bozulup testlerin yakaladığı doğrulandı (rapor).
