# ADR 002: Test Environment and .env Management for Dusk Browser Tests

## Status
Accepted

## Context

The project maintains two environment configurations:

1. **`.env`** — Local development environment (PostgreSQL, Redis, `APP_ENV=local`)
2. **`.env.dusk.local`** — Dusk browser test environment (SQLite, `APP_ENV=testing`)

### Why two env files are needed

Dusk tests use `php artisan dusk` which starts a `php -S` development server in a separate process. That server reads `.env` from disk on every request. The local dev `.env` points to PostgreSQL and Redis services that may not be available during Dusk runs. `.env.dusk.local` configures SQLite (`database/dusk.sqlite`) and database-backed sessions so the Dusk server can operate independently.

### Historical problems

- **Session driver mismatch**: `.env.dusk.local` originally used `SESSION_DRIVER=array`. The array driver stores sessions in a PHP in-memory array, which is wiped on every `php -S` request bootstrap. This caused all multi-request flows (login → redirect) to fail with **419 Page Expired** because the CSRF token/session was lost between requests.
  - **Fix**: Changed `SESSION_DRIVER` to `database` so sessions persist in the shared `dusk.sqlite` file across `php -S` requests.

- **Accidental `.env` overwrite**: Previous agents committed testing configurations into `.env` (the file meant for local dev), breaking local PostgreSQL connectivity.
  - **Fix**: `.env` is **not tracked in git**. `.env.backup` holds the canonical local dev config. `.env.dusk.local` **is tracked** for CI/test reproducibility.

- **`.env.testing` confusion**: A file named `.env.testing` was accidentally created and committed. Laravel does **not** automatically load `.env.testing` based on `APP_ENV=testing` — it only loads `.env`. The file caused confusion about which env was active.
  - **Fix**: Removed `.env.testing`. Dusk uses `.env.dusk.local` explicitly.

## Decision

### Use `run-tests.ps1` for all test execution

A PowerShell script (`run-tests.ps1`) handles env swapping automatically:

- **Pest tests** run with the current `.env` (no swap needed; Pest uses `phpunit.xml` in-memory env vars).
- **Dusk tests** trigger an automatic backup/swap/restore of `.env` with `.env.dusk.local`.

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

### Dusk-specific requirements

- `.env.dusk.local` **must** have:
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=database/dusk.sqlite`
  - `SESSION_DRIVER=database` (critical — array driver breaks multi-request flows)
  - `APP_ENV=testing`
  - `APP_URL=http://127.0.0.1:8000`

- The `dusk.sqlite` file must exist and be migrated/seeded before Dusk tests run:
  ```bash
  php artisan migrate --database=sqlite --env=dusk.local
  php artisan db:seed --class=CoreSeeder --database=sqlite --env=dusk.local
  ```

### Pest-specific notes

- Pest uses `:memory:` SQLite (configured in `phpunit.xml`), so no migration/seed is needed — `RefreshDatabase` handles it per test.
- Pest tests do **not** need `.env` swap because PHPUnit overrides env vars in-memory.

## When to revisit

- If Dusk is moved to a Docker-based test runner (e.g. Selenium + dedicated test container), `.env` swap may no longer be needed.
- If Laravel introduces native `.env.dusk.local` auto-loading in a future version, the script can be simplified.

## Related

- `run-tests.ps1` (project root)
- `tests/DuskTestCase.php`
- `phpunit.xml`
- `.env.dusk.local`
- GitHub Issue #32 (language picker UI — required Dusk env fixes)
