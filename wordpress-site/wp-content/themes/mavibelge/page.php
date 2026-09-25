<?php
/**
 * Generic WordPress Page template. Routes to one of three body layouts
 * by slug (inc/page-layouts.php) rather than shipping ~20 near-identical
 * page-{slug}.php files or one giant switch — see
 * docs/template-architecture.md. `page-referanslar.php` and
 * `page-sss.php` are separate real WordPress template-hierarchy files
 * (not routed through here) because they run their own bounded CPT
 * queries, which this generic file does not do.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id = get_the_ID();
	$slug    = get_post_field( 'post_name', $post_id );
	$layout  = function_exists( 'mavibelge_page_layout_for_slug' ) ? mavibelge_page_layout_for_slug( $slug ) : 'default';

	get_template_part(
		'template-parts/page/page-hero',
		null,
		array(
			'title'            => get_the_title(),
			'breadcrumb_items' => array( array( 'label' => get_the_title() ) ),
		)
	);

	if ( 'hub' === $layout ) {
		$links = function_exists( 'mavibelge_hub_links_for_slug' ) ? mavibelge_hub_links_for_slug( $slug ) : array();
		get_template_part( 'template-parts/page/content-hub', null, array( 'links' => $links ) );
	} elseif ( 'form-disabled' === $layout ) {
		$fields = function_exists( 'mavibelge_form_shell_fields_for_slug' ) ? mavibelge_form_shell_fields_for_slug( $slug ) : array();
		$form   = function_exists( 'mavibelge_form_dto' ) ? mavibelge_form_dto( $slug ) : null;
		if ( is_array( $form ) && ( $form['open'] || 'success' === $form['status'] ) ) {
			// Kapı AÇIK (veya PRG sonrası başarı ekranı): gerçek form.
			get_template_part( 'template-parts/page/content-form-live', null, array( 'form' => $form ) );
		} else {
			get_template_part( 'template-parts/page/content-form-disabled', null, array( 'fields' => $fields, 'form' => $form ) );
		}
		if ( 'iletisim' === $slug ) {
			// Lokasyon/iletişim verisinin merkezi yönetimi: mb_lokasyon kayıtları varsa gösterilir.
			get_template_part( 'template-parts/content/location-list' );
		}
	} else {
		get_template_part( 'template-parts/page/content-default' );
	}
endwhile;

get_footer();
