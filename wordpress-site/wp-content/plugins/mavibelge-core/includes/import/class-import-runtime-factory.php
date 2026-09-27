<?php
/**
 * Faz 6B4 — CLI ve admin için TEK ortak import çalışma-zamanı grafiği.
 *
 * WP-CLI komutu ve admin ekranı repository/writer/transaction/run store/audit bağımlılıklarını AYRI AYRI
 * kurmaz; ikisi de bu factory'den aynı, tembel ve önbellekli servis örneklerini alır. Böylece aynı fixture
 * için CLI ve admin AYNI summary/entries/manifest digest/plan digest'ini üretir; ikinci bir importer veya
 * karar motoru yoktur (kararlar `Dry_Run_Service`/`Dry_Run_Planner`/`Apply_Eligibility`/`Write_Payload`).
 *
 * Sektör görsel eşlemesi burada DOĞRULANIR: option okunur, güncel sektör manifestiyle
 * `MaviBelge_Core_Import_Sector_Image_Map::validate_envelope()`'tan geçirilir ve YALNIZ geçerliyse repository'ye
 * verilir. Geçersiz/eski map repository'ye hiçbir zaman ulaşmaz (görselli sektörler `blocked_dependency` kalır).
 *
 * Kurucu `$overrides` (YALNIZ testler ve izole runtime içindir; WP-CLI/admin bunu geçmez, istemciden hiçbir değer
 * taşınmaz): manifest_dir, image_map_raw, attachment_inspector, repository_factory (callable(array $map)),
 * writer, tx, store, audit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Runtime_Factory {

	/** @var array */
	private $overrides;
	/** @var array<string,mixed> */
	private $memo = array();

	/** @param array $overrides Bkz. sınıf docblock'u; varsayılan boş (gerçek WordPress bağımlılıkları). */
	public function __construct( array $overrides = array() ) {
		$this->overrides = $overrides;
	}

	/** @return string|null Yalnız testlerde verilen manifest dizini. */
	public function manifest_dir() {
		return isset( $this->overrides['manifest_dir'] ) && is_string( $this->overrides['manifest_dir'] ) && '' !== $this->overrides['manifest_dir'] ? $this->overrides['manifest_dir'] : null;
	}

	/**
	 * Sektör görsel eşlemesinin ŞU ANKİ doğrulanmış durumu (SALT OKUNUR).
	 *
	 * @return array{present: bool, valid: bool, errors: string[], mappings: array<string,int>, digest: string|null,
	 *   manifest_digest: string|null, required: array<string,string>}
	 */
	public function image_map_state() {
		return $this->memoize(
			'image_map_state',
			function () {
				$loaded  = MaviBelge_Core_Import_Manifest_Loader::load_all( $this->manifest_dir() );
				$records = $loaded['ok'] && isset( $loaded['manifest']['sectors'] ) && is_array( $loaded['manifest']['sectors'] ) ? $loaded['manifest']['sectors'] : array();
				if ( ! $loaded['ok'] ) {
					return array( 'present' => false, 'valid' => false, 'errors' => array( 'manifest_unavailable' ), 'mappings' => array(), 'digest' => null, 'manifest_digest' => null, 'required' => array() );
				}
				$manifestDigest = MaviBelge_Core_Import_Sector_Image_Map::manifest_digest_for( $records );
				$raw            = array_key_exists( 'image_map_raw', $this->overrides ) ? $this->overrides['image_map_raw'] : get_option( MaviBelge_Core_Import_Sector_Image_Map::OPTION, null );
				$inspect        = isset( $this->overrides['attachment_inspector'] ) ? $this->overrides['attachment_inspector'] : array( 'MaviBelge_Core_Import_Sector_Image_Map', 'inspect_attachment' );
				$state          = MaviBelge_Core_Import_Sector_Image_Map::state_from_raw( $raw, $records, $inspect, $manifestDigest );
				$state['manifest_digest'] = $manifestDigest;
				$state['required']        = MaviBelge_Core_Import_Sector_Image_Map::required_image_sources( $records );
				return $state;
			}
		);
	}

	/** @return MaviBelge_Core_Import_Image_Map_Store Sektör görsel eşlemesi kayıt deposu (atomik option + audit). */
	public function image_map_store() {
		return $this->memoize(
			'image_map_store',
			function () {
				return isset( $this->overrides['image_map_store'] ) ? $this->overrides['image_map_store'] : new MaviBelge_Core_Import_Wp_Image_Map_Store( $this->transaction_for_map() );
			}
		);
	}

	private function transaction_for_map() {
		$tx = $this->transaction();
		return $tx instanceof MaviBelge_Core_Import_Wpdb_Transaction ? $tx : null;
	}

	/** @return MaviBelge_Core_Import_Target_Repository */
	public function repository() {
		return $this->memoize(
			'repository',
			function () {
				$state = $this->image_map_state();
				$map   = $state['valid'] ? $state['mappings'] : array(); // doğrulanmamış map ASLA verilmez
				if ( isset( $this->overrides['repository_factory'] ) && is_callable( $this->overrides['repository_factory'] ) ) {
					return call_user_func( $this->overrides['repository_factory'], $map );
				}
				return new MaviBelge_Core_Import_WordPress_Target_Repository( $map );
			}
		);
	}

	/** @return MaviBelge_Core_Import_Dry_Run_Service */
	public function dry_run_service() {
		return $this->memoize(
			'dry_run_service',
			function () {
				return new MaviBelge_Core_Import_Dry_Run_Service( $this->repository(), $this->manifest_dir() );
			}
		);
	}

	/** @return MaviBelge_Core_Import_Target_Writer */
	public function writer() {
		return $this->memoize(
			'writer',
			function () {
				return isset( $this->overrides['writer'] ) ? $this->overrides['writer'] : new MaviBelge_Core_Import_WordPress_Target_Writer( MaviBelge_Core_Import_Manifest_Loader::logo_dir( $this->manifest_dir() ) );
			}
		);
	}

	/** @return MaviBelge_Core_Import_Transaction */
	public function transaction() {
		return $this->memoize(
			'tx',
			function () {
				return isset( $this->overrides['tx'] ) ? $this->overrides['tx'] : new MaviBelge_Core_Import_Wpdb_Transaction();
			}
		);
	}

	/** @return MaviBelge_Core_Import_Run_Store */
	public function run_store() {
		return $this->memoize(
			'store',
			function () {
				return isset( $this->overrides['store'] ) ? $this->overrides['store'] : new MaviBelge_Core_Import_Wpdb_Run_Store();
			}
		);
	}

	/** @return MaviBelge_Core_Import_Audit_Sink */
	public function audit_sink() {
		return $this->memoize(
			'audit',
			function () {
				return isset( $this->overrides['audit'] ) ? $this->overrides['audit'] : new MaviBelge_Core_Import_WP_Audit_Sink();
			}
		);
	}

	/** @return MaviBelge_Core_Import_Apply_Service */
	public function apply_service() {
		return $this->memoize(
			'apply_service',
			function () {
				return new MaviBelge_Core_Import_Apply_Service( $this->dry_run_service(), $this->writer(), $this->transaction(), $this->run_store(), $this->audit_sink() );
			}
		);
	}

	/** @return MaviBelge_Core_Import_Rollback_Service */
	public function rollback_service() {
		return $this->memoize(
			'rollback_service',
			function () {
				return new MaviBelge_Core_Import_Rollback_Service( $this->repository(), $this->writer(), $this->transaction(), $this->run_store(), $this->audit_sink() );
			}
		);
	}

	/** @return MaviBelge_Core_Import_Page_Publisher Faz 12: taslak sayfaları AYRI, onaylı işlemle yayınlar. */
	public function page_publisher() {
		return $this->memoize(
			'page_publisher',
			function () {
				return new MaviBelge_Core_Import_Page_Publisher( $this->dry_run_service(), $this->writer(), $this->transaction(), $this->run_store(), $this->audit_sink() );
			}
		);
	}

	private function memoize( $key, callable $build ) {
		if ( ! array_key_exists( $key, $this->memo ) ) {
			$this->memo[ $key ] = call_user_func( $build );
		}
		return $this->memo[ $key ];
	}
}
