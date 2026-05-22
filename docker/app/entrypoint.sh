#!/bin/sh
set -e

echo "[entrypoint] Waiting for database..."
php artisan db:wait

echo "[entrypoint] Running migrations..."
php artisan migrate --force

if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] APP_KEY is empty, generating..."
    php artisan key:generate --force
fi

echo "[entrypoint] Caching config, routes and views..."
php artisan optimize

echo "[entrypoint] Starting php-fpm..."
exec php-fpm
