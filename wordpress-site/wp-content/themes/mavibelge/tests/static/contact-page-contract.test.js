/**
 * Static SOURCE-TEXT contract (Faz 12g): İletişim sayfası (tanitim-site/iletisim.html düzeni) — kahraman, ofis/sınav alanı
 * kartları (merkezi içerik servisi + TEK doğrulanmış yedek), sosyal medya (footer ile TEK kaynak), "Bize Yazın" (form kapısı
 * atlanmaz; kapalıyken <form>/ad alanı/nonce/jeton yok). Davranış gerçek WordPress + Chrome'da
 * tools/runtime-test/contact-page-test.js ile ayrıca sınanır. PHP/tarayıcı çalıştırmaz.
 *
 * Run: node tests/static/contact-page-contract.test.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );
var cp = require( 'child_process' );

var root = path.join( __dirname, '..', '..' );
var repo = path.join( root, '..', '..', '..', '..' );
var failures = [];
var checks = 0;
function check( label, ok ) {
	checks++;
	if ( ! ok ) {
		failures.push( label );
	}
}
function read( rel ) {
	return fs.readFileSync( path.join( root, rel ), 'utf8' );
}
function code( s ) {
	return s.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /^\s*\/\/.*$/gm, '' ).replace( /<!--[\s\S]*?-->/g, '' );
}

var page = code( read( 'page.php' ) );
var layouts = code( read( 'inc/page-layouts.php' ) );
var contact = code( read( 'template-parts/page/content-contact.php' ) );
var helpers = code( read( 'inc/contact-helpers.php' ) );
var list = code( read( 'template-parts/content/location-list.php' ) );
var card = code( read( 'template-parts/content/location-card.php' ) );
var social = code( read( 'template-parts/components/social-links.php' ) );
var footer = code( read( 'footer.php' ) );
var form = code( read( 'template-parts/forms/form.php' ) );
var bootstrap = read( 'inc/bootstrap.php' );
var css = read( 'assets/src/css/pages.css' );
var resp = read( 'assets/src/css/responsive.css' );
var dist = read( 'assets/dist/style.css' );

/* yönlendirme + kahraman */
check( 'page.php: iletisim kontrollü olarak content-contact ile; lokasyon listesi page.php\'de AYRICA çizilmez (tek render)', /'iletisim' === \$slug/.test( page ) && /template-parts\/page\/content-contact/.test( page ) && ! /content\/location-list/.test( page ) );
check( 'page-layouts: iletisim kahramanı (eyebrow İletişim + açıklama), üst kırıntı YOK', /'iletisim'\s*=> array\(/.test( layouts ) && /Merkez ofisimiz, sınav alanlarımız ve iletişim bilgilerimiz\./.test( layouts ) );
check( 'bootstrap: contact-helpers.php yüklenir', /contact-helpers\.php/.test( bootstrap ) );

/* lokasyonlar */
check( 'helpers: mavibelge_contact_locations() önce mavibelge_get_locations() (merkezi servis), yoksa TEK yedek', /function mavibelge_contact_locations\(/.test( helpers ) && /mavibelge_get_locations\(\)/.test( helpers ) && /mavibelge_fallback_locations\(\)/.test( helpers ) );
check( 'helpers: yedek veri statik referansla doğrulanmış 4 lokasyon; harita URL\'si yalnız referanstaki Ankara bağlantısı', ( helpers.match( /'name'\s*=>/g ) || [] ).length === 4 && ( helpers.match( /https:\/\/www\.google\.com\/maps/g ) || [] ).length === 1 );
check( 'footer: sabit lokasyon kopyası YOK; mavibelge_contact_locations() kullanır', /mavibelge_contact_locations\(\)/.test( footer ) && footer.indexOf( 'Yıldırım Beyazıt' ) === -1 && footer.indexOf( '1176 Sokak' ) === -1 );
check( 'location-list: yalnız mavibelge_contact_locations(); tek bölüm H2 (görsel gizli olabilir); 3 sütun ızgara', /mavibelge_contact_locations\(\)/.test( list ) && /<h2[^>]*id="locations-heading"/.test( list ) && /class="location-grid"/.test( list ) );
check( 'location-card: H3 başlık; tel: bağlantı; mailto yalnız veri varsa; harita yalnız map_url varsa + noopener noreferrer + yeni sekme metni; yoksa yer tutucu', /'heading_level'/.test( card ) && /esc_url\( \$phone\['tel'\], array\( 'tel' \) \)/.test( card ) && /mailto:/.test( card ) && /rel="noopener noreferrer"/.test( card ) && /yeni sekmede açılır/.test( card ) && /map-placeholder/.test( card ) );
check( 'location-card: sahte harita/iframe/izleme betiği YOK', ! /<iframe|<script|maps\.googleapis/.test( card + contact + list ) );

/* sosyal medya */
check( 'social-links: URL\'ler mavibelge_social_links() tek kaynağından; noopener noreferrer; aria-label; ikonlar aria-hidden', /mavibelge_social_links\(\)/.test( social ) && /rel="noopener noreferrer"/.test( social ) && /aria-label=/.test( social ) && /aria-hidden="true"/.test( social ) );
check( 'helpers: sosyal hesaplar mevcut doğrulanmış footer değerleri (facebook mavibelge31, instagram mavi_belge, twitter mavibelge31) — yeni hesap yok', /https:\/\/www\.facebook\.com\/mavibelge31/.test( helpers ) && /https:\/\/www\.instagram\.com\/mavi_belge/.test( helpers ) && /https:\/\/twitter\.com\/mavibelge31/.test( helpers ) && ( helpers.match( /'url'\s*=>\s*'https:\/\/(www\.)?(facebook|instagram|twitter|x)\./g ) || [] ).length === 3 );
check( 'footer ve iletişim sosyal bölümü AYNI parçayı kullanır (bağlantı kopyası yok)', /components\/social-links/.test( footer ) && /components\/social-links/.test( contact ) && footer.indexOf( 'facebook.com' ) === -1 && contact.indexOf( 'facebook.com' ) === -1 );
check( 'iletişim: "Sosyal Medya" H2 + açıklama', /Sosyal Medya/.test( contact ) && /Güncel duyurularımızı ve gelişmelerimizi resmi sosyal medya hesaplarımızdan takip edebilirsiniz\./.test( contact ) );

/* Bize Yazın + kapı */
check( 'iletişim: "Bize Yazın" H2 bölümü açık gri (bg-light) ve dar kapsayıcı', /class="bg-light contact-write"/.test( contact ) && /Bize Yazın/.test( contact ) && /contact-form-wrap/.test( contact ) );
check( 'iletişim: kapı kararı yalnız DTO open (gate atlanmaz); açık dal merkezi forms/form renderer\'ı', /true === \$form\['open'\]/.test( contact ) && /template-parts\/forms\/form'/.test( contact ) && ! /save_config|recipient_email|consent_approved/.test( contact ) );
var closedStart = contact.indexOf( 'contact-closed' );
var closed = contact.slice( closedStart );
check( 'kapalı dal: <form>, name=, <input>, <textarea>, <select>, nonce, jeton, bal küpü YOK', closedStart > 0 && ! /<form\b|\bname=|<input\b|<textarea\b|<select\b|nonce|mb_token|hp-field/.test( closed ) );
check( 'kapalı dal: kullanılamıyor + gerçek mesaj gönderilemez + telefon/e-posta ile ulaşın (tel:/mailto: tek kaynaktan)', /şu anda kullanılamıyor/.test( closed ) && /gerçek mesaj gönderilemez/.test( closed ) && /mavibelge_primary_contact\(\)/.test( contact ) );
check( 'kapalı dal: yönetici nedenleri yalnız manage_options', /current_user_can\(\s*'manage_options'\s*\)/.test( contact ) );
check( 'form.php: isteğe bağlı iki sütunlu satır (rows) — alan işaretlemesi field.php\'de kalır', /\$rows/.test( form ) && /class="form-row"/.test( form ) && /forms\/field/.test( form ) );
check( 'iletişim: Ad Soyad + Telefon aynı satırda (rows)', /'rows'\s*=>\s*array\(\s*array\(\s*'full_name',\s*'phone'\s*\)\s*\)/.test( contact ) );

/* CSS */
check( 'CSS: lokasyon ızgarası 3 sütun; 1024 altında 2; 768 altında 1', /\.location-grid \{[^}]*grid-template-columns: repeat\(3, 1fr\)/.test( css ) && /@media \(max-width: 1024px\)[\s\S]*?\.location-grid \{ grid-template-columns: repeat\(2, 1fr\); \}/.test( resp ) && /@media \(max-width: 768px\)[\s\S]*?\.location-grid \{ grid-template-columns: 1fr; \}/.test( resp ) );
check( 'CSS: iletişim formu dar alan (720px), adres/telefon kırılır (overflow-wrap)', /\.contact-form-wrap \{[^}]*max-width: 720px/.test( css ) && /\.location-card \{[^}]*overflow-wrap: anywhere/.test( css ) );
check( 'CSS: iletişim sosyal ikonları açık zeminde okunur ve >=44px dokunma alanı', /\.contact-social \.footer-social-list a \{[^}]*min-width: 44px;[^}]*min-height: 44px;/.test( css ) );
check( 'dist/style.css kaynak kurallarını içerir', dist.indexOf( '.location-grid {' ) !== -1 && dist.indexOf( '.contact-form-wrap {' ) !== -1 );

/* tanitim-site */
var st = '';
try {
	st = cp.execFileSync( 'git', [ 'status', '--porcelain', '--', 'tanitim-site' ], { cwd: repo, encoding: 'utf8' } );
} catch ( e ) {
	st = 'git yok';
}
check( 'tanitim-site/** değişmedi (yalnız korunan izlenmeyen zip)', st.split( '\n' ).filter( function ( l ) { return l.trim() && ! /yeni-mavibelge-v1\.zip$/.test( l ); } ).length === 0 );

if ( failures.length ) {
	failures.forEach( function ( f ) {
		console.log( 'FAIL  ' + f );
	} );
	console.log( failures.length + '/' + checks + ' başarısız' );
	process.exit( 1 );
}
console.log( 'Tüm ' + checks + ' iletişim sayfası sözleşme testi geçti (statik kaynak taraması; davranış gerçek WordPress/Chrome\'da ayrıca).' );
