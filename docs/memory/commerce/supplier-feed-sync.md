---
name: supplier-feed-sync
description: Supplier feed sync (SF3a, #10895): the job tick, one lock per feed, the phases over one file, matching, price and stock writers, closing and reopening, held prices, brakes, run rows
module: supplier_feed_sync
---

## Where does a pull run?
- `includes/fn/supplier_feed_sync.php`, loaded by `job.php`, the test pull and Pull now: `pg_supplier_feed_job_tick(pg_cron_time_left(20))` every tick when `supplier_feeds` exists and the store is on. Feeds with `status` 1 and a running pull (`state` set) or `next_run_at` due, running first.
- `pg_supplier_feed_pull($id, $deadline, $kind, $drop_ok)` under `GET_LOCK('pg_supplier_feed_<id>', 0)`; a locked feed is skipped this tick. `state` keeps the run id, phase, cursor and counts between ticks.
- The file: `data/temp/supplier_feed_<id>_pull.dat`, not the screen's (#11784). A missing file or a feed switched off mid-pull: run failed (3), state dropped, the next pull starts over.

## What are the phases?
- Scan: every record read once, nothing written to products; items seen get `seen_at` = the run's start.
- Apply: the records again. Unknown keys get an item row (product 0 while unmatched). Matched by the code to `products.name`, else `product_barcodes.barcode`. Only the `active` item writes; with several feeds: `supplier-feed-several.md`. Bundles and binned products skipped.
- Close: active matched items not seen in the pull are closed as if their stock were 0.
- Drafts: the records no product matched become products switched off (`supplier-feed-drafts.md`).

## What does one record write?
- `pg_supplier_feed_plan_item()` says; only what moved is written. Price: `api_products_set_price()` (audit, price history `supplier`, `product.updated`, marketplace queue). Stock: `api_inventory_apply(..., 'set', ..., 'supplier_feed', 'feed:<id>')`, tracked products only. Off and on: `api_products_set_enabled()`.
- The price follows only with a saved rule (`rules` with `margin`), a rate and `follow_price` 1; else only stock follows and the buy price is kept on the item.
- Buy price net of VAT when the feed's prices include it: the record's VAT field, else the product's own `tax_rate`, else as read; followed or not, held while matched (`pg_supplier_feed_item_buy()`, #11784). Rate: `1 / currencies.exchange_rate`; no rate, no price.
- Stock 0 closes: tracked gets stock 0 (page and back-in-stock notices stay), untracked is turned off; `closed_by_feed` 1. Stock back reopens only with `closed_by_feed` 1, so a person's off stays off; a tracked product's `enabled` is never touched.

## When is a buy price held?
- Under half or over ten times the item's last `buy_price` (`pg_supplier_feed_price_held()`, in `supplier_feed.php`): kept in `held_price`, the last buy price and the product's price stay, stock still follows, counted `held`. A later pull in range lets it go; Accept and Reject: the price card file.

## What are the brakes? (F13)
- A failed download, an unreadable file, no records, a mapped field no record of the first step has, or more than half the active matched products dropped (missing, or stock now 0 where the item had stock): nothing written to products.
- The feed gets `status` 2 and `pause_reason`, the run `status` 2 with the reason, the activity log a line, and the `supplier_feed` operations alert (once a day, subject `feed:<id>`).
- Saving the feed's Fields switches a paused feed on again (`edit_supplier_feed.php`). A mass drop a person wants: `$drop_ok`, Pull now on a paused feed.

## What does a run row hold?
- Counts `records`, `changed`, `closed`, `reopened`, `held`, `failed` (`added`: drafts made), written each step. `detail` is JSON: `reason` and up to 500 `failed` lines (`pg_supplier_feed_detail_add()`, `pg_supplier_feed_detail_reason()` for the screen's pill). Rows past 90 days swept each tick.

## Why does api_inventory_apply() run outside the API?
- Its queries use `pg_catalog_query()` (API helpers or `db()`); `pg_supplier_feed_writers()` loads it with `PG_API_PANEL`.

Related: `supplier-feed-screen.md`, `supplier-feed-price-card.md`, `supplier-feed-several.md`.
