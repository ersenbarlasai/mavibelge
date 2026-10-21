<?php
/**
 * Faz 16 — online başvuru meslek seçenekleri ücret kayıtlarından (SAF sınıf + e-posta kurucusu + kaynak sözleşmesi).
 * Veri SENTETİKTİR (gerçek kişi verisi yok). Ücret satırları gerçek kataloğun biçimini taklit eder.
 */

$FO = 'MaviBelge_Core_Forms_Fee_Options';

$f16_rows = array(
	array( 'id' => 11, 'profession_name' => 'Makine Bakımcı', 'level' => '3', 'qualification_code' => '10UY0002-3/03', 'qualification_id' => 501, 'linked_title' => 'Makine Bakımcı', 'linked_myk_code' => '10UY0002-3/03' ),
	array( 'id' => 12, 'profession_name' => 'Dokuma Operatörü', 'level' => '4', 'qualification_code' => '', 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
	array( 'id' => 13, 'profession_name' => 'Dokuma Operatörü', 'level' => '3', 'qualification_code' => '', 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
	array( 'id' => 14, 'profession_name' => 'Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci)', 'level' => '3', 'qualification_code' => '12UY0061-3/04', 'qualification_id' => 502, 'linked_title' => 'Mobil Vinç Operatörü', 'linked_myk_code' => '12UY0061-3/04' ),
	array( 'id' => 15, 'profession_name' => 'Mobil Vinç Operatörü (İş Makineleri Grubu)', 'level' => '3', 'qualification_code' => '12UY0061-3/04', 'qualification_id' => 502, 'linked_title' => 'Mobil Vinç Operatörü', 'linked_myk_code' => '12UY0061-3/04' ),
	array( 'id' => 16, 'profession_name' => 'Kazıcı Yükleyici (Beko Loder) Operatörü', 'level' => '3', 'qualification_code' => '', 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
	array( 'id' => 17, 'profession_name' => 'Çelik Kaynakçısı', 'level' => '3', 'qualification_code' => '11UY0012-3/02', 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
	array( 'id' => 18, 'profession_name' => 'İnşaat Boyacısı', 'level' => '3', 'qualification_code' => '', 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
	array( 'id' => 19, 'profession_name' => 'Isı Yalıtımcısı', 'level' => '3', 'qualification_code' => '12UY0057-3/01', 'qualification_id' => 503, 'linked_title' => 'Isı Yalıtımcısı', 'linked_myk_code' => '12UY0057-3/01' ),
	array( 'id' => 20, 'profession_name' => 'Şantiye Elemanı', 'level' => '', 'qualification_code' => '', 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
	array( 'id' => 21, 'profession_name' => 'Gizli Satır', 'level' => '9', 'qualification_code' => "KOD\r\nBcc: x@ornek.example", 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
	array( 'id' => 0, 'profession_name' => 'Kimliksiz', 'level' => '3', 'qualification_code' => '', 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
	array( 'id' => 22, 'profession_name' => '', 'level' => '3', 'qualification_code' => '', 'qualification_id' => 0, 'linked_title' => '', 'linked_myk_code' => '' ),
);
$f16 = $FO::build( $f16_rows );

mb_test( 'Faz 16 etiket: "{Meslek adı} — Seviye {N} — {MYK kodu}"; kodsuz kayıtta kod UYDURULMAZ',
	'Makine Bakımcı — Seviye 3 — 10UY0002-3/03' === $f16[11] && 'Dokuma Operatörü — Seviye 3' === $f16[13] && 'Dokuma Operatörü — Seviye 4' === $f16[12]
	&& 'Kazıcı Yükleyici (Beko Loder) Operatörü — Seviye 3' === $f16[16] && false === strpos( $f16[16], 'UY' ) );
mb_test( 'Faz 16 seviye 1-8 değilse "Seviye" eklenmez; kontrol karakterleri etikete giremez',
	'Şantiye Elemanı' === $f16[20] && false === strpos( $f16[21], "\n" ) && false === strpos( $f16[21], "\r" ) && false === strpos( $f16[21], 'Seviye' ) );
mb_test( 'Faz 16 ücret kaydı başına TEK seçenek; kimliksiz/adsız satır seçenek olmaz; anahtar ücret kimliği',
	11 === count( $f16 ) && ! isset( $f16[0] ) && ! isset( $f16[22] ) && array_keys( $f16 ) === array_values( array_filter( array_keys( $f16 ), 'is_int' ) ) );
mb_test( 'Faz 16 iki Mobil Vinç tarifesi aynı kodla AYRI kimlik ve adla kalır (birleştirilmez)',
	'Mobil Vinç Operatörü (MHC, Sahil ve Gemi Vinci) — Seviye 3 — 12UY0061-3/04' === $f16[14] && 'Mobil Vinç Operatörü (İş Makineleri Grubu) — Seviye 3 — 12UY0061-3/04' === $f16[15] );
$f16_order = array_keys( $f16 );
mb_test( 'Faz 16 Türkçe sıralama: Ç C\'den sonra, Dokuma 3 < Dokuma 4, I (ı) İ\'den önce, Ş S\'den sonra; aynı ad seviye/koda göre',
	array( 17, 13, 12, 21, 19, 18, 16, 11, 15, 14, 20 ) === $f16_order, json_encode( $f16_order ) );
mb_test( 'Faz 16 Türkçe sıralama anahtarı: büyük/küçük harf duyarsız (I=ı, İ=i), ğ g\'den sonra, ö o\'dan sonra, rakam harften önce',
	$FO::sort_key( 'IŞIK' ) === $FO::sort_key( 'ışık' ) && $FO::sort_key( 'İzmir' ) === $FO::sort_key( 'izmir' ) && strcmp( $FO::sort_key( 'ağ' ), $FO::sort_key( 'ah' ) ) < 0
	&& strcmp( $FO::sort_key( 'ag' ), $FO::sort_key( 'ağ' ) ) < 0 && strcmp( $FO::sort_key( 'oz' ), $FO::sort_key( 'öa' ) ) < 0 && strcmp( $FO::sort_key( '9a' ), $FO::sort_key( 'aa' ) ) < 0 );
$f16_dup = $FO::build( array(
	array( 'id' => 31, 'profession_name' => 'Aynı Ad', 'level' => '3', 'qualification_code' => '' ),
	array( 'id' => 30, 'profession_name' => 'Aynı Ad', 'level' => '3', 'qualification_code' => '' ),
	array( 'id' => 32, 'profession_name' => 'Aynı Ad', 'level' => '4', 'qualification_code' => '' ),
) );
mb_test( 'Faz 16 aynı etiketli farklı ücretler SESSİZCE birleşmez: ikisi de kalır ve kayıt numarasıyla ayrışır',
	3 === count( $f16_dup ) && 'Aynı Ad — Seviye 3 — Ücret kaydı #30' === $f16_dup[30] && 'Aynı Ad — Seviye 3 — Ücret kaydı #31' === $f16_dup[31] && 'Aynı Ad — Seviye 4' === $f16_dup[32]
	&& array( 30, 31, 32 ) === array_keys( $f16_dup ) );

// 103 ücret / 145 fiyat seçeneği fixture'ı: seçenek sayısı ücret kaydı sayısıdır.
$f16_many = array();
$f16_price_options = 0;
for ( $i = 1; $i <= 103; $i++ ) {
	$f16_many[] = array( 'id' => 1000 + $i, 'profession_name' => 'Meslek ' . sprintf( '%03d', $i ), 'level' => (string) ( 2 + ( $i % 4 ) ), 'qualification_code' => $i <= 84 ? sprintf( '%02dUY%04d-3', 10 + ( $i % 9 ), $i ) : '', 'price_options' => array_fill( 0, $i <= 42 ? 2 : 1, array( 'amount_kurus' => 100000 ) ) );
	$f16_price_options += $i <= 42 ? 2 : 1;
}
$f16_many_opts = $FO::build( $f16_many );
mb_test( 'Faz 16 103 ücret (145 fiyat seçeneği) -> 103 meslek seçeneği; 19 kodsuz ücret seçilebilir',
	145 === $f16_price_options && 103 === count( $f16_many_opts ) && 19 === count( array_filter( $f16_many_opts, function ( $l ) {
		return false === strpos( $l, 'UY' );
	} ) ) );
$f16_big = array();
for ( $i = 1; $i <= 650; $i++ ) {
	$f16_big[] = array( 'id' => $i, 'profession_name' => 'Uzun Liste ' . $i, 'level' => '3', 'qualification_code' => '' );
}
mb_test( 'Faz 16 seçenek kurucusu sayı sınırı uygulamaz: 650 uygun ücret -> 650 seçenek (sessiz kesme yok)', 650 === count( $FO::build( $f16_big ) ) );

/* ---- kimlik ve ön seçim ---- */
mb_test( 'Faz 16 kimlik biçimi: yalnız pozitif ondalık rakam dizisi; baştaki sıfır/işaret/boşluk/ondalık/üs/dizi/nesne/null reddedilir',
	'14' === $FO::normalize_id( '14' ) && '14' === $FO::normalize_id( 14 ) && '' === $FO::normalize_id( '014' ) && '' === $FO::normalize_id( '-14' ) && '' === $FO::normalize_id( ' 14' )
	&& '' === $FO::normalize_id( '14 ' ) && '' === $FO::normalize_id( '1.4' ) && '' === $FO::normalize_id( '1e3' ) && '' === $FO::normalize_id( array( '14' ) ) && '' === $FO::normalize_id( (object) array() )
	&& '' === $FO::normalize_id( null ) && '' === $FO::normalize_id( 0 ) && '' === $FO::normalize_id( '0' ) && '' === $FO::normalize_id( str_repeat( '9', 19 ) ) && '' === $FO::normalize_id( "14\n" ) && '' === $FO::normalize_id( true ) );
mb_test( 'Faz 16 ?ucret= ön seçim: listede olan kimlik seçilir; listede olmayan (pasif/taslak/başka tür/sahte) seçilmez',
	'15' === $FO::requested_fee( '15', $f16 ) && '' === $FO::requested_fee( '999', $f16 ) && '' === $FO::requested_fee( '<script>', $f16 ) && '' === $FO::requested_fee( '15', array() ) );
mb_test( 'Faz 16 ?meslek= tek ücrete çözülen kod -> o ücret seçilir (eski bağlantılar çalışır)',
	array( 'selected' => '11', 'ambiguous' => array() ) === $FO::preselect( null, '10UY0002-3/03', $f16, $f16_rows )
	&& array( 'selected' => '17', 'ambiguous' => array() ) === $FO::preselect( null, '11UY0012-3/02', $f16, $f16_rows ) );
mb_test( 'Faz 16 ?meslek= iki tarifeye çözülen kod (Mobil Vinç) -> HİÇBİRİ seçilmez; iki tarife belirsiz olarak döner',
	array( 'selected' => '', 'ambiguous' => array( 14, 15 ) ) === $FO::preselect( null, '12UY0061-3/04', $f16, $f16_rows ) );
mb_test( 'Faz 16 ?ucret= ?meslek=\'ten önceliklidir; belirsiz koda tekil ücretle seçim yapılabilir',
	array( 'selected' => '15', 'ambiguous' => array() ) === $FO::preselect( '15', '12UY0061-3/04', $f16, $f16_rows ) );
mb_test( 'Faz 16 ?meslek= bozuk/bilinmeyen kod veya listede olmayan ücrete çözülen kod -> seçim yok, belirsizlik yok',
	array( 'selected' => '', 'ambiguous' => array() ) === $FO::preselect( null, '11uy0011-3/03', $f16, $f16_rows )
	&& array( 'selected' => '', 'ambiguous' => array() ) === $FO::preselect( null, '99UY9999-3/00', $f16, $f16_rows )
	&& array( 'selected' => '', 'ambiguous' => array() ) === $FO::preselect( 'abc', array( '10UY0002-3/03' ), $f16, $f16_rows )
	&& array( 'selected' => '', 'ambiguous' => array() ) === $FO::preselect( null, '10UY0002-3/03', array( 12 => 'x' ), $f16_rows ) );
mb_test( 'Faz 16 ?meslek= bağlı yeterliliğin koduyla da çözülür (ücretin kendi kod alanı boş olsa bile)',
	array( 'selected' => '41', 'ambiguous' => array() ) === $FO::preselect( null, '13UY0137-3/01', array( 41 => 'Bitim — Seviye 3' ), array( array( 'id' => 41, 'qualification_code' => '', 'linked_myk_code' => '13UY0137-3/01' ) ) ) );

/* ---- e-posta ---- */
$f16_mail_row = $f16_rows[5];
$f16_details  = $FO::mail_details( $f16_mail_row );
mb_test( 'Faz 16 e-posta ayrıntısı (bağlantısız, kodsuz): ücret kaydı, meslek, seviye, "MYK kodu: Belirtilmemiş", "Bağlı yeterlilik: Bağlantısız"',
	array( 'Ücret kaydı' => '#16', 'Meslek' => 'Kazıcı Yükleyici (Beko Loder) Operatörü', 'Seviye' => '3', 'MYK kodu' => 'Belirtilmemiş', 'Bağlı yeterlilik' => 'Bağlantısız' ) === $f16_details );
mb_test( 'Faz 16 e-posta ayrıntısı (bağlı): gerçek yeterlilik adı, kodu ve kayıt numarası',
	'Mobil Vinç Operatörü (12UY0061-3/04) — kayıt #502' === $FO::mail_details( $f16_rows[4] )['Bağlı yeterlilik'] && '12UY0061-3/04' === $FO::mail_details( $f16_rows[4] )['MYK kodu'] );
$f16_settings = array( 'recipient_email' => 'basvuru@ornek.example', 'consent_version' => 'v1' );
$f16_clean    = array( 'qualification' => '16', 'exam_location' => 'iskenderun', 'full_name' => 'Test Aday', 'national_id' => '10000000146', 'phone' => '0212 000 00 00', 'email' => 'aday@ornek.example', 'consent' => true );
$f16_msg      = MaviBelge_Core_Forms_Mail_Builder::build( 'application', $f16_clean, $f16_settings, array(), array( 'qualification' => array( 'value' => $f16[16], 'lines' => $f16_details ) ) );
mb_test( 'Faz 16 e-posta gövdesi: seçilen tarifenin etiketi ve sunucu kaydından ayrıntılar; ham kimlik değeri meslek adı yerine geçmez',
	is_array( $f16_msg ) && false !== strpos( $f16_msg['body'], "Başvurulacak meslek/yeterlilik: Kazıcı Yükleyici (Beko Loder) Operatörü — Seviye 3\n" )
	&& false !== strpos( $f16_msg['body'], "  Ücret kaydı: #16\n" ) && false !== strpos( $f16_msg['body'], "  MYK kodu: Belirtilmemiş\n" ) && false !== strpos( $f16_msg['body'], "  Bağlı yeterlilik: Bağlantısız\n" )
	&& false === strpos( $f16_msg['body'], 'meslek/yeterlilik: 16' ) );
mb_test( 'Faz 16 e-posta konusu ve başlıkları değişmedi; ayrıntılardaki satır sonu başlığa/gövdeye yeni satır olarak giremez',
	'[Mavi Belge] Online Başvuru formu' === $f16_msg['subject'] && array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: aday@ornek.example' ) === $f16_msg['headers']
	&& false === strpos( MaviBelge_Core_Forms_Mail_Builder::build( 'application', $f16_clean, $f16_settings, array(), array( 'qualification' => array( 'value' => "X\r\nBcc: y@ornek.example", 'lines' => array( "A\nB" => "C\r\nD" ) ) ) )['body'], "\nBcc:" ) );
mb_test( 'Faz 16 e-posta: ayrıntı verilmeyen formlarda gövde eski biçimde (geriye uyumlu)',
	"Ad Soyad: Test\n" === substr( MaviBelge_Core_Forms_Mail_Builder::build( 'contact', array( 'full_name' => 'Test', 'email' => 'a@ornek.example', 'message' => 'm', 'consent' => true ), $f16_settings )['body'], 0, strlen( "Ad Soyad: Test\n" ) ) );

/* ---- şema ve kaynak sözleşmesi (WordPress gerektiren yol; davranışı runtime testi doğrular) ---- */
$f16_app_field = null;
foreach ( MaviBelge_Core_Forms_Schema::get( 'application' )['fields'] as $f ) {
	if ( 'qualification' === $f['name'] ) {
		$f16_app_field = $f;
	}
}
$f16_exam_field = null;
foreach ( MaviBelge_Core_Forms_Schema::get( 'exam_request' )['fields'] as $f ) {
	if ( 'qualification' === $f['name'] ) {
		$f16_exam_field = $f;
	}
}
mb_test( 'Faz 16 şema: online başvuru meslek alanı application_fees kaynağından ve zorunlu; sınav talebi formu yeterlilik kaynağında kaldı',
	'application_fees' === $f16_app_field['options_source'] && true === $f16_app_field['required'] && 'select' === $f16_app_field['type'] && 'qualifications' === $f16_exam_field['options_source'] );
$f16_svc  = file_get_contents( dirname( __DIR__, 2 ) . '/public/class-forms-service.php' );
$f16_cat  = file_get_contents( dirname( __DIR__, 2 ) . '/public/class-catalog-service.php' );
mb_test( 'Faz 16 kaynak: liste Catalog_Service::get_application_fee_rows() (Sınav Ücretleri ile aynı görünürlük); gönderimde ÖNBELLEKSİZ get_public_fee() ile yeniden çözülür',
	false !== strpos( $f16_svc, 'MaviBelge_Core_Catalog_Service::get_application_fee_rows()' ) && false !== strpos( $f16_svc, 'MaviBelge_Core_Catalog_Service::get_public_fee( (int) $id )' )
	&& false !== strpos( $f16_svc, "self::verify_application_fee( \$post, \$validated, \$context['options']['application_fees'] )" ) );
mb_test( 'Faz 16 kaynak: görünürlük kararı TEK fonksiyonda (public_fee_dto) — liste ve tek kayıt aynı yoldan; aday sorgusu parçalı (paged), sessiz kesme yok',
	2 === substr_count( $f16_cat, 'self::public_fee_dto( $post, $active_period' ) && false !== strpos( $f16_cat, "'paged'          => \$page," )
	&& false !== strpos( $f16_cat, 'if ( count( $batch ) < self::CANDIDATE_CAP ) {' ) && false !== strpos( $f16_cat, 'self::$candidates_truncated = true;' ) );
mb_test( 'Faz 16 kaynak: e-postadaki meslek/seviye/kod/ilişki yalnız sunucu kaydından (application_fee_row + mail_details); POST metni kullanılmaz',
	false !== strpos( $f16_svc, '$row = MaviBelge_Core_Catalog_Service::application_fee_row( $fee );' ) && false !== strpos( $f16_svc, "'lines' => MaviBelge_Core_Forms_Fee_Options::mail_details( \$row )," ) );
