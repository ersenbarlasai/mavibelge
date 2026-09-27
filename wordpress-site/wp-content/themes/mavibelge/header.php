<?php
/**
 * The header for the Mavi Belge theme.
 *
 * Structure/markup ported from tanitim-site/index.html's header
 * (trust bar, .site-header, primary nav, trust-group, CTA, hamburger)
 * — see docs/design-system.md for the token/ASCII-layout sign-off.
 *
 * SEO meta/canonical/schema is explicitly out of scope for Faz 3
 * (görev kartı 05 / Faz 9); only wp_head() is called here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'İçeriğe atla', 'mavibelge' ); ?></a>

<aside class="trust-bar" aria-label="<?php echo esc_attr__( 'İletişim ve yetki bilgisi', 'mavibelge' ); ?>">
	<div class="container">
		<ul>
			<li><a href="tel:08502154422">0850 215 44 22</a></li>
			<li><a href="mailto:info@mavibelge.com.tr">info@mavibelge.com.tr</a></li>
			<li><?php esc_html_e( 'MYK tarafından yetkilendirilmiş belgelendirme kuruluşu', 'mavibelge' ); ?></li>
		</ul>
	</div>
</aside>

<header class="site-header">
	<div class="container header-main">
		<a class="brand-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Mavi Belge anasayfa', 'mavibelge' ); ?>">
			<img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/logos/header-logo.png' ) ); ?>" alt="Mavi Belge — Uluslararası Sertifikasyon ve Gözetim Hizmetleri" <?php echo mavibelge_local_image_attrs( 'assets/images/logos/header-logo.png', 'eager', 44 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- öznitelikler sayısal/sabit (inc/images.php). ?>>
		</a>

		<nav class="main-nav" id="main-nav" aria-label="<?php esc_attr_e( 'Ana menü', 'mavibelge' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'items_wrap'     => '<ul>%3$s</ul>',
					'walker'         => new MaviBelge_Nav_Walker(),
					'fallback_cb'    => 'mavibelge_primary_nav_fallback',
					'depth'          => 2,
					'echo'           => true,
				)
			);
			?>
			<div class="mobile-header-cta">
				<a class="btn btn-primary btn-block" href="<?php echo esc_url( mavibelge_url( 'online-basvuru' ) ); ?>"><?php esc_html_e( 'Online Başvuru', 'mavibelge' ); ?></a>
			</div>
		</nav>

		<?php get_template_part( 'template-parts/components/trust-logo', null, array( 'context' => 'header' ) ); ?>

		<div class="header-cta">
			<a class="btn btn-primary" href="<?php echo esc_url( mavibelge_url( 'online-basvuru' ) ); ?>"><?php esc_html_e( 'Online Başvuru', 'mavibelge' ); ?></a>
		</div>

		<button type="button" class="menu-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="<?php esc_attr_e( 'Menüyü aç/kapat', 'mavibelge' ); ?>">
			<span></span>
		</button>
	</div>
</header>

<main id="main" class="site-main" tabindex="-1">
