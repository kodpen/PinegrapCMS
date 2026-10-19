---
name: product-price-history
description: product_price_history (2026.11.0, 11.0.2, PR #545): one row per change of products.price, pg_price_history_record() / queue after every writer, initial rows, API read, compare_at_price, known limits
---

## What is recorded and why?
- `product_price_history` (product_id, channel, price minor units, currency, changed_at, source): one row each time a product's own price changes. The "price before the discount" (TR price label rule: lowest of the last 30 days, last 10 days for discount ads from 2026-08-01; EU Omnibus: 30 days) is read from it, so it must see every change.
- `channel` is `web` for `products.price`, the shop's own price. `pos` / `marketplace:<code>` are reserved for prices kept per channel (a marketplace account selling at its own prices).
- `source`: `initial` (upgrade), `panel` (screens, Workspace included), `import`, `api` (API and MCP tools), `offer`, `erp` (reserved). VARCHAR, not ENUM, so a later label never turns into `''`.
- `products.compare_at_price` (INT NULL) came in the same step for the merchant's "was" price; nothing reads or writes it yet.

## How do writers record a change?
- After the write, `pg_price_history_record($ids, $source)` (`includes/fn/price_history.php`): it reads the stored price, compares it with the product's newest row (MAX(id)) and writes a row only when price or currency differs. The same price again writes nothing, so a screen that cannot tell whether the price changed calls it on every save.
- Bulk writers call `pg_price_history_queue($id, $source)` after each write and `pg_price_history_flush()` after the loop. The queue writes every 500 products and from a shutdown function, so an import stopped by `output_error()` or the time limit keeps the rows of the prices it changed.
- It never stops the request: `@mysqli_query`, failures go to the error log (`Price history: ... failed`); the next save of that product catches up.
- `pg_pb_delete_product()` calls `pg_price_history_forget()`: MySQL 5.7 may reuse a deleted product's id after a restart.

## Which code paths call it?
- `pg_pb_create_product()` right after the insert (product screens, variant sets with price rules, API create with `price_source => 'api'`, Workspace create, design templates), `pg_pb_update_product()`, `pg_pb_quick_update()`, `edit_products.php` mass edit, file manager bulk and quick edit, `import_products.php`, `duplicate_product()`, `duplicate_products.php`, API `products.update` and `products.prices`, Workspace `ws_change_write_product()`.
- ERP writes no `products.price` (account prices are `erp_account_prices`). A new writer must add the call (`pinegrap-yeni-dosya` skill).

## How is it read back?
- `GET /products/{id}/price-history`: product-price-history-api.md. No panel screen reads the rows yet.

## What does the upgrade write?
- One `initial` row per product at its current price, stamped with the upgrade time (PHP clock); a product with a `web` row is skipped, so a rerun adds nothing. A fresh install loads the starter data first, so its products get the row too.
- `products.timestamp` is not used as the start: other edits move it and the installer stamps every product.
- `products` is in `install_heavy_tables()` for 2026.11.0: the column goes in INSTANT where possible, by copy otherwise (MyISAM, MySQL 5.7).
- A dev database already at `config.version` 2026.11.0 skips the step until the version is set back to 2026.4.10 (release-status.md).

## What are the known limits?
- Offer prices are not recorded, so the history alone cannot give a lawful "lowest price" yet (plan file).
- A base currency change does not rewrite `products.price`; rows in the new currency appear as products are saved.

## How was it verified?
- `tests/price_history_test.php`; on MariaDB 10.11 the step ran twice (second run skipped all), the MyISAM copy fallback, a fresh install, and each writer above over HTTP or directly (dry runs wrote nothing).

Related: docs/memory/plans/price-history-reference-price.md, docs/memory/commerce/variant-price-rules.md
