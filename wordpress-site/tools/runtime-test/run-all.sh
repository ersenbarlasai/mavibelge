#!/usr/bin/env bash
# Faz 6B2 runtime doğrulaması — host sürücüsü (tamamı yerel ve izole).
# Kullanım: run-all.sh <env-dosyası (depo DIŞINDA)> <çıktı-dizini (depo DIŞINDA)>
# Ortamı SIFIRDAN kurar (docker compose down -v), fixture'ları yazar, sonra
# CLI ve admin dry-run'ı çalıştırıp önce/sonra anlık görüntüleri karşılaştırır.
set -uo pipefail
ENV_FILE="$1"
OUT="$2"
HERE="$(cd "$(dirname "$0")" && pwd)"
export MSYS_NO_PATHCONV=1
mkdir -p "$OUT"
set -a; . "$ENV_FILE"; set +a
dc() { docker compose -p mbruntime6b2 --env-file "$ENV_FILE" "$@"; }
cd "$HERE"

echo "== 0) sıfırdan ortam"
dc down -v >/dev/null 2>&1
dc up -d --build >/dev/null 2>&1 || { echo "compose up başarısız"; exit 2; }
dc exec -T wp sh -c 'chown www-data:www-data /var/www/html/wp-content /var/www/html/wp-content/plugins /var/www/html/wp-content/themes'

echo "== 1) kurulum + aktivasyon + fixture (test hazırlığı)"
dc exec -T -u www-data -e ADMPW="$WP_ADMIN_PASSWORD" -e LOWPW="$WP_LOW_PASSWORD" wp sh /opt/mb-runtime/setup.sh > "$OUT/setup.log" 2>&1
echo "setup exit=$?"; tail -1 "$OUT/setup.log"
dc exec -T -u www-data wp sh -c 'cat /tmp/debug-activation.log' > "$OUT/debug-activation.log"
dc exec -T -u www-data wp sh -c 'cat /tmp/debug-fixtures.log' > "$OUT/debug-fixtures.log"
dc exec -T -u www-data wp cat /tmp/mb-fixture-expect.json > "$OUT/expect.json"
dc exec -T -u www-data wp cat /tmp/mb-fixture-expect-6b3.json > "$OUT/expect-6b3.json"
dc exec -T -u www-data wp wp --path=/var/www/html eval-file /opt/mb-runtime/check-registration.php > "$OUT/registration.json"
dc exec -T -u www-data wp wp --path=/var/www/html eval-file /opt/mb-runtime/probe-types.php > "$OUT/probe-types.json"

echo "== 2) oturum aç + ısınma (başlangıçtan ÖNCE)"
node admin-http.js login "$ENV_FILE" "$OUT/state"

echo "== 3) snapshot A (başlangıç)"
bash snapshot.sh "$ENV_FILE" "$OUT/snapA"

echo "== 4) WP-CLI dry-run"
for i in 1 2; do
	dc exec -T -u www-data wp wp --path=/var/www/html mavibelge import catalog --dry-run --format=json > "$OUT/cli$i.json" 2> "$OUT/cli$i.err"
	echo "cli json$i exit=$? stderr_bytes=$(wc -c < "$OUT/cli$i.err")"
done
dc exec -T -u www-data wp wp --path=/var/www/html mavibelge import catalog --dry-run > "$OUT/cli-table.txt" 2> "$OUT/cli-table.err"
echo "cli table exit=$? satır=$(wc -l < "$OUT/cli-table.txt") stderr_bytes=$(wc -c < "$OUT/cli-table.err")"
dc exec -T -u www-data wp wp --path=/var/www/html mavibelge import catalog > "$OUT/cli-noflag.txt" 2>&1
echo "cli (--dry-run bayraksız) exit=$?"
dc exec -T -u www-data wp wp --path=/var/www/html mavibelge import catalog --dry-run --format=xml > /dev/null 2> "$OUT/cli-badformat.err"
echo "cli --format=xml exit=$? :: $(tr '\n' ' ' < "$OUT/cli-badformat.err")"
dc exec -T -u www-data wp wp --path=/var/www/html mavibelge import catalog --apply > /dev/null 2> "$OUT/cli-apply.err"
echo "cli --apply exit=$? :: $(tr '\n' ' ' < "$OUT/cli-apply.err")"

echo "== 5) snapshot B (CLI sonrası)"
bash snapshot.sh "$ENV_FILE" "$OUT/snapB"

echo "== 6) admin HTTP testleri"
node admin-http.js tests "$ENV_FILE" "$OUT/state" "$OUT/cli1.json" > "$OUT/admin-tests.txt" 2>&1
echo "admin exit=$?"; tail -1 "$OUT/admin-tests.txt"

echo "== 7) snapshot C (admin sonrası)"
bash snapshot.sh "$ENV_FILE" "$OUT/snapC"

echo "== 8) karşılaştırmalar"
for pair in "A B" "B C" "A C"; do
	set -- $pair
	d1=$(diff "$OUT/snap$1/db.sql" "$OUT/snap$2/db.sql" | wc -l)
	d2=$(diff "$OUT/snap$1/tables.txt" "$OUT/snap$2/tables.txt" | wc -l)
	d3=$(diff "$OUT/snap$1/files.txt" "$OUT/snap$2/files.txt" | wc -l)
	d4=$(diff "$OUT/snap$1/uploads.txt" "$OUT/snap$2/uploads.txt" | wc -l)
	echo "$1->$2: db.sql fark satırı=$d1 tables fark=$d2 dosya fark=$d3 uploads fark=$d4 debug.log($2)=$(wc -l < "$OUT/snap$2/debug.log") satır"
done
node -e '
const fs=require("fs");const r=f=>{const j=JSON.parse(fs.readFileSync(f,"utf8").replace(/^[^{]*/,""));delete j.generated_at_utc;return JSON.stringify(j)};
console.log("cli json1 == json2 (generated_at_utc hariç):", r(process.argv[1])===r(process.argv[2]));' "$OUT/cli1.json" "$OUT/cli2.json"
node scripts/summarize-plan.js "$OUT/cli1.json" > "$OUT/cli-summary.json"
node compare-expect.js "$OUT/expect.json" "$OUT/cli1.json" "$OUT/expect-6b3.json" > "$OUT/scenario-compare.txt"
echo "senaryo karşılaştırma exit=$? :: $(tail -1 "$OUT/scenario-compare.txt")"
