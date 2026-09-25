<?php
/**
 * Alert / empty-state component. NEW in Faz 3 (no 1:1 static
 * equivalent — see docs/component-catalog.md). Presentation only.
 *
 * $args:
 * - type (string) 'alert' (default) or 'empty-state'
 * - variant (string, alert only) 'info'|'success'|'warning'|'danger' — default 'info'
 * - title (string) optional (used by empty-state as <h3>, by alert as bold lead-in)
 * - message (string, required) plain text, escaped
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type    = isset( $args['type'] ) ? $args['type'] : 'alert';
$variant = isset( $args['variant'] ) ? $args['variant'] : 'info';
$title   = isset( $args['title'] ) ? $args['title'] : '';
$message = isset( $args['message'] ) ? $args['message'] : '';

if ( 'empty-state' === $type ) :
	?>
	<div class="empty-state">
		<svg class="empty-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M9 10h.01M15 10h.01M8 15c1 1.2 2.4 1.8 4 1.8s3-.6 4-1.8"/></svg>
		<?php if ( '' !== $title ) : ?>
			<h3><?php echo esc_html( $title ); ?></h3>
		<?php endif; ?>
		<?php if ( '' !== $message ) : ?>
			<p><?php echo esc_html( $message ); ?></p>
		<?php endif; ?>
	</div>
	<?php
else :
	$role = 'danger' === $variant || 'warning' === $variant ? 'alert' : 'status';
	?>
	<div class="alert alert-<?php echo esc_attr( $variant ); ?>" role="<?php echo esc_attr( $role ); ?>">
		<svg class="alert-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
		<span>
			<?php if ( '' !== $title ) : ?>
				<strong><?php echo esc_html( $title ); ?></strong><br>
			<?php endif; ?>
			<?php echo esc_html( $message ); ?>
		</span>
	</div>
	<?php
endif;
