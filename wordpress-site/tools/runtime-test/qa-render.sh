#!/usr/bin/env bash
# Faz 11 render QA — silinebilir mbfx_ fixture DB, HTTP yönlendirmeli (content-http.sh ile aynı düzen). Parola OKUMAZ.
# Kullanım: qa-render.sh <çıktı-dizini (depo DIŞINDA)>
set -uo pipefail
OUT="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
export MSYS_NO_PATHCONV=1
WP=mbruntime6b2-wp-1
mkdir -p "$OUT"
cd "$HERE"
wpx() { docker exec -u www-data "$WP" wp --path=/var/www/html "$@"; }
fx() { docker exec -u www-data "$WP" wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php "$@"; }
snapshot() {
	local d="$OUT/$1"
	mkdir -p "$d"
	wpx eval-file /opt/mb-runtime/db-snapshot.php > "$d/db.txt"
	grep '^## TABLE' "$d/db.txt" > "$d/tables.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" ! -path "./wp-content/mu-plugins/mu-fixture-http.php" ! -path "./wp-content/plugins/mavibelge-core/*" ! -path "./wp-content/themes/mavibelge/*" ! -path "./data/*" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find wp-content/uploads -printf "%p %s %T@\n" 2>/dev/null | sort || echo "uploads yok"' > "$d/uploads.txt"
}
cleanup() {
	docker exec "$WP" sh -c 'rm -f /tmp/mbfx-http-on /tmp/mbfx-mail.count /tmp/mbfx-staging /var/www/html/wp-content/mu-plugins/mu-fixture-http.php' >/dev/null 2>&1
	wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
}
trap cleanup EXIT
wpx eval 'wp_get_theme()->delete_pattern_cache(); wp_get_theme()->get_block_patterns();' >/dev/null 2>&1
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA
wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
wpx eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
fx --user=mbadmin eval-file /opt/mb-runtime/qa-fixtures.php > "$OUT/qa-fixtures.json" 2> "$OUT/qa-fixtures.err"
echo "qa-fixtures exit=$? :: $(tr -d '\n' < "$OUT/qa-fixtures.json" | cut -c1-160) hata=$(wc -l < "$OUT/qa-fixtures.err")"
docker cp scripts/mu-fixture-http.php "$WP":/var/www/html/wp-content/mu-plugins/mu-fixture-http.php >/dev/null
docker exec "$WP" sh -c 'chmod 644 /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; : > /tmp/mbfx-mail.count; touch /tmp/mbfx-http-on; chmod 644 /tmp/mbfx-http-on; chmod 666 /tmp/mbfx-mail.count'
node qa-render-test.js "$(cygpath -m "$OUT")/qa-fixtures.json" | tee "$OUT/qa-render.txt" | grep -E "^FAIL|kontrolü geçti"
echo "qa-render exit=${PIPESTATUS[0]}"
cleanup
snapshot snapZ
echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
echo "debug.log: $(wc -l < "$OUT/debug.log") satır"
