# Definition of Done: Recurring Event Service and Billing App

## Purpose

This document defines the MVP Definition of Done for the recurring event service and billing app. It translates the existing product scope, user stories, acceptance criteria, screen flows, data model, API contract, business rules, printing requirements, role model, and seed data into a practical release-readiness checklist.

The goal is to make “done” mean more than “code exists.” A feature or milestone is done only when behavior, permissions, printing, auditability, and core operational workflows are implemented, testable, and aligned with the agreed documentation set.

## Scope of this document

This Definition of Done applies to MVP delivery of the app for a specific recurring event setup, using the current documentation pack and current scope boundaries.

It covers:

- Functional completion.
- Documentation alignment.
- Testing expectations.
- Print and operational readiness.
- Data and audit integrity.
- Role and permission correctness.
- Deployment readiness for local-network MVP use.

It does not define future version goals or post-MVP enhancements.

## Global done criteria

A feature, module, or MVP release is considered done only if all applicable items below are satisfied.

### DoD-001 — Scope alignment
The implemented behavior matches the current approved documentation and does not introduce major out-of-scope features.

### DoD-002 — Acceptance criteria coverage
All relevant acceptance criteria for the implemented feature are satisfied and verifiable.

### DoD-003 — No undocumented behavior dependencies
Critical workflows do not rely on hidden manual assumptions that are absent from the documentation set.

### DoD-004 — Role-aware implementation
The feature behaves correctly for each relevant role and does not expose unauthorized actions.

### DoD-005 — Audit-safe behavior
Important state-changing actions produce the expected audit trail and do not silently lose historical traceability.

### DoD-006 — Visible failure handling
Failures that affect occupancy, ordering, printing, billing, or export are exposed visibly rather than silently ignored.

## Functional done criteria by domain

## 1. Floor and seating

A floor and seating feature is done only when:

- The UI can display sections, rows, and seat-pair occupancy clearly.
- Free and occupied ranges are visually distinguishable.
- A server or cashier can create a billing group and assign one or more valid seat ranges.
- Cashier-created zones require explicit assigned-server selection.
- Overlapping occupied zones are prevented.
- Occupancy changes do not rename physical layout elements.
- Released ranges become available again without damaging historical references.
- Relevant actions are audit-logged.

## 2. Billing-group lifecycle

A billing-group lifecycle feature is done only when:

- A billing group can be created, viewed, updated, and tracked through valid statuses.
- Invalid status transitions are rejected.
- Closed groups reject new orders unless reopened through an allowed workflow.
- Reopen preserves history and restores valid service actions.
- Group-level identity remains separate from physical location identity.
- Zone-level discrimination remains visible where required.

## 3. Order capture and delivery targeting

An order-entry feature is done only when:

- A server or cashier can create an order for a billing group.
- Orders can optionally target a valid occupied zone within the billing group.
- Default delivery targeting uses the zone center when no override is supplied.
- Specific seat-pair overrides are accepted only when valid.
- Submitted items retain route and pricing history.
- Post-submission corrections follow void/correction behavior rather than silent overwrite.
- Order creation writes the expected audit events.
- Cancellation rules enforce server-own / cashier-any access correctly and require reopen before cancelling on closed groups.

## 4. Production tickets and printing

A production-printing feature is done only when:

- Food and drinks route to the configured destinations correctly.
- Mixed orders split correctly across kitchen and bar outputs.
- Kitchen and bar tickets contain the required identifying information.
- Void/correction slips print to the same destination as the original production ticket.
- Reprints are clearly marked.
- Failed print jobs remain visible and traceable.
- Queue behavior preserves jobs when a printer is unavailable.

## 5. Billing and checkout

A billing feature is done only when:

- A cashier can find the intended billing group reliably.
- A cashier can print an internal bill without forcing closure.
- A cashier can complete a paid voucher sale and print the resulting vouchers from the sales flow.
- Partial payments can be recorded accurately.
- Remaining balance is calculated correctly.
- Bill reprints do not change commercial state.
- Voucher-sale documents follow the configured grouping behavior and use the cashier printer.
- Reopen after partial payment works only for authorized users and valid states.
- Billing and payment events are audit-logged.

## 6. Admin and configuration

An admin feature is done only when:

- Admin can configure venue structure, billing statuses, printers, menu routing, users, roles, and translations where applicable.
- Configuration changes validate correctly.
- Invalid configuration is rejected visibly.
- Manual printer configuration works without relying on auto-discovery.
- Accounting export can be generated from persisted operational data.

## 7. Event log and history

An audit/history feature is done only when:

- The expected event types are recorded.
- Actor and timestamp are present when applicable.
- Historical records remain available after closure, release, reprint, or reopen.
- Event log access respects role boundaries.
- Event log data is useful enough to explain what happened during service.

## Role-based done criteria

### SERVER done conditions
Server-facing work is done only when:

- Servers can complete floor and ordering workflows from supported devices.
- Servers cannot access cashier-only or admin-only actions.
- Server-specific conditional actions, such as reopen or void/correction, are enforced correctly.

### CASHIER done conditions
Cashier-facing work is done only when:

- Cashiers can search, inspect, bill, partially settle, and reprint according to scope.
- Cashiers can complete the approved floor, zone-assignment, and order-entry workflows according to scope.
- Bills print to the cashier’s assigned printer.
- Cashiers cannot access admin-only configuration functions.

### ADMIN done conditions
Admin-facing work is done only when:

- Admin can configure the system without needing database edits.
- Admin can trigger printer test prints.
- Admin can inspect event history and generate exports.
- Admin can adjust printer routes during service if necessary.

### KITCHEN_OUTPUT and BAR_OUTPUT done conditions
Kitchen/bar-related MVP work is done only when:

- Kitchen and bar do not require app interaction.
- Printed tickets alone are sufficient to support the intended operational handoff.
- No interactive kitchen/bar screen is required for MVP operation.

## Testing done criteria

### DoD-007 — Automated test coverage for critical flows
Critical workflows have automated coverage at the appropriate level, such as unit, integration, or end-to-end tests.

At minimum, automated tests should cover:

- Billing-group creation with valid and conflicting zones.
- Order creation at group and zone level.
- Delivery override validation.
- Mixed kitchen/bar routing.
- Void/correction generation.
- Order-level and item-level cancellation authorization and reopen prerequisites.
- Bill generation and bill reprint.
- Voucher sale completion, voucher printing, optional receipt printing, and sales export visibility.
- Partial payment and reopen.
- Role-based authorization checks.
- Printer queue behavior on failure.
- Event-log creation for major actions.

### DoD-008 — Tests may generate their own data
Automated tests should generate additional scenario-specific data when needed rather than relying only on static seed data.

### DoD-009 — Seed data remains usable
The base seed data remains loadable and useful for local development and smoke testing.

### DoD-010 — Manual validation for operational flows
High-risk operational flows should also be manually validated in a realistic local environment, especially for printing and multi-role workflows.

## Data and integrity done criteria

### DoD-011 — Data constraints hold
Core integrity rules are enforced, including:

- No overlapping open zones in the same row.
- Zone linkage only within the owning billing group.
- Valid delivery-seat override boundaries.
- Stable history for reprints and corrections.

### DoD-012 — Concurrency protection exists
Sensitive concurrent operations, especially on billing groups and occupied zones, have a defined protection strategy such as optimistic locking or equivalent conflict handling.

### DoD-013 — Historical records survive state changes
Closing a group, releasing a zone, printing a reprint, or reopening a group does not destroy the historical record of prior actions.

## Printing done criteria

### DoD-014 — Printer routing works per documented rules
Print routing follows the documented model:

- One kitchen printer.
- One bar printer.
- One printer per cashier.
- No separate bill printer.
- Menu-item destination set by admin.
- Void slips routed to the same destination as the original production output.

### DoD-015 — Supported connectivity works
The implemented printing stack supports at least one of the documented supported paths:

- Direct LAN printing from backend to printer.
- USB printer access through a local print agent/service.

### DoD-016 — Unsupported connectivity is not assumed
The MVP does not depend on Wi-Fi or Bluetooth printer behavior.

### DoD-017 — Startup test procedure can be performed
An admin can perform documented startup test prints for kitchen, bar, and cashier printers before service.

## UI and usability done criteria

### DoD-018 — Device-appropriate behavior
The app is usable on the intended web/PWA device classes: phone, tablet, and desktop.

### DoD-019 — Operational clarity
Operationally important information is easy to identify during service, especially:

- Occupancy state.
- Billing-group identity.
- Zone references.
- Delivery references.
- Print status.
- Remaining balance.

### DoD-020 — Supported languages work
Supported interface strings function in pt-PT and en-US, with a consistent fallback when a translation is missing.

## Deployment and readiness done criteria

### DoD-021 — Local deployment is documented
The app can be deployed in a local-network MVP environment using documented steps.

### DoD-022 — Required configuration is documented
The setup steps for session context, printers, routes, users, and layout configuration are documented well enough for admin setup.

### DoD-023 — No blocker known for running one real service
There is no known unresolved blocker that would prevent the MVP from running one real dinner service under the agreed scope.

## Documentation done criteria

### DoD-024 — Docs and implementation agree
If behavior changed during development, the relevant documentation has been updated so the project pack stays trustworthy.

### DoD-025 — API, rules, and screens stay consistent
The implemented behavior is not in contradiction with the API contract, business rules, or screen-flow model unless those documents were intentionally revised.

### DoD-026 — Open limitations are explicit
Known limitations are recorded explicitly rather than hidden.

## Exclusions from “done”

The following do not count as done for MVP on their own:

- A UI mockup without working behavior.
- Backend endpoints without correct validation and permissions.
- Print generation without queueing or visible failure handling.
- A happy-path flow that breaks under role restrictions or history requirements.
- Features that work only with manually edited database records.
- Features that pass only with unrealistic or hand-crafted data not representative of actual use.

## Recommended release gate checklist

Before declaring the MVP release done, all of the following should be true:

- Core server workflow works end to end from seating to order submission.
- Kitchen and bar tickets print correctly.
- Cashier can print bills, record partial payments, and reopen where allowed.
- Admin can configure printers, routes, statuses, and venue layout.
- Event log captures the major workflows.
- Seed data loads successfully.
- Automated tests for critical flows pass.
- Manual smoke test on a realistic local environment passes.
- Known limitations are documented.

## Final rule

The MVP is done only when it is operationally usable for the intended recurring event, not merely technically implemented.
