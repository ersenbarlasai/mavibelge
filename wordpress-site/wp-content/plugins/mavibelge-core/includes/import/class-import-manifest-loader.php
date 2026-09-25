<?php
/**
 * Faz 6B2 — güvenli, salt okunur manifest loader.
 *
 * Yalnız Faz 6A'nın ürettiği ÜÇ SABİT dosyayı okur:
 * sectors.manifest.json, qualifications.manifest.json, fees.manifest.json.
 * İstemciden (WP-CLI argümanı, admin form alanı, GET/POST) gelen HİÇBİR
 * dosya yolu, glob veya dosya adı kabul edilmez — dosya adları bu sınıfın
 * kendi `FILES` sabitinde gömülüdür, çağıran taraf yalnız temel dizini
 * (ve onu bile yalnız `MAVIBELGE_IMPORT_MANIFEST_DIR` SABİTİ üzerinden,
 * asla bir fonksiyon parametresiyle) etkileyebilir.
 *
 * Bu sınıf HİÇBİR WordPress fonksiyonu çağırmaz (yalnız çekirdek PHP:
 * `file_get_contents`/`json_decode`/`realpath`) — `tests/bootstrap.php`
 * ile WordPress olmadan tam test edilebilir. Node üretim doğrulayıcısının
 * (`tools/import/lib/validate-manifest-set.js`) tam alan/çapraz-alan
 * sözleşmesini burada YENİDEN YAZMAZ — bu sınıf yalnız ZARFI (üst-seviye
 * şekil: schema_version/record_type/count/source/records + dosyaya özel
 * `notes`/`counts`) ve GÜVENLİ OKUMA sınırını (path traversal, boyut,
 * UTF-8, JSON geçerliliği) doğrular. Kayıtların TAM alan sözleşmesi zaten
 * `MaviBelge_Core_Import_Record_Validator` + `MaviBelge_Core_Import_Dry_Run_Planner`
 * tarafından, kayıtlar planlanmadan hemen önce, ayrıca doğrulanır — bu iki
 * katman birbirinin YERİNE geçmez, İKİSİ DE gereklidir.
 *
 * Bu sınıf hiçbir dosya oluşturamaz/değiştiremez/silemez; cache, transient
 * veya log dosyası yazmaz — yalnız `file_get_contents()` ile okur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Manifest_Loader {

	/** record_type => sabit dosya adı. Başka hiçbir dosya adı kabul edilmez. */
	const FILES = array(
		'sector'        => 'sectors.manifest.json',
		'qualification' => 'qualifications.manifest.json',
		'fee'           => 'fees.manifest.json',
	);

	/**
	 * Faz 7 içerik manifestleri — katalog dosyalarından AYRI, YALNIZ `content` aşaması
	 * için yüklenir (`load_content()`); `load_all()` bu dosyalara HİÇ bakmaz.
	 */
	const CONTENT_FILES = array(
		'news'      => 'news.manifest.json',
		'reference' => 'references.manifest.json',
	);

	/** Güvenlik üst sınırı — gerçek manifestler bundan çok daha küçük. */
	const MAX_FILE_BYTES = 5242880; // 5 MiB.

	/** Her zarfta zorunlu olan ortak alt küme. */
	const ENVELOPE_REQUIRED_KEYS = array( 'schema_version', 'record_type', 'count', 'source', 'records' );

	/**
	 * Düzeltme ve Kabul §2.5 — dosya türüne özel ek alan artık yalnız
	 * "izin verilen" değil, HER dosya türü için ZORUNLU tek alan (kapalı
	 * politika: bunun dışında hiçbir üst-seviye alan kabul edilmez, VE bu
	 * alan eksikse de zarf reddedilir).
	 */
	const ENVELOPE_EXTRA_REQUIRED_KEY = array(
		'sector'        => 'notes',
		'qualification' => 'notes',
		'fee'           => 'counts',
		'news'          => 'notes',
		'reference'     => 'notes',
	);

	/** Faz 6A'nın sabit `counts` anahtar kümesi (fees.manifest.json) — hepsi nonnegative int olmalı. */
	const FEE_COUNTS_KEYS = array(
		'total', 'priceOptionsTotal', 'pricingSingle', 'pricingMulti',
		'multiOptionsTotal', 'feesWithCode', 'feesWithoutCode', 'linkedCount',
	);

	/** Zarfın `source.file`'ının eşleşmesi GEREKEN sabit Faz 6A kaynak yolu — tür başına tek doğru değer. */
	const EXPECTED_SOURCE_FILE = array(
		'sector'        => 'tanitim-site/assets/data/sectors.js',
		'qualification' => 'tanitim-site/assets/data/qualifications.js',
		'fee'           => 'tanitim-site/assets/data/fees.js',
		'news'          => 'tanitim-site/assets/data/news.js',
		'reference'     => 'tanitim-site/assets/data/references.js',
	);

	/**
	 * Üç manifesti de yükler. Herhangi biri başarısız olursa TÜMÜ reddedilir
	 * (fail-closed) — kısmi/tutarsız bir manifest seti asla planlayıcıya
	 * verilmez.
	 *
	 * @param string|null $baseDirOverride YALNIZ `tests/run.php`'nin birden
	 *   çok izole senaryoyu (bozuk zarf, path traversal, boyut aşımı vb.)
	 *   TEK bir PHP sürecinde test edebilmesi için vardır —
	 *   `MAVIBELGE_IMPORT_MANIFEST_DIR` bir PHP sabiti olduğundan yalnız BİR
	 *   KEZ tanımlanabilir, birden çok senaryoya yetmez. Gerçek Faz 6B2
	 *   üretim çağıranları (`MaviBelge_Core_Import_Dry_Run_Service`,
	 *   WP-CLI komutu, admin ekranı) bu parametreyi ASLA geçmez — hiçbiri
	 *   istemciden gelen bir dosya yolunu buraya taşımaz; üretimde TEK
	 *   yapılandırma noktası hâlâ `MAVIBELGE_IMPORT_MANIFEST_DIR` sabitidir
	 *   (bkz. base_dir()).
	 * @return array{
	 *   ok: bool,
	 *   manifest: array{sectors: array[], qualifications: array[], fees: array[]}|null,
	 *   errors: string[],
	 * }
	 */
	public static function load_all( $baseDirOverride = null ) {
		$errors  = array();
		$loaded  = array();

		foreach ( self::FILES as $type => $filename ) {
			$result = self::load_one( $type, $filename, $baseDirOverride );
			if ( ! $result['ok'] ) {
				$errors = array_merge( $errors, $result['errors'] );
				continue;
			}
			$loaded[ $type ] = $result['records'];
		}

		if ( ! empty( $errors ) ) {
			return array( 'ok' => false, 'manifest' => null, 'errors' => $errors );
		}

		return array(
			'ok'       => true,
			'manifest' => array(
				'sectors'        => $loaded['sector'],
				'qualifications' => $loaded['qualification'],
				'fees'           => $loaded['fee'],
			),
			'errors'   => array(),
		);
	}

	/**
	 * Faz 7 — iki içerik manifestini (haber, referans) yükler. `load_all()` ile AYNI güvenli
	 * okuma/zarf doğrulaması; herhangi biri başarısızsa TÜMÜ reddedilir (kısmi içerik manifesti yok).
	 *
	 * @param string|null $baseDirOverride Bkz. load_all().
	 * @return array{ok: bool, manifest: array{news: array[], references: array[]}|null, errors: string[]}
	 */
	public static function load_content( $baseDirOverride = null ) {
		$errors = array();
		$loaded = array();

		foreach ( self::CONTENT_FILES as $type => $filename ) {
			$result = self::load_one( $type, $filename, $baseDirOverride );
			if ( ! $result['ok'] ) {
				$errors = array_merge( $errors, $result['errors'] );
				continue;
			}
			$loaded[ $type ] = $result['records'];
		}

		if ( ! empty( $errors ) ) {
			return array( 'ok' => false, 'manifest' => null, 'errors' => $errors );
		}

		return array(
			'ok'       => true,
			'manifest' => array(
				'news'       => $loaded['news'],
				'references' => $loaded['reference'],
			),
			'errors'   => array(),
		);
	}

	/**
	 * Kaynak ağaçta varsayılan konum (bu dosyadan GÖRECELİ hesaplanır —
	 * `MAVIBELGE_CORE_PATH` sabitine bağımlı DEĞİLDİR, bu yüzden
	 * `tests/bootstrap.php`'nin WordPress'siz ortamında da güvenle
	 * çağrılabilir): `.../wordpress-site/data/content`. Üretimde gerekirse
	 * TEK yapılandırma noktası olan `MAVIBELGE_IMPORT_MANIFEST_DIR` sabitiyle
	 * açıkça değiştirilebilir.
	 */
	public static function base_dir() {
		if ( defined( 'MAVIBELGE_IMPORT_MANIFEST_DIR' ) && is_string( MAVIBELGE_IMPORT_MANIFEST_DIR ) && '' !== MAVIBELGE_IMPORT_MANIFEST_DIR ) {
			return MAVIBELGE_IMPORT_MANIFEST_DIR;
		}
		// __DIR__ = .../wordpress-site/wp-content/plugins/mavibelge-core/includes/import
		// — 5 seviye yukarısı wordpress-site kök dizinidir: import -> includes
		// -> mavibelge-core -> plugins -> wp-content -> wordpress-site.
		return dirname( __DIR__, 5 ) . '/data/content';
	}

	private static function load_one( $type, $filename, $baseDirOverride = null ) {
		$baseDir  = null !== $baseDirOverride ? $baseDirOverride : self::base_dir();
		$baseReal = realpath( $baseDir );
		if ( false === $baseReal || ! is_dir( $baseReal ) ) {
			// Düzeltme ve Kabul §2.5 madde 1 — hata metnine mutlak `$baseDir`
			// KONMAZ; admin/CLI çıktısına yalnız sabit hata kodu + izinli
			// dosya adı çıkabilir.
			return array( 'ok' => false, 'errors' => array( "{$filename}: manifest dizini bulunamadı veya erişilemiyor." ) );
		}

		// Yalnız sabit dosya adı ile birleştirilir — istemciden gelen hiçbir
		// yol bileşeni burada YOKTUR.
		$candidate = rtrim( $baseReal, '/\\' ) . DIRECTORY_SEPARATOR . $filename;
		$real      = realpath( $candidate );
		if ( false === $real ) {
			return array( 'ok' => false, 'errors' => array( "{$filename} bulunamadı." ) );
		}

		// realpath() SONRASI: çözülmüş gerçek yolun izin verilen temel
		// dizinin İÇİNDE kaldığını doğrula — symlink/`..` kaçışına karşı
		// savunma derinliği (sabit dosya adı zaten path traversal'a izin
		// vermez, ama temel dizinin kendisi bir symlink olabilir).
		$baseWithSep = rtrim( $baseReal, '/\\' ) . DIRECTORY_SEPARATOR;
		if ( 0 !== strpos( $real, $baseWithSep ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename} izin verilen dizinin dışında çözümlendi." ) );
		}

		if ( ! is_file( $real ) || ! is_readable( $real ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename} okunabilir bir dosya değil." ) );
		}

		$size = filesize( $real );
		if ( false === $size || 0 === $size ) {
			return array( 'ok' => false, 'errors' => array( "{$filename} boş." ) );
		}
		if ( $size > self::MAX_FILE_BYTES ) {
			return array( 'ok' => false, 'errors' => array( "{$filename} izin verilen boyutu aşıyor." ) );
		}

		// PHP hata/uyarı üretmeden okumak için @ ile bastırılır — sonuç zaten
		// açıkça kontrol edilir, sessiz fatal/uyarı riski yoktur.
		$raw = @file_get_contents( $real );
		if ( false === $raw ) {
			return array( 'ok' => false, 'errors' => array( "{$filename} okunamadı." ) );
		}
		if ( ! self::is_valid_utf8( $raw ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename} geçerli UTF-8 değil." ) );
		}

		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			// Hata mesajına dosya İÇERİĞİ veya mutlak sunucu yolu KONMAZ —
			// yalnız dosya adı.
			return array( 'ok' => false, 'errors' => array( "{$filename} geçerli JSON değil." ) );
		}

		return self::validate_envelope( $type, $filename, $decoded );
	}

	private static function validate_envelope( $type, $filename, array $decoded ) {
		$extraKey    = self::ENVELOPE_EXTRA_REQUIRED_KEY[ $type ];
		$allowedKeys = array_merge( self::ENVELOPE_REQUIRED_KEYS, array( $extraKey ) );
		$extra       = array_diff( array_keys( $decoded ), $allowedKeys );
		if ( ! empty( $extra ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: üst seviyesinde beklenmeyen alan(lar): " . implode( ', ', $extra ) . '.' ) );
		}

		foreach ( self::ENVELOPE_REQUIRED_KEYS as $key ) {
			if ( ! array_key_exists( $key, $decoded ) ) {
				return array( 'ok' => false, 'errors' => array( "{$filename}: {$key} eksik." ) );
			}
		}
		// Düzeltme ve Kabul §2.5 madde 2 — dosyaya özel alan artık YALNIZ
		// izin verilen değil, ZORUNLU (eksikse zarf reddedilir).
		if ( ! array_key_exists( $extraKey, $decoded ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: {$extraKey} eksik (bu dosya türünde zorunlu)." ) );
		}

		if ( ! is_string( $decoded['schema_version'] ) || '2.0.0' !== $decoded['schema_version'] ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: schema_version \"2.0.0\" olmalı." ) );
		}
		if ( ! is_string( $decoded['record_type'] ) || $type !== $decoded['record_type'] ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: record_type beklenenle (\"{$type}\") uyuşmuyor." ) );
		}
		if ( ! is_int( $decoded['count'] ) || $decoded['count'] < 0 ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: count negatif olmayan bir tam sayı olmalı." ) );
		}

		$sourceResult = self::validate_source_object( $filename, $type, $decoded['source'] );
		if ( ! $sourceResult['ok'] ) {
			return $sourceResult;
		}

		if ( ! is_array( $decoded['records'] ) || ! self::is_list_array( $decoded['records'] ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: records sıralı bir liste olmalı." ) );
		}
		if ( count( $decoded['records'] ) !== $decoded['count'] ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: count, records uzunluğuyla uyuşmuyor." ) );
		}

		// Düzeltme ve Kabul §2.5 madde 6 — zarf `source`, her kayıttaki
		// source provenance ile BİREBİR tutarlı olmalı. Tek uyuşmazlık
		// tüm dosyayı reddeder (kayıtlar arası tutarlılık zaten
		// MaviBelge_Core_Import_Record_Validator::check_batch_positional_integrity()
		// tarafından ayrıca kontrol edilir — bu, o kontrolün YERİNE değil,
		// EK olarak zarfın kendisiyle karşılaştırır).
		foreach ( $decoded['records'] as $index => $record ) {
			if ( ! is_array( $record ) || ! array_key_exists( 'source', $record ) || ! is_array( $record['source'] ) ) {
				continue; // Kayıt şekli zaten bozuk — kendi tip-doğrulayıcısında raporlanır.
			}
			$recordFile = array_key_exists( 'file', $record['source'] ) ? $record['source']['file'] : null;
			$recordSha  = array_key_exists( 'sha256', $record['source'] ) ? $record['source']['sha256'] : null;
			if ( $recordFile !== $decoded['source']['file'] || $recordSha !== $decoded['source']['sha256'] ) {
				return array( 'ok' => false, 'errors' => array( "{$filename}: records[{$index}].source, zarf source ile tutarsız (provenance drift)." ) );
			}
		}

		if ( 'fee' === $type ) {
			$countsResult = self::validate_fee_counts( $filename, $decoded['counts'] );
			if ( ! $countsResult['ok'] ) {
				return $countsResult;
			}
		} else {
			$notesResult = self::validate_notes( $filename, $decoded['notes'] );
			if ( ! $notesResult['ok'] ) {
				return $notesResult;
			}
		}

		// Kayıtlar filtrelenmeden, yeniden sıralanmadan ve source_index
		// değerleri değiştirilmeden OLDUĞU GİBİ döndürülür.
		return array( 'ok' => true, 'records' => $decoded['records'], 'errors' => array() );
	}

	/**
	 * Düzeltme ve Kabul §2.5 madde 5 — zarf `source`, yalnız `file` ve
	 * `sha256` anahtarlarını taşıyan KAPALI bir nesne olmalı; `file`
	 * beklenen sabit Faz 6A kaynak yoluyla BİREBİR eşleşmeli, `sha256` tam
	 * 64 küçük-harf hex olmalı.
	 */
	private static function validate_source_object( $filename, $type, $source ) {
		if ( ! is_array( $source ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: source bir nesne olmalı." ) );
		}
		$extra = array_diff( array_keys( $source ), array( 'file', 'sha256' ) );
		if ( ! empty( $extra ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: source içinde beklenmeyen alan(lar): " . implode( ', ', $extra ) . '.' ) );
		}
		if ( ! array_key_exists( 'file', $source ) || ! is_string( $source['file'] ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: source.file eksik veya string değil." ) );
		}
		if ( self::EXPECTED_SOURCE_FILE[ $type ] !== $source['file'] ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: source.file beklenen sabit kaynak yoluyla uyuşmuyor." ) );
		}
		if ( ! array_key_exists( 'sha256', $source ) || ! is_string( $source['sha256'] ) || ! preg_match( '/^[0-9a-f]{64}$/', $source['sha256'] ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: source.sha256 tam 64 küçük-harf hex karakter olmalı." ) );
		}
		return array( 'ok' => true, 'errors' => array() );
	}

	/** Düzeltme ve Kabul §2.5 madde 3 — `notes` gerçekten sıralı string listesi olmalı. */
	private static function validate_notes( $filename, $notes ) {
		if ( ! is_array( $notes ) || ! self::is_list_array( $notes ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: notes sıralı bir liste olmalı." ) );
		}
		foreach ( $notes as $index => $note ) {
			if ( ! is_string( $note ) ) {
				return array( 'ok' => false, 'errors' => array( "{$filename}: notes[{$index}] string değil." ) );
			}
		}
		return array( 'ok' => true, 'errors' => array() );
	}

	/**
	 * Düzeltme ve Kabul §2.5 madde 4 — `counts` mevcut Faz 6A ücret
	 * zarfındaki TAM anahtar kümesini, nonnegative integer değerleriyle
	 * taşımalı. Bu, sayaçları PHP'de kaynaktan YENİDEN HESAPLAYAN ikinci
	 * bir karmaşık doğrulayıcı DEĞİLDİR — yalnız zarf şeklinin kapalı ve
	 * eksiksiz olduğunu doğrular.
	 */
	private static function validate_fee_counts( $filename, $counts ) {
		if ( ! is_array( $counts ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: counts bir nesne olmalı." ) );
		}
		$extra = array_diff( array_keys( $counts ), self::FEE_COUNTS_KEYS );
		if ( ! empty( $extra ) ) {
			return array( 'ok' => false, 'errors' => array( "{$filename}: counts içinde beklenmeyen alan(lar): " . implode( ', ', $extra ) . '.' ) );
		}
		foreach ( self::FEE_COUNTS_KEYS as $key ) {
			if ( ! array_key_exists( $key, $counts ) || ! is_int( $counts[ $key ] ) || $counts[ $key ] < 0 ) {
				return array( 'ok' => false, 'errors' => array( "{$filename}: counts.{$key} eksik veya negatif olmayan bir tam sayı değil." ) );
			}
		}
		return array( 'ok' => true, 'errors' => array() );
	}

	private static function is_valid_utf8( $string ) {
		return '' === $string || 1 === preg_match( '//u', $string );
	}

	/** PHP 7.3-safe liste tespiti (bkz. class-import-hash.php'deki aynı kural). */
	private static function is_list_array( array $arr ) {
		$expected = 0;
		foreach ( $arr as $key => $unused_value ) {
			if ( $key !== $expected ) {
				return false;
			}
			++$expected;
		}
		return true;
	}
}
