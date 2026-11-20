---
name: product-form-upload-sweep
description: The general job's sweep of a product form's uploaded files in carts that never became an order: 30 days after the cart's last touch, what counts as a touch, how the pass walks, orders never swept
module: product_form_uploads
---

## When are a cart's uploaded files deleted?
- 30 days after the cart's last touch (`PG_PFU_SWEEP_DAYS`). Owner (2026-11-20): 30 days after the last touch, an order's files never (poll 162, channel message 10010). Built in #10638.
- Only a cart still `incomplete`; an order (`complete`, `exported`, `cancelled`) is never swept. An answer whose order row is gone is not swept either.

## What is the cart's last touch?
- The newest of `orders.order_date`, `orders.last_modified_timestamp` and the newest `files.timestamp` among the cart's answers (`pg_pfu_sweep_last_touch()`): a fresh upload keeps an old cart. A cart with no time is kept.
- The choice is pure: `pg_pfu_sweep_chosen()`, `pg_pfu_sweep_pick()` (`tests/product_form_uploads_test.php`).

## How does the pass run?
- `job.php` runs `pg_pfu_sweep_run(PG_PFU_SWEEP_BUDGET)` (5 s) when `pg_pfu_sweep_due()`: once a day, every tick while a pass is under way. Not tied to e-commerce being on.
- It walks the orders whose product answers hold a file (`pg_pfu_sweep_walk_sql()`, form_data's `order_id` index), 200 a chunk, reads their status and times (`pg_pfu_sweep_carts_sql()`), and keeps its place in `data/temp/product_form_upload_sweep.json` (`after_id`, `done_at`). A failed read ends the tick without marking the pass done.
- Files go through the one remove function, `pg_pfu_remove_files_where()`, with `pg_pfu_sweep_where()`: it reads the order's status and stamps again at the delete, so a cart that became an order meanwhile keeps its files. A file another answer holds stays.
- The swept answers stay with the cart, with `file_id = 0` and `data = ''`: a shopper who comes back is asked for a required file again.
- The activity log gets "general job removed N uploaded file(s) of M cart(s) left unordered for 30 days".

## What was not verified?
- Not run on a database: the statements are pinned by the unit tests only.

Related: commerce/product-form-uploads.md, commerce/product-form-upload-field.md, commerce/product-price-history-retention.md
