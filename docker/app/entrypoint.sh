#!/bin/sh
set -e

# ── Shared setup (all containers) ──────────────────────────────────────

# Ensure .env exists so artisan key:generate has a file to write to.
# .env is excluded from the Docker image by .dockerignore,
# but .env.example IS included.
if [ ! -f .env ]; then
    echo "[entrypoint] Creating .env from .env.example..."
    cp .env.example .env
fi

# Generate APP_KEY before anything that boots Laravel.
# key:generate writes to .env — it doesn't need the database.
# Docker compose may pass APP_KEY= as an empty string, which overrides
# the .env value (Laravel uses immutable dotenv). We must export the
# newly generated key so this shell process uses it.
if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] APP_KEY is empty, generating..."
    php artisan key:generate --force
    export APP_KEY="$(grep ^APP_KEY= .env | cut -d= -f2-)"
    echo "[entrypoint] APP_KEY generated and exported for this session."
fi

# ── Determine container role ─────────────────────────────────────────
#   Workers (queue:work) → skip DB setup, exec immediately.
#   App (no args) / scheduler (schedule:run via shell) → full DB setup → php-fpm.

case "$*" in
    *queue:work*)
        echo "[entrypoint] Worker container detected — skipping DB setup."
        echo "[entrypoint] Executing: $*"
        exec "$@"
        ;;
esac

# ── App / scheduler container: full DB setup ──────────────────────────

echo "[entrypoint] App/scheduler container — running DB setup."

# Wait for PostgreSQL
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

echo "[entrypoint] Seeding permissions and roles..."
php artisan db:seed --class=RolePermissionSeeder --force

echo "[entrypoint] Running CoreSeeder..."
php artisan db:seed --class=CoreSeeder --force

echo "[entrypoint] Caching config, routes and views..."
php artisan optimize

# Sync public/ assets from backup when the volume is stale.
# Named volumes survive image updates and hide the image's public/ files.
# cp -au copies only newer/missing files — existing volume files (like
# debug.php) are preserved. This handles ALL public/ content: build
# assets, Filament assets, icons, manifest, service worker, etc.
if [ -d /var/www/public-backup ]; then
    echo "[entrypoint] Syncing public/ assets from backup..."
    cp -au /var/www/public-backup/. /var/www/html/public/
    echo "[entrypoint] public/ sync complete."
fi

if [ "$APP_DEBUG" = "true" ]; then
    echo "[entrypoint] ===== Startup summary ====="
    echo "  APP_KEY: $(echo "$APP_KEY" | cut -c1-20)..."
    echo "  APP_URL: $APP_URL"
    echo "  APP_ENV: $APP_ENV"
    echo "  DB_HOST: $DB_HOST:$DB_PORT ($DB_DATABASE)"
    echo "  CACHE_STORE: $CACHE_STORE"
    echo "  SESSION_DRIVER: $SESSION_DRIVER"
    echo "  .env exists: $(test -f .env && echo yes || echo no)"
    echo "[entrypoint] ====="

    # Write debug.php for diagnostic access when debugging is on.
    echo "[entrypoint] Writing debug.php (APP_DEBUG=true)..."
    cat > public/debug.php << 'DEBUGEOPHP'
<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== IMAGE ===\ncommit: 3d955a2\nnginx: if_not_empty fix\ntrustProxies: at:*\n\n";
echo "=== SERVER ===\nPHP: " . PHP_VERSION . "\nContainer: " . gethostname() . "\n\n";
$key = getenv('APP_KEY') ?: '(empty)';
echo "=== APP_KEY ===\nvalue: " . substr($key, 0, 25) . "...\nvalid: " . (str_starts_with($key, 'base64:') ? 'YES' : 'NO') . "\n\n";
echo "=== HEADERS ===\n";
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_') || in_array($k, ['SERVER_NAME','SERVER_PORT','REMOTE_ADDR','REQUEST_SCHEME','HTTPS','SERVER_PROTOCOL']))
        echo "  $k: $v\n";
}
echo "\n=== PROXY HEADERS (raw) ===\n";
foreach (['HTTP_X_FORWARDED_PROTO','HTTP_X_FORWARDED_HOST','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED_PORT','HTTP_CF_VISITOR','HTTP_CF_CONNECTING_IP'] as $h) {
    $v = $_SERVER[$h] ?? null;
    if ($v === null) echo "  $h: NOT SET\n";
    elseif ($v === '') echo "  $h: EMPTY STRING\n";
    else echo "  $h: $v\n";
}
echo "\n=== PUBLIC ASSETS ===\n";
echo "manifest.json:    " . (file_exists('/var/www/html/public/build/manifest.json') ? 'YES' : 'MISSING') . "\n";
echo "filament css:     " . (file_exists('/var/www/html/public/css/filament/filament/app.css') ? 'YES' : 'MISSING') . "\n";
echo "filament js:      " . (file_exists('/var/www/html/public/js/filament/filament/app.js') ? 'YES' : 'MISSING') . "\n";
echo "manifest:         " . (file_exists('/var/www/html/public/manifest.webmanifest') ? 'YES' : 'MISSING') . "\n";
echo "sw.js:            " . (file_exists('/var/www/html/public/sw.js') ? 'YES' : 'MISSING') . "\n";
echo "icon-192:         " . (file_exists('/var/www/html/public/icons/icon-192.png') ? 'YES' : 'MISSING') . "\n";
echo "public-backup/:   " . (is_dir('/var/www/public-backup') ? 'YES' : 'MISSING') . "\n";
DEBUGEOPHP
    echo "[entrypoint] debug.php written."
else
    # In production, ensure debug.php is absent.
    rm -f public/debug.php 2>/dev/null || true
fi

echo "[entrypoint] Starting php-fpm..."
exec php-fpm
