<?php
/**
 * Faz 6B1 — pure dry-run planner. Ties together one Faz 6A manifest
 * record, a caller-resolved "target lookup" (does a matching WordPress
 * record already exist, what is its current managed-field state, is it
 * the right type, is it duplicated) and a caller-resolved dependency map
 * (sector slug → term ID, MYK code → post ID, sector image → attachment
 * ID) into ONE plan entry via MaviBelge_Core_Import_Decision.
 *
 * This class NEVER queries WordPress itself — `$targetLookup` and
 * `$dependencies` are plain arrays the CALLER (Faz 6B2's read-only
 * repository adapter, not written in this task) is responsible for
 * filling in from real `get_term_by()`/`get_posts()`/`get_post_meta()`
 * reads. No `get_posts`, `WP_Query`, `$wpdb`, `update_post_meta`,
 * `wp_insert_post`, `wp_update_term`, or any other WordPress I/O call
 * appears anywhere in this file.
 *
 * Faz 6B1 Düzeltme ve Kabul: her `plan_*()` yolu artık projeksiyondan ÖNCE
 * `MaviBelge_Core_Import_Record_Validator`'dan geçer (tek fail-closed
 * kayıt doğrulama katmanı) ve her hedef lookup'ı
 * `MaviBelge_Core_Import_Record_Validator::normalize_target_lookup()` ile
 * doğrular — bkz. o sınıfın docblock'u.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Dry_Run_Planner {

	const TYPE_SECTOR        = 'sector';
	const TYPE_QUALIFICATION = 'qualification';
	const TYPE_FEE           = 'fee';
	// Faz 7 içerik aktarımı: mb_haber / mb_referans.
	const TYPE_NEWS          = 'news';
	const TYPE_REFERENCE     = 'reference';
	// Faz 12b: SSS (mb_sss).
	const TYPE_FAQ           = 'faq';
	// Faz 12: WordPress çekirdek `page` (32 sayfa).
	const TYPE_PAGE          = 'page';

	/**
	 * Faz 6B1 Son Kapanış Düzeltmesi: hiçbir parametre artık `array` tip
	 * ipucu TAŞIMAZ — bağımsız incelemenin bulduğu üzere, önceki `array`
	 * ipucu, bu public sınıra scalar/null geçildiğinde
	 * `validate_manifest_shape()`'in kontrollü hatasına HİÇ ulaşmadan
	 * PHP'nin kendi ölümcül `TypeError`'ını fırlatıyordu. Artık üçü de
	 * `mixed` kabul edip İÇERİDE `is_array()` ile kontrollü sonuca
	 * çevriliyor.
	 *
	 * @param mixed $manifest {sectors: array[], qualifications: array[], fees: array[]} — Faz 6A `records` dizileri.
	 * @param mixed $targetLookups source_key => target lookup array (bkz. class docblock).
	 * @param mixed $dependencies Faz 6B1 Zorunlu Dependency DTO Kapanışı'nda
	 *   kapalı/zorunlu hale getirildi (bkz.
	 *   MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape()) —
	 *   üç anahtar da HER ZAMAN verilmiş olmalı, değeri `int` DEĞİL, kapalı
	 *   typed sonuç şekli taşır: {
	 *     @type array $sector_term_ids           sector_slug => array{id: int>0, type_verified: true}
	 *     @type array $sector_image_attachment_ids sector_slug => array{id: int>0, type_verified: true} (yalnız ÇÖZÜLMÜŞ olanlar için anahtar var)
	 *     @type array $qualification_post_ids     myk_code => array{id: int>0, type_verified: true}
	 * }
	 * @return array{entries: array, summary: array, errors: array}
	 */
	public static function plan( $manifest, $targetLookups, $dependencies ) {
		// §5 — üst-seviye manifest şekli fail-closed doğrulanır: eksik
		// anahtar/null/scalar/liste-olmayan değer/fazladan anahtar ->
		// TEK üst-seviye hata, hiçbir entry üretilmez (yarım plan yok).
		$shapeCheck = MaviBelge_Core_Import_Record_Validator::validate_manifest_shape( $manifest );
		if ( ! $shapeCheck['valid'] ) {
			return array( 'entries' => array(), 'summary' => self::empty_summary(), 'errors' => $shapeCheck['errors'] );
		}

		// §7/§5 — `targetLookups`/`dependencies` container'larının kendisi
		// dizi DEĞİLSE (ör. adapter yanlışlıkla null/string döndürdüyse)
		// public sınırda kontrollü, TEK bir üst-seviye hata ile durulur —
		// içerideki per-source_key erişimler asla bir `TypeError`/uyarı
		// üretemeyecek şekle ulaşmaz.
		if ( ! is_array( $targetLookups ) ) {
			return array( 'entries' => array(), 'summary' => self::empty_summary(), 'errors' => array( 'targetLookups bir dizi (object) değil; plan üretilmedi.' ) );
		}
		// Sözleşme Eşitleme §2.1 — `$dependencies` ÜST DTO'su artık raw
		// `is_array()` kontrolünden fazlasını görür: kapalı üç-anahtarlı
		// küme + üç anahtarın HEPSİNİN verilmiş olması (Faz 6B1 Zorunlu
		// Dependency DTO Kapanışı'nda mecburi hale getirildi) + her map
		// değerinin kapalı `{id,type_verified}` şeklinde olması, kayıtları
		// planlamadan ÖNCE fail-closed doğrulanır (bkz.
		// MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape()
		// docblock'u).
		$dependenciesCheck = MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape( $dependencies );
		if ( ! $dependenciesCheck['valid'] ) {
			return array( 'entries' => array(), 'summary' => self::empty_summary(), 'errors' => $dependenciesCheck['errors'] );
		}

		$errors  = array();
		$entries = array();

		$sectors        = $manifest['sectors'];
		$qualifications = $manifest['qualifications'];
		$fees           = $manifest['fees'];
		// Faz 7 — isteğe bağlı içerik listeleri (yoksa boş). `withContent`: manifest
		// içerik anahtarlarını AÇIKÇA taşıyorsa özet by_type'ı news/reference'ı da kapsar;
		// katalog aşamalarının çıktı şekli (by_type üç anahtar) DEĞİŞMEZ.
		$news        = isset( $manifest['news'] ) ? $manifest['news'] : array();
		$references  = isset( $manifest['references'] ) ? $manifest['references'] : array();
		$faqs        = isset( $manifest['faqs'] ) ? $manifest['faqs'] : array();
		$withContent = array_key_exists( 'news', $manifest ) || array_key_exists( 'references', $manifest ) || array_key_exists( 'faqs', $manifest );
		// Faz 12 — isteğe bağlı sayfa listesi; özetin by_type'ı yalnız `pages` anahtarı VARSA `page` taşır (katalog/içerik çıktısı DEĞİŞMEZ).
		$pages     = isset( $manifest['pages'] ) ? $manifest['pages'] : array();
		$withPages = array_key_exists( 'pages', $manifest );

		// §10.13 / §5 son madde — girişte tekrar eden VEYA biçimsiz/eksik
		// source_key fail-closed reddedilir (manifest zaten benzersiz
		// olmalı — bu, planlayıcının kendi güvenlik ağı; Faz 6A'nın
		// garantisine kör güvenmez; biçimsiz source_key'ler duplicate
		// taramasından KAÇMAZ — ayrıca raporlanır).
		$sourceKeyCheck = MaviBelge_Core_Import_Record_Validator::find_source_key_problems( array_merge( $sectors, $qualifications, $fees, $news, $references, $faqs, $pages ) );
		if ( ! empty( $sourceKeyCheck['duplicates'] ) ) {
			$errors[] = 'Girişte tekrar eden source_key bulundu, plan üretilmedi: ' . implode( ', ', $sourceKeyCheck['duplicates'] );
			return array( 'entries' => array(), 'summary' => self::empty_summary( $withContent, $withPages ), 'errors' => $errors );
		}
		if ( ! empty( $sourceKeyCheck['errors'] ) ) {
			$errors = array_merge( $errors, $sourceKeyCheck['errors'] );
		}

		// §2.2 — her tür listesi kendi içinde source_index/pozisyon ve
		// source.file/sha256 provenance tutarlılığı taşımalı. Tek bir
		// bozuklukta bile hiçbir uygulanabilir plan üretilmez (bkz.
		// find_source_key_problems() ile AYNI fail-closed desen —
		// `errors` doluysa `entries` boştur).
		$positionalErrors = array_merge(
			MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $sectors, 'sectors' ),
			MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $qualifications, 'qualifications' ),
			MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $fees, 'fees' ),
			MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $news, 'news' ),
			MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $references, 'references' ),
			MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $faqs, 'faqs' ),
			MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity( $pages, 'pages' )
		);
		if ( ! empty( $positionalErrors ) ) {
			return array( 'entries' => array(), 'summary' => self::empty_summary( $withContent, $withPages ), 'errors' => array_merge( $errors, $positionalErrors ) );
		}

		foreach ( $sectors as $record ) {
			$entries[] = self::plan_sector( $record, $targetLookups, $dependencies );
		}
		foreach ( $qualifications as $record ) {
			$entries[] = self::plan_qualification( $record, $targetLookups, $dependencies );
		}
		foreach ( $fees as $record ) {
			$entries[] = self::plan_fee( $record, $targetLookups, $dependencies );
		}
		foreach ( $news as $record ) {
			$entries[] = self::plan_news( $record, $targetLookups, $dependencies );
		}
		foreach ( $references as $record ) {
			$entries[] = self::plan_reference( $record, $targetLookups, $dependencies );
		}
		foreach ( $faqs as $record ) {
			$entries[] = self::plan_faq( $record, $targetLookups, $dependencies );
		}
		foreach ( $pages as $record ) {
			$entries[] = self::plan_page( $record, $targetLookups, $dependencies );
		}

		$summary = self::summarize( $entries, count( $sectors ) + count( $qualifications ) + count( $fees ) + count( $news ) + count( $references ) + count( $faqs ) + count( $pages ), $withContent, $withPages );

		return array( 'entries' => $entries, 'summary' => $summary, 'errors' => $errors );
	}

	/**
	 * @param mixed $targetLookups dizi DEĞİLSE kontrollü `invalid` (bkz. görev promptu §7).
	 * @param mixed $dependencies Sözleşme Eşitleme §2.1 — `plan()`'ın toplu
	 *   çağırdığı yoldan BAĞIMSIZ olarak, DOĞRUDAN çağrılan bu metot da
	 *   `validate_dependencies_shape()`'ten geçer; görselsiz bir sektöre
	 *   verilmiş bozuk `sector_image_attachment_ids` container'ı artık bu
	 *   metod hiç `image`/`hasImage` kontrolüne bakmadan, en baştan reddeder.
	 */
	public static function plan_sector( $record, $targetLookups, $dependencies ) {
		return self::plan_typed( self::TYPE_SECTOR, $record, $targetLookups, $dependencies );
	}

	/** @param mixed $targetLookups @param mixed $dependencies — Sözleşme Eşitleme §2.1, bkz. plan_sector() docblock'u. */
	public static function plan_qualification( $record, $targetLookups, $dependencies ) {
		return self::plan_typed( self::TYPE_QUALIFICATION, $record, $targetLookups, $dependencies );
	}

	/** @param mixed $targetLookups @param mixed $dependencies — Sözleşme Eşitleme §2.1, bkz. plan_sector() docblock'u. */
	public static function plan_fee( $record, $targetLookups, $dependencies ) {
		return self::plan_typed( self::TYPE_FEE, $record, $targetLookups, $dependencies );
	}

	/** @param mixed $targetLookups @param mixed $dependencies — Faz 7 haber; bkz. plan_sector() docblock'u. */
	public static function plan_news( $record, $targetLookups, $dependencies ) {
		return self::plan_typed( self::TYPE_NEWS, $record, $targetLookups, $dependencies );
	}

	/** @param mixed $targetLookups @param mixed $dependencies — Faz 7 referans; bkz. plan_sector() docblock'u. */
	public static function plan_reference( $record, $targetLookups, $dependencies ) {
		return self::plan_typed( self::TYPE_REFERENCE, $record, $targetLookups, $dependencies );
	}

	/** @param mixed $targetLookups @param mixed $dependencies — Faz 12b SSS; bkz. plan_sector() docblock'u. */
	public static function plan_faq( $record, $targetLookups, $dependencies ) {
		return self::plan_typed( self::TYPE_FAQ, $record, $targetLookups, $dependencies );
	}

	/** @param mixed $targetLookups @param mixed $dependencies — Faz 12 sayfa; bkz. plan_sector() docblock'u. */
	public static function plan_page( $record, $targetLookups, $dependencies ) {
		return self::plan_typed( self::TYPE_PAGE, $record, $targetLookups, $dependencies );
	}

	/**
	 * Faz 6B3 — apply katmanının yazacağı yönetilen alanlar, planlayıcının
	 * incoming_hash'i hesapladığı AYNI doğrulama + bağımlılık çözümü +
	 * projeksiyon yolundan (prepare_projection()) üretilir; ikinci bir
	 * doğrulama/projeksiyon sistemi yoktur. Çağıran ayrıca
	 * `Hash::hash( fields ) === entry.incoming_hash` eşitliğini doğrular.
	 *
	 * @param mixed $type
	 * @param mixed $record
	 * @param mixed $dependencies
	 * @return array{ok: bool, fields: array|null}
	 */
	public static function project_for_apply( $type, $record, $dependencies ) {
		if ( ! in_array( $type, MaviBelge_Core_Import_Managed_Fields::TYPES, true ) ) {
			return array( 'ok' => false, 'fields' => null );
		}
		$prep = self::prepare_projection( $type, $record, $dependencies );
		if ( ! $prep['valid'] || ! $prep['dependency_resolved'] || null === $prep['projection'] ) {
			return array( 'ok' => false, 'fields' => null );
		}
		return array( 'ok' => true, 'fields' => $prep['projection']['fields'] );
	}

	/** Üç tür için ortak plan yolu (Faz 6B3'te plan_*() gövdeleri buraya toplandı; davranış aynı). */
	private static function plan_typed( $type, $record, $targetLookups, $dependencies ) {
		if ( ! is_array( $targetLookups ) ) {
			return self::invalid_entry( $type, self::extract_source_key( $record ), array( 'targetLookups bir dizi (object) değil; kayıt güvenle planlanamaz.' ) );
		}
		$prep = self::prepare_projection( $type, $record, $dependencies );
		if ( ! $prep['valid'] ) {
			return self::invalid_entry( $type, self::extract_source_key( $record ), $prep['errors'] );
		}
		return self::build_entry( $type, $record['source_key'], $prep['projection'], $targetLookups, $prep['dependency_resolved'], $prep['unresolved'] );
	}

	/**
	 * TEK doğrulama + bağımlılık çözümü + projeksiyon noktası (plan ve apply
	 * AYNI yolu kullanır).
	 *
	 * @return array{valid: bool, errors?: string[], projection?: array|null, dependency_resolved?: bool, unresolved?: string[]}
	 */
	private static function prepare_projection( $type, $record, $dependencies ) {
		$dependenciesCheck = MaviBelge_Core_Import_Record_Validator::validate_dependencies_shape( $dependencies );
		if ( ! $dependenciesCheck['valid'] ) {
			return array( 'valid' => false, 'errors' => $dependenciesCheck['errors'] );
		}

		$resolvedDependencies   = array();
		$unresolvedDependencies = array();

		if ( self::TYPE_SECTOR === $type ) {
			$validation = MaviBelge_Core_Import_Record_Validator::validate_sector( $record );
			if ( ! $validation['valid'] ) {
				return array( 'valid' => false, 'errors' => $validation['errors'] );
			}
			$hasImage = '' !== $record['image'];
			if ( $hasImage ) {
				$imageAttachmentId = MaviBelge_Core_Import_Record_Validator::resolve_verified_dependency( $dependencies, 'sector_image_attachment_ids', $record['slug'] );
				if ( null !== $imageAttachmentId ) {
					$resolvedDependencies['image_attachment_id'] = $imageAttachmentId;
				} else {
					$unresolvedDependencies[] = 'image_attachment_id';
				}
			}
			$dependencyResolved = empty( $unresolvedDependencies );
			$projection         = $dependencyResolved ? MaviBelge_Core_Import_Managed_Fields::project_sector( $record, $resolvedDependencies ) : null;
		} elseif ( self::TYPE_QUALIFICATION === $type ) {
			$validation = MaviBelge_Core_Import_Record_Validator::validate_qualification( $record );
			if ( ! $validation['valid'] ) {
				return array( 'valid' => false, 'errors' => $validation['errors'] );
			}
			$sectorTermId = MaviBelge_Core_Import_Record_Validator::resolve_verified_dependency( $dependencies, 'sector_term_ids', $record['sector_slug'] );
			if ( null !== $sectorTermId ) {
				$resolvedDependencies['sector_term_id'] = $sectorTermId;
			} else {
				$unresolvedDependencies[] = 'sector_term_id';
			}
			$dependencyResolved = empty( $unresolvedDependencies );
			$projection         = $dependencyResolved ? MaviBelge_Core_Import_Managed_Fields::project_qualification( $record, $resolvedDependencies ) : null;
		} elseif ( self::TYPE_NEWS === $type ) {
			$validation = MaviBelge_Core_Import_Record_Validator::validate_news( $record );
			if ( ! $validation['valid'] ) {
				return array( 'valid' => false, 'errors' => $validation['errors'] );
			}
			// Haber türü terimi (mb_haber_turu) ÇÖZÜLMÜŞ ve doğrulanmış olmalı; çözülemezse
			// blocked_dependency — hiçbir "tahmini" tür yazılmaz.
			$newsTypeTermId = MaviBelge_Core_Import_Record_Validator::resolve_verified_dependency( $dependencies, 'news_type_term_ids', $record['news_type'] );
			if ( null !== $newsTypeTermId ) {
				$resolvedDependencies['news_type_term_id'] = $newsTypeTermId;
			} else {
				$unresolvedDependencies[] = 'news_type_term_id';
			}
			$dependencyResolved = empty( $unresolvedDependencies );
			$projection         = $dependencyResolved ? MaviBelge_Core_Import_Managed_Fields::project_news( $record, $resolvedDependencies ) : null;
		} elseif ( self::TYPE_PAGE === $type ) {
			$validation = MaviBelge_Core_Import_Record_Validator::validate_page( $record );
			if ( ! $validation['valid'] ) {
				return array( 'valid' => false, 'errors' => $validation['errors'] );
			}
			$dependencyResolved = true; // Sayfanın bağımlılığı yok (parent bu sürümde null).
			$projection         = MaviBelge_Core_Import_Managed_Fields::project_page( $record, $resolvedDependencies );
		} elseif ( self::TYPE_REFERENCE === $type ) {
			$validation = MaviBelge_Core_Import_Record_Validator::validate_reference( $record );
			if ( ! $validation['valid'] ) {
				return array( 'valid' => false, 'errors' => $validation['errors'] );
			}
			// Referansın çözülecek bağımlılığı yok: logo dosyası manifest yükleyicide (SHA-256/PNG/boyut) doğrulanır; attachment içe aktarımda
			// oluşturulur ve MEVCUT durumda logo_sha256 alanı olarak geri okunur.
			$dependencyResolved = true;
			$projection         = MaviBelge_Core_Import_Managed_Fields::project_reference( $record, $resolvedDependencies );
		} elseif ( self::TYPE_FAQ === $type ) {
			$validation = MaviBelge_Core_Import_Record_Validator::validate_faq( $record );
			if ( ! $validation['valid'] ) {
				return array( 'valid' => false, 'errors' => $validation['errors'] );
			}
			$dependencyResolved = true; // SSS'in bağımlılığı yok.
			$projection         = MaviBelge_Core_Import_Managed_Fields::project_faq( $record, $resolvedDependencies );
		} else {
			$validation = MaviBelge_Core_Import_Record_Validator::validate_fee( $record );
			if ( ! $validation['valid'] ) {
				return array( 'valid' => false, 'errors' => $validation['errors'] );
			}
			$qualificationCode = $record['qualification_code'];
			// §7 — 19 kodsuz ücret: kod boşsa HİÇBİR bağımlılık aranmaz, ilişki
			// her zaman 0'dır (tahmin/fuzzy eşleştirme yok). Kod DOLU ise
			// çözülemezse blocked_dependency — "çözülemeyen yeterlilikte
			// applicable sayılmaz" kuralı.
			if ( '' !== $qualificationCode ) {
				$qualificationPostId = MaviBelge_Core_Import_Record_Validator::resolve_verified_dependency( $dependencies, 'qualification_post_ids', $qualificationCode );
				if ( null !== $qualificationPostId ) {
					$resolvedDependencies['qualification_post_id'] = $qualificationPostId;
				} else {
					$unresolvedDependencies[] = 'qualification_post_id';
				}
			}
			$dependencyResolved = empty( $unresolvedDependencies );
			$projection         = $dependencyResolved ? MaviBelge_Core_Import_Managed_Fields::project_fee( $record, $resolvedDependencies, $validation['normalized_price_options'] ) : null;
		}

		return array(
			'valid'               => true,
			'projection'          => $projection,
			'dependency_resolved' => $dependencyResolved,
			'unresolved'          => $unresolvedDependencies,
		);
	}

	/**
	 * Shared final step for all three types: computes incoming_hash (if a
	 * projection exists), pulls the target lookup, computes
	 * current_managed_hash from the lookup's raw current field values
	 * using the SAME projection-shaped hash input, calls the decision
	 * classifier, and assembles one reportable plan entry (§8: source_key,
	 * type, decision, reason code, target ID only, hash short forms,
	 * CHANGED FIELD NAMES only — never full content values —, unresolved
	 * dependencies, warnings).
	 */
	private static function build_entry( $type, $sourceKey, $projection, array $targetLookups, $dependencyResolved, array $unresolvedDependencies ) {
		// Faz 6B1 Kabul Öncesi Nokta Düzeltmeleri §2.1 — `isset()` DEĞİL
		// `array_key_exists()` kullanılır: `targetLookups[$sourceKey] = null`
		// PHP'de `isset()` altında "anahtar yok" ile AYIRT EDİLEMEZ ve
		// sessizce boş diziye (= "hedef yok" = create adayı) çevrilebiliyordu
		// — bu, bağımsız incelemenin bulduğu somut bir açıktı. Artık:
		// - Anahtar HİÇ YOKSA açık, kanonik bir "not_found" DTO üretilir.
		// - Anahtar VARSA (değeri null/scalar/boş dizi dahil) ham değer
		//   OLDUĞU GİBİ `normalize_target_lookup()`'a verilir — o metod
		//   dizi-olmayanı VE eksik zorunlu anahtarı (`target_found`) kendi
		//   başına `invalid_target_state` olarak sınıflandırır (bkz. o
		//   metodun docblock'u) — sessizce "hedef yok" sayılmaz.
		if ( array_key_exists( $sourceKey, $targetLookups ) ) {
			$rawLookup = $targetLookups[ $sourceKey ];
		} else {
			$rawLookup = array( 'target_found' => false ); // kanonik not-found DTO
		}
		$lookup = MaviBelge_Core_Import_Record_Validator::normalize_target_lookup( $rawLookup, $type );

		$incomingHash = null;
		$changedFields = array();
		$hashException = false;
		if ( null !== $projection ) {
			try {
				$incomingHash = MaviBelge_Core_Import_Hash::hash( $projection['fields'] );
			} catch ( InvalidArgumentException $e ) {
				// İçgelen hash bile hesaplanamıyorsa planın TAMAMINI
				// kesmek yerine bu kaydı güvenli biçimde conflict/
				// invalid_target_state sayarız (görev promptu §7 son madde).
				$hashException = true;
			}
		}

		$currentManagedHash = null;
		if ( ! $hashException && $lookup['target_found'] && null !== $lookup['current_managed_fields'] ) {
			try {
				$currentManagedHash = MaviBelge_Core_Import_Hash::hash( $lookup['current_managed_fields'] );
			} catch ( InvalidArgumentException $e ) {
				$hashException = true;
			}
			if ( ! $hashException && null !== $projection ) {
				foreach ( $projection['fields'] as $fieldName => $incomingValue ) {
					$currentValue = array_key_exists( $fieldName, $lookup['current_managed_fields'] ) ? $lookup['current_managed_fields'][ $fieldName ] : null;
					if ( $currentValue !== $incomingValue ) {
						$changedFields[] = $fieldName;
					}
				}
			}
		}

		$decision = MaviBelge_Core_Import_Decision::classify(
			array(
				// Şekil geçersizliği (madde 9) bu noktaya HİÇ ulaşmaz —
				// invalid_entry() zorunlu manifest alanları eksikse
				// build_entry()'yi hiç çağırmadan INVALID döner. Buraya
				// gelindiğinde şekil zaten geçerlidir; dependency_resolved
				// (madde 8) ayrı, bağımsız bir kontrol.
				'valid_shape'            => true,
				'duplicate_targets'      => $lookup['duplicate_targets'],
				'target_state_valid'     => $lookup['target_state_valid'] && ! $hashException,
				'dependency_resolved'    => $dependencyResolved,
				'target_found'           => $lookup['target_found'],
				'target_type_matches'    => $lookup['target_type_matches'],
				'last_applied_hash'      => $lookup['last_applied_hash'],
				'current_managed_hash'   => $currentManagedHash,
				'incoming_hash'          => $incomingHash,
				'has_source_key_marker'  => $lookup['has_source_key_marker'],
				'natural_key'            => $lookup['natural_key'],
			)
		);

		return array(
			'source_key'              => $sourceKey,
			'type'                    => $type,
			'decision'                => $decision['decision'],
			'reason'                  => $decision['reason'],
			'message'                 => $decision['message'],
			'target_id'               => $lookup['target_id'],
			'incoming_hash'           => $incomingHash,
			'current_hash'            => $currentManagedHash,
			'last_applied_hash'       => $lookup['last_applied_hash'],
			'changed_fields'          => $changedFields,
			'unresolved_dependencies' => $unresolvedDependencies,
			'warnings'                => array(),
			// Faz 6B3 Önkoşul — doğal anahtar preflight sonucu (bkz.
			// MaviBelge_Core_Import_Record_Validator::NATURAL_KEY_STATES);
			// null = KONTROL EDİLMEDİ (hedef bulundu ya da adapter bilgi
			// vermedi). Apply uygunluğu create için 'none' şart koşar.
			'natural_key_check'       => $lookup['natural_key'],
		);
	}

	private static function invalid_entry( $type, $sourceKey, array $validationErrors = array() ) {
		$decision = MaviBelge_Core_Import_Decision::classify( array( 'valid_shape' => false ) );
		$warnings = array( 'Manifest kaydı beklenen zorunlu alanları taşımıyor.' );
		if ( ! empty( $validationErrors ) ) {
			$warnings = $validationErrors;
		}
		return array(
			'source_key'              => $sourceKey,
			'type'                    => $type,
			'decision'                => $decision['decision'],
			'reason'                  => $decision['reason'],
			'message'                 => $decision['message'],
			'target_id'               => null,
			'incoming_hash'           => null,
			'current_hash'            => null,
			'last_applied_hash'       => null,
			'changed_fields'          => array(),
			'unresolved_dependencies' => array(),
			'warnings'                => $warnings,
			'natural_key_check'       => null,
		);
	}

	private static function extract_source_key( $record ) {
		if ( is_array( $record ) && array_key_exists( 'source_key', $record ) && is_string( $record['source_key'] ) ) {
			return $record['source_key'];
		}
		return null;
	}

	private static function empty_summary( $withContent = false, $withPages = false ) {
		$byDecision = array();
		foreach ( MaviBelge_Core_Import_Decision::ALL_DECISIONS as $decision ) {
			$byDecision[ $decision ] = 0;
		}
		$byType = array( self::TYPE_SECTOR => 0, self::TYPE_QUALIFICATION => 0, self::TYPE_FEE => 0 );
		if ( $withContent ) {
			$byType[ self::TYPE_NEWS ]      = 0;
			$byType[ self::TYPE_REFERENCE ] = 0;
			$byType[ self::TYPE_FAQ ]       = 0;
		}
		if ( $withPages ) {
			$byType[ self::TYPE_PAGE ] = 0;
		}
		return array(
			'total'          => 0,
			'by_decision'    => $byDecision,
			'by_type'        => $byType,
			'operations'     => self::empty_operations(),
			'structurally_valid' => false,
			'applicable'     => false,
		);
	}

	private static function empty_operations() {
		return array( 'create' => 0, 'update' => 0, 'unchanged' => 0, 'conflict' => 0, 'blocked' => 0, 'invalid' => 0 );
	}

	/**
	 * §8 — bağlayıcı operasyon grupları: `by_decision`'ın ayrıntılı
	 * sayaçlarının YANINDA, decision değerlerini gruplayan bağlayıcı bir
	 * özet. `conflict` grubu generic conflict + duplicate + wrong-type
	 * hepsini KAPSAR. Bilinmeyen bir decision değeri (olmaması gerekir,
	 * ama savunma amaçlı) sessizce sayım dışı bırakılmaz — `applicable`
	 * false'a düşürülür.
	 */
	private static function operation_group_for_decision( $decision ) {
		switch ( $decision ) {
			case MaviBelge_Core_Import_Decision::CREATE:
				return 'create';
			case MaviBelge_Core_Import_Decision::UPDATE:
				return 'update';
			case MaviBelge_Core_Import_Decision::UNCHANGED:
				return 'unchanged';
			case MaviBelge_Core_Import_Decision::CONFLICT:
			case MaviBelge_Core_Import_Decision::CONFLICT_DUPLICATE_TARGET:
			case MaviBelge_Core_Import_Decision::CONFLICT_WRONG_TARGET_TYPE:
				return 'conflict';
			case MaviBelge_Core_Import_Decision::BLOCKED_DEPENDENCY:
				return 'blocked';
			case MaviBelge_Core_Import_Decision::INVALID:
				return 'invalid';
			default:
				return null;
		}
	}

	/**
	 * @param array $entries
	 * @param int   $expectedTotal giriş kayıt sayısı — özetin toplamı bununla BİREBİR eşit olmalı.
	 */
	private static function summarize( array $entries, $expectedTotal, $withContent = false, $withPages = false ) {
		$summary = self::empty_summary( $withContent, $withPages );
		$summary['total'] = count( $entries );
		$unknownDecision = false;
		$hasInvalid      = false;

		foreach ( $entries as $entry ) {
			if ( isset( $summary['by_decision'][ $entry['decision'] ] ) ) {
				++$summary['by_decision'][ $entry['decision'] ];
			} else {
				$unknownDecision = true;
			}
			if ( isset( $summary['by_type'][ $entry['type'] ] ) ) {
				++$summary['by_type'][ $entry['type'] ];
			}
			if ( MaviBelge_Core_Import_Decision::INVALID === $entry['decision'] ) {
				$hasInvalid = true;
			}
			$group = self::operation_group_for_decision( $entry['decision'] );
			if ( null === $group ) {
				$unknownDecision = true;
				continue;
			}
			++$summary['operations'][ $group ];
		}

		$summary['total_matches_input'] = ( $summary['total'] === (int) $expectedTotal );
		$summary['has_invalid']         = $hasInvalid;
		// `structurally_valid`: plan HESABI kendi içinde tutarlı mı — sayım
		// invariant'ları sağlanıyor, bilinmeyen bir decision sessizce
		// yutulmadı, hiçbir kayıt invalid değil. Bu, ESKİ `applicable`
		// tanımıydı — bağımsız incelemenin bulduğu üzere bu tanım
		// "uygulanabilir" ile "hesabı doğru" kavramlarını KARIŞTIRIYORDU:
		// yalnız conflict/blocked_dependency içeren, hiçbir invalid
		// TAŞIMAYAN bir plan da eskiden `applicable=true` sayılıyordu —
		// oysa Faz 6B3'ün apply katmanı böyle bir planı asla ÇALIŞTIRMAMALI.
		$summary['structurally_valid'] = $summary['total_matches_input'] && ! $unknownDecision && ! $hasInvalid;
		// `applicable` (Faz 6B1 Son Kapanış Düzeltmesi'nde YENİDEN
		// TANIMLANDI — fail-closed): plan yalnız hesabı doğruYSA VE
		// hiçbir conflict*/blocked_dependency/invalid kaydı YOKSA true —
		// yani yalnız create/update/unchanged kararları taşıyan, tamamen
		// temiz bir plan "uygulanabilir aday" sayılır. Faz 6B3 (bu
		// görevde yazılmadı) yalnız `applicable === true` iken çalışmalı.
		$summary['applicable'] = $summary['structurally_valid']
			&& 0 === $summary['operations']['conflict']
			&& 0 === $summary['operations']['blocked']
			&& 0 === $summary['operations']['invalid'];

		return $summary;
	}
}
