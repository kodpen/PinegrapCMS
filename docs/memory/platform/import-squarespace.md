---
name: import-squarespace
description: Squarespace products and customers CSV reader (2026.11.0): variant rows, sale price, both old addresses, weight without a unit, services not shipped, no subscribed customers
module: importers
---

## Where is it?
- Reader `includes/import/squarespace.php`; products go to `write_product.php` (`import-woocommerce.md`), customers to `write_contact.php` (`import-shopify.md`). Tests: `tests/import_squarespace_test.php` on `tests/fixtures/import/squarespace_products.csv` and `squarespace_customers.csv`, built from the columns Squarespace's help pages document (`import-site-builders-census-squarespace.md`), not from a real export.

## Is it a kind on the import screen yet?
- No. `imports.kind` is an ENUM of wordpress, woocommerce, shopify; adding `squarespace` is a schema change, asked of the architect in the IB2 PR. Until then nothing detects the files or calls the reader, and the Squarespace XML still runs as WordPress.

## Which file is it?
- Header cells are named by `pg_import_squarespace_column()`: lower case, spaces folded, the ` [Non Editable]` note dropped. `Product Page` with `Product URL` is products; `Email`, `First Name` and `Customer Since` is customers.

## How are products read?
- A row with a Title, Product URL or Product Page, or a Product ID other than the current one, starts a product; the rows below it are its variants. Key `squarespace:product:<Product ID>` (else the URL, else the line).
- Option Name 1-3 (first non-blank in the rows) are the axes; no axes is a simple product. A SKU-less variant takes `<url>-<n>`.
- On Sale `Yes` with a Sale Price below the Price: the sale price is the price, the Price the compare-at price. Stock `unlimited` or empty is untracked.
- Physical and Service are products, a service with `shippable` false (written `products.shippable = 0`); any other type is named by it and the writer skips it.
- Categories (`/a, /a/b-c`) become name paths ("B c"), Tags the keywords, Visible `Yes` enabled, Hosted Image URLs (space separated) the pictures. A Description without tags is escaped into paragraphs; HTML is kept.
- `data.addresses` holds both old addresses, `/<page>/<url>` (7.0) and `/<page>/p/<url>` (7.1): the file does not say which version the site ran.

## How is the weight read?
- No Squarespace file names its unit. The reader hands the figure as `weight_figure` (a lone decimal comma read) and `weight` empty; `pg_import_product_weight()` in `write_product.php` converts it to pounds with the mapping row `weight_unit` (kg, g, lb, oz), pounds when none was asked. The row's `default` is `lb`: the site stores no unit of its own and `products.weight` holds pounds.

## How are customers read?
- Key `squarespace:email:<address>` (lower case), else `squarespace:line:<n>`. Billing cells first, shipping when billing is empty; a "Name, Address, and Phone Number" cell is kept whole as the description, split columns (address, city, state, zip, country, phone) fill the business columns.
- No consent column: `opt_in` is always false, so no address comes in as subscribed. Tags become contact groups. The mailing-list ZIP is not read.
