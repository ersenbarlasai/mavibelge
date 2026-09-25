<?php
/**
 * Faz 9 — `wp mavibelge redirects` (yalnız WP-CLI ortamında yüklenir).
 *
 *   wp mavibelge redirects import --file=<manifest.json> [--format=json]        DRY-RUN (varsayılan, hiçbir şey yazmaz)
 *   wp mavibelge redirects import --file=<...> --apply --confirm=<digest> --user=<yönetici>
 *   wp mavibelge redirects status
 *
 * Apply varsayılan KAPALI: MAVIBELGE_REDIRECTS_APPLY_ENABLED === true + manage_options yetkisi + dry-run'ın verdiği
 * kural-seti özeti (--confirm). Yalnız `origin=verified` VE hedefi gerçekten var olan kurallar aktif yazılır;
 * `proposed` kurallar ve hedefi henüz olmayan kurallar PASİF kaydedilir (kurum onayı/hedef oluşana kadar çalışmaz).
 * Hiçbir sunucu/.htaccess dosyasına dokunulmaz.
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit;
}

class MaviBelge_Core_Redirects_CLI_Command {

	/**
	 * @when after_wp_load
	 */
	public function import( $args, $assoc_args ) {
		$file = isset( $assoc_args['file'] ) && is_string( $assoc_args['file'] ) ? $assoc_args['file'] : '';
		if ( '' === $file || ! is_readable( $file ) ) {
			WP_CLI::error( '--file okunabilir bir yönlendirme manifesti olmalı.' );
		}
		$json = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $json ) || ! isset( $json['rules'] ) || ! is_array( $json['rules'] ) ) {
			WP_CLI::error( 'Manifest geçersiz: "rules" listesi yok.' );
		}
		$incoming = array_values( $json['rules'] );
		$report   = MaviBelge_Core_Redirects_Rules::dry_run( $incoming, MaviBelge_Core_Redirects_Service::rules(), array( 'MaviBelge_Core_Redirects_Service', 'target_exists' ) );
		$digest   = MaviBelge_Core_Redirects_Rules::digest( $incoming );
		$apply    = isset( $assoc_args['apply'] );
		$out      = array( 'mode' => $apply ? 'apply' : 'dry-run', 'digest' => $digest, 'applicable' => $report['applicable'], 'summary' => $report['summary'], 'errors' => $report['errors'] );
		if ( ! $apply ) {
			WP_CLI::log( wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
			return;
		}
		if ( ! defined( 'MAVIBELGE_REDIRECTS_APPLY_ENABLED' ) || true !== MAVIBELGE_REDIRECTS_APPLY_ENABLED ) {
			WP_CLI::error( 'Apply kapalı: MAVIBELGE_REDIRECTS_APPLY_ENABLED yalnız onaylı pencere süresince true yapılmalıdır.' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			WP_CLI::error( 'Yetkisiz: manage_options yetkisi olan bir kullanıcı (--user) gerekir.' );
		}
		if ( ! isset( $assoc_args['confirm'] ) || $assoc_args['confirm'] !== $digest ) {
			WP_CLI::error( 'Onay gerekli: --confirm=' . $digest );
		}
		if ( ! $report['applicable'] ) {
			WP_CLI::error( 'Küme geçersiz; hiçbir şey yazılmadı.' );
		}
		$toWrite = array();
		foreach ( $incoming as $rule ) {
			$active = true === $rule['active'] && 'verified' === $rule['origin'];
			if ( $active && 410 !== $rule['status'] && ! MaviBelge_Core_Redirects_Service::target_exists( MaviBelge_Core_Redirects_Rules::normalize_path( $rule['target'] ) ) ) {
				$active = false;
			}
			$rule['active'] = $active;
			$toWrite[]      = $rule;
		}
		$saved = MaviBelge_Core_Redirects_Service::save_rules( $toWrite );
		if ( ! $saved['ok'] ) {
			WP_CLI::error( 'Kaydedilemedi (küme doğrulaması başarısız).' );
		}
		$out['written']       = count( $toWrite );
		$out['active_written'] = count( array_filter( $toWrite, function ( $r ) {
			return true === $r['active'];
		} ) );
		WP_CLI::log( wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
	}

	/**
	 * @when after_wp_load
	 */
	public function status() {
		$rules = MaviBelge_Core_Redirects_Service::rules();
		WP_CLI::log( wp_json_encode( array( 'total' => count( $rules ), 'active' => count( array_filter( $rules, function ( $r ) {
			return ! empty( $r['active'] );
		} ) ) ), JSON_UNESCAPED_SLASHES ) );
	}
}
