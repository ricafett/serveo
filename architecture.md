# Architecture: Recurring Event Service and Billing App

> **Note:** This is the original pre-implementation architecture document.
> See the sections marked [AS BUILT] for the updated version reflecting the actual implementation.

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

## UI architecture

### Primary UI model

Use server-driven UI with Livewire for most interactive workflows.

Recommended split:

- **Livewire:** floor operations, billing-group detail, order entry, cashier workflows, printer status, basic event views.
- **Filament:** admin setup, printers, routes, statuses, users, roles, exports, and internal management screens.
- **Blade/Tailwind:** layout shell, navigation, document preview wrappers, and shared visual components.

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

### Print queue rules

- Every operational print should have a persistent print job record.
- Failed jobs must remain visible and retryable.
- Jobs must not disappear silently on printer failure.

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
