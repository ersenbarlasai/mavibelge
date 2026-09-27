<?php
/**
 * Faz 12g — iletişim bilgisinin TEK kaynağı (footer + İletişim sayfası). PHP 7.3 uyumlu; sorgu/iş kuralı YOK.
 *
 * - Lokasyonlar: önce merkezi içerik servisi (mavibelge_get_locations(): yayında + görünürlük kapısından geçmiş mb_lokasyon,
 *   harita URL'si servis tarafından yalnız https ile doğrulanmış). Kayıt yoksa AŞAĞIDAKİ doğrulanmış yedek — kabul edilmiş
 *   statik referanstaki (tanitim-site/iletisim.html + index.html footer) değerlerin birebir kopyasıdır; yeni adres/telefon/
 *   harita UYDURULMAZ. Kurumsal güncellik onayı (`location_data_pending`) bu yedekle KAPANMAZ.
 * - Sosyal medya: footer'da daha önce doğrulanmış üç hesap (yalnız şema https). X/Twitter hesabı kurum teyidi bekler.
 * - Birincil iletişim kanalı: statik referanstaki merkez telefon + e-posta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Statik referanstan doğrulanmış lokasyon yedeği (DTO ile aynı biçim + `fallback`/`footer_phones`/`email`).
 * `footer_phones` false: bu lokasyonun telefonları footer'ın lokasyon şeridinde gösterilmez (statik footer düzeni;
 * merkez telefonları footer'ın "İletişim" sütunundadır).
 *
 * @return array<int, array>
 */
function mavibelge_fallback_locations() {
	$tel = function ( $display, $tel ) {
		return array( 'display' => $display, 'tel' => $tel );
	};
	return array(
		array(
			'id'            => 0,
			'name'          => __( 'Merkez Ofis — İskenderun/Hatay', 'mavibelge' ),
			'address'       => __( 'Mustafa Kemal Mah. İbrahim Karaoğlanoğlu Cad. Atay İş Merkezi Kat:12 Daire:63–64 İskenderun / HATAY Pk:31200', 'mavibelge' ),
			'phones'        => array( $tel( '0850 215 44 22', 'tel:08502154422' ), $tel( '0326 441 44 22', 'tel:03264414422' ), $tel( '0542 618 62 84', 'tel:05426186284' ) ),
			'email'         => 'info@mavibelge.com.tr',
			'hours'         => '',
			'map_url'       => '',
			'fallback'      => true,
			'footer_phones' => false,
		),
		array(
			'id'            => 0,
			'name'          => __( 'Payas Sınav Alanı', 'mavibelge' ),
			'address'       => __( 'Yıldırım Beyazıt, Özkul Çolak Cd., 31900 Payas / Dörtyol / Hatay', 'mavibelge' ),
			'phones'        => array( $tel( '0850 215 44 22', 'tel:08502154422' ) ),
			'email'         => '',
			'hours'         => '',
			'map_url'       => '',
			'fallback'      => true,
			'footer_phones' => false,
		),
		array(
			'id'            => 0,
			'name'          => __( 'İzmir Aliağa Sınav Alanı', 'mavibelge' ),
			'address'       => __( 'Siteler Mahallesi, 35800 Aliağa / İzmir', 'mavibelge' ),
			'phones'        => array( $tel( '0542 619 62 84', 'tel:05426196284' ) ),
			'email'         => '',
			'hours'         => '',
			'map_url'       => '',
			'fallback'      => true,
			'footer_phones' => true,
		),
		array(
			'id'            => 0,
			'name'          => __( 'Ankara Ofisi', 'mavibelge' ),
			'address'       => __( '1176 Sokak, No: 28, Ostim / ANKARA', 'mavibelge' ),
			'phones'        => array( $tel( '0542 619 62 84', 'tel:+905426196284' ), $tel( '0542 622 62 84', 'tel:+905426226284' ) ),
			'email'         => '',
			'hours'         => '',
			'map_url'       => 'https://www.google.com/maps/search/?api=1&query=1176%20Sokak%20No%3A28%20Ostim%20Ankara',
			'fallback'      => true,
			'footer_phones' => true,
		),
	);
}

/**
 * Gösterilecek lokasyonlar: merkezi servisin herkese açık kayıtları; hiç yoksa doğrulanmış yedek.
 *
 * @return array<int, array>
 */
function mavibelge_contact_locations() {
	$locations = function_exists( 'mavibelge_get_locations' ) ? mavibelge_get_locations() : array();
	if ( ! empty( $locations ) ) {
		foreach ( $locations as $index => $location ) {
			$locations[ $index ]['fallback']      = false;
			$locations[ $index ]['footer_phones'] = true;
			$locations[ $index ]['email']         = '';
		}
		return $locations;
	}
	return mavibelge_fallback_locations();
}

/**
 * Doğrulanmış sosyal medya hesapları (footer ve İletişim sayfası bu listeyi paylaşır).
 *
 * @return array<int, array{key: string, url: string, label: string}>
 */
function mavibelge_social_links() {
	return array(
		array( 'key' => 'facebook', 'url' => 'https://www.facebook.com/mavibelge31', 'label' => __( 'Mavi Belge Facebook hesabı', 'mavibelge' ) ),
		array( 'key' => 'instagram', 'url' => 'https://www.instagram.com/mavi_belge', 'label' => __( 'Mavi Belge Instagram hesabı', 'mavibelge' ) ),
		// Statik kaynakta http://twitter.com/mavibelge31; yalnız şema https'e düzeltildi. Hesap kurum teyidi bekler (docs/faz3-dependencies.md).
		array( 'key' => 'x', 'url' => 'https://twitter.com/mavibelge31', 'label' => __( 'Mavi Belge X (Twitter) hesabı', 'mavibelge' ) ),
	);
}

/**
 * Birincil iletişim kanalı (statik referanstaki merkez numara ve e-posta).
 *
 * @return array{phone_display: string, phone_tel: string, email: string}
 */
function mavibelge_primary_contact() {
	return array(
		'phone_display' => '0850 215 44 22',
		'phone_tel'     => 'tel:08502154422',
		'email'         => 'info@mavibelge.com.tr',
	);
}
