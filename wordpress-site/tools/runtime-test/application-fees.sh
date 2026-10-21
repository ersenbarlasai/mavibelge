#!/usr/bin/env bash
# Faz 16 — online başvuru meslek seçenekleri ücret kayıtlarından: GERÇEK katalog (14/83/103) silinebilir mbfx_ fixture DB'de,
# 103 ücret yayında + aktif (canlıdaki kullanıcı yayını gibi); liste/ön seçim/dışlama/önbellek/gün sınırı/dönem/503 kayıt/gönderim
# yeniden doğrulaması/sahte kimlik/e-posta içeriği (test mailer'ı) + gerçek Chrome. Form yalnız bu klonda SENTETİK ayarla açılır.
# Parola OKUMAZ. Konteyner/volume SİLMEZ. Ana wp_ DB/dosya/uploads farkı 0 olmalıdır.
# Kullanım: application-fees.sh <çıktı-dizini>
set -uo pipefail
OUT="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../../.." && pwd)"
export MSYS_NO_PATHCONV=1
WP=mbruntime6b2-wp-1
mkdir -p "$OUT"
cd "$HERE"
wpx() { docker exec -u www-data "$WP" wp --path=/var/www/html "$@"; }
# Ana wp_ önekindeki yardımcılar (anlık görüntü, klon, silme) yalnız $wpdb kullanır; tema/eklenti YÜKLENMEDEN çalışır. Böylece
# WordPress çekirdeğinin tema desen önbelleği (30 dk site transient) test süresince ana DB'de okunup silinmez/yeniden yazılmaz.
wpdb() { docker exec -u www-data "$WP" wp --path=/var/www/html --skip-themes --skip-plugins "$@"; }
fx() { docker exec -u www-data "$WP" wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php "$@"; }
snapshot() {
	local d="$OUT/$1"
	mkdir -p "$d"
	wpdb eval-file /opt/mb-runtime/db-snapshot.php > "$d/db.txt"
	grep '^## TABLE' "$d/db.txt" > "$d/tables.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" ! -path "./wp-content/mu-plugins/mu-fixture-http.php" ! -path "./wp-content/plugins/mavibelge-core/*" ! -path "./wp-content/themes/mavibelge/*" ! -path "./wp-content/uploads/mbfx-catalog/*" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'find wp-content/uploads -printf "%p %s %T@\n" 2>/dev/null | sort || echo "uploads yok"' > "$d/uploads.txt"
}
cleanup() {
	docker exec "$WP" sh -c 'rm -f /tmp/mbfx-http-on /tmp/mbfx-mail.count /tmp/mbfx-mail-capture /tmp/mbfx-mail.last /tmp/mbfx-cache-on /tmp/mbfx-tz /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; rm -rf /var/www/html/wp-content/uploads/mbfx-catalog; if [ -f /tmp/mbfx-formtmp-new ]; then rm -rf /var/www/html/wp-content/uploads/mavibelge-forms-tmp; rm -f /tmp/mbfx-formtmp-new; fi; if [ -f /tmp/mbfx-uploads-mtime ]; then touch -d "$(cat /tmp/mbfx-uploads-mtime)" /var/www/html/wp-content/uploads; rm -f /tmp/mbfx-uploads-mtime; fi' >/dev/null 2>&1
	wpdb eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
}
trap cleanup EXIT
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA
wpdb eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
wpdb eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
docker exec "$WP" sh -c 'stat -c %y /var/www/html/wp-content/uploads > /tmp/mbfx-uploads-mtime; [ -d /var/www/html/wp-content/uploads/mavibelge-forms-tmp ] || touch /tmp/mbfx-formtmp-new; mkdir -p /var/www/html/wp-content/uploads/mbfx-catalog'
for f in "$REPO"/tanitim-site/assets/images/content/real-*.png; do docker cp "$(cygpath -m "$f")" "$WP":/var/www/html/wp-content/uploads/mbfx-catalog/ >/dev/null; done
docker exec "$WP" sh -c 'chown -R www-data:www-data /var/www/html/wp-content/uploads/mbfx-catalog'
fx option update permalink_structure "/index.php/%postname%/" >/dev/null
# 1) gerçek katalog içe aktarımı (qualification-render ile AYNI fixture), ardından 103 ücret yayında + aktif ve form sentetik açık
fx --user=mbadmin eval-file /opt/mb-runtime/qualification-render-fixtures.php > "$OUT/qual-fixtures.json" 2> "$OUT/qual-fixtures.err"
echo "katalog fixture exit=$? :: $(tr -d '\n' < "$OUT/qual-fixtures.json" | grep -o '"counts":{[^}]*}') hata=$(wc -l < "$OUT/qual-fixtures.err")"
fx --user=mbadmin eval-file /opt/mb-runtime/application-fee-fixtures.php setup > "$OUT/setup.json" 2> "$OUT/setup.err"
echo "başvuru ücret fixture exit=$? hata=$(wc -l < "$OUT/setup.err")"
docker cp scripts/mu-fixture-http.php "$WP":/var/www/html/wp-content/mu-plugins/mu-fixture-http.php >/dev/null
docker exec "$WP" sh -c 'chmod 644 /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; : > /tmp/mbfx-mail.count; touch /tmp/mbfx-http-on; chmod 644 /tmp/mbfx-http-on; chmod 666 /tmp/mbfx-mail.count'
# 2) liste + ön seçim + dışlama + önbellek + gün sınırı + dönem + 503 kayıt + gönderim + e-posta + gerçek Chrome
node application-fees-test.js "$(cygpath -m "$OUT")/setup.json" "$(cygpath -m "$OUT")/screens" | tee "$OUT/application-fees.txt" | grep -E "^FAIL|seçenekleri testi"
echo "application-fees exit=${PIPESTATUS[0]}"
fx --user=mbadmin eval-file /opt/mb-runtime/application-fee-fixtures.php close > /dev/null 2>> "$OUT/setup.err"
trap - EXIT
cleanup
snapshot snapZ
echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
echo "debug.log: $(wc -l < "$OUT/debug.log") satır"
[ "$(wc -l < "$OUT/debug.log")" = "0" ] || echo "FAIL  debug.log boş değil"
[ "$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l)" = "0" ] || echo "FAIL  ana DB değişti"
[ "$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)" = "0" ] || echo "FAIL  uploads değişti"
[ "$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l)" = "0" ] || echo "FAIL  dosyalar değişti"
grep -q "seçenekleri testi geçti" "$OUT/application-fees.txt" || echo "FAIL  Faz 16 başvuru testi geçmedi"
