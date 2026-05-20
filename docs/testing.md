# Testing Guide

This document describes the testing setup and conventions for the Serveo application.

## Test Stack

- **Pest** (v4) — primary test runner for unit and feature tests.
- **Laravel Dusk** (v8) — end-to-end browser tests covering full user journeys.
- **PHPUnit** (v11) — underlying framework for Pest and Dusk.

## Running Tests

### Timeout Warning

**The full test suite requires a large timeout — recommend 20 minutes (1200000 ms).**

Dusk browser tests with Filament admin pages are slow (~2–10s each) because the single-threaded PHP built-in server serializes asset requests. The full suite (translation check + Pest + Dusk) can easily exceed 10 minutes. Agent tasks and CI pipelines must allow at least 20 minutes or the test runner will be killed mid-Dusk with a false failure.

### Automated Test Runner (Recommended)

Use the provided PowerShell script to run the full suite with automatic `.env` management:

```powershell
.\run-tests.ps1                     # Run translation check, Pest, and Dusk
.\run-tests.ps1 -PestOnly           # Run only Pest tests
.\run-tests.ps1 -DuskOnly           # Run only Dusk tests
.\run-tests.ps1 -NoTranslationCheck # Skip translation check
.\run-tests.ps1 -PestFilter "MultilingualTest" -DuskFilter "ThemeAndLanguageTest"
```

The script automatically swaps `.env` with `.env.dusk.local` before tests and restores it after, even on failure or interruption. It also starts the `php -S` server required by Dusk.

### Translation Completeness Check

A standalone script verifies that every translation key referenced in the codebase exists in `lang/*/*.php` **and** in `CoreSeeder.php` for every locale:

```bash
php scripts/check-translations.php              # Check all locales + CoreSeeder (default)
php scripts/check-translations.php --no-strict  # Skip CoreSeeder parity check
php scripts/check-translations.php --locale=en  # Check a single locale only
```

This is run automatically by `run-tests.ps1` before Pest and Dusk. It catches:

- Missing namespaced keys (e.g. `app.status_closed` used in code but absent from `lang/en/app.php`)
- Bare English strings passed to `__()` without a namespace (e.g. `__('Unauthorized')`)
- CoreSeeder mismatches (default)

### Feature Tests (Pest)

```bash
./vendor/bin/pest                    # All tests
./vendor/bin/pest tests/Feature      # Feature tests only
./vendor/bin/pest tests/Unit         # Unit tests only
```

**Note**: Running Pest directly may fail if your local `.env` uses Redis/PostgreSQL and those services are not available. Use `run-tests.ps1` for a consistent testing environment.

### Dusk Browser Tests

**Always run Dusk tests via `run-tests.ps1` or manually with `.env.dusk.local` active:**

```bash
# Manual approach (not recommended — use run-tests.ps1 instead)
cp .env.dusk.local .env
php -S 127.0.0.1:8000 -t public &    # Start dev server
php artisan dusk                     # Run Dusk tests
```

Running `./vendor/bin/pest tests/Browser/` directly will fail because `phpunit.xml` overrides `DB_DATABASE` to `:memory:`, which breaks table truncation between tests.

### Environment Requirements for Dusk

1. **Google Chrome** or **Microsoft Edge** must be installed.
2. **ChromeDriver** is auto-managed by `laravel/dusk` (`vendor/laravel/dusk/bin/chromedriver-win.exe` on Windows).
3. A local PHP server must be running on `127.0.0.1:8000` with `.env.dusk.local` active (the script handles this automatically).

### Environment File Separation (Critical)

The repository maintains two environment configurations. **They must not be mixed up.**

| File | Purpose | Tracked in git |
|---|---|---|
| `.env` | **Local development** — PostgreSQL, Redis, `APP_ENV=local` | **No** (`.gitignore`d) |
| `.env.dusk.local` | **Test runner** — SQLite, `APP_ENV=testing`, sync queues | **Yes** |

**Why this matters**: `.env` is read by the application server on every request. If `.env` accidentally contains the Dusk test config (e.g. `DB_CONNECTION=sqlite` pointing to `database/dusk.sqlite`), your local dev app and Dusk tests will share the **same SQLite file**. Dusk truncates operational tables including `users` between every test, so after running Dusk your dev login will break with *"credentials do not match our records"*.

**Rule**: `.env` must always stay as your local dev config. `run-tests.ps1` temporarily swaps it with `.env.dusk.local` and restores it afterward.

### Dusk Environment Files

- `.env.dusk.local` — used by Dusk tests. Points to `database/dusk.sqlite`, uses `database` session driver, `sync` queue.
- `database/dusk.sqlite` — shared SQLite database for the test runner and the web server.

**Important**: `php artisan dusk` does **not** start a web server automatically on Windows. The `run-tests.ps1` script starts `php -S 127.0.0.1:8000 -t public` before running Dusk and kills it afterwards.

## Test Organization

```
tests/
├── Browser/              # Dusk E2E tests
│   ├── Admin/            # Filament admin panel tests
│   ├── Auth/             # Login, logout, role access
│   ├── Cashier/          # Lookup, checkout, reprint
│   ├── CrossCutting/     # Theme, language, mobile nav
│   └── Server/           # Floor, billing group, order entry
├── Feature/              # Pest feature tests (API, services)
├── Unit/                 # Pest unit tests
├── DuskTestCase.php      # Base class for all Dusk tests
└── Pest.php              # Pest bootstrap (binds DuskTestCase for Browser/)
```

## DuskTestCase Helpers

### Table Truncation

`setUp()` truncates operational tables between tests to keep state clean without dropping the schema (required because the server runs in a separate process):

- `accounting_exports`, `audit_events`, `billing_documents`, `billing_groups`
- `cashier_printer_assignments`, `occupied_zones`, `order_headers`, `order_items`
- `payment_records`, `print_jobs`, `production_tickets`, `service_sessions`
- `model_has_permissions`, `model_has_roles`, `users`

Reference data (roles, permissions, venue, menu, printers) is preserved.

### Boot Scenario

`bootScenario()` seeds baseline reference data before each test:
- Roles and permissions (via `spatie/laravel-permission`)
- Venue, sections, rows, seat pairs
- Active service session
- Menu categories and items
- Printers and printer routes

### User Factory

`makeUser(string $roleName)` creates a user with the given role and a random username.

Example:
```php
$server = makeUser('SERVER');
$cashier = makeUser('CASHIER');
$admin = makeUser('ADMIN');
```

### Session Cookie Clearing

Dusk reuses the browser instance across the entire suite. Session cookies from earlier tests can leak into later tests. **Every test that logs in must start with:**

```php
$browser->driver->manage()->deleteAllCookies();
```

This is already applied to all existing Dusk tests.

## Dusk Selector Conventions

### Filament v3 Login Form

Filament inputs use `id` attributes (not `name`). Use attribute selectors:

```php
$browser->type('input[id="form.email"]', $admin->email)
        ->type('input[id="form.password"]', 'secret')
        ->press('Sign in');
```

### Livewire v3 Operational Forms

Custom Livewire components do not auto-generate `name` or `id` on inputs. **Input `id` attributes were added to Blade views specifically for testability.** Use CSS ID selectors:

```php
$browser->type('#cover-count', 4)
        ->type('#payment-amount', 5.00)
        ->type('#search', 'G-001')
        ->check('#show-closed');
```

### CSS `text-transform: uppercase`

Labels like `Charges`, `Paid`, and `Balance` are rendered with Tailwind's `uppercase` class. Dusk's `waitForText` and `assertSee` check **visible rendered text**, so assertions must use the uppercase form:

```php
$browser->waitForText('CHARGES', 10)
        ->waitForText('PAID', 10)
        ->waitForText('BALANCE', 10);
```

## Known Limitations

1. **Filament admin tests are slow** (~2–10s each) because the admin panel loads heavy JavaScript bundles. The single-threaded PHP built-in server serializes asset requests. Tests use `waitForLocation` with generous timeouts instead of `waitForText('Dashboard')`.
2. **SERVER role lacks `billing_group.reopen`** in the current seed data. The spec says servers may reopen, but the seed assignment only grants this to CASHIER and ADMIN. Dusk tests for closed-group detail do not assert the presence of a `Reopen` button for SERVER users.
3. **Order entry for closed groups** returns the order entry page with a `Closed` badge and a disabled submit button, rather than a 403 response. This is the current implementation behavior.

## Adding New Dusk Tests

1. Place the file under `tests/Browser/{Domain}/`.
2. Extend `DuskTestCase` implicitly via Pest (already bound in `Pest.php`).
3. Call `$this->scenario()` in `beforeEach` or at the start of each test.
4. Use `$browser->driver->manage()->deleteAllCookies()` before every login sequence.
5. Use `waitForText` instead of `assertSee` for Livewire-hydrated content.
6. Run with `php artisan dusk --filter=YourTest`.

## Adding Input IDs for Testability

When adding new form inputs to Livewire Blade views, add an explicit `id` attribute so Dusk can select the input reliably:

```blade
<input id="my-field" type="text" wire:model="myField">
```

Then reference it in tests as `#my-field`.
