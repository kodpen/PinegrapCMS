---
name: price-history-reference-price
description: Plan (approved 2026-10-19, in progress: history, writers, API read, compare-at price, offer rows, retention, window function done): automatic "price before discount" (TR 10/30-day, EU 30-day rules)
---

## Why?
- The storefront shows a crossed-out price only while an offer applies (`__original_price` / `__has_discount` in `includes/fn/widgets_catalog.php`); there was no price history and no merchant reference price. Turkish price-label rules require the lowest price of the previous 30 days as the discount baseline and, from 1 Aug 2026, discount advertising must show the lowest of the previous 10 days per sales channel. EU Omnibus: 30 days.

## What is done?
- History (2026.11.0, 11.0.2): `product_price_history`, `products.compare_at_price`, `initial` rows, `pg_price_history_record()` in every writer (commerce/product-price-history.md).
- API read `GET /products/{id}/price-history` + MCP (commerce/product-price-history-api.md).
- Step 3 (PR #658): `compare_at_price` in panel, files, API, copies (commerce/product-compare-at-price.md).
- Step 1 (PR #681): offer rows, hourly (commerce/product-price-history-offers.md).
- Step 4 (PR #1425): 13-month retention (commerce/product-price-history-retention.md).
- Step 2a (PR #PRNUM): `pg_price_history_reference()` and batched `pg_price_history_window_sql()`, no caller; window end is a parameter (commerce/product-reference-price.md).

## What is decided?
- Rule is a setting: `lowest_30d` (default for TR and EU), `lowest_10d`, or `manual`. Catalog widgets print `__reference_price` and `__reference_label` (text through `lang()`); legacy catalog pages get the same through `get_catalog.php` helpers without layout changes.
- The lowest of a window is read from the rows inside it plus the newest row before it, all sources (offer prices are recorded, not computed). While the `initial` row is younger than the window the label says "current price", never a fabricated lowest.
- `compare_at_price` is shown only when higher than the current price and only under `manual`.
- Offers keep their discount display; the reference price is shown beside it when the rule demands.

## What remains?
- 2b. Widget tokens `__reference_price` / `__reference_label` (`tools/check_bindings.php`) and the legacy catalog print through `get_catalog.php`, one window query per page of products. Waits for the window-end answer below.
- 2c. Rule setting card in Settings › Ecommerce: needs a `config` column (TEXT, data/config-table-no-varchar.md), a schema change: ask the owner first.
- When it ships: one `[NEW]` changelog line.

## Open questions?
- POS: the reference price is informational only there (it shows gross/net already).
- Window end while an offer runs. Recommendation: the window ends where the current reduced price began (the `changed_at` of the row that started it), else now. The TR price label rule and EU Omnibus art. 6a both name the lowest price of the period before the reduction; a window ending now holds the reduced price itself, so the advertised discount would be its own reference and show nothing. 2a takes the end as a parameter; 2b needs the answer.

## Where to start?
- 2b once the window question is answered: per page call `pg_price_history_window_sql()`, group rows by product, call `pg_price_history_reference()`; read commerce/product-reference-price.md.
