<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı. Faz 9 HTTP testleri için kalıcı bağlantıları
 * (index.php önekli: mod_rewrite gerektirmez) ve AÇIKÇA SAHTE eski-URL yönlendirme kurallarını yazar.
 * content-fixtures.php'den SONRA çalışır (form/render testleri düz bağlantı sorgularını kullandığı için
 * kalıcı bağlantılar SEO testlerinden hemen önce açılır).
 */
global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	exit( 1 );
}
$out = array();
// Yerel test ortamı "arama motorlarından gizle" ile kurulmuş olabilir; üretim davranışını sınamak için fixture'da açılır
// (staging kapısı testi ayrıca işaret dosyasıyla üretim-dışı ortamı simüle eder).
update_option( 'blog_public', '1' );
$out['blog_public'] = get_option( 'blog_public' );
// --- Kalıcı bağlantılar (index.php önekli: mod_rewrite gerektirmez) ve yönlendirme kuralları (fixture)
update_option( 'permalink_structure', '/index.php/%postname%/' );
flush_rewrite_rules( false );
$saved = MaviBelge_Core_Redirects_Service::save_rules(
	array(
		array( 'source' => '/index.php/eski-onayli/', 'target' => '/index.php/iletisim/', 'status' => 301, 'origin' => 'verified', 'active' => true, 'note' => 'fixture' ),
		array( 'source' => '/index.php/eski-onerilen/', 'target' => '/index.php/iletisim/', 'status' => 301, 'origin' => 'proposed', 'active' => false, 'note' => 'fixture' ),
		array( 'source' => '/index.php/eski-kaldirildi/', 'target' => '', 'status' => 410, 'origin' => 'verified', 'active' => true, 'note' => 'fixture' ),
		array( 'source' => '/index.php/eski-gecici/', 'target' => '/index.php/referanslar/', 'status' => 302, 'origin' => 'verified', 'active' => true, 'note' => 'fixture' ),
		array( 'source' => '/index.php/sss/', 'target' => '/index.php/iletisim/', 'status' => 301, 'origin' => 'verified', 'active' => true, 'note' => 'fixture: var olan sayfayı GÖLGELEMEMELİ' ),
	)
);
$out['redirects_saved'] = $saved['ok'];


echo wp_json_encode( $out ), "\n";
