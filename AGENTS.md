# AGENTS.md

## Purpose

This file gives implementation instructions to AI coding agents working on the recurring event service and billing app repository.

It defines how agents should interpret the documentation pack, how to make decisions, how to structure code, and how to avoid introducing scope drift or architecture mistakes.

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

Priority order when making implementation decisions:

1. `architecture.md`
2. `product-scope.md`
3. `04-acceptance-criteria.md`
4. `08-business-rules.md`
5. `10-role-permissions.md`
6. `05-screen-flows.md`
7. `06-data-model.md`
8. `07-api-contract.md`
9. `09-printing-hardware.md`
10. `12-definition-of-done.md`
11. `11-seed-data.json`

If a conflict appears between code and docs, align code to docs unless the docs are clearly outdated and the task explicitly includes updating them.

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

### Screen intent

Agents should align screens with `05-screen-flows.md`.

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

Agents should organize code by domain/module rather than by generic technical folders alone.

Recommended top-level app domains:

- Auth
- Users
- RolesPermissions
- VenueLayout
- ServiceSessions
- BillingGroups
- OccupiedZones
- Menu
- Orders
- ProductionTickets
- BillingDocuments
- Payments
- Printing
- EventLog
- AccountingExport
- Localization
- Admin

### Preferred patterns

- Use service/action classes for important workflows.
- Keep Livewire components thin where possible.
- Keep business rules in domain/services, not only in UI components.
- Use policies/gates for authorization.
- Use form requests or equivalent validation layers.
- Use database transactions for critical multi-step writes.
- Use queued jobs for printing and exports.
- Use explicit DTOs/value objects where they reduce ambiguity.

### Avoid

- Fat controllers with embedded business rules.
- Business logic only in Blade/Livewire views.
- Direct printer calls inside UI event handlers.
- Hidden status transitions.
- Silent exception swallowing.
- Over-abstraction before real need exists.

## Data and migration guidance

Agents must align persistence with `06-data-model.md` and `08-business-rules.md`.

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
- Print requests create persistent print-job records.
- Queue workers dispatch print jobs.
- Adapters perform actual printer delivery.
- Results update print-job status.
- Failures remain visible and retryable.

### Adapter model

Preferred adapter interfaces:

- `LanEscPosPrinterAdapter`
- `UsbAgentPrinterAdapter`

Possible support classes:

- `PrintPayloadRenderer`
- `PrinterRouteResolver`
- `PrintJobDispatcher`
- `PrintResultRecorder`

### Printing rules

- Kitchen/bar routing is configured by admin per menu item.
- Bills print only to cashier printers.
- Void slips go to the original destination type.
- Reprints must be marked.
- Print failures must not silently disappear.

## Testing guidance

Agents must create tests for critical workflows, not just happy-path demos.

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

Use `11-seed-data.json` as a baseline fixture only.

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
- Identify the exact acceptance criteria and business rules involved.
- Check role-permission implications.
- Check whether printing, audit, or queue behavior is affected.

### During coding

Agents should:

- Make small, coherent changes.
- Keep naming consistent with the documentation pack.
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

Use domain names from the docs consistently.

Prefer:

- `BillingGroup`
- `OccupiedZone`
- `ProductionTicket`
- `BillingDocument`
- `PaymentRecord`
- `AuditEvent`

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
