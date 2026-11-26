---
name: supplier-feed-price-rule
description: Supplier feed amounts (Turkish and English separators, stock text, net of VAT) and the F4 price rule: margin, fixed, rate, ,90 or ,99 endings, floor, category and brand rules
module: supplier_feed
---

## How is an amount in a feed read?
- `pg_supplier_feed_parse_amount($text, $decimals = 2)`: minor units, rounded with `round()`. A currency sign or code around the number is left aside.
- With both `.` and `,`, the last is the decimal mark (`1.234,50`, `1,234.56`). One kind used more than once is a thousands mark (`1.234.567`). Used once before exactly three digits it is a thousands mark (`1.234` is 1234), unless the part before it is `0` (`0.125`). `null` for no number or a negative one.
- A buy price of 0 is no buy price (`pg_supplier_feed_record_values()`): a "No buy price" failed line, the last one kept (#11784).
- `pg_supplier_feed_parse_stock()`: `10+` is 10, `5,00` is 5, `1.000` is 1000, never below 0, text is 0.
- `pg_supplier_feed_net_of_vat($amount, $vat_percent)` for a feed whose prices include VAT.

## How is the selling price worked out?
- `pg_supplier_feed_price($buy, $rule, $rate, $home_decimals = 2, $feed_decimals = 2)`, pure: (buy net of VAT × (1 + margin %) + fixed) × rate. `fixed` is in minor units of the feed's currency; `rate` is home currency per unit of the feed's currency (1 for the same). 0 when there is no buy price or rate.
- Rounding: `none`, `whole` (nearest whole unit, half up), `90` / `99` (nearest price ending in ,90 or ,99, a tie goes up, never 0 or below). A currency without two decimals rounds `90` and `99` as whole.
- Floor: buy × (1 + `floor_margin` %) at the same rate. A rounded price under it becomes the first price at or above the floor that has the rounding's ending. `pg_supplier_feed_price_parts()` also says whether the floor lifted it (the test pull's "Floor" pill).

## Which rule applies?
- `pg_supplier_feed_rule_pick($rules, $category, $brand)` on the feed's `rules` JSON: `margin`, `fixed`, `rounding`, `floor_margin`, `specific` (a list of rules with `category` and/or `brand`).
- A specific rule naming both first, then one naming the category, then one naming the brand (names compared without regard to case, Turkish İ/I included); else the feed's own. A specific rule takes from the feed's own what it does not set.

Related: `commerce/supplier-feed-readers.md`.
