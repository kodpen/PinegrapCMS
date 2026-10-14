---
name: express-order-notes-address-book
description: Express order (EO) widget bindings for order note (eo_field notes) and address book section, plus the narrow-screen table classes and the three places to update.
---

## How does the EO order note binding work? (2026.1.28)
- `eo_field` binding `notes`: set `_bindings.eo_field='notes'` on an input/textarea inside the EO widget.
- `submit_order.php` probes defensively (`SHOW COLUMNS FROM orders LIKE 'notes'`): saves to `orders.notes` if the column exists, silently skips otherwise (legacy installs unaffected, upgraded ones gain the feature).

## How does the address book section work? (2026.1.28)
- Section binding `address_book` (`_bindings.section='address_book'` on a semantic div). Rendered only for logged-in members: DISTINCT billing addresses from the member's past `complete`/`exported` orders as a `<select>` (max 10).
- Picking an address fills the order form's billing_* inputs via inline JS (`querySelector` by name attribute).
- Guest / ghost mode / member with no past orders returns an empty string (binding silently hidden).
- Each option carries the payload inline as `data-pgab='{"billing_first_name":"…",…}'`.
- JS is one-shot (`window.__pgEoAddressBookBound`): bound once even with several EO widgets.
- After fill, a `change` event is dispatched on every input so country/state cascades fire.
- Quick prefill (B2): billing_* prefill from member contact data already exists in `get_express_order.php` (contact fetch + `assign_field_value` chain, ~line 2587-2670); no new work needed.

## How are table widgets made responsive on narrow screens? (2026.1.30)
- Legacy `table.mobile_stacked` (livesite.src.css:490) was not ported. EO cart table's right columns are fixed `6+8+8+3 = 25rem`; below ~900px the name column shrank to 188px and wrapped to five lines. `table-responsive` is NOT a fix (the table is squeezed, not overflowing).
- Fix (Bootstrap only, no extra CSS): `thead` -> `d-none d-md-table-header-group`; `tr` -> `d-block d-md-table-row` (+ border-bottom for row separation on mobile); `td/th` -> `d-block d-md-table-cell`; `text-end` -> `text-start text-md-end`.
- Qty stepper fixed `width:9rem` became `max-width:9rem` (fixed width was the floor that stopped the table shrinking).
- Update in THREE places together: `_eo_default_designer_tree()` (PHP), `_buildExpressOrderStarterTree()` (JS), `_eo_render_cart_summary()` (server fallback).
- Do NOT give `colspan` rows `d-block`: once the cell leaves table layout, colspan is cancelled.
