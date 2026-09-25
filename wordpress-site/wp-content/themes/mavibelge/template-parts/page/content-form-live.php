<?php
/**
 * Açık (kapıyı geçmiş) form sayfası gövdesi: editör girişi + durum iletisi + gerçek form. PRG sonrası
 * başarı ekranında yalnız başarı iletisi gösterilir (form tekrar çizilmez).
 *
 * $args:
 * - form (array, zorunlu) MaviBelge_Core_Forms_Service::describe() DTO'su
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
?>
<div class="container section-tight">
	<?php if ( trim( (string) get_the_content() ) ) : ?>
		<div class="entry-content content-narrow">
			<?php the_content(); ?>
		</div>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/forms/form-status', null, array( 'form' => $form ) ); ?>
	<?php if ( 'success' !== $form['status'] ) : ?>
		<?php get_template_part( 'template-parts/forms/form', null, array( 'form' => $form ) ); ?>
	<?php endif; ?>
</div>
