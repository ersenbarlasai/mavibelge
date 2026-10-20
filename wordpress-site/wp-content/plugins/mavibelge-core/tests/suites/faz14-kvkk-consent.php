<?php
/**
 * Faz 14 — KVKK onay kutusu: kısa bağlantılı etiket (SAF; WordPress gerektirmez).
 *
 * Ziyaretçiye tam `consent_text` BASILMAZ; formda "KVKK Metni’ni okudum ve kabul ediyorum." görünür ve "KVKK Metni"
 * /kvkk/ sayfasına bağlanır (tema). `consent_text` + `consent_version` kurumca onaylı metnin kayıt/kapı kanıtı olarak
 * KORUNUR: kapı, sanitize ve e-postadaki sürüm kaydı değişmez. Veri SENTETİKTİR.
 */

$KS = 'MaviBelge_Core_Forms_Schema';
$KC = 'MaviBelge_Core_Forms_Config';
$KM = 'MaviBelge_Core_Forms_Mail_Builder';

$kv_long = 'TEST uzun kurum metni: ' . str_repeat( 'Kişisel verileriniz 6698 sayılı Kanun kapsamında işlenir. ', 20 );
$kv_long = rtrim( mb_substr( $kv_long, 0, 1400, 'UTF-8' ) );
$kv_full = array( 'enabled' => '1', 'recipient_email' => 'kurum@ornek.example', 'consent_approved' => '1', 'consent_text' => $kv_long, 'consent_version' => 'test-kvkk-2026', 'sensitive_fields_approved' => '1', 'uploads_approved' => '1' );
$kv_cfg  = array();
foreach ( $KS::FORM_IDS as $kv_id ) {
	$kv_cfg[ $kv_id ] = $kv_full;
}
$kv_clean = $KC::sanitize( array( 'forms' => $kv_cfg ) );

mb_test( 'Faz 14 KVKK: beş form (contact, application, exam_request, complaint, job_application) tanımlı ve her birinde TEK consent alanı ZORUNLU',
	array( 'contact', 'application', 'exam_request', 'complaint', 'job_application' ) === $KS::FORM_IDS
	&& 5 === count( array_filter( $KS::FORM_IDS, function ( $id ) use ( $KS ) {
		$c = array_values( array_filter( $KS::get( $id )['fields'], function ( $f ) {
			return 'consent' === $f['type'];
		} ) );
		return 1 === count( $c ) && 'consent' === $c[0]['name'] && true === $c[0]['required'];
	} ) ) );

mb_test( 'Faz 14 KVKK: şema etiketi kısa ve anlaşılır ("KVKK Metni"; hata özetinde bağlantı metni)',
	5 === count( array_filter( $KS::FORM_IDS, function ( $id ) use ( $KS ) {
		foreach ( $KS::get( $id )['fields'] as $f ) {
			if ( 'consent' === $f['type'] ) {
				return 'KVKK Metni' === $f['label'];
			}
		}
		return false;
	} ) ) );

mb_test( 'Faz 14 KVKK: uzun (1400 karakter) consent_text sanitize edilir, AYNEN saklanır ve kapıyı açmaya devam eder (beş form)',
	$kv_long === $kv_clean['forms']['contact']['consent_text']
	&& 5 === count( array_filter( $KS::FORM_IDS, function ( $id ) use ( $KC, $kv_clean ) {
		return true === $KC::gate( $id, $kv_clean )['open'];
	} ) ) );

mb_test( 'Faz 14 KVKK: consent_version sanitize sonrası korunur',
	'test-kvkk-2026' === $kv_clean['forms']['application']['consent_version'] && 'test-kvkk-2026' === $kv_clean['forms']['job_application']['consent_version'] );

$kv_dtos_ok = method_exists( $KS, 'present_fields' );
$kv_leak    = true;
$kv_labels  = array();
if ( $kv_dtos_ok ) {
	$kv_leak = false;
	foreach ( $KS::FORM_IDS as $kv_id ) {
		$kv_dto = $KS::present_fields( $KS::get( $kv_id ), array() );
		if ( false !== strpos( serialize( $kv_dto ), 'TEST uzun kurum metni' ) ) {
			$kv_leak = true;
		}
		foreach ( $kv_dto as $f ) {
			if ( 'consent' === $f['type'] ) {
				$kv_labels[ $kv_id ] = $f['label'];
			}
		}
	}
}
$kv_params = $kv_dtos_ok ? array_map( function ( $p ) {
	return $p->getName();
}, ( new ReflectionMethod( $KS, 'present_fields' ) )->getParameters() ) : array();
mb_test( 'Faz 14 KVKK: alan DTO kurucusu (present_fields) saf ve yapılandırma ALMAZ (imza: def, options); consent etiketi beş formda şema etiketi "KVKK Metni"',
	$kv_dtos_ok && array( 'def', 'options' ) === $kv_params && ! $kv_leak && 5 === count( $kv_labels ) && array( 'KVKK Metni' ) === array_values( array_unique( $kv_labels ) ) );

// describe() gövdesi: alanlar present_fields ile kurulur; yapılandırmadaki consent_text okunmaz/ikame edilmez (yorumlar hariç).
$kv_service  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/class-forms-service.php' );
$kv_describe = preg_match( '/public static function describe\( \$formId \) \{(.*?)\n\t\}\n/s', $kv_service, $kv_m ) ? preg_replace( '#^\s*//.*$#m', '', $kv_m[1] ) : '';
mb_test( 'Faz 14 KVKK: describe() consent_text\'i etiket olarak İKAME ETMEZ (gövdede consent_text / $settings okunmaz; alanlar present_fields ile)',
	'' !== $kv_describe && false === strpos( $kv_describe, 'consent_text' ) && false === strpos( $kv_describe, '$settings' ) && false !== strpos( $kv_describe, 'MaviBelge_Core_Forms_Schema::present_fields( $def, $options )' ) );

$kv_val = MaviBelge_Core_Forms_Validator::validate( 'contact', array( 'full_name' => 'TEST Sentetik', 'email' => 'aday@ornek.example', 'message' => 'TEST mesaj', 'consent' => '1' ) );
$kv_msg = $KM::build( 'contact', $kv_val['clean'], array( 'recipient_email' => 'kurum@ornek.example', 'consent_text' => $kv_long, 'consent_version' => 'test-kvkk-2026' ) );
mb_test( 'Faz 14 KVKK: e-posta yalnız onay durumu + sürümü yazar ("KVKK onayı: verildi (sürüm …)"); consent_text ve etiket gövdeye GİRMEZ',
	true === $kv_val['ok'] && is_array( $kv_msg ) && 1 === substr_count( $kv_msg['body'], 'KVKK onayı: verildi (sürüm test-kvkk-2026)' )
	&& false === strpos( $kv_msg['body'], 'TEST uzun kurum metni' ) && false === strpos( $kv_msg['body'], 'KVKK Metni' ) );

mb_test( 'Faz 14 KVKK: consent onayı olmadan gönderim reddedilir (beş form; sunucu doğrulaması değişmedi)',
	5 === count( array_filter( $KS::FORM_IDS, function ( $id ) {
		$r = MaviBelge_Core_Forms_Validator::validate( $id, array( 'consent' => '' ) );
		return false === $r['ok'] && 'Devam etmek için onaylamanız gerekir.' === $r['errors']['consent'];
	} ) ) );

$kv_no_text = array();
$kv_no_ver  = array();
$kv_no_appr = array();
foreach ( $KS::FORM_IDS as $kv_id ) {
	$kv_no_text[ $kv_id ] = array_merge( $kv_full, array( 'consent_text' => " \n " ) );
	$kv_no_ver[ $kv_id ]  = array_merge( $kv_full, array( 'consent_version' => '' ) );
	$kv_no_appr[ $kv_id ] = array_merge( $kv_full, array( 'consent_approved' => '' ) );
}
$kv_closed = function ( $forms, $reason ) use ( $KS, $KC ) {
	$c = $KC::sanitize( array( 'forms' => $forms ) );
	return 5 === count( array_filter( $KS::FORM_IDS, function ( $id ) use ( $KC, $c, $reason ) {
		$g = $KC::gate( $id, $c );
		return false === $g['open'] && in_array( $reason, $g['reasons'], true );
	} ) );
};
mb_test( 'Faz 14 KVKK: consent_text boş/boşluk -> beş form KAPALI (consent_not_approved)', $kv_closed( $kv_no_text, 'consent_not_approved' ) );
mb_test( 'Faz 14 KVKK: consent_version boş -> beş form KAPALI (consent_not_approved)', $kv_closed( $kv_no_ver, 'consent_not_approved' ) );
mb_test( 'Faz 14 KVKK: consent_approved işaretsiz -> beş form KAPALI (consent_not_approved)', $kv_closed( $kv_no_appr, 'consent_not_approved' ) );

$kv_bad = array(
	$KC::sanitize( null ),
	$KC::sanitize( 'bozuk' ),
	$KC::sanitize( array( 'forms' => 'bozuk' ) ),
	$KC::sanitize( array( 'forms' => array( 'contact' => array( 'consent_approved' => array( '1' ), 'consent_text' => array( 'x' ), 'consent_version' => new stdClass(), 'enabled' => '1', 'recipient_email' => 'kurum@ornek.example' ) ) ) ),
);
mb_test( 'Faz 14 KVKK: bozuk/bilinmeyen yapılandırma FAIL-CLOSED (beş form kapalı; bilinmeyen form kimliği kapalı)',
	4 === count( array_filter( $kv_bad, function ( $c ) use ( $KS, $KC ) {
		return 0 === count( array_filter( $KS::FORM_IDS, function ( $id ) use ( $KC, $c ) {
			return true === $KC::gate( $id, $c )['open'];
		} ) );
	} ) ) && false === $KC::gate( 'bogus', $kv_clean )['open'] && null === $KS::get( 'bogus' ) );

$kv_admin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-forms-admin.php' );
mb_test( 'Faz 14 KVKK yönetim: yanıltıcı "formda onay kutusunun yanında görünür" açıklaması kaldırıldı; onaylı metin kaydı açıklaması var',
	false === strpos( $kv_admin, 'onay kutusunun yanında görünür' )
	&& false !== strpos( $kv_admin, 'Onaylı metin kaydı. Formda metnin tamamı gösterilmez; ziyaretçi /kvkk/ sayfasındaki KVKK Metni’ne yönlendirilir.' ) );
mb_test( 'Faz 14 KVKK yönetim: KVKK sayfası WordPress API ile bulunur (get_page_by_path + get_permalink + get_edit_post_link); URL koda gömülmez; bulunamazsa uyarı',
	false !== strpos( $kv_admin, 'get_page_by_path(' ) && false !== strpos( $kv_admin, 'get_permalink(' ) && false !== strpos( $kv_admin, 'get_edit_post_link(' )
	&& false !== strpos( $kv_admin, 'KVKK sayfası bulunamadı' ) && false === strpos( $kv_admin, 'mavibelge.com.tr/kvkk' ) && false === strpos( $kv_admin, "home_url( '/kvkk" ) );
