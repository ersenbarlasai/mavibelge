<?php
/**
 * mb_yeterlilik single — WordPress equivalent of tanitim-site's
 * yeterlilik.html detail layout (Faz 12e: koyu page-hero + 2/3 içerik +
 * 1/3 sticky CTA). Shows only fields that are real, saved meta keys per
 * docs/content-model.md (_mb_myk_code, _mb_level, _mb_revision,
 * mb_sektor terms) plus the editor content. No fee/price is shown or
 * computed via a direct query here — mb_ucret is a separate, non-public
 * CPT, and this template never queries it itself.
 *
 * Faz 5: the fee block (right-hand CTA card) calls
 * mavibelge_get_active_fees_for_qualification(), which matches a fee
 * ONLY via the real, stored _mb_qualification_id relation — never a
 * name/MYK-code similarity guess (see docs/catalog-service-contract.md).
 * The fee is rendered exactly ONCE (in the CTA card).
 *
 * Faz 12e: "Yeterlilik Birimleri", "Sınav Yapısı", "Belge Geçerliliği"
 * ve "İlgili Dokümanlar" bölümlerinde kayıt bazlı doğrulanmış veri
 * YOKTUR; metin tanitim-site/yeterlilik.html'deki genel/örnek içeriktir
 * ve görünür "Örnek yapı" / "Bilgi güncellenecektir" uyarısıyla sunulur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id  = get_the_ID();
	$myk_code = (string) get_post_meta( $post_id, '_mb_myk_code', true );
	$level    = (string) get_post_meta( $post_id, '_mb_level', true );
	$revision = (string) get_post_meta( $post_id, '_mb_revision', true );
	$sectors  = taxonomy_exists( 'mb_sektor' ) ? get_the_terms( $post_id, 'mb_sektor' ) : array();
	if ( ! is_array( $sectors ) ) {
		$sectors = array();
	}
	$primary_sector = ! empty( $sectors ) ? $sectors[0] : null;
	$hero_image     = mavibelge_term_hero_image_url( $primary_sector );
	$has_content    = '' !== trim( wp_strip_all_tags( (string) get_the_content() ) );
	$pending_label  = __( 'Kurum tarafından doğrulanacak', 'mavibelge' );
	?>
	<section class="page-hero qual-hero" style="background-image:url('<?php echo esc_url( $hero_image ); ?>')">
		<div class="container">
			<?php
			get_template_part(
				'template-parts/components/breadcrumb',
				null,
				array(
					'items' => array(
						array(
							'label' => __( 'Anasayfa', 'mavibelge' ),
							'url'   => home_url( '/' ),
						),
						array(
							/*
							 * Real registered rewrite slug for mb_yeterlilik is
							 * "yeterlilikler" (includes/class-content-types.php) —
							 * get_post_type_archive_link() always resolves to
							 * whatever WordPress actually registered.
							 */
							'label' => __( 'Meslekler ve Belgeler', 'mavibelge' ),
							'url'   => (string) get_post_type_archive_link( 'mb_yeterlilik' ),
						),
						array( 'label' => get_the_title() ),
					),
				)
			);
			?>
			<?php if ( '' !== $myk_code ) : ?>
				<span class="eyebrow">
					<?php
					/* translators: %s: MYK qualification code */
					printf( esc_html__( 'MYK Kodu: %s', 'mavibelge' ), esc_html( $myk_code ) );
					?>
				</span>
			<?php endif; ?>
			<h1><?php the_title(); ?></h1>
			<?php if ( ! empty( $sectors ) || '' !== $level ) : ?>
				<p class="qual-hero-meta">
					<?php if ( ! empty( $sectors ) ) : ?>
						<?php esc_html_e( 'Sektör:', 'mavibelge' ); ?>
						<?php
						$sector_links = array();
						foreach ( $sectors as $sector ) {
							$sector_link = get_term_link( $sector );
							$sector_links[] = is_wp_error( $sector_link )
								? esc_html( $sector->name )
								: '<a href="' . esc_url( $sector_link ) . '">' . esc_html( $sector->name ) . '</a>';
						}
						echo implode( ', ', $sector_links ); // phpcs:ignore WordPress.Security.EscapeOutput -- her parça yukarıda kaçışlandı
						?>
					<?php endif; ?>
					<?php if ( ! empty( $sectors ) && '' !== $level ) : ?>
						<span aria-hidden="true">·</span>
					<?php endif; ?>
					<?php if ( '' !== $level ) : ?>
						<?php
						/* translators: %s: MYK qualification level */
						printf( esc_html__( 'Seviye %s', 'mavibelge' ), esc_html( $level ) );
						?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section-tight">
		<div class="container qual-detail-grid">
			<div class="qual-detail-main">
				<h2><?php esc_html_e( 'Yeterlilik Özeti', 'mavibelge' ); ?></h2>
				<ul class="qual-facts">
					<li><strong><?php esc_html_e( 'MYK Kodu', 'mavibelge' ); ?></strong><span><?php echo esc_html( '' !== $myk_code ? $myk_code : $pending_label ); ?></span></li>
					<li><strong><?php esc_html_e( 'Seviye', 'mavibelge' ); ?></strong><span><?php echo esc_html( '' !== $level ? $level : $pending_label ); ?></span></li>
					<li><strong><?php esc_html_e( 'Revizyon', 'mavibelge' ); ?></strong><span><?php echo esc_html( '' !== $revision ? $revision : $pending_label ); ?></span></li>
					<li><strong><?php esc_html_e( 'Sektör', 'mavibelge' ); ?></strong><span><?php echo esc_html( ! empty( $sectors ) ? implode( ', ', wp_list_pluck( $sectors, 'name' ) ) : $pending_label ); ?></span></li>
				</ul>

				<?php if ( $has_content ) : ?>
					<div class="entry-content qual-summary">
						<?php the_content(); ?>
					</div>
				<?php else : ?>
					<p class="qual-summary qual-placeholder"><?php esc_html_e( 'Bu yeterlilik, adayın ilgili ulusal meslek standardında tanımlanan bilgi, beceri ve yetkinlikleri kanıtlamasını sağlar. Kısa tanım, kapsam ve ön koşul bilgileri kurum onayının ardından bu alanda yayınlanacaktır.', 'mavibelge' ); ?></p>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Yeterlilik Birimleri', 'mavibelge' ); ?></h2>
				<p class="hint qual-sample-note"><?php esc_html_e( 'Örnek yapı — kesin birim listesi ilgili ulusal yeterlilik dokümanına göre yayın öncesi doğrulanacaktır.', 'mavibelge' ); ?></p>
				<ul class="unit-list is-sample" aria-label="<?php esc_attr_e( 'Örnek birim yapısı (doğrulanmamış)', 'mavibelge' ); ?>">
					<li><span class="unit-code">A1</span><span><?php esc_html_e( 'İş Sağlığı ve Güvenliği, Çevre ve Kalite Kuralları', 'mavibelge' ); ?></span></li>
					<li><span class="unit-code">B1</span><span><?php esc_html_e( 'Mesleğe özgü temel işlemler', 'mavibelge' ); ?></span></li>
					<li><span class="unit-code">B2</span><span><?php esc_html_e( 'Mesleğe özgü ileri düzey işlemler', 'mavibelge' ); ?></span></li>
				</ul>

				<h2><?php esc_html_e( 'Sınav Yapısı', 'mavibelge' ); ?></h2>
				<ol class="icon-list">
					<li><span class="bullet" aria-hidden="true">1</span><span><?php esc_html_e( 'Teorik sınav (çoktan seçmeli/yazılı)', 'mavibelge' ); ?></span></li>
					<li><span class="bullet" aria-hidden="true">2</span><span><?php esc_html_e( 'Performansa dayalı uygulama sınavı', 'mavibelge' ); ?></span></li>
					<li><span class="bullet" aria-hidden="true">3</span><span><?php esc_html_e( 'Geçme notu ve değerlendirme kriterleri: Bilgi güncellenecektir', 'mavibelge' ); ?></span></li>
				</ol>

				<h2><?php esc_html_e( 'Belge Geçerliliği', 'mavibelge' ); ?></h2>
				<p>
					<?php esc_html_e( 'Belge geçerlilik süresi ve gözetim dönemleri ilgili ulusal yeterlilik dokümanına göre belirlenir. Güncel süre bilgisi için', 'mavibelge' ); ?>
					<a href="<?php echo esc_url( mavibelge_url( 'belge-yenileme' ) ); ?>"><?php esc_html_e( 'Belge Yenileme', 'mavibelge' ); ?></a>
					<?php esc_html_e( 'sayfasını inceleyin.', 'mavibelge' ); ?>
				</p>

				<h2><?php esc_html_e( 'İlgili Dokümanlar', 'mavibelge' ); ?></h2>
				<div class="doc-card">
					<div class="doc-icon" aria-hidden="true"><?php echo mavibelge_icon_svg( 'ui', 'document', 'icon-24' ); // phpcs:ignore WordPress.Security.EscapeOutput -- sabit, kayıtlı SVG ?></div>
					<div>
						<strong><?php esc_html_e( 'Ulusal Yeterlilik Dokümanı', 'mavibelge' ); ?></strong>
						<div class="doc-meta">
							<?php esc_html_e( 'Bilgi güncellenecektir', 'mavibelge' ); ?> ·
							<a href="<?php echo esc_url( mavibelge_url( 'dokumanlar' ) ); ?>"><?php esc_html_e( 'Doküman Merkezi', 'mavibelge' ); ?></a>
						</div>
					</div>
				</div>
			</div>

			<aside class="sticky-cta" aria-label="<?php esc_attr_e( 'Ücret ve başvuru', 'mavibelge' ); ?>">
				<div class="info-card qual-cta-card">
					<h2 class="qual-cta-title"><?php esc_html_e( 'Ücret', 'mavibelge' ); ?></h2>
					<?php
					$fees = mavibelge_get_active_fees_for_qualification( $post_id );
					if ( ! empty( $fees ) ) :
						foreach ( $fees as $fee ) :
							?>
							<div class="qual-cta-fee">
								<?php if ( count( $fees ) > 1 && '' !== (string) $fee['profession_name'] ) : ?>
									<p class="qual-cta-fee-name"><?php echo esc_html( $fee['profession_name'] ); ?></p>
								<?php endif; ?>
								<?php get_template_part( 'template-parts/catalog/fee-options', null, array( 'fee' => $fee, 'expanded' => true ) ); ?>
							</div>
							<?php
						endforeach;
					else :
						?>
						<p class="hint"><?php esc_html_e( 'Bu yeterlilik için şu an güncel bir ücret kaydı bulunmuyor. Güncel ücret bilgisi için:', 'mavibelge' ); ?></p>
					<?php endif; ?>
					<a class="btn btn-secondary btn-block" href="<?php echo esc_url( mavibelge_url( 'sinav-ucretleri' ) ); ?>"><?php esc_html_e( 'Sınav Ücretlerini Gör', 'mavibelge' ); ?></a>

					<h2 class="qual-cta-title"><?php esc_html_e( 'Başvuru', 'mavibelge' ); ?></h2>
					<a class="btn btn-primary btn-block" href="<?php echo esc_url( mavibelge_application_url( $myk_code ) ); ?>"><?php esc_html_e( 'Bu Yeterlilik İçin Başvur', 'mavibelge' ); ?></a>
				</div>
			</aside>
		</div>
	</section>
	<?php
endwhile;

get_footer();
