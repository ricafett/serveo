# API Contract: Recurring Event Service and Billing App

## Purpose

This document defines the MVP API contract for the recurring event service and billing app. It is aligned with the current product scope, user stories, acceptance criteria, screen flows, and data model: local-network web/PWA delivery, server/cashier/admin interactive roles, non-interactive kitchen/bar ticket output, internal billing, accounting export, multilingual UI, and full event logging.

The API is designed primarily for a web/PWA frontend used on phone, tablet, and desktop over a local network. It assumes a single backend application exposing authenticated HTTP APIs to first-party clients.

## API principles

- The API must reflect the real domain model, not generic restaurant tables.
- Physical layout and commercial state must remain separate.
- Billing groups are the primary operational and billing entity.
- Occupied zones are first-class references within a billing group.
- Printed outputs are first-class records, not hidden side effects.
- All important state-changing actions must be audit-loggable.
- The API must fail visibly rather than silently dropping orders, prints, or billing changes.

## Style and conventions

### Protocol
- Transport: HTTPS when feasible on the local network, otherwise HTTP on trusted LAN only during MVP.
- Format: JSON request and response bodies.
- Character encoding: UTF-8.
- Time format: ISO 8601 timestamps in UTC.
- Numeric money fields: decimal strings or fixed-precision numeric JSON values, depending on implementation.

### Base path
- Suggested base path: `/api/v1`

### Authentication
- Session cookie or bearer token, to be finalized in architecture.
- Every authenticated request resolves to one AppUser.
- Role-based access applies at endpoint level.

### Response envelope
A consistent envelope is recommended for mutable operations and error reporting.

Success example:
```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

Error example:
```json
{
  "success": false,
  "error": {
    "code": "ZONE_OVERLAP",
    "message": "Selected seat range overlaps an occupied zone.",
    "details": {}
  }
}
```

### Common error codes
- `UNAUTHENTICATED`
- `FORBIDDEN`
- `NOT_FOUND`
- `VALIDATION_ERROR`
- `CONFLICT`
- `ZONE_OVERLAP`
- `INVALID_DELIVERY_TARGET`
- `INVALID_STATUS_TRANSITION`
- `GROUP_CLOSED`
- `PRINTER_ROUTE_MISSING`
- `PRINT_FAILED`
- `VERSION_CONFLICT`

## Resource map

### Core operational resources
- `/auth/*`
- `/sessions`
- `/floor`
- `/billing-groups`
- `/occupied-zones`
- `/orders`
- `/production-tickets`
- `/billing-documents`
- `/payments`
- `/event-log`

### Admin/configuration resources
- `/venues`
- `/sections`
- `/rows`
- `/seats`
- `/seat-pairs`
- `/billing-statuses`
- `/menu-categories`
- `/menu-items`
- `/printers`
- `/printer-routes`
- `/users`
- `/roles`
- `/translations`
- `/accounting-exports`

## Authentication endpoints

### POST /api/v1/auth/login
Authenticate user and create session.

Request:
```json
{
  "username": "marta",
  "password": "secret",
  "language": "pt-PT"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "usr_123",
      "displayName": "Marta",
      "roles": ["SERVER"],
      "preferredLanguage": "pt-PT"
    },
    "session": {
      "token": "...",
      "expiresAt": "2026-04-28T23:59:59Z"
    }
  }
}
```

### POST /api/v1/auth/logout
Invalidate current session.

### GET /api/v1/auth/me
Return current authenticated user, roles, and effective permissions.

## Floor and occupancy endpoints

### GET /api/v1/sessions/current
Return the currently active service session and venue context.

Response includes:
- service session metadata
- venue identity
- active billing statuses
- effective language options

### GET /api/v1/floor
Return floor occupancy view for the active session.

Query params:
- `sectionId` optional
- `includeClosed=false` optional

Response shape:
```json
{
  "success": true,
  "data": {
    "sessionId": "sess_001",
    "sections": [
      {
        "sectionId": "sec_A",
        "sectionCode": "A",
        "rows": [
          {
            "rowId": "row_A1",
            "rowCode": "1",
            "seatPairs": [
              {"pairSequence": 1, "state": "FREE"},
              {"pairSequence": 2, "state": "OCCUPIED", "billingGroupId": "bg_1001", "status": "ACTIVE"}
            ]
          }
        ]
      }
    ]
  }
}
```

### POST /api/v1/billing-groups
Create a billing group and optionally assign initial occupied zones.

Request:
```json
{
  "statusCode": "WAITING",
  "coverCount": 6,
  "notes": "Walk-in",
  "zones": [
    {
      "rowId": "row_A1",
      "startSeatPairSequence": 2,
      "endSeatPairSequence": 4,
      "deliveryMode": "CENTER"
    }
  ]
}
```

Validation rules:
- selected ranges must not overlap open zones
- status must be active and valid
- zone ranges must be contiguous within one row

Response:
```json
{
  "success": true,
  "data": {
    "billingGroupId": "bg_1001",
    "displayCode": "BG-1001",
    "statusCode": "WAITING",
    "zones": [
      {
        "occupiedZoneId": "zone_001",
        "rowId": "row_A1",
        "startSeatPairSequence": 2,
        "endSeatPairSequence": 4,
        "defaultDeliveryReference": "CENTER(2-4)"
      }
    ]
  }
}
```

### GET /api/v1/billing-groups/{billingGroupId}
Return one billing group with zones, orders summary, balance summary, and recent documents.

### PATCH /api/v1/billing-groups/{billingGroupId}
Update mutable billing-group fields.

Allowed MVP updates:
- `statusCode`
- `coverCount`
- `notes`

Request may include optimistic locking field:
```json
{
  "versionNumber": 7,
  "statusCode": "ACTIVE",
  "notes": "Moved closer to aisle"
}
```

On stale version, return `VERSION_CONFLICT`.

### POST /api/v1/billing-groups/{billingGroupId}/zones
Add one or more occupied zones to an existing billing group.

Request:
```json
{
  "zones": [
    {
      "rowId": "row_A2",
      "startSeatPairSequence": 1,
      "endSeatPairSequence": 2,
      "deliveryMode": "CENTER"
    }
  ]
}
```

### PATCH /api/v1/occupied-zones/{occupiedZoneId}
Update zone delivery settings or mark zone released.

Allowed MVP updates:
- `deliveryMode`
- `deliverySeatPairId`
- `releasedAt`
- `isOpen`

Example:
```json
{
  "deliveryMode": "SPECIFIC_SEAT_PAIR",
  "deliverySeatPairId": "sp_15"
}
```

### GET /api/v1/occupied-zones/{occupiedZoneId}
Return one occupied zone with billing-group reference and delivery metadata.

## Ordering endpoints

### POST /api/v1/orders
Create and submit an order.

Request:
```json
{
  "billingGroupId": "bg_1001",
  "occupiedZoneId": "zone_001",
  "notes": "First round",
  "items": [
    {
      "menuItemId": "item_beer",
      "quantity": 4
    },
    {
      "menuItemId": "item_burger",
      "quantity": 2,
      "deliverySeatPairId": "sp_14"
    }
  ]
}
```

Behavior:
- if `occupiedZoneId` is omitted, order is group-level
- if line `deliverySeatPairId` is omitted, default zone center applies
- items are split into ticket outputs by fulfillment route
- audit event is written

Response includes:
- order header
- persisted order items
- resulting production tickets, if created immediately

### GET /api/v1/orders/{orderHeaderId}
Return order with items, routing, and production status.

### POST /api/v1/orders/{orderHeaderId}/void-items
Void or correct previously sent items and generate void output.

Request:
```json
{
  "items": [
    {
      "orderItemId": "oi_001",
      "reason": "Wrong dish"
    }
  ]
}
```

Response includes:
- affected items
- generated void/correction tickets

### GET /api/v1/billing-groups/{billingGroupId}/orders
List orders for one billing group in chronological order.

## Production ticket endpoints

### GET /api/v1/production-tickets/{ticketId}
Return one printed or pending production ticket.

### POST /api/v1/production-tickets/{ticketId}/reprint
Reprint a production ticket.

Request:
```json
{
  "reason": "Lost ticket"
}
```

Rules:
- reprint must create a new ticket record linked to the original
- printed output must be marked as reprint
- audit event must be recorded

### GET /api/v1/billing-groups/{billingGroupId}/production-tickets
List production tickets associated with a billing group.

## Billing and checkout endpoints

### GET /api/v1/billing-groups/{billingGroupId}/bill-summary
Return the current internal bill summary.

Response should include:
- billing group identity
- occupied zones summary
- line items
- subtotal
- total
- payments to date
- remaining balance

### POST /api/v1/billing-documents
Generate an internal bill document.

Request:
```json
{
  "billingGroupId": "bg_1001",
  "documentType": "INTERNAL_BILL",
  "print": true
}
```

Response:
```json
{
  "success": true,
  "data": {
    "billingDocumentId": "bill_001",
    "documentType": "INTERNAL_BILL",
    "documentStatus": "PRINTED",
    "totalAmount": "42.50"
  }
}
```

### POST /api/v1/billing-documents/{billingDocumentId}/reprint
Reprint existing bill without altering commercial state.

### POST /api/v1/payments
Register an internal payment record.

Request:
```json
{
  "billingGroupId": "bg_1001",
  "amount": "20.00",
  "paymentLabel": "Cashier partial payment",
  "notes": "Guest paid part of total"
}
```

Rules:
- no card-terminal integration implied
- payment creates operational record only
- group remains open if outstanding balance remains

### POST /api/v1/billing-groups/{billingGroupId}/reopen
Reopen a billing group after partial checkout when permitted.

Request:
```json
{
  "reason": "Additional orders requested"
}
```

Response includes updated status and version.

## Event log and audit endpoints

### GET /api/v1/event-log
List audit events.

Query params:
- `serviceSessionId`
- `billingGroupId`
- `eventType`
- `from`
- `to`
- `page`
- `pageSize`

Response item example:
```json
{
  "eventId": "evt_001",
  "eventType": "ORDER_CREATED",
  "eventTime": "2026-04-28T20:11:34Z",
  "actor": {"userId": "usr_123", "displayName": "Marta"},
  "billingGroupId": "bg_1001",
  "occupiedZoneId": "zone_001",
  "summary": "Created order with 3 items"
}
```

### GET /api/v1/event-log/{eventId}
Return detailed event record including payload if authorized.

## Admin configuration endpoints

### Venue structure
- `GET /api/v1/venues/{venueId}`
- `GET /api/v1/venues/{venueId}/layout`
- `POST /api/v1/sections`
- `POST /api/v1/rows`
- `POST /api/v1/seats`
- `POST /api/v1/seat-pairs`
- `PATCH /api/v1/sections/{sectionId}`
- `PATCH /api/v1/rows/{rowId}`
- `PATCH /api/v1/seats/{seatId}`
- `PATCH /api/v1/seat-pairs/{seatPairId}`

Recommended rule:
- destructive deletes should be avoided in MVP; prefer disable/deactivate semantics

### Billing statuses
- `GET /api/v1/billing-statuses`
- `POST /api/v1/billing-statuses`
- `PATCH /api/v1/billing-statuses/{billingStatusId}`

### Menu and routing
- `GET /api/v1/menu-categories`
- `POST /api/v1/menu-categories`
- `GET /api/v1/menu-items`
- `POST /api/v1/menu-items`
- `PATCH /api/v1/menu-items/{menuItemId}`

### Printers
- `GET /api/v1/printers`
- `POST /api/v1/printers`
- `PATCH /api/v1/printers/{printerId}`
- `GET /api/v1/printer-routes`
- `POST /api/v1/printer-routes`
- `PATCH /api/v1/printer-routes/{printerRouteId}`

### Users and roles
- `GET /api/v1/users`
- `POST /api/v1/users`
- `PATCH /api/v1/users/{userId}`
- `GET /api/v1/roles`
- `POST /api/v1/users/{userId}/roles`
- `PATCH /api/v1/users/{userId}/roles/{assignmentId}`

### Localization
- `GET /api/v1/translations?languageCode=pt-PT`
- `GET /api/v1/translations?languageCode=en-US`

### Accounting export
- `POST /api/v1/accounting-exports`
- `GET /api/v1/accounting-exports`
- `GET /api/v1/accounting-exports/{accountingExportId}`
- `GET /api/v1/accounting-exports/{accountingExportId}/download`

Create export request example:
```json
{
  "serviceSessionId": "sess_001",
  "exportType": "ACCOUNTING_SUMMARY",
  "fileFormat": "CSV"
}
```

## DTO guidance

### BillingGroup DTO
Recommended fields:
- `billingGroupId`
- `displayCode`
- `statusCode`
- `statusLabel`
- `coverCount`
- `isClosed`
- `zones[]`
- `runningTotals`
- `openedAt`
- `closedAt`
- `versionNumber`

### OccupiedZone DTO
Recommended fields:
- `occupiedZoneId`
- `rowId`
- `rowCode`
- `startSeatPairSequence`
- `endSeatPairSequence`
- `defaultDeliveryReference`
- `deliverySeatPairId`
- `isOpen`

### Order DTO
Recommended fields:
- `orderHeaderId`
- `billingGroupId`
- `occupiedZoneId`
- `orderedBy`
- `orderedAt`
- `submissionStatus`
- `items[]`

### ProductionTicket DTO
Recommended fields:
- `productionTicketId`
- `ticketType`
- `ticketStatus`
- `billingGroupId`
- `occupiedZoneId`
- `printerId`
- `printedAt`
- `isVoidSlip`
- `isReprint`

### BillingDocument DTO
Recommended fields:
- `billingDocumentId`
- `billingGroupId`
- `documentType`
- `documentStatus`
- `totalAmount`
- `printedAt`
- `isReprint`

## Permission matrix at API level

### Server
Allowed:
- view floor
- create billing groups
- update billing-group status within allowed range
- add occupied zones
- create orders
- void/correct eligible order items
- view billing-group detail
- reopen billing groups if permitted

Not allowed by default:
- admin configuration
- accounting export
- user management

### Cashier
Allowed:
- billing-group lookup
- view billing-group detail
- generate internal bills
- reprint bills
- record partial payment
- reopen billing groups if permitted
- inspect event history where allowed

### Admin
Allowed:
- all configuration endpoints
- full event log access
- export access
- printer and role management

### Kitchen/bar
- no interactive API use required in MVP
- ticket delivery is represented through print records, not direct API clients

## State-transition expectations

### Billing group
Suggested transitions:
- `WAITING -> ACTIVE`
- `ACTIVE -> CHECK_REQUESTED`
- `CHECK_REQUESTED -> PARTIALLY_PAID`
- `PARTIALLY_PAID -> ACTIVE` via reopen
- `PARTIALLY_PAID -> CLOSED`
- `ACTIVE -> CLOSED` if settled directly

Invalid transitions should return `INVALID_STATUS_TRANSITION`.

### Production tickets
- `PENDING -> PRINTED`
- `PENDING -> FAILED`
- `PRINTED -> REPRINTED` represented by child reprint record rather than in-place mutation

## Concurrency expectations

Because the service may involve a dozen or more servers on the same local network, the API should anticipate concurrent edits. The most sensitive resources are billing groups, occupied zones, and orders tied to active service. [cite:152]

Recommended MVP approach:
- include `versionNumber` on BillingGroup updates
- reject stale writes with `VERSION_CONFLICT`
- validate occupied-zone overlap at write time inside a transaction
- ensure ticket generation and audit-event creation happen in the same transactional unit as order submission where practical

## Non-goals for MVP API

These endpoint families should not exist in MVP unless scope changes:
- customer payment terminal APIs
- online ordering APIs
- reservation APIs
- QR ordering APIs
- inventory APIs
- analytics/reporting dashboards beyond accounting export and event log
- interactive kitchen-display APIs

## Suggested implementation order

1. Auth and current session
2. Floor read model
3. Billing-group create/read/update
4. Occupied-zone assignment and validation
5. Order submission and ticket generation
6. Bill generation and payment record endpoints
7. Reprint and reopen actions
8. Event log endpoints
9. Admin configuration endpoints
10. Accounting export endpoints

## Open decisions for architecture

- cookie session vs bearer token
- exact printer integration mechanism behind print endpoints
- sync vs async printing behavior
- whether document generation is command-style only or also directly queryable as rendered HTML/PDF
- pagination standard and filter syntax
- whether translations are bundled client-side or served dynamically
