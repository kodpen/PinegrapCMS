---
name: variant-price-rules
description: How variant prices are computed from attribute price rules (base, multiplier, delta), the fixed order and rounding, the single compute function and its JS preview twin.
---

## How are variant prices computed? (2026.4.6)
- When a set is built on the new-product screen, price can be derived from options. Per attribute: "Fiyatı belirler" (base), "Çarpan" (multiplier) or "Fark ekler" (+/- amount or +/- %).
- Fixed order, independent of card order: `base x multipliers + amounts`, then percentages are applied ONE BY ONE as multiplications (not summed), finally rounded to kuruş, below 0 becomes 0.
- The result is written to each variant's `products.price`. Cart / order / API / marketplace read only that column and do not know the rule.

## Where is the single computation?
- Only `pg_variant_price_compute()` (`includes/fn/ecommerce.php`). `pgPriceRuleCompute()` in `product_builder.js` is the preview twin: both must change together, same order and same rounding.
- The server recomputes every row that was not hand-written (`price_manual`).

## Where are rules stored and what can edit them?
- Stored in `product_groups.variant_price_rules`, but no screen reads them. No later bulk editing; a variant is edited from its own screen.
- Because the stored rule does not change, "hand-edited variant" = `price` differs from the computation with the stored rule.
- Detail and rationale: `docs/degisiklikler-arsiv/2026.4.6.md` "Varyant fiyat kuralları".

Related: docs/memory/commerce/products-variant-model.md
