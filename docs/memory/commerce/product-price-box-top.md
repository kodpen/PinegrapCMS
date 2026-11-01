---
name: product-price-box-top
description: The product screen's unit price, compare-at price and extra shipping cost boxes hold 13 characters (21.474.836,47) and the save refuses a larger amount (#4444)
---

## How large an amount do the product screen's money boxes take?
- Unit price, compare-at price and extra shipping cost (`product_builder.php`) are masked boxes with `maxlength="13"`: the mask counts its grouping and decimals, so `21.474.836,47` (the INT column's top, 2147483647 minor units) fits.
- `pg_pb_validate_product_post()` refuses a unit price or extra shipping cost above that top with "{var:1} is too large." before anything is written, through `pg_pb_price_above_column_top()` (float checked before the int cast; more than 15 digits is always too large). The compare-at price already had its own check (`product-compare-at-price.md`).

## What did it fix? (#4444)
- The boxes had `maxlength="12"`, so the mask stopped at 9.999.999,99: typing `25000000` kept `2.500.000,00` and saved the product ten times cheaper without a word. The server checked only the compare-at price, so a wider box alone would have let MySQL cut a larger unit price to the column top.

## Which test covers it?
- `tests/product_price_box_top_test.php` (the reader's bounds, source pins of the boxes and the save check). Not typed in a browser.
