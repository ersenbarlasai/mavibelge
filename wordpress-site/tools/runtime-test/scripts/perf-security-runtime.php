<?php
/**
 * TEST — YALNIZ silinebilir `mbfx_` fixture veritabanı (`wp --require=fixture-env.php --user=mbadmin eval-file`).
 * Faz 10 runtime doğrulaması: audit zinciri (v1->v2 yükseltme, kurcalama, saklama, kilit), bakım cron'u, önbellek
 * (nesil, kapalı kipler, fail-open), N+1 sorgu sayıları, oran sınırı (gerçek transient deposu), giriş hata mesajı ve
 * oran sınırı, sağlık ekranı render'ı, güvenli uninstall. E-posta göndermez; ana `wp_` DB'ye YAZMAZ.
 * Parola/anahtar YAZDIRMAZ.
 */

global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	exit( 1 );
}
$pass = 0;
$fail = 0;
$t    = function ( $label, $cond, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $cond ) {
		$pass++;
		echo "PASS  {$label}\n";
	} else {
		$fail++;
		echo "FAIL  {$label}" . ( '' !== $detail ? "  [{$detail}]" : '' ) . "\n";
	}
};
$warnings = array();
set_error_handler(
	function ( $no, $str, $file, $line ) use ( &$warnings ) {
		$warnings[] = $str . ' @' . basename( $file ) . ':' . $line;
		return true;
	}
);
$ids = json_decode( (string) getenv( 'MB_FX_IDS_JSON' ), true );

/* =============================== A) AUDIT =============================== */
$table = MaviBelge_Core_Audit_Log::table_name();
// Deterministik başlangıç (YALNIZ mbfx_ klonu): audit tablosu boşaltılır ve v1 biçimine döndürülür (HTTP testleri v2'ye yükseltmiş olabilir).
$wpdb->query( "TRUNCATE TABLE `{$table}`" );
foreach ( array( 'prev_hash', 'row_hash' ) as $col ) {
	if ( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $col ) ) ) {
		$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `{$col}`" );
	}
}
update_option( 'mavibelge_core_audit_table_version', '1' );
$t( 'A0 başlangıç: klonlanmış tablo v1 (row_hash sütunu YOK), sürüm seçeneği 1', ! MaviBelge_Core_Audit_Log::chain_columns_present() && '1' === get_option( 'mavibelge_core_audit_table_version' ) );
MaviBelge_Core_Audit_Log::record( 'content_meta_changed', 'mb_test', 1, array( 'sira' => 'eski-v1' ) );
$t( 'A1 v1 tabloya yazım eski biçimde çalışır (fatal yok)', 1 <= (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE context LIKE '%eski-v1%'" ) );
MaviBelge_Core_Audit_Log::install();
$t( 'A2 install(): sürüm 2 seçeneği yazıldı ve zincir sütunları var (dbDelta yükseltmesi)', '2' === get_option( 'mavibelge_core_audit_table_version' ) && MaviBelge_Core_Audit_Log::chain_columns_present() );
$t( 'A3 eski v1 satırı row_hash boş kaldı (legacy)', 1 <= (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE row_hash = ''" ) );
MaviBelge_Core_Audit_Log::install();
$t( 'A4 install() idempotent (ikinci çağrı hata/uyarı vermez)', '2' === get_option( 'mavibelge_core_audit_table_version' ) );
$w1 = MaviBelge_Core_Audit_Log::record( 'content_meta_changed', 'mb_test', 11, array( 'a' => 'b|c' ) );
$w2 = MaviBelge_Core_Audit_Log::record( 'price_option_changed', 'mb_test', 12, array( 'tr' => 'ğüşiöç' ) );
$tw2 = strtotime( $wpdb->get_var( "SELECT created_at_gmt FROM `{$table}` ORDER BY id DESC LIMIT 1" ) . ' UTC' );
sleep( 2 ); // sonraki satırlar farklı saniyede: saklama eşiği aralarına konulacak
$w3 = MaviBelge_Core_Audit_Log::record( 'form_rejected', 'mb_form', 0, array( 'form' => 'iletisim', 'result' => 'honeypot' ) );
$w4 = MaviBelge_Core_Audit_Log::record( 'content_meta_changed', 'mb_test', 13, array() );
$w5 = MaviBelge_Core_Audit_Log::record( 'form_submitted', 'mb_form', 0, array( 'form' => 'iletisim', 'result' => 'ok' ) );
$t( 'A5 beş zincirli kayıt yazıldı', $w1 && $w2 && $w3 && $w4 && $w5 );
$rows = $wpdb->get_results( "SELECT id, event_type, prev_hash, row_hash FROM `{$table}` WHERE row_hash <> '' ORDER BY id ASC", ARRAY_A );
$core = array_values( array_filter( $rows, function ( $r ) {
	return 0 !== strpos( $r['event_type'], 'form_' );
} ) );
$t( 'A6 core zinciri: ilk satırın prev_hash\'i boş (zincir başı), sonrakiler öncekinin row_hash\'ine bağlı', '' === $core[0]['prev_hash'] && $core[1]['prev_hash'] === $core[0]['row_hash'] && $core[2]['prev_hash'] === $core[1]['row_hash'] );
$v = MaviBelge_Core_Audit_Log::verify_chain( 1000 );
$t( 'A7 verify_chain: sağlam zincir ok, 5 satır doğrulandı, eski sürüm satırı sayıldı', true === $v['ok'] && 5 === $v['checked'] && $v['legacy'] >= 1, wp_json_encode( $v ) );
$midId = (int) $core[1]['id'];
$orig  = $wpdb->get_var( $wpdb->prepare( "SELECT context FROM `{$table}` WHERE id = %d", $midId ) );
$wpdb->update( $table, array( 'context' => '{"tr":"kurcalandi"}' ), array( 'id' => $midId ) );
$v = MaviBelge_Core_Audit_Log::verify_chain( 1000 );
$t( 'A8 kurcalanmış satır (bağlam değiştirildi) verify_chain ile YAKALANDI, doğru id', false === $v['ok'] && 'row_modified' === $v['reason'] && $midId === $v['first_bad_id'], wp_json_encode( $v ) );
$wpdb->update( $table, array( 'context' => $orig ), array( 'id' => $midId ) );
$t( 'A9 kurcalama geri alınınca zincir yeniden sağlam', true === MaviBelge_Core_Audit_Log::verify_chain( 1000 )['ok'] );
$saved = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $midId ), ARRAY_A );
$wpdb->delete( $table, array( 'id' => $midId ) );
$v = MaviBelge_Core_Audit_Log::verify_chain( 1000 );
$t( 'A10 aradan SİLİNEN satır yakalandı (chain_break)', false === $v['ok'] && 'chain_break' === $v['reason'], wp_json_encode( $v ) );
$wpdb->insert( $table, $saved );
$t( 'A11 silinen satır geri konunca zincir sağlam', true === MaviBelge_Core_Audit_Log::verify_chain( 1000 )['ok'] );

$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
$del0   = MaviBelge_Core_Audit_Log::prune( $tw2 + 50 * 86400 );
$t( 'A12 saklama: 50 gün sonrasında (form 90, core 365 gün) hiçbir satır silinmez', is_array( $del0 ) && 0 === $del0['form'] && 0 === $del0['core'] && (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) === $before, wp_json_encode( $del0 ) );
$del = MaviBelge_Core_Audit_Log::prune( $tw2 + 365 * 86400 + 1 ); // eşik w2 ile w4 arasına düşer
$t( 'A13 saklama: 365 gün + 1 sn sonra en eski core öneki (v1 satırı + w1 + w2) ve TÜM form satırları (90 gün) silinir; sonraki core satırı KALIR', is_array( $del ) && $del['core'] >= 2 && 2 === $del['form'] && 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE object_id = 13" ), wp_json_encode( $del ) );
$v = MaviBelge_Core_Audit_Log::verify_chain( 1000 );
$t( 'A14 saklama silmesi sonrası ilk kalan satırın prev_hash-i ÇAPA: zincir hâlâ sağlam', true === $v['ok'] && 1 === $v['checked'], wp_json_encode( $v ) );
MaviBelge_Core_Audit_Log::record( 'content_meta_changed', 'mb_test', 21, array() );
$t( 'A15 saklama silmesinden sonra yeni kayıt zincire bağlanır ve zincir sağlam', true === ( $v15 = MaviBelge_Core_Audit_Log::verify_chain( 1000 ) )['ok'], wp_json_encode( $v15 ) );

// Kilit: başka bir bağlantı kilidi tutarken kayıt YAZILMAZ (kilitsiz yazım yok).
$lockName = 'mbc_audit_' . substr( md5( $wpdb->prefix ), 0, 16 );
$other    = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
$other->query( "SELECT GET_LOCK('" . $lockName . "', 0)" );
$cnt0    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
$t0      = microtime( true );
$locked  = MaviBelge_Core_Audit_Log::record( 'content_meta_changed', 'mb_test', 22, array() );
$elapsed = microtime( true ) - $t0;
$cnt1    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
$other->query( "SELECT RELEASE_LOCK('" . $lockName . "')" );
$other->close();
$t( 'A16 kilit başka oturumda tutuluyken record() false döner, satır YAZILMAZ (~3 sn bekler)', false === $locked && $cnt0 === $cnt1 && $elapsed >= 2.5, 'sure=' . round( $elapsed, 1 ) );
$t( 'A17 kilit bırakıldıktan sonra yazım yeniden çalışır', true === MaviBelge_Core_Audit_Log::record( 'content_meta_changed', 'mb_test', 23, array() ) );

// Bakım cron'u.
MaviBelge_Core_Maintenance::unschedule();
MaviBelge_Core_Maintenance::schedule();
MaviBelge_Core_Maintenance::schedule();
$hooks = 0;
foreach ( (array) _get_cron_array() as $ts => $events ) {
	if ( isset( $events[ MaviBelge_Core_Maintenance::HOOK ] ) ) {
		$hooks += count( $events[ MaviBelge_Core_Maintenance::HOOK ] );
	}
}
$t( 'A18 cron zamanlaması idempotent: iki schedule() sonrası TEK olay', 1 === $hooks );
MaviBelge_Core_Maintenance::unschedule();
$t( 'A19 unschedule(): kanca kaldırıldı', false === wp_next_scheduled( MaviBelge_Core_Maintenance::HOOK ) );
MaviBelge_Core_Maintenance::schedule();
$t( 'A20 yeniden zamanlama çalışır', false !== wp_next_scheduled( MaviBelge_Core_Maintenance::HOOK ) );

// Geçici form dosyası temizliği: upload dizini /tmp altına yönlendirilir (gerçek uploads'a dokunulmaz).
$tmpBase = '/tmp/mbfx-uploads-perf';
@mkdir( $tmpBase . '/' . MaviBelge_Core_Maintenance::TMP_DIR, 0777, true );
add_filter(
	'upload_dir',
	function ( $d ) use ( $tmpBase ) {
		$d['basedir'] = $tmpBase;
		$d['error']   = false;
		return $d;
	}
);
$dir   = $tmpBase . '/' . MaviBelge_Core_Maintenance::TMP_DIR;
$old   = str_repeat( 'a', 32 ) . '.pdf';
$fresh = str_repeat( 'b', 32 ) . '.pdf';
file_put_contents( $dir . '/' . $old, 'x' );
file_put_contents( $dir . '/' . $fresh, 'x' );
file_put_contents( $dir . '/.htaccess', 'x' );
file_put_contents( $dir . '/index.php', 'x' );
file_put_contents( $dir . '/kullanici-dosyasi.pdf', 'x' );
touch( $dir . '/' . $old, time() - 7200 );
touch( $dir . '/.htaccess', time() - 7200 );
touch( $dir . '/kullanici-dosyasi.pdf', time() - 7200 );
$n = MaviBelge_Core_Maintenance::cleanup_forms_tmp();
$t( 'A21 geçici dosya temizliği: yalnız 1 saatten eski rastgele adlı dosya silindi; taze dosya, .htaccess, index.php, başka ad KALDI', 1 === $n && ! file_exists( $dir . '/' . $old ) && file_exists( $dir . '/' . $fresh ) && file_exists( $dir . '/.htaccess' ) && file_exists( $dir . '/index.php' ) && file_exists( $dir . '/kullanici-dosyasi.pdf' ) );
foreach ( glob( $dir . '/{,.}*', GLOB_BRACE ) as $f ) {
	if ( is_file( $f ) ) {
		unlink( $f );
	}
}
@rmdir( $dir );
@rmdir( $tmpBase );
remove_all_filters( 'upload_dir' );

/* =============================== B) ÖNBELLEK =============================== */
$cache_on = function ( $default, $env ) {
	return ! $env['disabled_const'] && ! $env['can_edit']; // WP_DEBUG'u yok say (test ortamı debug açık)
};
wp_set_current_user( 0 );
$t( 'B0 varsayılan (filtresiz): WP_DEBUG açık test ortamında önbellek KAPALI', ! MaviBelge_Core_Cache::enabled() );
add_filter( 'mavibelge_core_cache_enabled', $cache_on, 10, 2 );
$t( 'B1 anonim ziyaretçide (süzgeçle debug yok sayılınca) önbellek AÇIK', MaviBelge_Core_Cache::enabled() );
wp_set_current_user( get_user_by( 'login', 'mbadmin' )->ID );
$t( 'B2 düzenleme yetkili oturumda önbellek KAPALI (önizleme/yönetim taze)', ! MaviBelge_Core_Cache::enabled() );
wp_set_current_user( 0 );

$q = function ( $fn ) use ( $wpdb ) {
	$n0  = $wpdb->num_queries;
	$res = $fn();
	return array( $wpdb->num_queries - $n0, $res );
};
$flush = function () {
	wp_cache_flush();
};
$countTransients = function () use ( $wpdb ) {
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_mbc\\_%'" );
};
$flush();
list( $q1, $r1 ) = $q( function () {
	return MaviBelge_Core_Content_Service::get_news( array() );
} );
$flush();
list( $q2, $r2 ) = $q( function () {
	return MaviBelge_Core_Content_Service::get_news( array() );
} );
$t( 'B3 get_news: ikinci çağrı sorgu sayısını DÜŞÜRÜR ve sonuç birebir aynı', $q2 < $q1 && $q2 <= 4 && $r1 === $r2 && 12 === count( $r1['items'] ), "ilk={$q1} ikinci={$q2}" );
$t( 'B4 ön bellekli sonuç saf DTO (nesne içermez)', MaviBelge_Core_Cache::is_pure_dto( $r2 ) );
$typed = MaviBelge_Core_Content_Service::get_news( array( 'mb_type' => 'duyuru' ) );
$t( 'B5 filtre AYRIMI: tür filtreli sonuç genel sonuçtan farklı (yanlış önbellek yok)', $typed['total'] !== $r1['total'] && $typed['args']['type'] === 'duyuru', $typed['total'] . ' vs ' . $r1['total'] );
$p2 = MaviBelge_Core_Content_Service::get_news( array( 'mb_page' => '2' ) );
$t( 'B6 sayfa AYRIMI: 2. sayfa 1. sayfadan farklı öğeler', 2 === $p2['page'] && $p2['items'][0]['id'] !== $r1['items'][0]['id'] );
$cBefore = $countTransients();
MaviBelge_Core_Content_Service::get_news( array( 'mb_page' => '400' ) );
MaviBelge_Core_Content_Service::get_news( array( 'mb_page' => '400' ) );
$t( 'B7 var olmayan (sıkıştırılan) sayfa önbelleğe YAZILMAZ (anahtar uzayı sınırlı)', $countTransients() === $cBefore );

// Nesil / geçersiz kılma.
$newsId = $r1['items'][0]['id'];
$genA   = MaviBelge_Core_Cache::generation();
$oldTitle = get_the_title( $newsId );
wp_update_post( array( 'ID' => $newsId, 'post_title' => 'TEST yeni başlık faz10' ) );
$genB = MaviBelge_Core_Cache::generation();
$flush();
$r3   = MaviBelge_Core_Content_Service::get_news( array() );
$t( 'B8 yazı kaydı nesli artırır ve ESKİ veri gelmez (yeni başlık görünür)', (int) $genB > (int) $genA && 'TEST yeni başlık faz10' === $r3['items'][0]['title'], "gen {$genA}->{$genB}" );
$r3b = MaviBelge_Core_Content_Service::get_news( array() );
$genC = MaviBelge_Core_Cache::generation();
update_post_meta( $newsId, '_mb_approval_status', 'in_review' );
$t( 'B9 _mb_ meta değişimi nesli artırır', (int) MaviBelge_Core_Cache::generation() > (int) $genC );
$flush();
$r4  = MaviBelge_Core_Content_Service::get_news( array() );
$ids4 = wp_list_pluck( $r4['items'], 'id' );
$t( 'B10 onaydan çıkarılan haber ÖNBELLEKTEN de kalkar (eski liste gelmez)', ! in_array( $newsId, $ids4, true ) );
$genD = MaviBelge_Core_Cache::generation();
update_post_meta( $newsId, 'baska_meta', 'x' );
$t( 'B11 _mb_ olmayan meta nesli ARTIRMAZ', MaviBelge_Core_Cache::generation() === $genD );
update_option( 'mavibelge_core_redirects', array() );
update_option( MaviBelge_Core_Forms_Config::OPTION_KEY, MaviBelge_Core_Forms_Config::sanitize( array() ) );
$t( 'B12 form/yönlendirme seçenekleri nesli ARTIRMAZ', MaviBelge_Core_Cache::generation() === $genD );
update_option( 'mb_active_tariff_period', get_option( 'mb_active_tariff_period', '' ) . 'x' );
$t( 'B13 aktif tarife dönemi değişimi nesli artırır', (int) MaviBelge_Core_Cache::generation() > (int) $genD );
$b0 = MaviBelge_Core_Cache::bump_count();
$g0 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", MaviBelge_Core_Cache::GEN_OPTION ) );
MaviBelge_Core_Cache::bump();
$g1 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", MaviBelge_Core_Cache::GEN_OPTION ) );
$t( 'B14 bump(): tek atomik artış (+1) veritabanında', $g1 === $g0 + 1 && MaviBelge_Core_Cache::bump_count() === $b0 + 1 );

// Kapalı kip: doğru sonuç, sorgu sayısı düşmez.
remove_filter( 'mavibelge_core_cache_enabled', $cache_on, 10 );
add_filter( 'mavibelge_core_cache_enabled', '__return_false' );
$flush();
list( $o1, $x1 ) = $q( function () {
	return MaviBelge_Core_Content_Service::get_news( array() );
} );
$flush();
list( $o2, $x2 ) = $q( function () {
	return MaviBelge_Core_Content_Service::get_news( array() );
} );
$t( 'B15 önbellek KAPALIYKEN doğru sonuç ve ikinci çağrı da sorgu çalıştırır (önbellekten okumaz)', $x1 === $x2 && $o2 >= $o1 - 1 && $o2 > 4 && ! in_array( $newsId, wp_list_pluck( $x2['items'], 'id' ), true ), "{$o1}/{$o2}" );
remove_filter( 'mavibelge_core_cache_enabled', '__return_false' );
add_filter( 'mavibelge_core_cache_enabled', $cache_on, 10, 2 );

// Fail-open: transient okunamaz / yazılamaz.
$key = MaviBelge_Core_Cache::key( MaviBelge_Core_Cache::generation(), 'news', get_locale(), 1, array( 'type' => '' ) );
add_filter(
	'pre_transient_' . $key,
	function () {
		return 'bozuk-deger';
	}
);
$flush();
$fo = MaviBelge_Core_Content_Service::get_news( array() );
$t( 'B16 FAIL-OPEN: transient bozuk/okunamazsa sorgu çalışır ve DOĞRU sonuç döner', 12 === count( $fo['items'] ) && $fo['total'] === $x1['total'] );
remove_all_filters( 'pre_transient_' . $key );
delete_transient( $key );
$blockKey = function ( $sql ) use ( $key ) {
	return ( 0 === stripos( ltrim( $sql ), 'INSERT' ) || 0 === stripos( ltrim( $sql ), 'UPDATE' ) ) && false !== strpos( $sql, $key ) ? 'SELECT 1 FROM DUAL WHERE 0' : $sql;
};
add_filter( 'query', $blockKey );
$fo2 = MaviBelge_Core_Content_Service::get_news( array() );
$fo3 = MaviBelge_Core_Content_Service::get_news( array() );
$t( 'B17 FAIL-OPEN: transient YAZILAMAZSA sonuç yine doğru (yazma hatası yok sayılır)', $fo2 === $fo3 && 12 === count( $fo3['items'] ) );
remove_filter( 'query', $blockKey );

// Diğer servisler.
$flush();
list( $c1, $qa ) = $q( function () {
	return MaviBelge_Core_Catalog_Service::get_qualification_results( array() );
} );
$flush();
list( $c2, $qb ) = $q( function () {
	return MaviBelge_Core_Catalog_Service::get_qualification_results( array() );
} );
$t( 'B18 katalog get_qualification_results: ikinci çağrı sorgu sayısını düşürür, sonuç aynı', $qa === $qb && ( $c2 < $c1 || 0 === (int) $qa['total'] ), "{$c1}/{$c2}" );
$cBefore = $countTransients();
MaviBelge_Core_Catalog_Service::get_qualification_results( array( 'mb_q' => 'zz-yok' ) );
$t( 'B19 serbest metin (mb_q) araması ÖNBELLEĞE YAZILMAZ (anahtar uzayı sınırlı)', $countTransients() === $cBefore );
$flush();
list( $d1 ) = $q( function () {
	return MaviBelge_Core_Content_Service::get_documents( array() );
} );
$flush();
list( $d2, $dd ) = $q( function () {
	return MaviBelge_Core_Content_Service::get_documents( array() );
} );
$t( 'B20 get_documents: ikinci çağrı sorgu düşürür', $d2 < $d1, "{$d1}/{$d2}" );
$flush();
list( $e1, $ee ) = $q( function () {
	return MaviBelge_Core_Content_Service::get_references();
} );
$flush();
list( $e2 ) = $q( function () {
	return MaviBelge_Core_Content_Service::get_references();
} );
$t( 'B21 get_references ve get_locations/get_faqs çalışır; referans ikinci çağrıda sorgu düşer', $e2 < $e1 && is_array( MaviBelge_Core_Content_Service::get_locations() ) && is_array( MaviBelge_Core_Content_Service::get_faqs( array() )['items'] ), "{$e1}/{$e2}" );

/* =============================== C) N+1 =============================== */
remove_filter( 'mavibelge_core_cache_enabled', $cache_on, 10 );
add_filter( 'mavibelge_core_cache_enabled', '__return_false' ); // ham sorgu sayısı
$measure = function ( $fn ) use ( $wpdb, $flush ) {
	$flush();
	$fn(); // seçenek/varsayılan yükleri eşitle
	$flush();
	$n0 = $wpdb->num_queries;
	$fn();
	return $wpdb->num_queries - $n0;
};
// Onaydan çıkan haberi geri getir: 13 onaylı haber.
update_post_meta( $newsId, '_mb_approval_status', 'approved' );
$n4  = $measure( function () {
	return MaviBelge_Core_Content_Service::get_latest_news( 4 );
} );
$n12 = $measure( function () {
	return MaviBelge_Core_Content_Service::get_news( array() );
} );
$t( 'C1 haber listesi: 12 kart <= 25 sorgu ve 4 kart ile 12 kart arası fark <= 3 (doğrusal büyüme yok)', $n12 <= 25 && ( $n12 - $n4 ) <= 3 && $n12 >= 1, "4 kart={$n4} 12 kart={$n12}" );
$doc = $measure( function () {
	return MaviBelge_Core_Content_Service::get_documents( array() );
} );
$t( 'C2 doküman listesi (ek dosya toplu ısıtma): <= 25 sorgu', $doc <= 25, (string) $doc );
$ref = $measure( function () {
	return MaviBelge_Core_Content_Service::get_references();
} );
$t( 'C3 referans listesi (logo toplu ısıtma): <= 25 sorgu', $ref <= 25, (string) $ref );
$ql = $measure( function () {
	return MaviBelge_Core_Catalog_Service::get_qualification_results( array() );
} );
$fe = $measure( function () {
	return MaviBelge_Core_Catalog_Service::get_active_fee_results( array() );
} );
$t( 'C4 katalog: yeterlilik listesi ve ücret sonuçları <= 25 sorgu', $ql <= 25 && $fe <= 25, "yeterlilik={$ql} ucret={$fe}" );
echo "INFO  sorgu sayıları: 4 kart={$n4} 12 kart={$n12} doküman={$doc} referans={$ref} yeterlilik={$ql} ücret={$fe}\n";
remove_filter( 'mavibelge_core_cache_enabled', '__return_false' );

/* =============================== D) ORAN SINIRI + GİRİŞ =============================== */
$store = new MaviBelge_Core_Rate_Limit_Transient_Store();
$lim   = array( 'per_client' => 2, 'window' => 60, 'global' => 9, 'global_window' => 60 );
$r     = array();
for ( $i = 0; $i < 3; $i++ ) {
	$r[] = MaviBelge_Core_Rate_Limit::check_and_hit( $store, 'mbf_rc_perftest', 'mbf_rg_perftest', $lim );
}
$t( 'D1 gerçek transient deposu: 2 ok, 3. deneme client_limited', array( 'ok', 'ok', 'client_limited' ) === $r, implode( ',', $r ) );
$blockWrite = function ( $sql ) {
	return ( 0 === stripos( ltrim( $sql ), 'INSERT' ) || 0 === stripos( ltrim( $sql ), 'UPDATE' ) ) && false !== strpos( $sql, 'mbf_rc_perftest2' ) ? 'SELECT 1 FROM DUAL WHERE 0' : $sql;
};
add_filter( 'query', $blockWrite ); // sayaç satırının veritabanına yazılmasını engeller (gerçek yazma hatası benzetimi)
$t( 'D2 FAIL-CLOSED (gerçek depo): sayaç yazılamazsa unavailable', 'unavailable' === MaviBelge_Core_Rate_Limit::check_and_hit( $store, 'mbf_rc_perftest2', 'mbf_rg_perftest2', $lim ) );
remove_filter( 'query', $blockWrite );
delete_transient( 'mbf_rc_perftest' );
delete_transient( 'mbf_rg_perftest' );

$loginKeyGuess = MaviBelge_Core_Hardening::login_client_key( MaviBelge_Core_Rate_Limit::client_id( 'unknown-x', 'x' ) );
$e1 = wp_authenticate( 'yok-boyle-bir-kullanici', 'yanlis-parola-1' );
$e2 = wp_authenticate( 'mbadmin', 'yanlis-parola-2' );
$t( 'D3 giriş hatası GENEL: var olmayan kullanıcı ve var olan kullanıcı + yanlış parola AYNI kod ve mesaj', is_wp_error( $e1 ) && is_wp_error( $e2 ) && $e1->get_error_code() === $e2->get_error_code() && $e1->get_error_message() === $e2->get_error_message() && MaviBelge_Core_Hardening::LOGIN_MESSAGE === $e1->get_error_message() && false === stripos( $e1->get_error_message(), 'şifre' ) );
for ( $i = 0; $i < 9; $i++ ) {
	wp_authenticate( 'yok-boyle-bir-kullanici', 'yanlis-' . $i );
}
$e11 = wp_authenticate( 'yok-boyle-bir-kullanici', 'yanlis-son' );
$t( 'D4 giriş oran sınırı: 10 başarısız denemeden sonra istemci kilitlenir (sınır mesajı)', is_wp_error( $e11 ) && MaviBelge_Core_Hardening::LOGIN_LIMITED_CODE === $e11->get_error_code(), is_wp_error( $e11 ) ? $e11->get_error_code() : 'hata değil' );
$e12 = wp_authenticate( 'mbadmin', 'baska-yanlis' );
$t( 'D5 kilit istemci düzeyinde: var olan kullanıcı için de sınır mesajı (hesap ayrımı sızmaz)', is_wp_error( $e12 ) && MaviBelge_Core_Hardening::LOGIN_LIMITED_CODE === $e12->get_error_code() );
$loginKey = MaviBelge_Core_Hardening::login_client_key( MaviBelge_Core_Rate_Limit::client_id( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '', wp_salt( 'auth' ) . '|mavibelge-login' ) );
$t( 'D6 giriş sayaç anahtarı HAM IP içermez (mbl_rc_ + hex) ve sayaç gerçekten yazıldı', 1 === preg_match( '/^mbl_rc_[0-9a-f]{32}$/', $loginKey ) && false !== get_transient( $loginKey ) );
add_filter(
	'pre_transient_' . (string) $loginKey,
	function () {
		return 'bozuk';
	}
);
$e13 = wp_authenticate( 'mbadmin', 'x' );
$t( 'D7 giriş sayacı OKUNAMAZSA FAIL-CLOSED: genel hata (giriş izni yok)', is_wp_error( $e13 ) && MaviBelge_Core_Hardening::LOGIN_UNAVAILABLE_CODE === $e13->get_error_code() && MaviBelge_Core_Hardening::LOGIN_MESSAGE === $e13->get_error_message() );
remove_all_filters( 'pre_transient_' . (string) $loginKey );
delete_transient( (string) $loginKey );

/* =============================== E) SAĞLIK EKRANI =============================== */
require_once MAVIBELGE_CORE_PATH . 'admin/class-health-page.php';
wp_set_current_user( get_user_by( 'login', 'mbadmin' )->ID );
$env = MaviBelge_Core_Health_Page::collect_env();
$chk = MaviBelge_Core_Health_Checks::evaluate( $env );
ob_start();
MaviBelge_Core_Health_Page::render();
$html = ob_get_clean();
$ids2 = wp_list_pluck( $chk, 'id' );
$t( 'E1 sağlık ekranı render edilir: başlık, tablo, tüm kontrol satırları', false !== strpos( $html, 'Mavi Belge Sağlık' ) && false !== strpos( $html, 'mb-health-table' ) && substr_count( $html, 'data-check=' ) === count( $chk ) && count( $chk ) >= 20 );
$byId = array();
foreach ( $chk as $c ) {
	$byId[ $c['id'] ] = $c;
}
$t( 'E2 gerçek ortam: PHP 7.3.33 -> warn (EOL, gizleme yok); WordPress 6.9.9 -> warn (koşullu/legacy); eklenti tabloları InnoDB -> ok', 'warn' === $byId['php_version']['status'] && 'warn' === $byId['wp_version']['status'] && 'ok' === $byId['db_engine']['status'], $byId['db_engine']['detail'] );
$t( 'E3 gerçek ortam: audit zinciri ok, önbellek durumu bildirilir, DISABLE_WP_CRON uyarısı', 'ok' === $byId['audit']['status'] && isset( $byId['cache'] ) && 'warn' === $byId['wp_cron']['status'], $byId['audit']['status'] . ' ' . $byId['audit']['detail'] . ' cron=' . $byId['wp_cron']['status'] );
$t( 'E4 ekran sızıntısız: parola/anahtar/sunucu yolu/DB bilgisi yok', 1 !== preg_match( '#(/var/www|AUTH_KEY|phpinfo|wp-config)#', $html ) && false === strpos( $html, DB_PASSWORD ) );
$t( 'E5 ekran salt okunur: <form>/<input>/POST düğmesi yok', false === stripos( $html, '<form' ) && false === stripos( $html, '<input' ) && false === stripos( $html, '<button' ) );
$t( 'E6 yetki: manage_options', 'manage_options' === MaviBelge_Core_Health_Page::CAPABILITY );

/* =============================== F) UNINSTALL =============================== */
$postsBefore = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
$termsBefore = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" );
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'mavibelge-core/mavibelge-core.php' );
}
delete_option( MaviBelge_Core_Uninstall_Scope::OPT_IN_OPTION );
include MAVIBELGE_CORE_PATH . 'uninstall.php';
$tablesLeft = 0;
foreach ( MaviBelge_Core_Uninstall_Scope::tables( $wpdb->prefix ) as $tbl ) {
	$tablesLeft += ( $tbl === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tbl ) ) ) ) ? 1 : 0;
}
$t( 'F1 uninstall: seçenek YOKKEN hiçbir tablo/seçenek/cron silinmez', $tablesLeft >= 1 && false !== get_option( 'mavibelge_core_audit_table_version' ) && false !== wp_next_scheduled( MaviBelge_Core_Maintenance::HOOK ) );
update_option( MaviBelge_Core_Uninstall_Scope::OPT_IN_OPTION, 'true' );
include MAVIBELGE_CORE_PATH . 'uninstall.php';
$t( 'F2 uninstall: seçenek "true" DİZGİSİ (açık onay değil) iken hâlâ hiçbir şey silinmez', false !== get_option( 'mavibelge_core_audit_table_version' ) );
update_option( MaviBelge_Core_Uninstall_Scope::OPT_IN_OPTION, true );
$tariff = get_option( 'mb_active_tariff_period', null );
include MAVIBELGE_CORE_PATH . 'uninstall.php';
$tablesLeft = 0;
foreach ( MaviBelge_Core_Uninstall_Scope::tables( $wpdb->prefix ) as $tbl ) {
	$tablesLeft += ( $tbl === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tbl ) ) ) ) ? 1 : 0;
}
wp_cache_flush();
$t( 'F3 uninstall (açık onay true): eklenti tabloları, seçenekleri ve cron kancası silindi', 0 === $tablesLeft && false === get_option( 'mavibelge_core_audit_table_version' ) && false === get_option( MaviBelge_Core_Uninstall_Scope::OPT_IN_OPTION ) && false === wp_next_scheduled( MaviBelge_Core_Maintenance::HOOK ), 'tablo=' . $tablesLeft . ' ver=' . var_export( get_option( 'mavibelge_core_audit_table_version' ), true ) . ' opt=' . var_export( get_option( MaviBelge_Core_Uninstall_Scope::OPT_IN_OPTION ), true ) . ' cron=' . var_export( wp_next_scheduled( MaviBelge_Core_Maintenance::HOOK ), true ) );
$t( 'F4 uninstall: yazılar, terimler ve kurum tarife dönemi seçeneği KORUNDU (içerik silinmez)', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ) === $postsBefore && (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" ) === $termsBefore && get_option( 'mb_active_tariff_period', null ) === $tariff );

restore_error_handler();
$t( 'Z0 PHP uyarısı/bildirimi/kullanımdan kaldırma YOK', empty( $warnings ), implode( ' ; ', array_slice( $warnings, 0, 3 ) ) );
echo "\nFaz 10 runtime: {$pass} geçti, {$fail} başarısız.\n";
exit( $fail > 0 ? 1 : 0 );
