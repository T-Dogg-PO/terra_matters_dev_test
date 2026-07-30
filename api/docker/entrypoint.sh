#!/bin/sh
set -e

cd /app

if [ ! -f .env ]; then
    echo "> creating .env from .env.example"
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    echo "> installing composer dependencies"
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "> generating application key"
    php artisan key:generate --force
fi

chmod -R 777 storage bootstrap/cache 2>/dev/null || true

echo "> waiting for mysql at ${DB_HOST}:${DB_PORT}"
until mysqladmin ping -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" --silent > /dev/null 2>&1; do
    sleep 2
done
echo "> mysql is up"

php artisan migrate --force
php artisan db:seed --force

echo "> api listening on http://localhost:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
