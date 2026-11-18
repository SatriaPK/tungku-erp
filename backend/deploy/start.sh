#!/bin/sh
set -eu
cd /var/www/html

# Never bootstrap a deployment with a temporary SQLite database or a new key.
: "${APP_KEY:?Set a persistent APP_KEY in the hosting dashboard}"
: "${APP_URL:?Set the HTTPS API URL}"
: "${DB_HOST:?Set the external database host}"
: "${DB_DATABASE:?Set the external database name}"
: "${DB_USERNAME:?Set the external database username}"
: "${DB_PASSWORD:?Set the external database password}"
if [ "${DB_CONNECTION:-}" != mysql ]; then
    echo 'This free-hosting image requires DB_CONNECTION=mysql and a persistent external database.' >&2
    exit 1
fi

port="${PORT:-10000}"
case "$port" in ''|*[!0-9]*) echo 'PORT must be numeric.' >&2; exit 1;; esac
sed -i "s/^Listen 80$/Listen $port/" /etc/apache2/ports.conf
sed -i "s/\*:10000>/*:$port>/" /etc/apache2/sites-available/000-default.conf
php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan view:cache

# Catalog vectors are disposable; rebuild from the durable catalog after a restart.
# An empty/new catalog continues with the existing explicit matching fallback.
if ! php artisan ml:train-package-recommendations; then
    echo 'Recommendation training unavailable; the API will use its catalog fallback.' >&2
fi
chown -R www-data:www-data storage bootstrap/cache
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
