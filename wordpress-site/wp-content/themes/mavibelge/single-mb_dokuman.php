<?php
/**
 * mb_dokuman single. Dosya bilgisi build_document_dto() DTO'sundan gelir: indirme bağlantısı yalnız
 * doğrulanmış gerçek dosya için (izinli MIME + diskte var) üretilir; aksi hâlde dürüst uyarı gösterilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$doc  = mavibelge_content_dto( 'document', get_post() );
	$file = isset( $doc['file'] ) && is_array( $doc['file'] ) ? $doc['file'] : null;
	?>
	<div class="container section-tight">
		<?php
		get_template_part(
			'template-parts/components/breadcrumb',
			null,
			array(
				'items' => array(
					array( 'label' => __( 'Bilgi Merkezi', 'mavibelge' ), 'url' => mavibelge_url( 'bilgi-merkezi' ) ),
					array( 'label' => __( 'Dokümanlar', 'mavibelge' ), 'url' => mavibelge_url( 'dokumanlar' ) ),
					array( 'label' => get_the_title() ),
				),
			)
		);
		?>

		<h1><?php the_title(); ?></h1>

		<ul class="qual-facts">
			<?php if ( ! empty( $doc['version'] ) ) : ?>
				<li><strong><?php esc_html_e( 'Sürüm', 'mavibelge' ); ?></strong><?php echo esc_html( $doc['version'] ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $doc['publish_date'] ) ) : ?>
				<li><strong><?php esc_html_e( 'Yayın Tarihi', 'mavibelge' ); ?></strong><?php echo esc_html( mysql2date( get_option( 'date_format' ), $doc['publish_date'] ) ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $doc['valid_until'] ) ) : ?>
				<li><strong><?php esc_html_e( 'Geçerlilik', 'mavibelge' ); ?></strong><?php echo esc_html( mysql2date( get_option( 'date_format' ), $doc['valid_until'] ) ); ?><?php echo ! empty( $doc['expired'] ) ? ' — ' . esc_html__( 'süresi dolmuş', 'mavibelge' ) : ''; ?></li>
			<?php endif; ?>
		</ul>

		<?php if ( trim( (string) get_the_content() ) ) : ?>
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<?php if ( $file ) : ?>
			<?php
			get_template_part(
				'template-parts/components/button',
				null,
				array(
					'label' => trim( sprintf( /* translators: 1: file type, 2: size */ __( 'Dokümanı İndir (%1$s %2$s)', 'mavibelge' ), $file['type_label'], $file['size'] ) ),
					'url'   => $file['url'],
				)
			);
			?>
		<?php else : ?>
			<?php
			get_template_part(
				'template-parts/components/alert',
				null,
				array(
					'type'    => 'alert',
					'variant' => 'warning',
					'message' => __( 'Bu dokümanın dosyası henüz eklenmemiş veya kullanılamıyor.', 'mavibelge' ),
				)
			);
			?>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
