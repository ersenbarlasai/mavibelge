<?php
/**
 * TEK form alanı (template-parts/forms/form.php içinden çağrılır; Faz 12f'de adım gruplaması için ayrı parçaya alındı,
 * işaretleme DEĞİŞMEDİ). Hassas alanlar (T.C. kimlik no) ASLA geri doldurulmaz. Değer önceliği: POST ile gelen doğrulama
 * yankısı (values) > `?meslek` ön seçimi (preselect, yalnız eklentinin allowlist'inden gelen seçenek anahtarı) > boş.
 *
 * $args:
 * - field (array, zorunlu) describe() DTO'sundaki bir alan
 * - form  (array, zorunlu) describe() DTO'su
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field     = isset( $args['field'] ) && is_array( $args['field'] ) ? $args['field'] : null;
$form      = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : null;
if ( null === $field || null === $form ) {
	return;
}
$errors    = isset( $form['errors'] ) && is_array( $form['errors'] ) ? $form['errors'] : array();
$values    = isset( $form['values'] ) && is_array( $form['values'] ) ? $form['values'] : array();
$preselect = isset( $form['preselect'] ) && is_array( $form['preselect'] ) ? $form['preselect'] : array();
$prefix    = 'mbf-' . $form['id'] . '-';
$autocomplete = array( 'full_name' => 'name', 'email' => 'email', 'phone' => 'tel', 'company' => 'organization', 'national_id' => 'off' );

$name     = $field['name'];
$id       = $prefix . $name;
$has_err  = isset( $errors[ $name ] );
$err_id   = $id . '-error';
$value    = isset( $values[ $name ] ) ? $values[ $name ] : ( isset( $preselect[ $name ] ) ? $preselect[ $name ] : '' );
$required = ! empty( $field['required'] );
$describe = $has_err ? ' aria-describedby="' . esc_attr( $err_id ) . '" aria-invalid="true"' : '';
$req_attr = $required ? ' required aria-required="true"' : '';
$req_mark = $required ? ' <span aria-hidden="true">*</span>' : '';
?>
<div class="form-field<?php echo $has_err ? ' has-error' : ''; ?>">
	<?php if ( 'consent' === $field['type'] ) : ?>
		<label for="<?php echo esc_attr( $id ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" required aria-required="true"<?php echo $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- yukarıda esc_attr ile üretildi. ?> style="width:auto;min-height:auto;display:inline-block;margin-right:8px">
			<?php echo esc_html( $field['label'] ); ?> <span aria-hidden="true">*</span>
		</label>
	<?php elseif ( 'radio' === $field['type'] ) : ?>
		<fieldset class="radio-group">
			<legend><?php echo esc_html( $field['label'] ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sabit işaret. ?></legend>
			<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
				<label><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $option_value ); ?>"<?php echo (string) $value === (string) $option_value ? ' checked' : ''; ?><?php echo $required ? ' required' : ''; ?>> <?php echo esc_html( $option_label ); ?></label>
			<?php endforeach; ?>
		</fieldset>
	<?php else : ?>
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sabit işaret. ?></label>
		<?php if ( 'textarea' === $field['type'] ) : ?>
			<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="6"<?php echo $field['max'] > 0 ? ' maxlength="' . (int) $field['max'] . '"' : ''; ?><?php echo $req_attr . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sabit/esc_attr'li parçalar. ?>><?php echo esc_textarea( $value ); ?></textarea>
		<?php elseif ( 'select' === $field['type'] ) : ?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $req_attr . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<option value=""><?php esc_html_e( 'Seçiniz', 'mavibelge' ); ?></option>
				<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $option_value ); ?>"<?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php elseif ( 'file' === $field['type'] ) : ?>
			<input type="file" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name . ( $field['multiple'] ? '[]' : '' ) ); ?>" accept="<?php echo esc_attr( $field['accept'] ); ?>"<?php echo $field['multiple'] ? ' multiple' : ''; ?><?php echo $req_attr . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php else : ?>
			<input type="<?php echo esc_attr( in_array( $field['type'], array( 'email', 'tel' ), true ) ? $field['type'] : 'text' ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $field['sensitive'] ? '' : $value ); ?>"<?php echo $field['max'] > 0 ? ' maxlength="' . (int) $field['max'] . '"' : ''; ?> autocomplete="<?php echo esc_attr( isset( $autocomplete[ $name ] ) ? $autocomplete[ $name ] : 'off' ); ?>"<?php echo 'national_id' === $name ? ' inputmode="numeric" pattern="[0-9]{11}"' : ''; ?><?php echo $req_attr . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( $has_err ) : ?>
		<span class="field-error" id="<?php echo esc_attr( $err_id ); ?>" role="alert"><?php echo esc_html( $errors[ $name ] ); ?></span>
	<?php endif; ?>
</div>
