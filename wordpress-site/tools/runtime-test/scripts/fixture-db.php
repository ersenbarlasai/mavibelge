<?php
/**
 * YALNIZ yerel, izole test ortamı — `wp eval-file fixture-db.php <komut>`
 *
 *   clone    : ana `wp_` tablolarının AYNISINI `mbfx_` önekiyle kopyalar
 *              (CREATE TABLE ... LIKE + INSERT ... SELECT). Kullanıcı yetki
 *              meta anahtarları ve rol seçeneği yeni öneke taşınır. `mbfx_`
 *              tablosu zaten varsa DURUR (temiz başlangıç şartı).
 *   drop     : bütün `mbfx_` tablolarını siler ve kalmadığını doğrular.
 *   manifest : sahte apply fixture manifestlerini /tmp/mbfx-manifest ve
 *              /tmp/mbfx-manifest-v2 dizinlerine yazar (v2: bir sektör
 *              açıklaması ve bir ücret fiyatı değişik). Faz 7: sahte haber/
 *              referans manifestlerini /tmp/mbfx-content-manifest{,-v2}'ye yazar.
 *              Faz 6B4: 25 sektörlü /tmp/mbfx-b4-manifest (katalog + sahte içerik).
 *   rmmanifest: bu dizinleri siler.
 * Yalnız `wp_` önekiyle (fixture ortamı DIŞINDA) çalıştırılır. Üretime kopyalanmaz.
 */

global $wpdb;
$cmd = isset( $args[0] ) ? $args[0] : '';
if ( 'wp_' !== $wpdb->prefix ) {
	echo "HATA: fixture-db.php yalnız wp_ önekiyle çalışır.\n";
	return;
}
$like = function ( $prefix ) use ( $wpdb ) {
	return $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
};

if ( 'clone' === $cmd ) {
	if ( ! empty( $like( 'mbfx_' ) ) ) {
		echo "HATA: mbfx_ tabloları zaten var; önce drop.\n";
		return;
	}
	$n = 0;
	foreach ( $like( 'wp_' ) as $table ) {
		$new = 'mbfx_' . substr( $table, 3 );
		$wpdb->query( "CREATE TABLE `{$new}` LIKE `{$table}`" );
		$wpdb->query( "INSERT INTO `{$new}` SELECT * FROM `{$table}`" );
		$n++;
	}
	$wpdb->query( $wpdb->prepare( "UPDATE `mbfx_usermeta` SET meta_key = CONCAT('mbfx_', SUBSTRING(meta_key, 4)) WHERE meta_key LIKE %s", $wpdb->esc_like( 'wp_' ) . '%' ) );
	$wpdb->query( "UPDATE `mbfx_options` SET option_name = 'mbfx_user_roles' WHERE option_name = 'wp_user_roles'" );
	echo 'CLONE_OK tables=' . $n . ' mbfx=' . count( $like( 'mbfx_' ) ) . "\n";
	return;
}

if ( 'drop' === $cmd ) {
	foreach ( $like( 'mbfx_' ) as $table ) {
		$wpdb->query( "DROP TABLE `{$table}`" );
	}
	echo 'DROP_OK remaining=' . count( $like( 'mbfx_' ) ) . "\n";
	return;
}

if ( 'manifest' === $cmd ) {
	require_once WP_PLUGIN_DIR . '/mavibelge-core/tests/fixtures/apply-fixture.php';
	mb_apply_fixture_write_dir( '/tmp/mbfx-manifest', mb_apply_fixture_envelopes() );
	mb_apply_fixture_write_dir(
		'/tmp/mbfx-manifest-v2',
		mb_apply_fixture_envelopes(
			array(
				'fee'    => array( 'fee:zz-test-a:3:test-meslek-bir' => array( 'price_options' => array( array( 'label' => 'Sınav ücreti', 'units' => array( 'A1', 'A2' ), 'amount_kurus' => 1100000, 'sort_order' => 0 ) ), 'min_amount_kurus' => 1100000, 'max_amount_kurus' => 1100000 ) ),
				'sector' => array( 'sector:zz-test-a' => array( 'description' => 'Güncellenmiş sahte açıklama.' ) ),
			)
		)
	);
	// Faz 7 içerik aktarımı: AÇIKÇA SAHTE haber/referans manifestleri (zz-test-haber-*, zz-test-ref-*).
	// GERÇEK data/content/{news,references}.manifest.json ASLA kullanılmaz. v2: bir haberin gövdesi/özeti,
	// bir haberin türü+tarihi ve iki referansın sırası değişik.
	mb_content_fixture_write_dir( '/tmp/mbfx-content-manifest', mb_content_fixture_envelopes() );
	$v2   = mb_content_fixture_envelopes(
		array(
			'news' => array(
				'news:zz-test-haber-a' => array( 'body' => 'Güncellenmiş sahte gövde.', 'summary' => 'Güncellenmiş özet.' ),
				'news:zz-test-haber-c' => array( 'news_type' => 'duyuru', 'published_on' => '2026-03-01' ),
			),
		)
	);
	$swap = $v2['reference']['records'];
	$swap = array( $swap[1], $swap[0], $swap[2] );
	foreach ( $swap as $i => $r ) {
		$swap[ $i ]['source_index'] = $i;
	}
	$v2['reference']['records'] = $swap;
	mb_content_fixture_write_dir( '/tmp/mbfx-content-manifest-v2', $v2 );
	// Faz 6B4 — çok istekli (resumable) admin apply testleri: 25 sahte sektör (batch=10 -> 3 istek) + sahte haber/referans
	// AYNI dizinde (dört aşamanın hepsi bu dizinle çalışır). GERÇEK manifestler yazılmaz.
	mb_apply_fixture_write_dir( '/tmp/mbfx-b4-manifest', mb_apply_fixture_envelopes_with_sectors( 25 ) );
	mb_content_fixture_write_dir( '/tmp/mbfx-b4-manifest', mb_content_fixture_envelopes() );
	// Faz 12: aşama zinciri pages -> sectors -> qualifications -> all -> content ZORUNLU; pages manifesti (gerçek, yalnız TASLAK
	// sayfa içeriği) izole fixture DB'sine uygulanır. Gerçek manifest salt okunur kopyalanır (kaynak dizin DEĞİŞMEZ).
	$mbPages = dirname( ABSPATH ) . '/html/data/content/pages.manifest.json';
	$mbPages = is_readable( $mbPages ) ? $mbPages : ABSPATH . 'data/content/pages.manifest.json';
	copy( $mbPages, '/tmp/mbfx-b4-manifest/pages.manifest.json' );
	copy( $mbPages, '/tmp/mbfx-manifest/pages.manifest.json' );
	copy( $mbPages, '/tmp/mbfx-manifest-v2/pages.manifest.json' );
	// Tam zincir dizini (CLI content aşaması testi): sahte katalog + sahte içerik + pages.
	mb_apply_fixture_write_dir( '/tmp/mbfx-c7-full', mb_apply_fixture_envelopes() );
	mb_content_fixture_write_dir( '/tmp/mbfx-c7-full', mb_content_fixture_envelopes() );
	copy( $mbPages, '/tmp/mbfx-c7-full/pages.manifest.json' );
	// Faz 12b: yayın kapısı dizini — pages + sahte içerik (3 haber, 3 referans, 3 SSS): referanslar/sss sayfaları içerik kayıtları oluşup YAYINLANANA kadar yayınlanamaz.
	mb_content_fixture_write_dir( '/tmp/mbfx-pagegate', mb_content_fixture_envelopes( array(), 3 ) );
	copy( $mbPages, '/tmp/mbfx-pagegate/pages.manifest.json' );
	// Yalnız pages dizini (sayfa aşaması testleri).
	if ( ! is_dir( '/tmp/mbfx-pages-manifest' ) ) {
		mkdir( '/tmp/mbfx-pages-manifest', 0777, true );
	}
	copy( $mbPages, '/tmp/mbfx-pages-manifest/pages.manifest.json' );
	echo "MANIFEST_OK\n";
	return;
}

if ( 'rmmanifest' === $cmd ) {
	$dirs = array( '/tmp/mbfx-manifest', '/tmp/mbfx-manifest-v2', '/tmp/mbfx-content-manifest', '/tmp/mbfx-content-manifest-v2', '/tmp/mbfx-b4-manifest', '/tmp/mbfx-b4-manifest-mod', '/tmp/mbfx-c7-full', '/tmp/mbfx-pages-manifest', '/tmp/mbfx-pagegate' );
	foreach ( $dirs as $dir ) {
		foreach ( (array) glob( $dir . '/*.manifest.json' ) as $file ) {
			unlink( $file );
		}
		if ( is_dir( $dir ) ) {
			rmdir( $dir );
		}
	}
	// Faz 12b: fixture logo dosyaları (manifest dizininin kardeşi) ve geçici uploads dizini de silinir.
	foreach ( array( '/tmp/sources/reference-logos', '/tmp/mbfx-uploads/mbfx' ) as $extra ) {
		foreach ( (array) glob( $extra . '/*' ) as $file ) {
			is_file( $file ) && @unlink( $file );
		}
		is_dir( $extra ) && @rmdir( $extra );
	}
	is_dir( '/tmp/sources' ) && @rmdir( '/tmp/sources' ); // başka kullanıcıya (root testleri) ait olabilir: sessizce bırakılır (boş dizin)
	is_dir( '/tmp/mbfx-uploads' ) && @rmdir( '/tmp/mbfx-uploads' );
	$left = 0;
	foreach ( $dirs as $dir ) {
		$left += (int) is_dir( $dir );
	}
	echo 'RMMANIFEST_OK left=' . $left . "\n";
	return;
}

echo "HATA: bilinmeyen komut\n";
