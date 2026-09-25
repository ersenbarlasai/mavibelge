<?php
// wp eval-file — SALT OKUNUR kayıt doğrulaması (hiçbir şey yazmaz).
$out = array();
foreach ( array( 'mb_yeterlilik', 'mb_ucret', 'mb_haber', 'mb_dokuman', 'mb_sss', 'mb_referans', 'mb_kurul' ) as $pt ) {
	$out['post_types'][ $pt ] = post_type_exists( $pt );
}
foreach ( array( 'mb_sektor', 'mb_seviye', 'mb_haber_turu', 'mb_dokuman_turu' ) as $tx ) {
	$out['taxonomies'][ $tx ] = taxonomy_exists( $tx );
}
$out['all_mb_post_types'] = array_values( array_filter( get_post_types(), function ( $p ) { return 0 === strpos( $p, 'mb_' ); } ) );
$out['all_mb_taxonomies'] = array_values( array_filter( get_taxonomies(), function ( $t ) { return 0 === strpos( $t, 'mb_' ); } ) );
$reg = get_registered_meta_keys( 'term', 'mb_sektor' );
$out['term_meta_mb_sektor'] = array_keys( $reg );
foreach ( array( 'mb_yeterlilik', 'mb_ucret' ) as $pt ) {
	$out['post_meta'][ $pt ] = array_keys( get_registered_meta_keys( 'post', $pt ) );
}
$out['roles'] = array_keys( wp_roles()->roles );
$out['admin_can_manage_options'] = user_can( 1, 'manage_options' );
$out['cli_class_loaded'] = class_exists( 'MaviBelge_Core_Import_CLI_Command' );
$out['repository_implements_interface'] = in_array( 'MaviBelge_Core_Import_Target_Repository', class_implements( 'MaviBelge_Core_Import_WordPress_Target_Repository' ), true );
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), "\n";
