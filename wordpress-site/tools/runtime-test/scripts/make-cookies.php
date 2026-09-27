<?php
/**
 * TEST — izole ortamda parola KULLANMADAN admin HTTP testleri için oturum
 * çerezi üretir (`wp eval-file`). Her test kullanıcısı için gerçek bir
 * WordPress oturum token'ı (usermeta session_tokens) oluşturur ve
 * auth + logged_in çerezlerini /tmp/mb-cookies-<login>.json dosyasına
 * (0600) yazar. Çerez değerleri stdout'a BASILMAZ. Bu yazı (oturum token'ı)
 * snapshot A'dan ÖNCE yapılır.
 */
$out = array();
// Faz 6B4: mbcapa (yalnız manage_options) ve mbcapb (yalnız mb_manage_tariff_period) İSTEĞE BAĞLIDIR (admin-import-http.sh oluşturur).
foreach ( array( 'mbadmin', 'mbeditor', 'mbsubscriber', 'mbcapa', 'mbcapb' ) as $login ) {
	$user = get_user_by( 'login', $login );
	if ( ! $user && in_array( $login, array( 'mbcapa', 'mbcapb' ), true ) ) {
		continue;
	}
	if ( ! $user ) {
		fwrite( STDERR, "kullanıcı yok: {$login}\n" );
		exit( 1 );
	}
	$exp   = time() + 4 * HOUR_IN_SECONDS;
	$token = WP_Session_Tokens::get_instance( $user->ID )->create( $exp );
	$jar   = array(
		'wordpress_test_cookie' => rawurlencode( 'WP Cookie check' ),
		AUTH_COOKIE             => rawurlencode( wp_generate_auth_cookie( $user->ID, $exp, 'auth', $token ) ),
		LOGGED_IN_COOKIE        => rawurlencode( wp_generate_auth_cookie( $user->ID, $exp, 'logged_in', $token ) ),
	);
	$file = "/tmp/mb-cookies-{$login}.json";
	file_put_contents( $file, wp_json_encode( $jar ) );
	chmod( $file, 0600 );
	$out[ $login ] = implode( ',', $user->roles );
}
echo wp_json_encode( $out ), "\n";
