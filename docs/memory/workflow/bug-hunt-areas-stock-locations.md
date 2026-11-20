---
name: bug-hunt-areas-stock-locations
description: The bug hunt of stock by location and the barcode stock screens (2026-11-20): the three faults fixed, the two issues opened, what was read clean
module: stock_locations
---

## Which area was hunted?
- Architect (2026-11-20, agent channel message 10907), before pick-and-pack PP2–PP5 build on it: `includes/fn/stock_locations.php`, `stock_moves.php`, `stock_location_screens.php`, `stock_pickup.php`, `stock_sales.php`, `barcode_increase_inventory.php`, `barcode_decrease_inventory.php`, `barcode_menu.php`, `view_stock_moves.php`.
- Left out as held: `stock_holds.php`, `includes/fn/ecommerce.php` (the stock-holds line), `stock_notify.php` (hunted in #10712), `erp_stock*.php`.

## What did the hunt's PR fix?
- Pickup at a location counted a bundle in the cart by the bundle's own rows, which are never kept up: a cart with a bundle was offered no pickup but at the default location, and there by a stale figure. `pg_stock_pickup_line_needs()` counts its tracked components, as completion takes them.
- The Transfer dialog moved a bundle's units: the writer then put the stale sum of its rows in place of its cached stock. `pg_stock_transfer()` refuses a bundle (`bundle`), as the inventory API does.
- A transfer refused after its first half left the total short on a `products` table still on MyISAM; the total is written again from the rows after the rollback.

## Which issues did it open?
- #10814: a change by a writer that changes only the total (marketplace orders, ERP, Workspace, import of an untracked product, bundle return) is undone by the next `pg_stock_change()`, which recomputes the total from the rows; needs a decision on where such a change lands.
- #10815: the counter sale limits by the total while its completion takes only from the default location (decision #5042).

## What was read clean?
- The writer: whole numbers only, the guarded row update, rows locked `FOR UPDATE` so two writers serialize, the seed under `INSERT IGNORE`, no nested transaction in its callers.
- The barcode screens: whole quantities, the code that is two products' refused, Recycle Bin and bundles refused, the location checked, the access check (`validate_ecommerce_access()`, Designer and Manager pass).
- The order's location (stored, then the first enabled method location, then the default), the sale's take and retry, the history filters, the locations card's save plan.

## What was not run?
- No database here: the fixes are pinned by `tests/stock_pickup_test.php` and `tests/stock_locations_moves_test.php`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/stock-locations-decisions.md
