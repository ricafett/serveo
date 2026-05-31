# Architecture: Recurring Event Service and Billing App

## Purpose

This document defines the MVP architecture for the recurring event service and billing app, updated to reflect what has been built.

It reflects the current product and delivery choices:

- AI-agent-led implementation with minimal direct human coding.
- Local-network deployment.
- Web app with mobile-friendly "add to home screen" support; full PWA behavior is optional, not mandatory for MVP.
- Laravel + Livewire + Filament as the selected stack.
- One kitchen printer, one bar printer, and one printer per cashier.
- 80mm thermal printers with cutter.
- Direct LAN printing preferred, with USB supported through a local print agent where needed.
- Job queue retention on printer failure.

## Architecture goals

The architecture optimizes for:

- Low implementation ambiguity for AI agents.
- Strong support for operational workflows and admin configuration.
- Reliable printing and print-job traceability.
- Simple deployment for a local-network environment.
- Clear separation between business rules, UI behavior, and printer integration.
- Future extensibility without requiring a rewrite.

## Architecture style

### Primary style

Use a **modular monolith**.

That means:

- One main Laravel application.
- One primary relational database.
- One queue system for background jobs.
- Optional one or more local print agents only where USB printers require them.
- No microservices in MVP.

### Why this style fits

A modular monolith is the best fit because the system has many tightly related workflows: seating, billing groups, occupied zones, order routing, printing, bills, payments, audit logging, and admin configuration. Splitting those into separate services would add unnecessary coordination complexity and increase deployment and debugging overhead.

## Selected stack

### Application stack

- **Backend / main app:** Laravel.
- **Interactive UI:** Livewire + Blade.
- **Admin and configuration UI:** Filament.
- **Styling:** Tailwind CSS.
- **Database:** PostgreSQL.
- **Queue / cache:** Redis preferred, database queue acceptable for early MVP.
- **Background processing:** Laravel queue workers.
- **Printing library:** ESC/POS generation via `mike42/escpos-php`.
- **Containerization:** Docker Compose.
- **Reverse proxy:** Nginx or Caddy.

## Application structure

The Laravel application is organized as follows:

```
app/
  Domain/          # Domain service classes, organized by subdomain
    Audit/
    Billing/
    Floor/
    Orders/
    Printing/
      Adapters/
      Contracts/
  Filament/        # Filament admin resources and pages
    Pages/
    Resources/
  Http/            # Controllers, middleware, Livewire components
  Jobs/            # Queued background jobs
  Models/          # All Eloquent models (flat)
  Providers/       # Service providers
```

### Domain layer (`app/Domain/`)

Domain logic is organized into subfolders by subdomain. Each subfolder contains service classes, exceptions, and value objects relevant to that subdomain.

Implemented subfolders:

- `Audit/` — audit event recording (`Audit.php`)
- `Billing/` — billing document and checkout logic (`BillingService.php`)
- `Floor/` — billing group and occupancy management (`BillingGroupService.php`, `OccupancyService.php`, `ZoneOverlapException.php`)
- `Orders/` — order creation, validation, and voiding (`OrderService.php`)
- `Printing/` — print pipeline and adapter subsystem (see Printing architecture section)
- `Sales/` — cashier voucher sale completion and sale-document orchestration (`VoucherSaleService.php`)

Domains that do not yet have their own subfolder (Auth, Users, RolesPermissions, Menu, ServiceSessions, VenueLayout, Payments, AccountingExport, EventLog, Localization) are currently handled through Filament resources and flat Models. When adding new domain logic, create a new subfolder under `app/Domain/` following the existing pattern.

### Models layer (`app/Models/`)

All Eloquent models are kept flat under `app/Models/`. Do not create per-domain model subfolders.

Current models:

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
- `Sale`
- `SaleDocument`
- `SaleItem`
- `SalePayment`
- `Seat`
- `SeatPair`
- `Section`
- `ServiceSession`
- `TranslationKey`
- `User`
- `Venue`

### Filament layer (`app/Filament/`)

Filament is used for the admin panel and currently exposes the following resources:

- `AuditEventResource` — event log view
- `BillingStatusResource` — billing status configuration
- `MenuCategoryResource` — menu category management
- `MenuItemResource` — menu item management with printer route assignment
- `SaleResource` — sales visibility and detail review
- `PrintJobResource` — print job visibility and retry actions
- `PrinterResource` — printer configuration
- `PrinterRouteResource` — printer route configuration
- `RowResource` — venue row management
- `SectionResource` — venue section management
- `ServiceSessionResource` — service session management
- `UserResource` — user management

Filament is not used for core service-floor operational UX (floor view, order entry, cashier workflows). Those are Livewire-driven.

## UI architecture

### Primary UI model

Server-driven UI with Livewire for most interactive workflows.

Recommended split:

- **Livewire:** floor operations, billing-group detail, order entry, cashier workflows, cashier voucher sales, printer status, basic event views.
- **Filament:** admin setup, printers, routes, statuses, users, service sessions, exports, and internal management screens.
- **Blade/Tailwind:** layout shell, navigation, document preview wrappers, and shared visual components.

### Mobile and home-screen behavior

The app should be optimized for mobile browser usability and should support being added to the home screen. A full offline-first PWA architecture is not required for MVP.

## Data architecture

### Database choice

Use PostgreSQL as the primary database.

### Data categories

The database separates:

- Configuration data.
- Operational state.
- Historical/audit records.
- Print queue and print result data.

### Recommended persistence principles

- Keep current state in primary domain tables.
- Keep immutable traceability in audit/event and print records.
- Avoid destructive deletes for historical entities.
- Prefer soft disable for configuration referenced by history.

## Printing architecture

### Design principles

Printing is backend-owned. The browser is not the source of truth for whether an operational ticket or bill was successfully produced.

### Printing subsystem structure

The print subsystem lives under `app/Domain/Printing/` and is structured as:

```
app/Domain/Printing/
  Contracts/
    PrinterAdapter.php         # Interface all adapters must implement
  Adapters/
    LanEscPosAdapter.php       # Direct LAN ESC/POS printing
    UsbAgentAdapter.php        # USB print agent forwarding
    NullAdapter.php            # No-op adapter for testing / disabled printers
  PrinterAdapterRegistry.php   # Resolves the correct adapter for a given Printer model
  PrintQueueService.php        # Creates and manages PrintJob records
  TicketRenderer.php           # Renders ESC/POS payloads for tickets and bills
  PrintResult.php              # Value object: outcome of a print dispatch attempt
```

### Print flow

1. User action creates a business record (order, bill request, sale voucher/receipt request, void, correction, or reprint).
2. The application calls `PrintQueueService` to create a persistent `PrintJob` record.
3. `PrintQueueService` dispatches `DispatchPrintJob` to the queue.
4. The queue worker runs `DispatchPrintJob`.
5. `PrinterAdapterRegistry` resolves the correct adapter for the target `Printer`.
6. `TicketRenderer` generates the ESC/POS payload.
7. The adapter delivers the payload and returns a `PrintResult`.
8. The job updates the `PrintJob` record with the outcome.
9. The application records the audit trail via `Audit`.

### Queue job

All print dispatch is handled through one unified job: `app/Jobs/DispatchPrintJob.php`.

This single job handles all print job types: production tickets, bills, sale vouchers, sale receipts, void slips, and reprints. The job type and payload are carried on the `PrintJob` record.

### Printer adapters

Implemented adapters:

- `LanEscPosAdapter` — direct TCP/IP delivery to a networked ESC/POS printer.
- `UsbAgentAdapter` — HTTP delivery to a local print agent running on a PC attached to a USB printer.
- `NullAdapter` — no-op adapter used for testing and for printers in disabled/unknown state.

All adapters implement `Contracts/PrinterAdapter.php`.

When referring to adapter class names in code or docs, use:
- `LanEscPosAdapter` (not `LanEscPosPrinterAdapter`)
- `UsbAgentAdapter` (not `UsbAgentPrinterAdapter`)

### Print queue rules

- Every operational print must have a persistent `PrintJob` record.
- Failed jobs must remain visible and retryable via `PrintJobResource` in the admin panel.
- Jobs must not disappear silently on printer failure.
- Void slips go to the same destination as the original production ticket.
- Customer bills print only to the cashier's assigned printer (`CashierPrinterAssignment`).
- Sale vouchers and optional sale receipts also print only to the cashier's assigned printer in MVP.

## Queue and background job design

### Queue strategy

Use Laravel queues for all non-trivial print and export work.

Current job:

- `DispatchPrintJob` — unified job handling all print dispatch.

Future jobs to add when needed:

- `GenerateAccountingExportJob`
- `RetryFailedPrintJob` (or integrate retry into `DispatchPrintJob`)

### Queue backend

Preferred MVP choice: Redis queue. Acceptable early fallback: database queue.

### Why queueing matters

Queueing is required because printer operations are inherently slower and less reliable than ordinary database writes. Moving them into background jobs protects the user workflow and allows persistent retry behavior when a printer or local connector is temporarily unavailable.

## Realtime and refresh strategy

### MVP recommendation

Start with polling or lightweight partial refresh for most live operational screens:

- Floor occupancy refresh.
- Billing-group totals refresh.
- Printer status refresh.
- Queue status refresh.

WebSockets can be added later if polling proves insufficient. Do not make WebSockets a hard dependency for MVP.

## Security model

### Network assumptions

The application runs on a trusted local network. The system still requires authentication and role-based authorization.

### Security controls

- Authenticated users only.
- Role-based access checks at UI and backend levels.
- CSRF protection for web actions.
- Audit logging for sensitive actions.
- Restricted admin-only printer route changes and test prints.
- Restricted cashier-only bill printing.

## Deployment architecture

### Recommended deployment shape

Use Docker Compose for MVP deployment.

Suggested services:

- `app` or `php-fpm`
- `web` (Nginx or Caddy)
- `postgres`
- `redis`
- `worker`
- optional `scheduler`

Optional extra service outside the main host:

- `usb-print-agent` on cashier PC or print-hub PC when needed.

## Observability and supportability

### Minimum operational visibility

The system exposes admin-visible state for troubleshooting via Filament:

- Printer list and printer status.
- Recent print jobs (`PrintJobResource`).
- Failed print jobs with retry actions.
- Event log (`AuditEventResource`).
- Queue health summary.

### Logging

- Application logs.
- Queue worker logs.
- Print-dispatch logs.
- Print-agent logs where applicable.

## AI-agent implementation guidance

Because this project is intended for AI-agent-led development, the architecture optimizes for explicit conventions and narrow module boundaries.

Guidance for the build process:

- Keep one repository for MVP.
- Keep modules clearly named and consistent with this document and `AGENTS.md`.
- Avoid premature abstraction.
- Put print templates and adapters behind `Contracts/PrinterAdapter.php`.
- Keep Filament limited to admin/configuration concerns.
- Keep Livewire focused on operational workflows.

### Suggested implementation order for remaining work

1. Livewire floor and occupancy workflows.
2. Livewire billing-group and order entry workflows.
3. Livewire cashier billing workflow.
4. USB print-agent adapter integration.
5. Accounting export job and Filament export page.
6. Admin configuration completions (venue, seat pairs, localization).

## Optional local USB print agent

A local print agent should exist only where a printer cannot be reached directly over the LAN.

The print agent is a small local service on the PC attached to the USB printer. It receives print jobs from the Laravel app via `UsbAgentAdapter` and forwards them to the local printer.

Recommended responsibilities:

- Register one or more attached printers.
- Receive authenticated print requests from the Laravel app.
- Forward raw ESC/POS payloads to the local USB printer.
- Return success/failure results.
- Expose basic health information.

Non-responsibilities:

- No business-rule decisions.
- No routing logic.
- No document templating.
- No queue ownership beyond optional local buffering.

## Future evolution path

The architecture allows future additions without changing the core shape:

- Interactive kitchen/bar screens.
- Better live updates via WebSockets.
- More advanced export and reporting.
- Reservation subsystem.
- More printer types or fallback routing.

Even with those additions, the modular monolith should remain the default architecture until real operational complexity proves otherwise.

## Final recommendation

The implemented MVP architecture is:

- **Laravel modular monolith**
- **Livewire for operational UI**
- **Filament for admin/configuration**
- **PostgreSQL as primary database**
- **Redis-backed Laravel queues**
- **Backend-owned print subsystem under `app/Domain/Printing/`**
- **Single `DispatchPrintJob` for all print dispatch**
- **`LanEscPosAdapter` / `UsbAgentAdapter` / `NullAdapter` as printer adapters**
- **Flat `app/Models/` layer alongside domain service layer**
- **Docker Compose deployment on a local host**
