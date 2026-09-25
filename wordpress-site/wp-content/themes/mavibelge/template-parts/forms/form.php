<?php
/**
 * Gerçek form (yalnız kapı AÇIKKEN çağrılır). JavaScript GEREKTİRMEZ: sunucu tarafı doğrulama, `required`/`type`
 * öznitelikleri tarayıcı yardımıdır. Hatalarda aynı yanıtta yeniden çizim: hata özeti (role="alert") sayfa
 * başında, alan hataları alanın altında (`aria-invalid` + `aria-describedby`). Hassas alanlar (T.C. kimlik no)
 * ASLA geri doldurulmaz. Bal küpü alanı görünmez ve klavyeyle odaklanamaz. Onay kutusunun metni yapılandırmadaki
 * kurumca ONAYLI metindir (kodda uydurma metin yok).
 *
 * $args:
 * - form (array, zorunlu) MaviBelge_Core_Forms_Service::describe() DTO'su (open === true)
 * - action_url (string) formun gönderileceği URL (varsayılan: geçerli sayfa)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form   = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
if ( empty( $form['open'] ) ) {
	return;
}
$action = isset( $args['action_url'] ) ? $args['action_url'] : get_permalink();
$errors = isset( $form['errors'] ) && is_array( $form['errors'] ) ? $form['errors'] : array();
$values = isset( $form['values'] ) && is_array( $form['values'] ) ? $form['values'] : array();
$multipart = false;
foreach ( $form['fields'] as $field ) {
	if ( 'file' === $field['type'] ) {
		$multipart = true;
	}
}
$prefix = 'mbf-' . $form['id'] . '-';
$autocomplete = array( 'full_name' => 'name', 'email' => 'email', 'phone' => 'tel', 'company' => 'organization', 'national_id' => 'off' );
?>
<div class="form-card" id="mb-form-<?php echo esc_attr( $form['id'] ); ?>">
	<?php if ( ! empty( array_diff_key( $errors, array( '_form' => true ) ) ) ) : ?>
		<div class="form-error-summary" role="alert" tabindex="-1">
			<strong><?php esc_html_e( 'Form gönderilemedi. Lütfen aşağıdaki alanları kontrol edin:', 'mavibelge' ); ?></strong>
			<ul>
				<?php foreach ( $errors as $field_name => $message ) : ?>
					<?php
					if ( '_form' === $field_name ) {
						continue; // Genel iletiler form-status.php'de gösterilir.
					}
					$label = '';
					foreach ( $form['fields'] as $field ) {
						if ( $field['name'] === $field_name ) {
							$label = $field['label'];
						}
					}
					?>
					<li>
						<?php if ( '' !== $label ) : ?>
							<a href="#<?php echo esc_attr( $prefix . $field_name ); ?>"><?php echo esc_html( $label ); ?></a> —
						<?php endif; ?>
						<?php echo esc_html( $message ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $action ); ?>"<?php echo $multipart ? ' enctype="multipart/form-data"' : ''; ?> novalidate>
		<input type="hidden" name="<?php echo esc_attr( $form['names']['form'] ); ?>" value="<?php echo esc_attr( $form['id'] ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $form['names']['token'] ); ?>" value="<?php echo esc_attr( $form['token'] ); ?>">
		<?php wp_nonce_field( $form['nonce_action'], $form['names']['nonce'], false ); ?>
		<div class="hp-field" aria-hidden="true">
			<label for="<?php echo esc_attr( $prefix . 'hp' ); ?>"><?php esc_html_e( 'Bu alanı boş bırakın', 'mavibelge' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $prefix . 'hp' ); ?>" name="<?php echo esc_attr( $form['names']['honeypot'] ); ?>" value="" tabindex="-1" autocomplete="off">
		</div>

		<?php foreach ( $form['fields'] as $field ) : ?>
			<?php
			$name     = $field['name'];
			$id       = $prefix . $name;
			$has_err  = isset( $errors[ $name ] );
			$err_id   = $id . '-error';
			$value    = isset( $values[ $name ] ) ? $values[ $name ] : '';
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
		<?php endforeach; ?>

		<button type="submit" class="btn btn-primary"><?php echo esc_html( $form['submit_label'] ); ?></button>
	</form>
</div>
