#!/usr/bin/env bash
# Host tarafı: izole test ortamının veritabanı + dosya anlık görüntüsünü alır.
# Kullanım: snapshot.sh <env-dosyası> <çıktı-dizini>
# Hiçbir şey YAZMAZ (yalnız okur: mariadb-dump, SELECT COUNT, sha256sum).
set -euo pipefail
ENV_FILE="$1"
OUT="$2"
mkdir -p "$OUT"
export MSYS_NO_PATHCONV=1
dc() { docker compose -p mbruntime6b2 --env-file "$ENV_FILE" "$@"; }

dc exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb-dump -uroot --skip-dump-date --skip-comments --skip-extended-insert --order-by-primary --single-transaction wp' > "$OUT/db.sql"

dc exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema=\"wp\" ORDER BY table_name" | while read t; do echo "$t $(MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N wp -e "SELECT COUNT(*) FROM \`$t\`") $(MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N wp -e "CHECKSUM TABLE \`$t\` EXTENDED" | cut -f2)"; done' > "$OUT/tables.txt"

# Dosyalar: debug.log hariç tüm WordPress ağacı (eklenti/tema salt okunur bağlı olsa da dahil).
dc exec -T -u www-data wp sh -c 'cd /var/www/html && find . -type f ! -path "./wp-content/debug.log" -print0 | sort -z | xargs -0 sha256sum' > "$OUT/files.txt"
dc exec -T -u www-data wp sh -c 'cd /var/www/html && ls -laR wp-content/uploads 2>/dev/null || echo "uploads yok"' > "$OUT/uploads.txt"
dc exec -T -u www-data wp sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null || true' > "$OUT/debug.log"

echo "snapshot: $(wc -l < "$OUT/tables.txt") tablo, $(wc -l < "$OUT/files.txt") dosya, db.sql sha256=$(sha256sum "$OUT/db.sql" | cut -c1-16), debug.log $(wc -l < "$OUT/debug.log") satır"
