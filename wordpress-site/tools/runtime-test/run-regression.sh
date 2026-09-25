#!/usr/bin/env bash
# Faz 6B2 runtime regresyonu — MEVCUT izole ortamda (mbruntime6b2-wp-1 / -db-1).
# Parola veya env dosyası OKUMAZ: komutlar `docker exec` ile çalışır, DB anlık
# görüntüsü WordPress'in kendi $wpdb bağlantısıyla alınır, admin oturumu
# WP-CLI'nin ürettiği oturum çerezleriyle açılır. Konteyner/volume SİLMEZ.
# Kullanım: run-regression.sh <çıktı-dizini (depo DIŞINDA)> <önceki expect.json> [expect-6b3.json]
set -uo pipefail
OUT="$1"
EXPECT="$2"
EXPECT6B3="${3:-}"
HERE="$(cd "$(dirname "$0")" && pwd)"
export MSYS_NO_PATHCONV=1
WP=mbruntime6b2-wp-1
mkdir -p "$OUT/state"
cd "$HERE"
wpx() { docker exec -u www-data "$WP" wp --path=/var/www/html "$@"; }

snapshot() {
	local d="$OUT/$1"
	mkdir -p "$d"
	wpx eval-file /opt/mb-runtime/db-snapshot.php > "$d/db.txt"
	grep '^## TABLE' "$d/db.txt" > "$d/tables.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && ls -laR --time-style=+%s wp-content/uploads 2>/dev/null || echo "uploads yok"' > "$d/uploads.txt"
	docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$d/debug.log"
	echo "snapshot $1: $(wc -l < "$d/tables.txt") tablo, $(wc -l < "$d/files.txt") dosya, db sha256=$(sha256sum "$d/db.txt" | cut -c1-16), debug.log $(wc -l < "$d/debug.log") satır"
}
cmp_snap() {
	echo "$1->$2: db fark satırı=$(diff "$OUT/snap$1/db.txt" "$OUT/snap$2/db.txt" | wc -l) tablo(sayı/checksum) fark=$(diff "$OUT/snap$1/tables.txt" "$OUT/snap$2/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snap$1/files.txt" "$OUT/snap$2/files.txt" | wc -l) uploads fark=$(diff "$OUT/snap$1/uploads.txt" "$OUT/snap$2/uploads.txt" | wc -l)"
}

echo "== 1) hazırlık (snapshot A'dan ÖNCE): kayıt kontrolü, tip yoklaması, _mb_level meta testi, oturum çerezleri"
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
wpx eval-file /opt/mb-runtime/check-registration.php > "$OUT/registration.json"; echo "registration exit=$?"
wpx eval-file /opt/mb-runtime/probe-types.php > "$OUT/probe-types.json"; echo "probe-types exit=$?"
wpx eval-file /opt/mb-runtime/level-meta-test.php > "$OUT/level-meta-test.txt" 2>&1; echo "level-meta-test exit=$? :: $(tail -1 "$OUT/level-meta-test.txt")"
wpx eval-file /opt/mb-runtime/write-safety-test.php > "$OUT/write-safety-test.txt" 2>&1; echo "write-safety-test exit=$? :: $(tail -1 "$OUT/write-safety-test.txt")"
wpx eval-file /opt/mb-runtime/make-cookies.php > "$OUT/cookies-roles.json"; echo "make-cookies exit=$? $(cat "$OUT/cookies-roles.json")"
for u in mbadmin mbeditor mbsubscriber; do
	docker exec -u www-data "$WP" cat "/tmp/mb-cookies-$u.json" > "$OUT/state/cookies-$u.json"
	docker exec -u www-data "$WP" rm -f "/tmp/mb-cookies-$u.json"
done
node admin-http.js warmup "$OUT/state"

echo "== 2) snapshot A"
snapshot snapA

echo "== 3) WP-CLI dry-run"
for i in 1 2; do
	wpx mavibelge import catalog --dry-run --format=json > "$OUT/cli$i.json" 2> "$OUT/cli$i.err"
	echo "cli json$i exit=$? stderr_bytes=$(wc -c < "$OUT/cli$i.err")"
done
wpx mavibelge import catalog --dry-run > "$OUT/cli-table.txt" 2> "$OUT/cli-table.err"
echo "cli table exit=$? satır=$(wc -l < "$OUT/cli-table.txt") stderr_bytes=$(wc -c < "$OUT/cli-table.err")"
wpx mavibelge import catalog > /dev/null 2>&1; echo "cli (bayraksız) exit=$?"
wpx mavibelge import catalog --dry-run --format=xml > /dev/null 2> "$OUT/cli-badformat.err"; echo "cli --format=xml exit=$? :: $(tr '\n' ' ' < "$OUT/cli-badformat.err")"
wpx mavibelge import catalog --apply > /dev/null 2> "$OUT/cli-apply.err"; echo "cli --apply (anahtar kapalı) exit=$? :: $(tr '\n' ' ' < "$OUT/cli-apply.err")"
wpx mavibelge import catalog --apply --force > /dev/null 2> "$OUT/cli-force.err"; echo "cli --apply --force exit=$? :: $(tr '\n' ' ' < "$OUT/cli-force.err")"
# Faz 6B3 — GERÇEK manifest planı, apply anahtarı AÇIK + yönetici + planın kendi digest'iyle bile
# reddedilmeli (conflict/blocked); A->B karşılaştırması bu reddin sıfır yazma (tablo kurulumu dahil) olduğunu kanıtlar.
REALD="$(node -e 'const s=require("fs").readFileSync(process.argv[1],"utf8");console.log(JSON.parse(s.slice(s.search(/^[{]/m))).plan_digest)' "$OUT/cli1.json")"
docker exec -u www-data "$WP" wp --path=/var/www/html --require=/opt/mb-runtime/apply-enable.php --user=mbadmin mavibelge import catalog --apply --confirm="$REALD" --format=json > "$OUT/cli-realapply.json" 2>&1
echo "cli gerçek manifest --apply (anahtar açık, yönetici, doğru digest) exit=$? :: $(node -e 'const s=require("fs").readFileSync(process.argv[1],"utf8");const j=JSON.parse(s.slice(s.search(/^[{]/m)));console.log("status="+j.status+" error_code="+j.error_code+" run_uid="+j.run_uid)' "$OUT/cli-realapply.json")"
wpx --user=mbadmin mavibelge import status > "$OUT/cli-status.txt" 2>&1; echo "cli status exit=$? :: $(tr '\n' ' ' < "$OUT/cli-status.txt")"
wpx --user=mbadmin mavibelge import rollback --run-id=00000000000000000000000000000000 --format=json > "$OUT/cli-rollback-none.json" 2>&1; echo "cli rollback (olmayan run) exit=$? :: $(tr -d '\n ' < "$OUT/cli-rollback-none.json" | cut -c1-80)"

echo "== 4) snapshot B"
snapshot snapB

echo "== 5) admin HTTP testleri"
node admin-http.js tests - "$OUT/state" "$OUT/cli1.json" > "$OUT/admin-tests.txt" 2>&1
echo "admin exit=$? :: $(tail -1 "$OUT/admin-tests.txt")"

echo "== 6) snapshot C"
snapshot snapC

echo "== 7) karşılaştırmalar"
cmp_snap A B; cmp_snap B C; cmp_snap A C
node -e '
const fs=require("fs");const r=f=>{const j=JSON.parse(fs.readFileSync(f,"utf8").replace(/^[^{]*/,""));delete j.generated_at_utc;return JSON.stringify(j)};
console.log("cli json1 == json2 (generated_at_utc hariç):", r(process.argv[1])===r(process.argv[2]));' "$OUT/cli1.json" "$OUT/cli2.json"
node scripts/summarize-plan.js "$OUT/cli1.json" > "$OUT/cli-summary.json"
node compare-expect.js "$EXPECT" "$OUT/cli1.json" $EXPECT6B3 > "$OUT/scenario-compare.txt"; echo "senaryo exit=$? :: $(tail -1 "$OUT/scenario-compare.txt")"

echo "== 8) kanarya (dedektör duyarlılığı) — sonra geri alınır"
wpx eval 'update_term_meta( get_term_by( "slug", "plastik", "mb_sektor" )->term_id, "_mb_canary", "x" ); file_put_contents( WP_CONTENT_DIR . "/uploads-canary.txt", "x" );'
snapshot snapD
cmp_snap C D
wpx eval 'delete_term_meta( get_term_by( "slug", "plastik", "mb_sektor" )->term_id, "_mb_canary" ); @unlink( WP_CONTENT_DIR . "/uploads-canary.txt" );'

echo "== 9) tema HTTP render (gerçek HTTP istemcisi: curl; tarayıcı değil)"
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
render() {
	local label="$1" url="$2"
	local code
	code=$(curl -s -o "$OUT/render.html" -w '%{http_code}' "http://127.0.0.1:18673$url")
	printf '%-44s %s bytes=%s btn=%s pagination=%s kart(Render Test)=%s bos-sonuc=%s critical=%s parse=%s\n' "$label" "$code" "$(wc -c < "$OUT/render.html")" \
		"$(grep -c 'class="btn' "$OUT/render.html")" "$(grep -c '<nav class="pagination"' "$OUT/render.html")" \
		"$(grep -o 'Render Test Mesleği [0-9]*' "$OUT/render.html" | sort -u | wc -l)" "$(grep -c 'eşleşen yeterlilik kaydı yok' "$OUT/render.html")" \
		"$(grep -ci 'critical error' "$OUT/render.html")" "$(grep -ci 'parse error' "$OUT/render.html")"
}
{
	render "ana sayfa /" "/"
	render "sektör arşivi (sonuçlu) plastik" "/?mb_sektor=plastik"
	render "sektör arşivi sayfa 2" "/?mb_sektor=plastik&mb_page=2"
	render "sektör filtreyle boş (mb_q)" "/?mb_sektor=plastik&mb_q=zzqqxx-yok"
	render "sektör yayımlanmış kaydı olmayan (mobilya)" "/?mb_sektor=mobilya"
	render "geçersiz sektör terimi" "/?mb_sektor=yok-boyle-bir-sektor"
	render "bozuk mb_page" "/?mb_sektor=plastik&mb_page=abc"
	render "iç sayfa: 404" "/?p=999999"
} | tee "$OUT/render.txt"
echo "render sonrası debug.log: $(docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null | wc -l') satır"
