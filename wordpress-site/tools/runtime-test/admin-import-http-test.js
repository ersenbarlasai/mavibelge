'use strict';
/**
 * Faz 6B4 — admin katalog aktarımı GERÇEK HTTP testi (admin-ajax, gerçek oturum çerezleri, gerçek nonce'lar).
 * YALNIZ mbfx_ fixture veritabanına yönlendirilmiş yerel ortamda çalışır (admin-import-http.sh). Kapılar (HTTPS,
 * wp-config sabitleri, WordPress ortam türü, üretim host'u) test ortamında işaret dosyalarıyla benzetilir; üretim kodunda
 * hiçbir test kancası yoktur. Çerez/parola/nonce değerleri ekrana basılmaz.
 *   node admin-import-http-test.js <cookie-dizini>   (cookies-<kullanıcı>.json)
 */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const BASE = 'http://127.0.0.1:18673';
const WP = 'mbruntime6b2-wp-1';
const cookieDir = process.argv[2];
const PAGE = '/wp-admin/tools.php?page=mavibelge-core-import-dry-run';
const AJAX = '/wp-admin/admin-ajax.php';
let pass = 0;
let fail = 0;
const bodies = [];

function check(label, cond, detail) {
	if (cond) {
		pass++;
		console.log('PASS  ' + label);
	} else {
		fail++;
		console.log('FAIL  ' + label + (detail ? '  [' + detail + ']' : ''));
	}
}
function jar(user) {
	const j = JSON.parse(fs.readFileSync(path.join(cookieDir, 'cookies-' + user + '.json'), 'utf8'));
	return Object.entries(j).map(([k, v]) => k + '=' + v).join('; ');
}
function sh(cmd) {
	return execFileSync('docker', ['exec', WP, 'sh', '-c', cmd], { encoding: 'utf8' });
}
function marker(name, on, content) {
	if (on) {
		sh(`printf '%s' '${content || '1'}' > /tmp/${name} && chmod 644 /tmp/${name}`);
	} else {
		sh(`rm -f /tmp/${name}`);
	}
}
function state(s) {
	marker('mbfx-https', !!s.https);
	marker('mbfx-env-staging', s.env === 'staging');
	marker('mbfx-env-production', s.env === 'production');
	marker('mbfx-imp-apply', !!s.apply);
	marker('mbfx-imp-admin', !!s.admin);
	marker('mbfx-imp-prod', !!s.prod);
	marker('mbfx-imp-host', !!s.host, s.host);
}
function fxEval(php) {
	return execFileSync('docker', ['exec', '-u', 'www-data', WP, 'wp', '--path=/var/www/html', '--require=/opt/mb-runtime/fixture-env.php', 'eval', php], { encoding: 'utf8' }).trim();
}
const sectors = () => Number(fxEval('global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = \'mb_sektor\' AND t.slug LIKE \'zz-test-%\'" );'));
const runsCount = () => Number(fxEval('global $wpdb; $t = $wpdb->prefix . "mb_import_runs"; echo ( $t === $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $t ) ) ) ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE stage <> CHAR(112,97,103,101,115)" ) : 0;'));
const runStatus = (uid) => fxEval('global $wpdb; echo (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}mb_import_runs WHERE uid = %s", "' + uid + '" ) );');

async function req(method, url, opts) {
	opts = opts || {};
	const headers = Object.assign({}, opts.headers || {});
	if (opts.user) headers.Cookie = jar(opts.user);
	let body;
	if (opts.form) {
		body = new URLSearchParams(opts.form).toString();
		headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
	}
	const res = await fetch(BASE + url, { method, headers, body, redirect: 'manual' });
	const text = await res.text();
	bodies.push(text);
	let json = null;
	try {
		json = JSON.parse(text);
	} catch (e) {
		json = null;
	}
	return { status: res.status, text, json, type: res.headers.get('content-type') || '' };
}

let nonces = {};
let actions = {};
/** action: kısa ad (preview_stage ...); fields: kapalı anahtar kümesi dışındaki alanlar. */
function ajax(user, action, fields, o) {
	o = o || {};
	const form = { action: 'mavibelge_import_' + action };
	if (!o.omitNonce) form.mb_import_nonce = o.nonce || nonces[o.nonceOf || action];
	Object.assign(form, fields || {}, o.extra || {});
	if (o.method === 'GET') {
		return req('GET', AJAX + '?' + new URLSearchParams(form).toString(), { user });
	}
	return req('POST', AJAX + (o.query ? '?' + o.query : ''), { user, form });
}

(async () => {
	sh('rm -f /tmp/mbfx-https /tmp/mbfx-env-staging /tmp/mbfx-env-production /tmp/mbfx-imp-apply /tmp/mbfx-imp-admin /tmp/mbfx-imp-prod /tmp/mbfx-imp-host');
	sh("printf '%s' '/tmp/mbfx-b4-manifest' > /tmp/mbfx-imp-manifest && chmod 644 /tmp/mbfx-imp-manifest");

	/* ---------- 1) sayfa: yalnız manage_options; kapılar KAPALI görünür ---------- */
	state({});
	const page = await req('GET', PAGE, { user: 'mbadmin' });
	check('admin sayfası 200; dört bölüm başlığı ve sistem kapıları görünür', page.status === 200 && /Katalog Aktarımı/.test(page.text) && /1\) Sistem kapıları/.test(page.text) && /3\) Aşama önizleme ve uygulama/.test(page.text) && /4\) Run listesi ve geri alma/.test(page.text), String(page.status));
	check('sistem kapıları: apply KAPALI ve engel gerekçeleri (HTTPS, iki sabit, ortam türü) sabit metinle gösterilir',
		/<strong>KAPALI<\/strong>/.test(page.text) && /HTTPS üzerinden olmalı/.test(page.text) && /MAVIBELGE_IMPORT_APPLY_ENABLED sabiti true değil/.test(page.text) && /MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED sabiti true değil/.test(page.text) && /WP_ENVIRONMENT_TYPE staging/.test(page.text));
	check('sayfa sızdırmaz: mutlak sunucu yolu, /tmp, SQL yok', !/\/var\/www|\/tmp\/|wp-content\/plugins\/mavibelge-core\/includes|SELECT /.test(page.text.replace(/<script[\s\S]*?<\/script>/g, '')));
	const m = page.text.match(/var mbImportApply = (\{[\s\S]*?\});/);
	const cfg = m ? JSON.parse(m[1]) : null;
	check('yerelleştirilmiş yapılandırma: dokuz action, dokuz action-özel nonce, aşama listesi sıralı (pages ilk)', cfg && Object.keys(cfg.actions).length === 9 && Object.keys(cfg.nonces).length === 9 && new Set(Object.values(cfg.nonces)).size >= 2 && cfg.stages.join(',') === 'pages,sectors,qualifications,all,content' && /^mb_import_nonce$/.test(cfg.nonceField));
	if (!cfg) {
		console.log('\n0 testi yapılamadı: yerelleştirilmiş yapılandırma bulunamadı');
		process.exit(1);
	}
	nonces = cfg.nonces;
	actions = cfg.actions;
	check('script ve kontrol listesi: import-apply.js yüklenir (defer/footer), wp.media kuyruğa alınır', /admin\/assets\/import-apply\.js\?ver=/.test(page.text) && /media-editor|media-views|wp-media/.test(page.text));
	for (const u of ['mbeditor', 'mbsubscriber']) {
		const r = await req('GET', PAGE, { user: u });
		check('sayfa erişimi: ' + u + ' (manage_options yok) -> 403', r.status === 403, String(r.status));
	}
	const anonPage = await req('GET', PAGE);
	check('sayfa erişimi: oturumsuz -> giriş yönlendirmesi (302), içerik YOK', anonPage.status === 302 && !/Katalog Aktarımı/.test(anonPage.text));

	/* ---------- 2) kapı sırası: HTTPS, yöntem, giriş, yetki ---------- */
	state({ https: false });
	let r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' });
	check('HTTPS yok -> 403 not_https (önizleme dahil)', r.status === 403 && r.json && r.json.error_code === 'not_https', r.text.slice(0, 120));
	state({ https: true });
	r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' });
	check('HTTPS var, apply sabitleri KAPALI: salt okunur önizleme çalışır (200, 25 create, eligible, 64-hex digest; önkoşul pages henüz yok)', r.status === 200 && r.json && r.json.ok === true && r.json.writes === 25 && r.json.eligible === true && /^[0-9a-f]{64}$/.test(r.json.plan_digest) && r.json.prerequisites.met === false && r.json.prerequisites.requires === 'pages', r.text.slice(0, 160));
	check('önizleme DTO\'su güvenli: sektör adı/açıklaması, yol, SQL yok; entries yalnız source_key/type/decision/reason', !/TEST Sektör|Sahte test|\/tmp|SELECT/.test(r.text) && r.json.entries.length === 25 && Object.keys(r.json.entries[0]).sort().join() === 'decision,reason,source_key,type');
	const digest = r.json.plan_digest;
	r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' }, { method: 'GET' });
	check('GET ile önizleme/yazma denemesi -> 405 method_not_post', r.status === 405 && r.json && r.json.error_code === 'method_not_post', String(r.status));
	r = await ajax('mbadmin', 'start_apply', { stage: 'sectors', plan_digest: digest, confirm_phrase: 'UYGULA sectors ' + digest.slice(0, 12) }, { method: 'GET' });
	check('GET ile start_apply denemesi -> 405; run OLUŞMAZ', r.status === 405 && runsCount() === 0);
	for (const u of ['mbsubscriber', 'mbeditor', 'mbcapa', 'mbcapb']) {
		r = await ajax(u, 'preview_stage', { stage: 'sectors' });
		check('yetki: ' + u + ' -> 403 (missing_capability; yalnız iki yetkinin ikisi birden geçer)', r.status === 403 && r.json && r.json.error_code === 'missing_capability', String(r.status) + ' ' + r.text.slice(0, 80));
	}
	r = await ajax('mbcapa', 'save_sector_image_map', { 'mappings[zz-x]': '1' });
	check('yetki: yalnız manage_options olan kullanıcı görsel eşleme KAYDEDEMEZ (403)', r.status === 403 && r.json.error_code === 'missing_capability');
	r = await req('POST', AJAX, { form: { action: 'mavibelge_import_preview_stage', mb_import_nonce: nonces.preview_stage, stage: 'sectors' } });
	check('oturumsuz istek (wp_ajax_nopriv yok) -> işlem YOK: JSON ok:true değil, run yok', !(r.json && r.json.ok === true) && runsCount() === 0, String(r.status) + ' ' + r.text.slice(0, 60));

	/* ---------- 3) nonce ve kapalı request şekli (admin, HTTPS) ---------- */
	r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' }, { omitNonce: true });
	check('nonce alanı yok -> 400 (kapalı anahtar kümesi)', r.status === 400 && r.json && r.json.ok === false && ['unexpected_request_key', 'invalid_nonce'].includes(r.json.error_code), r.text.slice(0, 100));
	r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' }, { nonce: 'a1b2c3d4e5' });
	check('bozuk nonce -> 403 invalid_nonce', r.status === 403 && r.json.error_code === 'invalid_nonce');
	r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' }, { nonceOf: 'advance_apply' });
	check('başka action\'ın nonce\'ı (nonce action\'a özel) -> 403 invalid_nonce', r.status === 403 && r.json.error_code === 'invalid_nonce');
	r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' }, { extra: { mb_force: '1' } });
	check('fazladan anahtar (mb_force) -> 400 unexpected_request_key', r.status === 400 && r.json.error_code === 'unexpected_request_key');
	r = await ajax('mbadmin', 'preview_stage', {}, {});
	check('eksik anahtar (stage yok) -> 400', r.status === 400 && r.json.error_code === 'unexpected_request_key');
	r = await ajax('mbadmin', 'preview_stage', { stage: 'fees' });
	check('bilinmeyen aşama -> 400 invalid_request', r.status === 400 && r.json.error_code === 'invalid_request');
	r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' }, { query: 'x=1' });
	check('URL parametresi taşıyan istek -> 400 unexpected_request_key (GET boş olmalı)', r.status === 400 && r.json.error_code === 'unexpected_request_key');
	r = await ajax('mbadmin', 'silent_admin', {}, { nonce: 'a1b2c3d4e5' });
	check('kayıtsız action -> işlem YOK (JSON ok:true değil)', !(r.json && r.json.ok === true));

	/* ---------- 4) çalıştırma kapıları: sabitler, ortam türü ---------- */
	const startBody = (phraseStage, d) => ({ stage: phraseStage, plan_digest: d, confirm_phrase: 'UYGULA ' + phraseStage + ' ' + d.slice(0, 12) });
	r = await ajax('mbadmin', 'start_apply', startBody('sectors', digest));
	check('apply sabitleri kapalı -> 403 apply_disabled; run OLUŞMAZ', r.status === 403 && r.json.error_code === 'apply_disabled' && runsCount() === 0);
	state({ https: true, apply: true });
	r = await ajax('mbadmin', 'start_apply', startBody('sectors', digest));
	check('yalnız MAVIBELGE_IMPORT_APPLY_ENABLED açık -> 403 admin_apply_disabled', r.status === 403 && r.json.error_code === 'admin_apply_disabled' && runsCount() === 0);
	state({ https: true, apply: true, admin: true });
	r = await ajax('mbadmin', 'start_apply', startBody('sectors', digest));
	check('iki sabit açık ama WP_ENVIRONMENT_TYPE=local -> 403 environment_not_allowed; run OLUŞMAZ', r.status === 403 && r.json.error_code === 'environment_not_allowed' && runsCount() === 0);
	state({ https: true, apply: true, admin: true, env: 'production' });
	r = await ajax('mbadmin', 'start_apply', startBody('sectors', digest));
	check('production: ek üretim sabiti kapalı -> 403 production_disabled', r.status === 403 && r.json.error_code === 'production_disabled' && runsCount() === 0);
	state({ https: true, apply: true, admin: true, env: 'production', prod: true });
	r = await ajax('mbadmin', 'start_apply', startBody('sectors', digest));
	check('production: üretim sabiti açık ama host sabiti YOK -> 403 production_host_mismatch', r.status === 403 && r.json.error_code === 'production_host_mismatch' && runsCount() === 0);
	state({ https: true, apply: true, admin: true, env: 'production', prod: true, host: 'evil.example' });
	r = await ajax('mbadmin', 'start_apply', startBody('sectors', digest));
	check('production: host sabiti bu host ile eşleşmiyor -> 403 production_host_mismatch', r.status === 403 && r.json.error_code === 'production_host_mismatch' && runsCount() === 0);
	/* Faz 12: aşama zinciri pages ile başlar. sectors önkoşulu pages; pages HTTP ile (staging kapıları) uygulanır. */
	const pageCount = (status) => Number(fxEval('echo count( get_posts( array( "post_type" => "page", "post_status" => "' + status + '", "numberposts" => -1, "meta_key" => "_mb_import_source_key", "fields" => "ids" ) ) );'));
	state({ https: true, apply: true, admin: true, env: 'staging' });
	r = await ajax('mbadmin', 'preview_stage', { stage: 'sectors' });
	check('aşama sırası (HTTP): pages tamamlanmadan sectors önkoşulu KARŞILANMADI (requires: pages)', r.json.ok === true && r.json.prerequisites.met === false && r.json.prerequisites.requires === 'pages');
	r = await ajax('mbadmin', 'start_apply', startBody('sectors', digest));
	check('aşama sırası (HTTP, sunucu): pages atlanıp sectors start reddedilir (prerequisite_not_met); run OLUŞMAZ', r.status === 200 && r.json.ok === false && r.json.error_code === 'prerequisite_not_met' && runsCount() === 0);
	const pgPv = await ajax('mbadmin', 'preview_stage', { stage: 'pages' });
	check('pages önizleme (HTTP): 32 create, eligible, önkoşulsuz; bloklayıcı kurum kararı YOK (yedi karar çözüldü); bilgilendirme uyarıları (notices) görünür', pgPv.json.ok === true && pgPv.json.writes === 32 && pgPv.json.eligible === true && pgPv.json.prerequisites.met === true && pgPv.json.notices.filter((n) => n.blocking).length === 0 && pgPv.json.notices.length > 0 && pgPv.json.entries.length === 32, pgPv.text.slice(0, 120));
	r = await ajax('mbadmin', 'start_apply', startBody('pages', pgPv.json.plan_digest));
	check('pages start (HTTP): ready; yazma henüz YOK (0 sayfa)', r.status === 200 && r.json.ok === true && r.json.status === 'ready' && pageCount('any') === 0, r.text.slice(0, 120));
	const pgUid = r.json.run_uid;
	let pgCp = 0;
	const pgSeq = [];
	for (let i = 0; i < 6; i++) {
		const a = await ajax('mbadmin', 'advance_apply', { run_uid: pgUid, expected_checkpoint: String(pgCp) });
		pgSeq.push(a.json);
		if (!a.json.ok || a.json.status !== 'paused') break;
		pgCp = a.json.checkpoint;
	}
	check('pages apply (HTTP): 4 istek (10,20,30,32), completed; 32 TASLAK sayfa, yayında sayfa YOK', pgSeq.length === 4 && pgSeq[3].status === 'completed' && pageCount('draft') === 32 && pageCount('publish') === 0, JSON.stringify(pgSeq.map((x) => x.status + ':' + x.remaining)));
	state({ https: true, apply: true, admin: true, env: 'production', prod: true, host: '127.0.0.1:18673' });
	r = await ajax('mbadmin', 'start_apply', startBody('sectors', digest));
	check('production: bütün üretim kapıları (iki sabit + üretim sabiti + BİREBİR host) geçince kapı açılır (fixture DB\'de run oluşur)', r.status === 200 && r.json.ok === true && r.json.status === 'ready' && runsCount() === 1, r.text.slice(0, 140));
	const prodUid = r.json.run_uid;
	r = await ajax('mbadmin', 'advance_apply', { run_uid: prodUid, expected_checkpoint: '0' });
	check('production simülasyonu: advance de bütün kapılarla geçer (10 sektör, paused)', r.status === 200 && r.json.ok === true && r.json.status === 'paused' && sectors() === 10);
	// bu run'ı staging'e dönüp geri al (temiz başlangıç için)
	state({ https: true, apply: true, admin: true, env: 'staging' });
	let pv = await ajax('mbadmin', 'preview_rollback', { run_uid: prodUid });
	check('paused (yarım) run için rollback önizleme çalışır: not rollbackable (yalnız tamamlanmış/rollback_required geri alınabilir)', pv.status === 200 && pv.json.ok === true && pv.json.blockers.length >= 1 && pv.json.blockers[0].code === 'run_not_rollbackable', pv.text.slice(0, 160));

	/* ---------- 5) staging: uçtan uca, kesinti, çift tıklama ---------- */
	// yarım kalmış prod-simülasyon run'ını bitirelim: aynı run, staging kapılarıyla devam eder (sayfa kapanıp açılmış gibi).
	state({ https: true, apply: true, admin: true, env: 'staging' });
	r = await ajax('mbadmin', 'advance_apply', { run_uid: prodUid, expected_checkpoint: '1' });
	check('staging: yarım run kaldığı yerden devam eder (20 sektör, paused, checkpoint 2)', r.status === 200 && r.json.ok === true && r.json.status === 'paused' && r.json.checkpoint === 2 && sectors() === 20, r.text.slice(0, 140));
	state({ https: true, apply: false, admin: true, env: 'staging' });
	r = await ajax('mbadmin', 'advance_apply', { run_uid: prodUid, expected_checkpoint: '2' });
	check('sabit ortada kapatılırsa devam ETMEZ (403 apply_disabled); run paused kalır, 20 sektör', r.status === 403 && r.json.error_code === 'apply_disabled' && runStatus(prodUid) === 'paused' && sectors() === 20);
	state({ https: true, apply: true, admin: true, env: 'staging' });
	// çift tıklama: aynı checkpoint'e iki EŞZAMANLI istek
	const both = await Promise.all([ajax('mbadmin', 'advance_apply', { run_uid: prodUid, expected_checkpoint: '2' }), ajax('mbadmin', 'advance_apply', { run_uid: prodUid, expected_checkpoint: '2' })]);
	const oks = both.filter((x) => x.json && x.json.ok === true);
	const rej = both.filter((x) => x.json && x.json.ok === false);
	check('AYNI checkpoint\'e iki paralel HTTP isteği: yalnız BİRİ ilerler (ok:true), diğeri stale_request/locked; toplam 25 sektör (tekrar yazma YOK)',
		oks.length === 1 && rej.length === 1 && ['stale_request', 'locked', 'run_not_resumable'].includes(rej[0].json.error_code) && sectors() === 25 && runStatus(prodUid) === 'completed', JSON.stringify(both.map((x) => x.text.slice(0, 90))));
	r = await ajax('mbadmin', 'advance_apply', { run_uid: prodUid, expected_checkpoint: '3' });
	check('tamamlanmış run\'a advance -> run_not_resumable', r.status === 200 && r.json.ok === false && r.json.error_code === 'run_not_resumable' && sectors() === 25);
	r = await ajax('mbadmin', 'preview_stage', { stage: 'qualifications' });
	check('aşama sırası (HTTP): qualifications önkoşulu karşılandı (sektörler unchanged) ve 3 create önerilir', r.json.ok === true && r.json.prerequisites.met === true && r.json.writes === 3);
	r = await ajax('mbadmin', 'start_apply', { stage: 'content', plan_digest: 'a'.repeat(64), confirm_phrase: 'UYGULA content ' + 'a'.repeat(12) });
	check('aşama sırası (HTTP): content önkoşul (all) yokken start reddedilir (prerequisite_not_met)', r.status === 200 && r.json.ok === false && r.json.error_code === 'prerequisite_not_met');
	// yanlış onay ifadesi (yeni run, sectors noop -> qualifications ile dene)
	pv = await ajax('mbadmin', 'preview_stage', { stage: 'qualifications' });
	const bad = await ajax('mbadmin', 'start_apply', { stage: 'qualifications', plan_digest: pv.json.plan_digest, confirm_phrase: 'uygula qualifications ' + pv.json.plan_digest.slice(0, 12) });
	check('yanlış (küçük harfli) onay ifadesi -> confirmation_phrase_mismatch; run OLUŞMAZ (toplam 1 run)', bad.json.ok === false && bad.json.error_code === 'confirmation_phrase_mismatch' && runsCount() === 1);

	/* ---------- 6) rollback (HTTP) ---------- */
	pv = await ajax('mbadmin', 'preview_rollback', { run_uid: prodUid });
	check('rollback önizleme: 25 bekleyen item, engel yok, 64-hex digest', pv.json.ok === true && pv.json.items_pending === 25 && pv.json.blockers.length === 0 && /^[0-9a-f]{64}$/.test(pv.json.rollback_digest));
	let rs = await ajax('mbadmin', 'start_rollback', { run_uid: prodUid, rollback_digest: pv.json.rollback_digest, confirm_phrase: 'UYGULA sectors ' + pv.json.rollback_digest.slice(0, 12) });
	check('rollback: apply ifadesi geçersiz (confirmation_phrase_mismatch); durum completed kalır', rs.json.ok === false && rs.json.error_code === 'confirmation_phrase_mismatch' && runStatus(prodUid) === 'completed');
	state({ https: true, apply: false, admin: true, env: 'staging' });
	rs = await ajax('mbadmin', 'start_rollback', { run_uid: prodUid, rollback_digest: pv.json.rollback_digest, confirm_phrase: 'GERI AL ' + prodUid.slice(0, 8) + ' ' + pv.json.rollback_digest.slice(0, 12) });
	check('rollback de sabitlere bağlı: apply sabiti kapalıyken doğru ifade bile 403 apply_disabled', rs.status === 403 && rs.json.error_code === 'apply_disabled' && runStatus(prodUid) === 'completed');
	state({ https: true, apply: true, admin: true, env: 'staging' });
	rs = await ajax('mbadmin', 'start_rollback', { run_uid: prodUid, rollback_digest: pv.json.rollback_digest, confirm_phrase: 'GERI AL ' + prodUid.slice(0, 8) + ' ' + pv.json.rollback_digest.slice(0, 12) });
	check('rollback start: doğru ifade -> rollback_ready; hiçbir kayıt henüz geri alınmadı (25)', rs.json.ok === true && rs.json.status === 'rollback_ready' && sectors() === 25);
	let cp = 0;
	const seq = [];
	for (let i = 0; i < 4; i++) {
		const a = await ajax('mbadmin', 'advance_rollback', { run_uid: prodUid, expected_checkpoint: String(cp) });
		seq.push(a.json);
		if (!a.json.ok || a.json.status !== 'rollback_paused') break;
		cp = a.json.checkpoint;
	}
	check('rollback advance zinciri: 3 istek (15, 5, 0 kalan) sonunda rolled_back; 0 sektör', seq.length === 3 && seq[0].status === 'rollback_paused' && seq[0].remaining === 15 && seq[1].remaining === 5 && seq[2].status === 'rolled_back' && sectors() === 0 && runStatus(prodUid) === 'rolled_back', JSON.stringify(seq));

	/* ---------- 6b) Faz 12: sayfa yayınlama (HTTP) — AYRI işlem: yalnız staging, açık onay, istek başına ≤10 ---------- */
	state({ https: true });
	r = await ajax('mbadmin', 'preview_publish', {});
	check('yayın önizleme (HTTP, yalnız HTTPS; salt okunur): 32 satır, hazır 30, içerik bekleyen 2 (referanslar, sss), bekletilen 0, yayında 0; onay ifadesi "YAYINLA <12>"', r.status === 200 && r.json.ok === true && r.json.summary.total === 32 && r.json.summary.ready === 30 && r.json.summary.content_not_ready === 2 && r.json.summary.held_pending_decision === 0 && r.json.summary.published === 0 && /^YAYINLA [0-9a-f]{12}$/.test(r.json.phrase) && r.json.rows.length === 32, r.text.slice(0, 160));
	check('yayın önizleme DTO\'su güvenli: mutlak yol, SQL, sayfa içeriği YOK; bekleyen kararlar Türkçe etiketle', !/\/tmp|\/var\/www|SELECT|post_content/.test(r.text) && r.json.rows.some((x) => x.pending.some((p) => p.label.length > 5)) && r.json.rows.filter((x) => x.reason === 'content_not_ready').map((x) => x.slug).sort().join() === 'referanslar,sss');
	const pubDigest = r.json.plan_digest;
	const pubPhrase = r.json.phrase;
	const pubBody = (n, ph) => ({ plan_digest: pubDigest, confirm_phrase: ph === undefined ? pubPhrase : ph, expected_remaining: String(n) });
	r = await ajax('mbadmin', 'publish_pages', pubBody(30), { method: 'GET' });
	check('yayın: GET ile deneme -> 405; yayın YOK', r.status === 405 && pageCount('publish') === 0);
	for (const u of ['mbsubscriber', 'mbeditor', 'mbcapa', 'mbcapb']) {
		r = await ajax(u, 'publish_pages', pubBody(30));
		check('yayın yetkisi: ' + u + ' -> 403 missing_capability; yayın YOK', r.status === 403 && r.json.error_code === 'missing_capability' && pageCount('publish') === 0);
	}
	state({ https: true });
	r = await ajax('mbadmin', 'publish_pages', pubBody(30));
	check('yayın: apply sabitleri kapalı -> 403 apply_disabled; yayın YOK', r.status === 403 && r.json.error_code === 'apply_disabled' && pageCount('publish') === 0);
	state({ https: true, apply: true, admin: true });
	r = await ajax('mbadmin', 'publish_pages', pubBody(30));
	check('yayın: ortam türü local -> 403 environment_not_allowed; yayın YOK', r.status === 403 && r.json.error_code === 'environment_not_allowed' && pageCount('publish') === 0);
	state({ https: true, apply: true, admin: true, env: 'production', prod: true, host: '127.0.0.1:18673' });
	r = await ajax('mbadmin', 'publish_pages', pubBody(30));
	check('yayın: üretim kapıları TAMAM olsa bile yayınlama YALNIZ staging -> 403 publish_staging_only; yayın YOK', r.status === 403 && r.json.error_code === 'publish_staging_only' && pageCount('publish') === 0, r.text.slice(0, 100));
	state({ https: true, apply: true, admin: true, env: 'staging' });
	r = await ajax('mbadmin', 'publish_pages', { plan_digest: pubDigest, confirm_phrase: pubPhrase, expected_remaining: '30', mb_force: '1' });
	check('yayın: fazladan anahtar -> 400 unexpected_request_key; yayın YOK', r.status === 400 && r.json.error_code === 'unexpected_request_key' && pageCount('publish') === 0);
	r = await ajax('mbadmin', 'publish_pages', pubBody(30, pubPhrase.toLowerCase()));
	check('yayın: yanlış (küçük harfli) onay ifadesi -> confirmation_phrase_mismatch; yayın YOK', r.status === 200 && r.json.ok === false && r.json.error_code === 'confirmation_phrase_mismatch' && pageCount('publish') === 0);
	r = await ajax('mbadmin', 'publish_pages', pubBody(29));
	check('yayın: bayat expected_remaining -> stale_request; yayın YOK', r.status === 200 && r.json.ok === false && r.json.error_code === 'stale_request' && pageCount('publish') === 0);
	r = await ajax('mbadmin', 'publish_pages', pubBody(30));
	check('yayın #1 (HTTP, staging, tüm kapılar): 10 sayfa yayınlandı, kalan 20, paused', r.status === 200 && r.json.ok === true && r.json.published === 10 && r.json.remaining === 20 && r.json.status === 'paused' && pageCount('publish') === 10, r.text.slice(0, 140));
	const pubBoth = await Promise.all([ajax('mbadmin', 'publish_pages', pubBody(20)), ajax('mbadmin', 'publish_pages', pubBody(20))]);
	const pubOk = pubBoth.filter((x) => x.json && x.json.ok === true);
	const pubRej = pubBoth.filter((x) => x.json && x.json.ok === false);
	check('yayın: AYNI expected_remaining\'e iki paralel HTTP isteği -> yalnız BİRİ yayınlar, diğeri stale_request/locked; toplam 20 (tekrar yayın YOK)', pubOk.length === 1 && pubRej.length === 1 && ['stale_request', 'locked'].includes(pubRej[0].json.error_code) && pageCount('publish') === 20, JSON.stringify(pubBoth.map((x) => x.text.slice(0, 90))));
	r = await ajax('mbadmin', 'publish_pages', pubBody(10));
	check('yayın #3: kalan 10 yayınlandı, completed; toplam 30 yayında, içerik bağımlılığı olan 2 sayfa (referanslar, sss) TASLAKTA', r.json.ok === true && r.json.published === 10 && r.json.status === 'completed' && pageCount('publish') === 30 && pageCount('draft') === 2, r.text.slice(0, 140));
	r = await ajax('mbadmin', 'publish_pages', pubBody(0));
	check('yayın idempotent: tekrar onay -> completed, 0 yayın', r.json.ok === true && r.json.published === 0 && r.json.status === 'completed' && pageCount('publish') === 30);

	/* ---------- 7) sektör görsel eşleme kaydı kapıları ---------- */
	state({ https: true });
	r = await ajax('mbadmin', 'save_sector_image_map', { 'mappings[zz-fazla]': '1' });
	check('eşleme kaydı: apply sabitleri OLMADAN çalışır ama doğrulama hatasında REDDEDİLİR (fazla slug): 200 ok:false + hata kodu', r.status === 200 && r.json.ok === false && r.json.error_codes.some((c) => c.indexOf('unexpected_slug:') === 0), r.text.slice(0, 160));
	r = await ajax('mbadmin', 'save_sector_image_map', { 'mappings[zz-fazla]': '0' });
	check('eşleme kaydı: 0 ID kapalı şekil ihlali -> 400', r.status === 400 && r.json.error_code === 'invalid_request');
	state({ https: false });
	r = await ajax('mbadmin', 'save_sector_image_map', { 'mappings[zz-fazla]': '1' });
	check('eşleme kaydı: HTTPS yoksa 403 not_https', r.status === 403 && r.json.error_code === 'not_https');
	const optRow = fxEval('echo (int) ( null !== get_option( "mavibelge_core_sector_image_map", null ) );');
	check('reddedilen eşleme istekleri option YAZMADI', optRow === '0');

	/* ---------- 8) hijyen: hiçbir yanıt iç bilgi sızdırmadı ---------- */
	const all = bodies.filter((b) => b.length < 200000).join('\n');
	check('yanıtlarda mutlak sunucu yolu, SQL, PHP uyarısı/fatal, exception izi YOK (ajax + sayfa hariç script)', !/\/var\/www\/html|Fatal error|Warning:|Notice:|Stack trace|SELECT .* FROM|wpdb/.test(bodies.filter((b) => b.trim().startsWith('{')).join('\n')));

	console.log(`\n${pass}/${pass + fail} Faz 6B4 admin HTTP testi geçti.`);
	process.exit(fail === 0 ? 0 : 1);
})().catch((e) => {
	console.log('FAIL  beklenmeyen hata: ' + String(e && e.stack ? e.stack : e).slice(0, 300));
	process.exit(1);
});
