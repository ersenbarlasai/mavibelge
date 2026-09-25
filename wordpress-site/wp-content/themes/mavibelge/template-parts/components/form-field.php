<?php
/**
 * Form field presentation shell. No submit handling, no form element —
 * a real form belongs to a later phase (görev kartı 06/03, Faz 8).
 * This renders one labeled field with optional hint/error text.
 *
 * $args:
 * - id (string, required)
 * - label (string, required)
 * - type (string) 'text'|'email'|'tel'|'search'|'textarea'|'select' — default 'text'
 * - hint (string) optional helper text
 * - error (string) optional — when set, field renders in the invalid state
 * - options (array) for type=select: value => label
 * - required (bool) default false
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed_types = array( 'text', 'email', 'tel', 'search', 'textarea', 'select' );

$id       = isset( $args['id'] ) ? $args['id'] : '';
$label    = isset( $args['label'] ) ? $args['label'] : '';
$type     = isset( $args['type'] ) && in_array( $args['type'], $allowed_types, true ) ? $args['type'] : 'text';
$hint     = isset( $args['hint'] ) ? $args['hint'] : '';
$error    = isset( $args['error'] ) ? $args['error'] : '';
$options  = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
$required = ! empty( $args['required'] );

if ( '' === $id || '' === $label ) {
	return;
}

$error_id       = $id . '-error';
$hint_id        = $id . '-hint';
$describedby    = array();
if ( '' !== $hint ) {
	$describedby[] = $hint_id;
}
if ( '' !== $error ) {
	$describedby[] = $error_id;
}
$describedby_attr = $describedby ? ' aria-describedby="' . esc_attr( implode( ' ', $describedby ) ) . '"' : '';
$required_attr     = $required ? ' required aria-required="true"' : '';
$invalid_attr      = '' !== $error ? ' aria-invalid="true"' : '';
?>
<div class="form-field<?php echo '' !== $error ? ' is-invalid' : ''; ?>">
	<label for="<?php echo esc_attr( $id ); ?>">
		<?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?>
	</label>

	<?php if ( 'textarea' === $type ) : ?>
		<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>"<?php echo $describedby_attr . $required_attr . $invalid_attr; ?>></textarea>
	<?php elseif ( 'select' === $type ) : ?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>"<?php echo $describedby_attr . $required_attr . $invalid_attr; ?>>
			<?php foreach ( $options as $value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $option_label ); ?></option>
			<?php endforeach; ?>
		</select>
	<?php else : ?>
		<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>"<?php echo $describedby_attr . $required_attr . $invalid_attr; ?>>
	<?php endif; ?>

	<?php if ( '' !== $hint ) : ?>
		<p class="hint" id="<?php echo esc_attr( $hint_id ); ?>"><?php echo esc_html( $hint ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $error ) : ?>
		<p class="field-error" id="<?php echo esc_attr( $error_id ); ?>"><?php echo esc_html( $error ); ?></p>
	<?php endif; ?>
</div>
