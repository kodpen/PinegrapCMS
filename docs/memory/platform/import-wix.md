---
name: import-wix
description: Wix import (2026.11.0, kind wix): collection, products (two layouts) and contacts CSV told apart, product brand, barcode and cost, contacts paired column by column, no old address
module: importers
---

## Where is it?
- Reader `includes/import/wix.php` (PR #12164): a collection file goes to the shared collection reader (`import-collection-csv.md`), products to `write_product.php`, contacts to `write_contact.php`. It reuses helpers of `webflow.php` and `squarespace.php`.
- Tests: `tests/import_wix_test.php` on `tests/fixtures/import/wix_products.csv`, `wix_products_v1.csv` and `wix_contacts.csv`. Products fixtures follow Wix's help pages, contacts headings are assumed (`import-sources.md`).

## How is each file told apart?
- `pg_import_detect_kind()` says `wix` for the headers `import-sources.md` lists.
- The reader decides again with `pg_import_wix_file_kind()` (pure; `pg_import_wix_column()` keeps letters and digits, so `productOptionName[1]` is `productoptionname1`). The cursor carries `wix_file` `products` or `contacts`; a cursor without it is a collection's.

## How are products read?
- A `PRODUCT`/`Product` row starts a product; `VARIANT` rows are its variants, `MEDIA` rows (current layout) its pictures. Key `wix:product:<handle>`. A media id (`abc_123~mv2.jpg`) becomes `https://static.wixstatic.com/media/<id>`; the previous layout's `productImageUrl` splits at `;`.
- Option names `productOptionName1-6`; a variant's choice is `productOptionChoices<n>` (current) or `productOptionDescription<n>` (previous); a swatch `#FFFFFF:White` is its label. No option is a simple product, its empty cells filled from a variant row when the export gives one.
- Current layout: a variant's own price and `strikethroughPrice`, else the product's; the strikethrough is the compare-at price only above the price. Previous layout: product price plus the variant's `surcharge` (below zero for a cheaper variant; never under 0), less `discountMode`/`discountValue` (AMOUNT off, PERCENT through `pg_percent_off()`), the undiscounted price the compare-at. Cells read as the product import reads them (`pg_import_wix_cents()`: 1,299 is 1299, a sign or code allowed).
- `inventory`: a number as written (2.0 is 2, below zero 0, 1,000 grouped), `OUT_OF_STOCK`/`OutOfStock` 0, `IN_STOCK`/`InStock` or empty untracked. `visible` FALSE switches the product or the variant off. Categories: `primaryCategorySlug` first, then `categorySlugs` (`;`); previous layout `collection`.
- Physical products only (all shippable); no address column, so no redirect. Weight unit asked as Squarespace's (`import-squarespace.md`).

## Where do brand, barcode and cost go?
- The product item (every reader's, optional) carries `brand`, `barcode` and `cost` (minor units); a variant `barcode` and `cost`.
- `pg_import_product_row()` writes `brand` (cut to 100, the column). `pg_import_product_barcode()` keeps a barcode through `pg_assign_product_barcode()` when `BARCODE_ENABLED`; a code that fails the site's barcode type or is held by another product is a note.
- Cost is read, not stored: a product has no cost field; Accounting averages it from purchase invoices (`erp_product_costs`, `erp/erp-stock-moves-and-cost.md`), which an import would skew.

## How are contacts read?
- No help page lists the headings, so the mapping step has one `select` row per part (`contact_<part>`, `pg_import_wix_contact_parts()`), choices `col_<n>` = the headings, each starting on the column `pg_import_wix_contact_guess()` finds; "Leave out" is allowed.
- The item keeps its cells as `data.columns` and the parts read with the guess (dry run; key `wix:email:<address>`, else `wix:line:<n>`). The writer rereads the cells with the chosen pairs (`pg_import_contact_pairs()`, `pg_import_contact_from_columns()` in `write_contact.php`).
- Consent: only a status `Subscribed` (or yes/true/1) is `opt_in` (`pg_import_contact_subscribed()`); unsubscribed, never subscribed, not set and pending come in refused. Labels split at `;`, else `,`, become contact groups.
