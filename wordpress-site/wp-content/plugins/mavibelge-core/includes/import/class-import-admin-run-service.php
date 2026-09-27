<?php
/**
 * Faz 6B4 — admin çalıştırma servisi (WordPress fonksiyonu çağırmaz; bütün bağımlılıklar factory'den gelir).
 *
 * YENİ karar motoru YOKTUR: bu sınıf yalnız (1) aşama sırasını sunucu tarafında zorunlu kılar, (2) kullanıcının BİREBİR
 * yazdığı onay ifadesini doğrular, (3) mevcut `Apply_Service::start_resumable()/advance_resumable()` ve
 * `Rollback_Service::start_resumable()/advance_resumable()` primitive'lerini çağırır, (4) yalnız güvenli DTO döndürür.
 * Yetki/nonce/HTTPS/sabit/ortam kapıları ve kapalı request şekli çağıran katmandadır (`MaviBelge_Core_Import_Admin_Gates`).
 *
 * Aşama sırası: pages -> sectors -> qualifications -> all -> content (TEK kaynak: Apply_Plan::PREREQUISITE_STAGE; CLI aynı kuralı uygular). Bir aşama yalnız önceki aşamanın kapsadığı bütün kayıtlar
 * GERÇEK readback sonucunda `unchanged` iken açılır (önceki aşamanın tam planı yeniden çalıştırılır; UI kararına güvenilmez).
 *
 * Güvenli DTO: yalnız sayaç, durum, sabit hata kodu, source_key/tür/karar/reason ve hash özetleri. Yönetilen alan değeri,
 * SQL, exception mesajı, parola veya mutlak yol ASLA döndürülmez.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Admin_Run_Service {

	/** Admin istek başına en çok işlenen kayıt (apply ve rollback). */
	const BATCH_SIZE = 10;

	/** Aşama => önkoşul aşaması (bu aşamanın TAM planı unchanged olmalı). */
	const PREREQUISITE_STAGE = MaviBelge_Core_Import_Apply_Plan::PREREQUISITE_STAGE;

	const LIST_LIMIT = 20;

	/** @var MaviBelge_Core_Import_Runtime_Factory */
	private $factory;

	public function __construct( MaviBelge_Core_Import_Runtime_Factory $factory ) {
		$this->factory = $factory;
	}

	/**
	 * @return array{met: bool, requires: string|null}
	 */
	public function prerequisites( $stage ) {
		if ( ! is_string( $stage ) || ! array_key_exists( $stage, self::PREREQUISITE_STAGE ) ) {
			return array( 'met' => false, 'requires' => null );
		}
		$required = self::PREREQUISITE_STAGE[ $stage ];
		if ( null === $required ) {
			return array( 'met' => true, 'requires' => null );
		}
		$ctx = $this->factory->dry_run_service()->run_stage( $required );
		if ( ! $ctx['ok'] ) {
			return array( 'met' => false, 'requires' => $required );
		}
		$summary = $ctx['plan']['summary'];
		$met     = empty( $ctx['plan']['errors'] ) && $summary['total'] > 0 && $summary['operations']['unchanged'] === $summary['total'] && true === $summary['structurally_valid'];
		return array( 'met' => $met, 'requires' => $required );
	}

	/** SALT OKUNUR aşama önizlemesi (güvenli DTO). */
	public function preview_stage( $stage ) {
		if ( ! is_string( $stage ) || ! in_array( $stage, MaviBelge_Core_Import_Apply_Plan::ALL_STAGES, true ) ) {
			return array( 'ok' => false, 'error_code' => 'invalid_stage' );
		}
		$preview = $this->factory->apply_service()->preview( $stage );
		$entries = array();
		foreach ( $preview['plan']['entries'] as $entry ) {
			$entries[] = array(
				'source_key' => isset( $entry['source_key'] ) && is_string( $entry['source_key'] ) ? $entry['source_key'] : '',
				'type'       => isset( $entry['type'] ) && is_string( $entry['type'] ) ? $entry['type'] : '',
				'decision'   => isset( $entry['decision'] ) && is_string( $entry['decision'] ) ? $entry['decision'] : '',
				'reason'     => isset( $entry['reason'] ) && is_string( $entry['reason'] ) ? $entry['reason'] : '',
			);
		}
		$notices = array();
		if ( MaviBelge_Core_Import_Apply_Plan::STAGE_PAGES === $stage ) {
			// Faz 12: kurum kararı bekleyen sayfa alanları görünür uyarı olarak (yalnız sözlük kodu + etiket).
			$stageCtx = $this->factory->dry_run_service()->run_stage( $stage );
			foreach ( $stageCtx['ok'] ? $stageCtx['manifest']['pages'] : array() as $record ) {
				if ( array() === $record['pending_decisions'] ) {
					continue;
				}
				$labels = array();
				foreach ( $record['pending_decisions'] as $code ) {
					$labels[] = MaviBelge_Core_Import_Page_Publisher::PENDING_LABELS[ $code ];
				}
				$notices[] = array( 'source_key' => $record['source_key'], 'title' => $record['title'], 'blocking' => $record['publish_hold'], 'pending' => $labels );
			}
		}
		$state = $this->factory->image_map_state();
		return array(
			'ok'               => $preview['ok'],
			'error_code'       => $preview['ok'] ? null : 'plan_not_available',
			'stage'            => $stage,
			'plan_digest'      => $preview['plan_digest'],
			'summary'          => $preview['summary'],
			'eligible'         => $preview['eligible'],
			'writes'           => $preview['writes'],
			'noops'            => $preview['noops'],
			'entries'          => $entries,
			'notices'          => $notices,
			'diagnostics'      => $preview['diagnostics'],
			'load_error_count' => count( $preview['errors'] ),
			'prerequisites'    => $this->prerequisites( $stage ),
			'map'              => array( 'present' => $state['present'], 'valid' => $state['valid'], 'required' => count( $state['required'] ), 'mapped' => count( $state['mappings'] ) ),
		);
	}

	/**
	 * Faz 12 — SALT OKUNUR sayfa yayın önizlemesi: her sayfanın başlığı, slug'ı, kaynak dosyası, durumu, yayınlanabilirlik nedeni ve
	 * bekleyen kurum kararları (Türkçe etiketlerle). Yalnız güvenli alanlar; içerik/yol/SQL YOK.
	 */
	public function preview_publish() {
		$pv = $this->factory->page_publisher()->preview();
		$rows = array();
		foreach ( $pv['rows'] as $row ) {
			$pending = array();
			foreach ( $row['pending_decisions'] as $code ) {
				$pending[] = MaviBelge_Core_Import_Page_Publisher::pending_view( $code );
			}
			$rows[] = array(
				'source_key'   => $row['source_key'],
				'slug'         => $row['slug'],
				'title'        => $row['title'],
				'source_file'  => $row['source_file'],
				'status'       => $row['status'],
				'reason'       => $row['reason'],
				'publish_hold' => $row['publish_hold'],
				'publish_requires' => $row['publish_requires'],
				'pending'      => $pending,
			);
		}
		return array(
			'ok'          => $pv['ok'],
			'error_code'  => $pv['error_code'],
			'plan_digest' => $pv['plan_digest'],
			'summary'     => $pv['summary'],
			'rows'        => $rows,
			'phrase'      => null === $pv['plan_digest'] ? null : MaviBelge_Core_Import_Admin_Gates::publish_phrase( $pv['plan_digest'] ),
		);
	}

	/**
	 * Faz 12 — hazır taslak sayfaları yayınlar (istek başına en çok 10). Onay ifadesi `YAYINLA <özet ilk 12>` BİREBİR olmalı;
	 * `expected_remaining` çift tıklama/bayat isteği reddeder. Yetki/nonce/HTTPS/sabit/staging kapıları çağıran katmandadır.
	 *
	 * @param mixed $planDigest
	 * @param mixed $confirmPhrase
	 * @param mixed $expectedRemaining
	 */
	public function publish_pages( $planDigest, $confirmPhrase, $expectedRemaining, $userId ) {
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $planDigest ) ) {
			return self::publish_result( array( 'ok' => false, 'status' => 'rejected', 'error_code' => 'invalid_confirmation' ) );
		}
		if ( ! MaviBelge_Core_Import_Admin_Gates::phrase_matches( $confirmPhrase, MaviBelge_Core_Import_Admin_Gates::publish_phrase( $planDigest ) ) ) {
			return self::publish_result( array( 'ok' => false, 'status' => 'rejected', 'error_code' => 'confirmation_phrase_mismatch' ) );
		}
		return self::publish_result( $this->factory->page_publisher()->publish( $planDigest, $expectedRemaining, (int) $userId ) );
	}

	/** Yayın sonucunu KAPALI, güvenli şekle indirger. */
	private static function publish_result( array $r ) {
		return array(
			'ok'          => isset( $r['ok'] ) ? (bool) $r['ok'] : false,
			'status'      => isset( $r['status'] ) && is_string( $r['status'] ) ? $r['status'] : null,
			'error_code'  => isset( $r['error_code'] ) && is_string( $r['error_code'] ) ? $r['error_code'] : null,
			'published'   => isset( $r['published'] ) ? (int) $r['published'] : 0,
			'remaining'   => isset( $r['remaining'] ) ? (int) $r['remaining'] : 0,
			'plan_digest' => isset( $r['plan_digest'] ) && is_string( $r['plan_digest'] ) ? $r['plan_digest'] : null,
		);
	}

	/**
	 * @param mixed $planDigest    Kullanıcının önizlemede gördüğü plan_digest.
	 * @param mixed $confirmPhrase Kullanıcının BİREBİR yazdığı "UYGULA <stage> <digest ilk 12>".
	 */
	public function start_apply( $stage, $planDigest, $confirmPhrase, $userId ) {
		if ( ! is_string( $stage ) || ! in_array( $stage, MaviBelge_Core_Import_Apply_Plan::ALL_STAGES, true ) ) {
			return self::run_result( array( 'ok' => false, 'error_code' => 'invalid_stage' ) );
		}
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $planDigest ) ) {
			return self::run_result( array( 'ok' => false, 'error_code' => 'invalid_confirmation' ), $stage );
		}
		$prereq = $this->prerequisites( $stage );
		if ( ! $prereq['met'] ) {
			return self::run_result( array( 'ok' => false, 'status' => 'rejected', 'error_code' => 'prerequisite_not_met' ), $stage );
		}
		if ( ! MaviBelge_Core_Import_Admin_Gates::phrase_matches( $confirmPhrase, MaviBelge_Core_Import_Admin_Gates::apply_phrase( $stage, $planDigest ) ) ) {
			return self::run_result( array( 'ok' => false, 'status' => 'rejected', 'error_code' => 'confirmation_phrase_mismatch' ), $stage );
		}
		$state  = $this->factory->image_map_state();
		$result = $this->factory->apply_service()->start_resumable( $stage, $planDigest, self::BATCH_SIZE, (int) $userId, $state['valid'] ? $state['digest'] : null );
		return self::run_result( $result, $stage );
	}

	public function advance_apply( $runUid, $expectedCheckpoint, $userId ) {
		$state  = $this->factory->image_map_state();
		$result = $this->factory->apply_service()->advance_resumable( $runUid, $expectedCheckpoint, (int) $userId, $state['valid'] ? $state['digest'] : null );
		return self::run_result( $result );
	}

	/** SALT OKUNUR rollback önizlemesi (güvenli DTO). */
	public function preview_rollback( $runUid ) {
		$preview  = $this->factory->rollback_service()->preview( $runUid );
		$blockers = array();
		foreach ( $preview['blockers'] as $blocker ) {
			$blockers[] = array( 'source_key' => is_string( $blocker['source_key'] ) ? $blocker['source_key'] : null, 'code' => (string) $blocker['code'] );
		}
		return array(
			'ok'              => $preview['ok'],
			'error_code'      => $preview['error_code'],
			'run_uid'         => $preview['run_uid'],
			'status'          => $preview['status'],
			'items_pending'   => $preview['items_pending'],
			'rollback_digest' => $preview['rollback_digest'],
			'blockers'        => $blockers,
		);
	}

	public function start_rollback( $runUid, $rollbackDigest, $confirmPhrase ) {
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $rollbackDigest ) ) {
			return self::run_result( array( 'ok' => false, 'error_code' => 'invalid_confirmation' ) );
		}
		if ( ! MaviBelge_Core_Import_Admin_Gates::phrase_matches( $confirmPhrase, MaviBelge_Core_Import_Admin_Gates::rollback_phrase( $runUid, $rollbackDigest ) ) ) {
			return self::run_result( array( 'ok' => false, 'status' => 'rejected', 'error_code' => 'confirmation_phrase_mismatch' ) );
		}
		return self::run_result( $this->factory->rollback_service()->start_resumable( $runUid, $rollbackDigest ) );
	}

	public function advance_rollback( $runUid, $expectedCheckpoint ) {
		return self::run_result( $this->factory->rollback_service()->advance_resumable( $runUid, $expectedCheckpoint, self::BATCH_SIZE ) );
	}

	/**
	 * Son run'lar (en yeni önce): yalnız güvenli sütunlar. Tablolar kurulu değilse boş liste (tablo OLUŞTURMAZ).
	 *
	 * @return array[]
	 */
	public function list_runs() {
		$store = $this->factory->run_store();
		if ( ! $store->is_installed() ) {
			return array();
		}
		$rows = array();
		foreach ( $store->list_runs( self::LIST_LIMIT ) as $run ) {
			$rows[] = array(
				'run_uid'           => $run['uid'],
				'stage'             => $run['stage'],
				'status'            => $run['status'],
				'error_code'        => $run['error_code'],
				'total_writes'      => $run['total_writes'],
				'committed_batches' => $run['committed_batches'],
				'committed_items'   => $run['committed_items'],
				'rollback_batches'  => isset( $run['rollback_batches'] ) ? $run['rollback_batches'] : 0,
				'rollback_items'    => isset( $run['rollback_items'] ) ? $run['rollback_items'] : 0,
				'created_at'        => $run['created_at'],
				'next_action'       => self::next_action( $run['status'] ),
			);
		}
		return $rows;
	}

	/**
	 * Sektör görsel eşleme görünümü (güvenli): gerekli slug'lar ve kaynak görsel ETİKETİ (yalnız dosya adı), doğrulanmış
	 * mevcut eşleme ve hata KODLARI.
	 *
	 * @return array
	 */
	public function image_map_view() {
		$state    = $this->factory->image_map_state();
		$required = array();
		foreach ( $state['required'] as $slug => $source ) {
			$required[ $slug ] = basename( $source );
		}
		return array(
			'present'         => $state['present'],
			'valid'           => $state['valid'],
			'error_codes'     => $state['errors'],
			'required'        => $required,
			'mappings'        => $state['mappings'],
			'digest'          => $state['digest'],
			'manifest_digest' => $state['manifest_digest'],
		);
	}

	/**
	 * Yetkili kullanıcının seçtiği görsel eşlemesini doğrulayıp kaydeder (yetki/nonce çağıran katmanda).
	 *
	 * @param array<string,int> $mappings
	 * @return array{ok: bool, error_codes: string[], digest: string|null, changed_slugs: string[]}
	 */
	public function save_image_map( array $mappings ) {
		$state = $this->factory->image_map_state();
		if ( null === $state['manifest_digest'] ) {
			return array( 'ok' => false, 'error_codes' => array( 'manifest_unavailable' ), 'digest' => null, 'changed_slugs' => array() );
		}
		$loaded  = MaviBelge_Core_Import_Manifest_Loader::load_all( $this->factory->manifest_dir() );
		$records = $loaded['ok'] && isset( $loaded['manifest']['sectors'] ) ? $loaded['manifest']['sectors'] : array();
		$saved   = MaviBelge_Core_Import_Sector_Image_Map::save( $mappings, $records, $state['manifest_digest'], $this->factory->image_map_store() );
		return array( 'ok' => $saved['ok'], 'error_codes' => $saved['errors'], 'digest' => $saved['digest'], 'changed_slugs' => $saved['changed_slugs'] );
	}

	private static function next_action( $status ) {
		switch ( $status ) {
			case 'ready':
			case 'paused':
				return 'advance_apply';
			case 'completed':
			case 'rollback_required':
			case 'rollback_failed':
				return 'preview_rollback';
			case 'rollback_ready':
			case 'rollback_paused':
				return 'advance_rollback';
			default:
				return null;
		}
	}

	/** Servis sonucunu KAPALI, güvenli şekle indirger (errors metinleri, writes_total vb. dışarı çıkmaz). */
	private static function run_result( array $result, $stage = null ) {
		return array(
			'ok'          => isset( $result['ok'] ) ? (bool) $result['ok'] : false,
			'status'      => isset( $result['status'] ) && is_string( $result['status'] ) ? $result['status'] : null,
			'error_code'  => isset( $result['error_code'] ) && is_string( $result['error_code'] ) ? $result['error_code'] : null,
			'run_uid'     => isset( $result['run_uid'] ) && is_string( $result['run_uid'] ) ? $result['run_uid'] : null,
			'stage'       => is_string( $stage ) ? $stage : null,
			'plan_digest' => isset( $result['plan_digest'] ) && is_string( $result['plan_digest'] ) ? $result['plan_digest'] : null,
			'checkpoint'  => isset( $result['checkpoint'] ) ? (int) $result['checkpoint'] : 0,
			'total'       => isset( $result['total'] ) ? (int) $result['total'] : 0,
			'committed'   => isset( $result['committed'] ) ? (int) $result['committed'] : 0,
			'remaining'   => isset( $result['remaining'] ) ? (int) $result['remaining'] : 0,
		);
	}
}
