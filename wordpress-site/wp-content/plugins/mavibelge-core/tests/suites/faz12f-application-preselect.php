<?php
/**
 * Faz 12f — online başvuru yeterlilik seçenekleri ve `meslek` ön seçimi (SAF; WordPress gerektirmez).
 * Veri SENTETİKTİR (gerçek kişi verisi yok); MYK kodları biçim olarak geçerli örneklerdir.
 */

$QO = 'MaviBelge_Core_Forms_Qualification_Options';

$qo_rows = array(
	array( 'code' => '11UY0011-3/03', 'title' => 'Ahşap Kalıpçı', 'level' => '3', 'public' => true ),
	array( 'code' => '11UY0014-3/02', 'title' => 'Alüminyum Kaynakçısı', 'level' => '3', 'public' => true ),
	array( 'code' => '17UY0268-3', 'title' => 'Liman RTG Operatörü', 'level' => '', 'public' => true ),
	array( 'code' => '99UY9999-3/00', 'title' => 'Gizli Taslak', 'level' => '3', 'public' => false ),
	array( 'code' => '', 'title' => 'Kodsuz', 'level' => '4', 'public' => true ),
	array( 'code' => '12UY0061-3/04', 'title' => 'Mobil Vinç A', 'level' => '3', 'public' => true ),
	array( 'code' => '12UY0061-3/04', 'title' => 'Mobil Vinç B', 'level' => '3', 'public' => true ),
	array( 'code' => '13UY0137-3/01', 'title' => 'Bitim İşlemleri Operatörü', 'level' => 'x9', 'public' => true ),
);
$qo = $QO::build( $qo_rows );

mb_test( 'Faz 12f seçenek etiketi: "{Ad} — {Kod} (Seviye {N})"',
	isset( $qo['11UY0011-3/03'] ) && 'Ahşap Kalıpçı — 11UY0011-3/03 (Seviye 3)' === $qo['11UY0011-3/03'] );
mb_test( 'Faz 12f seviye yoksa/geçersizse uydurulmaz: yalnız ad + kod',
	'Liman RTG Operatörü — 17UY0268-3' === $qo['17UY0268-3'] && 'Bitim İşlemleri Operatörü — 13UY0137-3/01' === $qo['13UY0137-3/01'] );
mb_test( 'Faz 12f herkese açık olmayan (taslak/gizli/pasif) ve kodsuz kayıt seçenek olmaz',
	! isset( $qo['99UY9999-3/00'] ) && ! isset( $qo[''] ) );
mb_test( 'Faz 12f aynı MYK koduna iki herkese açık kayıt = veri bütünlüğü sorunu: kod TAMAMEN dışlanır (ilk/son kayıt rastgele seçilmez)',
	! isset( $qo['12UY0061-3/04'] ) && 4 === count( $qo ) && array( '12UY0061-3/04' ) === $QO::duplicates( $qo_rows ) );
mb_test( 'Faz 12f seçenek anahtarları yalnız kod; sıra girdiyle aynı (başlık sırası çağırandan)',
	array( '11UY0011-3/03', '11UY0014-3/02', '17UY0268-3', '13UY0137-3/01' ) === array_keys( $qo ) );

mb_test( 'Faz 12f meslek: geçerli ve seçeneklerde olan kod -> aynı kod',
	'11UY0011-3/03' === $QO::requested( '11UY0011-3/03', $qo ) && '17UY0268-3' === $QO::requested( '17UY0268-3', $qo ) );
mb_test( 'Faz 12f meslek: parametre yok/boş/dizi/sayı/null -> boş',
	'' === $QO::requested( null, $qo ) && '' === $QO::requested( '', $qo ) && '' === $QO::requested( array( '11UY0011-3/03' ), $qo ) && '' === $QO::requested( 113, $qo ) && '' === $QO::requested( true, $qo ) );
mb_test( 'Faz 12f meslek: bilinmeyen, taslak/gizli ve yinelenen kod -> boş (yalnız herkese açık tekil kayıt seçilir)',
	'' === $QO::requested( '10UY0002-3/03', $qo ) && '' === $QO::requested( '99UY9999-3/00', $qo ) && '' === $QO::requested( '12UY0061-3/04', $qo ) );
mb_test( 'Faz 12f meslek: bozuk biçim, HTML/JS, satır sonu, boşluk, aşırı uzun, çift kodlanmış değer -> boş',
	'' === $QO::requested( 'BOZUK', $qo ) && '' === $QO::requested( '<script>alert(1)</script>', $qo ) && '' === $QO::requested( "11UY0011-3/03\n", $qo )
	&& '' === $QO::requested( ' 11UY0011-3/03', $qo ) && '' === $QO::requested( str_repeat( '1', 5000 ), $qo ) && '' === $QO::requested( '11UY0011-3%2F03', $qo ) && '' === $QO::requested( '11uy0011-3/03', $qo ) );
mb_test( 'Faz 12f meslek: seçenek listesi boşsa hiçbir değer seçilmez',
	'' === $QO::requested( '11UY0011-3/03', array() ) );
