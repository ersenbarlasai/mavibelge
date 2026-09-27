<?php
/**
 * Faz 6B2 — TEK dry-run uygulama servisi.
 *
 * Akış (görev promptu §6): sabit manifest dosyaları -> güvenli manifest
 * loader -> salt okunur WordPress target repository -> targetLookups + üç
 * anahtarlı dependencies DTO -> `MaviBelge_Core_Import_Dry_Run_Planner::plan()`
 * (TEK çağrı) -> tek dry-run sonuç DTO'su. WP-CLI komutu ve admin ekranı
 * kendi karar mantığını YAZMAZ — ikisi de yalnız bu servisi çağırır.
 *
 * Loader başarısız olursa (dosya yok/bozuk/zarf geçersiz) planlayıcı yine
 * de TEK SEFER çağrılır — `manifest=null` ile, bu da
 * `MaviBelge_Core_Import_Record_Validator::validate_manifest_shape()`'in
 * kendi fail-closed yoluna (entries=[], summary.applicable=false) düşer.
 * Loader'ın somut, dosya-düzeyi hataları AYRI `load_errors` alanında
 * taşınır — Faz 6B1'in `plan.errors` sözleşmesi bu yüzden KEYFÎ
 * DEĞİŞTİRİLMEZ, yalnız genişletilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Dry_Run_Service {

	/** @var MaviBelge_Core_Import_Target_Repository */
	private $repository;

	/**
	 * Faz 6B3 — YALNIZ testlerin (saf PHP ve izole runtime) sahte fixture
	 * manifest dizinini verebilmesi için. WP-CLI komutu ve admin ekranı bu
	 * parametreyi ASLA geçmez; istemciden gelen hiçbir yol buraya taşınmaz
	 * (bkz. MaviBelge_Core_Import_Manifest_Loader::load_all() docblock'u).
	 *
	 * @var string|null
	 */
	private $manifestDirOverride;

	public function __construct( MaviBelge_Core_Import_Target_Repository $repository, $manifestDirOverride = null ) {
		$this->repository          = $repository;
		$this->manifestDirOverride = is_string( $manifestDirOverride ) && '' !== $manifestDirOverride ? $manifestDirOverride : null;
	}

	/**
	 * @return array{
	 *   plan: array{entries: array, summary: array, errors: array},
	 *   diagnostics: array<int, array{code:string, type:string, source_key:string}>,
	 *   generated_at_utc: string,
	 *   read_only: true,
	 *   load_errors: string[],
	 * }
	 */
	public function run_dry_run() {
		$result = $this->run_stage( MaviBelge_Core_Import_Apply_Plan::STAGE_ALL );
		return array(
			'plan'             => $result['plan'],
			'diagnostics'      => $result['diagnostics'],
			'generated_at_utc' => $result['generated_at_utc'],
			'read_only'        => true,
			'load_errors'      => $result['load_errors'],
		);
	}

	/**
	 * Faz 6B3 — bir aşamanın (bkz. MaviBelge_Core_Import_Apply_Plan) SALT
	 * OKUNUR planı. `all` aşaması run_dry_run() ile AYNI planı üretir.
	 *
	 * @param mixed $stage
	 * @return array{ok: bool, stage: mixed, plan: array, manifest: array|null, dependencies: array,
	 *   manifest_digest: string|null, load_errors: string[], diagnostics: array, generated_at_utc: string}
	 */
	public function run_stage( $stage ) {
		$loadResult = $this->load_for_stage( $stage );
		$manifest   = $loadResult['ok'] ? MaviBelge_Core_Import_Apply_Plan::filter_manifest( $loadResult['manifest'], $stage ) : null;

		if ( null === $manifest ) {
			$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( null, array(), $this->empty_dependencies() );
			return array(
				'ok'               => false,
				'stage'            => $stage,
				'plan'             => $plan,
				'manifest'         => null,
				'dependencies'     => $this->empty_dependencies(),
				'manifest_digest'  => null,
				'load_errors'      => $loadResult['ok'] ? array( 'Bilinmeyen içe aktarım aşaması.' ) : $loadResult['errors'],
				'diagnostics'      => array(),
				'generated_at_utc' => self::now_utc(),
			);
		}

		$targetLookups = $this->build_target_lookups( $manifest );
		$dependencies  = $this->build_dependencies( $manifest );

		$plan = MaviBelge_Core_Import_Dry_Run_Planner::plan( $manifest, $targetLookups, $dependencies );

		return array(
			'ok'               => true,
			'stage'            => $stage,
			'plan'             => $plan,
			'manifest'         => $manifest,
			'dependencies'     => $dependencies,
			'manifest_digest'  => MaviBelge_Core_Import_Apply_Plan::manifest_digest( $loadResult['manifest'] ),
			'load_errors'      => array(),
			'diagnostics'      => self::sanitize_diagnostics( $this->repository->get_diagnostics() ),
			'generated_at_utc' => self::now_utc(),
		);
	}

	/**
	 * Faz 6B3 — manifest dosyalarının ŞU ANKİ özeti (batch'ler arasında
	 * manifestin değişmediğini doğrulamak için). SALT OKUNUR.
	 *
	 * @return string|null
	 */
	public function current_manifest_digest( $stage = MaviBelge_Core_Import_Apply_Plan::STAGE_ALL ) {
		$loadResult = $this->load_for_stage( $stage );
		return $loadResult['ok'] ? MaviBelge_Core_Import_Apply_Plan::manifest_digest( $loadResult['manifest'] ) : null;
	}

	/**
	 * Faz 7 — aşamanın manifest dosyalarını yükler. `content` aşaması YALNIZ iki içerik
	 * dosyasını okur (katalog dosyaları gerekmez); diğer aşamalar YALNIZ üç katalog
	 * dosyasını okur (içerik dosyaları varken de yokken de aynı sonuç). Bilinmeyen aşama
	 * katalog yolunu izler ve filter_manifest()'te null olur.
	 *
	 * @return array{ok: bool, manifest: array|null, errors: string[]}
	 */
	private function load_for_stage( $stage ) {
		if ( MaviBelge_Core_Import_Apply_Plan::STAGE_PAGES === $stage ) {
			// Faz 12: `pages` aşaması YALNIZ sayfa manifestini okur (katalog/içerik dosyaları gerekmez).
			$pages = MaviBelge_Core_Import_Manifest_Loader::load_pages( $this->manifestDirOverride );
			if ( ! $pages['ok'] ) {
				return $pages;
			}
			return array(
				'ok'       => true,
				'manifest' => array(
					'sectors'        => array(),
					'qualifications' => array(),
					'fees'           => array(),
					'pages'          => $pages['manifest']['pages'],
				),
				'errors'   => array(),
			);
		}
		if ( MaviBelge_Core_Import_Apply_Plan::STAGE_CONTENT === $stage ) {
			$content = MaviBelge_Core_Import_Manifest_Loader::load_content( $this->manifestDirOverride );
			if ( ! $content['ok'] ) {
				return $content;
			}
			return array(
				'ok'       => true,
				'manifest' => array(
					'sectors'        => array(),
					'qualifications' => array(),
					'fees'           => array(),
					'news'           => $content['manifest']['news'],
					'references'     => $content['manifest']['references'],
					'faqs'           => $content['manifest']['faqs'],
				),
				'errors'   => array(),
			);
		}
		return MaviBelge_Core_Import_Manifest_Loader::load_all( $this->manifestDirOverride );
	}

	/**
	 * Faz 6B3 — TEK kaydın ŞU ANKİ durumunu, planlamayla AYNI yoldan
	 * (repository lookup + bağımlılık çözümü + planlayıcı) SALT OKUNUR
	 * yeniden gözler. Apply bunu yazmadan hemen önce (TOCTOU) ve yazdıktan
	 * hemen sonra (readback) kullanır; ikinci bir karar sistemi yoktur.
	 *
	 * @param string $type
	 * @param array  $record Manifest kaydı.
	 * @return array{entry: array, lookup: array, dependencies: array}
	 */
	public function observe_record( $type, array $record ) {
		$lists = array(
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR        => 'sectors',
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_QUALIFICATION => 'qualifications',
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FEE           => 'fees',
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_NEWS          => 'news',
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_REFERENCE     => 'references',
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FAQ           => 'faqs',
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_PAGE          => 'pages',
		);
		$mini = array( 'sectors' => array(), 'qualifications' => array(), 'fees' => array() );
		if ( isset( $lists[ $type ] ) ) {
			$mini[ $lists[ $type ] ] = array( $record );
		}
		$lookups      = $this->build_target_lookups( $mini );
		$dependencies = $this->build_dependencies( $mini );
		switch ( $type ) {
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR:
				$entry = MaviBelge_Core_Import_Dry_Run_Planner::plan_sector( $record, $lookups, $dependencies );
				break;
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_QUALIFICATION:
				$entry = MaviBelge_Core_Import_Dry_Run_Planner::plan_qualification( $record, $lookups, $dependencies );
				break;
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_NEWS:
				$entry = MaviBelge_Core_Import_Dry_Run_Planner::plan_news( $record, $lookups, $dependencies );
				break;
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_REFERENCE:
				$entry = MaviBelge_Core_Import_Dry_Run_Planner::plan_reference( $record, $lookups, $dependencies );
				break;
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FAQ:
				$entry = MaviBelge_Core_Import_Dry_Run_Planner::plan_faq( $record, $lookups, $dependencies );
				break;
			case MaviBelge_Core_Import_Dry_Run_Planner::TYPE_PAGE:
				$entry = MaviBelge_Core_Import_Dry_Run_Planner::plan_page( $record, $lookups, $dependencies );
				break;
			default:
				$entry = MaviBelge_Core_Import_Dry_Run_Planner::plan_fee( $record, $lookups, $dependencies );
				break;
		}
		$sourceKey = isset( $record['source_key'] ) && is_string( $record['source_key'] ) ? $record['source_key'] : '';
		$rawLookup = array_key_exists( $sourceKey, $lookups ) ? $lookups[ $sourceKey ] : array( 'target_found' => false );
		return array(
			'entry'        => $entry,
			'lookup'       => MaviBelge_Core_Import_Record_Validator::normalize_target_lookup( $rawLookup, $type ),
			'dependencies' => $dependencies,
		);
	}

	/**
	 * Düzeltme ve Kabul §2.3 son madde — repository'nin `get_diagnostics()`'i
	 * artık arayüzde tanımlı olsa da, BU servis katmanı repository'nin
	 * döndürdüğü değeri KÖRÜ KÖRÜNE güvenmez: yalnız kapalı `{code, type,
	 * source_key}` şeklini (üçü de string, `type` bilinen bir
	 * `MaviBelge_Core_Import_Dry_Run_Planner::TYPE_*` değeri) taşıyan
	 * girdiler WP-CLI/admin çıktı katmanına GEÇER — şekli bozuk/fazladan
	 * anahtarlı/hassas bir alan taşıyan bir girdi sessizce ELENİR (fatal
	 * vermez, yalnız çıktıdan düşer).
	 *
	 * @param mixed $diagnostics
	 * @return array<int, array{code:string, type:string, source_key:string}>
	 */
	private static function sanitize_diagnostics( $diagnostics ) {
		if ( ! is_array( $diagnostics ) ) {
			return array();
		}
		$knownTypes = MaviBelge_Core_Import_Managed_Fields::TYPES;
		$safe       = array();
		foreach ( $diagnostics as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$extra = array_diff( array_keys( $entry ), array( 'code', 'type', 'source_key' ) );
			if ( ! empty( $extra ) ) {
				continue;
			}
			if ( ! isset( $entry['code'] ) || ! is_string( $entry['code'] ) || '' === $entry['code'] ) {
				continue;
			}
			if ( ! isset( $entry['type'] ) || ! in_array( $entry['type'], $knownTypes, true ) ) {
				continue;
			}
			if ( ! isset( $entry['source_key'] ) || ! is_string( $entry['source_key'] ) || '' === $entry['source_key'] ) {
				continue;
			}
			$safe[] = array(
				'code'       => $entry['code'],
				'type'       => $entry['type'],
				'source_key' => $entry['source_key'],
			);
		}
		return $safe;
	}

	private function empty_dependencies() {
		return array(
			'sector_term_ids'             => array(),
			'sector_image_attachment_ids' => array(),
			'qualification_post_ids'      => array(),
		);
	}

	/** @return array<string, array> source_key => MaviBelge_Core_Import_Target_Repository::find_target_by_source_key() sonucu. */
	private function build_target_lookups( array $manifest ) {
		$lookups = array();
		$byType  = array(
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_SECTOR        => isset( $manifest['sectors'] ) ? $manifest['sectors'] : array(),
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_QUALIFICATION => isset( $manifest['qualifications'] ) ? $manifest['qualifications'] : array(),
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FEE           => isset( $manifest['fees'] ) ? $manifest['fees'] : array(),
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_NEWS          => isset( $manifest['news'] ) ? $manifest['news'] : array(),
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_REFERENCE     => isset( $manifest['references'] ) ? $manifest['references'] : array(),
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_FAQ           => isset( $manifest['faqs'] ) ? $manifest['faqs'] : array(),
			MaviBelge_Core_Import_Dry_Run_Planner::TYPE_PAGE          => isset( $manifest['pages'] ) ? $manifest['pages'] : array(),
		);

		foreach ( $byType as $type => $records ) {
			if ( ! is_array( $records ) ) {
				continue;
			}
			foreach ( $records as $record ) {
				if ( ! is_array( $record ) || ! array_key_exists( 'source_key', $record ) || ! is_string( $record['source_key'] ) || '' === $record['source_key'] ) {
					// Biçimsiz kayıt zaten kendi validate_*() yolunda invalid
					// sayılacak — lookup'a ihtiyacı yok, gereksiz sorgu açmaz.
					continue;
				}
				$sourceKey = $record['source_key'];
				if ( array_key_exists( $sourceKey, $lookups ) ) {
					// Girişte tekrar eden source_key zaten planlayıcının
					// find_source_key_problems() adımında ayrıca raporlanır —
					// burada sorguyu tekrarlamaya gerek yok.
					continue;
				}
				$lookups[ $sourceKey ] = $this->repository->find_target_by_source_key( $type, $sourceKey );
			}
		}

		return $lookups;
	}

	/** @return array{sector_term_ids: array, sector_image_attachment_ids: array, qualification_post_ids: array} */
	private function build_dependencies( array $manifest ) {
		$sectorTermIds    = array();
		$imageIds         = array();
		$qualificationIds = array();

		$qualifications = isset( $manifest['qualifications'] ) && is_array( $manifest['qualifications'] ) ? $manifest['qualifications'] : array();
		foreach ( $qualifications as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['sector_slug'] ) || ! is_string( $record['sector_slug'] ) || '' === $record['sector_slug'] ) {
				continue;
			}
			$slug = $record['sector_slug'];
			if ( array_key_exists( $slug, $sectorTermIds ) ) {
				continue;
			}
			$resolved = $this->repository->resolve_sector_term_id( $slug );
			if ( null !== $resolved ) {
				$sectorTermIds[ $slug ] = $resolved;
			}
		}

		$sectors = isset( $manifest['sectors'] ) && is_array( $manifest['sectors'] ) ? $manifest['sectors'] : array();
		foreach ( $sectors as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['image'], $record['slug'] ) || '' === $record['image'] || ! is_string( $record['slug'] ) || '' === $record['slug'] ) {
				continue; // Kaynakta görsel YOKSA hiç aranmaz (tahmin/fuzzy yok).
			}
			$slug = $record['slug'];
			if ( array_key_exists( $slug, $imageIds ) ) {
				continue;
			}
			$resolved = $this->repository->resolve_sector_image_attachment_id( $slug );
			if ( null !== $resolved ) {
				$imageIds[ $slug ] = $resolved;
			}
		}

		$fees = isset( $manifest['fees'] ) && is_array( $manifest['fees'] ) ? $manifest['fees'] : array();
		foreach ( $fees as $record ) {
			if ( ! is_array( $record ) || ! isset( $record['qualification_code'] ) || ! is_string( $record['qualification_code'] ) || '' === $record['qualification_code'] ) {
				continue; // 19 kodsuz ücret: hiç aranmaz (Faz 6A'nın kararı, plan_fee()'de de tekrar uygulanır).
			}
			$code = $record['qualification_code'];
			if ( array_key_exists( $code, $qualificationIds ) ) {
				continue;
			}
			$resolved = $this->repository->resolve_qualification_post_id( $code );
			if ( null !== $resolved ) {
				$qualificationIds[ $code ] = $resolved;
			}
		}

		$dependencies = array(
			'sector_term_ids'             => $sectorTermIds,
			'sector_image_attachment_ids' => $imageIds,
			'qualification_post_ids'      => $qualificationIds,
		);

		// Faz 7 — haber türü terimleri YALNIZ manifest haber listesi taşıyorsa (content aşaması /
		// haber gözlemi) DTO'ya eklenir; katalog aşamalarının DTO'su ESKİSİYLE aynı üç anahtardır.
		// Çözümleyici arayüzünü uygulamayan repository'de harita boş kalır (haberler blocked).
		if ( array_key_exists( 'news', $manifest ) ) {
			$newsTypeIds = array();
			if ( $this->repository instanceof MaviBelge_Core_Import_Content_Dependency_Resolver && is_array( $manifest['news'] ) ) {
				foreach ( $manifest['news'] as $record ) {
					if ( ! is_array( $record ) || ! isset( $record['news_type'] ) || ! is_string( $record['news_type'] ) || array_key_exists( $record['news_type'], $newsTypeIds ) ) {
						continue;
					}
					$resolved = $this->repository->resolve_news_type_term_id( $record['news_type'] );
					if ( null !== $resolved ) {
						$newsTypeIds[ $record['news_type'] ] = $resolved;
					}
				}
			}
			$dependencies['news_type_term_ids'] = $newsTypeIds;
		}

		return $dependencies;
	}

	/** UTC ISO 8601 zaman damgası — hash/karar hesabına GİRMEZ, yalnız görüntü/tanılama amaçlıdır. */
	private static function now_utc() {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}
}
