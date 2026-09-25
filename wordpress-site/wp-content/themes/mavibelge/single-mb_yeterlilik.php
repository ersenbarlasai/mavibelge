<?php
/**
 * mb_yeterlilik single — WordPress equivalent of tanitim-site's
 * yeterlilik.html detail layout. Shows only fields that are real, saved
 * meta keys per docs/content-model.md (_mb_myk_code, _mb_level,
 * _mb_revision, mb_sektor terms) plus the editor content. No fee/price
 * is shown or computed via a direct query here — mb_ucret is a
 * separate, non-public CPT, and this template never queries it itself.
 *
 * Faz 5: the fee section below calls
 * mavibelge_get_active_fees_for_qualification(), which matches a fee
 * ONLY via the real, stored _mb_qualification_id relation — never a
 * name/MYK-code similarity guess (see docs/catalog-service-contract.md).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id  = get_the_ID();
	$myk_code = get_post_meta( $post_id, '_mb_myk_code', true );
	$level    = get_post_meta( $post_id, '_mb_level', true );
	$revision = get_post_meta( $post_id, '_mb_revision', true );
	$sectors  = taxonomy_exists( 'mb_sektor' ) ? get_the_terms( $post_id, 'mb_sektor' ) : array();
	if ( ! is_array( $sectors ) ) {
		$sectors = array();
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
						/*
						 * Real registered rewrite slug for mb_yeterlilik is
						 * "yeterlilikler" (includes/class-content-types.php),
						 * not "meslekler" — get_post_type_archive_link()
						 * always resolves to whatever WordPress actually
						 * registered, so this link is never a guess even if
						 * the eventual public IA renames the nav label. See
						 * docs/page-template-map.md for the meslekler.html
						 * planned-path vs. real-slug note.
						 */
						'label' => __( 'Meslekler ve Belgeler', 'mavibelge' ),
						'url'   => (string) get_post_type_archive_link( 'mb_yeterlilik' ),
					),
					array( 'label' => get_the_title() ),
				),
			)
		);
		?>

		<h1><?php the_title(); ?></h1>

		<ul class="qual-facts">
			<?php if ( $myk_code ) : ?>
				<li><strong><?php esc_html_e( 'MYK Kodu', 'mavibelge' ); ?></strong><?php echo esc_html( $myk_code ); ?></li>
			<?php endif; ?>
			<?php if ( $level ) : ?>
				<li><strong><?php esc_html_e( 'Seviye', 'mavibelge' ); ?></strong><?php echo esc_html( $level ); ?></li>
			<?php endif; ?>
			<?php if ( $revision ) : ?>
				<li><strong><?php esc_html_e( 'Revizyon', 'mavibelge' ); ?></strong><?php echo esc_html( $revision ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $sectors ) ) : ?>
				<li>
					<strong><?php esc_html_e( 'Sektör', 'mavibelge' ); ?></strong>
					<?php echo esc_html( implode( ', ', wp_list_pluck( $sectors, 'name' ) ) ); ?>
				</li>
			<?php endif; ?>
		</ul>

		<div class="entry-content">
			<?php the_content(); ?>
		</div>

		<?php
		$fees = mavibelge_get_active_fees_for_qualification( $post_id );
		if ( ! empty( $fees ) ) :
			?>
			<div class="qual-fee-section">
				<h2><?php esc_html_e( 'Sınav ve Belgelendirme Ücreti', 'mavibelge' ); ?></h2>
				<?php foreach ( $fees as $fee ) : ?>
					<?php get_template_part( 'template-parts/catalog/fee-options', null, array( 'fee' => $fee ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php
		else :
			get_template_part(
				'template-parts/components/alert',
				null,
				array(
					'type'    => 'alert',
					'variant' => 'info',
					'message' => __( 'Bu yeterlilik için şu an güncel bir ücret kaydı bulunmuyor.', 'mavibelge' ),
				)
			);
			?>
			<p>
				<a href="<?php echo esc_url( home_url( '/sinav-ucretleri/' ) ); ?>"><?php esc_html_e( 'Sınav Ücretleri sayfasına git →', 'mavibelge' ); ?></a>
			</p>
			<?php
		endif;
		?>

		<?php if ( ! $myk_code && ! $level && empty( $sectors ) ) : ?>
			<?php
			get_template_part(
				'template-parts/components/alert',
				null,
				array(
					'type'    => 'alert',
					'variant' => 'info',
					'message' => __( 'Bu kayıt için henüz ayrıntılı bilgi girilmemiş.', 'mavibelge' ),
				)
			);
			?>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
