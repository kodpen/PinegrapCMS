---
name: product-price-history-retention
description: product_price_history keeps 13 months (396 days, PR #1425): the general job's daily trim pg_price_history_trim_run(), which old rows stay and why, how the pass walks, verification
---

## How long are rows kept?
- 396 days (`PG_PRICE_HISTORY_KEEP_DAYS`, thirteen months rounded up), so the same month last year and the 30-day window before it stay readable. An older row goes unless it is one of the two below.

## Which old rows stay?
- In each product and channel, among the rows older than the cutoff:
  - the newest in read-back order (`changed_at`, then `id`): the price at the cutoff, where a window reaching back starts, and the price of a product unchanged for longer. Not `MAX(id)`: rows can be written out of time order, and readers go by time.
  - the newest writer row (`MAX(id)`, source not `offer`): `pg_price_history_record()` compares the stored price with it; without it the next same-price save during an offer would write a row again.
- So a long history starts with one or two rows older than 13 months; the API route description says so.
- Younger rows are never touched. A writer or the offer pass running meanwhile compares with the same row as without the trim: new rows are stamped now, and the newest row of any source is young or the kept newest-before-cutoff.

## How does the pass run?
- `job.php` runs `pg_price_history_trim_run(PG_PRICE_HISTORY_TRIM_BUDGET)` (5 s) when `pg_price_history_trim_due()`: once a day, every tick while a pass is under way. Not tied to e-commerce being on: rows age either way.
- Products are walked from the history's own ids, 500 a chunk (`pg_price_history_trim_walk_sql()`). One grouped read per chunk keeps only products with two old rows or more in a channel (`pg_price_history_trim_candidates_sql()`), so a history younger than 13 months costs two reads per 500 products. For those: the keep query (`pg_price_history_trim_keep_sql()`: a join on `MAX(changed_at)` plus a `UNION` for the writer row; no window functions, MySQL 5.7) and `DELETE ... LIMIT 5000` repeated (`pg_price_history_trim_delete_sql()`, `''` without keep ids, so never a delete without them).
- Everything runs on the index `(product_id, channel, changed_at)`: no schema change, no new index.
- Marker `data/temp/price_history_trim.json` (`after_id`, `done_at`). A chunk left halfway (time up, failed statement) is walked again next tick; the kept rows are the same. A lost marker costs one more pass. No lock: two passes at once remove the same rows.
- Removed rows reach the activity log ("general job removed N product price history row(s) older than 396 days").

## How was it verified?
- `tests/price_history_test.php`: cutoff, marker, timing, the four statements.
- MariaDB 10.11 with the migration's table: writer rows, a lone old row, offer rows with an older writer row, two rows in one second, rows out of time order, two channels, 12,001 old offer rows (three deletes), young rows untouched, 1,200 one-row products over three chunks, a resumed marker, a rerun removing nothing, the recorder writing nothing for unchanged prices after the trim; 300,000 rows of one product with a 1 s budget went in three ticks. Not run through `job.php` on a running site.

Related: docs/memory/commerce/product-price-history.md, docs/memory/plans/price-history-reference-price.md
