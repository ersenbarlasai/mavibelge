<?php
/**
 * Faz 8 — "Formlar" yönetim ekranı (Ayarlar altında). Yalnız `manage_options`.
 * Her form için KAPI durumunu ve kapalıysa NEDENLERİNİ açıkça gösterir; kurum kararları
 * (KVKK açık rıza metni/sürümü, alıcı e-posta, hassas alan ve dosya yükleme onayı) burada
 * girilir ve yalnız bu yapılandırmayla form etkinleşir (kod değişikliği gerekmez).
 * Kaydetme: nonce + yetki + `MaviBelge_Core_Forms_Config::sanitize()`. Alan içeriği/kişisel
 * veri hiçbir zaman burada saklanmaz veya gösterilmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Forms_Admin {

	const NONCE_ACTION = 'mavibelge_core_forms_config_save';
	const NONCE_NAME   = 'mavibelge_core_forms_config_nonce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_options_page( 'Mavi Belge Formları', 'Mavi Belge Formları', 'manage_options', 'mavibelge-core-forms', array( __CLASS__, 'render_page' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
		}
		$saved = false;
		if ( isset( $_POST[ self::NONCE_NAME ] ) && wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			MaviBelge_Core_Forms_Service::save_config( isset( $_POST['forms_config'] ) ? wp_unslash( $_POST['forms_config'] ) : array() );
			$saved = true;
		}
		$overview = MaviBelge_Core_Forms_Service::admin_overview();
		?>
		<div class="wrap">
			<h1>Mavi Belge Formları</h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success"><p>Yapılandırma kaydedildi. Bir form yalnız aşağıdaki TÜM koşullar sağlandığında ziyaretçiye açılır.</p></div>
			<?php endif; ?>
			<p>Bütün formlar varsayılan olarak <strong>kapalıdır</strong>. Kişisel veri toplayan formlar, kurum kararları (KVKK açık rıza metni, alıcı adresi, hassas alan/dosya yükleme onayı) girilene kadar yayına alınamaz. Gönderimler yalnız e-posta ile iletilir; kalıcı kişisel veri saklanmaz.</p>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<?php foreach ( $overview as $id => $row ) : ?>
					<?php $s = $row['settings']; $n = 'forms_config[forms][' . $id . ']'; ?>
					<h2><?php echo esc_html( $row['label'] ); ?>
						<span class="<?php echo $row['open'] ? 'notice-success' : 'notice-warning'; ?>" style="font-size:13px;font-weight:normal;padding:2px 8px;border-radius:3px;background:<?php echo $row['open'] ? '#d7f0dd' : '#fff3cd'; ?>">
							<?php echo $row['open'] ? 'AÇIK' : 'KAPALI'; ?>
						</span>
					</h2>
					<p class="description">Sayfa: /<?php echo esc_html( $row['page_slug'] ); ?>/ · <?php echo $row['sensitive'] ? 'Hassas alan içerir (T.C. kimlik no / belge / CV).' : 'Hassas alan yok.'; ?></p>
					<?php if ( ! $row['open'] ) : ?>
						<div class="notice notice-warning inline"><p><strong>Neden kapalı:</strong></p><ul style="list-style:disc;margin-left:1.4em">
							<?php foreach ( $row['reasons'] as $code ) : ?>
								<li><?php echo esc_html( MaviBelge_Core_Forms_Config::REASON_LABELS[ $code ] ); ?></li>
							<?php endforeach; ?>
						</ul></div>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<tr><th scope="row">Form açık</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> Yayına almak için işaretleyin (diğer koşullar da sağlanmalı)</label></td></tr>
						<tr><th scope="row"><label for="<?php echo esc_attr( $id ); ?>-rcpt">Alıcı e-posta</label></th><td><input type="email" class="regular-text" id="<?php echo esc_attr( $id ); ?>-rcpt" name="<?php echo esc_attr( $n ); ?>[recipient_email]" value="<?php echo esc_attr( $s['recipient_email'] ); ?>" autocomplete="off"></td></tr>
						<tr><th scope="row">KVKK açık rıza</th><td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[consent_approved]" value="1" <?php checked( $s['consent_approved'] ); ?>> Metin kurumca ONAYLANDI</label><br>
							<label for="<?php echo esc_attr( $id ); ?>-cv">Sürüm</label> <input type="text" id="<?php echo esc_attr( $id ); ?>-cv" class="small-text" name="<?php echo esc_attr( $n ); ?>[consent_version]" value="<?php echo esc_attr( $s['consent_version'] ); ?>" maxlength="40"><br>
							<label for="<?php echo esc_attr( $id ); ?>-ct">Onay metni (formda onay kutusunun yanında görünür)</label><br>
							<textarea id="<?php echo esc_attr( $id ); ?>-ct" name="<?php echo esc_attr( $n ); ?>[consent_text]" rows="3" cols="70" maxlength="1500"><?php echo esc_textarea( $s['consent_text'] ); ?></textarea>
						</td></tr>
						<?php if ( $row['sensitive'] ) : ?>
							<tr><th scope="row">Hassas alanlar</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[sensitive_fields_approved]" value="1" <?php checked( $s['sensitive_fields_approved'] ); ?>> T.C. kimlik no / kimlik-CV belgesi toplanmasına kurumca onay verildi</label></td></tr>
						<?php endif; ?>
						<?php if ( $row['has_files'] ) : ?>
							<tr><th scope="row">Dosya yükleme</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[uploads_approved]" value="1" <?php checked( $s['uploads_approved'] ); ?>> Dosya yüklemeye kurumca onay verildi (dosyalar yalnız e-postaya eklenir, sunucuda saklanmaz)</label></td></tr>
						<?php endif; ?>
					</table>
				<?php endforeach; ?>
				<?php submit_button( 'Kaydet' ); ?>
			</form>
		</div>
		<?php
	}
}
