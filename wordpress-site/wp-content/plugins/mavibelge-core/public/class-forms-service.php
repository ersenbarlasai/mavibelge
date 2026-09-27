<?php
/**
 * Faz 8 — form hizmeti (WordPress bağlantısı). İş kuralları SAF sınıflardadır:
 * Forms_Schema (şema/allowlist), Forms_Validator (doğrulama), Forms_Config
 * (yapılandırma + etkinleştirme kapısı), Forms_Security (jeton/oran sınırı/dosya),
 * Forms_Mail_Builder (ileti). Bu sınıf yalnız istekleri bağlar.
 *
 * Güvenlik akışı (bir gönderim için, sırayla; biri başarısızsa sonraki adım çalışmaz):
 *  1. Yalnız POST + geçerli form kimliği + formun KENDİ sayfası; kapı (gate) AÇIK olmalı.
 *  2. WordPress nonce (`_mb_nonce`, eylem `mb_form_<id>`).
 *  3. Bal küpü dolu -> sessiz sahte başarı (mail yok, sayaç yok).
 *  4. İmzalı jeton: imza, min/maks yaş, daha önce kullanılmamış.
 *  5. FAIL-CLOSED oran sınırı (istemci + form geneli). Sayaç yazılamazsa reddedilir.
 *  6. Sunucu tarafı doğrulama (allowlist alanlar); hatada aynı yanıtta yeniden çizim
 *     (girilen hassas olmayan değerler geri doldurulur; hassas alanlar ASLA).
 *  7. Dosyalar: uzantı+boyut+finfo MIME+rastgele ad; geçici özel dizin; e-postadan
 *     sonra HEMEN silinir (kalıcı saklama YOK).
 *  8. E-posta adaptörü ile gönderim. Başarıda: jeton tüketilir, PRG (303) yönlendirmesi.
 * Kişisel veri URL'ye, loga veya audit context'e YAZILMAZ: audit yalnız form kimliği ve
 * sabit sonuç kodu taşır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Service {

	/** Bu istekteki gönderim sonucu (şablon bunu okur). */
	private static $last = null;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_submission' ), 1 );
	}

	/* ---------------------------------------------------------- yapılandırma */

	public static function config() {
		return MaviBelge_Core_Forms_Config::sanitize( get_option( MaviBelge_Core_Forms_Config::OPTION_KEY, array() ) );
	}

	public static function save_config( $raw ) {
		$clean = MaviBelge_Core_Forms_Config::sanitize( $raw );
		update_option( MaviBelge_Core_Forms_Config::OPTION_KEY, $clean, false );
		return $clean;
	}

	/** Yönetim özeti: her form için kapı sonucu ve kapalıysa NEDENLERİ. */
	public static function admin_overview() {
		$config = self::config();
		$rows   = array();
		foreach ( MaviBelge_Core_Forms_Schema::FORM_IDS as $id ) {
			$def  = MaviBelge_Core_Forms_Schema::get( $id );
			$gate = MaviBelge_Core_Forms_Config::gate( $id, $config );
			$rows[ $id ] = array(
				'label'      => $def['label'],
				'page_slug'  => $def['page_slug'],
				'sensitive'  => MaviBelge_Core_Forms_Schema::has_sensitive_fields( $id ),
				'has_files'  => MaviBelge_Core_Forms_Schema::has_file_fields( $id ),
				'open'       => $gate['open'],
				'reasons'    => $gate['reasons'],
				'settings'   => $config['forms'][ $id ],
			);
		}
		return $rows;
	}

	/* ------------------------------------------------------------- şablon DTO */

	/**
	 * Şablon (tema) için kapalı DTO. Form KAPALIYSA alanlar yine döner (etiket önizlemesi), ama `open=false` ve
	 * gönderim düğmesi/jeton YOKTUR. Kapalı nedenleri yalnız `manage_options` yetkisi olan kullanıcıya gösterilmelidir
	 * (`reasons`), ziyaretçiye genel bir "şu anda kullanılamıyor" gösterilir.
	 *
	 * @return array|null Bilinmeyen sayfa slug'ı -> null.
	 */
	public static function describe_for_page( $pageSlug ) {
		$formId = MaviBelge_Core_Forms_Schema::form_id_for_page_slug( $pageSlug );
		return null === $formId ? null : self::describe( $formId );
	}

	public static function describe( $formId ) {
		$def = MaviBelge_Core_Forms_Schema::get( $formId );
		if ( null === $def ) {
			return null;
		}
		$config   = self::config();
		$gate     = MaviBelge_Core_Forms_Config::gate( $formId, $config );
		$settings = $config['forms'][ $formId ];
		$options  = self::dynamic_options( $def );
		$fields   = array();
		foreach ( $def['fields'] as $field ) {
			$out = array(
				'name'      => $field['name'],
				'type'      => $field['type'],
				'label'     => $field['label'],
				'required'  => ! empty( $field['required'] ),
				'max'       => isset( $field['max'] ) ? (int) $field['max'] : 0,
				'sensitive' => ! empty( $field['sensitive'] ),
				'multiple'  => ! empty( $field['multiple'] ),
				'options'   => array(),
			);
			if ( isset( $field['options'] ) ) {
				$out['options'] = $field['options'];
			} elseif ( isset( $field['options_source'] ) && isset( $options[ $field['options_source'] ] ) ) {
				$out['options'] = $options[ $field['options_source'] ];
			}
			if ( 'file' === $field['type'] ) {
				$allow          = isset( $field['allow'] ) ? $field['allow'] : array_keys( MaviBelge_Core_Forms_Schema::UPLOAD_TYPES );
				$out['accept']  = implode( ',', array_map( function ( $e ) {
					return '.' . $e;
				}, $allow ) );
			}
			if ( 'consent' === $field['type'] ) {
				$out['label'] = '' !== $settings['consent_text'] ? $settings['consent_text'] : $field['label'];
			}
			$fields[] = $out;
		}
		$result  = ( null !== self::$last && self::$last['form'] === $formId ) ? self::$last : null;
		$status  = null !== $result ? $result['status'] : ( self::success_from_query( $formId ) ? 'success' : '' );
		// Faz 12f: `?meslek=<MYK kodu>` yalnız online başvuruda ve yalnız POST sonucu YOKKEN ön seçim olur (doğrulama hatasıyla
		// yeniden çizimde kullanıcının POST ettiği değer önceliklidir). Değer yalnız herkese açık, tekil bir seçenek anahtarıyla
		// TAM eşleşirse kullanılır; aksi hâlde hiçbir şey seçilmez ve ham değer hiçbir yere taşınmaz.
		$preselect = array();
		if ( null === $result && 'application' === $formId ) {
			$requested = MaviBelge_Core_Forms_Qualification_Options::requested(
				isset( $_GET['meslek'] ) ? $_GET['meslek'] : null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput -- yalnız katı biçim + allowlist eşleşmesiyle okunur, durum değiştirmez.
				isset( $options['qualifications'] ) ? $options['qualifications'] : array()
			);
			if ( '' !== $requested ) {
				$preselect['qualification'] = $requested;
			}
		}
		return array(
			'id'            => $formId,
			'label'         => $def['label'],
			'submit_label'  => $def['submit'],
			'open'          => $gate['open'],
			'reasons'       => $gate['reasons'],
			'reason_labels' => array_map(
				function ( $code ) {
					return MaviBelge_Core_Forms_Config::REASON_LABELS[ $code ];
				},
				$gate['reasons']
			),
			'fields'        => $fields,
			'status'        => $status,
			'errors'        => null !== $result ? $result['errors'] : array(),
			'values'        => null !== $result ? $result['echo'] : array(),
			'preselect'     => $preselect,
			'token'         => $gate['open'] ? self::issue_token( $formId ) : '',
			'names'         => array(
				'form'     => MaviBelge_Core_Forms_Schema::FORM_FIELD,
				'token'    => MaviBelge_Core_Forms_Schema::TOKEN_FIELD,
				'honeypot' => MaviBelge_Core_Forms_Schema::HONEYPOT_FIELD,
				'nonce'    => MaviBelge_Core_Forms_Schema::NONCE_FIELD,
			),
			'nonce_action'  => 'mb_form_' . $formId,
		);
	}

	private static function success_from_query( $formId ) {
		return isset( $_GET['mb_form_status'], $_GET['mb_form'] ) && 'success' === $_GET['mb_form_status'] && $formId === $_GET['mb_form']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız başarı iletisini gösterir; durum değiştirmez.
	}

	/** Dinamik seçenek kaynakları (yalnız herkese açık, aktif yeterlilikler; en çok 200). @return array<string, array<string,string>> */
	private static function dynamic_options( array $def ) {
		$needs = false;
		foreach ( $def['fields'] as $field ) {
			if ( isset( $field['options_source'] ) && 'qualifications' === $field['options_source'] ) {
				$needs = true;
			}
		}
		if ( ! $needs || ! post_type_exists( 'mb_yeterlilik' ) ) {
			return array( 'qualifications' => array() );
		}
		$ids = get_posts(
			array(
				'post_type'        => 'mb_yeterlilik',
				'post_status'      => 'publish',
				'posts_per_page'   => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		$rows = array();
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			$rows[] = array(
				'code'   => (string) get_post_meta( $id, '_mb_myk_code', true ),
				'title'  => get_the_title( $id ),
				'level'  => (string) get_post_meta( $id, '_mb_level', true ),
				'public' => MaviBelge_Core_Visibility_Guard::is_public_qualification( $id ),
			);
		}
		// Faz 12f: etiket "{Ad} — {Kod} (Seviye {N})"; aynı koda iki herkese açık kayıt varsa kod dışlanır (fail-closed).
		return array( 'qualifications' => MaviBelge_Core_Forms_Qualification_Options::build( $rows ) );
	}

	/* ------------------------------------------------------------ gönderim */

	public static function last_result() {
		return self::$last;
	}

	/** Testler için: bu isteğin sonucunu temizler. */
	public static function reset() {
		self::$last = null;
	}

	public static function maybe_handle_submission() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! is_page() ) {
			return;
		}
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce aşağıda doğrulanır.
		$formId = isset( $post[ MaviBelge_Core_Forms_Schema::FORM_FIELD ] ) && is_string( $post[ MaviBelge_Core_Forms_Schema::FORM_FIELD ] ) ? $post[ MaviBelge_Core_Forms_Schema::FORM_FIELD ] : '';
		$def    = MaviBelge_Core_Forms_Schema::get( $formId );
		if ( null === $def || get_post_field( 'post_name', get_queried_object_id() ) !== $def['page_slug'] ) {
			return;
		}
		$files  = isset( $_FILES ) && is_array( $_FILES ) ? $_FILES : array();
		$result = self::process( $formId, $post, $files, self::mailer(), time() );
		self::$last = $result;
		if ( 'success' === $result['status'] ) {
			$url = add_query_arg( array( 'mb_form_status' => 'success', MaviBelge_Core_Forms_Schema::FORM_FIELD => $formId ), get_permalink( get_queried_object_id() ) );
			wp_safe_redirect( $url, 303 ); // PRG: yenileme yeniden gönderim yapmaz; URL kişisel veri taşımaz.
			exit;
		}
		if ( 'validation' !== $result['status'] ) {
			status_header( 'unavailable' === $result['status'] ? 503 : ( 'rate_limited' === $result['status'] ? 429 : 400 ) );
			nocache_headers();
		}
	}

	/**
	 * Bir gönderimi işler (WordPress nonce'u dışında saf bağımlılıklar enjekte edilir; testlerde doğrudan çağrılır).
	 *
	 * @param string                       $formId
	 * @param array                        $post   unslash edilmiş POST
	 * @param array                        $files  $_FILES biçimli
	 * @param MaviBelge_Core_Forms_Mailer  $mailer
	 * @param int                          $now
	 * @return array{form: string, status: string, errors: array, echo: array}
	 *   status: success | validation | unavailable | invalid_request | rate_limited | send_failed
	 */
	public static function process( $formId, array $post, array $files, MaviBelge_Core_Forms_Mailer $mailer, $now ) {
		$res = function ( $status, array $errors = array(), array $echo = array() ) use ( $formId ) {
			return array( 'form' => $formId, 'status' => $status, 'errors' => $errors, 'echo' => $echo );
		};
		$config   = self::config();
		$settings = MaviBelge_Core_Forms_Config::open_settings( $formId, $config );
		if ( null === $settings ) {
			self::audit( MaviBelge_Core_Audit_Log::EVENT_FORM_REJECTED, $formId, 'gate_closed' );
			return $res( 'unavailable' );
		}
		$nonce = isset( $post[ MaviBelge_Core_Forms_Schema::NONCE_FIELD ] ) && is_string( $post[ MaviBelge_Core_Forms_Schema::NONCE_FIELD ] ) ? $post[ MaviBelge_Core_Forms_Schema::NONCE_FIELD ] : '';
		if ( false === wp_verify_nonce( $nonce, 'mb_form_' . $formId ) ) {
			self::audit( MaviBelge_Core_Audit_Log::EVENT_FORM_REJECTED, $formId, 'bad_nonce' );
			return $res( 'invalid_request', array( '_form' => 'Oturum süresi doldu; sayfayı yenileyip tekrar deneyin.' ) );
		}
		if ( MaviBelge_Core_Forms_Security::honeypot_triggered( $post ) ) {
			self::audit( MaviBelge_Core_Audit_Log::EVENT_FORM_REJECTED, $formId, 'honeypot' );
			return $res( 'success' ); // Bota bilgi vermeyen sahte başarı; e-posta gönderilmez.
		}
		$token = isset( $post[ MaviBelge_Core_Forms_Schema::TOKEN_FIELD ] ) ? $post[ MaviBelge_Core_Forms_Schema::TOKEN_FIELD ] : '';
		$check = MaviBelge_Core_Forms_Security::verify_token( $formId, $token, self::secret(), $now );
		if ( ! $check['ok'] || false !== get_transient( 'mbf_used_' . $check['id'] ) ) {
			self::audit( MaviBelge_Core_Audit_Log::EVENT_FORM_REJECTED, $formId, $check['ok'] ? 'token_replay' : $check['reason'] );
			return $res( 'invalid_request', array( '_form' => 'Form süresi doldu; sayfayı yenileyip tekrar deneyin.' ) );
		}
		$rate = self::rate_check( $formId, $config['rate_limit'] );
		if ( 'ok' !== $rate ) {
			self::audit( MaviBelge_Core_Audit_Log::EVENT_FORM_REJECTED, $formId, 'rate_' . $rate );
			return $res( 'rate_limited', array( '_form' => 'Çok fazla deneme yapıldı; lütfen biraz sonra tekrar deneyin.' ) );
		}
		$context   = array( 'options' => self::dynamic_options( MaviBelge_Core_Forms_Schema::get( $formId ) ) );
		$validated = MaviBelge_Core_Forms_Validator::validate( $formId, $post, $context );
		$upload    = self::collect_uploads( $formId, $files );
		$errors    = array_merge( $validated['errors'], $upload['errors'] );
		if ( ! empty( $errors ) ) {
			self::cleanup( $upload['paths'] );
			return $res( 'validation', $errors, $validated['echo'] );
		}
		$message = MaviBelge_Core_Forms_Mail_Builder::build( $formId, $validated['clean'], $settings, $upload['paths'] );
		$sent    = null !== $message && $mailer->send( $message );
		self::cleanup( $upload['paths'] ); // Kalıcı saklama YOK: e-postadan hemen sonra (başarılı ya da değil) silinir.
		if ( ! $sent ) {
			self::audit( MaviBelge_Core_Audit_Log::EVENT_FORM_REJECTED, $formId, 'send_failed' );
			return $res( 'send_failed', array( '_form' => 'Gönderim şu anda tamamlanamadı; lütfen daha sonra tekrar deneyin.' ), $validated['echo'] );
		}
		set_transient( 'mbf_used_' . $check['id'], 1, MaviBelge_Core_Forms_Security::TOKEN_MAX_AGE );
		self::audit( MaviBelge_Core_Audit_Log::EVENT_FORM_SUBMITTED, $formId, 'ok' );
		return $res( 'success' );
	}

	private static function mailer() {
		$mailer = apply_filters( 'mavibelge_core_forms_mailer', new MaviBelge_Core_Forms_Wp_Mailer() );
		return $mailer instanceof MaviBelge_Core_Forms_Mailer ? $mailer : new MaviBelge_Core_Forms_Wp_Mailer();
	}

	private static function secret() {
		return wp_salt( 'auth' ) . '|mavibelge-forms';
	}

	private static function issue_token( $formId ) {
		return MaviBelge_Core_Forms_Security::issue_token( $formId, self::secret(), time(), bin2hex( random_bytes( 8 ) ) );
	}

	/**
	 * Oran sınırı: sayaç mantığı TEK altyapıdadır (MaviBelge_Core_Rate_Limit); burada yalnız anahtarlar kurulur.
	 * Herhangi bir sayaç okunamaz/yazılamazsa 'unavailable' (FAIL-CLOSED).
	 */
	private static function rate_check( $formId, array $limits ) {
		$client = self::client_hash();
		$ck     = 'mbf_rc_' . substr( hash( 'sha256', $formId . '|' . $client ), 0, 32 );
		$gk     = 'mbf_rg_' . $formId;
		return MaviBelge_Core_Rate_Limit::check_and_hit( new MaviBelge_Core_Rate_Limit_Transient_Store(), $ck, $gk, $limits );
	}

	/** İstemci kimliği: yalnız REMOTE_ADDR (vekil başlıklarına güvenilmez), HAM IP saklanmaz — HMAC özeti. */
	private static function client_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		return MaviBelge_Core_Rate_Limit::client_id( $ip, self::secret() );
	}

	/**
	 * @return array{errors: array<string,string>, paths: string[]}
	 */
	private static function collect_uploads( $formId, array $files ) {
		$def    = MaviBelge_Core_Forms_Schema::get( $formId );
		$errors = array();
		$paths  = array();
		foreach ( $def['fields'] as $field ) {
			if ( 'file' !== $field['type'] ) {
				continue;
			}
			$name       = $field['name'];
			$descriptors = isset( $files[ $name ] ) && is_array( $files[ $name ] ) ? self::normalize_files( $files[ $name ] ) : array();
			$descriptors = array_values( array_filter( $descriptors, function ( $f ) {
				return isset( $f['error'] ) && UPLOAD_ERR_NO_FILE !== $f['error'];
			} ) );
			if ( empty( $descriptors ) ) {
				if ( ! empty( $field['required'] ) ) {
					$errors[ $name ] = 'Bu alan zorunludur.';
				}
				continue;
			}
			$max = ! empty( $field['multiple'] ) ? MaviBelge_Core_Forms_Schema::UPLOAD_MAX_FILES : 1;
			if ( count( $descriptors ) > $max ) {
				$errors[ $name ] = 'Çok fazla dosya seçildi.';
				continue;
			}
			$allow = isset( $field['allow'] ) ? $field['allow'] : array_keys( MaviBelge_Core_Forms_Schema::UPLOAD_TYPES );
			foreach ( $descriptors as $descriptor ) {
				$tmp  = isset( $descriptor['tmp_name'] ) && is_string( $descriptor['tmp_name'] ) ? $descriptor['tmp_name'] : '';
				$mime = '' !== $tmp && is_readable( $tmp ) && function_exists( 'finfo_open' ) ? self::detect_mime( $tmp ) : null;
				$check = MaviBelge_Core_Forms_Security::validate_upload( $descriptor, $allow, $mime, '' !== $tmp && is_uploaded_file( $tmp ) );
				if ( ! $check['ok'] ) {
					$errors[ $name ] = 'Dosya kabul edilmedi (tür, boyut veya biçim uygun değil).';
					break;
				}
				$stored = self::store_temp( $tmp, $check['ext'] );
				if ( null === $stored ) {
					$errors[ $name ] = 'Dosya işlenemedi; lütfen tekrar deneyin.';
					break;
				}
				$paths[] = $stored;
			}
		}
		if ( ! empty( $errors ) ) {
			self::cleanup( $paths );
			$paths = array();
		}
		return array( 'errors' => $errors, 'paths' => $paths );
	}

	/** PHP'nin çok-dosya diziliminden (name[0]...) betimleyici listesine. */
	private static function normalize_files( array $entry ) {
		if ( ! isset( $entry['name'] ) ) {
			return array();
		}
		if ( ! is_array( $entry['name'] ) ) {
			return array( $entry );
		}
		$out = array();
		foreach ( array_keys( $entry['name'] ) as $i ) {
			$out[] = array(
				'name'     => isset( $entry['name'][ $i ] ) ? $entry['name'][ $i ] : '',
				'tmp_name' => isset( $entry['tmp_name'][ $i ] ) ? $entry['tmp_name'][ $i ] : '',
				'error'    => isset( $entry['error'][ $i ] ) ? (int) $entry['error'][ $i ] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $entry['size'][ $i ] ) ? (int) $entry['size'][ $i ] : 0,
			);
		}
		return $out;
	}

	private static function detect_mime( $path ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( false === $finfo ) {
			return null;
		}
		$mime = finfo_file( $finfo, $path );
		finfo_close( $finfo );
		return is_string( $mime ) ? $mime : null;
	}

	/** Geçici, dışarıdan erişilemeyen dizine rastgele adla kopyalar. */
	private static function store_temp( $tmp, $ext ) {
		$dir = self::temp_dir();
		if ( null === $dir ) {
			return null;
		}
		$name = MaviBelge_Core_Forms_Security::random_filename( $ext, bin2hex( random_bytes( 16 ) ) );
		if ( null === $name ) {
			return null;
		}
		$target = $dir . '/' . $name;
		if ( ! move_uploaded_file( $tmp, $target ) ) {
			return null;
		}
		chmod( $target, 0600 );
		return $target;
	}

	/** wp-content/uploads/mavibelge-forms-tmp: doğrudan erişim ve dizin listeleme kapalı (.htaccess + index.php). */
	private static function temp_dir() {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}
		$dir = $uploads['basedir'] . '/mavibelge-forms-tmp';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "# Doğrudan erişim ve betik yürütme kapalı.\nRequire all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\nOptions -Indexes -ExecCGI\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Sessizlik altındır.\n" );
		}
		return $dir;
	}

	private static function cleanup( array $paths ) {
		foreach ( $paths as $path ) {
			if ( is_string( $path ) && is_file( $path ) ) {
				unlink( $path );
			}
		}
	}

	/** Audit YALNIZ form kimliği ve sabit sonuç kodu taşır; kişisel veri asla. */
	private static function audit( $event, $formId, $code ) {
		if ( class_exists( 'MaviBelge_Core_Audit_Log' ) ) {
			MaviBelge_Core_Audit_Log::record( $event, 'mb_form', 0, array( 'form' => $formId, 'result' => $code ) );
		}
	}
}
