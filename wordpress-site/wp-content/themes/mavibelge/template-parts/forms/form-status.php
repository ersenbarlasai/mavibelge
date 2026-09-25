<?php
/**
 * Form durum iletisi: başarı (PRG sonrası) veya genel hata (geçersiz istek, oran sınırı, gönderim hatası).
 * Alan hataları form.php içindeki özette gösterilir. İleti yalnız sabit metinlerdir; girilen değer içermez.
 *
 * $args:
 * - form (array, zorunlu) MaviBelge_Core_Forms_Service::describe() DTO'su
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form   = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
$status = isset( $form['status'] ) ? $form['status'] : '';
if ( 'success' === $status ) {
	get_template_part(
		'template-parts/components/alert',
		null,
		array(
			'type'    => 'alert',
			'variant' => 'success',
			'title'   => __( 'Talebiniz alındı', 'mavibelge' ),
			'message' => __( 'Bilgileriniz kurumumuza iletildi. Gerekirse sizinle iletişime geçilecektir.', 'mavibelge' ),
		)
	);
	return;
}
$general = isset( $form['errors']['_form'] ) ? $form['errors']['_form'] : '';
if ( '' !== $general ) {
	get_template_part(
		'template-parts/components/alert',
		null,
		array(
			'type'    => 'alert',
			'variant' => 'danger',
			'title'   => __( 'Gönderilemedi', 'mavibelge' ),
			'message' => $general,
		)
	);
}
