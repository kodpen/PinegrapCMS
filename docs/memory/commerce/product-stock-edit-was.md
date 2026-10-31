---
name: product-stock-edit-was
description: Edit Product writes the stock count only when it was changed on the screen, and only over the count it opened with; a count that moved meanwhile is kept and the operator is told
---

## When does an Edit Product save write the stock count?
- The screen posts `inventory_quantity_was`, the stored count when it opened (edit mode only, next to the Inventory Quantity box in `pg_pb_render_product_screen()`).
- `pg_pb_stock_save_action(posted, was, stored)` (pure, `tests/product_stock_was_test.php`) decides: `keep` (count not changed, or the typed count is the stored one), `write` (changed, stored still the opened count; or no numeric was value: old form, writes as before), `conflict` (changed, but checkout moved it meanwhile).
- `keep`/`conflict`: `inventory_quantity` is left out of the row; `out_of_stock` and the `pg_product_stock_delta()` announcement use the stored count, so nothing is announced.
- Guarded `write`: the row is written without the count, then `UPDATE … SET inventory_quantity, out_of_stock[, out_of_stock_timestamp] … AND inventory_quantity = <was>`. No row matched: the stored count is kept and the flag is recomputed from it.
- The bundle branch is untouched: a bundle's count is the stored cache and never guarded.

## What does the operator see on a conflict?
- Everything else is saved (this screen does not refill typed fields after a refusal, so the whole save is not refused). `pg_pb_update_product()` returns `stock_kept`; `edit_product.php` adds the warning "The stock of this product changed while the screen was open: it is now {var:1}; the count you typed was not saved." On a save that would leave for a screen other than the product list, it stays on Edit Product so the warning is drawn.

## Why?
- Checkout lowers stock relatively (`inventory_quantity - n`, `includes/fn/order_completion.php`). The screen wrote back the count read when it opened on every save: stock 10, two orders took 3, a description save put it back to 10, unflagged a sold-out product and announced `inventory.changed` 7→10 (webhooks, back-in-stock mails, marketplace queue). Same mechanism as the gift card screen's `balance_was` ([[gift-card-balance-edit]]) and the Workspace drawer's `ws_quick_edit_moved`.

## How was it checked?
- Unit tests of the helper and source pins; no browser run (the paths need a sale between open and save, which a unit test covers through the pure decision).

Related: docs/memory/api/api-webhook-stock-events.md, docs/memory/commerce/edit-product-undrawn-columns-kept.md
