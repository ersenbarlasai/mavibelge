<?php
/**
 * Faz 6B3 — AÇIKÇA SAHTE, yalnız yerel/silinebilir test ortamı için apply
 * fixture manifesti. Gerçek katalog verisi DEĞİLDİR: bütün slug'lar
 * `zz-test-*`, bütün MYK kodları `97UY7xxx` (gerçek MYK kod aralığında
 * olmayan uydurma kodlar), bütün adlar "TEST ..." önekiyle başlar.
 *
 * Hem `tests/run.php` (saf PHP, sahte WordPress dünyası) hem
 * `wordpress-site/tools/runtime-test/scripts/` (izole WordPress 6.9.9,
 * klonlanmış fixture tabloları) AYNI fixture'ı kullanır — iki ayrı,
 * ayrışabilecek fixture tanımı yoktur.
 *
 * Görselli sektör YOKTUR (`image` = ''): görsel eşleme stratejisi hâlâ
 * açık bir operasyonel önkoşuldur; fixture uydurma attachment ID taşımaz.
 * WordPress fonksiyonu çağırmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mb_apply_fixture_envelopes' ) ) {

	/**
	 * @param array $overrides Opsiyonel kayıt değişiklikleri: array( 'fee' => array( <source_key> => array( alan => değer ) ), ... ).
	 * @return array{sector: array, qualification: array, fee: array} Faz 6A zarf şekli.
	 */
	function mb_apply_fixture_envelopes( array $overrides = array() ) {
		$src = array(
			'sector'        => array( 'file' => 'tanitim-site/assets/data/sectors.js', 'sha256' => hash( 'sha256', 'mavibelge-apply-fixture/sectors' ) ),
			'qualification' => array( 'file' => 'tanitim-site/assets/data/qualifications.js', 'sha256' => hash( 'sha256', 'mavibelge-apply-fixture/qualifications' ) ),
			'fee'           => array( 'file' => 'tanitim-site/assets/data/fees.js', 'sha256' => hash( 'sha256', 'mavibelge-apply-fixture/fees' ) ),
		);

		$sectors = array();
		foreach ( array(
			array( 'zz-test-a', 'TEST Sektör A', 'gear' ),
			array( 'zz-test-b', 'TEST Sektör B', 'bolt' ),
			array( 'zz-test-c', 'TEST Sektör C', 'flask' ),
		) as $i => $s ) {
			$sectors[] = array(
				'schema_version' => '2.0.0',
				'source_key'     => 'sector:' . $s[0],
				'source_index'   => $i,
				'slug'           => $s[0],
				'name'           => $s[1],
				'description'    => 'Sahte test sektörü (yalnız yerel apply fixture).',
				'icon'           => $s[2],
				'image'          => '',
				'source'         => $src['sector'],
			);
		}

		$qualifications = array();
		foreach ( array(
			array( '97UY7001-3/01', 'TEST Yeterlilik Bir', 3, 'zz-test-a', '01' ),
			array( '97UY7002-4', 'TEST Yeterlilik İki', 4, 'zz-test-b', '' ),
			array( '97UY7003-5/02', 'TEST Yeterlilik Üç', 5, 'zz-test-c', '02' ),
		) as $i => $q ) {
			$qualifications[] = array(
				'schema_version'                          => '2.0.0',
				'source_key'                              => 'qualification:' . $q[0],
				'source_index'                            => $i,
				'code'                                    => $q[0],
				'name'                                    => $q[1],
				'level'                                   => $q[2],
				'sector_slug'                             => $q[3],
				'revision'                                => $q[4],
				'has_revision'                            => '' !== $q[4],
				'matches_legacy_revision_required_format' => '' !== $q[4],
				'planned_record_status'                   => 'active',
				'source'                                  => $src['qualification'],
			);
		}

		$fees = array();
		foreach ( array(
			// profession, slug, level, sector, code, pricing_type, options
			array( 'TEST Meslek Bir', 'test-meslek-bir', 3, 'zz-test-a', '97UY7001-3/01', 'single', array( array( 'Sınav ücreti', array( 'A1', 'A2' ), 1000000 ) ) ),
			array( 'TEST Meslek Iki', 'test-meslek-iki', 4, 'zz-test-b', '97UY7002-4', 'multiple', array( array( 'Teorik', array(), 500000 ), array( 'Pratik', array( 'B1' ), 750000 ) ) ),
			array( 'TEST Meslek Uc', 'test-meslek-uc', 5, 'zz-test-c', '97UY7003-5/02', 'single', array( array( 'Sınav ücreti', array(), 1250000 ) ) ),
			array( 'TEST Meslek Dort', 'test-meslek-dort', 2, 'zz-test-a', '', 'single', array( array( 'Tek birim fiyatı', array(), 300000 ) ) ),
			array( 'TEST Meslek Bes', 'test-meslek-bes', 1, 'zz-test-b', '', 'unit', array( array( 'Birim fiyatı', array( 'U1' ), 200000 ) ) ),
		) as $i => $f ) {
			$options = array();
			$amounts = array();
			foreach ( $f[6] as $order => $o ) {
				$options[] = array( 'label' => $o[0], 'units' => $o[1], 'amount_kurus' => $o[2], 'sort_order' => $order );
				$amounts[] = $o[2];
			}
			$fees[] = array(
				'schema_version'              => '2.0.0',
				'source_key'                  => 'fee:' . $f[3] . ':' . $f[2] . ':' . $f[1],
				'source_index'                => $i,
				'profession_name'             => $f[0],
				'level'                       => $f[2],
				'sector_slug'                 => $f[3],
				'qualification_code'          => $f[4],
				'qualification_source_key'    => '' === $f[4] ? null : 'qualification:' . $f[4],
				'pricing_type'                => $f[5],
				'price_options'               => $options,
				'min_amount_kurus'            => min( $amounts ),
				'max_amount_kurus'            => max( $amounts ),
				'vat_included'                => true,
				'certificate_print_fee_kurus' => 150000,
				'source_name'                 => 'TEST Ücret Listesi (sahte fixture)',
				'source_page'                 => 1,
				'source_attachment_id'        => 0,
				'planned_tariff_period'       => '2026',
				'planned_record_status'       => 'draft',
				'planned_valid_from'          => '',
				'planned_valid_until'         => '',
				'source'                      => $src['fee'],
			);
		}

		$lists = array( 'sector' => $sectors, 'qualification' => $qualifications, 'fee' => $fees );
		foreach ( $overrides as $type => $byKey ) {
			foreach ( $lists[ $type ] as $i => $record ) {
				if ( isset( $byKey[ $record['source_key'] ] ) ) {
					$lists[ $type ][ $i ] = array_merge( $record, $byKey[ $record['source_key'] ] );
				}
			}
		}

		$optionsTotal = 0;
		$multi        = 0;
		$multiOptions = 0;
		$withCode     = 0;
		foreach ( $lists['fee'] as $fee ) {
			$optionsTotal += count( $fee['price_options'] );
			if ( count( $fee['price_options'] ) > 1 ) {
				$multi++;
				$multiOptions += count( $fee['price_options'] );
			}
			$withCode += '' === $fee['qualification_code'] ? 0 : 1;
		}

		return array(
			'sector'        => array( 'schema_version' => '2.0.0', 'record_type' => 'sector', 'count' => count( $lists['sector'] ), 'source' => $src['sector'], 'notes' => array( 'SAHTE apply fixture — gerçek katalog verisi değildir.' ), 'records' => $lists['sector'] ),
			'qualification' => array( 'schema_version' => '2.0.0', 'record_type' => 'qualification', 'count' => count( $lists['qualification'] ), 'source' => $src['qualification'], 'notes' => array( 'SAHTE apply fixture — gerçek katalog verisi değildir.' ), 'records' => $lists['qualification'] ),
			'fee'           => array(
				'schema_version' => '2.0.0',
				'record_type'    => 'fee',
				'count'          => count( $lists['fee'] ),
				'counts'         => array(
					'total'             => count( $lists['fee'] ),
					'priceOptionsTotal' => $optionsTotal,
					'pricingSingle'     => count( $lists['fee'] ) - $multi,
					'pricingMulti'      => $multi,
					'multiOptionsTotal' => $multiOptions,
					'feesWithCode'      => $withCode,
					'feesWithoutCode'   => count( $lists['fee'] ) - $withCode,
					'linkedCount'       => $withCode,
				),
				'source'         => $src['fee'],
				'records'        => $lists['fee'],
			),
		);
	}

	/**
	 * Faz 7 içerik aktarımı — AÇIKÇA SAHTE haber/referans zarfları (`zz-test-haber-*`,
	 * `zz-test-ref-*`). Gerçek haber/referans verisi DEĞİLDİR. Zarf şekli
	 * `sectors.manifest.json` ile aynıdır (schema_version/record_type/count/source/notes/records).
	 *
	 * @param array $overrides array( 'news' => array( <source_key> => array( alan => değer ) ), 'reference' => ... ).
	 * @return array{news: array, reference: array}
	 */
	function mb_content_fixture_envelopes( array $overrides = array() ) {
		$src = array(
			'news'      => array( 'file' => 'tanitim-site/assets/data/news.js', 'sha256' => hash( 'sha256', 'mavibelge-content-fixture/news' ) ),
			'reference' => array( 'file' => 'tanitim-site/assets/data/references.js', 'sha256' => hash( 'sha256', 'mavibelge-content-fixture/references' ) ),
		);

		$news = array();
		foreach ( array(
			array( 'zz-test-haber-a', 'TEST Haber A', '2026-01-15', 'haber', 'Sahte haber A özeti.', 'Sahte haber A gövdesi. Yalnız yerel apply fixture; gerçek haber değildir.' ),
			array( 'zz-test-haber-b', 'TEST Duyuru B', '2026-02-20', 'duyuru', 'Sahte duyuru B özeti.', 'Sahte duyuru B gövdesi. Yalnız yerel apply fixture.' ),
			array( 'zz-test-haber-c', 'TEST Haber C', '2025-12-31', 'haber', 'Sahte haber C özeti.', "Sahte haber C gövdesi.\nİkinci satır." ),
		) as $i => $n ) {
			$news[] = array(
				'schema_version' => '2.0.0',
				'source_key'     => 'news:' . $n[0],
				'source_index'   => $i,
				'slug'           => $n[0],
				'title'          => $n[1],
				'published_on'   => $n[2],
				'news_type'      => $n[3],
				'summary'        => $n[4],
				'body'           => $n[5],
				'source'         => $src['news'],
			);
		}

		$references = array();
		foreach ( array(
			array( 'zz-test-ref-a', 'ZZ Test Ref A' ),
			array( 'zz-test-ref-b', 'ZZ Test Ref B' ),
			array( 'zz-test-ref-c', 'ZZ Test Ref C' ),
		) as $i => $r ) {
			$references[] = array(
				'schema_version' => '2.0.0',
				'source_key'     => 'reference:' . $r[0],
				'source_index'   => $i,
				'name'           => $r[1],
				'slug'           => $r[0],
				'logo_file'      => 'tanitim-site/assets/images/references/' . $r[0] . '.svg',
				'alt'            => $r[1] . ' — temsili referans logosu (sahte fixture)',
				'source'         => $src['reference'],
			);
		}

		$lists = array( 'news' => $news, 'reference' => $references );
		foreach ( $overrides as $type => $byKey ) {
			foreach ( $lists[ $type ] as $i => $record ) {
				if ( isset( $byKey[ $record['source_key'] ] ) ) {
					$lists[ $type ][ $i ] = array_merge( $record, $byKey[ $record['source_key'] ] );
				}
			}
		}

		return array(
			'news'      => array( 'schema_version' => '2.0.0', 'record_type' => 'news', 'count' => count( $lists['news'] ), 'source' => $src['news'], 'notes' => array( 'SAHTE içerik fixture — gerçek haber verisi değildir.' ), 'records' => $lists['news'] ),
			'reference' => array( 'schema_version' => '2.0.0', 'record_type' => 'reference', 'count' => count( $lists['reference'] ), 'source' => $src['reference'], 'notes' => array( 'SAHTE içerik fixture — gerçek müşteri referansı değildir.' ), 'records' => $lists['reference'] ),
		);
	}

	/** İçerik zarflarını bir dizine sabit dosya adlarıyla yazar (yalnız test dizinleri). */
	function mb_content_fixture_write_dir( $dir, array $envelopes ) {
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		$names = array( 'news' => 'news.manifest.json', 'reference' => 'references.manifest.json' );
		foreach ( $names as $type => $name ) {
			if ( isset( $envelopes[ $type ] ) ) {
				file_put_contents( $dir . '/' . $name, json_encode( $envelopes[ $type ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			}
		}
	}

	/** Zarfları bir dizine sabit Faz 6A dosya adlarıyla yazar (yalnız test dizinleri). */
	function mb_apply_fixture_write_dir( $dir, array $envelopes ) {
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		$names = array( 'sector' => 'sectors.manifest.json', 'qualification' => 'qualifications.manifest.json', 'fee' => 'fees.manifest.json' );
		foreach ( $names as $type => $name ) {
			file_put_contents( $dir . '/' . $name, json_encode( $envelopes[ $type ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
	}
}
