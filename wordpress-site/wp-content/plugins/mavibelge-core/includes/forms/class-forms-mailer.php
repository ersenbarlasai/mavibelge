<?php
/**
 * Faz 8 — e-posta gönderim arayüzü (adaptör). Servis yalnız bu arayüze konuşur;
 * testlerde GERÇEK e-posta gönderilmez (sahte uygulama enjekte edilir).
 * Üretim uygulaması `wp_mail()`'i sarar. Uygulama, `MaviBelge_Core_Forms_Mail_Builder`
 * çıktısını olduğu gibi iletir (başlık enjeksiyonu kurucuda engellenmiştir).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MaviBelge_Core_Forms_Mailer {

	/**
	 * @param array $message Mail_Builder::build() çıktısı (to, subject, body, headers, attachments).
	 * @return bool Yalnız teslim için KABUL edildiyse true.
	 */
	public function send( array $message );
}

class MaviBelge_Core_Forms_Wp_Mailer implements MaviBelge_Core_Forms_Mailer {

	public function send( array $message ) {
		return true === wp_mail( $message['to'], $message['subject'], $message['body'], $message['headers'], $message['attachments'] );
	}
}
