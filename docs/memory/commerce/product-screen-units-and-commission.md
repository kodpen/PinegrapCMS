---
name: product-screen-units-and-commission
description: Product screen weight and dimensions (lb/in stored, metric typed keeps four places, unit labels through lang()) and the commission rate limit read like a dimension and held to decimal(5,2) (#2056)
---

## How are weight and dimensions stored and typed?
- `products.weight`, `length`, `width`, `height` are `decimal(12,4) unsigned`, in pounds and inches. The "Convert to metric system" switch lets the operator type kilograms and centimetres; `pg_pb_common_from_post()` (`product_builder.php`) converts on save with the legacy factors and rounds to four places, the columns' precision. Rounded to two, 0.11 kg was stored as 0.24 lb and came back as 0.1089 kg on the next open (#2056, PR #2618).
- Both separators are read: `pg_pb_decimal_from_input()` takes `0,125` and `0.125`.
- The unit labels beside the fields are `lang('lbs')` and `lang('in')` (Turkish `lb`, `inç`). The switch's script restores them from the label text it read at load, so it needs no literal of its own; `kg` and `cm` are the same in every language.

## How is the commission rate limit read?
- `pg_pb_commission_rate_from_input()` (`product_builder.php`): the typed text through `pg_pb_decimal_from_input()`, rounded to two places and held to 999.99, what `decimal(5,2) unsigned` holds; blank is `0.00`, which `submit_order.php` reads as no limit. Before, the raw text was written and the non-strict session turned `7,5` into 7.00.
- The box is `inputmode="decimal"` with `maxlength="6"` (999.99); the old `maxlength="3"` could not hold even the stored `0.00`.

## How does the metric switch convert in the browser?
- The switch's script (`backend.src.js`, `pgProductFigure()` and `pgProductRound()`) reads the box with either separator and rounds the converted figure to four places. Before, it multiplied the text as typed: `12,5` became `NaN` and 0.25 lb came back as `0.25000000000013883`.

Test: `tests/product_screen_units_test.php`.
