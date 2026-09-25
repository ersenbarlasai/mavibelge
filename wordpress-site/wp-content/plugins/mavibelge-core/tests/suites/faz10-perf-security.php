<?php
/**
 * Faz 10 — SAF birim testleri: önbellek, oran sınırı, sertleştirme, audit zinciri, bakım, sağlık kontrolleri,
 * uyumluluk, uninstall kapsamı ve kaynak taraması. WordPress gerektirmez.
 */

/** Bellek içi depo: $failWrite true ise yazma başarısız, $corrupt true ise okuma bozuk değer verir. */
class MB_Test_Memory_Store implements MaviBelge_Core_Rate_Limit_Store {
	public $data = array();
	public $failWrite = false;
	public $corrupt = false;
	public function get( $key ) {
		if ( $this->corrupt ) {
			return 'bozuk';
		}
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : false;
	}
	public function set( $key, $value, $ttl ) {
		if ( $this->failWrite ) {
			return false;
		}
		$this->data[ $key ] = $value;
		return true;
	}
}

/* ---------------- Cache: anahtar ---------------- */
$C = 'MaviBelge_Core_Cache';
$k = $C::key( 3, 'news', 'tr_TR', 1, array( 'type' => 'haber' ) );
mb_test( 'Faz 10 cache anahtarı: mbc_<nesil>_<sha1> biçimi', 1 === preg_match( '/^mbc_3_[0-9a-f]{40}$/', $k ) );
mb_test( 'Faz 10 cache anahtarı: aynı girdi aynı anahtar; filtre anahtar SIRASI önemsiz',
	$k === $C::key( 3, 'news', 'tr_TR', 1, array( 'type' => 'haber' ) ) && $C::key( 1, 'x', 'tr_TR', 1, array( 'a' => '1', 'b' => '2' ) ) === $C::key( 1, 'x', 'tr_TR', 1, array( 'b' => '2', 'a' => '1' ) ) );
mb_test( 'Faz 10 cache anahtarı: dil, sayfa, filtre, tür ve nesil AYRI anahtar üretir',
	$k !== $C::key( 3, 'news', 'en_US', 1, array( 'type' => 'haber' ) ) && $k !== $C::key( 3, 'news', 'tr_TR', 2, array( 'type' => 'haber' ) )
	&& $k !== $C::key( 3, 'news', 'tr_TR', 1, array( 'type' => 'duyuru' ) ) && $k !== $C::key( 3, 'docs', 'tr_TR', 1, array( 'type' => 'haber' ) ) && $k !== $C::key( 4, 'news', 'tr_TR', 1, array( 'type' => 'haber' ) ) );
mb_test( 'Faz 10 cache anahtarı: filtre değeri sınırı belirsiz değil (a=b&c ile a=b, c=... çakışmaz)',
	$C::key( 1, 't', 'l', 1, array( 'a' => 'b&c=d' ) ) !== $C::key( 1, 't', 'l', 1, array( 'a' => 'b', 'c' => 'd' ) ) );
mb_test( 'Faz 10 cache anahtarı: bool ve boş filtre ayrı', $C::key( 1, 't', 'l', 1, array( 'p' => true ) ) !== $C::key( 1, 't', 'l', 1, array( 'p' => false ) ) );

/* ---------------- Cache: açık/kapalı kipler ---------------- */
mb_test( 'Faz 10 cache kipi: normal ziyaretçi -> AÇIK', true === $C::is_enabled_for( array( 'debug' => false, 'disabled_const' => false, 'can_edit' => false ) ) );
mb_test( 'Faz 10 cache kipi: WP_DEBUG -> KAPALI', false === $C::is_enabled_for( array( 'debug' => true, 'disabled_const' => false, 'can_edit' => false ) ) );
mb_test( 'Faz 10 cache kipi: MAVIBELGE_CACHE_DISABLED -> KAPALI', false === $C::is_enabled_for( array( 'debug' => false, 'disabled_const' => true, 'can_edit' => false ) ) );
mb_test( 'Faz 10 cache kipi: düzenleme yetkili oturum -> KAPALI (önizleme taze)', false === $C::is_enabled_for( array( 'debug' => false, 'disabled_const' => false, 'can_edit' => true ) ) );
mb_test( 'Faz 10 cache kipi: süzgeç geçersiz kılması (bool) son sözü söyler; bool olmayan yok sayılır',
	true === $C::is_enabled_for( array( 'debug' => true, 'override' => true ) ) && false === $C::is_enabled_for( array( 'override' => false ) ) && false === $C::is_enabled_for( array( 'debug' => true, 'override' => 'evet' ) ) );

/* ---------------- Cache: DTO saflığı ve paket ---------------- */
mb_test( 'Faz 10 cache: yalnız saf DTO (skaler/null/dizi) saklanabilir; nesne/iç içe nesne REDDEDİLİR',
	$C::is_pure_dto( array( 'a' => array( 1, 'x', null, true, 1.5 ) ) ) && ! $C::is_pure_dto( array( 'o' => new stdClass() ) ) && ! $C::is_pure_dto( array( 'a' => array( new stdClass() ) ) ) );
mb_test( 'Faz 10 cache paketi: nesil uyuşmazsa/bozuksa null (fail-open okuma)',
	array( 1 ) === $C::unwrap( array( 'g' => '2', 'd' => array( 1 ) ), 2 ) && null === $C::unwrap( array( 'g' => '1', 'd' => array( 1 ) ), 2 ) && null === $C::unwrap( 'bozuk', 2 ) && null === $C::unwrap( array( 'g' => '2' ), 2 ) && null === $C::unwrap( array( 'g' => '2', 'd' => 'x' ), 2 ) );

/* ---------------- Rate_Limit ---------------- */
$RL  = 'MaviBelge_Core_Rate_Limit';
$lim = array( 'per_client' => 3, 'window' => 60, 'global' => 5, 'global_window' => 120 );
mb_test( 'Faz 10 oran sınırı: evaluate() Faz 8 davranışıyla AYNI (ok/client/global/unavailable)',
	'ok' === $RL::evaluate( 2, 4, $lim ) && 'client_limited' === $RL::evaluate( 3, 0, $lim ) && 'global_limited' === $RL::evaluate( 0, 5, $lim ) && 'unavailable' === $RL::evaluate( null, 0, $lim ) && 'unavailable' === $RL::evaluate( 0, '1', $lim ) && 'unavailable' === $RL::evaluate( -1, 0, $lim )
	&& 'client_limited' === MaviBelge_Core_Forms_Security::evaluate_rate( 3, 0, $lim ) );
mb_test( 'Faz 10 oran sınırı: counter_value(): yok->0, int, rakam dizgesi->int; bozuk/negatif/dizi -> null',
	0 === $RL::counter_value( false ) && 4 === $RL::counter_value( 4 ) && 7 === $RL::counter_value( '7' ) && null === $RL::counter_value( -1 ) && null === $RL::counter_value( 'abc' ) && null === $RL::counter_value( '1e3' ) && null === $RL::counter_value( array( 1 ) ) && null === $RL::counter_value( 1.5 ) );
mb_test( 'Faz 10 oran sınırı: client_id() HMAC (ham IP içermez), geçersiz IP -> unknown özeti',
	64 === strlen( $RL::client_id( '203.0.113.9', 's' ) ) && false === strpos( $RL::client_id( '203.0.113.9', 's' ), '203' ) && $RL::client_id( 'zz', 's' ) === $RL::client_id( '', 's' ) && $RL::client_id( '203.0.113.9', 's' ) !== $RL::client_id( '203.0.113.9', 't' ) );
$st = new MB_Test_Memory_Store();
$r1 = $RL::check_and_hit( $st, 'c', 'g', $lim );
$r2 = $RL::check_and_hit( $st, 'c', 'g', $lim );
$r3 = $RL::check_and_hit( $st, 'c', 'g', $lim );
$r4 = $RL::check_and_hit( $st, 'c', 'g', $lim );
mb_test( 'Faz 10 oran sınırı: check_and_hit 3 kez ok, 4. deneme client_limited; sayaç yazıldı', 'ok' === $r1 && 'ok' === $r2 && 'ok' === $r3 && 'client_limited' === $r4 && 3 === $st->data['c'] && 3 === $st->data['g'] );
$st = new MB_Test_Memory_Store();
$st->data['g'] = 5;
mb_test( 'Faz 10 oran sınırı: genel sayaç dolu -> global_limited, istemci sayacı YAZILMAZ', 'global_limited' === $RL::check_and_hit( $st, 'c', 'g', $lim ) && ! isset( $st->data['c'] ) );
$st            = new MB_Test_Memory_Store();
$st->failWrite = true;
mb_test( 'Faz 10 oran sınırı FAIL-CLOSED: sayaç YAZILAMAZSA unavailable (izin verilmez)', 'unavailable' === $RL::check_and_hit( $st, 'c', 'g', $lim ) );
$st          = new MB_Test_Memory_Store();
$st->corrupt = true;
mb_test( 'Faz 10 oran sınırı FAIL-CLOSED: sayaç OKUNAMAZ/bozuksa unavailable; peek() de unavailable; hit() false',
	'unavailable' === $RL::check_and_hit( $st, 'c', 'g', $lim ) && 'unavailable' === $RL::peek( $st, 'c', null, $lim ) && false === $RL::hit( $st, 'c', null, $lim ) );
$st  = new MB_Test_Memory_Store();
$llm = MaviBelge_Core_Hardening::login_limits();
for ( $i = 0; $i < 10; $i++ ) {
	$RL::hit( $st, 'l', null, $llm );
}
mb_test( 'Faz 10 giriş sınırı: 10 başarısız denemeye kadar ok, 10. sonrası client_limited (genel sınır yok)', 10 === $st->data['l'] && 'client_limited' === $RL::peek( $st, 'l', null, $llm ) );
$st = new MB_Test_Memory_Store();
for ( $i = 0; $i < 9; $i++ ) {
	$RL::hit( $st, 'l', null, $llm );
}
mb_test( 'Faz 10 giriş sınırı: 9 başarısız denemede hâlâ ok', 'ok' === $RL::peek( $st, 'l', null, $llm ) );

/* ---------------- Hardening (saf) ---------------- */
$H = 'MaviBelge_Core_Hardening';
mb_test( 'Faz 10 REST allowlist: varsayılan (boş) hiçbir anonim yola izin vermez; oembed dahil',
	! $H::route_allowed( '/wp/v2/posts', array() ) && ! $H::route_allowed( '/oembed/1.0/embed', array() ) && ! $H::route_allowed( '', array( '/x' ) ) && ! $H::route_allowed( null, array( '/x' ) ) && ! $H::route_allowed( '/x', 'x' ) );
mb_test( 'Faz 10 REST allowlist: ön ek + alt yol eşleşir, benzer ad (x/v10) eşleşmez, boş/geçersiz ön ek yok sayılır',
	$H::route_allowed( '/x/v1/form', array( '/x/v1' ) ) && $H::route_allowed( '/x/v1', array( 'x/v1/' ) ) && ! $H::route_allowed( '/x/v10', array( '/x/v1' ) ) && ! $H::route_allowed( '/anything', array( '', '/', 5 ) ) );
mb_test( 'Faz 10 giriş hatası: hesap var/yok sızdıran kodlar TEK genel hataya indirgenir; ilgisiz kod indirgenmez',
	$H::is_credential_failure( array( 'invalid_username' ) ) && $H::is_credential_failure( array( 'incorrect_password' ) ) && $H::is_credential_failure( array( 'invalid_email' ) ) && ! $H::is_credential_failure( array( 'mb_login_limited' ) ) && ! $H::is_credential_failure( array() ) );
mb_test( 'Faz 10 giriş: istemci anahtarı HAM IP içermez ve sabit uzunlukta', 1 === preg_match( '/^mbl_rc_[0-9a-f]{32}$/', $H::login_client_key( $RL::client_id( '198.51.100.7', 's' ) ) ) && 10 === $llm['per_client'] && 900 === $llm['window'] );
$hd = $H::security_headers();
mb_test( 'Faz 10 güvenli başlıklar: yalnız güvenli alt küme; HSTS ve CSP YOK',
	'nosniff' === $hd['X-Content-Type-Options'] && 'strict-origin-when-cross-origin' === $hd['Referrer-Policy'] && 'SAMEORIGIN' === $hd['X-Frame-Options'] && 'camera=(), microphone=(), geolocation=()' === $hd['Permissions-Policy']
	&& 4 === count( $hd ) && ! isset( $hd['Strict-Transport-Security'] ) && ! isset( $hd['Content-Security-Policy'] ) );

/* ---------------- Audit zinciri ---------------- */
$AC   = 'MaviBelge_Core_Audit_Chain';
$mkrow = function ( $id, $event, $prev, $ctx = '{}' ) use ( $AC ) {
	$row             = array( 'id' => $id, 'event_type' => $event, 'object_type' => 'x', 'object_id' => $id, 'user_id' => 1, 'context' => $ctx, 'created_at_gmt' => '2026-01-0' . $id . ' 00:00:00' );
	$row['prev_hash'] = $prev;
	$row['row_hash']  = $AC::compute( $prev, $row );
	return $row;
};
$a = $mkrow( 1, 'content_meta_changed', '' );
$b = $mkrow( 2, 'content_meta_changed', $a['row_hash'], '{"a":"b|c"}' );
$c = $mkrow( 3, 'content_meta_changed', $b['row_hash'] );
$f = $mkrow( 4, 'form_submitted', '' ); // form zinciri ayrı
$res = $AC::verify( array( $a, $b, $c, $f ) );
mb_test( 'Faz 10 audit zinciri: sağlam iki zincir (core+form) doğrulanır', true === $res['ok'] && 4 === $res['checked'] && 0 === $res['legacy'] && null === $res['first_bad_id'] );
$bad         = $b;
$bad['context'] = '{"a":"kurcalandi"}';
$res         = $AC::verify( array( $a, $bad, $c ) );
mb_test( 'Faz 10 audit zinciri: değiştirilmiş satır (bağlam) tespit edilir -> row_modified, doğru id', false === $res['ok'] && 'row_modified' === $res['reason'] && 2 === $res['first_bad_id'] );
$res = $AC::verify( array( $a, $c ) );
mb_test( 'Faz 10 audit zinciri: aradan SİLİNEN satır tespit edilir -> chain_break', false === $res['ok'] && 'chain_break' === $res['reason'] && 3 === $res['first_bad_id'] );
$res = $AC::verify( array( $b, $c ) );
mb_test( 'Faz 10 audit zinciri: saklama silmesi sonrası ilk kalan satırın prev_hash\'i ÇAPA kabul edilir', true === $res['ok'] && 2 === $res['checked'] );
$forged             = $c;
$forged['row_hash'] = str_repeat( 'a', 64 );
mb_test( 'Faz 10 audit zinciri: row_hash yeniden yazılırsa (bağlam ile uyuşmazsa) yakalanır; bozuk hash biçimi yakalanır',
	false === $AC::verify( array( $a, $b, $forged ) )['ok'] && 'malformed_hash' === $AC::verify( array( array_merge( $a, array( 'row_hash' => 'xyz' ) ) ) )['reason'] );
$legacy = array( 'id' => 0, 'event_type' => 'form_submitted', 'row_hash' => '', 'prev_hash' => '' );
$res    = $AC::verify( array( $legacy, $a, $b ) );
mb_test( 'Faz 10 audit zinciri: eski sürüm (row_hash=\'\') satırları "legacy" sayılır, yalnız v2 satırları denetlenir', true === $res['ok'] && 1 === $res['legacy'] && 2 === $res['checked'] );
mb_test( 'Faz 10 audit zinciri: grup ayrımı (form_* ayrı zincir) ve saklama süreleri 90/365',
	'form' === $AC::group( 'form_rejected' ) && 'core' === $AC::group( 'import_run_started' ) && 90 === $AC::retention_days( 'form' ) && 365 === $AC::retention_days( 'core' ) );
mb_test( 'Faz 10 audit zinciri: uzunluk önekli hash — alan sınırı kaydırma çakışması yok',
	$AC::compute( '', array( 'event_type' => 'a|', 'object_type' => 'b' ) ) !== $AC::compute( '', array( 'event_type' => 'a', 'object_type' => '|b' ) ) && 64 === strlen( $AC::compute( '', array() ) ) );
mb_test( 'Faz 10 audit tablosu: sürüm 2 (v1->v2 yükseltme)', '2' === MaviBelge_Core_Audit_Log::TABLE_VERSION );

/* ---------------- Bakım ---------------- */
$M   = 'MaviBelge_Core_Maintenance';
$now = 1800000000;
$nm  = str_repeat( 'a', 32 ) . '.pdf';
mb_test( 'Faz 10 bakım: yalnız rastgele adlı (32 hex + uzantı) ve 1 saatten eski geçici dosya silinir',
	$M::is_stale_temp_file( $nm, $now - 3601, $now ) && ! $M::is_stale_temp_file( $nm, $now - 3599, $now ) && ! $M::is_stale_temp_file( $nm, $now - 3600, $now ) );
mb_test( 'Faz 10 bakım: .htaccess, index.php, kısa/büyük harfli/yol geçişli/uzantısız ad ASLA silinmez',
	! $M::is_stale_temp_file( '.htaccess', 1, $now ) && ! $M::is_stale_temp_file( 'index.php', 1, $now ) && ! $M::is_stale_temp_file( 'abc.pdf', 1, $now ) && ! $M::is_stale_temp_file( strtoupper( $nm ), 1, $now ) && ! $M::is_stale_temp_file( '../' . $nm, 1, $now ) && ! $M::is_stale_temp_file( str_repeat( 'a', 32 ), 1, $now ) && ! $M::is_stale_temp_file( $nm, 'x', $now ) );
mb_test( 'Faz 10 bakım: kanca adı sabit', 'mavibelge_core_daily_maintenance' === $M::HOOK );

/* ---------------- Sağlık kontrolleri ---------------- */
$HC   = 'MaviBelge_Core_Health_Checks';
$good = array(
	'php_version' => '8.3.1', 'extensions' => array_fill_keys( $HC::REQUIRED_EXTENSIONS, true ), 'db_engines' => array( 'wp_mb_audit_log' => 'InnoDB' ), 'db_charset' => 'utf8mb4', 'db_collate' => 'utf8mb4_unicode_520_ci',
	'permalink_structure' => '/%postname%/', 'wp_cron_disabled' => false, 'maintenance_scheduled' => true, 'disk_free_bytes' => 5 * 1073741824, 'uploads_writable' => true, 'content_writable' => true,
	'wp_version' => '7.1', 'wp_debug' => false, 'wp_debug_display' => false, 'file_edit_disallowed' => true, 'is_https' => true, 'environment_type' => 'production', 'blog_public' => true,
	'forms' => array( 'total' => 6, 'closed' => 2, 'reasons' => array( 'not_enabled' => 2 ) ), 'redirects' => array( 'total' => 10, 'active' => 8 ),
	'audit' => array( 'rows' => 10, 'bytes' => 1024, 'chain' => array( 'ok' => true, 'checked' => 10, 'legacy' => 0 ) ), 'cache' => array( 'enabled' => true, 'generation' => '4' ),
);
$st_of = function ( array $env, $id ) use ( $HC ) {
	foreach ( $HC::evaluate( $env ) as $check ) {
		if ( $check['id'] === $id ) {
			return $check['status'];
		}
	}
	return null;
};
$all = $HC::evaluate( $good );
$okShape = true;
foreach ( $all as $check ) {
	$okShape = $okShape && isset( $check['id'], $check['label'], $check['status'], $check['detail'] ) && in_array( $check['status'], $HC::STATUSES, true );
}
mb_test( 'Faz 10 sağlık: çıktı biçimi {id,label,status,detail}; iyi ortamda hiç fail yok', $okShape && 0 === $HC::summary( $all )['fail'] );
mb_test( 'Faz 10 sağlık: PHP 7.3 -> warn (EOL kısıt açıkça yazılır, gizlenmez); 7.2 -> fail; 8.3 -> ok',
	'warn' === $st_of( array_merge( $good, array( 'php_version' => '7.3.33' ) ), 'php_version' ) && 'fail' === $st_of( array_merge( $good, array( 'php_version' => '7.2.5' ) ), 'php_version' ) && 'ok' === $st_of( $good, 'php_version' ) );
$p73 = array_merge( $good, array( 'php_version' => '7.3.33' ) );
$txt = '';
foreach ( $HC::evaluate( $p73 ) as $check ) {
	if ( 'php_version' === $check['id'] ) {
		$txt = $check['detail'];
	}
}
mb_test( 'Faz 10 sağlık: PHP 7.3 ayrıntısı "EOL" ve "güvenli bir platform DEĞİLDİR" der', false !== strpos( $txt, 'EOL' ) && false !== strpos( $txt, 'güvenli bir platform DEĞİLDİR' ) );
$noext                 = $good['extensions'];
$noext['zip']          = false;
mb_test( 'Faz 10 sağlık: eksik PHP uzantısı -> fail ve adı ayrıntıda', 'fail' === $st_of( array_merge( $good, array( 'extensions' => $noext ) ), 'php_extensions' ) );
mb_test( 'Faz 10 sağlık: MyISAM eklenti tablosu -> fail; hiç tablo yok -> info',
	'fail' === $st_of( array_merge( $good, array( 'db_engines' => array( 'wp_mb_audit_log' => 'MyISAM' ) ) ), 'db_engine' ) && 'info' === $st_of( array_merge( $good, array( 'db_engines' => array() ) ), 'db_engine' ) );
mb_test( 'Faz 10 sağlık: üretimde WP_DEBUG_DISPLAY açık -> fail; WP_DEBUG açık -> warn; üretim dışında display açık -> fail DEĞİL',
	'fail' === $st_of( array_merge( $good, array( 'wp_debug_display' => true ) ), 'debug' ) && 'warn' === $st_of( array_merge( $good, array( 'wp_debug' => true ) ), 'debug' ) && 'ok' === $st_of( array_merge( $good, array( 'wp_debug_display' => true, 'environment_type' => 'local' ) ), 'debug' ) );
mb_test( 'Faz 10 sağlık: WordPress 6.9.x -> warn (koşullu/legacy, kilitli değil); 6.8 -> fail; 7.x -> info',
	'warn' === $st_of( array_merge( $good, array( 'wp_version' => '6.9.9' ) ), 'wp_version' ) && 'fail' === $st_of( array_merge( $good, array( 'wp_version' => '6.8.2' ) ), 'wp_version' ) && 'info' === $st_of( $good, 'wp_version' ) );
mb_test( 'Faz 10 sağlık: DISABLE_WP_CRON -> warn; DISALLOW_FILE_EDIT yok -> warn; düz bağlantı -> warn; https yok (üretim) -> warn',
	'warn' === $st_of( array_merge( $good, array( 'wp_cron_disabled' => true ) ), 'wp_cron' ) && 'warn' === $st_of( array_merge( $good, array( 'file_edit_disallowed' => false ) ), 'file_edit' ) && 'warn' === $st_of( array_merge( $good, array( 'permalink_structure' => '' ) ), 'permalinks' ) && 'warn' === $st_of( array_merge( $good, array( 'is_https' => false ) ), 'https' ) );
mb_test( 'Faz 10 sağlık: disk <200MB fail, <1GB warn, okunamadı info; uploads yazılamaz -> fail',
	'fail' === $st_of( array_merge( $good, array( 'disk_free_bytes' => 100 * 1048576 ) ), 'disk_free' ) && 'warn' === $st_of( array_merge( $good, array( 'disk_free_bytes' => 500 * 1048576 ) ), 'disk_free' ) && 'info' === $st_of( array_merge( $good, array( 'disk_free_bytes' => null ) ), 'disk_free' ) && 'fail' === $st_of( array_merge( $good, array( 'uploads_writable' => false ) ), 'uploads_writable' ) );
mb_test( 'Faz 10 sağlık: audit zinciri bozuksa fail; tablo yoksa warn; blog_public üretimle/stagingle tutarsızsa warn',
	'fail' === $st_of( array_merge( $good, array( 'audit' => array( 'rows' => 1, 'bytes' => 1, 'chain' => array( 'ok' => false, 'first_bad_id' => 5, 'reason' => 'row_modified' ) ) ) ), 'audit' ) && 'warn' === $st_of( array_merge( $good, array( 'audit' => null ) ), 'audit' )
	&& 'warn' === $st_of( array_merge( $good, array( 'blog_public' => false ) ), 'blog_public' ) && 'warn' === $st_of( array_merge( $good, array( 'environment_type' => 'staging', 'blog_public' => true ) ), 'blog_public' ) );
mb_test( 'Faz 10 sağlık: eksik girdi güvenli varsayılan alır (fatal yok)', is_array( $HC::evaluate( array() ) ) && count( $HC::evaluate( array() ) ) >= 15 );
$leak = json_encode( $HC::evaluate( $good ) );
mb_test( 'Faz 10 sağlık: çıktı parola/anahtar/sunucu yolu kalıbı içermez', 1 !== preg_match( '#(/var/www|/home/|[A-Z]:\\\\|password|secret|AUTH_KEY)#i', $leak ) );

/* ---------------- Uyumluluk ve uninstall ---------------- */
$CO = 'MaviBelge_Core_Compat';
mb_test( 'Faz 10 uyumluluk: PHP 7.2 / WP 6.8 uyarı verir; PHP 7.3 + WP 6.9 uyumlu; WP sürümü bilinmiyorsa yalnız PHP denetlenir',
	2 === count( $CO::unmet( '7.2.0', '6.8' ) ) && array() === $CO::unmet( '7.3.33', '6.9.9' ) && 1 === count( $CO::unmet( '7.3.0', '6.8.9' ) ) && array() === $CO::unmet( '8.3', '' ) );
$US = 'MaviBelge_Core_Uninstall_Scope';
mb_test( "Faz 10 uninstall: varsayılan (seçenek yok/false/boş/'true'/'yes'/0/int 1) HİÇBİR ŞEY silmez; yalnız boolean true veya WordPress'in sakladığı tam '1' siler",
	! $US::should_delete( false ) && ! $US::should_delete( 'true' ) && ! $US::should_delete( 'yes' ) && ! $US::should_delete( '' ) && ! $US::should_delete( '0' ) && ! $US::should_delete( 1 ) && ! $US::should_delete( null ) && ! $US::should_delete( array( 1 ) ) && $US::should_delete( true ) && $US::should_delete( '1' ) );
mb_test( 'Faz 10 uninstall: kapsam KAPALI allowlist — yalnız eklenti tabloları/seçenekleri; içerik, roller ve tarife dönemi YOK',
	array( 'wp_mb_audit_log', 'wp_mb_import_runs', 'wp_mb_import_run_items' ) === $US::tables( 'wp_' ) && ! in_array( 'mb_active_tariff_period', $US::OPTIONS, true ) && ! in_array( 'blogname', $US::OPTIONS, true ) && ! in_array( 'siteurl', $US::OPTIONS, true )
	&& array( 'mavibelge_core_daily_maintenance' ) === $US::CRON_HOOKS && count( $US::OPTIONS ) === count( array_unique( $US::OPTIONS ) ) );
$unins = file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );
mb_test( 'Faz 10 uninstall.php: yazı/terim/medya/kullanıcı silme çağrısı YOK; silme yalnız should_delete() kapısı içinde',
	false === strpos( $unins, 'wp_delete_post' ) && false === strpos( $unins, 'wp_delete_term' ) && false === strpos( $unins, 'wp_delete_attachment' ) && false === strpos( $unins, 'remove_role' ) && false === strpos( $unins, 'wp_delete_user' ) && strpos( $unins, 'should_delete' ) < strpos( $unins, 'DROP TABLE' ) );

/* ---------------- Kaynak taraması (12) ---------------- */
$scan = function ( $dir ) use ( &$scan ) {
	$files = array();
	foreach ( scandir( $dir ) as $entry ) {
		if ( '.' === $entry || '..' === $entry || 'tests' === $entry ) {
			continue;
		}
		$path = $dir . '/' . $entry;
		if ( is_dir( $path ) ) {
			$files = array_merge( $files, $scan( $path ) );
		} elseif ( '.php' === substr( $entry, -4 ) ) {
			$files[] = $path;
		}
	}
	return $files;
};
$roots   = array( dirname( __DIR__, 2 ), dirname( __DIR__, 4 ) . '/themes/mavibelge' );
$badCall = array();
$logBad  = array();
$nFiles  = 0;
foreach ( $roots as $root ) {
	if ( ! is_dir( $root ) ) {
		continue;
	}
	foreach ( $scan( $root ) as $file ) {
		$nFiles++;
		$src = file_get_contents( $file );
		if ( 1 === preg_match( '/\b(var_dump|print_r|phpinfo)\s*\(/', $src ) && ! preg_match( '#class-import-cli-command\.php$#', $file ) ) {
			$badCall[] = basename( $file );
		}
		if ( preg_match_all( '/\berror_log\s*\(([^\n]*)/', $src, $mm ) ) {
			foreach ( $mm[1] as $args ) {
				// yalnız sabit metin (tek tırnaklı dize) veya sabit metin + implode( ' | ', $problems ) (şema hata metni; kişisel veri yok)
				if ( 1 !== preg_match( "/^\s*'[^']*'\s*(\)|\.\s*implode\()/", $args ) ) {
					$logBad[] = basename( $file );
				}
			}
		}
	}
}
mb_test( 'Faz 10 kaynak taraması: eklenti+tema PHP dosyalarında var_dump/print_r/phpinfo çağrısı yok (CLI tablo yardımcısı print_rows hariç)', $nFiles > 50 && array() === $badCall );
mb_test( 'Faz 10 kaynak taraması: error_log() yalnız sabit metinle (değişken/kişisel veri birleştirme yok; şema hata metni implode istisnası)', array() === $logBad );
