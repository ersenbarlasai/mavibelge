<?php
/**
 * Generic, schema-driven meta box for every mavibelge-core content
 * type. Rendering and saving both read from
 * MaviBelge_Core_Meta_Schema::get_schema() so a field only needs to be
 * defined once.
 *
 * Save order (görev kartı 02 / brief §9 — zorunlu güvenlik sırası):
 * 1) autosave/revision check
 * 2) nonce check
 * 3) capability check (per post type's own edit_post meta cap)
 * 4) field-by-field sanitize + validate
 * 5) on invalid data: keep the previously stored value and queue a
 *    visible Turkish admin notice, never write a broken value silently.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Meta_Boxes {

	const NONCE_ACTION = 'mavibelge_core_save_meta';
	const NONCE_NAME   = 'mavibelge_core_meta_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		foreach ( array_keys( MaviBelge_Core_Meta_Schema::get_schema() ) as $post_type ) {
			if ( ! MaviBelge_Core_Meta_Schema::has_editable_fields( $post_type ) ) {
				continue; // Faz 12: çekirdek `page` yalnız sistem alanı taşır; meta kutusu/kaydetme kancası eklenmez.
			}
			add_action( "save_post_{$post_type}", array( __CLASS__, 'save' ), 10, 2 );
		}
	}

	public static function register() {
		foreach ( MaviBelge_Core_Meta_Schema::get_schema() as $post_type => $fields ) {
			if ( ! MaviBelge_Core_Meta_Schema::has_editable_fields( $post_type ) ) {
				continue;
			}
			add_meta_box(
				'mavibelge_core_fields',
				'Mavi Belge Alanları',
				array( __CLASS__, 'render' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public static function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$fields = MaviBelge_Core_Meta_Schema::get_fields_for( $post->post_type );

		echo '<table class="form-table mavibelge-core-meta-table"><tbody>';
		foreach ( $fields as $key => $config ) {
			self::render_field( $post, $key, $config );
		}
		echo '</tbody></table>';
	}

	private static function render_field( $post, $key, $config ) {
		$value    = get_post_meta( $post->ID, $key, true );
		$type     = $config['type'];
		$label    = esc_html( $config['label'] );
		$id       = esc_attr( $key );
		$readonly = ! empty( $config['readonly'] );

		echo '<tr><th scope="row"><label for="' . $id . '">' . $label . '</label></th><td>';

		// System-managed fields (e.g. haber reviewer/reviewed-at) are
		// never an editable control at all — showing a disabled-looking
		// ID/date box would be misleading since the server ignores any
		// value submitted for them regardless. Plain, human-readable text.
		if ( ! empty( $config['system_managed'] ) ) {
			echo '<p>' . esc_html( self::format_system_managed_value( $key, $value ) ) . '</p>';
			if ( ! empty( $config['description'] ) ) {
				echo '<p class="description">' . esc_html( $config['description'] ) . '</p>';
			}
			echo '</td></tr>';
			return;
		}

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%1$s" rows="3" class="large-text"%3$s>%2$s</textarea>',
					$id,
					esc_textarea( (string) $value ),
					$readonly ? ' readonly' : ''
				);
				break;

			case 'string_list':
			case 'phone_list':
			case 'id_list':
				$lines = is_array( $value ) ? implode( "\n", $value ) : '';
				printf(
					'<textarea id="%1$s" name="%1$s" rows="3" class="large-text" placeholder="Her satıra bir değer">%2$s</textarea>',
					$id,
					esc_textarea( $lines )
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%1$s" name="%1$s" value="%2$s" class="regular-text"%3$s>',
					$id,
					esc_attr( (string) $value ),
					$readonly ? ' readonly' : ''
				);
				break;

			case 'date':
				printf(
					'<input type="date" id="%1$s" name="%1$s" value="%2$s"%3$s>',
					$id,
					esc_attr( (string) $value ),
					$readonly ? ' readonly' : ''
				);
				break;

			case 'datetime':
				$html_value = $value ? str_replace( ' ', 'T', substr( (string) $value, 0, 16 ) ) : '';
				printf(
					'<input type="datetime-local" id="%1$s" name="%1$s" value="%2$s"%3$s>',
					$id,
					esc_attr( $html_value ),
					$readonly ? ' readonly' : ''
				);
				break;

			case 'integer':
			case 'id':
			case 'user_id':
				printf(
					'<input type="number" step="1" min="%4$s" id="%1$s" name="%1$s" value="%2$s" class="small-text"%3$s>',
					$id,
					esc_attr( (string) $value ),
					$readonly ? ' readonly' : '',
					isset( $config['min'] ) ? esc_attr( $config['min'] ) : '0'
				);
				break;

			case 'money_kurus':
				// Faz 6B3 Önkoşul: saklanan değer kanonik kuruştur; insan
				// her zaman TL görür/girer (admin_input_to_storage() kaydederken çevirir).
				$display = ( '' !== (string) $value && 0 !== (int) $value )
					? MaviBelge_Core_Validator::kurus_to_lira_display( $value )
					: '';
				printf(
					'<input type="text" inputmode="decimal" placeholder="1.500,00" id="%1$s" name="%1$s" value="%2$s" class="regular-text"%3$s>',
					$id,
					esc_attr( $display ),
					$readonly ? ' readonly' : ''
				);
				break;

			case 'select':
				$restricted        = ! empty( $config['restricted_option_caps'] ) ? $config['restricted_option_caps'] : array();
				$any_restricted_off = false;
				echo '<select id="' . $id . '" name="' . $id . '">';
				echo '<option value="">—</option>';
				foreach ( $config['options'] as $opt_value => $opt_label ) {
					$option_disabled = '';
					if ( isset( $restricted[ $opt_value ] ) && ! current_user_can( $restricted[ $opt_value ] ) ) {
						$option_disabled    = ' disabled';
						$any_restricted_off = true;
					}
					printf(
						'<option value="%1$s"%3$s%4$s>%2$s</option>',
						esc_attr( $opt_value ),
						esc_html( $opt_label ),
						selected( (string) $value, (string) $opt_value, false ),
						$option_disabled
					);
				}
				echo '</select>';
				if ( $any_restricted_off ) {
					echo '<p class="description">Bazı seçenekler bu kullanıcı için devre dışıdır (yayımlama/onay yetkisi gerektirir).</p>';
				}
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s> Evet</label>',
					$id,
					checked( (bool) $value, true, false )
				);
				break;

			case 'price_options':
				self::render_price_options( $post, $value );
				break;

			default: // text
				printf(
					'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text"%3$s>',
					$id,
					esc_attr( (string) $value ),
					$readonly ? ' readonly' : ''
				);
				break;
		}

		if ( ! empty( $config['description'] ) ) {
			echo '<p class="description">' . esc_html( $config['description'] ) . '</p>';
		}

		echo '</td></tr>';
	}

	/**
	 * Renders existing rows plus a fixed number of empty filler rows so
	 * the field works without JavaScript (existing rows stay editable
	 * and one can still add a bounded number of new options by filling
	 * a blank row). meta-boxes.js progressively enhances this with
	 * add/remove buttons when JS is available.
	 */
	private static function render_price_options( $post, $value ) {
		$rows = is_array( $value ) ? $value : array();
		$empty_filler_rows = 3;

		echo '<div class="mavibelge-core-price-options" data-field="_mb_price_options">';
		echo '<table class="widefat mavibelge-core-price-options-table"><thead><tr>';
		echo '<th>Etiket</th><th>Birimler (virgülle ayrılmış)</th><th>Tutar (TL)</th><th>Sıra</th><th></th>';
		echo '</tr></thead><tbody>';

		$index = 0;
		foreach ( $rows as $row ) {
			self::render_price_option_row( $index, $row );
			$index++;
		}
		for ( $i = 0; $i < $empty_filler_rows; $i++ ) {
			self::render_price_option_row( $index, array() );
			$index++;
		}

		echo '</tbody></table>';
		echo '<p class="description">Tutarı TL olarak girin (örn. 17.000 veya 17.000,00). Boş satırlar yok sayılır. Herhangi bir dolu satır geçersizse hiçbiri kaydedilmez ve önceki liste korunur. JavaScript kapalıysa yeni seçenek eklemek için boş bir satırı doldurun.</p>';
		echo '<button type="button" class="button mavibelge-core-add-price-row" style="display:none;">+ Seçenek Ekle</button>';
		echo '</div>';
	}

	private static function render_price_option_row( $index, $row ) {
		$label      = isset( $row['label'] ) ? $row['label'] : '';
		$units      = isset( $row['units'] ) && is_array( $row['units'] ) ? implode( ', ', $row['units'] ) : '';
		$amount_try = isset( $row['amount_kurus'] ) && '' !== $row['amount_kurus']
			? MaviBelge_Core_Validator::kurus_to_lira_display( $row['amount_kurus'] )
			: '';
		$sort_order = isset( $row['sort_order'] ) ? $row['sort_order'] : $index;

		printf(
			'<tr class="mavibelge-core-price-row">
				<td><input type="text" name="_mb_price_options[%1$d][label]" value="%2$s" class="regular-text"></td>
				<td><input type="text" name="_mb_price_options[%1$d][units]" value="%3$s" class="regular-text"></td>
				<td><input type="text" inputmode="decimal" placeholder="17.000,00" name="_mb_price_options[%1$d][amount_try]" value="%4$s" class="small-text"></td>
				<td><input type="number" step="1" min="0" name="_mb_price_options[%1$d][sort_order]" value="%5$s" class="small-text"></td>
				<td><button type="button" class="button-link mavibelge-core-remove-price-row" style="display:none;">Kaldır</button></td>
			</tr>',
			$index,
			esc_attr( $label ),
			esc_attr( $units ),
			esc_attr( $amount_try ),
			esc_attr( (string) $sort_order )
		);
	}

	private static function format_system_managed_value( $key, $value ) {
		if ( '_mb_reviewer_user_id' === $key ) {
			$user_id = (int) $value;
			if ( $user_id <= 0 ) {
				return 'Henüz incelenmedi.';
			}
			$user = get_userdata( $user_id );
			return $user ? sprintf( '%s (ID %d)', $user->display_name, $user_id ) : sprintf( 'Kullanıcı ID %d (silinmiş olabilir)', $user_id );
		}
		if ( '_mb_reviewed_at' === $key ) {
			return $value ? $value . ' UTC' : 'Henüz incelenmedi.';
		}
		if ( '_mb_min_amount_kurus' === $key || '_mb_max_amount_kurus' === $key ) {
			// Stored as kuruş; always displayed as TL (Faz2 ikinci
			// düzeltme brief §7 — never a raw kuruş integer on screen).
			return '' === (string) $value ? '—' : MaviBelge_Core_Validator::kurus_to_lira_display( $value ) . ' TL';
		}
		return (string) $value;
	}

	public static function save( $post_id, $post ) {
		// 1) Autosave/revision.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// 2) Nonce.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( wp_unslash( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}

		// 3) Capability.
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
			return;
		}

		$fields = MaviBelge_Core_Meta_Schema::get_fields_for( $post->post_type );
		if ( empty( $fields ) ) {
			return;
		}

		$old_status   = get_post_meta( $post_id, '_mb_record_status', true );
		$old_approval = get_post_meta( $post_id, '_mb_approval_status', true );

		$critical_fields = self::critical_fields_for( $post->post_type );
		$old_critical     = array();
		foreach ( $critical_fields as $field => $sentinel ) {
			$old_critical[ $field ] = get_post_meta( $post_id, $field, true );
		}

		// 4) Sanitize + validate every simple field (price_options and
		// system-managed fields are handled separately/never here).
		foreach ( $fields as $key => $config ) {
			if ( ! empty( $config['readonly'] ) || 'price_options' === $config['type'] ) {
				continue;
			}

			$has_value = array_key_exists( $key, $_POST );
			$raw       = $has_value ? wp_unslash( $_POST[ $key ] ) : ( 'checkbox' === $config['type'] ? false : null );

			if ( ! $has_value && 'checkbox' !== $config['type'] ) {
				continue; // Field not present in this submit; leave stored value untouched.
			}

			// Faz 6B3 Önkoşul: insan girişi (ör. TL) önce saklanan kanonik
			// temsile çevrilir, SONRA doğrulanır — kuruş hiçbir yolda iki kez
			// çarpılmaz.
			list( $raw, $error ) = MaviBelge_Core_Field_Repository::admin_input_to_storage( $config, $raw );
			$clean = null;
			if ( null === $error ) {
				list( $clean, $error ) = MaviBelge_Core_Field_Repository::sanitize_and_validate( $config, $raw );
			}

			if ( null !== $error ) {
				MaviBelge_Core_Admin_Notices::queue( $config['label'] . ': ' . $error . ' Önceki değer korundu.', 'warning' );
				continue;
			}

			update_post_meta( $post_id, $key, $clean );
		}

		if ( isset( $fields['_mb_price_options'] ) ) {
			self::save_price_options( $post_id );
		}

		if ( 'mb_yeterlilik' === $post->post_type ) {
			self::enforce_myk_uniqueness( $post_id );
		}

		if ( 'mb_ucret' === $post->post_type ) {
			self::guard_ucret_record_status( $post_id, $old_status );
		}

		if ( 'mb_haber' === $post->post_type ) {
			self::guard_haber_approval_status( $post_id, $old_approval );
		}

		self::audit_status_changes( $post_id, $post->post_type, $old_status, $old_approval );
		self::audit_critical_field_changes( $post_id, $post->post_type, $critical_fields, $old_critical );
	}

	/**
	 * post_type => (meta_key => "empty" sentinel). Only fields explicitly
	 * called out in the Faz2 düzeltme brief §5 (ilişki, tarife dönemi,
	 * fiyatlandırma türü, kaynak, referans gerçek/temsili durumu, doküman
	 * eki) are watched here; record_status/approval_status are handled
	 * separately by audit_status_changes() (existing, narrower logic).
	 * The sentinel is what "not set yet" looks like for that field, so a
	 * first-time assignment is never logged as a "change".
	 */
	private static function critical_fields_for( $post_type ) {
		switch ( $post_type ) {
			case 'mb_ucret':
				return array(
					'_mb_qualification_id' => 0,
					'_mb_tariff_period'    => '',
					'_mb_pricing_type'     => '',
					'_mb_source_name'      => '',
				);
			case 'mb_referans':
				return array( '_mb_reference_status' => '' );
			case 'mb_dokuman':
				return array( '_mb_attachment_id' => 0 );
			default:
				return array();
		}
	}

	private static function audit_critical_field_changes( $post_id, $post_type, array $critical_fields, array $old_values ) {
		foreach ( $critical_fields as $field => $sentinel ) {
			$old_value = $old_values[ $field ];
			if ( $old_value === $sentinel ) {
				continue; // First-time set, not a "change".
			}
			$new_value = get_post_meta( $post_id, $field, true );
			if ( $new_value === $old_value ) {
				continue;
			}
			MaviBelge_Core_Audit_Log::record(
				MaviBelge_Core_Audit_Log::EVENT_CONTENT_META_CHANGED,
				$post_type,
				$post_id,
				array(
					'field' => $field,
					'from'  => $old_value,
					'to'    => $new_value,
				)
			);
		}
	}

	/**
	 * The generic field loop above already wrote whatever
	 * _mb_record_status value was submitted (that field isn't marked
	 * readonly — ordinary editors may set 'draft'). This guard runs
	 * right after and reverts the write if the request tried to move
	 * into active/archived without publish_mb_ucretler — e.g.
	 * mb_price_editor POSTing the field directly, bypassing the
	 * disabled <option> in the UI, must not succeed server-side.
	 */
	private static function guard_ucret_record_status( $post_id, $old_status ) {
		if ( ! isset( $_POST['_mb_record_status'] ) ) {
			return;
		}
		$requested = sanitize_text_field( wp_unslash( $_POST['_mb_record_status'] ) );
		if ( ! in_array( $requested, array( 'active', 'archived' ), true ) ) {
			return;
		}
		if ( $requested === $old_status ) {
			return; // Already in that state; not a new transition.
		}
		if ( current_user_can( 'publish_mb_ucretler' ) ) {
			return;
		}
		update_post_meta( $post_id, '_mb_record_status', $old_status );
		MaviBelge_Core_Admin_Notices::queue(
			'Kayıt Durumu: ücret kaydını aktif veya arşivlenmiş yapma yetkiniz yok. Önceki değer korundu.',
			'error'
		);
	}

	/**
	 * Same revert-if-unauthorized pattern as above for haber approval.
	 * On an AUTHORIZED transition into approved/rejected, this is also
	 * the only place _mb_reviewer_user_id / _mb_reviewed_at are ever
	 * written — always the current user and current UTC time, never
	 * from POST (those two fields are readonly/system_managed and are
	 * skipped entirely by the generic field loop).
	 */
	private static function guard_haber_approval_status( $post_id, $old_approval ) {
		if ( ! isset( $_POST['_mb_approval_status'] ) ) {
			return;
		}
		$requested = sanitize_text_field( wp_unslash( $_POST['_mb_approval_status'] ) );
		if ( ! in_array( $requested, array( 'approved', 'rejected' ), true ) ) {
			return; // 'draft'/'in_review' are open to anyone who can edit_post (already checked).
		}
		if ( $requested === $old_approval ) {
			return; // No transition attempted; do not re-stamp reviewer/time on every save.
		}
		if ( ! current_user_can( 'publish_mb_haberler' ) ) {
			update_post_meta( $post_id, '_mb_approval_status', $old_approval );
			MaviBelge_Core_Admin_Notices::queue(
				'Onay Durumu: haberi onaylama/reddetme yetkiniz yok. Önceki değer korundu.',
				'error'
			);
			return;
		}
		update_post_meta( $post_id, '_mb_reviewer_user_id', get_current_user_id() );
		update_post_meta( $post_id, '_mb_reviewed_at', current_time( 'mysql', true ) );
	}

	/**
	 * Field-by-field sanitize+validate now lives in
	 * MaviBelge_Core_Field_Repository::sanitize_and_validate() — shared
	 * with includes/class-publish-readiness.php so both the real save
	 * path and the pre-publish readiness check apply the exact same
	 * rules (Faz2 ikinci düzeltme brief §2/§4).
	 *
	 * Price-options parsing itself now lives in the single shared
	 * MaviBelge_Core_Validator::evaluate_admin_price_rows() — also used
	 * by includes/class-publish-readiness.php — instead of a second,
	 * separately-maintained copy of the same parsing logic. The whole
	 * save is atomic: if any non-blank submitted row is invalid, NOTHING
	 * is written; the previously stored options/min/max are kept exactly
	 * as they were (Faz2 düzeltme brief §3.3).
	 */
	private static function save_price_options( $post_id ) {
		$raw = isset( $_POST['_mb_price_options'] ) ? wp_unslash( $_POST['_mb_price_options'] ) : array();

		$eval = MaviBelge_Core_Validator::evaluate_admin_price_rows( $raw );

		if ( ! $eval['replace'] ) {
			foreach ( $eval['errors'] as $error ) {
				MaviBelge_Core_Admin_Notices::queue( 'Fiyat Seçenekleri: ' . $error, 'warning' );
			}
			MaviBelge_Core_Admin_Notices::queue(
				'Fiyat seçeneklerinde hata bulunduğu için hiçbir değişiklik kaydedilmedi; önceki liste korundu.',
				'error'
			);
			return;
		}

		$old_options = get_post_meta( $post_id, '_mb_price_options', true );

		update_post_meta( $post_id, '_mb_price_options', $eval['options'] );
		update_post_meta( $post_id, '_mb_min_amount_kurus', $eval['min_kurus'] );
		update_post_meta( $post_id, '_mb_max_amount_kurus', $eval['max_kurus'] );

		if ( wp_json_encode( $old_options ) !== wp_json_encode( $eval['options'] ) ) {
			MaviBelge_Core_Audit_Log::record(
				MaviBelge_Core_Audit_Log::EVENT_PRICE_OPTION_CHANGED,
				'mb_ucret',
				$post_id,
				array(
					'option_count' => count( $eval['options'] ),
					'min_kurus'    => $eval['min_kurus'],
					'max_kurus'    => $eval['max_kurus'],
				)
			);
		}
	}

	private static function enforce_myk_uniqueness( $post_id ) {
		$code     = get_post_meta( $post_id, '_mb_myk_code', true );
		$level    = get_post_meta( $post_id, '_mb_level', true );
		$revision = get_post_meta( $post_id, '_mb_revision', true );

		// Faz 6A Güvenlik/Şema/Sözleşme Kapanışı Bulgu #1: kod DOLU ama
		// içine gömülü seviye/revizyon _mb_level/_mb_revision ile
		// UYUŞMUYORSA da aynı fail-closed davranış — kodu boşalt, Türkçe
		// uyarı göster. Paylaşılan tek kural:
		// MaviBelge_Core_Validator::myk_code_matches_level_revision() —
		// bu, class-publish-readiness.php::check_yeterlilik()'in de
		// kullandığı AYNI metod, ikinci bir regex kopyası yok.
		if ( '' !== trim( (string) $code ) && ! MaviBelge_Core_Validator::myk_code_matches_level_revision( $code, $level, $revision ) ) {
			update_post_meta( $post_id, '_mb_myk_code', '' );
			MaviBelge_Core_Admin_Notices::queue(
				sprintf(
					'"%s" MYK koduna gömülü seviye/revizyon, bu kayıttaki Seviye/Revizyon alanlarıyla uyuşmuyor. Tutarsızlığı önlemek için MYK kodu bu kayıtta boşaltıldı; lütfen kontrol edip düzeltin.',
					$code
				),
				'error'
			);
			return;
		}

		if ( MaviBelge_Core_Validator::is_myk_combination_unique( $post_id, $code, $level, $revision ) ) {
			return;
		}

		// Safe fallback: clear only the offending code so the record no
		// longer collides, rather than silently keeping a duplicate.
		update_post_meta( $post_id, '_mb_myk_code', '' );
		MaviBelge_Core_Admin_Notices::queue(
			sprintf(
				'"%s" MYK kodu, aynı seviye ve revizyonla başka bir yeterlilik kaydında zaten kullanılıyor. Çakışmayı önlemek için MYK kodu bu kayıtta boşaltıldı; lütfen kontrol edip düzeltin.',
				$code
			),
			'error'
		);
	}

	private static function audit_status_changes( $post_id, $post_type, $old_status, $old_approval ) {
		$new_status = get_post_meta( $post_id, '_mb_record_status', true );
		if ( '' !== $old_status && $new_status !== $old_status ) {
			MaviBelge_Core_Audit_Log::record(
				MaviBelge_Core_Audit_Log::EVENT_CONTENT_META_CHANGED,
				$post_type,
				$post_id,
				array(
					'field' => '_mb_record_status',
					'from'  => $old_status,
					'to'    => $new_status,
				)
			);
		}

		if ( 'mb_haber' === $post_type ) {
			$new_approval = get_post_meta( $post_id, '_mb_approval_status', true );
			if ( '' !== $old_approval && $new_approval !== $old_approval ) {
				MaviBelge_Core_Audit_Log::record(
					MaviBelge_Core_Audit_Log::EVENT_APPROVAL_STATUS_CHANGED,
					$post_type,
					$post_id,
					array(
						'from' => $old_approval,
						'to'   => $new_approval,
					)
				);
			}
		}
	}
}
