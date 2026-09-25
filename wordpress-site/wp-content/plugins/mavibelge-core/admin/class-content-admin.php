<?php
/**
 * Faz 7 — haber/doküman/referans/lokasyon/SSS yönetim listelerine:
 * "Görünürlük" (herkese açık mı, değilse NEDEN) ve "Yayın Hazırlığı"
 * (eksik zorunlu alanlar) sütunları, kapalı allowlist filtreleri ve
 * sayısal sıralama. Kurallar `MaviBelge_Core_Content_Admin_Rules`'tadır;
 * bu sınıf yalnız WordPress bağlantısıdır. Ham SQL yoktur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Content_Admin {

	public static function init() {
		foreach ( MaviBelge_Core_Content_Admin_Rules::POST_TYPES as $postType ) {
			add_filter( "manage_{$postType}_posts_columns", array( __CLASS__, 'add_columns' ) );
			add_action( "manage_{$postType}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
			add_filter( "manage_edit-{$postType}_sortable_columns", array( __CLASS__, 'sortable_columns' ) );
		}
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_to_query' ) );
	}

	public static function add_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new['mb_visibility'] = 'Görünürlük';
				$new['mb_readiness']  = 'Yayın Hazırlığı';
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new['mb_visibility'] ) ) {
			$new['mb_visibility'] = 'Görünürlük';
			$new['mb_readiness']  = 'Yayın Hazırlığı';
		}
		return $new;
	}

	public static function sortable_columns( $columns ) {
		$columns['mb_sort'] = 'mb_sort';
		return $columns;
	}

	public static function render_column( $column, $postId ) {
		$postType = get_post_type( $postId );
		if ( 'mb_visibility' === $column ) {
			$v = MaviBelge_Core_Content_Admin_Rules::visibility(
				$postType,
				get_post_status( $postId ),
				(string) get_post_meta( $postId, '_mb_approval_status', true ),
				(string) get_post_meta( $postId, '_mb_record_status', true )
			);
			printf( '<span class="%1$s">%2$s</span>', esc_attr( $v['public'] ? 'mb-visibility-public' : 'mb-visibility-hidden' ), esc_html( $v['label'] ) );
			return;
		}
		if ( 'mb_readiness' === $column ) {
			$problems = MaviBelge_Core_Publish_Readiness::check_readiness( $postType, $postId );
			if ( empty( $problems ) ) {
				echo esc_html( 'Hazır' );
				return;
			}
			echo '<ul style="margin:0;padding-left:1.1em;list-style:disc">';
			foreach ( $problems as $problem ) {
				printf( '<li>%s</li>', esc_html( (string) $problem ) );
			}
			echo '</ul>';
		}
	}

	public static function render_filters( $postType ) {
		foreach ( MaviBelge_Core_Content_Admin_Rules::filters_for( $postType ) as $key => $def ) {
			$current = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? MaviBelge_Core_Content_Admin_Rules::sanitize_filter( $postType, $key, wp_unslash( $_GET[ $key ] ) ) : '';
			printf( '<label class="screen-reader-text" for="%1$s">%2$s</label><select name="%1$s" id="%1$s">', esc_attr( $key ), esc_html( $def['label'] ) );
			printf( '<option value="">%s</option>', esc_html( $def['label'] ) );
			foreach ( $def['options'] as $value => $label ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $current, (string) $value, false ), esc_html( $label ) );
			}
			echo '</select>';
		}
		if ( 'mb_dokuman' === $postType && taxonomy_exists( 'mb_dokuman_kategori' ) ) {
			wp_dropdown_categories(
				array(
					'show_option_all' => 'Tüm Kategoriler',
					'taxonomy'        => 'mb_dokuman_kategori',
					'name'            => 'mb_dokuman_kategori',
					'value_field'     => 'slug',
					'selected'        => isset( $_GET['mb_dokuman_kategori'] ) && is_string( $_GET['mb_dokuman_kategori'] ) ? sanitize_title( wp_unslash( $_GET['mb_dokuman_kategori'] ) ) : '',
					'hide_empty'      => false,
				)
			);
		}
	}

	public static function apply_to_query( $query ) {
		if ( ! is_admin() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
			return;
		}
		$postType = $query->get( 'post_type' );
		if ( ! is_string( $postType ) || ! in_array( $postType, MaviBelge_Core_Content_Admin_Rules::POST_TYPES, true ) ) {
			return;
		}
		$meta = MaviBelge_Core_Content_Admin_Rules::build_meta_query( $postType, is_array( $_GET ) ? wp_unslash( $_GET ) : array(), current_time( 'Y-m-d' ) );
		if ( ! empty( $meta ) ) {
			$existing = $query->get( 'meta_query' );
			$query->set( 'meta_query', is_array( $existing ) && ! empty( $existing ) ? array( 'relation' => 'AND', $existing, $meta ) : $meta );
		}
		$sortKey = MaviBelge_Core_Content_Admin_Rules::sortable_meta_key( $query->get( 'orderby' ) );
		if ( null !== $sortKey ) {
			$query->set( 'meta_key', $sortKey );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}
}
