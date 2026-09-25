<?php
/**
 * Accessible table wrapper. Presentation shell only — the caller
 * supplies $args['head'] (array of column header strings) and
 * $args['rows'] (array of arrays of cell strings, plain text, escaped
 * here). For anything richer than plain text per cell, build the
 * table markup directly instead of using this shell.
 *
 * $args:
 * - caption (string) optional <caption>, visually hidden but read by
 *   screen readers (table purpose)
 * - head (array, required) column header labels
 * - rows (array, required) array of arrays of cell text
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$caption = isset( $args['caption'] ) ? $args['caption'] : '';
$head    = isset( $args['head'] ) && is_array( $args['head'] ) ? $args['head'] : array();
$rows    = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
?>
<div class="table-wrap">
	<table class="data-table">
		<?php if ( '' !== $caption ) : ?>
			<caption class="visually-hidden"><?php echo esc_html( $caption ); ?></caption>
		<?php endif; ?>
		<?php if ( ! empty( $head ) ) : ?>
			<thead>
				<tr>
					<?php foreach ( $head as $column ) : ?>
						<th scope="col"><?php echo esc_html( $column ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
		<?php endif; ?>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<?php foreach ( $row as $cell ) : ?>
						<td><?php echo esc_html( $cell ); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
