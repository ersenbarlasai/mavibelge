<?php
/**
 * Adds only the columns that are actually meaningful for each content
 * type's admin list table (görev kartı 02 §9 / brief §9: "yalnız ilgili
 * alanlar").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_List_Columns {

	public static function init() {
		foreach ( self::definitions() as $post_type => $columns ) {
			add_filter( "manage_{$post_type}_posts_columns", self::add_columns_callback( $columns ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		}
	}

	private static function definitions() {
		return array(
			'mb_yeterlilik' => array(
				'mb_myk_code' => 'MYK Kodu',
				'mb_level'    => 'Seviye',
				'mb_sector'   => 'Sektör',
				'mb_status'   => 'Durum',
			),
			'mb_ucret'      => array(
				'mb_profession'      => 'Meslek',
				'mb_ucret_myk_code'  => 'MYK Kodu',
				'mb_level'           => 'Seviye',
				'mb_sector_slug'     => 'Sektör',
				'mb_pricing_type'    => 'Fiyatlandırma Türü',
				'mb_period'          => 'Dönem',
				'mb_status'          => 'Durum',
				'mb_price_range'     => 'Fiyat Aralığı',
				'mb_qualification'   => 'Bağlı Yeterlilik',
			),
			'mb_haber'      => array(
				'mb_approval' => 'Onay Durumu',
			),
			'mb_dokuman'    => array(
				'mb_status' => 'Durum',
			),
			'mb_referans'   => array(
				'mb_reference_type' => 'Referans Türü',
				'mb_sort'           => 'Sıralama',
			),
			'mb_lokasyon'   => array(
				'mb_sort'   => 'Sıralama',
				'mb_status' => 'Durum',
			),
			'mb_sss'        => array(
				'mb_status' => 'Durum',
			),
		);
	}

	private static function add_columns_callback( $columns ) {
		return function ( $existing ) use ( $columns ) {
			// Insert new columns right before the date column when present.
			$new = array();
			$inserted = false;
			foreach ( $existing as $key => $label ) {
				if ( 'date' === $key && ! $inserted ) {
					foreach ( $columns as $col_key => $col_label ) {
						$new[ $col_key ] = $col_label;
					}
					$inserted = true;
				}
				$new[ $key ] = $label;
			}
			if ( ! $inserted ) {
				foreach ( $columns as $col_key => $col_label ) {
					$new[ $col_key ] = $col_label;
				}
			}
			return $new;
		};
	}

	public static function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'mb_myk_code':
				echo esc_html( (string) get_post_meta( $post_id, '_mb_myk_code', true ) );
				break;

			case 'mb_level':
				echo esc_html( (string) get_post_meta( $post_id, '_mb_level', true ) );
				break;

			case 'mb_status':
				echo esc_html( self::label_for_status( (string) get_post_meta( $post_id, '_mb_record_status', true ) ) );
				break;

			case 'mb_period':
				echo esc_html( (string) get_post_meta( $post_id, '_mb_tariff_period', true ) );
				break;

			case 'mb_sector':
				if ( taxonomy_exists( 'mb_sektor' ) ) {
					$terms = get_the_terms( $post_id, 'mb_sektor' );
					if ( is_array( $terms ) && ! empty( $terms ) ) {
						echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
						break;
					}
				}
				echo '—';
				break;

			case 'mb_profession':
				echo esc_html( (string) get_post_meta( $post_id, '_mb_profession_name', true ) );
				break;

			case 'mb_ucret_myk_code':
				$code = (string) get_post_meta( $post_id, '_mb_qualification_code', true );
				echo '' !== $code ? esc_html( $code ) : '—';
				break;

			case 'mb_sector_slug':
				$slug = (string) get_post_meta( $post_id, '_mb_sector_slug', true );
				echo '' !== $slug ? esc_html( $slug ) : '—';
				break;

			case 'mb_pricing_type':
				$map = array(
					'single'   => 'Tek fiyat',
					'unit'     => 'Birim bazlı',
					'package'  => 'Paket',
					'multiple' => 'Çok seçenekli',
				);
				$value = (string) get_post_meta( $post_id, '_mb_pricing_type', true );
				echo esc_html( isset( $map[ $value ] ) ? $map[ $value ] : '—' );
				break;

			case 'mb_qualification':
				$qualification_id = (int) get_post_meta( $post_id, '_mb_qualification_id', true );
				if ( $qualification_id > 0 && 'mb_yeterlilik' === get_post_type( $qualification_id ) ) {
					$title = get_the_title( $qualification_id );
					if ( current_user_can( 'edit_post', $qualification_id ) ) {
						printf(
							'<a href="%1$s">%2$s</a>',
							esc_url( (string) get_edit_post_link( $qualification_id ) ),
							esc_html( $title )
						);
					} else {
						echo esc_html( $title );
					}
					break;
				}
				echo '—';
				break;

			case 'mb_price_range':
				$min = (int) get_post_meta( $post_id, '_mb_min_amount_kurus', true );
				$max = (int) get_post_meta( $post_id, '_mb_max_amount_kurus', true );
				if ( 0 === $min && 0 === $max ) {
					echo '—';
				} elseif ( $min === $max ) {
					echo esc_html( self::format_kurus( $min ) );
				} else {
					echo esc_html( self::format_kurus( $min ) . ' – ' . self::format_kurus( $max ) );
				}
				break;

			case 'mb_approval':
				$map = array(
					'draft'     => 'Taslak',
					'in_review' => 'İncelemede',
					'approved'  => 'Onaylandı',
					'rejected'  => 'Reddedildi',
				);
				$value = (string) get_post_meta( $post_id, '_mb_approval_status', true );
				echo esc_html( isset( $map[ $value ] ) ? $map[ $value ] : '—' );
				break;

			case 'mb_reference_type':
				$value = (string) get_post_meta( $post_id, '_mb_reference_status', true );
				echo esc_html( 'real' === $value ? 'Gerçek' : 'Temsili' );
				break;

			case 'mb_sort':
				echo esc_html( (string) get_post_meta( $post_id, '_mb_sort_order', true ) );
				break;
		}
	}

	private static function label_for_status( $value ) {
		$map = array(
			'active'   => 'Aktif',
			'passive'  => 'Pasif',
			'draft'    => 'Taslak',
			'archived' => 'Arşivlendi',
		);
		return isset( $map[ $value ] ) ? $map[ $value ] : '—';
	}

	private static function format_kurus( $kurus ) {
		return number_format( $kurus / 100, 2, ',', '.' ) . ' TL';
	}
}
