#!/usr/bin/env bash
# Faz 12 — GERÇEK içe aktarılmış sayfalarla 41 rota render + güzel kalıcı bağlantı + robots/sitemap. Silinebilir mbfx_ fixture DB, HTTP
# yönlendirmeli (qa-render.sh ile aynı düzen). Güzel bağlantı için KONTEYNERDE geçici olarak mod_rewrite + .htaccess AllowOverride
# açılır ve SONUNDA geri alınır (a2disconf/a2dismod, .htaccess silinir). Parola OKUMAZ. Konteyner/volume SİLMEZ.
# Kullanım: pages-render.sh <çıktı-dizini (depo DIŞINDA)> [all|up|down]
#   up   : kur + test, AYAKTA bırak (bağımsız tarayıcı QA için; güzel yapı /%postname%/ geri yüklenir); bitince: <aynı dizin> down
#   down : temizle + A->Z farkı
set -uo pipefail
OUT="$1"
MODE="${2:-all}"
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
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" ! -path "./wp-content/mu-plugins/mu-fixture-http.php" ! -path "./wp-content/plugins/mavibelge-core/*" ! -path "./wp-content/themes/mavibelge/*" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'find wp-content/uploads -printf "%p %s %T@\n" 2>/dev/null | sort || echo "uploads yok"' > "$d/uploads.txt"
}
cleanup() {
	docker exec "$WP" sh -c 'rm -f /tmp/mbfx-http-on /tmp/mbfx-mail.count /tmp/mbfx-staging /tmp/mbfx-uploads/mbfx/mbfx-page-parity-hero.png /var/www/html/wp-content/mu-plugins/mu-fixture-http.php /var/www/html/.htaccess; a2disconf mbfx-rewrite >/dev/null 2>&1; rm -f /etc/apache2/conf-available/mbfx-rewrite.conf; a2dismod rewrite >/dev/null 2>&1; apache2ctl graceful >/dev/null 2>&1' >/dev/null 2>&1
	wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
}
finish() {
	cleanup
	snapshot snapZ
	echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
}
if [ "$MODE" = "down" ]; then
	finish
	exit 0
fi
[ "$MODE" = "up" ] || trap cleanup EXIT
wpx eval 'wp_get_theme()->delete_pattern_cache(); wp_get_theme()->get_block_patterns();' >/dev/null 2>&1
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA
wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
wpx eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
# geçici mod_rewrite + yalnız fixture görseli için izole uploads Alias'ı (yalnız bu koşu)
docker exec "$WP" sh -c 'a2enmod rewrite >/dev/null 2>&1; printf "Alias /wp-content/uploads/mbfx/ /tmp/mbfx-uploads/mbfx/\n<Directory /tmp/mbfx-uploads/mbfx/>\n\tRequire all granted\n</Directory>\n<Directory /var/www/html/>\n\tAllowOverride All\n</Directory>\n" > /etc/apache2/conf-available/mbfx-rewrite.conf; a2enconf mbfx-rewrite >/dev/null 2>&1; apache2ctl graceful >/dev/null 2>&1'
docker exec -u www-data "$WP" sh -c 'printf "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%%{HTTP:Authorization}]\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %%{REQUEST_FILENAME} !-f\nRewriteCond %%{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress\n" > /var/www/html/.htaccess'
docker exec -u www-data -e MB_QA_SKIP_PAGES=1 "$WP" wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php --user=mbadmin eval-file /opt/mb-runtime/qa-fixtures.php > "$OUT/qa-fixtures.json" 2> "$OUT/qa-fixtures.err"
echo "qa-fixtures (sayfasız) exit=$? :: hata=$(wc -l < "$OUT/qa-fixtures.err")"
fx --user=mbadmin eval-file /opt/mb-runtime/pages-render-fixtures.php import > "$OUT/pages-import.json" 2> "$OUT/pages-import.err"
echo "pages import+yayın exit=$? :: $(tr -d '\n' < "$OUT/pages-import.json" | cut -c1-120) hata=$(wc -l < "$OUT/pages-import.err")"
docker cp scripts/mu-fixture-http.php "$WP":/var/www/html/wp-content/mu-plugins/mu-fixture-http.php >/dev/null
docker exec "$WP" sh -c 'chmod 644 /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; : > /tmp/mbfx-mail.count; touch /tmp/mbfx-http-on; chmod 644 /tmp/mbfx-http-on; chmod 666 /tmp/mbfx-mail.count'
node pages-render-test.js phase1 "$(cygpath -m "$OUT")/qa-fixtures.json" "$(cygpath -m "$OUT")/pages-import.json" | tee "$OUT/pages-render-1.txt" | grep -E "^FAIL|testi geçti"
echo "pages-render phase1 exit=${PIPESTATUS[0]}"
fx --user=mbadmin eval-file /opt/mb-runtime/pages-render-fixtures.php publish-held > "$OUT/pages-import2.json" 2> "$OUT/pages-import2.err"
echo "test düzeneği: bekletilenler izole DB'de yayınlandı exit=$? :: $(tr -d '\n' < "$OUT/pages-import2.json" | cut -c1-60)"
node -e 'const fs=require("fs");const a=JSON.parse(fs.readFileSync(process.argv[1],"utf8").replace(/^[^{]*/,""));const b=JSON.parse(fs.readFileSync(process.argv[2],"utf8").replace(/^[^{]*/,""));fs.writeFileSync(process.argv[3],JSON.stringify({published:b.published,draft:b.draft,permalink:a.permalink}))' "$(cygpath -m "$OUT")/pages-import.json" "$(cygpath -m "$OUT")/pages-import2.json" "$(cygpath -m "$OUT")/pages-import-final.json"
node pages-render-test.js phase2 "$(cygpath -m "$OUT")/qa-fixtures.json" "$(cygpath -m "$OUT")/pages-import-final.json" | tee "$OUT/pages-render-2.txt" | grep -E "^FAIL|testi geçti"
echo "pages-render phase2 exit=${PIPESTATUS[0]}"
# Faz 13: referans sayfa aileleri — gerçek sayfa içeriği + sentetik haber/duyuru/doküman; önce güzel (/%postname%/), sonra
# /index.php/%postname%/ yapısında HTTP; gerçek headless Chrome (güzel yapı). Yalnız bu klonda.
fx --user=mbadmin eval-file /opt/mb-runtime/page-parity-fixtures.php content > "$OUT/parity-fixtures.json" 2> "$OUT/parity-fixtures.err"
echo "parite fixture exit=$? hata=$(wc -l < "$OUT/parity-fixtures.err")"
node page-parity-test.js http pretty "$(cygpath -m "$OUT")/screens-parity" | tee "$OUT/page-parity-http-pretty.txt" | grep -E "^FAIL|parite testi"
echo "page-parity http pretty exit=${PIPESTATUS[0]}"
node page-parity-test.js browser pretty "$(cygpath -m "$OUT")/screens-parity" | tee "$OUT/page-parity-browser.txt" | grep -E "^FAIL|parite testi"
echo "page-parity browser exit=${PIPESTATUS[0]}"
fx --user=mbadmin eval-file /opt/mb-runtime/page-parity-fixtures.php permalink /index.php/%postname%/ > /dev/null 2>> "$OUT/parity-fixtures.err"
node page-parity-test.js http index "$(cygpath -m "$OUT")/screens-parity" | tee "$OUT/page-parity-http-index.txt" | grep -E "^FAIL|parite testi"
echo "page-parity http index exit=${PIPESTATUS[0]}"
if [ "$MODE" = "up" ]; then
	fx --user=mbadmin eval-file /opt/mb-runtime/page-parity-fixtures.php permalink /%postname%/ > /dev/null 2>> "$OUT/parity-fixtures.err"
	echo "AYAKTA (güzel kalıcı bağlantı): bitince: $0 $OUT down"
	exit 0
fi
cleanup
snapshot snapZ
echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
echo "debug.log: $(wc -l < "$OUT/debug.log") satır"
[ "$(wc -l < "$OUT/debug.log")" = "0" ] || echo "FAIL  debug.log boş değil"
[ "$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l)" = "0" ] || echo "FAIL  ana DB değişti"
[ "$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)" = "0" ] || echo "FAIL  ana uploads değişti"
