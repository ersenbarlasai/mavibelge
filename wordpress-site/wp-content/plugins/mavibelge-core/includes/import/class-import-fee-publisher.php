<?php
/**
 * Faz 15 — ücret YAYINI: `all` aşamasının TASLAK olarak oluşturduğu ücret kayıtlarını, ayrı ve açık onaylı bir işlemle
 * etkinleştirir (WordPress `publish` + `_mb_record_status = active`) ve yalnız bu aracın etkinleştirdiklerini geri alabilir.
 *
 * Neden ayrı: import (apply) ücreti HİÇBİR ZAMAN yayınlamaz. Bu sınıf yeni bir karar motoru DEĞİLDİR: kayıt durumu mevcut tek
 * karar motorundan (`Dry_Run_Service::run_stage()` / `observe_record()`), ziyaretçi görünürlüğü katalog servisinin AYNI
 * kuralından (`MaviBelge_Core_Catalog_Service::fee_publication_blocker()`, enjekte edilen çağrılabilir), kayıt bazlı yayın
 * kararı `data/content/fee-publication.manifest.json`'dan (tools/fees/build-approval-matrix.js üretir) gelir.
 *
 * Bir ücret YALNIZ şu koşulların TÜMÜ sağlanırsa etkinleştirilebilir ("ready"):
 *  - yayın kararı `publish` ve karar satırı ücret manifest kaydının kanonik özetine bağlı (kayıt değişmişse karar bayattır),
 *  - hedef kayıt import tarafından oluşturulmuş ve yönetilen alanları son uygulanan haliyle BİREBİR (karar `unchanged`;
 *    panelden değiştirilmiş kayıt `not_unchanged` olur ve ATLANIR — sessiz ezme yok),
 *  - şu anki durum `draft` + `_mb_record_status = draft` (işaret yok),
 *  - etkinleştirilse ziyaretçiye görünür olacak (aktif dönem, geçerlilik penceresi, kanonik fiyat listesi, gerçek sektör).
 * Bekletilen kararlı (ad farkı, aynı yeterliliğe iki ücret) kayıtlar `held_pending_decision` olarak görünür ve atlanır.
 *
 * Güvenlik: onay özeti (`plan_digest`) her satırın kimliğini/hash'ini/kararını ve yayınlanabilirlik durumunu kapsar (anlık yayın
 * durumunu KAPSAMAZ: etkinleştirme/geri alma ilerledikçe özet değişmez); ifadeler `UCRET YAYINLA <özet ilk 12>` ve
 * `UCRET YAYINDAN KALDIR <özet ilk 12>`; istek başına en çok 10 kayıt; her batch tek transaction (yeniden gözlem -> yazma ->
 * katı readback -> audit -> commit); `expected_remaining` çift tıklama/bayat isteği reddeder; çözülmemiş import run'ı varken
 * çalışmaz. Geri alma yalnız işareti bu aracın yazdığı değere eşit ve içeriği değişmemiş kayıtlarda yapılır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Fee_Publisher {

	/** İstek başına en çok işlenen ücret. */
	const BATCH_LIMIT = 10;

	const PLAN_SCHEMA   = 'mavibelge-fee-publish-plan/1';
	const MARKER_SCHEMA = 'mavibelge-fee-publication-marker/1';

	const MODE_ACTIVATE   = 'activate';
	const MODE_DEACTIVATE = 'deactivate';

	/** Satır nedenleri (kapalı küme). */
	const REASONS = array( 'ready', 'published', 'held_pending_decision', 'not_created', 'not_unchanged', 'not_publicly_valid', 'unexpected_status' );

	/** Bekletme nedeni => Türkçe etiket (tools/fees/build-approval-matrix.js HOLD_REASONS ile AYNI küme; statik test eşitliği doğrular). */
	const HOLD_REASONS = array(
		'pdf_mismatch'                 => 'PDF ile manifest arasında fark',
		'pdf_unreadable'               => 'PDF okunamadı',
		'name_differs_from_pdf'        => 'PDF adı ile manifest adı farklı — kamuya yazılacak ad kararı bekliyor',
		'duplicate_qualification_link' => 'Aynı yeterliliğe birden çok ücret bağlı — yeterlilik detayında gösterim kararı bekliyor',
	);

	/** Ücret yayınının ÇALIŞMA ANINDA okuduğu manifest dosyaları (dağıtım paketi bu listeyle birebir; statik test doğrular). */
	const REQUIRED_FILES = array( 'sectors.manifest.json', 'qualifications.manifest.json', 'fees.manifest.json', 'fee-publication.manifest.json' );

	/** Karar dosyasının sabit adı (hata metinlerinde). */
	const FEE_DECISIONS_FILE_LABEL = 'fee-publication.manifest.json';

	/** Önizleme hata ayrıntısında en çok gösterilen yükleme hatası (dosya adı + sabit metin; mutlak yol İÇERMEZ). */
	const MAX_LOAD_ERRORS = 10;

	/** Önizlemede listelenen en çok manifest dışı ücret kaydı. */
	const MAX_OUTSIDE_ROWS = 200;

	/** Yalnız sunucunun eklediği bekletme nedeni: karar satırı artık ücret kaydının içeriğine uymuyor. */
	const STALE_REASON = 'decision_stale';

	/** Yayın notu => Türkçe etiket (build-approval-matrix.js PUBLICATION_NOTES ile AYNI küme). */
	const PUBLICATION_NOTES = array(
		'vat_rule_institution_decision'         => 'KDV/basım: %20 KDV dahil, 1.500 TL belge basım ücreti hariç (kurum kararı)',
		'unlinked_listed_without_qualification' => 'Bağlantısız listelenir (MYK kodu kararı açık; detay bağlantısı üretilmez)',
	);

	/** Görünürlük ön kontrolü engel kodu => Türkçe etiket (MaviBelge_Core_Catalog_Service::fee_publication_blocker()). */
	const BLOCKER_LABELS = array(
		'no_active_period'        => 'Aktif tarife dönemi tanımlı değil',
		'period_mismatch'         => 'Kaydın tarife dönemi aktif dönemle eşleşmiyor',
		'outside_validity_window' => 'Bugün geçerlilik tarihleri dışında',
		'price_options_invalid'   => 'Fiyat seçenekleri geçersiz veya boş',
		'sector_unresolved'       => 'Sektör gerçek bir sektör terimine çözülmüyor',
		'not_a_fee'               => 'Kayıt bir ücret kaydı değil',
	);

	const DECISION_SCHEMA_VERSION = '1.0.0';
	const DECISION_RECORD_TYPE    = 'fee_publication';
	const DECISION_ENVELOPE_KEYS  = array( 'schema_version', 'record_type', 'count', 'decided_on', 'basis', 'vat_rule', 'records' );
	const DECISION_RECORD_KEYS    = array( 'source_index', 'source_key', 'record_sha256', 'decision', 'hold_reasons', 'notes' );
	const MAX_BASIS_LENGTH        = 2000;

	/** @var MaviBelge_Core_Import_Dry_Run_Service */
	private $dryRun;
	/** @var MaviBelge_Core_Import_Target_Writer */
	private $writer;
	/** @var MaviBelge_Core_Import_Transaction */
	private $tx;
	/** @var MaviBelge_Core_Import_Run_Store */
	private $store;
	/** @var MaviBelge_Core_Import_Audit_Sink */
	private $audit;
	/** @var callable (int $postId) => string|null görünürlük engel kodu */
	private $visibilityBlocker;
	/** @var callable () => array{ok: bool, document: array|null, errors: string[]} */
	private $decisionsLoader;
	/** @var callable|null () => array{total: int, truncated: bool, records: array[]} — MaviBelge_Core_Catalog_Service::fee_inventory() */
	private $inventory;

	public function __construct(
		MaviBelge_Core_Import_Dry_Run_Service $dryRun,
		MaviBelge_Core_Import_Target_Writer $writer,
		MaviBelge_Core_Import_Transaction $tx,
		MaviBelge_Core_Import_Run_Store $store,
		MaviBelge_Core_Import_Audit_Sink $audit,
		callable $visibilityBlocker,
		callable $decisionsLoader,
		callable $inventory = null
	) {
		$this->dryRun            = $dryRun;
		$this->writer            = $writer;
		$this->tx                = $tx;
		$this->store             = $store;
		$this->audit             = $audit;
		$this->visibilityBlocker = $visibilityBlocker;
		$this->decisionsLoader   = $decisionsLoader;
		$this->inventory         = $inventory;
	}

	/** Kullanıcının BİREBİR yazması gereken ifade (mod başına AYRI; apply/rollback/sayfa yayını ifadelerinden farklı). */
	public static function confirm_phrase( $mode, $digest ) {
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $digest ) ) {
			return null;
		}
		if ( self::MODE_ACTIVATE === $mode ) {
			return 'UCRET YAYINLA ' . substr( $digest, 0, 12 );
		}
		if ( self::MODE_DEACTIVATE === $mode ) {
			return 'UCRET YAYINDAN KALDIR ' . substr( $digest, 0, 12 );
		}
		return null;
	}

	/**
	 * Bu aracın bir kayda yazdığı yayın işareti: kaynak anahtarı + etkinleştirme anındaki içerik özeti. Geri alma yalnız aynı
	 * değeri taşıyan (araç etkinleştirmiş ve içerik o zamandan beri değişmemiş) kayıtlarda çalışır.
	 */
	public static function marker( $sourceKey, $incomingHash ) {
		return MaviBelge_Core_Import_Hash::hash( array( 'schema' => self::MARKER_SCHEMA, 'source_key' => (string) $sourceKey, 'incoming_hash' => (string) $incomingHash ) );
	}

	/**
	 * SAF — yayın kararı belgesinin kapalı doğrulaması ve ücret manifestine bağlanması. Herhangi bir sapma belgenin TAMAMINI
	 * reddeder (kısmi karar listesi kullanılmaz). Kayıt içeriği değişmişse ilgili satır `stale` işaretlenir (bekletilir).
	 *
	 * @param mixed $document Çözülmüş JSON belgesi.
	 * @param array $fees     Ücret manifest kayıtları (sırasıyla).
	 * @return array{ok: bool, error_code: string|null, rows: array<string, array>, digest: string|null, vat_rule: array|null}
	 */
	public static function validate_decisions( $document, array $fees ) {
		$fail = array( 'ok' => false, 'error_code' => 'decisions_invalid', 'rows' => array(), 'digest' => null, 'vat_rule' => null );
		if ( ! is_array( $document ) || ! self::has_exact_keys( $document, self::DECISION_ENVELOPE_KEYS ) ) {
			return $fail;
		}
		if ( self::DECISION_SCHEMA_VERSION !== $document['schema_version'] || self::DECISION_RECORD_TYPE !== $document['record_type'] ) {
			return $fail;
		}
		if ( ! is_string( $document['decided_on'] ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}\z/', $document['decided_on'] ) || ! MaviBelge_Core_Validator::is_valid_ymd_date( $document['decided_on'] ) ) {
			return $fail;
		}
		if ( ! is_string( $document['basis'] ) || '' === trim( $document['basis'] ) || strlen( $document['basis'] ) > self::MAX_BASIS_LENGTH ) {
			return $fail;
		}
		$vat = $document['vat_rule'];
		if ( ! is_array( $vat ) || ! self::has_exact_keys( $vat, array( 'included', 'rate_percent', 'certificate_print_fee_kurus' ) )
			|| ! is_bool( $vat['included'] ) || ! is_int( $vat['rate_percent'] ) || $vat['rate_percent'] < 0 || $vat['rate_percent'] > MaviBelge_Core_Meta_Schema::MAX_VAT_RATE_PERCENT
			|| ! is_int( $vat['certificate_print_fee_kurus'] ) || $vat['certificate_print_fee_kurus'] < 0 ) {
			return $fail;
		}
		$records = $document['records'];
		if ( ! is_array( $records ) || array_values( $records ) !== $records || ! is_int( $document['count'] ) || count( $records ) !== $document['count'] || count( $records ) !== count( $fees ) ) {
			return $fail;
		}
		$rows = array();
		foreach ( array_values( $fees ) as $index => $fee ) {
			$row = $records[ $index ];
			if ( ! is_array( $row ) || ! self::has_exact_keys( $row, self::DECISION_RECORD_KEYS ) || $index !== $row['source_index']
				|| ! is_array( $fee ) || ! isset( $fee['source_key'] ) || $fee['source_key'] !== $row['source_key'] || isset( $rows[ $row['source_key'] ] )
				|| ! is_string( $row['record_sha256'] ) || 1 !== preg_match( '/^[0-9a-f]{64}\z/', $row['record_sha256'] )
				|| ! in_array( $row['decision'], array( 'publish', 'hold' ), true )
				|| ! self::is_code_list( $row['hold_reasons'], array_keys( self::HOLD_REASONS ) ) || ! self::is_code_list( $row['notes'], array_keys( self::PUBLICATION_NOTES ) )
				|| ( 'hold' === $row['decision'] ) !== ( array() !== $row['hold_reasons'] ) ) {
				return $fail;
			}
			try {
				$current = MaviBelge_Core_Import_Hash::hash( $fee );
			} catch ( InvalidArgumentException $e ) {
				return $fail;
			}
			$row['stale']                 = ! hash_equals( $row['record_sha256'], $current );
			$rows[ $row['source_key'] ] = $row;
		}
		try {
			$digest = MaviBelge_Core_Import_Hash::hash( $document );
		} catch ( InvalidArgumentException $e ) {
			return $fail;
		}
		return array( 'ok' => true, 'error_code' => null, 'rows' => $rows, 'digest' => $digest, 'vat_rule' => $vat );
	}

	/**
	 * SALT OKUNUR yayın önizlemesi: her ücret için ad, seviye, sektör, kod, seçenek sayısı, yayın kararı, durum ve neden.
	 *
	 * @return array{ok: bool, error_code: string|null, plan_digest: string|null, rows: array[], summary: array, records: array}
	 */
	public function preview() {
		$ctx = $this->dryRun->run_stage( MaviBelge_Core_Import_Apply_Plan::STAGE_ALL );
		if ( ! $ctx['ok'] ) {
			// Katalog manifestleri (sectors/qualifications/fees) okunamadı veya doğrulanamadı: HİÇBİR yazma yapılmaz.
			return self::preview_failed( 'catalog_manifests_unavailable', isset( $ctx['load_errors'] ) && is_array( $ctx['load_errors'] ) ? $ctx['load_errors'] : array() );
		}
		if ( ! is_array( $ctx['manifest'] ) || ! isset( $ctx['manifest']['fees'] ) || ! is_array( $ctx['manifest']['fees'] ) || array() === $ctx['manifest']['fees'] ) {
			return self::preview_failed( 'plan_not_available' );
		}
		$loaded = call_user_func( $this->decisionsLoader );
		if ( ! is_array( $loaded ) || empty( $loaded['ok'] ) ) {
			return self::preview_failed( 'decisions_unavailable', is_array( $loaded ) && isset( $loaded['errors'] ) && is_array( $loaded['errors'] ) ? $loaded['errors'] : array() );
		}
		$decisions = self::validate_decisions( $loaded['document'], $ctx['manifest']['fees'] );
		if ( ! $decisions['ok'] ) {
			return self::preview_failed( 'decisions_invalid', array( self::FEE_DECISIONS_FILE_LABEL . ': şeması geçersiz veya ücret manifestiyle (fees.manifest.json) birebir eşleşmiyor.' ) );
		}
		$entries = array();
		foreach ( $ctx['plan']['entries'] as $entry ) {
			if ( isset( $entry['type'], $entry['source_key'] ) && 'fee' === $entry['type'] ) {
				$entries[ $entry['source_key'] ] = $entry;
			}
		}
		$rows      = array();
		$digestRow = array();
		$records   = array();
		foreach ( $ctx['manifest']['fees'] as $record ) {
			$key = $record['source_key'];
			if ( ! isset( $entries[ $key ] ) ) {
				return self::preview_failed( 'plan_not_available' );
			}
			$records[ $key ] = $record;
			$entry           = $entries[ $key ];
			$decision        = $decisions['rows'][ $key ];
			$view            = $this->classify( $record, $entry, $decision );
			$rows[]          = $view;
			// Özet, "yayınlanabilir mi" bilgisini taşır ama ANLIK yayın durumunu taşımaz (ready == published): ilerledikçe değişmez.
			$digestRow[] = array( $key, $view['target_id'], $entry['incoming_hash'], $view['decision'], in_array( $view['reason'], array( 'ready', 'published' ), true ) ? 'publishable' : $view['reason'] . ':' . (string) $view['blocker'] );
		}
		try {
			$digest = MaviBelge_Core_Import_Hash::hash( array( 'schema' => self::PLAN_SCHEMA, 'manifest' => $ctx['manifest_digest'], 'decisions' => $decisions['digest'], 'rows' => $digestRow ) );
		} catch ( InvalidArgumentException $e ) {
			return self::preview_failed( 'plan_not_available' );
		}
		$summary = self::empty_summary();
		foreach ( $rows as $row ) {
			++$summary['total'];
			++$summary[ $row['reason'] ];
			// Gerçek ortam farkı: karar "yayına uygun" ama şu an hazır/yayında değil -> nedeni ayrı sayılır.
			if ( 'publish' === $row['decision'] && ! in_array( $row['reason'], array( 'ready', 'published' ), true ) ) {
				++$summary['publish_gap'][ $row['reason'] ];
				if ( 'not_unchanged' === $row['reason'] ) {
					// Aktarım karar motorunun gerçek kararı (update = manifest yeni / conflict = panelden değişmiş / blocked|invalid = bağımlılık).
					$d = '' === $row['import_decision'] ? 'unknown' : $row['import_decision'];
					$summary['not_unchanged_by_import_decision'][ $d ] = isset( $summary['not_unchanged_by_import_decision'][ $d ] ) ? $summary['not_unchanged_by_import_decision'][ $d ] + 1 : 1;
				}
			}
			// Bekletilen (veya kararı bayat) ama ŞU AN yayında/aktif kayıt: araç dokunmaz, yönetici bilmeli.
			if ( 'hold' === $row['decision'] && 'publish' === $row['post_status'] && 'active' === $row['record_status'] ) {
				++$summary['held_but_published'];
			}
			if ( 'published' === $row['reason'] && $row['by_publisher'] ) {
				++$summary['deactivatable'];
			}
			if ( ! $row['linked'] ) {
				++$summary['unlinked'];
				if ( 'ready' === $row['reason'] || 'published' === $row['reason'] ) {
					++$summary['unlinked_publishable'];
				}
			}
			if ( 'publish' === $row['decision'] ) {
				++$summary['decision_publish'];
				$summary['options_publish'] += $row['option_count'];
			} else {
				++$summary['decision_hold'];
			}
			$summary['options_total'] += $row['option_count'];
		}
		return array( 'ok' => true, 'error_code' => null, 'load_errors' => array(), 'plan_digest' => $digest, 'rows' => $rows, 'summary' => $summary, 'records' => $records, 'vat_rule' => $decisions['vat_rule'], 'inventory' => $this->inventory_view( $records ) );
	}

	/**
	 * SALT OKUNUR — sitedeki bütün ücret kayıtları içinden manifest dışı olanlar (kaynak anahtarı yok veya manifestte olmayan anahtar).
	 * Bu kayıtlar ziyaretçiye görünüyorsa listede ek satır olarak çıkar; bu araç onlara DOKUNMAZ. Envanter verilmemişse null.
	 *
	 * @param array $records source_key => manifest kaydı
	 * @return array|null
	 */
	private function inventory_view( array $records ) {
		if ( null === $this->inventory ) {
			return null;
		}
		$inv = call_user_func( $this->inventory );
		if ( ! is_array( $inv ) || ! isset( $inv['records'] ) || ! is_array( $inv['records'] ) ) {
			return null;
		}
		$view = array( 'site_total' => 0, 'site_visible' => 0, 'truncated' => ! empty( $inv['truncated'] ), 'outside_manifest' => 0, 'outside_manifest_visible' => 0, 'outside_rows' => array() );
		foreach ( $inv['records'] as $rec ) {
			if ( ! is_array( $rec ) || ! isset( $rec['id'], $rec['source_key'] ) ) {
				continue;
			}
			$visible = ! empty( $rec['visible'] );
			++$view['site_total'];
			if ( $visible ) {
				++$view['site_visible'];
			}
			if ( '' !== (string) $rec['source_key'] && isset( $records[ (string) $rec['source_key'] ] ) ) {
				continue;
			}
			++$view['outside_manifest'];
			if ( $visible ) {
				++$view['outside_manifest_visible'];
			}
			if ( count( $view['outside_rows'] ) < self::MAX_OUTSIDE_ROWS ) {
				$view['outside_rows'][] = array(
					'id'             => (int) $rec['id'],
					'title'          => isset( $rec['title'] ) ? (string) $rec['title'] : '',
					'post_status'    => isset( $rec['post_status'] ) ? (string) $rec['post_status'] : '',
					'record_status'  => isset( $rec['record_status'] ) ? (string) $rec['record_status'] : '',
					'has_source_key' => '' !== (string) $rec['source_key'],
					'visible'        => $visible,
				);
			}
		}
		return $view;
	}

	/**
	 * En çok BATCH_LIMIT hazır ücreti etkinleştirir (tek transaction). Onay ifadesini çağıran katman (admin servisi) doğrular.
	 *
	 * @param mixed $confirmDigest     Kullanıcının önizlemede gördüğü plan_digest.
	 * @param mixed $expectedRemaining Kullanıcının gördüğü "etkinleştirilmeyi bekleyen hazır ücret" sayısı.
	 */
	public function activate( $confirmDigest, $expectedRemaining ) {
		return $this->run( self::MODE_ACTIVATE, $confirmDigest, $expectedRemaining );
	}

	/** En çok BATCH_LIMIT ücreti, yalnız bu aracın etkinleştirdikleri arasından, geri alır (taslağa döndürür). */
	public function deactivate( $confirmDigest, $expectedRemaining ) {
		return $this->run( self::MODE_DEACTIVATE, $confirmDigest, $expectedRemaining );
	}

	private function run( $mode, $confirmDigest, $expectedRemaining ) {
		if ( ! MaviBelge_Core_Import_Apply_Plan::is_digest( $confirmDigest ) || ! is_int( $expectedRemaining ) || $expectedRemaining < 0 ) {
			return self::result( false, 'rejected', 'invalid_confirmation', 0, 0, null );
		}
		$preflight = $this->tx->preflight();
		if ( ! is_array( $preflight ) || true !== $preflight['ok'] || ! $this->audit->ready() ) {
			return self::result( false, 'rejected', 'infrastructure_unavailable', 0, 0, $confirmDigest );
		}
		if ( ! $this->store->acquire_lock() ) {
			return self::result( false, 'rejected', 'locked', 0, 0, $confirmDigest );
		}
		try {
			if ( ! empty( $this->store->find_runs_in_status( MaviBelge_Core_Import_Run_State::BLOCKS_NEW_APPLY ) ) ) {
				return self::result( false, 'rejected', 'unresolved_run_exists', 0, 0, $confirmDigest );
			}
			$pv = $this->preview();
			if ( ! $pv['ok'] ) {
				return self::result( false, 'rejected', $pv['error_code'], 0, 0, $confirmDigest );
			}
			$candidates = array();
			foreach ( $pv['rows'] as $row ) {
				if ( self::MODE_ACTIVATE === $mode ? 'ready' === $row['reason'] : ( 'published' === $row['reason'] && $row['by_publisher'] ) ) {
					$candidates[] = $row;
				}
			}
			if ( $pv['plan_digest'] !== $confirmDigest ) {
				return self::result( false, 'rejected', 'confirmation_mismatch', 0, count( $candidates ), $confirmDigest );
			}
			if ( count( $candidates ) !== $expectedRemaining ) {
				return self::result( false, 'rejected', 'stale_request', 0, count( $candidates ), $confirmDigest );
			}
			if ( array() === $candidates ) {
				return self::result( true, 'completed', null, 0, 0, $confirmDigest );
			}
			return $this->run_batch( $mode, array_slice( $candidates, 0, self::BATCH_LIMIT ), count( $candidates ), $confirmDigest, $pv['records'] );
		} finally {
			$this->store->release_lock();
		}
	}

	private function run_batch( $mode, array $batch, $before, $digest, array $records ) {
		$activate = self::MODE_ACTIVATE === $mode;
		if ( true !== $this->tx->begin() ) {
			return self::result( false, 'failed', 'transaction_begin_failed', 0, $before, $digest );
		}
		$auditItems = array();
		$error      = null;
		foreach ( $batch as $row ) {
			$record = isset( $records[ $row['source_key'] ] ) ? $records[ $row['source_key'] ] : null;
			if ( null === $record ) {
				$error = 'toctou_drift';
				break;
			}
			// TOCTOU: yazmadan HEMEN önce aynı karar yoluyla yeniden gözle.
			$before_obs = $this->dryRun->observe_record( 'fee', $record );
			$id         = $before_obs['entry']['target_id'];
			$marker     = self::marker( $row['source_key'], $before_obs['entry']['incoming_hash'] );
			$state      = is_int( $id ) ? $this->writer->fee_publication_state( $id ) : null;
			$expected   = $activate ? array( 'post_status' => 'draft', 'record_status' => 'draft', 'marker' => '' ) : array( 'post_status' => 'publish', 'record_status' => 'active', 'marker' => $marker );
			if ( 'unchanged' !== $before_obs['entry']['decision'] || $id !== $row['target_id'] || $state !== $expected ) {
				$error = 'toctou_drift';
				break;
			}
			if ( $activate && null !== call_user_func( $this->visibilityBlocker, $id ) ) {
				$error = 'not_publicly_valid';
				break;
			}
			$res = $this->writer->set_fee_publication( $id, $activate, $marker );
			if ( ! is_array( $res ) || true !== $res['ok'] ) {
				$error = 'write_failed';
				break;
			}
			// Katı readback: durum beklenen, yönetilen alanlar hâlâ birebir (karar unchanged), etkinleştirmede görünürlük kuralı geçer.
			$after      = $this->dryRun->observe_record( 'fee', $record );
			$afterState = $this->writer->fee_publication_state( $id );
			$want       = $activate ? array( 'post_status' => 'publish', 'record_status' => 'active', 'marker' => $marker ) : array( 'post_status' => 'draft', 'record_status' => 'draft', 'marker' => '' );
			if ( 'unchanged' !== $after['entry']['decision'] || $after['entry']['target_id'] !== $id || $afterState !== $want
				|| ( $activate && null !== call_user_func( $this->visibilityBlocker, $id ) ) ) {
				$error = 'readback_mismatch';
				break;
			}
			$auditItems[] = array( 'source_key' => $row['source_key'], 'type' => 'fee', 'target_id' => $id, 'new_hash' => $after['entry']['incoming_hash'] );
		}
		$event = $activate ? MaviBelge_Core_Audit_Log::EVENT_IMPORT_FEES_ACTIVATED : MaviBelge_Core_Audit_Log::EVENT_IMPORT_FEES_DEACTIVATED;
		if ( null === $error && true !== $this->audit->record( $event, 0, array( 'items' => $auditItems ) ) ) {
			$error = 'audit_failed';
		}
		if ( null === $error && true !== $this->tx->commit() ) {
			$error = 'commit_failed';
		}
		if ( null !== $error ) {
			$rolledBack = true === $this->tx->rollback();
			return self::result( false, 'failed', $rolledBack ? $error : 'transaction_rollback_failed', 0, $before, $digest );
		}
		$remaining = $before - count( $batch );
		return self::result( true, $remaining > 0 ? 'paused' : 'completed', null, count( $batch ), $remaining, $digest );
	}

	/** Bir ücret satırının görünümü + nedeni (yazmaz). */
	private function classify( array $record, array $entry, array $decision ) {
		$id       = isset( $entry['target_id'] ) && is_int( $entry['target_id'] ) ? $entry['target_id'] : null;
		$state    = null === $id ? null : $this->writer->fee_publication_state( $id );
		$holds    = $decision['hold_reasons'];
		$blocker  = null;
		$byTool   = false;
		$isHold   = 'hold' === $decision['decision'] || ! empty( $decision['stale'] );
		if ( ! empty( $decision['stale'] ) ) {
			$holds[] = self::STALE_REASON;
		}
		if ( $isHold ) {
			$reason = 'held_pending_decision';
		} elseif ( null === $id || 'create' === $entry['decision'] ) {
			$reason = 'not_created';
		} elseif ( 'unchanged' !== $entry['decision'] ) {
			$reason = 'not_unchanged';
		} elseif ( null === $state ) {
			$reason = 'unexpected_status';
		} elseif ( 'draft' === $state['post_status'] && 'draft' === $state['record_status'] && '' === $state['marker'] ) {
			$blocker = call_user_func( $this->visibilityBlocker, $id );
			$reason  = null === $blocker ? 'ready' : 'not_publicly_valid';
		} elseif ( 'publish' === $state['post_status'] && 'active' === $state['record_status'] ) {
			$reason = 'published';
			$byTool = '' !== $state['marker'] && hash_equals( self::marker( $record['source_key'], $entry['incoming_hash'] ), $state['marker'] );
		} else {
			$reason = 'unexpected_status';
		}
		return array(
			'source_key'         => $record['source_key'],
			'profession_name'    => $record['profession_name'],
			'level'              => $record['level'],
			'sector_slug'        => $record['sector_slug'],
			'qualification_code' => $record['qualification_code'],
			'linked'             => '' !== $record['qualification_code'],
			'option_count'       => count( $record['price_options'] ),
			'decision'           => $isHold ? 'hold' : 'publish',
			'hold_reasons'       => $holds,
			'notes'              => $decision['notes'],
			'import_decision'    => isset( $entry['decision'] ) ? (string) $entry['decision'] : '',
			'post_status'        => null === $state ? null : $state['post_status'],
			'record_status'      => null === $state ? null : $state['record_status'],
			'reason'             => $reason,
			'blocker'            => $blocker,
			'by_publisher'       => $byTool,
			'target_id'          => $id,
		);
	}

	private static function is_code_list( $value, array $allowed ) {
		if ( ! is_array( $value ) || array_values( $value ) !== $value || count( array_unique( $value ) ) !== count( $value ) ) {
			return false;
		}
		foreach ( $value as $code ) {
			if ( ! is_string( $code ) || ! in_array( $code, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function has_exact_keys( array $arr, array $keys ) {
		$given = array_map( 'strval', array_keys( $arr ) );
		sort( $given, SORT_STRING );
		sort( $keys, SORT_STRING );
		return $given === $keys;
	}

	private static function empty_summary() {
		$summary = array( 'total' => 0 );
		foreach ( self::REASONS as $reason ) {
			$summary[ $reason ] = 0;
		}
		$gap = array();
		foreach ( array_diff( self::REASONS, array( 'ready', 'published', 'held_pending_decision' ) ) as $reason ) {
			$gap[ $reason ] = 0;
		}
		return array_merge( $summary, array( 'deactivatable' => 0, 'unlinked' => 0, 'unlinked_publishable' => 0, 'decision_publish' => 0, 'decision_hold' => 0, 'options_total' => 0, 'options_publish' => 0, 'publish_gap' => $gap, 'not_unchanged_by_import_decision' => array(), 'held_but_published' => 0 ) );
	}

	/** Yükleme hatalarının güvenli alt kümesi (yalnız string, en çok MAX_LOAD_ERRORS; yükleyici mutlak yol yazmaz). */
	private static function preview_failed( $code, array $loadErrors = array() ) {
		$safe = array();
		foreach ( $loadErrors as $err ) {
			if ( is_string( $err ) && '' !== $err && count( $safe ) < self::MAX_LOAD_ERRORS ) {
				$safe[] = $err;
			}
		}
		return array( 'ok' => false, 'error_code' => $code, 'load_errors' => $safe, 'plan_digest' => null, 'rows' => array(), 'records' => array(), 'summary' => self::empty_summary(), 'vat_rule' => null, 'inventory' => null );
	}

	private static function result( $ok, $status, $errorCode, $processed, $remaining, $digest ) {
		return array( 'ok' => $ok, 'status' => $status, 'error_code' => $errorCode, 'processed' => $processed, 'remaining' => $remaining, 'plan_digest' => $digest );
	}
}
