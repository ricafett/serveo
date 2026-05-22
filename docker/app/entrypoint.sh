#!/bin/sh
set -e

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

echo "[entrypoint] ===== Startup summary ====="
echo "  APP_KEY: $(echo "$APP_KEY" | cut -c1-20)..."
echo "  APP_URL: $APP_URL"
echo "  APP_ENV: $APP_ENV"
echo "  APP_DEBUG: $APP_DEBUG"
echo "  DB_HOST: $DB_HOST:$DB_PORT ($DB_DATABASE)"
echo "  CACHE_STORE: $CACHE_STORE"
echo "  SESSION_DRIVER: $SESSION_DRIVER"
echo "  .env exists: $(test -f .env && echo yes || echo no)"
echo "  debug.php: $(test -f public/debug.php && echo yes || echo no)"
echo "[entrypoint] ====="

# Ensure debug.php is available even if the app-public volume is stale.
# The volume mounts over public/ so we write directly at startup.
if [ ! -f public/debug.php ]; then
    echo "[entrypoint] Writing debug.php into mounted volume..."
    cat > public/debug.php << 'DEBUGEOPHP'
<?php
header('Content-Type: text/plain; charset=utf-8');
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
    elseif ($v === '') echo "  $h: EMPTY STRING (BAD - nginx bug)\n";
    else echo "  $h: $v\n";
}
echo "\n=== DB ===\n";
try { new PDO("pgsql:host=".getenv('DB_HOST').";port=".getenv('DB_PORT').";dbname=".getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT=>3]); echo "CONNECTED\n"; } catch(Exception $e) { echo "FAILED: ".$e->getMessage()."\n"; }
echo "\n=== IMAGE ===\ncommit: 4082dd1 + build-backup\nnginx: X-Forwarded-* with if_not_empty\n";
DEBUGEOPHP
    echo "[entrypoint] debug.php written."
fi

# Restore Vite build assets from backup if the volume is stale.
# The app-public named volume mounts over public/ and hides the image's
# public/build/ directory. This backup is outside the volume mount.
if [ ! -f public/build/manifest.json ] && [ -d /var/www/build-backup ]; then
    echo "[entrypoint] Restoring public/build/ from backup (volume was stale)..."
    rm -rf public/build 2>/dev/null || true
    cp -a /var/www/build-backup public/build
    echo "[entrypoint] public/build/ restored."
fi

# Restore Filament assets if the volume is stale.
if [ ! -f public/css/filament/filament/app.css ] && [ -d /var/www/css-backup ]; then
    echo "[entrypoint] Restoring Filament assets from backup..."
    for dir in css js fonts; do
        backup="/var/www/${dir}-backup"
        if [ -d "$backup" ] && [ ! -d "public/$dir/filament" ]; then
            mkdir -p "public/$dir"
            cp -a "$backup/filament" "public/$dir/filament" 2>/dev/null || true
        fi
    done
    echo "[entrypoint] Filament assets restored."
fi

echo "[entrypoint] Starting php-fpm..."
exec php-fpm
