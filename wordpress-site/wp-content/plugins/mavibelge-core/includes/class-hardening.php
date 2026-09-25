<?php
/**
 * Faz 10 — sertleştirme: XML-RPC kapalı, anonim REST kapalı, kullanıcı/yazar numaralandırma kapalı, genel giriş hata
 * mesajı ve oturum açma denemelerinde oran sınırı, güvenli HTTP başlıkları alt kümesi.
 *
 * SAF kararlar (WordPress fonksiyonu çağırmaz; birim testleri): `route_allowed()`, `generic_login_failure()`,
 * `login_client_key()`, `login_limits()`, `security_headers()`.
 * WordPress bağlantısı: `init()` kancaları. Hiçbir ayar/veri DEĞİŞTİRİLMEZ (yalnız filtre/kanca).
 *
 * HSTS ve CSP BİLEREK PHP'de etkinleştirilmez (HTTPS/CDN doğrulanmadı) — bkz. deploy/security-headers.htaccess.template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Hardening {

	const LOGIN_MESSAGE         = 'Kullanıcı adı veya parola hatalı.';
	const LOGIN_LIMITED_MESSAGE = 'Çok fazla başarısız deneme yapıldı; lütfen daha sonra tekrar deneyin.';

	/** Kimlik bilgisi hatası sayılan WordPress hata kodları (hepsi TEK genel mesaja çevrilir). */
	const CREDENTIAL_ERROR_CODES = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'empty_username', 'empty_password', 'authentication_failed' );

	/** 10 başarısız / 15 dk / istemci; genel (global) sınır YOK. */
	const LOGIN_MAX_FAILURES = 10;
	const LOGIN_WINDOW       = 900;

	const LOGIN_LIMITED_CODE     = 'mb_login_limited';
	const LOGIN_UNAVAILABLE_CODE = 'mb_login_unavailable';
	const LOGIN_FAILED_CODE      = 'mb_login_failed';

	/* ------------------------------------------------------------- SAF kısım */

	/**
	 * Anonim REST isteğine izin verilen yol mu? Allowlist ön ekleri `/ad-alani/v1` biçimindedir; tam eşleşme veya
	 * `/` ile ayrılan alt yol eşleşir. Boş allowlist (varsayılan) = hiçbir anonim yol.
	 *
	 * @param mixed $route     `/wp/v2/posts` gibi yol
	 * @param mixed $allowlist dizi
	 */
	public static function route_allowed( $route, $allowlist ) {
		if ( ! is_string( $route ) || '' === $route || ! is_array( $allowlist ) ) {
			return false;
		}
		$route = '/' . trim( $route, '/' );
		foreach ( $allowlist as $prefix ) {
			if ( ! is_string( $prefix ) || '' === trim( $prefix, '/' ) ) {
				continue;
			}
			$prefix = '/' . trim( $prefix, '/' );
			if ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Bu hata kodlarından biri kimlik bilgisi hatası mı? (Hesap var/yok bilgisini sızdırmamak için tek mesaj.) */
	public static function is_credential_failure( array $codes ) {
		return count( array_intersect( $codes, self::CREDENTIAL_ERROR_CODES ) ) > 0;
	}

	/** Oturum açma oran sınırı limitleri (Rate_Limit biçimi; genel sınır yok). */
	public static function login_limits() {
		return array(
			'per_client'    => self::LOGIN_MAX_FAILURES,
			'window'        => self::LOGIN_WINDOW,
			'global'        => PHP_INT_MAX,
			'global_window' => self::LOGIN_WINDOW,
		);
	}

	/** İstemci sayacı anahtarı (HAM IP içermez; yalnız HMAC özetinden türer). */
	public static function login_client_key( $clientId ) {
		return 'mbl_rc_' . substr( hash( 'sha256', 'login|' . (string) $clientId ), 0, 32 );
	}

	/**
	 * Güvenli HTTP başlıkları alt kümesi (SAF). HSTS/CSP YOK.
	 *
	 * @return array<string,string>
	 */
	public static function security_headers() {
		return array(
			'X-Content-Type-Options' => 'nosniff',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
		);
	}

	/* -------------------------------------------------- WordPress bağlantısı */

	public static function init() {
		// XML-RPC: hem çekirdek anahtarı hem erken çıkış; pingback başlığı kaldırılır.
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_headers', array( __CLASS__, 'filter_wp_headers' ) );
		add_filter( 'login_headers', array( __CLASS__, 'filter_wp_headers' ) );
		add_action( 'init', array( __CLASS__, 'block_xmlrpc_request' ), 0 );
		add_action( 'send_headers', array( __CLASS__, 'remove_powered_by' ) );

		// REST: anonim kapalı; kullanıcı uç noktaları yetkisiz oturumlarda YOK.
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'filter_rest_authentication' ), 101 );
		add_filter( 'rest_endpoints', array( __CLASS__, 'filter_rest_endpoints' ) );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );

		// Kullanıcı/yazar numaralandırma.
		add_action( 'template_redirect', array( __CLASS__, 'block_author_enumeration' ), 0 );

		// Giriş: genel hata mesajı + oran sınırı.
		// Çekirdek parola denetimi (öncelik 20) daha önceki bir WP_Error'ı ezip WP_User döndürebildiğinden karar SON öncelikte verilir.
		add_filter( 'authenticate', array( __CLASS__, 'filter_authenticate_final' ), 99, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_login_failed' ), 10, 2 );
	}

	public static function filter_wp_headers( $headers ) {
		$headers = is_array( $headers ) ? $headers : array();
		unset( $headers['X-Pingback'] );
		$extra = apply_filters( 'mavibelge_core_security_headers', self::security_headers() );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $name => $value ) {
				if ( is_string( $name ) && is_string( $value ) && 1 === preg_match( '/^[A-Za-z][A-Za-z0-9-]*$/', $name ) && false === strpbrk( $value, "\r\n" ) ) {
					$headers[ $name ] = $value;
				}
			}
		}
		return $headers;
	}

	public static function remove_powered_by() {
		if ( ! headers_sent() && function_exists( 'header_remove' ) ) {
			header_remove( 'X-Powered-By' );
		}
	}

	/** xmlrpc.php isteği: ayrıntı vermeden 403 ile bitir (POST gövdesi işlenmez). */
	public static function block_xmlrpc_request() {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			if ( ! headers_sent() ) {
				status_header( 403 );
				header( 'Content-Type: text/plain; charset=utf-8' );
			}
			echo 'XML-RPC kapalı.';
			exit;
		}
	}

	/**
	 * Anonim (oturumsuz) REST isteği 401. Zaten karar verilmişse (çerez/nonce doğrulaması, uygulama parolası) ona dokunulmaz;
	 * oturum açmış kullanıcı ve yönetim/Gutenberg etkilenmez.
	 */
	public static function filter_rest_authentication( $result ) {
		// Çekirdek çerez denetimi nonce'suz anonim istekte `true` döndürür ("karar yok") — bu yüzden yalnız HATA ve oturum
		// durumuna bakılır: hata varsa olduğu gibi döner; geçerli oturum (çerez+nonce, uygulama parolası) varsa dokunulmaz.
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( is_user_logged_in() ) {
			return $result;
		}
		$allow = apply_filters( 'mavibelge_core_rest_public_routes', array() );
		if ( self::route_allowed( self::current_rest_route(), $allow ) ) {
			return $result;
		}
		return new WP_Error( 'rest_forbidden', 'REST API bu site için oturum gerektirir.', array( 'status' => 401 ) );
	}

	/** Kullanıcı uç noktaları `list_users` yetkisi olmayan oturumlarda kaldırılır (404). */
	public static function filter_rest_endpoints( $endpoints ) {
		if ( ! is_array( $endpoints ) || current_user_can( 'list_users' ) ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( is_string( $route ) && 1 === preg_match( '#^/wp/v2/users(/|$)#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	private static function current_rest_route() {
		global $wp;
		if ( isset( $wp ) && is_object( $wp ) && isset( $wp->query_vars['rest_route'] ) && is_string( $wp->query_vars['rest_route'] ) ) {
			return $wp->query_vars['rest_route'];
		}
		if ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız yol karşılaştırması; durum değiştirmez.
			return wp_unslash( $_GET['rest_route'] );
		}
		return '';
	}

	/** `?author=N`, `/author/…` ve yazar arşivi: 404 (yönlendirme sızıntısı da yok: redirect_canonical'dan ÖNCE çalışır). */
	public static function block_author_enumeration() {
		global $wp_query;
		if ( is_admin() || ! is_object( $wp_query ) ) {
			return;
		}
		$is_author_request = is_author() || isset( $_GET['author'] ) || isset( $_GET['author_name'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız 404 kararı.
		if ( ! $is_author_request ) {
			return;
		}
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/* -------------------------------------------------------------- giriş */

	private static function login_store() {
		return new MaviBelge_Core_Rate_Limit_Transient_Store();
	}

	private static function login_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		return self::login_client_key( MaviBelge_Core_Rate_Limit::client_id( $ip, wp_salt( 'auth' ) . '|mavibelge-login' ) );
	}

	/**
	 * (1) Sınır aşıldıysa veya sayaç okunamıyorsa (FAIL-CLOSED) BAŞARILI kimlik doğrulamayı bile reddeder;
	 * (2) hesap var/yok bilgisini sızdıran hata kodlarını TEK genel mesaja indirger.
	 */
	public static function filter_authenticate_final( $user, $username = '', $password = '' ) {
		$attempt = ( is_string( $username ) && '' !== $username ) || ( is_string( $password ) && '' !== $password );
		if ( $attempt ) {
			$decision = MaviBelge_Core_Rate_Limit::peek( self::login_store(), self::login_key(), null, self::login_limits() );
			if ( 'unavailable' === $decision ) {
				return new WP_Error( self::LOGIN_UNAVAILABLE_CODE, self::LOGIN_MESSAGE );
			}
			if ( 'ok' !== $decision ) {
				return new WP_Error( self::LOGIN_LIMITED_CODE, self::LOGIN_LIMITED_MESSAGE );
			}
		}
		if ( ! is_wp_error( $user ) ) {
			return $user;
		}
		$codes = $user->get_error_codes();
		if ( self::is_credential_failure( is_array( $codes ) ? $codes : array() ) ) {
			return new WP_Error( self::LOGIN_FAILED_CODE, self::LOGIN_MESSAGE );
		}
		return $user;
	}

	/** Başarısız denemeyi sayar (sınırlama/erişilemezlik hataları ayrıca sayılmaz). */
	public static function on_login_failed( $username, $error = null ) {
		if ( ! is_string( $username ) || '' === $username ) {
			return; // boş form gönderimi deneme sayılmaz
		}
		if ( is_wp_error( $error ) ) {
			$codes = $error->get_error_codes();
			if ( in_array( self::LOGIN_LIMITED_CODE, $codes, true ) || in_array( self::LOGIN_UNAVAILABLE_CODE, $codes, true ) ) {
				return;
			}
		}
		MaviBelge_Core_Rate_Limit::hit( self::login_store(), self::login_key(), null, self::login_limits() );
	}
}
