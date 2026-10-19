---
name: price-history-reference-price
description: Plan (approved 2026-10-19, in progress: history table and writers done): automatic "price before discount" (TR 10/30-day, EU 30-day rules), merchant compare-at price, API endpoint, retention
---

## Why?
- The storefront shows a crossed-out price only while an offer applies (`__original_price` / `__has_discount` in `includes/fn/widgets_catalog.php:1312-1314`); there was no price history and no merchant-set reference price. Turkish price-label rules require the lowest price of the previous 30 days as the discount baseline (since 1 Mar 2022) and, from 1 Aug 2026, discount advertising must show the lowest price of the previous 10 days per sales channel. The EU Omnibus rule is 30 days. Competitors show a reference price on every discounted product.

## What is done?
- Step 1 and step 3 (2026.11.0, 11.0.2): `product_price_history`, `products.compare_at_price`, `initial` rows on upgrade, `pg_price_history_record()` hooked into every `products.price` writer, tests. Details and the writer list: docs/memory/commerce/product-price-history.md.

## What is decided?
- Reference price rule is a setting: `lowest_30d` (default for TR and EU), `lowest_10d`, or `manual`. The catalog widgets print `__reference_price` and `__reference_label` ("lowest price in the last 30 days" style text through `lang()`); the legacy catalog pages get the same value through `get_catalog.php` helpers without layout changes.
- The lowest price of a window is read from the history rows inside it plus the newest row before it. While the window is not yet covered by history (the `initial` row is younger than the window), only the current price is known and the label says so ("current price"), never a fabricated lowest.
- Merchant compare-at price `products.compare_at_price` (nullable) for "was" prices on non-offer items; shown only when higher than the current price and allowed by the rule.
- Offers keep their current discount display; the reference price is shown beside it when the rule demands.
- API: `GET /products/{id}/price-history?since=` and `compare_at_price` on product read/write; MCP inherits it.
- Retention: 13 months, trimmed by the general job, always keeping the newest row of each product and channel (the window start needs it). Add a `changed_at` index in the same step if the trim needs it.

## What remains?
1. Offer prices into the history (blocker for step 2): a past offer price inside the window is a price that applied, and without it the "lowest price" shown beside a later discount is too high, which is the non-compliance this plan exists to prevent. Record the effective price of the offers that change a product's own price (source `offer`) at their start and end, or compute the window from the history plus the offers' own dates; decide which first (M).
2. Pure function for the reference price of a window (+ tests), setting card in Settings › Ecommerce, widget tokens (`tools/check_bindings.php`), legacy catalog print (S).
3. compare_at_price in the product screen, list edit, import/export, the API presenter, and the copy paths (`duplicate_product()`, `duplicate_products.php` list their columns) (S).
4. API route `price-history` + schema (S).
5. Retention trim in the general job (S).
6. When it ships: one `[NEW]` changelog line.

## Open questions?
- Per-channel rule for POS: POS shows gross/net already; the reference price is informational only there.

## Where to start?
- Remaining step 1 (offers, `includes/fn/ecommerce.php` offer rules); read docs/memory/commerce/product-price-history.md and `includes/fn/price_history.php`.
