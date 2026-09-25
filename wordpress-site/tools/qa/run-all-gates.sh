#!/usr/bin/env bash
# Tüm yerel kalite kapılarını sırayla çalıştırır ve gerçek sayıları özetler. DEPLOY/CANLI/STAGING bağlantısı YOKTUR.
# Kullanım: bash tools/qa/run-all-gates.sh [--runtime] <çıktı-dizini>
#   --runtime : Docker'daki (mbruntime6b2-*) gerçek WordPress runtime kapılarını da çalıştırır (with-lock.sh ile seri).
set -uo pipefail
RUNTIME=0
if [ "${1:-}" = "--runtime" ]; then RUNTIME=1; shift; fi
OUT="${1:-/tmp/mb-gates}"
HERE="$(cd "$(dirname "$0")" && pwd)"
WS="$(cd "$HERE/../.." && pwd)"
REPO="$(cd "$WS/.." && pwd)"
export MSYS_NO_PATHCONV=1
mkdir -p "$OUT"
WP=mbruntime6b2-wp-1
FAILS=0
gate() { # gate <etiket> <komut...>
	local label="$1"; shift
	local log="$OUT/$(echo "$label" | tr -c 'A-Za-z0-9' '_').log"
	if "$@" > "$log" 2>&1 && ! grep -q "^FAIL" "$log"; then echo "PASS  $label :: $(tail -1 "$log" | cut -c1-150)"; else echo "FAIL  $label :: $(tail -1 "$log" | cut -c1-150)"; FAILS=$((FAILS + 1)); fi
}
cd "$WS"
echo "== Statik/Node kapıları"
gate "kaynak sayıları 14/83/103/145/87/16/58/84/19" node tools/verify-source-counts.js
gate "Faz 6A extract güvenlik testleri" node tools/import/test-extract-safety.js
gate "manifest negatif/regresyon testleri" node tools/import/test-manifest-validation.js
gate "manifest doğrulama + finalizasyon" node tools/import/verify-manifest.js
gate "içerik manifesti doğrulama (6 haber / 12 referans)" node tools/import/verify-content-manifest.js
gate "içerik manifesti testleri" node tools/import/test-content-manifest.js
gate "Faz 6B2/6B3/7/8 statik sözleşme" node tools/test-faz6b2-static-contract.js
gate "tema ücret metni testi" node tools/test-theme-fee-text.js
for t in wp-content/themes/mavibelge/tests/static/*.test.js wp-content/themes/mavibelge/tests/js/*.test.js; do gate "tema testi $(basename "$t")" node "$t"; done
gate "tema dist == src (byte-eşit)" node tools/build/build-theme-assets.js --check
gate "SEO sayfa varsayılanları == kaynak" node tools/seo/build-page-defaults.js --check
gate "yönlendirme manifesti == kaynak" node tools/redirects/build-redirect-manifest.js --check
gate "renk kontrastı (token çiftleri)" node tools/qa/contrast-check.js
for f in $(find wp-content/themes/mavibelge/assets/src/js tools -name '*.js' -not -path '*/node_modules/*'); do node --check "$f" || echo "node --check BAŞARISIZ: $f" >> "$OUT/nodecheck.err"; done
gate "node --check (tüm JS)" bash -c "test ! -s '$OUT/nodecheck.err'"
echo "== Depo taramaları"
gate "gizli bilgi taraması (özel anahtar/AKIA/ghp_/gerçek anahtar tanımı)" bash -c "! grep -rIlE -e '-----BEGIN [A-Z ]*PRIVATE KEY-----' -e '\\bAKIA[0-9A-Z]{16}\\b' -e '\\bghp_[A-Za-z0-9]{30,}\\b' -e \"define\\( *'(AUTH|SECURE_AUTH|LOGGED_IN|NONCE)_KEY', *'[^']{16,}\" '$WS' --exclude-dir=node_modules --exclude-dir=dist-packages --exclude='*.zip' --exclude='*.png' | grep -v hardening"
gate "NUL bayt taraması (metin dosyaları)" bash -c "! grep -rIlP '\\x00' '$WS' --exclude-dir=node_modules --exclude-dir=dist-packages --exclude='*.zip' --exclude='*.png' --exclude='*.jpg' --exclude='*.pdf'"
gate "satır sonu boşluğu taraması (PHP/JS/CSS/JSON yeni kodda)" bash -c "! grep -rnE '[ 	]+$' '$WS/wp-content/plugins/mavibelge-core' '$WS/wp-content/themes/mavibelge' --include=*.php --include=*.js --include=*.css | grep -v '/tests/' | grep -v 'dist/' | head -1 | grep ."
gate "PHP 7.4+/8.x yasak sözdizimi taraması (eklenti+tema)" bash -c "! grep -rnE '\\?->|\\?\\?=|\\bstr_contains *\\(|\\bstr_starts_with *\\(|\\barray_is_list *\\(|\\benum +[A-Za-z]+ *\\{' '$WS/wp-content/plugins/mavibelge-core' '$WS/wp-content/themes/mavibelge' --include=*.php | grep -v '/tests/' | grep -vE '^[^:]+:[0-9]+:[ 	]*(\\*|//)' | head -1 | grep ."
echo "== Git koruma kontrolleri"
gate "tanitim-site/** değişmedi (git)" bash -c "cd '$REPO' && test -z \"\$(git status --porcelain tanitim-site | grep -v 'yeni-mavibelge-v1.zip')\" && test -z \"\$(git diff --name-only -- tanitim-site)\""
gate "korunan kullanıcı dosyaları değişmedi (git)" bash -c "cd '$REPO' && test -z \"\$(git diff --name-only -- 'CLAUDE_*.md' 'STITCH_*.md' tanitim-site .gitignore)\""
gate "git diff --check (takip edilen dosyalar)" bash -c "cd '$REPO' && git diff --check"
gate "paketler: üretim + testler" bash -c "node tools/package/build-packages.js --write >/dev/null && node tools/package/test-package.js"
if [ "$RUNTIME" = "1" ]; then
	echo "== PHP 7.3 / WordPress 6.9.9 runtime kapıları (Docker)"
	gate "PHP lint (tüm eklenti+tema dosyaları)" docker exec "$WP" sh -c "cd /var/www/html/wp-content && n=0; for f in \$(find plugins/mavibelge-core themes/mavibelge -name '*.php'); do n=\$((n+1)); php -l \$f | grep -v 'No syntax errors'; done; echo \"lint tamam: \$n dosya\"; test -z \"\$(for f in \$(find plugins/mavibelge-core themes/mavibelge -name '*.php'); do php -l \$f | grep -v 'No syntax errors'; done)\""
	gate "eklenti birim testleri (koşu 1)" docker exec "$WP" sh -c "cd /var/www/html/wp-content/plugins/mavibelge-core && php tests/run.php > /tmp/r1.txt 2>&1; grep -E 'assertions passed' /tmp/r1.txt; ! grep -q '^FAIL' /tmp/r1.txt"
	gate "eklenti birim testleri (koşu 2) + byte-eşitlik" docker exec "$WP" sh -c "cd /var/www/html/wp-content/plugins/mavibelge-core && php tests/run.php > /tmp/r2.txt 2>&1; cmp /tmp/r1.txt /tmp/r2.txt && echo 'iki koşu BYTE-EŞİT: '\$(grep -E 'assertions passed' /tmp/r2.txt)"
	gate "apply/rollback/reapply + audit hata enjeksiyonu + içerik aşaması runtime döngüsü" bash "$WS/tools/runtime-test/with-lock.sh" bash "$WS/tools/runtime-test/apply-cycle.sh" "$OUT/apply-cycle"
	gate "Faz 7/8/9 HTTP render + form + SEO + yönlendirme + (Faz 10) testleri" bash "$WS/tools/runtime-test/with-lock.sh" bash "$WS/tools/runtime-test/content-http.sh" "$OUT/content-http"
	gate "Faz 11 render QA (41 sayfa + boş durum + eklenti pasif)" bash "$WS/tools/runtime-test/with-lock.sh" bash "$WS/tools/runtime-test/qa-render.sh" "$OUT/qa-render"
fi
echo
if [ "$FAILS" -eq 0 ]; then echo "TÜM KAPILAR GEÇTİ (yerel)."; else echo "BAŞARISIZ KAPI SAYISI: $FAILS"; fi
exit "$FAILS"
