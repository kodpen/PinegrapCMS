---
name: product-bulk-price-edit
description: The bulk product edit's price change (edit_products.php, pg_bulk_price_change()): four methods, a change that lowers a price never takes it to zero or below, why a negative price must not be stored
---

## How does a bulk edit change a price?
- Products › select › Edit, "Change price": `1` decrease by an amount, `2` increase by an amount (typed in the currency, read into minor units), `3` decrease by a percentage, `4` increase by a percentage.
- `edit_products.php` asks `pg_bulk_price_change($price, $method, $value)` (`includes/fn/ecommerce.php`) for every selected product: `['price' => new or old, 'refused' => bool]`. A refused product keeps its price and is counted in "{var:1} product(s) price cant be decerease. Product price equal or smaller than 0!".
- A change that lowers the price is refused when the result is zero or below. The decrease methods always refused such a result; an increase by a negative value (−5.00, −150%) is a decrease too (#1296). A change that does not lower the price keeps its result: a free product raised by a percentage stays free; a price already below zero may go up, not down.
- Percentages round as before (`round((100 ± v) / 100 x price)`), so every change that was allowed gives the same price.

## Why must no writer store a negative price?
- A negative product becomes a negative order line. Paraşüt refuses such a line outright (live, 2026-10-23): unit price, VAT and discount must be zero or more. The checkout taxes it alone while the ERP groups it, and an ERP return of it refunds too much (erp/erp-negative-price-lines.md).
- The other writers refuse or strip a minus sign: the product form (`pg_pb_price_to_cents()`), the API ("price cannot be negative"), variant price rules (floor at 0), the CSV import (since #1262, product-import-prices.md). Until #1296 the increase methods here were the one way left; prices stored before stay as they are.

## Tests
- `tests/bulk_price_change_test.php`. The screen itself was not run on a live instance.
