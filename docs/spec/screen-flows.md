# Screen Flows: Recurring Event Service and Billing App

## Purpose

This document defines the MVP screen set, navigation structure, and primary user flows for the recurring event service and billing app. It is aligned with the current scope and acceptance criteria: local-network web/PWA delivery, server/kitchen/cashier/admin roles, internal billing, printed kitchen and bar workflows, multilingual UI, accounting export, and full event logging.[cite:152]

The goal of this document is to describe what screens exist, who uses them, what each screen is for, and how users move between screens during live service. It is not a visual design document and does not prescribe final layout or styling.[cite:152]

## Role model for MVP

The MVP has four named roles in the product scope, but only three roles actively use the application interface during service:[cite:152]

- Server, who also acts as host.
- Cashier.
- Admin.
- Kitchen/bar as operational output roles only, receiving printed tickets and not using the app directly in MVP.[cite:152]

Because kitchen and bar are non-interactive in MVP, this document includes no kitchen station screen or bar station screen. All kitchen and bar workflow in MVP is represented through ticket output generated from server and cashier actions.[cite:152]

## Navigation model

The app should use role-based navigation, showing only the screens needed by the current user. This keeps service fast and reduces operator confusion during a high-throughput event.[cite:152]

Recommended top-level navigation by role:

| Role | Primary screens |
|---|---|
| Server | Floor, Billing Group Detail, Order Entry, Bill Preview/Request, Menu, Reopen if permitted |
| Cashier | Billing Group Lookup, Billing Group Detail, Checkout, Reprint, Menu, Reopen |
| Admin | Venue Setup, Printer Setup, User/Roles, Status Setup, Event Log, Export |

On small screens, the app should behave like a task-first PWA, with the current screen optimized for one main job at a time. On larger screens, related panels may be shown side by side, but the flow logic should remain the same.[cite:152]

## Screen inventory

The MVP screen set should contain at least these screens:

1. Login / session entry.
2. Floor screen.
3. Create/Edit Billing Group screen or panel.
4. Billing Group Detail screen.
5. Order Entry screen or drawer.
6. Billing Group Lookup screen.
7. Checkout screen.
8. Reprint / document actions panel.
9. Menu catalog screen.
10. Venue Setup screen.
11. Printer Setup screen.
12. User and Role Management screen.
13. Billing Status Configuration screen.
14. Event Log screen.
15. Accounting Export screen.
16. Settings / language switch area.[cite:152]

## Screen definitions

### 1. Login / session entry

**Users:** server, cashier, admin.[cite:152]

**Purpose:** start a session and enter the role-appropriate workspace.

**Key actions:**
- Sign in.
- Select language if needed.
- Enter the application.

**Flow rules:**
- After login, users land on the most relevant default screen for their role.
- Servers should land on the Floor screen.
- Cashiers should land on the Billing Group Lookup screen.
- Admins may land on an admin home or directly on the Venue Setup/Event Log area.[cite:152]

### 2. Floor screen

**Users:** server, cashier.[cite:152]

**Purpose:** show live occupancy and act as the main operating surface for seating and service.

**Key content:**
- Sections.
- Rows.
- Seat ranges.
- Free versus occupied visual states.
- Billing-group identifier on occupied ranges.
- Billing-group status on occupied ranges.[cite:152]

**Key actions:**
- Select free seat range.
- Open a new billing group.
- Open an existing billing group.
- View range details.
- Reopen a billing group if permitted.

**Primary flows:**
- Floor -> Create/Edit Billing Group.
- Floor -> Billing Group Detail.
- Floor -> Order Entry, via selected billing group.

### 3. Create/Edit Billing Group screen or panel

**Users:** server, cashier.[cite:152]

**Purpose:** create a new billing group or edit the occupancy and status of an existing one.

**Key content:**
- Billing-group identifier.
- Current status.
- Assigned occupied zones.
- Assigned server per newly created zone when the actor is a cashier.
- Optional notes.

**Key actions:**
- Assign one or more free seat ranges.
- Change status.
- Save.
- Cancel.

**Flow rules:**
- This should open from the Floor screen.
- On save success, the user returns to the Floor screen or Billing Group Detail screen.
- If selected ranges conflict with existing occupancy, the save must be blocked and the conflict clearly shown.[cite:152]

### 4. Billing Group Detail screen

**Users:** server, cashier.[cite:152]

**Purpose:** act as the core operational record for one billing group.

**Key content:**
- Billing-group identifier.
- Current status.
- All occupied zones attached to the group.
- Current ordered items.
- Running totals.
- Recent activity.
- Print and billing actions.[cite:152]

**Key actions for server:**
- Add order.
- Inspect zones.
- Reopen if allowed.
- Request bill print.

**Key actions for cashier:**
- Open billing groups from the floor.
- Add order.
- Add occupied zones.
- Review charges.
- Print internal bill.
- Register partial payment.
- Reopen.
- Reprint bill.

**Primary flows:**
- Billing Group Detail -> Order Entry.
- Billing Group Detail -> Checkout.
- Billing Group Detail -> Reprint / document actions.

### 5. Order Entry screen or drawer

**Users:** server, cashier.[cite:152]

**Purpose:** add order items quickly from phone or tablet while standing on the floor.

**Key content:**
- Current billing group.
- Optional zone selector.
- Menu item entry.
- Delivery target summary.
- Printer routing preview or destination indication.[cite:152]

**Key actions:**
- Add items.
- Choose occupied zone when needed.
- Accept default center delivery target.
- Override with specific seat pair.
- Submit order.
- Void/correct eligible items.

**Flow rules:**
- This should open in a lightweight form optimized for quick entry.
- On submit, the system routes food and drinks to the correct ticket destinations.
- On success, the user returns to Billing Group Detail or remains ready for more entry depending on device context.[cite:152]

### 6. Billing Group Lookup screen

**Users:** cashier, optionally server on larger devices.[cite:152]

**Purpose:** locate an open billing group quickly for checkout or review.

**Key content:**
- Search field.
- Result list.
- Group identifiers.
- Status.
- Zone summary.

**Key actions:**
- Search.
- Filter to open groups.
- Open selected billing group.

**Flow rules:**
- This is the cashier's default landing screen.
- Search results should give enough context to choose the right billing group quickly during live service.[cite:152]

### 7. Checkout screen

**Users:** cashier.[cite:152]

**Purpose:** complete internal billing actions for one billing group.

**Key content:**
- Billing-group identifier.
- Ordered items.
- Totals.
- Occupied-zone summary.
- Payment history inside the app.
- Remaining balance.[cite:152]

**Key actions:**
- Print bill.
- Register partial payment.
- Reopen if needed.
- Finish internal checkout state.

**Flow rules:**
- This screen must not imply integrated card or cash-terminal payment because external payment systems are out of scope.
- The screen records operational payment state only.[cite:152]

### 8. Reprint / document actions panel

**Users:** cashier, admin, possibly server depending on permissions.[cite:152]

**Purpose:** centralize bill reprints and ticket/document reissues.

**Key content:**
- Available printable artifacts for the selected billing group or order.
- Reprint markers.
- Void-slip actions where allowed.

**Key actions:**
- Reprint bill.
- Reprint ticket where applicable.
- Trigger void/correction slip where applicable.

**Flow rules:**
- Reprints and void outputs must be clearly marked and logged.[cite:152]

### 9. Menu catalog screen

**Users:** server, cashier, admin.[cite:152]

**Purpose:** browse the full menu catalog outside of the order entry flow.

**Key content:**
- Active menu categories with their display names and route types.
- Active menu items grouped by category, showing display names, prices, and SKUs.
- Active variants for each item where applicable.
- Active modifier sets and modifiers for each item where applicable, including default modifier indicators.

**Key actions:**
- View and scroll through the menu catalog.
- No order submission, cart, or management actions are available.

**Flow rules:**
- This screen is read-only and does not allow order creation or menu editing.
- All interactive roles may access it.
- Kitchen and bar output roles do not have interactive access to this screen in MVP.

### 10. Venue Setup screen

**Users:** admin.[cite:152]

**Purpose:** define the physical venue structure used by the whole app.

**Key content:**
- Sections.
- Rows.
- Seats.
- Seat pairs.
- Layout ordering.

**Key actions:**
- Create, edit, and disable venue structure elements.
- Validate seat-pair definitions.

**Flow rules:**
- This screen is configuration-oriented and not expected to be used during live service except by administrators.

### 11. Printer Setup screen

**Users:** admin.[cite:152]

**Purpose:** configure routing of kitchen tickets, bar tickets, bills, and void slips.

**Key content:**
- Printer destinations.
- Document types.
- Routing rules.

**Key actions:**
- Assign kitchen printer.
- Assign bar printer.
- Assign bill printer.
- Assign void-slip printer.
- Test routing if supported.

**Flow rules:**
- Printer auto-discovery is out of scope, so the screen should assume manual configuration.[cite:152]

### 12. User and Role Management screen

**Users:** admin.[cite:152]

**Purpose:** manage who can use the system and what they can do.

**Key content:**
- Users.
- Roles.
- Permissions summary.

**Key actions:**
- Create user.
- Edit user.
- Assign role.
- Enable or disable access.

**Flow rules:**
- Kitchen/bar may be represented as non-login operational roles in MVP if useful for configuration, but they do not need interactive UI access in service mode.[cite:152]

### 13. Billing Status Configuration screen

**Users:** admin.[cite:152]

**Purpose:** define the valid statuses available for billing groups during operations.

**Key content:**
- Status list.
- Active/inactive state.
- Display order.

**Key actions:**
- Add status.
- Edit status.
- Disable status.

### 14. Event Log screen

**Users:** admin, optionally cashier for investigation.[cite:152]

**Purpose:** provide full audit visibility across seating, ordering, printing, billing, and export actions.

**Key content:**
- Timestamp.
- User.
- Event type.
- Billing-group identifier.
- Zone reference where relevant.
- Action summary.[cite:152]

**Key actions:**
- Search.
- Filter.
- Inspect event details.

**Flow rules:**
- This screen should support operational investigation and post-event auditing.

### 15. Accounting Export screen

**Users:** admin.[cite:152]

**Purpose:** generate export data for external accounting workflows.

**Key content:**
- Export period.
- Export type.
- Export status.
- Prior export history if available.

**Key actions:**
- Select export range.
- Generate export.
- Download export.

### 16. Settings / language switch area

**Users:** all interactive app users.[cite:152]

**Purpose:** support operational preferences needed in MVP, especially language.

**Key actions:**
- Switch between pt-pt and en-us.
- Access session-level preferences as needed.

**Flow rules:**
- Language switching should be available without leaving the operational workflow for longer than necessary.[cite:152]

## Primary flow maps

### Flow A — Seat a new group and start service

1. Server opens the Floor screen.
2. Server identifies free seat ranges.
3. Server opens Create/Edit Billing Group.
4. Server assigns one or more seat ranges.
5. Server sets initial billing-group status.
6. System saves the group and returns to Floor or Billing Group Detail.
7. Server proceeds to Order Entry if service begins immediately.[cite:152]

### Flow B — Add an order and print tickets

1. Server opens Billing Group Detail.
2. Server opens Order Entry.
3. Server adds items.
4. Server optionally selects an occupied zone.
5. System assigns default center delivery target unless the server chooses a specific seat pair.
6. Server submits the order.
7. System routes drinks to bar ticket output and food to kitchen ticket output.
8. Server returns to the billing-group context.[cite:152]

### Flow C — Void or correct an item

1. Server opens the relevant billing group or recent order context.
2. Server selects an eligible item for void or correction.
3. Server confirms the action.
4. System records the change.
5. System prints the relevant void or correction slip.
6. Event log records the action.[cite:152]

### Flow D — Find a group and print a bill

1. Cashier opens Billing Group Lookup.
2. Cashier searches for the group.
3. Cashier opens Billing Group Detail or Checkout.
4. Cashier verifies occupied zones and charges.
5. Cashier prints an internal customer bill.
6. Print event is logged.[cite:152]

### Flow E — Partial payment and reopen

1. Cashier opens Checkout.
2. Cashier records a partial payment.
3. System updates the remaining balance and keeps history.
4. If more service is needed, cashier or authorized server reopens the billing group.
5. Server resumes order entry on that group.
6. Reopen event is logged.[cite:152]

### Flow F — Configure the venue before service

1. Admin opens Venue Setup.
2. Admin defines sections, rows, seats, and seat pairs.
3. Admin saves the layout.
4. Admin opens Printer Setup.
5. Admin configures destinations for kitchen tickets, bar tickets, bills, and void slips.
6. Admin configures roles, statuses, and language availability as needed.[cite:152]

### Flow G — Review history and export data after service

1. Admin opens Event Log.
2. Admin filters the service period.
3. Admin investigates any needed actions or disputes.
4. Admin opens Accounting Export.
5. Admin generates and downloads the export for external accounting workflows.[cite:152]

## Mobile versus larger-screen behavior

The same functional flows should exist on phone, tablet, and computer, but the interaction style should adapt to device size.[cite:152]

Recommended behavior:

- On phone: one primary task screen at a time; order entry as a focused screen or bottom sheet.
- On tablet: floor plus contextual detail panel where possible.
- On desktop: list/detail or floor/detail layouts for cashier and admin tasks.

The mobile form should optimize for fast floor work by servers, while larger screens should optimize for visibility and review by cashiers and admins.[cite:152]

## Out-of-scope screens for MVP

These screens should not exist in version 1 unless scope changes:[cite:152]

- Reservation management screen.
- Customer-facing payment screen.
- Integrated payment terminal screen.
- Inventory or stock management screen.
- Analytics dashboard.
- Online ordering screen.
- QR ordering screen.
- Interactive kitchen display screen.
- Interactive bar production screen.

## Notes for later refinement

This document defines what screens and flows should exist in MVP, but not the final component layout, visual hierarchy, or interaction details at wireframe level. A future wireframe or UX document should refine the exact arrangement of controls, especially for the Floor, Billing Group Detail, Order Entry, and Checkout screens.[cite:152]

Business-rules documentation should also refine edge cases that affect screen behavior, including concurrency conflicts, eligibility to reopen, and how mixed food/drinks orders are visually confirmed before submission.[cite:152]
