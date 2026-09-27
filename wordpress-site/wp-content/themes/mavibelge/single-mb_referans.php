<?php
/**
 * mb_referans single. Logo ve web sitesi build_reference_dto() DTO'sundan gelir: logo yalnız gerçek görsel
 * attachment (SVG hariç), web sitesi yalnız https bağlantıdır. "Temsili Görsel" rozeti `real` olmayan her
 * referansta gösterilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$ref     = mavibelge_content_dto( 'reference', get_post() );
	$is_real = isset( $ref['status'] ) && 'real' === $ref['status'];
	?>
	<div class="container section-tight">
		<?php
		get_template_part(
			'template-parts/components/breadcrumb',
			null,
			array(
				'items' => array(
					array( 'label' => __( 'Referanslarımız', 'mavibelge' ), 'url' => mavibelge_url( 'referanslar' ) ),
					array( 'label' => get_the_title() ),
				),
			)
		);
		?>

		<?php if ( ! empty( $ref['logo_id'] ) ) : ?>
			<?php echo wp_get_attachment_image( $ref['logo_id'], 'medium', false, array( 'alt' => get_the_title() ) ); ?>
		<?php endif; ?>

		<h1><?php the_title(); ?></h1>

		<?php
		get_template_part(
			'template-parts/components/badge',
			null,
			array(
				'label'   => $is_real ? __( 'Gerçek Referans', 'mavibelge' ) : __( 'Temsili Görsel', 'mavibelge' ),
				'variant' => $is_real ? '' : 'neutral',
			)
		);
		?>

		<?php if ( ! empty( $ref['website_url'] ) ) : ?>
			<p><a class="link-ext" href="<?php echo esc_url( $ref['website_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $ref['website_url'] ); ?><span class="screen-reader-text"> <?php esc_html_e( '(yeni sekmede açılır)', 'mavibelge' ); ?></span></a></p>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
