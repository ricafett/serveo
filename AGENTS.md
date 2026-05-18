# AGENTS.md

## Purpose

This file gives implementation instructions to AI coding agents working on the recurring event service and billing app repository.

It defines how agents should interpret the documentation pack, how to make decisions, how to structure code, and how to avoid introducing scope drift or architecture mistakes.

## Repository identity

| Property | Value |
|---|---|
| GitHub owner | `ricafett` |
| Repository name | `serveo` |
| GitHub URL | `https://github.com/ricafett/serveo` |
| Clone URL | `https://github.com/ricafett/serveo.git` |

When using MCP GitHub tools (e.g., `GitHub_issue_read`, `GitHub_list_issues`, `GitHub_create_pull_request`), use:
- **owner:** `ricafett`
- **repo:** `serveo`

## Product context

This repository is for an MVP application used in a recurring food-service event environment.

Core characteristics:

- Local-network web app.
- Mobile-friendly browser experience with optional "add to home screen" use.
- Interactive roles: SERVER, CASHIER, ADMIN.
- Non-interactive operational roles: KITCHEN_OUTPUT, BAR_OUTPUT.
- Kitchen and bar staff receive printed tickets only in MVP.
- Internal billing only.
- Payments are tracked operationally, not integrated.
- Strong reliance on ticket printing, print queueing, and audit logging.
- Seat-based physical addressing uses section, row, seat, and seat pair.
- Billing is by billing group, with zone discrimination.

## Source of truth

Agents must treat the documentation pack as the source of truth.

The spec files have been moved to `docs/spec/`. Priority order when making implementation decisions:

1. `docs/spec/architecture.md`
2. `docs/spec/product-scope.md`
3. `docs/spec/acceptance-criteria.md`
4. `docs/spec/business-rules.md`
5. `docs/spec/role-permissions.md`
6. `docs/spec/screen-flows.md`
7. `docs/spec/data-model.md`
8. `docs/spec/api-contract.md`
9. `docs/spec/printing-hardware.md`
10. `docs/spec/definition-of-done.md`
11. `docs/spec/seed-data.md`

If a conflict appears between code and docs, align code to docs unless the docs are clearly outdated and the task explicitly includes updating them.

### Tracking done and planned work
- **GitHub issues** are the source of truth for open and planned work. Check open issues before starting a new task to avoid duplication.
- **Commit messages** act as the project changelog. Write detailed, descriptive commit titles and messages that explain the *why* and *what* of each change. Future agents (and humans) will read the git history to understand how the codebase evolved.
- **Decision records** live in `docs/decisions/` and capture intentional deviations from the spec (e.g., why a shortcut was taken, why a doc was updated instead of the code). Read them before reversing a past choice.

## Required stack

Agents must implement the MVP using this stack unless explicitly instructed otherwise:

- Laravel
- Livewire
- Filament
- Blade
- Tailwind CSS
- PostgreSQL
- Redis preferred for queues/cache
- Laravel queue workers
- Docker Compose

Do not replace the stack with React, Next.js, ASP.NET, Rails, Django, or another stack unless explicitly instructed.

### Pinned versions

| Package | Installed |
|---|---|
| Laravel | v13.7.0 |
| Livewire | v4.2.4 |
| Filament | v5.6.1 |
| spatie/laravel-permission | 7.4.1 |
| Pest | v4.6.3 |

Do not assume v3 APIs. When unsure about a class, method, or namespace from vendor source, query Context7 MCP before writing code.

## Required architecture

Agents must follow these architectural rules:

- Use a modular monolith.
- Keep one main application for MVP.
- Do not introduce microservices.
- Keep printing backend-owned.
- Keep print queues persistent.
- Prefer direct LAN printing.
- Support USB printing through a local print agent abstraction only when needed.
- Do not make browser printing the source of truth.
- Do not make WebSockets mandatory for MVP.
- Do not make offline-first PWA behavior mandatory for MVP.

## Domain rules to preserve

Agents must preserve these product-specific invariants:

- The smallest assignable physical unit is a seat pair.
- An occupied zone is one contiguous seat-pair range within one row.
- A billing group may span multiple occupied zones.
- No two open occupied zones may overlap within the same row.
- Orders belong to one billing group and may optionally reference one occupied zone.
- Delivery defaults to the center of the occupied zone unless overridden by a valid seat pair.
- Closed billing groups may not accept new orders unless reopened through an authorized workflow.
- Kitchen and bar are non-interactive in MVP.
- Void slips go to the same destination as the original production ticket.
- Customer bills print only to the cashier's assigned printer.
- Failed print jobs must remain visible and retryable.
- Important actions must create audit events.

## Role rules to preserve

Agents must not blur role behavior.

### SERVER
Allowed focus:
- Floor view
- Billing-group creation
- Zone assignment
- Order entry
- Void/correction where allowed
- Reopen where allowed

### CASHIER
Allowed focus:
- Billing-group lookup
- Bill printing
- Bill reprint
- Partial payment recording
- Reopen where allowed

### ADMIN
Allowed focus:
- Venue setup
- Printer and route setup
- User and role setup
- Status setup
- Event log access
- Export
- Test prints
- Route changes during service

### KITCHEN_OUTPUT / BAR_OUTPUT
- No interactive UI in MVP.
- No station screens in MVP.
- Only printed-ticket output semantics.

## UI implementation guidance

### General

- Build for speed and clarity during live service.
- Optimize touch targets for phone and tablet use.
- Keep layouts simple and task-focused.
- Use server-driven interactions with Livewire wherever practical.
- Use Filament primarily for admin/configuration surfaces, not for core service-floor UX.
- **All UI components, screens, and design changes must support both light and dark themes from inception.** Configure Tailwind `dark:` variants and test both modes before considering UI work complete.

### Screen intent

Agents should align screens with `screen-flows.md`.

Key screens:
- Login / session entry
- Floor
- Create/Edit Billing Group
- Billing Group Detail
- Order Entry
- Billing Group Lookup
- Checkout
- Reprint / document actions
- Venue Setup
- Printer Setup
- User and Role Management
- Billing Status Configuration
- Event Log
- Accounting Export

### Avoid

Do not add these screens in MVP unless explicitly instructed:

- Reservation management
- Inventory management
- QR ordering
- Online ordering
- Interactive kitchen display
- Interactive bar display
- Customer-facing payment terminal flows
- Analytics dashboard beyond agreed scope

## Backend implementation guidance

### Code organization

The application uses the following top-level structure:

- `app/Domain/` — domain service and business logic classes, organized by subdomain
- `app/Models/` — all Eloquent models (flat, not per-domain subfolders)
- `app/Filament/` — Filament admin resources and pages
- `app/Http/` — controllers, middleware, Livewire components
- `app/Jobs/` — queued background jobs
- `app/Providers/` — service providers

### Implemented domain subfolders

The following domain subfolders exist under `app/Domain/`:

- `Audit` — audit event recording (contains `Audit.php`)
- `Billing` — billing document and checkout logic (contains `BillingService.php`)
- `Floor` — billing group and occupancy management (contains `BillingGroupService.php`, `OccupancyService.php`, `ZoneOverlapException.php`)
- `Orders` — order creation, validation, and voiding (contains `OrderService.php`)
- `Printing` — print adapter registry, queue service, ticket renderer, and contracts (contains `PrintQueueService.php`, `PrintResult.php`, `PrinterAdapterRegistry.php`, `TicketRenderer.php`, plus `Adapters/` and `Contracts/` subfolders)

New domain logic for Auth, Users, RolesPermissions, Menu, ServiceSessions, VenueLayout, Payments, AccountingExport, EventLog, Localization, and Admin is currently handled through Filament resources and flat Models. When adding new domain logic, create a new subfolder under `app/Domain/` following the existing pattern.

### Implemented Eloquent models

All Eloquent models live flat under `app/Models/`:

- `AccountingExport`
- `AuditEvent`
- `BillingDocument`
- `BillingGroup`
- `BillingStatus`
- `CashierPrinterAssignment`
- `MenuCategory`
- `MenuItem`
- `OccupiedZone`
- `OrderHeader`
- `OrderItem`
- `PaymentRecord`
- `PrintJob`
- `Printer`
- `PrinterRoute`
- `ProductionTicket`
- `Row`
- `Seat`
- `SeatPair`
- `Section`
- `ServiceSession`
- `TranslationKey`
- `User`
- `Venue`

### Implemented Filament resources

The Filament admin panel currently exposes:

- `AuditEventResource` — event log view
- `BillingStatusResource` — billing status configuration
- `MenuCategoryResource` — menu category management
- `MenuItemResource` — menu item management with printer route assignment
- `PrintJobResource` — print job visibility and retry actions
- `PrinterResource` — printer configuration
- `PrinterRouteResource` — printer route configuration
- `RowResource` — venue row management
- `SectionResource` — venue section management
- `ServiceSessionResource` — service session management
- `UserResource` — user management

### Printing subsystem

The printing subsystem lives under `app/Domain/Printing/` and uses the following structure:

- `Contracts/PrinterAdapter.php` — interface that all adapters must implement
- `Adapters/LanEscPosAdapter.php` — direct LAN ESC/POS printing adapter
- `Adapters/UsbAgentAdapter.php` — USB print agent forwarding adapter
- `Adapters/NullAdapter.php` — no-op adapter for testing and disabled printers
- `PrinterAdapterRegistry.php` — resolves the correct adapter for a given printer
- `PrintQueueService.php` — creates and manages print job records
- `TicketRenderer.php` — renders ESC/POS payloads for production tickets and bills
- `PrintResult.php` — value object representing the outcome of a print dispatch

All print dispatch happens through the single queued job `app/Jobs/DispatchPrintJob.php`, which handles all print job types (production tickets, bills, void slips, reprints).

When referring to adapter class names in code or docs, use:
- `LanEscPosAdapter` (not `LanEscPosPrinterAdapter`)
- `UsbAgentAdapter` (not `UsbAgentPrinterAdapter`)
- `NullAdapter` for testing/disabled scenarios

### Preferred patterns

- Use service/action classes for important workflows.
- Keep Livewire components thin where possible.
- Keep business rules in `app/Domain/` services, not in UI components.
- Use policies/gates for authorization.
- Use form requests or equivalent validation layers.
- Use database transactions for critical multi-step writes.
- Use queued jobs for printing and exports.
- Use explicit DTOs/value objects where they reduce ambiguity (see `PrintResult.php` as an example).

### Avoid

- Fat controllers with embedded business rules.
- Business logic only in Blade/Livewire views.
- Direct printer calls inside UI event handlers.
- Hidden status transitions.
- Silent exception swallowing.
- Over-abstraction before real need exists.
- Creating per-domain model subfolders — keep all models flat under `app/Models/`.

## Data and migration guidance

Agents must align persistence with `data-model.md` and `business-rules.md`.

### Required persistence principles

- Preserve historical traceability.
- Avoid destructive deletes for records referenced by history.
- Prefer deactivation for configuration.
- Store current state and explicit audit records separately.
- Store print jobs and print results as first-class records.

### Concurrency-sensitive areas

Use stronger protection for:

- Billing-group updates
- Occupied-zone assignment
- Order submission
- Reopen actions
- Print-job state transitions

If needed, use optimistic locking or equivalent conflict handling.

## Printing implementation guidance

Agents must treat printing as a core subsystem.

### Required model

- User actions create business records first.
- Print requests create persistent `PrintJob` records via `PrintQueueService`.
- The queue worker dispatches `DispatchPrintJob`.
- `PrinterAdapterRegistry` resolves the correct adapter for the target printer.
- `TicketRenderer` generates the ESC/POS payload.
- The adapter performs delivery and returns a `PrintResult`.
- The job updates the `PrintJob` record with the outcome.
- Failures remain visible and retryable in the Filament `PrintJobResource`.

### Printing rules

- Kitchen/bar routing is configured by admin per menu item via `PrinterRoute`.
- Bills print only to the cashier's assigned printer (`CashierPrinterAssignment`).
- Void slips go to the original destination type.
- Reprints must be marked.
- Print failures must not silently disappear.

## Testing guidance

Agents must create tests for critical workflows, not just happy-path demos.

**Read `docs/testing.md` before writing or modifying tests.** It covers the test stack (Pest + Dusk), the `run-tests.ps1` script, selector conventions, and Dusk helper methods. Do not skip it.

### Environment involvement

- `.env.dusk.local` is the **tracked** test environment (SQLite, sync queues). It is the only env file meant for the test runner.
- `.env` is **not tracked** and must remain the local dev config (PostgreSQL/Redis). Do not overwrite `.env` with test settings.
- Always run the full suite via `run-tests.ps1`, which swaps `.env` with `.env.dusk.local` and restores it afterward.
- Pest tests use `:memory:` SQLite and are isolated. Dusk tests share `database/dusk.sqlite` and truncate operational tables between tests.

### Required coverage areas

- Occupied-zone overlap rejection
- Billing-group creation
- Status transition validation
- Group-level and zone-level order creation
- Delivery override validation
- Mixed kitchen/bar routing
- Void/correction flow
- Bill printing and reprint
- Partial payment handling
- Reopen behavior
- Role-based authorization
- Print queue persistence on failure
- Audit-event creation

### Test data rule

Use `seed-data.md` as a reference for baseline fixture data.

Tests should generate additional scenario-specific data when necessary instead of depending entirely on static seed data.

## Documentation responsibilities

Agents must update documentation when implementation changes agreed behavior.

At minimum, update relevant files when changing:

- Domain rules
- Screen flows
- Permissions
- API behavior
- Print routing behavior
- Data model
- Architecture decisions

Do not silently change behavior without updating docs.

## Decision rules for ambiguous cases

When implementation details are not fully specified:

1. Prefer the simplest solution consistent with the docs.
2. Prefer backend-owned logic over frontend-owned logic.
3. Prefer explicit state and auditability over hidden convenience.
4. Prefer queue-backed resilience over synchronous fragility.
5. Prefer configuration over hardcoding where admin control is expected.
6. Prefer local-network assumptions over internet-scale complexity.
7. Do not expand scope automatically.

## Working style for agents

### Before coding

Agents should:

- Read the relevant docs for the module they are touching.
- When researching library or framework behavior — especially Filament, Livewire, or Laravel — query Context7 MCP first instead of guessing from v3 docs or vendor source code.
- Identify the exact acceptance criteria and business rules involved.
- Check role-permission implications.
- Check whether printing, audit, or queue behavior is affected.

### During coding

Agents should:

- Make small, coherent changes.
- Keep naming consistent with the documentation pack and the existing codebase.
- Add or update tests with each meaningful behavior change.
- Prefer explicit, readable code over clever abstractions.

### Before finishing a task

Agents should verify:

- Acceptance criteria are satisfied.
- Permissions are enforced.
- Audit behavior exists where required.
- Print behavior is queue-backed where required.
- Tests pass.
- Documentation is still accurate.

## Naming guidance

Use domain names from the docs and existing codebase consistently.

Prefer:

- `BillingGroup`
- `OccupiedZone`
- `ProductionTicket`
- `BillingDocument`
- `PaymentRecord`
- `AuditEvent`
- `OrderHeader` / `OrderItem` (as implemented, not just `Order`)
- `PrintJob`
- `PrinterRoute`
- `CashierPrinterAssignment`
- `ServiceSession`
- `LanEscPosAdapter` / `UsbAgentAdapter` / `NullAdapter`

Avoid replacing these with generic names like:

- `Table`
- `TableGroup`
- `Desk`
- `ReceiptItem`
- `Invoice` for MVP internal bill concepts

## Out-of-scope reminders

Do not introduce these in MVP unless explicitly asked:

- Legal invoicing / VAT-certified invoicing
- Integrated payments
- Reservations subsystem
- Inventory or stock management
- Customer QR ordering
- Online ordering
- Loyalty accounts
- Per-person split billing
- Interactive kitchen/bar stations
- Cloud-first architecture
- Microservices

## Done criteria for agent tasks

A task is not done just because code compiles.

Agents should consider a task complete only when:

- Behavior matches docs.
- Tests exist and pass.
- Permissions are correct.
- Auditability is preserved.
- Print side effects are handled correctly.
- No known scope drift was introduced.

## Final instruction

When in doubt, build the smallest correct thing that preserves operational clarity, print reliability, and historical traceability.
