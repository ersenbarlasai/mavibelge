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

	// Faz 12f: doğrulanmış sayfalarda kahraman eyebrow/açıklama ve Anasayfa'dan başlayan kırıntı (inc/page-layouts.php).
	$hero   = function_exists( 'mavibelge_page_hero_for_slug' ) ? mavibelge_page_hero_for_slug( $slug ) : array();
	$crumbs = array();
	if ( ! empty( $hero ) ) {
		$crumbs[] = array( 'label' => __( 'Anasayfa', 'mavibelge' ), 'url' => home_url( '/' ) );
		foreach ( isset( $hero['parents'] ) ? $hero['parents'] : array() as $parent ) {
			$crumbs[] = array( 'label' => $parent['label'], 'url' => mavibelge_url( $parent['path'] ) );
		}
	}
	$crumbs[] = array( 'label' => get_the_title() );

	get_template_part(
		'template-parts/page/page-hero',
		null,
		array(
			'eyebrow'          => isset( $hero['eyebrow'] ) ? $hero['eyebrow'] : '',
			'title'            => get_the_title(),
			'description'      => isset( $hero['description'] ) ? $hero['description'] : '',
			'breadcrumb_items' => $crumbs,
		)
	);

	if ( 'hub' === $layout ) {
		$links = function_exists( 'mavibelge_hub_links_for_slug' ) ? mavibelge_hub_links_for_slug( $slug ) : array();
		get_template_part( 'template-parts/page/content-hub', null, array( 'links' => $links ) );
	} elseif ( 'form-disabled' === $layout ) {
		$fields = function_exists( 'mavibelge_form_shell_fields_for_slug' ) ? mavibelge_form_shell_fields_for_slug( $slug ) : array();
		$form   = function_exists( 'mavibelge_form_dto' ) ? mavibelge_form_dto( $slug ) : null;
		if ( 'iletisim' === $slug ) {
			// Faz 12g: tanitim-site/iletisim.html düzeni (lokasyonlar + sosyal medya + "Bize Yazın"). Kapı kararı yine
			// DTO'nun `open` alanıdır; kapalıyken <form> çizilmez. Lokasyonlar bu gövdede TEK kez çizilir.
			get_template_part( 'template-parts/page/content-contact', null, array( 'form' => $form ) );
		} elseif ( 'online-basvuru' === $slug && is_array( $form ) ) {
			// Faz 12f: tanitim-site/online-basvuru.html düzeni. Kapı kararı yine DTO'nun `open` alanıdır
			// (MaviBelge_Core_Forms_Config::gate()); kapalıyken <form> çizilmez, yalnız önizleme.
			get_template_part( 'template-parts/page/content-application', null, array( 'form' => $form ) );
		} elseif ( is_array( $form ) && ( $form['open'] || 'success' === $form['status'] ) ) {
			// Kapı AÇIK (veya PRG sonrası başarı ekranı): gerçek form.
			get_template_part( 'template-parts/page/content-form-live', null, array( 'form' => $form ) );
		} else {
			get_template_part( 'template-parts/page/content-form-disabled', null, array( 'fields' => $fields, 'form' => $form ) );
		}
	} else {
		get_template_part( 'template-parts/page/content-default' );
	}
endwhile;

get_footer();
