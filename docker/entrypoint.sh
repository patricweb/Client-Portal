#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f public/build/manifest.json ]; then
    npm run build
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

if [ ! -L public/storage ]; then
    php artisan storage:link >/dev/null 2>&1 || true
fi

exec "$@"
