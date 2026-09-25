<?php
/**
 * mb_lokasyon single. Adres/telefon/harita/çalışma saatleri build_location_dto() DTO'sundan gelir;
 * harita bağlantısı yalnız https'tir. Sayfa başlığı H1, kart başlığı H2 olur (tek H1).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$location = mavibelge_content_dto( 'location', get_post() );
	?>
	<div class="container section-tight">
		<?php
		get_template_part(
			'template-parts/components/breadcrumb',
			null,
			array(
				'items' => array(
					array( 'label' => __( 'İletişim', 'mavibelge' ), 'url' => home_url( '/iletisim/' ) ),
					array( 'label' => get_the_title() ),
				),
			)
		);
		?>

		<h1><?php the_title(); ?></h1>

		<?php if ( ! empty( $location['address'] ) ) : ?>
			<p><?php echo esc_html( $location['address'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $location['phones'] ) ) : ?>
			<ul>
				<?php foreach ( $location['phones'] as $phone ) : ?>
					<li><a href="<?php echo esc_url( $phone['tel'], array( 'tel' ) ); ?>"><?php echo esc_html( $phone['display'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $location['hours'] ) ) : ?>
			<p><?php echo esc_html( $location['hours'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $location['map_url'] ) ) : ?>
			<a class="directions-link" href="<?php echo esc_url( $location['map_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Yol Tarifi Al', 'mavibelge' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(yeni sekmede açılır)', 'mavibelge' ); ?></span></a>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
