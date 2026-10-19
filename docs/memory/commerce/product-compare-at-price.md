---
name: product-compare-at-price
description: products.compare_at_price (2026.11.0, 11.0.2), the merchant's "was" price: NULL/0 rules, product screen switch, list column, import/export, API field, copy paths, what does not read it yet
---

## What is it and how is it stored?
- The merchant's own earlier or list price of one article, minor units like `price`. `NULL` is none; empty and `0` mean none too, so `0` is never stored. Not a selling price: the price history never records it, an offer never changes it.
- One judge for every writer: `pg_compare_at_price_value()` (`includes/fn/price_history.php`) gives `null` (none), the int, or `false` (negative, above the signed INT top `PG_COMPARE_AT_PRICE_MAX`, not whole). `false` is refused, never cut to fit: the session is not strict and MySQL would clamp it. `pg_compare_at_price_sql()` writes `NULL` or the quoted int.
- Every reader and writer asks `pg_compare_at_price_ready()` first; without the upgrade the site works as before (no switch or column, API reads `null`, an API write naming it gets 503 `service_unavailable`).

## How does the panel take it?
- Product screen (`product_builder.php`): a "Compare-at Price" switch under Taxable with the amount in its panel, like the tax rate. Off or empty/zero saves `NULL`. Typed amounts go through `pg_pb_compare_at_price_from_input()`, the price box's own reader (`pg_pb_price_to_cents()`), so both boxes read typing the same way; more than 15 digits or above the column is refused in `pg_pb_validate_product_post()` ("{var:1} is too large.").
- The switch is `pg-pb-single-only`: hidden while a variant set is built, and the set branch of `pg_pb_save_new_product()` writes none (like the GTIN, it names one article). A variant gets its own on its edit screen.
- Product list (`view_products.php`): a sortable "Compare-at Price" column after Price, editable in place (`pg_pb_quick_edit_columns()`, `'empty' => true` clears). Drawn only once a live product has one (`pg_pb_list_shows_compare_at_price()`), so a shop that never uses it keeps its list; the first one is set on the product screen, an import or the API.
- A change of it does not queue a marketplace sync: no connector sends it (n11 sends the one price as both list and sale price).

## How do files carry it?
- Export (`export_products_f.php`): heading `compare_at_price` after every fixed column, kind `money_optional`: an empty cell for none, `1299.90` otherwise.
- Import (`import_products.php`, heading `compare_at_price`, read with the product screen's reader): an empty cell leaves it as it is (the rule of every column), `0` takes it off, a negative figure or one the column cannot hold leaves the stored value alone. A new product without one gets `NULL`.
- Copies: `duplicate_product()` and `duplicate_products.php` copy it when their `SELECT *` row has the column.

## How does the API take it?
- `compare_at_price` on the Product object (`integer?`, `api_product_compare_at_price()`), and a `money` body field of `POST /products` and `POST /products/{id}` with `nullable`, min 0, max the INT top: JSON null, `""` or `0` clears. MCP tools take it from the routes. `POST /products/prices` stays price-only.

## What does not read it yet?
- The storefront: showing it is step 2 of docs/memory/plans/price-history-reference-price.md. In Turkey a crossed-out price must be the lowest of the previous 30 days, so the merchant's figure is shown only under that step's "manual" rule.
- Not in the file manager's quick edit, the mass edit (`edit_products.php`), the variant set screen or the Workspace record drawer.

## How was it verified?
- `tests/compare_at_price_test.php`, `tests/api_money_bounds_test.php`; on a sandbox (`tools/setup_sandbox.sh`): screen save and switch off, a crafted too-large post, list edit and sort, export, import cases, both copies, API read/write/dry run/create, OpenAPI and MCP shapes, and each path again with the column dropped.

Related: docs/memory/commerce/product-price-history.md, docs/memory/commerce/product-price-history-api.md
