---
name: product-customs-fields
description: products.hs_code and origin_country (2026.11.0, 11.0.130, #3408): customs data per product, empty is the shop default; product screen, copy, import/export; API with C2
module: product_customs
---

## What are the two columns?
- `products.hs_code VARCHAR(16) NOT NULL DEFAULT ''`: the Harmonized System code, or a national tariff number (Turkey's GTİP: 12 digits with dots). Digits in groups split by single dots, at most 16 characters, trimmed (`pg_product_hs_code_parse()`).
- `products.origin_country CHAR(2) NOT NULL DEFAULT ''`: ISO 3166-1 alpha-2 of the country the goods were made in, upper case, from the whole ISO list (`pg_sales_map_iso_countries()`), not the shop's `countries` table: goods come from countries a shop never ships to (`pg_product_origin_country_parse()`).
- Empty means "use the shop default" of the label driver's customs block (C2/C3 of `plans/shipping-carrier-labels.md`). A line with no HS code anywhere refuses the label (#3408), never a guessed code.
- Step `upgrade_2026_11_0_product_customs()` (11.0.130), `install_add_column()` only; a fresh install runs it like every step. Readers ask `pg_product_customs_ready()` (exact-name `pg_schema_has()`) first.

## Where are they edited and carried?
- Product screen (`product_builder.php`): two fields in the Shippable panel after Extra Shipping Cost, a hint "Empty uses the shop default of the label service"; the country picker's first option is "Shop default", names in the panel language with the code. Saved in `pg_pb_common_from_post()` through `pg_product_customs_from_post()`, so every variant of a set takes them (a property of the goods, like weight). `pg_pb_validate_product_post()` refuses a bad value with `output_error()` before any write (`pg_product_customs_problem()`), rather than saving it empty.
- Copy: `duplicate_product()` and `duplicate_products.php` add `pg_product_customs_copy_sql($row)` beside the compare-at price; a stored value the screen would refuse is copied as ''.
- Export (`export_products_f.php`): `hs_code`, `origin_country` after `compare_at_price`. Import: same headings; `pg_product_customs_import_row()` normalises the cells, a bad cell leaves the whole row out and names it in its own error ("…HS code or country of origin is not valid"); an empty cell leaves the stored value, as for every column.
- Not yet: the products external API (`includes/api/schema.php` was held by #3298, so the fields go with C2), the mass edit screen and the product list's in-place edit.

## Why these rules?
- One HS code for a mixed catalogue misdeclares goods, so the code is per product with a shop fallback (#3408 (c)). Two empty columns change nothing on a running site.
- A letter or space inside a code is refused, not stripped: the carrier would declare a code nobody wrote.

Related: commerce/carrier-label-shippo.md, commerce/carrier-label-sendcloud.md, workflow/decisions-made-schema-5.md
