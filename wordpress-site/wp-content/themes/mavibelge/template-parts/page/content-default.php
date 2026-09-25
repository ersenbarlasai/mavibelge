<?php
/**
 * Generic WordPress Page body: editor content via the_content(), inside
 * the approved reading-width container. Used by page.php for every
 * ordinary corporate/legal/info page (Hakkımızda, Misyon ve Vizyon,
 * Kalite Politikamız, KVKK, Mevzuat, etc.) — the actual long-form text
 * is NOT hardcoded here or in any PHP template; it lives in the real
 * WordPress Page content, written by an editor (Faz 6 content import).
 * This part just renders it safely.
 *
 * Runs inside the loop (the_post() already called by the caller).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="section-tight">
	<div class="container" style="max-width:820px">
		<?php the_content(); ?>
	</div>
</section>
