# Architecture: Recurring Event Service and Billing App

## Purpose

This document defines the recommended MVP architecture for the recurring event service and billing app.

It reflects the current product and delivery choices:

- AI-agent-led implementation with minimal direct human coding.
- Local-network deployment.
- Web app with mobile-friendly "add to home screen" support; full PWA behavior is optional, not mandatory for MVP.
- Laravel + Livewire + Filament as the selected stack.
- One kitchen printer, one bar printer, and one printer per cashier.
- 80mm thermal printers with cutter.
- Direct LAN printing preferred, with USB supported through a local print agent/service where needed.
- Job queue retention on printer failure.

## Architecture goals

The architecture should optimize for:

- Low implementation ambiguity for AI agents.
- Strong support for operational workflows and admin configuration.
- Reliable printing and print-job traceability.
- Simple deployment for a local-network environment.
- Clear separation between business rules, UI behavior, and printer integration.
- Future extensibility without requiring a rewrite.

## Recommended architecture style

### Primary style

Use a **modular monolith**.

That means:

- One main Laravel application.
- One primary relational database.
- One queue system for background jobs.
- Optional one or more local print agents only where USB printers require them.
- No microservices in MVP.

### Why this style fits

A modular monolith is the best fit for MVP because the system has many tightly related workflows: seating, billing groups, occupied zones, order routing, printing, bills, payments, audit logging, and admin configuration.

Splitting those into separate services would add unnecessary coordination complexity for AI agents and increase deployment and debugging overhead.

## Selected stack

### Application stack

- **Backend / main app:** Laravel.
- **Interactive UI:** Livewire + Blade.
- **Admin and configuration UI:** Filament.
- **Styling:** Tailwind CSS.
- **Database:** PostgreSQL.
- **Queue / cache:** Redis preferred, database queue acceptable for early MVP.
- **Background processing:** Laravel queue workers.
- **Printing library:** ESC/POS generation via PHP library such as mike42/escpos-php.
- **Containerization:** Docker Compose.
- **Reverse proxy:** Nginx or Caddy.

### Why this stack was chosen

Laravel, Livewire, and Filament are a strong fit for AI-assisted development because they reduce boilerplate, provide clear conventions, and make CRUD-heavy, workflow-heavy internal apps faster to build consistently.

This stack also fits the app's needs well because the product is operational and admin-heavy, with roles, statuses, routing, exports, event history, and printing, rather than being a highly custom consumer-facing frontend product.

## High-level system components

The MVP should contain these main components:

1. Web application.
2. Database.
3. Queue worker subsystem.
4. Print subsystem.
5. Optional local USB print agent.
6. Reverse proxy / local access layer.

### 1. Web application

The Laravel application owns:

- Authentication.
- Role and permission checks.
- Floor and occupancy workflows.
- Billing-group workflows.
- Order capture.
- Billing and payment recording.
- Admin configuration.
- Audit event creation.
- Print-job creation.

### 2. Database

PostgreSQL stores:

- Core operational state.
- Configuration state.
- Audit events.
- Print jobs and print results.
- Queue-linked records and document history.

### 3. Queue worker subsystem

Queue workers handle background tasks such as:

- Production-ticket rendering.
- Bill rendering.
- Printer dispatch.
- Retry scheduling.
- Accounting export generation.
- Cleanup or maintenance jobs.

### 4. Print subsystem

The print subsystem is not just a helper; it is a first-class operational subsystem.

It should own:

- Print job records.
- Printer selection.
- Payload generation.
- Delivery to LAN or USB-backed destinations.
- Retry handling.
- Final status updates.
- Audit-linked traceability.

### 5. Optional local USB print agent

A local print agent should exist only where a printer cannot be reached directly over the LAN.

The print agent is a small local service on the PC attached to the USB printer. It receives print jobs from the Laravel app and forwards them to the local printer.

### 6. Reverse proxy / local access layer

A reverse proxy should terminate local HTTP traffic, route requests to the Laravel app, and support a stable hostname on the local network.

This keeps deployment cleaner and makes device access easier for servers, cashiers, and admins.

## Module boundaries inside the monolith

The Laravel app should be organized into clear modules or domains, even though it remains one deployable application.

Recommended modules:

- Auth and Users.
- Venue Layout.
- Service Sessions.
- Billing Groups.
- Occupied Zones.
- Menu and Routing.
- Orders.
- Production Tickets.
- Billing Documents.
- Payments.
- Printers and Print Jobs.
- Event Log.
- Accounting Export.
- Localization.
- Admin Settings.

This structure is especially useful for AI agents because it reduces cross-module confusion and makes prompts easier to scope.

## UI architecture

### Primary UI model

Use server-driven UI with Livewire for most interactive workflows.

Recommended split:

- **Livewire:** floor operations, billing-group detail, order entry, cashier workflows, printer status, basic event views.
- **Filament:** admin setup, printers, routes, statuses, users, roles, exports, and internal management screens.
- **Blade/Tailwind:** layout shell, navigation, document preview wrappers, and shared visual components.

### Why this UI model fits

This approach reduces client-side state complexity compared with a separate SPA frontend.

That is beneficial for AI-agent-led development because it lowers the number of moving parts, keeps most business behavior near the backend, and avoids a lot of custom frontend/backend synchronization logic.

### Mobile and home-screen behavior

The app should be optimized for mobile browser usability and should support being added to the home screen.

A full offline-first PWA architecture is not required for MVP.

However, the app may still include lightweight installability features later if they do not complicate delivery.

## Data architecture

### Database choice

Use PostgreSQL as the primary database.

### Why PostgreSQL

PostgreSQL is a strong fit because the app depends on relational integrity, range/occupancy validation, auditability, and concurrent writes from multiple operators during service.

### Data categories

The database should clearly separate:

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

Printing must be backend-owned.

The browser should not be the source of truth for whether an operational ticket or bill was successfully produced.

### Print flow

Recommended flow:

1. User action creates an order, bill request, void, correction, or reprint request.
2. The application writes the business transaction.
3. The application creates one or more print job records.
4. Queue workers pick up print jobs.
5. The worker resolves the destination printer and connector type.
6. The worker renders the ESC/POS payload.
7. The worker dispatches the job through the appropriate adapter.
8. The worker updates print status.
9. The application records the audit trail.

### Printer adapter model

The print subsystem should expose an internal adapter interface.

Recommended adapters:

- `LanEscPosPrinterAdapter`
- `UsbAgentPrinterAdapter`

Optional future adapters:

- `FilePreviewAdapter`
- `PdfDebugAdapter`

### Preferred print path

Primary path:

- Direct LAN printing from Laravel workers to networked printers.

Secondary path:

- USB printing through a local print agent running on a PC.

### Existing integration path

For receipt payload generation, use ESC/POS support from a PHP library such as `mike42/escpos-php`.

For local USB bridging, the architecture may use either:

- A custom lightweight print agent.
- An existing local connector such as QZ Tray.

QZ Tray should be treated as one possible connector implementation, not as the core architecture of the app.

### Print queue rules

- Every operational print should have a persistent print job record.
- Failed jobs must remain visible and retryable.
- Jobs must not disappear silently on printer failure.
- Queue state should include pending, processing, printed, failed, and retryable-style outcomes.
- Void slips go to the same destination as the original production ticket.
- Customer bills print only to the cashier's assigned printer.

## Suggested print-agent architecture

If USB support is needed, a local print agent should be a very small service with a narrow responsibility boundary.

Recommended responsibilities:

- Register one or more attached printers.
- Receive authenticated print requests from the Laravel app.
- Forward raw ESC/POS payloads to the local USB printer.
- Return success/failure results.
- Expose basic health information.

Recommended non-responsibilities:

- No business-rule decisions.
- No routing logic.
- No document templating.
- No queue ownership beyond optional local buffering.

This keeps the Laravel app as the source of truth for routing, queueing, and auditability.

## Queue and background job design

### Queue strategy

Use Laravel queues for all non-trivial print and export work.

Recommended job types:

- `DispatchProductionTicketJob`
- `DispatchBillPrintJob`
- `DispatchVoidSlipJob`
- `RetryFailedPrintJob`
- `GenerateAccountingExportJob`

### Queue backend

Preferred MVP choice:

- Redis queue.

Acceptable early fallback:

- Database queue.

### Why queueing matters

Queueing is required because printer operations are inherently slower and less reliable than ordinary database writes.

Moving them into background jobs protects the user workflow and allows persistent retry behavior when a printer or local connector is temporarily unavailable.

## Realtime and refresh strategy

### MVP recommendation

Start with polling or lightweight partial refresh for most live operational screens.

Examples:

- Floor occupancy refresh.
- Billing-group totals refresh.
- Printer status refresh.
- Queue status refresh.

### Optional later enhancement

WebSockets can be added later for live updates if polling is not good enough.

For MVP, do not make WebSockets a hard dependency unless testing shows polling is insufficient.

This reduces delivery risk for AI agents.

## Security model

### Network assumptions

The application runs on a trusted local network.

Even so, the system should still require authentication and role-based authorization.

### Security controls

Recommended MVP controls:

- Authenticated users only.
- Role-based access checks at UI and backend levels.
- CSRF protection for web actions.
- Audit logging for sensitive actions.
- Restricted admin-only printer route changes and test prints.
- Restricted cashier-only bill printing.

### Future-hardening options

Later, if needed, add:

- HTTPS on the local network.
- Device/session restrictions.
- IP allowlists for admin access.
- Signed communication between Laravel and local USB print agents.

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

### Why this deployment fits

This keeps deployment simple enough for a local environment while preserving clean separation between web traffic, database, queueing, and background workers.

## Observability and supportability

### Minimum operational visibility

The system should expose enough internal visibility for admin troubleshooting.

Recommended admin-visible states:

- Printer list and printer status.
- Recent print jobs.
- Failed print jobs.
- Retry actions.
- Event log.
- Queue health summary.

### Logging

Recommended logs:

- Application logs.
- Queue worker logs.
- Print-dispatch logs.
- Print-agent logs where applicable.

## AI-agent implementation guidance

Because this project is intended for AI-agent-led development, the architecture should optimize for explicit conventions and narrow module boundaries.

Recommended guidance for the build process:

- Keep one repository for MVP.
- Keep modules clearly named and documented.
- Avoid premature abstraction.
- Create stable DTOs and form objects where useful.
- Put print templates and adapters behind clear interfaces.
- Keep Filament limited to admin/configuration concerns.
- Keep Livewire focused on operational workflows.

### Suggested implementation order

1. Core Laravel app, auth, and roles.
2. Venue layout and billing-group modules.
3. Floor and occupancy workflows.
4. Order capture and menu routing.
5. Print job model and queue pipeline.
6. LAN printing adapter.
7. Cashier billing workflow.
8. USB print-agent adapter.
9. Event log and export.
10. Admin configuration via Filament.

## Future evolution path

The architecture should allow future additions without changing the core shape.

Likely future additions:

- Interactive kitchen/bar screens.
- Better live updates.
- More advanced export and reporting.
- Reservation subsystem.
- More printer types or fallback routing.

Even with those additions, the modular monolith should remain the default architecture until real operational complexity proves otherwise.

## Final recommendation

The recommended MVP architecture is:

- **Laravel modular monolith**
- **Livewire for operational UI**
- **Filament for admin/configuration**
- **PostgreSQL as primary database**
- **Redis-backed Laravel queues**
- **Backend-owned print subsystem**
- **Direct LAN printing first**
- **USB printing via optional local print agent**
- **Docker Compose deployment on a local host**

This architecture is the best match for the current product scope, the printing requirements, and the goal of relying primarily on AI agents for implementation.
