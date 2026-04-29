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

See `architecture.md` for the full architecture decision.

## Documentation pack

The repository is driven by a documentation-first workflow.

Important project documents include:

- `product-scope.md`
- `user-stories.md`
- `04-acceptance-criteria.md`
- `05-screen-flows.md`
- `06-data-model.md`
- `07-api-contract.md`
- `08-business-rules.md`
- `09-printing-hardware.md`
- `10-role-permissions.md`
- `11-seed-data.json`
- `12-definition-of-done.md`
- `architecture.md`
- `AGENTS.md`

## AI-agent development

This project is intended to be built primarily by AI coding agents.

`AGENTS.md` defines:

- The source-of-truth document order.
- Required stack and architectural constraints.
- Domain invariants that must not be broken.
- Role boundaries.
- Printing and testing rules.
- How agents should behave when requirements are ambiguous.

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

Serveo is currently in specification and architecture-driven build preparation.

The repository contains the documentation required for agents to implement the MVP in a controlled way before broader feature expansion.

## Next steps

Recommended implementation order:

1. Bootstrap Laravel app and local Docker environment.
2. Implement auth, roles, and base admin setup.
3. Implement venue layout and billing-group domains.
4. Implement floor workflow and order capture.
5. Implement printing pipeline and print adapters.
6. Implement cashier billing workflow.
7. Implement event log, export, and operational hardening.

## Notes

Serveo is optimized for recurring event operations where physical seat positioning, printed routing, and auditability are more important than generic retail POS features.
