# Localization System

## Overview

Serveo uses a **hybrid file + database** translation system. Translations are resolved by merging PHP file fallbacks with database-stored values, where **database values override file values**.

This means:
- There is **no `lang/pt-PT/` directory**. Only `lang/en/` exists as a file-level fallback.
- Both `pt-PT` and `en-US` translations are stored in the `translation_keys` table.
- All new translation strings must be added to **both** the file fallback (`lang/en/*.php`) **and** the `CoreSeeder` database entries.

## Architecture

### Components

| File | Role |
|---|---|
| `app/Domain/Localization/DatabaseTranslationLoader.php` | Extends Laravel's `FileLoader`. On every `__('namespace.key')` call, loads file translations first, then merges DB translations on top. |
| `app/Models/TranslationKey.php` | Eloquent model for the `translation_keys` table. |
| `app/Providers/AppServiceProvider.php` | Registers the custom loader and resolves the active locale per request. |
| `database/seeders/CoreSeeder.php` | Seeds all translation strings for both `pt-PT` and `en-US` into the database. |

### Locale Resolution

`AppServiceProvider::resolveLocale()` determines the active locale in this priority order:

1. **Authenticated user preference** — `User.preferred_language_code`
2. **Session locale** — set by `LanguageSwitcher` before login
3. **Config default** — `config('app.locale')`, defaults to `pt-PT`

### Translation Loading Flow

When `__('dashboard.title')` is called:

```
1. FileLoader loads lang/en/dashboard.php → ['title' => 'Dashboard']
2. DatabaseTranslationLoader queries:
   translation_keys WHERE language_code = 'pt-PT'
     AND translation_namespace = 'dashboard'
     AND translation_key = 'title'
     AND is_active = true
   → 'Painel principal'
3. array_merge(fileTranslations, dbTranslations)
   → DB value overrides file value
4. Result: 'Painel principal' (for pt-PT locale)
```

### Caching

Database translations are cached for **5 minutes** per locale/namespace/group combination:

```
Cache key: translations:{locale}:{namespace}:{group}
Example:   translations:pt-PT:*:dashboard
```

The cache is flushed when `LanguageSwitcher::setLocale()` is called (via `Cache::flush()`).

## Adding New Translations

When adding new UI strings, you must update **three places**:

### 1. File fallback (`lang/en/{namespace}.php`)

```php
// lang/en/dashboard.php
return [
    'title' => 'Dashboard',
    'new_key' => 'New string',
];
```

This file serves as the English fallback. Create a new file per namespace if it doesn't exist.

### 2. CoreSeeder — Portuguese entries

```php
// database/seeders/CoreSeeder.php
['pt-PT', 'dashboard', 'new_key', 'Nova string'],
```

### 3. CoreSeeder — English entries

```php
// database/seeders/CoreSeeder.php
['en-US', 'dashboard', 'new_key', 'New string'],
```

### Seeding format

Each entry in `CoreSeeder` is a 4-element array:

```php
[language_code, translation_namespace, translation_key, translation_value]
```

- `language_code`: `pt-PT` or `en-US`
- `translation_namespace`: Matches the file namespace (e.g., `dashboard`, `app`, `floor`)
- `translation_key`: The key used in `__('namespace.key')`
- `translation_value`: The translated string

### Verification

Run `php scripts/check-translations.php` to verify translation completeness. This script scans all PHP/Blade files for `__()`, `trans()`, and `Lang::get()` calls and verifies every key exists in both `lang/*.php` files and `CoreSeeder` for all discovered locales.

## Translation Table Schema

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `language_code` | string | `pt-PT` or `en-US` |
| `translation_namespace` | string | e.g., `dashboard`, `app`, `floor` |
| `translation_key` | string | e.g., `title`, `floor_tile` |
| `translation_value` | text | The translated string |
| `is_active` | boolean | Inactive entries are excluded from loading |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Uniqueness constraint: `Unique(language_code, translation_namespace, translation_key)`

## Common Mistakes to Avoid

- **Creating `lang/pt-PT/` files** — This does nothing. Portuguese translations live in the database.
- **Only adding to `lang/en/`** — The file is just a fallback. Without CoreSeeder entries, the DB has no translation and the key returns untranslated.
- **Only adding to CoreSeeder** — Without the file fallback, tests using the file loader (e.g., Pest with in-memory SQLite before seeding) may fail.
- **Forgetting `is_active`** — The loader filters `WHERE is_active = true`. Inactive entries are silently excluded.
