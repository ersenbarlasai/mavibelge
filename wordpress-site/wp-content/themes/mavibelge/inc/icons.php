<?php
/**
 * Onaylı statik referanstaki (tanitim-site/index.html) satır içi SVG ikonlarının kaydı.
 * Değerler referanstan BİREBİR taşınmıştır (yeni ikon tasarlanmadı). Anahtarlar: sektör ikon anahtarı
 * (`_mb_icon_key` term meta = manifest `icon`) ve ana sayfa görev kartı anahtarları.
 * Yalnız sabit, kayıtlı anahtarlar çizilir; bilinmeyen anahtarda nötr yedek ikon döner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mavibelge_icon_paths() {
	return array(
		'sector' => array(
			'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
			'flame' => '<path d="M12 2c1 4-3 5-3 9a3 3 0 006 0c0-1.5-1-2-1-3.5 1.5 1 3 3 3 5.5a5 5 0 01-10 0C7 9 12 7 12 2z"/>',
			'layers' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>',
			'truck' => '<rect x="2" y="7" width="12" height="9"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
			'crane' => '<path d="M4 20V9l6-4 6 4v3"/><path d="M10 20v-7h4v7"/><path d="M16 13h3l3 3v4h-6z"/><circle cx="17" cy="20" r="1.4"/><circle cx="20.5" cy="20" r="1.4"/>',
			'drop' => '<path d="M12 3c4 5 6 8 6 11a6 6 0 01-12 0c0-3 2-6 6-11z"/>',
			'bolt' => '<path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z"/>',
			'square' => '<rect x="4" y="4" width="16" height="16" rx="1"/><path d="M4 9h16M9 4v16"/>',
			'thread' => '<circle cx="12" cy="12" r="8"/><path d="M6 9c2 1 2 5 0 6M18 9c-2 1-2 5 0 6M9 6c1 2 5 2 6 0M9 18c1-2 5-2 6 0"/>',
			'building' => '<rect x="4" y="3" width="10" height="18"/><rect x="14" y="9" width="6" height="12"/><path d="M7 7h1M11 7h1M7 11h1M11 11h1M7 15h1M11 15h1"/>',
			'chair' => '<path d="M6 3v10a3 3 0 003 3h6a3 3 0 003-3V3M6 9h12M9 16v5M15 16v5"/>',
			'pickaxe' => '<path d="M4 21l5-15 2 5 2-3 7 13H4z"/><path d="M9 10L6 4l6 2-1 3"/>',
			'mountain' => '<path d="M3 20l6-11 4 6 2-3 6 8H3z"/>',
			'scissors' => '<circle cx="6" cy="6" r="2.2"/><circle cx="6" cy="18" r="2.2"/><path d="M8 7.5L20 20M8 16.5L20 4"/>',
		),
		// Faz 12e: yeterlilik detayı 'İlgili Dokümanlar' kartı (tanitim-site/yeterlilik.html doc-icon ile birebir).
		'ui'     => array(
			'document' => '<path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/>',
		),
		'task'   => array(
			'fee' => '<circle cx="12" cy="12" r="9"/><path d="M9 15.5c.6.7 1.6 1.2 3 1.2 1.9 0 3-1 3-2.2 0-1.3-1.1-1.8-3-2.3-1.9-.5-3-1-3-2.3 0-1.2 1.1-2.2 3-2.2 1.4 0 2.4.5 3 1.2M12 6.5v11"/>',
			'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.8-4.8"/>',
			'refresh' => '<path d="M4 12a8 8 0 0113.9-5.5M20 12a8 8 0 01-13.9 5.5"/><path d="M18 3v4h-4M6 21v-4h4"/>',
			'list' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
			'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		),
	);
}

/**
 * @param string $group 'sector'|'task'|'ui'
 * @param string $key   Kayıtlı ikon anahtarı
 * @param string $class SVG sınıfı (icon-22|icon-24)
 * @return string Sabit, güvenilir SVG (kullanıcı girdisi içermez)
 */
function mavibelge_icon_svg( $group, $key, $class = 'icon-24' ) {
	$all   = mavibelge_icon_paths();
	$class = in_array( $class, array( 'icon-22', 'icon-24' ), true ) ? $class : 'icon-24';
	$inner = ( is_string( $group ) && is_string( $key ) && isset( $all[ $group ][ $key ] ) ) ? $all[ $group ][ $key ] : '<circle cx="12" cy="12" r="8"/>';
	return '<svg class="' . $class . '" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">' . $inner . '</svg>';
}
