# Data Model: Recurring Event Service and Billing App

## Purpose

This document defines the MVP data model for the recurring event service and billing app. It is aligned with the existing product scope, user stories, acceptance criteria, and screen flows: local-network web/PWA delivery, internal billing, server-led floor operation, printed kitchen and bar tickets, pt-pt/en-us UI, accounting export, and full event logging.

The model is designed around the app's core domain choices:

- Physical addressing is based on section, row, seat, and seat pair.
- The smallest assignable unit is a seat pair.
- Occupancy is tracked as one or more occupied zones.
- Billing is tracked by billing group, not by person.
- A billing group may span multiple occupied zones.
- Orders may be linked at billing-group level or zone level.
- Delivery defaults to the center of the occupied zone, with optional override to a specific seat pair.
- Kitchen and bar are non-interactive in MVP and receive printed tickets only.

## Modeling principles

- Separate physical layout from commercial state.
- Keep mutable service state separate from immutable event history.
- Model printed outputs as first-class records.
- Preserve traceability between billing groups, occupied zones, orders, prints, and user actions.
- Support multilingual UI without coupling translations to core business tables.

## Entity overview

| Entity | Purpose |
|---|---|
| Venue | Top-level recurring event location or installation |
| ServiceSession | A dated operating session, such as one lunch or dinner service |
| Section | Top-level physical service area |
| Row | Ordered service row inside a section |
| Seat | Addressable seat position inside a row |
| SeatPair | Smallest assignable physical unit, composed of two facing seats |
| BillingStatus | Configurable operational status for a billing group |
| BillingGroup | Primary commercial/service entity |
| OccupiedZone | One contiguous occupied range of seat pairs assigned to a billing group |
| MenuCategory | Category used for organization and routing |
| MenuItem | Sellable item |
| Printer | Configured output device |
| PrinterRoute | Rule mapping document/item class to printer destination |
| OrderHeader | One submitted order action for a billing group |
| OrderItem | Individual item line within an order |
| ProductionTicket | Printed kitchen or bar ticket |
| ProductionTicketItem | Mapping between ticket and order items |
| BillingDocument | Internal customer bill or related printable billing artifact |
| PaymentRecord | Internal record of full or partial payment state |
| AccountingExport | Export batch for external accounting software |
| AppUser | Interactive user account |
| AppRole | Role such as server, cashier, admin |
| UserRoleAssignment | User-to-role mapping |
| AuditEvent | Immutable operational event log |
| TranslationKey | Optional UI translation dictionary metadata |

## Core relational model

### Venue and session

A single deployment may support one primary recurring venue, but the model allows explicit session separation so that each meal service is isolated operationally and historically.

#### Venue
- VenueId (PK)
- VenueCode (unique)
- Name
- IsActive
- CreatedAt
- UpdatedAt

#### ServiceSession
- ServiceSessionId (PK)
- VenueId (FK -> Venue)
- SessionType
- SessionLabel
- StartsAt
- EndsAt nullable
- Status
- Notes nullable
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(VenueId, SessionLabel)

## Physical layout entities

### Section
- SectionId (PK)
- VenueId (FK -> Venue)
- SectionCode
- Name
- SortOrder
- IsActive
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(VenueId, SectionCode)

### Row
- RowId (PK)
- SectionId (FK -> Section)
- RowCode
- SortOrder
- IsActive
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(SectionId, RowCode)

### Seat
- SeatId (PK)
- RowId (FK -> Row)
- SeatNumber
- SortOrder
- IsActive
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(RowId, SeatNumber)

### SeatPair
- SeatPairId (PK)
- RowId (FK -> Row)
- PairSequence
- SeatAId (FK -> Seat)
- SeatBId (FK -> Seat)
- IsActive
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(RowId, PairSequence)
- Unique(RowId, SeatAId)
- Unique(RowId, SeatBId)

Business notes:
- SeatAId and SeatBId must belong to the same row.
- A seat should belong to at most one active seat pair in MVP.

## Billing and occupancy entities

### BillingStatus
- BillingStatusId (PK)
- Code
- DisplayName
- SortOrder
- IsActive
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(Code)

### BillingGroup
- BillingGroupId (PK)
- ServiceSessionId (FK -> ServiceSession)
- DisplayCode
- BillingStatusId (FK -> BillingStatus)
- CoverCount nullable
- Notes nullable
- OpenedByUserId (FK -> AppUser)
- OpenedAt
- ClosedAt nullable
- IsClosed
- VersionNumber
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(ServiceSessionId, DisplayCode)

Purpose:
- Primary owner of service activity, ordering, billing, partial payment, and print history.

### OccupiedZone
- OccupiedZoneId (PK)
- BillingGroupId (FK -> BillingGroup)
- RowId (FK -> Row)
- StartSeatPairSequence
- EndSeatPairSequence
- DefaultDeliveryMode
- DeliveryCenterLabel nullable
- DeliverySeatPairId nullable (FK -> SeatPair)
- OpenedAt
- ReleasedAt nullable
- IsOpen
- CreatedByUserId (FK -> AppUser)
- CreatedAt
- UpdatedAt

Recommended indexes:
- Index(BillingGroupId, IsOpen)
- Index(RowId, IsOpen, StartSeatPairSequence, EndSeatPairSequence)

Business notes:
- A zone must belong to exactly one billing group while open.
- StartSeatPairSequence must be less than or equal to EndSeatPairSequence.
- Open zones in the same row must not overlap.
- DeliverySeatPairId, when set, must fall inside the same row and range.

Optional future extension:
- If zones may ever span multiple rows, introduce OccupiedZoneSegment instead of storing one row range directly in OccupiedZone.

## Ordering entities

### MenuCategory
- MenuCategoryId (PK)
- Code
- DisplayName
- RouteType
- SortOrder
- IsActive
- CreatedAt
- UpdatedAt

RouteType examples:
- KITCHEN
- BAR
- NONE

### MenuItem
- MenuItemId (PK)
- MenuCategoryId (FK -> MenuCategory)
- Sku nullable
- Code nullable
- DisplayName
- ShortName nullable
- UnitPrice
- TaxCode nullable
- IsActive
- CreatedAt
- UpdatedAt

### OrderHeader
- OrderHeaderId (PK)
- BillingGroupId (FK -> BillingGroup)
- OccupiedZoneId nullable (FK -> OccupiedZone)
- OrderedByUserId (FK -> AppUser)
- OrderedAt
- SubmissionStatus
- Notes nullable
- CreatedAt
- UpdatedAt

Recommended indexes:
- Index(BillingGroupId, OrderedAt)
- Index(OccupiedZoneId, OrderedAt)

Business notes:
- OccupiedZoneId is nullable because some orders may be attached only to the billing group.
- If OccupiedZoneId is set, it must belong to the same BillingGroupId.

### OrderItem
- OrderItemId (PK)
- OrderHeaderId (FK -> OrderHeader)
- MenuItemId (FK -> MenuItem)
- Quantity
- UnitPrice
- LineSubtotal
- FulfillmentRoute
- DeliverySeatPairId nullable (FK -> SeatPair)
- DeliveryReferenceLabel nullable
- SentToProductionAt nullable
- VoidedAt nullable
- VoidedByUserId nullable (FK -> AppUser)
- VoidReason nullable
- ParentOrderItemId nullable (FK -> OrderItem)
- CreatedAt
- UpdatedAt

Recommended indexes:
- Index(OrderHeaderId)
- Index(FulfillmentRoute, SentToProductionAt)

Business notes:
- FulfillmentRoute should normally derive from the menu item category at creation time, then be stored denormalized on the line for historical safety.
- DeliverySeatPairId is nullable because default zone-center delivery is allowed.
- ParentOrderItemId can relate correction lines or replacement lines to original lines.

## Printing entities

### Printer
- PrinterId (PK)
- Name
- PrinterType
- ConnectionType
- Address nullable
- Port nullable
- IsActive
- CreatedAt
- UpdatedAt

PrinterType examples:
- KITCHEN
- BAR
- BILL
n- GENERIC

### PrinterRoute
- PrinterRouteId (PK)
- VenueId (FK -> Venue)
- DocumentType
- FulfillmentRoute nullable
- PrinterId (FK -> Printer)
- IsActive
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(VenueId, DocumentType, FulfillmentRoute)

DocumentType examples:
- PRODUCTION_TICKET
- BILL
- VOID_SLIP

### ProductionTicket
- ProductionTicketId (PK)
- ServiceSessionId (FK -> ServiceSession)
- BillingGroupId (FK -> BillingGroup)
- OccupiedZoneId nullable (FK -> OccupiedZone)
- PrinterId (FK -> Printer)
- TicketType
- TicketStatus
- DeliveryReferenceLabel nullable
- PrintedAt nullable
- RequestedAt
- ReprintOfTicketId nullable (FK -> ProductionTicket)
- IsVoidSlip
- IsReprint
- CreatedByUserId (FK -> AppUser)
- CreatedAt
- UpdatedAt

TicketType examples:
- KITCHEN
- BAR
- VOID

### ProductionTicketItem
- ProductionTicketItemId (PK)
- ProductionTicketId (FK -> ProductionTicket)
- OrderItemId (FK -> OrderItem)
- CreatedAt

Recommended uniqueness:
- Unique(ProductionTicketId, OrderItemId)

## Billing and checkout entities

### BillingDocument
- BillingDocumentId (PK)
- BillingGroupId (FK -> BillingGroup)
- PrinterId nullable (FK -> Printer)
- DocumentType
- DocumentStatus
- DocumentNumber nullable
- SubtotalAmount
- TotalAmount
- PrintedAt nullable
- RequestedAt
- ReprintOfBillingDocumentId nullable (FK -> BillingDocument)
- IsReprint
- CreatedByUserId (FK -> AppUser)
- CreatedAt
- UpdatedAt

DocumentType examples:
- INTERNAL_BILL
- BILL_REPRINT

### PaymentRecord
- PaymentRecordId (PK)
- BillingGroupId (FK -> BillingGroup)
- RecordedByUserId (FK -> AppUser)
- RecordedAt
- Amount
- PaymentLabel
- Notes nullable
- IsVoided
- VoidedAt nullable
- VoidedByUserId nullable (FK -> AppUser)
- CreatedAt
- UpdatedAt

Business notes:
- This is an internal operational record only.
- External payment terminal processing is out of scope.

## Export and audit entities

### AccountingExport
- AccountingExportId (PK)
- VenueId (FK -> Venue)
- ServiceSessionId nullable (FK -> ServiceSession)
- ExportType
- ExportRangeStart nullable
- ExportRangeEnd nullable
- FileName nullable
- FileFormat
- ExportStatus
- RequestedByUserId (FK -> AppUser)
- RequestedAt
- CompletedAt nullable
- CreatedAt
- UpdatedAt

### AuditEvent
- AuditEventId (PK)
- ServiceSessionId nullable (FK -> ServiceSession)
- EventType
- EventTime
- ActorUserId nullable (FK -> AppUser)
- BillingGroupId nullable (FK -> BillingGroup)
- OccupiedZoneId nullable (FK -> OccupiedZone)
- OrderHeaderId nullable (FK -> OrderHeader)
- OrderItemId nullable (FK -> OrderItem)
- ProductionTicketId nullable (FK -> ProductionTicket)
- BillingDocumentId nullable (FK -> BillingDocument)
- PaymentRecordId nullable (FK -> PaymentRecord)
- AccountingExportId nullable (FK -> AccountingExport)
- EntityType nullable
- EntityId nullable
- Summary
- PayloadJson nullable
- CreatedAt

Recommended indexes:
- Index(ServiceSessionId, EventTime)
- Index(BillingGroupId, EventTime)
- Index(EventType, EventTime)

Design note:
- An append-only event log is the simplest way to preserve traceability, and audit-focused database guidance commonly recommends dedicated audit logging rather than relying only on current-state tables.

## User and authorization entities

### AppUser
- AppUserId (PK)
- Username
- DisplayName
- PreferredLanguageCode
- IsActive
- LastLoginAt nullable
- PasswordHash or external auth reference
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(Username)

### AppRole
- AppRoleId (PK)
- Code
- DisplayName
- IsInteractive
- IsActive
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(Code)

Expected MVP roles:
- SERVER
- CASHIER
- ADMIN
- KITCHEN_OUTPUT
- BAR_OUTPUT

Note:
- KITCHEN_OUTPUT and BAR_OUTPUT may exist for configuration and audit semantics even though they are non-login roles in MVP.

### UserRoleAssignment
- UserRoleAssignmentId (PK)
- AppUserId (FK -> AppUser)
- AppRoleId (FK -> AppRole)
- AssignedAt
- AssignedByUserId nullable (FK -> AppUser)
- IsActive

Recommended uniqueness:
- Unique(AppUserId, AppRoleId)

## Localization entities

### TranslationKey
- TranslationKeyId (PK)
- LanguageCode
- TranslationNamespace
- TranslationKey
- TranslationValue
- IsActive
- CreatedAt
- UpdatedAt

Recommended uniqueness:
- Unique(LanguageCode, TranslationNamespace, TranslationKey)

Expected MVP language codes:
- pt-PT
- en-US

## Suggested enums

These may be implemented as database enums, lookup tables, or constrained strings depending on the final stack.

### BillingGroup.IsClosed with BillingStatus.Code examples
- WAITING
- ACTIVE
- CHECK_REQUESTED
- PARTIALLY_PAID
- CLOSED

### OrderHeader.SubmissionStatus
- DRAFT
- SUBMITTED
- PARTIALLY_VOIDED
- VOIDED

### ProductionTicket.TicketStatus
- PENDING
- PRINTED
- FAILED
- CANCELED

### BillingDocument.DocumentStatus
- GENERATED
- PRINTED
- VOIDED

### AccountingExport.ExportStatus
- REQUESTED
- COMPLETED
- FAILED

## Relationship summary

- One Venue has many Sections and many ServiceSessions.
- One Section has many Rows.
- One Row has many Seats and many SeatPairs.
- One ServiceSession has many BillingGroups.
- One BillingGroup has many OccupiedZones, OrderHeaders, BillingDocuments, PaymentRecords, and ProductionTickets.
- One OccupiedZone belongs to one BillingGroup and may be referenced by many orders and tickets.
- One OrderHeader has many OrderItems.
- One ProductionTicket may contain many OrderItems through ProductionTicketItem.
- One AppUser may hold many roles through UserRoleAssignment.
- Many business actions emit AuditEvents.

## Derived views and computed fields

These do not need to be stored if they can be computed safely, but they are important to application behavior.

- Current balance per billing group.
- Current open occupied zones per billing group.
- Floor occupancy state by row and seat-pair range.
- Default center delivery label for each occupied zone.
- Effective printer route for each order item or document.
- Current localized label set per language.

## Integrity rules

- No two open occupied zones may overlap within the same row.
- An order linked to an occupied zone must reference a zone owned by the same billing group.
- A delivery seat pair override must fall within the relevant occupied zone.
- Food and drinks routing must resolve to a configured printer route or fail visibly.
- Reprints must preserve linkage to the original ticket or bill.
- Audit events should be append-only.
- Closed sessions should be logically immutable except for admin-level corrective workflows if those are added later.

## Normalization and implementation notes

- Store current operational state in primary tables such as BillingGroup, OccupiedZone, OrderHeader, OrderItem, and BillingDocument.
- Store immutable history in AuditEvent and in explicit print/billing records instead of trying to infer history from mutable tables alone.
- Denormalize route and pricing values onto order lines so historical accuracy is preserved even if catalog data changes later.
- Consider optimistic locking on BillingGroup using VersionNumber to reduce conflicting edits from many simultaneous servers.

## Recommended MVP database shape

For MVP, a relational database is the best fit because the domain has clear entities, constraints, and audit relationships. A normalized schema with append-only audit logging is especially suitable when precise event history and operational traceability are required.

If architecture later chooses SQLite for simplicity or PostgreSQL for concurrency, the logical model should remain the same even if some implementation details differ.

## Seed data requirements

A realistic seed dataset should include:

- One venue.
- One sample service session.
- Multiple sections and rows.
- Seats and seat pairs for each row.
- Billing statuses.
- A minimal menu with both kitchen and bar categories.
- Printer records and printer routes.
- Sample users for server, cashier, and admin.
- One sample open billing group spanning more than one occupied zone.
- One sample bill, partial payment, and reprint trail.

## Open decisions for later documents

The following choices should be refined later in architecture or business-rules documents:

- Exact session lifecycle rules.
- Whether a billing group can ever transfer zones to another group directly.
- Whether menu, taxes, and pricing need more complex structures.
- Whether all audit events are application-level only or partly database-enforced.
- Exact accounting export formats and field mappings.
- Whether non-login kitchen/bar output roles should be stored as full roles or only as printer-route semantics.
