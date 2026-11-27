---
name: import-site-builders-census-webflow
description: What Webflow lets an owner export (2026-11-27, from search text, the help pages refused): collection, products and redirect CSVs, the code ZIP, the reader and writers each needs
---

## Which help pages is this read from?
- Under `help.webflow.com/hc/en-us/articles/`: W1 `33961290794771` import/export Collection content; W2 `33961342531859` migrate CMS content; W3 `33961361857811` import/export products; W4 `33961211526291` 301 redirects; W5 `33961386739347` export site code; W6 `33961344521235` form submissions; W7 `36046006227731` User Accounts; W8 `33961244391059` collection URLs; W9 `33961314889875` product URLs.
- Every page answered 403 on 2026-11-27; the lines below come from a search engine's text of those pages and are unconfirmed. A step that builds on a column reads its page first.

## What does a collection CSV hold?
- CMS panel > the collection > Export: every item, archived ones too, in the current locale only, with an Item ID column and one per field (W1).
- Rich text as HTML; a multi-reference as a comma list; a reference as text or an address; images and files as Webflow-hosted addresses that break once the site is deleted (W2).
- Reader: new, with a mapping of columns to a chosen form's fields; writer `pg_import_write_record()` (`includes/import/write_record.php` line 345). Its rows take only a post's parts today (title, body, summary, date, picture, categories, tags; `pg_import_record_rows()` line 178), so other columns need more mapping. Old address `/<Collection URL>/<item slug>` (W8).
- By hand today: Import Submitted Forms (`import_submitted_forms.php` line 217) fills the fields whose names match the headings.

## What does the products CSV hold?
- Ecommerce > Products > Export: products, variants and categories, archived too, in the import template's columns (W3).
- Columns (W3): Product handle (unique; letters, digits, dashes), name, plain description, categories (semicolons), product type (Advanced, Digital, Service, Physical, Membership), Option1 and Option2 name and value, Variant SKU, Variant price and compare-at price (plain numbers), Variant inventory, weight and dimensions (no unit; set in Ecommerce settings), Main variant image, a variant image column, Requires shipping (true/false), a tax column.
- Reader: new, building the item of `pg_import_shopify_product()` (`includes/import/shopify.php` line 291) for `pg_import_write_product()` (`write_product.php` line 78), which skips all but simple and variable products (line 90). Old address `/product/<slug>` (W9).

## What does the redirects CSV hold?
- Site settings > Publishing > 301 redirects > Export, its link sent by e-mail: old path, new path; a wildcard is `(.*)` with `%1` (W4).
- Writer: `pg_import_write_redirect()` (`includes/import/write_redirect.php` line 283), item `from` / `to`; it refuses `*`, and a `(.*)` line has no counterpart, a short link being one exact path. By hand today: the Short Links import, `pg_short_link_import()` (`includes/fn/short_link_redirects.php` line 2039).

## Which kinds have no file?
- Contacts: none. Form submissions export one CSV per form (W6), columns not listed; User Accounts closed on 2026-01-29 (W7). Left out.
- Pages: only the code export ZIP of a paid Workspace plan (W5): .html pages, CSS, JS, images, without CMS content, Ecommerce, forms or other locales. That is a design import (`import-design-fetch.md`); left out here.
