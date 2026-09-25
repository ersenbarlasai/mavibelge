<?php
/**
 * TEST — yalnız izole runtime test veritabanında `wp eval-file` ile.
 * `_mb_level` ve string select alanlarının GERÇEK kayıtlı WordPress meta
 * yollarındaki davranışı: (A) register_post_meta sanitize_callback yolu
 * (update_post_meta), (B) korumalı MaviBelge_Core_Field_Repository::write_meta().
 * Geçici test yazıları oluşturur ve sonunda kalıcı olarak siler (test fixture).
 */
$results = array();
$info    = array(); // Test DEĞİL — sözleşme gözlemi; sayıma girmez.
$t = function ( $label, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array( $label, (bool) $ok, $detail );
};
$show = function ( $v ) {
	return var_export( $v, true );
};

$created = array();
foreach ( array( 'mb_yeterlilik', 'mb_ucret' ) as $pt ) {
	$pid = wp_insert_post( array( 'post_type' => $pt, 'post_status' => 'draft', 'post_title' => 'level-meta-test ' . $pt ), true );
	if ( is_wp_error( $pid ) ) {
		fwrite( STDERR, "post oluşturulamadı\n" );
		exit( 1 );
	}
	$created[] = $pid;
	$get = function () use ( $pid ) {
		wp_cache_delete( $pid, 'post_meta' );
		return get_post_meta( $pid, '_mb_level', true );
	};

	// (A) Kayıtlı sanitize yolu.
	update_post_meta( $pid, '_mb_level', '3' );
	$t( "$pt A: update_post_meta(\"3\") -> \"3\"", '3' === $get(), $show( $get() ) );
	update_post_meta( $pid, '_mb_level', 4 );
	$t( "$pt A: update_post_meta(int 4) -> kanonik \"4\"", '4' === $get(), $show( $get() ) );
	foreach ( array( '"03"' => '03', '"0"' => '0', '"9"' => '9', '"1.0"' => '1.0', 'dizi' => array( '3' ), 'nesne' => (object) array( 'v' => '3' ) ) as $label => $bad ) {
		update_post_meta( $pid, '_mb_level', '5' );
		update_post_meta( $pid, '_mb_level', $bad );
		$v = $get();
		$t( "$pt A: update_post_meta($label) geçerli seviyeye dönüşmez", ! in_array( $v, array( '1', '2', '3', '4', '5', '6', '7', '8' ), true ) || '5' === $v, $show( $v ) );
		$info[] = "$pt A: önceki \"5\" üzerine update_post_meta($label) -> saklanan değer " . $show( $v );
	}

	// (B) Korumalı write_meta() yolu.
	$r = MaviBelge_Core_Field_Repository::write_meta( $pid, $pt, '_mb_level', '6' );
	$t( "$pt B: write_meta(\"6\") success + \"6\"", ! empty( $r['success'] ) && '6' === $get(), $show( $get() ) );
	$r = MaviBelge_Core_Field_Repository::write_meta( $pid, $pt, '_mb_level', 7 );
	$t( "$pt B: write_meta(int 7) success + kanonik \"7\"", ! empty( $r['success'] ) && '7' === $get(), $show( $get() ) );
	foreach ( array( '"03"' => '03', '"0"' => '0', '"9"' => '9', '"1e1"' => '1e1', 'dizi' => array( '3' ), 'nesne' => new stdClass() ) as $label => $bad ) {
		$r = MaviBelge_Core_Field_Repository::write_meta( $pid, $pt, '_mb_level', $bad );
		$t( "$pt B: write_meta($label) reddedilir ve önceki \"7\" korunur", empty( $r['success'] ) && '7' === $get(), $show( $get() ) );
	}
}

// String select regresyonu (gerçek kayıtlı yollar).
$q = $created[0];
$f = $created[1];
update_post_meta( $q, '_mb_record_status', 'passive' );
wp_cache_delete( $q, 'post_meta' );
$t( 'mb_yeterlilik string select: update_post_meta("passive") -> "passive"', 'passive' === get_post_meta( $q, '_mb_record_status', true ), $show( get_post_meta( $q, '_mb_record_status', true ) ) );
$r = MaviBelge_Core_Field_Repository::write_meta( $q, 'mb_yeterlilik', '_mb_record_status', 'active' );
wp_cache_delete( $q, 'post_meta' );
$t( 'mb_yeterlilik string select: write_meta("active") -> "active"', ! empty( $r['success'] ) && 'active' === get_post_meta( $q, '_mb_record_status', true ), '' );
$r = MaviBelge_Core_Field_Repository::write_meta( $q, 'mb_yeterlilik', '_mb_record_status', 'deleted' );
wp_cache_delete( $q, 'post_meta' );
$t( 'mb_yeterlilik string select: write_meta("deleted") reddedilir, "active" korunur', empty( $r['success'] ) && 'active' === get_post_meta( $q, '_mb_record_status', true ), '' );
$fFields = MaviBelge_Core_Meta_Schema::get_fields_for( 'mb_ucret' );
foreach ( array( '_mb_pricing_type', '_mb_record_status' ) as $key ) {
	if ( ! isset( $fFields[ $key ] ) || 'select' !== $fFields[ $key ]['type'] ) {
		continue;
	}
	foreach ( array_keys( $fFields[ $key ]['options'] ) as $opt ) {
		$r = MaviBelge_Core_Field_Repository::write_meta( $f, 'mb_ucret', $key, (string) $opt );
		wp_cache_delete( $f, 'post_meta' );
		$t( "mb_ucret string select: write_meta($key=\"$opt\") -> \"$opt\"", ! empty( $r['success'] ) && (string) $opt === get_post_meta( $f, $key, true ), '' );
	}
}

foreach ( $created as $pid ) {
	wp_delete_post( $pid, true );
}

foreach ( $info as $line ) {
	echo "INFO  {$line}\n";
}
$pass = 0;
foreach ( $results as $r ) {
	echo ( $r[1] ? 'PASS  ' : 'FAIL  ' ) . $r[0] . ( '' !== $r[2] ? '  [' . $r[2] . ']' : '' ) . "\n";
	$pass += $r[1] ? 1 : 0;
}
echo "\n{$pass}/" . count( $results ) . " _mb_level/select WordPress meta testi geçti.\n";
