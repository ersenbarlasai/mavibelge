<?php
/**
 * Faz 7 — haber, doküman, referans, lokasyon ve SSS için TEK genel içerik
 * servisi. Tema bu içerik türleri için doğrudan WP_Query/$wpdb KULLANMAZ;
 * yalnız bu sınıfın döndürdüğü kapalı DTO'ları render eder
 * (`inc/content-helpers.php` ince ve fatal-güvenli bir adaptördür). Eklenti
 * pasifken adaptör güvenli boş sonuç döndürür.
 *
 * Görünürlük kuralları (tek yerde):
 * - yalnız `publish` yazılar;
 * - `_mb_record_status === 'passive'` gizlenir (eksik meta = `active`);
 * - haber: ek olarak `_mb_approval_status === 'approved'` (yayınlanmış ama
 *   onaysız haber asla listelenmez);
 * - doküman dosyası: gerçek bir `attachment`, izinli MIME, diskte var; aksi
 *   hâlde bağlantı verilmez (sahte/kırık indirme yok);
 * - referans logosu: gerçek bir görsel attachment (SVG hariç);
 * - harici bağlantılar yalnız https (Content_Query::safe_external_url()).
 * Bütün sonuç kümeleri SINIRLIDIR (sayfa boyutu/limit sabitleri).
 * DTO alanları HAM metindir; kaçış (esc_html/esc_url) şablonun işidir.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Content_Service {

	/**
	 * @param array $raw_args $_GET biçimli dizi (mb_type, mb_page).
	 * @return array{items: array, total: int, total_pages: int, page: int, page_size: int, args: array}
	 */
	public static function get_news( array $raw_args ) {
		$args = MaviBelge_Core_Content_Query::normalize_news_args( $raw_args );
		if ( ! post_type_exists( 'mb_haber' ) ) {
			return self::empty_page( $args, MaviBelge_Core_Content_Query::NEWS_PAGE_SIZE );
		}
		$query = array(
			'post_type'      => 'mb_haber',
			'post_status'    => 'publish',
			'posts_per_page' => MaviBelge_Core_Content_Query::NEWS_PAGE_SIZE,
			'paged'          => $args['page'],
			'orderby'        => array( 'date' => 'DESC', 'ID' => 'DESC' ),
			'meta_query'     => self::public_meta_query( 'mb_haber' ),
			'no_found_rows'  => false,
		);
		if ( '' !== $args['type'] && taxonomy_exists( 'mb_haber_turu' ) ) {
			$query['tax_query'] = array( array( 'taxonomy' => 'mb_haber_turu', 'field' => 'slug', 'terms' => array( $args['type'] ) ) );
		}
		// Faz 10: önbellek anahtarı sayfa+tür ile ayrışır; istenenden farklı (sıkıştırılmış) sayfa SAKLANMAZ (anahtar uzayı sınırlı kalır).
		$result = MaviBelge_Core_Cache::remember(
			'news',
			$args['page'],
			array( 'type' => $args['type'] ),
			function () use ( $query, $args ) {
				return self::run_paged( $query, $args, MaviBelge_Core_Content_Query::NEWS_PAGE_SIZE, array( __CLASS__, 'build_news_dto' ) );
			},
			function ( $fresh ) use ( $args ) {
				return isset( $fresh['page'] ) && $fresh['page'] === $args['page'];
			}
		);
		self::warm_attachments( $result['items'], 'thumbnail_id' );
		return $result;
	}

	/**
	 * Ana sayfa şeridi için en yeni N (varsayılan 3, en çok 6) onaylı haber/duyuru.
	 *
	 * @return array[]
	 */
	public static function get_latest_news( $limit = 3 ) {
		$limit = max( 1, min( 6, (int) $limit ) );
		if ( ! post_type_exists( 'mb_haber' ) ) {
			return array();
		}
		$items = MaviBelge_Core_Cache::remember(
			'latest_news',
			1,
			array( 'limit' => $limit ),
			function () use ( $limit ) {
				$query = new WP_Query(
					array(
						'post_type'           => 'mb_haber',
						'post_status'         => 'publish',
						'posts_per_page'      => $limit,
						'orderby'             => array( 'date' => 'DESC', 'ID' => 'DESC' ),
						'meta_query'          => self::public_meta_query( 'mb_haber' ),
						'no_found_rows'       => true,
						'ignore_sticky_posts' => true,
					)
				);
				return array_map( array( __CLASS__, 'build_news_dto' ), $query->posts );
			}
		);
		self::warm_attachments( $items, 'thumbnail_id' );
		return $items;
	}

	/**
	 * @param array $raw_args $_GET biçimli dizi (mb_cat, mb_page).
	 * @return array{items: array, total: int, total_pages: int, page: int, page_size: int, args: array, categories: array}
	 */
	public static function get_documents( array $raw_args ) {
		$args = MaviBelge_Core_Content_Query::normalize_document_args( $raw_args );
		if ( ! post_type_exists( 'mb_dokuman' ) ) {
			$empty               = self::empty_page( $args, MaviBelge_Core_Content_Query::DOC_PAGE_SIZE );
			$empty['categories'] = array();
			return $empty;
		}
		$query = array(
			'post_type'      => 'mb_dokuman',
			'post_status'    => 'publish',
			'posts_per_page' => MaviBelge_Core_Content_Query::DOC_PAGE_SIZE,
			'paged'          => $args['page'],
			'orderby'        => array( 'date' => 'DESC', 'ID' => 'DESC' ),
			'meta_query'     => self::public_meta_query( 'mb_dokuman' ),
			'no_found_rows'  => false,
		);
		$categories = self::get_category_terms( 'mb_dokuman_kategori' );
		if ( '' !== $args['category'] ) {
			if ( ! self::category_exists( $categories, $args['category'] ) ) {
				// Var olmayan kategori: filtreyi sessizce yok saymak yerine SIFIR sonuç.
				$empty               = self::empty_page( $args, MaviBelge_Core_Content_Query::DOC_PAGE_SIZE );
				$empty['categories'] = $categories;
				return $empty;
			}
			$query['tax_query'] = array( array( 'taxonomy' => 'mb_dokuman_kategori', 'field' => 'slug', 'terms' => array( $args['category'] ) ) );
		}
		// Faz 10: kategori yukarıda var olduğu doğrulandı (anahtar uzayı sınırlı); süre dolumu tarihe bağlı olduğundan gün anahtara girer.
		$result = MaviBelge_Core_Cache::remember(
			'docs',
			$args['page'],
			array( 'category' => $args['category'], 'day' => self::today() ),
			function () use ( $query, $args ) {
				return self::run_paged( $query, $args, MaviBelge_Core_Content_Query::DOC_PAGE_SIZE, array( __CLASS__, 'build_document_dto' ), '_mb_attachment_id' );
			},
			function ( $fresh ) use ( $args ) {
				return isset( $fresh['page'] ) && $fresh['page'] === $args['page'];
			}
		);
		$result['categories'] = $categories;
		return $result;
	}

	/** @return array[] Aktif referanslar (sıra -> başlık -> ID), en çok REFERENCE_LIMIT. */
	public static function get_references() {
		$items = self::get_sorted_active( 'mb_referans', MaviBelge_Core_Content_Query::REFERENCE_LIMIT, array( __CLASS__, 'build_reference_dto' ), array(), array(), '_mb_logo_attachment_id' );
		self::warm_attachments( $items, 'logo_id' );
		return $items;
	}

	/** @return array[] Aktif lokasyonlar (sıra -> başlık -> ID), en çok LOCATION_LIMIT. */
	public static function get_locations() {
		return self::get_sorted_active( 'mb_lokasyon', MaviBelge_Core_Content_Query::LOCATION_LIMIT, array( __CLASS__, 'build_location_dto' ) );
	}

	/**
	 * @param array $raw_args $_GET biçimli dizi (mb_cat).
	 * @return array{items: array, categories: array, args: array}
	 */
	public static function get_faqs( array $raw_args ) {
		$args       = MaviBelge_Core_Content_Query::normalize_faq_args( $raw_args );
		$categories = post_type_exists( 'mb_sss' ) ? self::get_category_terms( 'mb_sss_kategori' ) : array();
		if ( '' !== $args['category'] && ! self::category_exists( $categories, $args['category'] ) ) {
			return array( 'items' => array(), 'categories' => $categories, 'args' => $args );
		}
		$extra = '' === $args['category'] || ! taxonomy_exists( 'mb_sss_kategori' ) ? array() : array( 'tax_query' => array( array( 'taxonomy' => 'mb_sss_kategori', 'field' => 'slug', 'terms' => array( $args['category'] ) ) ) );
		return array(
			'items'      => self::get_sorted_active( 'mb_sss', MaviBelge_Core_Content_Query::FAQ_LIMIT, array( __CLASS__, 'build_faq_dto' ), $extra, array( 'category' => $args['category'] ) ),
			'categories' => $categories,
			'args'       => $args,
		);
	}

	/* --------------------------------------------------------------- DTO'lar */

	/** @return array */
	public static function build_news_dto( $post ) {
		$terms = taxonomy_exists( 'mb_haber_turu' ) ? get_the_terms( $post->ID, 'mb_haber_turu' ) : array();
		$term  = is_array( $terms ) && ! empty( $terms ) ? reset( $terms ) : null;
		$excerpt = '' !== trim( (string) $post->post_excerpt ) ? (string) $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		return array(
			'id'           => (int) $post->ID,
			'title'        => (string) get_the_title( $post ),
			'permalink'    => (string) get_permalink( $post ),
			'date_iso'     => (string) get_post_time( 'c', false, $post ),
			'date_display' => (string) get_the_date( '', $post ),
			'type_slug'    => $term ? (string) $term->slug : '',
			'type_label'   => $term ? (string) $term->name : '',
			'excerpt'      => wp_trim_words( $excerpt, 32, '…' ),
			'thumbnail_id' => (int) get_post_thumbnail_id( $post ),
		);
	}

	/** @return array */
	public static function build_document_dto( $post ) {
		$file  = self::document_file( (int) get_post_meta( $post->ID, '_mb_attachment_id', true ) );
		$until = (string) get_post_meta( $post->ID, '_mb_valid_until', true );
		return array(
			'id'           => (int) $post->ID,
			'title'        => (string) get_the_title( $post ),
			'permalink'    => (string) get_permalink( $post ),
			'version'      => (string) get_post_meta( $post->ID, '_mb_document_version', true ),
			'publish_date' => (string) get_post_meta( $post->ID, '_mb_publish_date', true ),
			'valid_until'  => $until,
			'expired'      => MaviBelge_Core_Content_Query::is_document_expired( $until, self::today() ),
			'file'         => $file,
			'categories'   => self::term_pairs( $post->ID, 'mb_dokuman_kategori' ),
		);
	}

	/** @return array */
	public static function build_reference_dto( $post ) {
		$status = (string) get_post_meta( $post->ID, '_mb_reference_status', true );
		return array(
			'id'          => (int) $post->ID,
			'name'        => (string) get_the_title( $post ),
			'website_url' => MaviBelge_Core_Content_Query::safe_external_url( (string) get_post_meta( $post->ID, '_mb_website_url', true ) ),
			'logo_id'     => self::valid_logo_id( (int) get_post_meta( $post->ID, '_mb_logo_attachment_id', true ) ),
			'status'      => 'real' === $status ? 'real' : 'representative',
		);
	}

	/** @return array */
	public static function build_location_dto( $post ) {
		$phones = get_post_meta( $post->ID, '_mb_phone_numbers', true );
		$phones = is_array( $phones ) ? array_values( array_filter( array_map( 'strval', $phones ), 'strlen' ) ) : array();
		return array(
			'id'      => (int) $post->ID,
			'name'    => (string) get_the_title( $post ),
			'address' => (string) get_post_meta( $post->ID, '_mb_address', true ),
			'phones'  => array_map(
				function ( $phone ) {
					return array( 'display' => $phone, 'tel' => 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) );
				},
				$phones
			),
			'map_url' => MaviBelge_Core_Content_Query::safe_external_url( (string) get_post_meta( $post->ID, '_mb_map_url', true ) ),
			'hours'   => (string) get_post_meta( $post->ID, '_mb_working_hours', true ),
		);
	}

	/** @return array */
	public static function build_faq_dto( $post ) {
		return array(
			'id'          => (int) $post->ID,
			'question'    => (string) get_the_title( $post ),
			'answer_html' => wp_kses_post( wpautop( self::strip_active_content( (string) $post->post_content ) ) ),
			'categories'  => self::term_pairs( $post->ID, 'mb_sss_kategori' ),
		);
	}

	/* ------------------------------------------------------------- yardımcılar */

	/** <script>/<style> ÖĞELERİNİ içerikleriyle birlikte kaldırır (kses yalnız etiketi siler, içindeki metni bırakırdı). */
	private static function strip_active_content( $html ) {
		$clean = preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $html );
		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * Bir içerik türünün HERKESE AÇIK görünürlük kapısı (tek tanım): haber -> onaylı; doküman/referans/
	 * lokasyon/SSS -> `passive` olmayan (eksik meta görünür). Diğer türler için boş dizi.
	 * Hem servis sorguları hem ana sorgu (Visibility_Guard) bunu kullanır; ikinci bir kopya yoktur.
	 *
	 * @return array
	 */
	public static function public_meta_query( $postType ) {
		if ( 'mb_haber' === $postType ) {
			return array( array( 'key' => '_mb_approval_status', 'value' => 'approved' ) );
		}
		if ( in_array( $postType, array( 'mb_dokuman', 'mb_referans', 'mb_lokasyon', 'mb_sss' ), true ) ) {
			return self::active_meta_query();
		}
		return array();
	}

	/** Yayında (publish) VE `passive` olmayan meta kapısı; eksik meta görünür. */
	private static function active_meta_query() {
		return array(
			'relation' => 'OR',
			array( 'key' => '_mb_record_status', 'value' => MaviBelge_Core_Content_Query::STATUS_PASSIVE, 'compare' => '!=' ),
			array( 'key' => '_mb_record_status', 'compare' => 'NOT EXISTS' ),
		);
	}

	/**
	 * @param array  $cacheFilters Önbellek anahtarına giren filtreler (yalnız doğrulanmış/sınırlı değerler).
	 * @param string $attachmentMetaKey Doluysa listedeki yazıların bu meta anahtarındaki ek kimlikleri toplu ısıtılır (N+1 yok).
	 */
	private static function get_sorted_active( $postType, $limit, $builder, array $extra = array(), array $cacheFilters = array(), $attachmentMetaKey = '' ) {
		if ( ! post_type_exists( $postType ) ) {
			return array();
		}
		return MaviBelge_Core_Cache::remember(
			'sorted_' . $postType,
			1,
			array_merge( $cacheFilters, array( 'limit' => (int) $limit ) ),
			function () use ( $postType, $limit, $builder, $extra, $attachmentMetaKey ) {
				return self::build_sorted_active( $postType, $limit, $builder, $extra, $attachmentMetaKey );
			}
		);
	}

	private static function build_sorted_active( $postType, $limit, $builder, array $extra, $attachmentMetaKey ) {
		$query = new WP_Query(
			array_merge(
				array(
					'post_type'           => $postType,
					'post_status'         => 'publish',
					'posts_per_page'      => $limit,
					'orderby'             => 'ID',
					'order'               => 'ASC',
					'meta_query'          => self::public_meta_query( $postType ),
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				),
				$extra
			)
		);
		if ( '' !== $attachmentMetaKey ) {
			self::prime_attachments_from_meta( $query->posts, $attachmentMetaKey );
		}
		$rows = array();
		foreach ( $query->posts as $post ) {
			$order  = (int) get_post_meta( $post->ID, '_mb_sort_order', true );
			$rows[] = array( 'order' => max( 0, $order ), 'title' => (string) get_the_title( $post ), 'id' => (int) $post->ID, 'post' => $post );
		}
		usort(
			$rows,
			function ( $a, $b ) {
				if ( $a['order'] !== $b['order'] ) {
					return $a['order'] < $b['order'] ? -1 : 1;
				}
				$byTitle = strcmp( $a['title'], $b['title'] );
				return 0 !== $byTitle ? $byTitle : ( $a['id'] < $b['id'] ? -1 : 1 );
			}
		);
		return array_map(
			function ( $row ) use ( $builder ) {
				return call_user_func( $builder, $row['post'] );
			},
			$rows
		);
	}

	/**
	 * N+1 önleme: bir liste yazısının meta'sındaki ek (attachment) kimliklerini TEK toplu sorguyla ısıtır
	 * (ek yazıları + ek meta'sı). DTO kurulurken get_post_type()/get_attached_file() tekrar sorgu atmaz.
	 */
	private static function prime_attachments_from_meta( array $posts, $metaKey ) {
		$ids = array();
		foreach ( $posts as $post ) {
			$id = (int) get_post_meta( $post->ID, $metaKey, true );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		self::prime_post_ids( $ids );
	}

	/** DTO listesindeki ek kimliklerini (`$field` alanı) toplu ısıtır; şablonun görsel çıktısı sorgu atmaz. */
	private static function warm_attachments( array $items, $field ) {
		$ids = array();
		foreach ( $items as $item ) {
			if ( isset( $item[ $field ] ) && (int) $item[ $field ] > 0 ) {
				$ids[] = (int) $item[ $field ];
			}
		}
		self::prime_post_ids( $ids );
	}

	private static function prime_post_ids( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return;
		}
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, true );
		} elseif ( function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $ids );
		}
	}

	private static function run_paged( array $query, array $args, $pageSize, $builder, $attachmentMetaKey = '' ) {
		$first      = new WP_Query( $query );
		$total      = (int) $first->found_posts;
		$totalPages = MaviBelge_Core_Content_Query::total_pages( $total, $pageSize );
		$page       = MaviBelge_Core_Content_Query::clamp_page( $args['page'], $totalPages );
		$posts      = $first->posts;
		if ( $page !== $args['page'] ) {
			$query['paged'] = $page;
			$again          = new WP_Query( $query );
			$posts          = $again->posts;
		}
		if ( '' !== $attachmentMetaKey ) {
			self::prime_attachments_from_meta( $posts, $attachmentMetaKey );
		}
		return array(
			'items'       => array_map( $builder, $posts ),
			'total'       => $total,
			'total_pages' => $totalPages,
			'page'        => $page,
			'page_size'   => $pageSize,
			'args'        => $args,
		);
	}

	private static function empty_page( array $args, $pageSize ) {
		return array( 'items' => array(), 'total' => 0, 'total_pages' => 1, 'page' => 1, 'page_size' => $pageSize, 'args' => $args );
	}

	/** @return array<int, array{slug: string, name: string}> */
	private static function get_category_terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 100, 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return array_map(
			function ( $term ) {
				return array( 'slug' => (string) $term->slug, 'name' => (string) $term->name );
			},
			$terms
		);
	}

	private static function category_exists( array $categories, $slug ) {
		foreach ( $categories as $category ) {
			if ( $category['slug'] === $slug ) {
				return true;
			}
		}
		return false;
	}

	/** @return array<int, array{slug: string, name: string}> */
	private static function term_pairs( $postId, $taxonomy ) {
		$terms = taxonomy_exists( $taxonomy ) ? get_the_terms( $postId, $taxonomy ) : array();
		if ( ! is_array( $terms ) ) {
			return array();
		}
		return array_values(
			array_map(
				function ( $term ) {
					return array( 'slug' => (string) $term->slug, 'name' => (string) $term->name );
				},
				$terms
			)
		);
	}

	/**
	 * Güvenli doküman dosyası: gerçek attachment + izinli MIME + diskte var. Aksi hâlde null
	 * (kırık/sahte indirme bağlantısı üretilmez).
	 *
	 * @return array{id: int, url: string, type_label: string, size: string}|null
	 */
	private static function document_file( $attachmentId ) {
		if ( $attachmentId <= 0 || 'attachment' !== get_post_type( $attachmentId ) ) {
			return null;
		}
		$label = MaviBelge_Core_Content_Query::document_type_label( get_post_mime_type( $attachmentId ) );
		$path  = get_attached_file( $attachmentId );
		$url   = wp_get_attachment_url( $attachmentId );
		if ( null === $label || ! is_string( $path ) || ! is_readable( $path ) || ! is_string( $url ) || '' === $url ) {
			return null;
		}
		$size = filesize( $path );
		return array(
			'id'         => $attachmentId,
			'url'        => $url,
			'type_label' => $label,
			'size'       => MaviBelge_Core_Content_Query::format_bytes( false === $size ? -1 : (int) $size ),
		);
	}

	/** @return int Geçerli logo attachment ID'si; aksi hâlde 0. */
	private static function valid_logo_id( $attachmentId ) {
		if ( $attachmentId <= 0 || 'attachment' !== get_post_type( $attachmentId ) || ! MaviBelge_Core_Content_Query::is_allowed_logo_mime( get_post_mime_type( $attachmentId ) ) ) {
			return 0;
		}
		return $attachmentId;
	}

	private static function today() {
		return function_exists( 'current_time' ) ? (string) current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	}
}
