# User Stories by Epic: Recurring Event Service and Billing App

## Purpose

This document organizes the approved MVP user stories into epics for the recurring event service and billing app. It aligns with the current product scope: a local-network web/PWA used by servers, kitchen, cashier, and admin staff for end-to-end service, internal billing, ticket printing, and full event logging.[cite:152]

The stories are written as implementation-oriented product stories, not as acceptance criteria. They are intended to be refined later into acceptance tests and task breakdowns.[cite:152]

## Epic overview

The MVP user stories are grouped into these epics:

- EPIC-01 Floor and seating management.
- EPIC-02 Order capture and routing.
- EPIC-03 Kitchen and bar ticket handling.
- EPIC-04 Cashier billing and checkout.
- EPIC-05 Admin configuration and control.
- EPIC-06 Audit trail and event history.[cite:152]

## EPIC-01 Floor and seating management

This epic covers the live floor model used by servers acting as hosts. It focuses on opening billing groups, assigning seat ranges, and understanding floor occupancy without relying on fixed table IDs.[cite:152]

| ID | User story |
|---|---|
| US-001 | As a server acting as host, I want to open a new billing group and assign one or more seat ranges to it, so that I can start service without depending on fixed table IDs. |
| US-002 | As a server, I want to see which seat ranges are free or occupied, so that I can seat new groups quickly. |
| US-003 | As a server, I want to assign a status to a newly opened billing group, so that I can distinguish waiting and active groups during service. |

## EPIC-02 Order capture and routing

This epic covers server-side order entry from the floor, including group-level and zone-level ordering, delivery targeting, and printer routing logic. It reflects the custom model in which a billing group may span multiple occupied zones and delivery defaults to the center of the occupied range unless a more specific seat pair is selected.[cite:152]

| ID | User story |
|---|---|
| US-004 | As a server, I want to add orders to a billing group, or to a specific zone associated with a billing group, from my phone or tablet, so that I can work directly on the floor. |
| US-005 | As a server, I want each order to be linked to a specific occupied zone when needed, so that kitchen and cashier staff can keep service traceable inside the same group. |
| US-006 | As a server, I want the default delivery target for an order to be the center of the occupied range, so that I do not need to specify a precise point every time. |
| US-007 | As a server, I want to override the default delivery point with a specific seat pair, so that I can direct service more precisely when needed. |
| US-008 | As a server, I want to send drinks to the bar printer and food to the kitchen printer automatically, so that tickets reach the correct preparation station. |
| US-009 | As a server, I want to void or correct an order item with a printed void slip, so that the paper workflow stays consistent for runners and kitchen. |

## EPIC-03 Kitchen and bar ticket handling

This epic covers printed production tickets for kitchen and bar staff, including the information needed by prep staff and runners to identify the correct group and delivery area.[cite:152]

| ID | User story |
|---|---|
| US-010 | As kitchen staff, I want to receive printed tickets for food orders, so that I can prepare dishes without using the floor app. |
| US-011 | As bar staff, I want to receive printed tickets only for drinks orders, so that bar work is separated from kitchen work. |
| US-012 | As kitchen staff, I want tickets to show the billing group and the relevant occupied zone, so that I can identify where the order belongs. |
| US-013 | As kitchen staff, I want tickets to show the default delivery point or specific seat pair when relevant, so that runners know where to bring items. |
| US-014 | As kitchen staff, I want void slips and reprints to be clearly marked, so that I do not confuse corrections with new production. |

## EPIC-04 Cashier billing and checkout

This epic covers internal billing and checkout for cashiers. It reflects the current scope in which billing is internal-only, legal invoicing is handled elsewhere, and the MVP supports partial payment and reopen while preserving full traceability.[cite:152]

| ID | User story |
|---|---|
| US-015 | As a cashier, I want to find any open billing group quickly, so that I can process checkout without asking the server to recreate context. |
| US-016 | As a cashier, I want to see all occupied zones attached to the billing group, so that I can verify I am billing the correct party. |
| US-017 | As a cashier, I want to print an internal customer bill at any point, so that the guest can review charges before checkout. |
| US-018 | As a cashier, I want to register partial payment against a billing group, so that the remaining balance stays open and traceable. |
| US-019 | As a cashier, I want to reopen a billing group after a partial checkout, so that service and billing can continue cleanly. |
| US-020 | As a cashier, I want to reprint a bill without altering the original transaction history, so that duplicate printouts do not damage auditability. |
| US-021 | As a cashier, I want every action on a billing group to remain logged, so that disputed changes can be reviewed later. |
| US-022 | As a server, I want to reopen a billing group after partial checkout, so that service can continue without losing history. |

## EPIC-05 Admin configuration and control

This epic covers the administrative setup required to run the recurring event reliably, including venue structure, printer routing, access control, multilingual configuration, and accounting export.[cite:152]

| ID | User story |
|---|---|
| US-023 | As an admin, I want to define the venue structure by section, row, seat, and seat pair, so that the app reflects the real service layout. |
| US-024 | As an admin, I want to configure which printers receive kitchen tickets, bar tickets, bills, and void slips, so that printing follows the event workflow. |
| US-025 | As an admin, I want to manage user roles for server, kitchen, cashier, and admin, so that each person sees only the functions they need. |
| US-026 | As an admin, I want to configure billing-group statuses, so that staff can use a shared operational vocabulary during service. |
| US-027 | As an admin, I want to export accounting data after service, so that financial records can be processed in external software. |
| US-028 | As an admin, I want the UI to support multiple languages, specifically pt-pt and en-us, so that staff can use the system in the language that suits them best. |

## EPIC-06 Audit trail and event history

This epic covers the cross-cutting requirement for full historical traceability. It is separated into its own epic because the product scope requires a full event log rather than only closed-bill history.[cite:152]

| ID | User story |
|---|---|
| US-029 | As an admin, I want a full event log of seating, orders, prints, voids, reopen actions, and payments, so that the whole service can be audited after the event. |

## Notes for refinement

These stories represent the approved MVP story set, but they still need later refinement into acceptance criteria, edge-case rules, and implementation tasks. In particular, reopen behavior, partial payment behavior, zone-level order targeting, and print routing should be converted into explicit business rules and test cases in the next documentation steps.[cite:152]

The story set intentionally reflects the current product-scope decisions: local network only, web/PWA delivery, internal billing, no dedicated reservations module, no integrated payments, no inventory, and no offline mode.[cite:152]
