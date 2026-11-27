---
name: import-site-builders
description: Plan (owner 2026-11-27, poll 246): importers from Squarespace, Webflow and Wix as new readers of the import framework, each from the files its owner can export; steps IB1 to IB5
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): importers from Wix, Squarespace and Webflow. The list it belongs to and its rules: `plans/global-features.md`.

## What stands today? (count of 2026-11-27)
- One framework (`includes/fn/importers.php`, the screen `import.php`, `platform/import-framework.md`): a source is a reader in `includes/import/<kind>.php` (open, next, plan, an optional mapping) over an uploaded file, told apart from the file's first 8 KB; the writers for pages, records, products, contacts, files, comments, users and redirects are shared.
- Readers: WordPress (its XML export), WooCommerce and Shopify (CSV). Every import has a dry run, runs in short steps, can be carried on and undone for 30 days.
- Nothing reads another service's API or asks for its password: an import starts from a file its owner downloaded.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- IB1 (S) The count of what each service lets its owner take out. For Squarespace, Webflow and Wix: which files (pages and posts, products, contacts, collections), their format and columns, read from each service's own help pages; each source page named. Written as `platform/import-site-builders-census.md`, with for each file the reader and the writers it needs and what cannot be brought over. No product code. No query carries the product's name.
  - If a service gives no file for a kind (pages of a site whose service exports none), the note says so and that kind is left out; nothing is read from a live site.
- IB2 (M) Squarespace. Its site export is in WordPress's format: the file is told apart from a WordPress one and listed as "Squarespace", read by the WordPress reader with what differs (page kinds, galleries, addresses for the redirects). Its products file becomes products through the product writer.
- IB3 (M) Webflow. A collection's CSV becomes records of a content type chosen in the mapping step, its columns paired with the type's fields; its products CSV becomes products; each item's old address gets its redirect.
- IB4 (M) Wix. Its products and contacts files become products and contacts. Posts and pages only as far as IB1 found a file for them.
- IB5 (S) Close-out: `platform/import-screen.md` and the framework's notes corrected (the kinds, how each is told apart), new `platform/import-squarespace.md`, `import-webflow.md`, `import-wix.md`, this file deleted.

## What does every step keep?
- `platform/import-decisions.md`: one framework, a new source is a reader; nothing existing is overwritten; a reader is pure and tested on fixture files; the shared writers are reused; authors come in as disabled users.
- A fixture is built from the columns the service documents, and the PR says so: no session has an account there. A real export the owner or a customer gives later is added as a fixture by a small fix.
- The screen's file field and its refusal text name the new kinds; an unknown file is still refused.
- No table and no column.

## What is left out?
- A sign-in to the other service: the framework reads files only.
- The old site's look and its pages read by their address: that is Import My Site (`platform/import-design-fetch.md`, `import-my-site-scans.md`), which these readers do not change. The import screen says so beside the three new kinds.
