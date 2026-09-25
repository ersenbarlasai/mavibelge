<?php
/**
 * Faz 6B1 — three-hash decision classifier. Pure PHP 7.3, no WordPress
 * function calls (no get_posts/WP_Query/$wpdb/update_post_meta/
 * wp_insert_post/wp_update_term — none of that belongs here). Takes only
 * already-resolved facts about ONE target record and returns ONE decision
 * + ONE machine-readable reason code + a Turkish human-readable message.
 *
 * The three-hash table below is the exact, binding table from
 * raporlar/veri-aktarim-raporlari/faz6a-faz6b-import-sozlesmesi-ve-rollback-taslagi.md
 * §2, refined by this task's own §5 (duplicate-target / wrong-target-type
 * / dependency-blocked / invalid-shape are their own decisions, not
 * folded into a generic "conflict").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Decision {

	// --- Decisions (bkz. görev promptu §5, madde 1-9) — TEK sabit sözlük. ---
	const CREATE                     = 'create';
	const UNCHANGED                  = 'unchanged';
	const UPDATE                     = 'update';
	const CONFLICT                   = 'conflict';
	const CONFLICT_DUPLICATE_TARGET  = 'conflict_duplicate_target';
	const CONFLICT_WRONG_TARGET_TYPE = 'conflict_wrong_target_type';
	const BLOCKED_DEPENDENCY         = 'blocked_dependency';
	const INVALID                    = 'invalid';

	/** Tüm geçerli karar değerleri — testlerde/planlayıcıda "bilinmeyen karar" hatasını yakalamak için. */
	const ALL_DECISIONS = array(
		self::CREATE, self::UNCHANGED, self::UPDATE, self::CONFLICT,
		self::CONFLICT_DUPLICATE_TARGET, self::CONFLICT_WRONG_TARGET_TYPE,
		self::BLOCKED_DEPENDENCY, self::INVALID,
	);

	// --- Machine-readable reason codes (Türkçe insan-okunur metinden ayrı). ---
	const REASON_INVALID_SHAPE            = 'invalid_shape';
	const REASON_DUPLICATE_TARGET         = 'duplicate_target';
	const REASON_DEPENDENCY_UNRESOLVED    = 'dependency_unresolved';
	const REASON_WRONG_TARGET_TYPE        = 'wrong_target_type';
	const REASON_NO_TARGET                = 'no_target';
	const REASON_LEGACY_MISSING_HASH      = 'legacy_missing_hash';
	const REASON_INVALID_TARGET_STATE     = 'invalid_target_state';
	const REASON_HASH_MATCH               = 'hash_match';
	const REASON_SAFE_UPDATE              = 'safe_update';
	const REASON_MANUAL_EDIT_DETECTED     = 'manual_edit_detected';
	const REASON_BOTH_CHANGED             = 'both_changed';

	/*
	 * Faz 6B3 Önkoşul — doğal anahtar preflight neden kodları (marker ile
	 * hedef bulunamadığında). Hepsi create'i ENGELLER; biri de otomatik
	 * yazma adayı değildir. Anlamları için bkz.
	 * MaviBelge_Core_Import_Record_Validator::NATURAL_KEY_STATES ve
	 * raporlar/veri-aktarim-raporlari/faz6b3-yazma-guvenligi-sozlesmesi.md.
	 */
	const REASON_UNMANAGED_NATURAL_KEY   = 'unmanaged_natural_key';
	const REASON_CORRUPT_MARKER          = 'corrupt_marker';
	const REASON_WRONG_MARKER_PREFIX     = 'wrong_marker_prefix';
	const REASON_FOREIGN_MARKER          = 'foreign_marker';
	const REASON_UNDISCOVERED_MARKER     = 'undiscovered_marker';
	const REASON_DUPLICATE_NATURAL_KEY   = 'duplicate_natural_key';
	const REASON_NATURAL_KEY_QUERY_ERROR = 'natural_key_query_error';

	/**
	 * @param array $input {
	 *     @type bool        $valid_shape           false ise her şeyden önce INVALID döner.
	 *     @type bool        $duplicate_targets     aynı source_key için birden fazla hedef bulundu mu.
	 *     @type bool        $target_state_valid    target lookup şekli/bayrak tutarlılığı geçerli mi (bkz.
	 *         MaviBelge_Core_Import_Record_Validator::normalize_target_lookup() — çelişkili target_found/
	 *         target_id/current_managed_fields/target_type_matches burada false döner). Geçmiyorsa true say
	 *         (mevcut testler bu anahtarı hiç vermez — varsayılan olarak geçerli kabul edilir).
	 *     @type bool        $dependency_resolved    zorunlu ilişki/medya hedefi çözüldü mü (bağımlılık yoksa true geç).
	 *     @type bool        $target_found          hedef kayıt (post/term) bulundu mu.
	 *     @type bool        $target_type_matches   yalnız target_found=true iken anlamlı; hedefin GERÇEK türü beklenen türle uyuşuyor mu.
	 *     @type string|null $last_applied_hash     hedefte saklı _mb_last_applied_hash (yoksa/geçersizse null).
	 *     @type string|null $current_managed_hash  hedefin ŞU ANKİ yönetilen alanlarının hash'i (hedef yoksa null).
	 *     @type string      $incoming_hash         bu çalıştırmanın manifest kaydından hesaplanan hash.
	 *     @type bool        $has_source_key_marker hedefte _mb_import_source_key VAR MI (legacy/eksik-hash tespiti için).
	 * }
	 * @return array{decision:string, reason:string, message:string}
	 */
	public static function classify( array $input ) {
		$valid_shape       = ! empty( $input['valid_shape'] );
		$duplicate_targets = ! empty( $input['duplicate_targets'] );
		$target_state_valid = array_key_exists( 'target_state_valid', $input ) ? (bool) $input['target_state_valid'] : true;
		$dependency_resolved = array_key_exists( 'dependency_resolved', $input ) ? (bool) $input['dependency_resolved'] : true;
		$target_found      = ! empty( $input['target_found'] );
		$target_type_matches = array_key_exists( 'target_type_matches', $input ) ? (bool) $input['target_type_matches'] : true;
		$last_applied_hash    = isset( $input['last_applied_hash'] ) ? $input['last_applied_hash'] : null;
		$current_managed_hash = isset( $input['current_managed_hash'] ) ? $input['current_managed_hash'] : null;
		$incoming_hash        = isset( $input['incoming_hash'] ) ? $input['incoming_hash'] : null;
		$has_source_key_marker = ! empty( $input['has_source_key_marker'] );
		$natural_key          = isset( $input['natural_key'] ) && is_string( $input['natural_key'] ) ? $input['natural_key'] : null;

		// 9. Geçersiz manifest/alan şekli → invalid. Sessiz varsayılan/kısmi plan yok — her şeyden önce kontrol edilir.
		if ( ! $valid_shape ) {
			return self::result( self::INVALID, self::REASON_INVALID_SHAPE, 'Manifest kaydı veya çözülen bağımlılık şekli geçersiz; hiçbir plan üretilmedi.' );
		}

		// 6. Aynı source_key için hedefte birden fazla kayıt → conflict_duplicate_target. İlk kaydı KEYFÎ SEÇMEK YOK.
		if ( $duplicate_targets ) {
			return self::result( self::CONFLICT_DUPLICATE_TARGET, self::REASON_DUPLICATE_TARGET, 'Aynı source_key için hedefte birden fazla kayıt bulundu; hangisinin doğru hedef olduğu makine tarafından seçilemez, elle inceleme gerekir.' );
		}

		// Target lookup şekli/bayrak tutarlılığı geçersiz (çelişkili
		// target_found/target_id/current_managed_fields vb.) → kontrollü
		// conflict, hash istisnası/yanlış create-update YOK. Görev promptu
		// §7'nin önerdiği sırada duplicate'ten hemen sonra, dependency ve
		// wrong-target-type'tan ÖNCE gelir — bir hedefin "türü" veya
		// "bulunma durumu" bile güvenilir değilse, dependency/tip
		// kararları da güvenilir sayılamaz.
		if ( ! $target_state_valid ) {
			return self::result( self::CONFLICT, self::REASON_INVALID_TARGET_STATE, 'Hedef kaydın çözümlenmiş durumu (bulunma/tip/mevcut alanlar) tutarsız; elle inceleme gerekir.' );
		}

		// Faz 6B3 Önkoşul — marker ile hedef yok AMA doğal anahtar
		// preflight'ı bir engel buldu: create ASLA verilmez. Bağımlılık
		// kontrolünden ÖNCE gelir (var olan yönetilmeyen/bozuk bir hedef,
		// "bağımlılık çözülemedi"den daha önemli bir bulgudur).
		if ( ! $target_found && null !== $natural_key && 'none' !== $natural_key ) {
			return self::natural_key_result( $natural_key );
		}

		// 7. Hedef kayıt türü beklenen türle uyuşmuyorsa → conflict_wrong_target_type.
		if ( $target_found && ! $target_type_matches ) {
			return self::result( self::CONFLICT_WRONG_TARGET_TYPE, self::REASON_WRONG_TARGET_TYPE, 'source_key eşleşen bir kayıt bulundu ama gerçek türü beklenen içerik türüyle uyuşmuyor.' );
		}

		// 8. Gerekli ilişki/medya hedefi çözülemiyorsa → blocked_dependency. create/update SAYILMAZ.
		if ( ! $dependency_resolved ) {
			return self::result( self::BLOCKED_DEPENDENCY, self::REASON_DEPENDENCY_UNRESOLVED, 'Zorunlu bir ilişki veya medya hedefi henüz çözülemedi; bu kayıt create/update olarak sayılmaz.' );
		}

		// 1. Hedef kayıt yok → create.
		if ( ! $target_found ) {
			return self::result( self::CREATE, self::REASON_NO_TARGET, 'Hedefte bu source_key ile eşleşen kayıt yok; yeni kayıt olarak planlanabilir.' );
		}

		// 5. _mb_import_source_key VAR ama _mb_last_applied_hash yok/geçersiz → conflict (eski/geçiş kaydı).
		if ( $has_source_key_marker && null === $last_applied_hash ) {
			return self::result( self::CONFLICT, self::REASON_LEGACY_MISSING_HASH, 'Kayıtta içe aktarım kaynak anahtarı var ama geçerli bir son-uygulanan-özet yok; üç-hash karşılaştırması yapılamaz, elle inceleme gerekir.' );
		}

		// 2/3/4 — üç-hash karşılaştırması.
		$current_equals_last = ( null !== $last_applied_hash && $current_managed_hash === $last_applied_hash );
		if ( ! $current_equals_last ) {
			$both_changed = ( null !== $last_applied_hash && $incoming_hash !== $last_applied_hash );
			return self::result(
				self::CONFLICT,
				$both_changed ? self::REASON_BOTH_CHANGED : self::REASON_MANUAL_EDIT_DETECTED,
				$both_changed
					? 'Hedef kayıt hem elle değiştirilmiş HEM kaynak veri değişmiş; otomatik ezilmez, en yüksek öncelikli çakışma olarak raporlanır.'
					: 'Hedef kayıt, içe aktarıcının son yazdığından beri elle değiştirilmiş; otomatik ezilmez.'
			);
		}
		if ( $incoming_hash === $last_applied_hash ) {
			return self::result( self::UNCHANGED, self::REASON_HASH_MATCH, 'Hedef kayıt değişmemiş; hiçbir yazma gerekmez.' );
		}
		return self::result( self::UPDATE, self::REASON_SAFE_UPDATE, 'Hedef kayıt içe aktarıcının son bildiği haliyle değişmemiş, yalnız kaynak veri değişmiş; güvenli güncelleme.' );
	}

	/** Faz 6B3 Önkoşul — doğal anahtar durumundan fail-closed karar. Bilinmeyen durum da conflict'tir. */
	private static function natural_key_result( $state ) {
		switch ( $state ) {
			case 'duplicate':
				return self::result( self::CONFLICT_DUPLICATE_TARGET, self::REASON_DUPLICATE_NATURAL_KEY, 'Marker ile hedef bulunamadı ama doğal anahtarla birden fazla kayıt eşleşiyor; hangisi olduğu makine tarafından seçilemez.' );
			case 'unmanaged':
				return self::result( self::CONFLICT, self::REASON_UNMANAGED_NATURAL_KEY, 'Aynı doğal anahtarla içe aktarım işareti taşımayan bir kayıt zaten var; yeni kayıt oluşturulmaz, elle eşleştirme gerekir.' );
			case 'corrupt_marker':
				return self::result( self::CONFLICT, self::REASON_CORRUPT_MARKER, 'Aynı doğal anahtarlı kaydın içe aktarım işareti bozuk (dizi/nesne veya biçimsiz); yeni kayıt oluşturulmaz.' );
			case 'wrong_marker_prefix':
				return self::result( self::CONFLICT, self::REASON_WRONG_MARKER_PREFIX, 'Aynı doğal anahtarlı kaydın içe aktarım işareti yanlış önek taşıyor; yeni kayıt oluşturulmaz.' );
			case 'foreign_marker':
				return self::result( self::CONFLICT, self::REASON_FOREIGN_MARKER, 'Aynı doğal anahtarlı kayıt başka bir kaynak anahtarına bağlı; yeni kayıt oluşturulmaz.' );
			case 'undiscovered_marker':
				return self::result( self::CONFLICT, self::REASON_UNDISCOVERED_MARKER, 'Aynı doğal anahtarlı kayıt bu kaynak anahtarını taşıyor ama işaret keşfinde görünmedi (ör. çöp kutusunda); yeni kayıt oluşturulmaz.' );
			default: // query_error veya bilinmeyen
				return self::result( self::CONFLICT, self::REASON_NATURAL_KEY_QUERY_ERROR, 'Doğal anahtar sorgusu güvenilir biçimde yapılamadı; yeni kayıt oluşturulmaz.' );
		}
	}

	private static function result( $decision, $reason, $message ) {
		return array( 'decision' => $decision, 'reason' => $reason, 'message' => $message );
	}
}
