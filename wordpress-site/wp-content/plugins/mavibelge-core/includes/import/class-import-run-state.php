<?php
/**
 * Faz 6B3 — import run durum makinesi (SAF; WordPress fonksiyonu çağırmaz).
 *
 * Geçişler KAPALIDIR: yalnız TRANSITIONS tablosundaki geçişler izinlidir;
 * terminal durumlardan (failed, rolled_back) çıkış yoktur. Run deposu her
 * geçişi karşılaştır-ve-değiştir (compare-and-set) ile uygular.
 *
 * - planned           : run oluşturuldu, henüz hiçbir batch başlamadı.
 * - running           : batch'ler işleniyor (kilit tutuluyor).
 * - failed            : hiçbir batch commit edilmeden başarısız oldu (geri alınacak veri yok).
 * - completed         : bütün batch'ler commit edildi, audit tamam.
 * - rollback_required : en az bir batch commit edildikten sonra başarısız oldu;
 *                       önceki batch'ler yalnız doğrulanmış rollback kayıtlarıyla geri alınabilir.
 * - rolling_back      : rollback işleniyor (kilit tutuluyor).
 * - rolled_back       : bütün item'lar geri alındı.
 * - rollback_failed   : rollback reddedildi veya kısmen tamamlandı; manuel inceleme
 *                       gerekir, koşullar düzeltildikten sonra yeniden denenebilir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Import_Run_State {

	const PLANNED           = 'planned';
	const RUNNING           = 'running';
	const FAILED            = 'failed';
	const COMPLETED         = 'completed';
	const ROLLBACK_REQUIRED = 'rollback_required';
	const ROLLING_BACK      = 'rolling_back';
	const ROLLED_BACK       = 'rolled_back';
	const ROLLBACK_FAILED   = 'rollback_failed';

	const ALL = array( 'planned', 'running', 'failed', 'completed', 'rollback_required', 'rolling_back', 'rolled_back', 'rollback_failed' );

	/** Kaynak durum => izinli hedef durumlar. */
	const TRANSITIONS = array(
		'planned'           => array( 'running', 'failed' ),
		'running'           => array( 'completed', 'failed', 'rollback_required' ),
		'failed'            => array(),
		'completed'         => array( 'rolling_back' ),
		'rollback_required' => array( 'rolling_back' ),
		'rolling_back'      => array( 'rolled_back', 'rollback_failed' ),
		'rolled_back'       => array(),
		'rollback_failed'   => array( 'rolling_back' ),
	);

	/** Rollback başlatılabilecek durumlar. */
	const ROLLBACKABLE = array( 'completed', 'rollback_required', 'rollback_failed' );

	/** Kilit tutan (etkin) durumlar — kilit alındığında bu durumdaki run bayattır. */
	const ACTIVE = array( 'running', 'rolling_back' );

	/** Yeni bir apply'ı engelleyen, çözülmemiş durumlar. */
	const BLOCKS_NEW_APPLY = array( 'running', 'rolling_back', 'rollback_required' );

	/**
	 * @param mixed $from
	 * @param mixed $to
	 * @return bool
	 */
	public static function can_transition( $from, $to ) {
		if ( ! is_string( $from ) || ! is_string( $to ) || ! array_key_exists( $from, self::TRANSITIONS ) ) {
			return false;
		}
		return in_array( $to, self::TRANSITIONS[ $from ], true );
	}
}
