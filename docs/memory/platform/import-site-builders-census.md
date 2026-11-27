---
name: import-site-builders-census
description: What Squarespace, Webflow and Wix let an owner export for the importers (2026-11-27): kinds with a file, readers and writers each needs, what no file carries
---

## Which file holds what?
- Squarespace: `import-site-builders-census-squarespace.md`; Webflow: `import-site-builders-census-webflow.md`; Wix: `import-site-builders-census-wix.md`. Each names the help pages its lines come from.
- Read on 2026-11-27 from each service's own help centre; nothing from a live site, no account, no file downloaded. A step that builds on a column reads its page again.

## Which kinds have a file?
- Squarespace: pages and one blog's posts (an XML in WordPress's format), products (CSV), contacts (customers CSV, contacts CSV, mailing list ZIP). No collection file of its own.
- Webflow: collections (a CSV each), products (CSV), 301 redirects (CSV); pages only as the code export ZIP of a paid Workspace plan. No contacts file.
- Wix: products (CSV, two layouts), contacts (CSV), collections (CSV). No file for pages or blog posts.

## Which reader and writers does each need?
- Squarespace XML: the WordPress reader (`includes/import/wordpress.php`) and its page, record, comment, file and author writers.
- Products CSV (all three): a new reader each, making the item `pg_import_shopify_product()` makes (`includes/import/shopify.php` line 291) for `pg_import_write_product()` (`includes/import/write_product.php` line 78).
- Contacts CSV (Squarespace, Wix): a new reader making the data of `pg_import_shopify_customer()` (line 412) for `pg_import_write_contact()` (`includes/import/write_contact.php` line 133).
- Collection CSV (Webflow, Wix): one reader with a column mapping, for `pg_import_write_record()` (`includes/import/write_record.php` line 345), whose rows take only a post's parts today (`pg_import_record_rows()` line 178).
- Webflow redirects CSV: `pg_import_write_redirect()` (`includes/import/write_redirect.php` line 283).
- Each new reader is named in `pg_import_kinds()` and `pg_import_detect_kind()` (`includes/fn/importers.php` lines 68, 104) and gives its old addresses through `pg_import_redirect_old_address()` (`write_redirect.php` line 44).

## What does no file carry, or the writers not take?
- The design: Import My Site (`import-design-fetch.md`). Pictures: addresses on the service's servers, fetched by `pg_import_media_fetch()` (`includes/import/media.php` line 382) only while the old site is live.
- A weight or size unit: no products file has one, and `pg_import_weight_pounds()` (`importers.php` line 480) needs it, so the owner has to give it.
- Brand, barcode, cost, size and shipping need: `pg_import_product_row()` (`write_product.php` line 218) writes them empty, 0 or fixed.
- Marketing consent: Squarespace's customers file has no column; the writer then logs refused (`write_contact.php` line 168).

## What could not be read?
- `help.webflow.com` refused every page (403): Webflow's lines are search text, unconfirmed.
- No page lists the headers of Squarespace's contacts and mailing list exports or of Wix's contacts export, nor says what marks Squarespace's XML apart from WordPress's.
