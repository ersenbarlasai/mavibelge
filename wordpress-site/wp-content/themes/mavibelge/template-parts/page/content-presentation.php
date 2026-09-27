<?php
/**
 * Faz 13 — referans sayfa ailelerinin TEK ortak gövdesi (page.php'den, döngü içinde çağrılır).
 *
 * İçerik WordPress editöründen gelir (`the_content` filtresi) ve METNİ DEĞİŞMEZ; bu parça yalnız statik referanstaki
 * sunumu uygular: genişlik (wide 1280 / prose 820 / narrow 760), aile bileşeni (numaralı/madde liste, bilgi kartları,
 * kart ızgarası, adımlar, düz metin), isteğe bağlı logo (tema varlığı) ve gezinme CTA'sı (`mavibelge_url()`).
 * Kök-göreli iç bağlantılar etkin kalıcı bağlantı yapısına çevrilir. Aile kaydı: inc/page-layouts.php.
 *
 * $args:
 * - presentation (array, zorunlu) mavibelge_page_presentation() satırı
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$presentation = isset( $args['presentation'] ) && is_array( $args['presentation'] ) ? $args['presentation'] : array();
$family       = isset( $presentation['family'] ) ? (string) $presentation['family'] : 'prose';
$width        = isset( $presentation['width'] ) ? (string) $presentation['width'] : 'prose';
$cta          = isset( $presentation['cta'] ) && is_array( $presentation['cta'] ) ? $presentation['cta'] : array();
$logo         = isset( $presentation['logo'] ) ? (string) $presentation['logo'] : '';
$card_logos   = isset( $presentation['card_logos'] ) && is_array( $presentation['card_logos'] ) ? $presentation['card_logos'] : array();
$list_style   = isset( $presentation['list'] ) && 'bullets' === $presentation['list'] ? 'bullets' : 'numbered';
$columns      = isset( $presentation['columns'] ) ? max( 1, min( 3, (int) $presentation['columns'] ) ) : 3;
$split_tag    = isset( $presentation['heading'] ) ? (string) $presentation['heading'] : 'h2';

$html  = mavibelge_rendered_content();
$logos = array(
	'myk'    => array( 'file' => 'assets/images/logos/myk_logo.png', 'alt' => 'MYK' ),
	'turkak' => array( 'file' => 'assets/images/logos/turkak_logo.png', 'alt' => 'TÜRKAK' ),
);
$logo_img = function ( $key ) use ( $logos ) {
	if ( ! isset( $logos[ $key ] ) ) {
		return '';
	}
	return '<img class="mb-logo" src="' . esc_url( get_theme_file_uri( $logos[ $key ]['file'] ) ) . '" alt="' . esc_attr( $logos[ $key ]['alt'] ) . '" loading="lazy" decoding="async">';
};
$list_class = 'entry-content mb-list mb-list--' . $list_style;
?>
<section class="section-tight mb-section">
	<div class="container mb-body mb-body--<?php echo esc_attr( $width ); ?>" data-mb-family="<?php echo esc_attr( $family ); ?>">
		<?php
		if ( 'prose' === $family ) {
			echo '' !== $logo ? $logo_img( $logo ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url/esc_attr içeride.
			echo '<div class="entry-content mb-prose">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı.
		} elseif ( 'list' === $family ) {
			echo '<div class="' . esc_attr( $list_class ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı.
		} elseif ( 'card' === $family || 'cards' === $family || 'steps' === $family ) {
			$parts = mavibelge_split_content_sections( $html, $split_tag );
			if ( empty( $parts['sections'] ) ) {
				// Başlıksız içerik: tek kart (card) ya da düz metin (cards/steps) — içerik asla kaybolmaz.
				echo 'card' === $family ? '<div class="info-card mb-card"><div class="entry-content">' . $html . '</div></div>' : '<div class="entry-content mb-prose">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı.
			} else {
				if ( '' !== trim( $parts['intro'] ) ) {
					echo '<div class="entry-content mb-intro">' . $parts['intro'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı.
				}
				if ( 'steps' === $family ) {
					echo '<ol class="process-steps mb-steps">';
					foreach ( $parts['sections'] as $i => $section ) {
						echo '<li class="process-step"><span class="step-num" aria-hidden="true">' . (int) ( $i + 1 ) . '</span>' . mavibelge_section_heading( $section['heading_html'], 2, '', $section['heading_attrs'] ) . '<div class="entry-content">' . $section['body'] . '</div></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı.
					}
					echo '</ol>';
				} else {
					echo 'cards' === $family ? '<div class="mb-cards mb-cards--' . (int) $columns . '">' : '<div class="mb-card-stack">';
					foreach ( $parts['sections'] as $i => $section ) {
						$media = isset( $card_logos[ $i ] ) ? $logo_img( (string) $card_logos[ $i ] ) : '';
						echo '<div class="info-card mb-card">' . $media . mavibelge_section_heading( $section['heading_html'], 2, 'mb-card-title', $section['heading_attrs'] ) . '<div class="entry-content">' . $section['body'] . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı.
					}
					echo '</div>';
				}
				if ( '' !== trim( $parts['rest'] ) ) {
					echo '<div class="' . esc_attr( $list_class ) . ' mb-rest">' . $parts['rest'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı.
				}
			}
		} else {
			echo '<div class="entry-content mb-prose">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content çıktısı.
		}
		if ( ! empty( $cta['label'] ) && ! empty( $cta['path'] ) ) :
			?>
			<p class="mb-cta-row"><a class="btn btn-primary mb-cta" href="<?php echo esc_url( mavibelge_url( $cta['path'] ) ); ?>"><?php echo esc_html( $cta['label'] ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
