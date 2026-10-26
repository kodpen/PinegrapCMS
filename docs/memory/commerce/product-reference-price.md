---
name: product-reference-price
description: pg_price_history_reference() and pg_price_history_window_sql() (PR #PRNUM): the "price before the discount" of a window from product_price_history, rules, coverage, labels; no caller yet
---

## What gives the reference price?
- `pg_price_history_reference($rows, $current, $rule, $window_end, $compare_at)` (`includes/fn/price_history.php`), pure. Returns `price` (int minor units or null), `kind` and `days`.
- Kinds: `lowest` (lowest of `days` days), `current` (window not covered: the current price, labelled as such), `manual` (compare-at price), `none` (nothing to show: unknown rule, manual without a compare-at price above the current one).
- Rules (`pg_price_history_reference_rules()`): `lowest_30d` (TR price label rule, EU Omnibus), `lowest_10d` (TR discount advertising from 2026-08-01), `manual`. The setting that picks one does not exist yet (plan step 2c, a `config` column).

## How is the window read?
- `pg_price_history_window_sql($ids, $channel, $start, $end)`: one query per page of products, never per product. It returns rows after `$start` and before `$end`, plus the rows of the newest second at or before `$start` (ties all come back), ordered product, changed_at, id. Every source counts: an offer price that applied is a price.
- `$start` is `pg_price_history_window_start($rule, $end)` (days × 86400).
- The price at the start is the newest row at or before it (changed_at, then id). A row stamped exactly at the end is left out: the end is where the price the reference stands beside began.
- Prices go through `pg_price_history_minor_units()`: `round()`, never `(int)` truncation; a row without a numeric price is skipped.

## When is the window covered?
- When a row is at or before the start, or when the oldest row is not `initial`: a product added after the upgrade has its whole life in the history, so the lowest of its rows is honest.
- When the oldest row is an `initial` row younger than the window, the earlier price is unknown: kind `current`, never a lowest read from part of the window. Retention (product-price-history-retention.md) keeps the row before its cutoff, so trimming never uncovers a window.

## Where does the window end?
- The caller passes it. Open question in docs/memory/plans/price-history-reference-price.md (recommended: where the current reduced price began, so an offer is not its own reference).

## How was it verified?
- `tests/price_history_test.php` (lowest, edges, coverage, new product, same second, rounding, manual, query). No database run: nothing calls it yet.

Related: docs/memory/commerce/product-price-history.md, docs/memory/commerce/product-compare-at-price.md, docs/memory/commerce/product-price-history-offers.md
