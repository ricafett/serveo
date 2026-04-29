# Implementation Audit Report: Docs vs. Code

**Date:** 2026-04-29
**Scope:** Full documentation pack (12 .md files) vs. current codebase
**Method:** Line-by-line comparison of requirements, architecture, data model, API contract, business rules, role matrix, screen flows, acceptance criteria, printing spec, DOD, and seed data against actual PHP/Laravel implementation.

---

## Executive Summary

The codebase is a **partially implemented MVP** with a **strong backend domain layer** but **significant gaps in frontend architecture, API surface, test coverage, and operational UI fidelity**. The data model, migrations, domain services, printing subsystem, and audit trail are well-aligned with the documentation. However, the application diverges critically from the architecture in three areas:

1. **No REST API exists** despite `api-contract.md` defining a full `/api/v1` surface.
2. **No Livewire components exist** despite `architecture.md` mandating Livewire for operational UI.
3. **Operational screens are built as Filament Pages**, blurring the admin/operational boundary that the docs explicitly require.

| Category | Status | Grade |
|---|---|---|
| Data Model & Migrations | Mostly aligned | B+ |
| Domain Services (Floor, Orders, Billing, Printing) | Well implemented | A- |
| Printing Subsystem | Fully aligned | A |
| Audit Trail | Fully aligned | A |
| Role/Permission Model | Partially aligned | B |
| REST API | Missing entirely | F |
| Livewire Operational UI | Missing entirely | F |
| Filament Admin Resources | Complete | A- |
| Test Coverage | Insufficient | D |
| Seed Data | Complete and rich | A |
| Mobile/PWA Optimisation | Not demonstrated | D |

---

## 1. Architecture.md

### 1.1 What is CORRECT

- **Modular monolith:** One Laravel app, one DB, one queue. ✅
- **Domain layer exists** under `app/Domain/` with subfolders:
  - `Audit/`, `Billing/`, `Floor/`, `Orders/`, `Printing/` ✅
- **Models are flat** under `app/Models/` — no per-domain subfolders. ✅
- **Filament resources** exist for all listed admin/config surfaces:
  - `AuditEventResource`, `BillingStatusResource`, `MenuCategoryResource`, `MenuItemResource`, `PrintJobResource`, `PrinterResource`, `PrinterRouteResource`, `RowResource`, `SectionResource`, `ServiceSessionResource`, `UserResource` ✅
- **Printing subsystem structure** matches exactly:
  - `Contracts/PrinterAdapter.php` ✅
  - `Adapters/LanEscPosAdapter.php`, `UsbAgentAdapter.php`, `NullAdapter.php` ✅
  - `PrinterAdapterRegistry.php`, `PrintQueueService.php`, `TicketRenderer.php`, `PrintResult.php` ✅
- **Single queue job** `DispatchPrintJob.php` handles all print types. ✅
- **PostgreSQL** is configured (evident from `timestampTz` usage). ✅
- **Redis queue** is referenced (`->onQueue('prints')`). ✅
- **Docker Compose** files exist (`.github/workflows/docker.yml` plus standard Laravel structure). ✅

### 1.2 What is INCORRECT or MISSING

- **Livewire operational UI is completely absent.** `architecture.md` states:
  > "Livewire: floor operations, billing-group detail, order entry, cashier workflows, printer status, basic event views."
  
  Instead, all operational screens (`Floor`, `BillingGroupDetail`, `OrderEntry`, `CashierCheckout`) are implemented as **Filament Pages**. This is a fundamental architectural divergence.

- **Filament is used for core service-floor UX**, which the docs explicitly say to avoid:
  > "Filament is not used for core service-floor operational UX (floor view, order entry, cashier workflows). Those are Livewire-driven."

- **Missing domain subfolders** for future extensibility:
  - `Auth`, `Users`, `RolesPermissions`, `Menu`, `ServiceSessions`, `VenueLayout`, `Payments`, `AccountingExport`, `EventLog`, `Localization` are all handled through Filament resources and flat Models, which is noted as acceptable but not ideal for growth.

- **No queue health summary** is exposed in the admin panel.

- **No `GenerateAccountingExportJob`** or `RetryFailedPrintJob` exists yet (noted as "future jobs" in architecture).

---

## 2. Product-Scope.md

### 2.1 What is CORRECT

- **In-scope capabilities implemented:**
  - End-to-end service workflow (seating → order → print → bill → payment → reopen) ✅
  - Group-based seating with section-row-seat-range model ✅
  - Zone-level discrimination within billing groups ✅
  - Kitchen and bar paper ticket printing ✅
  - Customer bill printing ✅
  - Reprints ✅
  - Ticket void slips ✅
  - Internal billing and checkout ✅
  - Full event log ✅
  - Multilingual UI infrastructure (`TranslationKey` model + seed data) ✅

- **Out-of-scope capabilities correctly excluded:**
  - No integrated payments, stock, reservations, QR ordering, online ordering, VAT invoicing, offline mode, analytics, or printer auto-discovery. ✅

### 2.2 What is INCORRECT or MISSING

- **Accounting export** is modeled (`AccountingExport` migration + model) but **no actual export generation logic, job, or Filament page** exists. The UI lists the resource/page as missing.
- **Multilingual UI** has the database layer (`TranslationKey`) but no evidence of runtime translation loading or language switching in the operational UI. The docs say "multilingual user interface" is in scope.

---

## 3. Acceptance-Criteria.md

### 3.1 What is CORRECT

| AC | Description | Status |
|---|---|---|
| AC-001 | Open billing group with seat ranges + overlap blocking | ✅ `BillingGroupService::open` + `OccupancyService::assignZone` with `lockForUpdate` overlap check |
| AC-002 | View free/occupied seat ranges | ✅ `Floor` Filament page shows occupancy state |
| AC-003 | Assign billing-group status | ✅ `BillingGroupService::setStatus` + audit log |
| AC-004 | Add orders to group or zone | ✅ `OrderService::submit` supports `$zone` parameter |
| AC-005 | Link order to occupied zone | ✅ `OrderHeader.occupied_zone_id` nullable FK |
| AC-006 | Default delivery target is zone center | ✅ `OccupiedZone::defaultDeliveryLabel()` computes center |
| AC-007 | Override delivery with specific seat pair | ✅ `OrderService::submit` accepts `delivery_seat_pair_id` and validates it |
| AC-008 | Route food/drinks automatically | ✅ `OrderService` groups by `fulfillment_route` (KITCHEN/BAR) |
| AC-009 | Void item with printed void slip | ✅ `OrderService::voidItem` creates `ProductionTicket` with `is_void_slip=true` |
| AC-010 | Print kitchen tickets | ✅ `ProductionTicket` + `PrintQueueService` + `DispatchPrintJob` |
| AC-011 | Print bar tickets only for bar orders | ✅ Mixed orders split; food-only produces no bar ticket |
| AC-012 | Tickets show billing group and zone | ✅ `TicketRenderer::renderProductionTicket` includes both |
| AC-013 | Tickets show default delivery or specific seat pair | ✅ `delivery_reference_label` printed on ticket |
| AC-014 | Void slips and reprints clearly marked | ✅ `is_void_slip` and `is_reprint` flags; renderer shows `*** ANULAÇÃO ***` and `** REIMPRESSÃO **` |
| AC-015 | Find open billing groups quickly | ✅ `CashierCheckout` page lists open groups |
| AC-016 | View occupied zones on billing group | ✅ `BillingGroupDetail` shows zones |
| AC-017 | Print internal bill without closing | ✅ `BillingService::generateInternalBill` does not close group |
| AC-018 | Register partial payment | ✅ `BillingService::recordPayment` supports partial |
| AC-019 | Reopen after partial checkout | ✅ `BillingGroupService::reopen` restores open state |
| AC-020 | Reprint bill without altering history | ✅ `BillingService::reprintBill` creates new `BillingDocument` with `is_reprint=true` |
| AC-021 | Log all billing-group actions | ✅ Audit events for bill, reprint, payment, void, reopen |
| AC-022 | Server can reopen billing group | ✅ UI action visible; service supports it |
| AC-023 | Define venue structure | ✅ Filament resources for Section, Row, Seat, SeatPair |
| AC-024 | Configure printer routing | ✅ `PrinterRouteResource` |
| AC-025 | Manage user roles | ✅ `UserResource` + Spatie Permission |
| AC-026 | Configure billing-group statuses | ✅ `BillingStatusResource` |
| AC-027 | Export accounting data | ❌ **Missing** — model exists but no export logic |
| AC-028 | Support pt-pt and en-us | ⚠️ **Partial** — seed data has translations, but no runtime switcher or full coverage |
| AC-029 | Full event log | ✅ `AuditEventResource` with filtering |
| AC-030 | Role-based access | ⚠️ **Partial** — Spatie roles exist, but no API-layer enforcement and UI-level conditional visibility is coarse |
| AC-031 | Print actions traceable | ✅ Every print creates `AuditEvent` |
| AC-032 | No silent state loss | ✅ Exceptions thrown and caught as Filament notifications |

### 3.2 What is INCORRECT or MISSING

- **AC-027 (Accounting export)** is not implemented.
- **AC-028 (Language support)** lacks UI integration.
- **AC-030 (Role-based access)** is enforced at the Filament panel login level (`canAccessPanel`) but not granularly at the action/button level across all operational pages. For example, the `BillingGroupDetail` page shows the "Imprimir conta" button to any logged-in user, not just cashiers/admins.

---

## 4. Business-Rules.md

### 4.1 What is CORRECT

| Rule | Status | Evidence |
|---|---|---|
| BR-001 to BR-010 (Physical layout) | ✅ | Migrations enforce section/row/seat/pair structure; `OccupancyService` enforces overlap |
| BR-011 to BR-022 (Billing-group lifecycle) | ✅ | `BillingGroupService` handles open, close, reopen, status |
| BR-023 to BR-034 (Orders) | ✅ | `OrderService` validates zone linkage, delivery override, pricing snapshot, atomicity via transactions |
| BR-035 to BR-044 (Production tickets) | ✅ | Routing by fulfillment route, void slips, reprint marking |
| BR-045 to BR-055 (Billing/payment) | ✅ | Internal bills, partial payment, reopen preserves history |
| BR-056 to BR-061 (Roles) | ✅ | Spatie roles for SERVER, CASHIER, ADMIN, KITCHEN_OUTPUT, BAR_OUTPUT |
| BR-062 to BR-067 (Audit) | ✅ | `Audit::record()` used throughout; append-only table |
| BR-068 to BR-074 (Error handling) | ✅ | Overlap exceptions, closed-group rejection, invalid delivery rejection |
| BR-075 to BR-080 (Session/config) | ✅ | Session scoping, soft-disable (`is_active`), manual printer config |

### 4.2 What is INCORRECT or MISSING

- **BR-017 (Suggested MVP status vocabulary):** WAITING, ACTIVE, CHECK_REQUESTED, PARTIALLY_PAID, CLOSED are all seeded. ✅
- **BR-070 (Version conflict handling):** The `version_number` column exists on `billing_groups`, but **no optimistic locking is actually implemented** in `BillingGroupService::setStatus` or any update path. The API contract specifies `VERSION_CONFLICT` responses, but no code checks this field.
- **BR-072 (Invalid status transition rejection):** `BillingGroupService::setStatus` does **not validate transitions**. Any status can be set to any other status. The recommended transitions (WAITING→ACTIVE, ACTIVE→CHECK_REQUESTED, etc.) are not enforced.
- **BR-077 (Soft-disable over destructive delete):** Filament resources do not appear to implement soft-delete or disable-only restrictions. Some resources may allow physical deletion.

---

## 5. Role-Permissions.md

### 5.1 What is CORRECT

- **Five MVP roles exist:** SERVER, CASHIER, ADMIN, KITCHEN_OUTPUT, BAR_OUTPUT. ✅
- **Non-interactive roles** (KITCHEN_OUTPUT, BAR_OUTPUT) cannot log in. ✅ (`canAccessPanel` rejects them.)
- **Spatie Permission** is used with a permission catalog that maps closely to the suggested permission codes. ✅
- **Role restrictions** are broadly correct in the seeder:
  - SERVER has floor, order, zone permissions. ✅
  - CASHIER has billing, payment, reprint permissions. ✅
  - ADMIN has everything. ✅

### 5.2 What is INCORRECT or MISSING

- **No policies or gates** are registered in `AppServiceProvider`. The entire authorization layer relies on Spatie's `hasRole`/`hasPermissionTo` checks, but these are **not enforced in the domain services** or at the API level (because there is no API).
- **UI-level permission enforcement is weak.** Filament Pages use simple `visible()` closures (e.g., `visible(fn () => ! $this->group?->is_closed)`) but do not check Spatie permissions. A server can technically click "Imprimir conta" on the `BillingGroupDetail` page because there is no `->can('bill.print')` guard.
- **Permission matrix at API level** is irrelevant because the API does not exist.
- **Cashier-only bill printing** is not enforced. The `BillingGroupDetail` page shows the bill-print button to any user.
- **Server search access (RP-001)** is not implemented because there is no search endpoint.
- **Cashier status edits (RP-002)** are not enforced — cashiers can change status if the UI exposes it.
- **Conditional reopen permissions (RP-005)** are not enforced by business rules. `BillingGroupService::reopen` only checks `is_closed`, not user role or payment state.

---

## 6. Screen-Flows.md

### 6.1 What is CORRECT

- **Screen inventory** is mostly represented:
  - Login ✅ (Filament default login)
  - Floor ✅ (`Floor` page)
  - Create/Edit Billing Group ✅ (`BillingGroupDetail` with zone assignment modal)
  - Billing Group Detail ✅ (`BillingGroupDetail` page)
  - Order Entry ✅ (`OrderEntry` page)
  - Billing Group Lookup ✅ (`CashierCheckout` page lists groups)
  - Checkout ✅ (`CashierCheckout` page)
  - Reprint / document actions ✅ (partially via `CashierCheckout` actions)
  - Venue Setup ✅ (`SectionResource`, `RowResource`)
  - Printer Setup ✅ (`PrinterResource`, `PrinterRouteResource`)
  - User and Role Management ✅ (`UserResource`)
  - Billing Status Configuration ✅ (`BillingStatusResource`)
  - Event Log ✅ (`AuditEventResource`)
  - Settings / language switch ❌ Missing

### 6.2 What is INCORRECT or MISSING

- **All operational screens are Filament Pages**, not Livewire components. The docs say:
  > "Livewire: floor operations, billing-group detail, order entry, cashier workflows..."
  > "Filament is not used for core service-floor operational UX."

  This is the **single largest architectural deviation**. It means:
  - The app is not optimized for mobile server workflows (Filament admin UI is desktop/tablet-first).
  - Navigation is through Filament's admin sidebar, not a task-first PWA interface.
  - Screen flows A-G are technically possible but the **interaction model is wrong**.

- **Missing screens:**
  - **Settings / language switch area** — no page or component exists.
  - **Accounting Export screen** — no `AccountingExportResource` or page exists.
  - **Reprint / document actions panel** is fragmented across `CashierCheckout` and `BillingGroupDetail`; there is no centralized reprint panel.

- **Default landing screens** are not implemented. All users land on the Filament Dashboard, not role-specific screens.
- **Mobile versus larger-screen behavior** is not addressed. Filament's responsive grid helps, but there is no phone-optimized bottom-sheet order entry or task-first navigation.

---

## 7. Data-Model.md

### 7.1 What is CORRECT

- **All 25 entities** from the entity overview are modeled as Eloquent models. ✅
- **Migrations match the spec almost exactly:**
  - `venues`, `service_sessions`, `sections`, `rows`, `seats`, `seat_pairs` ✅
  - `billing_statuses`, `billing_groups`, `occupied_zones` ✅
  - `menu_categories`, `menu_items`, `order_headers`, `order_items` ✅
  - `printers`, `printer_routes`, `cashier_printer_assignments`, `production_tickets`, `production_ticket_items`, `print_jobs` ✅
  - `billing_documents`, `payment_records`, `accounting_exports`, `audit_events`, `translation_keys` ✅
- **Foreign keys and indexes** align with recommendations:
  - `occupied_zones` has the recommended indexes. ✅
  - `order_headers` has the recommended indexes. ✅
  - `audit_events` has the recommended indexes. ✅
- **Field types** are correct: `timestampTz` for timestamps, `decimal(12,2)` for money, `jsonb` for `payload_json`. ✅
- **Uniqueness constraints** match:
  - `Unique(VenueId, SessionLabel)` ✅
  - `Unique(VenueId, SectionCode)` ✅
  - `Unique(SectionId, RowCode)` ✅
  - `Unique(RowId, SeatNumber)` ✅
  - `Unique(RowId, PairSequence)` ✅
  - `Unique(RowId, SeatAId)` ✅
  - `Unique(RowId, SeatBId)` ✅
  - `Unique(Code)` on `billing_statuses` ✅
  - `Unique(ServiceSessionId, DisplayCode)` on `billing_groups` ✅
  - `Unique(VenueId, DocumentType, FulfillmentRoute)` on `printer_routes` ✅
  - `Unique(LanguageCode, TranslationNamespace, TranslationKey)` on `translation_keys` ✅

### 7.2 What is INCORRECT or MISSING

- **`AppUser` / `AppRole` / `UserRoleAssignment` names:** The implementation uses Laravel's default `users` table and Spatie's `roles`/`permissions`/`model_has_roles` tables. This is a pragmatic divergence and is noted in the model docblock ("Mapped 1:1 with the AppUser entity..."). It is functionally equivalent but not a literal table-name match.
- **`order_items.fulfillment_route`** is stored as a string (KITCHEN/BAR/NONE) which is correct, but there is no database-level `CHECK` constraint or enum enforcement.
- **`billing_groups.version_number`** exists but is never incremented or checked.
- **Missing `ProductionTicketItem` model?** The migration creates `production_ticket_items`, but there is no dedicated `ProductionTicketItem` Eloquent model. Instead, the relationship uses a `belongsToMany` via `items()->sync()`. This is functionally acceptable but deviates from the entity list.

---

## 8. API-Contract.md

### 8.1 What is CORRECT

- **Nothing.** The API contract is entirely unimplemented.

### 8.2 What is INCORRECT or MISSING

This is a **critical gap**. The API contract defines:

- Base path `/api/v1` — **missing.** `routes/web.php` only has a welcome route.
- Authentication endpoints (`/auth/login`, `/auth/logout`, `/auth/me`) — **missing.** The app uses Filament's built-in session auth.
- Floor and occupancy endpoints (`/floor`, `/billing-groups`, `/occupied-zones`) — **missing.**
- Ordering endpoints (`/orders`, `/orders/{id}/void-items`) — **missing.**
- Production ticket endpoints (`/production-tickets`, `/production-tickets/{id}/reprint`) — **missing.**
- Billing and checkout endpoints (`/billing-documents`, `/payments`, `/billing-groups/{id}/reopen`) — **missing.**
- Event log endpoints (`/event-log`) — **missing.**
- Admin configuration endpoints (`/venues`, `/sections`, `/menu-items`, etc.) — **missing.**
- DTO guidance — **not followed.**
- Response envelope (`{success, data, meta}` / `{success, error}`) — **not used.**
- Common error codes (`ZONE_OVERLAP`, `VERSION_CONFLICT`, `GROUP_CLOSED`, etc.) — **not exposed via API.**
- Optimistic locking via `versionNumber` — **not enforced.**

**Impact:** Without the API, there is no machine-readable interface for first-party clients, no explicit request/response contracts, and no separation between backend domain logic and frontend transport. The entire application is driven through Filament's server-rendered admin panel.

---

## 9. Printing-Hardware.md

### 9.1 What is CORRECT

- **MVP printer inventory:** 1 kitchen, 1 bar, 1 per cashier. ✅ Seed data reflects this.
- **80mm thermal printer assumption** with ESC/POS. ✅ `TicketRenderer` uses 42-char width ASCII.
- **Auto-cutter support** via `\x1D\x56\x01` in `LanEscPosAdapter`. ✅
- **Preferred connectivity:** Direct LAN first, USB agent second. ✅
- **Wi-Fi and Bluetooth explicitly excluded.** ✅ No code references them.
- **Kitchen printer** receives production tickets and void slips. ✅
- **Bar printer** receives bar production tickets and void slips. ✅
- **Cashier printer assignment** via `CashierPrinterAssignment`. ✅
- **Void slips routed to same destination as original.** ✅ `OrderService::voidItem` routes by `fulfillment_route`.
- **Bill reprints** triggered by cashier/admin. ✅ `BillingService::reprintBill`.
- **Queue persistence on failure.** ✅ `PrintJob` records remain in `FAILED` state.
- **Retry behavior.** ✅ `PrintQueueService::retry` re-queues failed jobs.
- **Ticket content minimums** (group, zone, delivery, items, timestamp, reprint/void marking). ✅ `TicketRenderer` includes all.
- **Bill content** (group, items, prices, totals, payments, balance, reprint marking). ✅ `TicketRenderer` includes all.
- **Admin-only test prints and route changes.** ✅ `PrinterResource` and `PrinterRouteResource` are admin-only Filament resources.

### 9.2 What is INCORRECT or MISSING

- **Menu-item destination setup:** The docs say:
  > "Each menu item must have an explicit configured production destination for MVP."
  
  The current implementation derives routing from `MenuCategory.route_type`, not from a per-menu-item configuration. This is a pragmatic simplification but deviates from the spec. `MenuItemResource` does not show a printer route assignment field per item.

- **Cashier printer assignment per station vs. per user:** The docs say:
  > "Each cashier station has its own printer... Each cashier account or cashier station should be associated with one cashier printer."
  
  The implementation uses `CashierPrinterAssignment.user_id`, which is user-based. This is acceptable but station-based assignment is not supported.

- **Startup test procedure** is not automated or guided in the UI. The docs recommend a pre-service checklist with test prints; the app has no dedicated "Pre-service startup" screen or wizard.

---

## 10. Definition-of-Done.md

### 10.1 What is CORRECT

- **DoD-001 (Scope alignment):** Mostly yes, except missing API and Livewire.
- **DoD-002 (Acceptance criteria coverage):** Most ACs are satisfied at the service level.
- **DoD-005 (Audit-safe):** Yes, `Audit::record` is used pervasively.
- **DoD-006 (Visible failure handling):** Yes, exceptions surface as Filament notifications.
- **DoD-011 (Data constraints):** Yes, overlap rejection, zone linkage, delivery override are enforced.
- **DoD-014 (Printer routing):** Yes, matches documented rules.
- **DoD-015 (Supported connectivity):** Yes, LAN and USB agent adapters exist.
- **DoD-020 (Supported languages):** Partial — seed data exists but UI switcher missing.

### 10.2 What is INCORRECT or MISSING

- **DoD-007 (Automated test coverage):** **Insufficient.** Only 5 meaningful test files exist:
  - `BillingFlowTest` (4 tests)
  - `OccupancyOverlapTest` (4 tests)
  - `OrderSubmissionTest` (3 tests)
  - `RolePolicyTest` (5 tests)
  - `PrintJobRetryTest` (3 tests)
  
  **Missing tests for:**
  - Delivery override validation
  - Mixed kitchen/bar routing (tested in `OrderSubmissionTest` but not in isolation)
  - Bill reprint neutrality
  - Role-based authorization at the service/controller level
  - Concurrency / version conflict
  - Closed-group order rejection
  - Invalid status transition rejection
  - Accounting export
  - Event-log filtering

- **DoD-012 (Concurrency protection):** **Missing.** `version_number` exists but is unused. Overlap checks use `lockForUpdate` which is good, but billing-group concurrent edits are unprotected.

- **DoD-018 (Device-appropriate behavior):** **Not demonstrated.** Filament Pages are not optimized for phone-based floor work. The docs require "one primary task screen at a time" on phone and "floor plus contextual detail panel" on tablet.

- **DoD-021 (Local deployment documented):** Docker Compose exists but no README or deployment guide was found in the repo root.

- **DoD-023 (No blocker for running one real service):** The missing API and Livewire UI mean the app can only be used through the Filament admin panel. For a real service with 12+ servers on phones, this is a **significant operational blocker**.

---

## 11. Seed-Data.md

### 11.1 What is CORRECT

- **One venue** (`MAIN`) ✅
- **One active service session** ✅
- **Roles and sample users** (admin, cashier1, server1) ✅
- **Billing statuses** (WAITING, ACTIVE, CHECK_REQUESTED, PARTIALLY_PAID, CLOSED) ✅
- **Venue layout** with sections, rows, seats, seat pairs ✅
- **Printers and printer routes** ✅
- **Cashier printer assignment** ✅
- **Menu categories and items** (kitchen + bar mix) ✅
- **Basic translations** (pt-PT + en-US) ✅
- **Sample open billing group spanning two zones** ✅

### 11.2 What is INCORRECT or MISSING

- **Sample orders, production tickets, bills, payments, and audit events** are **not seeded.** The docs say seed data should include these. `CoreSeeder` creates the open group and zones but does not create orders, tickets, bills, or payments.
- **Sample bill, partial payment, and reprint trail** are missing from seed data.

---

## 12. User-Stories.md

### 12.1 Mapping

| Story | Status | Notes |
|---|---|---|
| US-001 (Open billing group with seat ranges) | ✅ | `BillingGroupService::open` + `OccupancyService::assignZone` |
| US-002 (See free/occupied ranges) | ✅ | `Floor` page |
| US-003 (Assign status) | ✅ | `BillingGroupService::setStatus` |
| US-004 (Add orders to group/zone) | ✅ | `OrderEntry` page + `OrderService::submit` |
| US-005 (Link order to zone) | ✅ | `OrderHeader.occupied_zone_id` |
| US-006 (Default delivery center) | ✅ | `OccupiedZone::defaultDeliveryLabel()` |
| US-007 (Override with seat pair) | ✅ | `OrderService::submit` validates pair |
| US-008 (Auto route food/drinks) | ✅ | `OrderService` splits by `fulfillment_route` |
| US-009 (Void with printed slip) | ✅ | `OrderService::voidItem` |
| US-010 (Kitchen printed tickets) | ✅ | `ProductionTicket` + print queue |
| US-011 (Bar printed tickets) | ✅ | Same |
| US-012 (Tickets show group/zone) | ✅ | `TicketRenderer` |
| US-013 (Tickets show delivery point) | ✅ | `TicketRenderer` |
| US-014 (Void/reprints marked) | ✅ | `TicketRenderer` + flags |
| US-015 (Cashier finds open groups) | ✅ | `CashierCheckout` page |
| US-016 (Cashier sees zones) | ✅ | `CashierCheckout` + `BillingGroupDetail` |
| US-017 (Print internal bill) | ✅ | `BillingService::generateInternalBill` |
| US-018 (Register partial payment) | ✅ | `BillingService::recordPayment` |
| US-019 (Reopen after partial checkout) | ✅ | `BillingGroupService::reopen` |
| US-020 (Reprint bill without altering history) | ✅ | `BillingService::reprintBill` |
| US-021 (Log all actions) | ✅ | `Audit::record` |
| US-022 (Server reopens group) | ✅ | UI action present |
| US-023 (Define venue structure) | ✅ | Filament resources |
| US-024 (Configure printer routing) | ✅ | `PrinterRouteResource` |
| US-025 (Manage user roles) | ✅ | `UserResource` + seeder |
| US-026 (Configure billing statuses) | ✅ | `BillingStatusResource` |
| US-027 (Export accounting data) | ❌ | Not implemented |
| US-028 (Multilingual UI) | ⚠️ | Infrastructure exists; UI integration missing |
| US-029 (Full event log) | ✅ | `AuditEventResource` |

---

## Summary of Critical Gaps

### 🔴 Blockers for MVP Release

1. **No REST API** — `api-contract.md` defines the entire contract, but `routes/web.php` only has a welcome page. The application has zero API controllers.
2. **No Livewire Operational UI** — `architecture.md` and `screen-flows.md` require Livewire for floor, order entry, and cashier workflows. All operational screens are Filament admin pages, which are unsuitable for phone-based server work.
3. **Weak Permission Enforcement** — Spatie permissions are seeded but not enforced in domain services or consistently in the UI. A server can trigger bill prints because the button lacks `->can('bill.print')`.
4. **Missing Accounting Export** — `US-027` and `AC-027` are unimplemented.
5. **Insufficient Test Coverage** — Only 19 tests across 5 files. Critical paths like concurrency, version conflicts, and role authorization at the service level are untested.
6. **Version Number Unused** — Optimistic locking is specified but not implemented, risking concurrent edit issues with 12+ servers.

### 🟡 Important but Non-Blocking

7. **Status Transition Rules Not Enforced** — Any status can jump to any status.
8. **Language Switcher Missing** — Translations exist in DB but no UI to switch languages.
9. **Seed Data Incomplete** — No sample orders, tickets, bills, or payments in seeder.
10. **Menu-item routing is category-level**, not item-level as specified in `printing-hardware.md`.
11. **No Policies directory** — Authorization is ad-hoc rather than policy-driven.
12. **No mobile-optimised views** — Filament's default responsive grid is not enough for fast floor work on phones.

### 🟢 Well-Implemented Areas

- Data model and migrations
- Domain services (`BillingGroupService`, `OccupancyService`, `OrderService`, `BillingService`)
- Printing subsystem (adapters, registry, queue, renderer, job)
- Audit event recording
- Filament admin/configuration resources
- Seed data for layout, users, statuses, printers, menu
- Overlap validation with database locking
- Void slip generation and reprint marking
- Partial payment and reopen logic

---

## Recommendations

1. **Implement the REST API** as defined in `api-contract.md`. Create `API` namespace controllers, form requests, and resource transformers.
2. **Build Livewire components** for `Floor`, `BillingGroupDetail`, `OrderEntry`, and `CashierCheckout`. Move these out of Filament Pages.
3. **Enforce permissions** using Laravel Policies (`BillingGroupPolicy`, `OrderPolicy`, etc.) and check them in both UI and API layers.
4. **Implement optimistic locking** on `BillingGroup` by checking `version_number` on every update and rejecting stale writes.
5. **Add status transition validation** in `BillingGroupService::setStatus`.
6. **Add the Accounting Export** feature with a queued job and Filament page.
7. **Expand test coverage** to include concurrency, role gates, invalid transitions, and delivery override edge cases.
8. **Add a language switcher** to the operational UI and wire `TranslationKey` into Laravel's translator.
9. **Complete seed data** with sample orders, tickets, bills, and payments.
10. **Document deployment** with a pre-service startup checklist for printers.

---

## Post-Audit Action Log

*Date: 2026-04-29*

### Docs corrected (small deviations → align docs with code)

| Doc | Change | Reason |
|---|---|---|
| `data-model.md` | `AppUser` / `AppRole` / `UserRoleAssignment` sections updated to reflect Laravel `User` model + Spatie Permission package usage | Naming convention divergence; functionally identical |
| `data-model.md` | `ProductionTicketItem` clarified as pivot table without dedicated Eloquent model | Implementation uses `belongsToMany` / `sync`; no functional impact |
| `printing-hardware.md` | Menu-item destination setup clarified as category-level (`MenuCategory.route_type`) | Current implementation routes by category; works correctly for MVP |
| `printing-hardware.md` | Cashier printer assignment clarified as per-user via `CashierPrinterAssignment` | Current implementation uses `user_id`; sufficient for MVP |

### GitHub issues created (real gaps → need code changes)

| Issue | Title | Priority | Why it matters |
|---|---|---|---|
| [#1](https://github.com/ricafett/serveo/issues/1) | Implement REST API surface per api-contract.md | 🔴 Critical | Zero API controllers exist; blocks any first-party client integration |
| [#2](https://github.com/ricafett/serveo/issues/2) | Build Livewire operational UI screens | 🔴 Critical | All operational screens are Filament admin pages; unsuitable for 12+ phone-based servers |
| [#3](https://github.com/ricafett/serveo/issues/3) | Enforce role-based access control | 🔴 Critical | Servers can currently trigger bill prints; permissions seeded but not enforced |
| [#4](https://github.com/ricafett/serveo/issues/4) | BillingGroup status transitions + optimistic locking | 🟡 High | Any→any status changes allowed; `version_number` column exists but unchecked |
| [#5](https://github.com/ricafett/serveo/issues/5) | Implement Accounting Export | 🟡 High | In-scope per product-scope.md; model exists but zero logic/UI |
| [#6](https://github.com/ricafett/serveo/issues/6) | Add runtime multilingual UI support | 🟡 High | TranslationKey seeded but not wired into translator; no language switcher |
| [#7](https://github.com/ricafett/serveo/issues/7) | Complete seed data with sample transactions | 🟡 High | Makes local dev and smoke testing harder without realistic data |
| [#8](https://github.com/ricafett/serveo/issues/8) | Expand automated test coverage | 🟡 High | Only 19 tests; many critical paths untested (concurrency, role gates, transitions) |

### Resolved / no-action audit items

- ✅ Data model & migrations — fully aligned; no issues needed
- ✅ Domain services (Floor, Orders, Billing) — fully aligned; no issues needed
- ✅ Printing subsystem — fully aligned; no issues needed
- ✅ Audit trail — fully aligned; no issues needed
- ✅ Filament admin resources — fully aligned; no issues needed
- ✅ Seed data (baseline) — fully aligned; no issues needed

---

*End of Report*
