<?php
/**
 * Faz 6B3 — `MaviBelge_Core_Import_Audit_Sink`'in mevcut
 * `MaviBelge_Core_Audit_Log` üzerine uygulaması.
 *
 * Yalnız yedi import olayı yazılabilir; context önce kapalı izin listesinden
 * (`MaviBelge_Core_Import_Audit_Context::build()`) geçer, geçmezse HİÇ
 * yazılmaz ve `false` döner (apply/rollback bunu başarısızlık sayar).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_WP_Audit_Sink implements MaviBelge_Core_Import_Audit_Sink {

	const OBJECT_TYPE = 'mb_import_run';

	const EVENTS = array(
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_STARTED,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_BATCH_COMMITTED,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_COMPLETED,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_RUN_FAILED,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_STARTED,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_COMPLETED,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_ROLLBACK_FAILED,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_PAGES_PUBLISHED,
	);

	/** Faz 12: yayın olayı bir import run'ına bağlı değildir. */
	const PUBLISH_OBJECT_TYPE = 'mb_import_pages';

	public function ready() {
		return MaviBelge_Core_Audit_Log::table_exists();
	}

	public function record( $event, $runId, array $context ) {
		$isPublish = MaviBelge_Core_Audit_Log::EVENT_IMPORT_PAGES_PUBLISHED === $event;
		if ( ! in_array( $event, self::EVENTS, true ) || ! is_int( $runId ) || ( $isPublish ? 0 !== $runId : $runId <= 0 ) ) {
			return false;
		}
		$safe = MaviBelge_Core_Import_Audit_Context::build( $context );
		if ( null === $safe ) {
			return false;
		}
		return true === MaviBelge_Core_Audit_Log::record( $event, $isPublish ? self::PUBLISH_OBJECT_TYPE : self::OBJECT_TYPE, $runId, $safe );
	}
}
