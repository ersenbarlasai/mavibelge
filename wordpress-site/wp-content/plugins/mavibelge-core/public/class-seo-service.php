<?php
/**
 * Faz 9 — SEO hizmeti (WordPress bağlantısı). İş kuralları SAF sınıflardadır: Seo_Meta (title/description/canonical/
 * robots/OG/Twitter), Seo_Schema (schema.org @graph), Seo_Robots (robots.txt/sitemap politikası). Bu sınıf yalnız
 * WordPress kancalarını bağlar ve sayfa modelinden bağlamı toplar.
 *
 * Görsel ve iletişim verisi UYDURULMAZ: sosyal görsel yalnız gerçek öne çıkan görselden, kurum iletişim bilgisi yalnız
 * statik referanstaki doğrulanmış başlık verisinden (filtrelenebilir `mavibelge_core_seo_organization`) gelir. Twitter/X
 * hesabı bilinmiyor: `twitter:site` yalnız `mavibelge_core_seo` seçeneğinde `twitter_site` açıkça verilirse yazılır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Seo_Service {

	const OPTION_KEY = 'mavibelge_core_seo';

	const EDITOR_TITLE_META = '_mb_seo_title';
	const EDITOR_DESC_META  = '_mb_seo_description';
	const EDITOR_NOINDEX_META = '_mb_seo_noindex';

	/** @var array|null */
	private static $pageDefaults = null;

	public static function init() {
		remove_action( 'wp_head', 'rel_canonical' );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots_filter' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'render_head' ), 2 );
		add_action( 'send_headers', array( __CLASS__, 'send_robots_header' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'sitemap_taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'sitemap_providers' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_query_args' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'register_editor_meta' ), 30 );
	}

	/* --------------------------------------------------------------- yapılandırma */

	public static function config() {
		$raw = get_option( self::OPTION_KEY, array() );
		$raw = is_array( $raw ) ? $raw : array();
		$cfg = array(
			'twitter_site'   => isset( $raw['twitter_site'] ) && is_string( $raw['twitter_site'] ) && 1 === preg_match( '/^@[A-Za-z0-9_]{1,15}$/', $raw['twitter_site'] ) ? $raw['twitter_site'] : '',
			'crawler_policy' => MaviBelge_Core_Seo_Robots::normalize_policy( isset( $raw['crawler_policy'] ) ? $raw['crawler_policy'] : array() ),
		);
		return $cfg;
	}

	/** Üretim ortamı mı: WP ortam türü `production` VE MAVIBELGE_ENV staging değil VE "arama motorlarından gizle" kapalı. */
	public static function is_production() {
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( defined( 'MAVIBELGE_ENV' ) && 'production' !== MAVIBELGE_ENV ) {
			return false;
		}
		return (bool) apply_filters( 'mavibelge_core_is_production', 'production' === $env && (bool) get_option( 'blog_public' ) );
	}

	/* ------------------------------------------------------------------ editör alanı */

	public static function register_editor_meta() {
		foreach ( array( 'page', 'mb_yeterlilik', 'mb_haber', 'mb_dokuman', 'mb_lokasyon', 'mb_sss', 'mb_referans' ) as $type ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}
			foreach ( array( self::EDITOR_TITLE_META => 'title', self::EDITOR_DESC_META => 'description' ) as $key => $kind ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => false,
						'sanitize_callback' => function ( $value ) use ( $kind ) {
							$v = MaviBelge_Core_Seo_Meta::validate_editor_field( $kind, $value );
							return $v['ok'] ? $v['value'] : '';
						},
						'auth_callback'     => function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
			register_post_meta(
				$type,
				self::EDITOR_NOINDEX_META,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => function ( $value ) {
						return ( true === $value || '1' === $value || 1 === $value ) ? '1' : '';
					},
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/* ------------------------------------------------------------------ başlık/açıklama */

	private static function defaults_for( $slug ) {
		if ( null === self::$pageDefaults ) {
			$file               = MAVIBELGE_CORE_PATH . 'includes/seo/data/page-defaults.php';
			self::$pageDefaults = is_readable( $file ) ? include $file : array();
			self::$pageDefaults = is_array( self::$pageDefaults ) ? self::$pageDefaults : array();
		}
		return isset( self::$pageDefaults[ $slug ] ) ? self::$pageDefaults[ $slug ] : null;
	}

	/**
	 * Geçerli isteğin SEO modeli: başlık, açıklama, canonical, robots bağlamı ve sosyal alanlar için ham veri.
	 *
	 * @return array
	 */
	public static function page_model() {
		$title = '';
		$desc  = '';
		$type  = 'website';
		$image = '';
		$published = '';
		$modified  = '';
		$editorNoindex = false;
		$url = null;

		if ( is_front_page() ) {
			$d     = self::defaults_for( 'front-page' );
			$title = $d ? $d[0] : get_bloginfo( 'name' );
			$desc  = $d ? $d[1] : (string) get_bloginfo( 'description' );
			$url   = home_url( '/' );
		} elseif ( is_singular() ) {
			$post  = get_queried_object();
			$id    = (int) get_queried_object_id();
			$slug  = $post instanceof WP_Post ? $post->post_name : '';
			$d     = 'page' === get_post_type( $id ) ? self::defaults_for( $slug ) : null;
			$eTitle = (string) get_post_meta( $id, self::EDITOR_TITLE_META, true );
			$eDesc  = (string) get_post_meta( $id, self::EDITOR_DESC_META, true );
			$title  = '' !== $eTitle ? $eTitle : ( $d ? $d[0] : get_the_title( $id ) );
			$desc   = MaviBelge_Core_Seo_Meta::description( $eDesc, $d ? $d[1] : ( $post instanceof WP_Post ? ( '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content ) : '' ) );
			$url    = get_permalink( $id );
			$editorNoindex = '1' === (string) get_post_meta( $id, self::EDITOR_NOINDEX_META, true );
			if ( 'mb_haber' === get_post_type( $id ) ) {
				$type      = 'article';
				$published = (string) get_post_time( 'c', false, $id );
				$modified  = (string) get_post_modified_time( 'c', false, $id );
			}
			if ( has_post_thumbnail( $id ) ) {
				$src   = wp_get_attachment_image_url( get_post_thumbnail_id( $id ), 'large' );
				$image = is_string( $src ) ? $src : '';
			}
		} elseif ( is_post_type_archive( array( 'mb_haber', 'mb_dokuman', 'mb_yeterlilik' ) ) ) {
			$map   = array( 'mb_haber' => 'haberler', 'mb_dokuman' => 'dokumanlar', 'mb_yeterlilik' => 'meslekler' );
			$pt    = (string) get_query_var( 'post_type' );
			$d     = self::defaults_for( isset( $map[ $pt ] ) ? $map[ $pt ] : '' );
			$title = $d ? $d[0] : post_type_archive_title( '', false );
			$desc  = $d ? $d[1] : '';
			$url   = (string) get_post_type_archive_link( $pt );
		} elseif ( is_tax() ) {
			$term  = get_queried_object();
			$title = $term instanceof WP_Term ? $term->name : '';
			$desc  = $term instanceof WP_Term ? MaviBelge_Core_Seo_Meta::description( $term->description, $term->name . ' sektöründeki meslekler ve MYK belgelendirme bilgileri.' ) : '';
			$url   = $term instanceof WP_Term ? (string) get_term_link( $term ) : null;
		} elseif ( is_search() ) {
			$title = 'Arama sonuçları';
		} elseif ( is_404() ) {
			$d     = self::defaults_for( '404' );
			$title = $d ? $d[0] : 'Sayfa Bulunamadı';
			$desc  = $d ? $d[1] : '';
		}
		$page = (int) get_query_var( 'paged' );
		$mbPage = isset( $_GET['mb_page'] ) && is_string( $_GET['mb_page'] ) ? MaviBelge_Core_Content_Query::normalize_page( wp_unslash( $_GET['mb_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pageArg = $mbPage > 1 ? 'mb_page' : 'paged';
		$number  = max( 1, $page, $mbPage );
		return array(
			'title'         => MaviBelge_Core_Seo_Meta::compose_title( $title ),
			'description'   => MaviBelge_Core_Seo_Meta::description( $desc, '' ),
			'url'           => is_string( $url ) ? $url : null,
			'page'          => $number,
			'page_arg'      => $pageArg,
			'type'          => $type,
			'image'         => $image,
			'published'     => $published,
			'modified'      => $modified,
			'editor_noindex' => $editorNoindex,
		);
	}

	private static function query_params() {
		return is_array( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public static function document_title( $title ) {
		if ( is_feed() || is_admin() ) {
			return $title;
		}
		$model = self::page_model();
		return '' !== $model['title'] ? $model['title'] : $title;
	}

	/** wp_robots filtresi: belirteç listesini WordPress'in yönerge dizisine çevirir. */
	public static function robots_filter( $robots ) {
		$model  = self::page_model();
		$tokens = MaviBelge_Core_Seo_Meta::robots(
			array(
				'production'     => self::is_production(),
				'blog_public'    => (bool) get_option( 'blog_public' ),
				'is_404'         => is_404(),
				'is_search'      => is_search(),
				'has_filters'    => MaviBelge_Core_Seo_Meta::has_filters( self::query_params() ),
				'editor_noindex' => $model['editor_noindex'],
			)
		);
		$out = array();
		foreach ( $tokens as $token ) {
			if ( false !== strpos( $token, ':' ) ) {
				list( $k, $v ) = explode( ':', $token, 2 );
				$out[ $k ]     = $v;
			} else {
				$out[ $token ] = true;
			}
		}
		return $out;
	}

	public static function send_robots_header() {
		if ( ! self::is_production() && ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}

	/* ---------------------------------------------------------------- <head> çıktısı */

	public static function render_head() {
		if ( is_feed() || is_admin() ) {
			return;
		}
		$model     = self::page_model();
		$canonical = MaviBelge_Core_Seo_Meta::canonical(
			array( 'url' => $model['url'], 'page' => $model['page'], 'page_arg' => $model['page_arg'], 'is_search' => is_search(), 'is_404' => is_404() )
		);
		$cfg    = self::config();
		$social = MaviBelge_Core_Seo_Meta::social(
			array(
				'title'        => $model['title'],
				'description'  => $model['description'],
				'url'          => $canonical,
				'type'         => $model['type'],
				'image'        => $model['image'],
				'published'    => $model['published'],
				'modified'     => $model['modified'],
				'twitter_site' => $cfg['twitter_site'],
			)
		);
		if ( '' !== $model['description'] && ! is_404() ) {
			printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $model['description'] ) );
		}
		if ( null !== $canonical ) {
			printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $canonical ) );
		}
		if ( ! is_404() ) {
			foreach ( $social['og'] as $property => $content ) {
				printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $property ), esc_attr( $content ) );
			}
			foreach ( $social['twitter'] as $name => $content ) {
				printf( "<meta name=\"%s\" content=\"%s\">\n", esc_attr( $name ), esc_attr( $content ) );
			}
		}
		$json = self::schema_json( $model, $canonical );
		if ( '' !== $json ) {
			echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG/AMP/APOS/QUOT ile kodlanmış.
		}
	}

	/* --------------------------------------------------------------------- şema */

	private static function schema_json( array $model, $canonical ) {
		if ( is_404() || is_search() || null === $canonical ) {
			return '';
		}
		$home = home_url( '/' );
		$org  = apply_filters(
			'mavibelge_core_seo_organization',
			array(
				'name'      => 'Mavi Belge',
				'logo'      => function_exists( 'get_theme_file_uri' ) ? get_theme_file_uri( 'assets/images/logos/header-logo.png' ) : '',
				'telephone' => '+90 850 215 44 22',
				'email'     => 'info@mavibelge.com.tr',
			)
		);
		$ctx = array(
			'home_url'     => $home,
			'site_name'    => 'Mavi Belge',
			'language'     => 'tr-TR',
			'organization' => is_array( $org ) ? $org : array(),
			'page'         => array( 'url' => $canonical, 'name' => $model['title'], 'description' => $model['description'], 'published' => $model['published'], 'modified' => $model['modified'], 'image' => $model['image'] ),
			'breadcrumbs'  => self::breadcrumbs( $canonical, $model['title'] ),
			'entity'       => self::entity(),
		);
		$doc = MaviBelge_Core_Seo_Schema::build( $ctx );
		if ( empty( $doc ) ) {
			return '';
		}
		$problems = MaviBelge_Core_Seo_Schema::validate( $doc );
		if ( ! empty( $problems ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( 'mavibelge-core SEO şema doğrulaması başarısız (varlık düğümü atlandı): ' . implode( ' | ', $problems ) ); // Yalnız hata metni; içerik/kişisel veri yok.
			}
			// Geçersiz varlık düğümü ÜRETİLMEZ: yalnız temel düğümlerle yeniden dene; o da geçersizse hiçbir şey çıkarılmaz.
			$ctx['entity'] = null;
			$doc           = MaviBelge_Core_Seo_Schema::build( $ctx );
			if ( empty( $doc ) || ! empty( MaviBelge_Core_Seo_Schema::validate( $doc ) ) ) {
				return '';
			}
		}
		return MaviBelge_Core_Seo_Schema::to_json( $doc );
	}

	/** @return array[] Ana Sayfa > ... > mevcut sayfa (tema breadcrumb bileşenleriyle aynı yollar). */
	private static function breadcrumbs( $canonical, $title ) {
		$crumbs = array( array( 'name' => 'Ana Sayfa', 'url' => home_url( '/' ) ) );
		if ( is_front_page() ) {
			return array();
		}
		if ( is_singular( 'mb_haber' ) || is_post_type_archive( 'mb_haber' ) ) {
			$crumbs[] = array( 'name' => 'Bilgi Merkezi', 'url' => home_url( '/bilgi-merkezi/' ) );
			if ( is_singular( 'mb_haber' ) ) {
				$crumbs[] = array( 'name' => 'Haberler', 'url' => (string) get_post_type_archive_link( 'mb_haber' ) );
			}
		} elseif ( is_singular( 'mb_dokuman' ) || is_post_type_archive( 'mb_dokuman' ) ) {
			$crumbs[] = array( 'name' => 'Bilgi Merkezi', 'url' => home_url( '/bilgi-merkezi/' ) );
			if ( is_singular( 'mb_dokuman' ) ) {
				$crumbs[] = array( 'name' => 'Dokümanlar', 'url' => (string) get_post_type_archive_link( 'mb_dokuman' ) );
			}
		} elseif ( is_singular( 'mb_yeterlilik' ) ) {
			$crumbs[] = array( 'name' => 'Meslekler', 'url' => (string) get_post_type_archive_link( 'mb_yeterlilik' ) );
		} elseif ( is_singular( 'mb_lokasyon' ) ) {
			$crumbs[] = array( 'name' => 'İletişim', 'url' => home_url( '/iletisim/' ) );
		} elseif ( is_singular( 'page' ) ) {
			foreach ( array_reverse( get_post_ancestors( get_queried_object_id() ) ) as $ancestor ) {
				$crumbs[] = array( 'name' => get_the_title( $ancestor ), 'url' => get_permalink( $ancestor ) );
			}
		} elseif ( is_tax( 'mb_sektor' ) ) {
			$crumbs[] = array( 'name' => 'Meslekler', 'url' => (string) get_post_type_archive_link( 'mb_yeterlilik' ) );
		}
		$name = is_singular() ? get_the_title( get_queried_object_id() ) : preg_replace( '/ — Mavi Belge$/u', '', $title );
		$crumbs[] = array( 'name' => (string) $name, 'url' => $canonical );
		return array_values( array_filter( $crumbs, function ( $c ) {
			return '' !== $c['name'] && ( '' !== $c['url'] );
		} ) );
	}

	/** @return array|null İçeriğe özgü şema varlığı (yalnız GÖRÜNÜR içerik). */
	private static function entity() {
		if ( is_singular( 'mb_haber' ) ) {
			$dto = MaviBelge_Core_Content_Service::build_news_dto( get_queried_object() );
			return array( 'kind' => 'duyuru' === $dto['type_slug'] ? 'announcement' : 'news', 'headline' => $dto['title'], 'published' => $dto['date_iso'], 'modified' => (string) get_post_modified_time( 'c', false ), 'description' => $dto['excerpt'], 'image' => has_post_thumbnail() ? (string) wp_get_attachment_image_url( get_post_thumbnail_id(), 'large' ) : '' );
		}
		if ( is_singular( 'mb_dokuman' ) ) {
			$dto  = MaviBelge_Core_Content_Service::build_document_dto( get_queried_object() );
			$mime = ! empty( $dto['file'] ) ? (string) get_post_mime_type( $dto['file']['id'] ) : '';
			return ! empty( $dto['file'] ) && '' !== $mime ? array( 'kind' => 'document', 'name' => $dto['title'], 'format' => $mime, 'version' => $dto['version'], 'published' => $dto['publish_date'] ) : null;
		}
		if ( is_singular( 'mb_lokasyon' ) ) {
			$dto = MaviBelge_Core_Content_Service::build_location_dto( get_queried_object() );
			return array( 'kind' => 'location', 'name' => $dto['name'], 'address' => $dto['address'], 'telephone' => ! empty( $dto['phones'] ) ? $dto['phones'][0]['display'] : '', 'map' => $dto['map_url'] );
		}
		if ( is_page( 'sss' ) ) {
			$faqs  = MaviBelge_Core_Content_Service::get_faqs( array() );
			$items = array();
			foreach ( $faqs['items'] as $faq ) {
				$items[] = array( 'q' => $faq['question'], 'a' => MaviBelge_Core_Seo_Meta::truncate( MaviBelge_Core_Seo_Meta::plain( $faq['answer_html'] ), 1000 ) );
			}
			return array( 'kind' => 'faq', 'items' => $items );
		}
		if ( is_tax( 'mb_sektor' ) && class_exists( 'MaviBelge_Core_Catalog_Service' ) ) {
			$term    = get_queried_object();
			$results = MaviBelge_Core_Catalog_Service::get_qualification_results( array( 'mb_sector' => $term instanceof WP_Term ? $term->slug : '' ) );
			$items   = array();
			foreach ( $results['items'] as $item ) {
				$items[] = array( 'name' => $item['title'], 'url' => $item['permalink'] );
			}
			return array( 'kind' => 'sector', 'items' => $items );
		}
		return null;
	}

	/* ------------------------------------------------------------ robots.txt / sitemap */

	public static function robots_txt( $output, $public ) {
		return MaviBelge_Core_Seo_Robots::generate(
			array(
				'production'     => self::is_production(),
				'sitemap_url'    => home_url( '/wp-sitemap.xml' ),
				'crawler_policy' => self::config()['crawler_policy'],
			)
		);
	}

	public static function sitemap_post_types( $types ) {
		return is_array( $types ) ? array_intersect_key( $types, array_flip( MaviBelge_Core_Seo_Robots::SITEMAP_POST_TYPES ) ) : array();
	}

	public static function sitemap_taxonomies( $taxonomies ) {
		return is_array( $taxonomies ) ? array_intersect_key( $taxonomies, array_flip( MaviBelge_Core_Seo_Robots::SITEMAP_TAXONOMIES ) ) : array();
	}

	/** Kullanıcı sitemap'i (yazar/kullanıcı adı sızıntısı) KAPALI. */
	public static function sitemap_providers( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/** Sitemap'e yalnız herkese açık kayıtlar: haber onaylı, doküman/lokasyon pasif değil, yeterlilik aktif. */
	public static function sitemap_query_args( $args, $postType ) {
		if ( 'mb_yeterlilik' === $postType ) {
			$args['meta_query'] = array( array( 'key' => '_mb_record_status', 'value' => 'active' ) );
		} elseif ( in_array( $postType, array( 'mb_haber', 'mb_dokuman', 'mb_lokasyon' ), true ) ) {
			$args['meta_query'] = MaviBelge_Core_Content_Service::public_meta_query( $postType );
		}
		return $args;
	}
}
