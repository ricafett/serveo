# Decision 003: Menu Item Variants and Modifiers

## Status

Planned

## Context

Menu items need two new dimensions:

- **Variants**: Named alternatives for a menu item (e.g., "Soda" → "Coca-Cola", "7Up"). Variants are stored per-item and have no pricing impact. When variants exist on an item, the server **must** pick one.
- **Modifiers**: Optional attributes grouped into named sets (e.g., "Temperatura" → "Fresca", "Natural"). An admin creates a modifier set, then assigns it to one or more menu items. An item may link to at most one modifier set.

Both were requested as a single feature. The plan below addresses data model, admin configuration, order-entry UI, backend validation, print rendering, and seed data.

## Decision

### Database changes

#### New tables

**`modifier_sets`**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `display_name` | string | e.g., "Temperatura" |
| `selection_mode` | string, default `single` | `single` or `multiple` |
| `sort_order` | unsigned int, default 0 | |
| `is_active` | bool, default true | |
| `timestamps` | | |

**`modifier_set_items`**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `modifier_set_id` | FK → modifier_sets | on delete cascade |
| `display_name` | string | e.g., "Fresca" |
| `sort_order` | unsigned int, default 0 | |
| `is_active` | bool, default true | |
| `timestamps` | | |

Unique index: `(modifier_set_id, display_name)`

**`menu_item_variants`**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `menu_item_id` | FK → menu_items | on delete cascade |
| `display_name` | string | e.g., "Coca-Cola" |
| `sort_order` | unsigned int, default 0 | |
| `is_active` | bool, default true | |
| `timestamps` | | |

Unique index: `(menu_item_id, display_name)`

#### Existing table changes

**`menu_items`**

| Change | Notes |
|---|---|
| Add `modifier_set_id` nullable FK → `modifier_sets` | `nullOnDelete` — when a set is deleted, items referencing it get `null` |

**`order_items`**

| Change | Notes |
|---|---|
| Add `variant_name` varchar, nullable | Denormalized snapshot |
| Add `modifier_name` varchar, nullable | Denormalized snapshot; comma-separated for multi-select sets |

### Models

| Model | File | Relations |
|---|---|---|
| `ModifierSet` | new `app/Models/ModifierSet.php` | `hasMany(ModifierSetItem)`, `hasMany(MenuItem)` |
| `ModifierSetItem` | new `app/Models/ModifierSetItem.php` | `belongsTo(ModifierSet)` |
| `MenuItemVariant` | new `app/Models/MenuItemVariant.php` | `belongsTo(MenuItem)` |
| `MenuItem` | updated | add `belongsTo(ModifierSet)`, `hasMany(MenuItemVariant)` |
| `OrderItem` | updated | add `variant_name`, `modifier_name` to `$fillable` |

### Admin UI (Filament)

1. **New `ModifierSetResource`**
   - List/create/edit pages under Configuração navigation group
   - Permission: `menu.manage`
   - Form: `display_name`, `selection_mode` (radio: single/multiple), `sort_order`, `is_active`
   - Items managed inline via Filament `Repeater` (display_name, sort_order, is_active)

2. **Updated `MenuItemResource`**
   - Add `Select` for `modifier_set_id` (nullable, lists active sets)
   - Add `Repeater` for `menu_item_variants` (display_name, sort_order, is_active)

### Order-entry UI (Livewire + Alpine.js)

1. **Modal on item tap**: When a menu item has variants or a modifier set, tapping it opens a small modal positioned contextually over the item card (not a full-page overlay).
   - **Variant selector**: required if item has variants. Shown as radio buttons.
   - **Modifier selector**: optional. Single-select (radio) or multi-select (checkboxes) depending on set's `selection_mode`.
   - **Confirm / Cancel** buttons.

2. **Cart identity**: Each cart entry is keyed by `menu_item_id + variant_name`. Adding the same item with a different variant creates a separate cart line. Adding the same item with the same variant increments quantity.

3. **Cart display**: Each line shows `display_name — variant_name (modifier_name)`.

4. **Submit payload**: Extended to include `variant_name` and `modifier_name` per line.

### Backend validation (OrderService)

- If a `MenuItem` has variants, `variant_name` is required in the line payload.
- `modifier_name` is always optional.
- Invalid variant names (not matching an active variant) are rejected.
- Invalid modifier names (not matching active items in the item's modifier set) are rejected.
- If modifier set's `selection_mode` is `single` and more than one modifier name is passed, reject.

### Print rendering (TicketRenderer)

- Each line item appends variant and modifier: `2x Soda — Coca-Cola (Fresca)`
- Variants produce separate ticket lines (same `menu_item_id`, different `variant_name`) — matches the separate-cart-line semantics.

### Seed data

- Create modifier set "Temperatura" (`single`) with items "Fresca", "Natural"
- Create modifier set "Extras" (`multiple`) with items "Queijo extra", "Bacon extra", "Molho picante"
- Add variants to "Água 50cl": "c/gás", "s/gás"
- Add variants to "Vinho tinto - copo": "Casa", "Reserva"
- Translations for all new UI strings (pt-PT + en-US) in CoreSeeder and `lang/en/*.php`

### Migration order

Single migration creating `modifier_sets`, `modifier_set_items`, `menu_item_variants`, and the two column additions.

## Consequences

- **No pricing impact**: Variants and modifiers carry no price adjustment. This is an intentional simplification for MVP.
- **Separate line items**: Variants create distinct `OrderItem` rows. This ensures kitchen tickets, order views, and checkout lists show them as independent items that can be individually voided.
- **Modifier set deletion**: When a `ModifierSet` is deleted, all referencing `MenuItem` records get `modifier_set_id = null` (`nullOnDelete`), effectively removing the modifier prompt for those items without data loss.
- **Denormalized storage**: `variant_name` and `modifier_name` are snapshots on `OrderItem` (same pattern as `unit_price`). Renaming a variant or modifier later does not alter historical orders.
- **History**: Existing audit events on orders are unaffected. The new fields widen `OrderItem` but don't change the event structure.
