---
name: csv-import-headings
description: How Import Products and Import Campaign Profiles match headings (pg_import_heading: case, spaces, dashes), the products name-column refusal, and blank submitted-form rows passed over
module: ecommerce, forms
---

## How is a heading matched to a column?
- `pg_import_heading()` (`includes/fn/ecommerce.php`): trimmed, lower-cased as UTF-8, each run of spaces or `-` read as one `_`. `Price`, ` PRICE `, `Short Description`, `extra-shipping cost` all match.
- Import Products runs it in `convert_column_name()` before the `switch`; the custom field labels are still compared by `mb_strtolower()` first, and the `sfc_` / `sfu_` prefixes are read from the raw heading, as before.
- Import Campaign Profiles runs it on each heading before comparing; `$columns['name']` and `$columns['enabled']` are read with `isset()`.
- Before, both compared exactly: `name,Price,Code` made products with no price and code and reported them imported; `name,Subject,Body` made empty profiles.

## What does Import Products do without a name column?
- It refuses the file on the file field: "The file has no name column. Add a column with the heading name for the product ID (SKU)." Before, `$name_key` was undefined, every row warned and was skipped, and the screen said only "No product have been imported or updated."

## What does Import Submitted Forms do with a blank row?
- `pg_import_row_is_blank()` (`includes/fn/forms.php`) passes over a row with no value (a blank line is `array(null)`, a row of commas empty strings) before the `INSERT INTO forms`, uncounted. Before, each became an empty submitted form with its own reference code and counted as imported. `'0'` is a value.

## What is still open?
- Naming headings that match no column or field, naming product rows dropped while "Update Existing Products" is off, file-upload cells that resolve to no editable file, and reading `;` files: a follow-up using the CSV helpers beside `pg_csv_open()` in `includes/fn/core.php`.

## How is it checked?
- `tests/import_headings_test.php` (both helpers, and that the three screens call them). The import loops need a database; not run on a live instance.
