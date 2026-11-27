---
name: import-site-builders
description: Plan (owner 2026-11-27, poll 246): importers from Squarespace, Webflow and Wix as new readers of the import framework, each from the files its owner can export; steps IB1 to IB5
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): importers from Wix, Squarespace and Webflow. The list it belongs to and its rules: `plans/global-features.md`.

## What stands today? (count of 2026-11-27)
- One framework (`platform/import-framework.md`): a source is a reader in `includes/import/<kind>.php` over an uploaded file, told apart from its first 8 KB; the writers are shared. Readers: WordPress, WooCommerce, Shopify. An import has a dry run and a 30-day undo.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- IB1 (S) The count of what each service lets its owner take out: done, `platform/import-site-builders-census.md` and its three files. Each step reads its service's file first.
- IB2 (M) Squarespace.
  - Its site export is WordPress's format: when the file's first 8 KB name Squarespace it is listed as "Squarespace" and read by the WordPress reader, a post's old address `/<blog page slug>/<post slug>`; a file that does not say so runs as WordPress, as today.
  - Its products CSV: a new reader making the product item the Shopify reader makes, a product's first row and its variant rows below; old addresses in both forms the census names.
  - The weight unit no file carries: the import's plan step asks it (the site's own unit chosen at first) for every products file without one.
  - Its customers CSV becomes contacts. It has no consent column, so no address comes in as subscribed; the mailing list ZIP is left out, its headings being unknown.
- IB3 (M) A collection's CSV, Webflow's and Wix's alike.
  - One reader with a mapping step: the person chooses the form the rows become records of and pairs each column with one of its fields, the pairs guessed from matching names; the record writer takes the paired fields beside a post's parts.
  - Rich text comes in as HTML through the cleaner the WordPress reader uses, pictures through the media fetch; a reference column stays text. Webflow's item gets its old address; Wix's file has none.
- IB4 (M) Webflow's products and redirects.
  - The products CSV through a new reader making the same product item; types other than physical and service are counted as skipped. The 301 redirects CSV through the redirect writer; a line with `(.*)` is counted as skipped and named in the report.
  - The census could not open Webflow's help pages: the worker reads them first and, if they still refuse, builds on the import template's columns and says so in the PR.
- IB5 (M) Wix. The products CSV in both layouts, told apart by `handle` or `handleId`, through a new reader; the contacts CSV becomes contacts, its e-mail subscription status the consent and its labels the tags, the headings paired in a mapping step since no page lists them.
  - The product item gains brand, barcode and cost as optional parts, and the product writer stores them where the product already has the field.
- IB6 (S) Close-out: `platform/import-screen.md` and the framework's notes corrected (the kinds, how each is told apart), new `platform/import-squarespace.md`, `import-webflow.md`, `import-wix.md`; the census files and this file deleted.

## What does every step keep?
- `platform/import-decisions.md`: a new source is a reader; nothing existing is overwritten; a reader is pure and tested on fixture files; the shared writers are reused.
- A fixture is built from the columns the service documents, and the PR says so: no session has an account there.
- The screen's file field and refusal text name the new kinds.
- No table and no column.

## What is left out?
- A sign-in to the other service: the framework reads files only.
- The old site's look and pages read by their address: that is Import My Site (`platform/import-design-fetch.md`), and the import screen says so beside the three new kinds.
