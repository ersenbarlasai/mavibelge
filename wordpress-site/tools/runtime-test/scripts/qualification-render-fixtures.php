<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı. Faz 12e yeterlilik liste/detay render testi için GERÇEK
 * katalog manifestlerini (data/content: 14 sektör, 83 yeterlilik, 103 ücret) mevcut admin apply servisiyle içe aktarır.
 *
 * Yalnız bu klonda (hepsi test düzeneğidir, üretim/staging davranışı DEĞİLDİR):
 *  - eski elle-tohumlanmış katalog kayıtları temizlenir;
 *  - sektör görselleri: qualification-render.sh'in wp-content/uploads/mbfx-catalog/ altına kopyaladığı referans görselleri
 *    attachment yapılır ve mevcut görsel eşleme servisiyle eşlenir (görseli olmayan 4 sektör bilerek görselsiz kalır);
 *  - pages -> sectors -> qualifications -> all aşamaları uygulanır (hazır sayfalar mevcut yayın servisiyle yayınlanır); yeterlilikler yayına alınır (staging'deki gibi);
 *  - aktif tarife dönemi 2026; YALNIZ iki kontrollü ücret aktif + yayında (staging QA ile aynı: 11UY0036-2/01 tek fiyat,
 *    10UY0002-3/03 çok seçenekli) — diğer 101 ücret taslak kalır;
 *  - kalıcı bağlantı yapısı staging ile aynı: /index.php/%postname%/ (qualification-render.sh ayarlar).
 * Çıktı: JSON (kimlikler/kalıcı bağlantılar). Ana `wp_` tablolarına dokunulmaz.
 */
global $wpdb, $wp_rewrite;
if ( 'mbfx_' !== $wpdb->prefix ) {
	fwrite( STDERR, "HATA: yalnız mbfx_ fixture önekinde çalışır.\n" );
	exit( 1 );
}
$dir    = ABSPATH . 'data/content';
$imgDir = ABSPATH . 'wp-content/uploads/mbfx-catalog';
$fail   = function ( $m ) {
	fwrite( STDERR, $m . "\n" );
	exit( 1 );
};
$svc = function () use ( $dir ) {
	return new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory( array( 'manifest_dir' => $dir ) ) );
};

// 1) eski katalog kayıtları (yalnız klon)
foreach ( (array) get_terms( array( 'taxonomy' => 'mb_sektor', 'hide_empty' => false, 'fields' => 'ids' ) ) as $termId ) {
	wp_delete_term( (int) $termId, 'mb_sektor' );
}
foreach ( array( 'mb_yeterlilik', 'mb_ucret' ) as $type ) {
	foreach ( get_posts( array( 'post_type' => $type, 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'fields' => 'ids' ) ) as $postId ) {
		wp_delete_post( (int) $postId, true );
	}
}

// 2) sektör görselleri -> attachment -> mevcut eşleme servisi
$sectors = json_decode( file_get_contents( $dir . '/sectors.manifest.json' ), true );
$byFile  = array();
foreach ( $sectors['records'] as $rec ) {
	if ( '' === (string) $rec['image'] ) {
		continue;
	}
	$base = basename( $rec['image'] );
	if ( ! isset( $byFile[ $base ] ) ) {
		$path = $imgDir . '/' . $base;
		if ( ! is_readable( $path ) ) {
			$fail( 'görsel yok: ' . $base );
		}
		$byFile[ $base ] = wp_insert_attachment( array( 'post_title' => 'TEST Sektör Görseli ' . $base, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $path );
	}
}
$view = $svc()->image_map_view();
$map  = array();
foreach ( $view['required'] as $slug => $label ) {
	if ( ! isset( $byFile[ $label ] ) ) {
		$fail( 'eşlenecek görsel yok: ' . $slug );
	}
	$map[ $slug ] = (int) $byFile[ $label ];
}
$saved = $svc()->save_image_map( $map );
if ( true !== $saved['ok'] ) {
	$fail( 'görsel eşleme kaydedilemedi: ' . wp_json_encode( $saved['error_codes'] ) );
}

// 3) sectors -> qualifications -> all
$G = 'MaviBelge_Core_Import_Admin_Gates';
foreach ( array( 'pages', 'sectors', 'qualifications', 'all' ) as $stage ) {
	$p = $svc()->preview_stage( $stage );
	if ( empty( $p['eligible'] ) ) {
		$fail( $stage . ' önizlemesi uygulanabilir değil: ' . wp_json_encode( isset( $p['summary'] ) ? $p['summary'] : $p ) );
	}
	$st = $svc()->start_apply( $stage, (string) $p['plan_digest'], (string) $G::apply_phrase( $stage, (string) $p['plan_digest'] ), get_current_user_id() );
	for ( $i = 0; $i < 40 && ! empty( $st['ok'] ) && in_array( $st['status'], array( 'ready', 'paused' ), true ); $i++ ) {
		$st = $svc()->advance_apply( $st['run_uid'], $st['checkpoint'], get_current_user_id() );
	}
	if ( empty( $st['ok'] ) || 'completed' !== $st['status'] ) {
		$fail( $stage . ' apply tamamlanmadı: ' . wp_json_encode( $st ) );
	}
}

// 3b) sayfa kaydı olan bağlantılar (Sınav Ücretleri, Belge Yenileme...) gerçek sayfaya gitsin: hazır sayfalar mevcut yayın servisiyle
$pp  = $svc()->preview_publish();
$rem = isset( $pp['summary']['ready'] ) ? (int) $pp['summary']['ready'] : 0;
for ( $i = 0; $i < 10 && $rem > 0; $i++ ) {
	$pr = $svc()->publish_pages( $pp['plan_digest'], $pp['phrase'], $rem, get_current_user_id() );
	if ( empty( $pr['ok'] ) ) {
		$fail( 'sayfa yayını başarısız: ' . (string) $pr['error_code'] );
	}
	$rem = (int) $pr['remaining'];
}

// 3c) YALNIZ test düzeneği: SSS sayfası içerik (mb_sss) kapısıyla taslak kalır; başlık regresyon turu (header-geometry-test.js)
//     SSS ekranını da render ettiği için klonda yayına alınır (boş durum çizilir). Üretim yayın kapısı DEĞİŞMEZ.
$sssPage = get_page_by_path( 'sss', OBJECT, 'page' );
if ( $sssPage instanceof WP_Post && 'publish' !== $sssPage->post_status ) {
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => (int) $sssPage->ID ) );
	clean_post_cache( (int) $sssPage->ID );
}

// 4) yeterlilikleri yayına al (staging'deki gibi); ücretlerden yalnız iki kontrollü kayıt aktif
foreach ( get_posts( array( 'post_type' => 'mb_yeterlilik', 'post_status' => 'draft', 'numberposts' => -1, 'fields' => 'ids' ) ) as $qid ) {
	// wp_update_post: WordPress post_name'i başlıktan üretir (staging'de yönetim ekranından yayınlamakla aynı).
	$res = wp_update_post( array( 'ID' => (int) $qid, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $res ) || 'publish' !== get_post_status( (int) $qid ) ) {
		$fail( 'yeterlilik yayınlanamadı: ' . (int) $qid );
	}
}
update_option( 'mb_active_tariff_period', '2026' );
$feeBySource = function ( $sourceKey ) {
	$ids = get_posts( array( 'post_type' => 'mb_ucret', 'post_status' => 'any', 'meta_key' => '_mb_import_source_key', 'meta_value' => $sourceKey, 'fields' => 'ids', 'numberposts' => 2 ) );
	return 1 === count( $ids ) ? (int) $ids[0] : 0;
};
$activate = array( 'fee:tekstil:2:iplik-bitim-isleri-operatoru', 'fee:makine:3:makine-bakimci' );
$active   = array();
foreach ( $activate as $key ) {
	$fid = $feeBySource( $key );
	if ( $fid <= 0 ) {
		$fail( 'ücret yok: ' . $key );
	}
	update_post_meta( $fid, '_mb_record_status', 'active' );
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $fid ) );
	clean_post_cache( $fid );
	$active[] = $fid;
}

// 5) kalıcı bağlantı yapısı (/index.php/%postname%/) qualification-render.sh tarafından bu süreçten ÖNCE ayarlanır; burada
//    CPT/taksonomi kuralları yeni yapıyla kayıtlı olduğundan yalnız kurallar yeniden üretilir.
flush_rewrite_rules( false );

$q = function ( $code ) {
	$ids = get_posts( array( 'post_type' => 'mb_yeterlilik', 'post_status' => 'publish', 'meta_key' => '_mb_myk_code', 'meta_value' => $code, 'fields' => 'ids', 'numberposts' => 2 ) );
	return 1 === count( $ids ) ? (int) $ids[0] : 0;
};
$pick = array(
	'iplik'   => '11UY0036-2/01', // tekstil (görselli), tek fiyat
	'multi'   => '10UY0002-3/03', // makine (görselli), çok seçenekli
	'nofee'   => '15UY0227-3/00', // Tornacı, ücret aktif değil
	'noimage' => '12UY0069-3/02', // plastik (görselsiz sektör)
);
$out = array( 'active_fee_ids' => $active, 'archive' => get_post_type_archive_link( 'mb_yeterlilik' ), 'quals' => array() );
foreach ( $pick as $k => $code ) {
	$id = $q( $code );
	if ( $id <= 0 ) {
		$fail( 'yeterlilik yok: ' . $code );
	}
	$terms = get_the_terms( $id, 'mb_sektor' );
	$term  = is_array( $terms ) ? $terms[0] : null;
	$out['quals'][ $k ] = array(
		'id'        => $id,
		'code'      => $code,
		'title'     => get_the_title( $id ),
		'url'       => get_permalink( $id ),
		'sector'    => $term ? $term->slug : '',
		'image_id'  => $term ? (int) get_term_meta( $term->term_id, '_mb_image_attachment_id', true ) : 0,
		'image_url' => $term ? (string) wp_get_attachment_image_url( (int) get_term_meta( $term->term_id, '_mb_image_attachment_id', true ), 'full' ) : '',
	);
}
$out['counts'] = array(
	'sectors' => count( (array) get_terms( array( 'taxonomy' => 'mb_sektor', 'hide_empty' => false, 'fields' => 'ids' ) ) ),
	'quals'   => (int) wp_count_posts( 'mb_yeterlilik' )->publish,
	'fees'    => array_sum( (array) wp_count_posts( 'mb_ucret' ) ),
	'active'  => count( MaviBelge_Core_Catalog_Service::get_active_fee_results( array() )['items'] ),
);
$out['sector_links'] = array();
foreach ( MaviBelge_Core_Catalog_Service::get_sector_terms() as $t ) {
	$out['sector_links'][] = array( 'slug' => $t->slug, 'name' => $t->name, 'url' => get_term_link( $t ), 'icon' => (string) get_term_meta( $t->term_id, '_mb_icon_key', true ) );
}
echo wp_json_encode( $out ), "\n";
