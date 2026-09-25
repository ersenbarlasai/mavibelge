#!/usr/bin/env bash
# Faz 7/8 HTTP render + form testleri, MEVCUT izole ortamda (mbruntime6b2-*). Parola OKUMAZ. Konteyner/volume SİLMEZ.
# Ana `wp_` veritabanına YAZMAZ: silinebilir `mbfx_` klonu oluşturulur, HTTP istekleri geçici must-use eklentiyle
# (scripts/mu-fixture-http.php + /tmp/mbfx-http-on işareti) bu klona yönlendirilir; wp_mail GÖNDERİLMEZ (kısa devre + sayaç).
# Sonunda işaret, must-use eklenti, klon ve geçici dosyalar silinir; ana DB + dosyalar + uploads başlangıç anlık
# görüntüsüyle karşılaştırılır ve debug log sayılır.
# Kullanım: content-http.sh <çıktı-dizini (depo DIŞINDA)>
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
	docker exec "$WP" sh -c 'rm -f /tmp/mbfx-http-on /tmp/mbfx-mail.count /var/www/html/wp-content/mu-plugins/mu-fixture-http.php /tmp/mbfx-test-doc.pdf /tmp/mbfx-test-logo.png' >/dev/null 2>&1
	wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
}
trap cleanup EXIT

echo "== 1) başlangıç: debug log temizlenir, ana DB/dosya anlık görüntüsü A"
wpx eval 'wp_get_theme()->delete_pattern_cache(); wp_get_theme()->get_block_patterns();' >/dev/null 2>&1
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA

echo "== 2) fixture klonu + sahte içerik"
wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
wpx eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
fx --user=mbadmin eval-file /opt/mb-runtime/content-fixtures.php > "$OUT/fixtures.json" 2> "$OUT/fixtures.err"
echo "fixtures exit=$? :: hata satırı=$(wc -l < "$OUT/fixtures.err")"

echo "== 3) HTTP -> mbfx_ yönlendirmesi (geçici must-use eklenti + işaret dosyası)"
docker cp scripts/mu-fixture-http.php "$WP":/var/www/html/wp-content/mu-plugins/mu-fixture-http.php >/dev/null
docker exec "$WP" sh -c 'chmod 644 /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; : > /tmp/mbfx-mail.count; touch /tmp/mbfx-http-on; chmod 644 /tmp/mbfx-http-on; chmod 666 /tmp/mbfx-mail.count'
node content-forms-http-test.js "$(cygpath -m "$OUT")/fixtures.json" | tee "$OUT/content-forms-http.txt" | grep -E "^FAIL|testi geçti"
echo "http-test exit=${PIPESTATUS[0]}"
node seo-redirects-http-test.js "$(cygpath -m "$OUT")/fixtures.json" | tee "$OUT/seo-redirects-http.txt" | grep -E "^FAIL|testi geçti"
echo "seo-http-test exit=${PIPESTATUS[0]}"

# Faz 10 — performans/güvenlik HTTP testi (staging işareti SEO testi tarafından kaldırıldı). Oturum çerezleri mbfx_ klonuna
# yazılır (make-cookies.php; değerler ekrana basılmaz) ve yalnız $OUT/state içine kopyalanır.
mkdir -p "$OUT/state"
fx --user=mbadmin eval-file /opt/mb-runtime/make-cookies.php > "$OUT/cookies-roles.json" 2>/dev/null
for u in mbadmin mbeditor mbsubscriber; do
	docker exec -u www-data "$WP" cat "/tmp/mb-cookies-$u.json" > "$OUT/state/cookies-$u.json"
	docker exec -u www-data "$WP" rm -f "/tmp/mb-cookies-$u.json"
done
node perf-security-http-test.js "$(cygpath -m "$OUT")/state" | tee "$OUT/perf-security-http.txt" | grep -E "^FAIL|testi:"
echo "perf-security-http-test exit=${PIPESTATUS[0]}"
rm -rf "$OUT/state"
# Faz 10 — runtime (WP-CLI, mbfx_ klonu): audit zinciri, önbellek, N+1, oran sınırı, giriş, sağlık ekranı, uninstall. Klonun
# eklenti tablolarını siler (uninstall testi) — bu yüzden HTTP testlerinden SONRA çalışır.
fx --user=mbadmin eval-file /opt/mb-runtime/perf-security-runtime.php > "$OUT/perf-security-runtime.txt" 2>&1
echo "perf-security-runtime exit=$? :: $(grep -E '^FAIL' "$OUT/perf-security-runtime.txt" | wc -l) FAIL :: $(tail -1 "$OUT/perf-security-runtime.txt")"

echo "== 4) temizlik, ana DB/dosya/uploads farkı ve debug log"
cleanup
snapshot snapZ
echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
echo "debug.log: $(wc -l < "$OUT/debug.log") satır"
