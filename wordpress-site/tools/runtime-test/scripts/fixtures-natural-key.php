<?php
/**
 * TEST FIXTURE (Faz 6B3 Önkoşul) — YALNIZ izole runtime test veritabanında
 * `wp eval-file`, fixtures.php'DEN SONRA bir kez çalıştırılır. Üretim kodu
 * DEĞİLDİR. Doğal anahtar preflight senaryolarını kurar ve beklentileri
 * /tmp/mb-fixture-expect-6b3.json'a yazar:
 *   - "overrides": fixtures.php'nin önceki beklentilerinden, doğal anahtar
 *     kuralı nedeniyle BİLİNÇLİ olarak değişenler (gerekçeleriyle);
 *   - "added": yeni senaryolar.
 * Bozuk/yanlış önekli marker'lar kayıtlı sanitize artık reddettiği için
 * DB bozulmasını modellemek üzere $wpdb->insert ile ham yazılır.
 */
$overrides = array();
$added     = array();
$note      = array();

$raw_meta = function ( $kind, $objectId, $metaKey, $value ) {
	global $wpdb;
	$table  = 'term' === $kind ? $wpdb->termmeta : $wpdb->postmeta;
	$column = 'term' === $kind ? 'term_id' : 'post_id';
	$wpdb->insert( $table, array( $column => $objectId, 'meta_key' => $metaKey, 'meta_value' => maybe_serialize( $value ) ) );
	wp_cache_delete( $objectId, 'term' === $kind ? 'term_meta' : 'post_meta' );
};
$make_post = function ( $postType, $title, array $meta, $status = 'draft' ) {
	$pid = wp_insert_post( array( 'post_type' => $postType, 'post_status' => $status, 'post_title' => $title ), true );
	if ( is_wp_error( $pid ) ) {
		throw new RuntimeException( 'post: ' . $pid->get_error_message() );
	}
	foreach ( $meta as $k => $v ) {
		update_post_meta( $pid, $k, $v );
	}
	return (int) $pid;
};
$term_id = function ( $slug ) {
	$t = get_term_by( 'slug', $slug, 'mb_sektor' );
	return $t ? (int) $t->term_id : 0;
};

$M = MaviBelge_Core_Import_Manifest_Loader::load_all()['manifest'];
$sector = function ( $slug ) use ( $M ) {
	foreach ( $M['sectors'] as $r ) {
		if ( $slug === $r['slug'] ) {
			return $r;
		}
	}
	throw new RuntimeException( "sektör yok: {$slug}" );
};

// --- fixtures.php beklentilerinden bilinçli olarak DEĞİŞENLER ---
$overrides['sector:guzellik-sac-bakim'] = array( 'conflict', 'corrupt_marker', '7a (değişti): marker yalnız serileştirilmiş dizi — artık keşfedilemese de doğal anahtar (slug) bozuk marker\'ı yakalar; create DEĞİL' );
$overrides['qualification:18UY0344-4/00'] = array( 'conflict_duplicate_target', 'duplicate_natural_key', '13 (değişti): aynı MYK koduyla iki marker\'sız yeterlilik — create DEĞİL' );
$overrides['qualification:17UY0280-3/01'] = array( 'conflict', 'unmanaged_natural_key', '1 (değişti): aynı MYK kodlu marker\'sız yazı var — create DEĞİL' );

// metalurji: senaryo 14 (görsel attachment değil -> blocked) korunur; terim artık
// kendi geçerli marker'ını taşır (hedef marker ile bulunur, sonra bağımlılık bloklanır).
update_term_meta( $term_id( 'metalurji' ), '_mb_import_source_key', 'sector:metalurji' );
$overrides['sector:metalurji'] = array( 'blocked_dependency', 'dependency_unresolved', '14 (korundu): marker\'lı terim, görsel ID attachment değil' );

// metal: senaryo 15 (gerçek attachment) — marker + projeksiyon hash'i ile
// görsel gerçekten {id,type_verified} çözülmedikçe unchanged olamaz.
$metalTerm = $term_id( 'metal' );
$attId     = (int) get_term_meta( $metalTerm, '_mb_image_attachment_id', true );
$proj      = MaviBelge_Core_Import_Managed_Fields::project_sector( $sector( 'metal' ), array( 'image_attachment_id' => $attId ) )['fields'];
update_term_meta( $metalTerm, '_mb_import_source_key', 'sector:metal' );
update_term_meta( $metalTerm, '_mb_last_applied_hash', MaviBelge_Core_Import_Hash::hash( $proj ) );
$overrides['sector:metal'] = array( 'unchanged', 'hash_match', '15 (değişti): gerçek attachment {id,type_verified:true} çözüldü ve hash eşleşti (görselli sektör create ile görsel çözemez — bkz. rapor)' );
$note['metal_attachment_id'] = $attId;

// --- YENİ doğal anahtar senaryoları ---
// Yanlış önekli marker taşıyan, doğal anahtarı eşleşen terim (maden).
$madenTerm = wp_insert_term( 'Maden', 'mb_sektor', array( 'slug' => 'maden' ) );
$raw_meta( 'term', (int) $madenTerm['term_id'], '_mb_import_source_key', 'qualification:10UY0002-3/03' );
$overrides['sector:maden'] = array( 'conflict', 'wrong_marker_prefix', 'NK: doğal anahtar terimi yanlış önekli marker taşıyor (önceki beklenti blocked idi — doğal anahtar bağımlılıktan önce gelir)' );

// Aynı ailede geçerli ama FARKLI source_key (foreign marker).
$make_post( 'mb_yeterlilik', 'İplik Eğirme Operatörü', array( '_mb_myk_code' => '11UY0037-2/01', '_mb_level' => '2', '_mb_revision' => '01', '_mb_record_status' => 'active', '_mb_import_source_key' => 'qualification:11UY0039-3/02' ) );
$added['qualification:11UY0037-2/01'] = array( 'conflict', 'foreign_marker', 'NK: aynı MYK kodlu yazı başka bir source_key\'e bağlı' );
$added['qualification:11UY0039-3/02'] = array( 'conflict', 'invalid_target_state', 'NK yan etkisi: bu source_key\'in marker\'ı yabancı yazıda bulunur; o yazının sektör terimi yok -> mevcut alanlar okunamaz (invalid_target_state, legacy kontrolünden önce gelir)' );

// Çöp kutusundaki, tam bu source_key\'i taşıyan yazı (undiscovered marker).
$make_post( 'mb_yeterlilik', 'Bitim İşlemleri Operatörü', array( '_mb_myk_code' => '13UY0137-3/01', '_mb_level' => '3', '_mb_revision' => '01', '_mb_record_status' => 'active', '_mb_import_source_key' => 'qualification:13UY0137-3/01' ), 'trash' );
$added['qualification:13UY0137-3/01'] = array( 'conflict', 'undiscovered_marker', 'NK: marker doğru ama kayıt çöp kutusunda (keşif görmez, doğal anahtar görür)' );

// Ücret doğal anahtarı: tam eşleşen marker'sız ücret -> unmanaged.
$make_post( 'mb_ucret', 'Sıcak Su Kazanı Operatörü', array( '_mb_sector_slug' => 'enerji', '_mb_level' => '3', '_mb_profession_name' => 'Sıcak Su Kazanı Operatörü' ) );
$overrides['fee:enerji:3:sicak-su-kazani-operatoru'] = array( 'conflict', 'unmanaged_natural_key', 'NK: sektör+seviye+meslek slug\'ı tam eşleşen marker\'sız ücret' );

// Fuzzy benzerlik eşleşme SAYILMAZ -> create kalır.
$make_post( 'mb_ucret', 'Kızgın Yağ Operatörü (eski)', array( '_mb_sector_slug' => 'enerji', '_mb_level' => '4', '_mb_profession_name' => 'Kızgın Yağ Operatörü (eski)' ) );
$overrides['fee:enerji:4:kizgin-yag-operatoru'] = array( 'create', 'no_target', 'NK: yalnız benzer adlı ücret (fuzzy) eşleşme sayılmaz' );

// Aynı doğal anahtarla iki marker'sız ücret -> duplicate.
foreach ( array( 1, 2 ) as $i ) {
	$make_post( 'mb_ucret', 'Buhar Kazanı Operatörü', array( '_mb_sector_slug' => 'enerji', '_mb_level' => '4', '_mb_profession_name' => 'Buhar Kazanı Operatörü' ) );
}
$overrides['fee:enerji:4:buhar-kazani-operatoru'] = array( 'conflict_duplicate_target', 'duplicate_natural_key', 'NK: aynı doğal anahtarla iki marker\'sız ücret' );

file_put_contents( '/tmp/mb-fixture-expect-6b3.json', wp_json_encode( array( 'overrides' => $overrides, 'added' => $added, 'note' => $note ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
echo wp_json_encode( array( 'overrides' => count( $overrides ), 'added' => count( $added ) ) ), "\n";
