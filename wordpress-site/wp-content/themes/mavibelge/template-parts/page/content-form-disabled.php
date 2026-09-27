<?php
/**
 * Safe "not live yet" shell for the 5 pages that must not accept real
 * submissions until Faz 8 (online-basvuru, sinav-talepleri,
 * itiraz-sikayet, iletisim, is-basvurusu — brief §9). Deliberately
 * renders NO <form> element and NO <input> at all: just the editor's
 * intro content, a clear notice, and the intended field LABELS as plain
 * text so a visitor knows what the eventual form will ask for. This
 * sidesteps every risk in the brief's "do not do" list (no action="#",
 * no fake AJAX, no disabled-but-still-a-real-form-tag ambiguity) by
 * simply not being a <form> yet.
 *
 * Faz 8: form altyapısı (mavibelge-core) etkinse kapalı form için etiketler merkezi şemadan gelir; kapalı
 * NEDENLERİ yalnız `manage_options` yetkisi olan kullanıcıya gösterilir (ziyaretçiye genel bir "şu anda kullanılamıyor"
 * iletisi). Form kapıyı geçene kadar HİÇBİR <form> öğesi çizilmez.
 *
 * $args:
 * - fields (array) optional list of field label strings (eklenti pasifken yedek)
 * - form (array|null) optional MaviBelge_Core_Forms_Service::describe() DTO'su
 * - presentation (array) optional Faz 13 sunum kaydı; family 'split' ise içerik + form kartı iki sütunda (statik
 *   itiraz-sikayet.html düzeni). Kapı/alan davranışı DEĞİŞMEZ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
$form   = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : null;
$split  = isset( $args['presentation']['family'] ) && 'split' === $args['presentation']['family'];
if ( $form ) {
	$fields = array();
	foreach ( $form['fields'] as $form_field ) {
		if ( 'consent' !== $form_field['type'] ) {
			$fields[] = $form_field['label'];
		}
	}
}
?>
<?php if ( $split ) : ?>
<section class="section-tight mb-section">
<div class="container mb-body mb-body--wide" data-mb-family="split">
<?php else : ?>
<div class="container section-tight">
<?php endif; ?>
	<?php if ( $split ) : ?>
		<div class="mb-split<?php echo trim( (string) get_the_content() ) ? '' : ' mb-split--single'; ?>">
		<?php if ( trim( (string) get_the_content() ) ) : ?>
		<div class="entry-content mb-list mb-list--numbered">
			<?php echo mavibelge_rendered_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı. ?>
		</div>
		<?php endif; ?>
	<?php elseif ( trim( (string) get_the_content() ) ) : ?>
		<div class="entry-content content-narrow">
			<?php echo mavibelge_rendered_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı. ?>
		</div>
	<?php endif; ?>

	<div class="form-card">
		<?php
		get_template_part(
			'template-parts/components/alert',
			null,
			array(
				'type'    => 'alert',
				'variant' => 'info',
				'title'   => __( 'Bu form şu anda kullanılamıyor', 'mavibelge' ),
				'message' => __( 'Bu form şu anda yayında değildir; gerçek veri gönderemezsiniz. Aşağıdaki liste, formun açıldığında hangi bilgileri isteyeceğini önizler. Bize telefon veya e-posta ile de ulaşabilirsiniz.', 'mavibelge' ),
			)
		);
		if ( $form && current_user_can( 'manage_options' ) && ! empty( $form['reason_labels'] ) ) :
			?>
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

		<?php if ( ! empty( $fields ) ) : ?>
			<ul class="icon-list">
				<?php foreach ( $fields as $field_label ) : ?>
					<li>
						<span class="bullet" aria-hidden="true">•</span>
						<span><?php echo esc_html( $field_label ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php if ( $split ) : ?>
		</div>
	<?php endif; ?>
</div>
<?php if ( $split ) : ?>
</section>
<?php endif; ?>
