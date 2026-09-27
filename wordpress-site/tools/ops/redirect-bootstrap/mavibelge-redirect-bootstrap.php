<?php
/**
 * Plugin Name: Mavi Belge — Geçici Yönlendirme Deposu Kurulumu
 * Description: TEK KULLANIMLIK operasyon aracı. Yetkili yönlendirme manifestini (29 kural / 4 etkin) Mavi Belge Core'un kendi doğrulama ve kayıt yoluyla `mavibelge_core_redirects` deposuna yazar. İşlem bitince bu dosya ve redirects.manifest.json File Manager'dan silinmelidir.
 * Version: 1.0.0
 * Requires PHP: 7.3
 *
 * Güvenlik modeli (özet; ayrıntı: wordpress-site/tools/ops/redirect-bootstrap/README.md):
 * - Doğrudan web çağrısında çalışmaz (ABSPATH yok -> exit).
 * - Kamu isteğinde (is_admin() false) hiçbir kanca kaydetmez, hiçbir şey okumaz/yazmaz.
 * - Kendiliğinden çalışmaz: yalnız wp-admin'de, manage_options yetkisiyle, nonce korumalı POST eylemiyle.
 * - Manifest SHA-256 sabit değerle doğrulanır; depo boş değilse işlem reddedilir; kurallar yalnız
 *   MaviBelge_Core_Redirects_Rules::validate_set() + MaviBelge_Core_Redirects_Service::save_rules() ile yazılır.
 * - Tema, eklenti, form, SMTP, içerik, .htaccess ve kalıcı bağlantı ayarına dokunmaz; dosya yazmaz/silmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MaviBelge_Redirect_Bootstrap', false ) ) {

	final class MaviBelge_Redirect_Bootstrap {

		const VERSION            = '1.0.0';
		const MANIFEST_FILE      = 'redirects.manifest.json';
		const MANIFEST_SHA256 = 'ebd84ce828efbc1b12490b79b838d6534c70141bf04da849aab7070d34597792';
		const MANIFEST_MAX_BYTES = 262144;
		const SCHEMA_VERSION     = '1.0.0';
		const RECORD_TYPE        = 'redirect_rules';
		const EXPECTED_TOTAL     = 29;
		const EXPECTED_ACTIVE    = 4;
		const STORE_OPTION       = 'mavibelge_core_redirects';
		const STATE_OPTION       = 'mavibelge_redirect_bootstrap_state';
		const CAPABILITY         = 'manage_options';
		const PAGE_SLUG          = 'mavibelge-redirect-bootstrap';
		const ACTION_IMPORT      = 'mavibelge_redirect_bootstrap_import';
		const ACTION_ROLLBACK    = 'mavibelge_redirect_bootstrap_rollback';
		const NONCE_FIELD        = 'mb_rb_nonce';
		const RESULT_PARAM       = 'mb_rb_result';
		const RULES_CLASS        = 'MaviBelge_Core_Redirects_Rules';
		const SERVICE_CLASS      = 'MaviBelge_Core_Redirects_Service';

		/** Kamu isteğinde hiçbir şey yapmaz; yönetimde yalnız menü, bildirim ve iki oturumlu POST eylemi kaydeder. */
		public static function boot() {
			if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
				return;
			}
			add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
			add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
			add_action( 'admin_post_' . self::ACTION_IMPORT, array( __CLASS__, 'handle_import' ) );
			add_action( 'admin_post_' . self::ACTION_ROLLBACK, array( __CLASS__, 'handle_rollback' ) );
		}

		public static function default_manifest_path() {
			return __DIR__ . '/' . self::MANIFEST_FILE;
		}

		/* ------------------------------------------------------------------ */
		/* İşlemler                                                            */
		/* ------------------------------------------------------------------ */

		/** Üretim girişi: yalnız sabit manifest yolu ve sabit SHA-256 kullanılır. */
		public static function import( array $request ) {
			return self::import_from( $request, self::default_manifest_path(), self::MANIFEST_SHA256 );
		}

		/**
		 * Import. İlk başarısız kapıda durur; o ana kadar depoya HİÇBİR şey yazılmaz.
		 *
		 * @return array{ok: bool, code: string, total: int, active: int}
		 */
		public static function import_from( array $request, $manifest_path, $expected_sha256 ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return self::result( 'forbidden' );
			}
			if ( ! self::nonce_ok( $request, self::ACTION_IMPORT ) ) {
				return self::result( 'nonce_invalid' );
			}
			if ( ! self::core_available() ) {
				return self::result( 'core_unavailable' );
			}
			$state = self::state();
			if ( null !== $state && in_array( $state['state'], array( 'importing', 'imported' ), true ) ) {
				return self::result( 'already_imported' );
			}
			$original = self::store_emptiness();
			if ( null === $original ) {
				return self::result( 'store_not_empty' );
			}

			$loaded = self::load_manifest( $manifest_path, $expected_sha256 );
			if ( true !== $loaded['ok'] ) {
				return self::result( $loaded['code'] );
			}
			$rules = $loaded['rules'];

			$check = call_user_func( array( self::RULES_CLASS, 'validate_set' ), $rules );
			if ( ! is_array( $check ) || true !== $check['valid'] ) {
				return self::result( 'rules_invalid' );
			}
			foreach ( $rules as $rule ) {
				if ( true !== $rule['active'] ) {
					continue;
				}
				if ( 'verified' !== $rule['origin'] ) {
					return self::result( 'active_rule_not_verified' );
				}
				$target = call_user_func( array( self::RULES_CLASS, 'normalize_path' ), $rule['target'] );
				if ( 410 !== $rule['status'] && ( null === $target || true !== (bool) call_user_func( array( self::SERVICE_CLASS, 'target_exists' ), $target ) ) ) {
					return self::result( 'target_missing' );
				}
			}
			$digest  = call_user_func( array( self::RULES_CLASS, 'digest' ), $rules );
			$content = self::content_hash( $rules );

			// Son an kontrolü: depo hâlâ aynı boş durumda mı?
			if ( $original !== self::store_emptiness() ) {
				return self::result( 'store_not_empty' );
			}

			// Önce durum kaydı: depo yazıldıysa rollback her zaman kökenini kanıtlayabilsin.
			if ( ! self::write_state( 'importing', $digest, $content, $original ) ) {
				return self::result( 'state_write_failed' );
			}

			$saved = call_user_func( array( self::SERVICE_CLASS, 'save_rules' ), $rules );
			if ( ! is_array( $saved ) || true !== $saved['ok'] ) {
				self::restore_store( $original );
				delete_option( self::STATE_OPTION );
				return self::result( 'save_failed' );
			}

			$counts = self::stored_counts();
			if ( self::EXPECTED_TOTAL !== $counts['total'] || self::EXPECTED_ACTIVE !== $counts['active'] || $digest !== $counts['digest'] || $content !== $counts['content'] ) {
				self::restore_store( $original );
				delete_option( self::STATE_OPTION );
				return self::result( 'verify_failed' );
			}

			self::write_state( 'imported', $digest, $content, $original );
			return self::result( 'imported', true, $counts['total'], $counts['active'] );
		}

		/**
		 * Rollback: yalnız bu loader'ın boş başlangıçtan oluşturduğu ve o andan beri değişmemiş depoyu başlangıç
		 * durumuna döndürür.
		 */
		public static function rollback( array $request ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return self::result( 'forbidden' );
			}
			if ( ! self::nonce_ok( $request, self::ACTION_ROLLBACK ) ) {
				return self::result( 'nonce_invalid' );
			}
			if ( ! self::core_available() ) {
				return self::result( 'core_unavailable' );
			}
			$state = self::state();
			if ( null === $state || ! in_array( $state['state'], array( 'importing', 'imported' ), true ) || ! in_array( $state['original'], array( 'absent', 'empty_array' ), true ) || ! isset( $state['content_sha256'] ) ) {
				return self::result( 'rollback_not_allowed' );
			}
			// Tam içerik eşitliği (not alanı dahil): import sonrası herhangi bir değişiklik varsa depo korunur.
			$counts = self::stored_counts();
			if ( null === $counts['content'] || $counts['content'] !== $state['content_sha256'] || $counts['digest'] !== $state['digest'] ) {
				return self::result( 'store_changed' );
			}
			self::restore_store( $state['original'] );
			if ( $state['original'] !== self::store_emptiness() ) {
				return self::result( 'rollback_failed' );
			}
			self::write_state( 'rolled_back', $state['digest'], $state['content_sha256'], $state['original'] );
			return self::result( 'rolled_back', true, 0, 0 );
		}

		/* ------------------------------------------------------------------ */
		/* Yardımcılar                                                         */
		/* ------------------------------------------------------------------ */

		/** @return array{ok: bool, code: string, rules?: array} */
		private static function load_manifest( $path, $expected_sha256 ) {
			if ( ! is_string( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
				return array( 'ok' => false, 'code' => 'manifest_missing' );
			}
			$size = filesize( $path );
			if ( false === $size || $size <= 0 || $size > self::MANIFEST_MAX_BYTES ) {
				return array( 'ok' => false, 'code' => 'manifest_missing' );
			}
			$bytes = file_get_contents( $path );
			if ( ! is_string( $bytes ) || ! is_string( $expected_sha256 ) || ! hash_equals( strtolower( $expected_sha256 ), hash( 'sha256', $bytes ) ) ) {
				return array( 'ok' => false, 'code' => 'manifest_hash_mismatch' );
			}
			$data = json_decode( $bytes, true, 64 );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) || ( array() !== $data && array_values( $data ) === $data ) ) {
				return array( 'ok' => false, 'code' => 'manifest_json_invalid' );
			}
			if ( ! isset( $data['schema_version'], $data['record_type'] ) || self::SCHEMA_VERSION !== $data['schema_version'] || self::RECORD_TYPE !== $data['record_type'] ) {
				return array( 'ok' => false, 'code' => 'manifest_schema_invalid' );
			}
			if ( ! isset( $data['rules'] ) || ! is_array( $data['rules'] ) || array() === $data['rules'] || array_values( $data['rules'] ) !== $data['rules'] ) {
				return array( 'ok' => false, 'code' => 'manifest_rules_invalid' );
			}
			$rules  = $data['rules'];
			$active = 0;
			foreach ( $rules as $rule ) {
				if ( ! is_array( $rule ) || ! array_key_exists( 'active', $rule ) ) {
					return array( 'ok' => false, 'code' => 'rules_invalid' );
				}
				if ( true === $rule['active'] ) {
					$active++;
				}
			}
			if ( self::EXPECTED_TOTAL !== count( $rules ) || self::EXPECTED_ACTIVE !== $active ) {
				return array( 'ok' => false, 'code' => 'manifest_count_mismatch' );
			}
			return array( 'ok' => true, 'code' => 'ok', 'rules' => $rules );
		}

		/** Mavi Belge Core'un gerekli sınıf/yöntem/seçenek sözleşmesi hazır mı. */
		private static function core_available() {
			if ( ! class_exists( self::RULES_CLASS ) || ! class_exists( self::SERVICE_CLASS ) ) {
				return false;
			}
			$methods = array(
				array( self::RULES_CLASS, 'validate_set' ),
				array( self::RULES_CLASS, 'normalize_path' ),
				array( self::RULES_CLASS, 'digest' ),
				array( self::SERVICE_CLASS, 'save_rules' ),
				array( self::SERVICE_CLASS, 'rules' ),
				array( self::SERVICE_CLASS, 'target_exists' ),
			);
			foreach ( $methods as $method ) {
				if ( ! is_callable( $method ) ) {
					return false;
				}
			}
			return defined( self::SERVICE_CLASS . '::OPTION' ) && self::STORE_OPTION === constant( self::SERVICE_CLASS . '::OPTION' );
		}

		/** @return string|null 'absent' | 'empty_array' | null (boş değil / bozuk: işlem yapılmaz). */
		private static function store_emptiness() {
			$sentinel = 'mavibelge-redirect-bootstrap-absent';
			$value    = get_option( self::STORE_OPTION, $sentinel );
			if ( $sentinel === $value || false === $value ) {
				return 'absent';
			}
			if ( array() === $value ) {
				return 'empty_array';
			}
			return null;
		}

		private static function restore_store( $original ) {
			if ( 'empty_array' === $original ) {
				update_option( self::STORE_OPTION, array(), false );
				return;
			}
			delete_option( self::STORE_OPTION );
		}

		/** Kural listesinin tam içerik özeti (tüm alanlar ve sıra; Core digest() not alanını kapsamaz). */
		private static function content_hash( array $rules ) {
			return hash( 'sha256', (string) json_encode( $rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}

		/** @return array{total: int, active: int, digest: string|null, content: string|null} Depodan geri okunan gerçek değerler. */
		private static function stored_counts() {
			$none   = array( 'total' => 0, 'active' => 0, 'digest' => null, 'content' => null );
			$stored = get_option( self::STORE_OPTION, false );
			if ( ! is_array( $stored ) || array() === $stored || array_values( $stored ) !== $stored ) {
				return $none;
			}
			$active = 0;
			foreach ( $stored as $rule ) {
				if ( ! is_array( $rule ) ) {
					return $none;
				}
				if ( isset( $rule['active'] ) && true === $rule['active'] ) {
					$active++;
				}
			}
			return array( 'total' => count( $stored ), 'active' => $active, 'digest' => call_user_func( array( self::RULES_CLASS, 'digest' ), $stored ), 'content' => self::content_hash( $stored ) );
		}

		/** @return array|null Geçerli şekilli durum kaydı veya null. */
		private static function state() {
			$state = get_option( self::STATE_OPTION, null );
			if ( ! is_array( $state ) || ! isset( $state['state'], $state['digest'], $state['original'] ) || ! is_string( $state['state'] ) ) {
				return null;
			}
			return $state;
		}

		/** Durum kaydını yazar ve geri okuyarak doğrular. Kişisel veri içermez. */
		private static function write_state( $name, $digest, $content, $original ) {
			$value = array(
				'state'           => $name,
				'digest'          => $digest,
				'content_sha256'  => $content,
				'manifest_sha256' => self::MANIFEST_SHA256,
				'original'        => $original,
				'total'           => 'rolled_back' === $name ? 0 : self::EXPECTED_TOTAL,
				'active'          => 'rolled_back' === $name ? 0 : self::EXPECTED_ACTIVE,
				'loader_version'  => self::VERSION,
				'updated_at'      => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			);
			update_option( self::STATE_OPTION, $value, false );
			return get_option( self::STATE_OPTION, null ) === $value;
		}

		private static function nonce_ok( array $request, $action ) {
			if ( ! isset( $request[ self::NONCE_FIELD ] ) || ! is_string( $request[ self::NONCE_FIELD ] ) || '' === $request[ self::NONCE_FIELD ] ) {
				return false;
			}
			return false !== wp_verify_nonce( wp_unslash( $request[ self::NONCE_FIELD ] ), $action );
		}

		private static function result( $code, $ok = false, $total = 0, $active = 0 ) {
			return array( 'ok' => (bool) $ok, 'code' => (string) $code, 'total' => (int) $total, 'active' => (int) $active );
		}

		/** Sabit mesaj sözlüğü; istek verisi asla yansıtılmaz. */
		public static function message( $code ) {
			$messages = array(
				'imported'                 => 'Yönlendirme deposu oluşturuldu: toplam 29, etkin 4. Şimdi Araçlar → Mavi Belge Sağlık ekranını ve dört yönlendirmeyi doğrulayın; ardından mavibelge-redirect-bootstrap.php ve redirects.manifest.json dosyalarını File Manager ile silin.',
				'rolled_back'              => 'Geri alındı: yönlendirme deposu içe aktarma öncesindeki boş durumuna döndü. Sonucu kaydedin ve durun.',
				'forbidden'                => 'Yetkisiz: bu işlem yalnız yönetici (manage_options) yetkisiyle yapılabilir. Hiçbir şey yazılmadı.',
				'nonce_invalid'            => 'Güvenlik doğrulaması geçersiz veya süresi dolmuş. Sayfayı yenileyip yeniden deneyin. Hiçbir şey yazılmadı.',
				'bad_method'               => 'Bu işlem yalnız form düğmesiyle (POST) yapılabilir. Hiçbir şey yazılmadı.',
				'core_unavailable'         => 'Mavi Belge Core yönlendirme sınıfları kullanılamıyor (eklenti etkin değil veya sürüm uyumsuz). Hiçbir şey yazılmadı.',
				'already_imported'         => 'Bu araçla içe aktarma zaten yapıldı; yeni içe aktarma reddedildi. Hiçbir şey yazılmadı.',
				'store_not_empty'          => 'Yönlendirme deposu boş değil; mevcut veri korunmak için işlem reddedildi. Hiçbir şey yazılmadı.',
				'manifest_missing'         => 'redirects.manifest.json bu dosyayla aynı klasörde bulunamadı veya okunamadı. Hiçbir şey yazılmadı.',
				'manifest_hash_mismatch'   => 'Manifest SHA-256 doğrulaması başarısız (dosya değişmiş veya eksik yüklenmiş). Hiçbir şey yazılmadı.',
				'manifest_json_invalid'    => 'Manifest geçerli bir JSON nesnesi değil. Hiçbir şey yazılmadı.',
				'manifest_schema_invalid'  => 'Manifest şema sürümü veya kayıt türü beklenen değer değil. Hiçbir şey yazılmadı.',
				'manifest_rules_invalid'   => 'Manifestte geçerli ve boş olmayan bir kural listesi yok. Hiçbir şey yazılmadı.',
				'manifest_count_mismatch'  => 'Manifest 29 toplam / 4 etkin kural içermiyor. Hiçbir şey yazılmadı.',
				'rules_invalid'            => 'Kural kümesi Mavi Belge Core doğrulamasından geçmedi. Hiçbir şey yazılmadı.',
				'active_rule_not_verified' => 'Etkin kurallardan biri doğrulanmış (verified) değil. Hiçbir şey yazılmadı.',
				'target_missing'           => 'Etkin yönlendirmelerden birinin hedef sayfası sitede bulunamadı. Hiçbir şey yazılmadı.',
				'save_failed'              => 'Kayıt Mavi Belge Core tarafından reddedildi; depo başlangıç durumunda bırakıldı.',
				'verify_failed'            => 'Kayıt geri okunduğunda 29/4 doğrulanamadı; depo başlangıç durumuna döndürüldü.',
				'state_write_failed'       => 'İşlem durum kaydı yazılamadı; depoya hiçbir şey yazılmadı.',
				'rollback_not_allowed'     => 'Geri alma yapılamaz: depo bu araçla boş başlangıçtan oluşturulmamış veya zaten geri alınmış. Hiçbir şey değişmedi.',
				'store_changed'            => 'Geri alma reddedildi: depo içe aktarmadan sonra değiştirilmiş; değişiklik korunuyor. Hiçbir şey değişmedi.',
				'rollback_failed'          => 'Geri alma doğrulanamadı; Araçlar → Mavi Belge Sağlık ekranını kontrol edin.',
			);
			return is_string( $code ) && isset( $messages[ $code ] ) ? $messages[ $code ] : 'Bilinmeyen sonuç. Araçlar → Mavi Belge Sağlık ekranını kontrol edin.';
		}

		/* ------------------------------------------------------------------ */
		/* WordPress yönetim bağlantısı                                        */
		/* ------------------------------------------------------------------ */

		public static function register_page() {
			add_management_page( 'Yönlendirme deposu kurulumu', 'Yönlendirme deposu kurulumu', self::CAPABILITY, self::PAGE_SLUG, array( __CLASS__, 'render_page' ) );
		}

		public static function handle_import() {
			self::finish( self::guarded( 'import' ) );
		}

		public static function handle_rollback() {
			self::finish( self::guarded( 'rollback' ) );
		}

		private static function guarded( $operation ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return self::result( 'forbidden' );
			}
			$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : '';
			if ( 'POST' !== $method ) {
				return self::result( 'bad_method' );
			}
			$request = is_array( $_POST ) ? $_POST : array();
			return 'rollback' === $operation ? self::rollback( $request ) : self::import( $request );
		}

		private static function finish( array $result ) {
			if ( 'forbidden' === $result['code'] ) {
				wp_die( esc_html( self::message( 'forbidden' ) ), 403 );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, self::RESULT_PARAM => $result['code'] ), admin_url( 'tools.php' ) ) );
			exit;
		}

		public static function render_notice() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return;
			}
			$url = add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'tools.php' ) );
			echo '<div class="notice notice-warning"><p><strong>Geçici yönlendirme deposu kurulum aracı etkin.</strong> <a href="' . esc_url( $url ) . '">Araçlar → Yönlendirme deposu kurulumu</a>. İşlem doğrulandıktan sonra <code>wp-content/mu-plugins/</code> içindeki <code>mavibelge-redirect-bootstrap.php</code> ve <code>redirects.manifest.json</code> dosyalarını silin.</p></div>';
		}

		public static function render_page() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return;
			}
			$core    = self::core_available();
			$state   = self::state();
			$empty   = self::store_emptiness();
			$counts  = $core ? self::stored_counts() : array( 'total' => 0, 'active' => 0, 'digest' => null, 'content' => null );
			$done    = null !== $state && in_array( $state['state'], array( 'importing', 'imported' ), true );
			$raw     = isset( $_GET[ self::RESULT_PARAM ] ) && is_string( $_GET[ self::RESULT_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_PARAM ] ) ) : '';
			$action  = admin_url( 'admin-post.php' );

			echo '<div class="wrap"><h1>Yönlendirme deposu kurulumu (geçici)</h1>';
			if ( '' !== $raw ) {
				$success = in_array( $raw, array( 'imported', 'rolled_back' ), true );
				echo '<div class="notice ' . ( $success ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( self::message( $raw ) ) . '</p></div>';
			}
			echo '<table class="widefat striped" style="max-width:720px"><tbody>';
			echo '<tr><th>Mavi Belge Core yönlendirme sınıfları</th><td>' . ( $core ? 'Hazır' : 'Kullanılamıyor' ) . '</td></tr>';
			echo '<tr><th>Depo durumu</th><td>' . esc_html( null === $empty ? 'Dolu — toplam ' . $counts['total'] . ', etkin ' . $counts['active'] : 'Boş' ) . '</td></tr>';
			echo '<tr><th>Bu aracın kaydı</th><td>' . esc_html( null === $state ? 'Yok' : $state['state'] ) . '</td></tr>';
			echo '<tr><th>Beklenen manifest SHA-256</th><td><code>' . esc_html( self::MANIFEST_SHA256 ) . '</code></td></tr>';
			echo '</tbody></table>';

			if ( $core && ! $done && null !== $empty ) {
				echo '<h2>İçe aktarma</h2><p>Manifest doğrulanır ve 29 kural (4 etkin) Mavi Belge Core kayıt yoluyla yazılır. Depo boş değilse işlem yapılmaz.</p>';
				echo '<form method="post" action="' . esc_url( $action ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION_IMPORT ) . '" />';
				wp_nonce_field( self::ACTION_IMPORT, self::NONCE_FIELD );
				echo '<p><button type="submit" class="button button-primary">Yönlendirmeleri içe aktar</button></p></form>';
			}
			if ( $core && $done ) {
				echo '<h2>Sonraki adım</h2><p>Araçlar → Mavi Belge Sağlık ekranında "Toplam 29, etkin 4" değerini ve dört yönlendirmeyi doğrulayın. Başarılıysa <code>wp-content/mu-plugins/</code> içindeki <code>mavibelge-redirect-bootstrap.php</code> ve <code>redirects.manifest.json</code> dosyalarını File Manager ile silin; depo WordPress seçeneği olarak kalır.</p>';
				echo '<h2>Geri alma</h2><p>Yalnız doğrulama başarısızsa kullanın: depo içe aktarma öncesindeki boş durumuna döner. Depo sonradan değiştirildiyse geri alma reddedilir.</p>';
				echo '<form method="post" action="' . esc_url( $action ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION_ROLLBACK ) . '" />';
				wp_nonce_field( self::ACTION_ROLLBACK, self::NONCE_FIELD );
				echo '<p><button type="submit" class="button">Yönlendirme içe aktarmasını geri al</button></p></form>';
			}
			echo '</div>';
		}
	}

	MaviBelge_Redirect_Bootstrap::boot();
}
