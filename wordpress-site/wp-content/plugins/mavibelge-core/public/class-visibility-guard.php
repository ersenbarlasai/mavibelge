<?php
/**
 * Faz 5 Düzeltme ve Kabul §2.5 — the ONE decision for whether a
 * mb_yeterlilik post is public right now. WordPress's own routing has
 * no idea about _mb_record_status: a post that is `post_status =
 * publish` but `_mb_record_status = passive` (excluded from every
 * catalog listing/search result, see
 * MaviBelge_Core_Catalog_Service::get_qualification_results()) was
 * still directly reachable at its real permalink, because nothing in
 * the request lifecycle checked that meta value before the theme's
 * single-mb_yeterlilik.php rendered it.
 *
 * This class is the single, plugin-side place that rule lives — the
 * theme never reimplements it (AGENTS.md / brief §7's "iş kuralını
 * temaya kopyalama" applies here too, not only to mb_ucret).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Visibility_Guard {

	/** Faz 7: herkese açık görünürlük kapısı olan içerik türleri (haber onayı; diğerleri pasif kayıt). */
	const GATED_CONTENT_TYPES = array( 'mb_haber', 'mb_dokuman', 'mb_referans', 'mb_lokasyon', 'mb_sss' );

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_block_passive_qualification' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_block_nonpublic_content' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'restrict_public_archives' ) );
		add_filter( 'the_posts', array( __CLASS__, 'filter_search_results' ), 10, 2 );
	}

	/**
	 * Faz 7 — haber/doküman/referans/lokasyon/SSS için TEK karar: kayıt yayınlanmış VE görünürlük kapısını
	 * (haber: onaylı; diğerleri: pasif değil) geçiyor mu. Görünürlük kapısı olmayan türler (yeterlilik dahil)
	 * için `is_public_qualification()`/ilgili tür kuralı kullanılır; bilinmeyen tür serbesttir (true).
	 */
	public static function is_public_content( $post_id ) {
		$post_id = (int) $post_id;
		$type    = $post_id > 0 ? get_post_type( $post_id ) : false;
		if ( 'mb_yeterlilik' === $type ) {
			return self::is_public_qualification( $post_id );
		}
		if ( ! in_array( $type, self::GATED_CONTENT_TYPES, true ) ) {
			return false !== $type;
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}
		if ( 'mb_haber' === $type ) {
			return MaviBelge_Core_Content_Query::is_news_approved( get_post_meta( $post_id, '_mb_approval_status', true ) );
		}
		return MaviBelge_Core_Content_Query::is_publicly_active( get_post_meta( $post_id, '_mb_record_status', true ) );
	}

	/** Kapıya takılan bir kaydın tekil URL'si (düzenleme yetkisi olmayanlara) gerçek 404 döner. */
	public static function maybe_block_nonpublic_content() {
		if ( ! is_singular( self::GATED_CONTENT_TYPES ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( self::is_public_content( $post_id ) || current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Ana sorgu arşivleri (haber türü terim arşivi, haber/doküman arşivi, doküman kategorisi): aynı görünürlük
	 * kapısı SQL düzeyinde uygulanır; sayfa boyutu servis sabitine eşitlenir. Yönetim ve ikincil sorgular etkilenmez.
	 */
	public static function restrict_public_archives( $query ) {
		if ( is_admin() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
			return;
		}
		$type = null;
		if ( $query->is_post_type_archive( 'mb_haber' ) || $query->is_tax( 'mb_haber_turu' ) ) {
			$type = 'mb_haber';
		} elseif ( $query->is_post_type_archive( 'mb_dokuman' ) || $query->is_tax( 'mb_dokuman_kategori' ) ) {
			$type = 'mb_dokuman';
		}
		if ( null === $type ) {
			return;
		}
		$existing = $query->get( 'meta_query' );
		$gate     = MaviBelge_Core_Content_Service::public_meta_query( $type );
		$query->set( 'meta_query', is_array( $existing ) && ! empty( $existing ) ? array( 'relation' => 'AND', $existing, $gate ) : $gate );
		$query->set( 'posts_per_page', 'mb_haber' === $type ? MaviBelge_Core_Content_Query::NEWS_PAGE_SIZE : MaviBelge_Core_Content_Query::DOC_PAGE_SIZE );
	}

	/**
	 * Site içi arama sonuçları: kapıya takılan (onaysız haber, pasif kayıt/yeterlilik) sonuçlar çıkarılır.
	 * Yalnız ana arama sorgusu ve yönetim dışı; `found_posts` toplamı bu süzmeyi yansıtmaz (belgelenen sınır).
	 */
	public static function filter_search_results( $posts, $query ) {
		if ( is_admin() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() || ! $query->is_search() || ! is_array( $posts ) ) {
			return $posts;
		}
		return array_values(
			array_filter(
				$posts,
				function ( $post ) {
					return ! ( $post instanceof WP_Post ) || self::is_public_content( $post->ID ) || current_user_can( 'edit_post', $post->ID );
				}
			)
		);
	}

	/**
	 * True only for a real, currently-public mb_yeterlilik: it exists,
	 * really is that post type, is actually published, and its record
	 * status is 'active' — the exact same rule
	 * get_qualification_results()'s meta_query already applies at query
	 * time. Used both to gate the direct single-post URL (below) and to
	 * decide whether MaviBelge_Core_Catalog_Service::present_fee() may
	 * hand out a permalink to a linked qualification.
	 */
	public static function is_public_qualification( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( 'mb_yeterlilik' !== get_post_type( $post_id ) ) {
			return false;
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}
		return 'active' === get_post_meta( $post_id, '_mb_record_status', true );
	}

	/**
	 * Blocks anonymous/unauthorized public access to a passive
	 * qualification's own single URL with a real 404 — never a silent
	 * redirect or a "coming soon" page that would confirm the post's
	 * existence differently than an ordinary 404 would. A user who can
	 * actually edit the post (author/editor/admin preview) still sees
	 * it normally — this narrows PUBLIC visibility only, it is not a
	 * capability change.
	 */
	public static function maybe_block_passive_qualification() {
		if ( ! is_singular( 'mb_yeterlilik' ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( self::is_public_qualification( $post_id ) ) {
			return;
		}
		if ( current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
