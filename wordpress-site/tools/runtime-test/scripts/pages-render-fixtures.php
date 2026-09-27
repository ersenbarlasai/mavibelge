<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı. Faz 12: GERÇEK pages manifestini (data/content/pages.manifest.json)
 * ÜRETİM KODUYLA (Admin_Run_Service: önizleme -> onaylı apply -> resumable batch'ler -> AYRI yayınlama işlemi) izole fixture
 * DB'sine uygular; kalıcı bağlantıları /%postname%/ yapar.
 *   import       : pages aşaması (32 TASLAK) + yayınlama işlemi; Faz 12b: GERÇEK content manifestleri (6 haber, 15 logolu referans, 6 SSS) content
 *                  aşamasıyla izole DB'ye uygulanır (logo attachment'ları /tmp/mbfx-uploads'a), SSS + referans kayıtları yayına alınır ve
 *                  bağımlı sayfalar (referanslar, sss) ayrı yayınlama işlemiyle yayınlanır. Çıktı JSON: published / draft slug'ları.
 *   publish-held : YALNIZ TEST DÜZENEĞİ — kurum kararı bekleyen (bekletilen) sayfaları render/route testi için izole DB'de
 *                  doğrudan yayına alır. Üretimdeki yayınlama işlemi bunları YAYINLAMAZ; bu adım yalnız şablonların o sayfaları
 *                  doğru render ettiğini sınamak içindir ve gerçek staging/üretimde çalışmaz (yalnız mbfx_ öneki).
 * Parola/anahtar okunmaz.
 */
global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : 'import';
$list = function ( $status ) {
	$ids = get_posts( array( 'post_type' => 'page', 'post_status' => $status, 'numberposts' => -1, 'meta_key' => '_mb_import_source_key', 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
	$out = array();
	foreach ( $ids as $id ) {
		$out[] = (string) get_post_field( 'post_name', $id );
	}
	sort( $out );
	return $out;
};

if ( 'import' === $mode ) {
	$factory = new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => ABSPATH . 'data/content' ) );
	$svc     = new MaviBelge_Core_Import_Admin_Run_Service( $factory );
	$G       = 'MaviBelge_Core_Import_Admin_Gates';
	$p       = $svc->preview_stage( 'pages' );
	if ( true !== $p['eligible'] ) {
		fwrite( STDERR, "pages önizleme uygulanabilir değil\n" );
		exit( 1 );
	}
	$st = $svc->start_apply( 'pages', (string) $p['plan_digest'], (string) $G::apply_phrase( 'pages', (string) $p['plan_digest'] ), get_current_user_id() );
	for ( $i = 0; $i < 10 && ! empty( $st['ok'] ) && in_array( $st['status'], array( 'ready', 'paused' ), true ); $i++ ) {
		$st = ( new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => ABSPATH . 'data/content' ) ) ) )->advance_apply( $st['run_uid'], $st['checkpoint'], get_current_user_id() );
	}
	if ( empty( $st['ok'] ) || 'completed' !== $st['status'] ) {
		fwrite( STDERR, "pages apply tamamlanmadı\n" );
		exit( 1 );
	}
	$pp = $svc->preview_publish();
	$rem = $pp['summary']['ready'];
	for ( $i = 0; $i < 10 && $rem > 0; $i++ ) {
		$r = ( new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => ABSPATH . 'data/content' ) ) ) )->publish_pages( $pp['plan_digest'], $pp['phrase'], $rem, get_current_user_id() );
		if ( empty( $r['ok'] ) ) {
			fwrite( STDERR, "yayın başarısız: " . (string) $r['error_code'] . "\n" );
			exit( 1 );
		}
		$rem = $r['remaining'];
	}
	// Faz 12b: content aşaması (gerçek manifestler) -> SSS/referans kayıtlarını yayına al -> bağımlı sayfaları yayınla.
	foreach ( array( 'haber' => 'Haber', 'duyuru' => 'Duyuru' ) as $ts => $tn ) {
		if ( ! get_term_by( 'slug', $ts, 'mb_haber_turu' ) ) {
			wp_insert_term( $tn, 'mb_haber_turu', array( 'slug' => $ts ) );
		}
	}
	$cf  = new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => ABSPATH . 'data/content' ) );
	$cp  = $cf->apply_service()->preview( 'content' );
	$ca  = $cf->apply_service()->apply( 'content', (string) $cp['plan_digest'], null, get_current_user_id() );
	if ( empty( $ca['ok'] ) || 'completed' !== $ca['status'] ) {
		fwrite( STDERR, 'content apply tamamlanmadı: ' . wp_json_encode( $ca ) . "\n" );
		exit( 1 );
	}
	foreach ( get_posts( array( 'post_type' => array( 'mb_sss', 'mb_referans' ), 'post_status' => 'draft', 'numberposts' => -1, 'fields' => 'ids' ) ) as $cid ) {
		wp_update_post( array( 'ID' => $cid, 'post_status' => 'publish' ) );
	}
	$pp2  = ( new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => ABSPATH . 'data/content' ) ) ) )->preview_publish();
	$rem2 = $pp2['summary']['ready'];
	for ( $i = 0; $i < 3 && $rem2 > 0; $i++ ) {
		$r2 = ( new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => ABSPATH . 'data/content' ) ) ) )->publish_pages( $pp2['plan_digest'], $pp2['phrase'], $rem2, get_current_user_id() );
		if ( empty( $r2['ok'] ) ) {
			fwrite( STDERR, 'yayın (içerik sonrası) başarısız: ' . (string) $r2['error_code'] . "\n" );
			exit( 1 );
		}
		$rem2 = $r2['remaining'];
	}
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules( true );
	echo wp_json_encode( array( 'published' => $list( 'publish' ), 'draft' => $list( 'draft' ), 'permalink' => get_option( 'permalink_structure' ) ) ), "\n";
	return;
}

if ( 'publish-held' === $mode ) {
	$n = 0;
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'draft', 'numberposts' => -1, 'meta_key' => '_mb_import_source_key', 'fields' => 'ids' ) ) as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		$n++;
	}
	echo wp_json_encode( array( 'force_published_in_fixture' => $n, 'published' => $list( 'publish' ), 'draft' => $list( 'draft' ) ) ), "\n";
	return;
}
echo "HATA: bilinmeyen mod (import|publish-held)\n";
exit( 1 );
