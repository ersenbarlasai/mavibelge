<?php
/**
 * Homepage "Başvurudan Belgelendirmeye" 4-step band. Static explanatory
 * copy (process description, not yeterlilik/ücret/haber/referans data),
 * verbatim from tanitim-site/index.html.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$steps = array(
	array(
		'title' => __( 'Mesleğini Seç', 'mavibelge' ),
		'text'  => __( '14 sektör içinden kendi mesleğini ve yeterlilik seviyeni bul.', 'mavibelge' ),
	),
	array(
		'title' => __( 'Başvurunu Tamamla', 'mavibelge' ),
		'text'  => __( 'Online başvuru formunu doldur, gerekli belgeleri yükle.', 'mavibelge' ),
	),
	array(
		'title' => __( 'Sınava Katıl', 'mavibelge' ),
		'text'  => __( 'Teorik ve performans sınavına belirlenen tarihte katıl.', 'mavibelge' ),
	),
	array(
		'title' => __( 'Belgeni Al', 'mavibelge' ),
		'text'  => __( 'Başarılı olduğunda MYK Mesleki Yeterlilik Belgeni al.', 'mavibelge' ),
	),
);
?>
<section>
	<div class="container">
		<?php
		get_template_part(
			'template-parts/components/section-heading',
			null,
			array(
				'title'       => __( 'Başvurudan Belgelendirmeye', 'mavibelge' ),
				'description' => __( 'Dört adımda mesleki yeterlilik belgenize ulaşın.', 'mavibelge' ),
			)
		);
		?>
		<div class="process-steps">
			<?php foreach ( $steps as $index => $step ) : ?>
				<div class="process-step">
					<div class="step-num"><?php echo esc_html( (string) ( $index + 1 ) ); ?></div>
					<h3><?php echo esc_html( $step['title'] ); ?></h3>
					<p><?php echo esc_html( $step['text'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
