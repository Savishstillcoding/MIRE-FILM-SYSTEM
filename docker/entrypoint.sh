#!/bin/sh
set -e
cd /var/www/html

# Make sure Laravel's writable dirs exist (matters for fresh named volumes)
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

run() { su-exec www-data "$@"; }

if [ "$1" = "php-fpm" ]; then
  if [ "${APP_ENV:-production}" = "production" ]; then
    # Production: cache config/routes/views, optionally migrate
    run php artisan config:cache
    run php artisan route:cache
    run php artisan view:cache
    [ "${RUN_MIGRATIONS:-false}" = "true" ] && run php artisan migrate --force
  else
    # Development: install deps, create key, optionally migrate
    [ -d vendor ] || composer install --no-interaction --prefer-dist
    [ -f .env ] || { [ -f .env.example ] && cp .env.example .env; }
    grep -q '^APP_KEY=.\+' .env 2>/dev/null || run php artisan key:generate --ansi
    [ "${RUN_MIGRATIONS:-false}" = "true" ] && run php artisan migrate --force
  fi
fi

exec "$@"
