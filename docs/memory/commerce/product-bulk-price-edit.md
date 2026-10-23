---
name: product-bulk-price-edit
description: Bulk price change (edit_products.php, pg_bulk_price_change(), and the Catalog Manager's): a value above zero, a decrease never ends at or below zero, an increase never lowers; no negative price
---

## How does a bulk edit change a price?
- Products › select › Edit, "Change price": `1` decrease by an amount, `2` increase by an amount (typed in the currency, read into minor units), `3` decrease by a percentage, `4` increase by a percentage.
- The value must be a number above zero, as in the Catalog Manager's bulk price change: an empty, non-numeric, zero or negative value gives "Enter an amount for the price change." (`add_error()`, so a tax rate error beside it does not replace it) and no price is touched (#1296). Before, an increase by −150% stored −5.00 for 10.00, and an empty value stopped the request with a TypeError.
- Each product's price is `pg_bulk_price_change($price, $method, $value)` (`includes/fn/ecommerce.php`): `['price' => new or old, 'refused' => bool]`. The decrease methods refuse any result at or below zero, a free or negative price included; the increase methods refuse one only when the change lowers the price. A refused product keeps its price and is counted in "{var:1} product(s) price cant be decerease. Product price equal or smaller than 0!".
- Percentages round as before (`round((100 ± v) / 100 x price)`).

## And the Catalog Manager's bulk price change?
- `view_folder_and_files_f.php` (`price_method` increase, decrease, increase_percent, decrease_percent): it refuses a value that is empty, non-numeric or at or below zero up front and skips every product whose result is at or below zero. Both screens now take the same values; on a price already below zero the Catalog Manager skips an increase that the bulk edit writes.

## Why must no writer store a negative price?
- A negative product becomes a negative order line. Paraşüt refuses such a line outright (live, 2026-10-23): unit price, VAT and discount must be zero or more. The checkout taxes it alone while the ERP groups it, and an ERP return of it refunds too much (erp/erp-negative-price-lines.md).
- The other writers refuse or strip a minus sign: the product form (`pg_pb_price_to_cents()`), the API ("price cannot be negative"), variant price rules (floor at 0), the CSV import (since #1262, product-import-prices.md). Prices stored before stay as they are.

## Which tests cover it?
- `tests/bulk_price_change_test.php` (the helper, and the screen's refusal of a value that is no amount). The screen was not submitted on a live instance.
