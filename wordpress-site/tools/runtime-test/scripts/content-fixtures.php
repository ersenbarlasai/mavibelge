<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı (`wp --require=fixture-env.php --user=mbadmin eval-file`).
 * Faz 7/8 HTTP render ve form testleri için AÇIKÇA SAHTE içerik yazar (başlıklar "TEST ..." ile başlar; gerçek kişi,
 * kurum, telefon, e-posta, T.C. kimlik no YOKTUR; e-postalar .example alan adlıdır). Gerçek kayıt yolu kullanılır
 * (wp_insert_post + meta). "Yayında ama onaysız/pasif" durumlar, yayın kapısının (Publish_Readiness) DIŞINDA, yayın
 * sonrası doğrudan meta güncellemesiyle kurulur (eski/içe aktarılmış kayıt senaryosu).
 */

global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	exit( 1 );
}

$out = array();
$mk  = function ( $type, $title, $meta = array(), $extra = array() ) use ( $wpdb ) {
	$want = isset( $extra['post_status'] ) ? $extra['post_status'] : 'publish';
	$id   = wp_insert_post( wp_slash( array_merge( array( 'post_type' => $type, 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_content' => 'TEST içerik: ' . $title ), $extra, array( 'post_status' => 'draft' ) ) ), true );
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, 'oluşturulamadı: ' . $title . ' ' . $id->get_error_message() . "
" );
		exit( 1 );
	}
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, wp_slash( $v ) );
	}
	if ( 'draft' !== $want ) {
		// Yayın kapısı (Publish_Readiness) yalnız yönetim kaydetme yolunu korur; fixture, eski/içe aktarılmış "yayında" kayıt durumunu doğrudan kurar.
		$wpdb->update( $wpdb->posts, array( 'post_status' => $want ), array( 'ID' => $id ) );
		clean_post_cache( $id );
	}
	return (int) $id;
};

// --- Sayfalar (statik referanstaki slug'lar)
$pages = array(
	'iletisim'        => 'İletişim',
	'online-basvuru'  => 'Online Başvuru',
	'sinav-talepleri' => 'Sınav Talepleri',
	'itiraz-sikayet'  => 'İtiraz ve Şikayet',
	'is-basvurusu'    => 'İş Başvurusu',
	'sss'             => 'Sık Sorulan Sorular',
	'referanslar'     => 'Referanslarımız',
);
foreach ( $pages as $slug => $title ) {
	$out['page'][ $slug ] = $mk( 'page', $title, array(), array( 'post_name' => $slug, 'post_content' => '' ) );
}

// --- Haber türü terimleri (kontrollü kelime dağarcığı) ve haberler
foreach ( array( 'haber', 'duyuru' ) as $slug ) {
	if ( ! get_term_by( 'slug', $slug, 'mb_haber_turu' ) ) {
		wp_insert_term( 'haber' === $slug ? 'Haber' : 'Duyuru', 'mb_haber_turu', array( 'slug' => $slug ) );
	}
}
$news_ids = array();
for ( $i = 1; $i <= 13; $i++ ) {
	$id = $mk( 'mb_haber', sprintf( 'TEST Haber %02d', $i ), array( '_mb_approval_status' => 'approved' ), array( 'post_date' => sprintf( '2026-08-%02d 10:00:00', $i ), 'post_excerpt' => 'TEST özet ' . $i ) );
	wp_set_object_terms( $id, 1 === $i % 4 ? 'duyuru' : 'haber', 'mb_haber_turu' );
	$news_ids[] = $id;
}
$out['news_last']        = $news_ids[12];
$out['news_hidden']      = $mk( 'mb_haber', 'TEST Onaysız Yayında Haber', array( '_mb_approval_status' => 'in_review' ), array( 'post_date' => '2026-09-01 10:00:00' ) );
$out['news_draft']       = $mk( 'mb_haber', 'TEST Taslak Haber', array( '_mb_approval_status' => 'draft' ), array( 'post_status' => 'draft' ) );

// --- Dokümanlar (gerçek dosya: /tmp altındaki sentetik PDF; absolut _wp_attached_file)
file_put_contents( '/tmp/mbfx-test-doc.pdf', "%PDF-1.4\n% TEST\n" );
$att = wp_insert_attachment( array( 'post_title' => 'TEST Ek PDF', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit' ), false );
update_post_meta( $att, '_wp_attached_file', '/tmp/mbfx-test-doc.pdf' );
$att_bad = wp_insert_attachment( array( 'post_title' => 'TEST Ek HTML', 'post_mime_type' => 'text/html', 'post_status' => 'inherit' ), false );
update_post_meta( $att_bad, '_wp_attached_file', '/tmp/mbfx-test-doc.pdf' );
foreach ( array( 'kalite' => 'Kalite Belgeleri', 'formlar' => 'Formlar' ) as $slug => $name ) {
	wp_insert_term( $name, 'mb_dokuman_kategori', array( 'slug' => $slug ) );
}
$out['doc_ok']       = $mk( 'mb_dokuman', 'TEST Doküman Geçerli', array( '_mb_attachment_id' => $att, '_mb_document_version' => 'v1', '_mb_publish_date' => '2026-01-10', '_mb_record_status' => 'active' ) );
wp_set_object_terms( $out['doc_ok'], 'kalite', 'mb_dokuman_kategori' );
$out['doc_nofile']   = $mk( 'mb_dokuman', 'TEST Doküman Dosyasız', array( '_mb_attachment_id' => 0, '_mb_record_status' => 'active' ) );
$out['doc_badmime']  = $mk( 'mb_dokuman', 'TEST Doküman Zararlı MIME', array( '_mb_attachment_id' => $att_bad, '_mb_record_status' => 'active' ) );
$out['doc_expired']  = $mk( 'mb_dokuman', 'TEST Doküman Süresi Dolmuş', array( '_mb_attachment_id' => $att, '_mb_valid_until' => '2026-01-01', '_mb_record_status' => 'active' ) );
wp_set_object_terms( $out['doc_expired'], 'formlar', 'mb_dokuman_kategori' );
$out['doc_passive']  = $mk( 'mb_dokuman', 'TEST Doküman Pasif', array( '_mb_attachment_id' => $att, '_mb_record_status' => 'passive' ) );

// --- Referanslar
file_put_contents( '/tmp/mbfx-test-logo.png', base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );
$logo = wp_insert_attachment( array( 'post_title' => 'TEST Logo', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), false );
update_post_meta( $logo, '_wp_attached_file', '/tmp/mbfx-test-logo.png' );
$out['ref_real']  = $mk( 'mb_referans', 'TEST Referans Gerçek', array( '_mb_reference_status' => 'real', '_mb_website_url' => 'https://ornek.example/', '_mb_sort_order' => 2, '_mb_record_status' => 'active', '_mb_logo_attachment_id' => $logo ) );
$out['ref_rep']   = $mk( 'mb_referans', 'TEST Referans Temsili', array( '_mb_reference_status' => 'representative', '_mb_website_url' => 'javascript:alert(1)', '_mb_sort_order' => 1, '_mb_record_status' => 'active' ) );
$out['ref_pass']  = $mk( 'mb_referans', 'TEST Referans Pasif', array( '_mb_reference_status' => 'representative', '_mb_sort_order' => 3, '_mb_record_status' => 'passive' ) );

// --- Lokasyonlar
$out['loc_a']    = $mk( 'mb_lokasyon', 'TEST Lokasyon A', array( '_mb_address' => 'TEST Adres Satırı A', '_mb_phone_numbers' => array( '0212 000 00 01' ), '_mb_map_url' => 'https://harita.example/a', '_mb_working_hours' => 'TEST 09:00-18:00', '_mb_sort_order' => 1, '_mb_record_status' => 'active' ) );
$out['loc_b']    = $mk( 'mb_lokasyon', 'TEST Lokasyon B', array( '_mb_address' => 'TEST Adres Satırı B', '_mb_map_url' => 'javascript:alert(2)', '_mb_sort_order' => 2, '_mb_record_status' => 'active' ) );
$out['loc_pass'] = $mk( 'mb_lokasyon', 'TEST Lokasyon Pasif', array( '_mb_address' => 'TEST Gizli Adres', '_mb_sort_order' => 3, '_mb_record_status' => 'passive' ) );

// --- SSS
$fq = wp_insert_term( 'Genel', 'mb_sss_kategori', array( 'slug' => 'genel' ) );
$out['faq_1']    = $mk( 'mb_sss', 'TEST Soru 1?', array( '_mb_sort_order' => 1, '_mb_record_status' => 'active' ), array( 'post_content' => 'TEST cevap 1 <script>alert(1)</script><strong>kalın</strong>' ) );
wp_set_object_terms( $out['faq_1'], 'genel', 'mb_sss_kategori' );
$out['faq_2']    = $mk( 'mb_sss', 'TEST Soru 2?', array( '_mb_sort_order' => 2, '_mb_record_status' => 'active' ) );
$out['faq_pass'] = $mk( 'mb_sss', 'TEST Soru Pasif?', array( '_mb_sort_order' => 3, '_mb_record_status' => 'passive' ) );

// --- Form yapılandırması: yalnız "contact" tüm kurum kararlarıyla AÇIK (SENTETİK metin); "complaint" açık ama rıza yok (kapalı kalmalı).
$full = array( 'enabled' => '1', 'recipient_email' => 'kurum@ornek.example', 'consent_approved' => '1', 'consent_text' => 'TEST: sentetik onay metni (kurum kararı DEĞİL).', 'consent_version' => 'test-1' );
MaviBelge_Core_Forms_Service::save_config(
	array(
		'forms' => array(
			'contact'   => $full,
			'complaint' => array_merge( $full, array( 'consent_approved' => '' ) ),
		),
		'rate_limit' => array( 'per_client' => 8, 'window' => 600, 'global' => 500, 'global_window' => 3600 ),
	)
);

echo wp_json_encode( $out ),"\n";
