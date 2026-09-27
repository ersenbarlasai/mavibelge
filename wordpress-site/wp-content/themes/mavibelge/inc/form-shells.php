<?php
/**
 * Field LABEL lists (not real form fields, no validation rules, no
 * personal-data fixtures) for the 5 pages that must not submit yet
 * (Faz 4 brief §9). Kept separate from inc/page-layouts.php since this
 * is a different kind of structural config (form transparency text, not
 * navigation). Real field types/validation/KVKK text are Faz 8 decisions
 * — this is only what's shown to a visitor so they know what the form
 * will eventually ask for.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $slug
 * @return array<int, string> field labels, in the order the static
 *   reference (tanitim-site) shows them
 */
function mavibelge_form_shell_fields_for_slug( $slug ) {
	$forms = array(
		'online-basvuru'   => array(
			__( 'Meslek / Sınav Yeri Seçimi', 'mavibelge' ),
			__( 'Ad Soyad', 'mavibelge' ),
			__( 'TC Kimlik No', 'mavibelge' ),
			__( 'Telefon', 'mavibelge' ),
			__( 'E-posta', 'mavibelge' ),
			__( 'Belge Yükleme', 'mavibelge' ),
			__( 'KVKK Aydınlatma Metni Onayı', 'mavibelge' ),
		),
		'sinav-talepleri'  => array(
			__( 'Başvuru Türü (Bireysel / Kurumsal)', 'mavibelge' ),
			__( 'Ad Soyad', 'mavibelge' ),
			__( 'Firma (kurumsal ise)', 'mavibelge' ),
			__( 'Telefon', 'mavibelge' ),
			__( 'E-posta', 'mavibelge' ),
			__( 'Meslek', 'mavibelge' ),
			__( 'Aday Sayısı', 'mavibelge' ),
			__( 'Mesaj', 'mavibelge' ),
		),
		'itiraz-sikayet'   => array(
			__( 'Bildirim Türü (İtiraz / Şikayet)', 'mavibelge' ),
			__( 'Ad Soyad', 'mavibelge' ),
			__( 'E-posta', 'mavibelge' ),
			__( 'Konu', 'mavibelge' ),
			__( 'Açıklama', 'mavibelge' ),
		),
		'iletisim'         => array(
			__( 'Ad Soyad', 'mavibelge' ),
			__( 'Telefon', 'mavibelge' ),
			__( 'E-posta', 'mavibelge' ),
			__( 'İlgili Meslek', 'mavibelge' ),
			__( 'Mesaj', 'mavibelge' ),
		),
		'is-basvurusu'     => array(
			__( 'Ad Soyad', 'mavibelge' ),
			__( 'Telefon', 'mavibelge' ),
			__( 'E-posta', 'mavibelge' ),
			__( 'Pozisyon', 'mavibelge' ),
			__( 'Özgeçmiş (CV) Yükleme', 'mavibelge' ),
			__( 'Mesaj', 'mavibelge' ),
		),
	);

	return isset( $forms[ $slug ] ) ? $forms[ $slug ] : array();
}

/**
 * Faz 12f — online başvuru adımları (tanitim-site/online-basvuru.html). Yalnız SUNUM gruplaması: alanların kendisi,
 * doğrulaması ve kapısı eklentideki merkezi şemadadır. Bir alan hiçbir adımda değilse form onu adımların ardından çizer.
 *
 * @return array<int, array{title: string, fields: string[]}>
 */
function mavibelge_application_steps() {
	return array(
		array( 'title' => __( '1. Meslek Seçimi', 'mavibelge' ), 'fields' => array( 'qualification', 'exam_location' ) ),
		array( 'title' => __( '2. Kişisel Bilgiler', 'mavibelge' ), 'fields' => array( 'full_name', 'national_id', 'phone', 'email' ) ),
		array( 'title' => __( '3. Belgeler', 'mavibelge' ), 'fields' => array( 'documents' ) ),
		array( 'title' => __( '4. Onay', 'mavibelge' ), 'fields' => array( 'consent' ) ),
	);
}
