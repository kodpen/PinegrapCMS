---
name: product-screen-units-and-commission
description: Product screen weight and dimensions (lb/in stored, metric keeps four places), the commission rate limit (decimal(5,2), #2056), stored "no limit" zeros shown blank, boxes sized to their columns
---

## How are weight and dimensions stored and typed?
- `products.weight`, `length`, `width`, `height` are `decimal(12,4) unsigned`, in pounds and inches. The "Convert to metric system" switch lets the operator type kilograms and centimetres; `pg_pb_common_from_post()` (`product_builder.php`) converts on save with the legacy factors and rounds to four places, the columns' precision. Rounded to two, 0.11 kg was stored as 0.24 lb and came back as 0.1089 kg on the next open (#2056, PR #2618).
- Both separators are read: `pg_pb_decimal_from_input()` takes `0,125` and `0.125`.
- The unit labels beside the fields are `lang('lbs')` and `lang('in')` (Turkish `lb`, `inç`). The switch's script restores them from the label text it read at load, so it needs no literal of its own; `kg` and `cm` are the same in every language.

## How is the commission rate limit read?
- `pg_pb_commission_rate_from_input()` (`product_builder.php`): the typed text through `pg_pb_decimal_from_input()`, rounded to two places and held to 999.99, what `decimal(5,2) unsigned` holds; blank is `0.00`, which `submit_order.php` reads as no limit. Before, the raw text was written and the non-strict session turned `7,5` into 7.00.
- The box is `inputmode="decimal"` with `maxlength="6"` (999.99); the old `maxlength="3"` was too short for a two-place rate.

## Why does a stored 0 show as an empty box?
- Number of Payments, Access duration (`private_days`), Min./Max. Quantity and Commission Rate Limit say "leave blank for no limit / no expiration"; the save stores the blank as 0 and the readers take 0 as unlimited. `pg_pb_load_product()` runs `pg_pb_blank_unlimited_zeros()`, which turns a stored 0 / `0.00` / NULL into `''`, so an edited product shows them blank like a new one. Shown as `0` or `0.00`, the boxes read as "none allowed" or "expires at once".
- The Mailchimp card (`includes/settings/contact.php`) prints Historical Sync and Limit Sync 0 (all orders, no LIMIT) blank the same way; the save (`pg_parse_whole_number()`) turns blank back into 0.

## How does the metric switch convert in the browser?
- The switch's script (`backend.src.js`, `pgProductFigure()` and `pgProductRound()`) reads the box with either separator and rounds the converted figure to four places. Before, it multiplied the text as typed: `12,5` became `NaN` and 0.25 lb came back as `0.25000000000013883`.

## How big may the product screen's boxes be?
- Each box takes what its column holds, because the non-strict session cuts longer text and truncates decimals without a word: Product ID / SKU and Short Description `maxlength="100"` (also the variant matrix cells in `product_builder.js`), the gift card and recurring-profile-disabled e-mail subjects `maxlength="255"` (they were 100, refusing valid subjects), Primary/Secondary Weight Points `step="1"` (`int(10) unsigned`; `step="0.01"` let 1.25 through to be stored as 1). The product group form: Name 100 and `required` in edit mode too (`edit_product_group.php` refuses it blank), Title 255.
- `pg_pb_validate_product_post()` refuses a code over 100 characters before the uniqueness check: two long codes sharing their first 100 characters both passed it and were stored as one code.
- Product and group Delete buttons carry `formnovalidate`, so a blank required box does not block them.

Tests: `tests/product_screen_units_test.php`, `tests/product_box_limits_test.php`, `tests/product_zero_blank_test.php`.
