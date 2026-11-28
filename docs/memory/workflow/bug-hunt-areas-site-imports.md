---
name: bug-hunt-areas-site-imports
description: Bug hunt of the site-builder imports (2026-11-28, IB1 to IB3): Squarespace, Webflow, Wix and collection readers and their writers; what was fixed, what read clean, what was left
module: importers
---

## What was the area?
- Named by the architect (agent channel message 15427, `bug-hunt-areas-7.md`): `includes/import/squarespace.php`, `webflow.php`, `wix.php`, `collection.php`, the writers they feed (`write_product.php`, `write_contact.php`, `write_record.php`, `write_redirect.php`), `pg_import_detect_kind()` and the Import screen (`import.php`).

## What did it fix? (PR #12247)
- Webflow and Wix prices: the cell went through `pg_import_squarespace_figure()` first, so 1,299 was read as 1.30 and 1,299.00, 19.99 USD or $19.99 were refused. Now `pg_products_import_price_cents()` reads it, as Squarespace's reader and the product import do.
- Collection date fields: a Webflow or Wix date (midnight UTC) became the day before on a site west of UTC. `pg_import_collection_date()` keeps the day in the cell's own zone; a date and time is still shown in the site zone.
- Wix previous layout: a negative `surcharge` (a variant cheaper than its product) left the variant out as "not an amount". It is now taken off the price, never below 0.
- Wix `inventory`: the cell went through the heading normaliser, so 2.0 was 20 and -3 was 3. A count is read as written; a comma grouping thousands is dropped.
- Upload: a Webflow redirects file headed `old path,new path`, `from,to` or `redirect from,redirect to`, which the reader reads, was refused as unknown by `pg_import_detect_kind()`.
- Tests: `tests/import_site_builders_hunt_test.php`.

## What was left?
- A variable product's weight is its first row's for every variant (the set's common columns): all six readers do so; a weight per variant would be a change of the product writer, not a reader fault.
- Squarespace `Stock` with a grouping comma (1,000) is read as untracked; Squarespace writes plain counts.
- Wix category slugs (`summer-sale`) become group names as written; Squarespace's reader turns slugs into names.

## What read clean?
- Squarespace: products grouped by their first row, both old addresses, sale price, stock, categories, customers' billing then shipping cells, no consent; the site XML through the WordPress reader with the `exc-` caption cleared.
- Webflow: rows grouped by handle, axes, SKU fallback, compare-at only above the price, shippable by type, redirect rows with a wildcard skipped with their reason, keys by short link name.
- Wix: both layouts told apart, media ids to the media host, swatch labels, discounts, contacts paired by the chosen columns with only Subscribed as consent.
- Collection reader: keys, drafts, Wix `wix:image://` addresses; writers: contact precheck, redirect add and destination checks, product SKU holders.
- Import screen: CSRF, upload refusals, mapping kept to the offered choices, a field row of another form dropped.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-7.md, docs/memory/platform/import-sources.md
