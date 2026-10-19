---
name: api-text-field-limits
description: External API text limits must fit their varchar column (the non-strict session cuts in silence): product and group limits, and the test against the setup SQL
---

## Why does max_length have to fit the column?
- The product pins a non-strict session (`sql_mode` without `STRICT_*`), so MySQL cuts a value longer than a `varchar(N)` column to N characters without an error. If the route declares a wider `max_length`, the router (`includes/api/router.php`, `mb_strlen()`) lets the value through, the client gets 200, and the record holds a shortened text.
- The limit is in characters, as `varchar(N)` in utf8mb4 is.

## What did the product and group routes accept before, and what now?
- Before the fix (issue #513), `products.create`/`products.update` took `name` 255 and `short_description` 2000 (columns `varchar(100)`), `meta_description` and `keywords` 500 (255), `brand` 190 (100), `gtin` and `mpn` 100 (50); `product_groups.update` took `short_description` 2000 (100) and both group routes `keywords` 2000 (255).
- Now every one of them is the column's width, the same limits the Workspace catalogue (`ws_change_types()` in `includes/workspace/changes.php`) already used. A longer value gets 422 `validation_failed` naming the field and the limit.
- The product screen still offers 255 characters in its short description box (`product_builder.php`); the column cuts that one too.

## What keeps them in line?
- `tests/api_text_bounds_test.php` reads the `products` and `product_groups` tables from `data/backups/english_default/sql.sql` (and checks `turkish_default` has the same widths), then asserts every string body parameter of the four routes that names a `varchar` column declares `max_length` no wider than the column.
- `tools/check_api_schema.php` does not compare limits with columns. A new write route on another table follows the same rule; extend the test's route list when it writes a `varchar` column.
- Widening a column instead is a schema step (owner's call); the limit then moves with it in the same change.
