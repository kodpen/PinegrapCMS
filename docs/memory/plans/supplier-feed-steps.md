---
name: supplier-feed-steps
description: Plan supplier-feed, steps SF1 to SF7: tables and readers, the feed screen and mapping, the sync, drafts and variants, several suppliers and rates, cost link and API, close-out
---

## Which steps are there?
- SF1 (M) first: tables and readers.
  - The migration step adds the three tables (`supplier-feed.md`, Which tables?) with the idempotent helpers; the columns in the PR body, asked of the architect before ready.
  - New `includes/fn/supplier_feed.php`: the format told from the first bytes, the XML (XMLReader), CSV (`pg_csv_read_separator()`) and JSON readers with a resumable cursor, the field proposal, the price rule (pure). `tests/supplier_feed_test.php`, red first, on fixtures of common shapes: nested XML, a CSV with a BOM and `;`, JSON lists, Turkish decimal commas, rounding endings, the floor.
  - Features: F2 (proposal), F4.
- SF2 (M) after SF1: Products › Supplier feeds: the list, add and edit (F1) with the http box, the encrypted fields, the first pull through `api_http_request()` to a file under `data/temp/`, the mapping form (F2), the test pull (F3). Translations with `tools/tr_add.php`.
- SF3 (M) after SF2: the sync.
  - The job tick (`job.php`, budget from `pg_cron_time_left()`), one lock per feed, the cursor kept between ticks; matching, price through the product writer, stock through `api_inventory_apply()`, closing and reopening, the brakes and the `supplier_feed` operations alert, run rows.
  - Accept and Reject of a held price on the screen. Tests: a pull changes only what moved; a half-dropped pull writes nothing; a person's off stays off; a price held under half and over ten times. Features: F5, F6, F7, F12, F13.
- SF4 (M) after SF3: drafts: the product writer, pictures through the address check, groups by name, variant sets by model code through `pg_catalog_variant_set_create()`. Feature: F8.
- SF5 (S) after SF3: several suppliers on one barcode, the choice shown on the product; the rate hook after Update Exchange Rates. Features: F9, F10.
- SF6 (S) after SF3: the cost link of F11 (`includes/erp/order_profit.php`, the stock plan's supplier), the API routes in `includes/api/resources/`, their types, `php tools/check_api_schema.php`. Features: F11, F14.
- SF7 (S) close-out: every F number checked against the code with its PR; `commerce/supplier-feed.md` written; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After SF3: SF4, SF5 and SF6 side by side; SF5 and SF6 each touch the product screen's feed box only through SF3's helper.
- `includes/erp/order_profit.php` is also entered by the order-profit plan: SF6 does not start while its NP6 is open (architect, message 10643).
