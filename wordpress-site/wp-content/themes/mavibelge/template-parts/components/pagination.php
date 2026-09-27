<?php
/**
 * Pagination presentation shell. No query logic — the caller supplies
 * fully-resolved page URLs; this never invents a "#" placeholder link.
 *
 * $args:
 * - current (int, required) current page number, 1-based
 * - total (int, required) total page count
 * - url_for (callable, required) function( int $page ): string — must
 *   return a real URL for that page number
 *
 * Faz 12e: "Önceki"/"Sonraki" bağlantıları (ilk/son sayfada devre dışı
 * <span aria-disabled="true">), rel=prev/next; tek sayfada hiç çizilmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current = isset( $args['current'] ) ? (int) $args['current'] : 1;
$total   = isset( $args['total'] ) ? (int) $args['total'] : 1;
$url_for = isset( $args['url_for'] ) && is_callable( $args['url_for'] ) ? $args['url_for'] : null;

if ( ! $url_for || $total < 2 ) {
	return;
}

// Clamp into [1, total] — a caller passing an out-of-range current page
// (e.g. a stale query var) must not silently mark no page as current or
// produce a nonsensical aria-current.
$current = max( 1, min( $current, $total ) );
?>
<nav class="pagination" aria-label="<?php esc_attr_e( 'Sayfalama', 'mavibelge' ); ?>">
	<?php if ( $current > 1 ) : ?>
		<a class="page-btn page-prev" href="<?php echo esc_url( call_user_func( $url_for, $current - 1 ) ); ?>" rel="prev"><?php esc_html_e( 'Önceki', 'mavibelge' ); ?></a>
	<?php else : ?>
		<span class="page-btn page-prev is-disabled" aria-disabled="true"><?php esc_html_e( 'Önceki', 'mavibelge' ); ?></span>
	<?php endif; ?>
	<?php for ( $page = 1; $page <= $total; $page++ ) : ?>
		<?php if ( $page === $current ) : ?>
			<span class="page-btn is-active" aria-current="page"><?php echo esc_html( (string) $page ); ?></span>
		<?php else : ?>
			<a class="page-btn" href="<?php echo esc_url( call_user_func( $url_for, $page ) ); ?>"><?php echo esc_html( (string) $page ); ?></a>
		<?php endif; ?>
	<?php endfor; ?>
	<?php if ( $current < $total ) : ?>
		<a class="page-btn page-next" href="<?php echo esc_url( call_user_func( $url_for, $current + 1 ) ); ?>" rel="next"><?php esc_html_e( 'Sonraki', 'mavibelge' ); ?></a>
	<?php else : ?>
		<span class="page-btn page-next is-disabled" aria-disabled="true"><?php esc_html_e( 'Sonraki', 'mavibelge' ); ?></span>
	<?php endif; ?>
</nav>
