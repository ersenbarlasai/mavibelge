<?php
/**
 * Role and capability matrix for mavibelge-core.
 *
 * Only add_role()/add_cap() — never remove_role()/remove_cap() outside
 * an explicit future migration. Deactivation must not touch roles or
 * capabilities (görev kartı 02 / karar-kaydi §... "Eklenti devre dışı
 * bırakıldığında veya kaldırıldığında içerik korunmaya devam etsin").
 *
 * Installation is idempotent and versioned via the
 * 'mavibelge_core_roles_version' option so it does not re-run add_cap
 * on every request (see class-installer.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Roles {

	const VERSION = '2';

	/**
	 * Custom, non-content capability used to gate taxonomy term
	 * management (create/edit/delete terms) separately from assigning
	 * existing terms to a post.
	 */
	const MANAGE_TAXONOMIES_CAP = 'mb_manage_taxonomies';

	/**
	 * Gates the "active tariff period" setting (mb_active_tariff_period
	 * option) separately from ordinary ücret editing/publishing, per
	 * §10: mb_price_editor must not be able to change it.
	 */
	const MANAGE_TARIFF_PERIOD_CAP = 'mb_manage_tariff_period';

	const CUSTOM_ROLES = array(
		'mb_site_manager'  => 'Mavi Belge Site Yöneticisi',
		'mb_content_editor' => 'Mavi Belge İçerik Editörü',
		'mb_price_editor'  => 'Mavi Belge Fiyat Editörü',
		'mb_reviewer'      => 'Mavi Belge Kontrol Eden/Onaylayan',
	);

	/**
	 * post_type => plural capability key, mirrors the capability_type
	 * arrays in includes/class-content-types.php.
	 */
	public static function plural_caps_by_post_type() {
		return array(
			'mb_yeterlilik' => 'mb_yeterlilikler',
			'mb_ucret'      => 'mb_ucretler',
			'mb_haber'      => 'mb_haberler',
			'mb_dokuman'    => 'mb_dokumanlar',
			'mb_referans'   => 'mb_referanslar',
			'mb_lokasyon'   => 'mb_lokasyonlar',
			'mb_sss'        => 'mb_sss_kayitlari',
		);
	}

	private static function primitive_caps( $plural ) {
		return array(
			"edit_{$plural}",
			"edit_others_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_private_{$plural}",
			"delete_published_{$plural}",
			"delete_others_{$plural}",
			"edit_private_{$plural}",
			"edit_published_{$plural}",
		);
	}

	/**
	 * Idempotent install: creates the four custom roles if missing and
	 * (re)grants capabilities on every version bump only, not on every
	 * request. Never revokes anything a site administrator may have
	 * manually adjusted beyond this matrix.
	 */
	public static function install() {
		$installed_version = get_option( 'mavibelge_core_roles_version' );
		if ( $installed_version === self::VERSION ) {
			return;
		}

		foreach ( self::CUSTOM_ROLES as $role_key => $role_label ) {
			if ( ! get_role( $role_key ) ) {
				add_role( $role_key, $role_label, array( 'read' => true ) );
			}
		}

		self::grant( 'administrator', self::admin_caps() );
		self::grant( 'mb_site_manager', self::site_manager_caps() );
		self::grant( 'mb_content_editor', self::content_editor_caps() );
		self::grant( 'mb_price_editor', self::price_editor_caps() );
		self::grant( 'mb_reviewer', self::reviewer_caps() );

		// Migration from v1: content_editor_caps() only ever excluded
		// mb_ucret, so v1 installs wrongly granted mb_content_editor
		// edit/delete rights on mb_yeterlilik. grant() above only adds
		// missing caps — it never removes anything — so the stale v1
		// caps must be removed explicitly. Only these two exact caps
		// are touched; nothing else a site owner may have customized.
		if ( '1' === $installed_version ) {
			self::remove_stale_v1_content_editor_yeterlilik_caps();
		}

		update_option( 'mavibelge_core_roles_version', self::VERSION );
	}

	private static function remove_stale_v1_content_editor_yeterlilik_caps() {
		$role = get_role( 'mb_content_editor' );
		if ( ! $role ) {
			return;
		}
		foreach ( array( 'edit_mb_yeterlilikler', 'delete_mb_yeterlilikler' ) as $cap ) {
			if ( $role->has_cap( $cap ) ) {
				$role->remove_cap( $cap );
			}
		}
	}

	private static function grant( $role_key, array $caps ) {
		$role = get_role( $role_key );
		if ( ! $role ) {
			return;
		}
		foreach ( $caps as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	private static function all_plurals() {
		return array_values( self::plural_caps_by_post_type() );
	}

	private static function admin_caps() {
		$caps = array( self::MANAGE_TAXONOMIES_CAP, self::MANAGE_TARIFF_PERIOD_CAP, 'upload_files' );
		foreach ( self::all_plurals() as $plural ) {
			$caps = array_merge( $caps, self::primitive_caps( $plural ) );
		}
		return $caps;
	}

	private static function site_manager_caps() {
		// Full content + ücret control, but deliberately no
		// edit_theme_options / install_plugins / edit_plugins / edit_files.
		$caps = array( self::MANAGE_TAXONOMIES_CAP, self::MANAGE_TARIFF_PERIOD_CAP, 'upload_files' );
		foreach ( self::all_plurals() as $plural ) {
			$caps = array_merge( $caps, self::primitive_caps( $plural ) );
		}
		return $caps;
	}

	private static function content_editor_caps() {
		// Sayfa, haber, doküman, referans, lokasyon, SSS taslağı; ücrete
		// VE yeterliliğe hiç erişim yok; kendi taslaklarını yayımlayamaz
		// (publish_* yok). v1'de yalnız mb_ucret çıkarılmış, mb_yeterlilik
		// yanlışlıkla dahil edilmişti — bkz. migrate_v1_to_v2... altta.
		$plurals = self::plural_caps_by_post_type();
		unset( $plurals['mb_ucret'] );
		unset( $plurals['mb_yeterlilik'] );

		$caps = array( 'upload_files' );
		foreach ( $plurals as $plural ) {
			$caps[] = "edit_{$plural}";
			$caps[] = "delete_{$plural}";
		}
		// WordPress core "page" content type, named explicitly in the brief.
		$caps[] = 'edit_pages';
		$caps[] = 'delete_pages';

		return $caps;
	}

	private static function price_editor_caps() {
		// Ücret kaydı oluşturup düzenleyebilir; yayımlayamaz.
		return array(
			'upload_files',
			'edit_mb_ucretler',
			'delete_mb_ucretler',
		);
	}

	private static function reviewer_caps() {
		// İnceleme/onay + yayımlama; sistem ayarlarına erişim yok.
		$caps = array( self::MANAGE_TAXONOMIES_CAP, 'upload_files' );
		foreach ( self::all_plurals() as $plural ) {
			$caps[] = "edit_{$plural}";
			$caps[] = "edit_others_{$plural}";
			$caps[] = "edit_published_{$plural}";
			$caps[] = "publish_{$plural}";
			$caps[] = "read_private_{$plural}";
		}
		return $caps;
	}
}
