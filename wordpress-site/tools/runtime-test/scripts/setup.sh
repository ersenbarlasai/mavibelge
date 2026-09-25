#!/bin/sh
# Faz 6B2 runtime doğrulaması — konteyner İÇİNDE, www-data olarak çalışır.
# YALNIZ izole test veritabanı/dosyalarına yazar (kurulum + test fixture).
# Gerekli ortam: DB_PASSWORD (compose), ADMPW, LOWPW (exec -e ile).
set -eu
cd /var/www/html

WP_VERSION=6.9.9

# 1) WordPress çekirdeği — resmî tar.gz + yayınlanan sha1, sonra dosya checksum'ları.
cd /tmp
curl -fsSL -o wp.tgz "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
curl -fsSL -o wp.sha1 "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz.sha1"
echo "$(cat wp.sha1)  wp.tgz" | sha1sum -c -
tar -xzf wp.tgz -C /var/www/html --strip-components=1
rm -f wp.tgz wp.sha1
cd /var/www/html
wp core verify-checksums

# 2) Yapılandırma — debug log açık, ekranda gösterim kapalı, cron kapalı, dış HTTP kapalı.
wp config create --dbname=wp --dbuser=wp --dbpass="$DB_PASSWORD" --dbhost=db --dbcharset=utf8mb4 --quiet --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '/var/www/html/wp-content/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DISALLOW_FILE_EDIT', true );
PHP

wp core install --url=http://127.0.0.1:18673 --title="MB Runtime Test" --admin_user=mbadmin --admin_password="$ADMPW" --admin_email=admin@example.invalid --skip-email

# 3) Test izolasyon mu-plugin'i (yalnız çekirdek güncelleme denetimlerini kapatır).
mkdir -p wp-content/mu-plugins
cp /opt/mb-runtime/mu-runtime-isolation.php wp-content/mu-plugins/

# 4) Eklenti + tema aktivasyonu — debug log bu aşama için ayrı saklanır.
rm -f wp-content/debug.log
wp plugin activate mavibelge-core
wp theme activate mavibelge
if [ -f wp-content/debug.log ]; then mv wp-content/debug.log /tmp/debug-activation.log; else : > /tmp/debug-activation.log; fi

# 5) Kullanıcılar — yetkisiz iki kullanıcı (özel içerik editörü rolü + abone).
wp user create mbeditor editor@example.invalid --role=mb_content_editor --user_pass="$LOWPW" --quiet
wp user create mbsubscriber subscriber@example.invalid --role=subscriber --user_pass="$LOWPW" --quiet

# 6) Test fixture yazımı (dry-run'dan ÖNCE; ayrı log).
rm -f wp-content/debug.log
wp eval-file /opt/mb-runtime/fixtures.php
# Faz 6B3 Önkoşul — doğal anahtar senaryoları (beklenti geçersiz kılmaları + eklemeler).
wp eval-file /opt/mb-runtime/fixtures-natural-key.php
if [ -f wp-content/debug.log ]; then mv wp-content/debug.log /tmp/debug-fixtures.log; else : > /tmp/debug-fixtures.log; fi

echo "SETUP_OK activation_log_lines=$(wc -l </tmp/debug-activation.log) fixture_log_lines=$(wc -l </tmp/debug-fixtures.log)"
