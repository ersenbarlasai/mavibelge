<?php
/**
 * Faz 10 — TEK oran sınırı altyapısı (formlar ve oturum açma paylaşır; ikinci bir sınırlayıcı YOKTUR).
 *
 * İki katman:
 *  - SAF karar mantığı (WordPress fonksiyonu çağırmaz): `evaluate()` Faz 8'in `Forms_Security::evaluate_rate()`
 *    davranışını AYNEN korur (FAIL-CLOSED: sayaçlardan biri okunamazsa 'unavailable'); `counter_value()` depolanan
 *    değeri katı biçimde okur; `client_id()` ham IP'yi asla saklamayan HMAC özeti üretir.
 *  - Depo arayüzü (`MaviBelge_Core_Rate_Limit_Store`): `get()`/`set()`. Üretim uygulaması WordPress transient'larıdır;
 *    testler bellek içi depo kullanır.
 *
 * `check_and_hit()` = bak + say (formlar); `peek()` yalnız bakar, `hit()` yalnız sayar (oturum açma: yalnız BAŞARISIZ
 * denemeler sayılır). Sayaç yazılamazsa 'unavailable' döner — hiçbir çağıran bunu "izin ver" diye yorumlamaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MaviBelge_Core_Rate_Limit_Store {

	/** @return mixed Kayıt yoksa `false`. */
	public function get( $key );

	/** @return bool Yazma başarılı mı. */
	public function set( $key, $value, $ttl );
}

class MaviBelge_Core_Rate_Limit_Transient_Store implements MaviBelge_Core_Rate_Limit_Store {

	public function get( $key ) {
		return get_transient( $key );
	}

	public function set( $key, $value, $ttl ) {
		return (bool) set_transient( $key, $value, (int) $ttl );
	}
}

class MaviBelge_Core_Rate_Limit {

	/**
	 * FAIL-CLOSED karar: sayaçlardan biri okunamazsa (null) izin VERİLMEZ.
	 *
	 * @param int|null $clientCount Bu istemcinin penceredeki sayısı (bu deneme HARİÇ).
	 * @param int|null $globalCount Genel penceredeki sayı.
	 * @param array    $limits      per_client, global (üst sınırlar); window/global_window saniye.
	 * @return string 'ok' | 'client_limited' | 'global_limited' | 'unavailable'
	 */
	public static function evaluate( $clientCount, $globalCount, array $limits ) {
		if ( ! is_int( $clientCount ) || ! is_int( $globalCount ) || $clientCount < 0 || $globalCount < 0 ) {
			return 'unavailable';
		}
		if ( $clientCount >= (int) $limits['per_client'] ) {
			return 'client_limited';
		}
		if ( $globalCount >= (int) $limits['global'] ) {
			return 'global_limited';
		}
		return 'ok';
	}

	/**
	 * Sayaç okuması: kayıt yok (false) -> 0; tam sayı veya WordPress'in seçenek deposundan dönen rakam dizgesi -> int;
	 * bozuk/beklenmeyen değer -> null (karar FAIL-CLOSED 'unavailable' verir).
	 */
	public static function counter_value( $stored ) {
		if ( false === $stored ) {
			return 0;
		}
		if ( is_int( $stored ) ) {
			return $stored >= 0 ? $stored : null;
		}
		return is_string( $stored ) && 1 === preg_match( '/^[0-9]{1,9}$/', $stored ) ? (int) $stored : null;
	}

	/** İstemci kimliği: yalnız verilen adres (vekil başlıklarına güvenilmez), HAM IP saklanmaz — HMAC özeti. */
	public static function client_id( $remoteAddr, $secret ) {
		$ip = is_string( $remoteAddr ) && false !== filter_var( $remoteAddr, FILTER_VALIDATE_IP ) ? $remoteAddr : 'unknown';
		return hash_hmac( 'sha256', $ip, (string) $secret );
	}

	/**
	 * Yalnız bakar (yazmaz).
	 *
	 * @param string|null $globalKey null: genel sayaç yok (yalnız istemci sınırı).
	 * @return string evaluate() sonucu.
	 */
	public static function peek( MaviBelge_Core_Rate_Limit_Store $store, $clientKey, $globalKey, array $limits ) {
		$cc = self::counter_value( $store->get( $clientKey ) );
		$gc = null === $globalKey ? 0 : self::counter_value( $store->get( $globalKey ) );
		return self::evaluate( $cc, $gc, $limits );
	}

	/**
	 * Yalnız sayar. Okuma bozuksa veya yazılamazsa false (çağıran FAIL-CLOSED davranır).
	 *
	 * @return bool
	 */
	public static function hit( MaviBelge_Core_Rate_Limit_Store $store, $clientKey, $globalKey, array $limits ) {
		$cc = self::counter_value( $store->get( $clientKey ) );
		$gc = null === $globalKey ? 0 : self::counter_value( $store->get( $globalKey ) );
		if ( null === $cc || null === $gc ) {
			return false;
		}
		if ( ! $store->set( $clientKey, $cc + 1, (int) $limits['window'] ) ) {
			return false;
		}
		return null === $globalKey || $store->set( $globalKey, $gc + 1, (int) $limits['global_window'] );
	}

	/**
	 * Bak + say (formlar). Sayaç okunamaz/yazılamazsa 'unavailable' (FAIL-CLOSED).
	 *
	 * @return string 'ok' | 'client_limited' | 'global_limited' | 'unavailable'
	 */
	public static function check_and_hit( MaviBelge_Core_Rate_Limit_Store $store, $clientKey, $globalKey, array $limits ) {
		$cc       = self::counter_value( $store->get( $clientKey ) );
		$gc       = null === $globalKey ? 0 : self::counter_value( $store->get( $globalKey ) );
		$decision = self::evaluate( $cc, $gc, $limits );
		if ( 'ok' !== $decision ) {
			return $decision;
		}
		if ( ! $store->set( $clientKey, $cc + 1, (int) $limits['window'] ) ) {
			return 'unavailable';
		}
		if ( null !== $globalKey && ! $store->set( $globalKey, $gc + 1, (int) $limits['global_window'] ) ) {
			return 'unavailable';
		}
		return 'ok';
	}
}
