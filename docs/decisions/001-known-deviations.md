# ADR 001: Known MVP Deviations from Initial Spec

## Status
Accepted — deviations are intentional for the initial backend baseline. They are tracked as open GitHub issues for post-MVP resolution.

## Context
The initial spec pack (`docs/spec/`) was written before implementation began. During the backend baseline phase, several pragmatic simplifications were made that deviate from the documented architecture and business rules. This record exists so future agents do not waste time "fixing" these as if they were accidental.

## Deviations

### 1. Operational screens are Filament Pages instead of Livewire components
**Spec:** `architecture.md` and `screen-flows.md` mandate Livewire + Blade for operational screens (Floor, Billing Group Detail, Order Entry, Cashier Checkout). Filament is reserved for admin/configuration.

**Reality:** All operational screens are implemented as `Filament\Pages` (`Floor.php`, `BillingGroupDetail.php`, `OrderEntry.php`, `CashierCheckout.php`).

**Rationale:**
- Filament provided a complete scaffold for CRUD, forms, actions, and notifications out of the box.
- Livewire operational UI requires significant frontend work (mobile optimization, role-based landing, task-first navigation).
- The domain services underneath are UI-agnostic; replacing Filament Pages with Livewire later is a presentation-layer change only.

**Tracking:** GitHub Issue #2 — "Build Livewire operational UI screens"

---

### 2. Menu-item routing is category-level, not per-item
**Spec:** `printing-hardware.md` states: "Admin must be able to assign a production destination to each menu item."

**Reality:** Routing is determined by `MenuCategory.route_type` (KITCHEN / BAR / NONE). All items in a category inherit the same route.

**Rationale:**
- The current menu is small and category boundaries align perfectly with kitchen vs. bar.
- Per-item routing adds UI complexity (another field on `MenuItemResource`) with no operational benefit for the current menu.
- The `OrderItem.fulfillment_route` field is still denormalized at the line level, so per-item routing can be added later without rewriting order history.

**Tracking:** No dedicated issue; can be reopened if the menu grows complex enough to need per-item overrides.

---

### 3. Cashier printer assignment is per-user, not per-station
**Spec:** `printing-hardware.md` states: "Each cashier station has its own printer... Each cashier account or cashier station should be associated with one cashier printer."

**Reality:** `CashierPrinterAssignment.user_id` links a user to a printer. There is no station entity.

**Rationale:**
- MVP assumes one cashier per station, making user-based assignment functionally equivalent.
- Introducing a `Station` entity is premature abstraction for the current scale (3 cashiers).
- The fallback mechanism (any active `BILL` printer) covers cases where assignment is missing.

**Tracking:** No dedicated issue; revisit if multi-user-per-station scenarios arise.

---

### 4. `version_number` on `BillingGroup` exists but is unused
**Spec:** `business-rules.md` (BR-070) and `api-contract.md` require optimistic locking. The `data-model.md` includes `version_number` on `BillingGroup`.

**Reality:** The column is present but never checked or incremented.

**Rationale:**
- Overlap-sensitive writes (`OccupancyService::assignZone`) already use `lockForUpdate`, which covers the most dangerous concurrency scenario.
- Billing-group status edits are relatively low-frequency compared to order submission.
- The field was added proactively so the API layer (Issue #1) can implement it without a migration.

**Tracking:** GitHub Issue #4 — "BillingGroup status transitions + optimistic locking"

---

### 5. `AppUser` / `AppRole` / `UserRoleAssignment` names differ from spec
**Spec:** `data-model.md` defines `AppUser`, `AppRole`, and `UserRoleAssignment` entities.

**Reality:** The implementation uses Laravel's default `users` table and Spatie Permission's `roles` / `permissions` / `model_has_roles` tables.

**Rationale:**
- Laravel's built-in auth and Spatie Permission are battle-tested and integrate seamlessly with Filament.
- Renaming tables would break framework conventions and package compatibility for zero functional gain.
- The `User` model docblock explicitly notes it maps 1:1 to the `AppUser` entity.

**Tracking:** `data-model.md` was updated to reflect this mapping. No code change needed.

---

## When to revisit
Revisit this ADR when any of the following happen:
- Issue #2 is closed (Livewire UI implemented)
- The menu grows to need per-item routing
- Station-based hardware tracking becomes necessary
- Issue #4 is closed (optimistic locking enforced)

## Related
- `docs/spec/architecture.md`
- `docs/spec/printing-hardware.md`
- `docs/spec/data-model.md`
- `docs/spec/business-rules.md`
- GitHub Issues #1, #2, #3, #4
