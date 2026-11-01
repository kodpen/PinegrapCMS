---
name: variant-price-rules
description: How variant prices are computed from attribute price rules (base, multiplier, delta), the fixed order and rounding, the top a rule price may reach, the single compute function and its JS preview twin.
---

## How are variant prices computed? (2026.4.6)
- When a set is built on the new-product screen, price can be derived from options. Per attribute: "Fiyatı belirler" (base), "Çarpan" (multiplier) or "Fark ekler" (+/- amount or +/- %).
- Fixed order, independent of card order: `base x multipliers + amounts`, then percentages are applied ONE BY ONE as multiplications (not summed), finally rounded to kuruş, below 0 becomes 0.
- The result is written to each variant's `products.price`. Cart / order / API / marketplace read only that column and do not know the rule.

## What happens when a rule takes a price past the column?
- A factor (up to 100000) or a percentage (up to 100000%) can take a base the column holds past `PG_PRODUCT_PRICE_MAX`. `pg_variant_price_compute()` compares the rounded float before the int cast and returns `'too_large' => true` (its `cents` is then the top, never written).
- `pg_catalog_variant_set_check()` prices every row through `pg_catalog_variant_set_priced()` and refuses the set with "The price rules give {SKU} a price that is too large.", so the product screen, `POST /product-groups/variant-sets` and the Workspace's `variant_set` change (proposal and apply) all stop before any write. A row typed over the rules (`price_manual`) is checked as a typed price.
- Before (#4717): the session is not strict, so MySQL stored 2147483647 and the price history recorded the cut value. Tests: `tests/catalog_variant_set_test.php`. The browser preview (`pgPriceRuleCompute()`) still shows the uncut figure and no warning.

## Where is the single computation?
- Only `pg_variant_price_compute()` (`includes/fn/ecommerce.php`). `pgPriceRuleCompute()` in `product_builder.js` is the preview twin: both must change together, same order and same rounding.
- The server recomputes every row that was not hand-written (`price_manual`).

## Where are rules stored and what can edit them?
- Stored in `product_groups.variant_price_rules`, but no screen reads them. No later bulk editing; a variant is edited from its own screen.
- Because the stored rule does not change, "hand-edited variant" = `price` differs from the computation with the stored rule.
- Detail and rationale (git history only): `git show 6930e15f:docs/degisiklikler.md`, section "2026.4.6 — Varyant fiyat kuralları".

Related: docs/memory/commerce/products-variant-model.md
