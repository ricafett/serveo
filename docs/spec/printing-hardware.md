# Printing and Hardware: Recurring Event Service and Billing App

## Purpose

This document defines the MVP printing and print-hardware requirements for the recurring event service and billing app. It covers printer roles, document routing, connectivity, queueing behavior, failure handling, permissions, and startup procedures for a local-network food-service operation.

The printing system is a core operational subsystem in MVP because kitchen and bar staff do not interact with the app directly and instead rely on printed paper tickets.

## MVP printer inventory

The MVP target layout is:

- 1 kitchen printer.
- 1 bar printer.
- 1 printer per cashier station.
- No separate dedicated bill printer beyond the cashier printers.

This produces three printer roles in the minimum deployment:

- Kitchen production printer.
- Bar production printer.
- Cashier document printer, one per cashier.

## Printer hardware requirements

### Required printer characteristics

All MVP printers should meet these baseline requirements:

- 80mm thermal receipt printer.
- Auto-cutter support.
- Reliable continuous use during meal service.
- Support for direct LAN/network printing where available.
- Optional support for USB connection where direct LAN is not used.
- No dependency on Wi-Fi or Bluetooth.

### Preferred connectivity order

Connectivity priority for MVP is:

1. Direct LAN/Ethernet printing from the backend to the printer.
2. USB-connected printer exposed through a local print agent/service on one PC.

Wi-Fi and Bluetooth are explicitly out of scope for the MVP printing model.

### Hardware strategy

The system should remain hardware-agnostic in the product documentation because no specific printers have been purchased yet. However, the MVP design assumes standard 80mm thermal ESC/POS-style receipt printers or equivalent devices that can be reached consistently over Ethernet or USB print-agent workflows.

## Printer roles and document ownership

### Kitchen printer

The kitchen printer receives production tickets for menu items routed to kitchen preparation.

Documents allowed on this printer:

- Kitchen production tickets.
- Kitchen void/correction slips.
- Kitchen ticket reprints, if reprint support is enabled for production documents later.

### Bar printer

The bar printer receives production tickets for menu items routed to bar or drinks preparation.

Documents allowed on this printer:

- Bar production tickets.
- Bar void/correction slips.
- Bar ticket reprints, if reprint support is enabled for production documents later.

### Cashier printers

Each cashier station has its own printer. These printers are used for customer-facing and cashier-facing billing documents.

Documents allowed on cashier printers:

- Internal customer bills.
- Bill reprints.
- Cashier-side checkout printouts, if later added.

Customer bills in MVP are printed by cashiers only.

## Routing rules

### Menu-item routing

Routing to kitchen or bar is determined per menu item by admin configuration.

This means:

- The destination is not inferred only from category unless the menu model later uses categories as helpers.
- Each menu item must have an explicit configured production destination for MVP.
- Valid production destinations are kitchen or bar.

### Billing-document routing

Internal customer bills must print only to the cashier printer of the cashier currently handling the billing action.

### Void and correction routing

Void and correction slips must print to the same destination as the original production ticket.

This means:

- Kitchen voids go to the kitchen printer.
- Bar voids go to the bar printer.

### Reprint permissions

Bill reprints may be triggered only by:

- Cashier.
- Admin.

If production-ticket reprint is later exposed in the app, permission should default to admin unless explicitly extended.

## Connectivity model

### Preferred backend printing model

The preferred MVP printing model is direct LAN printing from the backend to each network-capable printer.

This model assumes:

- The app backend can open connections to printers on the local network.
- Printers have stable IP addresses or equivalent stable addressing.
- Admin manually configures printer destinations in the application.

### Secondary print-agent model

The MVP should also allow a secondary print path using a local print agent or print service running on one PC.

This model is intended for cases where:

- A printer is USB-only.
- Direct LAN printing is not practical.
- The operating environment requires one PC to mediate print jobs.

In this model, the backend sends print jobs to the print agent, and the print agent forwards them to the local USB printer.

### Unsupported connectivity

The MVP should not depend on:

- Wi-Fi printer discovery.
- Bluetooth printing.
- Consumer mobile-device direct printing.

## Print queue and failure handling

### Queue requirement

If a printer is unavailable or a print attempt fails, the job must remain in a queue instead of being silently discarded.

### Failure behavior

When a print fails:

- The print job must remain traceable in system state.
- The job must be marked as pending, failed, or retryable according to implementation.
- The user must be able to see that the document was not successfully printed.
- The system must not falsely mark the document as fully printed.

### Retry behavior

The system should support controlled retry from the queued or failed state.

Recommended MVP behavior:

- Automatic retry may be allowed by the print subsystem.
- Manual retry or reprint must remain available for authorized roles.
- Queue persistence should survive ordinary transient connection problems during service.

### Queue ownership by destination

Each destination should behave like a logical print queue:

- Kitchen queue.
- Bar queue.
- Cashier queue per cashier printer.

This makes failure isolation clearer and avoids one failed printer blocking unrelated destinations.

## Ticket and bill formatting requirements

### Kitchen and bar ticket content

Production tickets should include at least:

- Ticket type, kitchen or bar.
- Timestamp.
- Billing-group identifier.
- Occupied-zone reference when applicable.
- Default delivery reference based on zone center, or specific seat-pair reference when overridden.
- Ordered items and quantities.
- Server identifier or name.
- Reprint or void/correction marking when applicable.

### Bill content

Internal customer bills should include at least:

- Billing-group identifier.
- Ordered items.
- Quantities.
- Prices.
- Totals.
- Payment records to date, if the UI/business flow chooses to show them.
- Remaining balance where relevant.
- Reprint marking when applicable.

### Legibility rules

Printed output should prioritize operational clarity over branding.

This implies:

- Large enough key identifiers for fast reading.
- Clear separation between header, items, and footer.
- Prominent marking of voids and reprints.
- Consistent formatting across all printers of the same role.

## Admin configuration requirements

### Printer records

Admin must be able to configure each printer with enough information to print reliably.

Recommended printer fields include:

- Printer name.
- Printer role.
- Connection type, LAN or print-agent/USB.
- Network address and port for LAN printers.
- Print-agent target identifier for agent-managed printers.
- Active/inactive state.

### Menu-item destination setup

Admin assigns a production destination at the menu category level (`MenuCategory.route_type`). Each menu item inherits its fulfillment route from its category (Kitchen, Bar, or None). This simplifies configuration while keeping routing explicit per item at creation time.

Minimum valid values:

- Kitchen.
- Bar.

A menu item intended for live service should not be sellable unless its parent category has a configured production routing.

### Cashier printer assignment

Each cashier user account is associated with one cashier printer for bill output via `CashierPrinterAssignment`. The system resolves the printer by the authenticated cashier user, falling back to any active `BILL` printer if no explicit assignment exists.

### Test prints

Admin is the only role allowed to:

- Trigger printer test prints.
- Change printer routes during service.
- Change printer definitions.

## Startup and operational procedures

### Pre-service startup checklist

Before each service, admin or designated setup staff should:

1. Confirm all printers are powered on.
2. Confirm paper is loaded in each printer.
3. Confirm cutter operation on each printer.
4. Confirm network or USB/agent connectivity.
5. Run test prints for kitchen, bar, and each cashier printer.
6. Confirm correct routing by destination.
7. Resolve any failed tests before opening service.

### During-service printer changes

During service, only admin may:

- Reassign routes.
- Disable a failed printer.
- Change printer definitions.
- Trigger test prints.

Operational staff should not lose the ability to continue service because of a single printer issue, but the system must preserve queued jobs and visible failure state until admin intervention or recovery happens.

### Paper and cutter maintenance

Because all printers use 80mm thermal paper with auto-cutter, the operational checklist should include:

- Adequate spare paper on site.
- Cutter jam checks if tickets stop separating cleanly.
- Replacement procedure known to cashier/admin staff.

## Permissions summary

### Server

Server may:

- Trigger production printing indirectly by submitting orders.
- Trigger void/correction slips when allowed by app rules.

Server may not:

- Change printer routing.
- Trigger printer test prints.
- Print customer bills in MVP.

### Cashier

Cashier may:

- Print internal customer bills.
- Reprint bills.

Cashier may not:

- Change printer routing.
- Trigger printer test prints.

### Admin

Admin may:

- Configure printers.
- Configure menu-item destinations.
- Trigger test prints.
- Change printer routes during service.
- Reprint bills.
- Investigate print failures and queued jobs.

## Recommended data points for implementation

The printing subsystem should persist at least:

- Printer identity and configuration.
- Printer role.
- Connection mode.
- Queue state per print job.
- Original print timestamp.
- Retry or reprint attempts.
- Destination printer used.
- Print result status.
- User who triggered the print-related action, when applicable.

## Open implementation decisions

These points should be finalized later in architecture or technical design:

- Exact print protocol for LAN printers.
- Exact print-agent contract for USB printers.
- Whether the backend renders tickets as raw ESC/POS, HTML-to-printer, PDF, or another format.
- Whether queued jobs are stored in the main application database, a job queue, or both.
- Whether cashier printers are bound to user accounts, physical stations, or both.

## Non-goals for MVP

The printing and hardware scope for MVP does not include:

- Wi-Fi printing.
- Bluetooth printing.
- Automatic printer discovery.
- Interactive kitchen display stations.
- Interactive bar display stations.
- Customer self-print workflows.
- Cloud print routing outside the local network.
