# Faz 6B2 runtime doğrulaması — YALNIZ yerel, izole, silinebilir test ortamı.
# Üretim imajı DEĞİLDİR. PHP 7.3 bilerek seçildi (bağlayıcı üretim kısıtı,
# raporlar/karar-kaydi-wordpress-php73.md); PHP 7.3 EOL'dür.
FROM php:7.3-apache

RUN docker-php-ext-install mysqli

# WP-CLI — resmî GitHub sürümü, yayınlanan sha512 ile doğrulanır.
ARG WP_CLI_VERSION=2.12.0
RUN curl -fsSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
 && curl -fsSL -o /tmp/wp-cli.sha512 "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar.sha512" \
 && echo "$(cat /tmp/wp-cli.sha512)  /usr/local/bin/wp" | sha512sum -c - \
 && chmod +x /usr/local/bin/wp \
 && rm /tmp/wp-cli.sha512

RUN chown www-data:www-data /var/www/html
ENV WP_CLI_CACHE_DIR=/tmp/wp-cli-cache
