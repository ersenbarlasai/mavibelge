<?php
/**
 * Faz 5 — GET-only meslek/ücret filter form. Real WordPress GET
 * request, no JS required (progressive enhancement only — brief §6.6).
 * A "Filtrele" button submits; there is no auto-submit-on-change, so a
 * screen reader or keyboard user is never surprised by a page reload
 * they didn't explicitly trigger.
 *
 * $args:
 * - action_url (string, required) — real archive/taxonomy/page URL, never "#"
 * - filters (array, required) — normalized MaviBelge_Core_Catalog_Query shape (q/sector/level/priced/page)
 * - sectors (array) — WP_Term list; omit or pass empty to hide the sector select (e.g. locked taxonomy context)
 * - show_priced (bool) default true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$action_url  = isset( $args['action_url'] ) ? $args['action_url'] : '';
$filters     = isset( $args['filters'] ) && is_array( $args['filters'] )
	? $args['filters']
	: array( 'q' => '', 'sector' => '', 'level' => '', 'priced' => false );
$sectors     = isset( $args['sectors'] ) && is_array( $args['sectors'] ) ? $args['sectors'] : array();
$show_priced = ! isset( $args['show_priced'] ) || (bool) $args['show_priced'];

if ( '' === $action_url ) {
	return;
}

$has_active_filter = '' !== $filters['q'] || '' !== $filters['sector'] || '' !== $filters['level'] || ! empty( $filters['priced'] );
?>
<form class="catalog-filter-form" method="get" action="<?php echo esc_url( $action_url ); ?>">
	<div class="filter-row">
		<div class="form-field">
			<label for="mb_q"><?php esc_html_e( 'Meslek adı veya MYK kodu', 'mavibelge' ); ?></label>
			<input type="search" id="mb_q" name="mb_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="<?php esc_attr_e( 'Örn. kaynakçı veya 10UY0002', 'mavibelge' ); ?>">
		</div>

		<?php if ( ! empty( $sectors ) ) : ?>
			<div class="form-field">
				<label for="mb_sector"><?php esc_html_e( 'Sektör', 'mavibelge' ); ?></label>
				<select id="mb_sector" name="mb_sector">
					<option value=""><?php esc_html_e( 'Tüm Sektörler', 'mavibelge' ); ?></option>
					<?php foreach ( $sectors as $sector ) : ?>
						<option value="<?php echo esc_attr( $sector->slug ); ?>" <?php selected( $filters['sector'], $sector->slug ); ?>><?php echo esc_html( $sector->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<div class="form-field">
			<label for="mb_level"><?php esc_html_e( 'Seviye', 'mavibelge' ); ?></label>
			<select id="mb_level" name="mb_level">
				<option value=""><?php esc_html_e( 'Tüm Seviyeler', 'mavibelge' ); ?></option>
				<?php for ( $level = 1; $level <= 8; $level++ ) : ?>
					<option value="<?php echo esc_attr( (string) $level ); ?>" <?php selected( $filters['level'], (string) $level ); ?>><?php echo esc_html( (string) $level ); ?></option>
				<?php endfor; ?>
			</select>
		</div>

		<?php if ( $show_priced ) : ?>
			<div class="form-field form-field-checkbox">
				<label>
					<input type="checkbox" name="mb_priced" value="1" <?php checked( ! empty( $filters['priced'] ) ); ?>>
					<?php esc_html_e( 'Yalnız güncel fiyatı bulunanlar', 'mavibelge' ); ?>
				</label>
			</div>
		<?php endif; ?>

		<div class="filter-actions">
			<button type="submit" class="btn btn-primary btn-sm"><?php esc_html_e( 'Filtrele', 'mavibelge' ); ?></button>
			<?php if ( $has_active_filter ) : ?>
				<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $action_url ); ?>"><?php esc_html_e( 'Temizle', 'mavibelge' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</form>
