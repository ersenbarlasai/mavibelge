<?php
/**
 * Faz 6B3 — import audit hedefi sözleşmesi.
 *
 * `record()` yazma BAŞARISIZ olursa `false` döner; apply/rollback bunu
 * başarısızlık sayar (audit yazılamayan bir işlem başarılı sayılmaz).
 * Context önce `MaviBelge_Core_Import_Audit_Context::build()` ile kapalı
 * izin listesinden geçer; alan içeriği/kişisel veri/gizli bilgi taşınmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MaviBelge_Core_Import_Audit_Sink {

	/** @return bool Audit hedefi yazılabilir durumda mı (SALT OKUNUR). */
	public function ready();

	/**
	 * @param string $event  MaviBelge_Core_Audit_Log::EVENT_IMPORT_* sabitlerinden biri.
	 * @param int    $runId
	 * @param array  $context Ham context — kapalı izin listesinden geçmezse yazılmaz.
	 * @return bool
	 */
	public function record( $event, $runId, array $context );
}
