---
name: price-history-reference-price
description: Plan (approved 2026-10-19, not started): product price history table, automatic "price before discount" (TR 10/30-day, EU 30-day rules), merchant compare-at price, API endpoint
---

## Why?
- The storefront shows a crossed-out price only while an offer applies (`__original_price` / `__has_discount` in `includes/fn/widgets_catalog.php:1312-1314`); there is no price history and no merchant-set reference price. Turkish price-label rules require the lowest price of the previous 30 days as the discount baseline (since 1 Mar 2022) and, from 1 Aug 2026, discount advertising must show the lowest price of the previous 10 days per sales channel. The EU Omnibus rule is 30 days. Competitors show a reference price on every discounted product.

## What is decided?
- New table `product_price_history` (product_id, channel `web|pos|marketplace:<code>`, price minor units, currency, changed_at, source `panel|import|api|offer|erp`), one row per effective price change; written by one helper `pg_price_history_record()` called from every price writer (product form, inline list edit, mass edit, import, API bulk prices, variant price rules, ERP price lists when a web price changes). Prices stay integer minor units.
- Reference price rule is a setting: `lowest_30d` (default for TR and EU), `lowest_10d`, or `manual`. The catalog widgets print `__reference_price` and `__reference_label` ("lowest price in the last 30 days" style text through `lang()`); the legacy catalog pages get the same value through `get_catalog.php` helpers without layout changes.
- Merchant compare-at field `products.compare_at_price` (nullable) for "was" prices on non-offer items; shown only when higher than the current price and allowed by the rule.
- Offers keep their current discount display; the reference price is shown beside it when the rule demands.
- API: `GET /products/{id}/price-history?since=` and `compare_at_price` on product read/write; MCP inherits it.
- Retention: 13 months, trimmed by the general job.

## What remains?
1. Migration step (table + column), helper + unit test (`tests/price_history_test.php`), writers hooked (S–M).
2. Setting card in Settings › Ecommerce, widget tokens (`tools/check_bindings.php`), legacy catalog print (S).
3. Backfill on upgrade: one row per product with today's price so the first 30 days are honest (shown only as "current" until the window fills) (S).
4. API route + schema (S).

## Open questions?
- Per-channel rule for POS: POS shows gross/net already; the reference price is informational only there.

## Where to start?
- Step 1; read `docs/memory/commerce/products-variant-model.md` and `variant-price-rules.md` for every price writer.
