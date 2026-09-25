<?php
/**
 * Faz 6B3 — import run/checkpoint deposu sözleşmesi.
 *
 * WP-CLI ve (ileride) admin fallback AYNI depoyu ve AYNI kayıt şeklini
 * kullanır. Run ve run item ayrı kayıtlardır. Run item'ı yalnız import
 * tarafından yönetilen alanları taşıyan kapalı rollback kaydını (JSON)
 * saklar; gizli bilgi veya kişisel veri saklanmaz.
 *
 * Run satırı (get_run/create_run dönüşü):
 *   id (int), uid (32 hex), stage, status (MaviBelge_Core_Import_Run_State),
 *   plan_digest, manifest_digest (64 hex), batch_size, total_writes,
 *   committed_batches, committed_items (int), error_code (string|null),
 *   created_by (int), created_at, updated_at (UTC 'Y-m-d H:i:s').
 * Run item satırı (get_items dönüşü):
 *   id, run_id, seq, batch_no (int), source_key, type, decision,
 *   target_id (int), rollback_record (JSON string), rollback_status
 *   ('pending'|'rolled_back').
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MaviBelge_Core_Import_Run_Store {

	/** Tablolar kurulu mu (SALT OKUNUR; kurulum yapmaz). */
	public function is_installed();

	/** İdempotent kurulum; sürüm kaydı yalnız tablolar GERÇEKTEN oluştuysa yazılır. */
	public function ensure_installed();

	/** Tek eşzamanlı apply/rollback kilidi. @return bool */
	public function acquire_lock();

	/** @return bool */
	public function release_lock();

	/**
	 * `planned` durumunda yeni run oluşturur; uid tahmin edilemez rastgele
	 * bir kimliktir ama YETKİ anahtarı olarak kullanılmaz.
	 *
	 * @param array $data stage, plan_digest, manifest_digest, batch_size, total_writes, created_by
	 * @return array|null Oluşturulan run satırı.
	 */
	public function create_run( array $data );

	/**
	 * Karşılaştır-ve-değiştir durum geçişi: yalnız mevcut durum `$from` ise
	 * VE geçiş `MaviBelge_Core_Import_Run_State::can_transition()` ile
	 * izinliyse uygulanır.
	 *
	 * @param array $fields Yalnız error_code değiştirilebilir.
	 * @return bool
	 */
	public function transition( $runId, $from, $to, array $fields = array() );

	/** @return array|null */
	public function get_run( $uid );

	/** @return array[] */
	public function find_runs_in_status( array $statuses );

	/** @return array[] En yeni önce. */
	public function list_runs( $limit );

	/**
	 * @param array $item seq, batch_no, source_key, type, decision, target_id, rollback_record (JSON)
	 * @return bool
	 */
	public function add_item( $runId, array $item );

	/** Yalnız `running` durumundaki run için checkpoint sayaçlarını yazar. @return bool */
	public function record_checkpoint( $runId, $committedBatches, $committedItems );

	/** @return array[] seq sırasıyla. */
	public function get_items( $runId );

	/**
	 * Herhangi bir run'da `create` kararıyla oluşturulmuş VE rollback'i
	 * tamamlanmış (`rolled_back`) hedef ID'leri — sektör terimi silinmeden
	 * önce, terime hâlâ bağlı çöp kutusundaki postların import tarafından
	 * oluşturulup geri alınmış kayıtlar olduğunu kanıtlamak için.
	 *
	 * @param string $type qualification|fee
	 * @return int[]
	 */
	public function rolled_back_create_target_ids( $type );

	/** Yalnız `pending` item'ı `rolled_back` yapar. @return bool */
	public function mark_item_rolled_back( $itemId );
}
