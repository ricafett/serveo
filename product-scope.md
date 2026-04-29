# Product Scope: Recurring Event Service and Billing App

## Product summary

This product is a local-network web/PWA application for operating a specific recurring food-service event. It supports end-to-end floor operation for servers, kitchen, cashier, and admin staff, using a custom seating and billing model based on section, row, and seat ranges rather than fixed restaurant tables.[cite:152]

The product is intended to run one full meal service for hundreds of guests with a dozen or more servers, while preserving precise operational tracking, producing kitchen and bar paper tickets, printing customer bills, and maintaining a full historical event log.[cite:152]

## Product goal

The primary goal of version 1 is to support a complete dinner-service workflow from seating through order capture, kitchen/bar ticket printing, billing, partial payment, reopen, and final bill printing, without losing traceability of where a group was seated and how service progressed.[cite:152]

The app is not meant to be a general-purpose restaurant POS product in version 1. It is designed for one specific recurring event setup with known staffing patterns, known service flow, and a custom floor model already defined in the domain specification.[cite:152]

## Primary users

The MVP includes these user roles:

- Server.
- Kitchen.
- Cashier.
- Admin.[cite:152]

Servers also act as hosts for live seating and group assignment. Runners are out of scope as app users and will rely on printed paper tickets rather than interacting with the system directly.[cite:152]

## Operating environment

The application shall be delivered as a web/PWA solution that works on computers, tablets, and phones. It is intended for local-network-only operation during service.[cite:152]

The initial operating scale assumes approximately:

- 1 admin user.
- 3 cashier users.
- 2 kitchen users, including 1 drinks/bar station.
- 12 or more server users.[cite:152]

## In-scope capabilities

Version 1 includes the following scope:

- End-to-end service workflow.
- Multilingual user interface.
- Accounting export.
- Group-based seating and service tracking using the custom section-row-seat-range model.
- Billing groups associated with one or more occupied zones.
- Zone-level discrimination within the same billing group.
- Kitchen and bar paper ticket printing.
- Customer bill printing.
- Reprints.
- Ticket void slips.
- Internal billing and checkout support.
- Full event log and historical traceability.[cite:152]

The app shall support the previously defined floor model in which the smallest assignable unit is a seat pair, occupied zones are defined by seat ranges, billing is tracked by group, and delivery defaults to the center of an occupied range unless a specific seat pair is used for precision.[cite:152]

## Out-of-scope capabilities

The following features are explicitly out of scope for version 1:

- Integrated payments.
- Stock and inventory management.
- Reservation-specific workflows.
- QR ordering.
- Online ordering.
- VAT-certified invoicing.
- Offline mode.
- Analytics.
- Printer auto-discovery.[cite:152]

Reservations are intentionally excluded as a dedicated feature because the operating model has only one reservation round per meal time. Any reservation-like handling shall be represented operationally through pre-opened billing groups and status rather than through a dedicated reservation subsystem.[cite:152]

## Billing and invoicing scope

The billing model in version 1 is internal-only. The system must keep precise commercial and operational tracking, but final legal invoicing at checkout or payment will be handled in separate software.[cite:152]

Version 1 must still support:

- Internal bill generation.
- Partial payment.
- Reopen after partial closure when needed.
- Accurate linkage between billing groups and all occupied zones.
- Full traceability of service and billing events.[cite:152]

Complex billing-group lifecycle behavior beyond reopen and partial payment is intentionally deferred unless it proves necessary during implementation or testing.[cite:152]

## Printing scope

Printing is a core operational requirement for version 1. The application must support:

- Kitchen paper tickets.
- Bar/drinks paper tickets.
- Customer bills.
- Reprints.
- Ticket void slips.[cite:152]

Printed tickets are a primary operational artifact because runners do not use the app directly. The printing workflow is therefore part of the MVP core, not an optional integration.[cite:152]

## History and audit scope

Version 1 requires a full event log rather than simple closed-bill history. The system must preserve detailed traceability of operational and billing activity across the service lifecycle.[cite:152]

At a minimum, the event log should be able to capture billing-group creation, occupied-zone assignment, order creation, ticket printing, reprints, voids, reopen actions, partial payments, bill printing, and user-attributed actions taken during service.[cite:152]

## Success criteria for version 1

Version 1 is successful if it can support one full dinner service for hundreds of people with a dozen or more servers, while correctly tracking groups over seat ranges, routing and printing kitchen and bar tickets, handling cashier checkout with internal bills, and preserving complete history without operational breakdown.[cite:152]

Success also means the system is usable in a high-throughput local event environment across phones, tablets, and computers without requiring runner interaction with the software.[cite:152]

## Product principles

The product should follow these guiding principles:

- Model the real event floor, not a generic restaurant table map.
- Keep physical addressing separate from billing identity.
- Optimize for speed and clarity during live service.
- Preserve operational traceability at group and zone level.
- Treat printing as a first-class workflow.
- Prefer simplicity in version 1 over exhaustive edge-case handling.
- Keep architecture decisions open for the dedicated architecture document.[cite:152]

## Deferred decisions

The following areas remain intentionally undecided and should be resolved in the architecture phase rather than the product-scope phase:

- Frontend framework and backend stack.
- Database choice.
- Hosting method.
- Authentication model.
- Printer integration approach.
- Export format details for accounting.[cite:152]

## Open questions

The current product scope still leaves some decisions for follow-up documentation:

- Which features, if any, should be explicitly marked as version 2.
- Whether manager-specific behavior should remain part of admin or become a separate role later.
- What exact accounting export formats are required.
- Whether multilingual support in MVP requires full translation management or just a fixed language set.
