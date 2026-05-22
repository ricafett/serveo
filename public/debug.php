<?php
/**
 * Standalone debug script — does NOT boot Laravel.
 * Access via https://serveo.rica.ovh/debug.php
 * Remove after debugging!
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== SERVER INFO ===\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Time: " . date('Y-m-d H:i:s T') . "\n";
echo "Container: " . gethostname() . "\n";

echo "\n=== APP_KEY ===\n";
$key = getenv('APP_KEY') ?: '(empty)';
echo "env APP_KEY: " . substr($key, 0, 20) . "..." . (strlen($key) > 10 ? ' (' . strlen($key) . ' chars)' : '') . "\n";
echo "APP_KEY valid: " . (str_starts_with($key, 'base64:') ? 'YES (has base64: prefix)' : 'NO (missing base64: prefix)') . "\n";

echo "\n=== .env FILE ===\n";
$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    echo "EXISTS\n";
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), 'APP_KEY=') || str_starts_with(trim($line), 'APP_URL=') || str_starts_with(trim($line), 'DB_') || str_starts_with(trim($line), 'CACHE_')) {
            echo "  " . trim($line) . "\n";
        }
    }
} else {
    echo "MISSING — key:generate cannot write!\n";
}

echo "\n=== REQUEST HEADERS (as seen by PHP-FPM) ===\n";
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_') || in_array($k, ['SERVER_NAME', 'SERVER_PORT', 'SERVER_ADDR', 'REMOTE_ADDR', 'REQUEST_SCHEME', 'HTTPS', 'SERVER_PROTOCOL'])) {
        echo "  $k: $v\n";
    }
}

echo "\n=== X-FORWARDED-* RAW ===\n";
foreach (['HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_PORT', 'HTTP_CF_VISITOR', 'HTTP_CF_CONNECTING_IP'] as $h) {
    $v = $_SERVER[$h] ?? null;
    if ($v === null) echo "  $h: NOT SET (neither proxy nor nginx sent it)\n";
    elseif ($v === '') echo "  $h: EMPTY STRING (nginx sent it empty — THIS IS BAD)\n";
    else echo "  $h: $v\n";
}

echo "\n=== DATABASE CONNECTIVITY ===\n";
try {
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST') ?: 'postgres', getenv('DB_PORT') ?: '5432', getenv('DB_DATABASE') ?: 'serveo');
    $pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'serveo', getenv('DB_PASSWORD') ?: 'serveo', [PDO::ATTR_TIMEOUT => 3]);
    echo "PostgreSQL: CONNECTED ($dsn)\n";
} catch (Exception $e) {
    echo "PostgreSQL: FAILED — " . $e->getMessage() . "\n";
}

echo "\n=== REDIS CONNECTIVITY ===\n";
try {
    $redis = new Redis();
    $redis->connect(getenv('REDIS_HOST') ?: 'redis', (int)(getenv('REDIS_PORT') ?: 6379), 2);
    echo "Redis: CONNECTED — " . $redis->ping() . "\n";
} catch (Exception $e) {
    echo "Redis: FAILED — " . $e->getMessage() . "\n";
}

echo "\n=== IMAGE VERSION ===\n";
echo "Build commit: aa95756 (entrypoint fix)\n";
echo "Nginx config: X-Forwarded-* with if_not_empty\n";
echo "TrustProxies: at: '*'\n";
