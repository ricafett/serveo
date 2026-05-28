# Role and Permissions: Recurring Event Service and Billing App

## Purpose

This document defines the MVP role model and permission boundaries for the recurring event service and billing app. It translates the product scope, user stories, acceptance criteria, screen flows, API contract, business rules, and printing requirements into a clear authorization model for interactive and non-interactive roles.

The goal is to make role behavior explicit so the implementation does not guess who is allowed to view, create, edit, print, reopen, configure, or export.

## MVP role model

The MVP includes these roles:

- SERVER
- CASHIER
- ADMIN
- KITCHEN_OUTPUT
- BAR_OUTPUT

Only SERVER, CASHIER, and ADMIN are interactive application roles in MVP.

KITCHEN_OUTPUT and BAR_OUTPUT exist as operational concepts for routing, auditing, and future extensibility, but they do not require direct app interaction in MVP.

## Permission model principles

- Permissions should follow the minimum access needed to perform the role.
- Operational roles should not receive configuration rights.
- Printing rights should distinguish between production outputs and billing outputs.
- Sensitive actions such as reopen, reprint, payment recording, and route changes should be explicitly controlled.
- Kitchen and bar should not have interactive permissions in MVP.

## Permission categories

Permissions are grouped into these categories:

1. Session and authentication.
2. Floor and occupancy.
3. Billing-group lifecycle.
4. Orders and production printing.
5. Billing and payment.
6. Reprints and void-related actions.
7. Event log and history.
8. Configuration and administration.
9. Export and localization.

## Role summaries

### SERVER

Primary operational role for live service on the floor. In MVP, servers also act as hosts.

Main responsibilities:

- View floor occupancy.
- Open billing groups.
- Assign occupied zones.
- Set allowed statuses during service.
- Create orders.
- Trigger production tickets indirectly through ordering.
- Trigger void/correction slips where business rules allow.
- Reopen billing groups if permitted.

Restrictions:

- No printer configuration.
- No user management.
- No accounting export.
- No bill printing in MVP.
- No printer test prints.

### CASHIER

Primary operational role for billing review and checkout.

Main responsibilities:

- Look up open billing groups.
- Review group totals and occupied zones.
- Print internal customer bills.
- Reprint bills.
- Record partial payments.
- Reopen billing groups if permitted.
- Review limited history when needed for checkout investigation.

Restrictions:

- No venue structure configuration.
- No menu routing configuration.
- No printer route changes.
- No printer test prints.
- No accounting export by default.

### ADMIN

Administrative and supervisory role.

Main responsibilities:

- Configure venue structure.
- Configure printers and printer routes.
- Configure billing statuses.
- Manage users and roles.
- Access full event log.
- Trigger test prints.
- Change printer routes during service.
- Reprint bills.
- Generate accounting exports.
- Manage translations and language configuration.

Restrictions:

- None within MVP scope, except actions outside the overall product scope.

### KITCHEN_OUTPUT

Non-interactive operational role for ticket destination semantics only.

Main responsibilities:

- Receive printed kitchen production tickets.
- Receive printed kitchen void/correction slips.

Restrictions:

- No login required.
- No interactive screens.
- No direct API usage in MVP.

### BAR_OUTPUT

Non-interactive operational role for ticket destination semantics only.

Main responsibilities:

- Receive printed bar production tickets.
- Receive printed bar void/correction slips.

Restrictions:

- No login required.
- No interactive screens.
- No direct API usage in MVP.

## Functional permission matrix

Legend:
- Allow = permitted in MVP.
- Deny = not permitted in MVP.
- Conditional = permitted only under specific business-rule or workflow conditions.

| Capability | Server | Cashier | Admin | Kitchen output | Bar output |
|---|---|---|---|---|---|
| Sign in to app | Allow | Allow | Allow | Deny | Deny |
| Switch UI language | Allow | Allow | Allow | Deny | Deny |
| View current session context | Allow | Allow | Allow | Deny | Deny |
| View floor occupancy | Allow | Allow | Allow | Deny | Deny |
| Open billing group | Allow | Deny | Allow | Deny | Deny |
| Edit billing-group notes | Allow | Conditional | Allow | Deny | Deny |
| Change billing-group status | Allow | Conditional | Allow | Deny | Deny |
| Assign occupied zones | Allow | Deny | Allow | Deny | Deny |
| Release occupied zones | Conditional | Allow | Allow | Deny | Deny |
| View billing-group detail | Allow | Allow | Allow | Deny | Deny |
| Search billing groups | Conditional | Allow | Allow | Deny | Deny |
| Create orders | Allow | Deny | Allow | Deny | Deny |
| Void/correct submitted items | Conditional | Deny | Allow | Deny | Deny |
| Trigger production print via order submit | Allow | Deny | Allow | Deny | Deny |
| View production ticket history | Conditional | Conditional | Allow | Deny | Deny |
| Print internal bill | Deny | Allow | Allow | Deny | Deny |
| Reprint bill | Deny | Allow | Allow | Deny | Deny |
| Record partial payment | Deny | Allow | Allow | Deny | Deny |
| Reopen billing group | Conditional | Conditional | Allow | Deny | Deny |
| View event log summary | Deny | Conditional | Allow | Deny | Deny |
| View full event log | Deny | Deny | Allow | Deny | Deny |
| Configure venue structure | Deny | Deny | Allow | Deny | Deny |
| Configure billing statuses | Deny | Deny | Allow | Deny | Deny |
| Configure printers | Deny | Deny | Allow | Deny | Deny |
| Change printer routes during service | Deny | Deny | Allow | Deny | Deny |
| Trigger printer test prints | Deny | Deny | Allow | Deny | Deny |
| Manage users | Deny | Deny | Allow | Deny | Deny |
| Manage roles | Deny | Deny | Allow | Deny | Deny |
| Manage translations | Deny | Deny | Allow | Deny | Deny |
| Generate accounting export | Deny | Deny | Allow | Deny | Deny |

## Conditional permission rules

### RP-001 — Server search access
Servers may search billing groups only if the UI needs it for operational recovery or reopen workflows on larger devices. Floor-first access remains the preferred path for servers.

### RP-002 — Cashier status edits
Cashiers may change billing-group status only where that status change is part of the checkout flow, such as moving a group toward CHECK_REQUESTED, PARTIALLY_PAID, or CLOSED according to business rules.

### RP-003 — Server and cashier release of occupied zones
Servers may release occupied zones only if the related billing-group workflow explicitly allows it and only while preserving auditability. Cashiers may release zones from the checkout page. Admin may always perform corrective release where needed.

### RP-004 — Server void/correction permissions
Servers may void or correct submitted items only if the current workflow allows it and the action generates the required void/correction print and audit trail.

### RP-005 — Reopen permissions
Server and cashier reopen permissions are conditional. The implementation should enforce business rules on eligible states, and admin should retain override authority inside MVP.

### RP-006 — Limited cashier history access
Cashiers may access only the billing-history and print-history context necessary to resolve checkout and dispute questions. Full event-log access remains admin-only.

## Detailed permissions by area

## 1. Session and authentication

### SERVER
- Can authenticate.
- Can maintain an active session during service.
- Can use supported UI languages.

### CASHIER
- Can authenticate.
- Can maintain an active session during checkout operations.
- Can use supported UI languages.

### ADMIN
- Can authenticate.
- Can maintain an active session for setup, monitoring, and export.
- Can use supported UI languages.

### KITCHEN_OUTPUT / BAR_OUTPUT
- No authentication required.
- No direct interface access in MVP.

## 2. Floor and occupancy

### SERVER
Allowed:
- View floor occupancy.
- Open billing groups.
- Assign one or more occupied zones to new groups.
- View which ranges are free or occupied.
- Open an existing billing group from the floor.

Conditional:
- Release zones if that workflow is later exposed in MVP and business rules permit it.

Denied:
- Structural venue reconfiguration.

### CASHIER
Allowed:
- View occupancy context attached to a billing group.

Denied:
- Opening billing groups.
- Assigning or editing occupied zones.

### ADMIN
Allowed:
- View floor occupancy.
- Perform corrective intervention where needed.
- Configure venue structure outside live service workflow.

## 3. Billing-group lifecycle

### SERVER
Allowed:
- Create billing groups.
- Set initial billing-group status.
- Edit permitted statuses during service.
- Reopen if allowed.

Denied:
- Final billing closure actions that depend on cashier-side settlement workflow, unless admin policy explicitly permits.

### CASHIER
Allowed:
- View billing-group state.
- Move a group through checkout-related statuses where allowed.
- Reopen if allowed.
- Mark group toward closure through billing workflow.

### ADMIN
Allowed:
- Override or correct status as needed.
- Reopen and close as necessary within administrative authority.

## 4. Orders and production printing

### SERVER
Allowed:
- Create orders at billing-group or zone level.
- Apply delivery defaults or seat-pair overrides.
- Submit orders that trigger kitchen/bar ticket generation.
- Void/correct items where allowed.

Denied:
- Manual printer-route reconfiguration.
- Printer test-print functions.

### CASHIER
Denied by default:
- Create orders.
- Submit production tickets.

### ADMIN
Allowed:
- Perform corrective operational actions if needed.
- Inspect production-ticket records.
- Reprint or diagnose production records where implementation permits.

## 5. Billing and payment

### SERVER
Denied:
- Print customer bills in MVP.
- Record partial payment.

### CASHIER
Allowed:
- View bill summary.
- Print internal bill.
- Reprint internal bill.
- Record partial payment.
- View remaining balance.

### ADMIN
Allowed:
- Perform all cashier billing functions.
- Investigate or correct issues within administrative authority.

## 6. Reprints, voids, and sensitive actions

### Server
- May trigger void/correction slips only through allowed order workflows.
- May not reprint customer bills.
- May not trigger printer test prints.

### Cashier
- May reprint internal bills.
- May not change printer routes.
- May not trigger printer test prints.

### Admin
- May reprint bills.
- May trigger test prints.
- May change printer routes during service.
- May investigate queued or failed print jobs.

## 7. Event log and history

### SERVER
Allowed:
- View only the operational history context directly attached to active billing-group work, if exposed by UI.

Denied:
- Full event-log browsing.

### CASHIER
Allowed:
- View limited billing-history context needed to handle checkout issues.

Denied:
- Full event-log browsing by default.

### ADMIN
Allowed:
- View full event log.
- Filter by session, group, event type, and time range.
- Review print, payment, and reopen history.

## 8. Configuration and administration

### SERVER
Denied:
- Venue structure changes.
- Billing-status configuration.
- Printer configuration.
- Menu routing configuration.
- User management.
- Role management.
- Translation management.
- Export configuration.

### CASHIER
Denied:
- All administrative configuration by default.

### ADMIN
Allowed:
- Venue structure configuration.
- Billing-status configuration.
- Printer configuration.
- Menu-item routing configuration.
- User management.
- Role management.
- Translation management.
- Accounting export configuration and execution.

## Role-specific screen access

This section maps roles to the MVP screens defined elsewhere.

| Screen | Server | Cashier | Admin |
|---|---|---|---|
| Login / session entry | Allow | Allow | Allow |
| Floor | Allow | Conditional | Allow |
| Create/Edit Billing Group | Allow | Deny | Allow |
| Billing Group Detail | Allow | Allow | Allow |
| Order Entry | Allow | Deny | Allow |
| Billing Group Lookup | Conditional | Allow | Allow |
| Checkout | Deny | Allow | Allow |
| Reprint / document actions | Deny | Allow | Allow |
| Venue Setup | Deny | Deny | Allow |
| Printer Setup | Deny | Deny | Allow |
| User and Role Management | Deny | Deny | Allow |
| Billing Status Configuration | Deny | Deny | Allow |
| Event Log | Deny | Conditional | Allow |
| Accounting Export | Deny | Deny | Allow |
| Settings / language switch | Allow | Allow | Allow |

## Recommended permission implementation model

The MVP should implement permissions in layers:

- Role-level default permissions.
- Endpoint-level authorization.
- UI-level visibility restrictions.
- Business-rule validation for conditional actions.

This means a button hidden in the UI is not enough by itself. Sensitive operations should also be blocked in the API and validated in the domain logic.

## Suggested permission codes

A simple permission-code scheme can help implementation even if the app starts with role defaults only.

Suggested permission codes:

- `floor.view`
- `billing_group.create`
- `billing_group.update_status`
- `billing_group.reopen`
- `occupied_zone.assign`
- `occupied_zone.release`
- `order.create`
- `order.void_correct`
- `bill.print`
- `bill.reprint`
- `payment.record`
- `event_log.view_limited`
- `event_log.view_full`
- `printer.configure`
- `printer.test`
- `printer.route_change`
- `venue.configure`
- `status.configure`
- `user.manage`
- `role.manage`
- `translation.manage`
- `accounting_export.generate`

## Security and operational notes

- Admin should be the only role able to change routing during service, because print-routing errors can directly disrupt kitchen and cashier operations.
- Cashier should be the only non-admin role allowed to print customer bills in MVP.
- Server reopen permissions should be narrow and validated by business rules, because reopen affects billing integrity.
- Non-interactive kitchen/bar roles should not accidentally be provisioned as normal login users unless later scope explicitly changes.

## Open decisions for later refinement

The following choices may be refined later without changing the core MVP role model:

- Whether cashier should have broader event-history visibility.
- Whether server should be allowed to search billing groups on small devices.
- Whether admins need explicit override logs separate from normal audit events.
- Whether production-ticket reprints should be admin-only or shared with another role.
- Whether station-based permissions should be added in addition to role-based permissions.
