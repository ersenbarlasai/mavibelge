<?php
/**
 * Faz 6B3 — `MaviBelge_Core_Import_Audit_Sink`'in mevcut
 * `MaviBelge_Core_Audit_Log` üzerine uygulaması.
 *
 * Yalnız EVENTS listesindeki import olayları yazılabilir; context önce kapalı izin listesinden
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
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_FEES_ACTIVATED,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_FEES_DEACTIVATED,
	);

	/** Faz 12: yayın olayı bir import run'ına bağlı değildir. */
	const PUBLISH_OBJECT_TYPE = 'mb_import_pages';

	/** Faz 15: ücret yayını olayları da run'a bağlı değildir. */
	const FEE_PUBLISH_OBJECT_TYPE = 'mb_import_fees';

	/** Run'a bağlı OLMAYAN (runId = 0) olay => nesne türü. */
	const RUNLESS_EVENTS = array(
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_PAGES_PUBLISHED  => self::PUBLISH_OBJECT_TYPE,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_FEES_ACTIVATED   => self::FEE_PUBLISH_OBJECT_TYPE,
		MaviBelge_Core_Audit_Log::EVENT_IMPORT_FEES_DEACTIVATED => self::FEE_PUBLISH_OBJECT_TYPE,
	);

	public function ready() {
		return MaviBelge_Core_Audit_Log::table_exists();
	}

	public function record( $event, $runId, array $context ) {
		$isRunless = is_string( $event ) && array_key_exists( $event, self::RUNLESS_EVENTS );
		if ( ! in_array( $event, self::EVENTS, true ) || ! is_int( $runId ) || ( $isRunless ? 0 !== $runId : $runId <= 0 ) ) {
			return false;
		}
		$safe = MaviBelge_Core_Import_Audit_Context::build( $context );
		if ( null === $safe ) {
			return false;
		}
		return true === MaviBelge_Core_Audit_Log::record( $event, $isRunless ? self::RUNLESS_EVENTS[ $event ] : self::OBJECT_TYPE, $runId, $safe );
	}
}
