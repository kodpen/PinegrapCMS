---
name: import-webflow
description: Webflow import (2026.11.0, kind webflow): which of its three CSV files is which, products as product items, the old /product/<handle> address, 301 redirects and the skipped (.*) lines
module: importers
---

## Where is it?
- Reader `includes/import/webflow.php` (PR #12153): a collection file goes to the shared collection reader (`import-collection-csv.md`, PR #12134), the products file to `write_product.php`, the redirects file to `write_redirect.php`. It requires `squarespace.php` for its decimal cell and plain-text description helpers.
- Tests: `tests/import_webflow_test.php` on `tests/fixtures/import/webflow_products.csv` and `webflow_redirects.csv`, built from the columns of Webflow's import template as search text gives them; Webflow's help pages answered 403 (`import-sources.md`), so no column is confirmed from a real export.

## How is each file told apart?
- `pg_import_detect_kind()` says `webflow` for a header with `Product Handle` and `Variant Price`; for a two-cell first line `source,target` (or the reader's other headers below), or an old path starting with `/` and a path or web address; and for a collection (`Item ID` + `Slug`).
- The reader decides again with `pg_import_webflow_file_kind()` (pure; it also takes `old path`/`new path` and `from`/`to` headers). The cursor carries `webflow_file` `products` or `redirects`; a cursor without it is a collection's, as saved before.
- Mapping: products ask Product group, Folder for pictures and Unit of the Weight column (pounds at first), as Squarespace's do (`import-squarespace.md`); redirects ask nothing.

## How are products read?
- Webflow writes one row per variant with the product cells repeated: consecutive rows with one `Product Handle` (case ignored; else `Product ID`) are one product. Key `webflow:product:<handle>`.
- Option1-3 Name are the axes, Option1-3 Value a variant's choices; none is a simple product. A SKU-less variant takes `<handle>-<n>`.
- `Variant Price` and `Variant Compare-at Price` become minor units through `pg_products_import_price_cents()`, as the product import reads them (grouping, decimal comma, a currency sign or code); a compare-at price not above the price is dropped. An empty `Variant Inventory` is untracked.
- Product Type Physical (or empty) and Service come in; Digital, Membership, Advanced are named by type and skipped by the product writer. `Requires Shipping` true/false sets shippable; empty means a service is not shipped.
- `Product Categories` split at `;`, each a top-level name. Pictures: the first row's `Main Variant Image` and `More Variant Images`; each variant gets its own `Main Variant Image`. A cell named Archived or Draft that is true makes the product disabled.

## Which redirects come in?
- A product: `/product/<handle>` (`data.addresses`, `pg_import_redirect_old_addresses()`).
- The 301 redirects file: one `redirect` item per row, `data.from` / `data.to`, key `webflow:redirect:<short link name>` (lower case), so a second row with the same old path is skipped as imported already.
- A row with `(.*)` or `%1` is skipped with a reason naming it (Webflow's wildcard has no exact short link; the person adds a pattern on Short Links); a row without a new address too. `pg_import_precheck_redirect()` gives the dry run that reason, `pg_import_write_redirect()` returns it as skipped.
