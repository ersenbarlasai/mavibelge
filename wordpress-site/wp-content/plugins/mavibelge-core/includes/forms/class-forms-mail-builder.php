<?php
/**
 * Faz 8 — e-posta iletisi kurucusu (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Konu ve alıcı SABİT/yapılandırma kaynaklıdır; kullanıcı girdisi ASLA konuya
 * veya başlıklara girmez, yalnız (doğrulanmış) Reply-To e-postası başlık olur.
 * Ürettiği hiçbir başlık CR/LF içeremez (başlık enjeksiyonu koruması);
 * ihlalde null döner ve gönderim yapılmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Mail_Builder {

	/**
	 * @param string   $formId
	 * @param array    $clean       Validator::validate() `clean` çıktısı
	 * @param array    $settings    Form yapılandırması (recipient_email, consent_version)
	 * @param string[] $attachments Geçici dosya yolları (yalnız yol dizgeleri)
	 * @return array{to: string, subject: string, body: string, headers: string[], attachments: string[]}|null
	 */
	public static function build( $formId, array $clean, array $settings, array $attachments = array() ) {
		$def = MaviBelge_Core_Forms_Schema::get( $formId );
		if ( null === $def || empty( $settings['recipient_email'] ) || ! MaviBelge_Core_Forms_Validator::is_valid_email( $settings['recipient_email'] ) ) {
			return null;
		}
		$lines = array();
		foreach ( $def['fields'] as $field ) {
			$name = $field['name'];
			if ( 'file' === $field['type'] ) {
				continue;
			}
			if ( 'consent' === $field['type'] ) {
				$lines[] = 'KVKK onayı: verildi (sürüm ' . self::single_line( isset( $settings['consent_version'] ) ? $settings['consent_version'] : '' ) . ')';
				continue;
			}
			if ( ! array_key_exists( $name, $clean ) ) {
				continue;
			}
			$lines[] = $field['label'] . ': ' . self::value_text( $field, $clean[ $name ] );
		}
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( isset( $clean['email'] ) && MaviBelge_Core_Forms_Validator::is_valid_email( $clean['email'] ) ) {
			$headers[] = 'Reply-To: ' . $clean['email'];
		}
		$subject = '[Mavi Belge] ' . $def['label'] . ' formu';
		foreach ( array_merge( $headers, array( $subject, $settings['recipient_email'] ) ) as $headerValue ) {
			if ( 1 === preg_match( '/[\r\n\0]/', $headerValue ) ) {
				return null;
			}
		}
		foreach ( $attachments as $path ) {
			if ( ! is_string( $path ) || '' === $path || 1 === preg_match( '/[\r\n\0]/', $path ) ) {
				return null;
			}
		}
		return array(
			'to'          => $settings['recipient_email'],
			'subject'     => $subject,
			'body'        => implode( "\n", $lines ) . "\n",
			'headers'     => $headers,
			'attachments' => array_values( $attachments ),
		);
	}

	private static function value_text( array $field, $value ) {
		if ( ( 'select' === $field['type'] || 'radio' === $field['type'] ) && isset( $field['options'][ $value ] ) ) {
			return $field['options'][ $value ];
		}
		return is_string( $value ) ? $value : (string) $value;
	}

	private static function single_line( $value ) {
		return trim( preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $value ) );
	}
}
