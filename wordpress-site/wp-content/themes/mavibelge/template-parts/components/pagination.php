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
	<?php for ( $page = 1; $page <= $total; $page++ ) : ?>
		<?php if ( $page === $current ) : ?>
			<span class="page-btn is-active" aria-current="page"><?php echo esc_html( (string) $page ); ?></span>
		<?php else : ?>
			<a class="page-btn" href="<?php echo esc_url( call_user_func( $url_for, $page ) ); ?>"><?php echo esc_html( (string) $page ); ?></a>
		<?php endif; ?>
	<?php endfor; ?>
</nav>
