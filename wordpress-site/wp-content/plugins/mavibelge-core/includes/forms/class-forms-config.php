<?php
/**
 * Faz 8 — form yapılandırması ve ETKİNLEŞTİRME KAPISI (SAF; WordPress
 * fonksiyonu çağırmaz — okuma/yazma `MaviBelge_Core_Forms_Service`'tedir).
 *
 * Varsayılan: HER FORM KAPALI. Bir form yalnız şu koşulların TAMAMI sağlanınca
 * açılır ve hiçbiri kod değil KONFİGÜRASYON kararıdır (kurum kararı geldiğinde
 * yönetim ekranından girilir):
 *  - `enabled`: yönetici formu açtı;
 *  - `consent_approved` + dolu `consent_text` + `consent_version`: KVKK açık rıza
 *    metni kurumca onaylandı (metin burada tutulur; kodda uydurma metin YOKTUR);
 *  - `recipient_email`: geçerli bir alıcı adresi;
 *  - hassas alanlı formlar (T.C. kimlik no, belge/CV yükleme) için ayrıca
 *    `sensitive_fields_approved`; dosya alanlı formlar için `uploads_approved`.
 * Kalıcı kişisel veri saklama YOKTUR (gönderim yalnız e-posta ile iletilir); bu
 * nedenle bir saklama süresi kararı gerekmez — kalıcı depolama eklenirse ayrı
 * bir `retention_days` kararı ve yeni bir kapı koşulu gerekir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Config {

	const OPTION_KEY = 'mavibelge_core_forms_config';

	const REASON_NOT_ENABLED       = 'not_enabled';
	const REASON_CONSENT           = 'consent_not_approved';
	const REASON_RECIPIENT         = 'recipient_not_configured';
	const REASON_SENSITIVE         = 'sensitive_fields_not_approved';
	const REASON_UPLOADS           = 'uploads_not_approved';
	const REASON_UNKNOWN_FORM      = 'unknown_form';

	/** Kapalı neden kodu -> yönetimde gösterilecek açık Türkçe açıklama (kurum kararı olarak). */
	const REASON_LABELS = array(
		'not_enabled'                   => 'Form yönetici tarafından açılmadı.',
		'consent_not_approved'          => 'KVKK açık rıza metni kurumca onaylanmadı (onay, metin ve sürüm girilmeli).',
		'recipient_not_configured'      => 'Gönderimlerin iletileceği alıcı e-posta adresi tanımlı değil.',
		'sensitive_fields_not_approved' => 'Hassas alanlar (T.C. kimlik no, kimlik/CV belgesi) için kurum onayı yok.',
		'uploads_not_approved'          => 'Dosya yükleme için kurum onayı yok.',
		'unknown_form'                  => 'Bilinmeyen form.',
	);

	const DEFAULT_RATE_LIMIT = array(
		'per_client'    => 8,
		'window'        => 600,
		'global'        => 60,
		'global_window' => 3600,
	);

	/** @return array<string, mixed> Bütün formlar KAPALI. */
	public static function defaults() {
		$forms = array();
		foreach ( MaviBelge_Core_Forms_Schema::FORM_IDS as $id ) {
			$forms[ $id ] = self::form_defaults();
		}
		return array( 'forms' => $forms, 'rate_limit' => self::DEFAULT_RATE_LIMIT );
	}

	public static function form_defaults() {
		return array(
			'enabled'                   => false,
			'recipient_email'           => '',
			'consent_approved'          => false,
			'consent_text'              => '',
			'consent_version'           => '',
			'sensitive_fields_approved' => false,
			'uploads_approved'          => false,
		);
	}

	/**
	 * Ham (kaydedilmiş veya gönderilmiş) yapılandırmayı kapalı şekle indirger: bilinmeyen form/anahtar atılır,
	 * tipler sıkılaştırılır, geçersiz alıcı boşaltılır. Eksik/bozuk her şey KAPALI varsayılana düşer.
	 *
	 * @param mixed $raw
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$out = self::defaults();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		$rawForms = isset( $raw['forms'] ) && is_array( $raw['forms'] ) ? $raw['forms'] : array();
		foreach ( MaviBelge_Core_Forms_Schema::FORM_IDS as $id ) {
			if ( ! isset( $rawForms[ $id ] ) || ! is_array( $rawForms[ $id ] ) ) {
				continue;
			}
			$f = $rawForms[ $id ];
			$clean = self::form_defaults();
			foreach ( array( 'enabled', 'consent_approved', 'sensitive_fields_approved', 'uploads_approved' ) as $flag ) {
				$clean[ $flag ] = isset( $f[ $flag ] ) && ( true === $f[ $flag ] || '1' === $f[ $flag ] || 1 === $f[ $flag ] || 'on' === $f[ $flag ] );
			}
			$email = isset( $f['recipient_email'] ) && is_string( $f['recipient_email'] ) ? trim( $f['recipient_email'] ) : '';
			$clean['recipient_email'] = MaviBelge_Core_Forms_Validator::is_valid_email( $email ) ? $email : '';
			$clean['consent_text']    = self::text( isset( $f['consent_text'] ) ? $f['consent_text'] : '', 1500 );
			$clean['consent_version'] = self::text( isset( $f['consent_version'] ) ? $f['consent_version'] : '', 40, true );
			$out['forms'][ $id ]      = $clean;
		}
		if ( isset( $raw['rate_limit'] ) && is_array( $raw['rate_limit'] ) ) {
			foreach ( self::DEFAULT_RATE_LIMIT as $key => $default ) {
				$v = isset( $raw['rate_limit'][ $key ] ) ? $raw['rate_limit'][ $key ] : $default;
				$out['rate_limit'][ $key ] = ( is_int( $v ) || ( is_string( $v ) && 1 === preg_match( '/^[0-9]{1,6}$/', $v ) ) ) && (int) $v > 0 ? (int) $v : $default;
			}
		}
		return $out;
	}

	/**
	 * Etkinleştirme kapısı: form yalnız TÜM koşullar sağlanırsa AÇIKTIR.
	 *
	 * @param string $formId
	 * @param array  $config sanitize() çıktısı
	 * @return array{open: bool, reasons: string[]}
	 */
	public static function gate( $formId, array $config ) {
		$def = MaviBelge_Core_Forms_Schema::get( $formId );
		if ( null === $def ) {
			return array( 'open' => false, 'reasons' => array( self::REASON_UNKNOWN_FORM ) );
		}
		$cfg     = isset( $config['forms'][ $formId ] ) && is_array( $config['forms'][ $formId ] ) ? array_merge( self::form_defaults(), $config['forms'][ $formId ] ) : self::form_defaults();
		$reasons = array();
		if ( true !== $cfg['enabled'] ) {
			$reasons[] = self::REASON_NOT_ENABLED;
		}
		if ( true !== $cfg['consent_approved'] || '' === trim( (string) $cfg['consent_text'] ) || '' === trim( (string) $cfg['consent_version'] ) ) {
			$reasons[] = self::REASON_CONSENT;
		}
		if ( '' === (string) $cfg['recipient_email'] || ! MaviBelge_Core_Forms_Validator::is_valid_email( (string) $cfg['recipient_email'] ) ) {
			$reasons[] = self::REASON_RECIPIENT;
		}
		if ( MaviBelge_Core_Forms_Schema::has_sensitive_fields( $formId ) && true !== $cfg['sensitive_fields_approved'] ) {
			$reasons[] = self::REASON_SENSITIVE;
		}
		if ( MaviBelge_Core_Forms_Schema::has_file_fields( $formId ) && true !== $cfg['uploads_approved'] ) {
			$reasons[] = self::REASON_UPLOADS;
		}
		return array( 'open' => empty( $reasons ), 'reasons' => $reasons );
	}

	/** Bir formun (kapı geçerse) kullanacağı doğrulanmış ayarlar; kapalıysa null. */
	public static function open_settings( $formId, array $config ) {
		$gate = self::gate( $formId, $config );
		return $gate['open'] ? $config['forms'][ $formId ] : null;
	}

	private static function text( $value, $max, $singleLine = false ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = str_replace( "\0", '', strip_tags( $value ) );
		$value = $singleLine ? preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value ) : preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		$value = trim( $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max, 'UTF-8' ) : substr( $value, 0, $max );
	}
}
