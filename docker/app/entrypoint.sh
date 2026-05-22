#!/bin/sh
set -e

# Generate APP_KEY before anything that boots Laravel.
# key:generate writes to .env — it doesn't need the database.
if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] APP_KEY is empty, generating..."
    php artisan key:generate --force
fi

# Wait for PostgreSQL using raw PDO — no Laravel boot needed.
echo "[entrypoint] Waiting for database (host=$DB_HOST:$DB_PORT)..."
while ! php -r '
    try {
        $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE"));
        new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
        exit(0);
    } catch (Exception $e) { exit(1); }
' 2>/dev/null; do
    echo "  Not ready, retrying in 2s..."
    sleep 2
done
echo "[entrypoint] Database is ready."

echo "[entrypoint] Running migrations..."
php artisan migrate --force

echo "[entrypoint] Caching config, routes and views..."
php artisan optimize

echo "[entrypoint] Starting php-fpm..."
exec php-fpm
