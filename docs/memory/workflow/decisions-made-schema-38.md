---
name: decisions-made-schema-38
description: Decisions list, schema part 38 (2026-11-20 on): the three tables of the supplier feed: supplier_feeds, supplier_feed_items, supplier_feed_runs; no column on products
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-37.md`.

## Does the supplier feed get tables? (owner, 2026-11-20, agent channel poll 185, channel message 10642)
- "Evet, üç tablo eklensin": `supplier_feeds`, `supplier_feed_items`, `supplier_feed_runs`. No column is added to `products`.
- Not chosen: two tables with no run log (only the last run's summary on the feed's row), no tables.
- The plan is the owner's yes to poll 168 (channel message 10315).

## Which columns does supplier_feeds have? (architect, 2026-11-20)
- `id` INT UNSIGNED AUTO_INCREMENT; `name` VARCHAR(150); `host` VARCHAR(255), the only part of the address shown; `secret` TEXT NULL, one encrypted blob with the address, user, password, header name and key.
- `allow_http` TINYINT; `format` VARCHAR(10), '' for automatic; `interval_hours` TINYINT UNSIGNED; `currency` VARCHAR(3); `vat_included` TINYINT; `erp_account_id` INT UNSIGNED.
- `mapping` TEXT NULL and `rules` TEXT NULL (JSON: the record path and fields; the price rule, its category and brand rules, rounding, floor); `state` TEXT NULL, the running pull's cursor, NULL when none runs.
- `status` TINYINT (0 off, 1 on, 2 paused by a brake); `pause_reason` VARCHAR(500); `next_run_at`, `last_run_at`, `created_at`, `updated_at` INT UNSIGNED; `created_by` INT UNSIGNED.
- Keys: (`status`, `next_run_at`).

## Which columns does supplier_feed_items have? (architect, 2026-11-20)
- One row per feed and supplier record. `id` BIGINT UNSIGNED AUTO_INCREMENT; `feed_id` INT UNSIGNED; `supplier_key` VARCHAR(100); `product_id` INT UNSIGNED, 0 while unmatched; `barcode` VARCHAR(50).
- `buy_price` INT UNSIGNED, minor units of `currency` VARCHAR(3), net of VAT; `held_price` INT UNSIGNED, a buy price waiting for a person's yes, 0 for none; `stock` INT.
- `follow_price` TINYINT default 1 ("Price from the feed"); `closed_by_feed` TINYINT; `active` TINYINT, the row a product follows when several feeds carry it; `dismissed` TINYINT, a record whose product a person deleted, so no draft is made again.
- `seen_at`, `changed_at`, `created_at` INT UNSIGNED.
- Keys: unique (`feed_id`, `supplier_key`); `product_id`; `barcode`; (`feed_id`, `seen_at`).

## Which columns does supplier_feed_runs have? (architect, 2026-11-20)
- `id` BIGINT UNSIGNED AUTO_INCREMENT; `feed_id` INT UNSIGNED; `kind` TINYINT (0 scheduled, 1 by hand or the API); `status` TINYINT (0 running, 1 done, 2 stopped by a brake, 3 failed); `started_at`, `ended_at` INT UNSIGNED.
- `records`, `added`, `changed`, `closed`, `reopened`, `held`, `failed` INT UNSIGNED; `detail` MEDIUMTEXT NULL, the failed lines with their reason and a brake's reason.
- Keys: (`feed_id`, `started_at`); `started_at`. Rows older than 90 days are swept. A test pull writes no row.

## What else holds?
- Every column NOT NULL with its default but the TEXT ones. No personal data is in any of the three.
- The worker of the plan's first step writes the columns into its PR body and asks the architect before it is marked ready.
