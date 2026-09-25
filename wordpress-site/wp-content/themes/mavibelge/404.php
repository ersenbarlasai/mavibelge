<?php
/**
 * 404 template. Visual/message parity with tanitim-site/404.html's
 * .error-page (error code, heading, message, search box, 3 recovery
 * links) — ported structure, not a copied full HTML document.
 *
 * The search box here is WordPress's own get_search_form() (a real,
 * working GET search — this is NOT the static demo's JS autosuggest
 * widget, which is Faz 5 scope); wrapping markup only adds the
 * .search-box class tanitim-site/assets/css/pages.css styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="error-page">
	<div class="container">
		<div class="error-code">404</div>
		<h1><?php esc_html_e( 'Sayfa Bulunamadı', 'mavibelge' ); ?></h1>
		<p><?php esc_html_e( 'Aradığınız sayfa taşınmış veya kaldırılmış olabilir.', 'mavibelge' ); ?></p>

		<div class="search-box">
			<?php get_search_form(); ?>
		</div>

		<div class="error-actions">
			<?php
			get_template_part(
				'template-parts/components/button',
				null,
				array(
					'label'   => __( 'Anasayfaya Dön', 'mavibelge' ),
					'url'     => home_url( '/' ),
					'variant' => 'primary',
				)
			);
			get_template_part(
				'template-parts/components/button',
				null,
				array(
					'label'   => __( 'Meslek Ara', 'mavibelge' ),
					// Real registered rewrite slug is "yeterlilikler", not
					// "meslekler" — see single-mb_yeterlilik.php's breadcrumb
					// comment and docs/page-template-map.md.
					'url'     => post_type_exists( 'mb_yeterlilik' ) ? (string) get_post_type_archive_link( 'mb_yeterlilik' ) : home_url( '/' ),
					'variant' => 'secondary',
				)
			);
			get_template_part(
				'template-parts/components/button',
				null,
				array(
					'label'   => __( 'İletişime Geç', 'mavibelge' ),
					'url'     => home_url( '/iletisim/' ),
					'variant' => 'secondary',
				)
			);
			?>
		</div>
	</div>
</section>

<?php
get_footer();
