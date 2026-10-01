#!/bin/sh
set -e

: "${PORT:=8080}"
export PORT

# Allow `docker run <image> <command>` to run one-off commands (artisan
# tinker, key:generate, migrate, a shell, ...) instead of always booting
# the web server — needed for local testing and for Render's shell/console.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec supervisord -c /etc/supervisor/supervisord.conf
