# Business Rules: Recurring Event Service and Billing App

## Purpose

This document defines the MVP business rules for the recurring event service and billing app. It translates the product scope, user stories, acceptance criteria, screen flows, data model, and API contract into explicit operational rules that govern how the system behaves during live service.

The goal of this document is to remove ambiguity before implementation, especially around occupancy conflicts, billing-group lifecycle, order routing, printing, partial payments, reopen behavior, and auditability.

## Scope

These rules apply to MVP only. They assume:

- Local-network web/PWA operation.
- Interactive roles limited to server, cashier, and admin.
- Kitchen and bar are non-interactive in MVP and receive printed tickets only.
- Billing is internal-only.
- Legal invoicing and integrated payments are out of scope.
- Reservations are represented operationally through statuses and pre-opened groups rather than a dedicated reservation subsystem.

## Rule categories

The rules are grouped into these areas:

1. Physical layout and occupancy.
2. Billing-group lifecycle.
3. Order capture and delivery targeting.
4. Production ticket generation and printing.
5. Billing and payment handling.
6. Roles and permissions.
7. Audit and history.
8. Error and conflict handling.
9. Session and configuration rules.

## 1. Physical layout and occupancy

### BR-001 — Physical addressing model
The venue floor shall be modeled by section, row, seat, and seat pair.

### BR-002 — Smallest assignable unit
The smallest assignable operational unit shall be one seat pair.

### BR-003 — Occupied zone structure
An occupied zone shall consist of one contiguous range of seat pairs within a single row.

### BR-004 — No cross-row occupied zone in MVP
An occupied zone shall not span more than one row in MVP.

### BR-005 — Multiple zones per billing group
A billing group may own multiple occupied zones at the same time.

### BR-006 — No overlap between open zones
Two open occupied zones in the same row shall not overlap at any seat-pair position.

### BR-007 — Physical layout stability
Changes in occupancy shall not rename sections, rows, seats, or seat pairs.

### BR-008 — Reuse of released space
When an occupied zone is released, any subset of that freed range may be assigned to one or more different billing groups.

### BR-009 — Zone identity versus physical identity
An occupied zone is a temporary operational assignment. Section, row, seat, and seat pair identities are permanent configuration records.

### BR-010 — Zone release behavior
Releasing an occupied zone shall make its seat-pair range available for reassignment, but shall not remove historical records that referenced that zone.

## 2. Billing-group lifecycle

### BR-011 — Billing group as primary commercial entity
A billing group is the primary entity for service, billing, bill printing, payment tracking, reopen actions, and historical review.

### BR-012 — Billing by group, not person
The system shall not require per-person billing in MVP.

### BR-013 — Zone discrimination within one group
Although billing is group-based, the system shall preserve which occupied zone an order, print, or service action belongs to whenever that information exists.

### BR-014 — One active session owner
A billing group belongs to exactly one service session.

### BR-015 — Billing-group open state
A billing group is considered open until it is explicitly closed.

### BR-016 — Status assignment
Each billing group shall have one current status selected from the active configured billing statuses.

### BR-017 — Suggested MVP status vocabulary
The default MVP status set should be: WAITING, ACTIVE, CHECK_REQUESTED, PARTIALLY_PAID, and CLOSED.

### BR-018 — Closed group restrictions
A closed billing group shall not accept new orders unless it is reopened by an authorized user.

### BR-019 — Reopen eligibility
A billing group may be reopened only if business rules and permissions allow it.

### BR-020 — Reopen effect
Reopening a billing group restores the ability to add orders and continue service without deleting prior billing or payment history.

### BR-021 — Cover count behavior
Cover count is optional in MVP and may be stored for operational context, but it shall not be required to create a billing group.

### BR-022 — Notes behavior
Billing-group notes are optional and may be edited by authorized users.

## 3. Order capture and delivery targeting

### BR-023 — Order ownership
Every order must belong to exactly one billing group.

### BR-024 — Optional zone linkage
An order may additionally reference one occupied zone belonging to that billing group.

### BR-025 — Invalid zone linkage rejection
An order shall not be saved with an occupied zone that belongs to a different billing group.

### BR-026 — Group-level order allowed
Orders without an occupied-zone reference are valid in MVP and are treated as billing-group-level orders.

### BR-027 — Order items as printable production units
Order items, not just order headers, must retain sufficient data for routing, printing, and historical review.

### BR-028 — Default delivery target
If no specific delivery seat pair is chosen for an order item, the delivery target shall default to the center of the related occupied zone.

### BR-029 — Delivery override
A specific seat-pair delivery override may be used when greater precision is needed.

### BR-030 — Validity of delivery override
A delivery override seat pair must fall inside the referenced occupied zone.

### BR-031 — No override without zone context
A specific seat-pair delivery override should not be accepted unless the system can validate it against a relevant occupied zone.

### BR-032 — Order immutability after submission
Once an order item has been submitted to production, it shall not be silently edited in place. Changes after submission must be represented through voids, corrections, or additional order items.

### BR-033 — Pricing snapshot
Unit price and routing-relevant values shall be copied onto the order item at creation time so later menu changes do not rewrite history.

### BR-034 — Submission atomicity
Order creation, production-ticket generation, and audit logging should succeed together or fail visibly together wherever practical.

## 4. Production ticket generation and printing

### BR-035 — Printer routing by fulfillment type
Food items shall route to kitchen output, and drinks items shall route to bar output, according to configured routing rules.

### BR-036 — Mixed order splitting
If one submitted order contains both kitchen-routed and bar-routed items, the system shall generate separate production outputs for each destination.

### BR-037 — No silent print loss
If a required print destination is missing or fails, the system shall not silently mark the order as successfully printed.

### BR-038 — Kitchen/bar non-interactive workflow
Kitchen and bar do not need to confirm receipt in MVP. Printed tickets are the operational handoff.

### BR-039 — Production ticket identity
Every generated kitchen, bar, void, or reprint ticket shall have its own persistent record.

### BR-040 — Ticket content minimums
A production ticket shall include enough information to identify the billing group and, when applicable, the occupied zone and delivery target.

### BR-041 — Void slip identity
A void or correction after production submission shall generate a distinguishable void or correction output rather than rewriting the original ticket record.

### BR-042 — Reprint identity
A reprinted production ticket shall be recorded as a new print event linked to the original ticket.

### BR-043 — Reprint marking
A reprint must be visibly marked as a reprint.

### BR-044 — Void/correction marking
A void or correction slip must be visibly marked as void or correction.

## 5. Billing and payment handling

### BR-045 — Internal billing only
All bills and payment records in MVP are internal operational artifacts.

### BR-046 — Bill generation without forced closure
Printing an internal bill shall not automatically close a billing group.

### BR-047 — Bill reprint neutrality
Reprinting a bill shall not alter commercial totals, item lines, or previous billing history.

### BR-048 — Partial payment support
A billing group may have one or more partial payment records.

### BR-049 — Remaining balance calculation
Remaining balance equals current bill total minus non-voided recorded payments.

### BR-050 — Full settlement behavior
If recorded payments equal the current total, the group may be closed according to workflow rules, but the system should not assume legal invoicing has been completed because that is outside MVP.

### BR-051 — Payment record immutability
A saved payment record should not be edited in place. Corrections should use void/reversal-style handling if supported later; in MVP, admin-only corrective handling may be required.

### BR-052 — Reopen after partial checkout
A billing group may be reopened after partial checkout if permitted by role and current state.

### BR-053 — Reopen preserves history
Reopen shall not delete or overwrite prior payment, bill, or print records.

### BR-054 — Charges remain group-level
Charges belong to the billing group even when the group spans multiple occupied zones.

### BR-055 — Zone detail remains operationally visible
Even though totals are group-level, zone-level associations must remain visible for service traceability.

### BR-081 — Payment does not release zones
Recording a payment, including a full payment that brings the balance to zero, shall not automatically release occupied zones. Zone release is a separate, explicit action that must be performed by an authorized user.

## 6. Roles and permissions

### BR-056 — Interactive MVP roles
Interactive users in MVP are SERVER, CASHIER, and ADMIN.

### BR-057 — Server capabilities
Servers may create billing groups, assign zones, update permitted statuses, create orders, trigger production tickets through ordering, and reopen groups if allowed.

### BR-058 — Cashier capabilities
Cashiers may search billing groups, view floor occupancy, open billing groups, assign zones, create orders, view bill details, print bills, record partial payments, reprint bills, and reopen groups if allowed.

### BR-058A — Zone-only server assignment
Assigned server ownership is tracked only through occupied zones. Billing groups do not carry assigned-server ownership.

### BR-058B — Cashier zone assignment behavior
When a cashier opens a billing group or adds an occupied zone, the workflow must let the cashier choose the assigned server for that zone. The authenticated cashier remains the actor for audit purposes.

### BR-059 — Admin capabilities
Admins may configure venue structure, printers, routing, users, roles, statuses, translations, exports, and audit inspection.

### BR-060 — Non-interactive kitchen/bar roles
Kitchen and bar may exist as output or configuration concepts, but they do not require interactive app use in MVP.

### BR-061 — Restricted actions
Only authorized roles may perform reopen, payment recording, configuration changes, and reprint actions.

## 7. Audit and history

### BR-062 — Append-only audit principle
Important operational and billing events shall be written to an append-only audit log.

### BR-063 — Audit minimum coverage
The audit log shall cover at least billing-group creation, zone assignment, status change, order creation, production-ticket creation, ticket reprint, void/correction, bill generation, bill reprint, payment record creation, reopen, and accounting export.

### BR-064 — Actor attribution
Each audit event should include actor identity when the event was triggered by an authenticated user.

### BR-065 — Time attribution
Each audit event shall include an event timestamp.

### BR-066 — Historical survivability
Historical records shall remain available after a billing group is closed or an occupied zone is released.

### BR-067 — Current-state tables are not enough
Mutable current-state records alone are not sufficient to satisfy MVP audit requirements.

## 8. Error and conflict handling

### BR-068 — Visible failure principle
If an action affecting occupancy, ordering, printing, billing, or payment cannot be completed, the failure shall be exposed to the user rather than silently ignored.

### BR-069 — Occupancy conflict validation
Zone overlap shall be checked at save time using current persisted state.

### BR-070 — Version conflict handling
Concurrent edits to the same billing group should be detected and rejected with a version conflict response rather than blindly overwriting newer changes.

### BR-071 — Print failure handling
A print failure shall preserve enough pending or failed state to allow investigation and controlled reprint.

### BR-072 — Invalid status transition rejection
A billing-group status transition that violates allowed workflow rules shall be rejected.

### BR-073 — Invalid delivery override rejection
A seat-pair delivery override that falls outside the relevant occupied zone shall be rejected.

### BR-074 — Closed-group order rejection
New orders shall be rejected for closed billing groups unless a valid reopen happens first.

## 9. Session and configuration rules

### BR-075 — Session scoping
Operational records should belong to a service session wherever relevant.

### BR-076 — Configuration versus operational data separation
Venue structure, menu, printer routes, statuses, roles, and translations are configuration data. Billing groups, zones, orders, prints, bills, payments, and audit events are operational data.

### BR-077 — Soft-disable over destructive delete
Configuration objects used in history should normally be deactivated rather than physically deleted.

### BR-078 — Translation fallback
If a translatable UI string is missing in the selected language, the system shall use the configured fallback language rather than exposing a raw key where possible.

### BR-079 — Manual printer configuration
Because printer auto-discovery is out of scope, printers and routes must be configured manually by an admin.

### BR-080 — Accounting export independence
Accounting export shall read from persisted operational records and shall not depend on ephemeral UI state.

## Recommended status transition rules

The following status transitions are recommended for MVP:

- WAITING -> ACTIVE
- ACTIVE -> CHECK_REQUESTED
- CHECK_REQUESTED -> PARTIALLY_PAID
- PARTIALLY_PAID -> ACTIVE via reopen
- ACTIVE -> CLOSED
- PARTIALLY_PAID -> CLOSED

Recommended restrictions:

- CLOSED -> ACTIVE only through explicit reopen workflow when supported.
- WAITING -> CLOSED should be allowed only if business decides abandoned groups need closure without service.
- ACTIVE -> WAITING should normally be disallowed because it moves backward operationally.

## Recommended validation rules by action

### Create billing group
- At least one zone is recommended but not strictly required if business wants pre-opened unseated groups.
- All selected zones must be free.
- Status must be a valid active billing status.

### Add occupied zone
- Billing group must be open.
- Range must be contiguous within one row.
- Range must not overlap any other open zone.

### Create order
- Billing group must be open.
- If zone is provided, zone must belong to the billing group and be open.
- If delivery seat pair is provided, it must belong to the relevant zone.
- Every item must resolve to a valid fulfillment route.

### Register partial payment
- Billing group must exist and be open or reopen-eligible.
- Amount must be positive.
- Resulting remaining balance may be zero but should not go negative unless explicit overpayment logic is added later.

### Reopen billing group
- User must have permission.
- Group must not be in a terminal non-reopenable state defined later by policy.
- Reopen must preserve prior bill and payment history.

## Recommended derived calculations

The system should calculate these consistently from persisted records:

- Current open balance per billing group.
- Current occupancy map per floor row.
- Current active status per billing group.
- Default delivery reference for each occupied zone.
- Outstanding print failures, if any.

## Out-of-scope rule areas for MVP

The following rule areas are intentionally deferred:

- Reservation allocation logic.
- Inventory depletion rules.
- Integrated card-terminal workflows.
- Kitchen acknowledgment workflows.
- Runner tracking workflows.
- Customer-specific accounts or loyalty rules.
- Per-person item ownership and split billing.
- Analytics-specific aggregation rules beyond export and event history.

## Implementation notes

These business rules should be enforced across multiple layers where appropriate:

- Domain/service layer for core invariants.
- Database constraints and transactions for overlap, integrity, and concurrency-sensitive writes.
- API validation for request correctness.
- UI validation for fast operator feedback.

A rule should not rely only on frontend validation if violating it would damage operational integrity or historical traceability.
