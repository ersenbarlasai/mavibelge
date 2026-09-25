<?php
/**
 * Generic taxonomy archive — used for any term archive without its own
 * taxonomy-{taxonomy}.php (mb_sektor has one: taxonomy-mb_sektor.php).
 *
 * This is where "duyurular.html" really lives in WordPress: the
 * mb_haber_turu term "duyuru" (rewrite base "haber-turu", so the real
 * URL is /haber-turu/duyuru/, NOT /duyurular/ — see
 * docs/page-template-map.md for that planned-path-vs-real-URL note; no
 * rewrite/redirect is added by this theme, that is a Faz 9 decision).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term         = get_queried_object();
$is_haber_tax = is_a( $term, 'WP_Term' ) && 'mb_haber_turu' === $term->taxonomy;
?>

<div class="container section-tight">
	<?php
	get_template_part(
		'template-parts/components/section-heading',
		null,
		array(
			'eyebrow' => $is_haber_tax ? __( 'Bilgi Merkezi', 'mavibelge' ) : '',
			'title'   => is_a( $term, 'WP_Term' ) ? $term->name : get_the_archive_title(),
			'level'   => 1,
		)
	);

	if ( have_posts() ) :
		?>
		<div class="card-grid-3">
			<?php
			while ( have_posts() ) :
				the_post();
				if ( $is_haber_tax ) {
					get_template_part(
						'template-parts/content/content-card',
						null,
						array( 'post_id' => get_the_ID() )
					);
				} else {
					get_template_part(
						'template-parts/components/card',
						null,
						array(
							'title'   => get_the_title(),
							'content' => wp_strip_all_tags( get_the_excerpt() ),
							'url'     => get_permalink(),
						)
					);
				}
			endwhile;
			?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<?php
		get_template_part(
			'template-parts/content/content-none',
			null,
			array(
				'title'   => __( 'İçerik bulunamadı', 'mavibelge' ),
				'message' => __( 'Bu bölümde henüz yayınlanmış bir kayıt yok.', 'mavibelge' ),
			)
		);
	endif;
	?>
</div>

<?php
get_footer();
