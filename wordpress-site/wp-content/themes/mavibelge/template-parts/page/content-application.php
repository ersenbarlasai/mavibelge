<?php
/**
 * Faz 12f — online başvuru gövdesi (tanitim-site/online-basvuru.html düzeni: dar alan, dört adımlı gösterge).
 *
 * Kapı kararı YALNIZ eklentinin describe() DTO'sundaki `open` alanıdır (MaviBelge_Core_Forms_Config::gate()); bu
 * şablon kapıyı ASLA atlamaz:
 *  - Kapı AÇIK (veya PRG başarı ekranı): gerçek, güvenli form (nonce, imzalı jeton, bal küpü, allowlist, dosya
 *    doğrulaması, PRG) adımlara gruplanmış olarak çizilir (template-parts/forms/form.php + steps).
 *  - Kapı KAPALI: <form> YOKTUR; ad alanı, nonce, jeton, kişisel veri/dosya girişi YOKTUR. Yalnız ilk adımın
 *    (meslek + sınav alanı) seçim önizlemesi, devre dışı "Devam Et" ve formun açıldığında isteyeceği bilgilerin listesi.
 *    `?meslek=` ön seçimi her iki durumda da aynı sözleşmeyle (eklentinin allowlist eşleşmesi) uygulanır.
 *
 * $args:
 * - form (array, zorunlu) MaviBelge_Core_Forms_Service::describe() DTO'su
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : null;
if ( null === $form ) {
	return;
}
$steps     = function_exists( 'mavibelge_application_steps' ) ? mavibelge_application_steps() : array();
$preselect = isset( $form['preselect'] ) && is_array( $form['preselect'] ) ? $form['preselect'] : array();
$by_name   = array();
foreach ( $form['fields'] as $form_field ) {
	$by_name[ $form_field['name'] ] = $form_field;
}
$is_live = true === $form['open'] || 'success' === $form['status'];
?>
<div class="container section-tight application-wrap">
	<?php if ( trim( (string) get_the_content() ) ) : ?>
		<div class="entry-content content-narrow">
			<?php the_content(); ?>
		</div>
	<?php endif; ?>

	<?php if ( $is_live ) : ?>
		<?php get_template_part( 'template-parts/forms/form-status', null, array( 'form' => $form ) ); ?>
		<?php if ( 'success' !== $form['status'] && true === $form['open'] ) : ?>
			<?php get_template_part( 'template-parts/forms/form', null, array( 'form' => $form, 'steps' => $steps ) ); ?>
		<?php endif; ?>
	<?php else : ?>
		<div class="mb-application-preview">
			<div class="demo-notice" role="note">
				<strong><?php esc_html_e( 'Bu form şu anda kullanılamıyor.', 'mavibelge' ); ?></strong>
				<?php esc_html_e( 'Bu ekran önizlemedir; buradan gerçek başvuru gönderilmez ve kişisel bilgi alınmaz. Başvuru için bize telefon veya e-posta ile ulaşabilirsiniz.', 'mavibelge' ); ?>
			</div>

			<?php if ( current_user_can( 'manage_options' ) && ! empty( $form['reason_labels'] ) ) : ?>
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

			<div class="form-card">
				<?php if ( ! empty( $steps ) ) : ?>
					<ol class="step-indicator" aria-label="<?php esc_attr_e( 'Başvuru adımları', 'mavibelge' ); ?>">
						<?php foreach ( $steps as $index => $step ) : ?>
							<li<?php echo 0 === $index ? ' class="is-active" aria-current="step"' : ''; ?>><?php echo esc_html( $step['title'] ); ?></li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>

				<h2 class="form-step-title"><?php echo esc_html( ! empty( $steps ) ? $steps[0]['title'] : __( '1. Meslek Seçimi', 'mavibelge' ) ); ?></h2>
				<?php
				foreach ( array( 'qualification', 'exam_location' ) as $preview_name ) :
					if ( ! isset( $by_name[ $preview_name ] ) ) {
						continue;
					}
					$preview_field = $by_name[ $preview_name ];
					$preview_id    = 'mb-preview-' . $preview_name;
					$preview_value = isset( $preselect[ $preview_name ] ) ? (string) $preselect[ $preview_name ] : '';
					?>
					<div class="form-field">
						<label for="<?php echo esc_attr( $preview_id ); ?>"><?php echo esc_html( $preview_field['label'] ); ?> <span aria-hidden="true">*</span></label>
						<select id="<?php echo esc_attr( $preview_id ); ?>">
							<option value=""><?php esc_html_e( 'Seçiniz', 'mavibelge' ); ?></option>
							<?php foreach ( $preview_field['options'] as $option_value => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_value ); ?>"<?php selected( $preview_value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endforeach; ?>

				<div class="step-actions">
					<span></span>
					<button type="button" class="btn btn-primary" disabled aria-describedby="mb-application-closed-note"><?php esc_html_e( 'Devam Et →', 'mavibelge' ); ?></button>
				</div>
				<p class="hint application-closed-note" id="mb-application-closed-note"><?php esc_html_e( 'Form kapalı olduğu için sonraki adımlar (kişisel bilgiler, belgeler, onay) şu anda etkin değildir; gerçek başvuru gönderilmez.', 'mavibelge' ); ?></p>

				<?php
				$later = array();
				foreach ( array_slice( $steps, 1 ) as $step ) {
					foreach ( $step['fields'] as $later_name ) {
						if ( isset( $by_name[ $later_name ] ) && 'consent' !== $by_name[ $later_name ]['type'] ) {
							$later[] = $by_name[ $later_name ]['label'];
						}
					}
				}
				if ( ! empty( $later ) ) :
					?>
					<h3 class="application-later-title"><?php esc_html_e( 'Form açıldığında istenecek diğer bilgiler', 'mavibelge' ); ?></h3>
					<ul class="icon-list">
						<?php foreach ( $later as $later_label ) : ?>
							<li><span class="bullet" aria-hidden="true">•</span><span><?php echo esc_html( $later_label ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
