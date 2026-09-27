#!/usr/bin/env bash
# Faz 6B4 — admin katalog aktarımı GERÇEK HTTP testi, MEVCUT izole ortamda (mbruntime6b2-*). Parola OKUMAZ. Konteyner/volume SİLMEZ.
# Ana `wp_` veritabanına YAZMAZ: HTTP istekleri geçici must-use eklentiyle (scripts/mu-fixture-http.php + /tmp/mbfx-http-on)
# silinebilir `mbfx_` klonuna yönlendirilir; kapı girdileri (HTTPS, wp-config sabitleri, ortam türü, üretim host'u) işaret
# dosyalarıyla benzetilir; GERÇEK e-posta/dış bağlantı yoktur. Sonunda işaretler, must-use eklenti, klon, sahte manifest, test
# kullanıcıları ve çerezler silinir; ana DB + dosyalar + uploads başlangıç anlık görüntüsüyle karşılaştırılır; debug log sayılır.
# Kullanım: admin-import-http.sh <çıktı-dizini (depo DIŞINDA)>
set -uo pipefail
OUT="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
export MSYS_NO_PATHCONV=1
WP=mbruntime6b2-wp-1
mkdir -p "$OUT"
cd "$HERE"
wpx() { docker exec -u www-data "$WP" wp --path=/var/www/html "$@"; }
fx() { docker exec -u www-data "$WP" wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php "$@"; }
check() { if [ "$2" = "$3" ]; then echo "PASS  $1"; else echo "FAIL  $1  [beklenen=$3 gerçek=$2]"; fi; }
snapshot() {
	local d="$OUT/$1"
	mkdir -p "$d"
	wpx eval-file /opt/mb-runtime/db-snapshot.php > "$d/db.txt"
	grep '^## TABLE' "$d/db.txt" > "$d/tables.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" ! -path "./wp-content/mu-plugins/mu-fixture-http.php" ! -path "./wp-content/plugins/mavibelge-core/*" ! -path "./wp-content/themes/mavibelge/*" ! -path "./data/*" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find wp-content/uploads -printf "%p %s %T@\n" 2>/dev/null | sort || echo "uploads yok"' > "$d/uploads.txt"
}
cleanup() {
	docker exec "$WP" sh -c 'rm -f /tmp/mbfx-http-on /tmp/mbfx-https /tmp/mbfx-env-staging /tmp/mbfx-env-production /tmp/mbfx-imp-apply /tmp/mbfx-imp-admin /tmp/mbfx-imp-prod /tmp/mbfx-imp-host /tmp/mbfx-imp-manifest /tmp/mbfx-mail.count /var/www/html/wp-content/mu-plugins/mu-fixture-http.php' >/dev/null 2>&1
	wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
	wpx eval-file /opt/mb-runtime/fixture-db.php rmmanifest >/dev/null 2>&1
}
trap cleanup EXIT

echo "== 0) test ortamı: wp-config ortam türü işaret dosyalarından okunur (idempotent; yalnız bu silinebilir konteyner)"
docker exec "$WP" sh -c "grep -q \"is_readable( '/tmp/mbfx-env-production' )\" /var/www/html/wp-config.php || sed -i \"s#define( 'WP_ENVIRONMENT_TYPE', 'local' );#define( 'WP_ENVIRONMENT_TYPE', is_readable( '/tmp/mbfx-env-production' ) ? 'production' : ( is_readable( '/tmp/mbfx-env-staging' ) ? 'staging' : 'local' ) );#\" /var/www/html/wp-config.php; grep -c \"mbfx-env-staging\" /var/www/html/wp-config.php"

echo "== 1) başlangıç: debug log temizlenir, ana DB/dosya anlık görüntüsü A"
wpx eval 'wp_get_theme()->delete_pattern_cache(); wp_get_theme()->get_block_patterns();' >/dev/null 2>&1
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA

echo "== 2) fixture klonu + sahte manifest + test kullanıcıları (mbcapa: yalnız manage_options, mbcapb: yalnız mb_manage_tariff_period)"
wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
wpx eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
wpx eval-file /opt/mb-runtime/fixture-db.php manifest >/dev/null
for u in mbcapa mbcapb; do
	fx user create "$u" "$u@example.invalid" --role=subscriber --user_pass="$(head -c 24 /dev/urandom | base64 | tr -d '/+=' )" >/dev/null 2>&1
done
fx user add-cap mbcapa manage_options >/dev/null 2>&1
fx user add-cap mbcapb mb_manage_tariff_period >/dev/null 2>&1
fx eval 'foreach ( array( "mbcapa", "mbcapb" ) as $l ) { $u = get_user_by( "login", $l ); echo $l, ":", (int) user_can( $u, "manage_options" ), (int) user_can( $u, "mb_manage_tariff_period" ), " "; }'
echo

echo "== 3) HTTP -> mbfx_ yönlendirmesi + oturum çerezleri (değerler basılmaz)"
docker cp scripts/mu-fixture-http.php "$WP":/var/www/html/wp-content/mu-plugins/mu-fixture-http.php >/dev/null
docker exec "$WP" sh -c 'chmod 644 /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; : > /tmp/mbfx-mail.count; touch /tmp/mbfx-http-on; chmod 644 /tmp/mbfx-http-on; chmod 666 /tmp/mbfx-mail.count'
mkdir -p "$OUT/state"
fx --user=mbadmin eval-file /opt/mb-runtime/make-cookies.php > "$OUT/cookies-roles.json" 2>/dev/null
echo "çerez kullanıcıları: $(cat "$OUT/cookies-roles.json" | tr -d '\n' | cut -c1-200)"
for u in mbadmin mbeditor mbsubscriber mbcapa mbcapb; do
	docker exec -u www-data "$WP" cat "/tmp/mb-cookies-$u.json" > "$OUT/state/cookies-$u.json"
	docker exec -u www-data "$WP" rm -f "/tmp/mb-cookies-$u.json"
done

echo "== 4) HTTP testi"
node admin-import-http-test.js "$(cygpath -m "$OUT")/state" | tee "$OUT/admin-import-http.txt" | grep -E "^FAIL|testi geçti"
HTTP_EXIT=${PIPESTATUS[0]}
echo "admin-import-http-test exit=$HTTP_EXIT"
check "admin HTTP testi çıkış kodu 0" "$HTTP_EXIT" "0"
rm -rf "$OUT/state"

echo "== 5) işaretler/klon/manifest silinir (trap), ana DB/dosya/uploads farkı ve debug log"
cleanup
snapshot snapZ
echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
check "ana wp_ DB/tablo/dosya/uploads farkı 0" "$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l):$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l):$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l):$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)" "0:0:0:0"
docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
check "debug.log 0 satır (fatal/warning/notice yok)" "$(wc -l < "$OUT/debug.log")" "0"
