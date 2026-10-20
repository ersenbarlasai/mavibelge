<?php
/**
 * TEST FIXTURE — YALNIZ silinebilir `mbfx_` fixture veritabanı (qualification-render.sh ortamı). Faz 14 KVKK onay bağlantısı.
 * Gerçek kişi verisi YOKTUR. Klonda içe aktarılmış /kvkk/ sayfası varsa o kullanılır (içeriği DEĞİŞTİRİLMEZ; `hide` yalnız
 * klonda geçici taslağa alır); yoksa "TEST" içerikli sentetik sayfa yalnız bu klonda oluşturulur.
 *
 *   wp eval-file kvkk-fixtures.php page [hide|restore|clear]  /kvkk/ yoksa sentetik sayfa; hide: yayındakini taslağa al; restore/clear: geri yükle + sentetiği kaldır
 *   wp eval-file kvkk-fixtures.php permalink <yapı>    permalink_structure (yalnız klon; ör. "/%postname%/")
 *   wp eval-file kvkk-fixtures.php urls                iki yapıda mavibelge_url('kvkk') ve get_permalink() (DB'ye yazmadan)
 *   wp eval-file kvkk-fixtures.php describe-consent    beş formun describe() DTO'sunda consent etiketi + uzun metin sızıntısı
 *   wp eval-file kvkk-fixtures.php admin               "Mavi Belge Formları" ekranı çıktısındaki KVKK kaydı/bağlantı/uyarı
 */
global $wpdb, $wp_rewrite;
if ( 'mbfx_' !== $wpdb->prefix ) {
	fwrite( STDERR, "HATA: yalnız mbfx_ fixture önekinde çalışır.\n" );
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : '';
$out  = array( 'mode' => $mode );
$find = function () {
	$p = get_page_by_path( 'kvkk' );
	return ( $p instanceof WP_Post && 'publish' === $p->post_status ) ? $p : null;
};

if ( 'page' === $mode ) {
	$sub = isset( $args[1] ) ? $args[1] : '';
	// Yalnız bu fixture'ın oluşturduğu sentetik sayfa (_mbfx_kvkk) kaldırılır; içe aktarılmış sayfaya dokunulmaz. hide/restore
	// silmez (restore gizlenen sentetik sayfayı geri yükleyebilmeli); clear önce geri yükler, sonra siler.
	$purge = function () {
		foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_mbfx_kvkk', 'meta_value' => '1' ) ) as $old ) {
			wp_delete_post( $old, true );
		}
	};
	if ( '' === $sub ) {
		$purge();
	}
	if ( 'hide' === $sub ) {
		// Klonda (içe aktarılmış onaylı) KVKK sayfası varsa geçici olarak taslağa alınır; kimliği yalnız bu klonun seçeneğinde tutulur.
		$p = $find();
		if ( $p ) {
			update_option( 'mbfx_kvkk_hidden', (int) $p->ID, false );
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $p->ID ) );
			clean_post_cache( $p->ID );
		}
		$out['exists'] = null !== $find();
	} elseif ( 'restore' === $sub || 'clear' === $sub ) {
		$hid = (int) get_option( 'mbfx_kvkk_hidden', 0 );
		if ( $hid > 0 ) {
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $hid ) );
			clean_post_cache( $hid );
			delete_option( 'mbfx_kvkk_hidden' );
		}
		if ( 'clear' === $sub ) {
			$purge();
		}
		$out['exists'] = null !== $find();
	} else {
		if ( null === $find() ) {
			$id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_title' => 'KVKK', 'post_name' => 'kvkk', 'post_status' => 'publish', 'post_content' => '<p>TEST: sentetik KVKK sayfası (kurum metni DEĞİL).</p>' ) ), true );
			if ( is_wp_error( $id ) ) {
				fwrite( STDERR, "kvkk sayfası oluşturulamadı\n" );
				exit( 1 );
			}
			update_post_meta( $id, '_mbfx_kvkk', '1' );
		}
		$p             = $find();
		$out['id']     = $p ? (int) $p->ID : 0;
		$out['exists'] = null !== $p;
		$out['url']    = $p ? get_permalink( $p ) : '';
	}
} elseif ( 'permalink' === $mode ) {
	update_option( 'permalink_structure', isset( $args[1] ) ? (string) $args[1] : '' );
	$out['structure'] = get_option( 'permalink_structure' );
} elseif ( 'urls' === $mode ) {
	$structure = '';
	add_filter( 'pre_option_permalink_structure', function () use ( &$structure ) {
		return $structure;
	} );
	foreach ( array( 'pretty' => '/%postname%/', 'index' => '/index.php/%postname%/' ) as $k => $s ) {
		$structure = $s;
		$wp_rewrite->init();
		$p         = $find();
		$out[ $k ] = array( 'helper' => mavibelge_url( 'kvkk' ), 'permalink' => $p ? get_permalink( $p ) : '' );
	}
} elseif ( 'describe-consent' === $mode ) {
	$cfg = MaviBelge_Core_Forms_Service::config();
	foreach ( MaviBelge_Core_Forms_Schema::FORM_IDS as $id ) {
		$d     = MaviBelge_Core_Forms_Service::describe( $id );
		$label = null;
		foreach ( $d['fields'] as $f ) {
			if ( 'consent' === $f['type'] ) {
				$label = $f['label'];
			}
		}
		$text        = (string) $cfg['forms'][ $id ]['consent_text'];
		$out[ $id ] = array( 'open' => $d['open'], 'label' => $label, 'leak' => '' !== $text && false !== strpos( serialize( $d ), $text ), 'text_len' => mb_strlen( $text, 'UTF-8' ), 'version' => $cfg['forms'][ $id ]['consent_version'] );
	}
} elseif ( 'admin' === $mode ) {
	require_once ABSPATH . 'wp-admin/includes/template.php';
	if ( ! class_exists( 'MaviBelge_Core_Forms_Admin' ) ) {
		require_once WP_PLUGIN_DIR . '/mavibelge-core/admin/class-forms-admin.php'; // yönetim sınıfı yalnız is_admin()'de yüklenir
	}
	set_current_screen( 'settings_page_mavibelge-core-forms' );
	ob_start();
	MaviBelge_Core_Forms_Admin::render_page();
	$html = ob_get_clean();
	$p    = $find();
	$out['exists']      = null !== $p;
	$out['record_note'] = substr_count( $html, 'Onaylı metin kaydı. Formda metnin tamamı gösterilmez; ziyaretçi /kvkk/ sayfasındaki KVKK Metni’ne yönlendirilir.' );
	$out['old_note']    = substr_count( $html, 'onay kutusunun yanında görünür' );
	$out['view']        = $p ? substr_count( $html, 'href="' . esc_url( get_permalink( $p ) ) . '"' ) : 0;
	$out['edit']        = $p ? substr_count( $html, 'href="' . esc_url( get_edit_post_link( $p->ID, 'raw' ) ) . '"' ) : 0;
	$out['warning']     = substr_count( $html, 'KVKK sayfası bulunamadı' );
	$out['textareas']   = substr_count( $html, '[consent_text]' );
	$out['versions']    = substr_count( $html, '[consent_version]' );
} else {
	fwrite( STDERR, "kip: page|permalink|urls|describe-consent|admin\n" );
	exit( 1 );
}
echo wp_json_encode( $out ), "\n";
