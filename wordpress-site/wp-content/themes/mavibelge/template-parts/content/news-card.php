<?php
/**
 * Haber/duyuru kartı — MaviBelge_Core_Content_Service::build_news_dto() DTO'sundan render edilir
 * (kendi sorgusu/meta okuması yok).
 *
 * $args:
 * - item (array, zorunlu) haber DTO'su
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item = isset( $args['item'] ) && is_array( $args['item'] ) ? $args['item'] : array();
if ( empty( $item['id'] ) ) {
	return;
}

$badges = '';
if ( ! empty( $item['type_label'] ) ) {
	$badges .= '<span class="badge' . ( 'duyuru' === $item['type_slug'] ? ' badge-duyuru' : '' ) . '">' . esc_html( $item['type_label'] ) . '</span> ';
}
if ( ! empty( $item['date_display'] ) ) {
	$badges .= '<time class="badge badge-neutral" datetime="' . esc_attr( $item['date_iso'] ) . '">' . esc_html( $item['date_display'] ) . '</time>';
}

get_template_part(
	'template-parts/components/card',
	null,
	array(
		'title'        => $item['title'],
		'url'          => $item['permalink'],
		'content'      => $item['excerpt'],
		'content_html' => '' !== $badges ? '<div class="card-badges">' . $badges . '</div>' : '',
	)
);
