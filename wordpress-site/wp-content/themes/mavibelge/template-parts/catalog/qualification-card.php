<?php
/**
 * Faz 5 Düzeltme ve Kabul §2.6 — renders ONE yeterlilik result card
 * straight from the catalog service's own DTO
 * (MaviBelge_Core_Catalog_Service::get_qualification_results()'s
 * 'items' shape: id/title/permalink/myk_code/level/excerpt/sectors[]).
 *
 * This does NOT re-read post/meta/term data itself — the service already
 * resolved title/MYK-code/level/sector a moment earlier.
 *
 * Faz 12e: tanitim-site/meslekler.html kart yapısı — başlık, MYK kodu /
 * seviye / sektör etiketleri ve İKİ ayrı aksiyon ("Detayları Gör" ->
 * gerçek permalink, "Başvuru Yap" -> mavibelge_application_url()). Kartın
 * kendisi bağlantı DEĞİLDİR (iç içe bağlantı yok).
 *
 * $args:
 * - item (array, required) — one element of get_qualification_results()['items']
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item = isset( $args['item'] ) && is_array( $args['item'] ) ? $args['item'] : null;
if ( null === $item ) {
	return;
}

$title    = isset( $item['title'] ) ? (string) $item['title'] : '';
$myk_code = ! empty( $item['myk_code'] ) ? (string) $item['myk_code'] : '';
?>
<article class="qual-card">
	<div class="qual-info">
		<h2 class="qual-card-title"><?php echo esc_html( $title ); ?></h2>
		<div class="qual-tags">
			<?php if ( '' !== $myk_code ) : ?>
				<span class="tag"><?php echo esc_html( $myk_code ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $item['level'] ) ) : ?>
				<span class="tag">
					<?php
					/* translators: %s: MYK qualification level, "1"-"8" */
					printf( esc_html__( 'Seviye %s', 'mavibelge' ), esc_html( $item['level'] ) );
					?>
				</span>
			<?php endif; ?>
			<?php if ( ! empty( $item['sectors'] ) && is_array( $item['sectors'] ) ) : ?>
				<?php foreach ( $item['sectors'] as $sector ) : ?>
					<span class="tag"><?php echo esc_html( $sector['name'] ); ?></span>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<div class="qual-actions">
		<a class="btn btn-secondary btn-sm" href="<?php echo esc_url( $item['permalink'] ); ?>" aria-label="<?php /* translators: %s: qualification title */ echo esc_attr( sprintf( __( 'Detayları Gör: %s', 'mavibelge' ), $title ) ); ?>"><?php esc_html_e( 'Detayları Gör', 'mavibelge' ); ?></a>
		<a class="btn btn-primary btn-sm" href="<?php echo esc_url( mavibelge_application_url( $myk_code ) ); ?>" aria-label="<?php /* translators: %s: qualification title */ echo esc_attr( sprintf( __( 'Başvuru Yap: %s', 'mavibelge' ), $title ) ); ?>"><?php esc_html_e( 'Başvuru Yap', 'mavibelge' ); ?></a>
	</div>
</article>
