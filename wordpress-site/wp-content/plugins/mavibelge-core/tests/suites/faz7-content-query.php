<?php
/**
 * Faz 7 — haber/doküman/referans/lokasyon/SSS için SAF sorgu sözleşmesi testleri
 * (MaviBelge_Core_Content_Query). WordPress gerektirmez.
 */

$CQ = 'MaviBelge_Core_Content_Query';

mb_test( 'Faz 7 sorgu: haber türü yalnız haber/duyuru; dizi, büyük harf, boşluklu, bilinmeyen -> boş (filtre yok)',
	'haber' === $CQ::normalize_news_type( 'haber' ) && 'duyuru' === $CQ::normalize_news_type( ' duyuru ' ) && '' === $CQ::normalize_news_type( 'Haber' ) && '' === $CQ::normalize_news_type( array( 'haber' ) )
	&& '' === $CQ::normalize_news_type( 'haber,duyuru' ) && '' === $CQ::normalize_news_type( null ) && '' === $CQ::normalize_news_type( 5 ) );
mb_test( 'Faz 7 sorgu: sayfa numarası pozitif tam sayı dizgesi; 0/negatif/ondalık/üstel/dizi/çok uzun -> 1; üst sınır MAX_PAGE',
	1 === $CQ::normalize_page( '' ) && 3 === $CQ::normalize_page( '3' ) && 1 === $CQ::normalize_page( '0' ) && 1 === $CQ::normalize_page( '-2' ) && 1 === $CQ::normalize_page( '1.5' ) && 1 === $CQ::normalize_page( '1e2' )
	&& 1 === $CQ::normalize_page( array( 4 ) ) && 1 === $CQ::normalize_page( '9999999' ) && $CQ::MAX_PAGE === $CQ::normalize_page( '999999' ) && 4 === $CQ::normalize_page( 4 ) && 1 === $CQ::normalize_page( '٣' ) );
mb_test( 'Faz 7 sorgu: kategori slug\'ı yalnız küçük harf/rakam/tire (biçim); büyük harf, boşluk, çift tire, dizi, aşırı uzun -> boş',
	'kalite-politikalari' === $CQ::normalize_slug( 'kalite-politikalari' ) && 'a1' === $CQ::normalize_slug( ' A1 ' ) && '' === $CQ::normalize_slug( 'a--b' ) && '' === $CQ::normalize_slug( '-a' ) && '' === $CQ::normalize_slug( 'a b' )
	&& '' === $CQ::normalize_slug( array( 'a' ) ) && '' === $CQ::normalize_slug( str_repeat( 'a', 101 ) ) && '' === $CQ::normalize_slug( "a\nb" ) && '' === $CQ::normalize_slug( 'ş' ) );
mb_test( 'Faz 7 sorgu: normalize_news_args/document_args/faq_args ham GET dizisini kapalı şekle indirir; dizi/nesne değerler yok sayılır',
	array( 'type' => 'duyuru', 'page' => 2 ) === $CQ::normalize_news_args( array( 'mb_type' => 'duyuru', 'mb_page' => '2', 'diger' => 'x' ) )
	&& array( 'type' => '', 'page' => 1 ) === $CQ::normalize_news_args( array( 'mb_type' => array( 'duyuru' ), 'mb_page' => array( '2' ) ) )
	&& array( 'category' => 'politikalar', 'page' => 1 ) === $CQ::normalize_document_args( array( 'mb_cat' => 'politikalar' ) )
	&& array( 'category' => '' ) === $CQ::normalize_faq_args( array( 'mb_cat' => new stdClass() ) ) );
mb_test( 'Faz 7 sorgu: sayfa sıkıştırma [1, toplam]; toplam sayfa sayısı yukarı yuvarlanır ve en az 1',
	1 === $CQ::clamp_page( 0, 5 ) && 5 === $CQ::clamp_page( 99, 5 ) && 3 === $CQ::clamp_page( 3, 5 ) && 1 === $CQ::clamp_page( 4, 0 )
	&& 1 === $CQ::total_pages( 0, 12 ) && 1 === $CQ::total_pages( 12, 12 ) && 2 === $CQ::total_pages( 13, 12 ) && 1 === $CQ::total_pages( -5, 12 ) && 5 === $CQ::total_pages( 5, 0 ) );
mb_test( 'Faz 7 görünürlük: yalnız açık `passive` gizler (eksik/bozuk meta = aktif); haber yalnız `approved` görünür',
	false === $CQ::is_publicly_active( 'passive' ) && true === $CQ::is_publicly_active( 'active' ) && true === $CQ::is_publicly_active( '' ) && true === $CQ::is_publicly_active( false )
	&& true === $CQ::is_news_approved( 'approved' ) && false === $CQ::is_news_approved( 'draft' ) && false === $CQ::is_news_approved( 'in_review' ) && false === $CQ::is_news_approved( 'rejected' ) && false === $CQ::is_news_approved( '' ) && false === $CQ::is_news_approved( true ) );
mb_test( 'Faz 7 doküman süresi: geçerlilik bitişi bugünden ÖNCEYSE süresi dolmuş; bugün/gelecek/boş/bozuk biçim değil',
	true === $CQ::is_document_expired( '2026-01-01', '2026-09-25' ) && false === $CQ::is_document_expired( '2026-09-25', '2026-09-25' ) && false === $CQ::is_document_expired( '2027-01-01', '2026-09-25' )
	&& false === $CQ::is_document_expired( '', '2026-09-25' ) && false === $CQ::is_document_expired( '01.01.2026', '2026-09-25' ) && false === $CQ::is_document_expired( null, '2026-09-25' ) );
mb_test( 'Faz 7 güvenli dış bağlantı: yalnız https + ana makine; http, javascript:, data:, protokolsüz, boşluk/tırnak/kontrol karakteri, dizi -> boş',
	'https://ornek.example/yol?a=1' === $CQ::safe_external_url( 'https://ornek.example/yol?a=1' ) && '' === $CQ::safe_external_url( 'http://ornek.example' ) && '' === $CQ::safe_external_url( 'javascript:alert(1)' )
	&& '' === $CQ::safe_external_url( 'data:text/html;base64,AAAA' ) && '' === $CQ::safe_external_url( '//ornek.example' ) && '' === $CQ::safe_external_url( 'ornek.example' ) && '' === $CQ::safe_external_url( 'https://' )
	&& '' === $CQ::safe_external_url( 'https://ornek.example/a b' ) && '' === $CQ::safe_external_url( "https://ornek.example/\"x" ) && '' === $CQ::safe_external_url( "https://ornek.example/\x00" ) && '' === $CQ::safe_external_url( array( 'https://x.example' ) )
	&& '' === $CQ::safe_external_url( 'HTTPS:///yol' ) && 'HTTPS://ORNEK.EXAMPLE' === $CQ::safe_external_url( 'HTTPS://ORNEK.EXAMPLE' ) );
mb_test( 'Faz 7 dosya güvenliği: doküman MIME izin listesi (PDF/DOC/DOCX/XLS/XLSX); html/js/php/svg/exe reddedilir; logo yalnız png/jpeg/webp/gif (SVG YOK)',
	'PDF' === $CQ::document_type_label( 'application/pdf' ) && 'DOCX' === $CQ::document_type_label( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' )
	&& null === $CQ::document_type_label( 'text/html' ) && null === $CQ::document_type_label( 'application/x-httpd-php' ) && null === $CQ::document_type_label( 'image/svg+xml' ) && null === $CQ::document_type_label( 'application/x-msdownload' ) && null === $CQ::document_type_label( null )
	&& true === $CQ::is_allowed_logo_mime( 'image/png' ) && true === $CQ::is_allowed_logo_mime( 'image/webp' ) && false === $CQ::is_allowed_logo_mime( 'image/svg+xml' ) && false === $CQ::is_allowed_logo_mime( 'text/html' ) && false === $CQ::is_allowed_logo_mime( array() ) );
mb_test( 'Faz 7 dosya boyutu etiketi: B/KB/MB, negatif/dizgi/ondalık -> boş',
	'512 B' === $CQ::format_bytes( 512 ) && '2 KB' === $CQ::format_bytes( 2048 ) && '1,5 MB' === $CQ::format_bytes( 1572864 ) && '' === $CQ::format_bytes( -1 ) && '' === $CQ::format_bytes( '512' ) && '' === $CQ::format_bytes( 1.5 ) );
mb_test( 'Faz 7 sabitler: sayfa boyutları ve limitler pozitif ve sınırlı (sınırsız sorgu yok)',
	$CQ::NEWS_PAGE_SIZE > 0 && $CQ::NEWS_PAGE_SIZE <= 50 && $CQ::DOC_PAGE_SIZE > 0 && $CQ::DOC_PAGE_SIZE <= 50 && $CQ::REFERENCE_LIMIT <= 100 && $CQ::LOCATION_LIMIT <= 50 && $CQ::FAQ_LIMIT <= 200 );
