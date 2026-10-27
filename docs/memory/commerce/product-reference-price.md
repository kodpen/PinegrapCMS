---
name: product-reference-price
description: Reference price ("price before the discount"): pg_price_history_reference(), the window query (PR #1803), the rule setting pg_price_reference_rule() (11.0.49), coverage, labels
---

## What gives the reference price?
- `pg_price_history_reference($rows, $current, $rule, $window_end, $compare_at)` (`includes/fn/price_history.php`), pure. Returns `price` (int minor units or null), `kind` and `days`.
- Kinds: `lowest` (lowest of `days` days), `current` (window not covered: the current price, labelled as such), `manual` (compare-at price), `none` (nothing to show: unknown rule, manual without a compare-at price above the current one).
- Rules (`pg_price_history_reference_rules()`): `lowest_30d` (TR price label rule, EU Omnibus), `lowest_10d` (TR discount advertising from 2026-08-01), `manual`.

## Which rule does a shop use?
- `pg_price_reference_rule()`: `config.ecommerce_reference_price_rule` (TEXT DEFAULT NULL, 2026.11.0 step 11.0.49), read once per request behind `pg_price_reference_rule_ready()`. Callers pass it to `pg_price_history_reference()`; no storefront caller yet (plan step 2b).
- `pg_price_reference_rule_from_input()` maps NULL (never saved, the column's state after the upgrade), empty and unknown values to `PG_PRICE_REFERENCE_RULE_DEFAULT` (`lowest_30d`), so the step writes no rows and the card always has one option checked.
- Edited in Settings › Ecommerce › Reference Price (`pgset-reference-price`, `includes/settings/commerce.php`), drawn and saved only once the column exists. TEXT, not ENUM or VARCHAR: no VARCHAR on `config` (data/config-table-no-varchar.md). The API exposes no shop settings, so there is no API field.

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
- `tests/price_history_test.php` (lowest, edges, coverage, new product, same second, rounding, manual, query, rule from input). No database run.

Related: docs/memory/commerce/product-price-history.md, docs/memory/commerce/product-compare-at-price.md, docs/memory/commerce/product-price-history-offers.md
