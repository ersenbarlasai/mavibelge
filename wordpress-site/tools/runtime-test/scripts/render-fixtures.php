<?php
/**
 * TEST FIXTURE — yalnız izole runtime test veritabanı (`wp eval-file --user=mbadmin`).
 * Tema HTTP render testleri için `plastik`
 * sektöründe 14 yayımlanmış test yeterliliği (sayfa boyutu 12 -> 2 sayfa).
 * Kodlar manifestte OLMAYAN `99UY90NN-3/01` ailesindendir ve import
 * marker'ı taşımaz; bu yüzden Faz 6B2 dry-run eşleşmelerini etkilemez.
 * Gerçek kayıt yolu kullanılır: wp_insert_post + meta_input + tax_input
 * (yayın hazırlık kapısı dahil).
 */
// Kalıcı bağlantılar varsayılan (plain) bırakılır: test imajında mod_rewrite
// etkin değil; tema bağlantıları get_term_link() ile sorgu biçiminde üretilir.

$term = get_term_by( 'slug', 'plastik', 'mb_sektor' );
if ( ! $term ) {
	fwrite( STDERR, "plastik terimi yok\n" );
	exit( 1 );
}
$published = 0;
for ( $i = 1; $i <= 14; $i++ ) {
	$code = sprintf( '99UY90%02d-3/01', $i );
	$pid  = wp_insert_post(
		array(
			'post_type'   => 'mb_yeterlilik',
			'post_status' => 'publish',
			'post_title'  => sprintf( 'Render Test Mesleği %02d', $i ),
			'meta_input'  => array(
				'_mb_myk_code'      => $code,
				'_mb_level'         => '3',
				'_mb_revision'      => '01',
				'_mb_record_status' => 'active',
			),
			'tax_input'   => array( 'mb_sektor' => array( (int) $term->term_id ) ),
		),
		true
	);
	if ( is_wp_error( $pid ) ) {
		fwrite( STDERR, 'yazı oluşturulamadı: ' . $pid->get_error_message() . "\n" );
		exit( 1 );
	}
	wp_cache_delete( $pid, 'post_meta' );
	if ( 'publish' === get_post_status( $pid ) && '3' === get_post_meta( $pid, '_mb_level', true ) ) {
		$published++;
	}
}
echo wp_json_encode( array( 'permalink' => get_option( 'permalink_structure' ), 'published_with_level_3' => $published, 'term_link' => get_term_link( $term ) ) ), "\n";
