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
            $rows = TranslationKey::where('language_code', $locale)
                ->where('translation_namespace', $ns)
                ->where('translation_key', 'like', "{$group}.%")
                ->where('is_active', true)
                ->get();

            $translations = [];
            foreach ($rows as $row) {
                $key = substr($row->translation_key, strlen($group) + 1);
                data_set($translations, $key, $row->translation_value);
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
