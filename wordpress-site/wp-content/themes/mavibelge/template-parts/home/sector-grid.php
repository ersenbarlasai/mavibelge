<?php
/**
 * Homepage "Yetkilendirilmiş Sektörler" section. Real mb_sektor terms
 * only — no hardcoded sector list. If the taxonomy is missing (plugin
 * inactive) or no terms exist yet (Faz 6 seeding not done), renders an
 * honest empty state instead of the static demo's 14 fixed cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$terms = array();
if ( taxonomy_exists( 'mb_sektor' ) ) {
	$found = get_terms( array(
		'taxonomy'   => 'mb_sektor',
		'hide_empty' => false,
		'number'     => 24,
		'orderby'    => 'name',
	) );
	if ( ! is_wp_error( $found ) ) {
		$terms = $found;
	}
}
?>
<section class="bg-light" id="sektorler">
	<div class="container">
		<?php get_template_part( 'template-parts/components/section-heading', null, array(
			'title'       => __( 'Yetkilendirilmiş Sektörler', 'mavibelge' ),
			'description' => __( 'MYK tarafından yetkilendirildiğimiz sektörlerin tamamında sınav ve belgelendirme hizmeti veriyoruz.', 'mavibelge' ),
		) ); ?>

		<?php if ( ! empty( $terms ) ) : ?>
			<div class="sector-grid">
				<?php
				foreach ( $terms as $term ) :
					$term_link = get_term_link( $term );
					if ( is_wp_error( $term_link ) ) {
						continue;
					}
					?>
					<a class="sector-card" href="<?php echo esc_url( $term_link ); ?>">
						<div class="sector-icon" aria-hidden="true">
							<?php
							$icon_key = get_term_meta( $term->term_id, '_mb_icon_key', true );
							echo mavibelge_icon_svg( 'sector', is_string( $icon_key ) ? $icon_key : '', 'icon-22' ); // phpcs:ignore WordPress.Security.EscapeOutput -- sabit, kayıtlı SVG
							?>
						</div>
						<div>
							<strong><?php echo esc_html( $term->name ); ?></strong>
							<span><?php esc_html_e( 'Meslekleri İncele →', 'mavibelge' ); ?></span>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/content-none', null, array(
				'title'   => __( 'Sektör listesi henüz yayınlanmadı', 'mavibelge' ),
				'message' => __( 'Sektör taksonomi terimleri kurum onayının ardından Faz 6 veri aktarımında eklenecektir.', 'mavibelge' ),
			) ); ?>
		<?php endif; ?>
	</div>
</section>
