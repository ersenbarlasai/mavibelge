<?php
/**
 * Faz 8 — güvenli form altyapısının SAF testleri (şema, doğrulayıcı, yapılandırma/kapı, güvenlik, ileti kurucusu).
 * WordPress gerektirmez. Test verisi tamamen SENTETİKTİR (gerçek kişi, T.C. kimlik no, telefon, e-posta YOK; e-posta
 * alanları .example alan adı, T.C. numarası yalnız algoritmik olarak geçerli sentetik bir sayıdır).
 */

$FS = 'MaviBelge_Core_Forms_Schema';
$FV = 'MaviBelge_Core_Forms_Validator';
$FC = 'MaviBelge_Core_Forms_Config';
$FX = 'MaviBelge_Core_Forms_Security';
$FM = 'MaviBelge_Core_Forms_Mail_Builder';

/* ---------------- şema ---------------- */
mb_test( 'Faz 8 şema: beş form (contact, application, exam_request, complaint, job_application); her birinin sayfa slug\'ı statik referansla aynı',
	array( 'contact', 'application', 'exam_request', 'complaint', 'job_application' ) === $FS::FORM_IDS
	&& 'iletisim' === $FS::get( 'contact' )['page_slug'] && 'online-basvuru' === $FS::get( 'application' )['page_slug'] && 'sinav-talepleri' === $FS::get( 'exam_request' )['page_slug']
	&& 'itiraz-sikayet' === $FS::get( 'complaint' )['page_slug'] && 'is-basvurusu' === $FS::get( 'job_application' )['page_slug'] && null === $FS::get( 'bogus' ) && null === $FS::get( array( 'contact' ) ) );
mb_test( 'Faz 8 şema: sayfa slug -> form kimliği; bilinmeyen -> null',
	'contact' === $FS::form_id_for_page_slug( 'iletisim' ) && 'job_application' === $FS::form_id_for_page_slug( 'is-basvurusu' ) && null === $FS::form_id_for_page_slug( 'hakkimizda' ) && null === $FS::form_id_for_page_slug( '' ) );
mb_test( 'Faz 8 şema: her formda consent alanı ZORUNLU; her alan adı benzersiz; her alanın türü izinli kümede',
	(function () use ( $FS ) {
		foreach ( $FS::FORM_IDS as $id ) {
			$names = $FS::allowed_field_names( $id );
			if ( count( $names ) !== count( array_unique( $names ) ) || ! in_array( 'consent', $names, true ) ) {
				return false;
			}
			foreach ( $FS::get( $id )['fields'] as $f ) {
				if ( ! in_array( $f['type'], array( 'text', 'email', 'tel', 'textarea', 'select', 'radio', 'file', 'consent' ), true ) ) {
					return false;
				}
				if ( 'consent' === $f['type'] && true !== $f['required'] ) {
					return false;
				}
			}
		}
		return true;
	})() );
mb_test( 'Faz 8 şema: hassas alanlar yalnız application (T.C. kimlik + belge) ve job_application (CV); dosya alanı yalnız bu ikisinde',
	true === $FS::has_sensitive_fields( 'application' ) && true === $FS::has_sensitive_fields( 'job_application' ) && false === $FS::has_sensitive_fields( 'contact' ) && false === $FS::has_sensitive_fields( 'exam_request' ) && false === $FS::has_sensitive_fields( 'complaint' )
	&& true === $FS::has_file_fields( 'application' ) && true === $FS::has_file_fields( 'job_application' ) && false === $FS::has_file_fields( 'contact' ) && false === $FS::has_sensitive_fields( 'bogus' ) );
mb_test( 'Faz 8 şema: allowlist kapalı — honeypot/jeton/nonce/form alanları izinli alan listesinde YOK; bilinmeyen form -> boş liste',
	! in_array( $FS::HONEYPOT_FIELD, $FS::allowed_field_names( 'contact' ), true ) && ! in_array( $FS::TOKEN_FIELD, $FS::allowed_field_names( 'contact' ), true ) && ! in_array( $FS::NONCE_FIELD, $FS::allowed_field_names( 'contact' ), true ) && array() === $FS::allowed_field_names( 'bogus' ) );
mb_test( 'Faz 8 şema: dosya sınırları (5 MB) ve izinli türler (pdf/jpg/jpeg/png/doc/docx); svg/html/php YOK',
	5242880 === $FS::UPLOAD_MAX_BYTES && array( 'pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx' ) === array_keys( $FS::UPLOAD_TYPES ) );

/* ---------------- doğrulayıcı ---------------- */
$fv_contact = array( 'full_name' => 'Test Kullanıcı', 'phone' => '0212 000 00 00', 'email' => 'test@ornek.example', 'profession' => '', 'message' => "Merhaba,\nbu bir testtir.", 'consent' => '1' );
$fv_ok      = $FV::validate( 'contact', $fv_contact );
mb_test( 'Faz 8 doğrulama: geçerli iletişim gönderimi kabul; clean yalnız allowlist alanlarını taşır; consent true',
	true === $fv_ok['ok'] && array() === $fv_ok['errors'] && true === $fv_ok['clean']['consent'] && 'Test Kullanıcı' === $fv_ok['clean']['full_name'] && ! array_key_exists( 'profession', $fv_ok['clean'] ) );
$fv_extra = $FV::validate( 'contact', array_merge( $fv_contact, array( 'admin' => '1', 'role' => 'administrator', 'post_id' => '5', $FS::HONEYPOT_FIELD => '' ) ) );
mb_test( 'Faz 8 doğrulama: allowlist dışı alanlar (admin, role, post_id, honeypot) clean/echo\'ya GİRMEZ (mass-assignment yok)',
	true === $fv_extra['ok'] && ! array_key_exists( 'admin', $fv_extra['clean'] ) && ! array_key_exists( 'role', $fv_extra['clean'] ) && ! array_key_exists( 'post_id', $fv_extra['clean'] ) && ! array_key_exists( $FS::HONEYPOT_FIELD, $fv_extra['clean'] ) && ! array_key_exists( 'role', $fv_extra['echo'] ) );
$fv_missing = $FV::validate( 'contact', array() );
mb_test( 'Faz 8 doğrulama: zorunlu alanlar eksikse alan başına hata (full_name, email, message, consent); hata metni değer yansıtmaz',
	false === $fv_missing['ok'] && isset( $fv_missing['errors']['full_name'], $fv_missing['errors']['email'], $fv_missing['errors']['message'], $fv_missing['errors']['consent'] ) && ! isset( $fv_missing['errors']['phone'] ) );
$fv_bad = $FV::validate( 'contact', array_merge( $fv_contact, array( 'email' => "kotu@ornek.example\r\nBcc: baska@ornek.example", 'full_name' => array( 'a' ) ) ) );
mb_test( 'Faz 8 doğrulama: e-postada CR/LF (başlık enjeksiyonu) reddedilir; dizi değer reddedilir; hata metninde girilen değer YOK',
	false === $fv_bad['ok'] && isset( $fv_bad['errors']['email'], $fv_bad['errors']['full_name'] ) && false === strpos( json_encode( $fv_bad['errors'] ), 'Bcc' ) && false === strpos( json_encode( $fv_bad['errors'] ), 'ornek.example' ) );
mb_test( 'Faz 8 doğrulama: e-posta biçimi — geçerli kabul; boşluk/virgül/noktalı virgül/tırnak/çift @ reddedilir',
	$FV::is_valid_email( 'ad.soyad+etiket@ornek.example' ) && ! $FV::is_valid_email( 'a b@ornek.example' ) && ! $FV::is_valid_email( 'a@ornek.example, b@ornek.example' ) && ! $FV::is_valid_email( 'a@ornek.example;b@ornek.example' )
	&& ! $FV::is_valid_email( '"a"@ornek.example' ) && ! $FV::is_valid_email( 'a@@ornek.example' ) && ! $FV::is_valid_email( '' ) && ! $FV::is_valid_email( array() ) && ! $FV::is_valid_email( str_repeat( 'a', 250 ) . '@ornek.example' ) );
mb_test( 'Faz 8 doğrulama: telefon 7-15 rakam, yalnız rakam/boşluk/()-+; harf ve çok kısa/uzun reddedilir',
	$FV::is_valid_phone( '0212 000 00 00' ) && $FV::is_valid_phone( '+90 (212) 000-00-00' ) && ! $FV::is_valid_phone( '12345' ) && ! $FV::is_valid_phone( '0212 abc 00 00' ) && ! $FV::is_valid_phone( str_repeat( '1', 16 ) ) && ! $FV::is_valid_phone( '' ) );
mb_test( 'Faz 8 doğrulama: T.C. kimlik no algoritması (sentetik geçerli sayı kabul; kontrol hanesi/uzunluk/0 ile başlama/harf reddedilir)',
	$FV::is_valid_national_id( '10000000146' ) && ! $FV::is_valid_national_id( '10000000147' ) && ! $FV::is_valid_national_id( '1000000014' ) && ! $FV::is_valid_national_id( '00000000146' ) && ! $FV::is_valid_national_id( '1000000014a' ) && ! $FV::is_valid_national_id( 12345678901 ) );
$fv_len = $FV::validate( 'contact', array_merge( $fv_contact, array( 'message' => str_repeat( 'a', 2001 ) ) ) );
mb_test( 'Faz 8 doğrulama: uzunluk sınırı (message 2000) aşılırsa hata', false === $fv_len['ok'] && isset( $fv_len['errors']['message'] ) && true === $FV::validate( 'contact', array_merge( $fv_contact, array( 'message' => str_repeat( 'a', 2000 ) ) ) )['ok'] );
$fv_html = $FV::validate( 'contact', array_merge( $fv_contact, array( 'full_name' => '<script>alert(1)</script>Ad', 'message' => "satır1\r\nsatır2\x00" ) ) );
mb_test( 'Faz 8 doğrulama: HTML etiketleri temizlenir; textarea CRLF -> LF ve NUL/kontrol karakteri silinir; tek satırlı alanda satır sonu boşluğa dönüşür',
	'alert(1)Ad' === $fv_html['clean']['full_name'] && "satır1\nsatır2" === $fv_html['clean']['message']
	&& 'a b' === $FV::normalize( array( 'type' => 'text' ), "a\r\nb" ) );
$fv_sel = $FV::validate( 'exam_request', array( 'request_type' => 'corporate', 'full_name' => 'Test Kurum', 'phone' => '0212 000 00 00', 'email' => 'kurum@ornek.example', 'consent' => 'on', 'candidate_count' => '25x' ) );
mb_test( 'Faz 8 doğrulama: sayısal desen (candidate_count yalnız rakam); radio değeri listede olmalı',
	isset( $fv_sel['errors']['candidate_count'] ) && ! isset( $fv_sel['errors']['request_type'] ) && isset( $FV::validate( 'exam_request', array( 'request_type' => 'hacker' ) )['errors']['request_type'] ) );
$fv_app = array( 'qualification' => '97UY0001-3', 'exam_location' => 'iskenderun', 'full_name' => 'Test Aday', 'national_id' => '10000000146', 'phone' => '0212 000 00 00', 'email' => 'aday@ornek.example', 'consent' => '1' );
$fv_app_ok = $FV::validate( 'application', $fv_app, array( 'options' => array( 'qualifications' => array( '97UY0001-3' => 'Test' ) ) ) );
mb_test( 'Faz 8 doğrulama: online başvuru — dinamik seçenek (yeterlilik kodu) ve sabit seçenek (sınav alanı) doğrulanır; T.C. kimlik geçerli',
	true === $fv_app_ok['ok'] && ! isset( $FV::validate( 'application', $fv_app, array( 'options' => array( 'qualifications' => array( '97UY0001-3' => 'Test' ) ) ) )['errors']['national_id'] )
	&& isset( $FV::validate( 'application', $fv_app, array( 'options' => array( 'qualifications' => array( 'BASKA' => 'x' ) ) ) )['errors']['qualification'] ) && isset( $FV::validate( 'application', array_merge( $fv_app, array( 'exam_location' => 'yok' ) ), array( 'options' => array( 'qualifications' => array( '97UY0001-3' => 'Test' ) ) ) )['errors']['exam_location'] ) );
mb_test( 'Faz 8 doğrulama: hassas alan (national_id) ve bilinmeyen alanlar ASLA echo\'ya girmez (yeniden görüntülemede geri doldurulmaz)',
	! array_key_exists( 'national_id', $fv_app_ok['echo'] ) && array_key_exists( 'full_name', $fv_app_ok['echo'] ) && ! array_key_exists( 'consent', $fv_app_ok['echo'] ) );
mb_test( 'Faz 8 doğrulama: bilinmeyen form -> hata, clean boş', false === $FV::validate( 'bogus', array( 'x' => '1' ) )['ok'] && array() === $FV::validate( 'bogus', array( 'x' => '1' ) )['clean'] );

/* ---------------- yapılandırma ve kapı ---------------- */
$fc_def = $FC::defaults();
mb_test( 'Faz 8 kapı: varsayılan yapılandırmada BÜTÜN formlar kapalı (hassas ve standart); kapalı nedenleri açıkça döner',
	(function () use ( $FC, $FS, $fc_def ) {
		foreach ( $FS::FORM_IDS as $id ) {
			$g = $FC::gate( $id, $fc_def );
			if ( $g['open'] || ! in_array( 'not_enabled', $g['reasons'], true ) || ! in_array( 'consent_not_approved', $g['reasons'], true ) || ! in_array( 'recipient_not_configured', $g['reasons'], true ) ) {
				return false;
			}
		}
		return true;
	})() );
mb_test( 'Faz 8 kapı: hassas formlar ek olarak hassas alan onayı; dosya alanlı formlar dosya yükleme onayı ister; standart formlar istemez',
	in_array( 'sensitive_fields_not_approved', $FC::gate( 'application', $fc_def )['reasons'], true ) && in_array( 'uploads_not_approved', $FC::gate( 'application', $fc_def )['reasons'], true )
	&& in_array( 'sensitive_fields_not_approved', $FC::gate( 'job_application', $fc_def )['reasons'], true ) && ! in_array( 'sensitive_fields_not_approved', $FC::gate( 'contact', $fc_def )['reasons'], true ) && ! in_array( 'uploads_not_approved', $FC::gate( 'contact', $fc_def )['reasons'], true ) );
$fc_full = array( 'enabled' => '1', 'recipient_email' => 'kurum@ornek.example', 'consent_approved' => '1', 'consent_text' => 'Sentetik test onay metni.', 'consent_version' => 'v-test-1', 'sensitive_fields_approved' => '1', 'uploads_approved' => '1' );
$fc_cfg  = $FC::sanitize( array( 'forms' => array( 'contact' => $fc_full, 'application' => $fc_full ) ) );
mb_test( 'Faz 8 kapı: tüm koşullar (açık + onaylı rıza metni/sürümü + alıcı + hassas + dosya onayı) sağlanınca AÇILIR; başka form hâlâ kapalı',
	true === $FC::gate( 'contact', $fc_cfg )['open'] && true === $FC::gate( 'application', $fc_cfg )['open'] && false === $FC::gate( 'complaint', $fc_cfg )['open'] && is_array( $FC::open_settings( 'contact', $fc_cfg ) ) && null === $FC::open_settings( 'complaint', $fc_cfg ) );
$fc_no_consent = $FC::sanitize( array( 'forms' => array( 'contact' => array_merge( $fc_full, array( 'consent_approved' => '' ) ) ) ) );
$fc_no_text    = $FC::sanitize( array( 'forms' => array( 'contact' => array_merge( $fc_full, array( 'consent_text' => '   ' ) ) ) ) );
$fc_no_ver     = $FC::sanitize( array( 'forms' => array( 'contact' => array_merge( $fc_full, array( 'consent_version' => '' ) ) ) ) );
mb_test( 'Faz 8 kapı: KVKK onayı işaretsiz VEYA metin boş VEYA sürüm boş -> form AÇILMAZ (submit düğmesi etkinleşmez)',
	false === $FC::gate( 'contact', $fc_no_consent )['open'] && false === $FC::gate( 'contact', $fc_no_text )['open'] && false === $FC::gate( 'contact', $fc_no_ver )['open'] && in_array( 'consent_not_approved', $FC::gate( 'contact', $fc_no_text )['reasons'], true ) );
$fc_bad_rcpt = $FC::sanitize( array( 'forms' => array( 'contact' => array_merge( $fc_full, array( 'recipient_email' => "kotu@ornek.example\r\nBcc: x@ornek.example" ) ) ) ) );
mb_test( 'Faz 8 kapı: geçersiz/başlık-enjeksiyonlu alıcı adresi boşaltılır ve form AÇILMAZ (recipient_not_configured)',
	'' === $fc_bad_rcpt['forms']['contact']['recipient_email'] && false === $FC::gate( 'contact', $fc_bad_rcpt )['open'] && in_array( 'recipient_not_configured', $FC::gate( 'contact', $fc_bad_rcpt )['reasons'], true ) );
$fc_no_sens = $FC::sanitize( array( 'forms' => array( 'application' => array_merge( $fc_full, array( 'sensitive_fields_approved' => '' ) ) ) ) );
$fc_no_up   = $FC::sanitize( array( 'forms' => array( 'job_application' => array_merge( $fc_full, array( 'uploads_approved' => '' ) ) ) ) );
mb_test( 'Faz 8 kapı: hassas alan onayı olmadan online başvuru, dosya onayı olmadan iş başvurusu AÇILMAZ (CV/T.C. kimlik kurum onayı olmadan etkinleşmez)',
	false === $FC::gate( 'application', $fc_no_sens )['open'] && in_array( 'sensitive_fields_not_approved', $FC::gate( 'application', $fc_no_sens )['reasons'], true ) && false === $FC::gate( 'job_application', $fc_no_up )['open'] && in_array( 'uploads_not_approved', $FC::gate( 'job_application', $fc_no_up )['reasons'], true ) );
mb_test( 'Faz 8 yapılandırma: sanitize bozuk/dizi-olmayan/bilinmeyen form/anahtar/tipleri KAPALI varsayılana indirger; bool alanlar yalnız true/"1"/1/"on"',
	$FC::defaults() === $FC::sanitize( 'bozuk' ) && $FC::defaults() === $FC::sanitize( null ) && false === $FC::sanitize( array( 'forms' => array( 'contact' => array( 'enabled' => 'false' ) ) ) )['forms']['contact']['enabled']
	&& true === $FC::sanitize( array( 'forms' => array( 'contact' => array( 'enabled' => 'on' ) ) ) )['forms']['contact']['enabled'] && ! isset( $FC::sanitize( array( 'forms' => array( 'gizli' => array( 'enabled' => '1' ) ) ) )['forms']['gizli'] )
	&& false === $FC::sanitize( array( 'forms' => array( 'contact' => array( 'enabled' => array( '1' ) ) ) ) )['forms']['contact']['enabled'] );
mb_test( 'Faz 8 yapılandırma: onay metni HTML\'den arındırılır ve 1500 karakterle sınırlanır; sürüm tek satır ve 40 karakter',
	'yalnız metin' === $FC::sanitize( array( 'forms' => array( 'contact' => array( 'consent_text' => '<b>yalnız</b> metin' ) ) ) )['forms']['contact']['consent_text']
	&& 1500 === strlen( $FC::sanitize( array( 'forms' => array( 'contact' => array( 'consent_text' => str_repeat( 'a', 3000 ) ) ) ) )['forms']['contact']['consent_text'] )
	&& 'v 1' === $FC::sanitize( array( 'forms' => array( 'contact' => array( 'consent_version' => "v\n1" ) ) ) )['forms']['contact']['consent_version'] );
mb_test( 'Faz 8 yapılandırma: oran sınırı değerleri yalnız pozitif tamsayı; bozuk değer varsayılana düşer; her neden kodunun açıklaması var',
	8 === $FC::sanitize( array( 'rate_limit' => array( 'per_client' => '0' ) ) )['rate_limit']['per_client'] && 3 === $FC::sanitize( array( 'rate_limit' => array( 'per_client' => '3' ) ) )['rate_limit']['per_client'] && 8 === $FC::sanitize( array( 'rate_limit' => array( 'per_client' => array( 3 ) ) ) )['rate_limit']['per_client']
	&& 6 === count( $FC::REASON_LABELS ) && 'unknown_form' === $FC::gate( 'bogus', $fc_def )['reasons'][0] );

/* ---------------- güvenlik ---------------- */
$fx_secret = 'sentetik-gizli-anahtar';
$fx_token  = $FX::issue_token( 'contact', $fx_secret, 1000, 'abcdef0123456789' );
mb_test( 'Faz 8 jeton: geçerli jeton ilk 3 saniyede reddedilir (çok hızlı), sonra kabul edilir, 2 saatten sonra süresi dolar',
	'token_too_fast' === $FX::verify_token( 'contact', $fx_token, $fx_secret, 1002 )['reason'] && true === $FX::verify_token( 'contact', $fx_token, $fx_secret, 1003 )['ok'] && true === $FX::verify_token( 'contact', $fx_token, $fx_secret, 1000 + 7200 )['ok'] && 'token_expired' === $FX::verify_token( 'contact', $fx_token, $fx_secret, 1000 + 7201 )['reason'] );
mb_test( 'Faz 8 jeton: farklı form/farklı sır/kurcalanmış zaman/bozuk biçim reddedilir; tüketim kimliği kararlı ve form\'a bağlı',
	'token_bad_signature' === $FX::verify_token( 'complaint', $fx_token, $fx_secret, 1100 )['reason'] && 'token_bad_signature' === $FX::verify_token( 'contact', $fx_token, 'baska', 1100 )['reason']
	&& 'token_bad_signature' === $FX::verify_token( 'contact', preg_replace( '/^1000/', '900', $fx_token ), $fx_secret, 1100 )['reason'] && 'token_malformed' === $FX::verify_token( 'contact', 'x.y.z', $fx_secret, 1100 )['reason'] && 'token_malformed' === $FX::verify_token( 'contact', array(), $fx_secret, 1100 )['reason']
	&& 'token_malformed' === $FX::verify_token( 'contact', '', $fx_secret, 1100 )['reason'] && $FX::verify_token( 'contact', $fx_token, $fx_secret, 1100 )['id'] === $FX::verify_token( 'contact', $fx_token, $fx_secret, 1500 )['id'] && 64 === strlen( $FX::verify_token( 'contact', $fx_token, $fx_secret, 1100 )['id'] ) );
mb_test( 'Faz 8 bal küpü: boş -> tetiklenmez; dolu, dizi veya nesne -> bot; alan hiç yoksa tetiklenmez',
	false === $FX::honeypot_triggered( array() ) && false === $FX::honeypot_triggered( array( $FS::HONEYPOT_FIELD => '' ) ) && false === $FX::honeypot_triggered( array( $FS::HONEYPOT_FIELD => '   ' ) ) && true === $FX::honeypot_triggered( array( $FS::HONEYPOT_FIELD => 'http://spam.example' ) ) && true === $FX::honeypot_triggered( array( $FS::HONEYPOT_FIELD => array( '' ) ) ) );
$fx_lim = array( 'per_client' => 3, 'window' => 600, 'global' => 10, 'global_window' => 3600 );
mb_test( 'Faz 8 oran sınırı: FAIL-CLOSED — sınır altında ok; istemci/genel sınırda reddet; sayaç okunamazsa (null/negatif/tip hatası) unavailable',
	'ok' === $FX::evaluate_rate( 2, 9, $fx_lim ) && 'client_limited' === $FX::evaluate_rate( 3, 0, $fx_lim ) && 'global_limited' === $FX::evaluate_rate( 0, 10, $fx_lim ) && 'unavailable' === $FX::evaluate_rate( null, 0, $fx_lim ) && 'unavailable' === $FX::evaluate_rate( 0, null, $fx_lim )
	&& 'unavailable' === $FX::evaluate_rate( -1, 0, $fx_lim ) && 'unavailable' === $FX::evaluate_rate( '1', 0, $fx_lim ) && 'unavailable' === $FX::evaluate_rate( false, 0, $fx_lim ) );
mb_test( 'Faz 8 dosya adı: son uzantı çıkarılır; çift uzantı (a.php.pdf), tehlikeli uzantı, NUL, yol geçişi, uzantısız, aşırı uzun ad reddedilir',
	'pdf' === $FX::safe_extension( 'Özgeçmiş.PDF' ) && 'docx' === $FX::safe_extension( 'cv.final.docx' ) && null === $FX::safe_extension( 'a.php.pdf' ) && null === $FX::safe_extension( 'a.pdf.php' ) && null === $FX::safe_extension( 'shell.phtml' ) && null === $FX::safe_extension( 'a.svg' )
	&& null === $FX::safe_extension( "a.pdf\0.php" ) && null === $FX::safe_extension( 'noext' ) && null === $FX::safe_extension( '' ) && null === $FX::safe_extension( str_repeat( 'a', 300 ) . '.pdf' ) && 'pdf' === $FX::safe_extension( '../../x.pdf' ) && null === $FX::safe_extension( '.htaccess' ) && null === $FX::safe_extension( array( 'a.pdf' ) ) );
$fx_file = array( 'name' => 'cv.pdf', 'type' => 'application/x-msdownload', 'tmp_name' => '/tmp/phpXXXX', 'error' => 0, 'size' => 1000 );
mb_test( 'Faz 8 dosya doğrulama: sunucu MIME\'ı esastır (istemci type yok sayılır); MIME/uzantı uyuşmazlığı, boyut, hata kodu, yüklenmemiş dosya reddedilir',
	true === $FX::validate_upload( $fx_file, array( 'pdf' ), 'application/pdf', true )['ok'] && 'upload_mime' === $FX::validate_upload( $fx_file, array( 'pdf' ), 'text/html', true )['error'] && 'upload_mime' === $FX::validate_upload( $fx_file, array( 'pdf' ), null, true )['error']
	&& 'upload_extension' === $FX::validate_upload( array_merge( $fx_file, array( 'name' => 'cv.exe' ) ), array( 'pdf', 'exe' ), 'application/pdf', true )['error'] && 'upload_extension' === $FX::validate_upload( $fx_file, array( 'doc' ), 'application/pdf', true )['error']
	&& 'upload_size' === $FX::validate_upload( array_merge( $fx_file, array( 'size' => 5242881 ) ), array( 'pdf' ), 'application/pdf', true )['error'] && true === $FX::validate_upload( array_merge( $fx_file, array( 'size' => 5242880 ) ), array( 'pdf' ), 'application/pdf', true )['ok'] && 'upload_size' === $FX::validate_upload( array_merge( $fx_file, array( 'size' => 0 ) ), array( 'pdf' ), 'application/pdf', true )['error']
	&& 'upload_error' === $FX::validate_upload( array_merge( $fx_file, array( 'error' => 1 ) ), array( 'pdf' ), 'application/pdf', true )['error'] && 'upload_missing' === $FX::validate_upload( array_merge( $fx_file, array( 'error' => 4 ) ), array( 'pdf' ), 'application/pdf', true )['error']
	&& 'upload_not_uploaded_file' === $FX::validate_upload( $fx_file, array( 'pdf' ), 'application/pdf', false )['error'] && 'upload_malformed' === $FX::validate_upload( array( 'name' => 'a.pdf' ), array( 'pdf' ), 'application/pdf', true )['error'] );
mb_test( 'Faz 8 dosya: rastgele ad yalnız 32-hex + güvenli uzantıdan üretilir; kullanıcı adı asla kullanılmaz',
	'0123456789abcdef0123456789abcdef.pdf' === $FX::random_filename( 'pdf', '0123456789abcdef0123456789abcdef' ) && null === $FX::random_filename( 'pdf', 'kisa' ) && null === $FX::random_filename( 'p/hp', '0123456789abcdef0123456789abcdef' ) && null === $FX::random_filename( 'pdf', "../../etc/passwd" ) );

/* ---------------- ileti kurucusu ---------------- */
$fm_settings = array( 'recipient_email' => 'kurum@ornek.example', 'consent_version' => 'v-test-1' );
$fm_msg      = $FM::build( 'contact', $fv_ok['clean'], $fm_settings );
mb_test( 'Faz 8 e-posta: konu SABİT (kullanıcı girdisi yok), alıcı yapılandırmadan, Reply-To doğrulanmış e-postadan; gövde etiket: değer; KVKK sürümü yazılır',
	is_array( $fm_msg ) && 'kurum@ornek.example' === $fm_msg['to'] && '[Mavi Belge] İletişim formu' === $fm_msg['subject'] && in_array( 'Reply-To: test@ornek.example', $fm_msg['headers'], true ) && false !== strpos( $fm_msg['body'], 'Ad Soyad: Test Kullanıcı' ) && false !== strpos( $fm_msg['body'], 'KVKK onayı: verildi (sürüm v-test-1)' ) );
$fm_inj = $FM::build( 'contact', array_merge( $fv_ok['clean'], array( 'email' => "x@ornek.example\r\nBcc: y@ornek.example" ) ), $fm_settings );
mb_test( 'Faz 8 e-posta: kurucuya doğrulanmamış CRLF\'li e-posta verilse bile Reply-To BAŞLIĞI üretilmez (başlık enjeksiyonu yok); hiçbir başlıkta CR/LF yok',
	is_array( $fm_inj ) && 1 === count( $fm_inj['headers'] ) && 0 === preg_match( '/[\r\n]/', implode( '', $fm_inj['headers'] ) . $fm_inj['subject'] . $fm_inj['to'] ) );
mb_test( 'Faz 8 e-posta: geçersiz/başlık-enjeksiyonlu alıcı, bilinmeyen form veya CRLF\'li ek yolu -> null (gönderilmez)',
	null === $FM::build( 'contact', $fv_ok['clean'], array( 'recipient_email' => "a@ornek.example\r\nBcc: b@ornek.example" ) ) && null === $FM::build( 'contact', $fv_ok['clean'], array( 'recipient_email' => '' ) ) && null === $FM::build( 'bogus', array(), $fm_settings )
	&& null === $FM::build( 'contact', $fv_ok['clean'], $fm_settings, array( "/tmp/a\r\n.pdf" ) ) && array( '/tmp/x.pdf' ) === $FM::build( 'contact', $fv_ok['clean'], $fm_settings, array( '/tmp/x.pdf' ) )['attachments'] );
mb_test( 'Faz 8 e-posta: seçenek alanları (radio/select) kod yerine görünen etiketle yazılır',
	false !== strpos( $FM::build( 'complaint', array( 'notice_type' => 'objection', 'full_name' => 'Test', 'email' => 'a@ornek.example', 'subject' => 'S', 'description' => 'D', 'consent' => true ), $fm_settings )['body'], 'Bildirim türü: İtiraz' ) );
mb_test( 'Faz 8 e-posta: adaptör arayüzü tanımlı ve üretim uygulaması onu uygular (gerçek e-posta testlerde GÖNDERİLMEZ)',
	interface_exists( 'MaviBelge_Core_Forms_Mailer' ) && class_exists( 'MaviBelge_Core_Forms_Wp_Mailer' ) && in_array( 'MaviBelge_Core_Forms_Mailer', class_implements( 'MaviBelge_Core_Forms_Wp_Mailer' ), true ) );
