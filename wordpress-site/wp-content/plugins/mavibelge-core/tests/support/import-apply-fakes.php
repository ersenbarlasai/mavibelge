<?php
/**
 * Faz 6B3 — `tests/run.php` için SAHTE, bellek içi WordPress dünyası.
 *
 * Gerçek WordPress'in yerine geçmez; apply/rollback servislerinin karar,
 * sıra, batch, transaction, checkpoint, audit ve rollback mantığını
 * WordPress'siz sınamak içindir. Gerçek davranış ayrıca izole WordPress
 * 6.9.9 runtime testinde doğrulanır.
 *
 * Sadakat için:
 * - Meta değerleri WordPress'in sakladığı biçimde tutulur (int -> "5",
 *   true -> "1", false -> "", dizi -> dizi); repository katı
 *   dönüştürücülerle okur.
 * - Hedef okuma, gerçek repository'nin PUBLIC SAF kurucularını
 *   (`*_fields_from_raw()`, `marker_raw_matches()`,
 *   `natural_key_state_from_marker()` ...) kullanır — ikinci bir alan
 *   dönüştürme kuralı yazılmaz.
 * - Transaction: begin() tüm dünya durumunun anlık görüntüsünü alır,
 *   rollback() onu geri yükler (terimler, postlar, run/item, audit dahil).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MB_Fake_World {
	public $terms       = array();
	public $posts       = array();
	public $attachments = array();
	/** Faz 12b — logo attachment'ları: id => sha256 (içerik özeti; gerçek WordPress'te dosyanın hash'i). */
	public $logoAttachments = array();
	public $runs        = array();
	public $items       = array();
	public $audit       = array();
	public $nextId      = 100;
	public $nextRunId   = 1;
	public $nextItemId  = 1;
	/** Hata enjeksiyonu: array( 'op' => ..., 'source_key' => ... ) listesi. */
	public $faults      = array();
	public $writeLog    = array();
	/** Faz 12c — fiziksel dosya kaydı (DB rollback ile GERİ ALINMAZ): ad => true. */
	public $files = array();
	/** Faz 12c — telafi hata enjeksiyonu: unlink başarısız olsun. */
	public $unlinkFails = false;
	/** Faz 6B4 — plan snapshot satırları: run id => seq => item. */
	public $planItems   = array();

	public function state() {
		return array( $this->terms, $this->posts, $this->runs, $this->items, $this->audit, $this->nextRunId, $this->nextItemId, $this->planItems, $this->logoAttachments );
	}

	/** Geri yükleme; 'external' işaretli kayıtlar (başka bir bağlantının transaction DIŞI yazımı simülasyonu) korunur. */
	public function restore( array $s ) {
		$externalTerms = array_filter(
			$this->terms,
			function ( $t ) {
				return ! empty( $t['external'] );
			}
		);
		list( $this->terms, $this->posts, $this->runs, $this->items, $this->audit, $this->nextRunId, $this->nextItemId, $this->planItems, $this->logoAttachments ) = $s;
		$this->terms = $this->terms + $externalTerms;
	}

	public function fault( $op, $key ) {
		foreach ( $this->faults as $f ) {
			if ( $f['op'] === $op && ( ! isset( $f['source_key'] ) || $f['source_key'] === $key ) ) {
				return true;
			}
		}
		return false;
	}

	/** WordPress'in sakladığı temsil. */
	public static function stored( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		return $value;
	}

	/** Sayım için: trash dahil/haric post listesi. */
	public function posts_of_type( $postType, $includeTrash ) {
		$out = array();
		foreach ( $this->posts as $id => $p ) {
			if ( $p['post_type'] === $postType && ( $includeTrash || 'trash' !== $p['status'] ) ) {
				$out[ $id ] = $p;
			}
		}
		return $out;
	}
}

class MB_Fake_World_Repository implements MaviBelge_Core_Import_Target_Repository, MaviBelge_Core_Import_Content_Dependency_Resolver {
	private $w;
	/** Faz 6B4 — DOĞRULANMIŞ açık sektör görsel eşlemesi (slug => attachment ID); gerçek repository ile aynı öncelik kuralı. */
	private $imageMap = array();
	private $diagnostics = array();

	public function __construct( MB_Fake_World $world, array $imageMap = array() ) {
		$this->w        = $world;
		$this->imageMap = $imageMap;
	}

	public function get_diagnostics() {
		return $this->diagnostics;
	}

	public function find_target_by_source_key( $type, $sourceKey ) {
		$candidates = array();
		foreach ( $this->w->terms as $id => $t ) {
			if ( 'mb_sektor' === $t['taxonomy'] && isset( $t['meta']['_mb_import_source_key'] ) && $t['meta']['_mb_import_source_key'] === $sourceKey ) {
				$candidates[] = array( 'type' => 'sector', 'id' => $id );
			}
		}
		foreach ( array( 'qualification' => 'mb_yeterlilik', 'fee' => 'mb_ucret', 'news' => 'mb_haber', 'reference' => 'mb_referans', 'faq' => 'mb_sss', 'page' => 'page' ) as $importType => $postType ) {
			foreach ( $this->w->posts_of_type( $postType, false ) as $id => $p ) {
				if ( isset( $p['meta']['_mb_import_source_key'] ) && $p['meta']['_mb_import_source_key'] === $sourceKey ) {
					$candidates[] = array( 'type' => $importType, 'id' => $id );
				}
			}
		}
		if ( 0 === count( $candidates ) ) {
			return array( 'target_found' => false, 'natural_key' => $this->natural_key_state( $type, $sourceKey ) );
		}
		if ( count( $candidates ) > 1 ) {
			return array( 'target_found' => false, 'duplicate_targets' => true );
		}
		$c = $candidates[0];
		if ( $c['type'] !== $type ) {
			return array( 'target_found' => true, 'target_id' => $c['id'], 'duplicate_targets' => false, 'target_type_matches' => false, 'has_source_key_marker' => true, 'last_applied_hash' => null, 'current_managed_fields' => null );
		}
		$R = 'MaviBelge_Core_Import_WordPress_Target_Repository';
		if ( 'sector' === $type ) {
			$t    = $this->w->terms[ $c['id'] ];
			$hash = $R::normalize_last_applied_hash_raw( $this->meta( $t, '_mb_last_applied_hash' ) );
			if ( ! $hash['ok'] ) {
				return array( 'target_found' => 'invalid_meta' );
			}
			$fields = $R::sector_fields_from_raw(
				array(
					'slug'                => $t['slug'],
					'name'                => $t['name'],
					'description'         => $t['description'],
					'icon_key'            => $this->meta( $t, '_mb_icon_key' ),
					'image_attachment_id' => $this->meta( $t, '_mb_image_attachment_id' ),
				)
			);
		} else {
			$p    = $this->w->posts[ $c['id'] ];
			$hash = $R::normalize_last_applied_hash_raw( $this->meta( $p, '_mb_last_applied_hash' ) );
			if ( ! $hash['ok'] ) {
				return array( 'target_found' => 'invalid_meta' );
			}
			if ( 'news' === $type ) {
				$fields = $R::news_fields_from_raw(
					array(
						'slug'               => $p['name'],
						'title'              => $p['title'],
						'content'            => $p['content'],
						'excerpt'            => $p['excerpt'],
						'post_date'          => $p['date'],
						'news_type_term_ids' => isset( $p['terms']['mb_haber_turu'] ) ? $p['terms']['mb_haber_turu'] : array(),
						'approval_status'    => $this->meta( $p, '_mb_approval_status' ),
					)
				);
			} elseif ( 'page' === $type ) {
				$fields = $R::page_fields_from_raw(
					array(
						'slug'       => $p['name'],
						'title'      => $p['title'],
						'content'    => $p['content'],
						'excerpt'    => $p['excerpt'],
						'parent_id'  => isset( $p['parent'] ) ? $p['parent'] : 0,
						'menu_order' => isset( $p['menu_order'] ) ? $p['menu_order'] : 0,
					)
				);
			} elseif ( 'reference' === $type ) {
				$attId  = $this->meta( $p, '_mb_logo_attachment_id' );
				$attKey = is_string( $attId ) && 1 === preg_match( '/^[1-9][0-9]*\z/', $attId ) ? (int) $attId : 0;
				$fields = $R::reference_fields_from_raw(
					array(
						'slug'             => $p['name'],
						'title'            => $p['title'],
						'reference_status' => $this->meta( $p, '_mb_reference_status' ),
						'record_status'    => $this->meta( $p, '_mb_record_status' ),
						'sort_order'       => $this->meta( $p, '_mb_sort_order' ),
						'website_url'      => $this->meta( $p, '_mb_website_url' ),
						'logo_sha256'      => isset( $this->w->logoAttachments[ $attKey ] ) ? $this->w->logoAttachments[ $attKey ] : '',
					)
				);
			} elseif ( 'faq' === $type ) {
				$fields = $R::faq_fields_from_raw(
					array(
						'slug'          => $p['name'],
						'title'         => $p['title'],
						'content'       => $p['content'],
						'sort_order'    => $this->meta( $p, '_mb_sort_order' ),
						'record_status' => $this->meta( $p, '_mb_record_status' ),
					)
				);
			} elseif ( 'qualification' === $type ) {
				$fields = $R::qualification_fields_from_raw(
					array(
						'title'           => $p['title'],
						'myk_code'        => $this->meta( $p, '_mb_myk_code' ),
						'level'           => $this->meta( $p, '_mb_level' ),
						'revision'        => $this->meta( $p, '_mb_revision' ),
						'record_status'   => $this->meta( $p, '_mb_record_status' ),
						'sector_term_ids' => isset( $p['terms']['mb_sektor'] ) ? $p['terms']['mb_sektor'] : array(),
					)
				);
			} else {
				$raw = array( 'title' => $p['title'] );
				$map = array(
					'profession_name' => '_mb_profession_name', 'level' => '_mb_level', 'sector_slug' => '_mb_sector_slug',
					'qualification_post_id' => '_mb_qualification_id', 'qualification_code' => '_mb_qualification_code',
					'pricing_type' => '_mb_pricing_type', 'price_options' => '_mb_price_options', 'vat_included' => '_mb_vat_included',
					'certificate_print_fee_kurus' => '_mb_certificate_print_fee_kurus', 'source_name' => '_mb_source_name',
					'source_page' => '_mb_source_page', 'source_attachment_id' => '_mb_source_attachment_id',
					'tariff_period' => '_mb_tariff_period', 'record_status' => '_mb_record_status',
					'valid_from' => '_mb_valid_from', 'valid_until' => '_mb_valid_until',
				);
				foreach ( $map as $field => $key ) {
					$raw[ $field ] = $this->meta( $p, $key );
				}
				$fields = $R::fee_fields_from_raw( $raw );
			}
		}
		return array(
			'target_found'           => true,
			'target_id'              => $c['id'],
			'duplicate_targets'      => false,
			'target_type_matches'    => true,
			'has_source_key_marker'  => true,
			'last_applied_hash'      => $hash['value'],
			'current_managed_fields' => $fields,
		);
	}

	private function meta( array $obj, $key ) {
		return isset( $obj['meta'][ $key ] ) ? $obj['meta'][ $key ] : '';
	}

	private function natural_key_state( $type, $sourceKey ) {
		$R   = 'MaviBelge_Core_Import_WordPress_Target_Repository';
		$key = $R::natural_key_from_source_key( $type, $sourceKey );
		if ( null === $key ) {
			return 'query_error';
		}
		$ids = array();
		if ( 'sector' === $type ) {
			foreach ( $this->w->terms as $id => $t ) {
				if ( 'mb_sektor' === $t['taxonomy'] && $t['slug'] === $key['slug'] ) {
					$ids[] = $id;
				}
			}
		} elseif ( 'news' === $type || 'reference' === $type || 'faq' === $type || 'page' === $type ) {
			foreach ( $this->w->posts_of_type( 'news' === $type ? 'mb_haber' : ( 'page' === $type ? 'page' : ( 'faq' === $type ? 'mb_sss' : 'mb_referans' ) ), true ) as $id => $p ) {
				// WordPress çöpteki postun post_name'ine `__trashed` ekler ve asıl slug'ı `_wp_desired_post_slug`
				// olarak saklar (fake: 'desired_slug'); doğal anahtar ikisini de dikkate alır.
				if ( ( isset( $p['name'] ) && $p['name'] === $key['slug'] ) || ( 'trash' === $p['status'] && isset( $p['desired_slug'] ) && $p['desired_slug'] === $key['slug'] ) ) {
					$ids[] = $id;
				}
			}
		} elseif ( 'qualification' === $type ) {
			foreach ( $this->w->posts_of_type( 'mb_yeterlilik', true ) as $id => $p ) {
				if ( $this->meta( $p, '_mb_myk_code' ) === $key['code'] ) {
					$ids[] = $id;
				}
			}
		} else {
			foreach ( $this->w->posts_of_type( 'mb_ucret', true ) as $id => $p ) {
				if ( $this->meta( $p, '_mb_sector_slug' ) === $key['sector_slug'] && $this->meta( $p, '_mb_level' ) === $key['level']
					&& MaviBelge_Core_Import_Record_Validator::profession_slug( $this->meta( $p, '_mb_profession_name' ) ) === $key['profession_slug'] ) {
					$ids[] = $id;
				}
			}
		}
		if ( 0 === count( $ids ) ) {
			return 'none';
		}
		if ( count( $ids ) > 1 ) {
			return 'duplicate';
		}
		$obj = 'sector' === $type ? $this->w->terms[ $ids[0] ] : $this->w->posts[ $ids[0] ];
		return $R::natural_key_state_from_marker( isset( $obj['meta']['_mb_import_source_key'] ) ? $obj['meta']['_mb_import_source_key'] : '', $type, $sourceKey );
	}

	public function resolve_news_type_term_id( $typeSlug ): ?array {
		foreach ( $this->w->terms as $id => $t ) {
			if ( 'mb_haber_turu' === $t['taxonomy'] && $t['slug'] === $typeSlug ) {
				return array( 'id' => $id, 'type_verified' => true );
			}
		}
		return null;
	}

	public function resolve_sector_term_id( $sectorSlug ): ?array {
		foreach ( $this->w->terms as $id => $t ) {
			if ( 'mb_sektor' === $t['taxonomy'] && $t['slug'] === $sectorSlug ) {
				return array( 'id' => $id, 'type_verified' => true );
			}
		}
		return null;
	}

	public function resolve_qualification_post_id( $mykCode ): ?array {
		$ids = array();
		foreach ( $this->w->posts_of_type( 'mb_yeterlilik', false ) as $id => $p ) {
			if ( $this->meta( $p, '_mb_myk_code' ) === $mykCode ) {
				$ids[] = $id;
			}
		}
		return 1 === count( $ids ) ? array( 'id' => $ids[0], 'type_verified' => true ) : null;
	}

	public function resolve_sector_image_attachment_id( $sectorSlug ): ?array {
		$explicit = isset( $this->imageMap[ $sectorSlug ] ) && in_array( $this->imageMap[ $sectorSlug ], $this->w->attachments, true ) ? $this->imageMap[ $sectorSlug ] : null;
		$metaId   = null;
		foreach ( $this->w->terms as $t ) {
			if ( 'mb_sektor' === $t['taxonomy'] && $t['slug'] === $sectorSlug ) {
				$att    = (int) $this->meta( $t, '_mb_image_attachment_id' );
				$metaId = $att > 0 && in_array( $att, $this->w->attachments, true ) ? $att : null;
				break;
			}
		}
		$merged = MaviBelge_Core_Import_WordPress_Target_Repository::merge_sector_image_sources( $explicit, $metaId );
		if ( $merged['conflict'] ) {
			$this->diagnostics[] = array( 'code' => 'sector_image_map_conflict', 'type' => 'sector', 'source_key' => 'sector:' . $sectorSlug );
			return null;
		}
		return null === $merged['id'] ? null : array( 'id' => $merged['id'], 'type_verified' => true );
	}
}

class MB_Fake_Writer implements MaviBelge_Core_Import_Target_Writer {
	private $w;
	private $journal = null;

	public function begin_side_effect_scope() {
		if ( null !== $this->journal ) {
			return false;
		}
		$this->journal = array();
		return true;
	}

	public function commit_side_effect_scope() {
		$this->journal = null;
		return true;
	}

	public function compensate_side_effect_scope( $dbRolledBack ) {
		$journal       = $this->journal;
		$this->journal = null;
		if ( ! is_array( $journal ) || array() === $journal ) {
			return array( 'ok' => true, 'removed' => 0, 'error' => null );
		}
		if ( true !== $dbRolledBack ) {
			return array( 'ok' => false, 'removed' => 0, 'error' => 'compensation_skipped_db_rollback_failed' );
		}
		$removed = 0;
		$error   = null;
		foreach ( $journal as $e ) {
			if ( isset( $this->w->logoAttachments[ $e['attachment_id'] ] ) ) {
				$error = null === $error ? 'side_effect_attachment_still_present' : $error;
			} elseif ( $this->w->unlinkFails ) {
				$error = null === $error ? 'side_effect_cleanup_failed' : $error;
			} else {
				unset( $this->w->files[ $e['file'] ] );
				$removed++;
			}
		}
		return array( 'ok' => null === $error, 'removed' => $removed, 'error' => $error );
	}

	public function __construct( MB_Fake_World $world ) {
		$this->w = $world;
	}

	private static function res( $ok, $id = null, $error = null ) {
		return array( 'ok' => $ok, 'id' => $id, 'error' => $error );
	}

	private function key( array $payload ) {
		return isset( $payload['source_key'] ) ? $payload['source_key'] : '';
	}

	public function create_sector( array $payload ) {
		foreach ( $this->w->terms as $t ) {
			if ( $t['slug'] === $payload['term']['slug'] ) {
				return self::res( false, null, 'term_exists' );
			}
		}
		$id                   = $this->w->nextId++;
		$this->w->terms[ $id ] = array( 'taxonomy' => 'mb_sektor', 'slug' => '', 'name' => '', 'description' => '', 'parent' => 0, 'meta' => array() );
		return $this->write_sector( $id, $payload, 'create_sector' );
	}

	public function update_sector( $termId, array $payload ) {
		if ( ! isset( $this->w->terms[ $termId ] ) ) {
			return self::res( false, null, 'term_missing' );
		}
		return $this->write_sector( $termId, $payload, 'update_sector' );
	}

	private function write_sector( $id, array $payload, $op ) {
		$this->w->writeLog[] = $op . ':' . $this->key( $payload );
		$t                   = &$this->w->terms[ $id ];
		$t['slug']           = $payload['term']['slug'];
		$t['name']           = $payload['term']['name'];
		$t['description']    = $payload['term']['description'];
		foreach ( $payload['term_meta'] as $k => $v ) {
			if ( $this->w->fault( 'corrupt_meta', $this->key( $payload ) ) && '_mb_icon_key' === $k ) {
				$v = 'bozulmus';
			}
			$t['meta'][ $k ] = MB_Fake_World::stored( $v );
		}
		if ( $this->w->fault( $op, $this->key( $payload ) ) ) {
			return self::res( false, null, 'injected_failure' );
		}
		return self::res( true, $id );
	}

	public function create_post( array $payload ) {
		$id                   = $this->w->nextId++;
		$this->w->posts[ $id ] = array( 'post_type' => $payload['post']['post_type'], 'title' => '', 'status' => 'draft', 'content' => '', 'name' => '', 'excerpt' => '', 'date' => '', 'parent' => 0, 'menu_order' => 0, 'extra' => array(), 'meta' => array(), 'terms' => array() );
		return $this->write_post( $id, $payload, 'create_post' );
	}

	public function update_post( $postId, array $payload ) {
		if ( ! isset( $this->w->posts[ $postId ] ) || $this->w->posts[ $postId ]['post_type'] !== $payload['post']['post_type'] ) {
			return self::res( false, null, 'post_missing' );
		}
		return $this->write_post( $postId, $payload, 'update_post' );
	}

	private function write_post( $id, array $payload, $op ) {
		$this->w->writeLog[] = $op . ':' . $this->key( $payload );
		$p                   = &$this->w->posts[ $id ];
		$p['title']          = $payload['post']['post_title'];
		// Yönetilen çekirdek alanlar YALNIZ doğrulanmış yükten gelir (haber: ad/içerik/özet/tarih; referans: ad).
		foreach ( array( 'post_name' => 'name', 'post_content' => 'content', 'post_excerpt' => 'excerpt', 'post_date' => 'date', 'post_parent' => 'parent', 'menu_order' => 'menu_order' ) as $core => $field ) {
			if ( array_key_exists( $core, $payload['post'] ) ) {
				$p[ $field ] = $payload['post'][ $core ];
			}
		}
		foreach ( $payload['post_meta'] as $k => $v ) {
			$p['meta'][ $k ] = MB_Fake_World::stored( $v );
		}
		foreach ( isset( $payload['terms'] ) ? $payload['terms'] : array() as $taxonomy => $termIds ) {
			$p['terms'][ $taxonomy ] = $termIds;
		}
		if ( isset( $payload['logo'] ) ) {
			// Faz 12b: içerik özetiyle mevcut logo attachment'ı yeniden kullanılır (aynı logo tekrar eklenmez); yoksa yenisi oluşturulur.
			if ( $this->w->fault( 'logo_fail', $this->key( $payload ) ) ) {
				return self::res( false, null, 'logo_source_missing' );
			}
			$attId = array_search( $payload['logo']['sha256'], $this->w->logoAttachments, true );
			if ( false === $attId ) {
				$attId                             = $this->w->nextId++;
				$this->w->logoAttachments[ $attId ] = $payload['logo']['sha256'];
				$file                               = 'logo-' . substr( $payload['logo']['sha256'], 0, 12 ) . '-' . $attId . '.png';
				$this->w->files[ $file ]            = true;
				if ( null !== $this->journal ) {
					$this->journal[] = array( 'file' => $file, 'attachment_id' => $attId );
				}
				$this->w->writeLog[]               = 'create_logo_attachment:' . $attId;
			}
			$p['meta']['_mb_logo_attachment_id'] = MB_Fake_World::stored( $attId );
		}
		if ( $this->w->fault( 'corrupt_meta', $this->key( $payload ) ) ) {
			$p['meta']['_mb_record_status'] = 'bozulmus';
		}
		if ( $this->w->fault( 'touch_unmanaged', $this->key( $payload ) ) ) {
			$p['content'] = 'yönetilmeyen alana dokunuldu';
		}
		if ( $this->w->fault( $op, $this->key( $payload ) ) ) {
			return self::res( false, null, 'injected_failure' );
		}
		return self::res( true, $id );
	}

	public function rollback_created_post( $postId, $postType, $expectedFingerprint ) {
		if ( ! isset( $this->w->posts[ $postId ] ) || $this->w->posts[ $postId ]['post_type'] !== $postType || 'trash' === $this->w->posts[ $postId ]['status'] ) {
			return self::res( false, null, 'post_missing' );
		}
		$type = array_search( $postType, array( 'qualification' => 'mb_yeterlilik', 'fee' => 'mb_ucret', 'news' => 'mb_haber', 'reference' => 'mb_referans', 'faq' => 'mb_sss', 'page' => 'page' ), true );
		if ( false === $type ) {
			return self::res( false, null, 'post_missing' );
		}
		if ( $this->unmanaged_fingerprint( $type, $postId ) !== $expectedFingerprint ) {
			return self::res( false, null, 'drift_detected' );
		}
		$this->w->writeLog[] = 'trash_post:' . $postId;
		foreach ( MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META[ $type ] as $key ) {
			unset( $this->w->posts[ $postId ]['meta'][ $key ] );
		}
		if ( 'qualification' === $type ) {
			unset( $this->w->posts[ $postId ]['terms']['mb_sektor'] );
		}
		if ( 'news' === $type ) {
			unset( $this->w->posts[ $postId ]['terms']['mb_haber_turu'] );
		}
		if ( 'news' === $type || 'reference' === $type || 'faq' === $type || 'page' === $type ) {
			$this->w->posts[ $postId ]['name'] = ''; // post_name doğal anahtardır: çöpteki kabuk slug'ı tutmaz.
		}
		$this->w->posts[ $postId ]['status'] = 'trash';
		if ( $this->w->fault( 'trash_post', (string) $postId ) ) {
			return self::res( false, null, 'injected_failure' );
		}
		return self::res( true, $postId );
	}

	public function page_status( $postId ) {
		return ( isset( $this->w->posts[ $postId ] ) && 'page' === $this->w->posts[ $postId ]['post_type'] ) ? $this->w->posts[ $postId ]['status'] : null;
	}

	public function content_post_status( $type, $postId ) {
		$map = array( 'faq' => 'mb_sss', 'reference' => 'mb_referans' );
		return ( isset( $map[ $type ], $this->w->posts[ $postId ] ) && $map[ $type ] === $this->w->posts[ $postId ]['post_type'] ) ? $this->w->posts[ $postId ]['status'] : null;
	}

	public function publish_page( $postId ) {
		if ( ! isset( $this->w->posts[ $postId ] ) || 'page' !== $this->w->posts[ $postId ]['post_type'] || 'draft' !== $this->w->posts[ $postId ]['status'] ) {
			return self::res( false, null, 'page_not_draft' );
		}
		$this->w->writeLog[] = 'publish_page:' . $postId;
		if ( $this->w->fault( 'publish_page', (string) $postId ) ) {
			return self::res( false, null, 'injected_failure' );
		}
		$this->w->posts[ $postId ]['status'] = 'publish';
		if ( $this->w->fault( 'publish_corrupt', (string) $postId ) ) {
			$this->w->posts[ $postId ]['content'] = 'yayın sırasında bozuldu';
		}
		return self::res( true, $postId );
	}

	public function delete_sector_term( $termId, $expectedFingerprint ) {
		if ( ! isset( $this->w->terms[ $termId ] ) ) {
			return self::res( false, null, 'term_missing' );
		}
		if ( $this->unmanaged_fingerprint( 'sector', $termId ) !== $expectedFingerprint ) {
			return self::res( false, null, 'drift_detected' );
		}
		$this->w->writeLog[] = 'delete_sector_term:' . $termId;
		unset( $this->w->terms[ $termId ] );
		foreach ( $this->w->posts as $id => $p ) {
			if ( isset( $p['terms']['mb_sektor'] ) ) {
				$this->w->posts[ $id ]['terms']['mb_sektor'] = array_values( array_diff( $p['terms']['mb_sektor'], array( $termId ) ) );
			}
		}
		return self::res( true, $termId );
	}

	public function unmanaged_fingerprint( $type, $id ) {
		if ( 'sector' === $type ) {
			if ( ! isset( $this->w->terms[ $id ] ) ) {
				return null;
			}
			$t = $this->w->terms[ $id ];
			$m = $t['meta'];
			foreach ( array( '_mb_icon_key', '_mb_image_attachment_id', '_mb_import_source_key', '_mb_last_applied_hash' ) as $k ) {
				unset( $m[ $k ] );
			}
			ksort( $m );
			return hash( 'sha256', serialize( array( $t['parent'], isset( $t['term_group'] ) ? $t['term_group'] : 0, $m ) ) );
		}
		if ( ! isset( $this->w->posts[ $id ] ) ) {
			return null;
		}
		$p = $this->w->posts[ $id ];
		$m = $p['meta'];
		foreach ( MaviBelge_Core_Import_Write_Payload::MANAGED_POST_META[ $type ] as $k ) {
			unset( $m[ $k ] );
		}
		ksort( $m );
		$terms = isset( $p['terms'] ) ? $p['terms'] : array();
		if ( 'qualification' === $type ) {
			unset( $terms['mb_sektor'] ); // Yönetilen alan (sector_term_id).
		}
		if ( 'news' === $type ) {
			unset( $terms['mb_haber_turu'] ); // Yönetilen alan (news_type_term_id).
		}
		ksort( $terms );
		// Yönetilmeyen çekirdek alanlar; haberde ad/içerik/özet/tarih, referansta ad YÖNETİLİR (parmak izine girmez).
		$core = array(
			'status'  => $p['status'],
			'content' => $p['content'],
			'name'    => isset( $p['name'] ) ? $p['name'] : '',
			'excerpt' => isset( $p['excerpt'] ) ? $p['excerpt'] : '',
			'date'    => isset( $p['date'] ) ? $p['date'] : '',
			'parent'  => isset( $p['parent'] ) ? $p['parent'] : 0,
			'menu_order' => isset( $p['menu_order'] ) ? $p['menu_order'] : 0,
			'extra'   => isset( $p['extra'] ) ? $p['extra'] : array(),
		);
		$managedCore = array( 'news' => array( 'content', 'name', 'excerpt', 'date' ), 'reference' => array( 'name' ), 'faq' => array( 'content', 'name' ), 'page' => array( 'content', 'name', 'excerpt', 'parent', 'menu_order' ) );
		foreach ( isset( $managedCore[ $type ] ) ? $managedCore[ $type ] : array() as $field ) {
			unset( $core[ $field ] );
		}
		return hash( 'sha256', serialize( array( $core, $m, $terms ) ) );
	}

	public function sector_term_references( $termId ) {
		if ( ! isset( $this->w->terms[ $termId ] ) ) {
			return array( 'ok' => false, 'slug' => null, 'object_ids' => array(), 'fee_ids' => array(), 'trashed_ids' => array(), 'child_count' => 0 );
		}
		$slug    = $this->w->terms[ $termId ]['slug'];
		$objects = array();
		$fees    = array();
		foreach ( $this->w->posts as $id => $p ) {
			if ( isset( $p['terms']['mb_sektor'] ) && in_array( $termId, $p['terms']['mb_sektor'], true ) ) {
				$objects[] = $id;
			}
			if ( 'mb_ucret' === $p['post_type'] && isset( $p['meta']['_mb_sector_slug'] ) && $p['meta']['_mb_sector_slug'] === $slug ) {
				$fees[] = $id;
			}
		}
		$children = 0;
		foreach ( $this->w->terms as $t ) {
			if ( $t['parent'] === $termId ) {
				$children++;
			}
		}
		$trashed = array();
		foreach ( array_unique( array_merge( $objects, $fees ) ) as $id ) {
			if ( 'trash' === $this->w->posts[ $id ]['status'] ) {
				$trashed[] = $id;
			}
		}
		return array( 'ok' => true, 'slug' => $slug, 'object_ids' => $objects, 'fee_ids' => $fees, 'trashed_ids' => $trashed, 'child_count' => $children );
	}
}

class MB_Fake_Transaction implements MaviBelge_Core_Import_Transaction {
	private $w;
	private $snapshot = null;
	public $preflightOk = true;
	public $failBegin   = false;
	public $failCommit  = false;
	public $failRollback = false;
	/** Hata enjeksiyonu: N başarılı begin/commit'ten SONRA başarısız ol (null = kapalı). */
	public $failBeginAfter  = null;
	public $failCommitAfter = null;
	private $begins  = 0;
	private $commits = 0;
	public $log         = array();

	public function __construct( MB_Fake_World $world ) {
		$this->w = $world;
	}

	/** Şu andan itibaren N başarılı begin'den sonra begin başarısız olsun. */
	public function fail_begin_in( $n ) {
		$this->failBeginAfter = $this->begins + $n;
	}

	/** Şu andan itibaren N başarılı commit'ten sonra commit başarısız olsun. */
	public function fail_commit_in( $n ) {
		$this->failCommitAfter = $this->commits + $n;
	}

	public function preflight() {
		return $this->preflightOk ? array( 'ok' => true, 'error' => null ) : array( 'ok' => false, 'error' => 'non_transactional_table' );
	}

	public function begin() {
		if ( $this->failBegin || null !== $this->snapshot || ( null !== $this->failBeginAfter && $this->begins >= $this->failBeginAfter ) ) {
			return false;
		}
		$this->begins++;
		$this->snapshot = $this->w->state();
		$this->log[]    = 'begin';
		return true;
	}

	public function commit() {
		if ( null === $this->snapshot || $this->failCommit || ( null !== $this->failCommitAfter && $this->commits >= $this->failCommitAfter ) ) {
			return false;
		}
		$this->commits++;
		$this->snapshot = null;
		$this->log[]    = 'commit';
		return true;
	}

	public function rollback() {
		if ( null === $this->snapshot ) {
			return false;
		}
		$this->w->restore( $this->snapshot );
		$this->snapshot = null;
		$this->log[]    = 'rollback';
		return ! $this->failRollback;
	}
}

class MB_Fake_Run_Store implements MaviBelge_Core_Import_Run_Store {
	private $w;
	public $installed = false;
	public $installOk = true;
	public $locked    = false;
	/** Hata enjeksiyonu: bu HEDEF durumlara geçiş başarısız olur (durum geçişi hatası simülasyonu). */
	public $failTransitionTo = array();
	/** Hata enjeksiyonu (Faz 6B4): 'checkpoint' | 'rollback_checkpoint' | 'create_run_with_plan' yazımları başarısız olur. */
	public $failWrites = array();

	public function __construct( MB_Fake_World $world ) {
		$this->w = $world;
	}

	public function is_installed() {
		return $this->installed;
	}

	public function ensure_installed() {
		if ( $this->installOk ) {
			$this->installed = true;
		}
		return $this->installed;
	}

	public function acquire_lock() {
		if ( $this->locked ) {
			return false;
		}
		$this->locked = true;
		return true;
	}

	public function release_lock() {
		$this->locked = false;
		return true;
	}

	public function create_run( array $data ) {
		$id                   = $this->w->nextRunId++;
		$this->w->runs[ $id ] = array(
			'id' => $id, 'uid' => sprintf( '%032x', $id ), 'stage' => $data['stage'], 'status' => MaviBelge_Core_Import_Run_State::PLANNED,
			'plan_digest' => $data['plan_digest'], 'manifest_digest' => $data['manifest_digest'], 'batch_size' => $data['batch_size'],
			'total_writes' => $data['total_writes'], 'committed_batches' => 0, 'committed_items' => 0, 'error_code' => null,
			'created_by' => $data['created_by'], 'created_at' => '2026-09-24 00:00:00', 'updated_at' => '2026-09-24 00:00:00',
			'map_digest' => null, 'rollback_batches' => 0, 'rollback_items' => 0,
		);
		return $this->w->runs[ $id ];
	}

	public function transition( $runId, $from, $to, array $fields = array() ) {
		if ( in_array( $to, $this->failTransitionTo, true ) ) {
			return false;
		}
		if ( ! isset( $this->w->runs[ $runId ] ) || $this->w->runs[ $runId ]['status'] !== $from || ! MaviBelge_Core_Import_Run_State::can_transition( $from, $to ) ) {
			return false;
		}
		$this->w->runs[ $runId ]['status'] = $to;
		if ( array_key_exists( 'error_code', $fields ) ) {
			$this->w->runs[ $runId ]['error_code'] = $fields['error_code'];
		}
		return true;
	}

	public function get_run( $uid ) {
		foreach ( $this->w->runs as $run ) {
			if ( $run['uid'] === $uid ) {
				return $run;
			}
		}
		return null;
	}

	public function find_runs_in_status( array $statuses ) {
		$out = array();
		foreach ( $this->w->runs as $run ) {
			if ( in_array( $run['status'], $statuses, true ) ) {
				$out[] = $run;
			}
		}
		return $out;
	}

	public function list_runs( $limit ) {
		return array_slice( array_reverse( array_values( $this->w->runs ) ), 0, $limit );
	}

	public function add_item( $runId, array $item ) {
		$id                    = $this->w->nextItemId++;
		$this->w->items[ $id ] = array_merge( $item, array( 'id' => $id, 'run_id' => $runId, 'rollback_status' => 'pending' ) );
		return true;
	}

	public function record_checkpoint( $runId, $committedBatches, $committedItems, $expectedBatches = null ) {
		if ( ! isset( $this->w->runs[ $runId ] ) || MaviBelge_Core_Import_Run_State::RUNNING !== $this->w->runs[ $runId ]['status'] ) {
			return false;
		}
		if ( null !== $expectedBatches && $this->w->runs[ $runId ]['committed_batches'] !== $expectedBatches ) {
			return false;
		}
		if ( in_array( 'checkpoint', $this->failWrites, true ) ) {
			return false;
		}
		$this->w->runs[ $runId ]['committed_batches'] = $committedBatches;
		$this->w->runs[ $runId ]['committed_items']   = $committedItems;
		return true;
	}

	public function record_rollback_checkpoint( $runId, $expectedBatches, $newBatches, $newItems ) {
		if ( ! isset( $this->w->runs[ $runId ] ) || MaviBelge_Core_Import_Run_State::ROLLING_BACK !== $this->w->runs[ $runId ]['status'] || in_array( 'rollback_checkpoint', $this->failWrites, true ) ) {
			return false;
		}
		$run = $this->w->runs[ $runId ];
		if ( $run['rollback_batches'] !== $expectedBatches || $newBatches < $run['rollback_batches'] || $newItems < $run['rollback_items'] ) {
			return false;
		}
		$this->w->runs[ $runId ]['rollback_batches'] = $newBatches;
		$this->w->runs[ $runId ]['rollback_items']   = $newItems;
		return true;
	}

	public function create_run_with_plan( array $data, array $planItems ) {
		if ( array() !== MaviBelge_Core_Import_Plan_Snapshot::validate_items( $planItems ) || (int) $data['total_writes'] !== count( $planItems ) || in_array( 'create_run_with_plan', $this->failWrites, true ) ) {
			return null;
		}
		$run = $this->create_run( $data );
		$id  = $run['id'];
		$this->w->runs[ $id ]['status']    = MaviBelge_Core_Import_Run_State::READY;
		$this->w->runs[ $id ]['map_digest'] = isset( $data['map_digest'] ) ? $data['map_digest'] : null;
		foreach ( $planItems as $item ) {
			$this->w->planItems[ $id ][ $item['seq'] ] = $item;
		}
		return $this->w->runs[ $id ];
	}

	public function get_plan_items( $runId, $afterSeq, $limit ) {
		$rows = isset( $this->w->planItems[ $runId ] ) ? $this->w->planItems[ $runId ] : array();
		ksort( $rows );
		$out = array();
		foreach ( $rows as $seq => $row ) {
			if ( $seq > $afterSeq ) {
				$out[] = $row;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	public function get_items( $runId ) {
		$out = array();
		foreach ( $this->w->items as $item ) {
			if ( $item['run_id'] === $runId ) {
				$out[] = $item;
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['seq'] <=> $b['seq'];
			}
		);
		return $out;
	}

	public function rolled_back_create_target_ids( $type ) {
		$ids = array();
		foreach ( $this->w->items as $item ) {
			if ( $item['type'] === $type && 'create' === $item['decision'] && 'rolled_back' === $item['rollback_status'] ) {
				$ids[] = $item['target_id'];
			}
		}
		return $ids;
	}

	public function mark_item_rolled_back( $itemId ) {
		if ( ! isset( $this->w->items[ $itemId ] ) || 'pending' !== $this->w->items[ $itemId ]['rollback_status'] ) {
			return false;
		}
		$this->w->items[ $itemId ]['rollback_status'] = 'rolled_back';
		return true;
	}
}

class MB_Fake_Audit_Sink implements MaviBelge_Core_Import_Audit_Sink {
	private $w;
	public $readyFlag  = true;
	public $failEvents = array();

	public function __construct( MB_Fake_World $world ) {
		$this->w = $world;
	}

	public function ready() {
		return $this->readyFlag;
	}

	public function record( $event, $runId, array $context ) {
		if ( in_array( $event, $this->failEvents, true ) ) {
			return false;
		}
		$ctx = MaviBelge_Core_Import_Audit_Context::build( $context );
		if ( null === $ctx ) {
			return false;
		}
		$this->w->audit[] = array( 'event' => $event, 'run_id' => $runId, 'context' => $ctx );
		return true;
	}
}

/** Sahte dünya + servisler; `$dir` fixture manifest dizini. */
function mb_fake_apply_env( $dir ) {
	$w    = new MB_Fake_World();
	$repo = new MB_Fake_World_Repository( $w );
	$env  = new stdClass();
	$env->world  = $w;
	$env->repo   = $repo;
	$env->writer = new MB_Fake_Writer( $w );
	$env->tx     = new MB_Fake_Transaction( $w );
	$env->store  = new MB_Fake_Run_Store( $w );
	$env->audit  = new MB_Fake_Audit_Sink( $w );
	$env->dryRun = new MaviBelge_Core_Import_Dry_Run_Service( $repo, $dir );
	$env->apply  = new MaviBelge_Core_Import_Apply_Service( $env->dryRun, $env->writer, $env->tx, $env->store, $env->audit );
	$env->rollback = new MaviBelge_Core_Import_Rollback_Service( $repo, $env->writer, $env->tx, $env->store, $env->audit );
	return $env;
}

/**
 * Faz 6B4 son kabul düzeltmesi — sektör görsel eşlemesi deposu için bellek içi sahte WordPress: option (db) + nesne önbelleği
 * (cache) + audit satırları + transaction. Gerçek davranışa sadıktır: rollback db ve audit'i geri yükler, ÖNBELLEĞİ geri
 * YÜKLEMEZ (yeni değer flush() çağrılmazsa okumada görünmeye devam eder).
 */
class MB_Fake_Image_Map_Store implements MaviBelge_Core_Import_Image_Map_Store {
	public $db           = null;
	public $cache        = null;
	public $audit        = array();
	public $log          = array();
	public $attachments  = array();
	public $readyFlag    = true;
	public $failBegin    = false;
	public $failWrite    = false;
	public $failAudit    = false;
	public $failCommit   = false;
	public $failRollback = false;
	public $writeThrows  = false;
	public $lockFail     = false;
	public $lockHeld     = false;
	/** @var callable|null Eşzamanlı başka yöneticinin yazımı: yalnız kilit BOŞKEN bir zamanlama noktasında (ready/begin) bir kez çalışır. */
	public $rival        = null;
	/** @var array<int,mixed> write() anında gerçekten değiştirilen (üzerine yazılan) option durumları. */
	public $replaced     = array();
	private $snap        = null;

	private function schedule_point() {
		if ( null !== $this->rival && ! $this->lockHeld ) {
			$rival       = $this->rival;
			$this->rival = null;
			$rival( $this );
		}
	}

	public function lock() {
		$this->log[] = 'lock';
		if ( $this->lockFail ) {
			return false;
		}
		$this->lockHeld = true;
		return true;
	}

	public function unlock() {
		$this->log[] = 'unlock';
		$this->lockHeld = false;
		return true;
	}

	public function ready() {
		$this->log[] = 'ready';
		$this->schedule_point();
		return $this->readyFlag;
	}

	public function read() {
		$this->log[] = 'read';
		return null !== $this->cache ? $this->cache : $this->db;
	}

	public function inspect( $id ) {
		return in_array( $id, $this->attachments, true ) ? array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'mime' => 'image/png', 'readable' => true ) : null;
	}

	public function begin() {
		$this->log[] = 'begin';
		$this->schedule_point();
		if ( $this->failBegin || null !== $this->snap ) {
			return false;
		}
		$this->snap = array( $this->db, $this->audit );
		return true;
	}

	public function write( array $stored, $exists ) {
		$this->log[] = 'write:' . ( $exists ? 'update' : 'add' );
		if ( $this->writeThrows ) {
			throw new RuntimeException( 'sızıntı /mutlak/yol SELECT * FROM' );
		}
		if ( $this->failWrite ) {
			return false;
		}
		$this->replaced[] = $this->db;
		$this->db    = $stored;
		$this->cache = $stored;
		return true;
	}

	public function audit( array $context ) {
		$this->log[] = 'audit';
		if ( $this->failAudit ) {
			return false;
		}
		$this->audit[] = $context;
		return true;
	}

	public function commit() {
		$this->log[] = 'commit';
		if ( $this->failCommit || null === $this->snap ) {
			return false;
		}
		$this->snap = null;
		return true;
	}

	public function rollback() {
		$this->log[] = 'rollback';
		if ( null !== $this->snap ) {
			list( $this->db, $this->audit ) = $this->snap;
			$this->snap                      = null;
		}
		return ! $this->failRollback;
	}

	public function flush() {
		$this->log[] = 'flush';
		$this->cache = null;
	}

	public function open() {
		return null !== $this->snap;
	}
}
