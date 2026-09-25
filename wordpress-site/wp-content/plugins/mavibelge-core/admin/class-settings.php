<?php
/**
 * "Aktif Tarife Dönemi" screen — a capability-gated place to store the
 * single active period (mb_active_tariff_period option) that the
 * front end will eventually read.
 *
 * Faz 2 scope note: this screen exists so the mechanism is in place,
 * but this phase does not assign a real value here — no fee data has
 * been imported yet (import is Faz 6), so there is nothing real to
 * point the option at. The field is left empty by default; only a
 * user with MANAGE_TARIFF_PERIOD_CAP can change it, and every change
 * is audit-logged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Settings {

	const OPTION_KEY   = 'mb_active_tariff_period';
	const NONCE_ACTION = 'mavibelge_core_save_active_period';
	const NONCE_NAME   = 'mavibelge_core_active_period_nonce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=mb_ucret',
			'Aktif Tarife Dönemi',
			'Aktif Tarife Dönemi',
			MaviBelge_Core_Roles::MANAGE_TARIFF_PERIOD_CAP,
			'mavibelge-core-active-period',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( MaviBelge_Core_Roles::MANAGE_TARIFF_PERIOD_CAP ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}

		if ( isset( $_POST[ self::NONCE_NAME ] ) && wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			self::handle_save();
		}

		$current = get_option( self::OPTION_KEY, '' );
		?>
		<div class="wrap">
			<h1>Aktif Tarife Dönemi</h1>
			<p>Ziyaretçiye gösterilecek tek aktif ücret dönemini belirler. Bu alan aynı anda yalnız bir değer taşır.</p>
			<?php self::render_active_period_warning( $current ); ?>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="mb_active_tariff_period">Aktif Dönem</label></th>
						<td>
							<input type="text" id="mb_active_tariff_period" name="mb_active_tariff_period" value="<?php echo esc_attr( $current ); ?>" class="regular-text" placeholder="Örn. 2026" list="mb_active_tariff_period_suggestions">
							<?php self::render_period_suggestions_datalist(); ?>
							<p class="description">Kısa bir dönem etiketi (ör. yıl). Boş bırakılabilir. Aşağıdaki öneri listesi, mevcut ücret kayıtlarında gerçekten kullanılan dönemlerden türetilmiştir — serbest metin alanını kısıtlamaz, yalnız yardımcı olur.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Kaydet' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Faz 5 §8.3: aktif dönem boşsa veya bu dönemde gerçekten aktif +
	 * geçerlilik penceresi içinde + dolu bir fiyat listesine sahip en az
	 * bir ücret kaydı yoksa, yetkili kullanıcıya anlaşılır bir uyarı
	 * gösterilir. Ziyaretçiye hiçbir ek bilgi sızdırılmaz — bu yalnız
	 * yönetim ekranında görünür.
	 */
	private static function render_active_period_warning( $current ) {
		$current = trim( (string) $current );
		if ( '' === $current ) {
			echo '<div class="notice notice-warning inline"><p>Aktif dönem henüz boş. Boşken ziyaretçiye hiçbir ücret gösterilmez.</p></div>';
			return;
		}
		if ( ! class_exists( 'MaviBelge_Core_Catalog_Service' ) || ! post_type_exists( 'mb_ucret' ) ) {
			return;
		}
		// Faz 5 Düzeltme ve Kabul §2.4: get_active_fee_qualification_ids()
		// only counts fees LINKED to a real yeterlilik (its intended,
		// narrower job for the mb_priced archive filter) — using it here
		// wrongly warned "no active fee" whenever every otherwise-fully-
		// valid active fee happened to have _mb_qualification_id = 0
		// (an explicitly allowed, unlinked state per docs/content-model.md).
		// get_active_fee_count() counts every fee that actually passes all
		// §5 visibility rules, linked or not.
		if ( 0 === MaviBelge_Core_Catalog_Service::get_active_fee_count() ) {
			printf(
				'<div class="notice notice-warning inline"><p>Aktif dönem "%s" olarak ayarlı, ancak bu döneme ait, durumu Aktif, geçerlilik tarihi uygun ve dolu bir fiyat listesi taşıyan yayınlanmış hiçbir ücret kaydı bulunamadı. Ziyaretçiye şu an bu dönem için hiçbir ücret gösterilmiyor.</p></div>',
				esc_html( $current )
			);
		}
	}

	private static function render_period_suggestions_datalist() {
		if ( ! post_type_exists( 'mb_ucret' ) ) {
			echo '<datalist id="mb_active_tariff_period_suggestions"></datalist>';
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'mb_ucret',
				'post_status'    => array( 'draft', 'publish', 'pending', 'private' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);
		$periods = array();
		foreach ( $ids as $post_id ) {
			$period = trim( (string) get_post_meta( $post_id, '_mb_tariff_period', true ) );
			if ( '' !== $period ) {
				$periods[ $period ] = true;
			}
		}
		echo '<datalist id="mb_active_tariff_period_suggestions">';
		foreach ( array_keys( $periods ) as $period ) {
			printf( '<option value="%s">', esc_attr( $period ) );
		}
		echo '</datalist>';
	}

	private static function handle_save() {
		if ( ! current_user_can( MaviBelge_Core_Roles::MANAGE_TARIFF_PERIOD_CAP ) ) {
			return;
		}

		$raw = isset( $_POST['mb_active_tariff_period'] ) ? wp_unslash( $_POST['mb_active_tariff_period'] ) : '';
		$new = sanitize_text_field( $raw );
		if ( strlen( $new ) > 20 ) {
			$new = substr( $new, 0, 20 );
		}

		$old = get_option( self::OPTION_KEY, '' );
		if ( $new === $old ) {
			return;
		}

		update_option( self::OPTION_KEY, $new );

		MaviBelge_Core_Audit_Log::record(
			MaviBelge_Core_Audit_Log::EVENT_ACTIVE_PERIOD_CHANGED,
			'option',
			0,
			array(
				'option' => self::OPTION_KEY,
				'from'   => $old,
				'to'     => $new,
			)
		);

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible"><p>Aktif tarife dönemi güncellendi.</p></div>';
			}
		);
	}
}
