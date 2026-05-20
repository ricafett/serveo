# Serveo

Serveo is a local-network service and billing app for recurring food events and restaurant-style operations.

It is built for live floor service with servers, cashiers, kitchen/bar ticket printing, billing groups across seat ranges, internal billing, and full event traceability.

## What Serveo is

Serveo is designed for environments where service happens across physical seating zones rather than simple table numbers.

The MVP focuses on:

- Floor and seating management by section, row, seat, and seat pair.
- Billing groups that can span multiple occupied zones.
- Server-led order entry.
- Kitchen and bar paper ticket printing.
- Cashier-led bill printing and partial payment tracking.
- Admin configuration for layout, printers, routing, statuses, and users.
- Full event logging and accounting export.

## MVP principles

Serveo is intentionally scoped as an operational MVP.

Key principles:

- Local network only.
- Mobile-friendly web app; full PWA behavior is optional.
- Kitchen and bar are non-interactive in MVP and receive printed tickets only.
- Billing is internal-only in MVP.
- Payment processing is out of scope.
- Reliability, traceability, and print correctness matter more than feature breadth.

## Roles

Serveo supports these MVP roles:

- **Server** — handles seating, billing-group creation, zone assignment, and order entry.
- **Cashier** — handles billing lookup, bill printing, reprints, and partial payments.
- **Admin** — configures the system, manages routes and printers, reviews logs, and generates exports.
- **Kitchen/Bar output** — non-interactive output roles that receive printed tickets.

## Core workflows

Main workflows supported by the MVP:

1. Open a billing group on one or more seat-pair ranges.
2. Add orders at billing-group or occupied-zone level.
3. Route food and drinks to the correct printers.
4. Print kitchen and bar tickets.
5. Print internal customer bills from cashier stations.
6. Record partial payments and reopen groups where allowed.
7. Keep a full audit trail of operational and billing actions.

## Printing model

Printing is a first-class subsystem in Serveo.

The MVP printing model is:

- One kitchen printer.
- One bar printer.
- One printer per cashier.
- 80mm thermal printers with cutter.
- Direct LAN printing preferred.
- USB printing supported through a local print agent/service when needed.
- Failed print jobs remain queued and visible.
- Void slips go to the same destination as the original production ticket.

## Architecture

Serveo uses a modular monolith architecture.

Selected stack:

- Laravel
- Livewire
- Filament
- Blade
- Tailwind CSS
- PostgreSQL
- Redis-backed queues preferred
- Docker Compose

High-level architecture rules:

- Backend-owned business logic.
- Backend-owned print queue and printer routing.
- No microservices for MVP.
- No browser-as-source-of-truth printing.
- Clear module boundaries for AI-agent-led development.

Architectural decisions are documented inline in `event-restaurant-spec.md` and refined in `business-rules.md`. Deployment topology, build, and operational details live in `deployment.md`.

## Documentation pack

The repository is driven by a documentation-first workflow.

Important project documents include:

- `product-scope.md`
- `event-restaurant-spec.md`
- `user-stories.md`
- `acceptance-criteria.md`
- `screen-flows.md`
- `data-model.md`
- `api-contract.md`
- `business-rules.md`
- `printing-hardware.md`
- `role-permissions.md`
- `seed-data.md`
- `definition-of-done.md`
- `deployment.md`

## AI-agent development

This project is intended to be built primarily by AI coding agents.

The canonical source-of-truth document order is:

1. `product-scope.md` — what we are building and why.
2. `event-restaurant-spec.md` — domain spec and architectural intent.
3. `user-stories.md` and `acceptance-criteria.md` — required behaviour.
4. `data-model.md`, `api-contract.md`, `business-rules.md` — invariants.
5. `printing-hardware.md`, `role-permissions.md`, `seed-data.md` — config.
6. `definition-of-done.md` — exit criteria.
7. `deployment.md` — how to run it.

Domain invariants (must not be broken by any change):

- A row cannot have two open `OccupiedZone`s whose seat-pair ranges overlap.
- Customer bills only print on cashier-assigned printers.
- Production tickets only print on the kitchen/bar printer routed for the
  item's category; void slips follow the same routing.
- A `PrintJob` is the source of truth for whether something printed.
  Failed jobs remain visible and retryable; the browser is never the source.
- All operational and billing actions append to `audit_events`; no silent edits.

## Out of scope for MVP

The following are intentionally out of scope unless explicitly added later:

- Integrated payment processing.
- Legal invoicing / VAT-certified invoicing.
- Reservations subsystem.
- Inventory or stock management.
- Online ordering.
- QR ordering.
- Loyalty features.
- Per-person split billing.
- Interactive kitchen or bar stations.

## Development status

The MVP is implemented:

- Laravel 11 + Filament 3 + Livewire 3 modular monolith.
- PostgreSQL data store, Redis-backed `prints` queue.
- Filament admin (Configuração) and operational pages
  (`/admin/floor`, `/admin/billing-groups/{id}`, `/admin/orders/new/{id}`,
  `/admin/cashier-checkout`).
- Print subsystem with three adapters: `LanEscPosAdapter` (TCP/9100 ESC/POS),
  `UsbAgentAdapter` (HTTP to print-agent), `NullAdapter` (file-dump for
  development and tests).
- Audit logging on every state change via `App\Domain\Audit\Audit`.
- Pest test suite covering occupancy overlap, order submission, billing
  flow, print job retry, and role policy.

## Quick start

```bash
docker compose up -d postgres redis
docker compose up -d app web worker scheduler
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed --force
open http://localhost:8080/admin/login   # admin / password
```

For full deployment, hardening and operational steps, see `deployment.md`.

## Tests

> **Timeout:** The full suite (`run-tests.ps1`) can take 10+ minutes. Use a **20-minute timeout** when running via agent tasks or CI.

Use the PowerShell script for a consistent test environment (automatic `.env` swapping and Dusk server management):

```powershell
.\run-tests.ps1              # Pest + Dusk
.\run-tests.ps1 -PestOnly    # Pest only
.\run-tests.ps1 -DuskOnly    # Dusk only
```

Or run Pest directly:

```bash
./vendor/bin/pest            # all feature/unit tests
./vendor/bin/pest tests/Feature
```

See `docs/testing.md` for the full testing guide.

## Notes

Serveo is optimized for recurring event operations where physical seat positioning, printed routing, and auditability are more important than generic retail POS features.
