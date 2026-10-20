#!/usr/bin/env bash
# Faz 15 — ücret kataloğu: GERÇEK katalog (14/83/103) silinebilir mbfx_ fixture DB'de, canlı ile AYNI başlangıçla (yalnız 2 QA ücreti
# elle aktif) kurulur; toplu ücret yayını (gerçek Fee_Publisher, transaction, audit) -> Sınav Ücretleri'nin tam taranıp kaynakla
# karşılaştırılması -> gerçek Chrome'da yönetim ekranı düzenleme/yetki/mobil testleri -> ikinci aktarım önizlemesi (conflict),
# araçla geri alma ve eski hash geçişi. Karar dosyası 103 yayın / 0 bekletme (2026-10-20). Parola OKUMAZ (oturum çerezleri klonda üretilir, ekrana basılmaz). Konteyner/volume SİLMEZ.
# Kullanım: fee-catalog.sh <çıktı-dizini>
set -uo pipefail
OUT="$1"
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
	docker exec "$WP" sh -c 'rm -rf /tmp/mbfx-feepkg /tmp/mbfx-whatif /tmp/mbfx-feemiss-*; rm -f /tmp/mbfx-http-on /tmp/mbfx-mail.count /tmp/mbfx-fee-cookies-*.json /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; rm -rf /var/www/html/wp-content/uploads/mbfx-catalog; if [ -f /tmp/mbfx-formtmp-new ]; then rm -rf /var/www/html/wp-content/uploads/mavibelge-forms-tmp; rm -f /tmp/mbfx-formtmp-new; fi; if [ -f /tmp/mbfx-uploads-mtime ]; then touch -d "$(cat /tmp/mbfx-uploads-mtime)" /var/www/html/wp-content/uploads; rm -f /tmp/mbfx-uploads-mtime; fi' >/dev/null 2>&1
	wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
	rm -rf "$OUT/state"
}
trap cleanup EXIT
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA
wpx eval-file /opt/mb-runtime/fixture-db.php drop >/dev/null 2>&1
wpx eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
docker exec "$WP" sh -c 'stat -c %y /var/www/html/wp-content/uploads > /tmp/mbfx-uploads-mtime; [ -d /var/www/html/wp-content/uploads/mavibelge-forms-tmp ] || touch /tmp/mbfx-formtmp-new; mkdir -p /var/www/html/wp-content/uploads/mbfx-catalog'
for f in "$REPO"/tanitim-site/assets/images/content/real-*.png; do docker cp "$(cygpath -m "$f")" "$WP":/var/www/html/wp-content/uploads/mbfx-catalog/ >/dev/null; done
docker exec "$WP" sh -c 'chown -R www-data:www-data /var/www/html/wp-content/uploads/mbfx-catalog'
fx option update permalink_structure "/index.php/%postname%/" >/dev/null
# 1) canlı ile aynı başlangıç: gerçek katalog içe aktarımı + yalnız 2 QA ücreti elle aktif (qualification-render ile AYNI fixture)
fx --user=mbadmin eval-file /opt/mb-runtime/qualification-render-fixtures.php > "$OUT/qual-fixtures.json" 2> "$OUT/qual-fixtures.err"
echo "katalog fixture exit=$? :: $(tr -d '\n' < "$OUT/qual-fixtures.json" | grep -o '"counts":{[^}]*}') hata=$(wc -l < "$OUT/qual-fixtures.err")"
# 1b) ücret veri dağıtım paketi: dist-packages'taki GÜNCEL zip host'ta açılır, konteynerde /tmp/mbfx-feepkg'e kopyalanır (fixture önizlemeyi
#     bu dizinden de alır ve gerçek data/content ile karşılaştırır)
PKG_BASE="$(cd "$REPO/wordpress-site" && node -e "process.stdout.write(require('./tools/package/build-fee-data-package').build().base)")"
rm -rf "$OUT/feepkg" && mkdir -p "$OUT/feepkg"
node lib/unzip.js "$(cygpath -m "$REPO/wordpress-site/dist-packages/$PKG_BASE.zip")" "$(cygpath -m "$OUT/feepkg")"
docker cp "$(cygpath -m "$OUT/feepkg/mavibelge-data")" "$WP":/tmp/mbfx-feepkg >/dev/null
docker exec "$WP" sh -c 'chown -R www-data:www-data /tmp/mbfx-feepkg'
echo "veri paketi: $PKG_BASE.zip"
# 2) toplu ücret yayını (mevcut admin servisi -> gerçek Fee_Publisher)
fx --user=mbadmin eval-file /opt/mb-runtime/fee-catalog-fixtures.php publish > "$OUT/fee-publish.json" 2> "$OUT/fee-publish.err"
echo "ücret yayını fixture exit=$? hata=$(wc -l < "$OUT/fee-publish.err")"
mkdir -p "$OUT/state"
for u in mbadmin mbpriceed mbsubscriber; do
	docker exec -u www-data "$WP" cat "/tmp/mbfx-fee-cookies-$u.json" > "$OUT/state/cookies-$u.json"
	docker exec -u www-data "$WP" rm -f "/tmp/mbfx-fee-cookies-$u.json"
done
docker cp scripts/mu-fixture-http.php "$WP":/var/www/html/wp-content/mu-plugins/mu-fixture-http.php >/dev/null
docker exec "$WP" sh -c 'chmod 644 /var/www/html/wp-content/mu-plugins/mu-fixture-http.php; : > /tmp/mbfx-mail.count; touch /tmp/mbfx-http-on; chmod 644 /tmp/mbfx-http-on; chmod 666 /tmp/mbfx-mail.count'
# 3) ziyaretçi taraması + gerçek Chrome yönetim/mobil testleri
node fee-catalog-test.js publish "$(cygpath -m "$OUT")/fee-publish.json" "$(cygpath -m "$OUT")/state" "$(cygpath -m "$OUT")/screens" | tee "$OUT/fee-catalog-publish.txt" | grep -E "^FAIL|runtime testi"
echo "fee-catalog publish exit=${PIPESTATUS[0]}"
# 4) düzenleme sonrası: aktarım önizlemesi (conflict), araçla geri alma, eski hash geçişi
fx --user=mbadmin eval-file /opt/mb-runtime/fee-catalog-fixtures.php after > "$OUT/fee-after.json" 2> "$OUT/fee-after.err"
echo "after fixture exit=$? hata=$(wc -l < "$OUT/fee-after.err")"
node fee-catalog-test.js after "$(cygpath -m "$OUT")/fee-after.json" "$(cygpath -m "$OUT")/fee-publish.json" "$(cygpath -m "$OUT")/screens/edited.json" | tee "$OUT/fee-catalog-after.txt" | grep -E "^FAIL|runtime testi"
echo "fee-catalog after exit=${PIPESTATUS[0]}"
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
