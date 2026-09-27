<?php
/**
 * Slug -> presentation layout map for generic WordPress `page` content,
 * consulted by page.php. This is the "kontrollü slug→layout eşleme
 * yardımcı fonksiyonu" option from the Faz 4 brief §4.3 — chosen over
 * writing ~20 near-identical page-{slug}.php files, and over a single
 * giant switch template, because every layout here really only differs
 * in which template-parts/page/content-*.php it calls; see
 * docs/template-architecture.md for the full rationale.
 *
 * Nothing here is content (yeterlilik/ücret/haber/referans data stays
 * banned from PHP per the brief) — only site *navigation* structure,
 * i.e. which page slugs are hub pages and what their card links are.
 * The hub link data intentionally mirrors inc/menu-fallback.php's
 * primary-nav submenu groups (same source labels/paths, same class of
 * "safe planned-path fallback" already reviewed and accepted in Faz 3)
 * rather than refactoring that already-hardened shared file under this
 * narrower task's scope — see the note above each array below.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mavibelge_page_layout_for_slug( $slug ) {
	$hub = array( 'kurumsal', 'bilgi-merkezi', 'sinav-ve-basvuru' );
	if ( in_array( $slug, $hub, true ) ) {
		return 'hub';
	}

	$form_disabled = array( 'online-basvuru', 'sinav-talepleri', 'itiraz-sikayet', 'iletisim', 'is-basvurusu' );
	if ( in_array( $slug, $form_disabled, true ) ) {
		return 'form-disabled';
	}

	return 'default';
}

/**
 * Faz 12f — sayfa kahramanı ek bilgisi (tanitim-site'deki eyebrow + açıklama + üst kırıntı). Yalnız statik referansla
 * doğrulanmış sayfalar; eşleme yoksa boş dizi (kahraman yalnız başlık + kırıntı ile çizilir, önceki davranış).
 *
 * @param string $slug
 * @return array{eyebrow?: string, description?: string, parents?: array<int, array{label: string, path: string}>}
 */
function mavibelge_page_hero_for_slug( $slug ) {
	// Geriye uyumluluk (Faz 12f API'si): kahraman artık mavibelge_page_hero_args() ile çizilir; bu sarmalayıcı ikinci harita
	// DEĞİLDİR, aynı merkezi kayda delege eder.
	$presentation = mavibelge_page_presentation( $slug );
	if ( empty( $presentation ) ) {
		return array();
	}
	return array(
		'eyebrow'     => isset( $presentation['eyebrow'] ) ? $presentation['eyebrow'] : '',
		'description' => isset( $presentation['description'] ) ? $presentation['description'] : '',
		'parents'     => isset( $presentation['parents'] ) ? $presentation['parents'] : array(),
	);
}

/**
 * Faz 13 — TEK merkezi sunum kaydı (statik referans tanitim-site/<slug>.html ile doğrulanmış). Yalnız SUNUM ve gezinme
 * yapısı: içerik/veri YOK (başlık = WordPress başlığı, gövde = editör içeriği, lead = önce sayfa özeti).
 *
 * Alanlar:
 * - family      'prose' | 'list' | 'card' | 'cards' | 'steps' | 'split' | 'custom' (gövde: template-parts/page/content-presentation.php;
 *               'split' form sayfası gövdesi, 'custom' kendi gövdesi olan sayfa — iletisim/online-basvuru)
 * - width       'wide' (1280) | 'prose' (820) | 'narrow' (760)
 * - eyebrow     kahraman üst etiketi (menü grubu adı); parents: kırıntı üst sayfaları (etiket + mavibelge_url yolu)
 * - description yedek lead (yalnız WordPress özeti boşsa)
 * - heading     bölümlere ayırma başlığı (card/cards/steps); columns: kart ızgarası sütunu; list: 'numbered' | 'bullets'
 * - cta         array( label, path ) gezinme düğmesi (statik referanstaki düğme metni; hedef mavibelge_url)
 * - logo / card_logos  tema varlığı anahtarı ('myk' | 'turkak'); veri değil, marka görseli
 *
 * @param string $slug
 * @return array boş dizi: kayıt yok (önceki davranış)
 */
function mavibelge_page_presentation( $slug ) {
	$sib = array( array( 'label' => __( 'Sınav ve Başvuru', 'mavibelge' ), 'path' => 'sinav-ve-basvuru' ) );
	$bm  = array( array( 'label' => __( 'Bilgi Merkezi', 'mavibelge' ), 'path' => 'bilgi-merkezi' ) );
	$kur = array( array( 'label' => __( 'Kurumsal', 'mavibelge' ), 'path' => 'kurumsal' ) );
	$mvb = array( array( 'label' => __( 'Meslekler ve Belgeler', 'mavibelge' ), 'path' => 'yeterlilikler' ) );
	$apply = array( 'label' => __( 'Online Başvuru Yap', 'mavibelge' ), 'path' => 'online-basvuru' );
	$map   = array(
		'iletisim'              => array(
			'family'      => 'custom',
			'eyebrow'     => __( 'İletişim', 'mavibelge' ),
			'description' => __( 'Merkez ofisimiz, sınav alanlarımız ve iletişim bilgilerimiz.', 'mavibelge' ),
			'parents'     => array(),
		),
		'online-basvuru'        => array(
			'family'      => 'custom',
			'eyebrow'     => __( 'Sınav ve Başvuru', 'mavibelge' ),
			'description' => __( 'Mesleki yeterlilik sınavına başvurmak için aşağıdaki adımları tamamlayın.', 'mavibelge' ),
			'parents'     => $sib,
		),
		'sinav-surecleri'       => array(
			'family'  => 'list',
			'width'   => 'wide',
			'list'    => 'numbered',
			'eyebrow' => __( 'Sınav ve Başvuru', 'mavibelge' ),
			'parents' => $sib,
			'cta'     => $apply,
		),
		'banka-hesap-bilgileri' => array(
			'family'      => 'card',
			'width'       => 'narrow',
			'heading'     => 'h4',
			'eyebrow'     => __( 'Sınav ve Başvuru', 'mavibelge' ),
			'description' => __( 'Sınav ve belgelendirme ücreti ödemeleri için banka hesap bilgileri.', 'mavibelge' ),
			'parents'     => $sib,
		),
		'yetki-akreditasyon'    => array(
			'family'     => 'cards',
			'width'      => 'wide',
			'heading'    => 'h2',
			'columns'    => 2,
			'card_logos' => array( 'myk', 'turkak' ),
			'eyebrow'    => __( 'Kurumsal', 'mavibelge' ),
			'parents'    => $kur,
		),
		'mevzuat'               => array(
			'family'  => 'list',
			'width'   => 'prose',
			'list'    => 'bullets',
			'eyebrow' => __( 'Bilgi Merkezi', 'mavibelge' ),
			'parents' => $bm,
		),
		'myk'                   => array(
			'family'  => 'prose',
			'width'   => 'prose',
			'logo'    => 'myk',
			'eyebrow' => __( 'Bilgi Merkezi', 'mavibelge' ),
			'parents' => $bm,
		),
		'turkak'                => array(
			'family'  => 'prose',
			'width'   => 'prose',
			'logo'    => 'turkak',
			'eyebrow' => __( 'Bilgi Merkezi', 'mavibelge' ),
			'parents' => $bm,
		),
		'nasil-basvururum'      => array(
			'family'  => 'steps',
			'width'   => 'wide',
			'heading' => 'h3',
			'eyebrow' => __( 'Sınav ve Başvuru', 'mavibelge' ),
			'parents' => $sib,
			'cta'     => array( 'label' => __( 'Şimdi Başvur', 'mavibelge' ), 'path' => 'online-basvuru' ),
		),
		'sinav-takvimi'         => array(
			'family'  => 'card',
			'width'   => 'narrow',
			'heading' => 'h2',
			'eyebrow' => __( 'Sınav ve Başvuru', 'mavibelge' ),
			'parents' => $sib,
		),
		'sonuc-belge-sorgulama' => array(
			'family'  => 'card',
			'width'   => 'narrow',
			'heading' => 'h2',
			'eyebrow' => __( 'Sınav ve Başvuru', 'mavibelge' ),
			'parents' => $sib,
		),
		'itiraz-sikayet'        => array(
			'family'  => 'split',
			'width'   => 'wide',
			'list'    => 'numbered',
			'eyebrow' => __( 'Sınav ve Başvuru', 'mavibelge' ),
			'parents' => $sib,
		),
		'belge-yenileme'        => array(
			'family'  => 'cards',
			'width'   => 'wide',
			'heading' => 'h3',
			'columns' => 3,
			'list'    => 'numbered',
			'eyebrow' => __( 'Meslekler ve Belgeler', 'mavibelge' ),
			'parents' => $mvb,
			'cta'     => array( 'label' => __( 'Belge Yenileme Başvurusu Yap', 'mavibelge' ), 'path' => 'online-basvuru' ),
		),
	);
	return isset( $map[ $slug ] ) ? $map[ $slug ] : array();
}

/**
 * Faz 13 — arşiv/taksonomi ekranlarının kahraman kaydı (haberler.html, dokumanlar.html, duyurular.html). Başlık WordPress
 * arşiv etiketi / terim adıdır; açıklama yalnız WordPress tarafında (terim açıklaması) boşsa kullanılır.
 *
 * @param string $key 'haberler' | 'dokumanlar' | 'haber-turu'
 * @return array
 */
function mavibelge_archive_presentation( $key ) {
	$bm  = array( array( 'label' => __( 'Bilgi Merkezi', 'mavibelge' ), 'path' => 'bilgi-merkezi' ) );
	$map = array(
		'haberler'   => array(
			'eyebrow'     => __( 'Bilgi Merkezi', 'mavibelge' ),
			'description' => __( "Mavi Belge'ye ait güncel haberler ve kurumsal gelişmeler.", 'mavibelge' ),
			'parents'     => $bm,
		),
		'dokumanlar' => array(
			'eyebrow'     => __( 'Bilgi Merkezi', 'mavibelge' ),
			'description' => __( 'Herkese açık, versiyonlu kurumsal ve mesleki dokümanlar.', 'mavibelge' ),
			'parents'     => $bm,
		),
		'haber-turu' => array(
			'eyebrow'     => __( 'Bilgi Merkezi', 'mavibelge' ),
			'description' => __( 'Ücret, mevzuat ve süreçlere ilişkin resmi duyurular.', 'mavibelge' ),
			'parents'     => $bm,
		),
	);
	return isset( $map[ $key ] ) ? $map[ $key ] : array();
}

/**
 * Hub card links, one array per hub page slug. Mirrors
 * inc/menu-fallback.php's mavibelge_primary_nav_fallback() children for
 * "Kurumsal" (8), "Bilgi Merkezi" (9) and "Sınav ve Başvuru" (9) —
 * counts re-verified against tanitim-site/kurumsal.html,
 * bilgi-merkezi.html, sinav-ve-basvuru.html directly (not guessed).
 *
 * @param string $slug
 * @return array<int, array{label:string,path:string}>
 */
function mavibelge_hub_links_for_slug( $slug ) {
	$hubs = array(
		'kurumsal' => array(
			array( 'label' => 'Hakkımızda', 'path' => 'hakkimizda' ),
			array( 'label' => 'Misyon ve Vizyon', 'path' => 'misyon-vizyon' ),
			array( 'label' => 'Kalite Politikamız', 'path' => 'kalite-politikamiz' ),
			array( 'label' => 'Tarafsızlık Beyanı', 'path' => 'tarafsizlik-beyani' ),
			array( 'label' => 'Yasal Dayanağımız', 'path' => 'yasal-dayanagimiz' ),
			array( 'label' => 'Yetki ve Akreditasyon', 'path' => 'yetki-akreditasyon' ),
			array( 'label' => 'Referanslarımız', 'path' => 'referanslar' ),
			array( 'label' => 'Sosyal Sorumluluk', 'path' => 'sosyal-sorumluluk' ),
		),
		'bilgi-merkezi' => array(
			array( 'label' => 'Haberler', 'path' => 'haberler' ),
			array( 'label' => 'Duyurular', 'path' => 'duyurular' ),
			array( 'label' => 'Dokümanlar', 'path' => 'dokumanlar' ),
			array( 'label' => 'Mevzuat', 'path' => 'mevzuat' ),
			array( 'label' => 'Sık Sorulan Sorular', 'path' => 'sss' ),
			array( 'label' => 'MYK Nedir?', 'path' => 'myk' ),
			array( 'label' => 'TÜRKAK Nedir?', 'path' => 'turkak' ),
			array( 'label' => 'Ulusal Meslek Standartları', 'path' => 'ulusal-meslek-standartlari' ),
			array( 'label' => 'Ulusal Yeterlilikler', 'path' => 'ulusal-yeterlilikler' ),
		),
		'sinav-ve-basvuru' => array(
			array( 'label' => 'Nasıl Başvururum?', 'path' => 'nasil-basvururum' ),
			array( 'label' => 'Online Başvuru', 'path' => 'online-basvuru' ),
			array( 'label' => 'Sınav Takvimi', 'path' => 'sinav-takvimi' ),
			array( 'label' => 'Sınav Ücretleri', 'path' => 'sinav-ucretleri' ),
			array( 'label' => 'Sonuç ve Belge Sorgulama', 'path' => 'sonuc-belge-sorgulama' ),
			array( 'label' => 'Sınav Süreçleri', 'path' => 'sinav-surecleri' ),
			array( 'label' => 'Sınav Talepleri', 'path' => 'sinav-talepleri' ),
			array( 'label' => 'Banka Hesap Bilgileri', 'path' => 'banka-hesap-bilgileri' ),
			array( 'label' => 'İtiraz ve Şikayet', 'path' => 'itiraz-sikayet' ),
		),
	);

	return isset( $hubs[ $slug ] ) ? $hubs[ $slug ] : array();
}

/**
 * Resolves one hub link's real URL. Almost all paths in
 * mavibelge_hub_links_for_slug() are plain `page`/CPT-archive slugs
 * where mavibelge_url() (inc/urls.php) resolves the real URL. The one documented exception
 * is "duyurular": no page/route exists at that path (mb_haber_turu's
 * real rewrite base is "haber-turu", see docs/page-template-map.md) —
 * so that one link resolves dynamically to the real mb_haber_turu
 * "duyuru" term archive when it exists, rather than linking to a URL
 * that would 404. If the term doesn't exist yet (no data imported,
 * Faz 6), it falls back to the haberler archive so the link is never
 * dead.
 *
 * Lives in inc/page-layouts.php (loaded once via inc/bootstrap.php's
 * require_once) rather than in the template-parts/page/content-hub.php
 * template part, because a template part can be loaded more than once
 * per request (get_template_part() has no re-declaration guard) and a
 * second `function` declaration in the same request would be a fatal
 * redeclaration error.
 *
 * @param array $link array('label'=>string,'path'=>string)
 * @return string
 */
function mavibelge_resolve_hub_link_url( array $link ) {
	return mavibelge_url( $link['path'] );
}
