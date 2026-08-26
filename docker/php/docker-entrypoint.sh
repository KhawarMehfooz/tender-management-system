#!/bin/sh
set -e

# PHP_UPLOAD_MAX_FILESIZE is normally injected as a container env var (see
# docker-compose*.yml `env_file: .env`). Fall back to reading it straight out
# of the mounted .env for local dev, where the app container gets its .env
# via bind mount rather than `env_file`.
if [ -z "${PHP_UPLOAD_MAX_FILESIZE:-}" ] && [ -f /var/www/html/.env ]; then
    PHP_UPLOAD_MAX_FILESIZE=$(grep -m1 '^PHP_UPLOAD_MAX_FILESIZE=' /var/www/html/.env | cut -d '=' -f2-)
fi

: "${PHP_UPLOAD_MAX_FILESIZE:=6M}"

cat > /usr/local/etc/php/conf.d/uploads.ini <<INI
upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE}
post_max_size = ${PHP_UPLOAD_MAX_FILESIZE}
INI

exec "$@"