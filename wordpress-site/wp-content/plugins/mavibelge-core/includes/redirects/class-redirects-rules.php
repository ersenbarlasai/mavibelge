<?php
/**
 * Faz 9 — eski URL yönlendirme SÖZLEŞMESİ (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Kural şekli (kapalı): source (yol), target (iç yol; 410 için boş), status (301|302|410), origin
 * (verified|proposed), active (bool), note (kısa serbest metin). Kurallar bir SET olarak doğrulanır; tek bir hata
 * bütün seti reddeder (kısmi kaydetme yok). Reddedilenler: çakışan kaynak, kendine yönlendirme, döngü, ZİNCİR
 * (hedef başka bir aktif kuralın kaynağı), dış/mutlak hedef, korumalı yollar, 410'da hedef/301'de hedef yokluğu,
 * bozuk yol (.., kontrol karakteri, joker), aşırı uzunluk, küme büyüklüğü sınırı.
 * Toplu "her şeyi ana sayfaya" yönlendirmesi (hedef `/`) REDDEDİLİR (SEO/AIO kartı: ana sayfaya toplu yönlendirme yok).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Redirects_Rules {

	const MAX_RULES  = 2000;
	const MAX_PATH   = 300;
	const STATUSES   = array( 301, 302, 410 );
	const ORIGINS    = array( 'verified', 'proposed' );
	const RULE_KEYS  = array( 'source', 'target', 'status', 'origin', 'active', 'note' );

	/** Yönlendirilemeyen (korumalı) yol önekleri. */
	const PROTECTED_PREFIXES = array( '/wp-admin/', '/wp-login.php', '/wp-json/', '/wp-sitemap', '/robots.txt', '/xmlrpc.php' );

	/**
	 * Eski adresi (mutlak URL veya yol) karşılaştırma için normalleştirir: sorgu/parça atılır, yüzde-kodlama çözülür,
	 * küçük harf, çift eğik çizgiler tek, tek sonda eğik çizgi (dosya uzantılı yollar hariç). Geçersizse null.
	 */
	public static function normalize_path( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}
		$path = trim( $raw );
		if ( 1 === preg_match( '#^https?://#i', $path ) ) {
			$parts = parse_url( $path );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				return null;
			}
			$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		}
		$path = preg_replace( '/[?#].*$/', '', $path );
		$path = rawurldecode( $path );
		if ( '' === $path || '/' !== $path[0] || 1 === preg_match( '/[\x00-\x1F\x7F\s*<>"\'\\\\]/u', $path ) || false !== strpos( $path, '..' ) || strlen( $path ) > self::MAX_PATH ) {
			return null;
		}
		$path = strtolower( preg_replace( '#/{2,}#', '/', $path ) );
		if ( '/' !== substr( $path, -1 ) && false === strpos( basename( $path ), '.' ) ) {
			$path .= '/';
		}
		return $path;
	}

	/** @return string[] Bir tek kuralın hata kodları (boş = geçerli). */
	public static function validate_rule( $rule ) {
		if ( ! is_array( $rule ) ) {
			return array( 'rule_not_array' );
		}
		$errors = array();
		$extra  = array_diff( array_keys( $rule ), self::RULE_KEYS );
		$miss   = array_diff( self::RULE_KEYS, array_keys( $rule ) );
		if ( ! empty( $extra ) ) {
			$errors[] = 'unexpected_keys';
		}
		if ( ! empty( $miss ) ) {
			return array_merge( $errors, array( 'missing_keys' ) );
		}
		if ( null === self::normalize_path( $rule['source'] ) ) {
			$errors[] = 'source_invalid';
		} else {
			foreach ( self::PROTECTED_PREFIXES as $prefix ) {
				if ( 0 === strpos( self::normalize_path( $rule['source'] ), $prefix ) ) {
					$errors[] = 'source_protected';
				}
			}
			if ( '/' === self::normalize_path( $rule['source'] ) ) {
				$errors[] = 'source_is_home';
			}
		}
		if ( ! is_int( $rule['status'] ) || ! in_array( $rule['status'], self::STATUSES, true ) ) {
			$errors[] = 'status_invalid';
		}
		if ( ! is_string( $rule['origin'] ) || ! in_array( $rule['origin'], self::ORIGINS, true ) ) {
			$errors[] = 'origin_invalid';
		}
		if ( ! is_bool( $rule['active'] ) ) {
			$errors[] = 'active_invalid';
		}
		if ( ! is_string( $rule['note'] ) || strlen( $rule['note'] ) > 300 ) {
			$errors[] = 'note_invalid';
		}
		if ( 410 === $rule['status'] ) {
			if ( '' !== $rule['target'] ) {
				$errors[] = 'gone_must_have_empty_target';
			}
		} else {
			if ( ! is_string( $rule['target'] ) || '' === $rule['target'] || '/' !== $rule['target'][0] || 0 === strpos( $rule['target'], '//' ) || 1 === preg_match( '/[\x00-\x1F\x7F\s<>"\'\\\\]/u', $rule['target'] ) || strlen( $rule['target'] ) > self::MAX_PATH ) {
				$errors[] = 'target_invalid_or_external';
			} elseif ( '/' === self::normalize_path( $rule['target'] ) ) {
				$errors[] = 'target_is_home_bulk_redirect';
			}
		}
		return array_values( array_unique( $errors ) );
	}

	/**
	 * Kural KÜMESİ doğrulaması. AKTİF olmayan kurallar döngü/zincir denetimine girmez ama tek tek geçerli olmalı ve
	 * kaynak çakışması (aktiflik fark etmeksizin) yasaktır.
	 *
	 * @param mixed $rules
	 * @return array{valid: bool, errors: array<int, array{index: int|null, source: string|null, code: string}>}
	 */
	public static function validate_set( $rules ) {
		$errors = array();
		$add    = function ( $index, $source, $code ) use ( &$errors ) {
			$errors[] = array( 'index' => $index, 'source' => $source, 'code' => $code );
		};
		if ( ! is_array( $rules ) ) {
			return array( 'valid' => false, 'errors' => array( array( 'index' => null, 'source' => null, 'code' => 'set_not_array' ) ) );
		}
		if ( count( $rules ) > self::MAX_RULES ) {
			$add( null, null, 'too_many_rules' );
		}
		$bySource = array();
		foreach ( $rules as $i => $rule ) {
			$codes = self::validate_rule( $rule );
			$src   = is_array( $rule ) && isset( $rule['source'] ) ? self::normalize_path( $rule['source'] ) : null;
			foreach ( $codes as $code ) {
				$add( $i, $src, $code );
			}
			if ( empty( $codes ) && null !== $src ) {
				if ( isset( $bySource[ $src ] ) ) {
					$add( $i, $src, 'duplicate_source_conflict' );
				}
				$bySource[ $src ] = $i;
				$tgt = 410 === $rule['status'] ? null : self::normalize_path( $rule['target'] );
				if ( null !== $tgt && $tgt === $src ) {
					$add( $i, $src, 'self_redirect' );
				}
			}
		}
		// Döngü ve zincir: yalnız AKTİF, tek tek geçerli kurallar arasında.
		$active = array();
		foreach ( $rules as $i => $rule ) {
			if ( empty( self::validate_rule( $rule ) ) && true === $rule['active'] && 410 !== $rule['status'] ) {
				$active[ self::normalize_path( $rule['source'] ) ] = self::normalize_path( $rule['target'] );
			}
		}
		foreach ( $active as $src => $tgt ) {
			if ( isset( $active[ $tgt ] ) && $tgt !== $src ) {
				$loop = ( $active[ $tgt ] === $src );
				$add( null, $src, $loop ? 'redirect_loop' : 'redirect_chain' );
			}
		}
		// Aktif bir kuralın hedefi aktif bir 410 kuralının kaynağıysa da kırık zincirdir.
		$gone = array();
		foreach ( $rules as $rule ) {
			if ( empty( self::validate_rule( $rule ) ) && true === $rule['active'] && 410 === $rule['status'] ) {
				$gone[ self::normalize_path( $rule['source'] ) ] = true;
			}
		}
		foreach ( $active as $src => $tgt ) {
			if ( isset( $gone[ $tgt ] ) ) {
				$add( null, $src, 'target_is_gone' );
			}
		}
		return array( 'valid' => empty( $errors ), 'errors' => $errors );
	}

	/**
	 * Çalışma zamanı dizini: normalize edilmiş kaynak -> kural. Yalnız AKTİF kurallar; küme geçersizse boş dizin
	 * (fail-closed: bozuk küme hiçbir yönlendirme üretmez).
	 *
	 * @return array<string, array>
	 */
	public static function build_index( $rules ) {
		if ( ! self::validate_set( $rules )['valid'] ) {
			return array();
		}
		$index = array();
		foreach ( $rules as $rule ) {
			if ( true === $rule['active'] ) {
				$index[ self::normalize_path( $rule['source'] ) ] = $rule;
			}
		}
		return $index;
	}

	/** İstek yoluna uyan aktif kural veya null. */
	public static function match( array $index, $requestPath ) {
		$path = self::normalize_path( $requestPath );
		return null !== $path && isset( $index[ $path ] ) ? $index[ $path ] : null;
	}

	/** Kümenin deterministik özeti (apply onayı için): normalize edilmiş, sıralanmış kurallardan sha256. */
	public static function digest( array $rules ) {
		$norm = array();
		foreach ( $rules as $rule ) {
			$norm[] = array( self::normalize_path( $rule['source'] ), $rule['target'], $rule['status'], $rule['origin'], $rule['active'] );
		}
		usort(
			$norm,
			function ( $a, $b ) {
				return strcmp( (string) $a[0], (string) $b[0] );
			}
		);
		return hash( 'sha256', json_encode( $norm, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * DRY-RUN raporu: gelen kurallar ile mevcut kayıtlı kurallar karşılaştırılır. Hiçbir şey yazmaz.
	 *
	 * @param array         $incoming
	 * @param array         $existing
	 * @param callable|null $targetExists function ( string $normalizedTargetPath ): bool — hedef gerçekten var mı (WordPress'e bağlı; yoksa null: kontrol yok)
	 * @return array{applicable: bool, errors: array, summary: array<string,int>, entries: array[]}
	 */
	public static function dry_run( array $incoming, array $existing, $targetExists = null ) {
		$validation = self::validate_set( $incoming );
		$exBySrc    = array();
		foreach ( $existing as $rule ) {
			if ( is_array( $rule ) && isset( $rule['source'] ) && null !== self::normalize_path( $rule['source'] ) ) {
				$exBySrc[ self::normalize_path( $rule['source'] ) ] = $rule;
			}
		}
		$entries = array();
		$summary = array( 'create' => 0, 'update' => 0, 'unchanged' => 0, 'invalid' => 0, 'inactive_target_missing' => 0 );
		foreach ( $incoming as $i => $rule ) {
			$src = is_array( $rule ) && isset( $rule['source'] ) ? self::normalize_path( $rule['source'] ) : null;
			if ( ! empty( self::validate_rule( $rule ) ) ) {
				$summary['invalid']++;
				$entries[] = array( 'source' => $src, 'decision' => 'invalid', 'codes' => self::validate_rule( $rule ) );
				continue;
			}
			$decision = 'create';
			if ( isset( $exBySrc[ $src ] ) ) {
				$decision = self::same_rule( $exBySrc[ $src ], $rule ) ? 'unchanged' : 'update';
			}
			$note = null;
			if ( true === $rule['active'] && 410 !== $rule['status'] && is_callable( $targetExists ) && ! call_user_func( $targetExists, self::normalize_path( $rule['target'] ) ) ) {
				$note = 'inactive_target_missing'; // Hedef henüz yok: kural AKTİF uygulanmamalı (apply pasif yazar).
				$summary['inactive_target_missing']++;
			}
			$summary[ $decision ]++;
			$entries[] = array( 'source' => $src, 'decision' => $decision, 'note' => $note );
		}
		return array( 'applicable' => $validation['valid'], 'errors' => $validation['errors'], 'summary' => $summary, 'entries' => $entries );
	}

	private static function same_rule( array $a, array $b ) {
		foreach ( self::RULE_KEYS as $key ) {
			if ( ! array_key_exists( $key, $a ) || $a[ $key ] !== $b[ $key ] ) {
				return false;
			}
		}
		return true;
	}
}
