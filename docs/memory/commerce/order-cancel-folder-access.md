---
name: order-cancel-folder-access
description: Private folder access an order granted, taken back on cancel and on a refunded return, given again on restore (#7204); no order reference on aclfolder, so it goes by user and folder
module: order_completion
---

## What happens to the folder access an order granted when it is cancelled?
- `process_order_cancellation()` calls `pg_order_folder_access_cancelled($order)` once, after the reward points. Each active line whose product has `grant_private_access` gives back its quantity less the units its refunded returns took back already (`pg_order_folder_access_lines()`, `pg_order_folder_access_held()`).
- `pg_order_folder_access_revoked()` (pure) decides the `aclfolder` row: limited access (`private_days` > 0) loses `private_days × units` calendar days from its end, and the row goes when that end is before today; unlimited access (`private_days` = 0) deletes the row. Kept: no row, edit rights (2), rights 0, an access that never ends after a limited purchase (the purchase did not write it, `pg_order_folder_access_extends()`).
- The row carries no order (no schema, owner decision #7204), so a grant staff gave by hand on the same user and folder goes with it; staff give it again by hand. A guest order (user 0) and a product with no folder (send-to page only) have nothing to take back. The user's home page (send-to page) is not changed.
- The store-wide private folder every order grants (`pg_order_complete_private_access()`, `ECOMMERCE_PRIVATE_FOLDER_ID`) is not taken back: it is not bought by a line, and another order may hold it.

## And on a refunded return?
- `pg_return_refund()` calls `pg_order_folder_access_lines_refunded($order_id, $returned_quantities)` next to the reward points: the returned units of each line are taken back, the same rule. Skipped for a cancelled order (the cancel took it) and a guest order.

## What does restoring the order do?
- `pg_order_restore_cancelled()` calls `pg_order_folder_access_restored()`: each line with units still held goes through `pg_order_complete_line_folder_access()` again (send-to page left out), so a limited access is extended from today or its current end.

## Why?
- Before #7204 a cancelled, refunded or charged-back order kept the folder; only `recurring_payment_job.php` revoked access. A gift card the order issued is voided on cancel; the files it sold now go too.

## Tests?
- `tests/order_folder_access_revoke_test.php`.

Related: docs/memory/commerce/order-cancel-gift-cards.md, docs/memory/commerce/product-bundles.md, docs/memory/workflow/decisions-made-product-34.md

## A bundle that already holds a private access component?
- It sells until its next save, which is refused; the bundle card names the component with a warning (`pg_pb_render_bundle_card()`).
