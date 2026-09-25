<?php
/**
 * Faz 6B3 Önkoşul ve Yazma Güvenliği — SAF apply uygunluk, TOCTOU ve
 * rollback kayıt sözleşmesi.
 *
 * Bu sınıf HİÇBİR ŞEY YAZMAZ ve hiçbir WordPress fonksiyonu çağırmaz; yalnız
 * gelecekteki Faz 6B3 apply katmanının UYMAK ZORUNDA olduğu kararları
 * kodlanabilir ve test edilebilir biçimde ifade eder:
 *
 * - evaluate_plan(): bir dry-run planının (MaviBelge_Core_Import_Dry_Run_Planner::plan()
 *   çıktısı) BÜTÜN OLARAK yazma adayı olup olmadığı. Tek bir conflict/
 *   blocked/invalid kayıt ya da kanıtsız create BÜTÜN batch'i durdurur;
 *   `writes` yalnız batch tamamen uygunsa dolar (kısmi batch YOK).
 * - toctou_recheck(): planlama ile yazma arasındaki durum değişikliğini,
 *   yazmadan HEMEN ÖNCE yeniden gözlenen durumla karşılaştırır; en küçük
 *   sapmada yazma yapılmaz.
 * - build_rollback_record() / rollback_allowed(): gelecekteki audit/rollback
 *   kaydının kapalı şekli ve kullanıcının import sonrası değişikliklerinin
 *   körlemesine ezilmemesi kuralı.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Apply_Eligibility {

	/** Yazma adayı kararlar. */
	const WRITE_DECISIONS = array( 'create', 'update' );

	/** No-op kararlar (yazma yok, audit yok). */
	const NOOP_DECISIONS = array( 'unchanged' );

	/** Rollback kaydının şema sürümü. */
	const ROLLBACK_SCHEMA = 'mavibelge-import-rollback/2';

	/**
	 * @param mixed $plan
	 * @return array{eligible: bool, errors: string[], writes: array, noops: string[]}
	 */
	public static function evaluate_plan( $plan ) {
		$errors = array();
		if ( ! is_array( $plan ) || ! isset( $plan['entries'], $plan['summary'], $plan['errors'] ) || ! is_array( $plan['entries'] ) || ! is_array( $plan['summary'] ) || ! is_array( $plan['errors'] ) ) {
			return self::not_eligible( array( 'plan şekli geçersiz.' ) );
		}
		if ( ! empty( $plan['errors'] ) ) {
			$errors[] = 'plan girdi düzeyinde hata taşıyor.';
		}
		if ( ! array_key_exists( 'applicable', $plan['summary'] ) || true !== $plan['summary']['applicable'] ) {
			$errors[] = 'summary.applicable === true değil.';
		}
		if ( ! array_key_exists( 'total', $plan['summary'] ) || count( $plan['entries'] ) !== $plan['summary']['total'] ) {
			$errors[] = 'entries sayısı summary.total ile eşleşmiyor.';
		}

		$writes = array();
		$noops  = array();
		$seen   = array();
		foreach ( $plan['entries'] as $i => $entry ) {
			$entryError = self::entry_error( $entry );
			if ( null !== $entryError ) {
				$key      = is_array( $entry ) && isset( $entry['source_key'] ) && is_string( $entry['source_key'] ) ? $entry['source_key'] : '#' . $i;
				$errors[] = $key . ': ' . $entryError;
				continue;
			}
			if ( isset( $seen[ $entry['source_key'] ] ) ) {
				$errors[] = $entry['source_key'] . ': batch içinde tekrar ediyor.';
				continue;
			}
			$seen[ $entry['source_key'] ] = true;
			if ( in_array( $entry['decision'], self::NOOP_DECISIONS, true ) ) {
				$noops[] = $entry['source_key'];
				continue;
			}
			$writes[] = array(
				'source_key'                 => $entry['source_key'],
				'type'                       => $entry['type'],
				'decision'                   => $entry['decision'],
				'target_id'                  => $entry['target_id'],
				'expected_incoming_hash'     => $entry['incoming_hash'],
				'expected_current_hash'      => $entry['current_hash'],
				'expected_last_applied_hash' => $entry['last_applied_hash'],
			);
		}

		if ( ! empty( $errors ) ) {
			return self::not_eligible( $errors ); // Kısmi batch YOK: writes/noops boş döner.
		}
		return array( 'eligible' => true, 'errors' => array(), 'writes' => $writes, 'noops' => $noops );
	}

	/** @return string|null Kaydın yazma/no-op uygunluk ihlali, yoksa null. */
	private static function entry_error( $e ) {
		if ( ! is_array( $e ) || ! isset( $e['source_key'], $e['type'], $e['decision'] ) || ! is_string( $e['source_key'] ) ) {
			return 'kayıt şekli geçersiz.';
		}
		if ( ! in_array( $e['type'], MaviBelge_Core_Import_Managed_Fields::TYPES, true ) ) {
			return 'bilinmeyen tür.';
		}
		if ( MaviBelge_Core_Validator::IMPORT_KEY_VALID !== MaviBelge_Core_Validator::classify_import_source_key( $e['source_key'], $e['type'] ) ) {
			return 'source_key biçimi geçersiz.';
		}
		$decision = $e['decision'];
		if ( ! in_array( $decision, self::WRITE_DECISIONS, true ) && ! in_array( $decision, self::NOOP_DECISIONS, true ) ) {
			return "karar '{$decision}' hiçbir zaman yazılmaz; batch durduruldu.";
		}
		$incoming = self::hash_or_null( $e, 'incoming_hash' );
		$current  = self::hash_or_null( $e, 'current_hash' );
		$last     = self::hash_or_null( $e, 'last_applied_hash' );
		if ( null === $incoming ) {
			return 'incoming_hash geçersiz.';
		}
		$targetId = array_key_exists( 'target_id', $e ) ? $e['target_id'] : null;
		switch ( $decision ) {
			case 'create':
				if ( ! array_key_exists( 'natural_key_check', $e ) || 'none' !== $e['natural_key_check'] ) {
					return "create için doğal anahtar preflight'ı 'none' ile KANITLANMALI (kontrol edilmemiş create yazılmaz).";
				}
				if ( null !== $targetId || null !== $current || null !== $last ) {
					return 'create kaydı hedef/hash taşımamalı.';
				}
				return null;
			case 'update':
				if ( ! is_int( $targetId ) || $targetId <= 0 || null === $current || null === $last ) {
					return 'update kaydı hedef ID ve mevcut/son hash taşımalı.';
				}
				if ( $current !== $last || $incoming === $last ) {
					return 'update yalnız current===last VE incoming!==last iken güvenlidir.';
				}
				if ( empty( $e['changed_fields'] ) || ! is_array( $e['changed_fields'] ) ) {
					return 'update kaydı değişen alan listesi taşımalı.';
				}
				return null;
			default: // unchanged
				if ( ! is_int( $targetId ) || $targetId <= 0 || $incoming !== $current || $current !== $last ) {
					return 'unchanged kaydı incoming===current===last taşımalı.';
				}
				return null;
		}
	}

	private static function hash_or_null( array $e, $key ) {
		if ( ! array_key_exists( $key, $e ) || ! is_string( $e[ $key ] ) || 1 !== preg_match( '/^[0-9a-f]{64}\z/', $e[ $key ] ) ) {
			return null;
		}
		return $e[ $key ];
	}

	/**
	 * TOCTOU: yazmadan HEMEN ÖNCE gözlenen durum planlanan durumla aynı mı?
	 *
	 * @param array $write    evaluate_plan()['writes'] öğesi.
	 * @param mixed $observed {target_found:bool, target_id:int|null, current_hash:string|null,
	 *                         last_applied_hash:string|null, natural_key:string|null, incoming_hash:string}
	 * @return array{ok: bool, reason: string}
	 */
	public static function toctou_recheck( array $write, $observed ) {
		if ( ! is_array( $observed ) || ! array_key_exists( 'target_found', $observed ) || ! is_bool( $observed['target_found'] ) ) {
			return array( 'ok' => false, 'reason' => 'gözlenen durum şekli geçersiz' );
		}
		if ( ! isset( $observed['incoming_hash'] ) || $observed['incoming_hash'] !== $write['expected_incoming_hash'] ) {
			return array( 'ok' => false, 'reason' => 'incoming_hash değişti (manifest/projeksiyon planlamadan sonra değişti)' );
		}
		if ( 'create' === $write['decision'] ) {
			if ( false !== $observed['target_found'] ) {
				return array( 'ok' => false, 'reason' => 'create planlandıktan sonra marker ile bir hedef belirdi' );
			}
			if ( ! array_key_exists( 'natural_key', $observed ) || 'none' !== $observed['natural_key'] ) {
				return array( 'ok' => false, 'reason' => 'create planlandıktan sonra doğal anahtar artık boş değil' );
			}
			return array( 'ok' => true, 'reason' => 'ok' );
		}
		if ( true !== $observed['target_found'] || ! isset( $observed['target_id'] ) || $observed['target_id'] !== $write['target_id'] ) {
			return array( 'ok' => false, 'reason' => 'hedef kayıt değişti veya kayboldu' );
		}
		if ( ! array_key_exists( 'current_hash', $observed ) || $observed['current_hash'] !== $write['expected_current_hash'] ) {
			return array( 'ok' => false, 'reason' => 'hedefin mevcut yönetilen alanları planlamadan sonra değişti' );
		}
		if ( ! array_key_exists( 'last_applied_hash', $observed ) || $observed['last_applied_hash'] !== $write['expected_last_applied_hash'] ) {
			return array( 'ok' => false, 'reason' => 'hedefin son uygulanan hash\'i planlamadan sonra değişti' );
		}
		return array( 'ok' => true, 'reason' => 'ok' );
	}

	/** Kapalı rollback kaydının TAM anahtar kümesi (fazlası/eksiği reddedilir). */
	const ROLLBACK_KEYS = array( 'schema', 'source_key', 'type', 'decision', 'target_id', 'old_hash', 'new_hash', 'old_fields', 'new_fields', 'changed_fields', 'unmanaged_fingerprint' );

	/**
	 * Gelecekteki audit/rollback kaydının KAPALI şekli. Yalnız import
	 * tarafından yönetilen alanlar (türün allowlist'i) taşınır; kişisel veri
	 * alanı tanımlı değildir, fazladan anahtar reddedilir.
	 *
	 * Son Kabul Düzeltmesi — kök neden: eski sürüm hash'lerin yalnız BİÇİMİNE
	 * bakıyordu; alanlarla ilgisiz 'aaaa…'/'bbbb…' hash'leri içeren kayıt
	 * geçerli sayılıyordu (Codex runtime kanıtı). Artık kayıt kurulur ve TEK
	 * doğrulayıcı validate_rollback_record()'dan geçmezse null döner: old/new
	 * hash'ler alanlardan YENİDEN hesaplanır, changed_fields deterministik
	 * olarak üretilir. Çağıranın hash'ine yalnız biçimi doğru diye güvenilmez.
	 *
	 * Faz 6B3 Düzeltme: create kaydı, create İŞLEMİNDEN HEMEN SONRA alınan
	 * yönetilmeyen-durum parmak izini (`unmanaged_fingerprint`, yalnız
	 * 64-hex hash; ham içerik/kişisel veri YOK) zorunlu taşır; update
	 * kaydında bu alan null'dır (update rollback'i yönetilmeyen alanlara
	 * zaten dokunmaz).
	 *
	 * @return array|null Geçersiz girdide null.
	 */
	public static function build_rollback_record( $type, $sourceKey, $targetId, $decision, $oldFields, $newFields, $oldHash, $newHash, $unmanagedFingerprint = null ) {
		$changed = self::expected_changed_fields( $type, $oldFields, $newFields );
		if ( null === $changed ) {
			return null;
		}
		$record = array(
			'schema'         => self::ROLLBACK_SCHEMA,
			'source_key'     => $sourceKey,
			'type'           => $type,
			'decision'       => $decision,
			'target_id'      => $targetId,
			'old_hash'       => $oldHash,
			'new_hash'       => $newHash,
			'old_fields'     => $oldFields,
			'new_fields'     => $newFields,
			'changed_fields' => $changed,
			'unmanaged_fingerprint' => $unmanagedFingerprint,
		);
		return self::validate_rollback_record( $record ) ? $record : null;
	}

	/**
	 * Son Kabul Düzeltmesi — kapalı rollback kaydı için TEK doğrulayıcı.
	 * - gerçek dizi; anahtar kümesi tam ROLLBACK_KEYS (eksik/fazla -> ret)
	 * - schema tam ROLLBACK_SCHEMA; type/decision/source_key/target_id geçerli
	 * - new_fields türün TAM, geçerli managed-field kümesi; new_hash === hash(new_fields)
	 * - create: old_fields === null VE old_hash === null VE unmanaged_fingerprint 64-hex
	 * - update: unmanaged_fingerprint === null; old_fields geçerli; old_hash === hash(old_fields); old_hash !== new_hash
	 * - changed_fields: string listesi, tekrar yok, allowlist içinde ve old/new
	 *   farkının deterministik listesiyle (allowlist sırası) BİREBİR aynı;
	 *   update'te boş olamaz
	 * Hash hesaplanamazsa fail-closed false.
	 *
	 * @param mixed $record
	 * @return bool
	 */
	public static function validate_rollback_record( $record ) {
		if ( ! is_array( $record ) ) {
			return false;
		}
		$keys     = array_keys( $record );
		$expected = self::ROLLBACK_KEYS;
		sort( $keys );
		sort( $expected );
		if ( $keys !== $expected ) {
			return false;
		}
		if ( self::ROLLBACK_SCHEMA !== $record['schema'] ) {
			return false;
		}
		$type = $record['type'];
		if ( ! in_array( $type, MaviBelge_Core_Import_Managed_Fields::TYPES, true ) || ! in_array( $record['decision'], self::WRITE_DECISIONS, true ) ) {
			return false;
		}
		if ( MaviBelge_Core_Validator::IMPORT_KEY_VALID !== MaviBelge_Core_Validator::classify_import_source_key( $record['source_key'], $type ) ) {
			return false;
		}
		if ( ! is_int( $record['target_id'] ) || $record['target_id'] <= 0 ) {
			return false;
		}
		if ( ! MaviBelge_Core_Import_Record_Validator::is_valid_managed_field_set( $type, $record['new_fields'] ) ) {
			return false;
		}
		$newHash = self::safe_hash( $record['new_fields'] );
		if ( null === $newHash || ! is_string( $record['new_hash'] ) || $newHash !== $record['new_hash'] ) {
			return false;
		}
		if ( 'create' === $record['decision'] ) {
			if ( null !== $record['old_fields'] || null !== $record['old_hash'] ) {
				return false;
			}
			if ( ! is_string( $record['unmanaged_fingerprint'] ) || 1 !== preg_match( '/^[0-9a-f]{64}\z/', $record['unmanaged_fingerprint'] ) ) {
				return false; // Create rollback'i kullanıcı değişikliğini parmak izi olmadan koruyamaz.
			}
		} else {
			if ( null !== $record['unmanaged_fingerprint'] ) {
				return false;
			}
			if ( ! MaviBelge_Core_Import_Record_Validator::is_valid_managed_field_set( $type, $record['old_fields'] ) ) {
				return false;
			}
			$oldHash = self::safe_hash( $record['old_fields'] );
			if ( null === $oldHash || ! is_string( $record['old_hash'] ) || $oldHash !== $record['old_hash'] || $oldHash === $newHash ) {
				return false;
			}
		}
		$changed = self::expected_changed_fields( $type, $record['old_fields'], $record['new_fields'] );
		if ( null === $changed || ! is_array( $record['changed_fields'] ) || $record['changed_fields'] !== $changed ) {
			return false; // Tip/liste/tekrar/allowlist/sıra ihlalleri bu katı eşitlikte yakalanır.
		}
		if ( 'update' === $record['decision'] && array() === $changed ) {
			return false;
		}
		return true;
	}

	/**
	 * Rollback yalnız kayıt validate_rollback_record()'dan TAMAMEN geçerse VE
	 * hedefin ŞU ANKİ yönetilen alan hash'i import'un yazdığı new_hash'e hâlâ
	 * eşitse izinlidir — kullanıcı import sonrası alanları değiştirdiyse
	 * rollback onların üzerine körlemesine YAZMAZ. Eksik/bozuk kayıt (ör.
	 * yalnız schema + new_hash) current hash eşleşse bile reddedilir.
	 */
	public static function rollback_allowed( $record, $currentHashNow ) {
		if ( ! self::validate_rollback_record( $record ) ) {
			return false;
		}
		return is_string( $currentHashNow ) && $currentHashNow === $record['new_hash'];
	}

	/**
	 * Deterministik changed_fields: türün allowlist SIRASIYLA, old/new
	 * arasında katı (!==) farklı alanlar; create'te (old null) tüm alanlar.
	 * new geçerli bir dizi değilse, old null/dizi değilse veya new'de bir
	 * allowlist alanı eksikse null.
	 *
	 * @return string[]|null
	 */
	private static function expected_changed_fields( $type, $oldFields, $newFields ) {
		$allow = MaviBelge_Core_Import_Managed_Fields::fields_for( $type );
		if ( null === $allow ) {
			return null;
		}
		if ( ! is_array( $newFields ) || ( null !== $oldFields && ! is_array( $oldFields ) ) ) {
			return null;
		}
		$changed = array();
		foreach ( $allow as $field ) {
			if ( ! array_key_exists( $field, $newFields ) ) {
				return null;
			}
			if ( null === $oldFields || ! array_key_exists( $field, $oldFields ) || $oldFields[ $field ] !== $newFields[ $field ] ) {
				$changed[] = $field;
			}
		}
		return $changed;
	}

	/** Deterministik hash; hesaplanamazsa null (fail-closed). */
	private static function safe_hash( $fields ) {
		try {
			return MaviBelge_Core_Import_Hash::hash( $fields );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	private static function not_eligible( array $errors ) {
		return array( 'eligible' => false, 'errors' => $errors, 'writes' => array(), 'noops' => array() );
	}
}
