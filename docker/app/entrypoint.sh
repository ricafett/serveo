#!/bin/sh
set -e

echo "[entrypoint] Running migrations..."
php artisan migrate --force

echo "[entrypoint] Caching config, routes and views..."
php artisan optimize

echo "[entrypoint] Starting php-fpm..."
exec php-fpm
