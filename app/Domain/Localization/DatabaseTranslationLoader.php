<?php

namespace App\Domain\Localization;

use App\Models\TranslationKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\FileLoader;

class DatabaseTranslationLoader extends FileLoader
{
    public function load($locale, $group, $namespace = null): array
    {
        $fileTranslations = parent::load($locale, $group, $namespace);

        $dbTranslations = $this->loadFromDatabase($locale, $group, $namespace);

        return array_merge($fileTranslations, $dbTranslations);
    }

    private function loadFromDatabase(string $locale, string $group, ?string $namespace = null): array
    {
        $ns = $namespace ?? '*';
        $cacheKey = "translations:{$locale}:{$ns}:{$group}";

        return Cache::remember($cacheKey, 300, function () use ($locale, $group, $ns) {
            $query = TranslationKey::where('language_code', $locale)
                ->where('is_active', true);

            if ($group === '*') {
                // Raw string / JSON-style translations (e.g. __('Hello'))
                $query->where('translation_namespace', '*');
                $rows = $query->get();

                $translations = [];
                foreach ($rows as $row) {
                    $translations[$row->translation_key] = $row->translation_value;
                }

                return $translations;
            }

            if ($ns === '*') {
                // Default namespace: in our data model the group name IS the namespace
                $query->where('translation_namespace', $group);
            } else {
                // Explicit namespace: look for namespaced keys with group prefix
                $query->where('translation_namespace', $ns)
                    ->where('translation_key', 'like', "{$group}.%");
            }

            $rows = $query->get();

            $translations = [];
            foreach ($rows as $row) {
                data_set($translations, $row->translation_key, $row->translation_value);
            }

            return $translations;
        });
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
