# Testing Guide

This document describes the testing setup and conventions for the Serveo application.

## Test Stack

- **Pest** (v4) — primary test runner for unit and feature tests.
- **Laravel Dusk** (v8) — end-to-end browser tests covering full user journeys.
- **PHPUnit** (v11) — underlying framework for Pest and Dusk.

## Running Tests

### Feature Tests (Pest)

```bash
./vendor/bin/pest                    # All tests
./vendor/bin/pest tests/Feature      # Feature tests only
./vendor/bin/pest tests/Unit         # Unit tests only
```

### Dusk Browser Tests

**Always run Dusk tests via the Artisan command** so `.env.dusk.local` is loaded correctly:

```bash
php artisan dusk                     # Full suite
php artisan dusk --filter=LoginTest  # Specific test class
```

Running `./vendor/bin/pest tests/Browser/` directly will fail because `phpunit.xml` overrides `DB_DATABASE` to `:memory:`, which breaks table truncation between tests.

### Environment Requirements for Dusk

1. **Google Chrome** or **Microsoft Edge** must be installed.
2. **ChromeDriver** is auto-managed by `laravel/dusk` (`vendor/laravel/dusk/bin/chromedriver-win.exe` on Windows).
3. A local PHP server must be running with `.env.testing`:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
   The `.env.testing` file ensures the test runner and the server share the same SQLite file (`database/dusk.sqlite`).

### Dusk Environment Files

- `.env.dusk.local` — used by `php artisan dusk`. Points to `database/dusk.sqlite`, uses `array` session driver, `sync` queue.
- `.env.testing` — a copy of `.env.dusk.local` so the manual PHP server uses the same database.
- `database/dusk.sqlite` — shared SQLite database for the test runner and the web server.

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
