<?php
/**
 * Geçici yönlendirme loader'ı (mavibelge-redirect-bootstrap.php) testleri — PHP 7.3.
 *
 *   php wordpress-site/tools/ops/redirect-bootstrap/tests/run.php
 *
 * Gerçek `MaviBelge_Core_Redirects_Rules` ve `MaviBelge_Core_Redirects_Service` sınıfları + sahte WordPress
 * yüzeyi (tests/fake-wp.php) kullanılır. "Mavi Belge Core yok" senaryosu ayrı bir PHP sürecinde çalışır
 * (`--no-core`), çünkü aynı süreçte yüklenmiş bir sınıf geri alınamaz. Çıkış kodu: 0 = hepsi geçti.
 */

$mbrb_dir      = dirname( __DIR__ );
$mbrb_site     = dirname( dirname( dirname( $mbrb_dir ) ) );
$mbrb_loader   = $mbrb_dir . '/mavibelge-redirect-bootstrap.php';
$mbrb_manifest = $mbrb_site . '/data/redirects/redirects.manifest.json';
$mbrb_core     = $mbrb_site . '/wp-content/plugins/mavibelge-core/includes/redirects';

require __DIR__ . '/fake-wp.php';

/* ---- alt süreç: Mavi Belge Core sınıfları YOK ---- */
if ( isset( $argv[1] ) && '--no-core' === $argv[1] ) {
	mbrb_env_reset();
	require $mbrb_loader;
	$result = MaviBelge_Redirect_Bootstrap::import_from( array( 'mb_rb_nonce' => wp_create_nonce( MaviBelge_Redirect_Bootstrap::ACTION_IMPORT ) ), $mbrb_manifest, MaviBelge_Redirect_Bootstrap::MANIFEST_SHA256 );
	$rb     = MaviBelge_Redirect_Bootstrap::rollback( array( 'mb_rb_nonce' => wp_create_nonce( MaviBelge_Redirect_Bootstrap::ACTION_ROLLBACK ) ) );
	echo json_encode( array( 'import' => $result['code'], 'rollback' => $rb['code'], 'writes' => count( mbrb_writes() ), 'options' => count( $GLOBALS['mbrb_env']['options'] ) ) );
	exit( 0 );
}

$failures = 0;
$total    = 0;
function mb_test( $description, $condition ) {
	global $failures, $total;
	$total++;
	if ( $condition ) {
		echo "PASS  {$description}\n";
	} else {
		$failures++;
		echo "FAIL  {$description}\n";
	}
}

$mbrb_tmp = sys_get_temp_dir() . '/mbrb_' . getmypid();
@mkdir( $mbrb_tmp, 0700, true );
function mbrb_tmp_file( $name, $bytes ) {
	global $mbrb_tmp;
	$path = $mbrb_tmp . '/' . $name;
	file_put_contents( $path, $bytes );
	return $path;
}

/* ---- kamu isteği: loader yüklenirken yönetim dışı bağlamda HİÇBİR kanca/yazma yok ---- */
mbrb_env_reset( array( 'is_admin' => false ) );
require $mbrb_loader;
mb_test( 'Kamu isteği: loader yüklenince hiçbir kanca kaydedilmez ve hiçbir seçenek yazılmaz', array() === $GLOBALS['mbrb_env']['hooks'] && array() === mbrb_writes() );

$B = 'MaviBelge_Redirect_Bootstrap';
mb_test( 'Loader yüklü, ama Mavi Belge Core sınıfları henüz yüklenmedi (bu süreçte yalnız sonra yüklenir)', class_exists( $B ) && ! class_exists( 'MaviBelge_Core_Redirects_Service', false ) );

/* ---- Mavi Belge Core yok (ayrı süreç) ---- */
$mbrb_out  = array();
$mbrb_code = 1;
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --no-core', $mbrb_out, $mbrb_code );
$mbrb_nc = json_decode( implode( '', $mbrb_out ), true );
mb_test( 'Mavi Belge Core sınıfları yoksa import core_unavailable verir ve HİÇBİR yazma olmaz', 0 === $mbrb_code && is_array( $mbrb_nc ) && 'core_unavailable' === $mbrb_nc['import'] && 0 === $mbrb_nc['writes'] && 0 === $mbrb_nc['options'] );
mb_test( 'Mavi Belge Core sınıfları yoksa rollback da core_unavailable verir, yazma yok', is_array( $mbrb_nc ) && 'core_unavailable' === $mbrb_nc['rollback'] );

require $mbrb_core . '/class-redirects-rules.php';
require $mbrb_core . '/class-redirects-service.php';

/* ---- yönetim bağlamı: kancalar ---- */
mbrb_env_reset();
$B::boot();
$mbrb_hooks = $GLOBALS['mbrb_env']['hooks'];
mb_test( 'Yönetim bağlamı: yalnız admin_menu, admin_notices ve iki oturumlu admin_post eylemi kaydedilir; nopriv eylemi YOK',
	in_array( 'admin_menu', $mbrb_hooks, true ) && in_array( 'admin_post_' . $B::ACTION_IMPORT, $mbrb_hooks, true ) && in_array( 'admin_post_' . $B::ACTION_ROLLBACK, $mbrb_hooks, true )
	&& 0 === count( preg_grep( '/nopriv/', $mbrb_hooks ) ) && 0 === count( preg_grep( '/^(init|wp_loaded|template_redirect|plugins_loaded|muplugins_loaded)$/', $mbrb_hooks ) ) && array() === mbrb_writes() );
mb_test( 'Loader işlemi kendiliğinden başlatmaz: boot() sonrası depo ve durum seçeneği yok', ! array_key_exists( $B::STORE_OPTION, $GLOBALS['mbrb_env']['options'] ) && ! array_key_exists( $B::STATE_OPTION, $GLOBALS['mbrb_env']['options'] ) );

/* ---- sabitler ---- */
mb_test( 'Sabitler: manifest SHA-256 onaylı değer, 29 toplam / 4 etkin, depo adı Core ile aynı',
	'ebd84ce828efbc1b12490b79b838d6534c70141bf04da849aab7070d34597792' === $B::MANIFEST_SHA256 && 29 === $B::EXPECTED_TOTAL && 4 === $B::EXPECTED_ACTIVE && MaviBelge_Core_Redirects_Service::OPTION === $B::STORE_OPTION );
mb_test( 'Varsayılan manifest yolu loader ile aynı dizindeki redirects.manifest.json dosyasıdır', dirname( $mbrb_loader ) . DIRECTORY_SEPARATOR . 'redirects.manifest.json' === $B::default_manifest_path() || dirname( $mbrb_loader ) . '/redirects.manifest.json' === $B::default_manifest_path() );
mb_test( 'Yetkili manifest dosyasının gerçek SHA-256 değeri loader sabitiyle eşit', hash_equals( $B::MANIFEST_SHA256, hash_file( 'sha256', $mbrb_manifest ) ) );

$ok_import   = array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_IMPORT ) );
$ok_rollback = array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_ROLLBACK ) );
$sha         = $B::MANIFEST_SHA256;
$bytes       = file_get_contents( $mbrb_manifest );
$json        = json_decode( $bytes, true );
$rehash      = function ( $data ) {
	$raw = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return array( mbrb_tmp_file( 'm' . md5( $raw ) . '.json', $raw ), hash( 'sha256', $raw ) );
};

/* ---- yetki ve nonce ---- */
mbrb_env_reset( array( 'can' => false ) );
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Yetkisiz kullanıcı (manage_options yok) reddedilir: forbidden, yazma yok', false === $r['ok'] && 'forbidden' === $r['code'] && array() === mbrb_writes() );

mbrb_env_reset();
$r1 = $B::import_from( array(), $mbrb_manifest, $sha );
$r2 = $B::import_from( array( 'mb_rb_nonce' => 'yanlis' ), $mbrb_manifest, $sha );
$r3 = $B::import_from( array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_ROLLBACK ) ), $mbrb_manifest, $sha );
$r4 = $B::import_from( array( 'mb_rb_nonce' => array( 'dizi' ) ), $mbrb_manifest, $sha );
mb_test( 'Nonce yok / yanlış / başka eylemin nonce\'u / dizi -> nonce_invalid, yazma yok',
	'nonce_invalid' === $r1['code'] && 'nonce_invalid' === $r2['code'] && 'nonce_invalid' === $r3['code'] && 'nonce_invalid' === $r4['code'] && array() === mbrb_writes() );

/* ---- manifest bütünlüğü ---- */
mbrb_env_reset();
$tampered = mbrb_tmp_file( 'tampered.json', str_replace( '"/kvkk/"', '"/kvkk-x/"', $bytes ) );
$r        = $B::import_from( $ok_import, $tampered, $sha );
mb_test( 'Bozuk hash (tek bayt değişmiş manifest) reddedilir: manifest_hash_mismatch, yazma yok', 'manifest_hash_mismatch' === $r['code'] && array() === mbrb_writes() );
$r = $B::import_from( $ok_import, $mbrb_manifest, str_repeat( '0', 64 ) );
mb_test( 'Beklenen hash farklıysa doğru dosya da reddedilir (sabit hash bağlayıcı), yazma yok', 'manifest_hash_mismatch' === $r['code'] && array() === mbrb_writes() );
$r = $B::import_from( $ok_import, $mbrb_tmp . '/yok.json', $sha );
mb_test( 'Manifest dosyası yoksa manifest_missing, yazma yok', 'manifest_missing' === $r['code'] && array() === mbrb_writes() );

$bad = mbrb_tmp_file( 'bad.json', '{"schema_version":"1.0.0","record_type":"redirect_rules","rules":[' );
$r   = $B::import_from( $ok_import, $bad, hash_file( 'sha256', $bad ) );
mb_test( 'Bozuk JSON (hash eşleşse bile) reddedilir: manifest_json_invalid, yazma yok', 'manifest_json_invalid' === $r['code'] && array() === mbrb_writes() );
$scalar = mbrb_tmp_file( 'scalar.json', '"metin"' );
$r      = $B::import_from( $ok_import, $scalar, hash_file( 'sha256', $scalar ) );
mb_test( 'JSON nesne değilse reddedilir: manifest_json_invalid', 'manifest_json_invalid' === $r['code'] && array() === mbrb_writes() );

list( $p1, $h1 ) = $rehash( array_merge( $json, array( 'record_type' => 'baska_tur' ) ) );
list( $p2, $h2 ) = $rehash( array_merge( $json, array( 'schema_version' => '2.0.0' ) ) );
$noType          = $json;
unset( $noType['record_type'] );
list( $p3, $h3 ) = $rehash( $noType );
mb_test( 'Yanlış record_type / schema_version / eksik tür reddedilir: manifest_schema_invalid, yazma yok',
	'manifest_schema_invalid' === $B::import_from( $ok_import, $p1, $h1 )['code'] && 'manifest_schema_invalid' === $B::import_from( $ok_import, $p2, $h2 )['code'] && 'manifest_schema_invalid' === $B::import_from( $ok_import, $p3, $h3 )['code'] && array() === mbrb_writes() );

list( $p4, $h4 ) = $rehash( array_merge( $json, array( 'rules' => array() ) ) );
list( $p5, $h5 ) = $rehash( array_merge( $json, array( 'rules' => 'yok' ) ) );
$noRules         = $json;
unset( $noRules['rules'] );
list( $p6, $h6 ) = $rehash( $noRules );
list( $p7, $h7 ) = $rehash( array_merge( $json, array( 'rules' => array( 'a' => $json['rules'][0] ) ) ) );
mb_test( 'Kurallar boş / dizi değil / yok / liste değil -> manifest_rules_invalid, yazma yok',
	'manifest_rules_invalid' === $B::import_from( $ok_import, $p4, $h4 )['code'] && 'manifest_rules_invalid' === $B::import_from( $ok_import, $p5, $h5 )['code'] && 'manifest_rules_invalid' === $B::import_from( $ok_import, $p6, $h6 )['code'] && 'manifest_rules_invalid' === $B::import_from( $ok_import, $p7, $h7 )['code'] && array() === mbrb_writes() );

$short           = $json;
array_pop( $short['rules'] );
list( $p8, $h8 ) = $rehash( $short );
$moreActive      = $json;
foreach ( $moreActive['rules'] as $i => $rule ) {
	if ( false === $rule['active'] ) {
		$moreActive['rules'][ $i ]['active'] = true;
		break;
	}
}
list( $p9, $h9 ) = $rehash( $moreActive );
mb_test( 'Kural sayısı 29 değilse veya etkin sayısı 4 değilse reddedilir: manifest_count_mismatch, yazma yok',
	'manifest_count_mismatch' === $B::import_from( $ok_import, $p8, $h8 )['code'] && 'manifest_count_mismatch' === $B::import_from( $ok_import, $p9, $h9 )['code'] && array() === mbrb_writes() );

$loop                        = $json;
$loop['rules'][0]['target']  = $loop['rules'][0]['source'];
list( $p10, $h10 )           = $rehash( $loop );
mb_test( 'Core validate_set() geçmeyen küme (kendine yönlendirme) reddedilir: rules_invalid, yazma yok', 'rules_invalid' === $B::import_from( $ok_import, $p10, $h10 )['code'] && array() === mbrb_writes() );

$proposedActive = $json;
foreach ( $proposedActive['rules'] as $i => $rule ) {
	if ( true === $rule['active'] ) {
		$proposedActive['rules'][ $i ]['origin'] = 'proposed';
		break;
	}
}
list( $p11, $h11 ) = $rehash( $proposedActive );
mb_test( 'Etkin kural origin=verified değilse reddedilir: active_rule_not_verified, yazma yok', 'active_rule_not_verified' === $B::import_from( $ok_import, $p11, $h11 )['code'] && array() === mbrb_writes() );

/* ---- hedef yoksa ---- */
mbrb_env_reset( array( 'pages' => array( 'gizlilik-politikasi' ) ) );
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Etkin bir kuralın hedefi WordPress\'te yoksa (/kvkk/) reddedilir: target_missing, yazma yok', 'target_missing' === $r['code'] && array() === mbrb_writes() );

/* ---- mevcut depo boş değilse ---- */
$existing = array( array( 'source' => '/eski/', 'target' => '/yeni/', 'status' => 301, 'origin' => 'verified', 'active' => true, 'note' => 'sentetik' ) );
mbrb_env_reset( array( 'options' => array( 'mavibelge_core_redirects' => $existing ) ) );
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Mevcut yönlendirme deposu boş değilse reddedilir: store_not_empty, mevcut veri ezilmez, yazma yok', 'store_not_empty' === $r['code'] && array() === mbrb_writes() && $existing === get_option( 'mavibelge_core_redirects' ) );
mbrb_env_reset( array( 'options' => array( 'mavibelge_core_redirects' => 'bozuk' ) ) );
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Depo dizi olmayan bozuk değer taşıyorsa da boş sayılmaz: store_not_empty, yazma yok', 'store_not_empty' === $r['code'] && array() === mbrb_writes() && 'bozuk' === get_option( 'mavibelge_core_redirects' ) );

/* ---- başarılı import ---- */
mbrb_env_reset();
$r      = $B::import_from( $ok_import, $mbrb_manifest, $sha );
$stored = get_option( 'mavibelge_core_redirects' );
$state  = get_option( $B::STATE_OPTION );
$active = count( array_filter( is_array( $stored ) ? $stored : array(), function ( $x ) {
	return true === $x['active'];
} ) );
mb_test( 'Doğru manifest kaydedilir: ok, imported, 29 toplam / 4 etkin', true === $r['ok'] && 'imported' === $r['code'] && 29 === $r['total'] && 4 === $r['active'] && is_array( $stored ) && 29 === count( $stored ) && 4 === $active );
mb_test( 'Kaydedilen kurallar manifestteki kurallarla BİREBİR aynı (sıra, alanlar, etkinlik)', $json['rules'] === $stored );
mb_test( 'Depo autoload KAPALI yazıldı (Core save_rules() yolu)', false === $GLOBALS['mbrb_env']['autoload']['mavibelge_core_redirects'] );
mb_test( 'Durum kaydı: imported, özet Core digest() ile eşit, başlangıç durumu absent, kişisel veri alanı yok',
	is_array( $state ) && 'imported' === $state['state'] && MaviBelge_Core_Redirects_Rules::digest( $json['rules'] ) === $state['digest'] && 'absent' === $state['original'] && $sha === $state['manifest_sha256']
	&& array() === array_diff( array_keys( $state ), array( 'state', 'digest', 'content_sha256', 'manifest_sha256', 'original', 'total', 'active', 'loader_version', 'updated_at' ) ) && false === $GLOBALS['mbrb_env']['autoload'][ $B::STATE_OPTION ] );
mb_test( 'Yalnız iki seçenek yazıldı (depo + durum); tema/kalıcı bağlantı/form/SMTP seçeneğine dokunulmadı',
	array() === array_diff( array_unique( array_column( mbrb_writes(), 1 ) ), array( 'mavibelge_core_redirects', $B::STATE_OPTION ) ) );
$health = count( MaviBelge_Core_Redirects_Service::rules() );
mb_test( 'Sağlık ekranının okuduğu Core rules() 29 kayıt döndürür', 29 === $health );
$idx = MaviBelge_Core_Redirects_Rules::build_index( MaviBelge_Core_Redirects_Service::rules() );
mb_test( 'Çalışma zamanı dizini: /kvkk-2/ -> /kvkk/ ve /gizlilik-politikamiz/ -> /gizlilik-politikasi/ 301, dizinde tam 4 kural',
	4 === count( $idx ) && '/kvkk/' === MaviBelge_Core_Redirects_Rules::match( $idx, '/kvkk-2/' )['target'] && 301 === MaviBelge_Core_Redirects_Rules::match( $idx, '/kvkk-2' )['status']
	&& '/gizlilik-politikasi/' === MaviBelge_Core_Redirects_Rules::match( $idx, '/gizlilik-politikamiz/' )['target'] );

/* ---- ikinci import ---- */
$before = count( mbrb_writes() );
$r      = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'İkinci import reddedilir: already_imported, yeni yazma yok', 'already_imported' === $r['code'] && $before === count( mbrb_writes() ) );
$GLOBALS['mbrb_env']['options'][ $B::STATE_OPTION ]['state'] = 'baska';
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Durum kaydı bozulsa bile dolu depo ikinci importu engeller: store_not_empty', 'store_not_empty' === $r['code'] && $before === count( mbrb_writes() ) );
$GLOBALS['mbrb_env']['options'][ $B::STATE_OPTION ]['state'] = 'imported';

/* ---- rollback ---- */
$GLOBALS['mbrb_env']['can'] = false;
$r                          = $B::rollback( $ok_rollback );
mb_test( 'Rollback yetkisiz kullanıcıda reddedilir: forbidden, depo korunur', 'forbidden' === $r['code'] && 29 === count( get_option( 'mavibelge_core_redirects' ) ) );
$GLOBALS['mbrb_env']['can'] = true;
$r                          = $B::rollback( $ok_import );
mb_test( 'Rollback import eyleminin nonce\'uyla reddedilir: nonce_invalid, depo korunur', 'nonce_invalid' === $r['code'] && 29 === count( get_option( 'mavibelge_core_redirects' ) ) );

$changed                  = get_option( 'mavibelge_core_redirects' );
$changed[0]['note']       = 'yönetici değiştirdi';
$GLOBALS['mbrb_env']['options']['mavibelge_core_redirects'] = $changed;
$before                   = count( mbrb_writes() );
$r                        = $B::rollback( $ok_rollback );
mb_test( 'Import sonrası depo değiştirilmişse rollback reddedilir: store_changed, değişiklik korunur', 'store_changed' === $r['code'] && $changed === get_option( 'mavibelge_core_redirects' ) && $before === count( mbrb_writes() ) );
$GLOBALS['mbrb_env']['options']['mavibelge_core_redirects'] = $json['rules'];

$r = $B::rollback( $ok_rollback );
mb_test( 'Rollback loader\'ın boş başlangıçtan oluşturduğu depoyu kaldırır: rolled_back, seçenek yok, durum rolled_back',
	true === $r['ok'] && 'rolled_back' === $r['code'] && ! array_key_exists( 'mavibelge_core_redirects', $GLOBALS['mbrb_env']['options'] ) && 'rolled_back' === get_option( $B::STATE_OPTION )['state'] );
$r = $B::rollback( $ok_rollback );
mb_test( 'İkinci rollback reddedilir: rollback_not_allowed', 'rollback_not_allowed' === $r['code'] );
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Rollback sonrası depo yeniden boş: kontrollü yeni import mümkündür (29/4)', 'imported' === $r['code'] && 29 === count( get_option( 'mavibelge_core_redirects' ) ) );

mbrb_env_reset( array( 'options' => array( 'mavibelge_core_redirects' => array() ) ) );
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Boş dizi depo boş sayılır; durum kaydı original=empty_array', 'imported' === $r['code'] && 'empty_array' === get_option( $B::STATE_OPTION )['original'] );
$r = $B::rollback( $ok_rollback );
mb_test( 'Boş dizi başlangıcında rollback depoyu yine boş diziye döndürür', 'rolled_back' === $r['code'] && array() === get_option( 'mavibelge_core_redirects', 'yok' ) );

mbrb_env_reset( array( 'options' => array( 'mavibelge_core_redirects' => $existing ) ) );
$r = $B::rollback( $ok_rollback );
mb_test( 'Loader\'ın oluşturmadığı (başlangıçta dolu) depo rollback edilmez: rollback_not_allowed, yazma yok', 'rollback_not_allowed' === $r['code'] && array() === mbrb_writes() && $existing === get_option( 'mavibelge_core_redirects' ) );

/* ---- atomiklik: yazma doğrulanamazsa ---- */
mbrb_env_reset( array( 'fail_update' => array( 'mavibelge_core_redirects' ) ) );
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Depo yazımı geri okunamazsa başarı sayılmaz: verify_failed, depo yok, durum kaydı kalmaz',
	false === $r['ok'] && 'verify_failed' === $r['code'] && ! array_key_exists( 'mavibelge_core_redirects', $GLOBALS['mbrb_env']['options'] ) && ! array_key_exists( $B::STATE_OPTION, $GLOBALS['mbrb_env']['options'] ) );
mbrb_env_reset( array( 'fail_update' => array( $B::STATE_OPTION ) ) );
$r = $B::import_from( $ok_import, $mbrb_manifest, $sha );
mb_test( 'Durum kaydı yazılamazsa depoya HİÇ yazılmaz: state_write_failed', 'state_write_failed' === $r['code'] && ! array_key_exists( 'mavibelge_core_redirects', $GLOBALS['mbrb_env']['options'] ) );

/* ---- mesajlar ---- */
$codes   = array( 'imported', 'rolled_back', 'forbidden', 'nonce_invalid', 'bad_method', 'core_unavailable', 'already_imported', 'store_not_empty', 'manifest_missing', 'manifest_hash_mismatch', 'manifest_json_invalid', 'manifest_schema_invalid', 'manifest_rules_invalid', 'manifest_count_mismatch', 'rules_invalid', 'active_rule_not_verified', 'target_missing', 'save_failed', 'verify_failed', 'state_write_failed', 'rollback_not_allowed', 'store_changed', 'rollback_failed' );
$allMsg  = true;
foreach ( $codes as $code ) {
	$m = $B::message( $code );
	if ( ! is_string( $m ) || '' === $m || false !== strpos( $m, '@' ) || 1 === preg_match( '#/home/|public_html|[A-Z]:\\\\#', $m ) ) {
		$allMsg = false;
	}
}
mb_test( 'Her sonuç kodunun sabit, boş olmayan mesajı var; e-posta/sunucu yolu içermez', $allMsg );
mb_test( 'Bilinmeyen/kullanıcı girdisi sonuç kodu yansıtılmaz (genel mesaj döner)', $B::message( '<script>x</script>' ) === $B::message( 'bilinmeyen' ) && false === strpos( $B::message( '<script>x</script>' ), '<script>' ) );
mb_test( 'Başarı mesajı dosyaların File Manager ile silinmesi talimatını içerir', false !== strpos( $B::message( 'imported' ), 'sil' ) );

/* ---- yönetim sayfası ---- */
mbrb_env_reset( array( 'can' => false ) );
ob_start();
$B::render_page();
$html = ob_get_clean();
mb_test( 'Yönetim sayfası yetkisiz kullanıcıya form/nonce üretmez', false === strpos( $html, '<form' ) && false === strpos( $html, 'ok:' ) );
mbrb_env_reset();
$_GET = array();
ob_start();
$B::render_page();
$html = ob_get_clean();
mb_test( 'Yönetim sayfası: boş depoda nonce korumalı "Yönlendirmeleri içe aktar" formu var, rollback formu yok',
	false !== strpos( $html, 'Yönlendirmeleri içe aktar' ) && false !== strpos( $html, 'value="ok:' . $B::ACTION_IMPORT . '"' ) && false !== strpos( $html, 'method="post"' ) && false === strpos( $html, 'value="ok:' . $B::ACTION_ROLLBACK . '"' ) );
$B::import_from( $ok_import, $mbrb_manifest, $sha );
$_GET = array( 'mb_rb_result' => 'imported' );
ob_start();
$B::render_page();
$html = ob_get_clean();
mb_test( 'Yönetim sayfası import sonrası: import formu yok, nonce korumalı rollback formu ve silme talimatı var',
	false === strpos( $html, 'value="ok:' . $B::ACTION_IMPORT . '"' ) && false !== strpos( $html, 'value="ok:' . $B::ACTION_ROLLBACK . '"' ) && false !== strpos( $html, 'mavibelge-redirect-bootstrap.php' ) && false !== strpos( $html, 'redirects.manifest.json' ) );
$_GET = array();

/* ---- statik kaynak taraması ---- */
$src  = file_get_contents( $mbrb_loader );
$code = '';
foreach ( token_get_all( $src ) as $tok ) {
	if ( is_array( $tok ) && in_array( $tok[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
		continue;
	}
	$code .= is_array( $tok ) ? $tok[1] : $tok;
}
mb_test( 'Kaynak: doğrudan erişim koruması ilk satırlarda (ABSPATH yoksa exit)', 1 === preg_match( '/if\s*\(\s*!\s*defined\(\s*\'ABSPATH\'\s*\)\s*\)\s*\{\s*exit;/', substr( $src, 0, 2500 ) ) );
mb_test( 'Kaynak: parola/gizli anahtar/token/kimlik bilgisi yok', 0 === preg_match( '/pass(word|wd)|parola|secret|api[_-]?key|token|DB_PASSWORD|AUTH_KEY|LOGGED_IN_KEY/i', $src ) );
mb_test( 'Kaynak: e-posta adresi veya e-posta gönderimi yok', 0 === preg_match( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}|wp_mail|\bmail\s*\(/', $src ) );
mb_test( 'Kaynak: sunucu yolu yok (/home/, public_html, /var/www, Windows sürücü yolu)', 0 === preg_match( '#/home/|public_html|/var/www|[A-Za-z]:\\\\#', $src ) );
mb_test( 'Kaynak: doğrudan SQL, dosya yazma/silme, .htaccess, tema/kalıcı bağlantı değişikliği yok',
	0 === preg_match( '/\$wpdb|file_put_contents|unlink|rename\s*\(|fopen|htaccess|switch_theme|permalink_structure|flush_rewrite_rules|eval\s*\(|base64_decode|curl_|shell_exec|exec\s*\(|system\s*\(/i', $code ) );
mb_test( 'Kaynak: PHP 7.4+ sözdizimi yok (??=, fn(), ?->, match(, str_contains, typed property)',
	0 === preg_match( '/\?\?=|\bfn\s*\(|\?->|\bmatch\s*\(|str_contains|str_starts_with|str_ends_with|(private|public|protected)\s+(static\s+)?(int|string|bool|array|float|\?)\s*\$/', $code ) );
mb_test( 'Kaynak: yazma yalnız Core save_rules() ile; depoya doğrudan update_option/add_option çağrısı yok',
	false !== strpos( $src, "'save_rules'" ) && false !== strpos( $src, "'validate_set'" ) && 0 === preg_match( '/update_option\s*\(\s*self::STORE_OPTION\s*,\s*\$(rules|incoming)/', $src ) );

/* ---- temizlik ---- */
foreach ( glob( $mbrb_tmp . '/*' ) ?: array() as $f ) {
	unlink( $f );
}
@rmdir( $mbrb_tmp );

echo "\n{$total} test, " . ( $total - $failures ) . " geçti, {$failures} başarısız.\n";
exit( $failures > 0 ? 1 : 0 );
