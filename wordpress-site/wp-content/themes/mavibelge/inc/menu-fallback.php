<?php
/**
 * Primary menu fallback — used ONLY until an admin assigns a real menu
 * to the 'primary' location in Görünüm → Menüler. Once a menu is
 * assigned, wp_nav_menu()'s normal path (MaviBelge_Nav_Walker over the
 * real menu items) takes over and this file's output never renders
 * again — WordPress calls a fallback_cb only when no menu is assigned.
 *
 * Labels are the real Turkish labels from tanitim-site/index.html's
 * header nav (Faz 3 brief §6.2). Targets are PLANNED clean WordPress
 * paths under home_url() — never a literal "#" — matching the slugs
 * tanitim-site's own filenames already use (e.g. meslekler.html ->
 * /meslekler/). The actual pages do not exist yet; that is Faz 4's
 * job. A link to a not-yet-created page 404s until then, which is
 * expected and documented — it is not a dead "#" placeholder.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mavibelge_flat_nav_fallback( array $items ) {
	echo '<ul>';
	foreach ( $items as $item ) {
		printf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( mavibelge_fallback_menu_url( $item ) ),
			esc_html( $item['label'] )
		);
	}
	echo '</ul>';
}

/**
 * Footer "Hızlı Bağlantılar" fallback — real Turkish labels, planned
 * clean paths, same as tanitim-site/index.html's footer-col.
 */
function mavibelge_footer_quick_nav_fallback() {
	mavibelge_flat_nav_fallback(
		array(
			array( 'label' => 'Online Başvuru', 'path' => 'online-basvuru' ),
			array( 'label' => 'Sınav Ücretleri', 'path' => 'sinav-ucretleri' ),
			array( 'label' => 'Belge Sorgulama', 'path' => 'sonuc-belge-sorgulama' ),
			array( 'label' => 'Belge Yenileme', 'path' => 'belge-yenileme' ),
			array( 'label' => 'Sınav Süreçleri', 'path' => 'sinav-surecleri' ),
			array( 'label' => 'Sınav Talepleri', 'path' => 'sinav-talepleri' ),
		)
	);
}

/**
 * Footer "Kurumsal" fallback — same source.
 */
function mavibelge_footer_kurumsal_nav_fallback() {
	mavibelge_flat_nav_fallback(
		array(
			array( 'label' => 'Hakkımızda', 'path' => 'hakkimizda' ),
			array( 'label' => 'Yetki Belgelerimiz', 'path' => 'yetki-akreditasyon' ),
			array( 'label' => 'Haberler', 'path' => 'haberler' ),
			array( 'label' => 'Referanslarımız', 'path' => 'referanslar' ),
			array( 'label' => 'Kariyer', 'path' => 'kariyer' ),
			array( 'label' => 'İletişim', 'path' => 'iletisim' ),
		)
	);
}

function mavibelge_fallback_menu_url( array $item ) {
	$url = home_url( '/' . ltrim( $item['path'], '/' ) . '/' );
	if ( ! empty( $item['anchor'] ) ) {
		$url = home_url( '/' . ltrim( $item['path'], '/' ) . '/#' . $item['anchor'] );
	}
	return $url;
}

function mavibelge_primary_nav_fallback() {
	$menu = array(
		array(
			'label'    => 'Meslekler ve Belgeler',
			'path'     => 'meslekler',
			'children' => array(
				array( 'label' => 'Tüm Meslekler', 'path' => 'meslekler' ),
				array( 'label' => 'Sektörler', 'path' => 'meslekler', 'anchor' => 'sektorler' ),
				array( 'label' => 'Belge Yenileme', 'path' => 'belge-yenileme' ),
			),
		),
		array(
			'label'    => 'Sınav ve Başvuru',
			'path'     => 'sinav-ve-basvuru',
			'children' => array(
				array( 'label' => 'Nasıl Başvururum?', 'path' => 'nasil-basvururum' ),
				array( 'label' => 'Online Başvuru', 'path' => 'online-basvuru' ),
				array( 'label' => 'Sınav Takvimi', 'path' => 'sinav-takvimi' ),
				array( 'label' => 'Sınav Ücretleri', 'path' => 'sinav-ucretleri' ),
				array( 'label' => 'Sonuç ve Belge Sorgulama', 'path' => 'sonuc-belge-sorgulama' ),
				array( 'label' => 'Sınav Süreçleri', 'path' => 'sinav-surecleri' ),
				array( 'label' => 'Sınav Talepleri', 'path' => 'sinav-talepleri' ),
				array( 'label' => 'Banka Hesap Bilgileri', 'path' => 'banka-hesap-bilgileri' ),
				array( 'label' => 'İtiraz ve Şikayet', 'path' => 'itiraz-sikayet' ),
			),
		),
		array(
			'label'    => 'Kurumsal',
			'path'     => 'kurumsal',
			'children' => array(
				array( 'label' => 'Hakkımızda', 'path' => 'hakkimizda' ),
				array( 'label' => 'Misyon ve Vizyon', 'path' => 'misyon-vizyon' ),
				array( 'label' => 'Kalite Politikamız', 'path' => 'kalite-politikamiz' ),
				array( 'label' => 'Tarafsızlık Beyanı', 'path' => 'tarafsizlik-beyani' ),
				array( 'label' => 'Yasal Dayanağımız', 'path' => 'yasal-dayanagimiz' ),
				array( 'label' => 'Yetki ve Akreditasyon', 'path' => 'yetki-akreditasyon' ),
				array( 'label' => 'Referanslarımız', 'path' => 'referanslar' ),
				array( 'label' => 'Sosyal Sorumluluk', 'path' => 'sosyal-sorumluluk' ),
			),
		),
		array(
			'label'    => 'Bilgi Merkezi',
			'path'     => 'bilgi-merkezi',
			'children' => array(
				array( 'label' => 'Haberler', 'path' => 'haberler' ),
				array( 'label' => 'Duyurular', 'path' => 'duyurular' ),
				array( 'label' => 'Dokümanlar', 'path' => 'dokumanlar' ),
				array( 'label' => 'Mevzuat', 'path' => 'mevzuat' ),
				array( 'label' => 'Sık Sorulan Sorular', 'path' => 'sss' ),
				array( 'label' => 'MYK Nedir?', 'path' => 'myk' ),
				array( 'label' => 'TÜRKAK Nedir?', 'path' => 'turkak' ),
				array( 'label' => 'Ulusal Meslek Standartları', 'path' => 'ulusal-meslek-standartlari' ),
				array( 'label' => 'Ulusal Yeterlilikler', 'path' => 'ulusal-yeterlilikler' ),
			),
		),
		array(
			'label' => 'İletişim',
			'path'  => 'iletisim',
		),
	);

	echo '<ul>';
	foreach ( $menu as $index => $item ) {
		if ( ! empty( $item['children'] ) ) {
			$submenu_id   = 'submenu-fallback-' . (int) $index;
			$toggle_label = sprintf(
				/* translators: %s: parent menu item label, e.g. "Kurumsal" */
				__( '%s alt menüsünü aç/kapat', 'mavibelge' ),
				$item['label']
			);
			echo '<li>';
			printf(
				'<a class="nav-parent-link" href="%1$s">%2$s</a>',
				esc_url( mavibelge_fallback_menu_url( $item ) ),
				esc_html( $item['label'] )
			);
			echo '<button type="button" class="nav-toggle" aria-expanded="false" aria-controls="' . esc_attr( $submenu_id ) . '">'
				. '<span class="screen-reader-text">' . esc_html( $toggle_label ) . '</span>'
				. ' <svg width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>'
				. '</button>';
			echo '<ul id="' . esc_attr( $submenu_id ) . '" class="submenu">';
			foreach ( $item['children'] as $child ) {
				printf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( mavibelge_fallback_menu_url( $child ) ),
					esc_html( $child['label'] )
				);
			}
			echo '</ul></li>';
		} else {
			printf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( mavibelge_fallback_menu_url( $item ) ),
				esc_html( $item['label'] )
			);
		}
	}
	echo '</ul>';
}
