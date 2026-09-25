<?php
/**
 * Faz 6B2 — salt okunur admin dry-run ekranı (WP-CLI bulunmayan hosting
 * için). Yalnız `manage_options` yetkisi olan kullanıcı görebilir ve
 * çalıştırabilir. Tetikleme POST + nonce iledir; GET isteği HİÇBİR ağır
 * sorgu ÇALIŞTIRMAZ, yalnız formu gösterir. Formda TEK eylem vardır:
 * "Salt Okunur Dry-Run Çalıştır" — apply/import/write düğmesi YOKTUR.
 *
 * Bu SINIF KENDİ KARAR MANTIĞINI YAZMAZ — yalnız
 * `MaviBelge_Core_Import_Dry_Run_Service` (WP-CLI komutuyla PAYLAŞILAN AYNI
 * servis) çağrılır. Bu dosya hiçbir option/transient/post/meta/log dosyası
 * YAZMAZ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Dry_Run_Page {

	const CAPABILITY  = 'manage_options';
	const NONCE_ACTION = 'mavibelge_core_import_dry_run';
	const NONCE_NAME   = 'mavibelge_core_import_dry_run_nonce';
	const PAGE_SLUG    = 'mavibelge-core-import-dry-run';
	const PER_PAGE     = 25;

	/** Yalnız bu sabit listedeki karar değerleri bilinen bir CSS sınıfına eşlenir — ham değer sınıf adına ASLA basılmaz. */
	const DECISION_CSS_CLASS = array(
		'create'                     => 'mb-decision-create',
		'update'                     => 'mb-decision-update',
		'unchanged'                  => 'mb-decision-unchanged',
		'conflict'                   => 'mb-decision-conflict',
		'conflict_duplicate_target'  => 'mb-decision-conflict',
		'conflict_wrong_target_type' => 'mb-decision-conflict',
		'blocked_dependency'         => 'mb-decision-blocked',
		'invalid'                    => 'mb-decision-invalid',
	);

	const TYPE_CSS_CLASS = array(
		'sector'        => 'mb-type-sector',
		'qualification' => 'mb-type-qualification',
		'fee'           => 'mb-type-fee',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Runtime Doğrulama turu — `validate_request()` sonucu: null = geçerli
	 * POST yok (GET), int = doğrulanmış POST'un sayfa numarası.
	 * `$requestValidated` isteğin tek kez doğrulanmasını sağlar.
	 */
	private static $validatedPaged   = null;
	private static $requestValidated = false;

	public static function register_menu() {
		$hook = add_submenu_page(
			'tools.php',
			'İçe Aktarım Dry-Run (Salt Okunur)',
			'İçe Aktarım Dry-Run',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
		// Runtime Doğrulama turu — kök neden: istek doğrulaması render_page()
		// içinde, admin başlığı ZATEN basıldıktan sonra yapılıyordu; bu yüzden
		// wp_die()'ın 'response' => 400/403 durumu uygulanamıyor, hata admin
		// sayfasının içine HTTP 200 ile gömülüyordu (PHP 7.3 + WordPress 6.9.9
		// runtime testinde kanıtlandı). Doğrulama artık sayfanın `load-{hook}`
		// aşamasında, HİÇBİR çıktıdan önce yapılır.
		if ( is_string( $hook ) && '' !== $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'handle_request_before_output' ) );
		}
	}

	/** `load-{hook}` — çıktıdan ÖNCE: yetki, sonra istek şekli/nonce. */
	public static function handle_request_before_output() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.', 'Yetkisiz', array( 'response' => 403 ) );
		}
		self::$validatedPaged   = self::validate_request();
		self::$requestValidated = true;
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		// Savunma derinliği: `load-{hook}` herhangi bir nedenle çalışmadıysa
		// doğrulama burada yine yapılır (aynı fail-closed kurallar).
		if ( ! self::$requestValidated ) {
			self::$validatedPaged   = self::validate_request();
			self::$requestValidated = true;
		}

		$result = null;
		$ranNow = false;
		$paged  = null === self::$validatedPaged ? 1 : self::$validatedPaged;

		if ( null !== self::$validatedPaged ) {
			$repository = new MaviBelge_Core_Import_WordPress_Target_Repository();
			$service    = new MaviBelge_Core_Import_Dry_Run_Service( $repository );
			$result     = $service->run_dry_run();
			$ranNow     = true;
		}

		echo '<div class="wrap"><h1>İçe Aktarım Dry-Run (Salt Okunur)</h1>';
		echo '<p>Bu ekran Faz 6A manifestlerini gerçek WordPress kayıtlarına karşı yalnız <strong>SALT OKUNUR</strong> planlar. Hiçbir içerik, terim, meta veya seçenek oluşturulmaz/değiştirilmez/silinmez. Görsel ve bağımlılık eşleştirmesinde tahmin/fuzzy yöntem yoktur.</p>';

		echo '<form method="post" id="mb-import-dry-run-form">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="mb_paged" id="mb-import-dry-run-paged" value="' . esc_attr( (string) $paged ) . '" />';
		submit_button( 'Salt Okunur Dry-Run Çalıştır', 'primary', 'submit', false );
		echo '</form>';

		if ( $ranNow && is_array( $result ) ) {
			self::render_result( $result, $paged );
		}

		echo '</div>';
	}

	/**
	 * Düzeltme ve Kabul §2.6 — istek şekli, yetki kontrolünden SONRA ama
	 * sorgu zincirinden ÖNCE, HER adımda scalar-şekil kontrolünden
	 * geçirilir; unslash/cast yalnız şekil doğrulandıktan SONRA uygulanır.
	 * Nonce eksik/array/object/geçersiz olan bir POST sessizce normal GET
	 * görünümüne DÜŞMEZ — wp_die ile reddedilir. GET hiçbir sorgu çalıştırmaz.
	 *
	 * @return int|null Doğrulanmış POST için sayfa numarası; GET için null.
	 */
	private static function validate_request() {
		$requestMethod = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( $_SERVER['REQUEST_METHOD'] )
			: '';

		if ( 'POST' !== $requestMethod ) {
			return null;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! is_string( $_POST[ self::NONCE_NAME ] ) ) {
			wp_die( 'Geçersiz istek: güvenlik anahtarı (nonce) eksik veya geçersiz biçimde.', 'Geçersiz istek', array( 'response' => 400 ) );
		}
		$nonceRaw = wp_unslash( $_POST[ self::NONCE_NAME ] );
		if ( ! is_string( $nonceRaw ) || ! wp_verify_nonce( $nonceRaw, self::NONCE_ACTION ) ) {
			wp_die( 'Geçersiz istek: güvenlik anahtarı (nonce) doğrulanamadı.', 'Geçersiz istek', array( 'response' => 403 ) );
		}
		return self::parse_positive_decimal_paged( isset( $_POST['mb_paged'] ) ? $_POST['mb_paged'] : null );
	}

	/**
	 * Düzeltme ve Kabul §2.6 — `mb_paged` YALNIZ pozitif, tam-onluk (decimal)
	 * bir tam sayı string'i/int'i kabul eder. `is_numeric()` bilimsel
	 * gösterimi ("1e2"), ondalığı ("1.5") ve öndeki `+`/boşluğu da GEÇERLİ
	 * sayardığı için (önceki turun kök nedeni) burada KULLANILMAZ — yalnız
	 * `^[1-9][0-9]*\z` biçimi (veya gerçek pozitif PHP int) kabul edilir;
	 * array/object/negatif/ondalık/bilimsel gösterim güvenli varsayılan
	 * (sayfa 1) sonucuna düşer — bu, planlayıcıya verilen veriyi ETKİLEMEZ,
	 * yalnız görüntü sayfasını etkiler, bu yüzden hard-reject GEREKMEZ.
	 */
	private static function parse_positive_decimal_paged( $raw ) {
		if ( is_int( $raw ) ) {
			return $raw > 0 ? $raw : 1;
		}
		if ( ! is_string( $raw ) ) {
			return 1; // array/object/bool/null -> güvenli varsayılan.
		}
		$parsed = self::normalize_paged_value( wp_unslash( $raw ) );
		return null === $parsed ? 1 : $parsed;
	}

	/**
	 * Saf (WordPress fonksiyonu çağırmayan) `mb_paged` biçim kararı —
	 * `tests/run.php` doğrudan test eder. Yalnız gerçek pozitif PHP int'i
	 * veya pozitif, tam-onluk string'i kabul eder; aksi hâlde `null`.
	 *
	 * @param mixed $value unslash edilmiş ham değer.
	 * @return int|null
	 */
	public static function normalize_paged_value( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}
		// `\z` (kesin dize sonu) — `$` sondaki "\n"i tolere ederdi ("1\n" kabul
		// ediliyordu; PHP 7.3 runtime testinde kanıtlandı).
		if ( ! is_string( $value ) || ! preg_match( '/^[1-9][0-9]*\z/', $value ) ) {
			return null;
		}
		return (int) $value;
	}

	private static function render_result( array $result, $paged ) {
		$plan    = $result['plan'];
		$summary = $plan['summary'];

		echo '<h2>Sonuç</h2>';
		echo '<p>Üretim zamanı (UTC): <code>' . esc_html( $result['generated_at_utc'] ) . '</code> — bu değer hash/karar hesabına GİRMEZ, yalnız görüntü amaçlıdır.</p>';

		if ( ! empty( $result['load_errors'] ) ) {
			echo '<div class="notice notice-error"><p><strong>Manifest yükleme hataları:</strong></p><ul>';
			foreach ( $result['load_errors'] as $error ) {
				echo '<li>' . esc_html( (string) $error ) . '</li>';
			}
			echo '</ul></div>';
		}
		if ( ! empty( $plan['errors'] ) ) {
			echo '<div class="notice notice-error"><p><strong>Plan hataları:</strong></p><ul>';
			foreach ( $plan['errors'] as $error ) {
				echo '<li>' . esc_html( (string) $error ) . '</li>';
			}
			echo '</ul></div>';
		}

		echo '<table class="widefat" style="max-width:640px"><tbody>';
		echo '<tr><th>Toplam</th><td>' . esc_html( (string) $summary['total'] ) . '</td></tr>';
		foreach ( array( 'create', 'update', 'unchanged', 'conflict', 'blocked', 'invalid' ) as $op ) {
			echo '<tr><th>' . esc_html( $op ) . '</th><td>' . esc_html( (string) $summary['operations'][ $op ] ) . '</td></tr>';
		}
		echo '<tr><th>structurally_valid</th><td>' . ( $summary['structurally_valid'] ? 'true' : 'false' ) . '</td></tr>';
		echo '<tr><th>applicable</th><td>' . ( $summary['applicable'] ? 'true' : 'false' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '<p><em>applicable=true, yalnız gelecekteki Faz 6B3 için bir ADAYLIK işaretidir — otomatik uygulama yetkisi DEĞİLDİR.</em></p>';

		if ( ! empty( $result['diagnostics'] ) ) {
			echo '<h3>Tanılar</h3><ul>';
			foreach ( $result['diagnostics'] as $diagnostic ) {
				$code = isset( $diagnostic['code'] ) ? (string) $diagnostic['code'] : '';
				$type = isset( $diagnostic['type'] ) ? (string) $diagnostic['type'] : '';
				$key  = isset( $diagnostic['source_key'] ) ? (string) $diagnostic['source_key'] : '';
				echo '<li>[' . esc_html( $type ) . '] ' . esc_html( $code ) . ' (' . esc_html( $key ) . ')</li>';
			}
			echo '</ul>';
		}

		self::render_entries_table( $plan['entries'], $paged );
	}

	/**
	 * Sayfalama YALNIZ görüntü katmanındadır — planlayıcıya HER ZAMAN tam
	 * 14/83/103 kayıt orijinal sıra/indeksleriyle verilir (bkz.
	 * class-import-dry-run-service.php); yalnız BU tablo dilimlenir.
	 * Sonuç hiçbir yerde saklanmadığı için (bkz. class docblock) sayfa
	 * seçimi bir GET bağlantısı OLAMAZ — her sayfa numarası KENDİ, nonce'lı
	 * POST mini-formuyla (dry-run'ı yeniden çalıştırarak) seçilir.
	 */
	private static function render_entries_table( array $entries, $paged ) {
		$total       = count( $entries );
		$totalPages  = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged       = max( 1, min( $totalPages, (int) $paged ) );
		$offset      = ( $paged - 1 ) * self::PER_PAGE;
		$pageEntries = array_slice( $entries, $offset, self::PER_PAGE );

		echo '<h3>Kayıtlar (' . esc_html( (string) $total ) . ')</h3>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'source_key', 'type', 'decision', 'reason', 'target_id', 'değişen alanlar', 'çözülemeyen bağımlılıklar' ) as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $pageEntries as $entry ) {
			$decision    = isset( $entry['decision'] ) && is_string( $entry['decision'] ) ? $entry['decision'] : '';
			$type        = isset( $entry['type'] ) && is_string( $entry['type'] ) ? $entry['type'] : '';
			$decisionCss = isset( self::DECISION_CSS_CLASS[ $decision ] ) ? self::DECISION_CSS_CLASS[ $decision ] : 'mb-decision-unknown';
			$typeCss     = isset( self::TYPE_CSS_CLASS[ $type ] ) ? self::TYPE_CSS_CLASS[ $type ] : 'mb-type-unknown';
			$targetId    = isset( $entry['target_id'] ) && null !== $entry['target_id'] ? (string) $entry['target_id'] : '—';
			$changed     = isset( $entry['changed_fields'] ) && is_array( $entry['changed_fields'] ) ? implode( ', ', array_map( 'strval', $entry['changed_fields'] ) ) : '';
			$unresolved  = isset( $entry['unresolved_dependencies'] ) && is_array( $entry['unresolved_dependencies'] ) ? implode( ', ', array_map( 'strval', $entry['unresolved_dependencies'] ) ) : '';

			echo '<tr class="' . esc_attr( $typeCss ) . ' ' . esc_attr( $decisionCss ) . '">';
			echo '<td>' . esc_html( isset( $entry['source_key'] ) ? (string) $entry['source_key'] : '' ) . '</td>';
			echo '<td>' . esc_html( $type ) . '</td>';
			echo '<td>' . esc_html( $decision ) . '</td>';
			echo '<td>' . esc_html( isset( $entry['reason'] ) ? (string) $entry['reason'] : '' ) . '</td>';
			echo '<td>' . esc_html( $targetId ) . '</td>';
			echo '<td>' . esc_html( $changed ) . '</td>';
			echo '<td>' . esc_html( $unresolved ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		if ( $totalPages > 1 ) {
			echo '<p class="tablenav-pages">';
			for ( $page = 1; $page <= $totalPages; $page++ ) {
				if ( $page === $paged ) {
					echo '<strong>' . esc_html( (string) $page ) . '</strong> ';
					continue;
				}
				echo '<form method="post" style="display:inline">';
				wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
				echo '<input type="hidden" name="mb_paged" value="' . esc_attr( (string) $page ) . '" />';
				submit_button( (string) $page, 'secondary', 'submit', false, array( 'style' => 'margin:0 2px' ) );
				echo '</form>';
			}
			echo '</p>';
		}
	}
}
