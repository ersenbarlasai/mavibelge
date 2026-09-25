<?php
/**
 * Faz 7 — yönetim listesi kuralları (MaviBelge_Core_Content_Admin_Rules), SAF testler.
 */

$AR = 'MaviBelge_Core_Content_Admin_Rules';

mb_test( 'Faz 7 yönetim: beş içerik türü için filtre tanımı var; bilinmeyen tür/yeterlilik/ücret için boş',
	array( 'mb_haber', 'mb_dokuman', 'mb_referans', 'mb_lokasyon', 'mb_sss' ) === $AR::POST_TYPES && array() !== $AR::filters_for( 'mb_haber' ) && array() === $AR::filters_for( 'mb_ucret' ) && array() === $AR::filters_for( 'bogus' ) && array() === $AR::filters_for( null ) );
mb_test( 'Faz 7 yönetim: filtre değeri KAPALI allowlist\'ten geçer; bilinmeyen değer, dizi, başka türün filtre anahtarı, SQL benzeri değer -> boş',
	'approved' === $AR::sanitize_filter( 'mb_haber', 'mb_f_approval', 'approved' ) && '' === $AR::sanitize_filter( 'mb_haber', 'mb_f_approval', 'APPROVED' ) && '' === $AR::sanitize_filter( 'mb_haber', 'mb_f_approval', "approved' OR 1=1" )
	&& '' === $AR::sanitize_filter( 'mb_haber', 'mb_f_approval', array( 'approved' ) ) && '' === $AR::sanitize_filter( 'mb_haber', 'mb_f_record', 'active' ) && 'real' === $AR::sanitize_filter( 'mb_referans', 'mb_f_reference', 'real' )
	&& '' === $AR::sanitize_filter( 'mb_haber', array( 'x' ), 'approved' ) );
mb_test( 'Faz 7 yönetim: meta sorgusu yalnız geçerli filtrelerden üretilir; hiç filtre yoksa boş; geçersiz değerler sessizce düşer',
	array() === $AR::build_meta_query( 'mb_haber', array(), '2026-09-25' ) && array() === $AR::build_meta_query( 'mb_haber', array( 'mb_f_approval' => 'bogus' ), '2026-09-25' )
	&& array( 'relation' => 'AND', array( 'key' => '_mb_approval_status', 'value' => 'draft' ) ) === $AR::build_meta_query( 'mb_haber', array( 'mb_f_approval' => 'draft', 'mb_f_record' => 'passive' ), '2026-09-25' ) );
$ar_active = $AR::build_meta_query( 'mb_referans', array( 'mb_f_record' => 'active' ), '2026-09-25' );
$ar_pass   = $AR::build_meta_query( 'mb_referans', array( 'mb_f_record' => 'passive' ), '2026-09-25' );
mb_test( 'Faz 7 yönetim: "aktif" filtresi eksik meta\'yı da aktif sayar (genel görünürlük kuralıyla aynı); "pasif" yalnız açık pasif',
	'OR' === $ar_active[0]['relation'] && '!=' === $ar_active[0][0]['compare'] && 'NOT EXISTS' === $ar_active[0][1]['compare'] && array( 'relation' => 'AND', array( 'key' => '_mb_record_status', 'value' => 'passive' ) ) === $ar_pass );
$ar_exp = $AR::build_meta_query( 'mb_dokuman', array( 'mb_f_expired' => 'expired' ), '2026-09-25' );
$ar_val = $AR::build_meta_query( 'mb_dokuman', array( 'mb_f_expired' => 'valid' ), '2026-09-25' );
mb_test( 'Faz 7 yönetim: doküman süresi filtresi: süresi dolmuş = bitiş < bugün (BETWEEN 0001-01-01..dün); geçerli = boş/eksik VEYA bitiş >= bugün',
	'BETWEEN' === $ar_exp[0]['compare'] && array( '0001-01-01', '2026-09-24' ) === $ar_exp[0]['value'] && 'DATE' === $ar_exp[0]['type']
	&& 'OR' === $ar_val[0]['relation'] && 'NOT EXISTS' === $ar_val[0][0]['compare'] && '>=' === $ar_val[0][2]['compare'] && '2026-09-25' === $ar_val[0][2]['value'] );
mb_test( 'Faz 7 yönetim: görünürlük açıklaması — yayında değil / onaysız haber GİZLİ / pasif kayıt GİZLİ / herkese açık; Visibility_Guard kuralıyla aynı',
	array( 'public' => false, 'label' => 'Yayında değil' ) === $AR::visibility( 'mb_haber', 'draft', 'approved', '' )
	&& false === $AR::visibility( 'mb_haber', 'publish', 'in_review', '' )['public'] && false !== strpos( $AR::visibility( 'mb_haber', 'publish', 'draft', '' )['label'], 'onaylanmadığı' )
	&& true === $AR::visibility( 'mb_haber', 'publish', 'approved', 'passive' )['public']
	&& false === $AR::visibility( 'mb_dokuman', 'publish', '', 'passive' )['public'] && false !== strpos( $AR::visibility( 'mb_sss', 'publish', '', 'passive' )['label'], 'pasif' )
	&& true === $AR::visibility( 'mb_referans', 'publish', '', 'active' )['public'] && true === $AR::visibility( 'mb_lokasyon', 'publish', '', '' )['public'] );
mb_test( 'Faz 7 yönetim: sıralama yalnız izinli sütun anahtarından (mb_sort -> _mb_sort_order); bilinmeyen/dizi -> null (ham meta anahtarı sorguya girmez)',
	'_mb_sort_order' === $AR::sortable_meta_key( 'mb_sort' ) && null === $AR::sortable_meta_key( '_mb_sort_order' ) && null === $AR::sortable_meta_key( 'title' ) && null === $AR::sortable_meta_key( array( 'mb_sort' ) ) && null === $AR::sortable_meta_key( null ) );
