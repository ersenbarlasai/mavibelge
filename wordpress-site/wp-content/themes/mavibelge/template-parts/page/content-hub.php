<?php
/**
 * Hub page body: a card grid of links to child pages, plus whatever
 * the_content() holds above it (an editor-written intro paragraph is
 * optional and safe to be empty). Used for kurumsal/bilgi-merkezi/
 * sinav-ve-basvuru — see inc/page-layouts.php for the slug map and
 * docs/template-architecture.md for why these three get a distinct
 * layout instead of the plain prose one.
 *
 * mavibelge_resolve_hub_link_url() lives in inc/page-layouts.php (loaded
 * once via inc/bootstrap.php) rather than here, because this template
 * part can be loaded more than once per request.
 *
 * $args:
 * - links (array, required) array of array('label'=>string,'path'=>string)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$links = isset( $args['links'] ) && is_array( $args['links'] ) ? $args['links'] : array();
?>
<div class="container section-tight">
	<?php if ( trim( (string) get_the_content() ) ) : ?>
		<div class="entry-content content-narrow">
			<?php the_content(); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $links ) ) : ?>
		<div class="card-grid-3">
			<?php foreach ( $links as $link ) : ?>
				<a class="info-card" href="<?php echo esc_url( mavibelge_resolve_hub_link_url( $link ) ); ?>">
					<strong><?php echo esc_html( $link['label'] ); ?></strong>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
