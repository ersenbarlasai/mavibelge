<?php
/**
 * Standard WordPress search results. This is WordPress's real search
 * (title/content match across public post types) shown in the approved
 * card layout — NOT the static demo's meslek-only autosuggest widget
 * (tanitim-site/assets/js/search.js), which is Faz 5 scope and would
 * need its own scoped, real backend logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;

get_header();
?>

<div class="container section-tight">
	<?php
	get_template_part(
		'template-parts/components/section-heading',
		null,
		array(
			'title' => sprintf(
				/* translators: %s: the submitted search term */
				__( '"%s" için arama sonuçları', 'mavibelge' ),
				get_search_query()
			),
			'level' => 1,
		)
	);
	?>

	<div class="search-box">
		<?php get_search_form(); ?>
	</div>

	<?php if ( have_posts() ) : ?>
		<div class="card-grid-3">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part(
					'template-parts/components/card',
					null,
					array(
						'title'   => get_the_title(),
						'content' => wp_strip_all_tags( get_the_excerpt() ),
						'url'     => get_permalink(),
					)
				);
			endwhile;
			?>
		</div>

		<?php
		$big = 999999999;
		$links = paginate_links(
			array(
				'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
				'current'   => max( 1, get_query_var( 'paged' ) ),
				'total'     => $wp_query->max_num_pages,
				'type'      => 'array',
				'prev_text' => __( '‹ Önceki', 'mavibelge' ),
				'next_text' => __( 'Sonraki ›', 'mavibelge' ),
			)
		);
		if ( ! empty( $links ) ) :
			?>
			<nav class="pagination" aria-label="<?php esc_attr_e( 'Sayfalama', 'mavibelge' ); ?>">
				<?php foreach ( $links as $link ) : ?>
					<?php echo wp_kses_post( str_replace( 'page-numbers', 'page-btn', $link ) ); ?>
				<?php endforeach; ?>
			</nav>
			<?php
		endif;
		?>
	<?php else : ?>
		<?php
		get_template_part(
			'template-parts/components/alert',
			null,
			array(
				'type'    => 'empty-state',
				'title'   => __( 'Sonuç bulunamadı', 'mavibelge' ),
				'message' => __( 'Farklı bir anahtar kelime ile tekrar deneyin ya da meslek listesine göz atın.', 'mavibelge' ),
			)
		);
		?>
	<?php endif; ?>
</div>

<?php
get_footer();
