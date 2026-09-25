#!/usr/bin/env bash
# Yerel runtime fixture betiklerini SERİ çalıştırır (Git Bash'te flock yoktur; mkdir tabanlı atomik kilit).
# Kullanım: with-lock.sh <komut> [argümanlar...]
# Kilit dizini: /tmp/mbfx.lockdir (30 dk'dan eskiyse bayat sayılıp silinir). Kilit her çıkışta serbest bırakılır.
LOCK=/tmp/mbfx.lockdir
waited=0
until mkdir "$LOCK" 2>/dev/null; do
	if [ -d "$LOCK" ] && [ -n "$(find "$LOCK" -maxdepth 0 -mmin +30 2>/dev/null)" ]; then
		rmdir "$LOCK" 2>/dev/null
		continue
	fi
	sleep 5
	waited=$((waited + 5))
	if [ "$waited" -ge 1800 ]; then
		echo "with-lock: 30 dk beklendi, kilit alınamadı" >&2
		exit 99
	fi
done
trap 'rmdir "$LOCK" 2>/dev/null' EXIT
"$@"
