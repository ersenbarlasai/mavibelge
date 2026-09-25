<?php
/**
 * Homepage "5 görev kartı" band, overlapping the hero's bottom edge
 * (.task-grid-overlap). Links are real home_url() paths (no href="#"),
 * copy taken verbatim from tanitim-site/index.html.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tasks = array(
	array(
		'title' => __( 'Sınav Ücretleri', 'mavibelge' ),
		'text'  => __( 'Mesleğinize ait güncel sınav ve belgelendirme ücretlerini inceleyin.', 'mavibelge' ),
		'link'  => __( 'Detayları Gör →', 'mavibelge' ),
		'path'  => 'sinav-ucretleri',
	),
	array(
		'title' => __( 'Belge Sorgulama', 'mavibelge' ),
		'text'  => __( 'MYK portalı üzerinden belgenizi güvenle sorgulayın.', 'mavibelge' ),
		'link'  => __( 'Detayları Gör →', 'mavibelge' ),
		'path'  => 'sonuc-belge-sorgulama',
	),
	array(
		'title' => __( 'Belge Yenileme', 'mavibelge' ),
		'text'  => __( 'Geçerlilik süresi yaklaşan belgeniz için yenileme adımlarını öğrenin.', 'mavibelge' ),
		'link'  => __( 'Detayları Gör →', 'mavibelge' ),
		'path'  => 'belge-yenileme',
	),
	array(
		'title' => __( 'Sınav Süreçleri', 'mavibelge' ),
		'text'  => __( 'Başvurudan sınav ve belgelendirmeye kadar tüm süreci görün.', 'mavibelge' ),
		'link'  => __( 'Detayları Gör →', 'mavibelge' ),
		'path'  => 'sinav-surecleri',
	),
	array(
		'title' => __( 'Sınav Talepleri', 'mavibelge' ),
		'text'  => __( 'Bireysel veya kurumsal sınav talebinizi bize iletin.', 'mavibelge' ),
		'link'  => __( 'Talep Oluştur →', 'mavibelge' ),
		'path'  => 'sinav-talepleri',
	),
);
?>
<section class="section-tight hero-tasks">
	<div class="container">
		<div class="task-grid task-grid-overlap">
			<?php foreach ( $tasks as $task ) : ?>
				<div class="task-card">
					<div class="task-icon" aria-hidden="true">
						<svg class="icon-24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/></svg>
					</div>
					<h3><?php echo esc_html( $task['title'] ); ?></h3>
					<p><?php echo esc_html( $task['text'] ); ?></p>
					<a class="task-link" href="<?php echo esc_url( home_url( '/' . $task['path'] . '/' ) ); ?>"><?php echo esc_html( $task['link'] ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
