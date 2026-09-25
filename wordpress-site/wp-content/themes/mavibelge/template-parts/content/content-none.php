<?php
/**
 * Honest empty-state for any archive/loop that finds zero published
 * items — used when the eklenti is inactive, no content has been
 * imported yet (Faz 6), or a real query genuinely returns nothing.
 * Never fabricates placeholder records.
 *
 * $args:
 * - title (string) optional
 * - message (string, required)
 * - action_label (string) optional CTA
 * - action_url (string) optional CTA target — required if action_label set
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title        = isset( $args['title'] ) ? $args['title'] : '';
$message      = isset( $args['message'] ) ? $args['message'] : '';
$action_label = isset( $args['action_label'] ) ? $args['action_label'] : '';
$action_url   = isset( $args['action_url'] ) ? $args['action_url'] : '';
?>
<?php get_template_part( 'template-parts/components/alert', null, array(
	'type'    => 'empty-state',
	'title'   => $title,
	'message' => $message,
) ); ?>
<?php if ( '' !== $action_label && '' !== $action_url ) : ?>
	<p style="text-align:center;margin-top:var(--space-4)">
		<?php get_template_part( 'template-parts/components/button', null, array(
			'label' => $action_label,
			'url'   => $action_url,
		) ); ?>
	</p>
<?php endif; ?>
