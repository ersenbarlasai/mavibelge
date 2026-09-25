<?php
/**
 * Homepage — WordPress equivalent of the approved tanitim-site/index.html
 * ("ANASAYFA / V1 Görev Odaklı / Desktop 1440 — Düzeltilmiş (v2)").
 * Section order matches the real, current index.html exactly (9
 * sections; verified directly, not from memory). No separate "öne
 * çıkan meslek/yeterlilik" section exists in the approved reference —
 * the sector grid and hero search panel are its only meslek/yeterlilik
 * surface; one is not invented here. The impact-stats band shows a 4th
 * value ("81 — İlde Hizmet") that is not in the frozen static
 * tanitim-site/index.html but is user-approved content from the Faz 4
 * task brief itself — see docs/integration-notes/faz4-homepage-content.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

get_template_part( 'template-parts/home/hero' );
get_template_part( 'template-parts/home/tasks' );
get_template_part( 'template-parts/home/stats' );
get_template_part( 'template-parts/home/sector-grid' );
get_template_part( 'template-parts/home/process-steps' );
get_template_part( 'template-parts/home/trust-cta' );
get_template_part( 'template-parts/home/news' );
get_template_part( 'template-parts/home/references' );
get_template_part( 'template-parts/home/cta-band' );

get_footer();
