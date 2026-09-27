/**
 * Static SOURCE-TEXT contract (Faz 12f): online başvuru ekranı (tanitim-site/online-basvuru.html düzeni) ve `meslek`
 * ön seçimi. Form kapısı atlanmaz; kapalı formda <form>/ad alanı/nonce/jeton çizilmez; açık formda adımlar ilerlemeli
 * iyileştirmedir (JS yokken bütün alanlar erişilebilir). Davranış gerçek WordPress + Chrome'da
 * tools/runtime-test/application-form-test.js ile ayrıca sınanır. PHP/tarayıcı çalıştırmaz.
 *
 * Run: node tests/static/application-form-contract.test.js
 */
'use strict';

var fs = require( 'fs' );
var path = require( 'path' );

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
	return s.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /^\s*\/\/.*$/gm, '' );
}

var page = code( read( 'page.php' ) );
var layouts = code( read( 'inc/page-layouts.php' ) );
var shells = code( read( 'inc/form-shells.php' ) );
var app = code( read( 'template-parts/page/content-application.php' ) );
var form = code( read( 'template-parts/forms/form.php' ) );
var field = code( read( 'template-parts/forms/field.php' ) );
var js = read( 'assets/src/js/application.js' );
var css = read( 'assets/src/css/pages.css' );
var dist = read( 'assets/dist/style.css' );
var distJs = read( 'assets/dist/main.js' );
var helpers = code( read( 'inc/qualification-helpers.php' ) );
var card = code( read( 'template-parts/catalog/qualification-card.php' ) );
var single = code( read( 'single-mb_yeterlilik.php' ) );
var build = fs.readFileSync( path.join( repo, 'wordpress-site', 'tools', 'build', 'build-theme-assets.js' ), 'utf8' );
var service = fs.readFileSync( path.join( repo, 'wordpress-site', 'wp-content', 'plugins', 'mavibelge-core', 'public', 'class-forms-service.php' ), 'utf8' );

/* URL üretimi */
check( 'başvuru URL: tek merkezi yardımcı; mavibelge_url(online-basvuru) + add_query_arg(meslek, rawurlencode) — tek kodlama', /mavibelge_url\(\s*'online-basvuru'\s*\)/.test( helpers ) && /add_query_arg\(\s*'meslek',\s*rawurlencode\(\s*\$code\s*\)/.test( helpers ) && ! /urlencode\(\s*rawurlencode|rawurlencode\(\s*rawurlencode/.test( helpers ) );
check( 'kart ve detay CTA aynı yardımcıyı kullanır', /mavibelge_application_url\(\s*\$myk_code\s*\)/.test( card ) && /mavibelge_application_url\(\s*\$myk_code\s*\)/.test( single ) );
check( 'URL elle sabitlenmez (/index.php/ veya home_url slug yok)', ! /\/index\.php/.test( helpers + app + page ) && ! /home_url\(\s*'\/[a-z]/.test( helpers + app + page ) );

/* sayfa kahramanı */
check( 'page-layouts: online-basvuru kahramanı (eyebrow "Sınav ve Başvuru", açıklama, üst kırıntı sinav-ve-basvuru)', /function mavibelge_page_hero_for_slug\(/.test( layouts ) && /'online-basvuru'/.test( layouts ) && /Sınav ve Başvuru/.test( layouts ) && /Mesleki yeterlilik sınavına başvurmak için aşağıdaki adımları tamamlayın\./.test( layouts ) && /'sinav-ve-basvuru'/.test( layouts ) );
check( 'page.php: kahraman eşlemesini kullanır; kırıntı Anasayfa ile başlar', /mavibelge_page_hero_for_slug\(/.test( page ) && /__\( 'Anasayfa', 'mavibelge' \)/.test( page ) && /'eyebrow'/.test( page ) && /'description'/.test( page ) );

/* form kapısı */
check( 'page.php: online-basvuru content-application ile; kapı kararı DTO open alanından (gate atlanmaz)', /content-application/.test( page ) && /\$form\['open'\]/.test( page ) );
check( 'content-application: açık dal yalnız open===true (veya PRG başarı) iken forms/form çağırır', /true === \$form\['open'\]/.test( app ) && /template-parts\/forms\/form'/.test( app ) );
var closedStart = app.indexOf( 'mb-application-preview' );
var closed = app.slice( closedStart );
check( 'kapalı önizleme: <form>, name=, nonce, jeton, input (metin/dosya/onay) YOK', closedStart > 0 && ! /<form\b/.test( closed ) && ! /\bname=/.test( closed ) && ! /nonce|mb_token|wp_nonce_field/.test( closed ) && ! /<input\b/.test( closed ) );
check( 'kapalı önizleme: "şu anda kullanılamıyor" + gerçek başvuru gönderilmediği açıkça yazılı', /şu anda kullanılamıyor/.test( closed ) && /gerçek başvuru gönderilmez/.test( closed ) );
check( 'kapalı önizleme: Devam Et devre dışı ve açıklamaya bağlı (aria-describedby)', /<button type="button" class="btn btn-primary" disabled aria-describedby="/.test( closed ) && /Devam Et/.test( closed ) );
check( 'kapalı önizleme: meslek + sınav alanı seçimleri DTO seçeneklerinden; ön seçim preselect', /'qualification'/.test( closed ) && /'exam_location'/.test( closed ) && /\$form\['preselect'\]/.test( app ) && /selected\(/.test( closed ) );
check( 'kapalı önizleme: yönetici nedenleri yalnız manage_options', /current_user_can\(\s*'manage_options'\s*\)/.test( app ) );

/* adım göstergesi */
check( 'form-shells: dört adım (Meslek Seçimi/Kişisel Bilgiler/Belgeler/Onay) ve alan eşlemesi', /function mavibelge_application_steps\(/.test( shells ) && [ 'Meslek Seçimi', 'Kişisel Bilgiler', 'Belgeler', 'Onay' ].every( function ( t ) { return shells.indexOf( t ) !== -1; } ) && /'qualification', 'exam_location'/.test( shells ) );
check( 'kapalı gösterge: ilk adım aria-current="step"', /aria-current="step"/.test( closed ) && /step-indicator/.test( closed ) );
check( 'açık form: gösterge gerçek <button> (data-step-goto), JS yokken gizli (hidden)', /<ol class="step-indicator" data-step-indicator hidden>/.test( form ) && /<button type="button" class="step-tab" data-step-goto=/.test( form ) );
check( 'açık form: her adım fieldset + legend içinde H2; adım eylemleri JS yokken gizli; gönder düğmesi her zaman DOM\'da', /<fieldset class="form-step" data-step=/.test( form ) && /<legend[^>]*><h2 class="form-step-title"/.test( form ) && /data-step-actions hidden/.test( form ) && /data-step-submit/.test( form ) && /type="submit"/.test( form ) );
check( 'açık form: POST değeri GET ön seçiminden önce (values -> preselect)', /isset\( \$values\[ \$name \] \) \? \$values\[ \$name \] : \( isset\( \$preselect\[ \$name \] \)/.test( field ) );
check( 'açık form: güvenlik öğeleri korunur (nonce, jeton, bal küpü, hassas alan geri doldurulmaz, aria-invalid/describedby)', /wp_nonce_field\(/.test( form ) && /\$form\['names'\]\['token'\]/.test( form ) && /hp-field/.test( form ) && /\$field\['sensitive'\] \? '' :/.test( field ) && /aria-invalid="true"/.test( field ) && /aria-describedby/.test( field ) );

/* JS */
check( 'JS: ilerlemeli iyileştirme — göstergeyi/adım eylemlerini açar, aria-current="step", odak yönetimi', /data-step-form/.test( js ) && /aria-current', 'step'/.test( js ) && /\.focus\(/.test( js ) && /hidden = false/.test( js ) );
check( 'JS: hata sonrası aria-invalid alanın adımını açar; hata özetindeki bağlantı hedefin adımını açar', /\[aria-invalid="true"\]/.test( js ) && /form-error-summary/.test( js ) );
check( 'JS: ileri adımda yalnız o adım doğrulanır (checkValidity); gerileme serbest', /checkValidity\(\)/.test( js ) && /data-step-prev/.test( js ) && /data-step-next/.test( js ) );
check( 'JS: prefers-reduced-motion dikkate alınır (animasyonlu kaydırma yok)', /prefers-reduced-motion/.test( js ) );
check( 'build: application.js JS_ORDER içinde; dist/main.js onu içerir', /JS_ORDER = \[[^\]]*'application'/.test( build ) && /data-step-form/.test( distJs ) );

/* CSS */
check( 'CSS: dar başvuru alanı (800px), gösterge etkin/tamam durumları, [hidden] güvenli', /\.application-wrap \{[^}]*max-width: 800px/.test( css ) && /\.step-indicator li\.is-active/.test( css ) && /\.step-indicator li\.is-done/.test( css ) && /\.step-actions\[hidden\]/.test( css ) && /\.form-step\[hidden\]/.test( css ) );
check( 'CSS: gösterge düğmesi görünür odak ve yalnız renge dayanmayan etkin durum (alt çizgi + kalın)', /\.step-tab:focus-visible/.test( css ) && /\.step-indicator li\.is-active \{[^}]*border-bottom-color/.test( css ) );
check( 'CSS: sunucu hata özeti ve alan hataları görünür (statik demo gizleme kurallarını geçersiz kılar)', /\.form-error-summary \{ display: block;/.test( css ) && /\.form-field\.has-error \.field-error \{ display: block; \}/.test( css ) );
check( 'dist/style.css kaynak kurallarını içerir', dist.indexOf( '.application-wrap {' ) !== -1 && dist.indexOf( '.form-field.has-error .field-error { display: block; }' ) !== -1 );

/* eklenti: seçenek etiketi + yinelenen kod + ön seçim */
check( 'eklenti: dynamic_options saf kurucuyu kullanır; describe() preselect yalnız POST sonucu yokken ve yalnız application', /MaviBelge_Core_Forms_Qualification_Options::build\(/.test( service ) && /null === \$result && 'application' === \$formId/.test( service ) && /'preselect'\s*=> \$preselect/.test( service ) );

if ( failures.length ) {
	failures.forEach( function ( f ) {
		console.log( 'FAIL  ' + f );
	} );
	console.log( failures.length + '/' + checks + ' başarısız' );
	process.exit( 1 );
}
console.log( 'Tüm ' + checks + ' online başvuru sözleşme testi geçti (statik kaynak taraması; davranış gerçek WordPress/Chrome\'da ayrıca).' );
