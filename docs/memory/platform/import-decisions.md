---
name: import-decisions
description: Why the content importers (WordPress, WooCommerce, Shopify, Squarespace, Webflow, Wix) are built as they are: tables, nothing overwritten, readers on fixtures, reused writers, authors, what stays out
---

## Why an import framework and not one screen per source?
- Owner, 2026-11-02: a site coming from elsewhere was retyped. One framework (`import-framework.md`) takes every export: upload, dry run, mapping, resumable run, report, 30-day undo; each source is a reader (`import-wordpress.md`, `import-woocommerce.md`, `import-shopify.md`).

## Why `imports` and `import_items` and no column on the target tables?
- #5034 question 1: undo and "imported already" need to know what a run made; a column on records, pages, files, comments and products would touch five hot tables. `import_items` holds kind, id and source key.

## Why is nothing overwritten?
- An agency moves a client once and runs it again when it stopped: an item with the same source key, a product with the SKU, a contact with the address is skipped and named; a second run picks up where the first stopped. A redirect never replaces a name in use (`import-redirects.md`).

## Why are readers pure and tested on fixtures?
- Exports vary by plugin and version; nothing is guessed from a live site. `tests/fixtures/import/` keeps a WXR (posts, pages, attachments, comments, a custom post type), a WooCommerce CSV with variations, Shopify products with options and a customers file.
- Site builders (owner, 2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): each fixture is built from the columns the service documents, and the PR says it is not a real export (`import-sources.md`).

## Why does an importer read files only?
- Squarespace, Webflow and Wix came in as readers of their owner's export files, never a sign-in to the service; their look and pages by address are Import My Site (`import-design-fetch.md`), which the import screen names.

## Why are the existing writers reused?
- The product import keeps its screen; its row writer and price reader are shared, not copied (`import-woocommerce.md`). Media goes through `pg_curl_run()` with the upload caps and the stored-name rule (`import-media.md`); a failed fetch leaves its text and a note, never a broken row.

## How do authors come in, and who owns a record?
- #5034 question 2 (owner): as disabled panel users, no password, no sign-in; #6186 (architect): `user.user_disabled_at` and the sign-in gates (`workflow/decisions-made-schema-9.md`). A record belongs only to the account the import made, never an existing one matched by its unverified address (`import-records.md`).

## What stays out on purpose?
- Private and password-protected WordPress posts (a record's visibility does not follow complete), spam, pingbacks, revisions, menu items, block editor internals; categories become names matched to existing choices, not new records.
