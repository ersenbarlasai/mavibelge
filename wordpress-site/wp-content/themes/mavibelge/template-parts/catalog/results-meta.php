<?php
/**
 * Faz 5 — "Toplam X meslekten Y–Z arası gösteriliyor" sentence. No JS,
 * no aria-live — the whole page reloads on filter/pagination (a real
 * GET request), so an ARIA live region would be redundant noise, not a
 * helpful announcement (brief §9 explicitly warns against this).
 *
 * $args:
 * - total (int, required)
 * - page (int, required)
 * - page_size (int, required)
 * - items_count (int, required) — actual number of items on this page (last page may be shorter than page_size)
 * - noun (string) default 'meslekten' — Turkish partitive noun for the sentence, e.g. 'ücret kaydından'
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total       = isset( $args['total'] ) ? (int) $args['total'] : 0;
$page        = isset( $args['page'] ) ? (int) $args['page'] : 1;
$page_size   = isset( $args['page_size'] ) ? (int) $args['page_size'] : 12;
$items_count = isset( $args['items_count'] ) ? (int) $args['items_count'] : 0;
$noun        = isset( $args['noun'] ) ? $args['noun'] : __( 'kayıttan', 'mavibelge' );

if ( 0 === $total || 0 === $items_count ) {
	return;
}

$from = ( ( $page - 1 ) * $page_size ) + 1;
$to   = $from + $items_count - 1;
?>
<p class="results-meta">
	<?php
	printf(
		/* translators: 1: total record count, 2: partitive noun (e.g. "meslekten"), 3: first shown index, 4: last shown index */
		esc_html__( 'Toplam %1$d %2$s %3$d–%4$d arası gösteriliyor.', 'mavibelge' ),
		$total,
		esc_html( $noun ),
		$from,
		$to
	);
	?>
</p>
