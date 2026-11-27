---
name: import-squarespace
description: Squarespace import (2026.11.0, kind squarespace, 11.0.282): site XML through the WordPress reader, products and customers CSV, both old product addresses, weight unit asked, no subscribed customers
module: importers
---

## Where is it?
- Reader `includes/import/squarespace.php`: the site XML goes on to the WordPress reader (`import-wordpress.md`), products to `write_product.php` (`import-woocommerce.md`), customers to `write_contact.php` (`import-shopify.md`). Built by PR #12075.
- Tests: `tests/import_squarespace_test.php` on `tests/fixtures/import/squarespace_products.csv`, `squarespace_customers.csv` and `squarespace_site.xml`, built from Squarespace's help pages and the export's public shape (`import-sources.md`), not from a real export.

## Which kind is it and how is it told?
- `imports.kind` `squarespace`, added by step 11.0.282 with `webflow` and `wix` (their readers come later; `pg_import_kinds()` lists only kinds with a reader). Owner (2026-11-27): the three are ENUM values (agent channel poll 253, `workflow/decisions-made-schema-53.md`).
- `pg_import_detect_kind()`: a WXR file whose first 8 KB say "squarespace" (its images sit on `static1.squarespace.com`); a CSV header with `Product Page` and `Product URL`, or `First Name`, `Last Name`, `Email` and `Customer Since`. A WXR without the word stays WordPress.
- The screen's file note and refusal name the Squarespace files; a side card line says sign-in and the old look are left out, which is Import My Site (`import-screen.md`).

## How is the site XML read?
- A file opening with a tag (after a BOM) gets cursor `format` `xml` around the WordPress cursor; open, next, plan and the mapping rows are WordPress's (`pg_import_squarespace_mapping($file)` reads the file it is given).
- The export numbers its first item `post_id` 0 and has no `base_site_url`: the WordPress reader takes both, the key host from the channel `<link>`. An attachment caption `exc-<hex>` is a placeholder and is cleared (`pg_import_squarespace_content_item()`).
- A post's link is `/<blog page slug>/<post slug>` (the slug may hold a date path), a page's `/<slug>`: each becomes a redirect (`import-redirects.md`).

## How are products read?
- A row with a Title, Product URL or Product Page, or another Product ID, starts a product; the rows below it are its variants. Key `squarespace:product:<Product ID>` (else the URL, else the line).
- Option Name 1-3 are the axes; none is a simple product. A SKU-less variant takes `<url>-<n>`. On Sale `Yes` with a lower Sale Price: that is the price, the Price the compare-at price. Stock `unlimited` or empty is untracked.
- Physical and Service come in, a service with `shippable` false; any other type is named and skipped. Categories become name paths, Tags keywords, Visible `Yes` enabled, Hosted Image URLs the pictures; a Description without tags becomes escaped paragraphs.
- `data.addresses` holds `/<page>/<url>` (7.0) and `/<page>/p/<url>` (7.1): the file does not say which version the site ran, so both get a redirect.

## How is the weight read?
- No file names its unit. `weight_figure` (a lone decimal comma read) is converted by `pg_import_product_weight()` with the mapping row `weight_unit` (kg, g, lb, oz); the row starts on `lb` (`default`), as `products.weight` holds pounds.

## How are customers read?
- Key `squarespace:email:<address>` (lower case), else `squarespace:line:<n>`. Billing cells first, shipping when billing is empty; a "Name, Address, and Phone Number" cell is kept whole as the description, split columns fill the business columns. No mapping rows.
- No consent column: `opt_in` is false, nobody comes in subscribed. Tags become contact groups. The mailing-list ZIP is not read.
