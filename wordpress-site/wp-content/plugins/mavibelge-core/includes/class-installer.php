<?php
/**
 * Activation-time setup: registers content types/taxonomies so rewrite
 * rules can be flushed once, then installs roles and the audit table.
 * Everything here is idempotent and safe to call more than once.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Installer {

	public static function activate() {
		// Register types/taxonomies now (init may not have fired yet
		// during activation) so the rewrite flush below is meaningful.
		MaviBelge_Core_Content_Types::register_all();
		MaviBelge_Core_Taxonomies::register_all();

		flush_rewrite_rules();

		MaviBelge_Core_Roles::install();
		MaviBelge_Core_Audit_Log::install();
		MaviBelge_Core_Maintenance::schedule();
	}
}
