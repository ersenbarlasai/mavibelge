<?php
/**
 * Activation hook.
 *
 * Faz 2 update: registers content types/taxonomies, flushes rewrite
 * rules once, and idempotently installs roles/capabilities and the
 * audit log table. No content, option, or role is ever deleted here —
 * only created if missing. No outbound network requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Activator {

	public static function activate() {
		MaviBelge_Core_Installer::activate();
	}
}
