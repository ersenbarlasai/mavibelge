<?php
/**
 * Faz 10 — ağır herkese açık sorgular için güvenli, kısa ömürlü önbellek (TEK önbellek; ikinci bir sistem yoktur).
 *
 * Tasarım:
 *  - Anahtar: `mbc_<nesil>_<sha1(tür|dil|sayfa|filtreler)>`. Dil, sayfa ve filtre AYRI anahtar üretir; filtre dizisi
 *    anahtar sırasından bağımsız kanonikleştirilir. Nesil (`mavibelge_core_cache_gen` seçeneği) arttığında bütün eski
 *    anahtarlar ulaşılmaz olur (silme gerekmez; WordPress süresi dolan transient'ı kendisi temizler).
 *  - TTL 300 sn. Önbellek KAPALI: `WP_DEBUG` iken, `MAVIBELGE_CACHE_DISABLED` true iken, veya içerik düzenleme yetkisi olan
 *    oturum açmış kullanıcıda (önizleme/yönetim her zaman taze veri görür). `mavibelge_core_cache_enabled` süzgeci SON
 *    sözü söyler (yalnız test/özel kurulum içindir).
 *  - FAIL-OPEN OKUMA: transient okunamaz/bozuksa sorgu çalıştırılır (doğru sonuç her zaman üretilir). Yazma başarısızlığı
 *    sessizce yok sayılır.
 *  - Yalnız SAF DTO dizileri (skaler/null/dizi) saklanır; nesne içeren sonuç ASLA saklanmaz.
 *  - Anahtar uzayı sınırlıdır: çağıranlar serbest metin (arama) veya var olmayan sayfa/filtre için `$storeIf` ile önbelleğe
 *    YAZMAZ.
 *  - Geçersiz kılma: yalnız `mb_` içerik/terim/meta değişimleri, aktif tarife dönemi, tema/eklenti değişimi, kalıcı
 *    bağlantı/görüntü ayarı değişimi nesli TEK atomik UPDATE ile artırır. Form/yönlendirme seçenekleri artırmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Cache {

	const TTL        = 300;
	const GEN_OPTION = 'mavibelge_core_cache_gen';
	const KEY_PREFIX = 'mbc_';

	/** İstek içi nesil önbelleği (bump() sıfırlar). */
	private static $gen_memo = null;

	/** Bu istekteki bump() sayısı (test/tanı). */
	private static $bumps = 0;

	/* ------------------------------------------------------------- SAF kısım */

	/**
	 * @param string|int $generation
	 * @param string     $type       kısa tür adı (ör. 'news')
	 * @param string     $lang       yerel ayar (ör. 'tr_TR')
	 * @param int        $page
	 * @param array      $filters    skaler değerli filtreler; anahtar sırası önemsizdir
	 * @return string
	 */
	public static function key( $generation, $type, $lang, $page, array $filters ) {
		$canonical = self::canonical_filters( $filters );
		$payload   = (string) $type . '|' . (string) $lang . '|' . (int) $page . '|' . $canonical;
		return self::KEY_PREFIX . preg_replace( '/[^0-9]/', '', (string) $generation ) . '_' . sha1( $payload );
	}

	/** Filtreleri anahtar sırasından bağımsız, belirsizliksiz bir dizgeye çevirir. */
	public static function canonical_filters( array $filters ) {
		ksort( $filters );
		$parts = array();
		foreach ( $filters as $name => $value ) {
			if ( is_array( $value ) ) {
				$value = self::canonical_filters( $value );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			}
			$value   = (string) $value;
			$parts[] = strlen( (string) $name ) . ':' . $name . '=' . strlen( $value ) . ':' . $value;
		}
		return implode( '&', $parts );
	}

	/**
	 * Önbellek bu istekte açık mı? (SAF karar; ortam dışarıdan verilir.)
	 *
	 * @param array $env debug(bool), disabled_const(bool), can_edit(bool), override(bool|null: süzgeç sonucu)
	 */
	public static function is_enabled_for( array $env ) {
		$enabled = empty( $env['debug'] ) && empty( $env['disabled_const'] ) && empty( $env['can_edit'] );
		if ( array_key_exists( 'override', $env ) && is_bool( $env['override'] ) ) {
			return $env['override'];
		}
		return $enabled;
	}

	/** Değer yalnız skaler/null/dizi (özyinelemeli) ise saklanabilir; nesne/kaynak saklanmaz. */
	public static function is_pure_dto( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! self::is_pure_dto( $item ) ) {
					return false;
				}
			}
			return true;
		}
		return null === $value || is_scalar( $value );
	}

	/** Saklanan paketin geçerli biçimi: array('g' => nesil, 'd' => veri). Aksi hâlde null. */
	public static function unwrap( $stored, $generation ) {
		if ( is_array( $stored ) && array_key_exists( 'd', $stored ) && isset( $stored['g'] ) && (string) $stored['g'] === (string) $generation && is_array( $stored['d'] ) ) {
			return $stored['d'];
		}
		return null;
	}

	/* -------------------------------------------------- WordPress bağlantısı */

	public static function init() {
		$bump = array( __CLASS__, 'bump' );
		add_action( 'save_post', array( __CLASS__, 'on_post_change' ), 10, 1 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_change' ), 10, 1 );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_change' ), 10, 1 );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_change' ), 10, 1 );
		add_action( 'edit_attachment', $bump );
		add_action( 'delete_attachment', $bump );
		add_action( 'set_object_terms', array( __CLASS__, 'on_object_terms' ), 10, 4 );
		add_action( 'created_term', array( __CLASS__, 'on_term_change' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'on_term_change' ), 10, 3 );
		add_action( 'deleted_term', array( __CLASS__, 'on_deleted_term' ), 10, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_deleted_meta' ), 10, 3 );
		add_action( 'update_option_mb_active_tariff_period', $bump );
		add_action( 'add_option_mb_active_tariff_period', $bump );
		add_action( 'delete_option_mb_active_tariff_period', $bump );
		foreach ( array( 'permalink_structure', 'home', 'siteurl', 'date_format', 'timezone_string', 'gmt_offset', 'WPLANG', 'category_base' ) as $option ) {
			add_action( 'update_option_' . $option, $bump );
		}
		add_action( 'switch_theme', $bump );
		add_action( 'activated_plugin', $bump );
		add_action( 'deactivated_plugin', $bump );
		add_action( 'upgrader_process_complete', $bump );
		add_action( 'mavibelge_core_cache_flush', $bump );
	}

	/** Yalnız `mb_*` yazı türleri, ekler ve sayfalar önbellekteki DTO'ları etkiler. */
	public static function on_post_change( $postId ) {
		$type = get_post_type( $postId );
		if ( is_string( $type ) && ( 0 === strpos( $type, 'mb_' ) || in_array( $type, array( 'attachment', 'page', 'nav_menu_item' ), true ) ) ) {
			self::bump();
		}
	}

	public static function on_object_terms( $objectId, $terms, $ttIds, $taxonomy ) {
		if ( is_string( $taxonomy ) && 0 === strpos( $taxonomy, 'mb_' ) ) {
			self::bump();
		}
	}

	public static function on_term_change( $termId, $ttId, $taxonomy ) {
		if ( is_string( $taxonomy ) && 0 === strpos( $taxonomy, 'mb_' ) ) {
			self::bump();
		}
	}

	public static function on_deleted_term( $termId, $ttId, $taxonomy ) {
		self::on_term_change( $termId, $ttId, $taxonomy );
	}

	/** `updated_post_meta( $metaId, $postId, $metaKey )` — yalnız `_mb_` anahtarları (ve öne çıkan görsel). */
	public static function on_meta_change( $metaId, $postId, $metaKey ) {
		if ( is_string( $metaKey ) && ( 0 === strpos( $metaKey, '_mb_' ) || '_thumbnail_id' === $metaKey ) ) {
			self::bump();
		}
	}

	/** `deleted_post_meta( $metaIds, $postId, $metaKey )`. */
	public static function on_deleted_meta( $metaIds, $postId, $metaKey ) {
		self::on_meta_change( 0, $postId, $metaKey );
	}

	/** Önbellek bu istekte açık mı? */
	public static function enabled() {
		$can_edit = false;
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$can_edit = current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
		}
		$env = array(
			'debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'disabled_const' => defined( 'MAVIBELGE_CACHE_DISABLED' ) && true === MAVIBELGE_CACHE_DISABLED,
			'can_edit'       => $can_edit,
		);
		$default = self::is_enabled_for( $env );
		$final   = apply_filters( 'mavibelge_core_cache_enabled', $default, $env );
		return is_bool( $final ) ? $final : $default;
	}

	/** Geçerli nesil (istek içinde bir kez okunur). */
	public static function generation() {
		if ( null === self::$gen_memo ) {
			$value           = get_option( self::GEN_OPTION, '1' );
			self::$gen_memo = is_scalar( $value ) && 1 === preg_match( '/^[0-9]{1,18}$/', (string) $value ) ? (string) $value : '1';
		}
		return self::$gen_memo;
	}

	/**
	 * Nesli TEK atomik UPDATE ile artırır (yarış durumunda iki artış da uygulanır, hiçbiri kaybolmaz).
	 * Seçenek yoksa önce (yalnız bir kez) oluşturulur.
	 */
	public static function bump() {
		global $wpdb;
		self::$bumps++;
		self::$gen_memo = null;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}
		add_option( self::GEN_OPTION, '1', '', 'yes' ); // varsa hiçbir şey yapmaz
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s", self::GEN_OPTION ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablo adı çekirdekten.
		wp_cache_delete( self::GEN_OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	public static function bump_count() {
		return self::$bumps;
	}

	/**
	 * Önbellekten oku; yoksa/bozuksa/kapalıysa `$compute` çalıştırılır (fail-open).
	 *
	 * @param callable      $compute  saf DTO dizisi döndürmeli
	 * @param callable|null $storeIf  sonuç alınca çağrılır; false dönerse SAKLANMAZ (anahtar uzayını sınırlamak için)
	 * @return array
	 */
	public static function remember( $type, $page, array $filters, $compute, $storeIf = null ) {
		if ( ! self::enabled() ) {
			return call_user_func( $compute );
		}
		$generation = self::generation();
		$lang       = function_exists( 'get_locale' ) ? (string) get_locale() : '';
		$key        = self::key( $generation, $type, $lang, $page, $filters );
		$stored     = get_transient( $key );
		$hit        = false === $stored ? null : self::unwrap( $stored, $generation );
		if ( null !== $hit ) {
			return $hit;
		}
		$fresh = call_user_func( $compute );
		if ( is_array( $fresh ) && self::is_pure_dto( $fresh ) && ( null === $storeIf || true === call_user_func( $storeIf, $fresh ) ) ) {
			set_transient( $key, array( 'g' => $generation, 'd' => $fresh ), self::TTL ); // yazma başarısızlığı sessizce yok sayılır
		}
		return $fresh;
	}
}
