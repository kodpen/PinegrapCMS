---
name: content-importers
description: Plan (owner 2026-11-02): importers for WordPress (WXR), WooCommerce and Shopify (CSV): records, pages, media, comments, customers, products, old addresses as redirects; steps IM1-IM7
---

## What is wrong now?
- A site coming from elsewhere is retyped. The product can import a CSV of products (`import_products.php`, with its price cell reader), contacts (`import_users.php`), redirects (`platform/short-link-redirects.md`) and HTML pages into the designer (`includes/designer_import.php`: a zip or one file, assets stored, lazy `src` attributes of WordPress plugins already understood), but nothing reads a WordPress export, nothing brings posts in as records of a form, nothing keeps the old addresses, and nothing imports comments or customers.
- An agency moving a client, or a blogger leaving WordPress, decides on the first day; this is the first day.

## What is proposed?
- One import framework: an upload (WXR XML, a CSV, a zip), a dry run that lists what would be made, a mapping screen, then the job runs it in resumable chunks with a cursor file (no schema, as the search index does), a report (made, skipped with reasons, failed) and an undo list (everything created carries the import id, so one button removes it within 30 days).
- WordPress: posts and custom post types become records of a chosen form (the News and Magazine templates' Articles form, or any form: title, body as rich text, excerpt, date, author, categories and tags into the form's collection fields where they exist, featured image as the form's picture field); pages go through the designer's HTML importer; attachments are fetched into Files; comments into `comments` with their threads; authors as panel users (disabled, no password) or as names; every old address into a redirect to the new one. WooCommerce (products CSV, its variations as variant sets, categories as product groups, images fetched) and Shopify (products CSV with options, customers CSV as contacts with consent as exported) through the same framework with their own readers.
- Nothing is overwritten: an existing record, product or contact with the same source id is skipped and named; a second run picks up where the first stopped.

## Which decisions bind it?
- Readers are pure and tested on sample exports kept in `tests/fixtures/import/` (a WXR with posts, pages, attachments, comments and a custom post type; a WooCommerce CSV with variations; a Shopify CSV with options and a customers file); nothing is guessed from a live site.
- Media is fetched through `pg_curl_run()` with the size caps of uploads and the stored-name rule (`platform/stored-file-name-upload-rule.md`); a fetch that fails leaves the record with its text and a note, never a broken row.
- The product import keeps its screen; the framework reuses its row writer and price reader rather than copying them. Records go through the writer the `forms` API resource uses, so the form's rules, collection references and revisions apply.

## Which questions are open? (architect, before IM1 and IM3)
1. Where an imported row remembers its source: `import_id` and `source_key` columns on `forms`, `products`, `contacts`, `comments` and `files` (recommended: one small table `import_items` (import id, kind, id, source key) instead, no column on five tables), in the open version's migration.
2. Authors: panel users made disabled (recommended), or author names kept as a text field only.

## Where does it start?
- IM1 alone, then IM2, IM3, IM4 and IM5 together. Read `platform/README.md` and `designer/README.md` key rules, `designer/design-template-news.md` (the Articles form contract), `platform/short-link-redirects.md`, `includes/designer_import.php` (`pg_designer_import_single_html()`), `import_products.php`. Steps: `content-importers-steps.md`, `-steps-2.md`.
