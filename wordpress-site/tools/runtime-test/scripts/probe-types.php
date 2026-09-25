<?php
// wp eval-file — SALT OKUNUR: gerçek WordPress ID ve meta dönüş tipleri.
$t = function ( $v ) {
	return is_array( $v ) ? 'array(' . implode( ',', array_map( 'gettype', $v ) ) . ')' : gettype( $v );
};
$o = array();
$o['get_terms_ids'] = $t( get_terms( array( 'taxonomy' => 'mb_sektor', 'hide_empty' => false, 'fields' => 'ids', 'meta_key' => '_mb_import_source_key', 'meta_value' => 'sector:plastik' ) ) );
$o['get_posts_ids'] = $t( get_posts( array( 'post_type' => 'mb_yeterlilik', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_mb_import_source_key', 'meta_value' => 'qualification:12UY0069-3/02' ) ) );
$qid = get_posts( array( 'post_type' => 'mb_yeterlilik', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_mb_import_source_key', 'meta_value' => 'qualification:12UY0069-3/02' ) );
$qid = $qid[0];
$o['wp_get_post_terms_ids'] = $t( wp_get_post_terms( $qid, 'mb_sektor', array( 'fields' => 'ids' ) ) );
$term = get_term_by( 'slug', 'plastik', 'mb_sektor' );
$o['get_term_by_term_id'] = gettype( $term->term_id );
$o['get_term_meta_int_like'] = $t( get_term_meta( $term->term_id, '_mb_image_attachment_id', true ) ) . ' ' . var_export( get_term_meta( $term->term_id, '_mb_image_attachment_id', true ), true );
$o['get_post_meta_level'] = var_export( get_post_meta( $qid, '_mb_level', true ), true );
$o['get_post_meta_missing'] = var_export( get_post_meta( $qid, '_mb_yok_boyle_bir_alan', true ), true );
$cam = get_term_by( 'slug', 'cam', 'mb_sektor' );
$o['get_term_meta_marker_rows_single'] = $t( get_term_meta( $cam->term_id, '_mb_import_source_key', true ) );
$o['get_term_meta_marker_rows_all'] = $t( get_term_meta( $cam->term_id, '_mb_import_source_key', false ) );
$enerji = get_term_by( 'slug', 'enerji', 'mb_sektor' );
$o['get_term_meta_icon_serialized_array'] = $t( get_term_meta( $enerji->term_id, '_mb_icon_key', true ) );
$fee = get_posts( array( 'post_type' => 'mb_ucret', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_mb_pricing_type', 'posts_per_page' => 1 ) );
$o['get_post_meta_vat'] = var_export( get_post_meta( $fee[0], '_mb_vat_included', true ), true );
$o['get_post_meta_price_options'] = $t( get_post_meta( $fee[0], '_mb_price_options', true ) );
$att = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ) );
$o['attachment_ids'] = $t( $att ) . ' post_type=' . get_post_type( $att[0] );
echo wp_json_encode( $o, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), "\n";
