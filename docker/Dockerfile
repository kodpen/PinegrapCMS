# Pinegrap - Enterprise Website Platform
#
# Two images from one build, both built from the repository root (the build
# context is the folder that holds index.php and pinegrap/):
#
#   app   PHP-FPM with the extensions Pinegrap uses; also runs the general
#         job in the cron service of docker-compose.yml
#   web   nginx serving the same files, with the rules the installer writes
#         for nginx (includes/server_config.php), passing PHP to app
#
# BuildKit is required (the default builder since Docker 23): it reads
# Dockerfile.dockerignore next to this file, which keeps the runtime contents
# of pinegrap/data/ (a development machine's config.php, backups, uploads)
# out of the image.
#
# @author      Erdal Güral (Kodpen)
# @link        https://kodpen.com
# @copyright   2017–2026 Kodpen
# @license     https://opensource.org/licenses/mit-license.html MIT License

FROM php:8.3-fpm-alpine AS app

# Required by System Status: mysqli, curl, openssl, mbstring, zip, fileinfo,
# opcache (curl, openssl, mbstring and fileinfo are built into the base
# image). Also used: gd with JPEG, PNG, WebP and AVIF for images, imagick as
# the better image engine, pdo_mysql for backups, intl, exif for photo
# orientation, ftp for backups to an FTP server.
RUN set -eux; \
    apk add --no-cache \
        freetype libjpeg-turbo libpng libwebp libavif libzip icu-libs imagemagick; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev libavif-dev libzip-dev icu-dev imagemagick-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif; \
    docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql gd zip intl exif opcache ftp; \
    pecl install imagick; \
    docker-php-ext-enable imagick; \
    rm -rf /tmp/pear; \
    apk del --no-network .build-deps; \
    php -m

COPY docker/php.ini "$PHP_INI_DIR/conf.d/pinegrap.ini"
COPY docker/cron.sh /usr/local/bin/pinegrap-cron
COPY docker/job-ready.php /usr/local/bin/pinegrap-job-ready

WORKDIR /var/www/html

# The release tree: the root index.php that hands the site to the router,
# and the software folder. web.config (IIS) and download_assistant.php (an
# installer for hosts without shell access) have no use here.
COPY --chown=www-data:www-data index.php /var/www/html/index.php
COPY --chown=www-data:www-data pinegrap /var/www/html/pinegrap

# data/config.php starts as the empty file the installer expects; the
# installer writes the real one into the data volume. The whole tree belongs
# to www-data, as on a host where updates are installed from the panel.
RUN set -eux; \
    chmod 0755 /usr/local/bin/pinegrap-cron; \
    chmod 0644 /usr/local/bin/pinegrap-job-ready; \
    printf '<?php ?>' > /var/www/html/pinegrap/data/config.php; \
    chown -R www-data:www-data /var/www/html

# Uploads, layouts, backups, temporary files and config.php live here. A
# new named volume is filled from the image on first start, starter sites
# included.
VOLUME /var/www/html/pinegrap/data


FROM nginx:stable-alpine AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html /var/www/html
