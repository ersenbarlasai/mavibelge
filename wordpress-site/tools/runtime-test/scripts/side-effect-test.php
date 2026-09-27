<?php
/**
 * Faz 12c — içerik import atomikliği: dosya sistemi yan etkisi telafisi, GERÇEK WordPress 6.9.9 + PHP 7.3.33 + MariaDB kanıtı
 * (`wp --require=fixture-env.php --user=mbadmin eval-file side-effect-test.php`).
 *
 * Yalnız klonlanmış `mbfx_` fixture tabloları, AÇIKÇA SAHTE fixture manifesti ve fixture sahte PNG'leri (/tmp/sources/reference-logos)
 * kullanılır. Logo dosyaları GERÇEK uploads dizinine değil `/tmp/mbfx-uploads` altına yazılır (`upload_dir` filtresi).
 * Her senaryo DB (attachment/post sayısı), uploads dosya listesi ve /tmp/mbfx-* envanterini önce/sonra karşılaştırır.
 */

global $wpdb;
if ( 'mbfx_' !== $wpdb->prefix ) {
	echo "HATA: yalnız mbfx_ fixture önekinde çalışır.\n";
	return;
}
$v1      = '/tmp/mbfx-content-manifest';
$logoDir = '/tmp/sources/reference-logos';
$upTmp   = '/tmp/mbfx-uploads';
add_filter(
	'upload_dir',
	function ( $d ) use ( $upTmp ) {
		$sub = '/mbfx';
		wp_mkdir_p( $upTmp . $sub );
		return array_merge( $d, array( 'path' => $upTmp . $sub, 'url' => 'http://mbfx.invalid/uploads' . $sub, 'subdir' => $sub, 'basedir' => $upTmp, 'baseurl' => 'http://mbfx.invalid/uploads', 'error' => false ) );
	}
);
if ( 0 !== strpos( $v1, '/tmp/mbfx-' ) || ! is_file( $v1 . '/references.manifest.json' ) ) {
	echo "HATA: sahte içerik manifesti yok; önce fixture-db.php manifest.\n";
	return;
}

/** Audit sink: seçilen olay KAYDEDİLMEDEN false döner. */
class MBFX12C_Sink implements MaviBelge_Core_Import_Audit_Sink {
	public $inner;
	public $failEvent = null;
	public function __construct() {
		$this->inner = new MaviBelge_Core_Import_WP_Audit_Sink();
	}
	public function ready() {
		return $this->inner->ready();
	}
	public function record( $event, $runId, array $context ) {
		if ( $event === $this->failEvent ) {
			return false;
		}
		return $this->inner->record( $event, $runId, $context );
	}
}
class MBFX12C_Store extends MaviBelge_Core_Import_Wpdb_Run_Store {
	public $failCheckpoint = false;
	public function record_checkpoint( $runId, $committedBatches, $committedItems, $expectedBatches = null ) {
		return $this->failCheckpoint ? false : parent::record_checkpoint( $runId, $committedBatches, $committedItems, $expectedBatches );
	}
}
class MBFX12C_Tx extends MaviBelge_Core_Import_Wpdb_Transaction {
	public $failCommitAfter = null;
	public $failRollback    = false;
	private $commits        = 0;
	public function commit() {
		if ( null !== $this->failCommitAfter && $this->commits >= $this->failCommitAfter ) {
			return false; // COMMIT hiç gönderilmez; çağıran rollback dener.
		}
		$this->commits++;
		return parent::commit();
	}
	public function rollback() {
		$ok = parent::rollback(); // Gerçek ROLLBACK yürütülür (bağlantı temiz kalsın); yalnız RAPOR yanlış olur.
		return $this->failRollback ? false : $ok;
	}
}
class MBFX12C_Writer extends MaviBelge_Core_Import_WordPress_Target_Writer {
	public $unlinkFails = false;
	protected function remove_file( $path ) {
		return $this->unlinkFails ? false : parent::remove_file( $path );
	}
}

$results = array();
$t       = function ( $label, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array( $label, (bool) $ok, (string) $detail );
};
wp_mkdir_p( $upTmp . '/mbfx' );
$inv = function () use ( $wpdb, $upTmp ) {
	$files = glob( $upTmp . '/mbfx/*' );
	$files = is_array( $files ) ? array_map( 'basename', $files ) : array();
	sort( $files );
	$tmp = glob( '/tmp/mbfx-*' );
	$tmp = is_array( $tmp ) ? array_values( array_diff( array_map( 'basename', $tmp ), array( 'mbfx-uploads' ) ) ) : array();
	sort( $tmp );
	return array(
		'attachments' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" ),
		'content'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('mb_haber','mb_referans')" ),
		'files'       => $files,
		'tmp'         => $tmp,
	);
};
$mk = function ( $sink = null, $store = null, $tx = null, $writer = null ) use ( $v1, $logoDir ) {
	$repo   = new MaviBelge_Core_Import_WordPress_Target_Repository();
	$sink   = null === $sink ? new MBFX12C_Sink() : $sink;
	$store  = null === $store ? new MBFX12C_Store() : $store;
	$tx     = null === $tx ? new MBFX12C_Tx() : $tx;
	$writer = null === $writer ? new MBFX12C_Writer( $logoDir ) : $writer;
	$o      = new stdClass();
	$o->store  = $store;
	$o->writer = $writer;
	$o->apply  = new MaviBelge_Core_Import_Apply_Service( new MaviBelge_Core_Import_Dry_Run_Service( $repo, $v1 ), $writer, $tx, $store, $sink );
	$o->rollback = new MaviBelge_Core_Import_Rollback_Service( $repo, $writer, $tx, $store, $sink );
	return $o;
};
$apply = function ( $svc, $batch = null ) {
	$p = $svc->apply->preview( 'content' );
	return $svc->apply->apply( 'content', (string) $p['plan_digest'], $batch, get_current_user_id() );
};
$runStatus = function ( $uid ) {
	$r = ( new MaviBelge_Core_Import_Wpdb_Run_Store() )->get_run( (string) $uid );
	return is_array( $r ) ? $r['status'] : null;
};
$wipeFiles = function () use ( $upTmp ) {
	foreach ( glob( $upTmp . '/mbfx/*' ) ? glob( $upTmp . '/mbfx/*' ) : array() as $f ) {
		is_file( $f ) && unlink( $f );
	}
};
$wipeContent = function () use ( $wpdb, $wipeFiles ) {
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('mb_haber','mb_referans')" ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_mb_import_logo_sha256', 'fields' => 'ids', 'posts_per_page' => 50, 'no_found_rows' => true ) ) as $a ) {
		wp_delete_attachment( (int) $a, true );
	}
	$wipeFiles();
	// Yalnız mbfx_ fixture tabloları (önek başta doğrulandı): sonraki senaryo çözülmemiş run yüzünden reddedilmesin.
	foreach ( array( 'mb_import_run_plan_items', 'mb_import_run_items', 'mb_import_runs' ) as $tbl ) {
		// Run tabloları ilk apply'da tembel oluşur; yokken sorgu atılmaz (debug.log'a DB hatası düşmesin).
		if ( $wpdb->prefix . $tbl === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $tbl ) ) ) ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}{$tbl}" );
		}
	}
};
$noPath = function ( $r ) {
	$j = wp_json_encode( $r );
	return 1 !== preg_match( '#(/tmp/|/var/|/opt/|mbfx-uploads|[A-Za-z]:[\\\\/])#', (string) $j );
};

// Kontrollü haber türü terimleri (üretimde import bunları oluşturmaz; fixture'da betik oluşturur).
$tHaber  = wp_insert_term( 'Haber', 'mb_haber_turu', array( 'slug' => 'haber' ) );
$tDuyuru = wp_insert_term( 'Duyuru', 'mb_haber_turu', array( 'slug' => 'duyuru' ) );
$wipeContent();
$base = $inv();
$t( 'başlangıç: fixture temiz (0 logo dosyası, 0 içerik postu, 0 logo attachment)', array() === $base['files'] && 0 === $base['content'] && 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mb_import_logo_sha256'" ) );

/** Bir hata senaryosu: batch geri alınmış, yeni dosya/attachment/post yok, run completed değil, sonuç yolsuz. */
$assertClean = function ( $label, $r, $expectedCode, $before ) use ( $t, $inv, $runStatus, $noPath ) {
	$after = $inv();
	unset( $before['runs'], $after['runs'] );
	$t( $label, false === $r['ok'] && $expectedCode === $r['error_code'] && 'completed' !== $runStatus( $r['run_uid'] ) && $before === $after && $noPath( $r ), wp_json_encode( array( $r['error_code'], $r['status'], $after ) ) );
};

/* 1) logo dosyası kopyalanıp attachment oluştuktan SONRA post/meta readback hatası */
$before = $inv();
$hook   = function ( $check, $id, $key ) {
	return '_mb_logo_attachment_id' === $key ? false : $check; // update atlanır -> katı readback başarısız
};
add_filter( 'update_post_metadata', $hook, 10, 3 );
$r1 = $apply( $mk() );
remove_filter( 'update_post_metadata', $hook, 10 );
$assertClean( 'senaryo 1: logo kopyalandıktan sonra post meta readback hatası -> DB geri alındı; attachment satırı YOK, yeni dosya YOK', $r1, 'write_failed', $before );
$wipeContent();

/* 2) batch audit hatası */
$before = $inv();
$sink   = new MBFX12C_Sink();
$sink->failEvent = 'import_batch_committed';
$r2 = $apply( $mk( $sink ) );
$assertClean( 'senaryo 2: batch audit hatası -> audit_failed; yeni dosya/attachment/post YOK', $r2, 'audit_failed', $before );
$wipeContent();

/* 3) checkpoint hatası */
$before = $inv();
$store  = new MBFX12C_Store();
$store->failCheckpoint = true;
$r3 = $apply( $mk( null, $store ) );
$assertClean( 'senaryo 3: checkpoint hatası -> checkpoint_failed; yeni dosya/attachment/post YOK', $r3, 'checkpoint_failed', $before );
$wipeContent();

/* 4) commit hatası (1. commit = run_started geçer; batch commit'i başarısız) */
$before = $inv();
$tx     = new MBFX12C_Tx();
$tx->failCommitAfter = 1;
$r4 = $apply( $mk( null, null, $tx ) );
$assertClean( 'senaryo 4: batch COMMIT hatası -> commit_failed; yeni dosya/attachment/post YOK', $r4, 'commit_failed', $before );
$wipeContent();

/* 5) DB rollback başarısız (rapor): kör silme YOK, sabit kod, run completed değil */
$before = $inv();
$tx     = new MBFX12C_Tx();
$tx->failRollback = true;
$sink5  = new MBFX12C_Sink();
$sink5->failEvent = 'import_batch_committed';
$r5     = $apply( $mk( $sink5, null, $tx ) );
$after5 = $inv();
$t( 'senaryo 5: DB rollback başarısız -> transaction_rollback_failed; dosyalar KÖRÜ KÖRÜNE SİLİNMEZ (fail-closed); run completed DEĞİL; sonuçta yol yok',
	false === $r5['ok'] && 'transaction_rollback_failed' === $r5['error_code'] && 'completed' !== $runStatus( $r5['run_uid'] ) && count( $after5['files'] ) > count( $before['files'] ) && $noPath( $r5 ), wp_json_encode( array( $r5['error_code'], $after5['files'] ) ) );
$wipeContent();

/* 6) unlink başarısız: başarı raporlanmaz, sabit kod, mutlak yol yok */
$before = $inv();
$w6     = new MBFX12C_Writer( $logoDir );
$w6->unlinkFails = true;
$sink6  = new MBFX12C_Sink();
$sink6->failEvent = 'import_batch_committed';
$r6     = $apply( $mk( $sink6, null, null, $w6 ) );
$after6 = $inv();
$t( 'senaryo 6: unlink başarısız -> side_effect_cleanup_failed; ok=false; run completed DEĞİL; DB temiz (attachment/post yok); sonuçta mutlak yol yok',
	false === $r6['ok'] && 'side_effect_cleanup_failed' === $r6['error_code'] && 'completed' !== $runStatus( $r6['run_uid'] ) && $after6['attachments'] === $before['attachments'] && $after6['content'] === $before['content'] && count( $after6['files'] ) > 0 && $noPath( $r6 ), wp_json_encode( array( $r6['error_code'], $after6 ) ) );
$wipeContent();

/* 9) çoklu referans + birden çok hata noktası (tek batch: 3 referansın 3 logo dosyası; 3. referansın meta yazımı başarısız) */
$before = $inv();
$cnt    = 0;
$hook9  = function ( $check, $id, $key ) use ( &$cnt ) {
	if ( '_mb_logo_attachment_id' === $key && ++$cnt >= 3 ) {
		return false;
	}
	return $check;
};
add_filter( 'update_post_metadata', $hook9, 10, 3 );
$r9 = $apply( $mk() );
remove_filter( 'update_post_metadata', $hook9, 10 );
$assertClean( 'senaryo 9a: tek batch, 3 logo dosyası oluştuktan sonra 3. referansta hata -> TÜM yeni dosyalar/attachment\'lar geri alınır', $r9, 'write_failed', $before );
$wipeContent();
$cnt    = 0;
$hook9b = function ( $check, $id, $key ) use ( &$cnt ) {
	if ( '_mb_logo_attachment_id' === $key && ++$cnt >= 3 ) {
		return false;
	}
	return $check;
};
add_filter( 'update_post_metadata', $hook9b, 10, 3 );
$r9b = $apply( $mk(), 2 );
remove_filter( 'update_post_metadata', $hook9b, 10 );
$after9b = $inv();
$attIds  = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_mb_import_logo_sha256', 'fields' => 'ids', 'posts_per_page' => 50, 'no_found_rows' => true ) );
$filesOk = true;
foreach ( $attIds as $aid ) {
	$filesOk = $filesOk && is_file( (string) get_attached_file( (int) $aid ) );
}
$t( 'senaryo 9b: batch=2, hata anındaki batch telafi edilir; önceki commit edilmiş batch\'lerin logo dosyaları+attachment\'ları korunur; dosya sayısı = attachment sayısı (yetim yok)',
	false === $r9b['ok'] && count( $after9b['files'] ) === count( $attIds ) && $filesOk && $noPath( $r9b ), wp_json_encode( array( $r9b['error_code'], count( $attIds ), $after9b['files'] ) ) );
$wipeContent();

/* 8) başarılı batch: dosya + attachment kalır; readback değişmez; yeniden apply kopya üretmez */
$svc8 = $mk();
$r8   = $apply( $svc8 );
$in8  = $inv();
$pv8  = $svc8->apply->preview( 'content' );
$r8b  = $apply( $mk() );
$in8b = $inv();
$t( 'senaryo 8: başarılı batch -> completed; 3 logo dosyası + 3 attachment KALIR; readback tümü unchanged; yeniden apply noop, kopya dosya/attachment YOK',
	true === $r8['ok'] && 'completed' === $r8['status'] && 3 === count( $in8['files'] ) && $in8['attachments'] === $base['attachments'] + 3 && 6 === $pv8['summary']['operations']['unchanged'] && 'noop' === $r8b['status'] && $in8['files'] === $in8b['files'] && $in8['attachments'] === $in8b['attachments'], wp_json_encode( array( $r8['status'], $in8['files'], $r8b['status'] ) ) );

/* 7) mevcut SHA eşleşen attachment: rollback içeriği geri alır (attachment kalır) -> yeniden apply hata verirse mevcut attachment+dosya KORUNUR */
$rbp = $svc8->rollback->preview( $r8['run_uid'] );
$rb7 = $svc8->rollback->rollback( $r8['run_uid'], (string) $rbp['rollback_digest'], null );
$pre = $inv();
$attBefore = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_mb_import_logo_sha256', 'fields' => 'ids', 'posts_per_page' => 50, 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC' ) );
$sink7 = new MBFX12C_Sink();
$sink7->failEvent = 'import_batch_committed';
$r7 = $apply( $mk( $sink7 ) );
$post7 = $inv();
$attAfter = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'meta_key' => '_mb_import_logo_sha256', 'fields' => 'ids', 'posts_per_page' => 50, 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC' ) );
$t( 'senaryo 7: rollback sonrası kalan SHA eşleşen attachment\'lar yeniden kullanılır; sonraki batch hatasında mevcut attachment+dosya KORUNUR (kimlikler ve dosyalar aynı), yeni kopya YOK',
	true === $rb7['ok'] && 3 === count( $attBefore ) && false === $r7['ok'] && 'audit_failed' === $r7['error_code'] && $attBefore === $attAfter && $pre['files'] === $post7['files'] && 3 === count( $post7['files'] ), wp_json_encode( array( $rb7['status'], $attBefore, $attAfter, $r7['error_code'] ) ) );
$wipeContent();

/* 10) güvenlik: yol sınırı — uploads dışına/ad kalıbına uymayan girdi ASLA silinmez */
$outside = '/tmp/mbfx-outside-' . getmypid() . '.png';
file_put_contents( $outside, 'x' );
$w10 = new class( $logoDir ) extends MaviBelge_Core_Import_WordPress_Target_Writer {
	public function poke( $file, $name ) {
		$m = new ReflectionMethod( 'MaviBelge_Core_Import_WordPress_Target_Writer', 'compensate_entry' );
		$m->setAccessible( true );
		return $m->invoke( $this, array( 'file' => $file, 'name' => $name, 'attachment_id' => null ) );
	}
};
$goodName = 'mavibelge-referans-logo-' . str_repeat( 'a', 12 ) . '.png';
$inUp     = $upTmp . '/mbfx/' . $goodName;
file_put_contents( $inUp, 'x' );
$link = $upTmp . '/mbfx/mavibelge-referans-logo-' . str_repeat( 'b', 12 ) . '.png';
@symlink( $outside, $link );
$c1 = $w10->poke( $outside, $goodName );
$c2 = $w10->poke( $upTmp . '/mbfx/../' . basename( $outside ), basename( $outside ) );
$c3 = $w10->poke( $link, basename( $link ) );
$c4 = $w10->poke( $inUp, 'evil.png' );
$t( 'senaryo 10: uploads dışı yol / ad kalıbı dışı / symlink / dizin atlama reddedilir (side_effect_path_rejected) ve dosyalar SİLİNMEZ; geçerli yeni dosya silinir',
	'side_effect_path_rejected' === $c1 && 'side_effect_path_rejected' === $c2 && 'side_effect_path_rejected' === $c3 && 'side_effect_path_rejected' === $c4 && is_file( $outside ) && is_file( $inUp ) && null === $w10->poke( $inUp, $goodName ) && ! file_exists( $inUp ), wp_json_encode( array( $c1, $c2, $c3, $c4 ) ) );
@unlink( $link );
@unlink( $outside );

/* temizlik */
$wipeContent();
foreach ( array( 'haber', 'duyuru' ) as $slug ) {
	$term = get_term_by( 'slug', $slug, 'mb_haber_turu' );
	if ( $term instanceof WP_Term ) {
		wp_delete_term( $term->term_id, 'mb_haber_turu' );
	}
}
if ( is_dir( $upTmp ) ) {
	@rmdir( $upTmp . '/mbfx' );
	@rmdir( $upTmp );
}
$end = $inv();
$t( 'temizlik: fixture\'da içerik/attachment/logo dosyası kalmadı; /tmp/mbfx-* envanteri başlangıçla eşit (geçici dosya sızıntısı yok)',
	0 === $end['content'] && $end['attachments'] === $base['attachments'] && array() === $end['files'] && $end['tmp'] === $base['tmp'], wp_json_encode( array( $base['tmp'], $end['tmp'] ) ) );

$pass = 0;
foreach ( $results as $r ) {
	echo ( $r[1] ? 'PASS  ' : 'FAIL  ' ) . $r[0] . ( '' !== $r[2] && ! $r[1] ? '  [' . $r[2] . ']' : '' ) . "\n";
	$pass += $r[1] ? 1 : 0;
}
echo "\n{$pass}/" . count( $results ) . " Faz 12c yan etki telafisi WordPress runtime testi geçti.\n";
