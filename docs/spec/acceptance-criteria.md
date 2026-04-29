# Acceptance Criteria: Recurring Event Service and Billing App

## Purpose

This document defines MVP acceptance criteria for the approved user stories of the recurring event service and billing app. It is aligned with the current product scope: local-network web/PWA delivery, end-to-end service workflow, internal billing, paper ticket printing, multilingual UI, accounting export, and full event logging.[cite:152]

These criteria are written for validation of behavior, not implementation design. They are intended to support agent-driven development, manual testing, and later task breakdown.[cite:152]

## Global assumptions

The following assumptions apply to all acceptance criteria in this document:[cite:152]

- The application runs on the local network only.
- The application is accessed through web/PWA clients on computer, tablet, and phone.
- Kitchen and bar staff do not interact with the application directly in MVP; their operational interface is printed tickets only.
- Billing is internal-only in MVP; legal invoicing is handled outside this application.
- Payments are tracked operationally, but integrated payment processing is out of scope.
- Reservations are not a dedicated subsystem in MVP; pre-service planning is represented through status and early billing-group creation when needed.

## EPIC-01 Floor and seating management

### AC-001 — Open billing group with seat ranges

Related story: US-001.[cite:152]

- Given a server is authenticated and has permission to manage floor seating,
- When the server creates a new billing group and selects one or more free seat ranges,
- Then the system shall create the billing group and associate it with the selected occupied zones.
- Given at least one selected seat range overlaps an already occupied range,
- When the server attempts to save the billing group,
- Then the system shall block the action and identify the conflicting range.
- Given a billing group is successfully created,
- When the save is completed,
- Then the selected ranges shall become unavailable for other open billing groups.
- Given a billing group is successfully created,
- When the action is completed,
- Then the creation event shall be written to the event log with user, timestamp, and affected zones.

### AC-002 — View free and occupied seat ranges

Related story: US-002.[cite:152]

- Given the floor view is open,
- When seat ranges are displayed,
- Then the system shall visually distinguish free and occupied ranges.
- Given a seat range is occupied,
- When a server inspects that range,
- Then the system shall show the associated billing group identifier and current status.
- Given occupancy changes occur,
- When the floor view refreshes or updates,
- Then the displayed availability shall reflect the latest saved state.

### AC-003 — Assign billing-group status

Related story: US-003.[cite:152]

- Given a server is creating or editing a billing group,
- When the server selects a valid status,
- Then the billing group shall save with that status.
- Given a billing group has a saved status,
- When other authorized users view that billing group,
- Then they shall see the current status consistently.
- Given a status changes,
- When the change is saved,
- Then the change shall be recorded in the event log with old value, new value, user, and timestamp.

## EPIC-02 Order capture and routing

### AC-004 — Add orders to a billing group or specific zone

Related story: US-004.[cite:152]

- Given a server opens an active billing group,
- When the server adds one or more order items without choosing a specific zone,
- Then the order shall be attached to the billing group.
- Given a billing group has multiple occupied zones,
- When the server chooses a specific zone while adding items,
- Then the order shall be attached to both the billing group and that occupied zone.
- Given the target billing group is closed,
- When the server attempts to add items,
- Then the system shall block order entry unless the group is reopened by an authorized user.
- Given order items are saved,
- When the action completes,
- Then the event log shall record the order creation with user, timestamp, billing group, and any selected zone.

### AC-005 — Link order to occupied zone when needed

Related story: US-005.[cite:152]

- Given a server is entering an order for a billing group with multiple occupied zones,
- When the server selects a zone,
- Then the order shall retain that zone reference.
- Given a zone-linked order exists,
- When the order appears in cashier context, ticket printing, or event history,
- Then the associated occupied zone shall remain visible.
- Given a selected zone does not belong to the chosen billing group,
- When the server attempts to save the order,
- Then the system shall reject the action.

### AC-006 — Default delivery target is zone center

Related story: US-006.[cite:152]

- Given an order is created for a billing group or occupied zone,
- When no specific delivery seat pair is selected,
- Then the system shall assign the default delivery target as the center of the occupied range.
- Given a default center target is assigned,
- When the order is printed or displayed in downstream contexts,
- Then the delivery reference shall be available for use on the ticket or bill detail as configured.

### AC-007 — Override delivery target with specific seat pair

Related story: US-007.[cite:152]

- Given a server is entering or editing an order,
- When the server selects a specific seat pair within the occupied zone,
- Then that seat pair shall override the default center delivery target.
- Given a selected seat pair is outside the referenced occupied zone,
- When the server attempts to save,
- Then the system shall reject the override.
- Given an override is saved,
- When the related ticket is printed,
- Then the ticket shall show the specific seat-pair delivery reference.

### AC-008 — Route drinks and food to the correct printer automatically

Related story: US-008.[cite:152]

- Given printer routing rules are configured,
- When an order contains food items,
- Then food items shall be routed to the kitchen ticket output.
- Given printer routing rules are configured,
- When an order contains drinks items,
- Then drinks items shall be routed to the bar ticket output.
- Given an order contains both food and drinks,
- When the order is submitted,
- Then the system shall produce separate outputs for each destination according to the routing rules.
- Given a required printer destination is not configured,
- When the order is submitted,
- Then the system shall prevent silent loss of the ticket and show an error or exception state to the authorized user.

### AC-009 — Void or correct an order item with printed void slip

Related story: US-009.[cite:152]

- Given an order item has already been sent to production,
- When an authorized server voids or corrects that item,
- Then the system shall record the change and generate a printed void or correction slip for the relevant destination.
- Given a void or correction slip is produced,
- When it is printed,
- Then it shall be clearly distinguishable from a normal production ticket.
- Given a void is performed,
- When the action completes,
- Then the event log shall capture the original item, the change type, the user, and the timestamp.

## EPIC-03 Kitchen and bar ticket handling

### AC-010 — Print tickets for food orders

Related story: US-010.[cite:152]

- Given a food order is submitted,
- When routing succeeds,
- Then a kitchen paper ticket shall be printed or queued for the kitchen printer.
- Given kitchen staff do not use the app directly in MVP,
- When the order reaches production,
- Then the printed ticket shall be sufficient for kitchen staff to act without needing an on-screen workflow.

### AC-011 — Print drinks tickets only for bar orders

Related story: US-011.[cite:152]

- Given an order contains drinks items,
- When the order is submitted,
- Then a bar ticket shall be printed or queued to the configured drinks destination.
- Given an order contains only food items,
- When the order is submitted,
- Then no drinks/bar ticket shall be produced.

### AC-012 — Tickets show billing group and occupied zone

Related story: US-012.[cite:152]

- Given a production ticket is generated,
- When it is printed,
- Then it shall include the billing-group identifier.
- Given the related order references an occupied zone,
- When the ticket is printed,
- Then the ticket shall include the occupied-zone reference.
- Given the order is only group-linked and not zone-linked,
- When the ticket is printed,
- Then the printed content shall still identify the group clearly enough to locate the order operationally.

### AC-013 — Tickets show default delivery point or specific seat pair

Related story: US-013.[cite:152]

- Given a ticket is printed for an order without a delivery override,
- When the ticket is generated,
- Then it shall show the default center-based delivery reference for the occupied zone.
- Given an order has a delivery override,
- When the ticket is generated,
- Then it shall show the specific seat-pair reference instead of the default center reference.

### AC-014 — Void slips and reprints are clearly marked

Related story: US-014.[cite:152]

- Given a production ticket is reprinted,
- When the reprint is generated,
- Then the printed output shall be clearly marked as a reprint.
- Given a void or correction slip is generated,
- When it is printed,
- Then the output shall be clearly marked as void or correction.
- Given a reprint or void slip occurs,
- When the action completes,
- Then the event log shall record the action type, user, timestamp, and related document or order reference.

## EPIC-04 Cashier billing and checkout

### AC-015 — Find open billing groups quickly

Related story: US-015.[cite:152]

- Given a cashier opens the billing lookup view,
- When the cashier searches or browses for an open billing group,
- Then the system shall return matching open groups quickly enough for live service use.
- Given multiple matching groups exist,
- When results are shown,
- Then each result shall provide enough identifying context to select the correct group.

### AC-016 — View occupied zones attached to a billing group

Related story: US-016.[cite:152]

- Given a cashier opens a billing group,
- When the billing detail is shown,
- Then the system shall display all occupied zones attached to that group.
- Given a billing group spans multiple occupied zones,
- When the cashier reviews the bill,
- Then the zone associations shall remain visible for verification.

### AC-017 — Print internal customer bill at any point

Related story: US-017.[cite:152]

- Given a billing group is open,
- When a cashier requests a bill print,
- Then the system shall generate an internal customer bill without closing the group automatically.
- Given a bill is printed,
- When the action completes,
- Then the print action shall be logged.

### AC-018 — Register partial payment

Related story: US-018.[cite:152]

- Given a billing group has an outstanding balance,
- When a cashier records a partial payment,
- Then the system shall reduce the remaining balance without closing the group unless the full balance is settled.
- Given a partial payment is saved,
- When the transaction completes,
- Then the event log shall record the amount, user, timestamp, and resulting remaining balance.

### AC-019 — Reopen billing group after partial checkout

Related story: US-019.[cite:152]

- Given a billing group has been partially checked out and remains eligible for reopen,
- When an authorized cashier reopens it,
- Then additional service and billing actions shall be allowed again.
- Given a reopen action occurs,
- When it is saved,
- Then the event log shall record the user, timestamp, and prior state.

### AC-020 — Reprint bill without altering history

Related story: US-020.[cite:152]

- Given a bill has already been printed,
- When a cashier requests a reprint,
- Then the system shall print a duplicate bill without changing line items, totals, or prior history.
- Given a reprint occurs,
- When the action completes,
- Then the document shall be marked as a reprint and the reprint action shall be logged.

### AC-021 — Keep all billing-group actions logged

Related story: US-021.[cite:152]

- Given a cashier performs a billing-related action,
- When the action completes,
- Then the system shall create an event-log record.
- Billing-related actions shall include at least bill print, bill reprint, partial payment, reopen, and any bill-affecting void or correction.[cite:152]

### AC-022 — Server can reopen a billing group after partial checkout

Related story: US-022.[cite:152]

- Given a billing group has been partially checked out,
- When an authorized server attempts to reopen it,
- Then the system shall allow the reopen action according to role permissions.
- Given the reopen succeeds,
- When the server returns to service flow,
- Then the billing group shall once again permit order entry.
- Given the reopen occurs,
- When the action completes,
- Then the event shall be written to the event log.

## EPIC-05 Admin configuration and control

### AC-023 — Define venue structure by section, row, seat, and seat pair

Related story: US-023.[cite:152]

- Given an admin is configuring the venue,
- When the admin defines sections, rows, seats, and seat pairs,
- Then the system shall save a floor structure that can be used for seat-range assignment.
- Given a seat pair definition would create invalid or overlapping structure,
- When the admin attempts to save,
- Then the system shall reject the invalid configuration.

### AC-024 — Configure printer routing

Related story: US-024.[cite:152]

- Given an admin opens printer configuration,
- When the admin maps destinations for kitchen tickets, bar tickets, bills, and void slips,
- Then the system shall save those routing rules.
- Given routing rules exist,
- When eligible documents are generated,
- Then the system shall use the configured routing automatically.

### AC-025 — Manage user roles

Related story: US-025.[cite:152]

- Given an admin manages users,
- When the admin assigns a role,
- Then the system shall enforce the permissions of that role.
- Given MVP role restrictions,
- When kitchen or bar users are configured,
- Then the system shall be able to represent them as non-interactive roles or non-login operational roles, consistent with the MVP assumption that they receive printed tickets only.[cite:152]

### AC-026 — Configure billing-group statuses

Related story: US-026.[cite:152]

- Given an admin manages status configuration,
- When the admin creates, edits, enables, or disables billing-group statuses,
- Then the system shall persist the allowed status vocabulary for operational use.
- Given a status is disabled,
- When users create or update billing groups,
- Then disabled statuses shall no longer be selectable.

### AC-027 — Export accounting data

Related story: US-027.[cite:152]

- Given service data exists for a completed period,
- When an admin requests accounting export,
- Then the system shall generate export data suitable for processing in external accounting software.
- Given the export completes,
- When the result is produced,
- Then the export action shall be logged.

### AC-028 — Support pt-pt and en-us UI languages

Related story: US-028.[cite:152]

- Given a supported user opens the application,
- When the selected language is pt-pt,
- Then the UI shall render in pt-pt for translatable interface strings.
- Given a supported user opens the application,
- When the selected language is en-us,
- Then the UI shall render in en-us for translatable interface strings.
- Given a string is missing for the selected language,
- When the screen is shown,
- Then the system shall fall back consistently to the configured fallback language rather than displaying a broken key.

## EPIC-06 Audit trail and event history

### AC-029 — Maintain a full event log

Related story: US-029.[cite:152]

- Given users perform operational or billing actions,
- When those actions complete,
- Then the system shall append them to the event log.
- The MVP event log shall cover at least seating changes, billing-group creation, status changes, order creation, ticket printing, ticket reprints, voids, partial payments, reopen actions, bill printing, and accounting exports.[cite:152]
- Given an authorized admin views the event log,
- When records are displayed,
- Then the system shall show enough detail to understand what happened, when it happened, and who performed the action.

## Cross-cutting acceptance rules

### AC-030 — Role-based access

- Servers shall be able to perform floor and order-entry functions.
- Cashiers shall be able to perform billing and checkout functions.
- Admins shall be able to perform configuration and export functions.
- MVP shall not require kitchen or bar staff to use interactive screens in order to receive and act on tickets.[cite:152]

### AC-031 — Print actions must be traceable

- Every bill print, bill reprint, kitchen ticket print, bar ticket print, and void-slip print shall be loggable as an event with timestamp and origin context.[cite:152]

### AC-032 — No silent state loss

- Actions that affect occupied zones, orders, billing, or printing shall either save successfully or produce a visible error state.
- The system shall not silently drop orders, ticket outputs, or billing changes.[cite:152]

## Notes for later refinement

These acceptance criteria define MVP behavior only. They do not yet fully specify edge-case rules such as seat-range merging rules, concurrency conflict handling between multiple servers on the same billing group, export formats, or future interactive kitchen workflows.[cite:152]

Those details should be refined later in business rules, role-permissions, and architecture documents.[cite:152]
