<?php
/**
 * Faz 6B4 — admin katalog aktarımı: sektör görsel eşleme, aşama önizleme, kesintiye dayanıklı apply ve rollback.
 *
 * SSH/WP-CLI olmayan hosting içindir. Bu sınıf ince bir controller'dır: kendi karar mantığı YOKTUR.
 *  - Yazma yalnız `wp_ajax_mavibelge_import_*` action'larıyla yapılır (yedi action; `wp_ajax_nopriv_*` YOK, REST route YOK,
 *    doğrudan erişilebilen bağımsız PHP runner YOK).
 *  - Her action: (1) kapı seviyesi (`MaviBelge_Core_Import_Admin_Gates::evaluate()` — POST, HTTPS, giriş, `manage_options` +
 *    `mb_manage_tariff_period`; apply/rollback için ayrıca sabitler ve ortam), (2) kapalı request şekli, (3) action'a özel
 *    nonce, (4) `MaviBelge_Core_Import_Admin_Run_Service`. Hata yanıtları SABİT kod taşır; exception mesajı, SQL, alan değeri
 *    veya mutlak yol istemciye verilmez.
 *  - Bir HTTP isteği en çok bir küçük batch işler; tarayıcı kapansa bile (ignore_user_abort) batch commit edilir ve run
 *    `paused` kalır; sayfa yenilenince sunucu durumundan devam edilir.
 *  - JavaScript (admin/assets/import-apply.js) yalnız sıralı POST orkestrasyonu yapar; karar mantığı içermez.
 * Salt okunur dry-run tablosu `MaviBelge_Core_Import_Dry_Run_Page`'de kalır; bu bölümler onun altında render edilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Apply_Page {

	const SCRIPT_HANDLE = 'mavibelge-core-import-apply';

	/** Kapı hata kodu => HTTP durumu. */
	const GATE_HTTP_STATUS = array(
		'method_not_post'          => 405,
		'not_https'                => 403,
		'not_logged_in'            => 403,
		'missing_capability'       => 403,
		'apply_disabled'           => 403,
		'admin_apply_disabled'     => 403,
		'environment_not_allowed'  => 403,
		'production_disabled'      => 403,
		'production_host_mismatch' => 403,
		'invalid_snapshot'         => 403,
		'unknown_level'            => 400,
	);

	/** Sistem kapıları bölümünde gösterilen sabit Türkçe açıklamalar (yalnız sabit metin). */
	const GATE_LABELS = array(
		'not_https'                => 'Yönetim isteği HTTPS üzerinden olmalı.',
		'not_logged_in'            => 'Giriş yapılmış olmalı.',
		'missing_capability'       => 'manage_options ve mb_manage_tariff_period yetkilerinin ikisi de gerekli.',
		'apply_disabled'           => 'MAVIBELGE_IMPORT_APPLY_ENABLED sabiti true değil (kapalı).',
		'admin_apply_disabled'     => 'MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED sabiti true değil (kapalı).',
		'environment_not_allowed'  => 'WP_ENVIRONMENT_TYPE staging (veya üretim için production) olmalı.',
		'production_disabled'      => 'Üretimde MAVIBELGE_IMPORT_PRODUCTION_APPLY_ENABLED sabiti true değil (kapalı).',
		'production_host_mismatch' => 'Üretimde MAVIBELGE_IMPORT_PRODUCTION_HOST sabiti bu sitenin host adıyla birebir eşleşmiyor.',
	);

	public static function init() {
		foreach ( array_keys( MaviBelge_Core_Import_Admin_Gates::ACTIONS ) as $action ) {
			add_action( 'wp_ajax_' . MaviBelge_Core_Import_Admin_Gates::ACTION_PREFIX . $action, array( __CLASS__, 'handle_' . $action ) );
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/* ------------------------------------------------------------ action giriş noktaları */

	public static function handle_save_sector_image_map() {
		self::dispatch( 'save_sector_image_map' );
	}

	public static function handle_preview_stage() {
		self::dispatch( 'preview_stage' );
	}

	public static function handle_start_apply() {
		self::dispatch( 'start_apply' );
	}

	public static function handle_advance_apply() {
		self::dispatch( 'advance_apply' );
	}

	public static function handle_preview_rollback() {
		self::dispatch( 'preview_rollback' );
	}

	public static function handle_start_rollback() {
		self::dispatch( 'start_rollback' );
	}

	public static function handle_advance_rollback() {
		self::dispatch( 'advance_rollback' );
	}

	public static function handle_preview_publish() {
		self::dispatch( 'preview_publish' );
	}

	public static function handle_publish_pages() {
		self::dispatch( 'publish_pages' );
	}

	/**
	 * Tek dağıtıcı. Sıra: kapı seviyesi -> kapalı request şekli -> action'a özel nonce -> servis.
	 * Kapılar başarısızsa servis HİÇ oluşturulmaz.
	 *
	 * @param string $action MaviBelge_Core_Import_Admin_Gates::ACTIONS anahtarı.
	 */
	private static function dispatch( $action ) {
		if ( ! array_key_exists( $action, MaviBelge_Core_Import_Admin_Gates::ACTIONS ) ) {
			self::respond( array( 'ok' => false, 'error_code' => 'invalid_request' ), 400 );
		}
		$level = MaviBelge_Core_Import_Admin_Gates::ACTIONS[ $action ]['level'];
		$gate  = MaviBelge_Core_Import_Admin_Gates::evaluate( $level, MaviBelge_Core_Import_Admin_Gates::snapshot() );
		if ( ! $gate['ok'] ) {
			$code = $gate['codes'][0];
			self::respond( array( 'ok' => false, 'error_code' => $code ), isset( self::GATE_HTTP_STATUS[ $code ] ) ? self::GATE_HTTP_STATUS[ $code ] : 403 );
		}
		// Kapalı request şekli: unslash edilmiş POST + boş GET.
		$post    = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$get     = isset( $_GET ) && is_array( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$request = MaviBelge_Core_Import_Admin_Gates::normalize_request( $action, $post, $get );
		if ( ! $request['ok'] ) {
			self::respond( array( 'ok' => false, 'error_code' => $request['error_code'] ), 400 );
		}
		if ( ! wp_verify_nonce( $request['nonce'], MaviBelge_Core_Import_Admin_Gates::nonce_action( $action ) ) ) {
			self::respond( array( 'ok' => false, 'error_code' => 'invalid_nonce' ), 403 );
		}
		if ( in_array( $action, array( 'advance_apply', 'advance_rollback', 'publish_pages' ), true ) ) {
			// Tarayıcı kapansa bile o batch commit edilir; run `paused` kalır.
			ignore_user_abort( true );
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}

		$service = new MaviBelge_Core_Import_Admin_Run_Service( new MaviBelge_Core_Import_Runtime_Factory() );
		$data    = $request['data'];
		$userId  = (int) get_current_user_id();
		switch ( $action ) {
			case 'save_sector_image_map':
				$result = $service->save_image_map( $data['mappings'] );
				break;
			case 'preview_stage':
				$result = $service->preview_stage( $data['stage'] );
				break;
			case 'start_apply':
				$result = $service->start_apply( $data['stage'], $data['plan_digest'], $data['confirm_phrase'], $userId );
				break;
			case 'advance_apply':
				$result = $service->advance_apply( $data['run_uid'], $data['expected_checkpoint'], $userId );
				break;
			case 'preview_rollback':
				$result = $service->preview_rollback( $data['run_uid'] );
				break;
			case 'start_rollback':
				$result = $service->start_rollback( $data['run_uid'], $data['rollback_digest'], $data['confirm_phrase'] );
				break;
			case 'preview_publish':
				$result = $service->preview_publish();
				break;
			case 'publish_pages':
				$result = $service->publish_pages( $data['plan_digest'], $data['confirm_phrase'], $data['expected_remaining'], $userId );
				break;
			default: // advance_rollback
				$result = $service->advance_rollback( $data['run_uid'], $data['expected_checkpoint'] );
				break;
		}
		self::respond( $result, 200 );
	}

	/** JSON yanıtı; asla exception/SQL/yol içermez (servis DTO'ları zaten güvenlidir). */
	private static function respond( array $payload, $status ) {
		wp_send_json( $payload, $status );
	}

	/* ------------------------------------------------------------ render */

	public static function enqueue( $hook ) {
		if ( 'tools_page_' . MaviBelge_Core_Import_Dry_Run_Page::PAGE_SLUG !== $hook || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( self::SCRIPT_HANDLE, MAVIBELGE_CORE_URL . 'admin/assets/import-apply.js', array(), MAVIBELGE_CORE_VERSION, true );
		$nonces  = array();
		$actions = array();
		foreach ( array_keys( MaviBelge_Core_Import_Admin_Gates::ACTIONS ) as $action ) {
			$actions[ $action ] = MaviBelge_Core_Import_Admin_Gates::ACTION_PREFIX . $action;
			$nonces[ $action ]  = wp_create_nonce( MaviBelge_Core_Import_Admin_Gates::nonce_action( $action ) );
		}
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'mbImportApply',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonceField' => MaviBelge_Core_Import_Admin_Gates::NONCE_FIELD,
				'actions'    => $actions,
				'nonces'     => $nonces,
				'stages'     => MaviBelge_Core_Import_Apply_Plan::ALL_STAGES,
			)
		);
	}

	/** Dry-run sayfasının altına bölümleri basar (yalnız manage_options). */
	public static function render_sections() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$factory = new MaviBelge_Core_Import_Runtime_Factory();
		$service = new MaviBelge_Core_Import_Admin_Run_Service( $factory );
		echo '<hr /><h2 id="mb-import-apply-title">Katalog Aktarımı (Admin Apply)</h2>';
		echo '<p>Bu bölümler yalnız bu ekrandan, açık onayla ve her istekte <strong>en çok ' . esc_html( (string) MaviBelge_Core_Import_Admin_Run_Service::BATCH_SIZE ) . ' kayıtlık</strong> küçük batch\'lerle çalışır. Sekmeyi kapatırsanız işlem <em>paused</em> kalır ve buradan devam ettirilir. Gerçek apply yalnız yedek alındıktan ve sabitler geçici açıldıktan sonra yapılmalıdır (bkz. docs/admin-import-operations.md).</p>';
		self::render_gates();
		self::render_image_map( $service->image_map_view() );
		self::render_stage_preview();
		self::render_runs( $service->list_runs() );
		self::render_publish();
		echo '<div id="mb-import-status" class="notice inline" role="status" aria-live="polite" style="display:none"><p></p></div>';
	}

	private static function render_gates() {
		$snapshot           = MaviBelge_Core_Import_Admin_Gates::snapshot();
		$snapshot['method'] = 'POST';
		$gate               = MaviBelge_Core_Import_Admin_Gates::evaluate( 'run', $snapshot );
		echo '<h3>1) Sistem kapıları</h3><table class="widefat striped" style="max-width:820px"><tbody>';
		echo '<tr><th>Ortam türü</th><td>' . esc_html( (string) $snapshot['environment_type'] ) . '</td></tr>';
		echo '<tr><th>Apply/rollback şu an</th><td>' . ( $gate['ok'] ? '<strong>AÇIK</strong> (tüm kapılar geçiyor)' : '<strong>KAPALI</strong>' ) . '</td></tr>';
		foreach ( $gate['codes'] as $code ) {
			if ( isset( self::GATE_LABELS[ $code ] ) ) {
				echo '<tr><th>Engel</th><td>' . esc_html( self::GATE_LABELS[ $code ] ) . '</td></tr>';
			}
		}
		$store = new MaviBelge_Core_Import_Wpdb_Run_Store();
		echo '<tr><th>Run tabloları</th><td>' . ( $store->is_installed() ? 'kurulu' : 'kurulu değil (ilk apply\'da kurulur)' ) . '</td></tr>';
		$audit = new MaviBelge_Core_Import_WP_Audit_Sink();
		echo '<tr><th>Audit hedefi</th><td>' . ( $audit->ready() ? 'hazır' : 'hazır değil' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '<p><em>Sabitler bu ekrandan, URL\'den veya seçenekten AÇILAMAZ; yalnız wp-config.php\'de, işlem penceresi boyunca sistem yöneticisi tarafından açılır.</em></p>';
	}

	private static function render_image_map( array $view ) {
		echo '<h3>2) Sektör görsel eşleme</h3>';
		if ( array() === $view['required'] ) {
			echo '<p>Manifestte görseli olan sektör yok veya manifest okunamadı.</p>';
			return;
		}
		echo '<p>Aşağıdaki ' . esc_html( (string) count( $view['required'] ) ) . ' sektör için Ortam Kütüphanesinden <strong>elle</strong> görsel seçin. Görsel adına veya benzerliğe göre tahmin yapılmaz. Aynı kaynak görseli kullanan sektörler (ör. maden ve mermer) aynı görseli paylaşabilir.</p>';
		if ( $view['present'] ) {
			echo '<p>Kayıtlı eşleme: <strong>' . ( $view['valid'] ? 'geçerli' : 'GEÇERSİZ (yeniden kaydedin)' ) . '</strong>';
			if ( $view['valid'] && is_string( $view['digest'] ) ) {
				echo ' — özet: <code>' . esc_html( substr( $view['digest'], 0, 12 ) ) . '</code>';
			}
			echo '</p>';
		} else {
			echo '<p>Henüz eşleme kaydedilmedi.</p>';
		}
		echo '<table class="widefat striped" id="mb-image-map-table" style="max-width:820px"><thead><tr><th>Sektör</th><th>Kaynak görsel</th><th>Seçilen görsel</th><th></th></tr></thead><tbody>';
		foreach ( $view['required'] as $slug => $label ) {
			$id = isset( $view['mappings'][ $slug ] ) ? (int) $view['mappings'][ $slug ] : 0;
			echo '<tr data-slug="' . esc_attr( $slug ) . '"><td>' . esc_html( $slug ) . '</td><td><code>' . esc_html( $label ) . '</code></td><td>';
			echo '<span class="mb-image-preview">' . ( $id > 0 ? wp_kses_post( wp_get_attachment_image( $id, array( 60, 60 ) ) ) : '' ) . '</span> ';
			echo '<code class="mb-image-id">' . ( $id > 0 ? esc_html( (string) $id ) : '—' ) . '</code>';
			echo '<input type="hidden" class="mb-image-input" value="' . esc_attr( $id > 0 ? (string) $id : '' ) . '" /></td>';
			echo '<td><button type="button" class="button mb-pick-image">Görsel seç</button></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><button type="button" class="button button-primary" id="mb-save-image-map">Eşlemeyi doğrula ve kaydet</button></p>';
	}

	private static function render_stage_preview() {
		echo '<h3>3) Aşama önizleme ve uygulama</h3>';
		echo '<p>Aşama sırası zorunludur: <code>pages → sectors → qualifications → all → content</code>. Bir aşama yalnız öncekinin kayıtları gerçek okuma sonucunda <em>unchanged</em> olunca açılır (sunucu yeniden doğrular).</p>';
		echo '<p><label for="mb-stage">Aşama </label><select id="mb-stage">';
		foreach ( MaviBelge_Core_Import_Apply_Plan::ALL_STAGES as $stage ) {
			echo '<option value="' . esc_attr( $stage ) . '">' . esc_html( $stage ) . '</option>';
		}
		echo '</select> <button type="button" class="button" id="mb-preview-stage">Önizle (salt okunur)</button></p>';
		echo '<div id="mb-preview-result" style="display:none;max-width:820px"></div>';
		echo '<div id="mb-apply-confirm" style="display:none;max-width:820px"><p><label for="mb-apply-phrase"><strong>Onay ifadesi</strong> — aynen yazın: <code id="mb-apply-phrase-hint"></code></label><br />';
		echo '<input type="text" id="mb-apply-phrase" class="regular-text" autocomplete="off" spellcheck="false" /> ';
		echo '<button type="button" class="button button-primary" id="mb-start-apply">Uygula (batch\'ler halinde)</button></p></div>';
		echo '<div id="mb-progress" style="display:none;max-width:820px"><progress id="mb-progress-bar" value="0" max="1" style="width:100%"></progress><p id="mb-progress-text" aria-live="polite"></p></div>';
	}

	/** Faz 12 — taslak sayfaların AYRI yayınlanması (yalnız staging; sayfa oluşturma/apply ile birleştirilmez). */
	private static function render_publish() {
		echo '<h3>5) Sayfa yayınlama (yalnız staging)</h3>';
		echo '<p>Sayfalar <strong>taslak</strong> olarak oluşturulur (aşama <code>pages</code>). Bu bölüm taslakları AYRI ve açık onayla yayınlar; istek başına en çok ' . esc_html( (string) MaviBelge_Core_Import_Page_Publisher::BATCH_LIMIT ) . ' sayfa. Kurum kararı bekleyen sayfalar yayına <strong>alınmaz</strong> (bekletilir). Üretimde yayınlama bu ekrandan yapılamaz.</p>';
		echo '<p><button type="button" class="button" id="mb-preview-publish">Yayın özetini göster (salt okunur)</button></p>';
		echo '<div id="mb-publish-result" style="display:none;max-width:1000px"></div>';
		echo '<div id="mb-publish-confirm" style="display:none;max-width:820px"><p><label for="mb-publish-phrase"><strong>Yayın onay ifadesi</strong> — aynen yazın: <code id="mb-publish-phrase-hint"></code></label><br />';
		echo '<input type="text" id="mb-publish-phrase" class="regular-text" autocomplete="off" spellcheck="false" /> ';
		echo '<button type="button" class="button button-primary" id="mb-start-publish">Hazır sayfaları yayınla (batch\'ler halinde)</button></p></div>';
	}

	private static function render_runs( array $runs ) {
		echo '<h3>4) Run listesi ve geri alma</h3>';
		if ( array() === $runs ) {
			echo '<p>Henüz import run\'ı yok.</p>';
			return;
		}
		echo '<table class="widefat striped" id="mb-run-table" style="max-width:1000px"><thead><tr><th>Run</th><th>Aşama</th><th>Durum</th><th>İlerleme</th><th>Hata kodu</th><th>Tarih (UTC)</th><th></th></tr></thead><tbody>';
		foreach ( $runs as $run ) {
			$uid      = $run['run_uid'];
			$progress = $run['committed_items'] . '/' . $run['total_writes'];
			if ( $run['rollback_items'] > 0 || in_array( $run['status'], array( 'rollback_ready', 'rolling_back', 'rollback_paused', 'rolled_back' ), true ) ) {
				$progress .= ' (geri alınan: ' . $run['rollback_items'] . ')';
			}
			echo '<tr data-run-uid="' . esc_attr( $uid ) . '" data-status="' . esc_attr( $run['status'] ) . '" data-checkpoint="' . esc_attr( (string) $run['committed_batches'] ) . '" data-rollback-checkpoint="' . esc_attr( (string) $run['rollback_batches'] ) . '">';
			echo '<td><code>' . esc_html( substr( $uid, 0, 8 ) ) . '</code></td><td>' . esc_html( $run['stage'] ) . '</td><td>' . esc_html( $run['status'] ) . '</td><td>' . esc_html( $progress ) . '</td><td>' . esc_html( (string) $run['error_code'] ) . '</td><td>' . esc_html( $run['created_at'] ) . '</td><td>';
			if ( 'advance_apply' === $run['next_action'] ) {
				echo '<button type="button" class="button mb-run-continue-apply">Devam et</button>';
			} elseif ( 'advance_rollback' === $run['next_action'] ) {
				echo '<button type="button" class="button mb-run-continue-rollback">Geri almaya devam et</button>';
			} elseif ( 'preview_rollback' === $run['next_action'] ) {
				echo '<button type="button" class="button mb-run-preview-rollback">Geri alma önizle</button>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<div id="mb-rollback-result" style="display:none;max-width:820px"></div>';
		echo '<div id="mb-rollback-confirm" style="display:none;max-width:820px"><p><label for="mb-rollback-phrase"><strong>Geri alma onay ifadesi</strong> — aynen yazın: <code id="mb-rollback-phrase-hint"></code></label><br />';
		echo '<input type="text" id="mb-rollback-phrase" class="regular-text" autocomplete="off" spellcheck="false" /> ';
		echo '<button type="button" class="button button-primary" id="mb-start-rollback">Geri al (batch\'ler halinde)</button></p></div>';
	}
}
