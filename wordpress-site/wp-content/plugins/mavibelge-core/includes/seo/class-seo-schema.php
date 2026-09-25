<?php
/**
 * Faz 9 — merkezi schema.org SchemaBuilder (SAF; WordPress fonksiyonu çağırmaz). Sayfanın GERÇEK modelinden
 * (görünür içerik) tek bir `@graph` üretir; editör serbest JSON-LD alanı YOKTUR.
 *
 * Düğümler: site geneli `Organization` ve `WebSite` (tek, kararlı @id); sayfa bazlı `WebPage` ve `BreadcrumbList`;
 * içerik bazlı `NewsArticle`/`Article` (haber/duyuru), `FAQPage` (yalnız görünür SSS varsa), `ItemList` (sektör ->
 * yeterlilikler), `DigitalDocument` (yalnız doğrulanmış dosya varsa), `Place` + `PostalAddress` (lokasyon).
 * `Course` KULLANILMAZ; referanslar için şema ÜRETİLMEZ (temsili referans "müşteri" iddiası olur); fiyat/ücret şeması yok.
 *
 * Aynı varlık ASLA birden fazla düğüm olarak üretilmez: her düğümün @id'si benzersizdir ve `validate()` bunu doğrular.
 * JSON kodlaması `</script>` kaçışına karşı JSON_HEX_TAG/AMP/APOS/QUOT ile yapılır.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MaviBelge_Core_Seo_Schema {

	const CONTEXT = 'https://schema.org';

	const ENTITY_KINDS = array( 'news', 'announcement', 'faq', 'sector', 'qualification', 'document', 'location' );

	/**
	 * @param array $ctx bkz. sınıf başı; anahtarlar: home_url, site_name, language, organization, page, breadcrumbs, entity
	 * @return array Boş $ctx -> boş dizi (fail-closed: eksik zorunlu veri şema üretmez).
	 */
	public static function build( array $ctx ) {
		foreach ( array( 'home_url', 'site_name', 'page' ) as $required ) {
			if ( empty( $ctx[ $required ] ) ) {
				return array();
			}
		}
		$home   = rtrim( $ctx['home_url'], '/' ) . '/';
		$orgId  = $home . '#organization';
		$siteId = $home . '#website';
		$page   = $ctx['page'];
		if ( empty( $page['url'] ) || empty( $page['name'] ) ) {
			return array();
		}
		$pageId = $page['url'] . '#webpage';
		$lang   = isset( $ctx['language'] ) ? $ctx['language'] : 'tr-TR';

		$graph = array();
		$graph[] = self::organization( $ctx, $orgId, $home );
		$graph[] = array( '@type' => 'WebSite', '@id' => $siteId, 'url' => $home, 'name' => $ctx['site_name'], 'inLanguage' => $lang, 'publisher' => array( '@id' => $orgId ) );

		$crumbs   = isset( $ctx['breadcrumbs'] ) && is_array( $ctx['breadcrumbs'] ) ? $ctx['breadcrumbs'] : array();
		$crumbId  = $page['url'] . '#breadcrumb';
		$webpage  = array(
			'@type'      => 'WebPage',
			'@id'        => $pageId,
			'url'        => $page['url'],
			'name'       => $page['name'],
			'isPartOf'   => array( '@id' => $siteId ),
			'inLanguage' => $lang,
		);
		if ( ! empty( $page['description'] ) ) {
			$webpage['description'] = $page['description'];
		}
		if ( ! empty( $page['published'] ) ) {
			$webpage['datePublished'] = $page['published'];
		}
		if ( ! empty( $page['modified'] ) ) {
			$webpage['dateModified'] = $page['modified'];
		}
		if ( ! empty( $page['image'] ) && self::is_web_url( $page['image'] ) ) {
			$webpage['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => $page['image'] );
		}
		if ( count( $crumbs ) >= 2 ) {
			$webpage['breadcrumb'] = array( '@id' => $crumbId );
		}
		$graph[] = $webpage;
		if ( count( $crumbs ) >= 2 ) {
			$graph[] = self::breadcrumbs( $crumbId, $crumbs );
		}

		$entity = isset( $ctx['entity'] ) && is_array( $ctx['entity'] ) ? self::entity( $ctx['entity'], $page, $pageId, $orgId ) : null;
		if ( null !== $entity ) {
			$graph[] = $entity;
		}
		return array( '@context' => self::CONTEXT, '@graph' => $graph );
	}

	/**
	 * Üretilen grafiği doğrular: benzersiz @id, çözülen @id başvuruları, zorunlu alanlar, aynı türden tekrar YOK
	 * (Organization/WebSite/WebPage/BreadcrumbList tek). @return string[] hatalar (boş = geçerli)
	 */
	public static function validate( array $doc ) {
		$errors = array();
		if ( ! isset( $doc['@context'], $doc['@graph'] ) || self::CONTEXT !== $doc['@context'] || ! is_array( $doc['@graph'] ) || empty( $doc['@graph'] ) ) {
			return array( '@context/@graph eksik veya geçersiz.' );
		}
		$ids   = array();
		$types = array();
		foreach ( $doc['@graph'] as $i => $node ) {
			if ( ! is_array( $node ) || empty( $node['@type'] ) || empty( $node['@id'] ) ) {
				$errors[] = "düğüm[$i]: @type/@id eksik.";
				continue;
			}
			if ( isset( $ids[ $node['@id'] ] ) ) {
				$errors[] = 'tekrar eden @id: ' . $node['@id'];
			}
			$ids[ $node['@id'] ] = true;
			$types[ $node['@type'] ] = isset( $types[ $node['@type'] ] ) ? $types[ $node['@type'] ] + 1 : 1;
			$errors = array_merge( $errors, self::required_fields( $node ) );
		}
		foreach ( array( 'Organization', 'WebSite', 'WebPage', 'BreadcrumbList' ) as $single ) {
			if ( isset( $types[ $single ] ) && $types[ $single ] > 1 ) {
				$errors[] = $single . ' birden fazla düğüm olarak üretilmiş (çelişkili tekrar).';
			}
		}
		foreach ( array( 'Organization', 'WebSite', 'WebPage' ) as $mandatory ) {
			if ( empty( $types[ $mandatory ] ) ) {
				$errors[] = $mandatory . ' düğümü yok.';
			}
		}
		foreach ( self::references( $doc['@graph'] ) as $ref ) {
			if ( false !== strpos( $ref, '#' ) && ! isset( $ids[ $ref ] ) ) {
				$errors[] = 'çözülemeyen @id başvurusu: ' . $ref;
			}
		}
		return $errors;
	}

	/** Güvenli JSON-LD gövdesi (`<script type="application/ld+json">` içine yazılır); kodlanamazsa ''. */
	public static function to_json( array $doc ) {
		$json = json_encode( $doc, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) && JSON_ERROR_NONE === json_last_error() ? $json : '';
	}

	/* ---------------------------------------------------------------- düğümler */

	private static function organization( array $ctx, $orgId, $home ) {
		$org  = isset( $ctx['organization'] ) && is_array( $ctx['organization'] ) ? $ctx['organization'] : array();
		$node = array( '@type' => 'Organization', '@id' => $orgId, 'name' => isset( $org['name'] ) && '' !== $org['name'] ? $org['name'] : $ctx['site_name'], 'url' => $home );
		if ( ! empty( $org['logo'] ) && self::is_web_url( $org['logo'] ) ) {
			$node['logo'] = array( '@type' => 'ImageObject', 'url' => $org['logo'] );
		}
		$contact = array();
		if ( ! empty( $org['telephone'] ) ) {
			$contact['telephone'] = $org['telephone'];
		}
		if ( ! empty( $org['email'] ) && 1 === preg_match( '/^[^\s@<>"\']+@[^\s@<>"\']+\.[^\s@<>"\']+$/', $org['email'] ) ) {
			$contact['email'] = $org['email'];
		}
		if ( ! empty( $contact ) ) {
			$node['contactPoint'] = array_merge( array( '@type' => 'ContactPoint', 'contactType' => 'customer service', 'availableLanguage' => 'tr' ), $contact );
		}
		return $node;
	}

	private static function breadcrumbs( $crumbId, array $crumbs ) {
		$items = array();
		foreach ( array_values( $crumbs ) as $i => $crumb ) {
			if ( empty( $crumb['name'] ) ) {
				continue;
			}
			$item = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb['name'] );
			if ( ! empty( $crumb['url'] ) && self::is_web_url( $crumb['url'] ) ) {
				$item['item'] = $crumb['url'];
			}
			$items[] = $item;
		}
		return array( '@type' => 'BreadcrumbList', '@id' => $crumbId, 'itemListElement' => $items );
	}

	private static function entity( array $e, array $page, $pageId, $orgId ) {
		$kind = isset( $e['kind'] ) ? $e['kind'] : '';
		switch ( $kind ) {
			case 'news':
			case 'announcement':
				if ( empty( $e['headline'] ) || empty( $e['published'] ) ) {
					return null; // NewsArticle/Article için başlık + yayın tarihi zorunlu.
				}
				$node = array(
					'@type'            => 'news' === $kind ? 'NewsArticle' : 'Article',
					'@id'              => $page['url'] . '#article',
					'headline'         => $e['headline'],
					'datePublished'    => $e['published'],
					'inLanguage'       => 'tr-TR',
					'mainEntityOfPage' => array( '@id' => $pageId ),
					'publisher'        => array( '@id' => $orgId ),
					'author'           => array( '@id' => $orgId ),
				);
				if ( ! empty( $e['modified'] ) ) {
					$node['dateModified'] = $e['modified'];
				}
				if ( ! empty( $e['description'] ) ) {
					$node['description'] = $e['description'];
				}
				if ( ! empty( $e['image'] ) && self::is_web_url( $e['image'] ) ) {
					$node['image'] = array( $e['image'] );
				}
				return $node;
			case 'faq':
				$questions = array();
				foreach ( isset( $e['items'] ) && is_array( $e['items'] ) ? $e['items'] : array() as $item ) {
					if ( empty( $item['q'] ) || empty( $item['a'] ) ) {
						continue;
					}
					$questions[] = array( '@type' => 'Question', 'name' => $item['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $item['a'] ) );
				}
				return empty( $questions ) ? null : array( '@type' => 'FAQPage', '@id' => $page['url'] . '#faq', 'mainEntityOfPage' => array( '@id' => $pageId ), 'mainEntity' => $questions );
			case 'sector':
				$list = array();
				foreach ( isset( $e['items'] ) && is_array( $e['items'] ) ? array_slice( $e['items'], 0, 50 ) : array() as $i => $item ) {
					if ( empty( $item['name'] ) || empty( $item['url'] ) || ! self::is_web_url( $item['url'] ) ) {
						continue;
					}
					$list[] = array( '@type' => 'ListItem', 'position' => count( $list ) + 1, 'name' => $item['name'], 'url' => $item['url'] );
				}
				return empty( $list ) ? null : array( '@type' => 'ItemList', '@id' => $page['url'] . '#list', 'numberOfItems' => count( $list ), 'itemListElement' => $list );
			case 'document':
				if ( empty( $e['name'] ) || empty( $e['format'] ) ) {
					return null; // Yalnız doğrulanmış dosyası olan doküman.
				}
				$node = array( '@type' => 'DigitalDocument', '@id' => $page['url'] . '#document', 'name' => $e['name'], 'fileFormat' => $e['format'], 'inLanguage' => 'tr-TR', 'mainEntityOfPage' => array( '@id' => $pageId ) );
				if ( ! empty( $e['version'] ) ) {
					$node['version'] = $e['version'];
				}
				if ( ! empty( $e['published'] ) ) {
					$node['datePublished'] = $e['published'];
				}
				return $node;
			case 'location':
				if ( empty( $e['name'] ) || empty( $e['address'] ) ) {
					return null;
				}
				$node = array( '@type' => 'Place', '@id' => $page['url'] . '#place', 'name' => $e['name'], 'address' => array( '@type' => 'PostalAddress', 'streetAddress' => $e['address'], 'addressCountry' => 'TR' ) );
				if ( ! empty( $e['telephone'] ) ) {
					$node['telephone'] = $e['telephone'];
				}
				if ( ! empty( $e['map'] ) && self::is_web_url( $e['map'] ) ) {
					$node['hasMap'] = $e['map'];
				}
				return $node;
		}
		return null;
	}

	private static function required_fields( array $node ) {
		$need = array(
			'NewsArticle'    => array( 'headline', 'datePublished' ),
			'Article'        => array( 'headline', 'datePublished' ),
			'FAQPage'        => array( 'mainEntity' ),
			'DigitalDocument' => array( 'name', 'fileFormat' ),
			'Place'          => array( 'name', 'address' ),
			'WebPage'        => array( 'url', 'name' ),
			'Organization'   => array( 'name', 'url' ),
			'WebSite'        => array( 'url', 'name' ),
			'ItemList'       => array( 'itemListElement' ),
			'BreadcrumbList' => array( 'itemListElement' ),
		);
		$errors = array();
		foreach ( isset( $need[ $node['@type'] ] ) ? $need[ $node['@type'] ] : array() as $field ) {
			if ( empty( $node[ $field ] ) ) {
				$errors[] = $node['@type'] . ' düğümünde zorunlu alan eksik: ' . $field;
			}
		}
		return $errors;
	}

	/** Grafikteki tüm `{"@id": ...}` başvurularını toplar. @return string[] */
	private static function references( array $graph ) {
		$refs = array();
		$walk = function ( $value ) use ( &$walk, &$refs ) {
			if ( is_array( $value ) ) {
				if ( 1 === count( $value ) && isset( $value['@id'] ) && is_string( $value['@id'] ) ) {
					$refs[] = $value['@id'];
					return;
				}
				foreach ( $value as $child ) {
					$walk( $child );
				}
			}
		};
		foreach ( $graph as $node ) {
			foreach ( $node as $key => $value ) {
				if ( '@id' !== $key ) {
					$walk( $value );
				}
			}
		}
		return $refs;
	}

	private static function is_web_url( $url ) {
		return is_string( $url ) && 1 === preg_match( '#^https?://[^\s"<>]+$#', $url );
	}
}
