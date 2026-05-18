# ADR 002: Test Environment and .env Management for Dusk Browser Tests

## Status
Accepted

## Context

The project maintains two environment configurations:

1. **`.env`** — Local development environment (PostgreSQL, Redis, `APP_ENV=local`)
2. **`.env.dusk.local`** — Dusk browser test environment (SQLite, `APP_ENV=testing`)

### Why two env files are needed

Dusk tests use `php artisan dusk` which runs tests against a running application server. That server reads `.env` from disk on every request. The local dev `.env` points to PostgreSQL and Redis services that may not be available during test runs. `.env.dusk.local` configures SQLite (`database/dusk.sqlite`), sync queues, and array cache so tests can operate independently.

### Historical problems

- **Session driver mismatch**: `.env.dusk.local` originally used `SESSION_DRIVER=array`. The array driver stores sessions in PHP memory, which is wiped on every `php -S` request bootstrap. This caused all multi-request flows (login → redirect) to fail with **419 Page Expired** because the CSRF token/session was lost between requests.
  - **Fix**: Changed `SESSION_DRIVER` to `database` so sessions persist in the shared `dusk.sqlite` file across `php -S` requests.

- **Accidental `.env` overwrite**: Previous agents committed testing configurations into `.env` (the file meant for local dev), breaking local PostgreSQL connectivity.
  - **Fix**: `.env` is **not tracked in git**. `.env.backup` holds the canonical local dev config. `.env.dusk.local` **is tracked** for CI/test reproducibility.

- **`.env.testing` confusion**: A file named `.env.testing` was accidentally created and committed. Laravel does **not** automatically load `.env.testing` based on `APP_ENV=testing` — it only loads `.env`. The file caused confusion about which env was active.
  - **Fix**: Removed `.env.testing`. Dusk uses `.env.dusk.local` explicitly.

- **Pest tests failing with Redis error**: When the local dev `.env` has `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis`, Pest tests fail on environments without a running Redis server because something during Laravel bootstrap attempts to connect to Redis before PHPUnit env overrides take effect.
  - **Fix**: The `run-tests.ps1` script now swaps `.env` to `.env.dusk.local` **before both Pest and Dusk tests**, ensuring a consistent testing environment.

- **Dusk tests failing after login**: Dusk tests that logged in and waited for English text (e.g., "Floor", "Billing Groups") were failing with timeouts. The screenshot revealed the pages were rendering in Portuguese ("Plano de sala").
  - **Root cause**: `tests/Helpers.php`'s `makeUser()` created users with `'preferred_language_code' => 'pt-PT'`. The `SetLocale` middleware reads the authenticated user's preferred language and switches the app locale accordingly.
  - **Fix**: Changed `makeUser()` to use `'preferred_language_code' => 'en-US'` so test users default to English, matching the test assertions.

- **Dusk RoleAccessTest failing**: `navigation shows correct items per role` asserted `assertSee('Lookup')` for the cashier nav item, but the sidebar uses `__('cashier.title')` which translates to "Checkout".
  - **Fix**: Updated the assertion to `assertSee('Checkout')`.

- **Dusk FloorWorkflowTest flaky**: `server can create billing group and see it as occupied` occasionally failed with `ElementNotInteractableException` when clicking the free range button.
  - **Fix**: Added a `->pause(300)` after `waitForText('Floor', 5)` to ensure Livewire has finished hydrating the component before interaction.

## Decision

### Use `run-tests.ps1` for all test execution

A PowerShell script (`run-tests.ps1`) handles env swapping and server lifecycle automatically:

- **Before any tests**: Backs up `.env` and swaps it with `.env.dusk.local`.
- **Pest tests**: Run with the swapped `.env` (SQLite, sync queues, array cache).
- **Dusk tests**: Start a `php -S` server on `127.0.0.1:8000`, then run `php artisan dusk`.
- **After all tests**: Restore original `.env` in a `finally` block.

### Script usage

```powershell
# Run everything
.\run-tests.ps1

# Run only Pest tests with a filter
.\run-tests.ps1 -PestOnly -PestFilter "MultilingualTest"

# Run only Dusk tests with a filter
.\run-tests.ps1 -DuskOnly -DuskFilter "ThemeAndLanguageTest"

# Run both with filters
.\run-tests.ps1 -PestFilter "LanguageSwitcherTest" -DuskFilter "ThemeAndLanguageTest"

# Show help
.\run-tests.ps1 -Help
```

### Safety guarantees

- Original `.env` is backed up to `.env.backup.tests` before any swap.
- The backup is restored in a `finally` block, so interruption (Ctrl+C) or test failure never leaves `.env` in the Dusk state.
- The script validates it is run from the project root (checks for `vendor/autoload.php` and `.env`).
- The `php -S` server is started before Dusk and killed in a `finally` block, even if tests fail.

### Dusk-specific requirements

- `.env.dusk.local` **must** have:
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=database/dusk.sqlite`
  - `SESSION_DRIVER=database` (critical — array driver breaks multi-request flows)
  - `APP_ENV=testing`
  - `APP_URL=http://127.0.0.1:8000`

- The `dusk.sqlite` file must exist and be migrated before Dusk tests run:
  ```bash
  php artisan migrate --database=sqlite --env=dusk.local
  ```

### Pest-specific notes

- Pest uses `:memory:` SQLite (configured in `phpunit.xml`), so no migration/seed is needed — `RefreshDatabase` handles it per test.
- Pest tests now run with the swapped `.env.dusk.local` to avoid Redis connection errors on environments without Redis.

## When to revisit

- If Dusk is moved to a Docker-based test runner (e.g. Selenium + dedicated test container), `.env` swap may no longer be needed.
- If Laravel introduces native `.env.dusk.local` auto-loading in a future version, the script can be simplified.

## Related

- `run-tests.ps1` (project root)
- `tests/DuskTestCase.php`
- `tests/Helpers.php`
- `phpunit.xml`
- `.env.dusk.local`
