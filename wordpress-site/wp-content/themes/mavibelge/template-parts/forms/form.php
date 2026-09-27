<?php
/**
 * Gerçek form (yalnız kapı AÇIKKEN çağrılır). JavaScript GEREKTİRMEZ: sunucu tarafı doğrulama, `required`/`type`
 * öznitelikleri tarayıcı yardımıdır. Hatalarda aynı yanıtta yeniden çizim: hata özeti (role="alert") sayfa
 * başında, alan hataları alanın altında (`aria-invalid` + `aria-describedby`). Hassas alanlar (T.C. kimlik no)
 * ASLA geri doldurulmaz. Bal küpü alanı görünmez ve klavyeyle odaklanamaz. Onay kutusunun metni yapılandırmadaki
 * kurumca ONAYLI metindir (kodda uydurma metin yok).
 *
 * Faz 12f: isteğe bağlı `steps` ile alanlar adımlara (fieldset + legend/H2) gruplanır. JS YOKKEN bütün adımlar görünür,
 * adım göstergesi ve ileri/geri düğmeleri gizlidir (`hidden`), gönder düğmesi her zaman erişilebilirdir; JS
 * (assets/src/js/application.js) göstergeyi gerçek düğmelere, adımları tek tek görünüme çevirir. Alan işaretlemesi
 * template-parts/forms/field.php'dedir (değişmedi).
 *
 * $args:
 * - form (array, zorunlu) MaviBelge_Core_Forms_Service::describe() DTO'su (open === true)
 * - action_url (string) formun gönderileceği URL (varsayılan: geçerli sayfa)
 * - steps (array) isteğe bağlı: array( array( 'title' => string, 'fields' => string[] ), ... )
 * - rows (array) isteğe bağlı (Faz 12g, adımsız formlar): yan yana çizilecek alan grupları, ör. array( array( 'full_name', 'phone' ) )
 *   — grup ilk alanının yerinde .form-row içinde çizilir (masaüstü 2 sütun, mobil 1 sütun); alan işaretlemesi değişmez.
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
$steps  = isset( $args['steps'] ) && is_array( $args['steps'] ) ? $args['steps'] : array();
$rows   = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
$row_of = array(); // alan adı => satır dizini
foreach ( $rows as $row_index => $row ) {
	foreach ( is_array( $row ) ? $row : array() as $row_field ) {
		$row_of[ (string) $row_field ] = $row_index;
	}
}
$multipart = false;
$by_name   = array();
foreach ( $form['fields'] as $field ) {
	if ( 'file' === $field['type'] ) {
		$multipart = true;
	}
	$by_name[ $field['name'] ] = $field;
}
$prefix = 'mbf-' . $form['id'] . '-';

// Adımlar yalnız var olan alanları taşır; hiçbir adımda olmayan alan adımlardan sonra çizilir (alan KAYBOLMAZ).
$groups = array();
$placed = array();
foreach ( $steps as $step ) {
	$names = array();
	foreach ( isset( $step['fields'] ) && is_array( $step['fields'] ) ? $step['fields'] : array() as $step_field ) {
		if ( isset( $by_name[ $step_field ] ) && ! isset( $placed[ $step_field ] ) ) {
			$names[]               = $step_field;
			$placed[ $step_field ] = true;
		}
	}
	if ( ! empty( $names ) ) {
		$groups[] = array( 'title' => (string) $step['title'], 'fields' => $names );
	}
}
$rest = array();
foreach ( $form['fields'] as $field ) {
	if ( ! isset( $placed[ $field['name'] ] ) ) {
		$rest[] = $field;
	}
}
$stepped = count( $groups ) > 1;
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
					$label = isset( $by_name[ $field_name ] ) ? $by_name[ $field_name ]['label'] : '';
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

	<?php if ( $stepped ) : ?>
		<ol class="step-indicator" data-step-indicator hidden>
			<?php foreach ( $groups as $index => $group ) : ?>
				<li<?php echo 0 === $index ? ' class="is-active"' : ''; ?>><button type="button" class="step-tab" data-step-goto="<?php echo (int) $index; ?>"<?php echo 0 === $index ? ' aria-current="step"' : ''; ?>><?php echo esc_html( $group['title'] ); ?></button></li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $action ); ?>"<?php echo $multipart ? ' enctype="multipart/form-data"' : ''; ?> novalidate<?php echo $stepped ? ' data-step-form' : ''; ?>>
		<input type="hidden" name="<?php echo esc_attr( $form['names']['form'] ); ?>" value="<?php echo esc_attr( $form['id'] ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $form['names']['token'] ); ?>" value="<?php echo esc_attr( $form['token'] ); ?>">
		<?php wp_nonce_field( $form['nonce_action'], $form['names']['nonce'], false ); ?>
		<div class="hp-field" aria-hidden="true">
			<label for="<?php echo esc_attr( $prefix . 'hp' ); ?>"><?php esc_html_e( 'Bu alanı boş bırakın', 'mavibelge' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $prefix . 'hp' ); ?>" name="<?php echo esc_attr( $form['names']['honeypot'] ); ?>" value="" tabindex="-1" autocomplete="off">
		</div>

		<?php if ( $stepped ) : ?>
			<?php $last = count( $groups ) - 1; ?>
			<?php foreach ( $groups as $index => $group ) : ?>
				<?php $title_id = $prefix . 'step-' . (int) $index; ?>
				<fieldset class="form-step" data-step="<?php echo (int) $index; ?>">
					<legend class="form-step-legend"><h2 class="form-step-title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $group['title'] ); ?></h2></legend>
					<?php foreach ( $group['fields'] as $field_name ) : ?>
						<?php get_template_part( 'template-parts/forms/field', null, array( 'field' => $by_name[ $field_name ], 'form' => $form ) ); ?>
					<?php endforeach; ?>
					<?php if ( $index < $last ) : ?>
						<div class="step-actions" data-step-actions hidden>
							<?php if ( $index > 0 ) : ?>
								<button type="button" class="btn btn-secondary" data-step-prev><?php esc_html_e( '← Geri', 'mavibelge' ); ?></button>
							<?php else : ?>
								<span></span>
							<?php endif; ?>
							<button type="button" class="btn btn-primary" data-step-next><?php esc_html_e( 'Devam Et →', 'mavibelge' ); ?></button>
						</div>
					<?php else : ?>
						<div class="step-actions" data-step-actions hidden>
							<button type="button" class="btn btn-secondary" data-step-prev><?php esc_html_e( '← Geri', 'mavibelge' ); ?></button>
							<span></span>
						</div>
					<?php endif; ?>
				</fieldset>
			<?php endforeach; ?>
			<?php foreach ( $rest as $field ) : ?>
				<?php get_template_part( 'template-parts/forms/field', null, array( 'field' => $field, 'form' => $form ) ); ?>
			<?php endforeach; ?>
			<div class="form-submit" data-step-submit>
				<button type="submit" class="btn btn-primary"><?php echo esc_html( $form['submit_label'] ); ?></button>
			</div>
		<?php else : ?>
			<?php
			$rendered_rows = array();
			foreach ( $form['fields'] as $field ) :
				if ( isset( $row_of[ $field['name'] ] ) ) {
					$row_index = $row_of[ $field['name'] ];
					if ( isset( $rendered_rows[ $row_index ] ) ) {
						continue;
					}
					$rendered_rows[ $row_index ] = true;
					echo '<div class="form-row">';
					foreach ( $form['fields'] as $row_field ) {
						if ( isset( $row_of[ $row_field['name'] ] ) && $row_of[ $row_field['name'] ] === $row_index ) {
							get_template_part( 'template-parts/forms/field', null, array( 'field' => $row_field, 'form' => $form ) );
						}
					}
					echo '</div>';
					continue;
				}
				get_template_part( 'template-parts/forms/field', null, array( 'field' => $field, 'form' => $form ) );
			endforeach;
			?>

			<button type="submit" class="btn btn-primary"><?php echo esc_html( $form['submit_label'] ); ?></button>
		<?php endif; ?>
	</form>
</div>
