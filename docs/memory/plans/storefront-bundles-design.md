---
name: storefront-bundles-design
description: Bundles design note (storefront-conversion-pack step 6, for the owner's review): model, stock, price, schema; hooks and steps in storefront-bundles-hooks.md
---

## What is a bundle?
- A `products` row with `bundle = 1` whose contents are other products in fixed whole quantities (`product_bundle_items`). It is sold, priced, taxed, shipped and offered as one product: one cart line, one `order_items` row, one invoice line.
- A component is a plain product: not a bundle (no nesting), not a donation, reservation or gift card. A variant row is a component like any other (`commerce/products-variant-model.md`); no "pick a colour inside the bundle".

## How is its stock derived?
- `inventory_quantity` of a bundle is a cache: `min(floor(component stock / quantity))` over its tracked components; an untracked component sets no limit; all untracked: the bundle is untracked (`inventory = 0`). `out_of_stock` follows with `pg_product_out_of_stock_sql()`. `backorder` on the bundle is allowed only if every tracked component allows it.
- Why a cache and not a read-time join: about thirty readers use `products.inventory_quantity` (catalog widgets, `check_inventory()`, feeds, marketplace listings, the API, stock notices, dashboards). With the cache they all stay as they are.
- `pg_bundle_refresh_for($product_id)` recomputes the bundles that contain a product. It is called from `pg_product_stock_changed()` and `pg_marketplace_product_changed()` (`includes/fn/ecommerce.php`), the call every stock writer already makes (commerce key rule), so the marketplace refund restock (`includes/api/outbound/orders.php`) and ERP counts are covered too. `import_products.php` calls neither: it ends with one `pg_bundle_refresh_all()` pass. A changed bundle announces its own `inventory.changed` (source `bundle`), so back-in-stock notices work for bundles.
- A bundle's stock is never written directly: the product screen shows it read-only; the inventory API, barcode screens, ERP stock count and imports refuse or skip a bundle id.

## How is it priced?
- `bundle_pricing`: `fixed` = `products.price` typed as today; `sum` = `round(sum(component price × quantity) × (10000 − bundle_discount_bp) / 10000)` written into `products.price` (minor units, `round()`). A component price change re-prices its `sum` bundles through the same refresh, with `pg_price_history_record()`.
- Charged as a normal line from `products.price`: `get_express_order.php`, `get_order_preview.php` and `submit_order.php` totals are unchanged (`workflow/decisions-made-product.md`). The bundle's own `tax_rate` applies to the whole line; offers target the bundle id like any product.

## Which schema is proposed? (owner approval needed, Fixed rule 5)
One sub-step `upgrade_2026_11_0_product_bundles()` in the open version, label taken at coding time from the general range, new tables in `get_tables()`:
- `products`: `bundle TINYINT(1) NOT NULL DEFAULT 0`, `bundle_pricing ENUM('fixed','sum') NOT NULL DEFAULT 'fixed'`, `bundle_discount_bp SMALLINT UNSIGNED NOT NULL DEFAULT 0` (`install_add_column()`).
- `product_bundle_items`: id, bundle_id, product_id (INT UNSIGNED), quantity INT UNSIGNED, sort_order; UNIQUE (bundle_id, product_id), KEY (product_id).
- `order_item_components`: id, order_id, order_item_id, product_id, quantity (per bundle unit), unit_cost BIGINT; KEY (order_item_id), KEY (order_id). A snapshot written at checkout: a later edit of the bundle never changes an old order, return or invoice.
- `erp_invoice_item_components`: the same snapshot for an ERP invoice line (invoice_id, line_id, product_id, quantity).
- `erp_stock_moves`: UNIQUE `uniq_line (kind, line_id)` becomes `(kind, line_id, product_id)`, so one bundle line writes one move per component; every existing row stays unique (a line has one product today).
- The key change reads the old shape first (`release/migration-step-helpers.md`); run twice on a dev DB.

Hooks, steps and the owner's questions: `storefront-bundles-hooks.md`.
