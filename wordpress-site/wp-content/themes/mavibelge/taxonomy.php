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
$tax_title    = is_a( $term, 'WP_Term' ) ? $term->name : get_the_archive_title();
// Faz 13: ortak page-hero. Haber türü terimleri (duyurular.html) kayıttan eyebrow/kırıntı alır; lead önce terim açıklaması.
if ( $is_haber_tax ) {
	$hero_args = mavibelge_archive_hero_args( 'haber-turu', $tax_title, term_description( $term ) );
} else {
	$hero_args = array( 'title' => $tax_title, 'breadcrumb_items' => array( array( 'label' => __( 'Anasayfa', 'mavibelge' ), 'url' => home_url( '/' ) ), array( 'label' => $tax_title ) ) );
}
get_template_part( 'template-parts/page/page-hero', null, $hero_args );
?>

<section class="section-tight mb-archive">
<div class="container">
	<h2 class="screen-reader-text"><?php esc_html_e( 'Kayıt listesi', 'mavibelge' ); ?></h2>
	<?php
	if ( have_posts() ) :
		?>
		<div class="<?php echo $is_haber_tax ? 'news-grid' : 'card-grid-3'; ?>">
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
</section>

<?php
get_footer();
