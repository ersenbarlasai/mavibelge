<?php
/**
 * Deactivation hook. Only a safe rewrite-rule flush is allowed here.
 * Content, roles, capabilities, options and the audit table are never
 * touched on deactivation (görev kartı 02 / karar-kaydi: veri kaybolmaz).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
		MaviBelge_Core_Maintenance::unschedule(); // yalnız zamanlanmış kanca; veri silinmez
	}
}
