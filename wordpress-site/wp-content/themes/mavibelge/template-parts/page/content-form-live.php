<?php
/**
 * Açık (kapıyı geçmiş) form sayfası gövdesi: editör girişi + durum iletisi + gerçek form. PRG sonrası
 * başarı ekranında yalnız başarı iletisi gösterilir (form tekrar çizilmez).
 *
 * $args:
 * - form (array, zorunlu) MaviBelge_Core_Forms_Service::describe() DTO'su
 * - presentation (array) optional Faz 13 sunum kaydı; family 'split' ise içerik + form iki sütunda. Form işaretlemesi,
 *   nonce, jeton, alan adları ve PRG DEĞİŞMEZ (template-parts/forms/form.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form  = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
$split = isset( $args['presentation']['family'] ) && 'split' === $args['presentation']['family'];
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
		<div class="mb-split-form">
	<?php elseif ( trim( (string) get_the_content() ) ) : ?>
		<div class="entry-content content-narrow">
			<?php echo mavibelge_rendered_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı. ?>
		</div>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/forms/form-status', null, array( 'form' => $form ) ); ?>
	<?php if ( 'success' !== $form['status'] ) : ?>
		<?php get_template_part( 'template-parts/forms/form', null, array( 'form' => $form ) ); ?>
	<?php endif; ?>
	<?php if ( $split ) : ?>
		</div>
		</div>
	<?php endif; ?>
</div>
<?php if ( $split ) : ?>
</section>
<?php endif; ?>
