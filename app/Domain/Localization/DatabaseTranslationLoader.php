<?php

namespace App\Domain\Localization;

use App\Models\TranslationKey;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\FileLoader;

class DatabaseTranslationLoader extends FileLoader
{
    public function load($locale, $group, $namespace = null): array
    {
        $fileTranslations = $this->loadFileWithFallback($locale, $group, $namespace);

        $dbTranslations = $this->loadFromDatabase($locale, $group, $namespace);

        return array_merge($fileTranslations, $dbTranslations);
    }

    /**
     * Load file translations with locale normalization.
     *
     * Tries exact locale first, then normalized variants (underscore, short form),
     * then the configured fallback locale. This bridges the gap between
     * Laravel's locale format (e.g. "pt-PT") and directories shipped by
     * packages like Filament (e.g. "pt").
     */
    private function loadFileWithFallback(string $locale, string $group, ?string $namespace): array
    {
        $fileTranslations = parent::load($locale, $group, $namespace);

        if (! empty($fileTranslations)) {
            return $fileTranslations;
        }

        // Normalize: try underscore variant (pt-PT → pt_PT)
        if (str_contains($locale, '-')) {
            $underscored = str_replace('-', '_', $locale);
            $fileTranslations = parent::load($underscored, $group, $namespace);
        }

        if (! empty($fileTranslations)) {
            return $fileTranslations;
        }

        // Normalize: try short form (pt_PT → pt, en_US → en)
        $shortLocale = explode('_', str_replace('-', '_', $locale))[0];
        if ($shortLocale !== $locale) {
            $fileTranslations = parent::load($shortLocale, $group, $namespace);
        }

        return $fileTranslations;
    }

    private function loadFromDatabase(string $locale, string $group, ?string $namespace = null): array
    {
        $ns = $namespace ?? '*';
        $cacheKey = "translations:{$locale}:{$ns}:{$group}";

        return Cache::remember($cacheKey, 300, function () use ($locale, $group, $ns) {
            if (! Schema::hasTable('translation_keys')) {
                return [];
            }

            try {
                // Load fallback locale results first so exact locale can override
                $shortLocale = explode('_', str_replace('-', '_', $locale))[0];
                $hasFallback = $shortLocale !== $locale;

                $translations = [];

                if ($hasFallback) {
                    $fallbackQuery = TranslationKey::where('language_code', $shortLocale)
                        ->where('is_active', true);
                    $this->applyQueryFilters($fallbackQuery, $group, $ns);
                    foreach ($fallbackQuery->get() as $row) {
                        $this->mergeRow($translations, $row, $group);
                    }
                }

                // Exact locale overrides fallback
                $exactQuery = TranslationKey::where('language_code', $locale)
                    ->where('is_active', true);
                $this->applyQueryFilters($exactQuery, $group, $ns);
                foreach ($exactQuery->get() as $row) {
                    $this->mergeRow($translations, $row, $group);
                }

                return $translations;
            } catch (QueryException) {
                return [];
            }
        });
    }

    /**
     * Apply namespace/group filters to a translation query.
     */
    private function applyQueryFilters($query, string $group, string $ns): void
    {
        if ($group === '*') {
            $query->where('translation_namespace', '*');
        } elseif ($ns === '*') {
            $query->where('translation_namespace', $group);
        } else {
            $query->where('translation_namespace', $ns)
                ->where('translation_key', 'like', "{$group}.%");
        }
    }

    /**
     * Merge a translation row into the translations array.
     * For JSON-style groups (*), keys are flat. For namespaced groups, dots are nested.
     */
    private function mergeRow(array &$translations, $row, string $group): void
    {
        if ($group === '*') {
            $translations[$row->translation_key] = $row->translation_value;
        } else {
            data_set($translations, $row->translation_key, $row->translation_value);
        }
    }

    public function addNamespace($namespace, $hint): void
    {
        parent::addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        parent::addJsonPath($path);
    }

    public function namespaces(): array
    {
        return parent::namespaces();
    }
}
