#!/usr/bin/env bash
# Faz 6B4 — admin görsel eşleme + kesintiye dayanıklı apply/rollback runtime kapıları, MEVCUT izole ortamda (mbruntime6b2-*).
# Ana `wp_` veritabanına YAZMAZ: bütün yazmalar `wp_` tablolarının silinebilir `mbfx_` klonunda ve AÇIKÇA SAHTE fixture
# manifestiyle (`zz-test-*`) yapılır; GERÇEK manifest yalnız salt okunur dry-run/görsel-map doğrulaması için okunur.
# Her faz TAZE klonda, AYRI PHP sürecinde çalışır. Sonunda klon/manifest/geçici görseller silinir; ana DB + dosyalar + uploads
# başlangıç anlık görüntüsüyle karşılaştırılır ve debug log sayılır. Parola/env dosyası OKUMAZ; Konteyner/volume SİLMEZ.
# Kullanım: admin-import.sh <çıktı-dizini (depo DIŞINDA)>
set -uo pipefail
OUT="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
export MSYS_NO_PATHCONV=1
WP=mbruntime6b2-wp-1
mkdir -p "$OUT"
cd "$HERE"
wpx() { docker exec -u www-data "$WP" wp --path=/var/www/html "$@"; }
fx() { docker exec -u www-data "$WP" wp --path=/var/www/html --require=/opt/mb-runtime/fixture-env.php "$@"; }
json() { node -e 'const s=require("fs").readFileSync(0,"utf8");const i=s.search(/^RESULT /m);const j=JSON.parse(s.slice(i+7).split("\n")[0]);const v=process.argv[1].split(".").reduce((o,k)=>o==null?o:o[k],j);console.log(typeof v==="object"?JSON.stringify(v):String(v));' "$1"; }
snapshot() {
	local d="$OUT/$1"
	mkdir -p "$d"
	wpx eval-file /opt/mb-runtime/db-snapshot.php > "$d/db.txt"
	grep '^## TABLE' "$d/db.txt" > "$d/tables.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" ! -path "./wp-content/plugins/mavibelge-core/*" ! -path "./wp-content/themes/mavibelge/*" ! -path "./data/*" -print0 | sort -z | xargs -0 sha256sum' > "$d/files.txt"
	docker exec -u www-data "$WP" sh -c 'cd /var/www/html && ls -laR --time-style=+%s wp-content/uploads 2>/dev/null || echo "uploads yok"' > "$d/uploads.txt"
	echo "snapshot $1: $(wc -l < "$d/tables.txt") tablo, $(wc -l < "$d/files.txt") dosya, db sha256=$(sha256sum "$d/db.txt" | cut -c1-16)"
}
check() { if [ "$2" = "$3" ]; then echo "PASS  $1"; else echo "FAIL  $1  [beklenen=$3 gerçek=$2]"; fi; }
fresh() {
	wpx eval-file /opt/mb-runtime/fixture-db.php drop > /dev/null
	wpx eval-file /opt/mb-runtime/fixture-db.php clone | tail -1
	wpx eval-file /opt/mb-runtime/fixture-db.php manifest > /dev/null
}
phase() {
	local name="$1"; shift
	echo "== faz: $name"
	fresh
	fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php "$name" "$@" > "$OUT/$name.txt" 2> "$OUT/$name.err"
	echo "$name exit=$? :: $(tail -1 "$OUT/$name.txt") :: stderr satırı=$(wc -l < "$OUT/$name.err")"
	grep -E '^FAIL' "$OUT/$name.txt" || true
}

echo "== 1) başlangıç: debug log temizlenir, ana DB/dosya anlık görüntüsü A"
wpx eval 'wp_get_theme()->delete_pattern_cache(); wp_get_theme()->get_block_patterns(); echo "pattern cache tazelendi\n";'
docker exec -u www-data "$WP" sh -c 'rm -f /var/www/html/wp-content/debug.log'
snapshot snapA

phase map
phase image-map-atomicity
phase stages
phase sector-rollback
phase small-batches
phase migration
phase faults
phase pages

race_run() { # race_run <mod: lock|nolock> <önek>
	local mode="$1" pfx="$2"
	fresh
	fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php map-race-setup > "$OUT/${pfx}0.txt" 2> "$OUT/${pfx}0.err"
	( fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php map-race-a "$mode" > "$OUT/${pfx}A.txt" 2> "$OUT/${pfx}A.err" ) &
	local pa=$!
	sleep 1.5
	fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php map-race-b "$mode" > "$OUT/${pfx}B.txt" 2> "$OUT/${pfx}B.err"
	wait $pa
	fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php map-race-verify > "$OUT/${pfx}V.txt" 2> "$OUT/${pfx}V.err"
}
echo "== faz: map-race KONTROL (kilitsiz; düzeltmeden önceki davranış): audit zinciri BOZULMALI — yarışın gerçek DB'de yaşandığının kanıtı"
race_run nolock raceC
check "KONTROL (kilitsiz): iki yönetici eşzamanlı -> audit old_digest zinciri BOZUK (chain_ok=false); yarış gerçek" "$(json audit_rows < "$OUT/raceCV.txt"):$(json chain_ok < "$OUT/raceCV.txt")" "3:false"
echo "== faz: map-race (İKİ AYRI PHP süreci, GERÇEK GET_LOCK: eşzamanlı iki yönetici görsel eşleme kaydı)"
race_run lock race
check "yönetici A ve B'nin İKİSİ de başarılı (kilit sırayla serileştirdi; `locked` yok)" "$(json ok < "$OUT/raceA.txt"):$(json ok < "$OUT/raceB.txt")" "true:true"
check "B, A'nın kilidi yüzünden BEKLEDİ (B süresi >= 2 sn; gerçek eşzamanlılık kanıtı)" "$(json elapsed < "$OUT/raceB.txt" | awk '{print ($1>=2)?"bekledi":"beklemedi:"$1}')" "bekledi"
check "A ve B AYRI PHP süreçleri" "$(( $(json pid < "$OUT/raceA.txt") != $(json pid < "$OUT/raceB.txt") ))" "1"
check "audit: 3 satır; M0 -> A -> B zinciri (B.old_digest == A.new_digest, A.old_digest == M0.new_digest)" "$(json audit_rows < "$OUT/raceV.txt"):$(json chain_ok < "$OUT/raceV.txt")" "3:true"
check "son option B'nin tam haritası (tekstil=B; cam B'nin gönderdiği değer) — son yazan kazanır ve audit bunu doğru anlatır" "$(json final_has_b < "$OUT/raceV.txt"):$(json final_cam_is_b_value < "$OUT/raceV.txt")" "true:true"
check "B'nin audit changed_slugs'ı gerçek fark [cam,tekstil] (A'nın yazımına göre); açık transaction yok; audit hash zinciri sağlam" "$(json changed_b < "$OUT/raceV.txt"):$(json in_tx < "$OUT/raceV.txt"):$(json audit_hash_chain < "$OUT/raceV.txt")" '["cam","tekstil"]:0:true'

echo "== faz: resume (AYRI PHP süreçleri: sayfa kapanması benzetimi)"
fresh
fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php resume-start > "$OUT/r0.txt" 2>&1
check "süreç 1 (start): run ready, checkpoint 0, total 25, 0 sektör" "$(json ok < "$OUT/r0.txt"):$(json status < "$OUT/r0.txt"):$(json checkpoint < "$OUT/r0.txt"):$(json total < "$OUT/r0.txt"):$(json sectors < "$OUT/r0.txt")" "true:ready:0:25:0"
fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php resume-advance 0 > "$OUT/r1.txt" 2>&1
check "süreç 2 (advance, checkpoint 0): 10 sektör, paused, checkpoint 1" "$(json ok < "$OUT/r1.txt"):$(json status < "$OUT/r1.txt"):$(json checkpoint < "$OUT/r1.txt"):$(json sectors < "$OUT/r1.txt")" "true:paused:1:10"
fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php resume-advance 0 > "$OUT/r1b.txt" 2>&1
check "süreç 3 (çift tıklama: aynı checkpoint 0): stale_request, sektör sayısı DEĞİŞMEZ (10)" "$(json ok < "$OUT/r1b.txt"):$(json error_code < "$OUT/r1b.txt"):$(json sectors < "$OUT/r1b.txt")" "false:stale_request:10"
fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php resume-advance 1 > "$OUT/r2.txt" 2>&1
check "süreç 4 (advance, checkpoint 1): 20 sektör, paused, checkpoint 2" "$(json ok < "$OUT/r2.txt"):$(json status < "$OUT/r2.txt"):$(json checkpoint < "$OUT/r2.txt"):$(json sectors < "$OUT/r2.txt")" "true:paused:2:20"
fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php resume-advance 2 > "$OUT/r3.txt" 2>&1
check "süreç 5 (advance, checkpoint 2): 25 sektör, completed, checkpoint 3" "$(json ok < "$OUT/r3.txt"):$(json status < "$OUT/r3.txt"):$(json checkpoint < "$OUT/r3.txt"):$(json sectors < "$OUT/r3.txt")" "true:completed:3:25"
fx --user=mbadmin eval-file /opt/mb-runtime/admin-import-runtime.php resume-final > "$OUT/r4.txt" 2>&1
check "süreç 6 (denetim): completed, 3 batch, 25 item, 25 run item, 25 unchanged" "$(json status < "$OUT/r4.txt"):$(json batches < "$OUT/r4.txt"):$(json items < "$OUT/r4.txt"):$(json run_items < "$OUT/r4.txt"):$(json unchanged < "$OUT/r4.txt")" "completed:3:25:25:25"
PIDS="$(for f in r0 r1 r1b r2 r3 r4; do json pid < "$OUT/$f.txt" 2>/dev/null; done | sort -u | wc -l)"
check "altı çağrı ALTI AYRI PHP süreciydi (farklı pid'ler)" "$PIDS" "6"

echo "== 5) fixture veritabanı ve sahte manifest silinir"
wpx eval-file /opt/mb-runtime/fixture-db.php drop
wpx eval-file /opt/mb-runtime/fixture-db.php rmmanifest

echo "== 6) ana DB/dosya/uploads farkı ve debug log"
snapshot snapZ
echo "A->Z: db fark satırı=$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l) tablo fark=$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l) dosya fark=$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l) uploads fark=$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)"
check "ana wp_ DB/tablo/dosya/uploads farkı 0" "$(diff "$OUT/snapA/db.txt" "$OUT/snapZ/db.txt" | wc -l):$(diff "$OUT/snapA/tables.txt" "$OUT/snapZ/tables.txt" | wc -l):$(diff "$OUT/snapA/files.txt" "$OUT/snapZ/files.txt" | wc -l):$(diff "$OUT/snapA/uploads.txt" "$OUT/snapZ/uploads.txt" | wc -l)" "0:0:0:0"
docker exec -u www-data "$WP" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"
check "debug.log 0 satır (fatal/warning/notice yok)" "$(wc -l < "$OUT/debug.log")" "0"
