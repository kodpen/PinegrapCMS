---
name: decisions-made-schema-34
description: Decisions list, schema part 34 (2026-11-20 on): the stock_holds table of plan stock-holds, units held for an order while its payment runs
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-33.md`.

## May holding units during payment get a table? (owner, 2026-11-20, agent channel poll 172, message 10457)
- Yes. The question named it ("bir tablo ve ödeme akışına dokunan ayrı bir plan ister") and he chose "Uyarı şimdi, adet ayırma plana girsin". The plan: `plans/stock-holds.md`.

## Which columns does `stock_holds` have? (architect, 2026-11-20, plan stock-holds)
- `id` BIGINT UNSIGNED AUTO_INCREMENT; `order_id`, `product_id`, `location_id` INT UNSIGNED (location 0 on a site without stock locations); `quantity` INT UNSIGNED; `expires_at` and `created_at` INT UNSIGNED, Unix time as the order tables keep it.
- Keys: unique (`order_id`, `product_id`, `location_id`), so placing again replaces a row; (`product_id`, `location_id`, `expires_at`) for the read of a product's live holds; `expires_at` for the sweep.
- A row is a claim for minutes, not a stock figure: `products.inventory_quantity` and `product_stock` are never written by a hold, and no column is added to `products`, `orders` or `order_items`.
- Rows go with the order's completion, a failed payment, the order's or product's deletion, and a day after they ran out. The table holds no personal data.
- SH1's worker writes the columns into its PR body and asks the architect before it is marked ready.
