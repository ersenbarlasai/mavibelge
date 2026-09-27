<?php
/**
 * Minimal bootstrap for running the WordPress-independent portion of
 * mavibelge-core's tests WITHOUT a WordPress installation.
 *
 * Only defines ABSPATH so each file's direct-access guard does not
 * exit. Loads class-validator.php, class-field-repository.php and
 * class-meta-schema.php — none of these three call any WordPress
 * function at class-definition time (only inside individual methods),
 * so they're safe to load standalone as long as tests only exercise
 * code paths that don't reach a WordPress function (e.g. the 'select'
 * and 'checkbox' field types, which are pure PHP; 'text'/'textarea'
 * paths that call sanitize_text_field()/sanitize_textarea_field() are
 * NOT exercisable here).
 *
 * DB-dependent / WordPress-dependent methods (post_exists_of_type,
 * normalize_id_list_of_type, user_exists, is_myk_combination_unique,
 * MaviBelge_Core_Meta_Schema::register_all()/auth_callback(),
 * anything touching $_POST/$postarr in class-publish-readiness.php or
 * admin/class-meta-boxes.php) are NOT exercised by this standalone
 * bootstrap — they need a genuine WordPress test environment (wp-env /
 * WP_UnitTestCase), which this phase does not have available (see
 * docs/compatibility.md).
 *
 * Faz 5: class-catalog-query.php is loaded here too — like the three
 * files above, it never calls a WordPress function at class-definition
 * time (only inside individual methods, none of which this bootstrap's
 * tests exercise for WP-dependent behavior).
 * public/class-catalog-service.php is deliberately NOT loaded here: it
 * calls WP_Query/get_terms/current_time/etc. throughout and has no
 * WordPress-independent code path to test standalone — its correctness
 * needs a real WordPress test environment (see tests/README.md).
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/includes/class-validator.php';
require_once dirname( __DIR__ ) . '/includes/class-field-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-meta-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-catalog-query.php';
require_once dirname( __DIR__ ) . '/includes/class-content-query.php';
require_once dirname( __DIR__ ) . '/includes/class-content-admin-rules.php';
require_once dirname( __DIR__ ) . '/includes/forms/class-forms-schema.php';
require_once dirname( __DIR__ ) . '/includes/forms/class-forms-validator.php';
require_once dirname( __DIR__ ) . '/includes/forms/class-forms-qualification-options.php';
require_once dirname( __DIR__ ) . '/includes/forms/class-forms-config.php';
require_once dirname( __DIR__ ) . '/includes/class-rate-limit.php';
require_once dirname( __DIR__ ) . '/includes/class-cache.php';
require_once dirname( __DIR__ ) . '/includes/class-hardening.php';
require_once dirname( __DIR__ ) . '/includes/class-maintenance.php';
require_once dirname( __DIR__ ) . '/includes/class-compat.php';
require_once dirname( __DIR__ ) . '/includes/class-health-checks.php';
require_once dirname( __DIR__ ) . '/includes/class-uninstall-scope.php';
require_once dirname( __DIR__ ) . '/audit/class-audit-chain.php';
require_once dirname( __DIR__ ) . '/includes/forms/class-forms-security.php';
require_once dirname( __DIR__ ) . '/includes/forms/class-forms-mail-builder.php';
require_once dirname( __DIR__ ) . '/includes/forms/class-forms-mailer.php';
require_once dirname( __DIR__ ) . '/includes/seo/class-seo-meta.php';
require_once dirname( __DIR__ ) . '/includes/seo/class-seo-schema.php';
require_once dirname( __DIR__ ) . '/includes/seo/class-seo-robots.php';
require_once dirname( __DIR__ ) . '/includes/redirects/class-redirects-rules.php';
// By-Mid Kapsam Kapanışı — sektör term-meta sözleşmesi (sector_term_meta_contract())
// ve kapsam denetimi saftır; dosya yüklenirken WordPress fonksiyonu çağrılmaz.
require_once dirname( __DIR__ ) . '/includes/class-taxonomies.php';

// Faz 6B1 — pure decision-engine classes (no WordPress function calls
// anywhere in these four files), safe to load standalone exactly like
// the four above.
require_once dirname( __DIR__ ) . '/includes/import/class-import-hash.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-managed-fields.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-decision.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-record-validator.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-dry-run-planner.php';

// Faz 6B2 — pure loader/interface/service. `interface-import-target-repository.php`
// (pure interface), `class-import-manifest-loader.php` (only native PHP:
// file_get_contents/json_decode/realpath, no WordPress function anywhere)
// and `class-import-dry-run-service.php` (depends only on the interface +
// the loader + the pure Faz 6B1 planner — the concrete WordPress
// repository is INJECTED by the caller, never constructed inside the
// service, so the service itself never touches a WordPress function) are
// safe to load standalone, exactly like the files above.
// `class-import-wordpress-target-repository.php` (calls get_term_by()/
// get_posts()/get_post_meta()/etc. throughout), `class-import-cli-command.php`
// (WP_CLI) and `admin/class-import-dry-run-page.php` are DELIBERATELY NOT
// loaded here — like public/class-catalog-service.php above, they have no
// WordPress-independent code path to test standalone; their correctness
// needs a real WordPress test environment (see tests/README.md and the
// "NOT covered" list at the end of tests/run.php).
require_once dirname( __DIR__ ) . '/includes/import/interface-import-target-repository.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-manifest-loader.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-dry-run-service.php';

// Faz 6B3 Önkoşul — saf, yazmayan yazma güvenliği sınıfları (WordPress
// fonksiyonu çağırmazlar; Meta_Schema yukarıda zaten yüklü).
require_once dirname( __DIR__ ) . '/includes/import/class-import-write-payload.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-apply-eligibility.php';

// Faz 6B3 — apply/rollback: arayüzler + saf sınıflar + servisler. Servisler
// WordPress fonksiyonu ÇAĞIRMAZ; bütün WordPress erişimi enjekte edilen
// arayüzlerin (repository/writer/transaction/run store/audit sink) arkasındadır.
require_once dirname( __DIR__ ) . '/includes/import/interface-import-target-writer.php';
require_once dirname( __DIR__ ) . '/includes/import/interface-import-transaction.php';
require_once dirname( __DIR__ ) . '/includes/import/interface-import-run-store.php';
require_once dirname( __DIR__ ) . '/includes/import/interface-import-audit-sink.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-run-state.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-plan-snapshot.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-apply-plan.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-rollback-codec.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-audit-context.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-run-finalizer.php';
require_once dirname( __DIR__ ) . '/includes/import/interface-import-image-map-store.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-sector-image-map.php';
require_once dirname( __DIR__ ) . '/audit/class-audit-log.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-apply-service.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-rollback-service.php';
// WordPress uygulamaları: dosyayı yüklemek yalnız sınıfı TANIMLAR (metot
// gövdeleri WordPress fonksiyonu çağırır ve burada ÇALIŞTIRILMAZ); PHP arayüz
// uyumunu sınıf bildiriminde denetlediği için yükleme, dört arayüzün eksiksiz
// uygulandığının da kanıtıdır. Gerçek davranış izole WordPress'te sınanır.
require_once dirname( __DIR__ ) . '/includes/import/class-import-wordpress-target-writer.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-wpdb-transaction.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-wpdb-run-store.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-wp-audit-sink.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-wp-image-map-store.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-page-publisher.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-runtime-factory.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-admin-gates.php';
require_once dirname( __DIR__ ) . '/includes/import/class-import-admin-run-service.php';

// `class-import-wordpress-target-repository.php` itself calls get_terms()/
// get_posts()/get_post_meta()/etc. inside most of its methods (NOT safe to
// EXERCISE standalone) — but requiring the FILE only defines the class
// (and, since PHP checks interface conformance at class-declaration time,
// this also proves at LOAD time that the class fully implements
// `MaviBelge_Core_Import_Target_Repository`, including `get_diagnostics()`
// added in Düzeltme ve Kabul §2.3 — a missing method here would be a fatal
// error on this very `require_once`, not a silently-passing test). Its
// `strict_int()`/`strict_nonneg_int_or_empty_zero()`/`strict_bool()` static
// helpers (Düzeltme ve Kabul §2.2) are pure PHP (is_int/is_string/preg_match
// only). Loading it here lets tests/run.php test JUST those three helpers
// directly — plus, since the Son Kapanış Düzeltmesi, the equally pure
// strict_string(), marker_raw_matches(), normalize_last_applied_hash_raw()
// and sector_/qualification_/fee_fields_from_raw() builders (no WordPress
// call inside any of them); every other method on this class remains untested here (see
// tests/run.php's "NOT covered" list) and must NOT be called from
// tests/run.php.
require_once dirname( __DIR__ ) . '/includes/import/class-import-wordpress-target-repository.php';

// Runtime Doğrulama turu — admin dry-run sayfasının dosyası yalnız sınıfı
// tanımlar (add_action yalnız init() içinde, burada çağrılmaz). Yalnız saf
// `normalize_paged_value()` test edilir; render/nonce/yetki yolu WordPress
// gerektirir ve gerçek WordPress runtime'ında ayrıca doğrulanır.
require_once dirname( __DIR__ ) . '/admin/class-import-dry-run-page.php';
