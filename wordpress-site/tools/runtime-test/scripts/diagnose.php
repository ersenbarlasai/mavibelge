<?php
// wp eval-file — SALT OKUNUR tanılama: sapan senaryoların ham meta değerleri ve
// saf kurucuların hangi alanda başarısız olduğu. Hiçbir şey yazmaz.
global $wpdb;
$o = array();
$o['cross_type_markers_raw'] = $wpdb->get_results( "SELECT p.ID, p.post_type, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE m.meta_key='_mb_import_source_key' AND ( (p.post_type='mb_ucret' AND m.meta_value NOT LIKE 'fee:%') OR (p.post_type='mb_yeterlilik' AND m.meta_value NOT LIKE 'qualification:%') )", ARRAY_A );
$o['fixture_titles_with_marker_rows'] = $wpdb->get_results( "SELECT p.ID, p.post_type, p.post_title, (SELECT COUNT(*) FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_mb_import_source_key') AS marker_rows, (SELECT meta_value FROM {$wpdb->postmeta} m WHERE m.post_id=p.ID AND m.meta_key='_mb_import_source_key' LIMIT 1) AS marker FROM {$wpdb->posts} p WHERE p.post_title LIKE 'fixture yanlış tür%'", ARRAY_A );

$repo = new MaviBelge_Core_Import_WordPress_Target_Repository();
foreach ( array( 'qualification:12UY0069-3/02', 'qualification:16UY0244-4/02' ) as $key ) {
	$ids = get_posts( array( 'post_type' => 'mb_yeterlilik', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_mb_import_source_key', 'meta_value' => $key ) );
	$pid = $ids[0];
	$raw = array(
		'title' => get_post( $pid )->post_title,
		'myk_code' => get_post_meta( $pid, '_mb_myk_code', true ),
		'level' => get_post_meta( $pid, '_mb_level', true ),
		'revision' => get_post_meta( $pid, '_mb_revision', true ),
		'record_status' => get_post_meta( $pid, '_mb_record_status', true ),
		'sector_term_ids' => wp_get_post_terms( $pid, 'mb_sektor', array( 'fields' => 'ids' ) ),
	);
	$fields = MaviBelge_Core_Import_WordPress_Target_Repository::qualification_fields_from_raw( $raw );
	$lookup = $repo->find_target_by_source_key( 'qualification', $key );
	$norm   = MaviBelge_Core_Import_Record_Validator::normalize_target_lookup( $lookup, 'qualification' );
	$o[ $key ] = array( 'raw' => $raw, 'fields_ok' => null !== $fields, 'lookup_keys' => array_keys( $lookup ), 'current_fields' => $lookup['current_managed_fields'] ?? 'N/A', 'target_state_valid' => $norm['target_state_valid'] );
}
foreach ( array( 'fee:guzellik-sac-bakim:4:guzellik-uzmani', 'fee:guzellik-sac-bakim:3:cilt-bakim-uygulayicisi' ) as $key ) {
	$ids = get_posts( array( 'post_type' => 'mb_ucret', 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => '_mb_import_source_key', 'meta_value' => $key ) );
	$pid = $ids[0];
	$raw = array();
	foreach ( get_post_meta( $pid ) as $k => $v ) {
		if ( 0 === strpos( $k, '_mb_' ) ) {
			$raw[ $k ] = maybe_unserialize( $v[0] );
		}
	}
	$lookup = $repo->find_target_by_source_key( 'fee', $key );
	$norm   = MaviBelge_Core_Import_Record_Validator::normalize_target_lookup( $lookup, 'fee' );
	$o[ $key ] = array( 'post_title' => get_post( $pid )->post_title, 'raw_meta' => $raw, 'current_fields' => $lookup['current_managed_fields'] ?? 'N/A', 'target_state_valid' => $norm['target_state_valid'] );
}
echo wp_json_encode( $o, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), "\n";
