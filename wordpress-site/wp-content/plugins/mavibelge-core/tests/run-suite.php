<?php
/**
 * Tek bir `tests/suites/*.php` paketini bağımsız çalıştırır (geliştirme hızı için):
 *
 *   php tests/run-suite.php faz6b4-admin-import
 *
 * `tests/run.php` ile AYNI mb_test() çıktı biçimini ve bootstrap'ı kullanır; tam regresyon yerine
 * yalnız verilen paketi çalıştırır. Resmî kapı `tests/run.php`'dir (bütün paketleri yükler).
 */

require_once __DIR__ . '/bootstrap.php';

$failures = 0;
$total    = 0;

function mb_test( $description, $condition ) {
	global $failures, $total;
	$total++;
	if ( $condition ) {
		echo "PASS  {$description}\n";
	} else {
		$failures++;
		echo "FAIL  {$description}\n";
	}
}

function mb_empty_dependencies() {
	return array(
		'sector_term_ids'             => array(),
		'sector_image_attachment_ids' => array(),
		'qualification_post_ids'      => array(),
	);
}

$mb_temp_dirs = array();
function mb6b2_temp_dir( $label ) {
	global $mb_temp_dirs;
	$dir = sys_get_temp_dir() . '/mb6b2_' . $label . '_' . getmypid() . '_' . count( $mb_temp_dirs );
	mkdir( $dir, 0700, true );
	$mb_temp_dirs[] = $dir;
	return $dir;
}

function mb6b2_cleanup_temp_dirs() {
	global $mb_temp_dirs;
	foreach ( $mb_temp_dirs as $dir ) {
		foreach ( glob( $dir . '/*' ) ?: array() as $f ) {
			@unlink( $f );
		}
		@rmdir( $dir );
	}
}

require_once __DIR__ . '/fixtures/apply-fixture.php';
require_once __DIR__ . '/support/import-apply-fakes.php';

$name = isset( $argv[1] ) ? $argv[1] : '';
$file = __DIR__ . '/suites/' . $name . '.php';
if ( 1 !== preg_match( '/^[a-z0-9-]+\z/', $name ) || ! is_file( $file ) ) {
	fwrite( STDERR, "Kullanım: php tests/run-suite.php <suites/ altındaki paket adı>\n" );
	exit( 2 );
}
require $file;
mb6b2_cleanup_temp_dirs();
printf( "\n%d/%d assertions passed.\n", $total - $failures, $total );
exit( $failures > 0 ? 1 : 0 );
