<?php
/**
 * GERÇEK WordPress 6.9.9 + PHP 7.3.33 (yerel Docker, silinebilir `mbfx_` klonu) üzerinde loader testi.
 *
 *   wp --require=/opt/mb-runtime/fixture-env.php eval-file /tmp/mbrb-runtime-wp.php <fixture|cli>
 *
 * fixture : yalnız `mbfx_` önekinde; dört etkin hedefin TEST sayfa/haber kayıtlarını (yoksa) oluşturur, kalıcı
 *           bağlantıyı canlıdaki gibi `/%postname%/` yapar, yönlendirme deposunu ve loader durum kaydını temizler.
 * cli     : loader'ı gerçek seçenek/nonce/yetki/Core sınıflarıyla sınar; sonunda depoyu BOŞ bırakır (HTTP testi
 *           gerçek admin-post akışıyla yeniden içe aktarır).
 * Test verisi sentetiktir; e-posta yok; ana `wp_` tabloları bu süreçte kullanılmaz.
 */

global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : '';

if ( 'fixture' === $mode ) {
	$mk = function ( $type, $slug, $meta ) use ( $wpdb ) {
		$found = get_posts( array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'suppress_filters' => true ) );
		if ( ! empty( $found ) ) {
			$id = (int) $found[0];
		} else {
			$id = wp_insert_post( wp_slash( array( 'post_type' => $type, 'post_title' => 'TEST ' . $slug, 'post_name' => $slug, 'post_content' => 'TEST içerik', 'post_status' => 'draft' ) ), true );
			if ( is_wp_error( $id ) ) {
				fwrite( STDERR, 'oluşturulamadı: ' . $slug . "\n" );
				exit( 1 );
			}
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		// Yayın kapısı yalnız yönetim kaydetme yolunu korur; fixture yayında kaydı doğrudan kurar (content-fixtures.php ile aynı).
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish', 'post_name' => $slug ), array( 'ID' => $id ) );
		clean_post_cache( $id );
		return get_post_status( $id );
	};
	$out = array(
		'kvkk'                              => $mk( 'page', 'kvkk', array() ),
		'gizlilik-politikasi'               => $mk( 'page', 'gizlilik-politikasi', array() ),
		'6-dilde-myk-belgesi-gecerliligi'   => $mk( 'mb_haber', '6-dilde-myk-belgesi-gecerliligi', array( '_mb_approval_status' => 'approved' ) ),
		'mobilya-sektoru-belge-zorunlulugu' => $mk( 'mb_haber', 'mobilya-sektoru-belge-zorunlulugu', array( '_mb_approval_status' => 'approved' ) ),
	);
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blog_public', '1' );
	flush_rewrite_rules( false );
	delete_option( 'mavibelge_core_redirects' );
	delete_option( 'mavibelge_redirect_bootstrap_state' );
	$out['permalink'] = get_option( 'permalink_structure' );
	echo wp_json_encode( $out ), "\n";
	return;
}

if ( 'cli' !== $mode ) {
	echo "Kullanım: fixture|cli\n";
	exit( 1 );
}

$failures = 0;
$total    = 0;
$t        = function ( $desc, $cond ) use ( &$failures, &$total ) {
	$total++;
	if ( $cond ) {
		echo "PASS  {$desc}\n";
	} else {
		$failures++;
		echo "FAIL  {$desc}\n";
	}
};
$B     = 'MaviBelge_Redirect_Bootstrap';
$store = 'mavibelge_core_redirects';

$t( 'Gerçek WP: PHP 7.3.33 + WordPress 6.9.9', 0 === strpos( PHP_VERSION, '7.3.33' ) && '6.9.9' === get_bloginfo( 'version' ) );
$t( 'Gerçek WP: loader mu-plugin olarak yüklendi; Mavi Belge Core yönlendirme sınıfları mevcut', class_exists( $B, false ) && class_exists( 'MaviBelge_Core_Redirects_Service', false ) );
$t( 'Gerçek WP: yönetim dışı (WP-CLI) bağlamda loader kanca kaydetmedi', false === has_action( 'admin_menu', array( $B, 'register_page' ) ) && false === has_action( 'admin_post_' . $B::ACTION_IMPORT, array( $B, 'handle_import' ) ) );
$t( 'Başlangıç: depo ve durum kaydı yok (loader kendiliğinden çalışmadı)', 'YOK' === get_option( $store, 'YOK' ) && 'YOK' === get_option( $B::STATE_OPTION, 'YOK' ) );

$admin = get_current_user_id();
$sub   = get_user_by( 'login', 'mbsubscriber' );
if ( $sub ) {
	wp_set_current_user( $sub->ID );
	$r = $B::import( array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_IMPORT ) ) );
	$t( 'Abone kullanıcı (kendi geçerli nonce\'uyla) reddedilir: forbidden, depo yok', 'forbidden' === $r['code'] && 'YOK' === get_option( $store, 'YOK' ) );
	wp_set_current_user( $admin );
}
$r = $B::import( array( 'mb_rb_nonce' => 'gecersiz' ) );
$t( 'Yönetici + geçersiz nonce: nonce_invalid, depo yok', 'nonce_invalid' === $r['code'] && 'YOK' === get_option( $store, 'YOK' ) );
$r = $B::import( array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_ROLLBACK ) ) );
$t( 'Yönetici + rollback eyleminin nonce\'u: nonce_invalid, depo yok', 'nonce_invalid' === $r['code'] && 'YOK' === get_option( $store, 'YOK' ) );

$r = $B::import( array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_IMPORT ) ) );
$rules  = MaviBelge_Core_Redirects_Service::rules();
$active = count( array_filter( $rules, function ( $x ) {
	return ! empty( $x['active'] );
} ) );
$t( 'Gerçek import (mu-plugins yanındaki manifest, sabit SHA-256): imported, 29 toplam / 4 etkin', 'imported' === $r['code'] && 29 === count( $rules ) && 4 === $active );
$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $store ) );
$t( 'Depo veritabanında autoload kapalı', in_array( $autoload, array( 'no', 'off' ), true ) );
$manifest = json_decode( file_get_contents( WPMU_PLUGIN_DIR . '/redirects.manifest.json' ), true );
$t( 'Veritabanındaki kurallar manifestle birebir aynı', $manifest['rules'] === $rules );

if ( class_exists( 'MaviBelge_Core_Health_Page' ) && is_callable( array( 'MaviBelge_Core_Health_Page', 'collect_env' ) ) && class_exists( 'MaviBelge_Core_Health_Checks' ) ) {
	$env    = MaviBelge_Core_Health_Page::collect_env();
	$line   = isset( $env['redirects'] ) ? $env['redirects'] : null;
	$t( 'Sağlık ekranı verisi: toplam 29, etkin 4', is_array( $line ) && 29 === (int) $line['total'] && 4 === (int) $line['active'] );
}

$r = $B::import( array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_IMPORT ) ) );
$t( 'İkinci import reddedilir: already_imported', 'already_imported' === $r['code'] && 29 === count( MaviBelge_Core_Redirects_Service::rules() ) );

$r = $B::rollback( array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_ROLLBACK ) ) );
wp_cache_delete( $store, 'options' );
$t( 'Rollback: rolled_back, depo seçeneği veritabanından kalktı', 'rolled_back' === $r['code'] && null === $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $store ) ) );
$st = get_option( $B::STATE_OPTION );
$t( 'Durum kaydı rolled_back; kişisel/kullanıcı verisi alanı yok', is_array( $st ) && 'rolled_back' === $st['state'] && ! isset( $st['user'] ) && ! isset( $st['user_id'] ) );

// Başlangıçta dolu (loader'ın oluşturmadığı) depo: import ve rollback reddedilir, veri korunur.
delete_option( $B::STATE_OPTION );
$foreign = array( array( 'source' => '/test-eski/', 'target' => '/kvkk/', 'status' => 301, 'origin' => 'verified', 'active' => true, 'note' => 'TEST' ) );
MaviBelge_Core_Redirects_Service::save_rules( $foreign );
$r1 = $B::import( array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_IMPORT ) ) );
$r2 = $B::rollback( array( 'mb_rb_nonce' => wp_create_nonce( $B::ACTION_ROLLBACK ) ) );
$t( 'Dolu depo: import store_not_empty, rollback rollback_not_allowed, mevcut veri korunur', 'store_not_empty' === $r1['code'] && 'rollback_not_allowed' === $r2['code'] && $foreign === get_option( $store ) );

// HTTP testi için temiz başlangıç.
delete_option( $store );
delete_option( $B::STATE_OPTION );
$t( 'Temizlik: depo ve durum kaydı yok (HTTP testi boş depodan başlar)', 'YOK' === get_option( $store, 'YOK' ) && 'YOK' === get_option( $B::STATE_OPTION, 'YOK' ) );

echo "\n{$total} test, " . ( $total - $failures ) . " geçti, {$failures} başarısız.\n";
exit( $failures > 0 ? 1 : 0 );
