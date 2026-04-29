# Event Seating, Service, and Billing Specification

## Purpose

This specification defines a floor, service, and billing model for a food event or restaurant operation that uses long continuous seating, group-based ordering, and dynamic occupancy, while avoiding dependence on fixed table or temporary segment identifiers.

The goal is to support a real-world operating model in which staff work from stable physical references, groups may occupy one or more seat ranges, service can target different points within the same group footprint, and software can track billing and service state accordingly.

## Scope

This specification covers:

- Physical location addressing.
- Occupancy assignment.
- Group billing and service tracking.
- Delivery-point logic.
- Operational workflows.
- Software requirements derived from the operating model.
- Core data entities and rules.

This specification does not define UI design, accounting compliance details, tax law, or implementation in any specific POS product.

## Definitions

### Physical terms

- **Section**: A top-level physical area of the venue.
- **Row**: A service row within a section.
- **Seat**: A uniquely addressable seat position within a row.
- **Seat pair**: Two facing seats. This is the smallest assignable physical unit.
- **Seat range**: One or more contiguous seat pairs assigned together as an occupied zone.
- **Occupied zone**: A currently assigned seat range belonging to a billing group.

### Service and billing terms

- **Billing group**: The primary commercial entity for ordering, tab management, billing, and payment.
- **Delivery point**: The operational point used for order taking or item delivery within an occupied zone.
- **Default delivery point**: The center position of an occupied range.
- **Specific delivery point**: A specific seat pair used instead of the center when more precision is needed.
- **Service state**: The current operational stage of a billing group, such as reserved, seated, drinks, mains, dessert, bill requested, paid, or closed.

## Physical model

### Layout identity

The physical addressing model shall be based on:

1. Section.
2. Row.
3. Seat.

The physical model shall not require fixed table identifiers as the primary addressing system.

The physical model shall not depend on temporary carved-out segments as persistent identities.

### Smallest assignable unit

The smallest assignable unit shall be a seat pair consisting of two facing seats.

No assignment shall be made to an individual seat unless a later implementation explicitly introduces a special-case operational override.

### Range-based occupancy

Occupancy shall be represented as one or more seat ranges.

Each seat range shall be composed of contiguous seat pairs within the same section and row.

A seat range shall remain a logical occupancy construct only. It shall not rename or redefine the underlying physical layout.

### Physical stability

Physical identifiers shall remain constant throughout service.

When a group leaves, the affected seat ranges shall become free without any renaming of the section, row, or seat structure.

Freed space shall be reusable immediately, including splitting the previously occupied area into multiple new occupied ranges for different groups.

## Occupancy model

### Occupied zones

A billing group may occupy one or more occupied zones.

Each occupied zone shall be defined by:

- Section.
- Row.
- Start seat pair.
- End seat pair.

An occupied zone may be adjacent to another zone of the same billing group or physically separate from it.

### Zone discrimination

Although billing is group-based, the system shall preserve discrimination by occupied zone.

This means the system shall be able to distinguish which physical range an order, delivery action, or service event relates to, even when multiple occupied zones belong to the same billing group.

### Availability tracking

The system shall be able to determine, at minimum:

- Which seat pairs are free.
- Which seat pairs are occupied.
- Which occupied zone a seat pair belongs to.
- Which billing group controls that occupied zone.

## Billing model

### Group-based billing

Billing shall be tracked by group, not by person.

A billing group shall be the primary owner of:

- Orders.
- Open tabs.
- Bills.
- Payments.
- Service state.
- Historical transaction records.

### Relationship to occupied zones

Each billing group shall be associated with all occupied zones currently assigned to it.

A billing group may therefore span multiple seat ranges within the venue.

The billing model shall support discrimination per occupied zone inside the same billing group.

### Group identity

Each billing group shall have a distinct billing identifier separate from physical location identifiers.

Physical addressing and commercial identity shall therefore remain independent.

## Delivery and service model

### Default delivery logic

For each occupied zone, the default delivery point shall be the central position of the occupied range.

This default shall be used for normal order taking and item delivery unless a more specific target is required.

### Specific delivery logic

When greater operational precision is needed, the system shall allow a specific seat pair within the occupied zone to be used as the delivery point.

This specific seat pair may be used for:

- Order taking.
- Course delivery.
- Beverage delivery.
- Follow-up service actions.

### Convention handling

In-person directional conventions such as N/S or E/W may be used by staff as an informal operating shorthand.

These directional conventions are not mandatory data requirements of the model.

### Service targeting

The system shall support associating a service action with:

- A billing group only.
- A billing group plus occupied zone.
- A billing group plus occupied zone plus specific seat pair.

## Operational workflows

### Seating workflow

1. Staff identify one or more free seat ranges.
2. A new or existing billing group is assigned to those ranges.
3. The occupied ranges are recorded against that billing group.
4. Default delivery points are established from the center of each occupied range.
5. Specific seat-pair delivery points may be recorded if needed.

### Order workflow

1. A server creates an order under a billing group.
2. The order may optionally reference the relevant occupied zone.
3. The order may optionally reference a specific seat pair when required for clarity.
4. Kitchen or service tickets are generated from that order.
5. Delivery is performed using the occupied-zone center by default, or a specific seat pair when explicitly recorded.

### Reassignment workflow

1. When a billing group closes or vacates a zone, its occupied ranges are released.
2. The underlying physical addresses remain unchanged.
3. Any subset of the newly free space may be assigned to one or more different billing groups.
4. Newly assigned occupied zones are recorded without renaming physical layout elements.

### Billing workflow

1. A billing group remains the primary tab entity throughout service.
2. All occupied zones tied to that billing group remain visible for operational discrimination.
3. Charges accumulate at the billing-group level.
4. Bills and invoices are produced from the billing group, with zone-level traceability available operationally.
5. Historical records retain both the billing-group identity and the occupied-zone associations relevant to each service event.

## Functional software requirements

### Core operation

The software shall support operating a food event or restaurant using the physical, occupancy, delivery, and billing model defined in this specification.

### Ticket creation

The software shall allow servers to create tickets from Android devices or from a web interface.

### Kitchen printing

The software shall support printing paper tickets in the kitchen.

### History

The software shall keep historical records of orders, tickets, tabs, bills, and service activity.

### Billing output

The software shall support printing bills and invoices.

### Cost and implementation constraints

The preferred solution shall be completely free for the intended use case.

DIY adaptation, self-hosting, or customization is acceptable where necessary to satisfy the operating model.

### Commercial logic

The software shall support group-based tabs rather than requiring person-based billing.

The software shall support one billing group being associated with multiple occupied zones.

The software shall support service actions and ticket references being associated with a billing group and, where required, a specific occupied zone or seat pair.

The software should avoid forcing the venue model to be based primarily on fixed restaurant tables if the real operation is organized by section, row, and seat ranges.

## Data model requirements

### Required entities

The implementation shall support at least the following logical entities:

| Entity | Purpose |
|---|---|
| Section | Top-level physical venue area |
| Row | Ordered service row within a section |
| Seat | Addressable seat position within a row |
| SeatPair | Smallest assignable facing-seat unit |
| OccupiedZone | Assigned contiguous range of seat pairs |
| BillingGroup | Primary billing and service owner |
| DeliveryPoint | Default center point or specific seat-pair target |
| Order | Commercial/service transaction unit |
| Ticket | Printed or routed production/service instruction |
| Bill/Invoice | Financial output linked to a billing group |
| ServiceEvent | Historical record of operational activity |

### Minimum fields

#### Section

- SectionId
- SectionCode
- SectionName
- DisplayOrder

#### Row

- RowId
- SectionId
- RowCode or RowNumber
- DisplayOrder

#### Seat

- SeatId
- RowId
- SeatNumber
- DisplayOrder

#### SeatPair

- SeatPairId
- RowId
- PairSequence
- SeatAId
- SeatBId

#### OccupiedZone

- OccupiedZoneId
- BillingGroupId
- SectionId
- RowId
- StartSeatPairSequence
- EndSeatPairSequence
- DefaultDeliveryMode
- SpecificDeliverySeatPairId, nullable
- Status
- OpenedAt
- ClosedAt, nullable

#### BillingGroup

- BillingGroupId
- ExternalReference or DisplayCode
- GroupName or Label, nullable
- Covers
- ServiceState
- OpenedAt
- ClosedAt, nullable
- Notes, nullable

#### Order

- OrderId
- BillingGroupId
- OccupiedZoneId, nullable
- DeliverySeatPairId, nullable
- CreatedBy
- CreatedAt
- Status

#### Ticket

- TicketId
- OrderId
- BillingGroupId
- OccupiedZoneId, nullable
- DeliverySeatPairId, nullable
- PrintedAt, nullable
- Destination
- Status

#### Bill/Invoice

- BillingDocumentId
- BillingGroupId
- DocumentType
- CreatedAt
- PrintedAt, nullable
- TotalAmount
- Status

#### ServiceEvent

- ServiceEventId
- BillingGroupId
- OccupiedZoneId, nullable
- DeliverySeatPairId, nullable
- EventType
- EventTimestamp
- Actor
- Notes, nullable

## Business rules

### Mandatory rules

- A seat pair may belong to at most one open occupied zone at a time.
- An occupied zone shall belong to exactly one billing group while open.
- A billing group may own multiple open occupied zones at the same time.
- Orders shall always belong to a billing group.
- Orders may optionally reference an occupied zone.
- Orders may optionally reference a specific delivery seat pair.
- If a specific delivery seat pair is present, it shall belong to the referenced occupied zone.
- If no specific delivery seat pair is present, the system shall use the occupied-zone center as the operational delivery reference.
- Bills and invoices shall be issued from the billing group.
- Historical records shall remain available after closure.

### Integrity rules

- Start seat pair shall be less than or equal to end seat pair within an occupied zone.
- The start and end seat pairs of an occupied zone shall belong to the same section and row.
- Occupied zones shall not overlap on open assignments.
- Physical identifiers shall never be modified as part of routine occupancy changes.

## Non-functional requirements

- The model shall remain understandable to hosts, servers, runners, kitchen staff, and managers.
- The system should minimize dependence on person-level tracking.
- The system should remain usable in a fast-moving event environment.
- The system should support later adaptation to POS software, custom software, or hybrid paper-plus-digital workflows.

## Acceptance criteria

A solution satisfies this specification if it can do all of the following:

- Address any service location using section, row, and seat structures.
- Assign and release occupancy using seat-pair ranges.
- Allow one billing group to control multiple occupied zones.
- Keep billing group identity separate from physical identity.
- Support default delivery to the center of an occupied range.
- Support specific delivery to a seat pair when needed.
- Track orders, tickets, bills, and history at the billing-group level while preserving occupied-zone discrimination.
- Reassign freed space dynamically without renaming the physical layout.
- Allow servers to create tickets from Android or web-based interfaces.
- Print kitchen paper tickets.
- Print bills or invoices.
- Preserve historical records.
- Be implementable in a free or DIY-friendly software stack.
