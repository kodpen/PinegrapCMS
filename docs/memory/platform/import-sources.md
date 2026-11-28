---
name: import-sources
description: Which platforms' exports the import screen reads (2026.11.0): the six kinds and their files, the order pg_import_detect_kind() tells them apart in, and what each site builder exports no file for
module: importers
---

## Which kinds and files are read?
- `pg_import_kinds()` (`includes/fn/importers.php`) lists the six kinds that have a reader in `includes/import/<kind>.php`; the screen's file note and refusal take their labels (`import-screen.md`).
- `wordpress`: the WXR export (`import-wordpress.md`). `woocommerce`: the product exporter's CSV (`import-woocommerce.md`). `shopify`: products and customers CSV (`import-shopify.md`).
- `squarespace`: the site XML, products and customers CSV (`import-squarespace.md`). `webflow`: a collection, products and 301 redirects CSV (`import-webflow.md`). `wix`: a collection, products (two layouts) and contacts CSV (`import-wix.md`). Collections of both: `import-collection-csv.md`.
- `imports.kind` holds all six; `squarespace`, `webflow` and `wix` came with step 11.0.282 (`workflow/decisions-made-schema-53.md`). Built in 2026.11.0: #12075 (Squarespace), #12134 (collections), #12153 (Webflow products and redirects), #12164 (Wix).

## In which order is a file told apart?
- `pg_import_detect_kind()` (pure) reads the first 8 KB, a BOM dropped; the first test that holds wins, so a new reader's test goes where it cannot catch an earlier kind's file.
- 1. An RSS with `wordpress.org/export/`: `squarespace` when the text says "squarespace", else `wordpress`.
- 2. CSV header, cells lower case: `handle` + `body (html)`, or the name columns + `accepts (email) marketing` → shopify. 3. `id`, `type`, `sku`, `regular price` → woocommerce.
- 4. `product page` + `product url`, or the name columns + `customer since` → squarespace. 5. `product handle` + `variant price`; a two-cell `source,target` (also `old path,new path`, `from,to`, `redirect from,redirect to`) or `/old,new` line; `item id` + `slug` → webflow.
- 6. `id` + `owner`, or `id` + `created date` + `updated date`; `handle`/`handleid` + `fieldtype`; `first name` or `last name` with `labels` or an e-mail subscriber/subscription status → wix.
- No match: the upload is refused. A reader with several files decides again itself (`webflow_file`, `wix_file` in the cursor; none means a collection).

## What does no site-builder file carry?
- Squarespace: store, album, portfolio, index and calendar pages, other blog pages, drafts and styles are not in the XML; no collection file; the contacts panel and mailing-list ZIP are not read (no page lists their headings).
- Webflow: no contacts file (User Accounts closed 2026-01-29; form submissions are one CSV per form, left out); pages only as a paid plan's code ZIP, left out.
- Wix: no export of pages or blog posts; app collections (Stores, Bookings, Forms, Members) export as other collections do.
- None of the three: the look and the pages by address, which Import My Site fetches (`import-design-fetch.md`); a sign-in to the service. Pictures are addresses on the service's servers, fetched only while the old site is live (`import-media.md`).
- No products file names a weight unit: the mapping asks it (`import-squarespace.md`).

## Where do the columns come from?
- Each service's help centre, read 2026-11-27, no real export: Squarespace `support.squarespace.com/hc/en-us/articles/` (206566687 site XML, 115000378108 product columns, 229497568 customers); Wix `support.wix.com/en/article/` (`wix-stores-exporting-and-importing-your-products`, `wix-stores-importing-products-to-wix-stores`, `exporting-your-contacts`).
- Webflow's help pages answered 403: its columns are search text of the import template. Wix's contacts headings are listed nowhere and are assumed. A change to a reader reads the service's page again.
