# Seed Data Notes

This file package provides baseline seed data for local MVP development of the recurring event service and billing app.

Included in the JSON seed:

- One venue.
- One active service session.
- Roles and sample users.
- Billing statuses.
- Venue layout with sections, rows, seats, and seat pairs.
- Printers and printer routes.
- Cashier printer assignments.
- Menu categories and menu items.
- Basic translations.
- Sample open billing groups.
- Sample orders, production tickets, bills, payments, and audit events.

## Testing note

Automated tests should generate additional scenario-specific data when necessary instead of relying only on the static seed set.

Use this seed as a shared baseline for common fixtures, but create fresh test data for edge cases such as:

- Occupancy conflicts.
- Reopen after partial payment.
- Printer failures and queued jobs.
- Mixed kitchen/bar routing.
- Reprints and void slips.
- Concurrent edits by multiple servers.
