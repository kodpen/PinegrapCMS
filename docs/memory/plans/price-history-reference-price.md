---
name: price-history-reference-price
description: Plan (approved 2026-10-19, in progress: history, writers, API read, compare-at price, offer rows done): automatic "price before discount" (TR 10/30-day, EU 30-day rules), retention
---

## Why?
- The storefront shows a crossed-out price only while an offer applies (`__original_price` / `__has_discount` in `includes/fn/widgets_catalog.php:1312-1314`); there was no price history and no merchant-set reference price. Turkish price-label rules require the lowest price of the previous 30 days as the discount baseline (since 1 Mar 2022) and, from 1 Aug 2026, discount advertising must show the lowest price of the previous 10 days per sales channel. The EU Omnibus rule is 30 days. Competitors show a reference price on every discounted product.

## What is done?
- History (2026.11.0, 11.0.2): `product_price_history`, `products.compare_at_price`, `initial` rows on upgrade, `pg_price_history_record()` in every `products.price` writer. docs/memory/commerce/product-price-history.md.
- API read: `GET /products/{id}/price-history` (`since`, `channel`, cursor paging) on `pg_price_history_list_sql()`, with its MCP tool. docs/memory/commerce/product-price-history-api.md.
- Step 3 (PR #658): `compare_at_price` on the product screen, the product list, import/export, the API and the copy paths; the storefront does not read it yet (step 2). docs/memory/commerce/product-compare-at-price.md.
- Step 1 (PR #681): offer rows, the price the open offers give a visitor with no code or account, recorded hourly by the general job (source `offer`). docs/memory/commerce/product-price-history-offers.md.

## What is decided?
- Reference price rule is a setting: `lowest_30d` (default for TR and EU), `lowest_10d`, or `manual`. The catalog widgets print `__reference_price` and `__reference_label` ("lowest price in the last 30 days" style text through `lang()`); the legacy catalog pages get the same value through `get_catalog.php` helpers without layout changes.
- The lowest price of a window is read from the history rows inside it plus the newest row before it. While the window is not yet covered by history (the `initial` row is younger than the window), only the current price is known and the label says so ("current price"), never a fabricated lowest.
- Merchant compare-at price `products.compare_at_price` (nullable) for "was" prices on non-offer items; shown only when higher than the current price and allowed by the rule.
- Offers keep their current discount display; the reference price is shown beside it when the rule demands.
- Offer prices are recorded, not computed from the offers: offers are edited and deleted afterwards. The window reads rows of every source.
- API: `GET /products/{id}/price-history?since=` (built) and `compare_at_price` on product read/write; MCP inherits it.
- Retention: 13 months, trimmed by the general job, always keeping the newest row of each product and channel (the window start needs it). Add a `changed_at` index in the same step if the trim needs it.

## What remains?
1. Done (PR #681): offer prices into the history (see above).
2. Pure function for the reference price of a window (+ tests), setting card in Settings › Ecommerce, widget tokens (`tools/check_bindings.php`), legacy catalog print (S).
3. Done (PR #658): compare_at_price in the panel, files, API and copies (see above).
4. Retention trim in the general job (S).
5. When it ships: one `[NEW]` changelog line.

## Open questions?
- Per-channel rule for POS: POS shows gross/net already; the reference price is informational only there.
- While an offer runs, its own rows are inside the window: should the window end where the current discounted price began, so the advertised discount is not its own reference? Settle in step 2.

## Where to start?
- Step 2: the pure window function over the rows `pg_price_history_list_sql()` reads; read docs/memory/commerce/product-price-history.md, product-price-history-offers.md and `includes/fn/price_history.php`.
