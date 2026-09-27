<?php
/**
 * Faz 12g — İletişim sayfası gövdesi (tanitim-site/iletisim.html düzeni): ofis/sınav alanı kartları, sosyal medya,
 * "Bize Yazın". Kahraman page.php'dedir (inc/page-layouts.php).
 *
 * Form kapısı: karar YALNIZ eklentinin describe() DTO'sundaki `open` alanıdır (MaviBelge_Core_Forms_Config::gate()).
 *  - Açık (veya PRG başarı ekranı): merkezi güvenli renderer (template-parts/forms/form.php; nonce, imzalı jeton, bal küpü,
 *    allowlist, PRG) — Ad Soyad + Telefon aynı satırda.
 *  - Kapalı: <form>, ad alanı, nonce, jeton, bal küpü YOK; yalnız "kullanılamıyor" bildirimi, telefon/e-posta kanalı ve
 *    formun açıldığında isteyeceği bilgilerin listesi. Ziyaretçiden veri istenmez.
 *
 * $args:
 * - form (array|null) MaviBelge_Core_Forms_Service::describe() DTO'su (eklenti pasifse null)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form    = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : null;
$is_live = null !== $form && ( true === $form['open'] || 'success' === $form['status'] );
$primary = function_exists( 'mavibelge_primary_contact' ) ? mavibelge_primary_contact() : array( 'phone_display' => '', 'phone_tel' => '', 'email' => '' );
?>
<?php if ( trim( (string) get_the_content() ) ) : ?>
	<div class="container section-tight">
		<div class="entry-content content-narrow">
			<?php echo mavibelge_rendered_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı. ?>
		</div>
	</div>
<?php endif; ?>

<?php get_template_part( 'template-parts/content/location-list' ); ?>

<section class="section-tight contact-social" aria-labelledby="social-heading">
	<div class="container contact-form-wrap">
		<h2 id="social-heading"><?php esc_html_e( 'Sosyal Medya', 'mavibelge' ); ?></h2>
		<p><?php esc_html_e( 'Güncel duyurularımızı ve gelişmelerimizi resmi sosyal medya hesaplarımızdan takip edebilirsiniz.', 'mavibelge' ); ?></p>
		<?php get_template_part( 'template-parts/components/social-links' ); ?>
	</div>
</section>

<section class="bg-light contact-write" aria-labelledby="write-heading">
	<div class="container contact-form-wrap">
		<h2 id="write-heading"><?php esc_html_e( 'Bize Yazın', 'mavibelge' ); ?></h2>
		<?php if ( $is_live ) : ?>
			<?php get_template_part( 'template-parts/forms/form-status', null, array( 'form' => $form ) ); ?>
			<?php if ( 'success' !== $form['status'] && true === $form['open'] ) : ?>
				<?php get_template_part( 'template-parts/forms/form', null, array( 'form' => $form, 'rows' => array( array( 'full_name', 'phone' ) ) ) ); ?>
			<?php endif; ?>
		<?php else : ?>
			<?php
			$labels = array();
			if ( null !== $form ) {
				foreach ( $form['fields'] as $form_field ) {
					if ( 'consent' !== $form_field['type'] ) {
						$labels[] = $form_field['label'];
					}
				}
			} elseif ( function_exists( 'mavibelge_form_shell_fields_for_slug' ) ) {
				$labels = mavibelge_form_shell_fields_for_slug( 'iletisim' );
			}
			?>
			<div class="contact-closed">
				<div class="demo-notice" role="note">
					<strong><?php esc_html_e( 'İletişim formu şu anda kullanılamıyor.', 'mavibelge' ); ?></strong>
					<?php esc_html_e( 'Bu alandan gerçek mesaj gönderilemez ve kişisel bilgi alınmaz. Bize telefon veya e-posta ile ulaşabilirsiniz:', 'mavibelge' ); ?>
					<?php if ( '' !== $primary['phone_display'] ) : ?>
						<a href="<?php echo esc_url( $primary['phone_tel'], array( 'tel' ) ); ?>"><?php echo esc_html( $primary['phone_display'] ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $primary['email'] ) : ?>
						· <a href="<?php echo esc_url( 'mailto:' . $primary['email'], array( 'mailto' ) ); ?>"><?php echo esc_html( $primary['email'] ); ?></a>
					<?php endif; ?>
				</div>
				<?php if ( null !== $form && current_user_can( 'manage_options' ) && ! empty( $form['reason_labels'] ) ) : ?>
					<div class="alert alert-warning" role="note">
						<strong><?php esc_html_e( 'Yönetici notu — form neden kapalı:', 'mavibelge' ); ?></strong>
						<ul>
							<?php foreach ( $form['reason_labels'] as $reason_label ) : ?>
								<li><?php echo esc_html( $reason_label ); ?></li>
							<?php endforeach; ?>
						</ul>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=mavibelge-core-forms' ) ); ?>"><?php esc_html_e( 'Form ayarlarına git', 'mavibelge' ); ?></a>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $labels ) ) : ?>
					<div class="form-card">
						<h3 class="contact-fields-title"><?php esc_html_e( 'Form açıldığında istenecek bilgiler', 'mavibelge' ); ?></h3>
						<ul class="icon-list">
							<?php foreach ( $labels as $label ) : ?>
								<li><span class="bullet" aria-hidden="true">•</span><span><?php echo esc_html( $label ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
