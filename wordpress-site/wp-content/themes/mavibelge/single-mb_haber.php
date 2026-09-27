<?php
/**
 * mb_haber single — WordPress equivalent of tanitim-site's
 * haber-detay.html. Uses the post's real featured image (if set) via
 * the_post_thumbnail(); never a hardcoded <img> URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id       = get_the_ID();
	$updated_at    = get_post_meta( $post_id, '_mb_content_updated_at', true );
	$haber_turleri = taxonomy_exists( 'mb_haber_turu' ) ? get_the_terms( $post_id, 'mb_haber_turu' ) : array();
	if ( ! is_array( $haber_turleri ) ) {
		$haber_turleri = array();
	}
	?>
	<div class="container section-tight">
		<?php
		get_template_part(
			'template-parts/components/breadcrumb',
			null,
			array(
				'items' => array(
					array(
						'label' => __( 'Bilgi Merkezi', 'mavibelge' ),
						'url'   => mavibelge_url( 'bilgi-merkezi' ),
					),
					array(
						'label' => __( 'Haberler', 'mavibelge' ),
						'url'   => mavibelge_url( 'haberler' ),
					),
					array( 'label' => get_the_title() ),
				),
			)
		);
		?>

		<?php foreach ( $haber_turleri as $term ) : ?>
			<?php
			get_template_part(
				'template-parts/components/badge',
				null,
				array(
					'label'   => $term->name,
					'variant' => 'duyuru' === $term->slug ? 'duyuru' : '',
				)
			);
			?>
		<?php endforeach; ?>

		<h1><?php the_title(); ?></h1>

		<p class="entry-meta">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: publish date */
					__( 'Yayın: %s', 'mavibelge' ),
					get_the_date()
				)
			);
			if ( $updated_at ) {
				echo ' &middot; ' . esc_html(
					sprintf(
						/* translators: %s: last updated date */
						__( 'Güncelleme: %s', 'mavibelge' ),
						mysql2date( get_option( 'date_format' ), $updated_at )
					)
				);
			}
			?>
		</p>

		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'large' ); ?>
		<?php endif; ?>

		<div class="entry-content">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
