<?php
/**
 * YALNIZ yerel runtime test ortamı için must-use eklenti (üretimde YOKTUR, depo dışı dağıtım paketine girmez).
 *
 * `/tmp/mbfx-http-on` işaret dosyası VARSA bu HTTP isteklerini silinebilir `mbfx_` fixture veritabanına yönlendirir
 * ($wpdb->set_prefix) ve wp_mail() çağrılarını GERÇEKTEN göndermeden kısa devre yapar (yalnız sayaç dosyasına
 * "1" satırı yazar — ileti içeriği/alıcı ASLA kaydedilmez). İşaret dosyası yoksa hiçbir şey yapmaz.
 * Ana `wp_` veritabanına HTTP üzerinden yazma yapılmasını önlemek için render/form testleri bu yolla çalışır.
 */

if ( is_readable( '/tmp/mbfx-http-on' ) ) {
	global $wpdb;
	$wpdb->set_prefix( 'mbfx_' );
	// Seçenekler ve nesne önbelleği ilk yüklemede `wp_` önekiyle dolmuş olabilir: temizle.
	wp_cache_flush();
	add_filter(
		'pre_wp_mail',
		function ( $short_circuit, $atts ) {
			file_put_contents( '/tmp/mbfx-mail.count', "1\n", FILE_APPEND );
			return true;
		},
		10,
		2
	);
	// Test ortamının WordPress ortam türü `local` olabilir: SEO testleri ÜRETİM davranışını sınar, bu yüzden üretim kabul edilir;
	// staging kapısı testi `/tmp/mbfx-staging` işaretiyle üretim-dışı ortamı simüle eder.
	add_filter(
		'mavibelge_core_is_production',
		function () {
			return ! is_readable( '/tmp/mbfx-staging' );
		}
	);
}
