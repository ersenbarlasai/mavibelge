<?php
/**
 * Faz 12c — içerik import atomikliği: dosya sistemi yan etkisi (logo dosyası) telafisi.
 *
 * Bellek içi sahte dünya (tests/support/import-apply-fakes.php): `world->files` fiziksel dosya kaydıdır ve DB rollback'i
 * onu GERİ ALMAZ (gerçek WordPress'teki gibi). Gerçek WordPress davranışı (attachment, uploads, realpath/symlink sınırı,
 * unlink hatası) tools/runtime-test/scripts/side-effect-test.php ile ayrıca sınanır.
 */

function f12c_env( $dir ) {
	$env = mb_fake_apply_env( $dir );
	$env->world->terms[501] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'haber', 'name' => 'Haber', 'description' => '', 'parent' => 0, 'meta' => array() );
	$env->world->terms[502] = array( 'taxonomy' => 'mb_haber_turu', 'slug' => 'duyuru', 'name' => 'Duyuru', 'description' => '', 'parent' => 0, 'meta' => array() );
	return $env;
}
function f12c_apply( $env, $batch = null ) {
	$p = $env->apply->preview( 'content' );
	return $env->apply->apply( 'content', (string) $p['plan_digest'], $batch, 1 );
}
function f12c_clean( $env ) {
	return 0 === count( $env->world->files ) && 0 === count( $env->world->logoAttachments ) && array() === array_filter( $env->world->posts, function ( $p ) {
		return in_array( $p['post_type'], array( 'mb_referans', 'mb_sss', 'mb_haber' ), true );
	} );
}

$f12c_dir = mb6b2_temp_dir( 'f12ccontent' );
mb_content_fixture_write_dir( $f12c_dir, mb_content_fixture_envelopes( array(), 3 ) );

/* 1) yazma hatası (logo dosyası kopyalandıktan SONRA) */
$C1 = f12c_env( $f12c_dir );
$C1->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'reference:referans-02' ) );
$c1 = f12c_apply( $C1 );
mb_test( 'Faz 12c #1 logo kopyalandıktan sonra yazma hatası: batch geri alınır; yeni dosya YOK, attachment YOK, yarım kayıt YOK',
	false === $c1['ok'] && 'write_failed' === $c1['error_code'] && f12c_clean( $C1 ) );

/* 2) batch audit hatası */
$C2 = f12c_env( $f12c_dir );
$C2->audit->failEvents = array( 'import_batch_committed' );
$c2 = f12c_apply( $C2 );
mb_test( 'Faz 12c #2 batch audit hatası: audit_failed; yeni logo dosyaları silinir, DB temiz',
	false === $c2['ok'] && 'audit_failed' === $c2['error_code'] && f12c_clean( $C2 ) );

/* 3) checkpoint hatası */
$C3 = f12c_env( $f12c_dir );
$C3->store->failWrites = array( 'checkpoint' );
$c3 = f12c_apply( $C3 );
mb_test( 'Faz 12c #3 checkpoint hatası: checkpoint_failed; yeni logo dosyaları silinir, DB temiz',
	false === $c3['ok'] && 'checkpoint_failed' === $c3['error_code'] && f12c_clean( $C3 ) );

/* 4) commit hatası (run_started commit'i geçer, batch commit'i başarısız olur) */
$C4 = f12c_env( $f12c_dir );
$C4->tx->fail_commit_in( 1 );
$c4 = f12c_apply( $C4 );
mb_test( 'Faz 12c #4 commit hatası: commit_failed; yeni logo dosyaları silinir, DB temiz',
	false === $c4['ok'] && 'commit_failed' === $c4['error_code'] && f12c_clean( $C4 ) );

/* 5) DB rollback başarısız: kör silme YOK, sabit kod, güvenli run durumu */
$C5 = f12c_env( $f12c_dir );
$C5->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'reference:referans-03' ) );
$C5->tx->failRollback = true;
$c5 = f12c_apply( $C5 );
mb_test( 'Faz 12c #5 DB rollback başarısız: transaction_rollback_failed; dosyalar KÖRÜ KÖRÜNE SİLİNMEZ (fail-closed); run completed DEĞİL',
	false === $c5['ok'] && 'transaction_rollback_failed' === $c5['error_code'] && 'completed' !== $c5['status'] && count( $C5->world->files ) > 0 );

/* 6) unlink/cleanup başarısız: başarı raporlanmaz, sabit kod, mutlak yol yok */
$C6 = f12c_env( $f12c_dir );
$C6->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'reference:referans-03' ) );
$C6->world->unlinkFails = true;
$c6 = f12c_apply( $C6 );
mb_test( 'Faz 12c #6 temizlik (unlink) başarısız: side_effect_cleanup_failed; ok=false; sonuçta mutlak yol yok',
	false === $c6['ok'] && 'side_effect_cleanup_failed' === $c6['error_code'] && 'completed' !== $c6['status'] && 1 !== preg_match( '#(/tmp|/var|[A-Za-z]:[\\\\/])#', json_encode( $c6 ) ) );

/* 7) mevcut (SHA eşleşen) attachment yeniden kullanılır ve hata/rollback'te KORUNUR */
$C7  = f12c_env( $f12c_dir );
$c7e = mb_content_fixture_envelopes( array(), 3 );
$C7->world->logoAttachments[900] = $c7e['reference']['records'][0]['logo_sha256'];
$C7->world->files['pre-existing.png'] = true;
$C7->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'reference:referans-03' ) );
$c7 = f12c_apply( $C7 );
mb_test( 'Faz 12c #7 mevcut SHA eşleşen attachment: yeniden kullanılır; hata/rollback sonrası mevcut attachment + dosya KORUNUR, yalnız batch\'in yeni dosyaları silinir',
	false === $c7['ok'] && array( 900 => $c7e['reference']['records'][0]['logo_sha256'] ) === $C7->world->logoAttachments && array( 'pre-existing.png' => true ) === $C7->world->files );

/* 8) başarılı batch: dosya + attachment kalır; yeniden apply kopya üretmez */
$C8 = f12c_env( $f12c_dir );
$c8 = f12c_apply( $C8 );
$c8files = $C8->world->files;
$c8p = $C8->apply->preview( 'content' );
$c8n = $C8->apply->apply( 'content', (string) $c8p['plan_digest'], null, 1 );
mb_test( 'Faz 12c #8 başarılı batch: 3 dosya + 3 attachment KALIR; readback 9 unchanged; yeniden apply kopya dosya/attachment üretmez',
	true === $c8['ok'] && 3 === count( $c8files ) && 3 === count( $C8->world->logoAttachments ) && 9 === $c8p['summary']['operations']['unchanged'] && 'noop' === $c8n['status'] && $c8files === $C8->world->files && 3 === count( $C8->world->logoAttachments ) );

/* 9) çoklu referans + birden çok hata noktası (batch=3: ilk batch başarılı kalır, hatalı batch tamamen telafi edilir) */
$C9 = f12c_env( $f12c_dir );
$C9->world->faults = array( array( 'op' => 'create_post', 'source_key' => 'reference:referans-03' ) );
$c9 = f12c_apply( $C9, 2 );
$c9keep = array_keys( $C9->world->logoAttachments );
mb_test( 'Faz 12c #9 çok batch: hata anındaki batch\'in dosyaları silinir; ÖNCEKİ commit edilmiş batch\'lerin logo dosyası+attachment\'ı bozulmaz; dosya sayısı = attachment sayısı',
	false === $c9['ok'] && count( $C9->world->files ) === count( $C9->world->logoAttachments ) && count( $C9->world->files ) === count( $c9keep ) );

/* 10) kapsam kuralları */
$C10 = f12c_env( $f12c_dir );
mb_test( 'Faz 12c kapsam: ikinci begin reddedilir; commit sonrası telafi no-op; kapsamsız telafi no-op',
	true === $C10->writer->begin_side_effect_scope() && false === $C10->writer->begin_side_effect_scope() && true === $C10->writer->commit_side_effect_scope() && array( 'ok' => true, 'removed' => 0, 'error' => null ) === $C10->writer->compensate_side_effect_scope( true ) );
