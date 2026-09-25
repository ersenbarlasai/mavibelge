<?php
/**
 * Doküman kartı — build_document_dto() DTO'sundan. İndirme bağlantısı YALNIZ servisin doğruladığı
 * gerçek dosya (izinli MIME, diskte var) için üretilir; dosya yoksa dürüst bir "dosya eklenmedi" notu.
 *
 * $args:
 * - item (array, zorunlu) doküman DTO'su
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item = isset( $args['item'] ) && is_array( $args['item'] ) ? $args['item'] : array();
if ( empty( $item['id'] ) ) {
	return;
}
$file = isset( $item['file'] ) && is_array( $item['file'] ) ? $item['file'] : null;
$meta = array();
if ( '' !== $item['version'] ) {
	/* translators: %s: document version */
	$meta[] = sprintf( __( 'Sürüm: %s', 'mavibelge' ), $item['version'] );
}
if ( '' !== $item['publish_date'] ) {
	/* translators: %s: publish date */
	$meta[] = sprintf( __( 'Yayın: %s', 'mavibelge' ), mysql2date( get_option( 'date_format' ), $item['publish_date'] ) );
}
if ( '' !== $item['valid_until'] ) {
	/* translators: %s: valid-until date */
	$meta[] = sprintf( __( 'Geçerlilik: %s', 'mavibelge' ), mysql2date( get_option( 'date_format' ), $item['valid_until'] ) );
}
?>
<div class="doc-card">
	<div class="doc-icon" aria-hidden="true">
		<svg class="icon-20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/></svg>
	</div>
	<div>
		<h3><a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
		<?php if ( ! empty( $meta ) ) : ?>
			<div class="doc-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $item['expired'] ) ) : ?>
			<span class="badge badge-neutral"><?php esc_html_e( 'Geçerlilik süresi dolmuş', 'mavibelge' ); ?></span>
		<?php endif; ?>
		<?php if ( $file ) : ?>
			<a class="btn btn-secondary btn-sm" href="<?php echo esc_url( $file['url'] ); ?>" rel="noopener">
				<?php
				echo esc_html(
					trim(
						sprintf(
							/* translators: 1: file type (PDF), 2: file size */
							__( 'İndir (%1$s %2$s)', 'mavibelge' ),
							$file['type_label'],
							$file['size']
						)
					)
				);
				?>
			</a>
		<?php else : ?>
			<p class="doc-meta"><?php esc_html_e( 'Doküman dosyası henüz eklenmedi.', 'mavibelge' ); ?></p>
		<?php endif; ?>
	</div>
</div>
