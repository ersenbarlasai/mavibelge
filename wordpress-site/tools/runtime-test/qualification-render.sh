#!/usr/bin/env bash
# Faz 12e — yeterlilik liste/detay ekranları (+ kapanış: başlık geometrisi, gerçek Chrome) GERÇEK katalog verisiyle (83 yeterlilik, 14 sektör, 103 ücret; 2 kontrollü aktif ücret)
# silinebilir mbfx_ fixture DB'de render edilir ve HTTP sözleşmesi sınanır. Kalıcı bağlantı yapısı staging ile aynıdır
# (/index.php/%postname%/). Sektör görselleri için referans görseller GEÇİCİ olarak wp-content/uploads/mbfx-catalog/ altına
# kopyalanır ve sonunda silinir (uploads dizininin zaman damgası geri yüklenir). Parola OKUMAZ. Konteyner/volume SİLMEZ.
# Kullanım:
#   qualification-render.sh <çıktı-dizini>          kur + test + temizle (kalite kapısı)
#   qualification-render.sh <çıktı-dizini> up       kur + test, AYAKTA bırak (tarayıcı ekran görüntüsü için)
#   qualification-render.sh <çıktı-dizini> down     temizle + A->Z farkı
set -uo pipefail
OUT="$1"
MODE="${2:-all}"
HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../../.." && pwd)"
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
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" ! -path "./wp-content/mu-plugins/mu-fixture-http.php" ! -path "./wp-content/plugins/mavibelge-core/*" ! -path "./wp-content/themes/mavibelge/*" ! -path "./wp-content/uploads/mbfx-catalog/*" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'find wp-content/uploads -printf "%p %s %T@\n" 2>/dev/null | sort || echo "uploads yok"' > "$d/uploads.txt"
}
cleanup() {
	docker exec "$WP" sh -c 'rm -f /tmp/mbfx-http-on /tmp/mbfx-mail.count /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; rm -rf /var/www/html/wp-content/uploads/mbfx-catalog; if [ -f /tmp/mbfx-formtmp-new ]; then rm -rf /var/www/html/wp-content/uploads/mavibelge-forms-tmp; rm -f /tmp/mbfx-formtmp-new; fi; if [ -f /tmp/mbfx-uploads-mtime ]; then touch -d "$(cat /tmp/mbfx-uploads-mtime)" /var/www/html/wp-content/uploads; rm -f /tmp/mbfx-uploads-mtime; fi' >/dev/null 2>&1
	wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
}
down() {
	cleanup
	snapshot snapZ
	echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
	docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
	echo "debug.log: $(wc -l < "$OUT/debug.log") satır"
	[ "$(wc -l < "$OUT/debug.log")" = "0" ] || echo "FAIL  debug.log boş değil"
	[ "$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l)" = "0" ] || echo "FAIL  ana DB değişti"
	[ "$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)" = "0" ] || echo "FAIL  uploads değişti"
	[ "$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l)" = "0" ] || echo "FAIL  dosyalar değişti"
}
if [ "$MODE" = "down" ]; then
	down
	exit 0
fi
[ "$MODE" = "all" ] && trap cleanup EXIT
wpx eval 'wp_get_theme()->delete_pattern_cache(); wp_get_theme()->get_block_patterns();' >/dev/null 2>&1
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA
wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
wpx eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
# referans sektör görselleri (tanitim-site YALNIZ okunur) -> geçici uploads alt dizini
docker exec "$WP" sh -c 'stat -c %y /var/www/html/wp-content/uploads > /tmp/mbfx-uploads-mtime; [ -d /var/www/html/wp-content/uploads/mavibelge-forms-tmp ] || touch /tmp/mbfx-formtmp-new; mkdir -p /var/www/html/wp-content/uploads/mbfx-catalog'
for f in "$REPO"/tanitim-site/assets/images/content/real-*.png; do docker cp "$(cygpath -m "$f")" "$WP":/var/www/html/wp-content/uploads/mbfx-catalog/ >/dev/null; done
docker exec "$WP" sh -c 'chown -R www-data:www-data /var/www/html/wp-content/uploads/mbfx-catalog'
fx option update permalink_structure "/index.php/%postname%/" >/dev/null
fx --user=mbadmin eval-file /opt/mb-runtime/qualification-render-fixtures.php > "$OUT/qual-fixtures.json" 2> "$OUT/qual-fixtures.err"
echo "katalog fixture exit=$? :: $(tr -d '\n' < "$OUT/qual-fixtures.json" | grep -o '"counts":{[^}]*}') hata=$(wc -l < "$OUT/qual-fixtures.err")"
docker cp scripts/mu-fixture-http.php "$WP":/var/www/html/wp-content/mu-plugins/mu-fixture-http.php >/dev/null
docker exec "$WP" sh -c 'chmod 644 /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; : > /tmp/mbfx-mail.count; touch /tmp/mbfx-http-on; chmod 644 /tmp/mbfx-http-on; chmod 666 /tmp/mbfx-mail.count'
node qualification-render-test.js "$(cygpath -m "$OUT")/qual-fixtures.json" | tee "$OUT/qualification-render.txt" | grep -E "^FAIL|testi geçti"
echo "qualification-render exit=${PIPESTATUS[0]}"
# Faz 12e kapanış: GERÇEK headless Chrome (CDP) başlık geometrisi + menü etkileşimi; ekran görüntüleri $OUT/screens
node header-geometry-test.js "$(cygpath -m "$OUT")/qual-fixtures.json" "$(cygpath -m "$OUT")/screens" | tee "$OUT/header-geometry.txt" | grep -E "^FAIL|testi geçti"
echo "header-geometry exit=${PIPESTATUS[0]}"
# Faz 12f: online başvuru — meslek ön seçimi + form kapısı (kapalı, sonra YALNIZ bu klonda sentetik açık); gerçek Chrome
fx --user=mbadmin eval-file /opt/mb-runtime/application-fixtures.php prepare > "$OUT/app-fixtures.json" 2> "$OUT/app-fixtures.err"
echo "başvuru fixture exit=$? hata=$(wc -l < "$OUT/app-fixtures.err")"
node application-form-test.js closed "$(cygpath -m "$OUT")/qual-fixtures.json" "$(cygpath -m "$OUT")/app-fixtures.json" "$(cygpath -m "$OUT")/screens-app" | tee "$OUT/application-closed.txt" | grep -E "^FAIL|online başvuru testi"
echo "application-closed exit=${PIPESTATUS[0]}"
fx --user=mbadmin eval-file /opt/mb-runtime/application-fixtures.php open > "$OUT/app-open.json" 2>> "$OUT/app-fixtures.err"
node application-form-test.js open "$(cygpath -m "$OUT")/qual-fixtures.json" "$(cygpath -m "$OUT")/app-open.json" "$(cygpath -m "$OUT")/screens-app" | tee "$OUT/application-open.txt" | grep -E "^FAIL|online başvuru testi"
echo "application-open exit=${PIPESTATUS[0]}"
fx --user=mbadmin eval-file /opt/mb-runtime/application-fixtures.php close > /dev/null 2>> "$OUT/app-fixtures.err"
# Faz 12g: İletişim sayfası (kapalı/yedek lokasyon, mb_lokasyon DTO, sentetik açık) + beş formun güvenlik matrisi; gerçek Chrome.
# Form ayarları YALNIZ bu klonda sentetik açılır, test sonunda kapatılır; yükleme geçici dizini yoksa temizlikte kaldırılır.
node contact-page-test.js "$(cygpath -m "$OUT")/qual-fixtures.json" "$(cygpath -m "$OUT")/screens-contact" | tee "$OUT/contact-page.txt" | grep -E "^FAIL|beş form testi geçti"
echo "contact-page exit=${PIPESTATUS[0]}"
if [ "$MODE" = "up" ]; then
	echo "AYAKTA: tarayıcı doğrulaması için hazır; bitince: $0 $OUT down"
	exit 0
fi
trap - EXIT
down
