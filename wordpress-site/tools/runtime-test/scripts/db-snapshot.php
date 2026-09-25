<?php
/**
 * SALT OKUNUR veritabanı anlık görüntüsü (`wp eval-file`). Parola
 * gerektirmez: WordPress'in mevcut $wpdb bağlantısını kullanır.
 * Çıktı (stdout): her tablo için SHOW CREATE TABLE (AUTO_INCREMENT dahil),
 * satır sayısı, CHECKSUM TABLE EXTENDED ve birincil anahtara göre sıralı
 * tüm satırların JSON satırları. Hiçbir şey yazmaz.
 */
global $wpdb;
$tables = $wpdb->get_col( $wpdb->prepare( 'SELECT table_name FROM information_schema.tables WHERE table_schema = %s ORDER BY table_name', DB_NAME ) );
foreach ( $tables as $t ) {
	$create = $wpdb->get_row( "SHOW CREATE TABLE `{$t}`", ARRAY_N );
	$count  = $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" );
	$sum    = $wpdb->get_row( "CHECKSUM TABLE `{$t}` EXTENDED", ARRAY_N );
	echo "## TABLE {$t} rows={$count} checksum={$sum[1]}\n";
	echo $create[1], "\n";
	$pk = $wpdb->get_col( "SELECT column_name FROM information_schema.key_column_usage WHERE table_schema = '" . esc_sql( DB_NAME ) . "' AND table_name = '" . esc_sql( $t ) . "' AND constraint_name = 'PRIMARY' ORDER BY ordinal_position" );
	$order = $pk ? ' ORDER BY `' . implode( '`,`', $pk ) . '`' : '';
	foreach ( $wpdb->get_results( "SELECT * FROM `{$t}`{$order}", ARRAY_A ) as $row ) {
		echo wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
	}
}
