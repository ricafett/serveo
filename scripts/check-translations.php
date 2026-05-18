<?php

/**
 * Translation completeness checker for Serveo.
 *
 * Scans PHP source and Blade views for translation calls, then verifies
 * every key exists in the lang/ files AND in CoreSeeder for every locale.
 *
 * Usage:
 *   php scripts/check-translations.php              # Check all locales + CoreSeeder (default)
 *   php scripts/check-translations.php --no-strict  # Skip CoreSeeder parity check
 *   php scripts/check-translations.php --locale=en  # Check a single locale only
 */

$baseDir = realpath(__DIR__ . '/..');
$exitCode = 0;

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------
$strict = true; // default: require CoreSeeder parity for all locales
$localeArg = null;
foreach ($argv as $arg) {
    if ($arg === '--no-strict') {
        $strict = false;
    }
    if (str_starts_with($arg, '--locale=')) {
        $localeArg = substr($arg, 9);
    }
}

// ---------------------------------------------------------------------------
// Load translation files
// ---------------------------------------------------------------------------
function loadTranslations(string $baseDir, string $locale): array
{
    $translations = [];
    $langDir = $baseDir . '/lang/' . $locale;

    if (!is_dir($langDir)) {
        return $translations;
    }

    foreach (glob($langDir . '/*.php') as $file) {
        $domain = basename($file, '.php');
        $translations[$domain] = include $file;
    }

    return $translations;
}

function discoverLocales(string $baseDir): array
{
    $locales = [];
    $langDir = $baseDir . '/lang';
    if (!is_dir($langDir)) {
        return $locales;
    }
    foreach (glob($langDir . '/*', GLOB_ONLYDIR) as $dir) {
        $locales[] = basename($dir);
    }
    return $locales;
}

function loadSeederTranslations(string $baseDir): array
{
    $seederPath = $baseDir . '/database/seeders/CoreSeeder.php';
    $content = file_get_contents($seederPath);
    $translations = [];

    // Match: ['en-US', 'app', 'key', 'value'],
    if (preg_match_all("/\['([a-zA-Z-]+)',\s*'([a-zA-Z0-9_]+)',\s*'([a-zA-Z0-9_]+)',\s*'([^']+)'\],/", $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            [$_, $lang, $domain, $key, $value] = $m;
            $translations[$lang][$domain][$key] = $value;
        }
    }

    return $translations;
}

function mapLocaleToSeeder(string $locale): string
{
    return match ($locale) {
        'en' => 'en-US',
        'pt' => 'pt-PT',
        default => $locale,
    };
}

// ---------------------------------------------------------------------------
// Scan for translation calls
// ---------------------------------------------------------------------------
function stripPhpComments(string $code): string
{
    $tokens = token_get_all($code);
    $result = '';
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
            $result .= $token[1];
        } else {
            $result .= $token;
        }
    }
    return $result;
}

function scanDirectory(string $dir, array &$found): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $rawContent = file_get_contents($file->getPathname());
        $content = stripPhpComments($rawContent);
        $relPath = str_replace(realpath(__DIR__ . '/..'), '', realpath($file->getPathname()));

        // __(), trans(), Lang::get() with namespaced keys: domain.key
        $pattern = '/(?:__|trans|Lang::get)\([\'"]([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)[\'"]\)/';
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $found[] = [
                    'domain' => $m[1],
                    'key' => $m[2],
                    'file' => $relPath,
                ];
            }
        }

        // Bare __() calls with English text (no namespace dot)
        $barePattern = '/__\([\'"]([A-Z][a-zA-Z\s]+)[\'"]\)/';
        if (preg_match_all($barePattern, $content, $bareMatches, PREG_SET_ORDER)) {
            foreach ($bareMatches as $bm) {
                $found[] = [
                    'domain' => '_bare',
                    'key' => $bm[1],
                    'file' => $relPath,
                ];
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
$locales = $localeArg ? [$localeArg] : discoverLocales($baseDir);
$seederTranslations = loadSeederTranslations($baseDir);

$found = [];
scanDirectory($baseDir . '/app', $found);
scanDirectory($baseDir . '/resources/views', $found);

// Deduplicate
$byDomainKey = [];
foreach ($found as $item) {
    $dk = $item['domain'] . '.' . $item['key'];
    if (!isset($byDomainKey[$dk])) {
        $byDomainKey[$dk] = $item;
    }
}

foreach ($locales as $locale) {
    echo "=== Checking locale: {$locale} ===\n\n";

    $fileTranslations = loadTranslations($baseDir, $locale);

    $missingInFiles = [];
    $missingInSeeder = [];
    $bareStrings = [];

    foreach ($byDomainKey as $item) {
        $domain = $item['domain'];
        $key = $item['key'];

        if ($domain === '_bare') {
            $bareStrings[] = $item;
            continue;
        }

        // Check file translations
        if (!isset($fileTranslations[$domain][$key])) {
            $missingInFiles[] = $item;
        }

        // Check seeder translations (strict mode)
        if ($strict) {
            $seederLocale = mapLocaleToSeeder($locale);
            if (!isset($seederTranslations[$seederLocale][$domain][$key])) {
                $missingInSeeder[] = $item;
            }
        }
    }

    $localeHasIssues = false;

    if (!empty($missingInFiles)) {
        echo "Missing from lang/{$locale}/*.php:\n";
        foreach ($missingInFiles as $item) {
            echo "  {$item['domain']}.{$item['key']}  (used in {$item['file']})\n";
        }
        echo "\n";
        $localeHasIssues = true;
        $exitCode = 1;
    }

    if ($strict && !empty($missingInSeeder)) {
        echo "Missing from CoreSeeder:\n";
        foreach ($missingInSeeder as $item) {
            echo "  {$item['domain']}.{$item['key']}  (used in {$item['file']})\n";
        }
        echo "\n";
        $localeHasIssues = true;
        $exitCode = 1;
    }

    if (!empty($bareStrings)) {
        echo "Bare English strings (no translation namespace):\n";
        foreach ($bareStrings as $item) {
            echo "  '{$item['key']}'  (in {$item['file']})\n";
        }
        echo "\n";
        $localeHasIssues = true;
        $exitCode = 1;
    }

    if (!$localeHasIssues) {
        echo "All translation keys present for locale '{$locale}'.\n\n";
    }
}

if ($exitCode === 0) {
    echo "Translation check passed.\n";
} else {
    echo "Translation check FAILED.\n";
}

exit($exitCode);
