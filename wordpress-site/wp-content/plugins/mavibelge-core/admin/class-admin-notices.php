<?php
/**
 * Queues Turkish admin notices across a redirect (e.g. after a post
 * save) using a short-lived, per-user transient.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Admin_Notices {

	const TRANSIENT_PREFIX = 'mavibelge_core_notices_';

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	public static function queue( $message, $type = 'warning' ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$key      = self::TRANSIENT_PREFIX . $user_id;
		$existing = get_transient( $key );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$existing[] = array(
			'message' => $message,
			'type'    => in_array( $type, array( 'error', 'warning', 'success', 'info' ), true ) ? $type : 'warning',
		);
		set_transient( $key, $existing, 60 );
	}

	public static function render() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$key      = self::TRANSIENT_PREFIX . $user_id;
		$messages = get_transient( $key );
		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return;
		}
		delete_transient( $key );

		foreach ( $messages as $entry ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $entry['type'] ),
				esc_html( $entry['message'] )
			);
		}
	}
}
