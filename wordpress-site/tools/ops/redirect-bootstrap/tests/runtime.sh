#!/usr/bin/env bash
# Geçici yönlendirme loader'ı — gerçek WordPress 6.9.9 + PHP 7.3.33 uçtan uca testi (MEVCUT mbruntime6b2-* ortamı).
# Parola OKUMAZ. Konteyner/volume SİLMEZ. Ana `wp_` veritabanına YAZMAZ: silinebilir `mbfx_` klonu kullanılır ve HTTP
# istekleri runtime-test/scripts/mu-fixture-http.php + /tmp/mbfx-http-on işaretiyle klona yönlendirilir.
# Canlıdaki gibi temiz kalıcı bağlantı (/%postname%/) için yalnız test süresince Apache mod_rewrite + web kökü DIŞINDA
# bir conf açılır; sonunda kapatılır. Loader + manifest geçici olarak wp-content/mu-plugins/ içine kopyalanır ve silinir.
# Sonunda ana DB/dosya/uploads anlık görüntüsü başlangıçla karşılaştırılır.
# Kullanım: runtime.sh <çıktı-dizini (depo DIŞINDA)>
set -uo pipefail
OUT="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
RT="$(cd "$HERE/../../../runtime-test" && pwd)"
SITE="$(cd "$HERE/../../../.." && pwd)"
export MSYS_NO_PATHCONV=1
WP=mbruntime6b2-wp-1
MU=/var/www/html/wp-content/mu-plugins
mkdir -p "$OUT"
hp() { cygpath -m "$1" 2>/dev/null || echo "$1"; }
wpx() { docker exec -u www-data "$WP" wp --path=/var/www/html "$@"; }
fx() { docker exec -u www-data "$WP" wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php "$@"; }
snapshot() {
	local d="$OUT/$1"
	mkdir -p "$d"
	wpx eval-file /opt/mb-runtime/db-snapshot.php > "$d/db.txt"
	grep '^## TABLE' "$d/db.txt" > "$d/tables.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" ! -path "./wp-content/plugins/mavibelge-core/*" ! -path "./wp-content/themes/mavibelge/*" ! -path "./data/*" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find wp-content/uploads -printf "%p %s %T@\n" 2>/dev/null | sort || echo "uploads yok"' > "$d/uploads.txt"
}
cleanup() {
	docker exec "$WP" sh -c "rm -f /tmp/mbfx-http-on /tmp/mbfx-mail.count $MU/mu-fixture-http.php $MU/mavibelge-redirect-bootstrap.php $MU/redirects.manifest.json /tmp/mbrb-runtime-wp.php /tmp/mb-cookies-*.json /etc/apache2/conf-enabled/zz-mbrb-rewrite.conf; a2dismod -q rewrite >/dev/null 2>&1; apache2ctl -k graceful >/dev/null 2>&1" >/dev/null 2>&1
	wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
	rm -rf "$OUT/state"
}
trap cleanup EXIT

echo "== 1) başlangıç anlık görüntüsü A (ana wp_)"
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA

echo "== 2) mbfx_ klonu, hedef fixture'ları, temiz kalıcı bağlantı"
wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
wpx eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
docker cp "$(hp "$HERE/runtime-wp.php")" "$WP":/tmp/mbrb-runtime-wp.php >/dev/null
docker exec "$WP" chmod 644 /tmp/mbrb-runtime-wp.php
fx --user=mbadmin eval-file /tmp/mbrb-runtime-wp.php fixture | tee "$OUT/fixture.json"

echo "== 3) loader + manifest -> mu-plugins (ZIP içeriğiyle aynı iki dosya)"
docker cp "$(hp "$HERE/../mavibelge-redirect-bootstrap.php")" "$WP":"$MU/mavibelge-redirect-bootstrap.php" >/dev/null
docker cp "$(hp "$SITE/data/redirects/redirects.manifest.json")" "$WP":"$MU/redirects.manifest.json" >/dev/null
docker exec "$WP" sh -c "chmod 644 $MU/mavibelge-redirect-bootstrap.php $MU/redirects.manifest.json; sha256sum $MU/redirects.manifest.json | cut -c1-64; php -l $MU/mavibelge-redirect-bootstrap.php"

echo "== 4) WP-CLI gerçek WordPress testi (mbfx_)"
fx --user=mbadmin eval-file /tmp/mbrb-runtime-wp.php cli > "$OUT/runtime-wp.txt" 2>&1
echo "runtime-wp exit=$? :: $(grep -c '^FAIL' "$OUT/runtime-wp.txt") FAIL :: $(tail -1 "$OUT/runtime-wp.txt")"
grep '^FAIL' "$OUT/runtime-wp.txt"

echo "== 5) HTTP (mbfx_ yönlendirmesi + geçici mod_rewrite)"
docker cp "$(hp "$RT/scripts/mu-fixture-http.php")" "$WP":"$MU/mu-fixture-http.php" >/dev/null
printf '%s\n' '<Directory /var/www/html>' 'RewriteEngine On' 'RewriteBase /' 'RewriteRule ^index\.php$ - [L]' 'RewriteCond %{REQUEST_FILENAME} !-f' 'RewriteCond %{REQUEST_FILENAME} !-d' 'RewriteRule . /index.php [L]' '</Directory>' > "$OUT/zz-mbrb-rewrite.conf"
docker cp "$(hp "$OUT/zz-mbrb-rewrite.conf")" "$WP":/etc/apache2/conf-enabled/zz-mbrb-rewrite.conf >/dev/null
docker exec "$WP" sh -c "chmod 644 $MU/mu-fixture-http.php /etc/apache2/conf-enabled/zz-mbrb-rewrite.conf; : > /tmp/mbfx-mail.count; chmod 666 /tmp/mbfx-mail.count; touch /tmp/mbfx-http-on; chmod 644 /tmp/mbfx-http-on; a2enmod -q rewrite >/dev/null; apache2ctl -k graceful"
sleep 2
mkdir -p "$OUT/state"
fx --user=mbadmin eval-file /opt/mb-runtime/make-cookies.php > /dev/null 2>&1
for u in mbadmin mbsubscriber; do
	docker exec -u www-data "$WP" cat "/tmp/mb-cookies-$u.json" > "$OUT/state/cookies-$u.json"
done
node "$(hp "$HERE/runtime-http.js")" "$(cygpath -m "$OUT/state" 2>/dev/null || echo "$OUT/state")" "$(cygpath -m "$SITE/data/redirects/redirects.manifest.json" 2>/dev/null || echo "$SITE/data/redirects/redirects.manifest.json")" > "$OUT/runtime-http.txt" 2>&1
echo "runtime-http exit=$? :: $(grep -c '^FAIL' "$OUT/runtime-http.txt") FAIL :: $(tail -1 "$OUT/runtime-http.txt")"
grep '^FAIL' "$OUT/runtime-http.txt"
echo "mail sayacı (0 beklenir): $(docker exec "$WP" sh -c 'wc -l < /tmp/mbfx-mail.count')"

echo "== 6) temizlik, ana DB/dosya/uploads farkı ve debug log"
cleanup
snapshot snapZ
echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
echo "debug.log: $(wc -l < "$OUT/debug.log") satır"
echo "mu-plugins kalan: $(docker exec "$WP" ls /var/www/html/wp-content/mu-plugins | tr '\n' ' ')"
echo "rewrite modülü: $(docker exec "$WP" sh -c 'apache2ctl -M 2>/dev/null | grep -c rewrite')"
