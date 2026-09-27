#!/usr/bin/env bash
# Faz 6B3 — apply/rollback runtime döngüsü, MEVCUT izole ortamda (mbruntime6b2-*).
# Ana `wp_` test veritabanına YAZMAZ: bütün apply/rollback, `wp_` tablolarının
# `mbfx_` önekli silinebilir klonunda ve AÇIKÇA SAHTE fixture manifestiyle
# (`zz-test-*`) çalışır; sonunda klon ve manifest silinir, ana veritabanı +
# dosyalar + uploads başlangıç anlık görüntüsüyle karşılaştırılır.
# Parola veya env dosyası OKUMAZ. Konteyner/volume SİLMEZ.
# Kullanım: apply-cycle.sh <çıktı-dizini (depo DIŞINDA)>
set -uo pipefail
OUT="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
export MSYS_NO_PATHCONV=1
WP=mbruntime6b2-wp-1
mkdir -p "$OUT"
cd "$HERE"
wpx() { docker exec -u www-data "$WP" wp --path=/var/www/html "$@"; }
# fx: fixture önekinde WP-CLI. FXAPPLY=1 -> MAVIBELGE_IMPORT_APPLY_ENABLED; FXMAN=none -> gerçek manifest.
fx() { docker exec -u www-data -e MB_FX_APPLY="${FXAPPLY:-}" -e MB_FX_MANIFEST="${FXMAN:-}" "$WP" wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php "$@"; }
json() { node -e 'const s=require("fs").readFileSync(0,"utf8");const i=s.search(/^[{\[]/m);const j=JSON.parse(s.slice(i));const v=process.argv[1].split(".").reduce((o,k)=>o==null?o:o[k],j);console.log(typeof v==="object"?JSON.stringify(v):String(v));' "$1"; }
tables_exist() { fx eval 'global $wpdb; echo (int) ( $wpdb->prefix . "mb_import_runs" === $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $wpdb->prefix . "mb_import_runs" ) ) ) );'; }
snapshot() {
	local d="$OUT/$1"
	mkdir -p "$d"
	wpx eval-file /opt/mb-runtime/db-snapshot.php > "$d/db.txt"
	grep '^## TABLE' "$d/db.txt" > "$d/tables.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && ls -laR --time-style=+%s wp-content/uploads 2>/dev/null || echo "uploads yok"' > "$d/uploads.txt"
	echo "snapshot $1: $(wc -l < "$d/tables.txt") tablo, $(wc -l < "$d/files.txt") dosya, db sha256=$(sha256sum "$d/db.txt" | cut -c1-16)"
}
check() { if [ "$2" = "$3" ]; then echo "PASS  $1"; else echo "FAIL  $1  [beklenen=$3 gerçek=$2]"; fi; }

echo "== 1) başlangıç: debug log temizlenir, ana DB/dosya anlık görüntüsü A"
# WordPress çekirdeğinin tema desen önbelleği (site transient, ~30 dk) koşu ortasında
# süresi dolup yenilenmesin diye taban anlık görüntüden ÖNCE tazelenir (bizim kodumuz değil).
wpx eval 'wp_get_theme()->delete_pattern_cache(); wp_get_theme()->get_block_patterns(); echo "pattern cache tazelendi
";'
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA

echo "== 2) silinebilir fixture veritabanı (mbfx_ klonu) + sahte manifest"
wpx eval-file /opt/mb-runtime/fixture-db.php clone
wpx eval-file /opt/mb-runtime/fixture-db.php manifest

echo "== 3) WP-CLI kapıları (fixture önekinde)"
FXAPPLY= fx --user=mbadmin mavibelge import catalog --apply --stage=sectors --confirm="$(printf 'a%.0s' $(seq 64))" > "$OUT/c-a.out" 2>&1
check "CLI apply: MAVIBELGE_IMPORT_APPLY_ENABLED yok -> exit 1 (Apply kapalı)" "$?:$(grep -c 'Apply kapalı' "$OUT/c-a.out")" "1:1"
FXAPPLY=1 fx mavibelge import catalog --apply --stage=sectors > "$OUT/c-b.out" 2>&1
check "CLI apply: --user yok -> exit 1 (Yetkisiz)" "$?:$(grep -c 'Yetkisiz' "$OUT/c-b.out")" "1:1"
FXAPPLY=1 fx --user=mbeditor mavibelge import catalog --apply --stage=sectors > "$OUT/c-c.out" 2>&1
check "CLI apply: yetkisiz kullanıcı (mb_content_editor) -> exit 1 (Yetkisiz)" "$?:$(grep -c 'Yetkisiz' "$OUT/c-c.out")" "1:1"
FXAPPLY=1 fx --user=mbadmin mavibelge import catalog --apply --stage=sectors > "$OUT/c-d.out" 2>&1
check "Faz 12 CLI: önkoşul (pages) tamamlanmadan sectors apply --confirm'siz da reddedilir (exit 1, 'Aşama sırası')" "$?:$(grep -c 'Aşama sırası' "$OUT/c-d.out")" "1:1"
check "CLI apply reddi sonrası run tablosu OLUŞTURULMADI" "$(tables_exist)" "0"
FXAPPLY=1 fx --user=mbadmin mavibelge import catalog --apply --force > "$OUT/c-f.out" 2>&1
check "CLI apply: --force bilinmeyen parametre -> exit 1" "$?:$(grep -c 'force' "$OUT/c-f.out")" "1:1"
FXAPPLY=1 fx --user=mbadmin mavibelge import catalog --apply --dry-run > "$OUT/c-g.out" 2>&1
check "CLI: --apply ile --dry-run birlikte -> exit 1" "$?" "1"
fx mavibelge import catalog --dry-run --stage=sectors --format=json > "$OUT/c-dry.json" 2> "$OUT/c-dry.err"
DIGEST="$(json plan_digest < "$OUT/c-dry.json")"
check "CLI dry-run --stage=sectors: 3 create, applicable, 64-hex plan_digest" "$(json summary.operations.create < "$OUT/c-dry.json"):$(json summary.applicable < "$OUT/c-dry.json"):${#DIGEST}" "3:true:64"
check "CLI dry-run sonrası run tablosu yok (salt okunur)" "$(tables_exist)" "0"
# Faz 12: aşama zinciri gereği önce pages (gerçek TASLAK sayfa manifesti, izole fixture DB).
fx mavibelge import catalog --dry-run --stage=pages --format=json > "$OUT/c-pages-dry.json" 2> /dev/null
PGD="$(json plan_digest < "$OUT/c-pages-dry.json")"
FXAPPLY=1 fx --user=mbadmin mavibelge import catalog --apply --stage=pages --confirm="$PGD" --format=json > "$OUT/c-pages-apply.json" 2>&1
check "Faz 12 CLI: pages apply exit 0, completed, 32 item" "$?:$(json status < "$OUT/c-pages-apply.json"):$(json committed_items < "$OUT/c-pages-apply.json")" "0:completed:32"
FXAPPLY=1 fx --user=mbadmin mavibelge import catalog --apply --stage=sectors > "$OUT/c-d2.out" 2>&1
check "CLI apply: --confirm yok -> exit 1 (Onay gerekli), plan_digest gösterilir" "$?:$(grep -c 'Onay gerekli' "$OUT/c-d2.out"):$(grep -cE 'plan_digest: [0-9a-f]{64}' "$OUT/c-d2.out")" "1:1:1"
FXAPPLY=1 fx --user=mbadmin mavibelge import catalog --apply --stage=sectors --confirm="$(printf 'b%.0s' $(seq 64))" --format=json > "$OUT/c-e.out" 2>&1
check "CLI apply: yanlış onay digest'i -> exit 1 (confirmation_mismatch)" "$?:$(json error_code < "$OUT/c-e.out")" "1:confirmation_mismatch"
FXAPPLY=1 fx --user=mbadmin mavibelge import catalog --apply --stage=sectors --confirm="$DIGEST" --format=json > "$OUT/c-apply.json" 2>&1
EC=$?
RUN="$(json run_uid < "$OUT/c-apply.json")"
check "CLI apply: doğru onayla -> exit 0, completed, 3 item" "$EC:$(json status < "$OUT/c-apply.json"):$(json committed_items < "$OUT/c-apply.json")" "0:completed:3"
fx --user=mbadmin mavibelge import status --format=json > "$OUT/c-status.json" 2>&1
check "CLI status: run completed olarak listelenir" "$(node -e 'const j=JSON.parse(require("fs").readFileSync(0,"utf8"));console.log(j.filter(r=>r.run_uid===process.argv[1]).map(r=>r.status).join())' "$RUN" < "$OUT/c-status.json")" "completed"
fx --user=mbadmin mavibelge import rollback --run-id="$RUN" --format=json > "$OUT/c-rbp.json" 2>&1
EC=$?
RBD="$(json rollback_digest < "$OUT/c-rbp.json")"
check "CLI rollback önizleme: exit 0, 3 bekleyen item, engel yok, 64-hex digest" "$EC:$(json items_pending < "$OUT/c-rbp.json"):$(json blockers < "$OUT/c-rbp.json"):${#RBD}" "0:3:[]:64"
FXAPPLY=1 fx --user=mbeditor mavibelge import rollback --run-id="$RUN" --confirm="$RBD" > "$OUT/c-rbe.out" 2>&1
check "CLI rollback: yetkisiz kullanıcı -> exit 1" "$?:$(grep -c 'Yetkisiz' "$OUT/c-rbe.out")" "1:1"
FXAPPLY=1 fx --user=mbadmin mavibelge import rollback --run-id="$RUN" --confirm="$(printf 'c%.0s' $(seq 64))" --format=json > "$OUT/c-rbw.json" 2>&1
check "CLI rollback: yanlış onay -> exit 1 (confirmation_mismatch)" "$?:$(json error_code < "$OUT/c-rbw.json")" "1:confirmation_mismatch"
FXAPPLY= fx --user=mbadmin mavibelge import rollback --run-id="$RUN" --confirm="$RBD" > "$OUT/c-rbk.out" 2>&1
check "CLI rollback: anahtar kapalı -> exit 1 (Rollback kapalı)" "$?:$(grep -c 'Rollback kapalı' "$OUT/c-rbk.out")" "1:1"
FXAPPLY=1 fx --user=mbadmin mavibelge import rollback --run-id="$RUN" --confirm="$RBD" --format=json > "$OUT/c-rb.json" 2>&1
check "CLI rollback: doğru onayla -> exit 0, rolled_back, 3 item" "$?:$(json status < "$OUT/c-rb.json"):$(json rolled_back_items < "$OUT/c-rb.json")" "0:rolled_back:3"
FXMAN=none fx mavibelge import catalog --dry-run --format=json > "$OUT/c-real.json" 2> /dev/null
REALD="$(json plan_digest < "$OUT/c-real.json")"
check "gerçek manifest planı (fixture DB'de): conflict/blocked içerir, applicable=false" "$(json summary.applicable < "$OUT/c-real.json"):$(node -e 'const s=require("fs").readFileSync(0,"utf8");const j=JSON.parse(s.slice(s.search(/^[{]/m)));console.log(j.summary.operations.conflict+j.summary.operations.blocked>0)' < "$OUT/c-real.json")" "false:true"
FXAPPLY=1 FXMAN=none fx --user=mbadmin mavibelge import catalog --apply --confirm="$REALD" --format=json > "$OUT/c-realapply.json" 2>&1
check "gerçek manifest planı: apply REDDEDİLİR (plan_not_applicable veya aşama zinciri), exit 1" "$?:$(grep -cE 'plan_not_applicable|Aşama sırası' "$OUT/c-realapply.json")" "1:1"

echo "== 4) servis düzeyi apply/rollback döngüsü (gerçek WordPress uygulamaları)"
FXAPPLY=1 fx --user=mbadmin eval-file /opt/mb-runtime/apply-cycle-test.php > "$OUT/apply-cycle.txt" 2>&1
echo "apply-cycle exit=$? :: $(tail -1 "$OUT/apply-cycle.txt")"
grep -E '^FAIL' "$OUT/apply-cycle.txt" || true

echo "== 4b) Faz 6B3 düzeltme kabul kapıları (taze fixture DB): create rollback drift, rollback -> reapply döngüsü, run/audit atomikliği"
wpx eval-file /opt/mb-runtime/fixture-db.php drop
wpx eval-file /opt/mb-runtime/fixture-db.php clone
wpx eval-file /opt/mb-runtime/fixture-db.php manifest
FXAPPLY=1 fx --user=mbadmin eval-file /opt/mb-runtime/apply-cycle-test-2.php > "$OUT/apply-cycle-2.txt" 2>&1
echo "apply-cycle-2 exit=$? :: $(tail -1 "$OUT/apply-cycle-2.txt")"
grep -E '^FAIL' "$OUT/apply-cycle-2.txt" || true

echo "== 4c) Faz 7 içerik aktarımı (news/reference, content aşaması): taze fixture DB + AÇIKÇA SAHTE içerik manifesti (gerçek data/content/{news,references} kullanılmaz)"
wpx eval-file /opt/mb-runtime/fixture-db.php drop
wpx eval-file /opt/mb-runtime/fixture-db.php clone
wpx eval-file /opt/mb-runtime/fixture-db.php manifest
CMAN=/tmp/mbfx-content-manifest
# CLI kapıları: önce iki kontrollü haber türü terimi YOKKEN (varsa fixture kopyasından silinir).
FXMAN="$CMAN" fx eval 'foreach ( array( "haber", "duyuru" ) as $s ) { $t = get_term_by( "slug", $s, "mb_haber_turu" ); if ( $t ) { wp_delete_term( $t->term_id, "mb_haber_turu" ); } } echo "terms_left=" . count( get_terms( array( "taxonomy" => "mb_haber_turu", "hide_empty" => false, "fields" => "ids" ) ) );' > "$OUT/c7-terms0.out" 2>&1
FXMAN="$CMAN" fx mavibelge import catalog --dry-run --stage=content --format=json > "$OUT/c7-dry0.json" 2> /dev/null
D0="$(json plan_digest < "$OUT/c7-dry0.json")"
check "CLI content dry-run (terimler YOK): 3 create + 3 blocked, applicable=false, by_type news=3 reference=3" "$(json summary.operations.create < "$OUT/c7-dry0.json"):$(json summary.operations.blocked < "$OUT/c7-dry0.json"):$(json summary.applicable < "$OUT/c7-dry0.json"):$(json summary.by_type.news < "$OUT/c7-dry0.json"):$(json summary.by_type.reference < "$OUT/c7-dry0.json")" "3:3:false:3:3"
FXAPPLY=1 FXMAN="$CMAN" fx --user=mbadmin mavibelge import catalog --apply --stage=content --confirm="$D0" --format=json > "$OUT/c7-apply0.json" 2>&1
check "Faz 12 CLI aşama zinciri: önkoşulsuz (all tamamlanmadan) content apply reddedilir (exit 1, 'Aşama sırası'); run tablosu OLUŞMAZ" "$?:$(grep -c 'Aşama sırası' "$OUT/c7-apply0.json"):$(FXMAN="$CMAN" tables_exist)" "1:1:0"
# Tam zincir: pages -> sectors -> qualifications -> all (sahte katalog + gerçek TASLAK sayfa manifesti; izole fixture DB). Sonra content.
CFULL=/tmp/mbfx-c7-full
FXAPPLY=1 FXMAN="$CFULL" fx --user=mbadmin mavibelge import catalog --apply --stage=sectors --confirm="$(printf 'b%.0s' $(seq 64))" --format=json > "$OUT/c12-order.json" 2>&1
check "Faz 12 CLI aşama zinciri: pages atlanıp sectors apply reddedilir (exit 1, 'Aşama sırası')" "$?:$(grep -c 'Aşama sırası' "$OUT/c12-order.json")" "1:1"
for STG in pages sectors qualifications all; do
	FXMAN="$CFULL" fx mavibelge import catalog --dry-run --stage=$STG --format=json > "$OUT/c12-dry-$STG.json" 2> /dev/null
	DG="$(json plan_digest < "$OUT/c12-dry-$STG.json")"
	FXAPPLY=1 FXMAN="$CFULL" fx --user=mbadmin mavibelge import catalog --apply --stage=$STG --confirm="$DG" --format=json > "$OUT/c12-apply-$STG.json" 2>&1
	check "Faz 12 CLI zincir: $STG apply exit 0, completed" "$?:$(json status < "$OUT/c12-apply-$STG.json")" "0:completed"
done
check "Faz 12 CLI: pages aşaması 32 sayfayı TASLAK (draft) oluşturdu; yayında sayfa yok" "$(FXMAN="$CFULL" fx eval 'echo count( get_posts( array( "post_type" => "page", "post_status" => "draft", "numberposts" => -1, "meta_key" => "_mb_import_source_key", "fields" => "ids" ) ) ) . ":" . count( get_posts( array( "post_type" => "page", "post_status" => "publish", "numberposts" => -1, "meta_key" => "_mb_import_source_key", "fields" => "ids" ) ) );')" "32:0"
CMAN0="$CMAN"
CMAN="$CFULL"
FXMAN="$CMAN" fx mavibelge import catalog --dry-run --stage=content --format=json > "$OUT/c7-dry0b.json" 2> /dev/null
D0B="$(json plan_digest < "$OUT/c7-dry0b.json")"
FXAPPLY=1 FXMAN="$CMAN" fx --user=mbadmin mavibelge import catalog --apply --stage=content --confirm="$D0B" --format=json > "$OUT/c7-apply0b.json" 2>&1
check "CLI content apply (zincir tamam ama terimler YOK): exit 1, plan_not_applicable" "$?:$(json error_code < "$OUT/c7-apply0b.json")" "1:plan_not_applicable"
FXMAN="$CMAN" fx eval 'wp_insert_term( "Haber", "mb_haber_turu", array( "slug" => "haber" ) ); wp_insert_term( "Duyuru", "mb_haber_turu", array( "slug" => "duyuru" ) ); echo "seeded";' > "$OUT/c7-seed.out" 2>&1
FXMAN="$CMAN" fx mavibelge import catalog --dry-run --stage=content --format=json > "$OUT/c7-dry1.json" 2> /dev/null
D1="$(json plan_digest < "$OUT/c7-dry1.json")"
check "CLI content dry-run (terimler VAR): 6 create, applicable=true, 64-hex digest" "$(json summary.operations.create < "$OUT/c7-dry1.json"):$(json summary.applicable < "$OUT/c7-dry1.json"):${#D1}" "6:true:64"
FXMAN="$CMAN0" fx mavibelge import catalog --dry-run --stage=all --format=json > "$OUT/c7-all.json" 2> /dev/null
check "CLI: içerik dizininde katalog dosyaları yok -> --stage=all yükleme hatasıyla reddedilir (içerik aşaması katalogdan bağımsız, tersi de geçerli)" "$?:$(json load_errors < "$OUT/c7-all.json" | grep -c 'manifest.json')" "1:1"
FXAPPLY=1 FXMAN="$CMAN" fx --user=mbadmin mavibelge import catalog --apply --stage=content --confirm="$D1" --format=json > "$OUT/c7-apply1.json" 2>&1
EC=$?
RUN7="$(json run_uid < "$OUT/c7-apply1.json")"
check "CLI content apply: doğru onayla exit 0, completed, 6 item" "$EC:$(json status < "$OUT/c7-apply1.json"):$(json committed_items < "$OUT/c7-apply1.json")" "0:completed:6"
FXMAN="$CMAN" fx mavibelge import catalog --dry-run --stage=content --format=json > "$OUT/c7-dry2.json" 2> /dev/null
check "CLI content dry-run sonrası: 6 unchanged, applicable" "$(json summary.operations.unchanged < "$OUT/c7-dry2.json"):$(json summary.applicable < "$OUT/c7-dry2.json")" "6:true"
FXMAN="$CMAN" fx --user=mbadmin mavibelge import rollback --run-id="$RUN7" --format=json > "$OUT/c7-rbp.json" 2>&1
RBD7="$(json rollback_digest < "$OUT/c7-rbp.json")"
check "CLI content rollback önizleme: 6 bekleyen item, engel yok, 64-hex digest" "$(json items_pending < "$OUT/c7-rbp.json"):$(json blockers < "$OUT/c7-rbp.json"):${#RBD7}" "6:[]:64"
FXAPPLY=1 FXMAN="$CMAN" fx --user=mbadmin mavibelge import rollback --run-id="$RUN7" --confirm="$RBD7" --format=json > "$OUT/c7-rb.json" 2>&1
check "CLI content rollback: doğru onayla exit 0, rolled_back, 6 item" "$?:$(json status < "$OUT/c7-rb.json"):$(json rolled_back_items < "$OUT/c7-rb.json")" "0:rolled_back:6"
FXMAN="$CMAN" fx mavibelge import catalog --dry-run --stage=content --format=json > "$OUT/c7-dry3.json" 2> /dev/null
check "CLI content: rollback sonrası dry-run yeniden 6 create, applicable, conflict=0" "$(json summary.operations.create < "$OUT/c7-dry3.json"):$(json summary.applicable < "$OUT/c7-dry3.json"):$(json summary.operations.conflict < "$OUT/c7-dry3.json")" "6:true:0"
echo "-- servis düzeyi gerçek WordPress kanıtı (taze fixture DB; betik iki tür terimini kendisi oluşturur)"
wpx eval-file /opt/mb-runtime/fixture-db.php drop
wpx eval-file /opt/mb-runtime/fixture-db.php clone
wpx eval-file /opt/mb-runtime/fixture-db.php manifest
FXAPPLY=1 fx --user=mbadmin eval-file /opt/mb-runtime/apply-cycle-test-3.php > "$OUT/apply-cycle-3.txt" 2>&1
echo "apply-cycle-3 exit=$? :: $(tail -1 "$OUT/apply-cycle-3.txt")"
grep -E '^FAIL' "$OUT/apply-cycle-3.txt" || true
echo "-- Faz 12c: dosya sistemi yan etkisi telafisi (taze fixture DB; logo dosyası/attachment/rollback hata noktaları)"
wpx eval-file /opt/mb-runtime/fixture-db.php drop
wpx eval-file /opt/mb-runtime/fixture-db.php clone
wpx eval-file /opt/mb-runtime/fixture-db.php manifest
FXAPPLY=1 fx --user=mbadmin eval-file /opt/mb-runtime/side-effect-test.php > "$OUT/side-effect.txt" 2>&1
echo "side-effect exit=$? :: $(tail -1 "$OUT/side-effect.txt")"
grep -E '^FAIL' "$OUT/side-effect.txt" || true
echo "-- Faz 12d: tema dahili bağlantıları iki permalink yapısında (taze fixture DB)"
wpx eval-file /opt/mb-runtime/fixture-db.php drop
wpx eval-file /opt/mb-runtime/fixture-db.php clone
FXAPPLY= fx --user=mbadmin eval-file /opt/mb-runtime/theme-urls-test.php > "$OUT/theme-urls.txt" 2>&1
echo "theme-urls exit=$? :: $(tail -1 "$OUT/theme-urls.txt")"
grep -E '^FAIL' "$OUT/theme-urls.txt" || true

echo "== 5) fixture veritabanı ve sahte manifest silinir"
wpx eval-file /opt/mb-runtime/fixture-db.php drop
wpx eval-file /opt/mb-runtime/fixture-db.php rmmanifest

echo "== 6) ana DB/dosya/uploads farkı ve debug log"
snapshot snapZ
echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
echo "debug.log: $(wc -l < "$OUT/debug.log") satır"
