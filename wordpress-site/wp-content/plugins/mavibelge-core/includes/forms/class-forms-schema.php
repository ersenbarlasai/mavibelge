<?php
/**
 * Faz 8 — TEK merkezi form şeması (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Beş form (iletişim, online başvuru, sınav talebi, itiraz/şikâyet, iş
 * başvurusu) burada tanımlıdır. Her formun KAPALI alan allowlist'i vardır:
 * yalnız burada tanımlı alan adları işlenir, gönderilen başka her anahtar
 * yok sayılır (mass-assignment yok). Doğrulama `MaviBelge_Core_Forms_Validator`,
 * etkinleştirme kapısı `MaviBelge_Core_Forms_Config::gate()` tarafındadır.
 *
 * Alan türleri: text | email | tel | textarea | select | radio | file | consent.
 * `sensitive => true` alanlar (T.C. kimlik no, belge/CV yükleme) kurum onayı
 * olmadan ASLA etkinleşmez. Alan içeriği hiçbir zaman log/audit/URL'ye
 * yazılmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Schema {

	const FORM_IDS = array( 'contact', 'application', 'exam_request', 'complaint', 'job_application' );

	/** Bal küpü (honeypot) ve token alan adları — allowlist dışındadır, yalnız güvenlik amaçlıdır. */
	const HONEYPOT_FIELD = 'mb_hp_url';
	const TOKEN_FIELD    = 'mb_token';
	const FORM_FIELD     = 'mb_form';
	const NONCE_FIELD    = '_mb_nonce';

	/** Doğrulanmış sınav alanları (statik referanstaki üç seçenek; değerler tek yerde). */
	const EXAM_LOCATIONS = array(
		'iskenderun' => 'İskenderun / Hatay (Merkez)',
		'payas'      => 'Payas / Dörtyol / Hatay',
		'aliaga'     => 'Aliağa / İzmir',
	);

	const UPLOAD_MAX_BYTES = 5242880; // 5 MB
	const UPLOAD_MAX_FILES = 3;
	/** Uzantı => izinli MIME kümesi (finfo ile SUNUCU tarafında doğrulanır; istemci `type` alanına güvenilmez). */
	const UPLOAD_TYPES = array(
		'pdf'  => array( 'application/pdf' ),
		'jpg'  => array( 'image/jpeg' ),
		'jpeg' => array( 'image/jpeg' ),
		'png'  => array( 'image/png' ),
		'doc'  => array( 'application/msword' ),
		'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ),
	);

	/** @return array<string, array> */
	public static function all() {
		$consent = array( 'name' => 'consent', 'type' => 'consent', 'label' => 'KVKK Aydınlatma Metni\'ni okudum, kabul ediyorum.', 'required' => true );
		return array(
			'contact'         => array(
				'label'       => 'İletişim',
				'page_slug'   => 'iletisim',
				'sensitivity' => 'standard',
				'submit'      => 'Mesajı Gönder',
				'fields'      => array(
					array( 'name' => 'full_name', 'type' => 'text', 'label' => 'Ad Soyad', 'required' => true, 'max' => 100 ),
					array( 'name' => 'phone', 'type' => 'tel', 'label' => 'Telefon', 'required' => false, 'max' => 20 ),
					array( 'name' => 'email', 'type' => 'email', 'label' => 'E-posta', 'required' => true, 'max' => 120 ),
					array( 'name' => 'profession', 'type' => 'text', 'label' => 'İlgili Meslek (varsa)', 'required' => false, 'max' => 120 ),
					array( 'name' => 'message', 'type' => 'textarea', 'label' => 'Mesajınız', 'required' => true, 'max' => 2000 ),
					$consent,
				),
			),
			'application'     => array(
				'label'       => 'Online Başvuru',
				'page_slug'   => 'online-basvuru',
				'sensitivity' => 'sensitive',
				'submit'      => 'Başvuruyu Gönder',
				'fields'      => array(
					array( 'name' => 'qualification', 'type' => 'select', 'label' => 'Başvurulacak meslek/yeterlilik', 'required' => true, 'options_source' => 'qualifications' ),
					array( 'name' => 'exam_location', 'type' => 'select', 'label' => 'Tercih edilen sınav alanı', 'required' => true, 'options' => self::EXAM_LOCATIONS ),
					array( 'name' => 'full_name', 'type' => 'text', 'label' => 'Ad Soyad', 'required' => true, 'max' => 100 ),
					array( 'name' => 'national_id', 'type' => 'text', 'label' => 'T.C. Kimlik No', 'required' => true, 'max' => 11, 'sensitive' => true ),
					array( 'name' => 'phone', 'type' => 'tel', 'label' => 'Telefon', 'required' => true, 'max' => 20 ),
					array( 'name' => 'email', 'type' => 'email', 'label' => 'E-posta', 'required' => true, 'max' => 120 ),
					array( 'name' => 'documents', 'type' => 'file', 'label' => 'Kimlik ve varsa mesleğe özgü belgeler (PDF, JPG, PNG — en fazla 5 MB)', 'required' => false, 'sensitive' => true, 'multiple' => true ),
					$consent,
				),
			),
			'exam_request'    => array(
				'label'       => 'Sınav Talebi',
				'page_slug'   => 'sinav-talepleri',
				'sensitivity' => 'standard',
				'submit'      => 'Talebi Gönder',
				'fields'      => array(
					array( 'name' => 'request_type', 'type' => 'radio', 'label' => 'Talep türü', 'required' => true, 'options' => array( 'individual' => 'Bireysel', 'corporate' => 'Kurumsal' ) ),
					array( 'name' => 'full_name', 'type' => 'text', 'label' => 'Ad Soyad / Kurum Yetkilisi', 'required' => true, 'max' => 100 ),
					array( 'name' => 'company', 'type' => 'text', 'label' => 'Kurum/Firma Adı (kurumsal ise)', 'required' => false, 'max' => 150 ),
					array( 'name' => 'phone', 'type' => 'tel', 'label' => 'Telefon', 'required' => true, 'max' => 20 ),
					array( 'name' => 'email', 'type' => 'email', 'label' => 'E-posta', 'required' => true, 'max' => 120 ),
					array( 'name' => 'qualification', 'type' => 'select', 'label' => 'İlgili Meslek/Yeterlilik', 'required' => false, 'options_source' => 'qualifications' ),
					array( 'name' => 'candidate_count', 'type' => 'text', 'label' => 'Tahmini Aday Sayısı', 'required' => false, 'max' => 6, 'pattern' => 'digits' ),
					array( 'name' => 'message', 'type' => 'textarea', 'label' => 'Mesajınız', 'required' => false, 'max' => 2000 ),
					$consent,
				),
			),
			'complaint'       => array(
				'label'       => 'İtiraz ve Şikâyet',
				'page_slug'   => 'itiraz-sikayet',
				'sensitivity' => 'standard',
				'submit'      => 'Gönder',
				'fields'      => array(
					array( 'name' => 'notice_type', 'type' => 'radio', 'label' => 'Bildirim türü', 'required' => true, 'options' => array( 'objection' => 'İtiraz', 'complaint' => 'Şikâyet' ) ),
					array( 'name' => 'full_name', 'type' => 'text', 'label' => 'Ad Soyad', 'required' => true, 'max' => 100 ),
					array( 'name' => 'email', 'type' => 'email', 'label' => 'E-posta', 'required' => true, 'max' => 120 ),
					array( 'name' => 'subject', 'type' => 'text', 'label' => 'Konu', 'required' => true, 'max' => 150 ),
					array( 'name' => 'description', 'type' => 'textarea', 'label' => 'Açıklama', 'required' => true, 'max' => 3000 ),
					$consent,
				),
			),
			'job_application' => array(
				'label'       => 'İş Başvurusu',
				'page_slug'   => 'is-basvurusu',
				'sensitivity' => 'sensitive',
				'submit'      => 'Başvuruyu Gönder',
				'fields'      => array(
					array( 'name' => 'full_name', 'type' => 'text', 'label' => 'Ad Soyad', 'required' => true, 'max' => 100 ),
					array( 'name' => 'phone', 'type' => 'tel', 'label' => 'Telefon', 'required' => true, 'max' => 20 ),
					array( 'name' => 'email', 'type' => 'email', 'label' => 'E-posta', 'required' => true, 'max' => 120 ),
					array( 'name' => 'position', 'type' => 'text', 'label' => 'Başvurulan Pozisyon', 'required' => false, 'max' => 120 ),
					array( 'name' => 'cv', 'type' => 'file', 'label' => 'Özgeçmiş (CV) — PDF, DOC, DOCX, en fazla 5 MB', 'required' => true, 'sensitive' => true, 'multiple' => false, 'allow' => array( 'pdf', 'doc', 'docx' ) ),
					array( 'name' => 'message', 'type' => 'textarea', 'label' => 'Ön Yazı / Mesaj', 'required' => false, 'max' => 2000 ),
					$consent,
				),
			),
		);
	}

	/** @return array|null Bilinmeyen form -> null. */
	public static function get( $formId ) {
		$all = self::all();
		return is_string( $formId ) && isset( $all[ $formId ] ) ? $all[ $formId ] : null;
	}

	/** Sayfa slug'ından form kimliği; bilinmeyen -> null. */
	public static function form_id_for_page_slug( $slug ) {
		foreach ( self::all() as $id => $def ) {
			if ( $def['page_slug'] === $slug ) {
				return $id;
			}
		}
		return null;
	}

	/** @return string[] Bu formun İZİNLİ alan adları (kapalı allowlist; consent ve dosya alanları dahil). */
	public static function allowed_field_names( $formId ) {
		$def = self::get( $formId );
		return null === $def ? array() : array_map(
			function ( $f ) {
				return $f['name'];
			},
			$def['fields']
		);
	}

	/** Formda en az bir hassas alan var mı (T.C. kimlik no, belge/CV yükleme). */
	public static function has_sensitive_fields( $formId ) {
		$def = self::get( $formId );
		if ( null === $def ) {
			return false;
		}
		foreach ( $def['fields'] as $field ) {
			if ( ! empty( $field['sensitive'] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Formda dosya yükleme alanı var mı. */
	public static function has_file_fields( $formId ) {
		$def = self::get( $formId );
		if ( null === $def ) {
			return false;
		}
		foreach ( $def['fields'] as $field ) {
			if ( 'file' === $field['type'] ) {
				return true;
			}
		}
		return false;
	}
}
